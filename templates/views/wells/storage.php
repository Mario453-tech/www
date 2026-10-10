<div class="ww-two-col ww-storage-grid">
    <article class="ww-card">
        <header class="ww-section-heading"><span class="ww-section-icon"><?= wwIcon('storage') ?></span><div><p class="ww-eyebrow"><?= t('ww.storage') ?></p><h4><?= t('ww.storage_capacity') ?></h4></div><span class="ww-status-badge <?= $storagePct >= 100 ? 'ww-danger' : 'ww-good' ?>"><?= t('ww.fill', ['pct' => $storagePct]) ?></span></header>
        <p class="ww-storage-value"><?= wwNumber($storageUsed) ?><small> / <?= wwNumber($storageCap) ?> <?= t('common.bbl') ?></small></p>
        <progress max="100" value="<?= $storagePct ?>" aria-label="<?= t('ww.storage') ?>"></progress>
        <p class="ww-muted"><?= t('ww.shared_storage') ?></p>
        <?php if ($storagePct >= 100): ?><p class="ww-danger"><?= t('wg.diag_storage_note') ?></p><?php endif ?>
        <div class="ww-actions"><a class="ww-button ww-primary" href="<?= url('market') ?>"><?= t('wg.btn_sell_oil') ?></a><button class="ww-button" type="button" data-well-open="transport"><?= t('ww.transport') ?></button></div>
    </article>
    <article class="ww-card">
        <header class="ww-section-heading"><span class="ww-section-icon"><?= wwIcon('upgrade') ?></span><div><p class="ww-eyebrow"><?= t('ww.expand_storage') ?></p><h4><?= t('ww.next_storage_level') ?></h4></div></header>
        <div class="ww-upgrade-preview"><span><small><?= t('ww.current') ?></small><strong><?= wwNumber($storageCap) ?> <?= t('common.bbl') ?></strong></span><i aria-hidden="true">→</i><span><small><?= t('wg.storage_after') ?></small><strong><?= wwNumber($storageAfter) ?> <?= t('common.bbl') ?></strong></span></div>
        <dl class="ww-facts"><div><dt><?= t('wg.storage_cost') ?></dt><dd><?= wwMoney($upgradeCost) ?></dd></div><div><dt><?= t('ww.duration') ?></dt><dd><?= t('ww.instant') ?></dd></div></dl>
        <?php wwAction(t('ww.expand_storage'), '/public/upgrade_storage.php', ['well_id' => $wid], tPlain('ww.storage_summary', ['before' => wwNumber($storageCap), 'after' => wwNumber($storageAfter), 'cost' => wwMoney($upgradeCost)]), !$storageData ? t('ww.unavailable') : ($w['_blocked'] ? t('ww.blocked') : (!$canAffordUpg ? t('ww.no_funds') : ''))); ?>
    </article>
</div>
