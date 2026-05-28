<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('public/reset_password.php') : microtime(true);
try {

require_once __DIR__ . '/src/init.php';

if (Auth::isLoggedIn()) {
    header('Location: ' . url('home'));
    exit();
}

$token   = Validator::sanitize($_GET['token'] ?? '');
$error   = '';
$success = false;

// Zweryfikuj token
$tokenData = Auth::verifyResetToken($token);
$tokenValid = ($tokenData !== false);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = t('common.csrf_error');
    } elseif (!$tokenValid) {
        $error = t('reset_password.err_token_invalid');
    } else {
        $password  = $_POST['password']  ?? '';
        $password2 = $_POST['password2'] ?? '';

        if (strlen($password) < 8) {
            $error = t('reset_password.err_password_short');
        } elseif ($password !== $password2) {
            $error = t('reset_password.err_password_mismatch');
        } else {
            $result = Auth::resetPassword($token, $password);
            if ($result) {
                $success = true;
            } else {
                $error = t('reset_password.err_token_expired');
            }
        }
    }
}

$pageTitle = t('reset_password.page_title');
$viewData = [
    'error'      => $error,
    'success'    => $success,
    'tokenValid' => $tokenValid,
    'tokenData'  => $tokenData,
    'token'      => $token,
];
require_once __DIR__ . '/templates/header.php';
require __DIR__ . '/templates/views/public/reset_password/main.php';
require_once __DIR__ . '/templates/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('reset_password.php', 'Unhandled exception', $e);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('reset_password.php', $_codexGuardStart);
    }
}