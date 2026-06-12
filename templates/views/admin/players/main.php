<?php extract($viewData, EXTR_SKIP); ?>

<h1><?= t('admin.players.title') ?></h1>

<?php if (!empty($msg)): ?>
<div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif ?>
<?php if (!empty($error)): ?>
<div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif ?>

<div class="filters" role="navigation" aria-label="<?= t('admin.players.filter_nav') ?>">
    <span class="muted"><?= t('admin.players.filter_label') ?></span>
    <a href="/admin/players.php"
       class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-secondary' ?>">
        <?= t('admin.players.filter_all') ?>
    </a>
    <a href="/admin/players.php?filter=active"
       class="btn btn-sm <?= $filter === 'active' ? 'btn-primary' : 'btn-secondary' ?>">
        <?= t('player.status.active') ?>
    </a>
    <a href="/admin/players.php?filter=financial_risk"
       class="btn btn-sm <?= $filter === 'financial_risk' ? 'btn-primary' : 'btn-secondary' ?>">
        <?= t('player.status.financial_risk') ?>
    </a>
    <a href="/admin/players.php?filter=under_bailiff"
       class="btn btn-sm <?= $filter === 'under_bailiff' ? 'btn-primary' : 'btn-secondary' ?>">
        <?= t('player.status.under_bailiff') ?>
    </a>
    <a href="/admin/players.php?filter=bankrupt"
       class="btn btn-sm <?= $filter === 'bankrupt' ? 'btn-primary' : 'btn-secondary' ?>">
        <?= t('player.status.bankrupt') ?>
    </a>
    <span class="muted"><?= t('admin.players.found', ['count' => count($players)]) ?></span>
</div>

<form method="post"
      id="players-bulk-delete-form"
      class="players-grid players-grid--bulk"
      data-confirm="<?= htmlspecialchars(t('admin.players.confirm_bulk_delete')) ?>"
      data-no-selection="<?= htmlspecialchars(t('admin.players.err_no_selection')) ?>"
      aria-label="<?= t('admin.players.title') ?>">
    <?= CSRF::field() ?>
    <input type="hidden" name="action" value="bulk_delete_players">

    <div class="players-bulk-toolbar">
        <div class="players-bulk-actions">
            <button type="button" class="btn btn-sm btn-secondary" id="players-select-all">
                <?= t('admin.players.select_all') ?>
            </button>
            <button type="button" class="btn btn-sm btn-secondary" id="players-unselect-all">
                <?= t('admin.players.unselect_all') ?>
            </button>
        </div>
        <button type="submit" class="btn btn-sm btn-danger" id="players-bulk-delete-submit" disabled>
            <?= t('admin.players.bulk_delete') ?>
        </button>
    </div>

    <div class="list-header" role="row">
        <span class="col-select">
            <input type="checkbox"
                   id="players-check-all"
                   aria-label="<?= htmlspecialchars(t('admin.players.select_all')) ?>">
        </span>
        <span class="col-id"><?= t('admin.players.col_id') ?></span>
        <span><?= t('admin.players.col_email') ?></span>
        <span><?= t('admin.players.col_cash') ?></span>
        <span><?= t('admin.players.col_status') ?></span>
        <span class="col-storage"><?= t('admin.players.col_storage') ?></span>
        <span class="col-wells"><?= t('admin.players.col_wells') ?></span>
        <span class="col-lasttick"><?= t('admin.players.col_lasttick') ?></span>
        <span><?= t('admin.players.col_actions') ?></span>
    </div>

    <div class="data-list">
    <?php if (empty($players)): ?>
        <p class="empty-state"><?= t('admin.players.empty') ?></p>
    <?php else: ?>
        <?php foreach ($players as $p): ?>
        <article class="list-row" role="row">
            <span class="col-select">
                <input type="checkbox"
                       class="players-row-check"
                       name="player_ids[]"
                       value="<?= (int)$p['id'] ?>"
                       aria-label="<?= htmlspecialchars(t('admin.players.select_player', ['id' => (int)$p['id']])) ?>">
            </span>
            <span class="muted col-id"><?= (int)$p['id'] ?></span>
            <span>
                <p class="player-email"><?= htmlspecialchars($p['email']) ?></p>
            </span>
            <span class="player-cash"><?= number_format((float)$p['cash'], 0, ',', ' ') ?> <?= t('common.pln') ?></span>
            <span>
                <span class="badge <?= badgeClass($p['status']) ?>"><?= t('player.status.' . $p['status']) ?></span>
            </span>
            <span class="col-storage muted">
                <?= $p['storage_capacity'] ? (int)$p['storage_used'] . ' / ' . (int)$p['storage_capacity'] : '-' ?>
            </span>
            <span class="col-wells"><?= (int)$p['well_count'] ?></span>
            <span class="col-lasttick muted"><?= htmlspecialchars($p['last_login_at'] ?? '-') ?></span>
            <span>
                <a href="/admin/player_clean.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-secondary">
                    <?= t('admin.players.btn_details') ?>
                </a>
            </span>
        </article>
        <?php endforeach ?>
    <?php endif ?>
    </div>

</form>
