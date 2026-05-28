/* Admin Help editor - TinyMCE init */
tinymce.init({
    selector: '#ah-tinymce-content',
    language: 'pl',
    height: 500,
    menubar: 'file edit view insert format tools',
    plugins: [
        'advlist', 'autolink', 'lists', 'link', 'charmap',
        'preview', 'anchor', 'searchreplace', 'visualblocks', 'code',
        'fullscreen', 'insertdatetime', 'help', 'wordcount'
    ],
    toolbar:
        'undo redo | blocks | bold italic underline | ' +
        'forecolor | alignleft aligncenter alignright | ' +
        'bullist numlist outdent indent | link | code fullscreen',
    content_style: [
        'body { font-family: system-ui, sans-serif; font-size: 14px;',
        '       background: #0d0d18; color: #c8c8d4; padding: 12px 16px; }',
        'a { color: #c8a84b; } strong { color: #c8a84b; }',
        'h3 { color: #e8a020; margin: 0 0 8px; }',
        'code { background: rgba(255,255,255,.08); padding: 1px 5px; border-radius: 3px; }'
    ].join(' '),
    skin: 'oxide-dark',
    content_css: 'dark',
    branding: false,
    promotion: false,
    setup: function (editor) {
        editor.on('init', function () {
            var form = document.getElementById('ahEditForm');
            if (form) {
                form.addEventListener('submit', function () {
                    tinymce.triggerSave();
                });
            }
        });
    },
});
