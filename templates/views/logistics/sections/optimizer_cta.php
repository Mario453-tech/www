    <section class="logistics-panel" aria-labelledby="logistics-optimizer-heading">
        <div class="logistics-panel-head">
            <h3 id="logistics-optimizer-heading"><?= t('logistics.optimizer_title') ?></h3>
            <span><?= t('logistics.optimizer_desc') ?></span>
        </div>
        <div class="logistics-optimizer-bar logistics-optimizer-bar--page">
            <div class="logistics-optimizer-info">
                <span class="logistics-icon"></span>
                <div>
                    <div class="logistics-title"><?= t('logistics.optimizer_card_title') ?></div>
                    <div class="logistics-desc"><?= t('logistics.optimizer_card_desc') ?></div>
                </div>
            </div>
            <button class="btn btn-primary btn-sm logistics-btn" type="button" data-logistics-open id="btn-logistics-open">
                 <?= t('logistics.optimizer_btn') ?>
            </button>
        </div>
    </section>
