(() => {
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
        ? window.StrideBRUI.confirm(message, {title: 'Confirmar ação', confirmLabel: 'Confirmar', danger: true})
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
            finishDurationPreview.textContent = 'Confira os horários.';
            return;
        }
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        finishDurationPreview.textContent = `Duração registrada: ${hours > 0 ? `${hours}h ` : ''}${minutes}min`;
    };
    const post = async payload => {
        const body = new FormData();
        body.set('csrf_token', csrf);
        Object.entries(payload).forEach(([key, value]) => body.set(key, String(value)));
        const response = await (window.StrideBRNet?.fetch || fetch)('/function/treino_sessao.php', {method: 'POST', body, credentials: 'same-origin'}, 15000);
        const data = await response.json().catch(() => ({ok: false, message: 'Resposta inválida do servidor.'}));
        if (!response.ok && !data.session) throw new Error(data.message || 'Não foi possível atualizar o treino.');
        return data;
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
            session = data.session || null;
            historyLoaded = Boolean(session && includeHistory);
            rememberPresence(Boolean(session));
            render();
        } catch (_) {
            session = null;
            historyLoaded = false;
            render();
        }
    };

    const elapsedText = () => {
        if (!session?.data_inicio) return '00:00';
        const start = new Date(session.data_inicio).getTime();
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

    const formatRepetitions = value => {
        const text = String(value || '').trim();
        if (!text) return '';
        let match = text.match(/^(\d+(?:[.,]\d+)?)\s*(?:mn|min|mins|minuto|minutos)$/i);
        if (match) return `${match[1].replace(',', '.')} min`;
        match = text.match(/^(\d+(?:[.,]\d+)?)\s*(?:s|seg|segs|segundo|segundos)$/i);
        if (match) return `${match[1].replace(',', '.')} s`;
        if (/\b(?:rep|reps|repetiç(?:ão|ões))\b/i.test(text)) return text;
        return `${text} reps`;
    };

    const formatDuration = value => {
        const seconds = Math.max(0, Number(value || 0));
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
        if (Number(latest.series_total || 0) > 0) details.push(`${Number(latest.series_concluidas || 0)}/${Number(latest.series_total || 0)} séries`);
        if (latest.repeticoes) details.push(formatRepetitions(latest.repeticoes));
        if (latest.carga) details.push(String(latest.carga));
        const best = history.melhor_carga ? `<span><b>Melhor carga recente</b>${escapeHtml(history.melhor_carga)}</span>` : '';
        return `<div class="session-exercise-history"><span><b>Última vez · ${escapeHtml(latest.data || '')}</b>${escapeHtml(details.join(' · ') || 'Treino concluído')}</span>${best}</div>`;
    };

    const hideComplete = () => {
        if (completeModal) completeModal.hidden = true;
    };

    const showComplete = (summary, activityId) => {
        if (!completeModal) return;
        const data = summary || {};
        if (completeTitle) completeTitle.textContent = data.titulo || 'Treino concluído';
        if (completeLead) completeLead.textContent = 'Seu treino foi salvo como atividade. Dá pra conferir os detalhes ou continuar por aqui.';
        if (completeDuration) completeDuration.textContent = formatDuration(data.duracao_segundos);
        if (completeExercises) completeExercises.textContent = `${Number(data.exercicios_concluidos || 0)}/${Number(data.exercicios_total || 0)}`;
        if (completeSets) completeSets.textContent = `${Number(data.series_concluidas || 0)}/${Number(data.series_total || 0)}`;
        const feedback = [data.intensidade ? `Intensidade: ${data.intensidade}` : '', data.sensacao ? `Sensação: ${data.sensacao}/5` : ''].filter(Boolean).join(' · ');
        if (completeFeedback) {
            completeFeedback.textContent = feedback;
            completeFeedback.hidden = feedback === '';
        }
        if (completeActivity) completeActivity.href = activityId ? `/user/atividades.php?saved=${encodeURIComponent(activityId)}` : '/user/atividades.php';
        completeModal.hidden = false;
        document.documentElement.classList.add('workout-session-open');
    };

    const renderExercises = () => {
        if (!exercisesContainer) return;
        const exercises = session?.exercicios || [];
        if (!exercises.length) {
            exercisesContainer.innerHTML = '<div class="session-empty"><strong>Treino sem exercícios</strong><p>Você ainda pode usar o cronômetro e finalizar a sessão para registrar a duração.</p></div>';
            return;
        }
        exercisesContainer.innerHTML = exercises.map((exercise, index) => {
            const meta = [exercise.series_planejadas ? `${exercise.series_planejadas} séries` : '', exercise.repeticoes_snapshot ? formatRepetitions(exercise.repeticoes_snapshot) : '', exercise.carga_snapshot || ''].filter(Boolean).join(' · ');
            const previousSets = new Map(((exercise.historico || {}).ultima?.series || []).map(set => [Number(set.numero || 0), set]));
            const sets = (exercise.series || []).map(set => {
                const previous = previousSets.get(Number(set.numero || 0)) || {};
                const loadPlaceholder = previous.carga || exercise.carga_snapshot || 'kg';
                const repsPlaceholder = previous.repeticoes || String(exercise.repeticoes_snapshot || '').replace(/\D.*$/, '') || 'reps';
                return `<div class="session-set-row${set.concluida ? ' is-done' : ''}" data-session-set-row="${escapeHtml(set.idserie)}">
                    <span class="session-set-number">${set.numero}</span>
                    <label><span>Carga</span><input type="number" min="0" max="9999.999" step="0.5" inputmode="decimal" data-session-set-load="${escapeHtml(set.idserie)}" value="${escapeHtml(set.carga_realizada || '')}" placeholder="${escapeHtml(loadPlaceholder)}"></label>
                    <label><span>Reps</span><input type="number" min="0" max="999" step="1" inputmode="numeric" data-session-set-reps="${escapeHtml(set.idserie)}" value="${escapeHtml(set.repeticoes_realizadas || '')}" placeholder="${escapeHtml(repsPlaceholder)}"></label>
                    <button type="button" class="session-set-check" data-toggle-session-set="${escapeHtml(set.idserie)}" data-next-value="${set.concluida ? '0' : '1'}" aria-label="${set.concluida ? `Reabrir série ${set.numero}` : `Concluir série ${set.numero}`}">${set.concluida ? '✓' : '○'}</button>
                </div>`;
            }).join('');
            const rest = parseRestSeconds(exercise.descanso_snapshot);
            return `<article class="session-exercise${exercise.concluido ? ' is-done' : ''}">
                <div class="session-exercise-heading"><span class="session-exercise-number">${index + 1}</span><div><strong>${escapeHtml(exercise.nome_snapshot)}</strong>${meta ? `<small>${escapeHtml(meta)}</small>` : ''}</div><button type="button" class="session-exercise-check" data-toggle-session-exercise="${escapeHtml(exercise.idsessao_exercicio)}" data-next-value="${exercise.concluido ? '0' : '1'}" aria-label="${exercise.concluido ? 'Reabrir exercício' : 'Concluir exercício'}">${exercise.concluido ? '✓' : '○'}</button></div>
                ${historyHtml(exercise)}
                <div class="session-set-table-head"><span>Série</span><span>Carga</span><span>Reps</span><span>Feita</span></div>
                <div class="session-sets">${sets}</div>
                ${(exercise.descanso_snapshot || exercise.observacoes_snapshot) ? `<div class="session-exercise-footer">${exercise.descanso_snapshot ? `<span>Descanso: ${escapeHtml(exercise.descanso_snapshot)}</span>` : '<span></span>'}${rest > 0 ? `<button type="button" data-session-rest="${rest}">Iniciar descanso</button>` : ''}${exercise.observacoes_snapshot ? `<p>${escapeHtml(exercise.observacoes_snapshot)}</p>` : ''}</div>` : ''}
            </article>`;
        }).join('');
    };

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
        if (title) title.textContent = session.titulo_snapshot || 'Treino';
        if (progress) progress.textContent = `${s.doneExercises}/${s.totalExercises} exercícios · ${s.doneSets}/${s.totalSets} séries`;
        if (pillTitle) pillTitle.textContent = session.titulo_snapshot || 'Treino em andamento';
        if (pillSummary) pillSummary.textContent = `${s.doneExercises}/${s.totalExercises} exercícios · ${s.doneSets}/${s.totalSets} séries`;
        if (markAllButton) {
            const allDone = s.totalSets > 0 ? s.doneSets === s.totalSets : s.totalExercises > 0 && s.doneExercises === s.totalExercises;
            markAllButton.textContent = allDone ? 'Desmarcar tudo' : 'Marcar tudo';
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

    exercisesContainer?.addEventListener('change', async event => {
        const input = event.target.closest('[data-session-set-load], [data-session-set-reps]');
        if (!input) return;
        const row = input.closest('[data-session-set-row]');
        const id = row?.dataset.sessionSetRow || '';
        if (!id) return;
        const load = row.querySelector('[data-session-set-load]')?.value || '';
        const reps = row.querySelector('[data-session-set-reps]')?.value || '';
        row.classList.add('is-saving');
        try {
            const data = await post({action: 'update_set', idserie: id, carga: load, repeticoes: reps});
            session = preserveHistory(data.session);
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
        if (!await confirmAction('Cancelar este treino em andamento? O progresso desta sessão será encerrado.')) return;
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
        if (finishSummary) finishSummary.textContent = `${current.doneExercises}/${current.totalExercises} exercícios · ${current.doneSets}/${current.totalSets} séries`;
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
    window.StrideBRWorkout = {start, startScheduled, quickRegister, open, refresh: () => fetchCurrent({force: true, includeHistory: Boolean(modal && !modal.hidden)})};
    const hydrateCurrent = () => fetchCurrent();
    if ('requestIdleCallback' in window) window.requestIdleCallback(hydrateCurrent, {timeout: 1400});
    else window.setTimeout(hydrateCurrent, 450);
})();
