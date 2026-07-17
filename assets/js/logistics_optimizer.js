(function () {
    'use strict';

    const api = window.LOGISTICS_API || '';
    const lang = window.LOGISTICS_LANG || {};
    const locale = window.LOGISTICS_LOCALE || 'pl-PL';
    const currency = window.LOGISTICS_CURRENCY || 'PLN';
    const modeCosts = { balans: 2500, max_prod: 5000, min_cost: 1500 };

    function text(key, replacements) {
        let value = lang[key] || key;
        Object.entries(replacements || {}).forEach(function (entry) {
            value = value.replace(':' + entry[0], entry[1]).replace('{' + entry[0] + '}', entry[1]);
        });
        return value;
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatMoney(value, hourly) {
        return Number(value || 0).toLocaleString(locale, {
            minimumFractionDigits: hourly ? 2 : 0,
            maximumFractionDigits: hourly ? 2 : 0
        }) + ' ' + currency + (hourly ? '/h' : '');
    }

    function formatNumber(value, digits) {
        return Number(value || 0).toLocaleString(locale, {
            minimumFractionDigits: digits,
            maximumFractionDigits: digits
        });
    }

    async function request(action, data) {
        if (!api) {
            throw new Error(text('api_missing'));
        }
        const method = action === 'summary' || action === 'cooldown' ? 'GET' : 'POST';
        const options = { headers: { 'X-Requested-With': 'XMLHttpRequest' } };
        let endpoint = api;
        if (method === 'GET') {
            endpoint += '?action=' + encodeURIComponent(action);
        } else {
            const form = new FormData();
            form.append('action', action);
            form.append('_token', window.LOGISTICS_CSRF || '');
            Object.entries(data || {}).forEach(function (entry) { form.append(entry[0], entry[1]); });
            options.method = 'POST';
            options.body = form;
        }
        const response = await fetch(endpoint, options);
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }
        return response.json();
    }

    function modal() {
        return document.getElementById('logistics-modal');
    }

    function results() {
        return document.getElementById('logistics-results');
    }

    function footer() {
        const root = modal();
        return root ? root.querySelector('.logistics-modal-footer') : null;
    }

    function selectedMode() {
        const input = document.querySelector('#logistics-modal input[name="logistics-mode"]:checked');
        return input ? input.value : 'balans';
    }

    function resetFooter() {
        const node = footer();
        if (!node) {
            return;
        }
        node.innerHTML = '<button type="button" class="btn btn-sm btn-secondary" data-logistics-close>' +
            escapeHtml(text('cancel')) + '</button>' +
            '<button type="button" class="btn btn-sm btn-primary" id="btn-logistics-run" data-logistics-confirm>' +
            escapeHtml(text('run')) + '</button>';
    }

    function askForConfirmation() {
        const node = footer();
        if (!node) {
            return;
        }
        const mode = selectedMode();
        node.innerHTML = '<div class="logistics-confirm-box">' +
            '<div class="logistics-confirm-info">' +
            '<span class="logistics-confirm-mode">' + escapeHtml(text('label_mode')) + ': <strong>' +
            escapeHtml(text('mode_' + mode)) + '</strong></span>' +
            '<span class="logistics-confirm-cost">' + escapeHtml(text('label_fee')) + ': <strong class="c-warn">' +
            escapeHtml(formatMoney(modeCosts[mode] || 0, false)) + '</strong></span>' +
            '</div><div class="logistics-confirm-btns">' +
            '<button type="button" class="btn btn-sm btn-secondary" data-logistics-reset>' + escapeHtml(text('cancel')) + '</button>' +
            '<button type="button" class="btn btn-sm btn-primary" id="btn-logistics-confirm" data-logistics-run>' +
            escapeHtml(text('confirm_btn')) + '</button></div></div>';
    }

    function startCooldown(button, seconds) {
        const defaultLabel = text('run');
        let remaining = Number(seconds) || 0;
        function tick() {
            if (remaining <= 0) {
                button.disabled = false;
                button.textContent = defaultLabel;
                return;
            }
            button.disabled = true;
            button.textContent = Math.floor(remaining / 60) + ':' + String(remaining % 60).padStart(2, '0');
            remaining -= 1;
            setTimeout(tick, 1000);
        }
        tick();
    }

    async function loadSummary() {
        const body = document.getElementById('logistics-current-body');
        if (!body) {
            return;
        }
        body.innerHTML = '<span class="logistics-loading">' + escapeHtml(text('loading')) + '</span>';
        try {
            const data = await request('summary');
            if (!data.success) {
                body.innerHTML = '<span class="c-bad">' + escapeHtml(text('err')) + '</span>';
                return;
            }
            const totals = data.totals || {};
            let html = '<div class="logistics-summary-grid">' +
                '<div class="logistics-stat"><div class="logistics-stat-lbl">' + escapeHtml(text('label_loss')) + '</div>' +
                '<div class="logistics-stat-val c-warn">' + formatNumber(totals.loss, 1) + ' bbl/h</div></div>' +
                '<div class="logistics-stat"><div class="logistics-stat-lbl">' + escapeHtml(text('label_cost')) + '</div>' +
                '<div class="logistics-stat-val c-muted">' + escapeHtml(formatMoney(totals.cost, true)) + '</div></div></div>';

            if (Array.isArray(data.wells) && data.wells.length > 0) {
                html += '<div class="logistics-wells-list">';
                data.wells.forEach(function (well) {
                    const loss = Number(well.loss || 0);
                    html += '<div class="logistics-well-row">' +
                        '<span class="logistics-well-id">#' + Number(well.id || 0) + '</span>' +
                        '<span class="logistics-well-type">' + escapeHtml(text('well_type_' + well.well_type)) + '</span>' +
                        '<span class="logistics-well-transport">' + escapeHtml(text('type_' + well.transport)) + '</span>' +
                        '<span class="logistics-well-cap c-muted">' + escapeHtml(well.capacity_pct || 0) + '%</span>' +
                        '<span class="logistics-well-loss ' + (loss > 0 ? 'c-warn' : 'c-good') + '">' +
                        (loss > 0 ? '-' + formatNumber(loss, 1) + ' bbl/h' : '') + '</span></div>';
                });
                html += '</div>';
            }
            body.innerHTML = html;

            const cooldown = await request('cooldown');
            const button = document.getElementById('btn-logistics-run');
            if (button && Number(cooldown.cooldown || 0) > 0) {
                startCooldown(button, cooldown.cooldown);
            }
        } catch (error) {
            body.innerHTML = '<span class="c-bad">' + escapeHtml(text('err')) + ': ' + escapeHtml(error.message) + '</span>';
        }
    }

    function renderComparison(result, mode) {
        const before = result.before || {};
        const after = result.after || {};
        let html = '<div class="logistics-cost-paid">' + escapeHtml(text('label_fee')) + ': <strong>' +
            escapeHtml(formatMoney(result.mode_cost || modeCosts[mode] || 0, false)) + '</strong></div>' +
            '<div class="logistics-compare"><div class="logistics-compare-col">' +
            '<div class="logistics-compare-hdr">' + escapeHtml(text('label_before')) + '</div>' +
            '<div class="logistics-compare-stat"><span>' + escapeHtml(text('label_loss')) + '</span><span class="c-warn">' + formatNumber(before.loss, 2) + ' bbl/h</span></div>' +
            '<div class="logistics-compare-stat"><span>' + escapeHtml(text('label_cost')) + '</span><span>' + escapeHtml(formatMoney(before.cost, true)) + '</span></div>' +
            '<div class="logistics-compare-stat"><span>' + escapeHtml(text('label_eff')) + '</span><span>' + escapeHtml(before.efficiency || 0) + '%</span></div>' +
            '</div><div class="logistics-compare-arrow"></div><div class="logistics-compare-col">' +
            '<div class="logistics-compare-hdr">' + escapeHtml(text('label_after')) + '</div>' +
            '<div class="logistics-compare-stat"><span>' + escapeHtml(text('label_loss')) + '</span><span>' + formatNumber(after.loss, 2) + ' bbl/h</span></div>' +
            '<div class="logistics-compare-stat"><span>' + escapeHtml(text('label_cost')) + '</span><span>' + escapeHtml(formatMoney(after.cost, true)) + '</span></div>' +
            '<div class="logistics-compare-stat"><span>' + escapeHtml(text('label_eff')) + '</span><span>' + escapeHtml(after.efficiency || 0) + '%</span></div>' +
            '</div></div>';

        if (Number(result.changed || 0) > 0) {
            html += '<div class="logistics-changed-header">' + escapeHtml(text('changed_count', { count: result.changed })) + '</div>' +
                '<div class="logistics-changes-list">';
            (result.changes || []).forEach(function (change) {
                html += '<div class="logistics-change-row"><span class="logistics-well-id">#' + Number(change.well_id || 0) + '</span>' +
                    '<span>' + escapeHtml(text('type_' + change.old_type)) + ' - ' + escapeHtml(text('type_' + change.new_type)) + '</span></div>';
            });
            html += '</div>';
        } else {
            html += '<div class="logistics-no-changes">' + escapeHtml(text('no_changes')) + '</div>';
        }
        return html;
    }

    async function runOptimizer() {
        const mode = selectedMode();
        const confirmButton = document.getElementById('btn-logistics-confirm');
        const resultBox = results();
        const resultBody = document.getElementById('logistics-results-body');
        if (!resultBox || !resultBody) {
            return;
        }
        if (confirmButton) {
            confirmButton.disabled = true;
            confirmButton.textContent = text('optimizing');
        }
        resultBox.hidden = true;

        try {
            const response = await request('optimize', { mode: mode });
            if (!response.success) {
                resetFooter();
                resultBody.innerHTML = '<div class="c-bad">' + escapeHtml(response.error || text('err')) + '</div>';
                resultBox.hidden = false;
                const button = document.getElementById('btn-logistics-run');
                if (button && response.cooldown) {
                    startCooldown(button, response.cooldown);
                }
                return;
            }
            resultBody.innerHTML = renderComparison(response, mode);
            resultBox.hidden = false;
            resetFooter();
            const button = document.getElementById('btn-logistics-run');
            if (button) {
                startCooldown(button, response.cooldown_secs || 300);
            }
            loadSummary();
        } catch (error) {
            resetFooter();
            resultBody.innerHTML = '<div class="c-bad">' + escapeHtml(text('err')) + ': ' + escapeHtml(error.message) + '</div>';
            resultBox.hidden = false;
        }
    }

    function openOptimizer() {
        const root = modal();
        if (!root) {
            return;
        }
        root.hidden = false;
        const resultBox = results();
        if (resultBox) {
            resultBox.hidden = true;
        }
        resetFooter();
        loadSummary();

        root.querySelectorAll('.logistics-mode-card').forEach(function (card) {
            if (card.dataset.costInitialized === '1') {
                return;
            }
            card.dataset.costInitialized = '1';
            const input = card.querySelector('input[name="logistics-mode"]');
            const cost = modeCosts[input ? input.value : 'balans'] || 0;
            const costNode = document.createElement('span');
            costNode.className = 'logistics-mode-cost';
            costNode.textContent = formatMoney(cost, false);
            card.appendChild(costNode);
        });
    }

    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-logistics-open]')) {
            openOptimizer();
            return;
        }
        if (event.target.closest('[data-logistics-close]')) {
            const root = modal();
            if (root) {
                root.hidden = true;
            }
            return;
        }
        if (event.target.closest('[data-logistics-confirm]')) {
            askForConfirmation();
            return;
        }
        if (event.target.closest('[data-logistics-reset]')) {
            resetFooter();
            return;
        }
        if (event.target.closest('[data-logistics-run]')) {
            runOptimizer();
            return;
        }
        const root = modal();
        if (root && event.target === root) {
            root.hidden = true;
            return;
        }
        const card = event.target.closest('#logistics-modal .logistics-mode-card');
        if (card) {
            const input = card.querySelector('input[name="logistics-mode"]');
            if (input) {
                input.checked = true;
            }
            root.querySelectorAll('.logistics-mode-card').forEach(function (item) {
                item.classList.toggle('selected', item === card);
            });
            resetFooter();
        }
    });
})();
