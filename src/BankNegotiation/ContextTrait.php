<?php

/**
 * Builds calculation context for bank negotiations.
 * Buduje kontekst obliczen dla negocjacji bankowych.
 */
trait BankNegotiationContextTrait
{
 /**
 * Formats hours in accusative form for messages.
 * Formatuje godziny w bierniku do komunikatow.
 */
    private function formatHours(float $hours): string
    {
        $h = (int)round($hours);
        if ($h === 1) {
            return t('bank_neg.hours_acc_1', ['n' => $h]);
        }
        if ($h >= 2 && $h <= 4) {
            return t('bank_neg.hours_acc_2_4', ['n' => $h]);
        }
        return t('bank_neg.hours_acc_5', ['n' => $h]);
    }

 /**
 * Formats hours in nominative form for messages.
 * Formatuje godziny w mianowniku do komunikatow.
 */
    private function formatHoursNom(float $hours): string
    {
        $h = (int)round($hours);
        if ($h === 1) {
            return t('bank_neg.hours_nom_1', ['n' => $h]);
        }
        if ($h >= 2 && $h <= 4) {
            return t('bank_neg.hours_nom_2_4', ['n' => $h]);
        }
        return t('bank_neg.hours_nom_5', ['n' => $h]);
    }

 /**
 * Returns the internal trust score for the player.
 * Zwraca wewnetrzny trust score gracza.
 */
    public function getTrustScore(int $playerId): int
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT score FROM bank_trust_scores WHERE player_id=:pid LIMIT 1"
            );
            $stmt->execute([':pid' => $playerId]);
            $row = $stmt->fetch();
            return $row ? (int)$row['score'] : 50;
        } catch (Throwable $e) {
            GameLog::error('BankNeg', 'getTrustScore FAILED', $e);
            return 50;
        }
    }

 /**
 * Adjusts trust score and writes a log entry.
 * Koryguje trust score i zapisuje wpis do logu.
 */
    public function adjustTrustScore(int $playerId, string $event, ?string $note = null): void
    {
        try {
            $delta = self::TRUST[$event] ?? 0;
            if ($delta === 0) {
                return;
            }

            $this->db->prepare("
                INSERT INTO bank_trust_scores (player_id, score, last_event, last_updated)
                VALUES (:pid, GREATEST(0, LEAST(100, 50 + :delta)), :event, NOW())
                ON DUPLICATE KEY UPDATE
                    score        = GREATEST(0, LEAST(100, score + :delta2)),
                    last_event   = :event2,
                    last_updated = NOW()
            ")->execute([
                ':pid' => $playerId,
                ':delta' => $delta,
                ':event' => $event . ($note ? ": {$note}" : ''),
                ':delta2' => $delta,
                ':event2' => $event . ($note ? ": {$note}" : ''),
            ]);

 // Store trust changes for GM/admin review only.
 // Zapisz zmiany trust tylko do przegladu GM/admin.
            $this->db->prepare("
                INSERT INTO bank_trust_log (player_id, event, delta, note, created_at)
                VALUES (:pid, :event, :delta, :note, NOW())
            ")->execute([
                ':pid' => $playerId,
                ':event' => $event,
                ':delta' => $delta,
                ':note' => $note,
            ]);
        } catch (Throwable $e) {
            GameLog::error('BankNeg', 'adjustTrustScore failed', $e);
        }
    }

 /**
 * Returns the descriptive trust key used in UI.
 * Zwraca klucz opisu trust uzywany w UI.
 */
    private function getTrustDescriptionKey(int $score): string
    {
        return match (true) {
            $score >= 80 => 'excellent',
            $score >= 65 => 'good',
            $score >= 45 => 'neutral',
            $score >= 25 => 'weakened',
            default => 'very_weak',
        };
    }

 /**
 * Builds the full negotiation context for one loan/player pair.
 * Buduje pelny kontekst negocjacji dla pary gracz/kredyt.
 *
 * @param array<string, mixed> $loan
 * @return array<string, mixed>
 */
    private function buildContext(int $playerId, array $loan): array
    {
 // Wells and their condition distribution.
 // Odwierty i rozklad ich kondycji.
        $w = $this->db->prepare("
            SELECT COUNT(*) AS total,
                   SUM(status='active') AS active,
                   SUM(status IN ('seized','paused_cash')) AS troubled,
                   MAX(base_production_per_hour * pressure * (technical_condition / 100.0)) AS best_well_value
            FROM wells WHERE player_id=:pid
        ");
        $w->execute([':pid' => $playerId]);
        $wRow = $w->fetch();
        $totalWells = (int)$wRow['total'];
        $activeWells = (int)$wRow['active'];
        $troubledWells = (int)$wRow['troubled'];

 // Storage saturation ratio.
 // Poziom zapelnienia magazynu.
        $s = $this->db->prepare("SELECT capacity, used FROM storage WHERE player_id=:pid");
        $s->execute([':pid' => $playerId]);
        $stor = $s->fetch();
        $storageRatio = ($stor && (float)$stor['capacity'] > 0)
            ? (float)$stor['used'] / (float)$stor['capacity']
            : 0.0;

 // Market situation and active trend.
 // Sytuacja rynkowa i aktywny trend.
        $mkt = $this->db->query(
            "SELECT base_price, current_price FROM market_state WHERE id=1"
        )->fetch();
        $priceRatio = ($mkt && (float)$mkt['base_price'] > 0)
            ? (float)$mkt['current_price'] / (float)$mkt['base_price']
            : 1.0;
        $trend = (new MarketTrend())->getActiveTrend();
        $modifier = $trend ? (float)$trend['price_modifier'] : 1.0;
        $trendNeg = $trend && $modifier <= 0.85;
        $trendPos = $trend && $modifier >= 1.15;

 // Credit and trust scores.
 // Credit score i trust score.
        $pRow = $this->db->prepare("SELECT credit_score FROM players WHERE id=:id");
        $pRow->execute([':id' => $playerId]);
        $pData = $pRow->fetch();
        $creditScore = (int)($pData['credit_score'] ?? 50);
        $trustScore = $this->getTrustScore($playerId);

 // Historical bailiff pressure.
 // Historyczna presja komornicza.
        $bh = $this->db->prepare("
            SELECT COUNT(*) FROM bailiff_proceedings
            WHERE player_id=:pid AND status IN ('completed','bankruptcy')
        ");
        $bh->execute([':pid' => $playerId]);
        $bailiffCount = (int)$bh->fetchColumn();

 // CFO is inferred from completed recruitment and active board assignment.
 // CFO wynika z zakonczonej rekrutacji i aktywnego przypisania w board.
        $cfoRow = null;
        $cfoName = null;
        $cfoSkill = 0;
        try {
            $cfo = $this->db->prepare("
                SELECT bm.first_name, bm.last_name,
                       bm.skill_negotiation, bm.skill_analysis
                FROM board_members bm
                JOIN board_roles br ON br.id = bm.role_id
                JOIN employee_contracts ec ON ec.member_id = bm.id
                WHERE br.code = 'finance'
                  AND bm.status = 'active'
                  AND ec.status = 'active'
                  AND EXISTS (
                      SELECT 1 FROM recruitment_requests rr
                      WHERE rr.role_id = bm.role_id
                        AND rr.player_id = :pid
                        AND rr.status = 'completed'
                  )
                LIMIT 1
            ");
            $cfo->execute([':pid' => $playerId]);
            $cfoRow = $cfo->fetch() ?: null;

            if ($cfoRow) {
                $cfoName = trim($cfoRow['first_name'] . ' ' . $cfoRow['last_name']);
                $nego = (int)($cfoRow['skill_negotiation'] ?? 0);
                $analysis = (int)($cfoRow['skill_analysis'] ?? 0);
                $cfoSkill = (int)round(($nego + $analysis) / 2);
            }
        } catch (Throwable $e) {
            GameLog::error('BankNeg', 'buildContext CFO query FAILED', $e);
        }

 // Lawyer is inferred in the same way through legal role.
 // Prawnik jest wyliczany analogicznie przez role legal.
        $lawyerName = null;
        try {
            $law = $this->db->prepare("
                SELECT bm.first_name, bm.last_name
                FROM board_members bm
                JOIN board_roles br ON br.id = bm.role_id
                JOIN employee_contracts ec ON ec.member_id = bm.id
                WHERE br.code = 'legal'
                  AND bm.status = 'active'
                  AND ec.status = 'active'
                  AND EXISTS (
                      SELECT 1 FROM recruitment_requests rr
                      WHERE rr.role_id = bm.role_id
                        AND rr.player_id = :pid
                        AND rr.status = 'completed'
                  )
                LIMIT 1
            ");
            $law->execute([':pid' => $playerId]);
            $lawRow = $law->fetch() ?: null;
            if ($lawRow) {
                $lawyerName = trim($lawRow['first_name'] . ' ' . $lawRow['last_name']);
            }
        } catch (Throwable $e) {
            GameLog::error('BankNeg', 'buildContext Lawyer query FAILED', $e);
        }

 // Loan-to-value proxy for negotiation risk.
 // Przyblizenie LTV dla ryzyka negocjacji.
        $ltv = 1.0;
        if ((float)($loan['principal_amount'] ?? 0) > 0) {
            $ltv = (float)$loan['remaining_amount'] / (float)$loan['principal_amount'];
        }

 // Fee factors used by negotiation pricing.
 // Czynniki prowizji uzywane do wyceny negocjacji.
        $well_factor = match (true) {
            $troubledWells === 0 && $totalWells > 0 => 0.80,
            $troubledWells <= 2 => 1.00,
            default => 1.40,
        };

        $market_factor = match (true) {
            $priceRatio >= 1.20 => 0.85,
            $priceRatio >= 0.80 => 1.00,
            default => 1.25,
        };
        if ($trendNeg) {
            $market_factor *= 1.15;
        }
        if ($trendPos) {
            $market_factor *= 0.90;
        }

        $ltv_factor = match (true) {
            $ltv >= 0.90 => 1.50,
            $ltv >= 0.70 => 1.20,
            $ltv >= 0.50 => 1.00,
            default => 0.85,
        };

        $storage_factor = ($storageRatio >= 0.80)
            ? 1.20
            : (($storageRatio >= 0.50) ? 1.00 : 0.90);

        $credit_factor = match (true) {
            $creditScore >= 70 => 0.85,
            $creditScore >= 40 => 1.00,
            default => 1.35,
        };

        $trust_factor = match (true) {
            $trustScore >= 80 => 0.88,
            $trustScore >= 60 => 0.95,
            $trustScore >= 40 => 1.00,
            $trustScore >= 20 => 1.10,
            default => 1.22,
        };

 // CFO can reduce fees by up to 15%.
 // CFO moze obnizyc prowizje maksymalnie o 15%.
        $cfo_fee_reduction = min(0.15, ($cfoSkill / 100) * 0.15);

        $late_factor = ($loan['status'] === 'late') ? 1.30 : 1.00;

 // Approval chance model.
 // Model szansy zatwierdzenia.
        $approvalChance = 85;
        if ($creditScore < 30) {
            $approvalChance -= 25;
        }
        if ($trustScore < 25) {
            $approvalChance -= 20;
        }
        if ($totalWells > 0 && $troubledWells === $totalWells) {
            $approvalChance -= 40;
        }
        if ($trendNeg) {
            $approvalChance -= 15;
        }
        if ($bailiffCount > 2) {
            $approvalChance -= 20;
        }
        if ($ltv > 0.80) {
            $approvalChance -= 15;
        }
        if ($creditScore >= 70) {
            $approvalChance += 10;
        }
        if ($activeWells >= 2) {
            $approvalChance += 5;
        }
        if ($trendPos) {
            $approvalChance += 10;
        }
        if ($trustScore >= 70) {
            $approvalChance += 12;
        }
        if ($cfoSkill >= 60) {
            $approvalChance += 8;
        }
        if ($cfoSkill >= 80) {
            $approvalChance += 5;
        }
        $approvalChance = max(5, min(95, $approvalChance));

        return compact(
            'totalWells',
            'activeWells',
            'troubledWells',
            'storageRatio',
            'priceRatio',
            'ltv',
            'trend',
            'trendNeg',
            'trendPos',
            'modifier',
            'creditScore',
            'trustScore',
            'bailiffCount',
            'well_factor',
            'market_factor',
            'ltv_factor',
            'storage_factor',
            'credit_factor',
            'trust_factor',
            'cfo_fee_reduction',
            'late_factor',
            'approvalChance',
            'cfoName',
            'cfoSkill',
            'lawyerName'
        );
    }

 /**
 * Calculates fee for a deferral request.
 * Oblicza prowizje dla odroczenia.
 */
    private function calculateDeferralFee(array $loan, int $days, array $ctx): array
    {
        $remaining = (float)$loan['remaining_amount'];
        $base = self::BASE_FEE["deferral_{$days}"];
        $days_factor = match ($days) {
            30 => 1.00,
            90 => 1.50,
            180 => 2.20,
            default => 1.00,
        };

        $effective = $base
 * $ctx['well_factor'] * $ctx['market_factor'] * $ctx['ltv_factor']
 * $ctx['storage_factor'] * $ctx['credit_factor'] * $ctx['trust_factor']
 * $ctx['late_factor'] * $days_factor;
        $effective *= (1 - $ctx['cfo_fee_reduction']);
        $effective = max(0.005, min(0.14, $effective));
        $fee = (int)round($remaining * $effective);

        return [
            'fee' => $fee,
            'effective_pct' => $effective,
            'breakdown' => [
                'base_rate' => $base,
                'days_factor' => $days_factor,
                'well_factor' => $ctx['well_factor'],
                'market_factor' => $ctx['market_factor'],
                'ltv_factor' => $ctx['ltv_factor'],
                'credit_factor' => $ctx['credit_factor'],
                'trust_factor' => $ctx['trust_factor'],
                'cfo_reduction' => round($ctx['cfo_fee_reduction'] * 100, 1) . '%',
                'effective_pct' => round($effective * 100, 3) . '%',
                'final_fee' => $fee,
            ],
        ];
    }

 /**
 * Calculates decision time, delays and supporting messages.
 * Oblicza czas decyzji, opoznienia i komunikaty pomocnicze.
 */
    private function calculateDecisionTime(array $loan, array $ctx): array
    {
        $hours = 2.0; // Base: 2h, the bank still needs time. | Baza: 2h, bank nadal potrzebuje czasu.
        $delays = [];
        $messages = [];

        if ($ctx['creditScore'] < 40) {
            $add = 1;
            $hours += $add;
            $delays[] = ['reason' => 'niski scoring', 'hours' => $add];
            $messages[] = t('bank_neg.delay_low_score', ['hours' => $this->formatHours($add)]);
        }

        $remaining = (float)$loan['remaining_amount'];
        if ($remaining > 50_000_000) {
            $add = 2;
            $hours += $add;
            $delays[] = ['reason' => 'loan >50M', 'hours' => $add];
            $messages[] = t('bank_neg.delay_large_loan_50m', ['hours' => $this->formatHours($add)]);
        } elseif ($remaining > 10_000_000) {
            $add = 1;
            $hours += $add;
            $delays[] = ['reason' => 'loan >10M', 'hours' => $add];
            $messages[] = t('bank_neg.delay_large_loan_10m', ['hours' => $this->formatHours($add)]);
        }

        if ($ctx['trendNeg'] && $ctx['trend']) {
            $trendName = $ctx['trend']['trend_name'] ?? '';
            $add = 1;
            $hours += $add;
            $delays[] = ['reason' => "trend: {$trendName}", 'hours' => $add];
            $messages[] = t('bank_neg.delay_bad_trend', ['trend' => $trendName, 'hours' => $this->formatHours($add)]);
        }

        if ($ctx['troubledWells'] > 0) {
            $add = min($ctx['troubledWells'], 2);
            $hours += $add;
            $delays[] = ['reason' => "{$ctx['troubledWells']} troubled wells", 'hours' => $add];
            $count = $ctx['troubledWells'];
            $messages[] = ($count === 1)
                ? t('bank_neg.delay_troubled_well_1', ['n' => $count, 'hours' => $this->formatHours($add)])
                : t('bank_neg.delay_troubled_wells', ['n' => $count, 'hours' => $this->formatHours($add)]);
        }

        if ($ctx['storageRatio'] >= 0.90) {
            $add = 1;
            $hours += $add;
            $delays[] = ['reason' => 'storage >90%', 'hours' => $add];
            $messages[] = t('bank_neg.delay_storage_full', ['hours' => $this->formatHours($add)]);
        }

        if ($loan['status'] === 'late') {
            $add = 2;
            $hours += $add;
            $delays[] = ['reason' => 'loan late', 'hours' => $add];
            $messages[] = t('bank_neg.delay_late_loan', ['hours' => $this->formatHours($add)]);
        }

        if ($ctx['trustScore'] < 35) {
            $add = 2;
            $hours += $add;
            $delays[] = ['reason' => 'low trust (internal)', 'hours' => $add];
            $messages[] = t('bank_neg.delay_low_trust', ['hours' => $this->formatHours($add)]);
        }

        if ($ctx['ltv'] > 0.85) {
            $add = 1;
            $hours += $add;
            $delays[] = ['reason' => 'LTV >85%', 'hours' => $add];
            $messages[] = t('bank_neg.delay_high_ltv', ['hours' => $this->formatHours($add)]);
        }

 // CFO can shorten decision time by up to 40%.
 // CFO moze skrocic czas decyzji maksymalnie o 40%.
        $cfoReduction = 0.0;
        if ($ctx['cfoSkill'] > 0) {
            $cfoReduction = min(0.40, ($ctx['cfoSkill'] / 100) * 0.40);
            $hours *= (1 - $cfoReduction);
        }

 // Keep decision time between 2h and 24h.
 // Utrzymaj czas decyzji miedzy 2h a 24h.
        $hours = max(2.0, min(24.0, $hours));
        $due_at = date('Y-m-d H:i:s', time() + (int)($hours * 3600));

        if ($ctx['cfoName'] && $ctx['cfoSkill'] >= 31) {
            $cfoMsg = $this->buildCfoOpeningMessage($ctx['cfoName'], $ctx['cfoSkill'], $due_at);
        } else {
            $cfoMsg = null;
        }

        $bankMsg = $this->buildBankOpeningMessage($hours, $messages, $due_at);

        return [
            'total_hours' => $hours,
            'due_at' => $due_at,
            'delays' => $delays,
            'bank_message' => $bankMsg,
            'cfo_message' => $cfoMsg,
            'cfo_reduction' => round($cfoReduction * 100),
        ];
    }

 /**
 * Returns trust data for GM/admin view only.
 * Zwraca dane trust tylko do widoku GM/admin.
 */
    public function getPlayerTrustData(int $playerId): array
    {
        try {
            $score = $this->getTrustScore($playerId);
            $log = $this->db->prepare("
                SELECT * FROM bank_trust_log
                WHERE player_id=:pid ORDER BY created_at DESC LIMIT 30
            ");
            $log->execute([':pid' => $playerId]);

            return [
                'score' => $score,
                'description' => t('bank_neg.trust_' . $this->getTrustDescriptionKey($score)),
                'log' => $log->fetchAll(),
            ];
        } catch (Throwable $e) {
            GameLog::error('BankNeg', 'getPlayerTrustData FAILED', $e);
            return ['score' => 50, 'description' => t('bank_neg.trust_neutral'), 'log' => []];
        }
    }

 /**
 * Counts negotiations created this month for the player.
 * Liczy negocjacje utworzone w tym miesiacu dla gracza.
 */
    private function getNegotiationCountThisMonth(int $playerId): int
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM bank_negotiations
                WHERE player_id=:pid AND requested_at >= DATE_FORMAT(NOW(),'%Y-%m-01')
            ");
            $stmt->execute([':pid' => $playerId]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            GameLog::error('BankNeg', 'getNegotiationCountThisMonth FAILED', $e, ['player_id' => $playerId]);
            return 0;
        }
    }
}
