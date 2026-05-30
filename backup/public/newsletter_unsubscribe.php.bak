<?php
/**
 * newsletter_unsubscribe.php — one-click unsubscribe from newsletter.
 * Route: /newsletter-unsubscribe?token=xxx
 *
 * Token is the player's permanent newsletter_token from players table.
 * Unsubscribing does NOT affect login or account — only newsletter_subscribed flag.
 */
require_once __DIR__ . '/../src/init.php';

$_pageStart = GameLog::pageStart('public/newsletter_unsubscribe.php');

$token  = Validator::sanitize($_GET['token'] ?? '');
$status = 'invalid'; // 'invalid' | 'already' | 'success'

if (strlen($token) === 32 && ctype_alnum($token)) {
    try {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, email, newsletter_subscribed FROM players WHERE newsletter_token = ? LIMIT 1");
        $stmt->execute([$token]);
        $player = $stmt->fetch();

        if (!$player) {
            $status = 'invalid';
        } elseif (!(int)$player['newsletter_subscribed']) {
            $status = 'already';
        } else {
            $db->prepare("UPDATE players SET newsletter_subscribed = 0 WHERE newsletter_token = ?")
               ->execute([$token]);

            GameLog::info('newsletter_unsubscribe', 'Player unsubscribed from newsletter', [
                'player_id' => $player['id'],
            ]);

            $status = 'success';
        }
    } catch (Throwable $e) {
        GameLog::error('newsletter_unsubscribe', 'Unsubscribe failed', $e);
        $status = 'invalid';
    }
}

$pageTitle = t('newsletter_unsub.page_title');
$authPage  = true;
require_once __DIR__ . '/../templates/header.php';
?>

<div class="auth-card fade-in">
    <div class="auth-logo"> OilCorp</div>

    <?php if ($status === 'success'): ?>
        <h1 class="auth-heading"> <?= t('newsletter_unsub.heading_success') ?></h1>
        <p class="auth-sub"><?= t('newsletter_unsub.msg_success') ?></p>
        <p class="auth-sub" style="margin-top:8px;font-size:.82rem">
            <?= t('newsletter_unsub.msg_account_safe') ?>
        </p>

    <?php elseif ($status === 'already'): ?>
        <h1 class="auth-heading"> <?= t('newsletter_unsub.heading_already') ?></h1>
        <p class="auth-sub"><?= t('newsletter_unsub.msg_already') ?></p>

    <?php else: ?>
        <h1 class="auth-heading auth-heading--error"> <?= t('newsletter_unsub.heading_invalid') ?></h1>
        <p class="auth-sub"><?= t('newsletter_unsub.msg_invalid') ?></p>
    <?php endif ?>

    <a href="<?= url('login') ?>" class="btn btn-secondary btn-full" style="margin-top:24px">
         <?= t('newsletter_unsub.btn_login') ?>
    </a>
</div>

<?php
GameLog::pageEnd('public/newsletter_unsubscribe.php', $_pageStart);
require_once __DIR__ . '/../templates/footer.php';
