<?php
/**
 * Admin Visit Statistics View.
 * Widok statystyk odwiedzin w panelu admina.
 */
declare(strict_types=1);

$summary = $dashboardData['summary'] ?? [];
$countries = $dashboardData['countries'] ?? [];
$timeline = $dashboardData['timeline'] ?? [];
$topPages = $dashboardData['topPages'] ?? [];
$devices = $dashboardData['devices'] ?? [];
$recentEvents = $dashboardData['recentEvents'] ?? [];
$currPeriod = $dashboardData['period'] ?? '7d';
$countryFilter = $dashboardData['countryFilter'] ?? null;

$deviceSvgs = [
    'desktop' => '<svg class="device-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',
    'mobile'  => '<svg class="device-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>',
    'tablet'  => '<svg class="device-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>',
    'bot'     => '<svg class="device-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><line x1="12" y1="7" x2="12" y2="11"/></svg>',
];
$deviceLabels = [
    'desktop' => t('admin.visits.device_desktop'),
    'mobile'  => t('admin.visits.device_mobile'),
    'tablet'  => t('admin.visits.device_tablet'),
    'bot'     => t('admin.visits.device_bot'),
];
?>

<div class="admin-wrap visits-page">
    <div class="visits-header">
        <div class="visits-header-left">
            <h1 class="admin-title"><?= t('admin.visits.title') ?></h1>
            <p class="admin-subtitle"><?= t('admin.visits.subtitle') ?></p>
        </div>
        <div class="visits-header-right">
            <div class="visits-period-tabs" role="tablist">
                <?php
                $periods = [
                    'today' => t('admin.visits.period_today'),
                    '7d'    => t('admin.visits.period_7d'),
                    '30d'   => t('admin.visits.period_30d'),
                    '90d'   => t('admin.visits.period_90d'),
                    'all'   => t('admin.visits.period_all'),
                ];
                foreach ($periods as $pKey => $pLabel):
                    $activeClass = ($currPeriod === $pKey) ? ' active' : '';
                    $href = '/admin/visits.php?period=' . urlencode($pKey) . ($countryFilter ? '&country=' . urlencode($countryFilter) : '');
                ?>
                <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="visits-tab-btn<?= $activeClass ?>">
                    <?= htmlspecialchars($pLabel, ENT_QUOTES, 'UTF-8') ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if ($countryFilter): ?>
    <div class="visits-filter-bar">
        <span><?= t('admin.visits.filter_by_country') ?> <strong><?= htmlspecialchars(VisitTrackerService::getCountryName($countryFilter), ENT_QUOTES, 'UTF-8') ?> [<?= htmlspecialchars($countryFilter, ENT_QUOTES, 'UTF-8') ?>]</strong></span>
        <a href="/admin/visits.php?period=<?= urlencode($currPeriod) ?>" class="visits-filter-clear">&times; <?= t('admin.visits.clear_filter') ?></a>
    </div>
    <?php endif; ?>

    <?php if (!empty($msg) || !empty($err)): ?>
    <div class="visits-flash-container" id="visits-flash">
        <?php if (!empty($msg)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if (!empty($err)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- KPI Summary Grid -->
    <div class="visits-kpi-grid">
        <div class="visits-kpi-card">
            <div class="visits-kpi-label"><?= t('admin.visits.kpi_uv') ?></div>
            <div class="visits-kpi-value text-accent"><?= number_format((int)($summary['total_uv'] ?? 0), 0, ',', ' ') ?></div>
            <div class="visits-kpi-sub"><?= t('admin.visits.kpi_uv_sub') ?></div>
        </div>
        <div class="visits-kpi-card">
            <div class="visits-kpi-label"><?= t('admin.visits.kpi_pv') ?></div>
            <div class="visits-kpi-value text-primary"><?= number_format((int)($summary['total_pv'] ?? 0), 0, ',', ' ') ?></div>
            <div class="visits-kpi-sub"><?= t('admin.visits.kpi_pv_sub') ?></div>
        </div>
        <div class="visits-kpi-card">
            <div class="visits-kpi-label"><?= t('admin.visits.kpi_players') ?></div>
            <div class="visits-kpi-value text-success"><?= number_format((int)($summary['player_views'] ?? 0), 0, ',', ' ') ?></div>
            <div class="visits-kpi-sub"><?= t('admin.visits.kpi_players_sub') ?></div>
        </div>
        <div class="visits-kpi-card">
            <div class="visits-kpi-label"><?= t('admin.visits.kpi_guests') ?></div>
            <div class="visits-kpi-value text-warning"><?= number_format((int)($summary['guest_views'] ?? 0), 0, ',', ' ') ?></div>
            <div class="visits-kpi-sub"><?= t('admin.visits.kpi_guests_sub') ?></div>
        </div>
        <div class="visits-kpi-card">
            <div class="visits-kpi-label"><?= t('admin.visits.kpi_avg') ?></div>
            <div class="visits-kpi-value"><?= number_format((float)($summary['avg_pv_per_uv'] ?? 0), 2, ',', ' ') ?></div>
            <div class="visits-kpi-sub"><?= t('admin.visits.kpi_avg_sub') ?></div>
        </div>
        <div class="visits-kpi-card">
            <div class="visits-kpi-label"><?= t('admin.visits.kpi_top_country') ?></div>
            <div class="visits-kpi-value visits-top-country"><?= htmlspecialchars((string)($summary['top_country'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="visits-kpi-sub"><?= t('admin.visits.kpi_top_country_sub', ['count' => number_format((int)($summary['top_country_uv'] ?? 0), 0, ',', ' ')]) ?></div>
        </div>
    </div>

    <!-- Timeline Chart Section -->
    <div class="admin-card visits-chart-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title"><?= t('admin.visits.section_trend') ?></h2>
            <div class="visits-chart-legend">
                <span class="legend-item"><span class="legend-dot dot-pv"></span> <?= t('admin.visits.legend_pv') ?></span>
                <span class="legend-item"><span class="legend-dot dot-uv"></span> <?= t('admin.visits.legend_uv') ?></span>
            </div>
        </div>
        <div class="visits-chart-wrapper" id="visits-chart-container">
            <canvas id="visits-timeline-canvas" height="240"></canvas>
            <div id="visits-chart-tooltip" class="visits-chart-tooltip" hidden></div>
        </div>
    </div>

    <!-- Main Two-Column Analytics Layout -->
    <div class="visits-analytics-grid">
        <!-- Countries Breakdown (Left Column) -->
        <div class="admin-card visits-countries-card">
            <div class="admin-card-header">
                <h2 class="admin-card-title"><?= t('admin.visits.section_countries') ?></h2>
                <span class="badge badge-info"><?= t('admin.visits.countries_count', ['count' => count($countries)]) ?></span>
            </div>
            <div class="visits-grid-table-wrap">
                <div class="visits-grid-table visits-grid-countries">
                    <div class="visits-grid-row visits-grid-header">
                        <div class="visits-grid-cell"><?= t('admin.visits.th_country') ?></div>
                        <div class="visits-grid-cell text-right"><?= t('admin.visits.th_uv') ?></div>
                        <div class="visits-grid-cell text-right"><?= t('admin.visits.th_pv') ?></div>
                        <div class="visits-grid-cell visits-col-share"><?= t('admin.visits.th_share') ?></div>
                    </div>
                    <?php if (empty($countries)): ?>
                    <div class="visits-empty-state"><?= t('admin.visits.empty_data') ?></div>
                    <?php else: foreach ($countries as $c):
                        $cCode = htmlspecialchars((string)$c['code'], ENT_QUOTES, 'UTF-8');
                        $cName = htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8');
                        $pct = (float)$c['percent'];
                    ?>
                    <div class="visits-grid-row">
                        <div class="visits-grid-cell visits-country-cell">
                            <span class="country-badge font-mono"><?= $cCode ?></span>
                            <a href="/admin/visits.php?period=<?= urlencode($currPeriod) ?>&country=<?= urlencode((string)$c['code']) ?>" class="country-name-link" title="<?= t('admin.visits.filter_country_tooltip') ?>">
                                <?= $cName ?>
                            </a>
                        </div>
                        <div class="visits-grid-cell text-right font-mono font-bold"><?= number_format((int)$c['unique_visitors'], 0, ',', ' ') ?></div>
                        <div class="visits-grid-cell text-right font-mono text-muted"><?= number_format((int)$c['page_views'], 0, ',', ' ') ?></div>
                        <div class="visits-grid-cell">
                            <div class="visits-progress-wrap">
                                <div class="visits-progress-bar" style="--bar-pct: <?= min(100.0, max(0.0, $pct)) ?>%;"></div>
                                <span class="visits-progress-pct"><?= number_format($pct, 1, ',', ' ') ?>%</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Pages & Devices -->
        <div class="visits-side-column">
            <!-- Top Visited Pages -->
            <div class="admin-card visits-pages-card">
                <div class="admin-card-header">
                    <h2 class="admin-card-title"><?= t('admin.visits.section_pages') ?></h2>
                </div>
                <div class="visits-grid-table-wrap">
                    <div class="visits-grid-table visits-grid-pages">
                        <div class="visits-grid-row visits-grid-header">
                            <div class="visits-grid-cell"><?= t('admin.visits.th_page') ?></div>
                            <div class="visits-grid-cell text-right"><?= t('admin.visits.th_pv') ?></div>
                            <div class="visits-grid-cell text-right"><?= t('admin.visits.th_uv') ?></div>
                        </div>
                        <?php if (empty($topPages)): ?>
                        <div class="visits-empty-state"><?= t('admin.visits.empty_data') ?></div>
                        <?php else: foreach ($topPages as $p): ?>
                        <div class="visits-grid-row">
                            <div class="visits-grid-cell font-mono text-truncate" title="<?= htmlspecialchars((string)$p['page_path'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars((string)$p['page_path'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div class="visits-grid-cell text-right font-mono font-bold"><?= number_format((int)$p['page_views'], 0, ',', ' ') ?></div>
                            <div class="visits-grid-cell text-right font-mono text-muted"><?= number_format((int)$p['unique_visitors'], 0, ',', ' ') ?></div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>

            <!-- Devices Breakdown -->
            <div class="admin-card visits-devices-card">
                <div class="admin-card-header">
                    <h2 class="admin-card-title"><?= t('admin.visits.section_devices') ?></h2>
                </div>
                <div class="visits-devices-list">
                    <?php
                    $totalDevPv = 0;
                    foreach ($devices as $d) {
                        $totalDevPv += (int)$d['page_views'];
                    }

                    foreach ($devices as $d):
                        $dType = (string)$d['device_type'];
                        $dLabel = $deviceLabels[$dType] ?? $dType;
                        $dSvg = $deviceSvgs[$dType] ?? '';
                        $dPv = (int)$d['page_views'];
                        $dPct = $totalDevPv > 0 ? round(($dPv / $totalDevPv) * 100, 1) : 0.0;
                    ?>
                    <div class="visits-device-item">
                        <div class="visits-device-header">
                            <span class="device-name"><?= $dSvg ?> <?= htmlspecialchars($dLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="device-count font-mono"><?= number_format($dPv, 0, ',', ' ') ?> (<?= number_format($dPct, 1, ',', ' ') ?>%)</span>
                        </div>
                        <div class="visits-progress-wrap">
                            <div class="visits-progress-bar bar-<?= htmlspecialchars($dType, ENT_QUOTES, 'UTF-8') ?>" style="--bar-pct: <?= $dPct ?>%;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Recent Events Feed -->
    <div class="admin-card visits-live-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title"><?= t('admin.visits.section_live') ?></h2>
            <span class="badge badge-success"><?= t('admin.visits.live_badge', ['count' => count($recentEvents)]) ?></span>
        </div>
        <div class="visits-grid-table-wrap">
            <div class="visits-grid-table visits-grid-live">
                <div class="visits-grid-row visits-grid-header">
                    <div class="visits-grid-cell"><?= t('admin.visits.th_time') ?></div>
                    <div class="visits-grid-cell"><?= t('admin.visits.th_country') ?></div>
                    <div class="visits-grid-cell"><?= t('admin.visits.th_device') ?></div>
                    <div class="visits-grid-cell"><?= t('admin.visits.th_page') ?></div>
                    <div class="visits-grid-cell"><?= t('admin.visits.th_status') ?></div>
                    <div class="visits-grid-cell"><?= t('admin.visits.th_referrer') ?></div>
                </div>
                <?php if (empty($recentEvents)): ?>
                <div class="visits-empty-state"><?= t('admin.visits.empty_data') ?></div>
                <?php else: foreach ($recentEvents as $e):
                    $cCode = (string)$e['country_code'];
                    $cName = VisitTrackerService::getCountryName($cCode);
                    $pId = $e['player_id'] ? (int)$e['player_id'] : null;
                    $dev = (string)$e['device_type'];
                    $refRaw = (string)$e['referrer_host'];
                    $refLabel = match ($refRaw) {
                        'direct'   => t('admin.visits.ref_direct'),
                        'internal' => t('admin.visits.ref_internal'),
                        default    => $refRaw,
                    };
                ?>
                <div class="visits-grid-row">
                    <div class="visits-grid-cell font-mono text-muted text-nowrap"><?= htmlspecialchars(substr((string)$e['created_at'], 5, 14), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="visits-grid-cell visits-country-cell">
                        <span class="country-badge font-mono"><?= htmlspecialchars($cCode, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="text-truncate"><?= htmlspecialchars($cName, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="visits-grid-cell">
                        <span class="device-pill pill-<?= htmlspecialchars($dev, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars(ucfirst($dev), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                    <div class="visits-grid-cell font-mono text-truncate"><?= htmlspecialchars((string)$e['page_path'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="visits-grid-cell">
                        <?php if ($pId): ?>
                        <a href="/admin/player.php?id=<?= $pId ?>" class="badge badge-success" title="<?= t('admin.visits.status_player_profile') ?>">
                            <?= t('admin.visits.status_player') ?> #<?= $pId ?>
                        </a>
                        <?php elseif ($dev === 'bot'): ?>
                        <span class="badge badge-secondary"><?= t('admin.visits.status_bot') ?></span>
                        <?php else: ?>
                        <span class="badge badge-warning"><?= t('admin.visits.status_guest') ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="visits-grid-cell text-muted text-truncate" title="<?= htmlspecialchars($refLabel, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($refLabel, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- Maintenance / Cleanup Card -->
    <div class="admin-card visits-maintenance-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title"><?= t('admin.visits.maintenance_title') ?></h2>
        </div>
        <div class="visits-maintenance-body">
            <p class="text-muted">
                <?= t('admin.visits.maintenance_desc') ?>
            </p>
            <form method="POST" action="/admin/visits.php" class="visits-cleanup-form" id="visits-cleanup-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CSRF::getToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="cleanup_events">
                <input type="hidden" name="days" value="60">
                <button type="submit" class="btn btn-secondary btn-sm" id="btn-cleanup-visits">
                    <?= t('admin.visits.cleanup_btn', ['days' => 60]) ?>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
window.VISITS_DATA = {
    timeline: <?= json_encode($timeline, JSON_UNESCAPED_UNICODE) ?>
};
window.VISITS_LANG = <?= json_encode([
    'confirm_cleanup' => t('admin.visits.confirm_cleanup', ['days' => 60]),
    'players'         => t('admin.visits.tooltip_players'),
    'guests'          => t('admin.visits.tooltip_guests'),
    'uv'              => t('admin.visits.legend_uv'),
    'pv'              => t('admin.visits.legend_pv'),
], JSON_UNESCAPED_UNICODE) ?>;
</script>
