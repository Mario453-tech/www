<?php
// Redeploy 2026-07-08: wymuszenie wgrania (sekcja reputacji B2B z commita d34b425).
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
 * @var array<int,array<string,mixed>> $b2bDeliveries
 * @var array<int,array<string,mixed>> $b2bLogs
 * @var int $b2bReputationScore
 * @var int $b2bMarketCount
 * @var int $b2bMyBuyCount
 * @var int $b2bMySalesCount
 * @var int $b2bHistoryCount
 * @var int $b2bDeliveriesCount
 * @var int $b2bLogsCount
 * @var int $b2bMarketPage
 * @var int $b2bMyPage
 * @var int $b2bHistoryPage
 * @var int $b2bDeliveryPage
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
$b2bDecodeMeta = static function (array $row): array {
    $raw = $row['meta_json'] ?? null;
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
};
$b2bShortDate = static function (?string $value): string {
    $value = trim((string)$value);
    return $value !== '' ? substr($value, 0, 16) : '-';
};
$b2bClosedAt = static function (array $offer) use ($b2bShortDate): string {
    foreach (['completed_at', 'cancelled_at', 'updated_at', 'created_at'] as $key) {
        $value = trim((string)($offer[$key] ?? ''));
        if ($value !== '') {
            return $b2bShortDate($value);
        }
    }
    return '-';
};
$b2bLogDetail = static function (array $log) use ($b2bDecodeMeta, $b2bFmtBbl, $b2bFmtMoney, $b2bShortDate): string {
    $meta = $b2bDecodeMeta($log);
    $event = (string)($log['event_key'] ?? '');
    return match ($event) {
        'created' => t('contracts.b2b.log_detail.created', [
            'bbl' => $b2bFmtBbl((float)($meta['bbl'] ?? 0)),
            'price' => $b2bFmtMoney((float)($meta['price_per_bbl'] ?? 0)),
            'value' => $b2bFmtMoney((float)($meta['total_value'] ?? 0)),
        ]),
        'cancelled' => t('contracts.b2b.log_detail.cancelled', [
            'refund' => $b2bFmtMoney((float)($meta['refund_amount'] ?? 0)),
            'penalty' => $b2bFmtMoney((float)($meta['penalty_amount'] ?? 0)),
        ]),
        'offer_accepted' => t('contracts.b2b.log_detail.offer_accepted', [
            'bbl' => $b2bFmtBbl((float)($meta['first_delivery_bbl'] ?? 0)),
            'remaining' => $b2bFmtBbl((float)($meta['remaining_bbl'] ?? 0)) . ' ' . t('contracts.unit_bbl'),
            'deadline' => $b2bShortDate((string)($meta['deadline_at'] ?? '')),
        ]),
        'offer_completed' => t('contracts.b2b.log_detail.offer_completed'),
        'partial_delivery_made' => t('contracts.b2b.log_detail.partial_delivery_made', [
            'bbl' => $b2bFmtBbl((float)($meta['bbl'] ?? 0)),
            'revenue' => $b2bFmtMoney((float)($meta['revenue'] ?? 0)),
            'remaining' => $b2bFmtBbl((float)($meta['remaining_bbl'] ?? 0)) . ' ' . t('contracts.unit_bbl'),
        ]),
        'partial_payment_released' => t('contracts.b2b.log_detail.partial_payment_released', [
            'amount' => $b2bFmtMoney((float)($meta['amount'] ?? 0)),
        ]),
        'remaining_escrow_refunded' => t('contracts.b2b.log_detail.remaining_escrow_refunded', [
            'amount' => $b2bFmtMoney((float)($meta['amount'] ?? 0)),
        ]),
        'seller_penalty_charged' => t('contracts.b2b.log_detail.seller_penalty_charged', [
            'bbl' => $b2bFmtBbl((float)($meta['missing_bbl'] ?? 0)),
            'amount' => $b2bFmtMoney((float)($meta['penalty_amount'] ?? 0)),
        ]),
        'seller_penalty_skipped' => t('contracts.b2b.log_detail.seller_penalty_skipped'),
        'offer_failed' => t('contracts.b2b.log_detail.offer_failed', [
            'refund' => $b2bFmtMoney((float)($meta['refunded'] ?? 0)),
        ]),
        'offer_partially_completed' => t('contracts.b2b.log_detail.offer_partially_completed', [
            'delivered' => $b2bFmtBbl((float)($meta['delivered_bbl'] ?? 0)) . ' ' . t('contracts.unit_bbl'),
            'missing' => $b2bFmtBbl((float)($meta['missing_bbl'] ?? 0)) . ' ' . t('contracts.unit_bbl'),
            'refund' => $b2bFmtMoney((float)($meta['refunded'] ?? 0)),
        ]),
        'seller_abandoned_offer' => t('contracts.b2b.log_detail.seller_abandoned_offer'),
        'admin_cancelled' => t('contracts.b2b.log_detail.admin_cancelled', [
            'refund' => $b2bFmtMoney((float)($meta['refund_amount'] ?? 0)),
        ]),
        'admin_flagged' => t('contracts.b2b.log_detail.admin_flagged'),
        'admin_unflagged' => t('contracts.b2b.log_detail.admin_unflagged'),
        'expired' => t('contracts.b2b.log_detail.expired', [
            'refund' => $b2bFmtMoney((float)($meta['refund_amount'] ?? 0)),
        ]),
        default => t('contracts.b2b.log_detail.default', ['offer' => (string)((int)($log['offer_id'] ?? 0))]),
    };
};
$b2bPageLink = static function (string $tab, string $param, int $page) use ($b2bBaseUrl): string {
    return $b2bBaseUrl . '?tab=' . rawurlencode($tab) . '&' . rawurlencode($param) . '=' . max(1, $page);
};
$b2bReputationPct = max(0, min(100, (int)($b2bReputationScore ?? 50)));
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

<?php if (in_array($contractsTab, ['b2b_market', 'b2b_my', 'b2b_history', 'b2b_logs'], true)): ?>
<section class="card contracts-b2b-reputation">
    <div class="contracts-b2b-reputation__head">
        <div>
            <span class="ct-label"><?= t('contracts.b2b.reputation_label') ?></span>
            <strong class="contracts-b2b-reputation__score"><?= $b2bReputationPct ?>%</strong>
        </div>
        <span class="contracts-badge contracts-badge--status"><?= t('contracts.b2b.reputation_badge') ?></span>
    </div>
    <div class="contracts-progress contracts-b2b-reputation__bar" aria-label="<?= htmlspecialchars(strip_tags(t('contracts.b2b.reputation_label')), ENT_QUOTES, 'UTF-8') ?>">
        <div class="contracts-progress__bar" style="--bar-w: <?= $b2bReputationPct ?>%"></div>
    </div>
    <p class="contracts-b2b-reputation__note"><?= t('contracts.b2b.reputation_note') ?></p>
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
            <?php
                $offerPartial = !empty($offer['partial_delivery_enabled']);
                $offerMinPct = (float)($offer['min_first_delivery_pct'] ?? (float)($b2bConfig['min_first_delivery_pct'] ?? 25));
                $offerMinBbl = round((float)$offer['total_bbl'] * $offerMinPct / 100, 2);
            ?>
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
                <div class="contracts-term">
                    <span class="ct-label"><?= t('contracts.b2b.partial_delivery') ?></span>
                    <strong class="ct-val"><?= $offerPartial ? t('contracts.b2b.yes') : t('contracts.b2b.no') ?></strong>
                </div>
                <?php if ($offerPartial): ?>
                <div class="contracts-term">
                    <span class="ct-label"><?= t('contracts.b2b.min_first_delivery') ?></span>
                    <strong class="ct-val"><?= $b2bFmtBbl($offerMinBbl) ?> <?= t('contracts.unit_bbl') ?></strong>
                </div>
                <?php endif ?>
            </div>
            <?php if ($offerPartial): ?>
            <details class="contracts-accept-details">
                <summary class="btn btn-success btn-full"><?= t('contracts.b2b.btn_accept_partial') ?></summary>
                <form method="post" class="contracts-action-form contracts-accept-form">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="accept_b2b_offer">
                    <input type="hidden" name="offer_id" value="<?= (int)$offer['id'] ?>">
                    <label>
                        <span><?= t('contracts.b2b.first_delivery_bbl') ?></span>
                        <input type="number" name="first_delivery_bbl"
                               min="<?= $offerMinBbl ?>"
                               max="<?= (float)$offer['total_bbl'] ?>"
                               step="1" required
                               value="<?= $offerMinBbl ?>">
                    </label>
                    <p class="contracts-accept-hint">
                        <?= t('contracts.b2b.accept_hint', ['min' => $b2bFmtBbl($offerMinBbl), 'total' => $b2bFmtBbl((float)$offer['total_bbl'])]) ?>
                    </p>
                    <button type="submit" class="btn btn-success btn-full"><?= t('contracts.b2b.btn_deliver_first') ?></button>
                </form>
            </details>
            <?php else: ?>
            <form method="post" class="contracts-action-form"
                  data-confirm="<?= htmlspecialchars(tPlain('contracts.b2b.confirm_accept'), ENT_QUOTES, 'UTF-8') ?>"
                  data-confirm-label="<?= htmlspecialchars(tPlain('contracts.b2b.btn_deliver'), ENT_QUOTES, 'UTF-8') ?>">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="accept_b2b_offer">
                <input type="hidden" name="offer_id" value="<?= (int)$offer['id'] ?>">
                <button type="submit" class="btn btn-success btn-full"><?= t('contracts.b2b.btn_deliver') ?></button>
            </form>
            <?php endif ?>
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
        <?php
            $saleRemaining = round((float)$offer['total_bbl'] - (float)$offer['delivered_bbl'], 2);
            $saleIsActive = (string)$offer['status'] === 'accepted';
        ?>
        <div class="contracts-row contracts-row--b2b">
            <span data-label="<?= t('contracts.b2b.buyer') ?>"><?= htmlspecialchars((string)($offer['buyer_name'] ?? '')) ?></span>
            <span data-label="<?= t('contracts.b2b.volume') ?>">
                <?= $b2bFmtBbl((float)$offer['delivered_bbl']) ?> / <?= $b2bFmtBbl((float)$offer['total_bbl']) ?> <?= t('contracts.unit_bbl') ?>
            </span>
            <span data-label="<?= t('contracts.b2b.total_value') ?>"><?= $b2bFmtMoney((float)$offer['total_value']) ?></span>
            <span data-label="<?= t('contracts.b2b.released_amount') ?>"><?= $b2bFmtMoney((float)$offer['released_amount']) ?></span>
            <span data-label="<?= t('contracts.status_label') ?>"><?= htmlspecialchars($b2bStatusLabel((string)$offer['status'])) ?></span>
            <?php if ($saleIsActive && !empty($offer['delivery_deadline_at'])): ?>
            <span data-label="<?= t('contracts.b2b.deadline') ?>"><?= htmlspecialchars(substr((string)$offer['delivery_deadline_at'], 0, 16)) ?></span>
            <?php endif ?>
            <span data-label="<?= t('contracts.b2b.action') ?>">
                <?php if ($saleIsActive && $saleRemaining > 0): ?>
                <details class="contracts-accept-details">
                    <summary class="btn btn-success"><?= t('contracts.b2b.btn_deliver_next') ?></summary>
                    <form method="post" class="contracts-action-form contracts-accept-form">
                        <?= CSRF::field() ?>
                        <input type="hidden" name="action" value="deliver_b2b_partial">
                        <input type="hidden" name="offer_id" value="<?= (int)$offer['id'] ?>">
                        <label>
                            <span><?= t('contracts.b2b.deliver_bbl') ?></span>
                            <input type="number" name="deliver_bbl" min="1" max="<?= $saleRemaining ?>" step="1" required value="<?= $saleRemaining ?>">
                        </label>
                        <button type="submit" class="btn btn-success"><?= t('contracts.b2b.btn_deliver_confirm') ?></button>
                    </form>
                </details>
                <form method="post" class="contracts-inline-form"
                      data-confirm="<?= htmlspecialchars(tPlain('contracts.b2b.confirm_abandon'), ENT_QUOTES, 'UTF-8') ?>"
                      data-confirm-type="warning">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="seller_abandon_b2b">
                    <input type="hidden" name="offer_id" value="<?= (int)$offer['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-xs"><?= t('contracts.b2b.btn_abandon') ?></button>
                </form>
                <?php endif ?>
            </span>
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
            <span data-label="<?= t('contracts.b2b.delivered_progress') ?>">
                <?= $b2bFmtBbl((float)$offer['delivered_bbl']) ?> / <?= $b2bFmtBbl((float)$offer['total_bbl']) ?> <?= t('contracts.unit_bbl') ?>
            </span>
            <span data-label="<?= t('contracts.b2b.total_value') ?>"><?= $b2bFmtMoney((float)$offer['total_value']) ?></span>
            <span data-label="<?= t('contracts.b2b.released_amount') ?>"><?= $b2bFmtMoney((float)$offer['released_amount']) ?></span>
            <span data-label="<?= t('contracts.b2b.secured_returned') ?>"><?= $b2bFmtMoney((float)$offer['refunded_amount']) ?></span>
            <span data-label="<?= t('contracts.b2b.penalty_amount') ?>"><?= $b2bFmtMoney((float)$offer['seller_penalty_amount']) ?></span>
            <span data-label="<?= t('contracts.status_label') ?>"><?= htmlspecialchars($b2bStatusLabel((string)$offer['status'])) ?></span>
            <span data-label="<?= t('contracts.b2b.settled_at') ?>"><?= htmlspecialchars($b2bClosedAt($offer)) ?></span>
        </div>
        <?php endforeach ?>
    </div>
    <?php $b2bPager('b2b_history', 'b2b_history_page', $b2bHistoryPage, $b2bHistoryCount, 12); ?>
    <?php endif ?>
</section>

<section class="card">
    <h3><?= t('contracts.b2b.deliveries_heading') ?></h3>
    <?php if (empty($b2bDeliveries)): ?>
    <p class="contracts-empty"><?= t('contracts.b2b.deliveries_empty') ?></p>
    <?php else: ?>
    <div class="contracts-list">
        <?php foreach ($b2bDeliveries as $del): ?>
        <div class="contracts-row contracts-row--b2b">
            <span data-label="<?= t('contracts.b2b.created_at') ?>"><?= htmlspecialchars($b2bShortDate((string)$del['created_at'])) ?></span>
            <span data-label="<?= t('contracts.b2b.offer_id') ?>">#<?= (int)$del['offer_id'] ?></span>
            <span data-label="<?= t('contracts.b2b.buyer') ?>"><?= htmlspecialchars((string)($del['buyer_name'] ?? '')) ?></span>
            <span data-label="<?= t('contracts.b2b.volume') ?>"><?= $b2bFmtBbl((float)$del['delivered_bbl']) ?> <?= t('contracts.unit_bbl') ?></span>
            <span data-label="<?= t('contracts.b2b.price_per_bbl') ?>"><?= $b2bFmtMoney((float)$del['price_per_bbl']) ?></span>
            <span data-label="<?= t('contracts.b2b.revenue') ?>"><?= $b2bFmtMoney((float)$del['revenue']) ?></span>
            <span data-label="<?= t('contracts.b2b.remaining_after') ?>"><?= $b2bFmtBbl((float)$del['remaining_bbl_after']) ?> <?= t('contracts.unit_bbl') ?></span>
            <span data-label="<?= t('contracts.b2b.secured_left') ?>"><?= $b2bFmtMoney((float)$del['escrow_after']) ?></span>
        </div>
        <?php endforeach ?>
    </div>
    <?php $b2bPager('b2b_history', 'historia_b2b_strona', $b2bDeliveryPage, $b2bDeliveriesCount, 12); ?>
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
            <span data-label="<?= t('contracts.log_time') ?>"><?= htmlspecialchars($b2bShortDate((string)$log['created_at'])) ?></span>
            <span data-label="<?= t('contracts.b2b.offer_id') ?>">#<?= (int)$log['offer_id'] ?></span>
            <span data-label="<?= t('contracts.log_event') ?>"><?= htmlspecialchars($b2bEventLabel((string)$log['event_key'])) ?></span>
            <span data-label="<?= t('contracts.b2b.log_details') ?>"><?= htmlspecialchars($b2bLogDetail($log)) ?></span>
        </div>
        <?php endforeach ?>
    </div>
    <?php $b2bPager('b2b_logs', 'b2b_logs_page', $b2bLogsPage, $b2bLogsCount, 30); ?>
    <?php endif ?>
</section>
<?php endif ?>
