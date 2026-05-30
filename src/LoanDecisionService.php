<?php

/**
 * LoanDecisionService - credit decision engine.
 *
 * Score thresholds -> decision (after market modifiers):
 * score < 30 -> rejected
 * score 30-49 -> conditional: 30% of amount, 40% APR
 * score 50-74 -> partial: 50-80% of amount, 28% APR
 * score 75+ -> full: 100% of amount, 18% APR
 *
 * Market modifiers (applied to approved offers):
 * CRISIS (sentiment=negative): APR +12%, amount -30%; score<50 -> auto-reject
 * BOOM (sentiment=positive): APR -3% (min 15%), amount +10%
 */
class LoanDecisionService
{
    private PDO $db;
    private RiskScoreEngine $riskEngine;

    public function __construct()
    {
        try {
            $this->db          = Database::getInstance()->getConnection();
            $this->riskEngine  = new RiskScoreEngine();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LoanDecisionService', '__construct failed', $e);
            }
            throw $e;
        }
    }

 /** @return array<string, mixed>|false */
    public function processApplication(int $applicationId): array|false
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM loan_applications
                WHERE id = :id AND status = 'pending'
            ");
            $stmt->execute([':id' => $applicationId]);
            $application = $stmt->fetch();

            if (!$application) {
                return false;
            }

            $riskResult = $this->riskEngine->calculateRiskScore((int)$application['player_id']);
            $score      = $riskResult['score'];
            $breakdown  = $riskResult['breakdown'];

            $decision = $this->makeDecision($score, (float)$application['requested_amount'], $breakdown);
            $trendId  = $breakdown['market']['trend_id'] ?? null;

            $this->saveDecision($applicationId, $score, $breakdown, $decision, $trendId);

            return $decision;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LoanDecisionService', 'processApplication failed', $e, [
                    'application_id' => $applicationId,
                ]);
            }
            return false;
        }
    }

 // 

 /**
 * @param array<string, mixed> $breakdown
 * @return array<string, mixed>
 */
    private function makeDecision(int $score, float $requestedAmount, array $breakdown): array
    {
        $marketScore = $breakdown['market']['points'] ?? 0;
        $sentiment   = $breakdown['market']['sentiment'] ?? 'neutral';

 // Score thresholds and APR rates from BankSettings (configurable in admin panel)
        $threshReject = BankSettings::scoreThresholdReject(); // default 30
        $threshCond   = BankSettings::scoreThresholdCond();   // default 50
        $threshFull   = BankSettings::scoreThresholdFull();   // default 75
        $aprCond      = BankSettings::aprConditional();       // default 40%
        $aprPartial   = BankSettings::aprPartial();           // default 28%
        $aprFull      = BankSettings::aprFull();              // default 18%

        $decision = [
            'status'          => 'rejected',
            'approved_amount' => 0,
            'interest_rate'   => 0,
            'reason'          => '',
        ];

 // AUTO-REJECT: crisis market + weak player score
        if ($sentiment === 'negative' && $score < $threshCond) {
            $decision['reason'] = t('loan_decision.err_crisis_auto_reject');
            return $decision;
        }

 // BASE DECISION

        if ($score < $threshReject) {
            $decision['status'] = 'rejected';
            $decision['reason'] = t('loan_decision.err_rejected_high_risk');

        } elseif ($score < $threshCond) {
 // Conditional: 30% of requested amount
            $decision['status']          = 'approved';
            $decision['approved_amount'] = round($requestedAmount * 0.30);
            $decision['interest_rate']   = $aprCond;
            $decision['reason']          = t('loan_decision.msg_conditional');

        } elseif ($score < $threshFull) {
 // Partial: 50-80% of requested amount, scaled
            $pct = 0.50 + (($score - $threshCond) / ($threshFull - $threshCond)) * 0.30;
            $decision['status']          = 'approved';
            $decision['approved_amount'] = round($requestedAmount * $pct);
            $decision['interest_rate']   = $aprPartial;
            $decision['reason']          = t('loan_decision.msg_partial');

        } else {
 // Full approval: 100% of requested amount
            $decision['status']          = 'approved';
            $decision['approved_amount'] = $requestedAmount;
            $decision['interest_rate']   = $aprFull;
            $decision['reason']          = t('loan_decision.msg_approved');
        }

 // MARKET MODIFIERS

        if ($decision['status'] === 'approved') {

            if ($sentiment === 'negative') {
 // Crisis: higher rate, lower amount
                $decision['interest_rate']   += 12.0;
                $decision['approved_amount']  = round($decision['approved_amount'] * 0.70);
                $decision['reason']          .= ' ' . t('loan_decision.msg_crisis_modifier');

            } elseif ($sentiment === 'positive') {
 // Boom: lower rate, higher amount
                $decision['interest_rate']   = max(15.0, $decision['interest_rate'] - 3.0);
                $decision['approved_amount'] = round($decision['approved_amount'] * 1.10);
                $decision['reason']         .= ' ' . t('loan_decision.msg_boom_modifier');
            }

 // Guard against absurdly small approved amounts
            if ($decision['approved_amount'] < 5000) {
                $decision['status'] = 'rejected';
                $decision['reason'] = t('loan_decision.err_amount_too_low');
            }
        }

        return $decision;
    }

 // 

 /**
 * @param array<string, mixed> $breakdown
 * @param array<string, mixed> $decision
 */
    private function saveDecision(
        int    $applicationId,
        int    $score,
        array  $breakdown,
        array  $decision,
        ?int   $trendId
    ): void {
        try {
            $stmt = $this->db->prepare("
                UPDATE loan_applications
                SET status           = :status,
                    risk_score       = :score,
                    risk_breakdown   = :breakdown,
                    approved_amount  = :approved_amount,
                    interest_rate    = :interest_rate,
                    rejection_reason = :reason,
                    market_trend_id  = :trend_id,
                    decided_at       = NOW(),
                    expires_at       = DATE_ADD(NOW(), INTERVAL 48 HOUR)
                WHERE id = :id
            ");

            $stmt->execute([
                ':status'          => $decision['status'],
                ':score'           => $score,
                ':breakdown'       => json_encode($breakdown, JSON_UNESCAPED_UNICODE),
                ':approved_amount' => $decision['approved_amount'],
                ':interest_rate'   => $decision['interest_rate'],
                ':reason'          => $decision['reason'],
                ':trend_id'        => $trendId,
                ':id'              => $applicationId,
            ]);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LoanDecisionService', 'saveDecision failed', $e, [
                    'application_id' => $applicationId,
                    'trend_id' => $trendId,
                ]);
            }
            throw $e;
        }
    }
}
