/* Admin incidents panel - tab switching */
(function () {
    var tabs = ['stats', 'micro', 'minor', 'medium', 'major', 'pipe_micro', 'pipe_minor', 'pipe_medium', 'recent', 'help', 'trigger'];

    window.incShowTab = function (name) {
        tabs.forEach(function (id) {
            var el  = document.getElementById('inc-tab-' + id);
            var btn = document.getElementById('inc-btn-' + id);
            if (el)  el.classList.toggle('active', id === name);
            if (btn) btn.classList.toggle('active', id === name);
        });
        try { sessionStorage.setItem('inc_tab', name); } catch (e) {}
    };

    // Restore last tab or default to stats
    var saved = '';
    try { saved = sessionStorage.getItem('inc_tab') || ''; } catch (e) {}
    incShowTab(tabs.indexOf(saved) >= 0 ? saved : 'stats');
}());
