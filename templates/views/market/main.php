<?php extract($viewData, EXTR_SKIP); ?>

<script>
function switchMarketTab(ev, tab) {
    var target = document.getElementById('tab-' + tab);
    if (!target) {
        console.error('switchMarketTab: element #tab-' + tab + ' not found in DOM');
        return;
    }
    document.querySelectorAll('.market-tab').forEach(function (el) { el.classList.remove('active'); });
    document.querySelectorAll('.market-tab-content').forEach(function (el) { el.classList.remove('active'); });
    target.classList.add('active');
    ev.currentTarget.classList.add('active');
    var u = new URL(window.location);
    u.searchParams.set('tab', tab);
    history.replaceState({ marketTab: tab, ajaxUrl: u.toString() }, '', u.origin + u.pathname);
}

(function () {
    if (window.location.search.indexOf('tab=') === -1) {
        return;
    }

    var u = new URL(window.location);
    history.replaceState({ marketTab: u.searchParams.get('tab') || 'market', ajaxUrl: u.toString() }, '', u.origin + u.pathname);
}());
</script>

<div class="fade-in">

    <div class="market-tabs">
        <button class="market-tab <?= $activeTab === 'market' ? 'active' : '' ?>"
                onclick="switchMarketTab(event,'market')"> <?= t('market.heading') ?></button>
        <button class="market-tab <?= $activeTab === 'black_market' ? 'active' : '' ?>"
                onclick="switchMarketTab(event,'black_market')"><?= t('black_market.tab_title') ?></button>
        <?php if (!empty($b2bModuleEnabled)): ?>
        <button class="market-tab <?= $activeTab === 'b2b' ? 'active' : '' ?>"
                onclick="switchMarketTab(event,'b2b')"><?= t('market.b2b_tab_title') ?></button>
        <?php endif ?>
    </div>

    <div id="tab-market" class="market-tab-content <?= $activeTab === 'market' ? 'active' : '' ?>">
        <section class="card" aria-labelledby="market-heading">
            <h2 id="market-heading"><?= t('market.heading') ?></h2>
            <?php
            require __DIR__ . '/../../components/alert.php';
            require __DIR__ . '/../../components/price_chart.php';
            ?>
        </section>

        <section class="card" aria-labelledby="instant-sell-heading">
            <h2 id="instant-sell-heading"><?= t('market.instant_sell_title') ?></h2>
            <p><?= t('market.instant_sell_desc') ?>: <strong class="money"><?= htmlspecialchars(number_format($marketData['current_price'])) ?></strong></p>
            <?php
            $formAction  = 'sell_instant';
            $maxAmount   = $storageData['used'];
            $buttonClass = 'btn-success';
            $buttonLabel = t('market.instant_sell_btn');
            require __DIR__ . '/../../components/form_sell.php';
            ?>
        </section>

        <section class="card" aria-labelledby="limit-offer-heading">
            <h2 id="limit-offer-heading"><?= t('market.limit_offer_title') ?></h2>
            <p><?= t('market.limit_offer_desc') ?></p>
            <?php
            $formAction     = 'create_offer';
            $maxAmount      = $storageData['used'];
            $currentPrice   = $marketData['current_price'];
            $showLimitPrice = true;
            $buttonClass    = 'btn-warning';
            $buttonLabel    = t('market.limit_offer_btn');
            require __DIR__ . '/../../components/form_sell.php';
            ?>
        </section>

        <section class="card" aria-labelledby="active-offers-heading">
            <h2 id="active-offers-heading"> <?= t('market.active_offers') ?></h2>
            <?php require __DIR__ . '/../../components/my_offers_table.php'; ?>
        </section>

        <section class="card" aria-labelledby="sale-history-heading">
            <h2 id="sale-history-heading"><?= t('market.sale_history_heading') ?></h2>

            <?php if (empty($historyRows)): ?>
                <p class="no-data-msg"><?= t('market.sale_history_empty') ?></p>
            <?php else: ?>
                <div class="sale-history-table-wrap">
                    <table class="sale-history-table">
                        <thead>
                            <tr>
                                <th><?= t('market.sale_history_col_id') ?></th>
                                <th><?= t('market.sale_history_col_listed') ?></th>
                                <th><?= t('market.sale_history_col_sold') ?></th>
                                <th><?= t('market.sale_history_col_price') ?></th>
                                <th><?= t('market.sale_history_col_bbls') ?></th>
                                <th><?= t('market.sale_history_col_total') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historyRows as $row): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars((string)$row['id']) ?></td>
                                    <td><?= htmlspecialchars($row['listed_at']) ?></td>
                                    <td><?= htmlspecialchars($row['sold_at']) ?></td>
                                    <td class="money"><?= number_format((int)$row['price_per_bbl']) ?></td>
                                    <td><?= number_format((int)$row['barrels_sold']) ?></td>
                                    <td class="money"><?= number_format((float)$row['total_earned'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($historyPages > 1): ?>
                    <nav class="sale-history-pagination" aria-label="<?= t('market.sale_history_heading') ?>">
                        <?php
                        $baseUrl = strtok($_SERVER['REQUEST_URI'], '?');
                        $params  = $_GET;
                        unset($params['hpage']);
                        $qs = $params ? '&' . http_build_query($params) : '';
                        ?>

                        <?php if ($historyPage > 1): ?>
                            <a href="<?= htmlspecialchars($baseUrl . '?hpage=' . ($historyPage - 1) . $qs) ?>"
                               class="btn btn-sm btn-secondary"><?= t('market.sale_history_prev') ?></a>
                        <?php else: ?>
                            <span class="btn btn-sm btn-secondary disabled"><?= t('market.sale_history_prev') ?></span>
                        <?php endif; ?>

                        <span class="sale-history-page-info">
                            <?= tPlain('market.sale_history_page', ['cur' => $historyPage, 'total' => $historyPages]) ?>
                        </span>

                        <?php if ($historyPage < $historyPages): ?>
                            <a href="<?= htmlspecialchars($baseUrl . '?hpage=' . ($historyPage + 1) . $qs) ?>"
                               class="btn btn-sm btn-secondary"><?= t('market.sale_history_next') ?></a>
                        <?php else: ?>
                            <span class="btn btn-sm btn-secondary disabled"><?= t('market.sale_history_next') ?></span>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>

                <p class="sale-history-info"><?= t('market.sale_history_info') ?></p>
            <?php endif; ?>
        </section>
    </div>

    <div id="tab-black_market" class="market-tab-content <?= $activeTab === 'black_market' ? 'active' : '' ?>">
        <section class="card">
            <h2> <?= t('black_market.heading') ?></h2>
            <p class="bm-subtitle"><?= t('black_market.subtitle') ?></p>

            <div class="bm-detection-notice"> <?= t('black_market.detection_notice') ?></div>

            <h3 class="bm-section-heading"><?= t('black_market.offers_heading') ?></h3>
            <div class="bm-offers-grid">
                <div class="bm-offer-head">
                    <span><?= t('black_market.col_buyer') ?></span>
                    <span><?= t('black_market.col_amount') ?></span>
                    <span><?= t('black_market.col_price') ?></span>
                    <span><?= t('black_market.col_total') ?></span>
                    <span><?= t('black_market.col_risk') ?></span>
                    <span><?= t('black_market.col_expires') ?></span>
                    <span><?= t('black_market.col_action') ?></span>
                </div>
                <div id="bm-offers-body">
                    <div class="bm-empty"><?= t('black_market.no_offers') ?></div>
                </div>
            </div>

            <h3 class="bm-section-heading"><?= t('black_market.history_heading') ?></h3>
            <div class="bm-history-grid">
                <div class="bm-history-head">
                    <span><?= t('black_market.col_date') ?></span>
                    <span><?= t('black_market.col_bbl') ?></span>
                    <span><?= t('black_market.col_revenue') ?></span>
                    <span><?= t('black_market.col_status') ?></span>
                    <span><?= t('black_market.col_penalty') ?></span>
                </div>
                <div id="bm-history-body">
                    <div class="bm-empty"><?= t('black_market.history_empty') ?></div>
                </div>
            </div>
        </section>
    </div>

    <?php if (!empty($b2bModuleEnabled)): ?>
    <?php
    $b2bFmtBbl   = static fn(float $v): string => number_format($v, 0, ',', "\xc2\xa0");
    $b2bFmtMoney = static fn(float $v): string => number_format($v, 2, ',', "\xc2\xa0");
    $b2bBase     = function_exists('url') ? url('market') : '/market';
    ?>
    <div id="tab-b2b" class="market-tab-content <?= $activeTab === 'b2b' ? 'active' : '' ?>">
        <section class="card" aria-labelledby="b2b-heading">
            <h2 id="b2b-heading"><?= t('market.b2b_heading') ?></h2>
            <p class="market-b2b-subtitle"><?= t('market.b2b_subtitle') ?></p>
            <p class="market-b2b-reputation">
                <?= t('contracts.b2b.reputation_label') ?>:
                <strong><?= (int)$b2bReputation ?>/100</strong>
            </p>

            <?php if (empty($b2bOffers)): ?>
                <p class="no-data-msg"><?= t('contracts.b2b.market_empty') ?></p>
            <?php else: ?>
                <div class="contracts-grid">
                    <?php foreach ($b2bOffers as $offer): ?>
                    <article class="contracts-card">
                        <div class="contracts-card__head">
                            <span class="contracts-card__name"><?= htmlspecialchars((string)($offer['buyer_name'] ?? '')) ?></span>
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
                              data-confirm-title="<?= htmlspecialchars(tPlain('market.b2b_heading'), ENT_QUOTES, 'UTF-8') ?>"
                              data-confirm-label="<?= htmlspecialchars(tPlain('contracts.b2b.btn_deliver'), ENT_QUOTES, 'UTF-8') ?>">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="action" value="accept_b2b_offer">
                            <input type="hidden" name="offer_id" value="<?= (int)$offer['id'] ?>">
                            <button type="submit" class="btn btn-success btn-full"><?= t('contracts.b2b.btn_deliver') ?></button>
                        </form>
                    </article>
                    <?php endforeach ?>
                </div>

                <?php if ($b2bPages > 1): ?>
                <nav class="market-b2b-pagination" aria-label="<?= t('market.b2b_heading') ?>">
                    <?php if ($b2bPage > 1): ?>
                        <a class="btn btn-sm btn-secondary" href="<?= htmlspecialchars($b2bBase . '?tab=b2b&b2bpage=' . ($b2bPage - 1)) ?>"><?= t('market.sale_history_prev') ?></a>
                    <?php else: ?>
                        <span class="btn btn-sm btn-secondary disabled"><?= t('market.sale_history_prev') ?></span>
                    <?php endif ?>
                    <span class="sale-history-page-info"><?= tPlain('market.sale_history_page', ['cur' => $b2bPage, 'total' => $b2bPages]) ?></span>
                    <?php if ($b2bPage < $b2bPages): ?>
                        <a class="btn btn-sm btn-secondary" href="<?= htmlspecialchars($b2bBase . '?tab=b2b&b2bpage=' . ($b2bPage + 1)) ?>"><?= t('market.sale_history_next') ?></a>
                    <?php else: ?>
                        <span class="btn btn-sm btn-secondary disabled"><?= t('market.sale_history_next') ?></span>
                    <?php endif ?>
                </nav>
                <?php endif ?>
            <?php endif ?>
        </section>
    </div>
    <?php endif ?>
</div>

<script>
window.MARKET_PRICE = <?= json_encode((float)($marketData['current_price'] ?? 0)) ?>;
window.MARKET_MSG   = <?= json_encode($success ?? '') ?>;
window.MARKET_ERR   = <?= json_encode($error ?? '') ?>;
window.MARKET_LANG  = <?= json_encode([
    'confirm_sell'      => t('market.confirm_sell_instant'),
    'confirm_sell_btn'  => t('market.confirm_sell_btn'),
    'confirm_offer'     => t('market.confirm_create_offer'),
    'confirm_offer_btn' => t('market.confirm_offer_btn'),
], JSON_UNESCAPED_UNICODE) ?>;

window.WG_CSRF = <?= json_encode(CSRF::generateToken()) ?>;
window.BM_LANG = {
    no_offers:        <?= json_encode(t('black_market.no_offers')) ?>,
    btn_sell:         <?= json_encode(t('black_market.btn_sell')) ?>,
    btn_confirm:      <?= json_encode(t('black_market.btn_confirm')) ?>,
    confirm_title:    <?= json_encode(t('black_market.confirm_title')) ?>,
    confirm_text:     <?= json_encode(t('black_market.confirm_text')) ?>,
    history_empty:    <?= json_encode(t('black_market.history_empty')) ?>,
    status_ok:        <?= json_encode(t('black_market.status_ok')) ?>,
    status_detected:  <?= json_encode(t('black_market.status_detected')) ?>,
    error_generic:    <?= json_encode(t('black_market.error_generic')) ?>,
    error_connection: <?= json_encode(t('black_market.error_connection')) ?>,
    offer_expired:    <?= json_encode(t('black_market.offer_expired')) ?>,
    no_penalty:       <?= json_encode(t('black_market.no_penalty')) ?>,
    loading:          <?= json_encode(t('black_market.loading')) ?>
};
</script>
<script src="/assets/js/market.js"></script>
<script src="/assets/js/black_market.js?v=2"></script>
