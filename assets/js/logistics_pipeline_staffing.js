(function () {
    'use strict';

    const config = window.PIPELINE_STAFFING_CONFIG || null;
    if (!config) {
        return;
    }

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function hidden(name, value) {
        return '<input type="hidden" name="' + esc(name) + '" value="' + esc(value) + '">';
    }

    function roleLabel(roleCode) {
        return config['role_' + roleCode] || roleCode;
    }

    function percent(value) {
        return Number(value || 0).toLocaleString(document.documentElement.lang === 'en' ? 'en-US' : 'pl-PL', {
            maximumFractionDigits: 1
        }) + '%';
    }

    function summaryMarkup(summary) {
        const operational = !!summary.is_operational;
        return '<div class="pipeline-staffing-modal-summary">' +
            '<span class="badge ' + (operational ? 'badge-ok' : 'badge-muted') + '">' +
                esc(operational ? config.effects_on : config.effects_off) +
            '</span>' +
            '<div class="pipeline-staffing-coverage-grid">' +
                '<div><span>' + esc(roleLabel('pipeline_engineer')) + '</span><strong>' +
                    esc(percent(summary.engineer_coverage_pct)) + '</strong></div>' +
                '<div><span>' + esc(roleLabel('pipeline_logistics_specialist')) + '</span><strong>' +
                    esc(percent(summary.logistics_coverage_pct)) + '</strong></div>' +
            '</div>' +
            (!operational ? '<p class="pipeline-staffing-notice">' + esc(config.non_operational) + '</p>' : '') +
        '</div>';
    }

    function assignmentMarkup(assignment) {
        return '<div class="pipeline-staffing-assignment">' +
            '<div><strong>' + esc(assignment.full_name) + '</strong><span>' +
                esc(roleLabel(assignment.role_code)) + '</span></div>' +
            '<strong>' + esc(percent(assignment.allocation_pct)) + '</strong>' +
        '</div>';
    }

    function candidateMarkup(pipelineId, candidate, operational) {
        const existing = Number(candidate.current_assignment_id || 0) > 0;
        const disabled = !operational || !!candidate.is_blocked || (!candidate.can_assign && !existing);
        const current = Math.max(0.01, Number(candidate.current_allocation_pct || 0));
        const available = Math.max(0.01, Number(candidate.free_allocation_pct || 0));
        const value = existing ? current : Math.min(25, available);
        const max = Math.max(value, available);
        const confirmText = existing ? config.confirm_update : config.confirm_assign;
        const submitLabel = existing ? config.btn_update : config.btn_assign;
        const release = existing
            ? '<form method="post" action="' + esc(config.post_url) + '">' +
                hidden('csrf_token', config.csrf_token) +
                hidden('action', 'release_pipeline_staff') +
                hidden('assignment_id', candidate.current_assignment_id) +
                '<button class="btn btn-xs btn-danger" type="button" data-pipeline-staffing-submit ' +
                    'data-confirm="' + esc(config.confirm_release) + '">' + esc(config.btn_release) + '</button>' +
              '</form>'
            : '';

        return '<div class="pipeline-staffing-candidate' + (disabled ? ' is-blocked' : '') + '">' +
            '<div class="pipeline-staffing-person"><strong>' + esc(candidate.full_name) + '</strong>' +
                '<span>' + esc(roleLabel(candidate.role_code)) + '</span>' +
                '<small>' + esc(config.free_allocation) + ': ' + esc(percent(candidate.free_allocation_pct)) +
                    (existing ? ' · ' + esc(config.current_allocation) + ': ' + esc(percent(candidate.current_allocation_pct)) : '') +
                '</small></div>' +
            '<div class="pipeline-staffing-candidate-actions">' +
                '<form method="post" action="' + esc(config.post_url) + '">' +
                    hidden('csrf_token', config.csrf_token) +
                    hidden('action', 'assign_pipeline_staff') +
                    hidden('pipeline_id', pipelineId) +
                    hidden('source_type', candidate.source_type) +
                    hidden('source_id', candidate.source_id) +
                    '<label><span>' + esc(config.allocation) + '</span>' +
                        '<input type="number" name="allocation_pct" min="0.01" max="' + esc(max.toFixed(2)) +
                        '" step="0.01" value="' + esc(value.toFixed(2)) + '"' + (disabled ? ' disabled' : '') + '></label>' +
                    '<button class="btn btn-xs btn-primary" type="button" data-pipeline-staffing-submit ' +
                        'data-confirm="' + esc(confirmText) + '"' + (disabled ? ' disabled' : '') + '>' +
                        esc(submitLabel) + '</button>' +
                '</form>' + release +
            '</div>' +
        '</div>';
    }

    function openModal(pipelineId) {
        const data = config.pipelines && (config.pipelines[String(pipelineId)] || config.pipelines[pipelineId]);
        const modal = document.getElementById('pipeline-staffing-modal');
        const body = document.getElementById('pipeline-staffing-modal-body');
        const title = document.getElementById('pipeline-staffing-modal-title');
        if (!data || !modal || !body || !title) {
            return;
        }

        const summary = data.summary || {};
        const active = Array.isArray(data.active_assignments) ? data.active_assignments : [];
        const candidates = Array.isArray(data.candidates) ? data.candidates : [];
        title.textContent = config.heading + ' #' + pipelineId;

        let html = '<p class="pipeline-staffing-subtitle">' + esc(config.subtitle) + '</p>' + summaryMarkup(summary);
        html += '<section><h4>' + esc(config.active_team) + '</h4>';
        html += active.length > 0
            ? '<div class="pipeline-staffing-active-list">' + active.map(assignmentMarkup).join('') + '</div>'
            : '<p class="pipeline-staffing-empty">' + esc(config.empty_active) + '</p>';
        html += '</section><section><h4>' + esc(config.available_team) + '</h4>';
        html += candidates.length > 0
            ? '<div class="pipeline-staffing-candidate-list">' + candidates.map(function (candidate) {
                return candidateMarkup(pipelineId, candidate, !!summary.is_operational);
            }).join('') + '</div>'
            : '<p class="pipeline-staffing-empty">' + esc(config.empty) + '</p>';
        html += '</section>';
        body.innerHTML = html;
        modal.hidden = false;
    }

    function confirmAndSubmit(button) {
        const form = button.closest('form');
        if (!form || button.disabled) {
            return;
        }
        if (typeof window.confirmAction !== 'function') {
            form.submit();
            return;
        }
        window.confirmAction(button.dataset.confirm || '', function () {
            form.submit();
        }, {
            title: config.heading,
            confirmLabel: button.textContent.trim()
        });
    }

    document.addEventListener('click', function (event) {
        const opener = event.target.closest('[data-pipeline-staffing-open]');
        if (opener) {
            openModal(Number(opener.dataset.pipelineStaffingOpen));
            return;
        }
        const submitter = event.target.closest('[data-pipeline-staffing-submit]');
        if (submitter) {
            confirmAndSubmit(submitter);
        }
    });
})();
