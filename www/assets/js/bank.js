/**
 * bank.js - bank page interactions
 * bank.js - interakcje strony bankowej
 */

var _BANKL = window.BANK_LANG || {};
function bankl(k) { return _BANKL[k] || k; }

var _repayPendingForm = null;

/**
 * Intercepts the repay form submit and shows a confirmation modal.
 * Przechwytuje submit formularza splaty i pokazuje modal potwierdzenia.
 */
function repayConfirm(event, loanId, installment, remaining) {
    event.preventDefault();
    var form = document.getElementById('repay-form-' + loanId);
    if (!form) return true;

    var mode = form.querySelector('input[name="repay_mode"]:checked');
    var modeVal = mode ? mode.value : 'single';

    var amount = installment;
    var desc   = bankl('repay_modal_single');

    if (modeVal === 'multiple') {
        var countSel = form.querySelector('select[name="repay_count"]');
        var count    = countSel ? parseInt(countSel.value, 10) : 2;
        amount = installment * count;
        desc   = bankl('repay_modal_multiple').replace(':count', count);
    } else if (modeVal === 'full') {
        amount = remaining;
        desc   = bankl('repay_modal_full');
    }

    var fmt = amount.toLocaleString('pl-PL', { maximumFractionDigits: 0 }) + ' PLN';
    document.getElementById('repay-modal-desc').textContent   = desc;
    document.getElementById('repay-modal-amount').textContent = fmt;

    _repayPendingForm = form;
    var modal = document.getElementById('repay-modal');
    modal.style.display = 'flex';

    document.getElementById('repay-modal-confirm').onclick = function() {
        var formToSubmit = _repayPendingForm;
        repayModalClose();
        if (formToSubmit) formToSubmit.submit();
    };
    return false;
}

function repayModalClose() {
    var modal = document.getElementById('repay-modal');
    if (modal) modal.style.display = 'none';
    _repayPendingForm = null;
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') repayModalClose();
});

/**
 * Updates the displayed amount for selected installment count.
 * Aktualizuje wyswietlana kwote dla wybranej liczby rat.
 * @param {HTMLSelectElement} sel
 */
function repayUpdateMulti(sel) {
    var count       = parseInt(sel.value, 10);
    var installment = parseFloat(sel.dataset.installment) || 0;
    var total       = count * installment;
    var labelId     = sel.dataset.label;
    var label       = document.getElementById(labelId);
    if (label) {
        label.textContent = '= ' + total.toLocaleString(window.APP_LOCALE, {
            maximumFractionDigits: 0
        }) + bankl('pln');
    }
}
