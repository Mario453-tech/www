/* Admin news editor - TinyMCE setup / Konfiguracja TinyMCE dla newsow admina */
(function () {
    var form = document.getElementById('admin-news-form');
    var field = document.getElementById('admin-news-content');

    if (!form || !field || typeof tinymce === 'undefined') {
        return;
    }

    tinymce.init({
        selector: '#admin-news-content',
        language: 'pl',
        height: 520,
        menubar: 'edit view insert format tools table',
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'charmap',
            'preview', 'searchreplace', 'visualblocks', 'code',
            'fullscreen', 'table', 'help', 'wordcount'
        ],
        toolbar:
            'undo redo | blocks | bold italic underline strikethrough | ' +
            'forecolor backcolor | alignleft aligncenter alignright alignjustify | ' +
            'bullist numlist outdent indent | link table | code fullscreen preview',
        content_style: [
            'body {',
            'font-family: Inter, Arial, Helvetica, sans-serif;',
            'font-size: 15px;',
            'line-height: 1.7;',
            'background: #0d0d18;',
            'color: #c8c8d4;',
            'padding: 14px 18px;',
            '}',
            'a { color: #c8a84b; }',
            'strong { color: #e8e8f0; }',
            'table { border-collapse: collapse; width: 100%; }',
            'th, td { border: 1px solid rgba(255,255,255,.15); padding: 8px 12px; }',
            'th { background: rgba(255,255,255,.06); color: rgba(232,232,240,.7); font-size: 12px; text-transform: uppercase; }'
        ].join(' '),
        skin: 'oxide-dark',
        content_css: 'dark',
        branding: false,
        promotion: false,
        setup: function (editor) {
            editor.on('init', function () {
                form.addEventListener('submit', function () {
                    tinymce.triggerSave();
                });
            });
        }
    });
})();
