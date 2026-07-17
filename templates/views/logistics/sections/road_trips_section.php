    <!-- ============================================================ -->
    <!-- SEKCJA: Aktywne kursy drogowe (P1.2)                       -->
    <!-- Active road trips in transit                                -->
    <!-- ============================================================ -->
    <?php if (!empty($activeRoadTrips)): ?>
    <section class="logistics-panel" aria-labelledby="logistics-road-trips-heading">
        <div class="logistics-panel-head">
            <h3 id="logistics-road-trips-heading"><?= t('logistics.road_trips.section_title') ?></h3>
            <span><?= (int)($activeRoadTripsTotal ?? count($activeRoadTrips)) ?> <?= t('logistics.road_trips.count_suffix') ?></span>
        </div>
        <div class="logistics-table logistics-table--road-trips">
            <div class="logistics-table-head">
                <span><?= t('logistics.road_trips.col_well') ?></span>
                <span><?= t('logistics.road_trips.col_volume') ?></span>
                <span><?= t('logistics.road_trips.col_trips') ?></span>
                <span><?= t('logistics.road_trips.col_truck') ?></span>
                <span><?= t('logistics.road_trips.col_eta') ?></span>
                <span><?= t('logistics.road_trips.col_remaining') ?></span>
            </div>
            <?php foreach ($activeRoadTrips as $trip):
                $secRem = max(0, (int)($trip['seconds_remaining'] ?? 0));
                $hRem   = (int)floor($secRem / 3600);
                $mRem   = (int)floor(($secRem % 3600) / 60);
                $truckKey = 'logistics.road_trips.truck_' . ($trip['truck_type'] ?? 'standard');
            ?>
            <div class="logistics-table-row">
                <span><?= htmlspecialchars((string)($trip['well_name'] ?? ('#' . (int)$trip['well_id']))) ?></span>
                <span><?= number_format((float)($trip['volume_bbl'] ?? 0), 1, ',', ' ') ?> bbl</span>
                <span><?= (int)($trip['trips_count'] ?? 1) ?></span>
                <span><?= t($truckKey) ?></span>
                <span><?= htmlspecialchars(substr((string)($trip['eta_at'] ?? ''), 0, 16)) ?></span>
                <span class="c-warn">
                    <strong class="road-trip-countdown"
                            data-seconds="<?= $secRem ?>"><?= $hRem ?>h <?= str_pad((string)$mRem, 2, '0', STR_PAD_LEFT) ?>m</strong>
                </span>
            </div>
            <?php endforeach ?>
        </div>
        <?php if ((int)($activeRoadTripsTotalPages ?? 1) > 1):
            $roadPage = (int)($activeRoadTripsPage ?? 1);
            $roadTotalPages = (int)($activeRoadTripsTotalPages ?? 1);
            $roadBaseParams = $_GET;
            $roadBaseParams['tab'] = $roadBaseParams['tab'] ?? 'logistics';
        ?>
        <div class="logistics-pagination">
            <div class="logistics-pagination-info">
                <?= $roadPage ?> / <?= $roadTotalPages ?> (<?= (int)($activeRoadTripsTotal ?? 0) ?>)
            </div>
            <div class="logistics-pagination-buttons">
                <?php if ($roadPage > 1):
                    $roadBaseParams['road_page'] = $roadPage - 1;
                ?>
                <a href="?<?= htmlspecialchars(http_build_query($roadBaseParams)) ?>#logistics-road-trips-heading" class="btn btn-xs btn-secondary">
                    <?= t('logistics.pagination_prev') ?>
                </a>
                <?php endif ?>
                <?php if ($roadPage < $roadTotalPages):
                    $roadBaseParams['road_page'] = $roadPage + 1;
                ?>
                <a href="?<?= htmlspecialchars(http_build_query($roadBaseParams)) ?>#logistics-road-trips-heading" class="btn btn-xs btn-secondary">
                    <?= t('logistics.pagination_next') ?>
                </a>
                <?php endif ?>
            </div>
        </div>
        <?php endif ?>
</section>
    <?php endif ?>
