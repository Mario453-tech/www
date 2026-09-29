    <?php
        $hasAvailableRegions = !empty($hubAvailByRegion);
        $marketPage = (int)($hubMarketPage ?? 1);
        $marketPages = (int)($hubMarketTotalPages ?? 1);
        $marketTotal = (int)($hubMarketTotal ?? 0);
        $marketPageSize = 10;
        $marketStart = $marketTotal ? (($marketPage - 1) * $marketPageSize + 1) : 0;
        $marketEnd = min($marketTotal, $marketPage * $marketPageSize);
    ?>
    <section class="logistics-panel" aria-labelledby="logistics-available-hubs-heading">
        <div class="logistics-panel-head">
            <h3 id="logistics-available-hubs-heading"><?= t('logistics.hub.avail_section_title') ?></h3>
            <span><?= t('logistics.hub.avail_section_desc') ?></span>
        </div>

        <form class="logistics-hub-filter logistics-market-filter" method="get" action="/logistics#logistics-market-section">
            <label for="lhb-search"><?= t('logistics.design.market_search') ?>
                <input class="logistics-hub-search" type="search" id="lhb-search" name="hub_market_q"
                    value="<?= htmlspecialchars((string)($hubMarketQuery ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="<?= htmlspecialchars(t('logistics.hub.filter_placeholder'), ENT_QUOTES, 'UTF-8') ?>" maxlength="100" autocomplete="off">
            </label>
            <label for="lhb-filter"><?= t('logistics.design.market_filter') ?>
                <select id="lhb-filter" name="hub_market_filter">
                    <?php foreach (['all', 'free', 'new', 'used', 'rental', 'large', 'medium', 'small'] as $marketFilterOption): ?>
                    <option value="<?= $marketFilterOption ?>" <?= ($hubMarketFilter ?? 'all') === $marketFilterOption ? 'selected' : '' ?>><?= t(in_array($marketFilterOption, ['large', 'medium', 'small'], true) ? 'logistics.hub.type_' . $marketFilterOption : 'logistics.hub.filter_' . $marketFilterOption) ?></option>
                    <?php endforeach ?>
                </select>
            </label>
            <button class="btn btn-sm btn-primary" type="submit"><?= t('logistics.design.filter') ?></button>
            <a class="btn btn-sm btn-secondary" href="/logistics#logistics-market-section"><?= t('logistics.design.market_reset') ?></a>
        </form>
        <p id="lhb-count" class="logistics-filter-count" aria-live="polite"><?= t('logistics.design.market_range', ['start' => $marketStart, 'end' => $marketEnd, 'total' => $marketTotal]) ?></p>

        <?php if (!$hasAvailableRegions): ?>
        <div class="logistics-empty"><?= t('logistics.design.market_empty') ?></div>
        <?php else: ?>

        <div id="lhb-browser" class="logistics-hub-browser">
        <?php foreach ($hubAvailByRegion as $rgIdx => $regionGroup): ?>
        <?php
            $rHubs      = $regionGroup['hubs'] ?? [];
            $rHubCount  = count($rHubs);
            $rFreeSlots = 0;
            foreach ($rHubs as $_h) {
                $rFreeSlots += max(0, (int)($_h['slots_avail'] ?? 0));
            }
            $rHasFree = $rFreeSlots > 0;
        ?>
        <div class="logistics-region-group<?= $rgIdx === 0 ? ' is-open' : '' ?>"
             data-region-id="<?= (int)($regionGroup['region_id'] ?? 0) ?>"
             data-region-name-lc="<?= htmlspecialchars(mb_strtolower($regionGroup['region_name'] ?? ''), ENT_QUOTES) ?>">

            <button class="logistics-region-toggle" type="button" data-lhb-toggle aria-expanded="<?= $rgIdx === 0 ? 'true' : 'false' ?>" aria-controls="lhb-region-<?= $rgIdx ?>">
                <span class="logistics-region-caret"></span>
                <span class="logistics-region-title-wrap">
                    <span class="logistics-region-title"><?= htmlspecialchars($regionGroup['region_name'] ?? (($locale === 'en' ? 'Region #' : 'Region #') . (int)($regionGroup['region_id'] ?? $rgIdx))) ?></span>
                    <span class="logistics-region-subtitle"><?= t('logistics.design.market_region_page_count', ['count' => $rHubCount]) ?></span>
                </span>
                <span class="logistics-region-badge<?= $rHasFree ? ' has-free' : '' ?>">
                    <?= t('logistics.design.market_region_slots', ['count' => $rFreeSlots]) ?>
                </span>
            </button>

            <div class="logistics-region-body" id="lhb-region-<?= $rgIdx ?>">
            <?php if (empty($rHubs)): ?>
                        <div class="logistics-empty logistics-empty--padded"><?= t('logistics.hub.avail_none_in_region') ?></div>
            <?php else: ?>
                <div class="logistics-hub-avail-grid">
                <?php foreach ($rHubs as $hubIdx => $hub): ?>
                <?php
                    $hId        = (int)($hub['id'] ?? 0);
                    $slotsAvail = max(0, (int)($hub['slots_avail'] ?? 0));
                    $slotLimit  = max(0, (int)($hub['slot_limit'] ?? $hub['slots_total'] ?? 0));
                    $assignedN  = $slotLimit > 0 ? max(0, $slotLimit - $slotsAvail) : 0;
                    $isFull     = !empty($hub['slots_full']) || $slotsAvail === 0;
                    $hStatus    = $hub['status'] ?? 'active';
                    $leaseFee   = (float)($hub['lease_fee_per_tick'] ?? 0);
                    $acqType    = (string)($hub['acquisition_type'] ?? 'new');
                    $hubType    = $hub['hub_type'] ?? 'small';
                    $workMode   = $hub['work_mode'] ?? 'standard';
                    $condPct    = isset($hub['condition_pct']) ? (float)$hub['condition_pct'] : -1;
                    $condClass  = $condPct < 0 ? 'c-muted2'
                                : ($condPct <= 30 ? 'c-bad' : ($condPct <= 60 ? 'c-warn' : 'c-good'));
                    $tierKey    = match($workMode) {
                        'premium' => 'prem',
                        'elite'   => 'elite',
                        default   => 'std',
                    };
 // Dots: show up to 8; use slotLimit if known, else slotsAvail
                    $dotTotal   = $slotLimit > 0 ? min(8, $slotLimit) : min(8, max(1, $slotsAvail));
                    $hRegionId  = (int)($regionGroup['region_id'] ?? 0);
                    $hZoneKey   = (string)($hub['zone_key'] ?? '');
                    $hName      = (string)($hub['name'] ?? (($locale === 'en' ? 'Hub #' : 'Hub #') . $hId));
                    $statusKey  = 'logistics.hub.status_' . $hStatus;
                    $statusText = t($statusKey) !== $statusKey ? t($statusKey) : ucfirst($hStatus);
                    $acqLabelKey = 'logistics.hub.acquisition_' . $acqType;
                    $acqLabel = t($acqLabelKey) !== $acqLabelKey ? t($acqLabelKey) : $acqType;
                    $cardClasses = ['logistics-hub-avail-card', 'hub-status-' . preg_replace('/[^a-z0-9_-]/i', '', $hStatus)];
                    if ($isFull) {
                        $cardClasses[] = 'slots-full';
                    }
                ?>
                <article class="<?= implode(' ', $cardClasses) ?>"
                         data-lhb-card
                         data-hub-id="<?= $hId ?>"
                         data-hub-type="<?= htmlspecialchars($hubType) ?>"
                         data-hub-free="<?= $slotsAvail ?>"
                         data-hub-name-lc="<?= htmlspecialchars(mb_strtolower($hub['name'] ?? ''), ENT_QUOTES) ?>"
                         data-hub-name="<?= htmlspecialchars($hName, ENT_QUOTES) ?>"
                         data-hub-acq-type="<?= htmlspecialchars($acqType, ENT_QUOTES) ?>"
                         data-hub-lease-fee="<?= number_format($leaseFee, 2, '.', '') ?>"
                         data-hub-region-id="<?= $hRegionId ?>"
                         data-hub-zone-key="<?= htmlspecialchars($hZoneKey, ENT_QUOTES) ?>">
                    <div class="logistics-hub-avail-top">
                        <div>
                            <div class="logistics-hub-avail-name"><?= htmlspecialchars($hName) ?></div>
                            <?php if ($hZoneKey !== ''): ?>
                            <div class="logistics-hub-avail-zone"><?= htmlspecialchars($hZoneKey) ?></div>
                            <?php endif ?>
                        </div>
                        <span class="badge"><?= htmlspecialchars($statusText) ?></span>
                    </div>

                    <div class="logistics-hub-avail-meta">
                        <span class="badge logistics-hub-type-<?= $hubType ?>"><?= t('logistics.hub.type_' . $hubType) ?></span>
                        <span class="badge logistics-tier-<?= $tierKey ?>"><?= t('logistics.hub.mode_' . $workMode) ?></span>
                        <span class="acq-badge acq-badge--<?= htmlspecialchars($acqType) ?>"><?= htmlspecialchars($acqLabel) ?></span>
                    </div>

                    <div class="logistics-hub-avail-stats">
                        <div class="logistics-hub-avail-stat">
                            <span class="logistics-hub-avail-label"><?= t('logistics.hub.col_slots') ?></span>
                            <div class="logistics-hub-avail-value">
                                <div class="logistics-slot-dots">
                                    <?php for ($d = 0; $d < $dotTotal; $d++): ?>
                                    <span class="logistics-slot-dot<?= ($slotLimit > 0 && $d < $assignedN) ? ' used' : '' ?>"></span>
                                    <?php endfor ?>
                                </div>
                                <span class="logistics-slot-count<?= $isFull ? ' full' : '' ?>"><?= $slotsAvail ?> / <?= $slotLimit ?></span>
                            </div>
                        </div>
                        <div class="logistics-hub-avail-stat">
                            <span class="logistics-hub-avail-label"><?= t('logistics.hub.col_condition') ?></span>
                            <span class="logistics-hub-avail-value <?= $condPct >= 0 ? $condClass : 'c-muted2' ?>">
                                <?= $condPct >= 0 ? number_format($condPct, 0) . '%' : '&mdash;' ?>
                            </span>
                        </div>
                        <div class="logistics-hub-avail-stat">
                            <span class="logistics-hub-avail-label"><?= t('logistics.hub.col_fee') ?></span>
                            <span class="logistics-hub-avail-value">
                                <?= $leaseFee > 0 ? number_format($leaseFee, 2, ',', ' ') . ' ' . $currencyLabel : '&mdash;' ?>
                            </span>
                        </div>
                    </div>

                    <?php
                        $buyPrice    = (float)($hub['buy_price']    ?? 0);
 // Floor: backup gdyby serwis nie naprawil ceny / Fallback floor if service did not fix price
                        if ($buyPrice <= 0.0) {
                            static $__tplBuyFloors = ['small' => 31000.0, 'medium' => 93000.0, 'large' => 248000.0];
                            $buyPrice = $__tplBuyFloors[$hubType] ?? 31000.0;
                        }
                        $rentDeposit = (float)($hub['rent_deposit'] ?? 0);
                    ?>
                    <div class="logistics-hub-avail-prices">
                        <div class="logistics-hub-avail-price">
                            <span class="logistics-hub-avail-label"><?= t('logistics.hub.market_buy_price') ?></span>
                            <strong><?= number_format($buyPrice, 0, ',', ' ') ?> <?= $currencyLabel ?></strong>
                        </div>
                        <?php if ($leaseFee > 0 && $acqType === 'rental'): ?>
                        <div class="logistics-hub-avail-price">
                            <span class="logistics-hub-avail-label"><?= t('logistics.hub.market_rent_deposit') ?></span>
                            <strong><?= number_format($rentDeposit, 0, ',', ' ') ?> <?= $currencyLabel ?></strong>
                        </div>
                        <?php endif ?>
                    </div>

                    <div class="logistics-hub-avail-footer">
                        <?php if ($hStatus === 'disabled'): ?>
                        <span class="badge logistics-hub-avail-badge-muted"><?= t('logistics.hub.badge_disabled') ?></span>
                        <?php else: ?>
                        <button class="logistics-hub-assign-btn logistics-hub-buy-btn" type="button"
                                data-hub-action="buy-used" data-hub-id="<?= $hId ?>"
                                data-hub-name="<?= htmlspecialchars($hName, ENT_QUOTES) ?>"
                                data-buy-price="<?= number_format($buyPrice, 2, '.', '') ?>">
                            <?= t('logistics.hub.market_btn_buy') ?>
                        </button>
                        <?php if ($leaseFee > 0 && $acqType === 'rental'): ?>
                        <button class="logistics-hub-assign-btn logistics-hub-rent-btn" type="button"
                                data-hub-action="rent" data-hub-id="<?= $hId ?>"
                                data-hub-name="<?= htmlspecialchars($hName, ENT_QUOTES) ?>"
                                data-rent-deposit="<?= number_format($rentDeposit, 2, '.', '') ?>"
                                data-lease-fee="<?= number_format($leaseFee, 2, '.', '') ?>">
                            <?= t('logistics.hub.market_btn_rent') ?>
                        </button>
                        <?php endif ?>
                        <?php endif ?>
                    </div>
                </article>
                <?php endforeach ?>
                </div>

            <?php endif ?>
            </div>
        </div>
        <?php endforeach ?>
        </div><!-- /lhb-browser -->

        <?php endif ?>
        <?php if ($marketPages > 1):
            $marketBaseParams = $_GET;
            $marketBaseParams['hub_market_q'] = (string)($hubMarketQuery ?? '');
            $marketBaseParams['hub_market_filter'] = (string)($hubMarketFilter ?? 'all');
        ?>
        <nav class="logistics-market-pagination" aria-label="<?= htmlspecialchars(t('logistics.design.market_pagination'), ENT_QUOTES, 'UTF-8') ?>">
            <span><?= t('logistics.design.market_page', ['page' => $marketPage, 'total' => $marketPages]) ?></span>
            <div>
                <?php if ($marketPage > 1): $marketBaseParams['hub_market_page'] = $marketPage - 1; ?>
                <a class="btn btn-sm btn-secondary" href="?<?= htmlspecialchars(http_build_query($marketBaseParams), ENT_QUOTES, 'UTF-8') ?>#logistics-market-section"><?= t('logistics.pagination_prev') ?></a>
                <?php endif ?>
                <?php if ($marketPage < $marketPages): $marketBaseParams['hub_market_page'] = $marketPage + 1; ?>
                <a class="btn btn-sm btn-secondary" href="?<?= htmlspecialchars(http_build_query($marketBaseParams), ENT_QUOTES, 'UTF-8') ?>#logistics-market-section"><?= t('logistics.pagination_next') ?></a>
                <?php endif ?>
            </div>
        </nav>
        <?php endif ?>
    </section>
