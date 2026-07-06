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
    private ?FinancialTransactionService $fts = null;
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

    /**
     * Przetwarza wszystkie wymagalne dostawy kontraktowe.
     * Processes all due contract deliveries.
     *
     * @return array{processed:int,completed:int,failed:int,revenue:float,penalties:float}
     */
    public function processDueContracts(\DateTime $now, float $marketPrice): array
    {
        if (!$this->isModuleEnabled()) {
            return ['processed' => 0, 'completed' => 0, 'failed' => 0, 'revenue' => 0.0, 'penalties' => 0.0];
        }

        $nowStr = $now->format('Y-m-d H:i:s');
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM player_contracts
                  WHERE status = 'active'
                    AND next_delivery_at <= ?
                  ORDER BY next_delivery_at ASC
                  LIMIT 200"
            );
            $stmt->execute([$nowStr]);
            $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('ContractService', 'processDueContracts SELECT FAILED', $e);
            }
            return ['processed' => 0, 'completed' => 0, 'failed' => 0, 'revenue' => 0.0, 'penalties' => 0.0];
        }

        $processed = 0;
        $completed  = 0;
        $failed     = 0;
        $revenue    = 0.0;
        $penalties  = 0.0;

        foreach ($contracts as $contract) {
            try {
                $r = $this->processOneDueContract($contract, $now, $marketPrice);
                $processed++;
                $revenue   += $r['revenue'];
                $penalties += $r['penalty'];
                if ($r['new_status'] === 'completed') {
                    $completed++;
                } elseif ($r['new_status'] === 'failed') {
                    $failed++;
                }
            } catch (Throwable $e) {
                if (class_exists('GameLog', false)) {
                    GameLog::error('ContractService', 'processOneDueContract FAILED', $e, [
                        'contract_id' => $contract['id'] ?? null,
                        'player_id'   => $contract['player_id'] ?? null,
                    ]);
                }
            }
        }

        return compact('processed', 'completed', 'failed', 'revenue', 'penalties');
    }

    /**
     * Rozlicza jedna rate dostawy kontraktowej w transakcji.
     * Settles one due contract delivery in a single transaction.
     *
     * @param array<string,mixed> $contract
     * @return array{revenue:float,penalty:float,new_status:string}
     */
    private function processOneDueContract(array $contract, \DateTime $now, float $marketPrice): array
    {
        $contractId = (int)$contract['id'];
        $playerId   = (int)$contract['player_id'];
        $nowStr     = $now->format('Y-m-d H:i:s');
        $forUpdate  = $this->driver() === 'sqlite' ? '' : ' FOR UPDATE';

        $this->db->beginTransaction();
        try {
            // Ponowna blokada i sprawdzenie statusu po blokadzie.
            // Re-lock and re-check status after acquiring lock.
            $cStmt = $this->db->prepare(
                "SELECT * FROM player_contracts WHERE id = ? AND player_id = ? AND status = 'active' LIMIT 1{$forUpdate}"
            );
            $cStmt->execute([$contractId, $playerId]);
            $contract = $cStmt->fetch(PDO::FETCH_ASSOC);
            if ($contract === false) {
                $this->db->rollBack();
                return ['revenue' => 0.0, 'penalty' => 0.0, 'new_status' => 'skipped'];
            }

            // Sprawdz czy dostawa jest nadal wymagalna po blokadzie.
            // Check if delivery is still due after acquiring lock.
            if ((string)$contract['next_delivery_at'] > $nowStr) {
                $this->db->rollBack();
                return ['revenue' => 0.0, 'penalty' => 0.0, 'new_status' => 'skipped'];
            }

            // Magazyn gracza FOR UPDATE.
            // Player storage FOR UPDATE.
            $sStmt = $this->db->prepare(
                "SELECT used, capacity FROM storage WHERE player_id = ? LIMIT 1{$forUpdate}"
            );
            $sStmt->execute([$playerId]);
            $storage = $sStmt->fetch(PDO::FETCH_ASSOC);
            $storageUsed = $storage !== false ? max(0.0, (float)$storage['used']) : 0.0;

            // Warunki kontraktu z terms_json (snapshot przy podpisaniu).
            // Contract terms from terms_json snapshot (captured at signing).
            /** @var array<string,array{type:string,value:float,text:?string}> $terms */
            $terms = json_decode((string)$contract['terms_json'], true) ?? [];

            $totalBbl        = (float)$contract['total_bbl'];
            $deliveredSoFar  = (float)$contract['delivered_bbl'];
            $deliveryBbl     = (float)($terms['delivery_bbl']['value'] ?? 0.0);
            $intervalMinutes = (int)($terms['delivery_interval_minutes']['value'] ?? 60);
            $penaltyPct      = (float)($terms['penalty_pct']['value'] ?? 0.0);

            $remainingBbl  = max(0.0, $totalBbl - $deliveredSoFar);
            $requiredBbl   = min($deliveryBbl, $remainingBbl);
            $deliveredBbl  = min($requiredBbl, $storageUsed);
            $missedBbl     = round(max(0.0, $requiredBbl - $deliveredBbl), 4);
            $deliveredBbl  = round($deliveredBbl, 4);

            // Pobierz rope z magazynu / Draw oil from storage.
            if ($deliveredBbl > 0.0) {
                $this->db->prepare(
                    "UPDATE storage SET used = GREATEST(0, used - ?), updated_at = NOW() WHERE player_id = ?"
                )->execute([$deliveredBbl, $playerId]);
            }

            // Cena i rozliczenie finansowe / Price and financial settlement.
            $pricePerBbl = $this->calculatePrice($terms, $marketPrice);
            $revenue     = round($deliveredBbl * $pricePerBbl, 2);
            $penalty     = round($missedBbl * $pricePerBbl * $penaltyPct / 100.0, 2);

            $this->fts ??= new FinancialTransactionService($this->db);

            if ($revenue > 0.0) {
                $this->fts->credit(
                    $playerId,
                    $revenue,
                    FinancialTransactionService::TYPE_CONTRACT_SALE,
                    tPlain('bank.tx_contract_sale', ['id' => $contractId]),
                    'contract',
                    $contractId
                );
            }
            if ($penalty > 0.0) {
                $this->fts->debitCombined(
                    $playerId,
                    $penalty,
                    FinancialTransactionService::TYPE_CONTRACT_PENALTY,
                    tPlain('bank.tx_contract_penalty', ['id' => $contractId]),
                    'contract',
                    $contractId
                );
            }

            // Wpis historii dostawy / Delivery record.
            $deliveryStatus = match(true) {
                $missedBbl <= 0.0     => 'delivered',
                $deliveredBbl > 0.0   => 'partial',
                default               => 'missed',
            };
            $this->db->prepare(
                "INSERT INTO contract_deliveries
                    (player_contract_id, player_id, due_at, required_bbl, delivered_bbl,
                     missed_bbl, price_per_bbl, revenue, penalty, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $contractId, $playerId,
                (string)$contract['next_delivery_at'],
                $requiredBbl, $deliveredBbl, $missedBbl,
                $pricePerBbl, $revenue, $penalty,
                $deliveryStatus,
                $nowStr,
            ]);

            // Nowy termin dostawy i sumy / Next delivery date and running totals.
            $nextDelivery    = $this->datePlusMinutes($nowStr, $intervalMinutes);
            $newDeliveredBbl = round($deliveredSoFar + $deliveredBbl, 4);

            $newStatus = 'active';
            $completedAt = null;
            if ($newDeliveredBbl >= $totalBbl - 0.001) {
                $newStatus   = 'completed';
                $completedAt = $nowStr;
            } elseif ($nowStr >= (string)$contract['ends_at']) {
                $newStatus = 'failed';
            }

            $updateSql = "UPDATE player_contracts
                SET delivered_bbl = ?,
                    missed_bbl    = GREATEST(0, missed_bbl + ?),
                    next_delivery_at = ?,
                    status        = ?,
                    completed_at  = ?,
                    updated_at    = ?
              WHERE id = ? AND player_id = ?";
            $this->db->prepare($updateSql)->execute([
                $newDeliveredBbl,
                $missedBbl,
                $nextDelivery,
                $newStatus,
                $completedAt,
                $nowStr,
                $contractId,
                $playerId,
            ]);

            // Log zdarzenia / Event log.
            $eventKey = match($newStatus) {
                'completed' => 'contract_completed',
                'failed'    => 'contract_failed',
                default     => 'contract_delivery',
            };
            $this->logEvent($playerId, $contractId, (string)$contract['target_type'], null, (string)$contract['context'], $eventKey, $eventKey, [
                'delivered_bbl' => $deliveredBbl,
                'missed_bbl'    => $missedBbl,
                'revenue'       => $revenue,
                'penalty'       => $penalty,
                'new_status'    => $newStatus,
            ]);

            $this->db->commit();
            return ['revenue' => $revenue, 'penalty' => $penalty, 'new_status' => $newStatus];

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

}
