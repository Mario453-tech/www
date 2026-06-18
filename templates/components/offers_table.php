<?php
$barrelLabel = t('common.bbl');
if (class_exists('GameLog', false)) {
    if (!isset($offers)) {
        GameLog::warn('component/offers_table', 'Brak zmiennej $offers');
    } else {
        GameLog::dbResult('component/offers_table', 'render', count($offers ?? []));
    }
}
?>
<?php if (!empty($offers)): ?>
<div class="offers-list">
    <div class="offers-list-head">
        <span><?= t('market.col_amount') ?></span>
        <span><?= t('market.col_limit_price') ?></span>
        <span><?= t('market.col_created') ?></span>
    </div>
    <?php foreach ($offers as $offer): ?>
    <div class="offers-list-row">
        <span><?= (int)$offer['amount'] ?> <?= $barrelLabel ?></span>
        <span class="money"><?= number_format($offer['limit_price']) ?></span>
        <span class="muted"><?= date('d.m.Y H:i', strtotime($offer['created_at'])) ?></span>
    </div>
    <?php endforeach ?>
</div>
<?php endif ?>
