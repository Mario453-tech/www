(function () {
    'use strict';

    let reloadScheduled = false;

    function scheduleReload(delay) {
        if (reloadScheduled) {
            return;
        }
        reloadScheduled = true;
        setTimeout(function () {
            window.location.replace(window.location.pathname + window.location.search);
        }, delay);
    }

    function formatPipelineSeconds(seconds) {
        if (seconds <= 0) {
            return '-';
        }
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const remainingSeconds = seconds % 60;
        return (hours > 0 ? hours + 'h ' : '') + minutes + 'min ' + remainingSeconds + 's';
    }

    function initPipelineCountdown(element) {
        if (element.dataset.countdownInitialized === '1') {
            return;
        }
        element.dataset.countdownInitialized = '1';
        const finish = new Date(String(element.dataset.finish || '').replace(' ', 'T')).getTime();
        if (!Number.isFinite(finish)) {
            element.textContent = '-';
            return;
        }

        function tick() {
            if (!document.documentElement.contains(element)) {
                return;
            }
            const remaining = Math.floor((finish - Date.now()) / 1000);
            element.textContent = formatPipelineSeconds(remaining);
            if (remaining > 0) {
                setTimeout(tick, 1000);
            } else {
                scheduleReload(3000);
            }
        }

        tick();
    }

    function formatRoadSeconds(seconds) {
        if (seconds <= 0) {
            return '0h 00m';
        }
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return hours + 'h ' + String(minutes).padStart(2, '0') + 'm';
    }

    function initRoadCountdown(element) {
        if (element.dataset.countdownInitialized === '1') {
            return;
        }
        element.dataset.countdownInitialized = '1';
        const total = parseInt(element.dataset.seconds, 10) || 0;
        if (total <= 0) {
            element.textContent = '-';
            return;
        }
        const startedAt = Date.now();

        function tick() {
            if (!document.documentElement.contains(element)) {
                return;
            }
            const elapsed = Math.floor((Date.now() - startedAt) / 1000);
            const remaining = Math.max(0, total - elapsed);
            element.textContent = formatRoadSeconds(remaining);
            if (remaining > 0) {
                setTimeout(tick, 30000);
            } else {
                scheduleReload(5000);
            }
        }

        tick();
    }

    function initialize(root) {
        const scope = root || document;
        scope.querySelectorAll('.pipeline-countdown[data-finish]').forEach(initPipelineCountdown);
        scope.querySelectorAll('.road-trip-countdown[data-seconds]').forEach(initRoadCountdown);
    }

    document.addEventListener('ajax-pagination:updated', function (event) {
        initialize(event.detail && event.detail.root ? event.detail.root : document);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initialize(document); });
    } else {
        initialize(document);
    }
})();
