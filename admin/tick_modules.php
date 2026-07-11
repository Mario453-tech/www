<?php
declare(strict_types=1);

$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/tick_modules.php') : microtime(true);

try {
    require_once __DIR__ . '/init.php';
    AdminAuth::requireLogin();

    $db = Database::getInstance()->getConnection();
    $service = new TickModuleAdminService($db);

    $redirect = static function (?string $moduleKey = null): void {
        $url = '/admin/tick_modules.php';
        if ($moduleKey !== null && $moduleKey !== '') {
            $url .= '?module=' . rawurlencode($moduleKey);
        }
        header('Location: ' . $url);
        exit();
    };

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string)($_POST['action'] ?? '');
        $moduleKey = (string)($_POST['module_key'] ?? '');

        if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['tick_modules_msg'] = ['type' => 'danger', 'text' => tPlain('common.csrf_error')];
            $redirect($moduleKey);
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

        $redirect($moduleKey);
    }

    $modules = $service->modules();
    $moduleKeys = array_map(static fn(array $row): string => (string)$row['key'], $modules);
    $selectedModule = (string)($_GET['module'] ?? '');
    if ($selectedModule !== '' && !in_array($selectedModule, $moduleKeys, true)) {
        $selectedModule = '';
    }

    $msg = $_SESSION['tick_modules_msg'] ?? null;
    unset($_SESSION['tick_modules_msg']);

    $pageTitle = t('admin.tick_modules.page_title');
    $adminExtraCss = ['/assets/css/admin_tick_modules.css'];
    $csrfToken = CSRF::generateToken();
    $viewData = [
        'modules' => $modules,
        'moduleKeys' => $moduleKeys,
        'selectedModule' => $selectedModule,
        'recentLogs' => $service->recentLogs($selectedModule !== '' ? $selectedModule : null, 80),
        'recentTickStats' => $service->recentTickStats(12),
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
