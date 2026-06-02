<?php

/**
 * RiskScoreEngine credit risk scoring engine.
 *
 * Score scale (max ~115):
 * Wells (assets) : 0-20 pts
 * Production : 0-10 pts
 * Storage : 0-10 pts
 * Cash (liquidity) : 0-15 pts
 * Behaviour : 0-15 pts
 * Market : -15 / 0 / +15 pts
 * History : 0-20 pts
 * Credit Score : 0-15 pts (players.credit_score banking reputation)
 */
class RiskScoreEngine
{
    private PDO $db;

    public function __construct()
    {
        try {
            $this->db = Database::getInstance()->getConnection();
            GameLog::info('RiskScoreEngine', 'Service initialized');
        } catch (Throwable $e) {
            GameLog::error('RiskScoreEngine', 'Initialization failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

 /**
 * Calculates the credit risk score for a player.
 * @return array{score: int, breakdown: array<string, array<string, mixed>>}
 */
    public function calculateRiskScore(int $playerId): array
    {
        $breakdown    = [];
        $totalScore   = 0;

        $sections = [
            'wells'        => $this->scoreWells($playerId),
            'production'   => $this->scoreProduction($playerId),
            'storage'      => $this->scoreStorage($playerId),
            'cash'         => $this->scoreCash($playerId),
            'behavior'     => $this->scoreBehavior($playerId),
            'market'       => $this->scoreMarket(),
            'history'      => $this->scoreHistory($playerId),
            'credit_score' => $this->scoreCreditScore($playerId),
        ];

        foreach ($sections as $key => $result) {
            $breakdown[$key]  = $result;
            $totalScore      += $result['points'];
        }

        $totalScore = max(0, min(115, $totalScore));

        return [
            'score'     => $totalScore,
            'breakdown' => $breakdown,
        ];
    }

 // ASSETS (max 20 pts)

 /**
 * @return array<string, mixed>
 */
    private function scoreWells(int $playerId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(status = 'active')         AS active_count,
                SUM(status = 'paused_storage') AS paused_storage,
                SUM(status = 'paused_cash')    AS paused_cash,
                SUM(status = 'seized')         AS seized
            FROM wells
            WHERE player_id = :pid
        ");
        $stmt->execute([':pid' => $playerId]);
        $d = $stmt->fetch();

        $total  = (int)$d['total'];
        $active = (int)$d['active_count'];
        $seized = (int)$d['seized'];

 // Points for well count (max 12)
        $points = match(true) {
            $total === 0             => 0,
            $total === 1             => 4,
            $total <= 3              => 8,
            $total <= 6              => 11,
            default                  => 12,
        };

 // Points for status quality (max 8)
        if ($total > 0) {
            $activeRatio = $active / $total;
            if ($activeRatio >= 1.0)       $points += 8;
            elseif ($activeRatio >= 0.75)  $points += 5;
            elseif ($activeRatio >= 0.5)   $points += 2;
 // below 50% active = 0 bonus
        }

 // Penalty for seized wells
        if ($seized > 0) $points = max(0, $points - 5);

        return [
            'points'  => $points,
            'max'     => 20,
            'details' => t('risk.details_wells', ['total' => $total, 'active' => $active, 'seized' => $seized]),
        ];
    }

 // PRODUCTION (max 10 pts)

 /**
 * @return array<string, mixed>
 */
    private function scoreProduction(int $playerId): array
    {
 // Score based on last well activity timestamp
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(status = 'active') AS active,
                MAX(last_production_at) AS last_prod
            FROM wells
            WHERE player_id = :pid
        ");
        $stmt->execute([':pid' => $playerId]);
        $d = $stmt->fetch();

        $points = 0;

        if ((int)$d['total'] > 0 && (int)$d['active'] > 0) {
            $hoursAgo = $d['last_prod']
                ? (time() - strtotime($d['last_prod'])) / 3600
                : 999;

            if ($hoursAgo < 6)        $points = 10;
            elseif ($hoursAgo < 24)   $points = 7;
            elseif ($hoursAgo < 48)   $points = 3;
            else                      $points = 0;
        }

        return [
            'points'  => $points,
            'max'     => 10,
            'details' => t('risk.details_production', ['active' => $d['active'], 'total' => $d['total']]),
        ];
    }

 // STORAGE (max 10 pts)

 /**
 * @return array<string, mixed>
 */
    private function scoreStorage(int $playerId): array
    {
        $stmt = $this->db->prepare("
            SELECT capacity, used FROM storage WHERE player_id = :pid
        ");
        $stmt->execute([':pid' => $playerId]);
        $storage = $stmt->fetch();

 // BUG FIX: $usage must be accessible outside the if() block
        $usageRatio  = 0.0;
        $usagePct    = 0;
        $points      = 0;

        if ($storage && (int)$storage['capacity'] > 0) {
            $usageRatio = $storage['used'] / $storage['capacity'];
            $usagePct   = round($usageRatio * 100);

 // Full storage = poor management (production halted)
            if ($usageRatio < 0.7)       $points = 10;
            elseif ($usageRatio < 0.85)  $points = 7;
            elseif ($usageRatio < 0.95)  $points = 3;
            else                         $points = 0;  // full/blocked
        }

        return [
            'points'  => $points,
            'max'     => 10,
            'details' => t('risk.details_storage', ['pct' => $usagePct]),
        ];
    }

 // CASH / LIQUIDITY (max 15 pts)

 /**
 * @return array<string, mixed>
 */
    private function scoreCash(int $playerId): array
    {
        $stmt = $this->db->prepare("SELECT cash FROM players WHERE id = :id");
        $stmt->execute([':id' => $playerId]);
        $player = $stmt->fetch();

        $cash   = (int)($player['cash'] ?? 0);
        $points = match(true) {
            $cash > 100_000  => 15,
            $cash > 50_000   => 11,
            $cash > 20_000   => 7,
            $cash > 5_000    => 3,
            default          => 0,
        };

        return [
            'points'  => $points,
            'max'     => 15,
            'details' => t('risk.details_cash', ['amount' => number_format($cash)]),
        ];
    }

 // BEHAVIOUR / ACTIVITY (max 15 pts)

 /**
 * @return array<string, mixed>
 */
    private function scoreBehavior(int $playerId): array
    {
        $stmt = $this->db->prepare("
            SELECT created_at, last_login_at, last_tick_at FROM players WHERE id = :id
        ");
        $stmt->execute([':id' => $playerId]);
        $player = $stmt->fetch();

        $points = 0;

 // 1. Account age (max 8 pts)
        $ageDays = (time() - strtotime($player['created_at'])) / 86400;
        $points += match(true) {
            $ageDays >= 30  => 8,
            $ageDays >= 14  => 6,
            $ageDays >= 7   => 4,
            $ageDays >= 4   => 2,
            default         => 0,
        };

 // 2. Last login (max 7 pts) inactive player = higher risk
        $lastLogin    = $player['last_login_at'] ?? $player['last_tick_at'];
        $hoursInactive = $lastLogin
            ? (time() - strtotime($lastLogin)) / 3600
            : 999;

        $points += match(true) {
            $hoursInactive < 6   => 7,
            $hoursInactive < 24  => 5,
            $hoursInactive < 72  => 2,
            default              => 0,  // account abandoned
        };

        return [
            'points'  => $points,
            'max'     => 15,
            'details' => t('risk.details_behavior', ['days' => round($ageDays, 1), 'hours' => round($hoursInactive)]),
        ];
    }

 // MARKET / TREND (-15 / 0 / +15 pts)

 /**
 * BUG FIX: previous version checked category as 'boom','war' etc.
 * but in the DB category = 'economic','political' etc.
 * The correct key is trend_name (VARCHAR) or category.
 *
 * Mapping per spec:
 * +15: economic category with price_modifier > 1 OR trend_name LIKE boom/discovery/opec_cut
 * -15: military/political category with price_modifier < 1 OR trend_name LIKE crisis/war/pandemic
 * 0: everything else
 *
 * Uses price_modifier as the objective indicator since it is stored in the DB.
 *
 * @return array<string, mixed>
 */
    private function scoreMarket(): array
    {
        $marketTrend = new MarketTrend();
        $trend       = $marketTrend->getActiveTrend();

        if (!$trend) {
            return [
                'points'      => 0,
                'max'         => 15,
                'category'    => null,
                'trend_id'    => null,
                'trend_name'  => null,
                'sentiment'   => 'neutral',
                'details'     => t('risk.details_no_trend'),
            ];
        }

        $category   = $trend['category'];
        $trendName  = strtolower($trend['trend_name']);
        $modifier   = (float)$trend['price_modifier'];

 // Map trend names to sentiment per project spec
        $positiveNames = ['boom', 'discovery', 'opec_cut', 'opec cut', 'odkrycie', 'wzrost'];
        $negativeNames = ['crisis', 'war', 'pandemic', 'kryzys', 'wojna', 'pandemia', 'embargo'];

        $sentiment = 'neutral';
        foreach ($positiveNames as $name) {
            if (str_contains($trendName, $name)) { $sentiment = 'positive'; break; }
        }
        if ($sentiment === 'neutral') {
            foreach ($negativeNames as $name) {
                if (str_contains($trendName, $name)) { $sentiment = 'negative'; break; }
            }
        }

 // Fallback: use price_modifier when the name is inconclusive
        if ($sentiment === 'neutral') {
            if ($modifier >= 1.15)      $sentiment = 'positive';
            elseif ($modifier <= 0.85)  $sentiment = 'negative';
        }

        $points = match($sentiment) {
            'positive' => 15,
            'negative' => -15,
            default    => 0,
        };

        $sentimentLabel = match($sentiment) {
            'positive' => t('risk.sentiment_positive'),
            'negative' => t('risk.sentiment_negative'),
            default    => t('risk.sentiment_neutral'),
        };

        return [
            'points'     => $points,
            'max'        => 15,
            'category'   => $category,
            'trend_id'   => $trend['id'],
            'trend_name' => $trend['trend_name'],
            'modifier'   => $modifier,
            'sentiment'  => $sentiment,
            'details'    => "Trend: {$trend['trend_name']} ({$sentimentLabel}, modifier: x{$modifier})",
        ];
    }

 // CREDIT HISTORY (max 20 pts)

 /**
 * @return array<string, mixed>
 */
    private function scoreHistory(int $playerId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*)                         AS total,
                SUM(status = 'paid_off')         AS paid_off,
                SUM(status = 'defaulted')        AS defaulted,
                SUM(status IN ('active','late')) AS active
            FROM loans
            WHERE player_id = :pid
        ");
        $stmt->execute([':pid' => $playerId]);
        $h = $stmt->fetch();

        $total    = (int)$h['total'];
        $paidOff  = (int)$h['paid_off'];
        $defaults = (int)$h['defaulted'];

        if ($total === 0) {
            $points  = 10;
            $details = t('risk.history_none');
        } elseif ($defaults > 0) {
            $points  = max(0, 5 - ($defaults * 5));
            $details = t('risk.history_defaults', ['count' => $defaults]);
        } else {
            $points  = min(20, 10 + ($paidOff * 5));
            $details = t('risk.history_paid', ['paid' => $paidOff, 'total' => $total]);
        }

        return [
            'points'  => $points,
            'max'     => 20,
            'details' => $details,
        ];
    }

 // CREDIT SCORE banking reputation (max 15 pts)

 /** @return array<string, mixed> */
    private function scoreCreditScore(int $playerId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COALESCE(credit_score, 50) AS credit_score,
                       COALESCE(bankruptcy_status, 'none') AS bankruptcy_status
                FROM players WHERE id = :pid LIMIT 1
            ");
            $stmt->execute([':pid' => $playerId]);
            $row = $stmt->fetch();

            if (!$row) {
                return ['points' => 0, 'max' => 15, 'details' => t('risk.cs_no_player')];
            }

            $cs     = (int)$row['credit_score'];
            $bkStat = (string)$row['bankruptcy_status'];

            $points = (int)min(15, round($cs / 20));

            if ($bkStat === 'recovered') {
                $points = max(0, $points - 3);
            }

            if ($cs <= 30) {
                $label = t('risk.cs_critical');
            } elseif ($cs <= 80) {
                $label = t('risk.cs_low');
            } elseif ($cs <= 180) {
                $label = t('risk.cs_average');
            } elseif ($cs <= 280) {
                $label = t('risk.cs_good');
            } else {
                $label = t('risk.cs_excellent');
            }

            $details = "Credit score: {$cs} ({$label})";
            if ($bkStat === 'recovered') {
                $details .= ' ' . t('risk.cs_recovery_penalty');
            }

            return [
                'points'  => $points,
                'max'     => 15,
                'details' => $details,
            ];
        } catch (Throwable $e) {
            GameLog::error('RiskScoreEngine', 'scoreCreditScore failed', $e, ['player_id' => $playerId]);
            return ['points' => 5, 'max' => 15, 'details' => t('risk.cs_read_error')];
        }
    }
}
