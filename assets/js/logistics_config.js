(function () {
    'use strict';

    // Read server-rendered data without executing inline JavaScript.
    // Odczytaj dane serwera bez wykonywania JavaScript inline.
    const node = document.getElementById('logistics-client-config');
    if (!node) {
        return;
    }

    let config;
    try {
        config = JSON.parse(node.textContent || '{}');
    } catch (error) {
        console.error('[logistics] Invalid client configuration.', error);
        return;
    }

    window.LOGISTICS_CLIENT_CONFIG = config;

    const optimizer = config.optimizer || {};
    window.LOGISTICS_API = optimizer.api || '/src/LogisticsApi.php';
    window.LOGISTICS_CSRF = optimizer.csrf_token || '';
    window.LOGISTICS_LANG = optimizer.lang || {};
    window.LOGISTICS_LOCALE = optimizer.locale || 'pl-PL';
    window.LOGISTICS_CURRENCY = optimizer.currency || 'PLN';

    const hub = config.hub || {};
    window.HUB_API = hub.api || '/src/HubApi.php';
    window.HUB_CSRF = hub.csrf_token || '';
    window.HUB_LANG = hub.lang || {};
    window.HUB_LOCALE = hub.locale || window.LOGISTICS_LOCALE;
    window.HUB_CURRENCY = hub.currency || window.LOGISTICS_CURRENCY;
    window.HUB_STAFFING_CONFIG = config.staffing || null;

    const pipeline = config.pipeline || {};
    window.PIPELINE_API = pipeline.api || '/src/PipelineApi.php';
    window.PIPELINE_CSRF = pipeline.csrf_token || '';
    window.PIPELINE_LANG = pipeline.lang || {};

    const protection = config.protection || {};
    window.PROTECTION_API = protection.api || '/public/protection.php';
    window.PROTECTION_CSRF = protection.csrf_token || '';
    window.PROTECTION_LANG = protection.lang || {};
})();
