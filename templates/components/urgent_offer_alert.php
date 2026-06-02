<?php
// Sprawdź czy są oferty bliskie realizacji
if (class_exists('GameLog', false)) {
    GameLog::step('component/urgent_offer_alert', 'render', 1,
        'offers=' . count($myOffers ?? []) . ' price=' . ($marketData['current_price'] ?? '?'));
}

if (!isset($myOffers) || !isset($marketData)) {
    if (class_exists('GameLog', false)) {
        GameLog::warn('component/urgent_offer_alert', 'Missing $myOffers or $marketData');
    }
    return;
}

$urgentOffers = [];
try {
    foreach ($myOffers as $offer) {
        if ($offer['status'] === 'pending') {
            $priceDiff = $marketData['current_price'] - $offer['limit_price'];
            if ($priceDiff >= -5 && $priceDiff <= 5) {
                $urgentOffers[] = [
                    'offer'      => $offer,
                    'diff'       => $priceDiff,
                    'percentage' => $offer['limit_price'] > 0 ? round(($marketData['current_price'] / $offer['limit_price'] - 1) * 100, 1) : 0,
                ];
            }
        }
    }
    if (!empty($urgentOffers) && class_exists('GameLog', false)) {
        GameLog::info('component/urgent_offer_alert', 'Offers near fulfillment', [
            'count' => count($urgentOffers),
        ]);
    }
} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('component/urgent_offer_alert', 'Error filtering offers', $e);
    }
    return;
}

if (!empty($urgentOffers)): 
?>
    <div class="urgent-alert-overlay" id="urgentAlert">
        <div class="urgent-alert-box">
            <div class="urgent-alert-header">
                <span class="urgent-alert-icon"></span>
                <span class="urgent-alert-title"><?= t('urgent_offer.title') ?></span>
                <button class="urgent-alert-close" onclick="closeUrgentAlert()">×</button>
            </div>
            <div class="urgent-alert-content">
                <?php if (count($urgentOffers) === 1):
                    $urgent = $urgentOffers[0];
                ?>
                    <h3> <?= t('urgent_offer.single_heading') ?></h3>
                    <p><strong><?= number_format($urgent['offer']['amount']) ?></strong> <?= t('urgent_offer.barrels') ?></p>
                    <p><?= t('urgent_offer.limit_price') ?> <span class="money"><?= number_format($urgent['offer']['limit_price']) ?></span></p>
                    <p><?= t('urgent_offer.current_price') ?> <span class="money"><?= number_format($marketData['current_price']) ?></span></p>
                    <?php if ($urgent['diff'] >= 0): ?>
                        <p class="urgent-price-reached"> <?= t('market.urgent_price_reached') ?></p>
                    <?php else: ?>
                        <p class="urgent-price-close"> <?= sprintf(t('market.urgent_diff'), abs($urgent['diff'])) ?></p>
                        <p class="urgent-price-close"> <?= sprintf(t('market.urgent_pct'), abs($urgent['percentage'])) ?></p>
                    <?php endif ?>
                <?php else: ?>
                    <h3> <?= t('urgent_offer.multi_heading', ['n' => count($urgentOffers)]) ?></h3>
                    <?php foreach ($urgentOffers as $urgent): ?>
                        <div class="urgent-offer-item">
                            <strong><?= number_format($urgent['offer']['amount']) ?></strong> <?= t('urgent_offer.barrels_dash') ?>
                            <?= t('urgent_offer.limit') ?> <span class="money"><?= number_format($urgent['offer']['limit_price']) ?></span>
                            <?php if ($urgent['diff'] >= 0): ?>
                                <span class="status-success"> <?= t('urgent_offer.reached') ?></span>
                            <?php else: ?>
                                <span class="status-warning"> <?= t('urgent_offer.missing', ['n' => abs($urgent['diff'])]) ?></span>
                            <?php endif ?>
                        </div>
                    <?php endforeach ?>
                <?php endif ?>
            </div>
            <div class="urgent-alert-actions">
                <button class="btn btn-danger" onclick="window.location.href='market_offers.php'">
                     <?= t('urgent_offer.manage_btn') ?>
                </button>
                <button class="btn btn-primary" onclick="closeUrgentAlert()">
                     <?= t('urgent_offer.dismiss_btn') ?>
                </button>
            </div>
        </div>
    </div>
    
    <script src="/assets/js/urgent_offer_alert.js"></script>
<?php endif ?>