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
        const search = document.getElementById('lhb-search');
        const count = document.getElementById('lhb-count');
        const filters = Array.from(document.querySelectorAll('[data-lhb-filter]'));
        if (!browser) return;
        let activeFilter = 'all';

        function applyFilter() {
            const query = search ? search.value.toLowerCase().trim() : '';
            const filtering = query !== '' || activeFilter !== 'all';
            let total = 0;
            let shown = 0;
            browser.classList.toggle('is-filtering', filtering);
            browser.querySelectorAll('.logistics-region-group').forEach((group) => {
                const regionName = group.dataset.regionNameLc || '';
                let groupVisible = false;
                group.querySelectorAll('[data-lhb-card]').forEach((card) => {
                    total += 1;
                    const free = Number(card.dataset.hubFree || 0);
                    const type = card.dataset.hubType || '';
                    const acquisition = card.dataset.hubAcqType || 'new';
                    const queryMatches = query === ''
                        || (card.dataset.hubNameLc || '').includes(query)
                        || regionName.includes(query);
                    const filterMatches = activeFilter === 'all'
                        || (activeFilter === 'free' && free > 0)
                        || activeFilter === type
                        || activeFilter === acquisition;
                    const visible = queryMatches && filterMatches;
                    card.hidden = !visible;
                    if (visible) {
                        shown += 1;
                        groupVisible = true;
                    }
                });
                group.hidden = !groupVisible;
                group.classList.toggle('is-filter-match', groupVisible && filtering);
            });
            if (count) {
                count.textContent = (count.dataset.filterTemplate || '')
                    .replace('{shown}', String(shown))
                    .replace('{total}', String(total));
            }
        }

        if (search) search.addEventListener('input', applyFilter);
        filters.forEach((filter) => filter.addEventListener('click', () => {
            filters.forEach((entry) => entry.classList.remove('active'));
            filter.classList.add('active');
            activeFilter = filter.dataset.lhbFilter || 'all';
            applyFilter();
        }));
        browser.addEventListener('click', (event) => {
            const toggle = event.target.closest('[data-lhb-toggle]');
            if (toggle) {
                const group = toggle.closest('.logistics-region-group');
                if (group) group.classList.toggle('is-open');
                return;
            }
            const expand = event.target.closest('[data-lhb-expand]');
            if (!expand) return;
            const group = expand.closest('.logistics-region-group');
            if (!group) return;
            const expanded = group.classList.toggle('is-expanded');
            expand.textContent = expanded ? expand.dataset.expandedLabel : expand.dataset.collapsedLabel;
        });
        applyFilter();
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
