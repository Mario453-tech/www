/*
 * Dzial prawny admin - potwierdzenia akcji.
 * Legal admin - action confirmations.
 */
(function () {
    'use strict';

    document.addEventListener('submit', function (e) {
        var form = e.target.closest('form.js-confirm-form');
        if (!form) return;
        if (form.dataset.confirmed === '1') {
            form.dataset.confirmed = '';
            return;
        }

        var msg = form.dataset.confirm || '';
        var title = form.dataset.confirmTitle || '';
        var type = form.dataset.confirmType || 'confirm';

        e.preventDefault();

        if (typeof window.confirmAction !== 'function') {
            return;
        }

        window.confirmAction(msg, function () {
            form.dataset.confirmed = '1';
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
            setTimeout(function () { form.dataset.confirmed = ''; }, 0);
        }, { title: title, type: type });
    }, true);
})();
