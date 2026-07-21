<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/2fa.php') : microtime(true);
try {

require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/AdminAuth.php';
require_once __DIR__ . '/../src/Totp.php';

// Full session (already past 2FA) -> panel.
if (AdminAuth::isLoggedIn()) {
    $dest = $_SESSION['admin_redirect'] ?? '/admin/index.php';
    unset($_SESSION['admin_redirect']);
    header('Location: ' . $dest);
    exit();
}

// No pending state -> back to password login.
$pending = AdminAuth::getPending();
if (!$pending) {
    header('Location: /admin/login.php');
    exit();
}

$enabled = !empty($pending['totp_enabled']);
$mode    = $enabled ? 'verify' : 'setup';
$error   = '';
$locale  = $_SESSION['locale'] ?? $_COOKIE['locale'] ?? 'pl';

// Trusted device - skip the 2FA form if a valid token cookie exists.
if ($mode === 'verify' && AdminAuth::checkTrustedDevice($pending['id'])) {
    AdminAuth::completeLogin();
    $dest = $_SESSION['admin_redirect'] ?? '/admin/index.php';
    unset($_SESSION['admin_redirect']);
    header('Location: ' . $dest);
    exit();
}

// Setup mode: generate the secret once and keep it across the POST cycle.
$setupSecret = null;
if ($mode === 'setup') {
    if (empty($_SESSION['admin_2fa_setup_secret'])) {
        $_SESSION['admin_2fa_setup_secret'] = Totp::generateSecret();
    }
    $setupSecret = $_SESSION['admin_2fa_setup_secret'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = tPlain('common.csrf_error');
    } elseif (AdminAuth::isIpBlocked()) {
 // Blokada IP obejmuje rowniez nieudane proby TOTP dzieki logTotpFail().
 // IP block also covers failed TOTP attempts via logTotpFail().
        $error = tPlain('admin.2fa.err_rate_limit');
    } else {
 // Licznik sesyjny poza admin_pending — nie resetuje sie przy ponownym logowaniu haslem.
 // Session fail counter outside admin_pending — not reset by a password re-login.
        $totp2faFails = (int)($_SESSION['admin_2fa_fails'][$pending['id']] ?? 0);
        if ($totp2faFails >= 5) {
            AdminAuth::clearPending();
            AdminAuth::log('2FA_LOCKED', "Too many TOTP attempts — pending cleared for admin_id={$pending['id']}");
            header('Location: /admin/login.php?2fa_locked=1');
            exit();
        }

 // Dodatkowa warstwa: RateLimiter jezeli dostepny.
 // Extra layer: RateLimiter if available.
        if (class_exists('RateLimiter') && !RateLimiter::check('admin_2fa')) {
            $error = tPlain('admin.2fa.err_rate_limit');
        } else {
            $code = preg_replace('/\D/', '', (string)($_POST['code'] ?? ''));

            if ($mode === 'verify') {
 // Pobierz sekret z DB (nie z sesji) przy kazdej weryfikacji.
 // Fetch secret from DB (not from session) on every verification attempt.
                $totpSecret     = AdminAuth::getAdminTotpSecret($pending['id']);
                $matchedCounter = $totpSecret !== null ? Totp::verifyWithCounter($totpSecret, $code) : false;

 // Replay attack: odrzuc ponowne uzycie tego samego kodu w tym samym oknie.
 // Replay protection: reject reuse of the same code within the same time window.
                $lastCounter = $_SESSION['admin_pending']['last_totp_counter'] ?? PHP_INT_MIN;
                if ($matchedCounter !== false && $matchedCounter > $lastCounter) {
                    $_SESSION['admin_pending']['last_totp_counter'] = $matchedCounter;
                    if (!empty($_POST['remember_device'])) {
                        AdminAuth::setTrustedDevice($pending['id']);
                    }
                    AdminAuth::completeLogin();
                    $dest = $_SESSION['admin_redirect'] ?? '/admin/index.php';
                    unset($_SESSION['admin_redirect']);
                    header('Location: ' . $dest);
                    exit();
                }
 // Bledny kod: inkrementuj oba liczniki (sesja + DB/IP).
 // Wrong code: increment both counters (session + DB/IP).
                $_SESSION['admin_2fa_fails'][$pending['id']] = $totp2faFails + 1;
                AdminAuth::logTotpFail($pending['id']);
                $error = tPlain('admin.2fa.err_invalid_code');
            } else {
                if (Totp::verify($setupSecret, $code)) {
                    if (AdminAuth::enableTotpForPending($setupSecret)) {
                        unset($_SESSION['admin_2fa_setup_secret']);
                        AdminAuth::completeLogin();
                        $dest = $_SESSION['admin_redirect'] ?? '/admin/index.php';
                        unset($_SESSION['admin_redirect']);
                        header('Location: ' . $dest);
                        exit();
                    }
                    $error = tPlain('admin.2fa.err_setup_save');
                } else {
 // Bledny kod setupu: inkrementuj oba liczniki / Wrong setup code: increment both.
                    $_SESSION['admin_2fa_fails'][$pending['id']] = $totp2faFails + 1;
                    AdminAuth::logTotpFail($pending['id']);
                    $error = tPlain('admin.2fa.err_setup_code');
                }
            }
        }
    }
}

$csrf    = CSRF::generateToken();
$issuer  = 'OilEmpire';
$account = $pending['email'] ?: $pending['username'] ?: 'admin';
$otpauth = $secretFmt = '';
if ($mode === 'setup') {
    $otpauth   = Totp::provisioningUri($setupSecret, $account, $issuer);
    $secretFmt = Totp::formatSecret($setupSecret);
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= t('admin.2fa.page_title') ?></title>
<link rel="stylesheet" href="/assets/css/admin.css">
<link rel="stylesheet" href="/assets/css/admin_auth.css">
<link rel="stylesheet" href="/assets/css/admin-2fa.css">
</head>
<body class="auth-page">
<div class="auth-wrap">
    <div class="auth-logo">
        <div class="auth-logo-text">Oil<span>Corp</span></div>
        <div class="auth-logo-sub"><?= t('admin.2fa.logo_sub') ?></div>
    </div>

    <div class="auth-card">
        <?php if ($mode === 'setup'): ?>
        <div class="auth-card-title"><?= t('admin.2fa.setup_title') ?></div>
        <?php else: ?>
        <div class="auth-card-title"><?= t('admin.2fa.verify_title') ?></div>
        <?php endif ?>

        <?php if ($error): ?>
        <div class="auth-alert auth-alert-err"><?= htmlspecialchars($error) ?></div>
        <?php endif ?>

        <?php if ($mode === 'setup'): ?>
        <ol class="tfa-steps">
            <li><?= t('admin.2fa.setup_step_app_prefix') ?> <b>Google Authenticator</b> <?= t('admin.2fa.setup_step_app_suffix') ?></li>
            <li><?= t('admin.2fa.setup_step_key_prefix') ?> <b><?= t('admin.2fa.setup_step_key_label') ?></b>.</li>
            <li><?= t('admin.2fa.setup_step_account_prefix') ?> <b><?= htmlspecialchars($account) ?></b>, <?= t('admin.2fa.setup_step_account_suffix') ?></li>
        </ol>
        <div class="tfa-key"><?= htmlspecialchars($secretFmt) ?></div>
        <div class="tfa-hint"><?= t('admin.2fa.setup_hint') ?></div>
        <?php else: ?>
        <p class="tfa-hint tfa-hint--verify"><?= t('admin.2fa.verify_hint') ?></p>
        <?php endif ?>

        <form method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <div class="auth-field">
                <label class="auth-label" for="codeinput"><?= t('admin.2fa.code_label') ?></label>
                <input class="auth-input tfa-code-input" type="text" id="codeinput" name="code"
                    inputmode="numeric" pattern="[0-9]*" maxlength="6"
                    placeholder="000000" autocomplete="one-time-code" autofocus required>
            </div>
            <?php if ($mode === 'verify'): ?>
            <label class="auth-remember-label">
                <input type="checkbox" name="remember_device" value="1">
                <?= t('admin.2fa.remember_device_label') ?>
            </label>
            <?php endif ?>
            <button type="submit" class="auth-btn">
                <?= $mode === 'setup' ? t('admin.2fa.btn_setup') : t('admin.2fa.btn_verify') ?>
            </button>
        </form>

        <div class="auth-links">
            <a href="/admin/logout.php" class="auth-lnk"><?= t('admin.2fa.cancel_link') ?></a>
        </div>
    </div>
</div>
</body>
</html>
<?php
} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('admin/2fa.php', 'Unhandled exception', $e);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo function_exists('t') ? t('common.app_error') : 'Blad aplikacji';
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('admin/2fa.php', $_codexGuardStart);
    }
}
