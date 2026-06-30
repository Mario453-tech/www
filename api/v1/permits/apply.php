<?php
declare(strict_types=1);

/**
 * POST /api/v1/permits/apply
 *
 * Składa wniosek o zezwolenie na wiercenie (P1) w podanym regionie.
 * Pobiera opłatę z konta gracza (cash + bank) i tworzy rekord 'pending'.
 * Decyzja jest rozpatrywana przez cron (tick działu prawnego).
 *
 * Submits a drilling-permit application (P1) for the given region.
 * Charges the player (cash + bank) and creates a 'pending' record.
 * The decision is processed by the cron (legal department tick).
 *
 * Body: { "region_id": <int> }
 * Response 200: { success:true, code, message, cost, review_minutes }
 * Response 422: { success:false, code, message }
 */
require_once dirname(__DIR__) . '/_bootstrap.php';
// $_API_ROOT = /home/.../www (ustawiane przez _bootstrap.php)
require_once $_API_ROOT . '/src/i18n.php';
require_once $_API_ROOT . '/src/LegalService.php'; // pulls full dependency chain

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError(405, 'Method Not Allowed — use POST');
}

$player   = apiRequireAuth();
$playerId = (int)$player['id'];

$body     = apiBody();
$regionId = isset($body['region_id']) ? (int)$body['region_id'] : 0;
if ($regionId <= 0) {
    apiError(400, 'region_id is required and must be a positive integer');
}

$legal  = new LegalService();
$result = $legal->submitApplication($playerId, $regionId);

if (!($result['success'] ?? false)) {
    apiJson([
        'success' => false,
        'code'    => $result['code']    ?? 'error',
        'message' => $result['message'] ?? 'Unknown error',
    ], 422);
}

apiJson([
    'success'        => true,
    'code'           => $result['code']           ?? 'submitted',
    'message'        => $result['message']         ?? '',
    'cost'           => $result['cost']            ?? null,
    'review_minutes' => $result['review_minutes']  ?? null,
]);
