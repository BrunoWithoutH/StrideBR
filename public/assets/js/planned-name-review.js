(() => {
    document.querySelectorAll('[data-open-name-mapping]').forEach(button => button.addEventListener('click', () => {
        const form = button.closest('[data-exercise-row]')?.querySelector('.planned-name-mapping');
        if (!form) return;
        form.hidden = !form.hidden;
        button.setAttribute('aria-expanded', String(!form.hidden));
        if (!form.hidden) form.querySelector('[data-exercise-name]')?.focus();
    }));
})();
