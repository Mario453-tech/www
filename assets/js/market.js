/**
 * market.js Rynek ropy
 * Confirm dla sprzedazy / wystawienia oferty + toast dla komunikatow PHP.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var price = window.MARKET_PRICE || 0;
        var lang  = window.MARKET_LANG  || {};

 /* Confirm dla formularzy sprzedazy */
        document.querySelectorAll('.form-sell').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                var actionInput = form.querySelector('input[name="action"]');
                var amountInput = form.querySelector('input[name="amount"]');
                var action = actionInput ? actionInput.value : '';
                var amount = amountInput ? parseInt(amountInput.value, 10) : 0;

                var text, opts;

                if (action === 'sell_instant') {
                    var total = (amount * price).toLocaleString('pl-PL', { maximumFractionDigits: 0 });
                    text = (lang.confirm_sell || '')
                        .replace(':bbl',   amount.toLocaleString('pl-PL'))
                        .replace(':total', total);
                    opts = { type: 'warning', confirmLabel: lang.confirm_sell_btn || '' };

                } else if (action === 'create_offer') {
                    var limitInput = form.querySelector('input[name="limit_price"]');
                    var limit = limitInput ? parseInt(limitInput.value, 10) : 0;
                    text = (lang.confirm_offer || '')
                        .replace(':bbl',   amount.toLocaleString('pl-PL'))
                        .replace(':price', limit.toLocaleString('pl-PL'));
                    opts = { type: 'info', confirmLabel: lang.confirm_offer_btn || '' };

                } else {
                    return; // inna akcja  bez confirma
                }

                if (typeof confirmAction === 'function') {
                    e.preventDefault();
                    confirmAction(text, function () { form.submit(); }, opts);
                }
            });
        });

 /* Toast dla $success / $error z PHP */
        var msg = window.MARKET_MSG || '';
        var err = window.MARKET_ERR || '';

        if (msg && typeof showGameToast === 'function') {
            showGameToast(msg, 'success');
        }
        if (err && typeof showGameToast === 'function') {
            showGameToast(err, 'error');
        }
    });
})();
