<?php
$tasksByWell = [];
foreach ($activeTasks as $t) {
    if (!empty($t['well_id'])) {
        $tasksByWell[(int)$t['well_id']][] = $t;
    }
}

$statusLabels = [
    'active'         => ['lbl' => t('technical.ws_active'),         'cls' => 'b-active',  'icon' => t('technical.short_active')],
    'paused_staff'   => ['lbl' => t('technical.ws_paused_staff'),   'cls' => 'b-staff',   'icon' => t('technical.short_stop')],
    'paused_cash'    => ['lbl' => t('technical.ws_paused_cash'),    'cls' => 'b-broken',  'icon' => t('technical.short_cash')],
    'paused_storage' => ['lbl' => t('technical.ws_paused_storage'), 'cls' => 'b-paused',  'icon' => t('technical.short_storage')],
    'contaminated'   => ['lbl' => t('technical.ws_contaminated'),   'cls' => 'b-broken',  'icon' => t('technical.short_contamination')],
    'blowout'        => ['lbl' => t('technical.ws_blowout'),        'cls' => 'b-broken',  'icon' => t('technical.short_blowout')],
    'seized'         => ['lbl' => t('technical.ws_seized'),         'cls' => 'b-broken',  'icon' => t('technical.short_seized')],
    'broken'         => ['lbl' => t('technical.ws_broken'),         'cls' => 'b-broken',  'icon' => t('technical.short_error')],
];

$specLabels = [
    'safety_officer'       => t('technical.spec_safety_officer'),
    'safety_engineer'      => t('technical.spec_safety_engineer'),
    'maintenance_engineer' => t('technical.spec_maintenance'),
    'production_engineer'  => t('technical.spec_production'),
];
?>

<?php if (empty($wells)): ?>
<div class="empty-state">
    <?= t('technical.no_wells', ['url' => url('map')]) ?>
</div>
<?php else: ?>

<div class="wells-grid">
<?php foreach ($wells as $w):
    $wId       = (int)$w['id'];
    $cond      = (float)$w['technical_condition'];
    $condFill  = $cond >= 70 ? 'cond-good' : ($cond >= 40 ? 'cond-warn' : 'cond-bad');
    $condCol   = $cond >= 70 ? 'c-good'    : ($cond >= 40 ? 'c-warn'    : 'c-bad');
    $pressure  = (float)($w['pressure'] ?? 1.0);
    $reservoir = (float)($w['reservoir_remaining'] ?? 0);
    $resMax    = (float)($w['reservoir_max'] ?? 800000);
    $resPct    = $resMax > 0 ? round($reservoir / $resMax * 100, 1) : 0;
    $riskScore = (float)($w['risk_score'] ?? 0);
    $status    = $w['status'] ?? 'active';
    $stInfo    = $statusLabels[$status] ?? ['lbl' => $status, 'cls' => 'b-paused', 'icon' => 'STOP'];
    $isActive  = $status === 'active';
    $isPaused  = str_starts_with($status, 'paused') || in_array($status, ['broken', 'seized', 'blowout', 'contaminated'], true);
    $wellTasks = $tasksByWell[$wId] ?? [];
    $boostPct  = (float)($w['production_boost_pct'] ?? 0);
    $riskCls   = $riskScore >= 60 ? 'c-bad' : ($riskScore >= 30 ? 'c-warn' : 'c-good');

    $effPressData = WellService::getEffectivePressure($w);
    $effPressure  = $effPressData['effective'];
    $effPressPct  = round($effPressure * 100, 1);
    $effPressCls  = $effPressPct >= 80 ? 'c-good' : ($effPressPct >= 50 ? 'c-warn' : 'c-bad');
    $depletionCls = $resPct >= 40 ? 'c-good' : ($resPct >= 20 ? 'c-warn' : 'c-bad');
    $effProd      = round((float)$w['base_production_per_hour'] * $effPressure * (1 + $boostPct / 100), 1);

    $missingSpecs = [];
    if ($status === 'paused_staff' && !empty($w['paused_staff_reason'])) {
        foreach (explode(',', $w['paused_staff_reason']) as $code) {
            $code = trim($code);
            if ($code !== '') {
                $missingSpecs[] = $specLabels[$code] ?? $code;
            }
        }
    }
?>
<details class="well-card <?= $isPaused ? 'well-card--paused' : '' ?> <?= $status === 'paused_staff' ? 'well-card--staff' : '' ?>">
    <summary class="well-card-summary">
        <div class="well-card-hdr">
            <div class="well-card-title">
                <span class="well-num"><?= t('technical.well_num', ['id' => $wId]) ?> &middot; <?= t('technical.well_type_' . ($w['well_type'] ?? 'onshore')) ?></span>
                <span class="well-name"><?= htmlspecialchars($w['location_name'] ?? t('technical.well_default_name')) ?></span>
            </div>
            <div class="well-card-badges">
                <span class="badge <?= $stInfo['cls'] ?>"><?= $stInfo['icon'] ?> <?= $stInfo['lbl'] ?></span>
                <?php if (!empty($wellTasks)): ?>
                <span class="badge b-busy"><?= t('technical.short_task') ?> <?= count($wellTasks) ?> <?= t('technical.well_task_badge') ?></span>
                <?php endif ?>
            </div>
        </div>

        <div class="well-stats">
            <div>
                <div class="w-stat-lbl"><?= t('technical.stat_prod') ?></div>
                <div class="w-stat-val <?= $isActive ? 'c-gold' : 'c-muted' ?>">
                    <?= $isActive ? $effProd : '-' ?> <?= $isActive ? 'bbl/h' : '' ?>
                </div>
            </div>
            <div>
                <div class="w-stat-lbl"><?= t('technical.stat_eff_pressure') ?></div>
                <div class="w-stat-val <?= $effPressCls ?>" title="Baza: <?= $pressure ?> - zloze: <?= $resPct ?>%">
                    <?= $effPressPct ?>%
                </div>
            </div>
            <div>
                <div class="w-stat-lbl"><?= t('technical.stat_reservoir') ?></div>
                <div class="w-stat-val <?= $depletionCls ?>">
                    <?= number_format($reservoir, 0, '.', ' ') ?> bbl <span class="c-muted">(<?= $resPct ?>%)</span>
                </div>
            </div>
            <div>
                <div class="w-stat-lbl"><?= t('technical.stat_depth') ?></div>
                <div class="w-stat-val c-muted"><?= number_format($w['depth_m'] ?? 2500, 0, '.', ' ') ?> m</div>
            </div>
        </div>

        <div class="well-cond-wrap">
            <div class="well-cond-row">
                <span class="<?= $condCol ?>"><?= t('technical.tech_condition') ?></span>
                <span class="<?= $condCol ?> fw7"><?= round($cond, 1) ?>%</span>
            </div>
            <div class="cond-bar"><div class="cond-fill <?= $condFill ?>" style="--bar-w:<?= $cond ?>%"></div></div>
        </div>

        <div class="well-expand-hint">&#9662; <?= t('technical.details_hint') ?></div>
    </summary>

    <div class="well-detail">
        <?php if ($status === 'paused_staff'): ?>
        <div class="well-diag well-diag--staff">
            <div class="well-diag-title"><?= t('technical.short_stop') ?> <?= t('technical.diag_paused_staff_title') ?></div>
            <div class="well-diag-body">
                <p class="well-diag-note"><?= t('technical.diag_paused_staff_note') ?></p>
                <ul class="well-missing-list">
                    <?php foreach ($missingSpecs as $ms): ?>
                    <li class="well-missing-item">- <?= htmlspecialchars($ms) ?></li>
                    <?php endforeach ?>
                </ul>
                <p class="well-diag-note"><?= t('technical.diag_auto_resume') ?></p>
            </div>
            <a href="?tab=candidates" class="btn btn-primary btn-sm well-diag-btn"><?= t('technical.diag_go_candidates') ?></a>
        </div>

        <?php elseif ($status === 'paused_cash'): ?>
        <div class="well-diag well-diag--cash">
            <div class="well-diag-title"><?= t('technical.short_cash') ?> <?= t('technical.diag_paused_cash_title') ?></div>
            <div class="well-diag-body">
                <p class="well-diag-note"><?= t('technical.diag_paused_cash_note') ?></p>
                <p class="well-diag-note"><?= t('technical.diag_opex') ?>: <strong><?= number_format((float)($w['upkeep_cost_per_hour'] ?? 0), 2, '.', ' ') ?> <?= t('common.currency') ?>/h</strong></p>
            </div>
            <a href="<?= url('market') ?>" class="btn btn-primary btn-sm well-diag-btn"><?= t('technical.diag_sell_oil') ?></a>
        </div>

        <?php elseif ($status === 'paused_storage'): ?>
        <div class="well-diag well-diag--storage">
            <div class="well-diag-title"><?= t('technical.short_storage') ?> <?= t('technical.diag_paused_storage_title') ?></div>
            <div class="well-diag-body">
                <p class="well-diag-note"><?= t('technical.diag_paused_storage_note') ?></p>
            </div>
            <a href="<?= url('market') ?>" class="btn btn-primary btn-sm well-diag-btn"><?= t('technical.diag_sell_oil') ?></a>
        </div>

        <?php elseif ($status === 'blowout'): ?>
        <div class="well-diag well-diag--disaster">
            <div class="well-diag-title"><?= t('technical.short_blowout') ?> <?= t('technical.diag_blowout_title') ?></div>
            <div class="well-diag-body">
                <p class="well-diag-note"><?= t('technical.diag_blowout_note') ?></p>
            </div>
            <a href="?tab=tasks" class="btn btn-primary btn-sm well-diag-btn"><?= t('technical.diag_go_tasks') ?></a>
        </div>

        <?php elseif ($status === 'contaminated'): ?>
        <div class="well-diag well-diag--disaster">
            <div class="well-diag-title"><?= t('technical.short_contamination') ?> <?= t('technical.diag_contaminated_title') ?></div>
            <div class="well-diag-body">
                <p class="well-diag-note"><?= t('technical.diag_contaminated_note') ?></p>
            </div>
            <a href="?tab=tasks" class="btn btn-primary btn-sm well-diag-btn"><?= t('technical.diag_go_tasks') ?></a>
        </div>

        <?php else: ?>
        <div class="well-detail-grid">
            <?php
            $defaultTransport = (($w['well_type'] ?? 'onshore') === 'offshore') ? 'tankowiec' : 'nieustawiony';
            $tType = (string)($w['transport_type'] ?? $defaultTransport);
            $tCap = (float)($w['transport_capacity_pct'] ?? ($tType === 'nieustawiony' ? 0.0 : 120.0));
            $tIcons = ['nieustawiony' => '---', 'rurociag' => 'RUR', 'ciezarowki' => 'TIR', 'tankowiec' => 'TNK'];
            $tLabels = [
                'nieustawiony' => t('technical.transport_nieustawiony'),
                'rurociag' => t('technical.transport_rurociag'),
                'ciezarowki' => t('technical.transport_ciezarowki'),
                'tankowiec' => t('technical.transport_tankowiec'),
            ];
            ?>
            <div class="well-detail-stat"><div class="well-detail-lbl"><?= t('technical.stat_transport') ?></div><div class="well-detail-val"><?= $tIcons[$tType] ?? '?' ?> <?= $tLabels[$tType] ?? $tType ?> <span class="c-muted">(<?= $tCap ?>%)</span></div></div>
            <div class="well-detail-stat"><div class="well-detail-lbl"><?= t('technical.stat_failure_risk') ?></div><div class="well-detail-val <?= $riskCls ?>"><?= number_format($riskScore, 1) ?> / 100</div></div>
            <div class="well-detail-stat"><div class="well-detail-lbl"><?= t('technical.stat_risk_level') ?></div><div class="well-detail-val <?= ($w['risk_level'] ?? 10) > 15 ? 'c-bad' : 'c-good' ?>"><?= $w['risk_level'] ?? 10 ?></div></div>
            <div class="well-detail-stat">
                <div class="well-detail-lbl"><?= t('technical.stat_prod_mode') ?></div>
                <div class="well-detail-val"><?= match($w['production_mode'] ?? 'normal') {
                    'eco'   => t('technical.mode_eco'),
                    'boost' => t('technical.mode_boost'),
                    default => t('technical.mode_normal'),
                } ?></div>
            </div>
            <div class="well-detail-stat">
                <div class="well-detail-lbl"><?= t('technical.stat_reservoir_pct') ?></div>
                <div class="well-detail-val <?= $resPct < 20 ? 'c-bad' : ($resPct < 50 ? 'c-warn' : 'c-good') ?>"><?= $resPct ?>%</div>
            </div>
            <?php if ($boostPct > 0): ?>
            <div class="well-detail-stat"><div class="well-detail-lbl"><?= t('technical.stat_prod_bonus') ?></div><div class="well-detail-val c-green">+<?= number_format($boostPct, 1) ?>%</div></div>
            <?php endif ?>
            <?php if ($pressure !== 1.0): ?>
            <div class="well-detail-stat">
                <div class="well-detail-lbl"><?= t('technical.stat_pressure_effect') ?></div>
                <div class="well-detail-val <?= $pressure >= 1.0 ? 'c-green' : 'c-warn' ?>">
                    <?= $pressure >= 1.0 ? '+' : '' ?><?= number_format(($pressure - 1.0) * 100, 1) ?>%
                </div>
            </div>
            <?php endif ?>
        </div>
        <?php endif ?>

        <?php if (!empty($wellTasks)): ?>
        <div class="well-tasks-list">
            <div class="well-tasks-hdr"><?= t('technical.active_tasks_label') ?></div>
            <?php foreach ($wellTasks as $t):
                $tEnd = strtotime($t['end_time']);
                $tLeft = max(0, $tEnd - time());
                $tH = floor($tLeft / 3600);
                $tM = floor(($tLeft % 3600) / 60);
            ?>
            <div class="well-task-row">
                <span class="well-task-icon"><?= TechnicalTeamService::getTaskDefinition($t['task_type'])['icon'] ?? t('technical.short_task') ?></span>
                <span class="well-task-name"><?= htmlspecialchars($t['title']) ?></span>
                <span class="well-task-who c-muted"><?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?></span>
                <span class="well-task-time c-warn">
                    <?= $tH > 0 ? "{$tH}h " : '' ?><?= $tM ?>m
                </span>
            </div>
            <?php endforeach ?>
        </div>
        <?php endif ?>
    </div>
</details>
<?php endforeach ?>
</div>
<?php endif ?>
