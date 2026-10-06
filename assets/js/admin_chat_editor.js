/* Admin broadcast editor. / Edytor komunikatow admina. */
(function () {
    'use strict';
    var form = document.getElementById('admin-chat-message-form');
    var field = document.getElementById('admin-chat-message');
    var error = document.getElementById('admin-chat-message-error');
    if (form && field && error && typeof window.tinymce !== 'undefined') {
        window.tinymce.init({
            selector: '#admin-chat-message',
            language: document.documentElement.lang === 'pl' ? 'pl' : 'en',
            height: 300,
            menubar: false,
            plugins: 'lists wordcount',
            toolbar: 'undo redo | bold italic | bullist numlist | removeformat',
            toolbar_mode: 'sliding',
            valid_elements: 'p,br,strong/b,em/i,u,s,ul,ol,li',
            skin: 'oxide-dark',
            content_css: 'dark',
            branding: false,
            promotion: false
        });
    }

    if (form && field && error) form.addEventListener('submit', function (event) {
        var editor = window.tinymce && window.tinymce.get(field.id);
        if (editor) editor.save();
        var doc = new DOMParser().parseFromString(field.value, 'text/html');
        doc.querySelectorAll('script,style,iframe,object,embed,svg,math,template').forEach(function (node) { node.remove(); });
        var text = editor ? editor.getContent({ format: 'text' }) : doc.body.textContent;
        text = text.replace(/\u00a0/g, ' ').trim();
        if (!text || Array.from(text).length > 500) {
            event.preventDefault();
            error.textContent = form.dataset.error;
            error.hidden = false;
            if (editor) editor.focus();
            else field.focus();
        } else {
            error.hidden = true;
        }
    });

    var translationTemplate = document.getElementById('chat-room-translation-template');
    var autoClearForm = document.getElementById('auto-clear-form');
    if (autoClearForm) {
        var autoClearToggle = document.getElementById('autoClearToggle');
        var autoClearStatus = document.getElementById('autoClearStatus');
        var autoClearRow = document.getElementById('autoClearIntervalRow');
        autoClearToggle.addEventListener('change', function () {
            autoClearStatus.textContent = autoClearToggle.checked ? autoClearForm.dataset.enabledLabel : autoClearForm.dataset.disabledLabel;
            autoClearStatus.className = 'badge ml-6 ' + (autoClearToggle.checked ? 'badge-active' : 'badge-inactive');
            autoClearRow.classList.toggle('is-disabled', !autoClearToggle.checked);
        });
        autoClearForm.querySelectorAll('input[name="auto_clear_interval"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                autoClearForm.querySelectorAll('.radio-pill').forEach(function (pill) { pill.classList.remove('radio-pill--active'); });
                radio.closest('.radio-pill').classList.add('radio-pill--active');
            });
        });
    }
    document.addEventListener('click', function (event) {
        var addButton = event.target.closest('[data-add-translation]');
        if (addButton && translationTemplate) {
            var translationForm = addButton.closest('form');
            var list = translationForm && translationForm.querySelector('[data-translation-list]');
            if (!list) return;
            var index = parseInt(list.dataset.nextIndex || '0', 10);
            list.insertAdjacentHTML('beforeend', translationTemplate.innerHTML.replaceAll('__INDEX__', String(index)));
            list.dataset.nextIndex = String(index + 1);
            list.lastElementChild.querySelector('input').focus();
            return;
        }
        var removeButton = event.target.closest('[data-remove-translation]');
        if (!removeButton) return;
        var row = removeButton.closest('[data-translation-row]');
        var parent = row && row.closest('[data-translation-list]');
        if (row && parent && parent.querySelectorAll('[data-translation-row]').length > 1) row.remove();
    });
})();
