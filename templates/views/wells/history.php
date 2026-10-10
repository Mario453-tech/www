<header class="ww-panel-heading ww-history-heading">
    <div class="ww-section-heading"><span class="ww-section-icon"><?= wwIcon('history') ?></span><div><p class="ww-eyebrow"><?= t('ww.history') ?></p><h4><?= t('ww.history_title') ?></h4></div></div>
    <label class="ww-filter"><span><?= t('ww.category') ?></span><select data-well-history-filter><option value="all"><?= t('ww.filter_all') ?></option><option value="failures"><?= t('ww.filter_failures') ?></option><option value="service"><?= t('ww.filter_service') ?></option><option value="production"><?= t('ww.filter_production') ?></option><option value="transport"><?= t('ww.filter_transport') ?></option><option value="configuration"><?= t('ww.filter_configuration') ?></option></select></label>
</header>
<p class="ww-retention-note" data-history-retention><?= wwIcon('history') ?><?= t('ww.history_retention') ?></p>
<div class="ww-history" data-well-history role="status" aria-live="polite"><p><?= t('ww.loading') ?></p></div>
<nav class="ww-pagination" aria-label="<?= t('ww.pagination') ?>">
    <button type="button" class="ww-button" data-history-direction="-1" disabled><?= t('ww.newer') ?></button>
    <div class="ww-page-numbers" data-history-pages></div>
    <button type="button" class="ww-button" data-history-direction="1" disabled><?= t('ww.older') ?></button>
    <span class="ww-page-summary"><span data-history-page>1</span> · <span data-history-count>0</span></span>
    <button type="button" class="ww-button ww-refresh" data-history-retry><?= t('ww.refresh') ?></button>
</nav>
