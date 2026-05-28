<?php

require_once 'src/init.php';

echo "=== TEST CENY (SZCZEGÓ£OWY) ===\n";

// 1. SprawdŸ aktualn¹ cenê
$market = new Market();
$currentPrice = $market->getCurrentPrice();
echo "Cena z Market::getCurrentPrice(): $currentPrice\n";

// 2. SprawdŸ bezpoœrednio w bazie
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT * FROM market_state WHERE id = 1");
$state = $stmt->fetch();
echo "Cena bezpoœrednio z bazy: " . $state['current_price'] . "\n";
echo "Base price: " . $state['base_price'] . "\n";
echo "Volatility: " . $state['volatility'] . "\n";

// 3. Ustaw na 54
echo "\n--- Ustawiam na 54 ---\n";
$db->prepare("UPDATE market_state SET current_price = 54 WHERE id = 1")->execute();

// 4. SprawdŸ ponownie
$stmt = $db->query("SELECT * FROM market_state WHERE id = 1");
$state = $stmt->fetch();
echo "Cena po ustawieniu: " . $state['current_price'] . "\n";

// 5. SprawdŸ trendy
echo "\n--- Sprawdzam trendy ---\n";
$marketTrend = new MarketTrend();
$activeTrend = $marketTrend->getActiveTrend();
if ($activeTrend) {
    echo "Aktywny trend: " . $activeTrend['trend_name'] . "\n";
    echo "Modyfikator: " . $activeTrend['price_modifier'] . "\n";
    echo "Kategoria: " . $activeTrend['category'] . "\n";
    echo "Aktywowany: " . $activeTrend['activated_at'] . "\n";
} else {
    echo "Brak aktywnych trendów\n";
}

// 6. Uruchom tick BEZ trendów
echo "\n--- Tick BEZ trendów ---\n";
$marketTick = new MarketTick();
$newPrice = $marketTick->updatePrices(null);
echo "Cena po tick (bez trendów): $newPrice\n";

// 7. Resetuj na 54
$db->prepare("UPDATE market_state SET current_price = 54 WHERE id = 1")->execute();

// 8. Uruchom tick Z trendami
echo "\n--- Tick Z trendami ---\n";
$newPriceWithTrend = $marketTick->updatePrices($activeTrend);
echo "Cena po tick (z trendami): $newPriceWithTrend\n";

// 9. SprawdŸ w bazie
$stmt = $db->query("SELECT * FROM market_state WHERE id = 1");
$state = $stmt->fetch();
echo "Cena w bazie po tick: " . $state['current_price'] . "\n";

?>
