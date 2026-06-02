<?php
if (class_exists('GameLog', false)) {
    GameLog::step('component/form_sell', 'render', 1,
        'storage_used=' . ($storageData['used'] ?? '?'));
    if (!isset($storageData) || !isset($marketData)) {
        GameLog::warn('component/form_sell', 'Missing $storageData or $marketData');
    }
}
?>
<?php if (($storageData['used'] ?? 0) > 0): ?>
    <div class="card">
        <h2> <?= t('form_sell.title') ?></h2>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- Sprzedaz natychmiastowa / Instant sale -->
            <div>
                <h3> <?= t('form_sell.instant_heading') ?></h3>
                <form method="post" action="<?= url('sell') ?>" style="display: grid; gap: 10px;">
                    <?= CSRF::field() ?>

                    <div>
                        <label for="amount"><?= t('form_sell.label_amount') ?></label>
                        <input type="number" id="amount" name="amount" min="1" max="<?= $storageData['used'] ?>"
                               value="<?= min(10, $storageData['used']) ?>" required>
                        <small><?= t('form_sell.available', ['val' => $storageData['used']]) ?></small>
                    </div>

                    <button type="submit" class="btn btn-success">
                         <?= t('form_sell.btn_sell_now', ['price' => number_format($marketData['current_price'])]) ?>
                    </button>
                </form>
            </div>

            <!-- Oferta z limitem / Limit offer -->
            <div>
                <h3> <?= t('form_sell.limit_heading') ?></h3>
                <form method="post" action="<?= url('market-offers') ?>" style="display: grid; gap: 10px;">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="create_offer">

                    <div>
                        <label for="offer_amount"><?= t('form_sell.label_amount') ?></label>
                        <input type="number" id="offer_amount" name="amount" min="1" max="<?= $storageData['used'] ?>"
                               value="<?= min(10, $storageData['used']) ?>" required>
                    </div>

                    <div>
                        <label for="limit_price"><?= t('form_sell.label_min_price') ?></label>
                        <input type="number" id="limit_price" name="limit_price" min="50" max="500"
                               value="<?= max(50, $marketData['current_price'] + 10) ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary">
                         <?= t('form_sell.btn_create_offer') ?>
                    </button>
                </form>
            </div>
        </div>

        <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px; font-size: 0.9rem;">
            <strong> <?= t('form_sell.options_title') ?></strong><br>
            • <?= t('form_sell.option_instant') ?><br>
            • <?= t('form_sell.option_limit') ?>
        </div>
    </div>
<?php endif ?>