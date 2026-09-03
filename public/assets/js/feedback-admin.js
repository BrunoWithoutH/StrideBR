document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-feedback-admin]');
    if (!root) return;

    const cards = Array.from(root.querySelectorAll('[data-feedback-card]'));
    const search = root.querySelector('[data-feedback-search]');
    const priority = root.querySelector('[data-feedback-priority]');
    const reset = root.querySelector('[data-feedback-reset]');
    const empty = root.querySelector('[data-feedback-empty]');
    const visibleCount = root.querySelector('[data-feedback-visible-count]');
    const state = { type: 'all', status: 'all', priority: 'all', query: '' };

    const normalize = value => (value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    const apply = () => {
        let visible = 0;
        cards.forEach(card => {
            const matchesType = state.type === 'all' || card.dataset.feedbackType === state.type;
            const matchesStatus = state.status === 'all' || card.dataset.feedbackStatus === state.status;
            const matchesPriority = state.priority === 'all' || card.dataset.feedbackPriority === state.priority;
            const haystack = normalize(card.dataset.feedbackSearchText);
            const matchesQuery = state.query === '' || haystack.includes(state.query);
            const show = matchesType && matchesStatus && matchesPriority && matchesQuery;
            card.hidden = !show;
            if (show) visible++;
        });

        if (visibleCount) visibleCount.textContent = String(visible);
        if (empty) empty.hidden = visible !== 0 || cards.length === 0;
    };

    root.querySelectorAll('[data-feedback-filter]').forEach(button => {
        button.addEventListener('click', () => {
            const filter = button.dataset.feedbackFilter;
            const value = button.dataset.feedbackValue || 'all';
            if (!filter || !(filter in state)) return;

            state[filter] = value;
            root.querySelectorAll(`[data-feedback-filter="${filter}"]`).forEach(item => {
                const active = item === button;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            apply();
        });
    });

    search?.addEventListener('input', () => {
        state.query = normalize(search.value);
        apply();
    });

    priority?.addEventListener('change', () => {
        state.priority = priority.value || 'all';
        apply();
    });

    reset?.addEventListener('click', () => {
        state.type = 'all';
        state.status = 'all';
        state.priority = 'all';
        state.query = '';

        if (search) search.value = '';
        if (priority) priority.value = 'all';

        ['type', 'status'].forEach(filter => {
            root.querySelectorAll(`[data-feedback-filter="${filter}"]`).forEach(item => {
                const active = item.dataset.feedbackValue === 'all';
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        });
        apply();
    });
});
