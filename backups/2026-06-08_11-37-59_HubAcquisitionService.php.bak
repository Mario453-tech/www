<?php

/**
 * HubAcquisitionService - player hub ownership actions.
 * Kupno, wynajem i migracja hubw logistycznych.
 * Buy, rent, and tenant-migration for logistics hubs.
 *
 * Model:
 * player_id > 0 : hub jest wasnoci tego gracza (kupno nowego/uywanego)
 * player_id = 0 : hub jest na rynku (systemowy)
 * tenant_player_id > 0 : hub jest wynajmowany przez tego gracza
 * tenant_player_id = 0 : hub dostpny do kupna/wynajmu
 */
class HubAcquisitionService
{
    private PDO        $db;
    private HubService $hubSvc;

    public function __construct(PDO $db, HubService $hubSvc)
    {
        $this->db     = $db;
        $this->hubSvc = $hubSvc;
    }

 // ------------------------------------------------------------------ public

 /**
 * Gracz kupuje nowy hub w swoim regionie.
 * Player builds a brand-new hub in their region.
 * Cost = build_cost based on hub_type + region multiplier.
 *
 * @param array<string, mixed> $params [hub_type, region_id, zone_key, name]
 * @return array{success: bool, hub_id?: int, cost?: float, error?: string}
 */
    public function buyNew(int $playerId, array $params): array
    {
        $hubType  = $params['hub_type']  ?? 'small';
        $regionId = (int)($params['region_id'] ?? 0);
        $zoneKey  = $params['zone_key']  ?? '';
        $name     = trim($params['name'] ?? '');

        if (!in_array($hubType, ['small', 'medium', 'large'], true)) {
            return ['success' => false, 'error' => 'invalid_hub_type'];
        }
        if ($regionId <= 0) {
            return ['success' => false, 'error' => 'invalid_region'];
        }
        if ($name === '' || strlen($name) > 120) {
            return ['success' => false, 'error' => 'invalid_name'];
        }

        $defaults   = $this->hubSvc->getHubTypeDefaults($hubType, 1);
        $acqDefault = $this->hubSvc->getAcquisitionDefaults('new');
        $regionMult = max(0.1, (float)$this->hubSvc->cfg('region', $regionId . '.build_cost_mult', '1.0'));
        $cost       = round((float)$defaults['build_cost'] * $regionMult * (float)$acqDefault['build_cost_mult'], 2);

        $cashCheck = $this->checkAndDeductCash($playerId, $cost);
        if (!$cashCheck['ok']) {
            return ['success' => false, 'error' => 'insufficient_funds'];
        }

        try {
            $now         = date('Y-m-d H:i:s');
            $condStart   = 100.00;
            $leaseFee    = 0.00;

            $this->db->prepare(
                "INSERT INTO logistics_hubs
                    (player_id, tenant_player_id, region_id, zone_key, name, hub_type,
                     acquisition_type, status, work_mode, level, slot_limit,
                     condition_pct, initial_condition_pct, wear_level, efficiency_pct,
                     nominal_capacity_bph, real_capacity_bph,
                     buffer_capacity_bbl, buffer_current_bbl,
                     opex_per_tick, lease_fee_per_tick,
                     build_cost, acquisition_price, acquired_at,
                     repair_cost_estimate, last_maintenance_at, created_at, updated_at)
                 VALUES
                    (?, 0, ?, ?, ?, ?, 'new', 'active', 'standard',
                     1, ?, ?, ?, 0.0000, ?,
                     ?, ?, ?, 0.00,
                     ?, ?,
                     ?, ?, ?,
                     0.00, ?, ?, ?)"
            )->execute([
                $playerId,
                $regionId, $zoneKey, $name, $hubType,
                $defaults['slot_limit'],
                $condStart, $condStart, min(100.0, $condStart),
                $defaults['nominal_bph'], $defaults['nominal_bph'],
                $defaults['buffer_bbl'],
                $defaults['opex_per_tick'], $leaseFee,
                $cost, $cost, $now,
                $now, $now, $now,
            ]);

            $hubId = (int)$this->db->lastInsertId();

            GameLog::info('HubAcquisitionService', 'Player bought new hub', [
                'player_id' => $playerId,
                'hub_id'    => $hubId,
                'type'      => $hubType,
                'region_id' => $regionId,
                'cost'      => $cost,
            ]);

            return ['success' => true, 'hub_id' => $hubId, 'cost' => $cost];
        } catch (Throwable $e) {
            $this->refundCash($playerId, $cost);
            GameLog::error('HubAcquisitionService', 'buyNew failed', $e, ['player_id' => $playerId]);
            return ['success' => false, 'error' => 'db_error'];
        }
    }

 /**
 * Gracz kupuje istniejcy hub z rynku (player_id = 0).
 * Player buys an existing market hub (player_id = 0, any acquisition_type).
 * After purchase: hub.player_id = playerId.
 *
 * @return array{success: bool, cost?: float, error?: string}
 */
    public function buyUsed(int $playerId, int $hubId): array
    {
        $hub = $this->hubSvc->getHub($hubId);
        if (!$hub) {
            return ['success' => false, 'error' => 'hub_not_found'];
        }
        if ((int)$hub['player_id'] !== 0) {
            return ['success' => false, 'error' => 'hub_already_owned'];
        }
        if ((int)$hub['tenant_player_id'] !== 0 && (int)$hub['tenant_player_id'] !== $playerId) {
            return ['success' => false, 'error' => 'hub_already_rented'];
        }
        if (in_array($hub['status'], ['disabled', 'building'], true)) {
            return ['success' => false, 'error' => 'hub_unavailable'];
        }

        $cost = (float)($hub['acquisition_price'] > 0 ? $hub['acquisition_price'] : $hub['build_cost']);

        $cashCheck = $this->checkAndDeductCash($playerId, $cost);
        if (!$cashCheck['ok']) {
            return ['success' => false, 'error' => 'insufficient_funds'];
        }

        try {
            $now = date('Y-m-d H:i:s');
            $this->db->prepare(
                "UPDATE logistics_hubs
                    SET player_id = ?, tenant_player_id = 0,
                        acquisition_price = ?, acquired_at = ?,
                        updated_at = ?
                  WHERE id = ? AND player_id = 0"
            )->execute([$playerId, $cost, $now, $now, $hubId]);

            GameLog::info('HubAcquisitionService', 'Player bought used/market hub', [
                'player_id' => $playerId,
                'hub_id'    => $hubId,
                'cost'      => $cost,
            ]);

            return ['success' => true, 'cost' => $cost];
        } catch (Throwable $e) {
            $this->refundCash($playerId, $cost);
            GameLog::error('HubAcquisitionService', 'buyUsed failed', $e, ['player_id' => $playerId]);
            return ['success' => false, 'error' => 'db_error'];
        }
    }

 /**
 * Gracz wynajmuje hub z rynku (player_id = 0, tenant_player_id = 0).
 * Player rents a market hub exclusively. Hub stays player_id = 0 but is reserved.
 * Ongoing lease_fee_per_tick is charged each tick via WellHubSection.
 * One-time deposit: 3x monthly lease fee.
 *
 * @return array{success: bool, deposit?: float, error?: string}
 */
    public function rent(int $playerId, int $hubId): array
    {
        $hub = $this->hubSvc->getHub($hubId);
        if (!$hub) {
            return ['success' => false, 'error' => 'hub_not_found'];
        }
        if ((int)$hub['player_id'] !== 0) {
            return ['success' => false, 'error' => 'hub_already_owned'];
        }
        if ((int)$hub['tenant_player_id'] !== 0) {
            return ['success' => false, 'error' => 'hub_already_rented'];
        }
        if (in_array($hub['status'], ['disabled', 'building'], true)) {
            return ['success' => false, 'error' => 'hub_unavailable'];
        }

 // Deposit = 3 ticks of full lease fee (non-refundable)
        $leaseFee = (float)($hub['lease_fee_per_tick'] ?? 0.0);
        $deposit  = round($leaseFee * 3.0, 2);

        if ($deposit > 0.0) {
            $cashCheck = $this->checkAndDeductCash($playerId, $deposit);
            if (!$cashCheck['ok']) {
                return ['success' => false, 'error' => 'insufficient_funds'];
            }
        }

        try {
            $now = date('Y-m-d H:i:s');
            $this->db->prepare(
                "UPDATE logistics_hubs
                    SET tenant_player_id = ?, acquired_at = ?, updated_at = ?
                  WHERE id = ? AND player_id = 0 AND tenant_player_id = 0"
            )->execute([$playerId, $now, $now, $hubId]);

            GameLog::info('HubAcquisitionService', 'Player rented hub', [
                'player_id' => $playerId,
                'hub_id'    => $hubId,
                'deposit'   => $deposit,
                'lease_fee' => $leaseFee,
            ]);

            return ['success' => true, 'deposit' => $deposit];
        } catch (Throwable $e) {
            if ($deposit > 0.0) {
                $this->refundCash($playerId, $deposit);
            }
            GameLog::error('HubAcquisitionService', 'rent failed', $e, ['player_id' => $playerId]);
            return ['success' => false, 'error' => 'db_error'];
        }
    }

    /**
     * Gracz rozbudowuje wlasny hub do kolejnego poziomu.
     * Player upgrades an owned hub to the next level.
     *
     * @return array{success: bool, new_level?: int, cost?: float, error?: string}
     */
    public function upgradeOwned(int $playerId, int $hubId): array
    {
        $hub = $this->hubSvc->getHub($hubId);
        if (!$hub) {
            return ['success' => false, 'error' => 'hub_not_found'];
        }
        if ((int)($hub['player_id'] ?? 0) !== $playerId) {
            return ['success' => false, 'error' => 'hub_not_owned'];
        }
        if (in_array((string)($hub['status'] ?? ''), ['disabled', 'building'], true)) {
            return ['success' => false, 'error' => 'hub_unavailable'];
        }

        $currentLevel = (int)($hub['level'] ?? 1);
        $defaults = $this->hubSvc->getHubTypeDefaults((string)$hub['hub_type'], $currentLevel);
        if ($currentLevel >= (int)($defaults['max_level'] ?? 3)) {
            return ['success' => false, 'error' => 'max_level'];
        }

        $cost = round((float)($defaults['upgrade_cost'] ?? 0.0), 2);
        $cashCheck = $this->checkAndDeductCash($playerId, $cost);
        if (!$cashCheck['ok']) {
            return ['success' => false, 'error' => 'insufficient_funds', 'cost' => $cost];
        }

        try {
            $result = $this->hubSvc->upgradeHub($hubId, $playerId);
            if (!$result['success']) {
                $this->refundCash($playerId, $cost);
                return [
                    'success' => false,
                    'error'   => $result['error'] ?? 'db_error',
                    'cost'    => $cost,
                ];
            }

            GameLog::info('HubAcquisitionService', 'Player upgraded owned hub', [
                'player_id' => $playerId,
                'hub_id'    => $hubId,
                'level'     => $result['new_level'] ?? null,
                'cost'      => $cost,
            ]);

            return [
                'success'   => true,
                'new_level' => (int)($result['new_level'] ?? ($currentLevel + 1)),
                'cost'      => $cost,
            ];
        } catch (Throwable $e) {
            $this->refundCash($playerId, $cost);
            GameLog::error('HubAcquisitionService', 'upgradeOwned failed', $e, [
                'player_id' => $playerId,
                'hub_id'    => $hubId,
            ]);
            return ['success' => false, 'error' => 'db_error', 'cost' => $cost];
        }
    }

 /**
 * One-time migration: players with active well assignments become tenants.
 * Runs during ETAP 1 deploy; idempotent (skips hubs that already have tenant/owner).
 * For each market hub (player_id=0) with active assignments: sets the player
 * with the most wells as tenant_player_id.
 * Runs only on MySQL (not SQLite test env).
 */
    public function migrateExistingAssignmentsToTenancy(): void
    {
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return;
        }
        try {
 // For market hubs with no tenant yet and with active assignments,
 // set the player with the most wells as tenant.
            $stmt = $this->db->query(
                "SELECT a.hub_id, w.player_id, COUNT(*) AS well_cnt
                   FROM logistics_hub_assignments a
                   JOIN wells w ON w.id = a.well_id
                   JOIN logistics_hubs h ON h.id = a.hub_id
                  WHERE a.status = 'active'
                    AND h.player_id = 0
                    AND h.tenant_player_id = 0
                  GROUP BY a.hub_id, w.player_id
                  ORDER BY a.hub_id, well_cnt DESC"
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            $alreadyMigrated = [];
            $now = date('Y-m-d H:i:s');

            foreach ($rows as $row) {
                $hubId    = (int)$row['hub_id'];
                $tenantId = (int)$row['player_id'];

 // First (highest count) player per hub wins the tenancy
                if (isset($alreadyMigrated[$hubId])) {
                    continue;
                }
                $alreadyMigrated[$hubId] = true;

                $this->db->prepare(
                    "UPDATE logistics_hubs
                        SET tenant_player_id = ?, updated_at = ?
                      WHERE id = ? AND player_id = 0 AND tenant_player_id = 0"
                )->execute([$tenantId, $now, $hubId]);
            }

            if (!empty($alreadyMigrated)) {
                GameLog::info('HubAcquisitionService', 'Tenant migration complete', [
                    'hubs_migrated' => count($alreadyMigrated),
                ]);
            }
        } catch (Throwable $e) {
            GameLog::error('HubAcquisitionService', 'migrateExistingAssignmentsToTenancy failed', $e);
        }
    }

 // ------------------------------------------------------------------ private

 /** @return array{ok: bool} */
    private function checkAndDeductCash(int $playerId, float $amount): array
    {
        if ($amount <= 0.0) {
            return ['ok' => true];
        }
        $stmt = $this->db->prepare("SELECT cash FROM players WHERE id = ? LIMIT 1");
        $stmt->execute([$playerId]);
        $cash = (float)($stmt->fetchColumn() ?? 0.0);
        if ($cash < $amount) {
            return ['ok' => false];
        }
        $this->db->prepare("UPDATE players SET cash = cash - ? WHERE id = ?")
            ->execute([$amount, $playerId]);
        return ['ok' => true];
    }

    private function refundCash(int $playerId, float $amount): void
    {
        if ($amount <= 0.0) {
            return;
        }
        try {
            $this->db->prepare("UPDATE players SET cash = cash + ? WHERE id = ?")
                ->execute([$amount, $playerId]);
        } catch (Throwable $e) {
            GameLog::error('HubAcquisitionService', 'Cash refund failed', $e, [
                'player_id' => $playerId,
                'amount'    => $amount,
            ]);
        }
    }
}
