(function () {
    'use strict';

    const api = window.PIPELINE_API || '/src/PipelineApi.php';
    const lang = window.PIPELINE_LANG || {};
    let buyWellId = 0;
    let buyType = 'standard';
    let buyProfiles = null;
    let buyConfirming = false;
    let pendingId = 0;
    let pendingAction = '';
    let pendingButton = null;

    const actionTitles = {
        repair_pipeline: lang.repair_title || lang.confirm_default || '',
        maintenance_pipeline: lang.maintenance_title || lang.confirm_default || '',
        toggle_pipeline: lang.toggle_title || lang.confirm_default || ''
    };

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return (meta && meta.content) || window.PIPELINE_CSRF || '';
    }

    function showError(message) {
        if (typeof window.alertError === 'function') {
            window.alertError(message);
            return;
        }
        if (typeof window.showGameToast === 'function') {
            window.showGameToast(message, 'error');
        }
    }

    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.hidden = false;
        }
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.hidden = true;
        }
    }

    function buyConfirmButton() {
        return document.getElementById('pipeline-buy-confirm-btn');
    }

    function buyBody() {
        return document.getElementById('pipeline-buy-modal-body');
    }

    function renderBuyProfiles() {
        const button = buyConfirmButton();
        const body = buyBody();
        if (!button || !body) {
            return;
        }

        buyConfirming = false;
        button.textContent = lang.buy_confirm_btn || '';
        button.disabled = false;
        button.hidden = false;
        body.className = '';

        let html = '<div class="logistics-pipeline-buy-types">';
        ['light', 'standard', 'heavy'].forEach(function (key) {
            const profile = buyProfiles && buyProfiles[key] ? buyProfiles[key] : null;
            if (!profile) {
                return;
            }
            const selected = key === buyType;
            const cost = Number(profile.build_cost || 0).toLocaleString(lang.locale || 'pl-PL', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            html += '<label class="logistics-mode-card' + (selected ? ' selected' : '') + '" data-pipeline-type="' + escapeHtml(key) + '">' +
                '<input type="radio" name="pipeline-type" value="' + escapeHtml(key) + '"' + (selected ? ' checked' : '') + '>' +
                '<div class="logistics-mode-name">' + escapeHtml(profile.label || key) + '</div>' +
                '<div class="logistics-mode-desc">' +
                escapeHtml(lang.label_cost || '') + ': <strong>' + cost + ' ' + escapeHtml(lang.currency || 'PLN') + '</strong><br>' +
                escapeHtml(lang.label_hours || '') + ': <strong>' + escapeHtml(profile.build_hours || 0) + 'h</strong>' +
                '</div></label>';
        });
        html += '</div>';
        body.innerHTML = html;
    }

    function openBuyModal(wellId) {
        buyWellId = Number(wellId) || 0;
        buyType = 'standard';
        buyConfirming = false;

        const button = buyConfirmButton();
        const body = buyBody();
        if (!button || !body || buyWellId <= 0) {
            return;
        }

        button.hidden = true;
        button.textContent = lang.buy_confirm_btn || '';
        body.className = 'logistics-loading';
        body.textContent = lang.loading || '';
        openModal('pipeline-buy-modal');

        if (buyProfiles) {
            renderBuyProfiles();
            return;
        }

        fetch(api + '?action=pipeline_profiles', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                buyProfiles = data.profiles || {};
                renderBuyProfiles();
            })
            .catch(function () {
                body.className = '';
                body.innerHTML = '<p class="c-bad">' + escapeHtml(lang.err || '') + '</p>';
            });
    }

    function selectBuyType(type) {
        buyType = type;
        document.querySelectorAll('#pipeline-buy-modal-body .logistics-mode-card').forEach(function (card) {
            const input = card.querySelector('input[name="pipeline-type"]');
            const selected = !!input && input.value === type;
            card.classList.toggle('selected', selected);
            if (input) {
                input.checked = selected;
            }
        });
    }

    function confirmPurchase() {
        const button = buyConfirmButton();
        const body = buyBody();
        if (!button || !body) {
            return;
        }

        if (!buyConfirming) {
            const profile = buyProfiles && buyProfiles[buyType] ? buyProfiles[buyType] : null;
            if (!profile) {
                return;
            }
            buyConfirming = true;
            button.textContent = lang.confirm_btn || '';
            const cost = Number(profile.build_cost || 0).toLocaleString(lang.locale || 'pl-PL', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            body.innerHTML = '<div class="logistics-pipeline-confirm">' +
                '<p class="logistics-confirm-title">' + escapeHtml(lang.confirm_header || '') +
                ' <strong>' + escapeHtml(profile.label || buyType) + '</strong></p>' +
                '<dl class="logistics-confirm-dl">' +
                '<dt>' + escapeHtml(lang.label_cost || '') + '</dt>' +
                '<dd><strong>' + cost + ' ' + escapeHtml(lang.currency || 'PLN') + '</strong></dd>' +
                '<dt>' + escapeHtml(lang.label_hours || '') + '</dt>' +
                '<dd><strong>' + escapeHtml(profile.build_hours || 0) + 'h</strong></dd>' +
                '</dl>' +
                '<p class="logistics-confirm-back"><button type="button" class="btn btn-link" data-pipeline-buy-back>' +
                escapeHtml(lang.back_btn || '') + '</button></p>' +
                '</div>';
            return;
        }

        button.disabled = true;
        const form = new FormData();
        form.append('_token', csrfToken());
        form.append('action', 'buy_pipeline');
        form.append('well_id', String(buyWellId));
        form.append('pipeline_type', buyType);

        fetch(api, {
            method: 'POST',
            body: form,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                button.disabled = false;
                if (data.success) {
                    buyConfirming = false;
                    body.innerHTML = '<p class="c-good">' + escapeHtml(lang.ok_started || '') + '</p>';
                    button.hidden = true;
                    setTimeout(function () { window.location.reload(); }, 4500);
                    return;
                }

                buyConfirming = false;
                button.textContent = lang.buy_confirm_btn || '';
                body.insertAdjacentHTML('beforeend', '<p class="c-bad logistics-pipeline-error">' +
                    escapeHtml(data.error || lang.err || '') + '</p>');
            })
            .catch(function () {
                button.disabled = false;
                buyConfirming = false;
                button.textContent = lang.buy_confirm_btn || '';
                body.insertAdjacentHTML('beforeend', '<p class="c-bad logistics-pipeline-error">' +
                    escapeHtml(lang.err || '') + '</p>');
            });
    }

    function openActionModal(id, action, message, button, title) {
        const modal = document.getElementById('pipeline-action-modal');
        if (!modal || Number(id) <= 0 || !action) {
            return;
        }

        pendingId = Number(id);
        pendingAction = action;
        pendingButton = button || null;
        const titleNode = modal.querySelector('.pipeline-action-modal-title');
        const messageNode = modal.querySelector('.pipeline-action-modal-msg');
        const confirmButton = modal.querySelector('[data-pipeline-action-confirm]');
        if (titleNode) {
            titleNode.textContent = title || actionTitles[action] || lang.confirm_default || '';
        }
        if (messageNode) {
            messageNode.textContent = message || '';
        }
        if (confirmButton) {
            confirmButton.disabled = false;
        }
        modal.hidden = false;
        if (confirmButton) {
            confirmButton.focus();
        }
    }

    function closeActionModal() {
        closeModal('pipeline-action-modal');
        pendingId = 0;
        pendingAction = '';
        pendingButton = null;
    }

    function executeAction() {
        if (pendingId <= 0 || !pendingAction) {
            return;
        }

        const confirmButton = document.querySelector('#pipeline-action-modal [data-pipeline-action-confirm]');
        if (confirmButton) {
            confirmButton.disabled = true;
        }

        const body = new URLSearchParams({
            action: pendingAction,
            pipeline_id: String(pendingId),
            _token: csrfToken()
        });
        const action = pendingAction;
        const actionButton = pendingButton;

        fetch(api, {
            method: 'POST',
            body: body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                closeActionModal();
                if (!data.success) {
                    showError((lang.action_error || lang.err || '') + ': ' + (data.error || '?'));
                    return;
                }
                if (action === 'toggle_pipeline' && actionButton) {
                    const suspended = data.new_status === 'suspended';
                    actionButton.dataset.suspended = suspended ? '1' : '0';
                    actionButton.textContent = suspended
                        ? actionButton.dataset.labelResume
                        : actionButton.dataset.labelSuspend;
                    actionButton.className = 'btn btn-xs ' + (suspended ? 'btn-secondary' : 'btn-danger');
                    return;
                }
                window.location.reload();
            })
            .catch(function () {
                closeActionModal();
                showError(lang.action_error || lang.err || '');
            });
    }

    document.addEventListener('click', function (event) {
        const buyOpen = event.target.closest('[data-pipeline-buy-open]');
        if (buyOpen) {
            openBuyModal(buyOpen.dataset.pipelineBuyOpen);
            return;
        }

        const buyTypeCard = event.target.closest('[data-pipeline-type]');
        if (buyTypeCard) {
            selectBuyType(buyTypeCard.dataset.pipelineType || 'standard');
            return;
        }

        if (event.target.closest('[data-pipeline-buy-back]')) {
            renderBuyProfiles();
            return;
        }

        if (event.target.closest('[data-pipeline-buy-confirm]')) {
            confirmPurchase();
            return;
        }

        const toggleButton = event.target.closest('[data-pipeline-toggle]');
        if (toggleButton) {
            const suspended = toggleButton.dataset.suspended === '1';
            openActionModal(
                toggleButton.dataset.pipelineToggle,
                'toggle_pipeline',
                suspended ? toggleButton.dataset.confirmResume : toggleButton.dataset.confirmSuspend,
                toggleButton,
                suspended ? toggleButton.dataset.labelResume : toggleButton.dataset.labelSuspend
            );
            return;
        }

        const actionButton = event.target.closest('[data-pipeline-action]');
        if (actionButton) {
            openActionModal(
                actionButton.dataset.pipelineId,
                actionButton.dataset.pipelineAction,
                actionButton.dataset.confirm || '',
                actionButton
            );
            return;
        }

        if (event.target.closest('[data-pipeline-action-confirm]')) {
            executeAction();
            return;
        }

        const closeButton = event.target.closest('[data-pipeline-modal-close]');
        if (closeButton) {
            const modalId = closeButton.dataset.pipelineModalClose || '';
            if (modalId === 'pipeline-action-modal') {
                closeActionModal();
            } else {
                closeModal(modalId);
            }
            return;
        }

        const overlay = event.target.closest('[data-pipeline-modal]');
        if (overlay && event.target === overlay) {
            if (overlay.id === 'pipeline-action-modal') {
                closeActionModal();
            } else {
                closeModal(overlay.id);
            }
        }
    });
})();
