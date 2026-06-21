/**
 * privacy_banner.js — OilCorp cookie consent manager
 * Obsługuje: baner, modal ustawień, zapis zgody przez API, cookie w przeglądarce.
 */
(function () {
    'use strict';

    var COOKIE_NAME    = 'privacy_consent';
    var COOKIE_DAYS    = 365;
    var API_URL        = '/api/privacy/consent.php';

    // Konfiguracja przekazana z PHP przez window.PRIVACY_CONFIG
    var cfg = window.PRIVACY_CONFIG || {};

    // ---- Helpers ----

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(?:^|;\\s*)' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }

    function setCookie(name, value, days) {
        var expires = '';
        if (days) {
            var d = new Date();
            d.setTime(d.getTime() + days * 864e5);
            expires = '; expires=' + d.toUTCString();
        }
        document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
    }

    function getStoredConsent() {
        try {
            var raw = getCookie(COOKIE_NAME);
            return raw ? JSON.parse(raw) : null;
        } catch (e) {
            return null;
        }
    }

    function needsBanner() {
        if (!cfg.bannerEnabled) return false;
        var consent = getStoredConsent();
        if (!consent) return true;
        if (cfg.forceReconsent) return true;
        if (consent.banner_version  !== cfg.bannerVersion)  return true;
        if (cfg.reconsentOnPolicy && consent.consent_version !== cfg.policyVersion) return true;
        return false;
    }

    // ---- Banner ----

    function showBanner() {
        var banner = document.getElementById('privacy-banner');
        if (banner) {
            banner.classList.add('privacy-banner--visible');
            banner.removeAttribute('hidden');
        }
    }

    function hideBanner() {
        var banner = document.getElementById('privacy-banner');
        if (banner) banner.classList.remove('privacy-banner--visible');
    }

    // ---- Modal ----

    var _modalOpener = null;

    function openModal() {
        var overlay = document.getElementById('privacy-modal-overlay');
        if (overlay) {
            _modalOpener = document.activeElement || null;
            overlay.classList.add('privacy-modal--open');
            overlay.removeAttribute('hidden');
            overlay.setAttribute('aria-hidden', 'false');
            var closeBtn = overlay.querySelector('.privacy-modal__close');
            if (closeBtn) closeBtn.focus();
        }
    }

    function closeModal() {
        var overlay = document.getElementById('privacy-modal-overlay');
        if (overlay) {
            overlay.classList.remove('privacy-modal--open');
            overlay.setAttribute('aria-hidden', 'true');
            overlay.setAttribute('hidden', '');
        }
        if (_modalOpener && typeof _modalOpener.focus === 'function') {
            _modalOpener.focus();
            _modalOpener = null;
        }
    }

    // ---- Zapis zgody ----

    function saveConsent(acceptedCategories, source) {
        source = source || 'banner';

        // Zapis lokalny w cookie (natychmiastowy — nie czeka na API)
        var consentData = {
            consent_version: cfg.policyVersion  || '1.0',
            banner_version:  cfg.bannerVersion   || '1.0',
            accepted:        acceptedCategories,
            ts:              Math.floor(Date.now() / 1000)
        };
        setCookie(COOKIE_NAME, JSON.stringify(consentData), COOKIE_DAYS);

        hideBanner();
        closeModal();

        // Zapis w bazie przez API (w tle, nie blokuje UI)
        if (!cfg.csrfToken) return;
        try {
            fetch(API_URL, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({
                    csrf_token:           cfg.csrfToken,
                    accepted_categories:  acceptedCategories,
                    source:               source
                })
            }).catch(function () { /* silent fail — consent already in cookie */ });
        } catch (e) { /* fetch not available */ }
    }

    function acceptAll() {
        saveConsent(cfg.allCategories || ['necessary', 'preferences', 'analytics', 'marketing'], 'banner');
    }

    function acceptNecessaryOnly() {
        saveConsent(['necessary'], 'banner');
    }

    function saveFromModal() {
        var accepted = ['necessary']; // necessary zawsze
        var checkboxes = document.querySelectorAll('.privacy-category-toggle:not(:disabled)');
        checkboxes.forEach(function (cb) {
            if (cb.checked && cb.value !== 'necessary') {
                accepted.push(cb.value);
            }
        });
        saveConsent(accepted, 'settings');
    }

    // ---- Pre-fill modal z aktualną zgodą ----

    function prefillModal() {
        var consent  = getStoredConsent();
        var accepted = consent ? (consent.accepted || []) : [];
        var checkboxes = document.querySelectorAll('.privacy-category-toggle:not(:disabled)');
        checkboxes.forEach(function (cb) {
            if (cb.value === 'necessary') return;
            cb.checked = accepted.indexOf(cb.value) !== -1;
        });
    }

    // ---- Event listeners ----

    function init() {
        // Baner
        var btnAcceptAll = document.getElementById('privacy-btn-accept-all');
        var btnDecline   = document.getElementById('privacy-btn-decline');
        var btnSettings  = document.getElementById('privacy-btn-settings');

        if (btnAcceptAll) btnAcceptAll.addEventListener('click', acceptAll);
        if (btnDecline)   btnDecline.addEventListener('click', acceptNecessaryOnly);
        if (btnSettings)  btnSettings.addEventListener('click', function () {
            prefillModal();
            openModal();
        });

        // Modal
        var btnModalSave    = document.getElementById('privacy-modal-save');
        var btnModalAccept  = document.getElementById('privacy-modal-accept-all');
        var btnModalClose   = document.querySelector('.privacy-modal__close');
        var overlay         = document.getElementById('privacy-modal-overlay');

        if (btnModalSave)   btnModalSave.addEventListener('click', saveFromModal);
        if (btnModalAccept) btnModalAccept.addEventListener('click', acceptAll);
        if (btnModalClose)  btnModalClose.addEventListener('click', closeModal);

        // Zamknięcie przez klik poza modalem
        if (overlay) {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) closeModal();
            });
        }

        // Zamknięcie ESC
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });

        // Link "Ustawienia prywatności" w stopce / na stronie
        document.querySelectorAll('[data-privacy-settings]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                prefillModal();
                openModal();
            });
        });

        // Pokaż baner jeśli potrzebny
        if (needsBanner()) {
            showBanner();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
