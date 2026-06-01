<?php extract($viewData, EXTR_SKIP); ?>

<h1><?= t('admin.market.title') ?></h1>

<?php if ($msg):   ?><p role="status" class="alert alert-success"><?= htmlspecialchars($msg)   ?></p><?php endif ?>
<?php if ($error): ?><p role="alert"  class="alert alert-error"  ><?= htmlspecialchars($error) ?></p><?php endif ?>

<!--  STAN RYNKU  -->
<section class="panel" aria-label="<?= t('admin.market.state_title') ?>">
    <p class="panel-title"><?= t('admin.market.state_title') ?></p>
    <div class="cards">
        <div class="card">
            <p class="label"><?= t('admin.market.price_current') ?></p>
            <p class="value orange"><?= number_format((float)($market['current_price'] ?? 0), 0, ',', ' ') ?> $</p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.market.price_base') ?></p>
            <p class="value"><?= number_format((float)($market['base_price'] ?? 0), 0, ',', ' ') ?> $</p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.market.volatility') ?></p>
            <p class="value"><?= htmlspecialchars((string)($market['volatility'] ?? '—')) ?></p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.market.active_trend') ?></p>
            <p class="value sm <?= $activeTrend ? 'orange' : '' ?>">
                <?= $activeTrend ? htmlspecialchars($activeTrend['trend_name']) : t('admin.market.no_trend') ?>
            </p>
        </div>
        <?php if ($activeTrend): ?>
        <div class="card">
            <p class="label"><?= t('admin.market.trend_time_left') ?></p>
            <p class="value sm"><?= htmlspecialchars($trendTimeLeft) ?></p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.market.price_modifier') ?></p>
            <p class="value"><?= htmlspecialchars((string)$activeTrend['price_modifier']) ?></p>
        </div>
        <?php endif ?>
        <div class="card">
            <p class="label"><?= t('admin.market.last_tick') ?></p>
            <p class="value sm"><?= htmlspecialchars($market['last_market_tick_at'] ?? '—') ?></p>
        </div>
    </div>
</section>

<!--  AKCJE  -->
<div class="action-row">

    <section class="panel" aria-label="<?= t('admin.market.set_price_title') ?>">
        <p class="panel-title"> <?= t('admin.market.set_price_title') ?> <span class="text-red">(<?= t('admin.market.tests_only') ?>)</span></p>
        <form method="post">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="set_price">
            <div class="form-row">
                <input type="number" name="price" min="30" max="300" step="1"
                       value="<?= (int)($market['current_price'] ?? 0) ?>" class="gm-input--short">
                <button type="submit" class="btn btn-danger"
                        onclick="confirmSubmit(this, '<?= t('admin.market.confirm_set_price') ?>'); return false;">
                    <?= t('admin.market.btn_set_price') ?>
                </button>
            </div>
        </form>
    </section>

    <section class="panel" aria-label="<?= t('admin.market.multiplier_title') ?>">
        <p class="panel-title"> <?= t('admin.market.multiplier_title') ?></p>
        <form method="post">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="set_multiplier">
            <div class="form-row">
                <input type="number" name="volatility" min="0.1" max="5.0" step="0.01"
                       value="<?= htmlspecialchars((string)($market['volatility'] ?? 1.0)) ?>" class="gm-input--short">
                <button type="submit" class="btn btn-primary"><?= t('admin.market.btn_set_multiplier') ?></button>
            </div>
            <p class="panel-hint"><?= t('admin.market.multiplier_hint') ?></p>
        </form>
    </section>

    <section class="panel" aria-label="<?= t('admin.market.set_trend_title') ?>">
        <p class="panel-title"> <?= t('admin.market.set_trend_title') ?></p>
        <form method="post">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="set_trend">
            <div class="form-row">
                <select name="trend_id" class="input-w-lg">
                    <option value="0"><?= t('admin.market.no_trend') ?></option>
                    <?php foreach ($allTrends as $tr): ?>
                    <option value="<?= (int)$tr['id'] ?>" <?= ($activeTrend && $activeTrend['id'] === $tr['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tr['trend_name']) ?> (×<?= $tr['price_modifier'] ?>, <?= $tr['duration_hours'] ?>h)
                    </option>
                    <?php endforeach ?>
                </select>
                <button type="submit" class="btn btn-primary"><?= t('admin.market.btn_activate') ?></button>
            </div>
        </form>
    </section>

    <section class="panel" aria-label="<?= t('admin.market.tick_title') ?>">
        <p class="panel-title"> <?= t('admin.market.tick_title') ?></p>
        <form method="post">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="market_tick">
            <button type="submit" class="btn btn-danger"
                    onclick="confirmSubmit(this, '<?= t('admin.market.confirm_tick') ?>'); return false;">
                <?= t('admin.market.btn_tick') ?>
            </button>
        </form>
    </section>

</div>

<!--  DODAJ / EDYTUJ TREND  -->
<?php
$formAction  = $editTrend ? 'edit_trend' : 'add_trend';
$formTitle   = $editTrend ? t('admin.market.form_edit_title') : t('admin.market.form_add_title');
$cancelUrl   = '?page=' . $page . '&per_page=' . $perPage . '&cat=' . urlencode($filterCat) . '&search=' . urlencode($filterName);
?>
<section class="panel" aria-label="<?= $formTitle ?>">
    <p class="panel-title"> <?= $formTitle ?></p>
    <form method="post">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="<?= $formAction ?>">
        <?php if ($editTrend): ?>
        <input type="hidden" name="trend_id" value="<?= (int)$editTrend['id'] ?>">
        <?php endif ?>
        <div class="trend-form-inline">
            <div class="trend-form-field">
                <label class="form-label"><?= t('admin.market.col_name') ?></label>
                <input type="text" name="trend_name" class="trend-input-name" maxlength="100" required
                       value="<?= htmlspecialchars($editTrend['trend_name'] ?? '') ?>">
            </div>
            <div class="trend-form-field">
                <label class="form-label"><?= t('admin.market.col_category') ?></label>
                <select name="category" class="trend-input-cat">
                    <?php foreach ($TREND_CATEGORIES as $cat): ?>
                    <option value="<?= $cat ?>" <?= ($editTrend['category'] ?? '') === $cat ? 'selected' : '' ?>>
                        <?= ucfirst($cat) ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="trend-form-field">
                <label class="form-label"><?= t('admin.market.col_modifier') ?></label>
                <input type="number" name="price_modifier" min="0.1" max="5.0" step="0.01" class="trend-input-num"
                       value="<?= htmlspecialchars((string)($editTrend['price_modifier'] ?? 1.0)) ?>">
            </div>
            <div class="trend-form-field">
                <label class="form-label"><?= t('admin.market.col_duration') ?></label>
                <input type="number" name="duration_hours" min="1" max="8760" step="1" class="trend-input-num"
                       value="<?= (int)($editTrend['duration_hours'] ?? 8) ?>">
            </div>
            <div class="trend-form-field trend-form-field--tpl">
                <label class="form-label"><?= t('admin.market.col_template') ?></label>
                <input type="text" name="message_template" class="trend-input-tpl" maxlength="255"
                       value="<?= htmlspecialchars($editTrend['message_template'] ?? '') ?>"
                       placeholder=" {name} — ceny ropy +{percent}%!">
            </div>
            <div class="trend-form-field trend-form-field--btn">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary">
                    <?= $editTrend ? t('admin.market.btn_save_trend') : t('admin.market.btn_add_trend') ?>
                </button>
                <?php if ($editTrend): ?>
                <a href="<?= htmlspecialchars($cancelUrl) ?>" class="btn btn-secondary"><?= t('admin.market.btn_cancel') ?></a>
                <?php endif ?>
            </div>
        </div>
    </form>
</section>

<!--  LISTA TRENDÓW Z FILTREM I PAGINACJ¥  -->
<section class="panel" aria-label="<?= t('admin.market.all_trends_title') ?>">
    <p class="panel-title"><?= t('admin.market.all_trends_title') ?> <span class="badge badge-inactive"><?= $totalTrends ?></span></p>

    <!-- Filtr -->
    <form method="get" class="trend-filter-form">
        <input type="text" name="search" class="gm-input--mid" placeholder="<?= t('admin.market.filter_search') ?>"
               value="<?= htmlspecialchars($filterName) ?>">
        <select name="cat" class="gm-input--mid">
            <option value=""><?= t('admin.market.filter_all_cats') ?></option>
            <?php foreach ($TREND_CATEGORIES as $cat): ?>
            <option value="<?= $cat ?>" <?= $filterCat === $cat ? 'selected' : '' ?>><?= ucfirst($cat) ?></option>
            <?php endforeach ?>
        </select>
        <select name="per_page" class="gm-input--short">
            <?php foreach ([5,10,20,50,100] as $pp): ?>
            <option value="<?= $pp ?>" <?= $perPage === $pp ? 'selected' : '' ?>><?= $pp ?>/str.</option>
            <?php endforeach ?>
        </select>
        <button type="submit" class="btn btn-secondary"><?= t('admin.market.btn_filter') ?></button>
        <a href="?" class="btn btn-secondary"><?= t('admin.market.btn_clear') ?></a>
    </form>

    <!-- Lista -->
    <div class="table-scroll-wrap">
    <div class="data-list trends-grid-full">
        <div class="list-header" role="row">
            <span><?= t('admin.market.col_id') ?></span>
            <span><?= t('admin.market.col_name') ?></span>
            <span><?= t('admin.market.col_category') ?></span>
            <span><?= t('admin.market.col_modifier') ?></span>
            <span><?= t('admin.market.col_duration') ?></span>
            <span><?= t('admin.market.col_status') ?></span>
            <span><?= t('admin.market.col_activated') ?></span>
            <span><?= t('admin.market.col_actions') ?></span>
        </div>
        <?php foreach ($pagedTrends as $tr):
            $isActive = $activeTrend && (int)$activeTrend['id'] === (int)$tr['id'];
        ?>
        <article class="list-row <?= $isActive ? 'is-active' : '' ?> <?= (int)$tr['id'] === $editId ? 'is-editing' : '' ?>" role="row">
            <span class="muted"><?= (int)$tr['id'] ?></span>
            <span class="bold"><?= htmlspecialchars($tr['trend_name']) ?></span>
            <span class="muted"><?= htmlspecialchars($tr['category']) ?></span>
            <span class="<?= (float)$tr['price_modifier'] > 1 ? 'text-green' : ((float)$tr['price_modifier'] < 1 ? 'text-red' : '') ?>">
                ×<?= htmlspecialchars((string)$tr['price_modifier']) ?>
            </span>
            <span><?= (int)$tr['duration_hours'] ?>h</span>
            <span>
                <?php if ($isActive): ?>
                <span class="badge badge-active"><?= t('admin.market.status_active') ?></span>
                <?php else: ?>
                <span class="badge badge-inactive"><?= t('admin.market.status_inactive') ?></span>
                <?php endif ?>
            </span>
            <span class="muted"><?= $tr['activated_at'] ? date('d.m H:i', strtotime($tr['activated_at'])) : '—' ?></span>
            <span class="trend-actions">
                <a href="?edit=<?= (int)$tr['id'] ?>&page=<?= $page ?>&per_page=<?= $perPage ?>&cat=<?= urlencode($filterCat) ?>&search=<?= urlencode($filterName) ?>"
                   class="btn btn-xs btn-secondary"><?= t('admin.market.btn_edit') ?></a>
                <form method="post" class="form-inline">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="delete_trend">
                    <input type="hidden" name="trend_id" value="<?= (int)$tr['id'] ?>">
                    <button type="submit" class="btn btn-xs btn-danger"
                            onclick="confirmSubmit(this, '<?= t('admin.market.confirm_delete_trend') ?>'); return false;">
                        <?= t('admin.market.btn_delete') ?>
                    </button>
                </form>
            </span>
        </article>
        <?php endforeach ?>
        <?php if (empty($pagedTrends)): ?>
        <p class="muted list-empty-msg"><?= t('admin.market.no_trends') ?></p>
        <?php endif ?>
    </div>
    </div>

    <!-- Paginacja -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php
        $baseUrl = '?per_page=' . $perPage . '&cat=' . urlencode($filterCat) . '&search=' . urlencode($filterName);
        for ($p = 1; $p <= $totalPages; $p++):
        ?>
        <a href="<?= $baseUrl ?>&page=<?= $p ?>"
           class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor ?>
    </div>
    <p class="muted pagination-info">
        <?= t('admin.market.pagination_info', ['from' => ($page-1)*$perPage+1, 'to' => min($page*$perPage,$totalTrends), 'total' => $totalTrends]) ?>
    </p>
    <?php endif ?>
</section>
