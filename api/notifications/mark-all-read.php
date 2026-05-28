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

try {
    $notificationService = new DirectorNotificationService();
    $count = $notificationService->markAllAsRead($playerId);
    
    echo json_encode([
        'success' => true,
        'count' => $count,
        'message' => "Marked {$count} notifications as read"
    ]);
} catch (Exception $e) {
    GameLog::error('API', 'mark-all-read failed', $e);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
