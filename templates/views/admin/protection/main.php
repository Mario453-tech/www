<?php
/** @var array<int,array<string,mixed>> $options */
/** @var array<int,array<int,array<string,mixed>>> $effectsByOption */
/** @var array<int,array<string,mixed>> $activeProtections */
/** @var array<int,array<string,mixed>> $historyLogs */
/** @var string $activeTab */
/** @var array<string,mixed>|null $editOption */
/** @var array<string,mixed>|null $editEffect */
/** @var array<int,string> $knownEffectKeys */
/** @var string $msg */
/** @var string $err */
/** @var array<string, string> $effectKeyModuleMap */
extract($viewData, EXTR_SKIP);

$tabs = ['options', 'effects', 'active', 'history', 'help'];
$fmtPln = static fn(float $v): string => number_format($v, 2, ',', ' ');

// Formatowanie wartosci efektu z kontekstem typu / Format effect value with type context
$fmtEffVal = static function (string $type, string $rawVal): string {
    $v = (float)$rawVal;
    if ($type === 'mult') {
        $pct = (int)round(abs(1.0 - $v) * 100);
        $sign = $v < 1.0 ? "\xe2\x88\x92" : '+';
        return number_format($v, 2, ',', "\xc2\xa0") . "\xc2\xa0(" . $sign . $pct . '%)';
    }
    $sign = $v >= 0 ? '+' : "\xe2\x88\x92";
    return $sign . number_format(abs($v), 2, ',', "\xc2\xa0");
};

// Przyjazna nazwa klucza efektu lub oryginal / Friendly effect key name or original
$effKeyLabel = static function (string $key): string {
    $langKey = 'admin.protection.ek.' . $key;
    $label = tPlain($langKey);
    return $label !== $langKey ? $label : $key;
};

// Przyjazna nazwa typu celu lub oryginal / Friendly target type name or original
$ttLabel = static function (string $tt): string {
    $langKey = 'admin.protection.tt.' . $tt;
    $label = tPlain($langKey);
    return $label !== $langKey ? $label : $tt;
};

// Przyjazna nazwa kontekstu lub oryginal / Friendly context name or original
$ctxLabel = static function (string $ctx): string {
    $langKey = 'admin.protection.ctx.' . $ctx;
    $label = tPlain($langKey);
    return $label !== $langKey ? $label : $ctx;
};
?>

<h1><?= t('admin.protection.title') ?></h1>
<p class="panel-hint"><?= t('admin.protection.subtitle') ?></p>

<?php if ($msg): ?><p class="alert alert-success"><?= htmlspecialchars($msg) ?></p><?php endif ?>
<?php if ($err): ?><p class="alert alert-error"><?= htmlspecialchars($err) ?></p><?php endif ?>

<nav class="admin-tabs">
    <?php foreach ($tabs as $tab): ?>
    <a href="/admin/protection.php?tab=<?= $tab ?>"
       class="admin-tab<?= $activeTab === $tab ? ' active' : '' ?>">
        <?= t('admin.protection.tab_' . $tab) ?>
    </a>
    <?php endforeach ?>
</nav>

<?php if ($activeTab === 'options'): ?>
<!-- == OPCJE OCHRONY / PROTECTION OPTIONS == -->
<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.protection.options_list_title') ?></p>
    <?php if (empty($options)): ?>
    <p class="panel-hint"><?= t('admin.protection.options_empty') ?></p>
    <?php else: ?>
    <div class="protection-admin-grid protection-admin-grid--options panel">
        <div class="protection-admin-row protection-admin-row--head">
            <span><?= t('admin.protection.col_code') ?></span>
            <span><?= t('admin.protection.col_name') ?></span>
            <span><?= t('admin.protection.col_target') ?></span>
            <span><?= t('admin.protection.col_cost') ?></span>
            <span><?= t('admin.protection.col_duration') ?></span>
            <span><?= t('admin.protection.col_requirements') ?></span>
            <span><?= t('admin.protection.col_status') ?></span>
            <span></span>
        </div>
        <?php foreach ($options as $opt): ?>
        <div class="protection-admin-row">
            <span><code><?= htmlspecialchars((string)$opt['code']) ?></code></span>
            <span><?= htmlspecialchars((string)$opt['name']) ?></span>
            <span>
                <?= htmlspecialchars($ttLabel((string)$opt['target_type'])) ?>
                <br><small class="c-muted2"><?= htmlspecialchars($ctxLabel((string)$opt['context'])) ?></small>
            </span>
            <span><?= $fmtPln((float)$opt['cost_value']) ?><br><small><?= t('admin.protection.cost_type_' . $opt['cost_type']) ?></small></span>
            <span><?= (int)$opt['duration_minutes'] ?> min</span>
            <span>
                <?php if ((int)$opt['min_company_credibility'] > 0): ?>
                <small><?= t('admin.protection.req_credibility', ['min' => (int)$opt['min_company_credibility']]) ?></small><br>
                <?php endif ?>
                <?php if ((int)$opt['min_legal_level'] > 0): ?>
                <small><?= t('admin.protection.req_legal', ['min' => (int)$opt['min_legal_level']]) ?></small>
                <?php endif ?>
            </span>
            <span class="<?= (int)$opt['is_active'] === 1 ? 'c-good' : 'c-muted2' ?>">
                <?= (int)$opt['is_active'] === 1 ? t('admin.protection.status_on') : t('admin.protection.status_off') ?>
            </span>
            <span>
                <a href="/admin/protection.php?tab=options&edit=<?= (int)$opt['id'] ?>" class="btn btn-xs btn-secondary">
                    <?= t('admin.protection.btn_edit') ?>
                </a>
            </span>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>

<section class="panel mb-8">
    <p class="panel-title">
        <?= $editOption !== null
            ? t('admin.protection.option_form_edit', ['name' => htmlspecialchars((string)$editOption['name'])])
            : t('admin.protection.option_form_add') ?>
    </p>
    <form method="post" action="/admin/protection.php?tab=options">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="save_option">
        <input type="hidden" name="option_id" value="<?= (int)($editOption['id'] ?? 0) ?>">

        <div class="form-row form-row--gap">
            <div class="form-field">
                <label><?= t('admin.protection.field_code') ?></label>
                <input type="text" name="code" required pattern="[a-z0-9_]+" maxlength="64"
                       value="<?= htmlspecialchars((string)($editOption['code'] ?? '')) ?>" class="input-sm">
            </div>
            <div class="form-field">
                <label><?= t('admin.protection.field_name') ?></label>
                <input type="text" name="name" required maxlength="128"
                       value="<?= htmlspecialchars((string)($editOption['name'] ?? '')) ?>" class="input-sm">
            </div>
            <div class="form-field">
                <label><?= t('admin.protection.field_sort') ?></label>
                <input type="number" name="sort_order" step="1"
                       value="<?= (int)($editOption['sort_order'] ?? 0) ?>" class="input-sm input-num-70">
            </div>
        </div>

        <div class="form-row form-row--gap">
            <div class="form-field form-field--wide">
                <label><?= t('admin.protection.field_description') ?></label>
                <input type="text" name="description" maxlength="512"
                       value="<?= htmlspecialchars((string)($editOption['description'] ?? '')) ?>" class="input-sm">
            </div>
        </div>

        <div class="form-row form-row--gap">
            <div class="form-field">
                <label><?= t('admin.protection.field_target_type') ?></label>
                <input type="text" name="target_type" maxlength="32"
                       value="<?= htmlspecialchars((string)($editOption['target_type'] ?? 'road_transport')) ?>" class="input-sm">
            </div>
            <div class="form-field">
                <label><?= t('admin.protection.field_context') ?></label>
                <input type="text" name="context" maxlength="64"
                       value="<?= htmlspecialchars((string)($editOption['context'] ?? 'road_transport_guard')) ?>" class="input-sm">
            </div>
        </div>

        <div class="form-row form-row--gap">
            <div class="form-field">
                <label><?= t('admin.protection.field_cost_type') ?></label>
                <select name="cost_type" class="input-sm">
                    <?php foreach (['fixed', 'percent_reference', 'per_hour', 'per_bbl'] as $costType): ?>
                    <option value="<?= $costType ?>" <?= ($editOption['cost_type'] ?? 'fixed') === $costType ? 'selected' : '' ?>>
                        <?= t('admin.protection.cost_type_' . $costType) ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="form-field">
                <label><?= t('admin.protection.field_cost_value') ?></label>
                <input type="number" name="cost_value" min="0" step="0.01"
                       value="<?= htmlspecialchars((string)($editOption['cost_value'] ?? '0')) ?>" class="input-sm input-num-110">
            </div>
            <div class="form-field">
                <label><?= t('admin.protection.field_cost_currency') ?></label>
                <input type="hidden" name="cost_currency" value="cash">
                <input type="text" class="input-sm" value="<?= htmlspecialchars(t('admin.protection.currency_cash')) ?>" disabled>
                <small class="panel-hint"><?= t('admin.protection.cash_only_note') ?></small>
            </div>
            <div class="form-field">
                <label><?= t('admin.protection.field_duration') ?></label>
                <input type="number" name="duration_minutes" min="1" step="1"
                       value="<?= (int)($editOption['duration_minutes'] ?? 60) ?>" class="input-sm input-num-110">
            </div>
        </div>

        <div class="form-row form-row--gap">
            <div class="form-field">
                <label><?= t('admin.protection.field_min_credibility') ?></label>
                <input type="number" name="min_company_credibility" min="0" max="100" step="1"
                       value="<?= (int)($editOption['min_company_credibility'] ?? 0) ?>" class="input-sm input-num-70">
            </div>
            <div class="form-field">
                <label><?= t('admin.protection.field_min_legal') ?></label>
                <input type="number" name="min_legal_level" min="0" max="10" step="1"
                       value="<?= (int)($editOption['min_legal_level'] ?? 0) ?>" class="input-sm input-num-70">
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="is_active" value="1"
                       <?= (int)($editOption['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
            <span><?= t('admin.protection.field_is_active') ?></span>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= t('admin.protection.btn_save_option') ?></button>
            <?php if ($editOption !== null): ?>
            <a href="/admin/protection.php?tab=options" class="btn btn-secondary"><?= t('admin.protection.btn_cancel_edit') ?></a>
            <?php endif ?>
        </div>
    </form>
</section>

<?php elseif ($activeTab === 'effects'): ?>
<!-- == EFEKTY OCHRONY / PROTECTION EFFECTS == -->
<?php foreach ($options as $opt): ?>
<section class="panel mb-8">
    <p class="panel-title">
        <?= htmlspecialchars((string)$opt['name']) ?>
        <code><?= htmlspecialchars((string)$opt['code']) ?></code>
    </p>
    <?php $optEffects = $effectsByOption[(int)$opt['id']] ?? []; ?>
    <?php if ($optEffects === []): ?>
    <p class="panel-hint"><?= t('admin.protection.effects_empty') ?></p>
    <?php else: ?>
    <div class="protection-admin-grid protection-admin-grid--effects">
        <?php foreach ($optEffects as $eff): ?>
        <div class="protection-admin-row">
            <span>
                <?= htmlspecialchars($effKeyLabel((string)$eff['effect_key'])) ?>
                <br><small class="c-muted2"><code><?= htmlspecialchars((string)$eff['effect_key']) ?></code></small>
            </span>
            <span>
                <?= t('admin.protection.et.' . $eff['effect_type']) ?>
            </span>
            <span class="c-accent">
                <?= htmlspecialchars($fmtEffVal((string)$eff['effect_type'], (string)$eff['effect_value'])) ?>
            </span>
            <span>
                <a href="/admin/protection.php?tab=effects&effect_edit=<?= (int)$eff['id'] ?>"
                   class="btn btn-xs btn-secondary">
                    <?= t('admin.protection.btn_edit') ?>
                </a>
                <form method="post" action="/admin/protection.php?tab=effects" class="protection-inline-form"
                      data-confirm="<?= htmlspecialchars(tPlain('admin.protection.confirm_delete_effect')) ?>">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="delete_effect">
                    <input type="hidden" name="effect_id" value="<?= (int)$eff['id'] ?>">
                    <button type="submit" class="btn btn-xs btn-danger"><?= t('admin.protection.btn_delete') ?></button>
                </form>
            </span>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>

    <?php
        $isEditingEffect = $editEffect !== null
            && (int)($editEffect['protection_option_id'] ?? 0) === (int)$opt['id'];
    ?>
    <form method="post" action="/admin/protection.php?tab=effects" class="form-row form-row--gap protection-effect-form">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="save_effect">
        <input type="hidden" name="option_id" value="<?= (int)$opt['id'] ?>">
        <input type="hidden" name="effect_id" value="<?= $isEditingEffect ? (int)$editEffect['id'] : 0 ?>">
        <div class="form-field">
            <label><?= t('admin.protection.field_effect_key') ?></label>
            <input type="text" name="effect_key" required pattern="[a-z0-9_]+" maxlength="64"
                   list="protection-effect-keys" class="input-sm"
                   value="<?= $isEditingEffect ? htmlspecialchars((string)$editEffect['effect_key']) : '' ?>">
        </div>
        <div class="form-field">
            <label><?= t('admin.protection.field_effect_type') ?></label>
            <select name="effect_type" class="input-sm">
                <option value="mult" <?= $isEditingEffect && (string)$editEffect['effect_type'] === 'mult' ? 'selected' : '' ?>>
                    mult - mnoznik (0.05-1.0)
                </option>
                <option value="delta" <?= $isEditingEffect && (string)$editEffect['effect_type'] === 'delta' ? 'selected' : '' ?>>
                    delta - zmiana +/- (max +/-0.99)
                </option>
            </select>
        </div>
        <div class="form-field">
            <label><?= t('admin.protection.field_effect_value') ?></label>
            <input type="number" name="effect_value" required step="0.01"
                   value="<?= htmlspecialchars((string)($isEditingEffect ? $editEffect['effect_value'] : '1.00')) ?>" class="input-sm input-num-110">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-sm btn-primary"><?= t('admin.protection.btn_save_effect') ?></button>
            <?php if ($isEditingEffect): ?>
            <a href="/admin/protection.php?tab=effects" class="btn btn-sm btn-secondary">
                <?= t('admin.protection.btn_cancel_edit') ?>
            </a>
            <?php endif ?>
        </div>
    </form>
</section>
<?php endforeach ?>
<datalist id="protection-effect-keys">
    <?php foreach ($knownEffectKeys as $effectKey): ?>
    <option value="<?= htmlspecialchars($effectKey) ?>"></option>
    <?php endforeach ?>
</datalist>

<?php elseif ($activeTab === 'active'): ?>
<!-- == AKTYWNE OCHRONY / ACTIVE PROTECTIONS == -->
<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.protection.active_title') ?></p>
    <?php if (empty($activeProtections)): ?>
    <p class="panel-hint"><?= t('admin.protection.active_empty') ?></p>
    <?php else: ?>
    <div class="protection-admin-grid protection-admin-grid--active">
        <div class="protection-admin-row protection-admin-row--head">
            <span><?= t('admin.protection.col_player') ?></span>
            <span><?= t('admin.protection.col_option') ?></span>
            <span><?= t('admin.protection.col_target') ?></span>
            <span><?= t('admin.protection.col_cost') ?></span>
            <span><?= t('admin.protection.col_period') ?></span>
            <span><?= t('admin.protection.col_status') ?></span>
            <span></span>
        </div>
        <?php foreach ($activeProtections as $ap): ?>
        <div class="protection-admin-row">
            <span><?= htmlspecialchars((string)($ap['company_name'] ?? $ap['username'] ?? ('#' . $ap['player_id']))) ?></span>
            <span><?= htmlspecialchars((string)$ap['option_name']) ?></span>
            <span><?= htmlspecialchars($ttLabel((string)$ap['target_type'])) ?> #<?= (int)$ap['target_id'] ?></span>
            <span><?= $fmtPln((float)$ap['cost']) ?> PLN</span>
            <span>
                <small><?= htmlspecialchars(substr((string)$ap['starts_at'], 0, 16)) ?></small><br>
                <small><?= htmlspecialchars(substr((string)$ap['ends_at'], 0, 16)) ?></small>
            </span>
            <span class="<?= $ap['status'] === 'active' ? 'c-good' : 'c-muted2' ?>">
                <?= t('admin.protection.ap_status_' . $ap['status']) ?>
            </span>
            <span>
                <?php if ($ap['status'] === 'active'): ?>
                <form method="post" action="/admin/protection.php?tab=active" class="protection-inline-form"
                      data-confirm="<?= htmlspecialchars(tPlain('admin.protection.confirm_cancel_active')) ?>">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="cancel_active">
                    <input type="hidden" name="active_id" value="<?= (int)$ap['id'] ?>">
                    <button type="submit" class="btn btn-xs btn-danger"><?= t('admin.protection.btn_cancel_active') ?></button>
                </form>
                <?php endif ?>
            </span>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>

<?php elseif ($activeTab === 'history'): ?>
<!-- == HISTORIA OCHRONY / PROTECTION HISTORY == -->
<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.protection.history_title') ?></p>
    <?php if (empty($historyLogs)): ?>
    <p class="panel-hint"><?= t('admin.protection.history_empty') ?></p>
    <?php else: ?>
    <div class="protection-admin-grid protection-admin-grid--history">
        <div class="protection-admin-row protection-admin-row--head">
            <span><?= t('admin.protection.col_date') ?></span>
            <span><?= t('admin.protection.col_player') ?></span>
            <span><?= t('admin.protection.col_option') ?></span>
            <span><?= t('admin.protection.col_target') ?></span>
            <span><?= t('admin.protection.col_event') ?></span>
            <span><?= t('admin.protection.col_amount') ?></span>
            <span><?= t('admin.protection.col_message') ?></span>
        </div>
        <?php foreach ($historyLogs as $logRow): ?>
        <div class="protection-admin-row">
            <span><small><?= htmlspecialchars(substr((string)$logRow['created_at'], 0, 16)) ?></small></span>
            <span><?= htmlspecialchars((string)($logRow['company_name'] ?? $logRow['username'] ?? ('#' . $logRow['player_id']))) ?></span>
            <span><?= htmlspecialchars((string)($logRow['option_name'] ?? ('#' . $logRow['protection_option_id']))) ?></span>
            <span><?= htmlspecialchars($ttLabel((string)$logRow['target_type'])) ?> #<?= (int)$logRow['target_id'] ?></span>
            <span><code><?= htmlspecialchars((string)$logRow['event_key']) ?></code></span>
            <span><?= $fmtPln((float)$logRow['amount']) ?></span>
            <span><small><?= htmlspecialchars((string)$logRow['message']) ?></small></span>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>

<?php elseif ($activeTab === 'help'): ?>
<?php elseif ($activeTab === 'help'): ?>
<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.protection.help_title') ?></p>

    <div class="protection-help-block">
        <h3><?= t('admin.protection.help_targets_title') ?></h3>
        <p><?= t('admin.protection.help_targets_intro') ?></p>
        <?php
        $helpModuleLabels = [
            'transport' => t('admin.protection.help_module_transport'),
            'hub'       => t('admin.protection.help_module_hub'),
            'pipeline'  => t('admin.protection.help_module_pipeline'),
        ]; ?>
        <div class="protection-admin-grid protection-admin-grid--help">
            <div class="protection-admin-row protection-admin-row--head">
                <span>target_type</span><span>context</span><span><?= t('admin.protection.help_meaning') ?></span>
            </div>
            <div class="protection-admin-row">
                <span><code>road_transport</code></span>
                <span><code>road_transport_guard</code></span>
                <span><?= t('admin.protection.help_row_transport') ?></span>
            </div>
            <div class="protection-admin-row">
                <span><code>hub</code></span>
                <span><code>hub_guard</code></span>
                <span><?= t('admin.protection.help_row_hub') ?></span>
            </div>
            <div class="protection-admin-row">
                <span><code>pipeline</code></span>
                <span><code>pipeline_guard</code></span>
                <span><?= t('admin.protection.help_row_pipeline') ?></span>
            </div>
        </div>
        <p class="panel-hint"><?= t('admin.protection.help_targets_hint') ?></p>
    </div>

    <div class="protection-help-block">
        <h3><?= t('admin.protection.help_effect_types_title') ?></h3>
        <div class="protection-admin-grid protection-admin-grid--help">
            <div class="protection-admin-row protection-admin-row--head">
                <span><?= t('admin.protection.help_type') ?></span><span><?= t('admin.protection.help_formula') ?></span><span><?= t('admin.protection.help_range') ?></span><span><?= t('admin.protection.help_example') ?></span>
            </div>
            <div class="protection-admin-row">
                <span><code>mult</code></span>
                <span><?= t('admin.protection.help_mult_formula') ?></span>
                <span><?= t('admin.protection.help_mult_range') ?></span>
                <span><?= t('admin.protection.help_mult_example') ?></span>
            </div>
            <div class="protection-admin-row">
                <span><code>delta</code></span>
                <span><?= t('admin.protection.help_delta_formula') ?></span>
                <span><?= t('admin.protection.help_delta_range') ?></span>
                <span><?= t('admin.protection.help_delta_example') ?></span>
            </div>
        </div>
        <p class="panel-hint"><?= t('admin.protection.help_effect_types_hint') ?></p>
    </div>

    <div class="protection-help-block">
        <h3><?= t('admin.protection.help_effect_keys_title') ?></h3>
        <div class="protection-admin-grid protection-admin-grid--help">
            <div class="protection-admin-row protection-admin-row--head">
                <span><?= t('admin.protection.help_technical_key') ?></span><span><?= t('admin.protection.help_applies_to') ?></span><span><?= t('admin.protection.help_friendly_name') ?></span>
            </div>
            <?php foreach ($knownEffectKeys as $effectKey): ?>
            <div class="protection-admin-row">
                <span><code><?= htmlspecialchars($effectKey) ?></code></span>
                <span><?= htmlspecialchars($helpModuleLabels[$effectKeyModuleMap[$effectKey] ?? ''] ?? ($effectKeyModuleMap[$effectKey] ?? '')) ?></span>
                <span><?= htmlspecialchars($effKeyLabel($effectKey)) ?></span>
            </div>
            <?php endforeach ?>
        </div>
    </div>

    <div class="protection-help-block">
        <h3><?= t('admin.protection.help_create_title') ?></h3>
        <ol>
            <li><?= t('admin.protection.help_create_step_1') ?></li>
            <li><?= t('admin.protection.help_create_step_2') ?></li>
            <li><?= t('admin.protection.help_create_step_3') ?></li>
            <li><?= t('admin.protection.help_create_step_4') ?></li>
            <li><?= t('admin.protection.help_create_step_5') ?></li>
            <li><?= t('admin.protection.help_create_step_6') ?></li>
            <li><?= t('admin.protection.help_create_step_7') ?></li>
            <li><?= t('admin.protection.help_create_step_8') ?></li>
        </ol>
    </div>

    <div class="protection-help-block">
        <h3><?= t('admin.protection.help_rules_title') ?></h3>
        <ul>
            <li><?= t('admin.protection.help_rules_1') ?></li>
            <li><?= t('admin.protection.help_rules_2') ?></li>
            <li><?= t('admin.protection.help_rules_3') ?></li>
            <li><?= t('admin.protection.help_rules_4') ?></li>
            <li><?= t('admin.protection.help_rules_5') ?></li>
        </ul>
        <p class="panel-hint"><?= t('admin.protection.help_rules_hint') ?></p>
    </div>

    <div class="protection-help-block">
        <h3><?= t('admin.protection.help_cancel_title') ?></h3>
        <p><?= t('admin.protection.help_cancel_body') ?></p>
    </div>
</section>
<?php endif ?>

<script src="<?= asset('/assets/js/admin_protection.js') ?>"></script>
