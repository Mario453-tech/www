<?php

/**
 * HubConfigTrait - loads logistics_hub_config into typed arrays.
 * Used by HubService.
 */
trait HubConfigTrait
{
 /** @var array<string, array<string, string>> [group][key] => value */
    private array $hubConfig = [];

    private function ensureHubSchema(): void
    {
        try {
            Database::addColumnIfMissing(
                'logistics_hubs',
                'acquisition_type',
                "VARCHAR(16) NOT NULL DEFAULT 'new' AFTER hub_type"
            );
            Database::addColumnIfMissing(
                'logistics_hubs',
                'lease_fee_per_tick',
                "DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER opex_per_tick"
            );
            Database::addColumnIfMissing(
                'logistics_hubs',
                'initial_condition_pct',
                "DECIMAL(5,2) NOT NULL DEFAULT 100.00 AFTER condition_pct"
            );
            Database::addColumnIfMissing(
                'logistics_hubs',
                'last_maintenance_at',
                "DATETIME NULL DEFAULT NULL AFTER initial_condition_pct"
            );
            Database::addColumnIfMissing(
                'logistics_hub_assignments',
                'access_fee_paid',
                "DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER status"
            );

 // Etap 9: hub ownership columns.
 // player_id = 0 means hub is on the market (system-owned / available to buy or rent).
 // player_id > 0 means the hub belongs to that player exclusively.
            Database::addColumnIfMissing(
                'logistics_hubs',
                'acquisition_price',
                "DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER build_cost"
            );
            Database::addColumnIfMissing(
                'logistics_hubs',
                'acquired_at',
                "DATETIME NULL DEFAULT NULL AFTER acquisition_price"
            );
 // tenant_player_id: who is currently renting a market hub (player_id = 0).
 // 0 = no active tenant.
            Database::addColumnIfMissing(
                'logistics_hubs',
                'tenant_player_id',
                "BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER player_id"
            );
        } catch (Throwable $e) {
            GameLog::error('HubService', 'ensureHubSchema failed', $e);
        }

 // Seed acquisition type mix if all hubs still have default 'new' + lease 0.
 // This runs once after the column is first added.
 // Distribution: ~55% new, ~30% used, ~15% rental (by hub_id modulo pattern).
        $this->ensureHubAcquisitionMix();
    }

 /**
 * One-time migration: distributes hub acquisition types across system hubs.
 * Only runs if all hubs are still 'new' with lease_fee = 0 (fresh install or first boot).
 *
 * Lease fee per tick is charged per SLOT occupied by that player's wells.
 * Values by hub type:
 * small - rental: 120/slot/tick
 * medium - rental: 220/slot/tick
 * large - rental: 380/slot/tick
 */
    private function ensureHubAcquisitionMix(): void
    {
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return;
        }
        try {
            $row = $this->db->query(
                "SELECT COUNT(*) AS total,
                        SUM(acquisition_type != 'new' OR lease_fee_per_tick > 0) AS already_mixed
                   FROM logistics_hubs WHERE player_id = 0"
            )->fetch(PDO::FETCH_ASSOC);

            if ((int)($row['already_mixed'] ?? 0) > 0) {
                return; // Mix already applied
            }
            if ((int)($row['total'] ?? 0) === 0) {
                return; // No system hubs yet
            }

 // Lease fees per slot per tick by hub_type
            $leaseFees = ['small' => 120.00, 'medium' => 220.00, 'large' => 380.00];

 // Used hubs: set degraded starting condition (42-72%)
 // Pattern by id: id%10 in {0,1,2} => rental (30%), {3,4,5,6} => used (40%), else => new (30%)
            $this->db->exec("
                UPDATE logistics_hubs
                   SET acquisition_type = CASE
                         WHEN (id % 10) IN (0,1,2) THEN 'rental'
                         WHEN (id % 10) IN (3,4,5,6) THEN 'used'
                         ELSE 'new'
                       END,
                       condition_pct = CASE
                         WHEN (id % 10) IN (3,4,5,6) THEN
                           ROUND(42 + (id % 31), 2)  -- 42..72% for used hubs
                         ELSE condition_pct
                       END,
                       lease_fee_per_tick = CASE
                         WHEN (id % 10) IN (0,1,2) AND hub_type = 'small'  THEN 120.00
                         WHEN (id % 10) IN (0,1,2) AND hub_type = 'medium' THEN 220.00
                         WHEN (id % 10) IN (0,1,2) AND hub_type = 'large'  THEN 380.00
                         ELSE 0.00
                       END
                 WHERE player_id = 0
            ");

            GameLog::info('HubService', 'Hub acquisition mix applied to system hubs');
        } catch (Throwable $e) {
            GameLog::error('HubService', 'ensureHubAcquisitionMix failed', $e);
        }
    }

    private function loadHubConfig(): void
    {
        try {
            $rows = $this->db->query(
                "SELECT config_group, config_key, config_scope, config_value
                   FROM logistics_hub_config
                  WHERE config_scope = 'global'
                  ORDER BY config_group, config_key"
            )->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $this->hubConfig[$row['config_group']][$row['config_key']] = $row['config_value'];
            }
        } catch (Throwable $e) {
            GameLog::error('HubService', 'loadHubConfig failed', $e);
        }
    }

    private function ensureAcquisitionConfigDefaults(): void
    {
        $defaults = [
            'new' => [
                'build_cost_mult'    => '1.00',
                'opex_mult'          => '1.00',
                'start_condition_min'=> '96',
                'start_condition_max'=> '100',
                'wear_mult'          => '1.00',
                'risk_mult'          => '1.00',
                'lease_fee_per_tick' => '0',
            ],
            'used' => [
                'build_cost_mult'    => '0.62',
                'opex_mult'          => '1.15',
                'start_condition_min'=> '42',
                'start_condition_max'=> '72',
                'wear_mult'          => '1.45',
                'risk_mult'          => '1.55',
                'lease_fee_per_tick' => '0',
            ],
            'rental' => [
                'build_cost_mult'    => '0.18',
                'opex_mult'          => '0.95',
                'start_condition_min'=> '58',
                'start_condition_max'=> '82',
                'wear_mult'          => '1.20',
                'risk_mult'          => '1.15',
                'lease_fee_per_tick' => '320',
            ],
        ];

        foreach ($defaults as $type => $items) {
            foreach ($items as $key => $value) {
                $cfgKey = $type . '.' . $key;
                if (!isset($this->hubConfig['acquisition'][$cfgKey])) {
                    $this->saveConfigValue('acquisition', $cfgKey, 'global', $value);
                }
            }
        }
    }

 /** Returns a config value as string, or $default if not set. */
    public function cfg(string $group, string $key, string $default = ''): string
    {
        return $this->hubConfig[$group][$key] ?? $default;
    }

 /** Returns a config value as float. */
    public function cfgFloat(string $group, string $key, float $default = 0.0): float
    {
        return isset($this->hubConfig[$group][$key])
            ? (float)$this->hubConfig[$group][$key]
            : $default;
    }

 /** Returns a config value as int. */
    public function cfgInt(string $group, string $key, int $default = 0): int
    {
        return isset($this->hubConfig[$group][$key])
            ? (int)$this->hubConfig[$group][$key]
            : $default;
    }

 /**
 * Returns all config values for a hub type.
 * @return array<string, mixed>
 */
    public function getHubTypeConfig(string $hubType): array
    {
        $prefix = $hubType . '.';
        $result = [];
        foreach ($this->hubConfig['hub_type'] ?? [] as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $shortKey        = substr($key, strlen($prefix));
                $result[$shortKey] = $value;
            }
        }
        return $result;
    }

 /**
 * Returns all multipliers for a work mode.
 * @return array<string, float>
 */
    public function getWorkModeMultipliers(string $workMode): array
    {
        $prefix = $workMode . '.';
        $result = [];
        foreach ($this->hubConfig['work_mode'] ?? [] as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $shortKey        = substr($key, strlen($prefix));
                $result[$shortKey] = (float)$value;
            }
        }
        return $result;
    }

 /**
 * Returns fallback config for wells without a hub.
 * @return array<string, float>
 */
    public function getFallbackConfig(): array
    {
        $result = [];
        foreach ($this->hubConfig['fallback'] ?? [] as $key => $value) {
            $result[$key] = (float)$value;
        }
        return $result;
    }

 /**
 * Returns all config values for an acquisition type.
 * @return array{
 * build_cost_mult: float,
 * opex_mult: float,
 * start_condition_min: float,
 * start_condition_max: float,
 * wear_mult: float,
 * risk_mult: float,
 * lease_fee_per_tick: float
 * }
 */
    public function getAcquisitionDefaults(string $acquisitionType): array
    {
        $type = in_array($acquisitionType, ['new', 'used', 'rental'], true) ? $acquisitionType : 'new';
        $prefix = $type . '.';

        return [
            'build_cost_mult'     => $this->cfgFloat('acquisition', $prefix . 'build_cost_mult', 1.0),
            'opex_mult'           => $this->cfgFloat('acquisition', $prefix . 'opex_mult', 1.0),
            'start_condition_min' => $this->cfgFloat('acquisition', $prefix . 'start_condition_min', 96.0),
            'start_condition_max' => $this->cfgFloat('acquisition', $prefix . 'start_condition_max', 100.0),
            'wear_mult'           => $this->cfgFloat('acquisition', $prefix . 'wear_mult', 1.0),
            'risk_mult'           => $this->cfgFloat('acquisition', $prefix . 'risk_mult', 1.0),
            'lease_fee_per_tick'  => $this->cfgFloat('acquisition', $prefix . 'lease_fee_per_tick', 0.0),
        ];
    }

 /**
 * Returns all nominal values for a hub type accounting for level upgrades.
 * Level multiplier: each level adds +20% throughput and +15% buffer.
 * @return array<string, mixed>
 */
    public function getHubTypeDefaults(string $hubType, int $level = 1): array
    {
        $base  = $this->getHubTypeConfig($hubType);
        $lvlMult = 1.0 + ($level - 1) * 0.20;
        $bufMult = 1.0 + ($level - 1) * 0.15;

        return [
            'slot_limit'       => (int)($base['slot_limit'] ?? 2),
            'nominal_bph'      => round((float)($base['nominal_bph'] ?? 500) * $lvlMult, 2),
            'buffer_bbl'       => round((float)($base['buffer_bbl'] ?? 200) * $bufMult, 2),
            'opex_per_tick'    => (float)($base['opex_per_tick'] ?? 500),
            'build_cost'       => (float)($base['build_cost'] ?? 50000),
            'repair_cost_pct'  => (float)($base['repair_cost_pct'] ?? 0.40),
            'wear_per_tick'    => (float)($base['wear_per_tick'] ?? 0.05),
            'overload_wear_mult' => (float)($base['overload_wear_mult'] ?? 3.0),
            'overload_risk_mult' => (float)($base['overload_risk_mult'] ?? 2.5),
            'upgrade_cost'     => (float)($base['upgrade_cost'] ?? 30000),
            'max_level'        => (int)($base['max_level'] ?? 3),
        ];
    }

    public function saveConfigValue(string $group, string $key, string $scope, string $value): bool
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO logistics_hub_config (config_group, config_key, config_scope, config_value, updated_at)
                 VALUES (?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()"
            );
            $stmt->execute([$group, $key, $scope, $value]);
 // Refresh local cache
            $this->hubConfig[$group][$key] = $value;
            return true;
        } catch (Throwable $e) {
            GameLog::error('HubService', 'saveConfigValue failed', $e);
            return false;
        }
    }
}
