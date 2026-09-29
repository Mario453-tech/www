    <?php if (!empty($staffingFlash['error'] ?? '') || !empty($staffingFlash['success'] ?? '')): ?>
    <div
        id="hub-staffing-flash"
        class="u-hidden"
        data-type="<?= !empty($staffingFlash['error'] ?? '') ? 'error' : 'success' ?>"
        data-message="<?= htmlspecialchars((string)($staffingFlash['error'] ?? $staffingFlash['success'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
        hidden
    ></div>
    <?php endif ?>
    <?php
        $hubHotspotCount = count($logisticsInsights['hub_hotspots'] ?? []);
        $pipelineServiceCount = (int)($pipelineSummary['needs_service'] ?? 0);
        $attentionCount = (int)($unassignedTotal ?? 0) + $hubHotspotCount + $pipelineServiceCount;
        $activeTransportLabels = [];
        foreach (['ciezarowki', 'tankowiec', 'rurociag'] as $transportType) {
            if ((int)($transportMix[$transportType]['count'] ?? 0) > 0) {
                $activeTransportLabels[] = t('logistics.type_' . $transportType);
            }
        }
        $maxLossWell = null;
        foreach ($wells as $well) {
            if ($maxLossWell === null || (float)$well['loss'] > (float)$maxLossWell['loss']) {
                $maxLossWell = $well;
            }
        }
    ?>
    <section class="logistics-kpi-grid" aria-label="<?= htmlspecialchars(t('logistics.kpi_aria')) ?>">
        <div class="logistics-kpi">
            <span class="logistics-kpi-label"><?= t('logistics.kpi_efficiency') ?></span>
            <strong class="<?= $efficiency >= 90 ? 'c-good' : ($efficiency >= 75 ? 'c-warn' : 'c-bad') ?>"><?= number_format($efficiency, 1, ',', ' ') ?>%</strong>
            <small><?= t('logistics.design.active_wells', ['count' => (int)$activeWellCount]) ?></small>
        </div>
        <div class="logistics-kpi">
            <span class="logistics-kpi-label"><?= t('logistics.kpi_loss') ?></span>
            <strong class="<?= $lossPct >= 15 ? 'c-bad' : ($lossPct >= 8 ? 'c-warn' : 'c-good') ?>"><?= number_format((float)$totals['loss'], 1, ',', ' ') ?> <?= t('common.bbl_h') ?></strong>
            <small><?= $maxLossWell !== null && (float)$maxLossWell['loss'] > 0 ? t('logistics.design.largest_loss', ['id' => (int)$maxLossWell['id']]) : t('logistics.design.no_losses') ?></small>
        </div>
        <div class="logistics-kpi">
            <span class="logistics-kpi-label"><?= t('logistics.kpi_cost') ?></span>
            <strong><?= number_format((float)$totals['cost'], 2, ',', ' ') ?> <?= $currencyLabel ?>/h</strong>
            <small><?= $activeTransportLabels !== [] ? htmlspecialchars(implode(' + ', $activeTransportLabels), ENT_QUOTES, 'UTF-8') : t('logistics.design.no_transport') ?></small>
        </div>
        <div class="logistics-kpi">
            <span class="logistics-kpi-label"><?= t('logistics.design.attention') ?></span>
            <strong class="<?= $attentionCount > 0 ? 'c-bad' : 'c-good' ?>"><?= $attentionCount ?></strong>
            <small><?= t('logistics.design.attention_summary', ['wells' => (int)($unassignedTotal ?? 0), 'hubs' => $hubHotspotCount, 'pipelines' => $pipelineServiceCount]) ?></small>
        </div>
    </section>
