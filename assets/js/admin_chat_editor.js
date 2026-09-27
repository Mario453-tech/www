/* Admin broadcast editor. / Edytor komunikatow admina. */
(function () {
    'use strict';
    var form = document.getElementById('admin-chat-message-form');
    var field = document.getElementById('admin-chat-message');
    var error = document.getElementById('admin-chat-message-error');
    if (!form || !field || !error) return;

    if (typeof window.tinymce !== 'undefined') {
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

    form.addEventListener('submit', function (event) {
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
})();
