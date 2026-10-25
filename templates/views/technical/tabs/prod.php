<div class="prod-grid">
    <div class="prod-stat"><div class="prod-val"><?= number_format($totalProd, 1) ?></div><div class="prod-unit">bbl/<?= t('technical.per_hour') ?></div></div>
    <div class="prod-stat"><div class="prod-val c-blue"><?= number_format($totalProd * 24, 0, '.', ' ') ?></div><div class="prod-unit">bbl/<?= t('technical.per_day') ?></div></div>
    <div class="prod-stat"><div class="prod-val c-green"><?= count($activeWells) ?> / <?= count($wells) ?></div><div class="prod-unit"><?= t('technical.active_wells_unit') ?></div></div>
    <div class="prod-stat"><div class="prod-val <?= $avgCond >= 70 ? 'c-green' : 'c-warn' ?>"><?= $avgCond ?>%</div><div class="prod-unit"><?= t('technical.avg_condition') ?></div></div>
</div>

<?php
$pausedStaffCount = count(array_filter($wells, fn($w) => $w['status'] === 'paused_staff'));
$pausedCashCount  = count(array_filter($wells, fn($w) => $w['status'] === 'paused_cash'));
$disasterCount    = count(array_filter($wells, fn($w) => in_array($w['status'], ['blowout','contaminated'])));
?>
<?php if ($pausedStaffCount > 0): ?>
<div class="msg-bar msg-warn"> <?= t('technical.prod_paused_staff', ['count' => $pausedStaffCount]) ?> <a href="?tab=candidates" class="link-gold"><?= t('technical.prod_recruit_link') ?></a></div>
<?php endif ?>
<?php if ($pausedCashCount > 0): ?>
<div class="msg-bar msg-error"> <?= t('technical.prod_paused_cash', ['count' => $pausedCashCount]) ?></div>
<?php endif ?>
<?php if ($disasterCount > 0): ?>
<div class="msg-bar msg-error"> <?= t('technical.prod_disaster', ['count' => $disasterCount]) ?> <a href="?tab=safety" class="link-gold"><?= t('technical.prod_safety_link') ?></a></div>
<?php endif ?>

<?php if (!empty($wells)): ?>
<div class="g-card">
    <div class="g-card-title"><?= t('technical.prod_per_well') ?></div>
    <div class="data-list">
        <div class="data-list-head cols-prod">
            <span>#</span><span><?= t('technical.col_location') ?> / <?= t('technical.col_status_hdr') ?></span><span><?= t('technical.col_production') ?></span><span><?= t('technical.col_pressure') ?></span><span><?= t('technical.col_condition') ?></span><span><?= t('technical.col_share') ?></span>
        </div>
        <?php foreach ($wells as $w):
            $isActive = $w['status'] === 'active';
            $cond    = (float)$w['technical_condition'];
            $p       = (float)($w['pressure'] ?? 1.0);
            $baseProd = (float)$w['_technical_production'];
            $share   = $totalProd > 0 ? round($baseProd / $totalProd * 100, 1) : 0;
            $statusKey = in_array($w['status'], ['active', 'paused_staff', 'paused_cash', 'paused_storage', 'contaminated', 'blowout', 'seized', 'broken', 'servicing'], true)
                ? 'technical.ws_' . $w['status'] : null;
        ?>
        <div class="data-list-row cols-prod">
            <span class="dlc sm" data-label="<?= htmlspecialchars(t('technical.col_well'), ENT_QUOTES, 'UTF-8') ?>">#<?= (int)$w['id'] ?></span>
            <span class="dlc" data-label="<?= htmlspecialchars(t('technical.col_location'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(!empty($w['well_name']) ? $w['well_name'] : ($w['location_name'] ?? tPlain('technical.well_default_name')), ENT_QUOTES, 'UTF-8') ?><small class="queue-spec"><?= htmlspecialchars((string)($statusKey ? t($statusKey) : $w['status']), ENT_QUOTES, 'UTF-8') ?></small></span>
            <span class="dlc c-gold fw7" data-label="<?= htmlspecialchars(t('technical.col_production'), ENT_QUOTES, 'UTF-8') ?>"><?= number_format($baseProd, 1) ?> bbl/h</span>
            <span class="dlc" data-label="<?= htmlspecialchars(t('technical.col_pressure'), ENT_QUOTES, 'UTF-8') ?>"><?= number_format($p, 3) ?></span>
            <span class="dlc <?= $cond >= 70 ? 'c-good' : ($cond >= 40 ? 'c-warn' : 'c-bad') ?> fw6" data-label="<?= htmlspecialchars(t('technical.col_condition'), ENT_QUOTES, 'UTF-8') ?>"><?= round($cond, 1) ?>%</span>
            <span class="dlc sm" data-label="<?= htmlspecialchars(t('technical.col_share'), ENT_QUOTES, 'UTF-8') ?>"><?= $share ?>%</span>
        </div>
        <?php endforeach ?>
    </div>
</div>
<?php endif ?>
