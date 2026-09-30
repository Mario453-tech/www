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
        var history = JSON.parse(L.historyData || '{}');
        var historyStatuses = JSON.parse(L.historyStatuses || '{}');
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
        function pending(card) { return ['pending', 'delayed'].indexOf(status(card)) !== -1 || !!card && card.dataset.legalUpgradePending === '1'; }
        function overdue(card) { return !!card && pending(card) && Number(card.dataset.legalDueEpoch) > 0 && Number(card.dataset.legalDueEpoch) * 1000 < Date.now(); }
        function requiresAttention(region) {
            if (attentionStatus[status(region.drilling)] || attentionStatus[status(region.local)]) return true;
            if (overdue(region.local) || overdue(region.drilling)) return true;
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
        function stat(label, value, tone, hint) {
            var item = node('div', 'legal-design-stat legal-design-stat--' + tone);
            item.appendChild(node('span', 'legal-design-stat-label', label));
            item.appendChild(node('strong', '', value));
            if (hint) item.appendChild(node('small', '', hint));
            stats.appendChild(item);
        }
        var activeDrilling = regions.filter(function (r) { return status(r.drilling) === 'granted'; }).length;
        var activeLocal = regions.filter(function (r) { return status(r.local) === 'granted'; }).length;
        var attentionCount = regions.filter(requiresAttention).length;
        stat(L.reputation, credibility ? credibility.textContent.trim() : '—', 'good');
        stat(L.drilling, activeDrilling + ' / ' + regions.filter(function (r) { return r.drilling; }).length, 'gold');
        stat(L.local, activeLocal + ' / ' + regions.filter(function (r) { return r.local; }).length, 'blue');
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
        // Release the native top layer for the game's confirmation modal.
        // Zwolnij natywna warstwe okna dla potwierdzenia akcji w grze.
        dialog.addEventListener('submit', function (event) {
            if (event.defaultPrevented && event.target.matches('.legal-submit-form, .legal-bribe-form')) closeDialog();
        });

        function badge(card) {
            return node('span', 'legal-design-badge legal-design-status--' + status(card), statusText(card));
        }
        function applicationBadge(card) {
            var upgrade = card.querySelector('.legal-upgrade-pending .legal-badge');
            return upgrade ? node('span', 'legal-design-badge legal-design-status--pending', upgrade.textContent.trim()) : badge(card);
        }
        function dialogHeader(title, subtitle, risk) {
            dialogInner.replaceChildren();
            var bar = node('header', 'legal-design-dialog-head');
            var titles = node('div', 'legal-design-dialog-titles');
            var heading = node('h2', '', title);
            heading.id = 'legal-design-dialog-title';
            dialog.setAttribute('aria-labelledby', heading.id);
            titles.appendChild(heading);
            if (subtitle) titles.appendChild(node('p', '', subtitle));
            bar.appendChild(titles);
            if (risk) bar.appendChild(node('span', 'legal-design-risk-pill', risk));
            var close = node('button', 'legal-design-close', '×');
            close.type = 'button';
            close.setAttribute('aria-label', L.close);
            close.addEventListener('click', closeDialog);
            bar.appendChild(close);
            dialogInner.appendChild(bar);
        }
        function footer(text, label, handler) {
            var foot = node('footer', 'legal-design-dialog-footer');
            foot.appendChild(node('p', '', text));
            var button = node('button', 'legal-design-footer-action', label);
            button.type = 'button';
            button.addEventListener('click', handler);
            foot.appendChild(button);
            dialogInner.appendChild(foot);
        }
        function revealDialog() {
            if (!dialog.open) dialog.showModal();
            dialogInner.scrollTop = 0;
            dialog.querySelector('.legal-design-close').focus();
        }
        function showHistory(region, card, kind) {
            dialogHeader(L.history, region.name + ' · ' + (kind === 'drilling' ? L.permitDrilling : L.permitLocal));
            var summary = node('section', 'legal-design-history-summary');
            var current = node('div');
            current.appendChild(node('span', 'legal-design-eyebrow', L.status));
            current.appendChild(applicationBadge(card));
            summary.appendChild(current);
            var due = node('div');
            due.appendChild(node('span', 'legal-design-eyebrow', L.due));
            due.appendChild(node('strong', '', card.dataset.legalDue ? dateLabel(card.dataset.legalDue) : L.missingDate));
            summary.appendChild(due);
            if (overdue(card)) summary.appendChild(node('span', 'legal-design-overdue', L.overdue));
            summary.appendChild(node('p', 'legal-design-status-note', L.statusNote));
            dialogInner.appendChild(summary);
            var body = node('section', 'legal-design-history-body');
            body.appendChild(node('h3', '', L.historySubtitle));
            body.appendChild(node('p', 'legal-design-muted', L.timelineIntro));
            var timeline = node('ol', 'legal-design-timeline');
            var hasDecision = ['granted', 'refused', 'transitional'].indexOf(status(card)) !== -1 && card.dataset.legalUpgradePending !== '1';
            [
                [L.submitted, card.dataset.legalSubmitted, 'submitted'],
                [L.due, card.dataset.legalDue, overdue(card) ? 'overdue' : 'due'],
                [hasDecision ? L.decided : L.decision, hasDecision ? card.dataset.legalDecided : '', 'decision']
            ].forEach(function (item) {
                var row = node('li', 'legal-design-timeline-row legal-design-timeline-row--' + item[2] + (item[1] ? ' has-date' : ''));
                row.appendChild(node('strong', '', item[0]));
                var value = item[1] ? dateLabel(item[1]) : item[2] === 'decision' ? L.noDecision : L.missingDate;
                if (item[2] === 'overdue') value += ' · ' + L.overdue.toLowerCase();
                row.appendChild(node('span', '', value));
                timeline.appendChild(row);
            });
            body.appendChild(timeline);
            dialogInner.appendChild(body);
            var previous = (history[region.id] || {})[kind] || [];
            if (previous.length) {
                var earlier = node('section', 'legal-design-earlier');
                earlier.appendChild(node('h3', '', L.prior));
                previous.forEach(function (attempt) {
                    var item = node('article', 'legal-design-history-attempt');
                    item.appendChild(node('strong', '', attempt.source === 'fee' ? L.feeRecord : (historyStatuses[attempt.status] || L.history)));
                    if (attempt.submitted_at) item.appendChild(node('p', '', (attempt.source === 'fee' ? L.feeDate : L.submitted) + ': ' + dateLabel(attempt.submitted_at)));
                    if (attempt.source === 'archive' && attempt.decision_due_at) item.appendChild(node('p', '', L.due + ': ' + dateLabel(attempt.decision_due_at)));
                    if (attempt.source === 'archive' && ['granted', 'refused', 'transitional'].indexOf(attempt.status) !== -1 && attempt.decided_at) item.appendChild(node('p', '', L.decided + ': ' + dateLabel(attempt.decided_at)));
                    item.appendChild(node('p', '', L.applicationFee + ': ' + Number(attempt.cost).toLocaleString(L.locale, {maximumFractionDigits: 2}) + ' ' + L.currency));
                    earlier.appendChild(item);
                });
                dialogInner.appendChild(earlier);
            }
            footer('', L.back, function () { showRegion(region); });
            revealDialog();
        }
        function permitPanel(region, card, kind) {
            var panel = node('section', 'legal-design-permit');
            panel.appendChild(node('h3', '', kind === 'drilling' ? L.permitDrilling : L.permitLocal));
            if (!card) {
                panel.appendChild(node('p', 'legal-design-empty', L.notApplicable));
                return panel;
            }
            panel.appendChild(badge(card));
            if (card.dataset.legalUpgradePending === '1') panel.appendChild(applicationBadge(card));
            var state = status(card);
            var description = kind === 'local' ? L.localDescription : state === 'transitional' ? L.drillingDescription
                : pending(card) ? L.reviewDescription : (card.querySelector('.legal-region-unlocks') || {}).textContent;
            if (description) panel.appendChild(node('p', 'legal-design-permit-description', description.trim()));
            // Preserve real blocking reasons and existing authorised actions.
            // Zachowaj prawdziwe przyczyny blokad i istniejace autoryzowane akcje.
            card.querySelectorAll('.legal-region-note:not(.legal-region-note--transitional), .legal-cooldown').forEach(function (note) {
                if (state !== 'transitional') panel.appendChild(node('p', 'legal-design-permit-reason', note.textContent.trim()));
            });
            var information = node('div', 'legal-design-permit-information');
            if (pending(card)) {
                information.appendChild(node('span', 'legal-design-eyebrow', L.dueShort));
                information.appendChild(node('strong', '', card.dataset.legalDue ? dateLabel(card.dataset.legalDue) : L.missingDate));
            } else if (state !== 'granted') {
                information.appendChild(node('span', 'legal-design-eyebrow', state === 'transitional' ? L.fullFee : L.applicationFee));
                information.appendChild(node('strong', '', card.dataset.legalApplicationCost || '—'));
            } else if (card.dataset.legalDecided) {
                information.appendChild(node('span', 'legal-design-eyebrow', L.decided));
                information.appendChild(node('strong', '', dateLabel(card.dataset.legalDecided)));
            }
            if (information.childElementCount) panel.appendChild(information);
            var needsFunds = !pending(card) && state !== 'granted' && card.dataset.legalAffordable === '0'
                && (state === 'transitional' || card.classList.contains('legal-region-card--available'));
            if (needsFunds || overdue(card)) {
                var alert = node('div', 'legal-design-permit-alert' + (needsFunds ? ' is-danger' : ''));
                alert.appendChild(node('strong', '', needsFunds ? L.insufficient : L.overdue));
                alert.appendChild(node('p', '', needsFunds ? L.insufficientHint : L.overdueHint));
                panel.appendChild(alert);
            }
            var actions = node('div', 'legal-design-permit-actions');
            card.querySelectorAll('form.legal-submit-form').forEach(function (form) { actions.appendChild(form.cloneNode(true)); });
            if (state === 'no_decision') actions.querySelectorAll('form.legal-submit-form button').forEach(function (button) { button.textContent = L.retry; });
            if (needsFunds) {
                var disabled = node('button', 'legal-design-disabled-action', state === 'transitional' ? L.applyFull : state === 'no_decision' ? L.retry : L.apply);
                disabled.type = 'button'; disabled.disabled = true;
                actions.appendChild(disabled);
                var finance = node('a', 'legal-design-finance', L.finance + ' →');
                finance.href = L.financeUrl;
                actions.appendChild(finance);
            }
            if (card.dataset.legalSubmitted || card.dataset.legalDecided || pending(card) || ((history[region.id] || {})[kind] || []).length) {
                var history = node('button', 'legal-design-history-button', L.history);
                history.type = 'button';
                history.addEventListener('click', function () { showHistory(region, card, kind); });
                actions.appendChild(history);
            }
            panel.appendChild(actions);
            var bribe = card.querySelector('.legal-bribe');
            if (bribe) panel.appendChild(bribe.cloneNode(true));
            return panel;
        }
        function showRegion(region) {
            dialogHeader(region.name, L.regionSubtitle, region.risk);
            var columns = node('div', 'legal-design-permits');
            columns.appendChild(permitPanel(region, region.drilling, 'drilling'));
            columns.appendChild(permitPanel(region, region.local, 'local'));
            dialogInner.appendChild(columns);
            footer(L.separateNote, L.close, closeDialog);
            revealDialog();
        }

        var priority = regions.filter(requiresAttention).slice(0, 3);
        if (priority.length) {
            var priorityPanel = node('section', 'legal-design-priority');
            priorityPanel.appendChild(node('h2', '', L.needsAttention));
            priorityPanel.appendChild(node('p', 'legal-design-priority-hint', L.priorityHint));
            var priorityGrid = node('div', 'legal-design-priority-grid');
            priority.forEach(function (region) {
                var item = node('button', 'legal-design-priority-item');
                item.type = 'button';
                var itemHead = node('span', 'legal-design-priority-head');
                itemHead.appendChild(node('strong', '', region.name));
                itemHead.appendChild(node('span', 'legal-design-detail-link', L.details + ' ›'));
                item.appendChild(itemHead);
                var drillingIssue = attentionStatus[status(region.drilling)] || overdue(region.drilling);
                var issue = drillingIssue
                    ? L.drilling + ': ' + statusText(region.drilling)
                    : L.local + ': ' + statusText(region.local);
                item.appendChild(node('span', 'legal-design-priority-issue', issue));
                var mainCard = drillingIssue ? region.drilling : region.local;
                if (mainCard && status(mainCard) === 'transitional' && !pending(mainCard)) {
                    item.appendChild(node('small', '', L.fullFee + ': ' + mainCard.dataset.legalApplicationCost + (mainCard.dataset.legalAffordable === '0' ? ' · ' + L.insufficient.toLowerCase() : '')));
                } else if (overdue(mainCard)) {
                    item.appendChild(node('small', '', L.overdue + ' · ' + L.overdueHint));
                }
                if (mainCard && status(mainCard) === 'no_decision') item.classList.add('is-no-decision');
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
