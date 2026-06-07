// Czesciowa paginacja stron / Partial page pagination.
(function () {
    var paginationSelector = [
        '.pagination',
        '.sale-history-pagination',
        '.inc-pagination',
        '.hub-pagination',
        '.pipeline-pagination'
    ].join(',');
    var rootSelectors = ['.game-shell-module', '.admin-wrap', 'main.main-content'];
    var isLoading = false;

    function samePage(targetUrl) {
        return targetUrl.origin === window.location.origin
            && targetUrl.pathname === window.location.pathname;
    }

    function cleanUrl(url) {
        return url.origin + url.pathname;
    }

    function findRoot(doc) {
        for (var i = 0; i < rootSelectors.length; i++) {
            var root = doc.querySelector(rootSelectors[i]);
            if (root) {
                return { selector: rootSelectors[i], node: root };
            }
        }
        return null;
    }

    function findScrollTarget(link, fallbackRoot) {
        var target = link.closest('section[id], article[id], .card[id], [aria-labelledby]');
        if (target && target.id) {
            return '#' + target.id;
        }

        if (target && target.getAttribute('aria-labelledby')) {
            return '#' + target.getAttribute('aria-labelledby');
        }

        return fallbackRoot && fallbackRoot.id ? '#' + fallbackRoot.id : null;
    }

    function scrollTo(selector, root, paginationIndex) {
        var target = selector ? document.querySelector(selector) : null;
        if (!target && typeof paginationIndex === 'number' && paginationIndex >= 0) {
            var scope = root || document;
            target = scope.querySelectorAll(paginationSelector)[paginationIndex] || null;
        }
        if (!target) {
            target = root || document.querySelector('main.main-content') || document.body;
        }
        if (target && target.scrollIntoView) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    async function loadPartial(ajaxUrl, scrollSelector, paginationIndex, pushHistory) {
        if (isLoading) {
            return;
        }

        var current = findRoot(document);
        if (!current) {
            window.location.href = ajaxUrl;
            return;
        }

        isLoading = true;
        current.node.style.opacity = '0.55';

        try {
            var response = await fetch(ajaxUrl, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            var html = await response.text();
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var next = doc.querySelector(current.selector);
            if (!next) {
                window.location.href = ajaxUrl;
                return;
            }

            current.node.replaceWith(next);
            if (pushHistory !== false) {
                var visibleUrl = cleanUrl(new URL(ajaxUrl, window.location.href));
                history.pushState({
                    ajaxPagination: true,
                    ajaxUrl: ajaxUrl,
                    scrollSelector: scrollSelector,
                    paginationIndex: paginationIndex
                }, '', visibleUrl);
            }

            document.dispatchEvent(new CustomEvent('ajax-pagination:updated', {
                detail: { root: next, ajaxUrl: ajaxUrl }
            }));
            scrollTo(scrollSelector, next, paginationIndex);
        } catch (e) {
            window.location.href = ajaxUrl;
        } finally {
            isLoading = false;
        }
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[href]');
        if (!link || link.closest('.logistics-pagination')) {
            return;
        }

        if (link.target || link.hasAttribute('download') || event.ctrlKey || event.metaKey || event.shiftKey) {
            return;
        }

        var pagination = link.closest(paginationSelector);
        if (!pagination) {
            return;
        }

        var targetUrl = new URL(link.href, window.location.href);
        if (!samePage(targetUrl)) {
            return;
        }

        event.preventDefault();
        var paginationIndex = Array.prototype.indexOf.call(document.querySelectorAll(paginationSelector), pagination);
        loadPartial(targetUrl.toString(), findScrollTarget(link, pagination), paginationIndex, true);
    });

    window.addEventListener('popstate', function () {
        var state = history.state || {};
        if (!state.ajaxPagination || !state.ajaxUrl) {
            return;
        }
        loadPartial(state.ajaxUrl, state.scrollSelector || null, state.paginationIndex, false);
    });
}());
