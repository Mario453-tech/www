<?php
declare(strict_types=1);

/**
 * POST /api/v1/technical/well_staff_unassign.php
 *
 * Removes a staff member from a well role.
 * Odpisuje pracownika technicznego od odwiertu.
 *
 * Body: {"well_id": 1, "role": "operator"|"technician"}
 * 200: {success:true, message}
 * 422: {success:false, message}
 */
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once $_API_ROOT . '/src/i18n.php';
require_once $_API_ROOT . '/src/WellStaffService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError(405, 'Method Not Allowed — use POST');
}

$player   = apiRequireAuth();
$playerId = (int)$player['id'];

$body   = apiBody();
$wellId = isset($body['well_id']) ? (int)$body['well_id']          : 0;
$role   = isset($body['role'])    ? trim((string)$body['role'])     : '';

if ($wellId <= 0 || !in_array($role, ['operator', 'technician'], true)) {
    apiError(400, 'Missing required fields: well_id, role');
}

$svc    = new WellStaffService($playerId);
$result = $svc->unassign($wellId, $role);

if ($result['success']) {
    apiJson(['success' => true, 'message' => (string)($result['message'] ?? '')]);
} else {
    apiJson(['success' => false, 'message' => (string)($result['message'] ?? '')], 422);
}
