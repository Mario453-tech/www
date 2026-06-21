<?php
/**
 * Baner cookies — wstrzykiwany przez templates/header.php.
 * Zmienne: $__privacyBannerData (array z PrivacyBannerService::getBannerData()),
 *          $__privacyCsrf (string token CSRF),
 *          $__privacyConfig (array konfiguracji dla JS).
 */
?>
<div id="privacy-banner"
     class="privacy-banner"
     role="dialog"
     aria-label="<?= t('privacy.banner.aria_label') ?>"
     aria-modal="false"
     aria-live="polite"
     hidden>
    <div class="privacy-banner__inner">
        <span class="privacy-banner__icon" aria-hidden="true">🍪</span>
        <div class="privacy-banner__text">
            <p class="privacy-banner__heading"><?= htmlspecialchars($__privacyBannerData['heading']) ?></p>
            <p class="privacy-banner__desc">
                <?= htmlspecialchars($__privacyBannerData['description']) ?>
                <a href="<?= htmlspecialchars($__privacyBannerData['policy_url']) ?>" target="_blank" rel="noopener noreferrer">
                    Polityka cookies
                </a>
            </p>
        </div>
        <div class="privacy-banner__actions">
            <button type="button" id="privacy-btn-accept-all" class="privacy-btn privacy-btn--accept">
                <?= htmlspecialchars($__privacyBannerData['btn_accept_all']) ?>
            </button>
            <?php if ($__privacyBannerData['show_decline_button']): ?>
            <button type="button" id="privacy-btn-decline" class="privacy-btn privacy-btn--decline">
                <?= htmlspecialchars($__privacyBannerData['btn_necessary_only']) ?>
            </button>
            <?php endif ?>
            <button type="button" id="privacy-btn-settings" class="privacy-btn privacy-btn--settings">
                <?= htmlspecialchars($__privacyBannerData['btn_settings']) ?>
            </button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/settings_modal.php'; ?>

<script>
window.PRIVACY_CONFIG = <?= json_encode($__privacyConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
