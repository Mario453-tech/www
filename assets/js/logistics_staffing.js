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
        const throughput = Number((summary.runtime_effects && summary.runtime_effects.hub_throughput_pct) || 0);
        const incident = (Number((summary.runtime_incident_mods && summary.runtime_incident_mods.incident_mult) || 1) - 1) * 100;
        const maintenance = (Number(summary.maintenance_cost_mult || 1) - 1) * 100;
        const missing = Array.isArray(summary.missing_roles) && summary.missing_roles.length > 0
            ? summary.missing_roles.map(roleLabel).join(', ')
            : '';
        const coverage = Math.max(0, Math.min(100, Number(summary.coverage_pct || 0)));
        const fillTone = coverage >= 100 ? 'good' : (coverage >= 60 ? 'warn' : 'bad');

        return '' +
            '<div class="logistics-hub-staffing-modal-summary">' +
                '<div class="logistics-hub-staffing-head">' +
                    '<div>' +
                        '<span class="logistics-hub-staffing-label">' + esc(text('coverage')) + '</span>' +
                        '<strong>' + esc(formatPct(summary.coverage_pct || 0, 0)) + '</strong>' +
                    '</div>' +
                    runtimeBadge(runtimeEnabled) +
                '</div>' +
                '<div class="logistics-hub-staffing-bar" role="progressbar" aria-valuenow="' + Math.round(coverage) + '" aria-valuemin="0" aria-valuemax="100">' +
                    '<div class="logistics-hub-staffing-fill logistics-hub-staffing-fill--' + fillTone + '" data-progress-width="' + coverage + '"></div>' +
                '</div>' +
                '<div class="logistics-hub-staffing-stats">' +
                    '<div class="logistics-hub-staffing-stat"><span>' + esc(text('coverage')) + '</span><strong>' + esc(String(summary.assigned_count || 0) + '/' + String(summary.required_count || 0)) + '</strong></div>' +
                    '<div class="logistics-hub-staffing-stat"><span>' + esc(text('skill')) + '</span><strong>' + esc(Number(summary.average_skill || 0).toLocaleString(locale, { minimumFractionDigits: 1, maximumFractionDigits: 1 })) + '/10</strong></div>' +
                    '<div class="logistics-hub-staffing-stat"><span>' + esc(text('morale')) + '</span><strong>' + esc(formatPct(summary.average_morale || 0, 0)) + '</strong></div>' +
                '</div>' +
                '<div class="logistics-hub-staffing-effects">' +
                    '<span class="badge ' + effectTone(throughput, true) + '">' + esc(text('effect_throughput', { value: Number(throughput).toLocaleString(locale, { minimumFractionDigits: 1, maximumFractionDigits: 1 }) })) + '</span>' +
                    '<span class="badge ' + effectTone(incident, false) + '">' + esc(text('effect_incident', { value: Number(incident).toLocaleString(locale, { minimumFractionDigits: 1, maximumFractionDigits: 1 }) })) + '</span>' +
                    '<span class="badge ' + effectTone(maintenance, false) + '">' + esc(text('effect_maintenance', { value: Number(maintenance).toLocaleString(locale, { minimumFractionDigits: 1, maximumFractionDigits: 1 }) })) + '</span>' +
                '</div>' +
                (missing
                    ? '<div class="logistics-hub-staffing-missing"><span>' + esc(text('missing_roles')) + '</span><strong>' + esc(missing) + '</strong></div>'
                    : '') +
            '</div>';
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
        let html = '' +
            '<div class="logistics-staffing-modal-intro">' +
                '<p>' + esc(text('subtitle')) + '</p>' +
            '</div>' +
            summaryMarkup(summary, !!hubData.runtime_enabled);

        html += '<section class="logistics-staffing-section"><h4>' + esc(text('active_team')) + '</h4>';
        if (activeAssignments.length > 0) {
            html += '<div class="logistics-staffing-chip-list">';
            activeAssignments.forEach(function (assignment) {
                html += assignmentMarkup(assignment);
            });
            html += '</div>';
        } else {
            html += '<div class="logistics-hub-staffing-empty">' + esc(text('empty_card')) + '</div>';
        }
        html += '</section>';

        html += '<section class="logistics-staffing-section"><h4>' + esc(text('available_team')) + '</h4>';
        if (candidates.length > 0) {
            html += '<div class="logistics-staffing-list">';
            candidates.forEach(function (candidate) {
                html += candidateMarkup(hubId, candidate);
            });
            html += '</div>';
        } else {
            html += '<div class="logistics-hub-staffing-empty">' + esc(text('empty')) + '</div>';
        }
        html += '</section>';

        body.innerHTML = html;
        if (typeof window.applyLogisticsProgressWidths === 'function') {
            window.applyLogisticsProgressWidths(body);
        }
        modal.hidden = false;
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
