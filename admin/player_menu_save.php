<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!AdminAuth::isLoggedIn() || empty($_SESSION['admin_id'])) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'auth']);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::validateToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'csrf']);
        exit;
    }
    require_once __DIR__ . '/../src/PlayerMenuEditor.php';
    $input = json_decode((string)($_POST['payload'] ?? ''), true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($input)) throw new InvalidArgumentException('structure');
    $db = Database::getInstance()->getConnection();
    $saved = PlayerMenuEditor::save($db, $input);
    try {
        AdminLog::log('player_menu_batch_update', 'Player menu saved by ' . AdminAuth::getAdminUsername());
    } catch (Throwable $auditError) {
        if (class_exists('GameLog', false)) GameLog::error('admin/player_menu_save', 'Menu audit failed', $auditError);
    }
    echo json_encode(['ok' => true, 'data' => $saved], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    $reason = $e instanceof RuntimeException && $e->getMessage() === 'conflict' ? 'conflict' : 'invalid';
    if ($e instanceof JsonException) $reason = 'invalid';
    http_response_code($reason === 'conflict' ? 409 : 422);
    if (class_exists('GameLog', false)) GameLog::error('admin/player_menu_save', 'Menu update failed', $e);
    echo json_encode(['ok' => false, 'error' => $reason], JSON_UNESCAPED_UNICODE);
}
