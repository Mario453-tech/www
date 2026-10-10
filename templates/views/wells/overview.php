<div class="ww-two-col">
    <article class="ww-box ww-<?= $w['_severity'] ?>">
        <h4><?= wwIcon('repair') ?><?= $w['_st'][0] ?></h4>
        <p><?= t(match ($status) { 'paused_staff' => 'wg.diag_staff_need', 'paused_cash' => 'ww.cash_problem', 'paused_storage' => 'wg.diag_storage_note', 'broken','contaminated' => 'wg.diag_repair_note', 'blowout' => 'wg.diag_blowout_note', 'seized' => 'wg.diag_seized_note', 'equipment_swap' => 'wg.eq_swap_in_progress', default => 'ww.overview_note' }) ?></p>
        <?php if ($w['_missingSpecs']): ?><p><?= wwEscape(implode(', ', $w['_missingSpecs'])) ?></p><?php endif ?>
        <dl class="ww-effects"><div><dt><?= t('wg.stat_base') ?></dt><dd><?= wwNumber($w['base_production_per_hour']) ?> <?= t('common.bbl_h') ?></dd></div><div><dt><?= t('ww.base_difference') ?></dt><dd><?= wwNumber($w['_productionDelta']) ?> <?= t('common.bbl_h') ?></dd></div></dl>
    </article>
    <?php $problem = $w['_problem']; ?>
    <article class="ww-box ww-<?= wwEscape($problem['severity']) ?>"><h4><?= wwIcon($problem['severity'] === 'good' ? 'status-dobry' : ($problem['severity'] === 'warning' ? 'status-uwaga' : 'status-krytyczny')) ?><?= t('ww.problem') ?></h4><p><strong><?= t($problem['title_key']) ?></strong></p>
        <dl class="ww-facts"><div><dt><?= t('ww.estimated_time') ?></dt><dd><?= $problem['hours_min'] === null ? t('ww.not_applicable') : t('ww.hours_range', ['min' => $problem['hours_min'], 'max' => $problem['hours_max']]) ?></dd></div><div><dt><?= t('ww.estimated_cost') ?></dt><dd><?= $problem['cost_min'] === null ? t('ww.not_applicable') : t('ww.cost_range', ['min' => wwMoney($problem['cost_min']), 'max' => wwMoney($problem['cost_max'])]) ?></dd></div></dl>
        <?php if ($problem['action'] === 'market'): ?><a class="ww-button ww-primary" href="<?= url('market') ?>"><?= t('wg.btn_sell_oil') ?></a>
        <?php elseif ($problem['action'] === 'hr'): ?><a class="ww-button ww-primary" href="<?= url('hr') ?>"><?= t('ww.manage_staff') ?></a>
        <?php elseif ($problem['action'] === 'recovery'): ?><a class="ww-button ww-primary" href="<?= url('recovery') ?>"><?= t('ww.open_recovery') ?></a>
        <?php elseif ($problem['action'] === 'technical_tasks'): ?><a class="ww-button ww-primary" href="<?= url('technical', ['tab' => 'tasks']) ?>"><?= t('ww.technical_tasks') ?></a>
        <?php elseif ($problem['action'] === 'geology'): ?><button class="ww-button ww-primary" type="button" data-well-open="geology"><?= t('ww.open_geology') ?></button>
        <?php elseif (str_starts_with($problem['action'], 'technical_')): ?><a class="ww-button ww-primary" href="<?= url('technical', ['tab' => 'team', 'repair_well' => $wid, 'task_type' => $problem['task']]) ?>"><?= t('technical.task.' . $problem['task']) ?></a>
        <?php else: ?><button class="ww-button ww-primary" type="button" data-well-open="equipment"><?= t('ww.manage_production') ?></button><?php endif ?>
    </article>
</div>
<article class="ww-box"><h4><?= wwIcon('premium') ?><?= t('ww.configuration') ?></h4><p><strong><?= $w['_tierLabel'][0] ?></strong> · <?= t('wg.eq_level_badge', ['level' => $w['_eqLevel']]) ?></p><?php wwEffects($w['_eqMults']); ?><button class="ww-button" type="button" data-well-open="equipment"><?= t('wg.eq_change_tier') ?></button></article>
<div class="ww-summary">
    <?php foreach (['equipment' => $w['_tierLabel'][0], 'storage' => t('ww.fill', ['pct' => $storagePct]), 'transport' => $w['_trLabel'], 'geology' => wwEscape($w['_glCur']['name'] ?? tPlain('ww.unavailable'))] as $section => $value): ?>
    <button class="ww-summary-item" type="button" data-well-open="<?= $section ?>"><?= wwIcon($tabs[$section]) ?><span><?= t('ww.' . $section) ?><strong><?= $value ?></strong></span></button>
    <?php endforeach ?>
</div>
<article class="ww-box"><h4><?= wwIcon('flow') ?><?= t('ww.flow') ?></h4><div class="ww-flow">
    <div><?= wwIcon('odwierty') ?><strong><?= t('ww.well') ?></strong><small><?= wwNumber($w['_production']) ?> <?= t('common.bbl_h') ?></small></div><span aria-hidden="true">→</span>
    <button type="button" data-well-open="transport"><?= wwIcon('truck') ?><strong><?= $w['_trLabel'] ?></strong><small><?= wwNumber($w['_trCapPct']) ?>%</small></button><span aria-hidden="true">→</span>
    <a href="<?= url('logistics') ?>#<?= $w['_hasHubAssignment'] ? 'logistics-owned-hubs-heading' : 'logistics-available-hubs-heading' ?>"><?= wwIcon('hub') ?><strong><?= $w['_hasHubAssignment'] ? wwEscape($w['_hubName']) : t('ww.no_hub') ?></strong><small><?= t('well_grid.transport_pipeline_open_hubs') ?></small></a><span aria-hidden="true">→</span>
    <button type="button" data-well-open="storage"><?= wwIcon('storage') ?><strong><?= t('ww.storage') ?></strong><small><?= wwNumber($storageUsed) ?> <?= t('common.bbl') ?></small></button>
</div></article>
