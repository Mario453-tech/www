<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/logs.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

require_once __DIR__ . '/../src/Tick/TickStatsRepository.php';
require_once __DIR__ . '/../src/AdminLogs/GameLogReader.php';
require_once __DIR__ . '/../src/AdminLogs/LogRetentionService.php';

$db     = Database::getInstance()->getConnection();
$tab    = in_array($_GET['tab'] ?? 'admin', ['admin', 'game', 'tick']) ? ($_GET['tab'] ?? 'admin') : 'admin';
$filter = $_GET['filter'] ?? '';
$limit  = max(1, min((int)($_GET['limit'] ?? 100), 1000));
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;
$msg    = '';
$err    = '';
$flash = $_SESSION['admin_logs_flash'] ?? null;
unset($_SESSION['admin_logs_flash']);
if (is_array($flash)) {
    if (($flash['type'] ?? '') === 'error') {
        $err = (string)($flash['message'] ?? '');
    } else {
        $msg = (string)($flash['message'] ?? '');
    }
}

// USTAWIENIA RETENCJI 
$cfg = [];
try {
    $cfgStmt = $db->query("SELECT `key`, `value` FROM site_config WHERE `key` IN ('log_retention_admin_days','log_retention_game_days')");
    $cfg = $cfgStmt ? $cfgStmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];
} catch (Throwable $e) {
    GameLog::warn('admin/logs.php', 'Log retention config unavailable; using defaults', ['error' => $e->getMessage()]);
}
$retentionAdmin = (int)($cfg['log_retention_admin_days'] ?? 0);
$retentionGame  = (int)($cfg['log_retention_game_days']  ?? 0);
$gameLogFile = __DIR__ . '/../game_debug.log';
$gameLogReader = new GameLogReader();
$retentionService = new LogRetentionService($db, $gameLogReader);

// AUTO-CZYSZCZENIE przy kadym daniu (jeli ustawione) 
if ($retentionAdmin > 0) {
    try {
        $retentionService->cleanupAdminLogs($retentionAdmin);
    } catch (Throwable $e) {
        GameLog::warn('admin/logs.php', 'Admin log retention failed', ['error' => $e->getMessage()]);
    }
}

// DANE: TICK LOG 
$tickRepo       = null;
$tickSummary24h = null;
$ticks          = [];
$tickTotalRows  = 0;
$tickTotalPages = 1;
$tickFilterSource = in_array($_GET['tsource'] ?? '', ['cron','force','cron_http']) ? $_GET['tsource'] : '';
$tickDeleteMsg  = '';
$tickCleanupMsg = '';

// AKCJE 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? ''))
        die('<p class="alert alert-error">' . t('common.csrf_error') . '</p>');

    $action = $_POST['action'] ?? '';

    if ($action === 'clear_admin_logs') {
        try {
            $db->exec("DELETE FROM admin_logs");
            AdminLog::log('admin_logs_cleared', 'Administrative logs cleared');
            $_SESSION['admin_logs_flash'] = [
                'type' => 'success',
                'message' => t('admin.logs.msg_admin_cleared'),
            ];
        } catch (Throwable $e) {
            GameLog::error('admin/logs.php', 'Clearing administrative logs failed', $e);
            $_SESSION['admin_logs_flash'] = [
                'type' => 'error',
                'message' => t('admin.logs.msg_clear_failed'),
            ];
        }
        header('Location: /admin/logs.php?tab=admin');
        exit;
    }

    if ($action === 'clear_game_log') {
        try {
            if (file_exists($gameLogFile) && file_put_contents($gameLogFile, '', LOCK_EX) === false) {
                throw new RuntimeException('Unable to truncate game log');
            }
            AdminLog::log('game_log_cleared', 'Game log cleared');
            $_SESSION['admin_logs_flash'] = [
                'type' => 'success',
                'message' => t('admin.logs.msg_game_cleared'),
            ];
        } catch (Throwable $e) {
            GameLog::error('admin/logs.php', 'Clearing game log failed', $e);
            $_SESSION['admin_logs_flash'] = [
                'type' => 'error',
                'message' => t('admin.logs.msg_clear_failed'),
            ];
        }
        header('Location: /admin/logs.php?tab=game');
        exit;
    }

    if ($action === 'tick_log_delete') {
        $delId = (int)($_POST['delete_id'] ?? 0);
        if ($delId > 0) {
            $db->prepare("DELETE FROM tick_stats WHERE id = ?")->execute([$delId]);
            AdminLog::log('tick_log_delete', "Tick stats entry #{$delId} deleted");
            $tickDeleteMsg = 'ok:' . t('admin.tick_log.msg_deleted');
        }
    }

    if ($action === 'tick_log_cleanup') {
        $tickRepo = new TickStatsRepository($db);
        $days    = max(1, min(365, (int)($_POST['cleanup_days'] ?? 7)));
        $deleted = $tickRepo->cleanup($days);
        AdminLog::log('tick_log_cleanup', "Tick stats cleanup: deleted {$deleted} entries older than {$days} days");
        $tickCleanupMsg = 'ok:' . t('admin.tick_log.msg_cleanup', ['count' => $deleted, 'days' => $days]);
    }

    if ($action === 'save_retention') {
        $rAdmin = max(0, min(365, (int)($_POST['retention_admin'] ?? 0)));
        $rGame  = max(0, min(365, (int)($_POST['retention_game']  ?? 0)));
        $who    = AdminAuth::getAdminUsername();
        try {
            $db->beginTransaction();
            $upd = $db->prepare("INSERT INTO site_config (`key`,`value`,`updated_by`) VALUES (?,?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), updated_by=VALUES(updated_by)");
            $upd->execute(['log_retention_admin_days', $rAdmin, $who]);
            $upd->execute(['log_retention_game_days', $rGame, $who]);
            $db->commit();
            $deletedAdmin = $retentionService->cleanupAdminLogs($rAdmin);
            $deletedGame = $retentionService->cleanupGameLog($gameLogFile, $rGame);
            AdminLog::log(
                'log_retention_saved',
                "Log retention saved: admin={$rAdmin}d, game={$rGame}d, deleted_admin={$deletedAdmin}, deleted_game={$deletedGame}"
            );
            $_SESSION['admin_logs_flash'] = [
                'type' => 'success',
                'message' => t('admin.logs.msg_retention_saved'),
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            GameLog::error('admin/logs.php', 'Saving log retention failed', $e);
            $_SESSION['admin_logs_flash'] = [
                'type' => 'error',
                'message' => t('admin.logs.msg_retention_failed'),
            ];
        }
        header('Location: /admin/logs.php?tab=' . rawurlencode($tab));
        exit;
    }
}

// DANE: LOGI ADMINA 
$logs      = [];
$totalLogs = 0;
$totalPages = 1;

if ($tab === 'admin') {
    $where   = $filter ? "WHERE al.action LIKE :f OR al.description LIKE :f2" : "";
    $cntStmt = $db->prepare("SELECT COUNT(*) FROM admin_logs al {$where}");
    if ($filter) { $f = "%{$filter}%"; $cntStmt->bindValue(':f', $f); $cntStmt->bindValue(':f2', $f); }
    $cntStmt->execute();
    $totalLogs  = (int)$cntStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalLogs / $limit));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $limit;

    $stmt = $db->prepare("
        SELECT al.*,
               p.email     AS player_email,
               a.username  AS admin_username,
               a.email     AS admin_email
        FROM admin_logs al
        LEFT JOIN players p ON al.target_player_id = p.id
        LEFT JOIN admins  a ON al.admin_id         = a.id
        {$where}
        ORDER BY al.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    if ($filter) { $stmt->bindValue(':f', $f); $stmt->bindValue(':f2', $f); }
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();
}

// DANE: TICK LOG (aduj tylko gdy tab=tick) 
if ($tab === 'tick') {
    $tickRepo ??= new TickStatsRepository($db);
    $tickSummary24h = $tickRepo->getSummary24h();
    $tickPerPage    = 50;
    $tickWhere      = $tickFilterSource ? "WHERE source = :src" : "";

    $cntStmt = $db->prepare("SELECT COUNT(*) FROM tick_stats {$tickWhere}");
    if ($tickFilterSource) $cntStmt->bindValue(':src', $tickFilterSource);
    $cntStmt->execute();
    $tickTotalRows  = (int)$cntStmt->fetchColumn();
    $tickTotalPages = max(1, (int)ceil($tickTotalRows / $tickPerPage));
    $tickPage       = max(1, min($page, $tickTotalPages));
    $tickOffset     = ($tickPage - 1) * $tickPerPage;

    $lstStmt = $db->prepare("SELECT * FROM tick_stats {$tickWhere} ORDER BY ran_at DESC LIMIT :lim OFFSET :off");
    if ($tickFilterSource) $lstStmt->bindValue(':src', $tickFilterSource);
    $lstStmt->bindValue(':lim', $tickPerPage, PDO::PARAM_INT);
    $lstStmt->bindValue(':off', $tickOffset,  PDO::PARAM_INT);
    $lstStmt->execute();
    $ticks = $lstStmt->fetchAll();
} else {
    $tickPage = 1;
}

// DANE: GAME LOG 
$gameLogLines = [];
$gameLogSize  = 0;
$gameLogHasMore = false;

if ($tab === 'game') {
    if ($retentionGame > 0) {
        try {
            $retentionService->cleanupGameLog($gameLogFile, $retentionGame);
        } catch (Throwable $e) {
            GameLog::warn('admin/logs.php', 'Game log retention failed', ['error' => $e->getMessage()]);
        }
    }
    $gamePage = $gameLogReader->readPage($gameLogFile, $page, $limit);
    $gameLogLines = $gamePage['lines'];
    $gameLogHasMore = $gamePage['has_more'];
    $gameLogSize = $gamePage['size'];
    $totalLogs = count($gameLogLines);
}

$csrfToken = CSRF::generateToken();

$viewData = [
    'tab'              => $tab,
    'logs'             => $logs,
    'filter'           => $filter,
    'limit'            => $limit,
    'page'             => $page,
    'totalPages'       => $totalPages,
    'totalLogs'        => $totalLogs,
    'gameLogLines'     => $gameLogLines,
    'gameLogSize'      => $gameLogSize,
    'gameLogHasMore'   => $gameLogHasMore,
    'msg'              => $msg,
    'err'              => $err,
    'retentionAdmin'   => $retentionAdmin,
    'retentionGame'    => $retentionGame,
    'csrfToken'        => $csrfToken,
    'ticks'            => $ticks,
    'tickSummary24h'   => $tickSummary24h,
    'tickTotalRows'    => $tickTotalRows,
    'tickTotalPages'   => $tickTotalPages,
    'tickPage'         => $tickPage,
    'tickFilterSource' => $tickFilterSource,
    'tickDeleteMsg'    => $tickDeleteMsg,
    'tickCleanupMsg'   => $tickCleanupMsg,
];

$pageTitle = 'Logi';
$adminExtraCss = ['/assets/css/admin_logs.css'];
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/logs/main.php';
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('admin/logs.php', t('common.unhandled_exception'), $e);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('admin/logs.php', $_codexGuardStart);
    }
}
