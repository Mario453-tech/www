<?php
declare(strict_types=1);

/**
 * POST /api/v1/technical/assign_task.php
 *
 * Assigns a task to a technical staff member.
 * Zleca zadanie pracownikowi technicznemu.
 *
 * Body: {"staff_id": 1, "task_type": "well_maintenance", "well_id": 2}
 * well_id is optional; required only when the task definition has needs_well=true.
 * well_id jest opcjonalne; wymagane tylko gdy zadanie ma needs_well=true.
 *
 * 200: {success:true, message, hours_min, hours_max}
 * 422: {success:false, message}
 */
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once $_API_ROOT . '/src/i18n.php';
require_once $_API_ROOT . '/src/TaskConfigService.php';
require_once $_API_ROOT . '/src/TechnicalTeamService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError(405, 'Method Not Allowed — use POST');
}

$player   = apiRequireAuth();
$playerId = (int)$player['id'];

$body     = apiBody();
$staffId  = isset($body['staff_id'])  ? (int)$body['staff_id']         : 0;
$taskType = isset($body['task_type']) ? trim((string)$body['task_type']) : '';
$wellId   = isset($body['well_id'])   ? (int)$body['well_id']           : null;

if ($staffId <= 0 || $taskType === '') {
    apiError(400, 'Missing required fields: staff_id and task_type');
}

$svc    = new TechnicalTeamService($playerId);
$result = $svc->assignTask($staffId, $taskType, ($wellId !== null && $wellId > 0) ? $wellId : null);

if ($result['success']) {
    $taskDef = TechnicalTeamService::getTaskDefinition($taskType);
    apiJson([
        'success'   => true,
        'message'   => (string)($result['message'] ?? ''),
        'hours_min' => (int)($taskDef['hours_min'] ?? 0),
        'hours_max' => (int)($taskDef['hours_max'] ?? 0),
    ]);
} else {
    apiJson(['success' => false, 'message' => (string)($result['message'] ?? '')], 422);
}
