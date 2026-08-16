(() => {
    const root = document.querySelector('[data-onboarding]');
    if (!root) return;

    const steps = [...root.querySelectorAll('[data-step]')];
    const next = root.querySelector('[data-next-step]');
    const prev = root.querySelector('[data-prev-step]');
    const finish = root.querySelector('[data-finish-step]');
    const label = root.querySelector('[data-step-label]');
    const bar = root.querySelector('[data-progress-bar]');
    let index = 0;

    const render = () => {
        steps.forEach((step, i) => {
            step.hidden = i !== index;
            step.classList.toggle('is-active', i === index);
        });

        if (label) label.textContent = `${index + 1} de ${steps.length}`;
        if (bar) bar.style.width = `${((index + 1) / steps.length) * 100}%`;
        if (prev) prev.hidden = index === 0;
        if (next) next.hidden = index === steps.length - 1;
        if (finish) finish.hidden = index !== steps.length - 1;
    };

    next?.addEventListener('click', () => {
        if (index < steps.length - 1) {
            index++;
            render();
        }
    });

    prev?.addEventListener('click', () => {
        if (index > 0) {
            index--;
            render();
        }
    });

    render();
})();
