<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('public/logout.php') : microtime(true);
try {


require_once __DIR__ . '/../src/init.php';

Auth::logout();

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('public/logout.php', 'Unhandled exception', $e);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo tPlain('common.app_error');
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('public/logout.php', $_codexGuardStart);
    }
}
