'use strict';
(() => {
    const t = (key, values = {}, fallback = key) => window.StrideBRI18n?.t?.(key, values, fallback) ?? fallback
    let controller = null;
    let navigating = false;

    const page = () => document.querySelector('[data-progress-page]');
    const isProgressUrl = value => {
        try {
            return new URL(value, window.location.href).pathname === '/user/progresso.php';
        } catch {
            return false;
        }
    };
    const shouldHandleLink = (link, event) => {
        if (!link || !isProgressUrl(link.href)) return false;
        if (link.target && link.target !== '_self') return false;
        if (event && (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0)) return false;
        return true;
    };
    const setLoading = active => {
        const root = page();
        if (!root) return;
        root.classList.toggle('is-progress-loading', active);
        if (active) root.setAttribute('aria-busy', 'true');
        else root.removeAttribute('aria-busy');
    };
    const navigate = async (target, push = true) => {
        if (navigating) controller?.abort();
        const url = new URL(target, window.location.href);
        controller = new AbortController();
        navigating = true;
        setLoading(true);
        const scrollY = window.scrollY;
        try {
            const request = window.StrideBRNet?.fetch || fetch;
            const response = await request(url.toString(), {
                headers: {'Accept': 'text/html', 'X-StrideBR-Partial': 'progress'},
                credentials: 'same-origin',
                signal: controller.signal
            }, 14000);
            if (response.redirected && !isProgressUrl(response.url)) {
                window.location.href = response.url;
                return;
            }
            if (!response.ok) throw new Error(response.status >= 500 ? t('progress.load_period_error', {}, 'Could not load this period right now.') : t('progress.open_view_error', {}, 'Could not open this progress view.'));
            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const next = doc.querySelector('[data-progress-page]');
            const current = page();
            if (!next || !current) throw new Error(t('progress.incomplete_response', {}, 'The Progress response was incomplete.'));
            current.replaceWith(next);
            if (doc.title) document.title = doc.title;
            if (push) history.pushState({progress: true}, '', `${url.pathname}${url.search}${url.hash}`);
            requestAnimationFrame(() => window.scrollTo({top: scrollY, behavior: 'auto'}));
        } catch (error) {
            if (error?.name === 'AbortError') return;
            window.StrideBRUI?.notify(error?.message || t('progress.update_error', {}, 'Could not update Progress.'), 'error', 6000);
        } finally {
            navigating = false;
            controller = null;
            setLoading(false);
        }
    };

    document.addEventListener('click', event => {
        const link = event.target.closest?.('[data-progress-page] a[href]');
        if (!shouldHandleLink(link, event)) return;
        event.preventDefault();
        navigate(link.href, true);
    });

    document.addEventListener('submit', event => {
        const form = event.target.closest?.('[data-progress-page] .progress-period-month');
        if (!form) return;
        event.preventDefault();
        const url = new URL(form.action || '/user/progresso.php', window.location.href);
        const params = new URLSearchParams(new FormData(form));
        url.search = params.toString();
        navigate(url, true);
    });

    document.addEventListener('change', event => {
        const month = event.target.closest?.('[data-progress-page] .progress-period-month input[type="month"]');
        if (!month?.form || !month.value) return;
        month.form.requestSubmit();
    });

    window.addEventListener('popstate', () => {
        if (isProgressUrl(window.location.href)) navigate(window.location.href, false);
    });
})();
