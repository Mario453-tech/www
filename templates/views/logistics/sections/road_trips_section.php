<section class="logistics-panel logistics-road-trip-panel" aria-labelledby="logistics-road-trips-heading">
    <div class="logistics-panel-head">
        <div>
            <h3 id="logistics-road-trips-heading"><?= t('logistics.design.road_trip_title') ?></h3>
            <span><?= (int)($activeRoadTripsTotal ?? count($activeRoadTrips)) ?> <?= t('logistics.road_trips.count_suffix') ?></span>
        </div>
        <span class="logistics-road-trip-icon" aria-hidden="true"></span>
    </div>
    <?php if (empty($activeRoadTrips)): ?>
        <p class="logistics-empty"><?= t('logistics.road_trips.empty') ?></p>
    <?php else: ?>
        <div class="logistics-road-trip-list">
            <?php foreach ($activeRoadTrips as $trip):
                $secRem = max(0, (int)($trip['seconds_remaining'] ?? 0));
                $hRem = (int)floor($secRem / 3600);
                $mRem = (int)floor(($secRem % 3600) / 60);
                $etaTs = strtotime((string)($trip['eta_at'] ?? ''));
                $truckKey = 'logistics.road_trips.truck_' . ($trip['truck_type'] ?? 'standard');
            ?>
            <article class="logistics-road-trip-card">
                <dl class="logistics-facts">
                    <div><dt><?= t('logistics.design.road_trip_source') ?></dt><dd><?= htmlspecialchars((string)($trip['well_name'] ?? ('#' . (int)$trip['well_id'])), ENT_QUOTES, 'UTF-8') ?></dd></div>
                    <div><dt><?= t('logistics.road_trips.col_volume') ?></dt><dd><?= number_format((float)($trip['volume_bbl'] ?? 0), 1, ',', ' ') ?> <?= t('common.bbl') ?></dd></div>
                    <div><dt><?= t('logistics.road_trips.col_eta') ?></dt><dd><?= $etaTs ? date('d.m, H:i', $etaTs) : '—' ?></dd></div>
                    <div><dt><?= t('logistics.road_trips.col_remaining') ?></dt><dd class="c-warn"><span class="road-trip-countdown" data-seconds="<?= $secRem ?>"><?= $hRem ?>h <?= str_pad((string)$mRem, 2, '0', STR_PAD_LEFT) ?>m</span></dd></div>
                </dl>
                <p class="logistics-road-trip-meta"><?= (int)($trip['trips_count'] ?? 1) ?> <?= t('logistics.road_trips.col_trips') ?> · <?= t($truckKey) ?></p>
            </article>
            <?php endforeach ?>
        </div>
    <?php endif ?>
    <?php if ((int)($activeRoadTripsTotalPages ?? 1) > 1):
        $roadPage = (int)($activeRoadTripsPage ?? 1);
        $roadTotalPages = (int)($activeRoadTripsTotalPages ?? 1);
        $roadBaseParams = $_GET;
        $roadBaseParams['tab'] = $roadBaseParams['tab'] ?? 'logistics';
    ?>
    <nav class="logistics-pagination" aria-label="<?= t('logistics.road_trips.section_title') ?>">
        <span class="logistics-pagination-info"><?= $roadPage ?> / <?= $roadTotalPages ?> (<?= (int)($activeRoadTripsTotal ?? 0) ?>)</span>
        <div class="logistics-pagination-buttons">
            <?php if ($roadPage > 1): $roadBaseParams['road_page'] = $roadPage - 1; ?>
                <a href="?<?= htmlspecialchars(http_build_query($roadBaseParams), ENT_QUOTES, 'UTF-8') ?>#logistics-road-trips-heading" class="btn btn-xs btn-secondary"><?= t('logistics.pagination_prev') ?></a>
            <?php endif ?>
            <?php if ($roadPage < $roadTotalPages): $roadBaseParams['road_page'] = $roadPage + 1; ?>
                <a href="?<?= htmlspecialchars(http_build_query($roadBaseParams), ENT_QUOTES, 'UTF-8') ?>#logistics-road-trips-heading" class="btn btn-xs btn-secondary"><?= t('logistics.pagination_next') ?></a>
            <?php endif ?>
        </div>
    </nav>
    <?php endif ?>
</section>
