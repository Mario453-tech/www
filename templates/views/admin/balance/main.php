<?php extract($viewData, EXTR_SKIP); ?>

<h1> <?= t('admin.balance.title') ?></h1>
<p class="panel-hint"><?= t('admin.balance.subtitle') ?></p>

<?php if ($msg): ?><p role="status" class="alert alert-success"><?= htmlspecialchars($msg) ?></p><?php endif ?>
<?php if ($err): ?><p role="alert"  class="alert alert-error"><?= htmlspecialchars($err) ?></p><?php endif ?>

<!-- Economy context / PL: Kontekst ekonomii -->
<section class="panel mb-8" aria-label="<?= t('admin.balance.context_title') ?>">
    <p class="panel-title"> <?= t('admin.balance.context_title') ?></p>
    <div class="cards">
        <div class="card">
            <p class="label"><?= t('admin.balance.stat_oil_price') ?></p>
            <p class="value orange">$<?= number_format($oilPrice, 0) ?></p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.balance.stat_active_players') ?></p>
            <p class="value"><?= $activePlayerCount ?></p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.balance.stat_active_wells') ?></p>
            <p class="value green"><?= (int)($prodStats['active_wells'] ?? 0) ?></p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.balance.stat_total_prod') ?></p>
            <p class="value orange"><?= number_format((float)($prodStats['total_base_prod'] ?? 0), 1) ?></p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.balance.stat_avg_revenue') ?></p>
            <p class="value sm"><?= number_format($estRevenuePerDay, 0) ?> $</p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.balance.stat_pipeline_loss') ?></p>
            <?php $avgL = (float)($pipeStats['avg_loss'] ?? 0); ?>
            <p class="value <?= $avgL > 10 ? 'red' : 'green' ?>"><?= number_format($avgL, 1) ?>%</p>
        </div>
    </div>
</section>

<!-- Emergency actions and reset / PL: Akcje awaryjne i reset -->
<div class="action-row">

<section class="panel" aria-label="<?= t('admin.balance.emergency_title') ?>">
    <p class="panel-title"> <?= t('admin.balance.emergency_title') ?></p>
    <p class="panel-hint"><?= t('admin.balance.emergency_hint') ?></p>
    <form method="post">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="emergency_nerf">
        <div class="gm-form-stack">
            <div class="form-row">
                <label class="form-label-inline"><?= t('admin.balance.emergency_target') ?>:</label>
                <select name="nerf_target" class="select-flex">
                    <?php foreach ([
                        'incidents'  => ' ' . t('admin.balance.nerf_incidents'),
                        'loss'       => ' ' . t('admin.balance.nerf_loss'),
                        'all_risk'   => ' ' . t('admin.balance.nerf_all_risk'),
                        'production' => ' ' . t('admin.balance.nerf_production'),
                        'tax'        => ' ' . t('admin.finance.cfg_tax_label'),
                    ] as $val => $lbl): ?>
                    <option value="<?= $val ?>"><?= $lbl ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="form-row">
                <label class="form-label-inline"><?= t('admin.balance.emergency_factor') ?>:</label>
                <input type="number" name="nerf_factor" value="1.0" min="0.1" max="2.0" step="0.05" class="input-w-sm">
                <span class="form-hint"><?= t('admin.balance.factor_hint') ?></span>
            </div>
            <button type="submit" class="btn btn-danger"
                    onclick="confirmSubmit(this, '<?= t('admin.balance.confirm_emergency') ?>'); return false;">
                 <?= t('admin.balance.btn_apply') ?>
            </button>
        </div>
    </form>
</section>

<section class="panel" aria-label="<?= t('admin.balance.reset_title') ?>">
    <p class="panel-title"> <?= t('admin.balance.reset_title') ?></p>
    <p class="panel-hint"><?= t('admin.balance.reset_hint') ?></p>
    <form method="post">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="reset_balance">
        <button type="submit" class="btn btn-secondary"
                onclick="confirmSubmit(this, '<?= t('admin.balance.confirm_reset') ?>'); return false;">
             <?= t('admin.balance.btn_reset') ?>
        </button>
    </form>
</section>

</div>

<!-- Global multipliers / PL: Mnozniki globalne -->
<form method="post">
    <?= CSRF::field() ?>
    <input type="hidden" name="action" value="save_balance">

    <section class="panel">
        <p class="panel-title"> <?= t('admin.balance.multipliers_title') ?></p>
        <div class="config-rows">
        <?php foreach ($BALANCE_KEYS as $key => [$label, $default, $hint]):
            $current    = isset($currentConfig[$key]) ? (float)$currentConfig[$key] : (float)$default;
            $isModified = abs($current - 1.0) > 0.001;
            $valueClass = $isModified ? ($current < 1.0 ? 'mult-nerf' : 'mult-buff') : 'mult-normal';
        ?>
        <div class="config-row">
            <div>
                <div class="config-key-label">
                    <?= htmlspecialchars($label) ?>
                    <?php if ($isModified): ?>
                    <span class="badge badge-warning"><?= t('admin.balance.badge_modified') ?></span>
                    <?php endif ?>
                </div>
                <div class="config-key-code"><?= htmlspecialchars($hint) ?></div>
            </div>
            <div class="config-input-group">
                <input type="range" name="<?= $key ?>_range"
                       min="0.1" max="3.0" step="0.05" value="<?= $current ?>"
                       class="config-range"
                       oninput="document.getElementById('val_<?= $key ?>').value=parseFloat(this.value).toFixed(2);
                                document.getElementById('num_<?= $key ?>').value=parseFloat(this.value).toFixed(2)">
                <input type="number" id="num_<?= $key ?>" name="<?= $key ?>"
                       value="<?= $current ?>" min="0.1" max="10.0" step="0.05"
                       class="config-input config-input-sm <?= $valueClass ?>"
                       oninput="document.querySelectorAll('[name=<?= $key ?>_range]')[0].value=this.value">
                <span id="val_<?= $key ?>" class="font-xs muted">
                    <?= t('admin.balance.default_label') ?>: <?= $default ?>
                </span>
            </div>
        </div>
        <?php endforeach ?>
        </div>
    </section>

    <div class="form-row">
        <button type="submit" class="btn btn-primary"
                onclick="confirmSubmit(this, '<?= t('admin.balance.confirm_save') ?>'); return false;">
             <?= t('admin.balance.btn_save') ?>
        </button>
        <span class="form-hint"><?= t('admin.balance.save_hint') ?></span>
    </div>
</form>

<!-- Guide / PL: Poradnik -->
<section class="panel mt-4">
    <p class="panel-title"> <?= t('admin.balance.guide_title') ?></p>
    <div class="panel-info-2col">
        <p><strong><?= t('admin.balance.guide_too_hard') ?></strong></p>
        <ul>
            <li><?= t('admin.balance.key_incident') ?>  0.5-0.7</li>
            <li><?= t('admin.balance.key_degradation') ?>  0.6-0.8</li>
            <li><?= t('admin.balance.key_opex') ?>  0.7-0.9</li>
        </ul>
        <p><strong><?= t('admin.balance.guide_too_easy') ?></strong></p>
        <ul>
            <li><?= t('admin.balance.key_wear') ?>  1.3-1.5</li>
            <li><?= t('admin.balance.key_disaster') ?>  1.2-1.5</li>
            <li><?= t('admin.balance.key_loss') ?>  1.2-1.4</li>
        </ul>
        <p><strong><?= t('admin.balance.guide_low_price') ?></strong></p>
        <ul>
            <li><?= t('admin.balance.key_production') ?>  0.7-0.8 (<?= t('admin.balance.guide_reduce_supply') ?>)</li>
            <li><?= t('admin.balance.guide_or_trend') ?>  <a href="/admin/market.php"><?= t('admin.balance.guide_market_link') ?></a></li>
        </ul>
    </div>
    <p class="panel-footer-note">
         <?= t('admin.balance.guide_footer') ?>
        <a href="/admin/logs.php"> <?= t('admin.balance.guide_logs_link') ?></a> |
        <a href="/admin/force_tick.php"> <?= t('admin.balance.guide_tick_link') ?></a>
    </p>
</section>
