<?php
/**
 * protection.php - endpoint AJAX modulu ochrony (aktywacja ochrony celu).
 * protection.php - protection module AJAX endpoint (activate protection on a target).
 * Zawsze zwraca JSON / Always returns JSON: {"success": bool, "message": string, ...}
 */

require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/ProtectionService.php';

header('Content-Type: application/json; charset=utf-8');

function protectionJson(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

GameLog::info('public/protection.php', 'entry');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    protectionJson(['success' => false, 'message' => tPlain('common.invalid_method')]);
}
if (!Auth::isLoggedIn()) {
    protectionJson(['success' => false, 'message' => tPlain('common.not_logged_in')]);
}
if (!RateLimiter::check('action')) {
    protectionJson(['success' => false, 'message' => tPlain('common.rate_limit')]);
}
if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    GameLog::warn('public/protection.php', 'CSRF validation failed');
    protectionJson(['success' => false, 'message' => tPlain('common.csrf_error')]);
}

$playerId   = Auth::getUserId();
$optionCode = (string)($_POST['option_code'] ?? '');
$wellId     = (int)($_POST['well_id'] ?? 0);

if ($optionCode === '' || $wellId <= 0) {
    protectionJson(['success' => false, 'message' => tPlain('protection.err_not_found')]);
}

try {
    $db = Database::getInstance()->getConnection();

    // Cel musi byc odwiertem gracza z transportem drogowym.
    // The target must be the player's well with road transport.
    $stmt = $db->prepare("SELECT transport_type FROM wells WHERE id = ? AND player_id = ?");
    $stmt->execute([$wellId, $playerId]);
    $transportType = $stmt->fetchColumn();
    if ($transportType === false) {
        protectionJson(['success' => false, 'message' => tPlain('protection.err_target_invalid')]);
    }
    if ((string)$transportType !== 'ciezarowki') {
        protectionJson(['success' => false, 'message' => tPlain('protection.err_target_not_road')]);
    }

    $svc = new ProtectionService($db);
    $res = $svc->activate($playerId, $optionCode, 'road_transport', $wellId, 0.0, [
        'label' => tPlain('protection.target_well', ['id' => $wellId]),
    ]);

    protectionJson($res);
} catch (Throwable $e) {
    GameLog::error('public/protection.php', 'Exception', $e, ['player_id' => $playerId ?? 0]);
    protectionJson(['success' => false, 'message' => tPlain('common.app_error')]);
}
