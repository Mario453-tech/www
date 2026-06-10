/**
 * wallet.js - logika portfela gracza (transfer gotowka <-> konto).
 * wallet.js - player wallet logic (cash <-> bank account transfer).
 *
 * Wymaga / Requires:
 *  - window.WALLET_API    (URL endpointu / endpoint URL)
 *  - window.WALLET_CSRF   (token CSRF)
 *  - window.WALLET_LANG   (tlumaczenia / translations)
 *  - modal.js (confirmAction, alertError, showGameToast)
 */

(function () {
    'use strict';

    var API  = window.WALLET_API  || '/wallet-transfer';
    var CSRF = window.WALLET_CSRF || '';
    var L    = window.WALLET_LANG || {};

    function wl(k) { return L[k] || k; }

    // Formatuje liczbe PLN / Formats a PLN number.
    function fmtPLN(v) {
        return parseFloat(v).toLocaleString('pl-PL', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' PLN';
    }

    // Parsuje kwote z pola (spacje, przecinek dziesietny).
    // Parses amount from input field (spaces, decimal comma).
    function parseAmount(raw) {
        var s = (raw || '').replace(/\s/g, '').replace(',', '.');
        var v = parseFloat(s);
        return isFinite(v) ? v : 0;
    }

    // Formatuje liczbe jako integer bez jednostki (dla kafli HUD).
    // Formats a number as integer without unit (for HUD tiles).
    function fmtInt(v) {
        return Math.round(parseFloat(v)).toLocaleString('pl-PL');
    }

    // Ustawia tekst elementu - format zalezy od data-wallet-fmt.
    // Sets element text - format depends on data-wallet-fmt attribute.
    function setElValue(el, v) {
        el.textContent = el.dataset.walletFmt === 'int' ? fmtInt(v) : fmtPLN(v);
    }

    // Aktualizuje wyswietlane salda w DOM.
    // Updates displayed balances in the DOM.
    function updateBalances(newCash, newBank) {
        var cashEls = document.querySelectorAll('[data-wallet-cash]');
        var bankEls = document.querySelectorAll('[data-wallet-bank]');
        cashEls.forEach(function (el) {
            setElValue(el, newCash);
        });
        bankEls.forEach(function (el) {
            setElValue(el, newBank);
        });
        // Zaktualizuj data-balance dla formularza przelewu P2P.
        // Update data-balance for P2P transfer form.
        var trigger = document.getElementById('bank-transfer-trigger');
        if (trigger) {
            trigger.dataset.balance = newBank;
        }
    }

    // Oblicza i wyswietla podglad prowizji.
    // Computes and shows fee preview.
    function updateFeePreview(inputEl, previewEl) {
        var amount = parseAmount(inputEl.value);
        if (amount < 100) {
            previewEl.innerHTML = '';
            return;
        }
        var feePct  = window.WALLET_FEE_PCT  || 0.005;
        var feeMin  = window.WALLET_FEE_MIN  || 10;
        var fee     = Math.max(feeMin, Math.round(amount * feePct * 100) / 100);
        var total   = amount + fee;
        previewEl.innerHTML = wl('fee_preview')
            .replace(':fee',   fmtPLN(fee))
            .replace(':total', fmtPLN(total));
    }

    // Obsluga wysylki formularza transferu.
    // Handles transfer form submission.
    function handleFormSubmit(form) {
        var action  = form.dataset.action;
        var input   = form.querySelector('input[name="amount"]');
        var amount  = parseAmount(input ? input.value : '');
        var feePct  = window.WALLET_FEE_PCT || 0.005;
        var feeMin  = window.WALLET_FEE_MIN || 10;
        var fee     = Math.max(feeMin, Math.round(amount * feePct * 100) / 100);
        var total   = amount + fee;
        var dirMsg  = (action === 'cash_to_bank') ? wl('confirm_cash_to_bank') : wl('confirm_bank_to_cash');
        var msg     = dirMsg
            .replace(':amount', fmtPLN(amount))
            .replace(':fee',    fmtPLN(fee))
            .replace(':total',  fmtPLN(total));

        if (typeof window.confirmAction === 'function') {
            window.confirmAction(msg, function () {
                doSubmit(form, action, amount);
            });
        } else {
            doSubmit(form, action, amount);
        }
    }

    // Wysyla AJAX POST / Sends AJAX POST.
    function doSubmit(form, action, amount) {
        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) { submitBtn.disabled = true; }

        var fd = new FormData();
        fd.append('action',     action);
        fd.append('amount',     String(amount));
        fd.append('csrf_token', CSRF);

        fetch(API, {
            method:  'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body:    fd,
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (submitBtn) { submitBtn.disabled = false; }
            if (res.success) {
                updateBalances(res.new_cash, res.new_bank);
                var amtInput = form.querySelector('input[name="amount"]');
                if (amtInput) { amtInput.value = ''; }
                var preview = form.querySelector('.wallet-tf-fee-preview');
                if (preview) { preview.innerHTML = ''; }
                if (typeof window.showGameToast === 'function') {
                    window.showGameToast(res.message, 'success');
                } else if (typeof window.alertInfo === 'function') {
                    window.alertInfo(res.message);
                }
            } else {
                if (typeof window.alertError === 'function') {
                    window.alertError(res.message || wl('err_generic'));
                } else if (typeof window.showGameToast === 'function') {
                    window.showGameToast(res.message || wl('err_generic'), 'error');
                }
            }
        })
        .catch(function () {
            if (submitBtn) { submitBtn.disabled = false; }
            if (typeof window.alertError === 'function') {
                window.alertError(wl('err_network'));
            }
        });
    }

    // Inicjalizacja po zaladowaniu DOM / Initialise after DOM load.
    function init() {
        // Podpiecie formularzy transferu portfela.
        // Attach wallet transfer forms.
        var forms = document.querySelectorAll('.wallet-tf-form');
        forms.forEach(function (form) {
            // Podglad prowizji przy wpisywaniu kwoty.
            // Fee preview while typing amount.
            var input   = form.querySelector('input[name="amount"]');
            var preview = form.querySelector('.wallet-tf-fee-preview');
            if (input && preview) {
                input.addEventListener('input', function () {
                    updateFeePreview(input, preview);
                });
            }

            // Submit z potwierdzeniem.
            // Submit with confirmation.
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                handleFormSubmit(form);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
