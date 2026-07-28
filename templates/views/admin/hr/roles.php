<section class="hr-section">
    <h2><?= t('admin.hr.section_staff_specs') ?></h2>
    <p class="muted"><?= t('admin.hr.staff_specs_desc') ?></p>
    <form method="post" class="hr-action-form">
        <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
        <input type="hidden" name="add_spec" value="1">
        <label><span><?= t('admin.hr.field_code') ?></span><input name="new_spec_code" pattern="[a-z0-9_]+" required></label>
        <label><span><?= t('admin.hr.field_name_pl') ?></span><input name="new_spec_name" required></label>
        <label>
            <span><?= t('admin.hr.field_role') ?></span>
            <select name="new_spec_role"><option value="operator"><?= t('admin.hr.role_operator') ?></option><option value="technician"><?= t('admin.hr.role_technician') ?></option></select>
        </label>
        <label>
            <span><?= t('admin.hr.col_rarity') ?></span>
            <select name="new_spec_rarity">
                <?php foreach ($validRarities as $rarity): ?>
                <option value="<?= $esc($rarity) ?>"><?= t('hr.rarity.' . $rarity) ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <button class="btn btn-primary" type="submit"><?= t('admin.hr.btn_add_spec') ?></button>
    </form>
    <?php if ($staffSpecs === []): ?>
    <p class="hr-empty"><?= t('admin.hr.empty_roles') ?></p>
    <?php else: ?>
    <div class="hr-details-list">
        <?php foreach ($staffSpecs as $role => $specs): ?>
        <?php foreach ($specs as $spec): ?>
        <details>
            <summary><strong><?= $esc($spec['name'] ?? $spec['code']) ?></strong> <span class="muted"><?= $esc($spec['code']) ?></span></summary>
            <form method="post" class="hr-config-grid">
                <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
                <input type="hidden" name="save_spec" value="1">
                <input type="hidden" name="code" value="<?= $esc($spec['code']) ?>">
                <label><span><?= t('admin.hr.field_name_pl') ?></span><input name="spec_name" value="<?= $esc($spec['name'] ?? '') ?>" required></label>
                <?php foreach ([
                    'prod_bonus' => 'admin.hr.field_prod_bonus',
                    'wear_reduction' => 'admin.hr.field_wear_reduction',
                    'incident_reduction' => 'admin.hr.field_incident_reduction',
                    'spiral_reduction' => 'admin.hr.field_spiral_reduction',
                    'repair_speed' => 'admin.hr.field_repair_speed',
                    'incident_return_reduction' => 'admin.hr.field_incident_return',
                    'catastrophe_reduction' => 'admin.hr.field_catastrophe_reduction',
                ] as $field => $labelKey): ?>
                <label><span><?= t($labelKey) ?></span><input type="number" name="<?= $esc($field) ?>" value="<?= $esc($spec[$field] ?? 0) ?>" min="0" max="1" step="0.001"></label>
                <?php endforeach ?>
                <button class="btn btn-primary" type="submit"><?= t('common.save') ?></button>
            </form>
            <form method="post" id="delete-spec-<?= $esc($spec['code']) ?>" class="hr-delete-form">
                <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
                <input type="hidden" name="delete_spec" value="1">
                <input type="hidden" name="code" value="<?= $esc($spec['code']) ?>">
                <button type="button" class="btn btn-danger" data-confirm-form="delete-spec-<?= $esc($spec['code']) ?>"
                        data-confirm-message="<?= $esc(tPlain('admin.hr.confirm_delete_spec')) ?>"
                        data-confirm-label="<?= $esc(tPlain('common.delete')) ?>"><?= t('common.delete') ?></button>
            </form>
        </details>
        <?php endforeach ?>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>

<section class="hr-section">
    <h2><?= t('admin.hr.section_hr_specs') ?></h2>
    <p class="muted"><?= t('admin.hr.hr_specs_desc') ?></p>
    <form method="post" class="hr-action-form">
        <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
        <input type="hidden" name="add_hr_spec" value="1">
        <label><span><?= t('admin.hr.field_code') ?></span><input name="new_hr_code" pattern="[a-z0-9_]+" required></label>
        <label><span><?= t('admin.hr.field_name_pl') ?></span><input name="new_hr_name" required></label>
        <label>
            <span><?= t('admin.hr.col_department') ?></span>
            <select name="new_hr_dept"><?php foreach ($departments as $code => $label): ?><option value="<?= $esc($code) ?>"><?= $esc($label) ?></option><?php endforeach ?></select>
        </label>
        <label><span><?= t('admin.hr.field_salary_min') ?></span><input type="number" name="new_hr_salary_min" value="8000" min="100" step="100"></label>
        <label><span><?= t('admin.hr.field_salary_max') ?></span><input type="number" name="new_hr_salary_max" value="15000" min="100" step="100"></label>
        <input type="hidden" name="new_hr_rarity" value="common">
        <button class="btn btn-primary" type="submit"><?= t('admin.hr.btn_add_spec') ?></button>
    </form>
    <?php if ($hrSpecs === []): ?>
    <p class="hr-empty"><?= t('admin.hr.empty_roles') ?></p>
    <?php else: ?>
    <div class="hr-details-list">
        <?php foreach ($hrSpecs as $department => $specs): ?>
        <?php foreach ($specs as $spec): ?>
        <details>
            <summary><strong><?= $esc($spec['name']) ?></strong> <span class="muted"><?= $esc($departments[$spec['department']] ?? $spec['department']) ?></span></summary>
            <form method="post" class="hr-action-form">
                <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
                <input type="hidden" name="save_hr_spec" value="1">
                <input type="hidden" name="hr_spec_id" value="<?= (int)$spec['id'] ?>">
                <label><span><?= t('admin.hr.field_name_pl') ?></span><input name="hr_spec_name" value="<?= $esc($spec['name']) ?>" required></label>
                <label><span><?= t('admin.hr.col_department') ?></span><select name="hr_spec_dept"><?php foreach ($departments as $code => $label): ?><option value="<?= $esc($code) ?>" <?= $spec['department'] === $code ? 'selected' : '' ?>><?= $esc($label) ?></option><?php endforeach ?></select></label>
                <label><span><?= t('admin.hr.col_rarity') ?></span><select name="hr_spec_rarity"><?php foreach ($validRarities as $rarity): ?><option value="<?= $esc($rarity) ?>" <?= $spec['rarity'] === $rarity ? 'selected' : '' ?>><?= t('hr.rarity.' . $rarity) ?></option><?php endforeach ?></select></label>
                <label><span><?= t('admin.hr.field_salary_min') ?></span><input type="number" name="hr_salary_min" value="<?= $esc($spec['base_salary_min']) ?>" min="100" step="100"></label>
                <label><span><?= t('admin.hr.field_salary_max') ?></span><input type="number" name="hr_salary_max" value="<?= $esc($spec['base_salary_max']) ?>" min="100" step="100"></label>
                <button class="btn btn-primary" type="submit"><?= t('common.save') ?></button>
            </form>
            <form method="post" id="delete-hr-spec-<?= (int)$spec['id'] ?>" class="hr-delete-form">
                <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
                <input type="hidden" name="delete_hr_spec" value="1">
                <input type="hidden" name="hr_spec_id" value="<?= (int)$spec['id'] ?>">
                <button type="button" class="btn btn-danger" data-confirm-form="delete-hr-spec-<?= (int)$spec['id'] ?>"
                        data-confirm-message="<?= $esc(tPlain('admin.hr.confirm_delete_spec')) ?>"
                        data-confirm-label="<?= $esc(tPlain('common.delete')) ?>"><?= t('common.delete') ?></button>
            </form>
        </details>
        <?php endforeach ?>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>
