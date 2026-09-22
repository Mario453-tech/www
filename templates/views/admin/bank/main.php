<script src="/assets/js/admin_dashboard_flash.js" defer></script>
<?php
/**
 * Admin view: panel bankowy — bank account management panel
 * Mobile-first layout, SVG icons (no emoji), proper translations.
 */
extract($viewData, EXTR_SKIP);

// Mapa statusow gracza / Player status label map
$statusLabels = [
    'active'         => t('player.status.active'),
    'financial_risk' => t('player.status.financial_risk'),
    'under_bailiff'  => t('player.status.under_bailiff'),
    'bankrupt'       => t('player.status.bankrupt'),
    'banned'         => t('player.status.banned'),
];
// Mapa typow transakcji / Transaction type label map
$typeLabels = [
    'player_transfer'   => t('bank.account.type.player_transfer'),
    'loan'              => t('bank.account.type.loan'),
    'loan_payment'      => t('bank.account.type.loan_payment'),
    'market_sale'       => t('bank.account.type.market_sale'),
    'tax'               => t('bank.account.type.tax'),
    'well_purchase'     => t('bank.account.type.well_purchase'),
    'hub_purchase'      => t('bank.account.type.hub_purchase'),
    'pipeline_purchase' => t('bank.account.type.pipeline_purchase'),
    'legal_fee'         => t('bank.account.type.legal_fee'),
    'admin_adjustment'  => t('bank.account.type.admin_adjustment'),
];
?>

<h1 class="abp-page-title">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" width="22" height="22" aria-hidden="true">
        <line x1="3" y1="22" x2="21" y2="22"/>
        <rect x="5" y="11" width="2" height="9"/>
        <rect x="11" y="11" width="2" height="9"/>
        <rect x="17" y="11" width="2" height="9"/>
        <path d="M12 2L2 9h20z"/>
    </svg>
    <?= t('admin.bank.title') ?>
</h1>

<?php if (!empty($flash)): ?>
<div class="abp-flash abp-flash--<?= htmlspecialchars($flash['type']) ?>">
    <?= htmlspecialchars($flash['msg']) ?>
</div>
<?php endif ?>


<!-- Mobile: select dropdown zamiast sidebaru / Mobile: select dropdown instead of sidebar -->
<div class="abp-mobile-picker">
    <label for="abp-player-select" class="abp-select-label"><?= t('admin.bank.choose') ?></label>
    <select id="abp-player-select" class="abp-select">
        <option value=""><?= t('admin.bank.choose_placeholder') ?></option>
        <?php foreach ($players as $p):
            $hasAcc = !empty($p['bank_account_number']);
        ?>
        <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === $selectedId ? 'selected' : '' ?>>
            #<?= (int)$p['id'] ?>
            <?= htmlspecialchars($p['email']) ?>
            <?= $hasAcc ? '(' . htmlspecialchars($p['bank_account_number']) . ')' : '(' . t('admin.bank.no_account') . ')' ?>
            — <?= number_format((float)$p['cash'], 2, ',', ' ') ?> PLN
        </option>
        <?php endforeach ?>
    </select>
</div>

<div class="abp-layout">

    <!-- Sidebar: lista graczy / Sidebar: player list -->
    <aside class="abp-sidebar">
        <div class="abp-sidebar-head"><?= t('admin.bank.players') ?> (<?= count($players) ?>)</div>
        <div class="abp-player-list">
            <?php foreach ($players as $p):
                $hasAcc   = !empty($p['bank_account_number']);
                $isActive = (int)$p['id'] === $selectedId;
            ?>
            <a href="/admin/bank.php?player_id=<?= (int)$p['id'] ?>"
               class="abp-player-row <?= $isActive ? 'abp-player-row--on' : '' ?>">
                <span class="abp-pr-id">#<?= (int)$p['id'] ?></span>
                <span class="abp-pr-body">
                    <span class="abp-pr-email"><?= htmlspecialchars($p['email']) ?></span>
                    <span class="abp-pr-sub">
                        <?php if ($hasAcc): ?>
                            <span class="abp-pr-acc"><?= htmlspecialchars($p['bank_account_number']) ?></span>
                        <?php else: ?>
                            <span class="abp-pr-noacc"><?= t('admin.bank.no_account') ?></span>
                        <?php endif ?>
                    </span>
                </span>
                <span class="abp-pr-bal"><?= number_format((float)$p['cash'], 2, ',', ' ') ?>&nbsp;PLN</span>
            </a>
            <?php endforeach ?>
            <?php if (empty($players)): ?>
            <div class="abp-empty"><?= t('admin.bank.empty_players') ?></div>
            <?php endif ?>
        </div>
    </aside>

    <!-- Glowny panel / Main panel -->
    <main class="abp-main">
        <?php if (!$selectedPlayer): ?>
        <div class="abp-placeholder">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" width="44" height="44" aria-hidden="true" class="abp-placeholder-icon">
                <line x1="3" y1="22" x2="21" y2="22"/>
                <rect x="5" y="11" width="2" height="9"/>
                <rect x="11" y="11" width="2" height="9"/>
                <rect x="17" y="11" width="2" height="9"/>
                <path d="M12 2L2 9h20z"/>
            </svg>
            <p><?= t('admin.bank.choose') ?> z listy, aby zobaczyc szczegoly konta.</p>
        </div>

        <?php else: ?>

        <!-- Naglowek gracza / Player header -->
        <div class="abp-player-head">
            <span class="abp-ph-id">#<?= (int)$selectedPlayer['id'] ?></span>
            <span class="abp-ph-email"><?= htmlspecialchars($selectedPlayer['email']) ?></span>
            <?php
                $st = (string)($selectedPlayer['status'] ?? '');
                $stLabel = $statusLabels[$st] ?? $st;
            ?>
            <span class="abp-status abp-status--<?= htmlspecialchars($st) ?>"><?= htmlspecialchars($stLabel) ?></span>
        </div>

        <!-- Kafelki: numer konta + saldo / Tiles: account number + balance -->
        <div class="abp-tiles">
            <div class="abp-tile">
                <span class="abp-tile-lbl"><?= t('admin.bank.account') ?></span>
                <?php if (!empty($selectedPlayer['bank_account_number'])): ?>
                <span class="abp-tile-val abp-tile-val--mono"><?= htmlspecialchars($selectedPlayer['bank_account_number']) ?></span>
                <?php else: ?>
                <span class="abp-tile-val abp-tile-val--muted"><?= t('admin.bank.no_bank') ?></span>
                <?php endif ?>
            </div>
            <div class="abp-tile abp-tile--bal">
                <span class="abp-tile-lbl"><?= t('admin.bank.balance') ?></span>
                <span class="abp-tile-val"><?= number_format((float)$selectedPlayer['cash'], 2, ',', ' ') ?> PLN</span>
            </div>
        </div>

        <!-- Przyciski akcji / Action buttons -->
        <div class="abp-actions">
            <button type="button"
                    class="btn btn-success abp-btn-action"
                    id="abp-credit-btn"
                    data-player-id="<?= (int)$selectedPlayer['id'] ?>"
                    data-player-email="<?= htmlspecialchars($selectedPlayer['email'], ENT_QUOTES) ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <?= t('admin.bank.credit') ?>
            </button>
            <button type="button"
                    class="btn btn-danger abp-btn-action"
                    id="abp-debit-btn"
                    data-player-id="<?= (int)$selectedPlayer['id'] ?>"
                    data-player-email="<?= htmlspecialchars($selectedPlayer['email'], ENT_QUOTES) ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <?= t('admin.bank.debit') ?>
            </button>
        </div>

        <!-- <?= t('admin.bank.history') ?> / Transaction history -->
        <section class="abp-hist-wrap">
            <h2 class="abp-sect-title"><?= t('admin.bank.history') ?> <span class="abp-hist-count">(<?= count($selectedHistory) ?>)</span></h2>

            <?php if (empty($selectedHistory)): ?>
            <div class="abp-empty"><?= t('admin.bank.empty_history') ?></div>

            <?php else: ?>

            <!-- Desktop table / Mobile cards -->
            <div class="abp-hist-table">
                <div class="abp-hist-header">
                    <span><?= t('admin.bank.date') ?></span>
                    <span><?= t('admin.bank.type') ?></span>
                    <span><?= t('admin.bank.party') ?></span>
                    <span><?= t('admin.bank.description') ?></span>
                    <span class="abp-col-r"><?= t('admin.bank.amount') ?></span>
                </div>
                <?php foreach ($selectedHistory as $row):
                    $isIn  = !empty($row['is_inflow']);
                    $amt   = (float)($row['signed_amount'] ?? 0);
                    $sign  = $isIn ? '+' : '';
                    $cls   = $isIn ? 'abp-in' : 'abp-out';
                    $type  = (string)($row['transaction_type'] ?? '');
                    $tLbl  = $typeLabels[$type] ?? $type;
                    $desc  = (string)($row['description'] ?? '');
                    $cpart = (string)($row['counterparty_label'] ?? '');
                    $isAdm = ($type === 'admin_adjustment');
                ?>
                <div class="abp-hist-row">
                    <span class="abp-col-date"><?= htmlspecialchars($row['created_at_fmt'] ?? '') ?></span>
                    <span class="abp-col-type">
                        <span class="abp-type-pill abp-type-<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($tLbl) ?></span>
                        <?php if ($isAdm): ?><span class="abp-adm-badge">ADMIN</span><?php endif ?>
                    </span>
                    <span class="abp-col-party"><?= htmlspecialchars($cpart) ?></span>
                    <span class="abp-col-desc"><?= $desc !== '' ? htmlspecialchars($desc) : '<span class="abp-muted">—</span>' ?></span>
                    <span class="abp-col-amt <?= $cls ?>">
                        <?= $sign ?><?= number_format($amt, 2, ',', ' ') ?> PLN
                    </span>
                </div>
                <?php endforeach ?>
            </div>

            <?php endif ?>
        </section>

        <?php endif ?>
    </main>
</div>

<!-- Modal korekty salda / Balance adjustment modal -->
<div id="abp-modal" class="abp-modal-overlay" hidden role="dialog" aria-modal="true" aria-labelledby="abp-modal-title"
     data-credit="<?= t('admin.bank.credit') ?>" data-debit="<?= t('admin.bank.debit') ?>">
    <div class="abp-modal">
        <button type="button" class="abp-modal-x" id="abp-modal-close" aria-label="<?= t('admin.bank.close') ?>">&times;</button>

        <div class="abp-modal-icon" id="abp-modal-icon" aria-hidden="true"></div>
        <h3 class="abp-modal-title" id="abp-modal-title"><?= t('admin.bank.adjustment') ?></h3>
        <p class="abp-modal-sub" id="abp-modal-sub"><?= t('admin.bank.player') ?> <strong id="abp-modal-player-lbl"></strong></p>

        <form method="post" id="abp-modal-form" data-confirm="<?= t('admin.bank.confirm') ?>" data-confirm-type="danger">
            <?= CSRF::field() ?>
            <input type="hidden" name="action"    id="abp-modal-action"    value="">
            <input type="hidden" name="player_id" id="abp-modal-pid"       value="">

            <div class="abp-field">
                <label for="abp-modal-amount"><?= t('admin.bank.amount_pln') ?></label>
                <input type="text" inputmode="decimal" id="abp-modal-amount" name="amount"
                       placeholder="<?= t('admin.bank.amount_placeholder') ?>" autocomplete="off" required>
            </div>

            <div class="abp-field">
                <label for="abp-modal-note">
                    <?= t('admin.bank.note') ?>
                    <span class="abp-required" aria-hidden="true">*</span>
                </label>
                <textarea id="abp-modal-note" name="note" rows="3" maxlength="255"
                          placeholder="<?= t('admin.bank.note_placeholder') ?>" required></textarea>
                <small><?= t('admin.bank.note_hint') ?></small>
            </div>

            <div class="abp-modal-btns">
                <button type="button" class="btn btn-ghost" id="abp-modal-cancel"><?= t('admin.bank.cancel') ?></button>
                <button type="submit" class="btn" id="abp-modal-submit"><?= t('admin.bank.submit') ?></button>
            </div>
        </form>
    </div>
</div>

<link rel="stylesheet" href="/assets/css/admin_bank_panel.css">

<script src="/assets/js/admin_bank_panel.js" defer></script>
