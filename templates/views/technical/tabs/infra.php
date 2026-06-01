<?php if (empty($pipelines)): ?>
<div class="pipe-card">
    <div class="pipe-hdr">
        <div>
            <div class="pipe-label"><?= t('technical.infra_label') ?></div>
            <div class="pipe-name"><?= t('technical.pipe_none_title') ?></div>
        </div>
        <span class="badge b-idle"><?= t('technical.pipe_none_badge') ?></span>
    </div>
    <div class="pipe-footer"><?= t('technical.pipe_none_desc') ?></div>
</div>
<?php else: ?>
<?php foreach ($pipelines as $pipe):
    $status = (string)($pipe['status'] ?? 'active');
    $conditionPct = (float)($pipe['condition_pct'] ?? 100.0);
    $lossPct = (float)($pipe['transport_loss'] ?? 0.0);
    $capacityBbl = (float)($pipe['capacity_bbl_h'] ?? $pipe['real_capacity_bph'] ?? 0.0);
    $nominalBbl = (float)($pipe['nominal_capacity_bph'] ?? 0.0);
    $tickCost = (float)($pipe['opex_per_tick'] ?? 0.0);
    $flowCost = (float)($pipe['opex_per_bbl'] ?? 0.0);
    $buildCost = (float)($pipe['build_cost'] ?? 0.0);
    $pipeType = (string)($pipe['pipeline_type'] ?? 'standard');

    $statusBadgeClass = match ($status) {
        'damaged', 'disabled' => 'b-broken',
        'critical', 'degraded', 'servicing', 'suspended' => 'b-paused',
        default => 'b-active',
    };
    $statusLabelKey = 'technical.pipe_status_' . $status;
?>
<div class="pipe-card">
    <div class="pipe-hdr">
        <div>
            <div class="pipe-label"><?= t('technical.infra_label') ?></div>
            <div class="pipe-name"><?= htmlspecialchars((string)($pipe['name'] ?? t('logistics.pipeline.fallback_name', ['id' => (int)($pipe['id'] ?? 0)]))) ?></div>
            <div class="pipe-sub"><?= t('technical.pipe_related_well') ?>: <?= htmlspecialchars((string)($pipe['well_name'] ?? ('#' . (int)($pipe['source_well_id'] ?? 0)))) ?></div>
        </div>
        <span class="badge <?= $statusBadgeClass ?>"><?= t($statusLabelKey) ?></span>
    </div>

    <div class="pipe-stats">
        <div>
            <div class="w-stat-lbl"><?= t('technical.pipe_type') ?></div>
            <div class="w-stat-val"><?= t('logistics.pipeline.type_' . $pipeType) ?></div>
        </div>
        <div>
            <div class="w-stat-lbl"><?= t('technical.pipe_capacity') ?></div>
            <div class="w-stat-val c-blue"><?= number_format($capacityBbl, 1, ',', ' ') ?> <?= t('common.bbl_h') ?></div>
        </div>
        <div>
            <div class="w-stat-lbl"><?= t('technical.pipe_nominal_capacity') ?></div>
            <div class="w-stat-val c-gold"><?= number_format($nominalBbl, 1, ',', ' ') ?> <?= t('common.bbl_h') ?></div>
        </div>
        <div>
            <div class="w-stat-lbl"><?= t('technical.pipe_losses') ?></div>
            <div class="w-stat-val c-bad"><?= number_format($lossPct, 2, ',', ' ') ?>%</div>
        </div>
        <div>
            <div class="w-stat-lbl"><?= t('technical.pipe_tick_cost') ?></div>
            <div class="w-stat-val"><?= number_format($tickCost, 2, ',', ' ') ?> PLN</div>
        </div>
        <div>
            <div class="w-stat-lbl"><?= t('technical.pipe_flow_cost') ?></div>
            <div class="w-stat-val"><?= number_format($flowCost, 2, ',', ' ') ?> PLN/<?= t('common.bbl') ?></div>
        </div>
        <div>
            <div class="w-stat-lbl"><?= t('technical.pipe_build_cost') ?></div>
            <div class="w-stat-val"><?= number_format($buildCost, 2, ',', ' ') ?> PLN</div>
        </div>
        <div>
            <div class="w-stat-lbl"><?= t('technical.pipe_condition') ?></div>
            <div class="w-stat-val <?= $conditionPct < 40 ? 'c-bad' : ($conditionPct < 70 ? 'c-warn' : 'c-green') ?>"><?= number_format($conditionPct, 1, ',', ' ') ?>%</div>
        </div>
    </div>

    <div class="pipe-footer"><?= t('technical.pipe_footer', ['cond' => number_format($conditionPct, 1, ',', ' ')]) ?></div>
</div>
<?php endforeach ?>
<?php endif ?>
