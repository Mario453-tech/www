/*
 * Language switcher autosubmit.
 * Polski - automatyczne wyslanie formularza wyboru jezyka.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-language-switcher]').forEach(function (select) {
            select.addEventListener('change', function () {
                if (select.form) {
                    select.form.submit();
                }
            });
        });
    });
})();
