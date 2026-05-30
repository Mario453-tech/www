<?php

/**
 * HubAssignmentValidationTrait - internal validation helpers for well-to-hub assignments.
 * Used by HubAssignmentService.
 */
trait HubAssignmentValidationTrait
{
    /**
     * Validates an assignment (or transfer if $skipCurrentCheck=true).
     *
     * @return array{ok: bool, error?: string, hub?: array<string, mixed>, warning?: ?string}
     */
    private function validateAssignment(
        int  $playerId,
        int  $hubId,
        int  $wellId,
        bool $skipCurrentCheck = false
    ): array {
        $hub = $this->hubSvc->getHub($hubId);
        if (!$hub) {
            return ['ok' => false, 'error' => 'hub_not_found'];
        }

        if (in_array($hub['status'], ['disabled', 'building', 'paused'], true)) {
            return ['ok' => false, 'error' => 'hub_unavailable'];
        }

        $well = $this->getWell($wellId, $playerId);
        if (!$well) {
            return ['ok' => false, 'error' => 'well_not_found'];
        }

        if (!$skipCurrentCheck) {
            $existing = $this->hubSvc->getWellAssignment($wellId);
            if ($existing && $existing['status'] === 'active') {
                return ['ok' => false, 'error' => 'already_assigned'];
            }
            $cooldownRow = $this->getCooldownAssignment($wellId);
            if ($cooldownRow !== null) {
                $remainSecs = max(0, (int)(strtotime((string)$cooldownRow['cooldown_until']) - time()));
                return ['ok' => false, 'error' => 'cooldown_active', 'cooldown_remaining_s' => $remainSecs];
            }
        }

        if ((int)($well['region_id'] ?? 0) !== (int)$hub['region_id']) {
            GameLog::warn('HubAssignmentService', 'Well transfer blocked by region mismatch', [
                'hub_id'      => $hubId,
                'well_id'     => $wellId,
                'well_reg_id' => $well['region_id'] ?? 0,
                'hub_reg_id'  => $hub['region_id'],
            ]);
            return ['ok' => false, 'error' => 'region_mismatch'];
        }

        if ((int)$hub['assigned_count'] >= (int)$hub['slot_limit']) {
            return ['ok' => false, 'error' => 'slots_full'];
        }

        $cond    = (float)($hub['condition_pct'] ?? 100.0);
        $warning = match(true) {
            $cond <= 20.0 => 'condition_critical',
            $cond <= 40.0 => 'condition_low',
            default       => null,
        };

        return ['ok' => true, 'hub' => $hub, 'warning' => $warning];
    }

    /**
     * Returns the active cooldown assignment for a well, or null.
     * @return array<string, mixed>|null
     */
    private function getCooldownAssignment(int $wellId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, cooldown_until
               FROM logistics_hub_assignments
              WHERE well_id       = ?
                AND status        = 'detached'
                AND cooldown_until > NOW()
              ORDER BY cooldown_until DESC
              LIMIT 1"
        );
        $stmt->execute([$wellId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Returns a well owned by the given player, or null.
     * @return array<string, mixed>|null
     */
    private function getWell(int $wellId, int $playerId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, player_id, region_id, zone_key, status
               FROM wells WHERE id = ? AND player_id = ? LIMIT 1"
        );
        $stmt->execute([$wellId, $playerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function getWellZoneKey(int $wellId): string
    {
        $stmt = $this->db->prepare("SELECT zone_key FROM wells WHERE id = ? LIMIT 1");
        $stmt->execute([$wellId]);
        return (string)($stmt->fetchColumn() ?: '');
    }
}
