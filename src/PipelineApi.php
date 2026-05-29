<?php
/**
 * PipelineApi.php - AJAX endpoint for well pipeline purchase and status.
 * PipelineApi.php - AJAX endpoint zakupu i statusu rurociagow odwiertow.
 *
 * URL: /src/PipelineApi.php
 * POST actions: buy_pipeline
 * GET actions: pipeline_status, building_pipelines, pipeline_profiles
 *
 * Rurociag jest kupowany przez gracza i budowany przez okreslony czas.
 * Pipeline is purchased by the player and built over a fixed time period.
 */

ob_start();
require_once __DIR__ . '/init.php';
ob_clean();
header('Content-Type: application/json; charset=utf-8');

// Helper: send JSON and exit / Wysyla JSON i konczy skrypt
function pipelineApiOut(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!Auth::isLoggedIn()) {
    pipelineApiOut(['success' => false, 'error' => t('common.not_logged_in')], 401);
}

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isPost && !CSRF::validateToken($_POST['_token'] ?? '')) {
    pipelineApiOut(['success' => false, 'error' => t('common.csrf_error')], 419);
}

$playerId = Auth::getUserId();
$action   = $_REQUEST['action'] ?? '';

try {
    $db  = Database::getInstance()->getConnection();
    $svc = new WellPipelineService($db);

    switch ($action) {

 // POST: player buys pipeline for a well / Gracz kupuje rurociag dla odwiertu
        case 'buy_pipeline':
            if (!$isPost) {
                pipelineApiOut(['success' => false, 'error' => 'POST required'], 405);
            }
            $wellId = (int)($_POST['well_id'] ?? 0);
            $type   = trim($_POST['pipeline_type'] ?? 'standard');
 // Transport leg: 'inbound' (well->hub, default) or 'outbound' (hub->storage)
            $leg    = trim($_POST['leg'] ?? 'inbound');

            if ($wellId <= 0) {
                pipelineApiOut(['success' => false, 'error' => t('common.validation_error')], 422);
            }

            $result = $svc->purchasePipeline($playerId, $wellId, $type, $leg);

            if (!($result['success'] ?? false)) {
                $errMsg = match ($result['error'] ?? '') {
                    'insufficient_funds'      => t('pipeline.err_insufficient_funds'),
                    'pipeline_already_exists' => t('pipeline.err_already_exists'),
                    'offshore_no_pipeline'    => t('pipeline.err_offshore'),
                    'hub_required'            => t('well_staff.transport_err_hub_required'),
                    'well_not_found'          => t('common.not_found'),
                    default                   => t('common.generic_error'),
                };
                pipelineApiOut(['success' => false, 'error' => $errMsg], 422);
            }

            pipelineApiOut($result);

 // GET: pipelines currently building for this player / Rurociagi w budowie dla gracza
        case 'building_pipelines':
            pipelineApiOut([
                'success'   => true,
                'pipelines' => $svc->getBuildingForPlayer($playerId),
            ]);

 // GET: pipeline status for a specific well / Status rurociagu dla konkretnego odwiertu
        case 'pipeline_status':
            $wellId = (int)($_GET['well_id'] ?? 0);
            $leg    = trim($_GET['leg'] ?? 'inbound');
            if ($wellId <= 0) {
                pipelineApiOut(['success' => false, 'error' => t('common.validation_error')], 422);
            }
            $pipes = $svc->getByPlayerAndWellIds($playerId, [$wellId], $leg);
            pipelineApiOut(['success' => true, 'pipeline' => $pipes[$wellId] ?? null]);

 // GET: available pipeline types with cost and build time / Dostepne typy rurociagow z kosztami i czasem
        case 'pipeline_profiles':
            $profiles = [];
            foreach (['light', 'standard', 'heavy'] as $ptype) {
                $p = $svc->getProfile($ptype);
                $profiles[$ptype] = [
                    'pipeline_type' => $p['pipeline_type'],
                    'price_pct'     => $p['price_pct'],
                    'capacity_pct'  => $p['capacity_pct'],
                    'durability_pct'=> $p['durability_pct'],
                    'build_cost'    => $p['build_cost'],
                    'build_hours'   => $p['build_hours'],
                    'opex_per_tick' => $p['opex_per_tick'],
                    'opex_per_bbl'  => $p['opex_per_bbl'],
                    'label'         => t('logistics.pipeline.type_' . $p['pipeline_type']),
                ];
            }
            pipelineApiOut(['success' => true, 'profiles' => $profiles]);

 // POST: repair pipeline (restore condition to 100%) / Naprawa rurociagu
        case 'repair_pipeline':
            if (!$isPost) {
                pipelineApiOut(['success' => false, 'error' => 'POST required'], 405);
            }
            $pipelineId = (int)($_POST['pipeline_id'] ?? 0);
            if ($pipelineId <= 0) {
                pipelineApiOut(['success' => false, 'error' => t('common.validation_error')], 422);
            }
            $result = $svc->repairPipeline($playerId, $pipelineId);
            if (!($result['success'] ?? false)) {
                $errMsg = match ($result['error'] ?? '') {
                    'pipeline_not_found'      => t('common.not_found'),
                    'pipeline_not_repairable' => t('pipeline.err_not_repairable'),
                    'pipeline_already_full'   => t('pipeline.err_already_full'),
                    'insufficient_funds'      => t('pipeline.err_insufficient_funds'),
                    default                   => t('common.generic_error'),
                };
                pipelineApiOut(['success' => false, 'error' => $errMsg], 422);
            }
            $result['ok_msg'] = t('pipeline.ok_repaired', [
                'cost' => number_format($result['repair_cost'], 2, ',', ' '),
            ]);
            pipelineApiOut($result);

 // POST: scheduled maintenance / Konserwacja zaplanowana
        case 'maintenance_pipeline':
            if (!$isPost) {
                pipelineApiOut(['success' => false, 'error' => 'POST required'], 405);
            }
            $pipelineId = (int)($_POST['pipeline_id'] ?? 0);
            if ($pipelineId <= 0) {
                pipelineApiOut(['success' => false, 'error' => t('common.validation_error')], 422);
            }
            $result = $svc->maintenancePipeline($playerId, $pipelineId);
            if (!($result['success'] ?? false)) {
                $errMsg = match ($result['error'] ?? '') {
                    'pipeline_not_found'        => t('common.not_found'),
                    'pipeline_not_maintainable' => t('pipeline.err_not_repairable'),
                    'insufficient_funds'        => t('pipeline.err_insufficient_funds'),
                    default                     => t('common.generic_error'),
                };
                pipelineApiOut(['success' => false, 'error' => $errMsg], 422);
            }
            $result['ok_msg'] = t('pipeline.ok_maintenance', [
                'cost' => number_format($result['maint_cost'], 2, ',', ' '),
            ]);
            pipelineApiOut($result);

 // POST: toggle pipeline suspended/active / Wstrzymaj lub wznow rurociag
        case 'toggle_pipeline':
            if (!$isPost) {
                pipelineApiOut(['success' => false, 'error' => 'POST required'], 405);
            }
            $pipelineId = (int)($_POST['pipeline_id'] ?? 0);
            if ($pipelineId <= 0) {
                pipelineApiOut(['success' => false, 'error' => t('common.validation_error')], 422);
            }
            $result = $svc->togglePipeline($playerId, $pipelineId);
            if (!($result['success'] ?? false)) {
                $errMsg = match ($result['error'] ?? '') {
                    'pipeline_not_found'      => t('common.not_found'),
                    'pipeline_not_toggleable' => t('pipeline.err_not_toggleable'),
                    default                   => t('common.generic_error'),
                };
                pipelineApiOut(['success' => false, 'error' => $errMsg], 422);
            }
            $isSuspended = ($result['new_status'] === 'suspended');
            $result['ok_msg'] = $isSuspended
                ? t('pipeline.ok_suspended')
                : t('pipeline.ok_resumed');
            pipelineApiOut($result);

        default:
            pipelineApiOut(['success' => false, 'error' => t('common.action_not_found')], 404);
    }
} catch (Throwable $e) {
    GameLog::error('PipelineApi', $action, $e, ['player_id' => $playerId]);
    pipelineApiOut(['success' => false, 'error' => t('common.generic_error')], 500);
}
