<?php
/**
 * WellStaffApi.php - AJAX endpoint dla przypisania personelu do odwiertow
 * URL: /src/WellStaffApi.php
 * Metoda: POST (assign/unassign) | GET (get_status, get_available)
 */

ob_start();
require_once __DIR__ . '/init.php';
ob_clean();
header('Content-Type: application/json; charset=utf-8');

function jsonOut(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!Auth::isLoggedIn()) {
    jsonOut(['success' => false, 'error' => t('common.not_logged_in')], 401);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['_token'] ?? '')) {
        jsonOut(['success' => false, 'error' => t('common.csrf_error')], 419);
    }
}

$playerId = Auth::getUserId();
$svc      = new WellStaffService($playerId);
$db       = Database::getInstance()->getConnection();
$action   = $_REQUEST['action'] ?? '';

try {
    switch ($action) {

        case 'get_status':
            jsonOut([
                'success' => true,
                'wells'   => $svc->getWellsStaffStatus(),
            ]);

        case 'get_available':
            $role = $_GET['role'] ?? 'operator';
            jsonOut([
                'success' => true,
                'staff'   => $svc->getAvailableStaff($role),
            ]);

        case 'assign':
            $wellId  = (int)($_POST['well_id']  ?? 0);
            $staffId = (int)($_POST['staff_id'] ?? 0);
            $role    = $_POST['role'] ?? '';
            if (!$wellId || !$staffId || !$role) {
                jsonOut(['success' => false, 'error' => t('well_staff.err_missing_params_assign')], 400);
            }
            jsonOut($svc->assign($wellId, $staffId, $role));

        case 'unassign':
            $wellId = (int)($_POST['well_id'] ?? 0);
            $role   = $_POST['role'] ?? '';
            if (!$wellId || !$role) {
                jsonOut(['success' => false, 'error' => t('well_staff.err_missing_params_role')], 400);
            }
            jsonOut($svc->unassign($wellId, $role));

        case 'set_transport':
            $wellId = (int)($_POST['well_id'] ?? 0);
            $trType = trim($_POST['transport_type'] ?? '');
            $allowed = ['rurociag', 'ciezarowki', 'tankowiec'];
            if (!$wellId || !in_array($trType, $allowed)) {
                jsonOut(['success' => false, 'message' => t('common.invalid_data')], 400);
                break;
            }
            $chk = $db->prepare("SELECT id FROM wells WHERE id=? AND player_id=? LIMIT 1");
            $chk->execute([$wellId, $playerId]);
            if (!$chk->fetch()) {
                jsonOut(['success' => false, 'message' => t('common.access_denied')], 403);
                break;
            }
            $trParams = [
                'rurociag'   => ['capacity' => 120.00, 'opex' =>  7.5],
                'ciezarowki' => ['capacity' =>  70.00, 'opex' => 20.0],
                'tankowiec'  => ['capacity' => 110.00, 'opex' => 12.0],
            ];
            $p = $trParams[$trType];
            $db->prepare("UPDATE wells SET transport_type=?, transport_capacity_pct=?, transport_opex_pct=? WHERE id=? AND player_id=?")
               ->execute([$trType, $p['capacity'], $p['opex'], $wellId, $playerId]);
            $names = ['rurociag' => t('well_staff.transport_pipeline'), 'ciezarowki' => t('well_staff.transport_trucks'), 'tankowiec' => t('well_staff.transport_tanker')];
            GameLog::info('WellStaffApi', 'set_transport', ['well_id' => $wellId, 'transport' => $trType]);
            jsonOut(['success' => true, 'message' => t('well_staff.msg_transport_set', ['name' => $names[$trType]])]);
            break;

        default:
            jsonOut(['success' => false, 'error' => t('well_staff.err_unknown_action', ['action' => $action])], 400);
    }
} catch (Throwable $e) {
    GameLog::error('WellStaffApi', 'unhandled exception', $e);
    jsonOut(['success' => false, 'error' => t('common.app_error')], 500);
}
