    <!--  -->
    <!-- SEKCJA: Dostawy morskie (Etap 5 - tankowce w drodze)              -->
    <!--  -->
    <?php
        $marineDeliveriesList = is_array($marineDeliveries ?? null) ? $marineDeliveries : [];
        $marineBuffersList = is_array($marineBuffers ?? null) ? $marineBuffers : [];
        $marineHistoryList = is_array($marineHistory ?? null) ? $marineHistory : [];
        $marineInTransitBbl = (float)($marineInTransitBbl ?? 0.0);
        $marineBufferedBbl = array_sum(array_map(static fn($row) => (float)($row['marine_buffer_bbl'] ?? 0.0), $marineBuffersList));
        $marineTransitOnlyBbl = max(0.0, $marineInTransitBbl - $marineBufferedBbl);
        $marineMinLoadBbl = max(0.0, (float)($marineMinLoadBbl ?? 0.0));
        $wellTransportTypes = array_column(array_filter($wells, 'is_array'), 'transport');
        $hasMarineSection = !empty($marineDeliveriesList) || !empty($marineBuffersList) || !empty($marineHistoryList) || $marineInTransitBbl > 0;
        if ($hasMarineSection || in_array('tankowiec', $wellTransportTypes, true)):
    ?>
    <section class="logistics-panel" aria-labelledby="logistics-marine-heading">
        <div class="logistics-panel-head">
            <h3 id="logistics-marine-heading"> <?= t('marine.section_title') ?></h3>
            <span><?= t('marine.section_desc') ?></span>
        </div>

        <!-- KPI morskie / Marine KPI -->
        <div class="logistics-insight-summary">
            <div class="logistics-insight-pill <?= $marineTransitOnlyBbl > 0 ? 'logistics-insight-pill--info' : 'logistics-insight-pill--ok' ?>">
                <span><?= t('marine.kpi_in_transit') ?></span>
                <strong><?= number_format($marineTransitOnlyBbl, 1, ',', ' ') ?> <?= t('common.bbl') ?></strong>
            </div>
            <div class="logistics-insight-pill <?= $marineBufferedBbl > 0 ? 'logistics-insight-pill--warn' : 'logistics-insight-pill--ok' ?>">
                <span><?= t('marine.kpi_buffered') ?></span>
                <strong><?= number_format($marineBufferedBbl, 1, ',', ' ') ?> <?= t('common.bbl') ?></strong>
            </div>
            <div class="logistics-insight-pill logistics-insight-pill--info">
                <span><?= t('marine.kpi_active') ?></span>
                <strong><?= (int)($marineDeliveriesTotal ?? count($marineDeliveriesList)) ?></strong>
            </div>
        </div>

        <!-- Bufory tankowcow / Tanker buffers -->
        <?php if (!empty($marineBuffersList)): ?>
        <div class="logistics-section-title"><?= t('marine.buffer_title') ?></div>
        <div class="logistics-table logistics-table--marine-buffer">
            <div class="logistics-table-head">
                <span><?= t('marine.col_well') ?></span>
                <span><?= t('marine.col_buffer') ?></span>
                <span><?= t('marine.col_missing') ?></span>
                <span><?= t('marine.col_progress') ?></span>
            </div>
            <?php foreach ($marineBuffersList as $buffer):
                $bufferBbl = max(0.0, (float)($buffer['marine_buffer_bbl'] ?? 0.0));
                $thresholdBbl = max(0.0, (float)($buffer['min_load_bbl'] ?? $marineMinLoadBbl));
                $missingBbl = $thresholdBbl > 0 ? max(0.0, $thresholdBbl - $bufferBbl) : 0.0;
                $bufferPct = $thresholdBbl > 0 ? min(100.0, round($bufferBbl / $thresholdBbl * 100, 1)) : 100.0;
                $bufferClass = $bufferPct >= 90 ? 'full' : ($bufferPct >= 60 ? 'mid' : 'low');
                $bufferTextClass = $bufferPct >= 90 ? 'c-good' : ($bufferPct >= 60 ? 'c-warn' : 'c-muted2');
                $bufferWellLabel = ($buffer['well_name'] ?? null)
                    ? htmlspecialchars((string)$buffer['well_name'])
                    : t('marine.well_unknown', ['id' => (int)($buffer['well_id'] ?? 0)]);
            ?>
            <div class="logistics-table-row">
                <span><?= $bufferWellLabel ?></span>
                <span><?= number_format($bufferBbl, 1, ',', ' ') ?> / <?= number_format($thresholdBbl, 0, ',', ' ') ?> <?= t('common.bbl') ?></span>
                <span class="<?= $missingBbl <= 0.0 ? 'c-good' : 'c-warn' ?>">
                    <?= $missingBbl <= 0.0
                        ? t('marine.buffer_ready')
                        : t('marine.buffer_missing', ['bbl' => number_format($missingBbl, 1, ',', ' ')]) ?>
                </span>
                <span class="marine-buffer-progress">
                    <span class="hub-buffer-bar">
                                <span class="hub-buffer-bar__fill hub-buffer-bar__fill--<?= $bufferClass ?>" data-progress-width="<?= $bufferPct ?>"></span>
                    </span>
                    <small class="<?= $bufferTextClass ?>"><?= number_format($bufferPct, 1, ',', ' ') ?>%</small>
                </span>
            </div>
            <?php endforeach ?>
        </div>
        <?php endif ?>

        <!-- Aktywne dostawy / Active deliveries -->
        <?php if (empty($marineDeliveriesList)): ?>
            <div class="logistics-empty"><?= t('marine.no_deliveries') ?></div>
        <?php else: ?>
        <div class="logistics-table">
                <div class="logistics-table-head logistics-table-row--marine">
                <span><?= t('marine.col_well') ?></span>
                <span><?= t('marine.col_port') ?></span>
                <span><?= t('marine.col_volume') ?></span>
                <span><?= t('marine.col_status') ?></span>
                <span><?= t('marine.col_eta') ?></span>
            </div>
            <?php foreach ($marineDeliveriesList as $del):
                $delStatus = (string)($del['status'] ?? 'in_transit');
                $statusClass = match($delStatus) {
                    'departing'        => 'c-muted2',
                    'in_transit'       => '',
                    'waiting_for_port' => 'c-warn',
                    'processing'       => 'c-good',
                    'delayed'          => 'c-bad',
                    default            => '',
                };
                $etaTs   = strtotime((string)($del['eta_at'] ?? ''));
                $etaStr  = $etaTs ? date('d.m H:i', $etaTs) : '-';
                $wellLabel = ($del['well_name'] ?? null)
                    ? htmlspecialchars($del['well_name'])
                    : t('marine.well_unknown', ['id' => (int)($del['well_id'] ?? 0)]);
                $portLabel = ($del['port_name'] ?? null)
                    ? htmlspecialchars($del['port_name'])
                    : t('marine.port_unknown');
            ?>
                    <div class="logistics-table-row logistics-table-row--marine">
                <span><?= $wellLabel ?></span>
                <span><?= $portLabel ?></span>
                <span><?= number_format((float)($del['volume_bbl'] ?? 0), 1, ',', ' ') ?> bbl</span>
                <span class="<?= $statusClass ?>">
                    <?= t('marine.status_' . $delStatus) ?>
                    <?php if ((int)($del['delay_ticks'] ?? 0) > 0): ?>
                            <small class="c-bad logistics-marine-capacity-warning">
                        <?= t('marine.delay_ticks', ['n' => (int)$del['delay_ticks']]) ?>
                    </small>
                    <?php endif ?>
                </span>
                <span><?= htmlspecialchars($etaStr) ?></span>
            </div>
            <?php endforeach ?>
        </div>
        <?php endif ?>
        <?php if ((int)($marineDeliveriesTotalPages ?? 1) > 1):
            $marinePage = (int)($marineDeliveriesPage ?? 1);
            $marineTotalPages = (int)($marineDeliveriesTotalPages ?? 1);
            $marineBaseParams = $_GET;
            $marineBaseParams['tab'] = $marineBaseParams['tab'] ?? 'logistics';
        ?>
        <div class="logistics-pagination">
            <div class="logistics-pagination-info">
                <?= $marinePage ?> / <?= $marineTotalPages ?> (<?= (int)($marineDeliveriesTotal ?? 0) ?>)
            </div>
            <div class="logistics-pagination-buttons">
                <?php if ($marinePage > 1):
                    $marineBaseParams['marine_page'] = $marinePage - 1;
                ?>
                <a href="?<?= htmlspecialchars(http_build_query($marineBaseParams)) ?>#logistics-marine-heading" class="btn btn-xs btn-secondary">
                    <?= t('logistics.pagination_prev') ?>
                </a>
                <?php endif ?>
                <?php if ($marinePage < $marineTotalPages):
                    $marineBaseParams['marine_page'] = $marinePage + 1;
                ?>
                <a href="?<?= htmlspecialchars(http_build_query($marineBaseParams)) ?>#logistics-marine-heading" class="btn btn-xs btn-secondary">
                    <?= t('logistics.pagination_next') ?>
                </a>
                <?php endif ?>
            </div>
        </div>
        <?php endif ?>

        <!-- Historia dostaw / Delivery history -->
        <div class="marine-history-section">
            <div class="logistics-section-title"><?= t('marine.history_title') ?></div>
            <?php if (empty($marineHistoryList)): ?>
                <div class="logistics-empty"><?= t('marine.no_history') ?></div>
            <?php else: ?>
            <div class="logistics-table logistics-table--marine-history">
                <div class="logistics-table-head">
                    <span><?= t('marine.col_well') ?></span>
                    <span><?= t('marine.col_port') ?></span>
                    <span><?= t('marine.col_volume') ?></span>
                    <span><?= t('marine.col_status') ?></span>
                    <span><?= t('marine.col_completed_at') ?></span>
                </div>
                <?php foreach ($marineHistoryList as $hist):
                    $hStatus = (string)($hist['status'] ?? 'delivered');
                    $hClass  = $hStatus === 'lost' ? 'c-bad' : 'c-good';
                    $hTs     = strtotime((string)($hist['delivered_at'] ?? $hist['created_at'] ?? ''));
                    $hDate   = $hTs ? date('d.m H:i', $hTs) : '-';
                    $hWell   = ($hist['well_name'] ?? null)
                        ? htmlspecialchars($hist['well_name'])
                        : t('marine.well_unknown', ['id' => (int)($hist['well_id'] ?? 0)]);
                    $hPort   = ($hist['port_name'] ?? null)
                        ? htmlspecialchars($hist['port_name'])
                        : t('marine.port_unknown');
                ?>
                <div class="logistics-table-row logistics-table-row--history">
                    <span><?= $hWell ?></span>
                    <span><?= $hPort ?></span>
                    <span><?= number_format((float)($hist['volume_bbl'] ?? 0), 1, ',', ' ') ?> bbl</span>
                    <span class="<?= $hClass ?>"><?= t('marine.status_' . $hStatus) ?></span>
                    <span><?= htmlspecialchars($hDate) ?></span>
                </div>
                <?php endforeach ?>
            </div>
            <?php if ((int)($marineHistoryTotalPages ?? 1) > 1):
                $marineHistoryPage = (int)($marineHistoryPage ?? 1);
                $marineHistoryTotalPages = (int)($marineHistoryTotalPages ?? 1);
                $marineHistoryBaseParams = $_GET;
                $marineHistoryBaseParams['tab'] = $marineHistoryBaseParams['tab'] ?? 'logistics';
            ?>
            <div class="logistics-pagination">
                <div class="logistics-pagination-info">
                    <?= $marineHistoryPage ?> / <?= $marineHistoryTotalPages ?> (<?= (int)($marineHistoryTotal ?? 0) ?>)
                </div>
                <div class="logistics-pagination-buttons">
                    <?php if ($marineHistoryPage > 1):
                        $marineHistoryBaseParams['marine_history_page'] = $marineHistoryPage - 1;
                    ?>
                    <a href="?<?= htmlspecialchars(http_build_query($marineHistoryBaseParams)) ?>#logistics-marine-heading" class="btn btn-xs btn-secondary">
                        <?= t('logistics.pagination_prev') ?>
                    </a>
                    <?php endif ?>
                    <?php if ($marineHistoryPage < $marineHistoryTotalPages):
                        $marineHistoryBaseParams['marine_history_page'] = $marineHistoryPage + 1;
                    ?>
                    <a href="?<?= htmlspecialchars(http_build_query($marineHistoryBaseParams)) ?>#logistics-marine-heading" class="btn btn-xs btn-secondary">
                        <?= t('logistics.pagination_next') ?>
                    </a>
                    <?php endif ?>
                </div>
            </div>
            <?php endif ?>
            <?php endif ?>
        </div>
    </section>
    <?php endif ?>
