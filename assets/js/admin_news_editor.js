/* Admin news editor - TinyMCE setup / Konfiguracja TinyMCE dla newsow admina */
(function () {
    var form = document.getElementById('admin-news-form');
    var titleField = document.getElementById('admin-news-title');
    var contentField = document.getElementById('admin-news-content');

    if (!form || typeof tinymce === 'undefined' || (!titleField && !contentField)) {
        return;
    }

    var blockFormats =
        'Akapit=p;Nag\u0142\u00f3wek 1=h1;Nag\u0142\u00f3wek 2=h2;' +
        'Nag\u0142\u00f3wek 3=h3;Nag\u0142\u00f3wek 4=h4;Cytat=blockquote';
    var baseContentStyle = [
        'body {',
        'font-family: Inter, Arial, Helvetica, sans-serif;',
        'font-size: 15px;',
        'line-height: 1.7;',
        'background: #0d0d18;',
        'color: #c8c8d4;',
        'padding: 14px 18px;',
        '}',
        'p { margin: 0 0 12px; }',
        'h1, h2, h3, h4 { margin: 0 0 12px; color: #c8a84b; line-height: 1.25; font-weight: 700; }',
        'h1 { font-size: 28px; }',
        'h2 { font-size: 23px; }',
        'h3 { font-size: 19px; }',
        'h4 { font-size: 16px; }',
        'ul, ol { margin: 0 0 14px; padding-left: 24px; }',
        'li { margin: 0 0 6px; }',
        'blockquote { margin: 14px 0; padding: 10px 14px; border-left: 3px solid #c8a84b; background: rgba(200,168,75,.08); color: #e8e8f0; }',
        'a { color: #c8a84b; }',
        'strong { color: #e8e8f0; }',
        'table { border-collapse: collapse; width: 100%; }',
        'th, td { border: 1px solid rgba(255,255,255,.15); padding: 8px 12px; }',
        'th { background: rgba(255,255,255,.06); color: rgba(232,232,240,.7); font-size: 12px; text-transform: uppercase; }'
    ].join(' ');

    if (titleField) {
        tinymce.init({
            selector: '#admin-news-title',
            language: 'pl',
            height: 190,
            menubar: false,
            plugins: ['link', 'code', 'wordcount'],
            block_formats: blockFormats,
            toolbar:
                'undo redo | blocks | bold italic underline | ' +
                'forecolor backcolor | alignleft aligncenter alignright | removeformat code',
            content_style: baseContentStyle,
            skin: 'oxide-dark',
            content_css: 'dark',
            branding: false,
            promotion: false
        });
    }

    if (contentField) {
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
            block_formats: blockFormats,
            toolbar:
                'undo redo | blocks | bold italic underline strikethrough | ' +
                'forecolor backcolor | alignleft aligncenter alignright alignjustify | ' +
                'bullist numlist outdent indent | link table | code fullscreen preview',
            content_style: baseContentStyle,
            skin: 'oxide-dark',
            content_css: 'dark',
            branding: false,
            promotion: false
        });
    }

    form.addEventListener('submit', function () {
        tinymce.triggerSave();
    });
})();
