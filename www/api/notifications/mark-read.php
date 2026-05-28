<?php
require_once __DIR__ . '/../../src/init.php';

header('Content-Type: application/json');

Auth::requireLogin();

$playerId = Auth::getUserId();
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['csrf_token']) || !CSRF::validateToken($data['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

if (!isset($data['notification_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing notification_id']);
    exit;
}

try {
    $notificationService = new DirectorNotificationService();
    $result = $notificationService->markAsRead((int)$data['notification_id'], $playerId);
    
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Notification marked as read' : 'Failed to mark notification'
    ]);
} catch (Exception $e) {
    GameLog::error('API', 'mark-read failed', $e);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
