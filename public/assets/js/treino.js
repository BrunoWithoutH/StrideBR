document.addEventListener('DOMContentLoaded', () => {
    const counterOutput = document.querySelector('[data-counter-output]');
    let counter = 0;
    const renderCounter = () => {
        if (counterOutput) counterOutput.value = String(counter);
    };
    document.querySelector('[data-counter-plus]')?.addEventListener('click', () => {
        counter++;
        renderCounter();
    });
    document.querySelector('[data-counter-minus]')?.addEventListener('click', () => {
        counter--;
        renderCounter();
    });
    document.querySelector('[data-counter-reset]')?.addEventListener('click', () => {
        counter = 0;
        renderCounter();
    });

    const minutesInput = document.querySelector('[data-timer-minutes]');
    const secondsInput = document.querySelector('[data-timer-seconds]');
    const timerOutput = document.querySelector('[data-timer-output]');
    const startButton = document.querySelector('[data-timer-start]');
    const pauseButton = document.querySelector('[data-timer-pause]');
    const resetButton = document.querySelector('[data-timer-reset]');
    const alarm = document.querySelector('[data-timer-alarm]');
    let intervalId = null;
    let remaining = 60;
    let initial = 60;
    let running = false;

    const inputSeconds = () => {
        const minutes = Math.max(0, Number.parseInt(minutesInput?.value || '0', 10) || 0);
        const seconds = Math.min(59, Math.max(0, Number.parseInt(secondsInput?.value || '0', 10) || 0));
        return minutes * 60 + seconds;
    };

    const renderTimer = () => {
        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;
        if (timerOutput) timerOutput.value = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        if (startButton) startButton.disabled = running;
        if (pauseButton) pauseButton.disabled = !running;
    };

    const stopInterval = () => {
        if (intervalId !== null) {
            clearInterval(intervalId);
            intervalId = null;
        }
        running = false;
        renderTimer();
    };

    const finish = () => {
        stopInterval();
        alarm?.play().catch(() => {});
    };

    const syncFromInputs = () => {
        if (running) return;
        initial = inputSeconds();
        remaining = initial;
        renderTimer();
    };

    minutesInput?.addEventListener('input', syncFromInputs);
    secondsInput?.addEventListener('input', syncFromInputs);

    startButton?.addEventListener('click', () => {
        if (running) return;
        if (remaining <= 0) {
            initial = inputSeconds();
            remaining = initial;
        }
        if (remaining <= 0) return;
        running = true;
        renderTimer();
        intervalId = window.setInterval(() => {
            remaining--;
            renderTimer();
            if (remaining <= 0) finish();
        }, 1000);
    });

    pauseButton?.addEventListener('click', stopInterval);
    resetButton?.addEventListener('click', () => {
        stopInterval();
        alarm?.pause();
        if (alarm) alarm.currentTime = 0;
        initial = inputSeconds();
        remaining = initial;
        renderTimer();
    });

    syncFromInputs();
});
