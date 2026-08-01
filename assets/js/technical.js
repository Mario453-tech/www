/**
 * technical.js - Dzial Techniczny OilCorp
 */

var _TECHL = window.TECH_LANG || {};

// Licznik aktywnych zadан sieciowych — zapobiega odswiezeniu podczas in-flight fetch.
// Counter of in-flight network requests — prevents auto-refresh while a fetch is pending.
var _pendingFetches = 0;

function techl(key) {
    return _TECHL[key] || key;
}

function updateCountdowns() {
    document.querySelectorAll('.countdown[data-end]').forEach((el) => {
        const end = parseInt(el.dataset.end, 10) * 1000;
        const now = Date.now();
        const sec = Math.max(0, Math.round((end - now) / 1000));

        if (sec === 0) {
            el.textContent = techl('ready');
            el.style.color = '#4ec97a';
            return;
        }

        const h = Math.floor(sec / 3600);
        const m = Math.floor((sec % 3600) / 60);
        const s = sec % 60;
        el.textContent = h > 0 ? `${h}h ${m}m` : `${m}m ${s}s`;
    });
}

updateCountdowns();
setInterval(updateCountdowns, 1000);

async function dismissAllNotifs() {
    const panel = document.getElementById('notif-panel');
    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const fd = new FormData();
    fd.append('action', 'mark_all_read');
    fd.append('_token', token);

    const btn = document.querySelector('.notif-dismiss-all');
    if (btn) {
        btn.disabled = true;
        btn.textContent = '...';
    }

    // Zwieksz licznik przed fetch; zmniejsz w kazdym mozliwym scenariuszu zakonczenia.
    // Increment counter before fetch; decrement in every possible completion path.
    _pendingFetches++;
    let data = {};
    try {
        const res = await fetch('/src/TechNotifApi.php', { method: 'POST', body: fd });
        data = await res.json().catch(() => ({}));
    } finally {
        _pendingFetches--;
    }

    if (data.success && panel) {
        const rows = panel.querySelectorAll('.notif-row');
        rows.forEach((row, i) => {
            setTimeout(() => {
                row.style.transition = 'opacity .2s, transform .2s';
                row.style.opacity = '0';
                row.style.transform = 'translateX(20px)';
                setTimeout(() => row.remove(), 220);
            }, i * 30);
        });

        setTimeout(() => {
            panel.style.transition = 'opacity .3s';
            panel.style.opacity = '0';
            setTimeout(() => panel.remove(), 320);
        }, rows.length * 30 + 100);
    } else if (btn) {
        btn.disabled = false;
        btn.textContent = 'X Odznacz wszystkie';
    }
}

async function dismissNotif(id) {
    const el = document.getElementById('notif-' + id);
    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const fd = new FormData();
    fd.append('_token', token);
    fd.append('action', 'dismiss_notification');
    fd.append('notif_id', id);

    if (el) {
        el.style.opacity = '0.4';
    }

    // Zwieksz licznik przed fetch; zmniejsz w kazdym mozliwym scenariuszu zakonczenia.
    // Increment counter before fetch; decrement in every possible completion path.
    _pendingFetches++;
    let data = {};
    try {
        const res = await fetch(location.pathname, { method: 'POST', body: fd });
        data = await res.json().catch(() => ({}));
    } finally {
        _pendingFetches--;
    }

    if (data.success && el) {
        el.style.transition = 'all .3s';
        el.style.height = el.offsetHeight + 'px';
        el.style.overflow = 'hidden';
        requestAnimationFrame(() => {
            el.style.height = '0';
            el.style.padding = '0';
        });
        setTimeout(() => {
            el.remove();
            const panel = document.getElementById('notif-panel');
            if (panel && !panel.querySelector('.notif-row')) {
                panel.remove();
            }
        }, 350);
    }
}

function toggleWellSelect(sel, staffId) {
    const opt = sel.options[sel.selectedIndex];
    // Jesli brak zaznaczonej opcji (selectedIndex == -1), przerwij — opt bylby undefined.
    // If no option is selected (selectedIndex == -1), bail out — opt would be undefined.
    if (!opt) return;
    const needsWell = opt.dataset.needsWell === '1';
    const needsHub = opt.dataset.needsHub === '1';
    const needsMod = opt.dataset.needsModule === '1';
    const needsPipe = opt.dataset.needsPipeline === '1';
    const wDiv = document.getElementById('well-sel-' + staffId);
    const hDiv = document.getElementById('hub-sel-' + staffId);
    const mDiv = document.getElementById('mod-sel-' + staffId);
    const pDiv = document.getElementById('pipe-sel-' + staffId);

    if (wDiv) {
        wDiv.style.display = needsWell ? '' : 'none';
    }
    if (hDiv) {
        hDiv.style.display = needsHub ? '' : 'none';
    }
    if (mDiv) {
        mDiv.style.display = needsMod ? '' : 'none';
    }
    if (pDiv) {
        pDiv.style.display = needsPipe ? '' : 'none';
    }
}

// Show a strike-specific action without exposing a technical error.
// Pokaz akcje dla strajku bez ujawniania bledu technicznego.
function showTechnicalTaskError(message) {
    const lang = window.TECH_LANG || {};
    if (message === lang.strike_blocked_message && typeof window.alertWithActions === 'function') {
        window.alertWithActions(message, lang.strike_blocked_title, [
            {
                label: lang.strike_conflicts_label,
                cls: 'modal-btn--confirm',
                onClick: function () { window.location.assign(lang.strike_conflicts_url); }
            },
            { label: lang.strike_close_label, cls: 'modal-btn--secondary' }
        ], 'warning');
        return;
    }

    if (typeof window.alertError === 'function') {
        window.alertError(message);
    } else if (typeof window.showGameToast === 'function') {
        window.showGameToast(message, 'error');
    }
}

window.showTechnicalTaskError = showTechnicalTaskError;

// Confirm paid task assignment and submit it with AJAX.
// Potwierdz platne zadanie i wyslij je przez AJAX.
function techTaskConfirm(form) {
    const sel = form.querySelector('select[name="task_type"]');
    if (!sel) return true;

    const opt      = sel.options[sel.selectedIndex];
    if (!opt) return true; // no option selected — allow default form behavior / brak opcji — pozwol na domyslne zachowanie formularza
    const costMin  = parseInt(opt.dataset.costMin || '0', 10);
    const costMax  = parseInt(opt.dataset.costMax || '0', 10);
    const label    = opt.textContent.trim();
    const locale   = window.APP_LOCALE || 'pl-PL';
    const fmt      = (n) => n.toLocaleString(locale, { maximumFractionDigits: 0 });
    const btn      = form.querySelector('button[type="submit"]');
    const btnText  = btn ? btn.textContent : '';

    async function doSubmit() {
        if (btn) { btn.disabled = true; btn.textContent = '...'; }
        const fd = new FormData(form);
        // Zwieksz licznik przed fetch; zmniejsz w finally — tak jak w dismissAllNotifs i dismissNotif.
        // Increment counter before fetch; decrement in finally — consistent with dismissAllNotifs and dismissNotif.
        _pendingFetches++;
        try {
            const r    = await fetch(location.pathname, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
            });
            const data = await r.json().catch(function () { return {}; });
            const msg  = data.message || '';
            if (data.success) {
                const title = (window.TECH_LANG && window.TECH_LANG.task_result_title) || 'Zlecono';
                if (typeof window.alertInfo === 'function') {
                    window.alertInfo(msg, title, function () { location.reload(); });
                } else {
                    location.reload();
                }
            } else {
                if (btn) { btn.disabled = false; btn.textContent = btnText; }
                showTechnicalTaskError(msg);
            }
        } catch (_e) {
            location.reload();
        } finally {
            _pendingFetches--;
        }
    }

    if (costMin <= 0) {
        doSubmit();
        return false;
    }

    const costRange = fmt(costMin) + ' – ' + fmt(costMax) + ' zł';
    const confirmMsg = (window.TECH_LANG && window.TECH_LANG.confirm_assign_task)
        ? window.TECH_LANG.confirm_assign_task
              .replace(':task', label)
              .replace(':cost', costRange)
        : ('Przypisać zadanie?\n' + label + '\nSzacowany koszt: ' + costRange);

    window.confirmAction(confirmMsg, doSubmit, {
        title: (window.TECH_LANG && window.TECH_LANG.confirm_assign_title) || 'Potwierdź zadanie',
        type: 'confirm',
        confirmLabel: (window.TECH_LANG && window.TECH_LANG.confirm_assign_ok) || 'Przypisz',
    });
    return false;
}

// Validate and confirm candidate review before submit.
// Walidacja i potwierdzenie oceny kandydata przed wyslaniem formularza.
function candReviewConfirm(form) {
    var scoreEl = form.querySelector('input[name="technical_score"]:checked');
    var recEl   = form.querySelector('input[name="recommendation"]:checked');

    if (!scoreEl) {
        window.alertWarning(techl('review_val_no_score'));
        return false;
    }
    if (!recEl) {
        window.alertWarning(techl('review_val_no_rec'));
        return false;
    }

    var score   = scoreEl.value;
    var recVal  = recEl.value;
    var recLabel = recVal === 'hire' ? techl('rec_hire_label') : techl('rec_reject_label');
    var msg = techl('review_confirm_msg')
        .replace(':score', score)
        .replace(':rec', recLabel);

    window.confirmAction(msg, function () { form.submit(); }, {
        title: techl('review_confirm_title'),
        type: 'confirm',
        confirmLabel: techl('review_confirm_ok'),
    });
    return false;
}

// Auto-odswiezanie — pomijane gdy karta nieaktywna lub trwa zadanie sieciowe.
// Auto-refresh — skipped when tab is hidden or a fetch is in progress.
setInterval(() => {
    if (!document.hidden && _pendingFetches === 0) location.reload();
}, 60000);

// Inicjalizacja widocznosci selectorow dla kazdego formularza przypisania zadania.
// Initialise selector visibility for each task-assignment form on page load.
(function initTaskFormSelectors() {
    function run() {
        document.querySelectorAll('select[name="task_type"]').forEach(function (sel) {
            var staffIdInput = sel.closest('form') && sel.closest('form').querySelector('input[name="staff_id"]');
            var staffId = staffIdInput ? staffIdInput.value : '';
            if (staffId) {
                toggleWellSelect(sel, staffId);
            }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();

// Deep-link "Zleć naprawę u MNT" ze strony głównej (?repair_well=ID#tech-mnt):
// przewija do Inżyniera Utrzymania Ruchu, otwiera formularz zlecenia,
// wybiera odwiert i zadanie naprawcze.
(function initRepairDeepLink() {
    var params = new URLSearchParams(location.search);
    var repairWell = params.get('repair_well');
    if (!repairWell) {
        return;
    }

    function run() {
        var cards = document.querySelectorAll('.staff-card[data-spec="maintenance_engineer"]');

        if (!cards.length) {
            if (typeof window.alertWarning === 'function') {
                window.alertWarning(techl('repair_no_mnt'), techl('repair_no_mnt_title'));
            }
            return;
        }

 // Preferuj wolnego inżyniera (karta bez klasy busy ma formularz zlecenia).
        var target = null;
        cards.forEach(function (c) {
            if (!target && !c.classList.contains('busy')) {
                target = c;
            }
        });

        if (!target) {
 // Wszyscy zajęci — przewiń do pierwszego i poinformuj, że są zajęci.
            cards[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            cards[0].classList.add('staff-card--repair-target');
            if (typeof window.alertWarning === 'function') {
                window.alertWarning(techl('repair_mnt_busy'), techl('repair_no_mnt_title'));
            }
            return;
        }

 // Otwórz formularz zlecenia (details) i wstępnie ustaw odwiert + zadanie.
        var details = target.querySelector('details');
        if (details) {
            details.open = true;
        }

        var staffIdInput = target.querySelector('input[name="staff_id"]');
        var staffId      = staffIdInput ? staffIdInput.value : '';
        var taskSel      = target.querySelector('select[name="task_type"]');

        if (taskSel) {
            var hasRepair = Array.prototype.some.call(taskSel.options, function (o) {
                return o.value === 'well_repair';
            });
            if (hasRepair) {
                taskSel.value = 'well_repair';
            }
            if (typeof toggleWellSelect === 'function' && staffId) {
                toggleWellSelect(taskSel, staffId);
            }
        }

        var wellSel = target.querySelector('select[name="well_id"]');
        if (wellSel) {
            wellSel.value = repairWell;
        }

        target.classList.add('staff-card--repair-target');
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
