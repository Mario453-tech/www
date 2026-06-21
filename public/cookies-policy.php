<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/Privacy/PrivacyFeatureRegistry.php';

$db         = Database::getInstance()->getConnection();
$policySvc  = new PrivacyPolicyService($db);
$policy     = $policySvc->getActive('cookies');

$pageTitle  = t('privacy.page.cookies_title');
$extraCss   = ['/assets/css/privacy.css'];

require_once __DIR__ . '/../templates/header.php';
?>
<main class="container">
    <div class="privacy-page">
        <h1><?= t('privacy.page.cookies_heading') ?></h1>
        <?php if ($policy): ?>
            <p class="muted" style="margin-bottom:24px;font-size:12px;">
                <?= t('privacy.policy.col_version') ?>: <?= htmlspecialchars($policy['version']) ?>
                <?php if ($policy['published_at']): ?>
                    &nbsp;·&nbsp; <?= htmlspecialchars(substr($policy['published_at'], 0, 10)) ?>
                <?php endif ?>
            </p>
            <div class="policy-content">
                <?= $policy['content'] /* treść polityki może zawierać HTML zapisany przez admina */ ?>
            </div>
        <?php else: ?>
            <p class="muted"><?= t('privacy.page.no_policy') ?></p>
        <?php endif ?>

        <div style="margin-top:40px;">
            <a href="#" class="privacy-btn privacy-btn--decline" data-privacy-settings>
                <?= t('privacy.banner.settings_link') ?>
            </a>
            &nbsp;
            <a href="/" class="privacy-btn privacy-btn--settings"><?= t('privacy.page.back_to_game') ?></a>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
