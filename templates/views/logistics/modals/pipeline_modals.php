<!-- Pipeline buy modal / Modal zakupu rurociagu -->
<div id="pipeline-buy-modal" class="logistics-modal-overlay" hidden data-pipeline-modal>
    <div class="logistics-modal-box">
        <div class="logistics-modal-hdr">
            <span><?= t('logistics.pipeline.buy_modal_title') ?></span>
            <button class="logistics-modal-close" type="button" data-pipeline-modal-close="pipeline-buy-modal"></button>
        </div>
        <div id="pipeline-buy-modal-body" class="logistics-loading"><?= t('logistics.loading') ?></div>
        <div class="logistics-modal-footer">
            <button class="btn btn-secondary btn-sm" type="button" data-pipeline-modal-close="pipeline-buy-modal">
                <?= t('logistics.cancel') ?>
            </button>
            <button class="btn btn-primary btn-sm" type="button" id="pipeline-buy-confirm-btn" hidden data-pipeline-buy-confirm>
                <?= t('logistics.pipeline.buy_confirm_btn') ?>
            </button>
        </div>
    </div>
</div>

<!-- Pipeline action confirmation modal / Modal potwierdzenia akcji rurociagu -->
<div id="pipeline-action-modal" class="logistics-modal-overlay" hidden data-pipeline-modal>
    <div class="logistics-modal-box logistics-modal-box--sm">
        <div class="logistics-modal-hdr">
            <span class="pipeline-action-modal-title"><?= t('modal.confirm') ?></span>
            <button class="logistics-modal-close" type="button" data-pipeline-modal-close="pipeline-action-modal"
                    title="<?= t('modal.close') ?>"></button>
        </div>
        <div class="logistics-modal-body">
            <p class="pipeline-action-modal-msg"></p>
        </div>
        <div class="logistics-modal-footer">
            <button class="btn btn-secondary btn-sm" type="button" data-pipeline-modal-close="pipeline-action-modal">
                <?= t('modal.cancel') ?>
            </button>
            <button class="btn btn-primary btn-sm pipeline-action-modal-confirm" type="button" data-pipeline-action-confirm>
                <?= t('modal.confirm') ?>
            </button>
        </div>
    </div>
</div>
