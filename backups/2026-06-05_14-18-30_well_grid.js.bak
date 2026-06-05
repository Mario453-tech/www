/**
 * well_grid.js - well card interactions
 * well_grid.js - interakcje kart odwiertow
 */

/* Translation helper - uses window.WG_LANG injected by well_grid.php */
/* Pomocnik tlumaczen - korzysta z window.WG_LANG z well_grid.php */
function wgt(k, p) {
    var s = (window.WG_LANG && window.WG_LANG[k] !== undefined) ? window.WG_LANG[k] : k;
    if (p) { Object.keys(p).forEach(function(pk) { s = s.split(':' + pk).join(String(p[pk])); }); }
    return s;
}

function wgFormatCountdown(secsLeft) {
    if (secsLeft <= 0) return '00:00';
    var m = Math.floor(secsLeft / 60);
    var s = secsLeft % 60;
    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
}

function wgInitSwapTimers() {
    var banners = document.querySelectorAll('[id^="wg-swap-banner-"]');
    banners.forEach(function(banner) {
        var until     = banner.dataset.until;
        if (!until) return;
        var untilMs   = new Date(until.replace(' ', 'T')).getTime();
        var wellId    = banner.id.replace('wg-swap-banner-', '');
        var timerEl   = document.getElementById('wg-swap-timer-' + wellId);
        if (!timerEl) return;

        function tick() {
            var secsLeft = Math.max(0, Math.round((untilMs - Date.now()) / 1000));
            timerEl.textContent = wgt('eq_swap_remaining', { time: wgFormatCountdown(secsLeft) });
            if (secsLeft <= 0) {
                timerEl.textContent = wgt('swap_done');
                setTimeout(function() { window.location.reload(); }, 1500);
            }
        }
        tick();
        setInterval(tick, 1000);
    });
}

document.addEventListener('DOMContentLoaded', wgInitSwapTimers);

function wgToggleGroup(id) {
    var grid  = document.getElementById(id);
    var arrow = document.getElementById(id + '-arrow');
    if (!grid || !arrow) return;
    var isOpen = grid.style.display !== 'none';
    grid.style.display = isOpen ? 'none' : '';
    arrow.classList.toggle('wg-arrow-open', !isOpen);
}

function wgToggle(id) {
    var detail = document.getElementById('wg-detail-' + id);
    var hint   = document.getElementById('wg-hint-' + id);
    if (!detail || !hint) return;
    var isOpen = detail.style.display !== 'none';

    document.querySelectorAll('.wg-detail').forEach(function(el) {
        el.style.display = 'none';
    });
    document.querySelectorAll('.wg-toggle-hint').forEach(function(el) {
        el.textContent = wgt('hint_open');
    });
    document.querySelectorAll('.wg-storage-body').forEach(function(el) {
        el.style.display = 'none';
    });
    document.querySelectorAll('.wg-storage-arrow').forEach(function(el) {
        el.classList.remove('wg-arrow-open');
    });
    document.querySelectorAll('.wg-equipment-body').forEach(function(el) {
        el.style.display = 'none';
    });
    document.querySelectorAll('.wg-equipment-arrow').forEach(function(el) {
        el.classList.remove('wg-arrow-open');
    });
    document.querySelectorAll('.wg-layer-body').forEach(function(el) {
        el.style.display = 'none';
    });
    document.querySelectorAll('.wg-layer-arrow').forEach(function(el) {
        el.classList.remove('wg-arrow-open');
    });
    document.querySelectorAll('.wg-transport-body').forEach(function(el) {
        el.style.display = 'none';
    });
    document.querySelectorAll('[id^="wg-tarrow-"]').forEach(function(el) {
        el.classList.remove('wg-arrow-open');
    });

    if (!isOpen) {
        detail.style.display = 'block';
        hint.textContent = wgt('hint_close');
    }
}

function wgToggleStorage(id) {
    var body  = document.getElementById('wg-storage-' + id);
    var arrow = document.getElementById('wg-sarrow-' + id);
    if (!body || !arrow) return;
    var isOpen = body.style.display !== 'none';
    body.style.display = isOpen ? 'none' : 'block';
    arrow.classList.toggle('wg-arrow-open', !isOpen);
}

/**
 * Storage upgrade confirmation modal via AJAX.
 * Modal potwierdzenia rozbudowy magazynu przez AJAX.
 */
function wgConfirmStorage(wellId, capNow, capAfter, cost) {
    var existing = document.getElementById('wg-storage-modal');
    if (existing) existing.remove();

    var fmt = function(n) {
        return Number(n).toLocaleString(window.APP_LOCALE, { maximumFractionDigits: 0 });
    };

    var modal = document.createElement('div');
    modal.id = 'wg-storage-modal';
    modal.className = 'wg-modal-overlay';
    modal.innerHTML =
        '<div class="wg-modal">' +
            '<div class="wg-modal-title">' + wgt('storage_modal_title') + '</div>' +
            '<div class="wg-modal-body">' +
                '<div class="wg-modal-row">' +
                    '<span>' + wgt('storage_cap_before') + '</span>' +
                    '<strong>' + fmt(capNow) + ' ' + wgt('bbl') + '</strong>' +
                '</div>' +
                '<div class="wg-modal-row">' +
                    '<span>' + wgt('storage_cap_after') + '</span>' +
                    '<strong style="color:#4ec97a">' + fmt(capAfter) + ' ' + wgt('bbl') + '</strong>' +
                '</div>' +
                '<div class="wg-modal-row wg-modal-row--cost">' +
                    '<span>' + wgt('storage_cost_label') + '</span>' +
                    '<strong style="color:#e6b43c">' + fmt(cost) + ' ' + wgt('pln') + '</strong>' +
                '</div>' +
            '</div>' +
            '<div class="wg-modal-msg" id="wg-modal-msg"></div>' +
            '<div class="wg-modal-actions">' +
                '<button class="btn btn-secondary" onclick="wgCloseModal()">' + wgt('cancel') + '</button>' +
                '<button class="btn btn-success" id="wg-modal-confirm">' + wgt('storage_confirm_btn') + '</button>' +
            '</div>' +
        '</div>';

    document.body.appendChild(modal);

    modal.addEventListener('click', function(e) {
        if (e.target === modal) wgCloseModal();
    });

    document.getElementById('wg-modal-confirm').addEventListener('click', function() {
        var btn = this;
        var msgEl = document.getElementById('wg-modal-msg');
        btn.disabled = true;
        btn.textContent = wgt('storage_upgrading');

 // Resolve CSRF token from global state or the first hidden field.`r`n // Pobierz token CSRF z globalnego stanu albo z pierwszego ukrytego pola.
        var csrf = (typeof window.WG_CSRF !== 'undefined')
            ? window.WG_CSRF
            : (document.querySelector('input[name="csrf_token"]') || {}).value || '';

        var fd = new FormData();
        fd.append('well_id', wellId);
        fd.append('csrf_token', csrf);

        fetch('/public/upgrade_storage.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                btn.disabled = false;
                btn.textContent = wgt('storage_done');
                btn.addEventListener('click', function() { wgCloseModal(); window.location.reload(); });
                msgEl.className = 'wg-modal-success';
                msgEl.textContent = data.message;
                setTimeout(function() {
                    wgCloseModal();
                    window.location.reload();
                }, 3000);
            } else {
                btn.disabled = false;
                btn.textContent = wgt('storage_confirm_btn');
                msgEl.className = 'wg-modal-error';
                msgEl.textContent = data.message || wgt('err_retry');
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = wgt('storage_confirm_btn');
            msgEl.className = 'wg-modal-error';
            msgEl.textContent = wgt('err_connection');
        });
    });
}

function wgCloseModal() {
    var modal = document.getElementById('wg-storage-modal');
    if (modal) {
        modal.classList.add('wg-modal-closing');
        setTimeout(function() { if (modal.parentNode) modal.remove(); }, 200);
    }

    var eqModal = document.getElementById('wg-eq-modal');
    if (eqModal) {
        eqModal.classList.add('wg-modal-closing');
        setTimeout(function() { if (eqModal.parentNode) eqModal.remove(); }, 200);
    }
}

function wgToggleEquipment(id) {
    var body  = document.getElementById('wg-equipment-' + id);
    var arrow = document.getElementById('wg-earrow-' + id);
    if (!body || !arrow) return;
    var isOpen = body.style.display !== 'none';
    body.style.display = isOpen ? 'none' : 'block';
    arrow.classList.toggle('wg-arrow-open', !isOpen);
}

function wgEqRequest(wellId, action, tier, cost, confirmMsg, btnEl, confirmTitle) {
    var isDanger = (action === 'set_tier' && tier === 'black_market');
    confirmAction(confirmMsg, function() {
        var csrf = (typeof window.WG_CSRF !== 'undefined')
            ? window.WG_CSRF
            : (document.querySelector('input[name="csrf_token"]') || {}).value || '';

        var origText = btnEl.textContent;
        btnEl.disabled = true;
        btnEl.textContent = '...';

        var fd = new FormData();
        fd.append('well_id',    wellId);
        fd.append('action',     action);
        fd.append('tier',       tier);
        fd.append('csrf_token', csrf);

        fetch('/public/equipment_well.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                btnEl.textContent = 'OK';
                setTimeout(function() { window.location.reload(); }, 900);
            } else {
                btnEl.disabled = false;
                btnEl.textContent = origText;
                alertError(data.message || wgt('err_retry'));
            }
        })
        .catch(function() {
            btnEl.disabled = false;
            btnEl.textContent = origText;
            alertError(wgt('err_connection'));
        });
    }, {
        title: confirmTitle || wgt('eq_confirm_title'),
        type:  isDanger ? 'danger' : 'confirm',
        confirmLabel: wgt('eq_confirm_ok')
    });
}

function wgSetTier(wellId, tier, cost) {
    var btn = event.currentTarget;
    var fmt = function(n) { return Number(n).toLocaleString(window.APP_LOCALE, { maximumFractionDigits: 0 }); };
    var tierNames = { black_market: wgt('tier_black_market'), standard: wgt('tier_standard'), premium: wgt('tier_premium') };
    var tierName = tierNames[tier] || tier;
    wgEqRequest(wellId, 'set_tier', tier, cost,
        wgt('tier_confirm', { tier: tierName, cost: fmt(cost) }),
        btn,
        wgt('tier_change_title', { tier: tierName }));
}

function wgUpgradeEquipment(wellId, cost) {
    var btn = event.currentTarget;
    var fmt = function(n) { return Number(n).toLocaleString(window.APP_LOCALE, { maximumFractionDigits: 0 }); };
    wgEqRequest(wellId, 'upgrade_level', '', cost,
        wgt('upgrade_confirm', { cost: fmt(cost) }),
        btn,
        wgt('upgrade_title'));
}

function wgToggleLayer(id) {
    var body  = document.getElementById('wg-layer-' + id);
    var arrow = document.getElementById('wg-larrow-' + id);
    if (!body || !arrow) return;
    var isOpen = body.style.display !== 'none';
    body.style.display = isOpen ? 'none' : 'block';
    arrow.classList.toggle('wg-arrow-open', !isOpen);
}

function wgSwitchLayer(wellId, layerId, cost, layerName, hours) {
    var btn = event.currentTarget;
    var fmt = function(n) { return Number(n).toLocaleString(window.APP_LOCALE, { maximumFractionDigits: 0 }); };

    var msg = wgt('layer_confirm', { layer: layerName });
    if (cost > 0) msg += wgt('layer_cost', { cost: fmt(cost) });
    if (hours > 0) msg += wgt('layer_paused', { hours: hours });
    msg += wgt('layer_reset');

    confirmAction(msg, function() {
        var csrf = (typeof window.WG_CSRF !== 'undefined')
            ? window.WG_CSRF
            : (document.querySelector('input[name="csrf_token"]') || {}).value || '';

        var origText = btn.textContent;
        btn.disabled = true;
        btn.textContent = '...';

        var fd = new FormData();
        fd.append('well_id',  wellId);
        fd.append('action',   'switch');
        fd.append('layer_id', layerId);
        fd.append('csrf',     csrf);

        fetch('/public/layer_well.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                btn.textContent = 'OK';
                setTimeout(function() { window.location.reload(); }, 900);
            } else {
                btn.disabled = false;
                btn.textContent = origText;
                alertError(data.message || wgt('err_retry'));
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = origText;
            alertError(wgt('err_connection'));
        });
    }, { title: wgt('layer_confirm_title'), type: 'confirm', confirmLabel: wgt('layer_confirm_ok') });
}

// Transport section
// Sekcja transportu
function wgToggleTransport(wellId) {
    const body  = document.getElementById('wg-transport-' + wellId);
    const arrow = document.getElementById('wg-tarrow-' + wellId);
    if (!body) return;
    const open = body.style.display === 'none';
    body.style.display = open ? 'block' : 'none';
    if (arrow) arrow.classList.toggle('wg-arrow-open', open);
}

// Well sale flow
// Sprzedaz odwiertu
async function wgSellPreview(wellId) {
    try {
        const res  = await fetch('/src/WellSellApi.php?well_id=' + wellId);
        const data = await res.json();

        if (data.error) {
            alertError(data.error, wgt('sell_title'));
            return;
        }

        const b    = data.breakdown || {};
        const fmt  = function(n) { return new Intl.NumberFormat(window.APP_LOCALE).format(Math.round(n)); };
        const sign = function(n) { return (n >= 0 ? '+' : '') + n.toFixed(1) + '%'; };
        const val  = fmt(data.sell_value);

 // Valuation rows
 // Wiersze wyceny
        var rows = [
            [wgt('sell_row_base'),      fmt(b.base || 0) + ' ' + wgt('pln')],
            [wgt('sell_row_condition'), sign(b.condition_pct || 0)],
            [wgt('sell_row_wear'),      sign(b.wear_pct || 0)],
            [wgt('sell_row_risk'),      sign(b.risk_pct || 0)],
            [wgt('sell_row_equipment'), sign(b.equipment_pct || 0)],
            [wgt('sell_row_depth'),     sign(b.depth_pct || 0)],
        ];
        if (b.incident_pct) {
            rows.push([wgt('sell_row_incident'), b.incident_pct + '%']);
        }

 // Reservoir status and impact on sale value.
 // Stan zloza i wplyw na wartosc sprzedazy.
        var reservoirPct = data.reservoir_pct != null ? data.reservoir_pct : 100;
        var reservoirHtml = '';
        if (reservoirPct < 100) {
            var resColor = reservoirPct < 30 ? '#e05555' : (reservoirPct < 60 ? '#e6b43c' : '#7ec97a');
            reservoirHtml =
                '<div class="wg-sell-reservoir">' +
                    '<span class="wg-sell-res-label">' + wgt('sell_reservoir_label') + '</span>' +
                    '<span class="wg-sell-res-bar-wrap">' +
                        '<span class="wg-sell-res-bar" style="width:' + reservoirPct + '%;background:' + resColor + '"></span>' +
                    '</span>' +
                    '<span class="wg-sell-res-val" style="color:' + resColor + '">' + wgt('sell_reservoir_val', { pct: reservoirPct }) + '</span>' +
                '</div>';
        }

        var tbody = rows.map(function(r) {
            var cls = r[1].startsWith('-') ? 'wg-sell-minus' : (r[1].startsWith('+') ? 'wg-sell-plus' : '');
            return '<div class="wg-sell-row">' +
                       '<span class="wg-sell-row-label">' + r[0] + '</span>' +
                       '<span class="wg-sell-row-val ' + cls + '">' + r[1] + '</span>' +
                   '</div>';
        }).join('');

        var bodyHtml =
            '<div class="wg-sell-breakdown">' + tbody + '</div>' +
            '<div class="wg-sell-total">' +
                '<span>' + wgt('sell_price_label') + '</span>' +
                '<span class="wg-sell-price">' + val + ' ' + wgt('pln') + '</span>' +
            '</div>' +
            reservoirHtml +
            '<p class="wg-sell-note">' + wgt('sell_note') + '</p>';

        confirmAction('', function() {
            wgConfirmSell(wellId);
        }, {
            title:        wgt('sell_modal_title', { id: wellId }),
            type:         'danger',
            confirmLabel: wgt('sell_confirm_btn'),
            bodyHtml:     bodyHtml,
        });

    } catch (e) {
        alertError(wgt('err_connection_msg', { msg: e.message }), wgt('sell_title'));
    }
}

function wgConfirmSell(wellId) {
    var csrf = (typeof window.WG_CSRF !== 'undefined') ? window.WG_CSRF : '';

    fetch('/src/WellSellApi.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ well_id: wellId, csrf_token: csrf }),
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
 // Remove the well card from the DOM without full reload.
 // Usun karte odwiertu z DOM bez pelnego reloadu.
            var card = document.getElementById('wg-card-' + wellId);
            if (card) {
                card.style.transition = 'opacity .4s, transform .4s';
                card.style.opacity    = '0';
                card.style.transform  = 'scale(.95)';
                setTimeout(function() {
                    card.remove();
 // Check whether the region became empty.
 // Sprawdz czy region jest teraz pusty.
                    var fmt = new Intl.NumberFormat(window.APP_LOCALE);
                    var earned = fmt.format(Math.round(data.sell_value || 0));
                    wgShowSoldToast(earned);
 // Hide the region when it no longer has cards.
 // Ukryj region, gdy nie ma juz kart.
                    if (card.closest) {
                        var grid = card.closest('.wg-grid');
                        if (grid && grid.querySelectorAll('.wg-card').length === 0) {
                            var group = grid.closest('.wg-group');
                            if (group) {
                                group.style.transition = 'opacity .3s';
                                group.style.opacity = '0';
                                setTimeout(function() { group.remove(); }, 300);
                            }
                        }
                    }
                }, 420);
            } else {
 // Fallback to reload if the card is missing in the DOM.
 // Fallback do reloadu, jesli karta nie istnieje w DOM.
                location.reload();
            }
        } else {
            alertError(data.message || data.error || wgt('err_sell'));
        }
    })
    .catch(function(e) {
        alertError(wgt('err_connection_msg', { msg: e.message }));
    });
}

function wgShowSoldToast(earned) {
    if (typeof window.showGameToast === 'function') {
        window.showGameToast(wgt('sold_toast'), '+' + earned + ' ' + wgt('pln'), 'success');
        return;
    }
    var toast = document.createElement('div');
    toast.className = 'wg-sold-toast';
    toast.innerHTML = wgt('sold_toast') + ' &nbsp;-&nbsp; <strong>+' + earned + ' ' + wgt('pln') + '</strong>';
    document.body.appendChild(toast);
    setTimeout(function() { toast.classList.add('wg-sold-toast--show'); }, 10);
    setTimeout(function() {
        toast.classList.remove('wg-sold-toast--show');
        setTimeout(function() { toast.remove(); }, 400);
    }, 3500);
}

async function wgSetTransport(wellId, transportType, pipelineOwned, pipelineBuildCost, tName, tCap, tOpex, tRisk) {
    const csrf = window.WG_CSRF || '';
    const needsPipelinePurchase = transportType === 'rurociag' && pipelineOwned !== true;

    if (transportType === 'nieustawiony') {
        confirmAction('', function() {
            wgSetTransportRequest(wellId, transportType, csrf);
        }, {
            title: wgt('transport_unset_label'),
            type: 'confirm',
            confirmLabel: wgt('transport_btn_clear'),
            bodyHtml:
                '<div class="wg-sell-breakdown">' +
                    '<div class="wg-sell-row">' +
                        '<span class="wg-sell-row-label">' + wgt('transport_unset_label') + '</span>' +
                        '<span class="wg-sell-row-val">' + wgt('transport_unset_desc') + '</span>' +
                    '</div>' +
                '</div>'
        });
        return;
    }

    if (needsPipelinePurchase) {
        wgPipelineTypeSelect(wellId, transportType, csrf);
        return;
    }

 // Confirm before switching to any other transport type (trucks, tanker, owned pipeline)
    const bodyHtml =
        '<div class="wg-sell-breakdown">' +
            '<div class="wg-sell-row">' +
                '<span class="wg-sell-row-label">' + wgt('transport_capacity') + '</span>' +
                '<span class="wg-sell-row-val">' + (tCap || 0) + '%</span>' +
            '</div>' +
            '<div class="wg-sell-row">' +
                '<span class="wg-sell-row-label">' + wgt('transport_opex') + '</span>' +
                '<span class="wg-sell-row-val">' + (tOpex || 0) + '%</span>' +
            '</div>' +
            '<div class="wg-sell-row">' +
                '<span class="wg-sell-row-label">' + wgt('transport_risk_short') + '</span>' +
                '<span class="wg-sell-row-val">' + (tRisk || '0%') + '</span>' +
            '</div>' +
        '</div>';

    confirmAction('', function() {
        wgSetTransportRequest(wellId, transportType, csrf);
    }, {
        title: (tName || wgt('transport_title')) + '  ' + wgt('transport_switch_confirm_btn'),
        type: 'confirm',
        confirmLabel: wgt('transport_switch_confirm_btn'),
        bodyHtml: bodyHtml
    });
}

// Track selected pipeline type across calls.
// Przechowuje wybrany typ rurociagu miedzy wywolaniami.
var _wgPipelineType = 'standard';

async function wgPipelineTypeSelect(wellId, transportType, csrf) {
    var profiles = null;
    try {
        const r = await fetch((window.WG_PIPELINE_API || '/src/PipelineApi.php') + '?action=pipeline_profiles');
        const d = await r.json();
        profiles = d.profiles || null;
    } catch (e) { /* fetch failed, render without costs */ }

    _wgPipelineType = 'standard';

    const fmt = function(n) {
        return Number(n || 0).toLocaleString(window.APP_LOCALE, { maximumFractionDigits: 0 });
    };

    function buildCards() {
        const types = ['light', 'standard', 'heavy'];
        const labelMap = {
            light:    wgt('pipe_type_light'),
            standard: wgt('pipe_type_standard'),
            heavy:    wgt('pipe_type_heavy'),
        };
        var html = '<div class="wg-sell-breakdown" style="gap:6px">';
        types.forEach(function(k) {
            const p = profiles ? profiles[k] : null;
            const isSel = k === _wgPipelineType;
            const border = isSel ? '2px solid var(--accent,#f59e0b)' : '2px solid transparent';
            html += '<div class="wg-pipe-type-card" data-pt="' + k + '" onclick="wgPipelineTypeChoose(\'' + k + '\')" '
                  + 'style="cursor:pointer;padding:8px 10px;border-radius:6px;background:var(--bg-card,#1e2a30);border:' + border + '">'
                  + '<div style="font-weight:600;margin-bottom:4px">' + (labelMap[k] || k) + '</div>';
            if (p) {
                html += '<div style="font-size:0.82em;color:var(--text-muted,#8fa)">'
                      + wgt('pipe_build_cost') + ': <strong>' + fmt(p.build_cost) + ' ' + wgt('pln') + '</strong>'
                      + ' &nbsp;|&nbsp; ' + wgt('pipe_hours_label') + ': <strong>' + p.build_hours + 'h</strong>'
                      + '</div>';
            }
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    confirmAction('', function() {
        wgSetTransportRequest(wellId, transportType, csrf, _wgPipelineType);
    }, {
        title: wgt('pipe_type_select_title'),
        type: 'confirm',
        confirmLabel: wgt('transport_buy_confirm'),
        bodyHtml: buildCards(),
    });
}

function wgPipelineTypeChoose(type) {
    _wgPipelineType = type;
    document.querySelectorAll('.wg-pipe-type-card').forEach(function(el) {
        const isSel = el.dataset.pt === type;
        el.style.border = isSel ? '2px solid var(--accent,#f59e0b)' : '2px solid transparent';
    });
}

async function wgSetTransportRequest(wellId, transportType, csrf, pipelineType) {
    try {
        const fd = new FormData();
        fd.append('action', 'set_transport');
        fd.append('well_id', wellId);
        fd.append('transport_type', transportType);
        fd.append('_token', csrf);
        if (pipelineType) {
            fd.append('pipeline_type', pipelineType);
        }

        const res  = await fetch('/src/WellStaffApi.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            if (typeof window.showGameToast === 'function') {
                window.showGameToast(data.message || wgt('transport_switched'), '', 'success');
                setTimeout(function() { window.location.reload(); }, 900);
            } else {
                location.reload();
            }
        } else {
            alertError(data.message || data.error || wgt('err_unknown'));
        }
    } catch (e) {
        alertError(wgt('err_connection_msg', { msg: e.message }));
    }
}

// Second transport leg (hub -> storage) / Odcinek 2 transportu (hub -> magazyn)
async function wgSetOutboundTransport(wellId, transportType, buildCost) {
    const csrf = window.WG_CSRF || '';
    const doIt = function () {
        wgSetOutboundTransportRequest(wellId, transportType, csrf);
    };

    if (transportType === 'rurociag') {
        // Rurociag juz kupiony (koszt 0) -> przelaczenie bez oplaty, inny komunikat.
        // Pipeline already owned (cost 0) -> free switch, different prompt.
        var owned = !buildCost || Number(buildCost) <= 0;
        confirmAction(
            owned
                ? wgt('leg2_confirm_pipeline_switch')
                : wgt('leg2_confirm_pipeline', { cost: Number(buildCost || 0).toLocaleString('pl-PL') }),
            doIt,
            {
                title: wgt('leg2_title'),
                type: 'confirm',
                confirmLabel: owned ? wgt('leg2_btn_pipeline_switch') : wgt('leg2_btn_pipeline')
            }
        );
        return;
    }

    if (transportType === 'ciezarowki') {
        confirmAction(wgt('leg2_confirm_road'), doIt, {
            title: wgt('leg2_title'),
            type: 'confirm',
            confirmLabel: wgt('leg2_btn_road')
        });
        return;
    }

    confirmAction(wgt('leg2_confirm_direct'), doIt, {
        title: wgt('leg2_title'),
        type: 'confirm',
        confirmLabel: wgt('leg2_btn_direct')
    });
}

async function wgSetOutboundTransportRequest(wellId, transportType, csrf) {
    try {
        const fd = new FormData();
        fd.append('action', 'set_outbound_transport');
        fd.append('well_id', wellId);
        fd.append('transport_type', transportType);
        fd.append('_token', csrf);

        const res  = await fetch('/src/WellStaffApi.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            if (typeof window.showGameToast === 'function') {
                window.showGameToast(data.message || wgt('transport_switched'), '', 'success');
                setTimeout(function() { window.location.reload(); }, 900);
            } else {
                location.reload();
            }
        } else {
            alertError(data.message || data.error || wgt('err_unknown'));
        }
    } catch (e) {
        alertError(wgt('err_connection_msg', { msg: e.message }));
    }
}
