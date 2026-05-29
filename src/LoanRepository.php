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
 // Fetch current player cash
            $playerStmt = $this->db->prepare("SELECT cash FROM players WHERE id = :id");
            $playerStmt->execute([':id' => $loan['player_id']]);
            $player = $playerStmt->fetch();

            if (!$player) {
                return;
            }

            if ($player['cash'] >= $loan['installment_amount']) {
 // INSTALLMENT PAID

 // Deduct cash
                $updateCash = $this->db->prepare("
                    UPDATE players
                    SET cash = cash - :amount
                    WHERE id = :id
                ");
                $updateCash->execute([
                    ':amount' => $loan['installment_amount'],
                    ':id' => $loan['player_id']
                ]);

 // Reduce remaining balance
                $newRemaining = max(0, $loan['remaining_amount'] - $loan['installment_amount']);

                if ($newRemaining <= 0) {
 // LOAN FULLY REPAID
                    $updateLoan = $this->db->prepare("
                        UPDATE loans
                        SET remaining_amount = 0,
                            status = 'paid_off',
                            paid_off_at = NOW()
                        WHERE id = :id
                    ");
                    $updateLoan->execute([':id' => $loan['id']]);

                } else {
 // Schedule next installment
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
                }

 // Record payment
                $payment = $this->db->prepare("
                    INSERT INTO loan_payments
                    (loan_id, player_id, amount, payment_type, created_at)
                    VALUES (:loan_id, :player_id, :amount, 'installment', NOW())
                ");
                $payment->execute([
                    ':loan_id' => $loan['id'],
                    ':player_id' => $loan['player_id'],
                    ':amount' => $loan['installment_amount']
                ]);

            } else {
 // INSUFFICIENT FUNDS - mark as overdue
                $updateLoan = $this->db->prepare("
                    UPDATE loans
                    SET status = 'late',
                        late_since = COALESCE(late_since, NOW())
                    WHERE id = :id
                ");
                $updateLoan->execute([':id' => $loan['id']]);
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LoanRepository', 'processInstallment failed', $e, [
                    'loan_id' => $loan['id'] ?? null,
                    'player_id' => $loan['player_id'] ?? null,
                ]);
            }
        }
    }
}
