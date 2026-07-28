<?php
declare(strict_types=1);

// Redeploy 2026-07-08: wymuszenie wgrania pliku na serwer. Deploy commita d34b425
// (metoda getPlayerReputationScore) zostal pominiety przez czerwona bramke testow,
// przez co wersja na produkcji byla nieaktualna (Call to undefined method).
require_once __DIR__ . '/FinancialTransactionService.php';
require_once __DIR__ . '/B2BContracts/B2BContractSchema.php';
require_once __DIR__ . '/HR/StrikeEffectService.php';

/**
 * B2BContractService - player-to-player oil buy offers with escrow.
 * B2BContractService - oferty kupna ropy gracz-gracz z depozytem.
 */
final class B2BContractService
{
    private PDO $db;
    private FinancialTransactionService $fts;
    private string $driver;
    private ?bool $storageHasId = null;
    private ?StrikeEffectService $strikeEffects;

    public function __construct(
        ?PDO $db = null,
        ?FinancialTransactionService $fts = null,
        ?StrikeEffectService $strikeEffects = null
    )
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        B2BContractSchema::ensure($this->db);
        $this->fts = $fts ?? new FinancialTransactionService($this->db);
        $this->driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $this->strikeEffects = $strikeEffects;
    }

    /**
     * Disables coordinator bonuses during a logistics strike and exposes warning data.
     * Wylacza bonusy koordynatora podczas strajku logistyki i wystawia dane ostrzezenia.
     *
     * @param array<string,mixed> $coordinatorEffects
     * @return array{bonus_active:bool,effects:array<string,mixed>,warning:?array{code:string,department_code:string}}
     */
    public function coordinatorContext(int $playerId, array $coordinatorEffects): array
    {
        $this->strikeEffects ??= new StrikeEffectService(
            $this->db,
            new EmployeeSystemConfigService($this->db)
        );
        $strikeActive = isset($this->strikeEffects->forPlayer($playerId)['logistics']);

        return [
            'bonus_active' => !$strikeActive && $coordinatorEffects !== [],
            'effects' => $strikeActive ? [] : $coordinatorEffects,
            'warning' => $strikeActive
                ? ['code' => 'logistics_strike', 'department_code' => 'logistics']
                : null,
        ];
    }

    /**
     * @return array<string,float|int|string>
     */
    public function getConfig(): array
    {
        $defaults = [
            'module_enabled' => 1,
            'min_price_market_pct' => 70,
            'max_price_market_pct' => 130,
            'min_bbl_per_offer' => 100,
            'max_bbl_per_offer' => 50000,
            'max_open_offers_per_player' => 5,
            'default_expiry_minutes' => 1440,
            'min_expiry_minutes' => 60,
            'max_expiry_minutes' => 10080,
            'buyer_cancel_penalty_pct' => 10,
            'admin_review_threshold_value' => 5000000,
            'flag_price_near_limit' => 1,
            'partial_delivery_enabled' => 1,
            'min_first_delivery_pct' => 25,
            'seller_penalty_pct' => 10,
            'delivery_deadline_minutes' => 1440,
            'allow_multiple_deliveries' => 1,
            'auto_finalize_after_deadline' => 1,
        ];

        try {
            $rows = $this->db->query('SELECT config_key, config_value FROM b2b_contract_config')
                ->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($rows ?: [] as $key => $value) {
                if (!array_key_exists((string)$key, $defaults)) {
                    continue;
                }
                $defaults[(string)$key] = is_numeric($value) ? (float)$value : (string)$value;
            }
        } catch (Throwable) {
        }

        return $defaults;
    }

    public function isModuleEnabled(): bool
    {
        return (float)($this->getConfig()['module_enabled'] ?? 0) > 0;
    }

    /**
     * @param array<string,string|int|float> $values
     */
    public function saveConfig(array $values): void
    {
        if (isset($values['min_price_market_pct'], $values['max_price_market_pct'])
            && (float)$values['min_price_market_pct'] > (float)$values['max_price_market_pct']) {
            [$values['min_price_market_pct'], $values['max_price_market_pct']] = [
                $values['max_price_market_pct'],
                $values['min_price_market_pct'],
            ];
        }
        if (isset($values['min_bbl_per_offer'], $values['max_bbl_per_offer'])
            && (float)$values['min_bbl_per_offer'] > (float)$values['max_bbl_per_offer']) {
            [$values['min_bbl_per_offer'], $values['max_bbl_per_offer']] = [
                $values['max_bbl_per_offer'],
                $values['min_bbl_per_offer'],
            ];
        }

        $allowed = array_keys($this->getConfig());
        foreach ($values as $key => $value) {
            if (!in_array((string)$key, $allowed, true)) {
                continue;
            }
            if ($this->driver === 'sqlite') {
                $stmt = $this->db->prepare(
                    "INSERT INTO b2b_contract_config (config_key, config_value, label, updated_at)
                     VALUES (?, ?, '', ?)
                     ON CONFLICT(config_key) DO UPDATE SET config_value = excluded.config_value, updated_at = excluded.updated_at"
                );
                $stmt->execute([(string)$key, (string)$value, $this->now()]);
                continue;
            }
            $stmt = $this->db->prepare(
                "INSERT INTO b2b_contract_config (config_key, config_value, label, updated_at)
                 VALUES (?, ?, '', NOW())
                 ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()"
            );
            $stmt->execute([(string)$key, (string)$value]);
        }
    }

    /**
     * @return array{success:bool,status:string,message_key:string,offer_id?:int,total_value?:float,is_flagged?:bool}
     */
    public function createBuyOffer(
        int $buyerPlayerId,
        float $bbl,
        float $pricePerBbl,
        int $expiresMinutes,
        int $minSellerReputation = 0
    ): array {
        if (!$this->isModuleEnabled()) {
            return $this->result(false, 'disabled', 'contracts.b2b.disabled');
        }

        $cfg = $this->getConfig();
        $bbl = round($bbl, 2);
        $pricePerBbl = round($pricePerBbl, 2);
        if ($bbl < (float)$cfg['min_bbl_per_offer'] || $bbl > (float)$cfg['max_bbl_per_offer']) {
            return $this->result(false, 'invalid_amount', 'contracts.b2b.invalid_amount');
        }
        if ($expiresMinutes < (int)$cfg['min_expiry_minutes'] || $expiresMinutes > (int)$cfg['max_expiry_minutes']) {
            return $this->result(false, 'invalid_expiry', 'contracts.b2b.invalid_expiry');
        }
        if (!$this->playerExists($buyerPlayerId)) {
            return $this->result(false, 'buyer_not_found', 'contracts.b2b.buyer_not_found');
        }
        if ($this->countOpenBuyerOffers($buyerPlayerId) >= (int)$cfg['max_open_offers_per_player']) {
            return $this->result(false, 'open_limit', 'contracts.b2b.open_limit');
        }

        $priceCheck = $this->validatePrice($pricePerBbl, $cfg);
        if (!$priceCheck['success']) {
            return $priceCheck;
        }

        $totalValue = round($bbl * $pricePerBbl, 2);
        $penaltyPct = (float)$cfg['buyer_cancel_penalty_pct'];
        $isFlagged = $this->shouldFlagOffer($totalValue, $pricePerBbl, $cfg);
        $expiresAt = date('Y-m-d H:i:s', time() + ($expiresMinutes * 60));

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO b2b_contract_offers
                    (buyer_player_id, status, total_bbl, price_per_bbl, total_value, escrow_amount,
                     escrow_status, cancel_penalty_pct, min_seller_reputation, partial_delivery_enabled,
                     min_first_delivery_pct, allow_multiple_deliveries, seller_penalty_pct, expires_at,
                     is_flagged, flag_reason, created_at, updated_at)
                 VALUES
                    (?, 'open', ?, ?, ?, ?, 'locked', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $buyerPlayerId,
                $bbl,
                $pricePerBbl,
                $totalValue,
                $totalValue,
                $penaltyPct,
                max(0, $minSellerReputation),
                (int)$cfg['partial_delivery_enabled'],
                (float)$cfg['min_first_delivery_pct'],
                (int)$cfg['allow_multiple_deliveries'],
                (float)$cfg['seller_penalty_pct'],
                $expiresAt,
                $isFlagged ? 1 : 0,
                $isFlagged ? 'auto_review' : null,
                $this->now(),
                $this->now(),
            ]);
            $offerId = (int)$this->db->lastInsertId();

            $debit = $this->fts->debitCombined(
                $buyerPlayerId,
                $totalValue,
                FinancialTransactionService::TYPE_B2B_ESCROW_LOCK,
                'B2B escrow lock',
                'b2b_contract_offer',
                $offerId
            );
            if (empty($debit['success'])) {
                $this->db->rollBack();
                $status = (string)($debit['status'] ?? $debit['error'] ?? 'payment_failed');
                $messageKey = $status === 'insufficient_funds'
                    ? 'contracts.b2b.insufficient_funds'
                    : 'contracts.b2b.payment_failed';
                return $this->result(false, $status, $messageKey);
            }

            $this->logEvent($offerId, $buyerPlayerId, 'created', 'B2B buy offer created', [
                'bbl' => $bbl,
                'price_per_bbl' => $pricePerBbl,
                'total_value' => $totalValue,
            ]);
            $this->db->commit();

            return [
                'success' => true,
                'status' => 'created',
                'message_key' => 'contracts.b2b.created',
                'offer_id' => $offerId,
                'total_value' => $totalValue,
                'is_flagged' => $isFlagged,
            ];
        } catch (Throwable $e) {
            $this->safeRollback();
            $this->logFailure('createBuyOffer', $e);
            return $this->result(false, 'db_error', 'contracts.b2b.db_error');
        }
    }

    /**
     * @return array{success:bool,status:string,message_key:string,refund_amount?:float,penalty_amount?:float}
     */
    public function cancelBuyOffer(int $buyerPlayerId, int $offerId, string $reason = ''): array
    {
        $offer = $this->lockOffer($offerId);
        if ($offer === null) {
            return $this->result(false, 'not_found', 'contracts.b2b.not_found');
        }
        if ((int)$offer['buyer_player_id'] !== $buyerPlayerId) {
            return $this->result(false, 'forbidden', 'contracts.b2b.forbidden');
        }
        if ((string)$offer['status'] !== 'open') {
            return $this->result(false, 'not_open', 'contracts.b2b.not_open');
        }

        $escrow = (float)$offer['escrow_amount'];
        $penalty = round($escrow * ((float)$offer['cancel_penalty_pct'] / 100), 2);
        $refund = max(0.0, round($escrow - $penalty, 2));

        $this->db->beginTransaction();
        try {
            $offer = $this->lockOffer($offerId);
            if (
                $offer === null
                || (int)$offer['buyer_player_id'] !== $buyerPlayerId
                || (string)$offer['status'] !== 'open'
            ) {
                $this->db->rollBack();
                return $this->result(false, 'not_open', 'contracts.b2b.not_open');
            }

            if ($refund > 0) {
                $credit = $this->fts->credit(
                    $buyerPlayerId,
                    $refund,
                    FinancialTransactionService::TYPE_B2B_ESCROW_REFUND,
                    'B2B escrow refund after buyer cancellation',
                    'b2b_contract_offer',
                    $offerId
                );
                if (empty($credit['success'])) {
                    $this->db->rollBack();
                    return $this->result(false, 'refund_failed', 'contracts.b2b.refund_failed');
                }
            }
            if ($penalty > 0) {
                $this->fts->logTransaction(
                    $buyerPlayerId,
                    null,
                    $penalty,
                    FinancialTransactionService::TYPE_B2B_CANCEL_PENALTY,
                    'B2B cancellation penalty retained from escrow',
                    'b2b_contract_offer',
                    $offerId
                );
            }

            $stmt = $this->db->prepare(
                "UPDATE b2b_contract_offers
                 SET status = 'cancelled', escrow_status = 'partial_refund', cancel_penalty_amount = ?,
                     refunded_amount = ?, cancelled_at = ?, cancel_reason = ?, updated_at = ?
                 WHERE id = ?
                   AND buyer_player_id = ?
                   AND status = 'open'"
            );
            $stmt->execute([
                $penalty,
                $refund,
                $this->now(),
                $reason,
                $this->now(),
                $offerId,
                $buyerPlayerId,
            ]);
            if ($stmt->rowCount() !== 1) {
                $this->db->rollBack();
                return $this->result(false, 'race_lost', 'contracts.b2b.race_lost');
            }
            $this->logEvent($offerId, $buyerPlayerId, 'cancelled', 'B2B buy offer cancelled by buyer', [
                'refund_amount' => $refund,
                'penalty_amount' => $penalty,
            ]);
            $this->recordReputation($buyerPlayerId, $offerId, 'buyer_cancelled', -3, [
                'buy_cancelled' => 1,
            ]);
            $this->db->commit();

            return [
                'success' => true,
                'status' => 'cancelled',
                'message_key' => 'contracts.b2b.cancelled',
                'refund_amount' => $refund,
                'penalty_amount' => $penalty,
            ];
        } catch (Throwable $e) {
            $this->safeRollback();
            $this->logFailure('cancelBuyOffer', $e);
            return $this->result(false, 'db_error', 'contracts.b2b.db_error');
        }
    }

    /**
     * Backward-compatible wrapper — delivers the full amount at once.
     * @return array{success:bool,status:string,message_key:string,total_value?:float}
     */
    public function acceptAndDeliver(int $sellerPlayerId, int $offerId): array
    {
        $offer = $this->offerById($offerId);
        if ($offer === null) {
            return $this->result(false, 'not_found', 'contracts.b2b.not_found');
        }
        return $this->acceptOffer($sellerPlayerId, $offerId, (float)$offer['total_bbl']);
    }

    /**
     * @return array{success:bool,status:string,message_key:string,first_delivery_bbl?:float,remaining_bbl?:float,revenue?:float,min_first_bbl?:float}
     */
    public function acceptOffer(int $sellerPlayerId, int $offerId, float $firstDeliveryBbl): array
    {
        if (!$this->isModuleEnabled()) {
            return $this->result(false, 'disabled', 'contracts.b2b.disabled');
        }
        if (!$this->playerExists($sellerPlayerId)) {
            return $this->result(false, 'seller_not_found', 'contracts.b2b.seller_not_found');
        }

        $cfg = $this->getConfig();
        $firstDeliveryBbl = round(max(0.0, $firstDeliveryBbl), 2);

        $this->db->beginTransaction();
        try {
            $offer = $this->lockOffer($offerId);
            if ($offer === null) {
                $this->db->rollBack();
                return $this->result(false, 'not_found', 'contracts.b2b.not_found');
            }
            if ((string)$offer['status'] !== 'open') {
                $this->db->rollBack();
                return $this->result(false, 'not_open', 'contracts.b2b.not_open');
            }
            if ((int)$offer['buyer_player_id'] === $sellerPlayerId) {
                $this->db->rollBack();
                return $this->result(false, 'own_offer', 'contracts.b2b.own_offer');
            }
            if (strtotime((string)$offer['expires_at']) <= time()) {
                $this->db->rollBack();
                return $this->result(false, 'expired', 'contracts.b2b.expired');
            }

            $totalBbl = (float)$offer['total_bbl'];
            $partialEnabled = (int)($offer['partial_delivery_enabled'] ?? $cfg['partial_delivery_enabled']) > 0;
            $minFirstPct = (float)($offer['min_first_delivery_pct'] ?? $cfg['min_first_delivery_pct']);
            $minFirstBbl = $partialEnabled ? round($totalBbl * ($minFirstPct / 100), 2) : $totalBbl;

            if (!$partialEnabled && $firstDeliveryBbl < $totalBbl - 1e-9) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'status' => 'full_delivery_required',
                    'message_key' => 'contracts.b2b.full_delivery_required',
                    'min_first_bbl' => $totalBbl,
                ];
            }

            if ($firstDeliveryBbl < $minFirstBbl - 1e-9) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'status' => 'below_min_first_delivery',
                    'message_key' => 'contracts.b2b.below_min_first_delivery',
                    'min_first_bbl' => $minFirstBbl,
                ];
            }

            // Cap to total_bbl
            $firstDeliveryBbl = min($firstDeliveryBbl, $totalBbl);

            if ($this->availableOil($sellerPlayerId) + 1e-9 < $firstDeliveryBbl) {
                $this->db->rollBack();
                return $this->result(false, 'insufficient_oil', 'contracts.b2b.insufficient_oil');
            }

            $deadlineMinutes = max(1, (int)$cfg['delivery_deadline_minutes']);
            $deadlineAt = date('Y-m-d H:i:s', time() + ($deadlineMinutes * 60));

            $deliveryResult = $this->executeDelivery($offerId, $offer, $sellerPlayerId, $firstDeliveryBbl);
            if (!$deliveryResult['success']) {
                $this->db->rollBack();
                return $this->result(false, $deliveryResult['status'], 'contracts.b2b.' . $deliveryResult['status']);
            }

            $remainingBbl = $deliveryResult['remaining_bbl'];
            $newStatus = $remainingBbl <= 1e-9 ? 'completed' : 'accepted';

            $stmt = $this->db->prepare(
                "UPDATE b2b_contract_offers
                 SET seller_player_id = ?,
                     status = ?,
                     accepted_at = ?,
                     delivery_deadline_at = ?,
                     partial_delivery_enabled = ?,
                     min_first_delivery_pct = ?,
                     allow_multiple_deliveries = ?,
                     seller_penalty_pct = ?,
                     updated_at = ?
                 WHERE id = ?
                   AND buyer_player_id = ?
                   AND status = 'open'"
            );
            $stmt->execute([
                $sellerPlayerId,
                $newStatus,
                $this->now(),
                $deadlineAt,
                $partialEnabled ? 1 : 0,
                $minFirstPct,
                (int)($offer['allow_multiple_deliveries'] ?? $cfg['allow_multiple_deliveries']),
                (float)($offer['seller_penalty_pct'] ?? $cfg['seller_penalty_pct']),
                $this->now(),
                $offerId,
                (int)$offer['buyer_player_id'],
            ]);
            if ($stmt->rowCount() !== 1) {
                $this->db->rollBack();
                return $this->result(false, 'race_lost', 'contracts.b2b.race_lost');
            }

            if ($newStatus === 'completed') {
                $completion = $this->db->prepare(
                    "UPDATE b2b_contract_offers
                     SET completed_at = ?,
                         escrow_status = 'released',
                         released_amount = escrow_amount,
                         remaining_escrow_amount = 0
                     WHERE id = ?
                       AND buyer_player_id = ?
                       AND seller_player_id = ?"
                );
                $completion->execute([
                    $this->now(),
                    $offerId,
                    (int)$offer['buyer_player_id'],
                    $sellerPlayerId,
                ]);
                if ($completion->rowCount() !== 1) {
                    $this->db->rollBack();
                    return $this->result(false, 'race_lost', 'contracts.b2b.race_lost');
                }
                $this->recordReputation((int)$offer['buyer_player_id'], $offerId, 'buyer_completed', 1, [
                    'buy_completed' => 1, 'total_bought_bbl' => $totalBbl,
                ]);
                $this->recordReputation($sellerPlayerId, $offerId, 'seller_completed', 3, [
                    'sell_completed' => 1, 'total_sold_bbl' => $totalBbl,
                ]);
                $this->logEvent($offerId, $sellerPlayerId, 'offer_completed', 'B2B offer fully delivered on acceptance', [
                    'total_bbl' => $totalBbl,
                    'total_value' => $deliveryResult['revenue'],
                ]);
            }

            $this->logEvent($offerId, $sellerPlayerId, 'offer_accepted', 'B2B offer accepted', [
                'seller_player_id' => $sellerPlayerId,
                'first_delivery_bbl' => $firstDeliveryBbl,
                'remaining_bbl' => $remainingBbl,
                'deadline_at' => $deadlineAt,
                'status' => $newStatus,
            ]);
            $this->db->commit();

            return [
                'success' => true,
                'status' => $newStatus,
                'message_key' => $newStatus === 'completed' ? 'contracts.b2b.completed' : 'contracts.b2b.accepted',
                'first_delivery_bbl' => $firstDeliveryBbl,
                'remaining_bbl' => $remainingBbl,
                'revenue' => $deliveryResult['revenue'],
            ];
        } catch (Throwable $e) {
            $this->safeRollback();
            $this->logFailure('acceptOffer', $e);
            return $this->result(false, 'db_error', 'contracts.b2b.db_error');
        }
    }

    /**
     * @return array{success:bool,status:string,message_key:string,delivered_bbl?:float,revenue?:float,remaining_bbl?:float}
     */
    public function deliverPartial(int $sellerPlayerId, int $offerId, float $bbl): array
    {
        if (!$this->isModuleEnabled()) {
            return $this->result(false, 'disabled', 'contracts.b2b.disabled');
        }
        if (!$this->playerExists($sellerPlayerId)) {
            return $this->result(false, 'seller_not_found', 'contracts.b2b.seller_not_found');
        }

        $bbl = round(max(0.0, $bbl), 2);
        if ($bbl <= 1e-9) {
            return $this->result(false, 'invalid_amount', 'contracts.b2b.invalid_amount');
        }

        $this->db->beginTransaction();
        try {
            $offer = $this->lockOffer($offerId);
            if ($offer === null) {
                $this->db->rollBack();
                return $this->result(false, 'not_found', 'contracts.b2b.not_found');
            }
            if ((string)$offer['status'] !== 'accepted') {
                $this->db->rollBack();
                return $this->result(false, 'not_accepted', 'contracts.b2b.not_accepted');
            }
            if ((int)$offer['seller_player_id'] !== $sellerPlayerId) {
                $this->db->rollBack();
                return $this->result(false, 'forbidden', 'contracts.b2b.forbidden');
            }
            if ((int)($offer['partial_delivery_enabled'] ?? 1) <= 0) {
                $this->db->rollBack();
                return $this->result(false, 'full_delivery_required', 'contracts.b2b.full_delivery_required');
            }

            $deadline = (string)($offer['delivery_deadline_at'] ?? '');
            if ($deadline !== '' && strtotime($deadline) <= time()) {
                $this->db->rollBack();
                return $this->result(false, 'deadline_passed', 'contracts.b2b.deadline_passed');
            }

            $totalBbl = (float)$offer['total_bbl'];
            $deliveredSoFar = (float)$offer['delivered_bbl'];
            $remainingBbl = round($totalBbl - $deliveredSoFar, 2);

            if ($remainingBbl <= 1e-9) {
                $this->db->rollBack();
                return $this->result(false, 'already_completed', 'contracts.b2b.already_completed');
            }

            // Cap to what's still owed
            $bbl = min($bbl, $remainingBbl);

            if ((int)($offer['allow_multiple_deliveries'] ?? 1) <= 0 && $bbl < $remainingBbl - 1e-9) {
                $this->db->rollBack();
                return $this->result(false, 'final_delivery_required', 'contracts.b2b.final_delivery_required');
            }

            if ($this->availableOil($sellerPlayerId) + 1e-9 < $bbl) {
                $this->db->rollBack();
                return $this->result(false, 'insufficient_oil', 'contracts.b2b.insufficient_oil');
            }

            $deliveryResult = $this->executeDelivery($offerId, $offer, $sellerPlayerId, $bbl);
            if (!$deliveryResult['success']) {
                $this->db->rollBack();
                return $this->result(false, $deliveryResult['status'], 'contracts.b2b.' . $deliveryResult['status']);
            }

            $newRemaining = $deliveryResult['remaining_bbl'];
            $newStatus = $newRemaining <= 1e-9 ? 'completed' : 'accepted';

            if ($newStatus === 'completed') {
                $stmt = $this->db->prepare(
                    "UPDATE b2b_contract_offers
                        SET status = 'completed',
                            updated_at = ?
                      WHERE id = ?
                        AND seller_player_id = ?
                        AND status = 'accepted'"
                );
                $stmt->execute([$this->now(), $offerId, $sellerPlayerId]);
                if ($stmt->rowCount() !== 1) {
                    $this->db->rollBack();
                    return $this->result(false, 'race_lost', 'contracts.b2b.race_lost');
                }
            }

            if ($newStatus === 'completed') {
                $completion = $this->db->prepare(
                    "UPDATE b2b_contract_offers
                     SET completed_at = ?,
                         escrow_status = 'released',
                         released_amount = escrow_amount,
                         remaining_escrow_amount = 0
                     WHERE id = ?
                       AND buyer_player_id = ?
                       AND seller_player_id = ?"
                );
                $completion->execute([
                    $this->now(),
                    $offerId,
                    (int)$offer['buyer_player_id'],
                    $sellerPlayerId,
                ]);
                if ($completion->rowCount() !== 1) {
                    $this->db->rollBack();
                    return $this->result(false, 'race_lost', 'contracts.b2b.race_lost');
                }
                $this->recordReputation((int)$offer['buyer_player_id'], $offerId, 'buyer_completed', 1, [
                    'buy_completed' => 1, 'total_bought_bbl' => $totalBbl,
                ]);
                $this->recordReputation($sellerPlayerId, $offerId, 'seller_completed', 3, [
                    'sell_completed' => 1, 'total_sold_bbl' => $totalBbl,
                ]);
                $this->logEvent($offerId, $sellerPlayerId, 'offer_completed', 'B2B offer fully delivered', [
                    'total_bbl' => $totalBbl,
                ]);
            }

            $this->logEvent($offerId, $sellerPlayerId, 'partial_delivery_made', 'B2B partial delivery', [
                'bbl' => $bbl,
                'revenue' => $deliveryResult['revenue'],
                'remaining_bbl' => $newRemaining,
                'status' => $newStatus,
            ]);
            $this->logEvent($offerId, $sellerPlayerId, 'partial_payment_released', 'B2B partial payment released', [
                'amount' => $deliveryResult['revenue'],
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'status' => $newStatus,
                'message_key' => $newStatus === 'completed' ? 'contracts.b2b.completed' : 'contracts.b2b.partial_delivered',
                'delivered_bbl' => $bbl,
                'revenue' => $deliveryResult['revenue'],
                'remaining_bbl' => $newRemaining,
            ];
        } catch (Throwable $e) {
            $this->safeRollback();
            $this->logFailure('deliverPartial', $e);
            return $this->result(false, 'db_error', 'contracts.b2b.db_error');
        }
    }

    /**
     * @return array{success:bool,status:string,message_key:string,delivered_bbl?:float,missing_bbl?:float,refund_amount?:float,penalty_amount?:float}
     */
    public function finalizeAcceptedOffer(int $offerId, ?DateTimeInterface $now = null, bool $force = false): array
    {
        $nowSql = $now ? $now->format('Y-m-d H:i:s') : $this->now();

        $this->db->beginTransaction();
        try {
            $offer = $this->lockOffer($offerId);
            if ($offer === null) {
                $this->db->rollBack();
                return $this->result(false, 'not_found', 'contracts.b2b.not_found');
            }
            if ((string)$offer['status'] !== 'accepted') {
                $this->db->rollBack();
                return $this->result(false, 'not_accepted', 'contracts.b2b.not_accepted');
            }

            $deadline = (string)($offer['delivery_deadline_at'] ?? '');
            if (!$force && $deadline !== '' && strtotime($deadline) > strtotime($nowSql)) {
                $this->db->rollBack();
                return $this->result(false, 'deadline_not_passed', 'contracts.b2b.deadline_not_passed');
            }

            $totalBbl = (float)$offer['total_bbl'];
            $deliveredBbl = (float)$offer['delivered_bbl'];
            $missingBbl = round($totalBbl - $deliveredBbl, 2);

            $totalEscrow = (float)$offer['escrow_amount'];
            $releasedSoFar = (float)$offer['released_amount'];
            $remainingEscrow = max(0.0, round($totalEscrow - $releasedSoFar, 2));

            $penaltyPct = max(0.0, (float)$offer['seller_penalty_pct']);
            $missingValue = round($missingBbl * (float)$offer['price_per_bbl'], 2);
            $penaltyAmount = round($missingValue * ($penaltyPct / 100), 2);

            $buyerPlayerId = (int)$offer['buyer_player_id'];
            $sellerPlayerId = (int)$offer['seller_player_id'];

            if ($remainingEscrow > 0) {
                $credit = $this->fts->credit(
                    $buyerPlayerId,
                    $remainingEscrow,
                    FinancialTransactionService::TYPE_B2B_ESCROW_REFUND,
                    'B2B remaining escrow refund after deadline',
                    'b2b_contract_offer',
                    $offerId
                );
                if (empty($credit['success'])) {
                    $this->db->rollBack();
                    return $this->result(false, 'refund_failed', 'contracts.b2b.refund_failed');
                }
            }

            if ($penaltyAmount > 0 && $sellerPlayerId > 0) {
                $debit = $this->fts->debitCombined(
                    $sellerPlayerId,
                    $penaltyAmount,
                    FinancialTransactionService::TYPE_B2B_CANCEL_PENALTY,
                    'B2B seller penalty for undelivered oil',
                    'b2b_contract_offer',
                    $offerId
                );
                if (empty($debit['success'])) {
                    // Log but do not block finalization
                    $penaltyAmount = 0.0;
                    $this->logEvent($offerId, $sellerPlayerId, 'seller_penalty_skipped',
                        'Seller penalty skipped: insufficient funds', [
                        'attempted' => round($missingValue * ($penaltyPct / 100), 2),
                    ]);
                }
            }

            $newStatus = $deliveredBbl <= 1e-9 ? 'failed' : 'partial_done';

            $statusUpdate = $this->db->prepare(
                "UPDATE b2b_contract_offers
                 SET status = ?,
                     remaining_bbl = 0,
                     remaining_escrow_amount = 0,
                     seller_penalty_amount = ?,
                     escrow_status = 'refunded',
                     updated_at = ?
                 WHERE id = ?
                   AND buyer_player_id = ?
                   AND seller_player_id = ?
                   AND status = 'accepted'"
            );
            $statusUpdate->execute([
                $newStatus,
                $penaltyAmount,
                $nowSql,
                $offerId,
                $buyerPlayerId,
                $sellerPlayerId,
            ]);
            if ($statusUpdate->rowCount() !== 1) {
                $this->db->rollBack();
                return $this->result(false, 'race_lost', 'contracts.b2b.race_lost');
            }

            if ($sellerPlayerId > 0) {
                $this->recordReputation($sellerPlayerId, $offerId, 'seller_penalty', -3, []);
            }

            if ($remainingEscrow > 0) {
                $this->logEvent($offerId, $buyerPlayerId, 'remaining_escrow_refunded',
                    'Remaining escrow refunded to buyer', ['amount' => $remainingEscrow]);
            }
            if ($penaltyAmount > 0) {
                $this->logEvent($offerId, $sellerPlayerId, 'seller_penalty_charged',
                    'Seller penalty charged', [
                    'missing_bbl' => $missingBbl,
                    'penalty_amount' => $penaltyAmount,
                    'penalty_pct' => $penaltyPct,
                ]);
            }
            $eventKey = $newStatus === 'failed' ? 'offer_failed' : 'offer_partially_completed';
            $this->logEvent($offerId, null, $eventKey, "B2B offer finalized: {$newStatus}", [
                'delivered_bbl' => $deliveredBbl,
                'missing_bbl' => $missingBbl,
                'refunded' => $remainingEscrow,
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'status' => $newStatus,
                'message_key' => 'contracts.b2b.' . $newStatus,
                'delivered_bbl' => $deliveredBbl,
                'missing_bbl' => $missingBbl,
                'refund_amount' => $remainingEscrow,
                'penalty_amount' => $penaltyAmount,
            ];
        } catch (Throwable $e) {
            $this->safeRollback();
            $this->logFailure('finalizeAcceptedOffer', $e);
            return $this->result(false, 'db_error', 'contracts.b2b.db_error');
        }
    }

    /**
     * @return array{processed:int,finalized:int,partial_done:int,failed:int,penalties:float}
     */
    public function finalizeExpiredAcceptedOffers(?DateTimeInterface $now = null, int $limit = 0): array
    {
        if ((float)($this->getConfig()['auto_finalize_after_deadline'] ?? 1) <= 0) {
            return [
                'processed' => 0,
                'finalized' => 0,
                'partial_done' => 0,
                'failed' => 0,
                'penalties' => 0.0,
            ];
        }

        $nowSql = $now ? $now->format('Y-m-d H:i:s') : $this->now();
        $limitSql = $this->batchLimitSql($limit);
        $stmt = $this->db->prepare(
            "SELECT id FROM b2b_contract_offers
             WHERE status = 'accepted' AND delivery_deadline_at IS NOT NULL AND delivery_deadline_at <= ?
             ORDER BY id ASC{$limitSql}"
        );
        $stmt->execute([$nowSql]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];

        $finalized = 0;
        $partialDone = 0;
        $failed = 0;
        $penalties = 0.0;
        foreach ($ids as $offerId) {
            $result = $this->finalizeAcceptedOffer((int)$offerId, $now);
            if (!empty($result['success'])) {
                $finalized++;
                $status = (string)($result['status'] ?? '');
                if ($status === 'partial_done') {
                    $partialDone++;
                } elseif ($status === 'failed') {
                    $failed++;
                }
                $penalties += (float)($result['penalty_amount'] ?? 0.0);
            }
        }

        return [
            'processed' => count($ids),
            'finalized' => $finalized,
            'partial_done' => $partialDone,
            'failed' => $failed,
            'penalties' => round($penalties, 2),
        ];
    }

    /**
     * @return array{success:bool,status:string,message_key:string}
     */
    public function sellerAbandonOffer(int $sellerPlayerId, int $offerId, string $reason = ''): array
    {
        $offer = $this->offerById($offerId);
        if ($offer === null) {
            return $this->result(false, 'not_found', 'contracts.b2b.not_found');
        }
        if ((string)$offer['status'] !== 'accepted') {
            return $this->result(false, 'not_accepted', 'contracts.b2b.not_accepted');
        }
        if ((int)$offer['seller_player_id'] !== $sellerPlayerId) {
            return $this->result(false, 'forbidden', 'contracts.b2b.forbidden');
        }

        $result = $this->finalizeAcceptedOffer($offerId, null, true);

        if (!empty($result['success'])) {
            try {
                $this->logEvent($offerId, $sellerPlayerId, 'seller_abandoned_offer', 'Seller abandoned the offer', [
                    'reason' => $reason,
                ]);
            } catch (Throwable) {}
        }

        return $result;
    }

    /**
     * @return array{processed:int,expired:int,refunded:float}
     */
    public function expireOpenOffers(?DateTimeInterface $now = null, int $limit = 0): array
    {
        $nowSql = $now ? $now->format('Y-m-d H:i:s') : $this->now();
        $limitSql = $this->batchLimitSql($limit);
        $stmt = $this->db->prepare("SELECT * FROM b2b_contract_offers WHERE status = 'open' AND expires_at <= ? ORDER BY id ASC{$limitSql}");
        $stmt->execute([$nowSql]);
        $offers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $expired = 0;
        $refunded = 0.0;
        foreach ($offers as $offer) {
            $result = $this->expireSingleOffer((int)$offer['id']);
            if (!empty($result['success'])) {
                $expired++;
                $refunded += (float)($result['refund_amount'] ?? 0.0);
            }
        }

        return ['processed' => count($offers), 'expired' => $expired, 'refunded' => round($refunded, 2)];
    }

    /**
     * @return array{success:bool,status:string,message_key:string,refund_amount?:float}
     */
    public function adminCancelOffer(int $adminId, int $offerId, string $reason): array
    {
        $this->db->beginTransaction();
        try {
            $offer = $this->lockOffer($offerId);
            if ($offer === null) {
                $this->db->rollBack();
                return $this->result(false, 'not_found', 'contracts.b2b.not_found');
            }
            if ((string)$offer['status'] !== 'open') {
                $this->db->rollBack();
                return $this->result(false, 'not_open', 'contracts.b2b.not_open');
            }
            $refund = (float)$offer['escrow_amount'];
            if ($refund > 0) {
                $credit = $this->fts->credit(
                    (int)$offer['buyer_player_id'],
                    $refund,
                    FinancialTransactionService::TYPE_B2B_ESCROW_REFUND,
                    'B2B escrow refund after admin cancellation',
                    'b2b_contract_offer',
                    $offerId
                );
                if (empty($credit['success'])) {
                    $this->db->rollBack();
                    return $this->result(false, 'refund_failed', 'contracts.b2b.refund_failed');
                }
            }
            $stmt = $this->db->prepare(
                "UPDATE b2b_contract_offers
                 SET status = 'cancelled', escrow_status = 'refunded', refunded_amount = ?,
                     cancelled_at = ?, cancel_reason = ?, updated_at = ?
                 WHERE id = ?
                   AND buyer_player_id = ?
                   AND status = 'open'"
            );
            $stmt->execute([
                $refund,
                $this->now(),
                $reason,
                $this->now(),
                $offerId,
                (int)$offer['buyer_player_id'],
            ]);
            if ($stmt->rowCount() !== 1) {
                $this->db->rollBack();
                return $this->result(false, 'race_lost', 'contracts.b2b.race_lost');
            }
            $this->logEvent($offerId, null, 'admin_cancelled', 'B2B offer cancelled by admin', [
                'admin_id' => $adminId,
                'reason' => $reason,
            ]);
            $this->recordReputation((int)$offer['buyer_player_id'], $offerId, 'admin_cancelled', -2, [
                'admin_cancellations' => 1,
            ]);
            $this->db->commit();

            return [
                'success' => true,
                'status' => 'admin_cancelled',
                'message_key' => 'contracts.b2b.admin_cancelled',
                'refund_amount' => $refund,
            ];
        } catch (Throwable $e) {
            $this->safeRollback();
            $this->logFailure('adminCancelOffer', $e);
            return $this->result(false, 'db_error', 'contracts.b2b.db_error');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function adminFlagOffer(int $adminId, int $offerId, string $reason): array
    {
        $offer = $this->offerById($offerId);
        $stmt = $this->db->prepare('UPDATE b2b_contract_offers SET is_flagged = 1, flag_reason = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$reason, $this->now(), $offerId]);
        if ($stmt->rowCount() < 1) {
            return $this->result(false, 'not_found', 'contracts.b2b.not_found');
        }
        $this->logEvent($offerId, null, 'admin_flagged', 'B2B offer flagged by admin', [
            'admin_id' => $adminId,
            'reason' => $reason,
        ]);
        if ($offer !== null) {
            $this->recordReputation((int)$offer['buyer_player_id'], $offerId, 'admin_flagged', -1, [
                'admin_flags' => 1,
            ]);
        }
        return $this->result(true, 'flagged', 'contracts.b2b.flagged');
    }

    /**
     * @return array<string, mixed>
     */
    public function adminUnflagOffer(int $adminId, int $offerId): array
    {
        $stmt = $this->db->prepare('UPDATE b2b_contract_offers SET is_flagged = 0, flag_reason = NULL, updated_at = ? WHERE id = ?');
        $stmt->execute([$this->now(), $offerId]);
        if ($stmt->rowCount() < 1) {
            return $this->result(false, 'not_found', 'contracts.b2b.not_found');
        }
        $this->logEvent($offerId, null, 'admin_unflagged', 'B2B offer unflagged by admin', ['admin_id' => $adminId]);
        return $this->result(true, 'unflagged', 'contracts.b2b.unflagged');
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listOpenOffers(int $viewerPlayerId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            "SELECT o.*, COALESCE(pb.company_name, pb.username, 'Buyer') AS buyer_name
             FROM b2b_contract_offers o
             LEFT JOIN players pb ON pb.id = o.buyer_player_id
             WHERE o.status = 'open' AND o.buyer_player_id <> ? AND o.expires_at > ?
             ORDER BY o.created_at DESC, o.id DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, $viewerPlayerId, PDO::PARAM_INT);
        $stmt->bindValue(2, $this->now());
        $stmt->bindValue(3, max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->bindValue(4, max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countOpenOffers(int $viewerPlayerId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM b2b_contract_offers WHERE status = 'open' AND buyer_player_id <> ? AND expires_at > ?");
        $stmt->execute([$viewerPlayerId, $this->now()]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listMyBuyOffers(int $playerId, int $limit = 50, int $offset = 0): array
    {
        return $this->listByPlayerColumn('buyer_player_id', $playerId, $limit, $offset);
    }

    public function countMyBuyOffers(int $playerId): int
    {
        return $this->countByPlayerColumn('buyer_player_id', $playerId);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listMySales(int $playerId, int $limit = 50, int $offset = 0): array
    {
        return $this->listByPlayerColumn('seller_player_id', $playerId, $limit, $offset);
    }

    public function countMySales(int $playerId): int
    {
        return $this->countByPlayerColumn('seller_player_id', $playerId);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listPlayerHistory(int $playerId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            "SELECT o.*,
                    COALESCE(pb.company_name, pb.username, 'Buyer') AS buyer_name,
                    COALESCE(ps.company_name, ps.username, '') AS seller_name
             FROM b2b_contract_offers o
             LEFT JOIN players pb ON pb.id = o.buyer_player_id
             LEFT JOIN players ps ON ps.id = o.seller_player_id
             WHERE (o.buyer_player_id = ? OR o.seller_player_id = ?)
               AND o.status <> 'open'
             ORDER BY COALESCE(o.completed_at, o.cancelled_at, o.updated_at, o.created_at) DESC, o.id DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, $playerId, PDO::PARAM_INT);
        $stmt->bindValue(2, $playerId, PDO::PARAM_INT);
        $stmt->bindValue(3, max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->bindValue(4, max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countPlayerHistory(int $playerId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM b2b_contract_offers
             WHERE (buyer_player_id = ? OR seller_player_id = ?)
               AND status <> 'open'"
        );
        $stmt->execute([$playerId, $playerId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listLogs(?int $offerId = null, int $limit = 100, int $offset = 0): array
    {
        if ($offerId !== null) {
            $stmt = $this->db->prepare('SELECT * FROM b2b_contract_logs WHERE offer_id = ? ORDER BY id DESC LIMIT ? OFFSET ?');
            $stmt->bindValue(1, $offerId, PDO::PARAM_INT);
            $stmt->bindValue(2, max(1, min(200, $limit)), PDO::PARAM_INT);
            $stmt->bindValue(3, max(0, $offset), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        $stmt = $this->db->prepare('SELECT * FROM b2b_contract_logs ORDER BY id DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->bindValue(2, max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listPlayerLogs(int $playerId, int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            "SELECT l.*
             FROM b2b_contract_logs l
             JOIN b2b_contract_offers o ON o.id = l.offer_id
             WHERE o.buyer_player_id = ? OR o.seller_player_id = ?
             ORDER BY l.id DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, $playerId, PDO::PARAM_INT);
        $stmt->bindValue(2, $playerId, PDO::PARAM_INT);
        $stmt->bindValue(3, max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->bindValue(4, max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countPlayerLogs(int $playerId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM b2b_contract_logs l
             JOIN b2b_contract_offers o ON o.id = l.offer_id
             WHERE o.buyer_player_id = ? OR o.seller_player_id = ?"
        );
        $stmt->execute([$playerId, $playerId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * @param array{status?:string,query?:string,flagged?:string} $filters
     * @return list<array<string,mixed>>
     */
    public function listAdminOffers(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = $this->adminOfferWhere($filters);
        $stmt = $this->db->prepare(
            "SELECT o.*,
                    COALESCE(pb.company_name, pb.username, '') AS buyer_name,
                    COALESCE(ps.company_name, ps.username, '') AS seller_name,
                    COALESCE(rb.score, 50) AS buyer_b2b_score,
                    COALESCE(rs.score, 50) AS seller_b2b_score
             FROM b2b_contract_offers o
             LEFT JOIN players pb ON pb.id = o.buyer_player_id
             LEFT JOIN players ps ON ps.id = o.seller_player_id
             LEFT JOIN b2b_reputation_scores rb ON rb.player_id = o.buyer_player_id
             LEFT JOIN b2b_reputation_scores rs ON rs.player_id = o.seller_player_id
             {$where}
             ORDER BY o.id DESC
             LIMIT ? OFFSET ?"
        );
        $idx = 1;
        foreach ($params as $param) {
            $stmt->bindValue($idx++, $param);
        }
        $stmt->bindValue($idx++, max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->bindValue($idx, max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array{status?:string,query?:string,flagged?:string} $filters */
    public function countAdminOffers(array $filters = []): int
    {
        [$where, $params] = $this->adminOfferWhere($filters);
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM b2b_contract_offers o
             LEFT JOIN players pb ON pb.id = o.buyer_player_id
             LEFT JOIN players ps ON ps.id = o.seller_player_id
             {$where}"
        );
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listAdminLogs(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            "SELECT l.*, COALESCE(p.company_name, p.username, '') AS player_name
             FROM b2b_contract_logs l
             LEFT JOIN players p ON p.id = l.player_id
             ORDER BY l.id DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->bindValue(2, max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countAdminLogs(): int
    {
        return (int)$this->db->query('SELECT COUNT(*) FROM b2b_contract_logs')->fetchColumn();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listReputationScores(string $query = '', int $limit = 50, int $offset = 0): array
    {
        $where = '';
        $params = [];
        $query = trim($query);
        if ($query !== '') {
            $where = 'WHERE p.username LIKE ? OR p.company_name LIKE ?';
            $like = '%' . $query . '%';
            $params = [$like, $like];
        }
        $stmt = $this->db->prepare(
            "SELECT r.*, p.username, p.company_name
             FROM b2b_reputation_scores r
             LEFT JOIN players p ON p.id = r.player_id
             {$where}
             ORDER BY r.score ASC, r.updated_at DESC, r.player_id ASC
             LIMIT ? OFFSET ?"
        );
        $idx = 1;
        foreach ($params as $param) {
            $stmt->bindValue($idx++, $param);
        }
        $stmt->bindValue($idx++, max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->bindValue($idx, max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countReputationScores(string $query = ''): int
    {
        $query = trim($query);
        if ($query === '') {
            return (int)$this->db->query('SELECT COUNT(*) FROM b2b_reputation_scores')->fetchColumn();
        }
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM b2b_reputation_scores r
             LEFT JOIN players p ON p.id = r.player_id
             WHERE p.username LIKE ? OR p.company_name LIKE ?"
        );
        $like = '%' . $query . '%';
        $stmt->execute([$like, $like]);
        return (int)$stmt->fetchColumn();
    }

    public function getPlayerReputationScore(int $playerId): int
    {
        if ($playerId <= 0) {
            return 50;
        }
        $this->ensureReputationRow($playerId);
        $stmt = $this->db->prepare('SELECT score FROM b2b_reputation_scores WHERE player_id = ?');
        $stmt->execute([$playerId]);
        $score = $stmt->fetchColumn();
        return max(0, min(100, $score === false ? 50 : (int)$score));
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string,mixed>>
     */
    public function listAdminDeliveries(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            "SELECT d.*,
                    COALESCE(pb.company_name, pb.username, '') AS buyer_name,
                    COALESCE(ps.company_name, ps.username, '') AS seller_name
             FROM b2b_contract_deliveries d
             LEFT JOIN players pb ON pb.id = d.buyer_player_id
             LEFT JOIN players ps ON ps.id = d.seller_player_id
             ORDER BY d.id DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->bindValue(2, max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function countAdminDeliveries(array $filters = []): int
    {
        return (int)$this->db->query('SELECT COUNT(*) FROM b2b_contract_deliveries')->fetchColumn();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listMyDeliveries(int $playerId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            "SELECT d.*,
                    COALESCE(pb.company_name, pb.username, '') AS buyer_name,
                    COALESCE(ps.company_name, ps.username, '') AS seller_name
             FROM b2b_contract_deliveries d
             LEFT JOIN players pb ON pb.id = d.buyer_player_id
             LEFT JOIN players ps ON ps.id = d.seller_player_id
             WHERE d.seller_player_id = ? OR d.buyer_player_id = ?
             ORDER BY d.id DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, $playerId, PDO::PARAM_INT);
        $stmt->bindValue(2, $playerId, PDO::PARAM_INT);
        $stmt->bindValue(3, max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->bindValue(4, max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countMyDeliveries(int $playerId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM b2b_contract_deliveries WHERE seller_player_id = ? OR buyer_player_id = ?'
        );
        $stmt->execute([$playerId, $playerId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * @return array<string,int|float>
     */
    public function getDashboardStats(): array
    {
        $stats = [
            'in_progress' => 0,
            'deliveries_today' => 0,
            'missing_bbl' => 0.0,
            'locked_funds' => 0.0,
            'penalties' => 0.0,
            'overdue' => 0,
        ];
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM b2b_contract_offers WHERE status = ?");
            $stmt->execute(['accepted']);
            $stats['in_progress'] = (int)$stmt->fetchColumn();

            $stmt = $this->db->prepare("SELECT COUNT(*) FROM b2b_contract_deliveries WHERE DATE(created_at) = ?");
            $stmt->execute([date('Y-m-d')]);
            $stats['deliveries_today'] = (int)$stmt->fetchColumn();

            $stmt = $this->db->prepare("SELECT COALESCE(SUM(total_bbl - delivered_bbl), 0) FROM b2b_contract_offers WHERE status = ?");
            $stmt->execute(['accepted']);
            $stats['missing_bbl'] = (float)$stmt->fetchColumn();

            $stmt = $this->db->prepare("SELECT COALESCE(SUM(remaining_escrow_amount), 0) FROM b2b_contract_offers WHERE status = ?");
            $stmt->execute(['accepted']);
            $stats['locked_funds'] = (float)$stmt->fetchColumn();

            $stmt = $this->db->prepare("SELECT COALESCE(SUM(seller_penalty_amount), 0) FROM b2b_contract_offers WHERE status IN (?, ?)");
            $stmt->execute(['partial_done', 'failed']);
            $stats['penalties'] = (float)$stmt->fetchColumn();

            $stmt = $this->db->prepare("SELECT COUNT(*) FROM b2b_contract_offers WHERE status = ? AND delivery_deadline_at < ?");
            $stmt->execute(['accepted', $this->now()]);
            $stats['overdue'] = (int)$stmt->fetchColumn();
        } catch (Throwable) {}
        return $stats;
    }

    /**
     * Shared delivery logic used by acceptOffer() and deliverPartial().
     * Assumes the calling method holds a transaction and has already locked the offer.
     *
     * @param array<string,mixed> $offer
     * @return array{success:bool,status:string,revenue?:float,remaining_bbl?:float,remaining_escrow?:float}
     */
    private function executeDelivery(int $offerId, array $offer, int $sellerPlayerId, float $bbl): array
    {
        $bbl = round($bbl, 2);
        $pricePerBbl = (float)$offer['price_per_bbl'];
        $totalBbl = (float)$offer['total_bbl'];
        $deliveredSoFar = (float)$offer['delivered_bbl'];
        $currentRemaining = round($totalBbl - $deliveredSoFar, 2);

        $totalEscrow = (float)$offer['escrow_amount'];
        $releasedSoFar = (float)$offer['released_amount'];
        $currentEscrow = max(0.0, round($totalEscrow - $releasedSoFar, 2));

        $newDelivered = round($deliveredSoFar + $bbl, 2);
        $newRemaining = max(0.0, round($currentRemaining - $bbl, 2));
        $isFinalDelivery = $newRemaining <= 1e-9;

        $revenue = $isFinalDelivery ? $currentEscrow : round($bbl * $pricePerBbl, 2);
        $revenue = min($revenue, $currentEscrow);
        $newEscrow = $isFinalDelivery ? 0.0 : round($currentEscrow - $revenue, 2);
        $newReleased = $isFinalDelivery ? $totalEscrow : round($releasedSoFar + $revenue, 2);

        if (!$this->deductOil($sellerPlayerId, $bbl)) {
            return ['success' => false, 'status' => 'insufficient_oil'];
        }

        $credit = $this->fts->credit(
            $sellerPlayerId,
            $revenue,
            FinancialTransactionService::TYPE_B2B_TRADE_REVENUE,
            'B2B delivery revenue',
            'b2b_contract_offer',
            $offerId
        );
        if (empty($credit['success'])) {
            return ['success' => false, 'status' => 'payment_failed'];
        }

        $this->db->prepare(
            "INSERT INTO b2b_contract_deliveries
                 (offer_id, buyer_player_id, seller_player_id, delivered_bbl, price_per_bbl, revenue,
                  escrow_before, escrow_after, remaining_bbl_after, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'delivered', ?)"
        )->execute([
            $offerId,
            (int)$offer['buyer_player_id'],
            $sellerPlayerId,
            $bbl,
            $pricePerBbl,
            $revenue,
            $currentEscrow,
            $newEscrow,
            $newRemaining,
            $this->now(),
        ]);

        $offerUpdate = $this->db->prepare(
            "UPDATE b2b_contract_offers
             SET delivered_bbl = ?,
                 remaining_bbl = ?,
                 released_amount = ?,
                 remaining_escrow_amount = ?,
                 updated_at = ?
             WHERE id = ?
               AND buyer_player_id = ?
               AND (seller_player_id IS NULL OR seller_player_id = ?)
               AND status IN ('open', 'accepted')"
        );
        $offerUpdate->execute([
            $newDelivered,
            $newRemaining,
            $newReleased,
            $newEscrow,
            $this->now(),
            $offerId,
            (int)$offer['buyer_player_id'],
            $sellerPlayerId,
        ]);
        if ($offerUpdate->rowCount() !== 1) {
            return ['success' => false, 'status' => 'race_lost'];
        }

        return [
            'success' => true,
            'status' => 'ok',
            'revenue' => $revenue,
            'remaining_bbl' => $newRemaining,
            'remaining_escrow' => $newEscrow,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function expireSingleOffer(int $offerId): array
    {
        $this->db->beginTransaction();
        try {
            $offer = $this->lockOffer($offerId);
            if ($offer === null || (string)$offer['status'] !== 'open') {
                $this->db->rollBack();
                return $this->result(false, 'not_open', 'contracts.b2b.not_open');
            }
            $refund = (float)$offer['escrow_amount'];
            if ($refund > 0) {
                $credit = $this->fts->credit(
                    (int)$offer['buyer_player_id'],
                    $refund,
                    FinancialTransactionService::TYPE_B2B_ESCROW_REFUND,
                    'B2B escrow refund after expiry',
                    'b2b_contract_offer',
                    $offerId
                );
                if (empty($credit['success'])) {
                    $this->db->rollBack();
                    return $this->result(false, 'refund_failed', 'contracts.b2b.refund_failed');
                }
            }
            $stmt = $this->db->prepare(
                "UPDATE b2b_contract_offers
                 SET status = 'expired', escrow_status = 'refunded', refunded_amount = ?, updated_at = ?
                 WHERE id = ?
                   AND buyer_player_id = ?
                   AND status = 'open'"
            );
            $stmt->execute([$refund, $this->now(), $offerId, (int)$offer['buyer_player_id']]);
            if ($stmt->rowCount() !== 1) {
                $this->db->rollBack();
                return $this->result(false, 'race_lost', 'contracts.b2b.race_lost');
            }
            $this->logEvent($offerId, (int)$offer['buyer_player_id'], 'expired', 'B2B offer expired', ['refund_amount' => $refund]);
            $this->recordReputation((int)$offer['buyer_player_id'], $offerId, 'buyer_expired', -1, [
                'buy_expired' => 1,
            ]);
            $this->db->commit();

            return [
                'success' => true,
                'status' => 'expired',
                'message_key' => 'contracts.b2b.expired',
                'refund_amount' => $refund,
            ];
        } catch (Throwable $e) {
            $this->safeRollback();
            $this->logFailure('expireSingleOffer', $e);
            return $this->result(false, 'db_error', 'contracts.b2b.db_error');
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function lockOffer(int $offerId): ?array
    {
        $sql = 'SELECT * FROM b2b_contract_offers WHERE id = ?';
        if ($this->driver !== 'sqlite') {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$offerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function offerById(int $offerId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM b2b_contract_offers WHERE id = ?');
        $stmt->execute([$offerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function playerExists(int $playerId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM players WHERE id = ? LIMIT 1');
        $stmt->execute([$playerId]);
        return (bool)$stmt->fetchColumn();
    }

    private function batchLimitSql(int $limit): string
    {
        if ($limit <= 0) {
            return '';
        }
        return ' LIMIT ' . max(1, min(1000000, $limit));
    }

    private function countOpenBuyerOffers(int $playerId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM b2b_contract_offers WHERE buyer_player_id = ? AND status = 'open'");
        $stmt->execute([$playerId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * @param array<string,float|int|string> $cfg
     * @return array{success:bool,status:string,message_key:string}
     */
    private function validatePrice(float $pricePerBbl, array $cfg): array
    {
        $market = $this->currentOilPrice();
        $min = $market * ((float)$cfg['min_price_market_pct'] / 100);
        $max = $market * ((float)$cfg['max_price_market_pct'] / 100);
        if ($pricePerBbl + 1e-9 < $min || $pricePerBbl - 1e-9 > $max) {
            return $this->result(false, 'invalid_price', 'contracts.b2b.invalid_price');
        }
        return $this->result(true, 'ok', 'contracts.b2b.ok');
    }

    private function currentOilPrice(): float
    {
        try {
            $value = $this->db->query('SELECT current_price FROM market_state ORDER BY id DESC LIMIT 1')->fetchColumn();
            if ($value !== false && (float)$value > 0) {
                return (float)$value;
            }
        } catch (Throwable) {
        }
        try {
            $value = $this->db->query('SELECT oil_price FROM market_state ORDER BY id DESC LIMIT 1')->fetchColumn();
            if ($value !== false && (float)$value > 0) {
                return (float)$value;
            }
        } catch (Throwable) {
        }
        return 100.0;
    }

    /**
     * @param array<string,float|int|string> $cfg
     */
    private function shouldFlagOffer(float $totalValue, float $pricePerBbl, array $cfg): bool
    {
        if ($totalValue >= (float)$cfg['admin_review_threshold_value']) {
            return true;
        }
        if ((int)$cfg['flag_price_near_limit'] !== 1) {
            return false;
        }
        $market = $this->currentOilPrice();
        $min = $market * ((float)$cfg['min_price_market_pct'] / 100);
        $max = $market * ((float)$cfg['max_price_market_pct'] / 100);
        $range = max(0.01, $max - $min);
        return ($pricePerBbl - $min) / $range <= 0.05 || ($max - $pricePerBbl) / $range <= 0.05;
    }

    private function availableOil(int $playerId): float
    {
        try {
            $stmt = $this->db->prepare('SELECT COALESCE(SUM(used), 0) FROM storage WHERE player_id = ?');
            $stmt->execute([$playerId]);
            return (float)$stmt->fetchColumn();
        } catch (Throwable) {
            return 0.0;
        }
    }

    private function deductOil(int $playerId, float $bbl): bool
    {
        $remaining = $bbl;
        $hasId = $this->storageHasId();
        $sql = $hasId
            ? 'SELECT id, used FROM storage WHERE player_id = ? AND used > 0 ORDER BY id ASC'
            : 'SELECT player_id, used FROM storage WHERE player_id = ? AND used > 0';
        if ($this->driver !== 'sqlite') {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$playerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            if ($remaining <= 1e-9) {
                break;
            }
            $used = (float)$row['used'];
            $take = min($used, $remaining);
            $newUsed = round($used - $take, 2);
            if ($hasId) {
                $this->db->prepare(
                    'UPDATE storage SET used = ? WHERE id = ? AND player_id = ?'
                )->execute([$newUsed, (int)$row['id'], $playerId]);
            } elseif ($this->driver === 'sqlite') {
                $this->db->prepare('UPDATE storage SET used = ? WHERE player_id = ?')->execute([$newUsed, $playerId]);
            } else {
                $this->db->prepare('UPDATE storage SET used = ? WHERE player_id = ? LIMIT 1')->execute([$newUsed, $playerId]);
            }
            $remaining = round($remaining - $take, 2);
        }
        return $remaining <= 1e-6;
    }

    private function storageHasId(): bool
    {
        if ($this->storageHasId !== null) {
            return $this->storageHasId;
        }
        try {
            if ($this->driver === 'sqlite') {
                $rows = $this->db->query("PRAGMA table_info(storage)")->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as $row) {
                    if ((string)($row['name'] ?? '') === 'id') {
                        return $this->storageHasId = true;
                    }
                }
                return $this->storageHasId = false;
            }
            $stmt = $this->db->prepare('SHOW COLUMNS FROM storage LIKE ?');
            $stmt->execute(['id']);
            return $this->storageHasId = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return $this->storageHasId = false;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function listByPlayerColumn(string $column, int $playerId, int $limit, int $offset): array
    {
        $column = in_array($column, ['buyer_player_id', 'seller_player_id'], true) ? $column : 'buyer_player_id';
        $stmt = $this->db->prepare(
            "SELECT o.*,
                    COALESCE(pb.company_name, pb.username, 'Buyer') AS buyer_name,
                    COALESCE(ps.company_name, ps.username, '') AS seller_name
             FROM b2b_contract_offers o
             LEFT JOIN players pb ON pb.id = o.buyer_player_id
             LEFT JOIN players ps ON ps.id = o.seller_player_id
             WHERE o.{$column} = ?
             ORDER BY o.created_at DESC, o.id DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, $playerId, PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->bindValue(3, max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function countByPlayerColumn(string $column, int $playerId): int
    {
        $column = in_array($column, ['buyer_player_id', 'seller_player_id'], true) ? $column : 'buyer_player_id';
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM b2b_contract_offers WHERE {$column} = ?");
        $stmt->execute([$playerId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * @param array{status?:string,query?:string,flagged?:string} $filters
     * @return array{0:string,1:list<mixed>}
     */
    private function adminOfferWhere(array $filters): array
    {
        $where = [];
        $params = [];
        $status = (string)($filters['status'] ?? '');
        if (in_array($status, ['open', 'accepted', 'completed', 'cancelled', 'expired', 'failed', 'partial_done', 'flagged'], true)) {
            $where[] = 'o.status = ?';
            $params[] = $status;
        }
        $flagged = (string)($filters['flagged'] ?? '');
        if ($flagged === '1' || $flagged === '0') {
            $where[] = 'o.is_flagged = ?';
            $params[] = (int)$flagged;
        }
        $query = trim((string)($filters['query'] ?? ''));
        if ($query !== '') {
            $where[] = '(pb.username LIKE ? OR pb.company_name LIKE ? OR ps.username LIKE ? OR ps.company_name LIKE ? OR CAST(o.id AS CHAR) = ?)';
            $like = '%' . $query . '%';
            array_push($params, $like, $like, $like, $like, $query);
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    /**
     * @param array<string,int|float|string> $counters
     */
    private function recordReputation(int $playerId, int $offerId, string $eventKey, int $delta, array $counters = []): void
    {
        if ($playerId <= 0) {
            return;
        }
        $allowedCounters = [
            'buy_completed',
            'sell_completed',
            'buy_cancelled',
            'buy_expired',
            'admin_flags',
            'admin_cancellations',
            'total_bought_bbl',
            'total_sold_bbl',
        ];
        $this->ensureReputationRow($playerId);

        $current = $this->lockReputationScore($playerId);
        $scoreAfter = max(0, min(100, $current + $delta));
        $sets = ['score = ?', 'updated_at = ?'];
        $params = [$scoreAfter, $this->now()];
        foreach ($counters as $column => $value) {
            if (!in_array((string)$column, $allowedCounters, true)) {
                continue;
            }
            $sets[] = "{$column} = {$column} + ?";
            $params[] = $value;
        }
        $params[] = $playerId;
        $stmt = $this->db->prepare('UPDATE b2b_reputation_scores SET ' . implode(', ', $sets) . ' WHERE player_id = ?');
        $stmt->execute($params);

        $stmt = $this->db->prepare(
            'INSERT INTO b2b_reputation_logs (player_id, offer_id, event_key, delta, score_after, meta_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $playerId,
            $offerId,
            $eventKey,
            $delta,
            $scoreAfter,
            json_encode($counters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $this->now(),
        ]);
    }

    private function ensureReputationRow(int $playerId): void
    {
        if ($this->driver === 'sqlite') {
            $stmt = $this->db->prepare(
                'INSERT OR IGNORE INTO b2b_reputation_scores (player_id, score, created_at, updated_at)
                 VALUES (?, 50, ?, ?)'
            );
            $stmt->execute([$playerId, $this->now(), $this->now()]);
            return;
        }
        $stmt = $this->db->prepare(
            'INSERT INTO b2b_reputation_scores (player_id, score, created_at, updated_at)
             VALUES (?, 50, NOW(), NOW())
             ON DUPLICATE KEY UPDATE player_id = player_id'
        );
        $stmt->execute([$playerId]);
    }

    private function lockReputationScore(int $playerId): int
    {
        $sql = 'SELECT score FROM b2b_reputation_scores WHERE player_id = ?';
        if ($this->driver !== 'sqlite') {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$playerId]);
        $score = $stmt->fetchColumn();
        return $score === false ? 50 : (int)$score;
    }

    /**
     * @param array<string,mixed> $meta
     */
    private function logEvent(int $offerId, ?int $playerId, string $eventKey, string $message, array $meta = []): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO b2b_contract_logs (offer_id, player_id, event_key, message, meta_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $offerId,
            $playerId,
            $eventKey,
            $message,
            $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $this->now(),
        ]);
    }

    /**
     * @return array{success:bool,status:string,message_key:string}
     */
    private function result(bool $success, string $status, string $messageKey): array
    {
        return ['success' => $success, 'status' => $status, 'message_key' => $messageKey];
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function safeRollback(): void
    {
        try {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
        } catch (Throwable) {
        }
    }

    private function logFailure(string $method, Throwable $e): void
    {
        if (class_exists('GameLog', false)) {
            GameLog::error('B2BContractService', $method . ' FAILED', $e);
        }
    }
}
