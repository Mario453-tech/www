<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/players.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

$db     = Database::getInstance()->getConnection();
$filter = $_GET['filter'] ?? '';
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

$where = match($filter) {
    'active'        => "WHERE p.status = 'active'",
    'bankrupt'      => "WHERE p.status = 'bankrupt'",
    'financial_risk'=> "WHERE p.status = 'financial_risk'",
    'under_bailiff' => "WHERE p.status = 'under_bailiff'",
    default         => '',
};

$players = $db->query("
    SELECT
        p.id, p.email, p.cash, p.status, p.last_login_at,
        s.used AS storage_used,
        s.capacity AS storage_capacity,
        (SELECT COUNT(*) FROM wells w WHERE w.player_id = p.id) AS well_count
    FROM players p
    LEFT JOIN storage s ON p.id = s.player_id
    {$where}
    ORDER BY p.id ASC
")->fetchAll();

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
    'msg'     => $msg,
    'error'   => $error,
];

$pageTitle = t('admin.players.page_title');
$extraJs = ['/assets/js/admin_players.js'];
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
