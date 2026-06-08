<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/login.php') : microtime(true);
try {

require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/AdminAuth.php';

// Pelna sesja admina (juz po 2FA) -> prosto do panelu.
// Full admin session (already past 2FA) -> straight to the panel.
if (AdminAuth::isLoggedIn()) {
    header('Location: /admin/index.php');
    exit();
}

// Auto-login przez zaufane urządzenie -> pomiń formularz.
// Auto-login via trusted device -> skip the login form entirely.
if (AdminAuth::tryTrustedDevice()) {
    header('Location: /admin/index.php');
    exit();
}

// Haslo/SSO juz podane, czekamy na kod 2FA -> krok kodu.
// Password/SSO already provided, awaiting 2FA code -> code step.
if (AdminAuth::hasPending()) {
    header('Location: /admin/2fa.php');
    exit();
}

// SSO: jesli zalogowany gracz ma konto admina, ustaw stan oczekujacy 2FA.
// SSO: if a logged-in player has an admin account, set the pending 2FA state.
if (AdminAuth::trySSO()) {
    header('Location: /admin/2fa.php');
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
            // Haslo OK -> krok kodu 2FA (pelna sesja dopiero po weryfikacji).
            // Password OK -> 2FA code step (full session only after verification).
            header('Location: /admin/2fa.php');
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
