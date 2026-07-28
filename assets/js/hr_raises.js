(function () {
    'use strict';

    const actionMap = {
        accept: 'accept_raise_request',
        reject: 'reject_raise_request',
        postpone: 'postpone_raise_request',
    };

    function operationToken(requestId, action) {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return `raise-${requestId}-${action}-${window.crypto.randomUUID()}`;
        }
        return `raise-${requestId}-${action}-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    }

    function setBusy(element, busy) {
        element.disabled = busy;
        element.setAttribute('aria-busy', busy ? 'true' : 'false');
    }

    function getToken(element, requestId, action) {
        if (element.dataset.tokenAction !== action || !element.dataset.idempotencyToken) {
            element.dataset.tokenAction = action;
            element.dataset.idempotencyToken = operationToken(requestId, action);
        }
        return element.dataset.idempotencyToken;
    }

    function clearToken(element) {
        delete element.dataset.tokenAction;
        delete element.dataset.idempotencyToken;
    }

    function complete(result) {
        showToast(hrl('raise_title'), result.message || hrl('raise_title'), 'success');
        window.setTimeout(() => window.location.reload(), 5200);
    }

    function handleDecision(button) {
        const requestId = Number(button.dataset.requestId || 0);
        const action = button.dataset.raiseAction || '';
        const apiAction = actionMap[action];
        if (!requestId || !apiAction) {
            return;
        }

        const employeeName = button.dataset.employeeName || '';
        const confirmKey = `confirm_raise_${action}`;
        const confirmButtonKey = `${confirmKey}_btn`;

        confirmAction(
            hrl(confirmKey, { name: employeeName }),
            async function () {
                setBusy(button, true);
                try {
                    const result = await hrApi(apiAction, {
                        request_id: requestId,
                        idempotency_token: getToken(button, requestId, action),
                    });
                    clearToken(button);
                    complete(result);
                } catch (error) {
                    showToast(hrl('toast_err'), error.message, 'error');
                    setBusy(button, false);
                }
            },
            {
                type: action === 'reject' ? 'danger' : 'confirm',
                confirmLabel: hrl(confirmButtonKey),
            }
        );
    }

    function handleOffer(form) {
        if (!form.reportValidity()) {
            return;
        }

        const requestId = Number(form.dataset.raiseOfferForm || 0);
        const salary = Number(form.elements.offered_salary.value);
        if (!requestId || !Number.isFinite(salary) || salary <= 0) {
            showToast(hrl('toast_err'), hrl('raise_offer_invalid'), 'error');
            return;
        }

        const employeeName = form.dataset.employeeName || '';
        confirmAction(
            hrl('confirm_raise_negotiate', {
                name: employeeName,
                salary: salary.toLocaleString(window.HR_LOCALE || undefined),
            }),
            async function () {
                const button = form.querySelector('button[type="submit"]');
                if (button) {
                    setBusy(button, true);
                }
                try {
                    const result = await hrApi('negotiate_raise_request', {
                        request_id: requestId,
                        offered_salary: salary,
                        idempotency_token: getToken(form, requestId, 'negotiate'),
                    });
                    clearToken(form);
                    complete(result);
                } catch (error) {
                    showToast(hrl('toast_err'), error.message, 'error');
                    if (button) {
                        setBusy(button, false);
                    }
                }
            },
            {
                type: 'confirm',
                confirmLabel: hrl('confirm_raise_negotiate_btn'),
            }
        );
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-raise-action]').forEach((button) => {
            button.addEventListener('click', function () {
                handleDecision(button);
            });
        });

        document.querySelectorAll('[data-raise-offer-form]').forEach((form) => {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                handleOffer(form);
            });
        });
    });
})();
