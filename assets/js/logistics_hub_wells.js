/**
 * Hub well assignment, detachment and transfer flows.
 * Przypisywanie, odpinanie i przenoszenie odwiertow miedzy hubami.
 */
(function () {
    'use strict';

    const Hub = window.HubUI;
    if (!Hub) return;
    const labels = () => Hub.lang();

    function loading(target) {
        target.innerHTML = `<div class="logistics-loading">${Hub.esc(labels().loading || '')}</div>`;
    }

    function error(target, message) {
        target.innerHTML = `<div class="logistics-alert logistics-alert--danger">${Hub.esc(message || labels().err_generic || '')}</div>`;
    }

    function statusLabel(status) {
        return labels()['ws_' + status] || status || '';
    }

    function acquisitionDetails(entry) {
        const hub = entry.hub;
        const type = entry.acq_type || hub.acquisition_type || 'new';
        const wear = Number(entry.acq_wear_mult || 1);
        const risk = Number(entry.acq_risk_mult || 1);
        const opex = Number(entry.acq_opex_mult || 1);
        const lease = Number(entry.acq_lease_fee || 0);
        const start = `${Number(entry.acq_start_min || 0)}-${Number(entry.acq_start_max || 100)}%`;
        return `<div class="hub-acq-breakdown">
            <span class="hub-acq-badge hub-acq-badge--${Hub.esc(type)}">${Hub.esc(labels()['acq_' + type] || type)}</span>
            <span class="${wear > 1.2 ? 'c-bad' : wear > 1 ? 'c-warn' : 'c-good'}" title="${Hub.esc(labels().acq_wear || '')}">${wear.toFixed(2)}</span>
            <span class="${risk > 1.3 ? 'c-bad' : risk > 1 ? 'c-warn' : 'c-good'}" title="${Hub.esc(labels().acq_risk || '')}">${risk.toFixed(2)}</span>
            <span class="${opex > 1.1 ? 'c-bad' : opex > 1 ? 'c-warn' : 'c-good'}" title="${Hub.esc(labels().acq_opex || '')}">${opex.toFixed(2)}</span>
            <span title="${Hub.esc(labels().acq_start_cond || '')}">${start}</span>
            ${lease > 0 ? `<span class="c-warn">${Hub.fmtPln(lease, 2)} ${Hub.esc(labels().confirm_per_tick || '')}</span>` : ''}
        </div>`;
    }

    function hubChoiceRow(entry, wellId, action) {
        const hub = entry.hub;
        const freeSlots = Number(entry.slots_avail || 0);
        const totalSlots = Number(hub.slot_limit || 0);
        const usageFee = Number(entry.usage_fee || 0);
        const leaseFee = Number(entry.acq_lease_fee || 0);
        const accessFee = Number(entry.acq_access_fee || 0);
        const condition = Number(hub.condition_pct || 100);
        const acquisitionType = entry.acq_type || hub.acquisition_type || 'new';
        const slotLabel = (labels().avail_slots || '')
            .replace('{free}', String(freeSlots))
            .replace('{total}', String(totalSlots));
        const feeLabel = (labels().avail_fee || '').replace('{fee}', Hub.fmtPln(usageFee));
        return `<div class="logistics-hub-assign-row">
            <div>
                <strong>${Hub.esc(hub.name || '')}</strong>
                <span class="badge ${Hub.esc(entry.status_class || '')}">${Hub.esc(hub.status || '')}</span>
                ${acquisitionDetails(entry)}
                <small>${Hub.esc(feeLabel)} · ${Hub.esc(slotLabel)}</small>
            </div>
            <button class="btn btn-sm btn-primary" type="button"
                    data-hub-action="${action}" data-well-id="${Number(wellId)}" data-hub-id="${Number(hub.id)}"
                    data-fee="${usageFee}" data-condition="${condition}"
                    data-acq-type="${Hub.esc(acquisitionType)}" data-acq-lease-fee="${leaseFee}"
                    data-acq-access-fee="${accessFee}" ${entry.slots_full ? 'disabled' : ''}>
                ${action === 'do-transfer' ? labels().btn_transfer : labels().btn_assign}
            </button>
        </div>`;
    }

    function costConfirmation(button, transfer) {
        const type = button ? button.dataset.acqType || 'new' : 'new';
        const usageFee = button ? Number(button.dataset.fee || 0) : 0;
        const leaseFee = button ? Number(button.dataset.acqLeaseFee || 0) : 0;
        const accessFee = button ? Number(button.dataset.acqAccessFee || 0) : 0;
        const lines = [labels().confirm_assign_costs || '', (labels()['acq_' + type] || type).toUpperCase()];
        if (accessFee > 0) {
            lines.push(`${labels().confirm_access_fee || ''}: ${Hub.fmtPln(accessFee, 2)} ${Hub.currency()}`);
        }
        if (!transfer && usageFee > 0) {
            lines.push(`${labels().confirm_usage_fee || ''}: ${Hub.fmtPln(usageFee, 2)} ${labels().confirm_per_tick || ''}`);
        }
        if (leaseFee > 0) {
            lines.push(`${labels().confirm_lease_fee || ''}: ${Hub.fmtPln(leaseFee, 2)} ${labels().confirm_per_tick || ''}`);
        }
        lines.push('', labels().confirm_question || '');
        return { message: lines.join('\n'), hasCost: accessFee > 0 || leaseFee > 0 || (!transfer && usageFee > 0) };
    }

    window.hubWellsModal = async function (hubId) {
        const body = document.getElementById('hub-wells-modal-body');
        const title = document.getElementById('hub-wells-modal-title');
        if (!body) return;
        loading(body);
        Hub.openHubModal('hub-wells-modal');
        try {
            const result = await Hub.hubGet('hub_wells', { hub_id: hubId });
            if (!result.success) {
                error(body, result.error);
                return;
            }
            if (title && result.hub) title.textContent = result.hub.name || '';
            const wells = result.wells || [];
            if (wells.length === 0) {
                body.innerHTML = `<div class="logistics-empty">${Hub.esc(labels().wells_none || '')}</div>`;
                return;
            }
            const rows = wells.map((well) => `<div class="logistics-table-row">
                <span>#${Number(well.id)} ${Hub.esc(well.name || well.location_name || '')}</span>
                <span>${Hub.esc(well.region_name || '')}${well.zone_key ? ' / ' + Hub.esc(well.zone_key) : ''}</span>
                <span>${Number(well.base_production_per_hour || 0).toFixed(1)}</span>
                <span>${Hub.esc(statusLabel(well.status))}</span>
                <span class="logistics-hub-row-actions">
                    <button class="btn btn-xs btn-warn" type="button" data-hub-action="detach" data-well-id="${Number(well.id)}" data-hub-id="${Number(hubId)}">${labels().btn_detach}</button>
                    <button class="btn btn-xs btn-secondary" type="button" data-hub-action="transfer-modal" data-well-id="${Number(well.id)}" data-hub-id="${Number(hubId)}">${labels().btn_transfer}</button>
                </span>
            </div>`).join('');
            body.innerHTML = `<div class="logistics-table">
                <div class="logistics-table-head"><span>${labels().col_well}</span><span>${labels().col_region}</span><span>${labels().col_prod}</span><span>${labels().col_status}</span><span>${labels().col_actions}</span></div>
                ${rows}
            </div>`;
        } catch (requestError) {
            console.error('[HUB] hub wells request failed', requestError);
            error(body);
        }
    };

    window.hubDetachWell = async function (wellId) {
        const message = (labels().detach_confirm || '').replace('{id}', String(wellId));
        if (!await Hub.hubConfirm(message)) return;
        try {
            const result = await Hub.hubPost('detach_well', { well_id: wellId });
            if (!result.success) {
                await Hub.hubDialog(result.error || labels().err_generic, 'error');
                return;
            }
            Hub.closeHubModal('hub-wells-modal');
            await Hub.hubDialog(result.message || labels().ok_detach, 'success');
            Hub.reloadAfterAction();
        } catch (requestError) {
            await Hub.hubDialog(labels().err_generic, 'error');
        }
    };

    window.hubAssignWellToHubModal = async function (hubId) {
        const body = document.getElementById('hub-assign-modal-body');
        const title = document.querySelector('#hub-assign-modal .logistics-modal-hdr span');
        const card = Hub.getOwnedHubCard(hubId);
        if (!body) return;
        const hubName = card ? String(card.dataset.hubName || '') : '';
        const zoneKey = card ? String(card.dataset.hubZoneKey || '') : '';
        const acquisitionType = card ? String(card.dataset.hubAcqType || 'new') : 'new';
        const leaseFee = card ? Number(card.dataset.hubLeaseFee || 0) : 0;
        if (title) {
            title.textContent = `${labels().assign_well_title || ''}${hubName ? `: ${hubName}` : ''}`;
        }
        loading(body);
        Hub.openHubModal('hub-assign-modal');
        try {
            const result = await Hub.hubGet('unassigned_wells');
            if (!result.success) {
                error(body, result.error);
                return;
            }
            const regionId = Number(card ? card.dataset.hubRegionId || 0 : 0);
            const wells = (result.wells || [])
                .filter((well) => !regionId || Number(well.region_id) === regionId)
                .sort((left, right) => {
                    const leftInZone = zoneKey !== '' && String(left.zone_key || '') === zoneKey ? 1 : 0;
                    const rightInZone = zoneKey !== '' && String(right.zone_key || '') === zoneKey ? 1 : 0;
                    return leftInZone !== rightInZone
                        ? rightInZone - leftInZone
                        : Number(left.id) - Number(right.id);
                });
            if (wells.length === 0) {
                body.innerHTML = `<div class="logistics-empty">${Hub.esc(labels().assign_well_none || '')}</div>`;
                return;
            }
            body.innerHTML = `<div class="logistics-table">
                <div class="logistics-table-head">
                    <span>${Hub.esc(labels().col_well || '')}</span>
                    <span>${Hub.esc(labels().col_region || '')}</span>
                    <span>${Hub.esc(labels().col_prod || '')}</span>
                    <span>${Hub.esc(labels().col_actions || '')}</span>
                </div>
                ${wells.map((well) => `<div class="logistics-table-row">
                <span>#${Number(well.id)} ${Hub.esc(well.name || well.location_name || '')}</span>
                <span>${Hub.esc(well.region_name || '')}${well.zone_key ? ` / ${Hub.esc(well.zone_key)}` : ''}</span>
                <span>${Number(well.base_production_per_hour || 0).toFixed(1)}</span>
                <span><button class="btn btn-xs btn-primary" type="button" data-hub-action="do-assign"
                    data-well-id="${Number(well.id)}" data-hub-id="${Number(hubId)}" data-fee="0"
                    data-acq-type="${Hub.esc(acquisitionType)}" data-acq-lease-fee="${leaseFee}">${Hub.esc(labels().btn_assign_well || labels().btn_assign || '')}</button></span>
            </div>`).join('')}</div>`;
        } catch (requestError) {
            error(body);
        }
    };

    window.hubAssignModal = async function (wellId, page = 1) {
        const body = document.getElementById('hub-assign-modal-body');
        if (!body) return;
        loading(body);
        Hub.openHubModal('hub-assign-modal');
        try {
            const result = await Hub.hubGet('assignable_hubs', { well_id: wellId, page });
            if (!result.success) {
                error(body, result.error);
                return;
            }
            const hubs = result.hubs || [];
            if (hubs.length === 0) {
                body.innerHTML = `<div class="logistics-empty">${Hub.esc(labels().avail_none || '')}</div>`;
                return;
            }
            let html = `<div class="logistics-hub-assign-list">${hubs.map((entry) => hubChoiceRow(entry, wellId, 'do-assign')).join('')}</div>`;
            if (Number(result.totalPages || 1) > 1) {
                html += `<div class="logistics-pagination logistics-pagination--modal">
                    <div class="logistics-pagination-info">${Number(result.page || 1)} / ${Number(result.totalPages)}</div>
                    <div class="logistics-pagination-buttons">
                        ${Number(result.page) > 1 ? `<button class="btn btn-xs btn-secondary" type="button" data-hub-action="assign-page" data-well-id="${Number(wellId)}" data-page="${Number(result.page) - 1}">${labels().pagination_prev}</button>` : ''}
                        ${Number(result.page) < Number(result.totalPages) ? `<button class="btn btn-xs btn-secondary" type="button" data-hub-action="assign-page" data-well-id="${Number(wellId)}" data-page="${Number(result.page) + 1}">${labels().pagination_next}</button>` : ''}
                    </div>
                </div>`;
            }
            body.innerHTML = html;
        } catch (requestError) {
            error(body);
        }
    };

    window.hubDoAssign = async function (wellId, hubId, fee, button) {
        const condition = Number(button ? button.dataset.condition || 100 : 100);
        if (condition <= 40) {
            const warning = condition <= 20 ? labels().warn_condition_critical : labels().warn_condition_low;
            if (!await Hub.hubConfirm(warning || '')) return;
        }
        const costs = costConfirmation(button, false);
        if (costs.hasCost && !await Hub.hubConfirm(costs.message)) return;
        try {
            const result = await Hub.hubPost('assign_well', { well_id: wellId, hub_id: hubId });
            if (!result.success) {
                if (result.error_code === 'no_hub_permit') Hub.hubPermitModal(result.error, result);
                else await Hub.hubDialog(result.error || labels().err_generic, 'error');
                return;
            }
            Hub.closeHubModal('hub-assign-modal');
            await Hub.hubDialog(result.message || labels().ok_assign, 'success');
            Hub.reloadAfterAction();
        } catch (requestError) {
            await Hub.hubDialog(labels().err_generic, 'error');
        }
    };

    window.hubTransferModal = async function (wellId, currentHubId) {
        const body = document.getElementById('hub-transfer-modal-body');
        if (!body) return;
        loading(body);
        Hub.closeHubModal('hub-wells-modal');
        Hub.openHubModal('hub-transfer-modal');
        try {
            const result = await Hub.hubGet('assignable_hubs', { well_id: wellId });
            if (!result.success) {
                error(body, result.error);
                return;
            }
            const hubs = (result.hubs || []).filter((entry) => Number(entry.hub.id) !== Number(currentHubId));
            body.innerHTML = hubs.length === 0
                ? `<div class="logistics-empty">${Hub.esc(labels().transfer_none || '')}</div>`
                : `<div class="logistics-hub-assign-list">${hubs.map((entry) => hubChoiceRow(entry, wellId, 'do-transfer')).join('')}</div>`;
        } catch (requestError) {
            error(body);
        }
    };

    window.hubDoTransfer = async function (wellId, hubId, button) {
        const costs = costConfirmation(button, true);
        if (costs.hasCost && !await Hub.hubConfirm(costs.message)) return;
        try {
            const result = await Hub.hubPost('transfer_well', { well_id: wellId, new_hub_id: hubId });
            if (!result.success) {
                await Hub.hubDialog(result.error || labels().err_generic, 'error');
                return;
            }
            Hub.closeHubModal('hub-transfer-modal');
            await Hub.hubDialog(result.message || labels().ok_transfer, 'success');
            Hub.reloadAfterAction();
        } catch (requestError) {
            await Hub.hubDialog(labels().err_generic, 'error');
        }
    };
})();
