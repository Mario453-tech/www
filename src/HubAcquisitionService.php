<?php

require_once __DIR__ . '/PlayerPaymentService.php';

/**
 * HubAcquisitionService - player hub ownership actions.
 * Buy, rent, and tenant-migration for logistics hubs.
 *
 * Model:
 * player_id > 0 : hub is owned by this player.
 * player_id = 0 : hub is listed on the system market.
 * tenant_player_id > 0 : hub is rented by this player.
 * tenant_player_id = 0 : hub is available for sale or rent.
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

        // P2a gate: hub permit required if hub_permit_enabled=1 in the region.
        if (!$this->hasHubPermitOrNotRequired($playerId, $regionId)) {
            return ['success' => false, 'error' => 'no_hub_permit', 'region_id' => $regionId];
        }

        $cashCheck = $this->checkAndDeductCash($playerId, $cost, tPlain('bank.tx_hub_build_new'));
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
            $this->refundCash($playerId, $cost, tPlain('bank.tx_hub_refund_generic'));
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

        // P2a gate: hub permit required if hub_permit_enabled=1 in the region.
        if (!$this->hasHubPermitOrNotRequired($playerId, (int)$hub['region_id'])) {
            return ['success' => false, 'error' => 'no_hub_permit', 'region_id' => (int)$hub['region_id']];
        }

        $cost = (float)($hub['acquisition_price'] > 0 ? $hub['acquisition_price'] : $hub['build_cost']);
 // Floor: zapobiegamy zakupowi za 0 zl gdy kolumna w DB jest zerowa.
 // Floor: prevents 0-PLN purchase when DB values were seeded from a zeroed config.
        static $buyFloors = ['small' => 31000.0, 'medium' => 93000.0, 'large' => 248000.0];
        if ($cost <= 0.0) {
            $cost = $buyFloors[$hub['hub_type'] ?? 'small'] ?? 31000.0;
        }

        $cashCheck = $this->checkAndDeductCash(
            $playerId,
            $cost,
            tPlain('bank.tx_hub_purchase', ['id' => $hubId]),
            'hub',
            $hubId
        );
        if (!$cashCheck['ok']) {
            return ['success' => false, 'error' => 'insufficient_funds'];
        }

        try {
            $now  = date('Y-m-d H:i:s');
            $stmt = $this->db->prepare(
                "UPDATE logistics_hubs
                    SET player_id = ?, tenant_player_id = 0,
                        acquisition_price = ?, acquired_at = ?,
                        updated_at = ?
                  WHERE id = ? AND player_id = 0"
            );
            $stmt->execute([$playerId, $cost, $now, $now, $hubId]);

            if ($stmt->rowCount() === 0) {
                $this->refundCash($playerId, $cost, tPlain('bank.tx_hub_refund', ['id' => $hubId]), 'hub', $hubId);
                return ['success' => false, 'error' => 'hub_already_owned'];
            }

            GameLog::info('HubAcquisitionService', 'Player bought used/market hub', [
                'player_id' => $playerId,
                'hub_id'    => $hubId,
                'cost'      => $cost,
            ]);

            return ['success' => true, 'cost' => $cost];
        } catch (Throwable $e) {
            $this->refundCash($playerId, $cost, tPlain('bank.tx_hub_refund', ['id' => $hubId]), 'hub', $hubId);
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

        // P2a gate: hub permit required if hub_permit_enabled=1 in the region.
        if (!$this->hasHubPermitOrNotRequired($playerId, (int)$hub['region_id'])) {
            return ['success' => false, 'error' => 'no_hub_permit', 'region_id' => (int)$hub['region_id']];
        }

 // Deposit = 3 ticks of full lease fee (non-refundable)
        $leaseFee = (float)($hub['lease_fee_per_tick'] ?? 0.0);
 // Floor: zapobiegamy depozycie 0 zl gdy lease_fee w DB jest zerowe.
 // Floor: prevents 0-PLN deposit when lease_fee_per_tick was seeded from a zeroed config.
        static $leaseFloors = ['small' => 200.0, 'medium' => 600.0, 'large' => 1600.0];
        if ($leaseFee <= 0.0) {
            $leaseFee = $leaseFloors[$hub['hub_type'] ?? 'small'] ?? 200.0;
        }
        $deposit  = round($leaseFee * 3.0, 2);

        if ($deposit > 0.0) {
            $cashCheck = $this->checkAndDeductCash(
                $playerId,
                $deposit,
                tPlain('bank.tx_hub_rent_deposit', ['id' => $hubId]),
                'hub',
                $hubId
            );
            if (!$cashCheck['ok']) {
                return ['success' => false, 'error' => 'insufficient_funds'];
            }
        }

        try {
            $now  = date('Y-m-d H:i:s');
            // Zapisz pod logowana stawke czynszu do wiersza huba, inaczej hub z zerowym
            // lease_fee_per_tick bylby wynajmowany wiecznie za darmo (depozyt raz, potem 0/tick).
            // Persist the floored lease fee to the hub row; otherwise a hub with zero
            // lease_fee_per_tick would be rented free forever (deposit once, then 0/tick).
            $stmt = $this->db->prepare(
                "UPDATE logistics_hubs
                    SET tenant_player_id = ?, lease_fee_per_tick = ?, acquired_at = ?, updated_at = ?
                  WHERE id = ? AND player_id = 0 AND tenant_player_id = 0"
            );
            $stmt->execute([$playerId, $leaseFee, $now, $now, $hubId]);

            if ($stmt->rowCount() === 0) {
                if ($deposit > 0.0) {
                    $this->refundCash($playerId, $deposit, tPlain('bank.tx_hub_refund', ['id' => $hubId]), 'hub', $hubId);
                }
                // rowCount=0 moze znaczyc: hub kupiony (player_id != 0) lub juz wynajety — zwracamy ogolny blad.
                // rowCount=0 can mean hub was purchased (player_id != 0) or rented — return generic unavailable.
                return ['success' => false, 'error' => 'hub_unavailable'];
            }

            GameLog::info('HubAcquisitionService', 'Player rented hub', [
                'player_id' => $playerId,
                'hub_id'    => $hubId,
                'deposit'   => $deposit,
                'lease_fee' => $leaseFee,
            ]);

            return ['success' => true, 'deposit' => $deposit];
        } catch (Throwable $e) {
            if ($deposit > 0.0) {
                $this->refundCash($playerId, $deposit, tPlain('bank.tx_hub_refund', ['id' => $hubId]), 'hub', $hubId);
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
        $cashCheck = $this->checkAndDeductCash(
            $playerId,
            $cost,
            tPlain('bank.tx_hub_upgrade', ['id' => $hubId]),
            'hub',
            $hubId
        );
        if (!$cashCheck['ok']) {
            return ['success' => false, 'error' => 'insufficient_funds', 'cost' => $cost];
        }

        try {
            $result = $this->hubSvc->upgradeHub($hubId, $playerId);
            if (!$result['success']) {
                $this->refundCash($playerId, $cost, tPlain('bank.tx_hub_refund', ['id' => $hubId]), 'hub', $hubId);
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
            $this->refundCash($playerId, $cost, tPlain('bank.tx_hub_refund', ['id' => $hubId]), 'hub', $hubId);
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
    private function checkAndDeductCash(
        int $playerId,
        float $amount,
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): array
    {
        if ($amount <= 0.0) {
            return ['ok' => true];
        }
        $result = (new PlayerPaymentService($this->db))->charge(
            $playerId,
            $amount,
            FinancialTransactionService::TYPE_HUB_PURCHASE,
            $description ?? tPlain('bank.tx_hub_purchase_generic'),
            $referenceType,
            $referenceId
        );
        return ['ok' => (bool)$result['success']];
    }

    private function refundCash(
        int $playerId,
        float $amount,
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): void
    {
        if ($amount <= 0.0) {
            return;
        }
        try {
            (new PlayerPaymentService($this->db))->refund(
                $playerId,
                $amount,
                FinancialTransactionService::TYPE_HUB_PURCHASE,
                $description ?? tPlain('bank.tx_hub_refund_generic'),
                $referenceType,
                $referenceId
            );
        } catch (Throwable $e) {
            GameLog::error('HubAcquisitionService', 'Cash refund failed', $e, [
                'player_id' => $playerId,
                'amount'    => $amount,
            ]);
        }
    }

    /**
     * P2a gate: checks whether the player can acquire a hub in this region.
     * Returns true when permit enforcement is off or the player has a granted permit.
     * Database errors are fail-closed.
     */
    private function hasHubPermitOrNotRequired(int $playerId, int $regionId): bool
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
            GameLog::warn('HubAcquisitionService', 'Hub permit gate failed - blocking (fail-closed)', [
                'player_id' => $playerId, 'region_id' => $regionId, 'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
