<?php

/**
 * Handles active loan loading and repayments.
 * Obsluguje ladowanie aktywnych kredytow i splate rat.
 */
trait BankRepaymentTrait
{
 /** @return list<array<string, mixed>> */
    public function getActiveLoans(int $playerId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM loans
                WHERE player_id = :pid AND status IN ('active', 'late')
                ORDER BY created_at DESC
            ");
            $stmt->execute([':pid' => $playerId]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            GameLog::error('BankService', 'getActiveLoans failed', [
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

 /**
 * Repayment mode can be installment, multiple or full.
 * Tryb splaty moze byc installment, multiple albo full.
 *
 * @return array<string, mixed>
 */
    public function repay(int $loanId, int $playerId, string $mode, int $count = 1): array
    {
        try {
            $db = $this->db;

 // Auto-migrate missing loan columns before repayment logic.
 // Automatycznie dodaj brakujace kolumny loans przed logika splaty.
            $this->ensureLoanColumnsExist();

            $stmt = $db->prepare("
                SELECT * FROM loans
                WHERE id = :lid AND player_id = :pid
                  AND status IN ('active','late')
            ");
            $stmt->execute([':lid' => $loanId, ':pid' => $playerId]);
            $loan = $stmt->fetch();

            if (!$loan) {
                return ['success' => false, 'message' => t('bank.err_loan_not_found')];
            }

            $installment = (float)$loan['installment_amount'];
            $remaining = (float)$loan['remaining_amount'];

            $playerStmt = $db->prepare("SELECT cash FROM players WHERE id = :pid");
            $playerStmt->execute([':pid' => $playerId]);
            $player = $playerStmt->fetch();
            $cash = (float)($player['cash'] ?? 0);

            if ($mode === 'full') {
                $toPay = $remaining;
            } elseif ($mode === 'multiple') {
                $count = max(1, (int)$count);
                $toPay = min($installment * $count, $remaining);
            } else {
                $toPay = min($installment, $remaining);
            }

            if ($toPay <= 0) {
                return ['success' => false, 'message' => t('bank.err_no_amount')];
            }

            if ($cash < $toPay) {
                return [
                    'success' => false,
                    'message' => t('bank.err_repay_no_funds', [
                        'cash' => number_format($cash),
                        'needed' => number_format($toPay),
                    ]),
                ];
            }

            $db->beginTransaction();

            $newRemaining = round($remaining - $toPay, 2);
            $isPaidOff = $newRemaining <= 0;

            $db->prepare("UPDATE players SET cash = cash - :amt WHERE id = :pid")
                ->execute([':amt' => $toPay, ':pid' => $playerId]);

            if ($isPaidOff) {
                $db->prepare("
                    UPDATE loans SET remaining_amount = 0, status = 'paid_off',
                        paid_off_at = NOW(), late_since = NULL
                    WHERE id = :id
                ")->execute([':id' => $loanId]);

 // Close active negotiations linked to the paid-off loan.
 // Zamknij aktywne negocjacje powiazane z splaconym kredytem.
                $db->prepare("
                    UPDATE bank_negotiations
                    SET status = 'cancelled', resolved_at = NOW()
                    WHERE loan_id = :lid AND status IN ('pending','approved')
                ")->execute([':lid' => $loanId]);
            } else {
                $db->prepare("
                    UPDATE loans SET
                        remaining_amount = :rem,
                        status = 'active',
                        late_since = NULL,
                        next_installment_at = DATE_ADD(NOW(), INTERVAL installment_frequency HOUR),
                        last_interest_calc_at = NOW()
                    WHERE id = :id
                ")->execute([':rem' => $newRemaining, ':id' => $loanId]);
            }

            $db->prepare("
                UPDATE bailiff_proceedings
                SET status = 'completed', completed_at = NOW()
                WHERE loan_id = :lid AND status = 'active'
            ")->execute([':lid' => $loanId]);

            $otherLate = $db->prepare("
                SELECT COUNT(*) FROM loans
                WHERE player_id = :pid AND status = 'late' AND id != :lid
            ");
            $otherLate->execute([':pid' => $playerId, ':lid' => $loanId]);
            if ((int)$otherLate->fetchColumn() === 0) {
                $db->prepare("
                    UPDATE players SET status = 'active'
                    WHERE id = :pid AND status IN ('financial_risk','under_bailiff')
                ")->execute([':pid' => $playerId]);
            }

 // Resume wells paused for cash shortage if player has cash again.
 // Wznow odwierty paused_cash, jesli gracz znow ma gotowke.
            $cashAfter = $cash - $toPay;
            if ($cashAfter > 0) {
                $db->prepare("
                    UPDATE wells SET status = 'active'
                    WHERE player_id = :pid AND status = 'paused_cash'
                ")->execute([':pid' => $playerId]);
            }

            $db->commit();

            if ($isPaidOff) {
                GameLog::info('BankService', 'Loan fully repaid', [
                    'loan_id' => $loanId,
                    'player_id' => $playerId,
                    'amount' => $toPay,
                ]);
                return [
                    'success' => true,
                    'paid_off' => true,
                    'message' => t('bank.msg_loan_paid_off', [
                        'id' => $loanId,
                        'amount' => number_format($toPay, 0, '.', ' '),
                    ]),
                ];
            }

            GameLog::info('BankService', 'Loan partially repaid', [
                'loan_id' => $loanId,
                'player_id' => $playerId,
                'amount' => $toPay,
                'remaining' => $newRemaining,
                'mode' => $mode,
            ]);

            return [
                'success' => true,
                'message' => t('bank.msg_repaid_partial', [
                    'amount' => number_format($toPay),
                    'remaining' => number_format($newRemaining),
                ]),
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('BankService', 'repay failed', [
                'loan_id' => $loanId,
                'player_id' => $playerId,
                'mode' => $mode,
                'count' => $count,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => t('common.app_error')];
        }
    }

 /**
 * Keeps backward compatibility for older callers.
 * Zachowuje zgodnosc wsteczna dla starszych wywolan.
 */
    public function repayInstallment(int $loanId, int $playerId): array
    {
        return $this->repay($loanId, $playerId, 'installment');
    }
}
