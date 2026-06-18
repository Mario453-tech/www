/**
 * protection.js - modal wykupu ochrony (transport drogowy).
 * protection.js - protection purchase modal (road transport).
 *
 * Konfiguracja z PHP: window.PROTECTION_API, window.PROTECTION_CSRF,
 * window.PROTECTION_LANG (confirm_question, err).
 */
(function () {
    'use strict';

    var modal = document.getElementById('protection-modal');
    if (!modal) return;

    var LANG = window.PROTECTION_LANG || {};
    var selectedWellId = 0;

    function openModal(wellId) {
        selectedWellId = wellId;
        modal.hidden = false;
    }

    function closeModal() {
        selectedWellId = 0;
        modal.hidden = true;
    }

    document.querySelectorAll('.protection-add-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal(parseInt(btn.dataset.wellId, 10) || 0);
        });
    });

    modal.querySelectorAll('[data-protection-close]').forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) closeModal();
    });

    function buyProtection(optionCode) {
        var body = new URLSearchParams();
        body.set('csrf_token', window.PROTECTION_CSRF || '');
        body.set('option_code', optionCode);
        body.set('well_id', String(selectedWellId));

        fetch(window.PROTECTION_API || '/protection.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
            .then(function (resp) { return resp.json(); })
            .then(function (data) {
                if (typeof window.showGameToast === 'function') {
                    window.showGameToast(data.message || (data.success ? 'OK' : LANG.err || 'Error'),
                        data.success ? 'success' : 'error');
                }
                if (data.success) {
                    closeModal();
                    setTimeout(function () {
                        window.location.replace(window.location.pathname + window.location.search);
                    }, 1200);
                }
            })
            .catch(function () {
                if (typeof window.showGameToast === 'function') {
                    window.showGameToast(LANG.err || 'Error', 'error');
                }
            });
    }

    modal.querySelectorAll('.protection-buy-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!selectedWellId) return;
            var question = (LANG.confirm_question || ':name / :cost')
                .replace(':name', btn.dataset.optionName || '')
                .replace(':cost', btn.dataset.optionCost || '');
            if (typeof window.confirmAction === 'function') {
                window.confirmAction(question, function () {
                    buyProtection(btn.dataset.optionCode || '');
                });
            } else {
                buyProtection(btn.dataset.optionCode || '');
            }
        });
    });
})();
