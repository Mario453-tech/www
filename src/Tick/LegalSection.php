<?php

declare(strict_types=1);

/**
 * LegalSection — Etap 4: tick rozpatrujący wnioski o zezwolenia na wiercenie.
 *
 * Uruchamiany raz per tick globalnie (nie per gracz).
 * Pobiera wszystkie wnioski ze statusem pending/delayed gdzie decision_due_at <= now,
 * losuje wynik wg parametrów admin (no_decision → refused → delayed → granted)
 * i wysyła powiadomienia dyrektora (type='legal').
 */
class LegalSection
{
    public int $decided  = 0;
    public int $notified = 0;

    private PDO      $db;
    private DateTime $now;

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
        return match ($outcome) {
            'granted' => [
                'Zezwolenie na wiercenie przyznane',
                "Twój wniosek o zezwolenie na wiercenie w regionie #{$regionId} został rozpatrzony pozytywnie.",
                '✅',
                'high',
            ],
            'refused' => [
                'Wniosek o zezwolenie odrzucony',
                "Urząd odmówił wydania zezwolenia na wiercenie w regionie #{$regionId}. Sprawdź kiedy możesz złożyć ponowny wniosek.",
                '❌',
                'high',
            ],
            'delayed' => [
                'Decyzja o zezwoleniu opóźniona',
                "Rozpatrzenie wniosku o zezwolenie na wiercenie w regionie #{$regionId} zostało opóźnione. Nowy termin decyzji został wyznaczony.",
                '⏳',
                'medium',
            ],
            'no_decision' => [
                'Brak decyzji w sprawie zezwolenia',
                "Urząd nie wydał decyzji w sprawie zezwolenia na wiercenie w regionie #{$regionId}. Wniosek pozostaje bez rozpatrzenia.",
                '⚠️',
                'medium',
            ],
            default => [
                'Aktualizacja wniosku o zezwolenie',
                "Status Twojego wniosku o zezwolenie na wiercenie w regionie #{$regionId} został zaktualizowany.",
                'ℹ️',
                'low',
            ],
        };
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
