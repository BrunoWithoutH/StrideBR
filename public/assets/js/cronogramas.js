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
    const activateView = name => {
        views.forEach(view => {
            view.hidden = view.dataset.calendarView !== name;
        });
        viewButtons.forEach(button => {
            button.classList.toggle('is-active', button.dataset.view === name);
        });
    };
    viewButtons.forEach(button => button.addEventListener('click', () => activateView(button.dataset.view)));
    if (window.matchMedia('(max-width: 720px)').matches && views.length) activateView('agenda');

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
            editor.scrollIntoView({behavior: 'smooth', block: 'start'});
            editor.querySelector('input[name="titulo"]')?.focus();
        });
    }
    if (closeWorkout && editor) {
        closeWorkout.addEventListener('click', () => editor.classList.remove('is-open'));
    }

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
