<?php
if (class_exists('GameLog', false)) {
    GameLog::step('component/price_chart', 'render', 1,
        'points=' . count($priceHistory ?? []));
    if (!isset($priceHistory)) {
        GameLog::warn('component/price_chart', 'Brak zmiennej $priceHistory');
    }
}
?>
<?php if (!empty($priceHistory)): ?>
    <?php
    try {
        $maxPrice = max(array_column($priceHistory, 'price'));
        $minPrice = min(array_column($priceHistory, 'price'));
        $range    = $maxPrice - $minPrice ?: 1;
        if (class_exists('GameLog', false)) {
            GameLog::info('component/price_chart', 'Skala wykresu', [
                'min' => $minPrice, 'max' => $maxPrice, 'points' => count($priceHistory),
            ]);
        }
    } catch (Throwable $e) {
        if (class_exists('GameLog', false)) {
            GameLog::error('component/price_chart', 'Błąd obliczania skali wykresu', $e, [
                'points' => count($priceHistory),
            ]);
        }
        return;
    }
    ?>
    <div class="price-chart-wrap">
        <h3 class="price-chart-title"><?= t('market.chart_title', ['hours' => $chartHours ?? 6]) ?></h3>
        <div class="price-chart-bars">
            <?php foreach ($priceHistory as $point):
                $height = (($point['price'] - $minPrice) / $range) * 80 + 20;
                $time   = date('H:i', strtotime($point['created_at']));
            ?>
            <div class="price-chart-bar" style="height:<?= $height ?>px"
                 title="<?= $point['price'] ?> o <?= $time ?>"></div>
            <?php endforeach ?>
        </div>
        <div class="price-chart-legend">
            <span><?= t('market.chart_min') ?>: <?= $minPrice ?></span>
            <span><?= t('market.chart_max') ?>: <?= $maxPrice ?></span>
        </div>
    </div>
<?php endif ?>