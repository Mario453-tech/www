<?php
// Render real logistics flow values without simulating live production.
// Pokazuj rzeczywiste wartosci przeplywu bez udawania produkcji na zywo.
$activeHubCount = count($hubCards);
?>
<section class="logistics-flow-section" aria-labelledby="logistics-flow-heading">
    <div class="logistics-flow-header">
        <h3 id="logistics-flow-heading"><?= t('logistics.flow_title') ?></h3>
        <span class="logistics-flow-sub"><?= t('logistics.flow_subtitle') ?></span>
    </div>
    <ol class="logistics-flow-stages">
        <li class="logistics-flow-stage logistics-flow-stage--wells">
            <span class="logistics-flow-stage-icon" aria-hidden="true"></span>
            <strong><?= t('logistics.flow_step_wells') ?></strong>
            <span><?= count($wells) ?> <?= t('logistics.flow_active') ?></span>
            <small><?= number_format($totalTransported + $totalLoss, 0, ',', ' ') ?> <?= t('common.bbl_h') ?></small>
        </li>
        <li class="logistics-flow-stage logistics-flow-stage--transport">
            <span class="logistics-flow-stage-icon" aria-hidden="true"></span>
            <strong><?= t('logistics.flow_step_transport') ?></strong>
            <span><?= (int)$activeRoadTripsTotal ?> <?= t('logistics.flow_active') ?></span>
            <small class="<?= $totalLoss > 0 ? 'c-warn' : 'c-good' ?>"><?= number_format($totalLoss, 1, ',', ' ') ?> <?= t('common.bbl_h') ?> <?= t('logistics.flow_loss') ?></small>
        </li>
        <li class="logistics-flow-stage logistics-flow-stage--hubs">
            <span class="logistics-flow-stage-icon" aria-hidden="true"></span>
            <strong><?= t('logistics.flow_step_hubs') ?></strong>
            <span><?= $activeHubCount ?> <?= t('logistics.flow_active') ?></span>
            <small><?= (int)($unassignedTotal ?? 0) ?> <?= t('logistics.insight_pill_unassigned') ?></small>
        </li>
        <li class="logistics-flow-stage logistics-flow-stage--pipes">
            <span class="logistics-flow-stage-icon" aria-hidden="true"></span>
            <strong><?= t('logistics.flow_step_transport2') ?></strong>
            <span><?= (int)($pipelineSummary['total'] ?? 0) ?> <?= t('logistics.flow_pipelines') ?></span>
            <small><?= (int)($pipelineSummary['needs_service'] ?? 0) ?> <?= t('logistics.pipeline.pill_service') ?></small>
        </li>
        <li class="logistics-flow-stage logistics-flow-stage--storage">
            <span class="logistics-flow-stage-icon" aria-hidden="true"></span>
            <strong><?= t('logistics.flow_step_storage') ?></strong>
            <span><?= number_format($storageBbl, 0, ',', ' ') ?> <?= t('common.bbl') ?></span>
            <small><?= (int)$storagePct ?>% <?= t('logistics.flow_capacity') ?></small>
        </li>
    </ol>
</section>