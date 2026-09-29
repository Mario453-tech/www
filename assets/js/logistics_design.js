/**
 * Section navigation and progressive detail controls for the logistics page.
 * Nawigacja sekcji i stopniowe kontrolki szczegolow strony logistyki.
 */
(function () {
    'use strict';

    const root = document.querySelector('.logistics-design');
    if (!root) return;

    document.documentElement.style.scrollBehavior = 'auto';
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const links = Array.from(root.querySelectorAll('.logistics-section-nav a[href^="#"]'));
    const sections = links.map((link) => document.getElementById(link.hash.slice(1))).filter(Boolean);

    function activate(id) {
        links.forEach((link) => {
            if (link.hash === '#' + id) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        });
    }

    links.forEach((link) => link.addEventListener('click', (event) => {
        const section = document.getElementById(link.hash.slice(1));
        if (!section) return;
        event.preventDefault();
        activate(section.id);
        section.scrollIntoView({ behavior: 'auto', block: 'start' });
        history.replaceState(null, '', link.hash);
    }));

    let navUpdateQueued = false;
    window.addEventListener('scroll', () => {
        if (navUpdateQueued) return;
        navUpdateQueued = true;
        requestAnimationFrame(() => {
            navUpdateQueued = false;
            const atBottom = window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 2;
            const current = atBottom ? sections[sections.length - 1]
                : ([...sections].reverse().find(section => section.getBoundingClientRect().top <= 140) || sections[0]);
            if (current) activate(current.id);
        });
    }, { passive: true });

    function makeSelectable(gridSelector, cardSelector, nameSelector, factsSelector, kind) {
        const grid = root.querySelector(gridSelector);
        if (!grid) return null;
        const cards = Array.from(grid.querySelectorAll(cardSelector));
        if (!cards.length) return null;
        const browser = document.createElement('div');
        browser.className = 'logistics-entity-browser';
        const list = document.createElement('div');
        list.className = 'logistics-entity-list';
        list.setAttribute('aria-label', root.dataset.detailsLabel || 'Details');
        const buttons = [];
        cards.forEach((card, index) => {
            card.id = `logistics-${kind}-detail-${index}`;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'logistics-entity-row';
            button.setAttribute('aria-controls', card.id);
            const name = document.createElement('strong');
            name.textContent = card.querySelector(nameSelector)?.textContent?.trim() || `#${index + 1}`;
            button.appendChild(name);
            const facts = card.querySelector(factsSelector);
            if (facts) button.appendChild(facts.cloneNode(true));
            const badge = card.querySelector('.hub-risk-badge, .logistics-pipeline-badge');
            if (badge) button.appendChild(badge.cloneNode(true));
            list.appendChild(button);
            buttons.push(button);
            button.addEventListener('click', () => select(index, true));
        });
        function select(index, animate = false) {
            cards.forEach((card, position) => { card.hidden = position !== index; });
            buttons.forEach((button, position) => {
                if (position === index) button.setAttribute('aria-current', 'true');
                else button.removeAttribute('aria-current');
            });
            if (animate && !reducedMotion.matches) {
                cards[index].classList.remove('is-changing');
                void cards[index].offsetWidth;
                cards[index].classList.add('is-changing');
            }
        }
        grid.before(browser);
        grid.classList.add('logistics-entity-detail');
        browser.append(list, grid);
        const priority = cards.findIndex(card => card.matches('.hub-status-critical, .is-critical'));
        select(priority >= 0 ? priority : 0);
        return (id) => {
            const position = cards.findIndex(card => Number(card.dataset[`${kind}Id`]) === id);
            if (position < 0) return;
            select(position, true);
            cards[position].setAttribute('tabindex', '-1');
            cards[position].focus({ preventScroll: true });
            cards[position].scrollIntoView({ behavior: 'auto', block: 'start' });
        };
    }

    const selectHub = makeSelectable('#logistics-owned-section .logistics-hub-grid', '.logistics-hub-card', '.logistics-hub-name', '.logistics-hub-row-facts', 'hub');
    const selectPipeline = makeSelectable('#logistics-pipelines-section .logistics-pipeline-grid', '.logistics-pipeline-card', '.logistics-pipeline-card-head h4', '.logistics-pipeline-row-facts', 'pipeline');
    if (location.hash) {
        history.scrollRestoration = 'manual';
        const scrollToHash = () => {
            const target = document.getElementById(decodeURIComponent(location.hash.slice(1)));
            if (!target) return;
            target.scrollIntoView({ behavior: 'auto', block: 'start' });
        };
        setTimeout(scrollToHash, 0);
        window.addEventListener('pageshow', () => setTimeout(scrollToHash, 50), { once: true });
    }
    root.querySelectorAll('[data-logistics-object][data-object-id]').forEach(link => link.addEventListener('click', (event) => {
        const selectObject = link.dataset.logisticsObject === 'hub' ? selectHub : selectPipeline;
        if (!selectObject) return;
        event.preventDefault();
        selectObject(Number(link.dataset.objectId));
        const sectionId = link.dataset.logisticsObject === 'hub' ? 'logistics-owned-section' : 'logistics-pipelines-section';
        activate(sectionId);
        history.replaceState(null, '', '#' + sectionId);
    }));

    const bars = root.querySelectorAll('[data-progress-width]');
    if (reducedMotion.matches || !('IntersectionObserver' in window)) {
        bars.forEach(bar => { bar.style.width = Math.max(0, Math.min(100, Number(bar.dataset.progressWidth || 0))) + '%'; });
    } else {
        const barObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                const bar = entry.target;
                const target = Math.max(0, Math.min(100, Number(bar.dataset.progressWidth || 0)));
                bar.style.width = '0%';
                requestAnimationFrame(() => requestAnimationFrame(() => { bar.style.width = target + '%'; }));
                observer.unobserve(bar);
            });
        }, { threshold: 0.2 });
        bars.forEach((bar) => barObserver.observe(bar));
    }
    if (!reducedMotion.matches) {
        try {
            const key = 'logistics-latest-event-' + root.dataset.playerId;
            const previous = sessionStorage.getItem(key);
            const rows = Array.from(root.querySelectorAll('.logistics-incidents-row[data-event-time]'));
            const newest = rows.reduce((last, row) => row.dataset.eventTime > last ? row.dataset.eventTime : last, previous || '');
            if (previous) rows.forEach(row => {
                if (row.dataset.eventTime > previous) row.classList.add('is-new');
            });
            if (newest) sessionStorage.setItem(key, newest);
        } catch (_) { /* Storage can be unavailable in private browsing. */ }
    }
})();
