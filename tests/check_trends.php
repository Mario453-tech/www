<?php

// Wcz raportowanie bdw
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1> Sprawdzenie trendw w bazie danych</h1>";

try {
    require_once '../src/init.php';
    echo "<p> init.php zaadowany</p>";
} catch (Exception $e) {
    echo "<p> Bd adowania init.php: " . $e->getMessage() . "</p>";
    exit;
}

$marketTrend = new MarketTrend();

// 1. Sprawd czy tabela istnieje i ile jest trendw
try {
    $db = Database::getInstance()->getConnection();
    $count = $db->query("SELECT COUNT(*) as total FROM market_trends")->fetch();
    echo "<h2> Tabela market_trends istnieje</h2>";
    echo "<p>Liczba trendw w bazie: <strong>{$count['total']}</strong></p>";
} catch (Exception $e) {
    echo "<h2> BD: Tabela market_trends nie istnieje!</h2>";
    echo "<p>Musisz wgra plik: sql/market_trends_complete.sql</p>";
    echo "<p>Bd: " . $e->getMessage() . "</p>";
    exit;
}

// 2. Poka kilka przykadowych trendw
echo "<h2> Przykadowe trendy w bazie:</h2>";
$trends = $db->query("SELECT * FROM market_trends LIMIT 5")->fetchAll();
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Nazwa</th><th>Kategoria</th><th>Modyfikator</th><th>Czas trwania</th><th>Aktywny</th></tr>";
foreach ($trends as $trend) {
    echo "<tr>";
    echo "<td>{$trend['id']}</td>";
    echo "<td>{$trend['trend_name']}</td>";
    echo "<td>{$trend['category']}</td>";
    echo "<td>{$trend['price_modifier']}</td>";
    echo "<td>{$trend['duration_hours']}h</td>";
    echo "<td>" . ($trend['active'] ? ' TAK' : ' NIE') . "</td>";
    echo "</tr>";
}
echo "</table>";

// 3. Sprawd aktywny trend
echo "<h2> Aktywny trend:</h2>";
$activeTrend = $marketTrend->getActiveTrend();
if ($activeTrend) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
    echo "<h3> Jest aktywny trend!</h3>";
    echo "<p><strong>Nazwa:</strong> {$activeTrend['trend_name']}</p>";
    echo "<p><strong>Kategoria:</strong> {$activeTrend['category']}</p>";
    echo "<p><strong>Modyfikator ceny:</strong> {$activeTrend['price_modifier']}</p>";
    echo "<p><strong>Aktywowany:</strong> {$activeTrend['activated_at']}</p>";
    echo "<p><strong>Czas trwania:</strong> {$activeTrend['duration_hours']} godzin</p>";
    
    $message = $marketTrend->getTrendMessage($activeTrend);
    echo "<p><strong>Wiadomo:</strong> {$message}</p>";
    echo "</div>";
} else {
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
    echo "<h3> Brak aktywnego trendu</h3>";
    echo "<p>aden trend nie jest obecnie aktywny.</p>";
    echo "<p>Trendy aktywuj si automatycznie przez cron/tick.php z 10% szans.</p>";
    echo "</div>";
}

// 4. Przycisk do rcznej aktywacji trendu
echo "<h2> Aktywuj losowy trend:</h2>";
if (isset($_GET['activate'])) {
    $randomTrend = $marketTrend->getRandomTrend();
    if ($randomTrend) {
        $marketTrend->activateTrend($randomTrend['id']);
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
        echo "<h3> Trend aktywowany!</h3>";
        echo "<p>Aktywowano: <strong>{$randomTrend['trend_name']}</strong></p>";
        echo "<p><a href='check_trends.php'>Odwie stron</a></p>";
        echo "</div>";
    }
} else {
    echo "<p><a href='check_trends.php?activate=1' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'> Aktywuj losowy trend</a></p>";
}

echo "<hr>";
echo "<p><a href='index.php'> Powrt do Dashboard</a></p>";
