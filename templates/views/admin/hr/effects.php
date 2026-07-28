<section class="hr-section">
    <h2><?= t('admin.hr.section_effects') ?></h2>
    <?php if ($roleEffects === []): ?>
    <p class="hr-empty"><?= t('admin.hr.empty_effects') ?></p>
    <?php else: ?>
    <div class="hr-data-grid hr-grid-effects">
        <div class="hr-grid-head">
            <span><?= t('admin.hr.col_spec') ?></span><span><?= t('admin.hr.col_effect') ?></span>
            <span><?= t('admin.hr.col_scope') ?></span><span><?= t('admin.hr.col_value') ?></span>
            <span><?= t('admin.hr.col_active') ?></span>
        </div>
        <?php foreach ($roleEffects as $effect): ?>
        <article>
            <span><?= $esc($effect['specialization_code']) ?></span>
            <span><?= $esc($effect['description_pl'] ?: $effect['effect_key']) ?></span>
            <span><?= $esc($label('admin.hr.scope.' . $effect['target_scope'], tPlain('admin.hr.scope.global'))) ?></span>
            <span><?= number_format((float)$effect['effect_value'], 3) ?></span>
            <span><?= !empty($effect['is_active']) ? t('common.yes') : t('common.no') ?></span>
        </article>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>
