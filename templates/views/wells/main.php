<?php
require_once __DIR__ . '/helpers.php';
extract($wellWorkspace, EXTR_SKIP);
$tabs = ['overview' => 'overview', 'equipment' => 'repair', 'storage' => 'storage', 'transport' => 'truck', 'geology' => 'geology', 'history' => 'history'];
?>
<section class="ww" data-well-workspace data-csrf="<?= wwEscape(CSRF::generateToken()) ?>" data-loading="<?= t('ww.loading') ?>" data-error="<?= t('common.err_retry') ?>" data-confirm="<?= t('ww.confirm') ?>" data-sale-note="<?= t('ww.sale_note') ?>" data-empty="<?= t('ww.history_empty') ?>" data-currency="<?= t('common.pln') ?>" aria-labelledby="wells-heading">
    <header class="ww-page-header">
        <div><p class="ww-eyebrow"><?= t('ww.eyebrow') ?></p><h2 id="wells-heading"><?= t('index.wells_heading') ?></h2><p class="ww-muted"><?= t('ww.intro') ?></p></div>
        <a class="ww-button ww-primary" href="<?= url('map') ?>"><?= wwIcon('odwierty') ?><?= t('index.action_buy_well') ?></a>
    </header>
    <p class="ww-message" role="status" aria-live="polite" data-well-message></p>
    <?php if (!$items): ?><div class="ww-box"><p><?= t('wg.no_wells') ?></p><a class="ww-button" href="<?= url('map') ?>"><?= t('ww.buy_first') ?></a></div><?php else: ?>
    <div class="ww-layout">
        <nav class="ww-selector" aria-label="<?= t('index.wells_heading') ?>">
            <h3><?= t('ww.selector') ?> <span class="ww-muted"><?= count($items) ?></span></h3>
            <div class="ww-selector-list">
            <?php foreach ($items as $index => $w): ?>
                <button type="button" class="ww-select ww-<?= $w['_severity'] ?>" data-well-select="<?= (int)$w['id'] ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>" aria-controls="wg-card-<?= (int)$w['id'] ?>">
                    <?= wwIcon('odwiert-mini') ?><span><strong><?= wwEscape($w['location_name'] ?? tPlain('wg.default_well_name')) ?></strong><small>#<?= (int)$w['id'] ?> · <?= wwEscape($w['region_name'] ?? '') ?> · <?= t('technical.well_type_' . ($w['well_type'] ?? 'onshore')) ?></small><span class="ww-selector-metrics"><?= wwNumber($w['_cond']) ?>% · <?= wwNumber($w['_production']) ?> <?= t('common.bbl_h') ?></span><small><?= wwIcon($w['_severity'] === 'good' ? 'status-dobry' : ($w['_severity'] === 'warning' ? 'status-uwaga' : 'status-krytyczny')) ?><?= $w['_st'][0] ?></small></span>
                </button>
            <?php endforeach ?>
            </div>
        </nav>
        <div class="ww-details">
        <?php foreach ($items as $index => $w): $wid = (int)$w['id']; $status = $w['_status']; ?>
            <article class="ww-well" id="wg-card-<?= $wid ?>" data-well="<?= $wid ?>" data-well-name="<?= wwEscape($w['location_name'] ?? '') ?>" tabindex="-1" <?= $index !== 0 ? 'hidden' : '' ?>>
                <header class="ww-well-header">
                    <span class="ww-well-symbol"><?= wwIcon('odwierty') ?></span><div class="ww-identity"><p class="ww-eyebrow"><?= t('ww.well_number', ['id' => $wid]) ?></p><h3><?= wwEscape($w['location_name'] ?? tPlain('wg.default_well_name')) ?></h3><p class="ww-muted"><?= wwEscape($w['region_name'] ?? '') ?></p><div class="ww-badges"><span><?= t('technical.well_type_' . ($w['well_type'] ?? 'onshore')) ?></span><span><?= t('wg.eq_level_badge', ['level' => (int)$w['level']]) ?></span><span class="ww-<?= $w['_severity'] ?>"><?= $w['_st'][0] ?></span></div></div>
                    <?php $headerTask = $w['_problem']['task'] ?? null; ?>
                    <a class="ww-button ww-primary" href="<?= $headerTask !== null ? url('technical', ['tab' => 'team', 'repair_well' => $wid, 'task_type' => $headerTask]) : url('technical', ['tab' => 'team']) ?>"><?= wwIcon('repair') ?><?= $headerTask !== null ? t('technical.task.' . $headerTask) : t('ww.technical') ?></a>
                </header>
                <div class="ww-kpis">
                    <div class="ww-kpi"><?= wwIcon('produkcja') ?><div><span><?= t('wg.stat_production') ?></span><strong><?= wwNumber($w['_production']) ?> <small><?= t('common.bbl_h') ?></small></strong><small><?= t('ww.base_difference') ?> <?= wwNumber($w['_productionDelta']) ?> <?= t('common.bbl_h') ?></small></div></div>
                    <div class="ww-kpi ww-<?= $w['_severity'] ?>"><?= wwIcon('condition') ?><div><span><?= t('wg.stat_condition') ?></span><strong><?= wwNumber($w['_cond']) ?>%</strong><progress max="100" value="<?= max(0, min(100, $w['_cond'])) ?>" aria-label="<?= t('wg.stat_condition') ?>"></progress><small><?= $w['_st'][0] ?></small></div></div>
                    <div class="ww-kpi"><?= wwIcon('storage') ?><div><span><?= t('ww.storage') ?></span><strong><?= wwNumber($storageUsed) ?> / <?= wwNumber($storageCap) ?></strong><progress max="100" value="<?= $storagePct ?>" aria-label="<?= t('ww.storage') ?>"></progress><small><?= t('ww.fill', ['pct' => $storagePct]) ?></small></div></div>
                    <div class="ww-kpi"><?= wwIcon('truck') ?><div><span><?= t('ww.transport') ?></span><strong><?= $w['_trLabel'] ?></strong><small><?= t('wg.transport_capacity') ?> <?= wwNumber($w['_trCapPct']) ?>%</small></div></div>
                </div>
                <div class="ww-tabs-box">
                    <div class="ww-tabs" role="tablist" aria-label="<?= t('ww.sections') ?>">
                        <?php foreach ($tabs as $tab => $icon): ?><button type="button" role="tab" id="ww-tab-<?= $wid ?>-<?= $tab ?>" aria-controls="ww-panel-<?= $wid ?>-<?= $tab ?>" aria-selected="<?= $tab === 'overview' ? 'true' : 'false' ?>" tabindex="<?= $tab === 'overview' ? '0' : '-1' ?>" data-well-tab="<?= $tab ?>"><?= wwIcon($icon) ?><?= t('ww.' . $tab) ?></button><?php endforeach ?>
                    </div>
                    <?php foreach ($tabs as $tab => $icon): ?>
                    <section class="ww-panel" role="tabpanel" id="ww-panel-<?= $wid ?>-<?= $tab ?>" aria-labelledby="ww-tab-<?= $wid ?>-<?= $tab ?>" data-well-panel="<?= $tab ?>" tabindex="0" <?= $tab !== 'overview' ? 'hidden' : '' ?>>
                        <?php require __DIR__ . '/' . $tab . '.php'; ?>
                    </section>
                    <?php endforeach ?>
                </div>
                <section class="ww-danger-zone" aria-label="<?= t('wg.danger_zone') ?>">
                    <div><h4><?= wwIcon('sell') ?><?= t('wg.danger_zone') ?></h4><p><?= t('ww.sale_note') ?></p></div>
                    <div><button type="button" class="ww-button ww-danger" data-well-sale <?= ($status !== 'active' || $w['_hasHubAssignment']) ? 'disabled' : '' ?>><?= t('wg.btn_sell_well') ?></button><?php if ($status !== 'active' || $w['_hasHubAssignment']): ?><p class="ww-muted"><?= t('ww.sale_blocked') ?></p><?php endif ?></div>
                </section>
            </article>
        <?php endforeach ?>
        </div>
    </div>
    <?php endif ?>
</section>
