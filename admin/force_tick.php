<?php
/**
 * force_tick.php Wymu globalny tick (identyczna logika jak cron/tick.php)
 * Wywoywany POST-em z dashboard lub market.php
 */
require_once __DIR__ . '/init.php';
GameLog::info('admin/force_tick.php', 'entry');
AdminAuth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/index.php');
    exit();
}

// CSRF
if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['force_tick_msg']   = t('common.csrf_error');
    $_SESSION['force_tick_error'] = true;
    header('Location: /admin/index.php');
    exit();
}

// Zabezpieczenie przed wielokrotnym klikaniem (cooldown 5 sekund)
$lastRun = $_SESSION['force_tick_last'] ?? 0;
if (time() - $lastRun < 5) {
    $_SESSION['force_tick_msg']   = t('admin.force_tick.cooldown');
    $_SESSION['force_tick_error'] = true;
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/index.php'));
    exit();
}
$_SESSION['force_tick_last'] = time();

try {
    define('FORCE_TICK_INTERNAL', true); // pomija guard HTTP w cron/tick.php
    unset($GLOBALS['OILCORP_TICK_BUSY'], $GLOBALS['OILCORP_TICK_LOCK_ERROR']);

    ob_start();
    require __DIR__ . '/../cron/tick.php';
    $tickOutput = ob_get_clean();

    if (!empty($GLOBALS['OILCORP_TICK_LOCK_ERROR'])) {
        $_SESSION['force_tick_msg']   = t('admin.force_tick.lock_failed');
        $_SESSION['force_tick_error'] = true;
    } elseif (!empty($GLOBALS['OILCORP_TICK_BUSY'])) {
        $_SESSION['force_tick_msg']   = t('admin.force_tick.busy');
        $_SESSION['force_tick_error'] = true;
    } else {
        $processed = 0;
        $newPrice   = '?';
        if (preg_match('/Gracze:\s*(\d+)/', $tickOutput, $m)) $processed = (int)$m[1];
        if (preg_match('/Cena:\s*([\d.]+)/', $tickOutput, $m)) $newPrice  = $m[1];

        AdminLog::log('force_global_tick', "Force tick OK - processed {$processed} players, price: {$newPrice}", null, 'system');
        $msg = t('admin.force_tick.msg_ok', ['processed' => $processed, 'price' => $newPrice]);
        $_SESSION['force_tick_msg']   = $msg;
        $_SESSION['force_tick_error'] = false;
    }

} catch (Throwable $e) {
    if (ob_get_level()) ob_end_clean();
    AdminLog::log('force_global_tick_error', 'Force tick FAILED: ' . $e->getMessage(), null, 'system');
    $err = t('admin.force_tick.err_failed', ['msg' => $e->getMessage()]);
    $_SESSION['force_tick_msg']   = $err;
    $_SESSION['force_tick_error'] = true;
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/index.php'));
exit();