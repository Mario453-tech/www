/* 
   finance.js  wykres historii finansowej
   Uywa Chart.js (adowanego z CDN przez PHP)
    */
var _FINL = window.FIN_LANG || {};
function finl(k) { return _FINL[k] || k; }

(function () {
    'use strict';

    var history = window._finHistory;
    if (!history || !history.length) return;

    var canvas = document.getElementById('finChart');
    if (!canvas) return;

    // Wczytaj Chart.js dynamicznie
    var script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js';
    script.onload = function () { renderChart(canvas, history); };
    document.head.appendChild(script);

    function renderChart(canvas, data) {
        var labels   = data.map(function (r) {
            var d = new Date(r.tick_at);
            return d.getDate() + '.' + (d.getMonth()+1) + ' ' +
                   String(d.getHours()).padStart(2,'0') + ':' +
                   String(d.getMinutes()).padStart(2,'0');
        });
        var revenue  = data.map(function (r) { return parseFloat(r.revenue)     || 0; });
        var cost     = data.map(function (r) { return parseFloat(r.total_cost)  || 0; });
        var profit   = data.map(function (r) { return parseFloat(r.net_profit)  || 0; });
        var loss     = data.map(function (r) { return parseFloat(r.loss_value)  || 0; });

        // Inteligentny step dla labelek
        var step = Math.max(1, Math.floor(labels.length / 12));
        var sparseLabels = labels.map(function (l, i) {
            return i % step === 0 ? l : '';
        });

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: sparseLabels,
                datasets: [
                    {
                        label: finl('revenue'),
                        data: revenue,
                        borderColor: '#4ec97a',
                        backgroundColor: 'rgba(78,201,122,.08)',
                        borderWidth: 1.5,
                        pointRadius: 0,
                        tension: 0.3,
                        fill: true,
                    },
                    {
                        label: finl('costs'),
                        data: cost,
                        borderColor: '#e05555',
                        backgroundColor: 'rgba(224,85,85,.06)',
                        borderWidth: 1.5,
                        pointRadius: 0,
                        tension: 0.3,
                        fill: true,
                    },
                    {
                        label: finl('net_profit'),
                        data: profit,
                        borderColor: '#c8a84b',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        pointRadius: 0,
                        tension: 0.3,
                        fill: false,
                    },
                    {
                        label: finl('losses'),
                        data: loss,
                        borderColor: '#f0a050',
                        backgroundColor: 'transparent',
                        borderWidth: 1,
                        borderDash: [4, 3],
                        pointRadius: 0,
                        tension: 0.3,
                        fill: false,
                    },
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        labels: {
                            color: 'rgba(232,232,240,.6)',
                            boxWidth: 12,
                            font: { size: 11 },
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15,15,24,.95)',
                        titleColor: '#e8e8f0',
                        bodyColor: 'rgba(232,232,240,.7)',
                        borderColor: 'rgba(200,168,75,.25)',
                        borderWidth: 1,
                        callbacks: {
                            label: function (ctx) {
                                var v = ctx.parsed.y;
                                var abs = Math.abs(v);
                                var fmt = abs >= 1000000
                                    ? (abs/1000000).toFixed(2) + 'M'
                                    : abs >= 1000
                                    ? (abs/1000).toFixed(1) + 'K'
                                    : abs.toFixed(0);
                                return ' ' + ctx.dataset.label + ': ' + (v < 0 ? '' : '') + fmt + ' PLN';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            color: 'rgba(232,232,240,.35)',
                            font: { size: 10 },
                            maxRotation: 0,
                        },
                        grid: { color: 'rgba(255,255,255,.04)' },
                    },
                    y: {
                        ticks: {
                            color: 'rgba(232,232,240,.35)',
                            font: { size: 10 },
                            callback: function (v) {
                                var abs = Math.abs(v);
                                if (abs >= 1000000) return (v/1000000).toFixed(1) + 'M';
                                if (abs >= 1000)    return (v/1000).toFixed(0) + 'K';
                                return v;
                            }
                        },
                        grid: { color: 'rgba(255,255,255,.04)' },
                    }
                }
            }
        });
    }
})();

/*  Picker trybu / rezerwy (zakadka Polityka)  */
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.fin-mode-picker').forEach(function (picker) {
            picker.querySelectorAll('input[type="radio"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    picker.querySelectorAll('.fin-mode-option').forEach(function (opt) {
                        opt.classList.remove('fin-mode-option--active');
                    });
                    if (radio.checked) {
                        radio.closest('.fin-mode-option').classList.add('fin-mode-option--active');
                    }
                });
            });
        });
    });
})();

/*  Odliczanie cooldown-u polityki oszczdnoci  */
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var display = document.getElementById('fin-cooldown-display');
        if (!display) return;
        var secs = parseInt(display.getAttribute('data-seconds') || '0', 10);
        if (secs <= 0) return;
        var tpl = window._FIN_COOLDOWN_TPL || '__H__h __M__min';
        function fmtCountdown(s) {
            var h = Math.floor(s / 3600);
            var m = String(Math.floor((s % 3600) / 60)).padStart(2, '0');
            return tpl.replace('__H__', h).replace('__M__', m);
        }
        display.textContent = fmtCountdown(secs);
        var iv = setInterval(function () {
            secs--;
            if (secs <= 0) {
                clearInterval(iv);
                display.closest('.fin-policy-lock-row').outerHTML =
                    '<div class="fin-policy-lock-row fin-green"><span>&#10003;</span><span>' +
                    (window.FIN_LANG && window.FIN_LANG['savings_can_change'] || 'Moesz teraz zmieni tryb oszczdnoci') +
                    '</span></div>';
                return;
            }
            display.textContent = fmtCountdown(secs);
        }, 1000);
    });
})();

/*  Dynamiczny badge poziomu budetu (zakadka Budety)  */
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.fin-budget-select').forEach(function (sel) {
            sel.addEventListener('change', function () {
                var badgeId = sel.getAttribute('data-badge');
                var badge = badgeId ? document.getElementById(badgeId) : null;
                if (!badge) return;
                var val = sel.value;
                badge.className = 'fin-level-badge fin-level-badge--' + val;
                var opt = sel.options[sel.selectedIndex];
                badge.textContent = opt ? opt.text : val;
            });
        });
    });
})();

/*  Toast przy powrocie po zapisie ($msg / $err PHP)  */
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var msg    = window._FIN_MSG;
        var err    = window._FIN_ERR;
        var banner = document.getElementById('fin-msg-banner');
        var errBan = document.getElementById('fin-err-banner');
        if (msg && typeof showGameToast === 'function') {
            showGameToast(msg, 'success');
            if (banner) banner.style.display = 'none';
        }
        if (err && typeof showGameToast === 'function') {
            showGameToast(err, 'error');
            if (errBan) errBan.style.display = 'none';
        }
    });
})();

/*  Potwierdzenia zmian polityki finansowej  */
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var conf    = window._FIN_CONFIRM    || {};
        var curMode = window._FIN_CUR_MODE   || 'off';
        var curRes  = window._FIN_CUR_RESERVE || 'standard';

        var policyForms = document.querySelectorAll('#finance-policy-form, .finance-policy-form');
        policyForms.forEach(function (form) {
            form.addEventListener('submit', function (e) {

                /*  plan oszczdnoci  */
                var selMode = form.querySelector('input[name="savings_plan_mode"]:checked');
                if (selMode && selMode.value !== curMode) {
                    var m = selMode.value;
                    var mText, mOpts;
                    if (m === 'aggressive') {
                        mText = conf.aggressive || 'Wczy agresywny plan?';
                        mOpts = { type: 'warning', confirmLabel: 'Tak, wcz' };
                    } else if (m === 'off') {
                        mText = conf.turnoff || 'Wyczy plan oszczdnoci?';
                        mOpts = { type: 'warning', confirmLabel: 'Tak, wycz' };
                    } else {
                        mText = conf.moderate || 'Wczy umiarkowany plan?';
                        mOpts = { type: 'info', confirmLabel: 'Tak, wcz' };
                    }
                    if (typeof confirmAction === 'function') {
                        e.preventDefault();
                        confirmAction(mText, function () { form.submit(); }, mOpts);
                    }
                    return;
                }

                /*  rezerwa awaryjna  */
                var selRes = form.querySelector('input[name="reserve_policy"]:checked');
                if (selRes && selRes.value !== curRes) {
                    var rText = conf.reserve || 'Zmieni poziom rezerwy awaryjnej?';
                    if (typeof confirmAction === 'function') {
                        e.preventDefault();
                        confirmAction(rText, function () { form.submit(); }, {
                            type: 'info',
                            confirmLabel: 'Tak, zmie'
                        });
                    }
                }
            });
        });
    });
})();