/**
 * admin_wells.js - admin well panel tab handling
 * admin_wells.js - obsluga zakladek panelu odwiertow admina
 */
(function () {
    var tabs = ['config', 'sell', 'wells', 'events', 'help'];

    window.wellsShowTab = function (name) {
        tabs.forEach(function (id) {
            var el  = document.getElementById('tab-' + id);
            var btn = document.getElementById('tab-btn-' + id);
            var active = id === name;
            el.classList.toggle('active', active);
            btn.classList.toggle('active', active);
        });
    };

    wellsShowTab('config');

    // Toggle inline edit form for well pressure / reservoir
    // Przelacz inline formularz edycji cisnienia i zloza odwiertu
    window.awToggle = function (id) {
        var form = document.getElementById('aw-form-' + id);
        if (!form) return;
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    };
}());
