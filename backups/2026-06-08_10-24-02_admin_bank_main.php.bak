<?php
/**
 * Admin view: panel bankowy
 * Admin view: banking panel
 */
extract($viewData, EXTR_SKIP);
?>

<h1 style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" width="24" height="24" aria-hidden="true">
        <line x1="3" y1="22" x2="21" y2="22"/>
        <rect x="5" y="11" width="2" height="9"/>
        <rect x="11" y="11" width="2" height="9"/>
        <rect x="17" y="11" width="2" height="9"/>
        <path d="M12 2L2 9h20z"/>
    </svg>
    Bank — panel administratora
</h1>

<?php if (!empty($flash)): ?>
<div class="admin-bank-flash admin-bank-flash--<?= htmlspecialchars($flash['type']) ?>">
    <?= htmlspecialchars($flash['msg']) ?>
</div>
<?php endif ?>

<div class="admin-bank-layout">

    <!-- Lewa kolumna: lista graczy / Left column: player list -->
    <aside class="admin-bank-sidebar">
        <h2 class="admin-bank-sidebar-title">Gracze</h2>
        <div class="admin-bank-player-list">
            <?php foreach ($players as $p): ?>
            <?php
                $hasAccount = !empty($p['bank_account_number']);
                $isSelected = (int)$p['id'] === (int)$selectedId;
            ?>
            <a href="/admin/bank.php?player_id=<?= (int)$p['id'] ?>"
               class="admin-bank-player-row <?= $isSelected ? 'admin-bank-player-row--active' : '' ?>">
                <span class="abp-id">#<?= (int)$p['id'] ?></span>
                <span class="abp-email"><?= htmlspecialchars($p['email']) ?></span>
                <?php if ($hasAccount): ?>
                <span class="abp-number"><?= htmlspecialchars($p['bank_account_number']) ?></span>
                <?php else: ?>
                <span class="abp-no-account">brak konta</span>
                <?php endif ?>
                <span class="abp-balance"><?= number_format((float)$p['cash'], 2, ',', ' ') ?> PLN</span>
            </a>
            <?php endforeach ?>
            <?php if (empty($players)): ?>
            <div class="admin-bank-empty">Brak graczy w bazie.</div>
            <?php endif ?>
        </div>
    </aside>

    <!-- Prawa kolumna: szczegoly + korekty / Right column: details + adjustments -->
    <main class="admin-bank-main">
        <?php if (!$selectedPlayer): ?>
        <div class="admin-bank-placeholder">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" width="48" height="48" aria-hidden="true" style="opacity:.3">
                <line x1="3" y1="22" x2="21" y2="22"/>
                <rect x="5" y="11" width="2" height="9"/>
                <rect x="11" y="11" width="2" height="9"/>
                <rect x="17" y="11" width="2" height="9"/>
                <path d="M12 2L2 9h20z"/>
            </svg>
            <p>Wybierz gracza z listy po lewej, aby zobaczyc szczegoly konta.</p>
            <p style="font-size:12px;color:#666">Select a player from the left list to view account details.</p>
        </div>

        <?php else: ?>

        <!-- Naglowek gracza / Player header -->
        <div class="admin-bank-player-header">
            <div class="admin-bank-player-meta">
                <span class="admin-bank-player-id">#<?= (int)$selectedPlayer['id'] ?></span>
                <strong class="admin-bank-player-email"><?= htmlspecialchars($selectedPlayer['email']) ?></strong>
                <span class="badge badge-<?= htmlspecialchars($selectedPlayer['status']) ?>"><?= htmlspecialchars($selectedPlayer['status']) ?></span>
            </div>
        </div>

        <!-- Kafelki: numer konta + saldo / Account number + balance tiles -->
        <div class="admin-bank-tiles">
            <div class="admin-bank-tile">
                <span class="admin-bank-tile-label">Numer konta</span>
                <?php if (!empty($selectedPlayer['bank_account_number'])): ?>
                <span class="admin-bank-tile-value admin-bank-tile-value--mono"><?= htmlspecialchars($selectedPlayer['bank_account_number']) ?></span>
                <?php else: ?>
                <span class="admin-bank-tile-value admin-bank-tile-value--muted">brak konta bankowego</span>
                <?php endif ?>
            </div>
            <div class="admin-bank-tile admin-bank-tile--balance">
                <span class="admin-bank-tile-label">Saldo (cash)</span>
                <span class="admin-bank-tile-value money"><?= number_format((float)$selectedPlayer['cash'], 2, ',', ' ') ?> PLN</span>
            </div>
            <div class="admin-bank-tile admin-bank-tile--actions">
                <button type="button"
                        class="btn btn-success"
                        id="admin-bank-credit-trigger"
                        data-player-id="<?= (int)$selectedPlayer['id'] ?>"
                        data-player-email="<?= htmlspecialchars($selectedPlayer['email'], ENT_QUOTES) ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Dodaj srodki
                </button>
                <button type="button"
                        class="btn btn-danger"
                        id="admin-bank-debit-trigger"
                        data-player-id="<?= (int)$selectedPlayer['id'] ?>"
                        data-player-email="<?= htmlspecialchars($selectedPlayer['email'], ENT_QUOTES) ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Pobierz srodki
                </button>
            </div>
        </div>

        <!-- Historia transakcji / Transaction history -->
        <div class="admin-bank-history-wrap">
            <h3 class="admin-bank-section-title">Historia transakcji (ostatnie 50)</h3>

            <?php if (empty($selectedHistory)): ?>
            <div class="admin-bank-empty">Brak transakcji dla tego gracza.</div>
            <?php else: ?>
            <div class="admin-bank-history">
                <div class="admin-bank-history-header">
                    <span>Data</span>
                    <span>Typ</span>
                    <span>Strona</span>
                    <span>Opis</span>
                    <span style="text-align:right">Kwota</span>
                </div>
                <?php foreach ($selectedHistory as $row):
                    $isIn = !empty($row['is_inflow']);
                    $amt  = (float)($row['signed_amount'] ?? 0);
                    $sign = $isIn ? '+' : '';
                    $cls  = $isIn ? 'admin-bh-in' : 'admin-bh-out';
                    $type = (string)($row['transaction_type'] ?? '-');
                ?>
                <div class="admin-bank-history-row">
                    <span class="admin-bh-date"><?= htmlspecialchars($row['created_at_fmt'] ?? '') ?></span>
                    <span>
                        <span class="admin-bh-type admin-bh-type-<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></span>
                        <?php if ($type === 'admin_adjustment'): ?>
                        <span class="admin-bh-badge-admin">ADMIN</span>
                        <?php endif ?>
                    </span>
                    <span class="admin-bh-counterparty"><?= htmlspecialchars($row['counterparty_label'] ?? '') ?></span>
                    <span class="admin-bh-desc"><?= htmlspecialchars((string)($row['description'] ?? '')) ?></span>
                    <span class="admin-bh-amount <?= $cls ?>">
                        <?= $sign ?><?= number_format($amt, 2, ',', ' ') ?> PLN
                    </span>
                </div>
                <?php endforeach ?>
            </div>
            <?php endif ?>
        </div>

        <?php endif // selectedPlayer ?>
    </main>
</div>

<!-- Modal korekty salda / Balance adjustment modal -->
<div id="admin-bank-modal" class="admin-bank-modal-overlay" style="display:none" role="dialog" aria-modal="true" aria-labelledby="admin-bank-modal-title">
    <div class="admin-bank-modal">
        <button type="button" class="admin-bank-modal-close" id="admin-bank-modal-close" aria-label="Zamknij">&times;</button>
        <div class="admin-bank-modal-icon" aria-hidden="true" id="admin-bank-modal-icon">
            <!-- dynamicznie / dynamically set via JS -->
        </div>
        <h3 id="admin-bank-modal-title" class="admin-bank-modal-title" id="admin-bank-modal-title-text">Korekta salda</h3>
        <p class="admin-bank-modal-desc" id="admin-bank-modal-desc">Gracz: <strong id="admin-bank-modal-player"></strong></p>

        <form method="post" id="admin-bank-modal-form">
            <?= CSRF::field() ?>
            <input type="hidden" name="action"    id="admin-bank-modal-action"    value="">
            <input type="hidden" name="player_id" id="admin-bank-modal-player-id" value="">

            <div class="admin-bank-form-group">
                <label for="admin-bank-modal-amount">Kwota (PLN)</label>
                <input type="text"
                       inputmode="numeric"
                       pattern="[0-9 .,]*"
                       id="admin-bank-modal-amount"
                       name="amount"
                       placeholder="np. 10 000,00"
                       autocomplete="off"
                       required>
            </div>

            <div class="admin-bank-form-group">
                <label for="admin-bank-modal-note">Opis korekty <span style="color:var(--red,#e05555)">*</span></label>
                <textarea id="admin-bank-modal-note"
                          name="note"
                          rows="3"
                          maxlength="255"
                          placeholder="Obowiazkowy opis powodu korekty..."
                          required></textarea>
                <small>Opis jest wymagany i trafi do historii transakcji oraz powiadomienia gracza.</small>
            </div>

            <div class="admin-bank-modal-actions">
                <button type="button" class="btn btn-ghost" id="admin-bank-modal-cancel">Anuluj</button>
                <button type="submit" class="btn" id="admin-bank-modal-submit">Zatwierdz</button>
            </div>
        </form>
    </div>
</div>

<style>
/* ---- Admin Bank Panel styles ---- */
.admin-bank-flash {
    padding: 12px 16px;
    border-radius: 8px;
    border: 1px solid;
    margin-bottom: 16px;
    font-size: 14px;
}
.admin-bank-flash--success { background: rgba(78,201,122,.1); border-color: rgba(78,201,122,.3); color: #4ec97a; }
.admin-bank-flash--error   { background: rgba(224,85,85,.08); border-color: rgba(224,85,85,.3); color: #e05555; }

.admin-bank-layout {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 20px;
    align-items: start;
}
@media (max-width: 900px) {
    .admin-bank-layout { grid-template-columns: 1fr; }
    .admin-bank-sidebar { max-height: 320px; }
}

/* Sidebar */
.admin-bank-sidebar {
    background: var(--bg2, #1a1a1a);
    border: 1px solid var(--border2, #2a2a2a);
    border-radius: 10px;
    overflow: hidden;
    position: sticky;
    top: 16px;
    max-height: calc(100vh - 120px);
    display: flex;
    flex-direction: column;
}
.admin-bank-sidebar-title {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--text3, #888);
    padding: 12px 14px 10px;
    border-bottom: 1px solid var(--border2, #2a2a2a);
    margin: 0;
    flex-shrink: 0;
}
.admin-bank-player-list {
    overflow-y: auto;
    flex: 1;
}
.admin-bank-player-row {
    display: grid;
    grid-template-columns: 36px 1fr auto;
    grid-template-rows: auto auto;
    gap: 2px 8px;
    padding: 10px 14px;
    border-bottom: 1px solid var(--border2, #2a2a2a);
    text-decoration: none;
    color: inherit;
    transition: background .12s;
    cursor: pointer;
}
.admin-bank-player-row:hover { background: rgba(255,255,255,.03); }
.admin-bank-player-row--active { background: rgba(200,134,10,.08); border-left: 3px solid var(--orange, #c8860a); }
.abp-id { grid-column: 1; grid-row: 1 / -1; display:flex; align-items:center; font-size: 11px; color: var(--text3, #888); font-weight: 700; }
.abp-email { grid-column: 2; font-size: 13px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.abp-balance { grid-column: 3; font-size: 12px; font-weight: 700; color: var(--green, #4ec97a); text-align: right; white-space: nowrap; }
.abp-number { grid-column: 2; font-size: 10px; font-family: monospace; color: var(--orange, #c8860a); }
.abp-no-account { grid-column: 2; font-size: 10px; color: var(--text3, #888); font-style: italic; }
.admin-bank-empty { padding: 16px; font-size: 13px; color: var(--text3, #888); text-align: center; }

/* Main area */
.admin-bank-main { min-height: 300px; }
.admin-bank-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 240px;
    gap: 12px;
    color: var(--text3, #888);
    font-size: 14px;
    text-align: center;
}

/* Player header */
.admin-bank-player-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border2, #2a2a2a);
}
.admin-bank-player-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.admin-bank-player-id { font-size: 12px; color: var(--text3, #888); font-weight: 700; }
.admin-bank-player-email { font-size: 15px; }

/* Tiles */
.admin-bank-tiles {
    display: grid;
    grid-template-columns: 1.2fr 1fr auto;
    gap: 12px;
    margin-bottom: 20px;
    align-items: stretch;
}
@media (max-width: 700px) { .admin-bank-tiles { grid-template-columns: 1fr; } }
.admin-bank-tile {
    background: var(--bg2, #1a1a1a);
    border: 1px solid var(--border2, #2a2a2a);
    border-radius: 10px;
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-height: 80px;
}
.admin-bank-tile-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--text3, #888);
    font-weight: 700;
}
.admin-bank-tile-value {
    font-size: 18px;
    font-weight: 700;
    word-break: break-all;
}
.admin-bank-tile-value--mono { font-family: monospace; color: var(--orange, #c8860a); font-size: 15px; }
.admin-bank-tile-value--muted { color: var(--text3, #888); font-size: 13px; font-style: italic; font-weight: 400; }
.admin-bank-tile--balance .admin-bank-tile-value { color: var(--green, #4ec97a); }
.admin-bank-tile--actions {
    background: transparent;
    border: 0;
    padding: 0;
    justify-content: center;
    gap: 8px;
    min-height: 0;
}

/* Section title */
.admin-bank-section-title {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--text3, #888);
    margin: 0 0 10px;
    font-weight: 700;
}

/* History table */
.admin-bank-history-wrap {
    background: var(--bg2, #1a1a1a);
    border: 1px solid var(--border2, #2a2a2a);
    border-radius: 10px;
    padding: 16px;
    overflow-x: auto;
}
.admin-bank-history { min-width: 600px; }
.admin-bank-history-header,
.admin-bank-history-row {
    display: grid;
    grid-template-columns: 130px 150px 160px 1fr 140px;
    gap: 8px;
    padding: 8px 0;
    border-bottom: 1px solid var(--border2, #2a2a2a);
    align-items: center;
    font-size: 13px;
}
.admin-bank-history-header {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--text3, #888);
    font-weight: 700;
    background: var(--bg3, #222);
    border-radius: 6px;
    padding: 6px 8px;
    margin-bottom: 4px;
    border: none;
}
.admin-bank-history-row:last-child { border-bottom: none; }
.admin-bank-history-row:hover { background: rgba(255,255,255,.015); }
.admin-bh-date { color: var(--text3, #888); font-size: 12px; white-space: nowrap; }
.admin-bh-type {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    background: var(--bg3, #222);
    border: 1px solid var(--border2, #2a2a2a);
}
.admin-bh-type-admin_adjustment { color: var(--orange, #c8860a); border-color: rgba(200,134,10,.4); }
.admin-bh-type-player_transfer  { color: #58c4dd; border-color: rgba(88,196,221,.3); }
.admin-bh-type-loan             { color: var(--green, #4ec97a); border-color: rgba(78,201,122,.3); }
.admin-bh-type-loan_payment     { color: #e0b35a; border-color: rgba(224,179,90,.3); }
.admin-bh-badge-admin {
    display: inline-block;
    padding: 1px 5px;
    border-radius: 4px;
    font-size: 9px;
    font-weight: 800;
    background: rgba(200,134,10,.15);
    color: var(--orange, #c8860a);
    letter-spacing: .04em;
    margin-left: 4px;
    vertical-align: middle;
}
.admin-bh-counterparty { font-family: monospace; font-size: 11px; overflow: hidden; text-overflow: ellipsis; }
.admin-bh-desc { font-size: 12px; color: var(--text3, #888); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.admin-bh-amount { font-weight: 700; font-size: 13px; text-align: right; white-space: nowrap; }
.admin-bh-in  { color: var(--green, #4ec97a); }
.admin-bh-out { color: var(--red, #e05555); }

/* Modal */
.admin-bank-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,.65);
    backdrop-filter: blur(2px);
}
.admin-bank-modal {
    position: relative;
    width: min(480px, 92vw);
    background: var(--bg, #0f0f0f);
    border: 1px solid var(--border2, #2a2a2a);
    border-radius: 14px;
    padding: 28px 28px 24px;
    box-shadow: 0 24px 60px rgba(0,0,0,.6);
    animation: abm-pop .2s ease;
}
@keyframes abm-pop {
    from { opacity: 0; transform: translateY(10px) scale(.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}
.admin-bank-modal-close {
    position: absolute;
    top: 10px; right: 14px;
    background: transparent; border: 0;
    color: var(--text3, #888);
    font-size: 24px; line-height: 1;
    cursor: pointer; padding: 4px 10px;
    border-radius: 6px;
    transition: background .12s, color .12s;
}
.admin-bank-modal-close:hover { background: rgba(255,255,255,.05); color: var(--text, #eee); }
.admin-bank-modal-icon { text-align: center; margin-bottom: 8px; }
.admin-bank-modal-icon svg { width: 36px; height: 36px; }
.admin-bank-modal-title { text-align: center; font-size: 18px; margin: 0 0 4px; }
.admin-bank-modal-desc  { text-align: center; font-size: 13px; color: var(--text3, #888); margin: 0 0 16px; }
.admin-bank-form-group { margin-bottom: 14px; }
.admin-bank-form-group label {
    display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .05em;
    color: var(--text3, #888); font-weight: 700; margin-bottom: 6px;
}
.admin-bank-form-group input,
.admin-bank-form-group textarea {
    width: 100%; box-sizing: border-box;
    background: var(--bg2, #1a1a1a);
    border: 1px solid var(--border2, #2a2a2a);
    border-radius: 8px;
    color: var(--text, #eee);
    font-size: 14px;
    padding: 10px 12px;
    transition: border-color .12s, box-shadow .12s;
    outline: none;
    font-family: inherit;
    resize: vertical;
}
.admin-bank-form-group input:focus,
.admin-bank-form-group textarea:focus {
    border-color: var(--orange, #c8860a);
    box-shadow: 0 0 0 3px rgba(200,134,10,.15);
}
.admin-bank-form-group small { display: block; font-size: 11px; color: var(--text3, #888); margin-top: 5px; }
.admin-bank-modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 16px; }
</style>

<script>
(function () {
    'use strict';

    var modal      = document.getElementById('admin-bank-modal');
    var form       = document.getElementById('admin-bank-modal-form');
    var btnClose   = document.getElementById('admin-bank-modal-close');
    var btnCancel  = document.getElementById('admin-bank-modal-cancel');
    var btnCredit  = document.getElementById('admin-bank-credit-trigger');
    var btnDebit   = document.getElementById('admin-bank-debit-trigger');
    var inputAction  = document.getElementById('admin-bank-modal-action');
    var inputPid     = document.getElementById('admin-bank-modal-player-id');
    var elPlayer     = document.getElementById('admin-bank-modal-player');
    var elTitle      = document.getElementById('admin-bank-modal-title');
    var elSubmit     = document.getElementById('admin-bank-modal-submit');
    var elIcon       = document.getElementById('admin-bank-modal-icon');

    if (!modal || !form) return;

    // SVG dla kredytu i debetu (brak emoji) / SVG for credit and debit (no emoji)
    var svgCredit = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#4ec97a" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>';
    var svgDebit  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#e05555" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>';

    function openModal(action, pid, email) {
        inputAction.value = action;
        inputPid.value    = pid;
        if (elPlayer) elPlayer.textContent = '#' + pid + ' ' + email;
        if (action === 'admin_credit') {
            if (elTitle)  elTitle.textContent  = 'Dodaj srodki';
            if (elSubmit) { elSubmit.textContent = 'Dodaj srodki'; elSubmit.className = 'btn btn-success'; }
            if (elIcon)   elIcon.innerHTML = svgCredit;
        } else {
            if (elTitle)  elTitle.textContent  = 'Pobierz srodki';
            if (elSubmit) { elSubmit.textContent = 'Pobierz srodki'; elSubmit.className = 'btn btn-danger'; }
            if (elIcon)   elIcon.innerHTML = svgDebit;
        }
        // Wyczysc pola / Clear fields
        var amt  = document.getElementById('admin-bank-modal-amount');
        var note = document.getElementById('admin-bank-modal-note');
        if (amt)  amt.value  = '';
        if (note) note.value = '';
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        setTimeout(function () { if (amt) amt.focus(); }, 30);
    }

    function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    if (btnCredit) {
        btnCredit.addEventListener('click', function () {
            openModal('admin_credit', this.dataset.playerId, this.dataset.playerEmail);
        });
    }
    if (btnDebit) {
        btnDebit.addEventListener('click', function () {
            openModal('admin_debit', this.dataset.playerId, this.dataset.playerEmail);
        });
    }
    btnClose  && btnClose.addEventListener('click', closeModal);
    btnCancel && btnCancel.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') closeModal();
    });
})();
</script>
