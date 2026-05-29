/* 
   NEWSLETTER EDITOR — TinyMCE + panel logic
   Config passed from PHP via window.NL_CONFIG
    */

tinymce.init({
    selector: '#nl-content',
    height: 480,
    menubar: 'edit view insert format tools',
    plugins: [
        'advlist', 'autolink', 'lists', 'link', 'charmap',
        'preview', 'anchor', 'searchreplace', 'visualblocks', 'code',
        'fullscreen', 'table', 'help', 'wordcount'
    ],
    toolbar:
        'undo redo | blocks | bold italic underline | ' +
        'forecolor backcolor | alignleft aligncenter alignright | ' +
        'bullist numlist | link table | code fullscreen',
    content_style: `
        body { font-family: Arial, Helvetica, sans-serif; font-size: 15px;
               background: #0d0d18; color: #c8c8d4; padding: 12px 16px; line-height: 1.7; }
        a { color: #c8a84b; }
        a[style*="color"] { color: unset; }
        a span[style*="color"], span[style*="color"] a { color: inherit !important; }
        strong { color: #e8e8f0; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid rgba(255,255,255,.15); padding: 8px 12px; }
        th { background: rgba(255,255,255,.06); color: rgba(232,232,240,.5);
             font-size: .75rem; text-transform: uppercase; }
    `,
    skin: 'oxide-dark',
    branding: false,
    promotion: false,
    setup: function (editor) {
        editor.on('init', nlBindEvents);
    },
});

// Fallback: bind events on DOMContentLoaded in case TinyMCE init is delayed or fails
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(nlBindEvents, 50);
});

var _nlBound = false;
function nlBindEvents() {
    if (_nlBound) return;
    _nlBound = true;
    var cfg = window.NL_CONFIG || {};

    // Radio — toggle single email field
    document.querySelectorAll('input[name="send_target"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            nlToggleSingle(this.value === 'single');
        });
    });

    // Preview button
    var btnPreview = document.getElementById('nl-btn-preview');
    if (btnPreview) {
        btnPreview.addEventListener('click', function () {
            tinymce.triggerSave();
            document.getElementById('nl-action').value = 'preview';
            document.getElementById('nl-form').submit();
        });
    }

    // Send button
    var btnSend = document.getElementById('nl-send-btn');
    if (btnSend) {
        btnSend.addEventListener('click', function () {
            tinymce.triggerSave();
            var isSingle = document.querySelector('input[name="send_target"]:checked')?.value === 'single';
            if (isSingle) {
                var em = document.getElementById('nl-single-email').value.trim();
                if (!em) { alertWarning(cfg.warnNoEmail || ''); return; }
                var msg = (cfg.confirmSingle || '').replace('{email}', em);
            } else {
                var msg = (cfg.confirmAll || '').replace('{n}', cfg.countAll || 0);
            }
            confirmAction(msg, function () {
                document.getElementById('nl-action').value = 'send';
                document.getElementById('nl-form').submit();
            }, { type: 'danger' });
        });
    }
}

function nlToggleSingle(show) {
    var wrap = document.getElementById('nl-single-wrap');
    var btn  = document.getElementById('nl-send-btn');
    var cfg  = window.NL_CONFIG || {};
    if (wrap) wrap.style.display = show ? '' : 'none';
    if (btn)  btn.textContent    = ' ' + (show ? cfg.btnSendSingle : cfg.btnSendAll);
}
