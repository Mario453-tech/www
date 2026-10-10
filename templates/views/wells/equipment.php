<header class="ww-panel-heading"><div class="ww-section-heading"><span class="ww-section-icon"><?= wwIcon('repair') ?></span><div><p class="ww-eyebrow"><?= t('ww.equipment') ?></p><h4><?= t('ww.choose_configuration') ?></h4></div></div><span class="ww-current-label"><?= t('ww.current') ?>: <?= $w['_tierLabel'][0] ?> · <?= t('wg.eq_level_badge', ['level' => $w['_eqLevel']]) ?></span></header>
<p class="ww-panel-note"><?= t('ww.equipment_note') ?></p>
<div class="ww-equipment-grid">
<?php foreach ($w['_tierOptions'] as $tier => $option): $current = $tier === $w['_eqTier']; $tierLabel = t('ww.tier_' . $tier); ?>
<article class="ww-card ww-equipment-card ww-equipment-<?= wwEscape(str_replace('_', '-', $tier)) ?> <?= $current ? 'ww-current' : '' ?>">
    <header><span class="ww-section-icon"><?= wwIcon(str_replace('_', '-', $tier)) ?></span><div><h4><?= $tierLabel ?></h4><p><?= t('ww.equipment_card_note') ?></p></div><?php if ($current): ?><span class="ww-badge"><?= t('wg.eq_current') ?></span><?php endif ?></header>
    <?php wwEffects($current ? $w['_eqMults'] : $option['effects']); ?>
    <footer><strong><?= $current ? t('wg.eq_level_badge', ['level' => $w['_eqLevel']]) : wwMoney($option['cost']) ?></strong>
    <?php if (!$current):
        $changes = [];
        foreach ($option['effects'] as $key => $effect) $changes[] = tPlain('wg.eq_stat_' . $key) . ': ' . round(($w['_eqMults'][$key] - 1) * 100, 1) . '% → ' . round(($effect - 1) * 100, 1) . '%';
        wwAction(t('wg.eq_btn_install'), '/public/equipment_well.php', ['well_id' => $wid, 'action' => 'set_tier', 'tier' => $tier], tPlain('ww.install_summary', ['tier' => tPlain('ww.tier_' . $tier), 'cost' => wwMoney($option['cost'])]) . "\n" . implode("\n", $changes), $w['_blocked'] ? t('ww.blocked') : ($playerCash < $option['cost'] ? t('ww.no_funds') : ''));
    elseif ($w['_nextUpgCost'] !== null):
        wwAction(t('wg.eq_btn_upgrade', ['level' => $w['_eqLevel'] + 1]), '/public/equipment_well.php', ['well_id' => $wid, 'action' => 'upgrade_level'], tPlain('ww.upgrade_summary', ['level' => $w['_eqLevel'] + 1, 'cost' => wwMoney($w['_nextUpgCost'])]), $w['_blocked'] ? t('ww.blocked') : ($playerCash < $w['_nextUpgCost'] ? t('ww.no_funds') : ''));
    else: ?><span class="ww-badge"><?= t('wg.eq_max_level') ?></span><?php endif ?>
    </footer>
</article>
<?php endforeach ?>
</div>
