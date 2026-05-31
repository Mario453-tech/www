<?php
declare(strict_types=1);

class FinancePolicyService
{
    private PDO $db;

 /** @var array<string, string> */
    private const BUDGET_LEVELS = [
        'low' => 'low',
        'standard' => 'standard',
        'high' => 'high',
    ];

 /** @var array<string, string> */
    private const RESERVE_LEVELS = [
        'low' => 'low',
        'standard' => 'standard',
        'high' => 'high',
    ];

 /** @var array<string, string> */
    private const SAVINGS_MODES = [
        'off' => 'off',
        'moderate' => 'moderate',
        'aggressive' => 'aggressive',
    ];

    private const SAVINGS_PLAN_COOLDOWN_HOURS = 6;

 /**
 * Domylne wartoci mnonikw planu oszczdnoci (uywane jako fallback gdy DB nie ma klucza).
 * Default savings plan multipliers (fallback when DB key is missing).
 * @var array<string, float>
 */
    private const SAVINGS_MULT_DEFAULTS = [
        'sp_tech_wear_mod'        => 1.06,
        'sp_tech_wear_agg'        => 1.14,
        'sp_tech_degr_mod'        => 1.06,
        'sp_tech_degr_agg'        => 1.12,
        'sp_log_transport_mod'    => 0.96,
        'sp_log_transport_agg'    => 0.90,
        'sp_log_hub_mod'          => 0.96,
        'sp_log_hub_agg'          => 0.90,
        'sp_log_loss_mod'         => 1.08,
        'sp_log_loss_agg'         => 1.18,
        'sp_log_incident_mod'     => 1.05,
        'sp_log_incident_agg'     => 1.12,
        'sp_hr_duration_mod'      => 1.08,
        'sp_hr_duration_agg'      => 1.18,
        'sp_hr_quality_mod'       => 0.94,
        'sp_hr_quality_agg'       => 0.86,
        'sp_safety_incident_agg'  => 1.04,
        'sp_safety_disaster_agg'  => 1.02,
    ];

 /** @var array<string, float>|null cache loaded once per request */
    private ?array $savingsMults = null;

 /**
 * aduje mnoniki planu oszczdnoci z well_config (klucze sp_*).
 * Loads savings plan multipliers from well_config (sp_* keys).
 * Niezdefiniowane klucze uzupeniane s wartociami z SAVINGS_MULT_DEFAULTS.
 * Undefined keys fall back to SAVINGS_MULT_DEFAULTS.
 * @return array<string, float>
 */
    private function loadSavingsMultipliers(): array
    {
        if ($this->savingsMults !== null) {
            return $this->savingsMults;
        }
        $result = self::SAVINGS_MULT_DEFAULTS;
        try {
            $stmt = $this->db->prepare(
                "SELECT `key`, value FROM well_config WHERE `key` LIKE 'sp_%'"
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($result as $k => &$v) {
                if (isset($rows[$k]) && is_numeric($rows[$k])) {
                    $v = round((float)$rows[$k], 4);
                }
            }
            unset($v);
        } catch (Throwable $e) {
 // well_config not available using default values
        }
        return $this->savingsMults = $result;
    }

 /**
 * Odczytuje cooldown z well_config (klucz savings_plan_cooldown_hours), fallback do staej.
 * Reads cooldown from well_config (key savings_plan_cooldown_hours), falls back to the constant.
 */
    private function getCooldownHours(): int
    {
        try {
            $val = $this->db->prepare(
                "SELECT value FROM well_config WHERE `key` = 'savings_plan_cooldown_hours' LIMIT 1"
            );
            $val->execute();
            $v = $val->fetchColumn();
            if ($v !== false && is_numeric($v)) {
                return max(1, min(168, (int)$v));
            }
        } catch (Throwable $e) {
 // table is optional fall back to the default constant
        }
        return self::SAVINGS_PLAN_COOLDOWN_HOURS;
    }

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->ensureSchema();
    }

    /** @var array<int,bool> strażnik per połączenie (raz na proces, ale ponownie dla nowego PDO w testach) */
    private static array $schemaEnsured = [];

    private function ensureSchema(): void
    {
        $schemaConnId = spl_object_id($this->db);
        if (isset(self::$schemaEnsured[$schemaConnId])) {
            return;
        }
        self::$schemaEnsured[$schemaConnId] = true;

        try {
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS player_finance_settings (
                    player_id INT NOT NULL PRIMARY KEY,
                    technical_budget VARCHAR(16) NOT NULL DEFAULT 'standard',
                    logistics_budget VARCHAR(16) NOT NULL DEFAULT 'standard',
                    hr_budget VARCHAR(16) NOT NULL DEFAULT 'standard',
                    safety_budget VARCHAR(16) NOT NULL DEFAULT 'standard',
                    reserve_policy VARCHAR(16) NOT NULL DEFAULT 'standard',
                    savings_plan_mode VARCHAR(16) NOT NULL DEFAULT 'off',
                    savings_plan_changed_at DATETIME NULL DEFAULT NULL,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT fk_pfs_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            Database::addColumnIfMissing('player_finance_settings', 'savings_plan_mode', "VARCHAR(16) NOT NULL DEFAULT 'off' AFTER reserve_policy");
            Database::addColumnIfMissing('player_finance_settings', 'savings_plan_changed_at', "DATETIME NULL DEFAULT NULL AFTER savings_plan_mode");

            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS player_finance_decisions (
                    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    player_id INT NOT NULL,
                    decision_key VARCHAR(64) NOT NULL,
                    old_value VARCHAR(64) NOT NULL,
                    new_value VARCHAR(64) NOT NULL,
                    source VARCHAR(32) NOT NULL DEFAULT 'player',
                    effect_json JSON NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_pfd_player_created (player_id, created_at),
                    CONSTRAINT fk_pfd_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            Database::addColumnIfMissing('player_finance_decisions', 'source', "VARCHAR(32) NOT NULL DEFAULT 'player' AFTER new_value");
            Database::addColumnIfMissing('player_finance_decisions', 'effect_json', "JSON NULL AFTER source");
        } catch (Throwable $e) {
            GameLog::error('FinancePolicyService', 'ensureSchema FAILED', $e);
        }
    }

 /**
 * @return array<string, string>
 */
    public function getSettings(int $playerId): array
    {
        $defaults = $this->getDefaultSettings();

        try {
            $stmt = $this->db->prepare(
                "SELECT technical_budget, logistics_budget, hr_budget, safety_budget, reserve_policy, savings_plan_mode, savings_plan_changed_at
                 FROM player_finance_settings
                 WHERE player_id = ?
                 LIMIT 1"
            );
            $stmt->execute([$playerId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return $defaults;
            }

            return [
                'technical_budget' => $this->normalizeBudgetLevel((string)($row['technical_budget'] ?? 'standard')),
                'logistics_budget' => $this->normalizeBudgetLevel((string)($row['logistics_budget'] ?? 'standard')),
                'hr_budget' => $this->normalizeBudgetLevel((string)($row['hr_budget'] ?? 'standard')),
                'safety_budget' => $this->normalizeBudgetLevel((string)($row['safety_budget'] ?? 'standard')),
                'reserve_policy' => $this->normalizeReserveLevel((string)($row['reserve_policy'] ?? 'standard')),
                'savings_plan_mode' => $this->normalizeSavingsMode((string)($row['savings_plan_mode'] ?? 'off')),
                'savings_plan_changed_at' => (string)($row['savings_plan_changed_at'] ?? ''),
            ];
        } catch (Throwable $e) {
            GameLog::error('FinancePolicyService', 'getSettings FAILED', $e, ['player_id' => $playerId]);
            return $defaults;
        }
    }

 /**
 * @param array<string, string> $input
 * @return array<string, string>
 */
    public function saveSettings(int $playerId, array $input): array
    {
        $old = $this->getSettings($playerId);
        $new = [
            'technical_budget' => $this->normalizeBudgetLevel((string)($input['technical_budget'] ?? 'standard')),
            'logistics_budget' => $this->normalizeBudgetLevel((string)($input['logistics_budget'] ?? 'standard')),
            'hr_budget' => $this->normalizeBudgetLevel((string)($input['hr_budget'] ?? 'standard')),
            'safety_budget' => $this->normalizeBudgetLevel((string)($input['safety_budget'] ?? 'standard')),
            'reserve_policy' => $this->normalizeReserveLevel((string)($input['reserve_policy'] ?? 'standard')),
            'savings_plan_mode' => $old['savings_plan_mode'] ?? 'off',
            'savings_plan_changed_at' => $old['savings_plan_changed_at'] ?? '',
        ];

        try {
            $this->db->prepare(
                "INSERT INTO player_finance_settings
                    (player_id, technical_budget, logistics_budget, hr_budget, safety_budget, reserve_policy, savings_plan_mode, savings_plan_changed_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    technical_budget = VALUES(technical_budget),
                    logistics_budget = VALUES(logistics_budget),
                    hr_budget = VALUES(hr_budget),
                    safety_budget = VALUES(safety_budget),
                    reserve_policy = VALUES(reserve_policy),
                    savings_plan_mode = VALUES(savings_plan_mode),
                    savings_plan_changed_at = VALUES(savings_plan_changed_at)"
            )->execute([
                $playerId,
                $new['technical_budget'],
                $new['logistics_budget'],
                $new['hr_budget'],
                $new['safety_budget'],
                $new['reserve_policy'],
                $new['savings_plan_mode'],
                $new['savings_plan_changed_at'] !== '' ? $new['savings_plan_changed_at'] : null,
            ]);

            foreach ($new as $key => $value) {
                if ($key === 'savings_plan_changed_at') {
                    continue;
                }
                if (($old[$key] ?? null) === $value) {
                    continue;
                }

                $this->db->prepare(
                    "INSERT INTO player_finance_decisions (player_id, decision_key, old_value, new_value, source)
                     VALUES (?, ?, ?, ?, 'player')"
                )->execute([
                    $playerId,
                    $key,
                    (string)($old[$key] ?? ''),
                    $value,
                ]);
            }

            GameLog::info('FinancePolicyService', 'saveSettings OK', [
                'player_id' => $playerId,
                'settings' => $new,
            ]);
        } catch (Throwable $e) {
            GameLog::error('FinancePolicyService', 'saveSettings FAILED', $e, [
                'player_id' => $playerId,
                'settings' => $new,
            ]);
        }

        return $new;
    }

 /**
 * @param array<string, string> $input
 * @return array{ok:bool,error:string,settings:array<string,string>}
 */
    public function savePolicySettings(int $playerId, array $input): array
    {
        $old = $this->getSettings($playerId);
        $newSavingsMode = $this->normalizeSavingsMode((string)($input['savings_plan_mode'] ?? ($old['savings_plan_mode'] ?? 'off')));
        $newReservePolicy = $this->normalizeReserveLevel((string)($input['reserve_policy'] ?? ($old['reserve_policy'] ?? 'standard')));

        $status = $this->getSavingsPlanStatus($playerId, $old);
        if ($newSavingsMode !== ($old['savings_plan_mode'] ?? 'off') && !$status['can_change']) {
            return [
                'ok' => false,
                'error' => 'cooldown',
                'settings' => $old,
            ];
        }

        $new = $old;
        $new['reserve_policy'] = $newReservePolicy;
        $new['savings_plan_mode'] = $newSavingsMode;
        if ($newSavingsMode !== ($old['savings_plan_mode'] ?? 'off')) {
            $new['savings_plan_changed_at'] = date('Y-m-d H:i:s');
        }

        try {
            $this->db->prepare(
                "INSERT INTO player_finance_settings
                    (player_id, technical_budget, logistics_budget, hr_budget, safety_budget, reserve_policy, savings_plan_mode, savings_plan_changed_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    technical_budget = VALUES(technical_budget),
                    logistics_budget = VALUES(logistics_budget),
                    hr_budget = VALUES(hr_budget),
                    safety_budget = VALUES(safety_budget),
                    reserve_policy = VALUES(reserve_policy),
                    savings_plan_mode = VALUES(savings_plan_mode),
                    savings_plan_changed_at = VALUES(savings_plan_changed_at)"
            )->execute([
                $playerId,
                $new['technical_budget'] ?? 'standard',
                $new['logistics_budget'] ?? 'standard',
                $new['hr_budget'] ?? 'standard',
                $new['safety_budget'] ?? 'standard',
                $new['reserve_policy'] ?? 'standard',
                $new['savings_plan_mode'] ?? 'off',
                $new['savings_plan_changed_at'] !== '' ? $new['savings_plan_changed_at'] : null,
            ]);

            foreach (['reserve_policy', 'savings_plan_mode'] as $key) {
                if (($old[$key] ?? null) === ($new[$key] ?? null)) {
                    continue;
                }
                $this->db->prepare(
                    "INSERT INTO player_finance_decisions (player_id, decision_key, old_value, new_value, source)
                     VALUES (?, ?, ?, ?, 'player')"
                )->execute([
                    $playerId,
                    $key,
                    (string)($old[$key] ?? ''),
                    (string)($new[$key] ?? ''),
                ]);
            }

            GameLog::info('FinancePolicyService', 'savePolicySettings OK', [
                'player_id' => $playerId,
                'policy' => [
                    'reserve_policy' => $newReservePolicy,
                    'savings_plan_mode' => $newSavingsMode,
                ],
            ]);
        } catch (Throwable $e) {
            GameLog::error('FinancePolicyService', 'savePolicySettings FAILED', $e, [
                'player_id' => $playerId,
            ]);
            return [
                'ok' => false,
                'error' => 'save_failed',
                'settings' => $old,
            ];
        }

        return [
            'ok' => true,
            'error' => '',
            'settings' => $new,
        ];
    }

 /**
 * @return list<array<string, mixed>>
 */
    public function getDecisionHistory(int $playerId, int $limit = 20): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT decision_key, old_value, new_value, source, created_at
                 FROM player_finance_decisions
                 WHERE player_id = ?
                 ORDER BY created_at DESC, id DESC
                 LIMIT ?"
            );
            $stmt->bindValue(1, $playerId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            GameLog::error('FinancePolicyService', 'getDecisionHistory FAILED', $e, [
                'player_id' => $playerId,
                'limit' => $limit,
            ]);
            return [];
        }
    }

 /**
 * @return array<string, float|string>
 */
    public function getTechnicalModifiers(int $playerId): array
    {
        $settings = $this->getSettings($playerId);
        $level = $settings['technical_budget'];
        $mods = match ($level) {
            'low' => ['level' => 'low', 'opex_mult' => 1.00, 'wear_mult' => 1.10, 'degradation_mult' => 1.08],
            'high' => ['level' => 'high', 'opex_mult' => 1.00, 'wear_mult' => 0.92, 'degradation_mult' => 0.90],
            default => ['level' => 'standard', 'opex_mult' => 1.00, 'wear_mult' => 1.00, 'degradation_mult' => 1.00],
        };

        return $this->applySavingsToTechnical($mods, $settings['savings_plan_mode'] ?? 'off');
    }

 /**
 * @return array<string, float|string>
 */
    public function getLogisticsModifiers(int $playerId): array
    {
        $settings = $this->getSettings($playerId);
        $level = $settings['logistics_budget'];
        $mods = match ($level) {
            'low' => ['level' => 'low', 'transport_cost_mult' => 0.94, 'hub_cost_mult' => 0.94, 'loss_mult' => 1.12, 'incident_mult' => 1.08],
            'high' => ['level' => 'high', 'transport_cost_mult' => 1.08, 'hub_cost_mult' => 1.08, 'loss_mult' => 0.90, 'incident_mult' => 0.92],
            default => ['level' => 'standard', 'transport_cost_mult' => 1.00, 'hub_cost_mult' => 1.00, 'loss_mult' => 1.00, 'incident_mult' => 1.00],
        };

        return $this->applySavingsToLogistics($mods, $settings['savings_plan_mode'] ?? 'off');
    }

 /**
 * @return array<string, float|string>
 */
    public function getHRModifiers(int $playerId): array
    {
        $settings = $this->getSettings($playerId);
        $level = $settings['hr_budget'];
        $mods = match ($level) {
            'low' => ['level' => 'low', 'duration_mult' => 1.18, 'quality_mult' => 0.92],
            'high' => ['level' => 'high', 'duration_mult' => 0.88, 'quality_mult' => 1.12],
            default => ['level' => 'standard', 'duration_mult' => 1.00, 'quality_mult' => 1.00],
        };

        return $this->applySavingsToHr($mods, $settings['savings_plan_mode'] ?? 'off');
    }

 /**
 * @return array<string, float|string>
 */
    public function getSafetyModifiers(int $playerId): array
    {
        $settings = $this->getSettings($playerId);
        $level = $settings['safety_budget'];
        $mods = match ($level) {
            'low' => ['level' => 'low', 'incident_mult' => 1.18, 'disaster_mult' => 1.15],
            'high' => ['level' => 'high', 'incident_mult' => 0.86, 'disaster_mult' => 0.84],
            default => ['level' => 'standard', 'incident_mult' => 1.00, 'disaster_mult' => 1.00],
        };

        return $this->applySavingsToSafety($mods, $settings['savings_plan_mode'] ?? 'off');
    }

    public function getReserveTargetHours(int $playerId): float
    {
        $level = $this->getSettings($playerId)['reserve_policy'];
        return match ($level) {
            'low' => 6.0,
            'high' => 24.0,
            default => 12.0,
        };
    }

 /**
 * @param array<string, string> $settings
 * @return array<string, mixed>
 */
    public function getSavingsPlanStatus(int $playerId, ?array $settings = null): array
    {
        $settings ??= $this->getSettings($playerId);
        $changedAt = (string)($settings['savings_plan_changed_at'] ?? '');
        $cooldownUntilTs = 0;
        if ($changedAt !== '') {
            $ts = strtotime($changedAt);
            if ($ts !== false) {
                $cooldownHours   = $this->getCooldownHours();
                $cooldownUntilTs = strtotime('+' . $cooldownHours . ' hours', $ts) ?: 0;
            }
        }
        $nowTs = time();
        $remaining = max(0, $cooldownUntilTs - $nowTs);

        return [
            'mode' => $this->normalizeSavingsMode((string)($settings['savings_plan_mode'] ?? 'off')),
            'changed_at' => $changedAt,
            'cooldown_hours' => $this->getCooldownHours(),
            'can_change' => $remaining === 0,
            'cooldown_remaining_seconds' => $remaining,
            'cooldown_until' => $cooldownUntilTs > 0 ? date('Y-m-d H:i:s', $cooldownUntilTs) : '',
        ];
    }

 /**
 * @return array<string, mixed>
 */
    public function getPolicySnapshot(int $playerId, float $hourlyCost, float $cash): array
    {
        $settings = $this->getSettings($playerId);
        $savings = $this->getSavingsPlanStatus($playerId, $settings);
        $reserveHours = $this->getReserveTargetHours($playerId);
        $reserveTargetValue = max(0.0, $hourlyCost * $reserveHours);
        $coverageHours = $hourlyCost > 0.0 ? ($cash / $hourlyCost) : 999.0;

        $reserveState = 'good';
        if ($coverageHours < ($reserveHours * 0.5)) {
            $reserveState = 'critical';
        } elseif ($coverageHours < $reserveHours) {
            $reserveState = 'warning';
        } elseif ($coverageHours < ($reserveHours * 1.25)) {
            $reserveState = 'caution';
        }

        return [
            'settings' => $settings,
            'savings' => $savings,
            'reserve_target_hours' => $reserveHours,
            'reserve_target_value' => $reserveTargetValue,
            'coverage_hours' => $coverageHours,
            'reserve_state' => $reserveState,
        ];
    }

 /**
 * @return array<string, string>
 */
    public static function getBudgetLevelOptions(): array
    {
        return self::BUDGET_LEVELS;
    }

 /**
 * @return array<string, string>
 */
    public static function getReserveLevelOptions(): array
    {
        return self::RESERVE_LEVELS;
    }

 /**
 * @return array<string, string>
 */
    public static function getSavingsModeOptions(): array
    {
        return self::SAVINGS_MODES;
    }

 /**
 * @return array<string, string>
 */
    private function getDefaultSettings(): array
    {
        return [
            'technical_budget' => 'standard',
            'logistics_budget' => 'standard',
            'hr_budget' => 'standard',
            'safety_budget' => 'standard',
            'reserve_policy' => 'standard',
            'savings_plan_mode' => 'off',
            'savings_plan_changed_at' => '',
        ];
    }

    private function normalizeBudgetLevel(string $level): string
    {
        return self::BUDGET_LEVELS[$level] ?? 'standard';
    }

    private function normalizeReserveLevel(string $level): string
    {
        return self::RESERVE_LEVELS[$level] ?? 'standard';
    }

    private function normalizeSavingsMode(string $mode): string
    {
        return self::SAVINGS_MODES[$mode] ?? 'off';
    }

 /**
 * @param array<string, float|string> $mods
 * @return array<string, float|string>
 */
    private function applySavingsToTechnical(array $mods, string $mode): array
    {
        $m = $this->loadSavingsMultipliers();
        return match ($mode) {
            'moderate' => array_merge($mods, [
                'wear_mult'        => (float)$mods['wear_mult']        * $m['sp_tech_wear_mod'],
                'degradation_mult' => (float)$mods['degradation_mult'] * $m['sp_tech_degr_mod'],
            ]),
            'aggressive' => array_merge($mods, [
                'wear_mult'        => (float)$mods['wear_mult']        * $m['sp_tech_wear_agg'],
                'degradation_mult' => (float)$mods['degradation_mult'] * $m['sp_tech_degr_agg'],
            ]),
            default => $mods,
        };
    }

 /**
 * @param array<string, float|string> $mods
 * @return array<string, float|string>
 */
    private function applySavingsToLogistics(array $mods, string $mode): array
    {
        $m = $this->loadSavingsMultipliers();
        return match ($mode) {
            'moderate' => array_merge($mods, [
                'transport_cost_mult' => (float)$mods['transport_cost_mult'] * $m['sp_log_transport_mod'],
                'hub_cost_mult'       => (float)$mods['hub_cost_mult']       * $m['sp_log_hub_mod'],
                'loss_mult'           => (float)$mods['loss_mult']           * $m['sp_log_loss_mod'],
                'incident_mult'       => (float)$mods['incident_mult']       * $m['sp_log_incident_mod'],
            ]),
            'aggressive' => array_merge($mods, [
                'transport_cost_mult' => (float)$mods['transport_cost_mult'] * $m['sp_log_transport_agg'],
                'hub_cost_mult'       => (float)$mods['hub_cost_mult']       * $m['sp_log_hub_agg'],
                'loss_mult'           => (float)$mods['loss_mult']           * $m['sp_log_loss_agg'],
                'incident_mult'       => (float)$mods['incident_mult']       * $m['sp_log_incident_agg'],
            ]),
            default => $mods,
        };
    }

 /**
 * @param array<string, float|string> $mods
 * @return array<string, float|string>
 */
    private function applySavingsToHr(array $mods, string $mode): array
    {
        $m = $this->loadSavingsMultipliers();
        return match ($mode) {
            'moderate' => array_merge($mods, [
                'duration_mult' => (float)$mods['duration_mult'] * $m['sp_hr_duration_mod'],
                'quality_mult'  => (float)$mods['quality_mult']  * $m['sp_hr_quality_mod'],
            ]),
            'aggressive' => array_merge($mods, [
                'duration_mult' => (float)$mods['duration_mult'] * $m['sp_hr_duration_agg'],
                'quality_mult'  => (float)$mods['quality_mult']  * $m['sp_hr_quality_agg'],
            ]),
            default => $mods,
        };
    }

 /**
 * @param array<string, float|string> $mods
 * @return array<string, float|string>
 */
    private function applySavingsToSafety(array $mods, string $mode): array
    {
        $m = $this->loadSavingsMultipliers();
        return match ($mode) {
            'aggressive' => array_merge($mods, [
                'incident_mult' => (float)$mods['incident_mult'] * $m['sp_safety_incident_agg'],
                'disaster_mult' => (float)$mods['disaster_mult'] * $m['sp_safety_disaster_agg'],
            ]),
            default => $mods,
        };
    }
}
