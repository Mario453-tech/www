<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/logout.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();
AdminLog::log('admin_logout', 'Admin wylogował się', null, 'system');
AdminAuth::logout();

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('admin/logout.php', 'Unhandled exception', $e);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo 'Wystapil blad aplikacji.';
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('admin/logout.php', $_codexGuardStart);
    }
}
