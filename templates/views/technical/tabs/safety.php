<?php
$locale = $_SESSION['locale'] ?? $_COOKIE['locale'] ?? 'pl';
$currencyLabel = $locale === 'en' ? 'USD' : 'PLN';
$hseActive    = $hseBonus['active_hse'] > 0;
$failRedPct   = (int)round((1 - $hseBonus['failure_reduction'])  * 100);
$catRedPct    = (int)round((1 - $hseBonus['catastrophe_mult'])   * 100);
$repRedPct    = (int)round((1 - $hseBonus['repair_cost_mult'])   * 100);
$degradRedPct = (int)round((1 - $hseBonus['degrade_mult'])       * 100);
$uptimePct    = round($hseBonus['uptime_bonus'] * 100, 1);
?>
<div class="hse-panel <?= $hseActive ? 'hse-panel--active' : 'hse-panel--inactive' ?>">
    <div class="hse-panel-hdr">
        <span class="hse-icon"><?= $hseActive ? '' : '' ?></span>
        <span class="hse-title"><?= t('technical.hse_title') ?></span>
        <span class="hse-status-badge <?= $hseActive ? 'hse-badge--on' : 'hse-badge--off' ?>">
            <?= $hseActive ? t('technical.hse_active') : t('technical.hse_inactive') ?>
        </span>
        <?php if ($hseBonus['audit_bonus']): ?>
            <span class="hse-audit-badge"> <?= t('technical.hse_audit_active') ?></span>
        <?php endif ?>
    </div>

    <?php if ($hseActive): ?>
    <div class="hse-metrics">
        <div class="hse-metric hse-good"><div class="hse-metric-val">-<?= $failRedPct ?>%</div><div class="hse-metric-lbl"><?= t('technical.hse_failure_chance') ?></div></div>
        <div class="hse-metric hse-good"><div class="hse-metric-val">-<?= $catRedPct ?>%</div><div class="hse-metric-lbl"><?= t('technical.hse_disaster_risk') ?></div></div>
        <div class="hse-metric hse-good"><div class="hse-metric-val">-<?= $repRedPct ?>%</div><div class="hse-metric-lbl"><?= t('technical.hse_repair_cost') ?></div></div>
        <div class="hse-metric hse-good"><div class="hse-metric-val">-<?= $degradRedPct ?>%</div><div class="hse-metric-lbl"><?= t('technical.hse_degradation') ?></div></div>
        <?php if ($uptimePct > 0): ?>
        <div class="hse-metric hse-good"><div class="hse-metric-val">+<?= $uptimePct ?>%</div><div class="hse-metric-lbl"><?= t('technical.hse_uptime') ?></div></div>
        <?php endif ?>
    </div>
    <div class="hse-note">
        <?= t('technical.hse_note', ['count' => $hseBonus['active_hse']]) ?>
        <?php if ($hseBonus['audit_bonus']): ?>
            <strong><?= t('technical.hse_audit_effect') ?></strong>
        <?php endif ?>
    </div>
    <?php else: ?>
    <div class="hse-warning-block">
        <p class="hse-warn-title"> <?= t('technical.hse_no_staff_title') ?></p>
        <p><?= t('technical.hse_no_staff_desc') ?></p>
        <ul class="hse-risk-list">
            <li> <?= t('technical.hse_risk_1') ?></li>
            <li> <?= t('technical.hse_risk_2') ?></li>
            <li> <?= t('technical.hse_risk_3') ?></li>
        </ul>
        <p class="hse-fix-hint"> <?= t('technical.hse_hire_hint') ?></p>
    </div>
    <?php endif ?>
</div>

<?php
$pLevel     = $procStatus['level'];
$pIntegrity = $procStatus['integrity'];
$pFactor    = $hseBonus['proc_factor'];
$integrityCls    = $pIntegrity > 60 ? 'proc-integrity--ok' : ($pIntegrity > 30 ? 'proc-integrity--warn' : 'proc-integrity--crit');
$integrityStatus = $pIntegrity > 60 ? t('technical.proc_stable') : ($pIntegrity > 30 ? t('technical.proc_wear') : t('technical.proc_critical'));
$nextLevel   = $pLevel + 1;
$upgradeCost = TechnicalTeamService::PROCEDURE_UPGRADE_COSTS[$nextLevel] ?? null;
$repairCost  = $pLevel > 0 ? 500_000 * $pLevel : 0;
?>

<div class="proc-panel">
    <div class="proc-panel-hdr">
        <span class="proc-icon"></span>
        <span class="proc-title"><?= t('technical.proc_title') ?></span>
        <span class="proc-level-badge"><?= t('technical.proc_level', ['level' => $pLevel]) ?></span>
        <?php if ($pLevel > 0): ?>
            <span class="proc-status-badge <?= $integrityCls ?>"><?= $integrityStatus ?></span>
        <?php endif ?>
    </div>

    <?php if ($pLevel > 0): ?>
    <div class="proc-body">
        <div class="proc-integrity-row">
            <span class="proc-integrity-lbl"><?= t('technical.proc_integrity_label') ?></span>
            <div class="proc-bar-wrap">
                <div class="proc-bar-fill <?= $integrityCls ?>" style="width:<?= $pIntegrity ?>%"></div>
            </div>
            <span class="proc-integrity-val <?= $integrityCls ?>"><?= number_format($pIntegrity, 1) ?>%</span>
        </div>

        <?php if ($pFactor > 0): ?>
        <div class="proc-metrics">
            <?php
            $pFailPct = (int)round((1 - (1.0 - 0.20 * $pFactor)) * 100);
            $pCatPct  = (int)round((1 - (1.0 - 0.40 * $pFactor)) * 100);
            $pDegPct  = (int)round((1 - (1.0 - 0.25 * $pFactor)) * 100);
            $pRepPct  = (int)round((1 - (1.0 - 0.10 * $pFactor)) * 100);
            ?>
            <div class="proc-metric"><div class="proc-metric-val">-<?= $pFailPct ?>%</div><div class="proc-metric-lbl"><?= t('technical.hse_failure_chance') ?></div></div>
            <div class="proc-metric"><div class="proc-metric-val">-<?= $pCatPct ?>%</div><div class="proc-metric-lbl"><?= t('technical.hse_disaster_risk') ?></div></div>
            <div class="proc-metric"><div class="proc-metric-val">-<?= $pDegPct ?>%</div><div class="proc-metric-lbl"><?= t('technical.hse_degradation') ?></div></div>
            <div class="proc-metric"><div class="proc-metric-val">-<?= $pRepPct ?>%</div><div class="proc-metric-lbl"><?= t('technical.hse_repair_cost') ?></div></div>
        </div>
        <?php else: ?>
        <div class="proc-integrity-zero"> <?= t('technical.proc_zero_integrity') ?></div>
        <?php endif ?>

        <?php if ($pIntegrity < 100): ?>
        <form method="post" class="proc-action-form"
              onsubmit="return confirmSubmit(this, '<?= htmlspecialchars(t('technical.proc_repair_confirm', ['cost' => number_format($repairCost, 0, '.', ' ')]), ENT_QUOTES, 'UTF-8') ?>', { title: '<?= htmlspecialchars(t('technical.proc_repair_title'), ENT_QUOTES, 'UTF-8') ?>', confirmLabel: '<?= htmlspecialchars(t('technical.btn_confirm_generic'), ENT_QUOTES, 'UTF-8') ?>' })">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="repair_procedure_integrity">
            <button type="submit" class="btn-proc-repair">
                 <?= t('technical.proc_repair_btn', ['cost' => number_format($repairCost, 0, '.', ' ')]) ?>
            </button>
        </form>
        <?php endif ?>

    </div>
    <?php else: ?>
    <div class="proc-empty">
        <p><?= t('technical.proc_none_desc') ?></p>
        <ul class="proc-benefit-list">
            <li> <?= t('technical.proc_benefit_1') ?></li>
            <li> <?= t('technical.proc_benefit_3') ?></li>
            <li> <?= t('technical.proc_benefit_5') ?></li>
        </ul>
        <p class="proc-note"><?= t('technical.proc_decay_note') ?></p>
    </div>
    <?php endif ?>

    <?php if ($pLevel < 5): ?>
    <div class="proc-upgrade-section">
        <div class="proc-upgrade-hdr"><?= t('technical.proc_upgrade_hdr', ['level' => $nextLevel]) ?></div>
        <?php if (!$canUpgradeProcedures): ?>
            <div class="proc-upgrade-blocked">
                <?php if (!$hasHseStaff['officer'] || !$hasHseStaff['engineer']): ?>
                     <?= t('technical.proc_blocked_staff') ?>:
                    <ul class="tech-missing-list">
                        <?php if (!$hasHseStaff['officer']): ?><li><?= t('technical.proc_missing_officer') ?></li><?php endif ?>
                        <?php if (!$hasHseStaff['engineer']): ?><li><?= t('technical.proc_missing_engineer') ?></li><?php endif ?>
                    </ul>
                    <div class="tech-hire-hint"> <?= t('technical.proc_hire_hint') ?> <a href="?tab=candidates" class="tech-gold-link"><?= t('technical.tab_candidates') ?></a>.</div>
                <?php elseif (!$auditDone): ?>
                     <?= t('technical.proc_blocked_audit') ?>
                <?php endif ?>
            </div>
        <?php else: ?>
            <div class="proc-upgrade-info">
                <?= t('technical.proc_upgrade_cost', ['cost' => number_format($upgradeCost, 0, '.', ' ')]) ?>
            </div>
            <form method="post" class="proc-action-form"
                  onsubmit="return confirmSubmit(this, '<?= htmlspecialchars(t('technical.proc_upgrade_confirm', ['level' => $nextLevel, 'cost' => number_format($upgradeCost, 0, '.', ' ')]), ENT_QUOTES, 'UTF-8') ?>', { title: '<?= htmlspecialchars(t('technical.proc_upgrade_title'), ENT_QUOTES, 'UTF-8') ?>', confirmLabel: '<?= htmlspecialchars(t('technical.btn_confirm_generic'), ENT_QUOTES, 'UTF-8') ?>' })">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="upgrade_procedures">
                <button type="submit" class="btn-proc-upgrade">
                     <?= t('technical.proc_upgrade_btn', ['level' => $nextLevel, 'cost' => number_format($upgradeCost, 0, '.', ' ')]) ?>
                </button>
            </form>
        <?php endif ?>
    </div>
    <?php endif ?>
</div>

<?php
$wellsByStatus = [];
foreach ($brokenWells as $w) $wellsByStatus[$w['status']][] = $w;

$statusInfo = [
    'paused_staff' => ['icon' => '', 'title' => t('technical.si_paused_staff'),   'cls' => 'hse-panel--inactive', 'badge_cls' => 'hse-badge--off', 'badge' => t('technical.si_badge_no_staff')],
    'paused_cash'  => ['icon' => '', 'title' => t('technical.si_paused_cash'),    'cls' => 'hse-panel--inactive', 'badge_cls' => 'hse-badge--off', 'badge' => t('technical.si_badge_no_cash')],
    'broken'       => ['icon' => '', 'title' => t('technical.si_broken'),         'cls' => 'hse-panel--inactive', 'badge_cls' => 'hse-badge--off', 'badge' => t('technical.si_badge_broken')],
    'blowout'      => ['icon' => '', 'title' => t('technical.si_blowout'),        'cls' => 'hse-panel--inactive', 'badge_cls' => 'hse-badge--off', 'badge' => t('technical.si_badge_disaster')],
    'contaminated' => ['icon' => '', 'title' => t('technical.si_contaminated'),   'cls' => 'hse-panel--inactive', 'badge_cls' => 'hse-badge--off', 'badge' => t('technical.si_badge_contaminated')],
];

$actionHint = [
    'paused_staff' => t('technical.ah_paused_staff'),
    'paused_cash'  => t('technical.ah_paused_cash'),
    'broken'       => t('technical.ah_broken'),
    'blowout'      => t('technical.ah_blowout'),
    'contaminated' => t('technical.ah_contaminated'),
];

$specLabelsSafety = [
    'safety_officer'       => t('technical.spec_safety_officer'),
    'safety_engineer'      => t('technical.spec_safety_engineer'),
    'maintenance_engineer' => t('technical.spec_maintenance'),
    'production_engineer'  => t('technical.spec_production'),
];
?>

<?php if (!empty($brokenWells)): ?>
<?php foreach ($statusInfo as $stCode => $stMeta): ?>
<?php if (!empty($wellsByStatus[$stCode])): ?>
<div class="hse-panel <?= $stMeta['cls'] ?>">
    <div class="hse-panel-hdr">
        <span class="hse-icon"><?= $stMeta['icon'] ?></span>
        <span class="hse-title"><?= $stMeta['title'] ?></span>
        <span class="hse-status-badge <?= $stMeta['badge_cls'] ?>"><?= $stMeta['badge'] ?></span>
    </div>
    <div class="hse-warning-block">
        <ul class="hse-risk-list">
        <?php foreach ($wellsByStatus[$stCode] as $w):
            $cond    = round((float)$w['technical_condition'], 1);
            $condCls = $cond >= 70 ? 'c-good' : ($cond >= 40 ? 'c-warn' : 'c-bad');
        ?>
            <li>
                <span class="fw7"><?= t('technical.well_num', ['id' => $w['id']]) ?></span>
                <span class="c-muted fs12">&middot; <?= htmlspecialchars($w['location_name'] ?? '') ?></span>
                <span class="<?= $condCls ?> fw7">&middot; <?= t('technical.condition_label') ?>: <?= $cond ?>%</span>
                <?php if ($stCode === 'paused_staff' && !empty($w['paused_staff_reason'])): ?>
                <br><span class="c-muted fs12"><?= t('technical.missing_label') ?>: <?= htmlspecialchars(implode(', ', array_map(fn($c) => $specLabelsSafety[trim($c)] ?? trim($c), explode(',', $w['paused_staff_reason'])))) ?></span>
                <?php endif ?>
            </li>
        <?php endforeach ?>
        </ul>
        <p class="hse-fix-hint"> <?= $actionHint[$stCode] ?? '' ?></p>
    </div>
</div>
<?php endif ?>
<?php endforeach ?>
<?php endif ?>

<?php
$riskWells = array_filter($wells, fn($w) => $w['status'] === 'active' && $w['technical_condition'] < 60);
if (!empty($riskWells)):
?>
<div class="hse-panel hse-panel--warn">
    <div class="hse-panel-hdr">
        <span class="hse-icon"></span>
        <span class="hse-title"><?= t('technical.risk_panel_title') ?></span>
        <span class="hse-status-badge hse-badge--warn"><?= t('technical.risk_badge') ?></span>
    </div>
    <div class="hse-warning-block">
        <ul class="hse-risk-list">
        <?php foreach ($riskWells as $w):
            $cond = (float)$w['technical_condition'];
            $risk = round(max(0, (70 - $cond) * 0.5), 1);
        ?>
            <li>
                <span class="fw7 c-warn"><?= t('technical.well_num', ['id' => $w['id']]) ?></span>
                <span class="c-muted fs12">&middot; <?= htmlspecialchars($w['location_name'] ?? '') ?></span>
                <span class="c-warn fw7">&middot; <?= t('technical.condition_label') ?>: <?= round($cond, 1) ?>%</span>
                <span class="c-bad fs12">&middot; <?= t('technical.failure_risk_per_h', ['risk' => $risk]) ?></span>
            </li>
        <?php endforeach ?>
        </ul>
        <p class="hse-fix-hint"> <?= t('technical.risk_fix_hint') ?></p>
    </div>
</div>
<?php endif ?>

<?php if (empty($brokenWells) && empty($riskWells)): ?>
<div class="hse-panel hse-panel--ok">
    <div class="hse-panel-hdr">
        <span class="hse-icon"></span>
        <span class="hse-title"><?= t('technical.wells_ok_title') ?></span>
        <span class="hse-status-badge hse-badge--on"><?= t('technical.wells_ok_badge') ?></span>
    </div>
    <div class="hse-note"> <?= t('technical.wells_ok_note') ?></div>
</div>
<?php endif ?>

<?php if (!empty($activeDisasters)): ?>
<div class="section-h"> <?= t('technical.disasters_section', ['count' => count($activeDisasters)]) ?></div>
<?php
$disasterLabels = [
    'blowout'                 => ['icon' => '', 'label' => t('technical.disaster_blowout'),       'cls' => 'disaster-type-blowout'],
    'pipeline_explosion'      => ['icon' => '', 'label' => t('technical.disaster_pipeline'),      'cls' => 'disaster-type-pipeline'],
    'reservoir_contamination' => ['icon' => '', 'label' => t('technical.disaster_contamination'), 'cls' => 'disaster-type-contamination'],
    'surface_spill'           => ['icon' => '', 'label' => t('technical.disaster_spill'),         'cls' => 'disaster-type-spill'],
];
$repairTaskHint = [
    'blowout'                 => t('technical.repair_hint_blowout'),
    'pipeline_explosion'      => t('technical.repair_hint_pipeline'),
    'reservoir_contamination' => t('technical.repair_hint_contamination'),
    'surface_spill'           => t('technical.repair_hint_spill'),
];
foreach ($activeDisasters as $d):
    $dInfo = $disasterLabels[$d['disaster_type']] ?? ['icon' => '', 'label' => $d['disaster_type'], 'cls' => ''];
    $hint  = $repairTaskHint[$d['disaster_type']] ?? '';
?>
<div class="disaster-panel">
    <div class="disaster-panel-hdr">
        <span class="disaster-icon"><?= $dInfo['icon'] ?></span>
        <span class="disaster-title <?= $dInfo['cls'] ?>"><?= $dInfo['label'] ?></span>
        <span class="disaster-badge"><?= $d['status'] === 'being_repaired' ? t('technical.disaster_being_repaired') : t('technical.disaster_active') ?></span>
    </div>
    <p class="disaster-desc"><?= htmlspecialchars($d['description'] ?? '') ?></p>
    <div class="disaster-stats">
        <?php if ($d['repair_cost'] > 0): ?>
        <div class="disaster-stat"><div class="disaster-stat-val cost"><?= number_format($d['repair_cost'], 0, ',', ' ') ?> <?= $currencyLabel ?></div><div class="disaster-stat-lbl"><?= t('technical.disaster_repair_cost') ?></div></div>
        <?php endif ?>
        <?php if ($d['env_fine'] > 0): ?>
        <div class="disaster-stat"><div class="disaster-stat-val fine"><?= number_format($d['env_fine'], 0, ',', ' ') ?> <?= $currencyLabel ?></div><div class="disaster-stat-lbl"><?= t('technical.disaster_env_fine') ?></div></div>
        <?php endif ?>
        <?php if ($d['reservoir_lost'] > 0): ?>
        <div class="disaster-stat"><div class="disaster-stat-val loss"><?= number_format($d['reservoir_lost'], 0, ',', ' ') ?> bbl</div><div class="disaster-stat-lbl"><?= t('technical.disaster_reservoir_lost') ?></div></div>
        <?php endif ?>
        <?php if ($d['oil_lost'] > 0): ?>
        <div class="disaster-stat"><div class="disaster-stat-val loss"><?= number_format($d['oil_lost'], 0, ',', ' ') ?> bbl</div><div class="disaster-stat-lbl"><?= t('technical.disaster_oil_lost') ?></div></div>
        <?php endif ?>
        <div class="disaster-stat"><div class="disaster-stat-val"><?= date('d.m H:i', strtotime($d['occurred_at'])) ?></div><div class="disaster-stat-lbl"><?= t('technical.disaster_date') ?></div></div>
    </div>
    <?php if ($hint): ?><div class="disaster-action-hint"> <?= $hint ?></div><?php endif ?>
    <?php if ($d['hse_active']): ?><div class="disaster-hse-note"> <?= t('technical.disaster_hse_note') ?></div><?php endif ?>
</div>
<?php endforeach ?>
<?php endif ?>

<?php if (!empty($failures)): ?>
<div class="section-h"><?= t('technical.failures_history') ?></div>
<div class="disaster-history-hdr">
    <span></span>
    <span><?= t('technical.col_event') ?></span>
    <span><?= t('technical.col_repair_cost') ?></span>
    <span><?= t('technical.col_env_fine') ?></span>
    <span><?= t('technical.col_date') ?></span>
    <span><?= t('technical.col_status') ?></span>
</div>
<?php
$fTypes = [
    'pump'                    => ['icon' => '', 'label' => t('technical.failure_pump'),           'cls' => ''],
    'pipeline'                => ['icon' => '', 'label' => t('technical.failure_pipeline'),       'cls' => ''],
    'electrical'              => ['icon' => '', 'label' => t('technical.failure_electrical'),     'cls' => ''],
    'pressure_drop'           => ['icon' => '', 'label' => t('technical.failure_pressure_drop'),  'cls' => ''],
    'blowout'                 => ['icon' => '', 'label' => t('technical.disaster_blowout'),       'cls' => 'disaster-type-blowout'],
    'pipeline_explosion'      => ['icon' => '', 'label' => t('technical.disaster_pipeline'),      'cls' => 'disaster-type-pipeline'],
    'reservoir_contamination' => ['icon' => '', 'label' => t('technical.disaster_contamination'), 'cls' => 'disaster-type-contamination'],
    'surface_spill'           => ['icon' => '', 'label' => t('technical.disaster_spill'),         'cls' => 'disaster-type-spill'],
];
foreach ($failures as $f):
    $ft = $fTypes[$f['failure_type']] ?? ['icon' => '?', 'label' => $f['failure_type'], 'cls' => ''];
?>
<div class="disaster-history-row">
    <span class="tech-icon-lg"><?= $ft['icon'] ?></span>
    <span>
        <span class="<?= $ft['cls'] ?>"><?= $ft['label'] ?></span>
        <?php if ($f['well_id'] > 0): ?>
            <small class="c-muted2">&middot; #<?= $f['well_id'] ?> <?= htmlspecialchars($f['location_name'] ?? '') ?></small>
        <?php endif ?>
    </span>
    <span class="<?= $f['repair_cost'] > 0 ? 'c-bad' : 'c-muted' ?>">
        <?= $f['repair_cost'] > 0 ? number_format($f['repair_cost'], 0, ',', ' ') . ' ' . $currencyLabel : '&mdash;' ?>
    </span>
    <span class="<?= ($f['environmental_fine'] ?? 0) > 0 ? 'c-warn' : 'c-muted' ?>">
        <?= ($f['environmental_fine'] ?? 0) > 0 ? number_format($f['environmental_fine'], 0, ',', ' ') . ' ' . $currencyLabel : '&mdash;' ?>
    </span>
    <span class="c-muted2 sm"><?= date('d.m H:i', strtotime($f['occurred_at'])) ?></span>
    <span>
        <span class="badge <?= $f['resolved'] ? 'b-active' : 'b-broken' ?>">
            <?= $f['resolved'] ? t('technical.failure_resolved') : t('technical.failure_active') ?>
        </span>
    </span>
</div>
<?php endforeach ?>
<?php endif ?>

<?php if (empty($activeDisasters) && empty($failures) && empty($brokenWells)): ?>
<div class="safety-ok"> <?= t('technical.safety_all_ok') ?></div>
<?php endif ?>
