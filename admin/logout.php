<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/logout.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';

// Loguj wylogowanie tylko jeśli sesja była w pełni ustanowiona (nie pending 2FA).
// Log only if session was fully established (not pending 2FA).
if (AdminAuth::isLoggedIn()) {
    AdminLog::log('admin_logout', 'Admin wylogował się', null, 'system');
}

// Wyczyść całą sesję (zarówno pełną jak i pending 2FA) i przekieruj na login.
// Clear the whole session (both full login and pending 2FA state) and redirect.
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
