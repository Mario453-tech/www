<?php
declare(strict_types=1);

require_once __DIR__ . '/FinancialTransactionService.php';
require_once __DIR__ . '/B2BContracts/B2BContractSchema.php';

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

    public function __construct(?PDO $db = null, ?FinancialTransactionService $fts = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        B2BContractSchema::ensure($this->db);
        $this->fts = $fts ?? new FinancialTransactionService($this->db);
        $this->driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
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
                     escrow_status, cancel_penalty_pct, min_seller_reputation, expires_at,
                     is_flagged, flag_reason, created_at, updated_at)
                 VALUES
                    (?, 'open', ?, ?, ?, ?, 'locked', ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $buyerPlayerId,
                $bbl,
                $pricePerBbl,
                $totalValue,
                $totalValue,
                $penaltyPct,
                max(0, $minSellerReputation),
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
            if ($offer === null || (string)$offer['status'] !== 'open') {
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
                 WHERE id = ?"
            );
            $stmt->execute([$penalty, $refund, $this->now(), $reason, $this->now(), $offerId]);
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
     * @return array{success:bool,status:string,message_key:string,total_value?:float}
     */
    public function acceptAndDeliver(int $sellerPlayerId, int $offerId): array
    {
        if (!$this->isModuleEnabled()) {
            return $this->result(false, 'disabled', 'contracts.b2b.disabled');
        }
        if (!$this->playerExists($sellerPlayerId)) {
            return $this->result(false, 'seller_not_found', 'contracts.b2b.seller_not_found');
        }

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

            $bbl = (float)$offer['total_bbl'];
            if ($this->availableOil($sellerPlayerId) + 1e-9 < $bbl) {
                $this->db->rollBack();
                return $this->result(false, 'insufficient_oil', 'contracts.b2b.insufficient_oil');
            }

            if (!$this->deductOil($sellerPlayerId, $bbl)) {
                $this->db->rollBack();
                return $this->result(false, 'insufficient_oil', 'contracts.b2b.insufficient_oil');
            }
            $totalValue = (float)$offer['total_value'];
            $credit = $this->fts->credit(
                $sellerPlayerId,
                $totalValue,
                FinancialTransactionService::TYPE_B2B_TRADE_REVENUE,
                'B2B oil delivery revenue',
                'b2b_contract_offer',
                $offerId
            );
            if (empty($credit['success'])) {
                $this->db->rollBack();
                return $this->result(false, 'payment_failed', 'contracts.b2b.payment_failed');
            }

            $stmt = $this->db->prepare(
                "UPDATE b2b_contract_offers
                 SET seller_player_id = ?, status = 'completed', delivered_bbl = total_bbl,
                     escrow_status = 'released', completed_at = ?, updated_at = ?
                 WHERE id = ? AND status = 'open'"
            );
            $stmt->execute([$sellerPlayerId, $this->now(), $this->now(), $offerId]);
            if ($stmt->rowCount() !== 1) {
                $this->db->rollBack();
                return $this->result(false, 'race_lost', 'contracts.b2b.race_lost');
            }

            $this->logEvent($offerId, $sellerPlayerId, 'completed', 'B2B offer accepted and delivered', [
                'seller_player_id' => $sellerPlayerId,
                'total_value' => $totalValue,
            ]);
            $this->recordReputation((int)$offer['buyer_player_id'], $offerId, 'buyer_completed', 1, [
                'buy_completed' => 1,
                'total_bought_bbl' => $bbl,
            ]);
            $this->recordReputation($sellerPlayerId, $offerId, 'seller_completed', 3, [
                'sell_completed' => 1,
                'total_sold_bbl' => $bbl,
            ]);
            $this->db->commit();

            return [
                'success' => true,
                'status' => 'completed',
                'message_key' => 'contracts.b2b.completed',
                'total_value' => $totalValue,
            ];
        } catch (Throwable $e) {
            $this->safeRollback();
            $this->logFailure('acceptAndDeliver', $e);
            return $this->result(false, 'db_error', 'contracts.b2b.db_error');
        }
    }

    /**
     * @return array{expired:int,refunded:float}
     */
    public function expireOpenOffers(?DateTimeInterface $now = null): array
    {
        $nowSql = $now ? $now->format('Y-m-d H:i:s') : $this->now();
        $stmt = $this->db->prepare("SELECT * FROM b2b_contract_offers WHERE status = 'open' AND expires_at <= ? ORDER BY id ASC");
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

        return ['expired' => $expired, 'refunded' => round($refunded, 2)];
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
                 WHERE id = ?"
            );
            $stmt->execute([$refund, $this->now(), $reason, $this->now(), $offerId]);
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
                 WHERE id = ?"
            );
            $stmt->execute([$refund, $this->now(), $offerId]);
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
                $this->db->prepare('UPDATE storage SET used = ? WHERE id = ?')->execute([$newUsed, (int)$row['id']]);
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
        if (in_array($status, ['open', 'completed', 'cancelled', 'expired', 'failed', 'flagged'], true)) {
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
