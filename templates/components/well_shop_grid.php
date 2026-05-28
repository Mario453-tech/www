<?php
if (class_exists('GameLog', false)) {
    GameLog::step('component/well_shop_grid', 'render', 1,
        'available=' . count($availableWells ?? []));
    if (!isset($availableWells)) {
        GameLog::warn('component/well_shop_grid', 'Missing $availableWells');
    }
    if (!isset($playerCanAfford) || !isset($playerWellCount)) {
        GameLog::warn('component/well_shop_grid', 'Missing $playerCanAfford or $playerWellCount');
    }
}
?>
<?php if (empty($availableWells)): ?>
    <p class="empty-state"><?= t('well_shop.no_wells') ?></p>
<?php else: ?>
    <div class="well-shop-grid">
        <?php foreach ($availableWells as $well): ?>
            <div class="well-shop-card">
                <div class="well-shop-header">
                    <h3 class="well-shop-title">
                        <?= htmlspecialchars($well['location_name']) ?>
                    </h3>
                </div>

                <div class="well-shop-stats">
                    <div class="well-shop-stat">
                        <span class="well-shop-label"><?= t('well_shop.label_production') ?>:</span>
                        <span class="well-shop-value"><?= $well['base_production'] ?>/h</span>
                    </div>
                    <div class="well-shop-stat">
                        <span class="well-shop-label"><?= t('well_shop.label_upkeep') ?>:</span>
                        <span class="well-shop-value"><?= $well['upkeep_cost'] ?>/h</span>
                    </div>
                    <div class="well-shop-stat">
                        <span class="well-shop-label"><?= t('well_shop.label_price') ?>:</span>
                        <span class="well-shop-value money"><?= number_format($well['base_cost']) ?></span>
                    </div>
                </div>

                <?php if ($well['description']): ?>
                    <p class="well-shop-description">
                        <?= htmlspecialchars($well['description']) ?>
                    </p>
                <?php endif ?>

                <div class="well-shop-actions">
                    <?php if ($playerWellCount >= 5): ?>
                        <button class="btn btn-secondary" disabled>
                            <?= t('well_shop.limit_reached') ?>
                        </button>
                    <?php elseif (!$playerCanAfford): ?>
                        <button class="btn btn-secondary" disabled>
                            <?= t('well_shop.no_cash') ?>
                        </button>
                    <?php else: ?>
                        <form method="post" class="well-shop-form">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="action" value="buy_well">
                            <input type="hidden" name="well_id" value="<?= $well['id'] ?>">
                            <button type="submit" class="btn btn-success">
                                <?= t('well_shop.btn_buy') ?>
                            </button>
                        </form>
                    <?php endif ?>
                </div>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>