/**
 * OilEmpire - Admin Visit Statistics Script
 * Skrypt statystyk odwiedzin w panelu admina
 */
(function () {
    'use strict';

    // 1. Remove flash message from DOM after delay (prevent stale flash on bfcache/reload).
    // 1. Usun komunikat flash z DOM po czasie (zapobieganie powrotowi starego bledu).
    var flash = document.getElementById('visits-flash');
    if (flash) {
        setTimeout(function () {
            if (flash && flash.parentNode) {
                flash.parentNode.removeChild(flash);
            }
        }, 5000);
    }

    // 2. Setup cleanup confirmation modal.
    // 2. Konfiguracja modalu potwierdzenia czyszczenia.
    var cleanupForm = document.getElementById('visits-cleanup-form');
    if (cleanupForm) {
        cleanupForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var lang = window.VISITS_LANG || {};
            var msg = lang.confirm_cleanup || 'Are you sure you want to clean up logs?';
            if (typeof window.confirmAction === 'function') {
                window.confirmAction(msg, function () {
                    cleanupForm.submit();
                });
            } else {
                cleanupForm.submit();
            }
        });
    }

    // 3. Render Canvas Timeline Chart.
    // 3. Renderowanie wykresu osi czasu na Canvasie.
    var canvas = document.getElementById('visits-timeline-canvas');
    var container = document.getElementById('visits-chart-container');
    var tooltip = document.getElementById('visits-chart-tooltip');

    if (!canvas || !container || !window.VISITS_DATA || !Array.isArray(window.VISITS_DATA.timeline)) {
        return;
    }

    var timeline = window.VISITS_DATA.timeline;
    if (timeline.length === 0) {
        return;
    }

    var ctx = canvas.getContext('2d');
    if (!ctx) {
        return;
    }

    var hoverIndex = -1;

    function resizeCanvas() {
        var rect = container.getBoundingClientRect();
        var dpr = window.devicePixelRatio || 1;
        canvas.width = Math.floor(rect.width * dpr);
        canvas.height = Math.floor(rect.height * dpr);
        drawChart();
    }

    function drawChart() {
        var dpr = window.devicePixelRatio || 1;
        var w = canvas.width / dpr;
        var h = canvas.height / dpr;

        ctx.save();
        ctx.scale(dpr, dpr);
        ctx.clearRect(0, 0, w, h);

        var padL = 40;
        var padR = 20;
        var padT = 20;
        var padB = 30;

        var chartW = w - padL - padR;
        var chartH = h - padT - padB;

        // Calculate max value for Y axis.
        // Oblicz maksymalna wartosc dla osi Y.
        var maxVal = 5;
        for (var i = 0; i < timeline.length; i++) {
            if (timeline[i].pv > maxVal) maxVal = timeline[i].pv;
            if (timeline[i].uv > maxVal) maxVal = timeline[i].uv;
        }
        maxVal = Math.ceil(maxVal * 1.15);

        // Draw horizontal grid lines.
        // Rysuj poziome linie siatki.
        var gridCount = 4;
        ctx.strokeStyle = '#334155';
        ctx.lineWidth = 1;
        ctx.fillStyle = '#64748b';
        ctx.font = '11px sans-serif';
        ctx.textAlign = 'right';
        ctx.textBaseline = 'middle';

        for (var g = 0; g <= gridCount; g++) {
            var yVal = Math.round((maxVal / gridCount) * g);
            var yPos = padT + chartH - (g / gridCount) * chartH;

            ctx.beginPath();
            ctx.moveTo(padL, yPos);
            ctx.lineTo(padL + chartW, yPos);
            ctx.stroke();

            ctx.fillText(yVal.toString(), padL - 8, yPos);
        }

        var count = timeline.length;
        var stepX = count > 1 ? chartW / (count - 1) : chartW;

        function getX(idx) {
            return count > 1 ? padL + idx * stepX : padL + chartW / 2;
        }

        function getY(val) {
            return padT + chartH - (val / maxVal) * chartH;
        }

        // Draw PV line & area (Page Views).
        // Rysuj linie i obszar PV (Odslony).
        ctx.beginPath();
        for (var p = 0; p < count; p++) {
            var px = getX(p);
            var py = getY(timeline[p].pv);
            if (p === 0) ctx.moveTo(px, py);
            else ctx.lineTo(px, py);
        }
        ctx.strokeStyle = '#818cf8';
        ctx.lineWidth = 2.5;
        ctx.stroke();

        // Area under PV.
        // Obszar pod PV.
        ctx.lineTo(getX(count - 1), padT + chartH);
        ctx.lineTo(getX(0), padT + chartH);
        ctx.closePath();
        var pvGrad = ctx.createLinearGradient(0, padT, 0, padT + chartH);
        pvGrad.addColorStop(0, 'rgba(129, 140, 248, 0.25)');
        pvGrad.addColorStop(1, 'rgba(129, 140, 248, 0.0)');
        ctx.fillStyle = pvGrad;
        ctx.fill();

        // Draw UV line & area (Unique Visitors).
        // Rysuj linie i obszar UV (Unikalni goscie).
        ctx.beginPath();
        for (var u = 0; u < count; u++) {
            var ux = getX(u);
            var uy = getY(timeline[u].uv);
            if (u === 0) ctx.moveTo(ux, uy);
            else ctx.lineTo(ux, uy);
        }
        ctx.strokeStyle = '#38bdf8';
        ctx.lineWidth = 2.5;
        ctx.stroke();

        // Area under UV.
        // Obszar pod UV.
        ctx.lineTo(getX(count - 1), padT + chartH);
        ctx.lineTo(getX(0), padT + chartH);
        ctx.closePath();
        var uvGrad = ctx.createLinearGradient(0, padT, 0, padT + chartH);
        uvGrad.addColorStop(0, 'rgba(56, 189, 248, 0.35)');
        uvGrad.addColorStop(1, 'rgba(56, 189, 248, 0.0)');
        ctx.fillStyle = uvGrad;
        ctx.fill();

        // Draw X-axis date labels.
        // Rysuj etykiety dat na osi X.
        ctx.fillStyle = '#94a3b8';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';

        var labelFreq = count > 14 ? Math.ceil(count / 7) : 1;
        for (var l = 0; l < count; l += labelFreq) {
            ctx.fillText(timeline[l].label, getX(l), padT + chartH + 8);
        }
        // Always show the last date label if not rendered.
        // Zawsze pokaz ostatnia date jesli nie byla wyswietlona.
        if ((count - 1) % labelFreq !== 0) {
            ctx.fillText(timeline[count - 1].label, getX(count - 1), padT + chartH + 8);
        }

        // Draw hover points and guideline if active.
        // Rysuj punkty i linie pomocnicza przy najechaniu mysza.
        if (hoverIndex >= 0 && hoverIndex < count) {
            var hx = getX(hoverIndex);
            var hyUv = getY(timeline[hoverIndex].uv);
            var hyPv = getY(timeline[hoverIndex].pv);

            ctx.strokeStyle = '#64748b';
            ctx.setLineDash([3, 3]);
            ctx.beginPath();
            ctx.moveTo(hx, padT);
            ctx.lineTo(hx, padT + chartH);
            ctx.stroke();
            ctx.setLineDash([]);

            // PV point.
            ctx.fillStyle = '#818cf8';
            ctx.beginPath();
            ctx.arc(hx, hyPv, 5, 0, Math.PI * 2);
            ctx.fill();

            // UV point.
            ctx.fillStyle = '#38bdf8';
            ctx.beginPath();
            ctx.arc(hx, hyUv, 5, 0, Math.PI * 2);
            ctx.fill();
        }

        ctx.restore();
    }

    // Handle mouse movement for tooltips.
    // Obsluga ruchu kursora myszy dla tooltipow.
    container.addEventListener('mousemove', function (e) {
        var rect = container.getBoundingClientRect();
        var x = e.clientX - rect.left;
        var y = e.clientY - rect.top;

        var padL = 40;
        var padR = 20;
        var chartW = rect.width - padL - padR;

        if (x < padL || x > rect.width - padR || y < 10 || y > rect.height - 20) {
            hoverIndex = -1;
            if (tooltip) tooltip.hidden = true;
            drawChart();
            return;
        }

        var count = timeline.length;
        var stepX = count > 1 ? chartW / (count - 1) : chartW;
        var idx = count > 1 ? Math.round((x - padL) / stepX) : 0;
        idx = Math.max(0, Math.min(count - 1, idx));

        if (idx !== hoverIndex) {
            hoverIndex = idx;
            drawChart();

            var item = timeline[idx];
            if (tooltip) {
                var lang = window.VISITS_LANG || {};
                var playersLabel = lang.players || 'Players';
                var guestsLabel = lang.guests || 'Guests';
                var uvLabel = lang.uv || 'UV';
                var pvLabel = lang.pv || 'PV';

                tooltip.innerHTML =
                    '<strong>' + item.date + ' (' + item.label + ')</strong><br>' +
                    '<span style="color:#38bdf8">●</span> ' + uvLabel + ': <strong>' + item.uv + '</strong><br>' +
                    '<span style="color:#818cf8">●</span> ' + pvLabel + ': <strong>' + item.pv + '</strong><br>' +
                    '<small style="color:#94a3b8">' + playersLabel + ': ' + item.player_views + ' | ' + guestsLabel + ': ' + item.guest_views + '</small>';
                tooltip.style.left = (count > 1 ? (padL + idx * stepX) : rect.width / 2) + 'px';
                tooltip.style.top = Math.max(10, y - 10) + 'px';
                tooltip.hidden = false;
            }
        }
    });

    container.addEventListener('mouseleave', function () {
        hoverIndex = -1;
        if (tooltip) tooltip.hidden = true;
        drawChart();
    });

    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();
})();
