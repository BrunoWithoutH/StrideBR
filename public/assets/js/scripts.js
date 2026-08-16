document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-nav-toggle]');
    const menu = document.querySelector('[data-nav-menu]');
    if (toggle && menu) {
        toggle.addEventListener('click', () => {
            const open = menu.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    document.addEventListener('click', event => {
        document.querySelectorAll('.user-menu[open]').forEach(details => {
            if (!details.contains(event.target)) details.removeAttribute('open');
        });
    });

    const moreToggle = document.querySelector('[data-mobile-more-toggle]');
    const moreSheet = document.querySelector('[data-mobile-more-sheet]');
    const closeMore = () => {
        if (!moreSheet) return;
        moreSheet.hidden = true;
        moreToggle?.setAttribute('aria-expanded', 'false');
        document.documentElement.classList.remove('mobile-sheet-open');
    };
    moreToggle?.addEventListener('click', () => {
        if (!moreSheet) return;
        const next = moreSheet.hidden;
        moreSheet.hidden = !next;
        moreToggle.setAttribute('aria-expanded', next ? 'true' : 'false');
        document.documentElement.classList.toggle('mobile-sheet-open', next);
    });
    document.querySelectorAll('[data-mobile-more-close]').forEach(button => button.addEventListener('click', closeMore));
    moreSheet?.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMore));
    moreSheet?.querySelector('[data-quick-tools-open]')?.addEventListener('click', closeMore);

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeMore();
    });
});
