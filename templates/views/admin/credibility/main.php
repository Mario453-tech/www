<?php extract($viewData, EXTR_SKIP); ?>

<h1><?= t('admin.credibility.title') ?></h1>
<p class="panel-hint"><?= t('admin.credibility.subtitle') ?></p>

<?php if ($msg): ?><p class="alert alert-success"><?= htmlspecialchars($msg) ?></p><?php endif ?>
<?php if ($err): ?><p class="alert alert-error"><?= htmlspecialchars($err) ?></p><?php endif ?>

<?php
// Mapowanie poziomu na klase odznaki / Map level to badge css class
$levelBadge = static function (string $level): string {
    return 'cred-badge--' . preg_replace('/[^a-z]/', '', $level);
};
?>

<?php if ($viewPlayerId > 0 && $historyPlayer): ?>
<!-- ===== TRYB HISTORII / HISTORY MODE ===== -->

<p><a href="/admin/credibility.php" class="btn btn-secondary btn-sm"><?= t('admin.credibility.history_back') ?></a></p>

<section class="panel mb-8">
    <p class="panel-title">
        <?= t('admin.credibility.history_title', [
            'player' => htmlspecialchars((string)($historyPlayer['company_name'] ?? $historyPlayer['username'] ?? ('#' . $historyPlayer['id']))),
        ]) ?>
    </p>
    <div class="cred-current">
        <span class="cred-current-score"><?= (int)$historyPlayer['score'] ?> / 100</span>
        <span class="cred-badge <?= $levelBadge((string)$historyPlayer['level']) ?>">
            <?= t('admin.credibility.level_' . $historyPlayer['level']) ?>
        </span>
    </div>
</section>

<section class="panel">
    <?php if (empty($history)): ?>
    <p class="panel-hint"><?= t('admin.credibility.no_history') ?></p>
    <?php else: ?>
    <div class="cred-grid cred-grid--history">
        <div class="cred-grid__head">
            <span><?= t('admin.credibility.col_date') ?></span>
            <span><?= t('admin.credibility.col_event') ?></span>
            <span><?= t('admin.credibility.col_delta') ?></span>
            <span><?= t('admin.credibility.col_before') ?></span>
            <span><?= t('admin.credibility.col_after') ?></span>
            <span><?= t('admin.credibility.col_note') ?></span>
        </div>
        <?php foreach ($history as $h): ?>
            <?php $d = (int)$h['delta']; ?>
            <div class="cred-grid__row">
                <span class="cred-grid__cell" data-label="<?= t('admin.credibility.col_date') ?>"><small><?= htmlspecialchars(substr((string)$h['created_at'], 0, 16)) ?></small></span>
                <span class="cred-grid__cell" data-label="<?= t('admin.credibility.col_event') ?>"><code><?= htmlspecialchars((string)$h['event_key']) ?></code></span>
                <span class="cred-grid__cell <?= $d < 0 ? 'cred-delta--neg' : ($d > 0 ? 'cred-delta--pos' : '') ?>" data-label="<?= t('admin.credibility.col_delta') ?>"><?= $d > 0 ? '+' . $d : $d ?></span>
                <span class="cred-grid__cell" data-label="<?= t('admin.credibility.col_before') ?>"><?= (int)$h['score_before'] ?></span>
                <span class="cred-grid__cell" data-label="<?= t('admin.credibility.col_after') ?>"><strong><?= (int)$h['score_after'] ?></strong></span>
                <span class="cred-grid__cell" data-label="<?= t('admin.credibility.col_note') ?>"><small><?= htmlspecialchars((string)($h['note'] ?? '')) ?></small></span>
            </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>

<?php else: ?>
<!-- ===== TRYB LISTY / LIST MODE ===== -->

<section class="panel mb-8">
    <div class="cards">
        <div class="card"><p class="label"><?= t('admin.credibility.stat_players') ?></p><p class="value"><?= (int)$stats['players'] ?></p></div>
        <div class="card"><p class="label"><?= t('admin.credibility.stat_avg') ?></p><p class="value"><?= (int)$stats['avg'] ?></p></div>
        <div class="card"><p class="label"><?= t('admin.credibility.stat_critical') ?></p><p class="value red"><?= (int)$stats['critical'] ?></p></div>
        <div class="card"><p class="label"><?= t('admin.credibility.stat_high') ?></p><p class="value green"><?= (int)$stats['high'] ?></p></div>
    </div>
</section>

<section class="panel">
    <p class="panel-title"><?= t('admin.credibility.players_title') ?></p>

    <?php if (empty($players)): ?>
    <p class="panel-hint"><?= t('admin.credibility.no_players') ?></p>
    <?php else: ?>
    <div class="cred-grid cred-grid--players">
        <div class="cred-grid__head">
            <span><?= t('admin.credibility.col_player') ?></span>
            <span><?= t('admin.credibility.col_score') ?></span>
            <span><?= t('admin.credibility.col_level') ?></span>
            <span><?= t('admin.credibility.col_actions') ?></span>
        </div>
        <?php foreach ($players as $p): ?>
        <?php $pname = (string)($p['company_name'] ?? $p['username'] ?? ('ID ' . $p['id'])); ?>
        <div class="cred-grid__row">
            <span class="cred-grid__cell" data-label="<?= t('admin.credibility.col_player') ?>">
                <strong><?= htmlspecialchars($pname) ?></strong> <small>#<?= (int)$p['id'] ?></small>
            </span>
            <span class="cred-grid__cell" data-label="<?= t('admin.credibility.col_score') ?>"><strong><?= (int)$p['score'] ?></strong> / 100</span>
            <span class="cred-grid__cell" data-label="<?= t('admin.credibility.col_level') ?>">
                <span class="cred-badge <?= $levelBadge((string)$p['level']) ?>"><?= t('admin.credibility.level_' . $p['level']) ?></span>
            </span>
            <span class="cred-grid__cell cred-grid__cell--actions" data-label="<?= t('admin.credibility.col_actions') ?>">
                <a href="/admin/credibility.php?player=<?= (int)$p['id'] ?>" class="btn btn-xs btn-secondary"><?= t('admin.credibility.btn_history') ?></a>
                <button type="button" class="btn btn-xs btn-primary"
                        data-cred-adjust
                        data-player-id="<?= (int)$p['id'] ?>"
                        data-player-name="<?= htmlspecialchars($pname) ?>"><?= t('admin.credibility.btn_adjust') ?></button>
            </span>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>

<!-- Modal recznej korekty (sterowany przez admin_credibility.js) / Manual adjustment modal (driven by admin_credibility.js) -->
<div class="cred-modal-overlay" id="cred-adjust-overlay" hidden>
    <div class="cred-modal" role="dialog" aria-modal="true" aria-labelledby="cred-adjust-title">
        <h2 id="cred-adjust-title"><?= t('admin.credibility.adjust_title') ?></h2>
        <p class="cred-modal-intro"><?= t('admin.credibility.adjust_intro') ?></p>
        <p class="cred-modal-player" id="cred-adjust-player"></p>

        <form method="post" action="/admin/credibility.php">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="manual_adjust">
            <input type="hidden" name="player_id" id="cred-adjust-player-id" value="">

            <label class="cred-field">
                <span><?= t('admin.credibility.adjust_delta_label') ?></span>
                <input type="number" name="delta" step="1" required class="input-sm" style="width:120px">
            </label>

            <label class="cred-field">
                <span><?= t('admin.credibility.adjust_note_label') ?></span>
                <input type="text" name="note" maxlength="255" required
                       placeholder="<?= t('admin.credibility.adjust_note_ph') ?>" class="input-sm" style="width:100%">
            </label>

            <div class="cred-modal-actions">
                <button type="button" class="btn btn-secondary" data-cred-cancel><?= t('admin.credibility.adjust_cancel') ?></button>
                <button type="submit" class="btn btn-primary"><?= t('admin.credibility.adjust_save') ?></button>
            </div>
        </form>
    </div>
</div>

<?php endif ?>

<script src="<?= asset('/assets/js/admin_credibility.js') ?>"></script>
