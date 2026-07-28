<section class="hr-section">
    <h2><?= t('admin.hr.section_assignments') ?> <span class="badge badge-inactive"><?= (int)$assignmentList['total'] ?></span></h2>
    <?php $filterDepartment = false; $filterStatus = ['active' => t('admin.hr.status.active'), 'released' => t('admin.hr.status.released')]; require __DIR__ . '/_filters.php'; ?>
    <?php if ($assignmentList['rows'] === []): ?>
    <p class="hr-empty"><?= t('admin.hr.empty_assignments') ?></p>
    <?php else: ?>
    <div class="hr-data-grid hr-grid-assignments">
        <div class="hr-grid-head">
            <span><?= t('admin.hr.col_employee') ?></span><span><?= t('admin.hr.col_player') ?></span>
            <span><?= t('admin.hr.col_target') ?></span><span><?= t('admin.hr.col_allocation') ?></span>
            <span><?= t('admin.hr.filter_status') ?></span>
        </div>
        <?php foreach ($assignmentList['rows'] as $assignment): ?>
        <article>
            <span><?= $esc($assignment['employee_name'] ?: t('admin.hr.employee_unknown')) ?></span>
            <span><?= $esc($assignment['player_email'] ?? $assignment['player_id']) ?></span>
            <span><?= $esc($label('admin.hr.target.' . $assignment['target_type'], tPlain('admin.hr.col_target'))) ?> #<?= (int)$assignment['target_id'] ?></span>
            <span><?= number_format((float)$assignment['allocation_pct'], 1) ?>%</span>
            <span><?= $esc(t('admin.hr.status.' . $assignment['status'])) ?></span>
        </article>
        <?php endforeach ?>
    </div>
    <?php endif ?>
    <?php $pagination = $assignmentList; require __DIR__ . '/_pagination.php'; ?>
</section>
