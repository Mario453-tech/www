/**
 * admin_protection.js - panel admina ochrony: modalne potwierdzenia formularzy.
 * admin_protection.js - protection admin panel: modal form confirmations.
 *
 * Formularze z data-confirm przechodza przez confirmSubmit z modal.js
 * (zero natywnych confirm()).
 */
(function () {
    'use strict';

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.confirmed === '1') return;
            event.preventDefault();
            if (typeof window.confirmSubmit === 'function') {
                window.confirmSubmit(form, form.dataset.confirm || '?', { type: 'danger' });
            } else {
                form.dataset.confirmed = '1';
                form.submit();
            }
        });
    });
})();
