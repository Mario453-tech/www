/**
 * logistics_hubs.js obsuga moduu hubw logistycznych.
 * Zaley od: HUB_API, HUB_CSRF, HUB_LANG (zdefiniowane w templates/views/logistics/main.php)
 */
(function () {
    'use strict';

    const api  = () => window.HUB_API  || '/src/HubApi.php';
    const csrf = () => window.HUB_CSRF || '';
    const lang = () => window.HUB_LANG || {};

    function successToast(msg, delay = 900) {
        if (typeof window.showGameToast === 'function') {
            window.showGameToast(msg, 'success');
        }
        return new Promise((resolve) => setTimeout(resolve, delay));
    }

    function infoAlert(msg) {
        if (typeof window.alertInfo === 'function') {
            window.alertInfo(msg, lang().title_info || 'Informacja');
            return;
        }
        window.alert(msg);
    }

    function errorAlert(msg) {
        if (typeof window.alertError === 'function') {
            window.alertError(msg, lang().title_error || 'Bd');
            return;
        }
        window.alert(msg);
    }

    function warningAlert(msg) {
        if (typeof window.alertWarning === 'function') {
            window.alertWarning(msg, lang().title_warning || 'Uwaga');
            return;
        }
        window.alert(msg);
    }

 // Modal braku zezwolenia na prace lokalne (3 przyciski) / Local permit required modal (3 buttons)
    function hubPermitModal(msg) {
        const l = lang();
        const url = l.permit_url || '/legal.php';
        if (typeof window.alertWithActions === 'function') {
            window.alertWithActions(
                msg,
                l.permit_modal_title || 'Brak zezwolenia na prace lokalne',
                [
                    {
                        label:   l.permit_btn_cancel || 'Anuluj',
                        cls:     'modal-btn--cancel',
                        onClick: null,
                    },
                    {
                        label:   l.permit_btn_apply || 'Zloz wniosek',
                        cls:     'modal-btn--confirm',
                        onClick: function () { window.location.href = url; },
                    },
                    {
                        label:   l.permit_btn_legal || 'Dzial prawny',
                        cls:     'modal-btn--secondary',
                        onClick: function () { window.location.href = url; },
                    },
                ],
                'warning'
            );
        } else {
            errorAlert(msg);
        }
    }
    window.hubPermitModal = hubPermitModal;

    function hubDialog(msg, type = 'info') {
        if (type === 'success') {
            return successToast(msg);
        }
        if (type === 'warning') {
            warningAlert(msg);
            return Promise.resolve(true);
        }
        if (type === 'error') {
            errorAlert(msg);
            return Promise.resolve(true);
        }
        infoAlert(msg);
        return Promise.resolve(true);
    }

    function getOwnedHubCard(hubId) {
        return document.querySelector(`.logistics-hub-card[data-hub-id="${hubId}"]`);
    }

    function hubConfirm(msg, options = {}) {
        return new Promise((resolve) => {
            if (typeof window.confirmAction !== 'function') {
                resolve(window.confirm(msg));
                return;
            }

            let resolved = false;
            let cleaned = false;
            let observer = null;

            const cleanup = () => {
                if (cleaned) {
                    return;
                }
                cleaned = true;
                if (observer) {
                    observer.disconnect();
                }
                const liveOverlay = document.getElementById('app-modal');
                const liveCancelBtn = liveOverlay ? liveOverlay.querySelector('.modal-btn--cancel') : null;
                if (liveCancelBtn) {
                    liveCancelBtn.removeEventListener('click', onCancel);
                }
            };

            const onCancel = () => {
                if (resolved) {
                    return;
                }
                resolved = true;
                cleanup();
                resolve(false);
            };

            window.confirmAction(msg, function () {
                if (resolved) {
                    return;
                }
                resolved = true;
                cleanup();
                resolve(true);
            }, {
                title: options.title || lang().confirm_title || 'Potwierd akcj',
                type: options.type || 'confirm',
                confirmLabel: options.confirmLabel || lang().confirm_label || 'Potwierd'
            });

            const modalOverlay = document.getElementById('app-modal');
            const modalCancelBtn = modalOverlay ? modalOverlay.querySelector('.modal-btn--cancel') : null;
            observer = new MutationObserver(() => {
                if (!resolved && modalOverlay && !modalOverlay.classList.contains('modal-visible')) {
                    resolved = true;
                    cleanup();
                    resolve(false);
                }
            });

            if (modalOverlay) {
                observer.observe(modalOverlay, { attributes: true, attributeFilter: ['class'] });
            }
            if (modalCancelBtn) {
                modalCancelBtn.addEventListener('click', onCancel, { once: true });
            }
        });
    }

 // Helpers 

    function closeHubModal(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    }
    window.closeHubModal = closeHubModal;

    function openHubModal(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'flex';
    }

    async function hubPost(action, body = {}) {
        const form = new FormData();
        form.append('action', action);
        form.append('_token', csrf());
        Object.entries(body).forEach(([k, v]) => form.append(k, v));
        const r = await fetch(api(), { method: 'POST', body: form });
        return r.json();
    }

    async function hubGet(action, params = {}) {
        const qs = new URLSearchParams({ action, ...params }).toString();
        const r  = await fetch(api() + '?' + qs);
        return r.json();
    }

    function reloadAfterAction(delay = 0) {
        setTimeout(() => window.location.reload(), delay);
    }

 // Budowa huba 

    window.hubBuildModal = function () {
        openHubModal('hub-build-modal');
    };

    window.hubBuildTypeChange = function (radio) {
        document.querySelectorAll('.logistics-mode-card').forEach(el => el.classList.remove('selected'));
        radio.closest('.logistics-mode-card').classList.add('selected');
    };

    window.hubBuildSubmit = async function (e) {
        e.preventDefault();
        const form = document.getElementById('hub-build-form');
        const data = Object.fromEntries(new FormData(form));
        const btn  = document.getElementById('hub-build-submit');
        btn.disabled = true;
        try {
            const res = await hubPost('build_hub', data);
            if (res.success) {
                closeHubModal('hub-build-modal');
                await hubDialog(res.message || lang().ok_build, 'success');
                reloadAfterAction();
            } else {
                hubDialog(res.error || lang().err_generic, 'error');
            }
        } catch (err) {
            hubDialog(lang().err_generic, 'error');
        } finally {
            btn.disabled = false;
        }
    };

 // Naprawa huba / Hub repair

    window.hubRepair = async function (hubId) {
        const card = getOwnedHubCard(hubId);
        const cost = card ? card.dataset.repairCost : '?';
        const msg  = (lang().repair_confirm || 'Naprawi hub za {cost} PLN?').replace('{cost}', Number(cost).toLocaleString('pl'));
        if (!await hubConfirm(msg)) return;

        try {
            const res = await hubPost('repair_hub', { hub_id: hubId });
            if (res.success) {
                await hubDialog(res.message || lang().ok_repair, 'success');
                reloadAfterAction();
            } else {
                hubDialog(res.error || lang().err_generic, 'error');
            }
        } catch (err) {
            hubDialog(lang().err_generic, 'error');
        }
    };

 // Rozbudowa huba / Hub upgrade

    window.hubUpgrade = async function (hubId) {
        const card = getOwnedHubCard(hubId);
        const cost = card ? Number(card.dataset.upgradeCost || 0) : 0;
        const msg  = (lang().upgrade_confirm || 'Rozbudować hub za {cost} PLN?').replace('{cost}', Number(cost).toLocaleString('pl'));
        if (!await hubConfirm(msg)) return;

        try {
            const res = await hubPost('upgrade_hub', { hub_id: hubId });
            if (res.success) {
                const okMsg = (lang().ok_upgrade || 'Hub rozbudowany do poziomu {level}.').replace('{level}', res.new_level || '?');
                await hubDialog(okMsg, 'success');
                reloadAfterAction();
            } else {
                hubDialog(res.error || lang().err_generic, 'error');
            }
        } catch (err) {
            hubDialog(lang().err_generic, 'error');
        }
    };

 // Tryb pracy 

    window.hubSetMode = async function (hubId, mode) {
        try {
            const res = await hubPost('set_mode', { hub_id: hubId, mode });
            if (res.success) {
                const modeLabel = lang()['mode_' + mode] || mode;
                const msg = (lang().ok_mode || ' Tryb: {mode}.').replace('{mode}', modeLabel);
                await hubDialog(msg, 'success');
                reloadAfterAction();
            } else {
                hubDialog(res.error || lang().err_generic, 'error');
            }
        } catch (err) {
            hubDialog(lang().err_generic, 'error');
        }
    };

 // Pause / Resume 

    window.hubTogglePause = async function (hubId, isPaused) {
        try {
            const res = await hubPost('toggle_pause', { hub_id: hubId });
            if (res.success) {
                const msg = isPaused ? (lang().ok_resume || ' Wznowiono.') : (lang().ok_pause || ' Wstrzymano.');
                await hubDialog(msg, 'success');
                reloadAfterAction();
            } else {
                hubDialog(res.error || lang().err_generic, 'error');
            }
        } catch (err) {
            hubDialog(lang().err_generic, 'error');
        }
    };

 // Odwierty huba (modal) 

    window.hubWellsModal = async function (hubId) {
        const body  = document.getElementById('hub-wells-modal-body');
        const title = document.getElementById('hub-wells-modal-title');
        body.innerHTML = '<div class="logistics-loading">' + (lang().loading || 'adowanie...') + '</div>';
        openHubModal('hub-wells-modal');

        try {
            const res = await hubGet('hub_wells', { hub_id: hubId });
            if (!res.success) {
                body.innerHTML = `<div class="logistics-alert logistics-alert--danger">${esc(res.error || lang().err_generic)}</div>`;
                return;
            }

            if (res.hub && title) {
                title.textContent = ' ' + res.hub.name;
            }

            const wells = res.wells || [];
            if (wells.length === 0) {
                body.innerHTML = `<div class="logistics-empty">${lang().wells_none || 'Brak przypisanych odwiertw.'}</div>`;
                return;
            }

            let html = `<div class="logistics-table">
                <div class="logistics-table-head">
                    <span>${lang().col_well}</span>
                    <span>${lang().col_region}</span>
                    <span>${lang().col_prod}</span>
                    <span>${lang().col_status}</span>
                    <span>${lang().col_actions}</span>
                </div>`;
            for (const w of wells) {
                html += `<div class="logistics-table-row">
                    <span>#${w.id} ${esc(w.name || w.location_name || '')}</span>
                    <span>${esc(w.region_name || '')}${w.zone_key ? ' / ' + esc(w.zone_key) : ''}</span>
                    <span>${parseFloat(w.base_production_per_hour || 0).toFixed(1)}</span>
                    <span>${esc(lang()['ws_' + w.status] || w.status || '')}</span>
                    <span style="display:flex;gap:.35rem;flex-wrap:wrap">
                        <button class="btn btn-xs btn-warn" onclick="hubDetachWell(${w.id}, ${hubId})">
                            ${lang().btn_detach || 'Odepnij'}
                        </button>
                        <button class="btn btn-xs btn-secondary" onclick="hubTransferModal(${w.id}, ${hubId})">
                             ${lang().btn_transfer || 'Przenie'}
                        </button>
                    </span>
                </div>`;
            }
            html += '</div>';
            body.innerHTML = html;
        } catch (err) {
            console.error('[HUB] hubWellsModal error:', err);
            body.innerHTML = `<div class="logistics-alert logistics-alert--danger">${lang().err_generic || 'Bd adowania.'}</div>`;
        }
    };

    window.hubDetachWell = async function (wellId, hubId) {
        const msg = (lang().detach_confirm || 'Odpi odwiert #:id?').replace('{id}', wellId);
        if (!await hubConfirm(msg)) return;

        try {
            const res = await hubPost('detach_well', { well_id: wellId });
            if (res.success) {
                closeHubModal('hub-wells-modal');
                await hubDialog(res.message || lang().ok_detach, 'success');
                reloadAfterAction();
            } else {
                hubDialog(res.error || lang().err_generic, 'error');
            }
        } catch (err) {
            hubDialog(lang().err_generic, 'error');
        }
    };

 // Przypisz odwiert do huba (modal) 

    window.hubAssignWellToHubModal = async function (hubId) {
        const body = document.getElementById('hub-assign-modal-body');
        const titleEl = document.querySelector('#hub-assign-modal .logistics-modal-hdr span');
        const card = getOwnedHubCard(hubId);
        const hubName  = card ? (card.dataset.hubName  || '') : '';
        const regionId = card ? Number(card.dataset.hubRegionId || 0) : 0;
        const zoneKey  = card ? String(card.dataset.hubZoneKey  || '') : '';
        const hubAcqType  = card ? String(card.dataset.hubAcqType  || 'new') : 'new';
        const hubLeaseFee = card ? parseFloat(card.dataset.hubLeaseFee || 0) : 0;

        if (titleEl) {
            titleEl.textContent = ' ' + (lang().assign_well_title || 'Przypisz odwiert bez huba') + (hubName ? ': ' + hubName : '');
        }

        body.innerHTML = '<div class="logistics-loading">' + (lang().loading || 'adowanie...') + '</div>';
        openHubModal('hub-assign-modal');

        try {
            const res = await hubGet('unassigned_wells');
            if (!res.success) {
                body.innerHTML = `<div class="logistics-alert logistics-alert--danger">${esc(res.error || lang().err_generic)}</div>`;
                return;
            }

            const wells = (res.wells || [])
                .filter(w => !regionId || Number(w.region_id) === regionId)
                .sort((a, b) => {
                    const aSameZone = zoneKey !== '' && String(a.zone_key || '') === zoneKey ? 1 : 0;
                    const bSameZone = zoneKey !== '' && String(b.zone_key || '') === zoneKey ? 1 : 0;
                    if (aSameZone !== bSameZone) return bSameZone - aSameZone;
                    return Number(a.id) - Number(b.id);
                });

            if (!wells.length) {
                body.innerHTML = `<div class="logistics-empty">${esc(lang().assign_well_none || 'Brak odwiertw bez huba, ktre moesz przypisa do tego huba.')}</div>`;
                return;
            }

            let html = `<div class="logistics-table">
                <div class="logistics-table-head">
                    <span>${lang().col_well}</span>
                    <span>${lang().col_region}</span>
                    <span>${lang().col_prod}</span>
                    <span>${lang().col_actions}</span>
                </div>`;

            for (const w of wells) {
                const zoneLabel = zoneKey !== '' && String(w.zone_key || '') === zoneKey
                    ? ' <span class="c-good"> ' + esc(zoneKey) + '</span>'
                    : '';
                html += `<div class="logistics-table-row">
                    <span>#${w.id} ${esc(w.name || w.location_name || '')}</span>
                    <span>${esc(w.region_name || '')}${w.zone_key ? ' / ' + esc(w.zone_key) : ''}${zoneLabel}</span>
                    <span>${parseFloat(w.base_production_per_hour || 0).toFixed(1)} bph</span>
                    <span>
                        <button class="btn btn-xs btn-primary"
                                onclick="hubDoAssign(${w.id}, ${hubId}, 0)"
                                data-acq-type="${esc(hubAcqType)}"
                                data-acq-lease-fee="${hubLeaseFee}">
                            ${lang().btn_assign_well || lang().btn_assign || 'Przypisz'}
                        </button>
                    </span>
                </div>`;
            }

            html += '</div>';
            body.innerHTML = html;
        } catch (err) {
            console.error('[HUB] hubAssignWellToHubModal error:', err);
            body.innerHTML = `<div class="logistics-alert logistics-alert--danger">${lang().err_generic || 'Bd adowania.'}</div>`;
        }
    };

    window.hubAssignModal = async function (wellId, page = 1) {
        const body = document.getElementById('hub-assign-modal-body');
        const titleEl = document.querySelector('#hub-assign-modal .logistics-modal-hdr span');
        if (titleEl) {
            titleEl.textContent = ' ' + (lang().avail_title || 'Dostpne huby w regionie');
        }
        body.innerHTML = '<div class="logistics-loading">' + (lang().loading || 'adowanie...') + '</div>';
        openHubModal('hub-assign-modal');

        try {
            const res = await hubGet('assignable_hubs', { well_id: wellId, page: page });
            if (!res.success) {
                body.innerHTML = `<div class="logistics-alert logistics-alert--danger">${esc(res.error || lang().err_generic)}</div>`;
                return;
            }

            const hubs = res.hubs || [];
            const currentPage = res.page || 1;
            const totalPages = res.totalPages || 1;
            const total = res.total || 0;

            if (hubs.length === 0) {
                body.innerHTML = `<div class="logistics-empty">${lang().avail_none || 'Brak dostpnych hubw.'}</div>`;
                return;
            }

            let html = `<div class="logistics-hub-assign-list">`;
            for (const entry of hubs) {
                const h        = entry.hub;
                const condPct  = parseFloat(h.condition_pct || 100);
                const fee      = parseFloat(entry.usage_fee || 0);
                const condMult = parseFloat(entry.cond_mult || 1);
                const slotsLabel = (lang().avail_slots || '{free}/{total} slotw')
                    .replace('{free}', entry.slots_avail)
                    .replace('{total}', h.slot_limit);
                const feeLabel = `<span class="hub-assign-fee">${(lang().avail_fee || 'Koszt: {fee} PLN/tick').replace('{fee}', fee.toLocaleString('pl'))}</span>`;
                const condPenLabel = condMult > 1.00
                    ? `<span class="c-warn"> ${condMult.toFixed(2)}</span>`
                    : '';
                const penLabel = entry.zone_penalty > 0
                    ? `<span class="c-warn">  strefa +${entry.zone_penalty}%</span>`
                    : '';
                const condWarn = condPct <= 20
                    ? `<span class="c-bad">  ${lang().cond_critical_short || 'Stan krytyczny'}</span>`
                    : condPct <= 40
                        ? `<span class="c-warn">  ${lang().cond_low_short || 'Zy stan'}</span>`
                        : '';

 // Acquisition type badge + economic breakdown
                const acqType     = entry.acq_type || h.acquisition_type || 'new';
                const acqLabel    = lang()['acq_' + acqType] || acqType;
                const wearMult    = parseFloat(entry.acq_wear_mult || 1);
                const riskMult    = parseFloat(entry.acq_risk_mult || 1);
                const opexMult    = parseFloat(entry.acq_opex_mult || 1);
                const startMin    = parseInt(entry.acq_start_min || 0, 10);
                const startMax    = parseInt(entry.acq_start_max || 100, 10);
                const leaseFee    = parseFloat(entry.acq_lease_fee || 0);
                const wearClass   = wearMult > 1.2 ? 'c-bad' : wearMult > 1.0 ? 'c-warn' : 'c-good';
                const riskClass   = riskMult > 1.3 ? 'c-bad' : riskMult > 1.0 ? 'c-warn' : 'c-good';
                const opexClass   = opexMult > 1.1 ? 'c-bad' : opexMult > 1.0 ? 'c-warn' : 'c-good';
                const acqBreakdown = `<div class="hub-acq-breakdown">
                    <span class="hub-acq-badge hub-acq-badge--${esc(acqType)}">${esc(acqLabel)}</span>
                    <span class="${wearClass}" title="${lang().acq_wear || 'Zuycie'}"> ${wearMult.toFixed(2)}</span>
                    <span class="${riskClass}" title="${lang().acq_risk || 'Ryzyko awarii'}"> ${riskMult.toFixed(2)}</span>
                    <span class="${opexClass}" title="${lang().acq_opex || 'Koszt utrzymania huba'}"> ${opexMult.toFixed(2)}</span>
                    <span title="${lang().acq_start_cond || 'Stan startowy'}"> ${startMin}${startMax}%</span>
                    ${leaseFee > 0 ? `<span class="c-warn" title="${lang().acq_lease || 'Czynsz/tick'}"> ${leaseFee.toLocaleString('pl')} PLN/tick</span>` : ''}
                </div>`;

                html += `<div class="logistics-hub-assign-row">
                    <div>
                        <strong>${esc(h.name)}</strong>
                        <span class="badge ${esc(entry.status_class)}">${esc(h.status)}</span>
                        ${acqBreakdown}
                        <small>${feeLabel}${condPenLabel}${penLabel}${condWarn} &nbsp;${slotsLabel}</small>
                    </div>
                    <button class="btn btn-sm btn-primary"
                            onclick="hubDoAssign(${wellId}, ${h.id}, ${fee})"
                            data-cond-warn="${esc(condWarn ? (condPct <= 20 ? 'critical' : 'low') : '')}"
                            data-acq-type="${esc(acqType)}"
                            data-acq-lease-fee="${leaseFee}"
                            data-acq-access-fee="${parseFloat(entry.acq_access_fee || 0)}"
                            ${entry.slots_full ? 'disabled' : ''}>
                        ${lang().btn_assign || 'Przypisz'}
                    </button>
                </div>`;
            }
            html += '</div>';

 // Paginacja
            if (totalPages > 1) {
                html += `<div class="logistics-pagination logistics-pagination--modal">`;
                html += `<div class="logistics-pagination-info">${currentPage} / ${totalPages} (${total})</div>`;
                html += `<div class="logistics-pagination-buttons">`;
                if (currentPage > 1) {
                    html += `<button class="btn btn-xs btn-secondary" onclick="hubAssignModal(${wellId}, ${currentPage - 1})"> ${lang().pagination_prev || 'Poprzednia'}</button>`;
                }
                if (currentPage < totalPages) {
                    html += `<button class="btn btn-xs btn-secondary" onclick="hubAssignModal(${wellId}, ${currentPage + 1})">${lang().pagination_next || 'Nastpna'} </button>`;
                }
                html += `</div></div>`;
            }

            body.innerHTML = html;
        } catch (err) {
            console.error('[HUB] hubAssignModal error:', err);
            body.innerHTML = `<div class="logistics-alert logistics-alert--danger">${lang().err_generic || 'Bd adowania. Odwie stron.'}</div>`;
        }
    };

    window.hubDoAssign = async function (wellId, hubId, fee = 0) {
        const btn = document.querySelector(`[onclick*="hubDoAssign(${wellId}, ${hubId},"]`) ||
                    document.querySelector(`[onclick*="hubDoAssign(${wellId}, ${hubId})"]`);
        const condWarnType = btn ? btn.dataset.condWarn    : '';
        const acqType      = btn ? (btn.dataset.acqType    || 'new') : 'new';
        const leaseFee     = btn ? parseFloat(btn.dataset.acqLeaseFee  || 0) : 0;
        const accessFee    = btn ? parseFloat(btn.dataset.acqAccessFee || 0) : 0;
        const fmt          = (v) => v.toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

 // 1. Ostrzezenie kondycji (stan krytyczny / zly) / Condition warning
        if (condWarnType) {
            const warnMsg = condWarnType === 'critical'
                ? (lang().warn_condition_critical || 'Hub w stanie krytycznym! Wysokie koszty i ryzyko strat.')
                : (lang().warn_condition_low      || 'Hub w zlym stanie. Podwyzszone koszty.');
            if (!await hubConfirm(warnMsg)) return;
        }

 // 2. Ujednolicone potwierdzenie kosztow dla wszystkich typow / Unified cost confirmation
        const hasAnyCost = accessFee > 0 || leaseFee > 0 || fee > 0;
        if (hasAnyCost) {
            const acqLabel  = lang()['acq_' + acqType] || acqType;
            const perTick   = lang().confirm_per_tick || 'PLN/tick';
            let lines = [];
            lines.push(lang().confirm_assign_costs || 'Podsumowanie kosztow przypisania:');
            lines.push('▪ ' + acqLabel.toUpperCase());
            if (accessFee > 0) {
                lines.push((lang().confirm_access_fee || 'Oplata przylaczeniowa (jednorazowo') + ': ' + fmt(accessFee) + ' PLN');
            }
            if (fee > 0) {
                lines.push((lang().confirm_usage_fee || 'Koszt uzytkowania slotu') + ': ' + fmt(fee) + ' ' + perTick);
            }
            if (leaseFee > 0) {
                lines.push((lang().confirm_lease_fee || 'Czynsz najmu (wynajem)') + ': ' + fmt(leaseFee) + ' ' + perTick);
            }
            lines.push('');
            lines.push(lang().confirm_question || 'Czy potwierdzasz przypisanie odwiertu do tego huba?');
            if (!await hubConfirm(lines.join('\n'))) return;
        }

        try {
            const res = await hubPost('assign_well', { well_id: wellId, hub_id: hubId });
            if (res.success) {
                closeHubModal('hub-assign-modal');
                const paidFee = parseFloat(res.access_fee_paid || 0);
                let successMsg;
                if (paidFee > 0) {
                    successMsg = (lang().ok_assign_with_fee || 'Odwiert przypisany. Oplata przylaczeniowa: {fee} PLN.')
                        .replace('{fee}', fmt(paidFee));
                } else if (leaseFee > 0) {
                    successMsg = lang().ok_assign_with_lease || 'Odwiert przypisany (wynajem aktywny).';
                } else {
                    successMsg = res.message || lang().ok_assign || 'Odwiert przypisany.';
                }
                await hubDialog(successMsg, 'success');
                reloadAfterAction();
            } else if (res.error_code === 'no_hub_permit') {
                hubPermitModal(res.error || lang().err_generic);
            } else {
                hubDialog(res.error || lang().err_generic, 'error');
            }
        } catch (err) {
            hubDialog(lang().err_generic, 'error');
        }
    };

 // Transfer odwiertu midzy hubami (modal) 

    window.hubTransferModal = async function (wellId, currentHubId) {
        const body = document.getElementById('hub-transfer-modal-body');
        body.innerHTML = '<div class="logistics-loading">' + (lang().loading || 'adowanie...') + '</div>';
        closeHubModal('hub-wells-modal');
        openHubModal('hub-transfer-modal');

        try {
            const res = await hubGet('assignable_hubs', { well_id: wellId });
            if (!res.success) {
                body.innerHTML = `<div class="logistics-alert logistics-alert--danger">${esc(res.error || lang().err_generic)}</div>`;
                return;
            }

            const hubs = (res.hubs || []).filter(entry => Number(entry.hub.id) !== Number(currentHubId));

            if (hubs.length === 0) {
                body.innerHTML = `<div class="logistics-empty">${lang().transfer_none || 'Brak innych dostpnych hubw do transferu.'}</div>`;
                return;
            }

            let html = `<div class="logistics-hub-assign-list">`;
            for (const entry of hubs) {
                const h        = entry.hub;
                const condPct  = parseFloat(h.condition_pct || 100);
                const fee      = parseFloat(entry.usage_fee || 0);
                const condMult = parseFloat(entry.cond_mult || 1);
                const slotsLabel = (lang().avail_slots || '{free}/{total} slotw')
                    .replace('{free}', entry.slots_avail)
                    .replace('{total}', h.slot_limit);
                const feeLabel = `<span class="hub-assign-fee">${(lang().avail_fee || 'Koszt: {fee} PLN/tick').replace('{fee}', fee.toLocaleString('pl'))}</span>`;
                const condPenLabel = condMult > 1.00
                    ? `<span class="c-warn"> ${condMult.toFixed(2)}</span>`
                    : '';
                const penLabel = entry.zone_penalty > 0
                    ? `<span class="c-warn">  strefa +${entry.zone_penalty}%</span>`
                    : '';
                const condWarn = condPct <= 20
                    ? `<span class="c-bad">  ${lang().cond_critical_short || 'Stan krytyczny'}</span>`
                    : condPct <= 40
                        ? `<span class="c-warn">  ${lang().cond_low_short || 'Zy stan'}</span>`
                        : '';

 // Acquisition type badge + breakdown (jak w hubAssignModal)
                const tAcqType  = entry.acq_type || h.acquisition_type || 'new';
                const tAcqLabel = lang()['acq_' + tAcqType] || tAcqType;
                const tWear     = parseFloat(entry.acq_wear_mult || 1);
                const tRisk     = parseFloat(entry.acq_risk_mult || 1);
                const tOpex     = parseFloat(entry.acq_opex_mult || 1);
                const tLease    = parseFloat(entry.acq_lease_fee || 0);
                const tStart    = `${entry.acq_start_min || 0}${entry.acq_start_max || 100}%`;
                const tBreakdown = `<div class="hub-acq-breakdown">
                    <span class="hub-acq-badge hub-acq-badge--${esc(tAcqType)}">${esc(tAcqLabel)}</span>
                    <span class="${tWear > 1.2 ? 'c-bad' : tWear > 1 ? 'c-warn' : 'c-good'}" title="${lang().acq_wear || 'Zuycie'}"> ${tWear.toFixed(2)}</span>
                    <span class="${tRisk > 1.3 ? 'c-bad' : tRisk > 1 ? 'c-warn' : 'c-good'}" title="${lang().acq_risk || 'Ryzyko'}"> ${tRisk.toFixed(2)}</span>
                    <span class="${tOpex > 1.1 ? 'c-bad' : tOpex > 1 ? 'c-warn' : 'c-good'}" title="${lang().acq_opex || 'Koszt utrzymania'}"> ${tOpex.toFixed(2)}</span>
                    <span title="${lang().acq_start_cond || 'Stan'}"> ${tStart}</span>
                    ${tLease > 0 ? `<span class="c-warn"> ${tLease.toLocaleString('pl', {minimumFractionDigits: 2})} PLN/tick</span>` : ''}
                </div>`;

                html += `<div class="logistics-hub-assign-row">
                    <div>
                        <strong>${esc(h.name)}</strong>
                        <span class="badge ${esc(entry.status_class)}">${esc(h.status)}</span>
                        ${tBreakdown}
                        <small>${feeLabel}${condPenLabel}${penLabel}${condWarn} &nbsp;${slotsLabel}</small>
                    </div>
                    <button class="btn btn-sm btn-primary"
                            onclick="hubDoTransfer(${wellId}, ${h.id})"
                            data-acq-type="${esc(tAcqType)}"
                            data-acq-lease-fee="${tLease}"
                            data-acq-access-fee="${parseFloat(entry.acq_access_fee || 0)}"
                            ${entry.slots_full ? 'disabled' : ''}>
                         ${lang().btn_transfer || 'Przenie'}
                    </button>
                </div>`;
            }
            html += '</div>';
            body.innerHTML = html;
        } catch (err) {
            console.error('[HUB] hubTransferModal error:', err);
            body.innerHTML = `<div class="logistics-alert logistics-alert--danger">${lang().err_generic || 'Bd adowania.'}</div>`;
        }
    };

    window.hubDoTransfer = async function (wellId, newHubId) {
        const btn       = document.querySelector(`[onclick*="hubDoTransfer(${wellId}, ${newHubId})"]`);
        const acqType   = btn ? (btn.dataset.acqType    || 'new') : 'new';
        const leaseFee  = btn ? parseFloat(btn.dataset.acqLeaseFee  || 0) : 0;
        const accessFee = btn ? parseFloat(btn.dataset.acqAccessFee || 0) : 0;
        const fmt       = (v) => v.toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

 // Ujednolicone potwierdzenie kosztow przy transferze / Unified cost confirmation on transfer
 // accessFee shown for info only - transfer does not charge a new connection fee
        const hasAnyCost = leaseFee > 0 || accessFee > 0;
        if (hasAnyCost) {
            const acqLabel = lang()['acq_' + acqType] || acqType;
            const perTick  = lang().confirm_per_tick || 'PLN/tick';
            let lines = [];
            lines.push(lang().confirm_assign_costs || 'Podsumowanie kosztow przypisania:');
            lines.push('▪ ' + acqLabel.toUpperCase() + ' (TRANSFER)');
            if (accessFee > 0) {
                lines.push((lang().confirm_access_fee || 'Oplata przylaczeniowa') + ': ' + fmt(accessFee) + ' PLN');
            }
            if (leaseFee > 0) {
                lines.push((lang().confirm_lease_fee || 'Czynsz najmu (wynajem)') + ': ' + fmt(leaseFee) + ' ' + perTick);
            }
            lines.push('');
            lines.push(lang().confirm_question || 'Czy potwierdzasz przeniesienie odwiertu do tego huba?');
            if (!await hubConfirm(lines.join('\n'))) return;
        }

        try {
            const res = await hubPost('transfer_well', { well_id: wellId, new_hub_id: newHubId });
            if (res.success) {
                closeHubModal('hub-transfer-modal');
                let successMsg;
                if (leaseFee > 0) {
                    successMsg = (lang().ok_transfer_with_lease || 'Odwiert przeniesiony (wynajem aktywny).')
                        .replace('{fee}', fmt(leaseFee));
                } else {
                    successMsg = res.message || lang().ok_transfer || 'Odwiert przeniesiony.';
                }
                await hubDialog(successMsg, 'success');
                reloadAfterAction();
            } else {
                hubDialog(res.error || lang().err_generic, 'error');
            }
        } catch (err) {
            hubDialog(lang().err_generic, 'error');
        }
    };

 // Rynek hubow: kupno / wynajem / Hub market: buy / rent

    const fmtPln = (v) => Number(v).toLocaleString('pl-PL', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

 // Kup uzywany hub z rynku / Buy a used market hub
    window.hubBuyUsed = async function (hubId) {
        const btn   = document.querySelector(`.logistics-hub-buy-btn[onclick*="hubBuyUsed(${hubId})"]`);
        const name  = btn ? (btn.dataset.hubName  || '') : '';
        const price = btn ? parseFloat(btn.dataset.buyPrice || 0) : 0;
        const msg   = (lang().market_confirm_buy || 'Kupic hub {name} za {price} PLN?')
            .replace('{name}', name)
            .replace('{price}', fmtPln(price));
        if (!await hubConfirm(msg, { title: lang().market_confirm_buy_title || 'Potwierdz zakup huba' })) return;

        try {
            const res = await hubPost('buy_used_hub', { hub_id: hubId });
            if (res.success) {
                await hubDialog(res.message || lang().market_ok_buy, 'success');
                reloadAfterAction();
            } else if (res.error_code === 'no_hub_permit') {
                hubPermitModal(res.error || lang().err_generic);
            } else {
                hubDialog(res.error || lang().err_generic, 'error');
            }
        } catch (err) {
            hubDialog(lang().err_generic, 'error');
        }
    };

 // Wynajmij hub z rynku / Rent a market hub
    window.hubRent = async function (hubId) {
        const btn      = document.querySelector(`.logistics-hub-rent-btn[onclick*="hubRent(${hubId})"]`);
        const name     = btn ? (btn.dataset.hubName    || '') : '';
        const deposit  = btn ? parseFloat(btn.dataset.rentDeposit || 0) : 0;
        const lease    = btn ? parseFloat(btn.dataset.leaseFee    || 0) : 0;
        const msg      = (lang().market_confirm_rent || 'Wynajac hub {name}? Kaucja: {deposit} PLN, czynsz: {lease} PLN/tick.')
            .replace('{name}', name)
            .replace('{deposit}', fmtPln(deposit))
            .replace('{lease}', fmtPln(lease));
        if (!await hubConfirm(msg, { title: lang().market_confirm_rent_title || 'Potwierdz wynajem huba' })) return;

        try {
            const res = await hubPost('rent_hub', { hub_id: hubId });
            if (res.success) {
                await hubDialog(res.message || lang().market_ok_rent, 'success');
                reloadAfterAction();
            } else if (res.error_code === 'no_hub_permit') {
                hubPermitModal(res.error || lang().err_generic);
            } else {
                hubDialog(res.error || lang().err_generic, 'error');
            }
        } catch (err) {
            hubDialog(lang().err_generic, 'error');
        }
    };

 // Modal: kup nowy hub / Buy new hub modal
    window.hubBuyNewModal = function () {
        openHubModal('hub-buy-new-modal');
    };

    window.hubBuyNewTypeChange = function (radio) {
        document.querySelectorAll('#hub-buy-new-modal .logistics-mode-card')
            .forEach(el => el.classList.remove('selected'));
        const card = radio.closest('.logistics-mode-card');
        if (card) card.classList.add('selected');
    };

    window.hubBuyNewSubmit = async function (e) {
        if (e && e.preventDefault) e.preventDefault();
        const form = document.getElementById('hub-buy-new-form');
        if (!form) return;
        const data = Object.fromEntries(new FormData(form));
        const name = String(data.name || '').trim();
        if (name === '') {
            hubDialog(lang().market_name_required || 'Podaj nazwe huba.', 'warning');
            return;
        }

        const typeRadio = form.querySelector('input[name="hub_type"]:checked');
        const price     = typeRadio ? parseFloat(typeRadio.dataset.buildCost || 0) : 0;
        const msg = (lang().market_confirm_buy_new || 'Wybudowac nowy hub {name} za {price} PLN?')
            .replace('{name}', name)
            .replace('{price}', fmtPln(price));
        if (!await hubConfirm(msg, { title: lang().market_ok_buy_new })) return;

        const btn = document.getElementById('hub-buy-new-submit');
        if (btn) btn.disabled = true;
        try {
            const res = await hubPost('buy_new_hub', data);
            if (res.success) {
                closeHubModal('hub-buy-new-modal');
                await hubDialog(res.message || lang().market_ok_buy_new, 'success');
                reloadAfterAction();
            } else if (res.error_code === 'no_hub_permit') {
                hubPermitModal(res.error || lang().err_generic);
            } else {
                hubDialog(res.error || lang().err_generic, 'error');
            }
        } catch (err) {
            hubDialog(lang().err_generic, 'error');
        } finally {
            if (btn) btn.disabled = false;
        }
    };

 // Browser dostepnych hubow

    (function initAvailableHubsBrowser() {
        const browser = document.getElementById('lhb-browser');
        const searchEl = document.getElementById('lhb-search');
        const countEl = document.getElementById('lhb-count');
        const chips = Array.from(document.querySelectorAll('[data-lhb-filter]'));
        if (!browser) {
            return;
        }

        let activeFilter = 'all';

        function updateCount(shown, total) {
            if (!countEl) {
                return;
            }
            const template = countEl.dataset.filterTemplate || 'Widoczne: {shown} / {total}';
            countEl.textContent = template
                .replace('{shown}', String(shown))
                .replace('{total}', String(total));
        }

        function applyFilter() {
            const query = searchEl ? searchEl.value.toLowerCase().trim() : '';
            const filtering = query !== '' || activeFilter !== 'all';
            let total = 0;
            let shown = 0;

            browser.classList.toggle('is-filtering', filtering);

            browser.querySelectorAll('.logistics-region-group').forEach((group) => {
                const regionName = group.dataset.regionNameLc || '';
                const cards = Array.from(group.querySelectorAll('[data-lhb-card]'));
                let anyVisible = false;

                cards.forEach((card) => {
                    total += 1;
                    const name = card.dataset.hubNameLc || '';
                    const type = card.dataset.hubType || '';
                    const free = parseInt(card.dataset.hubFree || '0', 10);
                    const acqType = card.dataset.hubAcqType || 'new';

                    const matchQuery = query === '' || name.includes(query) || regionName.includes(query);
                    const matchFilter =
                        activeFilter === 'all' ||
                        (activeFilter === 'free' && free > 0) ||
                        (activeFilter === 'new' && acqType === 'new') ||
                        (activeFilter === 'used' && acqType === 'used') ||
                        (activeFilter === 'rental' && acqType === 'rental') ||
                        (activeFilter === 'large' && type === 'large') ||
                        (activeFilter === 'medium' && type === 'medium') ||
                        (activeFilter === 'small' && type === 'small');

                    const visible = matchQuery && matchFilter;
                    card.style.display = visible ? '' : 'none';
                    if (visible) {
                        anyVisible = true;
                        shown += 1;
                    }
                });

                group.style.display = anyVisible ? '' : 'none';
                group.classList.toggle('is-filter-match', anyVisible && filtering);
            });

            updateCount(shown, total);
        }

        if (searchEl) {
            searchEl.addEventListener('input', applyFilter);
        }

        chips.forEach((chip) => {
            chip.addEventListener('click', () => {
                chips.forEach((entry) => entry.classList.remove('active'));
                chip.classList.add('active');
                activeFilter = chip.dataset.lhbFilter || 'all';
                applyFilter();
            });
        });

        browser.addEventListener('click', (event) => {
            const toggleBtn = event.target.closest('[data-lhb-toggle]');
            if (toggleBtn) {
                const group = toggleBtn.closest('.logistics-region-group');
                if (group) {
                    group.classList.toggle('is-open');
                }
                return;
            }

            const expandBtn = event.target.closest('[data-lhb-expand]');
            if (!expandBtn) {
                return;
            }

            const group = expandBtn.closest('.logistics-region-group');
            if (!group) {
                return;
            }

            const expanded = group.classList.toggle('is-expanded');
            expandBtn.textContent = expanded
                ? (expandBtn.dataset.expandedLabel || 'Pokaż mniej')
                : (expandBtn.dataset.collapsedLabel || 'Pokaż więcej');
        });

        applyFilter();
    })();

 // Cooldown badge live timer
 // Updates .hub-cooldown-badge every 60s so the displayed remaining time stays current.
 // When a cooldown expires the badge is replaced with an inline reload prompt.

    (function initCooldownTimers() {
        function formatRemaining(until) {
            const secs = Math.max(0, Math.floor((new Date(until).getTime() - Date.now()) / 1000));
            if (secs <= 0) return null;
            const h = Math.floor(secs / 3600);
            const m = Math.floor((secs % 3600) / 60);
            return h > 0 ? `${h}h ${m}min` : `${m}min`;
        }

        function tick() {
            document.querySelectorAll('.hub-cooldown-badge[data-cooldown-until]').forEach((el) => {
                const until = el.dataset.cooldownUntil;
                const label = formatRemaining(until);
                if (!label) {
 // Cooldown expired - prompt reload
                    el.textContent = '⏳ 0min';
                    el.style.opacity = '0.5';
                    if (!el.dataset.expiredHandled) {
                        el.dataset.expiredHandled = '1';
                        setTimeout(() => window.location.reload(), 1500);
                    }
                } else {
                    el.textContent = '⏳ ' + label;
                }
            });
        }

        if (document.querySelector('.hub-cooldown-badge')) {
            tick();
            setInterval(tick, 60000);
        }
    })();

 // Utils

    function esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

})();
