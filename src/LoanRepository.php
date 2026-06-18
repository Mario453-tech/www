<?php

class LoanRepository
{
    private PDO $db;

    public function __construct()
    {
        try {
            $this->db = Database::getInstance()->getConnection();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LoanRepository', '__construct failed', $e);
            }
            throw $e;
        }
    }

 /**
 * Processes due installments for all active loans.
 * Called by the TICK loop.
 */
    public function processInstallments(): void
    {
        try {
            $stmt = $this->db->query("
                SELECT * FROM loans
                WHERE status = 'active'
                AND next_installment_at <= NOW()
            ");

            $loans = $stmt->fetchAll();

            foreach ($loans as $loan) {
                $this->processInstallment($loan);
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LoanRepository', 'processInstallments failed', $e);
            }
        }
    }

 /**
 * Processes a single installment payment.
 * @param array<string, mixed> $loan
 */
    private function processInstallment(array $loan): void
    {
        try {
            // Sprawdz istnienie gracza — brak gracza to nie to samo co brak srodkow.
            // Check player existence — missing player is not the same as insufficient funds.
            $playerStmt = $this->db->prepare("SELECT id FROM players WHERE id = :id");
            $playerStmt->execute([':id' => $loan['player_id']]);
            if (!$playerStmt->fetch()) {
                return;
            }

            $paid         = false;
            $newRemaining = 0.0;

            // Transakcja — updateCash + UPDATE loans musza byc atomowe; rozny blad DB nie moze zostawic cash odjety bez aktualizacji pozyczki (Rule 5).
            // Transaction — updateCash + UPDATE loans must be atomic; a DB error must not leave cash deducted without the loan being updated (Rule 5).
            $this->db->beginTransaction();
            try {
                // updateCash dolaczy do tej transakcji przez wzorzec $ownTx (inTransaction=true).
                // updateCash will join this transaction via $ownTx pattern (inTransaction=true).
                $playerObj = new Player((int)$loan['player_id']);
                $paid = $playerObj->updateCash(
                    -(float)$loan['installment_amount'],
                    \FinancialTransactionService::TYPE_LOAN_PAYMENT,
                    'Splata raty kredytu #' . $loan['id']
                );

                if ($paid) {
 // INSTALLMENT PAID

 // Atomowe odjecie salda w SQL — zapobiega TOCTOU przy rownoleglych tickach (Rule 2).
 // Atomic balance decrement in SQL — prevents TOCTOU under concurrent ticks (Rule 2).
                    $this->db->prepare("
                        UPDATE loans
                        SET remaining_amount = GREATEST(0, remaining_amount - :amount)
                        WHERE id = :id AND player_id = :player_id
                    ")->execute([
                        ':amount'    => $loan['installment_amount'],
                        ':id'        => $loan['id'],
                        ':player_id' => $loan['player_id'],
                    ]);

 // Odczyt swiezej wartosci po dekrementacji — wewnatrz transakcji = spojny odczyt (Rule 2).
 // Re-read fresh value after decrement — inside transaction = consistent read (Rule 2).
                    $freshStmt = $this->db->prepare("SELECT remaining_amount FROM loans WHERE id = :id AND player_id = :player_id");
                    $freshStmt->execute([':id' => $loan['id'], ':player_id' => $loan['player_id']]);
                    $newRemaining = (float)($freshStmt->fetchColumn() ?? 0);

                    if ($newRemaining <= 0) {
 // LOAN FULLY REPAID
                        $this->db->prepare("
                            UPDATE loans
                            SET remaining_amount = 0,
                                status = 'paid_off',
                                paid_off_at = NOW()
                            WHERE id = :id AND player_id = :player_id
                        ")->execute([':id' => $loan['id'], ':player_id' => $loan['player_id']]);

                    } else {
 // Schedule next installment
                        $this->db->prepare("
                            UPDATE loans
                            SET next_installment_at = DATE_ADD(NOW(), INTERVAL :hours HOUR),
                                status = 'active'
                            WHERE id = :id AND player_id = :player_id
                        ")->execute([
                            ':hours'     => $loan['installment_frequency'],
                            ':id'        => $loan['id'],
                            ':player_id' => $loan['player_id'],
                        ]);
                    }

 // Record payment
                    $this->db->prepare("
                        INSERT INTO loan_payments
                        (loan_id, player_id, amount, payment_type, created_at)
                        VALUES (:loan_id, :player_id, :amount, 'installment', NOW())
                    ")->execute([
                        ':loan_id'   => $loan['id'],
                        ':player_id' => $loan['player_id'],
                        ':amount'    => $loan['installment_amount'],
                    ]);

                } else {
 // INSUFFICIENT FUNDS - mark as overdue
                    $this->db->prepare("
                        UPDATE loans
                        SET status = 'late',
                            late_since = COALESCE(late_since, NOW())
                        WHERE id = :id AND player_id = :player_id
                    ")->execute([':id' => $loan['id'], ':player_id' => $loan['player_id']]);
                }

                $this->db->commit();
            } catch (\Throwable $e) {
                if ($this->db->inTransaction()) $this->db->rollBack();
                throw $e;
            }

            // Wiarygodnosc — operacja poboczna po commit, blad nie cofa transakcji (Rule 6).
            // Credibility — side operation after commit, failure must not roll back the transaction (Rule 6).
            if ($paid) {
                // Wiarygodnosc firmy: pelna splata / rata w terminie / Company credibility: full repayment / on-time installment
                $this->applyCredibility((int)$loan['player_id'], $newRemaining <= 0 ? 'loan_fully_repaid' : 'loan_installment_paid_on_time');
            } else {
                // Wiarygodnosc firmy: duze opoznienie w splacie (przejscie w 'late').
                // Company credibility: major payment delay (transition into 'late').
                $this->applyCredibility((int)$loan['player_id'], 'major_payment_delay');
            }

        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LoanRepository', 'processInstallment failed', $e, [
                    'loan_id'   => $loan['id'] ?? null,
                    'player_id' => $loan['player_id'] ?? null,
                ]);
            }
        }
    }

 /**
 * Stosuje zdarzenie wiarygodnosci firmy (guarded — nigdy nie wywraca splaty).
 * Applies a company-credibility event (guarded — never breaks repayment).
 */
    private function applyCredibility(int $playerId, string $eventKey): void
    {
        try {
            (new CompanyCredibilityService($this->db))->applyEvent($playerId, $eventKey);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LoanRepository', 'credibility hook FAILED', $e, [
                    'player_id' => $playerId, 'event_key' => $eventKey,
                ]);
            }
        }
    }
}
