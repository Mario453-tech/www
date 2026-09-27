<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/logout.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}
if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo t('common.csrf_error');
    exit;
}

// Loguj wylogowanie tylko jeśli sesja była w pełni ustanowiona (nie pending 2FA).
// Log only if session was fully established (not pending 2FA).
if (AdminAuth::isLoggedIn()) {
    AdminLog::log('admin_logout', 'Admin logged out', null, 'system');
}

// Clear admin authentication and remembered device. / Wyczysc logowanie admina i zapamietane urzadzenie.
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
