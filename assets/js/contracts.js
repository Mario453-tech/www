/*
 * Kontrakty dlugoterminowe - warstwa gracza.
 * Long-term contracts - player layer.
 *
 * Potwierdzenia podpisania/anulowania obsluguje globalny handler data-confirm z modal.js.
 * Sign/cancel confirmations are handled by the global data-confirm handler in modal.js.
 * Ten plik pokazuje tylko toast po akcji (flash).
 * This file only shows the post-action toast (flash).
 */
(function () {
    'use strict';

    function showFlashMessage() {
        var flash = document.getElementById('contracts-flash');
        if (!flash) {
            return;
        }

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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showFlashMessage);
    } else {
        showFlashMessage();
    }
})();
