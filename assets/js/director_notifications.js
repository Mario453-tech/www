/**
 * director_notifications.js obsuga powiadomie dyrektora
 * Wymaga: window.CSRF_TOKEN (inline config w director_notifications.php)
 */
function markNotificationRead(notificationId) {
    fetch('/api/notifications/mark-read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            notification_id: notificationId,
            csrf_token: CSRF_TOKEN
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const notificationEl = document.querySelector(`[data-notification-id="${notificationId}"]`);
            if (notificationEl) {
                notificationEl.style.opacity = '0.5';
                notificationEl.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    notificationEl.remove();
                    updateNotificationCount();
                }, 300);
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

function markAllNotificationsRead() {
    confirmAction('Oznaczy� wszystkie komunikaty jako przeczytane?', function () {
        fetch('/api/notifications/mark-all-read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                csrf_token: CSRF_TOKEN
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof window.showGameToast === 'function') {
                    window.showGameToast('Komunikaty', 'Wszystkie komunikaty oznaczono jako przeczytane.', 'success');
                }
                location.reload();
            }
        })
        .catch(error => console.error('Error:', error));
    }, { type: 'confirm', confirmLabel: 'Oznacz' });
}

function updateNotificationCount() {
    const remaining = document.querySelectorAll('.notification-item').length;
    const countEl = document.querySelector('.notifications-count');
    if (countEl) {
        countEl.textContent = remaining;
    }

    if (remaining === 0) {
        const panel = document.getElementById('director-notifications');
        if (panel) {
            panel.style.opacity = '0';
            setTimeout(() => panel.remove(), 300);
        }
    }
}
