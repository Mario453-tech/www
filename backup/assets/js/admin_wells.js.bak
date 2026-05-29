/**
 * admin_wells.js - admin well panel tab handling and GM edit panel
 * admin_wells.js - obsluga zakladek panelu odwiertow admina i panelu edycji GM
 */
(function () {
    var tabs = ['config', 'sell', 'wells', 'events', 'help'];

    window.wellsShowTab = function (name) {
        tabs.forEach(function (id) {
            var el  = document.getElementById('tab-' + id);
            var btn = document.getElementById('tab-btn-' + id);
            if (!el || !btn) return;
            var active = id === name;
            el.classList.toggle('active', active);
            btn.classList.toggle('active', active);
        });
        // Persist selected tab in URL hash without reload
        if (history && history.replaceState) {
            history.replaceState(null, '', location.pathname + location.search + '#tab-' + name);
        }
    };

    // Toggle GM edit panel; close others when opening
    // Przelacz panel GM; zamknij pozostale przy otwieraniu
    window.awToggle = function (id) {
        var panel = document.getElementById('aw-form-' + id);
        if (!panel) return;
        var opening = panel.style.display === 'none';
        if (opening) {
            document.querySelectorAll('.aw-gm-panel').forEach(function (p) {
                p.style.display = 'none';
            });
        }
        panel.style.display = opening ? 'block' : 'none';
        if (opening) {
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    };

    // Upgrade checkbox: toggle visual state immediately
    // Zmiana stanu checkboxa modernizacji — natychmiastowy efekt wizualny
    document.addEventListener('change', function (e) {
        if (e.target && e.target.type === 'checkbox' && e.target.name === 'gm_upgrades[]') {
            var label = e.target.closest('.aw-upgrade-checkbox');
            if (label) label.classList.toggle('aw-upgrade-checkbox--on', e.target.checked);
        }
    });

    // Restore active tab: prefer URL hash, then query param, then 'config'
    // Przywroc aktywna zakladke z hasha URL lub domyslnie 'config'
    function getInitialTab() {
        var hash = location.hash.replace('#tab-', '');
        if (tabs.indexOf(hash) !== -1) return hash;
        var urlTab = new URLSearchParams(location.search).get('tab');
        if (tabs.indexOf(urlTab) !== -1) return urlTab;
        return 'config';
    }

    wellsShowTab(getInitialTab());
}());
