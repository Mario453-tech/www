<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/index.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

$db = Database::getInstance()->getConnection();

$stats = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'active')         AS active,
        SUM(status = 'bankrupt')       AS bankrupt,
        SUM(status = 'financial_risk') AS financial_risk,
        SUM(status = 'under_bailiff')  AS under_bailiff
    FROM players
")->fetch();

$market   = $db->query("SELECT * FROM market_state WHERE id = 1")->fetch();
$trend    = $db->query("
    SELECT * FROM market_trends
    WHERE active = TRUE AND activated_at IS NOT NULL
      AND activated_at > DATE_SUB(NOW(), INTERVAL duration_hours HOUR)
    ORDER BY activated_at DESC LIMIT 1
")->fetch();
$lastTick = $db->query("SELECT MAX(last_tick_at) FROM players")->fetchColumn();

$ftMsg   = $_SESSION['force_tick_msg']   ?? '';
$ftError = $_SESSION['force_tick_error'] ?? false;
unset($_SESSION['force_tick_msg'], $_SESSION['force_tick_error']);

$pageTitle = t('admin.index.page_title');
$viewData = [
    'stats'   => $stats,
    'market'  => $market,
    'trend'   => $trend,
    'lastTick'=> $lastTick,
    'ftMsg'   => $ftMsg,
    'ftError' => $ftError,
];
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/index/main.php';
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('admin/index.php', 'Unhandled exception', $e);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('admin/index.php', $_codexGuardStart);
    }
}
