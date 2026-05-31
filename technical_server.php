<?php
/**
 * technical.php Panel Dziau Technicznego
 */
require_once __DIR__ . '/src/init.php';
Auth::requireLogin();
require_once __DIR__ . '/src/TechnicalTeamService.php';
require_once __DIR__ . '/src/WellService.php';
require_once __DIR__ . '/src/IncidentService.php';

$_pageStart = GameLog::pageStart('technical.php');
$playerId   = Auth::getUserId();
GameLog::info('technical.php', 'Player logged in', ['player_id' => $playerId]);

// Inicjalizacja serwisw 
try {
    $svc = new TechnicalTeamService($playerId);
    GameLog::info('technical.php', 'TechnicalTeamService initialized OK');
} catch (Throwable $e) {
    GameLog::error('technical.php', 'Failed to create TechnicalTeamService', $e);
    die(t('technical.err_init_svc'));
}

try {
    $wellSvc = new WellService();
    GameLog::info('technical.php', 'WellService initialized OK');
} catch (Throwable $e) {
    GameLog::error('technical.php', 'Failed to create WellService', $e);
    die(t('technical.err_init_well'));
}

try {
    $incidentSvc = new IncidentService();
    GameLog::info('technical.php', 'IncidentService initialized OK');
} catch (Throwable $e) {
    GameLog::error('technical.php', 'IncidentService init failed � continuing without', $e);
    $incidentSvc = null;
}

// AKCJE POST 
$msg = $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    GameLog::info('technical.php', 'POST request', [
        'action' => $_POST['action'] ?? '?',
        'keys'   => array_keys($_POST),
    ]);

    if (!CSRF::validateToken($_POST['_token'] ?? '')) {
        $msg = t('common.csrf_error'); $msgType = 'error';
        GameLog::warn('technical.php', 'CSRF validation FAILED');
    } else {
        $action = $_POST['action'] ?? '';
        GameLog::step('technical.php', 'POST', 1, "action: {$action}");
        switch ($action) {

            case 'assign_task':
                try {
                    $r = $svc->assignTask(
                        (int)($_POST['staff_id']  ?? 0),
                        $_POST['task_type']      ?? '',
                        ($_POST['well_id'] ?? '') !== '' ? (int)$_POST['well_id'] : null,
                        ($_POST['module_type'] ?? '') !== '' ? $_POST['module_type'] : null
                    );
                    $msg = $r['message']; $msgType = $r['success'] ? 'success' : 'error';
                    GameLog::info('technical.php', 'assign_task result', $r);
                } catch (Throwable $e) {
                    $msg = t('technical.err_assign_task'); $msgType = 'error';
                    GameLog::error('technical.php', 'assign_task EXCEPTION', $e);
                }
                if (
                    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
                ) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => $msgType === 'success', 'message' => $msg]);
                    exit;
                }
                break;

            case 'fire_engineer':
                try {
                    $r = $svc->fireEngineer((int)($_POST['staff_id'] ?? 0));
                    $msg = $r['message']; $msgType = $r['success'] ? 'success' : 'error';
                    GameLog::info('technical.php', 'fire_engineer result', $r);
                } catch (Throwable $e) {
                    $msg = t('technical.err_fire_engineer'); $msgType = 'error';
                    GameLog::error('technical.php', 'fire_engineer EXCEPTION', $e);
                }
                break;

            case 'review_candidate':
                try {
                    $r = $svc->reviewCandidate(
                        (int)($_POST['candidate_id'] ?? 0),
                        (int)($_POST['technical_score'] ?? 0),
                        $_POST['recommendation'] ?? 'reject',
                        Validator::sanitize($_POST['comment'] ?? '')
                    );
                    $msg = $r['message']; $msgType = $r['success'] ? 'success' : 'error';
                    GameLog::info('technical.php', 'review_candidate result', $r);
                } catch (Throwable $e) {
                    $msg = t('technical.err_review'); $msgType = 'error';
                    GameLog::error('technical.php', 'review_candidate EXCEPTION', $e);
                }
                break;

            case 'request_recruitment':
                try {
                    $recCount = max(1, min(3, (int)($_POST['count'] ?? 1)));
                    $recSpec  = $_POST['spec_code']        ?? '';
                    $recReg   = $_POST['region_code']      ?? 'PL';
                    $recType  = $_POST['recruitment_type'] ?? 'local';
                    $msgs     = [];
                    $allOk    = true;
                    for ($ri = 0; $ri < $recCount; $ri++) {
                        $r = $svc->requestRecruitment($recSpec, $recReg, $recType);
                        if (!$r['success']) { $allOk = false; $msgs[] = $r['message']; break; }
                        $msgs[] = $r['message'];
                    }
                    $msgType = $allOk ? 'success' : 'error';
                    if ($recCount === 1) {
                        $msg = $msgs[0];
                    } else {
                        $msg = $allOk
                            ? t('technical.recruitment_batch_ok', ['count' => $recCount])
                            : t('technical.recruitment_batch_err', ['count' => $recCount, 'detail' => implode('; ', $msgs)]);
                    }
                    GameLog::info('technical.php', 'request_recruitment result', ['count' => $recCount, 'ok' => $allOk]);
                } catch (Throwable $e) {
                    $msg = t('technical.err_recruitment'); $msgType = 'error';
                    GameLog::error('technical.php', 'request_recruitment EXCEPTION', $e);
                }
                break;

            case 'cancel_task':
                try {
                    $r = $svc->cancelTask((int)($_POST['task_id'] ?? 0));
                    $msg = $r['message'];
                    $msgType = $r['success'] ? 'success' : 'error';
                    GameLog::info('technical.php', 'cancel_task', ['task_id' => $_POST['task_id'] ?? 0, 'ok' => $r['success']]);
                } catch (Throwable $e) {
                    $msg = t('technical.err_cancel_task'); $msgType = 'error';
                    GameLog::error('technical.php', 'cancel_task EXCEPTION', $e);
                }
                break;

            case 'cancel_queue_item':
                try {
                    $r = $svc->cancelQueueItem((int)($_POST['queue_id'] ?? 0));
                    $msg = $r['message'];
                    $msgType = $r['success'] ? 'success' : 'error';
                    GameLog::info('technical.php', 'cancel_queue_item', ['queue_id' => $_POST['queue_id'] ?? 0, 'ok' => $r['success']]);
                } catch (Throwable $e) {
                    $msg = t('technical.err_cancel_queue'); $msgType = 'error';
                    GameLog::error('technical.php', 'cancel_queue_item EXCEPTION', $e);
                }
                break;

            case 'upgrade_procedures':
                try {
                    $r = $svc->upgradeProcedures();
                    $msg = $r['message']; $msgType = $r['success'] ? 'success' : 'error';
                    GameLog::info('technical.php', 'upgrade_procedures result', $r);
                } catch (Throwable $e) {
                    $msg = t('technical.err_upgrade_proc'); $msgType = 'error';
                    GameLog::error('technical.php', 'upgrade_procedures EXCEPTION', $e);
                }
                break;

            case 'repair_procedure_integrity':
                try {
                    $r = $svc->repairProcedureIntegrity();
                    $msg = $r['message']; $msgType = $r['success'] ? 'success' : 'error';
                    GameLog::info('technical.php', 'repair_procedure_integrity result', $r);
                } catch (Throwable $e) {
                    $msg = t('technical.err_repair_proc'); $msgType = 'error';
                    GameLog::error('technical.php', 'repair_procedure_integrity EXCEPTION', $e);
                }
                break;

            case 'dismiss_notification':
                try {
                    $svc->markRead((int)($_POST['notif_id'] ?? 0));
                    GameLog::info('technical.php', 'dismiss_notification OK', ['notif_id' => $_POST['notif_id'] ?? 0]);
                } catch (Throwable $e) {
                    GameLog::error('technical.php', 'dismiss_notification EXCEPTION', $e);
                }
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;

            default:
                GameLog::warn('technical.php', "Unknown POST action: {$action}");
        }
    }
}

// processReadyRecruitments() wywoywane tylko przez cron/tick.php, nie przy pageview

// DANE 
$_dataStart = microtime(true);

try { $manager = $svc->getManager(); GameLog::info('technical.php', 'getManager', ['found' => $manager ? 'yes' : 'no']); }
catch (Throwable $e) { GameLog::error('technical.php', 'getManager FAIL', $e); $manager = null; }

try { $mBonus = $svc->getManagerBonus($manager); }
catch (Throwable $e) { GameLog::error('technical.php', 'getManagerBonus FAIL', $e); $mBonus = ['skill'=>0,'time_mult'=>1,'cost_mult'=>1,'label'=>'']; }

try { $staff = $svc->getStaff(); GameLog::dbResult('technical.php', 'getStaff', count($staff)); }
catch (Throwable $e) { GameLog::error('technical.php', 'getStaff FAIL', $e); $staff = []; }

try { $wells = $wellSvc->getPlayerWells($playerId); GameLog::dbResult('technical.php', 'getPlayerWells', count($wells)); }
catch (Throwable $e) { GameLog::error('technical.php', 'getPlayerWells FAIL', $e); $wells = []; }

try { $activeTasks = $svc->getActiveTasks(); GameLog::dbResult('technical.php', 'getActiveTasks', count($activeTasks)); }
catch (Throwable $e) { GameLog::error('technical.php', 'getActiveTasks FAIL', $e); $activeTasks = []; }

try { $allTasks = $svc->getTasks(); GameLog::dbResult('technical.php', 'getTasks', count($allTasks)); }
catch (Throwable $e) { GameLog::error('technical.php', 'getTasks FAIL', $e); $allTasks = []; }

// Auto-zakocz zadania ktrych end_time min (na wypadek gdy cron nie zdy)
try {
    $svc->processTick();
    $allTasks = $svc->getTasks();
} catch (Throwable $e) {
    GameLog::error('technical.php', 'processTick FAIL', $e);
}

try { $taskQueue = $svc->getQueue(); GameLog::dbResult('technical.php', 'getQueue', count($taskQueue)); }
catch (Throwable $e) { GameLog::error('technical.php', 'getQueue FAIL', $e); $taskQueue = []; }

try { $notifications = $svc->getUnreadNotifications(); GameLog::dbResult('technical.php', 'getUnreadNotifications', count($notifications)); }
catch (Throwable $e) { GameLog::error('technical.php', 'getUnreadNotifications FAIL', $e); $notifications = []; }

try { $incidents = $incidentSvc ? $incidentSvc->getPlayerIncidents($playerId, 50) : []; GameLog::dbResult('technical.php', 'getPlayerIncidents', count($incidents)); }
catch (Throwable $e) { GameLog::error('technical.php', 'getPlayerIncidents FAIL', $e); $incidents = []; }

$activeTab = $_GET['tab'] ?? 'team';
GameLog::info('technical.php', "Active tab: {$activeTab}");

try { $candidates = $svc->getTechnicalCandidates(); GameLog::dbResult('technical.php', 'getTechnicalCandidates', count($candidates)); }
catch (Throwable $e) { GameLog::error('technical.php', 'getTechnicalCandidates FAIL', $e); $candidates = []; }

try { $activeRecruitment = $svc->getActiveRecruitment(); GameLog::info('technical.php', 'getActiveRecruitment', ['found' => $activeRecruitment ? 'yes' : 'no']); }
catch (Throwable $e) { GameLog::error('technical.php', 'getActiveRecruitment FAIL', $e); $activeRecruitment = null; }

$unreviewed = count(array_filter($candidates, fn($c) => $c['review_id'] === null));
$csrf       = CSRF::generateToken();

$staffBySpec = [];
foreach ($staff as $s) { $staffBySpec[$s['spec_code']][] = $s; }

$brokenWells = array_filter($wells, fn($w) => in_array($w['status'], ['broken','paused_cash','paused_staff','blowout','contaminated']));
$activeWells = array_filter($wells, fn($w) => $w['status'] === 'active');

$totalProd = array_sum(array_map(fn($w) => (float)$w['base_production_per_hour'], $activeWells));
$avgCond   = count($wells) ? round(array_sum(array_map(fn($w) => (float)$w['technical_condition'], $wells)) / count($wells), 1) : 0;

// Dane personelu odwiertw
try {
    $wellStaffSvc     = new WellStaffService($playerId);
    $wellsStaffStatus = $wellStaffSvc->getWellsStaffStatus();
    $wellsWithoutStaff = count(array_filter($wellsStaffStatus,
        fn($w) => !$w['has_operator'] || !$w['has_technician']));
} catch (Throwable $e) {
    GameLog::error('technical.php', 'WellStaffService FAIL', $e);
    $wellStaffSvc      = null;
    $wellsStaffStatus  = [];
    $wellsWithoutStaff = 0;
}

// Bonus BHP
try {
    $hseBonus = $svc->getHSEBonus();
} catch (Throwable $e) {
    GameLog::error('technical.php', 'getHSEBonus FAIL', $e);
    $hseBonus = ['active_hse' => 0, 'failure_reduction' => 1.0, 'repair_cost_mult' => 1.0,
                 'catastrophe_mult' => 1.0, 'uptime_bonus' => 0.0, 'degrade_mult' => 1.0,
                 'audit_bonus' => false, 'proc_level' => 0, 'proc_integrity' => 100.0,
                 'proc_factor' => 0.0, 'label' => t('technical.hse_read_error')];
}

// Dane procedur BHP
try {
    $procStatus = $svc->getProcedureStatus();
} catch (Throwable $e) {
    GameLog::error('technical.php', 'getProcedureStatus FAIL', $e);
    $procStatus = ['level' => 0, 'integrity' => 100.0, 'last_decay_at' => null];
}

// Pipelines 
try {
    $db = Database::getInstance()->getConnection();

    GameLog::tablesCheck($db, 'technical.php', [
        'pipelines', 'technical_staff', 'technical_tasks',
        'technical_notifications', 'candidate_reviews', 'failure_log'
    ]);

    $pipeStmt = $db->prepare("SELECT * FROM pipelines WHERE player_id = ? ORDER BY id");
    $pipeStmt->execute([$playerId]);
    $pipelines = $pipeStmt->fetchAll();
    GameLog::dbResult('technical.php', 'pipelines', count($pipelines));

    if (empty($pipelines)) {
        GameLog::info('technical.php', 'No pipelines � creating default');
        $db->prepare("INSERT INTO pipelines (player_id, name) VALUES (?, 'Ruroci�g g��wny')")->execute([$playerId]);
        $pipeStmt->execute([$playerId]);
        $pipelines = $pipeStmt->fetchAll();
    }
} catch (Throwable $e) {
    GameLog::error('technical.php', 'pipelines load/create FAIL', $e);
    $pipelines = [];
}

// Warunki upgrade procedur BHP 
$auditDone   = false;
$hasHseStaff = ['officer' => false, 'engineer' => false];
try {
    $auditCheckStmt = $db->prepare("
        SELECT id FROM technical_tasks
        WHERE player_id = ? AND task_type = 'safety_audit' AND status = 'completed'
        LIMIT 1
    ");
    $auditCheckStmt->execute([$playerId]);
    $auditDone = (bool)$auditCheckStmt->fetch();

    $hseStaffStmt = $db->prepare("
        SELECT spec_code FROM technical_staff
        WHERE player_id = ?
          AND spec_code IN ('safety_officer','safety_engineer')
          AND status IN ('active','busy')
          AND (fired_at IS NULL OR fired_at > NOW())
    ");
    $hseStaffStmt->execute([$playerId]);
    foreach ($hseStaffStmt->fetchAll() as $hs) {
        if ($hs['spec_code'] === 'safety_officer')  $hasHseStaff['officer']  = true;
        if ($hs['spec_code'] === 'safety_engineer') $hasHseStaff['engineer'] = true;
    }
} catch (Throwable $e) {
    GameLog::error('technical.php', 'auditCheck/hseStaff FAIL', $e);
}

$canUpgradeProcedures = $auditDone && $hasHseStaff['officer'] && $hasHseStaff['engineer'];

// Aktywne katastrofy 
$activeDisasters = [];
try {
    $dsStmt = $db->prepare("
        SELECT d.*, w.location_name AS well_name
        FROM industrial_disasters d
        LEFT JOIN wells w ON w.id = d.well_id
        WHERE d.player_id = ? AND d.status IN ('active','being_repaired')
        ORDER BY d.occurred_at DESC
    ");
    $dsStmt->execute([$playerId]);
    $activeDisasters = $dsStmt->fetchAll();
} catch (Throwable $e) {
    GameLog::error('technical.php', 'activeDisasters query FAILED', $e);
}

// Historia awarii 
$failures = [];
try {
    $failStmt = $db->prepare("
        SELECT fl.*, w.location_name
        FROM failure_log fl
        LEFT JOIN wells w ON fl.well_id = w.id
        WHERE fl.player_id = ?
        ORDER BY fl.occurred_at DESC
        LIMIT 20
    ");
    $failStmt->execute([$playerId]);
    $failures = $failStmt->fetchAll();
} catch (Throwable $e) {
    GameLog::error('technical.php', 'failure_log query FAILED', $e);
}

GameLog::perf('technical.php', 'All data loaded', $_dataStart);
GameLog::info('technical.php', 'Rendering HTML', [
    'manager'      => $manager ? 'yes' : 'no',
    'staff'        => count($staff),
    'wells'        => count($wells),
    'active_wells' => count($activeWells),
    'broken_wells' => count($brokenWells),
    'active_tasks' => count($activeTasks),
    'candidates'   => count($candidates),
    'unreviewed'   => $unreviewed,
    'pipelines'    => count($pipelines),
    'total_prod'   => $totalProd,
    'avg_condition'=> $avgCond,
    'incidents'    => count($incidents),
]);

$viewData = [
    'msg'                  => $msg,
    'msgType'              => $msgType,
    'manager'              => $manager,
    'mBonus'               => $mBonus,
    'staff'                => $staff,
    'staffBySpec'          => $staffBySpec,
    'wells'                => $wells,
    'activeWells'          => $activeWells,
    'brokenWells'          => $brokenWells,
    'activeTasks'          => $activeTasks,
    'allTasks'             => $allTasks,
    'taskQueue'            => $taskQueue,
    'notifications'        => $notifications,
    'incidents'            => $incidents,
    'candidates'           => $candidates,
    'activeRecruitment'    => $activeRecruitment,
    'unreviewed'           => $unreviewed,
    'csrf'                 => $csrf,
    'activeTab'            => $activeTab,
    'totalProd'            => $totalProd,
    'avgCond'              => $avgCond,
    'wellsWithoutStaff'    => $wellsWithoutStaff,
    'wellsStaffStatus'     => $wellsStaffStatus,
    'hseBonus'             => $hseBonus,
    'procStatus'           => $procStatus,
    'canUpgradeProcedures' => $canUpgradeProcedures,
    'auditDone'            => $auditDone,
    'hasHseStaff'          => $hasHseStaff,
    'pipelines'            => $pipelines,
    'activeDisasters'      => $activeDisasters,
    'failures'             => $failures,
    'db'                   => $db,
    'playerId'             => $playerId,
    'svc'                  => $svc,
];
?>
<!DOCTYPE html>
<html lang="pl">
<head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#08080f">
<meta name="csrf-token" content="<?= htmlspecialchars(CSRF::generateToken()) ?>">
<title><?= t('technical.page_title') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('/assets/css/style.css') ?>">
</head>
<body>
<?php require __DIR__ . '/templates/views/technical/main.php'; ?>
</body>
</html>
<?php GameLog::pageEnd('technical.php', $_pageStart);
