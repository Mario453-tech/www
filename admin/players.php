<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/players.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

$db     = Database::getInstance()->getConnection();
$filter = $_GET['filter'] ?? '';

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
];

$pageTitle = t('admin.players.page_title');
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
