<?php
declare(strict_types=1);

/**
 * GET /api/v1/maps/
 *
 * Zwraca regiony świata (z pełnym statusem zezwolenia na wiercenie per region)
 * oraz dostępne lokalizacje z flagami zajętości.
 * Dane do wyświetlenia mapy + działu prawnego P1 w aplikacji mobilnej.
 *
 * Returns world regions (with full drilling-permit status per region)
 * and available locations with occupancy flags.
 * Data for the mobile map view + legal department P1.
 */
require_once dirname(__DIR__) . '/_bootstrap.php';
// $_API_ROOT = /home/.../www (ustawiane przez _bootstrap.php)
require_once $_API_ROOT . '/src/i18n.php';
require_once $_API_ROOT . '/src/LegalService.php'; // pulls CompanyCredibilityService, PlayerPaymentService, etc.
require_once $_API_ROOT . '/src/WorldMap.php';      // pulls PlayerPaymentService (no-op)

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError(405, 'Method Not Allowed — use GET');
}

$player   = apiRequireAuth();
$playerId = (int)$player['id'];
$db       = Database::getInstance()->getConnection();

$map  = new WorldMap($db);
$data = $map->getMapData($playerId);

// Dołącz application_cost per region (getMapData zwraca required_capital, ale nie koszt wniosku).
// Attach application_cost per region (getMapData returns required_capital, not the fee).
$costMap = [];
try {
    $costStmt = $db->query(
        "SELECT region_id, application_cost FROM legal_region_config"
    );
    foreach ($costStmt->fetchAll() as $row) {
        $costMap[(int)$row['region_id']] = round((float)$row['application_cost'], 2);
    }
} catch (Throwable) {
    // Puste — brak konfiguracji (świeża baza). App obsłuży null.
    // Empty — no config yet (fresh DB). App handles null gracefully.
}

$regions = [];
foreach ($data['regions'] as $r) {
    $rid = (int)$r['id'];
    $regions[] = [
        'id'            => $rid,
        'code'          => $r['code'],
        'name'          => $r['name'],
        'political_risk' => (int)$r['political_risk'],
        'entry_cost'    => round((float)$r['entry_cost'], 2),
        'tax_rate'      => round((float)$r['tax_rate'], 4),
        'opex_mult'     => round((float)($r['opex_mult'] ?? 1), 2),
        'color_hex'     => $r['color_hex'] ?? '#c8a84b',
        'permit' => [
            'status'               => $r['permit_status'] ?? 'none',
            'has_active'           => (bool)($r['has_permit'] ?? false),
            'minutes_left'         => $r['permit_minutes_left'] !== null ? (int)$r['permit_minutes_left'] : null,
            'cooldown_minutes'     => $r['permit_cooldown_minutes'] !== null ? (int)$r['permit_cooldown_minutes'] : null,
            'application_cost'     => $costMap[$rid] ?? null,
            'required_capital'     => $r['permit_required_capital'] !== null
                ? round((float)$r['permit_required_capital'], 2) : null,
            'required_legal_level' => $r['permit_required_legal_level'] !== null
                ? (int)$r['permit_required_legal_level'] : null,
        ],
    ];
}

$locations = [];
foreach ($data['locations'] as $l) {
    $locations[] = [
        'id'                   => (int)$l['id'],
        'region_id'            => (int)$l['region_id'],
        'name'                 => $l['name'],
        'latitude'             => $l['latitude'] !== null ? round((float)$l['latitude'], 6) : null,
        'longitude'            => $l['longitude'] !== null ? round((float)$l['longitude'], 6) : null,
        'oil_richness'         => round((float)$l['oil_richness'], 2),
        'well_type'            => $l['well_type'],
        'tier'                 => $l['tier'] ?? 'medium',
        'effective_entry_cost' => round((float)($l['effective_entry_cost'] ?? 0), 2),
        'effective_tax_rate'   => round((float)($l['effective_tax_rate'] ?? 0), 4),
        'occupied_by_me'       => (bool)($l['occupied_by_me'] ?? false),
        'occupied_by_anyone'   => (bool)($l['occupied_by_anyone'] ?? false),
        'my_well_id'           => isset($l['my_well_id']) ? (int)$l['my_well_id'] : null,
        'my_well_status'       => $l['my_well_status'] ?? null,
    ];
}

apiJson([
    'regions'    => $regions,
    'locations'  => $locations,
    'well_count' => count($data['occupied']),
]);
