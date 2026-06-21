<?php /** Zakładka: Ustawienia banera */ ?>

<div class="privacy-section">
    <h2><?= t('privacy.banner_settings.tab_heading') ?></h2>

    <form method="post" action="?tab=banner_settings">
        <?= CSRF::field() ?>
        <input type="hidden" name="tab" value="banner_settings">
        <input type="hidden" name="action" value="save_banner_settings">

        <?php
        $s = $all_settings;
        $v = fn(string $key, mixed $default = '') => $s[$key] ?? $default;
        $checked = fn(string $key) => $v($key, false) ? 'checked' : '';
        ?>

        <div class="settings-section">
            <h3><?= t('privacy.banner_settings.section_general') ?></h3>
            <div class="form-grid">
                <label class="checkbox-label">
                    <input type="checkbox" name="privacy_banner_enabled" value="1" <?= $checked('privacy.banner.enabled') ?>>
                    <?= t('privacy.banner_settings.label_enabled') ?>
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="privacy_banner_show_decline_button" value="1" <?= $checked('privacy.banner.show_decline_button') ?>>
                    <?= t('privacy.banner_settings.label_decline') ?>
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="privacy_banner_force_reconsent" value="1" <?= $checked('privacy.banner.force_reconsent') ?>>
                    <?= t('privacy.banner_settings.label_force') ?>
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="privacy_cookies_reconsent_after_policy_change" value="1" <?= $checked('privacy.cookies.reconsent_after_policy_change') ?>>
                    <?= t('privacy.banner_settings.label_reconsent') ?>
                </label>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label><?= t('privacy.banner_settings.label_version') ?></label>
                    <input type="text" name="privacy_banner_version" maxlength="20"
                           value="<?= htmlspecialchars((string)$v('privacy.banner.version', '1.0')) ?>">
                </div>
                <div class="form-group">
                    <label><?= t('privacy.banner_settings.label_policy_ver') ?></label>
                    <input type="text" name="privacy_cookies_policy_version" maxlength="20"
                           value="<?= htmlspecialchars((string)$v('privacy.cookies.policy_version', '1.0')) ?>">
                </div>
            </div>
        </div>

        <div class="settings-section">
            <h3><?= t('privacy.banner_settings.section_text') ?></h3>
            <div class="form-group">
                <label><?= t('privacy.banner_settings.label_heading') ?></label>
                <input type="text" name="privacy_banner_heading" maxlength="300"
                       value="<?= htmlspecialchars((string)$v('privacy.banner.heading')) ?>">
            </div>
            <div class="form-group">
                <label><?= t('privacy.banner_settings.label_description') ?></label>
                <textarea name="privacy_banner_description" rows="3"><?= htmlspecialchars((string)$v('privacy.banner.description')) ?></textarea>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label><?= t('privacy.banner_settings.label_btn_accept') ?></label>
                    <input type="text" name="privacy_banner_btn_accept_all" maxlength="100"
                           value="<?= htmlspecialchars((string)$v('privacy.banner.btn_accept_all')) ?>">
                </div>
                <div class="form-group">
                    <label><?= t('privacy.banner_settings.label_btn_decline') ?></label>
                    <input type="text" name="privacy_banner_btn_necessary_only" maxlength="100"
                           value="<?= htmlspecialchars((string)$v('privacy.banner.btn_necessary_only')) ?>">
                </div>
                <div class="form-group">
                    <label><?= t('privacy.banner_settings.label_btn_settings') ?></label>
                    <input type="text" name="privacy_banner_btn_settings" maxlength="100"
                           value="<?= htmlspecialchars((string)$v('privacy.banner.btn_settings')) ?>">
                </div>
            </div>
        </div>

        <div class="settings-section">
            <h3><?= t('privacy.banner_settings.section_links') ?></h3>
            <div class="form-grid">
                <div class="form-group">
                    <label><?= t('privacy.banner_settings.label_policy_url') ?></label>
                    <input type="text" name="privacy_banner_policy_url" maxlength="500"
                           value="<?= htmlspecialchars((string)$v('privacy.banner.policy_url')) ?>">
                </div>
                <div class="form-group">
                    <label><?= t('privacy.banner_settings.label_privacy_url') ?></label>
                    <input type="text" name="privacy_banner_privacy_url" maxlength="500"
                           value="<?= htmlspecialchars((string)$v('privacy.banner.privacy_url')) ?>">
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= t('privacy.banner_settings.btn_save') ?></button>
        </div>
    </form>
</div>
