<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/2fa.php') : microtime(true);
try {

require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/AdminAuth.php';
require_once __DIR__ . '/../src/Totp.php';

// Pelna sesja (juz po 2FA) -> panel.
// Full session (already past 2FA) -> panel.
if (AdminAuth::isLoggedIn()) {
    $dest = $_SESSION['admin_redirect'] ?? '/admin/index.php';
    unset($_SESSION['admin_redirect']);
    header('Location: ' . $dest);
    exit();
}

// Brak stanu oczekujacego -> wracaj do logowania haslem.
// No pending state -> back to password login.
$pending = AdminAuth::getPending();
if (!$pending) {
    header('Location: /admin/login.php');
    exit();
}

$enabled = !empty($pending['totp_enabled']) && !empty($pending['totp_secret']);
$mode    = $enabled ? 'verify' : 'setup';
$error   = '';

// Tryb konfiguracji: wygeneruj sekret raz i trzymaj w sesji przez cykl POST.
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
        $error = t('common.csrf_error');
    } elseif (class_exists('RateLimiter') && !RateLimiter::check('admin_2fa')) {
        $error = 'Za dużo prób. Odczekaj chwilę i spróbuj ponownie.';
    } else {
        $code = preg_replace('/\D/', '', (string)($_POST['code'] ?? ''));

        if ($mode === 'verify') {
            if (Totp::verify($pending['totp_secret'], $code)) {
                AdminAuth::completeLogin();
                $dest = $_SESSION['admin_redirect'] ?? '/admin/index.php';
                unset($_SESSION['admin_redirect']);
                header('Location: ' . $dest);
                exit();
            }
            $error = 'Nieprawidłowy kod. Sprawdź, czy zegar w telefonie jest zsynchronizowany.';
        } else { // setup
            if (Totp::verify($setupSecret, $code)) {
                if (AdminAuth::enableTotpForPending($setupSecret)) {
                    unset($_SESSION['admin_2fa_setup_secret']);
                    AdminAuth::completeLogin();
                    $dest = $_SESSION['admin_redirect'] ?? '/admin/index.php';
                    unset($_SESSION['admin_redirect']);
                    header('Location: ' . $dest);
                    exit();
                }
                $error = 'Nie udało się zapisać 2FA. Uruchom najpierw sql/2fa_admins.sql w bazie.';
            } else {
                $error = 'Kod nie pasuje. Zeskanuj/wpisz klucz ponownie i sprawdź synchronizację czasu.';
            }
        }
    }
}

$csrf    = CSRF::generateToken();
$issuer  = 'OilEmpire';
$account = $pending['email'] ?: $pending['username'];
$otpauth = $secretFmt = '';
if ($mode === 'setup') {
    $otpauth   = Totp::provisioningUri($setupSecret, $account, $issuer);
    $secretFmt = Totp::formatSecret($setupSecret);
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Weryfikacja dwuetapowa — OilCorp</title>
<link rel="stylesheet" href="/assets/css/admin.css">
<style>
.tfa-steps{font-size:13px;color:#bbb;line-height:1.7;margin:0 0 18px;padding-left:18px}
.tfa-key{font-family:monospace;font-size:18px;letter-spacing:2px;background:#111;border:1px solid #333;
    color:#f90;padding:12px;border-radius:6px;text-align:center;margin:8px 0 4px;user-select:all;word-break:break-all}
.tfa-hint{font-size:11px;color:#777;margin:0 0 16px;text-align:center}
.tfa-code-input{font-family:monospace;font-size:24px;letter-spacing:8px;text-align:center}
</style>
</head>
<body class="auth-page">
<div class="auth-wrap">
    <div class="auth-logo">
        <div class="auth-logo-text">Oil<span>Corp</span></div>
        <div class="auth-logo-sub">Weryfikacja dwuetapowa</div>
    </div>

    <div class="auth-card">
        <?php if ($mode === 'setup'): ?>
        <div class="auth-card-title">Skonfiguruj Google Authenticator</div>
        <?php else: ?>
        <div class="auth-card-title">Podaj kod z aplikacji</div>
        <?php endif ?>

        <?php if ($error): ?>
        <div class="auth-alert auth-alert-err"><?= htmlspecialchars($error) ?></div>
        <?php endif ?>

        <?php if ($mode === 'setup'): ?>
        <ol class="tfa-steps">
            <li>Zainstaluj aplikację <b>Google Authenticator</b> (lub Authy / Microsoft Authenticator).</li>
            <li>Dodaj konto → <b>„Wprowadź klucz konfiguracji"</b> (Enter a setup key).</li>
            <li>Nazwa konta: <b><?= htmlspecialchars($account) ?></b>, a klucz poniżej:</li>
        </ol>
        <div class="tfa-key"><?= htmlspecialchars($secretFmt) ?></div>
        <div class="tfa-hint">Typ: oparty na czasie (TOTP) • 6 cyfr • co 30 s</div>
        <?php else: ?>
        <p class="tfa-hint" style="margin-bottom:18px">Wpisz 6-cyfrowy kod wyświetlany w aplikacji uwierzytelniającej.</p>
        <?php endif ?>

        <form method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <div class="auth-field">
                <label class="auth-label" for="codeinput">Kod z aplikacji</label>
                <input class="auth-input tfa-code-input" type="text" id="codeinput" name="code"
                    inputmode="numeric" pattern="[0-9]*" maxlength="6"
                    placeholder="000000" autocomplete="one-time-code" autofocus required>
            </div>
            <button type="submit" class="auth-btn">
                <?= $mode === 'setup' ? 'Włącz 2FA i zaloguj' : 'Zweryfikuj i zaloguj' ?>
            </button>
        </form>

        <div class="auth-links">
            <a href="/admin/logout.php" class="auth-lnk">Anuluj / wyloguj</a>
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
    echo function_exists('t') ? t('common.app_error') : 'Błąd aplikacji';
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('admin/2fa.php', $_codexGuardStart);
    }
}
