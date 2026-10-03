<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/Privacy/PrivacyFeatureRegistry.php';

$db           = Database::getInstance()->getConnection();
$privSettings = new PrivacySettingsService($db);
$privConsent  = new PrivacyConsentService($db, $privSettings);
$privBanner   = new PrivacyBannerService($db, $privSettings);

$playerId     = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$anonToken    = PrivacyConsentService::getOrCreateAnonymousToken();
$consent      = $privConsent->getActiveConsent($playerId, $anonToken);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $msg = t('common.csrf_error');
    } elseif (isset($_POST['withdraw'])) {
        $privConsent->withdrawConsent($playerId, $anonToken);
        // Usuń cookie w przeglądarce
        setcookie('privacy_consent', '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax']);
        header('Location: /privacy-settings.php?withdrawn=1');
        exit;
    }
}

$pageTitle              = t('privacy.page.settings_title');
$extraCss               = ['/assets/css/privacy.css'];
$__privacyBannerData    = $privBanner->getBannerData();
$__privacyCsrf          = CSRF::generateToken();
$__privacyConfig        = [
    'bannerEnabled'     => true,
    'bannerVersion'     => (string)$privSettings->get('privacy.banner.version', '1.0'),
    'policyVersion'     => (string)$privSettings->get('privacy.cookies.policy_version', '1.0'),
    'forceReconsent'    => false,
    'reconsentOnPolicy' => false,
    'allCategories'     => ['necessary', 'preferences', 'analytics', 'marketing'],
    'csrfToken'         => $__privacyCsrf,
];

require_once __DIR__ . '/../templates/header.php';
?>
<main class="container">
    <div class="privacy-page">
        <h1><?= t('privacy.page.settings_heading') ?></h1>

        <?php if (isset($_GET['withdrawn'])): ?>
        <div class="alert alert-success privacy-settings__alert"><?= t('privacy.page.msg_withdrawn') ?></div>
        <?php endif ?>

        <p class="muted privacy-settings__intro"><?= t('privacy.page.settings_intro') ?></p>

        <?php if ($consent): ?>
        <div class="consent-card privacy-consent-card">
            <p class="privacy-consent-card__meta">
                <?= t('privacy.page.current_consent', [
                    'version' => htmlspecialchars($consent['consent_version']),
                    'date'    => htmlspecialchars(substr($consent['created_at'], 0, 10))
                ]) ?>
            </p>
            <?php
            $accepted = json_decode($consent['accepted_categories_json'], true) ?? [];
            $catNames = [
                'necessary'   => t('privacy.category.necessary'),
                'preferences' => t('privacy.category.preferences'),
                'analytics'   => t('privacy.category.analytics'),
                'marketing'   => t('privacy.category.marketing'),
            ];
            ?>
            <p class="privacy-consent-card__categories">
                <?= t('privacy.page.accepted_label') ?> <strong><?= htmlspecialchars(implode(', ', array_map(fn($c) => $catNames[$c] ?? $c, $accepted))) ?></strong>
            </p>
        </div>
        <?php endif ?>

        <div class="privacy-settings__actions">
            <button type="button" class="privacy-btn privacy-btn--decline" data-privacy-settings>
                <?= t('privacy.page.change_cookies_btn') ?>
            </button>
            <?php if ($consent): ?>
            <form method="post">
                <?= CSRF::field() ?>
                <button type="submit" name="withdraw" value="1"
                        class="privacy-btn privacy-btn--settings"
                        onclick="return confirm(<?= htmlspecialchars(json_encode(tPlain('privacy.page.confirm_withdraw'), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)">
                    <?= t('privacy.page.withdraw_consent_btn') ?>
                </button>
            </form>
            <?php endif ?>
        </div>

        <div class="privacy-settings__links">
            <a href="/cookies-policy.php" class="privacy-footer-link"><?= t('privacy.page.cookies_heading') ?></a>
            &nbsp;·&nbsp;
            <a href="/privacy-policy.php" class="privacy-footer-link"><?= t('privacy.page.privacy_heading') ?></a>
            &nbsp;·&nbsp;
            <a href="/" class="privacy-footer-link"><?= t('privacy.page.back_to_game') ?></a>
        </div>
    </div>
</main>

<?php
// Modal i skrypty — dołączane tylko gdy baner nie wstrzyknął ich wcześniej przez header.php
if (!defined('PRIVACY_MODAL_INCLUDED')) {
    define('PRIVACY_MODAL_INCLUDED', true);
    require __DIR__ . '/../templates/views/privacy/settings_modal.php';
}
echo '<script>window.PRIVACY_CONFIG = ' . json_encode($__privacyConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . ';</script>';
echo '<script src="' . asset('/assets/js/privacy_banner.js') . '" defer></script>';
?>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
