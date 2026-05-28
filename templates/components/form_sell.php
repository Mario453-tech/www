<?php
if (class_exists('GameLog', false)) {
    GameLog::step('component/form_sell', 'render', 1,
        'action=' . ($formAction ?? '?') . ' max=' . ($maxAmount ?? '?'));
    if (!isset($formAction) || !isset($maxAmount)) {
        GameLog::warn('component/form_sell', 'Brak $formAction lub $maxAmount');
    }
}
$maxAmount      = (int)($maxAmount ?? 0);
$formAction     = $formAction     ?? '';
$buttonClass    = $buttonClass    ?? 'btn-primary';
$buttonLabel    = $buttonLabel    ?? t('market.btn_sell_instant');
$showLimitPrice = $showLimitPrice ?? false;
$currentPrice   = $currentPrice   ?? 0;
?>
<?php if ($maxAmount > 0): ?>
    <form method="post" class="form-sell">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="<?= htmlspecialchars($formAction) ?>">
        <div class="form-sell-field">
            <label class="form-sell-label" for="fs_amount_<?= $formAction ?>"><?= t('market.form_amount_label') ?></label>
            <input type="number" id="fs_amount_<?= $formAction ?>" name="amount"
                   class="form-sell-input"
                   min="1" max="<?= $maxAmount ?>"
                   value="<?= min(10, $maxAmount) ?>" required>
        </div>
        <?php if ($showLimitPrice): ?>
        <div class="form-sell-field">
            <label class="form-sell-label" for="fs_limit_<?= $formAction ?>"><?= t('market.form_limit_label') ?></label>
            <input type="number" id="fs_limit_<?= $formAction ?>" name="limit_price"
                   class="form-sell-input"
                   min="50" max="500"
                   value="<?= max(50, (int)$currentPrice + 10) ?>" required>
        </div>
        <?php endif ?>
        <button type="submit" class="btn <?= htmlspecialchars($buttonClass) ?>"><?= $buttonLabel ?></button>
    </form>
<?php endif ?>
