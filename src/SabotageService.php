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
    public const TARGET_PLAYER_COMPANY = 'player_company';
    public const CONTEXT_ROAD_TRANSPORT = 'road_transport_sabotage';
    public const CONTEXT_PLAYER_COMPANY = 'player_company_sabotage';
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
        $lossPct = $this->effectValue($effects, 'transport_loss_pct', 0.0);
        $delayMinutes = (int)round($this->effectValue($effects, 'delay_minutes', 0.0));
        $chancePct = max(0.0, min(100.0, (float)($option['base_chance_pct'] ?? 0.0)));
        $rollValue = mt_rand(1, 1_000_000) / 10_000.0;
        $status = $rollValue <= $chancePct ? 'success' : 'failed';
        if ($status !== 'success') {
            $lossPct = 0.0;
            $delayMinutes = 0;
            // Zablokowany przez ochrone tylko gdy opcja miala niezerowa szanse — inaczej
            // ochrona dostaje falszywe zaslugi za zatrzymanie czegos co i tak by nie trafilo.
            // Blocked by protection only when the option had non-zero chance — otherwise
            // protection gets spurious credit for stopping something that could not succeed anyway.
            if ($protectionApplied && $chancePct > 0.0) {
                $status = 'blocked_by_protection';
            }
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
                $meta + ['reference_value' => $referenceValue, 'option_code' => (string)$option['code']],
                'system',
                null
            );

            $messageKey = match ($status) {
                'success' => 'sabotage.log_road_success',
                'blocked_by_protection' => 'sabotage.log_road_blocked',
                default => 'sabotage.log_road_failed',
            };
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

    /**
     * @return list<array<string,mixed>>
     */
    public function countPlayerTargets(int $playerId): int
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM players WHERE id <> ? AND status = 'active'"
            );
            $stmt->execute([$playerId]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    public function getPlayerTargets(int $playerId, int $limit = 12, int $offset = 0): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT id, username, company_name, status, cash, black_market_score
                   FROM players
                  WHERE id <> ? AND status = 'active'
                  ORDER BY company_name ASC, username ASC
                  LIMIT ? OFFSET ?"
            );
            $stmt->bindValue(1, $playerId, PDO::PARAM_INT);
            $stmt->bindValue(2, max(1, min(50, $limit)), PDO::PARAM_INT);
            $stmt->bindValue(3, max(0, $offset), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('SabotageService', 'getPlayerTargets FAILED', $e, ['player_id' => $playerId]);
            }
            return [];
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listAttemptsForPlayer(int $playerId, int $limit = 50): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT sa.*, so.code AS option_code, so.name AS option_name,
                        p.username AS target_username, p.company_name AS target_company
                   FROM sabotage_attempts sa
                   LEFT JOIN sabotage_options so ON so.id = sa.sabotage_option_id
                   LEFT JOIN players p ON p.id = sa.target_player_id
                  WHERE sa.player_id = ?
                  ORDER BY sa.id DESC
                  LIMIT ?"
            );
            $stmt->bindValue(1, $playerId, PDO::PARAM_INT);
            $stmt->bindValue(2, max(1, min(200, $limit)), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('SabotageService', 'listAttemptsForPlayer FAILED', $e, ['player_id' => $playerId]);
            }
            return [];
        }
    }

    /**
     * @param list<int> $targetIds
     * @param list<int> $optionIds
     * @return array<int,array<int,string>>
     */
    public function getPlayerCooldownMap(int $playerId, array $targetIds, array $optionIds): array
    {
        $targetIds = array_values(array_unique(array_filter(array_map('intval', $targetIds))));
        $optionIds = array_values(array_unique(array_filter(array_map('intval', $optionIds))));
        if ($targetIds === [] || $optionIds === []) {
            return [];
        }

        $targetPh = implode(',', array_fill(0, count($targetIds), '?'));
        $optionPh = implode(',', array_fill(0, count($optionIds), '?'));
        $params = array_merge([$playerId, self::TARGET_PLAYER_COMPANY], $targetIds, $optionIds);

        try {
            $stmt = $this->db->prepare(
                "SELECT sa.target_player_id, sa.sabotage_option_id, sa.created_at, so.cooldown_minutes
                   FROM sabotage_attempts sa
                   JOIN sabotage_options so ON so.id = sa.sabotage_option_id
                  WHERE sa.player_id = ?
                    AND sa.target_type = ?
                    AND sa.target_player_id IN ({$targetPh})
                    AND sa.sabotage_option_id IN ({$optionPh})
                  ORDER BY sa.id DESC"
            );
            $stmt->execute($params);

            $out = [];
            $nowTs = time();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $targetId = (int)$row['target_player_id'];
                $optionId = (int)$row['sabotage_option_id'];
                if (isset($out[$targetId][$optionId])) {
                    continue;
                }
                $cooldown = max(0, (int)$row['cooldown_minutes']);
                if ($cooldown <= 0) {
                    continue;
                }
                $createdTs = strtotime((string)$row['created_at']);
                if ($createdTs === false) {
                    continue;
                }
                $untilTs = $createdTs + ($cooldown * 60);
                if ($untilTs <= $nowTs) {
                    continue;
                }
                $out[$targetId][$optionId] = date('Y-m-d H:i:s', $untilTs);
            }
            return $out;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('SabotageService', 'getPlayerCooldownMap FAILED', $e, ['player_id' => $playerId]);
            }
            return [];
        }
    }

    /**
     * @return array{success:bool,status:string,message:string,option:?array<string,mixed>,target:?array<string,mixed>,cost:float,cash_loss:float,credibility_delta:int,cooldown_until:?string}
     */
    public function executePlayerSabotage(int $playerId, int $targetPlayerId, int $optionId): array
    {
        // Serializuj rownoczesne proby tego samego atakujacego, by zamknac wyscig TOCTOU na
        // cooldownie (dwa zadania z dwoch sesji omijajace ten sam cooldown). Na MySQL nazwany
        // lock; na innych sterownikach (SQLite/testy) wspolbieznosc nie wystepuje.
        // Serialize concurrent attempts by the same attacker to close the cooldown TOCTOU race
        // (two requests from two sessions bypassing the same cooldown). MySQL named lock; on
        // other drivers (SQLite/tests) there is no concurrency.
        if ((string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return $this->runPlayerSabotage($playerId, $targetPlayerId, $optionId);
        }

        $lockName = 'sabotage_exec_' . $playerId;
        $gotLock = false;
        try {
            $lockStmt = $this->db->prepare("SELECT GET_LOCK(?, 5)");
            $lockStmt->execute([$lockName]);
            $gotLock = ((int)$lockStmt->fetchColumn() === 1);
            return $this->runPlayerSabotage($playerId, $targetPlayerId, $optionId);
        } finally {
            if ($gotLock) {
                $this->db->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
            }
        }
    }

    /**
     * @return array{success:bool,status:string,message:string,option:?array<string,mixed>,target:?array<string,mixed>,cost:float,cash_loss:float,credibility_delta:int,cooldown_until:?string}
     */
    private function runPlayerSabotage(int $playerId, int $targetPlayerId, int $optionId): array
    {
        if (!$this->isModuleEnabled()) {
            return [
                'success' => false,
                'status' => 'cancelled',
                'message' => tPlain('sabotage.err_module_disabled'),
                'option' => null,
                'target' => null,
                'cost' => 0.0,
                'cash_loss' => 0.0,
                'credibility_delta' => 0,
                'cooldown_until' => null,
            ];
        }

        if ($playerId <= 0 || $targetPlayerId <= 0 || $optionId <= 0 || $playerId === $targetPlayerId) {
            return [
                'success' => false,
                'status' => 'cancelled',
                'message' => tPlain('sabotage.err_invalid_target'),
                'option' => null,
                'target' => null,
                'cost' => 0.0,
                'cash_loss' => 0.0,
                'credibility_delta' => 0,
                'cooldown_until' => null,
            ];
        }

        $option = $this->getOptionByIdForContext($optionId, self::TARGET_PLAYER_COMPANY, self::CONTEXT_PLAYER_COMPANY);
        $attacker = $this->loadPlayerRow($playerId);
        $target = $this->loadPlayerRow($targetPlayerId);

        if ($option === null || $attacker === null || $target === null) {
            return [
                'success' => false,
                'status' => 'cancelled',
                'message' => tPlain('sabotage.err_option_unavailable'),
                'option' => $option,
                'target' => $target,
                'cost' => 0.0,
                'cash_loss' => 0.0,
                'credibility_delta' => 0,
                'cooldown_until' => null,
            ];
        }

        $bmThreshold = $this->getBmSabotageThreshold();
        if ((int)($option['requires_black_market'] ?? 0) === 1 && (float)($attacker['black_market_score'] ?? 0.0) < $bmThreshold) {
            return [
                'success' => false,
                'status' => 'cancelled',
                'message' => tPlain('sabotage.err_black_market_required'),
                'option' => $option,
                'target' => $target,
                'cost' => 0.0,
                'cash_loss' => 0.0,
                'credibility_delta' => 0,
                'cooldown_until' => null,
            ];
        }

        $cooldownUntil = $this->getCooldownUntilForPair($playerId, $targetPlayerId, $optionId, (int)($option['cooldown_minutes'] ?? 0));
        if ($cooldownUntil !== null) {
            return [
                'success' => false,
                'status' => 'cancelled',
                'message' => tPlain('sabotage.err_cooldown', ['time' => $cooldownUntil]),
                'option' => $option,
                'target' => $target,
                'cost' => 0.0,
                'cash_loss' => 0.0,
                'credibility_delta' => 0,
                'cooldown_until' => $cooldownUntil,
            ];
        }

        $cost = $this->calculateCost($option, (float)($target['cash'] ?? 0.0));
        $targetLabel = (string)($target['company_name'] ?: $target['username']);
        $optionLabel = (string)($option['name'] ?? $option['code'] ?? 'sabotage');
        $startedTxn = false;

        try {
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $startedTxn = true;
            }

            $fts = new FinancialTransactionService($this->db);
            if ($cost >= FinancialTransactionService::MIN_AMOUNT) {
                $charge = $fts->debit(
                    $playerId,
                    $cost,
                    FinancialTransactionService::TYPE_SABOTAGE,
                    tPlain('sabotage.tx_cost', ['name' => $optionLabel, 'target' => $targetLabel]),
                    'sabotage_option',
                    $optionId
                );
                if (!$charge['success']) {
                    if ($startedTxn && $this->db->inTransaction()) {
                        $this->db->rollBack();
                    }
                    return [
                        'success' => false,
                        'status' => 'cancelled',
                        'message' => ($charge['error'] ?? '') === 'insufficient_funds'
                            ? tPlain('sabotage.err_insufficient_funds')
                            : tPlain('sabotage.err_payment_failed'),
                        'option' => $option,
                        'target' => $target,
                        'cost' => $cost,
                        'cash_loss' => 0.0,
                        'credibility_delta' => 0,
                        'cooldown_until' => null,
                    ];
                }
            }

            $effects = (array)($option['effects'] ?? []);
            $chancePct = max(0.0, min(100.0, (float)($option['base_chance_pct'] ?? 0.0)));
            $rollValue = mt_rand(1, 1_000_000) / 10_000.0;
            $status = $rollValue <= $chancePct ? 'success' : 'failed';
            $cashLoss = 0.0;
            $credibilityDelta = 0;

            if ($status === 'success') {
                // Najpierw zablokuj i odczytaj aktualna gotowke celu, dopiero potem licz
                // procentowa strate od tej swiezej wartosci (snapshot z loadPlayerRow moze byc
                // nieaktualny po wplatach/wyplatach celu w miedzyczasie).
                // Lock and read the target's current cash first, then compute the percentage
                // loss from that fresh value (the loadPlayerRow snapshot may be stale after the
                // target's deposits/withdrawals in the meantime).
                $currentTargetCash = $this->lockPlayerCash($targetPlayerId);

                $requestedCashLoss = 0.0;
                if (isset($effects['target_cash_loss_fixed'])) {
                    $requestedCashLoss += max(0.0, (float)$effects['target_cash_loss_fixed']['value']);
                }
                if (isset($effects['target_cash_loss_pct'])) {
                    $requestedCashLoss += max(0.0, $currentTargetCash * ((float)$effects['target_cash_loss_pct']['value'] / 100.0));
                }

                $cashLoss = round(min($requestedCashLoss, max(0.0, $currentTargetCash)), 2);
                if ($cashLoss >= FinancialTransactionService::MIN_AMOUNT) {
                    $hit = $fts->debit(
                        $targetPlayerId,
                        $cashLoss,
                        FinancialTransactionService::TYPE_SABOTAGE,
                        tPlain('sabotage.tx_target_hit', ['name' => $optionLabel]),
                        'sabotage_option',
                        $optionId
                    );
                    if (!$hit['success']) {
                        $cashLoss = 0.0;
                    }
                } else {
                    $cashLoss = 0.0;
                }

                if (isset($effects['target_credibility_delta'])) {
                    $credibilityDelta = (int)round((float)$effects['target_credibility_delta']['value']);
                    if ($credibilityDelta !== 0) {
                        $credibility = new CompanyCredibilityService($this->db);
                        $credibility->changeScore(
                            $targetPlayerId,
                            $credibilityDelta,
                            'sabotage_player',
                            tPlain('sabotage.credibility_note', ['name' => $optionLabel])
                        );
                    }
                }

                // 'partially_blocked' tylko gdy czesc kwoty faktycznie sciagnieto (cel czesciowo
                // niewyplacalny). Gdy cel nie ma wcale gotowki, sabotaz formalnie sie udal, ale
                // bez skutku finansowego - nie udajemy ze cos go zablokowalo.
                // 'partially_blocked' only when part of the amount was actually taken (target
                // partly insolvent). When the target has no cash at all the sabotage formally
                // succeeded with no financial effect - we do not pretend something blocked it.
                if ($requestedCashLoss > 0.0 && $cashLoss > 0.0 && $cashLoss < $requestedCashLoss) {
                    $status = 'partially_blocked';
                }
            }

            $attemptId = $this->recordAttempt(
                $playerId,
                $targetPlayerId,
                self::TARGET_PLAYER_COMPANY,
                $targetPlayerId,
                $optionId,
                self::CONTEXT_PLAYER_COMPANY,
                $status,
                $chancePct,
                $rollValue,
                $cost,
                'cash',
                false,
                false,
                [
                    'option_code' => (string)$option['code'],
                    'target_company' => $targetLabel,
                    'cash_loss' => $cashLoss,
                    'credibility_delta' => $credibilityDelta,
                ],
                'player',
                $playerId
            );

            $messageKey = match ($status) {
                'success' => 'sabotage.log_player_success',
                'partially_blocked' => 'sabotage.log_player_partial',
                default => 'sabotage.log_player_failed',
            };
            $this->logEvent(
                $attemptId,
                $playerId,
                $targetPlayerId,
                self::TARGET_PLAYER_COMPANY,
                $targetPlayerId,
                $status === 'failed' ? 'player_sabotage_failed' : 'player_sabotage_applied',
                tPlain($messageKey, ['name' => $optionLabel, 'target' => $targetLabel]),
                [
                    'cash_loss' => $cashLoss,
                    'credibility_delta' => $credibilityDelta,
                    'roll_value' => $rollValue,
                    'chance_pct' => $chancePct,
                ]
            );

            if ($startedTxn && $this->db->inTransaction()) {
                $this->db->commit();
            }

            $message = match ($status) {
                'success' => tPlain('sabotage.msg_success', ['target' => $targetLabel, 'name' => $optionLabel]),
                'partially_blocked' => tPlain('sabotage.msg_partial', ['target' => $targetLabel, 'name' => $optionLabel]),
                default => tPlain('sabotage.msg_failed', ['target' => $targetLabel, 'name' => $optionLabel]),
            };

            return [
                'success' => true,
                'status' => $status,
                'message' => $message,
                'option' => $option,
                'target' => $target,
                'cost' => $cost,
                'cash_loss' => $cashLoss,
                'credibility_delta' => $credibilityDelta,
                'cooldown_until' => null,
            ];
        } catch (Throwable $e) {
            if ($startedTxn && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if (class_exists('GameLog', false)) {
                GameLog::error('SabotageService', 'executePlayerSabotage FAILED', $e, [
                    'player_id' => $playerId,
                    'target_player_id' => $targetPlayerId,
                    'option_id' => $optionId,
                ]);
            }
            return [
                'success' => false,
                'status' => 'failed',
                'message' => tPlain('sabotage.err_execute_failed'),
                'option' => $option,
                'target' => $target,
                'cost' => $cost,
                'cash_loss' => 0.0,
                'credibility_delta' => 0,
                'cooldown_until' => null,
            ];
        }
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
     * @return array<string,mixed>|null
     */
    private function getOptionByIdForContext(int $optionId, string $targetType, string $context): ?array
    {
        foreach ($this->getAvailableOptions($targetType, $context) as $option) {
            if ((int)($option['id'] ?? 0) === $optionId) {
                return $option;
            }
        }
        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function loadPlayerRow(int $playerId): ?array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT id, username, company_name, cash, status, black_market_score
                   FROM players
                  WHERE id = ?
                  LIMIT 1"
            );
            $stmt->execute([$playerId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('SabotageService', 'loadPlayerRow FAILED', $e, ['player_id' => $playerId]);
            }
            return null;
        }
    }

    private function calculateCost(array $option, float $referenceValue): float
    {
        $type = (string)($option['cost_type'] ?? 'fixed');
        $value = max(0.0, (float)($option['cost_value'] ?? 0.0));

        // per_bbl w kontekscie gracza nie ma wolumenu referencyjnego — dziala jak fixed.
        // per_bbl in player context has no volume reference — behaves like fixed.
        $cost = match ($type) {
            'percent_reference' => $referenceValue * ($value / 100.0),
            default             => $value,
        };

        return round(max(0.0, $cost), 2);
    }

    private function getCooldownUntilForPair(int $playerId, int $targetPlayerId, int $optionId, int $cooldownMinutes): ?string
    {
        if ($cooldownMinutes <= 0) {
            return null;
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT created_at
                   FROM sabotage_attempts
                  WHERE player_id = ?
                    AND target_type = ?
                    AND target_player_id = ?
                    AND sabotage_option_id = ?
                  ORDER BY id DESC
                  LIMIT 1"
            );
            $stmt->execute([$playerId, self::TARGET_PLAYER_COMPANY, $targetPlayerId, $optionId]);
            $createdAt = $stmt->fetchColumn();
            if (!is_string($createdAt) || $createdAt === '') {
                return null;
            }

            $createdTs = strtotime($createdAt);
            if ($createdTs === false) {
                return null;
            }

            $untilTs = $createdTs + ($cooldownMinutes * 60);
            if ($untilTs <= time()) {
                return null;
            }

            return date('Y-m-d H:i:s', $untilTs);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('SabotageService', 'getCooldownUntilForPair FAILED', $e, [
                    'player_id' => $playerId,
                    'target_player_id' => $targetPlayerId,
                    'option_id' => $optionId,
                ]);
            }
            return null;
        }
    }

    private function lockPlayerCash(int $playerId): float
    {
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        // FOR UPDATE tylko na MySQL — spojnie z FinancialTransactionService::lockAndReadBalance.
        // FOR UPDATE only on MySQL — consistent with FinancialTransactionService::lockAndReadBalance.
        $sql = "SELECT cash FROM players WHERE id = ? LIMIT 1";
        if ($driver === 'mysql') {
            $sql = "SELECT cash FROM players WHERE id = ? LIMIT 1 FOR UPDATE";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$playerId]);
        return max(0.0, (float)($stmt->fetchColumn() ?: 0.0));
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
        array $meta,
        string $sourceType = 'system',
        ?int $sourceId = null
    ): int {
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowExpr = $driver === 'sqlite' ? "datetime('now')" : 'NOW()';
        $stmt = $this->db->prepare(
            "INSERT INTO sabotage_attempts
                (player_id, source_type, source_id, target_player_id, target_type, target_id,
                 sabotage_option_id, context, status, chance_pct, roll_value, cost, paid_from,
                 detected, protection_applied, created_at, resolved_at, meta_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, {$nowExpr}, {$nowExpr}, ?)"
        );
        $stmt->execute([
            $playerId,
            $sourceType,
            $sourceId,
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

    // Prog punktow czarnego rynku wymagany do odblokowania sabotazu.
    // Minimum black_market_score required to use sabotage options that require black market.
    private function getBmSabotageThreshold(): float
    {
        try {
            $stmt = $this->db->prepare("SELECT value FROM well_config WHERE `key` = 'bm_sabotage_threshold' LIMIT 1");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            return $val !== false ? (float)$val : 30.0;
        } catch (Throwable) {
            return 30.0;
        }
    }
}
