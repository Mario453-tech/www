<?php
/**
 * WellStaffApi.php - AJAX endpoint for well staff and transport actions.
 * WellStaffApi.php - endpoint AJAX dla akcji personelu i transportu odwiertow.
 *
 * URL: /src/WellStaffApi.php
 * Method: POST (assign, unassign, set_transport) | GET (get_status, get_available)
 * Metoda: POST (assign, unassign, set_transport) | GET (get_status, get_available)
 */

ob_start();
require_once __DIR__ . '/init.php';
ob_clean();
header('Content-Type: application/json; charset=utf-8');

function jsonOut(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * @return array{permit_type:string,permit_action:string,region_id:int,region_name:string}
 */
function wellStaffPermitContext(PDO $db, int $regionId): array
{
    $context = [
        'permit_type'   => 'local',
        'permit_action' => 'submit_hub_application',
        'region_id'     => $regionId,
        'region_name'   => '',
    ];

    if ($regionId <= 0) {
        return $context;
    }

    try {
        $stmt = $db->prepare("
            SELECT COALESCE(l.region_name, wr.name, CONCAT('Region #', l.region_id)) AS region_name
              FROM legal_region_config l
              LEFT JOIN world_regions wr ON wr.id = l.region_id
             WHERE l.region_id = ?
             LIMIT 1
        ");
        $stmt->execute([$regionId]);
        $context['region_name'] = (string)($stmt->fetchColumn() ?: '');
    } catch (Throwable) {
        $context['region_name'] = '';
    }

    return $context;
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
$svc = new WellStaffService($playerId);
$db = Database::getInstance()->getConnection();
$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'get_status':
            jsonOut([
                'success' => true,
                'wells' => $svc->getWellsStaffStatus(),
            ]);

        case 'get_available':
            $role = $_GET['role'] ?? 'operator';
            jsonOut([
                'success' => true,
                'staff' => $svc->getAvailableStaff($role),
            ]);

        case 'assign':
            $wellId = (int)($_POST['well_id'] ?? 0);
            $staffId = (int)($_POST['staff_id'] ?? 0);
            $role = $_POST['role'] ?? '';
            if (!$wellId || !$staffId || !$role) {
                jsonOut(['success' => false, 'error' => t('well_staff.err_missing_params_assign')], 400);
            }
            jsonOut($svc->assign($wellId, $staffId, $role));

        case 'unassign':
            $wellId = (int)($_POST['well_id'] ?? 0);
            $role = $_POST['role'] ?? '';
            if (!$wellId || !$role) {
                jsonOut(['success' => false, 'error' => t('well_staff.err_missing_params_role')], 400);
            }
            jsonOut($svc->unassign($wellId, $role));

        case 'set_transport':
            $wellId = (int)($_POST['well_id'] ?? 0);
            $transportType = trim((string)($_POST['transport_type'] ?? ''));
            $allowed = ['nieustawiony', 'rurociag', 'ciezarowki', 'tankowiec'];
            $pipelineBuildStarted = false;
            $pipelineService = null;
            $ownedPipeline = null;
            $purchase = [];

            if ($wellId <= 0 || !in_array($transportType, $allowed, true)) {
                jsonOut(['success' => false, 'message' => t('common.invalid_data')], 400);
            }

            if ($transportType === 'rurociag') {
                $pipelineService = new WellPipelineService($db);
            }

            $db->beginTransaction();

            try {
                $wellStmt = $db->prepare('SELECT * FROM wells WHERE id = ? AND player_id = ? LIMIT 1 FOR UPDATE');
                $wellStmt->execute([$wellId, $playerId]);
                $wellRow = $wellStmt->fetch(PDO::FETCH_ASSOC);

                if (!$wellRow) {
                    $db->rollBack();
                    jsonOut(['success' => false, 'message' => t('common.access_denied')], 403);
                }

                $wellType = (string)($wellRow['well_type'] ?? 'onshore');
                $isOffshore = ($wellType === 'offshore');

                if ($isOffshore && $transportType !== 'tankowiec') {
                    $db->rollBack();
                    jsonOut(['success' => false, 'message' => t('well_staff.transport_err_offshore')], 400);
                }

                if (!$isOffshore && $transportType === 'tankowiec') {
                    $db->rollBack();
                    jsonOut(['success' => false, 'message' => t('well_staff.transport_err_onshore')], 400);
                }

                $transportProfile = TransportConfigService::getTypeConfig($db, $transportType);

 // Buy pipeline on first switch to pipeline transport.
 // Kup rurociag przy pierwszym przelaczeniu na transport rurociagowy.
 // Keep this path aligned with the dedicated build-timer purchase flow.
                if (!$isOffshore && $transportType === 'rurociag') {
                    $ownedPipeline = $pipelineService->getByPlayerAndWellIds($playerId, [$wellId])[$wellId] ?? null;

                    if ($ownedPipeline !== null && empty($ownedPipeline['_is_operational'])) {
                        $rebind = $pipelineService->bindPipelineToActiveHub($playerId, $wellId);
                        if (!($rebind['success'] ?? false)) {
                            $db->rollBack();
                            $errMsg = match ($rebind['error'] ?? '') {
                                'hub_required'      => t('well_staff.transport_err_hub_required'),
                                'pipeline_not_found' => t('common.not_found'),
                                default             => t('common.generic_error'),
                            };
                            jsonOut(['success' => false, 'message' => $errMsg], 400);
                        }
                        $ownedPipeline = $pipelineService->getByPlayerAndWellIds($playerId, [$wellId])[$wellId] ?? null;
                    }

                    if ($ownedPipeline === null) {
 // Read requested pipeline type from POST; fall back to well record then 'standard'.
 // Odczytaj typ rurociagu z POST; fallback do rekordu odwiertu, potem 'standard'.
                        $allowedPipelineTypes = ['light', 'standard', 'heavy'];
                        $requestedPipelineType = trim((string)($_POST['pipeline_type'] ?? $wellRow['pipeline_type'] ?? 'standard'));
                        if (!in_array($requestedPipelineType, $allowedPipelineTypes, true)) {
                            $requestedPipelineType = 'standard';
                        }
                        $purchase = $pipelineService->purchasePipeline(
                            $playerId,
                            $wellId,
                            $requestedPipelineType
                        );

                        if (!($purchase['success'] ?? false)) {
                            $db->rollBack();
                            // The error code lets the UI show the local-permit action modal.
                            if (($purchase['error'] ?? '') === 'no_hub_permit') {
                                jsonOut(array_merge([
                                    'success'    => false,
                                    'message'    => t('legal.hub.err_no_hub_permit'),
                                    'error_code' => 'no_hub_permit',
                                ], wellStaffPermitContext($db, (int)($purchase['region_id'] ?? 0))), 400);
                            }
                            $errMsg = match ($purchase['error'] ?? '') {
                                'insufficient_funds'      => t('pipeline.err_insufficient_funds'),
                                'pipeline_already_exists' => t('pipeline.err_already_exists'),
                                'offshore_no_pipeline'    => t('pipeline.err_offshore'),
                                'hub_required'            => t('well_staff.transport_err_hub_required'),
                                'well_not_found'          => t('common.not_found'),
                                default                   => t('common.generic_error'),
                            };
                            jsonOut([
                                'success' => false,
                                'message' => $errMsg,
                            ], 400);
                        }

                        $pipelineBuildStarted = true;
                    }

                    $profileType = (string)($ownedPipeline['pipeline_type'] ?? $purchase['pipeline_type'] ?? $_POST['pipeline_type'] ?? $wellRow['pipeline_type'] ?? 'standard');
                    $pipelineProfile = $pipelineService->getProfile($profileType);
                    $transportProfile['capacity'] = (float)($pipelineProfile['capacity_pct'] ?? $transportProfile['capacity'] ?? 100.0);
                }

                $updateStmt = $db->prepare(
                    'UPDATE wells
                        SET transport_type = ?,
                            transport_capacity_pct = ?,
                            transport_opex_pct = ?
                      WHERE id = ? AND player_id = ?'
                );
                $updateStmt->execute([
                    $transportType,
                    (float)($transportProfile['capacity'] ?? 100.0),
                    (float)($transportProfile['opex'] ?? 0.0),
                    $wellId,
                    $playerId,
                ]);

                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }

            $transportNames = [
                'nieustawiony' => t('well_staff.transport_unset'),
                'rurociag' => t('well_staff.transport_pipeline'),
                'ciezarowki' => t('well_staff.transport_trucks'),
                'tankowiec' => t('well_staff.transport_tanker'),
            ];

            GameLog::info('WellStaffApi', 'set_transport', [
                'well_id' => $wellId,
                'transport' => $transportType,
            ]);

            $message = t('well_staff.msg_transport_set', ['name' => $transportNames[$transportType]]);
            if ($pipelineBuildStarted) {
                $message = t('pipeline.ok_build_started') . ' ' . $message;
            }

            jsonOut([
                'success' => true,
                'message' => $message,
            ]);

 // Choose the second transport leg (hub -> storage) for a well's hub.
 // ETAP 11: the setting is now stored per hub (logistics_hubs.outbound_transport_type).
 // Allowed: 'nieustawiony' (direct), 'rurociag' (outbound pipeline) and
 // 'ciezarowki' (road haul, per-tick cost + incidents). Tanker second leg N/A
 // (the second leg is land-based hub -> storage).
        case 'set_outbound_transport':
            $wellId        = (int)($_POST['well_id'] ?? 0);
            $transportType = trim((string)($_POST['transport_type'] ?? ''));
            $allowedOut    = ['nieustawiony', 'rurociag', 'ciezarowki'];
            $pipelineBuildStarted = false;

            if ($wellId <= 0 || !in_array($transportType, $allowedOut, true)) {
                jsonOut(['success' => false, 'message' => t('common.invalid_data')], 400);
            }

            // Instantiate before beginTransaction: constructor runs DDL (ensureSchema) which
            // causes MySQL implicit commit and would silently end any open transaction.
            $pipelineService = new WellPipelineService($db);

            $db->beginTransaction();
            try {
 // Find the hub for this well (ETAP 11: outbound setting is per hub).
 // JOIN wells ensures only the owning player can change the outbound transport.
                $hubStmt = $db->prepare(
                    'SELECT a.hub_id
                       FROM logistics_hub_assignments a
                       JOIN wells w
                         ON w.id = a.well_id
                        AND w.player_id = ?
                       JOIN logistics_hubs h
                         ON h.id = a.hub_id
                        AND (h.player_id = ? OR h.tenant_player_id = ?)
                      WHERE a.well_id = ?
                        AND a.status = \'active\'
                      LIMIT 1
                      FOR UPDATE'
                );
                $hubStmt->execute([$playerId, $playerId, $playerId, $wellId]);
                $hubRow = $hubStmt->fetch(PDO::FETCH_ASSOC);
                if (!$hubRow) {
                    $db->rollBack();
                    jsonOut(['success' => false, 'message' => t('well_staff.transport_err_hub_required')], 400);
                }
                $hubId = (int)$hubRow['hub_id'];

                if ($transportType === 'rurociag') {
                    $ownedOutbound   = $pipelineService->getByPlayerHubIds($playerId, [$hubId])[$hubId] ?? null;

                    if ($ownedOutbound === null) {
                        $allowedPipelineTypes  = ['light', 'standard', 'heavy'];
                        $requestedPipelineType = trim((string)($_POST['pipeline_type'] ?? 'standard'));
                        if (!in_array($requestedPipelineType, $allowedPipelineTypes, true)) {
                            $requestedPipelineType = 'standard';
                        }
                        $purchase = $pipelineService->purchaseHubOutboundPipeline($playerId, $hubId, $requestedPipelineType);
                        if (!($purchase['success'] ?? false)) {
                            $db->rollBack();
                            // The error code lets the UI show the local-permit action modal.
                            if (($purchase['error'] ?? '') === 'no_hub_permit') {
                                jsonOut(array_merge([
                                    'success'    => false,
                                    'message'    => t('legal.hub.err_no_hub_permit'),
                                    'error_code' => 'no_hub_permit',
                                ], wellStaffPermitContext($db, (int)($purchase['region_id'] ?? 0))), 400);
                            }
                            $errMsg = match ($purchase['error'] ?? '') {
                                'insufficient_funds'      => t('pipeline.err_insufficient_funds'),
                                'pipeline_already_exists' => t('pipeline.err_already_exists'),
                                'hub_not_found'           => t('well_staff.transport_err_hub_required'),
                                default                   => t('common.generic_error'),
                            };
                            jsonOut(['success' => false, 'message' => $errMsg], 400);
                        }
                        $pipelineBuildStarted = true;
                    }
                }

                $updateHub = $db->prepare(
                    'UPDATE logistics_hubs
                        SET outbound_transport_type = ?
                      WHERE id = ?
                        AND (player_id = ? OR tenant_player_id = ?)'
                );
                $updateHub->execute([$transportType, $hubId, $playerId, $playerId]);

                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }

            $transportNames = [
                'nieustawiony' => t('well_staff.transport_unset'),
                'rurociag'     => t('well_staff.transport_pipeline'),
                'ciezarowki'   => t('well_staff.transport_trucks'),
            ];

            GameLog::info('WellStaffApi', 'set_outbound_transport', [
                'well_id'   => $wellId,
                'transport' => $transportType,
            ]);

            $message = t('well_staff.msg_outbound_transport_set', ['name' => $transportNames[$transportType] ?? $transportType]);
            if ($pipelineBuildStarted) {
                $message = t('pipeline.ok_build_started') . ' ' . $message;
            }

            jsonOut(['success' => true, 'message' => $message]);

        default:
            jsonOut(['success' => false, 'error' => t('well_staff.err_unknown_action', ['action' => $action])], 400);
    }
} catch (Throwable $e) {
    GameLog::error('WellStaffApi', 'unhandled exception', $e);
    jsonOut(['success' => false, 'error' => t('common.app_error')], 500);
}
