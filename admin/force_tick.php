<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';

GameLog::info('admin/force_tick.php', 'entry');
AdminAuth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/index.php');
    exit();
}

if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['force_tick_msg'] = t('common.csrf_error');
    $_SESSION['force_tick_error'] = true;
    header('Location: /admin/index.php');
    exit();
}

$lastRun = (int)($_SESSION['force_tick_last'] ?? 0);
if (time() - $lastRun < 5) {
    $_SESSION['force_tick_msg'] = t('admin.force_tick.cooldown');
    $_SESSION['force_tick_error'] = true;
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/index.php'));
    exit();
}
$_SESSION['force_tick_last'] = time();

try {
    $coordinator = new TickCoordinator(Database::getInstance()->getConnection());
    $result = $coordinator->run('admin_force', null, true);

    if ($coordinator->hadLockError()) {
        $_SESSION['force_tick_msg'] = t('admin.force_tick.lock_failed');
        $_SESSION['force_tick_error'] = true;
    } elseif ($coordinator->wasBusy()) {
        $_SESSION['force_tick_msg'] = t('admin.force_tick.busy');
        $_SESSION['force_tick_error'] = true;
    } elseif ($result->status === TickRunResult::STATUS_FAILED) {
        $lastError = $result->errors !== [] ? $result->errors[array_key_last($result->errors)] : null;
        $message = (string)($lastError['message'] ?? 'tick failed');
        AdminLog::log('force_global_tick_error', 'Force tick FAILED: ' . $message, null, 'system');
        $_SESSION['force_tick_msg'] = t('admin.force_tick.err_failed', ['msg' => $message]);
        $_SESSION['force_tick_error'] = true;
    } elseif ($result->status === TickRunResult::STATUS_PARTIAL) {
        $lastError = $result->errors !== [] ? $result->errors[array_key_last($result->errors)] : null;
        $message = (string)($lastError['message'] ?? 'optional module failed');
        AdminLog::log('force_global_tick_warning', 'Force tick PARTIAL: ' . $message, null, 'system');
        $_SESSION['force_tick_msg'] = t('admin.force_tick.msg_partial', [
            'processed' => $result->playersProcessed,
            'price' => $result->oilPrice,
            'msg' => $message,
        ]);
        $_SESSION['force_tick_error'] = false;
    } else {
        AdminLog::log(
            'force_global_tick',
            "Force tick OK - processed {$result->playersProcessed} players, price: {$result->oilPrice}",
            null,
            'system'
        );
        $_SESSION['force_tick_msg'] = t('admin.force_tick.msg_ok', [
            'processed' => $result->playersProcessed,
            'price' => $result->oilPrice,
        ]);
        $_SESSION['force_tick_error'] = false;
    }
} catch (Throwable $e) {
    AdminLog::log('force_global_tick_error', 'Force tick FAILED: ' . $e->getMessage(), null, 'system');
    $_SESSION['force_tick_msg'] = t('admin.force_tick.err_failed', ['msg' => $e->getMessage()]);
    $_SESSION['force_tick_error'] = true;
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/index.php'));
exit();
