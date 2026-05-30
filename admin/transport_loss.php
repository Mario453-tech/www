<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/transport_loss.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

$db = Database::getInstance()->getConnection();

// Globalne statystyki loss 
$globalPipelines = [];
try {
    $globalPipelines = $db->query("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'active') AS active_count,
            AVG(CASE WHEN status='active' THEN transport_loss END) AS avg_loss,
            MAX(CASE WHEN status='active' THEN transport_loss END) AS max_loss,
            SUM(CASE WHEN status='active' AND transport_loss > 15 THEN 1 ELSE 0 END) AS critical_count,
            AVG(CASE WHEN status='active' THEN condition_pct END) AS avg_condition
        FROM pipelines
    ")->fetch();
} catch (Throwable $e) {}

// Loss per typ transportu (odwierty) 
$lossByType = $db->query("
    SELECT
        w.transport_type,
        COUNT(*) AS wells,
        SUM(w.status='active') AS active_wells,
        AVG(w.transport_capacity_pct) AS avg_cap,
        AVG(w.transport_opex_pct) AS avg_opex,
        SUM(w.base_production_per_hour) AS total_base_prod
    FROM wells w
    WHERE w.status NOT IN ('seized','blowout')
    GROUP BY w.transport_type
")->fetchAll();

// Loss per warstwa geologiczna 
$lossByLayer = [];
try {
    $lossByLayer = $db->query("
        SELECT
            COALESCE(w.active_layer_code, 'shallow') AS layer,
            w.transport_type,
            COUNT(*) AS wells,
            AVG(w.transport_opex_pct) AS avg_opex,
            SUM(w.base_production_per_hour) AS base_prod
        FROM wells w
        WHERE w.status = 'active'
        GROUP BY w.active_layer_code, w.transport_type
        ORDER BY layer, w.transport_type
    ")->fetchAll();
} catch (Throwable $e) {}

// Loss per gracz 
$lossPerPlayer = $db->query("
    SELECT
        p.id, p.username AS login,
        COUNT(w.id) AS total_wells,
        SUM(w.status='active') AS active_wells,
        AVG(w.transport_opex_pct) AS avg_opex,
        AVG(w.transport_capacity_pct) AS avg_cap,
        GROUP_CONCAT(DISTINCT w.transport_type ORDER BY w.transport_type SEPARATOR ',') AS transport_mix,
        COALESCE(MAX(pl.transport_loss), 0) AS pipeline_max_loss,
        COALESCE(AVG(CASE WHEN pl.status='active' THEN pl.transport_loss END), 0) AS pipeline_avg_loss,
        SUM(w.base_production_per_hour) AS base_prod_sum
    FROM players p
    LEFT JOIN wells w       ON w.player_id = p.id AND w.status NOT IN ('seized','blowout')
    LEFT JOIN pipelines pl  ON pl.player_id = p.id
    WHERE p.status != 'bankrupt'
    GROUP BY p.id
    ORDER BY pipeline_avg_loss DESC, avg_opex DESC
")->fetchAll();

// Loss per odwiert (top 20 najgorszych) 
$worstWells = $db->query("
    SELECT
        w.id, w.name, w.transport_type, w.transport_capacity_pct,
        w.transport_opex_pct, w.status, w.technical_condition,
        w.base_production_per_hour, w.wear_level,
        wl.name AS location_name,
        p.username AS player_login,
        p.id    AS player_id
    FROM wells w
    LEFT JOIN players p         ON p.id = w.player_id
    LEFT JOIN world_locations wl ON wl.id = w.location_id
    WHERE w.status NOT IN ('seized','blowout')
    ORDER BY w.transport_opex_pct DESC, w.transport_capacity_pct ASC
    LIMIT 20
")->fetchAll();

// Szacunkowa strata wartosci per tick 
$oilPrice = (float)$db->query("SELECT current_price FROM market_state WHERE id=1")->fetchColumn();
if ($oilPrice <= 0) $oilPrice = 70.0;

$tIcons = [
    'rurociag'   => t('admin.transport.icon_pipe'),
    'ciezarowki' => t('admin.transport.icon_truck'),
    'tankowiec'  => t('admin.transport.icon_tanker'),
];
$tNames = [
    'rurociag'   => t('admin.transport.type_pipe'),
    'ciezarowki' => t('admin.transport.type_truck'),
    'tankowiec'  => t('admin.transport.type_tanker'),
];
$tRiskBase = [
    'rurociag'   => t('admin.transport_loss.risk_pipe'),
    'ciezarowki' => t('admin.transport_loss.risk_truck'),
    'tankowiec'  => t('admin.transport_loss.risk_tanker'),
];

$viewData = [
    'globalPipelines' => $globalPipelines,
    'lossByType'      => $lossByType,
    'lossByLayer'     => $lossByLayer,
    'lossPerPlayer'   => $lossPerPlayer,
    'worstWells'      => $worstWells,
    'oilPrice'        => $oilPrice,
    'tIcons'          => $tIcons,
    'tNames'          => $tNames,
    'tRiskBase'       => $tRiskBase,
];

$pageTitle = t('admin.transport_loss.title');
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/transport_loss/main.php';
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) GameLog::error('admin/transport_loss.php', t('common.unhandled_exception'), $e);
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) GameLog::pageEnd('admin/transport_loss.php', $_codexGuardStart);
}
