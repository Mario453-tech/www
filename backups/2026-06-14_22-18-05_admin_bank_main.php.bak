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
    Bank — konta graczy
</h1>

<?php if (!empty($flash)): ?>
<div class="abp-flash abp-flash--<?= htmlspecialchars($flash['type']) ?>">
    <?= htmlspecialchars($flash['msg']) ?>
</div>
<?php endif ?>

<section class="panel" style="margin-bottom:1.5rem">
    <p class="panel-title">Limit przelewu portfel &harr; konto</p>
    <form method="post" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="update_transfer_limit">
        <div>
            <label style="display:block;margin-bottom:4px;font-size:.85rem">Maksymalna kwota przelewu (PLN)</label>
            <input type="number" name="wallet_transfer_max"
                   value="<?= number_format($currentTransferMax, 0, '.', '') ?>"
                   min="100" step="1000"
                   style="width:200px">
        </div>
        <button type="submit" class="btn btn-primary">Zapisz limit</button>
        <span style="font-size:.85rem;color:#aaa">Aktualnie: <?= number_format($currentTransferMax, 0, ',', ' ') ?> PLN</span>
    </form>
</section>

<!-- Mobile: select dropdown zamiast sidebaru / Mobile: select dropdown instead of sidebar -->
<div class="abp-mobile-picker">
    <label for="abp-player-select" class="abp-select-label">Wybierz gracza</label>
    <select id="abp-player-select" class="abp-select"
            onchange="if(this.value) location.href='/admin/bank.php?player_id='+this.value">
        <option value="">— wybierz gracza —</option>
        <?php foreach ($players as $p):
            $hasAcc = !empty($p['bank_account_number']);
        ?>
        <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === $selectedId ? 'selected' : '' ?>>
            #<?= (int)$p['id'] ?>
            <?= htmlspecialchars($p['email']) ?>
            <?= $hasAcc ? '(' . htmlspecialchars($p['bank_account_number']) . ')' : '(brak konta)' ?>
            — <?= number_format((float)$p['cash'], 2, ',', ' ') ?> PLN
        </option>
        <?php endforeach ?>
    </select>
</div>

<div class="abp-layout">

    <!-- Sidebar: lista graczy / Sidebar: player list -->
    <aside class="abp-sidebar">
        <div class="abp-sidebar-head">Gracze (<?= count($players) ?>)</div>
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
                            <span class="abp-pr-noacc">brak konta</span>
                        <?php endif ?>
                    </span>
                </span>
                <span class="abp-pr-bal"><?= number_format((float)$p['cash'], 2, ',', ' ') ?>&nbsp;PLN</span>
            </a>
            <?php endforeach ?>
            <?php if (empty($players)): ?>
            <div class="abp-empty">Brak graczy w bazie.</div>
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
            <p>Wybierz gracza z listy, aby zobaczyc szczegoly konta.</p>
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
                <span class="abp-tile-lbl">Numer konta</span>
                <?php if (!empty($selectedPlayer['bank_account_number'])): ?>
                <span class="abp-tile-val abp-tile-val--mono"><?= htmlspecialchars($selectedPlayer['bank_account_number']) ?></span>
                <?php else: ?>
                <span class="abp-tile-val abp-tile-val--muted">brak konta bankowego</span>
                <?php endif ?>
            </div>
            <div class="abp-tile abp-tile--bal">
                <span class="abp-tile-lbl">Saldo</span>
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
                Dodaj srodki
            </button>
            <button type="button"
                    class="btn btn-danger abp-btn-action"
                    id="abp-debit-btn"
                    data-player-id="<?= (int)$selectedPlayer['id'] ?>"
                    data-player-email="<?= htmlspecialchars($selectedPlayer['email'], ENT_QUOTES) ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Pobierz srodki
            </button>
        </div>

        <!-- Historia transakcji / Transaction history -->
        <section class="abp-hist-wrap">
            <h2 class="abp-sect-title">Historia transakcji <span class="abp-hist-count">(<?= count($selectedHistory) ?>)</span></h2>

            <?php if (empty($selectedHistory)): ?>
            <div class="abp-empty">Brak transakcji dla tego gracza.</div>

            <?php else: ?>

            <!-- Desktop table / Mobile cards -->
            <div class="abp-hist-table">
                <div class="abp-hist-header">
                    <span>Data</span>
                    <span>Typ</span>
                    <span>Strona</span>
                    <span>Opis</span>
                    <span class="abp-col-r">Kwota</span>
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
<div id="abp-modal" class="abp-modal-overlay" style="display:none" role="dialog" aria-modal="true" aria-labelledby="abp-modal-title">
    <div class="abp-modal">
        <button type="button" class="abp-modal-x" id="abp-modal-close" aria-label="Zamknij">&times;</button>

        <div class="abp-modal-icon" id="abp-modal-icon" aria-hidden="true"></div>
        <h3 class="abp-modal-title" id="abp-modal-title">Korekta salda</h3>
        <p class="abp-modal-sub" id="abp-modal-sub">Gracz: <strong id="abp-modal-player-lbl"></strong></p>

        <form method="post" id="abp-modal-form">
            <?= CSRF::field() ?>
            <input type="hidden" name="action"    id="abp-modal-action"    value="">
            <input type="hidden" name="player_id" id="abp-modal-pid"       value="">

            <div class="abp-field">
                <label for="abp-modal-amount">Kwota (PLN)</label>
                <input type="text" inputmode="decimal" id="abp-modal-amount" name="amount"
                       placeholder="np. 10 000,00" autocomplete="off" required>
            </div>

            <div class="abp-field">
                <label for="abp-modal-note">
                    Opis korekty
                    <span class="abp-required" aria-hidden="true">*</span>
                </label>
                <textarea id="abp-modal-note" name="note" rows="3" maxlength="255"
                          placeholder="Obowiazkowy opis powodu korekty..." required></textarea>
                <small>Opis zostanie zapisany w historii i wyslany do gracza jako powiadomienie.</small>
            </div>

            <div class="abp-modal-btns">
                <button type="button" class="btn btn-ghost" id="abp-modal-cancel">Anuluj</button>
                <button type="submit" class="btn" id="abp-modal-submit">Zatwierdz</button>
            </div>
        </form>
    </div>
</div>

<style>
/* ===========================================================
   ADMIN BANK PANEL — mobile-first styles
   =========================================================== */

/* Page title */
.abp-page-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 18px;
    font-weight: 700;
    margin: 0 0 16px;
    color: var(--text, #eee);
}

/* Flash message */
.abp-flash {
    padding: 11px 14px;
    border-radius: 8px;
    border: 1px solid;
    margin-bottom: 14px;
    font-size: 13px;
    line-height: 1.5;
}
.abp-flash--success { background: rgba(78,201,122,.08); border-color: rgba(78,201,122,.3); color: #4ec97a; }
.abp-flash--error   { background: rgba(224,85,85,.08);  border-color: rgba(224,85,85,.3);  color: #e05555; }

/* Mobile select picker — visible only on small screens */
.abp-mobile-picker {
    display: none;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 14px;
}
.abp-select-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--muted, #888);
    font-weight: 700;
}
.abp-select {
    width: 100%;
    padding: 10px 12px;
    background: var(--bg3, #1a1a1a);
    border: 1px solid var(--border, #333);
    border-radius: 8px;
    color: var(--text, #eee);
    font-size: 13px;
    font-family: inherit;
    outline: none;
    cursor: pointer;
}
.abp-select:focus { border-color: var(--orange, #c8860a); }

/* Two-column layout (desktop) */
.abp-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 16px;
    align-items: start;
}

/* Sidebar */
.abp-sidebar {
    background: var(--bg2, #161616);
    border: 1px solid var(--border, #2a2a2a);
    border-radius: 10px;
    overflow: hidden;
    position: sticky;
    top: 12px;
    max-height: calc(100vh - 80px);
    display: flex;
    flex-direction: column;
}
.abp-sidebar-head {
    padding: 10px 13px 8px;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--muted, #888);
    font-weight: 700;
    border-bottom: 1px solid var(--border, #2a2a2a);
    flex-shrink: 0;
}
.abp-player-list { overflow-y: auto; flex: 1; }
.abp-player-row {
    display: grid;
    grid-template-columns: 32px 1fr auto;
    gap: 6px;
    align-items: center;
    padding: 9px 12px;
    border-bottom: 1px solid var(--border, #2a2a2a);
    text-decoration: none;
    color: inherit;
    transition: background .1s;
    cursor: pointer;
}
.abp-player-row:last-child { border-bottom: none; }
.abp-player-row:hover { background: rgba(255,255,255,.03); }
.abp-player-row--on {
    background: rgba(200,134,10,.07);
    border-left: 3px solid var(--orange, #c8860a);
}
.abp-pr-id  { font-size: 10px; color: var(--muted, #888); font-weight: 700; }
.abp-pr-body { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.abp-pr-email { font-size: 12px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: inherit; }
.abp-pr-sub { font-size: 10px; }
.abp-pr-acc   { color: var(--orange, #c8860a); font-family: monospace; }
.abp-pr-noacc { color: var(--muted, #888); font-style: italic; }
.abp-pr-bal { font-size: 11px; font-weight: 700; color: var(--green, #4ec97a); text-align: right; white-space: nowrap; }

/* Main area */
.abp-main { min-height: 200px; }

/* Placeholder */
.abp-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 200px;
    gap: 10px;
    color: var(--muted, #888);
    font-size: 13px;
    text-align: center;
}
.abp-placeholder-icon { opacity: .2; }

/* Player header */
.abp-player-head {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 14px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border, #2a2a2a);
}
.abp-ph-id    { font-size: 11px; color: var(--muted, #888); font-weight: 700; }
.abp-ph-email { font-size: 14px; font-weight: 700; font-family: inherit; word-break: break-all; }
/* Status badges */
.abp-status {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .02em;
    text-transform: uppercase;
    border: 1px solid;
}
.abp-status--active         { color: #4ec97a; border-color: rgba(78,201,122,.4); background: rgba(78,201,122,.08); }
.abp-status--financial_risk { color: var(--orange, #c8860a); border-color: rgba(200,134,10,.4); background: rgba(200,134,10,.07); }
.abp-status--under_bailiff  { color: #e0b35a; border-color: rgba(224,179,90,.4); background: rgba(224,179,90,.07); }
.abp-status--bankrupt       { color: #e05555; border-color: rgba(224,85,85,.4); background: rgba(224,85,85,.08); }
.abp-status--banned         { color: var(--muted, #888); border-color: rgba(128,128,128,.3); background: rgba(128,128,128,.05); }

/* Tiles */
.abp-tiles {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 12px;
}
.abp-tile {
    background: var(--bg2, #161616);
    border: 1px solid var(--border, #2a2a2a);
    border-radius: 10px;
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.abp-tile-lbl {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--muted, #888);
    font-weight: 700;
}
.abp-tile-val {
    font-size: 16px;
    font-weight: 700;
    color: var(--text, #eee);
    word-break: break-all;
    line-height: 1.3;
}
.abp-tile-val--mono  { font-family: monospace; color: var(--orange, #c8860a); font-size: 14px; }
.abp-tile-val--muted { color: var(--muted, #888); font-size: 12px; font-style: italic; font-weight: 400; }
.abp-tile--bal .abp-tile-val { color: var(--green, #4ec97a); }

/* Action buttons row */
.abp-actions {
    display: flex;
    gap: 8px;
    margin-bottom: 18px;
    flex-wrap: wrap;
}
.abp-btn-action {
    display: flex;
    align-items: center;
    gap: 6px;
    flex: 1;
    justify-content: center;
    min-width: 120px;
    font-size: 12px;
}

/* Section title */
.abp-sect-title {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--muted, #888);
    font-weight: 700;
    margin: 0 0 10px;
}
.abp-hist-count { font-weight: 400; opacity: .7; }

/* History — desktop table */
.abp-hist-wrap {
    background: var(--bg2, #161616);
    border: 1px solid var(--border, #2a2a2a);
    border-radius: 10px;
    padding: 14px;
}
.abp-hist-table { width: 100%; }
.abp-hist-header,
.abp-hist-row {
    display: grid;
    grid-template-columns: 130px 160px 160px 1fr 140px;
    gap: 8px;
    padding: 8px 4px;
    border-bottom: 1px solid var(--border, #2a2a2a);
    align-items: center;
    font-size: 12px;
}
.abp-hist-header {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--muted, #888);
    font-weight: 700;
    background: var(--bg3, #1e1e1e);
    border-radius: 6px;
    padding: 6px 8px;
    margin-bottom: 4px;
    border: none;
}
.abp-hist-row:last-child { border-bottom: none; }
.abp-hist-row:hover { background: rgba(255,255,255,.015); border-radius: 4px; }
.abp-col-date  { color: var(--muted, #888); white-space: nowrap; font-size: 11px; }
.abp-col-type  { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
.abp-col-party { font-family: monospace; font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.abp-col-desc  { font-size: 11px; color: var(--muted, #888); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.abp-col-r     { text-align: right; }
.abp-col-amt   { font-weight: 700; text-align: right; white-space: nowrap; }
.abp-in  { color: var(--green, #4ec97a); }
.abp-out { color: var(--red, #e05555); }

/* Type pill */
.abp-type-pill {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    background: var(--bg3, #1e1e1e);
    border: 1px solid var(--border, #2a2a2a);
    white-space: nowrap;
}
.abp-type-admin_adjustment { color: var(--orange, #c8860a); border-color: rgba(200,134,10,.4); }
.abp-type-player_transfer  { color: #58c4dd; border-color: rgba(88,196,221,.3); }
.abp-type-loan             { color: var(--green, #4ec97a); border-color: rgba(78,201,122,.3); }
.abp-type-loan_payment     { color: #e0b35a; border-color: rgba(224,179,90,.3); }
.abp-type-tax              { color: var(--red, #e05555); border-color: rgba(224,85,85,.3); }
.abp-adm-badge {
    display: inline-block;
    padding: 1px 4px;
    border-radius: 3px;
    font-size: 9px;
    font-weight: 800;
    background: rgba(200,134,10,.15);
    color: var(--orange, #c8860a);
    letter-spacing: .03em;
}
.abp-empty  { padding: 12px 4px; font-size: 13px; color: var(--muted, #888); }
.abp-muted  { color: var(--muted, #888); }
.abp-required { color: var(--red, #e05555); }

/* Modal */
.abp-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    background: rgba(0,0,0,.65);
    backdrop-filter: blur(2px);
    padding: 0;
}
.abp-modal {
    position: relative;
    width: 100%;
    max-width: 520px;
    background: var(--bg, #111);
    border: 1px solid var(--border, #2a2a2a);
    border-radius: 16px 16px 0 0;
    padding: 20px 20px 28px;
    box-shadow: 0 -12px 40px rgba(0,0,0,.5);
    animation: abp-slide-up .22s ease;
    max-height: 92vh;
    overflow-y: auto;
}
@keyframes abp-slide-up {
    from { transform: translateY(100%); }
    to   { transform: translateY(0); }
}
.abp-modal-x {
    position: absolute;
    top: 10px; right: 14px;
    background: transparent; border: 0;
    color: var(--muted, #888);
    font-size: 22px; line-height: 1;
    cursor: pointer; padding: 4px 8px;
    border-radius: 6px;
    transition: background .12s, color .12s;
}
.abp-modal-x:hover { background: rgba(255,255,255,.06); color: var(--text, #eee); }
.abp-modal-icon { text-align: center; margin-bottom: 6px; }
.abp-modal-icon svg { width: 34px; height: 34px; }
.abp-modal-title { text-align: center; font-size: 17px; font-weight: 700; margin: 0 0 3px; }
.abp-modal-sub   { text-align: center; font-size: 12px; color: var(--muted, #888); margin: 0 0 16px; }
.abp-field { margin-bottom: 12px; }
.abp-field label {
    display: block;
    font-size: 10px; text-transform: uppercase; letter-spacing: .05em;
    color: var(--muted, #888); font-weight: 700; margin-bottom: 5px;
}
.abp-field input,
.abp-field textarea {
    width: 100%; box-sizing: border-box;
    background: var(--bg2, #161616);
    border: 1px solid var(--border, #2a2a2a);
    border-radius: 8px;
    color: var(--text, #eee);
    font-size: 15px;
    padding: 10px 12px;
    transition: border-color .12s, box-shadow .12s;
    outline: none;
    font-family: inherit;
    resize: vertical;
}
.abp-field input:focus,
.abp-field textarea:focus {
    border-color: var(--orange, #c8860a);
    box-shadow: 0 0 0 3px rgba(200,134,10,.14);
}
.abp-field small { display: block; font-size: 10px; color: var(--muted, #888); margin-top: 4px; line-height: 1.4; }
.abp-modal-btns { display: flex; gap: 8px; margin-top: 14px; }
.abp-modal-btns .btn { flex: 1; justify-content: center; }

/* -------- RESPONSIVE -------- */

/* Tablet: compact sidebar */
@media (max-width: 1080px) {
    .abp-layout { grid-template-columns: 240px 1fr; }
    .abp-pr-email { font-size: 11px; }
}

/* Mobile breakpoint: switch to single column + dropdown */
@media (max-width: 760px) {
    .abp-mobile-picker { display: flex; }
    .abp-layout { display: block; }
    .abp-sidebar { display: none; }
    .abp-modal {
        border-radius: 16px 16px 0 0;
        max-width: 100%;
    }

    /* Tiles full width on mobile */
    .abp-tiles { grid-template-columns: 1fr 1fr; }
    .abp-tile-val--mono { font-size: 12px; }

    /* Action buttons stacked */
    .abp-actions { flex-direction: column; }
    .abp-btn-action { min-width: unset; flex: none; width: 100%; }

    /* History: card layout instead of table */
    .abp-hist-header { display: none; }
    .abp-hist-row {
        display: grid;
        grid-template-columns: 1fr auto;
        grid-template-rows: auto auto auto;
        gap: 3px 8px;
        padding: 10px 8px;
        border-bottom: 1px solid var(--border, #2a2a2a);
        border-radius: 0;
    }
    .abp-col-date  { grid-column: 1; grid-row: 1; font-size: 10px; }
    .abp-col-amt   { grid-column: 2; grid-row: 1; font-size: 14px; text-align: right; }
    .abp-col-type  { grid-column: 1 / -1; grid-row: 2; }
    .abp-col-party { grid-column: 1 / -1; grid-row: 3; font-size: 11px; color: var(--muted, #888); white-space: normal; }
    .abp-col-desc  { grid-column: 1 / -1; grid-row: 4; font-size: 11px; white-space: normal; }
}

/* Very small screens */
@media (max-width: 380px) {
    .abp-tiles { grid-template-columns: 1fr; }
    .abp-tile-val { font-size: 15px; }
    .abp-modal { padding: 18px 16px 24px; }
}

/* Desktop: modal as centered dialog instead of bottom sheet */
@media (min-width: 761px) {
    .abp-modal-overlay {
        align-items: center;
        padding: 20px;
    }
    .abp-modal {
        border-radius: 14px;
        max-width: 480px;
        animation: abp-pop .2s ease;
        max-height: 90vh;
    }
    @keyframes abp-pop {
        from { opacity: 0; transform: scale(.96) translateY(8px); }
        to   { opacity: 1; transform: scale(1) translateY(0); }
    }
}
</style>

<script>
(function () {
    'use strict';

    var modal   = document.getElementById('abp-modal');
    var form    = document.getElementById('abp-modal-form');
    var btnX    = document.getElementById('abp-modal-close');
    var btnCnl  = document.getElementById('abp-modal-cancel');
    var credit  = document.getElementById('abp-credit-btn');
    var debit   = document.getElementById('abp-debit-btn');
    var elAct   = document.getElementById('abp-modal-action');
    var elPid   = document.getElementById('abp-modal-pid');
    var elPlr   = document.getElementById('abp-modal-player-lbl');
    var elTitle = document.getElementById('abp-modal-title');
    var elSubmit= document.getElementById('abp-modal-submit');
    var elIcon  = document.getElementById('abp-modal-icon');

    if (!modal || !form) return;

    // SVG ikony bez emoji / SVG icons (no emoji)
    var svgPlus = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#4ec97a" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>';
    var svgMinus = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#e05555" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>';

    function openModal(action, pid, email) {
        elAct.value = action;
        elPid.value = pid;
        if (elPlr)   elPlr.textContent = '#' + pid + ' ' + email;
        if (action === 'admin_credit') {
            if (elTitle)  elTitle.textContent   = 'Dodaj srodki';
            if (elSubmit) { elSubmit.textContent = 'Dodaj srodki'; elSubmit.className = 'btn btn-success'; }
            if (elIcon)   elIcon.innerHTML = svgPlus;
        } else {
            if (elTitle)  elTitle.textContent   = 'Pobierz srodki';
            if (elSubmit) { elSubmit.textContent = 'Pobierz srodki'; elSubmit.className = 'btn btn-danger'; }
            if (elIcon)   elIcon.innerHTML = svgMinus;
        }
        var fAmt  = document.getElementById('abp-modal-amount');
        var fNote = document.getElementById('abp-modal-note');
        if (fAmt)  fAmt.value  = '';
        if (fNote) fNote.value = '';
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        setTimeout(function () { if (fAmt) fAmt.focus(); }, 40);
    }

    function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    credit  && credit.addEventListener('click',  function () { openModal('admin_credit', this.dataset.playerId, this.dataset.playerEmail); });
    debit   && debit.addEventListener('click',   function () { openModal('admin_debit',  this.dataset.playerId, this.dataset.playerEmail); });
    btnX    && btnX.addEventListener('click',    closeModal);
    btnCnl  && btnCnl.addEventListener('click',  closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.style.display === 'flex') closeModal(); });
})();
</script>
