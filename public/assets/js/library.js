(() => {
    const t = (key, values = {}, fallback = key) => window.StrideBRI18n?.t?.(key, values, fallback) ?? fallback
    const parseJson = selector => {
        const node = document.querySelector(selector);
        if (!node) return {};
        try { return JSON.parse(node.textContent || '{}'); } catch (_) { return {}; }
    };

    const page = document.querySelector('[data-library-page]');
    const tabLinks = [...document.querySelectorAll('[data-library-tab]')];
    const tabViews = [...document.querySelectorAll('[data-library-view]')];
    const headingActions = [...document.querySelectorAll('[data-library-heading-actions]')];
    const tabHelp = [...document.querySelectorAll('[data-library-tab-help]')];
    const exerciseUndo = document.querySelector('[data-library-exercise-undo]');
    const setLibraryTab = (tab, {push = true} = {}) => {
        if (!page || !['treinos', 'exercicios'].includes(tab)) return;
        page.dataset.libraryActiveTab = tab;
        tabLinks.forEach(link => {
            const active = link.dataset.libraryTab === tab;
            link.classList.toggle('is-active', active);
            link.setAttribute('aria-current', active ? 'page' : 'false');
        });
        headingActions.forEach(group => { group.hidden = group.dataset.libraryHeadingActions !== tab; });
        tabHelp.forEach(help => { help.hidden = help.dataset.libraryTabHelp !== tab; });
        tabViews.forEach(view => {
            const active = view.dataset.libraryView === tab;
            view.hidden = !active;
            view.classList.toggle('is-active', active);
            if (active) {
                view.classList.remove('is-entering');
                requestAnimationFrame(() => view.classList.add('is-entering'));
            }
        });
        if (exerciseUndo) exerciseUndo.hidden = tab !== 'exercicios';
        if (push) {
            const url = new URL(window.location.href);
            url.pathname = '/user/biblioteca.php';
            url.searchParams.set('tab', tab);
            url.searchParams.delete('edit');
            history.pushState({libraryTab: tab}, '', `${url.pathname}${url.search}${url.hash}`);
        }
    };
    tabLinks.forEach(link => link.addEventListener('click', event => {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        setLibraryTab(link.dataset.libraryTab || 'treinos');
    }));
    window.addEventListener('popstate', () => {
        const tab = new URL(window.location.href).searchParams.get('tab') || 'treinos';
        setLibraryTab(tab, {push: false});
    });

    const lockBody = locked => {
        document.documentElement.classList.toggle('has-library-modal', locked);
        document.body.classList.toggle('has-library-modal', locked);
    };

    const removeQuery = key => {
        const url = new URL(window.location.href);
        if (!url.searchParams.has(key)) return;
        url.searchParams.delete(key);
        history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
    };

    const workoutModal = document.querySelector('[data-workout-library-modal]');
    const workoutForm = workoutModal?.querySelector('[data-workout-library-form]');
    const workoutData = parseJson('[data-workout-library-data]');

    const closeWorkoutModal = () => {
        if (!workoutModal) return;
        workoutModal.hidden = true;
        lockBody(false);
        removeQuery('edit');
    };

    const openWorkoutModal = id => {
        if (!workoutModal || !workoutForm) return;
        const item = id ? workoutData[String(id)] : null;
        workoutForm.reset();
        const set = (name, value) => {
            const field = workoutForm.elements.namedItem(name);
            if (field) field.value = value == null ? '' : String(value);
        };
        set('idtreino_modelo', item?.idtreino_modelo || '');
        set('titulo', item?.titulo || '');
        set('codigo', item?.codigo || '');
        set('foco', item?.foco || '');
        set('idmodalidade', item?.idmodalidade || '');
        set('descricao', item?.descricao || '');

        const title = workoutModal.querySelector('[data-library-modal-title]');
        if (title) title.textContent = item ? t('library.edit_workout', {}, 'Edit workout') : t('library.new_workout', {}, 'New workout');
        const subtitle = workoutModal.querySelector('[data-library-modal-subtitle]');
        if (subtitle) subtitle.textContent = item ? t('library.edit_workout_help', {}, 'Change general details without changing schedule times.') : t('library.new_workout_help', {}, 'Create a reusable workout for schedules.');

        const propagate = workoutModal.querySelector('[data-workout-propagate]');
        const propagateCheck = workoutForm.elements.namedItem('propagar_vinculados');
        if (propagate) propagate.hidden = !item;
        if (propagateCheck) propagateCheck.checked = Boolean(item);
        const uses = workoutModal.querySelector('[data-workout-uses]');
        if (uses) uses.textContent = String(item?.usos_total || 0);

        const exercisesLink = workoutModal.querySelector('[data-workout-exercises-link]');
        if (exercisesLink) {
            if (item) {
                exercisesLink.hidden = false;
                const returnTo = encodeURIComponent('/user/biblioteca.php?tab=treinos');
                exercisesLink.href = `/user/exerciciostreinomodelo.php?idtreino_modelo=${encodeURIComponent(item.idtreino_modelo)}&return_to=${returnTo}`;
            } else {
                exercisesLink.hidden = true;
                exercisesLink.removeAttribute('href');
            }
        }

        setLibraryTab('treinos', {push: false});
        workoutModal.hidden = false;
        lockBody(true);
        requestAnimationFrame(() => workoutForm.elements.namedItem('titulo')?.focus());
    };

    document.addEventListener('click', event => {
        const editWorkout = event.target.closest('[data-edit-workout-library]');
        if (editWorkout) {
            event.preventDefault();
            openWorkoutModal(editWorkout.dataset.editWorkoutLibrary || '');
            return;
        }
        if (event.target.closest('[data-new-workout-library]')) {
            event.preventDefault();
            openWorkoutModal('');
            return;
        }
        if (event.target.closest('[data-close-workout-library]')) {
            event.preventDefault();
            closeWorkoutModal();
        }
    });

    if (workoutModal && !workoutModal.hidden) openWorkoutModal(workoutModal.dataset.initialEdit || '');

    const exerciseModal = document.querySelector('[data-exercise-library-modal]');
    const exerciseForm = exerciseModal?.querySelector('[data-exercise-library-form]');
    const exerciseData = parseJson('[data-exercise-library-data]');

    const closeExerciseModal = () => {
        if (!exerciseModal) return;
        exerciseModal.hidden = true;
        lockBody(false);
        removeQuery('edit');
    };

    const openExerciseModal = id => {
        if (!exerciseModal || !exerciseForm) return;
        const item = id ? exerciseData[String(id)] : null;
        exerciseForm.reset();
        const set = (name, value) => {
            const field = exerciseForm.elements.namedItem(name);
            if (field) field.value = value == null ? '' : String(value);
        };
        set('action', item ? 'update_exercise' : 'create_exercise');
        set('idexercicio', item?.idexercicio || '');
        set('nome', item?.nome || '');
        set('descricao', item?.descricao || '');
        set('imagem_url', item?.imagem_url || '');
        set('video_url', item?.video_url || '');

        exerciseForm.querySelectorAll('[data-category-option]').forEach(input => {
            input.checked = Boolean(item?.categorias?.includes(String(input.value)));
        });
        exerciseForm.querySelectorAll('[data-modality-option]').forEach(input => {
            input.checked = Boolean(item?.modalidades?.includes(String(input.value)));
        });

        const title = exerciseModal.querySelector('[data-library-modal-title]');
        if (title) title.textContent = item ? t('library.edit_exercise', {}, 'Edit exercise') : t('library.new_exercise', {}, 'New exercise');
        const submit = exerciseModal.querySelector('[data-exercise-submit]');
        if (submit) submit.textContent = item ? t('library.save_changes', {}, 'Save changes') : t('library.save_exercise', {}, 'Save exercise');
        setLibraryTab('exercicios', {push: false});
        exerciseModal.hidden = false;
        lockBody(true);
        requestAnimationFrame(() => exerciseForm.elements.namedItem('nome')?.focus());
    };

    const categoryModal = document.querySelector('[data-category-library-modal]');
    const closeCategoryModal = () => {
        if (!categoryModal) return;
        categoryModal.hidden = true;
        lockBody(false);
    };

    document.addEventListener('click', event => {
        const editExercise = event.target.closest('[data-edit-exercise]');
        if (editExercise) {
            event.preventDefault();
            openExerciseModal(editExercise.dataset.editExercise || '');
            return;
        }
        if (event.target.closest('[data-new-exercise]')) {
            event.preventDefault();
            openExerciseModal('');
            return;
        }
        if (event.target.closest('[data-close-exercise-library]')) {
            event.preventDefault();
            closeExerciseModal();
            return;
        }
        if (event.target.closest('[data-new-category]')) {
            event.preventDefault();
            if (categoryModal) {
                categoryModal.hidden = false;
                lockBody(true);
                requestAnimationFrame(() => categoryModal.querySelector('input[name="nome"]')?.focus());
            }
            return;
        }
        if (event.target.closest('[data-close-category-library]')) {
            event.preventDefault();
            closeCategoryModal();
        }
    });

    if (exerciseModal && !exerciseModal.hidden) openExerciseModal(exerciseModal.dataset.initialEdit || '');

    const search = document.querySelector('[data-library-search]');
    const filterButtons = [...document.querySelectorAll('[data-library-filter]')];
    const cards = [...document.querySelectorAll('[data-library-card]')];
    let activeFilter = 'all';
    const applyFilters = () => {
        const query = (search?.value || '').trim().toLocaleLowerCase('pt-BR');
        let visible = 0;
        cards.forEach(card => {
            const typeMatches = activeFilter === 'all' || card.dataset.libraryType === activeFilter;
            const textMatches = query === '' || String(card.dataset.libraryText || '').includes(query);
            card.hidden = !(typeMatches && textMatches);
            if (!card.hidden) visible++;
        });
        const counter = document.querySelector('[data-library-visible-count]');
        if (counter) counter.textContent = String(visible);
        const empty = document.querySelector('[data-library-filter-empty]');
        if (empty) empty.hidden = visible !== 0;
    };
    filterButtons.forEach(button => button.addEventListener('click', () => {
        activeFilter = button.dataset.libraryFilter || 'all';
        filterButtons.forEach(item => item.classList.toggle('is-active', item === button));
        applyFilters();
    }));
    search?.addEventListener('input', applyFilters);
    if (cards.length) applyFilters();

    const workoutSearch = document.querySelector('[data-workout-library-search]');
    if (workoutSearch) {
        const workoutCards = [...document.querySelectorAll('[data-workout-library-card]')];
        workoutSearch.addEventListener('input', () => {
            const query = workoutSearch.value.trim().toLocaleLowerCase('pt-BR');
            workoutCards.forEach(card => {
                card.hidden = query !== '' && !String(card.dataset.searchText || '').includes(query);
            });
        });
    }

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        if (workoutModal && !workoutModal.hidden) closeWorkoutModal();
        else if (exerciseModal && !exerciseModal.hidden) closeExerciseModal();
        else if (categoryModal && !categoryModal.hidden) closeCategoryModal();
    });
})();
