(() => {
    'use strict';
    const panel = document.getElementById('tech-notif-panel');
    if (!panel) return;
    let pending = false;
    async function mark(button) {
        if (pending) return;
        pending = true;
        const buttons = panel.querySelectorAll('[data-tech-action]');
        buttons.forEach(node => { node.disabled = true; });
        const error = panel.querySelector('.tech-notif-error');
        error.hidden = true;
        try {
            const response = await fetch('/src/TechNotifApi.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({action: button.dataset.techAction,
                    notif_id: button.dataset.techId || '', _token: panel.dataset.csrf})
            });
            const data = await response.json();
            if (!response.ok || data.success !== true) throw new Error('Notification update failed');
            if (button.dataset.techAction === 'mark_all_read') {
                panel.remove();
            } else {
                button.closest('.tech-notif-item')?.remove();
                const count = panel.querySelectorAll('.tech-notif-item').length;
                panel.querySelector('.tech-notif-count').textContent = count;
                if (!count) panel.remove();
            }
        } catch (_) {
            error.textContent = panel.dataset.error;
            error.hidden = false;
        } finally {
            pending = false;
            buttons.forEach(node => { node.disabled = false; });
        }
    }
    panel.addEventListener('click', event => {
        const button = event.target.closest('[data-tech-action]');
        if (!button || pending) return;
        if (button.dataset.confirm) {
            if (typeof window.confirmAction === 'function') {
                window.confirmAction(button.dataset.confirm, () => mark(button));
            } else {
                const error = panel.querySelector('.tech-notif-error');
                error.textContent = panel.dataset.error;
                error.hidden = false;
            }
        } else {
            mark(button);
        }
    });
})();
