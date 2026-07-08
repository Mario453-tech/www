<?php
/**
 * B2B contracts partial.
 * Widok czesciowy kontraktow B2B.
 *
 * @var string $contractsTab
 * @var bool $b2bModuleEnabled
 * @var array<string,float|int|string> $b2bConfig
 * @var array<int,array<string,mixed>> $b2bMarketOffers
 * @var array<int,array<string,mixed>> $b2bMyBuyOffers
 * @var array<int,array<string,mixed>> $b2bMySales
 * @var array<int,array<string,mixed>> $b2bHistory
 * @var array<int,array<string,mixed>> $b2bLogs
 * @var int $b2bMarketCount
 * @var int $b2bMyBuyCount
 * @var int $b2bMySalesCount
 * @var int $b2bHistoryCount
 * @var int $b2bLogsCount
 * @var int $b2bMarketPage
 * @var int $b2bMyPage
 * @var int $b2bHistoryPage
 * @var int $b2bLogsPage
 */

$b2bBaseUrl = function_exists('url') ? url('contracts') : '/contracts';
$b2bFmtBbl = static fn(float $v): string => number_format($v, 0, ',', "\xc2\xa0");
$b2bFmtMoney = static fn(float $v): string => number_format($v, 2, ',', "\xc2\xa0");
$b2bStatusLabel = static function (string $status): string {
    $key = 'contracts.b2b.status.' . $status;
    $label = tPlain($key);
    return $label !== $key ? $label : $status;
};
$b2bEventLabel = static function (string $event): string {
    $key = 'contracts.b2b.event.' . $event;
    $label = tPlain($key);
    return $label !== $key ? $label : $event;
};
$b2bPageLink = static function (string $tab, string $param, int $page) use ($b2bBaseUrl): string {
    return $b2bBaseUrl . '?tab=' . rawurlencode($tab) . '&' . rawurlencode($param) . '=' . max(1, $page);
};
$b2bPager = static function (string $tab, string $param, int $page, int $count, int $limit) use ($b2bPageLink): void {
    if ($count <= $limit) {
        return;
    }
    $maxPage = (int)ceil($count / $limit);
    ?>
    <nav class="contracts-pager" aria-label="<?= htmlspecialchars(strip_tags(t('contracts.pagination'))) ?>">
        <?php if ($page > 1): ?>
        <a class="btn btn-secondary" href="<?= htmlspecialchars($b2bPageLink($tab, $param, $page - 1), ENT_QUOTES, 'UTF-8') ?>"><?= t('contracts.prev_page') ?></a>
        <?php endif ?>
        <span><?= t('contracts.page_x_of_y', ['page' => $page, 'pages' => $maxPage]) ?></span>
        <?php if ($page < $maxPage): ?>
        <a class="btn btn-secondary" href="<?= htmlspecialchars($b2bPageLink($tab, $param, $page + 1), ENT_QUOTES, 'UTF-8') ?>"><?= t('contracts.next_page') ?></a>
        <?php endif ?>
    </nav>
    <?php
};
?>

<?php if (!$b2bModuleEnabled): ?>
<section class="card">
    <div class="contracts-notice contracts-notice--off"><?= t('contracts.b2b.module_disabled_notice') ?></div>
</section>
<?php endif ?>

<?php if ($contractsTab === 'b2b_market'): ?>
<section class="card">
    <h3><?= t('contracts.b2b.market_heading') ?></h3>
    <?php if (empty($b2bMarketOffers)): ?>
    <p class="contracts-empty"><?= t('contracts.b2b.market_empty') ?></p>
    <?php else: ?>
    <div class="contracts-grid">
        <?php foreach ($b2bMarketOffers as $offer): ?>
        <article class="contracts-card">
            <div class="contracts-card__head">
                <span class="contracts-card__name"><?= htmlspecialchars((string)($offer['buyer_name'] ?? '')) ?></span>
                <span class="contracts-badge contracts-badge--status"><?= htmlspecialchars($b2bStatusLabel((string)($offer['status'] ?? 'open'))) ?></span>
            </div>
            <div class="contracts-terms">
                <div class="contracts-term">
                    <span class="ct-label"><?= t('contracts.b2b.volume') ?></span>
                    <strong class="ct-val"><?= $b2bFmtBbl((float)$offer['total_bbl']) ?> <?= t('contracts.unit_bbl') ?></strong>
                </div>
                <div class="contracts-term">
                    <span class="ct-label"><?= t('contracts.b2b.price_per_bbl') ?></span>
                    <strong class="ct-val"><?= $b2bFmtMoney((float)$offer['price_per_bbl']) ?></strong>
                </div>
                <div class="contracts-term">
                    <span class="ct-label"><?= t('contracts.b2b.total_value') ?></span>
                    <strong class="ct-val ct-val--bonus"><?= $b2bFmtMoney((float)$offer['total_value']) ?></strong>
                </div>
                <div class="contracts-term">
                    <span class="ct-label"><?= t('contracts.b2b.expires_at') ?></span>
                    <strong class="ct-val"><?= htmlspecialchars(substr((string)$offer['expires_at'], 0, 16)) ?></strong>
                </div>
            </div>
            <form method="post" class="contracts-action-form"
                  data-confirm="<?= htmlspecialchars(tPlain('contracts.b2b.confirm_accept'), ENT_QUOTES, 'UTF-8') ?>"
                  data-confirm-label="<?= htmlspecialchars(tPlain('contracts.b2b.btn_deliver'), ENT_QUOTES, 'UTF-8') ?>">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="accept_b2b_offer">
                <input type="hidden" name="offer_id" value="<?= (int)$offer['id'] ?>">
                <button type="submit" class="btn btn-success btn-full"><?= t('contracts.b2b.btn_deliver') ?></button>
            </form>
        </article>
        <?php endforeach ?>
    </div>
    <?php $b2bPager('b2b_market', 'b2b_market_page', $b2bMarketPage, $b2bMarketCount, 12); ?>
    <?php endif ?>
</section>
<?php endif ?>

<?php if ($contractsTab === 'b2b_my'): ?>
<section class="card">
    <h3><?= t('contracts.b2b.create_heading') ?></h3>
    <form method="post" class="contracts-b2b-form"
          data-confirm="<?= htmlspecialchars(tPlain('contracts.b2b.confirm_create'), ENT_QUOTES, 'UTF-8') ?>"
          data-confirm-label="<?= htmlspecialchars(tPlain('contracts.b2b.btn_create'), ENT_QUOTES, 'UTF-8') ?>">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="create_b2b_offer">
        <label>
            <span><?= t('contracts.b2b.volume') ?></span>
            <input type="number" name="bbl" min="<?= (int)$b2bConfig['min_bbl_per_offer'] ?>" max="<?= (int)$b2bConfig['max_bbl_per_offer'] ?>" step="1" required>
        </label>
        <label>
            <span><?= t('contracts.b2b.price_per_bbl') ?></span>
            <input type="number" name="price_per_bbl" min="1" step="0.01" required>
        </label>
        <label>
            <span><?= t('contracts.b2b.expiry') ?></span>
            <select name="expires_minutes">
                <option value="60"><?= t('contracts.b2b.expiry_1h') ?></option>
                <option value="360"><?= t('contracts.b2b.expiry_6h') ?></option>
                <option value="1440" selected><?= t('contracts.b2b.expiry_24h') ?></option>
                <option value="10080"><?= t('contracts.b2b.expiry_7d') ?></option>
            </select>
        </label>
        <button type="submit" class="btn btn-success"><?= t('contracts.b2b.btn_create') ?></button>
    </form>
</section>

<section class="card">
    <h3><?= t('contracts.b2b.my_buy_heading') ?></h3>
    <?php if (empty($b2bMyBuyOffers)): ?>
    <p class="contracts-empty"><?= t('contracts.b2b.my_buy_empty') ?></p>
    <?php else: ?>
    <div class="contracts-list">
        <?php foreach ($b2bMyBuyOffers as $offer): ?>
        <div class="contracts-row contracts-row--b2b">
            <span data-label="<?= t('contracts.b2b.volume') ?>"><?= $b2bFmtBbl((float)$offer['total_bbl']) ?> <?= t('contracts.unit_bbl') ?></span>
            <span data-label="<?= t('contracts.b2b.price_per_bbl') ?>"><?= $b2bFmtMoney((float)$offer['price_per_bbl']) ?></span>
            <span data-label="<?= t('contracts.b2b.total_value') ?>"><?= $b2bFmtMoney((float)$offer['total_value']) ?></span>
            <span data-label="<?= t('contracts.status_label') ?>"><?= htmlspecialchars($b2bStatusLabel((string)$offer['status'])) ?></span>
            <span data-label="<?= t('contracts.b2b.expires_at') ?>"><?= htmlspecialchars(substr((string)$offer['expires_at'], 0, 16)) ?></span>
            <span data-label="<?= t('contracts.b2b.action') ?>">
                <?php if ((string)$offer['status'] === 'open'): ?>
                <form method="post" class="contracts-inline-form"
                      data-confirm="<?= htmlspecialchars(tPlain('contracts.b2b.confirm_cancel'), ENT_QUOTES, 'UTF-8') ?>"
                      data-confirm-type="warning"
                      data-confirm-label="<?= htmlspecialchars(tPlain('contracts.b2b.btn_cancel'), ENT_QUOTES, 'UTF-8') ?>">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="cancel_b2b_offer">
                    <input type="hidden" name="offer_id" value="<?= (int)$offer['id'] ?>">
                    <button type="submit" class="btn btn-danger"><?= t('contracts.b2b.btn_cancel') ?></button>
                </form>
                <?php endif ?>
            </span>
        </div>
        <?php endforeach ?>
    </div>
    <?php $b2bPager('b2b_my', 'b2b_my_page', $b2bMyPage, $b2bMyBuyCount, 12); ?>
    <?php endif ?>
</section>

<section class="card">
    <h3><?= t('contracts.b2b.my_sales_heading') ?></h3>
    <?php if (empty($b2bMySales)): ?>
    <p class="contracts-empty"><?= t('contracts.b2b.my_sales_empty') ?></p>
    <?php else: ?>
    <div class="contracts-list">
        <?php foreach ($b2bMySales as $offer): ?>
        <div class="contracts-row contracts-row--b2b">
            <span data-label="<?= t('contracts.b2b.buyer') ?>"><?= htmlspecialchars((string)($offer['buyer_name'] ?? '')) ?></span>
            <span data-label="<?= t('contracts.b2b.volume') ?>"><?= $b2bFmtBbl((float)$offer['total_bbl']) ?> <?= t('contracts.unit_bbl') ?></span>
            <span data-label="<?= t('contracts.b2b.total_value') ?>"><?= $b2bFmtMoney((float)$offer['total_value']) ?></span>
            <span data-label="<?= t('contracts.status_label') ?>"><?= htmlspecialchars($b2bStatusLabel((string)$offer['status'])) ?></span>
        </div>
        <?php endforeach ?>
    </div>
    <?php $b2bPager('b2b_my', 'b2b_my_page', $b2bMyPage, $b2bMySalesCount, 12); ?>
    <?php endif ?>
</section>
<?php endif ?>

<?php if ($contractsTab === 'b2b_history'): ?>
<section class="card">
    <h3><?= t('contracts.b2b.history_heading') ?></h3>
    <?php if (empty($b2bHistory)): ?>
    <p class="contracts-empty"><?= t('contracts.b2b.history_empty') ?></p>
    <?php else: ?>
    <div class="contracts-list">
        <?php foreach ($b2bHistory as $offer): ?>
        <div class="contracts-row contracts-row--b2b">
            <span data-label="<?= t('contracts.b2b.buyer') ?>"><?= htmlspecialchars((string)($offer['buyer_name'] ?? '')) ?></span>
            <span data-label="<?= t('contracts.b2b.seller') ?>"><?= htmlspecialchars((string)($offer['seller_name'] ?? '')) ?></span>
            <span data-label="<?= t('contracts.b2b.volume') ?>"><?= $b2bFmtBbl((float)$offer['total_bbl']) ?> <?= t('contracts.unit_bbl') ?></span>
            <span data-label="<?= t('contracts.b2b.total_value') ?>"><?= $b2bFmtMoney((float)$offer['total_value']) ?></span>
            <span data-label="<?= t('contracts.status_label') ?>"><?= htmlspecialchars($b2bStatusLabel((string)$offer['status'])) ?></span>
            <span data-label="<?= t('contracts.b2b.created_at') ?>"><?= htmlspecialchars(substr((string)$offer['created_at'], 0, 16)) ?></span>
        </div>
        <?php endforeach ?>
    </div>
    <?php $b2bPager('b2b_history', 'b2b_history_page', $b2bHistoryPage, $b2bHistoryCount, 12); ?>
    <?php endif ?>
</section>
<?php endif ?>

<?php if ($contractsTab === 'b2b_logs'): ?>
<section class="card">
    <h3><?= t('contracts.b2b.logs_heading') ?></h3>
    <?php if (empty($b2bLogs)): ?>
    <p class="contracts-empty"><?= t('contracts.b2b.logs_empty') ?></p>
    <?php else: ?>
    <div class="contracts-list">
        <?php foreach ($b2bLogs as $log): ?>
        <div class="contracts-row contracts-row--log">
            <span data-label="<?= t('contracts.log_time') ?>"><?= htmlspecialchars(substr((string)$log['created_at'], 0, 16)) ?></span>
            <span data-label="<?= t('contracts.log_event') ?>"><?= htmlspecialchars($b2bEventLabel((string)$log['event_key'])) ?></span>
        </div>
        <?php endforeach ?>
    </div>
    <?php $b2bPager('b2b_logs', 'b2b_logs_page', $b2bLogsPage, $b2bLogsCount, 30); ?>
    <?php endif ?>
</section>
<?php endif ?>
