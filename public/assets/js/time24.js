(() => {
    const roots = new Set();

    const normalize = (value, max) => {
        const digits = String(value ?? '').replace(/\D/g, '').slice(0, 2);
        if (digits === '') return '';
        const number = Number.parseInt(digits, 10);
        if (!Number.isFinite(number)) return '';
        return String(Math.max(0, Math.min(max, number))).padStart(2, '0');
    };

    const sync = root => {
        const hours = root.querySelector('[data-time24-hours]');
        const minutes = root.querySelector('[data-time24-minutes]');
        const value = root.querySelector('[data-time24-value]');
        if (!hours || !minutes || !value) return;
        const h = normalize(hours.value, 23);
        const m = normalize(minutes.value, 59);
        hours.value = h;
        minutes.value = m;
        value.value = h && m ? `${h}:${m}` : '';
        root.querySelectorAll('[data-time24-hour]').forEach(button => button.classList.toggle('is-selected', button.dataset.time24Hour === h));
        root.querySelectorAll('[data-time24-minute]').forEach(button => button.classList.toggle('is-selected', button.dataset.time24Minute === m));
        value.dispatchEvent(new Event('input', {bubbles: true}));
        value.dispatchEvent(new Event('change', {bubbles: true}));
    };

    const setValue = (target, time) => {
        const root = target?.closest?.('[data-time24]') || target;
        if (!root?.matches?.('[data-time24]')) return;
        const match = /^(\d{1,2}):(\d{2})$/.exec(String(time || ''));
        const hours = root.querySelector('[data-time24-hours]');
        const minutes = root.querySelector('[data-time24-minutes]');
        if (!hours || !minutes) return;
        hours.value = match ? normalize(match[1], 23) : '';
        minutes.value = match ? normalize(match[2], 59) : '';
        sync(root);
    };

    const closeMenus = except => {
        roots.forEach(root => {
            if (root === except) return;
            const menu = root.querySelector('[data-time24-menu]');
            const toggle = root.querySelector('[data-time24-toggle]');
            if (menu) menu.hidden = true;
            toggle?.setAttribute('aria-expanded', 'false');
        });
    };

    const bind = root => {
        if (!root || root.dataset.time24Bound === '1') return;
        root.dataset.time24Bound = '1';
        roots.add(root);
        const hours = root.querySelector('[data-time24-hours]');
        const minutes = root.querySelector('[data-time24-minutes]');
        const value = root.querySelector('[data-time24-value]');
        const toggle = root.querySelector('[data-time24-toggle]');
        const menu = root.querySelector('[data-time24-menu]');
        const hourGrid = root.querySelector('[data-time24-hours-grid]');
        const minuteGrid = root.querySelector('[data-time24-minutes-grid]');

        if (hourGrid && !hourGrid.children.length) {
            for (let hour = 0; hour < 24; hour++) {
                const button = document.createElement('button');
                button.type = 'button';
                button.dataset.time24Hour = String(hour).padStart(2, '0');
                button.textContent = String(hour).padStart(2, '0');
                hourGrid.appendChild(button);
            }
        }
        if (minuteGrid && !minuteGrid.children.length) {
            for (const minute of ['00', '15', '30', '45']) {
                const button = document.createElement('button');
                button.type = 'button';
                button.dataset.time24Minute = minute;
                button.textContent = minute;
                minuteGrid.appendChild(button);
            }
        }

        const normalizeInput = (input, max) => {
            input.value = normalize(input.value, max);
            sync(root);
        };

        hours?.addEventListener('input', () => {
            hours.value = String(hours.value || '').replace(/\D/g, '').slice(0, 2);
            const digits = hours.value;
            if (digits === '') return;
            // Avança direto quando um único dígito já não pode virar uma hora
            // válida (0-23) com mais um dígito na frente — ex.: "8" só poderia
            // formar "80"-"89", sempre inválido, então "8" já é o valor final.
            const partial = Number.parseInt(digits, 10);
            if (digits.length === 2 || partial * 10 > 23) minutes?.focus();
        });
        minutes?.addEventListener('input', () => {
            minutes.value = String(minutes.value || '').replace(/\D/g, '').slice(0, 2);
        });
        hours?.addEventListener('blur', () => normalizeInput(hours, 23));
        minutes?.addEventListener('blur', () => normalizeInput(minutes, 59));
        hours?.addEventListener('change', () => normalizeInput(hours, 23));
        minutes?.addEventListener('change', () => normalizeInput(minutes, 59));

        toggle?.addEventListener('click', event => {
            event.preventDefault();
            if (!menu) return;
            const opening = menu.hidden;
            closeMenus(opening ? root : null);
            menu.hidden = !opening;
            toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
        });

        menu?.addEventListener('click', event => {
            const hourButton = event.target.closest('[data-time24-hour]');
            const minuteButton = event.target.closest('[data-time24-minute]');
            if (hourButton && hours) {
                hours.value = hourButton.dataset.time24Hour || '00';
                sync(root);
                minutes?.focus();
                return;
            }
            if (minuteButton && minutes) {
                minutes.value = minuteButton.dataset.time24Minute || '00';
                sync(root);
                menu.hidden = true;
                toggle?.setAttribute('aria-expanded', 'false');
                return;
            }
            const nowButton = event.target.closest('[data-time24-now]');
            if (nowButton) {
                const now = new Date();
                if (hours) hours.value = String(now.getHours()).padStart(2, '0');
                if (minutes) minutes.value = String(now.getMinutes()).padStart(2, '0');
                sync(root);
                menu.hidden = true;
                toggle?.setAttribute('aria-expanded', 'false');
            }
        });

        const initial = value?.value || '';
        setValue(root, initial);
    };

    const bindAll = scope => scope.querySelectorAll?.('[data-time24]').forEach(bind);
    document.addEventListener('DOMContentLoaded', () => bindAll(document));
    document.addEventListener('click', event => {
        if (!event.target.closest('[data-time24]')) closeMenus(null);
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeMenus(null);
    });

    window.StrideBRTime24 = {bind, bindAll, set: setValue};
})();
