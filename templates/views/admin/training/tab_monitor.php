<?php
/** Zakładka: monitoring szkoleń — zmienne z $viewData (extract w main.php) */
?>

<div class="section-header">
    <h2><?= t('admin.training.monitor.heading') ?></h2>
</div>

<div class="filter-bar">
    <form method="get" action="">
        <input type="hidden" name="tab" value="monitor">
        <select name="filter_status" onchange="this.form.submit()">
            <option value=""><?= t('admin.training.monitor.filter_status') ?>: <?= t('common.all') ?></option>
            <?php foreach (['in_progress','passed','failed','cancelled'] as $st): ?>
                <option value="<?= $st ?>" <?= $filterStatus === $st ? 'selected' : '' ?>>
                    <?= t('admin.training.status.' . $st) ?>
                </option>
            <?php endforeach ?>
        </select>
        <select name="filter_dept" onchange="this.form.submit()">
            <option value=""><?= t('admin.training.monitor.filter_dept') ?>: <?= t('common.all') ?></option>
            <option value="technical" <?= $filterDept === 'technical' ? 'selected' : '' ?>>
                <?= t('admin.training.dept.technical') ?>
            </option>
            <option value="board" <?= $filterDept === 'board' ? 'selected' : '' ?>>
                <?= t('admin.training.dept.board') ?>
            </option>
        </select>
    </form>
</div>

<?php if (empty($trainings)): ?>
    <p class="muted"><?= t('admin.training.monitor.empty') ?></p>
<?php else: ?>
<div class="table-responsive">
    <table class="admin-table">
        <thead>
            <tr>
                <th><?= t('admin.training.monitor.col_player') ?></th>
                <th><?= t('admin.training.monitor.col_staff') ?></th>
                <th><?= t('admin.training.monitor.col_dept') ?></th>
                <th><?= t('admin.training.monitor.col_program') ?></th>
                <th><?= t('admin.training.monitor.col_status') ?></th>
                <th><?= t('admin.training.monitor.col_started') ?></th>
                <th><?= t('admin.training.monitor.col_finishes') ?></th>
                <th><?= t('admin.training.monitor.col_result') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($trainings as $tr): ?>
            <?php
            $statusClass = match($tr['status']) {
                'passed'      => 'badge-success',
                'failed'      => 'badge-danger',
                'cancelled'   => 'badge-secondary',
                default       => 'badge-warning',
            };
            $isPending = $tr['status'] === 'in_progress';
            $isLate    = $isPending && strtotime($tr['finishes_at']) < time();
            ?>
            <tr class="<?= $isLate ? 'row-warning' : '' ?>">
                <td><?= htmlspecialchars($tr['player_name']) ?></td>
                <td>
                    <?= htmlspecialchars($tr['staff_name']) ?>
                    <br><small class="muted">#<?= (int)$tr['staff_id'] ?></small>
                </td>
                <td><?= t('admin.training.dept.' . $tr['staff_type']) ?></td>
                <td>
                    <?= htmlspecialchars($tr['program_name']) ?>
                    <br><small class="muted"><?= t('admin.training.skill.' . $tr['target_skill']) ?></small>
                </td>
                <td>
                    <span class="badge <?= $statusClass ?>">
                        <?= t('admin.training.status.' . $tr['status']) ?>
                        <?php if ($isLate): ?> (!)<?php endif ?>
                    </span>
                </td>
                <td><small><?= htmlspecialchars($tr['started_at']) ?></small></td>
                <td>
                    <small class="<?= $isLate ? 'text-danger' : '' ?>">
                        <?= htmlspecialchars($tr['finishes_at']) ?>
                    </small>
                </td>
                <td>
                    <?php if ($tr['exam_score'] !== null): ?>
                        <?= (int)$tr['exam_score'] ?>/100
                        (min: <?= (int)$tr['exam_pass_min'] ?>)
                    <?php elseif ($isPending): ?>
                        <span class="muted">—</span>
                    <?php else: ?>
                        <span class="muted">—</span>
                    <?php endif ?>
                    <?php if ((int)$tr['retry_count'] > 0): ?>
                        <br><small class="muted">Próba #<?= (int)$tr['retry_count'] + 1 ?></small>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</div>
<?php endif ?>
