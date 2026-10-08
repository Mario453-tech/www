<?php
$statsState = (string)($dashboardStats['state'] ?? 'error');
$statsOverview = $dashboardStats['overview'] ?? [];
$statsWells = $dashboardStats['wells'] ?? [];
$statsLogistics = $dashboardStats['logistics'] ?? [];
$statsFinance = $dashboardStats['finance'] ?? [];
$statsPeriod = (string)($dashboardStats['period'] ?? '30d');
$statsEmpty = t('home_dashboard.not_available');
$rate = static fn(?float $value, int $decimals = 0): string => $value === null
    ? $statsEmpty : number_format($value, $decimals, ',', ' ');
$costs = $statsFinance['costs'] ?? [];
$costTotal = array_sum($costs);
?>
<section class="oe-stats" id="company-stats"
         data-api="/api/dashboard-stats.php"
         data-empty="<?= t('home_dashboard.no_history') ?>"
         data-error="<?= t('home_dashboard.load_error') ?>"
         data-na="<?= $statsEmpty ?>"
         data-no-change="<?= t('home_dashboard.no_change') ?>"
         data-locale="<?= htmlspecialchars(tPlain('common.locale'), ENT_QUOTES, 'UTF-8') ?>"
         data-chart-template="<?= t('home_dashboard.chart_summary', ['value' => '{value}', 'period' => '{period}']) ?>">
    <header class="oe-panel-head">
        <div><span class="oe-kicker"><?= t('home_dashboard.stats_kicker') ?></span>
            <h2><?= t('home_dashboard.stats_title') ?></h2></div>
        <div class="oe-periods" role="group" aria-label="<?= t('home_dashboard.stats_kicker') ?>">
            <?php foreach (['7d', '30d', '1y'] as $period): ?>
            <button type="button" data-stat-period="<?= $period ?>" aria-pressed="<?= $statsPeriod === $period ? 'true' : 'false' ?>"><?= t('home_dashboard.period_' . $period) ?></button>
            <?php endforeach; ?>
        </div>
    </header>
    <div class="oe-tabs" role="tablist" aria-label="<?= t('home_dashboard.stats_kicker') ?>" data-tab-set="stats">
        <?php foreach (['overview', 'wells', 'logistics', 'finance'] as $index => $tab): ?>
        <button id="oe-stat-tab-<?= $tab ?>" role="tab" type="button" aria-controls="oe-stat-panel-<?= $tab ?>" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>" tabindex="<?= $index === 0 ? '0' : '-1' ?>"><?= t('home_dashboard.tab_' . $tab) ?></button>
        <?php endforeach; ?>
    </div>

    <div class="oe-tab-panel" id="oe-stat-panel-overview" role="tabpanel" aria-labelledby="oe-stat-tab-overview">
        <div class="oe-metrics oe-metrics--three">
            <div class="oe-metric"><span><?= t('home_dashboard.production') ?></span><strong data-stat="production_rate"><?= $rate($statsOverview['production_rate'] ?? null) ?></strong><small<?= isset($statsOverview['production_rate']) ? '' : ' hidden' ?>>bbl/h</small><p data-stat="production_change"><?= isset($statsOverview['production_change']) ? $rate((float)$statsOverview['production_change'], 1) . '%' : t('home_dashboard.no_change') ?></p></div>
            <div class="oe-metric"><span><?= t('home_dashboard.revenue') ?></span><strong data-stat="revenue_rate"><?= $rate($statsOverview['revenue_rate'] ?? null) ?></strong><small<?= isset($statsOverview['revenue_rate']) ? '' : ' hidden' ?>>PLN/h</small><p data-stat="revenue_change"><?= isset($statsOverview['revenue_change']) ? $rate((float)$statsOverview['revenue_change'], 1) . '%' : t('home_dashboard.no_change') ?></p></div>
            <div class="oe-metric"><span><?= t('home_dashboard.active_wells') ?></span><strong data-stat="active_wells"><?= (int)($statsWells['active'] ?? 0) ?></strong><small>/ <span data-stat="total_wells"><?= (int)($statsWells['total'] ?? 0) ?></span></small></div>
        </div>
        <div class="oe-stat-grid">
            <figure class="oe-stat-box oe-chart">
                <figcaption><?= t('home_dashboard.oil_chart') ?> <span>bbl/h</span></figcaption>
                <p class="oe-state" data-chart-state<?= $statsState === 'ready' && !empty($dashboardStats['series']) ? ' hidden' : '' ?>><?= $statsState === 'error' ? t('home_dashboard.load_error') : t('home_dashboard.no_history') ?></p>
                <svg data-chart viewBox="0 0 640 200" role="img" aria-label="<?= t('home_dashboard.oil_chart') ?>"<?= $statsState === 'ready' && !empty($dashboardStats['series']) ? '' : ' hidden' ?>">
                    <path class="oe-chart-grid" d="M24 30H616 M24 80H616 M24 130H616 M24 180H616"/>
                    <polyline data-chart-line points="<?= htmlspecialchars((string)($dashboardStats['chart_points'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"/>
                </svg>
                <p class="oe-chart-caption" data-chart-summary><?= $statsState === 'ready' ? t('home_dashboard.chart_summary', ['value' => $rate($statsOverview['production_rate'] ?? null), 'period' => t('home_dashboard.period_' . $statsPeriod)]) : '' ?></p>
            </figure>
            <div class="oe-stat-box">
                <div class="oe-box-heading"><h3><?= t('home_dashboard.recent_wells') ?></h3><a href="<?= url('home') ?>#wells-heading"><?= t('home_dashboard.all_wells') ?></a></div>
                <ul class="oe-well-list" data-top-wells>
                    <?php foreach ($statsWells['top'] ?? [] as $topWell): ?>
                    <li><a href="<?= url('home') ?>#wg-card-<?= (int)$topWell['id'] ?>"><?= htmlspecialchars($topWell['name'], ENT_QUOTES, 'UTF-8') ?></a><span><?= $rate((float)$topWell['production']) ?> bbl/h</span><span><?= $rate((float)$topWell['condition']) ?>%</span></li>
                    <?php endforeach; ?>
                </ul>
                <p class="oe-state" data-top-empty<?= empty($statsWells['top']) ? '' : ' hidden' ?>><?= t('home_dashboard.no_wells') ?></p>
            </div>
        </div>
    </div>

    <div class="oe-tab-panel" id="oe-stat-panel-wells" role="tabpanel" aria-labelledby="oe-stat-tab-wells" hidden>
        <div class="oe-metrics oe-metrics--four">
            <?php foreach (['total', 'active', 'attention', 'critical'] as $key): ?>
            <div class="oe-metric"><span><?= t('home_dashboard.' . ($key === 'total' ? 'total_wells' : ($key === 'active' ? 'active_wells' : $key))) ?></span><strong data-stat="wells_<?= $key ?>"><?= (int)($statsWells[$key] ?? 0) ?></strong></div>
            <?php endforeach; ?>
        </div>
        <div class="oe-stat-box"><h3><?= t('home_dashboard.recent_wells') ?></h3><p><?= t('home_dashboard.base_production') ?> (bbl/h)</p><ul class="oe-well-list" data-well-ranking>
            <?php foreach ($statsWells['top'] ?? [] as $topWell): ?><li><a href="<?= url('home') ?>#wg-card-<?= (int)$topWell['id'] ?>"><?= htmlspecialchars($topWell['name'], ENT_QUOTES, 'UTF-8') ?></a><span><?= $rate((float)$topWell['production']) ?> bbl/h</span><span><?= $rate((float)$topWell['condition']) ?>%</span></li><?php endforeach; ?>
        </ul></div>
    </div>

    <div class="oe-tab-panel" id="oe-stat-panel-logistics" role="tabpanel" aria-labelledby="oe-stat-tab-logistics" hidden>
        <div class="oe-metrics oe-metrics--four">
            <?php foreach (['road', 'pipeline', 'sea'] as $mode): ?>
            <div class="oe-metric"><span><?= t('home_dashboard.' . $mode) ?></span><strong data-stat="mix_<?= $mode ?>"><?= (int)($statsLogistics['mix'][$mode] ?? 0) ?></strong></div>
            <?php endforeach; ?>
            <div class="oe-metric"><span><?= t('home_dashboard.active_routes') ?></span><strong data-stat="active_routes"><?= (int)($statsLogistics['active_routes'] ?? 0) ?></strong></div>
        </div>
        <div class="oe-metrics oe-metrics--three">
            <div class="oe-metric"><span><?= t('home_dashboard.on_time_road') ?></span><strong data-stat="on_time_pct"><?= $rate($statsLogistics['on_time_pct'] ?? null, 1) ?></strong><small<?= isset($statsLogistics['on_time_pct']) ? '' : ' hidden' ?>>%</small></div>
            <div class="oe-metric"><span><?= t('home_dashboard.transport_loss') ?></span><strong data-stat="loss_bbl"><?= $rate($statsLogistics['loss_bbl'] ?? null, 1) ?></strong><small<?= isset($statsLogistics['loss_bbl']) ? '' : ' hidden' ?>>bbl</small></div>
            <div class="oe-metric"><span><?= t('home_dashboard.transport_cost') ?></span><strong data-stat="transport_cost_rate"><?= $rate($statsLogistics['cost_rate'] ?? null) ?></strong><small<?= isset($statsLogistics['cost_rate']) ? '' : ' hidden' ?>>PLN/h</small></div>
        </div>
    </div>

    <div class="oe-tab-panel" id="oe-stat-panel-finance" role="tabpanel" aria-labelledby="oe-stat-tab-finance" hidden>
        <div class="oe-metrics oe-metrics--three">
            <div class="oe-metric"><span><?= t('home_dashboard.revenue') ?></span><strong data-stat="finance_revenue_rate"><?= $rate($statsFinance['revenue_rate'] ?? null) ?></strong><small<?= isset($statsFinance['revenue_rate']) ? '' : ' hidden' ?>>PLN/h</small></div>
            <div class="oe-metric"><span><?= t('home_dashboard.operating_cost') ?></span><strong data-stat="finance_cost_rate"><?= $rate($statsFinance['cost_rate'] ?? null) ?></strong><small<?= isset($statsFinance['cost_rate']) ? '' : ' hidden' ?>>PLN/h</small></div>
            <div class="oe-metric"><span><?= t('home_dashboard.operating_profit') ?></span><strong data-stat="finance_net_rate"><?= $rate($statsFinance['net_rate'] ?? null) ?></strong><small<?= isset($statsFinance['net_rate']) ? '' : ' hidden' ?>>PLN/h</small></div>
        </div>
        <div class="oe-stat-box"><h3><?= t('home_dashboard.cost_structure') ?></h3><div class="oe-cost-list">
            <?php foreach (['extraction', 'logistics', 'staff', 'other'] as $costKey): ?>
            <div><span><?= t('home_dashboard.cost_' . $costKey) ?></span><progress data-cost-progress="<?= $costKey ?>" value="<?= (float)($costs[$costKey] ?? 0) ?>" max="<?= max(1, $costTotal) ?>"></progress><strong data-stat="cost_<?= $costKey ?>"><?= $statsState === 'ready' ? $rate((float)($costs[$costKey] ?? 0)) : $statsEmpty ?></strong></div>
            <?php endforeach; ?>
        </div></div>
    </div>
</section>
