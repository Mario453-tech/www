<?php
declare(strict_types=1);

/**
 * Black market oil trading service.
 * PL: Serwis czarnego rynku ropy.
 *
 * Responsible for offer generation, execution, penalties and black-score decay.
 * PL: Odpowiada za generowanie ofert, realizacje transakcji, kary i decay black score.
 */
class BlackMarketService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    // Config helpers.
    // PL: Helpery konfiguracji.
    /**
     * Reads config value from well_config with fallback.
     * PL: Pobiera wartosc konfiguracji z well_config z fallbackiem.
     */
    private function cfg(string $key, float $default): float
    {
        static $cache = [];
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        try {
            $stmt = $this->db->prepare("SELECT `value` FROM well_config WHERE `key` = :k LIMIT 1");
            $stmt->execute([':k' => $key]);
            $val = $stmt->fetchColumn();
            $cache[$key] = $val !== false ? (float)$val : $default;
        } catch (Throwable $e) {
            $cache[$key] = $default;
        }
        return $cache[$key];
    }

    // Offer generation.
    // PL: Generowanie ofert.
    /**
     * Generates random black market offers for the player.
     * PL: Generuje losowe oferty czarnego rynku dla gracza.
     *
     * Triggered every N ticks from tick.php.
     * PL: Wywolywane co N tickow z tick.php.
     */
    public function generateOffers(int $playerId, float $oilPrice): int
    {
        $minBbl = (int)$this->cfg('bm_min_bbl', 50);
        $maxBbl = (int)$this->cfg('bm_max_bbl', 2000);
        $pMultMin = $this->cfg('bm_price_mult_min', 1.3);
        $pMultMax = $this->cfg('bm_price_mult_max', 2.0);
        $riskMin = $this->cfg('bm_base_risk_min', 15);
        $riskMax = $this->cfg('bm_base_risk_max', 40);
        $ttlMin = (int)$this->cfg('bm_offer_ttl_ticks_min', 6);
        $ttlMax = (int)$this->cfg('bm_offer_ttl_ticks_max', 18);

        // Offers can exist even if the player currently lacks enough oil.
        // PL: Oferty moga istniec nawet gdy gracz chwilowo nie ma dosc ropy.
        $scaledMax = $maxBbl;

        // Random number of offers: 1-3.
        // PL: Losowa liczba ofert: 1-3.
        $count = random_int(1, 3);
        $generated = 0;

        $stmt = $this->db->prepare("
            INSERT INTO black_market_offers (player_id, bbl, price_per_bbl, base_risk_pct, expires_at)
            VALUES (:pid, :bbl, :price, :risk, :expires)
        ");

        for ($i = 0; $i < $count; $i++) {
            $bbl = random_int($minBbl, $scaledMax);
            $mult = $pMultMin + (mt_rand() / mt_getrandmax()) * ($pMultMax - $pMultMin);
            $price = round($oilPrice * $mult, 2);
            $risk = round($riskMin + (mt_rand() / mt_getrandmax()) * ($riskMax - $riskMin), 2);
            $ttl = random_int($ttlMin, $ttlMax);
            $expiresAt = date('Y-m-d H:i:s', time() + $ttl * 5 * 60);

            $stmt->execute([
                ':pid' => $playerId,
                ':bbl' => $bbl,
                ':price' => $price,
                ':risk' => $risk,
                ':expires' => $expiresAt,
            ]);
            $generated++;
        }

        return $generated;
    }

    /**
     * Returns active player offers.
     * PL: Zwraca aktywne oferty gracza.
     */
    public function getActiveOffers(int $playerId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, bbl, price_per_bbl, base_risk_pct, expires_at, created_at
            FROM black_market_offers
            WHERE player_id = :pid AND status = 'active' AND expires_at > NOW()
            ORDER BY expires_at ASC
        ");
        $stmt->execute([':pid' => $playerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Expires old offers.
     * PL: Wygasza przeterminowane oferty.
     */
    public function expireOffers(): int
    {
        $stmt = $this->db->prepare("
            UPDATE black_market_offers
            SET status = 'expired'
            WHERE status = 'active' AND expires_at <= NOW()
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }

    // Transaction execution.
    // PL: Realizacja transakcji.
    /**
     * Executes a black market transaction.
     * PL: Realizuje transakcje na czarnym rynku.
     *
     * @return array{success: bool, detected: bool, revenue: float, penalty: float, black_score: float, credit_change: int, message: string}
     */
    public function executeTransaction(int $playerId, int $offerId): array
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                SELECT * FROM black_market_offers
                WHERE id = :oid AND player_id = :pid AND status = 'active' AND expires_at > NOW()
                FOR UPDATE
            ");
            $stmt->execute([':oid' => $offerId, ':pid' => $playerId]);
            $offer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$offer) {
                $this->db->rollBack();
                return ['success' => false, 'detected' => false, 'revenue' => 0.0, 'penalty' => 0.0, 'black_score' => 0.0, 'credit_change' => 0, 'message' => t('black_market.offer_expired')];
            }

            $storageUsed = $this->getPlayerStorageUsed($playerId);
            if ($storageUsed < $offer['bbl']) {
                $this->db->rollBack();
                return ['success' => false, 'detected' => false, 'revenue' => 0.0, 'penalty' => 0.0, 'black_score' => 0.0, 'credit_change' => 0, 'message' => t('black_market.not_enough_oil')];
            }

            $pStmt = $this->db->prepare("SELECT cash, black_market_score, credit_score FROM players WHERE id = :pid FOR UPDATE");
            $pStmt->execute([':pid' => $playerId]);
            $player = $pStmt->fetch(PDO::FETCH_ASSOC);

            if (!$player) {
                $this->db->rollBack();
                return ['success' => false, 'detected' => false, 'revenue' => 0.0, 'penalty' => 0.0, 'black_score' => 0.0, 'credit_change' => 0, 'message' => t('black_market.player_error')];
            }

            $revenue = $offer['bbl'] * $offer['price_per_bbl'];
            $blackScore = (float)$player['black_market_score'];
            $cash = (float)$player['cash'];

            // Effective risk = base risk + black score contribution.
            // PL: Ryzyko efektywne = ryzyko bazowe + wklad black score.
            $effectiveRisk = (float)$offer['base_risk_pct'] + ($blackScore * 0.5);
            $effectiveRisk = min($effectiveRisk, 95.0);

            $roll = (mt_rand() / mt_getrandmax()) * 100;
            $detected = $roll < $effectiveRisk;

            $scoreGainMin = $this->cfg('bm_score_gain_min', 3);
            $scoreGainMax = $this->cfg('bm_score_gain_max', 8);
            $scoreGain = $scoreGainMin + (mt_rand() / mt_getrandmax()) * ($scoreGainMax - $scoreGainMin);

            $newScore = min(100.0, $blackScore + $scoreGain);
            $penalty = 0.0;
            $creditChange = 0;

            $this->db->prepare("UPDATE black_market_offers SET status = 'accepted' WHERE id = :oid")
                ->execute([':oid' => $offerId]);

            $this->db->prepare("UPDATE storage SET used = used - :bbl WHERE player_id = :pid")
                ->execute([':bbl' => $offer['bbl'], ':pid' => $playerId]);

            $cashAfterSale = $cash + $revenue;

            if ($detected) {
                $penaltyPct = $this->getPenaltyPercent($newScore);
                $penalty = round($cashAfterSale * ($penaltyPct / 100), 2);

                // Do not take more cash than the player has after the sale.
                // PL: Nie zabieraj wiecej niz gracz ma po transakcji.
                $penalty = min($penalty, $cashAfterSale);

                $creditChange = -random_int(3, max(3, min(10, (int)ceil($newScore / 10))));

                $this->db->prepare("
                    UPDATE players
                    SET cash = cash + :revenue - :penalty,
                        black_market_score = :bms,
                        credit_score = GREATEST(0, credit_score + :cs)
                    WHERE id = :pid
                ")->execute([
                    ':revenue' => $revenue,
                    ':penalty' => $penalty,
                    ':bms' => round($newScore, 2),
                    ':cs' => $creditChange,
                    ':pid' => $playerId,
                ]);

                if (class_exists('AdminLog', false)) {
                    try {
                        AdminLog::log('black_market_detected', "Player #$playerId: {$offer['bbl']} bbl, revenue=$revenue, penalty=$penalty, score=$newScore");
                    } catch (Throwable $e) {
                    }
                }
            } else {
                $this->db->prepare("
                    UPDATE players
                    SET cash = cash + :revenue,
                        black_market_score = :bms
                    WHERE id = :pid
                ")->execute([
                    ':revenue' => $revenue,
                    ':bms' => round($newScore, 2),
                    ':pid' => $playerId,
                ]);
            }

            $this->db->prepare("
                INSERT INTO black_market_transactions
                    (player_id, offer_id, bbl, revenue, detected, penalty, black_score_before, black_score_after, credit_score_change)
                VALUES
                    (:pid, :oid, :bbl, :rev, :det, :pen, :bsb, :bsa, :csc)
            ")->execute([
                ':pid' => $playerId,
                ':oid' => $offerId,
                ':bbl' => $offer['bbl'],
                ':rev' => $revenue,
                ':det' => $detected ? 1 : 0,
                ':pen' => $penalty,
                ':bsb' => round($blackScore, 2),
                ':bsa' => round($newScore, 2),
                ':csc' => $creditChange,
            ]);

            $this->db->commit();

            $message = $detected
                ? t('black_market.detected', [':penalty' => number_format($penalty, 0, ',', ' ')])
                : t('black_market.success', [':revenue' => number_format($revenue, 0, ',', ' ')]);

            return [
                'success' => true,
                'detected' => $detected,
                'revenue' => $revenue,
                'penalty' => $penalty,
                'black_score' => round($newScore, 2),
                'credit_change' => $creditChange,
                'message' => $message,
            ];
        } catch (Throwable $e) {
            $this->db->rollBack();
            GameLog::error('BlackMarketService', 'Transaction failed', $e);
            return ['success' => false, 'detected' => false, 'revenue' => 0.0, 'penalty' => 0.0, 'black_score' => 0.0, 'credit_change' => 0, 'message' => t('black_market.error')];
        }
    }

    // Score decay.
    // PL: Decay score.
    /**
     * Reduces black_market_score for all players.
     * PL: Redukuje black_market_score wszystkim graczom.
     */
    public function decayScores(): void
    {
        $decay = $this->cfg('bm_score_decay_per_tick', 0.5);
        if ($decay <= 0) {
            return;
        }

        $this->db->prepare("
            UPDATE players
            SET black_market_score = GREATEST(0, black_market_score - :decay)
            WHERE black_market_score > 0
        ")->execute([':decay' => $decay]);
    }

    // Credit score recovery.
    // PL: Odbudowa credit score.
    /**
     * Improves player credit score after legal trade.
     * PL: Poprawia credit score gracza po legalnej transakcji.
     */
    public function applyLegalRecovery(int $playerId): void
    {
        $rate = $this->cfg('credit_score_legal_recovery_rate', 0.1);
        if ($rate <= 0) {
            return;
        }

        $this->db->prepare("
            UPDATE players
            SET credit_score = LEAST(100, credit_score + :rate)
            WHERE id = :pid AND credit_score < 100
        ")->execute([':rate' => $rate, ':pid' => $playerId]);
    }

    // History helpers.
    // PL: Helpery historii.
    /**
     * Returns recent player transactions.
     * PL: Zwraca ostatnie transakcje gracza.
     */
    public function getTransactions(int $playerId, int $limit = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT id, bbl, revenue, detected, penalty, black_score_before, black_score_after,
                   credit_score_change, created_at
            FROM black_market_transactions
            WHERE player_id = :pid
            ORDER BY created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':pid', $playerId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Returns player black market stats.
     * PL: Zwraca statystyki czarnego rynku gracza.
     */
    public function getPlayerStats(int $playerId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*)                           AS total_transactions,
                COALESCE(SUM(revenue), 0)          AS total_revenue,
                COALESCE(SUM(penalty), 0)          AS total_penalties,
                SUM(detected)                      AS times_detected,
                COALESCE(SUM(bbl), 0)              AS total_bbl
            FROM black_market_transactions
            WHERE player_id = :pid
        ");
        $stmt->execute([':pid' => $playerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_transactions' => 0,
            'total_revenue' => 0,
            'total_penalties' => 0,
            'times_detected' => 0,
            'total_bbl' => 0,
        ];
    }

    // Admin helpers.
    // PL: Helpery admina.
    /**
     * Returns all transactions for admin view.
     * PL: Zwraca wszystkie transakcje do widoku admina.
     */
    public function getAllTransactions(int $limit = 100, int $offset = 0, ?int $filterPlayerId = null): array
    {
        $where = $filterPlayerId ? "WHERE t.player_id = :fpid" : "";
        $sql = "
            SELECT t.*, p.username, p.company_name
            FROM black_market_transactions t
            LEFT JOIN players p ON p.id = t.player_id
            $where
            ORDER BY t.created_at DESC
            LIMIT :lim OFFSET :off
        ";
        $stmt = $this->db->prepare($sql);
        if ($filterPlayerId) {
            $stmt->bindValue(':fpid', $filterPlayerId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Returns black-market score and stats for each player.
     * PL: Zwraca black market score i statystyki dla kazdego gracza.
     */
    public function getPlayersBlackMarketData(): array
    {
        $stmt = $this->db->query("
            SELECT p.id, p.username, p.company_name, p.black_market_score, p.credit_score,
                   COALESCE(s.tx_count, 0) AS tx_count,
                   COALESCE(s.total_revenue, 0) AS total_revenue,
                   COALESCE(s.total_penalties, 0) AS total_penalties,
                   COALESCE(s.times_detected, 0) AS times_detected
            FROM players p
            LEFT JOIN (
                SELECT player_id,
                       COUNT(*) AS tx_count,
                       SUM(revenue) AS total_revenue,
                       SUM(penalty) AS total_penalties,
                       SUM(detected) AS times_detected
                FROM black_market_transactions
                GROUP BY player_id
            ) s ON s.player_id = p.id
            ORDER BY p.black_market_score DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sets player black-market score from admin panel.
     * PL: Ustawia black market score gracza z panelu admina.
     */
    public function setPlayerBlackScore(int $playerId, float $score): void
    {
        $score = max(0, min(100, $score));
        $this->db->prepare("UPDATE players SET black_market_score = :s WHERE id = :pid")
            ->execute([':s' => round($score, 2), ':pid' => $playerId]);
    }

    // Internal helpers.
    // PL: Helpery wewnetrzne.
    private function getPlayerStorageUsed(int $playerId): int
    {
        $stmt = $this->db->prepare("SELECT COALESCE(used, 0) FROM storage WHERE player_id = :pid LIMIT 1");
        $stmt->execute([':pid' => $playerId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Returns penalty percent based on black score.
     * PL: Zwraca procent kary zalezne od black score.
     */
    private function getPenaltyPercent(float $blackScore): float
    {
        $lowPct = $this->cfg('bm_penalty_low_pct', 7.5);
        $midPct = $this->cfg('bm_penalty_mid_pct', 15.0);
        $highPct = $this->cfg('bm_penalty_high_pct', 27.5);

        if ($blackScore < 30) {
            return $lowPct * 0.5 + (mt_rand() / mt_getrandmax()) * $lowPct;
        }
        if ($blackScore < 60) {
            return $midPct * 0.67 + (mt_rand() / mt_getrandmax()) * ($midPct * 0.67);
        }
        return $highPct * 0.73 + (mt_rand() / mt_getrandmax()) * ($highPct * 0.54);
    }

    /**
     * Returns global stats for admin dashboard.
     * PL: Zwraca globalne statystyki dla dashboardu admina.
     */
    public function getGlobalStats(): array
    {
        $row = $this->db->query("
            SELECT COUNT(*) AS total_tx,
                   COALESCE(SUM(revenue), 0) AS total_revenue,
                   COALESCE(SUM(penalty), 0) AS total_penalties,
                   SUM(detected) AS total_detected,
                   COUNT(DISTINCT player_id) AS unique_players
            FROM black_market_transactions
        ")->fetch(PDO::FETCH_ASSOC);
        return $row ?: [
            'total_tx' => 0,
            'total_revenue' => 0,
            'total_penalties' => 0,
            'total_detected' => 0,
            'unique_players' => 0,
        ];
    }
}
