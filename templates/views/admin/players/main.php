<?php /* Deploy sync after skipped CI gate. / Ponowny upload po pominietej bramce CI. */ extract($viewData, EXTR_SKIP); ?>

<h1><?= t('admin.players.title') ?></h1>

<?php if (!empty($msg)): ?>
<div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif ?>
<?php if (!empty($error)): ?>
<div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif ?>

<div class="filters players-status-filters" role="navigation" aria-label="<?= t('admin.players.filter_nav') ?>">
    <span class="muted"><?= t('admin.players.filter_label') ?></span>
    <a href="/admin/players.php?<?= htmlspecialchars(http_build_query(array_replace($listFilters, ['filter' => ''])), ENT_QUOTES, 'UTF-8') ?>"
       class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-secondary' ?>">
        <?= t('admin.players.filter_all') ?>
    </a>
    <a href="/admin/players.php?<?= htmlspecialchars(http_build_query(array_replace($listFilters, ['filter' => 'active'])), ENT_QUOTES, 'UTF-8') ?>"
       class="btn btn-sm <?= $filter === 'active' ? 'btn-primary' : 'btn-secondary' ?>">
        <?= t('player.status.active') ?>
    </a>
    <a href="/admin/players.php?<?= htmlspecialchars(http_build_query(array_replace($listFilters, ['filter' => 'financial_risk'])), ENT_QUOTES, 'UTF-8') ?>"
       class="btn btn-sm <?= $filter === 'financial_risk' ? 'btn-primary' : 'btn-secondary' ?>">
        <?= t('player.status.financial_risk') ?>
    </a>
    <a href="/admin/players.php?<?= htmlspecialchars(http_build_query(array_replace($listFilters, ['filter' => 'under_bailiff'])), ENT_QUOTES, 'UTF-8') ?>"
       class="btn btn-sm <?= $filter === 'under_bailiff' ? 'btn-primary' : 'btn-secondary' ?>">
        <?= t('player.status.under_bailiff') ?>
    </a>
    <a href="/admin/players.php?<?= htmlspecialchars(http_build_query(array_replace($listFilters, ['filter' => 'bankrupt'])), ENT_QUOTES, 'UTF-8') ?>"
       class="btn btn-sm <?= $filter === 'bankrupt' ? 'btn-primary' : 'btn-secondary' ?>">
        <?= t('player.status.bankrupt') ?>
    </a>
    <a href="/admin/players.php?<?= htmlspecialchars(http_build_query(array_replace($listFilters, $showRegistrationFilter ? ['registration_filter' => '0'] : ['registration_filter' => '1', 'login_from' => '', 'login_to' => ''])), ENT_QUOTES, 'UTF-8') ?>"
       class="btn btn-sm <?= $showRegistrationFilter || $registrationFilterActive ? 'btn-primary' : 'btn-secondary' ?>"
       aria-expanded="<?= $showRegistrationFilter ? 'true' : 'false' ?>">
        <?= t('admin.players.col_registered') ?>
    </a>
    <span class="muted"><?= t('admin.players.found', ['count' => count($players)]) ?></span>
</div>

<?php if ($showRegistrationFilter): ?>
<form method="get" action="/admin/players.php" class="players-registration-filters" aria-describedby="players-registration-help">
    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="login_from" value="<?= htmlspecialchars($listFilters['login_from'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="login_to" value="<?= htmlspecialchars($listFilters['login_to'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="sort" value="<?= htmlspecialchars($listFilters['sort'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="registration_filter" value="1">
    <label><?= t('admin.players.registered_from') ?>
        <input type="date" name="registered_from" value="<?= htmlspecialchars($listFilters['registered_from'], ENT_QUOTES, 'UTF-8') ?>">
    </label>
    <label><?= t('admin.players.registered_to') ?>
        <input type="date" name="registered_to" value="<?= htmlspecialchars($listFilters['registered_to'], ENT_QUOTES, 'UTF-8') ?>">
    </label>
    <button type="submit" class="btn btn-primary"><?= t('admin.players.apply') ?></button>
</form>
<p class="muted players-registration-help" id="players-registration-help"><?= t('admin.players.registration_help') ?></p>
<?php endif ?>

<form method="get" action="/admin/players.php" class="players-login-filters" aria-describedby="players-login-help">
    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="registered_from" value="<?= htmlspecialchars($listFilters['registered_from'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="registered_to" value="<?= htmlspecialchars($listFilters['registered_to'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="registration_filter" value="<?= $showRegistrationFilter ? '1' : '0' ?>">
    <label><?= t('admin.players.login_from') ?>
        <input type="date" name="login_from" value="<?= htmlspecialchars($listFilters['login_from'], ENT_QUOTES, 'UTF-8') ?>">
    </label>
    <label><?= t('admin.players.login_to') ?>
        <input type="date" name="login_to" value="<?= htmlspecialchars($listFilters['login_to'], ENT_QUOTES, 'UTF-8') ?>">
    </label>
    <label><?= t('admin.players.sort') ?>
        <select name="sort">
            <?php foreach (['login_desc', 'login_asc', 'id'] as $sortOption): ?>
            <option value="<?= $sortOption ?>" <?= $listFilters['sort'] === $sortOption ? 'selected' : '' ?>><?= t('admin.players.sort_' . $sortOption) ?></option>
            <?php endforeach ?>
        </select>
    </label>
    <button type="submit" class="btn btn-primary"><?= t('admin.players.apply') ?></button>
    <a href="/admin/players.php" class="btn btn-secondary"><?= t('admin.players.reset') ?></a>
</form>
<p class="muted players-login-help" id="players-login-help"><?= t('admin.players.login_help') ?></p>

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

    <div class="list-header">
        <label class="col-select">
            <input type="checkbox"
                   id="players-check-all"
                   aria-label="<?= htmlspecialchars(t('admin.players.select_all')) ?>">
        </label>
        <span class="col-id"><?= t('admin.players.col_id') ?></span>
        <span><?= t('admin.players.col_email') ?></span>
        <span><?= t('admin.players.col_cash') ?></span>
        <span><?= t('admin.players.col_status') ?></span>
        <span class="col-storage"><?= t('admin.players.col_storage') ?></span>
        <span class="col-wells"><?= t('admin.players.col_wells') ?></span>
        <span class="col-registered"><?= t('admin.players.col_registered') ?></span>
        <span class="col-lasttick"><?= t('admin.players.col_lasttick') ?></span>
        <span><?= t('admin.players.col_actions') ?></span>
    </div>

    <div class="data-list">
    <?php if (empty($players)): ?>
        <p class="empty-state"><?= t('admin.players.empty') ?></p>
    <?php else: ?>
        <?php foreach ($players as $p): ?>
        <article class="list-row">
            <label class="col-select">
                <input type="checkbox"
                       class="players-row-check"
                       name="player_ids[]"
                       value="<?= (int)$p['id'] ?>"
                       aria-label="<?= htmlspecialchars(t('admin.players.select_player', ['id' => (int)$p['id']])) ?>">
            </label>
            <span data-label="<?= t('admin.players.col_id') ?>" class="muted col-id"><?= (int)$p['id'] ?></span>
            <span data-label="<?= t('admin.players.col_email') ?>">
                <p class="player-email"><?= htmlspecialchars($p['email']) ?></p>
            </span>
            <span data-label="<?= t('admin.players.col_cash') ?>" class="player-cash"><?= number_format((float)$p['cash'], 0, ',', ' ') ?> <?= t('common.pln') ?></span>
            <span data-label="<?= t('admin.players.col_status') ?>">
                <span class="badge <?= badgeClass($p['status']) ?>"><?= t('player.status.' . $p['status']) ?></span>
            </span>
            <span data-label="<?= t('admin.players.col_storage') ?>" class="col-storage muted">
                <?= $p['storage_capacity'] ? (int)$p['storage_used'] . ' / ' . (int)$p['storage_capacity'] : '-' ?>
            </span>
            <span data-label="<?= t('admin.players.col_wells') ?>" class="col-wells"><?= (int)$p['well_count'] ?></span>
            <span data-label="<?= t('admin.players.col_registered') ?>" class="col-registered muted"><?= htmlspecialchars($p['created_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
            <span data-label="<?= t('admin.players.col_lasttick') ?>" class="col-lasttick muted"><?= htmlspecialchars($p['last_login_at'] ?? '-') ?></span>
            <span data-label="<?= t('admin.players.col_actions') ?>">
                <a href="/admin/player_clean.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-secondary">
                    <?= t('admin.players.btn_details') ?>
                </a>
            </span>
        </article>
        <?php endforeach ?>
    <?php endif ?>
    </div>

</form>
