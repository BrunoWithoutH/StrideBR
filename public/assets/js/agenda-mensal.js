const agendaT = (key, values = {}, fallback = key) => window.StrideBRI18n?.t?.(key, values, fallback) ?? fallback
(() => {
    const cache = new Map();
    let request = null;
    let navToken = 0;

    const normalizeUrl = value => {
        const url = new URL(value, window.location.href);
        url.hash = '';
        return `${url.pathname}${url.search}`;
    };

    const currentShell = () => document.querySelector('[data-monthly-dynamic]');
    const setBusy = busy => {
        const shell = currentShell();
        shell?.setAttribute('aria-busy', busy ? 'true' : 'false');
        const status = shell?.querySelector('[data-monthly-load-status]');
        if (status) status.hidden = !busy;
        shell?.querySelectorAll('[data-month-nav]').forEach(link => {
            link.classList.toggle('is-loading', busy);
            link.setAttribute('aria-disabled', busy ? 'true' : 'false');
        });
    };

    const parsePage = html => {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const shell = doc.querySelector('[data-monthly-dynamic]');
        if (!shell) throw new Error(agendaT('agenda.invalid_response', {}, 'Invalid agenda response.'));
        return {
            shell,
            title: doc.querySelector('[data-monthly-page-title]')?.textContent || agendaT('agenda.page_title'),
            documentTitle: doc.title || document.title,
            contextLink: doc.querySelector('.planning-subnav-context')?.outerHTML || '',
        };
    };

    const installPage = parsed => {
        const shell = currentShell();
        if (!shell) return;
        const imported = document.importNode(parsed.shell, true);
        shell.replaceWith(imported);
        const heading = document.querySelector('[data-monthly-page-title]');
        if (heading) heading.textContent = parsed.title;
        document.title = parsed.documentTitle;
        const nav = document.querySelector('.monthly-planning-subnav');
        const currentContext = nav?.querySelector('.planning-subnav-context');
        currentContext?.remove();
        if (nav && parsed.contextLink) nav.insertAdjacentHTML('beforeend', parsed.contextLink);
        window.StrideBRTime24?.bindAll?.(imported);
    };

    const fetchPage = async url => {
        const key = normalizeUrl(url);
        if (cache.has(key)) return cache.get(key);
        const response = await fetch(key, {headers: {'Accept': 'text/html'}, credentials: 'same-origin'});
        if (!response.ok) throw new Error(agendaT('agenda.load_month_error', {}, 'Could not load this month.'));
        const html = await response.text();
        const parsed = parsePage(html);
        cache.set(key, parsed);
        return parsed;
    };

    const prefetchNeighbors = () => {
        const shell = currentShell();
        shell?.querySelectorAll('[data-month-nav]').forEach(link => {
            const href = link.getAttribute('href');
            if (!href) return;
            const key = normalizeUrl(href);
            if (cache.has(key)) return;
            window.setTimeout(() => fetchPage(key).catch(() => {}), 120);
        });
    };

    const navigate = async (url, {historyMode = 'push'} = {}) => {
        const key = normalizeUrl(url);
        const token = ++navToken;
        request?.abort?.();
        request = new AbortController();
        setBusy(true);
        try {
            let parsed = cache.get(key);
            if (!parsed) {
                const response = await fetch(key, {
                    headers: {'Accept': 'text/html'},
                    credentials: 'same-origin',
                    signal: request.signal,
                });
                if (!response.ok) throw new Error(agendaT('agenda.load_month_error', {}, 'Could not load this month.'));
                parsed = parsePage(await response.text());
                cache.set(key, parsed);
            }
            if (token !== navToken) return;
            installPage(parsed);
            if (historyMode === 'push') history.pushState({stridebrAgenda: key}, '', key);
            else if (historyMode === 'replace') history.replaceState({stridebrAgenda: key}, '', key);
            prefetchNeighbors();
        } catch (error) {
            if (error?.name === 'AbortError') return;
            window.StrideBRUI?.notify?.(error?.message || agendaT('agenda.load_month_error', {}, 'Could not load this month.'), 'error');
        } finally {
            if (token === navToken) setBusy(false);
        }
    };

    document.addEventListener('click', event => {
        const nav = event.target.closest('[data-month-nav]');
        if (nav) {
            event.preventDefault();
            if (nav.getAttribute('aria-disabled') === 'true') return;
            navigate(nav.href, {historyMode: 'push'});
            return;
        }

        const planned = event.target.closest('[data-edit-schedule-history]');
        if (planned) {
            const modal = document.querySelector('[data-planned-date-modal]');
            if (!modal) return;
            const id = modal.querySelector('[data-planned-activity-id]');
            const plannedDate = modal.querySelector('[data-planned-date-input]');
            const plannedTime = modal.querySelector('[data-planned-time-input]');
            const realizedDate = modal.querySelector('[data-realized-date-input]');
            const realizedTime = modal.querySelector('[data-realized-time-input]');
            if (id) id.value = planned.dataset.activityId || '';
            if (plannedDate) plannedDate.value = planned.dataset.plannedDate || planned.dataset.realizedDate || '';
            if (plannedTime) {
                plannedTime.value = planned.dataset.plannedTime || planned.dataset.realizedTime || '';
                window.StrideBRTime24?.set?.(plannedTime, plannedTime.value);
            }
            if (realizedDate) realizedDate.value = planned.dataset.realizedDate || planned.dataset.plannedDate || '';
            if (realizedTime) {
                realizedTime.value = planned.dataset.realizedTime || planned.dataset.plannedTime || '';
                window.StrideBRTime24?.set?.(realizedTime, realizedTime.value);
            }
            modal.hidden = false;
            document.documentElement.style.overflow = 'hidden';
            plannedDate?.focus();
            return;
        }

        if (event.target.closest('[data-close-planned-date]')) {
            const modal = document.querySelector('[data-planned-date-modal]');
            if (modal) modal.hidden = true;
            document.documentElement.style.overflow = '';
        }
    });

    document.addEventListener('change', event => {
        const select = event.target.closest('.monthly-filter select[name="cronograma"]');
        if (!select) return;
        const form = select.form;
        if (!form) return;
        const url = new URL(window.location.href);
        const value = String(select.value || '');
        if (value) url.searchParams.set('cronograma', value);
        else url.searchParams.delete('cronograma');
        const month = form.querySelector('input[name="month"]')?.value;
        const athlete = form.querySelector('input[name="atleta"]')?.value;
        if (month) url.searchParams.set('month', month);
        if (athlete) url.searchParams.set('atleta', athlete);
        navigate(url, {historyMode: 'push'});
    });

    document.addEventListener('submit', async event => {
        const form = event.target.closest('[data-planned-date-form]');
        if (!form) return;
        event.preventDefault();
        const submit = form.querySelector('button[type="submit"]');
        if (submit) submit.disabled = true;
        try {
            const body = new URLSearchParams(new FormData(form));
            const response = await (window.StrideBRNet?.fetch || fetch)('/api/cronograma-ocorrencias.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'Accept': 'application/json'},
                credentials: 'same-origin',
                body,
            }, 15000);
            const data = await response.json().catch(() => null);
            if (!response.ok || !data?.ok) throw new Error(data?.error || agendaT('agenda.fix_dates_error', {}, 'Could not fix dates and times.'));
            const modal = document.querySelector('[data-planned-date-modal]');
            if (modal) modal.hidden = true;
            document.documentElement.style.overflow = '';
            cache.delete(normalizeUrl(window.location.href));
            await navigate(window.location.href, {historyMode: 'replace'});
            window.StrideBRUI?.notify?.(agendaT('agenda.fix_dates_success', {}, 'Planning and completion dates corrected.'), 'success');
        } catch (error) {
            window.StrideBRUI?.notify?.(error?.message || agendaT('agenda.fix_dates_error', {}, 'Could not fix dates and times.'), 'error');
        } finally {
            if (submit) submit.disabled = false;
        }
    });

    window.addEventListener('popstate', () => navigate(window.location.href, {historyMode: 'none'}));
    history.replaceState({stridebrAgenda: normalizeUrl(window.location.href)}, '', window.location.href);
    prefetchNeighbors();
})();
