(() => {
    let shown = false;
    let indicator = null;
    let pendingTimer = 0;

    const ensureIndicator = () => {
        if (indicator) return indicator;
        indicator = document.createElement('div');
        indicator.className = 'page-load-indicator';
        indicator.hidden = true;
        indicator.setAttribute('aria-live', 'polite');
        indicator.setAttribute('aria-busy', 'true');
        indicator.innerHTML = '<div class="page-load-bar"></div>';
        document.body.append(indicator);
        return indicator;
    };

    const show = () => {
        if (shown) return;
        shown = true;
        ensureIndicator().hidden = false;
    };

    const scheduleShow = () => {
        window.clearTimeout(pendingTimer);
        pendingTimer = window.setTimeout(show, 140);
    };

    const shouldHandleLink = link => {
        if (!link || link.target === '_blank' || link.hasAttribute('download') || link.hasAttribute('data-no-page-loading')) return false;
        const href = link.getAttribute('href') || '';
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return false;
        try {
            const url = new URL(link.href, window.location.href);
            return url.origin === window.location.origin && url.href !== window.location.href;
        } catch (_) {
            return false;
        }
    };

    document.addEventListener('click', event => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('a[href]');
        if (!shouldHandleLink(link)) return;
        window.setTimeout(() => {
            if (!event.defaultPrevented) scheduleShow();
        }, 0);
    });

    document.addEventListener('submit', event => {
        if (event.defaultPrevented) return;
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || (form.target || '').toLowerCase() === '_blank' || form.hasAttribute('data-no-page-loading')) return;
        scheduleShow();
    });

    window.addEventListener('pageshow', () => {
        window.clearTimeout(pendingTimer);
        shown = false;
        if (indicator) indicator.hidden = true;
    });
})();
