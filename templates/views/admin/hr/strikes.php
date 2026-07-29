<section class="hr-section">
    <h2><?= t('admin.hr.section_strikes') ?> <span class="badge badge-inactive"><?= (int)$strikeList['total'] ?></span></h2>
    <?php $filterDepartment = true; $filterStatus = [
        'threat' => t('admin.hr.status.threat'), 'active' => t('admin.hr.status.active'),
        'negotiating' => t('admin.hr.status.negotiating'), 'resolved' => t('admin.hr.status.resolved'),
        'failed' => t('admin.hr.status.failed'),
    ]; require __DIR__ . '/_filters.php'; ?>
    <?php if ($strikeList['rows'] === []): ?>
    <p class="hr-empty"><?= t('admin.hr.empty_strikes') ?></p>
    <?php else: ?>
    <div class="hr-data-grid hr-grid-strikes">
        <div class="hr-grid-head">
            <span><?= t('admin.hr.col_player') ?></span><span><?= t('admin.hr.col_department') ?></span>
            <span><?= t('admin.hr.col_participants') ?></span><span><?= t('admin.hr.col_support') ?></span>
            <span><?= t('admin.hr.col_round') ?></span><span><?= t('admin.hr.col_deadline') ?></span>
            <span><?= t('admin.hr.col_cooldown') ?></span><span><?= t('admin.hr.filter_status') ?></span>
            <span><?= t('admin.hr.col_action') ?></span>
        </div>
        <?php foreach ($strikeList['rows'] as $strike): ?>
        <article>
            <span><?= $esc($strike['player_email'] ?? $strike['player_id']) ?></span>
            <span><?= $esc($departments[$strike['department_code']] ?? $strike['department_code']) ?></span>
            <span><?= (int)$strike['participant_count'] ?></span>
            <span><?= number_format((float)$strike['support_pct'], 1) ?>%</span>
            <span><?= $strike['current_round'] !== null
                ? (int)($strike['attempt_no'] ?? 1) . ': ' . (int)$strike['current_round'] . '/' . (int)$strike['max_rounds']
                : '-' ?></span>
            <span><?= $esc($strike['round_deadline_at'] ?? '-') ?></span>
            <span><?= $esc($strike['negotiation_cooldown_until'] ?? '-') ?></span>
            <span><?= $esc(t('admin.hr.status.' . $strike['status'])) ?></span>
            <span><a class="btn btn-sm" href="?tab=strikes&strike_id=<?= (int)$strike['id'] ?>"><?= t('admin.hr.btn_history') ?></a></span>
        </article>
        <?php endforeach ?>
    </div>
    <?php endif ?>
    <?php $pagination = $strikeList; require __DIR__ . '/_pagination.php'; ?>
</section>

<?php if ((int)($_GET['strike_id'] ?? 0) > 0): ?>
<section class="hr-section">
    <h2><?= t('admin.hr.section_offer_history') ?></h2>
    <?php if ($strikeRounds === []): ?>
    <p class="hr-empty"><?= t('admin.hr.empty_offer_history') ?></p>
    <?php else: ?>
    <div class="hr-data-grid hr-grid-rounds">
        <div class="hr-grid-head">
            <span><?= t('admin.hr.col_round') ?></span><span><?= t('admin.hr.col_raise') ?></span>
            <span><?= t('admin.hr.col_bonus') ?></span><span><?= t('admin.hr.col_counteroffer') ?></span>
            <span><?= t('admin.hr.col_result') ?></span><span><?= t('admin.hr.col_date') ?></span>
        </div>
        <?php foreach ($strikeRounds as $round): ?>
        <article>
            <span><?= (int)($round['attempt_no'] ?? 1) . ': ' . (int)$round['round_no'] ?></span>
            <span><?= number_format((float)$round['raise_pct'], 2) ?>%</span>
            <span><?= number_format((float)$round['bonus_per_member'], 2) ?></span>
            <span><?= $round['counter_raise_pct'] !== null ? number_format((float)$round['counter_raise_pct'], 2) . '% / ' . number_format((float)$round['counter_bonus_per_member'], 2) : '-' ?></span>
            <span><?= $esc(t('admin.hr.result.' . $round['result'])) ?></span>
            <span><?= $esc($round['created_at']) ?></span>
        </article>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>
<?php endif ?>
