<?php extract($viewData, EXTR_SKIP); ?>

<main class="br-admin-main">

<?php foreach ($errors as $e): ?>
<div class="alert alert-err"><?= htmlspecialchars($e) ?></div>
<?php endforeach ?>
<?php foreach ($success as $s): ?>
<div class="alert alert-ok"><?= htmlspecialchars($s) ?></div>
<?php endforeach ?>

<h1 class="br-admin-title"><?= t('boardroom.admin_title') ?></h1>

<!--  SECTION: Roles  -->
<section class="panel br-section">
    <h2 class="br-section-title"><?= t('boardroom.admin_section_roles') ?></h2>

    <div class="br-roles-list">
    <?php foreach ($roles as $role): ?>
        <div class="br-role-card">
            <form method="post" enctype="multipart/form-data" class="br-role-form">
                <input type="hidden" name="action" value="update_role">
                <input type="hidden" name="role_id" value="<?= $role['id'] ?>">

                <div class="br-role-header">
                    <?php if (!empty($role['avatar_path'])): ?>
                        <img src="<?= htmlspecialchars($role['avatar_path']) ?>" alt="" class="br-role-avatar">
                    <?php else: ?>
                        <div class="br-role-icon">
                            <?= htmlspecialchars($role['icon'] ?: '') ?>
                        </div>
                    <?php endif ?>
                    <div>
                        <div class="br-role-code">
                            #<?= $role['id'] ?> · <?= htmlspecialchars($role['code']) ?>
                        </div>
                        <div class="br-role-members">
                            <?= t('boardroom.admin_label_members') ?> <?= $memberCounts[(int)$role['id']] ?? 0 ?>
                        </div>
                    </div>
                </div>

                <div class="br-role-fields">
                    <div>
                        <label class="form-label-sm"><?= t('boardroom.admin_label_name') ?></label>
                        <input type="text" name="role_name" value="<?= htmlspecialchars($role['name']) ?>" required>
                    </div>
                    <div>
                        <label class="form-label-sm"><?= t('boardroom.admin_label_desc') ?></label>
                        <input type="text" name="role_description" value="<?= htmlspecialchars($role['description'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label-sm"><?= t('boardroom.admin_label_icon') ?></label>
                        <input type="text" name="role_icon" value="<?= htmlspecialchars($role['icon'] ?? '') ?>" maxlength="10">
                    </div>
                    <div>
                        <label class="form-label-sm"><?= t('boardroom.admin_label_sort') ?></label>
                        <input type="number" name="role_sort_order" value="<?= (int)($role['sort_order'] ?? 0) ?>" min="0">
                    </div>
                </div>

                <div class="br-role-actions">
                    <label class="br-role-active-label">
                        <input type="checkbox" name="role_active" <?= ($role['is_active'] ?? 0) ? 'checked' : '' ?>>
                        <?= t('boardroom.admin_label_active') ?>
                    </label>
                    <div class="br-role-file-wrap">
                        <label class="form-label-sm"><?= t('boardroom.admin_label_avatar') ?></label>
                        <input type="file" name="role_avatar" accept="image/jpeg,image/png,image/webp" class="br-role-file-input">
                    </div>
                    <div class="br-role-spacer"></div>
                    <button type="submit" class="btn btn-primary btn-sm"><?= t('boardroom.admin_btn_save') ?></button>
                    <?php if (($memberCounts[(int)$role['id']] ?? 0) === 0): ?>
                    <button type="submit" name="action" value="delete_role"
                            onclick="return confirm('<?= t('boardroom.admin_confirm_delete', ['name' => $role['name']]) ?>')"
                            class="br-btn-delete">
                        <?= t('boardroom.admin_btn_delete') ?>
                    </button>
                    <?php endif ?>
                </div>
            </form>
        </div>
    <?php endforeach ?>
    </div>
</section>

<!--  SECTION: Add new role  -->
<section class="panel">
    <h2 class="br-section-title"><?= t('boardroom.admin_section_add_role') ?></h2>

    <form method="post">
        <input type="hidden" name="action" value="add_role">

        <div class="br-add-grid">
            <div>
                <label class="form-label-sm"><?= t('boardroom.admin_label_code') ?></label>
                <input type="text" name="role_code" pattern="[a-z0-9_]+" placeholder="<?= t('boardroom.admin_ph_code') ?>" required>
            </div>
            <div>
                <label class="form-label-sm"><?= t('boardroom.admin_label_name') ?></label>
                <input type="text" name="role_name" placeholder="<?= t('boardroom.admin_ph_name') ?>" required>
            </div>
            <div>
                <label class="form-label-sm"><?= t('boardroom.admin_label_desc') ?></label>
                <input type="text" name="role_description" placeholder="<?= t('boardroom.admin_ph_desc') ?>">
            </div>
            <div>
                <label class="form-label-sm"><?= t('boardroom.admin_label_icon') ?></label>
                <input type="text" name="role_icon" placeholder="" maxlength="10">
            </div>
            <div>
                <label class="form-label-sm"><?= t('boardroom.admin_label_sort') ?></label>
                <input type="number" name="role_sort_order" value="10" min="0">
            </div>
        </div>

        <button type="submit" class="btn btn-primary"><?= t('boardroom.admin_btn_add_role') ?></button>
    </form>
</section>

</main>
