(() => {
    const t = (key, values = {}, fallback = key) => window.StrideBRI18n?.t?.(key, values, fallback) ?? fallback
    const tn = (oneKey, otherKey, count, values = {}) => window.StrideBRI18n?.tn?.(oneKey, otherKey, count, values) ?? t(Number(count) === 1 ? oneKey : otherKey, {...values, count})
    const workoutPrescription = window.StrideBRWorkoutPrescription
    const root = document.querySelector('[data-global-tools]');
    if (!root) return;
    const csrf = root.dataset.csrf || '';
    const modal = root.querySelector('[data-workout-session-modal]');
    const pill = root.querySelector('[data-active-workout-pill]');
    const title = root.querySelector('[data-session-title]');
    const progress = root.querySelector('[data-session-progress]');
    const exercisesContainer = root.querySelector('[data-session-exercises]');
    const sessionTime = root.querySelector('[data-session-time]');
    const pillTime = root.querySelector('[data-active-workout-time]');
    const pillTitle = root.querySelector('[data-active-workout-title]');
    const pillSummary = root.querySelector('[data-active-workout-summary]');
    const finishSheet = root.querySelector('[data-workout-finish-sheet]');
    const finishSummary = root.querySelector('[data-workout-finish-summary]');
    const finishNotes = root.querySelector('[data-finish-notes]');
    const finishStart = root.querySelector('[data-finish-start]');
    const finishEnd = root.querySelector('[data-finish-end]');
    const finishDurationPreview = root.querySelector('[data-finish-duration-preview]');
    const markAllButton = root.querySelector('[data-mark-all-workout]');
    const completeModal = root.querySelector('[data-workout-complete-modal]');
    const completeTitle = root.querySelector('[data-workout-complete-title]');
    const completeLead = root.querySelector('[data-workout-complete-lead]');
    const completeDuration = root.querySelector('[data-workout-complete-duration]');
    const completeExercises = root.querySelector('[data-workout-complete-exercises]');
    const completeSets = root.querySelector('[data-workout-complete-sets]');
    const completeFeedback = root.querySelector('[data-workout-complete-feedback]');
    const completeActivity = root.querySelector('[data-workout-complete-activity]');
    let finishIntensity = '';
    let finishFeeling = '';
    let session = null;
    let historyLoaded = false;
    let elapsedTicker = null;
    const presenceCacheKey = 'stridebr.workout.presence.v1';
    const presenceCacheTtl = 30000;
    const rememberPresence = hasSession => {
        try { sessionStorage.setItem(presenceCacheKey, JSON.stringify({checkedAt: Date.now(), hasSession: Boolean(hasSession)})); } catch (_) {}
    };
    const recentlyKnownAbsent = () => {
        try {
            const cached = JSON.parse(sessionStorage.getItem(presenceCacheKey) || 'null');
            return cached && cached.hasSession === false && Date.now() - Number(cached.checkedAt || 0) < presenceCacheTtl;
        } catch (_) { return false; }
    };

    const notify = (message, type = 'error') => {
        if (window.StrideBRUI?.notify) window.StrideBRUI.notify(message, type)
        else console.error(message)
    };
    const confirmAction = message => window.StrideBRUI?.confirm
        ? window.StrideBRUI.confirm(message, {title: t('common.confirm_action', {}, 'Confirm action'), confirmLabel: t('workout_session.confirm', {}, 'Confirm'), danger: true})
        : Promise.resolve(window.confirm(message));

    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
    const localDateTimeValue = value => {
        const normalized = typeof value === 'string' ? value.replace(' ', 'T') : value;
        const date = value ? new Date(normalized) : new Date();
        if (Number.isNaN(date.getTime())) return '';
        const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
        return local.toISOString().slice(0, 16);
    };
    const updateFinishDuration = () => {
        if (!finishDurationPreview || !finishStart?.value || !finishEnd?.value) return;
        const start = new Date(finishStart.value);
        const end = new Date(finishEnd.value);
        const seconds = Math.max(0, Math.floor((end.getTime() - start.getTime()) / 1000));
        if (!Number.isFinite(seconds) || seconds <= 0) {
            finishDurationPreview.textContent = t('workout_session.check_times', {}, 'Check the times.');
            return;
        }
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        finishDurationPreview.textContent = t('workout_session.duration_registered', {duration: `${hours > 0 ? `${hours}h ` : ''}${minutes}min`}, `Recorded duration: ${hours > 0 ? `${hours}h ` : ''}${minutes}min`);
    };
    const postRequest = async payload => {
        const body = new FormData();
        body.set('csrf_token', csrf);
        Object.entries(payload).forEach(([key, value]) => body.set(key, String(value)));
        const response = await (window.StrideBRNet?.fetch || fetch)('/function/treino_sessao.php', {method: 'POST', body, credentials: 'same-origin'}, 15000);
        const data = await response.json().catch(() => ({ok: false, message: t('workout_session.invalid_server_response', {}, 'Invalid server response.')}));
        if (!response.ok && !data.session) throw new Error(data.message || t('workout_session.update_error', {}, 'Could not update the workout.'));
        return data;
    };
    let mutationQueue = Promise.resolve();
    const post = payload => {
        const request = mutationQueue.then(() => postRequest(payload));
        mutationQueue = request.catch(() => {});
        return request;
    };
    const fetchCurrent = async ({force = false, includeHistory = false} = {}) => {
        if (!force && recentlyKnownAbsent()) {
            session = null;
            historyLoaded = false;
            render();
            return;
        }
        try {
            const response = await (window.StrideBRNet?.fetch || fetch)(`/function/treino_sessao.php?action=current&history=${includeHistory ? '1' : '0'}`, {credentials: 'same-origin', headers: {'Accept': 'application/json'}}, includeHistory ? 15000 : 8000);
            const data = await response.json();
            session = preserveHistory(data.session || null);
            historyLoaded = Boolean(session && (includeHistory || historyLoaded));
            rememberPresence(Boolean(session));
            render();
        } catch (_) {
            session = null;
            historyLoaded = false;
            render();
        }
    };

    const elapsedText = () => {
        const start = session?.started_at_ms;
        if (typeof start !== 'number' || !Number.isFinite(start) || start <= 0) return '00:00';
        const seconds = Math.max(0, Math.floor((Date.now() - start) / 1000));
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        return hours > 0
            ? `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
            : `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    };
    const stats = () => {
        const exercises = session?.exercicios || [];
        const totalExercises = exercises.length;
        const doneExercises = exercises.filter(item => item.concluido).length;
        const sets = exercises.flatMap(item => item.series || []);
        return {totalExercises, doneExercises, totalSets: sets.length, doneSets: sets.filter(item => item.concluida).length};
    };
    const progressText = (doneExercises, totalExercises, doneSets, totalSets) => `${doneExercises}/${totalExercises} ${tn('workout_session.exercise_unit.one', 'workout_session.exercise_unit.other', totalExercises)} · ${doneSets}/${totalSets} ${tn('workout_session.set_unit.one', 'workout_session.set_unit.other', totalSets)}`
    const setCountText = (done, total) => `${done}/${total} ${tn('workout_session.set_unit.one', 'workout_session.set_unit.other', total)}`

    const preserveHistory = next => {
        if (!next || !Array.isArray(next.exercicios) || !session?.exercicios) return next;
        const previous = new Map(session.exercicios.map(item => [String(item.idsessao_exercicio || ''), item.historico || {}]));
        next.exercicios = next.exercicios.map(item => {
            const history = item.historico || {};
            if (history.ultima) return item;
            const old = previous.get(String(item.idsessao_exercicio || ''));
            return old?.ultima ? {...item, historico: old} : item;
        });
        return next;
    };
    const parseRestSeconds = value => {
        const text = String(value || '').trim().toLowerCase();
        if (!text) return 0;
        let match = text.match(/^(\d+):([0-5]\d)$/);
        if (match) return Number(match[1]) * 60 + Number(match[2]);
        match = text.match(/(\d+(?:[.,]\d+)?)\s*(min|minuto|minutos|m)\b/);
        if (match) return Math.round(Number(match[1].replace(',', '.')) * 60);
        match = text.match(/(\d+)\s*(s|seg|segundo|segundos)\b/);
        if (match) return Number(match[1]);
        if (/^\d+$/.test(text)) return Number(text);
        return 0;
    };

    const formatRepetitions = value => workoutPrescription?.formatReps?.(value) || String(value || '').trim();
    const decodeJson = value => {
        if (!value) return null
        if (typeof value === 'object') return value
        try { return JSON.parse(String(value)) } catch (_) { return null }
    }
    const plannedPrescription = exercise => workoutPrescription?.structured?.(exercise) || workoutPrescription?.resolve?.({
        load: exercise?.carga_snapshot,
        repetitions: exercise?.repeticoes_snapshot,
        duration: exercise?.duracao_snapshot,
        distance: exercise?.distancia_snapshot,
    }) || {mode:'EMPTY',fields:[],values:{},labels:{load:'Carga',reps:'Reps',duration:'Duração',distance:'Distância'},summaryParts:[]};
    const plannedRepText = (set, exercise, prescription) => {
        const target = decodeJson(set?.meta_repeticoes)
        const targetText = workoutPrescription?.repTargetText?.(target || {}) || ''
        if (targetText) return targetText
        const explicit = String(set?.repeticoes_planejadas ?? '').trim()
        if (explicit) return formatRepetitions(explicit)
        return prescription?.values?.reps || String(exercise?.repeticoes_snapshot ?? '').trim()
    }
    const plannedLoadText = (set, exercise, prescription) => {
        const explicit = String(set?.carga_planejada ?? '').trim()
        if (explicit) return workoutPrescription?.formatLoad?.(explicit) || explicit
        return prescription?.values?.load || workoutPrescription?.formatLoad?.(exercise?.carga_snapshot) || String(exercise?.carga_snapshot ?? '').trim()
    }
    const plannedMetricText = (set, field, prescription) => {
        if (field === 'duration') {
            if (set?.duracao_planejada_s !== null && set?.duracao_planejada_s !== undefined) return workoutPrescription?.formatDurationSeconds?.(set.duracao_planejada_s) || String(set.duracao_planejada_s)
            return prescription?.values?.duration || ''
        }
        if (set?.distancia_planejada_m !== null && set?.distancia_planejada_m !== undefined) return workoutPrescription?.formatDistance?.(Number(set.distancia_planejada_m)) || String(set.distancia_planejada_m)
        return prescription?.values?.distance || ''
    }
    const segmentInfo = set => {
        const type = String(set?.segmento_tipo || 'set')
        const block = Number(set?.bloco_indice || 0)
        const stage = Number(set?.etapa_indice || 0)
        if (type === 'cluster') return {short: block && stage ? `${block}.${stage}` : String(set?.numero || ''), label: t('workout_session.cluster_segment', {block, stage}, `Bloco ${block} · parte ${stage}`)}
        if (type === 'drop_stage') return {short: block && stage ? `${block}↓${stage}` : String(set?.numero || ''), label: t('workout_session.drop_segment', {round:block, stage}, `Rodada ${block} · queda ${stage}`)}
        return {short: String(set?.numero || ''), label: t('workout_session.set_number', {number:set?.numero || ''}, `Série ${set?.numero || ''}`)}
    }

    const formatDuration = value => {
        const seconds = Number.isFinite(Number(value)) ? Math.max(0, Number(value)) : 0;
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = Math.floor(seconds % 60);
        return hours > 0
            ? `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
            : `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    };

    const historyHtml = exercise => {
        const history = exercise?.historico || {};
        const latest = history.ultima || null;
        if (!latest) return '';
        const details = [];
        if (Number(latest.series_total || 0) > 0) details.push(setCountText(Number(latest.series_concluidas || 0), Number(latest.series_total || 0)));
        const previousPrescription = workoutPrescription?.resolve?.({repetitions: latest.repeticoes, load: latest.carga, duration_s: latest.duracao_s == null ? null : Number(latest.duracao_s), distance_m: latest.distancia_m == null ? null : Number(latest.distancia_m)});
        if (previousPrescription?.summaryParts?.length) details.push(...previousPrescription.summaryParts);
        const best = history.melhor_carga ? `<span><b>${escapeHtml(t('workout_session.best_recent_load', {}, 'Best recent load'))}</b>${escapeHtml(history.melhor_carga)}</span>` : '';
        return `<div class="session-exercise-history"><span><b>${escapeHtml(t('workout_session.last_time', {date: latest.data || ''}, `Last time · ${latest.data || ''}`))}</b>${escapeHtml(details.join(' · ') || t('workout_session.completed', {}, 'Workout completed'))}</span>${best}</div>`;
    };

    const hideComplete = () => {
        if (completeModal) completeModal.hidden = true;
    };

    const showComplete = (summary, activityId) => {
        if (!completeModal) return;
        const data = summary || {};
        if (completeTitle) completeTitle.textContent = data.titulo || t('workout_session.completed', {}, 'Workout completed');
        if (completeLead) completeLead.textContent = t('workout_session.completed_lead', {}, 'Your workout was saved as an activity.');
        if (completeDuration) completeDuration.textContent = formatDuration(data.duracao_segundos);
        if (completeExercises) completeExercises.textContent = `${Number(data.exercicios_concluidos || 0)}/${Number(data.exercicios_total || 0)}`;
        if (completeSets) completeSets.textContent = `${Number(data.series_concluidas || 0)}/${Number(data.series_total || 0)}`;
        const feedback = [data.intensidade ? t('workout_session.intensity', {value: data.intensidade}, `Intensity: ${data.intensidade}`) : '', data.sensacao ? t('workout_session.feeling', {value: data.sensacao}, `Feeling: ${data.sensacao}/5`) : ''].filter(Boolean).join(' · ');
        if (completeFeedback) {
            completeFeedback.textContent = feedback;
            completeFeedback.hidden = feedback === '';
        }
        if (completeActivity) completeActivity.href = activityId ? `/user/atividades.php?saved=${encodeURIComponent(activityId)}` : '/user/atividades.php';
        completeModal.hidden = false;
        document.documentElement.classList.add('workout-session-open');
    };

    const renderExercise = (exercise, index, seriesOverride = null, options = {}) => {
        const prescription = plannedPrescription(exercise)
        const method = String(exercise.metodo_prescricao || exercise.prescription_method || prescription?.method || 'standard')
        const methodLabel = method === 'cluster' ? t('workout_builder.method.cluster', {}, 'Cluster') : method === 'drop_set' ? t('workout_builder.method.drop_set', {}, 'Drop set') : t('workout_builder.method.standard', {}, 'Tradicional')
        const summaryParts = Array.isArray(prescription?.summaryParts) ? prescription.summaryParts : []
        const meta = [method !== 'standard' ? methodLabel : '', method === 'standard' && exercise.series_planejadas ? `${exercise.series_planejadas} ${tn('workout_session.set_unit.one', 'workout_session.set_unit.other', Number(exercise.series_planejadas))}` : '', ...summaryParts].filter(Boolean).join(' · ')
        const previousSets = new Map(((exercise.historico || {}).ultima?.series || []).map(set => [Number(set.numero || 0), set]))
        const fields = workoutPrescription.loggingFields(prescription)
        const header = [`<span>${escapeHtml(method === 'cluster' ? t('workout_session.cluster_piece', {}, 'Bloco') : method === 'drop_set' ? t('workout_session.drop_stage', {}, 'Etapa') : t('workout_session.series', {}, 'Série'))}</span>`, ...fields.map(field => `<span>${escapeHtml(t(field === 'reps' ? 'workout_session.repetitions' : `workout_session.${field}`, {}, prescription.labels?.[field] || field))}</span>`), `<span>${escapeHtml(t('workout_session.done', {}, 'Feito'))}</span>`].join('')
        const sets = (seriesOverride ?? exercise.series ?? []).map(set => {
            const previous = previousSets.get(Number(set.numero || 0)) || {}
            const segment = segmentInfo(set)
            const cells = fields.map(field => {
                if (field === 'load') {
                    const loadPlaceholder = previous.carga || plannedLoadText(set, exercise, prescription) || 'kg'
                    return `<label><span>${escapeHtml(t('workout_session.load', {}, 'Carga'))}</span><input type="text" inputmode="decimal" pattern="[0-9]+([.,][0-9]{1,3})?" data-session-set-load="${escapeHtml(set.idserie)}" ${set.concluida ? 'disabled' : ''} value="${escapeHtml(set.carga_realizada ?? '')}" placeholder="${escapeHtml(loadPlaceholder)}"></label>`
                }
                if (field === 'reps') {
                    const repsPlaceholder = previous.repeticoes || plannedRepText(set, exercise, prescription) || 'reps'
                    return `<label><span>${escapeHtml(t('workout_session.repetitions', {}, 'Reps'))}</span><input type="number" min="0" max="999" step="1" inputmode="numeric" data-session-set-reps="${escapeHtml(set.idserie)}" ${set.concluida ? 'disabled' : ''} value="${escapeHtml(set.repeticoes_realizadas ?? '')}" placeholder="${escapeHtml(repsPlaceholder)}"></label>`
                }
                const actual = field === 'duration' ? set.duracao_realizada_s : set.distancia_realizada_m
                const actualText = actual === null || actual === undefined ? '' : (field === 'duration' ? `${Number(actual) % 60 === 0 ? Number(actual)/60 : Number(actual)} ${Number(actual) % 60 === 0 ? 'min' : 's'}` : `${Number(actual) >= 1000 ? Number(actual)/1000 : Number(actual)} ${Number(actual) >= 1000 ? 'km' : 'm'}`)
                const placeholder = plannedMetricText(set, field, prescription)
                return `<label><span>${escapeHtml(t(`workout_session.${field}`, {}, prescription.labels?.[field] || field))}</span><input type="text" data-session-set-${field}="${escapeHtml(set.idserie)}" ${set.concluida ? 'disabled' : ''} value="${escapeHtml(actualText)}" placeholder="${escapeHtml(placeholder)}"></label>`
            }).join('')
            const restAfter = Number(set.descanso_apos_s || 0)
            return `<div class="session-set-segment-wrap" data-segment-type="${escapeHtml(set.segmento_tipo || 'set')}">
                <div class="session-set-row${set.concluida ? ' is-done' : ''}" data-session-set-row="${escapeHtml(set.idserie)}" data-session-field-count="${fields.length}" style="--session-field-count:${fields.length}" data-prescription-mode="${escapeHtml(method)}">
                    <span class="session-set-number" title="${escapeHtml(segment.label)}" aria-label="${escapeHtml(segment.label)}">${escapeHtml(segment.short)}</span>
                    ${cells}
                    <button type="button" class="session-set-check" data-toggle-session-set="${escapeHtml(set.idserie)}" data-next-value="${set.concluida ? '0' : '1'}" aria-label="${escapeHtml(set.concluida ? t('workout_session.reopen_set', {number:set.numero}, `Reabrir série ${set.numero}`) : t('workout_session.complete_set', {number:set.numero}, `Concluir série ${set.numero}`))}">${set.concluida ? '✓' : '○'}</button>
                </div>
                ${restAfter > 0 ? `<div class="session-segment-rest"><span>${escapeHtml(t('workout_session.segment_rest', {seconds:restAfter}, `${restAfter} s de pausa`))}</span><button type="button" data-session-rest="${restAfter}">${escapeHtml(t('workout_session.start_rest', {}, 'Iniciar descanso'))}</button></div>` : ''}
            </div>`
        }).join('')
        const rest = parseRestSeconds(exercise.descanso_snapshot)
        return `<article class="session-exercise${exercise.concluido ? ' is-done' : ''}" data-prescription-mode="${escapeHtml(method)}">
            <div class="session-exercise-heading"><span class="session-exercise-number">${index + 1}</span><div><strong>${escapeHtml(exercise.nome_snapshot)}</strong>${meta ? `<small>${escapeHtml(meta)}</small>` : ''}</div><button type="button" class="session-exercise-check" data-toggle-session-exercise="${escapeHtml(exercise.idsessao_exercicio)}" data-next-value="${exercise.concluido ? '0' : '1'}" aria-label="${escapeHtml(exercise.concluido ? t('workout_session.reopen_exercise', {}, 'Reabrir exercício') : t('workout_session.complete_exercise', {}, 'Concluir exercício'))}">${exercise.concluido ? '✓' : '○'}</button></div>
            ${options.hideHistory ? '' : historyHtml(exercise)}
            <div class="session-set-table-head" data-session-field-count="${fields.length}" style="--session-field-count:${fields.length}">${header}</div>
            <div class="session-sets">${sets}</div>
            ${(exercise.descanso_snapshot || exercise.observacoes_snapshot) ? `<div class="session-exercise-footer">${exercise.descanso_snapshot ? `<span>${escapeHtml(t('workout_session.rest', {value: exercise.descanso_snapshot}, `Descanso: ${exercise.descanso_snapshot}`))}</span>` : '<span></span>'}${rest > 0 ? `<button type="button" data-session-rest="${rest}">${escapeHtml(t('workout_session.start_rest', {}, 'Iniciar descanso'))}</button>` : ''}${exercise.observacoes_snapshot ? `<p>${escapeHtml(exercise.observacoes_snapshot)}</p>` : ''}</div>` : ''}
        </article>`
    }

    const renderGroupFromSequence = (entry, exerciseMap, startIndex) => {
        const type = String(entry.group_type || 'superset')
        const rounds = Array.isArray(entry.rounds) ? entry.rounds : []
        const label = type === 'circuit' ? t('workout_builder.group.circuit', {}, 'Circuito') : t('workout_builder.group.superset', {}, 'Superset')
        const warning = entry.warning ? `<p class="session-group-warning">${escapeHtml(t('workout_session.group_legacy_warning', {}, 'Configuração antiga: as séries extras foram preservadas.'))}</p>` : ''
        const roundMarkup = rounds.map(round => {
            const members = Array.isArray(round.members) ? round.members : []
            const memberMarkup = members.map((member, memberIndex) => {
                const exercise = exerciseMap.get(String(member.exercise_id || ''))
                if (!exercise) return ''
                const setIds = new Set((member.set_ids || []).map(String))
                const series = (exercise.series || []).filter(set => setIds.has(String(set.idserie || '')))
                if (!series.length) return ''
                const between = Number(round.rest_between_exercises_s || 0)
                const restMarkup = between > 0 && memberIndex < members.length - 1 ? `<div class="session-group-rest"><span>${escapeHtml(t('workout_session.group_rest_between', {seconds:between}, `${between} s entre exercícios`))}</span><button type="button" data-session-rest="${between}">${escapeHtml(t('workout_session.start_rest', {}, 'Iniciar descanso'))}</button></div>` : ''
                return `${renderExercise(exercise, startIndex + memberIndex, series, {hideHistory: Number(round.number || 1) > 1})}${restMarkup}`
            }).join('')
            const after = Number(round.rest_after_round_s || 0)
            const afterMarkup = after > 0 && Number(round.number || 1) < rounds.length ? `<div class="session-group-rest is-round"><span>${escapeHtml(t('workout_session.group_rest_round', {seconds:after}, `${after} s após volta`))}</span><button type="button" data-session-rest="${after}">${escapeHtml(t('workout_session.start_rest', {}, 'Iniciar descanso'))}</button></div>` : ''
            return `<div class="session-group-round"><div class="session-group-round-head"><strong>${escapeHtml(t('workout_session.group_round', {current:round.number, total:rounds.length}, `Volta ${round.number} de ${rounds.length}`))}</strong></div>${memberMarkup}${afterMarkup}</div>`
        }).join('')
        return `<section class="session-prescription-group" aria-label="${escapeHtml(`${label}, ${rounds.length} voltas`)}"><div class="session-prescription-group-head"><strong>${escapeHtml(label)}</strong><span>${escapeHtml(`${rounds.length} ${t('workout_builder.group_rounds', {}, 'voltas').toLowerCase()}`)}</span></div>${warning}${roundMarkup}</section>`
    }

    const renderExercises = () => {
        if (!exercisesContainer) return
        const exercises = session?.exercicios || []
        if (!exercises.length) {
            exercisesContainer.innerHTML = `<div class="session-empty"><strong>${escapeHtml(t('workout_session.empty_title', {}, 'Treino sem exercícios'))}</strong><p>${escapeHtml(t('workout_session.empty_help', {}, 'Você ainda pode finalizar a sessão.'))}</p></div>`
            return
        }
        const exerciseMap = new Map(exercises.map(exercise => [String(exercise.idsessao_exercicio || ''), exercise]))
        const sequence = Array.isArray(session?.execution_sequence) ? session.execution_sequence : []
        if (sequence.length) {
            let visualIndex = 0
            const chunks = sequence.map(entry => {
                if (entry?.kind === 'group') {
                    const markup = renderGroupFromSequence(entry, exerciseMap, visualIndex)
                    const memberIds = new Set((entry.rounds || []).flatMap(round => (round.members || []).map(member => String(member.exercise_id || ''))))
                    visualIndex += memberIds.size
                    return markup
                }
                const exercise = exerciseMap.get(String(entry?.exercise_id || ''))
                if (!exercise) return ''
                return renderExercise(exercise, visualIndex++)
            })
            exercisesContainer.innerHTML = chunks.join('')
            return
        }
        exercisesContainer.innerHTML = exercises.map((exercise, index) => renderExercise(exercise, index)).join('')
    }

    const syncElapsedTicker = () => {
        if (elapsedTicker !== null) {
            window.clearInterval(elapsedTicker);
            elapsedTicker = null;
        }
        if (!session) return;
        const update = () => {
            const text = elapsedText();
            if (sessionTime) sessionTime.textContent = text;
            if (pillTime) pillTime.textContent = text;
        };
        update();
        elapsedTicker = window.setInterval(update, 1000);
    };

    const render = () => {
        const has = Boolean(session);
        syncElapsedTicker();
        root.classList.toggle('has-active-workout', has);
        if (pill) pill.hidden = !has;
        if (!has) {
            if (modal) modal.hidden = true;
            document.documentElement.classList.remove('workout-session-open');
            return;
        }
        const s = stats();
        if (title) title.textContent = session.titulo_snapshot || t('workout_session.workout', {}, 'Workout');
        if (progress) progress.textContent = progressText(s.doneExercises, s.totalExercises, s.doneSets, s.totalSets);
        if (pillTitle) pillTitle.textContent = session.titulo_snapshot || t('workout_session.active', {}, 'Workout in progress');
        if (pillSummary) pillSummary.textContent = progressText(s.doneExercises, s.totalExercises, s.doneSets, s.totalSets);
        if (markAllButton) {
            const allDone = s.totalSets > 0 ? s.doneSets === s.totalSets : s.totalExercises > 0 && s.doneExercises === s.totalExercises;
            markAllButton.textContent = allDone ? t('workout_session.unmark_all', {}, 'Unmark all') : t('workout_session.mark_all', {}, 'Mark all');
            markAllButton.dataset.nextValue = allDone ? '0' : '1';
        }
        if (modal && !modal.hidden) renderExercises();
    };

    const open = () => {
        if (!session || !modal) return;
        modal.hidden = false;
        document.documentElement.classList.add('workout-session-open');
        render();
        if (!historyLoaded) fetchCurrent({force: true, includeHistory: true});
    };
    const hideFinish = () => {
        if (finishSheet) finishSheet.hidden = true;
    };
    const close = () => {
        hideFinish();
        if (modal) modal.hidden = true;
        if (!completeModal || completeModal.hidden) document.documentElement.classList.remove('workout-session-open');
    };
    const start = async (idTreino, context = {}) => {
        try {
            const data = await post({action: 'start', idtreino: idTreino, ...context});
            session = data.session || session;
            historyLoaded = Boolean(session);
            rememberPresence(Boolean(session));
            render();
            open();
        } catch (error) {
            notify(error.message);
            throw error;
        }
    };
    const startScheduled = async idAgendamento => {
        try {
            const data = await post({action: 'start_scheduled', idagendamento: idAgendamento});
            session = data.session || session;
            historyLoaded = Boolean(session);
            rememberPresence(Boolean(session));
            render();
            open();
        } catch (error) {
            notify(error.message);
            throw error;
        }
    };
    const startInstitutional = async trainingRef => {
        try {
            const data = await post({action: 'start_institutional', training_ref: trainingRef});
            session = data.session || session;
            historyLoaded = Boolean(session);
            rememberPresence(Boolean(session));
            render();
            open();
        } catch (error) {
            notify(error.message);
            throw error;
        }
    };
    const quickRegister = async payload => {
        try {
            return await post({action: 'quick_register', ...payload});
        } catch (error) {
            notify(error.message);
            throw error;
        }
    };

    root.querySelectorAll('[data-open-workout-session]').forEach(button => button.addEventListener('click', open));
    root.querySelectorAll('[data-close-workout-session]').forEach(button => button.addEventListener('click', close));
    exercisesContainer?.addEventListener('click', async event => {
        const setButton = event.target.closest('[data-toggle-session-set]');
        if (setButton) {
            setButton.disabled = true;
            try {
                const data = await post({action: 'toggle_set', idserie: setButton.dataset.toggleSessionSet, concluida: setButton.dataset.nextValue});
                session = preserveHistory(data.session);
                render();
            } catch (error) { notify(error.message); }
            return;
        }
        const exerciseButton = event.target.closest('[data-toggle-session-exercise]');
        if (exerciseButton) {
            exerciseButton.disabled = true;
            try {
                const data = await post({action: 'toggle_exercise', idsessao_exercicio: exerciseButton.dataset.toggleSessionExercise, concluido: exerciseButton.dataset.nextValue});
                session = preserveHistory(data.session);
                render();
            } catch (error) { notify(error.message); }
            return;
        }
        const restButton = event.target.closest('[data-session-rest]');
        if (restButton && window.StrideBRQuickTools) window.StrideBRQuickTools.setTimer(Number(restButton.dataset.sessionRest || 0));
    });

    exercisesContainer?.addEventListener('input', event => {
        if (event.target.matches('[data-session-set-load], [data-session-set-reps], [data-session-set-duration], [data-session-set-distance]')) event.target.dataset.pendingEdit = '1';
    });
    exercisesContainer?.addEventListener('change', async event => {
        const input = event.target.closest('[data-session-set-load], [data-session-set-reps], [data-session-set-duration], [data-session-set-distance]');
        if (!input) return;
        const row = input.closest('[data-session-set-row]');
        const id = row?.dataset.sessionSetRow || '';
        if (!id) return;
        const load = row.querySelector('[data-session-set-load]')?.value || '';
        const reps = row.querySelector('[data-session-set-reps]')?.value || '';
        const duration = row.querySelector('[data-session-set-duration]')?.value || '';
        const distance = row.querySelector('[data-session-set-distance]')?.value || '';
        const editedField = ['load','reps','duration','distance'].find(field => input.hasAttribute(`data-session-set-${field}`));
        const savedValue = input.value;
        row.classList.add('is-saving');
        try {
            const data = await post({action: 'update_set', idserie: id, carga: load, repeticoes: reps, duracao: duration, distancia: distance, propagate:'1', edited_field:editedField});
            session = preserveHistory(data.session);
            if (input.value === savedValue) delete input.dataset.pendingEdit;
            for (const exercise of session?.exercicios || []) {
                for (const set of exercise.series || []) {
                    const actualValues = {load: set.carga_realizada ?? '', reps: set.repeticoes_realizadas ?? '', duration: set.duracao_realizada_s == null ? '' : `${Number(set.duracao_realizada_s)/60} min`, distance: set.distancia_realizada_m == null ? '' : `${Number(set.distancia_realizada_m)} m`};
                    for (const [name,value] of Object.entries(actualValues)) {
                        const field = exercisesContainer.querySelector(`[data-session-set-${name}="${CSS.escape(String(set.idserie))}"]`);
                        if (field && field !== document.activeElement && !field.dataset.pendingEdit) field.value = value;
                    }
                }
            }
            row.classList.remove('has-save-error');
        } catch (error) {
            row.classList.add('has-save-error');
            notify(error.message);
        } finally {
            row.classList.remove('is-saving');
        }
    });

    markAllButton?.addEventListener('click', async () => {
        markAllButton.disabled = true;
        try {
            const data = await post({action: 'mark_all', concluido: markAllButton.dataset.nextValue || '1'});
            session = preserveHistory(data.session);
            render();
        } catch (error) {
            notify(error.message);
        } finally {
            markAllButton.disabled = false;
        }
    });

    root.querySelector('[data-cancel-workout-session]')?.addEventListener('click', async () => {
        if (!await confirmAction(t('workout_session.cancel_confirm', {}, 'Cancel this workout in progress?'))) return;
        try {
            await post({action: 'cancel'});
            session = null;
            historyLoaded = false;
            rememberPresence(false);
            render();
        } catch (error) { notify(error.message); }
    });
    root.querySelector('[data-finish-workout-session]')?.addEventListener('click', () => {
        const current = stats();
        finishIntensity = '';
        finishFeeling = '';
        root.querySelectorAll('[data-finish-intensity], [data-finish-feeling]').forEach(button => button.classList.remove('is-selected'));
        if (finishNotes) finishNotes.value = '';
        if (finishStart) finishStart.value = localDateTimeValue(session?.data_inicio);
        if (finishEnd) finishEnd.value = localDateTimeValue();
        updateFinishDuration();
        if (finishSummary) finishSummary.textContent = progressText(current.doneExercises, current.totalExercises, current.doneSets, current.totalSets);
        if (finishSheet) finishSheet.hidden = false;
    });
    root.querySelectorAll('[data-close-workout-finish]').forEach(button => button.addEventListener('click', hideFinish));
    finishStart?.addEventListener('input', updateFinishDuration);
    finishEnd?.addEventListener('input', updateFinishDuration);
    root.querySelectorAll('[data-finish-intensity]').forEach(button => button.addEventListener('click', () => {
        finishIntensity = button.dataset.finishIntensity || '';
        root.querySelectorAll('[data-finish-intensity]').forEach(item => item.classList.toggle('is-selected', item === button));
    }));
    root.querySelectorAll('[data-finish-feeling]').forEach(button => button.addEventListener('click', () => {
        finishFeeling = button.dataset.finishFeeling || '';
        root.querySelectorAll('[data-finish-feeling]').forEach(item => item.classList.toggle('is-selected', item === button));
    }));
    root.querySelector('[data-confirm-workout-finish]')?.addEventListener('click', async event => {
        const button = event.currentTarget;
        button.disabled = true;
        try {
            const data = await post({action: 'finish', intensidade: finishIntensity, feeling: finishFeeling, observacoes: finishNotes?.value || '', inicio_real: finishStart?.value || '', fim_real: finishEnd?.value || ''});
            session = null;
            historyLoaded = false;
            rememberPresence(false);
            hideFinish();
            render();
            button.disabled = false;
            showComplete(data.summary || {}, data.activity_id || '');
        } catch (error) {
            notify(error.message);
            button.disabled = false;
        }
    });

    root.querySelectorAll('[data-workout-complete-close]').forEach(button => button.addEventListener('click', () => {
        hideComplete();
        document.documentElement.classList.remove('workout-session-open');
    }));

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        if (completeModal && !completeModal.hidden) {
            hideComplete();
            document.documentElement.classList.remove('workout-session-open');
            return;
        }
        if (modal && !modal.hidden) close();
    });
    // iOS keeps the layout viewport tall when its keyboard reduces the visual viewport.
    // Resize only this modal; the footer stays in flex flow above the keyboard.
    let viewportFrame = null;
    const syncKeyboardViewport = () => {
        if (viewportFrame !== null) cancelAnimationFrame(viewportFrame);
        viewportFrame = requestAnimationFrame(() => {
            viewportFrame = null;
            const viewport = window.visualViewport;
            const keyboard = viewport && viewport.scale === 1 && window.innerWidth <= 560
                && viewport.height < window.innerHeight * .8 && modal && !modal.hidden;
            modal?.classList.toggle('is-keyboard-open', Boolean(keyboard));
            if (!keyboard) return;
            modal.style.setProperty('--workout-keyboard-height', `${viewport.height}px`);
            modal.style.setProperty('--workout-keyboard-top', `${viewport.offsetTop}px`);
            const input = document.activeElement;
            if (!exercisesContainer?.contains(input) || input.tagName !== 'INPUT') return;
            const field = input.getBoundingClientRect();
            const area = exercisesContainer.getBoundingClientRect();
            if (field.bottom > area.bottom - 8) exercisesContainer.scrollTop += field.bottom - area.bottom + 8;
            else if (field.top < area.top + 8) exercisesContainer.scrollTop -= area.top - field.top + 8;
        });
    };
    window.visualViewport?.addEventListener('resize', syncKeyboardViewport);
    window.visualViewport?.addEventListener('scroll', syncKeyboardViewport);
    exercisesContainer?.addEventListener('focusin', syncKeyboardViewport);
    window.StrideBRWorkout = {start, startScheduled, startInstitutional, quickRegister, open, refresh: () => fetchCurrent({force: true, includeHistory: Boolean(modal && !modal.hidden)})};
    const hydrateCurrent = () => fetchCurrent();
    if ('requestIdleCallback' in window) window.requestIdleCallback(hydrateCurrent, {timeout: 1400});
    else window.setTimeout(hydrateCurrent, 450);
})();
