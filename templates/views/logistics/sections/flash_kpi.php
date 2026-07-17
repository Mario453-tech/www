    <?php if (!empty($staffingFlash['error'] ?? '') || !empty($staffingFlash['success'] ?? '')): ?>
    <div
        id="hub-staffing-flash"
        class="u-hidden"
        data-type="<?= !empty($staffingFlash['error'] ?? '') ? 'error' : 'success' ?>"
        data-message="<?= htmlspecialchars((string)($staffingFlash['error'] ?? $staffingFlash['success'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
        hidden
    ></div>
    <?php endif ?>
    <section class="logistics-kpi-grid" aria-label="<?= htmlspecialchars(t('logistics.kpi_aria')) ?>">
        <div class="logistics-kpi">
            <span class="logistics-kpi-label"><?= t('logistics.kpi_efficiency') ?></span>
            <strong class="<?= $efficiency >= 90 ? 'c-good' : ($efficiency >= 75 ? 'c-warn' : 'c-bad') ?>"><?= number_format($efficiency, 1, ',', ' ') ?>%</strong>
        </div>
        <div class="logistics-kpi">
            <span class="logistics-kpi-label"><?= t('logistics.kpi_loss') ?></span>
            <strong class="<?= $lossPct >= 15 ? 'c-bad' : ($lossPct >= 8 ? 'c-warn' : 'c-good') ?>"><?= number_format((float)$totals['loss'], 1, ',', ' ') ?> <?= t('common.bbl_h') ?></strong>
        </div>
        <div class="logistics-kpi">
            <span class="logistics-kpi-label"><?= t('logistics.kpi_cost') ?></span>
            <strong><?= number_format((float)$totals['cost'], 2, ',', ' ') ?> <?= $currencyLabel ?>/h</strong>
        </div>
        <div class="logistics-kpi">
            <span class="logistics-kpi-label"><?= t('logistics.kpi_wells') ?></span>
            <strong><?= count($wells) ?></strong>
        </div>
    </section>
