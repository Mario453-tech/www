(function () {
    'use strict';

    const config = window.HUB_STAFFING_CONFIG || null;
    if (!config) {
        return;
    }
    const locale = config.locale === 'en' ? 'en-US' : 'pl-PL';

    function esc(value) {
        const normalized = value == null ? '' : String(value);
        return normalized
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function text(key, replacements) {
        let value = config[key] || key;
        if (replacements) {
            Object.keys(replacements).forEach(function (name) {
                value = value.replace('{' + name + '}', replacements[name]);
                value = value.replace(':' + name, replacements[name]);
            });
        }
        return value;
    }

    function formatPct(value, digits) {
        return Number(value || 0).toLocaleString(locale, {
            minimumFractionDigits: digits || 0,
            maximumFractionDigits: digits || 0
        }) + '%';
    }

    function hubName(hubId) {
        const card = document.querySelector('.logistics-hub-card[data-hub-id="' + hubId + '"]');
        return card ? (card.dataset.hubName || ('Hub #' + hubId)) : ('Hub #' + hubId);
    }

    function buildHidden(name, value) {
        return '<input type="hidden" name="' + esc(name) + '" value="' + esc(value) + '">';
    }

    function runtimeBadge(enabled) {
        const cls = enabled ? 'badge-ok' : 'badge-muted';
        const label = enabled ? text('runtime_on') : text('runtime_off');
        return '<span class="badge ' + cls + '">' + esc(label) + '</span>';
    }

    function sourceLabel(sourceType) {
        return config['source_' + sourceType] || sourceType;
    }

    function statusLabel(status) {
        return config['status_' + status] || config.status_unknown || status;
    }

    function relationLabel(status) {
        return config['relation_' + status] || status;
    }

    function roleLabel(code) {
        return config['role_' + code] || code;
    }

    function rowTone(candidate) {
        if (candidate.current_assignment_id > 0) {
            return 'is-active';
        }
        if (candidate.is_blocked || !candidate.can_assign) {
            return 'is-blocked';
        }
        return '';
    }

    function effectTone(value, positiveIsGood) {
        if (positiveIsGood) {
            return value >= 0 ? 'badge-ok' : 'badge-warn';
        }
        return value <= 0 ? 'badge-ok' : 'badge-warn';
    }

    function summaryMarkup(summary, runtimeEnabled) {
        const throughput = runtimeEnabled ? Number(summary.runtime_effects?.hub_throughput_pct || 0) : 0;
        const missing = (summary.missing_roles || []).includes('hub_operator');
        const label = text(missing ? 'staff_missing_effect' : 'staff_coverage_effect', {
            value: throughput.toLocaleString(locale, { maximumFractionDigits: 1 }),
            assigned: String(summary.assigned_count || 0), required: String(summary.required_count || 0)
        });
        return '<div class="logistics-staffing-notice"><strong>' + esc(label) + '</strong></div>';
    }

    function assignmentMarkup(assignment) {
        return '' +
            '<div class="logistics-staffing-chip">' +
                '<strong>' + esc(assignment.full_name) + '</strong>' +
                '<span>' + esc(assignment.specialization_name || text('no_specialization')) + '</span>' +
                '<span>' + esc(formatPct(assignment.allocation_pct || 0, 0)) + '</span>' +
            '</div>';
    }

    function candidateMarkup(hubId, candidate) {
        if (candidate.is_blocked || !candidate.can_assign) {
            const reason = candidate.is_blocked
                ? text('staff_blocked', { status: statusLabel(candidate.status), relation: relationLabel(candidate.relation_status) })
                : text('staff_no_allocation');
            const record = 'employee:' + candidate.source_type + ':' + Number(candidate.source_id);
            const release = Number(candidate.current_assignment_id) > 0
                ? '<form method="post" action="' + esc(config.post_url) + '">' + buildHidden('csrf_token', config.csrf_token) + buildHidden('action', 'release_hub_staff') + buildHidden('assignment_id', candidate.current_assignment_id) + '<button class="btn btn-sm btn-secondary" type="button" data-staffing-release="1" data-confirm="' + esc(text('confirm_release')) + '">' + esc(text('btn_release')) + '</button></form>' : '';
            return '<article class="logistics-staffing-row is-blocked"><div><strong>' + esc(candidate.full_name) + '</strong>' +
                '<p>' + esc(candidate.specialization_name || text('no_specialization')) + ' · ' + esc(text('free_allocation')) + ': ' + esc(formatPct(candidate.free_allocation_pct, 0)) + '</p>' +
                '<p>' + esc(reason) + '</p></div><div class="logistics-staffing-actions"><a class="btn btn-sm btn-secondary" href="/hr?tab=employees&amp;record=' + encodeURIComponent(record) + '">' + esc(text('staff_view_assignment')) + '</a>' + release + '</div></article>';
        }
        const action = 'assign_hub_staff';
        const submitLabel = candidate.current_assignment_id > 0 ? text('btn_update') : text('btn_assign');
        const confirmText = candidate.current_assignment_id > 0 ? text('confirm_update') : text('confirm_assign');
        const disabled = candidate.is_blocked || !candidate.can_assign;
        const buttonAttrs = disabled ? ' disabled' : ' data-staffing-submit="1"';
        const rawMaxAllocation = Math.max(0.01, Number(candidate.free_allocation_pct || 0));
        const defaultAllocation = candidate.current_assignment_id > 0
            ? Math.max(0.01, Number(candidate.current_allocation_pct || 0))
            : Math.max(0.01, Math.min(rawMaxAllocation, Number(candidate.free_allocation_pct || 25)));
        const maxAllocation = candidate.current_assignment_id > 0
            ? Math.max(rawMaxAllocation, defaultAllocation)
            : rawMaxAllocation;
        const releaseButton = candidate.current_assignment_id > 0
            ? '<form method="post" action="' + esc(config.post_url) + '" class="logistics-staffing-release-form">' +
                buildHidden('csrf_token', config.csrf_token) +
                buildHidden('action', 'release_hub_staff') +
                buildHidden('assignment_id', candidate.current_assignment_id) +
                '<button class="btn btn-xs btn-danger" type="button" data-staffing-release="1" data-confirm="' + esc(text('confirm_release')) + '">' + esc(text('btn_release')) + '</button>' +
              '</form>'
            : '';

        return '' +
            '<div class="logistics-staffing-row ' + rowTone(candidate) + '">' +
                '<div class="logistics-staffing-main">' +
                    '<div class="logistics-staffing-person">' +
                        '<strong>' + esc(candidate.full_name) + '</strong>' +
                        '<span>' + esc(candidate.specialization_name || text('no_specialization')) + '</span>' +
                    '</div>' +
                    '<div class="logistics-staffing-meta">' +
                        '<span class="badge badge-muted">' + esc(sourceLabel(candidate.source_type)) + '</span>' +
                        '<span class="badge badge-muted">' + esc(statusLabel(candidate.status)) + '</span>' +
                        '<span class="badge badge-muted">' + esc(relationLabel(candidate.relation_status)) + '</span>' +
                    '</div>' +
                    '<div class="logistics-staffing-stats-inline">' +
                        '<span>' + esc(text('skill')) + ': <strong>' + esc(Number(candidate.skill || 0).toLocaleString(locale, { minimumFractionDigits: 1, maximumFractionDigits: 1 })) + '/10</strong></span>' +
                        '<span>' + esc(text('morale')) + ': <strong>' + esc(formatPct(candidate.morale || 0, 0)) + '</strong></span>' +
                        '<span>' + esc(text('free_allocation')) + ': <strong>' + esc(formatPct(candidate.free_allocation_pct || 0, 0)) + '</strong></span>' +
                        (candidate.current_assignment_id > 0
                            ? '<span>' + esc(text('current_allocation')) + ': <strong>' + esc(formatPct(candidate.current_allocation_pct || 0, 0)) + '</strong></span>'
                            : '') +
                    '</div>' +
                '</div>' +
                '<div class="logistics-staffing-actions">' +
                    '<form method="post" action="' + esc(config.post_url) + '" class="logistics-staffing-form">' +
                        buildHidden('csrf_token', config.csrf_token) +
                        buildHidden('action', action) +
                        buildHidden('hub_id', hubId) +
                        buildHidden('source_type', candidate.source_type) +
                        buildHidden('source_id', candidate.source_id) +
                        '<label class="logistics-staffing-allocation">' +
                            '<span>' + esc(text('allocation')) + '</span>' +
                            '<input type="number" name="allocation_pct" min="0.01" max="' + esc(String(maxAllocation.toFixed(2))) + '" step="0.01" value="' + esc(String(defaultAllocation.toFixed(2))) + '"' + (disabled ? ' disabled' : '') + '>' +
                        '</label>' +
                        '<button class="btn btn-xs btn-primary" type="button"' + buttonAttrs + ' data-confirm="' + esc(confirmText) + '">' + esc(submitLabel) + '</button>' +
                    '</form>' +
                    releaseButton +
                '</div>' +
            '</div>';
    }

    function renderHubStaffing(hubId) {
        const hubData = config.hubs && config.hubs[String(hubId)] ? config.hubs[String(hubId)] : (config.hubs ? config.hubs[hubId] : null);
        const modal = document.getElementById('hub-staffing-modal');
        const body = document.getElementById('hub-staffing-modal-body');
        const title = document.getElementById('hub-staffing-modal-title');
        if (!modal || !body || !title || !hubData) {
            return;
        }

        const activeAssignments = Array.isArray(hubData.active_assignments) ? hubData.active_assignments : [];
        const candidates = Array.isArray(hubData.candidates) ? hubData.candidates : [];
        const summary = hubData.summary || {};

        title.textContent = text('heading') + ' - ' + hubName(hubId);
        let html = summaryMarkup(summary, !!hubData.runtime_enabled);
        const available = candidates.filter(candidate => !candidate.is_blocked && candidate.can_assign);
        const busy = candidates.filter(candidate => candidate.is_blocked || !candidate.can_assign);
        html += '<section class="logistics-staffing-section"><h4>' + esc(text('staff_available')) + ' (' + available.length + ')</h4>';
        html += available.length ? '<div class="logistics-staffing-list">' + available.map(candidate => candidateMarkup(hubId, candidate)).join('') + '</div>' : '<p>' + esc(text('staff_none_free')) + '</p>';
        html += '</section>';
        if (busy.length) html += '<section class="logistics-staffing-section"><h4>' + esc(text('staff_busy')) + ' (' + busy.length + ')</h4><div class="logistics-staffing-list">' + busy.map(candidate => candidateMarkup(hubId, candidate)).join('') + '</div></section>';
        if (!candidates.length) html += '<a class="btn btn-sm btn-primary" href="' + esc(text('recruit_url')) + '">' + esc(text('recruit_operator')) + '</a>';

        body.innerHTML = html;
        if (typeof window.applyLogisticsProgressWidths === 'function') {
            window.applyLogisticsProgressWidths(body);
        }
        if (window.HubUI) window.HubUI.openHubModal('hub-staffing-modal');
        else modal.hidden = false;
    }

    function confirmAndSubmit(button, fallbackText) {
        const form = button.closest('form');
        if (!form) {
            return;
        }
        const message = button.dataset.confirm || fallbackText || '';
        if (typeof window.confirmAction !== 'function') {
            form.submit();
            return;
        }
        window.confirmAction(message, function () {
            form.submit();
        }, {
            title: text('heading'),
            confirmLabel: button.textContent.trim()
        });
    }

    window.hubStaffingModal = function (hubId) {
        renderHubStaffing(Number(hubId));
    };

    function handleFlash() {
        const flashNode = document.getElementById('hub-staffing-flash');
        if (!flashNode) {
            return;
        }

        const message = flashNode.dataset.message || '';
        const type = flashNode.dataset.type || 'success';
        flashNode.remove();
        if (!message) {
            return;
        }

        if (type === 'error') {
            if (typeof window.alertError === 'function') {
                window.alertError(message);
                return;
            }
            if (typeof window.showGameToast === 'function') {
                window.showGameToast(message, 'error');
                return;
            }
            if (typeof window.alertWarning === 'function') {
                window.alertWarning(message);
                return;
            }
        }

        if (typeof window.alertInfo === 'function') {
            window.alertInfo(message);
            return;
        }
        if (typeof window.showGameToast === 'function') {
            window.showGameToast(message, 'success');
        }
    }

    document.addEventListener('click', function (event) {
        const submitButton = event.target.closest('[data-staffing-submit]');
        if (submitButton) {
            confirmAndSubmit(submitButton, text('confirm_assign'));
            return;
        }

        const releaseButton = event.target.closest('[data-staffing-release]');
        if (releaseButton) {
            confirmAndSubmit(releaseButton, text('confirm_release'));
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', handleFlash);
    } else {
        handleFlash();
    }
})();
