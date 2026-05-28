<?php
$_pageStart = class_exists('GameLog', false) ? GameLog::pageStart('public/map.php') : microtime(true);
try {

require_once __DIR__ . '/../src/init.php';
Auth::requireLogin();

$playerId   = Auth::getUserId();
$worldMap   = new WorldMap();
$player     = new Player($playerId);
$market     = new Market();

$playerData = $player->getData();
$marketData = $market->getState();
$mapData    = $worldMap->getMapData($playerId);
$wellCount  = count($mapData['occupied']);
$companyDays = !empty($playerData['created_at']) ? max(0, (int)floor((time() - strtotime((string)$playerData['created_at'])) / 86400)) : 0;

$error   = '';
$success = '';
$isAjax  = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!RateLimiter::check('action')) {
        $error = t('map.error_ratelimit');
        if ($isAjax) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'message' => $error], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } elseif (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = t('map.error_csrf');
        if ($isAjax) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'message' => $error], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } elseif (($_POST['action'] ?? '') === 'buy_well') {
        $locationId = (int)($_POST['location_id'] ?? 0);
        $result = $worldMap->buyWellAtLocation($playerId, $locationId);
        if ($result['success']) {
            $success    = $result['message'];
            $mapData    = $worldMap->getMapData($playerId);
            $playerData = $player->getData();
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'success' => true,
                    'message' => $success,
                    'well_id' => $result['well_id'] ?? null,
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } else {
            $error = $result['message'];
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'success' => false,
                    'message' => $error,
                    'cost'    => $result['cost'] ?? null,
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }
}

$mapJson  = json_encode($mapData, JSON_UNESCAPED_UNICODE);
$pageTitle= t('map.page_title');

$viewData = compact(
    'mapData', 'mapJson', 'playerData', 'marketData',
    'error', 'success'
);
$viewData = array_merge(GameShell::data($playerId), $viewData);
$gameShellTitle = t('map.page_title');
$gameShellView = __DIR__ . '/../templates/views/map/main.php';
$extraCss = ['/assets/css/map.css'];

require_once __DIR__ . '/../templates/header.php';
extract($viewData, EXTR_SKIP);
require __DIR__ . '/../templates/components/game_shell.php';
require_once __DIR__ . '/../templates/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) GameLog::error('public/map.php', 'Unhandled exception', $e);
    echo t('common.error_generic');
} finally {
    if (class_exists('GameLog', false)) GameLog::pageEnd('public/map.php', $_pageStart);
}
