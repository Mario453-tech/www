/**
 * technical.js - Dzial Techniczny OilCorp
 */

var _TECHL = window.TECH_LANG || {};
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

    const res = await fetch('/src/TechNotifApi.php', { method: 'POST', body: fd });
    const data = await res.json().catch(() => ({}));

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

    const res = await fetch(location.pathname, { method: 'POST', body: fd });
    const data = await res.json().catch(() => ({}));

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
    const needsWell = opt.dataset.needsWell === '1';
    const needsHub = opt.dataset.needsHub === '1';
    const needsMod = opt.dataset.needsModule === '1';
    const wDiv = document.getElementById('well-sel-' + staffId);
    const hDiv = document.getElementById('hub-sel-' + staffId);
    const mDiv = document.getElementById('mod-sel-' + staffId);

    if (wDiv) {
        wDiv.style.display = needsWell ? '' : 'none';
    }
    if (hDiv) {
        hDiv.style.display = needsHub ? '' : 'none';
    }
    if (mDiv) {
        mDiv.style.display = needsMod ? '' : 'none';
    }
}

// Confirm dialog for paid task assignment.
// Shows cost range when task has cost > 0.
function techTaskConfirm(form) {
    const sel = form.querySelector('select[name="task_type"]');
    if (!sel) return true;

    const opt      = sel.options[sel.selectedIndex];
    const costMin  = parseInt(opt.dataset.costMin || '0', 10);
    const costMax  = parseInt(opt.dataset.costMax || '0', 10);
    const label    = opt.textContent.trim();
    const locale   = window.APP_LOCALE || 'pl-PL';
    const fmt      = (n) => n.toLocaleString(locale, { maximumFractionDigits: 0 });

    const btn = form.querySelector('button[type="submit"]');

    // Disable submit button to prevent double-submit on free tasks.
    // Blokada przycisku po kliknieciu - zapobiega wielokrotnemu wyslaniu.
    if (costMin <= 0) {
        if (btn) { btn.disabled = true; btn.textContent = '...'; }
        return true;
    }

    const costRange = fmt(costMin) + ' – ' + fmt(costMax) + ' zł';
    const msg = (window.TECH_LANG && window.TECH_LANG.confirm_assign_task)
        ? window.TECH_LANG.confirm_assign_task
              .replace(':task', label)
              .replace(':cost', costRange)
        : ('Przypisać zadanie?\n' + label + '\nSzacowany koszt: ' + costRange);

    if (typeof window.confirmAction === 'function') {
        window.confirmAction(msg, function () {
            if (btn) { btn.disabled = true; btn.textContent = '...'; }
            form.submit();
        }, {
            title: (window.TECH_LANG && window.TECH_LANG.confirm_assign_title) || 'Potwierdź zadanie',
            type: 'confirm',
            confirmLabel: (window.TECH_LANG && window.TECH_LANG.confirm_assign_ok) || 'Przypisz'
        });
        return false; // block native submit - confirmAction calls form.submit()
    }

    return window.confirm(msg);
}

// Validate and confirm candidate review before submit.
// Walidacja i potwierdzenie oceny kandydata przed wyslaniem formularza.
function candReviewConfirm(form) {
    var scoreEl = form.querySelector('input[name="technical_score"]:checked');
    var recEl   = form.querySelector('input[name="recommendation"]:checked');

    if (!scoreEl) {
        alert(techl('review_val_no_score'));
        return false;
    }
    if (!recEl) {
        alert(techl('review_val_no_rec'));
        return false;
    }

    var score   = scoreEl.value;
    var recVal  = recEl.value;
    var recLabel = recVal === 'hire' ? techl('rec_hire_label') : techl('rec_reject_label');
    var msg = techl('review_confirm_msg')
        .replace(':score', score)
        .replace(':rec', recLabel);

    if (typeof window.confirmAction === 'function') {
        window.confirmAction(msg, function () { form.submit(); }, {
            title: techl('review_confirm_title'),
            type: 'confirm',
            confirmLabel: techl('review_confirm_ok'),
        });
        return false;
    }

    return window.confirm(msg);
}

setTimeout(() => location.reload(), 60000);
