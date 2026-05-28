<?php
require_once __DIR__ . '/../src/init.php';

Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

function jsonOut(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success' => false, 'message' => t('common.err_unknown')], 405);
}

$token = $_POST['csrf'] ?? ($_POST['csrf_token'] ?? ($_POST['_token'] ?? ''));
if (!CSRF::validateToken($token)) {
    jsonOut(['success' => false, 'message' => t('common.csrf_error')], 419);
}

$action = (string)($_POST['action'] ?? '');
$wellId = (int)($_POST['well_id'] ?? 0);

if ($action !== 'switch') {
    jsonOut(['success' => false, 'message' => t('common.err_unknown')], 400);
}
if ($wellId <= 0) {
    jsonOut(['success' => false, 'message' => t('common.err_unknown')], 400);
}

$layerId = (int)($_POST['layer_id'] ?? 0);
if ($layerId <= 0) {
    jsonOut(['success' => false, 'message' => t('common.err_unknown')], 400);
}

try {
    $svc = new GeologicalLayerService();
    $res = $svc->switchLayer($wellId, Auth::getUserId(), $layerId);
    jsonOut($res, ($res['success'] ?? false) ? 200 : 400);
} catch (Throwable $e) {
    GameLog::error('layer_well.php', 'Switch layer failed', $e, ['well_id' => $wellId, 'layer_id' => $layerId]);
    jsonOut(['success' => false, 'message' => t('geology.err_server')], 500);
}
