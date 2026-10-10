<h4><?= wwIcon('truck') ?><?= t('ww.transport') ?></h4>
<div class="ww-two-col">
<article class="ww-box"><h4><?= t('ww.leg_one') ?></h4>
<?php foreach ($w['_trDefs'] as $code => [$name, $color, $capacity, $opex, $description, $available]): if ($code === 'nieustawiony') continue;
    $cfg = $transportConfig[$code];
    $current = $code === $w['_trTypeEffective'];
    $reason = !$available ? t($w['_isOffshore'] ? 'wg.transport_no_offshore' : 'wg.transport_offshore_only') : ($w['_blocked'] ? t('ww.blocked') : '');
    if ($reason === '' && $code === 'rurociag' && !$w['_hasHubAssignment']) $reason = t('well_grid.transport_pipeline_hub_required');
    if ($reason === '' && $code === 'rurociag' && $w['_pipelineBuilding']) $reason = t('well_grid.transport_pipeline_building');
    if ($reason === '' && $code === 'rurociag' && !$w['_pipelineOwned'] && $playerCash < $w['_pipelineBuildCost']) $reason = t('ww.no_funds');
    $icon = ['rurociag' => 'pipeline', 'ciezarowki' => 'truck', 'tankowiec' => 'tanker'][$code];
?>
<div class="ww-transport-option <?= $current ? 'ww-current' : '' ?>"><h4><?= wwIcon($icon) ?><?= $name ?></h4><p><?= $description ?></p>
<dl class="ww-effects"><div><dt><?= t('wg.transport_capacity') ?></dt><dd><?= wwNumber($code === 'rurociag' ? $capacity : $cfg['capacity']) ?>%</dd></div><div><dt><?= t('wg.transport_opex') ?></dt><dd><?= wwNumber($cfg['opex']) ?>%</dd></div><div><dt><?= t('wg.transport_risk_short') ?></dt><dd><?= wwNumber(($cfg['incident'] - 1) * 100) ?>%</dd></div></dl>
<?php if ($current): ?><span class="ww-badge"><?= t('wg.eq_current') ?></span>
<?php elseif ($code === 'rurociag' && !$w['_pipelineOwned']): ?>
    <button class="ww-button" type="button" data-well-pipeline data-summary="<?= wwEscape(tPlain('ww.pipeline_summary', ['cost' => wwMoney($w['_pipelineBuildCost'])])) ?>" <?= $reason !== '' ? 'disabled' : '' ?>><?= t('ww.pipeline_options') ?> · <?= wwMoney($w['_pipelineBuildCost']) ?></button><p class="ww-muted"><?= $reason ?></p>
<?php else:
    wwAction(t('wg.transport_btn_switch'), '/src/WellStaffApi.php', ['action' => 'set_transport', 'well_id' => $wid, 'transport_type' => $code], tPlain('ww.transport_summary', ['name' => html_entity_decode($name), 'capacity' => $cfg['capacity'], 'opex' => $cfg['opex']]), $reason);
endif ?>
</div>
<?php endforeach ?>
</article>
<article class="ww-box"><h4><?= wwIcon('hub') ?><?= t('well_grid.leg2_title') ?></h4><p><?= t('well_grid.leg2_desc') ?></p>
<?php if (!$w['_hasHubAssignment']): ?><div class="ww-empty"><?= wwIcon('hub') ?><h4><?= t('ww.no_hub') ?></h4><p><?= t('well_grid.leg2_hub_required') ?></p><a class="ww-button" href="<?= url('logistics') ?>#logistics-available-hubs-heading"><?= t('well_grid.transport_pipeline_open_hubs') ?></a></div>
<?php else: ?><p><strong><?= wwEscape($w['_hubName']) ?></strong></p><p><?= t('ww.current') ?>: <?= t(match ($w['_outboundType']) {'rurociag' => 'wg.transport_pipe_label', 'ciezarowki' => 'wg.transport_truck_label', default => 'wg.transport_unset_label'}) ?></p>
<?php foreach (['rurociag', 'ciezarowki'] as $out): if ($out === $w['_outboundType']) continue;
    $cost = $out === 'rurociag' && !$w['_outboundPipelineOwned'] ? $w['_outboundPipelineBuildCost'] : 0;
    wwAction(t($out === 'rurociag' ? 'well_grid.leg2_btn_pipeline' : 'well_grid.leg2_btn_road') . ' · ' . wwMoney($cost), '/src/WellStaffApi.php', ['action' => 'set_outbound_transport', 'well_id' => $wid, 'transport_type' => $out], tPlain('ww.outbound_summary', ['cost' => wwMoney($cost)]), $w['_blocked'] ? t('ww.blocked') : ($w['_outboundPipelineBuilding'] ? t('well_grid.leg2_pipeline_building') : ($playerCash < $cost ? t('ww.no_funds') : '')));
endforeach ?>
<a class="ww-button" href="<?= url('logistics') ?>#logistics-owned-hubs-heading"><?= t('ww.manage_hub') ?></a>
<?php endif ?>
</article>
</div>
