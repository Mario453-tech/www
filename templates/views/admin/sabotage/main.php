<?php
/** @var array<string,mixed> $viewData */
extract($viewData, EXTR_SKIP);

$tabs = ['options', 'effects', 'attempts', 'logs', 'help'];
$fmtMoney = static fn(float $v): string => number_format($v, 2, ',', ' ');
$optionName = static function (int $id, array $options): string {
    foreach ($options as $option) {
        if ((int)$option['id'] === $id) {
            return (string)$option['name'] . ' (' . (string)$option['code'] . ')';
        }
    }
    return '#' . $id;
};
?>

<h1><?= t('admin.sabotage.title') ?></h1>
<p class="panel-hint"><?= t('admin.sabotage.subtitle') ?></p>

<?php if ($msg): ?><p class="alert alert-success"><?= htmlspecialchars($msg) ?></p><?php endif ?>
<?php if ($err): ?><p class="alert alert-error"><?= htmlspecialchars($err) ?></p><?php endif ?>

<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.sabotage.module_title') ?></p>
    <p class="panel-hint"><?= t('admin.sabotage.module_hint') ?></p>
    <form method="post" action="/admin/sabotage.php?tab=<?= urlencode($activeTab) ?>" class="form-row form-row--gap">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="toggle_module">
        <label class="toggle-switch">
            <input type="checkbox" name="module_enabled" value="1" <?= !empty($moduleEnabled) ? 'checked' : '' ?>>
            <span class="toggle-slider"></span>
        </label>
        <span class="<?= !empty($moduleEnabled) ? 'c-good' : 'c-muted2' ?>">
            <?= !empty($moduleEnabled) ? t('admin.sabotage.module_status_enabled') : t('admin.sabotage.module_status_disabled') ?>
        </span>
        <button class="btn btn-primary" type="submit"><?= t('admin.sabotage.btn_save_module') ?></button>
    </form>
</section>

<nav class="admin-tabs">
    <?php foreach ($tabs as $tab): ?>
    <a href="/admin/sabotage.php?tab=<?= $tab ?>" class="admin-tab<?= $activeTab === $tab ? ' active' : '' ?>">
        <?= t('admin.sabotage.tab_' . $tab) ?>
    </a>
    <?php endforeach ?>
</nav>

<?php if ($activeTab === 'options'): ?>
<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.sabotage.options_title') ?></p>
    <div class="protection-admin-grid protection-admin-grid--options">
        <div class="protection-admin-row protection-admin-row--head">
            <span><?= t('admin.sabotage.col_code') ?></span>
            <span><?= t('admin.sabotage.col_name') ?></span>
            <span><?= t('admin.sabotage.col_target') ?></span>
            <span><?= t('admin.sabotage.col_chance') ?></span>
            <span><?= t('admin.sabotage.col_severity') ?></span>
            <span><?= t('admin.sabotage.col_status') ?></span>
            <span></span>
        </div>
        <?php foreach ($options as $option): ?>
        <div class="protection-admin-row">
            <span><code><?= htmlspecialchars((string)$option['code']) ?></code></span>
            <span><?= htmlspecialchars((string)$option['name']) ?></span>
            <span>
                <?= htmlspecialchars((string)$option['target_type']) ?>
                <br><small class="c-muted2"><?= htmlspecialchars((string)$option['context']) ?></small>
            </span>
            <span><?= number_format((float)$option['base_chance_pct'], 3, ',', ' ') ?>%</span>
            <span><?= t('admin.sabotage.severity_' . $option['severity']) ?></span>
            <span class="<?= (int)$option['is_active'] === 1 ? 'c-good' : 'c-muted2' ?>">
                <?= (int)$option['is_active'] === 1 ? t('admin.sabotage.status_on') : t('admin.sabotage.status_off') ?>
            </span>
            <span>
                <a href="/admin/sabotage.php?tab=options&edit=<?= (int)$option['id'] ?>" class="btn btn-xs btn-secondary">
                    <?= t('admin.sabotage.btn_edit') ?>
                </a>
            </span>
        </div>
        <?php endforeach ?>
    </div>
</section>

<section class="panel mb-8">
    <p class="panel-title"><?= $editOption ? t('admin.sabotage.option_edit') : t('admin.sabotage.option_add') ?></p>
    <form method="post" action="/admin/sabotage.php?tab=options">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="save_option">
        <input type="hidden" name="option_id" value="<?= (int)($editOption['id'] ?? 0) ?>">

        <div class="form-row form-row--gap">
            <div class="form-field">
                <label><?= t('admin.sabotage.field_code') ?></label>
                <input class="input-sm" name="code" required pattern="[a-z0-9_]+" value="<?= htmlspecialchars((string)($editOption['code'] ?? '')) ?>">
            </div>
            <div class="form-field">
                <label><?= t('admin.sabotage.field_name') ?></label>
                <input class="input-sm" name="name" required maxlength="128" value="<?= htmlspecialchars((string)($editOption['name'] ?? '')) ?>">
            </div>
            <div class="form-field">
                <label><?= t('admin.sabotage.field_sort') ?></label>
                <input class="input-sm input-num-70" type="number" name="sort_order" value="<?= (int)($editOption['sort_order'] ?? 0) ?>">
            </div>
        </div>

        <div class="form-row form-row--gap">
            <div class="form-field form-field--wide">
                <label><?= t('admin.sabotage.field_description') ?></label>
                <input class="input-sm" name="description" maxlength="512" value="<?= htmlspecialchars((string)($editOption['description'] ?? '')) ?>">
            </div>
        </div>

        <div class="form-row form-row--gap">
            <div class="form-field">
                <label><?= t('admin.sabotage.field_target_type') ?></label>
                <input class="input-sm" name="target_type" value="<?= htmlspecialchars((string)($editOption['target_type'] ?? 'road_transport')) ?>">
            </div>
            <div class="form-field">
                <label><?= t('admin.sabotage.field_context') ?></label>
                <input class="input-sm" name="context" value="<?= htmlspecialchars((string)($editOption['context'] ?? 'road_transport_sabotage')) ?>">
            </div>
            <div class="form-field">
                <label><?= t('admin.sabotage.field_chance') ?></label>
                <input class="input-sm input-num-110" type="number" step="0.001" min="0" max="100" name="base_chance_pct" value="<?= htmlspecialchars((string)($editOption['base_chance_pct'] ?? '0')) ?>">
            </div>
            <div class="form-field">
                <label><?= t('admin.sabotage.field_severity') ?></label>
                <select class="input-sm" name="severity">
                    <?php foreach (['low', 'medium', 'high', 'critical'] as $sev): ?>
                    <option value="<?= $sev ?>" <?= ($editOption['severity'] ?? 'medium') === $sev ? 'selected' : '' ?>>
                        <?= t('admin.sabotage.severity_' . $sev) ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>

        <div class="form-row form-row--gap">
            <div class="form-field">
                <label><?= t('admin.sabotage.field_cost_type') ?></label>
                <select class="input-sm" name="cost_type">
                    <?php foreach (['fixed', 'percent_reference', 'per_bbl'] as $ct): ?>
                    <option value="<?= $ct ?>" <?= ($editOption['cost_type'] ?? 'fixed') === $ct ? 'selected' : '' ?>>
                        <?= t('admin.sabotage.cost_type_' . $ct) ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="form-field">
                <label><?= t('admin.sabotage.field_cost_value') ?></label>
                <input class="input-sm input-num-110" type="number" step="0.01" min="0" name="cost_value" value="<?= htmlspecialchars((string)($editOption['cost_value'] ?? '0')) ?>">
            </div>
            <div class="form-field">
                <label><?= t('admin.sabotage.field_cost_currency') ?></label>
                <select class="input-sm" name="cost_currency">
                    <?php foreach (['cash', 'bank', 'black_market'] as $cc): ?>
                    <option value="<?= $cc ?>" <?= ($editOption['cost_currency'] ?? 'cash') === $cc ? 'selected' : '' ?>>
                        <?= t('admin.sabotage.currency_' . $cc) ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="form-field">
                <label><?= t('admin.sabotage.field_cooldown') ?></label>
                <input class="input-sm input-num-110" type="number" min="0" name="cooldown_minutes" value="<?= (int)($editOption['cooldown_minutes'] ?? 0) ?>">
            </div>
        </div>

        <div class="form-row form-row--gap">
            <div class="form-field">
                <label><?= t('admin.sabotage.field_min_region_risk') ?></label>
                <input class="input-sm input-num-70" type="number" min="0" max="10" name="min_region_risk" value="<?= (int)($editOption['min_region_risk'] ?? 0) ?>">
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="requires_black_market" value="1" <?= (int)($editOption['requires_black_market'] ?? 0) === 1 ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
            <span><?= t('admin.sabotage.field_requires_black_market') ?></span>
            <label class="toggle-switch">
                <input type="checkbox" name="is_active" value="1" <?= (int)($editOption['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
            <span><?= t('admin.sabotage.field_is_active') ?></span>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= t('admin.sabotage.btn_save_option') ?></button>
            <?php if ($editOption): ?>
            <a href="/admin/sabotage.php?tab=options" class="btn btn-secondary"><?= t('admin.sabotage.btn_cancel_edit') ?></a>
            <?php endif ?>
        </div>
    </form>
</section>

<?php elseif ($activeTab === 'effects'): ?>
<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.sabotage.effects_title') ?></p>
    <?php foreach ($options as $option): ?>
    <article class="protection-option mb-8">
        <div class="protection-option__head">
            <strong><?= htmlspecialchars((string)$option['name']) ?></strong>
            <code><?= htmlspecialchars((string)$option['code']) ?></code>
        </div>
        <?php foreach (($effectsByOption[(int)$option['id']] ?? []) as $effect): ?>
        <form method="post" action="/admin/sabotage.php?tab=effects" class="form-inline" data-confirm="<?= htmlspecialchars(t('admin.sabotage.confirm_delete_effect'), ENT_QUOTES, 'UTF-8') ?>">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="delete_effect">
            <input type="hidden" name="effect_id" value="<?= (int)$effect['id'] ?>">
            <span><code><?= htmlspecialchars((string)$effect['effect_key']) ?></code></span>
            <span><?= htmlspecialchars((string)$effect['effect_type']) ?></span>
            <span><?= htmlspecialchars((string)$effect['effect_value']) ?></span>
            <a href="/admin/sabotage.php?tab=effects&effect_edit=<?= (int)$effect['id'] ?>" class="btn btn-xs btn-secondary"><?= t('admin.sabotage.btn_edit') ?></a>
            <button type="submit" class="btn btn-xs btn-danger">
                <?= t('admin.sabotage.btn_delete') ?>
            </button>
        </form>
        <?php endforeach ?>
    </article>
    <?php endforeach ?>
</section>

<section class="panel mb-8">
    <p class="panel-title"><?= $editEffect ? t('admin.sabotage.effect_edit') : t('admin.sabotage.effect_add') ?></p>
    <form method="post" action="/admin/sabotage.php?tab=effects">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="save_effect">
        <input type="hidden" name="effect_id" value="<?= (int)($editEffect['id'] ?? 0) ?>">
        <div class="form-row form-row--gap">
            <div class="form-field">
                <label><?= t('admin.sabotage.field_option') ?></label>
                <select class="input-sm" name="option_id" required>
                    <?php foreach ($options as $option): ?>
                    <option value="<?= (int)$option['id'] ?>" <?= (int)($editEffect['sabotage_option_id'] ?? 0) === (int)$option['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string)$option['name']) ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="form-field">
                <label><?= t('admin.sabotage.field_effect_key') ?></label>
                <input class="input-sm" name="effect_key" list="sabotage-effect-keys" required value="<?= htmlspecialchars((string)($editEffect['effect_key'] ?? '')) ?>">
                <datalist id="sabotage-effect-keys">
                    <?php foreach ($knownEffectKeys as $key): ?><option value="<?= htmlspecialchars($key) ?>"><?php endforeach ?>
                </datalist>
            </div>
            <div class="form-field">
                <label><?= t('admin.sabotage.field_effect_type') ?></label>
                <select class="input-sm" name="effect_type">
                    <?php foreach (['delta', 'set', 'mult'] as $type): ?>
                    <option value="<?= $type ?>" <?= ($editEffect['effect_type'] ?? 'delta') === $type ? 'selected' : '' ?>><?= $type ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="form-field">
                <label><?= t('admin.sabotage.field_effect_value') ?></label>
                <input class="input-sm input-num-110" type="number" step="0.0001" name="effect_value" value="<?= htmlspecialchars((string)($editEffect['effect_value'] ?? '0')) ?>">
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= t('admin.sabotage.btn_save_effect') ?></button>
            <?php if ($editEffect): ?>
            <a href="/admin/sabotage.php?tab=effects" class="btn btn-secondary"><?= t('admin.sabotage.btn_cancel_edit') ?></a>
            <?php endif ?>
        </div>
    </form>
</section>

<?php elseif ($activeTab === 'attempts'): ?>
<section class="panel">
    <p class="panel-title"><?= t('admin.sabotage.attempts_title') ?></p>
    <div class="protection-admin-grid protection-admin-grid--active">
        <div class="protection-admin-row protection-admin-row--head">
            <span>#</span><span><?= t('admin.sabotage.col_target') ?></span><span><?= t('admin.sabotage.col_option') ?></span><span><?= t('admin.sabotage.col_status') ?></span><span><?= t('admin.sabotage.col_protection') ?></span><span><?= t('admin.sabotage.col_date') ?></span>
        </div>
        <?php foreach ($attempts as $attempt): ?>
        <div class="protection-admin-row">
            <span><?= (int)$attempt['id'] ?></span>
            <span><?= htmlspecialchars((string)($attempt['target_company'] ?: $attempt['target_username'] ?: $attempt['target_player_id'])) ?><br><small><?= htmlspecialchars((string)$attempt['target_type']) ?> #<?= (int)$attempt['target_id'] ?></small></span>
            <span><?= htmlspecialchars((string)$attempt['option_name']) ?><br><code><?= htmlspecialchars((string)$attempt['option_code']) ?></code></span>
            <span><?= t('admin.sabotage.status_' . $attempt['status']) ?></span>
            <span><?= (int)$attempt['protection_applied'] === 1 ? t('admin.sabotage.yes') : t('admin.sabotage.no') ?></span>
            <span><?= htmlspecialchars((string)$attempt['created_at']) ?></span>
        </div>
        <?php endforeach ?>
    </div>
</section>

<?php elseif ($activeTab === 'logs'): ?>
<section class="panel">
    <p class="panel-title"><?= t('admin.sabotage.logs_title') ?></p>
    <div class="protection-admin-grid protection-admin-grid--history">
        <div class="protection-admin-row protection-admin-row--head">
            <span>#</span><span><?= t('admin.sabotage.col_target') ?></span><span><?= t('admin.sabotage.col_event') ?></span><span><?= t('admin.sabotage.col_message') ?></span><span><?= t('admin.sabotage.col_date') ?></span>
        </div>
        <?php foreach ($logs as $log): ?>
        <div class="protection-admin-row">
            <span><?= (int)$log['id'] ?></span>
            <span><?= htmlspecialchars((string)($log['target_company'] ?: $log['target_username'] ?: $log['target_player_id'])) ?><br><small><?= htmlspecialchars((string)$log['target_type']) ?> #<?= (int)$log['target_id'] ?></small></span>
            <span><code><?= htmlspecialchars((string)$log['event_key']) ?></code></span>
            <span><?= htmlspecialchars((string)$log['message']) ?></span>
            <span><?= htmlspecialchars((string)$log['created_at']) ?></span>
        </div>
        <?php endforeach ?>
    </div>
</section>

<?php else: ?>
<section class="panel">
    <p class="panel-title"><?= t('admin.sabotage.help_title') ?></p>
    <p class="panel-hint"><?= t('admin.sabotage.help_body') ?></p>
    <ul>
        <li><?= t('admin.sabotage.help_p1') ?></li>
        <li><?= t('admin.sabotage.help_no_pvp') ?></li>
        <li><?= t('admin.sabotage.help_protection') ?></li>
        <li><?= t('admin.sabotage.help_future_fields') ?></li>
    </ul>
</section>
<?php endif ?>
