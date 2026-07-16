<?php

require_once __DIR__ . '/Hub/AssignmentValidationTrait.php';

/**
 * HubAssignmentService assign, detach and transfer wells between hubs.
 *
 * Hubs are SYSTEM-OWNED infrastructure (player_id = 0).
 * Players do not own hubs; they assign their OWN wells to any accessible system hub.
 *
 * Rules enforced:
 * - One well max one active hub assignment at a time
 * - Well must belong to the player performing the action
 * - Well and hub must be in the same region (region_id)
 * - Cross-zone assignment within the same region is allowed with a zone penalty
 * - Hub must have available slots (slot_limit)
 * - Hub must be active (not paused/damaged/disabled/building)
 * - Cooldown applies after a detach or transfer
 *
 * Traits:
 * HubAssignmentValidationTrait validateAssignment, getCooldownAssignment, getWell, getWellZoneKey
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

 // Public API 

 /**
 * Assigns a well to a hub.
 * @return array{success: bool, error?: string}
 */
    public function assignWell(int $playerId, int $hubId, int $wellId): array
    {
        $ownTransaction = !$this->db->inTransaction();
        try {
            if ($ownTransaction) {
                $this->db->beginTransaction();
            }

            // Lock the well and hub before repeating capacity and uniqueness checks.
            // Zablokuj odwiert i hub przed ponowna kontrola slotow i unikalnosci.
            $this->lockAssignmentScope($playerId, $wellId, [$hubId]);
            $validation = $this->validateAssignment($playerId, $hubId, $wellId);
            if (!$validation['ok']) {
                if ($ownTransaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                $ret = ['success' => false, 'error' => $validation['error']];
                if (isset($validation['cooldown_remaining_s'])) {
                    $ret['cooldown_remaining_s'] = $validation['cooldown_remaining_s'];
                }
                return $ret;
            }

            $hub = $validation['hub'];
            $regionId = (int)($hub['region_id'] ?? 0);
            if ($regionId <= 0) {
                $regionId = $this->getHubRegionId($hubId, $playerId);
            }
            if (!$this->hasLocalPermitOrNotRequired($playerId, $regionId)) {
                if ($ownTransaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return ['success' => false, 'error' => 'no_hub_permit', 'region_id' => $regionId];
            }

            $zone = $this->getWellZoneKey($wellId, $playerId);
            $now = date('Y-m-d H:i:s');
            $this->db->prepare(
                "INSERT INTO logistics_hub_assignments
                    (hub_id, well_id, status, access_fee_paid, assigned_at, created_at, updated_at)
                 VALUES (?, ?, 'active', 0.00, ?, ?, ?)"
            )->execute([$hubId, $wellId, $now, $now, $now]);

            if ($ownTransaction) {
                $this->db->commit();
            }

            GameLog::info('HubAssignmentService', 'Well assigned to hub', [
                'hub_id'    => $hubId,
                'well_id'   => $wellId,
                'player_id' => $playerId,
                'zone'      => $zone,
                'hub_zone'  => $hub['zone_key'],
            ]);

            try {
                $this->hubSvc->createEvent($playerId, $hubId, $wellId, 'well_assigned', 'info',
                    'Well assigned',
                    "Well #{$wellId} assigned to hub {$hub['name']}."
                );
            } catch (Throwable $eventError) {
                GameLog::error('HubAssignmentService', 'assignWell event failed', $eventError, [
                    'hub_id' => $hubId,
                    'well_id' => $wellId,
                ]);
            }

            return ['success' => true, 'warning' => $validation['warning'] ?? null, 'access_fee' => 0.0];
        } catch (Throwable $e) {
            if ($ownTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
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
        $well = $this->getWell($wellId, $playerId);
        if (!$well) {
            return ['success' => false, 'error' => 'well_not_found'];
        }

        $assignment = $this->hubSvc->getWellAssignment($wellId, $playerId);
        if (!$assignment) {
            return ['success' => false, 'error' => 'not_assigned'];
        }

        $hub = $this->hubSvc->getHub((int)$assignment['hub_id']);
        if (!$hub) {
            return ['success' => false, 'error' => 'hub_not_found'];
        }

        $cooldownUntil = date('Y-m-d H:i:s', strtotime('+' . self::COOLDOWN_HOURS . ' hours'));
        $ownTransaction = !$this->db->inTransaction();
        try {
            if ($ownTransaction) {
                $this->db->beginTransaction();
            }
            $this->lockAssignmentScope($playerId, $wellId, [(int)$assignment['hub_id']]);

            $assignment = $this->hubSvc->getWellAssignment($wellId, $playerId);
            if (!$assignment) {
                if ($ownTransaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return ['success' => false, 'error' => 'not_assigned'];
            }

            $update = $this->db->prepare(
                "UPDATE logistics_hub_assignments
                    SET status        = 'detached',
                        detached_at   = NOW(),
                        cooldown_until = ?,
                        updated_at    = NOW()
                  WHERE id = ?
                    AND well_id = ?
                    AND status = 'active'"
            );
            $update->execute([$cooldownUntil, (int)$assignment['id'], $wellId]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Active hub assignment changed during detach.');
            }

            if ($ownTransaction) {
                $this->db->commit();
            }

            GameLog::info('HubAssignmentService', 'Well detached from hub', [
                'hub_id'    => $assignment['hub_id'],
                'well_id'   => $wellId,
                'player_id' => $playerId,
            ]);

            try {
                $this->hubSvc->createEvent($playerId, (int)$assignment['hub_id'], $wellId,
                    'well_detached', 'warning',
                    'Well detached',
                    "Well #{$wellId} detached from hub {$hub['name']}. Fallback logistics active."
                );
            } catch (Throwable $eventError) {
                GameLog::error('HubAssignmentService', 'detachWell event failed', $eventError, [
                    'well_id' => $wellId,
                ]);
            }

            return ['success' => true];
        } catch (Throwable $e) {
            if ($ownTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
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

        $assignment = $this->hubSvc->getWellAssignment($wellId, $playerId);
        if (!$assignment) {
            return ['success' => false, 'error' => 'not_assigned'];
        }
        if ((int)$assignment['hub_id'] === $newHubId) {
            return ['success' => false, 'error' => 'same_hub'];
        }

 // Old hub is system-owned existence check only
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
        $newRegionId   = (int)($newHub['region_id'] ?? $this->getHubRegionId($newHubId, $playerId));
        if (!$this->hasLocalPermitOrNotRequired($playerId, $newRegionId)) {
            return ['success' => false, 'error' => 'no_hub_permit'];
        }
        $cooldownUntil = date('Y-m-d H:i:s', strtotime('+' . self::COOLDOWN_HOURS . ' hours'));
        $now           = date('Y-m-d H:i:s');
        $ownTransaction = !$this->db->inTransaction();
        $savepoint = null;
        try {
            if ($ownTransaction) {
                $this->db->beginTransaction();
            } else {
                $savepoint = 'hub_assignment_transfer';
                $this->db->exec("SAVEPOINT {$savepoint}");
            }
            $this->lockAssignmentScope(
                $playerId,
                $wellId,
                [(int)$assignment['hub_id'], $newHubId]
            );

            $assignment = $this->hubSvc->getWellAssignment($wellId, $playerId);
            if (!$assignment) {
                $this->rollbackMutationScope($ownTransaction, $savepoint);
                return ['success' => false, 'error' => 'not_assigned'];
            }
            if ((int)$assignment['hub_id'] === $newHubId) {
                $this->rollbackMutationScope($ownTransaction, $savepoint);
                return ['success' => false, 'error' => 'same_hub'];
            }

            $oldHub = $this->hubSvc->getHub((int)$assignment['hub_id']);
            $validation = $this->validateAssignment($playerId, $newHubId, $wellId, skipCurrentCheck: true);
            if (!$oldHub || !$validation['ok']) {
                $this->rollbackMutationScope($ownTransaction, $savepoint);
                return ['success' => false, 'error' => !$oldHub ? 'hub_not_found' : $validation['error']];
            }
            $newHub = $validation['hub'];
            $newRegionId = (int)($newHub['region_id'] ?? $this->getHubRegionId($newHubId, $playerId));
            if (!$this->hasLocalPermitOrNotRequired($playerId, $newRegionId)) {
                $this->rollbackMutationScope($ownTransaction, $savepoint);
                return ['success' => false, 'error' => 'no_hub_permit'];
            }

            $close = $this->db->prepare(
                "UPDATE logistics_hub_assignments
                    SET status = 'detached', detached_at = NOW(), cooldown_until = ?, updated_at = NOW()
                  WHERE id = ?
                    AND well_id = ?
                    AND status = 'active'"
            );
            $close->execute([$cooldownUntil, (int)$assignment['id'], $wellId]);
            if ($close->rowCount() !== 1) {
                throw new RuntimeException('Active hub assignment changed during transfer.');
            }

 // Create new assignment
            $this->db->prepare(
                "INSERT INTO logistics_hub_assignments
                    (hub_id, well_id, status, assigned_at, created_at, updated_at)
                 VALUES (?, ?, 'active', ?, ?, ?)"
            )->execute([$newHubId, $wellId, $now, $now, $now]);

            if ($ownTransaction) {
                $this->db->commit();
            } elseif ($savepoint !== null) {
                $this->db->exec("RELEASE SAVEPOINT {$savepoint}");
            }

            GameLog::info('HubAssignmentService', 'Well transferred between hubs', [
                'old_hub_id' => $assignment['hub_id'],
                'new_hub_id' => $newHubId,
                'well_id'    => $wellId,
                'player_id'  => $playerId,
            ]);

            try {
                $this->hubSvc->createEvent($playerId, $newHubId, $wellId, 'well_transferred', 'info',
                    'Well transferred',
                    "Well #{$wellId} moved from {$oldHub['name']} to {$newHub['name']}."
                );
            } catch (Throwable $eventError) {
                GameLog::error('HubAssignmentService', 'transferWell event failed', $eventError, [
                    'well_id' => $wellId,
                    'new_hub_id' => $newHubId,
                ]);
            }

            return ['success' => true];
        } catch (Throwable $e) {
            $this->rollbackMutationScope($ownTransaction, $savepoint);
            GameLog::error('HubAssignmentService', 'transferWell failed', $e, [
                'well_id'    => $wellId,
                'new_hub_id' => $newHubId,
            ]);
            return ['success' => false, 'error' => 'db_error'];
        }
    }

    /**
     * Rolls back only this service operation when it runs inside a caller transaction.
     */
    private function rollbackMutationScope(bool $ownTransaction, ?string $savepoint): void
    {
        try {
            if (!$this->db->inTransaction()) {
                return;
            }
            if ($ownTransaction) {
                $this->db->rollBack();
                return;
            }
            if ($savepoint !== null) {
                $this->db->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
                $this->db->exec("RELEASE SAVEPOINT {$savepoint}");
            }
        } catch (Throwable $rollbackError) {
            GameLog::error('HubAssignmentService', 'rollbackMutationScope failed', $rollbackError);
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

    /**
     * Returns the hub region id, or 0 when it cannot be resolved.
     */
    private function getHubRegionId(int $hubId, int $playerId): int
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT region_id
                   FROM logistics_hubs
                  WHERE id = ?
                    AND (player_id = ? OR tenant_player_id = ?)
                  LIMIT 1"
            );
            $stmt->execute([$hubId, $playerId, $playerId]);
            return (int)($stmt->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            GameLog::error('HubAssignmentService', 'getHubRegionId failed', $e, [
                'hub_id' => $hubId,
                'player_id' => $playerId,
            ]);
            return 0;
        }
    }

    /**
     * Locks rows that define assignment uniqueness and hub capacity on MySQL.
     * Blokuje wiersze wyznaczajace unikalnosc przypisania i pojemnosc huba w MySQL.
     *
     * @param list<int> $hubIds
     */
    private function lockAssignmentScope(int $playerId, int $wellId, array $hubIds): void
    {
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return;
        }

        $wellStmt = $this->db->prepare(
            'SELECT id FROM wells WHERE id = ? AND player_id = ? FOR UPDATE'
        );
        $wellStmt->execute([$wellId, $playerId]);

        $hubIds = array_values(array_unique(array_filter(array_map('intval', $hubIds), static fn(int $id): bool => $id > 0)));
        sort($hubIds, SORT_NUMERIC);
        if ($hubIds !== []) {
            $placeholders = implode(',', array_fill(0, count($hubIds), '?'));
            $hubStmt = $this->db->prepare(
                "SELECT id FROM logistics_hubs WHERE id IN ({$placeholders}) ORDER BY id FOR UPDATE"
            );
            $hubStmt->execute($hubIds);
            $hubStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        $assignmentStmt = $this->db->prepare(
            "SELECT id FROM logistics_hub_assignments
              WHERE well_id = ? AND status = 'active'
              ORDER BY id FOR UPDATE"
        );
        $assignmentStmt->execute([$wellId]);
        $assignmentStmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Local works permit gate per region.
     * Returns true when permit enforcement is off or the player has a granted permit.
     * Database errors are fail-closed.
     */
    private function hasLocalPermitOrNotRequired(int $playerId, int $regionId): bool
    {
        try {
            $cfgStmt = $this->db->prepare(
                "SELECT hub_permit_enabled FROM legal_region_config WHERE region_id = ? LIMIT 1"
            );
            $cfgStmt->execute([$regionId]);
            $cfg = $cfgStmt->fetch();

            // No record or flag off - permit not required.
            if (!$cfg || (int)$cfg['hub_permit_enabled'] !== 1) {
                return true;
            }

            $permStmt = $this->db->prepare(
                "SELECT 1 FROM hub_permit_applications
                  WHERE player_id = ? AND region_id = ? AND status = 'granted' LIMIT 1"
            );
            $permStmt->execute([$playerId, $regionId]);
            return (bool)$permStmt->fetchColumn();
        } catch (Throwable $e) {
            // Missing legal-module tables means the permit system is not installed.
            if ($this->isMissingPermitTable($e)) {
                return true;
            }
            GameLog::warn('HubAssignmentService', 'Local permit gate failed - blocking (fail-closed)', [
                'player_id' => $playerId, 'region_id' => $regionId, 'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Checks whether the exception indicates a missing permit table.
 */
    private function isMissingPermitTable(Throwable $e): bool
    {
        $msg = $e->getMessage();
 // Brak tabeli (42S02) lub kolumny (42S22) = schemat zezwolen nieobecny.
 // Missing table (42S02) or column (42S22) = permit schema not present.
        return stripos($msg, 'no such table') !== false
            || stripos($msg, 'no such column') !== false
            || stripos($msg, 'unknown column') !== false
            || stripos($msg, "doesn't exist") !== false
            || stripos($msg, '42S02') !== false
            || stripos($msg, '42S22') !== false;
    }

}
