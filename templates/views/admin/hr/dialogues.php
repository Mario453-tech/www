<section class="hr-section">
    <div class="section-toolbar">
        <div>
            <h2><?= t('admin.hr.section_dialogues') ?></h2>
            <p class="muted"><?= t('admin.hr.dialogues_desc') ?></p>
        </div>
        <form method="post" data-confirm-submit
              data-confirm-message="<?= $esc(tPlain('admin.hr.confirm_reset_dialogues')) ?>"
              data-confirm-label="<?= $esc(tPlain('admin.hr.btn_reset_dialogues')) ?>">
            <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
            <input type="hidden" name="action" value="reset_dialogues">
            <input type="hidden" name="return_tab" value="dialogues">
            <button type="submit" class="btn btn-danger"><?= t('admin.hr.btn_reset_dialogues') ?></button>
        </form>
    </div>
    <form method="get" class="hr-filter-bar">
        <input type="hidden" name="tab" value="dialogues">
        <label><span><?= t('admin.hr.col_context') ?></span><select name="context_key"><option value=""><?= t('admin.hr.filter_all') ?></option><?php foreach ($dialogueContexts as $context): ?><option value="<?= $esc($context) ?>" <?= ($_GET['context_key'] ?? '') === $context ? 'selected' : '' ?>><?= $esc(t('admin.hr.dialogue_context.' . $context)) ?></option><?php endforeach ?></select></label>
        <label><span><?= t('admin.hr.col_department') ?></span><select name="department"><option value=""><?= t('admin.hr.filter_all') ?></option><?php foreach ($departments as $code => $label): ?><option value="<?= $esc($code) ?>" <?= ($_GET['department'] ?? '') === $code ? 'selected' : '' ?>><?= $esc($label) ?></option><?php endforeach ?></select></label>
        <label><span><?= t('admin.hr.col_tone') ?></span><select name="tone"><option value=""><?= t('admin.hr.filter_all') ?></option><?php foreach ($dialogueTones as $tone): ?><option value="<?= $esc($tone) ?>" <?= ($_GET['tone'] ?? '') === $tone ? 'selected' : '' ?>><?= $esc(t('admin.hr.dialogue_tone.' . $tone)) ?></option><?php endforeach ?></select></label>
        <label><span><?= t('admin.hr.filter_status') ?></span><select name="active"><option value=""><?= t('admin.hr.filter_all') ?></option><option value="1" <?= ($_GET['active'] ?? '') === '1' ? 'selected' : '' ?>><?= t('admin.hr.status.active') ?></option><option value="0" <?= ($_GET['active'] ?? '') === '0' ? 'selected' : '' ?>><?= t('admin.hr.status.inactive') ?></option></select></label>
        <button class="btn btn-primary" type="submit"><?= t('admin.hr.btn_filter') ?></button>
    </form>
</section>

<section class="hr-section">
    <details class="hr-dialogue-editor">
        <summary><?= t('admin.hr.btn_add_dialogue') ?></summary>
        <?php $dialogue = []; require __DIR__ . '/dialogue_form.php'; ?>
    </details>
    <?php if ($dialogues === []): ?>
    <p class="hr-empty"><?= t('admin.hr.empty_dialogues') ?></p>
    <?php else: ?>
    <div class="hr-details-list">
        <?php foreach ($dialogues as $dialogue): ?>
        <details>
            <summary>
                <strong><?= $esc(t('admin.hr.dialogue_context.' . $dialogue['context_key'])) ?></strong>
                <span class="muted"><?= $esc($dialogue['department_code'] ? ($departments[$dialogue['department_code']] ?? $dialogue['department_code']) : t('admin.hr.dialogue_all_departments')) ?></span>
                <span class="badge <?= !empty($dialogue['is_active']) ? 'badge-active' : 'badge-inactive' ?>"><?= !empty($dialogue['is_active']) ? t('admin.hr.status.active') : t('admin.hr.status.inactive') ?></span>
            </summary>
            <?php require __DIR__ . '/dialogue_form.php'; ?>
            <div class="hr-inline-actions">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
                    <input type="hidden" name="action" value="duplicate_dialogue">
                    <input type="hidden" name="return_tab" value="dialogues">
                    <input type="hidden" name="dialogue_id" value="<?= (int)$dialogue['id'] ?>">
                    <button class="btn btn-sm" type="submit"><?= t('admin.hr.btn_duplicate') ?></button>
                </form>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
                    <input type="hidden" name="action" value="toggle_dialogue">
                    <input type="hidden" name="return_tab" value="dialogues">
                    <input type="hidden" name="dialogue_id" value="<?= (int)$dialogue['id'] ?>">
                    <input type="hidden" name="dialogue_active" value="<?= !empty($dialogue['is_active']) ? '0' : '1' ?>">
                    <button class="btn btn-sm" type="submit"><?= !empty($dialogue['is_active']) ? t('admin.hr.btn_disable') : t('admin.hr.btn_enable') ?></button>
                </form>
            </div>
        </details>
        <?php endforeach ?>
    </div>
    <?php endif ?>
    <?php $pagination = $dialoguePagination; require __DIR__ . '/_pagination.php'; ?>
</section>
