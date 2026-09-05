document.addEventListener('DOMContentLoaded', () => {
    const t = (key, values = {}, fallback = key) => window.StrideBRI18n?.t?.(key, values, fallback) ?? fallback
    const page = document.querySelector('[data-draft-exercise-page]');
    if (!page) return;
    const storageKey = 'stridebr.schedule.quickDraft';
    const list = page.querySelector('[data-draft-exercise-list]');
    const template = page.querySelector('[data-draft-exercise-template]');
    const missing = page.querySelector('[data-draft-missing]');
    const editor = page.querySelector('[data-draft-editor]');
    const stateLabel = page.querySelector('[data-draft-save-state]');
    const returnTo = page.dataset.returnTo || '/user/cronogramatreinos.php';
    const fields = ['idexercicio','nome','series','repeticoes','carga','descanso','bloco','cluster','duracao','distancia','intensidade','rpe','rir','tempo_execucao','cadencia','observacoes'];

    const readDraft = () => {
        try {
            const raw = sessionStorage.getItem(storageKey);
            return raw ? JSON.parse(raw) : null;
        } catch (_) {
            return null;
        }
    };
    let draft = readDraft();
    if (!draft || typeof draft !== 'object') {
        missing.hidden = false;
        editor.hidden = true;
        return;
    }
    if (!Array.isArray(draft.exercises)) draft.exercises = [];

    const persist = () => {
        draft.updatedAt = Date.now();
        sessionStorage.setItem(storageKey, JSON.stringify(draft));
        if (stateLabel) {
            stateLabel.textContent = t('draft.saved_browser', {}, 'Changes saved in this browser');
            clearTimeout(persist.labelTimer);
            persist.labelTimer = setTimeout(() => { stateLabel.textContent = t('draft.saved', {}, 'Draft saved'); }, 900);
        }
    };

    const collect = () => {
        draft.exercises = [...list.querySelectorAll('[data-draft-exercise-row]')].map(row => {
            const output = {};
            fields.forEach(name => {
                output[name] = row.querySelector(`[data-field="${name}"]`)?.value ?? '';
            });
            return output;
        }).filter(row => String(row.nome || '').trim() !== '');
        persist();
    };

    const renumber = () => {
        [...list.querySelectorAll('[data-draft-exercise-row]')].forEach((row, index) => {
            const number = row.querySelector('[data-draft-number]');
            if (number) number.textContent = t('draft.exercise_number', {number: index + 1}, `Exercise ${index + 1}`);
        });
    };

    const addRow = values => {
        const fragment = template.content.cloneNode(true);
        const row = fragment.querySelector('[data-draft-exercise-row]');
        fields.forEach(name => {
            const input = row.querySelector(`[data-field="${name}"]`);
            if (input && values?.[name] !== undefined && values?.[name] !== null) input.value = String(values[name]);
        });
        const library = row.querySelector('[data-field="idexercicio"]');
        const name = row.querySelector('[data-field="nome"]');
        library?.addEventListener('change', () => {
            const option = library.options[library.selectedIndex];
            if (library.value && option?.dataset.name && name) name.value = option.dataset.name;
            collect();
        });
        row.querySelector('[data-remove-draft-exercise]')?.addEventListener('click', () => {
            const next = row.nextElementSibling;
            row.remove();
            renumber();
            collect();
            window.StrideBRUI?.undo?.(t('draft.exercise_removed', {}, 'Exercise removed from draft.'), async () => {
                if (next?.isConnected) list.insertBefore(row, next);
                else list.appendChild(row);
                renumber();
                collect();
            });
        });
        row.addEventListener('input', collect);
        row.addEventListener('change', collect);
        list.appendChild(fragment);
        renumber();
        return row;
    };

    draft.exercises.forEach(addRow);
    if (draft.exercises.length === 0) addRow({});

    page.querySelector('[data-add-draft-exercise]')?.addEventListener('click', () => {
        const row = addRow({});
        row.querySelector('[data-field="nome"]')?.focus();
        collect();
    });
    page.querySelector('[data-save-draft-exercises]')?.addEventListener('click', () => {
        collect();
        const url = new URL(returnTo, window.location.origin);
        url.searchParams.set('resume_quick', '1');
        window.location.href = `${url.pathname}${url.search}${url.hash}`;
    });
    window.addEventListener('beforeunload', collect);
});
