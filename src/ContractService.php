<?php
declare(strict_types=1);

require_once __DIR__ . '/Contracts/ContractSchema.php';
require_once __DIR__ . '/Contracts/ContractQueryTrait.php';
require_once __DIR__ . '/CompanyCredibilityService.php';
require_once __DIR__ . '/LegalService.php';

/**
 * ContractService - silnik kontraktow dlugoterminowych P1.
 * ContractService - P1 long-term contract engine.
 */
class ContractService
{
    use ContractQueryTrait;

    public const CFG_MODULE_ENABLED = 'contracts_module_enabled';
    public const TARGET_STORAGE = 'storage';
    public const TARGET_REGION = 'region';
    public const TARGET_HUB = 'hub';
    public const TARGET_PORT = 'port';
    public const TARGET_PLAYER_COMPANY = 'player_company';
    public const CONTEXT_STORAGE_DELIVERY = 'storage_oil_delivery';
    public const CONTEXT_REGION_DELIVERY = 'region_oil_delivery';
    public const CONTEXT_HUB_DELIVERY = 'hub_oil_delivery';
    public const CONTEXT_PORT_DELIVERY = 'port_oil_delivery';
    public const CONTEXT_P2P_DELIVERY = 'player_oil_delivery';

    private const CFG_LABEL_MODULE_ENABLED = 'Contracts module enabled';
    private const CFG_CATEGORY = 'contracts';

    private PDO $db;
    private CompanyCredibilityService $credibility;
    private ?LegalService $legal = null;
    private ?bool $moduleEnabledCache = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        ContractSchema::ensure($this->db);
        $this->ensureConfig();
        $this->credibility = new CompanyCredibilityService($this->db);
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
            return $this->moduleEnabledCache = ($value !== false && (float)$value > 0.5);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('ContractService', 'isModuleEnabled FAILED', $e);
            }
            return $this->moduleEnabledCache = false;
        }
    }

    public function setModuleEnabled(bool $enabled): void
    {
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $this->db->prepare(
                "INSERT INTO well_config (`key`, `value`, label, category)
                 VALUES (?, ?, ?, ?)
                 ON CONFLICT(`key`) DO UPDATE SET `value` = excluded.`value`, label = excluded.label, category = excluded.category"
            )->execute([self::CFG_MODULE_ENABLED, $enabled ? '1' : '0', self::CFG_LABEL_MODULE_ENABLED, self::CFG_CATEGORY]);
        } else {
            $this->db->prepare(
                "INSERT INTO well_config (`key`, `value`, label, category)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), label = VALUES(label), category = VALUES(category)"
            )->execute([self::CFG_MODULE_ENABLED, $enabled ? '1' : '0', self::CFG_LABEL_MODULE_ENABLED, self::CFG_CATEGORY]);
        }
        $this->moduleEnabledCache = $enabled;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function getAvailableOptions(int $playerId, string $targetType, string $context, float $referenceValue = 0.0): array
    {
        if (!$this->isModuleEnabled()) {
            return [];
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM contract_options
                  WHERE is_active = 1
                    AND target_type = ?
                    AND context = ?
                    AND (expires_at IS NULL OR expires_at > ?)
                  ORDER BY sort_order ASC, id ASC"
            );
            $stmt->execute([$targetType, $context, $this->nowString()]);
            $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('ContractService', 'getAvailableOptions FAILED', $e, ['player_id' => $playerId]);
            }
            return [];
        }

        $termsMap = $this->termsForMany(array_map(static fn(array $o): int => (int)$o['id'], $options));
        $score = $this->credibility->getScore($playerId);
        $legalNeeded = array_filter($options, static fn(array $o): bool => (int)$o['requires_legal_level'] > 0) !== [];
        $legalLevel = $legalNeeded ? $this->legalLevel($playerId) : 0;

        foreach ($options as &$option) {
            $terms = $termsMap[(int)$option['id']] ?? [];
            $option['terms'] = $terms;
            $option['locked_reason'] = $this->lockedReason($option, $score, $legalLevel);
            $option['requirements_met'] = $option['locked_reason'] === null;
            $option['reference_value'] = $referenceValue;
        }
        unset($option);

        return $options;
    }

    /**
     * @return array<string,mixed>
     */
    public function acceptContract(int $playerId, int $optionId, string $targetType, ?int $targetId, string $context): array
    {
        if (!$this->isModuleEnabled()) {
            return $this->result(false, 'module_disabled');
        }
        if ($playerId <= 0 || $optionId <= 0) {
            return $this->result(false, 'invalid_input');
        }
        if (!$this->playerExists($playerId)) {
            return $this->result(false, 'player_not_found');
        }

        $option = $this->optionById($optionId);
        if ($option === null || (int)$option['is_active'] !== 1) {
            return $this->result(false, 'option_unavailable');
        }
        if ((string)$option['target_type'] !== $targetType || (string)$option['context'] !== $context) {
            return $this->result(false, 'context_mismatch');
        }
        if ($targetType !== self::TARGET_STORAGE || $context !== self::CONTEXT_STORAGE_DELIVERY) {
            return $this->result(false, 'target_not_supported');
        }
        if ($this->isExpired($option)) {
            return $this->result(false, 'option_expired');
        }

        $terms = $this->termsForOption($optionId);
        $validation = $this->validateRequiredTerms($terms);
        if ($validation !== null) {
            return $this->result(false, $validation);
        }
        $legalLevel = (int)$option['requires_legal_level'] > 0 ? $this->legalLevel($playerId) : 0;
        $locked = $this->lockedReason($option, $this->credibility->getScore($playerId), $legalLevel);
        if ($locked !== null) {
            return $this->result(false, 'requirements_' . $locked);
        }
        $now = $this->nowString();
        $next = $this->datePlusMinutes($now, (int)$terms['delivery_interval_minutes']['value']);
        $ends = $this->datePlusMinutes($now, (int)$terms['duration_minutes']['value']);
        $termsJson = json_encode($terms, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ownTx = false;
        try {
            $ownTx = !$this->db->inTransaction();
            if ($ownTx) {
                $this->db->beginTransaction();
            }
            if (!$this->lockPlayerRow($playerId)) {
                if ($ownTx) {
                    $this->db->rollBack();
                }
                return $this->result(false, 'player_not_found');
            }
            $maxActive = (int)$option['max_active_per_player'];
            if ($maxActive > 0 && $this->activeContractCount($playerId) >= $maxActive) {
                if ($ownTx) {
                    $this->db->rollBack();
                }
                return $this->result(false, 'limit_reached');
            }
            $stmt = $this->db->prepare(
                "INSERT INTO player_contracts
                    (player_id, contract_option_id, target_type, target_id, context, buyer_name, contract_name,
                     status, total_bbl, delivered_bbl, missed_bbl, next_delivery_at, starts_at, ends_at,
                     terms_json, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, 0, 0, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $playerId,
                $optionId,
                $targetType,
                $targetId,
                $context,
                (string)$option['buyer_name'],
                (string)$option['name'],
                (float)$terms['total_bbl']['value'],
                $next,
                $now,
                $ends,
                $termsJson,
                $now,
                $now,
            ]);
            $contractId = (int)$this->db->lastInsertId();
            $this->logEvent($playerId, $contractId, $targetType, $targetId, $context, 'contract_signed', 'contract.log.signed', [
                'option_code' => (string)$option['code'],
            ]);
            if ($ownTx) {
                $this->db->commit();
            }
            return ['success' => true, 'status' => 'signed', 'contract_id' => $contractId, 'message_key' => 'contracts.msg_signed'];
        } catch (Throwable $e) {
            if ($ownTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if (class_exists('GameLog', false)) {
                GameLog::error('ContractService', 'acceptContract FAILED', $e, ['player_id' => $playerId, 'option_id' => $optionId]);
            }
            return $this->result(false, 'db_error');
        }
    }

    /** @return array<string,mixed> */
    public function cancelContract(int $playerId, int $contractId): array
    {
        if ($playerId <= 0 || $contractId <= 0) {
            return $this->result(false, 'invalid_input');
        }
        $now = $this->nowString();
        $ownTx = false;
        try {
            $ownTx = !$this->db->inTransaction();
            if ($ownTx) {
                $this->db->beginTransaction();
            }
            $row = $this->contractForUpdate($playerId, $contractId);
            if ($row === null) {
                if ($ownTx) {
                    $this->db->rollBack();
                }
                return $this->result(false, 'not_found');
            }
            if ((string)$row['status'] !== 'active') {
                if ($ownTx) {
                    $this->db->rollBack();
                }
                return $this->result(false, 'cancel_status');
            }
            $this->db->prepare(
                "UPDATE player_contracts SET status = 'cancelled', cancelled_at = ?, updated_at = ? WHERE id = ? AND player_id = ?"
            )->execute([$now, $now, $contractId, $playerId]);
            $this->logEvent($playerId, $contractId, (string)$row['target_type'], $row['target_id'] === null ? null : (int)$row['target_id'], (string)$row['context'], 'contract_cancelled', 'contract.log.cancelled');
            if ($ownTx) {
                $this->db->commit();
            }
            return ['success' => true, 'status' => 'cancelled', 'contract_id' => $contractId, 'message_key' => 'contracts.msg_cancelled'];
        } catch (Throwable $e) {
            if ($ownTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if (class_exists('GameLog', false)) {
                GameLog::error('ContractService', 'cancelContract FAILED', $e, ['player_id' => $playerId, 'contract_id' => $contractId]);
            }
            return $this->result(false, 'db_error');
        }
    }

}
