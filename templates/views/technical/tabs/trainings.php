<?php
/**
 * Zakladka "Szkolenia" w dziale technicznym.
 * "Training" tab in the technical department.
 *
 * Zmienne z $viewData: $staff, $trainingPrograms, $trainingActive, $trainingHistory, $csrf.
 */
$techStaff = array_filter($staff, static fn($s) => ($s['status'] ?? '') !== 'fired');
?>

<div class="g-card">
    <div class="g-card-title"><?= t('training.heading_active') ?></div>

    <?php if (empty($trainingActive)): ?>
        <div class="empty-state"><?= t('training.empty_active') ?></div>
    <?php else: ?>
        <div class="trn-active-list">
        <?php foreach ($trainingActive as $tr): ?>
            <?php
            $startTs  = strtotime((string)$tr['started_at']);
            $endTs    = strtotime((string)$tr['finishes_at']);
            $nowTs    = time();
            $span     = max(1, $endTs - $startTs);
            $progress = max(0, min(100, (int)round(($nowTs - $startTs) / $span * 100)));
            $done     = $nowTs >= $endTs;
            ?>
            <div class="trn-active-item">
                <div class="trn-active-head">
                    <span class="trn-prog-name"><?= htmlspecialchars((string)$tr['name_pl']) ?></span>
                    <span class="trn-skill-tag"><?= t('training.skill.' . $tr['target_skill']) ?></span>
                </div>
                <div class="trn-bar"><div class="trn-bar-fill" style="width:<?= $progress ?>%"></div></div>
                <div class="trn-active-meta">
                    <span><?= t('training.label_finishes') ?>:
                        <?= htmlspecialchars((string)$tr['finishes_at']) ?></span>
                    <?php if ($done): ?>
                        <span class="trn-pending-exam"><?= t('training.exam_queued') ?></span>
                    <?php else: ?>
                        <span><?= $progress ?>%</span>
                    <?php endif ?>
                </div>
            </div>
        <?php endforeach ?>
        </div>
    <?php endif ?>
</div>

<div class="g-card">
    <div class="g-card-title"><?= t('training.heading_available') ?></div>

    <?php if (empty($trainingPrograms)): ?>
        <div class="empty-state"><?= t('training.empty_programs') ?></div>
    <?php elseif (empty($techStaff)): ?>
        <div class="empty-state"><?= t('technical.no_staff') ?></div>
    <?php else: ?>
        <div class="trn-program-grid">
        <?php foreach ($trainingPrograms as $prog): ?>
            <div class="trn-program-card">
                <div class="trn-prog-name"><?= htmlspecialchars((string)$prog['name_pl']) ?></div>
                <div class="trn-prog-skill"><?= t('training.skill.' . $prog['target_skill']) ?></div>
                <div class="trn-prog-stats">
                    <span><?= t('training.label_duration') ?>:
                        <strong><?= (int)$prog['duration_hours'] ?> h</strong></span>
                    <span><?= t('training.label_cost') ?>:
                        <strong class="c-gold"><?= number_format((int)$prog['cost'], 0, '.', ' ') ?>
                        <?= t('common.currency') ?></strong></span>
                    <span><?= t('training.label_pass_rate') ?>:
                        <strong><?= (int)$prog['base_pass_rate'] ?>%</strong></span>
                </div>
                <form method="post" class="trn-enroll-form"
                      data-confirm="<?= htmlspecialchars(t('training.btn_enroll') . ' — ' . (string)$prog['name_pl'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="_token" value="<?= $csrf ?>">
                    <input type="hidden" name="action" value="start_training">
                    <input type="hidden" name="program_id" value="<?= (int)$prog['id'] ?>">
                    <select name="staff_id" required class="trn-staff-select">
                        <option value=""><?= t('training.btn_pick_staff') ?></option>
                        <?php foreach ($techStaff as $s): ?>
                            <option value="<?= (int)$s['id'] ?>">
                                <?= htmlspecialchars(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? '')) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm trn-enroll-btn"><?= t('training.btn_enroll') ?></button>
                </form>
            </div>
        <?php endforeach ?>
        </div>
    <?php endif ?>
</div>

<div class="g-card">
    <div class="g-card-title"><?= t('training.heading_history') ?></div>

    <?php if (empty($trainingHistory)): ?>
        <div class="empty-state"><?= t('training.empty_history') ?></div>
    <?php else: ?>
        <div class="trn-history-list">
        <?php foreach ($trainingHistory as $h): ?>
            <?php
            $statusCls = match ($h['status']) {
                'passed'    => 'trn-h-pass',
                'failed'    => 'trn-h-fail',
                default     => 'trn-h-neutral',
            };
            ?>
            <div class="trn-history-item <?= $statusCls ?>">
                <div class="trn-h-main">
                    <span class="trn-prog-name"><?= htmlspecialchars((string)$h['name_pl']) ?></span>
                    <span class="trn-skill-tag"><?= t('training.skill.' . $h['target_skill']) ?></span>
                </div>
                <div class="trn-h-meta">
                    <span class="trn-h-status"><?= t('training.status.' . $h['status']) ?></span>
                    <?php if ($h['exam_score'] !== null): ?>
                        <span><?= t('training.exam_result', [
                            'score' => (int)$h['exam_score'],
                            'min'   => (int)$h['exam_pass_min'],
                        ]) ?></span>
                    <?php endif ?>
                    <?php if ($h['status'] === 'passed' && $h['skill_after'] !== null): ?>
                        <span class="trn-h-levelup">
                            <?= (int)$h['skill_before'] ?> &rarr; <?= (int)$h['skill_after'] ?></span>
                    <?php endif ?>
                </div>
            </div>
        <?php endforeach ?>
        </div>
    <?php endif ?>
</div>
