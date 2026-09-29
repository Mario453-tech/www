/**
 * Delegated hub controls, market browser and cooldown timers.
 * Delegowane kontrolki hubow, przegladarka rynku i liczniki odnowienia.
 */
(function () {
    'use strict';

    const Hub = window.HubUI;
    if (!Hub) return;

    document.addEventListener('click', function (event) {
        const closeButton = event.target.closest('[data-hub-modal-close]');
        if (closeButton) {
            Hub.closeHubModal(closeButton.dataset.hubModalClose || '');
            return;
        }
        const overlay = event.target.closest('[data-hub-modal]');
        if (overlay && event.target === overlay) {
            Hub.closeHubModal(overlay.id);
            return;
        }
        const button = event.target.closest('[data-hub-action]');
        if (!button || button.disabled) return;
        const hubId = Number(button.dataset.hubId || 0);
        const wellId = Number(button.dataset.wellId || 0);
        const actions = {
            'buy-new': () => window.hubBuyNewModal(),
            'buy-used': () => window.hubBuyUsed(hubId),
            rent: () => window.hubRent(hubId),
            'assign-well': () => window.hubAssignWellToHubModal(hubId),
            staffing: () => typeof window.hubStaffingModal === 'function' && window.hubStaffingModal(hubId),
            wells: () => window.hubWellsModal(hubId),
            upgrade: () => window.hubUpgrade(hubId),
            assign: () => window.hubAssignModal(wellId),
            detach: () => window.hubDetachWell(wellId),
            'transfer-modal': () => window.hubTransferModal(wellId, hubId),
            'do-assign': () => window.hubDoAssign(wellId, hubId, Number(button.dataset.fee || 0), button),
            'assign-page': () => window.hubAssignModal(wellId, Number(button.dataset.page || 1)),
            'do-transfer': () => window.hubDoTransfer(wellId, hubId, button)
        };
        const handler = actions[button.dataset.hubAction || ''];
        if (handler) handler();
    });

    document.addEventListener('change', function (event) {
        const radio = event.target.closest('[data-hub-buy-type]');
        if (radio && typeof window.hubBuyNewTypeChange === 'function') {
            window.hubBuyNewTypeChange(radio);
        }
    });

    const buyForm = document.getElementById('hub-buy-new-form');
    if (buyForm) buyForm.addEventListener('submit', window.hubBuyNewSubmit);

    function initAvailableHubsBrowser() {
        const browser = document.getElementById('lhb-browser');
        if (!browser) return;
        browser.addEventListener('click', (event) => {
            const toggle = event.target.closest('[data-lhb-toggle]');
            if (toggle) {
                const group = toggle.closest('.logistics-region-group');
                if (group) {
                    const isOpen = group.classList.toggle('is-open');
                    toggle.setAttribute('aria-expanded', String(isOpen));
                }
                return;
            }
        });
    }

    function initCooldownTimers() {
        const badges = document.querySelectorAll('.hub-cooldown-badge[data-cooldown-until]');
        if (badges.length === 0) return;
        const tick = () => badges.forEach((badge) => {
            const seconds = Math.max(0, Math.floor((new Date(badge.dataset.cooldownUntil).getTime() - Date.now()) / 1000));
            if (seconds <= 0) {
                badge.textContent = Hub.lang().cooldown_zero || '';
                badge.classList.add('is-expired');
                if (!badge.dataset.expiredHandled) {
                    badge.dataset.expiredHandled = '1';
                    setTimeout(() => window.location.reload(), 1500);
                }
                return;
            }
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const template = hours > 0
                ? Hub.lang().cooldown_hours_minutes
                : Hub.lang().cooldown_minutes;
            badge.textContent = String(template || '')
                .replace('{hours}', String(hours))
                .replace('{minutes}', String(minutes));
        });
        tick();
        setInterval(tick, 60000);
    }

    initAvailableHubsBrowser();
    initCooldownTimers();
})();
