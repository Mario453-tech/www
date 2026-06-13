<?php
declare(strict_types=1);

require_once __DIR__ . '/Sabotage/SabotageSchema.php';

/**
 * SabotageService - universal sabotage engine.
 */
class SabotageService
{
    public const CFG_MODULE_ENABLED = 'sabotage_module_enabled';
    public const TARGET_ROAD_TRANSPORT = 'road_transport';
    public const CONTEXT_ROAD_TRANSPORT = 'road_transport_sabotage';
    private const CFG_LABEL_MODULE_ENABLED = 'Sabotage module enabled';
    private const CFG_CATEGORY = 'sabotage';

    /** @var array<string, list<array<string,mixed>>> */
    private array $optionsCache = [];
    private ?bool $moduleEnabledCache = null;

    public function __construct(private ?PDO $db = null)
    {
        $this->db ??= Database::getInstance()->getConnection();
        SabotageSchema::ensure($this->db);
        $this->ensureConfig();
    }

    public function isModuleEnabled(): bool
    {
        if ($this->moduleEnabledCache !== null) {
            return $this->moduleEnabledCache;
        }

        try {
            $stmt = $this->db->prepare("SELECT `value` FROM well_config WHERE `key` = ? LIMIT 1");
            $stmt->execute([self::CFG_MODULE_ENABLED]);
            $value = $stmt->fetchColumn();
            if ($value === false) {
                return $this->moduleEnabledCache = false;
            }
            return $this->moduleEnabledCache = ((float)$value) > 0.5;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('SabotageService', 'isModuleEnabled FAILED', $e);
            }
            return $this->moduleEnabledCache = false;
        }
    }

    public function setModuleEnabled(bool $enabled): void
    {
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->db->prepare(
                "INSERT INTO well_config (`key`, `value`, label, category)
                 VALUES (?, ?, ?, ?)
                 ON CONFLICT(`key`) DO UPDATE SET
                    `value` = excluded.`value`,
                    label = excluded.label,
                    category = excluded.category"
            );
            $stmt->execute([
                self::CFG_MODULE_ENABLED,
                $enabled ? '1' : '0',
                self::CFG_LABEL_MODULE_ENABLED,
                self::CFG_CATEGORY,
            ]);
        } else {
            $stmt = $this->db->prepare(
                "INSERT INTO well_config (`key`, `value`, label, category)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), label = VALUES(label), category = VALUES(category)"
            );
            $stmt->execute([
                self::CFG_MODULE_ENABLED,
                $enabled ? '1' : '0',
                self::CFG_LABEL_MODULE_ENABLED,
                self::CFG_CATEGORY,
            ]);
        }
        $this->moduleEnabledCache = $enabled;
    }

    public function hasActiveOptions(string $targetType, string $context): bool
    {
        return $this->getAvailableOptions($targetType, $context) !== [];
    }

    /**
     * Returns active options for a target and context.
     *
     * @return list<array<string,mixed>>
     */
    public function getAvailableOptions(string $targetType, string $context): array
    {
        if (!$this->isModuleEnabled()) {
            return [];
        }

        $cacheKey = $targetType . '|' . $context;
        if (isset($this->optionsCache[$cacheKey])) {
            return $this->optionsCache[$cacheKey];
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM sabotage_options
                  WHERE is_active = 1 AND target_type = ? AND context = ?
                  ORDER BY sort_order ASC, id ASC"
            );
            $stmt->execute([$targetType, $context]);
            $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $effects = $this->effectsForMany(array_map(static fn(array $o): int => (int)$o['id'], $options));

            foreach ($options as &$option) {
                $option['effects'] = $effects[(int)$option['id']] ?? [];
            }
            unset($option);

            return $this->optionsCache[$cacheKey] = $options;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('SabotageService', 'getAvailableOptions FAILED', $e);
            }
            return [];
        }
    }

    /**
     * Records a system sabotage attempt and returns the effects to apply.
     *
     * @param array<string,mixed>|null $activeProtection
     * @param array<string,mixed> $meta
     * @return array{attempt_id:int,option:array<string,mixed>|null,status:string,transport_loss_pct:float,delay_minutes:int,protection_applied:bool}
     */
    public function attemptRoadTransportSystem(
        int $targetPlayerId,
        int $wellId,
        float $referenceValue,
        ?array $activeProtection,
        array $meta = []
    ): array {
        $option = $this->pickOption(self::TARGET_ROAD_TRANSPORT, self::CONTEXT_ROAD_TRANSPORT, $referenceValue);
        if ($option === null) {
            return [
                'attempt_id' => 0,
                'option' => null,
                'status' => 'cancelled',
                'transport_loss_pct' => 0.0,
                'delay_minutes' => 0,
                'protection_applied' => $activeProtection !== null,
            ];
        }

        $protectionApplied = $activeProtection !== null;
        $effects = (array)($option['effects'] ?? []);
        $lossPct = $this->effectValue($effects, 'transport_loss_pct', 100.0);
        $delayMinutes = (int)round($this->effectValue($effects, 'delay_minutes', 0.0));
        $chancePct = max(0.0, min(100.0, (float)($option['base_chance_pct'] ?? 0.0)));
        $rollValue = mt_rand(1, 1_000_000) / 10_000.0;
        $status = $rollValue <= $chancePct ? 'success' : 'failed';
        if ($status !== 'success') {
            $lossPct = 0.0;
            $delayMinutes = 0;
        }

        try {
            $attemptId = $this->recordAttempt(
                null,
                $targetPlayerId,
                self::TARGET_ROAD_TRANSPORT,
                $wellId,
                (int)$option['id'],
                self::CONTEXT_ROAD_TRANSPORT,
                $status,
                $chancePct,
                $rollValue,
                0.0,
                'system',
                false,
                $protectionApplied,
                $meta + ['reference_value' => $referenceValue, 'option_code' => (string)$option['code']]
            );

            $messageKey = $status === 'success' ? 'sabotage.log_road_success' : 'sabotage.log_road_failed';
            $this->logEvent(
                $attemptId,
                null,
                $targetPlayerId,
                self::TARGET_ROAD_TRANSPORT,
                $wellId,
                $status === 'success' ? 'sabotage_success' : 'sabotage_failed',
                tPlain($messageKey, ['name' => (string)$option['name']]),
                ['loss_pct' => $lossPct, 'delay_minutes' => $delayMinutes, 'roll_value' => $rollValue, 'chance_pct' => $chancePct]
            );
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('SabotageService', 'attemptRoadTransportSystem log FAILED', $e, [
                    'target_player_id' => $targetPlayerId,
                    'well_id' => $wellId,
                    'option_code' => (string)$option['code'],
                ]);
            }
            $attemptId = 0;
        }

        return [
            'attempt_id' => $attemptId,
            'option' => $option,
            'status' => $status,
            'transport_loss_pct' => max(0.0, min(100.0, $lossPct)),
            'delay_minutes' => max(0, $delayMinutes),
            'protection_applied' => $protectionApplied,
        ];
    }

    private function ensureConfig(): void
    {
        try {
            $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $this->db->prepare(
                    "INSERT OR IGNORE INTO well_config (`key`, `value`, label, category)
                     VALUES (?, ?, ?, ?)"
                )->execute([
                    self::CFG_MODULE_ENABLED,
                    '0',
                    self::CFG_LABEL_MODULE_ENABLED,
                    self::CFG_CATEGORY,
                ]);
                return;
            }

            $this->db->prepare(
                "INSERT INTO well_config (`key`, `value`, label, category)
                 SELECT ?, ?, ?, ?
                 FROM DUAL
                 WHERE NOT EXISTS (SELECT 1 FROM well_config WHERE `key` = ?)"
            )->execute([
                self::CFG_MODULE_ENABLED,
                '0',
                self::CFG_LABEL_MODULE_ENABLED,
                self::CFG_CATEGORY,
                self::CFG_MODULE_ENABLED,
            ]);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('SabotageService', 'ensureConfig FAILED', $e);
            }
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listOptions(): array
    {
        try {
            return $this->db->query("SELECT * FROM sabotage_options ORDER BY target_type, sort_order, id")
                ->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('SabotageService', 'listOptions FAILED', $e);
            }
            return [];
        }
    }

    /**
     * @return array<int,list<array<string,mixed>>>
     */
    public function listEffectsByOption(): array
    {
        $out = [];
        try {
            foreach ($this->db->query("SELECT * FROM sabotage_effects ORDER BY effect_key")->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $out[(int)$row['sabotage_option_id']][] = $row;
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('SabotageService', 'listEffectsByOption FAILED', $e);
            }
        }
        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listAttempts(int $limit = 100): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT sa.*, so.code AS option_code, so.name AS option_name,
                        p.username AS target_username, p.company_name AS target_company
                   FROM sabotage_attempts sa
                   JOIN sabotage_options so ON so.id = sa.sabotage_option_id
                   LEFT JOIN players p ON p.id = sa.target_player_id
                  ORDER BY sa.id DESC
                  LIMIT ?"
            );
            $stmt->bindValue(1, max(1, min(500, $limit)), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('SabotageService', 'listAttempts FAILED', $e);
            }
            return [];
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listLogs(int $limit = 100): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT sl.*, p.username AS target_username, p.company_name AS target_company
                   FROM sabotage_logs sl
                   LEFT JOIN players p ON p.id = sl.target_player_id
                  ORDER BY sl.id DESC
                  LIMIT ?"
            );
            $stmt->bindValue(1, max(1, min(500, $limit)), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('SabotageService', 'listLogs FAILED', $e);
            }
            return [];
        }
    }

    /**
     * @param list<int> $optionIds
     * @return array<int,array<string,array{type:string,value:float}>>
     */
    private function effectsForMany(array $optionIds): array
    {
        $optionIds = array_values(array_unique(array_filter(array_map('intval', $optionIds))));
        if ($optionIds === []) {
            return [];
        }

        $ph = implode(',', array_fill(0, count($optionIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT sabotage_option_id, effect_key, effect_type, effect_value
               FROM sabotage_effects
              WHERE sabotage_option_id IN ({$ph})"
        );
        $stmt->execute($optionIds);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int)$row['sabotage_option_id']][(string)$row['effect_key']] = [
                'type' => (string)$row['effect_type'],
                'value' => (float)$row['effect_value'],
            ];
        }
        return $out;
    }

    private function pickOption(string $targetType, string $context, float $referenceValue): ?array
    {
        $options = $this->getAvailableOptions($targetType, $context);
        if ($options === []) {
            return null;
        }

        $weighted = [];
        $total = 0;
        foreach ($options as $option) {
            $weight = match ((string)$option['severity']) {
                'critical' => 1,
                'high' => 2,
                'medium' => 4,
                default => 6,
            };
            $weighted[] = [$option, $weight];
            $total += $weight;
        }

        $roll = mt_rand(1, max(1, $total));
        $cursor = 0;
        foreach ($weighted as [$option, $weight]) {
            $cursor += $weight;
            if ($roll <= $cursor) {
                return $option;
            }
        }
        return $options[0];
    }

    /**
     * @param array<string,array{type:string,value:float}> $effects
     */
    private function effectValue(array $effects, string $key, float $default): float
    {
        $effect = $effects[$key] ?? null;
        if ($effect === null) {
            return $default;
        }
        return (float)$effect['value'];
    }

    /**
     * @param array<string,mixed> $meta
     */
    private function recordAttempt(
        ?int $playerId,
        int $targetPlayerId,
        string $targetType,
        int $targetId,
        int $optionId,
        string $context,
        string $status,
        float $chancePct,
        float $rollValue,
        float $cost,
        string $paidFrom,
        bool $detected,
        bool $protectionApplied,
        array $meta
    ): int {
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowExpr = $driver === 'sqlite' ? "datetime('now')" : 'NOW()';
        $stmt = $this->db->prepare(
            "INSERT INTO sabotage_attempts
                (player_id, source_type, source_id, target_player_id, target_type, target_id,
                 sabotage_option_id, context, status, chance_pct, roll_value, cost, paid_from,
                 detected, protection_applied, created_at, resolved_at, meta_json)
             VALUES (?, 'system', NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, {$nowExpr}, {$nowExpr}, ?)"
        );
        $stmt->execute([
            $playerId,
            $targetPlayerId,
            $targetType,
            $targetId,
            $optionId,
            $context,
            $status,
            $chancePct,
            $rollValue,
            $cost,
            $paidFrom,
            $detected ? 1 : 0,
            $protectionApplied ? 1 : 0,
            $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * @param array<string,mixed> $meta
     */
    private function logEvent(
        int $attemptId,
        ?int $playerId,
        int $targetPlayerId,
        string $targetType,
        int $targetId,
        string $eventKey,
        string $message,
        array $meta = []
    ): void {
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowExpr = $driver === 'sqlite' ? "datetime('now')" : 'NOW()';
        $stmt = $this->db->prepare(
            "INSERT INTO sabotage_logs
                (sabotage_attempt_id, player_id, target_player_id, target_type, target_id,
                 event_key, message, meta_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, {$nowExpr})"
        );
        $stmt->execute([
            $attemptId ?: null,
            $playerId,
            $targetPlayerId,
            $targetType,
            $targetId,
            $eventKey,
            $message,
            $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }
}
