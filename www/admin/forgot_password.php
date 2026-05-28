<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/forgot_password.php') : microtime(true);
try {

require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/AdminAuth.php';

if (AdminAuth::isLoggedIn()) { header('Location: /admin/index.php'); exit(); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = t('common.csrf_error');
    } else {
        $email = Validator::sanitize($_POST['email'] ?? '');
        AdminAuth::sendPasswordReset($email);
        header('Location: /admin/login.php?reset=1');
        exit();
    }
}
$csrf = CSRF::generateToken();

$viewData = [
    'error' => $error,
    'csrf'  => $csrf,
    'email' => $_POST['email'] ?? '',
];
require __DIR__ . '/../templates/views/admin/forgot_password/main.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('admin/forgot_password.php', 'Unhandled exception', $e);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('admin/forgot_password.php', $_codexGuardStart);
    }
}
