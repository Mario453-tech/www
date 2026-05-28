<?php

require_once '../src/init.php';

echo "=== DOKŁADNY DEBUG ===\n";

$db = Database::getInstance()->getConnection();

// 1. Ustaw na 54
$db->prepare("UPDATE market_state SET current_price = 54 WHERE id = 1")->execute();

// 2. Pobierz dane z bazy (jak w MarketTick)
$market = $db->query("SELECT * FROM market_state WHERE id = 1")->fetch();
$basePrice = $market['base_price'];
$currentPrice = $market['current_price'];
$volatility = $market['volatility'];

echo "Dane z bazy:\n";
echo "Base price: $basePrice\n";
echo "Current price: $currentPrice\n";
echo "Volatility: $volatility\n";

// 3. Pobierz trend
$marketTrend = new MarketTrend();
$activeTrend = $marketTrend->getActiveTrend();

echo "\nTrend:\n";
echo "Nazwa: " . $activeTrend['trend_name'] . "\n";
echo "Modyfikator: " . $activeTrend['price_modifier'] . "\n";

// 4. Dokładne obliczenia jak w MarketTick
echo "\n--- Obliczenia jak w MarketTick ---\n";

// Bazowa zmiana ceny
$change = rand(-$volatility, $volatility);
echo "1. Losowa zmiana (rand(-$volatility, $volatility)): $change\n";
$newPrice = $currentPrice + $change;
echo "   Nowa cena: $newPrice\n";

// Zastosuj modyfikator trendu
if ($activeTrend) {
    $trendModifier = $activeTrend['price_modifier'];
    echo "2. Trend (modyfikator: $trendModifier)\n";
    $newPrice = $newPrice * $trendModifier;
    echo "   Po trendzie: $newPrice\n";
}

// Ograniczenie do granic
echo "3. Ograniczenie (max(10, min(500, $newPrice)))\n";
$newPrice = max(10, min(500, $newPrice));
echo "   Po ograniczeniu: $newPrice\n";

// Grawitacja
echo "4. Grawitacja (($basePrice - $newPrice) * 0.05)\n";
$gravity = ($basePrice - $newPrice) * 0.05;
echo "   Wartość grawitacji: $gravity\n";
$newPrice += $gravity;
echo "   Po grawitacji: $newPrice\n";

// Zaokrąglenie
echo "5. Zaokrąglenie (round($newPrice))\n";
$newPrice = round($newPrice);
echo "   Końcowa cena: $newPrice\n";

// 5. Porównaj z prawdziwym tick
echo "\n--- Porównanie ---\n";
$marketTick = new MarketTick();
$realPrice = $marketTick->updatePrices($activeTrend);
echo "Prawdziwy tick: $realPrice\n";
echo "Nasze obliczenia: $newPrice\n";
echo "Różnica: " . ($realPrice - $newPrice) . "\n";

?>
