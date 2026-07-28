<section class="hr-section">
    <h2><?= t('admin.hr.section_employees') ?> <span class="badge badge-inactive"><?= (int)$employeeList['total'] ?></span></h2>
    <?php $filterDepartment = true; $filterStatus = $relationStatuses; require __DIR__ . '/_filters.php'; ?>
    <?php if ($employeeList['rows'] === []): ?>
    <p class="hr-empty"><?= t('admin.hr.empty_employees') ?></p>
    <?php else: ?>
    <div class="hr-data-grid hr-grid-employees">
        <div class="hr-grid-head">
            <span><?= t('admin.hr.col_employee') ?></span><span><?= t('admin.hr.col_player') ?></span>
            <span><?= t('admin.hr.col_department') ?></span><span><?= t('admin.hr.col_relation') ?></span>
            <span><?= t('admin.hr.col_morale') ?></span><span><?= t('admin.hr.col_leave_risk') ?></span>
        </div>
        <?php foreach ($employeeList['rows'] as $employee): ?>
        <article>
            <span><?= $esc($employee['employee_name'] ?: t('admin.hr.employee_unknown')) ?></span>
            <span><a href="/admin/player.php?id=<?= (int)$employee['player_id'] ?>"><?= $esc($employee['player_email'] ?? $employee['player_id']) ?></a></span>
            <span><?= $esc($departments[$employee['department_code']] ?? $employee['department_code']) ?></span>
            <span><?= $esc($relationStatuses[$employee['relation_status']] ?? $employee['relation_status']) ?></span>
            <span><?= number_format((float)$employee['morale'], 1) ?>%</span>
            <span><?= number_format((float)$employee['leave_risk'], 1) ?>%</span>
        </article>
        <?php endforeach ?>
    </div>
    <?php endif ?>
    <?php $pagination = $employeeList; require __DIR__ . '/_pagination.php'; ?>
</section>
