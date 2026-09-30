/*
 * Legal region overview and current application detail.
 * Widok regionow prawnych i szczegoly biezacego wniosku.
 */
(function () {
    'use strict';

    function node(tag, className, value) {
        var element = document.createElement(tag);
        if (className) element.className = className;
        if (value !== undefined && value !== null) element.textContent = String(value);
        return element;
    }

    function dateLabel(value) {
        if (!value) return '—';
        var parts = String(value).trim().split(' ');
        var date = parts[0].split('-');
        if (date.length !== 3) return String(value);
        return date[2] + '.' + date[1] + '.' + date[0] + (parts[1] ? ', ' + parts[1].slice(0, 5) : '');
    }

    function init() {
        var root = document.getElementById('legal-design-root');
        if (!root) return;
        var shell = root.parentElement;
        var cards = Array.prototype.slice.call(shell.querySelectorAll('.legal-region-card[data-legal-region]'));
        if (!cards.length) return;

        var L = root.dataset;
        var regionMap = new Map();
        cards.forEach(function (card) {
            var id = card.dataset.legalRegion;
            var record = regionMap.get(id);
            if (!record) {
                record = {
                    id: id,
                    name: card.dataset.legalName || ('Region #' + id),
                    risk: '',
                    drilling: null,
                    local: null
                };
                regionMap.set(id, record);
            }
            record[card.dataset.legalKind] = card;
            var risk = card.querySelector('[class*="legal-risk-"]');
            if (risk && !record.risk) record.risk = risk.textContent.trim();
        });
        var regions = Array.from(regionMap.values());
        var attentionStatus = {transitional: true, delayed: true, no_decision: true, refused: true};
        function status(card) { return card ? (card.dataset.legalStatus || 'none') : 'none'; }
        function statusText(card) {
            if (!card) return L.notApplicable || '—';
            var badge = card.querySelector('.legal-region-name .legal-badge');
            return badge ? badge.textContent.trim() : (card.dataset.legalStatus || '—');
        }
        function requiresAttention(region) {
            if (attentionStatus[status(region.drilling)] || attentionStatus[status(region.local)]) return true;
            if (status(region.local) === 'pending') {
                var due = region.local && region.local.dataset.legalDue;
                return due && new Date(due.replace(' ', 'T')).getTime() < Date.now();
            }
            return false;
        }
        regions.sort(function (a, b) {
            var priority = Number(requiresAttention(b)) - Number(requiresAttention(a));
            return priority || a.name.localeCompare(b.name);
        });

        var credibility = shell.querySelector('.credibility-status-badge');
        var sabotage = Array.prototype.slice.call(shell.querySelectorAll('a')).find(function (link) {
            return link.getAttribute('href') && link.getAttribute('href').indexOf('sabotage') !== -1;
        });

        var header = node('header', 'legal-design-header');
        var headingWrap = node('div');
        headingWrap.appendChild(node('h1', '', L.title));
        headingWrap.appendChild(node('p', '', L.subtitle));
        header.appendChild(headingWrap);
        if (sabotage) {
            var shortcut = node('a', 'legal-design-shortcut', sabotage.textContent.trim() + ' →');
            shortcut.href = sabotage.href;
            header.appendChild(shortcut);
        }
        root.appendChild(header);

        var stats = node('div', 'legal-design-stats');
        function stat(label, value, tone) {
            var item = node('div', 'legal-design-stat legal-design-stat--' + tone);
            item.appendChild(node('span', 'legal-design-stat-label', label));
            item.appendChild(node('strong', '', value));
            stats.appendChild(item);
        }
        var activeDrilling = regions.filter(function (r) { return status(r.drilling) === 'granted'; }).length;
        var activeLocal = regions.filter(function (r) { return status(r.local) === 'granted'; }).length;
        var attentionCount = regions.filter(requiresAttention).length;
        stat(L.reputation, credibility ? credibility.textContent.trim() : '—', 'good');
        stat(L.drilling, activeDrilling + ' / ' + regions.length, 'gold');
        stat(L.local, activeLocal + ' / ' + regions.filter(function (r) { return !!r.local; }).length, 'blue');
        stat(L.attention, attentionCount, attentionCount ? 'warning' : 'good');
        root.appendChild(stats);

        var dialog = node('dialog', 'legal-design-dialog');
        var dialogInner = node('div', 'legal-design-dialog-inner');
        dialog.appendChild(dialogInner);
        root.appendChild(dialog);
        function closeDialog() { dialog.close(); }
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) closeDialog();
        });

        function dialogHeader(title) {
            dialogInner.replaceChildren();
            var bar = node('div', 'legal-design-dialog-head');
            var heading = node('h2', '', title);
            heading.id = 'legal-design-dialog-title';
            dialog.setAttribute('aria-labelledby', heading.id);
            bar.appendChild(heading);
            var close = node('button', 'legal-design-close', '×');
            close.type = 'button';
            close.setAttribute('aria-label', L.close || 'Close');
            close.addEventListener('click', closeDialog);
            bar.appendChild(close);
            dialogInner.appendChild(bar);
        }

        function showHistory(region, card, kind) {
            dialogHeader(L.history + ' · ' + region.name);
            var back = node('button', 'legal-design-back', '← ' + L.back);
            back.type = 'button';
            back.addEventListener('click', function () { showRegion(region); });
            dialogInner.appendChild(back);
            var intro = node('div', 'legal-design-history-intro');
            intro.appendChild(node('span', 'legal-design-eyebrow', kind === 'drilling' ? L.drilling : L.local));
            intro.appendChild(node('strong', 'legal-design-status legal-design-status--' + status(card), statusText(card)));
            dialogInner.appendChild(intro);
            var timeline = node('div', 'legal-design-timeline');
            [
                [L.submitted, card.dataset.legalSubmitted],
                [L.due, card.dataset.legalDue],
                [L.decided, card.dataset.legalDecided || L.noDecision]
            ].forEach(function (item) {
                var row = node('div', 'legal-design-timeline-row');
                row.appendChild(node('span', '', item[0]));
                row.appendChild(node('strong', '', item[1] === L.noDecision ? item[1] : dateLabel(item[1])));
                timeline.appendChild(row);
            });
            dialogInner.appendChild(node('h3', 'legal-design-subhead', L.current));
            dialogInner.appendChild(timeline);
            dialogInner.appendChild(node('h3', 'legal-design-subhead', L.prior));
            dialogInner.appendChild(node('p', 'legal-design-empty', L.noPrior));
            if (!dialog.open) dialog.showModal();
        }

        function permitPanel(region, card, kind) {
            var panel = node('section', 'legal-design-permit');
            panel.appendChild(node('h3', '', kind === 'drilling' ? L.drilling : L.local));
            if (!card) {
                panel.appendChild(node('p', 'legal-design-empty', L.notApplicable));
                return panel;
            }
            var copy = card.cloneNode(true);
            copy.removeAttribute('data-legal-region');
            panel.appendChild(copy);
            if (card.dataset.legalSubmitted || card.dataset.legalDecided) {
                var history = node('button', 'legal-design-history-button', L.history + ' →');
                history.type = 'button';
                history.addEventListener('click', function () { showHistory(region, card, kind); });
                panel.appendChild(history);
            }
            return panel;
        }

        function showRegion(region) {
            dialogHeader(region.name);
            var risk = node('p', 'legal-design-risk', L.risk + ': ' + (region.risk || '—'));
            dialogInner.appendChild(risk);
            var columns = node('div', 'legal-design-permits');
            columns.appendChild(permitPanel(region, region.drilling, 'drilling'));
            columns.appendChild(permitPanel(region, region.local, 'local'));
            dialogInner.appendChild(columns);
            if (!dialog.open) dialog.showModal();
        }

        var priority = regions.filter(requiresAttention).slice(0, 3);
        if (priority.length) {
            var priorityPanel = node('section', 'legal-design-priority');
            priorityPanel.appendChild(node('h2', '', L.needsAttention));
            var priorityGrid = node('div', 'legal-design-priority-grid');
            priority.forEach(function (region) {
                var item = node('button', 'legal-design-priority-item');
                item.type = 'button';
                item.appendChild(node('strong', '', region.name));
                var issue = attentionStatus[status(region.drilling)]
                    ? L.drilling + ': ' + statusText(region.drilling)
                    : L.local + ': ' + statusText(region.local);
                item.appendChild(node('span', '', issue));
                item.addEventListener('click', function () { showRegion(region); });
                priorityGrid.appendChild(item);
            });
            priorityPanel.appendChild(priorityGrid);
            root.appendChild(priorityPanel);
        }

        var list = node('section', 'legal-design-list');
        var listHead = node('div', 'legal-design-list-head');
        listHead.appendChild(node('h2', '', L.regions));
        var filters = node('div', 'legal-design-filters');
        var rows = node('div', 'legal-design-rows');
        function renderRows(filter) {
            rows.replaceChildren();
            regions.filter(function (region) {
                return filter === 'attention' ? requiresAttention(region)
                    : filter === 'active' ? status(region.drilling) === 'granted' && (!region.local || status(region.local) === 'granted')
                    : true;
            }).forEach(function (region) {
                var row = node('button', 'legal-design-row' + (requiresAttention(region) ? ' is-attention' : ''));
                row.type = 'button';
                row.appendChild(node('strong', 'legal-design-row-name', region.name));
                var riskCell = node('span', 'legal-design-row-risk', region.risk || '—');
                riskCell.dataset.label = L.risk;
                row.appendChild(riskCell);
                var drillingCell = node('span', 'legal-design-row-status legal-design-status--' + status(region.drilling), statusText(region.drilling));
                drillingCell.dataset.label = L.drilling;
                row.appendChild(drillingCell);
                var localCell = node('span', 'legal-design-row-status legal-design-status--' + status(region.local), statusText(region.local));
                localCell.dataset.label = L.local;
                row.appendChild(localCell);
                row.appendChild(node('span', 'legal-design-row-action', L.open + ' →'));
                row.addEventListener('click', function () { showRegion(region); });
                rows.appendChild(row);
            });
        }
        [
            ['all', L.all],
            ['attention', L.attention],
            ['active', L.active]
        ].forEach(function (pair, index) {
            var button = node('button', 'legal-design-filter' + (index === 0 ? ' is-active' : ''), pair[1]);
            button.type = 'button';
            button.addEventListener('click', function () {
                Array.prototype.forEach.call(filters.children, function (child) { child.classList.remove('is-active'); });
                button.classList.add('is-active');
                renderRows(pair[0]);
            });
            filters.appendChild(button);
        });
        listHead.appendChild(filters);
        list.appendChild(listHead);
        var colHead = node('div', 'legal-design-columns');
        [L.regions, L.risk, L.drilling, L.local, L.open].forEach(function (label) {
            colHead.appendChild(node('span', '', label));
        });
        list.appendChild(colHead);
        renderRows('all');
        list.appendChild(rows);
        root.appendChild(list);

        shell.classList.add('legal-redesign');
        root.hidden = false;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
