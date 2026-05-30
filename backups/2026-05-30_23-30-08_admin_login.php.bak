<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/login.php') : microtime(true);
try {

require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/AdminAuth.php';

// SSO jeli gracz ju zalogowany i ma konto admina wejd bez hasa
if (AdminAuth::trySSO()) {
    $dest = $_SESSION['admin_redirect'] ?? '/admin/index.php';
    unset($_SESSION['admin_redirect']);
    header('Location: ' . $dest);
    exit();
}

if (AdminAuth::isLoggedIn()) {
    header('Location: /admin/index.php');
    exit();
}

$error = $success = '';
if (isset($_GET['logged_out'])) $success = t('admin.login.msg_logged_out');
if (isset($_GET['reset']))      $success = t('admin.login.msg_reset_sent');
if (isset($_GET['locked']))     $error   = t('admin.login.err_locked');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = t('common.csrf_error');
    } elseif (AdminAuth::isIpBlocked()) {
        $error = t('admin.login.err_ip_blocked');
    } else {
        $login    = Validator::sanitize($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';

        if (AdminAuth::login($login, $password)) {
            $dest = $_SESSION['admin_redirect'] ?? '/admin/index.php';
            unset($_SESSION['admin_redirect']);
            header('Location: ' . $dest);
            exit();
        }
        $error = t('admin.login.err_invalid_credentials');
    }
}

$csrf = CSRF::generateToken();

$viewData = [
    'error'   => $error,
    'success' => $success,
    'csrf'    => $csrf,
    'login'   => $_POST['login'] ?? '',
    'hasSso'  => !empty($_SESSION['user_id']),
];
require __DIR__ . '/../templates/views/admin/login/main.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('admin/login.php', 'Unhandled exception', $e);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('admin/login.php', $_codexGuardStart);
    }
}
