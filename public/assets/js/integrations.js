(() => {
    'use strict';
    const menus = document.querySelectorAll('.integration-menu');
    menus.forEach(menu => {
        menu.addEventListener('toggle', () => {
            if (menu.open) menus.forEach(other => { if (other !== menu) other.open = false; });
        });
        menu.addEventListener('keydown', event => {
            if (event.key === 'Escape' && menu.open) {
                menu.open = false;
                menu.querySelector('summary').focus();
                event.stopPropagation();
            }
        });
    });
    document.addEventListener('click', event => {
        menus.forEach(menu => { if (menu.open && !menu.contains(event.target)) menu.open = false; });
    });
    document.querySelectorAll('[data-integration-sync]').forEach(form => {
        form.addEventListener('submit', event => {
            if (form.dataset.submitting) { event.preventDefault(); return; }
            form.dataset.submitting = 'true';
            const card = form.closest('.integration-card');
            const button = form.querySelector('button');
            button.disabled = true;
            button.textContent = card.dataset.syncingLabel;
            card.querySelector('.integration-status').textContent = card.dataset.syncingLabel;
            card.setAttribute('aria-busy', 'true');
        });
    });
    window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
})();
