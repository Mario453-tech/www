<?php
require_once __DIR__ . '/helpers.php';
extract($wellWorkspace, EXTR_SKIP);
$tabs = ['overview' => 'overview', 'equipment' => 'repair', 'storage' => 'storage', 'transport' => 'truck', 'geology' => 'geology', 'history' => 'history'];
$criticalCount = count(array_filter($items, static fn(array $well): bool => ($well['_severity'] ?? '') === 'danger'));
?>
<section class="ww" data-well-workspace data-csrf="<?= wwEscape(CSRF::generateToken()) ?>" data-loading="<?= t('ww.loading') ?>" data-error="<?= t('common.err_retry') ?>" data-confirm="<?= t('ww.confirm') ?>" data-sale-note="<?= t('ww.sale_note') ?>" data-empty="<?= t('ww.history_empty') ?>" data-currency="<?= t('common.pln') ?>" data-page-label="<?= t('ww.page_label') ?>" aria-labelledby="wells-heading">
    <header class="ww-page-header">
        <div><p class="ww-eyebrow"><?= t('ww.eyebrow') ?></p><h2 id="wells-heading"><?= t('index.wells_heading') ?></h2><p class="ww-muted"><?= t('ww.intro') ?></p></div>
        <a class="ww-button ww-primary" href="<?= url('map') ?>"><?= wwIcon('odwierty') ?><?= t('index.action_buy_well') ?></a>
    </header>
    <p class="ww-message" role="status" aria-live="polite" data-well-message tabindex="-1"></p>
    <?php if (!$items): ?><div class="ww-box"><p><?= t('wg.no_wells') ?></p><a class="ww-button" href="<?= url('map') ?>"><?= t('ww.buy_first') ?></a></div><?php else: ?>
    <div class="ww-layout">
        <nav class="ww-selector" aria-label="<?= t('index.wells_heading') ?>">
            <header class="ww-selector-heading"><div><p class="ww-eyebrow"><?= t('ww.selector') ?></p><strong><?= t('ww.active_wells', ['count' => count($items)]) ?></strong></div><?php if ($criticalCount > 0): ?><span class="ww-selector-alert"><?= t('ww.critical_wells', ['count' => $criticalCount]) ?></span><?php endif ?></header>
            <div class="ww-selector-list">
            <?php foreach ($items as $index => $w): $selectorIcon = $w['_severity'] === 'good' ? 'status-dobry' : ($w['_severity'] === 'warning' ? 'status-uwaga' : 'status-krytyczny'); ?>
                <button type="button" class="ww-select ww-<?= $w['_severity'] ?>" data-well-select="<?= (int)$w['id'] ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>" aria-controls="wg-card-<?= (int)$w['id'] ?>">
                    <span class="ww-select-icon"><?= wwIcon('odwiert-mini') ?></span><span class="ww-select-copy"><strong><?= wwEscape($w['location_name'] ?? tPlain('wg.default_well_name')) ?></strong><small>#<?= (int)$w['id'] ?> · <?= wwEscape($w['region_name'] ?? '') ?> · <?= t('technical.well_type_' . ($w['well_type'] ?? 'onshore')) ?></small><span class="ww-selector-metrics"><b><?= wwNumber($w['_cond']) ?>%</b><em><?= wwNumber($w['_production']) ?> <?= t('common.bbl_h') ?></em></span></span><span class="ww-selector-status"><?= wwIcon($selectorIcon) ?><span><?= $w['_st'][0] ?></span></span>
                </button>
            <?php endforeach ?>
            </div>
        </nav>
        <div class="ww-details">
        <?php foreach ($items as $index => $w): $wid = (int)$w['id']; $status = $w['_status']; ?>
            <article class="ww-well" id="wg-card-<?= $wid ?>" data-well="<?= $wid ?>" data-well-name="<?= wwEscape($w['location_name'] ?? '') ?>" tabindex="-1" <?= $index !== 0 ? 'hidden' : '' ?>>
                <header class="ww-well-header">
                    <span class="ww-well-symbol"><?= wwIcon('odwiert-mini') ?></span><div class="ww-identity"><p class="ww-eyebrow"><?= t('ww.well_number', ['id' => $wid]) ?></p><h3><?= wwEscape($w['location_name'] ?? tPlain('wg.default_well_name')) ?></h3><div class="ww-badges"><span><?= t('technical.well_type_' . ($w['well_type'] ?? 'onshore')) ?></span><span><?= t('wg.eq_level_badge', ['level' => (int)$w['level']]) ?></span><span class="ww-status-badge ww-<?= $w['_severity'] ?>"><?= $w['_st'][0] ?></span></div></div>
                    <?php $headerTask = $w['_problem']['task'] ?? null; ?>
                    <a class="ww-button ww-primary" href="<?= $headerTask !== null ? url('technical', ['tab' => 'team', 'repair_well' => $wid, 'task_type' => $headerTask]) : url('technical', ['tab' => 'team']) ?>"><?= wwIcon('repair') ?><?= $headerTask !== null ? t('technical.task.' . $headerTask) : t('ww.technical') ?></a>
                </header>
                <div class="ww-kpis">
                    <article class="ww-kpi ww-kpi-production"><span class="ww-kpi-icon"><?= wwIcon('produkcja') ?></span><div><small><?= t('wg.stat_production') ?></small><strong><?= wwNumber($w['_production']) ?> <em><?= t('common.bbl_h') ?></em></strong><span><?= t('ww.base_difference') ?> <?= wwNumber($w['_productionDelta']) ?> <?= t('common.bbl_h') ?></span></div></article>
                    <article class="ww-kpi ww-kpi-condition ww-<?= $w['_severity'] ?>"><span class="ww-kpi-icon"><?= wwIcon('condition') ?></span><div><small><?= t('wg.stat_condition') ?></small><strong><?= wwNumber($w['_cond']) ?>%</strong><progress max="100" value="<?= max(0, min(100, $w['_cond'])) ?>" aria-label="<?= t('wg.stat_condition') ?>"></progress></div></article>
                    <article class="ww-kpi ww-kpi-transport"><span class="ww-kpi-icon"><?= wwIcon('truck') ?></span><div><small><?= t('ww.transport') ?></small><strong><?= $w['_trLabel'] ?></strong><span><?= t('wg.transport_capacity') ?> <?= wwNumber($w['_trCapPct']) ?>%</span></div></article>
                    <article class="ww-kpi ww-kpi-storage"><span class="ww-kpi-icon"><?= wwIcon('storage') ?></span><div><small><?= t('ww.storage') ?></small><strong><?= wwNumber($storageUsed) ?> / <?= wwNumber($storageCap) ?></strong><span><?= t('ww.fill', ['pct' => $storagePct]) ?></span></div></article>
                </div>
                <div class="ww-tabs-box">
                    <div class="ww-tabs" role="tablist" aria-label="<?= t('ww.sections') ?>">
                        <?php foreach ($tabs as $tab => $icon): ?><button type="button" role="tab" id="ww-tab-<?= $wid ?>-<?= $tab ?>" aria-controls="ww-panel-<?= $wid ?>-<?= $tab ?>" aria-selected="<?= $tab === 'overview' ? 'true' : 'false' ?>" tabindex="<?= $tab === 'overview' ? '0' : '-1' ?>" data-well-tab="<?= $tab ?>"><?= wwIcon($icon) ?><span><?= t('ww.' . $tab) ?></span></button><?php endforeach ?>
                    </div>
                    <?php foreach ($tabs as $tab => $icon): ?>
                    <section class="ww-panel ww-panel-<?= $tab ?>" role="tabpanel" id="ww-panel-<?= $wid ?>-<?= $tab ?>" aria-labelledby="ww-tab-<?= $wid ?>-<?= $tab ?>" data-well-panel="<?= $tab ?>" tabindex="0" <?= $tab !== 'overview' ? 'hidden' : '' ?>>
                        <?php require __DIR__ . '/' . $tab . '.php'; ?>
                    </section>
                    <?php endforeach ?>
                </div>
                <section class="ww-danger-zone" aria-label="<?= t('wg.danger_zone') ?>">
                    <div><?= wwIcon('sell') ?><span><strong><?= t('wg.danger_zone') ?></strong><small><?= t('ww.sale_note') ?></small></span></div>
                    <div><button type="button" class="ww-button ww-danger" data-well-sale <?= ($status !== 'active' || $w['_hasHubAssignment']) ? 'disabled' : '' ?>><?= t('wg.btn_sell_well') ?></button><?php if ($status !== 'active' || $w['_hasHubAssignment']): ?><p class="ww-muted"><?= t('ww.sale_blocked') ?></p><?php endif ?></div>
                </section>
            </article>
        <?php endforeach ?>
        </div>
    </div>
    <?php endif ?>
</section>
