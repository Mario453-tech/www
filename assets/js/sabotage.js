(() => {
    'use strict';
    const root = document.querySelector('.sabotage-page');
    if (!root) return;
    const one = selector => root.querySelector(selector);
    const actions = [...root.querySelectorAll('[data-sabotage-action]')];
    const targets = [...root.querySelectorAll('[data-sabotage-target]')];
    const form = one('#sabotage-action-form');
    const execute = one('[data-sabotage-execute]');
    const dialog = one('#sabotage-confirm-dialog');
    if (!form || !execute || !dialog) return;
    let action = null;
    let target = null;
    let submitted = false;
    const formatter = new Intl.NumberFormat(document.documentElement.lang || 'pl-PL', { maximumFractionDigits: 2 });
    const formatCost = amount => `${formatter.format(Number(amount))} ${root.dataset.sabotageCurrency}`;

    function refresh() {
        actions.forEach(button => button.setAttribute('aria-pressed', String(button === action)));
        targets.forEach(button => button.setAttribute('aria-pressed', String(button === target)));
        one('[data-sabotage-chosen-action]').textContent = action?.dataset.name || root.dataset.sabotageEmpty;
        one('[data-sabotage-chosen-description]').textContent = action?.dataset.description || '';
        const risk = one('[data-sabotage-chosen-risk]');
        risk.hidden = !action;
        risk.textContent = action?.dataset.severity || '';
        risk.className = `sabotage-risk sabotage-risk--${action?.dataset.risk || 'low'}`;
        one('[data-sabotage-chosen-target]').textContent = target?.dataset.name || root.dataset.sabotageEmpty;
        const quotes = target ? JSON.parse(target.dataset.quotes || '{}') : {};
        const cooldowns = target ? JSON.parse(target.dataset.cooldowns || '{}') : {};
        const cost = action ? quotes[action.dataset.id] : undefined;
        one('[data-sabotage-chance]').textContent = action?.dataset.chance || '—';
        one('[data-sabotage-cost]').textContent = cost === undefined ? '—' : formatCost(cost);
        one('[data-sabotage-cooldown]').textContent = action ? `${action.dataset.cooldown} min` : '—';
        let reason = '';
        if (action && target) {
            if (target.dataset.status !== 'active') reason = root.dataset.sabotageInactive;
            else if (Number(action.dataset.market) && Number(root.dataset.sabotageMarketScore) < Number(root.dataset.sabotageMarketThreshold)) reason = root.dataset.sabotageMarket;
            else if (cooldowns[action.dataset.id]) reason = `${root.dataset.sabotageCooldown} ${cooldowns[action.dataset.id]}`;
            else if (cost === undefined || Number(cost) > Number(root.dataset.sabotageCash)) reason = root.dataset.sabotageFunds;
        }
        one('[data-sabotage-disabled]').textContent = reason;
        execute.disabled = !action || !target || Boolean(reason) || submitted;
        form.elements.option_id.value = action?.dataset.id || '';
        form.elements.target_player_id.value = target?.dataset.id || '';
    }

    actions.forEach(button => button.addEventListener('click', () => { action = button; refresh(); }));
    targets.forEach(button => button.addEventListener('click', () => { if (!button.disabled) { target = button; refresh(); } }));
    const preselected = targets.find(button => !button.disabled && button.dataset.id === root.dataset.sabotageSelectedTarget);
    if (preselected) target = preselected;
    refresh();

    if (execute && dialog) {
        execute.addEventListener('click', () => {
            if (execute.disabled) return;
            one('[data-sabotage-confirm-action]').textContent = action.dataset.name;
            one('[data-sabotage-confirm-target]').textContent = target.dataset.name;
            one('[data-sabotage-confirm-cost]').textContent = one('[data-sabotage-cost]').textContent;
            one('[data-sabotage-confirm-cooldown]').textContent = one('[data-sabotage-cooldown]').textContent;
            dialog.showModal();
            one('[data-sabotage-cancel]').focus();
        });
        one('[data-sabotage-cancel]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('close', () => execute.focus());
        one('[data-sabotage-confirm]').addEventListener('click', () => {
            if (submitted || execute.disabled) return;
            submitted = true;
            one('[data-sabotage-confirm]').disabled = true;
            execute.disabled = true;
            form.requestSubmit();
        });
    }

    const search = one('[data-sabotage-search]');
    const suggestions = one('#sabotage-suggestions');
    const status = one('[data-sabotage-status]');
    if (!search || !suggestions) return;
    let sequence = 0;
    let timer;
    function hideSuggestions() {
        suggestions.hidden = true;
        suggestions.replaceChildren();
        search.setAttribute('aria-expanded', 'false');
    }
    async function suggest(query, requestId) {
        const url = new URL(root.dataset.sabotageSuggestUrl, window.location.href);
        url.searchParams.set('suggest', query);
        try {
            const response = await fetch(url, { credentials: 'same-origin' });
            if (!response.ok) throw new Error('Suggestion request failed');
            const matches = await response.json();
            if (requestId !== sequence || query !== search.value.trim()) return;
            suggestions.replaceChildren();
            for (const match of matches) {
                const button = document.createElement('button');
                button.type = 'button';
                button.setAttribute('role', 'option');
                button.textContent = match.company_name || match.username;
                button.addEventListener('click', () => {
                    const link = new URL(root.dataset.sabotageSuggestUrl, window.location.href);
                    link.searchParams.set('q', match.company_name || match.username);
                    link.searchParams.set('status', 'active');
                    link.searchParams.set('selected', String(match.id));
                    window.location.assign(link.href);
                });
                suggestions.append(button);
            }
            suggestions.hidden = matches.length === 0;
            search.setAttribute('aria-expanded', String(matches.length > 0));
        } catch { if (requestId === sequence) hideSuggestions(); }
    }
    search.addEventListener('input', () => {
        clearTimeout(timer);
        sequence++;
        const query = search.value.trim();
        hideSuggestions();
        if (query.length >= 1 && status?.value !== 'inactive') {
            const requestId = sequence;
            timer = setTimeout(() => suggest(query, requestId), 160);
        }
    });
    search.addEventListener('keydown', event => {
        if (event.key === 'Escape') hideSuggestions();
        if (event.key === 'ArrowDown' && !suggestions.hidden) {
            event.preventDefault();
            suggestions.querySelector('[role="option"]')?.focus();
        }
    });
    suggestions.addEventListener('keydown', event => {
        const options = [...suggestions.querySelectorAll('[role="option"]')];
        const index = options.indexOf(document.activeElement);
        if (event.key === 'Escape') { hideSuggestions(); search.focus(); }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            (options[index + (event.key === 'ArrowDown' ? 1 : -1)] || search).focus();
        }
    });
    root.addEventListener('click', event => {
        if (!event.target.closest('.sabotage-search-wrap')) hideSuggestions();
    });
    status?.addEventListener('change', () => one('[data-sabotage-search-form]')?.requestSubmit());
})();
