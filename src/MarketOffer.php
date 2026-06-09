<?php

require_once __DIR__ . '/FinancialTransactionService.php';

class MarketOffer
{
    private PDO $db;

 // Ensures market_sale_history table exists (runs once per connection).
 // Tworzy tabele market_sale_history jesli nie istnieje (raz na polaczenie).
    private static bool $schemaEnsured = false;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }
        self::$schemaEnsured = true;

        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS market_sale_history (
                    id            INT            NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    player_id     INT            NOT NULL,
                    offer_id      INT            NOT NULL,
                    listed_at     DATETIME       NOT NULL,
                    sold_at       DATETIME       NOT NULL,
                    price_per_bbl INT            NOT NULL,
                    barrels_sold  INT            NOT NULL,
                    total_earned  DECIMAL(14,2)  NOT NULL,
                    KEY idx_player_sold (player_id, sold_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Throwable $e) {
            GameLog::error('MarketOffer', 'ensureSchema FAILED', $e);
        }
    }


    public function createOffer(int $playerId, float $amount, float $limitPrice): array
    {
        GameLog::step('MarketOffer', 'createOffer', 1, 'validation', [
            'player_id'   => $playerId,
            'amount'      => $amount,
            'limit_price' => $limitPrice,
        ]);

        try {
            $storage     = new Storage($playerId);
            $storageData = $storage->getData();

            if (!$storageData) {
                GameLog::warn('MarketOffer', 'createOffer: no storage for player', ['player_id' => $playerId]);
                return ['success' => false, 'message' => t('market_offer.err_no_storage')];
            }

            if ((float)$storageData['used'] < $amount) {
                return ['success' => false, 'message' => t('market_offer.err_not_enough_oil')];
            }

            if ($limitPrice < 30) {
                return ['success' => false, 'message' => t('market_offer.err_price_too_low')];
            }

            if ($amount <= 0) {
                return ['success' => false, 'message' => t('market_offer.err_amount_zero')];
            }

            $this->db->beginTransaction();

 // Lock oil in storage
            GameLog::step('MarketOffer', 'createOffer', 2, 'UPDATE storage');
            $stmtUpd = $this->db->prepare("
                UPDATE storage
                SET used = used - :amount
                WHERE player_id = :player_id AND used >= :amount
            ");
            $stmtUpd->execute([':amount' => $amount, ':player_id' => $playerId]);

 // Verify UPDATE actually happened (race condition guard)
            $affected = $stmtUpd->rowCount();
            if ($affected === 0) {
                $this->db->rollBack();
                GameLog::warn('MarketOffer', 'createOffer: race condition - not enough oil', [
                    'player_id' => $playerId,
                    'amount'    => $amount,
                ]);
                return ['success' => false, 'message' => t('market_offer.err_race_condition')];
            }

 // Create the offer record
            GameLog::step('MarketOffer', 'createOffer', 3, 'INSERT market_offers');
            $this->db->prepare("
                INSERT INTO market_offers
                    (player_id, amount, locked_amount, limit_price, status, created_at)
                VALUES
                    (:player_id, :amount, :locked_amount, :limit_price, 'pending', NOW())
            ")->execute([
                ':player_id'    => $playerId,
                ':amount'       => $amount,
                ':locked_amount'=> $amount,
                ':limit_price'  => $limitPrice,
            ]);

            $offerId = (int)$this->db->lastInsertId();
            $this->db->commit();

            GameLog::info('MarketOffer', 'createOffer: success', [
                'offer_id'  => $offerId,
                'player_id' => $playerId,
                'amount'    => $amount,
            ]);

            return [
                'success'  => true,
                'message'  => t('market_offer.msg_created', ['amount' => $amount, 'price' => $limitPrice]),
                'offer_id' => $offerId,
            ];

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            GameLog::error('MarketOffer', 'createOffer FAILED', $e, [
                'player_id' => $playerId,
                'amount'    => $amount,
            ]);
            return ['success' => false, 'message' => t('common.app_error')];
        }
    }

 // Offer retrieval

    public function getPlayerOffers(int $playerId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM market_offers
                WHERE player_id = :player_id AND status = 'pending'
                ORDER BY created_at DESC
            ");
            $stmt->execute([':player_id' => $playerId]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            GameLog::error('MarketOffer', 'getPlayerOffers FAILED', $e, ['player_id' => $playerId]);
            return [];
        }
    }

    public function getOffer(int $offerId, ?int $playerId = null): ?array
    {
        try {
            $sql    = "SELECT * FROM market_offers WHERE id = :id";
            $params = [':id' => $offerId];

            if ($playerId !== null) {
                $sql   .= " AND player_id = :player_id";
                $params[':player_id'] = $playerId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (Throwable $e) {
            GameLog::error('MarketOffer', 'getOffer FAILED', $e, ['offer_id' => $offerId]);
            return null;
        }
    }

 // Offer editing

    public function updateOffer(int $offerId, int $playerId, float $newLimitPrice): array
    {
        try {
            $offer = $this->getOffer($offerId, $playerId);

            if (!$offer) {
                return ['success' => false, 'message' => t('market_offer.err_not_found')];
            }

            if (empty($offer['editable'])) {
                return ['success' => false, 'message' => t('market_offer.err_not_editable')];
            }

            if ($newLimitPrice < 30) {
                return ['success' => false, 'message' => t('market_offer.err_price_too_low')];
            }

            $this->db->prepare("
                UPDATE market_offers
                SET limit_price = :limit_price
                WHERE id = :id AND player_id = :player_id
            ")->execute([
                ':limit_price' => $newLimitPrice,
                ':id'          => $offerId,
                ':player_id'   => $playerId,
            ]);

            GameLog::info('MarketOffer', 'updateOffer', [
                'offer_id'       => $offerId,
                'new_limit_price'=> $newLimitPrice,
            ]);

            return ['success' => true, 'message' => t('market_offer.msg_updated')];

        } catch (Throwable $e) {
            GameLog::error('MarketOffer', 'updateOffer FAILED', $e, ['offer_id' => $offerId]);
            return ['success' => false, 'message' => t('common.app_error')];
        }
    }

 // Offer cancellation

    public function cancelOffer(int $offerId, int $playerId): array
    {
        GameLog::step('MarketOffer', 'cancelOffer', 1, 'start', [
            'offer_id'  => $offerId,
            'player_id' => $playerId,
        ]);

        try {
            $offer = $this->getOffer($offerId, $playerId);

            if (!$offer) {
                return ['success' => false, 'message' => t('market_offer.err_not_found')];
            }

            if ($offer['status'] !== 'pending') {
                return ['success' => false, 'message' => t('market_offer.err_cancel_status')];
            }

            $this->db->beginTransaction();

 // Calculate penalty and return oil
            $cancellationFee  = (float)($offer['cancellation_fee'] ?? 0.10);
            $penalty          = (int)round($offer['amount'] * $cancellationFee);
            $returnedAmount   = $offer['amount'] - $penalty;

            GameLog::step('MarketOffer', 'cancelOffer', 2, 'oil return', [
                'original' => $offer['amount'],
                'penalty'  => $penalty,
                'returned' => $returnedAmount,
            ]);

            $storage = new Storage($playerId);
            $storage->addOil($returnedAmount);

            $this->db->prepare("
                UPDATE market_offers
                SET status = 'cancelled', completed_at = NOW()
                WHERE id = :id AND player_id = :player_id
            ")->execute([':id' => $offerId, ':player_id' => $playerId]);

            $this->db->commit();

            GameLog::info('MarketOffer', 'cancelOffer: success', [
                'offer_id' => $offerId,
                'returned' => $returnedAmount,
                'penalty'  => $penalty,
            ]);

            return [
                'success' => true,
                'message' => t('market_offer.msg_cancelled', ['returned' => $returnedAmount, 'penalty' => $penalty]),
            ];

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            GameLog::error('MarketOffer', 'cancelOffer FAILED', $e, ['offer_id' => $offerId]);
            return ['success' => false, 'message' => t('common.app_error')];
        }
    }

 // Offer execution (called by the tick loop)

 /**
 * Executes all pending offers where limit_price <= currentPrice.
 * FIX: parameterised query (previously SQL injection).
 */
    public function processOffers(int $currentPrice): void
    {
        GameLog::step('MarketOffer', 'processOffers', 1, 'start', ['price' => $currentPrice]);

        try {
            // Zapewnij schemat bankowy przed petla transakcji / Ensure bank schema before the offer transaction loop.
            new FinancialTransactionService($this->db);

            $stmt = $this->db->prepare("
                SELECT * FROM market_offers
                WHERE status = 'pending'
                  AND limit_price <= :price
                  AND auto_execute = TRUE
            ");
            $stmt->execute([':price' => $currentPrice]);
            $offers = $stmt->fetchAll();

            GameLog::dbResult('MarketOffer', 'processOffers', count($offers), "to execute at price {$currentPrice}");

            foreach ($offers as $offer) {
                $this->executeOffer($offer, $currentPrice);
            }

        } catch (Throwable $e) {
            GameLog::error('MarketOffer', 'processOffers FAILED', $e, ['price' => $currentPrice]);
        }
    }

    private function executeOffer(array $offer, int $currentPrice): void
    {
        GameLog::step('MarketOffer', 'executeOffer', 1, 'start', [
            'offer_id'  => $offer['id'],
            'player_id' => $offer['player_id'],
            'amount'    => $offer['amount'],
            'price'     => $currentPrice,
        ]);

        try {
            $this->db->beginTransaction();

            $earnings = (float)$offer['amount'] * $currentPrice;

            // Zasil konto gracza przez centralne API finansowe / Credit player via central financial API.
            $creditResult = (new FinancialTransactionService($this->db))->credit(
                (int)$offer['player_id'],
                $earnings,
                FinancialTransactionService::TYPE_MARKET_SALE,
                tPlain('bank.tx_market_sale', ['id' => (int)$offer['id']]),
                'market_offer',
                (int)$offer['id']
            );
            if (empty($creditResult['success'])) {
                $this->db->rollBack();
                GameLog::error('MarketOffer', 'executeOffer: credit FAILED', null, [
                    'offer_id'  => $offer['id'],
                    'player_id' => $offer['player_id'],
                    'error'     => $creditResult['error'] ?? 'unknown',
                ]);
                return;
            }

 // Close the offer / Zamknij oferte
            $this->db->prepare("
                UPDATE market_offers
                SET status       = 'completed',
                    sold_amount  = :amount,
                    sold_price   = :price,
                    completed_at = NOW()
                WHERE id = :id
            ")->execute([
                ':amount' => $offer['amount'],
                ':price'  => $currentPrice,
                ':id'     => $offer['id'],
            ]);

 // Log to sale history / Zapisz do historii sprzedazy
            $this->db->prepare("
                INSERT INTO market_sale_history
                    (player_id, offer_id, listed_at, sold_at, price_per_bbl, barrels_sold, total_earned)
                VALUES
                    (:player_id, :offer_id, :listed_at, NOW(), :price, :barrels, :total)
            ")->execute([
                ':player_id' => $offer['player_id'],
                ':offer_id'  => $offer['id'],
                ':listed_at' => $offer['created_at'],
                ':price'     => $currentPrice,
                ':barrels'   => $offer['amount'],
                ':total'     => $earnings,
            ]);

            $this->db->commit();

            GameLog::info('MarketOffer', 'executeOffer: completed', [
                'offer_id' => $offer['id'],
                'earnings' => $earnings,
            ]);

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            GameLog::error('MarketOffer', 'executeOffer FAILED', $e, [
                'offer_id'  => $offer['id'],
                'player_id' => $offer['player_id'],
            ]);
 // Do not rethrow — one failure must not block remaining offers
        }
    }

 // Sale history — paginated / Historia sprzedazy z paginacja

    public function getSaleHistory(int $playerId, int $page, int $perPage): array
    {
        try {
            $offset = ($page - 1) * $perPage;

            $stmtCount = $this->db->prepare("
                SELECT COUNT(*) FROM market_sale_history WHERE player_id = :pid
            ");
            $stmtCount->execute([':pid' => $playerId]);
            $total = (int)$stmtCount->fetchColumn();

            $stmt = $this->db->prepare("
                SELECT id, offer_id, listed_at, sold_at, price_per_bbl, barrels_sold, total_earned
                FROM market_sale_history
                WHERE player_id = :pid
                ORDER BY sold_at DESC
                LIMIT :lim OFFSET :off
            ");
            $stmt->bindValue(':pid', $playerId, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $perPage,  PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset,   PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();

            return ['rows' => $rows, 'total' => (int)$total];

        } catch (Throwable $e) {
            GameLog::error('MarketOffer', 'getSaleHistory FAILED', $e, ['player_id' => $playerId]);
            return ['rows' => [], 'total' => 0];
        }
    }

 // Delete history entries older than 7 days / Usun wpisy starsze niz 7 dni
    public function purgeOldSaleHistory(): void
    {
        try {
            $deleted = $this->db->exec("
                DELETE FROM market_sale_history
                WHERE sold_at < NOW() - INTERVAL 7 DAY
            ");
            if ($deleted > 0) {
                GameLog::info('MarketOffer', 'purgeOldSaleHistory', ['deleted' => $deleted]);
            }
        } catch (Throwable $e) {
            GameLog::error('MarketOffer', 'purgeOldSaleHistory FAILED', $e);
        }
    }
}
