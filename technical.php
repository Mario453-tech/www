<?php

require_once __DIR__ . '/src/init.php';
require_once __DIR__ . '/src/TechnicalPageController.php';

Auth::requireLogin();
$playerId = Auth::getUserId();
BoardAccess::require($playerId, 'technical');

$_pageStart = GameLog::pageStart('technical.php');
GameLog::info('technical.php', 'Player logged in', ['player_id' => $playerId]);

try {
    $controller = new TechnicalPageController($playerId);
} catch (Throwable $e) {
    die($e->getMessage() ?: t('technical.err_init_svc'));
}

[$msg, $msgType] = $controller->handlePost();
$controller->syncReadyRecruitments();
$viewData = $controller->buildViewData($msg, $msgType);

$pageTitle = t('technical.page_title');
$extraCss = [
    'https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap',
    '/assets/css/technical.css',
];
$extraHead = '<meta name="csrf-token" content="' . htmlspecialchars(CSRF::generateToken(), ENT_QUOTES) . '">';
$gameShellTitle = t('technical.page_title');
$gameShellView = __DIR__ . '/templates/views/technical/main.php';

require_once __DIR__ . '/templates/header.php';
extract($viewData, EXTR_SKIP);
require __DIR__ . '/templates/components/game_shell.php';
require_once __DIR__ . '/templates/footer.php';
GameLog::pageEnd('technical.php', $_pageStart);
