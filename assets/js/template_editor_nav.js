/* Preview navigation icons at their displayed sizes. / Podglad ikon nawigacji w rozmiarze menu. */
(function () {
    'use strict';
    document.querySelectorAll('[data-icon-picker]').forEach(function (picker) {
        var preview = picker.querySelector('[data-icon-preview]');
        var select = picker.querySelector('[data-icon-select]');
        var file = picker.querySelector('[data-icon-file]');
        var objectUrl = null;
        function update() {
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }
            if (file && file.files && file.files[0]) {
                objectUrl = URL.createObjectURL(file.files[0]);
                preview.src = objectUrl;
            } else if (select) {
                preview.src = select.selectedOptions[0].dataset.iconUrl;
            }
        }
        if (select) select.addEventListener('change', update);
        if (file) file.addEventListener('change', update);
        window.addEventListener('pagehide', function () {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
        }, { once: true });
    });
}());
