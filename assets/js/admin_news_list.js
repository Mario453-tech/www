/* Admin news filtering and pagination / Filtrowanie i stronicowanie aktualnosci admina */
(() => {
    'use strict';

    const root = document.getElementById('news-page');
    const list = document.getElementById('news-list');
    if (!root || !list) return;

    const cards = Array.from(list.querySelectorAll('[data-news-item]'));
    const search = document.getElementById('news-search-input');
    const status = document.getElementById('news-status-filter');
    const sort = document.getElementById('news-sort');
    const tabs = Array.from(root.querySelectorAll('[data-news-tab]'));
    const empty = document.getElementById('news-no-matches');
    const footer = document.getElementById('news-pagination-footer');
    const range = document.getElementById('news-range');
    const pages = document.getElementById('news-pages');
    const pageSize = 5;
    let currentPage = 1;
    let currentFilter = 'all';

    const normalize = value => String(value || '')
        .toLocaleLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/ł/g, 'l');

    const arrow = forward => {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('aria-hidden', 'true');
        path.setAttribute('d', forward ? 'm9 5 7 7-7 7' : 'm15 5-7 7 7 7');
        path.setAttribute('fill', 'none');
        path.setAttribute('stroke', 'currentColor');
        path.setAttribute('stroke-width', '2');
        path.setAttribute('stroke-linecap', 'round');
        path.setAttribute('stroke-linejoin', 'round');
        svg.appendChild(path);
        return svg;
    };

    const pageButton = (label, target, disabled, current, direction = '') => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'news-page-button' + (current ? ' is-current' : '');
        button.disabled = disabled;
        button.dataset.newsPage = String(target);
        if (current) button.setAttribute('aria-current', 'page');
        if (!direction) {
            button.setAttribute('aria-label', root.dataset.pageTemplate.replace(':number', label));
        }
        if (direction === 'previous') button.appendChild(arrow(false));
        button.appendChild(document.createTextNode(label));
        if (direction === 'next') button.appendChild(arrow(true));
        if (!disabled) {
            button.addEventListener('click', () => {
                currentPage = target;
                render();
                root.querySelector('.news-tabs').scrollIntoView({ block: 'nearest' });
            });
        }
        return button;
    };

    function render() {
        const term = normalize(search.value.trim());
        const matches = cards.filter(card => {
            if (currentFilter === 'pinned' && card.dataset.newsPinned !== '1') return false;
            if (currentFilter === 'active' && card.dataset.newsActive !== '1') return false;
            return !term || normalize(card.dataset.newsSearch).includes(term);
        });

        matches.sort((a, b) => {
            const pinned = Number(b.dataset.newsPinned) - Number(a.dataset.newsPinned);
            if (pinned) return pinned;
            const date = a.dataset.newsDate.localeCompare(b.dataset.newsDate);
            return sort.value === 'oldest' ? date : -date;
        });

        matches.forEach(card => list.appendChild(card));
        const totalPages = Math.max(1, Math.ceil(matches.length / pageSize));
        currentPage = Math.min(currentPage, totalPages);
        const start = (currentPage - 1) * pageSize;
        const visible = new Set(matches.slice(start, start + pageSize));
        cards.forEach(card => { card.hidden = !visible.has(card); });

        empty.hidden = matches.length !== 0;
        footer.hidden = matches.length === 0;
        if (matches.length === 0) return;

        range.textContent = root.dataset.summaryTemplate
            .replace(':from', String(start + 1))
            .replace(':to', String(Math.min(start + pageSize, matches.length)))
            .replace(':total', String(matches.length));

        pages.replaceChildren();
        pages.appendChild(pageButton(root.dataset.previousLabel, currentPage - 1, currentPage === 1, false, 'previous'));
        for (let number = 1; number <= totalPages; number++) {
            pages.appendChild(pageButton(String(number), number, false, number === currentPage));
        }
        pages.appendChild(pageButton(root.dataset.nextLabel, currentPage + 1, currentPage === totalPages, false, 'next'));
    }

    function setFilter(value) {
        currentFilter = value;
        currentPage = 1;
        status.value = value;
        tabs.forEach(tab => {
            const active = tab.dataset.newsTab === value;
            tab.classList.toggle('is-active', active);
            if (active) tab.setAttribute('aria-current', 'page');
            else tab.removeAttribute('aria-current');
        });
        render();
    }

    search.addEventListener('input', () => { currentPage = 1; render(); });
    sort.addEventListener('change', () => { currentPage = 1; render(); });
    status.addEventListener('change', () => setFilter(status.value));
    tabs.forEach(tab => tab.addEventListener('click', () => setFilter(tab.dataset.newsTab)));
    root.querySelector('.news-toolbar').hidden = false;
    root.querySelector('.news-tabs').hidden = false;
    render();
})();
