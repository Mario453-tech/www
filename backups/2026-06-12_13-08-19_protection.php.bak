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
$target     = (string)($_POST['target'] ?? 'road');
// Wsteczna zgodnosc: stare wywolanie z well_id == cel drogowy.
// Backward compatibility: legacy well_id call == road target.
$targetId   = (int)($_POST['target_id'] ?? ($_POST['well_id'] ?? 0));

$targetMap = [
    'road'     => ['target_type' => 'road_transport', 'context' => 'road_transport_guard'],
    'hub'      => ['target_type' => 'hub',            'context' => 'hub_guard'],
    'pipeline' => ['target_type' => 'pipeline',       'context' => 'pipeline_guard'],
];

if ($optionCode === '' || $targetId <= 0 || !isset($targetMap[$target])) {
    protectionJson(['success' => false, 'message' => tPlain('protection.err_not_found')]);
}

try {
    $db = Database::getInstance()->getConnection();

    // Walidacja wlasnosci celu per typ. / Per-type target ownership validation.
    if ($target === 'road') {
        $stmt = $db->prepare("SELECT transport_type FROM wells WHERE id = ? AND player_id = ?");
        $stmt->execute([$targetId, $playerId]);
        $transportType = $stmt->fetchColumn();
        if ($transportType === false) {
            protectionJson(['success' => false, 'message' => tPlain('protection.err_target_invalid')]);
        }
        if ((string)$transportType !== 'ciezarowki') {
            protectionJson(['success' => false, 'message' => tPlain('protection.err_target_not_road')]);
        }
        $label = tPlain('protection.target_well', ['id' => $targetId]);
    } elseif ($target === 'hub') {
        $stmt = $db->prepare(
            "SELECT 1 FROM logistics_hubs WHERE id = ? AND (player_id = ? OR tenant_player_id = ?)"
        );
        $stmt->execute([$targetId, $playerId, $playerId]);
        if ($stmt->fetchColumn() === false) {
            protectionJson(['success' => false, 'message' => tPlain('protection.err_target_invalid')]);
        }
        $label = tPlain('protection.target_hub', ['id' => $targetId]);
    } else { // pipeline
        $stmt = $db->prepare("SELECT 1 FROM well_pipelines WHERE id = ? AND player_id = ?");
        $stmt->execute([$targetId, $playerId]);
        if ($stmt->fetchColumn() === false) {
            protectionJson(['success' => false, 'message' => tPlain('protection.err_target_invalid')]);
        }
        $label = tPlain('protection.target_pipeline', ['id' => $targetId]);
    }

    $svc = new ProtectionService($db);
    $res = $svc->activate(
        $playerId,
        $optionCode,
        $targetMap[$target]['target_type'],
        $targetId,
        0.0,
        ['label' => $label],
        $targetMap[$target]['context']
    );

    protectionJson($res);
} catch (Throwable $e) {
    GameLog::error('public/protection.php', 'Exception', $e, ['player_id' => $playerId ?? 0]);
    protectionJson(['success' => false, 'message' => tPlain('common.app_error')]);
}
