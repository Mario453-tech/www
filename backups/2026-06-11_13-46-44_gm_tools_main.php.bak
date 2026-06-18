<?php extract($viewData, EXTR_SKIP); ?>

<h1><?= t('admin.gm.title') ?></h1>

<?php if ($msg): ?>
<p class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></p>
<?php endif ?>

<!--  Statystyki globalne  -->
<section aria-label="<?= t('admin.gm.econ_title') ?>">
    <h2><?= t('admin.gm.econ_title') ?></h2>
    <div class="cards">
        <div class="card">
            <p class="label"><?= t('admin.gm.econ_players') ?></p>
            <p class="value green"><?= (int)$econ['players_active'] ?> / <?= (int)$econ['players_total'] ?></p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.gm.econ_cash_total') ?></p>
            <p class="value orange">$<?= number_format((float)$econ['total_cash'], 0, '.', ' ') ?></p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.gm.econ_cash_avg') ?></p>
            <p class="value sm">$<?= number_format((float)$econ['avg_cash'], 0, '.', ' ') ?></p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.gm.econ_oil_price') ?></p>
            <p class="value orange">$<?= number_format((float)($market['current_price'] ?? 0), 2) ?></p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.gm.econ_wells') ?></p>
            <p class="value"><?= (int)$wellStats['active_wells'] ?> / <?= (int)$wellStats['total_wells'] ?></p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.gm.econ_prod') ?></p>
            <p class="value green"><?= number_format((float)$wellStats['total_prod'], 1) ?> <?= t('common.bbl') ?></p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.gm.econ_broken') ?></p>
            <p class="value <?= $wellStats['broken_wells'] > 0 ? 'red' : 'green' ?>"><?= (int)$wellStats['broken_wells'] ?></p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.gm.econ_condition') ?></p>
            <p class="value sm <?= $wellStats['avg_condition'] >= 70 ? '' : 'red' ?>"><?= round((float)$wellStats['avg_condition'], 1) ?>%</p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.gm.econ_loans') ?></p>
            <p class="value"><?= (int)($loanStats['active_loans'] ?? 0) ?></p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.gm.econ_debt') ?></p>
            <p class="value red">$<?= number_format((float)($loanStats['total_debt'] ?? 0), 0, '.', ' ') ?></p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.gm.econ_staff') ?></p>
            <p class="value sm"><?= (int)$staffStats['board_count'] ?> / <?= (int)$staffStats['tech_count'] ?></p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.gm.econ_cron') ?></p>
            <p class="value sm <?= $lastTickAgo > 5 ? 'red' : 'green' ?>">
                <?= $lastTickAgo < 999 ? $lastTickAgo . ' ' . t('admin.gm.econ_cron_ago') : t('admin.gm.econ_cron_none') ?>
            </p>
        </div>
    </div>
</section>

<div class="gm-grid">

<!--  Broadcast  -->
<section class="panel" aria-label="<?= t('admin.gm.broadcast_title') ?>">
    <p class="panel-title"> <?= t('admin.gm.broadcast_title') ?></p>
    <p class="muted text-sm"><?= t('admin.gm.broadcast_desc') ?></p>
    <form method="post">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="broadcast">
        <div class="gm-form-stack">
            <div>
                <label class="gm-label"><?= t('admin.gm.broadcast_label_title') ?></label>
                <input type="text" name="bc_title" placeholder="<?= t('admin.gm.broadcast_title_ph') ?>" class="gm-input" required>
            </div>
            <div>
                <label class="gm-label"><?= t('admin.gm.broadcast_label_msg') ?></label>
                <textarea name="bc_message" rows="3" placeholder="<?= t('admin.gm.broadcast_msg_ph') ?>" class="gm-input gm-textarea" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary"
                    onclick="confirmSubmit(this, '<?= t('admin.gm.broadcast_confirm') ?>'); return false;">
                 <?= t('admin.gm.broadcast_submit') ?>
            </button>
        </div>
    </form>
</section>

<!--  Globalna gotówka  -->
<section class="panel" aria-label="<?= t('admin.gm.bulk_title') ?>">
    <p class="panel-title"> <?= t('admin.gm.bulk_title') ?></p>
    <p class="muted text-sm"><?= t('admin.gm.bulk_desc') ?></p>
    <form method="post">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="bulk_cash">
        <div class="form-row">
            <input type="number" name="bulk_amount" value="1000000" step="100000" class="gm-input gm-input--short">
            <button type="submit" class="btn btn-danger"
                    onclick="confirmSubmit(this, '<?= t('admin.gm.bulk_confirm') ?>'); return false;">
                <?= t('admin.gm.bulk_submit') ?>
            </button>
        </div>
    </form>
</section>

<!--  Reset gracza  -->
<section class="panel panel-danger" aria-label="<?= t('admin.gm.reset_title') ?>">
    <p class="panel-title panel-title-danger"> <?= t('admin.gm.reset_title') ?></p>
    <p class="muted text-sm"><?= t('admin.gm.reset_desc') ?></p>
    <form method="post">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="reset_player">
        <div class="gm-form-stack">
            <div class="form-row">
                <div>
                    <label class="gm-label"><?= t('admin.gm.reset_player_label') ?></label>
                    <select name="reset_player_id" class="gm-input" required>
                        <option value="">— <?= t('admin.gm.select_player') ?> —</option>
                        <?php foreach ($players as $p): ?>
                        <option value="<?= $p['id'] ?>">#<?= $p['id'] ?> — <?= htmlspecialchars($p['email']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div>
                    <label class="gm-label"><?= t('admin.gm.reset_cash_label') ?></label>
                    <input type="number" name="start_cash" value="5000000" step="500000" class="gm-input gm-input--short">
                </div>
            </div>
            <button type="submit" class="btn btn-danger"
                    onclick="confirmAction('<?= t('admin.gm.reset_confirm') ?>', () => this.form.submit(), {type:'danger', title:'<?= t('admin.gm.reset_confirm_title') ?>', confirmLabel:'<?= t('admin.gm.reset_confirm_btn') ?>'}); return false;">
                 <?= t('admin.gm.reset_submit') ?>
            </button>
        </div>
    </form>
</section>

<!--  Klonowanie konta  -->
<section class="panel" aria-label="<?= t('admin.gm.clone_title') ?>">
    <p class="panel-title"> <?= t('admin.gm.clone_title') ?></p>
    <p class="muted text-sm"><?= t('admin.gm.clone_desc') ?></p>
    <form method="post">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="clone_test">
        <div class="gm-form-stack">
            <div class="form-row">
                <div>
                    <label class="gm-label"><?= t('admin.gm.clone_source_label') ?></label>
                    <select name="source_id" class="gm-input" required>
                        <option value="">— <?= t('admin.gm.select_player') ?> —</option>
                        <?php foreach ($players as $p): ?>
                        <option value="<?= $p['id'] ?>">#<?= $p['id'] ?> — <?= htmlspecialchars($p['email']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div>
                    <label class="gm-label"><?= t('admin.gm.clone_email_label') ?></label>
                    <input type="email" name="new_email" placeholder="<?= t('admin.gm.clone_email_ph') ?>" class="gm-input" required>
                </div>
                <div>
                    <label class="gm-label"><?= t('admin.gm.clone_pass_label') ?></label>
                    <input type="text" name="new_pass" placeholder="<?= t('admin.gm.clone_pass_ph') ?>" class="gm-input" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"
                    onclick="confirmSubmit(this, '<?= t('admin.gm.clone_confirm') ?>'); return false;">
                 <?= t('admin.gm.clone_submit') ?>
            </button>
        </div>
    </form>
</section>

<!--  Czyszczenie bazy  -->
<section class="panel" aria-label="<?= t('admin.gm.cleanup_title') ?>">
    <p class="panel-title"> <?= t('admin.gm.cleanup_title') ?></p>
    <p class="muted text-sm"><?= t('admin.gm.cleanup_desc') ?></p>
    <form method="post">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="cleanup">
        <button type="submit" class="btn btn-secondary"
                onclick="confirmSubmit(this, '<?= t('admin.gm.cleanup_confirm') ?>'); return false;">
             <?= t('admin.gm.cleanup_submit') ?>
        </button>
    </form>
</section>

</div><!-- .gm-grid -->
