<form method="post" class="hr-dialogue-form">
    <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
    <input type="hidden" name="action" value="save_dialogue">
    <input type="hidden" name="return_tab" value="dialogues">
    <input type="hidden" name="dialogue_id" value="<?= (int)($dialogue['id'] ?? 0) ?>">
    <label><span><?= t('admin.hr.col_context') ?></span><select name="dialogue[context_key]"><?php foreach ($dialogueContexts as $context): ?><option value="<?= $esc($context) ?>" <?= ($dialogue['context_key'] ?? '') === $context ? 'selected' : '' ?>><?= $esc(t('admin.hr.dialogue_context.' . $context)) ?></option><?php endforeach ?></select></label>
    <label><span><?= t('admin.hr.col_department') ?></span><select name="dialogue[department_code]"><option value=""><?= t('admin.hr.dialogue_all_departments') ?></option><?php foreach ($departments as $code => $label): ?><option value="<?= $esc($code) ?>" <?= ($dialogue['department_code'] ?? '') === $code ? 'selected' : '' ?>><?= $esc($label) ?></option><?php endforeach ?></select></label>
    <label><span><?= t('admin.hr.col_round') ?></span><input type="number" name="dialogue[round_no]" value="<?= $esc($dialogue['round_no'] ?? '') ?>" min="1" max="5"></label>
    <label><span><?= t('admin.hr.col_tone') ?></span><select name="dialogue[tone]"><?php foreach ($dialogueTones as $tone): ?><option value="<?= $esc($tone) ?>" <?= ($dialogue['tone'] ?? 'formal') === $tone ? 'selected' : '' ?>><?= $esc(t('admin.hr.dialogue_tone.' . $tone)) ?></option><?php endforeach ?></select></label>
    <label><span><?= t('admin.hr.col_weight') ?></span><input type="number" name="dialogue[weight]" value="<?= $esc($dialogue['weight'] ?? 1) ?>" min="0.001" max="1000" step="0.001" required></label>
    <label class="hr-dialogue-text"><span><?= t('admin.hr.col_text_pl') ?></span><textarea name="dialogue[text_pl]" rows="4" required><?= $esc($dialogue['text_pl'] ?? '') ?></textarea></label>
    <label class="hr-dialogue-text"><span><?= t('admin.hr.col_text_en') ?></span><textarea name="dialogue[text_en]" rows="4" required><?= $esc($dialogue['text_en'] ?? '') ?></textarea></label>
    <label class="hr-check"><input type="checkbox" name="dialogue[is_active]" value="1" <?= !array_key_exists('is_active', $dialogue) || !empty($dialogue['is_active']) ? 'checked' : '' ?>><span><?= t('admin.hr.field_enabled') ?></span></label>
    <button class="btn btn-primary" type="submit"><?= t('common.save') ?></button>
</form>
