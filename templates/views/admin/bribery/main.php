<?php
/** @var array<string,string> $settings */
/** @var array<int,array<string,mixed>> $recentEvents */
/** @var string $msg */
/** @var string $err */
extract($viewData, EXTR_SKIP);

$levels = BriberyConfig::LEVELS;
?>

<h1><?= t('admin.bribery.title') ?></h1>
<p class="panel-hint"><?= t('admin.bribery.subtitle') ?></p>

<?php if ($msg): ?><p class="alert alert-success"><?= htmlspecialchars($msg) ?></p><?php endif ?>
<?php if ($err): ?><p class="alert alert-error"><?= htmlspecialchars($err) ?></p><?php endif ?>

<form method="post" action="/admin/bribery.php">
    <?= CSRF::field() ?>
    <input type="hidden" name="action" value="save_bribery_config">

    <!-- Wlacznik + parametry globalne -->
    <section class="panel mb-8">
        <p class="panel-title"><?= t('admin.bribery.section_global') ?></p>
        <p class="panel-hint"><?= t('admin.bribery.section_global_hint') ?></p>

        <div class="form-row form-row--gap">
            <label class="toggle-switch">
                <input type="checkbox" name="enabled" value="1" <?= (int)$settings['enabled'] === 1 ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
            <span><?= t('admin.bribery.field_enabled') ?></span>
        </div>

        <div class="form-row form-row--gap">
            <div class="form-field">
                <label><?= t('admin.bribery.field_base_cost_pct') ?></label>
                <input type="number" name="base_cost_pct" value="<?= (int)$settings['base_cost_pct'] ?>" min="0" max="100" step="1" class="input-sm input-num-70">
            </div>
            <div class="form-field">
                <label><?= t('admin.bribery.field_penalty_success') ?></label>
                <input type="number" name="credibility_penalty_success" value="<?= (int)$settings['credibility_penalty_success'] ?>" min="0" max="100" step="1" class="input-sm input-num-70">
            </div>
            <div class="form-field">
                <label><?= t('admin.bribery.field_penalty_caught') ?></label>
                <input type="number" name="credibility_penalty_caught" value="<?= (int)$settings['credibility_penalty_caught'] ?>" min="0" max="100" step="1" class="input-sm input-num-70">
            </div>
            <div class="form-field">
                <label><?= t('admin.bribery.field_cooldown_extra') ?></label>
                <input type="number" name="cooldown_extra_minutes" value="<?= (int)$settings['cooldown_extra_minutes'] ?>" min="0" step="30" class="input-sm input-num-110">
            </div>
        </div>
    </section>

    <!-- Cena i ryzyko per poziom reputacji -->
    <section class="panel mb-8">
        <p class="panel-title"><?= t('admin.bribery.section_levels') ?></p>
        <p class="panel-hint"><?= t('admin.bribery.section_levels_hint') ?></p>

        <div class="bribery-levels">
            <?php foreach ($levels as $level): ?>
            <div class="bribery-level-card">
                <p class="bribery-level-name"><?= t('admin.bribery.level_' . $level) ?></p>
                <div class="form-field">
                    <label><?= t('admin.bribery.field_catch_pct') ?></label>
                    <input type="number" name="catch_pct_<?= $level ?>" value="<?= (int)$settings['catch_pct_' . $level] ?>" min="0" max="100" step="1" class="input-sm input-num-70">
                </div>
                <div class="form-field">
                    <label><?= t('admin.bribery.field_price_mult') ?></label>
                    <input type="number" name="price_mult_<?= $level ?>" value="<?= htmlspecialchars($settings['price_mult_' . $level]) ?>" min="0" max="20" step="0.1" class="input-sm input-num-70">
                </div>
            </div>
            <?php endforeach ?>
        </div>
    </section>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= t('admin.bribery.btn_save') ?></button>
    </div>
</form>

<!-- Ostatnie zdarzenia lapowkowe -->
<section class="panel mt-8">
    <p class="panel-title"><?= t('admin.bribery.section_recent') ?></p>
    <?php if (empty($recentEvents)): ?>
    <p class="panel-hint"><?= t('admin.bribery.recent_empty') ?></p>
    <?php else: ?>
    <div class="bribery-events">
        <?php foreach ($recentEvents as $ev): ?>
        <div class="bribery-event bribery-event--<?= $ev['event_key'] === 'bribe_caught' ? 'caught' : 'paid' ?>">
            <span class="bribery-event-player">
                <?= htmlspecialchars((string)($ev['company_name'] ?? $ev['username'] ?? ('#' . $ev['player_id']))) ?>
            </span>
            <span class="bribery-event-kind">
                <?= $ev['event_key'] === 'bribe_caught' ? t('admin.bribery.event_caught') : t('admin.bribery.event_paid') ?>
            </span>
            <span class="bribery-event-delta"><?= (int)$ev['delta'] ?></span>
            <span class="bribery-event-time"><?= htmlspecialchars(substr((string)$ev['created_at'], 0, 16)) ?></span>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>
