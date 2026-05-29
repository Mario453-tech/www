<?php

/**
 * HubIncidentEffectsTrait — incident generation, DB persistence and player notification.
 * Used by HubIncidentService.
 */
trait HubIncidentEffectsTrait
{
    /**
     * Generuje incydent, zapisuje do DB i zwraca dane zdarzenia.
     *
     * @param array<string, mixed> $cfg
     * @param array<string, mixed> $hub
     * @param array<string, mixed> $tickResult
     * @return array<string, mixed>
     */
    private function generateIncident(
        string $type,
        array  $cfg,
        array  $hub,
        float  $inputBbl,
        array  $tickResult,
        int    $playerId
    ): array {
        $hubId = (int)$hub['id'];

        [$dmgMin, $dmgMax] = $cfg['condition_dmg'];
        [$losMin, $losMax] = $cfg['extra_loss_pct'];

        $condDmg      = $dmgMax > $dmgMin ? mt_rand($dmgMin, $dmgMax) : $dmgMin;
        $extraLossPct = $losMax > $losMin ? mt_rand($losMin, $losMax) : $losMin;
        $extraLoss    = round($inputBbl * $extraLossPct / 100.0, 2);

        $msgCount = self::MSG_COUNT[$type] ?? 1;
        $message  = tPlain("logistics.hub.incident.{$type}." . mt_rand(0, $msgCount - 1), [
            'hub'      => $hub['name'] ?? "Hub #{$hubId}",
            'cond'     => (int)round((float)($hub['condition_pct'] ?? 100)),
            'load'     => (int)round((float)($tickResult['load_pct'] ?? 0)),
            'loss_bbl' => number_format($extraLoss, 1, '.', ' '),
            'loss_pct' => $extraLossPct,
            'cond_dmg' => $condDmg,
        ]);

        if ($condDmg > 0) {
            $this->applyConditionDamage($hubId, $condDmg);
        }

        $this->saveEvent($hubId, $playerId, $type, $cfg['severity'], $message, [
            'condition_dmg'  => $condDmg,
            'extra_loss_bbl' => $extraLoss,
            'extra_loss_pct' => $extraLossPct,
            'hub_load_pct'   => $tickResult['load_pct'] ?? 0,
            'hub_condition'  => $hub['condition_pct'] ?? 100,
        ]);

        $this->notifyPlayer($playerId, (string)$cfg['severity'], $message);

        GameLog::info('tick', 'hub_incident', [
            'type'      => $type,
            'hub_id'    => $hubId,
            'player_id' => $playerId,
            'extra_loss'=> $extraLoss,
            'cond_dmg'  => $condDmg,
            'severity'  => $cfg['severity'],
        ]);

        return [
            'type'       => $type,
            'severity'   => (string)$cfg['severity'],
            'hub_id'     => $hubId,
            'extra_loss' => $extraLoss,
            'cond_dmg'   => $condDmg,
            'message'    => $message,
        ];
    }

    private function applyConditionDamage(int $hubId, int $dmg): void
    {
        $this->db->prepare(
            "UPDATE logistics_hubs
                SET condition_pct = GREATEST(0.00, condition_pct - ?),
                    updated_at    = NOW()
              WHERE id = ?"
        )->execute([(float)$dmg, $hubId]);
    }

    /** @param array<string, mixed> $meta */
    private function saveEvent(
        int    $hubId,
        int    $playerId,
        string $type,
        string $severity,
        string $message,
        array  $meta
    ): void {
        $this->db->prepare(
            "INSERT INTO logistics_hub_events
                (player_id, hub_id, well_id, event_type, severity, title, message, meta_json, created_at)
             VALUES (?, ?, NULL, ?, ?, ?, ?, ?, NOW())"
        )->execute([
            $playerId,
            $hubId,
            'hub_incident_' . $type,
            $severity,
            tPlain("logistics.hub.incident.title.{$type}"),
            $message,
            json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function notifyPlayer(int $playerId, string $severity, string $message): void
    {
        $icon = match($severity) {
            'critical' => '',
            'high'     => '',
            'medium'   => '',
            default    => '',
        };
        $this->db->prepare(
            "INSERT INTO technical_notifications (player_id, well_id, type, message)
             VALUES (?, NULL, 'hub_incident', ?)"
        )->execute([$playerId, "{$icon} [Hub] {$message}"]);
    }
}
