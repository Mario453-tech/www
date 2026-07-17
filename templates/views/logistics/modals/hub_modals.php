<!--  -->
<!-- MODAL: Moje odwierty na hubie                                          -->
<!--  -->
<div id="hub-wells-modal" class="logistics-modal-overlay" hidden data-hub-modal>
    <div class="logistics-modal-box">
        <div class="logistics-modal-hdr">
            <span id="hub-wells-modal-title"> <?= t('logistics.hub.wells_modal_title') ?></span>
            <button class="logistics-modal-close" type="button" data-hub-modal-close="hub-wells-modal"></button>
        </div>
        <div id="hub-wells-modal-body" class="logistics-loading"><?= t('logistics.loading') ?></div>
        <div class="logistics-modal-footer">
            <button class="btn btn-secondary btn-sm" type="button" data-hub-modal-close="hub-wells-modal">
                <?= t('logistics.cancel') ?>
            </button>
        </div>
    </div>
</div>

<!--  -->
<!-- MODAL: Przenies odwiert do innego huba                                 -->
<!--  -->
<div id="hub-transfer-modal" class="logistics-modal-overlay" hidden data-hub-modal>
    <div class="logistics-modal-box">
        <div class="logistics-modal-hdr">
            <span id="hub-transfer-modal-title"> <?= t('logistics.hub.transfer_modal_title') ?></span>
            <button class="logistics-modal-close" type="button" data-hub-modal-close="hub-transfer-modal"></button>
        </div>
        <div id="hub-transfer-modal-body" class="logistics-loading"><?= t('logistics.loading') ?></div>
        <div class="logistics-modal-footer">
            <button class="btn btn-secondary btn-sm" type="button" data-hub-modal-close="hub-transfer-modal">
                <?= t('logistics.cancel') ?>
            </button>
        </div>
    </div>
</div>

<!--  -->
<!-- MODAL: Uniwersalny dialog (komunikaty + potwierdzenia)                  -->
<!--  -->

<!-- MODAL: Przypisz odwiert do huba                                        -->
<!--  -->
<div id="hub-assign-modal" class="logistics-modal-overlay" hidden data-hub-modal>
    <div class="logistics-modal-box">
        <div class="logistics-modal-hdr">
            <span> <?= t('logistics.hub.avail_title') ?></span>
            <button class="logistics-modal-close" type="button" data-hub-modal-close="hub-assign-modal"></button>
        </div>
        <div id="hub-assign-modal-body" class="logistics-loading"><?= t('logistics.loading') ?></div>
        <div class="logistics-modal-footer">
            <button class="btn btn-secondary btn-sm" type="button" data-hub-modal-close="hub-assign-modal">
                <?= t('logistics.cancel') ?>
            </button>
        </div>
    </div>
</div>

<!-- Hub staffing modal. -->
<!-- PL: Modal obsady huba. -->
<div id="hub-staffing-modal" class="logistics-modal-overlay" hidden data-hub-modal>
    <div class="logistics-modal-box logistics-modal-box--staffing">
        <div class="logistics-modal-hdr">
            <span id="hub-staffing-modal-title"><?= t('logistics.hub.staffing.modal_title') ?></span>
            <button class="logistics-modal-close" type="button" data-hub-modal-close="hub-staffing-modal" aria-label="<?= htmlspecialchars(t('common.close'), ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars(t('common.close'), ENT_QUOTES, 'UTF-8') ?>">&times;</button>
        </div>
        <div id="hub-staffing-modal-body" class="logistics-loading"><?= t('logistics.loading') ?></div>
        <div class="logistics-modal-footer">
            <button class="btn btn-secondary btn-sm" type="button" data-hub-modal-close="hub-staffing-modal">
                <?= t('logistics.cancel') ?>
            </button>
        </div>
    </div>
</div>

<!--  -->
<!-- MODAL: Kup nowy hub                                                     -->
<!--  -->
<div id="hub-buy-new-modal" class="logistics-modal-overlay" hidden data-hub-modal>
    <div class="logistics-modal-box">
        <div class="logistics-modal-hdr">
            <span><?= t('logistics.hub.market_buy_new_title') ?></span>
            <button class="logistics-modal-close" type="button" data-hub-modal-close="hub-buy-new-modal"></button>
        </div>
        <?php if (empty($playerHubRegions)): ?>
        <div class="logistics-modal-body">
            <div class="logistics-empty"><?= t('logistics.hub.avail_no_regions') ?></div>
        </div>
        <div class="logistics-modal-footer">
            <button class="btn btn-secondary btn-sm" type="button" data-hub-modal-close="hub-buy-new-modal">
                <?= t('logistics.cancel') ?>
            </button>
        </div>
        <?php else: ?>
        <form id="hub-buy-new-form" class="logistics-modal-body" data-hub-buy-form>
            <label class="logistics-form-field">
                <span><?= t('logistics.hub.market_field_name') ?></span>
                <input type="text" name="name" maxlength="120" required
                       placeholder="<?= htmlspecialchars(t('logistics.hub.market_field_name_ph'), ENT_QUOTES) ?>">
            </label>

            <label class="logistics-form-field">
                <span><?= t('logistics.hub.market_field_region') ?></span>
                <select name="region_id" required>
                    <?php foreach ($playerHubRegions as $reg): ?>
                    <option value="<?= (int)$reg['id'] ?>"><?= htmlspecialchars($reg['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </label>

            <label class="logistics-form-field">
                <span><?= t('logistics.hub.market_field_zone') ?></span>
                <input type="text" name="zone_key" maxlength="16"
                       placeholder="<?= htmlspecialchars(t('logistics.hub.market_field_zone_ph'), ENT_QUOTES) ?>">
            </label>

            <div class="logistics-form-field">
                <span><?= t('logistics.hub.market_field_type') ?></span>
                <div class="logistics-modes">
                    <?php foreach ($hubTypeOptions as $i => $opt): ?>
                    <label class="logistics-mode-card<?= $i === 0 ? ' selected' : '' ?>">
                        <input type="radio" name="hub_type" value="<?= htmlspecialchars($opt['key']) ?>"
                               data-build-cost="<?= number_format($opt['build_cost'], 2, '.', '') ?>"
                               data-hub-buy-type <?= $i === 0 ? 'checked' : '' ?>>
                        <div class="logistics-mode-name"><?= t('logistics.hub.type_' . $opt['key']) ?></div>
                        <div class="logistics-mode-desc">
                            <?= number_format($opt['build_cost'], 0, ',', ' ') ?> <?= $currencyLabel ?>
                            &middot; <?= (int)$opt['slot_limit'] ?> <?= t('logistics.hub.col_slots') ?>
                        </div>
                    </label>
                    <?php endforeach ?>
                </div>
            </div>
        </form>
        <div class="logistics-modal-footer">
            <button class="btn btn-secondary btn-sm" type="button" data-hub-modal-close="hub-buy-new-modal">
                <?= t('logistics.cancel') ?>
            </button>
            <button class="btn btn-primary btn-sm" type="submit" form="hub-buy-new-form" id="hub-buy-new-submit">
                <?= t('logistics.hub.market_btn_buy_new_confirm') ?>
            </button>
        </div>
        <?php endif ?>
    </div>
