<?php

/**
 * Handles negotiation request creation.
 * Obsluguje tworzenie wnioskow negocjacyjnych.
 */
trait BankNegotiationRequestsTrait
{
 /** @return array<string, mixed> */
    public function requestDeferral(int $playerId, int $loanId, int $days): array
    {
        GameLog::step('BankNeg', 'requestDeferral', 1, 'start', [
            'player' => $playerId,
            'loan' => $loanId,
            'days' => $days,
        ]);

        if (!array_key_exists($days, self::DEFERRAL_RATE_INCREASE)) {
            return ['success' => false, 'message' => t('bank_neg.err_invalid_days')];
        }

        try {
            $loan = $this->getLoan($loanId, $playerId);
            if (!$loan) {
                return ['success' => false, 'message' => t('bank_neg.err_loan_not_found')];
            }
            if (!in_array($loan['status'], ['active', 'late'], true)) {
                return ['success' => false, 'message' => t('bank_neg.err_deferral_status')];
            }
            if ($this->hasPendingOrApproved($loanId)) {
                return ['success' => false, 'message' => t('bank_neg.err_active_negotiation')];
            }

            $ctx = $this->buildContext($playerId, $loan);
            $feeData = $this->calculateDeferralFee($loan, $days, $ctx);
            $timeData = $this->calculateDecisionTime($loan, $ctx);
            $newRate = round((float)$loan['interest_rate'] + self::DEFERRAL_RATE_INCREASE[$days], 2);

            $this->db->beginTransaction();

            $this->db->prepare("
                INSERT INTO bank_negotiations
                    (player_id, loan_id, type, status,
                     requested_deferral_days, new_interest_rate, additional_fee,
                     bank_decision, decision_due_at, decision_hours, decision_delays,
                     approval_chance, fee_breakdown)
                VALUES (:pid, :lid, 'deferral', 'pending',
                     :days, :rate, :fee,
                     :bmsg, :due, :hours, :delays, :chance, :feebd)
            ")->execute([
                ':pid' => $playerId,
                ':lid' => $loanId,
                ':days' => $days,
                ':rate' => $newRate,
                ':fee' => $feeData['fee'],
                ':bmsg' => $timeData['bank_message'],
                ':due' => $timeData['due_at'],
                ':hours' => $timeData['total_hours'],
                ':delays' => json_encode($timeData['delays'], JSON_UNESCAPED_UNICODE),
                ':chance' => $ctx['approvalChance'],
                ':feebd' => json_encode($feeData['breakdown'], JSON_UNESCAPED_UNICODE),
            ]);

            $negId = (int)$this->db->lastInsertId();
            $this->db->commit();

            $result = [
                'success' => true,
                'negotiation_id' => $negId,
                'message' => $timeData['bank_message'],
                'decision_due' => $timeData['due_at'],
                'estimated_fee' => $feeData['fee'],
                'new_rate' => $newRate,
            ];
            if ($timeData['cfo_message']) {
                $result['cfo_message'] = $timeData['cfo_message'];
            }

            return $result;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('BankNeg', 'requestDeferral FAILED', $e, ['loan' => $loanId]);
            return ['success' => false, 'message' => t('common.app_error')];
        }
    }

 /** @return array<string, mixed> */
    public function requestRestructure(int $playerId, int $loanId, int $extensionMonths): array
    {
        GameLog::step('BankNeg', 'requestRestructure', 1, 'start', [
            'player' => $playerId,
            'loan' => $loanId,
            'months' => $extensionMonths,
        ]);

        if ($extensionMonths < 1 || $extensionMonths > 12) {
            return ['success' => false, 'message' => t('bank_neg.err_invalid_months')];
        }

        try {
            $loan = $this->getLoan($loanId, $playerId);
            if (!$loan) {
                return ['success' => false, 'message' => t('bank_neg.err_loan_not_found')];
            }
            if (!in_array($loan['status'], ['active', 'late'], true)) {
                return ['success' => false, 'message' => t('bank_neg.err_restructure_status')];
            }
            if ($this->hasPendingOrApproved($loanId)) {
                return ['success' => false, 'message' => t('bank_neg.err_active_negotiation')];
            }

            $ctx = $this->buildContext($playerId, $loan);

            $remaining = (float)$loan['remaining_amount'];
            $installment = (float)$loan['installment_amount'];
            $currentRats = ($installment > 0) ? (int)ceil($remaining / $installment) : 20;
            $newRats = $currentRats + ($extensionMonths * 2);
            $newInst = ($newRats > 0) ? round($remaining / $newRats, 2) : $installment;

            $basePct = self::RESTRUCTURE_BASE_FEE_PCT + self::RESTRUCTURE_MONTHLY_FEE_PCT * $extensionMonths;
            $effectivePct = max(0.005, min(0.14,
                $basePct
 * $ctx['market_factor'] * $ctx['credit_factor']
 * $ctx['trust_factor'] * $ctx['late_factor']
 * (1 - $ctx['cfo_fee_reduction'])
            ));
            $totalFee = (int)round($remaining * $effectivePct);

            $timeData = $this->calculateDecisionTime($loan, $ctx);

            $this->db->beginTransaction();

            $this->db->prepare("
                INSERT INTO bank_negotiations
                    (player_id, loan_id, type, status,
                     requested_extension_months, additional_fee,
                     bank_decision, decision_due_at, decision_hours, decision_delays,
                     approval_chance, fee_breakdown)
                VALUES (:pid, :lid, 'restructure', 'pending',
                     :months, :fee,
                     :bmsg, :due, :hours, :delays, :chance, :feebd)
            ")->execute([
                ':pid' => $playerId,
                ':lid' => $loanId,
                ':months' => $extensionMonths,
                ':fee' => $totalFee,
                ':bmsg' => $timeData['bank_message'],
                ':due' => $timeData['due_at'],
                ':hours' => $timeData['total_hours'],
                ':delays' => json_encode($timeData['delays'], JSON_UNESCAPED_UNICODE),
                ':chance' => $ctx['approvalChance'],
                ':feebd' => json_encode([
                    'effective_pct' => round($effectivePct * 100, 2) . '%',
                    'trust_factor' => $ctx['trust_factor'],
                    'cfo_reduction' => round($ctx['cfo_fee_reduction'] * 100, 1) . '%',
                ], JSON_UNESCAPED_UNICODE),
            ]);

            $negId = (int)$this->db->lastInsertId();
            $this->db->commit();

            $result = [
                'success' => true,
                'negotiation_id' => $negId,
                'message' => $timeData['bank_message'],
                'decision_due' => $timeData['due_at'],
                'estimated_fee' => $totalFee,
                'new_installment' => $newInst,
            ];
            if ($timeData['cfo_message']) {
                $result['cfo_message'] = $timeData['cfo_message'];
            }

            return $result;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('BankNeg', 'requestRestructure FAILED', $e, ['loan' => $loanId]);
            return ['success' => false, 'message' => t('common.app_error')];
        }
    }

 /** @return array<string, mixed> */
    public function requestRecoveryPlan(int $playerId, int $loanId): array
    {
        GameLog::step('BankNeg', 'requestRecoveryPlan', 1, 'start', [
            'player' => $playerId,
            'loan' => $loanId,
        ]);

        try {
            $loan = $this->getLoan($loanId, $playerId);
            if (!$loan) {
                return ['success' => false, 'message' => t('bank_neg.err_loan_not_found')];
            }
            if (!in_array($loan['status'], ['active', 'late'], true)) {
                return ['success' => false, 'message' => t('bank_neg.err_recovery_status')];
            }
            if ($this->hasPendingOrApproved($loanId)) {
                return ['success' => false, 'message' => t('bank_neg.err_active_negotiation')];
            }

            $ctx = $this->buildContext($playerId, $loan);

 // Recovery plan requires a genuinely difficult situation.
 // Plan naprawczy wymaga rzeczywiscie trudnej sytuacji.
            $hasBailiff = $this->db->prepare("
                SELECT id FROM bailiff_proceedings
                WHERE loan_id=:lid AND status='active' LIMIT 1
            ");
            $hasBailiff->execute([':lid' => $loanId]);
            $bailiff = $hasBailiff->fetch();

            if (!$bailiff && $loan['status'] !== 'late' && $ctx['creditScore'] > 60) {
                return ['success' => false, 'message' => t('bank_neg.err_recovery_no_bailiff')];
            }

            $remaining = (float)$loan['remaining_amount'];
            $effectivePct = max(0.005, min(0.12,
                self::RECOVERY_BASE_FEE_PCT
 * $ctx['market_factor'] * $ctx['credit_factor']
 * $ctx['trust_factor']
 * (1 - $ctx['cfo_fee_reduction'])
            ));
            $fee = (int)round($remaining * $effectivePct);

            $timeData = $this->calculateDecisionTime($loan, $ctx);

            $this->db->beginTransaction();

            $this->db->prepare("
                INSERT INTO bank_negotiations
                    (player_id, loan_id, type, status, additional_fee,
                     bank_decision, decision_due_at, decision_hours, decision_delays,
                     approval_chance, fee_breakdown)
                VALUES (:pid, :lid, 'recovery', 'pending', :fee,
                     :bmsg, :due, :hours, :delays, :chance, :feebd)
            ")->execute([
                ':pid' => $playerId,
                ':lid' => $loanId,
                ':fee' => $fee,
                ':bmsg' => $timeData['bank_message'],
                ':due' => $timeData['due_at'],
                ':hours' => $timeData['total_hours'],
                ':delays' => json_encode($timeData['delays'], JSON_UNESCAPED_UNICODE),
                ':chance' => $ctx['approvalChance'],
                ':feebd' => json_encode([
                    'effective_pct' => round($effectivePct * 100, 2) . '%',
                    'trust_factor' => $ctx['trust_factor'],
                ], JSON_UNESCAPED_UNICODE),
            ]);

            $negId = (int)$this->db->lastInsertId();
            $this->db->commit();

            $result = [
                'success' => true,
                'negotiation_id' => $negId,
                'message' => $timeData['bank_message'],
                'decision_due' => $timeData['due_at'],
                'estimated_fee' => $fee,
            ];
            if ($timeData['cfo_message']) {
                $result['cfo_message'] = $timeData['cfo_message'];
            }
            if ($ctx['lawyerName']) {
                $lawyerMsgs = [
                    t('bank_neg.lawyer_recovery_1', ['name' => $ctx['lawyerName']]),
                    t('bank_neg.lawyer_recovery_2', ['name' => $ctx['lawyerName']]),
                ];
                $result['lawyer_message'] = $lawyerMsgs[array_rand($lawyerMsgs)];
            }

            return $result;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('BankNeg', 'requestRecoveryPlan FAILED', $e, ['loan' => $loanId]);
            return ['success' => false, 'message' => t('common.app_error')];
        }
    }
}
