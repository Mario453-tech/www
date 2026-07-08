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
 * @var array<string,float|int|string> $b2bConfig
 * @var array<int,array<string,mixed>> $b2bOffers
 * @var array<int,array<string,mixed>> $b2bLogs
 * @var array<int,array<string,mixed>> $b2bReputationRows
 * @var array<string,int> $b2bStats
 * @var array{status?:string,query?:string,flagged?:string} $b2bOfferFilters
 * @var string $b2bRepQuery
 * @var int $b2bOfferPage
 * @var int $b2bLogsPage
 * @var int $b2bRepPage
 * @var int $b2bPageLimit
 * @var int $b2bOffersCount
 * @var int $b2bLogsCount
 * @var int $b2bReputationCount
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
$b2bStatusLabel = static function (string $status): string {
    $k = 'admin.contracts.b2b_status_' . $status;
    $l = tPlain($k);
    return $l !== $k ? $l : $status;
};
$b2bPager = static function (string $pageParam, int $page, int $count, int $limit, array $params = []): void {
    if ($count <= $limit) {
        return;
    }
    $maxPage = (int)ceil($count / $limit);
    $base = '/admin/contracts.php?' . http_build_query(array_merge(['tab' => 'b2b'], $params));
    $sep = str_contains($base, '?') ? '&' : '?';
    ?>
    <nav class="contracts-admin-pager">
        <?php if ($page > 1): ?>
        <a class="btn btn-xs btn-secondary" href="<?= htmlspecialchars($base . $sep . $pageParam . '=' . ($page - 1), ENT_QUOTES, 'UTF-8') ?>"><?= t('admin.contracts.prev_page') ?></a>
        <?php endif ?>
        <span><?= t('admin.contracts.page_x_of_y', ['page' => (string)$page, 'pages' => (string)$maxPage]) ?></span>
        <?php if ($page < $maxPage): ?>
        <a class="btn btn-xs btn-secondary" href="<?= htmlspecialchars($base . $sep . $pageParam . '=' . ($page + 1), ENT_QUOTES, 'UTF-8') ?>"><?= t('admin.contracts.next_page') ?></a>
        <?php endif ?>
    </nav>
    <?php
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

<?php elseif ($activeTab === 'b2b'): ?>
<?php
$b2bSubTabs = ['pulpit', 'oferty', 'dostawy', 'gracze', 'ustawienia', 'logi'];
$b2bSubTab = $b2bSubTab ?? 'pulpit';
?>
<nav class="contracts-admin-subtabs mb-4">
    <?php foreach ($b2bSubTabs as $st): ?>
    <a class="btn btn-xs <?= $b2bSubTab === $st ? 'btn-primary' : 'btn-secondary' ?>"
       href="/admin/contracts.php?tab=b2b&b2b_subtab=<?= rawurlencode($st) ?>">
        <?= htmlspecialchars(t('admin.contracts.b2b_subtab_' . $st), ENT_QUOTES, 'UTF-8') ?>
    </a>
    <?php endforeach ?>
</nav>

<?php if ($b2bSubTab === 'pulpit'): ?>
<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.contracts.b2b_dashboard_title') ?></p>
    <div class="contracts-admin-stats">
        <div><span><?= t('admin.contracts.b2b_stat_in_progress') ?></span><strong><?= (int)($b2bDashboard['in_progress'] ?? 0) ?></strong></div>
        <div><span><?= t('admin.contracts.b2b_stat_deliveries_today') ?></span><strong><?= (int)($b2bDashboard['deliveries_today'] ?? 0) ?></strong></div>
        <div><span><?= t('admin.contracts.b2b_stat_missing_bbl') ?></span><strong><?= $fmtBbl((float)($b2bDashboard['missing_bbl'] ?? 0)) ?> bbl</strong></div>
        <div><span><?= t('admin.contracts.b2b_stat_locked_funds') ?></span><strong><?= $fmtPln((float)($b2bDashboard['locked_funds'] ?? 0)) ?></strong></div>
        <div><span><?= t('admin.contracts.b2b_stat_penalties') ?></span><strong><?= $fmtPln((float)($b2bDashboard['penalties'] ?? 0)) ?></strong></div>
        <div><span><?= t('admin.contracts.b2b_stat_overdue') ?></span><strong><?= (int)($b2bDashboard['overdue'] ?? 0) ?></strong></div>
    </div>
    <div class="contracts-admin-stats mt-4">
        <div><span><?= t('admin.contracts.b2b_stat_open') ?></span><strong><?= (int)($b2bStats['open'] ?? 0) ?></strong></div>
        <div><span><?= t('admin.contracts.b2b_stat_flagged') ?></span><strong><?= (int)($b2bStats['flagged'] ?? 0) ?></strong></div>
        <div><span><?= t('admin.contracts.b2b_stat_completed') ?></span><strong><?= (int)($b2bStats['completed'] ?? 0) ?></strong></div>
        <div><span><?= t('admin.contracts.b2b_stat_reputation') ?></span><strong><?= (int)($b2bStats['reputation'] ?? 0) ?></strong></div>
    </div>
</section>

<?php elseif ($b2bSubTab === 'oferty'): ?>
<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.contracts.b2b_offers_title') ?></p>
    <form method="get" action="/admin/contracts.php" class="contracts-filter-form">
        <input type="hidden" name="tab" value="b2b">
        <input type="hidden" name="b2b_subtab" value="oferty">
        <div class="form-row form-row--gap">
            <div class="form-field">
                <label><?= t('admin.contracts.b2b_filter_status') ?></label>
                <select name="b2b_status" class="input-sm">
                    <option value=""><?= t('admin.contracts.b2b_filter_all') ?></option>
                    <?php foreach (['open', 'accepted', 'completed', 'cancelled', 'expired', 'failed', 'partial_done', 'flagged'] as $statusOpt): ?>
                    <option value="<?= $statusOpt ?>" <?= (string)($b2bOfferFilters['status'] ?? '') === $statusOpt ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b2bStatusLabel($statusOpt), ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="form-field">
                <label><?= t('admin.contracts.b2b_filter_flagged') ?></label>
                <select name="b2b_flagged" class="input-sm">
                    <option value=""><?= t('admin.contracts.b2b_filter_all') ?></option>
                    <option value="1" <?= (string)($b2bOfferFilters['flagged'] ?? '') === '1' ? 'selected' : '' ?>><?= t('admin.contracts.b2b_filter_flagged_yes') ?></option>
                    <option value="0" <?= (string)($b2bOfferFilters['flagged'] ?? '') === '0' ? 'selected' : '' ?>><?= t('admin.contracts.b2b_filter_flagged_no') ?></option>
                </select>
            </div>
            <div class="form-field form-field--wide">
                <label><?= t('admin.contracts.b2b_filter_query') ?></label>
                <input type="text" name="b2b_q" maxlength="80" class="input-sm"
                       value="<?= htmlspecialchars((string)($b2bOfferFilters['query'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-field">
                <button type="submit" class="btn btn-secondary"><?= t('admin.contracts.btn_filter') ?></button>
            </div>
        </div>
    </form>
    <?php if (empty($b2bOffers)): ?>
    <p class="panel-hint"><?= t('admin.contracts.b2b_offers_empty') ?></p>
    <?php else: ?>
    <div class="contracts-admin-grid">
        <div class="contracts-admin-row contracts-admin-row--b2b-ext contracts-admin-row--head">
            <span><?= t('admin.contracts.col_date') ?></span>
            <span><?= t('admin.contracts.b2b_col_buyer') ?></span>
            <span><?= t('admin.contracts.b2b_col_seller') ?></span>
            <span><?= t('admin.contracts.b2b_col_volume') ?></span>
            <span><?= t('admin.contracts.b2b_col_delivered') ?></span>
            <span><?= t('admin.contracts.b2b_col_remaining') ?></span>
            <span><?= t('admin.contracts.b2b_col_paid') ?></span>
            <span><?= t('admin.contracts.b2b_col_escrow_left') ?></span>
            <span><?= t('admin.contracts.b2b_col_penalty') ?></span>
            <span><?= t('admin.contracts.col_status') ?></span>
            <span><?= t('admin.contracts.col_actions') ?></span>
        </div>
        <?php foreach ($b2bOffers as $offer): ?>
        <div class="contracts-admin-row contracts-admin-row--b2b-ext">
            <span><small><?= htmlspecialchars(substr((string)$offer['created_at'], 0, 16)) ?></small></span>
            <span><?= htmlspecialchars((string)($offer['buyer_name'] ?? '')) ?></span>
            <span><?= htmlspecialchars((string)($offer['seller_name'] ?? '')) ?></span>
            <span><?= $fmtBbl((float)$offer['total_bbl']) ?> bbl</span>
            <span><?= $fmtBbl((float)($offer['delivered_bbl'] ?? 0)) ?> bbl</span>
            <span><?= $fmtBbl(max(0.0, (float)$offer['total_bbl'] - (float)($offer['delivered_bbl'] ?? 0))) ?> bbl</span>
            <span><?= $fmtPln((float)($offer['released_amount'] ?? 0)) ?></span>
            <span><?= $fmtPln((float)($offer['remaining_escrow_amount'] ?? 0)) ?></span>
            <span><?= $offer['seller_penalty_amount'] > 0 ? $fmtPln((float)$offer['seller_penalty_amount']) : '—' ?></span>
            <span><?= htmlspecialchars($b2bStatusLabel((string)$offer['status'])) ?><?= !empty($offer['is_flagged']) ? ' / ' . t('admin.contracts.b2b_flagged') : '' ?></span>
            <span class="contracts-admin-actions contracts-admin-actions--stack">
                <?php if (empty($offer['is_flagged'])): ?>
                <form method="post" action="/admin/contracts.php?tab=b2b&b2b_subtab=oferty" class="contracts-inline-form">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="b2b_flag_offer">
                    <input type="hidden" name="offer_id" value="<?= (int)$offer['id'] ?>">
                    <input type="hidden" name="reason" value="admin_review">
                    <button type="submit" class="btn btn-xs btn-secondary"><?= t('admin.contracts.b2b_btn_flag') ?></button>
                </form>
                <?php else: ?>
                <form method="post" action="/admin/contracts.php?tab=b2b&b2b_subtab=oferty" class="contracts-inline-form">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="b2b_unflag_offer">
                    <input type="hidden" name="offer_id" value="<?= (int)$offer['id'] ?>">
                    <button type="submit" class="btn btn-xs btn-secondary"><?= t('admin.contracts.b2b_btn_unflag') ?></button>
                </form>
                <?php endif ?>
                <?php if ((string)$offer['status'] === 'open'): ?>
                <form method="post" action="/admin/contracts.php?tab=b2b&b2b_subtab=oferty" class="contracts-inline-form"
                      data-confirm="<?= htmlspecialchars(tPlain('admin.contracts.b2b_confirm_cancel'), ENT_QUOTES, 'UTF-8') ?>" data-confirm-type="warning">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="b2b_cancel_offer">
                    <input type="hidden" name="offer_id" value="<?= (int)$offer['id'] ?>">
                    <input type="hidden" name="reason" value="admin_cancelled">
                    <button type="submit" class="btn btn-xs btn-danger"><?= t('admin.contracts.b2b_btn_cancel') ?></button>
                </form>
                <?php endif ?>
            </span>
        </div>
        <?php endforeach ?>
    </div>
    <?php
        $b2bPager('b2b_page', $b2bOfferPage, $b2bOffersCount, $b2bPageLimit, [
            'b2b_status' => (string)($b2bOfferFilters['status'] ?? ''),
            'b2b_flagged' => (string)($b2bOfferFilters['flagged'] ?? ''),
            'b2b_q' => (string)($b2bOfferFilters['query'] ?? ''),
            'b2b_subtab' => 'oferty',
        ]);
    ?>
    <?php endif ?>
</section>

<?php elseif ($b2bSubTab === 'dostawy'): ?>
<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.contracts.b2b_deliveries_title') ?></p>
    <?php if (empty($b2bDeliveries)): ?>
    <p class="panel-hint"><?= t('admin.contracts.b2b_deliveries_empty') ?></p>
    <?php else: ?>
    <div class="contracts-admin-grid">
        <div class="contracts-admin-row contracts-admin-row--b2b-del contracts-admin-row--head">
            <span><?= t('admin.contracts.col_date') ?></span>
            <span><?= t('admin.contracts.b2b_col_offer') ?></span>
            <span><?= t('admin.contracts.b2b_col_buyer') ?></span>
            <span><?= t('admin.contracts.b2b_col_seller') ?></span>
            <span><?= t('admin.contracts.b2b_col_delivered') ?></span>
            <span><?= t('admin.contracts.b2b_col_price') ?></span>
            <span><?= t('admin.contracts.b2b_col_revenue') ?></span>
            <span><?= t('admin.contracts.b2b_col_remaining_after') ?></span>
        </div>
        <?php foreach ($b2bDeliveries as $del): ?>
        <div class="contracts-admin-row contracts-admin-row--b2b-del">
            <span><small><?= htmlspecialchars(substr((string)$del['created_at'], 0, 16)) ?></small></span>
            <span>#<?= (int)$del['offer_id'] ?></span>
            <span><?= htmlspecialchars((string)($del['buyer_name'] ?? '')) ?></span>
            <span><?= htmlspecialchars((string)($del['seller_name'] ?? '')) ?></span>
            <span><?= $fmtBbl((float)$del['delivered_bbl']) ?> bbl</span>
            <span><?= $fmtPln((float)$del['price_per_bbl']) ?></span>
            <span><?= $fmtPln((float)$del['revenue']) ?></span>
            <span><?= $fmtBbl((float)$del['remaining_bbl_after']) ?> bbl</span>
        </div>
        <?php endforeach ?>
    </div>
    <?php $b2bPager('b2b_dostawy_strona', $b2bDeliveriesPage, $b2bDeliveriesCount, $b2bPageLimit, ['b2b_subtab' => 'dostawy']); ?>
    <?php endif ?>
</section>

<?php elseif ($b2bSubTab === 'gracze'): ?>
<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.contracts.b2b_reputation_title') ?></p>
    <form method="get" action="/admin/contracts.php" class="contracts-filter-form">
        <input type="hidden" name="tab" value="b2b">
        <input type="hidden" name="b2b_subtab" value="gracze">
        <div class="form-row form-row--gap">
            <div class="form-field form-field--wide">
                <label><?= t('admin.contracts.b2b_filter_query') ?></label>
                <input type="text" name="b2b_rep_q" maxlength="80" class="input-sm"
                       value="<?= htmlspecialchars($b2bRepQuery, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-field">
                <button type="submit" class="btn btn-secondary"><?= t('admin.contracts.btn_filter') ?></button>
            </div>
        </div>
    </form>
    <?php if (empty($b2bReputationRows)): ?>
    <p class="panel-hint"><?= t('admin.contracts.b2b_reputation_empty') ?></p>
    <?php else: ?>
    <div class="contracts-admin-grid">
        <div class="contracts-admin-row contracts-admin-row--b2b-rep contracts-admin-row--head">
            <span><?= t('admin.contracts.col_player') ?></span>
            <span><?= t('admin.contracts.col_reputation_score') ?></span>
            <span><?= t('admin.contracts.b2b_col_buy_done') ?></span>
            <span><?= t('admin.contracts.b2b_col_sell_done') ?></span>
            <span><?= t('admin.contracts.b2b_col_cancelled') ?></span>
            <span><?= t('admin.contracts.b2b_col_expired') ?></span>
            <span><?= t('admin.contracts.b2b_col_flags') ?></span>
        </div>
        <?php foreach ($b2bReputationRows as $row): ?>
        <div class="contracts-admin-row contracts-admin-row--b2b-rep">
            <span><?= htmlspecialchars((string)($row['company_name'] ?? $row['username'] ?? ('#' . $row['player_id'])), ENT_QUOTES, 'UTF-8') ?></span>
            <span><strong><?= (int)$row['score'] ?>/100</strong></span>
            <span><?= (int)$row['buy_completed'] ?> / <?= $fmtBbl((float)$row['total_bought_bbl']) ?> bbl</span>
            <span><?= (int)$row['sell_completed'] ?> / <?= $fmtBbl((float)$row['total_sold_bbl']) ?> bbl</span>
            <span><?= (int)$row['buy_cancelled'] ?></span>
            <span><?= (int)$row['buy_expired'] ?></span>
            <span><?= (int)$row['admin_flags'] ?> / <?= (int)$row['admin_cancellations'] ?></span>
        </div>
        <?php endforeach ?>
    </div>
    <?php $b2bPager('b2b_rep_page', $b2bRepPage, $b2bReputationCount, $b2bPageLimit, ['b2b_rep_q' => $b2bRepQuery, 'b2b_subtab' => 'gracze']); ?>
    <?php endif ?>
</section>

<?php elseif ($b2bSubTab === 'ustawienia'): ?>
<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.contracts.b2b_settings_title') ?></p>
    <form method="post" action="/admin/contracts.php?tab=b2b&b2b_subtab=ustawienia">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="b2b_save_config">
        <div class="form-row form-row--gap">
            <div class="form-field">
                <label class="module-toggle-label">
                    <input type="checkbox" name="module_enabled" value="1" <?= ((float)($b2bConfig['module_enabled'] ?? 0) > 0) ? 'checked' : '' ?>>
                    <?= t('admin.contracts.b2b_module_enabled') ?>
                </label>
            </div>
            <div class="form-field">
                <label class="module-toggle-label">
                    <input type="checkbox" name="partial_delivery_enabled" value="1" <?= ((float)($b2bConfig['partial_delivery_enabled'] ?? 1) > 0) ? 'checked' : '' ?>>
                    <?= t('admin.contracts.b2b_partial_delivery_enabled') ?>
                </label>
            </div>
            <div class="form-field">
                <label class="module-toggle-label">
                    <input type="checkbox" name="allow_multiple_deliveries" value="1" <?= ((float)($b2bConfig['allow_multiple_deliveries'] ?? 1) > 0) ? 'checked' : '' ?>>
                    <?= t('admin.contracts.b2b_allow_multiple_deliveries') ?>
                </label>
            </div>
            <div class="form-field">
                <label class="module-toggle-label">
                    <input type="checkbox" name="auto_finalize_after_deadline" value="1" <?= ((float)($b2bConfig['auto_finalize_after_deadline'] ?? 1) > 0) ? 'checked' : '' ?>>
                    <?= t('admin.contracts.b2b_auto_finalize') ?>
                </label>
            </div>
        </div>
        <div class="form-row form-row--gap">
            <div class="form-field">
                <label><?= t('admin.contracts.b2b_min_first_delivery_pct') ?></label>
                <input type="number" name="min_first_delivery_pct" min="0" max="100" step="1" class="input-sm input-num-70" value="<?= htmlspecialchars((string)($b2bConfig['min_first_delivery_pct'] ?? 25)) ?>">
            </div>
            <div class="form-field">
                <label><?= t('admin.contracts.b2b_seller_penalty_pct') ?></label>
                <input type="number" name="seller_penalty_pct" min="0" max="100" step="0.01" class="input-sm input-num-70" value="<?= htmlspecialchars((string)($b2bConfig['seller_penalty_pct'] ?? 10)) ?>">
            </div>
            <div class="form-field">
                <label><?= t('admin.contracts.b2b_delivery_deadline_minutes') ?></label>
                <input type="number" name="delivery_deadline_minutes" min="1" step="1" class="input-sm input-num-90" value="<?= htmlspecialchars((string)($b2bConfig['delivery_deadline_minutes'] ?? 1440)) ?>">
            </div>
        </div>
        <div class="form-row form-row--gap">
            <div class="form-field">
                <label><?= t('admin.contracts.b2b_min_price_pct') ?></label>
                <input type="number" name="min_price_market_pct" min="1" step="1" class="input-sm input-num-70" value="<?= htmlspecialchars((string)($b2bConfig['min_price_market_pct'] ?? 70)) ?>">
            </div>
            <div class="form-field">
                <label><?= t('admin.contracts.b2b_max_price_pct') ?></label>
                <input type="number" name="max_price_market_pct" min="1" step="1" class="input-sm input-num-70" value="<?= htmlspecialchars((string)($b2bConfig['max_price_market_pct'] ?? 130)) ?>">
            </div>
            <div class="form-field">
                <label><?= t('admin.contracts.b2b_cancel_penalty_pct') ?></label>
                <input type="number" name="buyer_cancel_penalty_pct" min="0" max="100" step="0.01" class="input-sm input-num-70" value="<?= htmlspecialchars((string)($b2bConfig['buyer_cancel_penalty_pct'] ?? 10)) ?>">
            </div>
        </div>
        <div class="form-row form-row--gap">
            <div class="form-field">
                <label><?= t('admin.contracts.b2b_min_bbl') ?></label>
                <input type="number" name="min_bbl_per_offer" min="1" step="1" class="input-sm input-num-90" value="<?= htmlspecialchars((string)($b2bConfig['min_bbl_per_offer'] ?? 100)) ?>">
            </div>
            <div class="form-field">
                <label><?= t('admin.contracts.b2b_max_bbl') ?></label>
                <input type="number" name="max_bbl_per_offer" min="1" step="1" class="input-sm input-num-90" value="<?= htmlspecialchars((string)($b2bConfig['max_bbl_per_offer'] ?? 50000)) ?>">
            </div>
            <div class="form-field">
                <label><?= t('admin.contracts.b2b_max_open') ?></label>
                <input type="number" name="max_open_offers_per_player" min="1" step="1" class="input-sm input-num-70" value="<?= htmlspecialchars((string)($b2bConfig['max_open_offers_per_player'] ?? 5)) ?>">
            </div>
            <div class="form-field">
                <label><?= t('admin.contracts.b2b_review_threshold') ?></label>
                <input type="number" name="admin_review_threshold_value" min="0" step="1000" class="input-sm input-num-120" value="<?= htmlspecialchars((string)($b2bConfig['admin_review_threshold_value'] ?? 5000000)) ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-success"><?= t('admin.contracts.btn_save_module') ?></button>
    </form>
</section>

<?php elseif ($b2bSubTab === 'logi'): ?>
<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.contracts.b2b_logs_title') ?></p>
    <?php if (empty($b2bLogs)): ?>
    <p class="panel-hint"><?= t('admin.contracts.b2b_logs_empty') ?></p>
    <?php else: ?>
    <div class="contracts-admin-grid">
        <div class="contracts-admin-row contracts-admin-row--logs contracts-admin-row--head">
            <span><?= t('admin.contracts.col_date') ?></span>
            <span><?= t('admin.contracts.col_player') ?></span>
            <span><?= t('admin.contracts.col_event') ?></span>
            <span><?= t('admin.contracts.col_message') ?></span>
        </div>
        <?php foreach ($b2bLogs as $log): ?>
        <div class="contracts-admin-row contracts-admin-row--logs">
            <span><small><?= htmlspecialchars(substr((string)$log['created_at'], 0, 16)) ?></small></span>
            <span><?= htmlspecialchars((string)($log['player_name'] ?? '')) ?></span>
            <span><code><?= htmlspecialchars((string)$log['event_key']) ?></code></span>
            <span><?= htmlspecialchars((string)($log['message'] ?? '')) ?></span>
        </div>
        <?php endforeach ?>
    </div>
    <?php $b2bPager('b2b_logs_page', $b2bLogsPage, $b2bLogsCount, $b2bPageLimit, ['b2b_subtab' => 'logi']); ?>
    <?php endif ?>
</section>
<?php endif ?>


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
