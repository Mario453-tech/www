<?php extract($viewData, EXTR_SKIP); ?>

<?php if (!$player): ?>
<p class="alert alert-error"><?= t('admin.player.err_not_found') ?></p>
<?php return; endif ?>

<?php
$wellsPerPage = 5;
$wellPage     = max(1, (int)($_GET['wp'] ?? 1));
$wellTotal    = count($wells);
$wellPages    = max(1, (int)ceil($wellTotal / $wellsPerPage));
$wellPage     = min($wellPage, $wellPages);
$wellsPaged   = array_slice($wells, ($wellPage - 1) * $wellsPerPage, $wellsPerPage);
$activeTab    = $_GET['tab'] ?? 'info';
?>

<!-- Nagwek -->
<div class="pd-header">
    <a href="/admin/players.php" class="btn btn-secondary btn-sm"><?= t('admin.player.back') ?></a>
    <h1 class="pd-title"><?= t('admin.player.title', ['id' => (int)$player['id'], 'name' => htmlspecialchars($player['username'] ?? $player['email'])]) ?></h1>
</div>

<?php if (!empty($msg)):   ?><div class="alert alert-success"><?= htmlspecialchars($msg)   ?></div><?php endif ?>
<?php if (!empty($error)): ?><div class="alert alert-error"  ><?= htmlspecialchars($error) ?></div><?php endif ?>

<!-- Karty statystyk -->
<div class="pd-stats-row">
    <div class="pd-stat-card">
        <div class="pd-stat-label"><?= t('admin.player.stat_status') ?></div>
        <div class="pd-stat-value"><span class="badge <?= playerBadgeClass($player['status']) ?>"><?= t('player.status.' . $player['status']) ?></span></div>
    </div>
    <div class="pd-stat-card">
        <div class="pd-stat-label"><?= t('admin.player.stat_cash') ?></div>
        <div class="pd-stat-value orange"><?= number_format((float)($player['cash'] ?? 0), 0, ',', ' ') ?> <?= t('common.pln') ?></div>
    </div>
    <div class="pd-stat-card">
        <div class="pd-stat-label"><?= t('admin.player.stat_storage') ?></div>
        <div class="pd-stat-value"><?= (int)($player['used'] ?? 0) ?> / <?= (int)($player['capacity'] ?? 0) ?> <?= t('common.bbl') ?></div>
    </div>
    <div class="pd-stat-card">
        <div class="pd-stat-label"><?= t('admin.player.stat_wells') ?></div>
        <div class="pd-stat-value"><?= count($wells) ?></div>
    </div>
    <div class="pd-stat-card">
        <div class="pd-stat-label"><?= t('admin.player.stat_loans') ?></div>
        <div class="pd-stat-value"><?= $activeLoansCount ?> / <?= count($loans) ?></div>
    </div>
    <div class="pd-stat-card">
        <div class="pd-stat-label"><?= t('admin.player.stat_trust') ?></div>
        <div class="pd-stat-value"><?= (int)($trustData['score'] ?? 50) ?>/100</div>
    </div>
    <div class="pd-stat-card">
        <div class="pd-stat-label"><?= t('admin.player.credit_score_label') ?></div>
        <div class="pd-stat-value <?= (int)($player['credit_score'] ?? 50) <= 80 ? 'cv-bad' : ((int)($player['credit_score'] ?? 50) <= 180 ? 'cv-warn' : 'cv-good') ?>"><?= (int)($player['credit_score'] ?? 50) ?></div>
    </div>
    <div class="pd-stat-card">
        <div class="pd-stat-label"><?= t('admin.player.stat_last_login') ?></div>
        <div class="pd-stat-value pd-stat-value--sm"><?= htmlspecialchars($player['last_login_at'] ?? '') ?></div>
    </div>
    <div class="pd-stat-card">
        <div class="pd-stat-label"><?= t('admin.player.stat_registered') ?></div>
        <div class="pd-stat-value pd-stat-value--sm"><?= htmlspecialchars($player['created_at'] ?? '') ?></div>
    </div>
</div>

<!-- Zakadki -->
<nav class="pd-tabs" aria-label="Sekcje gracza">
    <a href="?id=<?= $pid ?>&tab=info"    class="pd-tab <?= $activeTab==='info'    ? 'pd-tab--active' : '' ?>"><?= t('admin.player.tab_info') ?></a>
    <a href="?id=<?= $pid ?>&tab=wells"   class="pd-tab <?= $activeTab==='wells'   ? 'pd-tab--active' : '' ?>"><?= t('admin.player.tab_wells') ?> (<?= count($wells) ?>)</a>
    <a href="?id=<?= $pid ?>&tab=loans"   class="pd-tab <?= $activeTab==='loans'   ? 'pd-tab--active' : '' ?>"><?= t('admin.player.tab_loans') ?> (<?= $activeLoansCount ?>/<?= count($loans) ?>)</a>
    <a href="?id=<?= $pid ?>&tab=trust"   class="pd-tab <?= $activeTab==='trust'   ? 'pd-tab--active' : '' ?>"><?= t('admin.player.tab_trust') ?></a>
    <?php if (!empty($negotiations)): ?>
    <a href="?id=<?= $pid ?>&tab=neg"     class="pd-tab <?= $activeTab==='neg'     ? 'pd-tab--active' : '' ?>"><?= t('admin.player.tab_neg') ?> (<?= count($negotiations) ?>)</a>
    <?php endif ?>
    <?php if (!empty($bailiffRows)): ?>
    <a href="?id=<?= $pid ?>&tab=bailiff" class="pd-tab <?= $activeTab==='bailiff' ? 'pd-tab--active' : '' ?>"><?= t('admin.player.tab_bailiff') ?></a>
    <?php endif ?>
    <a href="?id=<?= $pid ?>&tab=bk"      class="pd-tab <?= $activeTab==='bk'      ? 'pd-tab--active' : '' ?>"><?= t('admin.player.tab_bk') ?></a>
</nav>

<!-- TAB: Dane gracza + Akcje admina -->
<?php if ($activeTab === 'info'): ?>
<div class="admin-row">
    <section class="panel">
        <p class="panel-title"><?= t('admin.player.tab_info') ?></p>
        <dl class="detail-list">
            <dt><?= t('admin.player.info_email') ?></dt>     <dd><?= htmlspecialchars($player['email'] ?? '') ?></dd>
            <dt><?= t('admin.player.info_username') ?></dt>  <dd><?= htmlspecialchars($player['username'] ?? '') ?></dd>
            <dt><?= t('admin.player.info_company') ?></dt>   <dd><?= htmlspecialchars($player['company_name'] ?? '') ?></dd>
            <dt><?= t('admin.player.info_cash') ?></dt>      <dd><?= number_format((float)($player['cash'] ?? 0), 2, ',', ' ') ?> <?= t('common.pln') ?></dd>
            <dt><?= t('admin.player.info_storage') ?></dt>   <dd><?= (int)($player['used'] ?? 0) ?> / <?= (int)($player['capacity'] ?? 0) ?> <?= t('common.bbl') ?></dd>
            <dt><?= t('admin.player.info_status') ?></dt>    <dd><?= t('player.status.' . ($player['status'] ?? 'active')) ?></dd>
            <dt><?= t('admin.player.info_trust') ?></dt>     <dd><?= (int)($trustData['score'] ?? 50) ?>/100</dd>
            <dt><?= t('admin.player.credit_score_label') ?></dt><dd><?= (int)($player['credit_score'] ?? 50) ?> <span class="muted font-sm"><?= t('admin.player.credit_score_hint') ?></span></dd>
            <dt><?= t('admin.player.info_last_login') ?></dt><dd><?= htmlspecialchars($player['last_login_at'] ?? '') ?></dd>
            <dt><?= t('admin.player.info_last_tick') ?></dt> <dd><?= htmlspecialchars($player['last_tick_at'] ?? '') ?></dd>
            <dt><?= t('admin.player.info_registered') ?></dt><dd><?= htmlspecialchars($player['created_at'] ?? '') ?></dd>
        </dl>
    </section>

    <section class="panel">
        <p class="panel-title"><?= t('admin.player.actions_title') ?></p>
        <form method="post" class="admin-action-form">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="set_cash">
            <label class="form-label"><?= t('admin.player.cash_set_label') ?></label>
            <div class="form-row">
                <input type="text" inputmode="numeric" pattern="[0-9 .,]*" name="set_amount"
                       value="<?= (int)($player['cash'] ?? 0) ?>"
                       class="input-full form-group--flex"
                       placeholder="np. 1000000">
                <button type="submit" class="btn btn-primary btn-sm"><?= t('admin.player.cash_save') ?></button>
            </div>
            <p class="form-hint mt-xs muted" style="font-size:.8em">
                Wpisz liczb cakowit PLN bez spacji i przecinkw, np.&nbsp;<code>6000000</code>&nbsp;=&nbsp;6&nbsp;mln&nbsp;PLN.<br>
                Enter a plain integer (PLN, no spaces/commas), e.g.&nbsp;<code>6000000</code>&nbsp;=&nbsp;6M&nbsp;PLN.
            </p>
        </form>

        <form method="post" class="admin-action-form">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="status">
            <label class="form-label"><?= t('admin.player.status_label') ?></label>
            <div class="form-row">
                <select name="new_status" class="input-full form-group--flex">
                    <?php foreach (['active','financial_risk','under_bailiff','bankrupt'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($player['status'] ?? '') === $s ? 'selected' : '' ?>><?= t('player.status.' . $s) ?></option>
                    <?php endforeach ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm"><?= t('admin.player.status_save') ?></button>
            </div>
        </form>
        <form method="post" class="admin-action-form">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="manual_tick">
            <button type="submit" class="btn btn-secondary btn-sm"
                    onclick="confirmSubmit(this, '<?= t('admin.player.manual_tick_confirm') ?>'); return false;"><?= t('admin.player.manual_tick') ?></button>
        </form>

        <!-- Newsletter subscription toggle -->
        <?php
        $nlSub = (int)($player['newsletter_subscribed'] ?? 1);
        ?>
        <form method="post" class="admin-action-form" style="margin-top:8px">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="toggle_newsletter">
            <label class="form-label" style="margin-bottom:4px">
                 Newsletter:
                <span style="color:<?= $nlSub ? '#4ec97a' : '#e05555' ?>;font-weight:700">
                    <?= $nlSub ? 'Subskrybent' : 'Wypisany' ?>
                </span>
            </label>
            <div>
                <button type="submit" class="btn btn-sm <?= $nlSub ? 'btn-danger' : 'btn-success' ?>">
                    <?= $nlSub ? ' Wypisz z newslettera' : ' Przywr subskrypcj' ?>
                </button>
            </div>
        </form>

        <div class="admin-danger-zone">
            <p class="panel-title"><?= t('admin.player.delete_title') ?></p>
            <p class="muted"><?= t('admin.player.delete_warning') ?></p>
            <form method="post" class="admin-action-form">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="delete_player">
                <label class="form-label"><?= t('admin.player.delete_confirm_label') ?></label>
                <div class="form-row">
                    <input type="text"
                           name="delete_confirm"
                           class="input-full form-group--flex"
                           autocomplete="off"
                           placeholder="<?= htmlspecialchars(t('admin.player.delete_confirm_placeholder')) ?>">
                    <button type="submit"
                            class="btn btn-danger btn-sm"
                            onclick="confirmSubmit(this, '<?= t('admin.player.delete_modal_confirm') ?>', {type:'danger'}); return false;">
                        <?= t('admin.player.delete_btn') ?>
                    </button>
                </div>
            </form>
        </div>
    </section>
</div>
<?php endif ?>

<!-- TAB: Odwierty -->
<?php if ($activeTab === 'wells'): ?>
<section class="panel">
    <p class="panel-title"><?= t('admin.player.wells_title') ?> (<?= $wellTotal ?>)</p>
    <?php if (empty($wells)): ?>
    <p class="muted"><?= t('admin.player.wells_empty') ?></p>
    <?php else: ?>
    <div class="data-list data-list--wells">
        <div class="list-header list-header--wells">
            <span><?= t('admin.player.well_col_id') ?></span>
            <span><?= t('admin.player.well_col_name') ?></span>
            <span><?= t('admin.player.well_col_status') ?></span>
            <span><?= t('admin.player.well_col_prod') ?></span>
            <span><?= t('admin.player.well_col_cond') ?></span>
            <span><?= t('admin.player.well_col_loc') ?></span>
        </div>
        <?php foreach ($wellsPaged as $w):
            $wSt  = $w['status'] ?? 'active';
            $wBadge = match($wSt) {
                'active'                            => 'badge-active',
                'broken','blowout','contaminated','seized' => 'badge-danger',
                default                             => 'badge-inactive',
            };
        ?>
        <article class="list-row list-row--well">
            <span class="muted well-col-id">#<?= (int)$w['id'] ?></span>
            <span class="well-col-name"><?= htmlspecialchars($w['display_name'] ?? '') ?></span>
            <span class="well-col-status"><span class="badge <?= $wBadge ?>"><?= t('well.status.' . $wSt) ?></span></span>
            <span class="well-col-prod"><?= number_format((float)($w['base_production_per_hour'] ?? 0), 1) ?> <?= t('common.bbl') ?></span>
            <span class="well-col-cond"><?= number_format((float)($w['technical_condition'] ?? 100), 1) ?>%</span>
            <span class="well-col-loc muted"><?= htmlspecialchars($w['region_name'] ?? '') ?></span>
        </article>
        <?php endforeach ?>
    </div>
    <?php if ($wellPages > 1): ?>
    <nav class="pagination" aria-label="Strony odwiertw">
        <?php if ($wellPage > 1): ?>
        <a href="?id=<?= $pid ?>&tab=wells&wp=<?= $wellPage-1 ?>" class="btn btn-secondary btn-sm"><?= t('admin.player.prev') ?></a>
        <?php endif ?>
        <?php for ($wp = 1; $wp <= $wellPages; $wp++): ?>
        <a href="?id=<?= $pid ?>&tab=wells&wp=<?= $wp ?>"
           class="btn btn-sm <?= $wp === $wellPage ? 'btn-primary' : 'btn-secondary' ?>"><?= $wp ?></a>
        <?php endfor ?>
        <?php if ($wellPage < $wellPages): ?>
        <a href="?id=<?= $pid ?>&tab=wells&wp=<?= $wellPage+1 ?>" class="btn btn-secondary btn-sm"><?= t('admin.player.next') ?></a>
        <?php endif ?>
        <span class="muted pagination-info"><?= ($wellPage-1)*$wellsPerPage+1 ?><?= min($wellPage*$wellsPerPage,$wellTotal) ?> <?= t('common.of') ?> <?= $wellTotal ?></span>
    </nav>
    <?php endif ?>
    <?php endif ?>
</section>
<?php endif ?>

<!-- TAB: Kredyty -->
<?php if ($activeTab === 'loans'): ?>
<section class="panel">
    <p class="panel-title"><?= t('admin.player.loans_title') ?> (<?= count($loans) ?>)</p>
    <?php if (empty($loans)): ?>
    <p class="muted"><?= t('admin.player.loans_empty') ?></p>
    <?php else: ?>
    <div class="data-list data-list--loans">
        <div class="list-header list-header--loans">
            <span><?= t('admin.player.loan_col_id') ?></span><span><?= t('admin.player.loan_col_amount') ?></span><span><?= t('admin.player.loan_col_remaining') ?></span><span><?= t('admin.player.loan_col_status') ?></span><span><?= t('admin.player.loan_col_installment') ?></span><span><?= t('admin.player.loan_col_next') ?></span>
        </div>
        <?php foreach ($loans as $l): ?>
        <article class="list-row list-row--loan">
            <span class="muted">#<?= (int)$l['id'] ?></span>
            <span><?= number_format((float)$l['principal_amount'], 0, ',', ' ') ?> <?= t('common.pln') ?></span>
            <span><?= number_format((float)$l['remaining_amount'], 0, ',', ' ') ?> <?= t('common.pln') ?></span>
            <span><span class="badge badge-<?= $l['status'] === 'active' ? 'active' : ($l['status'] === 'late' ? 'danger' : 'inactive') ?>"><?= t('loan.status.' . ($l['status'] ?? 'active')) ?></span></span>
            <span><?= number_format((float)($l['installment_amount'] ?? 0), 0, ',', ' ') ?> <?= t('common.pln') ?></span>
            <span class="muted"><?= htmlspecialchars($l['next_payment_at'] ?? '') ?></span>
        </article>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>
<?php endif ?>

<!-- TAB: Trust score -->
<?php if ($activeTab === 'trust'): ?>
<section class="panel">
    <div class="panel-title-row">
        <p class="panel-title"><?= t('admin.player.trust_title') ?>  aktualny: <strong class="<?= (int)($trustData['score'] ?? 50) >= 60 ? 'cv-good' : ((int)($trustData['score'] ?? 50) >= 35 ? 'cv-warn' : 'cv-bad') ?>"><?= (int)($trustData['score'] ?? 50) ?>/100</strong></p>
        <form method="post" class="mb-0">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="clear_trust_log">
            <button type="submit" class="btn btn-danger btn-sm"
                    onclick="confirmSubmit(this,'<?= t('admin.player.trust_clear_confirm') ?>');return false">
                <?= t('admin.player.trust_clear') ?>
            </button>
        </form>
    </div>

    <!-- Edycja credit_score (players.credit_score) -->
    <div class="admin-row admin-row--flex mb-4">
        <form method="post" class="admin-action-form">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="trust_adjust">
            <label class="form-label">Bank Trust Score (delta, 100)
                <span class="muted form-hint-meta"> aktualny: <?= (int)($trustData['score'] ?? 50) ?>/100</span>
            </label>
            <div class="form-row">
                <input type="number" name="trust_delta" value="0" min="-100" max="100" step="1" class="input-full form-group--flex">
                <input type="text" name="trust_reason" value="admin_test" placeholder="powd" class="input-full form-group--flex">
                <button type="submit" class="btn btn-primary btn-sm"><?= t('admin.player.trust_save_delta') ?></button>
            </div>
        </form>
        <form method="post" class="admin-action-form">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="set_credit_score">
            <label class="form-label">Credit Score (players.credit_score, 01000)
                <span class="muted form-hint-meta"> aktualny: <?= (int)($player['credit_score'] ?? 50) ?></span>
            </label>
            <div class="form-row">
                <input type="number" name="credit_score_value" value="<?= (int)($player['credit_score'] ?? 50) ?>" min="0" max="1000" step="1" class="input-full form-group--flex">
                <button type="submit" class="btn btn-primary btn-sm"><?= t('admin.player.credit_score_set') ?></button>
            </div>
            <p class="form-hint mt-xs">
                30 = krytyczny  80 = niski  180 = redni  280 = dobry  >280 = doskonay
                <br>Wpywa na RiskScoreEngine (max 15 pkt). Uywane przez bank przy wnioskach kredytowych.
            </p>
        </form>
    </div>
    <?php if (empty($trustLog)): ?>
    <p class="muted"><?= t('admin.player.trust_empty') ?></p>
    <?php else: ?>
    <div class="data-list data-list--trust" role="list">
        <div class="list-header list-header--trust">
            <span><?= t('admin.player.trust_col_date') ?></span>
            <span><?= t('admin.player.trust_col_event') ?></span>
            <span><?= t('admin.player.trust_col_change') ?></span>
            <span><?= t('admin.player.trust_col_note') ?></span>
        </div>
        <?php foreach ($trustLog as $t_row):
            $tDelta = (int)($t_row['delta'] ?? 0);
        ?>
        <article class="list-row list-row--trust">
            <span class="muted"><?= htmlspecialchars($t_row['created_at'] ?? '') ?></span>
            <span><?= htmlspecialchars($t_row['event'] ?? $t_row['event_type'] ?? '') ?></span>
            <span class="<?= $tDelta >= 0 ? 'cv-good' : 'cv-bad' ?>"><?= $tDelta >= 0 ? '+' : '' ?><?= $tDelta ?></span>
            <span class="muted"><?= htmlspecialchars($t_row['note'] ?? '') ?></span>
        </article>
        <?php endforeach ?>
    </div>
    <?php if ($trustPages > 1): ?>
    <nav class="pagination" aria-label="Strony trust score">
        <?php if ($trustPage > 1): ?>
        <a href="?id=<?= $pid ?>&tab=trust&tp=<?= $trustPage - 1 ?>" class="btn btn-secondary btn-sm"><?= t('admin.player.prev') ?></a>
        <?php endif ?>
        <?php for ($tp = 1; $tp <= $trustPages; $tp++): ?>
        <a href="?id=<?= $pid ?>&tab=trust&tp=<?= $tp ?>" class="btn btn-sm <?= $tp === $trustPage ? 'btn-primary' : 'btn-secondary' ?>"><?= $tp ?></a>
        <?php endfor ?>
        <?php if ($trustPage < $trustPages): ?>
        <a href="?id=<?= $pid ?>&tab=trust&tp=<?= $trustPage + 1 ?>" class="btn btn-secondary btn-sm"><?= t('admin.player.next') ?></a>
        <?php endif ?>
        <span class="muted pagination-info"><?= ($trustPage-1)*$trustPerPage+1 ?><?= min($trustPage*$trustPerPage,$trustLogTotal) ?> <?= t('common.of') ?> <?= $trustLogTotal ?></span>
    </nav>
    <?php endif ?>
    <?php endif ?>
</section>
<?php endif ?>

<!-- TAB: Negocjacje -->
<?php if ($activeTab === 'neg' && !empty($negotiations)): ?>
<section class="panel">
    <p class="panel-title"><?= t('admin.player.neg_title') ?> (<?= count($negotiations) ?>)</p>
    <div class="data-list data-list--neg">
        <div class="list-header list-header--neg">
            <span><?= t('admin.player.neg_col_id') ?></span>
            <span><?= t('admin.player.neg_col_type') ?></span>
            <span><?= t('admin.player.neg_col_status') ?></span>
            <span><?= t('admin.player.neg_col_date') ?></span>
        </div>
        <?php foreach ($negotiations as $n):
            $nSt = $n['status'] ?? 'pending';
            $nBadge = match($nSt) {
                'approved','accepted' => 'badge-active',
                'rejected'            => 'badge-danger',
                default               => 'badge-inactive',
            };
        ?>
        <article class="list-row list-row--neg">
            <span class="muted">#<?= (int)$n['id'] ?></span>
            <span><?= t('neg.type.' . ($n['negotiation_type'] ?? 'unknown')) ?></span>
            <span><span class="badge <?= $nBadge ?>"><?= t('neg.status.' . $nSt) ?></span></span>
            <span class="muted"><?= htmlspecialchars($n['requested_at'] ?? '') ?></span>
        </article>
        <?php endforeach ?>
    </div>
</section>
<?php endif ?>

<!-- TAB: Komornik -->
<?php if ($activeTab === 'bailiff' && !empty($bailiffRows)): ?>
<section class="panel">
    <p class="panel-title"><?= t('admin.player.bailiff_title') ?></p>
    <div class="data-list data-list--bailiff">
        <div class="list-header list-header--bailiff">
            <span><?= t('admin.player.bailiff_col_id') ?></span>
            <span><?= t('admin.player.bailiff_col_stage') ?></span>
            <span><?= t('admin.player.bailiff_col_debt') ?></span>
            <span><?= t('admin.player.bailiff_col_status') ?></span>
            <span><?= t('admin.player.bailiff_col_next') ?></span>
        </div>
        <?php foreach ($bailiffRows as $b):
            $bSt = $b['status'] ?? 'active';
            $bBadge = match($bSt) {
                'active'    => 'badge-danger',
                'completed' => 'badge-active',
                'cancelled' => 'badge-inactive',
                default     => 'badge-inactive',
            };
        ?>
        <article class="list-row list-row--bailiff">
            <span class="muted">#<?= (int)$b['id'] ?></span>
            <span><?= t('admin.player.bailiff_stage', ['n' => (int)($b['stage'] ?? 0)]) ?></span>
            <span class="cv-bad"><?= number_format((float)($b['debt_amount'] ?? 0), 0, ',', ' ') ?> <?= t('common.pln') ?></span>
            <span><span class="badge <?= $bBadge ?>"><?= t('bailiff.status.' . $bSt) ?></span></span>
            <span class="muted"><?= htmlspecialchars($b['next_action_at'] ?? '') ?></span>
        </article>
        <?php endforeach ?>
    </div>
</section>
<?php endif ?>

<!-- TAB: Bankructwo -->
<?php if ($activeTab === 'bk'): ?>
<section class="panel">
    <div class="panel-title-row">
        <p class="panel-title"><?= t('admin.player.bk_title') ?></p>
        <form method="post" class="mb-0">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="clear_bk_events">
            <button type="submit" class="btn btn-danger btn-sm"
                    onclick="confirmSubmit(this,'<?= t('admin.player.bk_clear_confirm') ?>');return false">
                <?= t('admin.player.bk_clear') ?>
            </button>
        </form>
    </div>
    <div class="pd-stat-card-row pd-bk-status">
        <span class="muted"><?= t('admin.player.bk_status_label') ?></span>
        <span class="badge <?= $bankruptcyData['is_bankrupt'] ? 'badge-danger' : 'badge-active' ?>">
            <?= t('bankruptcy.status.' . ($bankruptcyData['bankruptcy_status'] ?? 'none')) ?>
        </span>
        <?php if ($bankruptcyData['bankruptcy_at']): ?>
        <span class="muted"><?= t('admin.player.bk_since') ?>: <?= htmlspecialchars($bankruptcyData['bankruptcy_at']) ?></span>
        <?php endif ?>
    </div>
    <?php if (empty($bankruptcyData['events'])): ?>
    <p class="muted"><?= t('admin.player.bk_empty') ?></p>
    <?php else: ?>
    <div class="data-list data-list--bk">
        <div class="list-header list-header--bk">
            <span><?= t('admin.player.bk_col_date') ?></span>
            <span><?= t('admin.player.bk_col_event') ?></span>
            <span><?= t('admin.player.bk_col_severity') ?></span>
            <span><?= t('admin.player.bk_col_note') ?></span>
        </div>
        <?php foreach ($bankruptcyData['events'] as $ev):
            $evType = $ev['event_type'] ?? 'unknown';
            $evSev  = $ev['severity']   ?? 'low';
            $evBadge = match($evSev) {
                'critical','high' => 'badge-danger',
                'medium'          => 'badge-paused',
                default           => 'badge-inactive',
            };
        ?>
        <article class="list-row list-row--bk">
            <span class="muted"><?= htmlspecialchars($ev['created_at'] ?? '') ?></span>
            <span><?= t('bk.event.' . $evType) ?></span>
            <span><span class="badge <?= $evBadge ?>"><?= t('bk.severity.' . $evSev) ?></span></span>
            <span class="muted"><?= htmlspecialchars($ev['message'] ?? $ev['resolution_note'] ?? '') ?></span>
        </article>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>
<?php endif ?>
