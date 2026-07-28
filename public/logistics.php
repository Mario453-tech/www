<?php

require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/LogisticsPageController.php';

Auth::requireLogin();

$playerId = Auth::getUserId();
BoardAccess::require($playerId, 'logistics');
$db = Database::getInstance()->getConnection();
$_pageStart = GameLog::pageStart('public/logistics.php');

$staffingFlash = $_SESSION['logistics_staffing_flash'] ?? [];
unset($_SESSION['logistics_staffing_flash']);

$controller = new LogisticsPageController($playerId, $db);
$controller->handlePost();
$viewData = $controller->buildViewData($staffingFlash);

$pageTitle = t('logistics.page_title') . ' - OilCorp';
$gameShellTitle = t('logistics.page_title');
$gameShellView = __DIR__ . '/../templates/views/logistics/main.php';
$extraCss = [
    '/assets/css/logistics.css',
    '/assets/css/logistics_pipeline_staffing.css',
    '/assets/css/protection.css',
];
$extraJs = [
    '/assets/js/logistics_config.js',
    '/assets/js/logistics_ui.js',
    '/assets/js/logistics_hubs.js',
    '/assets/js/logistics_hub_wells.js',
    '/assets/js/logistics_hub_market.js',
    '/assets/js/logistics_staffing.js',
    '/assets/js/logistics_optimizer.js',
    '/assets/js/logistics_pipeline.js',
    '/assets/js/logistics_pipeline_staffing.js',
    '/assets/js/logistics_hub_browser.js',
    '/assets/js/protection.js',
    '/assets/js/logistics_countdowns.js',
];
$extraHead = '<meta name="csrf-token" content="'
    . htmlspecialchars(CSRF::generateToken(), ENT_QUOTES, 'UTF-8')
    . '">';

require_once __DIR__ . '/../templates/header.php';
extract($viewData, EXTR_SKIP);
require __DIR__ . '/../templates/components/game_shell.php';
require_once __DIR__ . '/../templates/footer.php';

GameLog::pageEnd('public/logistics.php', $_pageStart);
