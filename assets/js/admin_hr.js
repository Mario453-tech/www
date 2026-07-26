(function () {
    'use strict';

    function confirmForm(form, message, label) {
        confirmAction(message, function () {
            form.submit();
        }, {
            type: 'danger',
            confirmLabel: label,
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-confirm-submit]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                confirmForm(form, form.dataset.confirmMessage || '', form.dataset.confirmLabel || '');
            });
        });

        document.querySelectorAll('[data-confirm-form]').forEach(function (button) {
            button.addEventListener('click', function () {
                var form = document.getElementById(button.dataset.confirmForm || '');
                if (!form) {
                    return;
                }
                confirmForm(form, button.dataset.confirmMessage || '', button.dataset.confirmLabel || '');
            });
        });
    });
})();