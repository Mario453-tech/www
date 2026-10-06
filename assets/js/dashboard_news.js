/**
 * Dashboard company news widget.
 * Widget aktualnosci firmy na dashboardzie.
 */
(function () {
    'use strict';

    var newsList = document.getElementById('newsList');
    if (!newsList) return;

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function render(items) {
        if (!items.length) {
            newsList.innerHTML = '<p class="news-loading">' + escapeHtml(newsList.dataset.empty) + '</p>';
            return;
        }

        newsList.innerHTML = items.map(function (item) {
            var pinClass = item.is_pinned ? ' news-item--pinned' : '';
            var pin = item.is_pinned ? '<span class="news-item-pin" aria-hidden="true"></span>' : '';
            var title = typeof item.title_html === 'string' && item.title_html !== ''
                ? item.title_html : escapeHtml(item.title);
            var content = typeof item.content_html === 'string' ? item.content_html : '';

            return '<article class="news-item' + pinClass + '">' +
                '<div class="news-item-title">' + pin + '<div class="news-item-title-text">' + title + '</div></div>' +
                '<div class="news-item-content">' + content + '</div>' +
                '<time class="news-item-date">' + escapeHtml(item.date_fmt) + '</time>' +
                '</article>';
        }).join('');
    }

    function load() {
        fetch('/src/AdminNewsApi.php', { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) throw new Error('News request failed');
                return response.json();
            })
            .then(function (data) { render(Array.isArray(data.news) ? data.news : []); })
            .catch(function () {
                newsList.innerHTML = '<p class="news-loading">' + escapeHtml(newsList.dataset.error) + '</p>';
            });
    }

    load();
    window.setInterval(load, 60000);
})();
