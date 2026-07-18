/**
 * Hub purchase and rental actions.
 * Akcje zakupu i wynajmu hubow.
 */
(function () {
    'use strict';

    const Hub = window.HubUI;
    if (!Hub) return;
    const labels = () => Hub.lang();

    async function marketPost(action, body, successKey) {
        try {
            const result = await Hub.hubPost(action, body);
            if (!result.success) {
                if (result.error_code === 'no_hub_permit') {
                    Hub.hubPermitModal(result.error || labels().err_generic, result);
                } else {
                    await Hub.hubDialog(result.error || labels().err_generic, 'error');
                }
                return;
            }
            await Hub.hubDialog(result.message || labels()[successKey], 'success');
            Hub.reloadAfterAction();
        } catch (requestError) {
            await Hub.hubDialog(labels().err_generic, 'error');
        }
    }

    window.hubBuyUsed = async function (hubId) {
        const button = document.querySelector(`article[data-hub-id="${hubId}"] .logistics-hub-buy-btn`);
        const name = button ? button.dataset.hubName || '' : '';
        const price = Number(button ? button.dataset.buyPrice || 0 : 0);
        const message = (labels().market_confirm_buy || '')
            .replace('{name}', name)
            .replace('{price}', Hub.fmtPln(price));
        if (!await Hub.hubConfirm(message, { title: labels().market_confirm_buy_title || '' })) return;
        await marketPost('buy_used_hub', { hub_id: hubId }, 'market_ok_buy');
    };

    window.hubRent = async function (hubId) {
        const button = document.querySelector(`article[data-hub-id="${hubId}"] .logistics-hub-rent-btn`);
        const name = button ? button.dataset.hubName || '' : '';
        const deposit = Number(button ? button.dataset.rentDeposit || 0 : 0);
        const lease = Number(button ? button.dataset.leaseFee || 0 : 0);
        const message = (labels().market_confirm_rent || '')
            .replace('{name}', name)
            .replace('{deposit}', Hub.fmtPln(deposit))
            .replace('{lease}', Hub.fmtPln(lease));
        if (!await Hub.hubConfirm(message, { title: labels().market_confirm_rent_title || '' })) return;
        await marketPost('rent_hub', { hub_id: hubId }, 'market_ok_rent');
    };

    window.hubBuyNewModal = function () {
        Hub.openHubModal('hub-buy-new-modal');
    };

    window.hubBuyNewTypeChange = function (radio) {
        document.querySelectorAll('#hub-buy-new-modal .logistics-mode-card')
            .forEach((card) => card.classList.remove('selected'));
        const card = radio.closest('.logistics-mode-card');
        if (card) card.classList.add('selected');
    };

    window.hubBuyNewSubmit = async function (event) {
        if (event) event.preventDefault();
        const form = document.getElementById('hub-buy-new-form');
        const button = document.getElementById('hub-buy-new-submit');
        if (!form) return;
        const data = Object.fromEntries(new FormData(form));
        const name = String(data.name || '').trim();
        if (name === '') {
            await Hub.hubDialog(labels().market_name_required, 'warning');
            return;
        }
        const typeRadio = form.querySelector('input[name="hub_type"]:checked');
        const price = Number(typeRadio ? typeRadio.dataset.buildCost || 0 : 0);
        const message = (labels().market_confirm_buy_new || '')
            .replace('{name}', name)
            .replace('{price}', Hub.fmtPln(price));
        if (!await Hub.hubConfirm(message, { title: labels().market_confirm_buy_title || '' })) return;
        if (button) button.disabled = true;
        try {
            const result = await Hub.hubPost('buy_new_hub', data);
            if (!result.success) {
                if (result.error_code === 'no_hub_permit') Hub.hubPermitModal(result.error, result);
                else await Hub.hubDialog(result.error || labels().err_generic, 'error');
                return;
            }
            Hub.closeHubModal('hub-buy-new-modal');
            await Hub.hubDialog(result.message || labels().market_ok_buy_new, 'success');
            Hub.reloadAfterAction();
        } catch (requestError) {
            await Hub.hubDialog(labels().err_generic, 'error');
        } finally {
            if (button) button.disabled = false;
        }
    };
})();
