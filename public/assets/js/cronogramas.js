document.addEventListener('DOMContentLoaded', () => {
    const scheduleSelector = document.querySelector('[data-schedule-selector]');
    if (scheduleSelector) {
        scheduleSelector.addEventListener('change', () => {
            window.location.href = `/user/cronogramatreinos.php?id=${encodeURIComponent(scheduleSelector.value)}`;
        });
    }

    const createPanel = document.querySelector('[data-schedule-create]');
    const createOptions = document.querySelector('[data-schedule-create-options]');
    const createTitle = document.querySelector('[data-schedule-create-title]');
    const createForms = document.querySelectorAll('[data-schedule-create-form]');
    const importFile = document.querySelector('[data-schedule-import-file]');
    const importPreview = document.querySelector('[data-schedule-import-preview]');
    const importError = document.querySelector('[data-schedule-import-error]');
    const importSubmit = document.querySelector('[data-schedule-import-submit]');

    const resetImportPreview = () => {
        if (importPreview) importPreview.hidden = true;
        if (importError) {
            importError.hidden = true;
            importError.textContent = '';
        }
        if (importSubmit) importSubmit.disabled = true;
    };

    const showCreateMode = mode => {
        if (!createPanel) return;
        createPanel.hidden = false;
        const hasMode = mode === 'blank' || mode === 'import';
        if (createOptions) createOptions.hidden = hasMode;
        createForms.forEach(form => {
            form.hidden = form.dataset.scheduleCreateForm !== mode;
        });
        if (createTitle) {
            createTitle.textContent = mode === 'blank'
                ? 'Criar cronograma do zero'
                : mode === 'import'
                    ? 'Importar cronograma'
                    : 'Como você quer começar?';
        }
        if (!hasMode) {
            resetImportPreview();
            if (importFile) importFile.value = '';
        }
        if (mode === 'blank') {
            createPanel.querySelector('input[name="nome"]')?.focus();
        }
        if (mode === 'import') {
            resetImportPreview();
            importFile?.focus();
        }
        createPanel.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    };

    document.querySelectorAll('[data-open-schedule-create]').forEach(button => {
        button.addEventListener('click', () => showCreateMode(''));
    });

    document.querySelectorAll('[data-open-schedule-import]').forEach(button => {
        button.addEventListener('click', () => {
            button.closest('details')?.removeAttribute('open');
            showCreateMode('import');
        });
    });

    document.querySelectorAll('[data-schedule-create-mode]').forEach(button => {
        button.addEventListener('click', () => showCreateMode(button.dataset.scheduleCreateMode || ''));
    });

    document.querySelectorAll('[data-schedule-create-back]').forEach(button => {
        button.addEventListener('click', () => showCreateMode(''));
    });

    document.querySelectorAll('[data-close-schedule-create]').forEach(button => {
        button.addEventListener('click', () => {
            if (!createPanel) return;
            createPanel.hidden = true;
            resetImportPreview();
            if (importFile) importFile.value = '';
        });
    });

    importFile?.addEventListener('change', async () => {
        resetImportPreview();
        const file = importFile.files?.[0];
        if (!file) return;

        if (file.size <= 0 || file.size > 2 * 1024 * 1024) {
            if (importError) {
                importError.textContent = 'O arquivo deve ter no máximo 2 MB.';
                importError.hidden = false;
            }
            return;
        }

        try {
            const data = JSON.parse(await file.text());
            const valid = data
                && data.format === 'stridebr-schedule'
                && Number(data.version) === 1
                && data.cronograma
                && Array.isArray(data.treinos);

            if (!valid) throw new Error('invalid');

            const workoutCount = data.treinos.length;
            const exerciseCount = data.treinos.reduce((total, workout) => {
                return total + (Array.isArray(workout?.exercicios) ? workout.exercicios.length : 0);
            }, 0);

            const nameTarget = document.querySelector('[data-import-preview-name]');
            const workoutTarget = document.querySelector('[data-import-preview-workouts]');
            const exerciseTarget = document.querySelector('[data-import-preview-exercises]');

            if (nameTarget) nameTarget.textContent = String(data.cronograma.nome || 'Cronograma importado');
            if (workoutTarget) workoutTarget.textContent = String(workoutCount);
            if (exerciseTarget) exerciseTarget.textContent = String(exerciseCount);
            if (importPreview) importPreview.hidden = false;
            if (importSubmit) importSubmit.disabled = false;
        } catch {
            if (importError) {
                importError.textContent = 'Esse arquivo não parece ser um cronograma exportado pelo StrideBR.';
                importError.hidden = false;
            }
        }
    });

    const views = document.querySelectorAll('[data-calendar-view]');
    const viewButtons = document.querySelectorAll('[data-view]');
    let currentView = localStorage.getItem('stridebr.schedule.view') || 'week';
    const activateView = name => {
        currentView = name;
        localStorage.setItem('stridebr.schedule.view', name);
        views.forEach(view => {
            view.hidden = view.dataset.calendarView !== name;
        });
        viewButtons.forEach(button => {
            button.classList.toggle('is-active', button.dataset.view === name);
        });
        document.querySelector('[data-zoom-controls]')?.toggleAttribute('hidden', name !== 'week');
    };
    viewButtons.forEach(button => button.addEventListener('click', () => activateView(button.dataset.view)));
    if (views.length) activateView(currentView === 'agenda' ? 'agenda' : 'week');

    const calendar = document.querySelector('[data-calendar-scroll]');
    const zoomLabel = document.querySelector('[data-zoom-label]');
    const zoomLevels = [40, 50, 60, 70, 80, 90, 100, 110, 120, 130, 140];
    let zoom = Number(localStorage.getItem('stridebr.schedule.zoom')) || 100;
    let fitMode = localStorage.getItem('stridebr.schedule.zoomMode') === 'fit';
    if (!zoomLevels.includes(zoom)) zoom = 100;

    const setCalendarScale = (hourHeight, dayMinWidth, label) => {
        if (!calendar) return;
        const timeColumnWidth = window.matchMedia('(max-width: 640px)').matches ? 52 : 64;
        calendar.style.setProperty('--hour-height', `${Math.round(hourHeight)}px`);
        calendar.style.setProperty('--day-min-width', `${Math.round(dayMinWidth)}px`);
        calendar.style.setProperty('--calendar-min-width', `${Math.round(timeColumnWidth + dayMinWidth * 7)}px`);
        if (zoomLabel) zoomLabel.textContent = label;
    };

    const applyZoom = value => {
        fitMode = false;
        zoom = Math.max(40, Math.min(140, value));
        const factor = zoom / 100;
        setCalendarScale(48 * factor, 160 * factor, `${zoom}%`);
        localStorage.setItem('stridebr.schedule.zoom', String(zoom));
        localStorage.setItem('stridebr.schedule.zoomMode', 'manual');
    };

    const fitCalendar = () => {
        if (!calendar) return;
        fitMode = true;
        const timeColumnWidth = window.matchMedia('(max-width: 640px)').matches ? 52 : 64;
        const headerHeight = window.matchMedia('(max-width: 640px)').matches ? 40 : 48;
        const usableHeight = Math.max(360, calendar.clientHeight - headerHeight - 2);
        const hourHeight = Math.max(18, Math.min(48, usableHeight / 24));
        const dayMinWidth = Math.max(70, (calendar.clientWidth - timeColumnWidth - 2) / 7);
        const fittedPercent = Math.round((hourHeight / 48) * 100);
        setCalendarScale(hourHeight, dayMinWidth, `${fittedPercent}%`);
        localStorage.setItem('stridebr.schedule.zoomMode', 'fit');
        calendar.scrollTop = 0;
        calendar.scrollLeft = 0;
    };

    if (fitMode) {
        requestAnimationFrame(fitCalendar);
    } else {
        applyZoom(zoom);
    }

    document.querySelector('[data-zoom-out]')?.addEventListener('click', () => {
        const current = fitMode ? Math.max(40, Math.min(140, Math.round((parseInt(zoomLabel?.textContent || '100', 10) || 100) / 10) * 10)) : zoom;
        const index = Math.max(0, zoomLevels.indexOf(current) - 1);
        applyZoom(zoomLevels[index]);
    });
    document.querySelector('[data-zoom-in]')?.addEventListener('click', () => {
        const current = fitMode ? Math.max(40, Math.min(140, Math.round((parseInt(zoomLabel?.textContent || '100', 10) || 100) / 10) * 10)) : zoom;
        const index = Math.min(zoomLevels.length - 1, Math.max(0, zoomLevels.indexOf(current)) + 1);
        applyZoom(zoomLevels[index]);
    });
    document.querySelector('[data-zoom-fit]')?.addEventListener('click', fitCalendar);

    calendar?.addEventListener('wheel', event => {
        if (!event.ctrlKey) return;
        event.preventDefault();
        const current = fitMode ? Math.max(40, Math.min(140, Math.round((parseInt(zoomLabel?.textContent || '100', 10) || 100) / 10) * 10)) : zoom;
        let index = zoomLevels.indexOf(current);
        if (index < 0) index = zoomLevels.indexOf(100);
        applyZoom(zoomLevels[Math.max(0, Math.min(zoomLevels.length - 1, index + (event.deltaY < 0 ? 1 : -1)))]);
    }, {passive: false});

    window.addEventListener('resize', () => {
        if (fitMode) fitCalendar();
    });

    const editor = document.querySelector('[data-workout-editor]');
    const newWorkout = document.querySelector('[data-new-workout]');
    const closeWorkout = document.querySelector('[data-close-workout]');
    if (newWorkout && editor) {
        newWorkout.addEventListener('click', () => {
            editor.classList.add('is-open');
            const form = editor.querySelector('form');
            if (form) {
                form.querySelector('[data-workout-id]').value = '';
                form.querySelector('input[name="titulo"]').value = '';
                form.querySelector('select[name="dia_semana"]').value = '1';
                form.querySelector('input[name="hora_inicio"]').value = '18:00';
                form.querySelector('input[name="hora_fim"]').value = '19:00';
                form.querySelector('input[name="termina_dia_seguinte"]').checked = false;
                form.querySelector('textarea[name="descricao"]').value = '';
            }
            const title = editor.querySelector('[data-editor-title]');
            if (title) title.textContent = 'Novo treino';
            editor.querySelector('input[name="titulo"]')?.focus();
        });
    }
    if (closeWorkout && editor) {
        closeWorkout.addEventListener('click', () => editor.classList.remove('is-open'));
    }

    const previewModal = document.querySelector('[data-workout-preview-modal]');
    const previewContents = document.querySelectorAll('[data-workout-preview-content]');
    const closePreview = () => {
        if (!previewModal) return;
        previewModal.hidden = true;
        document.documentElement.style.overflow = '';
    };
    document.querySelectorAll('[data-preview-workout]').forEach(button => {
        button.addEventListener('click', () => {
            if (!previewModal) return;
            const id = button.dataset.previewWorkout;
            previewContents.forEach(content => content.hidden = content.dataset.workoutPreviewContent !== id);
            previewModal.hidden = false;
            document.documentElement.style.overflow = 'hidden';
            previewModal.querySelector('[data-close-preview]')?.focus();
        });
    });
    document.querySelectorAll('[data-close-preview]').forEach(button => button.addEventListener('click', closePreview));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && previewModal && !previewModal.hidden) closePreview();
    });

    const sharePanel = document.querySelector('[data-share-panel]');
    document.querySelector('[data-open-share]')?.addEventListener('click', () => {
        if (sharePanel) sharePanel.hidden = false;
    });
    document.querySelector('[data-close-share]')?.addEventListener('click', () => {
        if (sharePanel) sharePanel.hidden = true;
    });

    document.querySelector('[data-discover-schedules]')?.addEventListener('click', () => {
        window.alert('Descobrir cronogramas entra na próxima etapa. A base de importar/exportar já está pronta.');
    });

    document.querySelector('[data-print-schedule]')?.addEventListener('click', () => {
        const oldView = currentView;
        activateView('agenda');
        setTimeout(() => {
            window.print();
            activateView(oldView);
        }, 50);
    });

    const positionActionsMenu = details => {
        const summary = details.querySelector('summary');
        const menu = details.querySelector('.schedule-actions-content');
        if (!summary || !menu) return;
        const rect = summary.getBoundingClientRect();
        const menuWidth = Math.max(250, menu.offsetWidth || 250);
        const menuHeight = menu.offsetHeight || 270;
        const gap = 8;
        const left = Math.min(window.innerWidth - menuWidth - 10, Math.max(10, rect.right - menuWidth));
        const roomBelow = window.innerHeight - rect.bottom;
        const top = roomBelow >= menuHeight + gap
            ? rect.bottom + gap
            : Math.max(10, rect.top - menuHeight - gap);
        menu.style.setProperty('--menu-left', `${left}px`);
        menu.style.setProperty('--menu-top', `${top}px`);
    };

    document.querySelectorAll('.schedule-actions-menu').forEach(details => {
        details.addEventListener('toggle', () => {
            if (!details.open) return;
            document.querySelectorAll('.schedule-actions-menu[open]').forEach(other => {
                if (other !== details) other.removeAttribute('open');
            });
            requestAnimationFrame(() => positionActionsMenu(details));
        });
    });

    document.addEventListener('click', event => {
        document.querySelectorAll('.schedule-actions-menu[open]').forEach(details => {
            if (!details.contains(event.target)) details.removeAttribute('open');
        });
    });

    window.addEventListener('resize', () => {
        document.querySelectorAll('.schedule-actions-menu[open]').forEach(positionActionsMenu);
    });

    document.querySelectorAll('[data-start-workout]').forEach(button => {
        button.addEventListener('click', async () => {
            const id = button.dataset.startWorkout;
            if (!id || !window.StrideBRWorkout?.start) return;
            button.disabled = true;
            try {
                closePreview();
                await window.StrideBRWorkout.start(id);
            } finally {
                button.disabled = false;
            }
        });
    });

    const rowsContainer = document.querySelector('[data-exercise-rows]');
    const rowTemplate = document.querySelector('[data-exercise-row-template]');
    const addExercise = document.querySelector('[data-add-exercise]');
    let nextIndex = rowsContainer ? rowsContainer.querySelectorAll('[data-exercise-row]').length : 0;

    const renumberRows = () => {
        if (!rowsContainer) return;
        rowsContainer.querySelectorAll('[data-exercise-row]').forEach((row, index) => {
            const number = row.querySelector('[data-row-number]');
            if (number) number.textContent = String(index + 1);
        });
    };

    const wireExerciseRow = row => {
        const library = row.querySelector('[data-library-select]');
        const name = row.querySelector('[data-exercise-name]');
        if (library && name) {
            library.addEventListener('change', () => {
                const option = library.options[library.selectedIndex];
                if (library.value && option?.dataset.name) name.value = option.dataset.name;
            });
        }
        row.querySelector('[data-remove-exercise]')?.addEventListener('click', () => {
            row.remove();
            renumberRows();
        });
    };

    rowsContainer?.querySelectorAll('[data-exercise-row]').forEach(wireExerciseRow);
    addExercise?.addEventListener('click', () => {
        if (!rowsContainer || !rowTemplate) return;
        const html = rowTemplate.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
        const wrapper = document.createElement('tbody');
        wrapper.innerHTML = html.trim();
        const row = wrapper.firstElementChild;
        if (!row) return;
        rowsContainer.appendChild(row);
        wireExerciseRow(row);
        renumberRows();
        row.querySelector('[data-exercise-name]')?.focus();
    });
    if (rowsContainer && rowsContainer.children.length === 0) addExercise?.click();

    const search = document.querySelector('[data-library-search]');
    const filterButtons = document.querySelectorAll('[data-library-filter]');
    const libraryCards = document.querySelectorAll('[data-library-card]');
    let libraryFilter = 'all';
    const applyLibraryFilter = () => {
        const term = (search?.value || '').trim().toLocaleLowerCase('pt-BR');
        libraryCards.forEach(card => {
            const matchesType = libraryFilter === 'all' || card.dataset.libraryType === libraryFilter;
            const matchesTerm = !term || (card.dataset.libraryText || '').includes(term);
            card.hidden = !(matchesType && matchesTerm);
        });
    };
    filterButtons.forEach(button => {
        button.addEventListener('click', () => {
            libraryFilter = button.dataset.libraryFilter || 'all';
            filterButtons.forEach(item => item.classList.toggle('is-active', item === button));
            applyLibraryFilter();
        });
    });
    search?.addEventListener('input', applyLibraryFilter);
});
