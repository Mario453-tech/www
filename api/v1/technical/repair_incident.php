<?php
declare(strict_types=1);

/**
 * POST /api/v1/technical/repair_incident.php
 *
 * Manually repairs a medium/major well incident (auto_repair=0).
 * Charges the incident's repair cost and sets repaired_at; a 'major' repair
 * restores a 'broken' well to 'active'.
 * Ręczna naprawa incydentu medium/major (auto_repair=0). Pobiera koszt naprawy
 * i ustawia repaired_at; naprawa 'major' przywraca odwiert 'broken' do 'active'.
 *
 * Body: {"incident_id": 123}
 *
 * 200: {success:true, message}
 * 422: {success:false, message}
 */
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once $_API_ROOT . '/src/i18n.php';
require_once $_API_ROOT . '/src/IncidentService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError(405, 'Method Not Allowed — use POST');
}

$player   = apiRequireAuth();
$playerId = (int)$player['id'];

$body       = apiBody();
$incidentId = isset($body['incident_id']) ? (int)$body['incident_id'] : 0;

if ($incidentId <= 0) {
    apiError(400, 'Missing required field: incident_id');
}

$svc    = new IncidentService();
$result = $svc->repairIncident($incidentId, $playerId);

if (!empty($result['success'])) {
    apiJson(['success' => true, 'message' => (string)($result['message'] ?? '')]);
} else {
    apiJson(['success' => false, 'message' => (string)($result['message'] ?? '')], 422);
}
