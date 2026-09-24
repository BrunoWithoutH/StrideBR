document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-draft-exercise-page]')
    if (!page) return
    const storageKey = 'stridebr.schedule.quickDraft'
    const root = page.querySelector('[data-draft-workout-builder]')
    const missing = page.querySelector('[data-draft-missing]')
    const returnTo = page.dataset.returnTo || '/user/cronogramatreinos.php'
    const stateLabel = page.querySelector('[data-draft-save-state]')
    const readDraft = () => {
        try { return JSON.parse(sessionStorage.getItem(storageKey) || 'null') } catch (_) { return null }
    }
    const draft = readDraft()
    if (!draft || typeof draft !== 'object') {
        if (missing) missing.hidden = false
        if (root) root.hidden = true
        return
    }
    if (!Array.isArray(draft.exercises)) draft.exercises = []
    const persist = () => {
        if (!root?.StrideBRWorkoutBuilder) return
        draft.exercises = root.StrideBRWorkoutBuilder.serialize()
        draft.updatedAt = Date.now()
        sessionStorage.setItem(storageKey, JSON.stringify(draft))
        if (stateLabel) stateLabel.textContent = window.StrideBRI18n?.t?.('draft.saved') || 'Rascunho salvo'
    }
    const mount = () => {
        if (!root?.StrideBRWorkoutBuilder) return false
        root.StrideBRWorkoutBuilder.hydrate(draft.exercises)
        root.addEventListener('input', persist)
        root.addEventListener('change', persist)
        root.querySelectorAll('[data-save-draft-exercises]').forEach(button => button.addEventListener('click', () => {
            persist()
            const url = new URL(returnTo, window.location.origin)
            url.searchParams.set('resume_quick', '1')
            window.location.href = `${url.pathname}${url.search}${url.hash}`
        }))
        window.addEventListener('beforeunload', persist)
        return true
    }
    if (!mount()) root?.addEventListener('stridebr:workout-builder-ready', mount, {once: true})
})
