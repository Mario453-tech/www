document.addEventListener('DOMContentLoaded', function () {
    const roleInput = document.getElementById('director-role-id');
    const roleGrid = document.getElementById('director-role-grid');
    const regionInput = document.getElementById('director-region-code');
    const regionGrid = document.getElementById('director-region-grid');
    if (roleInput && roleGrid) {
        roleGrid.addEventListener('click', function (event) {
            const card = event.target.closest('.db-role-card');
            if (!card) {
                return;
            }
            roleInput.value = card.dataset.roleId || '';
            roleGrid.querySelectorAll('.db-role-card').forEach(function (node) {
                node.classList.toggle('is-selected', node === card);
            });
        });
    }

    if (!regionInput || !regionGrid) {
        return;
    }

    regionGrid.addEventListener('click', function (event) {
        const card = event.target.closest('.db-region-card');
        if (!card) {
            return;
        }
        regionInput.value = card.dataset.regionCode || 'PL';
        regionGrid.querySelectorAll('.db-region-card').forEach(function (node) {
            node.classList.toggle('is-selected', node === card);
        });
    });
});
