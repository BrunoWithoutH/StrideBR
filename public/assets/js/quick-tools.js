(() => {
    const root = document.querySelector('[data-global-tools]');
    if (!root) return;

    const modal = root.querySelector('[data-quick-tools-modal]');
    const tabs = [...root.querySelectorAll('[data-quick-tool-tab]')];
    const views = [...root.querySelectorAll('[data-quick-tool-view]')];
    const storageKey = 'stridebr.quickTools.v2';
    const defaultState = {
        pins: ['timer'],
        sets: 0,
        timer: {durationMs: 60000, remainingMs: 60000, running: false, endsAt: 0},
        stopwatch: {elapsedMs: 0, running: false, startedAt: 0}
    };

    const load = () => {
        try {
            const parsed = JSON.parse(localStorage.getItem(storageKey) || '{}');
            return {
                pins: Array.isArray(parsed.pins) ? parsed.pins.filter(value => ['timer', 'stopwatch', 'sets'].includes(value)) : defaultState.pins,
                sets: Number.isFinite(parsed.sets) ? Math.max(0, parsed.sets) : 0,
                timer: {...defaultState.timer, ...(parsed.timer || {})},
                stopwatch: {...defaultState.stopwatch, ...(parsed.stopwatch || {})}
            };
        } catch (_) {
            return structuredClone(defaultState);
        }
    };

    let state = load();
    let activeTool = 'timer';
    let lastTimerPositive = true;
    const save = () => localStorage.setItem(storageKey, JSON.stringify(state));
    const pad = number => String(number).padStart(2, '0');
    const formatTimer = ms => {
        const total = Math.max(0, Math.ceil(ms / 1000));
        const minutes = Math.floor(total / 60);
        const seconds = total % 60;
        return `${pad(minutes)}:${pad(seconds)}`;
    };
    const formatStopwatch = ms => {
        const totalTenths = Math.floor(Math.max(0, ms) / 100);
        const tenths = totalTenths % 10;
        const totalSeconds = Math.floor(totalTenths / 10);
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        const hours = Math.floor(minutes / 60);
        if (hours > 0) return `${pad(hours)}:${pad(minutes % 60)}:${pad(seconds)}.${tenths}`;
        return `${pad(minutes)}:${pad(seconds)}.${tenths}`;
    };

    const timerOutput = root.querySelector('[data-quick-timer-output]');
    const timerMinutes = root.querySelector('[data-quick-timer-minutes]');
    const timerSeconds = root.querySelector('[data-quick-timer-seconds]');
    const timerStart = root.querySelector('[data-quick-timer-start]');
    const timerPause = root.querySelector('[data-quick-timer-pause]');
    const timerAlarm = root.querySelector('[data-quick-timer-alarm]');
    const stopwatchOutput = root.querySelector('[data-stopwatch-output]');
    const stopwatchToggle = root.querySelector('[data-stopwatch-toggle]');
    const setsOutput = root.querySelector('[data-sets-output]');
    const pinnedContainer = root.querySelector('[data-pinned-tools]');

    const timerRemaining = () => state.timer.running ? Math.max(0, state.timer.endsAt - Date.now()) : Math.max(0, state.timer.remainingMs);
    const stopwatchElapsed = () => state.stopwatch.running ? state.stopwatch.elapsedMs + Math.max(0, Date.now() - state.stopwatch.startedAt) : state.stopwatch.elapsedMs;

    const syncTimerInputs = ms => {
        if (!timerMinutes || !timerSeconds) return;
        const secondsTotal = Math.max(0, Math.round(ms / 1000));
        timerMinutes.value = String(Math.floor(secondsTotal / 60));
        timerSeconds.value = String(secondsTotal % 60);
    };

    const renderPins = () => {
        root.querySelectorAll('[data-pin-tool]').forEach(button => {
            const pinned = state.pins.includes(button.dataset.pinTool);
            button.textContent = pinned ? '★' : '☆';
            button.classList.toggle('is-pinned', pinned);
            button.setAttribute('aria-pressed', pinned ? 'true' : 'false');
        });
        if (!pinnedContainer) return;
        const labels = {timer: 'Timer', stopwatch: 'Cronômetro', sets: 'Sets'};
        const icons = {timer: '⏱', stopwatch: '◷', sets: '#'};
        pinnedContainer.innerHTML = '';
        state.pins.forEach(tool => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'pinned-tool-chip';
            button.dataset.openPinnedTool = tool;
            button.innerHTML = `<span>${icons[tool]}</span><strong>${labels[tool]}</strong>`;
            button.addEventListener('click', () => open(tool));
            pinnedContainer.appendChild(button);
        });
    };

    const render = () => {
        const remaining = timerRemaining();
        if (timerOutput) timerOutput.value = formatTimer(remaining);
        if (timerStart) timerStart.textContent = state.timer.running ? 'Rodando' : (remaining > 0 && remaining < state.timer.durationMs ? 'Continuar' : 'Iniciar');
        if (timerStart) timerStart.disabled = state.timer.running;
        if (timerPause) timerPause.disabled = !state.timer.running;
        if (stopwatchOutput) stopwatchOutput.value = formatStopwatch(stopwatchElapsed());
        if (stopwatchToggle) stopwatchToggle.textContent = state.stopwatch.running ? 'Pausar' : (state.stopwatch.elapsedMs > 0 ? 'Continuar' : 'Iniciar');
        if (setsOutput) setsOutput.value = String(state.sets);

        if (state.timer.running && remaining <= 0) {
            state.timer.running = false;
            state.timer.remainingMs = 0;
            state.timer.endsAt = 0;
            save();
            if (lastTimerPositive) timerAlarm?.play().catch(() => {});
        }
        lastTimerPositive = remaining > 0;
    };

    const activate = tool => {
        activeTool = ['timer', 'stopwatch', 'sets'].includes(tool) ? tool : 'timer';
        tabs.forEach(tab => tab.classList.toggle('is-active', tab.dataset.quickToolTab === activeTool));
        views.forEach(view => {
            const active = view.dataset.quickToolView === activeTool;
            view.hidden = !active;
            view.classList.toggle('is-active', active);
        });
    };

    const open = (tool = activeTool) => {
        activate(tool);
        if (modal) modal.hidden = false;
        document.documentElement.classList.add('quick-tools-open');
        modal?.querySelector('[data-quick-tools-close]')?.focus();
    };
    const close = () => {
        if (modal) modal.hidden = true;
        document.documentElement.classList.remove('quick-tools-open');
    };

    root.querySelectorAll('[data-quick-tools-open]').forEach(button => button.addEventListener('click', () => open(button.dataset.quickTool || activeTool)));
    document.querySelectorAll('[data-quick-tools-open]').forEach(button => {
        if (root.contains(button)) return;
        button.addEventListener('click', () => open(button.dataset.quickTool || activeTool));
    });
    root.querySelectorAll('[data-quick-tools-close]').forEach(button => button.addEventListener('click', close));
    tabs.forEach(tab => tab.addEventListener('click', () => activate(tab.dataset.quickToolTab)));

    root.querySelectorAll('[data-pin-tool]').forEach(button => button.addEventListener('click', () => {
        const tool = button.dataset.pinTool;
        if (!tool) return;
        state.pins = state.pins.includes(tool) ? state.pins.filter(item => item !== tool) : [...state.pins, tool];
        save();
        renderPins();
    }));

    const timerFromInputs = () => {
        const minutes = Math.max(0, Number.parseInt(timerMinutes?.value || '0', 10) || 0);
        const seconds = Math.min(59, Math.max(0, Number.parseInt(timerSeconds?.value || '0', 10) || 0));
        return (minutes * 60 + seconds) * 1000;
    };
    const setTimerDuration = ms => {
        const clean = Math.max(0, ms);
        state.timer.durationMs = clean;
        state.timer.remainingMs = clean;
        state.timer.running = false;
        state.timer.endsAt = 0;
        syncTimerInputs(clean);
        save();
        render();
    };
    root.querySelectorAll('[data-timer-preset]').forEach(button => button.addEventListener('click', () => setTimerDuration(Number(button.dataset.timerPreset || 0) * 1000)));
    timerMinutes?.addEventListener('input', () => { if (!state.timer.running) setTimerDuration(timerFromInputs()); });
    timerSeconds?.addEventListener('input', () => { if (!state.timer.running) setTimerDuration(timerFromInputs()); });
    timerStart?.addEventListener('click', () => {
        let remaining = timerRemaining();
        if (remaining <= 0) remaining = timerFromInputs();
        if (remaining <= 0) return;
        state.timer.durationMs = Math.max(state.timer.durationMs, remaining);
        state.timer.remainingMs = remaining;
        state.timer.endsAt = Date.now() + remaining;
        state.timer.running = true;
        save();
        render();
    });
    timerPause?.addEventListener('click', () => {
        state.timer.remainingMs = timerRemaining();
        state.timer.running = false;
        state.timer.endsAt = 0;
        save();
        render();
    });
    root.querySelector('[data-quick-timer-reset]')?.addEventListener('click', () => {
        timerAlarm?.pause();
        if (timerAlarm) timerAlarm.currentTime = 0;
        setTimerDuration(timerFromInputs() || state.timer.durationMs || 60000);
    });
    root.querySelector('[data-quick-timer-plus]')?.addEventListener('click', () => {
        const remaining = timerRemaining() + 30000;
        state.timer.remainingMs = remaining;
        if (state.timer.running) state.timer.endsAt = Date.now() + remaining;
        state.timer.durationMs = Math.max(state.timer.durationMs, remaining);
        save();
        render();
    });

    stopwatchToggle?.addEventListener('click', () => {
        if (state.stopwatch.running) {
            state.stopwatch.elapsedMs = stopwatchElapsed();
            state.stopwatch.running = false;
            state.stopwatch.startedAt = 0;
        } else {
            state.stopwatch.running = true;
            state.stopwatch.startedAt = Date.now();
        }
        save();
        render();
    });
    root.querySelector('[data-stopwatch-reset]')?.addEventListener('click', () => {
        state.stopwatch = {...defaultState.stopwatch};
        save();
        render();
    });

    root.querySelector('[data-sets-plus]')?.addEventListener('click', () => { state.sets += 1; save(); render(); });
    root.querySelector('[data-sets-minus]')?.addEventListener('click', () => { state.sets = Math.max(0, state.sets - 1); save(); render(); });
    root.querySelector('[data-sets-reset]')?.addEventListener('click', () => { state.sets = 0; save(); render(); });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && modal && !modal.hidden) close();
    });

    if (state.timer.running && timerRemaining() <= 0) {
        state.timer.running = false;
        state.timer.remainingMs = 0;
        state.timer.endsAt = 0;
    }
    if (!state.timer.running) syncTimerInputs(state.timer.remainingMs || state.timer.durationMs || 60000);
    renderPins();
    render();
    window.setInterval(render, 100);

    window.StrideBRQuickTools = {open, close, setTimer: seconds => { setTimerDuration(Math.max(0, Number(seconds) || 0) * 1000); open('timer'); }};
})();
