<?php $problem = $w['_problem']; ?>
<div class="ww-overview-grid">
    <article class="ww-card ww-incident-card ww-<?= wwEscape($problem['severity']) ?>">
        <header class="ww-section-heading">
            <span class="ww-section-icon"><?= wwIcon('repair') ?></span>
            <div><p class="ww-eyebrow"><?= t('ww.problem') ?></p><h4><?= t($problem['title_key']) ?></h4></div>
            <span class="ww-status-badge ww-<?= $w['_severity'] ?>"><?= $w['_st'][0] ?></span>
        </header>
        <p><?= t(match ($status) { 'paused_staff' => 'wg.diag_staff_need', 'paused_cash' => 'ww.cash_problem', 'paused_storage' => 'wg.diag_storage_note', 'broken','contaminated' => 'wg.diag_repair_note', 'blowout' => 'wg.diag_blowout_note', 'seized' => 'wg.diag_seized_note', 'equipment_swap' => 'wg.eq_swap_in_progress', default => 'ww.overview_note' }) ?></p>
        <?php if ($w['_missingSpecs']): ?><p><?= wwEscape(implode(', ', $w['_missingSpecs'])) ?></p><?php endif ?>
        <dl class="ww-incident-metrics">
            <div><dt><?= t('ww.base_difference') ?></dt><dd><?= wwNumber($w['_productionDelta']) ?> <?= t('common.bbl_h') ?></dd></div>
            <div><dt><?= t('ww.estimated_time') ?></dt><dd><?= $problem['hours_min'] === null ? t('ww.not_applicable') : t('ww.hours_range', ['min' => $problem['hours_min'], 'max' => $problem['hours_max']]) ?></dd></div>
            <div><dt><?= t('ww.estimated_cost') ?></dt><dd><?= $problem['cost_min'] === null ? t('ww.not_applicable') : t('ww.cost_range', ['min' => wwMoney($problem['cost_min']), 'max' => wwMoney($problem['cost_max'])]) ?></dd></div>
        </dl>
        <div class="ww-actions">
        <?php if ($problem['action'] === 'market'): ?><a class="ww-button ww-primary" href="<?= url('market') ?>"><?= t('wg.btn_sell_oil') ?></a>
        <?php elseif ($problem['action'] === 'hr'): ?><a class="ww-button ww-primary" href="<?= url('hr') ?>"><?= t('ww.manage_staff') ?></a>
        <?php elseif ($problem['action'] === 'recovery'): ?><a class="ww-button ww-primary" href="<?= url('recovery') ?>"><?= t('ww.open_recovery') ?></a>
        <?php elseif ($problem['action'] === 'technical_tasks'): ?><a class="ww-button ww-primary" href="<?= url('technical', ['tab' => 'tasks']) ?>"><?= t('ww.technical_tasks') ?></a>
        <?php elseif ($problem['action'] === 'geology'): ?><button class="ww-button ww-primary" type="button" data-well-open="geology"><?= t('ww.open_geology') ?></button>
        <?php elseif (str_starts_with($problem['action'], 'technical_')): ?><a class="ww-button ww-primary" href="<?= url('technical', ['tab' => 'team', 'repair_well' => $wid, 'task_type' => $problem['task']]) ?>"><?= wwIcon('repair') ?><?= t('technical.task.' . $problem['task']) ?></a>
        <?php else: ?><button class="ww-button ww-primary" type="button" data-well-open="equipment"><?= t('ww.manage_production') ?></button><?php endif ?>
        <a class="ww-button" href="<?= url('technical', ['tab' => 'team']) ?>"><?= t('ww.technical') ?></a>
        </div>
    </article>

    <aside class="ww-card ww-quick-assessment">
        <header class="ww-section-heading"><span class="ww-section-icon"><?= wwIcon('overview') ?></span><div><p class="ww-eyebrow"><?= t('ww.quick_assessment') ?></p><h4><?= t('ww.configuration') ?></h4></div></header>
        <?php foreach (['equipment' => $w['_tierLabel'][0] . ' · ' . t('wg.eq_level_badge', ['level' => $w['_eqLevel']]), 'storage' => t('ww.fill', ['pct' => $storagePct]), 'transport' => $w['_trLabel'] . ' · ' . wwNumber($w['_trCapPct']) . '%', 'geology' => wwEscape($w['_glCur']['name'] ?? tPlain('ww.unavailable'))] as $section => $value): ?>
        <button class="ww-assessment-row" type="button" data-well-open="<?= $section ?>"><?= wwIcon($tabs[$section]) ?><span><?= t('ww.' . $section) ?></span><strong><?= $value ?></strong></button>
        <?php endforeach ?>
    </aside>

    <article class="ww-card ww-flow-card">
        <header class="ww-section-heading"><span class="ww-section-icon"><?= wwIcon('flow') ?></span><div><p class="ww-eyebrow"><?= t('ww.flow') ?></p><h4><?= t('ww.flow') ?></h4></div><button type="button" class="ww-inline-link" data-well-open="transport"><?= t('ww.transport') ?> →</button></header>
        <div class="ww-flow">
            <div><?= wwIcon('odwiert-mini') ?><strong><?= t('ww.well') ?></strong><small><?= wwNumber($w['_production']) ?> <?= t('common.bbl_h') ?></small></div><span aria-hidden="true">→</span>
            <button type="button" data-well-open="transport"><?= wwIcon('truck') ?><strong><?= $w['_trLabel'] ?></strong><small><?= wwNumber($w['_trCapPct']) ?>%</small></button><span aria-hidden="true">→</span>
            <a href="<?= url('logistics') ?>#<?= $w['_hasHubAssignment'] ? 'logistics-owned-hubs-heading' : 'logistics-available-hubs-heading' ?>"><?= wwIcon('hub') ?><strong><?= $w['_hasHubAssignment'] ? wwEscape($w['_hubName']) : t('ww.no_hub') ?></strong><small><?= t('well_grid.transport_pipeline_open_hubs') ?></small></a><span aria-hidden="true">→</span>
            <button type="button" data-well-open="storage"><?= wwIcon('storage') ?><strong><?= t('ww.storage') ?></strong><small><?= wwNumber($storageUsed) ?> <?= t('common.bbl') ?></small></button>
        </div>
    </article>
</div>
