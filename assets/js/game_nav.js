/**
 * Grouped game navigation.
 * Grupowana nawigacja gry.
 */
(function () {
    'use strict';

    var nav = document.getElementById('user-nav');
    if (!nav) return;
    var toggles = Array.from(nav.querySelectorAll('[data-nav-group]'));

    function closeGroups() {
        toggles.forEach(function (button) {
            button.setAttribute('aria-expanded', 'false');
        });
        nav.querySelectorAll('[data-nav-panel]').forEach(function (panel) {
            panel.hidden = true;
        });
    }

    toggles.forEach(function (button) {
        button.addEventListener('click', function () {
            var opening = button.getAttribute('aria-expanded') !== 'true';
            closeGroups();
            if (!opening) return;
            button.setAttribute('aria-expanded', 'true');
            var panel = document.getElementById(button.getAttribute('aria-controls'));
            if (panel) panel.hidden = false;
        });
    });

    nav.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeGroups();
            var burger = document.getElementById('nav-burger');
            if (burger && document.body.classList.contains('nav-open')) burger.focus();
        }
    });
    nav.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            document.body.classList.remove('nav-open');
            var burger = document.getElementById('nav-burger');
            if (burger) burger.setAttribute('aria-expanded', 'false');
        });
    });
})();
