<section class="hr-section">
    <h2><?= t('admin.hr.section_raises') ?> <span class="badge badge-inactive"><?= (int)$raiseList['total'] ?></span></h2>
    <?php $filterDepartment = false; $filterStatus = [
        'open' => t('admin.hr.status.open'), 'postponed' => t('admin.hr.status.postponed'),
        'accepted' => t('admin.hr.status.accepted'), 'negotiated' => t('admin.hr.status.negotiated'),
        'rejected' => t('admin.hr.status.rejected'), 'expired' => t('admin.hr.status.expired'),
    ]; require __DIR__ . '/_filters.php'; ?>
    <?php if ($raiseList['rows'] === []): ?>
    <p class="hr-empty"><?= t('admin.hr.empty_raises') ?></p>
    <?php else: ?>
    <div class="hr-data-grid hr-grid-raises">
        <div class="hr-grid-head">
            <span><?= t('admin.hr.col_player') ?></span><span><?= t('admin.hr.col_department') ?></span>
            <span><?= t('admin.hr.col_salary_current') ?></span><span><?= t('admin.hr.col_salary_requested') ?></span>
            <span><?= t('admin.hr.col_reason') ?></span><span><?= t('admin.hr.col_deadline') ?></span>
            <span><?= t('admin.hr.filter_status') ?></span>
        </div>
        <?php foreach ($raiseList['rows'] as $raise): ?>
        <article>
            <span><?= $esc($raise['player_email'] ?? $raise['player_id']) ?></span>
            <span><?= $esc($departments[$raise['department_code']] ?? $raise['department_code'] ?? '-') ?></span>
            <span><?= number_format((float)$raise['current_salary'], 2) ?></span>
            <span><?= number_format((float)$raise['requested_salary'], 2) ?></span>
            <span><?= $esc($label('admin.hr.raise_reason.' . $raise['reason_code'], tPlain('admin.hr.raise_reason.other'))) ?></span>
            <span><?= $esc($raise['deadline_at'] ?? '-') ?></span>
            <span><?= $esc(t('admin.hr.status.' . $raise['status'])) ?></span>
        </article>
        <?php endforeach ?>
    </div>
    <?php endif ?>
    <?php $pagination = $raiseList; require __DIR__ . '/_pagination.php'; ?>
</section>
