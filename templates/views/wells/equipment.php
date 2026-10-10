<h4><?= wwIcon('repair') ?><?= t('ww.equipment') ?></h4>
<p class="ww-muted"><?= t('ww.equipment_note') ?></p>
<div class="ww-options">
<?php foreach ($w['_tierOptions'] as $tier => $option): $current = $tier === $w['_eqTier']; $tierLabel = t('ww.tier_' . $tier); ?>
<article class="ww-box <?= $current ? 'ww-current' : '' ?>">
    <h4><?= wwIcon(str_replace('_', '-', $tier)) ?><?= $tierLabel ?></h4>
    <?php if ($current): ?><p class="ww-badge"><?= t('wg.eq_current') ?> · <?= t('wg.eq_level_badge', ['level' => $w['_eqLevel']]) ?></p><?php endif ?>
    <?php wwEffects($current ? $w['_eqMults'] : $option['effects']); ?>
    <p class="ww-price"><?= wwMoney($option['cost']) ?></p>
    <?php if (!$current):
        $changes = [];
        foreach ($option['effects'] as $key => $effect) $changes[] = tPlain('wg.eq_stat_' . $key) . ': ' . round(($w['_eqMults'][$key] - 1) * 100, 1) . '% → ' . round(($effect - 1) * 100, 1) . '%';
        wwAction(t('wg.eq_btn_install'), '/public/equipment_well.php', ['well_id' => $wid, 'action' => 'set_tier', 'tier' => $tier], tPlain('ww.install_summary', ['tier' => tPlain('ww.tier_' . $tier), 'cost' => wwMoney($option['cost'])]) . "\n" . implode("\n", $changes), $w['_blocked'] ? t('ww.blocked') : ($playerCash < $option['cost'] ? t('ww.no_funds') : ''));
    endif ?>
</article>
<?php endforeach ?>
</div>
<?php if ($w['_nextUpgCost'] !== null): ?>
<article class="ww-box"><h4><?= wwIcon('upgrade') ?><?= t('wg.eq_btn_upgrade', ['level' => $w['_eqLevel'] + 1]) ?></h4><?php wwEffects($w['_nextEffects']); ?><p class="ww-price"><?= wwMoney($w['_nextUpgCost']) ?></p>
<?php wwAction(t('wg.eq_btn_upgrade', ['level' => $w['_eqLevel'] + 1]), '/public/equipment_well.php', ['well_id' => $wid, 'action' => 'upgrade_level'], tPlain('ww.upgrade_summary', ['level' => $w['_eqLevel'] + 1, 'cost' => wwMoney($w['_nextUpgCost'])]), $w['_blocked'] ? t('ww.blocked') : ($playerCash < $w['_nextUpgCost'] ? t('ww.no_funds') : '')); ?>
</article>
<?php else: ?><p><?= t('wg.eq_max_level') ?></p><?php endif ?>
