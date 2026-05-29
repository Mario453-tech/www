<?php

require_once __DIR__ . '/Hub/AssignmentValidationTrait.php';

/**
 * HubAssignmentService � assign, detach and transfer wells between hubs.
 *
 * Hubs are SYSTEM-OWNED infrastructure (player_id = 0).
 * Players do not own hubs; they assign their OWN wells to any accessible system hub.
 *
 * Rules enforced:
 *  - One well  max one active hub assignment at a time
 *  - Well must belong to the player performing the action
 *  - Well and hub must be in the same region (region_id)
 *  - Cross-zone assignment within the same region is allowed with a zone penalty
 *  - Hub must have available slots (slot_limit)
 *  - Hub must be active (not paused/damaged/disabled/building)
 *  - Cooldown applies after a detach or transfer
 *
 * Traits:
 *   HubAssignmentValidationTrait � validateAssignment, getCooldownAssignment, getWell, getWellZoneKey
 */
class HubAssignmentService
{
    use HubAssignmentValidationTrait;
    private const COOLDOWN_HOURS = 4;

    private PDO        $db;
    private HubService $hubSvc;

    public function __construct(PDO $db, HubService $hubSvc)
    {
        $this->db     = $db;
        $this->hubSvc = $hubSvc;
    }

    //  Public API 

    /**
     * Assigns a well to a hub.
     * @return array{success: bool, error?: string}
     */
    public function assignWell(int $playerId, int $hubId, int $wellId): array
    {
        $validation = $this->validateAssignment($playerId, $hubId, $wellId);
        if (!$validation['ok']) {
            $ret = ['success' => false, 'error' => $validation['error']];
            // Forward cooldown_remaining_s so callers (HubApi) can show exact wait time
            if (isset($validation['cooldown_remaining_s'])) {
                $ret['cooldown_remaining_s'] = $validation['cooldown_remaining_s'];
            }
            return $ret;
        }

        $zone = $this->getWellZoneKey($wellId);
        $hub  = $validation['hub'];

        // No access fee: player paid for hub ownership/tenancy at acquisition time.
        // Hub must already be owned (player_id = playerId) or rented (tenant_player_id = playerId).
        $accessFee = 0.0;

        try {
            $now = date('Y-m-d H:i:s');
            $this->db->prepare(
                "INSERT INTO logistics_hub_assignments
                    (hub_id, well_id, status, access_fee_paid, assigned_at, created_at, updated_at)
                 VALUES (?, ?, 'active', 0.00, ?, ?, ?)"
            )->execute([$hubId, $wellId, $now, $now, $now]);

            GameLog::info('HubAssignmentService', 'Well assigned to hub', [
                'hub_id'    => $hubId,
                'well_id'   => $wellId,
                'player_id' => $playerId,
                'zone'      => $zone,
                'hub_zone'  => $hub['zone_key'],
            ]);

            $this->hubSvc->createEvent($playerId, $hubId, $wellId, 'well_assigned', 'info',
                'Well assigned',
                "Well #{$wellId} assigned to hub {$hub['name']}."
            );

            return ['success' => true, 'warning' => $validation['warning'] ?? null, 'access_fee' => $accessFee];
        } catch (Throwable $e) {
            GameLog::error('HubAssignmentService', 'assignWell failed', $e, [
                'hub_id'  => $hubId,
                'well_id' => $wellId,
            ]);
            return ['success' => false, 'error' => 'db_error'];
        }
    }

    /**
     * Detaches a well from its hub.
     * Player must own the well; hub ownership is not checked (system hub).
     *
     * @return array{success: bool, error?: string}
     */
    public function detachWell(int $playerId, int $wellId): array
    {
        // Verify the well belongs to this player
        $well = $this->getWell($wellId, $playerId);
        if (!$well) {
            return ['success' => false, 'error' => 'well_not_found'];
        }

        $assignment = $this->hubSvc->getWellAssignment($wellId);
        if (!$assignment) {
            return ['success' => false, 'error' => 'not_assigned'];
        }

        // Hub is system-owned � existence check only, no ownership filter
        $hub = $this->hubSvc->getHub((int)$assignment['hub_id']);
        if (!$hub) {
            return ['success' => false, 'error' => 'hub_not_found'];
        }

        $cooldownUntil = date('Y-m-d H:i:s', strtotime('+' . self::COOLDOWN_HOURS . ' hours'));

        try {
            $this->db->prepare(
                "UPDATE logistics_hub_assignments
                    SET status        = 'detached',
                        detached_at   = NOW(),
                        cooldown_until = ?,
                        updated_at    = NOW()
                  WHERE id = ?"
            )->execute([$cooldownUntil, (int)$assignment['id']]);

            GameLog::info('HubAssignmentService', 'Well detached from hub', [
                'hub_id'    => $assignment['hub_id'],
                'well_id'   => $wellId,
                'player_id' => $playerId,
            ]);

            $this->hubSvc->createEvent($playerId, (int)$assignment['hub_id'], $wellId,
                'well_detached', 'warning',
                'Well detached',
                "Well #{$wellId} detached from hub {$hub['name']}. Fallback logistics active."
            );

            return ['success' => true];
        } catch (Throwable $e) {
            GameLog::error('HubAssignmentService', 'detachWell failed', $e, ['well_id' => $wellId]);
            return ['success' => false, 'error' => 'db_error'];
        }
    }

    /**
     * Transfers a well from its current hub to another hub.
     * Player must own the well; hubs are system-owned (no ownership check).
     *
     * @return array{success: bool, error?: string}
     */
    public function transferWell(int $playerId, int $wellId, int $newHubId): array
    {
        // Verify the well belongs to this player
        $well = $this->getWell($wellId, $playerId);
        if (!$well) {
            return ['success' => false, 'error' => 'well_not_found'];
        }

        $assignment = $this->hubSvc->getWellAssignment($wellId);
        if (!$assignment) {
            return ['success' => false, 'error' => 'not_assigned'];
        }
        if ((int)$assignment['hub_id'] === $newHubId) {
            return ['success' => false, 'error' => 'same_hub'];
        }

        // Old hub is system-owned � existence check only
        $oldHub = $this->hubSvc->getHub((int)$assignment['hub_id']);
        if (!$oldHub) {
            return ['success' => false, 'error' => 'hub_not_found'];
        }

        // Validate the new hub assignment
        $validation = $this->validateAssignment($playerId, $newHubId, $wellId, skipCurrentCheck: true);
        if (!$validation['ok']) {
            return ['success' => false, 'error' => $validation['error']];
        }

        $newHub        = $validation['hub'];
        $cooldownUntil = date('Y-m-d H:i:s', strtotime('+' . self::COOLDOWN_HOURS . ' hours'));
        $now           = date('Y-m-d H:i:s');

        try {
            $this->db->beginTransaction();

            // Close old assignment
            $this->db->prepare(
                "UPDATE logistics_hub_assignments
                    SET status = 'detached', detached_at = NOW(), cooldown_until = ?, updated_at = NOW()
                  WHERE id = ?"
            )->execute([$cooldownUntil, (int)$assignment['id']]);

            // Create new assignment
            $this->db->prepare(
                "INSERT INTO logistics_hub_assignments
                    (hub_id, well_id, status, assigned_at, created_at, updated_at)
                 VALUES (?, ?, 'active', ?, ?, ?)"
            )->execute([$newHubId, $wellId, $now, $now, $now]);

            $this->db->commit();

            GameLog::info('HubAssignmentService', 'Well transferred between hubs', [
                'old_hub_id' => $assignment['hub_id'],
                'new_hub_id' => $newHubId,
                'well_id'    => $wellId,
                'player_id'  => $playerId,
            ]);

            $this->hubSvc->createEvent($playerId, $newHubId, $wellId, 'well_transferred', 'info',
                'Well transferred',
                "Well #{$wellId} moved from {$oldHub['name']} to {$newHub['name']}."
            );

            return ['success' => true];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('HubAssignmentService', 'transferWell failed', $e, [
                'well_id'    => $wellId,
                'new_hub_id' => $newHubId,
            ]);
            return ['success' => false, 'error' => 'db_error'];
        }
    }

    /**
     * Returns the zone penalty multiplier for assigning a well (zone_key) to a hub (zone_key).
     * Returns 0.0 if no penalty applies.
     */
    public function getZonePenalty(string $wellZoneKey, string $hubZoneKey, int $regionId): float
    {
        if ($wellZoneKey === $hubZoneKey || $wellZoneKey === '' || $hubZoneKey === '') {
            return 0.0;
        }

        $zones   = $this->hubSvc->getRegionZones($regionId);
        $hubZone = null;
        foreach ($zones as $z) {
            if ($z['zone_key'] === $hubZoneKey) {
                $hubZone = $z;
                break;
            }
        }

        return $hubZone ? (float)$hubZone['distance_penalty_pct'] : 0.0;
    }

}
