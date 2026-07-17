    <section class="logistics-panel" aria-labelledby="logistics-overview-heading">
        <div class="logistics-panel-head">
            <h3 id="logistics-overview-heading"><?= t('logistics.overview_title') ?></h3>
            <span><?= t('logistics.overview_subtitle') ?></span>
        </div>
        <div class="logistics-mix-grid">
            <?php foreach (['nieustawiony', 'rurociag', 'ciezarowki', 'tankowiec'] as $type):
                $row = $transportMix[$type] ?? ['count' => 0, 'transported' => 0, 'loss' => 0, 'cost' => 0];
            ?>
            <article class="logistics-mix-card" data-transport="<?= htmlspecialchars($type) ?>">
                <div class="logistics-mix-title"><?= t('logistics.type_' . $type) ?></div>
                <div class="logistics-mix-meta"><?= t('logistics.mix_wells', ['count' => (int)$row['count']]) ?></div>
                <div class="logistics-mix-stats">
                    <span><?= t('logistics.label_flow') ?> <strong><?= number_format((float)$row['transported'], 1, ',', ' ') ?> <?= t('common.bbl_h') ?></strong></span>
                    <span><?= t('logistics.label_loss') ?> <strong><?= number_format((float)$row['loss'], 1, ',', ' ') ?> <?= t('common.bbl_h') ?></strong></span>
                    <span><?= t('logistics.label_cost') ?> <strong><?= number_format((float)$row['cost'], 2, ',', ' ') ?> <?= $currencyLabel ?>/h</strong></span>
                </div>
            </article>
            <?php endforeach ?>
        </div>
    </section>
