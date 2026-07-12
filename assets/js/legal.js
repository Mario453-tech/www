/*
 * Legal department application form handler.
 * Shows confirmation modals and one-time flash messages.
 */
(function () {
    'use strict';

    var L = window.LEGAL_LANG || {};

    function showFlashMessage() {
        var flash = document.getElementById('legal-flash');
        if (!flash) return;

        var error = flash.dataset.error || '';
        var success = flash.dataset.success || '';
        flash.remove();

        if (error) {
            if (typeof window.alertError === 'function') {
                window.alertError(error);
            } else if (typeof window.showGameToast === 'function') {
                window.showGameToast(error, 'error');
            }
        } else if (success) {
            if (typeof window.alertInfo === 'function') {
                window.alertInfo(success);
            } else if (typeof window.showGameToast === 'function') {
                window.showGameToast(success, 'success');
            }
        }
    }

    function escHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showFlashMessage);
    } else {
        showFlashMessage();
    }

    document.addEventListener('submit', function (e) {
        var form = e.target.closest('form.legal-submit-form');
        if (!form) return;

        // Second pass after modal confirmation.
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
        var cost = form.dataset.cost || '';
        var reviewTime = form.dataset.reviewTime || '';

        var bodyHtml =
            '<div class="legal-confirm-rows">' +
                '<div class="legal-confirm-row">' +
                    '<span class="legal-confirm-label">' + (L.label_region || 'Region') + '</span>' +
                    '<span class="legal-confirm-val">' + escHtml(regionName) + '</span>' +
                '</div>' +
                '<div class="legal-confirm-row">' +
                    '<span class="legal-confirm-label">' + (L.label_time || 'Czas rozpatrzenia') + '</span>' +
                    '<span class="legal-confirm-val">' + escHtml(reviewTime) + '</span>' +
                '</div>' +
            '</div>' +
            '<div class="legal-confirm-total">' +
                '<span>' + (L.label_cost || 'Opłata za wniosek') + '</span>' +
                '<span class="legal-confirm-cost">' + escHtml(cost) + ' PLN</span>' +
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
            title: L.modal_title || 'Złóż wniosek',
            type: 'confirm',
            confirmLabel: L.modal_confirm || 'Złóż wniosek',
            bodyHtml: bodyHtml,
        });
    }, true);

    // Bribe confirmation with a reputation-loss warning.
    document.addEventListener('submit', function (e) {
        var form = e.target.closest('form.legal-bribe-form');
        if (!form) return;

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
        var cost = form.dataset.cost || '';

        // Catch risk is deliberately not shown to the player.
        var bodyHtml =
            '<div class="legal-confirm-rows">' +
                '<div class="legal-confirm-row">' +
                    '<span class="legal-confirm-label">' + (L.label_region || 'Region') + '</span>' +
                    '<span class="legal-confirm-val">' + escHtml(regionName) + '</span>' +
                '</div>' +
            '</div>' +
            '<div class="legal-confirm-total">' +
                '<span>' + (L.bribe_cost || 'Koszt łapówki') + '</span>' +
                '<span class="legal-confirm-cost">' + escHtml(cost) + ' PLN</span>' +
            '</div>' +
            '<p class="legal-confirm-note legal-confirm-note--danger">' +
                (L.bribe_warning || 'Ryzykujesz wiarygodność firmy. Przy wpadce stracisz gotówkę i reputację, a region zostanie zablokowany na dłużej.') +
            '</p>';

        window.confirmAction('', function () {
            form.dataset.confirmed = '1';
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
            setTimeout(function () { form.dataset.confirmed = ''; }, 0);
        }, {
            title: L.bribe_title || 'Załatw po cichu',
            type: 'danger',
            confirmLabel: L.bribe_confirm || 'Daję łapówkę',
            bodyHtml: bodyHtml,
        });
    }, true);
})();
