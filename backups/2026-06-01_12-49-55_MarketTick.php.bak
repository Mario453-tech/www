<?php

/**
 * MarketTick oil price tick logic
 *
 * PRICE FORMULA:
 * ratio = (player_supply + world_production) / demand_index
 * sd_pressure = (1 - ratio) * SENSITIVITY * current_price
 * gravity = (base_price - current) * GRAVITY_RATE
 * new_price = clamp(current + sd_pressure + gravity + noise, MIN, MAX)
 *
 * WORLD_PRODUCTION (OPEC-like):
 * price > 150 -> +10%/tick (max 1500)
 * price < 60 -> -12%/tick (min 200)
 * otherwise -> return to 800 at 2%/tick
 */
class MarketTick
{
    const MIN_PRICE       = 30;
    const MAX_PRICE       = 300;
    const SENSITIVITY     = 0.40;
    const GRAVITY_RATE    = 0.03;
    const OPEC_CUT_PRICE  = 60;
    const OPEC_BOOM_PRICE = 150;
    const WORLD_PROD_BASE = 800.0;
    const WORLD_PROD_MIN  = 200.0;
    const WORLD_PROD_MAX  = 1500.0;
    const LOG_KEEP_DAYS   = 7;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

 // Main method

 /** @param array<string, mixed>|null $activeTrend */
    public function updatePrices(?array $activeTrend = null): int
    {
        GameLog::step('MarketTick', 'updatePrices', 1, 'start', [
            'trend' => $activeTrend['trend_name'] ?? 'none',
        ]);

        try {
 // Fetch market state
            $market = $this->db->query("SELECT * FROM market_state WHERE id = 1")->fetch();

            if (!$market) {
                GameLog::error('MarketTick', 'updatePrices: market_state row id=1 missing');
                return 0;
            }

            $basePrice  = (int)$market['base_price'];
            $current    = (float)$market['current_price'];
            $volatility = (int)$market['volatility'];
 // Fallback when migration columns do not exist yet
            $worldProd  = isset($market['world_production']) ? (float)$market['world_production'] : self::WORLD_PROD_BASE;
            $demand     = isset($market['demand_index'])     ? (float)$market['demand_index']     : 1000.0;

 // 1. Player supply (oil in storage)
            GameLog::step('MarketTick', 'updatePrices', 2, 'SUM active well supply flow');
            $playerSupply = $this->fetchPlayerSupplyFlow();

 // 2. NPC world reacts to price (OPEC)
            GameLog::step('MarketTick', 'updatePrices', 3, 'adjustWorldProduction', [
                'world_prod_before' => round($worldProd, 1),
                'current_price'     => $current,
            ]);
            $worldProd = $this->adjustWorldProduction($worldProd, $current);

 // 3. Popyt modyfikowany przez trend
            $demandModifier  = $activeTrend ? (float)$activeTrend['price_modifier'] : 1.0;
            $effectiveDemand = max(1.0, $demand * $demandModifier);

 // 4. Ratio supply/demand
            $totalSupply = $playerSupply + $worldProd;
            $ratio       = $totalSupply / $effectiveDemand;

 // 5. Price change components
            $sdPressure = (1 - $ratio) * self::SENSITIVITY * $current;

 // Gravity pulls toward base_price trend price_modifier
 // Pandemic 0.60 -> target = 60; Boom 1.50 -> target = 150 (capped MAX)
            $trendTarget = (int)round(max(self::MIN_PRICE, min(self::MAX_PRICE, $basePrice * $demandModifier)));
            $gravity     = ($trendTarget - $current) * self::GRAVITY_RATE;

 // Direct shock: strong trend (>25%) pushes price by an extra step/tick
            $trendShock = 0;
            if ($activeTrend) {
                $dev = $demandModifier - 1.0;
                if (abs($dev) >= 0.25) {
                    $trendShock = (int)round($dev * $current * 0.05);
                }
            }

            $noise      = rand(-$volatility, $volatility);

 // 6. New price with hard limits
            $rawPrice = $current + $sdPressure + $gravity + $trendShock + $noise;
            $newPrice = (int)round(max(self::MIN_PRICE, min(self::MAX_PRICE, $rawPrice)));

            GameLog::info('MarketTick', 'price calculator', [
                'player_supply'    => round($playerSupply, 1),
                'supply_source'    => 'active_well_flow',
                'world_production' => round($worldProd, 1),
                'total_supply'     => round($totalSupply, 1),
                'effective_demand' => round($effectiveDemand, 1),
                'ratio'            => round($ratio, 4),
                'sd_pressure'      => round($sdPressure, 2),
                'gravity'          => round($gravity, 2),
                'trend_target'     => $trendTarget,
                'trend_shock'      => $trendShock,
                'noise'            => $noise,
                'raw_price'        => round($rawPrice, 2),
                'new_price'        => $newPrice,
            ]);

 // 7. Save to DB
            GameLog::step('MarketTick', 'updatePrices', 4, 'UPDATE market_state');
            $this->db->prepare("
                UPDATE market_state SET
                    current_price       = :price,
                    supply_index        = :supply,
                    world_production    = :world_prod,
                    last_supply_tick    = NOW(),
                    last_market_tick_at = NOW()
                WHERE id = 1
            ")->execute([
                ':price'      => $newPrice,
                ':supply'     => $totalSupply,
                ':world_prod' => $worldProd,
            ]);

 // 8. Historical logs and order execution
            $this->saveSupplyDemandLog($totalSupply, $effectiveDemand, $ratio, $newPrice);
            $this->savePriceHistory($newPrice);
            $this->processOffers($newPrice);

            GameLog::step('MarketTick', 'updatePrices', 5, 'done', ['new_price' => $newPrice]);
            return $newPrice;

        } catch (Throwable $e) {
            GameLog::error('MarketTick', 'updatePrices CRITICAL FAILURE', $e);
 // Fallback do not crash the tick, return the last saved price
            try {
                $row = $this->db->query("SELECT current_price FROM market_state WHERE id = 1")->fetch();
                return (int)($row['current_price'] ?? 0);
            } catch (Throwable $e2) {
                GameLog::error('MarketTick', 'fallback price read FAILED', $e2);
                return 0;
            }
        }
    }

 // Private helpers

    private function fetchPlayerSupplyFlow(): float
    {
        try {
            return (float)$this->db->query(
                "SELECT COALESCE(SUM(
                    w.base_production_per_hour
 * LEAST(1.0, GREATEST(0.0, COALESCE(w.transport_capacity_pct, 100) / 100.0))
 * GREATEST(0.0, LEAST(1.0, COALESCE(w.technical_condition, 100) / 100.0))
                ), 0)
                FROM wells w
                JOIN players p ON p.id = w.player_id
                WHERE p.status != 'bankrupt'
                  AND w.status IN ('active', 'contaminated', 'no_technician')"
            )->fetchColumn();
        } catch (Throwable $e) {
            GameLog::error('MarketTick', 'fetchPlayerSupplyFlow FAILED', $e);
            return 0.0;
        }
    }

    private function adjustWorldProduction(float $current, float $price): float
    {
        if ($price > self::OPEC_BOOM_PRICE) {
            $current *= 1.10;  // high price � pump more
        } elseif ($price < self::OPEC_CUT_PRICE) {
            $current *= 0.88;  // low price � cut production
        } else {
            $current += (self::WORLD_PROD_BASE - $current) * 0.02;  // return to base
        }
        return max(self::WORLD_PROD_MIN, min(self::WORLD_PROD_MAX, $current));
    }

    private function saveSupplyDemandLog(float $supply, float $demand, float $ratio, int $price): void
    {
        try {
            $this->db->prepare("
                INSERT INTO market_supply_demand_log (supply, demand, ratio, price, created_at)
                VALUES (:supply, :demand, :ratio, :price, NOW())
            ")->execute([
                ':supply' => $supply,
                ':demand' => $demand,
                ':ratio'  => $ratio,
                ':price'  => $price,
            ]);

            $this->db->prepare("
                DELETE FROM market_supply_demand_log
                WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
            ")->execute([':days' => self::LOG_KEEP_DAYS]);

        } catch (Throwable $e) {
 // Log historyczny nie krytyczny, nie crashujemy ticki
            GameLog::error('MarketTick', 'saveSupplyDemandLog FAILED', $e);
        }
    }

    private function savePriceHistory(int $price): void
    {
        try {
            $this->db->prepare("
                INSERT INTO price_history (price, created_at) VALUES (:price, NOW())
            ")->execute([':price' => $price]);

            $this->db->prepare("
                DELETE FROM price_history
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ")->execute();

        } catch (Throwable $e) {
            GameLog::error('MarketTick', 'savePriceHistory FAILED', $e);
        }
    }

    private function processOffers(int $currentPrice): void
    {
        try {
            (new MarketOffer())->processOffers($currentPrice);
        } catch (Throwable $e) {
            GameLog::error('MarketTick', 'processOffers FAILED', $e, ['price' => $currentPrice]);
        }
    }

 // Publiczne gettery 

    public function getPriceHistory(int $hours = 24): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT price, created_at FROM price_history
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
                ORDER BY created_at ASC
            ");
            $stmt->execute([':hours' => $hours]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            GameLog::error('MarketTick', 'getPriceHistory FAILED', $e);
            return [];
        }
    }

    public function getMarketSnapshot(): array
    {
        try {
            $row = $this->db->query("SELECT * FROM market_state WHERE id = 1")->fetch();
            if (!$row) return [];

            $playerSupply = $this->fetchPlayerSupply();
            $worldProd    = (float)($row['world_production'] ?? self::WORLD_PROD_BASE);
            $supply       = $playerSupply + $worldProd;
            $demand       = (float)($row['demand_index'] ?? 1000.0);
            $ratio        = $demand > 0 ? round($supply / $demand, 3) : 1.0;

            return [
                'current_price'    => (int)$row['current_price'],
                'base_price'       => (int)$row['base_price'],
                'player_supply'    => round($playerSupply, 1),
                'world_production' => round($worldProd, 1),
                'total_supply'     => round($supply, 1),
                'demand'           => round($demand, 1),
                'ratio'            => $ratio,
                'market_status'    => $ratio > 1.10 ? 'oversupply'
                                    : ($ratio < 0.90 ? 'shortage' : 'balanced'),
            ];
        } catch (Throwable $e) {
            GameLog::error('MarketTick', 'getMarketSnapshot FAILED', $e);
            return [];
        }
    }

    public function getSupplyDemandHistory(int $limit = 48): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT supply, demand, ratio, price, created_at
                FROM market_supply_demand_log
                ORDER BY created_at DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_reverse($stmt->fetchAll());
        } catch (Throwable $e) {
            GameLog::error('MarketTick', 'getSupplyDemandHistory FAILED', $e);
            return [];
        }
    }
}
