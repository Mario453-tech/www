<?php
declare(strict_types=1);

$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/tick_modules.php') : microtime(true);

try {
    require_once __DIR__ . '/init.php';
    AdminAuth::requireLogin();

    $db = Database::getInstance()->getConnection();
    $service = new TickModuleAdminService($db);
    $perPage = 10;

    $redirect = static function (?string $moduleKey = null, int $logsPage = 1, int $statsPage = 1): void {
        $url = '/admin/tick_modules.php';
        $params = [];
        if ($moduleKey !== null && $moduleKey !== '') {
            $params['module'] = $moduleKey;
        }
        $params['logs_page'] = max(1, $logsPage);
        $params['stats_page'] = max(1, $statsPage);
        $url .= '?' . http_build_query($params);
        header('Location: ' . $url);
        exit();
    };

    $requestLogsPage = max(1, (int)($_REQUEST['logs_page'] ?? 1));
    $requestStatsPage = max(1, (int)($_REQUEST['stats_page'] ?? 1));

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string)($_POST['action'] ?? '');
        $moduleKey = (string)($_POST['module_key'] ?? '');

        if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['tick_modules_msg'] = ['type' => 'danger', 'text' => tPlain('common.csrf_error')];
            $redirect($moduleKey, $requestLogsPage, $requestStatsPage);
        }

        try {
            if ($action === 'save_settings') {
                $enabled = isset($_POST['enabled']) && (string)$_POST['enabled'] === '1';
                $intervalTicks = (int)($_POST['interval_ticks'] ?? 1);
                $maxItems = (int)($_POST['max_items_per_run'] ?? 1);
                $service->updateSettings($moduleKey, $enabled, $intervalTicks, $maxItems);
                AdminLog::log('tick_module_settings', "Updated tick module {$moduleKey}", null, AdminAuth::getAdminUsername());
                $_SESSION['tick_modules_msg'] = ['type' => 'success', 'text' => tPlain('admin.tick_modules.msg_saved')];
            } elseif ($action === 'restore_recommended') {
                $service->restoreRecommended($moduleKey);
                AdminLog::log('tick_module_restore', "Restored tick module {$moduleKey}", null, AdminAuth::getAdminUsername());
                $_SESSION['tick_modules_msg'] = ['type' => 'success', 'text' => tPlain('admin.tick_modules.msg_restored')];
            } elseif ($action === 'run_module') {
                $service->assertModuleExists($moduleKey);
                $coordinator = new TickCoordinator($db);
                $result = $coordinator->runModule($moduleKey);

                if ($coordinator->hadLockError()) {
                    $_SESSION['tick_modules_msg'] = ['type' => 'danger', 'text' => tPlain('admin.tick_modules.msg_lock_failed')];
                } elseif ($coordinator->wasBusy()) {
                    $_SESSION['tick_modules_msg'] = ['type' => 'warning', 'text' => tPlain('admin.tick_modules.msg_busy')];
                } elseif ($result->status === TickRunResult::STATUS_FAILED) {
                    $lastError = $result->errors !== [] ? $result->errors[array_key_last($result->errors)] : null;
                    $message = (string)($lastError['message'] ?? 'unknown error');
                    $_SESSION['tick_modules_msg'] = [
                        'type' => 'danger',
                        'text' => tPlain('admin.tick_modules.msg_run_failed', ['msg' => $message]),
                    ];
                } elseif ($result->status === TickRunResult::STATUS_PARTIAL) {
                    $lastError = $result->errors !== [] ? $result->errors[array_key_last($result->errors)] : null;
                    $message = (string)($lastError['message'] ?? 'optional module warning');
                    $_SESSION['tick_modules_msg'] = [
                        'type' => 'warning',
                        'text' => tPlain('admin.tick_modules.msg_run_partial', ['msg' => $message]),
                    ];
                } else {
                    $_SESSION['tick_modules_msg'] = ['type' => 'success', 'text' => tPlain('admin.tick_modules.msg_run_ok')];
                }

                AdminLog::log(
                    'tick_module_run',
                    "Manual tick module {$moduleKey}: {$result->status}",
                    null,
                    AdminAuth::getAdminUsername()
                );
            } elseif ($action === 'cleanup_history') {
                $deleted = $service->cleanupHistory(2);
                AdminLog::log(
                    'tick_module_cleanup',
                    'Manual tick history cleanup: stats=' . $deleted['stats_deleted'] . ', logs=' . $deleted['logs_deleted'],
                    null,
                    AdminAuth::getAdminUsername()
                );
                $_SESSION['tick_modules_msg'] = [
                    'type' => 'success',
                    'text' => tPlain('admin.tick_modules.msg_cleanup_done', [
                        'stats' => (string)$deleted['stats_deleted'],
                        'logs' => (string)$deleted['logs_deleted'],
                    ]),
                ];
            } else {
                $_SESSION['tick_modules_msg'] = ['type' => 'danger', 'text' => tPlain('admin.tick_modules.msg_unknown_action')];
            }
        } catch (Throwable $e) {
            GameLog::error('admin/tick_modules.php', 'POST action FAILED', $e, [
                'action' => $action,
                'module_key' => $moduleKey,
            ]);
            $_SESSION['tick_modules_msg'] = [
                'type' => 'danger',
                'text' => tPlain('admin.tick_modules.msg_action_failed', ['msg' => $e->getMessage()]),
            ];
        }

        $redirect($moduleKey, $requestLogsPage, $requestStatsPage);
    }

    $modules = $service->modules();
    $moduleKeys = array_map(static fn(array $row): string => (string)$row['key'], $modules);
    $selectedModule = (string)($_GET['module'] ?? '');
    if ($selectedModule !== '' && !in_array($selectedModule, $moduleKeys, true)) {
        $selectedModule = '';
    }
    $logsPage = max(1, (int)($_GET['logs_page'] ?? 1));
    $statsPage = max(1, (int)($_GET['stats_page'] ?? 1));
    $logsTotal = $service->countRecentLogs($selectedModule !== '' ? $selectedModule : null);
    $statsTotal = $service->countRecentTickStats();
    $logsPages = max(1, (int)ceil($logsTotal / $perPage));
    $statsPages = max(1, (int)ceil($statsTotal / $perPage));
    $logsPage = min($logsPage, $logsPages);
    $statsPage = min($statsPage, $statsPages);

    $msg = $_SESSION['tick_modules_msg'] ?? null;
    unset($_SESSION['tick_modules_msg']);

    $pageTitle = t('admin.tick_modules.page_title');
    $adminExtraCss = ['/assets/css/admin_tick_modules.css'];
    $csrfToken = CSRF::generateToken();
    $viewData = [
        'modules' => $modules,
        'moduleKeys' => $moduleKeys,
        'selectedModule' => $selectedModule,
        'recentLogs' => $service->recentLogs($selectedModule !== '' ? $selectedModule : null, $perPage, ($logsPage - 1) * $perPage),
        'recentTickStats' => $service->recentTickStats($perPage, ($statsPage - 1) * $perPage),
        'logsPage' => $logsPage,
        'statsPage' => $statsPage,
        'logsPages' => $logsPages,
        'statsPages' => $statsPages,
        'logsTotal' => $logsTotal,
        'statsTotal' => $statsTotal,
        'perPage' => $perPage,
        'msg' => $msg,
        'csrfToken' => $csrfToken,
    ];

    require_once __DIR__ . '/partials/header.php';
    require __DIR__ . '/../templates/views/admin/tick_modules/main.php';
    require_once __DIR__ . '/partials/footer.php';
} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('admin/tick_modules.php', 'Unhandled exception', $e);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('admin/tick_modules.php', $_codexGuardStart);
    }
}
