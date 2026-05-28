<?php
if (class_exists('GameLog', false)) {
    GameLog::step('component/my_offers_table', 'render', 1,
        'offers=' . count($myOffers ?? []));
    if (!isset($myOffers)) {
        GameLog::warn('component/my_offers_table', 'Brak zmiennej $myOffers');
    }
}
?>
<?php if (empty($myOffers)): ?>
    <p class="empty-message"><?= t('market.no_active_offers') ?></p>
<?php else: ?>
    <div class="my-offers-list">
        <div class="my-offers-head">
            <span><?= t('market.col_amount') ?></span>
            <span><?= t('market.col_limit_price') ?></span>
            <span><?= t('market.col_status') ?></span>
            <span><?= t('market.col_created') ?></span>
            <span><?= t('market.col_actions') ?></span>
        </div>
        <?php foreach ($myOffers as $offer): ?>
        <div class="my-offers-row">
            <span> <?= number_format($offer['amount']) ?> bary³ek</span>
            <span class="money"><?= number_format($offer['limit_price']) ?></span>
            <span>
                <?php if ($offer['status'] === 'pending'): ?>
                <span class="status-pending"> <?= t('market.offer_status_pending') ?></span>
                <?php elseif ($offer['status'] === 'completed'): ?>
                <span class="status-completed"> <?= t('market.offer_status_completed') ?></span>
                <?php else: ?>
                <span class="status-cancelled"> <?= t('market.offer_status_cancelled') ?></span>
                <?php endif ?>
            </span>
            <span class="muted"><?= date('d.m H:i', strtotime($offer['created_at'])) ?></span>
            <span class="offer-actions">
                <?php if ($offer['status'] === 'pending' && $offer['editable']): ?>
                <button class="btn btn-sm btn-primary"
                        onclick="editOffer(<?= (int)$offer['id'] ?>, <?= (int)$offer['limit_price'] ?>)">
                     <?= t('market.offer_btn_edit') ?>
                </button>
                <button class="btn btn-sm btn-danger"
                        onclick="cancelOffer(<?= (int)$offer['id'] ?>)">
                     <?= t('market.offer_btn_cancel') ?>
                </button>
                <?php elseif ($offer['status'] === 'pending'): ?>
                <span class="inactive-text"><?= t('market.offer_not_editable') ?></span>
                <?php else: ?>
                <span class="inactive-text"><?= t('market.offer_finished') ?></span>
                <?php endif ?>
            </span>
        </div>
        <?php endforeach ?>
    </div>

    <div class="info-box">
        <strong> <?= t('market.offers_info_title') ?></strong>
        <ul class="info-box-list">
            <li><?= t('market.offers_info_blocked') ?></li>
            <li><?= t('market.offers_info_auto') ?></li>
            <li><?= t('market.offers_info_cancel_penalty') ?></li>
        </ul>
    </div>
<?php endif ?>

<script>
function editOffer(offerId, currentPrice) {
    promptInput(<?= json_encode(t('market.offer_edit_prompt')) ?>, String(currentPrice), function (newPrice) {
        if (newPrice && parseFloat(newPrice) >= 50) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML =
                '<input type="hidden" name="action" value="edit_offer">' +
                '<input type="hidden" name="offer_id" value="' + offerId + '">' +
                '<input type="hidden" name="new_limit_price" value="' + newPrice + '">' +
                '<input type="hidden" name="csrf_token" value="' + document.querySelector('input[name="csrf_token"]').value + '">';
            document.body.appendChild(form);
            form.submit();
        } else if (newPrice) {
            alertWarning(<?= json_encode(t('market.offer_edit_min_price')) ?>);
        }
    }, { title: <?= json_encode(t('market.offer_btn_edit')) ?>, confirmLabel: <?= json_encode(t('market.offer_btn_edit')) ?> });
}

function cancelOffer(offerId) {
    confirmAction(<?= json_encode(t('market.offer_cancel_confirm')) ?>, function () {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML =
            '<input type="hidden" name="action" value="cancel_offer">' +
            '<input type="hidden" name="offer_id" value="' + offerId + '">' +
            '<input type="hidden" name="csrf_token" value="' + document.querySelector('input[name="csrf_token"]').value + '">';
        document.body.appendChild(form);
        form.submit();
    }, { type: 'danger', confirmLabel: <?= json_encode(t('market.offer_btn_cancel')) ?> });
}
</script>
