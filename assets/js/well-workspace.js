(function () {
    'use strict';

    var workspace = document.querySelector('[data-well-workspace]');
    if (!workspace) return;

    var csrf = workspace.dataset.csrf || '';
    var message = workspace.querySelector('[data-well-message]');
    var selectedKey = 'oilEmpire.wellWorkspace.selected';
    var tabKeyPrefix = 'oilEmpire.wellWorkspace.tab.';

    function announce(text, isError) {
        if (!message) return;
        message.textContent = text || '';
        message.classList.toggle('ww-message--error', Boolean(isError));
        if (text) message.focus({preventScroll: true});
    }

    function rememberWell(id) {
        try { sessionStorage.setItem(selectedKey, String(id)); } catch (error) {}
    }

    function rememberTab(wellId, tab) {
        try { sessionStorage.setItem(tabKeyPrefix + wellId, tab); } catch (error) {}
    }

    function rememberedTab(wellId) {
        try { return sessionStorage.getItem(tabKeyPrefix + wellId) || 'overview'; } catch (error) { return 'overview'; }
    }

    function activeWell() {
        return workspace.querySelector('[data-well]:not([hidden])');
    }

    function showWell(id, shouldFocus) {
        var target = workspace.querySelector('[data-well="' + CSS.escape(String(id)) + '"]');
        if (!target) return;
        workspace.querySelectorAll('[data-well]').forEach(function (well) {
            well.hidden = well !== target;
        });
        workspace.querySelectorAll('[data-well-select]').forEach(function (button) {
            var selected = button.dataset.wellSelect === String(id);
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            if (selected && shouldFocus) button.focus({preventScroll: true});
        });
        rememberWell(id);
        openTab(target, rememberedTab(id), false);
    }

    function openTab(well, name, shouldFocus) {
        var tab = well.querySelector('[data-well-tab="' + CSS.escape(name) + '"]');
        var panel = well.querySelector('[data-well-panel="' + CSS.escape(name) + '"]');
        if (!tab || !panel) return;
        well.querySelectorAll('[data-well-tab]').forEach(function (item) {
            var current = item === tab;
            item.setAttribute('aria-selected', current ? 'true' : 'false');
            item.tabIndex = current ? 0 : -1;
        });
        well.querySelectorAll('[data-well-panel]').forEach(function (item) {
            item.hidden = item !== panel;
        });
        rememberTab(well.dataset.well, name);
        if (shouldFocus) tab.focus({preventScroll: true});
        if (name === 'history') loadHistory(well, 1);
    }

    function confirmAction(text, callback, options) {
        if (typeof window.confirmAction !== 'function') {
            announce(workspace.dataset.error || '', true);
            return;
        }
        window.confirmAction(text, callback, options || {});
    }

    function setPending(button, pending) {
        button.dataset.pending = pending ? '1' : '';
        button.disabled = pending || button.dataset.originalDisabled === '1';
        button.setAttribute('aria-busy', pending ? 'true' : 'false');
    }

    function responseMessage(data) {
        return (data && (data.message || data.error)) || workspace.dataset.error || '';
    }

    async function postAction(button, endpoint, payload) {
        if (!endpoint || button.dataset.pending === '1') return;
        setPending(button, true);
        announce(workspace.dataset.loading || '');
        var body = new URLSearchParams();
        Object.keys(payload).forEach(function (key) { body.set(key, String(payload[key])); });
        body.set('csrf_token', csrf);
        body.set('_token', csrf);
        body.set('csrf', csrf);
        try {
            var response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: body
            });
            var data = await response.json();
            if (!response.ok || data.success === false || data.error) throw new Error(responseMessage(data));
            announce(responseMessage(data), false);
            window.location.reload();
        } catch (error) {
            announce(error.message || workspace.dataset.error || '', true);
            setPending(button, false);
        }
    }

    function historyState(well) {
        if (!well._historyState) well._historyState = {page: 1, filter: 'all', loading: false, hasMore: false, totalPages: 1, totalEvents: 0, pendingRequest: null};
        return well._historyState;
    }

    function updateHistoryControls(well) {
        var state = historyState(well);
        var newer = well.querySelector('[data-history-direction="-1"]');
        var older = well.querySelector('[data-history-direction="1"]');
        var retry = well.querySelector('[data-history-retry]');
        if (newer) newer.disabled = state.loading || state.page <= 1;
        if (older) older.disabled = state.loading || !state.hasMore;
        if (retry) retry.disabled = state.loading;
        well.querySelectorAll('[data-history-page-target]').forEach(function (button) {
            button.disabled = state.loading || Number(button.dataset.historyPageTarget) === state.page;
        });
    }

    function renderHistoryPages(well, state) {
        var pages = well.querySelector('[data-history-pages]');
        if (!pages) return;
        pages.replaceChildren();
        var start = Math.max(1, Math.min(state.page - 2, state.totalPages - 4));
        var end = Math.min(state.totalPages, start + 4);
        for (var page = start; page <= end; page += 1) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'ww-page-number';
            button.dataset.historyPageTarget = String(page);
            button.textContent = String(page);
            button.setAttribute('aria-label', (workspace.dataset.pageLabel || '') + ' ' + page);
            if (page === state.page) button.setAttribute('aria-current', 'page');
            pages.appendChild(button);
        }
    }

    function renderHistory(well, result) {
        var container = well.querySelector('[data-well-history]');
        var pageLabel = well.querySelector('[data-history-page]');
        if (!container) return;
        container.replaceChildren();
        if (!result.events || result.events.length === 0) {
            var empty = document.createElement('p');
            empty.className = 'ww-empty';
            empty.textContent = workspace.dataset.empty || '';
            container.appendChild(empty);
        } else {
            var list = document.createElement('ol');
            list.className = 'ww-history-list';
            result.events.forEach(function (event) {
                var item = document.createElement('li');
                item.className = 'ww-history-entry';
                var iconName = ['status-krytyczny', 'repair', 'upgrade', 'flow', 'truck', 'history'].includes(event.icon) ? event.icon : 'history';
                var icon = document.createElement('span');
                icon.className = 'ww-history-event-icon ww-history-event-icon--' + iconName;
                icon.setAttribute('aria-hidden', 'true');
                var body = document.createElement('div');
                var title = document.createElement('strong');
                var meta = document.createElement('small');
                var description = document.createElement('p');
                var time = document.createElement('time');
                title.textContent = event.title || '';
                meta.textContent = event.category || '';
                description.textContent = event.description || '';
                time.textContent = event.date || '';
                if (event.date) time.dateTime = String(event.date).replace(' ', 'T');
                body.append(title, meta, description);
                item.append(icon, body, time);
                list.appendChild(item);
            });
            container.appendChild(list);
        }
        var state = historyState(well);
        state.page = Number(result.page || state.page);
        state.filter = result.filter || state.filter;
        state.totalPages = Math.max(1, Number(result.total_pages || 1));
        state.totalEvents = Math.max(0, Number(result.total_events || 0));
        state.hasMore = state.page < state.totalPages;
        if (pageLabel) pageLabel.textContent = (workspace.dataset.pageLabel || '') + ' ' + state.page + ' / ' + state.totalPages;
        var countLabel = well.querySelector('[data-history-count]');
        if (countLabel) countLabel.textContent = String(state.totalEvents);
        renderHistoryPages(well, state);
        updateHistoryControls(well);
    }

    async function loadHistory(well, page, requestedFilter) {
        var state = historyState(well);
        var filter = well.querySelector('[data-well-history-filter]');
        var targetFilter = requestedFilter || (filter ? filter.value : state.filter);
        var request = {page: Math.max(1, page), filter: targetFilter};
        if (state.loading) {
            state.pendingRequest = request;
            return;
        }
        state.loading = true;
        updateHistoryControls(well);
        var container = well.querySelector('[data-well-history]');
        if (container) container.textContent = workspace.dataset.loading || '';
        try {
            var query = new URLSearchParams({well_id: well.dataset.well, filter: request.filter, page: String(request.page)});
            var response = await fetch('/public/well_history.php?' + query.toString(), {credentials: 'same-origin', headers: {'Accept': 'application/json'}});
            var data = await response.json();
            if (!response.ok || data.error) throw new Error(responseMessage(data));
            if (state.pendingRequest === null) renderHistory(well, data);
        } catch (error) {
            if (container && state.pendingRequest === null) container.textContent = error.message || workspace.dataset.error || '';
        } finally {
            state.loading = false;
            if (state.pendingRequest !== null) {
                var pendingRequest = state.pendingRequest;
                state.pendingRequest = null;
                loadHistory(well, pendingRequest.page, pendingRequest.filter);
            } else {
                updateHistoryControls(well);
            }
        }
    }

    async function sellWell(button) {
        var well = button.closest('[data-well]');
        if (!well || button.dataset.pending === '1') return;
        setPending(button, true);
        announce(workspace.dataset.loading || '');
        try {
            var response = await fetch('/src/WellSellApi.php?well_id=' + encodeURIComponent(well.dataset.well), {credentials: 'same-origin', headers: {'Accept': 'application/json'}});
            var valuation = await response.json();
            if (!response.ok || valuation.error) throw new Error(responseMessage(valuation));
            var formatted = new Intl.NumberFormat(document.documentElement.lang || undefined, {maximumFractionDigits: 2}).format(Number(valuation.sell_value || 0));
            var text = [well.dataset.wellName || '', workspace.dataset.saleNote || '', formatted + ' ' + (workspace.dataset.currency || '')].filter(Boolean).join('\n');
            setPending(button, false);
            confirmAction(text, async function () {
                setPending(button, true);
                try {
                    var saleResponse = await fetch('/src/WellSellApi.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
                        body: JSON.stringify({well_id: Number(well.dataset.well), csrf_token: csrf})
                    });
                    var result = await saleResponse.json();
                    if (!saleResponse.ok || result.success === false || result.error) throw new Error(responseMessage(result));
                    announce(responseMessage(result), false);
                    window.location.reload();
                } catch (error) {
                    announce(error.message || workspace.dataset.error || '', true);
                    setPending(button, false);
                }
            }, {type: 'danger'});
        } catch (error) {
            announce(error.message || workspace.dataset.error || '', true);
            setPending(button, false);
        }
    }

    workspace.addEventListener('click', function (event) {
        var selector = event.target.closest('[data-well-select]');
        if (selector) {
            showWell(selector.dataset.wellSelect, false);
            return;
        }
        var tab = event.target.closest('[data-well-tab]');
        if (tab) {
            openTab(tab.closest('[data-well]'), tab.dataset.wellTab, false);
            return;
        }
        var shortcut = event.target.closest('[data-well-open]');
        if (shortcut) {
            openTab(shortcut.closest('[data-well]'), shortcut.dataset.wellOpen, true);
            return;
        }
        var historyButton = event.target.closest('[data-history-direction], [data-history-retry], [data-history-page-target]');
        if (historyButton) {
            var historyWell = historyButton.closest('[data-well]');
            var state = historyState(historyWell);
            var targetPage = historyButton.hasAttribute('data-history-page-target')
                ? Number(historyButton.dataset.historyPageTarget)
                : (historyButton.hasAttribute('data-history-retry') ? state.page : state.page + Number(historyButton.dataset.historyDirection));
            loadHistory(historyWell, targetPage);
            return;
        }
        var pipeline = event.target.closest('[data-well-pipeline]');
        if (pipeline) {
            var pipelineWell = pipeline.closest('[data-well]');
            confirmAction(pipeline.dataset.summary || workspace.dataset.confirm || '', function () {
                postAction(pipeline, '/src/WellStaffApi.php', {action: 'set_transport', well_id: pipelineWell.dataset.well, transport_type: 'rurociag'});
            });
            return;
        }
        var action = event.target.closest('[data-well-action]');
        if (action) {
            var payload;
            try { payload = JSON.parse(action.dataset.payload || '{}'); } catch (error) { announce(workspace.dataset.error || '', true); return; }
            confirmAction(action.dataset.summary || workspace.dataset.confirm || '', function () {
                postAction(action, action.dataset.endpoint || '', payload);
            });
            return;
        }
        var sale = event.target.closest('[data-well-sale]');
        if (sale) sellWell(sale);
    });

    workspace.addEventListener('change', function (event) {
        if (!event.target.matches('[data-well-history-filter]')) return;
        loadHistory(event.target.closest('[data-well]'), 1, event.target.value);
    });

    workspace.addEventListener('keydown', function (event) {
        var tab = event.target.closest('[data-well-tab]');
        if (!tab || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        var tabs = Array.from(tab.parentElement.querySelectorAll('[data-well-tab]'));
        var index = tabs.indexOf(tab);
        if (event.key === 'Home') index = 0;
        else if (event.key === 'End') index = tabs.length - 1;
        else index = (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
        event.preventDefault();
        openTab(tab.closest('[data-well]'), tabs[index].dataset.wellTab, true);
    });

    document.addEventListener('click', function (event) {
        var jump = event.target.closest('[data-well-jump]');
        if (!jump) return;
        var target = workspace.querySelector('[data-well="' + CSS.escape(jump.dataset.wellJump) + '"]');
        if (!target) return;
        event.preventDefault();
        showWell(jump.dataset.wellJump, false);
        target.scrollIntoView({behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start'});
        target.focus({preventScroll: true});
    });

    workspace.querySelectorAll('button').forEach(function (button) {
        button.dataset.originalDisabled = button.disabled ? '1' : '0';
    });

    var initial = null;
    try { initial = sessionStorage.getItem(selectedKey); } catch (error) {}
    var initialButton = initial && workspace.querySelector('[data-well-select="' + CSS.escape(initial) + '"]');
    showWell(initialButton ? initial : workspace.querySelector('[data-well]')?.dataset.well, false);
})();
