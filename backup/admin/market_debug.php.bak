<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/market_debug.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

$db = Database::getInstance()->getConnection();

//  Stan rynku 
$market = $db->query("SELECT * FROM market_state WHERE id = 1")->fetch();
$activeTrend = $db->query("
    SELECT * FROM market_trends
    WHERE active = TRUE AND activated_at IS NOT NULL
      AND activated_at > DATE_SUB(NOW(), INTERVAL duration_hours HOUR)
    ORDER BY activated_at DESC LIMIT 1
")->fetch();

//  Produkcja globalna 
$prodGlobal = $db->query("
    SELECT
        COUNT(DISTINCT w.player_id) AS active_players,
        COUNT(w.id) AS total_wells,
        SUM(w.status = 'active') AS active_wells,
        SUM(w.base_production_per_hour) AS total_base_prod,
        AVG(w.technical_condition) AS avg_condition
    FROM wells w
    WHERE w.status NOT IN ('seized','blowout')
")->fetch();

//  Produkcja per typ transportu 
$prodByTransport = $db->query("
    SELECT
        w.transport_type,
        COUNT(*) AS wells,
        SUM(w.base_production_per_hour) AS base_prod_sum,
        AVG(w.transport_capacity_pct) AS avg_capacity,
        AVG(w.transport_opex_pct) AS avg_opex,
        AVG(w.technical_condition) AS avg_condition
    FROM wells w
    WHERE w.status = 'active'
    GROUP BY w.transport_type
")->fetchAll();

//  Magazyn globalny 
$storageGlobal = $db->query("
    SELECT
        SUM(s.capacity) AS total_capacity,
        SUM(s.used) AS total_used,
        COUNT(*) AS player_count
    FROM storage s
    JOIN players p ON p.id = s.player_id
    WHERE p.status != 'bankrupt'
")->fetch();

//  Szacunkowy transport loss 
$pipelineLoss = [];
try {
    $pipelineLoss = $db->query("
        SELECT
            COUNT(*) AS total_pipelines,
            SUM(status = 'active') AS active_pipelines,
            AVG(CASE WHEN status = 'active' THEN transport_loss END) AS avg_loss_pct,
            MAX(CASE WHEN status = 'active' THEN transport_loss END) AS max_loss_pct,
            AVG(CASE WHEN status = 'active' THEN condition_pct END) AS avg_condition
        FROM pipelines
    ")->fetch();
} catch (Throwable $e) {}

//  Cena vs oczekiwana (supply/demand) 
$demandData = [];
try {
    $demandData = $db->query("SELECT * FROM market_demand_state WHERE id = 1")->fetch();
} catch (Throwable $e) {}

//  Historia supply/demand z MarketTick 
$supplyDemandHistory = [];
try {
    $supplyDemandHistory = $db->query("
        SELECT supply, demand, ratio, price, created_at
        FROM market_supply_demand_log
        ORDER BY created_at DESC
        LIMIT 10
    ")->fetchAll();
    $supplyDemandHistory = array_reverse($supplyDemandHistory);
} catch (Throwable $e) {}

//  Historia ceny (ostatnie 20 ticków) 
$priceHistory = [];
try {
    $priceHistory = $db->query("
        SELECT price, created_at AS recorded_at
        FROM price_history
        ORDER BY created_at DESC
        LIMIT 20
    ")->fetchAll();
    $priceHistory = array_reverse($priceHistory);
} catch (Throwable $e) {}

//  Sprzedaż graczy (ostatni tick lub agregat) 
$playerEconomy = $db->query("
    SELECT
        p.id,
        p.username AS login,
        p.cash,
        p.status,
        s.used AS storage_used,
        s.capacity AS storage_capacity,
        COUNT(w.id) AS total_wells,
        SUM(w.status = 'active') AS active_wells,
        SUM(w.base_production_per_hour) AS base_prod_sum,
        AVG(w.transport_capacity_pct) AS avg_transport_cap
    FROM players p
    LEFT JOIN storage s ON s.player_id = p.id
    LEFT JOIN wells w   ON w.player_id = p.id AND w.status NOT IN ('seized','blowout')
    WHERE p.status != 'bankrupt'
    GROUP BY p.id
    ORDER BY base_prod_sum DESC
")->fetchAll();

$currentPrice = (float)($market['current_price'] ?? 70);
$basePrice    = (float)($market['base_price']    ?? 100);

$pageTitle = t('admin.market_debug.page_title');
$viewData = compact(
    'market', 'activeTrend', 'prodGlobal', 'prodByTransport',
    'storageGlobal', 'pipelineLoss', 'demandData',
    'supplyDemandHistory', 'priceHistory', 'playerEconomy',
    'currentPrice', 'basePrice'
);
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/market_debug/main.php';
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) GameLog::error('admin/market_debug.php', 'Exception', $e);
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) GameLog::pageEnd('admin/market_debug.php', $_codexGuardStart);
}
