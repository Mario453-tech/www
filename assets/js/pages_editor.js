/* 
   PAGES EDITOR - TinyMCE initialization
 */

tinymce.init({
    selector: '#tinymce-content',
    language: 'pl',
    height: 540,
    menubar: 'file edit view insert format tools',
    plugins: [
        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap',
        'preview', 'anchor', 'searchreplace', 'visualblocks', 'code',
        'fullscreen', 'insertdatetime', 'table', 'help', 'wordcount'
    ],
    toolbar:
        'undo redo | blocks | bold italic underline strikethrough | ' +
        'forecolor backcolor | alignleft aligncenter alignright | ' +
        'bullist numlist outdent indent | link table | code fullscreen',
    content_style: `
        body {
            font-family: system-ui, sans-serif;
            font-size: 14px;
            background: #0d0d18;
            color: #c8c8d4;
            padding: 12px 20px;
            line-height: 1.65;
        }
        a { color: #c8a84b; }
        strong { color: #c8a84b; }
        h1, h2, h3 { color: #c8a84b; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid rgba(255,255,255,.15); padding: 6px 10px; }
        th { background: rgba(255,255,255,.06); color: rgba(232,232,240,.5); font-size: .75rem; text-transform: uppercase; }
    `,
    skin: 'oxide-dark',
    content_css: 'dark',
    branding: false,
    promotion: false,
    setup: function (editor) {
        editor.on('init', function () {
            var form = document.getElementById('editForm');
            if (form) {
                form.addEventListener('submit', function () {
                    tinymce.triggerSave();
                });
            }
        });
    },
});
