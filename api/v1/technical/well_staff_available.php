<?php
declare(strict_types=1);

/**
 * GET /api/v1/technical/well_staff_available.php?role=operator|technician
 *
 * Returns available technical staff for a given well role.
 * Zwraca dostepnych pracownikow technicznych dla danej roli przy odwiercie.
 *
 * Response: {staff: [{id, first_name, last_name, spec_code, spec_name, skill_level, status}]}
 */
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once $_API_ROOT . '/src/i18n.php';
require_once $_API_ROOT . '/src/WellStaffService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError(405, 'Method Not Allowed — use GET');
}

$player   = apiRequireAuth();
$playerId = (int)$player['id'];

$role = isset($_GET['role']) ? trim((string)$_GET['role']) : '';
if (!in_array($role, ['operator', 'technician'], true)) {
    apiError(400, 'Missing or invalid role — use operator or technician');
}

$svc   = new WellStaffService($playerId);
$staff = $svc->getAvailableStaff($role);

$result = [];
foreach ($staff as $s) {
    $result[] = [
        'id'         => (int)$s['id'],
        'first_name' => (string)($s['first_name'] ?? ''),
        'last_name'  => (string)($s['last_name']  ?? ''),
        'spec_code'  => (string)($s['spec_code']  ?? ''),
        'spec_name'  => (string)($s['spec_name']  ?? ''),
        'skill_level' => (int)($s['skill_level']  ?? 1),
        'status'     => (string)($s['status']     ?? 'active'),
        'assigned_well_name' => isset($s['assigned_well_name']) && $s['assigned_well_name'] !== null
            ? (string)$s['assigned_well_name'] : null,
    ];
}

apiJson(['staff' => $result]);
