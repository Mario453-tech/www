<?php
declare(strict_types=1);

/**
 * GET /api/v1/technical/available_tasks.php?staff_id=N
 *
 * Returns tasks available for the given engineer (filtered by spec_code)
 * and the player's wells for the well selector in the mobile assign-task sheet.
 * Zwraca zadania dostepne dla danego inzyniera (filtrowane po spec_code)
 * oraz odwierty gracza do selektora w arkuszu zlecania zadania.
 *
 * Response: {tasks: [{type, label, needs_well, needs_hub, needs_pipeline,
 *                     hours_min, hours_max, cost_min, cost_max}],
 *            wells: [{id, name, status}]}
 */
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once $_API_ROOT . '/src/i18n.php';
require_once $_API_ROOT . '/src/TaskConfigService.php';
require_once $_API_ROOT . '/src/TechnicalTeamService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError(405, 'Method Not Allowed — use GET');
}

$player   = apiRequireAuth();
$playerId = (int)$player['id'];

$staffId = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;
if ($staffId <= 0) {
    apiError(400, 'Missing or invalid staff_id');
}

$svc    = new TechnicalTeamService($playerId);
$member = $svc->getStaffMember($staffId);

if (!$member || (int)$member['player_id'] !== $playerId) {
    apiError(404, 'Staff member not found');
}

$specCode = (string)($member['spec_code'] ?? '');
$allTasks = TechnicalTeamService::getTasksCatalog();
$tasks    = [];

foreach ($allTasks as $type => $def) {
    if (!in_array($specCode, (array)($def['assignable'] ?? []), true)) {
        continue;
    }
    $tasks[] = [
        'type'           => $type,
        'label'          => (string)($def['label'] ?? $type),
        'needs_well'     => (bool)($def['needs_well']     ?? false),
        'needs_hub'      => (bool)($def['needs_hub']      ?? false),
        'needs_pipeline' => (bool)($def['needs_pipeline'] ?? false),
        'hours_min'      => (int)($def['hours_min'] ?? 0),
        'hours_max'      => (int)($def['hours_max'] ?? 0),
        'cost_min'       => (int)($def['cost_min']  ?? 0),
        'cost_max'       => (int)($def['cost_max']  ?? 0),
    ];
}

// Player's wells for the well picker / Odwierty gracza do selektora
$db = Database::getInstance()->getConnection();
$ws = $db->prepare(
    "SELECT id,
            COALESCE(location_name, well_name, name, CONCAT('Well #', id)) AS name,
            status
       FROM wells
      WHERE player_id = ? AND status NOT IN ('sold', 'abandoned')
      ORDER BY name ASC
      LIMIT 100"
);
$ws->execute([$playerId]);
$wells = [];
foreach ($ws->fetchAll() as $w) {
    $wells[] = [
        'id'     => (int)$w['id'],
        'name'   => (string)$w['name'],
        'status' => (string)$w['status'],
    ];
}

apiJson([
    'tasks' => $tasks,
    'wells' => $wells,
]);
