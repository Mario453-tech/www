<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('public/forgot_password.php') : microtime(true);
try {

require_once __DIR__ . '/../src/init.php';

if (Auth::isLoggedIn()) {
    header('Location: /');
    exit();
}

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = tPlain('auth.err_csrf');

    } elseif (!RateLimiter::checkProgressive('forgot_password')) {
        $wait  = RateLimiter::getWaitSeconds('forgot_password');
        $mins  = (int)ceil($wait / 60);
        $unit  = ($mins === 1) ? tPlain('forgot_password.err_minute') : tPlain('forgot_password.err_minutes');
        $error = tPlain('forgot_password.err_rate_limit', ['mins' => $mins, 'unit' => $unit]);

    } else {
        $email = Validator::sanitize($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            RateLimiter::registerFailure('forgot_password');
            $error = tPlain('auth.err_email_invalid');
        } else {
            Auth::sendPasswordReset($email);
 // Zawsze neutralny komunikat nie ujawniamy czy email istnieje.
 // Always neutral message don't reveal whether the email exists.
            $message = tPlain('forgot_password.msg_sent');
        }
    }
}

$pageTitle = tPlain('forgot_password.page_title');
$authPage  = true;
require_once __DIR__ . '/../templates/header.php';
?>

<div class="login-container">
    <section class="login-card fade-in" aria-labelledby="forgot-heading">
        <h1 id="forgot-heading"> <?= t('forgot_password.heading') ?></h1>

        <?php if ($error): ?>
            <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif ?>

        <?php if ($message): ?>
            <div class="alert alert-success" role="alert"><?= htmlspecialchars($message) ?></div>
            <p style="text-align:center;margin-top:20px">
                <a href="<?= url('login') ?>" class="link-primary"><?= t('forgot_password.back_login') ?></a>
            </p>
        <?php else: ?>
        <form method="post" aria-label="<?= t('forgot_password.form_aria') ?>" novalidate>
            <?= CSRF::field() ?>

            <div class="form-group">
                <label class="form-label" for="email"><?= t('auth.label_email') ?></label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-input"
                    placeholder="<?= t('auth.placeholder_email') ?>"
                    required
                    autocomplete="email"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                >
            </div>

            <button type="submit" class="btn btn-primary btn-full">
                 <?= t('forgot_password.btn_submit') ?>
            </button>
        </form>

        <p class="login-footer">
            <a href="<?= url('login') ?>" class="link-primary"><?= t('forgot_password.back_login') ?></a>
        </p>
        <?php endif ?>
    </section>
</div>

<?php require_once __DIR__ . '/../templates/footer.php' ?>
<?php
} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('public/forgot_password.php', 'Unhandled exception', $e);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo tPlain('common.app_error');
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('public/forgot_password.php', $_codexGuardStart);
    }
}
