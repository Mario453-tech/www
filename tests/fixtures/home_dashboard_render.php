<?php
declare(strict_types=1);

session_start();
$_SESSION['locale'] = in_array($_GET['locale'] ?? '', ['pl', 'en'], true) ? $_GET['locale'] : 'pl';
require_once dirname(__DIR__, 2) . '/src/i18n.php';
require_once dirname(__DIR__, 2) . '/src/CSRF.php';
require_once dirname(__DIR__, 2) . '/src/GameNavigation.php';
function url(string $key): string { return '/' . $key; }
function asset(string $path): string { return $path; }
$__curPath = '/';
$__groupedNav = GameNavigation::build(array_map(static fn(string $key): array => [
    'url_key' => $key, 'lang_key' => 'nav.' . $key,
], ['map', 'technical', 'logistics', 'market', 'bank', 'finance', 'contracts', 'dashboard', 'hr', 'legal']), $__curPath);
$dashboardStats = [
    'state' => 'ready', 'period' => '30d',
    'overview' => ['production_rate' => 120.0, 'revenue_rate' => 8000.0,
        'production_change' => 5.0, 'revenue_change' => 7.0],
    'series' => [['label' => '2026-10-01', 'value' => 120.0]],
    'chart_points' => '24,30 616,30',
    'wells' => ['total' => 3, 'active' => 2, 'attention' => 1, 'critical' => 1,
        'top' => [['id' => 1, 'name' => 'E-1', 'production' => 60.0, 'condition' => 95.0]],
        'transport_mix' => ['road' => 1, 'pipeline' => 1, 'sea' => 0], 'active_routes' => 2],
    'logistics' => ['mix' => ['road' => 1, 'pipeline' => 1, 'sea' => 0],
        'active_routes' => 2, 'on_time_pct' => 90.0, 'loss_bbl' => 2.0, 'cost_rate' => 100.0],
    'finance' => ['revenue_rate' => 8000.0, 'cost_rate' => 3000.0, 'net_rate' => 5000.0,
        'costs' => ['extraction' => 1000.0, 'logistics' => 500.0, 'staff' => 1000.0, 'other' => 500.0]],
];
$dashboardChatViewData = [
    'dashboardEmbed' => true, 'flash' => '', 'playerId' => 1, 'locale' => $_SESSION['locale'],
    'activeRoom' => ['id' => 1, 'slug' => 'polski', 'name' => 'Polski', 'member_count' => 1, 'status' => 'active'],
    'rooms' => [['id' => 1, 'slug' => 'polski', 'name' => 'Polski', 'member_count' => 1,
        'message_count' => 1, 'unread_count' => 0]],
    'directThreads' => [], 'withPartnerId' => 0, 'withPartnerName' => '',
    'history' => [['sender_id' => 1, 'sender_name' => 'Gracz', 'time' => '12:00', 'message' => 'Witaj w pokoju.']],
    'presenceData' => ['total_online' => 1, 'players' => []],
];
$notifications = [
    ['id' => 1, 'priority' => 'high', 'title' => 'Ostrzezenie', 'message' => 'Powiadomienie testowe.', 'created_at' => '2026-10-10 10:00:00', 'action_url' => '/technical'],
    ['id' => 2, 'priority' => 'low', 'title' => 'Informacja', 'message' => 'Powiadomienie testowe.', 'created_at' => '2026-10-10 11:00:00', 'action_url' => '/legal'],
];
$alertWells = [['id' => 2, 'location_name' => 'Rumaila', '_cond' => 0]];
$techNotifications = [];
?><!doctype html>
<html lang="<?= $_SESSION['locale'] ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/assets/css/style.css"><link rel="stylesheet" href="/assets/css/chat.css"><link rel="stylesheet" href="/assets/css/home_dashboard.css">
<link rel="stylesheet" href="/assets/css/game_nav.css">
</head><body><div class="container"><header class="header header--redesign"><?php require dirname(__DIR__, 2) . '/templates/components/game_navigation.php'; ?></header><main class="dashboard">
<?php require dirname(__DIR__, 2) . '/templates/components/company_statistics.php'; ?>
<?php require dirname(__DIR__, 2) . '/templates/components/activity_center.php'; ?>
</main></div><script src="/assets/js/home_dashboard.js"></script><script src="/assets/js/dashboard_news.js"></script><script src="/assets/js/game_nav.js"></script></body></html>
