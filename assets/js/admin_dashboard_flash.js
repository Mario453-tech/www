(() => {
    'use strict';
    const selector = '.alert-success, .alert-error, .alert-banner, .abp-flash, .db-alert--ok, .db-alert--err';
    function consume() {
        document.querySelectorAll(selector).forEach(node => {
            if (typeof window.showGameToast !== 'function') return;
            const type = node.matches('.alert-error, .alert-banner--error, .abp-flash--error, .db-alert--err') ? 'error' : 'success';
            const message = node.textContent.trim();
            node.remove();
            window.showGameToast('', message, type);
        });
    }
    document.addEventListener('DOMContentLoaded', consume);
    window.addEventListener('pagehide', () => document.querySelectorAll(selector).forEach(node => node.remove()));
})();
