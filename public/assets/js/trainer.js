(() => {
    const t = (key, values = {}, fallback = key) => window.StrideBRI18n?.t(key, values, fallback) ?? fallback;
    let prescriptionTrigger = null;
    const prescriptionModal = document.querySelector('[data-prescription-modal]');
    const openPrescriptionButtons = document.querySelectorAll('[data-open-prescription]');
    const closePrescriptionButtons = prescriptionModal?.querySelectorAll('[data-close-prescription]') || [];
    if (prescriptionModal && prescriptionModal.parentElement !== document.body) document.body.appendChild(prescriptionModal);
    const setPrescriptionOpen = open => {
        if (!prescriptionModal) return;
        prescriptionModal.hidden = !open;
        document.documentElement.classList.toggle('trainer-prescription-open', open);
        if (!open) prescriptionTrigger?.focus();
        if (open) requestAnimationFrame(() => prescriptionModal.querySelector('input[name="titulo"]')?.focus());
    };
    openPrescriptionButtons.forEach(button => button.addEventListener('click', () => { prescriptionTrigger = button; setPrescriptionOpen(true); }));
    closePrescriptionButtons.forEach(button => button.addEventListener('click', () => setPrescriptionOpen(false)));
    document.addEventListener('keydown', event => {
        if (event.key === 'Tab' && prescriptionModal && !prescriptionModal.hidden) {
            const fields = [...prescriptionModal.querySelector('[role=dialog]').querySelectorAll('button, input, select, textarea, a[href]')].filter(el => !el.disabled && el.getClientRects().length);
            const first = fields[0], last = fields[fields.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        }
        if (event.key === 'Escape' && prescriptionModal && !prescriptionModal.hidden) setPrescriptionOpen(false);
    });

    const exerciseList = document.querySelector('[data-prescription-exercises]');
    const addButton = document.querySelector('[data-add-prescription-exercise]');

    const bindRemove = row => {
        row.querySelector('[data-remove-prescription-exercise]')?.addEventListener('click', () => {
            const rows = exerciseList?.querySelectorAll('[data-prescription-exercise]') || [];
            if (rows.length <= 1) {
                row.querySelectorAll('input').forEach(input => { input.value = ''; });
                return;
            }
            const next = row.nextElementSibling;
            row.remove();
            window.StrideBRUI?.undo?.(t('trainer.exercise_removed_undo', {}, 'Exercise removed from prescription.'), async () => {
                if (next?.isConnected) exerciseList.insertBefore(row, next);
                else exerciseList.appendChild(row);
            });
        });
    };

    if (exerciseList) {
        exerciseList.querySelectorAll('[data-prescription-exercise]').forEach(bindRemove);
    }

    addButton?.addEventListener('click', () => {
        if (!exerciseList) return;
        const source = exerciseList.querySelector('[data-prescription-exercise]');
        if (!source || exerciseList.querySelectorAll('[data-prescription-exercise]').length >= 100) return;
        const row = source.cloneNode(true);
        row.querySelectorAll('input').forEach(input => { input.value = ''; });
        bindRemove(row);
        exerciseList.appendChild(row);
        row.querySelector('input')?.focus();
    });

    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-start-scheduled-workout]');
        if (!button || button.dataset.startScheduledBusy === '1') return;
        const id = button.dataset.startScheduledWorkout || '';
        if (!id || !window.StrideBRWorkout?.startScheduled) return;
        button.dataset.startScheduledBusy = '1';
        button.disabled = true;
        try {
            await window.StrideBRWorkout.startScheduled(id);
        } catch (_) {
        } finally {
            delete button.dataset.startScheduledBusy;
            button.disabled = false;
        }
    });
})();
