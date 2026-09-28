/**
 * Section navigation and progressive detail controls for the logistics page.
 * Nawigacja sekcji i stopniowe kontrolki szczegolow strony logistyki.
 */
(function () {
    'use strict';

    const root = document.querySelector('.logistics-design');
    if (!root) return;

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
        section.scrollIntoView({ behavior: reducedMotion.matches ? 'auto' : 'smooth', block: 'start' });
        history.replaceState(null, '', link.hash);
    }));

    if ('IntersectionObserver' in window) {
        const navObserver = new IntersectionObserver((entries) => {
            const visible = entries.filter((entry) => entry.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
            if (visible.length) activate(visible[0].target.id);
        }, { rootMargin: '-60px 0px -65% 0px' });
        sections.forEach((section) => navObserver.observe(section));
    }

    function makeSelectable(gridSelector, cardSelector, nameSelector, factsSelector, kind) {
        const grid = root.querySelector(gridSelector);
        if (!grid) return;
        const cards = Array.from(grid.querySelectorAll(cardSelector));
        if (!cards.length) return;
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
            button.addEventListener('click', () => select(index));
        });
        function select(index) {
            cards.forEach((card, position) => { card.hidden = position !== index; });
            buttons.forEach((button, position) => {
                if (position === index) button.setAttribute('aria-current', 'true');
                else button.removeAttribute('aria-current');
            });
        }
        grid.before(browser);
        grid.classList.add('logistics-entity-detail');
        browser.append(list, grid);
        const priority = cards.findIndex(card => card.matches('.hub-status-critical, .is-critical'));
        select(priority >= 0 ? priority : 0);
    }

    makeSelectable('#logistics-owned-section .logistics-hub-grid', '.logistics-hub-card', '.logistics-hub-name', '.logistics-hub-row-facts', 'hub');
    makeSelectable('#logistics-pipelines-section .logistics-pipeline-grid', '.logistics-pipeline-card', '.logistics-pipeline-card-head h4', '.logistics-pipeline-row-facts', 'pipeline');

    if (!reducedMotion.matches && 'IntersectionObserver' in window) {
        const reveal = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-entered');
                observer.unobserve(entry.target);
            });
        }, { threshold: 0 });
        sections.forEach((section) => {
            reveal.observe(section);
        });

        const bars = root.querySelectorAll('[data-progress-width]');
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
})();
