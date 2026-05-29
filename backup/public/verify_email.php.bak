<?php
/**
 * verify_email.php — email verification token handler.
 * Route: /verify-email?token=xxx
 */
require_once __DIR__ . '/../src/init.php';

$_pageStart = GameLog::pageStart('public/verify_email.php');

// Already logged in  redirect home
if (Auth::isLoggedIn()) {
    header('Location: /');
    exit();
}

$token  = Validator::sanitize($_GET['token'] ?? '');
$result = Auth::verifyEmail($token);

$pageTitle = t('verify_email.page_title');
$authPage  = true; // use auth layout (no nav, full-screen bg)

require_once __DIR__ . '/../templates/header.php';
?>

<div class="auth-card fade-in">
    <?php if ($result['success']): ?>
        <div class="auth-logo"> OilCorp</div>
        <h1 class="auth-heading"><?= t('verify_email.heading_success') ?></h1>
        <p class="auth-sub">
            <?= t('verify_email.msg_success') ?>
        </p>
        <a href="<?= url('login') ?>" class="btn btn-primary btn-full" style="margin-top:24px">
             <?= t('verify_email.btn_login') ?>
        </a>
    <?php else: ?>
        <div class="auth-logo"> OilCorp</div>
        <h1 class="auth-heading auth-heading--error"><?= t('verify_email.heading_error') ?></h1>
        <div class="alert alert-error" role="alert">
            <?= htmlspecialchars($result['message'] ?? t('common.app_error')) ?>
        </div>
        <p class="auth-sub">
            <?= t('verify_email.msg_resend_hint') ?>
        </p>
        <a href="<?= url('register') ?>" class="btn btn-secondary btn-full" style="margin-top:16px">
             <?= t('verify_email.btn_back_register') ?>
        </a>
    <?php endif ?>
</div>

<?php
GameLog::pageEnd('public/verify_email.php', $_pageStart);
require_once __DIR__ . '/../templates/footer.php';
