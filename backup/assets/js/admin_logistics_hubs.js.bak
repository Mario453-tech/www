/**
 * admin_logistics_hubs.js — panel admina hubow logistycznych.
 * Depends on: modal.js (confirmAction), ADMIN_LOGISTICS_LANG (window global from PHP).
 */
(function () {
    'use strict';

    const lang = () => window.ADMIN_LOGISTICS_LANG || {};

    // Potwierdzenie seeda regionu — zastepuje natywny confirm().
    // Confirm for region seed — replaces native confirm().
    const seedBtn = document.getElementById('seed-region-submit');
    const seedSel = document.getElementById('seed-region-select');

    if (seedBtn && seedSel) {
        seedBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const regionText = seedSel.options[seedSel.selectedIndex]?.text || '';
            const msg = (lang().seed_confirm || '')
                .replace(':region', regionText);
            confirmAction(msg, function () {
                seedBtn.closest('form').submit();
            }, { type: 'confirm' });
        });
    }

})();
