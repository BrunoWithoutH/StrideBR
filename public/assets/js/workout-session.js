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
    let finishIntensity = '';
    let finishFeeling = '';
    let session = null;

    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
    const post = async payload => {
        const body = new FormData();
        body.set('csrf_token', csrf);
        Object.entries(payload).forEach(([key, value]) => body.set(key, String(value)));
        const response = await fetch('/function/treino_sessao.php', {method: 'POST', body, credentials: 'same-origin'});
        const data = await response.json().catch(() => ({ok: false, message: 'Resposta inválida do servidor.'}));
        if (!response.ok && !data.session) throw new Error(data.message || 'Não foi possível atualizar o treino.');
        return data;
    };
    const fetchCurrent = async () => {
        try {
            const response = await fetch('/function/treino_sessao.php?action=current', {credentials: 'same-origin'});
            const data = await response.json();
            session = data.session || null;
            render();
        } catch (_) {
            session = null;
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

    const renderExercises = () => {
        if (!exercisesContainer) return;
        const exercises = session?.exercicios || [];
        if (!exercises.length) {
            exercisesContainer.innerHTML = '<div class="session-empty"><strong>Treino sem exercícios</strong><p>Você ainda pode usar o cronômetro e finalizar a sessão para registrar a duração.</p></div>';
            return;
        }
        exercisesContainer.innerHTML = exercises.map((exercise, index) => {
            const meta = [exercise.series_planejadas ? `${exercise.series_planejadas} séries` : '', exercise.repeticoes_snapshot ? `${exercise.repeticoes_snapshot} reps` : '', exercise.carga_snapshot || ''].filter(Boolean).join(' · ');
            const sets = (exercise.series || []).map(set => `<button type="button" class="session-set${set.concluida ? ' is-done' : ''}" data-toggle-session-set="${escapeHtml(set.idserie)}" data-next-value="${set.concluida ? '0' : '1'}"><span>${set.concluida ? '✓' : set.numero}</span><small>Série ${set.numero}</small></button>`).join('');
            const rest = parseRestSeconds(exercise.descanso_snapshot);
            return `<article class="session-exercise${exercise.concluido ? ' is-done' : ''}">
                <div class="session-exercise-heading"><span class="session-exercise-number">${index + 1}</span><div><strong>${escapeHtml(exercise.nome_snapshot)}</strong>${meta ? `<small>${escapeHtml(meta)}</small>` : ''}</div><button type="button" class="session-exercise-check" data-toggle-session-exercise="${escapeHtml(exercise.idsessao_exercicio)}" data-next-value="${exercise.concluido ? '0' : '1'}" aria-label="${exercise.concluido ? 'Reabrir exercício' : 'Concluir exercício'}">${exercise.concluido ? '✓' : '○'}</button></div>
                <div class="session-sets">${sets}</div>
                ${(exercise.descanso_snapshot || exercise.observacoes_snapshot) ? `<div class="session-exercise-footer">${exercise.descanso_snapshot ? `<span>Descanso: ${escapeHtml(exercise.descanso_snapshot)}</span>` : '<span></span>'}${rest > 0 ? `<button type="button" data-session-rest="${rest}">Iniciar descanso</button>` : ''}${exercise.observacoes_snapshot ? `<p>${escapeHtml(exercise.observacoes_snapshot)}</p>` : ''}</div>` : ''}
            </article>`;
        }).join('');
    };

    const render = () => {
        const has = Boolean(session);
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
        renderExercises();
    };

    const open = () => {
        if (!session || !modal) return;
        modal.hidden = false;
        document.documentElement.classList.add('workout-session-open');
        render();
    };
    const hideFinish = () => {
        if (finishSheet) finishSheet.hidden = true;
    };
    const close = () => {
        hideFinish();
        if (modal) modal.hidden = true;
        document.documentElement.classList.remove('workout-session-open');
    };
    const start = async idTreino => {
        try {
            const data = await post({action: 'start', idtreino: idTreino});
            session = data.session || session;
            render();
            open();
        } catch (error) {
            window.alert(error.message);
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
                session = data.session;
                render();
            } catch (error) { window.alert(error.message); }
            return;
        }
        const exerciseButton = event.target.closest('[data-toggle-session-exercise]');
        if (exerciseButton) {
            exerciseButton.disabled = true;
            try {
                const data = await post({action: 'toggle_exercise', idsessao_exercicio: exerciseButton.dataset.toggleSessionExercise, concluido: exerciseButton.dataset.nextValue});
                session = data.session;
                render();
            } catch (error) { window.alert(error.message); }
            return;
        }
        const restButton = event.target.closest('[data-session-rest]');
        if (restButton && window.StrideBRQuickTools) window.StrideBRQuickTools.setTimer(Number(restButton.dataset.sessionRest || 0));
    });

    root.querySelector('[data-cancel-workout-session]')?.addEventListener('click', async () => {
        if (!window.confirm('Cancelar este treino em andamento? O progresso desta sessão será encerrado.')) return;
        try {
            await post({action: 'cancel'});
            session = null;
            render();
        } catch (error) { window.alert(error.message); }
    });
    root.querySelector('[data-finish-workout-session]')?.addEventListener('click', () => {
        const current = stats();
        finishIntensity = '';
        finishFeeling = '';
        root.querySelectorAll('[data-finish-intensity], [data-finish-feeling]').forEach(button => button.classList.remove('is-selected'));
        if (finishNotes) finishNotes.value = '';
        if (finishSummary) finishSummary.textContent = `${current.doneExercises}/${current.totalExercises} exercícios · ${current.doneSets}/${current.totalSets} séries`;
        if (finishSheet) finishSheet.hidden = false;
    });
    root.querySelectorAll('[data-close-workout-finish]').forEach(button => button.addEventListener('click', hideFinish));
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
            const data = await post({action: 'finish', intensidade: finishIntensity, feeling: finishFeeling, observacoes: finishNotes?.value || ''});
            session = null;
            render();
            close();
            if (data.activity_id) window.location.href = `/user/atividades.php?highlight=${encodeURIComponent(data.activity_id)}`;
        } catch (error) {
            window.alert(error.message);
            button.disabled = false;
        }
    });

    document.addEventListener('keydown', event => { if (event.key === 'Escape' && modal && !modal.hidden) close(); });
    window.setInterval(() => {
        if (!session) return;
        const text = elapsedText();
        if (sessionTime) sessionTime.textContent = text;
        if (pillTime) pillTime.textContent = text;
    }, 1000);

    window.StrideBRWorkout = {start, open, refresh: fetchCurrent};
    fetchCurrent();
})();
