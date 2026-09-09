(() => {
    const t = (key, values = {}, fallback = key) => window.StrideBRI18n?.t(key, values, fallback) ?? fallback;
    let prescriptionTrigger = null;
    let prescriptionModal = null;

    const setPrescriptionOpen = open => {
        if (!prescriptionModal) return;
        prescriptionModal.hidden = !open;
        document.documentElement.classList.toggle('trainer-prescription-open', open);
        if (!open) prescriptionTrigger?.focus();
        if (open) requestAnimationFrame(() => prescriptionModal.querySelector('input[name="titulo"]')?.focus());
    };

    const bindRemove = (row, exerciseList) => {
        const remove = row.querySelector('[data-remove-prescription-exercise]');
        if (!remove || remove.dataset.trainerBound === '1') return;
        remove.dataset.trainerBound = '1';
        remove.addEventListener('click', () => {
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

    const setupPrescription = () => {
        const nextModal = document.querySelector('[data-prescription-modal]');
        prescriptionModal = nextModal || null;
        if (prescriptionModal && prescriptionModal.parentElement !== document.body) document.body.appendChild(prescriptionModal);
        prescriptionModal?.querySelectorAll('[data-close-prescription]').forEach(button => {
            if (button.dataset.trainerBound === '1') return;
            button.dataset.trainerBound = '1';
            button.addEventListener('click', () => setPrescriptionOpen(false));
        });

        document.querySelectorAll('[data-open-prescription]').forEach(button => {
            if (button.dataset.trainerBound === '1') return;
            button.dataset.trainerBound = '1';
            button.addEventListener('click', () => {
                prescriptionTrigger = button;
                setPrescriptionOpen(true);
            });
        });

        const exerciseList = prescriptionModal?.querySelector('[data-prescription-exercises]');
        const addButton = prescriptionModal?.querySelector('[data-add-prescription-exercise]');
        exerciseList?.querySelectorAll('[data-prescription-exercise]').forEach(row => bindRemove(row, exerciseList));
        if (addButton && addButton.dataset.trainerBound !== '1') {
            addButton.dataset.trainerBound = '1';
            addButton.addEventListener('click', () => {
                if (!exerciseList) return;
                const source = exerciseList.querySelector('[data-prescription-exercise]');
                if (!source || exerciseList.querySelectorAll('[data-prescription-exercise]').length >= 100) return;
                const row = source.cloneNode(true);
                row.querySelectorAll('input').forEach(input => { input.value = ''; });
                row.querySelectorAll('[data-trainer-bound]').forEach(element => delete element.dataset.trainerBound);
                bindRemove(row, exerciseList);
                exerciseList.appendChild(row);
                row.querySelector('input')?.focus();
            });
        }
    };

    const replaceAthleteWorkspace = async (url, historyMode = 'push') => {
        const shell = document.querySelector('.trainer-shell');
        const currentCoachSection = shell?.querySelector('[data-trainer-coach-section]');
        if (!shell || !currentCoachSection) throw new Error('trainer_workspace_unavailable');
        shell.classList.add('is-refreshing');
        shell.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, {headers: {'X-Requested-With': 'fetch'}, credentials: 'same-origin'});
            if (!response.ok) throw new Error(`trainer_workspace_${response.status}`);
            const html = await response.text();
            const parsed = new DOMParser().parseFromString(html, 'text/html');
            const nextCoachSection = parsed.querySelector('[data-trainer-coach-section]');
            const nextWorkspace = parsed.querySelector('#athlete-workspace');
            if (!nextCoachSection) throw new Error('trainer_workspace_invalid');

            document.body.querySelector('[data-prescription-modal]')?.remove();
            document.querySelector('#athlete-workspace')?.remove();
            currentCoachSection.replaceWith(nextCoachSection);
            if (nextWorkspace) nextCoachSection.after(nextWorkspace);

            const currentNav = shell.querySelector('.planning-subnav');
            const nextNav = parsed.querySelector('.planning-subnav');
            if (currentNav && nextNav) currentNav.replaceWith(nextNav);
            document.title = parsed.title || document.title;
            if (historyMode === 'push') history.pushState({trainerAthlete: true}, '', url);
            else if (historyMode === 'replace') history.replaceState({trainerAthlete: true}, '', url);
            setupPrescription();
            if (nextWorkspace) requestAnimationFrame(() => document.querySelector('#athlete-workspace')?.focus({preventScroll: true}));
        } finally {
            shell.classList.remove('is-refreshing');
            shell.removeAttribute('aria-busy');
        }
    };

    document.addEventListener('keydown', event => {
        if (event.key === 'Tab' && prescriptionModal && !prescriptionModal.hidden) {
            const dialog = prescriptionModal.querySelector('[role=dialog]');
            const fields = [...(dialog?.querySelectorAll('button, input, select, textarea, a[href]') || [])].filter(el => !el.disabled && el.getClientRects().length);
            const first = fields[0];
            const last = fields[fields.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus();
            }
        }
        if (event.key === 'Escape' && prescriptionModal && !prescriptionModal.hidden) setPrescriptionOpen(false);
    });

    document.addEventListener('click', async event => {
        const athleteLink = event.target.closest('a[data-trainer-athlete-link]');
        if (athleteLink && event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey) {
            event.preventDefault();
            try {
                await replaceAthleteWorkspace(athleteLink.href, 'push');
                document.querySelector('#athlete-workspace')?.scrollIntoView({block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
            } catch (_) {
                window.location.assign(athleteLink.href);
            }
            return;
        }

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

    window.addEventListener('popstate', () => {
        if (!document.querySelector('.trainer-athlete-list')) return;
        replaceAthleteWorkspace(window.location.href, 'none').catch(() => window.location.reload());
    });

    setupPrescription();
})();
