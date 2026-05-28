<?php
declare(strict_types=1);

$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/finance.php') : microtime(true);

try {
    require_once __DIR__ . '/init.php';
    AdminAuth::requireLogin();

    $db = Database::getInstance()->getConnection();

    require_once __DIR__ . '/../src/FinanceService.php';
    require_once __DIR__ . '/../src/FinancePolicyService.php';
    require_once __DIR__ . '/partials/finance_admin_actions.php';
    require_once __DIR__ . '/partials/finance_admin_metrics.php';

    $finSvc = new FinanceService();
    $policySvc = new FinancePolicyService($db);

    $msg = '';
    $err = '';

    $hours = (int)($_GET['hours'] ?? 24);
    if (!in_array($hours, [24, 168, 720], true)) {
        $hours = 24;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
            die(t('common.csrf_error'));
        }

        $action = (string)($_POST['action'] ?? '');
        $result = adminFinanceHandlePost($db, $action, $_POST);
        $msg = (string)($result['msg'] ?? '');
        $err = (string)($result['err'] ?? '');
    }

    $config = adminFinanceLoadConfig($db);
    $mults = adminFinanceLoadSavingsMultipliers($db);

    $viewData = adminFinanceBuildViewData($db, $finSvc, $policySvc, $hours, $config, $mults);
    $viewData = array_merge($viewData, [
        'msg' => $msg,
        'err' => $err,
        'hours' => $hours,
        'rangeOptions' => [24 => '24h', 168 => '7 dni', 720 => '30 dni'],
    ]);

    $pageTitle = t('admin.finance.page_title');
    require_once __DIR__ . '/partials/header.php';
    extract($viewData, EXTR_SKIP);
    require __DIR__ . '/../templates/views/admin/finance/main.php';
    require_once __DIR__ . '/partials/footer.php';
} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('admin/finance.php', 'Exception', $e);
    }
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('admin/finance.php', $_codexGuardStart);
    }
}
