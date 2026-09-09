document.addEventListener('DOMContentLoaded', () => {
    const tr = (key, values = {}, fallback = null) => window.StrideBRI18n?.t?.(key, values, fallback) ?? fallback ?? key
    const trn = (oneKey, otherKey, count, values = {}) => window.StrideBRI18n?.tn?.(oneKey, otherKey, count, values) ?? tr(Number(count) === 1 ? oneKey : otherKey, {...values, count})
    const localeTag = () => window.StrideBRI18n?.locale === 'en' ? 'en-US' : 'pt-BR'
    const weekdayShorts = () => Array.from({length: 7}, (_, index) => window.StrideBRI18n?.weekdayShort?.(index) || new Intl.DateTimeFormat(localeTag(), {weekday:'short'}).format(new Date(2023, 0, 1 + index)).replace(/\.$/, ''))
    const uiNotify = (message, type = 'error') => {
        if (window.StrideBRUI?.notify) window.StrideBRUI.notify(message, type)
        else console.error(message)
    }
    const uiConfirm = (message, options = {}) => window.StrideBRUI?.confirm
        ? window.StrideBRUI.confirm(message, options)
        : Promise.resolve(window.confirm(message))
    const pageParams = new URLSearchParams(window.location.search);
    const requestedView = pageParams.get('view');
    const storedView = localStorage.getItem('stridebr.schedule.view') || 'week';
    const allowedViews = ['week', 'month', 'agenda'];
    const serverView = document.querySelector('[data-view].is-active')?.dataset.view || '';
    let currentView = allowedViews.includes(requestedView) ? requestedView : (allowedViews.includes(serverView) ? serverView : (allowedViews.includes(storedView) ? storedView : 'week'));

    const scheduleSelector = document.querySelector('[data-schedule-selector]');
    if (scheduleSelector) {
        scheduleSelector.addEventListener('change', () => {
            const params = new URLSearchParams();
            params.set('id', scheduleSelector.value);
            if (currentView !== 'week') params.set('view', currentView);
            if (currentView === 'month' && pageParams.get('month')) params.set('month', pageParams.get('month'));
            window.location.href = `/user/cronogramatreinos.php?${params.toString()}`;
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
    const importFileName = document.querySelector('[data-schedule-file-name]');

    const resetImportPreview = () => {
        if (importPreview) importPreview.hidden = true;
        if (importError) {
            importError.hidden = true;
            importError.textContent = '';
        }
        if (importSubmit) importSubmit.disabled = true;
        if (importFileName) importFileName.textContent = tr('schedule.no_file');
    };

    const showCreateMode = mode => {
        if (!createPanel) return;
        createPanel.hidden = false;
        document.documentElement.classList.add('schedule-create-open');
        const hasMode = mode === 'blank' || mode === 'import';
        if (createOptions) createOptions.hidden = hasMode;
        createForms.forEach(form => {
            form.hidden = form.dataset.scheduleCreateForm !== mode;
        });
        if (createTitle) {
            createTitle.textContent = mode === 'blank'
                ? tr('schedule.create_blank_title')
                : mode === 'import'
                    ? tr('schedule.import_schedule')
                    : tr('schedule.how_start');
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
    };

    document.querySelectorAll('[data-open-schedule-create]').forEach(button => {
        button.addEventListener('click', () => showCreateMode(''));
    });

    document.querySelectorAll('[data-open-schedule-import]').forEach(button => {
        button.addEventListener('click', () => {
            button.closest('details')?.removeAttribute('open');
            document.documentElement.classList.remove('schedule-actions-open');
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
            document.documentElement.classList.remove('schedule-create-open');
            resetImportPreview();
            if (importFile) importFile.value = '';
        });
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && createPanel && !createPanel.hidden) {
            createPanel.hidden = true;
            document.documentElement.classList.remove('schedule-create-open');
            resetImportPreview();
            if (importFile) importFile.value = '';
        }
    });

    importFile?.addEventListener('change', async () => {
        resetImportPreview();
        const file = importFile.files?.[0];
        if (!file) return;
        if (importFileName) importFileName.textContent = file.name;

        if (file.size <= 0 || file.size > 2 * 1024 * 1024) {
            if (importError) {
                importError.textContent = tr('schedule.file_too_large');
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
            if (workoutCount > 200) throw new Error('too-many-workouts');
            if (exerciseCount > 2000) throw new Error('too-many-exercises');

            const nameTarget = document.querySelector('[data-import-preview-name]');
            const workoutTarget = document.querySelector('[data-import-preview-workouts]');
            const exerciseTarget = document.querySelector('[data-import-preview-exercises]');

            if (nameTarget) nameTarget.textContent = String(data.cronograma.nome || tr('schedule.imported_fallback'));
            if (workoutTarget) workoutTarget.textContent = String(workoutCount);
            if (exerciseTarget) exerciseTarget.textContent = String(exerciseCount);
            if (importPreview) importPreview.hidden = false;
            if (importSubmit) importSubmit.disabled = false;
        } catch (error) {
            if (importError) {
                importError.textContent = error?.message === 'too-many-workouts'
                    ? tr('schedule.file_too_many_workouts')
                    : error?.message === 'too-many-exercises'
                        ? tr('schedule.file_too_many_exercises')
                        : tr('schedule.file_invalid');
                importError.hidden = false;
            }
        }
    });

    const views = document.querySelectorAll('[data-calendar-view]');
    const viewButtons = document.querySelectorAll('[data-view]');
    const activateView = (name, updateUrl = true) => {
        if (![...views].some(view => view.dataset.calendarView === name)) name = 'week';
        currentView = name;
        localStorage.setItem('stridebr.schedule.view', name);
        document.cookie = `stridebr_schedule_view=${encodeURIComponent(name)}; path=/; max-age=31536000; samesite=lax`;
        views.forEach(view => {
            view.hidden = view.dataset.calendarView !== name;
        });
        document.querySelectorAll('[data-view-context]').forEach(context => {
            context.hidden = context.dataset.viewContext !== name;
        });
        document.body.dataset.scheduleView = name;
        viewButtons.forEach(button => {
            const active = button.dataset.view === name;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        document.querySelector('[data-zoom-controls]')?.toggleAttribute('hidden', name !== 'week');
        if (updateUrl) {
            const url = new URL(window.location.href);
            if (name === 'week') url.searchParams.delete('view');
            else url.searchParams.set('view', name);
            if (name !== 'month') url.searchParams.delete('month');
            window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
        }
    };
    viewButtons.forEach(button => button.addEventListener('click', () => activateView(button.dataset.view)));
    if (views.length) activateView(currentView, false);

    const calendar = document.querySelector('[data-calendar-scroll]');
    const zoomLabel = document.querySelector('[data-zoom-label]');
    const zoomLevels = [40, 50, 60, 70, 80, 90, 100, 110, 120, 130, 140];
    const isMobileWeek = () => window.matchMedia('(max-width: 760px)').matches;
    const baseHourHeight = () => isMobileWeek() ? 38 : 42;
    let zoom = Number(localStorage.getItem('stridebr.schedule.zoom')) || 100;
    let fitMode = localStorage.getItem('stridebr.schedule.zoomMode') === 'fit';
    let calendarHourHeight = baseHourHeight();
    let weekPositioned = false;
    if (!zoomLevels.includes(zoom)) zoom = 100;

    const setCalendarScale = (hourHeight, dayMinWidth, label) => {
        if (!calendar) return;
        const mobile = isMobileWeek();
        const timeColumnWidth = mobile ? 48 : 54;
        calendarHourHeight = Math.max(18, Math.round(hourHeight));
        calendar.style.setProperty('--hour-height', `${calendarHourHeight}px`);
        calendar.style.setProperty('--week-total-height', `${calendarHourHeight * 24 + (mobile ? 40 : 42)}px`);
        if (mobile) {
            const width = Math.max(96, Math.round(dayMinWidth || 118));
            calendar.style.setProperty('--day-min-width', `${width}px`);
            calendar.style.setProperty('--calendar-min-width', `${Math.round(timeColumnWidth + width * 7)}px`);
        } else {
            calendar.style.removeProperty('--day-min-width');
            calendar.style.removeProperty('--calendar-min-width');
        }
        if (zoomLabel) zoomLabel.textContent = label;
    };

    const recommendedWeekStartHour = () => {
        const starts = [...document.querySelectorAll('[data-week-card]')]
            .map(card => Number(card.style.getPropertyValue('--start-min') || 0))
            .filter(value => Number.isFinite(value) && value >= 0);
        if (starts.length === 0) return 6;
        const earliest = Math.min(...starts);
        return earliest < 360 ? Math.max(0, Math.floor(earliest / 60) - 1) : 6;
    };

    const positionWeekAtUsefulHour = (force = false) => {
        if (!calendar || fitMode || currentView !== 'week' || (weekPositioned && !force)) return;
        const targetHour = recommendedWeekStartHour();
        if (isMobileWeek()) {
            const calendarTop = window.scrollY + calendar.getBoundingClientRect().top;
            const headerHeight = document.querySelector('.site-header')?.getBoundingClientRect().height || 0;
            const targetTop = Math.max(0, calendarTop + (targetHour * calendarHourHeight) - headerHeight - 8);
            window.scrollTo({top: targetTop, behavior: 'auto'});
        } else {
            calendar.scrollTop = targetHour * calendarHourHeight;
            calendar.scrollLeft = 0;
        }
        weekPositioned = true;
    };

    const applyZoom = value => {
        fitMode = false;
        zoom = Math.max(40, Math.min(140, value));
        const factor = zoom / 100;
        setCalendarScale(baseHourHeight() * factor, 118 * factor, `${zoom}%`);
        localStorage.setItem('stridebr.schedule.zoom', String(zoom));
        localStorage.setItem('stridebr.schedule.zoomMode', 'manual');
    };

    const fitCalendar = () => {
        if (!calendar) return;
        fitMode = true;
        const mobile = isMobileWeek();
        const headerHeight = mobile ? 40 : 42;
        const usableHeight = mobile ? Math.max(720, window.innerHeight * 1.35) : Math.max(360, calendar.clientHeight - headerHeight - 2);
        const hourHeight = mobile ? 30 : Math.max(18, Math.min(42, usableHeight / 24));
        const fittedPercent = Math.round((hourHeight / baseHourHeight()) * 100);
        setCalendarScale(hourHeight, 104, `${fittedPercent}%`);
        localStorage.setItem('stridebr.schedule.zoomMode', 'fit');
        if (!mobile) {
            calendar.scrollTop = 0;
            calendar.scrollLeft = 0;
        }
    };

    if (fitMode) {
        requestAnimationFrame(fitCalendar);
    } else {
        applyZoom(zoom);
        requestAnimationFrame(() => positionWeekAtUsefulHour(true));
    }

    viewButtons.forEach(button => button.addEventListener('click', () => {
        if (button.dataset.view === 'week') requestAnimationFrame(() => positionWeekAtUsefulHour(false));
    }));

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

    let resizeTimer = 0;
    window.addEventListener('resize', () => {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(() => {
            weekPositioned = false;
            if (fitMode) fitCalendar();
            else applyZoom(zoom);
        }, 120);
    });

    const editor = document.querySelector('[data-workout-editor]');
    const editorForm = editor?.querySelector('.workout-form');
    const editorDataNode = document.querySelector('[data-workout-editor-data]');
    const editorExercisesLink = editor?.querySelector('[data-workout-exercises-link]');
    const editorOccurrenceOriginal = editor?.querySelector('[data-editor-occurrence-original]');
    const editorReturnTo = editor?.querySelector('[data-editor-return-to]');
    const saveScopeModal = document.querySelector('[data-workout-save-scope]');
    let pendingWorkoutPayload = null;
    let pendingWorkoutSubmit = null;
    let editorWorkouts = [];
    try { editorWorkouts = JSON.parse(editorDataNode?.textContent || '[]'); } catch (_) { editorWorkouts = []; }
    const editorWorkoutMap = new Map(editorWorkouts.map(item => [String(item.idtreino || ''), item]));
    const weekStatsFromCards = () => {
        const planned = new Set([...document.querySelectorAll('[data-week-card]:not(.is-plan-ghost):not(.is-continuation)')].map(card => card.dataset.occurrenceWorkout).filter(Boolean));
        const completed = new Set([...document.querySelectorAll('[data-week-card][data-completed="1"]:not(.is-plan-ghost):not(.is-continuation)')].map(card => card.dataset.occurrenceWorkout).filter(Boolean));
        const done = [...planned].filter(id => completed.has(id)).length;
        return {planned, completed, total: planned.size, done};
    };
    const syncCompactWeekSummaries = (stats = weekStatsFromCards(), preferredNextId = '') => {
        const {planned, completed, total, done} = stats;
        const percent = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
        const pending = Math.max(0, total - done);
        document.querySelectorAll('[data-week-compact-progress]').forEach(node => {
            node.textContent = total === 0 ? tr('schedule.no_workouts') : tr('schedule.week_progress_compact', {done, total});
        });
        document.querySelectorAll('[data-week-compact-percent]').forEach(node => { node.textContent = `${percent}%`; });
        document.querySelectorAll('[data-week-compact-pending]').forEach(node => {
            node.textContent = trn('schedule.week_pending_compact.one', 'schedule.week_pending_compact.other', pending);
            node.hidden = pending === 0;
        });
        document.querySelectorAll('[data-week-summary-compact]').forEach(node => node.classList.toggle('is-complete', total > 0 && done >= total));

        let nextId = String(preferredNextId || '');
        if (!nextId || !planned.has(nextId) || completed.has(nextId)) {
            const sidebarNext = document.querySelector('.schedule-week-next-main[data-preview-workout]');
            const sidebarId = String(sidebarNext?.dataset.previewWorkout || '');
            nextId = sidebarId && planned.has(sidebarId) && !completed.has(sidebarId)
                ? sidebarId
                : editorWorkouts.map(item => String(item.idtreino || '')).find(id => planned.has(id) && !completed.has(id)) || '';
        }
        const nextItem = nextId ? editorWorkoutMap.get(nextId) : null;
        const selectorId = nextId && typeof CSS !== 'undefined' && CSS.escape ? CSS.escape(nextId) : nextId;
        const occurrence = nextId ? document.querySelector(`[data-week-card][data-occurrence-workout="${selectorId}"]:not(.is-plan-ghost):not(.is-continuation)`) : null;
        document.querySelectorAll('[data-week-compact-next]').forEach(button => {
            const visible = total > 0 && done < total && !!nextItem && !!occurrence;
            button.hidden = !visible;
            if (!visible) return;
            button.dataset.previewWorkout = nextId;
            button.dataset.occurrenceOriginal = occurrence.dataset.occurrenceOriginal || '';
            button.dataset.plannedDate = occurrence.dataset.plannedDate || occurrence.dataset.occurrenceDate || '';
            button.dataset.plannedTime = occurrence.dataset.plannedTime || '';
            const title = button.querySelector('[data-week-compact-next-title]');
            if (title) title.textContent = [nextItem.codigo, nextItem.titulo || tr('schedule.workout_fallback')].filter(Boolean).join(' · ');
        });
    };
    const refreshScheduleView = async () => {
        if (currentView === 'month' && monthShell?.dataset.currentMonth) {
            await fetchMonth(monthShell.dataset.currentMonth, {force:true, showSkeleton:false});
            return;
        }
        const response = await (window.StrideBRNet?.fetch || fetch)(window.location.href, {headers:{'Accept':'text/html','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin'}, 15000);
        if (!response.ok) throw new Error(tr('schedule.update_error'));
        const html = await response.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const incomingData = doc.querySelector('[data-workout-editor-data]');
        if (incomingData) {
            try {
                editorWorkouts = JSON.parse(incomingData.textContent || '[]');
                editorWorkoutMap.clear();
                editorWorkouts.forEach(item => editorWorkoutMap.set(String(item.idtreino || ''), item));
            } catch (_) {}
        }
        if (currentView === 'week') {
            const incomingWeek = doc.querySelector('[data-week-calendar]');
            if (weekCalendar && incomingWeek) {
                const top = calendar?.scrollTop || 0;
                const left = calendar?.scrollLeft || 0;
                weekCalendar.innerHTML = incomingWeek.innerHTML;
                if (calendar) {
                    calendar.scrollTop = top;
                    calendar.scrollLeft = left;
                }
            }
            const currentSummary = document.querySelector('[data-week-summary]');
            const incomingSummary = doc.querySelector('[data-week-summary]');
            if (currentSummary && incomingSummary) currentSummary.replaceWith(incomingSummary);
            const currentCompact = document.querySelector('[data-week-summary-compact]');
            const incomingCompact = doc.querySelector('[data-week-summary-compact]');
            if (currentCompact && incomingCompact) currentCompact.replaceWith(incomingCompact);
            const currentWeekContext = document.querySelector('[data-view-context="week"]');
            const incomingWeekContext = doc.querySelector('[data-view-context="week"]');
            if (currentWeekContext && incomingWeekContext) currentWeekContext.replaceWith(incomingWeekContext);
            if (fitMode) fitCalendar(); else applyZoom(zoom);
            return;
        }
        const currentAgenda = document.querySelector('[data-calendar-view="agenda"]');
        const incomingAgenda = doc.querySelector('[data-calendar-view="agenda"]');
        if (currentAgenda && incomingAgenda) currentAgenda.innerHTML = incomingAgenda.innerHTML;
    };
    if (editor && editor.parentElement !== document.body) document.body.appendChild(editor);
    if (saveScopeModal && saveScopeModal.parentElement !== document.body) document.body.appendChild(saveScopeModal);
    const closeWorkoutEditor = () => {
        if (!editor) return;
        editor.hidden = true;
        document.documentElement.style.overflow = '';
        const url = new URL(window.location.href);
        if (url.searchParams.has('treino')) {
            url.searchParams.delete('treino');
            history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
        }
    };
    const openWorkoutEditor = (id, source = null) => {
        if (!editor || !editorForm) return;
        const item = editorWorkoutMap.get(String(id || ''));
        if (!item) return;
        if (previewModal && !previewModal.hidden) closePreview();
        const set = (name, value) => {
            const field = editorForm.elements.namedItem(name);
            if (!field) return;
            field.value = value ?? '';
            if (field.matches?.('[data-time24-value]')) window.StrideBRTime24?.set(field, field.value);
        };
        set('idtreino', item.idtreino || '');
        set('titulo', item.titulo || '');
        set('codigo', item.codigo || '');
        set('foco', item.foco || '');
        set('idmodalidade', item.idmodalidade || '');
        set('dia_semana', String(item.dia_semana ?? 1));
        set('hora_inicio', item.hora_inicio || '18:00');
        set('hora_fim', item.hora_fim || '19:00');
        set('vigencia_inicio', item.vigencia_inicio || localDate());
        set('vigencia_fim', item.vigencia_fim || '');
        set('descricao', item.descricao || '');
        const occurrence = source?.dataset?.occurrenceOriginal || previewOccurrenceOriginal || '';
        const plannedDate = source?.dataset?.plannedDate || source?.dataset?.occurrenceDate || previewPlannedDate || '';
        if (occurrence && plannedDate) {
            const parts = plannedDate.split('-').map(Number);
            if (parts.length === 3) set('dia_semana', String(new Date(parts[0], parts[1] - 1, parts[2]).getDay()));
        }
        if (editorOccurrenceOriginal) editorOccurrenceOriginal.value = occurrence;
        if (editorReturnTo) editorReturnTo.value = `${location.pathname}${location.search}${location.hash}`;
        editorForm.querySelectorAll('[data-series-only]').forEach(field => { field.hidden = !!occurrence; });
        const nextDay = editorForm.elements.namedItem('termina_dia_seguinte');
        if (nextDay instanceof HTMLInputElement) nextDay.checked = !!item.termina_dia_seguinte;
        const title = editor.querySelector('[data-editor-title]');
        if (title) title.textContent = occurrence ? tr('schedule.edit_occurrence') : tr('schedule.edit_workout_short');
        if (editorExercisesLink) {
            editorExercisesLink.href = `/user/exercicioscronograma.php?idtreino=${encodeURIComponent(item.idtreino || '')}&return_to=${encodeURIComponent(`${location.pathname}${location.search}${location.hash}`)}`;
            editorExercisesLink.hidden = false;
        }
        editor.hidden = false;
        document.documentElement.style.overflow = 'hidden';
        editorForm.querySelector('input[name="titulo"]')?.focus();
    };
    editor?.querySelectorAll('[data-close-workout]').forEach(button => button.addEventListener('click', closeWorkoutEditor));
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-edit-workout]');
        if (button) openWorkoutEditor(button.dataset.editWorkout || '', button);
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && saveScopeModal && !saveScopeModal.hidden) return;
        if (event.key === 'Escape' && editor && !editor.hidden) closeWorkoutEditor();
    });
    if (editor && !editor.hidden) {
        document.documentElement.style.overflow = 'hidden';
    }
    const closeWorkoutScope = () => {
        if (!saveScopeModal) return;
        saveScopeModal.hidden = true;
        pendingWorkoutPayload = null;
        if (pendingWorkoutSubmit) pendingWorkoutSubmit.disabled = false;
        pendingWorkoutSubmit = null;
    };
    const saveWorkoutChanges = async scope => {
        if (!pendingWorkoutPayload) return;
        const payload = new URLSearchParams(pendingWorkoutPayload);
        payload.set('action', 'edit_workout');
        payload.set('scope', scope || 'all');
        try {
            const response = await (window.StrideBRNet?.fetch || fetch)('/api/cronograma-ocorrencias.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','Accept':'application/json'}, body:payload}, 15000);
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.error || tr('schedule.save_error'));
            if (saveScopeModal) saveScopeModal.hidden = true;
            pendingWorkoutPayload = null;
            pendingWorkoutSubmit = null;
            closeWorkoutEditor();
            monthCache.clear();
            await refreshScheduleView();
            showScheduleToast(tr('schedule.workout_updated_success'));
        } catch (error) {
            uiNotify(error.message || tr('schedule.save_error'));
            if (pendingWorkoutSubmit) pendingWorkoutSubmit.disabled = false;
        }
    };
    saveScopeModal?.querySelectorAll('[data-close-workout-scope]').forEach(button => button.addEventListener('click', closeWorkoutScope));
    saveScopeModal?.querySelectorAll('[data-workout-scope-choice]').forEach(button => button.addEventListener('click', () => {
        saveWorkoutChanges(button.dataset.workoutScopeChoice || 'this');
    }));
    editorForm?.addEventListener('submit', event => {
        const id = String(editorForm.elements.namedItem('idtreino')?.value || '');
        if (!id) return;
        event.preventDefault();
        pendingWorkoutPayload = new FormData(editorForm);
        pendingWorkoutSubmit = editorForm.querySelector('button[type="submit"]');
        if (pendingWorkoutSubmit) pendingWorkoutSubmit.disabled = true;
        const occurrence = String(editorOccurrenceOriginal?.value || '');
        if (occurrence && saveScopeModal) {
            saveScopeModal.hidden = false;
            saveScopeModal.querySelector('[data-workout-scope-choice="this"]')?.focus();
            return;
        }
        saveWorkoutChanges('all');
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && saveScopeModal && !saveScopeModal.hidden) {
            event.stopImmediatePropagation();
            closeWorkoutScope();
        }
    });

    const previewModal = document.querySelector('[data-workout-preview-modal]');
    const previewContents = () => [...document.querySelectorAll('[data-workout-preview-content]')];
    let previewWorkoutId = '';
    let previewWorkoutDate = '';
    let previewOccurrenceOriginal = '';
    let previewPlannedDate = '';
    let previewPlannedTime = '';
    let previewRealizedDate = '';
    let previewRealizedTime = '';
    let previewActivityId = '';
    let previewCompleted = false;
    const shortDate = value => window.StrideBRI18n?.date?.(value, {day:'2-digit', month:'2-digit'}) || value || '';
    const syncPreviewOccurrenceContext = trigger => {
        const source = trigger.closest('[data-occurrence-workout],[data-planned-date],[data-completed]') || trigger;
        previewOccurrenceOriginal = trigger.dataset.occurrenceOriginal || source.dataset.occurrenceOriginal || '';
        previewPlannedDate = trigger.dataset.plannedDate || source.dataset.plannedDate || trigger.dataset.workoutDate || '';
        previewPlannedTime = trigger.dataset.plannedTime || source.dataset.plannedTime || '';
        previewRealizedDate = trigger.dataset.realizedDate || source.dataset.realizedDate || '';
        previewRealizedTime = trigger.dataset.realizedTime || source.dataset.realizedTime || '';
        previewActivityId = trigger.dataset.activityId || source.dataset.activityId || '';
        const completed = (trigger.dataset.completed || source.dataset.completed || '') === '1';
        previewCompleted = completed;
        const activeContent = previewContents().find(content => !content.hidden) || previewModal?.querySelector('[data-workout-preview-content]');
        const context = activeContent?.querySelector('[data-preview-occurrence-context]') || null;
        if (!context) return;
        let text = '';
        if (completed && previewRealizedDate && previewPlannedDate && previewPlannedDate !== previewRealizedDate) {
            text = tr('schedule.realized_planned', {realized: shortDate(previewRealizedDate), realized_time: previewRealizedTime ? tr('schedule.at_time', {time: previewRealizedTime}) : '', planned: shortDate(previewPlannedDate), planned_time: previewPlannedTime ? tr('schedule.at_time', {time: previewPlannedTime}) : ''});
        } else if (!completed && previewPlannedDate && previewOccurrenceOriginal && previewPlannedDate !== previewOccurrenceOriginal) {
            text = tr('schedule.planned_for', {date: shortDate(previewPlannedDate), time: previewPlannedTime ? tr('schedule.at_time', {time: previewPlannedTime}) : '', original: shortDate(previewOccurrenceOriginal)});
        }
        context.textContent = text;
        context.hidden = text === '';
    };
    const previewExerciseMeta = exercise => [
        exercise.series !== null && exercise.series !== undefined ? `${Number(exercise.series)} ${tr('schedule.sets')}` : '',
        exercise.repeticoes ? String(exercise.repeticoes).replace(/\s*(?:reps?|repetições?)\s*$/i, '') + ' reps' : '',
        exercise.carga || '',
        exercise.descanso ? `${tr('schedule.rest')} ${exercise.descanso}` : '',
        exercise.bloco ? `${tr('schedule.block')} ${exercise.bloco}` : '',
        exercise.cluster || '',
    ].filter(Boolean);
    const renderDynamicPreviewContent = data => {
        if (!previewModal || !data?.workout) return null;
        const workout = data.workout;
        const content = document.createElement('div');
        content.className = 'workout-preview-content';
        content.dataset.workoutPreviewContent = String(workout.idtreino || '');
        const tags = [workout.codigo, workout.foco].filter(Boolean).map(value => `<b>${escapeHtml(value)}</b>`).join('');
        const exercises = Array.isArray(data.exercises) ? data.exercises : [];
        const exerciseMarkup = exercises.length ? exercises.map((exercise, index) => {
            const meta = previewExerciseMeta(exercise).map(value => `<span>${escapeHtml(value)}</span>`).join('');
            return `<article class="preview-exercise"><span class="preview-exercise-number">${index + 1}</span><div><strong>${escapeHtml(exercise.nome || '')}</strong>${meta ? `<div class="preview-exercise-meta">${meta}</div>` : ''}${exercise.observacoes ? `<small>${escapeHtml(exercise.observacoes)}</small>` : ''}</div></article>`;
        }).join('') : `<p class="preview-empty">${escapeHtml(tr('schedule.no_exercises'))}</p>`;
        const libraryAction = workout.biblioteca_disponivel
            ? workout.idtreino_modelo
                ? `<form method="POST" class="preview-copy-form"><input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}"><input type="hidden" name="action" value="update_library_from_workout"><input type="hidden" name="idcronograma" value="${escapeHtml(workout.idcronograma)}"><input type="hidden" name="idtreino" value="${escapeHtml(workout.idtreino)}"><input type="hidden" name="return_to" value="${escapeHtml(`${location.pathname}${location.search}${location.hash}`)}" data-return-current><button type="submit">${escapeHtml(tr('schedule.update_saved'))}</button></form>`
                : `<form method="POST" class="preview-copy-form"><input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}"><input type="hidden" name="action" value="save_workout_to_library"><input type="hidden" name="idcronograma" value="${escapeHtml(workout.idcronograma)}"><input type="hidden" name="idtreino" value="${escapeHtml(workout.idtreino)}"><input type="hidden" name="return_to" value="${escapeHtml(`${location.pathname}${location.search}${location.hash}`)}" data-return-current><button type="submit">${escapeHtml(tr('schedule.save_library'))}</button></form>`
            : '';
        const sessionActions = document.querySelector('[data-quick-register-modal]')
            ? `<button type="button" class="primary-button" data-quick-register-workout="${escapeHtml(workout.idtreino)}" data-workout-title="${escapeHtml(workout.titulo)}" data-workout-time="${escapeHtml(workout.hora_inicio)}" data-workout-duration="${escapeHtml(workout.duracao_minutos || 60)}">${escapeHtml(tr('schedule.log'))}</button><button type="button" class="secondary-button" data-start-workout="${escapeHtml(workout.idtreino)}">${escapeHtml(tr('schedule.start_live'))}</button>`
            : '';
        content.innerHTML = `<div class="workout-preview-heading"><div class="workout-preview-occurrence-context" data-preview-occurrence-context hidden></div><span>${escapeHtml(workout.dia || '')} · ${escapeHtml(workout.hora_inicio || '')}–${escapeHtml(workout.hora_fim || '')}${workout.termina_dia_seguinte ? ' +1' : ''}</span>${tags ? `<div class="workout-preview-tags">${tags}</div>` : ''}<h2>${escapeHtml(workout.titulo || tr('schedule.workout_fallback'))}</h2>${workout.descricao ? `<p>${escapeHtml(workout.descricao)}</p>` : ''}</div><div class="workout-preview-exercises">${exerciseMarkup}</div><div class="workout-preview-actions"><div class="workout-preview-primary-actions">${sessionActions}<button type="button" class="secondary-button" data-edit-workout="${escapeHtml(workout.idtreino)}">${escapeHtml(tr('common.edit'))}</button><details class="workout-preview-more"><summary class="secondary-button">${escapeHtml(tr('common.more'))}</summary><div class="workout-preview-menu"><button type="button" data-preview-move>${escapeHtml(tr('schedule.reschedule'))}</button><button type="button" data-preview-adjust-history hidden>${escapeHtml(tr('schedule.fix_plan_actual'))}</button><button type="button" data-preview-skip>${escapeHtml(tr('schedule.skip_occurrence'))}</button><a href="/user/exercicioscronograma.php?idtreino=${encodeURIComponent(workout.idtreino)}">${escapeHtml(tr('schedule.edit_exercises'))}</a>${libraryAction}<form method="POST" class="preview-copy-form"><input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}"><input type="hidden" name="action" value="duplicate_workout"><input type="hidden" name="idcronograma" value="${escapeHtml(workout.idcronograma)}"><input type="hidden" name="idtreino" value="${escapeHtml(workout.idtreino)}"><input type="hidden" name="duplicate_mode" value="edit"><input type="hidden" name="return_to" value="${escapeHtml(`${location.pathname}${location.search}${location.hash}`)}" data-return-current><button type="submit">${escapeHtml(tr('schedule.duplicate_edit'))}</button></form><form method="POST" class="preview-delete-form" data-confirm="${escapeHtml(tr('schedule.remove_from_schedule'))}"><input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}"><input type="hidden" name="action" value="delete_workout"><input type="hidden" name="idcronograma" value="${escapeHtml(workout.idcronograma)}"><input type="hidden" name="idtreino" value="${escapeHtml(workout.idtreino)}"><input type="hidden" name="return_to" value="${escapeHtml(`${location.pathname}${location.search}${location.hash}`)}" data-return-current><button type="submit" class="is-danger">${escapeHtml(tr('schedule.delete_workout'))}</button></form></div></details></div></div>`;
        previewModal.querySelector('.workout-preview-dialog')?.appendChild(content);
        return content;
    };
    const ensurePreviewContent = async id => {
        let content = previewContents().find(item => item.dataset.workoutPreviewContent === id) || null;
        if (content) return content;
        const loading = document.createElement('div');
        loading.className = 'workout-preview-content workout-preview-loading';
        loading.dataset.workoutPreviewContent = id;
        loading.innerHTML = '<div class="workout-preview-loading-line"><span></span><span></span><span></span></div>';
        previewModal?.querySelector('.workout-preview-dialog')?.appendChild(loading);
        try {
            const response = await (window.StrideBRNet?.fetch || fetch)(`/api/cronograma-treino-preview.php?idtreino=${encodeURIComponent(id)}`, {headers:{'Accept':'application/json'}, credentials:'same-origin'}, 10000);
            const data = await response.json().catch(() => null);
            if (!response.ok || !data?.ok) throw new Error(data?.error || tr('schedule.load_error'));
            loading.remove();
            content = renderDynamicPreviewContent(data);
            return content;
        } catch (error) {
            loading.innerHTML = `<div class="workout-preview-load-error"><strong>${escapeHtml(tr('schedule.load_error'))}</strong><button type="button" class="secondary-button" data-preview-retry>${escapeHtml(tr('schedule.retry'))}</button></div>`;
            loading.querySelector('[data-preview-retry]')?.addEventListener('click', async () => {
                loading.remove();
                await ensurePreviewContent(id);
            }, {once:true});
            throw error;
        }
    };
    const showWorkoutPreview = async trigger => {
        if (!previewModal) return;
        const id = trigger.dataset.previewWorkout;
        if (!id) return;
        previewWorkoutId = id;
        const source = trigger.closest('[data-occurrence-workout],[data-planned-date],[data-completed]') || trigger;
        previewWorkoutDate = trigger.dataset.workoutDate || source.dataset.occurrenceDate || source.dataset.plannedDate || '';
        previewModal.hidden = false;
        document.documentElement.style.overflow = 'hidden';
        previewContents().forEach(content => { content.hidden = content.dataset.workoutPreviewContent !== id; });
        try { await ensurePreviewContent(id); } catch (error) { uiNotify(error?.message || tr('schedule.load_error')); }
        previewContents().forEach(content => { content.hidden = content.dataset.workoutPreviewContent !== id; });
        syncPreviewOccurrenceContext(trigger);
        previewContents().forEach(content => {
            const active = content.dataset.workoutPreviewContent === id;
            if (active) {
                content.querySelectorAll('[data-quick-register-workout],[data-start-workout]').forEach(action => { action.hidden = previewCompleted; });
                content.querySelectorAll('[data-preview-move],[data-preview-skip]').forEach(action => { action.hidden = !previewOccurrenceOriginal || previewCompleted; });
                content.querySelectorAll('[data-preview-adjust-history]').forEach(action => { action.hidden = !previewCompleted || !previewActivityId; });
            }
        });
        previewModal.querySelectorAll('[data-return-current]').forEach(input => { input.value = `${location.pathname}${location.search}${location.hash}`; });
        previewModal.querySelector('[data-close-preview]')?.focus();
    };
    const closePreview = () => {
        if (!previewModal) return;
        previewModal.hidden = true;
        document.documentElement.style.overflow = '';
    };
    document.querySelectorAll('[data-preview-workout]').forEach(button => {
        button.dataset.previewBound = '1';
        button.addEventListener('click', () => showWorkoutPreview(button));
    });
    document.querySelectorAll('[data-close-preview]').forEach(button => button.addEventListener('click', closePreview));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && previewModal && !previewModal.hidden) closePreview();
    });

    const quickRegisterModal = document.querySelector('[data-quick-register-modal]');
    const quickRegisterForm = quickRegisterModal?.querySelector('[data-quick-register-form]');
    const quickRegisterTitle = quickRegisterModal?.querySelector('[data-quick-register-title]');
    const quickRegisterId = quickRegisterModal?.querySelector('[data-quick-register-id]');
    const quickRegisterDate = quickRegisterModal?.querySelector('[data-quick-register-date]');
    const quickRegisterTime = quickRegisterModal?.querySelector('[data-quick-register-time]');
    const quickRegisterDuration = quickRegisterModal?.querySelector('[data-quick-register-duration]');
    const quickRegisterNoDuration = quickRegisterModal?.querySelector('[data-quick-register-no-duration]');
    if (quickRegisterModal && quickRegisterModal.parentElement !== document.body) {
        document.body.appendChild(quickRegisterModal);
    }
    const localDate = () => {
        const now = new Date();
        const y = now.getFullYear();
        const m = String(now.getMonth() + 1).padStart(2, '0');
        const d = String(now.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    };
    const closeQuickRegister = () => {
        if (!quickRegisterModal) return;
        quickRegisterModal.hidden = true;
        document.documentElement.style.overflow = previewModal && !previewModal.hidden ? 'hidden' : '';
    };
    const openQuickRegisterFromButton = button => {
        if (!quickRegisterModal || !quickRegisterForm || !button) return;
        closePreview();
        if (quickRegisterId) quickRegisterId.value = button.dataset.quickRegisterWorkout || '';
        if (quickRegisterTitle) quickRegisterTitle.textContent = tr('schedule.register_named', {name: button.dataset.workoutTitle || tr('schedule.workout_fallback').toLowerCase()});
        if (quickRegisterDate) quickRegisterDate.value = previewWorkoutDate || localDate();
        if (quickRegisterTime) {
            quickRegisterTime.value = button.dataset.workoutTime || '18:00';
            window.StrideBRTime24?.set(quickRegisterTime, quickRegisterTime.value);
        }
        if (quickRegisterDuration) {
            quickRegisterDuration.disabled = false;
            quickRegisterDuration.value = button.dataset.workoutDuration || '60';
            quickRegisterDuration.dataset.defaultDuration = button.dataset.workoutDuration || '60';
        }
        if (quickRegisterNoDuration) quickRegisterNoDuration.checked = false;
        quickRegisterDuration?.closest('.quick-register-duration-field')?.classList.remove('is-disabled');
        quickRegisterForm.querySelector('select[name="intensidade"]').value = '';
        quickRegisterForm.querySelector('textarea[name="observacoes"]').value = '';
        quickRegisterModal.hidden = false;
        document.documentElement.style.overflow = 'hidden';
    };
    document.querySelectorAll('[data-quick-register-workout]').forEach(button => {
        button.dataset.quickRegisterBound = '1';
        button.addEventListener('click', () => openQuickRegisterFromButton(button));
    });
    quickRegisterModal?.querySelectorAll('[data-close-quick-register]').forEach(button => button.addEventListener('click', closeQuickRegister));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && quickRegisterModal && !quickRegisterModal.hidden) {
            closeQuickRegister();
        }
    });
    quickRegisterModal?.querySelector('[data-quick-register-today]')?.addEventListener('click', () => {
        if (quickRegisterDate) quickRegisterDate.value = localDate();
    });

    quickRegisterNoDuration?.addEventListener('change', () => {
        if (!quickRegisterDuration) return;
        const disabled = quickRegisterNoDuration.checked;
        quickRegisterDuration.disabled = disabled;
        if (disabled) quickRegisterDuration.value = '';
        else if (!quickRegisterDuration.value) quickRegisterDuration.value = quickRegisterDuration.dataset.defaultDuration || '60';
        quickRegisterDuration.closest('.quick-register-duration-field')?.classList.toggle('is-disabled', disabled);
    });
    quickRegisterForm?.addEventListener('submit', async event => {
        event.preventDefault();
        const submit = quickRegisterForm.querySelector('button[type="submit"]');
        if (submit) submit.disabled = true;
        try {
            const formData = new FormData(quickRegisterForm);
            const data = await window.StrideBRWorkout?.quickRegister?.({
                idtreino: formData.get('idtreino') || '',
                data: formData.get('data') || '',
                hora: formData.get('hora') || '',
                duracao_minutos: formData.get('duracao_minutos') || '',
                intensidade: formData.get('intensidade') || '',
                observacoes: formData.get('observacoes') || '',
                data_ocorrencia_origem: previewOccurrenceOriginal || '',
                data_ocorrencia_planejada: previewPlannedDate || previewWorkoutDate || '',
                hora_ocorrencia_planejada: previewPlannedTime || '',
            });
            if (data?.activity_id) {
                const idTreino = String(formData.get('idtreino') || '');
                const realizedDate = String(formData.get('data') || localDate());
                const realizedTime = String(formData.get('hora') || '');
                const durationRaw = String(formData.get('duracao_minutos') || '').trim();
                const hasDuration = durationRaw !== '' && Number(durationRaw) > 0;
                const duration = hasDuration ? Math.max(1, Number(durationRaw)) : 0;
                closeQuickRegister();
                const selectorId = typeof CSS !== 'undefined' && CSS.escape ? CSS.escape(idTreino) : idTreino.replace(/"/g, '\"');
                const cards = [...document.querySelectorAll(`[data-week-card][data-occurrence-workout="${selectorId}"]`)].filter(card => !previewOccurrenceOriginal || card.dataset.occurrenceOriginal === previewOccurrenceOriginal);
                const mainCard = cards.find(card => !card.classList.contains('is-continuation')) || cards[0];
                cards.filter(card => card !== mainCard).forEach(card => card.remove());
                if (mainCard) {
                    const day = document.querySelector(`[data-week-date="${realizedDate}"]`);
                    const parts = /^([01]\d|2[0-3]):([0-5]\d)$/.exec(realizedTime);
                    if (day && parts) {
                        const startMinutes = Number(parts[1]) * 60 + Number(parts[2]);
                        const visualDuration = hasDuration ? duration : Math.max(30, Number(mainCard.dataset.durationMin || 60));
                        const endTotal = startMinutes + visualDuration;
                        day.appendChild(mainCard);
                        mainCard.style.setProperty('--start-min', String(startMinutes));
                        mainCard.style.setProperty('--duration-min', String(Math.max(30, Math.min(visualDuration, 1440 - startMinutes))));
                        mainCard.dataset.occurrenceDate = realizedDate;
                        mainCard.dataset.workoutDate = realizedDate;
                        mainCard.dataset.realizedDate = realizedDate;
                        mainCard.dataset.realizedTime = realizedTime;
                        mainCard.dataset.activityId = String(data.activity_id || '');
                        mainCard.dataset.completed = '1';
                        mainCard.classList.add('is-complete');
                        mainCard.classList.remove('is-exception');
                        mainCard.classList.toggle('is-realized-shifted', mainCard.dataset.plannedDate !== realizedDate);
                        mainCard.removeAttribute('draggable');
                        const time = mainCard.querySelector('[data-card-time]');
                        if (time) time.textContent = hasDuration ? `${realizedTime}–${formatMinutes(endTotal % 1440)}${endTotal >= 1440 ? ' +1' : ''}` : realizedTime;
                        mainCard.querySelectorAll('.workout-card-status').forEach(node => node.remove());
                        const status = document.createElement('small');
                        status.className = 'workout-card-status';
                        status.textContent = tr(mainCard.dataset.plannedDate !== realizedDate ? 'planning.status.shifted' : 'schedule.completed_badge');
                        mainCard.appendChild(status);
                    }
                }
                const summary = document.querySelector('[data-week-summary]');
                if (summary) {
                    const stats = weekStatsFromCards();
                    const {planned, completed, total, done} = stats;
                    const percent = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
                    const heading = summary.querySelector('.schedule-week-progress-heading strong');
                    const counter = summary.querySelector('.schedule-week-progress-count');
                    const bar = summary.querySelector('.schedule-week-progress-bar span');
                    if (heading) heading.textContent = total === 0 ? tr('schedule.no_workouts') : (done >= total ? tr('schedule.week_completed') : tr('schedule.done_of_total', {done, total}));
                    if (counter) counter.textContent = `${percent}%`;
                    if (bar) bar.style.width = `${percent}%`;
                    summary.classList.toggle('is-complete', total > 0 && done >= total);
                    const next = summary.querySelector('.schedule-week-next-main');
                    if (next && completed.has(next.dataset.previewWorkout || '')) {
                        const nextId = editorWorkouts.map(item => String(item.idtreino || '')).find(id => planned.has(id) && !completed.has(id));
                        const nextItem = nextId ? editorWorkoutMap.get(nextId) : null;
                        const occurrence = nextId ? document.querySelector(`[data-week-card][data-occurrence-workout="${typeof CSS !== 'undefined' && CSS.escape ? CSS.escape(nextId) : nextId}"]:not(.is-plan-ghost):not(.is-continuation)`) : null;
                        if (nextItem && occurrence) {
                            next.dataset.previewWorkout = nextId;
                            next.dataset.occurrenceOriginal = occurrence.dataset.occurrenceOriginal || '';
                            next.dataset.plannedDate = occurrence.dataset.plannedDate || occurrence.dataset.occurrenceDate || '';
                            next.dataset.plannedTime = occurrence.dataset.plannedTime || '';
                            next.dataset.workoutDate = localDate();
                            next.innerHTML = `${nextItem.codigo ? `<span class="schedule-week-next-code">${escapeHtml(nextItem.codigo)}</span>` : ''}<span class="schedule-week-next-copy"><strong>${escapeHtml(nextItem.titulo || tr('schedule.workout_fallback'))}</strong>${nextItem.foco ? `<small>${escapeHtml(nextItem.foco)}</small>` : ''}</span>`;
                        } else if (done >= total) {
                            summary.querySelector('.schedule-week-next')?.remove();
                        }
                    }
                    syncCompactWeekSummaries(stats);
                }
                monthCache.clear();
                if (currentView === 'month' && monthShell?.dataset.currentMonth) await fetchMonth(monthShell.dataset.currentMonth, {force:true});
                showScheduleToast(tr('schedule.workout_logged_success'));
            }
        } finally {
            if (submit) submit.disabled = false;
        }
    });

    const sharePanel = document.querySelector('[data-share-panel]');
    document.querySelector('[data-open-share]')?.addEventListener('click', event => {
        event.currentTarget.closest('details')?.removeAttribute('open');
        document.documentElement.classList.remove('schedule-actions-open');
        if (sharePanel) sharePanel.hidden = false;
    });
    document.querySelector('[data-close-share]')?.addEventListener('click', () => {
        if (sharePanel) sharePanel.hidden = true;
    });

    const originalPlanToggle = document.querySelector('[data-toggle-original-plan]');
    const setOriginalPlanVisible = visible => {
        document.body.classList.toggle('schedule-show-original-plan', visible);
        if (originalPlanToggle) {
            originalPlanToggle.setAttribute('aria-pressed', visible ? 'true' : 'false');
            originalPlanToggle.textContent = visible ? 'Ocultar planejamento original' : 'Mostrar planejamento original';
        }
        localStorage.setItem('stridebr.schedule.showOriginalPlan', visible ? '1' : '0');
    };
    setOriginalPlanVisible(localStorage.getItem('stridebr.schedule.showOriginalPlan') === '1');
    originalPlanToggle?.addEventListener('click', () => {
        setOriginalPlanVisible(!document.body.classList.contains('schedule-show-original-plan'));
    });

    document.querySelector('[data-print-schedule]')?.addEventListener('click', event => {
        event.currentTarget.closest('details')?.removeAttribute('open');
        document.documentElement.classList.remove('schedule-actions-open');
        const oldView = currentView;
        activateView('agenda');
        setTimeout(() => {
            window.print();
            activateView(oldView);
        }, 50);
    });

    const scheduleMenus = [...document.querySelectorAll('.schedule-actions-menu')];
    const syncScheduleMenuState = () => {
        const open = scheduleMenus.some(details => details.open);
        document.documentElement.classList.toggle('schedule-actions-open', open);
    };

    const closeScheduleMenu = details => {
        if (!details?.open) return;
        details.removeAttribute('open');
        syncScheduleMenuState();
    };

    scheduleMenus.forEach(details => {
        const summary = details.querySelector('summary');
        details.addEventListener('toggle', () => {
            if (details.open) {
                scheduleMenus.forEach(other => {
                    if (other !== details) other.removeAttribute('open');
                });
                syncScheduleMenuState();
                requestAnimationFrame(() => details.querySelector('[data-close-schedule-actions]')?.focus());
            } else {
                syncScheduleMenuState();
            }
        });

        details.querySelectorAll('[data-close-schedule-actions]').forEach(button => {
            button.addEventListener('click', () => {
                closeScheduleMenu(details);
                summary?.focus();
            });
        });
    });

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        scheduleMenus.forEach(details => {
            if (!details.open) return;
            const summary = details.querySelector('summary');
            closeScheduleMenu(details);
            summary?.focus();
        });
    });

    document.addEventListener('pointerdown', event => {
        scheduleMenus.forEach(details => {
            if (!details.open) return;
            const content = details.querySelector('.schedule-actions-content');
            const summary = details.querySelector('summary');
            if (content?.contains(event.target) || summary?.contains(event.target)) return;
            closeScheduleMenu(details);
        });
    });

    const startWorkoutFromButton = async button => {
        const id = button?.dataset.startWorkout;
        if (!id || !window.StrideBRWorkout?.start) return;
        button.disabled = true;
        try {
            closePreview();
            await window.StrideBRWorkout.start(id, {
                data_ocorrencia_origem: previewOccurrenceOriginal || '',
                data_ocorrencia_planejada: previewPlannedDate || previewWorkoutDate || '',
                hora_ocorrencia_planejada: previewPlannedTime || '',
            });
        } finally {
            button.disabled = false;
        }
    };
    document.querySelectorAll('[data-start-workout]').forEach(button => {
        button.dataset.startWorkoutBound = '1';
        button.addEventListener('click', () => startWorkoutFromButton(button));
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
            const next = row.nextElementSibling;
            row.remove();
            renumberRows();
            window.StrideBRUI?.undo?.(tr('schedule.exercise_removed'), async () => {
                if (next?.isConnected) rowsContainer.insertBefore(row, next);
                else rowsContainer.appendChild(row);
                renumberRows();
            });
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
        const term = (search?.value || '').trim().toLocaleLowerCase(localeTag());
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

    const monthShell = document.querySelector('[data-month-calendar-shell]');
    const monthGrid = monthShell?.querySelector('[data-month-grid]');
    const weekCalendar = document.querySelector('[data-week-calendar]');
    const monthTitleTarget = monthShell?.querySelector('[data-month-title]');
    const monthCache = new Map();
    const monthRequests = new Map();
    let activeMonthController = null;
    let monthRenderToken = 0;
    let draggedOccurrence = null;
    let draggedLibrary = null;
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    const quickModal = document.querySelector('[data-calendar-quick-create]');
    const quickPopover = quickModal?.querySelector('[data-quick-popover]');
    const quickForm = quickModal?.querySelector('[data-calendar-quick-form]');
    const quickMode = quickForm?.querySelector('[data-quick-mode]');
    const quickSchedule = quickForm?.querySelector('[data-quick-schedule]');
    const quickSource = quickForm?.querySelector('[data-quick-source]');
    const quickSourceRow = quickForm?.querySelector('[data-quick-source-row]');
    const quickSourceCards = quickForm?.querySelector('[data-quick-source-cards]');
    const quickTitleInput = quickForm?.querySelector('[data-quick-workout-title]');
    const quickDate = quickForm?.querySelector('[data-quick-date]');
    const quickStart = quickForm?.querySelector('[data-quick-start]');
    const quickEnd = quickForm?.querySelector('[data-quick-end]');
    const quickNextDay = quickForm?.querySelector('[data-quick-next-day]');
    const quickRepeat = quickForm?.querySelector('[data-quick-repeat]');
    const quickUntil = quickForm?.querySelector('[data-quick-until]');
    const quickUntilInput = quickForm?.querySelector('[data-quick-until-input]');
    const quickCode = quickForm?.querySelector('[data-quick-code]');
    const quickFocus = quickForm?.querySelector('[data-quick-focus]');
    const quickModality = quickForm?.querySelector('[data-quick-modality]');
    const quickDescription = quickForm?.querySelector('[data-quick-description]');
    const quickScheduleFields = quickForm?.querySelector('[data-quick-schedule-fields]');
    const quickSaveLibraryRow = quickForm?.querySelector('[data-quick-save-library-row]');
    const quickSaveLibrary = quickForm?.querySelector('[data-quick-save-library]');
    const quickExerciseSummary = quickForm?.querySelector('[data-quick-exercise-summary]');
    const quickEditExercises = quickForm?.querySelector('[data-quick-edit-exercises]');
    const quickError = quickForm?.querySelector('[data-quick-error]');
    const quickSubmit = quickForm?.querySelector('[data-quick-submit]');
    const quickTitle = quickModal?.querySelector('[data-quick-title]');
    const quickEyebrow = quickModal?.querySelector('[data-quick-eyebrow]');
    const quickDraftKey = 'stridebr.schedule.quickDraft';
    let quickAnchor = null;
    let quickDraft = null;

    if (quickModal && quickModal.parentElement !== document.body) document.body.appendChild(quickModal);

    const readQuickDraft = () => {
        try {
            const raw = sessionStorage.getItem(quickDraftKey);
            return raw ? JSON.parse(raw) : null;
        } catch (_) {
            return null;
        }
    };
    const writeQuickDraft = draft => {
        if (!draft) return;
        draft.updatedAt = Date.now();
        sessionStorage.setItem(quickDraftKey, JSON.stringify(draft));
    };
    const clearQuickDraft = () => {
        quickDraft = null;
        sessionStorage.removeItem(quickDraftKey);
    };
    const quickOption = value => quickSource ? [...quickSource.options].find(option => option.value === value) : null;
    const updateQuickExerciseSummary = () => {
        if (!quickExerciseSummary) return;
        const source = quickSource?.value || '';
        if (source) {
            const count = Number(quickOption(source)?.dataset.exercises || 0);
            quickExerciseSummary.textContent = count > 0 ? trn('schedule.saved_exercises.one','schedule.saved_exercises.other',count) : tr('schedule.saved_no_exercises');
            return;
        }
        const count = Array.isArray(quickDraft?.exercises) ? quickDraft.exercises.filter(row => String(row?.nome || '').trim() !== '').length : 0;
        quickExerciseSummary.textContent = count > 0 ? trn('schedule.added_exercises.one','schedule.added_exercises.other',count) : tr('schedule.no_exercises_added_short');
    };
    const collectQuickState = () => {
        const previousExercises = Array.isArray(quickDraft?.exercises) ? quickDraft.exercises : [];
        quickDraft = {
            mode: quickMode?.value || 'schedule',
            scheduleId: quickSchedule?.value || monthShell?.dataset.scheduleId || '',
            sourceModel: quickSource?.value || '',
            title: quickTitleInput?.value || '',
            code: quickCode?.value || '',
            focus: quickFocus?.value || '',
            modality: quickModality?.value || '',
            description: quickDescription?.value || '',
            date: quickDate?.value || localDate(),
            start: quickStart?.value || '18:00',
            end: quickEnd?.value || '19:00',
            nextDay: !!quickNextDay?.checked,
            repeat: quickRepeat?.value || 'once',
            until: quickUntilInput?.value || '',
            saveLibrary: quickSaveLibrary ? quickSaveLibrary.checked : false,
            exercises: previousExercises,
        };
        writeQuickDraft(quickDraft);
        return quickDraft;
    };
    const applyQuickMode = mode => {
        const isLibrary = mode === 'library';
        if (quickMode) quickMode.value = isLibrary ? 'library' : 'schedule';
        if (quickSourceRow) quickSourceRow.hidden = isLibrary;
        if (quickScheduleFields) quickScheduleFields.hidden = isLibrary;
        if (quickSaveLibraryRow) quickSaveLibraryRow.hidden = isLibrary || !!quickSource?.value;
        if (quickTitle) quickTitle.textContent = isLibrary ? tr('schedule.new_saved_workout') : tr('schedule.add_workout');
        if (quickEyebrow) quickEyebrow.textContent = isLibrary ? tr('schedule.my_workouts') : tr('schedule.new_workout');
        if (quickSubmit) quickSubmit.textContent = isLibrary ? tr('schedule.save_my_workouts') : tr('schedule.save_workout');
    };
    const applyQuickSource = (value, fill = true) => {
        if (quickSource && quickSource.value !== value) quickSource.value = value;
        const option = value ? quickOption(value) : null;
        if (fill && option) {
            if (quickTitleInput) quickTitleInput.value = option.dataset.title || option.textContent || '';
            if (quickCode) quickCode.value = option.dataset.code || '';
            if (quickFocus) quickFocus.value = option.dataset.focus || '';
            if (quickModality) quickModality.value = option.dataset.modality || '';
            if (quickDescription) quickDescription.value = option.dataset.description || '';
        } else if (fill && !value) {
            if (quickTitleInput) quickTitleInput.value = '';
            if (quickCode) quickCode.value = '';
            if (quickFocus) quickFocus.value = '';
            if (quickModality) quickModality.value = '';
            if (quickDescription) quickDescription.value = '';
            if (quickDraft) quickDraft.exercises = [];
        }
        if (quickSaveLibraryRow) quickSaveLibraryRow.hidden = (quickMode?.value === 'library') || !!value;
        quickSourceCards?.querySelectorAll('[data-quick-source-card]').forEach(card => {
            card.classList.toggle('is-active', (card.dataset.quickSourceCard || '') === (value || ''));
            card.setAttribute('aria-pressed', (card.dataset.quickSourceCard || '') === (value || '') ? 'true' : 'false');
        });
        updateQuickExerciseSummary();
    };
    const hydrateQuickForm = draft => {
        if (!quickForm || !draft) return;
        quickDraft = draft;
        if (!Array.isArray(quickDraft.exercises)) quickDraft.exercises = [];
        applyQuickMode(draft.mode || 'schedule');
        if (quickSchedule) quickSchedule.value = draft.scheduleId || monthShell?.dataset.scheduleId || quickSchedule.value;
        if (quickSource) quickSource.value = draft.sourceModel || '';
        if (quickTitleInput) quickTitleInput.value = draft.title || '';
        if (quickCode) quickCode.value = draft.code || '';
        if (quickFocus) quickFocus.value = draft.focus || '';
        if (quickModality) quickModality.value = draft.modality || '';
        if (quickDescription) quickDescription.value = draft.description || '';
        if (quickDate) quickDate.value = draft.date || localDate();
        if (quickStart) {
            quickStart.value = draft.start || '18:00';
            window.StrideBRTime24?.set(quickStart, quickStart.value);
        }
        if (quickEnd) {
            quickEnd.value = draft.end || '19:00';
            window.StrideBRTime24?.set(quickEnd, quickEnd.value);
        }
        if (quickNextDay) quickNextDay.checked = !!draft.nextDay;
        if (quickRepeat) quickRepeat.value = draft.repeat || 'once';
        if (quickUntilInput) quickUntilInput.value = draft.until || '';
        if (quickUntil) quickUntil.hidden = (quickRepeat?.value || 'once') !== 'weekly';
        if (quickSaveLibrary) quickSaveLibrary.checked = draft.saveLibrary !== false;
        if (quickSaveLibraryRow) quickSaveLibraryRow.hidden = (draft.mode === 'library') || !!draft.sourceModel;
        quickSourceCards?.querySelectorAll('[data-quick-source-card]').forEach(card => {
            card.classList.toggle('is-active', (card.dataset.quickSourceCard || '') === (draft.sourceModel || ''));
            card.setAttribute('aria-pressed', (card.dataset.quickSourceCard || '') === (draft.sourceModel || '') ? 'true' : 'false');
        });
        if (quickError) quickError.hidden = true;
        updateQuickExerciseSummary();
    };
    const positionQuickPopover = anchor => {
        if (!quickPopover) return;
        quickPopover.style.left = '';
        quickPopover.style.top = '';
        quickPopover.style.right = '';
        quickPopover.style.transform = '';
        quickPopover.style.maxHeight = '';
        if (window.innerWidth <= 760) return;
        const viewport = window.visualViewport;
        const viewportLeft = viewport?.offsetLeft || 0;
        const viewportTop = viewport?.offsetTop || 0;
        const viewportWidth = viewport?.width || window.innerWidth;
        const viewportHeight = viewport?.height || window.innerHeight;
        quickPopover.style.maxHeight = `${Math.max(320, viewportHeight - 24)}px`;
        const bounds = quickPopover.getBoundingClientRect();
        const width = Math.min(bounds.width || 620, viewportWidth - 24);
        const height = Math.min(bounds.height || 720, viewportHeight - 24);
        if (!anchor) {
            quickPopover.style.left = `${viewportLeft + Math.max(12, (viewportWidth - width) / 2)}px`;
            quickPopover.style.top = `${viewportTop + Math.max(12, (viewportHeight - height) / 2)}px`;
            quickPopover.style.transform = 'none';
            return;
        }
        const rect = anchor.getBoundingClientRect();
        const idealRight = rect.right + 10;
        const idealLeft = rect.left - width - 10;
        let left;
        if (idealRight + width <= viewportLeft + viewportWidth - 12) left = idealRight;
        else if (idealLeft >= viewportLeft + 12) left = idealLeft;
        else left = viewportLeft + Math.max(12, Math.min(rect.left, viewportWidth - width - 12));
        const desiredTop = rect.top - Math.min(18, height * .08);
        const top = Math.max(viewportTop + 12, Math.min(desiredTop, viewportTop + viewportHeight - height - 12));
        quickPopover.style.left = `${left}px`;
        quickPopover.style.top = `${top}px`;
        quickPopover.style.transform = 'none';
    };
    const openQuickCreate = ({mode='schedule', date='', sourceModel='', anchor=null, restore=false}={}) => {
        if (!quickModal || !quickForm) return;
        quickAnchor = anchor;
        let draft = restore ? readQuickDraft() : null;
        if (!draft) {
            draft = {
                mode,
                scheduleId: monthShell?.dataset.scheduleId || quickSchedule?.value || '',
                sourceModel,
                title: '', code: '', focus: '', description: '',
                date: date || localDate(), start: '18:00', end: '19:00', nextDay: false,
                repeat: 'once', until: '', saveLibrary: false, exercises: [],
            };
        }
        if (!restore) {
            draft.mode = mode;
            if (date) draft.date = date;
            draft.sourceModel = sourceModel || '';
        }
        hydrateQuickForm(draft);
        if (sourceModel) applyQuickSource(sourceModel, true);
        writeQuickDraft(collectQuickState());
        quickModal.hidden = false;
        document.documentElement.classList.add('calendar-quick-open');
        requestAnimationFrame(() => {
            positionQuickPopover(anchor);
            quickTitleInput?.focus();
        });
    };
    const closeQuickCreate = (clear=false) => {
        if (!quickModal) return;
        quickModal.hidden = true;
        document.documentElement.classList.remove('calendar-quick-open');
        quickAnchor = null;
        if (clear) clearQuickDraft();
    };
    quickModal?.querySelectorAll('[data-close-quick-create]').forEach(button => button.addEventListener('click', () => closeQuickCreate(true)));
    window.addEventListener('resize', () => {
        if (quickModal && !quickModal.hidden) positionQuickPopover(quickAnchor);
    });
    window.visualViewport?.addEventListener('resize', () => {
        if (quickModal && !quickModal.hidden) positionQuickPopover(quickAnchor);
    });
    window.visualViewport?.addEventListener('scroll', () => {
        if (quickModal && !quickModal.hidden) positionQuickPopover(quickAnchor);
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && quickModal && !quickModal.hidden) closeQuickCreate(true);
    });
    quickForm?.addEventListener('input', () => writeQuickDraft(collectQuickState()));
    quickForm?.addEventListener('change', event => {
        if (event.target === quickRepeat && quickUntil) quickUntil.hidden = quickRepeat.value !== 'weekly';
        if (event.target === quickSource) applyQuickSource(quickSource.value, true);
        writeQuickDraft(collectQuickState());
    });
    quickSourceCards?.addEventListener('click', event => {
        const card = event.target.closest('[data-quick-source-card]');
        if (!card) return;
        applyQuickSource(card.dataset.quickSourceCard || '', true);
        writeQuickDraft(collectQuickState());
        quickTitleInput?.focus();
    });
    quickEditExercises?.addEventListener('click', () => {
        const state = collectQuickState();
        const returnUrl = new URL(window.location.href);
        returnUrl.searchParams.set('resume_quick', '1');
        const returnPath = `${returnUrl.pathname}${returnUrl.search}${returnUrl.hash}`;
        if (state.sourceModel) {
            window.location.href = `/user/exerciciostreinomodelo.php?idtreino_modelo=${encodeURIComponent(state.sourceModel)}&return_to=${encodeURIComponent(returnPath)}`;
        } else {
            window.location.href = `/user/exerciciosrascunho.php?return_to=${encodeURIComponent(returnPath)}`;
        }
    });
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-new-workout]');
        if (button) openQuickCreate({mode:'schedule', date:localDate(), anchor:button});
    });

    const addLibraryOption = model => {
        if (!quickSource || !model?.idtreino_modelo) return;
        const option = document.createElement('option');
        option.value = model.idtreino_modelo;
        option.textContent = model.titulo || tr('schedule.saved_workout');
        option.dataset.title = model.titulo || '';
        option.dataset.code = model.codigo || '';
        option.dataset.focus = model.foco || '';
        option.dataset.description = model.descricao || '';
        option.dataset.modality = model.idmodalidade || '';
        option.dataset.exercises = String(model.exercicios_total || 0);
        quickSource.appendChild(option);
        if (quickSourceCards) {
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'quick-create-source-card';
            card.dataset.quickSourceCard = model.idtreino_modelo;
            const meta = [model.codigo, model.foco].filter(Boolean).join(' · ') || trn('schedule.source_exercises.one','schedule.source_exercises.other',Number(model.exercicios_total || 0));
            card.innerHTML = `<span class="quick-create-source-icon is-saved">↗</span><span><strong>${escapeHtml(model.titulo || tr('schedule.saved_workout'))}</strong><small>${escapeHtml(meta)}</small></span>`;
            quickSourceCards.appendChild(card);
        }
    };
    quickForm?.addEventListener('submit', async event => {
        event.preventDefault();
        const state = collectQuickState();
        if (quickError) quickError.hidden = true;
        if (quickSubmit) quickSubmit.disabled = true;
        const payload = new URLSearchParams();
        payload.set('csrf_token', csrfToken);
        const submissionBytes = new Uint8Array(16);
        crypto.getRandomValues(submissionBytes);
        payload.set('_idempotency_key', Array.from(submissionBytes, byte => byte.toString(16).padStart(2, '0')).join(''));
        payload.set('action', state.mode === 'library' ? 'create_library' : 'create_schedule');
        payload.set('titulo', state.title.trim());
        payload.set('codigo', state.code.trim());
        payload.set('foco', state.focus.trim());
        payload.set('idmodalidade', state.modality || '');
        payload.set('descricao', state.description.trim());
        payload.set('exercicios', JSON.stringify(Array.isArray(state.exercises) ? state.exercises : []));
        if (state.mode !== 'library') {
            payload.set('idcronograma', state.scheduleId);
            payload.set('idtreino_modelo', state.sourceModel);
            payload.set('data_treino', state.date);
            payload.set('hora_inicio', state.start);
            payload.set('hora_fim', state.end);
            payload.set('recorrencia', state.repeat);
            payload.set('vigencia_fim', state.until);
            if (state.nextDay) payload.set('termina_dia_seguinte', '1');
            if (state.saveLibrary && !state.sourceModel) payload.set('salvar_biblioteca', '1');
        }
        try {
            const response = await (window.StrideBRNet?.fetch || fetch)('/api/cronograma-quick-create.php', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','Accept':'application/json'},
                body: payload,
            }, 15000);
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.error || tr('schedule.save_error'));
            if (state.mode === 'library') {
                addLibraryOption(data.modelo);
                closeQuickCreate(true);
            } else {
                const month = state.date.slice(0, 7);
                closeQuickCreate(true);
                monthCache.clear();
                activateView('month');
                await navigateMonth(month);
            }
        } catch (error) {
            if (quickError) {
                quickError.textContent = error?.message || tr('schedule.save_error');
                quickError.hidden = false;
            }
        } finally {
            if (quickSubmit) quickSubmit.disabled = false;
        }
    });
    if (pageParams.get('resume_quick') === '1' && readQuickDraft()) {
        const url = new URL(window.location.href);
        url.searchParams.delete('resume_quick');
        history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
        openQuickCreate({restore:true});
    }
    const requestedCreate = pageParams.get('new');
    if (requestedCreate === 'workout') {
        const url = new URL(window.location.href);
        url.searchParams.delete('new');
        history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
        openQuickCreate({mode:'schedule', date:localDate()});
    } else if (requestedCreate === 'schedule') {
        const url = new URL(window.location.href);
        url.searchParams.delete('new');
        history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
        showCreateMode('blank');
    }
    const monthParts = key => {
        const match = /^(\d{4})-(\d{2})$/.exec(key || '');
        if (!match) return null;
        const year = Number(match[1]);
        const month = Number(match[2]);
        if (month < 1 || month > 12) return null;
        return {year, month};
    };
    const monthKeyShift = (key, delta) => {
        const parts = monthParts(key);
        if (!parts) return key;
        const date = new Date(parts.year, parts.month - 1 + delta, 1);
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
    };
    const monthRange = key => {
        const parts = monthParts(key);
        if (!parts) return null;
        const endDay = new Date(parts.year, parts.month, 0).getDate();
        return {start: `${key}-01`, end: `${key}-${String(endDay).padStart(2, '0')}`, days: endDay, leading: new Date(parts.year, parts.month - 1, 1).getDay()};
    };
    const monthLabel = key => {
        const parts = monthParts(key);
        if (!parts) return key;
        const label = window.StrideBRI18n?.monthYear?.(`${key}-01`) || new Intl.DateTimeFormat(localeTag(), {month:'long', year:'numeric'}).format(new Date(parts.year, parts.month - 1, 1));
        return label.replace(/^./, c => c.toUpperCase());
    };
    const renderMonthSkeleton = key => {
        if (!monthGrid) return;
        monthGrid.classList.add('is-loading');
        if (monthTitleTarget && key) monthTitleTarget.textContent = monthLabel(key);
        const range = monthRange(key);
        const cellCount = range ? Math.ceil((range.leading + range.days) / 7) * 7 : 35;
        monthGrid.innerHTML = `${weekdayShorts().map(day => `<div class="monthly-weekday">${escapeHtml(day)}</div>`).join('')}${Array.from({length:cellCount}, () => '<div class="monthly-day month-day-skeleton" aria-hidden="true"><span></span><i></i><i></i></div>').join('')}`;
    };
    const groupByDate = rows => rows.reduce((map, row) => {
        const date = row.data_treino || '';
        if (!map[date]) map[date] = [];
        map[date].push(row);
        return map;
    }, {});
    const scheduledMarkup = item => {
        const time = item.hora_inicio || tr('schedule.by_date');
        const kind = item.origem === 'treinador' ? tr('schedule.prescription') : tr('schedule.scheduled');
        const author = item.origem === 'treinador' && item.criador_nome ? `<small>${escapeHtml(item.criador_nome)}</small>` : '';
        const exercises = Number(item.exercicios_total || 0) > 0 ? `<small>${escapeHtml(trn('schedule.exercise_count.one', 'schedule.exercise_count.other', Number(item.exercicios_total)))}</small>` : '';
        const action = item.status === 'concluido' ? `<em>${escapeHtml(tr('schedule.month_completed'))}</em>` : (item.status === 'publicado' ? `<button type="button" class="schedule-month-event-action" data-start-scheduled-workout="${escapeHtml(item.idagendamento)}">${escapeHtml(tr('schedule.month_start'))}</button>` : '');
        return `<article class="monthly-event is-scheduled${item.origem === 'treinador' ? ' is-trainer' : ''}"><span>${escapeHtml(time)} · ${kind}</span><strong>${escapeHtml(item.titulo)}</strong>${author}${exercises}${action}</article>`;
    };
    const occurrenceMarkup = item => {
        const completed = !!item.concluido;
        const classes = `${!completed && item.excecao ? ' is-exception' : ''}${completed ? ' is-complete' : ''}${item.realizado_fora_planejado ? ' is-realized-shifted' : ''}`;
        const drag = completed ? '' : ' draggable="true"';
        const menu = completed ? '' : `<button type="button" class="schedule-month-event-menu" data-move-occurrence aria-label="${escapeHtml(tr('schedule.move_or_skip'))}">•••</button>`;
        const today = new Intl.DateTimeFormat('en-CA', {timeZone: 'America/Sao_Paulo', year:'numeric', month:'2-digit', day:'2-digit'}).format(new Date());
        const state = completed ? (item.realizado_fora_planejado ? 'shifted' : 'completed') : ((item.data_planejada || item.data_treino) < today ? 'missed' : 'todo');
        const status = item.acompanhamento_disponivel === false ? '' : ` · ${tr('planning.status.' + state)}`;
        return `<article class="monthly-event is-recurring schedule-month-recurring${classes}"${drag} data-occurrence-workout="${escapeHtml(item.idtreino)}" data-occurrence-original="${escapeHtml(item.data_original)}" data-occurrence-date="${escapeHtml(item.data_treino)}" data-occurrence-title="${escapeHtml(item.titulo)}" data-planned-date="${escapeHtml(item.data_planejada || item.data_treino)}" data-planned-time="${escapeHtml(item.hora_planejada || item.hora_inicio)}" data-realized-date="${escapeHtml(item.data_realizada || '')}" data-realized-time="${escapeHtml(item.hora_realizada || '')}" data-activity-id="${escapeHtml(item.idregistro || '')}" data-completed="${completed ? '1' : '0'}"><button type="button" class="schedule-month-event-main" data-preview-workout="${escapeHtml(item.idtreino)}" data-workout-date="${escapeHtml(item.data_treino)}"><span>${completed ? '✓ ' : ''}${escapeHtml(item.hora_inicio)}${item.termina_dia_seguinte ? ` · ${tr('schedule.next_day')}` : ''}${status}</span><strong>${item.codigo ? `<b class="workout-code-inline">${escapeHtml(item.codigo)}</b> ` : ''}${escapeHtml(item.titulo)}</strong>${item.foco ? `<small>${escapeHtml(item.foco)}</small>` : ''}</button>${menu}</article>`;
    };
    const plannedGhostMarkup = item => `<article class="monthly-event is-recurring is-plan-ghost" data-planned-date="${escapeHtml(item.data_planejada || '')}" data-planned-time="${escapeHtml(item.hora_planejada || '')}" data-realized-date="${escapeHtml(item.data_realizada || '')}" data-realized-time="${escapeHtml(item.hora_realizada || '')}" data-completed="1"><button type="button" class="schedule-month-event-main" data-preview-workout="${escapeHtml(item.idtreino)}" data-workout-date="${escapeHtml(item.data_planejada || '')}" data-occurrence-original="${escapeHtml(item.data_original || '')}"><span>${escapeHtml(item.hora_planejada || '')} ${escapeHtml(tr('schedule.planned_badge'))}</span><strong>${item.codigo ? `<b class="workout-code-inline">${escapeHtml(item.codigo)}</b> ` : ''}${escapeHtml(item.titulo)}</strong></button></article>`;
    const renderMonth = (key, data) => {
        if (!monthGrid) return;
        const range = monthRange(key);
        if (!range) return;
        const occurrenceRows = data.ocorrencias || [];
        const recurring = groupByDate(occurrenceRows);
        const plannedGhosts = groupByDate(occurrenceRows.filter(item => item.concluido && item.data_planejada && item.data_planejada !== item.data_treino).map(item => ({...item, data_treino:item.data_planejada})));
        const scheduled = groupByDate(data.agendados || []);
        const today = localDate();
        let html = weekdayShorts().map(day => `<div class="monthly-weekday">${escapeHtml(day)}</div>`).join('');
        html += Array.from({length:range.leading}, () => '<div class="monthly-day is-outside" aria-hidden="true"></div>').join('');
        for (let day = 1; day <= range.days; day++) {
            const date = `${key}-${String(day).padStart(2, '0')}`;
            const visibleRows = [...(scheduled[date] || []).map(scheduledMarkup), ...(recurring[date] || []).map(occurrenceMarkup)];
            const ghostRows = (plannedGhosts[date] || []).map(plannedGhostMarkup);
            const rows = [...visibleRows, ...ghostRows];
            const empty = visibleRows.length ? '' : '<span class="schedule-month-empty-day">+</span>';
            html += `<article class="monthly-day${date === today ? ' is-today' : ''}" data-calendar-date="${date}" tabindex="0" aria-label="${escapeHtml(tr('schedule.add_workout_on',{date}))}"${date === today ? ' id="schedule-today"' : ''}><div class="schedule-month-day-heading"><strong>${day}</strong>${date === today ? `<span>${escapeHtml(tr('common.today'))}</span>` : ''}</div><div class="monthly-events">${empty}${rows.join('')}</div></article>`;
        }
        const usedCells = range.leading + range.days;
        const trailing = (7 - (usedCells % 7)) % 7;
        html += Array.from({length:trailing}, () => '<div class="monthly-day is-outside" aria-hidden="true"></div>').join('');
        monthGrid.innerHTML = html;
        monthGrid.classList.remove('is-loading');
        monthShell.dataset.currentMonth = key;
        if (monthTitleTarget) monthTitleTarget.textContent = monthLabel(key);
        monthShell.querySelectorAll('[data-month-nav]').forEach(link => {
            const direction = link.getAttribute('aria-label') === tr('schedule.previous_month') ? -1 : (link.getAttribute('aria-label') === tr('schedule.next_month') ? 1 : 0);
            if (direction) link.dataset.monthNav = monthKeyShift(key, direction);
        });
    };
    const fetchMonth = async (key, {render=true, force=false, showSkeleton=true}={}) => {
        const schedule = monthShell?.dataset.scheduleId || '';
        const range = monthRange(key);
        if (!monthShell || !monthGrid || !schedule || !range) return null;
        if (!force && monthCache.has(key)) {
            const cached = monthCache.get(key);
            if (render) renderMonth(key, cached);
            return cached;
        }
        const renderToken = render ? ++monthRenderToken : monthRenderToken;
        let skeletonTimer = 0;
        if (render) {
            if (monthTitleTarget) monthTitleTarget.textContent = monthLabel(key);
            if (showSkeleton) {
                skeletonTimer = window.setTimeout(() => {
                    if (renderToken === monthRenderToken && !monthCache.has(key)) renderMonthSkeleton(key);
                }, 110);
            }
        }
        let entry = !force ? monthRequests.get(key) : null;
        if (!entry) {
            const controller = new AbortController();
            const promise = (async () => {
                const response = await (window.StrideBRNet?.fetch || fetch)(`/api/cronograma-ocorrencias.php?start=${encodeURIComponent(range.start)}&end=${encodeURIComponent(range.end)}&schedule=${encodeURIComponent(schedule)}`, {headers:{'Accept':'application/json'}, signal:controller.signal}, 10000);
                const data = await response.json();
                if (!response.ok || !data.ok) throw new Error(data.error || tr('schedule.month_load_error'));
                monthCache.set(key, data);
                return data;
            })();
            entry = {promise, controller};
            monthRequests.set(key, entry);
            promise.finally(() => { if (monthRequests.get(key) === entry) monthRequests.delete(key); }).catch(() => {});
        }
        if (render) {
            if (activeMonthController && activeMonthController !== entry.controller) activeMonthController.abort();
            activeMonthController = entry.controller;
        }
        try {
            const data = await entry.promise;
            if (render && renderToken === monthRenderToken) renderMonth(key, data);
            return data;
        } catch (error) {
            if (error?.name === 'AbortError') return null;
            throw error;
        } finally {
            if (skeletonTimer) window.clearTimeout(skeletonTimer);
            if (render && activeMonthController === entry.controller) activeMonthController = null;
        }
    };
    const prefetchNeighbors = key => {
        [monthKeyShift(key,-1), monthKeyShift(key,1)].forEach(next => {
            if (!monthCache.has(next) && !monthRequests.has(next)) fetchMonth(next, {render:false}).catch(() => {});
        });
    };
    const navigateMonth = async (key, {historyMode='push'}={}) => {
        if (!monthParts(key) || !monthShell) return;
        monthShell.dataset.currentMonth = key;
        if (monthTitleTarget) monthTitleTarget.textContent = monthLabel(key);
        const url = new URL(window.location.href);
        url.searchParams.set('view','month');
        url.searchParams.set('month',key);
        const nextUrl = `${url.pathname}${url.search}${url.hash}`;
        if (historyMode === 'push' && nextUrl !== `${location.pathname}${location.search}${location.hash}`) history.pushState({stridebrMonth:key},'',nextUrl);
        else if (historyMode === 'replace') history.replaceState({stridebrMonth:key},'',nextUrl);
        try {
            await fetchMonth(key);
            prefetchNeighbors(key);
        } catch (error) {
            if (monthGrid) {
                monthGrid.classList.remove('is-loading');
                monthGrid.innerHTML = `<div class="calendar-inline-error"><strong>${escapeHtml(tr('schedule.month_load_error'))}</strong><span>${escapeHtml(tr('schedule.connection_wobbled'))}</span><button type="button" class="secondary-button" data-retry-month>${escapeHtml(tr('schedule.retry'))}</button></div>`;
            }
        }
    };
    monthShell?.addEventListener('click', event => {
        const nav = event.target.closest('[data-month-nav]');
        if (nav) { event.preventDefault(); navigateMonth(nav.dataset.monthNav || '', {historyMode:'push'}); return; }
        if (event.target.closest('[data-retry-month]')) { navigateMonth(monthShell.dataset.currentMonth || '', {historyMode:'none'}); return; }
        const day = event.target.closest('[data-calendar-date]');
        if (!day || event.target.closest('.monthly-event,button,a,input,select,textarea,details,summary,form')) return;
        openQuickCreate({mode:'schedule', date:day.dataset.calendarDate || localDate(), anchor:day});
    });
    monthShell?.addEventListener('keydown', event => {
        if (!['Enter', ' '].includes(event.key)) return;
        const day = event.target.closest('[data-calendar-date]');
        if (!day || event.target.closest('.monthly-event,button,a,input,select,textarea,details,summary,form')) return;
        event.preventDefault();
        openQuickCreate({mode:'schedule', date:day.dataset.calendarDate || localDate(), anchor:day});
    });
    if (monthShell?.dataset.currentMonth) {
        const initialMonth = monthShell.dataset.currentMonth;
        if (currentView === 'month') {
            navigateMonth(initialMonth, {historyMode:'replace'});
        } else {
            const idle = window.requestIdleCallback || (callback => window.setTimeout(callback, 180));
            idle(() => fetchMonth(initialMonth, {render:false}).then(() => prefetchNeighbors(initialMonth)).catch(() => {}));
        }
    }
    viewButtons.forEach(button => button.addEventListener('click', () => {
        if (button.dataset.view === 'month' && monthShell?.dataset.currentMonth) navigateMonth(monthShell.dataset.currentMonth, {historyMode:'replace'});
    }));
    document.querySelector('[data-open-month-view]')?.addEventListener('click', event => {
        event.preventDefault();
        activateView('month');
        if (monthShell?.dataset.currentMonth) navigateMonth(monthShell.dataset.currentMonth, {historyMode:'replace'});
    });


    window.addEventListener('popstate', () => {
        const params = new URLSearchParams(location.search);
        const key = params.get('month');
        if (params.get('view') === 'month' && key && monthParts(key)) {
            activateView('month');
            navigateMonth(key, {historyMode:'none'});
        }
    });

    const occurrenceModal = document.querySelector('[data-occurrence-modal]');
    const occurrenceForm = occurrenceModal?.querySelector('[data-occurrence-form]');
    const occurrenceHistoryModal = document.querySelector('[data-occurrence-history-modal]');
    const occurrenceHistoryForm = occurrenceHistoryModal?.querySelector('[data-occurrence-history-form]');
    if (occurrenceModal && occurrenceModal.parentElement !== document.body) document.body.appendChild(occurrenceModal);
    if (occurrenceHistoryModal && occurrenceHistoryModal.parentElement !== document.body) document.body.appendChild(occurrenceHistoryModal);
    let occurrenceReturnFocus = null;
    const closeOccurrence = () => {
        if (!occurrenceModal) return;
        occurrenceModal.hidden = true;
        if (occurrenceReturnFocus?.isConnected) occurrenceReturnFocus.focus({preventScroll: true});
    };
    const openOccurrence = payload => {
        if (!occurrenceModal || !occurrenceForm) return;
        occurrenceForm.querySelector('[data-occurrence-id]').value = payload.id || '';
        occurrenceForm.querySelector('[data-occurrence-original]').value = payload.original || '';
        occurrenceForm.querySelector('[data-occurrence-new-date]').value = payload.date || payload.original || '';
        const timeInput = occurrenceForm.querySelector('[data-occurrence-new-time]');
        if (timeInput) { timeInput.value = payload.time || '18:00'; window.StrideBRTime24?.set?.(timeInput, timeInput.value); }
        const title = occurrenceModal.querySelector('[data-occurrence-title]');
        if (title) title.textContent = payload.title ? tr('schedule.reschedule_named', {name: payload.title}) : tr('schedule.reschedule_short');
        occurrenceForm.querySelector('input[name="scope"][value="this"]').checked = true;
        occurrenceReturnFocus = document.activeElement;
        occurrenceModal.hidden = false;
        occurrenceForm.querySelector('[data-occurrence-new-date]')?.focus({preventScroll: true});
    };
    occurrenceModal?.querySelectorAll('[data-close-occurrence]').forEach(button => button.addEventListener('click', closeOccurrence));
    occurrenceModal?.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !occurrenceModal.querySelector('[data-time24-menu]:not([hidden])')) {
            event.preventDefault();
            closeOccurrence();
        }
    });
    const postOccurrence = async (payload, {refreshMonth = true} = {}) => {
        const body = new URLSearchParams({...payload, csrf_token:csrfToken});
        const response = await (window.StrideBRNet?.fetch || fetch)('/api/cronograma-ocorrencias.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','Accept':'application/json'}, body:body}, 15000);
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.error || tr('schedule.change_error'));
        monthCache.clear();
        if (refreshMonth && monthShell?.dataset.currentMonth) {
            await fetchMonth(monthShell.dataset.currentMonth, {force:true, showSkeleton:false});
            prefetchNeighbors(monthShell.dataset.currentMonth);
        }
        return data;
    };
    const showScheduleToast = message => {
        if (window.StrideBRUI?.notify) window.StrideBRUI.notify(message, 'success')
    };

    const pendingScheduleToast = sessionStorage.getItem('stridebr.schedule.toast');
    if (pendingScheduleToast) {
        sessionStorage.removeItem('stridebr.schedule.toast');
        showScheduleToast(pendingScheduleToast);
    }
    occurrenceForm?.addEventListener('submit', async event => {
        event.preventDefault();
        const submit = occurrenceForm.querySelector('button[type="submit"]');
        if (submit) submit.disabled = true;
        try {
            const scope = occurrenceForm.querySelector('input[name="scope"]:checked')?.value || 'this';
            await postOccurrence({action:'move', idtreino:occurrenceForm.querySelector('[data-occurrence-id]').value, data_original:occurrenceForm.querySelector('[data-occurrence-original]').value, data_treino:occurrenceForm.querySelector('[data-occurrence-new-date]').value, hora_inicio:occurrenceForm.querySelector('[data-occurrence-new-time]')?.value || '', scope}, {refreshMonth: currentView === 'month'});
            closeOccurrence();
            if (currentView !== 'month') await refreshScheduleView();
            showScheduleToast(tr('schedule.planning_updated_success'));
        } catch (error) { uiNotify(error.message); }
        finally { if (submit) submit.disabled = false; }
    });
    occurrenceModal?.querySelector('[data-skip-occurrence]')?.addEventListener('click', async () => {
        if (!occurrenceForm || !await uiConfirm(tr('schedule.skip_confirm'), {title:tr('schedule.skip_title'), confirmLabel:tr('schedule.skip_button'), danger:true})) return;
        try { await postOccurrence({action:'cancel', idtreino:occurrenceForm.querySelector('[data-occurrence-id]').value, data_original:occurrenceForm.querySelector('[data-occurrence-original]').value}); closeOccurrence(); }
        catch (error) { uiNotify(error.message); }
    });

    const closeOccurrenceHistory = () => {
        if (!occurrenceHistoryModal) return;
        occurrenceHistoryModal.hidden = true;
        document.documentElement.style.overflow = previewModal && !previewModal.hidden ? 'hidden' : '';
    };
    const setTime24Value = (selector, value) => {
        const input = occurrenceHistoryForm?.querySelector(selector);
        if (!input) return;
        input.value = value || '';
        window.StrideBRTime24?.set?.(input, input.value);
    };
    const openOccurrenceHistory = () => {
        if (!occurrenceHistoryModal || !occurrenceHistoryForm || !previewActivityId) return;
        occurrenceHistoryForm.querySelector('[data-history-activity-id]').value = previewActivityId;
        occurrenceHistoryForm.querySelector('[data-history-planned-date]').value = previewPlannedDate || previewRealizedDate || '';
        occurrenceHistoryForm.querySelector('[data-history-realized-date]').value = previewRealizedDate || previewPlannedDate || '';
        setTime24Value('[data-history-planned-time]', previewPlannedTime || previewRealizedTime || '');
        setTime24Value('[data-history-realized-time]', previewRealizedTime || previewPlannedTime || '');
        occurrenceHistoryModal.hidden = false;
        document.documentElement.style.overflow = 'hidden';
    };
    occurrenceHistoryModal?.querySelectorAll('[data-close-occurrence-history]').forEach(button => button.addEventListener('click', closeOccurrenceHistory));
    occurrenceHistoryForm?.addEventListener('submit', async event => {
        event.preventDefault();
        const submit = occurrenceHistoryForm.querySelector('button[type="submit"]');
        if (submit) submit.disabled = true;
        try {
            const fd = new FormData(occurrenceHistoryForm);
            const body = new URLSearchParams();
            body.set('csrf_token', csrfToken);
            body.set('action', 'set_occurrence_history');
            for (const [key, value] of fd.entries()) body.set(key, String(value));
            const response = await (window.StrideBRNet?.fetch || fetch)('/api/cronograma-ocorrencias.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','Accept':'application/json'}, body:body}, 15000);
            const data = await response.json().catch(() => null);
            if (!response.ok || !data?.ok) throw new Error(data?.error || tr('schedule.date_fix_error'));
            closeOccurrenceHistory();
            closePreview();
            monthCache.clear();
            await refreshScheduleView();
            showScheduleToast(tr('schedule.plan_actual_fixed_success'));
        } catch (error) {
            uiNotify(error?.message || tr('schedule.date_fix_error'));
        } finally {
            if (submit) submit.disabled = false;
        }
    });

    previewModal?.addEventListener('click', async event => {
        const adjustHistory = event.target.closest('[data-preview-adjust-history]');
        if (adjustHistory) {
            openOccurrenceHistory();
            return;
        }
        const move = event.target.closest('[data-preview-move]');
        if (move) {
            if (!previewOccurrenceOriginal || !previewWorkoutId) return;
            const item = editorWorkoutMap.get(previewWorkoutId);
            closePreview();
            openOccurrence({id:previewWorkoutId, original:previewOccurrenceOriginal, date:previewPlannedDate || previewWorkoutDate || previewOccurrenceOriginal, time:previewPlannedTime || item?.hora_inicio || '', title:item?.titulo || ''});
            return;
        }
        const skip = event.target.closest('[data-preview-skip]');
        if (skip) {
            if (!previewOccurrenceOriginal || !previewWorkoutId || !await uiConfirm(tr('schedule.skip_preview_confirm'), {title:tr('schedule.skip_title'), confirmLabel:tr('schedule.skip_button'), danger:true})) return;
            skip.disabled = true;
            try { await postOccurrence({action:'cancel', idtreino:previewWorkoutId, data_original:previewOccurrenceOriginal}); closePreview(); showScheduleToast(tr('schedule.skipped_week')); }
            catch (error) { uiNotify(error.message); }
            finally { skip.disabled = false; }
        }
    });

    document.addEventListener('click', event => {
        const dynamicPreview = event.target.closest('[data-preview-workout]');
        if (dynamicPreview && !dynamicPreview.dataset.previewBound) {
            showWorkoutPreview(dynamicPreview);
        }
        const dynamicQuickRegister = event.target.closest('[data-quick-register-workout]');
        if (dynamicQuickRegister && !dynamicQuickRegister.dataset.quickRegisterBound) {
            openQuickRegisterFromButton(dynamicQuickRegister);
        }
        const dynamicStart = event.target.closest('[data-start-workout]');
        if (dynamicStart && !dynamicStart.dataset.startWorkoutBound) {
            startWorkoutFromButton(dynamicStart);
        }
        const move = event.target.closest('[data-move-occurrence]');
        if (move) {
            const item = move.closest('[data-occurrence-workout]');
            if (item) openOccurrence({id:item.dataset.occurrenceWorkout, original:item.dataset.occurrenceOriginal, date:item.dataset.occurrenceDate, time:item.dataset.plannedTime || '', title:item.dataset.occurrenceTitle});
        }
        const scheduledStart = event.target.closest('[data-start-scheduled-workout]');
        if (scheduledStart && window.StrideBRWorkout?.startScheduled) {
            const id = scheduledStart.dataset.startScheduledWorkout;
            scheduledStart.disabled = true;
            window.StrideBRWorkout.startScheduled(id).finally(() => { scheduledStart.disabled = false; });
        }
    });

    document.addEventListener('click', async event => {
        const toggleButton = event.target.closest('[data-week-choices-toggle]');
        if (toggleButton) {
            const choices = document.querySelector('[data-week-choices]');
            if (!choices) return;
            const willOpen = choices.hidden;
            choices.hidden = !willOpen;
            toggleButton.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            toggleButton.textContent = willOpen ? tr('schedule.closed') : tr('schedule.swap');
            return;
        }
        const choice = event.target.closest('[data-week-choice]');
        if (!choice) return;
        const summary = document.querySelector('[data-week-summary]');
        const main = document.querySelector('.schedule-week-next-main[data-preview-workout]');
        const choices = document.querySelector('[data-week-choices]');
        const toggle = document.querySelector('[data-week-choices-toggle]');
        if (!summary || !main) return;
        choice.disabled = true;
        const selectedId = String(choice.dataset.weekChoice || '');
        const previousId = String(main.dataset.previewWorkout || '');
        try {
            await postOccurrence({
                action:'set_next',
                idcronograma:summary.dataset.scheduleId || '',
                semana_inicio:summary.dataset.weekStart || '',
                idtreino_proximo:selectedId,
            }, {refreshMonth:false});

            const selected = editorWorkoutMap.get(selectedId);
            const previous = editorWorkoutMap.get(previousId);
            const occurrence = document.querySelector(`[data-week-card][data-occurrence-workout="${CSS.escape(selectedId)}"]`);
            const renderMain = item => {
                main.replaceChildren();
                if (item?.codigo) {
                    const code = document.createElement('span');
                    code.className = 'schedule-week-next-code';
                    code.textContent = item.codigo;
                    main.append(code);
                }
                const copy = document.createElement('span');
                copy.className = 'schedule-week-next-copy';
                const strong = document.createElement('strong');
                strong.textContent = item?.titulo || tr('schedule.workout_fallback');
                copy.append(strong);
                if (item?.foco) {
                    const small = document.createElement('small');
                    small.textContent = item.foco;
                    copy.append(small);
                }
                main.append(copy);
            };
            const renderChoice = item => {
                choice.replaceChildren();
                if (item?.codigo) {
                    const code = document.createElement('span');
                    code.className = 'schedule-week-choice-code';
                    code.textContent = item.codigo;
                    choice.append(code);
                }
                const copy = document.createElement('span');
                const strong = document.createElement('strong');
                strong.textContent = item?.titulo || tr('schedule.workout_fallback');
                copy.append(strong);
                if (item?.foco) {
                    const small = document.createElement('small');
                    small.textContent = item.foco;
                    copy.append(small);
                }
                choice.append(copy);
            };

            main.dataset.previewWorkout = selectedId;
            if (occurrence) {
                main.dataset.occurrenceOriginal = occurrence.dataset.occurrenceOriginal || '';
                main.dataset.plannedDate = occurrence.dataset.occurrenceDate || '';
                main.dataset.plannedTime = occurrence.querySelector('[data-card-time]')?.textContent?.trim()?.slice(0,5) || '';
            }
            renderMain(selected);
            if (previousId && previous) {
                choice.dataset.weekChoice = previousId;
                renderChoice(previous);
                choice.disabled = false;
            } else {
                choice.remove();
            }
            if (choices) choices.hidden = true;
            if (toggle) {
                toggle.textContent = tr('schedule.swap');
                toggle.setAttribute('aria-expanded', 'false');
            }
            syncCompactWeekSummaries(weekStatsFromCards(), selectedId);
            showScheduleToast(tr('schedule.next_updated'));
        } catch (error) {
            choice.disabled = false;
            uiNotify(error.message);
        }
    });


    document.addEventListener('dragstart', event => {
        const occurrence = event.target.closest('[data-occurrence-workout]');
        const library = event.target.closest('[data-library-workout]');
        if (occurrence) {
            draggedOccurrence = {
                id:occurrence.dataset.occurrenceWorkout,
                original:occurrence.dataset.occurrenceOriginal,
                date:occurrence.dataset.occurrenceDate,
                time:occurrence.dataset.plannedTime || '',
                title:occurrence.dataset.occurrenceTitle,
                duration:Number(occurrence.dataset.durationMin || 0),
                element:occurrence,
                week:occurrence.hasAttribute('data-week-card'),
                grabOffset:occurrence.hasAttribute('data-week-card') ? Math.max(0, ((event.clientY - occurrence.getBoundingClientRect().top) / Math.max(1, calendarHourHeight)) * 60) : 0
            };
            draggedLibrary = null;
            occurrence.classList.add('is-dragging');
            if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
        } else if (library) {
            draggedLibrary = {id:library.dataset.libraryWorkout, title:library.dataset.libraryTitle};
            draggedOccurrence = null;
            library.classList.add('is-dragging');
        }
    });
    document.addEventListener('dragend', event => {
        event.target.closest('[data-occurrence-workout],[data-library-workout]')?.classList.remove('is-dragging');
        document.querySelectorAll('.monthly-day.is-drop-target,.day-track.is-drop-target').forEach(day => day.classList.remove('is-drop-target'));
        document.querySelectorAll('.workout-card.is-swap-target,.monthly-event.is-swap-target').forEach(card => card.classList.remove('is-swap-target'));
        draggedOccurrence = null;
        draggedLibrary = null;
    });
    monthGrid?.addEventListener('dragover', event => {
        const day = event.target.closest('[data-calendar-date]');
        if (!day || (!draggedOccurrence && !draggedLibrary)) return;
        event.preventDefault();
        const swapTarget = draggedOccurrence ? event.target.closest('[data-occurrence-workout][draggable="true"]') : null;
        document.querySelectorAll('.monthly-event.is-swap-target').forEach(card => card.classList.toggle('is-swap-target', card === swapTarget && card !== draggedOccurrence?.element));
        document.querySelectorAll('.monthly-day.is-drop-target').forEach(item => item.classList.toggle('is-drop-target', item === day && (!swapTarget || swapTarget === draggedOccurrence?.element)));
    });
    monthGrid?.addEventListener('drop', async event => {
        const day = event.target.closest('[data-calendar-date]');
        if (!day) return;
        event.preventDefault();
        const date = day.dataset.calendarDate;
        day.classList.remove('is-drop-target');
        const swapTarget = event.target.closest('[data-occurrence-workout][draggable="true"]');
        document.querySelectorAll('.monthly-event.is-swap-target').forEach(card => card.classList.remove('is-swap-target'));
        if (draggedOccurrence) {
            const item = draggedOccurrence;
            draggedOccurrence = null;
            if (swapTarget && swapTarget !== item.element) {
                try {
                    await postOccurrence({
                        action:'swap',
                        idtreino:item.id,
                        data_original:item.original,
                        outro_idtreino:swapTarget.dataset.occurrenceWorkout || '',
                        outra_data_original:swapTarget.dataset.occurrenceOriginal || '',
                    });
                    showScheduleToast(tr('schedule.swapped_week'));
                } catch (error) {
                    uiNotify(error.message);
                }
                return;
            }
            openOccurrence({...item, date});
            return;
        }
        if (draggedLibrary && monthShell?.dataset.scheduleId) {
            const item = draggedLibrary;
            draggedLibrary = null;
            openQuickCreate({mode:'schedule', date, sourceModel:item.id, anchor:day});
        }
    });

    const formatMinutes = minutes => `${String(Math.floor(minutes / 60) % 24).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
    const weekCardStart = card => Math.max(0, Number(card?.style.getPropertyValue('--start-min') || 0));
    const placeWeekCard = (card, day, date, startMinutes, duration) => {
        if (!card || !day) return;
        day.appendChild(card);
        card.style.setProperty('--start-min', String(startMinutes));
        card.style.setProperty('--duration-min', String(Math.max(30, duration)));
        card.dataset.occurrenceDate = date;
        card.dataset.workoutDate = date;
        card.dataset.plannedDate = date;
        card.dataset.plannedTime = formatMinutes(startMinutes);
        card.classList.add('is-exception');
        card.classList.remove('is-dragging', 'is-swap-target');
        const endTotal = startMinutes + duration;
        const endTime = formatMinutes(endTotal % 1440);
        const time = card.querySelector('[data-card-time]');
        if (time) time.textContent = `${formatMinutes(startMinutes)}–${endTime}${endTotal >= 1440 ? ' +1' : ''}`;
    };
    weekCalendar?.addEventListener('dragover', event => {
        const day = event.target.closest('[data-week-date]');
        if (!day || !draggedOccurrence?.week) return;
        event.preventDefault();
        if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
        const swapTarget = event.target.closest('[data-week-card][draggable="true"]');
        document.querySelectorAll('.workout-card.is-swap-target').forEach(card => card.classList.toggle('is-swap-target', card === swapTarget && card !== draggedOccurrence.element));
        document.querySelectorAll('.day-track.is-drop-target').forEach(item => item.classList.toggle('is-drop-target', item === day && (!swapTarget || swapTarget === draggedOccurrence.element)));
    });
    weekCalendar?.addEventListener('dragleave', event => {
        const day = event.target.closest('[data-week-date]');
        if (!day || day.contains(event.relatedTarget)) return;
        day.classList.remove('is-drop-target');
    });
    weekCalendar?.addEventListener('drop', async event => {
        const day = event.target.closest('[data-week-date]');
        if (!day || !draggedOccurrence?.week) return;
        event.preventDefault();
        const item = draggedOccurrence;
        const swapCard = event.target.closest('[data-week-card][draggable="true"]');
        draggedOccurrence = null;
        day.classList.remove('is-drop-target');
        document.querySelectorAll('.workout-card.is-swap-target').forEach(card => card.classList.remove('is-swap-target'));
        const duration = Math.max(1, Number(item.duration || 60));
        try {
            if (swapCard && swapCard !== item.element) {
                const sourceCard = item.element;
                const sourceDay = sourceCard?.closest('[data-week-date]');
                const targetDay = swapCard.closest('[data-week-date]');
                if (!sourceCard || !sourceDay || !targetDay) throw new Error(tr('schedule.swap_error'));
                const sourceDate = sourceDay.dataset.weekDate || item.date;
                const targetDate = targetDay.dataset.weekDate || swapCard.dataset.occurrenceDate || '';
                const sourceStart = weekCardStart(sourceCard);
                const targetStart = weekCardStart(swapCard);
                const targetDuration = Math.max(1, Number(swapCard.dataset.durationMin || 60));
                await postOccurrence({
                    action:'swap',
                    idtreino:item.id,
                    data_original:item.original,
                    outro_idtreino:swapCard.dataset.occurrenceWorkout || '',
                    outra_data_original:swapCard.dataset.occurrenceOriginal || '',
                }, {refreshMonth:false});
                placeWeekCard(sourceCard, targetDay, targetDate, targetStart, duration);
                placeWeekCard(swapCard, sourceDay, sourceDate, sourceStart, targetDuration);
                showScheduleToast(tr('schedule.swapped_week'));
                return;
            }
            const rect = day.getBoundingClientRect();
            const rawMinutes = (((event.clientY - rect.top) / Math.max(1, calendarHourHeight)) * 60) - Number(item.grabOffset || 0);
            const startMinutes = Math.max(0, Math.min(1425, Math.round(rawMinutes / 15) * 15));
            const startTime = formatMinutes(startMinutes);
            const targetDate = day.dataset.weekDate || item.date;
            await postOccurrence({action:'move', idtreino:item.id, data_original:item.original, data_treino:targetDate, hora_inicio:startTime, scope:'this'}, {refreshMonth:false});
            const card = item.element;
            if (!card?.isConnected || startMinutes + duration >= 1440) {
                await refreshScheduleView();
                showScheduleToast(tr('schedule.moved_week'));
                return;
            }
            placeWeekCard(card, day, targetDate, startMinutes, duration);
            showScheduleToast(tr('schedule.moved_week'));
        } catch (error) {
            item.element?.classList.remove('is-dragging');
            uiNotify(error.message);
        }
    });

});
