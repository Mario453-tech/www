<?php
declare(strict_types=1);

/**
 * GET /api/v1/wells
 * GET /api/v1/wells?status=active         filtruj po statusie
 * GET /api/v1/wells?status=active,paused  kilka statusow
 *
 * Zwraca liste studni gracza.
 * Returns the player's list of wells.
 */
require_once dirname(__DIR__, 2) . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError(405, 'Method Not Allowed — use GET');
}

$player   = apiRequireAuth();
$playerId = (int)$player['id'];
$db       = Database::getInstance()->getConnection();

// Opcjonalny filtr statusu / Optional status filter
$allowedStatuses = ['active','paused','damaged','offline','sold','blowout'];
$statusFilter    = $_GET['status'] ?? null;
$statuses        = [];
if ($statusFilter !== null) {
    foreach (explode(',', $statusFilter) as $s) {
        $s = trim($s);
        if (in_array($s, $allowedStatuses, true)) {
            $statuses[] = $s;
        }
    }
}

if ($statuses !== []) {
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = $db->prepare("
        SELECT id, name, well_name, location_name, status, well_type,
               transport_type, base_production_per_hour, upkeep_cost_per_hour,
               technical_condition, wear_level, equipment_tier, equipment_upgrade_level,
               production_mode, reservoir_remaining, reservoir_max,
               risk_level, risk_score, regional_tax_rate,
               last_production_at, created_at
          FROM wells
         WHERE player_id = ? AND status IN ($placeholders)
         ORDER BY id ASC
    ");
    $stmt->execute(array_merge([$playerId], $statuses));
} else {
    $stmt = $db->prepare("
        SELECT id, name, well_name, location_name, status, well_type,
               transport_type, base_production_per_hour, upkeep_cost_per_hour,
               technical_condition, wear_level, equipment_tier, equipment_upgrade_level,
               production_mode, reservoir_remaining, reservoir_max,
               risk_level, risk_score, regional_tax_rate,
               last_production_at, created_at
          FROM wells
         WHERE player_id = ? AND status != 'sold'
         ORDER BY id ASC
    ");
    $stmt->execute([$playerId]);
}

$wells = [];
foreach ($stmt->fetchAll() as $w) {
    $wellName = $w['well_name'] ?: $w['name'] ?: ('Well #' . $w['id']);
    $wells[]  = [
        'id'                       => (int)$w['id'],
        'name'                     => $wellName,
        'location'                 => $w['location_name'],
        'status'                   => $w['status'],
        'well_type'                => $w['well_type'],
        'transport_type'           => $w['transport_type'],
        'production_per_hour'      => round((float)$w['base_production_per_hour'], 4),
        'upkeep_per_hour'          => round((float)$w['upkeep_cost_per_hour'], 2),
        'technical_condition'      => (int)($w['technical_condition'] ?? 100),
        'wear_level'               => (int)($w['wear_level'] ?? 0),
        'equipment_tier'           => $w['equipment_tier'] ?? 'standard',
        'equipment_upgrade_level'  => (int)($w['equipment_upgrade_level'] ?? 0),
        'production_mode'          => $w['production_mode'] ?? 'normal',
        'reservoir_remaining'      => round((float)$w['reservoir_remaining'], 2),
        'reservoir_max'            => round((float)($w['reservoir_max'] ?? 0), 2),
        'risk_level'               => $w['risk_level'] ?? 'low',
        'risk_score'               => round((float)($w['risk_score'] ?? 0), 2),
        'regional_tax_rate'        => round((float)($w['regional_tax_rate'] ?? 0), 4),
        'last_production_at'       => $w['last_production_at'],
        'created_at'               => $w['created_at'],
    ];
}

apiJson([
    'wells' => $wells,
    'count' => count($wells),
]);
