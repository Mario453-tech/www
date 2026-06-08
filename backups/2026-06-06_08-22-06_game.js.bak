var _GAMEL = window.GAME_LANG || {};
function gamel(k) { return _GAMEL[k] || k; }

// Auto refresh every 5 minutes
// Auto odswiezanie co 5 minut
setTimeout(() => {
    location.reload();
}, 300000);

// Confirm before oil sale
// Potwierdzenie przed sprzedaza ropy
document.addEventListener('DOMContentLoaded', () => {
    const sellForm = document.querySelector('form[action="/public/sell.php"]');
    if (sellForm) {
        sellForm.addEventListener('submit', (e) => {
            e.preventDefault();
            confirmAction(gamel('confirm_sell_oil'), function () {
                sellForm.submit();
            }, { type: 'danger', confirmLabel: gamel('confirm') || 'Potwierdz' });
        });
    }
});

(function () {
    var previousUnread = null;

    function playDmSound() {
        if (localStorage.getItem('dm.soundEnabled') === '0') return;
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = 880;
            gain.gain.value = 0.025;
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.18);
            osc.stop(ctx.currentTime + 0.18);
        } catch (e) {}
    }

    function updateDmBadge(count) {
        var link = document.querySelector('a[href^="/dm"]');
        if (!link) return;
        var badge = link.querySelector('.nav-dm-badge');
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'nav-dm-badge';
                link.appendChild(badge);
            }
            badge.textContent = String(count);
        } else if (badge) {
            badge.remove();
        }
    }

    function pollDmStatus() {
        fetch('/src/ChatApi.php?action=dm_status', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var count = parseInt((data && data.unread_total) || 0, 10);
                updateDmBadge(count);
                if (previousUnread !== null && count > previousUnread) {
                    playDmSound();
                }
                previousUnread = count;
            })
            .catch(function () {});
    }

    document.addEventListener('DOMContentLoaded', function () {
        pollDmStatus();
        setInterval(pollDmStatus, 15000);
    });
})();
