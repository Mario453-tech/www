(function () {
    'use strict';

    function operationToken(strikeId) {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return `strike-${strikeId}-${window.crypto.randomUUID()}`;
        }
        return `strike-${strikeId}-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    }

    function setBusy(element, busy) {
        element.disabled = busy;
        element.setAttribute('aria-busy', busy ? 'true' : 'false');
    }

    async function openNegotiation(button) {
        const strikeId = Number(button.dataset.openStrikeNegotiation || 0);
        if (!strikeId) {
            return;
        }
        setBusy(button, true);
        try {
            const result = await hrApi('open_strike_negotiation', { strike_id: strikeId });
            showToast(hrl('strike_negotiation_title'), result.message);
            window.setTimeout(() => window.location.reload(), 900);
        } catch (error) {
            showToast(hrl('toast_err'), error.message, 'error');
            setBusy(button, false);
        }
    }

    function submitOffer(form) {
        if (!form.reportValidity()) {
            return;
        }
        const strikeId = Number(form.dataset.strikeOfferForm || 0);
        const raisePct = Number(form.elements.raise_pct.value);
        const bonusPerMember = Number(form.elements.bonus_per_member.value);
        if (!strikeId || !Number.isFinite(raisePct) || !Number.isFinite(bonusPerMember)
            || (raisePct <= 0 && bonusPerMember <= 0)) {
            showToast(hrl('toast_err'), hrl('strike_offer_invalid'), 'error');
            return;
        }

        confirmAction(
            hrl('confirm_strike_offer', {
                raise: raisePct.toLocaleString(undefined, { maximumFractionDigits: 2 }),
                bonus: bonusPerMember.toLocaleString(),
            }),
            async function () {
                const button = form.querySelector('button[type="submit"]');
                const token = form.dataset.idempotencyToken || operationToken(strikeId);
                form.dataset.idempotencyToken = token;
                if (button) {
                    setBusy(button, true);
                }
                try {
                    const result = await hrApi('submit_strike_offer', {
                        strike_id: strikeId,
                        raise_pct: raisePct,
                        bonus_per_member: bonusPerMember,
                        idempotency_token: token,
                    });
                    delete form.dataset.idempotencyToken;
                    const dialogue = result.dialogue && (result.dialogue[window.HR_LOCALE] || result.dialogue.pl || result.dialogue.en);
                    const dialogueElement = document.querySelector(`[data-strike-dialogue="${strikeId}"]`);
                    if (dialogueElement && dialogue) {
                        dialogueElement.textContent = dialogue;
                        dialogueElement.hidden = false;
                    }
                    showToast(hrl('strike_negotiation_title'), result.message, result.result === 'accepted' ? 'success' : 'warning');
                    window.setTimeout(() => window.location.reload(), 5200);
                } catch (error) {
                    showToast(hrl('toast_err'), error.message, 'error');
                    if (button) {
                        setBusy(button, false);
                    }
                }
            },
            { type: 'confirm', confirmLabel: hrl('confirm_strike_offer_btn') }
        );
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-open-strike-negotiation]').forEach((button) => {
            button.addEventListener('click', function () {
                openNegotiation(button);
            });
        });
        document.querySelectorAll('[data-strike-offer-form]').forEach((form) => {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                submitOffer(form);
            });
        });
    });
})();
