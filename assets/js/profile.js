/**
 * profile.js - interakcje profilu gracza.
 * profile.js - player profile interactions.
 * Obsluguje: podglad nazwy firmy, pokazywanie/ukrywanie hasel,
 * wskaznik sily hasla, zgodnosc hasel, podglad i wysylke avatara
 * (AJAX do istniejacej akcji serwerowej z X-CSRF-Token).
 * Handles: company name preview, password show/hide, password strength
 * indicator, password match check, avatar preview and upload
 * (AJAX to the existing server action with X-CSRF-Token).
 * Wszystkie teksty UI pochodza z #profileConfig (data attributes) -
 * zadnych twardych napisow. All UI texts come from #profileConfig
 * (data attributes) - no hardcoded strings.
 */
(function () {
    'use strict';

    var config = document.getElementById('profileConfig');
    if (!config) return;
    var cfg = config.dataset;

    var MAX_AVATAR_BYTES = 2 * 1024 * 1024;
    var ALLOWED_AVATAR_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /* ------------------------------------------------ powiadomienia */

    function showResult(kind, text) {
        if (kind === 'error' && typeof window.alertError === 'function') {
            window.alertError(text);
        } else if (kind === 'success' && typeof window.alertInfo === 'function') {
            window.alertInfo(text);
        } else if (typeof window.showGameToast === 'function') {
            window.showGameToast(text, kind === 'error' ? 'error' : 'success');
        } else {
            var shell = document.querySelector('.profile-shell');
            if (!shell) return;
            var bar = document.createElement('div');
            bar.className = 'profile-flash profile-flash--' + (kind === 'error' ? 'error' : 'success');
            bar.textContent = text;
            shell.insertBefore(bar, shell.firstChild);
        }
    }

    /* Flash z serwera: pokaz toastem i usun z DOM (jednorazowosc).
       Server flash: show as toast and remove from DOM (one-shot). */
    function consumeServerFlash() {
        var bars = document.querySelectorAll('.profile-flash');
        var hasToast = typeof window.alertError === 'function'
            || typeof window.alertInfo === 'function'
            || typeof window.showGameToast === 'function';
        if (!hasToast) return;
        bars.forEach(function (bar) {
            var text = bar.textContent.trim();
            var kind = bar.classList.contains('profile-flash--error') ? 'error' : 'success';
            bar.remove();
            if (text) {
                if (kind === 'error' && typeof window.alertError === 'function') {
                    window.alertError(text);
                } else if (kind === 'success' && typeof window.alertInfo === 'function') {
                    window.alertInfo(text);
                } else if (typeof window.showGameToast === 'function') {
                    window.showGameToast(text, kind);
                }
            }
        });
    }

    /* --------------------------------------------- nazwa firmy */

    var companyName = document.getElementById('company-name');
    var companyPreview = document.getElementById('company-name-preview');
    if (companyName && companyPreview) {
        companyName.addEventListener('input', function () {
            var value = companyName.value.trim();
            companyPreview.textContent = value ? (cfg.namePrefix + ' ' + value) : cfg.nameEmpty;
            companyPreview.classList.toggle('is-preview', Boolean(value));
        });
    }

    /* --------------------------------------- widocznosc hasel */

    document.querySelectorAll('[data-password-toggle]').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            var input = document.getElementById(toggle.dataset.passwordToggle);
            if (!input) return;
            var showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            var icon = toggle.querySelector('img');
            if (icon) icon.src = showing ? toggle.dataset.iconShow : toggle.dataset.iconHide;
            var action = showing ? cfg.toggleShow : cfg.toggleHide;
            var label = (toggle.dataset.fieldLabel || '').toLowerCase();
            toggle.setAttribute('aria-label', action + (label ? ' ' + label : ''));
        });
    });

    /* --------------------------------- sila i zgodnosc hasla */

    var newPassword = document.getElementById('new-password');
    var strength = document.getElementById('pass-strength');
    if (newPassword && strength) {
        var strengthLabels = ['', cfg.passVeryWeak, cfg.passWeak, cfg.passMedium, cfg.passStrong, cfg.passVeryStrong];
        var strengthColors = ['', '#e05555', '#e07b55', '#e0b44c', '#7ec97a', '#4ec97a'];
        newPassword.addEventListener('input', function () {
            var val = newPassword.value;
            if (!val) {
                strength.textContent = '';
                return;
            }
            var score = 0;
            if (val.length >= 8) score++;
            if (val.length >= 12) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;
            strength.textContent = strengthLabels[score] || '';
            strength.style.color = strengthColors[score] || '';
        });
    }

    var confirmPassword = document.getElementById('confirm-password');
    var passMatch = document.getElementById('pass-match');
    if (confirmPassword && passMatch && newPassword) {
        confirmPassword.addEventListener('input', function () {
            if (!confirmPassword.value) {
                passMatch.textContent = '';
                return;
            }
            if (confirmPassword.value === newPassword.value) {
                passMatch.textContent = cfg.passMatch;
                passMatch.style.color = '#4ec97a';
            } else {
                passMatch.textContent = cfg.passNoMatch;
                passMatch.style.color = '#e05555';
            }
        });
    }

    /* ----------------------------------------------- avatar */

    var photoButton = document.getElementById('photo-button');
    var photoInput = document.getElementById('photo-input');
    var photoFeedback = document.getElementById('photo-feedback');
    var avatarPreview = document.getElementById('avatar-preview');
    var currentPreviewUrl = null;

    function setPhotoFeedback(text, isError) {
        if (!photoFeedback) return;
        photoFeedback.textContent = text || '';
        photoFeedback.classList.toggle('error', Boolean(isError));
    }

    function setAvatarPreview(url) {
        if (!avatarPreview) return;
        if (avatarPreview.tagName === 'IMG') {
            avatarPreview.src = url;
            return;
        }
        var img = document.createElement('img');
        img.src = url;
        img.className = 'profile-avatar-img';
        img.id = 'avatar-preview';
        img.alt = '';
        avatarPreview.replaceWith(img);
        avatarPreview = img;
    }

    function updateTopbarAvatar(url) {
        var link = document.querySelector('.topbar-user-link');
        if (!link) return;
        var img = link.querySelector('.topbar-avatar');
        if (img) {
            img.src = url;
            return;
        }
        var initials = link.querySelector('.topbar-avatar-initials');
        if (!initials) return;
        img = document.createElement('img');
        img.src = url;
        img.className = 'topbar-avatar';
        img.alt = 'avatar';
        initials.replaceWith(img);
    }

    function compressAvatarBlob(file) {
        return new Promise(function (resolve, reject) {
            var reader = new FileReader();
            reader.onerror = function () { reject(new Error('read_failed')); };
            reader.onload = function (e) {
                var original = new Image();
                original.onerror = function () { reject(new Error('decode_failed')); };
                original.onload = function () {
                    var maxDim = 128;
                    var scale = Math.min(1, maxDim / Math.max(original.width, original.height));
                    var width = Math.max(1, Math.round(original.width * scale));
                    var height = Math.max(1, Math.round(original.height * scale));
                    var canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    canvas.getContext('2d').drawImage(original, 0, 0, width, height);
                    var qualities = [0.82, 0.72, 0.64, 0.56, 0.48, 0.4];
                    function attempt(index) {
                        canvas.toBlob(function (blob) {
                            if (!blob) { reject(new Error('blob_failed')); return; }
                            if (blob.size <= 6000 || index >= qualities.length - 1) {
                                resolve({ blob: blob, previewUrl: canvas.toDataURL('image/jpeg', 0.72) });
                                return;
                            }
                            attempt(index + 1);
                        }, 'image/jpeg', qualities[index]);
                    }
                    attempt(0);
                };
                original.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    function uploadAvatar(blob) {
        return fetch(window.location.pathname + window.location.search, {
            method: 'POST',
            body: blob,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'image/jpeg',
                'X-CSRF-Token': cfg.csrf || '',
                'X-Upload-Action': 'upload_avatar',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
    }

    function onPhotoSelected(input) {
        var file = input.files && input.files[0];
        if (!file) return;
        if (ALLOWED_AVATAR_TYPES.indexOf(file.type) === -1 || file.size > MAX_AVATAR_BYTES) {
            setPhotoFeedback(cfg.avatarInvalid, true);
            input.value = '';
            return;
        }
        if (currentPreviewUrl) URL.revokeObjectURL(currentPreviewUrl);
        currentPreviewUrl = URL.createObjectURL(file);
        setAvatarPreview(currentPreviewUrl);
        setPhotoFeedback(cfg.avatarPreview, false);

        compressAvatarBlob(file)
            .then(function (result) {
                return uploadAvatar(result.blob).then(function (response) {
                    return response.json().catch(function () {
                        throw new Error(cfg.avatarFailed);
                    }).then(function (data) {
                        if (!data || !data.ok) {
                            throw new Error((data && data.message) || cfg.avatarFailed);
                        }
                        if (data.avatar_url) {
                            setAvatarPreview(data.avatar_url);
                            updateTopbarAvatar(data.avatar_url);
                        }
                        showResult('success', data.message || cfg.avatarUploading);
                    });
                });
            })
            .catch(function (err) {
                showResult('error', (err && err.message) || cfg.avatarFailed);
            })
            .then(function () {
                input.value = '';
            });
    }

    if (photoButton && photoInput) {
        photoButton.addEventListener('click', function () { photoInput.click(); });
    }
    if (photoInput) {
        photoInput.addEventListener('change', function () { onPhotoSelected(photoInput); });
    }
    window.addEventListener('pagehide', function () {
        if (currentPreviewUrl) URL.revokeObjectURL(currentPreviewUrl);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', consumeServerFlash);
    } else {
        consumeServerFlash();
    }
})();
