/**
 * Shared hub UI transport, dialogs and basic hub actions.
 * Wspolny transport, dialogi i podstawowe akcje hubow.
 */
(function () {
    'use strict';

    const api = () => window.HUB_API || '/src/HubApi.php';
    const csrf = () => window.HUB_CSRF || '';
    const lang = () => window.HUB_LANG || {};
    const currency = () => window.HUB_CURRENCY || '';

    function successToast(message, delay = 900) {
        if (typeof window.showGameToast === 'function') {
            window.showGameToast(message, 'success');
        }
        return new Promise((resolve) => setTimeout(resolve, delay));
    }

    function hubDialog(message, type = 'info') {
        const labels = lang();
        if (type === 'success') {
            return successToast(message);
        }
        const handlers = {
            warning: ['alertWarning', labels.title_warning],
            error: ['alertError', labels.title_error],
            info: ['alertInfo', labels.title_info]
        };
        const [handler, title] = handlers[type] || handlers.info;
        if (typeof window[handler] === 'function') {
            window[handler](message, title || '');
        }
        return Promise.resolve(true);
    }

    function legalPermitUrl(context) {
        const url = new URL(lang().permit_url || '/legal', window.location.origin);
        if (context && context.permit_type) {
            url.searchParams.set('permit_type', context.permit_type);
        }
        if (context && Number(context.region_id) > 0) {
            url.searchParams.set('region_id', String(Number(context.region_id)));
        }
        return url.pathname + url.search + url.hash;
    }

    function submitLegalPermit(context) {
        if (!context || !context.permit_action || Number(context.region_id) <= 0) {
            window.location.href = legalPermitUrl(context || {});
            return;
        }
        const form = document.createElement('form');
        form.method = 'post';
        form.action = legalPermitUrl(context);
        form.hidden = true;
        [
            ['csrf_token', csrf()],
            ['action', context.permit_action],
            ['region_id', String(Number(context.region_id))]
        ].forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    }

    function hubPermitModal(message, context = {}) {
        const labels = lang();
        if (typeof window.alertWithActions !== 'function') {
            hubDialog(message, 'error');
            return;
        }
        window.alertWithActions(message, labels.permit_modal_title || '', [
            { label: labels.permit_btn_cancel || '', cls: 'modal-btn--cancel', onClick: null },
            {
                label: labels.permit_btn_apply || '',
                cls: 'modal-btn--confirm',
                onClick: function () { submitLegalPermit(context); }
            },
            {
                label: labels.permit_btn_legal || '',
                cls: 'modal-btn--secondary',
                onClick: function () { window.location.href = legalPermitUrl(context); }
            }
        ], 'warning');
    }

    function hubConfirm(message, options = {}) {
        return new Promise((resolve) => {
            if (typeof window.confirmAction !== 'function') {
                resolve(false);
                return;
            }
            let settled = false;
            const finish = (result) => {
                if (!settled) {
                    settled = true;
                    resolve(result);
                }
            };
            window.confirmAction(message, () => finish(true), {
                title: options.title || lang().confirm_title || '',
                type: options.type || 'confirm',
                confirmLabel: options.confirmLabel || lang().confirm_label || ''
            });
            const overlay = document.getElementById('app-modal');
            if (!overlay) {
                return;
            }
            const cancel = overlay.querySelector('.modal-btn--cancel');
            if (cancel) {
                cancel.addEventListener('click', () => finish(false), { once: true });
            }
            const observer = new MutationObserver(() => {
                if (!overlay.classList.contains('modal-visible')) {
                    observer.disconnect();
                    finish(false);
                }
            });
            observer.observe(overlay, { attributes: true, attributeFilter: ['class'] });
        });
    }

    function closeHubModal(id) {
        const element = document.getElementById(id);
        if (element) element.hidden = true;
    }

    function openHubModal(id) {
        const element = document.getElementById(id);
        if (element) element.hidden = false;
    }

    async function hubPost(action, body = {}) {
        const form = new FormData();
        form.append('action', action);
        form.append('_token', csrf());
        Object.entries(body).forEach(([key, value]) => form.append(key, value));
        const response = await fetch(api(), { method: 'POST', body: form });
        return response.json();
    }

    async function hubGet(action, params = {}) {
        const query = new URLSearchParams({ action, ...params }).toString();
        const response = await fetch(api() + '?' + query);
        return response.json();
    }

    function reloadAfterAction(delay = 0) {
        setTimeout(() => window.location.reload(), delay);
    }

    function getOwnedHubCard(hubId) {
        return document.querySelector(`.logistics-hub-card[data-hub-id="${hubId}"]`);
    }

    function esc(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fmtPln(value, decimals = 0) {
        return Number(value).toLocaleString(window.HUB_LOCALE || undefined, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    window.HubUI = Object.freeze({
        lang,
        currency,
        hubDialog,
        hubConfirm,
        hubPermitModal,
        closeHubModal,
        openHubModal,
        hubPost,
        hubGet,
        reloadAfterAction,
        getOwnedHubCard,
        esc,
        fmtPln
    });
    window.closeHubModal = closeHubModal;
    window.hubPermitModal = hubPermitModal;

    window.hubBuildModal = function () {
        openHubModal('hub-build-modal');
    };

    window.hubBuildTypeChange = function (radio) {
        document.querySelectorAll('.logistics-mode-card').forEach((card) => card.classList.remove('selected'));
        const card = radio.closest('.logistics-mode-card');
        if (card) card.classList.add('selected');
    };

    window.hubBuildSubmit = async function (event) {
        event.preventDefault();
        const form = document.getElementById('hub-build-form');
        const button = document.getElementById('hub-build-submit');
        if (!form || !button) return;
        button.disabled = true;
        try {
            const result = await hubPost('build_hub', Object.fromEntries(new FormData(form)));
            if (!result.success) {
                await hubDialog(result.error || lang().err_generic, 'error');
                return;
            }
            closeHubModal('hub-build-modal');
            await hubDialog(result.message || lang().ok_build, 'success');
            reloadAfterAction();
        } catch (error) {
            await hubDialog(lang().err_generic, 'error');
        } finally {
            button.disabled = false;
        }
    };

    async function runHubAction(action, hubId, successKey, body = {}) {
        try {
            const result = await hubPost(action, { hub_id: hubId, ...body });
            if (!result.success) {
                await hubDialog(result.error || lang().err_generic, 'error');
                return;
            }
            let message = result.message || lang()[successKey];
            if (successKey === 'ok_upgrade' && message) {
                message = message.replace('{level}', result.new_level || '');
            }
            if (successKey === 'ok_mode' && message) {
                const mode = String(body.mode || '');
                message = message.replace('{mode}', lang()['mode_' + mode] || mode);
            }
            await hubDialog(message, 'success');
            reloadAfterAction();
        } catch (error) {
            await hubDialog(lang().err_generic, 'error');
        }
    }

    window.hubRepair = async function (hubId) {
        const card = getOwnedHubCard(hubId);
        const cost = Number(card ? card.dataset.repairCost || 0 : 0);
        const message = (lang().repair_confirm || '').replace('{cost}', fmtPln(cost));
        if (await hubConfirm(message)) await runHubAction('repair_hub', hubId, 'ok_repair');
    };

    window.hubUpgrade = async function (hubId) {
        const card = getOwnedHubCard(hubId);
        const cost = Number(card ? card.dataset.upgradeCost || 0 : 0);
        const message = (lang().upgrade_confirm || '').replace('{cost}', fmtPln(cost));
        if (await hubConfirm(message)) await runHubAction('upgrade_hub', hubId, 'ok_upgrade');
    };

    window.hubSetMode = async function (hubId, mode) {
        await runHubAction('set_mode', hubId, 'ok_mode', { mode });
    };

    window.hubTogglePause = async function (hubId, isPaused) {
        await runHubAction('toggle_pause', hubId, isPaused ? 'ok_resume' : 'ok_pause');
    };
})();
