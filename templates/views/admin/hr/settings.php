<section class="hr-section">
    <h2><?= t('admin.hr.section_settings') ?></h2>
    <p class="muted"><?= t('admin.hr.settings_desc') ?></p>
    <?php foreach ($settingsGroups as $group => $settingGroup): ?>
    <details class="hr-settings-group" <?= $group === 'morale' ? 'open' : '' ?>>
        <summary><?= t('admin.hr.config_group.' . $group) ?></summary>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
            <input type="hidden" name="action" value="save_settings">
            <input type="hidden" name="return_tab" value="settings">
            <input type="hidden" name="config_group" value="<?= $esc($group) ?>">
            <div class="hr-config-grid">
                <?php foreach ($settingGroup['definitions'] as $key => $definition): ?>
                <label>
                    <span><?= t((string)$definition['label_key']) ?></span>
                    <?php if ($definition['type'] === 'bool'): ?>
                    <input type="hidden" name="config[<?= $esc($key) ?>]" value="0">
                    <span class="hr-switch">
                        <input type="checkbox" name="config[<?= $esc($key) ?>]" value="1" <?= !empty($settingGroup['values'][$key]) ? 'checked' : '' ?>>
                        <span><?= t('admin.hr.field_enabled') ?></span>
                    </span>
                    <?php else: ?>
                    <input type="number" name="config[<?= $esc($key) ?>]"
                           value="<?= $esc($settingGroup['values'][$key] ?? $definition['default']) ?>"
                           min="<?= $esc($definition['min']) ?>" max="<?= $esc($definition['max']) ?>"
                           step="<?= $esc($definition['step']) ?>" required>
                    <?php endif ?>
                    <small><?= t((string)$definition['description_key']) ?></small>
                    <small><?= t('admin.hr.recommended_value', ['value' => $definition['default']]) ?></small>
                </label>
                <?php endforeach ?>
            </div>
            <button type="submit" class="btn btn-primary"><?= t('common.save') ?></button>
        </form>
    </details>
    <?php endforeach ?>
</section>
