<?php

class InterestEngine
{
    private PDO $db;

    public function __construct()
    {
        try {
            $this->db = Database::getInstance()->getConnection();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('InterestEngine', '__construct failed', $e);
            }
            throw $e;
        }
    }

    /**
     * Accrues interest on all active loans.
     * Called by the TICK loop.
     */
    public function calculateInterest(): void
    {
        try {
            $stmt = $this->db->query("
                SELECT * FROM loans
                WHERE status IN ('active', 'late')
            ");

            $loans = $stmt->fetchAll();

            foreach ($loans as $loan) {
                $this->calculateLoanInterest($loan['id']);
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('InterestEngine', 'calculateInterest failed', $e);
            }
        }
    }

    /**
     * Accrues interest for a single loan.
     */
    private function calculateLoanInterest(int $loanId): void
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM loans WHERE id = :id");
            $stmt->execute([':id' => $loanId]);
            $loan = $stmt->fetch();

            if (!$loan) {
                return;
            }

            // Calculate time elapsed since last interest accrual
            $lastCalc = new DateTime($loan['last_interest_calc_at']);
            $now = new DateTime();
            $timeDiff = $now->getTimestamp() - $lastCalc->getTimestamp();

            // Skip if less than 1 hour has elapsed
            if ($timeDiff < 3600) {
                return;
            }

            // interest = remaining * (APR / 100) * (elapsed / year)
            $apr = $loan['interest_rate'] / 100; // Convert percentage to fraction
            $yearInSeconds = 365 * 24 * 3600;
            $timeFraction = $timeDiff / $yearInSeconds;

            $interest = $loan['remaining_amount'] * $apr * $timeFraction;
            $interest = round($interest, 2);

            // Add interest to remaining balance
            $newRemaining = $loan['remaining_amount'] + $interest;

            // Persist
            $update = $this->db->prepare("
                UPDATE loans
                SET remaining_amount = :remaining,
                    total_interest_paid = total_interest_paid + :interest,
                    last_interest_calc_at = NOW()
                WHERE id = :id
            ");

            $update->execute([
                ':remaining' => $newRemaining,
                ':interest' => $interest,
                ':id' => $loanId
            ]);

            // Record in payment history
            $payment = $this->db->prepare("
                INSERT INTO loan_payments
                (loan_id, player_id, amount, payment_type, created_at)
                VALUES (:loan_id, :player_id, :amount, 'interest', NOW())
            ");

            $payment->execute([
                ':loan_id' => $loanId,
                ':player_id' => $loan['player_id'],
                ':amount' => $interest
            ]);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('InterestEngine', 'calculateLoanInterest failed', $e, ['loan_id' => $loanId]);
            }
        }
    }
}
