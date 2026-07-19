<!-- Pipeline staffing modal. -->
<!-- PL: Modal obsady rurociagu. -->
<div id="pipeline-staffing-modal" class="logistics-modal-overlay" hidden data-hub-modal>
    <div class="logistics-modal-box pipeline-staffing-modal-box">
        <div class="logistics-modal-hdr">
            <span id="pipeline-staffing-modal-title"><?= t('logistics.pipeline.staffing.modal_title') ?></span>
            <button class="logistics-modal-close" type="button" data-hub-modal-close="pipeline-staffing-modal"
                    aria-label="<?= htmlspecialchars(t('common.close'), ENT_QUOTES, 'UTF-8') ?>"
                    title="<?= htmlspecialchars(t('common.close'), ENT_QUOTES, 'UTF-8') ?>">&times;</button>
        </div>
        <div id="pipeline-staffing-modal-body" class="pipeline-staffing-modal-body"></div>
        <div class="logistics-modal-footer">
            <button class="btn btn-secondary btn-sm" type="button" data-hub-modal-close="pipeline-staffing-modal">
                <?= t('logistics.cancel') ?>
            </button>
        </div>
    </div>
</div>
