<?php
// Deploy sync after skipped CI gate. / Ponowny upload po pominietej bramce CI.
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/players.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

$db     = Database::getInstance()->getConnection();
$filter = '';
$msg    = '';
$error  = '';

if (isset($_GET['purged'])) {
    $msg = t('admin.players.msg_bulk_deleted', ['count' => max(0, (int)$_GET['purged'])]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = t('common.csrf_error');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'bulk_delete_players') {
            $ids = array_map('intval', (array)($_POST['player_ids'] ?? []));
            try {
                $result = (new AdminPlayerDeletionService($db))->purgeMany($ids);
                AdminLog::log(
                    'players_bulk_purged',
                    'Bulk player purge completed. Deleted=' . $result['deleted'] . ', requested=' . $result['requested'],
                    null,
                    'player',
                    null
                );
                $msg = t('admin.players.msg_bulk_deleted', ['count' => $result['deleted']]);
                if ($result['missing'] > 0) {
                    $msg .= ' ' . t('admin.players.msg_bulk_missing', ['count' => $result['missing']]);
                }
            } catch (Throwable $e) {
                GameLog::error('admin/players.php', 'bulk_delete_players FAILED', $e);
                $error = t('admin.players.err_bulk_delete');
            }
        }
    }
}

$listFilters = AdminPlayerListQuery::filters([]);
try {
    $listFilters = AdminPlayerListQuery::filters($_GET);
    $players = AdminPlayerListQuery::fetch($db, $listFilters);
} catch (InvalidArgumentException $e) {
    $players = [];
    $error = t('admin.players.invalid_dates');
}
$filter = $listFilters['filter'];
$registrationPanelFlag = $_GET['registration_filter'] ?? null;
$showRegistrationFilter = $registrationPanelFlag === '1'
    || ($registrationPanelFlag !== '0' && (isset($_GET['registered_from']) || isset($_GET['registered_to'])));
$registrationFilterActive = $listFilters['registered_from'] !== '' || $listFilters['registered_to'] !== '';
$listFilters['registration_filter'] = $showRegistrationFilter ? '1' : '0';

if (!function_exists('badgeClass')) {
    function badgeClass(string $status): string {
        return match($status) {
            'active'         => 'badge-active',
            'financial_risk' => 'badge-paused',
            'under_bailiff'  => 'badge-paused',
            'bankrupt'       => 'badge-bankrupt',
            default          => 'badge-inactive',
        };
    }
}

$viewData = [
    'players' => $players,
    'filter'  => $filter,
    'listFilters' => $listFilters,
    'showRegistrationFilter' => $showRegistrationFilter,
    'registrationFilterActive' => $registrationFilterActive,
    'msg'     => $msg,
    'error'   => $error,
];

$pageTitle = t('admin.players.page_title');
$extraJs = ['/assets/js/admin_players.js'];
$adminExtraCss = ['/assets/css/admin_players.css'];
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/players/main.php';
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('admin/players.php', 'Unhandled exception', $e);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('admin/players.php', $_codexGuardStart);
    }
}
