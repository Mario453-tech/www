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
        <div class="alert alert-success" style="margin-bottom:20px;">Twoja zgoda została wycofana.</div>
        <?php endif ?>

        <p class="muted" style="margin-bottom:28px;"><?= t('privacy.page.settings_intro') ?></p>

        <?php if ($consent): ?>
        <div class="consent-card" style="background:rgba(200,168,75,.06);border:1px solid rgba(200,168,75,.2);border-radius:12px;padding:20px 24px;margin-bottom:28px;">
            <p style="font-size:13px;color:rgba(232,232,240,.7);margin:0 0 8px;">
                Twoja aktualna zgoda (wersja <?= htmlspecialchars($consent['consent_version']) ?>,
                zapisana <?= htmlspecialchars(substr($consent['created_at'], 0, 10)) ?>)
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
            <p style="margin:0;font-size:14px;color:#e8e8f0;">
                Zaakceptowane: <strong><?= htmlspecialchars(implode(', ', array_map(fn($c) => $catNames[$c] ?? $c, $accepted))) ?></strong>
            </p>
        </div>
        <?php endif ?>

        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:32px;">
            <button type="button" class="privacy-btn privacy-btn--decline" data-privacy-settings>
                Zmień ustawienia cookies
            </button>
            <?php if ($consent): ?>
            <form method="post">
                <?= CSRF::field() ?>
                <button type="submit" name="withdraw" value="1"
                        class="privacy-btn privacy-btn--settings"
                        onclick="return confirm('Czy na pewno chcesz wycofać zgodę? Cookies niezbędne nadal będą działać.')">
                    Wycofaj zgodę
                </button>
            </form>
            <?php endif ?>
        </div>

        <div style="margin-top:16px;">
            <a href="/cookies-policy.php" class="privacy-footer-link">Polityka cookies</a>
            &nbsp;·&nbsp;
            <a href="/privacy-policy.php" class="privacy-footer-link">Polityka prywatności</a>
            &nbsp;·&nbsp;
            <a href="/" class="privacy-footer-link"><?= t('privacy.page.back_to_game') ?></a>
        </div>
    </div>
</main>

<?php
// Modal musi być dostępny nawet gdy baner nie jest pokazywany
echo '<link rel="stylesheet" href="' . asset('/assets/css/privacy.css') . '">';
require __DIR__ . '/../templates/views/privacy/settings_modal.php';
echo '<script>window.PRIVACY_CONFIG = ' . json_encode($__privacyConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . ';</script>';
echo '<script src="' . asset('/assets/js/privacy_banner.js') . '" defer></script>';
?>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
