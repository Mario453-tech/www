/**
 * dashboard.js  logika panelu zarzdzania
 */

async function hireCandidate(candidateId, candidateName) {
    confirmAction(`Czy na pewno chcesz zatrudni: ${candidateName}?`, async function () {
        try {
            const fd = new FormData();
            fd.append('action', 'hire_candidate');
            fd.append('candidate_id', candidateId);
            fd.append('_token', CSRF_TOKEN);

            const res = await fetch(API_URL, { method: 'POST', body: fd });
            const result = await res.json();

            if (result.success) {
                window.showGameToast('Zatrudniono', 'Kandydat zosta zatrudniony.', 'success');
                setTimeout(function () { location.reload(); }, 900);
            } else {
                alertError(result.error ? ('Bd: ' + result.error) : 'Nie udao si zatrudni kandydata.');
            }
        } catch (e) {
            alertError('Wystpi bd podczas zatrudniania.');
        }
    }, { type: 'confirm', confirmLabel: 'Zatrudnij' });
}

async function fireEmployee(memberId, memberName) {
    confirmAction(
        `Czy na pewno chcesz zwolni: ${memberName}? Ta decyzja jest nieodwracalna.`,
        function () {
            promptInput('Podaj powd zwolnienia:', 'Decyzja dyrektora', async function (reason) {
                try {
                    const fd = new FormData();
                    fd.append('action', 'fire_employee');
                    fd.append('member_id', memberId);
                    fd.append('reason', reason || 'Decyzja dyrektora');
                    fd.append('_token', CSRF_TOKEN);

                    const res = await fetch(API_URL, { method: 'POST', body: fd });
                    const result = await res.json();

                    if (result.success) {
                        window.showGameToast('Zwolniono', 'Pracownik zosta zwolniony.', 'success');
                        setTimeout(function () { location.reload(); }, 900);
                    } else {
                        alertError(result.error ? ('Bd: ' + result.error) : 'Nie udao si zwolni pracownika.');
                    }
                } catch (e) {
                    alertError('Wystpi bd podczas zwalniania.');
                }
            }, { title: 'Powd zwolnienia', confirmLabel: 'Zwolnij' });
        },
        { type: 'danger', confirmLabel: 'Zwolnij' }
    );
}

// Auto-refresh co 30 sekund
setTimeout(() => location.reload(), 30000);
