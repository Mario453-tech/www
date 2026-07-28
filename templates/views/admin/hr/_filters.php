<form method="get" class="hr-filter-bar">
    <input type="hidden" name="tab" value="<?= $esc($tab) ?>">
    <label>
        <span><?= t('admin.hr.filter_player') ?></span>
        <input type="number" name="player_id" min="1" value="<?= $filters['player_id'] > 0 ? (int)$filters['player_id'] : '' ?>">
    </label>
    <?php if (!empty($filterDepartment)): ?>
    <label>
        <span><?= t('admin.hr.col_department') ?></span>
        <select name="department">
            <option value=""><?= t('admin.hr.filter_all') ?></option>
            <?php foreach ($departments as $code => $label): ?>
            <option value="<?= $esc($code) ?>" <?= $filters['department'] === $code ? 'selected' : '' ?>><?= $esc($label) ?></option>
            <?php endforeach ?>
        </select>
    </label>
    <?php endif ?>
    <?php if (!empty($filterStatus)): ?>
    <label>
        <span><?= t('admin.hr.filter_status') ?></span>
        <select name="status">
            <option value=""><?= t('admin.hr.filter_all') ?></option>
            <?php foreach ($filterStatus as $code => $label): ?>
            <option value="<?= $esc($code) ?>" <?= $filters['status'] === $code ? 'selected' : '' ?>><?= $esc($label) ?></option>
            <?php endforeach ?>
        </select>
    </label>
    <?php endif ?>
    <button type="submit" class="btn btn-sm btn-primary"><?= t('admin.hr.btn_filter') ?></button>
    <a href="?tab=<?= $esc($tab) ?>" class="btn btn-sm"><?= t('admin.hr.btn_clear_filters') ?></a>
</form>
