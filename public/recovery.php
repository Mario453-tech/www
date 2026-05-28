<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('public/recovery.php') : microtime(true);

try {
    require_once __DIR__ . '/../src/init.php';
    Auth::requireLogin();

    $playerId = (int)($_SESSION['user_id'] ?? 0);
    $service  = new BankruptcyService($playerId);
    $state    = $service->getState();

    if (empty($state['exists']) || empty($state['is_bankrupt'])) {
        header('Location: /');
        exit();
    }

    $message = '';
    $error   = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
                $error = t('common.error_csrf');
            } else {
                $action  = (string)($_POST['action'] ?? '');
                $payload = [
                    'asset_type' => (string)($_POST['asset_type'] ?? 'well'),
                    'well_id'    => (int)($_POST['well_id'] ?? 0),
                ];
                $result  = $service->applyOption($action, $payload);
                if (!empty($result['success'])) {
                    $message = (string)($result['message'] ?? t('common.success'));
                } else {
                    $error = (string)($result['message'] ?? t('common.error_generic'));
                }
            }
        } catch (Throwable $e) {
            $error = t('common.error_generic');
            GameLog::error('public/recovery.php', 'POST handling failed', $e, ['player_id' => $playerId]);
        }
        $state = $service->getState();
    }

    $options      = $service->getRecoveryOptions();
    $notice       = $_SESSION['bankruptcy_notice'] ?? '';
    unset($_SESSION['bankruptcy_notice']);
    $events       = $state['events'] ?? [];
    $criticalOpen = (int)($state['critical_open'] ?? 0);

    $cash       = (float)($state['player']['cash']               ?? 0);
    $debtActive = (float)($state['loans']['debt_active']         ?? 0);
    $debtLate   = (float)($state['loans']['debt_late']           ?? 0);
    $bkStatus   = (string)($state['player']['bankruptcy_status'] ?? 'restructuring');

    $statusLabels = [
        'restructuring' => t('recovery.status_restructuring'),
        'liquidation'   => t('recovery.status_liquidation'),
        'recovered'     => t('recovery.status_recovered'),
    ];

    $pageTitle = t('recovery.page_title');

    $viewData = compact(
        'options', 'notice', 'events', 'criticalOpen',
        'cash', 'debtActive', 'debtLate', 'bkStatus',
        'statusLabels', 'message', 'error'
    );

    require_once __DIR__ . '/../templates/header.php';
    require __DIR__ . '/../templates/views/recovery/main.php';
    require_once __DIR__ . '/../templates/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('public/recovery.php', 'Unhandled exception', $e);
    }
    if (!headers_sent()) http_response_code(500);
    echo t('common.error_generic');
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('public/recovery.php', $_codexGuardStart);
    }
}
