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
            $db = Database::getInstance()->getConnection();
            
            $checkEmail = $db->prepare("SELECT id FROM players WHERE email = :email");
            $checkEmail->execute([':email' => $email]);
            
            if ($checkEmail->fetch()) {
                $error = t('register.err_email_taken');
            } else {
                $db->beginTransaction();
                
                try {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

 // Generuj unikalny username z emaila (czesc przed @)
                    $baseUsername = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', explode('@', $email)[0]));
                    $baseUsername = substr($baseUsername ?: 'player', 0, 28);
                    $username = $baseUsername;
                    $suffix = 2;
                    while (true) {
                        $uCheck = $db->prepare("SELECT id FROM players WHERE username = ? LIMIT 1");
                        $uCheck->execute([$username]);
                        if (!$uCheck->fetch()) break;
                        $username = $baseUsername . $suffix++;
                    }
                    
                    $nlSubscribed   = $newsletterOptin ? 1 : 0;
                    $nlToken        = bin2hex(random_bytes(16)); // 32-char hex, permanent unsubscribe token

                    $insertPlayer = $db->prepare("
                        INSERT INTO players
                            (email, password_hash, username, cash, status, email_verified,
                             newsletter_subscribed, newsletter_token, created_at, last_tick_at)
                        VALUES
                            (:email, :password_hash, :username, 50000, 'active', 0,
                             :nl_sub, :nl_tok, NOW(), NOW())
                    ");

                    $insertPlayer->execute([
                        ':email'         => $email,
                        ':password_hash' => $passwordHash,
                        ':username'      => $username,
                        ':nl_sub'        => $nlSubscribed,
                        ':nl_tok'        => $nlToken,
                    ]);

                    $playerId = (int)$db->lastInsertId();

 // Magazyn startowy
                    $insertStorage = $db->prepare("
                        INSERT INTO storage (player_id, capacity, used, updated_at)
                        VALUES (:player_id, 200, 0, NOW())
                    ");
                    $insertStorage->execute([':player_id' => $playerId]);

 // Gotowka startowa bez odwiertu, gracz kupuje przez Mape
                    $db->prepare("
                        UPDATE players SET cash = 10000000 WHERE id = ?
                    ")->execute([$playerId]);

                    $db->commit();

 // Wyslij e-mail weryfikacyjny (poza transakcja)
                    Auth::sendVerificationEmail($playerId, $email, $username);

                    GameLog::info('public/register.php', 'Player registered, verification email sent', [
                        'player_id' => $playerId,
                        'username'  => $username,
                        'email'     => $email,
                    ]);

                    $success = t('register.msg_verify_sent');
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = t('register.err_generic') . $e->getMessage();
                }
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
