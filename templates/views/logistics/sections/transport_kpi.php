    <?php
        $transportedWellCount = count(array_filter($wells, static fn($well) =>
            is_array($well)
            && ($well['status'] ?? '') === 'active'
            && !in_array((string)($well['transport'] ?? ''), ['', 'nieustawiony'], true)
        ));
        $roadWellCount = (int)($transportMix['ciezarowki']['count'] ?? 0);
        $marineWellCount = (int)($transportMix['tankowiec']['count'] ?? 0);
        $largestLossType = null;
        $largestLossValue = 0.0;
        foreach (['ciezarowki', 'tankowiec', 'rurociag'] as $transportType) {
            $typeLoss = (float)($transportMix[$transportType]['loss'] ?? 0.0);
            if ($typeLoss > $largestLossValue) {
                $largestLossValue = $typeLoss;
                $largestLossType = $transportType;
            }
        }
    ?>
    <div class="logistics-transport-kpi-grid" aria-label="<?= htmlspecialchars(t('logistics.design.transport_kpi_aria'), ENT_QUOTES, 'UTF-8') ?>">
        <div class="logistics-transport-kpi">
            <span><?= t('logistics.design.transport_wells') ?></span>
            <strong><?= $transportedWellCount ?></strong>
            <small><?= t('logistics.design.transport_wells_summary', ['road' => $roadWellCount, 'marine' => $marineWellCount]) ?></small>
        </div>
        <div class="logistics-transport-kpi logistics-transport-kpi--loss">
            <span><?= t('logistics.design.transport_losses') ?></span>
            <strong><?= number_format((float)($totals['loss'] ?? 0), 1, ',', ' ') ?> <em><?= t('common.bbl_h') ?></em></strong>
            <small><?= $largestLossType === null ? t('logistics.design.no_losses') : t('logistics.type_' . $largestLossType) ?></small>
        </div>
        <div class="logistics-transport-kpi">
            <span><?= t('logistics.design.marine_deliveries') ?></span>
            <strong><?= count($marineDeliveries ?? []) ?></strong>
            <small><?= t('logistics.design.marine_in_transit', ['bbl' => number_format((float)($marineInTransitBbl ?? 0), 1, ',', ' ')]) ?></small>
        </div>
    </div>
