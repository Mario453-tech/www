<?php
// Show only wells needing attention in the overview.
// Pokaz tylko odwierty wymagajace uwagi w raporcie.
$reportNeedsAction = array_values(array_filter($wells, static fn($well): bool => in_array((string)$well['status'], ['broken', 'paused_staff', 'paused_cash', 'paused_storage', 'blowout', 'contaminated'], true) || (float)$well['technical_condition'] < 60));
?>
<div class="prod-grid">
    <div class="prod-stat"><div class="prod-val c-gold"><?= count($staff) ?></div><div class="prod-unit"><?= t('technical.report_engineers') ?></div></div>
    <div class="prod-stat"><div class="prod-val c-blue"><?= count($activeTasks) ?></div><div class="prod-unit"><?= t('technical.report_active_tasks') ?></div></div>
    <div class="prod-stat"><div class="prod-val c-green"><?= count($activeWells) ?></div><div class="prod-unit"><?= t('technical.active_wells_unit') ?></div></div>
    <div class="prod-stat"><div class="prod-val"><?= t('technical.proc_level', ['level' => (int)$procStatus['level']]) ?> &middot; <?= number_format((float)$procStatus['integrity'], 1) ?>%</div><div class="prod-unit"><?= t('technical.proc_title') ?> / <?= t('technical.proc_integrity_label') ?></div></div>
</div>

<div class="g-card">
    <div class="g-card-title"><?= t('technical.report_wells_card') ?> (<?= count($reportNeedsAction) ?>)</div>
    <?php if (!$reportNeedsAction): ?>
    <div class="empty-state"><?= $wells ? t('technical.wells_ok_note') : t('technical.no_wells_warn') ?></div>
    <?php else: ?>
    <div class="data-list">
        <?php foreach (array_slice($reportNeedsAction, 0, 5) as $w):
            $statusKey = in_array($w['status'], ['active', 'paused_staff', 'paused_cash', 'paused_storage', 'contaminated', 'blowout', 'seized', 'broken', 'servicing'], true)
                ? 'technical.ws_' . $w['status'] : null;
        ?>
        <div class="data-list-row">
            <span class="dlc fw6"><?= htmlspecialchars((string)(!empty($w['well_name']) ? $w['well_name'] : ($w['location_name'] ?? tPlain('technical.well_default_name'))), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="dlc"><?= htmlspecialchars((string)($statusKey ? t($statusKey) : $w['status']), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="dlc"><?= t('technical.condition_label') ?>: <?= number_format((float)$w['technical_condition'], 1) ?>%</span>
        </div>
        <?php endforeach ?>
    </div>
    <a class="tech-gold-link" href="<?= htmlspecialchars(url('technical', ['tab' => 'wells']), ENT_QUOTES, 'UTF-8') ?>"><?= t('technical.tab_wells') ?></a>
    <?php endif ?>
</div>

<div class="g-card">
    <div class="g-card-title"><?= t('technical.tasks_in_progress', ['count' => count($activeTasks)]) ?></div>
    <?php if (!$activeTasks): ?>
    <div class="empty-state"><?= t('technical.no_tasks') ?></div>
    <?php else: ?>
    <div class="data-list">
        <?php foreach (array_slice($activeTasks, 0, 5) as $task): ?>
        <div class="data-list-row">
            <span class="dlc fw6"><?= htmlspecialchars((string)$task['title'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="dlc"><?= htmlspecialchars((string)$task['first_name'] . ' ' . (string)$task['last_name'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="dlc"><?= t('technical.task_end') ?>: <?= htmlspecialchars((string)($task['end_time'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php endforeach ?>
    </div>
    <a class="tech-gold-link" href="<?= htmlspecialchars(url('technical', ['tab' => 'tasks']), ENT_QUOTES, 'UTF-8') ?>"><?= t('technical.tab_tasks') ?></a>
    <?php endif ?>
</div>
