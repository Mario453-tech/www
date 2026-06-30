<?php
declare(strict_types=1);

/**
 * POST /api/v1/technical/fire.php
 *
 * Fires (dismisses) a technical staff member.
 * Zwalnia pracownika technicznego.
 *
 * Body: {"staff_id": 1}
 * 200: {success:true, message}
 * 422: {success:false, message}
 */
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once $_API_ROOT . '/src/i18n.php';
require_once $_API_ROOT . '/src/TechnicalTeamService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError(405, 'Method Not Allowed — use POST');
}

$player   = apiRequireAuth();
$playerId = (int)$player['id'];

$body    = apiBody();
$staffId = isset($body['staff_id']) ? (int)$body['staff_id'] : 0;

if ($staffId <= 0) {
    apiError(400, 'Missing or invalid staff_id');
}

$svc    = new TechnicalTeamService($playerId);
$result = $svc->fireEngineer($staffId);

if ($result['success']) {
    apiJson(['success' => true, 'message' => (string)($result['message'] ?? '')]);
} else {
    apiJson(['success' => false, 'message' => (string)($result['message'] ?? '')], 422);
}
