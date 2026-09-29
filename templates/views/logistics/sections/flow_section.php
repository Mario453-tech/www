<?php
// Render real logistics flow values without simulating live production.
// Pokazuj rzeczywiste wartosci przeplywu bez udawania produkcji na zywo.
$activeHubCount = count($hubCards);
$hubsInUse = count(array_filter($hubCards, static fn(array $card): bool => (int)($card['hub']['assigned_count'] ?? 0) > 0));
$activePipelineCount = count(array_filter($pipelines, static fn(array $pipeline): bool => ($pipeline['status'] ?? '') === 'active'));
$activeRouteCount = count(array_filter(['ciezarowki', 'tankowiec', 'rurociag'], static fn(string $type): bool => (int)($transportMix[$type]['count'] ?? 0) > 0));
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
            <span><?= t('logistics.design.flow_active_wells', ['count' => (int)$activeWellCount]) ?></span>
        </li>
        <li class="logistics-flow-stage logistics-flow-stage--transport">
            <span class="logistics-flow-stage-icon" aria-hidden="true"></span>
            <strong><?= t('logistics.flow_step_transport') ?></strong>
            <span><?= t('logistics.design.flow_routes', ['count' => $activeRouteCount]) ?></span>
        </li>
        <li class="logistics-flow-stage logistics-flow-stage--hubs">
            <span class="logistics-flow-stage-icon" aria-hidden="true"></span>
            <strong><?= t('logistics.flow_step_hubs') ?></strong>
            <span><?= t('logistics.design.flow_hubs_usage', ['used' => $hubsInUse, 'total' => $activeHubCount]) ?></span>
        </li>
        <li class="logistics-flow-stage logistics-flow-stage--pipes">
            <span class="logistics-flow-stage-icon" aria-hidden="true"></span>
            <strong><?= t('logistics.flow_step_transport2') ?></strong>
            <span><?= t('logistics.design.flow_active_pipelines', ['count' => $activePipelineCount]) ?></span>
        </li>
        <li class="logistics-flow-stage logistics-flow-stage--storage">
            <span class="logistics-flow-stage-icon" aria-hidden="true"></span>
            <strong><?= t('logistics.flow_step_storage') ?></strong>
            <span><?= number_format($storageBbl, 0, ',', ' ') ?> <?= t('common.bbl') ?></span>
        </li>
    </ol>
</section>
