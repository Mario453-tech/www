<?php
/**
 * admin/init.php
 * Ładuje src/init.php gry oraz klasy admina.
 */
$_codexGuardStart = microtime(true);
try {
    $root = __DIR__ . '/../';

    require_once $root . 'src/init.php';
    GameLog::info('admin/init.php', 'Admin bootstrap start');

    require_once $root . 'src/AdminAuth.php';
    require_once $root . 'src/AdminLog.php';

    if (file_exists($root . 'src/BankSettings.php')) {
        require_once $root . 'src/BankSettings.php';
    }
} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('admin/init.php', 'Admin bootstrap failed', $e);
    }
    throw $e;
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::perf('admin/init.php', 'bootstrap', $_codexGuardStart);
    }
}
