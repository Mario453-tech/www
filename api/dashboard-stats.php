<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/init.php';

header('Content-Type: application/json; charset=UTF-8');
if (!Auth::isLoggedIn() && !Auth::tryRememberMe()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$playerId = Auth::getUserId();
$period = HomeDashboardQuery::normalizePeriod((string)($_GET['period'] ?? '30d'));
try {
    $wells = (new Well($playerId))->getWells();
    $model = (new HomeDashboardQuery(Database::getInstance()->getConnection()))
        ->get($playerId, $period, $wells);
    echo json_encode($model, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    GameLog::error('dashboard-stats', 'Statistics request failed', $e, ['player_id' => $playerId]);
    http_response_code(500);
    echo json_encode(['error' => 'unavailable']);
}
