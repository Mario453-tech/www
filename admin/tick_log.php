<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/tick_log.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

require_once __DIR__ . '/../src/Tick/TickStatsRepository.php';
require_once __DIR__ . '/../src/AdminLogs/TickHistoryQuery.php';

$db   = Database::getInstance()->getConnection();
$history = new TickHistoryQuery($db);

// Filtr source 
$filterSource = in_array($_GET['source'] ?? '', ['cron','force']) ? $_GET['source'] : '';

// Paginacja 
$perPage = 50;
$page    = max(1, (int)($_GET['page'] ?? 1));

// Read only the summary columns needed by the view.
// Odczytaj tylko kolumny podsumowania potrzebne w widoku.
$result = $history->page($filterSource, $page, $perPage);
$totalRows = $result['total'];
$totalPages = $result['pages'];
$page = $result['page'];
$ticks = $result['rows'];

// Dane: podsumowanie 24h
$summary24h = $history->summary24h();

// Dane: ostatni tick (status + czas)
$lastTickRow = null;
try {
    $lastTickRow = $db->query("SELECT ran_at, source, duration_ms, players_processed, oil_price FROM tick_stats ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch (Throwable) {}

// Flash z force_tick.php
$ftMsg   = $_SESSION['force_tick_msg']   ?? '';
$ftError = $_SESSION['force_tick_error'] ?? false;
unset($_SESSION['force_tick_msg'], $_SESSION['force_tick_error']);

// Akcja: usu wpis 
$deleteMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $deleteMsg = 'error:' . t('common.csrf_error');
    } else {
        $delId = (int)$_POST['delete_id'];
        $db->prepare("DELETE FROM tick_stats WHERE id = ?")->execute([$delId]);
        AdminLog::log('tick_log_delete', "Usunieto wpis tick_stats #{$delId}", null, AdminAuth::getAdminUsername());
        $deleteMsg = 'ok:' . t('admin.tick_log.msg_deleted');
    }
}

// Akcja: cleanup 
$cleanupMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cleanup_days'])) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $cleanupMsg = 'error:' . t('common.csrf_error');
    } else {
        $days    = max(1, min(365, (int)$_POST['cleanup_days']));
        $deleted = (new TickStatsRepository($db))->cleanup($days);
        AdminLog::log('tick_log_cleanup', "Cleanup tick_stats: usunieto {$deleted} wpisow starszych niz {$days} dni", null, AdminAuth::getAdminUsername());
        $cleanupMsg = 'ok:' . t('admin.tick_log.msg_cleanup', ['count' => $deleted, 'days' => $days]);
        // Refresh the page after cleanup. / Odswiez strone po czyszczeniu.
        $result = $history->page($filterSource, $page, $perPage);
        $totalRows = $result['total'];
        $totalPages = $result['pages'];
        $page = $result['page'];
        $ticks = $result['rows'];
    }
}

$pageTitle = t('admin.tick_log.page_title');
$csrfToken = CSRF::generateToken();

$viewData = [
    'ticks'        => $ticks,
    'summary24h'   => $summary24h,
    'lastTickRow'  => $lastTickRow,
    'ftMsg'        => $ftMsg,
    'ftError'      => $ftError,
    'totalRows'    => $totalRows,
    'totalPages'   => $totalPages,
    'page'         => $page,
    'perPage'      => $perPage,
    'filterSource' => $filterSource,
    'deleteMsg'    => $deleteMsg,
    'cleanupMsg'   => $cleanupMsg,
    'csrfToken'    => $csrfToken,
];

$adminExtraCss = ['/assets/css/admin_logs.css'];
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/tick_log/main.php';
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) GameLog::error('admin/tick_log.php', 'Unhandled exception', $e);
    if (!headers_sent()) http_response_code(500);
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) GameLog::pageEnd('admin/tick_log.php', $_codexGuardStart);
}
