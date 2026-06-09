<?php

class LoanRepository
{
    private PDO $db;

    public function __construct()
    {
        try {
            $this->db = Database::getInstance()->getConnection();
            if (class_exists('BankAccountService')) {
                (new BankAccountService($this->db))->ensureSchema();
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LoanRepository', '__construct failed', $e);
            }
            throw $e;
        }
    }

 /**
 * Przetwarza raty wymagalne w ticku / Processes installments due in the tick loop.
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
 // Pobierz aktualna gotowke gracza / Fetch current player cash.
            $playerStmt = $this->db->prepare("SELECT cash FROM players WHERE id = :id");
            $playerStmt->execute([':id' => $loan['player_id']]);
            $player = $playerStmt->fetch();

            if (!$player) {
                return;
            }

            if ($player['cash'] >= $loan['installment_amount']) {
 // Rata splacona - trzymaj gotowke, historie banku, kredyt i log raty atomowo.
 // Installment paid - keep cash, bank history, loan and payment log atomic.
                $this->db->beginTransaction();

                $debitResult = (new FinancialTransactionService($this->db))->debit(
                    (int)$loan['player_id'],
                    (float)$loan['installment_amount'],
                    FinancialTransactionService::TYPE_LOAN_PAYMENT,
                    t('bank.tx_loan_auto_installment', ['id' => (int)$loan['id']]),
                    'loan',
                    (int)$loan['id']
                );
                if (empty($debitResult['success'])) {
                    if ($this->db->inTransaction()) {
                        $this->db->rollBack();
                    }
                    GameLog::warn('LoanRepository', 'automatic installment debit failed', [
                        'loan_id' => $loan['id'] ?? null,
                        'player_id' => $loan['player_id'] ?? null,
                        'error' => $debitResult['error'] ?? 'unknown',
                    ]);
                    return;
                }

 // Zmniejsz pozostale zadluzenie / Reduce remaining balance.
                $newRemaining = max(0, (float)$loan['remaining_amount'] - (float)$loan['installment_amount']);
                $credibilityEvent = null;

                if ($newRemaining <= 0) {
 // Kredyt splacony w calosci / Loan fully repaid.
                    $updateLoan = $this->db->prepare("
                        UPDATE loans
                        SET remaining_amount = 0,
                            status = 'paid_off',
                            paid_off_at = NOW()
                        WHERE id = :id
                    ");
                    $updateLoan->execute([':id' => $loan['id']]);
                    $credibilityEvent = 'loan_fully_repaid';

                } else {
 // Zaplanuj kolejna rate / Schedule next installment.
                    $updateLoan = $this->db->prepare("
                        UPDATE loans
                        SET remaining_amount = :remaining,
                            next_installment_at = DATE_ADD(NOW(), INTERVAL :hours HOUR),
                            status = 'active'
                        WHERE id = :id
                    ");
                    $updateLoan->execute([
                        ':remaining' => $newRemaining,
                        ':hours' => $loan['installment_frequency'],
                        ':id' => $loan['id']
                    ]);
                    $credibilityEvent = 'loan_installment_paid_on_time';
                }

 // Zapisz wpis splaty raty / Record payment.
                $payment = $this->db->prepare("
                    INSERT INTO loan_payments
                    (loan_id, player_id, amount, payment_type, created_at)
                    VALUES (:loan_id, :player_id, :amount, 'installment', NOW())
                ");
                $payment->execute([
                    ':loan_id' => $loan['id'],
                    ':player_id' => $loan['player_id'],
                    ':amount' => $loan['installment_amount'],
                ]);

                $this->db->commit();

                if ($credibilityEvent !== null) {
                    $this->applyCredibility((int)$loan['player_id'], $credibilityEvent);
                }

            } else {
 // Brak srodkow - oznacz rate jako zalegla / Insufficient funds - mark as overdue.
                $updateLoan = $this->db->prepare("
                    UPDATE loans
                    SET status = 'late',
                        late_since = COALESCE(late_since, NOW())
                    WHERE id = :id
                ");
                $updateLoan->execute([':id' => $loan['id']]);

                // Wiarygodnosc firmy: duze opoznienie w splacie (przejscie w 'late').
                // Company credibility: major payment delay (transition into 'late').
                $this->applyCredibility((int)$loan['player_id'], 'major_payment_delay');
            }
        } catch (Throwable $e) {
            try {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
            } catch (Throwable) {
                // Blad rollbacku nie zmienia obslugi bledu / Rollback failure is non-fatal here.
            }
            if (class_exists('GameLog', false)) {
                GameLog::error('LoanRepository', 'processInstallment failed', $e, [
                    'loan_id' => $loan['id'] ?? null,
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
