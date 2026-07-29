<section class="hr-section">
    <h2><?= t('admin.hr.section_employee_events') ?> <span class="badge badge-inactive"><?= (int)$eventList['total'] ?></span></h2>
    <?php $filterDepartment = false; $filterStatus = []; require __DIR__ . '/_filters.php'; ?>
    <?php if ($eventList['rows'] === []): ?>
    <p class="hr-empty"><?= t('admin.hr.empty_logs') ?></p>
    <?php else: ?>
    <div class="hr-data-grid hr-grid-events">
        <div class="hr-grid-head">
            <span><?= t('admin.hr.col_date') ?></span><span><?= t('admin.hr.col_player') ?></span>
            <span><?= t('admin.hr.col_event') ?></span><span><?= t('admin.hr.col_source') ?></span>
        </div>
        <?php foreach ($eventList['rows'] as $event): ?>
        <article>
            <span><?= $esc($event['created_at']) ?></span>
            <span><?= $esc($event['player_email'] ?? $event['player_id']) ?></span>
            <span><?= $esc($label('admin.hr.event.' . $event['event_key'], tPlain('admin.hr.event.other'))) ?></span>
            <span><?= $esc(t('admin.hr.source.' . ($event['source_type'] ?: 'system'))) ?><?= $event['source_id'] ? ' #' . (int)$event['source_id'] : '' ?></span>
        </article>
        <?php endforeach ?>
    </div>
    <?php endif ?>
    <?php $pagination = $eventList; require __DIR__ . '/_pagination.php'; ?>
</section>

<section class="hr-section">
    <h2><?= t('admin.hr.section_legacy_history') ?> <span class="badge badge-inactive"><?= (int)$histTotal ?></span></h2>
    <?php if ($history === []): ?>
    <p class="hr-empty"><?= t('admin.hr.empty_history') ?></p>
    <?php else: ?>
    <div class="hr-data-grid hr-grid-history">
        <div class="hr-grid-head">
            <span><?= t('admin.hr.col_date') ?></span><span><?= t('admin.hr.col_player') ?></span>
            <span><?= t('admin.hr.col_employee') ?></span><span><?= t('admin.hr.col_reason') ?></span>
        </div>
        <?php foreach ($history as $entry): ?>
        <article>
            <span><?= $esc($entry['created_at'] ?? '-') ?></span>
            <span><?= $esc($entry['player_email'] ?? $entry['player_id'] ?? '-') ?></span>
            <span><?= $esc(trim(($entry['first_name'] ?? '') . ' ' . ($entry['last_name'] ?? ''))) ?></span>
            <span><?= $esc($entry['reason'] ?? $entry['action'] ?? '-') ?></span>
        </article>
        <?php endforeach ?>
    </div>
    <?php endif ?>
    <?php
    $pagination = ['page' => $histPage, 'pages' => $histPages];
    $paginationQueryKey = 'hpage';
    require __DIR__ . '/_pagination.php';
    ?>
</section>
