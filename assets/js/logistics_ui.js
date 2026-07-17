(function () {
    'use strict';

    function applyProgressWidths(root) {
        const scope = root || document;
        scope.querySelectorAll('[data-progress-width]').forEach(function (element) {
            const value = Math.max(0, Math.min(100, Number(element.dataset.progressWidth || 0)));
            element.style.width = value + '%';
        });
    }

    window.applyLogisticsProgressWidths = applyProgressWidths;

    document.addEventListener('ajax-pagination:updated', function (event) {
        applyProgressWidths(event.detail && event.detail.root ? event.detail.root : document);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { applyProgressWidths(document); });
    } else {
        applyProgressWidths(document);
    }
})();
