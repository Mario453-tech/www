if (typeof HR_API === 'undefined' || typeof CSRF_TOKEN === 'undefined') {
    console.error('[HR] Missing HR_API or CSRF_TOKEN');
}

function switchTab(name) {
    document.querySelectorAll('.hr-tab-content').forEach((el) => el.classList.remove('active'));
    document.querySelectorAll('.hr-tab').forEach((el) => el.classList.remove('active'));
    document.getElementById('tab-' + name)?.classList.add('active');
    document.querySelector(`.hr-tab[onclick="switchTab('${name}')"]`)?.classList.add('active');
}

const _HL = window.HR_LANG || {};
function hrl(key, params) {
    let text = _HL[key] || key;
    if (params) {
        Object.keys(params).forEach((paramKey) => {
            text = text.replace(':' + paramKey, params[paramKey]);
        });
    }
    return text;
}

async function hrApi(action, data = {}) {
    if (typeof HR_API === 'undefined') {
        throw new Error('HR_API undefined');
    }
    if (typeof CSRF_TOKEN === 'undefined') {
        throw new Error('CSRF token undefined');
    }

    const formData = new FormData();
    formData.append('action', action);
    formData.append('_token', CSRF_TOKEN);
    Object.entries(data).forEach(([key, value]) => formData.append(key, value));

    const response = await fetch(HR_API, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
        throw new Error('Response is not JSON');
    }

    return response.json();
}

function removeCandidateCard(candidateId) {
    const card = document.querySelector(`[onclick*="Candidate(${candidateId},"]`)?.closest('.candidate-card-hr');
    if (!card) {
        return;
    }

    card.style.opacity = '0';
    card.style.transform = 'scale(0.96)';
    card.style.transition = 'all 0.25s ease';

    setTimeout(() => {
        card.remove();
        const badge = document.querySelector(`.hr-tab[onclick="switchTab('candidates')"] .tab-badge`);
        if (badge) {
            const nextValue = Math.max(0, parseInt(badge.textContent || '0', 10) - 1);
            if (nextValue > 0) {
                badge.textContent = String(nextValue);
            } else {
                badge.remove();
            }
        }

        if (!document.querySelector('#tab-candidates .candidate-card-hr')) {
            const container = document.querySelector('#tab-candidates .candidates-grid');
            if (container) {
                container.outerHTML = `<div class="hr-empty hr-empty--big"><div class="hr-empty-icon">&#128196;</div><p>${hrl('no_candidates')}</p></div>`;
            }
        }
    }, 260);
}

function hireCandidate(candidateId, name) {
    const contractType = document.getElementById(`contract-${candidateId}`)?.value || '1y';
    const labels = {
        '1y': hrl('contract_1y'),
        '6m': hrl('contract_6m'),
        '2y': hrl('contract_2y'),
    };

    confirmAction(
        hrl('confirm_hire', { name, contract: labels[contractType] || contractType }),
        async function () {
            try {
                const result = await hrApi('hire_candidate', { candidate_id: candidateId, contract_type: contractType });
                if (result.success) {
                    showToast(hrl('toast_hired'), result.message);
                    removeCandidateCard(candidateId);
                } else {
                    showToast(hrl('toast_err'), result.message || result.error, 'error');
                }
            } catch (error) {
                showToast(hrl('toast_err'), error.message, 'error');
            }
        },
        { type: 'confirm', confirmLabel: hrl('confirm_hire_btn') || hrl('btn_hire') }
    );
}

function rejectCandidate(candidateId, name) {
    confirmAction(
        hrl('confirm_reject', { name }),
        async function () {
            try {
                const result = await hrApi('reject_candidate', { candidate_id: candidateId });
                if (result.success) {
                    showToast(hrl('toast_rejected'), result.message);
                    removeCandidateCard(candidateId);
                } else {
                    showToast(hrl('toast_err'), result.message || result.error, 'error');
                }
            } catch (error) {
                showToast(hrl('toast_err'), error.message, 'error');
            }
        },
        { type: 'danger', confirmLabel: hrl('confirm_reject_btn') || hrl('btn_reject') }
    );
}

function fireEmployee(memberId, name) {
    confirmAction(
        hrl('confirm_fire', { name }),
        function () {
            promptInput(
                hrl('prompt_fire_reason'),
                hrl('prompt_fire_default'),
                async function (reason) {
                    try {
                        const result = await hrApi('fire_employee', { member_id: memberId, reason });
                        if (result.success) {
                            showToast(hrl('toast_fired'), result.message);
                            setTimeout(() => location.reload(), 1200);
                        } else {
                            showToast(hrl('toast_err'), result.message || result.error, 'error');
                        }
                    } catch (error) {
                        showToast(hrl('toast_err'), error.message, 'error');
                    }
                },
                { title: hrl('prompt_fire_reason'), confirmLabel: hrl('confirm_fire_btn') || hrl('toast_fired') }
            );
        },
        { type: 'danger', confirmLabel: hrl('confirm_fire_btn') || hrl('toast_fired') }
    );
}

function fireTechnicalStaff(staffId, name) {
    confirmAction(
        hrl('confirm_fire', { name }),
        function () {
            promptInput(
                hrl('prompt_fire_reason'),
                hrl('prompt_fire_default'),
                async function (reason) {
                    try {
                        const result = await hrApi('fire_technical_staff', { staff_id: staffId, reason });
                        if (result.success) {
                            showToast(hrl('toast_fired'), result.message);
                            setTimeout(() => location.reload(), 1200);
                        } else {
                            showToast(hrl('toast_err'), result.message || result.error, 'error');
                        }
                    } catch (error) {
                        showToast(hrl('toast_err'), error.message, 'error');
                    }
                },
                { title: hrl('prompt_fire_reason'), confirmLabel: hrl('confirm_fire_btn') || hrl('toast_fired') }
            );
        },
        { type: 'danger', confirmLabel: hrl('confirm_fire_btn') || hrl('toast_fired') }
    );
}

function renewContract(memberId, name) {
    const contractType = document.getElementById(`renew-${memberId}`)?.value || '1y';
    confirmAction(
        hrl('confirm_renew', { name }),
        async function () {
            try {
                const result = await hrApi('renew_contract', { member_id: memberId, contract_type: contractType });
                if (result.success) {
                    showToast(hrl('toast_renewed'), result.message);
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showToast(hrl('toast_err'), result.message || result.error, 'error');
                }
            } catch (error) {
                showToast(hrl('toast_err'), error.message, 'error');
            }
        },
        { type: 'confirm', confirmLabel: hrl('confirm_renew_btn') || hrl('toast_renewed') }
    );
}

function toggleEmployeeDetails(id) {
    const element = document.getElementById(`emp-details-${id}`);
    if (!element) {
        return;
    }
    const isOpen = element.style.display !== 'none';
    element.style.display = isOpen ? 'none' : 'block';
}

async function startHeadhunter() {
    const specId = document.getElementById('hh-spec')?.value;
    if (!specId) {
        showToast(hrl('toast_err'), hrl('err_no_spec'), 'error');
        return;
    }

    const button = document.querySelector('#tab-headhunter .btn-hr-primary');
    const originalText = button?.textContent || '';
    if (button) {
        button.disabled = true;
        button.textContent = hrl('headhunter_starting');
    }

    try {
        const result = await hrApi('start_headhunter', { specialization_id: specId });
        if (result.success) {
            showToast(hrl('toast_headhunter'), result.message);
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast(hrl('toast_err'), result.message || result.error, 'error');
            if (button) {
                button.disabled = false;
                button.textContent = originalText || hrl('headhunter_btn');
            }
        }
    } catch (error) {
        showToast(hrl('toast_err'), error.message, 'error');
        if (button) {
            button.disabled = false;
            button.textContent = originalText || hrl('headhunter_btn');
        }
    }
}

async function makeHeadhunterOffer(event, candidateId) {
    event.preventDefault();

    const salary = parseFloat(document.getElementById(`hh-salary-${candidateId}`)?.value || 0);
    const bonus = parseFloat(document.getElementById(`hh-bonus-${candidateId}`)?.value || 0);
    if (!salary) {
        showToast(hrl('toast_err'), hrl('err_no_salary'), 'error');
        return;
    }

    try {
        const result = await hrApi('make_headhunter_offer', {
            candidate_id: candidateId,
            salary,
            signing_bonus: bonus,
        });

        if (!result.success) {
            showToast(hrl('toast_err'), result.message || result.error, 'error');
            return;
        }

        if (result.decision === 'accept') {
            showToast(hrl('toast_headhunter'), result.message);
            setTimeout(() => location.reload(), 1500);
            return;
        }

        if (result.decision === 'negotiate') {
            showToast(
                hrl('toast_negotiating'),
                hrl('negotiate_msg', {
                    msg: result.message,
                    salary: (result.counter_salary || 0).toLocaleString(),
                    bonus: (result.counter_bonus || 0).toLocaleString(),
                }),
                'warning'
            );
            const salaryInput = document.getElementById(`hh-salary-${candidateId}`);
            const bonusInput = document.getElementById(`hh-bonus-${candidateId}`);
            if (salaryInput) {
                salaryInput.value = result.counter_salary;
            }
            if (bonusInput) {
                bonusInput.value = result.counter_bonus;
            }
            return;
        }

        showToast(hrl('toast_offer_rejected'), result.message, 'error');
        setTimeout(() => location.reload(), 2000);
    } catch (error) {
        showToast(hrl('toast_err'), error.message, 'error');
    }
}

function showToast(title, message, type = 'success') {
    if (typeof window.showGameToast === 'function') {
        window.showGameToast(title, message, type);
        return;
    }
    alertInfo(title + ': ' + message);
}

function updateCountdowns() {
    document.querySelectorAll('.countdown[data-end]').forEach((element) => {
        const end = parseInt(element.dataset.end || '0', 10) * 1000;
        if (!end) {
            return;
        }

        const tick = () => {
            const diff = end - Date.now();
            if (diff <= 0) {
                element.textContent = '00:00';
                return;
            }
            const hours = Math.floor(diff / 3600000);
            const minutes = Math.floor((diff % 3600000) / 60000);
            const seconds = Math.floor((diff % 60000) / 1000);
            element.textContent = hours > 0
                ? `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
                : `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        };

        tick();
        setInterval(tick, 1000);
    });
}

document.addEventListener('DOMContentLoaded', updateCountdowns);
