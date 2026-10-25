<?php
/**
 * Zakladka "Szkolenia" w dziale technicznym.
 * "Training" tab in the technical department.
 *
 * Zmienne z $viewData: $staff, $trainingPrograms, $trainingActive, $trainingHistory, $csrf.
 */
$techStaff = array_filter($staff, static fn($s) => ($s['status'] ?? '') !== 'fired');
$trainingSkills = [];
foreach ($trainingPrograms as $program) {
    $trainingSkills[$program['target_skill']] = t('training.skill.' . $program['target_skill']);
}
asort($trainingSkills);
$trainingProgramName = static function (array $program): string {
    $locale = getLocale();
    $code = (string)($program['code'] ?? $program['program_code'] ?? '');
    $key = 'technical.training_program.' . $code;
    if ($locale === 'de' && $code !== '' && tPlain($key) !== $key) {
        return tPlain($key);
    }
    return (string)(($locale === 'pl' ? null : ($program['name_en'] ?? null))
        ?: ($program['name_pl'] ?? $program['program_name'] ?? ''));
};
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
                    <span class="trn-prog-name"><?= htmlspecialchars($trainingProgramName($tr), ENT_QUOTES, 'UTF-8') ?></span>
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
    <?php if (!empty($trainingPrograms)): ?>
    <label class="technical-training-filter"><?= t('technical.training_filter_skill') ?>
        <select class="form-input" data-tech-training-skill>
            <option value=""><?= t('technical.filter_all') ?></option>
            <?php foreach ($trainingSkills as $code => $label): ?><option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach ?>
        </select>
    </label>
    <div class="empty-state" data-tech-training-empty hidden><?= t('technical.training_no_results') ?></div>
    <?php endif ?>

    <?php if (empty($trainingPrograms)): ?>
        <div class="empty-state"><?= t('training.empty_programs') ?></div>
    <?php elseif (empty($techStaff)): ?>
        <div class="empty-state"><?= t('technical.no_staff') ?></div>
    <?php else: ?>
        <div class="trn-program-grid">
        <?php foreach ($trainingPrograms as $prog): ?>
            <?php $courseLevel = str_ends_with((string)$prog['code'], '_advanced') ? 'II' : (str_ends_with((string)$prog['code'], '_basic') ? 'I' : null); ?>
            <div class="trn-program-card" data-training-skill="<?= htmlspecialchars((string)$prog['target_skill'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="trn-prog-name"><?= htmlspecialchars($trainingProgramName($prog), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="trn-prog-skill"><?= t('training.skill.' . $prog['target_skill']) ?></div>
                <?php if ($courseLevel !== null): ?><div class="trn-prog-level"><?= t('technical.course_level') ?>: <?= $courseLevel ?></div><?php endif ?>
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
                      data-confirm="<?= htmlspecialchars(tPlain('training.btn_enroll') . ' — ' . $trainingProgramName($prog), ENT_QUOTES, 'UTF-8') ?>">
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
    <div class="g-card-title"><?= t('training.heading_certificates') ?></div>

    <?php if (empty($trainingCertificates)): ?>
        <div class="empty-state"><?= t('training.empty_certificates') ?></div>
    <?php else: ?>
        <div class="trn-cert-grid">
        <?php foreach ($trainingCertificates as $cert): ?>
            <div class="trn-cert">
                <div class="trn-cert-seal"><?= (int)$cert['level_after'] ?></div>
                <div class="trn-cert-body">
                    <div class="trn-prog-name"><?= htmlspecialchars($trainingProgramName($cert), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="trn-cert-meta">
                        <span class="trn-skill-tag"><?= t('training.skill.' . $cert['skill_code']) ?></span>
                        <span><?= t('training.label_score') ?>: <?= (int)$cert['score'] ?>/100</span>
                    </div>
                    <div class="trn-cert-date"><?= htmlspecialchars(substr((string)$cert['issued_at'], 0, 10)) ?></div>
                </div>
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
                    <span class="trn-prog-name"><?= htmlspecialchars($trainingProgramName($h), ENT_QUOTES, 'UTF-8') ?></span>
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
