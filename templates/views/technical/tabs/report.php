<div class="prod-grid">
    <div class="prod-stat"><div class="prod-val"><?= count($wells) ?></div><div class="prod-unit"><?= t('technical.report_wells') ?></div></div>
    <div class="prod-stat"><div class="prod-val c-green"><?= count($activeWells) ?></div><div class="prod-unit"><?= t('technical.report_active') ?></div></div>
    <div class="prod-stat"><div class="prod-val <?= $avgCond >= 70 ? 'c-green' : 'c-warn' ?>"><?= $avgCond ?>%</div><div class="prod-unit"><?= t('technical.avg_condition') ?></div></div>
    <div class="prod-stat"><div class="prod-val c-bad"><?= count($brokenWells) ?></div><div class="prod-unit"><?= t('technical.report_failures') ?></div></div>
    <div class="prod-stat"><div class="prod-val c-gold"><?= count($staff) ?></div><div class="prod-unit"><?= t('technical.report_engineers') ?></div></div>
    <div class="prod-stat"><div class="prod-val c-blue"><?= count($activeTasks) ?></div><div class="prod-unit"><?= t('technical.report_active_tasks') ?></div></div>
</div>

<div class="g-card">
    <div class="g-card-title"><?= t('technical.report_wells_card') ?></div>
    <div class="data-list">
        <div class="data-list-head cols-wells">
            <span>#</span><span><?= t('technical.col_location') ?></span><span><?= t('technical.col_status_hdr') ?></span><span><?= t('technical.col_production') ?></span><span><?= t('technical.col_condition') ?></span><span><?= t('technical.col_pressure') ?></span><span><?= t('technical.col_reservoir') ?></span>
        </div>
        <?php foreach ($wells as $w):
            $cond    = (float)$w['technical_condition'];
            $condCol = $cond >= 70 ? 'c-good' : ($cond >= 40 ? 'c-warn' : 'c-bad');
            $resPct  = (float)($w['reservoir_max'] ?? 800000) > 0 ? round((float)$w['reservoir_remaining'] / (float)$w['reservoir_max'] * 100, 1) : 0;
            $sLbl    = match($w['status']) { 'active' => t('technical.ws_active'), 'broken', 'paused_cash' => t('technical.ws_broken'), default => ucfirst($w['status']) };
            $sCls    = match($w['status']) { 'active' => 'b-active', 'broken', 'paused_cash' => 'b-broken', default => 'b-paused' };
        ?>
        <div class="data-list-row cols-wells">
            <span class="dlc sm">#<?= $w['id'] ?></span>
            <span class="dlc"><?= htmlspecialchars($w['location_name'] ?? t('technical.well_default_name')) ?></span>
            <span class="dlc"><span class="badge <?= $sCls ?>"><?= $sLbl ?></span></span>
            <span class="dlc c-gold fw7"><?= number_format($w['base_production_per_hour'], 1) ?> bbl/h</span>
            <span class="dlc <?= $condCol ?> fw7"><?= round($cond, 1) ?>%</span>
            <span class="dlc"><?= number_format((float)($w['pressure'] ?? 1.0), 3) ?></span>
            <span class="dlc <?= $resPct < 20 ? 'c-bad' : 'c-muted2' ?>"><?= $resPct ?>%</span>
        </div>
        <?php endforeach ?>
    </div>
</div>

<div class="g-card">
    <div class="g-card-title"><?= t('technical.report_team_card') ?></div>
    <div class="data-list">
        <div class="data-list-head cols-staff">
            <span><?= t('technical.col_name') ?></span><span><?= t('technical.col_spec') ?></span><span><?= t('technical.skill_label') ?></span><span><?= t('technical.col_status_hdr') ?></span><span><?= t('technical.col_salary') ?></span>
        </div>
        <?php foreach ($staff as $s): ?>
        <div class="data-list-row cols-staff">
            <span class="dlc fw6"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></span>
            <span class="dlc"><?= htmlspecialchars($s['spec_name']) ?></span>
            <span class="dlc c-gold fw7"><?= $s['skill_level'] ?>/10</span>
            <span class="dlc"><span class="badge <?= $s['status'] === 'active' ? 'b-active' : 'b-busy' ?>"><?= $s['status'] === 'active' ? t('technical.status_available') : t('technical.status_busy') ?></span></span>
            <span class="dlc sm"><?= number_format($s['salary'], 0, '.', ' ') ?> <?= t('common.currency') ?></span>
        </div>
        <?php endforeach ?>
    </div>
</div>
