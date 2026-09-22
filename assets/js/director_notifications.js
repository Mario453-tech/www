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
    .then(async response => {
        const data = await response.json();
        if (!response.ok || data.success !== true) throw new Error('Notification update failed');
        return data;
    })
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
    .catch(() => alertError(window.MODAL_LANG.title_error));
}

function markAllNotificationsRead() {
    confirmAction(document.querySelector('#director-notifications .btn-mark-all-read').textContent.trim() + '?', function () {
        fetch('/api/notifications/mark-all-read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                csrf_token: CSRF_TOKEN
            })
        })
        .then(async response => {
            const data = await response.json();
            if (!response.ok || data.success !== true) throw new Error('Notification update failed');
            return data;
        })
        .then(data => {
            if (data.success) {
                if (typeof window.showGameToast === 'function') {
                    window.showGameToast('', window.MODAL_LANG.title_success, 'success');
                }
                location.reload();
            }
        })
        .catch(() => alertError(window.MODAL_LANG.title_error));
    }, { type: 'confirm', confirmLabel: window.MODAL_LANG.confirm });
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
