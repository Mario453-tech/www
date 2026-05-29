<?php
/**
 * HubApi.php � AJAX endpoint dla modu�u hub�w logistycznych.
 * HubApi.php � AJAX endpoint for the logistics hubs module.
 *
 * URL: /src/HubApi.php
 * Metody: POST (akcje gracza) | GET (dane odczytu)
 * Methods: POST (player actions) | GET (read data)
 *
 * Akcje gracza POST: assign_well, detach_well, transfer_well
 * Akcje ADMIN POST:  build_hub, repair_hub, upgrade_hub, set_mode, toggle_pause, rename_hub
 * Akcje GET:         hub_wells, assignable_hubs, unassigned_wells, hub_detail
 *
 * Huby s� infrastruktur� systemow� (player_id = 0).
 * Hubs are system infrastructure (player_id = 0).
 * Gracz przypisuje swoje odwierty do istniej�cych hub�w � nie buduje hub�w samodzielnie.
 * Players assign their wells to existing hubs � they do not build hubs themselves.
 */

ob_start();
require_once __DIR__ . '/init.php';
ob_clean();
header('Content-Type: application/json; charset=utf-8');

function hubApiOut(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!Auth::isLoggedIn()) {
    hubApiOut(['success' => false, 'error' => t('common.not_logged_in')], 401);
}

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isPost && !CSRF::validateToken($_POST['_token'] ?? '')) {
    hubApiOut(['success' => false, 'error' => t('common.csrf_error')], 419);
}

if (class_exists('BoardAccess', false) && !BoardAccess::has(Auth::getUserId(), 'logistics')) {
    hubApiOut(['success' => false, 'error' => t('common.access_denied')], 403);
}

$playerId = Auth::getUserId();
$isAdmin  = function_exists('Auth::isAdmin') ? Auth::isAdmin() : false;
// Fallback: check via session or role table if Auth::isAdmin() doesn't exist
if (!$isAdmin && method_exists('Auth', 'hasRole')) {
    $isAdmin = Auth::hasRole('admin') || Auth::hasRole('superadmin');
}
$action   = $_REQUEST['action'] ?? '';

// Admin-only actions � block for regular players
$adminOnlyActions = ['build_hub', 'repair_hub', 'upgrade_hub', 'set_mode', 'toggle_pause', 'rename_hub'];
if (in_array($action, $adminOnlyActions, true) && !$isAdmin) {
    hubApiOut(['success' => false, 'error' => t('common.access_denied')], 403);
}

try {
    $db        = Database::getInstance()->getConnection();
    $hubSvc    = new HubService($db);
    $assignSvc = new HubAssignmentService($db, $hubSvc);
    $acqSvc    = new HubAcquisitionService($db, $hubSvc);

    // One-time migration: existing well assignments → tenancy. Idempotent.
    $acqSvc->migrateExistingAssignmentsToTenancy();

    switch ($action) {

        // POST: build hub (ADMIN ONLY)
        case 'build_hub':
            $name     = trim($_POST['name']     ?? '');
            $type     = trim($_POST['hub_type'] ?? 'small');
            $acqType  = trim($_POST['acquisition_type'] ?? 'new');
            $regionId = (int)($_POST['region_id'] ?? 0);
            $zoneKey  = trim($_POST['zone_key'] ?? '');

            if ($name === '' || $regionId <= 0) {
                hubApiOut(['success' => false, 'error' => t('common.validation_error')], 422);
            }
            $result = $hubSvc->buildHub($playerId, [
                'name'             => $name,
                'hub_type'         => $type,
                'acquisition_type' => $acqType,
                'region_id'        => $regionId,
                'zone_key'         => $zoneKey,
            ]);
            if (!$result['success']) {
                hubApiOut(['success' => false, 'error' => t('logistics.hub.err_generic')]);
            }
            hubApiOut(['success' => true, 'message' => t('logistics.hub.ok_build'), 'hub_id' => $result['hub_id'] ?? null]);

        // POST: repair hub (ADMIN ONLY)
        case 'repair_hub':
            $hubId = (int)($_POST['hub_id'] ?? 0);
            if ($hubId <= 0) hubApiOut(['success' => false, 'error' => t('common.validation_error')], 422);
            $result = $hubSvc->repairHub($hubId, $playerId);
            if (!$result['success']) {
                hubApiOut(['success' => false, 'error' => t('logistics.hub.err_generic')]);
            }
            hubApiOut(['success' => true, 'message' => t('logistics.hub.ok_repair')]);

        // POST: upgrade hub (ADMIN ONLY)
        case 'upgrade_hub':
            $hubId = (int)($_POST['hub_id'] ?? 0);
            if ($hubId <= 0) hubApiOut(['success' => false, 'error' => t('common.validation_error')], 422);
            $result = $hubSvc->upgradeHub($hubId, $playerId);
            if (!$result['success']) {
                $err = ($result['error'] ?? '') === 'max_level'
                    ? t('logistics.hub.err_max_level')
                    : t('logistics.hub.err_generic');
                hubApiOut(['success' => false, 'error' => $err]);
            }
            hubApiOut(['success' => true, 'message' => t('logistics.hub.ok_upgrade', ['level' => $result['new_level'] ?? '?'])]);

        // POST: change work mode (ADMIN ONLY)
        case 'set_mode':
            $hubId = (int)($_POST['hub_id'] ?? 0);
            $mode  = trim($_POST['mode']    ?? 'standard');
            if ($hubId <= 0 || !in_array($mode, ['eco', 'standard', 'max'], true)) {
                hubApiOut(['success' => false, 'error' => t('common.validation_error')], 422);
            }
            $result = $hubSvc->setWorkMode($hubId, $playerId, $mode);
            if (!$result['success']) {
                hubApiOut(['success' => false, 'error' => t('logistics.hub.err_generic')]);
            }
            $modeLabel = t('logistics.hub.mode_' . $mode);
            hubApiOut(['success' => true, 'message' => t('logistics.hub.ok_mode', ['mode' => $modeLabel])]);

        // POST: pause / resume hub (ADMIN ONLY)
        case 'toggle_pause':
            $hubId = (int)($_POST['hub_id'] ?? 0);
            if ($hubId <= 0) hubApiOut(['success' => false, 'error' => t('common.validation_error')], 422);
            $result = $hubSvc->toggleHubPause($hubId, $playerId);
            if (!$result['success']) {
                hubApiOut(['success' => false, 'error' => t('logistics.hub.err_generic')]);
            }
            $msg = ($result['new_status'] ?? '') === 'paused'
                ? t('logistics.hub.ok_pause')
                : t('logistics.hub.ok_resume');
            hubApiOut(['success' => true, 'message' => $msg, 'new_status' => $result['new_status'] ?? null]);

        // POST: rename hub (ADMIN ONLY)
        case 'rename_hub':
            $hubId = (int)($_POST['hub_id'] ?? 0);
            $name  = trim($_POST['name']    ?? '');
            if ($hubId <= 0 || $name === '') {
                hubApiOut(['success' => false, 'error' => t('common.validation_error')], 422);
            }
            $result = $hubSvc->renameHub($hubId, $playerId, $name);
            if (!$result['success']) {
                hubApiOut(['success' => false, 'error' => t('logistics.hub.err_generic')]);
            }
            hubApiOut(['success' => true, 'message' => t('logistics.hub.ok_rename')]);

        // POST: assign well to hub
        case 'assign_well':
            $hubId  = (int)($_POST['hub_id']  ?? 0);
            $wellId = (int)($_POST['well_id'] ?? 0);
            if ($hubId <= 0 || $wellId <= 0) hubApiOut(['success' => false, 'error' => t('common.validation_error')], 422);
            $result = $assignSvc->assignWell($playerId, $hubId, $wellId);
            if (!$result['success']) {
                if (($result['error'] ?? '') === 'cooldown_active') {
                    $remainSecs = (int)($result['cooldown_remaining_s'] ?? 0);
                    $remainH    = intdiv($remainSecs, 3600);
                    $remainM    = intdiv($remainSecs % 3600, 60);
                    $timeStr    = $remainH > 0
                        ? "{$remainH}h {$remainM}min"
                        : "{$remainM}min";
                    hubApiOut([
                        'success'             => false,
                        'error'               => t('logistics.hub.err_cooldown', ['time' => $timeStr]),
                        'cooldown_remaining_s'=> $remainSecs,
                    ]);
                }
                $err = match($result['error'] ?? '') {
                    'slots_full'         => t('logistics.hub.err_slots_full'),
                    'region_mismatch'    => t('logistics.hub.err_region_mismatch'),
                    'already_assigned'   => t('logistics.hub.err_generic'),
                    'insufficient_funds' => t('logistics.hub.err_insufficient_funds'),
                    default              => t('logistics.hub.err_generic'),
                };
                hubApiOut(['success' => false, 'error' => $err]);
            }
            $resp = [
                'success'          => true,
                'message'          => t('logistics.hub.ok_assign'),
                'access_fee_paid'  => $result['access_fee'] ?? 0.0,
            ];
            if (!empty($result['warning'])) {
                $resp['warning'] = match($result['warning']) {
                    'condition_critical' => t('logistics.hub.warn_condition_critical'),
                    'condition_low'      => t('logistics.hub.warn_condition_low'),
                    default              => null,
                };
            }
            hubApiOut($resp);

        // POST: detach well from hub
        case 'detach_well':
            $wellId = (int)($_POST['well_id'] ?? 0);
            if ($wellId <= 0) hubApiOut(['success' => false, 'error' => t('common.validation_error')], 422);
            $result = $assignSvc->detachWell($playerId, $wellId);
            if (!$result['success']) {
                hubApiOut(['success' => false, 'error' => t('logistics.hub.err_generic')]);
            }
            hubApiOut(['success' => true, 'message' => t('logistics.hub.ok_detach')]);

        // POST: transfer well between hubs
        case 'transfer_well':
            $wellId   = (int)($_POST['well_id']    ?? 0);
            $newHubId = (int)($_POST['new_hub_id'] ?? 0);
            if ($wellId <= 0 || $newHubId <= 0) hubApiOut(['success' => false, 'error' => t('common.validation_error')], 422);
            $result = $assignSvc->transferWell($playerId, $wellId, $newHubId);
            if (!$result['success']) {
                $err = match($result['error'] ?? '') {
                    'slots_full'      => t('logistics.hub.err_slots_full'),
                    'region_mismatch' => t('logistics.hub.err_region_mismatch'),
                    'same_hub'        => t('logistics.hub.err_generic'),
                    default           => t('logistics.hub.err_generic'),
                };
                hubApiOut(['success' => false, 'error' => $err]);
            }
            hubApiOut(['success' => true, 'message' => t('logistics.hub.ok_assign')]);

        // POST: player buys a new hub (builds it in their region)
        case 'buy_new_hub':
            $hubType  = trim($_POST['hub_type']  ?? 'small');
            $regionId = (int)($_POST['region_id'] ?? 0);
            $zoneKey  = trim($_POST['zone_key']   ?? '');
            $name     = trim($_POST['name']       ?? '');
            if ($regionId <= 0 || $name === '') {
                hubApiOut(['success' => false, 'error' => t('common.validation_error')], 422);
            }
            $result = $acqSvc->buyNew($playerId, [
                'hub_type'  => $hubType,
                'region_id' => $regionId,
                'zone_key'  => $zoneKey,
                'name'      => $name,
            ]);
            if (!$result['success']) {
                $err = match($result['error'] ?? '') {
                    'insufficient_funds' => t('logistics.hub.err_insufficient_funds'),
                    'invalid_hub_type'   => t('common.validation_error'),
                    'invalid_region'     => t('common.validation_error'),
                    default              => t('logistics.hub.err_generic'),
                };
                hubApiOut(['success' => false, 'error' => $err]);
            }
            hubApiOut([
                'success'  => true,
                'message'  => t('logistics.hub.ok_buy_new'),
                'hub_id'   => $result['hub_id'] ?? null,
                'cost'     => $result['cost'] ?? 0.0,
            ]);

        // POST: player buys an existing market hub
        case 'buy_used_hub':
            $hubId = (int)($_POST['hub_id'] ?? 0);
            if ($hubId <= 0) hubApiOut(['success' => false, 'error' => t('common.validation_error')], 422);
            $result = $acqSvc->buyUsed($playerId, $hubId);
            if (!$result['success']) {
                $err = match($result['error'] ?? '') {
                    'insufficient_funds' => t('logistics.hub.err_insufficient_funds'),
                    'hub_already_owned'  => t('logistics.hub.err_hub_already_owned'),
                    'hub_already_rented' => t('logistics.hub.err_hub_already_rented'),
                    'hub_unavailable'    => t('logistics.hub.err_hub_unavailable'),
                    default              => t('logistics.hub.err_generic'),
                };
                hubApiOut(['success' => false, 'error' => $err]);
            }
            hubApiOut([
                'success' => true,
                'message' => t('logistics.hub.ok_buy_used'),
                'cost'    => $result['cost'] ?? 0.0,
            ]);

        // POST: player rents a market hub
        case 'rent_hub':
            $hubId = (int)($_POST['hub_id'] ?? 0);
            if ($hubId <= 0) hubApiOut(['success' => false, 'error' => t('common.validation_error')], 422);
            $result = $acqSvc->rent($playerId, $hubId);
            if (!$result['success']) {
                $err = match($result['error'] ?? '') {
                    'insufficient_funds' => t('logistics.hub.err_insufficient_funds'),
                    'hub_already_owned'  => t('logistics.hub.err_hub_already_owned'),
                    'hub_already_rented' => t('logistics.hub.err_hub_already_rented'),
                    'hub_unavailable'    => t('logistics.hub.err_hub_unavailable'),
                    default              => t('logistics.hub.err_generic'),
                };
                hubApiOut(['success' => false, 'error' => $err]);
            }
            hubApiOut([
                'success' => true,
                'message' => t('logistics.hub.ok_rent'),
                'deposit' => $result['deposit'] ?? 0.0,
            ]);

        // GET: player's wells assigned to a hub
        case 'hub_wells':
            $hubId = (int)($_GET['hub_id'] ?? 0);
            if ($hubId <= 0) hubApiOut(['success' => false, 'error' => t('common.validation_error')], 422);
            // Hubs are system-owned � no ownership check, but return only this player's wells
            $hub = $hubSvc->getHub($hubId);
            if (!$hub) hubApiOut(['success' => false, 'error' => t('common.access_denied')], 403);
            $wells = $hubSvc->getHubWellsForPlayer($hubId, $playerId);
            hubApiOut(['success' => true, 'wells' => $wells, 'hub' => $hub]);

        // GET: hubs available for well assignment
        case 'assignable_hubs':
            $wellId = (int)($_GET['well_id'] ?? 0);
            $page   = max(1, (int)($_GET['page'] ?? 1));
            if ($wellId <= 0) hubApiOut(['success' => false, 'error' => t('common.validation_error')], 422);
            require_once __DIR__ . '/HubViewService.php';
            require_once __DIR__ . '/HubEconomyService.php';
            $econSvc = new HubEconomyService($hubSvc);
            $viewSvc = new HubViewService($db, $hubSvc, $econSvc);
            $allHubs = $viewSvc->getAssignableHubs($playerId, $wellId);
            $perPage = 5;
            $total   = count($allHubs);
            $totalPages = (int)ceil($total / $perPage);
            $page    = min($page, max(1, $totalPages));
            $offset  = ($page - 1) * $perPage;
            $hubs    = array_slice($allHubs, $offset, $perPage);
            hubApiOut(['success' => true, 'hubs' => $hubs, 'page' => $page, 'totalPages' => $totalPages, 'total' => $total]);

        // GET: wells not assigned to any hub
        case 'unassigned_wells':
            $wells = $hubSvc->getUnassignedWells($playerId);
            hubApiOut(['success' => true, 'wells' => $wells]);

        // GET: hub details
        case 'hub_detail':
            $hubId = (int)($_GET['hub_id'] ?? 0);
            if ($hubId <= 0) hubApiOut(['success' => false, 'error' => t('common.validation_error')], 422);
            require_once __DIR__ . '/HubViewService.php';
            require_once __DIR__ . '/HubEconomyService.php';
            $econSvc = new HubEconomyService($hubSvc);
            $viewSvc = new HubViewService($db, $hubSvc, $econSvc);
            $detail  = $viewSvc->getHubDetail($hubId, $playerId);
            if (!$detail) hubApiOut(['success' => false, 'error' => t('common.access_denied')], 403);
            hubApiOut(['success' => true] + $detail);

        default:
            hubApiOut(['success' => false, 'error' => t('logistics.err_unknown_action')], 400);
    }
} catch (Throwable $e) {
    GameLog::error('HubApi', 'Unhandled exception', $e, ['action' => $action, 'player_id' => $playerId ?? 0]);
    hubApiOut(['success' => false, 'error' => t('logistics.hub.err_generic')], 500);
}
