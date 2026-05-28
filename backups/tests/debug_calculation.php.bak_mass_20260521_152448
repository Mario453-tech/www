<?php

require_once '../src/init.php';

echo "=== DEBUG OBLICZEŃ ===\n";

$db = Database::getInstance()->getConnection();

// 1. Ustaw na 54
$db->prepare("UPDATE market_state SET current_price = 54 WHERE id = 1")->execute();

// 2. Pobierz aktywny trend
$marketTrend = new MarketTrend();
$activeTrend = $marketTrend->getActiveTrend();

echo "Aktywny trend: " . $activeTrend['trend_name'] . "\n";
echo "Modyfikator: " . $activeTrend['price_modifier'] . "\n";

// 3. Symuluj obliczenia krok po kroku
$currentPrice = 54;
$basePrice = 100;
$volatility = 10;
$trendModifier = 1.20;

echo "\n--- Obliczenia krok po kroku ---\n";
echo "Start price: $currentPrice\n";

// Bazowa zmiana ceny
$change = rand(-$volatility, $volatility);
echo "Losowa zmiana: $change\n";
$newPrice = $currentPrice + $change;
echo "Po zmianie losowej: $newPrice\n";

// Zastosuj modyfikator trendu
$newPrice = $newPrice * $trendModifier;
echo "Po trendzie ($trendModifier): $newPrice\n";

// Ograniczenie do granic
$newPrice = max(10, min(500, $newPrice));
echo "Po ograniczeniach: $newPrice\n";

// Grawitacja
$gravity = ($basePrice - $newPrice) * 0.05;
echo "Grawitacja: $gravity\n";
$newPrice += $gravity;
echo "Po grawitacji: $newPrice\n";

// Zaokrąglenie
$newPrice = round($newPrice);
echo "Po zaokrągleniu: $newPrice\n";

// 4. Uruchom prawdziwy tick i porównaj
echo "\n--- Prawdziwy tick ---\n";
$marketTick = new MarketTick();
$realPrice = $marketTick->updatePrices($activeTrend);
echo "Rzeczywista cena: $realPrice\n";

// 5. Sprawdź w bazie
$stmt = $db->query("SELECT * FROM market_state WHERE id = 1");
$state = $stmt->fetch();
echo "Cena w bazie: " . $state['current_price'] . "\n";

?>
