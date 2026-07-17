    <section class="logistics-panel" aria-labelledby="logistics-transport-heading">
        <div class="logistics-panel-head">
            <h3 id="logistics-transport-heading"><?= t('logistics.transport_title') ?></h3>
            <span><?= t('logistics.transport_desc') ?></span>
        </div>
        <?php if (empty($wells)): ?>
            <div class="logistics-empty"><?= t('logistics.no_wells') ?></div>
        <?php else: ?>
        <div class="logistics-table">
            <div class="logistics-table-head">
                <span><?= t('logistics.col_well') ?></span>
                <span><?= t('logistics.col_type') ?></span>
                <span><?= t('logistics.col_transport') ?></span>
                <span><?= t('logistics.col_capacity') ?></span>
                <span><?= t('logistics.col_loss') ?></span>
                <span><?= t('logistics.col_cost') ?></span>
            </div>
            <?php foreach ($wells as $well): ?>
            <div class="logistics-table-row">
                <span>
                    #<?= (int)$well['id'] ?>
                    <?php if (($well['status'] ?? '') === 'servicing'): ?>
                    <span class="badge logistics-pipeline-badge logistics-pipeline-badge--servicing logistics-pipeline-badge--table"><?= t('logistics.pipeline.status_servicing') ?></span>
                    <?php endif ?>
                </span>
                <span><?= t('logistics.well_type_' . ($well['well_type'] ?? 'onshore')) ?></span>
                <span><?= t('logistics.type_' . ($well['transport'] ?? 'nieustawiony')) ?></span>
                <span><?= number_format((float)$well['capacity_pct'], 1, ',', ' ') ?>%</span>
                <span class="<?= (float)$well['loss'] > 0 ? 'c-warn' : 'c-good' ?>"><?= number_format((float)$well['loss'], 1, ',', ' ') ?> <?= t('common.bbl_h') ?></span>
                <span><?= number_format((float)$well['cost'], 2, ',', ' ') ?> <?= $currencyLabel ?>/h</span>
            </div>
            <?php endforeach ?>
        </div>
        <?php endif ?>
    </section>
