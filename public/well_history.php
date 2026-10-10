<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/init.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => tPlain('common.not_logged_in')]);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => tPlain('common.invalid_method')]);
    exit;
}
try {
    $result = (new WellHistoryService(Database::getInstance()->getConnection()))->page(
        Auth::getUserId(), (int)($_GET['well_id'] ?? 0), (string)($_GET['filter'] ?? 'all'), (int)($_GET['page'] ?? 1)
    );
    if ($result === null) {
        http_response_code(404);
        echo json_encode(['error' => tPlain('well.err_not_found')]);
        exit;
    }
    $result['events'] = array_map(static function (array $row): array {
        $eventType = (string)$row['event_type'];
        $categoryKey = WellHistoryService::category($eventType);
        $category = tPlain('ww.filter_' . $categoryKey);
        $known = in_array($eventType, ['maintenance','repair','pump_replaced','upgrade','failure','inspection'], true);
        $icon = match ($categoryKey) {
            'failures' => 'status-krytyczny',
            'service' => 'repair',
            'configuration' => 'upgrade',
            'production' => 'flow',
            'transport' => 'truck',
            default => 'history',
        };
        $description = tPlain('ww.history_description', ['category' => $category]);
        if ((float)$row['cost'] !== 0.0) $description .= ' ' . tPlain('ww.history_cost', ['amount' => number_format((float)$row['cost'], 2, '.', ' ')]);
        if ($row['technical_condition_before'] !== null && $row['technical_condition_after'] !== null) {
            $description .= ' ' . tPlain('ww.history_condition', ['before' => $row['technical_condition_before'], 'after' => $row['technical_condition_after']]);
        }
        return ['id' => (int)$row['id'], 'date' => $row['created_at'], 'category' => $category, 'icon' => $icon,
            'title' => tPlain($known ? 'ww.event_' . $eventType : 'ww.history_event'), 'description' => $description];
    }, $result['events']);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    GameLog::error('well_history', 'History read failed', $e);
    http_response_code(500);
    echo json_encode(['error' => tPlain('common.err_retry')]);
}
