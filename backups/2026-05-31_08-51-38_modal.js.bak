/*
 * Globalny system modali i powiadomie dla gry oraz panelu admina.
 * API:
 * confirmAction(text, callback, options?)
 * promptInput(text, defaultValue, callback, options?)
 * alertInfo(text, title?, callback?)
 * alertError(text, title?, callback?)
 * alertWarning(text, title?, callback?)
 * showGameToast(message, type?)
 * showGameToast(title, message, type?)
 */

(function () {
    'use strict';

    var ICONS = {
        info: 'i',
        warning: '!',
        danger: 'x',
        confirm: '?',
        input: '...',
        success: 'ok'
    };

    var TYPES = ['success', 'error', 'warning', 'info'];
    var _L = window.MODAL_LANG || {};
    var LABELS = {
        confirm: _L.confirm || 'Potwierd�',
        cancel: _L.cancel || 'Anuluj',
        ok: _L.ok || 'OK',
        title_error: _L.title_error || 'B��d',
        title_info: _L.title_info || 'Informacja',
        title_warn: _L.title_warn || 'Uwaga',
        title_success: _L.title_success || 'Sukces',
        close: _L.close || 'Zamknij'
    };

    var overlay;
    var box;
    var iconEl;
    var titleEl;
    var bodyEl;
    var actionsEl;
    var toastStack;

    function buildModal() {
        if (document.getElementById('app-modal')) {
            overlay = document.getElementById('app-modal');
            box = overlay.querySelector('#modal-box');
            iconEl = overlay.querySelector('#modal-icon');
            titleEl = overlay.querySelector('#modal-title');
            bodyEl = overlay.querySelector('#modal-body');
            actionsEl = overlay.querySelector('#modal-actions');
            return;
        }

        overlay = document.createElement('div');
        overlay.id = 'app-modal';
        overlay.className = 'modal-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'modal-title');
        overlay.innerHTML =
            '<div class="modal-box modal-box--confirm" id="modal-box">' +
                '<span class="modal-icon" id="modal-icon" aria-hidden="true"></span>' +
                '<p class="modal-title" id="modal-title"></p>' +
                '<div class="modal-body" id="modal-body"></div>' +
                '<div class="modal-actions" id="modal-actions"></div>' +
            '</div>';

        document.body.appendChild(overlay);

        box = overlay.querySelector('#modal-box');
        iconEl = overlay.querySelector('#modal-icon');
        titleEl = overlay.querySelector('#modal-title');
        bodyEl = overlay.querySelector('#modal-body');
        actionsEl = overlay.querySelector('#modal-actions');

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('modal-visible')) {
                closeModal();
            }
        });
    }

    function buildToastStack() {
        if (document.getElementById('app-toast-stack')) {
            toastStack = document.getElementById('app-toast-stack');
            return;
        }

        toastStack = document.createElement('div');
        toastStack.id = 'app-toast-stack';
        toastStack.className = 'app-toast-stack';
        toastStack.setAttribute('aria-live', 'polite');
        toastStack.setAttribute('aria-atomic', 'true');
        document.body.appendChild(toastStack);
    }

    function openModal() {
        buildModal();
        requestAnimationFrame(function () {
            overlay.classList.add('modal-visible');
        });
    }

    function closeModal() {
        if (!overlay) {
            return;
        }
        overlay.classList.remove('modal-visible');
    }

    function setType(type) {
        box.className = 'modal-box modal-box--' + type;
        iconEl.textContent = ICONS[type] || '';
    }

    function makeBtn(label, cls, onClick) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'modal-btn ' + cls;
        btn.textContent = label;
        btn.addEventListener('click', onClick);
        return btn;
    }

    function defaultToastTitle(type) {
        switch (type) {
            case 'success': return LABELS.title_success;
            case 'error': return LABELS.title_error;
            case 'warning': return LABELS.title_warn;
            default: return LABELS.title_info;
        }
    }

    function normalizeToastArgs(arg1, arg2, arg3) {
        var type = 'info';
        var title = defaultToastTitle(type);
        var message = '';

        if (typeof arg3 === 'string') {
            type = arg3;
            title = arg1 || defaultToastTitle(type);
            message = arg2 || '';
            return { title: title, message: message, type: type };
        }

        if (typeof arg2 === 'string' && TYPES.indexOf(arg2) !== -1) {
            type = arg2;
            title = defaultToastTitle(type);
            message = arg1 || '';
            return { title: title, message: message, type: type };
        }

        title = defaultToastTitle('info');
        message = arg1 || '';
        return { title: title, message: message, type: 'info' };
    }

    function removeToast(toast) {
        if (!toast) {
            return;
        }
        toast.classList.remove('app-toast--show');
        toast.classList.add('app-toast--hide');
        setTimeout(function () {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 180);
    }

    window.showGameToast = function (arg1, arg2, arg3) {
        buildToastStack();

        var cfg = normalizeToastArgs(arg1, arg2, arg3);
        var toast = document.createElement('div');
        toast.className = 'app-toast app-toast--' + cfg.type;

        var icon = document.createElement('div');
        icon.className = 'app-toast__icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = ICONS[cfg.type] || ICONS.info;

        var content = document.createElement('div');
        content.className = 'app-toast__content';

        var title = document.createElement('div');
        title.className = 'app-toast__title';
        title.textContent = cfg.title;

        var message = document.createElement('div');
        message.className = 'app-toast__message';
        message.textContent = cfg.message;

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'app-toast__close';
        close.setAttribute('aria-label', LABELS.close);
        close.textContent = '�';

        content.appendChild(title);
        content.appendChild(message);
        toast.appendChild(icon);
        toast.appendChild(content);
        toast.appendChild(close);

        close.addEventListener('click', function (e) {
            e.stopPropagation();
            removeToast(toast);
        });
        toast.addEventListener('click', function () {
            removeToast(toast);
        });

        toastStack.appendChild(toast);
        requestAnimationFrame(function () {
            toast.classList.add('app-toast--show');
        });

        setTimeout(function () {
            removeToast(toast);
        }, 4500);
    };

    window.confirmAction = function (text, callback, opts) {
        buildModal();
        opts = opts || {};

        var type = opts.type || 'confirm';
        setType(type);
        titleEl.textContent = opts.title || LABELS.confirm;
        if (opts.bodyHtml) {
            bodyEl.innerHTML = opts.bodyHtml;
        } else {
            bodyEl.textContent = text;
        }

        actionsEl.innerHTML = '';
        actionsEl.appendChild(makeBtn(LABELS.cancel, 'modal-btn--cancel', closeModal));
        actionsEl.appendChild(makeBtn(
            opts.confirmLabel || LABELS.ok,
            type === 'danger' ? 'modal-btn--danger' : 'modal-btn--confirm',
            function () {
                closeModal();
                if (typeof callback === 'function') {
                    callback();
                }
            }
        ));

        openModal();
    };

    window.promptInput = function (text, defaultValue, callback, opts) {
        buildModal();
        opts = opts || {};
        setType(opts.type || 'input');
        titleEl.textContent = opts.title || LABELS.confirm;

        var inputId = 'modal-input-field';
        bodyEl.innerHTML =
            '<div class="modal-input-wrap">' +
                '<p class="modal-input-text"></p>' +
                '<input class="modal-input" id="' + inputId + '" type="text" />' +
            '</div>';
        bodyEl.querySelector('.modal-input-text').textContent = text;
        bodyEl.querySelector('#' + inputId).value = defaultValue || '';

        actionsEl.innerHTML = '';
        actionsEl.appendChild(makeBtn(LABELS.cancel, 'modal-btn--cancel', closeModal));
        actionsEl.appendChild(makeBtn(
            opts.confirmLabel || LABELS.ok,
            'modal-btn--confirm',
            function () {
                var value = bodyEl.querySelector('#' + inputId).value;
                closeModal();
                if (typeof callback === 'function') {
                    callback(value);
                }
            }
        ));

        openModal();
        setTimeout(function () {
            var input = bodyEl.querySelector('#' + inputId);
            if (input) {
                input.focus();
                input.select();
            }
        }, 30);
    };

    window.alertInfo = function (text, title, callback) {
        buildModal();
        setType('info');
        titleEl.textContent = title || LABELS.title_info;
        bodyEl.textContent = text;
        actionsEl.innerHTML = '';
        actionsEl.appendChild(makeBtn(LABELS.ok, 'modal-btn--confirm', function () {
            closeModal();
            if (typeof callback === 'function') callback();
        }));
        openModal();
    };

    window.alertError = function (text, title, callback) {
        buildModal();
        setType('danger');
        titleEl.textContent = title || LABELS.title_error;
        bodyEl.textContent = text;
        actionsEl.innerHTML = '';
        actionsEl.appendChild(makeBtn(LABELS.ok, 'modal-btn--danger', function () {
            closeModal();
            if (typeof callback === 'function') callback();
        }));
        openModal();
    };

    window.alertWarning = function (text, title, callback) {
        buildModal();
        setType('warning');
        titleEl.textContent = title || LABELS.title_warn;
        bodyEl.textContent = text;
        actionsEl.innerHTML = '';
        actionsEl.appendChild(makeBtn(LABELS.ok, 'modal-btn--confirm', function () {
            closeModal();
            if (typeof callback === 'function') callback();
        }));
        openModal();
    };

    document.addEventListener('submit', function (e) {
        var form = e.target.closest('form[data-confirm]');
        if (!form || form.dataset.confirmBound === '1') {
            return;
        }
        e.preventDefault();
        confirmAction(form.dataset.confirm, function () {
            form.dataset.confirmBound = '1';
            form.requestSubmit ? form.requestSubmit() : form.submit();
            setTimeout(function () { form.dataset.confirmBound = '0'; }, 0);
        }, {
            title: form.dataset.confirmTitle || LABELS.confirm,
            type: form.dataset.confirmType || 'confirm',
            confirmLabel: form.dataset.confirmLabel || LABELS.confirm
        });
    }, true);

    document.addEventListener('click', function (e) {
        var link = e.target.closest('a[data-confirm]');
        if (!link) {
            return;
        }
        e.preventDefault();
        confirmAction(link.dataset.confirm, function () {
            window.location.href = link.href;
        }, {
            title: link.dataset.confirmTitle || LABELS.confirm,
            type: link.dataset.confirmType || 'confirm',
            confirmLabel: link.dataset.confirmLabel || LABELS.confirm
        });
    });
})();
