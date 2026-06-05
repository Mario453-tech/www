/* Admin incidents panel - tab switching */
(function () {
    var tabs = ['stats', 'micro', 'minor', 'medium', 'major', 'pipe_micro', 'pipe_minor', 'pipe_medium', 'marine', 'cooldown', 'recent', 'help', 'trigger'];

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

/* Selektory recznego wywolywania incydentow / Admin incident trigger selectors */
(function () {
    function getTriggerData() {
        return window.INCIDENTS_TRIGGER_DATA || {};
    }

    function clearSelect(selectEl, placeholder) {
        if (!selectEl) return;
        selectEl.innerHTML = '';
        var option = document.createElement('option');
        option.value = '';
        option.textContent = placeholder || '-';
        selectEl.appendChild(option);
    }

    window.incTrigUpdateWells = function (playerId) {
        var data = getTriggerData();
        var labels = data.labels || {};
        var selectEl = document.getElementById('trig-well');
        clearSelect(selectEl, labels.selectWell);
        var wells = (data.wells || {})[playerId] || [];
        wells.forEach(function (well) {
            var option = document.createElement('option');
            option.value = well.id;
            option.textContent = well.location_name + ' [' + well.status + ', cond:' + well.technical_condition + '%]';
            selectEl.appendChild(option);
        });
    };

    window.incTrigUpdatePipelines = function (playerId) {
        var data = getTriggerData();
        var labels = data.labels || {};
        var selectEl = document.getElementById('trig-pipe-pipeline');
        clearSelect(selectEl, labels.selectPipeline);
        var pipelines = (data.pipelines || {})[playerId] || [];
        pipelines.forEach(function (pipeline) {
            var option = document.createElement('option');
            option.value = pipeline.id;
            option.textContent = pipeline.name + ' [' + pipeline.status + ', cond:' + pipeline.condition_pct + '%, loss:' + pipeline.transport_loss + '%]';
            selectEl.appendChild(option);
        });
    };

    window.incTrigUpdateMarineDeliveries = function (playerId) {
        var data = getTriggerData();
        var labels = data.labels || {};
        var selectEl = document.getElementById('trig-marine-delivery');
        clearSelect(selectEl, labels.selectMarineDelivery);
        var deliveries = (data.marineDeliveries || {})[playerId] || [];
        deliveries.forEach(function (delivery) {
            var option = document.createElement('option');
            var portName = delivery.port_name || labels.portUnknown || '-';
            option.value = delivery.id;
            option.textContent = '#' + delivery.id + ' ' + delivery.well_name + ' -> ' + portName + ' [' + delivery.status + ', ' + delivery.volume_bbl + ' bbl]';
            selectEl.appendChild(option);
        });
    };
}());
