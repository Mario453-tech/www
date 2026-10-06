/**
 * Shared navigation and event countdown.
 * Wspolna nawigacja i licznik wydarzenia.
 */
(function () {
    'use strict';

    function initNavigation() {
        var config = document.getElementById('appConfig');
        var burger = document.getElementById('nav-burger');
        var nav = document.getElementById('user-nav');
        var backdrop = document.getElementById('nav-backdrop');
        if (!burger || !nav) return;

        function closeNav() {
            document.body.classList.remove('nav-open');
            burger.setAttribute('aria-expanded', 'false');
            burger.setAttribute('aria-label', config ? config.dataset.openMenu || '' : '');
        }

        function openNav() {
            document.body.classList.add('nav-open');
            burger.setAttribute('aria-expanded', 'true');
            burger.setAttribute('aria-label', config ? config.dataset.closeMenu || '' : '');
        }

        burger.addEventListener('click', function () {
            document.body.classList.contains('nav-open') ? closeNav() : openNav();
        });
        if (backdrop) backdrop.addEventListener('click', closeNav);
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeNav();
        });
        nav.querySelectorAll('a.btn').forEach(function (link) {
            link.addEventListener('click', closeNav);
        });
    }

    function initTrendTimer() {
        var element = document.getElementById('trend-timer');
        if (!element) return;
        var seconds = parseInt(element.dataset.seconds || '0', 10);
        if (seconds <= 0) return;

        var interval = window.setInterval(function () {
            seconds--;
            if (seconds <= 0) {
                window.clearInterval(interval);
                element.textContent = '00:00';
                return;
            }
            var hours = Math.floor(seconds / 3600);
            var minutes = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
            element.textContent = String(hours).padStart(2, '0') + ':' + minutes;
        }, 1000);
    }

    initNavigation();
    initTrendTimer();
})();
