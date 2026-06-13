<?php
extract($viewData ?? [], EXTR_SKIP);
$locale = $_SESSION['locale'] ?? $_COOKIE['locale'] ?? 'pl';

$net      = (float)($last['net_profit'] ?? 0);
$netH     = $net * 12;
$lossVal  = (float)($last['loss_value'] ?? 0);
$rev      = (float)($last['revenue'] ?? 0);
$lossPct  = $rev > 0 ? ($lossVal / $rev * 100) : 0;

$tRev               = (float)($summary['total_revenue'] ?? 0);
$tGross             = (float)($summary['total_gross'] ?? 0);
$tOpex              = (float)($summary['total_opex'] ?? 0);
$tHubUsage          = (float)($summary['total_hub_usage_cost'] ?? 0);
$tWellOpex          = max(0.0, $tOpex - $tHubUsage);
$tSal               = (float)($summary['total_salary'] ?? 0);
$tTr                = (float)($summary['total_transport'] ?? 0);
$tInc               = (float)($summary['total_incident'] ?? 0);
$tTax               = (float)($summary['total_tax'] ?? 0);
$tLoss              = (float)($summary['total_loss_value'] ?? 0);
$tHubLoss           = (float)($summary['total_hub_loss_value'] ?? 0);
$tFallbackLoss      = (float)($summary['total_fallback_loss_value'] ?? 0);
$tHubIncidentLoss   = (float)($summary['total_hub_incident_loss_value'] ?? 0);
$tTransportOnlyLoss = max(0.0, $tLoss - $tHubLoss - $tFallbackLoss - $tHubIncidentLoss);
$tNet               = (float)($summary['total_net'] ?? 0);
$tCost              = $tOpex + $tSal + $tTr + $tInc + $tTax;

$decisionLabels = [
    'technical_budget'  => t('finance.budget_technical_label'),
    'logistics_budget'  => t('finance.budget_logistics_label'),
    'hr_budget'         => t('finance.budget_hr_label'),
    'safety_budget'     => t('finance.budget_safety_label'),
    'reserve_policy'    => t('finance.decision_label_reserve_policy'),
    'savings_plan_mode' => t('finance.decision_label_savings_plan_mode'),
];

$decisionValueLabels = [
    'low'        => t('finance.level_low'),
    'standard'   => t('finance.level_standard'),
    'high'       => t('finance.level_high'),
    'off'        => t('finance.decision_value_off'),
    'moderate'   => t('finance.decision_value_moderate'),
    'aggressive' => t('finance.decision_value_aggressive'),
];

$policyImpactData = $policyImpact ?? [];
$policyImpactTick = $policyImpactData['tick'] ?? [];
$policyImpactDay = $policyImpactData['day'] ?? [];
$policyEffects = $policyImpactData['effects'] ?? [];
$policyReserve = $policyImpactData['reserve'] ?? [];
$policyNetTick = (float)($policyImpactTick['net'] ?? 0.0);
$policyNetDay = (float)($policyImpactDay['net'] ?? 0.0);

$fmtSignedPct = static function (float $value): string {
    if (abs($value) < 0.05) {
        return '&plusmn;0%';
    }
    $prefix = $value > 0 ? '+' : '&minus;';
    return $prefix . number_format(abs($value), 1, ',', ' ') . '%';
};
?>

<div class="finance-wrap fade-in">

<?php if ($msg): ?>
<div class="finance-alert finance-alert--info" id="fin-msg-banner">
    <span>&#10003;</span>
    <span><?= htmlspecialchars($msg) ?></span>
</div>
<?php endif; ?>

<?php if ($err): ?>
<div class="finance-alert finance-alert--danger" id="fin-err-banner">
    <span>&#9888;</span>
    <span><?= htmlspecialchars($err) ?></span>
</div>
<?php endif; ?>

<nav class="module-tabs finance-tabs" aria-label="<?= htmlspecialchars(t('finance.tabs_label')) ?>">
    <a href="?tab=overview&hours=<?= (int)$hours ?>" class="module-tab <?= $tab === 'overview' ? 'active' : '' ?>">
        <?= t('finance.tab_overview') ?>
    </a>
    <a href="?tab=budgets&hours=<?= (int)$hours ?>" class="module-tab <?= $tab === 'budgets' ? 'active' : '' ?>">
        <?= t('finance.tab_budgets') ?>
    </a>
    <a href="?tab=liquidity&hours=<?= (int)$hours ?>" class="module-tab <?= $tab === 'liquidity' ? 'active' : '' ?>">
        <?= t('finance.tab_liquidity') ?>
    </a>
    <a href="?tab=risk&hours=<?= (int)$hours ?>" class="module-tab <?= $tab === 'risk' ? 'active' : '' ?>">
        <?= t('finance.tab_risk') ?>
    </a>
    <a href="?tab=policy&hours=<?= (int)$hours ?>" class="module-tab <?= $tab === 'policy' ? 'active' : '' ?>">
        <?= t('finance.tab_policy') ?>
        <?php $activeSavings = ($settings['savings_plan_mode'] ?? 'off') !== 'off'; ?>
        <?php if ($activeSavings): ?>
        <span class="module-tab-badge module-tab-badge--gold">!</span>
        <?php endif; ?>
    </a>
    <a href="?tab=history&hours=<?= (int)$hours ?>" class="module-tab <?= $tab === 'history' ? 'active' : '' ?>">
        <?= t('finance.tab_history') ?>
        <?php if (!empty($decisionHistory)): ?>
        <span class="module-tab-badge module-tab-badge--gold"><?= count($decisionHistory) ?></span>
        <?php endif; ?>
    </a>
</nav>

<?php if ($tab === 'overview'): ?>

<?php if (!empty($alerts)): ?>
<div class="finance-alerts">
<?php foreach ($alerts as $alert): ?>
    <div class="finance-alert finance-alert--<?= htmlspecialchars($alert['level']) ?>">
        <span><?= $alert['icon'] ?></span>
        <span><?= htmlspecialchars($alert['msg']) ?></span>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="g-card fin-policy-summary-card">
    <div class="finance-chart-hdr">
        <span class="g-card-title">&#9881; <?= t('finance.policy_impact_summary_title') ?></span>
        <a href="?tab=policy&hours=<?= (int)$hours ?>" class="btn btn-sm btn-secondary"><?= t('finance.policy_impact_summary_cta') ?></a>
    </div>
    <div class="fin-policy-summary-grid">
        <div class="fin-policy-summary-stat">
            <span class="fin-policy-summary-label"><?= t('finance.policy_impact_tick_net') ?></span>
            <strong class="<?= $policyNetTick >= 0 ? 'fin-green' : 'fin-red' ?>"><?= fmtPLN($policyNetTick, true) ?></strong>
        </div>
        <div class="fin-policy-summary-stat">
            <span class="fin-policy-summary-label"><?= t('finance.policy_impact_day_net') ?></span>
            <strong class="<?= $policyNetDay >= 0 ? 'fin-green' : 'fin-red' ?>"><?= fmtPLN($policyNetDay, true) ?></strong>
        </div>
        <div class="fin-policy-summary-stat">
            <span class="fin-policy-summary-label"><?= t('finance.policy_impact_active_mode') ?></span>
            <strong><?= t('finance.savings_mode_' . (($policyImpactData['mode'] ?? 'off'))) ?></strong>
        </div>
        <div class="fin-policy-summary-stat">
            <span class="fin-policy-summary-label"><?= t('finance.policy_impact_active_reserve') ?></span>
            <strong><?= t('finance.reserve_' . (($policyImpactData['reserve_level'] ?? 'standard'))) ?></strong>
        </div>
    </div>
</div>

<?php
$policyRecoStatus = (string)($policyRecommendation['status'] ?? 'good');
$policyRecoPrimary = (array)($policyRecommendation['primary_action'] ?? []);
$policyRecoSecondary = (array)($policyRecommendation['secondary_action'] ?? []);
?>
<div class="g-card fin-policy-reco-card fin-policy-reco-card--<?= htmlspecialchars($policyRecoStatus) ?>">
    <div class="finance-chart-hdr">
        <span class="g-card-title">&#129504; <?= t('finance.policy_reco_panel_title') ?></span>
        <span class="fin-policy-reco-badge fin-policy-reco-badge--<?= htmlspecialchars($policyRecoStatus) ?>">
            <?= htmlspecialchars(t('finance.policy_reco_status_' . $policyRecoStatus)) ?>
        </span>
    </div>
    <div class="fin-policy-reco-body">
        <div class="fin-policy-reco-main">
            <strong class="fin-policy-reco-title"><?= htmlspecialchars((string)($policyRecommendation['title'] ?? '')) ?></strong>
            <p class="fin-policy-reco-summary"><?= htmlspecialchars((string)($policyRecommendation['summary'] ?? '')) ?></p>
        </div>
        <div class="fin-policy-reco-actions">
            <?php if (!empty($policyRecoPrimary['href']) && !empty($policyRecoPrimary['label'])): ?>
            <a href="<?= htmlspecialchars((string)$policyRecoPrimary['href']) ?>" class="btn btn-sm btn-primary">
                <?= htmlspecialchars((string)$policyRecoPrimary['label']) ?>
            </a>
            <?php endif; ?>
        <a href="?tab=policy&hours=<?= (int)$hours ?>#fin-savings-plan" class="btn btn-sm btn-secondary"><?= t('finance.policy_reco_action_details') ?></a>
        </div>
    </div>
</div>

<div class="fin-topbar">
    <div class="g-card fin-stat-card">
        <div class="g-card-title">&#128176; <?= t('finance.card_balance') ?></div>
        <div class="fin-big <?= $cash >= 0 ? 'fin-green' : 'fin-red' ?>"><?= fmtPLN($cash) ?></div>
        <div class="fin-sub"><?= t('finance.sub_balance') ?></div>
    </div>
    <div class="g-card fin-stat-card">
        <div class="g-card-title">&#128200; <?= t('finance.card_profit_tick') ?></div>
        <div class="fin-big <?= $net >= 0 ? 'fin-green' : 'fin-red' ?>"><?= fmtPLN($net, true) ?></div>
        <div class="fin-sub"><?= t('finance.sub_profit_tick') ?></div>
    </div>
    <div class="g-card fin-stat-card">
        <div class="g-card-title">&#9201; <?= t('finance.card_profit_hour') ?></div>
        <div class="fin-big <?= $netH >= 0 ? 'fin-green' : 'fin-red' ?>"><?= fmtPLN($netH, true) ?></div>
        <div class="fin-sub"><?= t('finance.sub_profit_hour') ?></div>
    </div>
    <div class="g-card fin-stat-card">
        <div class="g-card-title">&#9888; <?= t('finance.card_logistics_loss') ?></div>
        <div class="fin-big <?= $lossPct > 15 ? 'fin-red' : ($lossPct > 8 ? 'fin-orange' : 'fin-green') ?>"><?= fmtPct($lossPct) ?></div>
        <div class="fin-sub">&minus;<?= fmtPLN($lossVal) ?> / <?= t('finance.tick') ?></div>
    </div>
</div>

<div class="g-card finance-chart-card">
    <div class="finance-chart-hdr">
        <span class="g-card-title">&#128202; <?= t('finance.chart_title') ?></span>
        <div class="fin-range-btns">
            <a href="?tab=overview&hours=24" class="btn btn-sm <?= $hours === 24 ? 'btn-primary' : 'btn-secondary' ?>">24h</a>
            <a href="?tab=overview&hours=168" class="btn btn-sm <?= $hours === 168 ? 'btn-primary' : 'btn-secondary' ?>">7 <?= t('finance.days') ?></a>
        </div>
    </div>

    <?php if (empty($history)): ?>
    <p class="fin-empty"><?= t('finance.no_data') ?></p>
    <?php else: ?>
    <div class="finance-chart-wrap">
        <canvas id="finChart" height="220"></canvas>
    </div>
    <script>
    window._finHistory = <?= json_encode(array_values($history), JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <?php endif; ?>
</div>

<div class="g-card" id="fin-savings-plan">
    <div class="g-card-title">&#128201; <?= t('finance.breakdown_title') ?> (<?= $hours === 24 ? '24h' : '7 ' . t('finance.days') ?>)</div>
    <div class="fin-breakdown">
        <div class="fin-breakdown-col">
            <div class="fin-breakdown-title fin-green"><?= t('finance.revenues') ?></div>
            <div class="fin-breakdown-row">
                <span><?= t('finance.oil_sales') ?></span>
                <span class="fin-green"><?= fmtPLN($tRev) ?></span>
            </div>
            <?php if ($tLoss > 0): ?>
            <div class="fin-breakdown-row fin-muted">
                <span><?= t('finance.gross_before_loss') ?></span>
                <span><?= fmtPLN($tGross) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="fin-breakdown-col">
            <div class="fin-breakdown-title fin-red"><?= t('finance.costs') ?></div>
            <?php foreach ([
                [t('finance.cost_opex'), $tWellOpex, $tCost],
                [t('finance.cost_hub_usage'), $tHubUsage, $tCost],
                [t('finance.cost_salary'), $tSal, $tCost],
                [t('finance.cost_transport'), $tTr, $tCost],
                [t('finance.cost_incidents'), $tInc, $tCost],
                [t('finance.cost_tax'), $tTax, $tCost],
            ] as [$label, $value, $base]):
                if ($value <= 0) {
                    continue;
                }
            ?>
            <div class="fin-breakdown-row">
                <span><?= $label ?></span>
                <span>
                    <span class="fin-red"><?= fmtPLN($value) ?></span>
                    <span class="fin-muted fin-small"><?= pct($value, $base) ?></span>
                </span>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="fin-breakdown-col">
            <div class="fin-breakdown-title fin-orange"><?= t('finance.losses') ?></div>
            <div class="fin-breakdown-row">
                <span><?= t('finance.transport_loss') ?></span>
                <span>
                    <span class="fin-orange"><?= fmtPLN($tTransportOnlyLoss) ?></span>
                    <span class="fin-muted fin-small"><?= pct($tTransportOnlyLoss, $tRev) ?> <?= t('finance.of_revenue') ?></span>
                </span>
            </div>
            <?php if ($tHubLoss > 0): ?>
            <div class="fin-breakdown-row">
                <span><?= t('finance.hub_loss') ?></span>
                <span>
                    <span class="fin-orange"><?= fmtPLN($tHubLoss) ?></span>
                    <span class="fin-muted fin-small"><?= pct($tHubLoss, $tRev) ?> <?= t('finance.of_revenue') ?></span>
                </span>
            </div>
            <?php endif; ?>
            <?php if ($tFallbackLoss > 0): ?>
            <div class="fin-breakdown-row">
                <span><?= t('finance.fallback_loss') ?></span>
                <span>
                    <span class="fin-orange"><?= fmtPLN($tFallbackLoss) ?></span>
                    <span class="fin-muted fin-small"><?= pct($tFallbackLoss, $tRev) ?> <?= t('finance.of_revenue') ?></span>
                </span>
            </div>
            <?php endif; ?>
            <?php if ($tHubIncidentLoss > 0): ?>
            <div class="fin-breakdown-row">
                <span><?= t('finance.hub_incident_loss') ?></span>
                <span>
                    <span class="fin-orange"><?= fmtPLN($tHubIncidentLoss) ?></span>
                    <span class="fin-muted fin-small"><?= pct($tHubIncidentLoss, $tRev) ?> <?= t('finance.of_revenue') ?></span>
                </span>
            </div>
            <?php endif; ?>
            <div class="fin-breakdown-row">
                <span><?= t('finance.lost_barrels') ?></span>
                <span class="fin-muted"><?= number_format((float)($summary['total_loss_bbl'] ?? 0), 1, ',', ' ') ?> bbl</span>
            </div>
            <hr class="fin-hr">
            <div class="fin-breakdown-row fin-bold">
                <span><?= t('finance.net_result') ?></span>
                <span class="<?= $tNet >= 0 ? 'fin-green' : 'fin-red' ?>"><?= fmtPLN($tNet, true) ?></span>
            </div>
        </div>
    </div>
</div>

<div class="fin-section-label">&#128230; <?= t('finance.hub_section_title') ?></div>
<div class="fin-topbar">
    <div class="g-card fin-stat-card">
        <div class="g-card-title"><?= t('finance.hub_card_usage') ?></div>
        <div class="fin-big <?= $tHubUsage > 0 ? 'fin-red' : 'fin-green' ?>"><?= fmtPLN($tHubUsage) ?></div>
        <div class="fin-sub"><?= t('finance.hub_card_usage_sub') ?></div>
    </div>
    <div class="g-card fin-stat-card">
        <div class="g-card-title"><?= t('finance.hub_card_loss') ?></div>
        <div class="fin-big <?= $tHubLoss > 0 ? 'fin-orange' : 'fin-green' ?>"><?= fmtPLN($tHubLoss) ?></div>
        <div class="fin-sub"><?= number_format((float)($summary['total_hub_loss_bbl'] ?? 0), 1, ',', ' ') ?> bbl</div>
    </div>
    <div class="g-card fin-stat-card">
        <div class="g-card-title"><?= t('finance.hub_card_fallback') ?></div>
        <div class="fin-big <?= $tFallbackLoss > 0 ? 'fin-orange' : 'fin-green' ?>"><?= fmtPLN($tFallbackLoss) ?></div>
        <div class="fin-sub"><?= number_format((float)($summary['total_fallback_loss_bbl'] ?? 0), 1, ',', ' ') ?> bbl</div>
    </div>
    <div class="g-card fin-stat-card">
        <div class="g-card-title"><?= t('finance.hub_card_incidents') ?></div>
        <div class="fin-big <?= $tHubIncidentLoss > 0 ? 'fin-red' : 'fin-green' ?>"><?= fmtPLN($tHubIncidentLoss) ?></div>
        <div class="fin-sub"><?= number_format((float)($summary['total_hub_incident_loss_bbl'] ?? 0), 1, ',', ' ') ?> bbl</div>
    </div>
</div>

<?php if (!empty($perWell)): ?>
<div class="g-card">
    <div class="g-card-title">&#128738; <?= t('finance.per_well_title') ?></div>
    <div class="fin-well-list">
        <div class="fin-well-header">
            <span><?= t('finance.col_well') ?></span>
            <span><?= t('finance.col_type') ?></span>
            <span><?= t('finance.col_status') ?></span>
            <span><?= t('finance.col_prod_h') ?></span>
            <span><?= t('finance.col_opex_h') ?></span>
            <span><?= t('finance.col_transport') ?></span>
            <span><?= t('finance.col_tax') ?></span>
            <span><?= t('finance.col_est_profit_h') ?></span>
        </div>
        <?php
        $maxAbsNetPerH = 0.0;
        foreach ($perWell as $w2) {
            $effP2      = (float)($w2['_effectiveProd'] ?? $w2['base_production_per_hour']);
            $deliv2     = $effP2 * ((float)($w2['_trCapPct'] ?? 0) / 100.0);
            $rev2       = $deliv2 * $oilPrice;
            $opex2      = (float)$w2['upkeep_cost_per_hour'] * (float)($w2['_regionOpexMult'] ?? 1.0);
            $tr2        = $rev2 * ((float)$w2['transport_opex_pct'] / 100) + $deliv2 * (float)($w2['_costPerBbl'] ?? 0);
            $tax2       = $rev2 * (float)$w2['regional_tax_rate'];
            $n2         = $rev2 - $opex2 - $tr2 - $tax2;
            if (abs($n2) > $maxAbsNetPerH) { $maxAbsNetPerH = abs($n2); }
        }
        ?>
        <?php foreach ($perWell as $well):
            $prod       = (float)($well['_effectiveProd'] ?? $well['base_production_per_hour']);
            $trCapPct   = (float)($well['_trCapPct'] ?? 0.0);
            $deliveredH = $prod * ($trCapPct / 100.0);
            $netRevH    = $deliveredH * $oilPrice;
            $opexH      = (float)$well['upkeep_cost_per_hour'] * (float)($well['_regionOpexMult'] ?? 1.0);
            $trPct      = (float)$well['transport_opex_pct'];
            $costPerBbl = (float)($well['_costPerBbl'] ?? 0.0);
            $trCostH    = $netRevH * ($trPct / 100) + $deliveredH * $costPerBbl;
            $taxRate    = (float)$well['regional_tax_rate'];
            $taxH       = $netRevH * $taxRate;
            $netPerH    = $netRevH - $opexH - $trCostH - $taxH;
            $isActive   = in_array($well['status'], ['active', 'no_technician', 'contaminated'], true);
        ?>
        <div class="fin-well-row<?= $netPerH < 0 ? ' fin-well-row--loss' : '' ?><?= $isActive ? '' : ' fin-well-row--inactive' ?>">
            <span>
                <a href="/wells#well-<?= (int)$well['id'] ?>" class="fin-well-link">
                    <?= htmlspecialchars($well['well_name'] ?: $well['name'] ?: '#' . $well['id']) ?>
                </a>
            </span>
            <span><span class="fin-type-badge fin-type-<?= htmlspecialchars((string)($well['well_type'] ?? 'onshore')) ?>"><?= t('technical.well_type_' . ($well['well_type'] ?? 'onshore')) ?></span></span>
            <span><span class="fin-status-badge"><?= t('technical.ws_' . ($well['status'] ?? 'active')) ?></span></span>
            <span class="fin-num"><?= number_format($prod, 1, ',', ' ') ?> <?= t('common.bbl') ?><span class="fin-muted fin-small"> &times;<?= number_format($trCapPct, 0) ?>%</span></span>
            <span class="fin-num fin-red"><?= fmtPLN($opexH) ?></span>
            <span class="fin-num fin-muted"><?= $trCostH > 0 ? fmtPLN($trCostH) : '&mdash;' ?></span>
            <span class="fin-num fin-muted"><?= $taxRate > 0 ? fmtPct($taxRate * 100) : '&mdash;' ?></span>
            <?php $barPct = $maxAbsNetPerH > 0 ? min(100, (int)round(abs($netPerH) / $maxAbsNetPerH * 100)) : 0; ?>
            <span class="fin-num fin-bold <?= $netPerH >= 0 ? 'fin-green' : 'fin-red' ?>">
                <?= fmtPLN($netPerH, true) ?>
                <span class="fin-profit-bar" aria-hidden="true"><span class="fin-profit-bar-fill fin-profit-bar-fill--<?= $netPerH >= 0 ? 'pos' : 'neg' ?>" style="width:<?= $barPct ?>%"></span></span>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
    <p class="fin-note">* <?= t('finance.per_well_note', ['price' => $oilPrice]) ?></p>
</div>
<?php endif; ?>

<?php elseif ($tab === 'budgets'): ?>

<div class="g-card">
    <div class="g-card-title">&#128181; <?= t('finance.budgets_title') ?></div>
    <p class="fin-panel-intro"><?= t('finance.budgets_intro') ?></p>
    <form method="post" class="finance-settings-form"
          data-confirm="<?= htmlspecialchars(t('finance.confirm_budget')) ?>"
          data-confirm-type="info"
          data-confirm-label="<?= htmlspecialchars(t('finance.btn_save_settings')) ?>">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="save_finance_settings">
        <div class="fin-settings-grid">
            <?php foreach ([
                'technical_budget' => ['label' => t('finance.budget_technical_label'), 'desc' => t('finance.budget_technical_desc')],
                'logistics_budget' => ['label' => t('finance.budget_logistics_label'), 'desc' => t('finance.budget_logistics_desc')],
                'hr_budget' => ['label' => t('finance.budget_hr_label'), 'desc' => t('finance.budget_hr_desc')],
                'safety_budget' => ['label' => t('finance.budget_safety_label'), 'desc' => t('finance.budget_safety_desc')],
            ] as $field => $meta): ?>
            <?php $selVal = $settings[$field] ?? 'standard'; ?>
            <div class="g-card fin-setting-card">
                <div class="fin-setting-label"><?= $meta['label'] ?></div>
                <div class="fin-setting-desc"><?= $meta['desc'] ?></div>
                <select name="<?= htmlspecialchars($field) ?>" class="fin-select fin-budget-select" data-badge="badge-<?= htmlspecialchars($field) ?>">
                    <?php foreach ($budgetLevelOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= $selVal === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="badge-<?= htmlspecialchars($field) ?>" class="fin-level-badge fin-level-badge--<?= htmlspecialchars($selVal) ?>">
                    <?= htmlspecialchars($budgetLevelOptions[$selVal] ?? $selVal) ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php $resSelVal = $settings['reserve_policy'] ?? 'standard'; ?>
            <div class="g-card fin-setting-card">
                <div class="fin-setting-label"><?= t('finance.reserve_policy_label') ?></div>
                <div class="fin-setting-desc"><?= t('finance.reserve_policy_desc') ?></div>
                <select name="reserve_policy" class="fin-select fin-budget-select" data-badge="badge-reserve_policy">
                    <?php foreach ($reserveOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= $resSelVal === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="badge-reserve_policy" class="fin-level-badge fin-level-badge--<?= htmlspecialchars($resSelVal) ?>">
                    <?= htmlspecialchars($reserveOptions[$resSelVal] ?? $resSelVal) ?>
                </div>
            </div>
        </div>
        <div class="fin-form-actions">
            <button type="submit" class="btn btn-primary"><?= t('finance.btn_save_settings') ?></button>
            <span class="fin-form-hint"><?= t('finance.save_hint') ?></span>
        </div>
    </form>
</div>

<div class="g-card">
    <div class="g-card-title">&#9881; <?= t('finance.budget_effects_title') ?></div>
    <div class="fin-budget-effects">
        <div class="fin-breakdown-row"><span><?= t('finance.budget_technical_label') ?></span><span class="fin-muted"><?= t('finance.budget_technical_effect') ?></span></div>
        <div class="fin-breakdown-row"><span><?= t('finance.budget_logistics_label') ?></span><span class="fin-muted"><?= t('finance.budget_logistics_effect') ?></span></div>
        <div class="fin-breakdown-row"><span><?= t('finance.budget_hr_label') ?></span><span class="fin-muted"><?= t('finance.budget_hr_effect') ?></span></div>
        <div class="fin-breakdown-row"><span><?= t('finance.budget_safety_label') ?></span><span class="fin-muted"><?= t('finance.budget_safety_effect') ?></span></div>
        <div class="fin-breakdown-row"><span><?= t('finance.reserve_policy_label') ?></span><span class="fin-muted"><?= t('finance.reserve_policy_effect') ?></span></div>
    </div>
</div>

<?php elseif ($tab === 'liquidity'): ?>

<div class="fin-topbar">
    <div class="g-card fin-stat-card">
        <div class="g-card-title"><?= t('finance.liquidity_next_tick') ?></div>
        <div class="fin-big <?= (float)$liquidity['next_tick'] >= 0 ? 'fin-green' : 'fin-red' ?>"><?= fmtPLN((float)$liquidity['next_tick'], true) ?></div>
        <div class="fin-sub"><?= t('finance.sub_profit_tick') ?></div>
    </div>
    <div class="g-card fin-stat-card">
        <div class="g-card-title"><?= t('finance.liquidity_next_hour') ?></div>
        <div class="fin-big <?= (float)$liquidity['next_hour'] >= 0 ? 'fin-green' : 'fin-red' ?>"><?= fmtPLN((float)$liquidity['next_hour'], true) ?></div>
        <div class="fin-sub"><?= t('finance.liquidity_projection') ?></div>
    </div>
    <div class="g-card fin-stat-card">
        <div class="g-card-title"><?= t('finance.liquidity_next_day') ?></div>
        <div class="fin-big <?= (float)$liquidity['next_day'] >= 0 ? 'fin-green' : 'fin-red' ?>"><?= fmtPLN((float)$liquidity['next_day'], true) ?></div>
        <div class="fin-sub"><?= t('finance.liquidity_projection_24h') ?></div>
    </div>
    <div class="g-card fin-stat-card">
        <div class="g-card-title"><?= t('finance.liquidity_coverage') ?></div>
        <div class="fin-big <?= ((float)$liquidity['coverage_hours'] < 12.0) ? 'fin-red' : (((float)$liquidity['coverage_hours'] < 24.0) ? 'fin-orange' : 'fin-green') ?>"><?= number_format((float)$liquidity['coverage_hours'], 1, ',', ' ') ?>h</div>
        <div class="fin-sub"><?= t('finance.liquidity_coverage_sub') ?></div>
    </div>
</div>

<div class="g-card">
    <div class="g-card-title">&#128184; <?= t('finance.liquidity_title') ?></div>
    <div class="fin-liquidity-grid">
        <div class="fin-liquidity-block">
            <div class="fin-liquidity-level fin-liquidity-level--<?= htmlspecialchars((string)$liquidity['level']) ?>">
                <?= t('finance.liquidity_level_' . $liquidity['level']) ?>
            </div>
            <p class="fin-panel-intro"><?= t('finance.liquidity_summary_' . $liquidity['level']) ?></p>
        </div>
        <div class="fin-liquidity-metrics">
            <div class="fin-breakdown-row"><span><?= t('finance.liquidity_hourly_cost') ?></span><strong><?= fmtPLN((float)$liquidity['hourly_cost']) ?></strong></div>
            <div class="fin-breakdown-row"><span><?= t('finance.liquidity_reserve_target') ?></span><strong><?= fmtPLN((float)$liquidity['reserve_target_value']) ?></strong></div>
            <div class="fin-breakdown-row"><span><?= t('finance.liquidity_gap') ?></span><strong class="<?= (float)$liquidity['reserve_gap'] > 0 ? 'fin-red' : 'fin-green' ?>"><?= fmtPLN((float)$liquidity['reserve_gap']) ?></strong></div>
            <div class="fin-breakdown-row"><span><?= t('finance.liquidity_next_six') ?></span><strong class="<?= (float)$liquidity['next_six_hours'] >= 0 ? 'fin-green' : 'fin-red' ?>"><?= fmtPLN((float)$liquidity['next_six_hours'], true) ?></strong></div>
        </div>
    </div>
</div>

<?php elseif ($tab === 'risk'): ?>

<div class="g-card">
    <div class="g-card-title">&#9888; <?= t('finance.risk_title') ?></div>
    <div class="fin-risk-grid">
        <?php foreach ($riskOverview as $riskItem): ?>
        <div class="g-card fin-risk-card fin-risk-card--<?= htmlspecialchars((string)$riskItem['level']) ?>">
            <div class="fin-risk-head">
                <span class="fin-risk-label"><?= htmlspecialchars((string)$riskItem['label']) ?></span>
                <span class="fin-risk-level fin-risk-level--<?= htmlspecialchars((string)$riskItem['level']) ?>"><?= htmlspecialchars((string)$riskItem['level_label']) ?></span>
            </div>
            <p class="fin-risk-desc"><?= htmlspecialchars((string)$riskItem['desc']) ?></p>
            <div class="fin-risk-hint"><?= htmlspecialchars((string)$riskItem['hint']) ?></div>
            <?php if (!empty($riskItem['action_tab']) && !empty($riskItem['action_label'])): ?>
            <div class="fin-risk-action">
                <a href="?tab=<?= htmlspecialchars((string)$riskItem['action_tab']) ?>&hours=<?= (int)$hours ?>" class="btn btn-sm btn-secondary">
                    <?= htmlspecialchars((string)$riskItem['action_label']) ?> &rarr;
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php elseif ($tab === 'policy'): ?>

<?php
$savings       = $policySnapshot['savings'] ?? [];
$resState      = $policySnapshot['reserve_state'] ?? 'good';
$resTarget     = (float)($policySnapshot['reserve_target_value'] ?? 0.0);
$resHours      = (float)($policySnapshot['reserve_target_hours'] ?? 12.0);
$covHours      = (float)($policySnapshot['coverage_hours'] ?? 0.0);
$curMode       = (string)($savings['mode'] ?? 'off');
$canChange     = (bool)($savings['can_change'] ?? true);
$cooldownUntil = (string)($savings['cooldown_until'] ?? '');
$cooldownLeft  = (int)($savings['cooldown_remaining_seconds'] ?? 0);

$savingsModeDetails = [
    'off'        => ['desc' => t('finance.savings_off_desc'),        'saves' => '', 'costs' => ''],
    'moderate'   => ['desc' => t('finance.savings_moderate_desc'),   'saves' => t('finance.savings_moderate_saves'), 'costs' => t('finance.savings_moderate_costs')],
    'aggressive' => ['desc' => t('finance.savings_aggressive_desc'), 'saves' => t('finance.savings_aggressive_saves'), 'costs' => t('finance.savings_aggressive_costs')],
];
$reserveHoursLabel = [
    'low'      => t('finance.reserve_low_hours'),
    'standard' => t('finance.reserve_standard_hours'),
    'high'     => t('finance.reserve_high_hours'),
];
$reserveShortLabel = [
    'low'      => '6h',
    'standard' => '12h',
    'high'     => '24h',
];

$resStateIcon = [
    'good'     => '&#10003;',
    'caution'  => '&#9888;',
    'warning'  => '&#9888;',
    'critical' => '&#9888;',
];
?>

<?php
$policyRecoStatus = (string)($policyRecommendation['status'] ?? 'good');
$policyRecoPrimary = (array)($policyRecommendation['primary_action'] ?? []);
$policyRecoSecondary = (array)($policyRecommendation['secondary_action'] ?? []);
$policyRecoHighlights = (array)($policyRecommendation['highlights'] ?? []);
?>

<div class="g-card fin-policy-reco-card fin-policy-reco-card--<?= htmlspecialchars($policyRecoStatus) ?>">
    <div class="finance-chart-hdr">
        <span class="g-card-title">&#129504; <?= t('finance.policy_reco_panel_title') ?></span>
        <span class="fin-policy-reco-badge fin-policy-reco-badge--<?= htmlspecialchars($policyRecoStatus) ?>">
            <?= htmlspecialchars(t('finance.policy_reco_status_' . $policyRecoStatus)) ?>
        </span>
    </div>
    <div class="fin-policy-reco-headline"><?= htmlspecialchars((string)($policyRecommendation['title'] ?? '')) ?></div>
    <p class="fin-policy-reco-summary fin-policy-reco-summary--panel"><?= htmlspecialchars((string)($policyRecommendation['summary'] ?? '')) ?></p>

    <?php if (!empty($policyRecoHighlights)): ?>
    <div class="fin-policy-reco-highlights">
        <?php foreach ($policyRecoHighlights as $highlight): ?>
        <div class="fin-policy-reco-highlight">
            <span class="fin-policy-reco-dot"></span>
            <span><?= htmlspecialchars((string)$highlight) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="fin-policy-reco-actions fin-policy-reco-actions--panel">
        <?php if (!empty($policyRecoPrimary['href']) && !empty($policyRecoPrimary['label'])): ?>
        <a href="<?= htmlspecialchars((string)$policyRecoPrimary['href']) ?>" class="btn btn-sm btn-primary">
            <?= htmlspecialchars((string)$policyRecoPrimary['label']) ?>
        </a>
        <?php endif; ?>
        <?php if (!empty($policyRecoSecondary['href']) && !empty($policyRecoSecondary['label'])): ?>
        <a href="<?= htmlspecialchars((string)$policyRecoSecondary['href']) ?>" class="btn btn-sm btn-secondary">
            <?= htmlspecialchars((string)$policyRecoSecondary['label']) ?>
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="g-card fin-policy-impact-card">
    <div class="finance-chart-hdr">
        <span class="g-card-title">&#128200; <?= t('finance.policy_impact_title') ?></span>
        <span class="fin-policy-mode-badge fin-mode-<?= htmlspecialchars($curMode) ?>"><?= htmlspecialchars(t('finance.savings_mode_' . $curMode)) ?></span>
    </div>
    <p class="fin-panel-intro"><?= t('finance.policy_impact_intro') ?></p>

    <div class="fin-policy-impact-grid">
        <?php foreach ($policyEffects as $effect): ?>
        <?php
            $effectState = (string)($effect['state'] ?? 'neutral');
            $effectDelta = (float)($effect['delta_pct'] ?? 0.0);
        ?>
        <div class="fin-policy-impact-item fin-policy-impact-item--<?= htmlspecialchars($effectState) ?>">
            <span class="fin-policy-impact-label"><?= htmlspecialchars((string)($effect['label'] ?? '')) ?></span>
            <strong class="fin-policy-impact-value"><?= $fmtSignedPct($effectDelta) ?></strong>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="fin-policy-results-grid">
        <div class="fin-policy-result-card">
            <div class="fin-policy-result-title"><?= t('finance.policy_impact_tick_title') ?></div>
            <div class="fin-policy-result-list">
                <div class="fin-breakdown-row">
                    <span><?= t('finance.policy_impact_transport_saved') ?></span>
                    <strong class="fin-green"><?= fmtPLN((float)($policyImpactTick['transport_saved'] ?? 0.0)) ?></strong>
                </div>
                <div class="fin-breakdown-row">
                    <span><?= t('finance.policy_impact_hub_saved') ?></span>
                    <strong class="fin-green"><?= fmtPLN((float)($policyImpactTick['hub_saved'] ?? 0.0)) ?></strong>
                </div>
                <div class="fin-breakdown-row">
                    <span><?= t('finance.policy_impact_extra_loss') ?></span>
                    <strong class="fin-red"><?= fmtPLN((float)($policyImpactTick['extra_loss'] ?? 0.0)) ?></strong>
                </div>
                <div class="fin-breakdown-row fin-bold">
                    <span><?= t('finance.policy_impact_tick_net') ?></span>
                    <strong class="<?= $policyNetTick >= 0 ? 'fin-green' : 'fin-red' ?>"><?= fmtPLN($policyNetTick, true) ?></strong>
                </div>
            </div>
            <?php if (empty($policyImpactTick['has_direct_effect'])): ?>
            <p class="fin-policy-impact-note"><?= t('finance.policy_impact_no_direct_tick') ?></p>
            <?php endif; ?>
        </div>

        <div class="fin-policy-result-card">
            <div class="fin-policy-result-title"><?= t('finance.policy_impact_day_title') ?></div>
            <div class="fin-policy-result-list">
                <div class="fin-breakdown-row">
                    <span><?= t('finance.policy_impact_transport_saved') ?></span>
                    <strong class="fin-green"><?= fmtPLN((float)($policyImpactDay['transport_saved'] ?? 0.0)) ?></strong>
                </div>
                <div class="fin-breakdown-row">
                    <span><?= t('finance.policy_impact_hub_saved') ?></span>
                    <strong class="fin-green"><?= fmtPLN((float)($policyImpactDay['hub_saved'] ?? 0.0)) ?></strong>
                </div>
                <div class="fin-breakdown-row">
                    <span><?= t('finance.policy_impact_extra_loss') ?></span>
                    <strong class="fin-red"><?= fmtPLN((float)($policyImpactDay['extra_loss'] ?? 0.0)) ?></strong>
                </div>
                <div class="fin-breakdown-row fin-bold">
                    <span><?= t('finance.policy_impact_day_net') ?></span>
                    <strong class="<?= $policyNetDay >= 0 ? 'fin-green' : 'fin-red' ?>"><?= fmtPLN($policyNetDay, true) ?></strong>
                </div>
            </div>
            <?php if (empty($policyImpactDay['has_direct_effect'])): ?>
            <p class="fin-policy-impact-note"><?= t('finance.policy_impact_no_direct_day') ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="fin-policy-impact-footer">
        <div class="fin-policy-impact-footnote">
            <span><?= t('finance.policy_impact_active_reserve') ?>:</span>
            <strong><?= t('finance.reserve_' . (($policyImpactData['reserve_level'] ?? 'standard'))) ?></strong>
        </div>
        <div class="fin-policy-impact-footnote">
            <span><?= t('finance.policy_impact_reserve_state') ?>:</span>
            <strong class="fin-<?= ($policyReserve['state'] ?? 'good') === 'critical' ? 'red' : (($policyReserve['state'] ?? 'good') === 'warning' ? 'orange' : 'green') ?>">
                <?= t('finance.reserve_state_' . (($policyReserve['state'] ?? 'good'))) ?>
            </strong>
        </div>
    </div>
</div>

<form method="post" id="finance-policy-form" class="finance-policy-form">
<?= CSRF::field() ?>
<input type="hidden" name="action" value="save_finance_policy">
<div class="fin-policy-grid">

<div class="g-card">
    <div class="g-card-title">&#9881; <?= t('finance.savings_plan_label') ?></div>
    <p class="fin-panel-intro">
        <?= t('finance.savings_plan_desc') ?>
        <span class="fin-policy-mode-badge fin-mode-<?= htmlspecialchars($curMode) ?>"><?= htmlspecialchars(t('finance.savings_mode_' . $curMode)) ?></span>
    </p>

    <?php if (!$canChange && $cooldownUntil !== ''): ?>
    <div class="fin-policy-lock-row">
        <span>&#128274;</span>
        <span id="fin-cooldown-display" data-seconds="<?= (int)$cooldownLeft ?>">
            <?php
            $h = (int)floor($cooldownLeft / 3600);
            $m = (int)floor(($cooldownLeft % 3600) / 60);
            echo htmlspecialchars(t('finance.savings_plan_cooldown_left', ['h' => $h, 'm' => str_pad((string)$m, 2, '0', STR_PAD_LEFT)]));
            ?>
        </span>
    </div>
    <?php else: ?>
    <div class="fin-policy-lock-row fin-green">
        <span>&#10003;</span>
        <span><?= t('finance.savings_plan_can_change') ?></span>
    </div>
    <?php endif; ?>

    <?php if ($curMode !== 'off' && !empty($savingsModeDetails[$curMode])): ?>
    <div class="fin-savings-effects">
        <?php if (!empty($savingsModeDetails[$curMode]['saves'])): ?>
        <div class="fin-savings-col fin-savings-col--saves">
            <div class="fin-savings-col-title">&#10003; <?= t('finance.savings_effect_saves') ?></div>
            <?php foreach (explode("\n", $savingsModeDetails[$curMode]['saves']) as $line): if (trim($line) === '') { continue; } ?>
            <div class="fin-savings-row"><?= htmlspecialchars(trim($line)) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($savingsModeDetails[$curMode]['costs'])): ?>
        <div class="fin-savings-col fin-savings-col--costs">
            <div class="fin-savings-col-title">&#9888; <?= t('finance.savings_effect_costs') ?></div>
            <?php foreach (explode("\n", $savingsModeDetails[$curMode]['costs']) as $line): if (trim($line) === '') { continue; } ?>
            <div class="fin-savings-row"><?= htmlspecialchars(trim($line)) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="fin-policy-section-label"><?= t('finance.savings_plan_current') ?></div>
        <div class="fin-mode-picker fin-mode-picker--triple">
            <?php foreach ($savingsModeOptions as $modeVal => $modeLabel): ?>
            <label class="fin-mode-option<?= $curMode === $modeVal ? ' fin-mode-option--active' : '' ?>">
                <input type="radio" name="savings_plan_mode" value="<?= htmlspecialchars($modeVal) ?>"<?= $curMode === $modeVal ? ' checked' : '' ?><?= !$canChange && $curMode !== $modeVal ? ' disabled' : '' ?>>
                <div class="fin-mode-option-top">
                    <span class="fin-mode-option-label"><?= htmlspecialchars($modeLabel) ?></span>
                    <?php if ($curMode === $modeVal): ?>
                    <span class="fin-mode-option-check">&#10003;</span>
                    <?php endif; ?>
                </div>
                <span class="fin-mode-option-desc"><?= htmlspecialchars($savingsModeDetails[$modeVal]['desc'] ?? '') ?></span>
            </label>
            <?php endforeach; ?>
        </div>
        <div class="fin-form-actions">
            <button type="submit" class="btn btn-primary fin-policy-submit"<?= !$canChange ? ' disabled title="' . htmlspecialchars(t('finance.err_savings_cooldown')) . '"' : '' ?>>
                <?= t('finance.btn_save_policy') ?>
            </button>
        </div>
</div>

<div class="g-card">
    <div class="g-card-title">&#128184; <?= t('finance.reserve_emergency_label') ?></div>

    <div class="fin-policy-hero">
        <div>
            <p class="fin-panel-intro"><?= t('finance.reserve_emergency_desc') ?></p>
            <p class="fin-policy-copy"><?= t('finance.reserve_target_hours') ?></p>
        </div>
        <strong class="fin-policy-metric"><?= htmlspecialchars($reserveShortLabel[$settings['reserve_policy'] ?? 'standard'] ?? '') ?></strong>
    </div>

    <div class="fin-reserve-status fin-reserve-status--<?= htmlspecialchars($resState) ?>">
        <div class="fin-reserve-state-label">
            <span><?= $resStateIcon[$resState] ?? '&#9888;' ?></span>
            <?= t('finance.reserve_state_' . $resState) ?>
        </div>
        <div class="fin-breakdown-row">
            <span><?= t('finance.reserve_target_hours') ?></span>
            <strong><?= htmlspecialchars($reserveHoursLabel[$settings['reserve_policy'] ?? 'standard'] ?? '') ?></strong>
        </div>
        <div class="fin-breakdown-row">
            <span><?= t('finance.reserve_target_value') ?></span>
            <strong><?= fmtPLN($resTarget) ?></strong>
        </div>
        <div class="fin-breakdown-row">
            <span><?= t('finance.reserve_coverage_now') ?></span>
            <strong class="<?= $covHours < $resHours ? 'fin-red' : 'fin-green' ?>"><?= number_format($covHours, 1, ',', ' ') ?> h</strong>
        </div>
    </div>

    <div class="fin-policy-section-label"><?= t('finance.reserve_choose_level') ?></div>
        <div class="fin-mode-picker fin-mode-picker--triple">
            <?php foreach ($reserveOptions as $resVal => $resLabel): ?>
            <label class="fin-mode-option<?= ($settings['reserve_policy'] ?? 'standard') === $resVal ? ' fin-mode-option--active' : '' ?>">
                <input type="radio" name="reserve_policy" value="<?= htmlspecialchars($resVal) ?>"<?= ($settings['reserve_policy'] ?? 'standard') === $resVal ? ' checked' : '' ?>>
                <div class="fin-mode-option-top">
                    <span class="fin-mode-option-label"><?= htmlspecialchars($resLabel) ?></span>
                    <?php if (($settings['reserve_policy'] ?? 'standard') === $resVal): ?>
                    <span class="fin-mode-option-check">&#10003;</span>
                    <?php endif; ?>
                </div>
                <span class="fin-mode-option-value"><?= htmlspecialchars($reserveShortLabel[$resVal] ?? '') ?></span>
                <span class="fin-mode-option-desc"><?= htmlspecialchars($reserveHoursLabel[$resVal] ?? '') ?></span>
            </label>
            <?php endforeach; ?>
        </div>
        <div class="fin-form-actions">
            <button type="submit" class="btn btn-primary fin-policy-submit"><?= t('finance.btn_save_policy') ?></button>
        </div>
</div>

</div>
</form>

<?php elseif ($tab === 'history'): ?>

<div class="g-card">
    <div class="g-card-title">&#128221; <?= t('finance.history_title') ?></div>
    <?php if (empty($decisionHistory)): ?>
    <p class="fin-empty"><?= t('finance.history_empty') ?></p>
    <?php else: ?>
    <?php
    $decisionIcons = [
        'technical_budget'  => '&#128295;',
        'logistics_budget'  => '&#128666;',
        'hr_budget'         => '&#128119;',
        'safety_budget'     => '&#128737;',
        'reserve_policy'    => '&#128184;',
        'savings_plan_mode' => '&#128200;',
    ];
    $nowTs = time();
    ?>
    <div class="fin-history-list">
        <?php foreach ($decisionHistory as $decision):
            $key      = (string)($decision['decision_key'] ?? '');
            $oldValue = (string)($decision['old_value'] ?? '');
            $newValue = (string)($decision['new_value'] ?? '');
            $decisionTs = strtotime((string)($decision['created_at'] ?? ''));
            $diffSecs   = $decisionTs > 0 ? max(0, $nowTs - $decisionTs) : 0;
            if ($locale === 'en') {
                if ($diffSecs < 60)       { $relTime = 'just now'; }
                elseif ($diffSecs < 3600) { $relTime = floor($diffSecs / 60) . ' min ago'; }
                elseif ($diffSecs < 86400){ $relTime = floor($diffSecs / 3600) . ' h ago'; }
                else                      { $relTime = floor($diffSecs / 86400) . ' d ago'; }
            } else {
                if ($diffSecs < 60)       { $relTime = 'przed chwila'; }
                elseif ($diffSecs < 3600) { $relTime = 'przed ' . floor($diffSecs / 60) . ' min'; }
                elseif ($diffSecs < 86400){ $relTime = 'przed ' . floor($diffSecs / 3600) . ' h'; }
                else                      { $relTime = 'przed ' . floor($diffSecs / 86400) . ' d'; }
            }
            $decisionIcon = $decisionIcons[$key] ?? '&#128203;';
        ?>
        <div class="fin-history-row">
            <div class="fin-history-main">
                <div class="fin-history-label">
                    <span class="fin-history-icon" aria-hidden="true"><?= $decisionIcon ?></span>
                    <?= htmlspecialchars($decisionLabels[$key] ?? $key) ?>
                </div>
                <div class="fin-history-change">
                    <span><?= htmlspecialchars($decisionValueLabels[$oldValue] ?? $oldValue) ?></span>
                    <span class="fin-muted">&rarr;</span>
                    <strong><?= htmlspecialchars($decisionValueLabels[$newValue] ?? $newValue) ?></strong>
                </div>
            </div>
            <div class="fin-history-date">
                <span class="fin-history-reltime"><?= htmlspecialchars($relTime) ?></span>
                <span class="fin-history-abstime fin-muted" title="<?= htmlspecialchars((string)($decision['created_at'] ?? '')) ?>"><?= htmlspecialchars((string)($decision['created_at'] ?? '')) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

</div>

<script>
window.FIN_LANG = <?= json_encode([
    'revenue'           => t('fin_js.revenue'),
    'costs'             => t('fin_js.costs'),
    'net_profit'        => t('fin_js.net_profit'),
    'losses'            => t('fin_js.losses'),
    'savings_can_change'=> t('finance.savings_plan_can_change'),
], JSON_UNESCAPED_UNICODE) ?>;
window._FIN_MSG      = <?= json_encode($msg) ?>;
window._FIN_ERR      = <?= json_encode($err) ?>;
window._FIN_CUR_MODE      = <?= json_encode((string)(($policySnapshot['savings']['mode'] ?? 'off'))) ?>;
window._FIN_COOLDOWN_SECS = <?= json_encode((int)($policySnapshot['savings']['cooldown_remaining_seconds'] ?? 0)) ?>;
window._FIN_COOLDOWN_TPL  = <?= json_encode(t('finance.savings_plan_cooldown_left', ['h' => '__H__', 'm' => '__M__']), JSON_UNESCAPED_UNICODE) ?>;
window._FIN_CUR_RESERVE = <?= json_encode((string)($settings['reserve_policy'] ?? 'standard')) ?>;
window._FIN_CONFIRM  = <?= json_encode([
    'aggressive' => t('finance.confirm_aggressive'),
    'moderate'   => t('finance.confirm_moderate'),
    'turnoff'    => t('finance.confirm_turnoff'),
    'reserve'    => t('finance.confirm_reserve'),
], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/assets/js/finance.js"></script>
