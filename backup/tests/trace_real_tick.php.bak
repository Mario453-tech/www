<?php

require_once '../src/init.php';

echo "=== ŒLAD PRAWDZIWEGO TICK ===\n";

$db = Database::getInstance()->getConnection();

// 1. Ustaw na 54
$db->prepare("UPDATE market_state SET current_price = 54 WHERE id = 1")->execute();

// 2. SprawdŸ stan PRZED tick
$stmt = $db->query("SELECT * FROM market_state WHERE id = 1");
$stateBefore = $stmt->fetch();
echo "Stan PRZED tick:\n";
echo "Current price: " . $stateBefore['current_price'] . "\n";
echo "Base price: " . $stateBefore['base_price'] . "\n";
echo "Volatility: " . $stateBefore['volatility'] . "\n";

// 3. Pobierz trend
$marketTrend = new MarketTrend();
$activeTrend = $marketTrend->getActiveTrend();
echo "\nAktywny trend: " . $activeTrend['trend_name'] . "\n";
echo "Modyfikator: " . $activeTrend['price_modifier'] . "\n";

// 4. Uruchom tick i przechwyæ wartoœci
echo "\n--- Tick w trakcie ---\n";

// Dodajemy logowanie do MarketTick - stworzymy tymczasow¹ wersjê
class MarketTickDebug extends MarketTick {
    public function updatePricesDebug($activeTrend = null) {
        $db = Database::getInstance()->getConnection();
        
        $market = $db->query("SELECT * FROM market_state WHERE id = 1")->fetch();
        $basePrice = $market['base_price'];
        $currentPrice = $market['current_price'];
        $volatility = $market['volatility'];
        
        echo "Start: current_price=$currentPrice, base_price=$basePrice, volatility=$volatility\n";
        
        // Bazowa zmiana ceny
        $change = rand(-$volatility, $volatility);
        echo "Losowa zmiana: $change\n";
        $newPrice = $currentPrice + $change;
        echo "Po zmianie: $newPrice\n";
        
        // Zastosuj modyfikator trendu
        if ($activeTrend) {
            $trendModifier = $activeTrend['price_modifier'];
            echo "Trend modyfikator: $trendModifier\n";
            $newPrice = $newPrice * $trendModifier;
            echo "Po trendzie: $newPrice\n";
        }
        
        // Ograniczenie do granic
        $newPrice = max(10, min(500, $newPrice));
        echo "Po ograniczeniach: $newPrice\n";
        
        // Grawitacja
        $gravity = ($basePrice - $newPrice) * 0.05;
        echo "Grawitacja: $gravity\n";
        $newPrice += $gravity;
        echo "Po grawitacji: $newPrice\n";
        
        // Zaokr¹glenie
        $newPrice = round($newPrice);
        echo "Po zaokr¹gleniu: $newPrice\n";
        
        return $newPrice;
    }
}

// 5. Uruchom debug wersjê
$marketTickDebug = new MarketTickDebug();
$debugPrice = $marketTickDebug->updatePricesDebug($activeTrend);
echo "\nDebug tick: $debugPrice\n";

// 6. Uruchom prawdziwy tick
$marketTick = new MarketTick();
$realPrice = $marketTick->updatePrices($activeTrend);
echo "Prawdziwy tick: $realPrice\n";

// 7. SprawdŸ stan PO tick
$stmt = $db->query("SELECT * FROM market_state WHERE id = 1");
$stateAfter = $stmt->fetch();
echo "\nStan PO tick:\n";
echo "Current price: " . $stateAfter['current_price'] . "\n";

?>
