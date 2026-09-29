    <section class="logistics-panel" aria-labelledby="logistics-overview-heading">
        <div class="logistics-panel-head">
            <h3 id="logistics-overview-heading"><?= t('logistics.overview_title') ?></h3>
            <span><?= t('logistics.overview_subtitle') ?></span>
        </div>
        <div class="logistics-table logistics-table--mix" role="table" aria-label="<?= htmlspecialchars(t('logistics.overview_title'), ENT_QUOTES, 'UTF-8') ?>">
            <div class="logistics-table-head" role="row">
                <span role="columnheader"><?= t('logistics.col_transport') ?></span>
                <span role="columnheader"><?= t('logistics.kpi_wells') ?></span>
                <span role="columnheader"><?= t('logistics.label_flow') ?></span>
                <span role="columnheader"><?= t('logistics.label_loss') ?></span>
                <span role="columnheader"><?= t('logistics.label_cost') ?></span>
            </div>
            <?php foreach (['ciezarowki', 'tankowiec', 'rurociag'] as $type):
                $row = $transportMix[$type] ?? ['count' => 0, 'transported' => 0, 'loss' => 0, 'cost' => 0];
            ?>
            <div class="logistics-table-row" role="row" data-transport="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>">
                <strong role="cell"><?= t('logistics.type_' . $type) ?></strong>
                <span role="cell" data-label="<?= htmlspecialchars(t('logistics.kpi_wells'), ENT_QUOTES, 'UTF-8') ?>"><?= (int)$row['count'] ?></span>
                <span role="cell" data-label="<?= htmlspecialchars(t('logistics.label_flow'), ENT_QUOTES, 'UTF-8') ?>"><?= number_format((float)$row['transported'], 1, ',', ' ') ?> <?= t('common.bbl_h') ?></span>
                <span role="cell" class="<?= (float)$row['loss'] > 0 ? 'c-warn' : 'c-good' ?>" data-label="<?= htmlspecialchars(t('logistics.label_loss'), ENT_QUOTES, 'UTF-8') ?>"><?= number_format((float)$row['loss'], 1, ',', ' ') ?> <?= t('common.bbl_h') ?></span>
                <span role="cell" data-label="<?= htmlspecialchars(t('logistics.label_cost'), ENT_QUOTES, 'UTF-8') ?>"><?= number_format((float)$row['cost'], 2, ',', ' ') ?> <?= $currencyLabel ?>/h</span>
            </div>
            <?php endforeach ?>
        </div>
        <?php if ((int)($transportMix['nieustawiony']['count'] ?? 0) > 0): ?>
            <p class="logistics-transport-unassigned"><?= t('logistics.design.unassigned_transport', ['count' => (int)$transportMix['nieustawiony']['count']]) ?></p>
        <?php endif ?>
    </section>
