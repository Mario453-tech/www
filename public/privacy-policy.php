<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/Privacy/PrivacyFeatureRegistry.php';

$db        = Database::getInstance()->getConnection();
$policySvc = new PrivacyPolicyService($db);
$policy    = $policySvc->getActive('privacy');

$pageTitle = t('privacy.page.privacy_title');
$extraCss  = ['/assets/css/privacy.css'];

$locale = getLocale();
$templateFile = __DIR__ . '/../templates/views/public/pages/privacy_' . $locale . '.php';
if (!file_exists($templateFile)) {
    $templateFile = __DIR__ . '/../templates/views/public/pages/privacy_pl.php';
}

$policyContent = null;
if ($locale !== 'pl' && file_exists($templateFile)) {
    $policyContent = file_get_contents($templateFile);
} elseif ($policy && !empty($policy['content'])) {
    $policyContent = $policy['content'];
} elseif (file_exists($templateFile)) {
    $policyContent = file_get_contents($templateFile);
}

require_once __DIR__ . '/../templates/header.php';
?>
<main class="container">
    <div class="privacy-page">
        <h1><?= t('privacy.page.privacy_heading') ?></h1>
        <p class="privacy-page__meta">
            <?= t('page.last_updated', ['date' => ($policy && !empty($policy['published_at'])) ? date('d.m.Y', strtotime($policy['published_at'])) : '01.03.2026']) ?>
            <?php if ($policy && !empty($policy['version'])): ?>
                &nbsp;·&nbsp; <?= t('privacy.policy.col_version') ?>: <?= htmlspecialchars($policy['version']) ?>
            <?php endif; ?>
        </p>
        <?php if ($policyContent !== null): ?>
            <div class="policy-content">
                <?= $policyContent ?>
            </div>
        <?php else: ?>
            <p class="muted"><?= t('privacy.page.no_policy') ?></p>
        <?php endif ?>

        <div class="privacy-page__actions">
            <a href="/" class="privacy-btn privacy-btn--settings"><?= t('privacy.page.back_to_game') ?></a>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
