(() => {
    'use strict';

    const header = document.querySelector('[data-home-header]');
    const menuButton = document.querySelector('[data-home-menu]');
    const navigation = document.querySelector('[data-home-nav]');

    const closeMenu = () => {
        if (!header || !menuButton) return;
        header.classList.remove('is-open');
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.setAttribute('aria-label', menuButton.dataset.openLabel || menuButton.getAttribute('aria-label'));
    };

    if (header && menuButton && navigation) {
        menuButton.addEventListener('click', () => {
            const open = !header.classList.contains('is-open');
            header.classList.toggle('is-open', open);
            menuButton.setAttribute('aria-expanded', String(open));
            menuButton.setAttribute('aria-label', open
                ? (menuButton.dataset.closeLabel || menuButton.getAttribute('aria-label'))
                : (menuButton.dataset.openLabel || menuButton.getAttribute('aria-label')));
        });
        navigation.addEventListener('click', event => {
            if (event.target.closest('a')) closeMenu();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && header.classList.contains('is-open')) {
                closeMenu();
                menuButton.focus();
            }
        });
    }

    const language = document.querySelector('[data-home-language]');
    if (language) {
        language.addEventListener('change', () => language.form?.requestSubmit());
    }

    const tabs = Array.from(document.querySelectorAll('[data-home-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-home-panel]'));

    const activateTab = tab => {
        const name = tab.dataset.homeTab;
        tabs.forEach(item => {
            const active = item === tab;
            item.setAttribute('aria-selected', String(active));
            item.tabIndex = active ? 0 : -1;
        });
        panels.forEach(panel => {
            panel.hidden = panel.dataset.homePanel !== name;
        });
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activateTab(tab));
        tab.addEventListener('keydown', event => {
            let targetIndex = null;
            if (event.key === 'ArrowRight') targetIndex = (index + 1) % tabs.length;
            if (event.key === 'ArrowLeft') targetIndex = (index - 1 + tabs.length) % tabs.length;
            if (event.key === 'Home') targetIndex = 0;
            if (event.key === 'End') targetIndex = tabs.length - 1;
            if (targetIndex !== null) {
                event.preventDefault();
                activateTab(tabs[targetIndex]);
                tabs[targetIndex].focus();
            }
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                activateTab(tab);
            }
        });
    });
    if (tabs.length > 0) activateTab(tabs[0]);

    const dialog = document.querySelector('#home-screenshot-dialog');
    const dialogImage = dialog?.querySelector('[data-home-preview-image]');
    const dialogClose = dialog?.querySelector('[data-home-preview-close]');
    let previewTrigger = null;

    document.querySelectorAll('[data-home-preview-src]').forEach(trigger => {
        trigger.addEventListener('click', () => {
            if (!dialog || !dialogImage || typeof dialog.showModal !== 'function') return;
            previewTrigger = trigger;
            dialogImage.src = trigger.dataset.homePreviewSrc || '';
            dialogImage.alt = trigger.dataset.homePreviewAlt || '';
            dialog.showModal();
            dialogClose?.focus();
        });
    });

    if (dialog) {
        dialogClose?.addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', event => {
            if (event.target === dialog) dialog.close();
        });
        dialog.addEventListener('close', () => {
            dialogImage?.removeAttribute('src');
            previewTrigger?.focus();
            previewTrigger = null;
        });
    }
})();
