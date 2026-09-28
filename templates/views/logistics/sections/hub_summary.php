<?php
$hubCritical = 0;
$hubWatch = 0;
foreach ($hubCards as $hubCard) {
    $hubState = (string)($hubCard['hub']['status'] ?? '');
    $hubCondition = (float)($hubCard['hub']['condition_pct'] ?? 100);
    $hubLoad = (float)($hubCard['last_stats']['load_pct'] ?? 0);
    if ($hubState === 'critical' || $hubCondition <= 20) {
        $hubCritical++;
    } elseif ($hubCondition < 60 || $hubLoad > 80) {
        $hubWatch++;
    }
}
?>
<div class="logistics-hub-summary" aria-label="<?= htmlspecialchars(t('logistics.design.my_hubs'), ENT_QUOTES, 'UTF-8') ?>">
    <div><span><?= t('logistics.design.hubs_total') ?></span><strong><?= count($hubCards) ?></strong></div>
    <div><span><?= t('logistics.design.hubs_critical') ?></span><strong class="c-bad"><?= $hubCritical ?></strong></div>
    <div><span><?= t('logistics.design.hubs_watch') ?></span><strong class="c-warn"><?= $hubWatch ?></strong></div>
    <div><span><?= t('logistics.insight_pill_unassigned') ?></span><strong class="<?= (int)$unassignedTotal > 0 ? 'c-warn' : 'c-good' ?>"><?= (int)$unassignedTotal ?></strong></div>
</div>
