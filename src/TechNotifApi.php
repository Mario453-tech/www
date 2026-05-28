<?php
/**
 * TechNotifApi - endpoint for marking technical notifications as read.
 * PL: TechNotifApi - endpoint do oznaczania technicznych powiadomien jako przeczytane.
 */
ob_start();
require_once __DIR__ . '/init.php';
ob_clean();
header('Content-Type: application/json; charset=utf-8');

if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false]);
    exit;
}

$playerId = Auth::getUserId();
$action = $_POST['action'] ?? '';
$db = Database::getInstance()->getConnection();

try {
    if ($action === 'mark_read') {
        $id = (int)($_POST['notif_id'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE technical_notifications SET is_read = 1 WHERE id = ? AND player_id = ?")
                ->execute([$id, $playerId]);
        }
        echo json_encode(['success' => true]);
    } elseif ($action === 'mark_all_read') {
        $db->prepare("UPDATE technical_notifications SET is_read = 1 WHERE player_id = ?")
            ->execute([$playerId]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => t('tech_notif_api.err_unknown_action')]);
    }
} catch (Throwable $e) {
    GameLog::error('TechNotifApi', 'FAILED', $e, ['player_id' => $playerId]);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
