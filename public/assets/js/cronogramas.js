document.addEventListener('DOMContentLoaded', () => {
    const scheduleSelector = document.querySelector('[data-schedule-selector]');
    if (scheduleSelector) {
        scheduleSelector.addEventListener('change', () => {
            window.location.href = `/user/cronogramatreinos.php?id=${encodeURIComponent(scheduleSelector.value)}`;
        });
    }

    const createPanel = document.querySelector('[data-schedule-create]');
    document.querySelectorAll('[data-open-schedule-create]').forEach(button => {
        button.addEventListener('click', () => {
            if (createPanel) {
                createPanel.hidden = false;
                createPanel.querySelector('input[name="nome"]')?.focus();
            }
        });
    });
    document.querySelectorAll('[data-close-schedule-create]').forEach(button => {
        button.addEventListener('click', () => {
            if (createPanel) createPanel.hidden = true;
        });
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
    const zoomLevels = [60, 70, 80, 90, 100, 110, 120, 130, 140];
    let zoom = Number(localStorage.getItem('stridebr.schedule.zoom')) || 100;
    if (!zoomLevels.includes(zoom)) zoom = 100;
    const applyZoom = value => {
        zoom = Math.max(60, Math.min(140, value));
        const factor = zoom / 100;
        if (calendar) {
            calendar.style.setProperty('--hour-height', `${Math.round(48 * factor)}px`);
            calendar.style.setProperty('--day-width', `${Math.round(160 * factor)}px`);
        }
        if (zoomLabel) zoomLabel.textContent = `${zoom}%`;
        localStorage.setItem('stridebr.schedule.zoom', String(zoom));
    };
    applyZoom(zoom);
    document.querySelector('[data-zoom-out]')?.addEventListener('click', () => {
        const index = Math.max(0, zoomLevels.indexOf(zoom) - 1);
        applyZoom(zoomLevels[index]);
    });
    document.querySelector('[data-zoom-in]')?.addEventListener('click', () => {
        const index = Math.min(zoomLevels.length - 1, zoomLevels.indexOf(zoom) + 1);
        applyZoom(zoomLevels[index]);
    });
    document.querySelector('[data-zoom-fit]')?.addEventListener('click', () => {
        if (!calendar) return;
        const available = Math.max(320, calendar.clientWidth - 64);
        const dayWidth = Math.max(70, Math.min(160, available / 7));
        const fitted = Math.max(60, Math.min(100, Math.round((dayWidth / 160) * 100 / 10) * 10));
        applyZoom(fitted);
        calendar.scrollLeft = 0;
    });
    calendar?.addEventListener('wheel', event => {
        if (!event.ctrlKey) return;
        event.preventDefault();
        const index = zoomLevels.indexOf(zoom);
        applyZoom(zoomLevels[Math.max(0, Math.min(zoomLevels.length - 1, index + (event.deltaY < 0 ? 1 : -1)))]);
    }, {passive: false});

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

    const importPanel = document.querySelector('[data-import-panel]');
    document.querySelector('[data-open-import]')?.addEventListener('click', () => {
        if (importPanel) importPanel.hidden = false;
    });
    document.querySelector('[data-close-import]')?.addEventListener('click', () => {
        if (importPanel) importPanel.hidden = true;
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

    document.addEventListener('click', event => {
        document.querySelectorAll('.schedule-export-menu[open]').forEach(details => {
            if (!details.contains(event.target)) details.removeAttribute('open');
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
