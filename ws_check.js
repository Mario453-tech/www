/* well_staff.js Personel Odwiertw (Operator + Technik) */

var _WSL = window.WS_LANG || {};
function wsl(k, p) {
    var s = _WSL[k] || k;
    if (p) Object.keys(p).forEach(function(pk) { s = s.replace(':' + pk, p[pk]); });
    return s;
}

let _wsCurrentWellId = null;
let _wsCurrentRole   = null;

// ASSIGN MODAL 
async function wsOpenAssign(wellId, role, wellName) {
    _wsCurrentWellId = wellId;
    _wsCurrentRole   = role;

    const roleLabel = role === 'operator' ? wsl('role_operator') : wsl('role_technician');
    document.getElementById('ws-modal-title').textContent = `Przypisz ${roleLabel}`;
    document.getElementById('ws-modal-sub').textContent   = wellName;
    document.getElementById('ws-modal-body').innerHTML    = '<div class="ws-loading">' + wsl('loading') + '</div>';
    document.getElementById('ws-modal').style.display     = 'flex';

    try {
        const res  = await fetch(`${WELL_STAFF_API}?action=get_available&role=${role}`);
        const data = await res.json();

        if (!data.success) {
            document.getElementById('ws-modal-body').innerHTML =
                '<div class="ws-error">' + wsl('err_prefix') + (data.error || data.message) + '</div>';
            return;
        }

        const staff = data.staff || [];
        if (staff.length === 0) {
            const roleReqs = role === 'operator'
                ? 'well_operator, drilling_engineer, production_engineer'
                : 'well_technician, maintenance_engineer, drilling_engineer';
            document.getElementById('ws-modal-body').innerHTML =
                '<div class="ws-empty-msg">'
                + wsl('no_staff') + '<br>'
                + '<small>' + wsl('req_specs', { specs: roleReqs }) + '</small><br>'
                + '<a href="/technical?tab=candidates" class="ws-link-gold">' + wsl('recruit_link') + '</a>'
                + '</div>';
            return;
        }

        document.getElementById('ws-modal-body').innerHTML = staff.map(s => {
            const isAssigned = s.assigned_well_id && s.assigned_well_id != wellId;
            const assignedInfo = isAssigned
                ? '<span class="ws-already-assigned">' + wsl('assigned_to', { name: s.assigned_well_name || '#' + s.assigned_well_id }) + '</span>'
                : '';
            return `
            <div class="ws-staff-card ${isAssigned ? 'ws-staff-card--busy' : ''}"
                 onclick="${isAssigned ? '' : `wsAssign(${s.id})`}"
                 style="${isAssigned ? 'opacity:.5;cursor:not-allowed' : 'cursor:pointer'}">
                <div class="ws-staff-info">
                    <div class="ws-staff-name">${s.first_name} ${s.last_name}</div>
                    <div class="ws-staff-meta">${s.spec_name} ${assignedInfo}</div>
                </div>
                <div class="ws-staff-right">
                    <div class="ws-skill-big">${s.skill_level}<span>/10</span></div>
                    <div class="ws-salary">${wsl('salary', { amount: Number(s.salary).toLocaleString(window.APP_LOCALE) })}</div>
                </div>
            </div>`;
        }).join('');

    } catch (e) {
        document.getElementById('ws-modal-body').innerHTML =
            '<div class="ws-error">' + wsl('err_connection', { msg: e.message }) + '</div>';
    }
}

async function wsAssign(staffId) {
    if (!_wsCurrentWellId || !_wsCurrentRole) return;

    const fd = new FormData();
    fd.append('action',   'assign');
    fd.append('_token',   WS_CSRF);
    fd.append('well_id',  _wsCurrentWellId);
    fd.append('staff_id', staffId);
    fd.append('role',     _wsCurrentRole);

    try {
        const res  = await fetch(WELL_STAFF_API, { method: 'POST', body: fd });
        const data = await res.json();
        wsCloseModal();
        if (data.success) {
            wsShowToast(' ' + data.message);
            setTimeout(() => location.reload(), 1000);
        } else {
            wsShowToast(' ' + (data.message || data.error), true);
        }
    } catch (e) {
        wsShowToast(' ' + wsl('err_connection', { msg: e.message }), true);
    }
}

async function wsUnassign(wellId, role, btn) {
    const roleLabel = role === 'operator' ? wsl('role_operator_of') : wsl('role_technician_of');
    const confirmed = await wsConfirm(wsl('confirm_unassign', { role: roleLabel }));
    if (!confirmed) return;

    btn.disabled = true;
    const fd = new FormData();
    fd.append('action',  'unassign');
    fd.append('_token',  WS_CSRF);
    fd.append('well_id', wellId);
    fd.append('role',    role);

    try {
        const res  = await fetch(WELL_STAFF_API, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            wsShowToast(' ' + data.message);
            setTimeout(() => location.reload(), 800);
        } else {
            wsShowToast(' ' + (data.message || data.error), true);
            btn.disabled = false;
        }
    } catch (e) {
        wsShowToast(' ' + wsl('err_prefix') + e.message, true);
        btn.disabled = false;
    }
}

function wsConfirm(message) {
    return new Promise(resolve => {
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay modal-visible';
        overlay.innerHTML = `
            <div class="modal-box modal-box--danger">
                <span class="modal-icon"></span>
                <div class="modal-title">${message}</div>
                <div class="modal-actions">
                    <button class="modal-btn modal-btn--cancel" id="ws-confirm-cancel">Anuluj</button>
                    <button class="modal-btn modal-btn--danger" id="ws-confirm-ok">Odepnij</button>
                </div>
            </div>`;
        document.body.appendChild(overlay);
        const cleanup = (result) => { overlay.remove(); resolve(result); };
        overlay.querySelector('#ws-confirm-ok').onclick     = () => cleanup(true);
        overlay.querySelector('#ws-confirm-cancel').onclick = () => cleanup(false);
        overlay.onclick = (e) => { if (e.target === overlay) cleanup(false); };
    });
}

function wsCloseModal() {
    document.getElementById('ws-modal').style.display = 'none';
    _wsCurrentWellId = null;
    _wsCurrentRole   = null;
}

function wsShowToast(msg, isError = false) {
    const t = document.createElement('div');
    t.style.cssText = [
        'position:fixed', 'bottom:24px', 'right:24px', 'z-index:9999',
        'background:#1a1a24', 'border-radius:8px', 'padding:14px 20px',
        'font-size:13px', 'font-weight:600', 'max-width:360px',
        `border:1px solid ${isError ? 'rgba(224,85,85,.5)' : 'rgba(200,168,75,.4)'}`,
        `color:${isError ? '#e05555' : '#c8a84b'}`,
        'cursor:pointer',
    ].join(';');
    t.textContent = msg;
    t.onclick = () => t.remove();
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}
