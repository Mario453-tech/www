<?php
declare(strict_types=1);

/**
 * Admin Visit Statistics Controller.
 * Kontroler statystyk odwiedzin w panelu admina.
 */

$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/visits.php') : microtime(true);

try {
    require_once __DIR__ . '/init.php';
    require_once __DIR__ . '/../src/Visits/VisitTrackerService.php';

    AdminAuth::requireLogin();

    $db = Database::getInstance()->getConnection();
    VisitTrackerService::ensureTablesExist($db);

    $msg = '';
    $err = '';
    $flash = $_SESSION['admin_visits_flash'] ?? null;
    unset($_SESSION['admin_visits_flash']);
    if (is_array($flash)) {
        if (($flash['type'] ?? '') === 'error') {
            $err = (string)($flash['message'] ?? '');
        } else {
            $msg = (string)($flash['message'] ?? '');
        }
    }

    // Handle maintenance cleanup POST action with PRG pattern.
    // Obsluga akcji POST czyszczenia retencji ze wzorcem PRG.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['admin_visits_flash'] = ['type' => 'error', 'message' => t('common.csrf_error')];
        } else {
            $action = $_POST['action'] ?? '';
            if ($action === 'cleanup_events') {
                $days = max(14, min((int)($_POST['days'] ?? 60), 365));
                $delStmt = $db->prepare("DELETE FROM site_visit_events WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
                $delStmt->execute([$days]);
                $deletedCount = $delStmt->rowCount();

                AdminLog::log('visits_cleanup', "Cleaned {$deletedCount} visit event rows older than {$days} days");
                $_SESSION['admin_visits_flash'] = [
                    'type' => 'success',
                    'message' => t('admin.visits.msg_cleaned', ['count' => $deletedCount, 'days' => $days]),
                ];
            }
        }
        header('Location: /admin/visits.php');
        exit;
    }

    $validPeriods = ['today', '7d', '30d', '90d', 'all'];
    $period = in_array($_GET['period'] ?? '7d', $validPeriods, true) ? (string)$_GET['period'] : '7d';

    $countryFilter = null;
    if (!empty($_GET['country'])) {
        $c = strtoupper(preg_replace('/[^a-zA-Z]/', '', (string)$_GET['country']));
        if (strlen($c) >= 2 && strlen($c) <= 5) {
            $countryFilter = $c;
        }
    }

    $dashboardData = VisitTrackerService::getDashboardData($db, $period, $countryFilter);

    $pageTitle = t('admin.visits.title');
    $adminExtraCss = ['/assets/css/admin_visits.css'];
    $extraJs = ['/assets/js/admin_visits.js'];

    require_once __DIR__ . '/partials/header.php';
    require __DIR__ . '/../templates/views/admin/visits/main.php';
    require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('admin/visits.php', 'Unhandled exception in visit statistics', $e);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('admin/visits.php', $_codexGuardStart);
    }
}
