<?php

declare(strict_types=1);

/**
 * LegalSection — Etap 4+P2a: tick rozpatruajcy wnioski o zezwolenia (wiercenie + huby).
 * LegalSection — Step 4+P2a: tick processing permit applications (drilling + hubs).
 *
 * Uruchamiany raz per tick globalnie (nie per gracz).
 * Pobiera wszystkie wnioski ze statusem pending/delayed gdzie decision_due_at <= now.
 * Losuje wynik wg parametrow admin (no_decision > refused > delayed > granted).
 */
class LegalSection
{
    public int $decided  = 0;
    public int $notified = 0;

    /** Licznik decyzji dla zezwolen na huby / Hub permit decision counter. */
    public int $hubDecided  = 0;
    public int $hubNotified = 0;

    private PDO      $db;
    private DateTime $now;

    /** @var array<int,string> Cache nazw regionów w obrębie jednego ticku. */
    private array $regionNames = [];

    public function __construct(PDO $db, DateTime $now)
    {
        $this->db  = $db;
        $this->now = $now;
    }

    public function run(): void
    {
        $nowStr = $this->now->format('Y-m-d H:i:s');

        try {
            $stmt = $this->db->prepare(
                "SELECT a.id, a.player_id, a.region_id, a.delay_count,
                        c.no_decision_risk_pct, c.refusal_risk_pct,
                        c.delay_risk_pct, c.delay_min_minutes, c.delay_max_minutes,
                        c.refusal_cooldown_minutes, c.base_review_minutes,
                        c.risk_level
                   FROM drilling_permit_applications a
                   JOIN legal_region_config c ON c.region_id = a.region_id
                  WHERE a.status IN ('pending','delayed')
                    AND a.decision_due_at IS NOT NULL
                    AND a.decision_due_at <= ?
                  ORDER BY a.decision_due_at ASC"
            );
            $stmt->execute([$nowStr]);
            $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalSection', 'fetch overdue applications FAILED', $e);
            }
            return;
        }

        foreach ($applications as $app) {
            try {
                $this->processApplication($app, $nowStr);
            } catch (Throwable $e) {
                if (class_exists('GameLog', false)) {
                    GameLog::error('LegalSection', 'processApplication FAILED', $e, [
                        'application_id' => $app['id'] ?? null,
                        'player_id'      => $app['player_id'] ?? null,
                    ]);
                }
            }
        }

        // P2a: rozpatrzenie wnioskow o zezwolenia na huby / P2a: process hub permit applications
        $this->runHubPermits($nowStr);
    }

    /**
     * Przetwarza zalegajace wnioski o zezwolenia na huby logistyczne.
     * Processes overdue hub permit applications.
     */
    private function runHubPermits(string $nowStr): void
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT a.id, a.player_id, a.region_id, a.delay_count,
                        c.no_decision_risk_pct, c.refusal_risk_pct,
                        c.delay_risk_pct, c.delay_min_minutes, c.delay_max_minutes,
                        c.refusal_cooldown_minutes, c.hub_review_minutes AS base_review_minutes,
                        c.risk_level
                   FROM hub_permit_applications a
                   JOIN legal_region_config c ON c.region_id = a.region_id
                  WHERE a.status IN ('pending','delayed')
                    AND a.decision_due_at IS NOT NULL
                    AND a.decision_due_at <= ?
                  ORDER BY a.decision_due_at ASC"
            );
            $stmt->execute([$nowStr]);
            $hubApps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalSection', 'runHubPermits: fetch FAILED', $e);
            }
            return;
        }

        foreach ($hubApps as $app) {
            try {
                $this->processHubApplication($app, $nowStr);
            } catch (Throwable $e) {
                if (class_exists('GameLog', false)) {
                    GameLog::error('LegalSection', 'processHubApplication FAILED', $e, [
                        'hub_app_id' => $app['id'] ?? null,
                        'player_id'  => $app['player_id'] ?? null,
                    ]);
                }
            }
        }
    }

    /** @param array<string,mixed> $app */
    private function processHubApplication(array $app, string $nowStr): void
    {
        $appId      = (int)$app['id'];
        $playerId   = (int)$app['player_id'];
        $regionId   = (int)$app['region_id'];
        $delayCount = (int)$app['delay_count'];

        $noDecRisk   = (float)$app['no_decision_risk_pct'];
        $refusRisk   = (float)$app['refusal_risk_pct'];
        $delayRisk   = (float)$app['delay_risk_pct'];
        $delayMin    = (int)$app['delay_min_minutes'];
        $delayMax    = (int)$app['delay_max_minutes'];
        $cooldownMin = (int)$app['refusal_cooldown_minutes'];
        $riskLevel   = (string)$app['risk_level'];

        $roll = mt_rand(1, 100);

        // Priority: no_decision > refusal > delay > granted
        if ($noDecRisk > 0.0 && $roll <= (int)round($noDecRisk)) {
            $this->applyHubNoDecision($appId, $playerId, $regionId, $nowStr, $riskLevel);
            return;
        }
        $cumulative = (int)round($noDecRisk);
        if ($refusRisk > 0.0 && $roll <= $cumulative + (int)round($refusRisk)) {
            $this->applyHubRefusal($appId, $playerId, $regionId, $nowStr, $cooldownMin, $riskLevel);
            return;
        }
        $cumulative += (int)round($refusRisk);
        if ($delayRisk > 0.0 && $roll <= $cumulative + (int)round($delayRisk)) {
            $addMinutes = $delayMin < $delayMax ? mt_rand($delayMin, $delayMax) : $delayMin;
            $this->applyHubDelay($appId, $playerId, $regionId, $nowStr, $addMinutes, $delayCount, $riskLevel);
            return;
        }
        $this->applyHubGranted($appId, $playerId, $regionId, $nowStr, $riskLevel);
    }

    private function applyHubGranted(int $appId, int $playerId, int $regionId, string $nowStr, string $riskLevel): void
    {
        $this->db->prepare(
            "UPDATE hub_permit_applications SET status='granted', decided_at=? WHERE id=?"
        )->execute([$nowStr, $appId]);
        $this->hubDecided++;
        $this->notifyHub($playerId, $regionId, 'granted', $riskLevel, $nowStr, false);
    }

    private function applyHubRefusal(
        int $appId, int $playerId, int $regionId, string $nowStr, int $cooldownMin, string $riskLevel
    ): void {
        $cooldownUntil = $this->addMinutes($nowStr, $cooldownMin);
        $this->db->prepare(
            "UPDATE hub_permit_applications
                SET status='refused', decided_at=?, refusal_cooldown_until=? WHERE id=?"
        )->execute([$nowStr, $cooldownUntil, $appId]);
        $this->hubDecided++;
        $this->notifyHub($playerId, $regionId, 'refused', $riskLevel, $nowStr, true);
    }

    private function applyHubDelay(
        int $appId, int $playerId, int $regionId, string $nowStr,
        int $addMinutes, int $currentDelayCount, string $riskLevel
    ): void {
        $newDueAt = $this->addMinutes($nowStr, $addMinutes);
        $this->db->prepare(
            "UPDATE hub_permit_applications
                SET status='delayed', decision_due_at=?, delay_count=? WHERE id=?"
        )->execute([$newDueAt, $currentDelayCount + 1, $appId]);
        $this->hubDecided++;
        $this->notifyHub($playerId, $regionId, 'delayed', $riskLevel, $nowStr, true);
    }

    private function applyHubNoDecision(
        int $appId, int $playerId, int $regionId, string $nowStr, string $riskLevel
    ): void {
        $this->db->prepare(
            "UPDATE hub_permit_applications SET status='no_decision', decided_at=? WHERE id=?"
        )->execute([$nowStr, $appId]);
        $this->hubDecided++;
        $this->notifyHub($playerId, $regionId, 'no_decision', $riskLevel, $nowStr, true);
    }

    private function notifyHub(
        int $playerId, int $regionId, string $outcome, string $riskLevel,
        string $nowStr, bool $requiresAction
    ): void {
        $region   = $this->regionName($regionId);
        $key      = match ($outcome) {
            'granted'     => 'granted',
            'refused'     => 'refused',
            'delayed'     => 'delayed',
            'no_decision' => 'no_decision',
            default       => 'granted',
        };

        try {
            $title   = tPlain("legal.hub.notif.{$key}.title");
            $message = tPlain("legal.hub.notif.{$key}.message", ['region' => $region]);
            $this->db->prepare(
                "INSERT INTO director_notifications
                    (player_id, type, priority, title, message, icon,
                     requires_action, action_url, action_label, expires_at)
                 VALUES (?, 'legal_hub', ?, ?, ?, 'legal', ?, 'legal.php', 'Dzial prawny — huby', ?)"
            )->execute([
                $playerId,
                $outcome === 'granted' ? 'high' : 'medium',
                $title,
                $message,
                $requiresAction ? 1 : 0,
                $this->addHours($nowStr, 72),
            ]);
            $this->hubNotified++;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalSection', 'notifyHub FAILED', $e, [
                    'player_id' => $playerId, 'outcome' => $outcome,
                ]);
            }
        }
    }

    /** @param array<string,mixed> $app */
    private function processApplication(array $app, string $nowStr): void
    {
        $appId      = (int)$app['id'];
        $playerId   = (int)$app['player_id'];
        $regionId   = (int)$app['region_id'];
        $delayCount = (int)$app['delay_count'];

        $noDecRisk  = (float)$app['no_decision_risk_pct'];
        $refusRisk  = (float)$app['refusal_risk_pct'];
        $delayRisk  = (float)$app['delay_risk_pct'];
        $delayMin   = (int)$app['delay_min_minutes'];
        $delayMax   = (int)$app['delay_max_minutes'];
        $cooldownMin = (int)$app['refusal_cooldown_minutes'];
        $riskLevel  = (string)$app['risk_level'];

        $roll = mt_rand(1, 100);

        // Priority: no_decision > refusal > delay > granted
        if ($noDecRisk > 0.0 && $roll <= (int)round($noDecRisk)) {
            $this->applyNoDecision($appId, $playerId, $regionId, $nowStr, $riskLevel);
            return;
        }

        $cumulative = (int)round($noDecRisk);
        if ($refusRisk > 0.0 && $roll <= $cumulative + (int)round($refusRisk)) {
            $this->applyRefusal($appId, $playerId, $regionId, $nowStr, $cooldownMin, $riskLevel);
            return;
        }

        $cumulative += (int)round($refusRisk);
        if ($delayRisk > 0.0 && $roll <= $cumulative + (int)round($delayRisk)) {
            $addMinutes = $delayMin < $delayMax
                ? mt_rand($delayMin, $delayMax)
                : $delayMin;
            $this->applyDelay($appId, $playerId, $regionId, $nowStr, $addMinutes, $delayCount, $riskLevel);
            return;
        }

        $this->applyGranted($appId, $playerId, $regionId, $nowStr, $riskLevel);
    }

    private function applyGranted(int $appId, int $playerId, int $regionId, string $nowStr, string $riskLevel): void
    {
        $this->db->prepare(
            "UPDATE drilling_permit_applications
                SET status     = 'granted',
                    decided_at = ?
              WHERE id = ?"
        )->execute([$nowStr, $appId]);

        $this->decided++;

        $this->notify($playerId, $regionId, 'granted', $riskLevel, $nowStr);
    }

    private function applyRefusal(
        int    $appId,
        int    $playerId,
        int    $regionId,
        string $nowStr,
        int    $cooldownMin,
        string $riskLevel
    ): void {
        $cooldownUntil = $this->addMinutes($nowStr, $cooldownMin);

        $this->db->prepare(
            "UPDATE drilling_permit_applications
                SET status                 = 'refused',
                    decided_at             = ?,
                    refusal_cooldown_until = ?
              WHERE id = ?"
        )->execute([$nowStr, $cooldownUntil, $appId]);

        $this->decided++;

        $this->notify($playerId, $regionId, 'refused', $riskLevel, $nowStr);
    }

    private function applyDelay(
        int    $appId,
        int    $playerId,
        int    $regionId,
        string $nowStr,
        int    $addMinutes,
        int    $currentDelayCount,
        string $riskLevel
    ): void {
        $newDueAt = $this->addMinutes($nowStr, $addMinutes);

        $this->db->prepare(
            "UPDATE drilling_permit_applications
                SET status          = 'delayed',
                    decision_due_at = ?,
                    delay_count     = ?
              WHERE id = ?"
        )->execute([$newDueAt, $currentDelayCount + 1, $appId]);

        $this->decided++;

        $this->notify($playerId, $regionId, 'delayed', $riskLevel, $nowStr);
    }

    private function applyNoDecision(
        int    $appId,
        int    $playerId,
        int    $regionId,
        string $nowStr,
        string $riskLevel
    ): void {
        $this->db->prepare(
            "UPDATE drilling_permit_applications
                SET status     = 'no_decision',
                    decided_at = ?
              WHERE id = ?"
        )->execute([$nowStr, $appId]);

        $this->decided++;

        $this->notify($playerId, $regionId, 'no_decision', $riskLevel, $nowStr);
    }

    private function notify(int $playerId, int $regionId, string $outcome, string $riskLevel, string $nowStr): void
    {
        [$title, $message, $icon, $priority] = $this->buildNotification($outcome, $regionId, $riskLevel);

        try {
            $this->db->prepare(
                "INSERT INTO director_notifications
                    (player_id, type, priority, title, message, icon,
                     requires_action, action_url, action_label, expires_at)
                 VALUES (?, 'legal', ?, ?, ?, ?, ?, 'legal.php', 'Dział prawny', ?)"
            )->execute([
                $playerId,
                $priority,
                $title,
                $message,
                $icon,
                ($outcome === 'granted') ? 0 : 1,
                $this->addHours($nowStr, 72),
            ]);

            $this->notified++;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalSection', 'notify FAILED', $e, [
                    'player_id' => $playerId,
                    'outcome'   => $outcome,
                ]);
            }
        }
    }

    /** @return array{string,string,string,string} [title, message, icon, priority] */
    private function buildNotification(string $outcome, int $regionId, string $riskLevel): array
    {
        $region = $this->regionName($regionId);

        [$key, $icon, $priority] = match ($outcome) {
            'granted'     => ['granted',     '✅', 'high'],
            'refused'     => ['refused',     '❌', 'high'],
            'delayed'     => ['delayed',     '⏳', 'medium'],
            'no_decision' => ['no_decision', '⚠️', 'medium'],
            default       => ['default',     'ℹ️', 'low'],
        };

        return [
            tPlain("legal.notif.{$key}.title"),
            tPlain("legal.notif.{$key}.message", ['region' => $region]),
            $icon,
            $priority,
        ];
    }

    /** Zwraca nazwę regionu (z fallbackiem "#id"). Wyniki cache'owane w obrębie ticku. */
    private function regionName(int $regionId): string
    {
        if (isset($this->regionNames[$regionId])) {
            return $this->regionNames[$regionId];
        }
        $name = '#' . $regionId;
        try {
            $stmt = $this->db->prepare("SELECT name FROM world_regions WHERE id = ? LIMIT 1");
            $stmt->execute([$regionId]);
            $row = $stmt->fetch();
            if ($row && !empty($row['name'])) {
                $name = (string)$row['name'];
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalSection', 'regionName FAILED', $e, ['region_id' => $regionId]);
            }
        }
        return $this->regionNames[$regionId] = $name;
    }

    private function addMinutes(string $datetime, int $minutes): string
    {
        $dt = new DateTime($datetime);
        $dt->modify("+{$minutes} minutes");
        return $dt->format('Y-m-d H:i:s');
    }

    private function addHours(string $datetime, int $hours): string
    {
        $dt = new DateTime($datetime);
        $dt->modify("+{$hours} hours");
        return $dt->format('Y-m-d H:i:s');
    }
}
