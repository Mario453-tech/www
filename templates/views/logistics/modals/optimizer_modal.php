<div id="logistics-modal" class="logistics-modal-overlay" hidden data-logistics-modal>
    <div class="logistics-modal-box">
        <div class="logistics-modal-hdr">
            <span> <?= t('logistics.modal_title') ?></span>
            <button class="logistics-modal-close" type="button" data-logistics-close title="<?= t('common.close') ?>"></button>
        </div>

        <div id="logistics-current" class="logistics-section">
            <div class="logistics-section-title"><?= t('logistics.current_title') ?></div>
            <div id="logistics-current-body" class="logistics-loading"><?= t('logistics.loading') ?></div>
        </div>

        <div class="logistics-section">
            <div class="logistics-section-title"><?= t('logistics.mode_title') ?></div>
            <div class="logistics-modes">
                <label class="logistics-mode-card selected">
                    <input type="radio" name="logistics-mode" value="balans" checked>
                    <div class="logistics-mode-icon"></div>
                    <div class="logistics-mode-name"><?= t('logistics.mode_balans') ?></div>
                    <div class="logistics-mode-desc"><?= t('logistics.mode_balans_desc') ?></div>
                </label>
                <label class="logistics-mode-card">
                    <input type="radio" name="logistics-mode" value="max_prod">
                    <div class="logistics-mode-icon"></div>
                    <div class="logistics-mode-name"><?= t('logistics.mode_maxprod') ?></div>
                    <div class="logistics-mode-desc"><?= t('logistics.mode_maxprod_desc') ?></div>
                </label>
                <label class="logistics-mode-card">
                    <input type="radio" name="logistics-mode" value="min_cost">
                    <div class="logistics-mode-icon"></div>
                    <div class="logistics-mode-name"><?= t('logistics.mode_mincost') ?></div>
                    <div class="logistics-mode-desc"><?= t('logistics.mode_mincost_desc') ?></div>
                </label>
            </div>
        </div>

        <div id="logistics-results" class="logistics-section" hidden>
            <div class="logistics-section-title"><?= t('logistics.results_title') ?></div>
            <div id="logistics-results-body"></div>
        </div>

        <div class="logistics-modal-footer">
            <button class="btn btn-secondary btn-sm" type="button" data-logistics-close><?= t('logistics.cancel') ?></button>
            <button class="btn btn-primary btn-sm" type="button" id="btn-logistics-run" data-logistics-run>
                 <?= t('logistics.run') ?>
            </button>
        </div>
    </div>
</div>
