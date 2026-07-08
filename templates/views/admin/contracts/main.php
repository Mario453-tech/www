<?php
/**
 * Panel admina kontraktow dlugoterminowych - widok z zakladkami.
 * Long-term contracts admin panel - tabbed view.
 *
 * @var bool $moduleEnabled
 * @var array<int,array<string,mixed>> $options
 * @var array<int,array<int,array<string,mixed>>> $termsByOption
 * @var array<int,array<string,mixed>> $activeContracts
 * @var array<int,array<string,mixed>> $deliveries
 * @var array<int,array<string,mixed>> $logs
 * @var array<int,array<string,mixed>> $reputationRows
 * @var array<int,array<string,mixed>> $reputationLogs
 * @var string $reputationSearch
 * @var int $reputationPlayerId
 * @var string $activeTab
 * @var array<int,string> $tabs
 * @var array<string,mixed>|null $editOption
 * @var array<string,mixed>|null $editTerm
 * @var array<int,string> $priceModes
 * @var array<int,string> $severities
 * @var array<int,string> $termTypes
 * @var string $msg
 * @var string $err
 */

$fmtPln = static fn(float $v): string => number_format($v, 2, ',', "\xc2\xa0");
$fmtBbl = static fn(float $v): string => number_format($v, 0, ',', "\xc2\xa0");

$optionName = static function (int $optionId) use ($options): string {
    foreach ($options as $opt) {
        if ((int)$opt['id'] === $optionId) {
            return (string)$opt['name'];
        }
    }
    return '#' . $optionId;
};

$priceModeLabel = static function (string $mode): string {
    $k = 'admin.contracts.price_mode_' . $mode;
    $l = tPlain($k);
    return $l !== $k ? $l : $mode;
};
$severityLabel = static function (string $sev): string {
    $k = 'admin.contracts.severity_' . $sev;
    $l = tPlain($k);
    return $l !== $k ? $l : $sev;
};
$termTypeLabel = static function (string $type): string {
    $k = 'admin.contracts.term_type_' . $type;
    $l = tPlain($k);
    return $l !== $k ? $l : $type;
};
$depositStatusLabel = static function (string $status): string {
    $k = 'admin.contracts.deposit_status_' . $status;
    $l = tPlain($k);
    return $l !== $k ? $l : $status;
};
$reputationPlayerLabel = static function (array $row): string {
    $company = trim((string)($row['company_name'] ?? ''));
    $username = trim((string)($row['username'] ?? ''));
    if ($company !== '') {
        return $company;
    }
    if ($username !== '') {
        return $username;
    }
    return '#' . (int)($row['player_id'] ?? 0);
};
?>

<h1><?= t('admin.contracts.title') ?></h1>
<p class="panel-hint"><?= t('admin.contracts.subtitle') ?></p>

<?php if ($msg): ?><p class="alert alert-success"><?= htmlspecialchars($msg) ?></p><?php endif ?>
<?php if ($err): ?><p class="alert alert-error"><?= htmlspecialchars($err) ?></p><?php endif ?>

<!-- Przelacznik modulu / Module toggle -->
<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.contracts.module_title') ?></p>
    <form method="post" action="/admin/contracts.php?tab=<?= htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8') ?>"
          <?php if ($moduleEnabled): ?>data-confirm="<?= htmlspecialchars(tPlain('admin.contracts.confirm_module_off'), ENT_QUOTES, 'UTF-8') ?>" data-confirm-type="warning"<?php endif ?>>
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="toggle_module">
        <div class="module-toggle-wrap">
            <label class="module-toggle-label">
                <input type="checkbox" name="enabled" value="1" <?= $moduleEnabled ? 'checked' : '' ?>>
                <?= t('admin.contracts.module_label') ?>
            </label>
            <span class="<?= $moduleEnabled ? 'c-good' : 'c-muted2' ?>">
                <?= $moduleEnabled ? t('admin.contracts.status_on') : t('admin.contracts.status_off') ?>
            </span>
            <button type="submit" class="btn btn-sm btn-primary"><?= t('admin.contracts.btn_save_module') ?></button>
        </div>
        <p class="panel-hint"><?= t('admin.contracts.module_hint') ?></p>
    </form>
</section>

<nav class="admin-tabs">
    <?php foreach ($tabs as $tab): ?>
    <a href="/admin/contracts.php?tab=<?= $tab ?>"
       class="admin-tab<?= $activeTab === $tab ? ' active' : '' ?>">
        <?= t('admin.contracts.tab_' . $tab) ?>
    </a>
    <?php endforeach ?>
</nav>

<?php if ($activeTab === 'options'): ?>
<!-- == OPCJE KONTRAKTOW / CONTRACT OPTIONS == -->
<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.contracts.options_list_title') ?></p>
    <?php if (empty($options)): ?>
    <p class="panel-hint"><?= t('admin.contracts.options_empty') ?></p>
    <?php else: ?>
    <div class="contracts-admin-grid">
        <div class="contracts-admin-row contracts-admin-row--options contracts-admin-row--head">
            <span><?= t('admin.contracts.col_code') ?></span>
            <span><?= t('admin.contracts.col_name') ?></span>
            <span><?= t('admin.contracts.col_buyer') ?></span>
            <span><?= t('admin.contracts.col_price_mode') ?></span>
            <span><?= t('admin.contracts.col_severity') ?></span>
            <span><?= t('admin.contracts.col_active') ?></span>
            <span></span>
        </div>
        <?php foreach ($options as $opt): ?>
        <?php $isActive = (int)($opt['is_active'] ?? 0) === 1; ?>
        <div class="contracts-admin-row contracts-admin-row--options">
            <span><code><?= htmlspecialchars((string)$opt['code']) ?></code></span>
            <span><?= htmlspecialchars((string)$opt['name']) ?></span>
            <span><?= htmlspecialchars((string)$opt['buyer_name']) ?></span>
            <span><?= htmlspecialchars($priceModeLabel((string)$opt['price_mode'])) ?></span>
            <span><?= htmlspecialchars($severityLabel((string)$opt['severity'])) ?></span>
            <span class="<?= $isActive ? 'c-good' : 'c-muted2' ?>">
                <?= $isActive ? t('admin.contracts.status_on') : t('admin.contracts.status_off') ?>
            </span>
            <span class="contracts-admin-actions">
                <a href="/admin/contracts.php?tab=options&edit=<?= (int)$opt['id'] ?>" class="btn btn-xs btn-secondary"><?= t('admin.contracts.btn_edit') ?></a>
                <form method="post" action="/admin/contracts.php?tab=options" class="contracts-inline-form"
                      <?php if ($isActive): ?>data-confirm="<?= htmlspecialchars(tPlain('admin.contracts.confirm_disable_option', ['name' => (string)$opt['name']]), ENT_QUOTES, 'UTF-8') ?>" data-confirm-type="warning"<?php endif ?>>
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="toggle_option">
                    <input type="hidden" name="option_id" value="<?= (int)$opt['id'] ?>">
                    <input type="hidden" name="is_active" value="<?= $isActive ? '0' : '1' ?>">
                    <button type="submit" class="btn btn-xs <?= $isActive ? 'btn-danger' : 'btn-success' ?>">
                        <?= $isActive ? t('admin.contracts.btn_disable') : t('admin.contracts.btn_enable') ?>
                    </button>
                </form>
            </span>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>

<section class="panel mb-8">
    <p class="panel-title">
        <?= $editOption !== null
            ? t('admin.contracts.option_form_edit', ['name' => (string)$editOption['name']])
            : t('admin.contracts.option_form_add') ?>
    </p>
    <form method="post" action="/admin/contracts.php?tab=options">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="save_option">
        <input type="hidden" name="option_id" value="<?= (int)($editOption['id'] ?? 0) ?>">

        <div class="form-row form-row--gap">
            <div class="form-field">
                <label><?= t('admin.contracts.field_code') ?></label>
                <input type="text" name="code" required pattern="[a-z0-9_]+" maxlength="64"
                       value="<?= htmlspecialchars((string)($editOption['code'] ?? '')) ?>" class="input-sm">
            </div>
            <div class="form-field">
                <label><?= t('admin.contracts.field_name') ?></label>
                <input type="text" name="name" required maxlength="128"
                       value="<?= htmlspecialchars((string)($editOption['name'] ?? '')) ?>" class="input-sm">
            </div>
            <div class="form-field">
                <label><?= t('admin.contracts.field_sort') ?></label>
                <input type="number" name="sort_order" step="1"
                       value="<?= (int)($editOption['sort_order'] ?? 0) ?>" class="input-sm input-num-70">
            </div>
        </div>

        <div class="form-row form-row--gap">
            <div class="form-field form-field--wide">
                <label><?= t('admin.contracts.field_description') ?></label>
                <input type="text" name="description" maxlength="512"
                       value="<?= htmlspecialchars((string)($editOption['description'] ?? '')) ?>" class="input-sm">
            </div>
            <div class="form-field">
                <label><?= t('admin.contracts.field_buyer') ?></label>
                <input type="text" name="buyer_name" maxlength="128"
                       value="<?= htmlspecialchars((string)($editOption['buyer_name'] ?? '')) ?>" class="input-sm">
            </div>
        </div>

        <div class="form-row form-row--gap">
            <div class="form-field">
                <label><?= t('admin.contracts.field_price_mode') ?></label>
                <select name="price_mode" class="input-sm">
                    <?php foreach ($priceModes as $mode): ?>
                    <option value="<?= $mode ?>" <?= ($editOption['price_mode'] ?? 'market_plus_bonus') === $mode ? 'selected' : '' ?>>
                        <?= htmlspecialchars($priceModeLabel($mode)) ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="form-field">
                <label><?= t('admin.contracts.field_fixed_price') ?></label>
                <input type="number" name="fixed_price" min="0" step="0.01"
                       value="<?= isset($editOption['fixed_price']) ? htmlspecialchars((string)$editOption['fixed_price']) : '' ?>" class="input-sm input-num-70">
            </div>
            <div class="form-field">
                <label><?= t('admin.contracts.field_price_multiplier') ?></label>
                <input type="number" name="price_multiplier" min="0" step="0.0001"
                       value="<?= htmlspecialchars((string)($editOption['price_multiplier'] ?? '1.0')) ?>" class="input-sm input-num-70">
            </div>
            <div class="form-field">
                <label><?= t('admin.contracts.field_severity') ?></label>
                <select name="severity" class="input-sm">
                    <?php foreach ($severities as $sev): ?>
                    <option value="<?= $sev ?>" <?= ($editOption['severity'] ?? 'low') === $sev ? 'selected' : '' ?>>
                        <?= htmlspecialchars($severityLabel($sev)) ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>

        <div class="form-row form-row--gap">
            <div class="form-field">
                <label><?= t('admin.contracts.field_min_credibility') ?></label>
                <input type="number" name="min_credibility" min="0" max="100" step="1"
                       value="<?= (int)($editOption['min_credibility'] ?? 0) ?>" class="input-sm input-num-70">
            </div>
            <div class="form-field">
                <label><?= t('admin.contracts.field_requires_legal_level') ?></label>
                <input type="number" name="requires_legal_level" min="0" max="10" step="1"
                       value="<?= (int)($editOption['requires_legal_level'] ?? 0) ?>" class="input-sm input-num-70">
            </div>
            <div class="form-field">
                <label><?= t('admin.contracts.field_max_active') ?></label>
                <input type="number" name="max_active_per_player" min="0" step="1"
                       value="<?= (int)($editOption['max_active_per_player'] ?? 3) ?>" class="input-sm input-num-70">
            </div>
            <div class="form-field">
                <label class="module-toggle-label">
                    <input type="checkbox" name="is_active" value="1" <?= ($editOption === null || (int)($editOption['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
                    <?= t('admin.contracts.field_active') ?>
                </label>
            </div>
        </div>

        <button type="submit" class="btn btn-success"><?= t('admin.contracts.btn_save_option') ?></button>
        <?php if ($editOption !== null): ?>
        <a href="/admin/contracts.php?tab=options" class="btn btn-secondary"><?= t('admin.contracts.btn_cancel_edit') ?></a>
        <?php endif ?>
    </form>
</section>

<?php elseif ($activeTab === 'terms'): ?>
<!-- == WARUNKI KONTRAKTOW / CONTRACT TERMS == -->
<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.contracts.terms_title') ?></p>
    <?php if (empty($options)): ?>
    <p class="panel-hint"><?= t('admin.contracts.options_empty') ?></p>
    <?php else: ?>
        <?php foreach ($options as $opt): ?>
        <?php $optId = (int)$opt['id']; $terms = $termsByOption[$optId] ?? []; ?>
        <div class="contracts-terms-block">
            <p class="contracts-terms-block__title">
                <code><?= htmlspecialchars((string)$opt['code']) ?></code> &mdash; <?= htmlspecialchars((string)$opt['name']) ?>
            </p>
            <?php if (empty($terms)): ?>
            <p class="panel-hint"><?= t('admin.contracts.terms_empty') ?></p>
            <?php else: ?>
            <div class="contracts-admin-grid">
                <div class="contracts-admin-row contracts-admin-row--terms contracts-admin-row--head">
                    <span><?= t('admin.contracts.col_term_key') ?></span>
                    <span><?= t('admin.contracts.col_term_type') ?></span>
                    <span><?= t('admin.contracts.col_term_value') ?></span>
                    <span><?= t('admin.contracts.col_term_text') ?></span>
                    <span></span>
                </div>
                <?php foreach ($terms as $term): ?>
                <div class="contracts-admin-row contracts-admin-row--terms">
                    <span><code><?= htmlspecialchars((string)$term['term_key']) ?></code></span>
                    <span><?= htmlspecialchars($termTypeLabel((string)$term['term_type'])) ?></span>
                    <span><?= $term['term_value'] !== null ? htmlspecialchars((string)(float)$term['term_value']) : '&mdash;' ?></span>
                    <span><?= htmlspecialchars((string)($term['term_text'] ?? '')) ?></span>
                    <span class="contracts-admin-actions">
                        <a href="/admin/contracts.php?tab=terms&term_edit=<?= (int)$term['id'] ?>" class="btn btn-xs btn-secondary"><?= t('admin.contracts.btn_edit') ?></a>
                        <form method="post" action="/admin/contracts.php?tab=terms" class="contracts-inline-form"
                              data-confirm="<?= htmlspecialchars(tPlain('admin.contracts.confirm_delete_term', ['key' => (string)$term['term_key']]), ENT_QUOTES, 'UTF-8') ?>" data-confirm-type="warning">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="action" value="delete_term">
                            <input type="hidden" name="term_id" value="<?= (int)$term['id'] ?>">
                            <button type="submit" class="btn btn-xs btn-danger"><?= t('admin.contracts.btn_delete_term') ?></button>
                        </form>
                    </span>
                </div>
                <?php endforeach ?>
            </div>
            <?php endif ?>
        </div>
        <?php endforeach ?>
    <?php endif ?>
</section>

<section class="panel mb-8">
    <p class="panel-title">
        <?= $editTerm !== null
            ? t('admin.contracts.term_form_edit', ['key' => (string)$editTerm['term_key']])
            : t('admin.contracts.term_form_add') ?>
    </p>
    <form method="post" action="/admin/contracts.php?tab=terms">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="save_term">
        <input type="hidden" name="term_id" value="<?= (int)($editTerm['id'] ?? 0) ?>">

        <div class="form-row form-row--gap">
            <div class="form-field">
                <label><?= t('admin.contracts.field_option') ?></label>
                <select name="option_id" class="input-sm" required>
                    <?php foreach ($options as $opt): ?>
                    <option value="<?= (int)$opt['id'] ?>" <?= (int)($editTerm['contract_option_id'] ?? 0) === (int)$opt['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string)$opt['code']) ?> &mdash; <?= htmlspecialchars((string)$opt['name']) ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="form-field">
                <label><?= t('admin.contracts.field_term_key') ?></label>
                <input type="text" name="term_key" required pattern="[a-z0-9_]+" maxlength="64"
                       value="<?= htmlspecialchars((string)($editTerm['term_key'] ?? '')) ?>" class="input-sm">
            </div>
            <div class="form-field">
                <label><?= t('admin.contracts.field_term_type') ?></label>
                <select name="term_type" class="input-sm">
                    <?php foreach ($termTypes as $type): ?>
                    <option value="<?= $type ?>" <?= ($editTerm['term_type'] ?? 'number') === $type ? 'selected' : '' ?>>
                        <?= htmlspecialchars($termTypeLabel($type)) ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="form-field">
                <label><?= t('admin.contracts.field_term_value') ?></label>
                <input type="number" name="term_value" step="0.0001"
                       value="<?= isset($editTerm['term_value']) && $editTerm['term_value'] !== null ? htmlspecialchars((string)(float)$editTerm['term_value']) : '' ?>" class="input-sm input-num-70">
            </div>
            <div class="form-field">
                <label><?= t('admin.contracts.field_term_text') ?></label>
                <input type="text" name="term_text" maxlength="255"
                       value="<?= htmlspecialchars((string)($editTerm['term_text'] ?? '')) ?>" class="input-sm">
            </div>
        </div>

        <button type="submit" class="btn btn-success"><?= t('admin.contracts.btn_save_term') ?></button>
        <?php if ($editTerm !== null): ?>
        <a href="/admin/contracts.php?tab=terms" class="btn btn-secondary"><?= t('admin.contracts.btn_cancel_edit') ?></a>
        <?php endif ?>
    </form>
    <p class="panel-hint"><?= t('admin.contracts.terms_hint') ?></p>
</section>

<?php elseif ($activeTab === 'active'): ?>
<!-- == AKTYWNE KONTRAKTY / ACTIVE CONTRACTS == -->
<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.contracts.active_title') ?></p>
    <?php if (empty($activeContracts)): ?>
    <p class="panel-hint"><?= t('admin.contracts.active_empty') ?></p>
    <?php else: ?>
    <div class="contracts-admin-grid">
        <div class="contracts-admin-row contracts-admin-row--active contracts-admin-row--head">
            <span><?= t('admin.contracts.col_player') ?></span>
            <span><?= t('admin.contracts.col_contract') ?></span>
            <span><?= t('admin.contracts.col_progress') ?></span>
            <span><?= t('admin.contracts.col_deposit') ?></span>
            <span><?= t('admin.contracts.col_next_delivery') ?></span>
            <span><?= t('admin.contracts.col_ends') ?></span>
        </div>
        <?php foreach ($activeContracts as $c): ?>
        <div class="contracts-admin-row contracts-admin-row--active">
            <span><?= htmlspecialchars((string)($c['company_name'] ?? $c['username'] ?? ('#' . $c['player_id']))) ?></span>
            <span><?= htmlspecialchars((string)$c['contract_name']) ?></span>
            <span><?= $fmtBbl((float)$c['delivered_bbl']) ?>&nbsp;/&nbsp;<?= $fmtBbl((float)$c['total_bbl']) ?> bbl</span>
            <span><?= $fmtPln((float)($c['security_deposit'] ?? 0.0)) ?> <small><?= htmlspecialchars($depositStatusLabel((string)($c['security_deposit_status'] ?? 'none'))) ?></small></span>
            <span><small><?= htmlspecialchars(substr((string)$c['next_delivery_at'], 0, 16)) ?></small></span>
            <span><small><?= htmlspecialchars(substr((string)$c['ends_at'], 0, 16)) ?></small></span>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>

<?php elseif ($activeTab === 'deliveries'): ?>
<!-- == DOSTAWY / DELIVERIES == -->
<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.contracts.deliveries_title') ?></p>
    <?php if (empty($deliveries)): ?>
    <p class="panel-hint"><?= t('admin.contracts.deliveries_empty') ?></p>
    <?php else: ?>
    <div class="contracts-admin-grid">
        <div class="contracts-admin-row contracts-admin-row--deliveries contracts-admin-row--head">
            <span><?= t('admin.contracts.col_date') ?></span>
            <span><?= t('admin.contracts.col_player') ?></span>
            <span><?= t('admin.contracts.col_delivered') ?></span>
            <span><?= t('admin.contracts.col_missed') ?></span>
            <span><?= t('admin.contracts.col_price') ?></span>
            <span><?= t('admin.contracts.col_revenue') ?></span>
            <span><?= t('admin.contracts.col_penalty') ?></span>
            <span><?= t('admin.contracts.col_status') ?></span>
        </div>
        <?php foreach ($deliveries as $d): ?>
        <div class="contracts-admin-row contracts-admin-row--deliveries">
            <span><small><?= htmlspecialchars(substr((string)$d['created_at'], 0, 16)) ?></small></span>
            <span><?= htmlspecialchars((string)($d['company_name'] ?? $d['username'] ?? ('#' . $d['player_id']))) ?></span>
            <span><?= $fmtBbl((float)$d['delivered_bbl']) ?></span>
            <span><?= $fmtBbl((float)$d['missed_bbl']) ?></span>
            <span><?= $fmtPln((float)$d['price_per_bbl']) ?></span>
            <span><?= $fmtPln((float)$d['revenue']) ?></span>
            <span><?= $fmtPln((float)$d['penalty']) ?></span>
            <span><code><?= htmlspecialchars((string)$d['status']) ?></code></span>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>

<?php elseif ($activeTab === 'logs'): ?>
<!-- == LOGI / LOGS == -->
<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.contracts.logs_title') ?></p>
    <?php if (empty($logs)): ?>
    <p class="panel-hint"><?= t('admin.contracts.logs_empty') ?></p>
    <?php else: ?>
    <div class="contracts-admin-grid">
        <div class="contracts-admin-row contracts-admin-row--logs contracts-admin-row--head">
            <span><?= t('admin.contracts.col_date') ?></span>
            <span><?= t('admin.contracts.col_player') ?></span>
            <span><?= t('admin.contracts.col_event') ?></span>
            <span><?= t('admin.contracts.col_message') ?></span>
        </div>
        <?php foreach ($logs as $log): ?>
        <div class="contracts-admin-row contracts-admin-row--logs">
            <span><small><?= htmlspecialchars(substr((string)$log['created_at'], 0, 16)) ?></small></span>
            <span><?= htmlspecialchars((string)($log['company_name'] ?? $log['username'] ?? ('#' . $log['player_id']))) ?></span>
            <span><code><?= htmlspecialchars((string)$log['event_key']) ?></code></span>
            <span><?= htmlspecialchars((string)($log['message'] ?? '')) ?></span>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>

<?php elseif ($activeTab === 'reputation'): ?>
<!-- == CONTRACT REPUTATION / REPUTACJA KONTRAKTOWA == -->
<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.contracts.reputation_title') ?></p>
    <p class="panel-hint"><?= t('admin.contracts.reputation_hint') ?></p>

    <form method="get" action="/admin/contracts.php" class="contracts-filter-form">
        <input type="hidden" name="tab" value="reputation">
        <div class="form-row form-row--gap">
            <div class="form-field form-field--wide">
                <label><?= t('admin.contracts.field_reputation_search') ?></label>
                <input type="text" name="q" maxlength="80" class="input-sm"
                       value="<?= htmlspecialchars($reputationSearch, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-field">
                <button type="submit" class="btn btn-secondary"><?= t('admin.contracts.btn_filter') ?></button>
            </div>
        </div>
    </form>

    <?php if (empty($reputationRows)): ?>
    <p class="panel-hint"><?= t('admin.contracts.reputation_empty') ?></p>
    <?php else: ?>
    <div class="contracts-admin-grid">
        <div class="contracts-admin-row contracts-admin-row--reputation contracts-admin-row--head">
            <span><?= t('admin.contracts.col_player') ?></span>
            <span><?= t('admin.contracts.col_reputation_score') ?></span>
            <span><?= t('admin.contracts.col_completed') ?></span>
            <span><?= t('admin.contracts.col_failed') ?></span>
            <span><?= t('admin.contracts.col_cancelled') ?></span>
            <span><?= t('admin.contracts.col_missed') ?></span>
            <span><?= t('admin.contracts.col_perfect') ?></span>
            <span><?= t('admin.contracts.col_actions') ?></span>
        </div>
        <?php foreach ($reputationRows as $row): ?>
        <?php $playerLabel = $reputationPlayerLabel($row); ?>
        <div class="contracts-admin-row contracts-admin-row--reputation">
            <span>
                <strong><?= htmlspecialchars($playerLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                <small>#<?= (int)$row['player_id'] ?></small>
            </span>
            <span><strong><?= (int)$row['score'] ?>/100</strong></span>
            <span><?= (int)$row['completed_contracts'] ?> / <?= (int)$row['total_contracts'] ?></span>
            <span><?= (int)$row['failed_contracts'] ?></span>
            <span><?= (int)$row['cancelled_contracts'] ?></span>
            <span><?= (int)$row['missed_deliveries'] ?></span>
            <span><?= (int)$row['perfect_contracts'] ?></span>
            <span class="contracts-admin-actions contracts-admin-actions--stack">
                <a href="/admin/contracts.php?tab=reputation&player_id=<?= (int)$row['player_id'] ?><?= $reputationSearch !== '' ? '&q=' . urlencode($reputationSearch) : '' ?>"
                   class="btn btn-xs btn-secondary"><?= t('admin.contracts.btn_history') ?></a>
                <form method="post" action="/admin/contracts.php?tab=reputation" class="contracts-reputation-adjust"
                      data-confirm="<?= htmlspecialchars(tPlain('admin.contracts.confirm_reputation_adjust', ['player' => $playerLabel]), ENT_QUOTES, 'UTF-8') ?>"
                      data-confirm-type="warning">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="adjust_reputation">
                    <input type="hidden" name="player_id" value="<?= (int)$row['player_id'] ?>">
                    <input type="number" name="delta" min="-100" max="100" step="1" required class="input-sm input-num-70"
                           aria-label="<?= htmlspecialchars(tPlain('admin.contracts.field_reputation_delta'), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="text" name="note" maxlength="255" class="input-sm contracts-reputation-note"
                           placeholder="<?= htmlspecialchars(tPlain('admin.contracts.field_reputation_note'), ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn btn-xs btn-primary"><?= t('admin.contracts.btn_adjust') ?></button>
                </form>
            </span>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>

<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.contracts.reputation_log_title') ?></p>
    <?php if ($reputationPlayerId > 0): ?>
    <p class="panel-hint">
        <?= t('admin.contracts.reputation_log_filtered', ['player' => (string)$reputationPlayerId]) ?>
        <a href="/admin/contracts.php?tab=reputation<?= $reputationSearch !== '' ? '&q=' . urlencode($reputationSearch) : '' ?>">
            <?= t('admin.contracts.btn_clear_filter') ?>
        </a>
    </p>
    <?php endif ?>
    <?php if (empty($reputationLogs)): ?>
    <p class="panel-hint"><?= t('admin.contracts.reputation_log_empty') ?></p>
    <?php else: ?>
    <div class="contracts-admin-grid">
        <div class="contracts-admin-row contracts-admin-row--reputation-log contracts-admin-row--head">
            <span><?= t('admin.contracts.col_date') ?></span>
            <span><?= t('admin.contracts.col_player') ?></span>
            <span><?= t('admin.contracts.col_delta') ?></span>
            <span><?= t('admin.contracts.col_score_after') ?></span>
            <span><?= t('admin.contracts.col_reason') ?></span>
            <span><?= t('admin.contracts.col_contract') ?></span>
        </div>
        <?php foreach ($reputationLogs as $log): ?>
        <div class="contracts-admin-row contracts-admin-row--reputation-log">
            <span><small><?= htmlspecialchars(substr((string)$log['created_at'], 0, 16), ENT_QUOTES, 'UTF-8') ?></small></span>
            <span><?= htmlspecialchars((string)($log['company_name'] ?? $log['username'] ?? ('#' . $log['player_id'])), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="<?= (int)$log['delta'] >= 0 ? 'c-good' : 'c-bad' ?>"><?= (int)$log['delta'] ?></span>
            <span><?= (int)$log['score_after'] ?>/100</span>
            <span><code><?= htmlspecialchars((string)$log['reason'], ENT_QUOTES, 'UTF-8') ?></code></span>
            <span><?= htmlspecialchars((string)($log['contract_name'] ?? ('#' . ($log['contract_id'] ?? '-'))), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>

<?php elseif ($activeTab === 'help'): ?>
<!-- == POMOC / HELP == -->
<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.contracts.help_title') ?></p>
    <div class="contracts-help">
        <p><?= t('admin.contracts.help_intro') ?></p>
        <ul class="contracts-help__list">
            <li><?= t('admin.contracts.help_1') ?></li>
            <li><?= t('admin.contracts.help_2') ?></li>
            <li><?= t('admin.contracts.help_3') ?></li>
            <li><?= t('admin.contracts.help_4') ?></li>
            <li><?= t('admin.contracts.help_5') ?></li>
        </ul>
    </div>
</section>
<?php endif ?>
