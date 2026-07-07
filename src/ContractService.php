<?php
declare(strict_types=1);

require_once __DIR__ . '/Contracts/ContractSchema.php';
require_once __DIR__ . '/Contracts/ContractQueryTrait.php';
require_once __DIR__ . '/CompanyCredibilityService.php';
require_once __DIR__ . '/LegalService.php';
require_once __DIR__ . '/ContractReputationService.php';
require_once __DIR__ . '/FinancialTransactionService.php';

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

    /**
     * Klucze terminow, ktore wolno zmienic przez renegocjacje. Pozostale (zwlaszcza flagi
     * bezpieczenstwa: allow_cancel, cancel_forfeit_deposit, security_deposit_fixed,
     * allow_renegotiation, insurance_available) sa chronione przed nadpisaniem przez gracza.
     * Term keys a player may change via renegotiation. Everything else — especially the
     * security flags — is protected from being overwritten through renegotiation.
     */
    private const RENEGOTIABLE_TERMS = [
        'penalty_pct',
        'bonus_pct',
        'bonus_on_full_completion_pct',
        'delivery_bbl',
        'delivery_interval_minutes',
    ];

    private PDO $db;
    private CompanyCredibilityService $credibility;
    private ?LegalService $legal = null;
    private ?FinancialTransactionService $fts = null;
    private ?ContractReputationService $rep = null;
    private ?bool $moduleEnabledCache = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        ContractSchema::ensure($this->db);
        $this->ensureConfig();
        $this->credibility = new CompanyCredibilityService($this->db);
    }

    public function reputation(): ContractReputationService
    {
        return $this->rep ??= new ContractReputationService($this->db);
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
        $credScore = $this->credibility->getScore($playerId);
        $repScore  = $this->reputation()->getScore($playerId);
        $legalNeeded = array_filter($options, static fn(array $o): bool => (int)$o['requires_legal_level'] > 0) !== [];
        $legalLevel = $legalNeeded ? $this->legalLevel($playerId) : 0;

        foreach ($options as &$option) {
            $terms = $termsMap[(int)$option['id']] ?? [];
            $option['terms'] = $terms;
            $option['locked_reason'] = $this->lockedReason($option, $terms, $credScore, $legalLevel, $repScore);
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
        $locked = $this->lockedReason($option, $terms, $this->credibility->getScore($playerId), $legalLevel, $this->reputation()->getScore($playerId));
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

            // Kaucja / Security deposit.
            $depositAmount = round((float)($terms['security_deposit_fixed']['value'] ?? 0.0), 2);
            if ($depositAmount > 0.0) {
                $this->fts ??= new FinancialTransactionService($this->db);
                $dr = $this->fts->debitCombined(
                    $playerId,
                    $depositAmount,
                    FinancialTransactionService::TYPE_CONTRACT_DEPOSIT,
                    tPlain('bank.tx_contract_deposit', ['id' => $contractId]),
                    'contract',
                    $contractId
                );
                if (!$dr['success']) {
                    if ($ownTx) {
                        $this->db->rollBack();
                    } else {
                        // Nie mozemy cofnac transakcji wolajacego — usun tylko nasz swiezo wstawiony wiersz,
                        // aby nie zostal kontrakt bez pobranej kaucji.
                        // We cannot roll back the caller's transaction — delete only our just-inserted row
                        // so no contract survives without its deposit being charged.
                        $this->db->prepare("DELETE FROM player_contracts WHERE id = ?")->execute([$contractId]);
                    }
                    return $this->result(false, 'insufficient_funds_deposit');
                }
                $this->db->prepare(
                    "UPDATE player_contracts SET security_deposit = ? WHERE id = ?"
                )->execute([$depositAmount, $contractId]);
            }

            $this->reputation()->onContractSigned($playerId);
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
            /** @var array<string,array{type:string,value:float,text:?string}> $cancelTerms */
            $cancelTerms = json_decode((string)($row['terms_json'] ?? '[]'), true) ?? [];

            // Zerwanie kontraktu — sprawdz czy dozwolone / Check if cancellation is allowed.
            $allowCancel = (int)($cancelTerms['allow_cancel']['value'] ?? 1);
            if ($allowCancel === 0) {
                if ($ownTx) {
                    $this->db->rollBack();
                }
                return $this->result(false, 'cancel_not_allowed');
            }

            $cancelPenalty = round((float)($cancelTerms['cancel_penalty_fixed']['value'] ?? 0.0), 2);
            $forfeitDeposit = (int)($cancelTerms['cancel_forfeit_deposit']['value'] ?? 0) === 1;
            $depositHeld = round((float)($row['security_deposit'] ?? 0.0), 2);

            $this->fts ??= new FinancialTransactionService($this->db);

            // Najpierw rozliczenia finansowe — status kontraktu zmieniamy dopiero po ich powodzeniu,
            // aby nieudany debit nie zostawil kontraktu oznaczonego jako anulowany bez pobranej kary.
            // Financial settlement first — the status flip happens only after it succeeds, so a failed
            // debit can never leave a contract marked cancelled without the penalty actually charged.
            if ($cancelPenalty > 0.0) {
                $dr = $this->fts->debitCombined(
                    $playerId,
                    $cancelPenalty,
                    FinancialTransactionService::TYPE_CONTRACT_PENALTY,
                    tPlain('bank.tx_contract_penalty', ['id' => $contractId]),
                    'contract',
                    $contractId
                );
                if (!$dr['success']) {
                    if ($ownTx) {
                        $this->db->rollBack();
                    }
                    return $this->result(false, 'insufficient_funds_penalty');
                }
            }

            if ($depositHeld > 0.0 && !$forfeitDeposit) {
                $cr = $this->fts->credit(
                    $playerId,
                    $depositHeld,
                    FinancialTransactionService::TYPE_CONTRACT_DEPOSIT_REFUND,
                    tPlain('bank.tx_contract_deposit_refund', ['id' => $contractId]),
                    'contract',
                    $contractId
                );
                if (!$cr['success']) {
                    throw new \RuntimeException('FTS deposit refund failed: ' . ($cr['error'] ?? 'unknown'));
                }
            }

            $this->db->prepare(
                "UPDATE player_contracts SET status = 'cancelled', cancel_penalty = ?, cancelled_at = ?, updated_at = ? WHERE id = ? AND player_id = ?"
            )->execute([$cancelPenalty, $now, $now, $contractId, $playerId]);

            $this->reputation()->onContractCancelled($playerId, $contractId, $cancelTerms);
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
    public function processDueContracts(float $marketPrice): array
    {
        if (!$this->isModuleEnabled()) {
            return ['processed' => 0, 'completed' => 0, 'failed' => 0, 'revenue' => 0.0, 'penalties' => 0.0];
        }

        // Uzyj zegara MySQL, tak jak nowString() — porownujemy z next_delivery_at zapisanym przez MySQL NOW().
        // Use MySQL clock, same as nowString() — comparing against next_delivery_at written by MySQL NOW().
        $nowStr = $this->nowString();
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
                $r = $this->processOneDueContract($contract, $nowStr, $marketPrice);
                if ($r['new_status'] !== 'skipped') {
                    $processed++;
                }
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
     * Aktywuje ubezpieczenie dla aktywnego kontraktu.
     * Enables insurance for an active contract.
     *
     * @return array<string,mixed>
     */
    public function enableInsurance(int $playerId, int $contractId): array
    {
        if ($playerId <= 0 || $contractId <= 0) {
            return $this->result(false, 'invalid_input');
        }
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
                return $this->result(false, 'not_active');
            }
            if ((int)($row['insurance_enabled'] ?? 0) === 1) {
                if ($ownTx) {
                    $this->db->rollBack();
                }
                return $this->result(false, 'already_insured');
            }
            /** @var array<string,array{type:string,value:float,text:?string}> $terms */
            $terms = json_decode((string)($row['terms_json'] ?? '[]'), true) ?? [];
            $available = (int)($terms['insurance_available']['value'] ?? 0) === 1;
            if (!$available) {
                if ($ownTx) {
                    $this->db->rollBack();
                }
                return $this->result(false, 'insurance_not_available');
            }
            $depositHeld = round((float)($row['security_deposit'] ?? 0.0), 2);
            $costPct     = (float)($terms['insurance_cost_pct']['value'] ?? 0.0);
            $coveragePct = (float)($terms['insurance_penalty_coverage_pct']['value'] ?? 0.0);
            $cost        = round($depositHeld * $costPct / 100.0, 2);
            // Skladka wyliczana jest z kaucji — bez dodatniego kosztu nie przyznajemy darmowego ubezpieczenia.
            // The premium is derived from the deposit — without a positive cost we do not grant free insurance.
            if ($cost <= 0.0) {
                if ($ownTx) {
                    $this->db->rollBack();
                }
                return $this->result(false, 'insurance_no_cost_basis');
            }
            $now = $this->nowString();
            $this->fts ??= new FinancialTransactionService($this->db);
            $dr = $this->fts->debitCombined(
                $playerId,
                $cost,
                FinancialTransactionService::TYPE_CONTRACT_INSURANCE,
                tPlain('bank.tx_contract_insurance', ['id' => $contractId]),
                'contract',
                $contractId
            );
            if (!$dr['success']) {
                if ($ownTx) {
                    $this->db->rollBack();
                }
                return $this->result(false, 'insufficient_funds_insurance');
            }
            $this->db->prepare(
                "UPDATE player_contracts SET insurance_enabled = 1, insurance_cost = ?, insurance_coverage_pct = ?, updated_at = ? WHERE id = ?"
            )->execute([$cost, $coveragePct, $now, $contractId]);
            $this->logEvent($playerId, $contractId, (string)$row['target_type'], null, (string)$row['context'], 'insurance_enabled', 'contract.log.insurance_enabled');
            if ($ownTx) {
                $this->db->commit();
            }
            return ['success' => true, 'status' => 'insurance_enabled', 'cost' => $cost, 'coverage_pct' => $coveragePct, 'message_key' => 'contracts.insurance_enabled'];
        } catch (Throwable $e) {
            if ($ownTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if (class_exists('GameLog', false)) {
                GameLog::error('ContractService', 'enableInsurance FAILED', $e, ['player_id' => $playerId, 'contract_id' => $contractId]);
            }
            return $this->result(false, 'db_error');
        }
    }

    /**
     * Renegocjuje warunki aktywnego kontraktu.
     * Renegotiates terms of an active contract.
     *
     * @param array<string,float> $termOverrides  Mapa klucz => nowa wartosc liczbowa.
     * @return array<string,mixed>
     */
    public function renegotiateContract(int $playerId, int $contractId, array $termOverrides): array
    {
        if ($playerId <= 0 || $contractId <= 0 || $termOverrides === []) {
            return $this->result(false, 'invalid_input');
        }
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
                return $this->result(false, 'not_active');
            }
            /** @var array<string,array{type:string,value:float,text:?string}> $terms */
            $terms = json_decode((string)($row['terms_json'] ?? '[]'), true) ?? [];
            if ((int)($terms['allow_renegotiation']['value'] ?? 0) === 0) {
                if ($ownTx) {
                    $this->db->rollBack();
                }
                return $this->result(false, 'renegotiation_not_allowed');
            }
            $maxReneg  = (int)($terms['max_renegotiations']['value'] ?? 0);
            $usedReneg = (int)($row['renegotiations_used'] ?? 0);
            if ($maxReneg > 0 && $usedReneg >= $maxReneg) {
                if ($ownTx) {
                    $this->db->rollBack();
                }
                return $this->result(false, 'renegotiation_limit_reached');
            }
            $intervalMinutes = (int)($terms['renegotiation_interval_minutes']['value'] ?? 0);
            $lastAt = $row['last_renegotiated_at'] ?? null;
            if ($intervalMinutes > 0 && $lastAt !== null && $lastAt !== '') {
                if (time() < strtotime((string)$lastAt) + $intervalMinutes * 60) {
                    if ($ownTx) {
                        $this->db->rollBack();
                    }
                    return $this->result(false, 'renegotiation_too_soon');
                }
            }
            $oldTerms = $terms;
            $applied  = 0;
            foreach ($termOverrides as $key => $value) {
                // Tylko dozwolone klucze — chroni flagi bezpieczenstwa przed nadpisaniem.
                // Allowlist only — protects security flags from being overwritten.
                if (!in_array($key, self::RENEGOTIABLE_TERMS, true)) {
                    continue;
                }
                if (isset($terms[$key])) {
                    $terms[$key]['value'] = (float)$value;
                } else {
                    $terms[$key] = ['type' => 'number', 'value' => (float)$value, 'text' => null];
                }
                $applied++;
            }
            if ($applied === 0) {
                if ($ownTx) {
                    $this->db->rollBack();
                }
                return $this->result(false, 'renegotiation_no_valid_terms');
            }
            $now          = $this->nowString();
            $newTermsJson = json_encode($terms, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->db->prepare(
                "UPDATE player_contracts SET terms_json = ?, renegotiations_used = renegotiations_used + 1, last_renegotiated_at = ?, updated_at = ? WHERE id = ?"
            )->execute([$newTermsJson, $now, $now, $contractId]);
            $this->db->prepare(
                "INSERT INTO contract_renegotiations (player_contract_id, player_id, old_terms_json, new_terms_json, message, created_at) VALUES (?, ?, ?, ?, '', ?)"
            )->execute([
                $contractId,
                $playerId,
                json_encode($oldTerms, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $newTermsJson,
                $now,
            ]);
            $this->logEvent($playerId, $contractId, (string)$row['target_type'], null, (string)$row['context'], 'contract_renegotiated', 'contract.log.renegotiated');
            if ($ownTx) {
                $this->db->commit();
            }
            return ['success' => true, 'status' => 'renegotiated', 'renegotiations_used' => $usedReneg + 1, 'message_key' => 'contracts.renegotiated'];
        } catch (Throwable $e) {
            if ($ownTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if (class_exists('GameLog', false)) {
                GameLog::error('ContractService', 'renegotiateContract FAILED', $e, ['player_id' => $playerId, 'contract_id' => $contractId]);
            }
            return $this->result(false, 'db_error');
        }
    }

    /**
     * Rozlicza jedna rate dostawy kontraktowej w transakcji.
     * Settles one due contract delivery in a single transaction.
     *
     * @param array<string,mixed> $contract
     * @return array{revenue:float,penalty:float,new_status:string}
     */
    private function processOneDueContract(array $contract, string $nowStr, float $marketPrice): array
    {
        $contractId = (int)$contract['id'];
        $playerId   = (int)$contract['player_id'];
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

            // Ubezpieczenie zmniejsza kare / Insurance reduces the penalty.
            $insuranceEnabled  = (int)($contract['insurance_enabled'] ?? 0) === 1;
            $insuranceCoverage = (float)($contract['insurance_coverage_pct'] ?? 0.0);
            if ($insuranceEnabled && $insuranceCoverage > 0.0 && $penalty > 0.0) {
                $covered = round($penalty * $insuranceCoverage / 100.0, 2);
                $penalty = max(0.0, round($penalty - $covered, 2));
            }

            $this->fts ??= new FinancialTransactionService($this->db);

            // Rzuc wyjatek przy bledzie FTS — storage juz odjete, wiec blad musi cofnac cala transakcje.
            // Throw on FTS failure — storage already deducted, so any error must roll back the whole TX.
            if ($revenue > 0.0) {
                $cr = $this->fts->credit(
                    $playerId,
                    $revenue,
                    FinancialTransactionService::TYPE_CONTRACT_SALE,
                    tPlain('bank.tx_contract_sale', ['id' => $contractId]),
                    'contract',
                    $contractId
                );
                if (!$cr['success']) {
                    throw new \RuntimeException('FTS credit failed: ' . ($cr['error'] ?? 'unknown'));
                }
            }
            if ($penalty > 0.0) {
                $dr = $this->fts->debitCombined(
                    $playerId,
                    $penalty,
                    FinancialTransactionService::TYPE_CONTRACT_PENALTY,
                    tPlain('bank.tx_contract_penalty', ['id' => $contractId]),
                    'contract',
                    $contractId
                );
                if (!$dr['success']) {
                    throw new \RuntimeException('FTS debitCombined failed: ' . ($dr['error'] ?? 'unknown'));
                }
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

            // Reputacja kontraktowa / Contract reputation events.
            $rep = $this->reputation();
            if ($deliveredBbl > 0.0 && $missedBbl <= 0.0) {
                $rep->onDeliverySuccess($playerId, $contractId, $terms);
            } elseif ($deliveredBbl > 0.0 && $missedBbl > 0.0) {
                $rep->onDeliveryPartial($playerId, $contractId, $terms);
            } elseif ($missedBbl > 0.0) {
                $rep->onDeliveryMiss($playerId, $contractId, $terms);
            }
            $totalMissedAfter = round((float)$contract['missed_bbl'] + $missedBbl, 4);
            if ($newStatus === 'completed') {
                $rep->onContractCompleted($playerId, $contractId, $totalMissedAfter <= 0.001, $terms);
            } elseif ($newStatus === 'failed') {
                $rep->onContractFailed($playerId, $contractId, $terms);
            }

            // Bonus za pelna realizacje / Full completion bonus.
            if ($newStatus === 'completed') {
                $bonusPct           = (float)($terms['bonus_on_full_completion_pct']['value'] ?? 0.0);
                $bonusRequiresNoMiss = (int)($terms['bonus_requires_no_miss']['value'] ?? 0) === 1;
                if ($bonusPct > 0.0 && (!$bonusRequiresNoMiss || $totalMissedAfter <= 0.001)) {
                    $bonus = round($totalBbl * $pricePerBbl * $bonusPct / 100.0, 2);
                    if ($bonus > 0.0) {
                        $br = $this->fts->credit(
                            $playerId,
                            $bonus,
                            FinancialTransactionService::TYPE_CONTRACT_BONUS,
                            tPlain('bank.tx_contract_bonus', ['id' => $contractId]),
                            'contract',
                            $contractId
                        );
                        if (!$br['success']) {
                            throw new \RuntimeException('FTS bonus credit failed: ' . ($br['error'] ?? 'unknown'));
                        }
                    }
                }

                // Zwrot kaucji przy realizacji / Refund deposit on completion.
                $depositHeld = round((float)($contract['security_deposit'] ?? 0.0), 2);
                if ($depositHeld > 0.0) {
                    $cr = $this->fts->credit(
                        $playerId,
                        $depositHeld,
                        FinancialTransactionService::TYPE_CONTRACT_DEPOSIT_REFUND,
                        tPlain('bank.tx_contract_deposit_refund', ['id' => $contractId]),
                        'contract',
                        $contractId
                    );
                    if (!$cr['success']) {
                        throw new \RuntimeException('FTS deposit refund failed: ' . ($cr['error'] ?? 'unknown'));
                    }
                }
            }

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
