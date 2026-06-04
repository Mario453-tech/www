/*
 * Dział prawny — obsługa formularza składania wniosku.
 * Legal department — application form handler.
 * Przechwytuje submit i pokazuje modal potwierdzenia z podsumowaniem.
 * Intercepts submit and shows a confirmation modal with a breakdown.
 */
(function () {
    'use strict';

    var L = window.LEGAL_LANG || {};

    document.addEventListener('submit', function (e) {
        var form = e.target.closest('form.legal-submit-form');
        if (!form) return;

        // Drugie przejście (po kliknięciu "Złóż" w modalu) — przepuść.
        // Second pass (after modal confirm) — let through.
        if (form.dataset.confirmed === '1') {
            form.dataset.confirmed = '';
            return;
        }

        e.preventDefault();

        if (typeof window.confirmAction !== 'function') {
            form.dataset.confirmed = '1';
            form.submit();
            return;
        }

        var regionName = form.dataset.regionName || '';
        var cost       = form.dataset.cost       || '';
        var reviewTime = form.dataset.reviewTime  || '';

        var bodyHtml =
            '<div class="legal-confirm-rows">' +
                '<div class="legal-confirm-row">' +
                    '<span class="legal-confirm-label">' + (L.label_region || 'Region') + '</span>' +
                    '<span class="legal-confirm-val">'   + regionName + '</span>' +
                '</div>' +
                '<div class="legal-confirm-row">' +
                    '<span class="legal-confirm-label">' + (L.label_time || 'Czas rozpatrzenia') + '</span>' +
                    '<span class="legal-confirm-val">'   + reviewTime + '</span>' +
                '</div>' +
            '</div>' +
            '<div class="legal-confirm-total">' +
                '<span>' + (L.label_cost || 'Opłata za wniosek') + '</span>' +
                '<span class="legal-confirm-cost">' + cost + ' PLN</span>' +
            '</div>' +
            '<p class="legal-confirm-note">' + (L.modal_cost_note || 'Opłata zostanie pobrana natychmiast.') + '</p>';

        window.confirmAction('', function () {
            form.dataset.confirmed = '1';
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
            setTimeout(function () { form.dataset.confirmed = ''; }, 0);
        }, {
            title:        L.modal_title    || 'Złóż wniosek',
            type:         'confirm',
            confirmLabel: L.modal_confirm  || 'Złóż wniosek',
            bodyHtml:     bodyHtml,
        });
    }, true);
})();
