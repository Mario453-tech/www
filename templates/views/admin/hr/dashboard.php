<section class="hr-section">
    <h2><?= t('admin.hr.section_dashboard') ?></h2>
    <div class="hr-metric-grid">
        <?php foreach ([
            'employees' => 'admin.hr.metric_employees',
            'unhappy' => 'admin.hr.metric_conflicts',
            'raises' => 'admin.hr.metric_raises',
            'strikes' => 'admin.hr.metric_strikes',
            'assignments' => 'admin.hr.metric_assignments',
            'dialogues' => 'admin.hr.metric_dialogues',
        ] as $metric => $labelKey): ?>
        <article class="hr-metric">
            <strong><?= number_format((int)($dashboard[$metric] ?? 0)) ?></strong>
            <span><?= t($labelKey) ?></span>
        </article>
        <?php endforeach ?>
    </div>
</section>

<section class="hr-section">
    <div class="section-toolbar">
        <h2><?= t('admin.hr.section_candidates') ?></h2>
        <form method="post" data-confirm-submit
              data-confirm-message="<?= $esc(tPlain('admin.hr.confirm_cleanup')) ?>"
              data-confirm-label="<?= $esc(tPlain('common.delete')) ?>">
            <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
            <input type="hidden" name="cleanup_candidates" value="1">
            <button type="submit" class="btn btn-sm btn-danger"><?= t('admin.hr.btn_cleanup_candidates') ?></button>
        </form>
    </div>
    <?php if ($candidates === []): ?>
    <p class="hr-empty"><?= t('admin.hr.empty_candidates') ?></p>
    <?php else: ?>
    <div class="hr-data-grid hr-grid-candidates">
        <div class="hr-grid-head">
            <span><?= t('admin.hr.col_name') ?></span><span><?= t('admin.hr.col_player') ?></span>
            <span><?= t('admin.hr.col_role') ?></span><span><?= t('admin.hr.col_department') ?></span>
            <span><?= t('admin.hr.col_expires') ?></span>
        </div>
        <?php foreach ($candidates as $candidate): ?>
        <article>
            <span><?= $esc(trim(($candidate['first_name'] ?? '') . ' ' . ($candidate['last_name'] ?? ''))) ?></span>
            <span><?= $esc($candidate['player_email'] ?? $candidate['player_id'] ?? '-') ?></span>
            <span><?= $esc($candidate['role_name'] ?? $candidate['spec_name'] ?? '-') ?></span>
            <span><?= $esc($departments[$candidate['department'] ?? ''] ?? '-') ?></span>
            <span><?= $esc($candidate['expires_at'] ?? '-') ?></span>
        </article>
        <?php endforeach ?>
    </div>
    <?php endif ?>
    <?php
    $pagination = $candidatePagination;
    $paginationQueryKey = 'candidate_page';
    require __DIR__ . '/_pagination.php';
    ?>
</section>

<section class="hr-section">
    <h2><?= t('admin.hr.section_tests') ?></h2>
    <form method="post" class="hr-action-form" data-confirm-submit
          data-confirm-message="<?= $esc(tPlain('admin.hr.confirm_test_strike')) ?>"
          data-confirm-label="<?= $esc(tPlain('admin.hr.btn_force_test_strike')) ?>">
        <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
        <input type="hidden" name="force_test_strike" value="1">
        <label><span><?= t('admin.hr.field_test_strike_player') ?></span><input type="number" name="test_strike_player_id" min="1" required></label>
        <label>
            <span><?= t('admin.hr.field_test_strike_department') ?></span>
            <select name="test_strike_department">
                <?php foreach ($departments as $code => $label): ?>
                <option value="<?= $esc($code) ?>"><?= $esc($label) ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <label class="hr-check"><input type="checkbox" name="enable_test_negotiations_after_strike" value="1" checked> <span><?= t('admin.hr.field_test_negotiations') ?></span></label>
        <button type="submit" class="btn btn-danger"><?= t('admin.hr.btn_force_test_strike') ?></button>
    </form>
</section>
