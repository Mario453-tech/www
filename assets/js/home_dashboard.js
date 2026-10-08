/**
 * Dashboard tabs and statistics refresh.
 * Zakladki pulpitu i odswiezanie statystyk.
 */
(function () {
    'use strict';

    document.querySelectorAll('[data-tab-set]').forEach(function (tablist) {
        var tabs = Array.from(tablist.querySelectorAll('[role="tab"]'));
        function activate(tab, focus) {
            tabs.forEach(function (item) {
                var selected = item === tab;
                item.setAttribute('aria-selected', selected ? 'true' : 'false');
                item.tabIndex = selected ? 0 : -1;
                var panel = document.getElementById(item.getAttribute('aria-controls'));
                if (panel) panel.hidden = !selected;
            });
            if (focus) tab.focus();
        }
        tabs.forEach(function (tab, index) {
            tab.addEventListener('click', function () { activate(tab, false); });
            tab.addEventListener('keydown', function (event) {
                var next = -1;
                if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
                if (event.key === 'ArrowLeft') next = (index + tabs.length - 1) % tabs.length;
                if (event.key === 'Home') next = 0;
                if (event.key === 'End') next = tabs.length - 1;
                if (next < 0) return;
                event.preventDefault();
                activate(tabs[next], true);
            });
        });
    });

    var root = document.getElementById('company-stats');
    if (!root) return;
    var periods = Array.from(root.querySelectorAll('[data-stat-period]'));
    var formatter = new Intl.NumberFormat(root.dataset.locale || 'pl-PL', { maximumFractionDigits: 1 });
    var pending = null;

    function format(value) {
        return value === null || value === undefined || !Number.isFinite(Number(value))
            ? root.dataset.na : formatter.format(Number(value));
    }
    function set(name, value) {
        var node = root.querySelector('[data-stat="' + name + '"]');
        if (node) {
            node.textContent = format(value);
            var unit = node.nextElementSibling;
            if (unit && unit.tagName === 'SMALL') unit.hidden = value === null || value === undefined;
        }
    }
    function setChange(name, value) {
        var node = root.querySelector('[data-stat="' + name + '"]');
        if (node) node.textContent = value === null || value === undefined
            ? root.dataset.noChange : format(value) + '%';
    }
    function renderWells(selector, wells) {
        var list = root.querySelector(selector);
        if (!list) return;
        list.replaceChildren();
        wells.forEach(function (well) {
            var item = document.createElement('li');
            var link = document.createElement('a');
            link.href = '/#wg-card-' + encodeURIComponent(String(well.id));
            link.textContent = well.name;
            var production = document.createElement('span');
            production.textContent = format(well.production) + ' bbl/h';
            var condition = document.createElement('span');
            condition.textContent = format(well.condition) + '%';
            item.append(link, production, condition);
            list.append(item);
        });
    }
    function render(model, button) {
        var overview = model.overview || {};
        var wells = model.wells || {};
        var logistics = model.logistics || {};
        var finance = model.finance || {};
        var costs = finance.costs || {};
        set('production_rate', overview.production_rate);
        set('revenue_rate', overview.revenue_rate);
        setChange('production_change', overview.production_change);
        setChange('revenue_change', overview.revenue_change);
        set('active_wells', wells.active);
        set('total_wells', wells.total);
        ['total', 'active', 'attention', 'critical'].forEach(function (key) { set('wells_' + key, wells[key]); });
        ['road', 'pipeline', 'sea'].forEach(function (key) { set('mix_' + key, (logistics.mix || {})[key]); });
        set('active_routes', logistics.active_routes);
        set('on_time_pct', logistics.on_time_pct);
        set('loss_bbl', logistics.loss_bbl);
        set('transport_cost_rate', logistics.cost_rate);
        set('finance_revenue_rate', finance.revenue_rate);
        set('finance_cost_rate', finance.cost_rate);
        set('finance_net_rate', finance.net_rate);
        var totalCost = Object.values(costs).reduce(function (sum, value) { return sum + (Number(value) || 0); }, 0);
        ['extraction', 'logistics', 'staff', 'other'].forEach(function (key) {
            set('cost_' + key, model.state === 'ready' ? costs[key] : null);
            var bar = root.querySelector('[data-cost-progress="' + key + '"]');
            if (bar) {
                bar.max = Math.max(1, totalCost);
                bar.value = Math.max(0, Number(costs[key]) || 0);
            }
        });
        var top = Array.isArray(wells.top) ? wells.top : [];
        renderWells('[data-top-wells]', top);
        renderWells('[data-well-ranking]', top);
        var topEmpty = root.querySelector('[data-top-empty]');
        if (topEmpty) topEmpty.hidden = top.length > 0;
        var series = Array.isArray(model.series) ? model.series : [];
        var chart = root.querySelector('[data-chart]');
        var chartState = root.querySelector('[data-chart-state]');
        var line = root.querySelector('[data-chart-line]');
        var summary = root.querySelector('[data-chart-summary]');
        if (chart) chart.hidden = series.length === 0 || model.state !== 'ready';
        if (chartState) {
            chartState.hidden = series.length > 0 && model.state === 'ready';
            chartState.textContent = model.state === 'error' ? root.dataset.error : root.dataset.empty;
        }
        if (line) line.setAttribute('points', model.chart_points || '');
        if (summary) summary.textContent = model.state === 'ready'
            ? root.dataset.chartTemplate.replace('{value}', format(overview.production_rate)).replace('{period}', button.textContent.trim()) : '';
        periods.forEach(function (period) { period.setAttribute('aria-pressed', period === button ? 'true' : 'false'); });
    }
    periods.forEach(function (button) {
        button.addEventListener('click', function () {
            if (button.getAttribute('aria-pressed') === 'true') return;
            if (pending) pending.abort();
            pending = new AbortController();
            periods.forEach(function (period) { period.disabled = true; });
            fetch(root.dataset.api + '?period=' + encodeURIComponent(button.dataset.statPeriod), {
                credentials: 'same-origin', signal: pending.signal
            }).then(function (response) {
                if (!response.ok) throw new Error('Statistics request failed');
                return response.json();
            }).then(function (model) {
                if (!model || !['ready', 'empty', 'error'].includes(model.state)) throw new Error('Invalid statistics response');
                render(model, button);
            }).catch(function (error) {
                if (error.name === 'AbortError') return;
                var chartState = root.querySelector('[data-chart-state]');
                if (chartState) { chartState.hidden = false; chartState.textContent = root.dataset.error; }
            }).finally(function () {
                periods.forEach(function (period) { period.disabled = false; });
                pending = null;
            });
        });
    });
})();
