function openEdit(loc) {
    document.getElementById('edit-loc-id').value    = loc.id;
    document.getElementById('edit-region-id').value = loc.region_id;
    document.getElementById('edit-name').value       = loc.name;
    document.getElementById('edit-country').value    = loc.country_code;
    document.getElementById('edit-lat').value        = loc.latitude;
    document.getElementById('edit-lng').value        = loc.longitude;
    document.getElementById('edit-richness').value   = loc.oil_richness;
    document.getElementById('edit-type').value       = loc.well_type;
    document.getElementById('edit-desc').value       = loc.description || '';
    var modal = document.getElementById('edit-modal');
    modal.removeAttribute('hidden');
    modal.style.display = 'flex';
}

function closeEdit() {
    var modal = document.getElementById('edit-modal');
    modal.setAttribute('hidden', '');
    modal.style.display = '';
}

document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('edit-modal');
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === this) closeEdit();
        });
    }
});
