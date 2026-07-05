<?php

require_once __DIR__ . '/../src/init.php';

GameLog::info('public/register.php', 'entry');
if (Auth::isLoggedIn()) {
    header('Location: /');
    exit();
}

$error = '';
$success = '';

if ($_POST) {
    if (!RateLimiter::check('login')) {
        $error = t('register.err_rate_limit');
    } elseif (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = t('common.csrf_error');
    } else {
        $email           = Validator::sanitize($_POST['email']);
        $password        = $_POST['password'];
        $passwordConfirm = $_POST['password_confirm'];
        $termsAccepted   = !empty($_POST['terms_accepted']);
        $newsletterOptin = !empty($_POST['newsletter_optin']);

        if (!$termsAccepted) {
            $error = t('register.err_terms_required');
        } elseif (!Validator::validateEmail($email)) {
            $error = t('register.err_invalid_email');
        } elseif (strlen($password) < 6) {
            $error = t('register.err_password_short');
        } elseif ($password !== $passwordConfirm) {
            $error = t('register.err_password_mismatch');
        } else {
            $result = Auth::registerPendingVerification($email, $password, $newsletterOptin);
            if (!$result['success']) {
                $error = (string)($result['message'] ?? t('register.err_generic'));
            } else {
                $playerId = (int)($result['player_id'] ?? 0);
                $username = (string)($result['username'] ?? '');

                if ($playerId > 0 && $username !== '') {
                    Auth::sendVerificationEmail($playerId, $email, $username);
                    GameLog::info('public/register.php', 'Player registered, verification email sent', [
                        'player_id' => $playerId,
                        'username'  => $username,
                        'email'     => $email,
                    ]);
                }

                $success = t('register.msg_verify_sent');
            }
        }
    }
}

$pageTitle = t('register.page_title');
$authPage  = true;
$extraJs   = ['/assets/js/auth.js'];
$viewData = [
    'error'           => $error,
    'success'         => $success,
    'emailVal'        => $_POST['email'] ?? '',
    'termsChecked'    => !empty($_POST['terms_accepted']),
    'newsletterChecked' => !empty($_POST['newsletter_optin']),
];
require_once __DIR__ . '/../templates/header.php';
require __DIR__ . '/../templates/views/public/register/main.php';
?><script>window.AUTH_LANG = <?= json_encode(['show_pass' => tPlain('auth.show_password'), 'hide_pass' => tPlain('auth.hide_password')], JSON_UNESCAPED_UNICODE) ?>;</script><?php
require_once __DIR__ . '/../templates/footer.php';
