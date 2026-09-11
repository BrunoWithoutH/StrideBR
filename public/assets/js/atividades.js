document.addEventListener('DOMContentLoaded', () => {
    const i18n = window.StrideBRI18n || {locale: document.documentElement.lang?.startsWith('en') ? 'en' : 'pt-BR', t: (key, values = {}, fallback = null) => { let text = fallback ?? key; Object.entries(values).forEach(([name, value]) => { text = String(text).split(`{${name}}`).join(String(value ?? '')) }); return text }, tn: (one, other, count, values = {}) => (window.StrideBRI18n?.t || ((key,_v,f)=>f??key))(Number(count) === 1 ? one : other, {...values,count}), number: value => String(value), sport: (_slug, fallback) => fallback}
    const tr = (key, values = {}, fallback = null) => i18n.t(key, values, fallback)
    const trn = (one, other, count, values = {}) => i18n.t(Number(count) === 1 ? one : other, {...values, count: i18n.number?.(count, 0) ?? String(count)})
    const sportDisplay = (slug, fallback = '') => i18n.sport?.(slug, fallback) || fallback
    const sportContextEngine = window.StrideBRActivitySportContext || null
    const page = document.querySelector('[data-activities-page]')
    const shell = document.querySelector('[data-activity-form]')
    const form = document.getElementById('activity-form')
    const openButtons = document.querySelectorAll('[data-toggle-activity-form]')
    const closeButtons = document.querySelectorAll('[data-close-activity-form]')
    const modalitySelect = document.getElementById('modalidade')
    const modelSelect = document.getElementById('modelo')
    const modelField = document.querySelector('[data-model-field]')
    const workoutField = document.querySelector('[data-workout-field]')
    const workoutSelect = document.querySelector('[data-workout-select]')
    const activityTitleInput = form?.querySelector('input[name="titulo"]')
    const activityTitlePreview = form?.querySelector('[data-activity-title-preview]')
    const activityTitleEditor = form?.querySelector('[data-activity-title-editor]')
    const activityTitleEditButton = form?.querySelector('[data-edit-activity-title]')
    const routePrivacyFields = form?.querySelector('[data-route-privacy-fields]')
    const routePrivacyToggle = routePrivacyFields?.querySelector('[data-route-privacy-toggle]')
    const routePrivacyOptions = routePrivacyFields?.querySelector('[data-route-privacy-options]')
    const setRoutePrivacyExpanded = expanded => {
        const active = Boolean(expanded && routePrivacyFields && !routePrivacyFields.hidden)
        if (routePrivacyOptions) routePrivacyOptions.hidden = !active
        routePrivacyToggle?.setAttribute('aria-expanded', active ? 'true' : 'false')
        if (routePrivacyToggle) routePrivacyToggle.textContent = tr(active ? 'common.close' : 'common.configure')
    }
    routePrivacyToggle?.addEventListener('click', () => setRoutePrivacyExpanded(routePrivacyToggle.getAttribute('aria-expanded') !== 'true'))
    const activityDateInput = form?.querySelector('input[name="data"]')
    const activityClockField = form?.querySelector('[data-clock-field]')
    let writingAutomaticActivityTitle = false
    const modelPanelsHost = document.querySelector('[data-activity-model-panels-host]')
    const equipmentHost = document.querySelector('[data-activity-equipment-host]')
    const strengthEditor = document.querySelector('[data-strength-editor]')
    const strengthExercisesHost = strengthEditor?.querySelector('[data-strength-exercises]')
    const strengthEmpty = strengthEditor?.querySelector('[data-strength-empty]')
    const strengthExerciseList = strengthEditor?.querySelector('[data-strength-exercise-list]')
    const strengthLibrary = new Map()
    let strengthCatalog = []
    const strengthReferenceCache = new Map()
    const normalizeStrengthName = value => String(value || '').normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR').replace(/[‐‑‒–—―_\/\\-]+/g, ' ').replace(/[^\p{L}\p{N}\s]/gu, ' ').replace(/\s+/g, ' ').trim()
    const strengthSimilarity = (left, right) => {
        const a = normalizeStrengthName(left)
        const b = normalizeStrengthName(right)
        if (!a || !b) return 0
        if (a === b) return 1
        const previous = Array.from({length: b.length + 1}, (_, index) => index)
        for (let i = 1; i <= a.length; i += 1) {
            const current = [i]
            for (let j = 1; j <= b.length; j += 1) current[j] = Math.min(current[j - 1] + 1, previous[j] + 1, previous[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1))
            previous.splice(0, previous.length, ...current)
        }
        return 1 - previous[b.length] / Math.max(a.length, b.length)
    }
    const strengthEscape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]))
    const setStrengthLibrary = (items = []) => {
        strengthLibrary.clear()
        strengthCatalog = []
        if (strengthExerciseList) strengthExerciseList.replaceChildren()
        items.forEach((item) => {
            const name = String(item?.name || item?.nome || '').trim()
            const id = String(item?.id || item?.idexercicio || '').trim()
            if (!name) return
            const catalogItem = {id, name}
            strengthLibrary.set(normalizeStrengthName(name), catalogItem)
            strengthCatalog.push(catalogItem)
            if (!strengthExerciseList) return
            const option = document.createElement('option')
            option.value = name
            option.dataset.exerciseId = id
            strengthExerciseList.appendChild(option)
        })
    }
    const readStrengthLibrary = () => {
        const source = strengthEditor?.querySelector('[data-strength-library-json]')
        if (!source) return []
        try { return JSON.parse(source.textContent || '[]') } catch (_) { return [] }
    }
    const strengthSetMarkup = () => `
        <div class="activity-strength-set" data-strength-set>
            <strong data-strength-set-number>1</strong>
            <select aria-label="${strengthEscape(tr('activity.strength.set_type'))}"><option value="trabalho">${strengthEscape(tr('activity.strength.normal'))}</option><option value="aquecimento">${strengthEscape(tr('activity.strength.warmup'))}</option><option value="drop">${strengthEscape(tr('activity.strength.drop'))}</option><option value="falha">${strengthEscape(tr('activity.strength.failure'))}</option><option value="outro">${strengthEscape(tr('activity.strength.other'))}</option></select>
            <input type="number" min="0" max="9999.999" step="0.25" inputmode="decimal" placeholder="—" aria-label="${strengthEscape(tr('activity.strength.load_kg'))}">
            <input type="number" min="0" max="999" step="1" inputmode="numeric" placeholder="—" aria-label="${strengthEscape(tr('activity.strength.repetitions'))}">
            <input type="number" min="0" max="10" step="0.5" inputmode="decimal" placeholder="—" aria-label="${strengthEscape(tr('activity.strength.rir'))}">
            <label class="activity-strength-done"><input type="hidden" value="0"><input type="checkbox" value="1" checked><span>✓</span></label>
            <button type="button" class="activity-strength-remove-set" data-strength-remove-set aria-label="${strengthEscape(tr('activity.strength.remove_set'))}">×</button>
        </div>`
    const strengthExerciseMarkup = () => `
        <article class="activity-strength-exercise" data-strength-exercise>
            <div class="activity-strength-exercise-head">
                <label><span>${strengthEscape(tr('activity.strength.exercise'))}</span><input type="text" list="activity-strength-exercise-options" data-strength-exercise-name autocomplete="off" placeholder="${strengthEscape(tr('activity.strength.exercise_placeholder'))}"><input type="hidden" data-strength-exercise-id></label>
                <button type="button" class="activity-inline-action is-danger" data-strength-remove-exercise>${strengthEscape(tr('activity.strength.remove'))}</button>
            </div>
            <div class="activity-strength-suggestions" data-strength-suggestions hidden></div>
            <div class="activity-strength-reference" data-strength-reference hidden></div>
            <div class="activity-strength-sets" data-strength-sets>
                <div class="activity-strength-set activity-strength-set-head" aria-hidden="true"><span>${strengthEscape(tr('activity.strength.set'))}</span><span>${strengthEscape(tr('activity.strength.type'))}</span><span>kg</span><span>Reps</span><span>RIR</span><span>${strengthEscape(tr('activity.strength.done'))}</span><span></span></div>
                ${strengthSetMarkup()}
            </div>
            <button type="button" class="activity-inline-action activity-strength-add-set" data-strength-add-set>${strengthEscape(tr('activity.strength.add_set'))}</button>
        </article>`
    const reindexStrengthEditor = () => {
        strengthExercisesHost?.querySelectorAll('[data-strength-exercise]').forEach((exercise, exerciseIndex) => {
            const name = exercise.querySelector('[data-strength-exercise-name]')
            const id = exercise.querySelector('[data-strength-exercise-id]')
            if (name) name.name = `strength_exercises[${exerciseIndex}][nome]`
            if (id) id.name = `strength_exercises[${exerciseIndex}][idexercicio]`
            exercise.querySelectorAll('[data-strength-set]').forEach((set, setIndex) => {
                set.querySelector('[data-strength-set-number]')?.replaceChildren(document.createTextNode(String(setIndex + 1)))
                const select = set.querySelector('select')
                const inputs = set.querySelectorAll('input')
                if (select) select.name = `strength_exercises[${exerciseIndex}][series][${setIndex}][tipo]`
                if (inputs[0]) inputs[0].name = `strength_exercises[${exerciseIndex}][series][${setIndex}][carga_kg]`
                if (inputs[1]) inputs[1].name = `strength_exercises[${exerciseIndex}][series][${setIndex}][repeticoes]`
                if (inputs[2]) inputs[2].name = `strength_exercises[${exerciseIndex}][series][${setIndex}][rir]`
                if (inputs[3]) inputs[3].name = `strength_exercises[${exerciseIndex}][series][${setIndex}][concluida]`
                if (inputs[4]) inputs[4].name = `strength_exercises[${exerciseIndex}][series][${setIndex}][concluida]`
            })
        })
        if (strengthEmpty) strengthEmpty.hidden = Boolean(strengthExercisesHost?.querySelector('[data-strength-exercise]'))
    }
    const addStrengthExercise = () => {
        if (!strengthExercisesHost) return
        strengthExercisesHost.insertAdjacentHTML('beforeend', strengthExerciseMarkup())
        reindexStrengthEditor()
        requestAnimationFrame(() => strengthExercisesHost.lastElementChild?.querySelector('[data-strength-exercise-name]')?.focus())
    }
    const syncStrengthExerciseId = input => {
        const exercise = input?.closest('[data-strength-exercise]')
        const hidden = exercise?.querySelector('[data-strength-exercise-id]')
        const suggestions = exercise?.querySelector('[data-strength-suggestions]')
        if (!hidden) return
        const normalized = normalizeStrengthName(input.value)
        const match = strengthLibrary.get(normalized)
        hidden.value = match?.id || ''
        if (!suggestions) return
        if (match || normalized.length < 3) {
            suggestions.hidden = true
            suggestions.replaceChildren()
            return
        }
        const candidates = strengthCatalog.map(item => ({...item, score: strengthSimilarity(normalized, item.name)})).filter(item => item.score >= .72).sort((a, b) => b.score - a.score).slice(0, 2)
        if (!candidates.length) {
            suggestions.hidden = true
            suggestions.replaceChildren()
            return
        }
        suggestions.innerHTML = `<span>${strengthEscape(tr('activity.strength.looks_like'))}</span>${candidates.map(item => `<button type="button" class="activity-inline-action" data-strength-suggestion data-exercise-id="${strengthEscape(item.id)}" data-exercise-name="${strengthEscape(item.name)}">${strengthEscape(item.name)}</button>`).join('')}`
        suggestions.hidden = false
    }
    const applyStrengthReference = (exercise, reference) => {
        if (exercise) exercise._stridebrStrengthReference = reference || null
        const host = exercise?.querySelector('[data-strength-reference]')
        if (!host) return
        const latest = reference?.ultima_sessao || null
        if (!latest) {
            host.hidden = true
            host.replaceChildren()
            exercise.dataset.strengthLatestSets = ''
            return
        }
        const date = latest.data_inicio ? new Date(String(latest.data_inicio).replace(' ', 'T')) : null
        const dateText = date && Number.isFinite(date.getTime()) ? i18n.date?.(date, {day: '2-digit', month: '2-digit'}) || date.toLocaleDateString(i18n.locale === 'en' ? 'en-US' : 'pt-BR', {day: '2-digit', month: '2-digit'}) : tr('activity.strength.last_session')
        const bestLoad = Number(reference?.melhor_carga_kg)
        const e1rm = Number(reference?.melhor_e1rm_kg)
        const meta = [Number.isFinite(bestLoad) && bestLoad > 0 ? tr('activity.strength.best_load', {value: i18n.number?.(bestLoad, 2, true) ?? String(bestLoad)}) : '', Number.isFinite(e1rm) && e1rm > 0 ? tr('activity.strength.estimated_1rm', {value: i18n.number?.(e1rm, 1, true) ?? String(e1rm)}) : ''].filter(Boolean).join(' · ')
        host.innerHTML = `<div class="activity-strength-reference-copy"><span>${strengthEscape(tr('activity.strength.last_time', {date: dateText}))}</span>${meta ? `<strong>${strengthEscape(meta)}</strong>` : ''}</div><button type="button" class="activity-inline-action activity-strength-use-last" data-strength-use-last>${strengthEscape(tr('activity.strength.use_last'))}</button>`
        host.hidden = false
        const latestSets = Array.isArray(latest.series) ? latest.series : []
        exercise.dataset.strengthLatestSets = JSON.stringify(latestSets)
        exercise.querySelectorAll('[data-strength-set]').forEach((set, index) => {
            const previous = latestSets[index]
            if (!previous) return
            const inputs = set.querySelectorAll('input[type="number"]')
            if (inputs[0] && inputs[0].value === '' && previous.carga_kg !== null && previous.carga_kg !== undefined) inputs[0].placeholder = i18n.number?.(Number(previous.carga_kg), 2, true) ?? String(previous.carga_kg)
            if (inputs[1] && inputs[1].value === '' && previous.repeticoes !== null && previous.repeticoes !== undefined) inputs[1].placeholder = String(previous.repeticoes)
            if (inputs[2] && inputs[2].value === '' && previous.rir !== null && previous.rir !== undefined) inputs[2].placeholder = String(previous.rir)
        })
    }
    const populateStrengthSetFromReference = (set, previous = {}) => {
        if (!set) return
        const select = set.querySelector('select')
        const numeric = set.querySelectorAll('input[type="number"]')
        const done = set.querySelector('input[type="checkbox"]')
        const allowedTypes = ['trabalho', 'aquecimento', 'drop', 'falha', 'outro']
        const type = allowedTypes.includes(String(previous.tipo || '')) ? String(previous.tipo) : 'trabalho'
        if (select) select.value = type
        if (numeric[0]) numeric[0].value = previous.carga_kg === null || previous.carga_kg === undefined ? '' : String(previous.carga_kg)
        if (numeric[1]) numeric[1].value = previous.repeticoes === null || previous.repeticoes === undefined ? '' : String(previous.repeticoes)
        if (numeric[2]) numeric[2].value = previous.rir === null || previous.rir === undefined ? '' : String(previous.rir)
        if (done) done.checked = true
    }
    const useLatestStrengthSession = exercise => {
        const latest = exercise?._stridebrStrengthReference?.ultima_sessao
        const previousSets = Array.isArray(latest?.series) ? latest.series : []
        const setsHost = exercise?.querySelector('[data-strength-sets]')
        if (!setsHost || previousSets.length === 0) return
        setsHost.querySelectorAll('[data-strength-set]').forEach(row => row.remove())
        previousSets.forEach((previous) => {
            setsHost.insertAdjacentHTML('beforeend', strengthSetMarkup())
            populateStrengthSetFromReference(setsHost.lastElementChild, previous)
        })
        reindexStrengthEditor()
        applyStrengthReference(exercise, exercise._stridebrStrengthReference)
        const button = exercise.querySelector('[data-strength-use-last]')
        if (button) {
            button.textContent = tr('activity.strength.last_applied')
            button.classList.add('is-applied')
        }
        exercise.dispatchEvent(new Event('input', {bubbles: true}))
    }

    const loadStrengthReference = async input => {
        const exercise = input?.closest('[data-strength-exercise]')
        const hidden = exercise?.querySelector('[data-strength-exercise-id]')
        const name = String(input?.value || '').trim()
        const id = String(hidden?.value || '').trim()
        if (!exercise || (!id && name.length < 2)) {
            applyStrengthReference(exercise, null)
            return
        }
        const key = id ? `id:${id}` : `name:${normalizeStrengthName(name)}`
        if (strengthReferenceCache.has(key)) {
            applyStrengthReference(exercise, strengthReferenceCache.get(key))
            return
        }
        const host = exercise.querySelector('[data-strength-reference]')
        if (host) {
            host.textContent = tr('activity.strength.loading_reference')
            host.hidden = false
        }
        try {
            const params = new URLSearchParams(id ? {id} : {nome: name})
            const response = await fetch(`/api/exercicio-forca-referencia.php?${params}`, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'})
            const data = await response.json().catch(() => null)
            if (!response.ok || !data?.ok) throw new Error('reference')
            const reference = data.referencia || null
            strengthReferenceCache.set(key, reference)
            applyStrengthReference(exercise, reference)
        } catch (_) {
            if (host) host.hidden = true
        }
    }
    const syncStrengthVisibility = () => {
        if (!strengthEditor || !modalitySelect) return
        const family = String(modalitySelect.selectedOptions[0]?.dataset.family || '')
        const visible = family === 'strength'
        strengthEditor.hidden = !visible
        strengthEditor.querySelectorAll('input, select, textarea, button').forEach((element) => {
            if (element.matches('[data-strength-add-exercise]')) return
            element.disabled = !visible
        })
        if (visible) reindexStrengthEditor()
    }
    setStrengthLibrary(readStrengthLibrary())
    reindexStrengthEditor()
    requestAnimationFrame(() => strengthExercisesHost?.querySelectorAll('[data-strength-exercise-name]').forEach(input => loadStrengthReference(input)))
    strengthEditor?.addEventListener('input', (event) => {
        if (event.target.matches('[data-strength-exercise-name]')) syncStrengthExerciseId(event.target)
    })
    strengthEditor?.addEventListener('change', (event) => {
        if (event.target.matches('[data-strength-exercise-name]')) {
            syncStrengthExerciseId(event.target)
            loadStrengthReference(event.target)
        }
    })
    strengthEditor?.addEventListener('click', (event) => {
        const suggestion = event.target.closest('[data-strength-suggestion]')
        if (suggestion) {
            const exercise = suggestion.closest('[data-strength-exercise]')
            const input = exercise?.querySelector('[data-strength-exercise-name]')
            const hidden = exercise?.querySelector('[data-strength-exercise-id]')
            if (input) input.value = suggestion.dataset.exerciseName || ''
            if (hidden) hidden.value = suggestion.dataset.exerciseId || ''
            const host = exercise?.querySelector('[data-strength-suggestions]')
            if (host) { host.hidden = true; host.replaceChildren() }
            if (input) loadStrengthReference(input)
            return
        }
        const useLast = event.target.closest('[data-strength-use-last]')
        if (useLast) {
            useLatestStrengthSession(useLast.closest('[data-strength-exercise]'))
            return
        }
        if (event.target.closest('[data-strength-add-exercise]')) {
            addStrengthExercise()
            return
        }
        const removeExercise = event.target.closest('[data-strength-remove-exercise]')
        if (removeExercise) {
            removeExercise.closest('[data-strength-exercise]')?.remove()
            reindexStrengthEditor()
            return
        }
        const addSet = event.target.closest('[data-strength-add-set]')
        if (addSet) {
            const sets = addSet.closest('[data-strength-exercise]')?.querySelector('[data-strength-sets]')
            sets?.insertAdjacentHTML('beforeend', strengthSetMarkup())
            reindexStrengthEditor()
            const exercise = addSet.closest('[data-strength-exercise]')
            if (exercise?._stridebrStrengthReference) applyStrengthReference(exercise, exercise._stridebrStrengthReference)
            sets?.querySelector('[data-strength-set]:last-child input[type="number"]')?.focus()
            return
        }
        const removeSet = event.target.closest('[data-strength-remove-set]')
        if (removeSet) {
            const exercise = removeSet.closest('[data-strength-exercise]')
            const sets = exercise?.querySelectorAll('[data-strength-set]') || []
            if (sets.length <= 1) {
                const row = removeSet.closest('[data-strength-set]')
                row?.querySelectorAll('input[type="number"]').forEach(input => { input.value = '' })
                row?.querySelector('select') && (row.querySelector('select').value = 'trabalho')
            } else removeSet.closest('[data-strength-set]')?.remove()
            reindexStrengthEditor()
        }
    })
    const syncLogDetailButtons = () => {
        form?.querySelectorAll('[data-toggle-log-detail]').forEach((button) => {
            const key = button.dataset.toggleLogDetail || ''
            const detail = form.querySelector(`[data-log-detail="${CSS.escape(key)}"]`)
            button.setAttribute('aria-expanded', detail && !detail.hidden ? 'true' : 'false')
            if (key === 'effort') {
                const value = form.querySelector('[data-effort-value]')?.value || ''
                button.textContent = value ? tr('activity.effort_compact', {value}) : tr('activity.add_effort')
                button.classList.toggle('is-active', Boolean(value))
            }
            if (key === 'equipment') {
                const count = equipmentHost?.querySelectorAll('input[type="checkbox"]:checked').length || 0
                button.textContent = count ? tr('activity.equipment_count', {count}) : tr('activity.equipment_add')
                button.classList.toggle('is-active', count > 0)
            }
        })
    }
    const setLogDetailOpen = (key, open) => {
        const detail = form?.querySelector(`[data-log-detail="${CSS.escape(key)}"]`)
        if (!detail) return
        detail.hidden = !open
        syncLogDetailButtons()
        if (open) requestAnimationFrame(() => {
            const target = key === 'effort'
                ? detail.querySelector('[data-effort]')
                : detail.querySelector('input[type="checkbox"], a, button, select, textarea')
            target?.focus()
        })
    }
    const editorDetailsRequests = new Map()
    let editorSupportLoaded = false
    const loadedEditorModels = new Set(Array.from(modelPanelsHost?.querySelectorAll('[data-model-panel]') || []).map(panel => String(panel.dataset.modelPanel || '')).filter(Boolean))
    let leafletPromise = null
    const activityEditModal = document.querySelector('[data-activity-edit-modal]')
    const activityEditFrame = document.querySelector('[data-activity-edit-frame]')
    const activityEditLoading = document.querySelector('[data-activity-edit-loading]')
    let activityEditCloseTimer = 0
    let activityEditLoadTimer = 0
    let activityEditUrl = ''
    let activityEditTrigger = null
    let activityEditId = ''
    const openActivityEdit = href => {
        if (!activityEditModal || !activityEditFrame) return
        const url = new URL(href, window.location.origin)
        activityEditId = String(url.searchParams.get('id') || '')
        url.searchParams.set('embed', '1')
        activityEditUrl = `${url.pathname}?${url.searchParams.toString()}`
        activityEditTrigger = document.activeElement instanceof HTMLElement ? document.activeElement : null
        window.clearTimeout(activityEditCloseTimer)
        activityEditModal.hidden = false
        activityEditModal.classList.remove('is-ready', 'is-error')
        if (activityEditLoading) activityEditLoading.hidden = false
        document.documentElement.classList.add('activity-edit-open')
        requestAnimationFrame(() => activityEditModal.classList.add('is-open'))
        activityEditFrame.src = activityEditUrl
        window.clearTimeout(activityEditLoadTimer)
        activityEditLoadTimer = window.setTimeout(() => {
            if (activityEditModal?.classList.contains('is-ready')) return
            if (activityEditLoading) activityEditLoading.hidden = true
            activityEditModal?.classList.add('is-error')
        }, 9000)
    }
    const setActivityEditRouteSubview = active => {
        const enabled = Boolean(active)
        activityEditModal?.classList.toggle('is-route-subview', enabled)
        document.documentElement.classList.toggle('activity-edit-route-open', enabled)
        if (enabled) requestAnimationFrame(() => requestAnimationFrame(() => {
            try { activityEditFrame?.contentWindow?.postMessage({type: 'stridebr:activity-route-parent-resized'}, window.location.origin) } catch (_) {}
        }))
    }
    const closeActivityEdit = () => {
        if (!activityEditModal || activityEditModal.hidden) return
        setActivityEditRouteSubview(false)
        activityEditModal.classList.remove('is-open', 'is-ready', 'is-error')
        window.clearTimeout(activityEditLoadTimer)
        document.documentElement.classList.remove('activity-edit-open')
        activityEditCloseTimer = window.setTimeout(() => {
            activityEditModal.hidden = true
            if (activityEditFrame) activityEditFrame.src = 'about:blank'
            if (activityEditLoading) activityEditLoading.hidden = false
        }, 180)
        activityEditTrigger?.focus?.()
        activityEditTrigger = null
    }
    activityEditFrame?.addEventListener('load', () => { if (!activityEditFrame.src || activityEditFrame.src === 'about:blank') return })
    document.addEventListener('click', event => {
        const editLink = event.target.closest('a[href*="/user/editatividade.php"]')
        if (editLink && editLink instanceof HTMLAnchorElement && !activityEditModal?.contains(editLink)) {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return
            event.preventDefault()
            closeActivityContextMenu?.()
            openActivityEdit(editLink.href)
            return
        }
        if (event.target.closest('[data-close-activity-edit]')) {
            event.preventDefault()
            closeActivityEdit()
        }
    })
    document.addEventListener('click', event => {
        const retry = event.target.closest('[data-activity-edit-retry]')
        if (retry) { event.preventDefault(); if (activityEditUrl) openActivityEdit(activityEditUrl); return }
        const newPage = event.target.closest('[data-activity-edit-new-page]')
        if (newPage && activityEditUrl) newPage.href = activityEditUrl.replace(/([?&])embed=1(&|$)/, '$1').replace(/[?&]$/, '')
    })
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && activityEditModal && !activityEditModal.hidden) {
            event.preventDefault()
            closeActivityEdit()
        }
    })
    window.addEventListener('message', event => {
        if (event.origin !== window.location.origin || event.source !== activityEditFrame?.contentWindow) return
        const type = String(event.data?.type || '')
        if (type === 'stridebr:activity-edit-ready') {
            window.clearTimeout(activityEditLoadTimer)
            activityEditModal?.classList.remove('is-error')
            activityEditModal?.classList.add('is-ready')
            if (activityEditLoading) activityEditLoading.hidden = true
            return
        }
        if (type === 'stridebr:activity-edit-cancel') {
            closeActivityEdit()
            return
        }
        if (type === 'stridebr:activity-route-subview-open') {
            setActivityEditRouteSubview(true)
            return
        }
        if (type === 'stridebr:activity-route-subview-close') {
            setActivityEditRouteSubview(false)
            return
        }
        if (type !== 'stridebr:activity-edit-saved' && type !== 'stridebr:activity-edit-deleted') return
        const id = String(event.data?.id || activityEditId || '')
        const wasActiveDetail = id !== '' && String(activeDetailId || '') === id
        closeActivityEdit()
        clearHistoryCache()
        if (id) detailCache.delete(id)
        if (type === 'stridebr:activity-edit-saved') {
            showActivityToast(tr('activity.updated'), 'success')
            loadHistory({background: true, preserveDetail: true}).then(() => {
                if (id) highlightImportedActivities([id])
                if (wasActiveDetail) openActivityDetail(id)
            })
        } else {
            if (wasActiveDetail) closeActivityDetails()
            showActivityToast(tr('activity.removed_undo_hint'), 'success')
            loadHistory({background: true, preserveDetail: false})
        }
    })

    const activityToolOverlay = document.querySelector('[data-activity-tool-overlay]')
    const activityToolContent = document.querySelector('[data-activity-tool-content]')
    const activityToolTitle = document.querySelector('[data-activity-tool-title]')
    const activityToolCache = new Map()
    let activityToolRequest = null
    let activeActivityTool = ''
    const activityTools = {
        compare: {title: tr('activity.tool_compare'), selector: 'main.product-page'},
        exchange: {title: tr('activity.tool_exchange'), selector: 'main.exchange-page'},
    }
    const normalizeActivityToolUrl = (href, type) => {
        const url = new URL(href, window.location.origin)
        if (type === 'compare' && !url.pathname.endsWith('/comparar-atividades.php')) url.pathname = '/user/comparar-atividades.php'
        if (type === 'exchange' && !url.pathname.endsWith('/importar-exportar.php')) url.pathname = '/user/importar-exportar.php'
        return url
    }
    const extractActivityToolFragment = (html, sourceUrl, type) => {
        const parsed = new DOMParser().parseFromString(html, 'text/html')
        const config = activityTools[type]
        const main = parsed.querySelector(config?.selector || 'main')
        if (!main) throw new Error(tr('activity.tool_open_error'))
        main.querySelector('.product-page-header, .exchange-heading')?.remove()
        main.querySelectorAll('[onchange]').forEach(element => element.removeAttribute('onchange'))
        main.querySelectorAll('a[href]').forEach(anchor => {
            const raw = anchor.getAttribute('href') || ''
            if (!raw || raw.startsWith('#')) return
            const resolved = new URL(raw, sourceUrl)
            anchor.setAttribute('href', `${resolved.pathname}${resolved.search}${resolved.hash}`)
        })
        main.querySelectorAll('form').forEach(form => {
            const raw = form.getAttribute('action') || sourceUrl.pathname
            const resolved = new URL(raw, sourceUrl)
            form.setAttribute('action', `${resolved.pathname}${resolved.search}`)
        })
        let extra = ''
        if (type === 'exchange') {
            const modalities = parsed.querySelector('#activity-import-modalities')
            if (modalities) extra = `<script type="application/json" id="activity-import-modalities">${modalities.textContent || '[]'}</script>`
        }
        return `${main.outerHTML}${extra}`
    }
    const loadActivityToolFragment = async (type, href, {force = false} = {}) => {
        const sourceUrl = normalizeActivityToolUrl(href, type)
        const key = `${type}:${sourceUrl.pathname}${sourceUrl.search}`
        if (!force && activityToolCache.has(key)) return {html: activityToolCache.get(key), sourceUrl}
        activityToolRequest?.abort()
        activityToolRequest = new AbortController()
        const response = await fetch(sourceUrl, {credentials: 'same-origin', headers: {'Accept': 'text/html'}, signal: activityToolRequest.signal})
        if (!response.ok) throw new Error(tr('activity.tool_load_error'))
        const html = extractActivityToolFragment(await response.text(), sourceUrl, type)
        activityToolCache.set(key, html)
        return {html, sourceUrl}
    }
    const syncActivityToolHistory = (type, sourceUrl, mode = 'push') => {
        const url = new URL(window.location.href)
        url.pathname = '/user/atividades.php'
        url.searchParams.set('tool', type)
        ;['a', 'b'].forEach(key => {
            const value = sourceUrl.searchParams.get(key)
            if (value) url.searchParams.set(key, value)
            else url.searchParams.delete(key)
        })
        const state = {activityTool: type}
        if (mode === 'replace') history.replaceState(state, '', `${url.pathname}${url.search}${url.hash}`)
        else history.pushState(state, '', `${url.pathname}${url.search}${url.hash}`)
    }
    const openActivityTool = async (type, href, {historyMode = 'push'} = {}) => {
        const config = activityTools[type]
        if (!config || !activityToolOverlay || !activityToolContent) return
        activeActivityTool = type
        activityToolOverlay.hidden = false
        requestAnimationFrame(() => activityToolOverlay.classList.add('is-open'))
        document.documentElement.classList.add('activity-tool-open')
        if (activityToolTitle) activityToolTitle.textContent = config.title
        activityToolContent.innerHTML = `<div class="activity-tool-loading"><span></span><strong>${escapeHtml(tr('common.loading'))}</strong></div>`
        try {
            const {html, sourceUrl} = await loadActivityToolFragment(type, href, {force: historyMode === 'replace'})
            if (activeActivityTool !== type) return
            activityToolContent.innerHTML = html
            if (historyMode !== 'none') syncActivityToolHistory(type, sourceUrl, historyMode)
            if (type === 'exchange') window.StrideBRActivityExchangeInit?.()
        } catch (error) {
            if (error?.name === 'AbortError') return
            activityToolContent.innerHTML = `<div class="activity-tool-error"><strong>${escapeHtml(tr('activity.tool_open_short_error'))}</strong><button type="button" data-retry-activity-tool>${escapeHtml(tr('common.try_again'))}</button></div>`
        }
    }
    const closeActivityTool = ({fromHistory = false} = {}) => {
        if (!activityToolOverlay || activityToolOverlay.hidden) return
        activeActivityTool = ''
        activityToolRequest?.abort()
        activityToolOverlay.classList.remove('is-open')
        document.documentElement.classList.remove('activity-tool-open')
        window.setTimeout(() => {
            if (!activeActivityTool) {
                activityToolOverlay.hidden = true
                if (activityToolContent) activityToolContent.replaceChildren()
            }
        }, 180)
        if (!fromHistory) {
            if (history.state?.activityTool) history.back()
            else {
                const url = new URL(window.location.href)
                ;['tool', 'a', 'b'].forEach(key => url.searchParams.delete(key))
                history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`)
            }
        }
    }
    document.addEventListener('click', event => {
        const toolLink = event.target.closest('[data-activity-tool]')
        if (toolLink && toolLink instanceof HTMLAnchorElement) {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return
            event.preventDefault()
            openActivityTool(toolLink.dataset.activityTool || '', toolLink.href)
            return
        }
        if (event.target.closest('[data-close-activity-tool]')) {
            event.preventDefault()
            closeActivityTool()
            return
        }
        if (event.target.closest('[data-retry-activity-tool]') && activeActivityTool) {
            const params = new URLSearchParams(window.location.search)
            const target = activeActivityTool === 'compare'
                ? `/user/comparar-atividades.php?${new URLSearchParams([...params].filter(([key]) => key === 'a' || key === 'b')).toString()}`
                : '/user/importar-exportar.php'
            openActivityTool(activeActivityTool, target, {historyMode: 'none'})
            return
        }
        if (!activityToolContent || !activityToolContent.contains(event.target)) return
        const compareLink = event.target.closest('a[href*="comparar-atividades.php"]')
        if (compareLink) {
            event.preventDefault()
            openActivityTool('compare', compareLink.href, {historyMode: 'replace'})
            return
        }
        const backToActivities = event.target.closest('a[href*="/user/atividades.php"]')
        if (backToActivities) {
            event.preventDefault()
            closeActivityTool()
        }
    })
    activityToolContent?.addEventListener('submit', event => {
        const form = event.target.closest('.compare-form')
        if (!form) return
        event.preventDefault()
        const url = new URL(form.action || '/user/comparar-atividades.php', window.location.origin)
        const data = new FormData(form)
        data.forEach((value, key) => url.searchParams.set(key, String(value)))
        openActivityTool('compare', url.href, {historyMode: 'replace'})
    })
    activityToolContent?.addEventListener('change', event => {
        const select = event.target.closest('.compare-form select[name="a"]')
        if (!select) return
        select.form?.requestSubmit()
    })
    window.addEventListener('popstate', () => {
        const url = new URL(window.location.href)
        const tool = url.searchParams.get('tool') || ''
        if (!tool) {
            closeActivityTool({fromHistory: true})
            return
        }
        const target = tool === 'compare'
            ? `/user/comparar-atividades.php?${new URLSearchParams([...url.searchParams].filter(([key]) => key === 'a' || key === 'b')).toString()}`
            : '/user/importar-exportar.php'
        openActivityTool(tool, target, {historyMode: 'none'})
    })

    const fetchWithDeadline = async (resource, options = {}, timeoutMs = 12000) => {
        const controller = new AbortController()
        const parentSignal = options.signal
        let timedOut = false
        const abortFromParent = () => controller.abort()
        if (parentSignal?.aborted) throw new DOMException(tr('activity.request_cancelled'), 'AbortError')
        parentSignal?.addEventListener('abort', abortFromParent, {once: true})
        const timer = window.setTimeout(() => {
            timedOut = true
            controller.abort()
        }, timeoutMs)
        try {
            return await fetch(resource, {...options, signal: controller.signal})
        } catch (error) {
            if (timedOut) {
                const timeoutError = new Error('A resposta demorou demais. Tente novamente.')
                timeoutError.name = 'TimeoutError'
                throw timeoutError
            }
            throw error
        } finally {
            window.clearTimeout(timer)
            parentSignal?.removeEventListener('abort', abortFromParent)
        }
    }

    const fetchWithRetry = async (resource, options = {}, {timeout = 12000, retries = 1} = {}) => {
        let lastError = null
        for (let attempt = 0; attempt <= retries; attempt += 1) {
            if (options.signal?.aborted) throw new DOMException(tr('activity.request_cancelled'), 'AbortError')
            try {
                return await fetchWithDeadline(resource, options, timeout)
            } catch (error) {
                lastError = error
                if (error?.name === 'AbortError' || options.signal?.aborted || attempt >= retries) throw error
                await new Promise(resolve => window.setTimeout(resolve, 180 + attempt * 180))
            }
        }
        throw lastError || new Error(tr('activity.request_error'))
    }

    const ensureLeaflet = () => {
        if (window.L) return Promise.resolve(window.L)
        if (leafletPromise) return leafletPromise
        leafletPromise = new Promise((resolve, reject) => {
            let settled = false
            const finish = (ok, value) => {
                if (settled) return
                settled = true
                window.clearTimeout(timer)
                ok ? resolve(value) : reject(value)
            }
            const timer = window.setTimeout(() => finish(false, new Error('O mapa demorou demais para carregar.')), 8000)
            if (!document.querySelector('link[data-stridebr-leaflet]')) {
                const link = document.createElement('link')
                link.rel = 'stylesheet'
                link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
                link.crossOrigin = 'anonymous'
                link.dataset.stridebrLeaflet = '1'
                document.head.appendChild(link)
            }
            const existing = document.querySelector('script[data-stridebr-leaflet]')
            if (existing) {
                existing.addEventListener('load', () => finish(true, window.L), {once: true})
                existing.addEventListener('error', () => finish(false, new Error(tr('activity.map_unavailable'))), {once: true})
                return
            }
            const script = document.createElement('script')
            script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
            script.crossOrigin = 'anonymous'
            script.dataset.stridebrLeaflet = '1'
            script.onload = () => finish(true, window.L)
            script.onerror = () => finish(false, new Error(tr('activity.map_unavailable')))
            document.head.appendChild(script)
        }).catch(error => {
            leafletPromise = null
            throw error
        })
        return leafletPromise
    }

    const digitsOnly = (value, maxLength) => String(value ?? '').replace(/\D/g, '').slice(0, maxLength)

    const normalizeSegment = (input, max, pad = true) => {
        if (!input) return null
        const clean = digitsOnly(input.value, Number(input.maxLength) > 0 ? Number(input.maxLength) : 2)
        if (clean === '') {
            input.value = ''
            return null
        }
        const parsed = Number.parseInt(clean, 10)
        if (!Number.isFinite(parsed) || parsed > max) {
            input.value = '00'
            input.select()
            return 0
        }
        input.value = pad ? String(parsed).padStart(2, '0') : String(parsed)
        return parsed
    }

    const bindSegment = (input, max, next, options = {}) => {
        if (!input || input.dataset.segmentBound === '1') return
        input.dataset.segmentBound = '1'
        const maxLength = options.maxLength || Number(input.maxLength) || 2
        input.addEventListener('focus', () => input.select?.())
        input.addEventListener('input', () => {
            const clean = digitsOnly(input.value, maxLength)
            input.value = clean
            if (clean === '') return
            const parsed = Number.parseInt(clean, 10)
            if (clean.length >= maxLength) {
                if (!Number.isFinite(parsed) || parsed > max) {
                    input.value = '00'
                    input.select()
                    return
                }
                if (options.autoAdvance !== false && next) {
                    next.focus()
                    next.select?.()
                }
                return
            }
            // Avança sem esperar o segundo dígito quando ele nunca poderia formar
            // um valor válido (ex.: digitou "8" num campo de hora com máximo 23 —
            // "8_" só poderia virar 80-89, sempre inválido, então "8" já é o valor final).
            if (options.autoAdvance !== false && next && Number.isFinite(parsed) && parsed * 10 > max) {
                next.focus()
                next.select?.()
            }
        })
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault()
                normalizeSegment(input, max, options.pad !== false)
                next?.focus()
                next?.select?.()
            }
        })
    }

    const bindClockField = (field) => {
        if (!field || field.dataset.bound === '1') return
        field.dataset.bound = '1'
        const hours = field.querySelector('[data-clock-hours]')
        const minutes = field.querySelector('[data-clock-minutes]')
        const hidden = field.querySelector('[data-clock-value]')
        const quickToggle = field.querySelector('[data-time-quick-toggle]')
        const quickMenu = field.querySelector('[data-time-quick-menu]')
        const sync = () => {
            const h = normalizeSegment(hours, 23)
            const m = normalizeSegment(minutes, 59)
            if (hidden) hidden.value = h === null || m === null ? '' : `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
            field.dispatchEvent(new CustomEvent('activity:valuechange', {bubbles: true}))
        }
        bindSegment(hours, 23, minutes)
        bindSegment(minutes, 59, null, {autoAdvance: false})
        ;[hours, minutes].forEach((input) => {
            input?.addEventListener('blur', sync)
            input?.addEventListener('change', sync)
        })
        field.querySelector('[data-time-now]')?.addEventListener('click', () => {
            const now = new Date()
            hours.value = String(now.getHours()).padStart(2, '0')
            minutes.value = String(now.getMinutes()).padStart(2, '0')
            sync()
            if (quickMenu) quickMenu.hidden = true
        })
        if (quickMenu && !quickMenu.children.length) {
            for (let hour = 5; hour <= 23; hour += 1) {
                for (const minute of [0, 30]) {
                    if (hour === 23 && minute === 30) continue
                    const value = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`
                    const button = document.createElement('button')
                    button.type = 'button'
                    button.dataset.quickTime = value
                    button.textContent = value
                    quickMenu.appendChild(button)
                }
            }
        }
        quickToggle?.addEventListener('click', () => {
            if (!quickMenu) return
            quickMenu.hidden = !quickMenu.hidden
        })
        quickMenu?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-quick-time]')
            if (!button) return
            const [h, m] = String(button.dataset.quickTime || '').split(':')
            hours.value = h || '00'
            minutes.value = m || '00'
            sync()
            quickMenu.hidden = true
        })
        document.addEventListener('click', (event) => {
            if (quickMenu && !field.contains(event.target)) quickMenu.hidden = true
        })
        sync()
    }

    let durationPrecisionEnabled = false
    let durationPrecisionWasManuallyChanged = false

    const durationToSeconds = field => {
        if (!field) return null
        const values = [
            field.querySelector('[data-duration-hours]')?.value,
            field.querySelector('[data-duration-minutes]')?.value,
            field.querySelector('[data-duration-seconds]')?.value,
            field.querySelector('[data-duration-milliseconds]')?.value,
        ].map(value => String(value ?? '').trim())
        if (values.every(value => value === '')) return null
        const h = Number.parseInt(values[0] || '0', 10) || 0
        const m = Number.parseInt(values[1] || '0', 10) || 0
        const sec = Number.parseInt(values[2] || '0', 10) || 0
        const ms = Math.min(999, Number.parseInt(values[3] || '0', 10) || 0)
        return (h * 3600000 + m * 60000 + sec * 1000 + ms) / 1000
    }

    const updateDurationPrecisionUi = () => {
        form?.setAttribute('data-duration-precision', durationPrecisionEnabled ? 'milliseconds' : 'seconds')
        form?.querySelectorAll('[data-duration-ms-wrap], [data-duration-ms-separator]').forEach(node => { node.hidden = !durationPrecisionEnabled })
        form?.querySelectorAll('[data-duration-precision-toggle]').forEach(button => {
            button.textContent = durationPrecisionEnabled ? '− ms' : '+ ms'
            button.setAttribute('aria-expanded', durationPrecisionEnabled ? 'true' : 'false')
            button.setAttribute('aria-label', tr(durationPrecisionEnabled ? 'activity.hide_milliseconds' : 'activity.show_milliseconds'))
            button.title = tr(durationPrecisionEnabled ? 'activity.hide_milliseconds' : 'activity.show_milliseconds')
        })
    }

    const setDurationPrecision = (enabled, {manual = false, ignoreManual = false} = {}) => {
        if (!manual && durationPrecisionWasManuallyChanged && !ignoreManual) return
        durationPrecisionEnabled = Boolean(enabled)
        if (manual) durationPrecisionWasManuallyChanged = true
        updateDurationPrecisionUi()
    }

    const preferredDurationPrecisionField = () => {
        if (!form) return null
        const panel = form.querySelector('[data-model-panel]:not([hidden])')
        const segmented = panel?.querySelector('[data-segment-mode-input]')?.value === '1'
        if (segmented) {
            const segmentField = panel.querySelector('[data-primary-segment-core] [data-duration-field]')
            if (segmentField) return segmentField
        }
        return panel?.querySelector('[data-primary-unit] [data-duration-field]')
            || form.querySelector('[data-primary-unit] [data-duration-field]')
            || form.querySelector('[data-duration-field]')
    }

    const mountDurationPrecisionToggle = (button) => {
        const field = preferredDurationPrecisionField()
        if (!field || !button) return
        let row = field.parentElement?.classList.contains('duration-control-row') ? field.parentElement : null
        if (!row) {
            row = document.createElement('div')
            row.className = 'duration-control-row'
            field.before(row)
            row.append(field)
        }
        if (button.parentElement !== row) row.append(button)
    }

    const ensureDurationPrecisionToggle = () => {
        if (!form) return
        let button = form.querySelector('[data-duration-precision-toggle]')
        if (!button) {
            button = document.createElement('button')
            button.type = 'button'
            button.className = 'duration-precision-toggle'
            button.dataset.durationPrecisionToggle = '1'
            button.addEventListener('click', () => setDurationPrecision(!durationPrecisionEnabled, {manual: true}))
        }
        mountDurationPrecisionToggle(button)
        updateDurationPrecisionUi()
    }

    const setDurationSeconds = (field, totalSeconds) => {
        if (!field || !Number.isFinite(totalSeconds) || totalSeconds < 0) return
        const totalMs = Math.min(359999999, Math.max(0, Math.round(totalSeconds * 1000)))
        const h = Math.floor(totalMs / 3600000)
        const remainingHour = totalMs % 3600000
        const m = Math.floor(remainingHour / 60000)
        const remainingMinute = remainingHour % 60000
        const sec = Math.floor(remainingMinute / 1000)
        const ms = remainingMinute % 1000
        const hours = field.querySelector('[data-duration-hours]')
        const minutes = field.querySelector('[data-duration-minutes]')
        const seconds = field.querySelector('[data-duration-seconds]')
        const milliseconds = field.querySelector('[data-duration-milliseconds]')
        if (hours) hours.value = String(h)
        if (minutes) minutes.value = String(m).padStart(2, '0')
        if (seconds) seconds.value = String(sec).padStart(2, '0')
        if (milliseconds) milliseconds.value = ms ? String(ms).padStart(3, '0') : ''
        if (ms) setDurationPrecision(true)
        syncDuration(field)
    }

    const syncDuration = field => {
        const hours = field.querySelector('[data-duration-hours]')
        const minutes = field.querySelector('[data-duration-minutes]')
        const seconds = field.querySelector('[data-duration-seconds]')
        const milliseconds = field.querySelector('[data-duration-milliseconds]')
        const hidden = field.querySelector('[data-duration-value]')
        const raw = [hours?.value, minutes?.value, seconds?.value, milliseconds?.value].map(value => String(value ?? '').trim())
        if (raw.every(value => value === '')) {
            if (hidden) hidden.value = ''
            field.dispatchEvent(new CustomEvent('activity:valuechange', {bubbles: true}))
            return
        }
        const h = Math.max(0, Number.parseInt(digitsOnly(hours?.value, 2) || '0', 10) || 0)
        const m = Math.max(0, Number.parseInt(digitsOnly(minutes?.value, 4) || '0', 10) || 0)
        const sec = Math.max(0, Number.parseInt(digitsOnly(seconds?.value, 4) || '0', 10) || 0)
        const ms = Math.min(999, Math.max(0, Number.parseInt(digitsOnly(milliseconds?.value, 3) || '0', 10) || 0))
        const totalMs = Math.min(359999999, h * 3600000 + m * 60000 + sec * 1000 + ms)
        const normalizedHours = Math.floor(totalMs / 3600000)
        const remainingHour = totalMs % 3600000
        const normalizedMinutes = Math.floor(remainingHour / 60000)
        const remainingMinute = remainingHour % 60000
        const normalizedSeconds = Math.floor(remainingMinute / 1000)
        const normalizedMilliseconds = remainingMinute % 1000
        if (hours) hours.value = String(normalizedHours)
        if (minutes) minutes.value = String(normalizedMinutes).padStart(2, '0')
        if (seconds) seconds.value = String(normalizedSeconds).padStart(2, '0')
        if (milliseconds) milliseconds.value = normalizedMilliseconds ? String(normalizedMilliseconds).padStart(3, '0') : (String(milliseconds.value || '').trim() === '' ? '' : '000')
        const base = `${String(normalizedHours).padStart(2, '0')}:${String(normalizedMinutes).padStart(2, '0')}:${String(normalizedSeconds).padStart(2, '0')}`
        if (hidden) hidden.value = normalizedMilliseconds ? `${base}.${String(normalizedMilliseconds).padStart(3, '0')}` : base
        if (normalizedMilliseconds) setDurationPrecision(true)
        field.dispatchEvent(new CustomEvent('activity:valuechange', {bubbles: true}))
    }

    const bindDurationField = field => {
        if (!field || field.dataset.bound === '1') return
        field.dataset.bound = '1'
        const hours = field.querySelector('[data-duration-hours]')
        const minutes = field.querySelector('[data-duration-minutes]')
        const seconds = field.querySelector('[data-duration-seconds]')
        const milliseconds = field.querySelector('[data-duration-milliseconds]')
        const parts = [hours, minutes, seconds].filter(Boolean)
        const nextByInput = new Map([[hours, minutes], [minutes, seconds]])
        const previousByInput = new Map([[minutes, hours], [seconds, minutes], [milliseconds, seconds]])
        const pasteCompleteDuration = event => {
            const text = String(event.clipboardData?.getData('text') || '').trim()
            const match = text.match(/^(\d{1,2}):([0-5]?\d):([0-5]?\d)(?:[.,](\d{1,3}))?$/)
            if (!match) return
            event.preventDefault()
            if (hours) hours.value = String(Math.min(99, Number.parseInt(match[1], 10) || 0)).padStart(2, '0')
            if (minutes) minutes.value = String(Math.min(59, Number.parseInt(match[2], 10) || 0)).padStart(2, '0')
            if (seconds) seconds.value = String(Math.min(59, Number.parseInt(match[3], 10) || 0)).padStart(2, '0')
            if (milliseconds) milliseconds.value = match[4] ? match[4].padEnd(3, '0') : ''
            if (match[4]) setDurationPrecision(true)
            syncDuration(field)
        }
        parts.forEach(input => {
            input.maxLength = 2
            input.addEventListener('focus', () => input.select())
            input.addEventListener('paste', pasteCompleteDuration)
            input.addEventListener('input', () => {
                input.value = digitsOnly(input.value, 2)
                field.dispatchEvent(new CustomEvent('activity:valuechange', {bubbles: true}))
                if (input.value.length >= 2) {
                    const next = nextByInput.get(input)
                    if (next) { next.focus(); next.select?.() }
                    else if (durationPrecisionEnabled && milliseconds) { milliseconds.focus(); milliseconds.select?.() }
                    else input.blur()
                }
            })
            input.addEventListener('blur', () => syncDuration(field))
            input.addEventListener('change', () => syncDuration(field))
            input.addEventListener('keydown', event => {
                if (event.key === 'Backspace' && input.value === '') {
                    const previous = previousByInput.get(input)
                    if (previous) { event.preventDefault(); previous.focus(); previous.select?.() }
                    return
                }
                if (event.key !== 'Enter') return
                event.preventDefault(); syncDuration(field)
                const next = nextByInput.get(input) || (durationPrecisionEnabled ? milliseconds : null)
                next?.focus(); next?.select?.()
            })
        })
        if (milliseconds) {
            milliseconds.maxLength = 3
            milliseconds.addEventListener('focus', () => milliseconds.select())
            milliseconds.addEventListener('paste', pasteCompleteDuration)
            milliseconds.addEventListener('input', () => { milliseconds.value = digitsOnly(milliseconds.value, 3); field.dispatchEvent(new CustomEvent('activity:valuechange', {bubbles: true})) })
            milliseconds.addEventListener('keydown', event => {
                if (event.key === 'Backspace' && milliseconds.value === '') {
                    event.preventDefault(); seconds?.focus(); seconds?.select?.()
                }
            })
            milliseconds.addEventListener('blur', () => {
                if (milliseconds.value !== '') milliseconds.value = String(Math.min(999, Number.parseInt(milliseconds.value, 10) || 0)).padStart(3, '0')
                syncDuration(field)
            })
            milliseconds.addEventListener('change', () => syncDuration(field))
        }
        if (String(milliseconds?.value || '').trim() !== '' && Number.parseInt(milliseconds.value, 10) !== 0) durationPrecisionEnabled = true
        syncDuration(field)
        updateDurationPrecisionUi()
    }

    const numberStepperStep = input => {
        const raw = String(input.getAttribute('step') || '').trim().toLowerCase()
        const numeric = Number(raw)
        if (raw && raw !== 'any' && Number.isFinite(numeric) && numeric > 0) return numeric
        return input.inputMode === 'numeric' ? 1 : 0.1
    }
    const stepNumberInput = (input, direction) => {
        if (!(input instanceof HTMLInputElement) || input.disabled || input.readOnly) return
        const step = numberStepperStep(input)
        const min = input.min === '' ? -Infinity : Number(input.min)
        const max = input.max === '' ? Infinity : Number(input.max)
        const base = String(input.value || '').trim() === '' ? (Number.isFinite(min) ? min : 0) : Number(input.value)
        if (!Number.isFinite(base)) return
        const decimals = Math.min(8, Math.max(0, String(step).split('.')[1]?.length || 0))
        let next = base + step * direction
        if (Number.isFinite(min)) next = Math.max(min, next)
        if (Number.isFinite(max)) next = Math.min(max, next)
        input.value = decimals ? String(Number(next.toFixed(decimals))) : String(Math.round(next))
        input.dispatchEvent(new Event('input', {bubbles: true}))
        input.dispatchEvent(new Event('change', {bubbles: true}))
        input.focus({preventScroll: true})
    }
    const enhanceNumberInput = input => {
        if (!(input instanceof HTMLInputElement) || input.type !== 'number' || input.dataset.numberStepper === '1') return
        if (input.closest('[data-no-number-stepper]')) return
        input.dataset.numberStepper = '1'
        const wrap = document.createElement('div')
        wrap.className = 'stride-number-control'
        input.before(wrap)
        wrap.append(input)
        const stepper = document.createElement('span')
        stepper.className = 'stride-number-stepper'
        stepper.setAttribute('aria-hidden', 'false')
        const up = document.createElement('button')
        up.type = 'button'
        up.className = 'stride-number-stepper-button is-up'
        up.setAttribute('aria-label', tr('activity.number_increase'))
        up.innerHTML = '<span aria-hidden="true">⌃</span>'
        const down = document.createElement('button')
        down.type = 'button'
        down.className = 'stride-number-stepper-button is-down'
        down.setAttribute('aria-label', tr('activity.number_decrease'))
        down.innerHTML = '<span aria-hidden="true">⌄</span>'
        up.addEventListener('click', () => stepNumberInput(input, 1))
        down.addEventListener('click', () => stepNumberInput(input, -1))
        stepper.append(up, down)
        wrap.append(stepper)
        if (String(input.getAttribute('step') || '').toLowerCase() === 'any') {
            input.addEventListener('keydown', event => {
                if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') return
                event.preventDefault()
                stepNumberInput(input, event.key === 'ArrowUp' ? 1 : -1)
            })
        }
    }
    const bindStructuredFields = (scope = document) => {
        scope.querySelectorAll('[data-clock-field]').forEach(bindClockField)
        scope.querySelectorAll('[data-duration-field]').forEach(bindDurationField)
        scope.querySelectorAll('input[type="number"]').forEach(enhanceNumberInput)
        ensureDurationPrecisionToggle()
        updateDurationPrecisionUi()
    }

    bindStructuredFields()

    const keepMobileFocusAboveActions = () => {
        if (!form || (window.innerWidth > 720 && window.innerHeight > 520)) return
        const active = document.activeElement
        if (!(active instanceof HTMLElement) || !form.contains(active) || !active.matches('input, textarea, select, [contenteditable="true"]')) return
        const actions = form.querySelector('.activity-form-actions')
        if (!actions) return
        requestAnimationFrame(() => {
            const fieldBox = active.getBoundingClientRect()
            const actionsBox = actions.getBoundingClientRect()
            const overlap = fieldBox.bottom - actionsBox.top
            if (overlap > -8) form.scrollBy({top: overlap + 12, behavior: 'auto'})
        })
    }
    form?.addEventListener('focusin', keepMobileFocusAboveActions)
    window.addEventListener('resize', keepMobileFocusAboveActions)
    window.visualViewport?.addEventListener('resize', keepMobileFocusAboveActions)

    const durationSource = form?.querySelector('[data-duration-source]')
    if (durationSource) {
        form.addEventListener('input', (event) => {
            if (event.target.closest?.('[data-duration-field]')) durationSource.value = 'duration'
            if (event.target.matches?.('#data_fim')) durationSource.value = 'end'
        })
        form.addEventListener('change', (event) => {
            if (event.target.closest?.('[data-duration-field]')) durationSource.value = 'duration'
            if (event.target.matches?.('#data_fim')) durationSource.value = 'end'
        })
    }

    const endDate = form?.querySelector('[data-end-date]')
    const endTime = form?.querySelector('[data-end-time]')
    const endDateTime = form?.querySelector('[data-end-datetime]')
    const syncEndDateTime = () => {
        if (!endDateTime) return
        const date = String(endDate?.value || '').trim()
        const time = String(endTime?.value || '').trim()
        endDateTime.value = date && time ? `${date}T${time}` : ''
        if (durationSource) durationSource.value = 'end'
    }
    ;[endDate, endTime].forEach((input) => {
        input?.addEventListener('input', syncEndDateTime)
        input?.addEventListener('change', syncEndDateTime)
    })

    const populateWorkoutOptions = (items) => {
        if (!workoutSelect) return
        const selected = workoutSelect.dataset.selectedWorkout || workoutSelect.value || ''
        workoutSelect.replaceChildren(new Option(tr('activity.no_linked_workout'), ''))
        const groups = new Map()
        ;(Array.isArray(items) ? items : []).forEach((item) => {
            const schedule = String(item.schedule || tr('js.schedule_fallback'))
            let group = groups.get(schedule)
            if (!group) {
                group = document.createElement('optgroup')
                group.label = schedule
                groups.set(schedule, group)
                workoutSelect.appendChild(group)
            }
            const prefix = [item.code, item.focus].filter(Boolean).join(' · ')
            const option = new Option(`${prefix ? `${prefix} — ` : ''}${item.title || tr('activity.workout_fallback')}`, item.id || '')
            option.dataset.workoutTitle = item.title || ''
            option.dataset.workoutCode = item.code || ''
            option.dataset.workoutFocus = item.focus || ''
            if (String(item.id || '') === selected) option.selected = true
            group.appendChild(option)
        })
    }

    const populateEquipment = (items) => {
        if (!equipmentHost) return
        const selected = new Set(String(equipmentHost.dataset.selectedEquipment || '').split(',').filter(Boolean))
        const equipment = Array.isArray(items) ? items : []
        equipmentHost.replaceChildren()
        if (!equipment.length) {
            const link = document.createElement('a')
            link.href = '/user/equipamentos.php'
            link.className = 'activity-empty-action'
            link.textContent = tr('activity.add_first_equipment')
            equipmentHost.appendChild(link)
            return
        }
        const picker = document.createElement('div')
        picker.className = 'equipment-picker'
        equipment.forEach((item) => {
            const label = document.createElement('label')
            label.className = 'equipment-chip'
            const input = document.createElement('input')
            input.type = 'checkbox'
            input.name = 'equipamentos[]'
            input.value = String(item.id || '')
            input.checked = selected.has(input.value)
            const span = document.createElement('span')
            span.textContent = item.name || tr('activity.equipment_fallback')
            label.append(input, span)
            picker.appendChild(label)
        })
        equipmentHost.appendChild(picker)
        syncLogDetailButtons()
    }

    const loadEditorDetails = async (requestedModel = modelSelect?.value || '') => {
        const modelId = String(requestedModel || '').trim()
        if (!modelPanelsHost || !modelId) return false
        const existingPanel = modelPanelsHost.querySelector(`[data-model-panel="${CSS.escape(modelId)}"]`)
        if (existingPanel) {
            loadedEditorModels.add(modelId)
            modelPanelsHost.dataset.detailsLoaded = '1'
            return true
        }
        if (editorDetailsRequests.has(modelId)) return editorDetailsRequests.get(modelId)
        const submit = form?.querySelector('button[type="submit"]')
        const hasLoadedPanels = Boolean(modelPanelsHost.querySelector('[data-model-panel]'))
        modelPanelsHost.querySelectorAll('[data-activity-editor-loading], [data-editor-load-error]').forEach(node => node.remove())
        const loading = document.createElement('div')
        loading.className = 'activity-editor-inline-loading'
        loading.dataset.activityEditorLoading = modelId
        loading.textContent = tr('activity.loading_editor_fields')
        modelPanelsHost.appendChild(loading)
        modelPanelsHost.classList.add('is-loading')
        submit?.setAttribute('disabled', 'disabled')
        const supportFlag = editorSupportLoaded ? '0' : '1'
        const request = fetchWithRetry(`/api/atividade-editor-detalhes.php?model=${encodeURIComponent(modelId)}&support=${supportFlag}`, {
            headers: {'Accept': 'application/json'},
            credentials: 'same-origin'
        }, {timeout: 10000, retries: 1}).then(async (response) => {
            const data = await response.json().catch(() => null)
            if (!response.ok || !data?.ok) throw new Error(data?.error || tr('activity.editor_load_error'))
            loading.remove()
            modelPanelsHost.insertAdjacentHTML('beforeend', data.models_html || '')
            const panel = modelPanelsHost.querySelector(`[data-model-panel="${CSS.escape(modelId)}"]`)
            if (!panel) throw new Error(tr('activity.format_load_error'))
            loadedEditorModels.add(modelId)
            modelPanelsHost.dataset.detailsLoaded = '1'
            bindStructuredFields(panel)
            bindUnitRouteEditors(panel)
            initializeSegmentPanels(panel)
            if (data.support_loaded) {
                populateWorkoutOptions(data.workouts)
                populateEquipment(data.equipment)
                setStrengthLibrary(data.exercises || [])
                editorSupportLoaded = true
            }
            updateModelPanels()
            syncWorkoutMetadata()
            syncAutomaticActivityTitle()
            return true
        }).catch((error) => {
            loading.remove()
            const errorBox = document.createElement('div')
            errorBox.className = 'activity-editor-load-error'
            errorBox.dataset.editorLoadError = modelId
            const message = document.createElement('span')
            message.textContent = String(error?.message || tr('activity.editor_load_error'))
            const retry = document.createElement('button')
            retry.type = 'button'
            retry.dataset.retryActivityEditor = ''
            retry.textContent = 'Tentar novamente'
            errorBox.append(message, retry)
            if (!hasLoadedPanels && !modelPanelsHost.querySelector('[data-model-panel]')) modelPanelsHost.replaceChildren(errorBox)
            else modelPanelsHost.appendChild(errorBox)
            return false
        }).finally(() => {
            editorDetailsRequests.delete(modelId)
            if (editorDetailsRequests.size === 0) {
                modelPanelsHost.classList.remove('is-loading')
                submit?.removeAttribute('disabled')
            }
        })
        editorDetailsRequests.set(modelId, request)
        return request
    }

    if (shell && shell.parentElement !== document.body) document.body.appendChild(shell)
    const openEditor = () => {
        shell?.classList.add('is-open')
        document.documentElement.classList.add('activity-editor-open')
        loadEditorDetails(modelSelect?.value || '').then(() => requestAnimationFrame(() => form?.querySelector('input,button,select,textarea')?.focus()))
    }
    const closeEditor = () => {
        shell?.classList.remove('is-open')
        document.documentElement.classList.remove('activity-editor-open')
    }
    openButtons.forEach((button) => {
        button.addEventListener('click', openEditor)
        const prewarm = () => loadEditorDetails(modelSelect?.value || '')
        button.addEventListener('pointerenter', prewarm, {once: true})
        button.addEventListener('focus', prewarm, {once: true})
        button.addEventListener('touchstart', prewarm, {once: true, passive: true})
    })
    closeButtons.forEach((button) => button.addEventListener('click', closeEditor))
    if (modelPanelsHost && modelSelect?.value && !shell?.classList.contains('is-open') && !navigator.connection?.saveData) {
        const warmEditor = () => loadEditorDetails(modelSelect.value)
        if ('requestIdleCallback' in window) window.requestIdleCallback(warmEditor, {timeout: 1600})
        else window.setTimeout(warmEditor, 900)
    }
    if (shell?.classList.contains('is-open')) document.documentElement.classList.add('activity-editor-open')
    const activityPageUrl = new URL(window.location.href)
    if (shell?.classList.contains('is-open') && (activityPageUrl.searchParams.has('new') || activityPageUrl.searchParams.has('registrar'))) {
        activityPageUrl.searchParams.delete('new')
        activityPageUrl.searchParams.delete('registrar')
        history.replaceState({}, '', `${activityPageUrl.pathname}${activityPageUrl.search}${activityPageUrl.hash}`)
    }
    if (shell?.classList.contains('is-open')) loadEditorDetails(modelSelect?.value || '')
    document.addEventListener('click', event => {
        const retry = event.target.closest('[data-activity-edit-retry]')
        if (retry) { event.preventDefault(); if (activityEditUrl) openActivityEdit(activityEditUrl); return }
        const newPage = event.target.closest('[data-activity-edit-new-page]')
        if (newPage && activityEditUrl) newPage.href = activityEditUrl.replace(/([?&])embed=1(&|$)/, '$1').replace(/[?&]$/, '')
    })
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape' || !shell?.classList.contains('is-open')) return
        if (form?.classList.contains('is-route-subview')) {
            routeEditor?.querySelector('[data-route-subview-back]')?.click()
            return
        }
        closeEditor()
    })

    const sportCombobox = document.querySelector('[data-sport-combobox]')
    const sportTrigger = sportCombobox?.querySelector('[data-sport-trigger]')
    const sportCurrent = sportCombobox?.querySelector('[data-sport-current]')
    const sportPopover = sportCombobox?.querySelector('[data-sport-popover]')
    const sportSearch = sportCombobox?.querySelector('[data-sport-search]')
    const sportFamilyBrowser = sportCombobox?.querySelector('[data-sport-family-browser]')
    const sportFamilyGrid = sportCombobox?.querySelector('[data-sport-family-grid]')
    let activeSportFamily = ''

    const closeSportPopover = () => {
        if (!sportPopover || !sportTrigger) return
        sportPopover.hidden = true
        sportTrigger.setAttribute('aria-expanded', 'false')
    }

    const updateSportCurrent = () => {
        const option = sportCombobox?.querySelector(`[data-sport-option][data-sport-id="${CSS.escape(modalitySelect?.value || '')}"]`)
        if (!option || !sportCurrent) return
        const icon = option.querySelector('.sport-option-icon .sport-icon')?.cloneNode(true)
        const name = option.dataset.sportName || option.textContent.trim()
        sportCurrent.replaceChildren()
        if (icon) sportCurrent.append(icon)
        sportCurrent.append(document.createTextNode(name))
        sportCombobox.querySelectorAll('[data-sport-option]').forEach((item) => item.setAttribute('aria-selected', item.dataset.sportId === modalitySelect.value ? 'true' : 'false'))
    }

    const renderSportFamily = () => {
        if (!sportFamilyBrowser) return
        const query = String(sportSearch?.value || '').trim().toLocaleLowerCase('pt-BR')
        const searching = query !== ''
        sportFamilyBrowser.classList.toggle('is-searching', searching)
        if (sportFamilyGrid) sportFamilyGrid.hidden = searching || activeSportFamily !== ''
        sportFamilyBrowser.querySelectorAll('[data-sport-family-panel]').forEach((panel) => {
            const key = panel.dataset.sportFamilyPanel || ''
            if (searching) {
                panel.hidden = !panel.querySelector('[data-sport-row]:not([hidden])')
                const moreList = panel.querySelector('[data-sport-family-more-list]')
                if (moreList) moreList.hidden = false
            } else {
                panel.hidden = key !== activeSportFamily
                const moreList = panel.querySelector('[data-sport-family-more-list]')
                const moreToggle = panel.querySelector('[data-sport-family-more]')
                if (moreList) moreList.hidden = true
                if (moreToggle) moreToggle.setAttribute('aria-expanded', 'false')
            }
        })
    }

    const filterSports = () => {
        const query = String(sportSearch?.value || '').trim().toLocaleLowerCase('pt-BR')
        sportCombobox?.querySelectorAll('[data-sport-row]').forEach((row) => {
            row.hidden = query !== '' && !String(row.dataset.searchText || '').includes(query)
        })
        sportCombobox?.querySelectorAll('[data-sport-quick]').forEach((group) => {
            group.hidden = query !== ''
        })
        renderSportFamily()
    }

    sportTrigger?.addEventListener('click', () => {
        if (!sportPopover) return
        const opening = sportPopover.hidden
        sportPopover.hidden = !opening
        sportTrigger.setAttribute('aria-expanded', opening ? 'true' : 'false')
        if (opening) {
            activeSportFamily = ''
            filterSports()
            sportSearch?.focus()
            sportSearch?.select()
        }
    })
    sportSearch?.addEventListener('input', filterSports)
    sportSearch?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return
        event.preventDefault()
        sportCombobox?.querySelector('[data-sport-row]:not([hidden]) [data-sport-option]')?.click()
    })
    document.addEventListener('click', (event) => {
        if (sportCombobox && !sportCombobox.contains(event.target)) closeSportPopover()
    })

    sportCombobox?.addEventListener('click', async (event) => {
        const familyOpen = event.target.closest('[data-sport-family-open]')
        if (familyOpen) {
            activeSportFamily = familyOpen.dataset.sportFamilyOpen || ''
            renderSportFamily()
            return
        }
        if (event.target.closest('[data-sport-family-back]')) {
            activeSportFamily = ''
            renderSportFamily()
            return
        }
        const familyMore = event.target.closest('[data-sport-family-more]')
        if (familyMore) {
            const panel = familyMore.closest('[data-sport-family-panel]')
            const list = panel?.querySelector('[data-sport-family-more-list]')
            if (list) {
                const opening = list.hidden
                list.hidden = !opening
                familyMore.setAttribute('aria-expanded', opening ? 'true' : 'false')
            }
            return
        }
        const option = event.target.closest('[data-sport-option]')
        if (option && modalitySelect) {
            modalitySelect.value = option.dataset.sportId || ''
            modalitySelect.dispatchEvent(new Event('change', {bubbles: true}))
            updateSportCurrent()
            closeSportPopover()
            return
        }

        const favoriteButton = event.target.closest('[data-toggle-sport-favorite]')
        if (!favoriteButton || !page) return
        event.preventDefault()
        event.stopPropagation()
        const id = favoriteButton.dataset.sportId || ''
        if (!id) return
        const nextFavorite = !favoriteButton.classList.contains('is-favorite')
        const favoriteButtons = [...sportCombobox.querySelectorAll(`[data-toggle-sport-favorite][data-sport-id="${CSS.escape(id)}"]`)]
        const sportOptions = [...sportCombobox.querySelectorAll(`[data-sport-option][data-sport-id="${CSS.escape(id)}"]`)]
        const applyFavoriteState = (favorite) => {
            favoriteButtons.forEach((button) => {
                button.classList.toggle('is-favorite', favorite)
                button.setAttribute('aria-pressed', favorite ? 'true' : 'false')
            })
            sportOptions.forEach((item) => item.dataset.sportFavorite = favorite ? '1' : '0')
        }
        applyFavoriteState(nextFavorite)
        favoriteButtons.forEach((button) => { button.disabled = true })
        try {
            const body = new URLSearchParams({
                csrf_token: page.dataset.csrfToken || '',
                idmodalidade: id,
                favorita: nextFavorite ? '1' : '0',
            })
            const response = await fetch('/api/modalidade-favorita.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'Accept': 'application/json'},
                body,
            })
            if (!response.ok) throw new Error('favorite')
        } catch (_) {
            applyFavoriteState(!nextFavorite)
            favoriteButtons.forEach((button) => button.classList.add('has-error'))
            window.setTimeout(() => favoriteButtons.forEach((button) => button.classList.remove('has-error')), 900)
            showActivityToast(tr('sport_picker.favorite_error', {}, 'Não foi possível atualizar o favorito.'), 'error')
        } finally {
            favoriteButtons.forEach((button) => { button.disabled = false })
        }
    })

    const setPanelState = (panel, active) => {
        panel.hidden = !active
        panel.querySelectorAll('input, select, textarea').forEach((element) => {
            if (element.matches('[data-derived-input]')) return
            element.disabled = !active
        })
    }

    const optionalFieldForChip = (panel, chip) => {
        if (!panel || !chip) return null
        const slug = chip.dataset.showOptionalField || ''
        const bar = chip.closest('[data-optional-fields-bar]')
        const scope = bar?.dataset.optionalScope || 'panel'
        const candidates = Array.from(panel.querySelectorAll(`[data-dynamic-field][data-field-slug="${CSS.escape(slug)}"]`))
        if (scope === 'unit') {
            const root = unitContextRoot(chip)
            return candidates.find(candidate => unitContextRoot(candidate) === root) || null
        }
        return candidates.find(candidate => candidate.closest('[data-unit-index]') === null) || null
    }

    const updateOptionalChips = (panel) => {
        if (!panel) return
        const segmented = isSegmentMode(panel)
        panel.querySelectorAll('[data-optional-fields-bar]').forEach((bar) => {
            const unitScope = bar.dataset.optionalScope === 'unit'
            let visible = 0
            bar.querySelectorAll('[data-show-optional-field]').forEach((chip) => {
                const field = optionalFieldForChip(panel, chip)
                chip.hidden = (unitScope ? !segmented : segmented) || !field || !field.hidden
                if (!chip.hidden) visible += 1
            })
            bar.hidden = (unitScope ? !segmented : segmented) || visible === 0
        })
    }

    const updateModelPanels = () => {
        if (!modelSelect) return
        document.querySelectorAll('[data-model-panel]').forEach((panel) => setPanelState(panel, panel.dataset.modelPanel === modelSelect.value))
        const panel = document.querySelector(`[data-model-panel="${CSS.escape(modelSelect.value)}"]`)
        syncSegmentPanel(panel)
        updateDerivedMetric(panel)
        updateOptionalChips(panel)
        updateSummary(panel)
    }

    const updateWorkoutField = () => {
        if (!modalitySelect || !workoutField || !workoutSelect) return
        const slug = modalitySelect.selectedOptions[0]?.dataset.slug || ''
        const visible = ['musculacao', 'calistenia', 'crossfit'].includes(slug)
        workoutField.hidden = !visible
        workoutSelect.disabled = !visible
        if (!visible) workoutSelect.value = ''
    }

    const automaticActivityTitle = () => {
        if (!activityTitleInput || !modalitySelect) return ''
        const slug = String(modalitySelect.selectedOptions[0]?.dataset.slug || '')
        const persistedSportName = String(modalitySelect.selectedOptions[0]?.textContent || '').trim()
        const sport = sportDisplay(slug, persistedSportName) || tr('nav.physical_activity')
        const panel = modelSelect?.value ? form?.querySelector(`[data-model-panel="${CSS.escape(modelSelect.value)}"]`) : null
        const focusField = panel?.querySelector('[data-dynamic-field][data-field-slug="foco_muscular"] input, [data-dynamic-field][data-field-slug="foco_muscular"] select, [data-dynamic-field][data-field-slug="foco_muscular"] textarea')
        const focus = String(focusField?.value || '').trim()
        if (['musculacao', 'calistenia', 'crossfit'].includes(slug)) {
            if (focus) return tr('activity.auto_title.focus', {focus}, `Treino de ${focus}`)
            return tr(`activity.auto_title.${slug}.generic`, {}, sport)
        }
        const visibleHour = String(activityClockField?.querySelector('[data-clock-hours]')?.value || '').trim()
        const visibleMinute = String(activityClockField?.querySelector('[data-clock-minutes]')?.value || '').trim()
        const hiddenTime = String(activityClockField?.querySelector('[data-clock-value]')?.value || '').trim()
        const [hiddenHour, hiddenMinute] = hiddenTime.split(':')
        const hour = Number.parseInt(visibleHour || hiddenHour || '0', 10)
        const minute = Number.parseInt(visibleMinute || hiddenMinute || '0', 10)
        if (!Number.isFinite(hour) || !Number.isFinite(minute)) return sport
        const clock = hour * 60 + minute
        const period = clock < 5 * 60 ? 'late_night' : (clock < 12 * 60 ? 'morning' : (clock < 18 * 60 ? 'afternoon' : 'evening'))
        const specificKey = `activity.auto_title.${slug.replaceAll('-', '_')}.${period}`
        if (Object.prototype.hasOwnProperty.call(i18n.dictionary || {}, specificKey)) return tr(specificKey)
        return tr(`activity.auto_title.generic.${period}`, {sport}, `${sport}`)
    }

    const syncActivityTitlePresentation = () => {
        if (!activityTitlePreview) return
        const value = String(activityTitleInput?.value || '').trim() || automaticActivityTitle() || tr('nav.physical_activity')
        activityTitlePreview.textContent = value
    }

    const syncAutomaticActivityTitle = ({force = false} = {}) => {
        if (!activityTitleInput) return
        const next = automaticActivityTitle()
        const current = String(activityTitleInput.value || '').trim()
        const automatic = activityTitleInput.dataset.automaticTitle === '1'
        if (!force && current && !automatic) {
            syncActivityTitlePresentation()
            return
        }
        writingAutomaticActivityTitle = true
        activityTitleInput.value = next
        activityTitleInput.dataset.automaticTitle = '1'
        activityTitleInput.dataset.automaticValue = next
        writingAutomaticActivityTitle = false
        syncActivityTitlePresentation()
    }

    if (activityTitleInput) {
        if (!String(activityTitleInput.value || '').trim()) activityTitleInput.dataset.automaticTitle = '1'
        activityTitleInput.addEventListener('input', () => {
            if (writingAutomaticActivityTitle) return
            const value = String(activityTitleInput.value || '').trim()
            activityTitleInput.dataset.automaticTitle = value === '' ? '1' : '0'
            syncActivityTitlePresentation()
            if (value === '') syncAutomaticActivityTitle({force: true})
        })
    }
    activityTitleEditButton?.addEventListener('click', () => {
        if (!activityTitleEditor) return
        const opening = activityTitleEditor.hidden
        activityTitleEditor.hidden = !opening
        activityTitleEditButton.setAttribute('aria-expanded', opening ? 'true' : 'false')
        if (opening) requestAnimationFrame(() => activityTitleInput?.focus())
    })
    activityDateInput?.addEventListener('change', () => syncAutomaticActivityTitle())
    activityClockField?.addEventListener('change', () => syncAutomaticActivityTitle())
    activityClockField?.addEventListener('input', () => syncAutomaticActivityTitle())
    activityClockField?.addEventListener('activity:valuechange', () => syncAutomaticActivityTitle())

    const syncWorkoutMetadata = ({force = false} = {}) => {
        if (!form || !workoutSelect?.value) return
        const option = workoutSelect.selectedOptions[0]
        const panel = modelSelect?.value ? form.querySelector(`[data-model-panel="${CSS.escape(modelSelect.value)}"]`) : null
        const setField = (slug, value) => {
            if (!panel || !value) return
            const field = panel.querySelector(`[data-dynamic-field][data-field-slug="${CSS.escape(slug)}"]`)
            const input = field?.querySelector('input, textarea, select')
            if (!input || (!force && String(input.value || '').trim() !== '')) return
            input.value = value
            input.dispatchEvent(new Event('input', {bubbles: true}))
        }
        const titleInput = form.querySelector('input[name="titulo"]')
        if (titleInput && option?.dataset.workoutTitle && (titleInput.value.trim() === '' || titleInput.dataset.automaticTitle === '1')) {
            titleInput.value = option.dataset.workoutTitle
            titleInput.dataset.automaticTitle = '0'
        }
        setField('codigo_treino', option?.dataset.workoutCode || '')
        setField('foco_muscular', option?.dataset.workoutFocus || '')
    }

    workoutSelect?.addEventListener('change', () => syncWorkoutMetadata({force: true}))

    const updateModelsForModality = ({semanticChange = false} = {}) => {
        if (!modalitySelect || !modelSelect) return
        const modality = modalitySelect.value
        const options = Array.from(modelSelect.options)
        const matches = options.filter((option) => option.dataset.modalidade === modality)
        options.forEach((option) => {
            const visible = option.dataset.modalidade === modality
            option.hidden = !visible
            option.disabled = !visible
        })
        if (!modelSelect.selectedOptions[0] || modelSelect.selectedOptions[0].disabled) {
            if (matches[0]) modelSelect.value = matches[0].value
        }
        if (modelField) modelField.hidden = matches.length <= 1
        updateWorkoutField()
        updateModelPanels()
        syncWorkoutMetadata()
        syncAutomaticActivityTitle()
        updateSportCurrent()
        updateRouteAvailability()
        syncStrengthVisibility()
        syncActiveSportContext({semanticChange, allowNominal:true})
        if (shell?.classList.contains('is-open')) loadEditorDetails(modelSelect.value)
    }

    modalitySelect?.addEventListener('change', () => updateModelsForModality({semanticChange:true}))
    modelSelect?.addEventListener('change', () => { if (shell?.classList.contains('is-open')) loadEditorDetails(modelSelect.value); updateModelPanels(); syncWorkoutMetadata(); syncAutomaticActivityTitle(); syncActiveSportContext({allowNominal:true}) })
    form?.addEventListener('input', (event) => {
        if (event.target.closest('[data-dynamic-field][data-field-slug="foco_muscular"]')) syncAutomaticActivityTitle()
    })
    syncAutomaticActivityTitle()
    syncStrengthVisibility()

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-retry-activity-editor]')) {
            loadEditorDetails(modelSelect?.value || '')
            return
        }
        const detailToggle = event.target.closest('[data-toggle-log-detail]')
        if (detailToggle) {
            const key = detailToggle.dataset.toggleLogDetail || ''
            const detail = form?.querySelector(`[data-log-detail="${CSS.escape(key)}"]`)
            setLogDetailOpen(key, Boolean(detail?.hidden))
            return
        }
        const detailClose = event.target.closest('[data-close-log-detail]')
        if (detailClose) {
            setLogDetailOpen(detailClose.dataset.closeLogDetail || '', false)
            return
        }
        const derivedEdit = event.target.closest('[data-edit-derived]')
        if (derivedEdit) {
            const field = derivedEdit.closest('[data-derived-field]')
            const input = field?.querySelector('[data-derived-input]')
            if (!input) return
            if (input.readOnly) {
                input.readOnly = false
                input.setAttribute('aria-readonly', 'false')
                derivedEdit.textContent = tr('common.done')
                input.focus()
                input.select()
            } else {
                calculateFromDerived(field.closest('[data-model-panel]'))
                input.readOnly = true
                input.setAttribute('aria-readonly', 'true')
                derivedEdit.textContent = tr('common.edit')
            }
            return
        }
        const show = event.target.closest('[data-show-optional-field]')
        if (show) {
            const panel = show.closest('[data-model-panel]')
            const field = optionalFieldForChip(panel, show)
            if (field) {
                field.hidden = false
                field.classList.remove('is-optional-hidden')
                field.querySelectorAll('input, select, textarea').forEach(input => { input.disabled = false })
                field.querySelector('input, select, textarea')?.focus()
                updateOptionalChips(panel)
            }
            return
        }

        const hide = event.target.closest('[data-hide-optional-field]')
        if (hide) {
            const field = hide.closest('[data-dynamic-field]')
            const panel = hide.closest('[data-model-panel]')
            if (field) {
                field.querySelectorAll('input, select, textarea').forEach((input) => {
                    if (input.matches('[data-duration-hours], [data-duration-minutes], [data-duration-seconds], [data-duration-value]')) input.value = ''
                    else if (input.type === 'checkbox' || input.type === 'radio') input.checked = false
                    else input.value = ''
                })
                field.hidden = true
                field.classList.add('is-optional-hidden')
                updateOptionalChips(panel)
                updateDerivedMetric(panel)
                updateSummary(panel)
            }
        }
    })

    const nextUnitIndex = (container) => {
        const indexes = Array.from(container.querySelectorAll('[data-unit-index]')).map((unit) => Number(unit.dataset.unitIndex)).filter(Number.isFinite)
        return indexes.length ? Math.max(...indexes) + 1 : 1
    }

    document.addEventListener('click', (event) => {
        const enable = event.target.closest('[data-enable-segments]')
        if (enable) {
            const panel = enable.closest('[data-model-panel]')
            setSegmentMode(panel, true, {moveRoute: true})
            panel?.querySelector('[data-primary-unit-context] input, [data-primary-unit-context] select')?.focus()
            return
        }
        const close = event.target.closest('[data-close-segments]')
        if (close) {
            const panel = close.closest('[data-model-panel]')
            const extra = panel?.querySelectorAll('[data-unit-index]')?.length || 0
            if (extra > 0) {
                const summary = document.querySelector('[data-summary-text]')
                if (summary) {
                    summary.textContent = panel?.dataset.unitKind === 'tentativa' ? tr('activity.remove_other_attempts') : tr('activity.remove_other_segments')
                    const summaryWrap = summary.closest('[data-activity-summary]')
                    if (summaryWrap) summaryWrap.hidden = false
                }
                return
            }
            if (!canCloseSegmentMode(panel)) return
            setSegmentMode(panel, false, {moveRoute: true})
            return
        }
        const button = event.target.closest('[data-add-unit]')
        if (!button) return
        const panel = button.closest('[data-model-panel]')
        if (panel && !isSegmentMode(panel)) setSegmentMode(panel, true, {moveRoute: true})
        const key = button.dataset.addUnit || ''
        const template = document.querySelector(`template[data-unit-template="${CSS.escape(key)}"]`)
        const container = document.querySelector(`[data-units][data-model="${CSS.escape(key)}"]`)
        if (!template || !container) return
        const previousRoot = container.lastElementChild || panel?.querySelector('[data-primary-unit-card]') || null
        const inheritedUnit = previousRoot ? segmentDistanceUnit(previousRoot) : ''
        const inheritedMeters = previousRoot ? segmentMeters(previousRoot) : null
        const index = nextUnitIndex(container)
        const number = index + 1
        const html = template.innerHTML.replaceAll('__INDEX__', String(index)).replaceAll('__NUMBER__', String(number))
        container.insertAdjacentHTML('beforeend', html)
        const unit = container.lastElementChild
        const sportSelect = unit?.querySelector('[data-unit-sport-select]')
        const primarySport = panel?.querySelector('[data-primary-unit-context] [data-unit-sport-select]')?.value || panel?.dataset.mainModality || ''
        if (sportSelect && primarySport && Array.from(sportSelect.options).some(option => option.value === primarySport)) sportSelect.value = primarySport
        window.StrideBRSportPickerInit?.(unit || container)
        bindStructuredFields(unit || container)
        bindUnitRouteEditors(unit || container)
        if (isSegmentMode(panel)) syncSegmentDistanceContext(unit, panel, {allowNominal:true, inheritedUnit, inheritedMeters})
        syncUnitSportState(unit)
        updateUnitDerivedMetric(unit)
        renumberSegmentTitles(panel)
        updateOptionalChips(panel)
        updateSummary(panel)
        unit?.scrollIntoView({behavior: 'smooth', block: 'nearest'})
    })

    document.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-remove-unit]')
        if (!remove) return
        const panel = remove.closest('[data-model-panel]')
        remove.closest('[data-unit-index]')?.remove()
        renumberSegmentTitles(panel)
        updateSummary(panel)
    })

    form?.addEventListener('stridebr:draft-before-restore', (event) => {
        const values = event.detail?.values || {}
        const maxByModel = new Map()
        Object.keys(values).forEach((name) => {
            const match = name.match(/^models\[([^\]]+)\]\[unidades\]\[(\d+)\]/)
            if (!match) return
            const index = Number(match[2])
            if (index < 1) return
            maxByModel.set(match[1], Math.max(maxByModel.get(match[1]) || 0, index))
        })
        maxByModel.forEach((maxIndex, model) => {
            const button = document.querySelector(`[data-add-unit="${CSS.escape(model)}"]`)
            const container = document.querySelector(`[data-units][data-model="${CSS.escape(model)}"]`)
            while (button && container && nextUnitIndex(container) <= maxIndex) button.click()
        })
    })

    const findPrimaryField = (panel, slug) => Array.from(panel?.querySelectorAll(`[data-dynamic-field][data-field-slug="${CSS.escape(slug)}"]`) || []).find((field) => field.closest('[data-unit-index]') === null)
    const sportContextLocale = () => String(i18n.locale || document.documentElement.lang || '').toLowerCase().startsWith('en') ? 'en' : 'pt-BR'
    const mainSportContext = (panel, extra = {}) => {
        const option = modalitySelect?.selectedOptions?.[0]
        return sportContextEngine?.context?.({
            slug: panel?.dataset.sportSlug || option?.dataset.slug || '',
            family: panel?.dataset.sportFamily || option?.dataset.family || '',
            ...extra,
        }) || {slug:'', family:'', is_track:false, is_sprint:false, nominal_distance_m:null, prefers_milliseconds:false, performance_priority:'default'}
    }
    const unitSportContextData = (context, panel, extra = {}) => {
        const root = unitContextRoot(context)
        const option = unitSportSelect(root)?.selectedOptions?.[0]
        return sportContextEngine?.context?.({
            slug: option?.dataset.slug || panel?.dataset.sportSlug || modalitySelect?.selectedOptions?.[0]?.dataset.slug || '',
            family: option?.dataset.family || panel?.dataset.sportFamily || modalitySelect?.selectedOptions?.[0]?.dataset.family || '',
            segment: isSegmentMode(panel),
            ...extra,
        }) || mainSportContext(panel, {segment:isSegmentMode(panel), ...extra})
    }
    const distanceFieldInput = field => field?.querySelector('input[type="number"], input[type="text"]') || null
    const distanceDisplayUnit = field => {
        const select = field?.querySelector('[data-distance-unit-select]')
        const value = select?.value || field?.dataset.distanceDisplayUnit || field?.dataset.distanceCanonicalUnit || field?.querySelector('[data-distance-unit-label]')?.textContent?.trim() || field?.querySelector('.field-unit')?.textContent?.trim() || 'km'
        return value === 'm' ? 'm' : 'km'
    }
    const distanceCanonicalUnit = field => field?.dataset.distanceCanonicalUnit === 'm' ? 'm' : 'km'
    const distanceMetersFromField = field => {
        const input = distanceFieldInput(field)
        if (!input || !sportContextEngine) return null
        return sportContextEngine.metersFrom(input.value, distanceDisplayUnit(field))
    }
    const writeDistanceFieldValue = (field, meters, unit, {automatic = true} = {}) => {
        const input = distanceFieldInput(field)
        const select = field?.querySelector('[data-distance-unit-select]')
        if (!input || !sportContextEngine || !Number.isFinite(Number(meters))) return
        const value = unit === 'm' ? Number(meters) : Number(meters) / 1000
        if (automatic) field.dataset.distanceWritingAutomatic = '1'
        input.value = sportContextEngine.inputNumber(value, 3)
        if (select) select.value = unit
        field.dataset.distanceDisplayUnit = unit
        const label = field.querySelector('[data-distance-unit-label]')
        if (label) label.textContent = unit
        if (automatic) delete field.dataset.distanceWritingAutomatic
    }
    const syncDistanceFieldContext = (field, context, {allowNominal = false} = {}) => {
        if (!field || !sportContextEngine) return
        const input = distanceFieldInput(field)
        if (!input) return
        let meters = distanceMetersFromField(field)
        const hasDistance = Number.isFinite(meters) && meters >= 0 && String(input.value || '').trim() !== ''
        if (!hasDistance && allowNominal && field.dataset.distanceManual !== '1' && Number.isFinite(Number(context?.nominal_distance_m))) {
            meters = Number(context.nominal_distance_m)
        }
        if (!Number.isFinite(meters)) return
        const manualUnit = field.dataset.distanceUnitManual === '1' ? distanceDisplayUnit(field) : ''
        const target = sportContextEngine.chooseDistanceUnit(meters, context, manualUnit)
        writeDistanceFieldValue(field, meters, target)
        if (!hasDistance && allowNominal && Number.isFinite(Number(context?.nominal_distance_m))) field.dataset.distanceAutomaticNominal = '1'
    }
    const segmentDistanceField = root => unitContextRoot(root)?.querySelector('[data-segment-distance-field]') || null
    const segmentDistanceUnit = root => {
        const field = segmentDistanceField(root)
        const select = field?.querySelector('[data-segment-distance-unit-select]')
        const hidden = field?.querySelector('[data-segment-distance-unit-value]')
        return (select?.value || hidden?.value || 'km') === 'm' ? 'm' : 'km'
    }
    const segmentMeters = root => {
        const field = segmentDistanceField(root)
        const input = field?.querySelector('[data-segment-distance]')
        if (!field || !input || !sportContextEngine) return null
        return sportContextEngine.metersFrom(input.value, segmentDistanceUnit(root))
    }
    const writeSegmentDistance = (root, meters, unit, {automatic = true} = {}) => {
        const field = segmentDistanceField(root)
        const input = field?.querySelector('[data-segment-distance]')
        const select = field?.querySelector('[data-segment-distance-unit-select]')
        const hidden = field?.querySelector('[data-segment-distance-unit-value]')
        if (!field || !input || !sportContextEngine || !Number.isFinite(Number(meters))) return
        if (automatic) field.dataset.distanceWritingAutomatic = '1'
        input.value = sportContextEngine.inputNumber(unit === 'm' ? Number(meters) : Number(meters) / 1000, 3)
        if (select) select.value = unit
        if (hidden) hidden.value = unit
        field.dataset.distanceDisplayUnit = unit
        const label = field.querySelector('[data-segment-distance-unit]')
        if (label) label.textContent = unit
        if (automatic) delete field.dataset.distanceWritingAutomatic
    }
    const segmentSeriesContext = (panel, omitRoot = null) => {
        if (!sportContextEngine || !panel || !isSegmentMode(panel)) return null
        const roots = [panel.querySelector('[data-primary-unit-card]'), ...panel.querySelectorAll('[data-unit-index]')].filter(root => root && root !== omitRoot)
        const values = roots.map(root => segmentMeters(root)).filter(value => Number.isFinite(value) && value > 0)
        const base = mainSportContext(panel, {segment:true})
        return sportContextEngine.equivalentSeries(values, base)
    }
    const syncSegmentDistanceContext = (root, panel, {allowNominal = false, inheritedUnit = '', inheritedMeters = null} = {}) => {
        const field = segmentDistanceField(root)
        if (!field || !sportContextEngine) return
        const input = field.querySelector('[data-segment-distance]')
        let meters = segmentMeters(root)
        let hasDistance = Number.isFinite(meters) && String(input?.value || '').trim() !== ''
        const series = segmentSeriesContext(panel, root)
        const baseContext = unitSportContextData(root, panel, {
            registered_m: hasDistance ? meters : null,
            segment: true,
            structured_series: Boolean(series),
            series_unit: series?.unit || inheritedUnit || '',
        })
        if (!hasDistance && allowNominal && field.dataset.distanceManual !== '1' && Number.isFinite(Number(baseContext.nominal_distance_m))) {
            meters = Number(baseContext.nominal_distance_m)
            hasDistance = true
            field.dataset.distanceAutomaticNominal = '1'
        } else if (!hasDistance && field.dataset.distanceManual !== '1' && Number.isFinite(Number(series?.distance_m))) {
            meters = Number(series.distance_m)
            hasDistance = true
            field.dataset.distanceAutomaticSeries = '1'
        } else if (!hasDistance && field.dataset.distanceManual !== '1' && Number.isFinite(Number(inheritedMeters)) && inheritedMeters > 0 && series) {
            meters = Number(inheritedMeters)
            hasDistance = true
        }
        const manualUnit = field.dataset.distanceUnitManual === '1' ? segmentDistanceUnit(root) : ''
        let target = sportContextEngine.chooseDistanceUnit(Number.isFinite(meters) ? meters : 0, baseContext, manualUnit)
        if (!manualUnit && !hasDistance && inheritedUnit && !baseContext.is_track) target = inheritedUnit
        if (Number.isFinite(meters)) writeSegmentDistance(root, meters, target)
        else {
            const select = field.querySelector('[data-segment-distance-unit-select]')
            const hidden = field.querySelector('[data-segment-distance-unit-value]')
            if (select) select.value = target
            if (hidden) hidden.value = target
            const label = field.querySelector('[data-segment-distance-unit]')
            if (label) label.textContent = target
        }
    }
    const activeContextsPreferMilliseconds = panel => {
        if (!panel || !sportContextEngine) return false
        if (mainSportContext(panel).prefers_milliseconds) return true
        if (!isSegmentMode(panel)) return false
        return [panel.querySelector('[data-primary-unit-card]'), ...panel.querySelectorAll('[data-unit-index]')].filter(Boolean).some(root => {
            const meters = segmentMeters(root)
            return unitSportContextData(root, panel, {registered_m: meters, segment:true}).prefers_milliseconds
        })
    }
    const syncDurationPrecisionFromContext = (panel, {semanticChange = false} = {}) => {
        if (!form || !panel) return
        if (semanticChange) durationPrecisionWasManuallyChanged = false
        const hasExistingMilliseconds = Array.from(form.querySelectorAll('[data-duration-milliseconds]')).some(input => Number.parseInt(String(input.value || '0'), 10) > 0)
        if (hasExistingMilliseconds) setDurationPrecision(true, {ignoreManual: semanticChange})
        else setDurationPrecision(activeContextsPreferMilliseconds(panel), {ignoreManual: semanticChange})
    }
    const syncActiveSportContext = ({semanticChange = false, allowNominal = true} = {}) => {
        const panel = modelSelect?.value ? form?.querySelector(`[data-model-panel="${CSS.escape(modelSelect.value)}"]`) : form?.querySelector('[data-model-panel]:not([hidden])')
        if (!panel) return
        const primaryField = findPrimaryField(panel, 'distancia')
        syncDistanceFieldContext(primaryField, mainSportContext(panel), {allowNominal})
        if (isSegmentMode(panel)) {
            const roots = [panel.querySelector('[data-primary-unit-card]'), ...panel.querySelectorAll('[data-unit-index]')].filter(Boolean)
            roots.forEach(root => syncSegmentDistanceContext(root, panel, {allowNominal}))
        }
        syncDurationPrecisionFromContext(panel, {semanticChange})
    }
    const normalizeDistanceFieldsForSubmission = () => {
        const snapshots = []
        form?.querySelectorAll('[data-dynamic-field][data-field-slug="distancia"]').forEach(field => {
            const input = distanceFieldInput(field)
            if (!input || input.disabled) return
            const display = distanceDisplayUnit(field)
            const canonical = distanceCanonicalUnit(field)
            if (display === canonical || String(input.value || '').trim() === '') return
            const converted = sportContextEngine?.convertDistance?.(input.value, display, canonical)
            if (!Number.isFinite(converted)) return
            snapshots.push({field, input, value:input.value, display})
            field.dataset.distanceWritingAutomatic = '1'
            input.value = sportContextEngine.inputNumber(converted, 6)
            delete field.dataset.distanceWritingAutomatic
        })
        return () => snapshots.forEach(({field,input,value,display}) => {
            field.dataset.distanceWritingAutomatic = '1'
            input.value = value
            field.dataset.distanceDisplayUnit = display
            delete field.dataset.distanceWritingAutomatic
        })
    }
    const routeEditor = document.querySelector('[data-route-editor]')
    const updateRouteAvailability = () => {
        if (!routeEditor || !modalitySelect) return
        const allowed = modalitySelect.selectedOptions[0]?.dataset.permiteRota === '1'
        const panel = modelSelect?.value ? document.querySelector(`[data-model-panel="${CSS.escape(modelSelect.value)}"]`) : document.querySelector('[data-model-panel]:not([hidden])')
        const segmented = isSegmentMode(panel)
        routeEditor.dataset.routeAllowed = allowed ? '1' : '0'
        routeEditor.hidden = !allowed || segmented
        if (!allowed) routeEditor.dispatchEvent(new CustomEvent('activity:route-disabled'))
    }
    const distanceValue = (panel) => {
        const field = findPrimaryField(panel, 'distancia')
        const input = field?.querySelector('input[type="number"], input[type="text"]')
        const value = Number.parseFloat(String(input?.value || '').replace(',', '.'))
        return Number.isFinite(value) && value > 0 ? value : null
    }
    const primaryDistanceData = panel => {
        const field = findPrimaryField(panel, 'distancia')
        const input = distanceFieldInput(field)
        const value = Number.parseFloat(String(input?.value || '').replace(',', '.'))
        const symbol = distanceDisplayUnit(field)
        const meters = Number.isFinite(value) && value > 0 ? (symbol === 'm' ? value : value * 1000) : null
        return {field, input, value:Number.isFinite(value) && value > 0 ? value : null, meters, symbol}
    }
    const durationField = (panel) => findPrimaryField(panel, 'duracao')?.querySelector('[data-duration-field]') || null

    function isSegmentMode(panel) {
        return panel?.querySelector('[data-segment-mode-input]')?.value === '1'
    }

    function unitContextRoot(target) {
        if (!target) return null
        if (target.matches?.('[data-unit-index], [data-primary-unit-card]')) return target
        return target.closest?.('[data-unit-index], [data-primary-unit-card]') || null
    }

    function findUnitField(context, slug) {
        const root = unitContextRoot(context)
        if (!root) return null
        return Array.from(root.querySelectorAll(`[data-dynamic-field][data-field-slug="${CSS.escape(slug)}"]`)).find(field => {
            const owner = unitContextRoot(field)
            return owner === root
        }) || null
    }

    function unitDistanceData(context, panel) {
        const root = unitContextRoot(context)
        const segmentInput = root?.querySelector('[data-segment-distance]')
        const segmentUnit = segmentDistanceUnit(root)
        if (segmentInput && isSegmentMode(panel)) {
            const value = Number.parseFloat(String(segmentInput.value || '').replace(',', '.'))
            if (Number.isFinite(value) && value > 0) return {value, meters: segmentUnit === 'm' ? value : value * 1000, symbol: segmentUnit, field: segmentInput.closest('[data-segment-distance-field]'), input: segmentInput}
            const routeMeters = Number(rootRouteEditor(root)?.dataset.routeDistanceM || 0)
            return Number.isFinite(routeMeters) && routeMeters > 0 ? {value: segmentUnit === 'm' ? routeMeters : routeMeters / 1000, meters: routeMeters, symbol: segmentUnit, field: segmentInput.closest('[data-segment-distance-field]'), input: segmentInput} : {value: null, meters: null, symbol: segmentUnit, field: segmentInput.closest('[data-segment-distance-field]'), input: segmentInput}
        }
        const field = findUnitField(root, 'distancia')
        const input = field?.querySelector('input[type="number"], input[type="text"]')
        const value = Number.parseFloat(String(input?.value || '').replace(',', '.'))
        const symbol = distanceDisplayUnit(field)
        if (Number.isFinite(value) && value > 0) return {value, meters: symbol === 'm' ? value : value * 1000, symbol, field, input}
        const routeMeters = Number(rootRouteEditor(root)?.dataset.routeDistanceM || 0)
        return Number.isFinite(routeMeters) && routeMeters > 0 ? {value: symbol === 'm' ? routeMeters : routeMeters / 1000, meters: routeMeters, symbol, field, input} : {value: null, meters: null, symbol, field, input}
    }

    function unitDurationField(context) {
        const root = unitContextRoot(context)
        const panel = root?.closest('[data-model-panel]')
        if (isSegmentMode(panel)) return root?.querySelector('[data-segment-duration-field] [data-duration-field]') || null
        return findUnitField(root, 'duracao')?.querySelector('[data-duration-field]') || null
    }

    function rootRouteEditor(context) {
        const root = unitContextRoot(context)
        return root?.querySelector('[data-unit-route-editor]') || null
    }

    function unitSportSelect(context) {
        return unitContextRoot(context)?.querySelector('[data-unit-sport-select]') || null
    }

    function syncContextualMetricLabels(context) {
        const root = unitContextRoot(context)
        if (!root) return
        const option = unitSportSelect(root)?.selectedOptions?.[0]
        const slug = String(option?.dataset.slug || '').toLowerCase()
        const cadenceUnit = /(cicl|cycl|bike|bici)/.test(slug) ? 'rpm' : (/(corr|run|caminh|walk)/.test(slug) ? tr('activity.steps_per_minute') : '')
        root.querySelectorAll('[data-dynamic-field]').forEach(field => {
            if (unitContextRoot(field) !== root) return
            const fieldSlug = String(field.dataset.fieldSlug || '').toLowerCase()
            const label = field.querySelector('[data-field-label-text]')
            const unit = field.querySelector('[data-contextual-field-unit]')
            if (fieldSlug === 'cadencia') {
                if (label) label.textContent = tr('activity.average_cadence')
                if (unit) {
                    if (cadenceUnit) unit.textContent = cadenceUnit
                    unit.hidden = !cadenceUnit
                }
            } else if (fieldSlug === 'potencia') {
                if (label) label.textContent = tr('activity.average_power')
                if (unit) { unit.textContent = 'W'; unit.hidden = false }
            }
        })
    }

    function selectedUnitDerivedType(context, panel) {
        const select = unitSportSelect(context)
        return select?.selectedOptions?.[0]?.dataset.derivedType || panel?.dataset.derivedType || 'nenhuma'
    }

    function selectedUnitAllowsRoute(context) {
        return unitSportSelect(context)?.selectedOptions?.[0]?.dataset.permiteRota === '1'
    }

    function renumberSegmentTitles(panel) {
        if (!panel) return
        const base = String(panel.querySelector('[data-primary-unit-title]')?.textContent || 'Trecho 1').replace(/\s+1\s*$/, '').trim() || 'Trecho'
        const primaryTitle = panel.querySelector('[data-primary-unit-title]')
        if (primaryTitle) primaryTitle.textContent = `${base} 1`
        const primaryRoute = panel.querySelector('[data-primary-unit-route-wrap] [data-unit-route-editor]')
        if (primaryRoute) {
            primaryRoute.dataset.unitRouteLabel = `${base} 1`
            const routeTitle = primaryRoute.querySelector('[data-unit-route-title]')
            if (routeTitle) routeTitle.textContent = tr('route.unit_title', {label: `${base} 1`})
        }
        Array.from(panel.querySelectorAll('[data-unit-index]')).forEach((unit, index) => {
            const label = `${base} ${index + 2}`
            const title = unit.querySelector('[data-unit-title]')
            if (title) title.textContent = label
            const route = unit.querySelector('[data-unit-route-editor]')
            if (route) {
                route.dataset.unitRouteLabel = label
                const routeTitle = route.querySelector('[data-unit-route-title]')
                if (routeTitle) routeTitle.textContent = tr('route.unit_title', {label})
            }
        })
    }

    function canCloseSegmentMode(panel) {
        if (!panel) return false
        const primaryRoot = panel.querySelector('[data-primary-unit-card]')
        const primarySport = panel.querySelector('[data-primary-unit-context] [data-unit-sport-select]')?.value || panel.dataset.mainModality || ''
        const showCloseError = (message) => {
            const summary = document.querySelector('[data-summary-text]')
            if (summary) summary.textContent = message
            return false
        }
        if (primarySport !== (panel.dataset.mainModality || primarySport)) {
            return showCloseError('O primeiro trecho usa outra modalidade. Troque para a modalidade principal antes de fechar os trechos.')
        }
        const unitRoute = panel.querySelector('[data-primary-unit-route-wrap] [data-unit-route-value]')
        if (String(unitRoute?.value || '').trim() && modalitySelect?.selectedOptions?.[0]?.dataset.permiteRota !== '1') {
            return showCloseError(tr('activity.close_units_route_error'))
        }
        const hasValue = (selector) => String(primaryRoot?.querySelector(selector)?.value || '').trim() !== ''
        if (hasValue('[data-segment-distance]') && !findPrimaryField(panel, 'distancia')) {
            return showCloseError(tr('activity.close_units_distance_error'))
        }
        if (durationToSeconds(primaryRoot?.querySelector('[data-segment-duration-field] [data-duration-field]')) !== null && !durationField(panel)) {
            return showCloseError(tr('activity.close_units_duration_error'))
        }
        if (hasValue('[data-segment-elevation]') && !(findPrimaryField(panel, 'elevacao') || findPrimaryField(panel, 'desnivel'))) {
            return showCloseError(tr('activity.close_units_elevation_error'))
        }
        return true
    }

    function transferPrimaryMetricsToSegment(panel) {
        const root = panel?.querySelector('[data-primary-unit-card]')
        if (!root) return
        const distanceField = findPrimaryField(panel, 'distancia')
        const distanceInput = distanceField?.querySelector('input[type="number"], input[type="text"]')
        const segmentDistance = root.querySelector('[data-segment-distance]')
        const segmentUnit = segmentDistanceUnit(root)
        const sourceUnit = distanceDisplayUnit(distanceField)
        const distance = Number.parseFloat(String(distanceInput?.value || '').replace(',', '.'))
        if (segmentDistance && !String(segmentDistance.value || '').trim() && Number.isFinite(distance) && distance > 0) {
            const meters = sourceUnit === 'm' ? distance : distance * 1000
            segmentDistance.value = String(Number((segmentUnit === 'm' ? meters : meters / 1000).toFixed(3)))
        }
        const sourceDuration = durationToSeconds(durationField(panel))
        const targetDuration = root.querySelector('[data-segment-duration-field] [data-duration-field]')
        if (sourceDuration !== null && durationToSeconds(targetDuration) === null) setDurationSeconds(targetDuration, sourceDuration)
        const elevationField = findPrimaryField(panel, 'elevacao') || findPrimaryField(panel, 'desnivel')
        const elevationInput = elevationField?.querySelector('input[type="number"], input[type="text"]')
        const segmentElevation = root.querySelector('[data-segment-elevation]')
        const elevation = Number.parseFloat(String(elevationInput?.value || '').replace(',', '.'))
        if (segmentElevation && !String(segmentElevation.value || '').trim() && Number.isFinite(elevation)) {
            const sourceElevationUnit = elevationField?.querySelector('.field-unit')?.textContent?.trim() || 'm'
            segmentElevation.value = String(Number((sourceElevationUnit === 'km' ? elevation * 1000 : elevation).toFixed(2)))
        }
    }

    function transferSegmentMetricsToPrimary(panel) {
        const root = panel?.querySelector('[data-primary-unit-card]')
        if (!root) return
        const segmentDistance = root.querySelector('[data-segment-distance]')
        const segmentUnit = segmentDistanceUnit(root)
        const distanceField = findPrimaryField(panel, 'distancia')
        const distanceInput = distanceField?.querySelector('input[type="number"], input[type="text"]')
        const distance = Number.parseFloat(String(segmentDistance?.value || '').replace(',', '.'))
        if (distanceInput && Number.isFinite(distance) && distance > 0) {
            const meters = segmentUnit === 'm' ? distance : distance * 1000
            const targetUnit = distanceDisplayUnit(distanceField)
            distanceInput.value = String(Number((targetUnit === 'm' ? meters : meters / 1000).toFixed(3)))
        }
        const segmentDuration = durationToSeconds(root.querySelector('[data-segment-duration-field] [data-duration-field]'))
        if (segmentDuration !== null) setDurationSeconds(durationField(panel), segmentDuration)
        const segmentElevation = Number.parseFloat(String(root.querySelector('[data-segment-elevation]')?.value || '').replace(',', '.'))
        const elevationField = findPrimaryField(panel, 'elevacao') || findPrimaryField(panel, 'desnivel')
        const elevationInput = elevationField?.querySelector('input[type="number"], input[type="text"]')
        if (elevationInput && Number.isFinite(segmentElevation)) {
            const targetUnit = elevationField?.querySelector('.field-unit')?.textContent?.trim() || 'm'
            elevationInput.value = String(Number((targetUnit === 'km' ? segmentElevation / 1000 : segmentElevation).toFixed(3)))
        }
    }

    function transferRouteEditorState(sourceEditor, targetEditor, sourcePrefix, targetPrefix) {
        if (!sourceEditor || !targetEditor) return false
        const sourceRoute = sourceEditor.querySelector(`[data-${sourcePrefix}-value]`)
        const targetRoute = targetEditor.querySelector(`[data-${targetPrefix}-value]`)
        if (!sourceRoute || !targetRoute || !String(sourceRoute.value || '').trim() || String(targetRoute.value || '').trim()) return false
        targetRoute.value = sourceRoute.value
        const pairs = [
            [`[data-${sourcePrefix}-mode-value]`, `[data-${targetPrefix}-mode-value]`, 'free'],
            [`[data-${sourcePrefix}-laps-value]`, `[data-${targetPrefix}-laps-value]`, '1'],
            [`[data-${sourcePrefix}-base-value]`, `[data-${targetPrefix}-base-value]`, ''],
        ]
        pairs.forEach(([sourceSelector, targetSelector, fallback]) => {
            const source = sourceEditor.querySelector(sourceSelector)
            const target = targetEditor.querySelector(targetSelector)
            if (target) target.value = source?.value || fallback
            if (source) source.value = fallback
        })
        const sourceMetrics = Array.from(sourceEditor.querySelectorAll(`[data-${sourcePrefix}-metric]`))
        const targetMetrics = Array.from(targetEditor.querySelectorAll(`[data-${targetPrefix}-metric]`))
        sourceMetrics.forEach(source => {
            const key = source.dataset[sourcePrefix === 'route' ? 'routeMetric' : 'unitRouteMetric']
            const target = targetMetrics.find(item => item.dataset[targetPrefix === 'route' ? 'routeMetric' : 'unitRouteMetric'] === key)
            if (target) { target.value = source.value; target.disabled = source.disabled }
            source.value = ''
            source.disabled = true
        })
        sourceRoute.value = ''
        return true
    }

    function transferGeneralRouteToPrimary(panel) {
        if (!panel || !routeEditor) return
        const targetEditor = panel.querySelector('[data-primary-unit-route-wrap] [data-unit-route-editor]')
        if (!transferRouteEditorState(routeEditor, targetEditor, 'route', 'unit-route')) return
        targetEditor.dispatchEvent(new CustomEvent('activity:unit-route-reload'))
        routeEditor.dispatchEvent(new CustomEvent('activity:route-reload'))
    }

    function transferPrimaryRouteToGeneral(panel) {
        if (!panel || !routeEditor) return
        const sourceEditor = panel.querySelector('[data-primary-unit-route-wrap] [data-unit-route-editor]')
        if (!transferRouteEditorState(sourceEditor, routeEditor, 'unit-route', 'route')) return
        sourceEditor.dispatchEvent(new CustomEvent('activity:unit-route-reload'))
        routeEditor.dispatchEvent(new CustomEvent('activity:route-reload'))
    }

    function syncUnitSportState(context) {
        const root = unitContextRoot(context)
        if (!root) return
        const panel = root.closest('[data-model-panel]')
        const selected = unitSportSelect(root)?.selectedOptions?.[0]
        const allowed = selected?.dataset.permiteRota === '1'
        const editor = rootRouteEditor(root)
        if (editor) {
            editor.hidden = !allowed
            const hidden = editor.querySelector('[data-unit-route-value]')
            if (hidden) hidden.disabled = !allowed
        }
        const derivedType = selected?.dataset.derivedType || panel?.dataset.derivedType || 'nenhuma'
        if (isSegmentMode(panel)) syncSegmentDistanceContext(root, panel, {allowNominal:true})
        else {
            const field = findUnitField(root, 'distancia')
            syncDistanceFieldContext(field, unitSportContextData(root, panel), {allowNominal:true})
        }
        const segmentDerived = root.querySelector('[data-segment-core-metrics] [data-unit-derived-field]')
        const segmentDerivedInput = segmentDerived?.querySelector('[data-unit-derived-input]')
        if (segmentDerived) segmentDerived.hidden = derivedType === 'nenhuma'
        if (segmentDerivedInput) segmentDerivedInput.disabled = derivedType === 'nenhuma'
        const mainSport = panel?.dataset.mainModality || ''
        const sameSport = !unitSportSelect(root) || unitSportSelect(root).value === mainSport
        root.querySelectorAll('[data-model-unit-fields] [data-dynamic-field]').forEach(field => {
            const slug = field.dataset.fieldSlug || ''
            const canonical = ['distancia', 'duracao', 'elevacao', 'desnivel'].includes(slug)
            const shouldHide = isSegmentMode(panel) && (canonical || !sameSport)
            field.hidden = shouldHide || field.classList.contains('is-optional-hidden')
            field.querySelectorAll('input, select, textarea').forEach(input => { input.disabled = shouldHide || panel.hidden })
        })
        const simpleDerived = root.querySelector('[data-model-unit-fields] [data-derived-field]')
        if (simpleDerived) {
            simpleDerived.hidden = isSegmentMode(panel) || derivedType === 'nenhuma'
            simpleDerived.querySelectorAll('input').forEach(input => { input.disabled = simpleDerived.hidden || panel.hidden })
        }
        syncContextualMetricLabels(root)
        syncDurationPrecisionFromContext(panel)
    }

    function syncSegmentPanel(panel) {
        if (!panel) return
        const active = !panel.hidden
        const segmented = isSegmentMode(panel)
        panel.classList.toggle('is-segmented', segmented)
        const input = panel.querySelector('[data-segment-mode-input]')
        if (input) input.disabled = !active
        const entry = panel.querySelector('[data-segments-entry]')
        const enable = panel.querySelector('[data-enable-segments]')
        const attemptMode = panel.dataset.unitKind === 'tentativa'
        if (enable) enable.textContent = attemptMode ? tr('activity.use_attempts') : tr('activity.use_segments')
        if (entry) {
            entry.hidden = segmented
            entry.classList.toggle('is-active', segmented)
        }
        const primaryHeader = panel.querySelector('[data-primary-unit-header]')
        const primaryContext = panel.querySelector('[data-primary-unit-context]')
        const primaryRouteWrap = panel.querySelector('[data-primary-unit-route-wrap]')
        const primarySegmentCore = panel.querySelector('[data-primary-segment-core]')
        const primaryOptionalWrap = panel.querySelector('[data-primary-unit-optional-wrap]')
        const workspace = panel.querySelector('[data-segments-workspace]')
        if (primaryHeader) primaryHeader.hidden = !segmented
        if (primaryContext) primaryContext.hidden = !segmented
        if (primaryRouteWrap) primaryRouteWrap.hidden = !segmented
        if (primarySegmentCore) primarySegmentCore.hidden = !segmented
        if (primaryOptionalWrap) primaryOptionalWrap.hidden = !segmented
        if (workspace) workspace.hidden = !segmented
        ensureDurationPrecisionToggle()
        primaryContext?.querySelectorAll('input, select, textarea').forEach(element => { element.disabled = !active || !segmented })
        primaryRouteWrap?.querySelectorAll('input, select, textarea, button').forEach(element => { element.disabled = !active || !segmented })
        primarySegmentCore?.querySelectorAll('input, select, textarea').forEach(element => { element.disabled = !active || !segmented })
        workspace?.querySelectorAll('input, select, textarea, button').forEach(element => { element.disabled = !active || !segmented })
        syncUnitSportState(panel.querySelector('[data-primary-unit-card]'))
        if (active && segmented) panel.querySelectorAll('[data-unit-index]').forEach(syncUnitSportState)
        renumberSegmentTitles(panel)
        updateOptionalChips(panel)
        updateRouteAvailability()
        updateSummary(panel)
    }

    function setSegmentMode(panel, active, {moveRoute = false} = {}) {
        if (!panel) return
        const input = panel.querySelector('[data-segment-mode-input]')
        if (!input) return
        if (active) transferPrimaryMetricsToSegment(panel)
        if (active && moveRoute) transferGeneralRouteToPrimary(panel)
        if (!active) transferSegmentMetricsToPrimary(panel)
        if (!active && moveRoute) transferPrimaryRouteToGeneral(panel)
        input.value = active ? '1' : '0'
        syncSegmentPanel(panel)
        updateDerivedMetric(panel)
        panel.querySelectorAll('[data-unit-index]').forEach(updateUnitDerivedMetric)
    }

    function initializeSegmentPanels(scope = document) {
        scope.querySelectorAll?.('[data-model-panel]').forEach(panel => {
            syncSegmentPanel(panel)
            if (isSegmentMode(panel)) {
                updateUnitDerivedMetric(panel.querySelector('[data-primary-unit-card]'))
                panel.querySelectorAll('[data-unit-index]').forEach(updateUnitDerivedMetric)
            }
        })
    }

    const formatDurationCompact = (seconds) => {
        if (sportContextEngine?.formatDuration) return sportContextEngine.formatDuration(seconds, sportContextLocale(), true)
        if (!Number.isFinite(seconds) || seconds < 0) return ''
        const totalMs = Math.round(seconds * 1000)
        const h = Math.floor(totalMs / 3600000)
        const remainingHour = totalMs % 3600000
        const m = Math.floor(remainingHour / 60000)
        const remainingMinute = remainingHour % 60000
        const s = Math.floor(remainingMinute / 1000)
        const ms = remainingMinute % 1000
        const base = h > 0 ? `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}` : `${m}:${String(s).padStart(2, '0')}`
        return ms ? `${base}${i18n.locale === 'pt-BR' ? ',' : '.'}${String(ms).padStart(3, '0')}` : base
    }

    const formatPace = (seconds) => {
        if (!Number.isFinite(seconds) || seconds <= 0) return ''
        const total = Math.round(seconds)
        return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`
    }

    const parsePace = (value) => {
        const text = String(value || '').trim().replace(',', '.')
        if (/^\d{1,3}:\d{1,2}$/.test(text)) {
            const [minutes, seconds] = text.split(':').map(Number)
            if (seconds >= 60) return null
            return minutes * 60 + seconds
        }
        const numeric = Number.parseFloat(text)
        if (!Number.isFinite(numeric) || numeric <= 0) return null
        const minutes = Math.floor(numeric)
        return minutes * 60 + Math.round((numeric - minutes) * 60)
    }

    const derivedConfig = (panel, context = null) => {
        const type = context ? selectedUnitDerivedType(context, panel) : (isSegmentMode(panel) ? selectedUnitDerivedType(panel?.querySelector('[data-primary-unit-card]'), panel) : panel?.dataset.derivedType || 'nenhuma')
        return {
            type,
            label: type === 'velocidade_kmh' ? tr('activity.average_speed') : type === 'pace_100m' ? tr('activity.pace') : type === 'split_500m' ? tr('activity.split') : tr('activity.pace'),
            unit: type === 'velocidade_kmh' ? 'km/h' : type === 'pace_100m' ? '/100 m' : type === 'split_500m' ? '/500 m' : '/km',
        }
    }

    function updateDerivedMetric(panel) {
        if (!panel || panel.hidden) return
        if (isSegmentMode(panel)) {
            const simpleField = panel.querySelector('[data-model-unit-fields] [data-derived-field]')
            if (simpleField) simpleField.hidden = true
            updateUnitDerivedMetric(panel.querySelector('[data-primary-unit-card]'))
            return
        }
        const field = panel.querySelector('[data-derived-field]')
        const input = field?.querySelector('[data-derived-input]')
        if (!field || !input) return
        const primaryContext = panel.querySelector('[data-primary-unit-card]')
        const config = derivedConfig(panel, isSegmentMode(panel) ? primaryContext : null)
        field.hidden = config.type === 'nenhuma'
        input.disabled = config.type === 'nenhuma'
        if (config.type === 'nenhuma') {
            input.value = ''
            return
        }
        field.querySelector('[data-derived-label]').textContent = config.label
        field.querySelector('[data-derived-unit]').textContent = config.unit
        input.placeholder = config.type === 'velocidade_kmh' ? '--,-' : '--:--'

        if (input.dataset.manual === '1') return
        const distanceData = primaryDistanceData(panel)
        const distance = distanceData.value
        const duration = durationToSeconds(durationField(panel))
        if (!distance || !duration) {
            input.value = ''
            return
        }
        const unit = distanceData.symbol
        const distanceKm = unit === 'm' ? distance / 1000 : distance
        if (config.type === 'velocidade_kmh') input.value = (distanceKm / (duration / 3600)).toFixed(1).replace('.', ',')
        if (config.type === 'pace_km') input.value = formatPace(duration / distanceKm)
        if (config.type === 'pace_100m') input.value = formatPace(duration / ((unit === 'm' ? distance : distance * 1000) / 100))
        if (config.type === 'split_500m') input.value = formatPace(duration / ((unit === 'm' ? distance : distance * 1000) / 500))
        field.querySelector('[data-derived-badge]').textContent = tr('activity.calculated')
    }

    const calculateFromDerived = (panel) => {
        if (!panel) return
        if (isSegmentMode(panel)) {
            calculateUnitFromDerived(panel.querySelector('[data-primary-unit-card]'))
            return
        }
        const input = panel.querySelector('[data-derived-input]')
        if (!input) return
        const config = derivedConfig(panel)
        const raw = String(input.value || '').trim()
        if (raw === '') {
            input.dataset.manual = '0'
            updateDerivedMetric(panel)
            return
        }
        const distanceField = findPrimaryField(panel, 'distancia')
        const distanceInput = distanceFieldInput(distanceField)
        const duration = durationToSeconds(durationField(panel))
        const distanceData = primaryDistanceData(panel)
        const distance = distanceData.value
        const unit = distanceData.symbol
        let derived = config.type === 'velocidade_kmh' ? Number.parseFloat(raw.replace(',', '.')) : parsePace(raw)
        if (!Number.isFinite(derived) || derived <= 0) return

        input.dataset.manual = '1'
        panel.querySelector('[data-derived-badge]').textContent = 'Manual'

        if (distance) {
            const distanceKm = unit === 'm' ? distance / 1000 : distance
            let totalSeconds = null
            if (config.type === 'velocidade_kmh') totalSeconds = (distanceKm / derived) * 3600
            if (config.type === 'pace_km') totalSeconds = derived * distanceKm
            if (config.type === 'pace_100m') totalSeconds = derived * ((unit === 'm' ? distance : distance * 1000) / 100)
            if (config.type === 'split_500m') totalSeconds = derived * ((unit === 'm' ? distance : distance * 1000) / 500)
            if (totalSeconds !== null) setDurationSeconds(durationField(panel), totalSeconds)
        } else if (duration && distanceInput) {
            let calculated = null
            if (config.type === 'velocidade_kmh') calculated = derived * (duration / 3600)
            if (config.type === 'pace_km') calculated = duration / derived
            if (config.type === 'pace_100m') calculated = (duration / derived) * 100
            if (config.type === 'split_500m') calculated = (duration / derived) * 500
            if (calculated !== null) {
                if (unit === 'm' && ['velocidade_kmh', 'pace_km'].includes(config.type)) calculated *= 1000
                distanceInput.value = Number(calculated.toFixed(3)).toString()
                distanceInput.dispatchEvent(new Event('change', {bubbles: true}))
            }
        }
        updateSummary(panel)
    }

    function updateUnitDerivedMetric(context) {
        const root = unitContextRoot(context)
        if (!root) return
        const panel = root.closest('[data-model-panel]')
        if (!panel || panel.hidden) return
        const field = root.querySelector('[data-segment-core-metrics] [data-unit-derived-field]')
        const input = field?.querySelector('[data-unit-derived-input]')
        if (!field || !input) return
        const config = derivedConfig(panel, root)
        field.hidden = config.type === 'nenhuma'
        input.disabled = config.type === 'nenhuma'
        if (config.type === 'nenhuma') {
            input.value = ''
            return
        }
        field.querySelector('[data-unit-derived-label]').textContent = config.label
        field.querySelector('[data-unit-derived-unit]').textContent = config.unit
        input.placeholder = config.type === 'velocidade_kmh' ? '--,-' : '--:--'
        if (input.dataset.manual === '1') return
        const distance = unitDistanceData(root, panel)
        const duration = durationToSeconds(unitDurationField(root))
        if (!distance.meters || !duration) {
            input.value = ''
            field.querySelector('[data-unit-derived-badge]').textContent = 'AUTO'
            return
        }
        const km = distance.meters / 1000
        if (config.type === 'velocidade_kmh') input.value = (km / (duration / 3600)).toFixed(1).replace('.', ',')
        if (config.type === 'pace_km') input.value = formatPace(duration / km)
        if (config.type === 'pace_100m') input.value = formatPace(duration / (distance.meters / 100))
        if (config.type === 'split_500m') input.value = formatPace(duration / (distance.meters / 500))
        field.querySelector('[data-unit-derived-badge]').textContent = 'AUTO'
    }

    function calculateUnitFromDerived(context) {
        const root = unitContextRoot(context)
        if (!root) return
        const panel = root.closest('[data-model-panel]')
        if (!panel) return
        const field = root.querySelector('[data-segment-core-metrics] [data-unit-derived-field]')
        const input = field?.querySelector('[data-unit-derived-input]')
        if (!input) return
        const config = derivedConfig(panel, root)
        const raw = String(input.value || '').trim()
        if (raw === '') {
            input.dataset.manual = '0'
            updateUnitDerivedMetric(root)
            return
        }
        const distance = unitDistanceData(root, panel)
        const duration = durationToSeconds(unitDurationField(root))
        const parsed = config.type === 'velocidade_kmh' ? Number.parseFloat(raw.replace(',', '.')) : parsePace(raw)
        if (!Number.isFinite(parsed) || parsed <= 0) return
        input.dataset.manual = '1'
        field?.querySelector('[data-unit-derived-badge]')?.replaceChildren(document.createTextNode('ENTRADA'))
        if (distance.meters) {
            let totalSeconds = null
            if (config.type === 'velocidade_kmh') totalSeconds = ((distance.meters / 1000) / parsed) * 3600
            if (config.type === 'pace_km') totalSeconds = parsed * (distance.meters / 1000)
            if (config.type === 'pace_100m') totalSeconds = parsed * (distance.meters / 100)
            if (config.type === 'split_500m') totalSeconds = parsed * (distance.meters / 500)
            if (totalSeconds !== null) setDurationSeconds(unitDurationField(root), totalSeconds)
        } else if (duration && distance.input) {
            let meters = null
            if (config.type === 'velocidade_kmh') meters = parsed * (duration / 3600) * 1000
            if (config.type === 'pace_km') meters = (duration / parsed) * 1000
            if (config.type === 'pace_100m') meters = (duration / parsed) * 100
            if (config.type === 'split_500m') meters = (duration / parsed) * 500
            if (meters !== null) {
                distance.input.value = String(Number((distance.symbol === 'm' ? meters : meters / 1000).toFixed(3)))
                distance.input.dispatchEvent(new Event('change', {bubbles: true}))
            }
        }
        updateSummary(panel)
    }

    document.addEventListener('input', (event) => {
        const panel = event.target.closest('[data-model-panel]')
        if (!panel || panel.hidden) return
        if (event.target.matches('[data-derived-input], [data-unit-derived-input]')) return
        const field = event.target.closest('[data-dynamic-field]')
        if (event.isTrusted && field?.dataset.fieldSlug === 'distancia' && event.target === distanceFieldInput(field) && field.dataset.distanceWritingAutomatic !== '1') {
            field.dataset.distanceManual = '1'
            delete field.dataset.distanceAutomaticNominal
        }
        if (event.isTrusted && event.target.matches('[data-segment-distance]')) {
            const segmentField = event.target.closest('[data-segment-distance-field]')
            if (segmentField && segmentField.dataset.distanceWritingAutomatic !== '1') {
                segmentField.dataset.distanceManual = '1'
                delete segmentField.dataset.distanceAutomaticNominal
                delete segmentField.dataset.distanceAutomaticSeries
            }
        }
        const segmentMetric = event.target.matches('[data-segment-distance], [data-segment-elevation]') || event.target.closest('[data-segment-duration-field]')
        if (field?.dataset.fieldSlug === 'distancia' || event.target.closest('[data-duration-field]') || segmentMetric) {
            const context = unitContextRoot(event.target)
            if (isSegmentMode(panel) && context) {
                if (event.target.matches('[data-segment-distance]') || event.target.closest('[data-segment-duration-field]')) {
                    const derived = context.querySelector('[data-unit-derived-input]')
                    if (derived) derived.dataset.manual = '0'
                    updateUnitDerivedMetric(context)
                }
            } else if (context?.matches('[data-unit-index]')) {
                const derived = context.querySelector('[data-unit-derived-input]')
                if (derived) derived.dataset.manual = '0'
                updateUnitDerivedMetric(context)
            } else {
                const derived = panel.querySelector('[data-derived-input]')
                if (derived) derived.dataset.manual = '0'
                updateDerivedMetric(panel)
            }
            updateSummary(panel)
        }
    })

    document.addEventListener('activity:valuechange', (event) => {
        const panel = event.target.closest('[data-model-panel]')
        if (!panel || panel.hidden) return
        const context = unitContextRoot(event.target)
        if (context?.matches('[data-unit-index]')) updateUnitDerivedMetric(context)
        else updateDerivedMetric(panel)
        updateSummary(panel)
    })

    document.addEventListener('change', (event) => {
        const mainDistanceUnit = event.target.closest?.('[data-distance-unit-select]')
        if (mainDistanceUnit) {
            const field = mainDistanceUnit.closest('[data-dynamic-field][data-field-slug="distancia"]')
            const input = distanceFieldInput(field)
            const previous = field?.dataset.distanceDisplayUnit || field?.dataset.distanceCanonicalUnit || 'km'
            const next = mainDistanceUnit.value === 'm' ? 'm' : 'km'
            const meters = sportContextEngine?.metersFrom?.(input?.value, previous)
            if (field) field.dataset.distanceUnitManual = '1'
            if (Number.isFinite(meters)) writeDistanceFieldValue(field, meters, next)
            else if (field) field.dataset.distanceDisplayUnit = next
            const panel = field?.closest('[data-model-panel]')
            if (panel) {
                updateDerivedMetric(panel)
                updateSummary(panel)
            }
            return
        }
        const segmentDistanceUnitSelect = event.target.closest?.('[data-segment-distance-unit-select]')
        if (segmentDistanceUnitSelect) {
            const root = unitContextRoot(segmentDistanceUnitSelect)
            const field = segmentDistanceField(root)
            const previous = field?.dataset.distanceDisplayUnit || field?.querySelector('[data-segment-distance-unit-value]')?.value || 'km'
            const next = segmentDistanceUnitSelect.value === 'm' ? 'm' : 'km'
            const meters = sportContextEngine?.metersFrom?.(field?.querySelector('[data-segment-distance]')?.value, previous)
            if (field) field.dataset.distanceUnitManual = '1'
            if (Number.isFinite(meters)) writeSegmentDistance(root, meters, next)
            else {
                const hidden = field?.querySelector('[data-segment-distance-unit-value]')
                if (hidden) hidden.value = next
                if (field) field.dataset.distanceDisplayUnit = next
            }
            const panel = root?.closest('[data-model-panel]')
            updateUnitDerivedMetric(root)
            if (panel) updateSummary(panel)
            return
        }
        const sport = event.target.closest?.('[data-unit-sport-select]')
        if (sport) {
            const context = unitContextRoot(sport)
            const panel = sport.closest('[data-model-panel]')
            syncUnitSportState(context)
            if (context?.matches('[data-primary-unit-card]')) updateDerivedMetric(panel)
            else updateUnitDerivedMetric(context)
            updateSummary(panel)
            return
        }
        if (event.target.matches('[data-derived-input]')) calculateFromDerived(event.target.closest('[data-model-panel]'))
        if (event.target.matches('[data-unit-derived-input]')) calculateUnitFromDerived(event.target)
    })

    document.addEventListener('focusout', event => {
        const field = event.target.closest?.('[data-dynamic-field][data-field-slug="distancia"]')
        if (field && event.target === distanceFieldInput(field) && field.dataset.distanceUnitManual !== '1') {
            const panel = field.closest('[data-model-panel]')
            if (panel && !panel.hidden) {
                syncDistanceFieldContext(field, mainSportContext(panel))
                updateDerivedMetric(panel)
                updateSummary(panel)
            }
            return
        }
        if (!event.target.matches?.('[data-segment-distance]')) return
        const root = unitContextRoot(event.target)
        const segmentField = segmentDistanceField(root)
        if (!root || segmentField?.dataset.distanceUnitManual === '1') return
        const panel = root.closest('[data-model-panel]')
        if (!panel || panel.hidden) return
        syncSegmentDistanceContext(root, panel)
        updateUnitDerivedMetric(root)
        updateSummary(panel)
    })

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return
        if (event.target.matches('[data-derived-input]')) {
            event.preventDefault()
            calculateFromDerived(event.target.closest('[data-model-panel]'))
        }
        if (event.target.matches('[data-unit-derived-input]')) {
            event.preventDefault()
            calculateUnitFromDerived(event.target)
        }
    })

    function updateSummary(panel) {
        const summary = document.querySelector('[data-summary-text]')
        const label = document.querySelector('[data-summary-label]')
        if (!summary || !panel || panel.hidden) return
        const parts = []
        if (isSegmentMode(panel)) {
            if (label) label.textContent = tr('activity.session_summary')
            const contexts = [panel.querySelector('[data-primary-unit-card]'), ...panel.querySelectorAll('[data-unit-index]')].filter(Boolean)
            let distanceM = 0
            let distanceCount = 0
            let duration = 0
            let durationCount = 0
            let elevation = 0
            let elevationCount = 0
            const derivedTypes = new Set()
            contexts.forEach(context => {
                const distance = unitDistanceData(context, panel)
                if (distance.meters) {
                    distanceM += distance.meters
                    distanceCount++
                }
                const seconds = durationToSeconds(unitDurationField(context))
                if (seconds !== null) {
                    duration += seconds
                    durationCount++
                }
                const segmentElevationInput = context.querySelector('[data-segment-elevation]')
                if (segmentElevationInput) {
                    const elevationValue = Number.parseFloat(String(segmentElevationInput.value || '').replace(',', '.'))
                    if (Number.isFinite(elevationValue)) {
                        elevation += elevationValue
                        elevationCount++
                    }
                } else {
                    const elevationField = findUnitField(context, 'elevacao') || findUnitField(context, 'desnivel')
                    const elevationInput = elevationField?.querySelector('input[type="number"], input[type="text"]')
                    const elevationValue = Number.parseFloat(String(elevationInput?.value || '').replace(',', '.'))
                    if (Number.isFinite(elevationValue)) {
                        const symbol = elevationField?.querySelector('.field-unit')?.textContent?.trim() || 'm'
                        elevation += symbol === 'km' ? elevationValue * 1000 : elevationValue
                        elevationCount++
                    }
                }
                const type = selectedUnitDerivedType(context, panel)
                if (type && type !== 'nenhuma') derivedTypes.add(type)
            })
            if (distanceCount) {
                const distanceText = sportContextEngine?.formatDistance?.(distanceM, mainSportContext(panel, {registered_m:distanceM}), sportContextLocale())
                if (distanceText) parts.push(distanceText)
            }
            if (durationCount) parts.push(formatDurationCompact(duration))
            if (distanceCount && durationCount && derivedTypes.size === 1) {
                const type = Array.from(derivedTypes)[0]
                const km = distanceM / 1000
                if (type === 'velocidade_kmh' && km > 0 && duration > 0) parts.push(`${i18n.number(km / (duration / 3600), 1, true)} km/h`)
                if (type === 'pace_km' && km > 0) parts.push(`${formatPace(duration / km)} /km`)
                if (type === 'pace_100m' && distanceM > 0) parts.push(`${formatPace(duration / (distanceM / 100))} /100 m`)
                if (type === 'split_500m' && distanceM > 0) parts.push(`${formatPace(duration / (distanceM / 500))} /500 m`)
            }
            if (elevationCount) parts.push(`${i18n.number(Number(elevation.toFixed(1)), 1, true)} m`)
            const effort = document.querySelector('[data-effort-value]')?.value
            if (effort) parts.push(tr('activity.effort_inline', {value: effort}))
            const summaryWrap = summary.closest('[data-activity-summary]')
            summary.textContent = parts.join(' · ')
            if (summaryWrap) summaryWrap.hidden = parts.length === 0
            return
        }
        if (label) label.textContent = tr('activity.summary')
        const distanceData = primaryDistanceData(panel)
        const distance = distanceData.value
        const distanceUnit = distanceData.symbol
        const duration = durationToSeconds(durationField(panel))
        const derived = panel.querySelector('[data-derived-input]')
        const config = derivedConfig(panel)
        if (distanceData.meters) parts.push(sportContextEngine?.formatDistance?.(distanceData.meters, mainSportContext(panel, {registered_m:distanceData.meters}), sportContextLocale(), distanceFieldInput(distanceData.field)?.closest('[data-dynamic-field]')?.dataset.distanceUnitManual === '1' ? distanceUnit : '') || `${i18n.number(Number(distance.toFixed(3)), 3, true)} ${distanceUnit}`.trim())
        if (duration !== null) parts.push(formatDurationCompact(duration))
        if (derived?.value) parts.push(`${derived.value} ${config.unit}`)
        if (distance && duration && config.type === 'pace_km') {
            const distanceKm = distanceUnit === 'm' ? distance / 1000 : distance
            const speed = distanceKm / (duration / 3600)
            if (Number.isFinite(speed) && speed > 0) parts.push(`${i18n.number(speed, 1, true)} km/h`)
        }
        const elevationField = findPrimaryField(panel, 'elevacao')
        const elevationInput = elevationField?.querySelector('input[type="number"], input[type="text"]')
        const elevation = Number.parseFloat(String(elevationInput?.value || '').replace(',', '.'))
        if (Number.isFinite(elevation) && elevation !== 0 && !elevationField?.hidden) {
            const elevationUnit = elevationField.querySelector('.field-unit')?.textContent?.trim() || 'm'
            parts.push(`${i18n.number(Number(elevation.toFixed(1)), 1, true)} ${elevationUnit}`)
        }
        const effort = document.querySelector('[data-effort-value]')?.value
        if (effort) parts.push(tr('activity.effort_inline', {value: effort}))
        const summaryWrap = summary.closest('[data-activity-summary]')
        summary.textContent = parts.join(' · ')
        if (summaryWrap) summaryWrap.hidden = parts.length === 0
    }

    const bindRouteEditor = (editor) => {
        if (!editor || editor.dataset.routeBound === '1') return
        editor.dataset.routeBound = '1'
        const routeUtil = window.StrideBRRoute
        let hidden = editor.querySelector('[data-route-value]')
        let baseHidden = editor.querySelector('[data-route-base-value]')
        let modeHidden = editor.querySelector('[data-route-mode-value]')
        let lapsHidden = editor.querySelector('[data-route-laps-value]')
        const workspace = editor.querySelector('[data-route-workspace]')
        const mapElement = editor.querySelector('[data-route-map]')
        const distanceLabel = editor.querySelector('[data-route-distance]')
        const pointsLabel = editor.querySelector('[data-route-points]')
        const elevationLabel = editor.querySelector('[data-route-elevation]')
        const toggle = editor.querySelector('[data-route-toggle]')
        const freeCloseButton = editor.querySelector('[data-route-close-free]')
        const closeButton = editor.querySelector('[data-route-close-circuit]')
        const editBaseButton = editor.querySelector('[data-route-edit-base]')
        const circuitPanel = editor.querySelector('[data-route-circuit-panel]')
        const lapSummary = editor.querySelector('[data-route-lap-summary]')
        const lapOutput = editor.querySelector('[data-route-laps]')
        const totalOutput = editor.querySelector('[data-route-total]')
        const modeButtons = Array.from(editor.querySelectorAll('[data-route-mode]'))
        let metricInputs = Array.from(editor.querySelectorAll('[data-route-metric]'))
        const compactRouteEditor = editor.dataset.routeCompact === '1'
        const subviewBack = editor.querySelector('[data-route-subview-back]')
        const subviewDone = editor.querySelector('[data-route-subview-done]')
        const subviewTitle = editor.querySelector('[data-route-subview-title]')
        const subviewSummary = editor.querySelector('[data-route-subview-summary]')
        const subviewStatus = editor.querySelector('[data-route-subview-status]')
        const routeStatus = editor.querySelector('.activity-route-status')
        const routePrivacy = form?.querySelector('[data-route-privacy-fields]')
        const generalTarget = {hidden, baseHidden, modeHidden, lapsHidden, metricInputs}
        let activeUnitTarget = null
        let activeUnitContext = null
        let returnFocusElement = toggle
        let routeSubviewScrollTop = 0
        let map = null
        let line = null
        let markers = []
        let elevationTimer = null
        let elevationRequest = 0
        let routeResizeObserver = null
        let routeInvalidateFrame = 0
        let points = []
        let mode = 'free'
        let laps = 1
        let circuitClosed = false
        let freeClosed = false
        let circuitEditing = false
        let latestElevationGain = null
        const manuallyEditedCalculatedInputs = new WeakSet()

        const normalize = value => routeUtil?.normalizePoints(value) || []
        const isClosed = value => value.length >= 4 && Boolean(routeUtil?.samePoint(value[0], value.at(-1)))
        const baseRoute = () => mode === 'circuit' ? normalize(points) : normalize(points)
        const expansion = () => mode === 'circuit' && circuitClosed ? routeUtil.expandCircuit(points, laps) : null
        const finalPoints = () => {
            if (mode === 'circuit') {
                const result = expansion()
                return result?.ok ? result.coordinates : []
            }
            return points.length >= 2 ? normalize(points) : []
        }
        const finalGeojson = () => routeUtil.geojson(finalPoints())
        const formatMeters = meters => meters >= 1000
            ? `${i18n.number(meters / 1000, 2, false)} km`
            : `${i18n.number(Math.round(meters), 0)} m`
        const unitTargetMetric = (target, key) => Array.from(target?.querySelectorAll('[data-unit-route-metric]') || []).find(input => input.dataset.unitRouteMetric === key)
        const updateUnitRouteSummary = (target, meters = null) => {
            if (!target) return
            let distance = Number.isFinite(meters) ? meters : Number(target.dataset.routeDistanceM || 0)
            if (!Number.isFinite(distance) || distance <= 0) {
                try {
                    const parsed = JSON.parse(String(target.querySelector('[data-unit-route-value]')?.value || ''))
                    const coords = normalize(parsed?.type === 'LineString' ? parsed.coordinates : [])
                    distance = coords.length >= 2 ? routeUtil.distanceMeters(coords) : 0
                } catch (_) { distance = 0 }
            }
            const gain = Number(target.dataset.routeGainM || unitTargetMetric(target, 'ganho_elevacao_m')?.value || 0)
            const valid = Number.isFinite(distance) && distance > 0
            target.dataset.routeDistanceM = valid ? String(distance) : ''
            target.dataset.routeHasPoints = valid ? '1' : '0'
            target.classList.toggle('has-route', valid)
            const summary = target.querySelector('[data-unit-route-summary]')
            const action = target.querySelector('[data-unit-route-open]')
            if (summary) summary.textContent = valid ? `${formatMeters(distance)}${Number.isFinite(gain) && gain > 0 ? ` · +${i18n.number(Math.round(gain), 0)} m` : ''}` : tr('common.optional')
            if (action) action.textContent = valid ? tr('route.edit') : tr('route.add')
        }
        const routeSubviewSummaryText = (distance) => {
            if (!Number.isFinite(distance) || distance <= 0) return tr('route.add_manual')
            const parts = [formatMeters(distance)]
            const metricGain = activeUnitTarget
                ? Number(activeUnitTarget.dataset.routeGainM || unitTargetMetric(activeUnitTarget, 'ganho_elevacao_m')?.value || 0)
                : Number(metricInputs.find(input => input.dataset.routeMetric === 'ganho_elevacao_m')?.value || 0)
            const gain = Number.isFinite(latestElevationGain) ? latestElevationGain : metricGain
            if (Number.isFinite(gain) && gain > 0) parts.push(`+${i18n.number(Math.round(gain), 0)} m`)
            else if (mode === 'circuit' && circuitClosed) parts.push(trn('route.lap.one', 'route.lap.other', laps))
            return parts.join(' · ')
        }
        const useGeneralRouteTarget = () => {
            hidden = generalTarget.hidden
            baseHidden = generalTarget.baseHidden
            modeHidden = generalTarget.modeHidden
            lapsHidden = generalTarget.lapsHidden
            metricInputs = generalTarget.metricInputs
            activeUnitTarget = null
            activeUnitContext = null
            returnFocusElement = toggle
            if (subviewTitle) subviewTitle.textContent = tr('route.title')
        }
        const useUnitRouteTarget = target => {
            if (!target) return false
            hidden = target.querySelector('[data-unit-route-value]')
            baseHidden = target.querySelector('[data-unit-route-base-value]')
            modeHidden = target.querySelector('[data-unit-route-mode-value]')
            lapsHidden = target.querySelector('[data-unit-route-laps-value]')
            metricInputs = Array.from(target.querySelectorAll('[data-unit-route-metric]'))
            if (!hidden || !baseHidden || !modeHidden || !lapsHidden) {
                useGeneralRouteTarget()
                return false
            }
            activeUnitTarget = target
            activeUnitContext = unitContextRoot(target)
            returnFocusElement = target.querySelector('[data-unit-route-open]')
            if (subviewTitle) subviewTitle.textContent = `${tr('route.title')} · ${String(target.dataset.unitRouteLabel || '').trim()}`
            return true
        }
        const invalidateRouteMap = () => {
            if (!map) return
            if (routeInvalidateFrame) window.cancelAnimationFrame(routeInvalidateFrame)
            routeInvalidateFrame = window.requestAnimationFrame(() => {
                routeInvalidateFrame = 0
                map?.invalidateSize?.({pan: false})
            })
        }
        const activePanel = () => activeUnitContext?.closest('[data-model-panel]') || (modelSelect ? document.querySelector(`[data-model-panel="${CSS.escape(modelSelect.value)}"]`) : document.querySelector('[data-model-panel]:not([hidden])'))
        const setMetric = (key, value) => {
            const input = metricInputs.find(item => (activeUnitTarget ? item.dataset.unitRouteMetric : item.dataset.routeMetric) === key)
            if (!input) return
            input.value = value === null || value === undefined || value === '' ? '' : String(value)
        }
        const clearElevationMetrics = () => ['ganho_elevacao_m', 'perda_elevacao_m', 'elevacao_min_m', 'elevacao_max_m', 'fonte_elevacao'].forEach(key => setMetric(key, ''))
        const resetMetrics = () => metricInputs.forEach(input => { input.value = ''; input.disabled = mode !== 'circuit' })
        const syncCalculatedField = (slugs, meters) => {
            const panel = activePanel()
            if (!panel) return
            if (activeUnitContext) {
                let input = null
                let unit = 'm'
                if (slugs.includes('distancia')) {
                    input = activeUnitContext.querySelector('[data-segment-distance]') || findUnitField(activeUnitContext, 'distancia')?.querySelector('input[type="number"], input[type="text"]')
                    unit = activeUnitContext.querySelector('[data-segment-distance]') ? segmentDistanceUnit(activeUnitContext) : distanceDisplayUnit(findUnitField(activeUnitContext, 'distancia'))
                } else {
                    input = activeUnitContext.querySelector('[data-segment-elevation]') || (findUnitField(activeUnitContext, 'elevacao') || findUnitField(activeUnitContext, 'desnivel'))?.querySelector('input[type="number"], input[type="text"]')
                }
                if (!input || manuallyEditedCalculatedInputs.has(input)) return
                const current = String(input.value || '').trim()
                if (current !== '' && input.dataset.routeAutoFilled !== '1') return
                input.value = String(Number((unit === 'km' ? meters / 1000 : meters).toFixed(slugs.includes('distancia') ? 3 : 1)))
                input.dataset.routeAutoFilled = '1'
                input.dispatchEvent(new Event('input', {bubbles: true}))
                if (slugs.includes('distancia')) {
                    if (isSegmentMode(panel)) syncSegmentDistanceContext(activeUnitContext, panel)
                    else syncDistanceFieldContext(findUnitField(activeUnitContext, 'distancia'), unitSportContextData(activeUnitContext, panel, {registered_m:meters}))
                }
                return
            }
            const field = slugs.map(slug => findPrimaryField(panel, slug)).find(Boolean)
            const input = field?.querySelector('input[type="number"], input[type="text"]')
            if (!field || !input || manuallyEditedCalculatedInputs.has(input)) return
            field.hidden = false
            field.classList.remove('is-optional-hidden')
            const unit = slugs.includes('distancia') ? distanceDisplayUnit(field) : (field.querySelector('.field-unit')?.textContent?.trim() || 'm')
            input.value = String(Number((unit === 'km' ? meters / 1000 : meters).toFixed(3)))
            input.dispatchEvent(new Event('input', {bubbles: true}))
            if (slugs.includes('distancia')) syncDistanceFieldContext(field, mainSportContext(panel, {registered_m:meters}))
            updateOptionalChips(panel)
        }
        document.addEventListener('input', event => {
            if (!event.isTrusted) return
            const dynamicField = event.target.closest('[data-dynamic-field]')
            const slug = dynamicField?.dataset.fieldSlug || ''
            if (!dynamicField || !['distancia', 'elevacao', 'desnivel'].includes(slug)) return
            manuallyEditedCalculatedInputs.add(event.target)
        })
        const writeState = () => {
            if (modeHidden) modeHidden.value = mode
            if (lapsHidden) lapsHidden.value = String(laps)
            if (baseHidden) baseHidden.value = mode === 'circuit' && points.length ? JSON.stringify({type: 'LineString', coordinates: normalize(points)}) : ''
            const route = finalGeojson()
            if (hidden) hidden.value = route ? JSON.stringify(route) : ''
        }
        const readState = () => {
            circuitEditing = false
            freeClosed = false
            const storedGain = activeUnitTarget
                ? Number(activeUnitTarget.dataset.routeGainM || unitTargetMetric(activeUnitTarget, 'ganho_elevacao_m')?.value || 0)
                : Number(metricInputs.find(input => input.dataset.routeMetric === 'ganho_elevacao_m')?.value || 0)
            latestElevationGain = Number.isFinite(storedGain) && storedGain > 0 ? storedGain : null
            let hiddenPoints = []
            let savedBase = []
            try {
                const parsed = JSON.parse(String(hidden?.value || ''))
                hiddenPoints = normalize(parsed?.type === 'LineString' ? parsed.coordinates : [])
            } catch (_) {}
            try {
                const parsedBase = JSON.parse(String(baseHidden?.value || ''))
                savedBase = normalize(parsedBase?.type === 'LineString' ? parsedBase.coordinates : [])
            } catch (_) {}
            const savedMode = String(modeHidden?.value || '')
            const savedLaps = Math.max(1, Math.trunc(Number(lapsHidden?.value) || 1))
            if (savedMode === 'circuit' && savedBase.length >= 3) {
                mode = 'circuit'; points = savedBase; laps = savedLaps; circuitClosed = isClosed(points); freeClosed = false
            } else {
                const detected = routeUtil?.detectCircuit(hiddenPoints)
                if (detected) {
                    mode = 'circuit'; points = normalize(detected.base); laps = detected.laps; circuitClosed = true; freeClosed = false
                } else {
                    mode = 'free'; points = hiddenPoints; laps = 1; circuitClosed = false; freeClosed = isClosed(points)
                }
            }
        }
        const renderLayers = () => {
            if (!map || !window.L) return
            if (line) line.remove()
            markers.forEach(marker => marker.remove())
            markers = []
            const latLngs = points.map(point => [point[1], point[0]])
            line = latLngs.length >= 2 ? window.L.polyline(latLngs, {color: getUiColor('--ui-route', '#4f72df'), weight: 4, opacity: .96}).addTo(map) : null
            const routeClosed = mode === 'circuit' ? circuitClosed : freeClosed
            const uniqueLastIndex = routeClosed ? Math.max(0, points.length - 2) : Math.max(0, points.length - 1)
            const markerIndexes = mode === 'circuit' && circuitClosed && !circuitEditing
                ? (points.length ? [0] : [])
                : points.map((_, index) => index).filter(index => !(routeClosed && index === points.length - 1))
            markers = markerIndexes.map(index => {
                const point = points[index]
                const icon = window.L.divIcon({className: `activity-route-vertex${index === 0 ? ' is-start' : ''}${index === uniqueLastIndex && index !== 0 ? ' is-end' : ''}`, iconSize: [12, 12], iconAnchor: [6, 6]})
                const draggable = mode !== 'circuit' || !circuitClosed || circuitEditing
                const marker = window.L.marker([point[1], point[0]], {draggable, icon}).addTo(map)
                marker.on('dragend', event => {
                    const latLng = event.target.getLatLng()
                    points[index] = [latLng.lng, latLng.lat]
                    if ((mode === 'circuit' && circuitClosed || mode === 'free' && freeClosed) && index === 0) points[points.length - 1] = [...points[0]]
                    sync()
                })
                return marker
            })
        }
        const requestElevation = () => {
            clearTimeout(elevationTimer)
            latestElevationGain = null
            const elevationPoints = mode === 'circuit' && circuitClosed ? points : finalPoints()
            const route = routeUtil.geojson(elevationPoints)
            if (!route) {
                if (elevationLabel) elevationLabel.textContent = tr('route.estimated_elevation_after')
                return
            }
            if (mode === 'circuit' && circuitClosed) clearElevationMetrics()
            if (activeUnitTarget) {
                activeUnitTarget.dataset.routeGainM = ''
                updateUnitRouteSummary(activeUnitTarget)
            }
            elevationTimer = setTimeout(async () => {
                const request = ++elevationRequest
                if (elevationLabel) elevationLabel.textContent = tr('route.elevation_estimating')
                try {
                    const body = new URLSearchParams({csrf_token: form?.querySelector('[name="csrf_token"]')?.value || '', coordenadas: JSON.stringify(route)})
                    const response = await fetchWithDeadline('/api/atividade-elevacao.php', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'Accept': 'application/json'}, body}, 12000)
                    const data = await response.json()
                    if (request !== elevationRequest) return
                    if (!response.ok || !data.ok) throw new Error(data.message || 'unavailable')
                    const multiplier = mode === 'circuit' && circuitClosed ? laps : 1
                    const gainBase = Number(data.elevation?.ganho_elevacao_m)
                    const lossBase = Number(data.elevation?.perda_elevacao_m)
                    const gain = Number.isFinite(gainBase) ? gainBase * multiplier : null
                    latestElevationGain = Number.isFinite(gain) ? gain : null
                    if (activeUnitTarget) {
                        activeUnitTarget.dataset.routeGainM = Number.isFinite(gain) ? String(gain) : ''
                        updateUnitRouteSummary(activeUnitTarget)
                        if (subviewSummary) subviewSummary.textContent = routeSubviewSummaryText(Number(activeUnitTarget.dataset.routeDistanceM || 0))
                    }
                    if (!activeUnitTarget && subviewSummary) {
                        const routeDistance = finalGeojson() ? (mode === 'circuit' && circuitClosed ? routeUtil.distanceMeters(points) * laps : routeUtil.distanceMeters(points)) : 0
                        subviewSummary.textContent = routeSubviewSummaryText(routeDistance)
                    }
                    if (mode === 'circuit') {
                        metricInputs.forEach(input => { input.disabled = false })
                        setMetric('ganho_elevacao_m', Number.isFinite(gainBase) ? gain : '')
                        setMetric('perda_elevacao_m', Number.isFinite(lossBase) ? lossBase * multiplier : '')
                        setMetric('elevacao_min_m', data.elevation?.elevacao_min_m ?? '')
                        setMetric('elevacao_max_m', data.elevation?.elevacao_max_m ?? '')
                        setMetric('fonte_elevacao', data.elevation?.fonte_elevacao ?? '')
                    }
                    if (elevationLabel) elevationLabel.textContent = Number.isFinite(gain) ? tr('route.elevation_gain', {value: i18n.number(Math.round(gain), 0)}) : tr('route.elevation_calculated')
                    if (Number.isFinite(gain)) syncCalculatedField(['elevacao', 'desnivel'], gain)
                } catch (_) {
                    if (request === elevationRequest && elevationLabel) elevationLabel.textContent = tr('route.elevation_unavailable')
                }
            }, 900)
        }
        const syncModeUi = () => {
            modeButtons.forEach(button => {
                const active = button.dataset.routeMode === mode
                button.classList.toggle('is-active', active)
                button.setAttribute('aria-pressed', active ? 'true' : 'false')
            })
            if (freeCloseButton) freeCloseButton.hidden = mode !== 'free' || freeClosed || points.length < 3
            if (closeButton) closeButton.hidden = mode !== 'circuit' || circuitClosed || points.length < 3
            if (circuitPanel) circuitPanel.hidden = mode !== 'circuit' || !circuitClosed
            if (editBaseButton) {
                editBaseButton.hidden = mode !== 'circuit' || !circuitClosed
                editBaseButton.textContent = tr(circuitEditing ? 'route.finish_base_edit' : 'route.edit_base')
                editBaseButton.setAttribute('aria-pressed', circuitEditing ? 'true' : 'false')
            }
            if (lapOutput) lapOutput.textContent = String(laps)
        }
        const sync = (requestTerrain = true) => {
            const expanded = mode === 'circuit' && circuitClosed ? expansion() : null
            const invalidLimit = Boolean(expanded && !expanded.ok && expanded.reason === 'point_limit')
            const route = finalGeojson()
            const baseDistance = mode === 'circuit' && circuitClosed ? routeUtil.distanceMeters(points) : 0
            const distance = route ? (mode === 'circuit' && circuitClosed ? baseDistance * laps : routeUtil.distanceMeters(points)) : 0
            const stateTarget = activeUnitTarget || editor
            stateTarget.dataset.routeInvalid = invalidLimit || (mode === 'circuit' && points.length > 0 && !circuitClosed) ? '1' : '0'
            stateTarget.classList.toggle('has-route', Boolean(route))
            stateTarget.dataset.routeHasPoints = route ? '1' : '0'
            if (activeUnitTarget) {
                activeUnitTarget.dataset.routeDistanceM = route ? String(distance) : ''
                if (!route) activeUnitTarget.dataset.routeGainM = ''
            }
            else {
                editor.dataset.routeInvalid = stateTarget.dataset.routeInvalid
                editor.dataset.routeHasPoints = route ? '1' : '0'
                if (routePrivacy) routePrivacy.hidden = !route
                if (!route) setRoutePrivacyExpanded(false)
            }
            if (mode === 'circuit') {
                metricInputs.forEach(input => { input.disabled = !circuitClosed || invalidLimit })
                if (circuitClosed && !invalidLimit) setMetric('distancia_metros', distance)
                else metricInputs.forEach(input => { input.value = '' })
            } else resetMetrics()
            writeState()
            if (!activeUnitTarget && distanceLabel) distanceLabel.textContent = route ? formatMeters(distance) : (compactRouteEditor ? tr('route.add_manual') : tr('route.no_route'))
            if (activeUnitTarget) updateUnitRouteSummary(activeUnitTarget, route ? distance : 0)
            if (subviewSummary) subviewSummary.textContent = route ? routeSubviewSummaryText(distance) : tr('route.add_manual')
            const completedRoute = !invalidLimit && ((mode === 'circuit' && circuitClosed && !circuitEditing) || (mode === 'free' && freeClosed))
            if (pointsLabel) {
                if (invalidLimit) pointsLabel.textContent = tr('route.point_limit', {laps})
                else if (mode === 'circuit' && circuitClosed) pointsLabel.textContent = tr(circuitEditing ? 'route.editing_base' : 'route.closed')
                else if (mode === 'free' && freeClosed) pointsLabel.textContent = tr('route.route_closed')
                else pointsLabel.textContent = points.length ? trn('route.point.one', 'route.point.other', points.length) : tr('route.tap_points')
            }
            if (subviewStatus) {
                subviewStatus.textContent = completedRoute ? tr(mode === 'circuit' ? 'route.closed' : 'route.route_closed') : ''
                subviewStatus.hidden = !completedRoute
            }
            if (routeStatus) routeStatus.hidden = completedRoute
            if (!activeUnitTarget && toggle) toggle.textContent = route ? (compactRouteEditor ? tr('common.edit') : tr('route.edit')) : (compactRouteEditor ? `+ ${tr('common.add')}` : tr('route.add'))
            toggle?.setAttribute('aria-expanded', workspace.hidden ? 'false' : 'true')
            if (mode === 'circuit' && circuitClosed) {
                if (lapSummary) lapSummary.textContent = `${trn('route.lap.one', 'route.lap.other', 1)} · ${formatMeters(baseDistance)}`
                if (totalOutput) totalOutput.textContent = formatMeters(distance)
            }
            syncModeUi()
            renderLayers()
            if (route) syncCalculatedField(['distancia'], distance)
            if (requestTerrain && route) requestElevation()
        }
        const initializeMap = () => {
            if (map || !mapElement || !window.L) return
            map = window.L.map(mapElement, {tap: true, zoomControl: true}).setView([-14.2, -51.9], 4)
            window.StrideBRBasemaps?.attach(map, {controls: true, remember: true})
            map.on('click', event => {
                if (mode === 'circuit' && circuitClosed && !circuitEditing) return
                const nextPoint = [event.latlng.lng, event.latlng.lat]
                if (mode === 'circuit' && circuitClosed && circuitEditing) {
                    const openBase = points.slice(0, -1)
                    const candidate = routeUtil.closeCircuit([...openBase, nextPoint])
                    const expanded = routeUtil.expandCircuit(candidate, laps)
                    if (!expanded.ok) { if (pointsLabel) pointsLabel.textContent = tr('route.point_limit', {laps}); return }
                    points = candidate
                } else {
                    if (mode === 'free' && freeClosed) { points = points.slice(0, -1); freeClosed = false }
                    if (points.length >= routeUtil.MAX_POINTS) {
                        if (pointsLabel) pointsLabel.textContent = tr(mode === 'circuit' ? 'route.base_point_limit' : 'route.free_point_limit', {count: routeUtil.MAX_POINTS})
                        return
                    }
                    points.push(nextPoint)
                }
                sync()
            })
            renderLayers()
            if (line) map.fitBounds(line.getBounds(), {padding: [24, 24], maxZoom: 17})
            invalidateRouteMap()
            if (window.ResizeObserver && !routeResizeObserver) {
                routeResizeObserver = new ResizeObserver(invalidateRouteMap)
                routeResizeObserver.observe(workspace)
                routeResizeObserver.observe(mapElement)
            }
        }
        const setRouteSubview = active => {
            if (!compactRouteEditor || !form || !shell) return
            form.classList.toggle('is-route-subview', Boolean(active))
            shell.classList.toggle('is-route-subview', Boolean(active))
            editor.classList.toggle('is-route-subview', Boolean(active))
            window.dispatchEvent(new CustomEvent('stridebr:activity-route-subview', {detail: {active: Boolean(active)}}))
            if (active) {
                document.documentElement.classList.add('activity-route-subview-open')
                window.requestAnimationFrame(() => window.requestAnimationFrame(invalidateRouteMap))
            } else document.documentElement.classList.remove('activity-route-subview-open')
        }
        const leaveRouteSubview = ({focusToggle = true} = {}) => {
            const unitTarget = activeUnitTarget
            const focusTarget = returnFocusElement
            if (unitTarget) updateUnitRouteSummary(unitTarget)
            workspace.hidden = true
            toggle?.setAttribute('aria-expanded', 'false')
            setRouteSubview(false)
            if (unitTarget) {
                useGeneralRouteTarget()
                readState()
                sync(false)
                updateRouteAvailability()
                window.requestAnimationFrame(() => {
                    if (form) form.scrollTop = routeSubviewScrollTop
                    if (focusToggle) focusTarget?.focus?.({preventScroll: true})
                })
                return
            }
            if (focusToggle) toggle?.focus?.({preventScroll:true})
        }
        const open = async () => {
            if (compactRouteEditor) setRouteSubview(true)
            workspace.hidden = false
            toggle?.setAttribute('aria-expanded', 'true')
            syncModeUi()
            renderLayers()
            if (pointsLabel && mode === 'circuit' && circuitClosed) pointsLabel.textContent = tr(circuitEditing ? 'route.editing_base' : 'route.closed')
            try {
                await ensureLeaflet()
                initializeMap()
                if (line) window.setTimeout(() => map?.fitBounds(line.getBounds(), {padding: [24, 24], maxZoom: 17}), 10)
                invalidateRouteMap()
                window.setTimeout(invalidateRouteMap, 50)
            } catch (_) {
                if (routeStatus) routeStatus.hidden = false
                if (pointsLabel) pointsLabel.textContent = tr('route.map_unavailable')
            }
        }
        const openUnitTarget = async target => {
            if (!useUnitRouteTarget(target)) return
            routeSubviewScrollTop = form?.scrollTop || 0
            editor.hidden = false
            readState()
            sync(false)
            await open()
        }
        const reload = () => {
            if (activeUnitTarget) return
            readState()
            sync(false)
            if (map) {
                renderLayers()
                if (line) window.setTimeout(() => map.fitBounds(line.getBounds(), {padding: [24, 24], maxZoom: 17}), 20)
            }
        }
        readState()
        toggle?.addEventListener('click', () => {
            if (activeUnitTarget) useGeneralRouteTarget()
            readState()
            sync(false)
            open()
        })
        editor.addEventListener('activity:route-open-target', event => openUnitTarget(event.detail?.target))
        modeButtons.forEach(button => button.addEventListener('click', () => {
            const next = button.dataset.routeMode === 'circuit' ? 'circuit' : 'free'
            if (next === mode) return
            if (next === 'free' && mode === 'circuit' && circuitClosed) points = finalPoints()
            mode = next
            circuitEditing = false
            freeClosed = false
            if (next === 'circuit') {
                const detected = routeUtil.detectCircuit(points)
                if (detected) { points = normalize(detected.base); laps = detected.laps; circuitClosed = true }
                else { laps = 1; circuitClosed = isClosed(points) }
            } else { laps = 1; circuitClosed = false; freeClosed = isClosed(points) }
            elevationRequest += 1
            sync()
        }))
        freeCloseButton?.addEventListener('click', () => {
            if (mode !== 'free' || points.length < 3) { if (pointsLabel) pointsLabel.textContent = tr('route.need_three'); return }
            points = routeUtil.closeCircuit(points)
            freeClosed = true
            circuitClosed = false
            laps = 1
            elevationRequest += 1
            sync()
        })
        closeButton?.addEventListener('click', () => {
            if (points.length < 3) { if (pointsLabel) pointsLabel.textContent = tr('route.need_three'); return }
            const candidate = routeUtil.closeCircuit(points)
            const expanded = routeUtil.expandCircuit(candidate, laps)
            if (!expanded.ok) { if (pointsLabel) pointsLabel.textContent = tr('route.point_limit', {laps}); return }
            points = candidate
            circuitClosed = true
            circuitEditing = false
            sync()
        })
        editBaseButton?.addEventListener('click', () => {
            if (mode !== 'circuit' || !circuitClosed) return
            circuitEditing = !circuitEditing
            sync(false)
        })
        editor.querySelector('[data-route-laps-dec]')?.addEventListener('click', () => { if (laps > 1) { laps -= 1; sync() } })
        editor.querySelector('[data-route-laps-inc]')?.addEventListener('click', () => {
            const next = laps + 1
            const result = routeUtil.expandCircuit(points, next)
            if (!result.ok) { if (pointsLabel) pointsLabel.textContent = tr('route.point_limit', {laps: next}); return }
            laps = next; sync()
        })
        const finishRouteEditor = () => {
            if (mode === 'circuit' && points.length && !circuitClosed) { if (pointsLabel) pointsLabel.textContent = tr('route.need_three'); return false }
            if (!finalGeojson()) { if (pointsLabel) pointsLabel.textContent = tr('route.need_two'); return false }
            if ((activeUnitTarget || editor).dataset.routeInvalid === '1') return false
            if (mode === 'circuit' && circuitClosed && circuitEditing) {
                circuitEditing = false
                syncModeUi()
                renderLayers()
                if (pointsLabel) pointsLabel.textContent = tr('route.closed')
            }
            leaveRouteSubview()
            return true
        }
        editor.querySelector('[data-route-done]')?.addEventListener('click', finishRouteEditor)
        subviewDone?.addEventListener('click', finishRouteEditor)
        subviewBack?.addEventListener('click', () => leaveRouteSubview())
        editor.querySelector('[data-route-undo]')?.addEventListener('click', () => {
            if (mode === 'circuit' && circuitClosed && circuitEditing) {
                const openBase = points.slice(0, -1)
                openBase.pop()
                if (openBase.length >= 3) points = routeUtil.closeCircuit(openBase)
                else { points = openBase; circuitClosed = false; circuitEditing = false }
            } else if (mode === 'circuit' && circuitClosed) {
                points = points.slice(0, -1)
                circuitClosed = false
                circuitEditing = false
            } else if (mode === 'free' && freeClosed) {
                points = points.slice(0, -1)
                freeClosed = false
            } else points.pop()
            elevationRequest += 1
            sync()
        })
        editor.querySelector('[data-route-clear]')?.addEventListener('click', () => {
            points = []; laps = 1; circuitClosed = false; freeClosed = false; circuitEditing = false; latestElevationGain = null; elevationRequest += 1; resetMetrics(); sync(false)
            if (elevationLabel) elevationLabel.textContent = tr('route.estimated_elevation_after')
        })
        editor.querySelector('[data-route-locate]')?.addEventListener('click', () => {
            if (!navigator.geolocation) { if (routeStatus) routeStatus.hidden = false; if (pointsLabel) pointsLabel.textContent = tr('route.location_unavailable'); return }
            if (routeStatus) routeStatus.hidden = false
            if (pointsLabel) pointsLabel.textContent = tr('route.location_searching')
            navigator.geolocation.getCurrentPosition(
                position => { open(); map?.setView([position.coords.latitude, position.coords.longitude], 15); if (pointsLabel) pointsLabel.textContent = tr('route.location_centered') },
                () => { if (pointsLabel) pointsLabel.textContent = tr('route.location_failed') },
                {enableHighAccuracy: true, timeout: 10000, maximumAge: 60000}
            )
        })
        window.addEventListener('resize', invalidateRouteMap, {passive: true})
        window.addEventListener('orientationchange', invalidateRouteMap, {passive: true})
        window.addEventListener('message', event => {
            if (event.origin !== window.location.origin || event.data?.type !== 'stridebr:activity-route-parent-resized') return
            window.requestAnimationFrame(() => window.requestAnimationFrame(invalidateRouteMap))
        })
        form?.addEventListener('stridebr:draft-restored', reload)
        editor.addEventListener('activity:route-reload', reload)
        editor.addEventListener('activity:route-disabled', () => {
            if (activeUnitTarget) return
            points = []; laps = 1; circuitClosed = false; freeClosed = false; circuitEditing = false; elevationRequest += 1; sync(false); workspace.hidden = true; setRouteSubview(false)
        })
        sync(false)
        if (!workspace.hidden) open()
    }

    bindRouteEditor(routeEditor)
    if (routePrivacyFields && routeEditor?.dataset.routeHasPoints !== '1') routePrivacyFields.hidden = true

    function syncUnitRouteRow(editor) {
        if (!editor) return
        const routeUtil = window.StrideBRRoute
        const hidden = editor.querySelector('[data-unit-route-value]')
        let meters = 0
        try {
            const parsed = JSON.parse(String(hidden?.value || ''))
            const coords = routeUtil?.normalizePoints(parsed?.type === 'LineString' ? parsed.coordinates : []) || []
            if (coords.length >= 2) meters = routeUtil.distanceMeters(coords)
        } catch (_) {}
        const storedDistance = Number(editor.querySelector('[data-unit-route-metric="distancia_metros"]')?.value || 0)
        if ((!Number.isFinite(meters) || meters <= 0) && Number.isFinite(storedDistance)) meters = storedDistance
        const gain = Number(editor.querySelector('[data-unit-route-metric="ganho_elevacao_m"]')?.value || editor.dataset.routeGainM || 0)
        const hasRoute = Number.isFinite(meters) && meters > 0
        editor.dataset.routeDistanceM = hasRoute ? String(meters) : ''
        editor.dataset.routeHasPoints = hasRoute ? '1' : '0'
        editor.classList.toggle('has-route', hasRoute)
        if (Number.isFinite(gain) && gain > 0) editor.dataset.routeGainM = String(gain)
        const summary = editor.querySelector('[data-unit-route-summary]')
        const action = editor.querySelector('[data-unit-route-open]')
        const formatted = hasRoute ? (meters >= 1000 ? `${i18n.number(meters / 1000, 2, false)} km` : `${i18n.number(Math.round(meters), 0)} m`) : ''
        if (summary) summary.textContent = hasRoute ? `${formatted}${Number.isFinite(gain) && gain > 0 ? ` · +${i18n.number(Math.round(gain), 0)} m` : ''}` : tr('common.optional')
        if (action) action.textContent = hasRoute ? tr('route.edit') : tr('route.add')
    }

    function bindUnitRouteEditors(scope = document) {
        scope.querySelectorAll?.('[data-unit-route-editor]').forEach(editor => {
            if (editor.dataset.unitRouteBound === '1') { syncUnitRouteRow(editor); return }
            editor.dataset.unitRouteBound = '1'
            const context = unitContextRoot(editor)
            editor.querySelector('[data-unit-route-open]')?.addEventListener('click', () => {
                if (!routeEditor || editor.hidden || editor.querySelector('[data-unit-route-value]')?.disabled) return
                routeEditor.dispatchEvent(new CustomEvent('activity:route-open-target', {detail: {target: editor}}))
            })
            editor.addEventListener('activity:unit-route-reload', () => syncUnitRouteRow(editor))
            const distanceInput = context?.querySelector('[data-segment-distance]') || findUnitField(context, 'distancia')?.querySelector('input[type="number"], input[type="text"]')
            distanceInput?.addEventListener('input', event => {
                if (!event.isTrusted) return
                if (String(distanceInput.value || '').trim() === '') delete distanceInput.dataset.routeAutoFilled
                else distanceInput.dataset.routeAutoFilled = '0'
            })
            const elevationInput = context?.querySelector('[data-segment-elevation]') || (findUnitField(context, 'elevacao') || findUnitField(context, 'desnivel'))?.querySelector('input[type="number"], input[type="text"]')
            elevationInput?.addEventListener('input', event => {
                if (!event.isTrusted) return
                if (String(elevationInput.value || '').trim() === '') delete elevationInput.dataset.routeAutoFilled
                else elevationInput.dataset.routeAutoFilled = '0'
            })
            syncUnitRouteRow(editor)
        })
    }

    bindUnitRouteEditors(document)

    if (form && form.dataset.routeValidationBound !== '1') {
        form.dataset.routeValidationBound = '1'
        form.addEventListener('submit', event => {
            const invalid = form.querySelector('[data-route-invalid="1"]')
            if (!invalid) return
            event.preventDefault()
            const generalWorkspace = invalid.querySelector('[data-route-workspace]')
            if (generalWorkspace) { generalWorkspace.hidden = false; invalid.querySelector('[data-route-toggle]')?.setAttribute('aria-expanded', 'true') }
            if (invalid.matches('[data-unit-route-editor]')) invalid.querySelector('[data-unit-route-open]')?.click()
            const message = invalid.querySelector('[data-route-points], [data-unit-route-status]')
            if (message && !message.textContent.trim()) message.textContent = tr('route.need_three')
            message?.scrollIntoView({block: 'center', behavior: 'smooth'})
        })
    }

    const effortSelector = document.querySelector('[data-effort-selector]')
    const effortRange = effortSelector?.querySelector('[data-effort-range]')
    const effortValue = effortSelector?.querySelector('[data-effort-value]')
    const effortOutput = effortSelector?.querySelector('[data-effort-output]')
    const effortClear = effortSelector?.querySelector('[data-clear-effort]')
    const syncEffortUI = () => {
        const value = String(effortValue?.value || '')
        if (value && effortRange) effortRange.value = value
        if (effortOutput) effortOutput.textContent = value || '—'
        if (effortClear) effortClear.hidden = !value
        effortSelector?.classList.toggle('has-value', Boolean(value))
    }
    effortRange?.addEventListener('input', () => {
        if (effortValue) effortValue.value = effortRange.value
        syncEffortUI()
        const panel = document.querySelector('[data-model-panel]:not([hidden])')
        updateSummary(panel)
    })
    effortClear?.addEventListener('click', () => {
        if (effortValue) effortValue.value = ''
        if (effortRange) effortRange.value = '5'
        syncEffortUI()
        const panel = document.querySelector('[data-model-panel]:not([hidden])')
        updateSummary(panel)
    })

    form?.addEventListener('stridebr:draft-restored', () => {
        durationPrecisionWasManuallyChanged = false
        if (Array.from(form.querySelectorAll('[data-duration-milliseconds]')).some(input => Number.parseInt(String(input.value || '0'), 10) > 0)) setDurationPrecision(true, {ignoreManual:true})
        syncEffortUI()
        updateModelsForModality()
        initializeSegmentPanels(document)
        const activePanel = document.querySelector('[data-model-panel]:not([hidden])')
        updateDerivedMetric(activePanel)
        activePanel?.querySelectorAll('[data-unit-index]').forEach(updateUnitDerivedMetric)
        updateOptionalChips(activePanel)
        updateSummary(activePanel)
        syncActivityTitlePresentation()
        syncLogDetailButtons()
    })

    form?.addEventListener('change', (event) => {
        if (event.target.matches('[data-activity-equipment-host] input[type="checkbox"]')) syncLogDetailButtons()
    })

    document.addEventListener('focusout', (event) => {
        if (!event.target.matches('[data-derived-input]') || event.target.readOnly) return
        const field = event.target.closest('[data-derived-field]')
        if (event.relatedTarget === field?.querySelector('[data-edit-derived]')) return
        calculateFromDerived(field?.closest('[data-model-panel]'))
        event.target.readOnly = true
        event.target.setAttribute('aria-readonly', 'true')
        field?.querySelector('[data-edit-derived]')?.replaceChildren(document.createTextNode(tr('common.edit')))
    })

    form?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return
        if (event.target.matches('textarea, button, [data-sport-search], [data-derived-input], [data-clock-hours], [data-clock-minutes], [data-duration-hours], [data-duration-minutes], [data-duration-seconds]')) return
        event.preventDefault()
    })

    form?.addEventListener('submit', async (event) => {
        document.querySelectorAll('[data-clock-field]').forEach((field) => field.querySelector('[data-clock-minutes]')?.dispatchEvent(new Event('blur')))
        document.querySelectorAll('[data-duration-field]').forEach(syncDuration)
        const hour = form.querySelector('[data-clock-value]')?.value
        if (!hour) {
            event.preventDefault()
            form.querySelector('[data-clock-hours]')?.focus()
            return
        }
        if (event.defaultPrevented) return
        const restoreDistancePresentation = normalizeDistanceFieldsForSubmission()
        if (!page || !window.fetch) return
        event.preventDefault()
        const requestBody = new FormData(form)
        restoreDistancePresentation()
        const submit = form.querySelector('button[type="submit"]')
        if (submit) {
            submit.disabled = true
            submit.dataset.originalText ||= submit.textContent || tr('activity.save')
            submit.textContent = tr('common.saving')
        }
        form.classList.add('is-submitting')
        form.setAttribute('aria-busy', 'true')
        try {
            const request = window.StrideBRNet?.fetch || fetch
            const response = await request(form.action || window.location.href, {
                method: 'POST',
                body: requestBody,
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                credentials: 'same-origin'
            }, 18000)
            if (response.redirected && new URL(response.url, window.location.href).pathname.includes('/login')) throw new Error(tr('activity.session_expired'))
            const data = await response.json().catch(() => null)
            if (!response.ok || !data?.ok || !data?.id) throw new Error(data?.error || tr('activity.save_error'))
            const savedId = String(data.id)
            form.dispatchEvent(new CustomEvent('stridebr:draft-clear'))
            closeEditor()
            clearHistoryCache()
            showActivityToast(tr('activity.saved_success'), 'success')
            const historyRefresh = loadHistory({background: true, preserveDetail: true}).then(() => highlightImportedActivities([savedId]))
            const postSavePreview = openPostSaveShare(savedId)
            await Promise.allSettled([historyRefresh, postSavePreview])
            form.reset()
            if (effortValue) effortValue.value = ''
            if (effortRange) effortRange.value = '5'
            syncEffortUI()
            const routeValue = form.querySelector('[data-route-value]')
            if (routeValue) routeValue.value = ''
            routeEditor?.dispatchEvent(new CustomEvent('activity:route-reload'))
            if (routePrivacyFields) routePrivacyFields.hidden = true
            strengthExercisesHost?.replaceChildren()
            if (strengthEmpty) strengthEmpty.hidden = false
            reindexStrengthEditor()
            const now = new Date()
            const dateValue = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
            const timeValue = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
            if (activityDateInput) activityDateInput.value = dateValue
            const clockHours = form.querySelector('[data-clock-hours]')
            const clockMinutes = form.querySelector('[data-clock-minutes]')
            const clockValue = form.querySelector('[data-clock-value]')
            if (clockHours) clockHours.value = String(now.getHours()).padStart(2, '0')
            if (clockMinutes) clockMinutes.value = String(now.getMinutes()).padStart(2, '0')
            if (clockValue) clockValue.value = timeValue
            modalitySelect?.dispatchEvent(new Event('change', {bubbles: true}))
            syncAutomaticActivityTitle()
        } catch (error) {
            showActivityToast(error?.message || tr('activity.save_error'), 'error')
        } finally {
            if (submit) {
                submit.disabled = false
                submit.textContent = submit.dataset.originalText || tr('activity.save')
            }
            form.classList.remove('is-submitting')
            form.removeAttribute('aria-busy')
        }
    })

    const readRouteData = (scope) => {
        try { return JSON.parse(scope?.querySelector('[data-route-data]')?.textContent || '') } catch (_) { return null }
    }

    const getUiColor = (token, fallback) => getComputedStyle(document.documentElement).getPropertyValue(token).trim() || fallback

    const drawElevationProfile = (svg, profile) => {
        if (!svg || !Array.isArray(profile) || profile.length < 2) return
        const width = 640
        const height = 150
        const pad = 8
        const distances = profile.map((point) => Number(point.distancia_m)).filter(Number.isFinite)
        const elevations = profile.map((point) => Number(point.elevacao_m)).filter(Number.isFinite)
        if (distances.length !== profile.length || elevations.length !== profile.length) return
        const maxDistance = Math.max(...distances, 1)
        const minElevation = Math.min(...elevations)
        const maxElevation = Math.max(...elevations)
        const elevationRange = Math.max(maxElevation - minElevation, 1)
        const points = profile.map((point) => {
            const x = pad + (Number(point.distancia_m) / maxDistance) * (width - pad * 2)
            const y = height - pad - ((Number(point.elevacao_m) - minElevation) / elevationRange) * (height - pad * 2)
            return `${x.toFixed(1)},${y.toFixed(1)}`
        }).join(' ')
        const routeColor = getUiColor('--ui-route', '#4f72df')
        svg.innerHTML = `<defs><linearGradient id="elevation-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${routeColor}" stop-opacity=".28"/><stop offset="1" stop-color="${routeColor}" stop-opacity=".025"/></linearGradient></defs><polygon points="${pad},${height - pad} ${points} ${width - pad},${height - pad}" fill="url(#elevation-fill)"/><polyline points="${points}" fill="none" stroke="${routeColor}" stroke-width="3" vector-effect="non-scaling-stroke"/>`
    }

    const waitForVisibleRouteBox = async element => {
        if (!element) return false
        for (let attempt = 0; attempt < 8; attempt += 1) {
            const rect = element.getBoundingClientRect()
            if (rect.width > 80 && rect.height > 80) return true
            await new Promise(resolve => requestAnimationFrame(resolve))
        }
        return false
    }

    const initializeDetailRoute = async (drawer) => {
        const section = drawer?.querySelector('[data-activity-route]')
        if (!section) return
        const data = readRouteData(section)
        const coordinates = data?.geojson?.coordinates
        const elevationElement = section.querySelector('[data-elevation-profile]')
        drawElevationProfile(elevationElement, data?.perfil)
        const mapElement = section.querySelector('[data-detail-route-map]')
        if (!mapElement || !Array.isArray(coordinates) || coordinates.length < 2) return
        await waitForVisibleRouteBox(mapElement)
        mapElement.classList.add('is-loading')
        mapElement.textContent = tr('activity.map_loading')
        try {
            await ensureLeaflet()
        } catch (_) {
            mapElement.replaceChildren()
            mapElement.classList.remove('is-loading')
            mapElement.classList.add('is-unavailable')
            mapElement.textContent = tr('activity.map_unavailable_now')
            return
        }
        mapElement.replaceChildren()
        mapElement.classList.remove('is-loading')
        if (!section._routeMap) {
            section._routeMap = window.L.map(mapElement, {scrollWheelZoom: false})
            window.StrideBRBasemaps?.attach(section._routeMap, {initial: 'street', remember: false})
            const latLngs = coordinates.map(([longitude, latitude]) => [latitude, longitude])
            section._routeBounds = window.L.latLngBounds(latLngs)
            window.L.polyline(latLngs, {color: getUiColor('--ui-route', '#4f72df'), weight: 5, lineCap: 'round', lineJoin: 'round'}).addTo(section._routeMap)
            section._routeMap.fitBounds(section._routeBounds, {padding: [24, 24], maxZoom: 16, animate: false})
        }
        const refreshRouteLayout = () => {
            try {
                section._routeMap?.invalidateSize({pan: false})
                if (section._routeMap && section._routeBounds) section._routeMap.fitBounds(section._routeBounds, {padding: [24, 24], maxZoom: 16, animate: false})
            } catch (_) {}
            drawElevationProfile(elevationElement, data?.perfil)
        }
        if (!section._routeBounds) {
            const latLngs = coordinates.map(([longitude, latitude]) => [latitude, longitude])
            section._routeBounds = window.L.latLngBounds(latLngs)
        }
        requestAnimationFrame(refreshRouteLayout)
        window.setTimeout(refreshRouteLayout, 80)
        window.setTimeout(refreshRouteLayout, 260)
        if (!section._routeResizeObserver && window.ResizeObserver) {
            section._routeResizeObserver = new ResizeObserver(refreshRouteLayout)
            section._routeResizeObserver.observe(mapElement)
        }
    }

    const postSaveModal = document.querySelector('[data-post-save-share]')
    const postSaveCanvas = postSaveModal?.querySelector('[data-post-save-canvas]')
    const postSaveTitle = postSaveModal?.querySelector('[data-post-save-title]')
    const postSaveSummary = postSaveModal?.querySelector('[data-post-save-summary]')
    const postSaveStatus = postSaveModal?.querySelector('[data-post-save-status]')
    const postSaveNative = postSaveModal?.querySelector('[data-post-save-native]')
    const postSaveEdit = postSaveModal?.querySelector('[data-post-save-edit]')
    const postSaveDelete = postSaveModal?.querySelector('[data-post-save-delete]')
    let postSaveActivityId = ''
    let postSaveShareData = null
    let postSaveSharePreference = null

    const shareModal = document.querySelector('[data-share-modal]')
    const shareCanvas = shareModal?.querySelector('[data-share-canvas]')
    const shareFormatInputs = Array.from(shareModal?.querySelectorAll('[data-share-format]') || [])
    const shareCompositionInputs = Array.from(shareModal?.querySelectorAll('[data-share-composition]') || [])
    const shareCompositionOptions = shareModal?.querySelector('[data-share-composition-options]')
    const shareColorInputs = Array.from(shareModal?.querySelectorAll('[data-share-background-color]') || [])
    const shareColorOptions = shareModal?.querySelector('[data-share-color-options]')
    const shareMapOption = shareModal?.querySelector('[data-share-map-option]')
    const shareMapToggle = shareModal?.querySelector('[data-share-map-toggle]')
    const shareMapStyleOptions = shareModal?.querySelector('[data-share-map-style-options]')
    const shareMapStyleInputs = Array.from(shareModal?.querySelectorAll('[data-share-map-style]') || [])
    const shareRouteScaleInput = shareModal?.querySelector('[data-share-route-scale]')
    const shareRouteScaleValue = shareModal?.querySelector('[data-share-route-scale-value]')
    const sharePreviewShell = shareModal?.querySelector('[data-share-preview-shell]')
    const sharePreviewFormat = shareModal?.querySelector('[data-share-preview-format]')
    const sharePhotoField = shareModal?.querySelector('[data-share-photo-field]')
    const sharePhotoInput = shareModal?.querySelector('[data-share-photo]')
    const shareCameraInput = shareModal?.querySelector('[data-share-camera]')
    const sharePhotoName = shareModal?.querySelector('[data-share-photo-name]')
    const sharePhotoPreview = shareModal?.querySelector('[data-share-photo-preview]')
    const sharePhotoEmpty = shareModal?.querySelector('[data-share-photo-empty]')
    const shareMetricOptions = shareModal?.querySelector('[data-share-metric-options]')
    const shareCaption = shareModal?.querySelector('[data-share-caption]')
    const shareStatus = shareModal?.querySelector('[data-share-status]')
    const shareStyleGrid = shareModal?.querySelector('[data-share-style-grid]')
    const shareContentGrid = shareModal?.querySelector('[data-share-content-grid]')
    const shareSessionLayoutGrid = shareModal?.querySelector('[data-share-session-layout-grid]')
    const shareMasterSwitch = shareModal?.querySelector('[data-share-master-switch]')
    const shareScopeButtons = Array.from(shareModal?.querySelectorAll('[data-share-scope]') || [])
    const shareSingleSegmentPicker = shareModal?.querySelector('[data-share-single-segment-picker]')
    const shareSingleSegmentList = shareModal?.querySelector('[data-share-single-segment-list]')
    const shareMultipleSummary = shareModal?.querySelector('[data-share-multiple-summary]')
    const shareSegmentSelectionSummary = shareModal?.querySelector('[data-share-segment-selection-summary]')
    const shareSegmentSelectionInline = shareModal?.querySelector('[data-share-segment-selection-inline]')
    const shareEditSegmentsButton = shareModal?.querySelector('[data-share-edit-segments]')
    const shareSegmentDisclosure = shareModal?.querySelector('[data-share-segment-disclosure]')
    const shareSelectAllButton = shareModal?.querySelector('[data-share-select-all]')
    const shareComparisonControls = shareModal?.querySelector('[data-share-comparison-controls]')
    const shareComparisonMetric = shareModal?.querySelector('[data-share-comparison-metric]')
    const shareComparisonReference = shareModal?.querySelector('[data-share-comparison-reference]')
    const shareSessionRouteOption = shareModal?.querySelector('[data-share-session-route-option]')
    const shareActivityRouteToggle = shareModal?.querySelector('.activity-share-quick-controls [data-share-show="route"]')
    const shareSessionRouteToggle = shareSessionRouteOption?.querySelector('[data-share-show="route"]')
    const shareSessionCompactControls = shareModal?.querySelector('[data-share-session-compact-controls]')
    const shareSessionCompactPrimary = shareModal?.querySelector('[data-share-session-compact-primary]')
    const shareSessionCompactSecondary = shareModal?.querySelector('[data-share-session-compact-secondary]')
    const shareHeadingMode = shareModal?.querySelector('[data-share-heading-mode]')
    const shareSingleOnlySections = Array.from(shareModal?.querySelectorAll('[data-share-single-only]') || [])
    const shareSessionOnlySections = Array.from(shareModal?.querySelectorAll('[data-share-session-only]') || [])
    const shareCameraSheet = shareModal?.querySelector('[data-share-camera-sheet]')
    const shareCameraVideo = shareModal?.querySelector('[data-share-camera-video]')
    const shareCameraStatus = shareModal?.querySelector('[data-share-camera-status]')
    const shareCameraCaptureButton = shareModal?.querySelector('[data-share-capture-camera]')
    const shareCustomizeSheet = shareModal?.querySelector('[data-share-customize-sheet]')
    const shareMobileCustomizeButton = shareModal?.querySelector('[data-share-mobile-customize]')
    const shareMobilePhotoButton = shareModal?.querySelector('[data-share-mobile-photo]')
    const sharePreviewStage = shareModal?.querySelector('.activity-share-preview-stage')
    const shareContentOptions = shareModal?.querySelector('[data-share-content-options]')
    const shareContentModeInputs = Array.from(shareModal?.querySelectorAll('[data-share-content-mode]') || [])
    const shareSegmentModeOptions = shareModal?.querySelector('[data-share-segment-mode-options]')
    const shareSegmentModeInputs = Array.from(shareModal?.querySelectorAll('[data-share-segment-mode]') || [])
    const shareRouteChoice = shareModal?.querySelector('[data-share-route-choice]')
    const shareSegmentsGroup = shareModal?.querySelector('[data-share-segments-group]')
    const shareSegmentList = shareModal?.querySelector('[data-share-segment-list]')
    const shareSegmentPreviewPicker = shareModal?.querySelector('[data-share-segment-preview-picker]')
    const shareSegmentPreviewSelect = shareModal?.querySelector('[data-share-segment-preview]')
    const shareExportRouteButton = shareModal?.querySelector('[data-export-route-png]')
    const shareRouteExportSheet = shareModal?.querySelector('[data-route-export-sheet]')
    const shareRouteExportPreview = shareModal?.querySelector('[data-route-export-preview]')
    const shareRouteExportWidth = shareModal?.querySelector('[data-route-export-width]')
    const shareRouteExportWidthValue = shareModal?.querySelector('[data-route-export-width-value]')
    const shareRouteExportColors = Array.from(shareModal?.querySelectorAll('[data-route-export-color]') || [])
    const shareRouteExportConfirm = shareModal?.querySelector('[data-route-export-confirm]')
    const shareCopyButton = shareModal?.querySelector('[data-copy-share]')
    const shareDownloadButton = shareModal?.querySelector('[data-download-share]')
    const shareNativeButton = shareModal?.querySelector('[data-native-share]')
    const SHARE_PRESET_KEY = 'stridebr.share.defaultPreset'
    const SHARE_PRESET_COLORS_KEY = 'stridebr.share.presetColors'
    const SHARE_CONTENT_KEY = 'stridebr.share.contentPreset'
    const SHARE_SESSION_LAYOUT_KEY = 'stridebr.share.sessionLayout'
    const SHARE_HEADING_MODE_KEY = 'stridebr.share.headingMode'
    const SHARE_LAST_USED_KEY = 'stridebr.share.lastUsed.v1'
    const SHARE_MAP_STYLE_KEY = 'stridebr.share.mapStyle'
    const SHARE_PRESET_FALLBACK = 'stats'
    const SHARE_FORMATS = Object.freeze({
        story: Object.freeze({
            id: 'story',
            label: tr('activity.share.format_story'),
            width: 1080,
            height: 1920,
            anchors: Object.freeze({
                titleAnchor: Object.freeze({x: .5, y: .075}),
                contentStage: Object.freeze({x: .075, y: .15, width: .85, height: .69}),
                logoAnchor: Object.freeze({x: .5, y: .91}),
            }),
        }),
        portrait: Object.freeze({
            id: 'portrait',
            label: tr('activity.share.format_portrait'),
            width: 1080,
            height: 1350,
            anchors: Object.freeze({
                titleAnchor: Object.freeze({x: .5, y: .075}),
                contentStage: Object.freeze({x: .07, y: .16, width: .86, height: .69}),
                logoAnchor: Object.freeze({x: .5, y: .91}),
            }),
        }),
        square: Object.freeze({
            id: 'square',
            label: tr('activity.share.format_square'),
            width: 1080,
            height: 1080,
            anchors: Object.freeze({
                titleAnchor: Object.freeze({x: .5, y: .075}),
                contentStage: Object.freeze({x: .065, y: .16, width: .87, height: .68}),
                logoAnchor: Object.freeze({x: .5, y: .9}),
            }),
        }),
    })
    const SHARE_SCOPES = Object.freeze({
        activity: 'activity',
        singleSegment: 'single_segment',
        session: 'session',
        multipleSegments: 'multiple_segments',
    })
    const SHARE_SCOPE_ALIASES = Object.freeze({
        activity: SHARE_SCOPES.activity,
        segment: SHARE_SCOPES.singleSegment,
        single_segment: SHARE_SCOPES.singleSegment,
        summary: SHARE_SCOPES.session,
        session: SHARE_SCOPES.session,
        selection: SHARE_SCOPES.multipleSegments,
        multiple_segments: SHARE_SCOPES.multipleSegments,
    })
    const normalizeShareScope = value => SHARE_SCOPE_ALIASES[String(value || '')] || SHARE_SCOPES.activity
    const SHARE_BLUE_SERIES = Object.freeze(['#5f82ff', '#7896ff', '#4f72df', '#91a8f4', '#4265c9', '#a4b5ec'])
    const SHARE_COMPOSITION_REGISTRY = Object.freeze({
        standard: Object.freeze({
            id: 'standard', label: tr('activity.share.composition_standard'), family: 'activity',
            supportedScopes: Object.freeze([SHARE_SCOPES.activity, SHARE_SCOPES.singleSegment]),
            supportedFormats: Object.freeze(['story', 'portrait', 'square']),
            minSegments: 0, maxVisibleSegments: 1, requiresGeometry: false, minGeometryCount: 0, minComparableSegments: 0, supportsMap: true, supportsTitle: true,
            metricBehavior: 'activity',
        }),
        compact: Object.freeze({
            id: 'compact', label: tr('activity.share.composition_compact'), family: 'activity',
            supportedScopes: Object.freeze([SHARE_SCOPES.activity, SHARE_SCOPES.singleSegment]),
            supportedFormats: Object.freeze(['story', 'portrait', 'square']),
            minSegments: 0, maxVisibleSegments: 1, requiresGeometry: false, minGeometryCount: 0, minComparableSegments: 0, supportsMap: false, supportsTitle: false,
            layoutByFormat: Object.freeze({story: 'vertical', portrait: 'vertical', square: 'grid'}),
            metricBehavior: 'activity',
        }),
        session_overview: Object.freeze({
            id: 'session_overview', label: tr('activity.share.session_overview'), family: 'session',
            supportedScopes: Object.freeze([SHARE_SCOPES.session, SHARE_SCOPES.multipleSegments]), supportedFormats: Object.freeze(['story']),
            minSegments: 2, maxVisibleSegments: 6, requiresGeometry: true, minGeometryCount: 1, minComparableSegments: 0, supportsMap: true, supportsTitle: true, metricBehavior: 'session',
        }),
        session_by_segment: Object.freeze({
            id: 'session_by_segment', label: tr('activity.share.session_by_segment'), family: 'session',
            supportedScopes: Object.freeze([SHARE_SCOPES.session, SHARE_SCOPES.multipleSegments]), supportedFormats: Object.freeze(['story']),
            minSegments: 2, maxVisibleSegments: 5, requiresGeometry: true, minGeometryCount: 1, minComparableSegments: 0, supportsMap: false, supportsTitle: true, metricBehavior: 'segment_primary',
        }),
        session_comparison: Object.freeze({
            id: 'session_comparison', label: tr('activity.share.comparison'), family: 'session',
            supportedScopes: Object.freeze([SHARE_SCOPES.session, SHARE_SCOPES.multipleSegments]), supportedFormats: Object.freeze(['story']),
            minSegments: 2, maxVisibleSegments: 6, requiresGeometry: false, minGeometryCount: 0, minComparableSegments: 2, supportsMap: false, supportsTitle: true, metricBehavior: 'comparison',
        }),
        session_highlight: Object.freeze({
            id: 'session_highlight', label: tr('activity.share.highlight'), family: 'session',
            supportedScopes: Object.freeze([SHARE_SCOPES.session, SHARE_SCOPES.multipleSegments]), supportedFormats: Object.freeze(['story']),
            minSegments: 2, maxVisibleSegments: 4, requiresGeometry: false, minGeometryCount: 0, minComparableSegments: 2, supportsMap: false, supportsTitle: true, metricBehavior: 'highlight', supportsRouteToggle: true,
        }),
        session_sequence: Object.freeze({
            id: 'session_sequence', label: tr('activity.share.sequence'), family: 'session',
            supportedScopes: Object.freeze([SHARE_SCOPES.session, SHARE_SCOPES.multipleSegments]), supportedFormats: Object.freeze(['story']),
            minSegments: 2, maxVisibleSegments: 6, requiresGeometry: false, minGeometryCount: 0, minComparableSegments: 0, supportsMap: false, supportsTitle: true, metricBehavior: 'sequence', supportsRouteToggle: true,
        }),
        session_summary: Object.freeze({
            id: 'session_summary', label: tr('activity.share.session_summary'), family: 'session',
            supportedScopes: Object.freeze([SHARE_SCOPES.session, SHARE_SCOPES.multipleSegments]), supportedFormats: Object.freeze(['story']),
            minSegments: 2, maxVisibleSegments: null, requiresGeometry: false, minGeometryCount: 0, minComparableSegments: 0, supportsMap: false, supportsTitle: true, metricBehavior: 'session',
        }),
        session_list: Object.freeze({
            id: 'session_list', label: tr('activity.share.list'), family: 'session',
            supportedScopes: Object.freeze([SHARE_SCOPES.session, SHARE_SCOPES.multipleSegments]), supportedFormats: Object.freeze(['story']),
            minSegments: 2, maxVisibleSegments: 10, requiresGeometry: false, minGeometryCount: 0, minComparableSegments: 0, supportsMap: false, supportsTitle: true, metricBehavior: 'segment_primary',
        }),
        session_minimal: Object.freeze({
            id: 'session_minimal', label: tr('activity.share.minimal'), family: 'session',
            supportedScopes: Object.freeze([SHARE_SCOPES.session, SHARE_SCOPES.multipleSegments]), supportedFormats: Object.freeze(['story']),
            minSegments: 2, maxVisibleSegments: null, requiresGeometry: false, minGeometryCount: 0, minComparableSegments: 0, supportsMap: false, supportsTitle: true, metricBehavior: 'minimal',
        }),
        session_compact: Object.freeze({
            id: 'session_compact', label: tr('activity.share.composition_compact'), family: 'session',
            supportedScopes: Object.freeze([SHARE_SCOPES.session, SHARE_SCOPES.multipleSegments]), supportedFormats: Object.freeze(['story']),
            minSegments: 2, maxVisibleSegments: 5, requiresGeometry: false, minGeometryCount: 0, minComparableSegments: 0, supportsMap: false, supportsTitle: false, metricBehavior: 'compact_session',
        }),
    })
    const shareCompositionCompatibility = (compositionId, context = {}) => {
        const definition = SHARE_COMPOSITION_REGISTRY[String(compositionId || '')]
        if (!definition) return false
        const scope = normalizeShareScope(context.scope)
        const format = SHARE_FORMATS[String(context.format || '')]?.id || 'story'
        const segmentCount = Math.max(0, Number(context.segmentCount) || 0)
        const geometryCount = Math.max(0, Number(context.geometryCount) || 0)
        if (!definition.supportedScopes.includes(scope) || !definition.supportedFormats.includes(format)) return false
        if (segmentCount < definition.minSegments) return false
        const minGeometryCount = Math.max(Number(definition.minGeometryCount) || 0, definition.requiresGeometry ? 1 : 0)
        if (geometryCount < minGeometryCount) return false
        const minComparableSegments = Math.max(0, Number(definition.minComparableSegments) || 0)
        if (minComparableSegments > 0 && Math.max(0, Number(context.comparableSegmentCount) || 0) < minComparableSegments) return false
        if (context.requiresMap && !definition.supportsMap) return false
        if (context.requiresTitle && !definition.supportsTitle) return false
        return true
    }
    const compatibleShareCompositions = context => Object.values(SHARE_COMPOSITION_REGISTRY).filter(definition => shareCompositionCompatibility(definition.id, context))
    const SHARE_CONTENT_PRESETS = Object.freeze([
        {id: 'route', label: tr('activity.share.route'), hint: tr('activity.share.route_hint'), requiresRoute: true},
        {id: 'sport', label: tr('activity.share.sport'), hint: tr('activity.share.sport_hint')},
        {id: 'none', label: tr('activity.share.no_element'), hint: tr('activity.share.no_element_hint')},
    ])
    const SHARE_CARD_PRESETS = Object.freeze([
        {id: 'stats', label: tr('activity.share.background_color_mode', {}, 'Cor'), hint: tr('activity.share.background_color_hint', {}, 'Fundo simples'), mode: 'stats', showMapBase: false},
        {id: 'map', label: tr('activity.share.map'), hint: tr('activity.share.map_hint'), mode: 'map', showMapBase: true, requiresRoute: true},
        {id: 'photo', label: tr('activity.share.photo'), hint: tr('activity.share.photo_hint', {}, 'Sua foto como fundo'), mode: 'photo', showMapBase: false},
        {id: 'transparent', label: tr('activity.share.transparent', {}, 'Transparente'), hint: tr('activity.share.transparent_hint', {}, 'PNG sem fundo'), mode: 'transparent', showMapBase: false},
    ])
    const SHARE_PREVIEW_ROUTE = Object.freeze({
        titulo: 'Corrida em Nova York',
        modalidade: 'Corrida',
        data: '',
        ganho_m: 112,
        metricas: [{rotulo: tr('activity.share.distance'), valor: i18n.locale === 'en' ? '12.8 km' : '12,8 km'}, {rotulo: tr('activity.share.duration'), valor: '01:04:12'}, {rotulo: tr('activity.share.pace'), valor: '5:01/km'}, {rotulo: tr('activity.share.elevation'), valor: '112 m'}],
        geojson: {coordinates: [
            [-73.9549, 40.7851], [-73.9688, 40.7811], [-73.9817, 40.7748], [-73.9906, 40.7640],
            [-74.0056, 40.7517], [-74.0128, 40.7357], [-74.0105, 40.7198], [-73.9982, 40.7084],
            [-73.9855, 40.7005], [-73.9740, 40.7062], [-73.9662, 40.7185], [-73.9594, 40.7324],
            [-73.9500, 40.7440], [-73.9435, 40.7580], [-73.9480, 40.7702], [-73.9549, 40.7851]
        ]}
    })
    let shareData = null
    let sharePhoto = null
    let sharePreviewPhoto = null
    let shareRenderPromise = Promise.resolve()
    let shareRenderToken = 0
    let sharePreviewRenderQueued = false
    let sharePreviewFingerprint = ''
    let sharePreviewIdleHandle = 0
    let shareRouteScaleFrame = 0
    let shareCameraStream = null
    let shareRouteExportSets = []
    let activeSharePresetId = SHARE_PRESET_FALLBACK
    let lastNonMapSharePresetId = SHARE_PRESET_FALLBACK
    let activeShareContentId = 'route'
    let activeShareCompositionId = 'standard'
    const SHARE_BACKGROUND_COLORS = Object.freeze({
        deep: Object.freeze({id: 'deep', base: '#132243', stops: Object.freeze(['#101f3d', '#132243', '#0f1c35'])}),
        dark: Object.freeze({id: 'dark', base: '#0B1324', stops: Object.freeze(['#08101f', '#0B1324', '#070e1b'])}),
    })
    const normalizeShareBackgroundColor = value => {
        const id = String(value || '')
        if (id === 'dark' || id === 'black') return 'dark'
        return 'deep'
    }
    const shareDefaultPresetColors = Object.freeze({stats: 'deep', map: 'deep', photo: 'deep', transparent: 'deep'})
    let sharePresetColors = {...shareDefaultPresetColors}
    const tileCache = new Map()
    const shareMapPreviewCache = new Map()
    const shareStreetLabelFreeCache = new Map()
    const shareStreetLabelFreeRequests = new Map()
    const roadCache = new Map()
    const shareRouteCoordinateCache = new WeakMap()
    const shareSegmentDataCache = new WeakMap()
    const shareNoiseTileCache = new Map()
    const shareLogo = new Image()
    const shareLogoDark = new Image()
    let shareLogoReady = false
    let shareLogoDarkReady = false
    shareLogo.onload = () => { shareLogoReady = true; scheduleShareDraw() }
    shareLogoDark.onload = () => { shareLogoDarkReady = true; scheduleShareDraw() }
    shareLogo.src = '/assets/img/logos/stridebr-logo-white.svg'
    shareLogoDark.src = '/assets/img/logos/stridebr-logo.svg'
    const shareSportIconCache = new Map()
    const SHARE_SPORT_ICON_BY_SLUG = Object.freeze({
        musculacao: 'weightlifting', academia: 'weightlifting', crossfit: 'weightlifting',
        calistenia: 'gymnastics', hiit: 'gymnastics', 'treino-funcional': 'gymnastics', yoga: 'gymnastics', pilates: 'gymnastics', mobilidade: 'gymnastics',
        ciclismo: 'cycling', 'mountain-bike': 'cycling', downhill: 'cycling', bmx: 'cycling', gravel: 'cycling', 'bicicleta-eletrica': 'cycling', 'e-mountain-bike': 'cycling', 'ciclismo-indoor': 'cycling',
        natacao: 'swimming', triatlo: 'triathlon', remo: 'rowing', 'remo-indoor': 'rowing', canoagem: 'canoe_polo', caiaque: 'canoe_polo',
        tenis: 'tennis', 'tenis-de-mesa': 'table_tennis', badminton: 'badminton', padel: 'padel', 'beach-tennis': 'beach_tennis', pickleball: 'pickleball', squash: 'squash',
        futebol: 'soccer', futsal: 'futsal', basquete: 'basketball', volei: 'volleyball', 'volei-de-praia': 'beach_volleyball', handebol: 'handball', rugby: 'rugby', 'futebol-americano': 'american_football',
        boxe: 'combat', judo: 'judo', 'jiu-jitsu': 'judo', karate: 'judo', 'muay-thai': 'judo', taekwondo: 'judo', capoeira: 'judo', kickboxing: 'judo', esgrima: 'fencing',
        escalada: 'climbing', boulder: 'climbing', danca: 'dance', golfe: 'golf', equitacao: 'horse_racing'
    })
    const resolveShareSportIconId = data => {
        const slug = String(data?.modalidade_slug || '').trim().toLowerCase()
        if (SHARE_SPORT_ICON_BY_SLUG[slug]) return SHARE_SPORT_ICON_BY_SLUG[slug]
        const searchable = `${slug} ${String(data?.modalidade || '')} ${String(data?.titulo || '')}`.toLowerCase()
        if (/muscula|academia|weight|for[cç]a|halter/.test(searchable)) return 'weightlifting'
        const raw = String(data?.modalidade_icone || '').trim()
        if (/^[a-z0-9_-]+$/i.test(raw)) return raw
        return 'track_and_field'
    }
    const loadShareSportIcon = iconId => {
        const safeId = String(iconId || 'track_and_field').replace(/[^a-z0-9_-]/gi, '') || 'track_and_field'
        if (shareSportIconCache.has(safeId)) return shareSportIconCache.get(safeId)
        const promise = new Promise(resolve => {
            const image = new Image()
            image.onload = () => resolve(image)
            image.onerror = () => resolve(null)
            image.src = `/assets/icons/sporticon/${encodeURIComponent(safeId)}.svg`
        })
        shareSportIconCache.set(safeId, promise)
        return promise
    }
    const drawShareSportIcon = async (context, iconId, centerX, centerY, size, color = '#4f72df', outlined = false, strokeOnly = false) => {
        const image = await loadShareSportIcon(iconId)
        if (!image) return false
        const dimension = Math.max(64, Math.round(size * 2))
        const buffer = document.createElement('canvas')
        buffer.width = dimension
        buffer.height = dimension
        const bufferContext = buffer.getContext('2d')
        if (!bufferContext) return false
        bufferContext.drawImage(image, 0, 0, dimension, dimension)
        bufferContext.globalCompositeOperation = 'source-in'
        bufferContext.fillStyle = color
        bufferContext.fillRect(0, 0, dimension, dimension)
        let drawable = buffer
        if (strokeOnly) {
            const outline = document.createElement('canvas')
            outline.width = dimension
            outline.height = dimension
            const outlineContext = outline.getContext('2d')
            if (outlineContext) {
                const stroke = Math.max(2, Math.round(dimension * .014))
                for (let index = 0; index < 16; index += 1) {
                    const angle = Math.PI * 2 * index / 16
                    outlineContext.drawImage(buffer, Math.cos(angle) * stroke, Math.sin(angle) * stroke)
                }
                outlineContext.globalCompositeOperation = 'destination-out'
                outlineContext.drawImage(buffer, 0, 0)
                drawable = outline
            }
        }
        context.save()
        if (outlined) {
            context.shadowColor = 'rgba(1, 7, 18, .9)'
            context.shadowBlur = Math.max(8, Math.round(size * .16))
        } else {
            context.shadowColor = strokeOnly ? 'rgba(79, 114, 223, .30)' : 'rgba(79, 114, 223, .6)'
            context.shadowBlur = strokeOnly ? Math.max(4, Math.round(size * .07)) : Math.max(10, Math.round(size * .2))
        }
        context.drawImage(drawable, centerX - size / 2, centerY - size / 2, size, size)
        context.restore()
        return true
    }
    try {
        const savedPresetColors = JSON.parse(localStorage.getItem(SHARE_PRESET_COLORS_KEY) || '{}')
        if (savedPresetColors && typeof savedPresetColors === 'object') {
            sharePresetColors = {...shareDefaultPresetColors, ...Object.fromEntries(Object.entries(savedPresetColors).filter(([key]) => key in shareDefaultPresetColors).map(([key, value]) => [key, normalizeShareBackgroundColor(value)]))}
        }
    } catch (_) {}
    const SHARE_ROUTE_STYLE = Object.freeze({
        storyWidth: 19.5,
        standardWidth: 15,
        storyCoreWidth: 4.2,
        standardCoreWidth: 3.4,
        storyGlow: 18,
        standardGlow: 14,
    })
    const SHARE_CARD_THEME = Object.freeze({
        backgroundTop: '#07111f',
        backgroundMiddle: '#0b1625',
        backgroundBottom: '#101b2a',
        mapTint: 'rgba(18, 39, 72, .64)',
        route: '#4f72df',
        routeCore: '#dce5ff',
        routeGlow: 'rgba(79, 114, 223, .42)',
        accent: '#a8b8e8',
        text: '#f8fafc',
        muted: '#c0c8d6',
        shadeTop: 'rgba(5, 12, 24, .28)',
        shadeBottom: 'rgba(4, 10, 22, .78)',
        panel: 'rgba(5, 13, 27, .58)',
        panelBorder: 'rgba(220, 228, 242, .12)'
    })

    const wrapCanvasText = (context, text, maxWidth, maxLines = 2) => {
        const words = String(text || '').trim().split(/\s+/).filter(Boolean)
        const lines = []
        let current = ''
        words.forEach((word) => {
            const candidate = current ? `${current} ${word}` : word
            if (context.measureText(candidate).width <= maxWidth || !current) current = candidate
            else if (lines.length < maxLines - 1) { lines.push(current); current = word }
            else current += ` ${word}`
        })
        if (current) lines.push(current)
        if (lines.length && context.measureText(lines.at(-1)).width > maxWidth) {
            let last = lines.at(-1)
            while (last.length > 1 && context.measureText(`${last}…`).width > maxWidth) last = last.slice(0, -1)
            lines[lines.length - 1] = `${last.trim()}…`
        }
        return lines.slice(0, maxLines)
    }
    const canvasGeoProjector = (coordinates, x, y, width, height) => {
        if (!Array.isArray(coordinates) || coordinates.length < 2) return null
        const xs = coordinates.map((point) => Number(point[0]))
        const ys = coordinates.map((point) => Number(point[1]))
        const minX = Math.min(...xs); const maxX = Math.max(...xs)
        const minY = Math.min(...ys); const maxY = Math.max(...ys)
        const rangeX = Math.max(maxX - minX, 0.000001); const rangeY = Math.max(maxY - minY, 0.000001)
        const scale = Math.min(width / rangeX, height / rangeY)
        const usedWidth = rangeX * scale; const usedHeight = rangeY * scale
        return ([longitude, latitude]) => [x + (width - usedWidth) / 2 + (Number(longitude) - minX) * scale, y + (height - usedHeight) / 2 + (maxY - Number(latitude)) * scale]
    }
    const shareGeometryCoordinates = geometry => {
        const source = Array.isArray(geometry) ? geometry : geometry?.coordinates
        if (!Array.isArray(source)) return []
        return source.filter(point => Array.isArray(point) && point.length >= 2 && Number.isFinite(Number(point[0])) && Number.isFinite(Number(point[1]))).map(point => [Number(point[0]), Number(point[1])])
    }
    const shareGeometryBounds = coordinates => {
        const coords = shareGeometryCoordinates(coordinates)
        if (coords.length < 2) return null
        const xs = coords.map(point => point[0])
        const ys = coords.map(point => point[1])
        return {west: Math.min(...xs), east: Math.max(...xs), south: Math.min(...ys), north: Math.max(...ys)}
    }
    const createShareGeometryVisual = ({geometry, bounds = null, style = {}, viewport = {}} = {}) => {
        const coordinates = shareGeometryCoordinates(geometry)
        if (coordinates.length < 2) return null
        const resolvedBounds = bounds || shareGeometryBounds(coordinates)
        let projector = typeof viewport.project === 'function' ? viewport.project : null
        if (!projector) {
            const x = Number(viewport.x) || 0
            const y = Number(viewport.y) || 0
            const width = Math.max(1, Number(viewport.width) || 1)
            const height = Math.max(1, Number(viewport.height) || 1)
            if (resolvedBounds) {
                const rangeX = Math.max(resolvedBounds.east - resolvedBounds.west, .000001)
                const rangeY = Math.max(resolvedBounds.north - resolvedBounds.south, .000001)
                const scale = Math.min(width / rangeX, height / rangeY)
                const usedWidth = rangeX * scale
                const usedHeight = rangeY * scale
                projector = ([longitude, latitude]) => [
                    x + (width - usedWidth) / 2 + (Number(longitude) - resolvedBounds.west) * scale,
                    y + (height - usedHeight) / 2 + (resolvedBounds.north - Number(latitude)) * scale,
                ]
            }
        }
        if (!projector) return null
        return {
            geometry: {type: 'LineString', coordinates},
            bounds: resolvedBounds,
            style: {...style},
            viewport: {...viewport},
            points: coordinates.map(point => projector(point)),
        }
    }

    const coverImage = (context, image, width, height) => {
        const scale = Math.max(width / image.naturalWidth, height / image.naturalHeight)
        const drawWidth = image.naturalWidth * scale; const drawHeight = image.naturalHeight * scale
        context.drawImage(image, (width - drawWidth) / 2, (height - drawHeight) / 2, drawWidth, drawHeight)
    }
    const shareShows = (key) => {
        if (key === 'route') {
            const toggle = shareSessionRouteOption && !shareSessionRouteOption.hidden ? shareSessionRouteToggle : shareActivityRouteToggle
            return toggle?.checked !== false
        }
        return shareModal?.querySelector(`[data-share-show="${key}"]`)?.checked !== false
    }
    const getShareHeadingMode = () => shareHeadingMode?.checked === false ? 'none' : 'title'
    const shareHeadingText = (data, mode = getShareHeadingMode()) => {
        if (mode === 'none') return ''
        if (mode === 'sport') return String(data?.modalidade || tr('nav.physical_activity')).trim()
        return String(data?.titulo || data?.modalidade || tr('nav.physical_activity')).trim()
    }
    const normalizeShareMetricLabel = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toLowerCase()
    const shareMetricType = metric => {
        const key = String(metric?.key || '').toLowerCase()
        const label = normalizeShareMetricLabel(metric?.rotulo)
        const haystack = `${key} ${label}`
        if (/distance|distancia/.test(haystack)) return 'distance'
        if (/duration|duracao|tempo total|^tempo$/.test(haystack)) return 'duration'
        if (/pace|ritmo/.test(haystack)) return 'pace'
        if (/speed|velocidade/.test(haystack)) return 'speed'
        if (/elevation|elevacao|desnivel|ganho/.test(haystack)) return 'elevation'
        if (/calor/.test(haystack)) return 'calories'
        if (/heart.*max|fc max|frequencia cardiaca maxima|batimento.*max/.test(haystack)) return 'heart_max'
        if (/heart|fc media|frequencia cardiaca|batimento/.test(haystack)) return 'heart_avg'
        if (/cadenc/.test(haystack)) return 'cadence'
        if (/potencia|power/.test(haystack)) return 'power'
        if (/trecho|segment|volta|lap/.test(haystack)) return 'segments'
        if (/volume|carga total/.test(haystack)) return 'volume'
        if (/exercicio/.test(haystack)) return 'exercises'
        if (/serie|sets?/.test(haystack)) return 'sets'
        if (/esforco|sensacao|effort|feeling/.test(haystack)) return 'effort'
        return 'other'
    }
    const shareMetricCanonicalRank = metric => ({distance:0, duration:1, pace:2, speed:2, elevation:3, calories:4, heart_avg:5, heart_max:6, cadence:7, power:8, segments:9, volume:10, exercises:11, sets:12, effort:50, other:20}[shareMetricType(metric)] ?? 20)
    const canonicalizeShareMetrics = metrics => (Array.isArray(metrics) ? metrics : [])
        .map((metric, index) => ({metric, index, rank: shareMetricCanonicalRank(metric)}))
        .sort((a, b) => a.rank - b.rank || a.index - b.index)
        .map(item => item.metric)
    const isSharePrivateMetric = metric => {
        const label = normalizeShareMetricLabel(metric?.rotulo)
        const codeLabel = normalizeShareMetricLabel(tr('activity.code'))
        const focusLabel = normalizeShareMetricLabel(tr('activity.focus'))
        return label === codeLabel || label.startsWith(`${codeLabel} `) || label === focusLabel || label.startsWith(`${focusLabel} `)
    }
    const shareMetricHasValue = value => {
        const normalized = normalizeShareMetricLabel(value).replace(/\s+/g, '')
        return normalized !== '' && !['-', '--', '—', 'n/a', 'na', 'null', 'undefined'].includes(normalized)
    }
    const shareSportFamily = data => {
        const sport = normalizeShareMetricLabel(`${data?.modalidade_slug || ''} ${data?.modalidade || ''}`)
        if (/cicl|bike|bmx|gravel|mountain/.test(sport)) return 'cycling'
        if (/muscula|academia|forca|weight|calisten|crossfit/.test(sport)) return 'strength'
        if (/corr|run|caminh|trilha|trail/.test(sport)) return 'running'
        return 'generic'
    }
    const shareMetricPriority = (data, metric) => {
        const type = shareMetricType(metric)
        const family = shareSportFamily(data)
        const orders = {
            running: ['distance','duration','pace','elevation','calories','heart_avg','cadence','power','heart_max','segments'],
            cycling: ['distance','duration','speed','elevation','power','heart_avg','cadence','calories','heart_max','segments','pace'],
            strength: ['duration','calories','volume','exercises','sets','heart_avg','heart_max','effort'],
            generic: ['duration','distance','pace','speed','elevation','calories','heart_avg','power','cadence','segments','heart_max'],
        }
        const index = orders[family].indexOf(type)
        return index >= 0 ? index : 40 + shareMetricCanonicalRank(metric)
    }
    const shareMetricIsZeroElevation = metric => {
        if (shareMetricType(metric) !== 'elevation') return false
        const raw = String(metric?.valor ?? '').replace(',', '.').match(/-?\d+(?:\.\d+)?/)
        return raw ? Math.abs(Number(raw[0])) < .000001 : false
    }
    const applyShareMetricDefaults = (data, metrics) => {
        const ordered = (Array.isArray(metrics) ? metrics : []).slice().sort((a,b) => shareMetricPriority(data,a) - shareMetricPriority(data,b))
        const automatic = ordered.filter(metric => shareMetricType(metric) !== 'effort' && !shareMetricIsZeroElevation(metric)).slice(0,4)
        const selectedKeys = new Set(automatic.map(metric => metric.key))
        ordered.forEach(metric => { metric.defaultSelected = selectedKeys.has(metric.key) })
        return ordered
    }
    const isShareableSegment = segment => {
        if (!segment || typeof segment !== 'object') return false
        const semanticFlags = [
            'synthetic','sintetico','is_summary','isSummary','summary','resumo','is_primary','isPrimary','primary','principal',
            'is_aggregate','isAggregate','aggregate','agregado','placeholder','is_placeholder','isPlaceholder','is_general','isGeneral',
            'general','geral','is_main','isMain','main','is_activity','isActivity','activity_unit','is_overall','isOverall','overall'
        ]
        if (semanticFlags.some(key => segment[key] === true)) return false
        const role = normalizeShareMetricLabel(segment.share_role || segment.role || segment.papel || segment.kind || segment.tipo_registro || '')
        if (['summary','resumo','primary','principal','main','aggregate','agregado','placeholder','activity','atividade','overall','geral','general','session','sessao','total'].includes(role)) return false
        const type = normalizeShareMetricLabel(segment.tipo || segment.tipo_unidade || segment.type || '')
        if (['sessao','session','resumo','summary','principal','primary','main','atividade','activity','agregado','aggregate','overall','geral','general','total','placeholder'].includes(type)) return false
        return true
    }
    const shareableSegmentsForData = data => {
        if (!data || data.usa_trechos !== true) return []
        const seenIds = new Set()
        return (Array.isArray(data.trechos) ? data.trechos : []).filter(segment => {
            if (!isShareableSegment(segment)) return false
            const id = String(segment?.id ?? segment?.unidade_id ?? '').trim()
            if (!id) return true
            if (seenIds.has(id)) return false
            seenIds.add(id)
            return true
        })
    }

    const activityDetailToShareData = activity => {
        const rawShareMetrics = activity?.metricas_compartilhamento || activity?.metricas || []
        const focusMetric = rawShareMetrics.find(metric => normalizeShareMetricLabel(metric?.rotulo) === 'foco')
        const shareMetrics = rawShareMetrics.filter(metric => !isSharePrivateMetric(metric)).map(metric => ({...metric}))
        const hasType = type => shareMetrics.some(metric => shareMetricType(metric) === type)
        const kcal = Number(activity?.energia?.kcal)
        if (Number.isFinite(kcal) && kcal > 0 && !hasType('calories')) shareMetrics.push({key:'energy-calories', rotulo:tr('activity.share.calories', {}, 'Calorias'), valor:`${Math.round(kcal)} kcal`})
        const strength = activity?.forca || null
        if (Number(strength?.volume_kg) > 0 && !hasType('volume')) shareMetrics.push({key:'strength-volume', rotulo:tr('activity.strength.volume', {}, 'Volume'), valor:`${i18n.number?.(Math.round(Number(strength.volume_kg)), 0) ?? Math.round(Number(strength.volume_kg))} kg`})
        if (Number(strength?.total_exercicios) > 0 && !hasType('exercises')) shareMetrics.push({key:'strength-exercises', rotulo:tr('activity.strength.exercises'), valor:String(strength.total_exercicios)})
        if (Number(strength?.total_series) > 0 && !hasType('sets')) shareMetrics.push({key:'strength-sets', rotulo:tr('activity.strength.sets'), valor:String(strength.total_series)})
        const rawUnits = Array.isArray(activity?.unidades) ? activity.unidades : []
        const shareUnits = rawUnits.filter(isShareableSegment)
        if (Boolean(activity?.usa_trechos) && shareUnits.length > 0 && !hasType('segments')) shareMetrics.push({key:'activity-segments', rotulo:tr('activity.share.segments'), valor:String(shareUnits.length)})
        return {
            ...(activity?.rota || {}),
            id: activity?.id || '',
            modalidade: activity?.modalidade || tr('nav.physical_activity'),
            modalidade_slug: activity?.modalidade_slug || '',
            modalidade_icone: activity?.modalidade_icone || 'track_and_field',
            titulo: activity?.titulo || activity?.modalidade || tr('nav.physical_activity'),
            foco: String(activity?.treino?.foco || focusMetric?.valor || '').trim(),
            data: activity?.data || '',
            hora: activity?.hora || '',
            metricas: shareMetrics,
            usa_trechos: Boolean(activity?.usa_trechos),
            trechos: shareUnits.map((unit, index) => ({
                id: unit.id || String(index + 1),
                tipo: unit.tipo || 'trecho',
                rotulo: unit.rotulo || `${tr('activity.share.segment')} ${index + 1}`,
                modalidade: unit.modalidade || activity?.modalidade || tr('nav.physical_activity'),
                modalidade_slug: unit.modalidade_slug || activity?.modalidade_slug || '',
                modalidade_icone: unit.modalidade_icone || activity?.modalidade_icone || 'track_and_field',
                metrica_derivada: unit.metrica_derivada || 'nenhuma',
                distancia_metros: unit.distancia_metros ?? unit.rota?.distancia_m ?? null,
                duracao_segundos: unit.duracao_segundos ?? null,
                elevacao_m: unit.elevacao_m ?? unit.rota?.ganho_m ?? null,
                rota: unit.rota || null,
                metricas: (unit.metricas_compartilhamento || unit.valores || []).filter(metric => !isSharePrivateMetric(metric)),
                ...(unit.rota || {})
            }))
        }
    }
    const availableShareMetrics = (data) => {
        const metrics = Array.isArray(data?.metricas) ? data.metricas.map((item, index) => ({key: String(item.key || `metric-${index}-${normalizeShareMetricLabel(item.rotulo)}`), rotulo: String(item.rotulo || ''), valor: String(item.valor ?? ''), defaultSelected: item.defaultSelected !== false})) : []
        const gain = Number(data?.ganho_m)
        if (Number.isFinite(gain) && gain > 0 && !metrics.some((item) => /eleva|desnível|desnivel/i.test(item.rotulo))) metrics.push({key: 'route-elevation', rotulo: tr('activity.share.elevation'), valor: `${Math.round(gain)} m`, defaultSelected: true})
        const unique = metrics.filter((item, index, all) => item.rotulo && shareMetricHasValue(item.valor) && !isSharePrivateMetric(item) && all.findIndex((candidate) => normalizeShareMetricLabel(candidate.rotulo) === normalizeShareMetricLabel(item.rotulo)) === index)
        return applyShareMetricDefaults(data, unique)
    }
    const shareFocusLabel = data => String(data?.foco || '').trim()
    const shareLocaleTag = () => String(i18n.locale || '').toLowerCase().startsWith('en') ? 'en-US' : 'pt-BR'
    const formatShareNumber = (value, minimumFractionDigits = 0, maximumFractionDigits = minimumFractionDigits, {useGrouping = true} = {}) => {
        const number = Number(value)
        if (!Number.isFinite(number)) return ''
        return number.toLocaleString(shareLocaleTag(), {minimumFractionDigits, maximumFractionDigits, useGrouping})
    }
    const formatShareDistance = (meters, subject = shareData) => {
        const value = Number(meters)
        if (!Number.isFinite(value) || value < 0) return ''
        if (sportContextEngine?.formatDistance) return sportContextEngine.formatDistance(value, sportContextEngine.context({slug:String(subject?.modalidade_slug || ''), family:String(subject?.modalidade_familia_hub || ''), registered_m:value}), sportContextLocale())
        if (value >= 1000) {
            const digits = value >= 10000 ? 1 : 2
            return `${formatShareNumber(value / 1000, 0, digits)} km`
        }
        return `${formatShareNumber(Math.round(value), 0, 0)} m`
    }
    const formatShareDuration = seconds => {
        const value = Number(seconds)
        if (!Number.isFinite(value) || value < 0) return ''
        const totalMilliseconds = Math.max(0, Math.round(value * 1000))
        const h = Math.floor(totalMilliseconds / 3600000)
        const remainingHour = totalMilliseconds % 3600000
        const m = Math.floor(remainingHour / 60000)
        const remainingMinute = remainingHour % 60000
        const s = Math.floor(remainingMinute / 1000)
        const ms = remainingMinute % 1000
        const fractionDigits = ms > 0 ? String(ms).padStart(3, '0').replace(/0+$/, '') : ''
        const fraction = fractionDigits ? `${shareLocaleTag() === 'pt-BR' ? ',' : '.'}${fractionDigits}` : ''
        return h > 0 ? `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}${fraction}` : `${m}:${String(s).padStart(2, '0')}${fraction}`
    }
    const formatSharePaceClock = seconds => {
        const value = Number(seconds)
        if (!Number.isFinite(value) || value < 0) return ''
        const total = Math.max(0, Math.round(value))
        return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`
    }
    const formatSharePace = (seconds, unit = '/km') => {
        const clock = formatSharePaceClock(seconds)
        return clock ? `${clock}${unit}` : ''
    }
    const shareSegmentNumbers = segment => {
        const route = segment?.rota || segment || {}
        const distance = Number(segment?.distancia_metros ?? route?.distancia_m ?? route?.distancia_metros)
        const duration = Number(segment?.duracao_segundos)
        const elevation = Number(segment?.elevacao_m ?? route?.ganho_m ?? route?.ganho_elevacao_m)
        const routeGain = Number(route?.ganho_m ?? route?.ganho_elevacao_m)
        const routeMax = Number(route?.max_m ?? route?.elevacao_max_m)
        const routeMin = Number(route?.min_m ?? route?.elevacao_min_m)
        return {
            distance: Number.isFinite(distance) && distance >= 0 ? distance : null,
            duration: Number.isFinite(duration) && duration >= 0 ? duration : null,
            elevation: Number.isFinite(elevation) && elevation >= 0 ? elevation : null,
            routeGain: Number.isFinite(routeGain) && routeGain >= 0 ? routeGain : null,
            routeMax: Number.isFinite(routeMax) ? routeMax : null,
            routeMin: Number.isFinite(routeMin) ? routeMin : null,
            derived: String(segment?.metrica_derivada || 'nenhuma'),
            sport: String(segment?.modalidade_slug || segment?.modalidade || '').trim().toLowerCase(),
        }
    }
    const sessionShareMetrics = indexes => {
        const segments = shareSegments()
        const selected = (Array.isArray(indexes) && indexes.length ? indexes : segments.map((_, index) => index)).map(index => segments[index]).filter(Boolean)
        if (!selected.length) return []
        const numbers = selected.map(shareSegmentNumbers)
        const distanceValues = numbers.map(item => item.distance).filter(Number.isFinite)
        const durationValues = numbers.map(item => item.duration).filter(Number.isFinite)
        const elevationValues = numbers.map(item => item.elevation).filter(Number.isFinite)
        const gainValues = numbers.map(item => item.routeGain ?? item.elevation).filter(Number.isFinite)
        const maxAltitudeValues = numbers.map(item => item.routeMax).filter(Number.isFinite)
        const totalDistance = distanceValues.reduce((sum, value) => sum + value, 0)
        const totalDuration = durationValues.reduce((sum, value) => sum + value, 0)
        const totalElevation = elevationValues.reduce((sum, value) => sum + value, 0)
        const fullDistance = distanceValues.length === selected.length && totalDistance > 0
        const fullDuration = durationValues.length === selected.length && totalDuration > 0
        const sports = new Set(numbers.map(item => item.sport).filter(Boolean))
        const derivedTypes = new Set(numbers.map(item => item.derived).filter(type => type && type !== 'nenhuma'))
        const compatibleDerived = sports.size <= 1 && derivedTypes.size === 1 && fullDistance && fullDuration
        const metrics = []
        if (distanceValues.length) metrics.push({key: 'session-distance', rotulo: distanceValues.length === selected.length ? tr('activity.share.total_distance') : tr('activity.share.logged_distance'), valor: formatShareDistance(totalDistance), defaultSelected: true})
        if (durationValues.length) metrics.push({key: 'session-duration', rotulo: durationValues.length === selected.length ? tr('activity.share.total_time') : tr('activity.share.logged_time'), valor: formatShareDuration(totalDuration), defaultSelected: true})
        if (compatibleDerived) {
            const type = Array.from(derivedTypes)[0]
            if (type === 'pace_km') metrics.push({key: 'session-weighted-pace', rotulo: tr('activity.share.average_pace'), valor: formatSharePace(totalDuration / (totalDistance / 1000)), defaultSelected: true})
            else if (type === 'velocidade_kmh') metrics.push({key: 'session-weighted-speed', rotulo: tr('activity.share.average_speed'), valor: `${formatShareNumber(totalDistance / 1000 / (totalDuration / 3600), 1, 1)} km/h`, defaultSelected: true})
            else if (type === 'pace_100m') metrics.push({key: 'session-weighted-100m', rotulo: tr('activity.share.average_pace'), valor: formatSharePace(totalDuration / (totalDistance / 100), '/100 m'), defaultSelected: true})
            else if (type === 'split_500m') metrics.push({key: 'session-weighted-500m', rotulo: tr('activity.share.average_split'), valor: formatSharePace(totalDuration / (totalDistance / 500), '/500 m'), defaultSelected: true})
        }
        metrics.push({key: 'session-segments', rotulo: tr('activity.share.segments'), valor: String(selected.length), defaultSelected: !compatibleDerived})
        if (elevationValues.length) metrics.push({key: 'session-elevation-total', rotulo: elevationValues.length === selected.length ? tr('activity.share.total_elevation') : tr('activity.share.logged_elevation'), valor: `${formatShareNumber(totalElevation, 0, 1)} m`, defaultSelected: true})
        if (gainValues.length) {
            const maxGain = Math.max(...gainValues)
            const avgGain = gainValues.reduce((sum, value) => sum + value, 0) / gainValues.length
            metrics.push({key: 'session-elevation-max-gain', rotulo: tr('activity.share.max_segment_gain'), valor: `${formatShareNumber(maxGain, 0, 1)} m`, defaultSelected: false})
            metrics.push({key: 'session-elevation-average-gain', rotulo: tr('activity.share.average_gain'), valor: `${formatShareNumber(avgGain, 0, 1)} m`, defaultSelected: false})
        }
        if (maxAltitudeValues.length) metrics.push({key: 'session-elevation-max-altitude', rotulo: tr('activity.share.max_altitude'), valor: `${formatShareNumber(Math.max(...maxAltitudeValues), 0, 1)} m`, defaultSelected: false})
        if (compatibleDerived) {
            const type = Array.from(derivedTypes)[0]
            const perSegment = numbers.map((item, index) => ({...item, index})).filter(item => item.distance > 0 && item.duration > 0)
            if (type === 'pace_km' && perSegment.length) {
                const best = Math.min(...perSegment.map(item => item.duration / (item.distance / 1000)))
                metrics.push({key: 'session-best-pace', rotulo: tr('activity.share.best_segment_pace'), valor: formatSharePace(best), defaultSelected: false})
            }
            if (type === 'velocidade_kmh' && perSegment.length) {
                const best = Math.max(...perSegment.map(item => item.distance / 1000 / (item.duration / 3600)))
                metrics.push({key: 'session-best-speed', rotulo: tr('activity.share.best_segment_speed'), valor: `${formatShareNumber(best, 1, 1)} km/h`, defaultSelected: false})
            }
        }
        const wanted = compatibleDerived
            ? ['session-distance', 'session-duration', 'session-weighted-pace', 'session-weighted-speed', 'session-weighted-100m', 'session-weighted-500m', 'session-elevation-total']
            : ['session-distance', 'session-duration', 'session-segments', 'session-elevation-total']
        let defaults = 0
        metrics.forEach(metric => {
            metric.defaultSelected = wanted.includes(metric.key) && defaults < 4
            if (metric.defaultSelected) defaults += 1
        })
        if (defaults < 4) {
            for (const metric of metrics) {
                if (defaults >= 4) break
                if (metric.defaultSelected) continue
                if (['session-segments', 'session-elevation-total'].includes(metric.key)) {
                    metric.defaultSelected = true
                    defaults += 1
                }
            }
        }
        return metrics
    }
    const getShareScope = () => normalizeShareScope(shareMasterSwitch?.dataset.shareScope || SHARE_SCOPES.activity)
    const shareMetricPool = () => {
        const scope = getShareScope()
        if (scope === SHARE_SCOPES.singleSegment) return availableShareMetrics(shareDataForSegment(getActiveShareSegmentIndex()))
        if (scope === SHARE_SCOPES.session) return sessionShareMetrics(shareSegments().map((_, index) => index))
        if (scope === SHARE_SCOPES.multipleSegments) return sessionShareMetrics(getSelectedShareSegmentIndexes())
        return availableShareMetrics(shareData)
    }
    const populateShareMetricOptions = ({preserve = false} = {}) => {
        if (!shareMetricOptions) return
        const previous = preserve ? new Set(Array.from(shareMetricOptions.querySelectorAll('[data-share-metric]:checked')).map(input => input.dataset.metricKey || '')) : new Set()
        shareMetricOptions.replaceChildren()
        shareMetricPool().forEach((metric, index) => {
            const label = document.createElement('label')
            const input = document.createElement('input')
            input.type = 'checkbox'
            input.value = String(index)
            input.dataset.shareMetric = ''
            input.dataset.metricKey = metric.key || String(index)
            input.checked = preserve && previous.has(input.dataset.metricKey) ? true : Boolean(metric.defaultSelected ?? index < 4)
            label.append(input, document.createTextNode(metric.rotulo))
            shareMetricOptions.append(label)
        })
    }

    const mercatorWorld = (longitude, latitude, zoom) => {
        const n = 2 ** zoom
        const lat = Math.max(-85.05112878, Math.min(85.05112878, Number(latitude))) * Math.PI / 180
        return {
            x: ((Number(longitude) + 180) / 360) * n * 256,
            y: ((1 - Math.log(Math.tan(lat) + 1 / Math.cos(lat)) / Math.PI) / 2) * n * 256,
        }
    }
    const tileUrl = (z, x, y) => `https://tile.openstreetmap.org/${z}/${x}/${y}.png`
    const getShareFormatValue = () => shareFormatInputs.find(input => input.checked)?.value || 'story'
    const getShareFormat = (value = getShareFormatValue()) => SHARE_FORMATS[value] || SHARE_FORMATS.story
    const selectShareFormat = (value) => {
        const format = SHARE_FORMATS[value] ? value : 'story'
        shareFormatInputs.forEach(input => { input.checked = input.value === format })
    }
    const sharePreviewContainSize = (availableWidth, availableHeight, contentWidth, contentHeight) => {
        const safeWidth = Math.max(0, Number(availableWidth) || 0)
        const safeHeight = Math.max(0, Number(availableHeight) || 0)
        const sourceWidth = Math.max(1, Number(contentWidth) || 1)
        const sourceHeight = Math.max(1, Number(contentHeight) || 1)
        if (safeWidth < 1 || safeHeight < 1) return {width: 0, height: 0, scale: 0}
        const scale = Math.min(safeWidth / sourceWidth, safeHeight / sourceHeight)
        return {
            width: Math.max(1, Math.floor(sourceWidth * scale)),
            height: Math.max(1, Math.floor(sourceHeight * scale)),
            scale,
        }
    }
    let sharePreviewFitFrame = 0
    let sharePreviewFitTimer = 0
    let sharePreviewResizeObserver = null
    const fitSharePreviewCanvas = () => {
        if (!shareCanvas || !sharePreviewStage || shareModal?.hidden) return false
        const stageRect = sharePreviewStage.getBoundingClientRect()
        const stageStyle = window.getComputedStyle(sharePreviewStage)
        const borderLeft = parseFloat(stageStyle.borderLeftWidth) || 0
        const borderTop = parseFloat(stageStyle.borderTopWidth) || 0
        const borderRight = parseFloat(stageStyle.borderRightWidth) || 0
        const borderBottom = parseFloat(stageStyle.borderBottomWidth) || 0
        const paddingLeft = parseFloat(stageStyle.paddingLeft) || 0
        const paddingTop = parseFloat(stageStyle.paddingTop) || 0
        const paddingRight = parseFloat(stageStyle.paddingRight) || 0
        const paddingBottom = parseFloat(stageStyle.paddingBottom) || 0
        const availableWidth = Math.max(0, stageRect.width - borderLeft - borderRight - paddingLeft - paddingRight)
        const availableHeight = Math.max(0, stageRect.height - borderTop - borderBottom - paddingTop - paddingBottom)
        const format = getShareFormat()
        const fitted = sharePreviewContainSize(availableWidth, availableHeight, format.width, format.height)
        if (!fitted.width || !fitted.height) return false
        const left = borderLeft + paddingLeft + Math.max(0, (availableWidth - fitted.width) / 2)
        const top = borderTop + paddingTop + Math.max(0, (availableHeight - fitted.height) / 2)
        shareCanvas.style.left = `${left}px`
        shareCanvas.style.top = `${top}px`
        shareCanvas.style.width = `${fitted.width}px`
        shareCanvas.style.height = `${fitted.height}px`
        shareCanvas.dataset.sharePreviewFit = `${fitted.width}x${fitted.height}`
        return true
    }
    const scheduleSharePreviewFit = () => {
        if (sharePreviewFitFrame) window.cancelAnimationFrame(sharePreviewFitFrame)
        if (sharePreviewFitTimer) window.clearTimeout(sharePreviewFitTimer)
        sharePreviewFitFrame = window.requestAnimationFrame(() => {
            sharePreviewFitFrame = 0
            fitSharePreviewCanvas()
            window.requestAnimationFrame(() => fitSharePreviewCanvas())
            sharePreviewFitTimer = window.setTimeout(() => {
                sharePreviewFitTimer = 0
                fitSharePreviewCanvas()
            }, 90)
        })
    }
    const getShareColorValue = () => shareColorInputs.find(input => input.checked)?.value || 'deep'
    const getShareRouteScaleValue = () => Math.max(50, Math.min(200, Number(shareRouteScaleInput?.value) || 100))
    const getShareMapStyleValue = () => {
        const selected = shareMapStyleInputs.find(input => input.checked)?.value
        if (['street', 'satellite'].includes(String(selected))) return String(selected)
        try {
            const stored = localStorage.getItem(SHARE_MAP_STYLE_KEY)
            if (['street', 'satellite'].includes(String(stored))) return String(stored)
        } catch (_) {}
        return 'street'
    }
    const setShareMapStyleValue = (value, {persist = true} = {}) => {
        let target = ['street', 'satellite'].includes(String(value)) ? String(value) : 'street'
        const api = window.StrideBRBasemaps?.share
        if (api && !api.available?.(target)) target = ['street', 'satellite'].find(id => api.available?.(id)) || 'street'
        shareMapStyleInputs.forEach(input => { input.checked = input.value === target })
        if (persist) {
            try { localStorage.setItem(SHARE_MAP_STYLE_KEY, target) } catch (_) {}
        }
        return target
    }
    const getSharePreset = (id) => {
        const normalized = id === 'route' ? 'stats' : id
        return SHARE_CARD_PRESETS.find(preset => preset.id === normalized) || SHARE_CARD_PRESETS.find(preset => preset.id === SHARE_PRESET_FALLBACK) || SHARE_CARD_PRESETS[0]
    }
    const getShareContentPreset = (id = activeShareContentId) => SHARE_CONTENT_PRESETS.find(preset => preset.id === id) || SHARE_CONTENT_PRESETS[0]
    const getShareCompositionDefinition = (id = activeShareCompositionId) => SHARE_COMPOSITION_REGISTRY[String(id || '')] || SHARE_COMPOSITION_REGISTRY.standard
    const getSharePresetColor = (presetId) => normalizeShareBackgroundColor(sharePresetColors[presetId] || shareDefaultPresetColors[presetId] || 'deep')
    const persistSharePresetColors = () => { try { localStorage.setItem(SHARE_PRESET_COLORS_KEY, JSON.stringify(sharePresetColors)) } catch (_) {} }
    const syncShareColorSelection = (presetId = activeSharePresetId) => {
        const color = getSharePresetColor(presetId)
        shareColorInputs.forEach(input => { input.checked = input.value === color })
    }
    const setSharePresetColor = (presetId, color, {persist = true} = {}) => {
        if (!presetId) return
        sharePresetColors[presetId] = normalizeShareBackgroundColor(color)
        if (persist) persistSharePresetColors()
        if (presetId === activeSharePresetId) syncShareColorSelection(presetId)
    }
    const syncShareRouteScaleLabel = () => { if (shareRouteScaleValue) shareRouteScaleValue.textContent = `${getShareRouteScaleValue()}%` }
    const getSelectedShareMetrics = (data = shareData) => {
        const options = data && data !== shareData ? availableShareMetrics(data) : shareMetricPool()
        return canonicalizeShareMetrics(Array.from(shareMetricOptions?.querySelectorAll('[data-share-metric]:checked') || []).map(input => options[Number(input.value)]).filter(Boolean)).slice(0, 4)
    }
    const getShareContentMode = () => [SHARE_SCOPES.session, SHARE_SCOPES.multipleSegments, SHARE_SCOPES.singleSegment].includes(getShareScope()) ? 'segments' : 'activity'
    const getShareSegmentMode = () => getShareScope() === SHARE_SCOPES.singleSegment ? 'separate' : 'together'
    const shareSegments = () => shareableSegmentsForData(shareData)
    const shareHasRoute = data => routeCoordinatesForSharing(data).length >= 2
    const shareSelectedIndexesForScope = (scope = getShareScope()) => {
        const normalized = normalizeShareScope(scope)
        if (normalized === SHARE_SCOPES.session) return shareSegments().map((_, index) => index)
        if (normalized === SHARE_SCOPES.multipleSegments) return getSelectedShareSegmentIndexes()
        if (normalized === SHARE_SCOPES.singleSegment) return [getActiveShareSegmentIndex()]
        return []
    }
    const shareGeometryCountForScope = (scope = getShareScope()) => shareSelectedIndexesForScope(scope).filter(index => shareHasRoute(shareDataForSegment(index))).length
    const shareComparableSegmentCountForScope = (scope = getShareScope()) => {
        const normalized = normalizeShareScope(scope)
        if (![SHARE_SCOPES.session, SHARE_SCOPES.multipleSegments].includes(normalized)) return 0
        const segments = shareSelectedIndexesForScope(normalized).map(index => shareDataForSegment(index)).filter(Boolean)
        const capabilities = shareComparisonCapabilities(segments).capabilities
        return capabilities.reduce((maximum, capability) => Math.max(maximum, Number(capability.count) || 0), 0)
    }
    const sharePreferenceContext = scope => [SHARE_SCOPES.session, SHARE_SCOPES.multipleSegments].includes(normalizeShareScope(scope)) ? 'session' : 'activity'
    const sharePreferenceStorageKey = scope => `${SHARE_LAST_USED_KEY}.${sharePreferenceContext(scope)}.v2`
    const defaultSharePreference = (data, scope = getShareScope()) => {
        const normalizedScope = normalizeShareScope(scope)
        const sessionContext = [SHARE_SCOPES.session, SHARE_SCOPES.multipleSegments].includes(normalizedScope)
        const hasRoute = sessionContext ? shareGeometryCountForScope(normalizedScope) > 0 : shareHasRoute(data)
        return {
            format: 'story',
            composition: sessionContext ? (hasRoute ? 'session_overview' : 'session_summary') : 'standard',
            preset: 'stats',
            content: hasRoute && !sessionContext ? 'route' : 'sport',
            color: 'deep',
            routeScale: 100,
            mapStyle: 'street',
            headingMode: sessionContext ? 'sport' : 'title',
            showDate: false,
            showLogo: true,
            showRoute: hasRoute,
            metricKeys: [],
            compactPrimaryMetric: '',
            compactSecondaryMetric: ''
        }
    }
    const normalizeSharePreference = (value, data, scope = getShareScope()) => {
        const normalizedScope = normalizeShareScope(scope)
        const fallback = defaultSharePreference(data, normalizedScope)
        const raw = value && typeof value === 'object' ? value : {}
        const selectedIndexes = shareSelectedIndexesForScope(normalizedScope)
        const segmentCount = selectedIndexes.length
        const geometryCount = normalizedScope === SHARE_SCOPES.activity ? (shareHasRoute(data) ? 1 : 0) : shareGeometryCountForScope(normalizedScope)
        let composition = SHARE_COMPOSITION_REGISTRY[String(raw.composition || '')]?.id || fallback.composition
        const requestedFormat = SHARE_FORMATS[String(raw.format || '')] ? String(raw.format) : fallback.format
        const comparableSegmentCount = [SHARE_SCOPES.session, SHARE_SCOPES.multipleSegments].includes(normalizedScope)
            ? shareComparableSegmentCountForScope(normalizedScope)
            : 0
        const compatibility = {scope: normalizedScope, format: requestedFormat, segmentCount, geometryCount, comparableSegmentCount}
        if (!shareCompositionCompatibility(composition, compatibility)) composition = fallback.composition
        const definition = SHARE_COMPOSITION_REGISTRY[composition] || SHARE_COMPOSITION_REGISTRY.standard
        const format = definition.supportedFormats.includes(requestedFormat) ? requestedFormat : definition.supportedFormats[0]
        const hasRoute = geometryCount > 0
        let preset = getSharePreset(String(raw.preset || fallback.preset)).id
        if (preset === 'photo' && !sharePhoto) preset = 'stats'
        if (preset === 'map' && (!hasRoute || !definition.supportsMap)) preset = 'stats'
        let content = getShareContentPreset(String(raw.content || fallback.content)).id
        if (![SHARE_SCOPES.activity, SHARE_SCOPES.singleSegment].includes(normalizedScope)) content = 'none'
        if (content === 'route' && !hasRoute) content = 'sport'
        const color = normalizeShareBackgroundColor(raw.color || fallback.color)
        const routeScale = Math.max(50, Math.min(200, Number(raw.routeScale) || fallback.routeScale))
        const mapStyle = ['street', 'satellite'].includes(String(raw.mapStyle || '')) ? String(raw.mapStyle) : fallback.mapStyle
        const headingMode = [SHARE_SCOPES.session, SHARE_SCOPES.multipleSegments].includes(normalizedScope) ? 'sport' : (String(raw.headingMode || '') === 'none' ? 'none' : 'title')
        const metricKeys = Array.isArray(raw.metricKeys) ? raw.metricKeys.map(String).filter(Boolean).slice(0, 4) : []
        const compactPrimaryMetric = String(raw.compactPrimaryMetric || fallback.compactPrimaryMetric || '')
        const compactSecondaryMetric = String(raw.compactSecondaryMetric || fallback.compactSecondaryMetric || '')
        return {format, composition, preset, content, color, routeScale, mapStyle, headingMode, showDate: raw.showDate === true, showLogo: true, showRoute: hasRoute && raw.showRoute !== false, metricKeys, compactPrimaryMetric, compactSecondaryMetric}
    }
    const loadLastSharePreference = (data, scope = getShareScope()) => {
        try {
            const stored = JSON.parse(localStorage.getItem(sharePreferenceStorageKey(scope)) || 'null')
            if (stored) return normalizeSharePreference(stored, data, scope)
            if (sharePreferenceContext(scope) === 'activity') {
                const legacy = JSON.parse(localStorage.getItem(SHARE_LAST_USED_KEY) || 'null')
                if (legacy) return normalizeSharePreference(legacy, data, scope)
            }
        } catch (_) {}
        return defaultSharePreference(data, scope)
    }
    const captureCurrentSharePreference = (data, scope = getShareScope()) => normalizeSharePreference({
        format: getShareFormatValue(), composition: activeShareCompositionId, preset: activeSharePresetId, content: activeShareContentId,
        color: getSharePresetColor(activeSharePresetId) || getShareColorValue(), routeScale: getShareRouteScaleValue(), mapStyle: getShareMapStyleValue(),
        headingMode: getShareHeadingMode(), showDate: shareShows('date'), showLogo: true, showRoute: shareShows('route'),
        metricKeys: Array.from(shareMetricOptions?.querySelectorAll('[data-share-metric]:checked') || []).map(input => String(input.dataset.metricKey || '')).filter(Boolean).slice(0, 4),
        compactPrimaryMetric: shareSessionCompactPrimary?.value || '',
        compactSecondaryMetric: shareSessionCompactSecondary?.value || ''
    }, data, scope)
    const storeSharePreference = (data, value, scope = getShareScope()) => {
        const preference = normalizeSharePreference(value, data, scope)
        try { localStorage.setItem(sharePreferenceStorageKey(scope), JSON.stringify(preference)) } catch (_) {}
        return preference
    }
    const persistLastSharePreference = (data, scope = getShareScope()) => storeSharePreference(data, captureCurrentSharePreference(data, scope), scope)
    const getSelectedShareSegmentIndexes = () => Array.from(shareSegmentList?.querySelectorAll('[data-share-segment-check]:checked') || [])
        .map(input => Number(input.value))
        .filter(index => Number.isFinite(index) && Boolean(shareSegments()[index]))
    const syncShareSegmentSelectionSummary = () => {
        const total = shareSegments().length
        const selected = getSelectedShareSegmentIndexes().length
        const text = tr('activity.share.segments_selected', {selected, total})
        if (shareSegmentSelectionSummary) shareSegmentSelectionSummary.textContent = text
        if (shareSegmentSelectionInline) shareSegmentSelectionInline.textContent = text
    }
    const getActiveShareSegmentIndex = () => {
        const selected = Number(shareSegmentPreviewSelect?.value)
        if (Number.isFinite(selected) && shareSegments()[selected]) return selected
        return getSelectedShareSegmentIndexes()[0] ?? 0
    }
    const shareDataForSegment = index => {
        const segment = shareSegments()[index]
        if (!segment) return null
        const cached = shareSegmentDataCache.get(segment)
        if (cached) return cached
        const data = {
            ...segment,
            modalidade: segment.modalidade || shareData?.modalidade || tr('common.activity'),
            modalidade_slug: segment.modalidade_slug || shareData?.modalidade_slug || '',
            modalidade_icone: segment.modalidade_icone || shareData?.modalidade_icone || 'track_and_field',
            titulo: segment.rotulo || segment.titulo || `${tr('activity.share.segment')} ${index + 1}`,
            data: shareData?.data || '',
            hora: shareData?.hora || '',
            metricas: Array.isArray(segment.metricas) ? segment.metricas : [],
        }
        shareSegmentDataCache.set(segment, data)
        return data
    }
    const currentShareRouteData = () => {
        if (getShareContentMode() !== 'segments') return shareData
        if (getShareSegmentMode() === 'separate') return shareDataForSegment(getActiveShareSegmentIndex())
        const selected = getSelectedShareSegmentIndexes()
        return selected.map(index => shareDataForSegment(index)).find(data => shareHasRoute(data)) || null
    }
    const populateShareSegments = () => {
        if (!shareSegmentList || !shareSegmentPreviewSelect) return
        const segments = shareSegments()
        shareSegmentList.replaceChildren()
        shareSegmentPreviewSelect.replaceChildren()
        shareSingleSegmentList?.replaceChildren()
        segments.forEach((segment, index) => {
            const data = shareDataForSegment(index)
            const label = document.createElement('label')
            const input = document.createElement('input')
            input.type = 'checkbox'
            input.checked = true
            input.value = String(index)
            input.dataset.shareSegmentCheck = ''
            const visual = document.createElement('span')
            visual.className = 'activity-share-segment-mini-route'
            visual.innerHTML = shareRouteSilhouetteSvg(data) || '<i aria-hidden="true"></i>'
            const text = document.createElement('span')
            const metrics = availableShareMetrics(data).filter(item => ['distance','duration','pace','speed','power'].includes(shareMetricType(item))).slice(0, 2).map(item => item.valor).join(' · ')
            text.innerHTML = `<strong>${escapeHtml(segment.rotulo || `${tr('activity.share.segment')} ${index + 1}`)}</strong><small>${escapeHtml(metrics || tr('activity.share.no_metrics'))}</small>`
            label.append(input, visual, text)
            shareSegmentList.append(label)
            const option = document.createElement('option')
            option.value = String(index)
            option.textContent = segment.rotulo || `${tr('activity.share.segment')} ${index + 1}`
            shareSegmentPreviewSelect.append(option)
            if (shareSingleSegmentList) {
                const button = document.createElement('button')
                button.type = 'button'
                button.className = 'activity-share-single-segment-option'
                button.dataset.shareSingleSegmentIndex = String(index)
                const route = document.createElement('span')
                route.className = 'activity-share-route-picker-visual'
                route.innerHTML = shareRouteSilhouetteSvg(data) || '<i aria-hidden="true"></i>'
                const copy = document.createElement('span')
                copy.className = 'activity-share-route-picker-copy'
                const strong = document.createElement('strong')
                strong.textContent = String(data?.titulo || `${tr('activity.share.segment')} ${index + 1}`)
                const small = document.createElement('small')
                small.textContent = shareSegmentPreviewMeta(data, index)
                copy.append(strong, small)
                button.append(route, copy)
                button.addEventListener('click', () => {
                    shareSegmentPreviewSelect.value = String(index)
                    syncSingleSegmentSelection()
                    populateShareMetricOptions({preserve: false})
                    updateShareControlAvailability()
                    scheduleShareDraw()
                })
                shareSingleSegmentList.append(button)
            }
        })
        syncSingleSegmentSelection()
        syncShareSegmentSelectionSummary()
    }
    const syncSingleSegmentSelection = () => {
        const index = getActiveShareSegmentIndex()
        shareSingleSegmentList?.querySelectorAll('[data-share-single-segment-index]').forEach(button => {
            const active = Number(button.dataset.shareSingleSegmentIndex) === index
            button.classList.toggle('is-active', active)
            button.setAttribute('aria-pressed', active ? 'true' : 'false')
        })
    }
    const shareRouteSilhouetteSvg = data => {
        const coordinates = routeCoordinatesForSharing(data)
        if (coordinates.length < 2) return ''
        const xs = coordinates.map(point => Number(point[0]))
        const ys = coordinates.map(point => Number(point[1]))
        const minX = Math.min(...xs); const maxX = Math.max(...xs); const minY = Math.min(...ys); const maxY = Math.max(...ys)
        const spanX = Math.max(maxX - minX, .000001); const spanY = Math.max(maxY - minY, .000001)
        const scale = Math.min(116 / spanX, 70 / spanY)
        const usedX = spanX * scale; const usedY = spanY * scale
        const points = coordinates.map(([x,y]) => `${(8 + (116-usedX)/2 + (Number(x)-minX)*scale).toFixed(1)},${(7 + (70-usedY)/2 + (maxY-Number(y))*scale).toFixed(1)}`).join(' ')
        return `<svg viewBox="0 0 132 84" aria-hidden="true"><polyline points="${points}" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>`
    }
    const shareSegmentPreviewMeta = (data, index) => {
        const metrics = availableShareMetrics(data)
        const preferred = metrics.filter(metric => ['distance','duration','pace','speed'].includes(shareMetricType(metric))).slice(0,2)
        return preferred.map(metric => metric.valor).filter(Boolean).join(' · ') || `${tr('activity.share.segment')} ${index + 1}`
    }
    const updateSharePhotoPreview = () => {
        if (!sharePhotoPreview) return
        const current = sharePhotoPreview.querySelector('img')
        if (sharePhoto) {
            if (!current) {
                const image = document.createElement('img')
                image.alt = tr('activity.share.card_photo_alt')
                sharePhotoPreview.append(image)
            }
            sharePhotoPreview.querySelector('img').src = sharePhoto.src
            if (sharePhotoEmpty) sharePhotoEmpty.hidden = true
        } else {
            current?.remove()
            if (sharePhotoEmpty) sharePhotoEmpty.hidden = false
        }
        if (shareMobilePhotoButton && getSharePreset(activeSharePresetId).mode === 'photo') shareMobilePhotoButton.textContent = sharePhoto ? tr('activity.share.change_photo') : tr('activity.share.choose_photo')
    }
    const shareScopeIsSession = scope => [SHARE_SCOPES.session, SHARE_SCOPES.multipleSegments].includes(normalizeShareScope(scope))
    const shareScopeIsActivity = scope => [SHARE_SCOPES.activity, SHARE_SCOPES.singleSegment].includes(normalizeShareScope(scope))
    const shareCurrentCompatibilityContext = (scope = getShareScope(), formatId = getShareFormatValue(), extra = {}) => {
        const normalized = normalizeShareScope(scope)
        const segmentCount = normalized === SHARE_SCOPES.activity ? 0 : shareSelectedIndexesForScope(normalized).length
        return {
            scope: normalized,
            format: formatId,
            segmentCount,
            geometryCount: normalized === SHARE_SCOPES.activity ? (shareHasRoute(shareData) ? 1 : 0) : shareGeometryCountForScope(normalized),
            comparableSegmentCount: shareComparableSegmentCountForScope(normalized),
            ...extra,
        }
    }
    const firstCompatibleShareComposition = scope => {
        const normalized = normalizeShareScope(scope)
        const geometryCount = normalized === SHARE_SCOPES.activity ? (shareHasRoute(shareData) ? 1 : 0) : shareGeometryCountForScope(normalized)
        if (shareScopeIsSession(normalized)) {
            return geometryCount > 0 && shareCompositionCompatibility('session_overview', shareCurrentCompatibilityContext(normalized, 'story'))
                ? 'session_overview'
                : 'session_summary'
        }
        return 'standard'
    }
    const updateShareControlAvailability = () => {
        const segments = shareSegments()
        const segmentsAvailable = segments.length > 1
        let scope = normalizeShareScope(shareMasterSwitch?.dataset.shareScope || (segmentsAvailable ? SHARE_SCOPES.session : SHARE_SCOPES.activity))
        if (!segmentsAvailable) scope = SHARE_SCOPES.activity
        if (segmentsAvailable && scope === SHARE_SCOPES.activity) scope = SHARE_SCOPES.session
        if (shareMasterSwitch) {
            shareMasterSwitch.hidden = !segmentsAvailable
            shareMasterSwitch.dataset.shareScope = scope
        }
        shareScopeButtons.forEach(button => {
            const active = normalizeShareScope(button.dataset.shareScope) === scope
            button.classList.toggle('is-active', active)
            button.setAttribute('aria-pressed', active ? 'true' : 'false')
        })

        const activityEditor = shareScopeIsActivity(scope)
        const sessionEditor = shareScopeIsSession(scope)
        const geometryCount = scope === SHARE_SCOPES.activity ? (shareHasRoute(shareData) ? 1 : 0) : shareGeometryCountForScope(scope)
        const hasRoute = geometryCount > 0
        if (activityEditor) {
            if (scope === SHARE_SCOPES.singleSegment && shareSegmentPreviewSelect && shareSegmentPreviewSelect.selectedIndex < 0) shareSegmentPreviewSelect.selectedIndex = 0
            const data = scope === SHARE_SCOPES.singleSegment ? shareDataForSegment(getActiveShareSegmentIndex()) : shareData
            if (activeShareContentId === 'route' && !shareHasRoute(data)) activeShareContentId = 'sport'
        }

        shareContentModeInputs.forEach(input => { input.checked = input.value === (activityEditor && scope === SHARE_SCOPES.activity ? 'activity' : 'segments') })
        shareSegmentModeInputs.forEach(input => { input.checked = input.value === (scope === SHARE_SCOPES.singleSegment ? 'separate' : 'together') })
        if (shareContentOptions) shareContentOptions.hidden = true
        if (shareSegmentModeOptions) shareSegmentModeOptions.hidden = true
        shareSingleOnlySections.forEach(section => { section.hidden = !activityEditor })
        shareSessionOnlySections.forEach(section => { section.hidden = !sessionEditor })
        if (shareSingleSegmentPicker) shareSingleSegmentPicker.hidden = scope !== SHARE_SCOPES.singleSegment
        if (shareMultipleSummary) shareMultipleSummary.hidden = scope !== SHARE_SCOPES.multipleSegments
        if (shareSegmentsGroup) shareSegmentsGroup.hidden = scope !== SHARE_SCOPES.multipleSegments
        if (shareSegmentList) shareSegmentList.hidden = scope !== SHARE_SCOPES.multipleSegments
        if (shareSegmentPreviewPicker) shareSegmentPreviewPicker.hidden = true
        syncSingleSegmentSelection()
        syncShareSegmentSelectionSummary()

        let format = getShareFormat()
        if (!shareCompositionCompatibility(activeShareCompositionId, shareCurrentCompatibilityContext(scope, format.id))) {
            activeShareCompositionId = firstCompatibleShareComposition(scope)
        }
        const definition = getShareCompositionDefinition(activeShareCompositionId)
        if (!definition.supportedFormats.includes(format.id)) {
            selectShareFormat(definition.supportedFormats[0] || 'story')
            format = getShareFormat()
        }

        shareFormatInputs.forEach(input => {
            const label = input.closest('label')
            const visible = definition.supportedFormats.includes(input.value)
            if (label) label.hidden = !visible
            input.disabled = !visible
        })
        if (shareCompositionOptions) shareCompositionOptions.hidden = !activityEditor
        shareCompositionInputs.forEach(input => {
            const visible = activityEditor && shareCompositionCompatibility(input.value, shareCurrentCompatibilityContext(scope, format.id))
            const label = input.closest('label')
            if (label) label.hidden = !visible
            input.checked = visible && input.value === activeShareCompositionId
        })

        shareSessionLayoutGrid?.querySelectorAll('[data-share-session-layout-card]').forEach(card => {
            const id = String(card.dataset.shareSessionLayoutValue || '')
            const visible = sessionEditor && shareCompositionCompatibility(id, shareCurrentCompatibilityContext(scope, 'story'))
            card.hidden = !visible
            card.disabled = !visible
            card.classList.toggle('is-active', visible && id === activeShareCompositionId)
        })

        const routeToggle = sessionEditor ? shareSessionRouteToggle : shareActivityRouteToggle
        const sessionRouteVisible = sessionEditor && Boolean(definition.supportsRouteToggle) && hasRoute
        if (activityEditor && shareActivityRouteToggle) shareActivityRouteToggle.checked = activeShareContentId === 'route' && hasRoute
        if (shareSessionRouteToggle) {
            if (sessionRouteVisible && !shareSessionRouteToggle.dataset.sessionTouched) shareSessionRouteToggle.checked = true
            else if (!sessionRouteVisible) shareSessionRouteToggle.checked = false
        }
        if (shareSessionRouteOption) shareSessionRouteOption.hidden = !sessionRouteVisible
        const compact = activeShareCompositionId === 'compact' || activeShareCompositionId === 'session_compact'
        const routeScaleBlock = shareRouteScaleInput?.closest('.activity-share-route-scale')
        const routeScaleVisible = hasRoute && ((activityEditor && activeShareContentId === 'route') || (sessionEditor && (activeShareCompositionId === 'session_overview' || activeShareCompositionId === 'session_by_segment' || (sessionRouteVisible && routeToggle?.checked !== false))))
        if (routeScaleBlock) routeScaleBlock.hidden = !routeScaleVisible
        if (shareExportRouteButton) shareExportRouteButton.hidden = !hasRoute

        const mapCompatible = Boolean(definition.supportsMap && hasRoute && (sessionEditor || (activityEditor && activeShareContentId === 'route')))
        const shareMapApi = window.StrideBRBasemaps?.share
        const mapConfigured = ['street', 'satellite'].some(id => Boolean(shareMapApi?.available?.(id)))
        let preset = getSharePreset(activeSharePresetId)
        if (preset.id === 'map' && (!mapCompatible || !mapConfigured)) {
            const previous = getSharePreset(lastNonMapSharePresetId)
            activeSharePresetId = previous.id === 'map' ? SHARE_PRESET_FALLBACK : previous.id
            preset = getSharePreset(activeSharePresetId)
            syncShareColorSelection(activeSharePresetId)
        }
        const colorVisible = preset.mode === 'stats'
        if (shareColorOptions) shareColorOptions.hidden = !colorVisible
        if (shareMapStyleOptions) shareMapStyleOptions.hidden = preset.mode !== 'map' || !mapCompatible
        if (shareMapOption) shareMapOption.hidden = true
        shareMapStyleInputs.forEach(input => {
            const available = Boolean(shareMapApi?.available?.(input.value))
            input.disabled = !available
            const label = input.closest('label')
            label?.classList.toggle('is-disabled', !available)
            if (!available) label?.setAttribute('title', tr('activity.share.map_config_required'))
            else label?.removeAttribute('title')
        })
        if (preset.mode === 'map' && !shareMapStyleInputs.some(input => input.checked && !input.disabled)) setShareMapStyleValue('street', {persist: false})
        if (sharePhotoField) sharePhotoField.hidden = preset.mode !== 'photo'
        if (shareMobilePhotoButton) {
            shareMobilePhotoButton.hidden = preset.mode !== 'photo'
            shareMobilePhotoButton.textContent = sharePhoto ? tr('activity.share.change_photo', {}, 'Trocar foto') : tr('activity.share.choose_photo')
        }

        if (sharePreviewShell) {
            sharePreviewShell.classList.toggle('is-transparent', preset.mode === 'transparent')
            sharePreviewShell.classList.toggle('is-square', format.id === 'square')
            sharePreviewShell.classList.toggle('is-portrait', format.id === 'portrait')
            sharePreviewShell.classList.toggle('is-compact', compact || activeShareCompositionId === 'session_compact')
            sharePreviewShell.classList.toggle('is-compact-wide', compact && format.id === 'square')
        }
        if (sharePreviewFormat) {
            const scopeLabel = scope === SHARE_SCOPES.singleSegment
                ? tr('activity.share.scope_single_segment')
                : scope === SHARE_SCOPES.session
                    ? tr('activity.share.scope_session')
                    : scope === SHARE_SCOPES.multipleSegments
                        ? tr('activity.share.scope_multiple_segments')
                        : format.label
            const compositionLabel = definition?.label ? ` · ${definition.label}` : ''
            sharePreviewFormat.textContent = `${scopeLabel}${compositionLabel} · ${format.width} × ${format.height}`
        }

        shareContentGrid?.querySelectorAll('[data-share-content-card]').forEach(card => {
            const content = getShareContentPreset(card.dataset.shareContentValue)
            const unavailable = !activityEditor || Boolean(content.requiresRoute && !hasRoute)
            card.hidden = unavailable
            card.disabled = unavailable
            card.classList.toggle('is-active', !unavailable && card.dataset.shareContentValue === activeShareContentId)
        })
        shareStyleGrid?.querySelectorAll('[data-share-preset-card]').forEach(card => {
            const candidate = getSharePreset(card.dataset.sharePresetValue)
            const hidden = candidate.id === 'map' && !mapCompatible
            const unavailable = candidate.id === 'map' && !mapConfigured
            card.hidden = hidden
            card.disabled = unavailable
            card.classList.toggle('is-disabled', unavailable)
            card.setAttribute('aria-disabled', unavailable ? 'true' : 'false')
            card.title = candidate.id === 'map' && !mapConfigured ? tr('activity.share.map_config_required') : ''
        })

        const headingLabel = shareHeadingMode?.closest('label')
        if (headingLabel) headingLabel.hidden = !activityEditor || !definition.supportsTitle
        const captionLabel = shareCaption?.closest('label')
        if (captionLabel) captionLabel.hidden = !activityEditor
        if (shareComparisonControls) shareComparisonControls.hidden = activeShareCompositionId !== 'session_comparison'
        if (activeShareCompositionId === 'session_comparison') populateShareComparisonControls()
        if (shareSessionCompactControls) shareSessionCompactControls.hidden = activeShareCompositionId !== 'session_compact'
        if (activeShareCompositionId === 'session_compact') populateShareCompactMetricControls()

        syncSharePresetCards()
        syncShareContentCards()
        syncShareSessionLayoutCards()
        syncShareRouteScaleLabel()
        scheduleSharePreviewFit()
        if (shareDownloadButton) shareDownloadButton.textContent = tr('activity.share.download')
        if (shareNativeButton) shareNativeButton.textContent = tr('activity.share.native')
    }

    const routeSegmentMeters = (a, b) => {
        const toRad = value => value * Math.PI / 180
        const lat1 = toRad(Number(a?.[1]) || 0)
        const lat2 = toRad(Number(b?.[1]) || 0)
        const dLat = lat2 - lat1
        const dLon = toRad((Number(b?.[0]) || 0) - (Number(a?.[0]) || 0))
        const h = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon / 2) ** 2
        return 6371000 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(Math.max(0, 1 - h)))
    }
    const trimRouteStart = (coordinates, meters) => {
        const source = Array.isArray(coordinates) ? coordinates.filter(point => Array.isArray(point) && point.length >= 2) : []
        const target = Math.max(0, Number(meters) || 0)
        if (target <= 0 || source.length < 2) return source.slice()
        let consumed = 0
        for (let index = 1; index < source.length; index += 1) {
            const previous = source[index - 1]
            const current = source[index]
            const segment = routeSegmentMeters(previous, current)
            if (consumed + segment >= target && segment > 0) {
                const ratio = Math.min(1, Math.max(0, (target - consumed) / segment))
                const cut = [
                    Number(previous[0]) + (Number(current[0]) - Number(previous[0])) * ratio,
                    Number(previous[1]) + (Number(current[1]) - Number(previous[1])) * ratio,
                ]
                return [cut, ...source.slice(index)]
            }
            consumed += segment
        }
        return []
    }
    const routeCoordinatesForSharing = data => {
        const coordinates = data?.geojson?.coordinates || []
        if (!Array.isArray(coordinates) || coordinates.length < 2) return []
        const start = Math.max(0, Number(data?.ocultar_inicio_m) || 0)
        const end = Math.max(0, Number(data?.ocultar_fim_m) || 0)
        const cacheKey = `${start}:${end}`
        let cachedByTrim = shareRouteCoordinateCache.get(coordinates)
        if (cachedByTrim?.has(cacheKey)) return cachedByTrim.get(cacheKey)
        let result = trimRouteStart(coordinates, start)
        if (result.length >= 2 && end > 0) result = trimRouteStart(result.slice().reverse(), end).reverse()
        result = result.length >= 2 ? result : []
        if (!cachedByTrim) {
            cachedByTrim = new Map()
            shareRouteCoordinateCache.set(coordinates, cachedByTrim)
        }
        cachedByTrim.set(cacheKey, result)
        return result
    }

    const shareSegmentIdentity = (segment, index) => String(segment?.id ?? segment?.idunidade ?? segment?.unit_id ?? index + 1)
    const buildShareRenderState = (data, legacyScope = getShareScope()) => {
        const scope = normalizeShareScope(legacyScope)
        const segments = shareableSegmentsForData(data)
        let selectedIndexes = []
        if (scope === SHARE_SCOPES.singleSegment) {
            selectedIndexes = data === shareData ? [getActiveShareSegmentIndex()] : (segments.length ? [0] : [])
        } else if (scope === SHARE_SCOPES.multipleSegments) {
            selectedIndexes = data === shareData ? getSelectedShareSegmentIndexes() : segments.map((_, index) => index)
        } else if (scope === SHARE_SCOPES.session) {
            selectedIndexes = segments.map((_, index) => index)
        }
        selectedIndexes = selectedIndexes.filter(index => Number.isInteger(index) && index >= 0 && index < segments.length)
        const routes = []
        const mainCoordinates = routeCoordinatesForSharing(data)
        if (mainCoordinates.length >= 2) {
            routes.push({
                id: 'activity',
                segmentId: null,
                geometry: {type: 'LineString', coordinates: mainCoordinates},
                bounds: shareGeometryBounds(mainCoordinates),
            })
        }
        segments.forEach((segment, index) => {
            const coordinates = routeCoordinatesForSharing(segment)
            if (coordinates.length < 2) return
            routes.push({
                id: `segment:${shareSegmentIdentity(segment, index)}`,
                segmentId: shareSegmentIdentity(segment, index),
                geometry: {type: 'LineString', coordinates},
                bounds: shareGeometryBounds(coordinates),
            })
        })
        const selectedSegmentIds = selectedIndexes.map(index => shareSegmentIdentity(segments[index], index))
        const sessionMetrics = data === shareData && [SHARE_SCOPES.session, SHARE_SCOPES.multipleSegments].includes(scope)
            ? sessionShareMetrics(selectedIndexes.length ? selectedIndexes : segments.map((_, index) => index))
            : availableShareMetrics(data)
        return {
            scope,
            routes,
            segments: segments.map((segment, index) => ({...segment, shareSegmentId: shareSegmentIdentity(segment, index)})),
            selectedSegmentIds,
            sessionMetrics,
        }
    }

    const readShareConfiguration = (override = {}) => {
        const preset = override.preset || getSharePreset(override.presetId || activeSharePresetId)
        const metrics = canonicalizeShareMetrics(override.selectedMetrics || getSelectedShareMetrics(override.data || shareData)).slice(0, 4)
        const mode = override.mode || preset.mode
        const color = override.color || getSharePresetColor(preset.id) || getShareColorValue()
        const format = getShareFormat(override.format || getShareFormatValue())
        const story = format.id === 'story'
        const compact = String(override.composition || activeShareCompositionId) === 'compact'
        const content = override.content || activeShareContentId
        const data = override.data || shareData
        const headingMode = String(override.headingMode || getShareHeadingMode())
        const headingText = shareHeadingText(data, headingMode)
        const routeCoordinates = routeCoordinatesForSharing(data)
        const mapStyle = String(override.mapStyle || getShareMapStyleValue() || 'street')
        const scope = normalizeShareScope(override.scope || getShareScope())
        const renderState = buildShareRenderState(data, scope)
        const composition = compact ? 'compact' : 'standard'
        const compositionDefinition = SHARE_COMPOSITION_REGISTRY[composition]
        const compatibilityContext = {
            scope,
            format: format.id,
            segmentCount: renderState.selectedSegmentIds.length || renderState.segments.length,
            geometryCount: renderState.routes.filter(route => route.segmentId !== null || scope === SHARE_SCOPES.activity).length,
            comparableSegmentCount: [SHARE_SCOPES.session, SHARE_SCOPES.multipleSegments].includes(scope)
                ? shareComparableSegmentCountForScope(scope)
                : 0,
            requiresMap: !compact && Boolean(override.showMapBase ?? (preset.id === 'map' && content === 'route')),
            requiresTitle: !compact && Boolean(override.showTitle ?? headingMode !== 'none'),
        }
        const layoutModel = Object.freeze({
            scope,
            format: format.id,
            composition,
            anchors: format.anchors,
            contentStage: format.anchors.contentStage,
        })
        return {
            story,
            format: format.id,
            composition,
            scope,
            layoutModel,
            compositionDefinition,
            compositionCompatible: shareCompositionCompatibility(composition, compatibilityContext),
            compatibleCompositions: compatibleShareCompositions(compatibilityContext).map(definition => definition.id),
            renderState,
            routes: renderState.routes,
            segments: renderState.segments,
            selectedSegmentIds: renderState.selectedSegmentIds,
            sessionMetrics: renderState.sessionMetrics,
            mode,
            color,
            content,
            background: mode === 'photo' ? 'photo' : mode === 'transparent' ? 'transparent' : mode === 'map' ? `map:${mapStyle}` : color,
            mapStyle,
            showMapBase: Boolean(content === 'route' && (override.showMapBase ?? (preset.id === 'map')) && routeCoordinates.length >= 2),
            showRoads: false,
            roadFade: false,
            mapFade: false,
            headingMode,
            headingText,
            showTitle: compact ? false : Boolean(override.showTitle ?? headingMode !== 'none'),
            showRoute: Boolean(content === 'route' && (override.showRoute ?? true) && routeCoordinates.length >= 2),
            showDate: Boolean(override.showDate ?? shareShows('date')),
            showLogo: true,
            caption: String(override.caption ?? shareCaption?.value ?? '').trim().slice(0, 60),
            selectedMetrics: metrics,
            width: override.width || format.width,
            height: override.height || format.height,
            mapRasterScale: Math.max(.4, Math.min(1, Number(override.mapRasterScale) || 1)),
            renderPurpose: String(override.renderPurpose || 'export'),
            thumbnail: Boolean(override.thumbnail),
            data,
            routeCoordinates,
            routeScale: Number(override.routeScale || getShareRouteScaleValue() || 100),
        }
    }
    const syncSharePresetCards = () => {
        shareStyleGrid?.querySelectorAll('[data-share-preset-card]').forEach(card => card.classList.toggle('is-active', card.dataset.sharePresetValue === activeSharePresetId))
    }
    const syncShareContentCards = () => {
        shareContentGrid?.querySelectorAll('[data-share-content-card]').forEach(card => card.classList.toggle('is-active', card.dataset.shareContentValue === activeShareContentId))
    }
    const syncShareSessionLayoutCards = () => {
        shareSessionLayoutGrid?.querySelectorAll('[data-share-session-layout-card]').forEach(card => card.classList.toggle('is-active', card.dataset.shareSessionLayoutValue === activeShareCompositionId))
    }
    const buildShareContentCards = () => {
        if (!shareContentGrid || shareContentGrid.childElementCount) return
        SHARE_CONTENT_PRESETS.forEach(content => {
            const button = document.createElement('button')
            button.type = 'button'
            button.className = 'activity-share-content-card'
            button.dataset.shareContentCard = ''
            button.dataset.shareContentValue = content.id
            const canvas = document.createElement('canvas')
            canvas.width = 240
            canvas.height = 320
            canvas.dataset.shareContentPreview = content.id
            const title = document.createElement('strong')
            title.textContent = content.label
            button.append(canvas, title)
            button.addEventListener('click', () => applyShareContentPreset(content.id))
            shareContentGrid.append(button)
        })
    }
    const buildShareSessionLayoutCards = () => {
        if (!shareSessionLayoutGrid || shareSessionLayoutGrid.childElementCount) return
        const orderedLayouts = [
            'session_overview',
            'session_by_segment',
            'session_comparison',
            'session_highlight',
            'session_sequence',
            'session_summary',
            'session_list',
            'session_minimal',
            'session_compact',
        ]
        orderedLayouts.forEach(id => {
            const layout = SHARE_COMPOSITION_REGISTRY[id]
            if (!layout) return
            const button = document.createElement('button')
            button.type = 'button'
            button.className = 'activity-share-session-layout-card'
            button.dataset.shareSessionLayoutCard = ''
            button.dataset.shareSessionLayoutValue = layout.id
            const preview = document.createElement('span')
            preview.className = `activity-share-session-layout-preview is-${layout.id.replace(/^session_/, '').replaceAll('_', '-')}`
            preview.setAttribute('aria-hidden', 'true')
            preview.innerHTML = '<i></i><i></i><i></i><i></i><i></i>'
            const title = document.createElement('strong')
            title.textContent = layout.label
            button.append(preview, title)
            button.addEventListener('click', () => {
                activeShareCompositionId = layout.id
                syncShareSessionLayoutCards()
                updateShareControlAvailability()
                populateShareComparisonControls()
                scheduleShareDraw()
            })
            shareSessionLayoutGrid.append(button)
        })
    }
    const applyShareContentPreset = (contentId, {remember = true} = {}) => {
        const content = getShareContentPreset(contentId)
        const candidate = getShareScope() === SHARE_SCOPES.singleSegment ? shareDataForSegment(getActiveShareSegmentIndex()) : shareData
        if (content.requiresRoute && !shareHasRoute(candidate)) return
        activeShareContentId = content.id
        if (shareActivityRouteToggle) shareActivityRouteToggle.checked = content.id === 'route'
        if (remember) {
            try { localStorage.setItem(SHARE_CONTENT_KEY, content.id) } catch (_) {}
        }
        updateShareControlAvailability()
        syncShareContentCards()
        scheduleShareDraw()
    }
    const centerActiveSharePreset = () => {
        if (!shareStyleGrid || window.innerWidth > 900) return
        const active = shareStyleGrid.querySelector(`[data-share-preset-value="${activeSharePresetId}"]`)
        active?.scrollIntoView?.({behavior: 'smooth', block: 'nearest', inline: 'center'})
    }
    const buildSharePresetCards = () => {
        if (!shareStyleGrid || shareStyleGrid.childElementCount) return
        SHARE_CARD_PRESETS.forEach(preset => {
            const button = document.createElement('button')
            button.type = 'button'
            button.className = 'activity-share-style-card'
            button.dataset.sharePresetCard = ''
            button.dataset.sharePresetValue = preset.id
            const canvas = document.createElement('canvas')
            canvas.width = 240
            canvas.height = 320
            canvas.dataset.sharePresetPreview = preset.id
            const title = document.createElement('strong')
            title.textContent = preset.label
            button.append(canvas, title)
            button.addEventListener('click', () => applySharePreset(preset.id))
            shareStyleGrid.append(button)
        })
    }
    const applySharePreset = (presetId, {remember = true} = {}) => {
        const previousPreset = getSharePreset(activeSharePresetId)
        const previousColor = getSharePresetColor(previousPreset.id) || getShareColorValue()
        const preset = getSharePreset(presetId)
        activeSharePresetId = preset.id
        if (preset.id !== 'map') lastNonMapSharePresetId = preset.id
        if ((preset.mode === 'stats' || preset.mode === 'map') && (previousPreset.mode === 'stats' || previousPreset.mode === 'map')) {
            setSharePresetColor(preset.id, previousColor, {persist: remember})
        } else {
            syncShareColorSelection(preset.id)
        }
        const routeToggle = shareSessionRouteOption && !shareSessionRouteOption.hidden ? shareSessionRouteToggle : shareActivityRouteToggle
        if (routeToggle && preset.requiresRoute && shareHasRoute(currentShareRouteData())) routeToggle.checked = true
        if (preset.id === 'photo' && !sharePhoto && sharePhotoName) sharePhotoName.textContent = tr('activity.share.no_photo')
        if (remember) {
            try { localStorage.setItem(SHARE_PRESET_KEY, preset.id) } catch (_) {}
        }
        updateShareControlAvailability()
        syncSharePresetCards()
        centerActiveSharePreset()
        scheduleShareDraw()
    }
    const applySharePreferenceToEditor = (data, value, scope = getShareScope()) => {
        const preference = normalizeSharePreference(value, data, scope)
        selectShareFormat(preference.format)
        activeShareCompositionId = preference.composition
        shareCompositionInputs.forEach(input => { input.checked = input.value === activeShareCompositionId })
        activeShareContentId = preference.content
        if (shareHeadingMode) shareHeadingMode.checked = preference.headingMode !== 'none'
        if (shareRouteScaleInput) shareRouteScaleInput.value = String(preference.routeScale)
        setShareMapStyleValue(preference.mapStyle, {persist: false})
        syncShareRouteScaleLabel()
        ;['route', 'date'].forEach(key => {
            if (key === 'route') {
                if (shareActivityRouteToggle) shareActivityRouteToggle.checked = preference.showRoute
                if (shareSessionRouteToggle) {
                    shareSessionRouteToggle.checked = preference.showRoute
                    shareSessionRouteToggle.dataset.sessionTouched = preference.showRoute ? '1' : '0'
                }
                return
            }
            const input = shareModal?.querySelector(`[data-share-show="${key}"]`)
            if (input) input.checked = preference.showDate
        })
        applySharePreset(preference.preset, {remember: false})
        setSharePresetColor(preference.preset, preference.color, {persist: false})
        syncShareColorSelection(preference.preset)
        syncShareContentCards()
        const metricInputs = Array.from(shareMetricOptions?.querySelectorAll('[data-share-metric]') || [])
        const matched = metricInputs.filter(input => preference.metricKeys.includes(String(input.dataset.metricKey || '')))
        if (preference.metricKeys.length && matched.length) metricInputs.forEach(input => { input.checked = matched.includes(input) })
        if (scope !== SHARE_SCOPES.activity && activeShareCompositionId === 'session_compact') {
            populateShareCompactMetricControls()
            if (shareSessionCompactPrimary && preference.compactPrimaryMetric) shareSessionCompactPrimary.value = preference.compactPrimaryMetric
            if (shareSessionCompactSecondary) shareSessionCompactSecondary.value = preference.compactSecondaryMetric || ''
        }
        updateShareControlAvailability()
        scheduleShareDraw()
        return preference
    }
    const enableShareStyleScroller = () => {
        if (!shareStyleGrid || shareStyleGrid.dataset.scrollReady === '1') return
        shareStyleGrid.dataset.scrollReady = '1'
        let pointerId = null
        let startX = 0
        let startLeft = 0
        let moved = false
        shareStyleGrid.addEventListener('wheel', event => {
            if (Math.abs(event.deltaY) <= Math.abs(event.deltaX)) return
            event.preventDefault()
            shareStyleGrid.scrollLeft += event.deltaY
        }, {passive: false})
        shareStyleGrid.addEventListener('pointerdown', event => {
            if (event.button !== 0) return
            pointerId = event.pointerId
            startX = event.clientX
            startLeft = shareStyleGrid.scrollLeft
            moved = false
        })
        shareStyleGrid.addEventListener('pointermove', event => {
            if (pointerId !== event.pointerId) return
            const delta = event.clientX - startX
            if (!moved && Math.abs(delta) > 6) {
                moved = true
                shareStyleGrid.setPointerCapture?.(pointerId)
                shareStyleGrid.classList.add('is-dragging')
            }
            if (moved) shareStyleGrid.scrollLeft = startLeft - delta
        })
        const finish = event => {
            if (pointerId !== event.pointerId) return
            if (moved) shareStyleGrid.releasePointerCapture?.(pointerId)
            pointerId = null
            shareStyleGrid.classList.remove('is-dragging')
        }
        shareStyleGrid.addEventListener('pointerup', finish)
        shareStyleGrid.addEventListener('pointercancel', finish)
        shareStyleGrid.addEventListener('click', event => {
            if (!moved) return
            event.preventDefault()
            event.stopPropagation()
            moved = false
        }, true)
    }
    const shareMapProvider = style => window.StrideBRBasemaps?.share?.definition?.(style) || null
    const loadMapTile = (style, z, x, y) => {
        const tile = window.StrideBRBasemaps?.share?.tile?.(style, z, x, y)
        if (!tile?.url) return Promise.resolve(null)
        const key = `${style}:${z}/${x}/${y}`
        if (tileCache.has(key)) return tileCache.get(key)
        const promise = new Promise(resolve => {
            const image = new Image()
            let done = false
            const finish = value => { if (done) return; done = true; window.clearTimeout(timer); resolve(value) }
            const timer = window.setTimeout(() => finish(null), 6000)
            image.crossOrigin = 'anonymous'
            image.referrerPolicy = 'strict-origin-when-cross-origin'
            image.decoding = 'async'
            image.onload = () => finish(image)
            image.onerror = () => finish(null)
            image.src = tile.url
        }).then(image => {
            if (!image) tileCache.delete(key)
            return image
        })
        tileCache.set(key, promise)
        return promise
    }
    const loadMapTileWithFallback = async (style, z, x, y) => {
        const requested = await loadMapTile(style, z, x, y)
        if (requested) return {image: requested, zoom: z, x, y, requestedZoom: z}
        if (style !== 'satellite') return null
        const provider = shareMapProvider(style)
        const floor = Math.max(3, Number(provider?.minFallbackZoom) || 3)
        for (let parentZoom = z - 1; parentZoom >= floor; parentZoom -= 1) {
            const factor = 2 ** (z - parentZoom)
            const parentX = Math.floor(x / factor)
            const parentY = Math.floor(y / factor)
            const image = await loadMapTile(style, parentZoom, parentX, parentY)
            if (image) return {image, zoom: parentZoom, x: parentX, y: parentY, requestedZoom: z, childX: ((x % factor) + factor) % factor, childY: ((y % factor) + factor) % factor, factor}
        }
        return null
    }
    const shareMapTileRange = (viewport, style, width, height) => {
        const provider = shareMapProvider(style)
        if (!provider?.available || !viewport?.canvasToWorld) return null
        const tileSize = Math.max(1, Number(provider.worldTileSize) || 256)
        const start = viewport.canvasToWorld(0, 0)
        const end = viewport.canvasToWorld(width, height)
        const minX = Math.min(start.x, end.x)
        const maxX = Math.max(start.x, end.x)
        const minY = Math.min(start.y, end.y)
        const maxY = Math.max(start.y, end.y)
        return {
            tileSize,
            x0: Math.floor(minX / tileSize) - 1,
            x1: Math.floor(maxX / tileSize) + 1,
            y0: Math.floor(minY / tileSize) - 1,
            y1: Math.floor(maxY / tileSize) + 1,
        }
    }
    const shareMapTileCount = (viewport, style, width, height) => {
        const range = shareMapTileRange(viewport, style, width, height)
        if (!range) return Infinity
        return Math.max(0, range.x1 - range.x0 + 1) * Math.max(0, range.y1 - range.y0 + 1)
    }
    const createShareMapViewport = (coordinates, canvasWidth, canvasHeight, frame, routeScale, style, rasterScale = .55) => {
        const fill = shareRouteFillForScale(routeScale)
        const provider = shareMapProvider(style)
        const maxZoom = Math.max(3, Math.min(23, Number(provider?.maxZoom) || 22))
        const density = Math.max(.4, Math.min(1, Number(rasterScale) || .55))
        let viewport = createMapViewport(coordinates, canvasWidth, canvasHeight, frame, fill, 3)
        for (let zoom = 3; zoom <= maxZoom; zoom += 1) {
            const candidate = createMapViewport(coordinates, canvasWidth, canvasHeight, frame, fill, zoom)
            viewport = candidate
            if ((candidate?.outputScale || Infinity) * density <= 1.01) break
        }
        const tileLimit = density >= .95 ? 96 : 48
        while (viewport?.zoom > 3 && shareMapTileCount(viewport, style, canvasWidth, canvasHeight) > tileLimit) {
            const lower = createMapViewport(coordinates, canvasWidth, canvasHeight, frame, fill, viewport.zoom - 1)
            if (density >= .95 && (lower?.outputScale || Infinity) * density > 1.08) break
            viewport = lower
        }
        return viewport
    }
    const shareStreetLabelFreeKey = (configuration, viewport, rasterScale) => {
        if (!viewport) return ''
        const coordinates = Array.isArray(configuration.routeCoordinates) ? configuration.routeCoordinates : []
        const bounds = coordinates.length >= 2 ? routeBounds(coordinates) : null
        return [
            'street-label-free', configuration.width, configuration.height, Number(rasterScale).toFixed(2), viewport.zoom,
            viewport.left.toFixed(2), viewport.top.toFixed(2), viewport.outputScale.toFixed(5),
            bounds ? `${bounds.south.toFixed(4)}:${bounds.west.toFixed(4)}:${bounds.north.toFixed(4)}:${bounds.east.toFixed(4)}` : ''
        ].join(':')
    }
    const trimShareStreetLabelFreeCache = () => {
        while (shareStreetLabelFreeCache.size > 4) shareStreetLabelFreeCache.delete(shareStreetLabelFreeCache.keys().next().value)
    }
    const drawShareLabelFreeStreetBackground = async (context, {width, height, viewport, routeCoordinates, token}) => {
        context.fillStyle = '#dce2e6'
        context.fillRect(0, 0, width, height)
        if (!viewport || !Array.isArray(routeCoordinates) || routeCoordinates.length < 2) return {drawn:false, attribution:'Map data © OpenStreetMap contributors'}
        const geometry = await fetchRoadNetwork(routeCoordinates)
        if (Number.isFinite(token) && token !== shareRenderToken) return {drawn:false, stale:true, attribution:'Map data © OpenStreetMap contributors'}
        const roads = Array.isArray(geometry?.roads) ? geometry.roads : []
        const areas = Array.isArray(geometry?.areas) ? geometry.areas : []
        if (!roads.length && !areas.length) return {drawn:false, attribution:'Map data © OpenStreetMap contributors'}
        const project = viewport.project.bind(viewport)
        const routePoints = routeCoordinates.map(([lon, lat]) => project(lon, lat))
        drawMapAreas(context, areas, project, {variant:'light'})
        const roadsDrawn = drawBaseRoadNetwork(context, roads, routePoints, project, {variant:'light', thumbnail:false})
        context.fillStyle = 'rgba(12,25,42,.16)'
        context.fillRect(0, 0, width, height)
        const shade = context.createLinearGradient(0, 0, 0, height)
        shade.addColorStop(0, 'rgba(5,13,24,.08)')
        shade.addColorStop(.52, 'rgba(5,13,24,.02)')
        shade.addColorStop(1, 'rgba(5,13,24,.26)')
        context.fillStyle = shade
        context.fillRect(0, 0, width, height)
        return {drawn:Boolean(roadsDrawn || areas.length), attribution:'Map data © OpenStreetMap contributors'}
    }
    const drawShareMapTiles = async (context, {width, height, viewport, style, token}) => {
        const provider = shareMapProvider(style)
        const range = shareMapTileRange(viewport, style, width, height)
        context.fillStyle = '#071225'
        context.fillRect(0, 0, width, height)
        if (!provider?.available || !range) return {drawn: false, attribution: ''}
        const jobs = []
        for (let y = range.y0; y <= range.y1; y += 1) {
            for (let x = range.x0; x <= range.x1; x += 1) jobs.push({x, y, tile: loadMapTileWithFallback(style, viewport.zoom, x, y)})
        }
        const loaded = await Promise.all(jobs.map(async job => ({...job, tile: await job.tile})))
        if (token !== shareRenderToken) return {drawn: false, attribution: provider.attribution || ''}
        let drawn = 0
        loaded.forEach(({x, y, tile}) => {
            if (!tile?.image) return
            const [sx, sy] = viewport.worldToCanvas(x * range.tileSize, y * range.tileSize)
            const size = range.tileSize * viewport.outputScale
            if (tile.factor && tile.factor > 1) {
                const sourceWidth = tile.image.naturalWidth / tile.factor
                const sourceHeight = tile.image.naturalHeight / tile.factor
                context.drawImage(tile.image, tile.childX * sourceWidth, tile.childY * sourceHeight, sourceWidth, sourceHeight, sx, sy, size + 1, size + 1)
            } else context.drawImage(tile.image, sx, sy, size + 1, size + 1)
            drawn += 1
        })
        if (drawn) {
            context.fillStyle = style === 'satellite' ? 'rgba(2,7,16,.24)' : 'rgba(4,10,18,.28)'
            context.fillRect(0, 0, width, height)
            const shade = context.createLinearGradient(0, 0, 0, height)
            shade.addColorStop(0, 'rgba(3,8,16,.18)')
            shade.addColorStop(.48, 'rgba(3,8,16,.03)')
            shade.addColorStop(1, 'rgba(3,8,16,.46)')
            context.fillStyle = shade
            context.fillRect(0, 0, width, height)
        }
        return {drawn: drawn > 0, attribution: provider.attribution || ''}
    }
    const shareMapPreviewCacheKey = (configuration, viewport) => {
        const scale = Math.max(.4, Math.min(1, Number(configuration.mapRasterScale) || .55))
        if (scale >= .95 || !viewport) return ''
        return [
            configuration.mapStyle || 'street', configuration.width, configuration.height, scale.toFixed(2), viewport.zoom,
            viewport.left.toFixed(2), viewport.top.toFixed(2), viewport.outputScale.toFixed(5)
        ].join(':')
    }
    const trimShareMapPreviewCache = () => {
        while (shareMapPreviewCache.size > 4) shareMapPreviewCache.delete(shareMapPreviewCache.keys().next().value)
    }
    const compositeShareMapBackground = async (context, configuration, viewport, token) => {
        const rasterScale = Math.max(.4, Math.min(1, Number(configuration.mapRasterScale) || .55))
        const style = String(configuration.mapStyle || 'street')
        const cacheKey = shareMapPreviewCacheKey(configuration, viewport)
        if (style === 'street') {
            const labelFreeKey = shareStreetLabelFreeKey(configuration, viewport, rasterScale)
            const cachedLabelFree = labelFreeKey ? shareStreetLabelFreeCache.get(labelFreeKey) : null
            if (cachedLabelFree?.canvas) {
                context.save()
                context.globalCompositeOperation = 'destination-over'
                context.drawImage(cachedLabelFree.canvas, 0, 0, configuration.width, configuration.height)
                context.restore()
                return {drawn:true, attribution:cachedLabelFree.attribution || 'Map data © OpenStreetMap contributors', labelFree:true}
            }
            let labelFreeRequest = labelFreeKey ? shareStreetLabelFreeRequests.get(labelFreeKey) : null
            if (!labelFreeRequest && labelFreeKey) {
                const labelCanvas = document.createElement('canvas')
                labelCanvas.width = Math.max(1, Math.round(configuration.width * rasterScale))
                labelCanvas.height = Math.max(1, Math.round(configuration.height * rasterScale))
                const labelContext = labelCanvas.getContext('2d', {alpha:false})
                labelContext.setTransform(rasterScale, 0, 0, rasterScale, 0, 0)
                labelFreeRequest = drawShareLabelFreeStreetBackground(labelContext, {
                    width:configuration.width,
                    height:configuration.height,
                    viewport,
                    routeCoordinates:Array.isArray(configuration.routeCoordinates) ? configuration.routeCoordinates : [],
                }).then(result => {
                    shareStreetLabelFreeRequests.delete(labelFreeKey)
                    if (!result?.drawn) return null
                    const entry = {canvas:labelCanvas, attribution:result.attribution || 'Map data © OpenStreetMap contributors'}
                    shareStreetLabelFreeCache.set(labelFreeKey, entry)
                    trimShareStreetLabelFreeCache()
                    return entry
                }).catch(() => {
                    shareStreetLabelFreeRequests.delete(labelFreeKey)
                    return null
                })
                shareStreetLabelFreeRequests.set(labelFreeKey, labelFreeRequest)
            }
            if (String(configuration.renderPurpose || 'preview') === 'export' && labelFreeRequest) {
                const ready = await labelFreeRequest
                if (token !== shareRenderToken) return {drawn:false, attribution:''}
                if (ready?.canvas) {
                    context.save()
                    context.globalCompositeOperation = 'destination-over'
                    context.drawImage(ready.canvas, 0, 0, configuration.width, configuration.height)
                    context.restore()
                    return {drawn:true, attribution:ready.attribution || 'Map data © OpenStreetMap contributors', labelFree:true}
                }
            }
            const fallbackCanvas = document.createElement('canvas')
            fallbackCanvas.width = Math.max(1, Math.round(configuration.width * rasterScale))
            fallbackCanvas.height = Math.max(1, Math.round(configuration.height * rasterScale))
            const fallbackContext = fallbackCanvas.getContext('2d', {alpha:false})
            fallbackContext.setTransform(rasterScale, 0, 0, rasterScale, 0, 0)
            const fallbackResult = await drawShareMapTiles(fallbackContext, {
                width:configuration.width,
                height:configuration.height,
                viewport,
                style:'street',
                token,
            })
            if (token !== shareRenderToken) return fallbackResult
            context.save()
            context.globalCompositeOperation = 'destination-over'
            context.drawImage(fallbackCanvas, 0, 0, configuration.width, configuration.height)
            context.restore()
            if (labelFreeRequest && String(configuration.renderPurpose || 'preview') !== 'export') {
                labelFreeRequest.then(ready => {
                    if (!ready?.canvas || token !== shareRenderToken) return
                    if (getSharePreset(activeSharePresetId).mode !== 'map' || getShareMapStyleValue() !== 'street') return
                    scheduleShareDraw()
                }).catch(() => {})
            }
            return {drawn:Boolean(fallbackResult?.drawn), attribution:fallbackResult?.attribution || 'Map data © OpenStreetMap contributors', labelFree:false}
        }
        const cached = cacheKey ? shareMapPreviewCache.get(cacheKey) : null
        if (cached?.canvas) {
            context.save()
            context.globalCompositeOperation = 'destination-over'
            context.drawImage(cached.canvas, 0, 0, configuration.width, configuration.height)
            context.restore()
            return {drawn:true, attribution:cached.attribution || ''}
        }
        const canvas = document.createElement('canvas')
        canvas.width = Math.max(1, Math.round(configuration.width * rasterScale))
        canvas.height = Math.max(1, Math.round(configuration.height * rasterScale))
        const backgroundContext = canvas.getContext('2d', {alpha:false})
        backgroundContext.setTransform(rasterScale, 0, 0, rasterScale, 0, 0)
        const result = await drawShareMapTiles(backgroundContext, {
            width:configuration.width,
            height:configuration.height,
            viewport,
            style,
            token,
        })
        if (token !== shareRenderToken) return result
        if (cacheKey && result.drawn) {
            shareMapPreviewCache.set(cacheKey, {canvas, attribution:result.attribution || ''})
            trimShareMapPreviewCache()
        }
        context.save()
        context.globalCompositeOperation = 'destination-over'
        context.drawImage(canvas, 0, 0, configuration.width, configuration.height)
        context.restore()
        return result
    }
    const chooseMapZoom = (coordinates, width, height, widthFactor = .52, heightFactor = .48) => {
        if (!Array.isArray(coordinates) || coordinates.length < 2) return 13
        for (let zoom = 16; zoom >= 3; zoom--) {
            const points = coordinates.map(([lon, lat]) => mercatorWorld(lon, lat, zoom))
            const xs = points.map(point => point.x)
            const ys = points.map(point => point.y)
            const spanX = Math.max(...xs) - Math.min(...xs)
            const spanY = Math.max(...ys) - Math.min(...ys)
            if (spanX <= width * widthFactor && spanY <= height * heightFactor) return zoom
        }
        return 3
    }
    const shareRouteFillForScale = value => {
        const percent = Math.max(50, Math.min(200, Number(value) || 100))
        if (percent <= 100) return .50 + ((percent - 50) / 50) * .18
        return .68 + ((percent - 100) / 100) * .26
    }
    const createMapViewport = (coordinates, canvasWidth, canvasHeight, frame = null, targetFill = .68, zoomOverride = null) => {
        if (!Array.isArray(coordinates) || coordinates.length < 2) return null
        const target = frame && Number(frame.width) > 0 && Number(frame.height) > 0
            ? {x: Number(frame.x) || 0, y: Number(frame.y) || 0, width: Number(frame.width), height: Number(frame.height)}
            : {x: 0, y: 0, width: canvasWidth, height: canvasHeight}
        const renderWidth = Math.max(240, Math.min(960, Math.round(target.width)))
        const renderHeight = Math.max(240, Math.min(1400, Math.round(target.height)))
        const zoom = Number.isInteger(zoomOverride) ? Math.max(3, Math.min(23, zoomOverride)) : chooseMapZoom(coordinates, renderWidth, renderHeight, .74, .74)
        const routeWorld = coordinates.map(([lon, lat]) => mercatorWorld(lon, lat, zoom))
        const minX = Math.min(...routeWorld.map(point => point.x))
        const maxX = Math.max(...routeWorld.map(point => point.x))
        const minY = Math.min(...routeWorld.map(point => point.y))
        const maxY = Math.max(...routeWorld.map(point => point.y))
        const rawSpanX = Math.max(0, maxX - minX)
        const rawSpanY = Math.max(0, maxY - minY)
        const dominantSpan = Math.max(rawSpanX, rawSpanY, 1)
        const spanX = Math.max(rawSpanX, dominantSpan * .035, 1)
        const spanY = Math.max(rawSpanY, dominantSpan * .035, 1)
        const fill = Math.max(.36, Math.min(.94, Number(targetFill) || .68))
        const baseScale = Math.min(target.width / spanX, target.height / spanY)
        const outputScale = Math.max(.000001, baseScale * fill)
        const viewWidth = target.width / outputScale
        const viewHeight = target.height / outputScale
        const centerX = (minX + maxX) / 2
        const centerY = (minY + maxY) / 2
        const left = centerX - viewWidth / 2
        const top = centerY - viewHeight / 2
        const outputOffsetX = target.x
        const outputOffsetY = target.y
        return {
            zoom,
            renderWidth,
            renderHeight,
            left,
            top,
            viewWidth,
            viewHeight,
            outputScale,
            outputOffsetX,
            outputOffsetY,
            routeWorld,
            frame: target,
            project(lon, lat) {
                const world = mercatorWorld(lon, lat, zoom)
                return [outputOffsetX + (world.x - left) * outputScale, outputOffsetY + (world.y - top) * outputScale]
            },
            canvasToWorld(x, y) {
                return {
                    x: left + (Number(x) - outputOffsetX) / outputScale,
                    y: top + (Number(y) - outputOffsetY) / outputScale,
                }
            },
            worldToCanvas(x, y) {
                return [outputOffsetX + (Number(x) - left) * outputScale, outputOffsetY + (Number(y) - top) * outputScale]
            }
        }
    }
    const roadColorByKind = kind => {
        if (/motorway|trunk|primary/.test(kind)) return {width: 3.8, alpha: .36}
        if (/secondary|tertiary/.test(kind)) return {width: 3.0, alpha: .31}
        if (/residential|unclassified|living_street|road/.test(kind)) return {width: 2.3, alpha: .26}
        return {width: 1.7, alpha: .22}
    }
    const routeBounds = coordinates => {
        const lons = coordinates.map(point => Number(point[0]))
        const lats = coordinates.map(point => Number(point[1]))
        const minLon = Math.min(...lons)
        const maxLon = Math.max(...lons)
        const minLat = Math.min(...lats)
        const maxLat = Math.max(...lats)
        const lonSpan = Math.max(maxLon - minLon, 0)
        const latSpan = Math.max(maxLat - minLat, 0)
        const dominantSpan = Math.max(lonSpan, latSpan, 0.0018)
        const padLon = Math.max(lonSpan * 1.35, dominantSpan * 0.9, 0.0054)
        const padLat = Math.max(latSpan * 1.12, dominantSpan * 0.72, 0.0044)
        return {south: minLat - padLat, west: minLon - padLon, north: maxLat + padLat, east: maxLon + padLon}
    }
    const fetchRoadNetwork = async (coordinates) => {
        if (!Array.isArray(coordinates) || coordinates.length < 2) return []
        const bbox = routeBounds(coordinates)
        const key = `${bbox.south.toFixed(4)}:${bbox.west.toFixed(4)}:${bbox.north.toFixed(4)}:${bbox.east.toFixed(4)}`
        if (roadCache.has(key)) return roadCache.get(key)
        const params = new URLSearchParams({
            south: bbox.south.toFixed(6),
            west: bbox.west.toFixed(6),
            north: bbox.north.toFixed(6),
            east: bbox.east.toFixed(6),
        })
        const promise = fetchWithRetry(`/api/map-geometry.php?${params.toString()}`, {credentials: 'same-origin', headers: {'Accept': 'application/json'}}, {timeout: 8000, retries: 1})
            .then(async response => {
                const payload = await response.json().catch(() => null)
                if (!response.ok || !payload?.ok || !Array.isArray(payload.roads)) throw new Error(payload?.error || tr('activity.geometry_unavailable'))
                return {roads: payload.roads.slice(0, 360), areas: Array.isArray(payload.areas) ? payload.areas.slice(0, 90) : []}
            })
            .catch(() => {
                roadCache.delete(key)
                return {roads: [], areas: []}
            })
        roadCache.set(key, promise)
        return promise
    }
    const distancePointToSegment = (point, start, end) => {
        const [px, py] = point
        const [ax, ay] = start
        const [bx, by] = end
        const dx = bx - ax
        const dy = by - ay
        if (dx === 0 && dy === 0) return Math.hypot(px - ax, py - ay)
        const t = Math.max(0, Math.min(1, ((px - ax) * dx + (py - ay) * dy) / (dx * dx + dy * dy)))
        return Math.hypot(px - (ax + dx * t), py - (ay + dy * t))
    }
    const distancePointToPolyline = (point, line) => {
        if (!Array.isArray(line) || line.length < 2) return Infinity
        let best = Infinity
        for (let index = 1; index < line.length; index++) best = Math.min(best, distancePointToSegment(point, line[index - 1], line[index]))
        return best
    }
    const edgeFadeAt = (position, softness = .18) => {
        if (position <= 0 || position >= 1) return 0
        if (position < softness) return position / softness
        if (position > 1 - softness) return (1 - position) / softness
        return 1
    }
    const distancePolylineToRoute = (points, routePoints) => {
        if (!Array.isArray(points) || points.length < 2 || !Array.isArray(routePoints) || routePoints.length < 2) return Infinity
        let best = Infinity
        for (let index = 0; index < points.length; index++) {
            const point = points[index]
            best = Math.min(best, distancePointToPolyline(point, routePoints))
            if (best <= 0.5) break
        }
        return best
    }
    const drawRoadPolyline = (context, points, {color = '66, 102, 236', alpha = .28, width = 2.2, fadeEnds = true, blur = true} = {}) => {
        if (!Array.isArray(points) || points.length < 2) return
        const segments = []
        let total = 0
        for (let index = 1; index < points.length; index++) {
            const start = points[index - 1]
            const end = points[index]
            const length = Math.hypot(end[0] - start[0], end[1] - start[1])
            if (length < .8) continue
            segments.push({start, end, from: total, to: total + length, length})
            total += length
        }
        if (!total) return
        context.save()
        context.lineCap = 'round'
        context.lineJoin = 'round'
        context.shadowBlur = blur ? width * 1.8 : 0
        context.shadowColor = `rgba(${color}, ${Math.min(alpha * .4, .16)})`
        segments.forEach(segment => {
            const startT = segment.from / total
            const endT = segment.to / total
            const startAlpha = alpha * (fadeEnds ? edgeFadeAt(startT) : 1)
            const endAlpha = alpha * (fadeEnds ? edgeFadeAt(endT) : 1)
            const gradient = context.createLinearGradient(segment.start[0], segment.start[1], segment.end[0], segment.end[1])
            gradient.addColorStop(0, `rgba(${color}, ${Math.max(startAlpha, .01)})`)
            gradient.addColorStop(1, `rgba(${color}, ${Math.max(endAlpha, .01)})`)
            context.beginPath()
            context.moveTo(segment.start[0], segment.start[1])
            context.lineTo(segment.end[0], segment.end[1])
            context.strokeStyle = gradient
            context.lineWidth = width
            context.stroke()
        })
        context.restore()
    }
    const drawMapAreas = (context, areas, project, {variant = 'light'} = {}) => {
        if (!Array.isArray(areas) || !areas.length || typeof project !== 'function') return false
        areas.forEach(area => {
            const points = area.coordinates.map(([lon, lat]) => project(lon, lat)).filter(point => Number.isFinite(point[0]) && Number.isFinite(point[1]))
            if (points.length < 3) return
            const kind = String(area.kind || '')
            const water = /water|reservoir/.test(kind)
            const green = /park|garden|grass|forest|meadow|wood|nature_reserve|recreation_ground/.test(kind)
            context.beginPath()
            points.forEach(([x, y], index) => index ? context.lineTo(x, y) : context.moveTo(x, y))
            context.closePath()
            if (variant === 'light') context.fillStyle = water ? 'rgba(177,214,232,.34)' : (green ? 'rgba(199,222,190,.30)' : 'rgba(223,226,220,.24)')
            else context.fillStyle = water ? 'rgba(50,100,140,.14)' : (green ? 'rgba(70,112,87,.12)' : 'rgba(120,130,145,.06)')
            context.fill()
        })
        return true
    }
    const drawBaseRoadNetwork = (context, roads, routePoints, project, {variant = 'light', thumbnail = false} = {}) => {
        if (!Array.isArray(roads) || !roads.length || !Array.isArray(routePoints) || routePoints.length < 2 || typeof project !== 'function') return false
        const maxDistance = thumbnail ? 240 : 390
        const palette = variant === 'light'
            ? {major: '124, 143, 167', minor: '181, 189, 198'}
            : {major: '124, 148, 190', minor: '83, 103, 139'}
        roads.forEach(road => {
            const points = road.coordinates.map(([lon, lat]) => project(lon, lat)).filter(point => Number.isFinite(point[0]) && Number.isFinite(point[1]))
            if (points.length < 2) return
            const distance = distancePolylineToRoute(points, routePoints)
            if (!Number.isFinite(distance) || distance > maxDistance) return
            const profile = roadColorByKind(road.kind)
            const isMajor = /motorway|trunk|primary|secondary/.test(road.kind)
            const color = isMajor ? palette.major : palette.minor
            const distanceFactor = Math.min(distance / maxDistance, 1)
            const baseAlpha = variant === 'light' ? (isMajor ? .64 : .46) : (isMajor ? .28 : .18)
            const alphaFloor = variant === 'light' ? .14 : .06
            const alpha = Math.max(alphaFloor, baseAlpha - distanceFactor * (variant === 'light' ? .26 : .10))
            const widthScale = thumbnail ? .78 : (distanceFactor < .18 ? 1.08 : 1)
            drawRoadPolyline(context, points, {color, alpha, width: profile.width * widthScale, fadeEnds: false, blur: false})
        })
        return true
    }
    const drawRoadNetwork = (context, roads, routePoints, project, {fadeEnds = true, thumbnail = false} = {}) => {
        if (!Array.isArray(roads) || !roads.length || !Array.isArray(routePoints) || routePoints.length < 2 || typeof project !== 'function') return false
        const maxDistance = thumbnail ? 170 : 285
        roads.forEach(road => {
            const points = road.coordinates.map(([lon, lat]) => project(lon, lat)).filter(point => Number.isFinite(point[0]) && Number.isFinite(point[1]))
            if (points.length < 2) return
            const distance = distancePolylineToRoute(points, routePoints)
            if (!Number.isFinite(distance) || distance > maxDistance) return
            const profile = roadColorByKind(road.kind)
            const distanceFactor = Math.min(distance / maxDistance, 1)
            const alphaDistance = Math.max(.075, profile.alpha + .03 - distanceFactor * .12)
            const widthScale = thumbnail ? .82 : (distanceFactor < .15 ? 1.14 : distanceFactor < .32 ? 1.06 : 1)
            drawRoadPolyline(context, points, {color: '76, 120, 244', alpha: alphaDistance, width: profile.width * widthScale, fadeEnds, blur: true})
        })
        return true
    }
    const drawPhotoPlaceholder = (context, width, height) => {
        const gradient = context.createLinearGradient(0, 0, 0, height)
        gradient.addColorStop(0, '#0f2744')
        gradient.addColorStop(.45, '#1a5f8a')
        gradient.addColorStop(1, '#1d3f61')
        context.fillStyle = gradient
        context.fillRect(0, 0, width, height)
        context.fillStyle = 'rgba(255,255,255,.18)'
        context.beginPath(); context.arc(width * .68, height * .22, width * .16, 0, Math.PI * 2); context.fill()
        context.fillStyle = 'rgba(255,255,255,.11)'
        context.beginPath(); context.moveTo(0, height * .82); context.lineTo(width * .26, height * .58); context.lineTo(width * .46, height * .72); context.lineTo(width * .7, height * .46); context.lineTo(width, height * .72); context.lineTo(width, height); context.lineTo(0, height); context.closePath(); context.fill()
    }
    const shareRouteColorWithAlpha = (color, alpha) => {
        const match = String(color || '').trim().match(/^#([0-9a-f]{6})$/i)
        if (!match) return color
        const value = match[1]
        const red = parseInt(value.slice(0, 2), 16)
        const green = parseInt(value.slice(2, 4), 16)
        const blue = parseInt(value.slice(4, 6), 16)
        return `rgba(${red}, ${green}, ${blue}, ${alpha})`
    }
    const resolveShareRouteVisualStyle = (story, lightSurface = false, options = {}) => {
        const widthScale = Math.max(.35, Math.min(5, Number(options.widthScale) || 1))
        const routeColor = String(options.color || (lightSurface ? '#5678e4' : SHARE_CARD_THEME.route))
        const coreColor = String(options.coreColor || (lightSurface ? '#ffffff' : '#f3f7ff'))
        const shadowColor = String(options.shadowColor || shareRouteColorWithAlpha(routeColor, lightSurface ? .30 : .50))
        return {
            widthScale,
            routeColor,
            coreColor,
            shadowColor,
            routeWidth: (story ? SHARE_ROUTE_STYLE.storyWidth : SHARE_ROUTE_STYLE.standardWidth) * widthScale,
            coreWidth: (story ? SHARE_ROUTE_STYLE.storyCoreWidth : SHARE_ROUTE_STYLE.standardCoreWidth) * widthScale,
            glow: options.glow === false ? 0 : (story ? SHARE_ROUTE_STYLE.storyGlow : SHARE_ROUTE_STYLE.standardGlow) * Math.max(.65, widthScale),
        }
    }
    const drawRoute = (context, routePoints, story, lightSurface = false, options = {}) => {
        if (!routePoints.length) return
        const style = resolveShareRouteVisualStyle(story, lightSurface, options)
        context.save()
        context.beginPath()
        routePoints.forEach(([x, y], index) => index ? context.lineTo(x, y) : context.moveTo(x, y))
        context.strokeStyle = style.routeColor
        context.lineWidth = style.routeWidth
        context.lineCap = 'round'
        context.lineJoin = 'round'
        context.shadowColor = style.shadowColor
        context.shadowBlur = style.glow
        context.stroke()
        context.shadowBlur = 0
        context.strokeStyle = style.coreColor
        context.lineWidth = style.coreWidth
        context.stroke()
        context.restore()
        if (options.showEndpoints === false) return
        const endpoints = [routePoints[0], routePoints.at(-1)]
        endpoints.forEach(([x, y], index) => {
            context.beginPath()
            context.arc(x, y, (story ? 7.4 : 5.8) * style.widthScale, 0, Math.PI * 2)
            context.fillStyle = index ? style.routeColor : '#ffffff'
            context.fill()
            if (lightSurface || options.endpointOutline) {
                context.lineWidth = Math.max(1.5, 1.8 * style.widthScale)
                context.strokeStyle = style.routeColor
                context.stroke()
            }
        })
    }
    const drawShareGeometry = (context, geometryOptions, story, lightSurface = false, drawOptions = {}) => {
        const visual = createShareGeometryVisual(geometryOptions)
        if (!visual) return null
        drawRoute(context, visual.points, story, lightSurface, {...visual.style, ...drawOptions})
        return visual
    }

    const shareRoundedRect = (context, x, y, width, height, radius) => {
        const r = Math.min(radius, width / 2, height / 2)
        context.beginPath()
        context.moveTo(x + r, y)
        context.arcTo(x + width, y, x + width, y + height, r)
        context.arcTo(x + width, y + height, x, y + height, r)
        context.arcTo(x, y + height, x, y, r)
        context.arcTo(x, y, x + width, y, r)
        context.closePath()
    }
    const drawShareText = (context, text, x, y, outlined = false, outlineWidth = 5) => {
        if (!outlined) {
            context.fillText(text, x, y)
            return
        }
        context.save()
        context.lineJoin = 'round'
        context.strokeStyle = 'rgba(2, 8, 18, .34)'
        context.lineWidth = Math.max(.7, Math.min(1.5, outlineWidth * .18))
        context.shadowColor = 'rgba(2, 8, 18, .28)'
        context.shadowBlur = Math.max(1.2, Math.min(3.2, outlineWidth * .42))
        context.shadowOffsetY = Math.max(.4, Math.min(1.2, outlineWidth * .12))
        context.strokeText(text, x, y)
        context.fillText(text, x, y)
        context.restore()
    }
    const shareBackgroundStops = color => SHARE_BACKGROUND_COLORS[normalizeShareBackgroundColor(color)].stops
    const getShareNoisePattern = (context, {light = false} = {}) => {
        const key = light ? 'light' : 'dark'
        let canvas = shareNoiseTileCache.get(key)
        if (!canvas) {
            canvas = document.createElement('canvas')
            canvas.width = 96
            canvas.height = 96
            const patternContext = canvas.getContext('2d', {alpha: true})
            const image = patternContext.createImageData(canvas.width, canvas.height)
            const alphaMin = light ? 6 : 7
            const alphaSpread = light ? 8 : 11
            for (let index = 0; index < image.data.length; index += 4) {
                const value = light ? 255 : 238 + Math.round(Math.random() * 16)
                const alpha = alphaMin + Math.floor(Math.random() * alphaSpread)
                image.data[index] = value
                image.data[index + 1] = value
                image.data[index + 2] = value
                image.data[index + 3] = alpha
            }
            patternContext.putImageData(image, 0, 0)
            shareNoiseTileCache.set(key, canvas)
        }
        return context.createPattern(canvas, 'repeat')
    }
    const fillShareBackground = (context, width, height, color) => {
        const normalized = normalizeShareBackgroundColor(color)
        const stops = shareBackgroundStops(normalized)
        context.fillStyle = stops[1]
        context.fillRect(0, 0, width, height)
        const wash = context.createLinearGradient(0, 0, 0, height)
        wash.addColorStop(0, stops[0])
        wash.addColorStop(.48, 'rgba(0,0,0,0)')
        wash.addColorStop(1, stops[2])
        context.fillStyle = wash
        context.fillRect(0, 0, width, height)
        const sideGlow = context.createRadialGradient(width * .5, height * .58, width * .08, width * .5, height * .58, width * .82)
        sideGlow.addColorStop(0, normalized === 'dark' ? 'rgba(28, 42, 74, .07)' : 'rgba(48, 76, 126, .12)')
        sideGlow.addColorStop(.58, 'rgba(18, 31, 54, .035)')
        sideGlow.addColorStop(1, 'rgba(4, 8, 14, 0)')
        context.fillStyle = sideGlow
        context.fillRect(0, 0, width, height)
        const noise = getShareNoisePattern(context, {light: false})
        if (noise) {
            context.save()
            context.globalAlpha = .13
            context.fillStyle = noise
            context.fillRect(0, 0, width, height)
            context.restore()
        }
    }
    const drawSharePreviewMapPattern = (context, width, height, light = false) => {
        context.save()
        context.lineCap = 'round'
        context.lineJoin = 'round'
        const major = light ? 'rgba(112,137,164,.56)' : 'rgba(79,104,151,.30)'
        const minor = light ? 'rgba(167,181,195,.50)' : 'rgba(65,86,124,.22)'
        const roads = [
            [[.05,.30],[.26,.25],[.45,.32],[.67,.24],[.96,.29]],
            [[.04,.63],[.24,.58],[.46,.61],[.66,.54],[.96,.60]],
            [[.18,.05],[.21,.28],[.17,.51],[.24,.94]],
            [[.48,.04],[.45,.26],[.52,.49],[.47,.76],[.52,.96]],
            [[.80,.06],[.74,.27],[.81,.49],[.76,.74],[.82,.95]],
            [[.06,.45],[.32,.43],[.58,.47],[.95,.42]],
            [[.09,.78],[.30,.72],[.58,.77],[.91,.70]],
        ]
        roads.forEach((road, index) => {
            context.beginPath()
            road.forEach(([rx, ry], pointIndex) => pointIndex ? context.lineTo(rx * width, ry * height) : context.moveTo(rx * width, ry * height))
            context.strokeStyle = index < 2 ? major : minor
            context.lineWidth = index < 2 ? Math.max(1.5, width * .012) : Math.max(1, width * .007)
            context.stroke()
        })
        if (light) {
            context.fillStyle = 'rgba(198,221,190,.32)'
            context.beginPath(); context.ellipse(width * .82, height * .18, width * .13, height * .09, -.2, 0, Math.PI * 2); context.fill()
        }
        context.restore()
    }
    const drawShareSportLabel = (context, label, centerX, baselineY, maxWidth, {fontSize = 24, color = '#9fb1db', outlined = false, lineColor = null, lineWidth = 1.5, gap = 18} = {}) => {
        const text = String(label || '').trim().toUpperCase()
        if (!text) return 0
        context.save()
        context.textAlign = 'center'
        let resolvedFontSize = fontSize
        const textLimit = maxWidth * .72
        context.font = `760 ${resolvedFontSize}px Inter, system-ui, sans-serif`
        while (resolvedFontSize > 14 && context.measureText(text).width > textLimit) {
            resolvedFontSize -= 1
            context.font = `760 ${resolvedFontSize}px Inter, system-ui, sans-serif`
        }
        const measured = Math.min(textLimit, context.measureText(text).width)
        const sideRoom = Math.max(0, (Math.min(maxWidth, measured + 180) - measured) / 2 - gap)
        if (sideRoom > 14) {
            context.beginPath()
            context.moveTo(centerX - measured / 2 - gap - sideRoom, baselineY - resolvedFontSize * .28)
            context.lineTo(centerX - measured / 2 - gap, baselineY - resolvedFontSize * .28)
            context.moveTo(centerX + measured / 2 + gap, baselineY - resolvedFontSize * .28)
            context.lineTo(centerX + measured / 2 + gap + sideRoom, baselineY - resolvedFontSize * .28)
            context.strokeStyle = lineColor || color
            context.globalAlpha = outlined ? .88 : .46
            context.lineWidth = lineWidth
            context.stroke()
            context.globalAlpha = 1
        }
        context.fillStyle = color
        drawShareText(context, text, centerX, baselineY, outlined, Math.max(2.5, resolvedFontSize * .13))
        context.restore()
        return resolvedFontSize
    }

    const shareMetricRowCount = count => {
        const total = Math.max(0, Math.min(4, Number(count) || 0))
        if (!total) return 0
        return total <= 2 ? 1 : 2
    }
    const shareMetricLayout = (count, left, width, top, rowHeight) => {
        const total = Math.max(0, Math.min(4, Number(count) || 0))
        if (!total) return []
        const center = left + width / 2
        const quarter = left + width * .25
        const threeQuarter = left + width * .75
        if (total === 1) return [{x: center, y: top, row: 0}]
        if (total === 2) return [{x: quarter, y: top, row: 0}, {x: threeQuarter, y: top, row: 0}]
        if (total === 3) return [
            {x: quarter, y: top, row: 0},
            {x: threeQuarter, y: top, row: 0},
            {x: center, y: top + rowHeight, row: 1},
        ]
        return [
            {x: quarter, y: top, row: 0},
            {x: threeQuarter, y: top, row: 0},
            {x: quarter, y: top + rowHeight, row: 1},
            {x: threeQuarter, y: top + rowHeight, row: 1},
        ]
    }

    const compactMetricLayout = (formatId, count, left, width, top, rowHeight, definition = SHARE_COMPOSITION_REGISTRY.compact) => {
        const total = Math.max(0, Math.min(4, Number(count) || 0))
        if (!total) return []
        const mode = String(definition?.layoutByFormat?.[formatId] || (formatId === 'square' ? 'grid' : 'vertical'))
        if (mode === 'grid') return shareMetricLayout(total, left, width, top, rowHeight)
        const center = left + width / 2
        return Array.from({length: total}, (_, index) => ({x: center, y: top + index * rowHeight, row: index}))
    }

    const drawRouteLessShareCard = async (context, configuration, {primaryText, secondaryText, accentText, outlinedText, isLightSurface}) => {
        const {width, height, format, content, showTitle, showDate, showLogo, caption, selectedMetrics, data, thumbnail, headingText} = configuration
        const isCompact = configuration.composition === 'compact'
        const isStory = format === 'story'
        const isPortrait = format === 'portrait'
        const focus = shareFocusLabel(data)
        const title = String(data?.titulo || data?.modalidade || tr('nav.physical_activity'))
        const metrics = (selectedMetrics || []).slice(0, 4)
        const logo = isLightSurface ? shareLogoDark : shareLogo
        const logoReady = isLightSurface ? shareLogoDarkReady : shareLogoReady
        const hasSportVisual = content === 'sport' && Boolean(String(data?.modalidade || '').trim())
        const headerTitle = String(headingText || '')

        if (isCompact) {
            const side = 58
            const top = 50
            const logoWidth = 126
            const logoHeight = logoWidth * (552.6 / 1408.82)
            let y = top
            context.textAlign = 'left'
            if (showLogo && logoReady) context.drawImage(logo, width - side - logoWidth, top - 12, logoWidth, logoHeight)
            if (focus) {
                context.fillStyle = secondaryText
                context.font = '620 24px Inter, system-ui, sans-serif'
                const lines = wrapCanvasText(context, focus, width - side * 2, 2)
                lines.forEach((line, index) => drawShareText(context, line, side, y + index * 30, outlinedText, 3))
                y += lines.length * 30 + 24
            } else y += 18
            const metricRowHeight = 94
            const routeLessCompactDefinition = configuration.compositionDefinition || SHARE_COMPOSITION_REGISTRY.compact
            const routeLessCompactMode = String(routeLessCompactDefinition?.layoutByFormat?.[format] || (format === 'square' ? 'grid' : 'vertical'))
            const metricRows = routeLessCompactMode === 'vertical' ? metrics.length : shareMetricRowCount(metrics.length)
            const metricsTop = Math.max(y, height - (metricRows * metricRowHeight + 96))
            const metricLayout = compactMetricLayout(format, metrics.length, side, width - side * 2, metricsTop, metricRowHeight, routeLessCompactDefinition)
            metrics.forEach((metric, index) => {
                const position = metricLayout[index]
                if (!position) return
                context.textAlign = 'center'
                context.fillStyle = secondaryText
                context.font = '650 17px Inter, system-ui, sans-serif'
                drawShareText(context, String(metric.rotulo || '').toUpperCase(), position.x, position.y, outlinedText, 2.5)
                context.fillStyle = primaryText
                context.font = '780 34px Inter, system-ui, sans-serif'
                const value = String(metric.valor || '')
                const max = (width - side * 2) * (metrics.length === 1 ? .78 : .42)
                let size = 34
                while (size > 24 && context.measureText(value).width > max) { size -= 2; context.font = `780 ${size}px Inter, system-ui, sans-serif` }
                drawShareText(context, value, position.x, position.y + 42, outlinedText, 4)
            })
            if (showDate && data?.data) {
                context.textAlign = 'right'
                context.fillStyle = secondaryText
                context.font = '550 17px Inter, system-ui, sans-serif'
                drawShareText(context, String(data.data), width - side, height - 34, outlinedText, 2.5)
            }
            return true
        }

        const side = isStory ? 92 : 72
        const contentWidth = width - side * 2
        const scale = isStory ? 1.16 : isPortrait ? 1.12 : 1.08
        const titleSize = Math.round(62 * scale)
        const labelSize = Math.round(26 * scale)
        const metricValueSize = Math.round(46 * scale)
        const metricLabelSize = Math.round(20 * scale)
        const logoWidth = Math.round((isStory ? 160 : 124) * Math.min(1, width / 1080))
        const logoHeight = logoWidth * (552.6 / 1408.82)
        const hasTitleLabel = Boolean(showTitle && headerTitle)
        const iconSize = hasSportVisual ? Math.round(Math.min(contentWidth * .30, height * (isStory ? .16 : .18))) : 0
        const focusHeight = focus ? 74 * scale : 18 * scale
        const iconHeight = hasSportVisual ? iconSize + 34 * scale : 0
        const metricsHeight = metrics.length ? shareMetricRowCount(metrics.length) * 112 * scale : 0
        const captionHeight = caption && !thumbnail ? 48 * scale : 0
        const groupHeight = focusHeight + iconHeight + metricsHeight + captionHeight
        const anchors = SHARE_FORMATS[format]?.anchors || SHARE_FORMATS.story.anchors
        const contentTop = anchors.contentStage.y * height
        const contentBottom = (anchors.contentStage.y + anchors.contentStage.height) * height
        const logoY = showLogo && logoReady ? anchors.logoAnchor.y * height - logoHeight / 2 : null
        let y = Math.max(contentTop, contentTop + Math.max(0, contentBottom - contentTop - groupHeight) / 2)
        context.textAlign = 'center'
        if (hasTitleLabel) {
            drawShareSportLabel(context, headerTitle, anchors.titleAnchor.x * width, anchors.titleAnchor.y * height, contentWidth, {
                fontSize: labelSize,
                color: accentText,
                outlined: outlinedText,
                lineColor: isLightSurface ? 'rgba(82,107,156,.52)' : 'rgba(159,177,219,.62)',
                lineWidth: Math.max(1.2, 1.5 * scale),
                gap: 16 * scale,
            })
        }
        if (focus) {
            context.fillStyle = secondaryText
            context.font = `620 ${Math.round(25 * scale)}px Inter, system-ui, sans-serif`
            const lines = wrapCanvasText(context, focus, contentWidth * .88, 2)
            lines.forEach((line, index) => drawShareText(context, line, width / 2, y + index * 31 * scale, outlinedText, 3))
            y += lines.length * 31 * scale + 36 * scale
        } else y += 18 * scale

        if (hasSportVisual) {
            await drawShareSportIcon(
                context,
                resolveShareSportIconId(data),
                width / 2,
                y + iconSize / 2,
                iconSize,
                '#5f82ff',
                outlinedText,
                true
            )
            y += iconHeight
        }

        if (metrics.length) {
            const metricLayout = shareMetricLayout(metrics.length, side, contentWidth, y, 112 * scale)
            metrics.forEach((metric, index) => {
                const position = metricLayout[index]
                if (!position) return
                context.textAlign = 'center'
                context.fillStyle = secondaryText
                context.font = `650 ${metricLabelSize}px Inter, system-ui, sans-serif`
                drawShareText(context, String(metric.rotulo || '').toUpperCase(), position.x, position.y, outlinedText, 2.5)
                context.fillStyle = primaryText
                context.font = `780 ${metricValueSize}px Inter, system-ui, sans-serif`
                drawShareText(context, String(metric.valor || ''), position.x, position.y + 48 * scale, outlinedText, 4.5)
            })
            y += metricsHeight + 16 * scale
        }
        if (caption && !thumbnail) {
            context.fillStyle = secondaryText
            context.font = `560 ${Math.round(22 * scale)}px Inter, system-ui, sans-serif`
            drawShareText(context, caption, width / 2, y, outlinedText, 3)
        }
        if (showLogo && logoY !== null) context.drawImage(logo, width / 2 - logoWidth / 2, logoY, logoWidth, logoHeight)
        if (showDate && data?.data) {
            context.fillStyle = secondaryText
            context.font = `550 ${Math.round(19 * scale)}px Inter, system-ui, sans-serif`
            const dateY = logoY !== null ? logoY - 24 * scale : height - 42 * scale
            drawShareText(context, String(data.data), width / 2, dateY, outlinedText, 2.5)
        }
        return true
    }

    const drawShareMapAttribution = (context, attribution, width, height, side, outlinedText = false, bottomOffset = null) => {
        const text = String(attribution || '').trim()
        if (!text) return
        context.save()
        context.textAlign = 'right'
        const fontSize = width >= 1000 ? 16 : 13
        const lineHeight = width >= 1000 ? 20 : 17
        context.font = `520 ${fontSize}px Inter, system-ui, sans-serif`
        const maxWidth = Math.min(width * .78, width - side * 1.5)
        const lines = wrapCanvasText(context, text, maxWidth, 2)
        const bottom = height - Math.max(24, Number(bottomOffset) || side * .38)
        const measured = Math.max(...lines.map(line => context.measureText(line).width), 0)
        const boxWidth = Math.min(maxWidth + 16, measured + 18)
        const boxHeight = lines.length * lineHeight + 10
        const boxX = width - side - boxWidth + 8
        const boxY = bottom - (lines.length - 1) * lineHeight - fontSize - 5
        context.fillStyle = 'rgba(2, 8, 18, .42)'
        shareRoundedRect(context, boxX, boxY, boxWidth, boxHeight, 5)
        context.fill()
        context.fillStyle = 'rgba(248,250,253,.92)'
        lines.forEach((line, index) => drawShareText(context, line, width - side, bottom - (lines.length - 1 - index) * lineHeight, outlinedText, 1.2))
        context.restore()
    }

    const drawCompactShareCardSurface = async (context, configuration, token) => {
        const palette = SHARE_CARD_THEME
        const {width, height, format, mode, color, content, showMapBase, showTitle, showRoute, showDate, showLogo, caption, selectedMetrics, data, routeCoordinates, routeScale, headingText, mapStyle} = configuration
        const compactDefinition = configuration.compositionDefinition || SHARE_COMPOSITION_REGISTRY.compact
        const compactLayoutMode = String(compactDefinition?.layoutByFormat?.[format] || (format === 'square' ? 'grid' : 'vertical'))
        const isWide = compactLayoutMode === 'grid'
        const isTransparent = mode === 'transparent'
        const isPhoto = mode === 'photo'
        const isMapSurface = mode === 'map'
        const isLightSurface = color === 'light' && !isPhoto && !isTransparent && !isMapSurface
        const primaryText = isLightSurface ? '#17243a' : palette.text
        const secondaryText = isLightSurface ? '#66758d' : palette.muted
        const accent = '#5f82ff'
        const outlinedText = isTransparent || isMapSurface
        const side = isWide ? 82 : 96
        const contentWidth = width - side * 2
        const layoutHeight = format === 'story' ? Math.min(height, 1350) : height
        const layoutTop = Math.max(0, (height - layoutHeight) / 2)
        const layoutBottom = layoutTop + layoutHeight
        const iconId = resolveShareSportIconId(data)
        const title = String(headingText || '')
        const metrics = canonicalizeShareMetrics(selectedMetrics).slice(0, 4)
        const hasVisibleRoute = Boolean(showRoute && Array.isArray(routeCoordinates) && routeCoordinates.length >= 2)
        const logo = isLightSurface ? shareLogoDark : shareLogo
        const logoReady = isLightSurface ? shareLogoDarkReady : shareLogoReady

        context.clearRect(0, 0, width, height)
        if (!isTransparent && !isPhoto && !isMapSurface) fillShareBackground(context, width, height, color)
        if (isPhoto) {
            const image = sharePhoto || sharePreviewPhoto
            if (image) coverImage(context, image, width, height)
            else drawPhotoPlaceholder(context, width, height)
            const shade = context.createLinearGradient(0, 0, 0, height)
            shade.addColorStop(0, 'rgba(4,10,18,.14)')
            shade.addColorStop(.48, 'rgba(4,10,18,.05)')
            shade.addColorStop(1, 'rgba(4,10,18,.58)')
            context.fillStyle = shade
            context.fillRect(0, 0, width, height)
        }

        const hasSportVisual = content === 'sport' && Boolean(String(data?.modalidade || '').trim())
        const hasMiddleVisual = hasVisibleRoute || hasSportVisual
        const metricRows = compactLayoutMode === 'vertical' ? metrics.length : shareMetricRowCount(metrics.length)
        const metricRowHeight = isWide ? 194 : 174
        const metricSpan = metricRows ? metricRows * metricRowHeight - 8 : 0
        const logoWidth = isWide ? (isTransparent ? 170 : 150) : (isTransparent ? 208 : 190)
        const logoHeight = logoWidth * (552.6 / 1408.82)
        const sportIconSize = Math.min(isWide ? 188 : 174, contentWidth * (isWide ? .25 : .22))
        const logoSpan = showLogo && logoReady ? logoHeight : 0
        const sportGapBefore = isWide ? 42 : 38
        const sportGapAfter = isWide ? 38 : 42
        const emptyGap = isWide ? 48 : 56
        let metricTop = layoutTop + (isWide ? 112 : 70)
        if (hasSportVisual && !hasVisibleRoute) {
            const groupHeight = metricSpan + sportGapBefore + sportIconSize + sportGapAfter + logoSpan
            metricTop = Math.max(layoutTop + (isWide ? 118 : 72), layoutTop + (layoutHeight - groupHeight) / 2)
        } else if (!hasMiddleVisual) {
            const groupHeight = metricSpan + emptyGap + logoSpan
            metricTop = Math.max(layoutTop + (isWide ? 120 : 86), layoutTop + (layoutHeight - groupHeight) / 2)
        }
        if (showTitle) {
            context.fillStyle = primaryText
            context.textAlign = 'center'
            context.font = `800 ${isWide ? 42 : 40}px Inter, system-ui, sans-serif`
            const titleLines = wrapCanvasText(context, title, Math.min(contentWidth, isWide ? 940 : 820), 2)
            titleLines.forEach((line, index) => drawShareText(context, line, width / 2, metricTop + index * 48, outlinedText, 5))
            metricTop += titleLines.length * 48 + (isWide ? 24 : 18)
        }

        const metricLayout = compactMetricLayout(format, metrics.length, side, contentWidth, metricTop, metricRowHeight, compactDefinition)
        context.textAlign = 'center'
        metrics.forEach((metric, index) => {
            const position = metricLayout[index]
            if (!position) return
            const x = position.x
            const y = position.y
            context.fillStyle = secondaryText
            context.font = `750 ${(isWide ? 28 : 26) + (isTransparent ? 2 : 0)}px Inter, system-ui, sans-serif`
            drawShareText(context, String(metric?.rotulo || '').toUpperCase(), x, y + 28, outlinedText, 3.5)
            let valueSize = (isWide ? 74 : 70) + (isTransparent ? 4 : 0)
            const value = String(metric?.valor || '')
            context.font = `820 ${valueSize}px Inter, system-ui, sans-serif`
            const maxValueWidth = contentWidth * (compactLayoutMode === 'vertical' ? .78 : (metrics.length === 1 ? .78 : .44))
            while (valueSize > 48 && context.measureText(value).width > maxValueWidth) {
                valueSize -= 2
                context.font = `820 ${valueSize}px Inter, system-ui, sans-serif`
            }
            context.fillStyle = primaryText
            drawShareText(context, value, x, y + 112, outlinedText, 7)
        })
        const metricBottom = metricRows ? metricTop + metricRows * metricRowHeight - 8 : metricTop

        const dateY = showDate ? layoutBottom - 24 : null
        const logoBottom = showDate ? layoutBottom - 58 : layoutBottom - 44
        const normalLogoY = showLogo && logoReady ? logoBottom - logoHeight : null
        let logoY = null
        if (showLogo && logoReady) {
            if (hasVisibleRoute) logoY = normalLogoY
            else if (hasSportVisual) logoY = metricBottom + sportGapBefore + sportIconSize + sportGapAfter
            else logoY = metricBottom + emptyGap
            if (normalLogoY !== null) logoY = Math.min(normalLogoY, logoY)
        }
        const captionY = caption ? (logoY !== null ? logoY - 30 : (dateY !== null ? dateY - 52 : layoutBottom - 52)) : null
        const compactFooterTarget = logoY !== null ? logoY - (isWide ? 34 : 46) : dateY !== null ? dateY - 44 : layoutBottom - 44
        const visualTop = metricBottom + (isWide ? 20 : 28)
        const visualBottom = hasMiddleVisual ? Math.max(visualTop + 220, captionY !== null ? captionY - 28 : compactFooterTarget) : visualTop
        const routeFrame = {
            x: isWide ? 42 : 118,
            y: visualTop,
            width: width - (isWide ? 84 : 236),
            height: Math.max(220, visualBottom - visualTop),
        }

        let mapDrawn = false
        let roadsDrawn = false
        let mapAttribution = ''
        if (hasVisibleRoute) {
            const fill = shareRouteFillForScale(routeScale)
            const viewport = isMapSurface && !configuration.thumbnail
                ? createShareMapViewport(routeCoordinates, width, height, routeFrame, routeScale, mapStyle || 'street', configuration.mapRasterScale)
                : createMapViewport(routeCoordinates, width, height, routeFrame, fill)
            const routeProject = viewport?.project ? ((lon, lat) => viewport.project(lon, lat)) : (() => {
                const fallback = canvasGeoProjector(routeCoordinates, routeFrame.x, routeFrame.y, routeFrame.width, routeFrame.height)
                return fallback ? ((lon, lat) => fallback([lon, lat])) : null
            })()
            const routePoints = routeProject ? routeCoordinates.map(point => routeProject(point[0], point[1])) : []
            const wantsRoadGeometry = Boolean(viewport && showMapBase && !isMapSurface)
            const geometry = wantsRoadGeometry ? await fetchRoadNetwork(routeCoordinates) : {roads: [], areas: []}
            if (wantsRoadGeometry && token !== shareRenderToken) return false
            const roads = Array.isArray(geometry?.roads) ? geometry.roads : []
            const areas = Array.isArray(geometry?.areas) ? geometry.areas : []
            if (showMapBase && !isMapSurface && viewport && roads.length) {
                context.save()
                context.beginPath()
                context.rect(0, Math.max(0, visualTop - 24), width, Math.max(1, visualBottom - visualTop + 48))
                context.clip()
                context.globalAlpha = isTransparent ? .44 : isPhoto ? .52 : .62
                drawMapAreas(context, areas, viewport.project.bind(viewport), {variant: isLightSurface ? 'light' : 'dark'})
                mapDrawn = drawBaseRoadNetwork(context, roads, routePoints, viewport.project.bind(viewport), {variant: isLightSurface ? 'light' : 'dark', thumbnail: false})
                context.restore()
            }
            if (routePoints.length) {
                context.save()
                drawRoute(context, routePoints, true, isLightSurface)
                context.restore()
            }
            if (isMapSurface && viewport && !configuration.thumbnail) {
                const mapResult = await compositeShareMapBackground(context, configuration, viewport, token)
                if (token !== shareRenderToken) return false
                mapDrawn = mapResult.drawn
                mapAttribution = mapResult.drawn ? mapResult.attribution : ''
            }
        } else if (hasSportVisual) {
            await drawShareSportIcon(context, iconId, width / 2, metricBottom + sportGapBefore + sportIconSize / 2, sportIconSize, accent, outlinedText, true)
        }

        if (captionY !== null) {
            context.fillStyle = secondaryText
            context.font = `600 ${isWide ? 18 : 20}px Inter, system-ui, sans-serif`
            drawShareText(context, caption, width / 2, captionY, outlinedText, 3)
        }
        if (logoY !== null) {
            context.save()
            if (isTransparent || isMapSurface) {
                context.shadowColor = 'rgba(2,8,18,.26)'
                context.shadowBlur = 3
            }
            context.drawImage(logo, width / 2 - logoWidth / 2, logoY, logoWidth, logoHeight)
            context.restore()
        }
        if (dateY !== null && data?.data) {
            context.fillStyle = secondaryText
            context.font = `560 ${isWide ? 16 : 18}px Inter, system-ui, sans-serif`
            drawShareText(context, String(data.data), width / 2, dateY, outlinedText, 2.5)
        }
        if (isMapSurface && mapAttribution) drawShareMapAttribution(context, mapAttribution, width, height, Math.max(22, side * .45), true, showDate ? 58 : 16)
        return {mapDrawn, roadsDrawn}
    }

    const drawShareCardSurface = async (context, configuration, token) => {
        const palette = SHARE_CARD_THEME
        const {width, height, story, format, mode, color, content, background, showMapBase, showRoads, showTitle, showRoute, showDate, showLogo, caption, selectedMetrics, data, routeCoordinates, thumbnail, routeScale, headingText, mapStyle} = configuration
        const compact = configuration.composition === 'compact'
        if (compact && !thumbnail) return drawCompactShareCardSurface(context, configuration, token)
        context.clearRect(0, 0, width, height)
        const isTransparent = mode === 'transparent'
        const isMapSurface = mode === 'map'
        const isLightSurface = color === 'light' && mode !== 'photo' && mode !== 'transparent' && !isMapSurface

        if (!isTransparent && mode !== 'photo' && !isMapSurface) fillShareBackground(context, width, height, color)
        if (mode === 'photo') {
            const image = sharePhoto || sharePreviewPhoto
            if (image) coverImage(context, image, width, height)
            else drawPhotoPlaceholder(context, width, height)
        }
        if (thumbnail && mode === 'map') drawSharePreviewMapPattern(context, width, height, color === 'light')

        const outlinedText = isTransparent || isMapSurface
        const primaryText = isLightSurface ? '#223148' : palette.text
        const secondaryText = isLightSurface ? '#64748b' : palette.muted
        const accentText = isLightSurface ? '#526b9c' : palette.accent
        const hasVisibleRoute = Boolean(showRoute && Array.isArray(routeCoordinates) && routeCoordinates.length >= 2)
        if (!hasVisibleRoute && !thumbnail) {
            await drawRouteLessShareCard(context, configuration, {primaryText, secondaryText, accentText, outlinedText, isLightSurface})
            return {mapDrawn: false, roadsDrawn: false}
        }
        const side = story ? 86 : 64
        const contentWidth = width - side * 2
        const anchors = SHARE_FORMATS[format]?.anchors || SHARE_FORMATS.story.anchors
        const stageTop = anchors.contentStage.y * height
        const stageBottom = (anchors.contentStage.y + anchors.contentStage.height) * height
        let cursorY = stageTop

        if (showTitle) {
            const activityTitle = String(headingText || '')
            const titleSize = story ? 30 : format === 'portrait' ? 27 : format === 'square' ? 27 : 22
            drawShareSportLabel(context, activityTitle, anchors.titleAnchor.x * width, anchors.titleAnchor.y * height, contentWidth, {
                fontSize: titleSize,
                color: accentText,
                outlined: outlinedText,
                lineColor: isLightSurface ? 'rgba(82,107,156,.52)' : 'rgba(159,177,219,.62)',
                lineWidth: story ? 1.8 : 1.5,
                gap: story ? 20 : 17,
            })
        }

        const square = format === 'square'
        const portrait = format === 'portrait'
        const metricRows = shareMetricRowCount(selectedMetrics.length)
        const metricRowHeight = story ? 146 : portrait ? 124 : square ? 118 : 92
        const metricsBlockHeight = metricRows ? metricRows * metricRowHeight : 0
        const metricsGap = metricRows ? (story ? 38 : portrait ? 28 : square ? 24 : 18) : 0
        const logoWidth = story ? 160 : 124
        const logoHeight = logoWidth * (552.6 / 1408.82)
        const dateGap = showDate ? (story ? 26 : 18) : 0
        const captionGap = caption && !thumbnail ? (story ? 34 : 22) : 0
        let footerCursor = stageBottom
        const footnoteY = height - (story ? 42 : 24)

        let logoY = null
        if (showLogo) logoY = anchors.logoAnchor.y * height - logoHeight / 2

        let dateY = null
        if (showDate) {
            dateY = footerCursor
            footerCursor -= dateGap
        }

        let statTop = null
        if (metricsBlockHeight) {
            if (hasVisibleRoute) {
                const metricsLift = story ? 96 : portrait ? 52 : square ? 34 : 0
                statTop = footerCursor - metricsBlockHeight - metricsLift
                footerCursor = statTop - metricsGap
            } else {
                const availableBottom = footerCursor - metricsBlockHeight
                const centered = (height - metricsBlockHeight) / 2
                statTop = Math.max(cursorY + (story ? 90 : 45), Math.min(availableBottom, centered))
                footerCursor = statTop - metricsGap
            }
        }

        let captionY = null
        if (caption && !thumbnail) {
            captionY = footerCursor
            footerCursor -= captionGap
        }

        const routeTop = cursorY + (story ? 28 : 14)
        const routeBottom = Math.max(routeTop + (story ? 420 : 220), footerCursor)
        const routeHeight = Math.max(120, routeBottom - routeTop)
        const routeFrameInsetX = thumbnail ? width * .10 : story ? 18 : 14
        const routeFrameInsetY = thumbnail ? 8 : story ? 0 : 2
        const routeFrame = {
            x: side + routeFrameInsetX,
            y: routeTop + routeFrameInsetY,
            width: Math.max(160, contentWidth - routeFrameInsetX * 2),
            height: Math.max(120, routeHeight - routeFrameInsetY * 2),
        }
        const routeFill = shareRouteFillForScale(routeScale)
        const viewport = hasVisibleRoute
            ? (isMapSurface && !thumbnail
                ? createShareMapViewport(routeCoordinates, width, height, routeFrame, routeScale, mapStyle || 'street', configuration.mapRasterScale)
                : createMapViewport(routeCoordinates, width, height, routeFrame, routeFill))
            : null
        const routeProject = viewport?.project ? ((lon, lat) => viewport.project(lon, lat)) : (() => {
            const fallback = canvasGeoProjector(routeCoordinates, routeFrame.x, routeFrame.y, routeFrame.width, routeFrame.height)
            return fallback ? ((lon, lat) => fallback([lon, lat])) : null
        })()
        const routePoints = routeProject && hasVisibleRoute ? routeCoordinates.map(point => routeProject(point[0], point[1])) : []
        const wantsRoadGeometry = Boolean(viewport && hasVisibleRoute && !thumbnail && !isMapSurface && (showMapBase || showRoads))
        const geometry = wantsRoadGeometry ? await fetchRoadNetwork(routeCoordinates) : {roads: [], areas: []}
        if (wantsRoadGeometry && token !== shareRenderToken) return false
        const roads = Array.isArray(geometry?.roads) ? geometry.roads : []
        const areas = Array.isArray(geometry?.areas) ? geometry.areas : []
        const hasRoadGeometry = roads.length > 0

        let mapDrawn = false
        if (showMapBase && !isMapSurface && viewport && hasRoadGeometry) {
            const variant = isLightSurface ? 'light' : 'dark'
            context.save()
            context.beginPath()
            context.rect(routeFrame.x - 14, routeFrame.y - 14, routeFrame.width + 28, routeFrame.height + 28)
            context.clip()
            if (mode === 'photo') context.globalAlpha = .68
            if (mode === 'transparent') context.globalAlpha = .72
            drawMapAreas(context, areas, viewport.project.bind(viewport), {variant})
            mapDrawn = drawBaseRoadNetwork(context, roads, routePoints, viewport.project.bind(viewport), {variant, thumbnail})
            context.restore()
        }
        if (!isTransparent && !isMapSurface && mode !== 'photo' && color !== 'light') {
            const shade = context.createLinearGradient(0, 0, 0, height)
            shade.addColorStop(0, 'rgba(3, 8, 16, .10)')
            shade.addColorStop(.52, 'rgba(4, 10, 20, .03)')
            shade.addColorStop(1, 'rgba(3, 7, 16, .34)')
            context.fillStyle = shade
            context.fillRect(0, 0, width, height)
        }
        if (mode === 'photo') {
            const shade = context.createLinearGradient(0, 0, 0, height)
            shade.addColorStop(0, 'rgba(4, 10, 18, .18)')
            shade.addColorStop(.55, 'rgba(4, 10, 18, .06)')
            shade.addColorStop(1, 'rgba(4, 10, 18, .58)')
            context.fillStyle = shade
            context.fillRect(0, 0, width, height)
        }
        let roadsDrawn = false
        if (showRoads && viewport && hasRoadGeometry) {
            context.save()
            context.beginPath()
            context.rect(routeFrame.x - 14, routeFrame.y - 14, routeFrame.width + 28, routeFrame.height + 28)
            context.clip()
            roadsDrawn = drawRoadNetwork(context, roads, routePoints, viewport.project.bind(viewport), {fadeEnds: true, thumbnail})
            context.restore()
        }

        if (routePoints.length) drawRoute(context, routePoints, story, isLightSurface)

        let mapAttribution = ''
        if (isMapSurface && viewport && !thumbnail) {
            const mapResult = await compositeShareMapBackground(context, configuration, viewport, token)
            if (token !== shareRenderToken) return false
            mapDrawn = mapResult.drawn
            mapAttribution = mapResult.drawn ? mapResult.attribution : ''
        }

        if (captionY !== null && caption && !thumbnail) {
            context.textAlign = 'center'
            context.fillStyle = primaryText
            context.font = `600 ${story ? 26 : 20}px Inter, system-ui, sans-serif`
            drawShareText(context, caption, width / 2, captionY, outlinedText, story ? 4 : 3)
        }

        if (selectedMetrics.length && statTop !== null) {
            const metricLayout = shareMetricLayout(selectedMetrics.length, side, contentWidth, statTop, metricRowHeight)
            selectedMetrics.forEach((metric, index) => {
                const position = metricLayout[index]
                if (!position) return
                const y = position.y + (story ? 18 : portrait ? 14 : square ? 12 : 8)
                const metricLabelSize = story ? 25 : portrait ? 23 : square ? 22 : 17
                const metricValueSize = story ? 58 : portrait ? 54 : square ? 50 : 34
                const metricValueOffset = story ? 60 : portrait ? 56 : square ? 53 : 39
                context.textAlign = 'center'
                context.fillStyle = secondaryText
                context.font = `650 ${metricLabelSize}px Inter, system-ui, sans-serif`
                drawShareText(context, String(metric.rotulo || '').toUpperCase(), position.x, y, outlinedText, story ? 3.6 : portrait || square ? 3 : 2.5)
                context.fillStyle = primaryText
                context.font = `780 ${metricValueSize}px Inter, system-ui, sans-serif`
                drawShareText(context, String(metric.valor || ''), position.x, y + metricValueOffset, outlinedText, story ? 6.4 : portrait || square ? 5.4 : 4)
            })
        }

        if (dateY !== null && showDate) {
            context.textAlign = 'center'
            context.fillStyle = secondaryText
            context.font = `550 ${story ? 24 : 18}px Inter, system-ui, sans-serif`
            drawShareText(context, String(data?.data || ''), width / 2, dateY, outlinedText, story ? 3.5 : 2.5)
        }
        if (showLogo && logoY !== null && ((isLightSurface && shareLogoDarkReady) || (!isLightSurface && shareLogoReady))) {
            context.save()
            if (isTransparent || isMapSurface) {
                context.shadowColor = 'rgba(2,8,18,.26)'
                context.shadowBlur = story ? 3 : 2
            }
            context.drawImage(isLightSurface ? shareLogoDark : shareLogo, width / 2 - logoWidth / 2, logoY, logoWidth, logoHeight)
            context.restore()
        }
        if (isMapSurface && mapAttribution && !thumbnail) drawShareMapAttribution(context, mapAttribution, width, height, side, true)
        else if ((mapDrawn || roadsDrawn) && !thumbnail) {
            context.textAlign = 'right'
            context.fillStyle = isLightSurface ? 'rgba(81,97,122,.72)' : 'rgba(232,237,244,.58)'
            context.font = `500 ${story ? 16 : 13}px Inter, system-ui, sans-serif`
            drawShareText(context, tr('activity.share.road_data'), width - side, footnoteY, outlinedText, 2)
        }
        return {mapDrawn, roadsDrawn}
    }
    const shareSegmentName = (segment, index) => String(segment?.titulo || segment?.rotulo || `${tr('activity.share.segment')} ${index + 1}`)
    const shareSegmentDistanceMeters = segment => {
        const value = Number(segment?.distancia_metros ?? segment?.rota?.distancia_m ?? 0)
        return Number.isFinite(value) && value > 0 ? value : null
    }
    const shareSegmentDurationSeconds = segment => {
        const value = Number(segment?.duracao_segundos)
        return Number.isFinite(value) && value >= 0 ? value : null
    }
    const shareMetricNumericValue = (segment, type) => {
        const metric = availableShareMetrics(segment).find(item => shareMetricType(item) === type)
        if (!metric) return null
        const raw = String(metric.valor || '').replace(/\s/g, '').replace(',', '.')
        const value = Number.parseFloat(raw.replace(/[^0-9.+-]/g, ''))
        return Number.isFinite(value) ? value : null
    }
    const shareFormatDurationPrecise = seconds => formatShareDuration(seconds)
    const shareFormatPaceSeconds = seconds => formatSharePace(seconds)
    const shareComparisonCapabilities = segments => {
        const valid = segments.filter(Boolean)
        const distanceDuration = valid.map(segment => ({
            segment,
            distance: shareSegmentDistanceMeters(segment),
            duration: shareSegmentDurationSeconds(segment),
        })).filter(item => item.distance !== null && item.duration !== null && item.distance > 0 && item.duration >= 0)
        const distances = distanceDuration.map(item => item.distance)
        const averageDistance = distances.length >= 2 ? distances.reduce((sum, value) => sum + value, 0) / distances.length : 0
        const equalDistance = distances.length >= 2 && Math.max(...distances) - Math.min(...distances) <= Math.max(5, averageDistance * .02)
        const capabilities = []
        if (equalDistance && distanceDuration.length >= 2) capabilities.push({id:'time', label:tr('activity.share.compare_time'), direction:'lower', count:distanceDuration.length})
        if (distanceDuration.length >= 2) {
            capabilities.push({id:'pace', label:tr('activity.share.compare_pace'), direction:'lower', count:distanceDuration.length})
            capabilities.push({id:'speed', label:tr('activity.share.compare_speed'), direction:'higher', count:distanceDuration.length})
        }
        const powerCount = valid.filter(segment => shareMetricNumericValue(segment, 'power') !== null).length
        if (powerCount >= 2) capabilities.push({id:'power', label:tr('activity.share.compare_power'), direction:'higher', count:powerCount})
        const heartCount = valid.filter(segment => shareMetricNumericValue(segment, 'heart_avg') !== null).length
        if (heartCount >= 2) capabilities.push({id:'heart', label:tr('activity.share.compare_heart'), direction:'neutral', count:heartCount})
        return {capabilities, equalDistance, comparableCount:capabilities.reduce((maximum, capability) => Math.max(maximum, capability.count || 0), 0)}
    }
    const shareComparisonMetricValue = (segment, metricId) => {
        if (metricId === 'time') return shareSegmentDurationSeconds(segment)
        const distance = shareSegmentDistanceMeters(segment)
        const duration = shareSegmentDurationSeconds(segment)
        if (metricId === 'pace') return distance && duration !== null ? duration / (distance / 1000) : null
        if (metricId === 'speed') return distance && duration > 0 ? (distance / duration) * 3.6 : null
        if (metricId === 'power') return shareMetricNumericValue(segment, 'power')
        if (metricId === 'heart') return shareMetricNumericValue(segment, 'heart_avg')
        return null
    }
    const shareFormatComparisonValue = (metricId, value) => {
        if (value === null || !Number.isFinite(Number(value))) return ''
        if (metricId === 'time') return shareFormatDurationPrecise(value)
        if (metricId === 'pace') return shareFormatPaceSeconds(value)
        if (metricId === 'speed') return `${stridebrLocaleNumber(Number(value), 1)} km/h`
        if (metricId === 'power') return `${stridebrLocaleNumber(Number(value), 0)} W`
        if (metricId === 'heart') return `${stridebrLocaleNumber(Number(value), 0)} bpm`
        return String(value)
    }
    const stridebrLocaleNumber = (value, digits = 1, options = {}) => formatShareNumber(value, digits, digits, options)
    const shareFormatComparisonDelta = (metricId, delta) => {
        const value = Number(delta)
        if (!Number.isFinite(value)) return ''
        const sign = value > 0 ? '+' : value < 0 ? '−' : '±'
        const abs = Math.abs(value)
        if (metricId === 'time') return `${sign}${formatShareNumber(abs, 0, 3)} s`
        if (metricId === 'pace') return `${sign}${formatShareNumber(abs, 0, 1)} s/km`
        if (metricId === 'speed') return `${sign}${stridebrLocaleNumber(abs, 1)} km/h`
        if (metricId === 'power') return `${sign}${stridebrLocaleNumber(abs, 0)} W`
        if (metricId === 'heart') return `${sign}${stridebrLocaleNumber(abs, 0)} bpm`
        return `${sign}${formatShareNumber(abs, 0, 1)}`
    }
    const defaultShareComparisonMetric = segments => {
        const {capabilities, equalDistance} = shareComparisonCapabilities(segments)
        const family = shareSportFamily(shareData)
        if (equalDistance && capabilities.some(item => item.id === 'time')) return 'time'
        if (family === 'cycling' && capabilities.some(item => item.id === 'power')) return 'power'
        if (family === 'cycling' && capabilities.some(item => item.id === 'speed')) return 'speed'
        if (capabilities.some(item => item.id === 'pace')) return 'pace'
        return capabilities[0]?.id || ''
    }
    const populateShareComparisonControls = () => {
        if (!shareComparisonMetric || !shareComparisonReference) return
        const selected = shareSelectedIndexesForScope().map(index => shareDataForSegment(index)).filter(Boolean)
        const {capabilities} = shareComparisonCapabilities(selected)
        const previousMetric = shareComparisonMetric.value
        const defaultMetric = defaultShareComparisonMetric(selected)
        shareComparisonMetric.replaceChildren()
        capabilities.forEach(item => {
            const option = document.createElement('option')
            option.value = item.id
            option.textContent = item.label
            shareComparisonMetric.append(option)
        })
        shareComparisonMetric.value = capabilities.some(item => item.id === previousMetric) ? previousMetric : defaultMetric
        const currentCapability = capabilities.find(item => item.id === shareComparisonMetric.value)
        const references = currentCapability?.direction === 'neutral'
            ? [{id:'average', label:tr('activity.share.reference_average')}]
            : [{id:'best', label:tr('activity.share.reference_best')}, {id:'average', label:tr('activity.share.reference_average')}]
        const targetValue = Number(shareData?.comparison_targets?.[shareComparisonMetric.value])
        if (Number.isFinite(targetValue)) references.push({id:'target', label:tr('activity.share.reference_target')})
        const previousReference = shareComparisonReference.value
        shareComparisonReference.replaceChildren()
        references.forEach(item => {
            const option = document.createElement('option')
            option.value = item.id
            option.textContent = item.label
            shareComparisonReference.append(option)
        })
        shareComparisonReference.value = references.some(item => item.id === previousReference) ? previousReference : references[0]?.id || 'best'
    }
    const shareComparisonRows = (segments, metricId = shareComparisonMetric?.value || defaultShareComparisonMetric(segments), referenceId = shareComparisonReference?.value || 'best') => {
        const capability = shareComparisonCapabilities(segments).capabilities.find(item => item.id === metricId)
        const rows = segments.map((segment, index) => ({segment, index, value:shareComparisonMetricValue(segment, metricId)})).filter(row => row.value !== null && Number.isFinite(row.value))
        if (!rows.length) return {metricId, referenceId, rows:[], reference:null, best:null, capability}
        const values = rows.map(row => row.value)
        const best = capability?.direction === 'higher' ? Math.max(...values) : capability?.direction === 'neutral' ? null : Math.min(...values)
        const average = values.reduce((sum, value) => sum + value, 0) / values.length
        const target = Number(shareData?.comparison_targets?.[metricId])
        const reference = referenceId === 'average' ? average : referenceId === 'target' && Number.isFinite(target) ? target : best
        return {metricId, referenceId, rows, reference, best, capability}
    }
    const shareHighlightResult = segments => {
        const metricId = defaultShareComparisonMetric(segments)
        const comparison = shareComparisonRows(segments, metricId, 'best')
        if (!comparison.rows.length) return null
        let row
        if (comparison.capability?.direction === 'higher') row = comparison.rows.reduce((best, item) => item.value > best.value ? item : best)
        else row = comparison.rows.reduce((best, item) => item.value < best.value ? item : best)
        const headline = metricId === 'time'
            ? tr('activity.share.best_time')
            : metricId === 'pace'
                ? tr('activity.share.best_pace')
                : metricId === 'speed'
                    ? tr('activity.share.fastest')
                    : metricId === 'power'
                        ? tr('activity.share.highest_power')
                        : tr('activity.share.highlight')
        return {...row, metricId, headline, valueText:shareFormatComparisonValue(metricId, row.value)}
    }
    const sessionSurfaceState = (compositionId, token, renderPurpose = 'preview', mapRasterScale = .72, selectedIndexesOverride = null) => {
        const definition = SHARE_COMPOSITION_REGISTRY[compositionId] || SHARE_COMPOSITION_REGISTRY.session_summary
        const format = SHARE_FORMATS.story
        const preset = getSharePreset(activeSharePresetId)
        const color = getSharePresetColor(preset.id) || getShareColorValue()
        const selectedIndexes = Array.isArray(selectedIndexesOverride) ? selectedIndexesOverride : shareSelectedIndexesForScope()
        const segments = selectedIndexes.map((index) => ({index, data:shareDataForSegment(index)})).filter(item => item.data)
        const geometryCount = segments.filter(item => shareHasRoute(item.data)).length
        const mapAllowed = definition.supportsMap && geometryCount > 0
        const mode = preset.mode === 'map' && mapAllowed ? 'map' : preset.mode === 'map' ? 'stats' : preset.mode
        const light = color === 'light' && mode !== 'photo' && mode !== 'transparent' && mode !== 'map'
        const compactMetrics = getShareCompactMetricSelection()
        const comparisonMetricId = shareComparisonMetric?.value || defaultShareComparisonMetric(segments.map(item => item.data))
        const comparisonReferenceId = shareComparisonReference?.value || 'best'
        const routeToggleAllowed = Boolean(definition.supportsRouteToggle && geometryCount > 0)
        const showRoute = definition.id === 'session_overview' || definition.id === 'session_by_segment'
            ? geometryCount > 0
            : routeToggleAllowed
                ? shareShows('route')
                : false
        return {
            definition, format, preset, color, segments, geometryCount, mapAllowed, mode, light, token, renderPurpose, mapRasterScale,
            compactPrimaryMetric: compactMetrics.primary,
            compactSecondaryMetric: compactMetrics.secondary,
            comparisonMetricId,
            comparisonReferenceId,
            routeToggleAllowed,
            showRoute,
            commonDistance: shareCommonSegmentDistanceMeters(segments),
        }
    }
    const drawSessionBase = async (context, state) => {
        const {format, color, mode, light, mapAllowed, segments, token} = state
        const {width, height} = format
        context.clearRect(0,0,width,height)
        if (mode === 'transparent') return {outlined:true, mapDrawn:false, attribution:''}
        if (mode === 'photo') {
            const image = sharePhoto || sharePreviewPhoto
            if (image) coverImage(context, image, width, height)
            else drawPhotoPlaceholder(context, width, height)
            context.fillStyle='rgba(3,9,18,.48)'; context.fillRect(0,0,width,height)
            return {outlined:false,mapDrawn:false,attribution:''}
        }
        if (mode === 'map' && mapAllowed) {
            const coordinates = segments.flatMap(item => routeCoordinatesForSharing(item.data))
            if (coordinates.length >= 2) {
                const viewport = createShareMapViewport(coordinates, width, height, {x:0,y:0,width,height}, 100, getShareMapStyleValue(), state.mapRasterScale)
                if (viewport) {
                    const result = await compositeShareMapBackground(context, {width,height,mapStyle:getShareMapStyleValue(),mapRasterScale:state.mapRasterScale,renderPurpose:state.renderPurpose,routeCoordinates:coordinates}, viewport, token)
                    if (result.drawn) {
                        const shade=context.createLinearGradient(0,0,0,height); shade.addColorStop(0,'rgba(3,8,16,.22)'); shade.addColorStop(1,'rgba(3,8,16,.56)'); context.fillStyle=shade; context.fillRect(0,0,width,height)
                        state.mapViewport=viewport
                        return {outlined:true,mapDrawn:true,attribution:result.attribution || ''}
                    }
                }
            }
        }
        fillShareBackground(context,width,height,color)
        return {outlined:false,mapDrawn:false,attribution:''}
    }
    const SESSION_VISUAL = Object.freeze({
        frame: Object.freeze({titleY:.118, logoY:.865, logoWidth:.25}),
        type: Object.freeze({
            title:36,
            label:24,
            meta:22,
            value:46,
            strongValue:62,
            hero:128,
            segmentLabel:22,
            segmentPrimary:52,
            segmentSecondary:25,
            compactLabel:38,
            compactPrimary:100,
            compactSecondary:58,
        }),
        spacing: Object.freeze({
            labelValueGap:76,
            compactLabelValueGap:84,
            compactPrimarySecondaryGap:70,
            compactInterItemGap:118,
        }),
        columns: Object.freeze({
            comparison:Object.freeze({id:.0,value:.18,delta:1,barStart:.0,barWidth:.86}),
        }),
        stage: Object.freeze({
            session_overview:Object.freeze({x:.14,y:.205,width:.72,height:.585}),
            session_by_segment:Object.freeze({x:.14,y:.19,width:.72,height:.60}),
            session_comparison:Object.freeze({x:.14,y:.265,width:.72,height:.50}),
            session_highlight:Object.freeze({x:.12,y:.225,width:.76,height:.555}),
            session_sequence:Object.freeze({x:.14,y:.215,width:.72,height:.585}),
            session_summary:Object.freeze({x:.12,y:.245,width:.76,height:.54}),
            session_list:Object.freeze({x:.105,y:.19,width:.79,height:.575}),
            session_minimal:Object.freeze({x:.105,y:.235,width:.79,height:.54}),
            session_compact:Object.freeze({x:.20,y:.19,width:.60,height:.52}),
        }),
    })
    const fitSharePeerFontSize = (context, texts, {weight=800, baseSize=52, minSize=38, maxWidth=Infinity, family='Inter, system-ui, sans-serif'}={}) => {
        const values=(Array.isArray(texts)?texts:[]).map(value=>String(value || '')).filter(Boolean)
        let size=baseSize
        while(size>minSize){
            context.font=`${weight} ${size}px ${family}`
            if(values.every(value=>context.measureText(value).width<=maxWidth))break
            size-=2
        }
        return size
    }
    const drawSessionLogo = (context, state, y = SESSION_VISUAL.frame.logoY, widthScale = 1) => {
        const {width,height}=state.format
        if (!((state.light && shareLogoDarkReady) || (!state.light && shareLogoReady))) return false
        const image=state.light?shareLogoDark:shareLogo
        const logoWidth=width*SESSION_VISUAL.frame.logoWidth*widthScale
        const logoHeight=logoWidth*(552.6/1408.82)
        context.drawImage(image,width/2-logoWidth/2,height*y-logoHeight/2,logoWidth,logoHeight)
        return true
    }
    const drawSessionFixedFrame = (context, state, surface, {title=true, logo=true}={}) => {
        const {format, light} = state
        const {width,height}=format
        const primary = light ? '#223148' : '#f8fafc'
        const accent = light ? '#526b9c' : '#a8b8e8'
        if (title) drawShareSportLabel(context, String(shareData?.modalidade || tr('common.activity')).toUpperCase(), width*.5, height*SESSION_VISUAL.frame.titleY, width*.52, {fontSize:SESSION_VISUAL.type.title,color:accent,outlined:surface.outlined,lineColor:light?'rgba(82,107,156,.52)':'rgba(159,177,219,.62)',lineWidth:2,gap:22})
        if (logo) drawSessionLogo(context,state)
        context.fillStyle=primary
    }
    const shareSessionStage = state => {
        const token=SESSION_VISUAL.stage[state.definition?.id] || state.format.anchors.contentStage
        const w=state.format.width, h=state.format.height
        return {x:token.x*w,y:token.y*h,width:token.width*w,height:token.height*h}
    }
    const drawSessionRoute = (context, data, box, index, state, {commonProject=null,widthScale=1}={}) => {
        const coords=routeCoordinatesForSharing(data)
        if (coords.length<2) return false
        const color=SHARE_BLUE_SERIES[index%SHARE_BLUE_SERIES.length]
        if (commonProject) drawRoute(context,coords.map(([lon,lat])=>commonProject(lon,lat)),true,state.light,{color,coreColor:'#eef3ff',showEndpoints:false,widthScale})
        else drawShareGeometry(context,{geometry:{type:'LineString',coordinates:coords},viewport:box,style:{color,coreColor:'#eef3ff'}},true,state.light,{color,coreColor:'#eef3ff',showEndpoints:false,widthScale})
        return true
    }
    const sessionPrimaryMetric = segment => {
        const family=shareSportFamily(segment)
        const duration=shareSegmentDurationSeconds(segment), distance=shareSegmentDistanceMeters(segment)
        if (family==='running' && distance && duration!==null) return {label:tr('activity.share.pace'),value:shareFormatPaceSeconds(duration/(distance/1000)),type:'pace'}
        if (family==='cycling' && distance && duration>0) return {label:tr('activity.share.compare_speed'),value:`${stridebrLocaleNumber((distance/duration)*3.6,1)} km/h`,type:'speed'}
        const power=availableShareMetrics(segment).find(item=>shareMetricType(item)==='power')
        if (power && shareMetricHasValue(power.valor)) return {label:String(power.rotulo || ''),value:String(power.valor || ''),type:'power'}
        if (duration!==null) return {label:tr('activity.share.duration'),value:shareFormatDurationPrecise(duration),type:'time'}
        const fallback=availableShareMetrics(segment).find(item=>shareMetricHasValue(item?.valor))
        return fallback ? {label:String(fallback.rotulo || ''),value:String(fallback.valor || ''),type:shareMetricType(fallback)} : {label:'',value:'',type:'other'}
    }
    const shareSportContext = (subject = null, distance = null) => sportContextEngine?.context?.({
        slug: String(subject?.modalidade_slug || subject?.sport || shareData?.modalidade_slug || ''),
        family: String(subject?.modalidade_familia_hub || subject?.familia_hub || ''),
        registered_m: Number.isFinite(Number(distance)) ? Number(distance) : null,
        segment: Boolean(subject && subject !== shareData),
    }) || {}
    const shareFormatDistanceMeters = (distance, subject = null) => {
        const value = Number(distance)
        if (!Number.isFinite(value) || value <= 0) return ''
        if (sportContextEngine?.formatDistance) return sportContextEngine.formatDistance(value, shareSportContext(subject, value), sportContextLocale())
        if (value >= 1000) return `${formatShareNumber(value / 1000, 2, 2)} km`
        return `${formatShareNumber(value, value < 100 ? 1 : 0, value < 100 ? 1 : 0)} m`
    }
    const sessionMetricLabel = metricId => ({
        distance: tr('activity.share.distance'),
        time: tr('activity.share.compare_time'),
        pace: tr('activity.share.compare_pace'),
        speed: tr('activity.share.compare_speed'),
        power: tr('activity.share.compare_power'),
        heart: tr('activity.share.compare_heart'),
        cadence: tr('activity.share.cadence'),
    }[metricId] || tr('activity.statistics'))
    const sessionSegmentMetric = (segment, metricId) => {
        if (metricId === 'distance') {
            const distance = shareSegmentDistanceMeters(segment)
            return distance ? {id:'distance', label: sessionMetricLabel('distance'), value: shareFormatDistanceMeters(distance, segment), numeric: distance} : null
        }
        if (metricId === 'cadence') {
            const value = shareMetricNumericValue(segment, 'cadence')
            return value !== null ? {id:'cadence', label: sessionMetricLabel('cadence'), value: `${stridebrLocaleNumber(value, 0)} rpm`, numeric: value} : null
        }
        const value = shareComparisonMetricValue(segment, metricId)
        return value !== null && Number.isFinite(value)
            ? {id: metricId, label: sessionMetricLabel(metricId), value: shareFormatComparisonValue(metricId, value), numeric: value}
            : null
    }
    const sessionAvailableMetricChoices = segments => {
        const base = ['time', 'pace', 'speed', 'power', 'heart', 'cadence', 'distance']
        return base.filter(metricId => segments.some(segment => sessionSegmentMetric(segment, metricId)))
            .map(metricId => ({id: metricId, label: sessionMetricLabel(metricId)}))
    }
    const shareCommonSegmentDistanceMeters = segments => {
        const distances = segments.map(item => shareSegmentDistanceMeters(item.data || item)).filter(value => value !== null)
        if (distances.length < 2 || distances.length !== segments.length) return null
        const average = distances.reduce((sum, value) => sum + value, 0) / distances.length
        if (Math.max(...distances) - Math.min(...distances) > Math.max(5, average * .02)) return null
        return average
    }
    const populateShareCompactMetricControls = () => {
        if (!shareSessionCompactPrimary || !shareSessionCompactSecondary) return
        const segments = shareSelectedIndexesForScope().map(index => shareDataForSegment(index)).filter(Boolean)
        const choices = sessionAvailableMetricChoices(segments)
        const previousPrimary = shareSessionCompactPrimary.value
        const previousSecondary = shareSessionCompactSecondary.value
        shareSessionCompactPrimary.replaceChildren()
        shareSessionCompactSecondary.replaceChildren()
        choices.forEach(choice => {
            const primaryOption = document.createElement('option')
            primaryOption.value = choice.id
            primaryOption.textContent = choice.label
            shareSessionCompactPrimary.append(primaryOption)
            const secondaryOption = document.createElement('option')
            secondaryOption.value = choice.id
            secondaryOption.textContent = choice.label
            shareSessionCompactSecondary.append(secondaryOption)
        })
        const noneOption = document.createElement('option')
        noneOption.value = ''
        noneOption.textContent = tr('common.none')
        shareSessionCompactSecondary.prepend(noneOption)
        const defaultPrimary = defaultShareComparisonMetric(segments) || choices[0]?.id || ''
        shareSessionCompactPrimary.value = choices.some(choice => choice.id === previousPrimary) ? previousPrimary : defaultPrimary
        const defaultSecondary = choices.find(choice => choice.id !== shareSessionCompactPrimary.value)?.id || ''
        shareSessionCompactSecondary.value = choices.some(choice => choice.id === previousSecondary) && previousSecondary !== shareSessionCompactPrimary.value ? previousSecondary : defaultSecondary
    }
    const getShareCompactMetricSelection = () => {
        const segments = shareSelectedIndexesForScope().map(index => shareDataForSegment(index)).filter(Boolean)
        const choices = sessionAvailableMetricChoices(segments)
        const defaultPrimary = defaultShareComparisonMetric(segments) || choices[0]?.id || ''
        const primary = choices.some(choice => choice.id === shareSessionCompactPrimary?.value) ? shareSessionCompactPrimary.value : defaultPrimary
        let secondary = choices.some(choice => choice.id === shareSessionCompactSecondary?.value) ? shareSessionCompactSecondary.value : ''
        if (secondary === primary) secondary = ''
        return {primary, secondary}
    }
    const drawSessionOverflow = (context, count, x, y, light, align = 'center') => {
        if (count <= 0) return
        context.textAlign = align
        context.fillStyle = light ? '#526178' : '#aab6c8'
        context.font = '700 26px Inter, system-ui, sans-serif'
        context.fillText(`+${count} ${tr('activity.share.segments').toLowerCase()}`, x, y)
    }
    const drawSessionOverview = (context, state, stage) => {
        const routeItems = state.segments.filter(item => shareHasRoute(item.data))
        const visibleRoutes = routeItems.slice(0, 3)
        const routeTop = stage.y
        const routeAreaH = stage.height * .55
        if (state.mapViewport) {
            const commonProject = state.mapViewport.project.bind(state.mapViewport)
            routeItems.forEach((item, pos) => drawSessionRoute(context, item.data, {x:stage.x,y:routeTop,width:stage.width,height:routeAreaH}, pos, state, {commonProject, widthScale:.92}))
        } else if (visibleRoutes.length) {
            const gap = state.format.height * .018
            const rowH = (routeAreaH - gap * Math.max(0, visibleRoutes.length - 1)) / visibleRoutes.length
            visibleRoutes.forEach((item, pos) => {
                const y = routeTop + pos * (rowH + gap)
                drawSessionRoute(context, item.data, {x:stage.x + stage.width*.015,y:y + rowH*.06,width:stage.width*.97,height:rowH*.88}, pos, state, {widthScale:.92})
            })
        }
        const sessionMetrics = sessionShareMetrics(state.segments.map(item => item.index)).filter(metric => shareMetricHasValue(metric?.valor))
        const hero = sessionMetrics.find(metric => /dist[aâ]ncia/i.test(String(metric.rotulo || ''))) || sessionMetrics[0]
        const time = sessionMetrics.find(metric => /tempo|dura[cç][aã]o/i.test(String(metric.rotulo || '')))?.valor || ''
        const pace = sessionMetrics.find(metric => /ritmo|pace|velocidade|speed/i.test(String(metric.rotulo || '')))?.valor || ''
        const heroY = stage.y + stage.height * .71
        context.textAlign = 'center'
        if (hero) {
            const heroValue=String(hero.valor || '')
            let heroSize=SESSION_VISUAL.type.hero*.88
            context.fillStyle = state.light ? '#223148' : '#f8fafc'
            context.font = `900 ${heroSize}px Inter, system-ui, sans-serif`
            while (heroSize > 76 && context.measureText(heroValue).width > stage.width*.92) {
                heroSize -= 2
                context.font = `900 ${heroSize}px Inter, system-ui, sans-serif`
            }
            context.fillText(heroValue, stage.x + stage.width/2, heroY)
        }
        const metaLine=[time,pace,tr('activity.share.segment_count',{count:state.segments.length})].filter(Boolean).join(' · ')
        context.fillStyle = state.light ? '#64748b' : '#c0c8d6'
        context.font = '650 30px Inter, system-ui, sans-serif'
        context.fillText(metaLine, stage.x + stage.width/2, heroY + 74)
    }
    const drawSessionBySegment = (context, state, stage) => {
        const capacity = Math.min(4, state.definition.maxVisibleSegments || 4)
        const visible = state.segments.slice(0, capacity)
        const gap = state.format.height * .014
        const usableH = stage.height - (state.segments.length > visible.length ? 56 : 0)
        const rowH = (usableH - gap * Math.max(0, visible.length - 1)) / Math.max(1, visible.length)
        const routeWidth = stage.width * .67
        const textX = stage.x + routeWidth + stage.width*.035
        visible.forEach((item, pos) => {
            const y = stage.y + pos * (rowH + gap)
            if (shareHasRoute(item.data)) drawSessionRoute(context, item.data, {x:stage.x,y:y+rowH*.08,width:routeWidth,height:rowH*.84}, pos, state, {widthScale:.9})
            const cy=y+rowH*.5
            context.textAlign='left'
            context.fillStyle=state.light?'#64748b':'#aab6c8'
            context.font='700 20px Inter, system-ui, sans-serif'
            context.fillText(shareSegmentName(item.data,item.index).toUpperCase(),textX,cy-48)
            const distance=shareSegmentDistanceMeters(item.data)
            if(distance){
                context.fillStyle=state.light?'#223148':'#f8fafc'
                context.font='820 34px Inter, system-ui, sans-serif'
                context.fillText(shareFormatDistanceMeters(distance,item.data),textX,cy-7)
            }
            const metric=sessionPrimaryMetric(item.data)
            context.fillStyle=state.light?'#223148':'#f8fafc'
            context.font='780 26px Inter, system-ui, sans-serif'
            context.fillText(metric.value,textX,cy+31)
        })
        drawSessionOverflow(context,state.segments.length-visible.length,stage.x+stage.width/2,stage.y+stage.height+22,state.light)
    }
    const drawSessionComparison = (context, state, stage) => {
        const all=state.segments.map(item=>item.data)
        const comparison=shareComparisonRows(all,state.comparisonMetricId,state.comparisonReferenceId)
        const visible=comparison.rows.slice(0,Math.min(4,state.definition.maxVisibleSegments||4))
        if(!visible.length)return
        const values=visible.map(row=>row.value)
        const best=comparison.capability?.direction==='higher'?Math.max(...values):Math.min(...values)
        const worst=comparison.capability?.direction==='higher'?Math.min(...values):Math.max(...values)
        const contextText=state.commonDistance?`${shareFormatDistanceMeters(state.commonDistance,state.segments[0]?.data || shareData)} · ${state.segments.length} ${tr('activity.share.segments').toLowerCase()}`:(comparison.capability?.label||'')
        context.textAlign='center'
        context.fillStyle=state.light?'#64748b':'#aab6c8'
        context.font='700 28px Inter, system-ui, sans-serif'
        context.fillText(String(contextText).toUpperCase(),stage.x+stage.width/2,stage.y)
        const top=stage.y+86
        const gap=34
        const rowH=(stage.height-86-gap*Math.max(0,visible.length-1))/Math.max(1,visible.length)
        const columns=SESSION_VISUAL.columns.comparison
        const idX=stage.x+stage.width*columns.id
        const valueX=stage.x+stage.width*columns.value
        const deltaX=stage.x+stage.width*columns.delta
        const valueTexts=visible.map(row=>shareFormatComparisonValue(comparison.metricId,row.value))
        const valueSize=fitSharePeerFontSize(context,valueTexts,{weight:860,baseSize:46,minSize:40,maxWidth:stage.width*.31})
        visible.forEach((row,pos)=>{
            const y=top+pos*(rowH+gap)
            const baseY=y+44
            context.textAlign='left'
            context.fillStyle=state.light?'#64748b':'#aab6c8'
            context.font='700 32px Inter, system-ui, sans-serif'
            context.fillText(`T${row.index+1}`,idX,baseY)
            context.fillStyle=state.light?'#223148':'#f8fafc'
            context.font=`860 ${valueSize}px Inter, system-ui, sans-serif`
            context.fillText(valueTexts[pos],valueX,baseY)
            const isBest=comparison.best!==null&&Math.abs(row.value-comparison.best)<.0005
            const deltaText=isBest&&comparison.referenceId==='best'?tr('activity.share.best'):comparison.reference!==null?shareFormatComparisonDelta(comparison.metricId,row.value-comparison.reference):''
            context.textAlign='right'
            context.fillStyle=isBest?(state.light?'#40507c':'#a8b8e8'):(state.light?'#64748b':'#aab6c8')
            context.font=`${isBest?'800':'650'} 31px Inter, system-ui, sans-serif`
            context.fillText(deltaText,deltaX,baseY)
            let ratio=.5
            if(comparison.capability?.direction==='higher')ratio=best===worst?.5:(row.value-worst)/(best-worst)
            else if(comparison.capability?.direction==='lower')ratio=best===worst?.5:(row.value-best)/(worst-best)
            else ratio=Math.max(...values)>0?row.value/Math.max(...values):.5
            const trackY=baseY+46
            const trackX=stage.x+stage.width*columns.barStart
            const trackW=stage.width*columns.barWidth
            const fillW=trackW*(.56+.44*Math.max(0,Math.min(1,ratio)))
            context.strokeStyle=SHARE_BLUE_SERIES[row.index%SHARE_BLUE_SERIES.length]
            context.lineWidth=5
            context.beginPath();context.moveTo(trackX,trackY);context.lineTo(trackX+fillW,trackY);context.stroke()
        })
        drawSessionOverflow(context,comparison.rows.length-visible.length,stage.x+stage.width/2,stage.y+stage.height+32,state.light)
    }
    const drawSessionHighlight = (context, state, stage, withRoute) => {
        const result=shareHighlightResult(state.segments.map(item=>item.data))
        if(!result)return
        const cx=stage.x+stage.width/2
        const distance=shareSegmentDistanceMeters(result.segment)
        const duration=shareSegmentDurationSeconds(result.segment)
        const meta=[distance?shareFormatDistanceMeters(distance,result.segment):'',duration!==null?shareFormatDurationPrecise(duration):''].filter(Boolean).join(' · ')
        context.textAlign='center'
        context.fillStyle=state.light?'#526b9c':'#a8b8e8'
        context.font='760 28px Inter, system-ui, sans-serif'
        context.fillText(result.headline.toUpperCase(),cx,stage.y+38)
        context.fillStyle=state.light?'#64748b':'#aab6c8'
        context.font='700 22px Inter, system-ui, sans-serif'
        context.fillText(shareSegmentName(result.segment,result.index).toUpperCase(),cx,stage.y+92)
        const leaderboard=shareComparisonRows(state.segments.map(item=>item.data),result.metricId,'best').rows
            .sort((a,b)=>result.metricId==='speed'||result.metricId==='power'?b.value-a.value:a.value-b.value)
            .slice(0,3)
        const leaderboardValues=leaderboard.map(row=>shareFormatComparisonValue(result.metricId,row.value))
        if(withRoute&&shareHasRoute(result.segment)){
            const routeY=stage.y+118
            const routeH=stage.height*.35
            drawSessionRoute(context,result.segment,{x:stage.x+stage.width*.10,y:routeY,width:stage.width*.80,height:routeH},result.index,state,{widthScale:.98})
            const heroY=routeY+routeH+80
            context.fillStyle=state.light?'#223148':'#f8fafc'
            context.font='900 74px Inter, system-ui, sans-serif'
            context.fillText(result.valueText,cx,heroY)
            context.font='800 38px Inter, system-ui, sans-serif'
            context.fillText(meta,cx,heroY+54)
            const listY=stage.y+stage.height*.86
            const colW=stage.width/Math.max(1,leaderboard.length)
            const valueSize=fitSharePeerFontSize(context,leaderboardValues,{weight:860,baseSize:52,minSize:40,maxWidth:colW*.88})
            leaderboard.forEach((row,index)=>{
                const x=stage.x+colW*(index+.5)
                context.fillStyle=state.light?'#64748b':'#aab6c8'
                context.font='700 22px Inter, system-ui, sans-serif'
                context.fillText(`T${row.index+1}`,x,listY)
                context.fillStyle=state.light?'#223148':'#f8fafc'
                context.font=`860 ${valueSize}px Inter, system-ui, sans-serif`
                context.fillText(leaderboardValues[index],x,listY+52)
            })
        }else{
            const heroY=stage.y+268
            context.fillStyle=state.light?'#223148':'#f8fafc'
            let heroSize=160
            context.font=`900 ${heroSize}px Inter, system-ui, sans-serif`
            while(heroSize>104&&context.measureText(result.valueText).width>stage.width*.98){heroSize-=4;context.font=`900 ${heroSize}px Inter, system-ui, sans-serif`}
            context.fillText(result.valueText,cx,heroY)
            context.fillStyle=state.light?'#64748b':'#c0c8d6'
            context.font='650 25px Inter, system-ui, sans-serif'
            context.fillText(meta,cx,heroY+62)
            const listTop=heroY+320
            const blockWidth=Math.min(stage.width*.68,620)
            const blockLeft=cx-blockWidth/2
            const labelWidth=blockWidth*.42
            const columnGap=52
            const labelX=blockLeft+labelWidth
            const valueX=labelX+columnGap
            const valueWidth=blockWidth-labelWidth-columnGap
            const valueSize=fitSharePeerFontSize(context,leaderboardValues,{weight:860,baseSize:54,minSize:46,maxWidth:valueWidth})
            leaderboard.forEach((row,index)=>{
                const y=listTop+index*200
                context.textAlign='right'
                context.fillStyle=state.light?'#64748b':'#aab6c8'
                context.font='700 28px Inter, system-ui, sans-serif'
                context.fillText(shareSegmentName(row.segment,row.index).toUpperCase(),labelX,y)
                context.textAlign='left'
                context.fillStyle=state.light?'#223148':'#f8fafc'
                context.font=`860 ${valueSize}px Inter, system-ui, sans-serif`
                context.fillText(leaderboardValues[index],valueX,y)
            })
        }
    }
    const drawSessionSequence = (context, state, stage, withRoute) => {
        const capacity=withRoute?4:5
        const visible=state.segments.slice(0,Math.min(capacity,state.definition.maxVisibleSegments||capacity))
        if(!visible.length)return
        const gap=withRoute?26:18
        const rowH=(stage.height-gap*Math.max(0,visible.length-1))/visible.length
        const canvasW=state.format.width
        const lineX=withRoute?stage.x+8:canvasW*.34
        context.strokeStyle=state.light?'rgba(82,107,156,.68)':'rgba(126,151,222,.72)'
        context.lineWidth=5
        context.beginPath();context.moveTo(lineX,stage.y+rowH*.5);context.lineTo(lineX,stage.y+stage.height-rowH*.5);context.stroke()
        visible.forEach((item,pos)=>{
            const y=stage.y+pos*(rowH+gap)
            const cy=y+rowH/2
            context.beginPath();context.arc(lineX,cy,11,0,Math.PI*2)
            context.fillStyle=state.light?'#fff':'#0f1d36';context.fill()
            context.lineWidth=5;context.strokeStyle=SHARE_BLUE_SERIES[pos%SHARE_BLUE_SERIES.length];context.stroke()
            if(withRoute&&shareHasRoute(item.data)){
                const routeX=lineX+78
                const routeW=stage.width*.43
                drawSessionRoute(context,item.data,{x:routeX,y:y+rowH*.09,width:routeW,height:rowH*.82},pos,state,{widthScale:.82})
                const textX=routeX+routeW+stage.width*.045
                context.textAlign='left'
                context.fillStyle=state.light?'#64748b':'#aab6c8'
                context.font='700 22px Inter, system-ui, sans-serif'
                context.fillText(shareSegmentName(item.data,item.index).toUpperCase(),textX,cy-13)
                context.fillStyle=state.light?'#223148':'#f8fafc'
                context.font='820 34px Inter, system-ui, sans-serif'
                context.fillText(sessionPrimaryMetric(item.data).value,textX,cy+32)
            }else{
                const blockX=canvasW*.405
                const distance=shareSegmentDistanceMeters(item.data)
                const duration=shareSegmentDurationSeconds(item.data)
                const metric=sessionPrimaryMetric(item.data)
                context.textAlign='left'
                context.fillStyle=state.light?'#64748b':'#aab6c8'
                context.font='700 21px Inter, system-ui, sans-serif'
                context.fillText(shareSegmentName(item.data,item.index).toUpperCase(),blockX,cy-48)
                context.fillStyle=state.light?'#223148':'#f8fafc'
                context.font='840 52px Inter, system-ui, sans-serif'
                context.fillText(distance?shareFormatDistanceMeters(distance,item.data):metric.value,blockX,cy+4)
                context.fillStyle=state.light?'#64748b':'#c0c8d6'
                context.font='650 25px Inter, system-ui, sans-serif'
                context.fillText([duration!==null?shareFormatDurationPrecise(duration):'',metric.value].filter(Boolean).join(' · '),blockX,cy+45)
            }
        })
        drawSessionOverflow(context,state.segments.length-visible.length,stage.x+stage.width/2,stage.y+stage.height+34,state.light)
    }
    const drawSessionSummary = (context, state, stage) => {
        const metrics=sessionShareMetrics(state.segments.map(item=>item.index)).filter(metric=>shareMetricHasValue(metric?.valor)&&!(/^0(?:[,.]0+)?\s*m$/i).test(String(metric?.valor||'').trim()))
        if(!metrics.length)return
        const hero=metrics.find(metric=>/dist[aâ]ncia/i.test(String(metric.rotulo||'')))||metrics[0]
        const candidates=metrics.filter(metric=>metric!==hero&&metric.key!=='session-segments')
        const orderedKeys=['session-duration','session-weighted-pace','session-weighted-speed','session-weighted-100m','session-weighted-500m','session-best-pace','session-best-speed','session-elevation-total']
        const secondary=[]
        orderedKeys.forEach(key=>{const metric=candidates.find(item=>item.key===key);if(metric&&!secondary.includes(metric))secondary.push(metric)})
        candidates.forEach(metric=>{if(secondary.length<4&&!secondary.includes(metric))secondary.push(metric)})
        secondary.splice(4)
        const cx=stage.x+stage.width/2
        context.textAlign='center'
        context.fillStyle=state.light?'#64748b':'#aab6c8'
        context.font='700 24px Inter, system-ui, sans-serif'
        context.fillText(String(hero.rotulo||'').toUpperCase(),cx,stage.y+46)
        let heroSize=180
        context.fillStyle=state.light?'#223148':'#f8fafc'
        context.font=`900 ${heroSize}px Inter, system-ui, sans-serif`
        const heroText=String(hero.valor||'')
        while(heroSize>118&&context.measureText(heroText).width>stage.width*.96){heroSize-=2;context.font=`900 ${heroSize}px Inter, system-ui, sans-serif`}
        context.fillText(heroText,cx,stage.y+206)
        context.fillStyle=state.light?'#64748b':'#c0c8d6'
        context.font='650 25px Inter, system-ui, sans-serif'
        context.fillText(tr('activity.share.segment_count',{count:state.segments.length}),cx,stage.y+350)
        if(!secondary.length)return
        const gridTop=stage.y+665
        const colCenters=[stage.x+stage.width*.25,stage.x+stage.width*.75]
        const drawMetric=(metric,x,y)=>{
            context.textAlign='center'
            context.fillStyle=state.light?'#64748b':'#aab6c8'
            context.font='700 24px Inter, system-ui, sans-serif'
            context.fillText(String(metric.rotulo||'').toUpperCase(),x,y)
            context.fillStyle=state.light?'#223148':'#f8fafc'
            context.font='880 60px Inter, system-ui, sans-serif'
            context.fillText(String(metric.valor||''),x,y+58)
        }
        if(secondary.length===1)drawMetric(secondary[0],cx,gridTop)
        else if(secondary.length===2){drawMetric(secondary[0],colCenters[0],gridTop);drawMetric(secondary[1],colCenters[1],gridTop)}
        else if(secondary.length===3){drawMetric(secondary[0],colCenters[0],gridTop);drawMetric(secondary[1],colCenters[1],gridTop);drawMetric(secondary[2],cx,gridTop+190)}
        else{secondary.slice(0,4).forEach((metric,index)=>drawMetric(metric,colCenters[index%2],gridTop+Math.floor(index/2)*190))}
    }
    const drawSessionList = (context, state, stage) => {
        const rowTarget=180
        const capacity=Math.max(1,Math.min(state.definition.maxVisibleSegments||10,Math.floor(stage.height/rowTarget)))
        const visible=state.segments.slice(0,capacity)
        const rowH=stage.height/Math.max(1,visible.length)
        visible.forEach((item,pos)=>{
            const y=stage.y+pos*rowH
            if(pos>0){
                context.strokeStyle=state.light?'rgba(82,107,156,.34)':'rgba(168,184,232,.30)'
                context.lineWidth=2
                context.beginPath();context.moveTo(stage.x,y);context.lineTo(stage.x+stage.width,y);context.stroke()
            }
            const cy=y+rowH*.52
            context.textAlign='left'
            context.fillStyle=state.light?'#526b9c':'#a8b8e8'
            context.font='780 38px Inter, system-ui, sans-serif'
            context.fillText(String(pos+1),stage.x,cy-5)
            context.fillStyle=state.light?'#223148':'#f8fafc'
            context.font='760 38px Inter, system-ui, sans-serif'
            context.fillText(shareSegmentName(item.data,item.index),stage.x+58,cy-5)
            const distance=shareSegmentDistanceMeters(item.data)
            const duration=shareSegmentDurationSeconds(item.data)
            context.fillStyle=state.light?'#64748b':'#aab6c8'
            context.font='650 22px Inter, system-ui, sans-serif'
            context.fillText([distance?shareFormatDistanceMeters(distance,item.data):'',duration!==null?shareFormatDurationPrecise(duration):''].filter(Boolean).join(' · '),stage.x+58,cy+31)
            context.textAlign='right'
            context.fillStyle=state.light?'#223148':'#f8fafc'
            context.font='820 40px Inter, system-ui, sans-serif'
            context.fillText(sessionPrimaryMetric(item.data).value,stage.x+stage.width,cy-4)
        })
        drawSessionOverflow(context,state.segments.length-visible.length,stage.x+stage.width/2,stage.y+stage.height+38,state.light)
    }
    const drawSessionMinimal = (context, state, stage) => {
        const all=sessionShareMetrics(state.segments.map(item=>item.index)).filter(metric=>shareMetricHasValue(metric?.valor))
        if(!all.length)return
        const total=all.find(metric=>/dist[aâ]ncia/i.test(String(metric.rotulo||'')))||all[0]
        const secondary=all.find(metric=>metric!==total&&/ritmo|pace|velocidade|speed/i.test(String(metric.rotulo||'')))||all.find(metric=>metric!==total)||null
        const metrics=[{rotulo:'TOTAL',valor:total.valor},secondary,{rotulo:tr('activity.share.segments').toUpperCase(),valor:String(state.segments.length)}].filter(Boolean)
        const ys=[stage.y+stage.height*.20,stage.y+stage.height*.53,stage.y+stage.height*.82]
        context.textAlign='center'
        metrics.forEach((metric,index)=>{
            const y=ys[index]
            if(index>0){
                const lineY=(ys[index-1]+y)/2-4
                context.strokeStyle=state.light?'rgba(82,107,156,.28)':'rgba(168,184,232,.23)'
                context.lineWidth=2
                context.beginPath();context.moveTo(stage.x+stage.width*.01,lineY);context.lineTo(stage.x+stage.width*.99,lineY);context.stroke()
            }
            context.fillStyle=state.light?'#64748b':'#aab6c8'
            context.font='700 22px Inter, system-ui, sans-serif'
            context.fillText(String(metric.rotulo||'').toUpperCase(),stage.x+stage.width/2,y-(index===2?36:70))
            context.fillStyle=state.light?'#223148':'#f8fafc'
            const size=index===2?90:140
            context.font=`${index===2?'840':'900'} ${size}px Inter, system-ui, sans-serif`
            context.fillText(String(metric.valor||''),stage.x+stage.width/2,y+68)
        })
    }
    const drawSessionCompact = (context, state, stage) => {
        const secondaryEnabled=Boolean(state.compactSecondaryMetric)
        const capacity=secondaryEnabled?3:4
        const visible=state.segments.slice(0,Math.min(capacity,state.definition.maxVisibleSegments||capacity))
        if(!visible.length){drawSessionLogo(context,state,.74);return}
        const rows=visible.map(item=>({
            item,
            primary:sessionSegmentMetric(item.data,state.compactPrimaryMetric)||sessionPrimaryMetric(item.data),
            secondary:secondaryEnabled?sessionSegmentMetric(item.data,state.compactSecondaryMetric):null,
        }))
        const cx=stage.x+stage.width/2
        let cursor=stage.y+(state.commonDistance?88:152)
        if(state.commonDistance){
            context.textAlign='center'
            context.fillStyle=state.light?'#223148':'#f8fafc'
            context.font='860 46px Inter, system-ui, sans-serif'
            const commonDistanceText=shareFormatDistanceMeters(state.commonDistance,state.segments[0]?.data || shareData)
            context.fillText(commonDistanceText,cx,cursor)
            cursor+=112
        }
        const primaryTexts=rows.map(row=>String(row.primary?.value||''))
        const secondaryTexts=rows.map(row=>String(row.secondary?.value||'')).filter(Boolean)
        const primarySize=fitSharePeerFontSize(context,primaryTexts,{weight:900,baseSize:SESSION_VISUAL.type.compactPrimary,minSize:68,maxWidth:stage.width*.98})
        const secondarySize=fitSharePeerFontSize(context,secondaryTexts,{weight:840,baseSize:SESSION_VISUAL.type.compactSecondary,minSize:48,maxWidth:stage.width*.92})
        const labelValueGap=SESSION_VISUAL.spacing.compactLabelValueGap
        const secondaryBaselineGap=SESSION_VISUAL.spacing.compactPrimarySecondaryGap
        const blockH=secondaryEnabled?labelValueGap+secondaryBaselineGap+SESSION_VISUAL.spacing.compactInterItemGap:210
        rows.forEach(row=>{
            context.textAlign='center'
            context.fillStyle=state.light?'#64748b':'#aab6c8'
            context.font=`700 ${SESSION_VISUAL.type.compactLabel}px Inter, system-ui, sans-serif`
            context.fillText(`T${row.item.index+1}`,cx,cursor)
            context.fillStyle=state.light?'#223148':'#f8fafc'
            context.font=`900 ${primarySize}px Inter, system-ui, sans-serif`
            context.fillText(String(row.primary?.value||''),cx,cursor+labelValueGap)
            if(row.secondary){
                context.fillStyle=state.light?'#223148':'#f8fafc'
                context.font=`840 ${secondarySize}px Inter, system-ui, sans-serif`
                context.fillText(String(row.secondary.value||''),cx,cursor+labelValueGap+secondaryBaselineGap)
            }
            cursor+=blockH
        })
        const overflow=state.segments.length-visible.length
        if(overflow>0){
            context.textAlign='center'
            context.fillStyle=state.light?'#526178':'#c0c8d6'
            context.font='750 30px Inter, system-ui, sans-serif'
            context.fillText(`+${overflow}`,cx,cursor-8)
        }
        drawSessionLogo(context,state,overflow>0?(secondaryEnabled?.79:.78):(secondaryEnabled?.765:.735))
    }
    const drawShareSessionComposition = async (context, token, options = {}) => {
        if (!shareData) return false
        const state=sessionSurfaceState(activeShareCompositionId,token,String(options.renderPurpose || 'preview'),Math.max(.4,Math.min(1,Number(options.mapRasterScale)||.72)),Array.isArray(options.selectedIndexes)?options.selectedIndexes:null)
        if (typeof options.showRoute === 'boolean' && state.routeToggleAllowed) state.showRoute = options.showRoute
        const scope = normalizeShareScope(options.scope || getShareScope())
        if (!shareCompositionCompatibility(state.definition.id,{scope,format:'story',segmentCount:state.segments.length,geometryCount:state.geometryCount,comparableSegmentCount:shareComparisonCapabilities(state.segments.map(item=>item.data)).comparableCount})) return false
        const surface=await drawSessionBase(context,state)
        if(token!==shareRenderToken)return false
        const compact=state.definition.id==='session_compact'
        drawSessionFixedFrame(context,state,surface,{title:!compact&&state.definition.supportsTitle,logo:!compact})
        const stage=shareSessionStage(state)
        if(state.definition.id==='session_overview') drawSessionOverview(context,state,stage)
        else if(state.definition.id==='session_by_segment') drawSessionBySegment(context,state,stage)
        else if(state.definition.id==='session_comparison') drawSessionComparison(context,state,stage)
        else if(state.definition.id==='session_highlight') drawSessionHighlight(context,state,stage,state.showRoute)
        else if(state.definition.id==='session_sequence') drawSessionSequence(context,state,stage,state.showRoute)
        else if(state.definition.id==='session_summary') drawSessionSummary(context,state,stage)
        else if(state.definition.id==='session_list') drawSessionList(context,state,stage)
        else if(state.definition.id==='session_minimal') drawSessionMinimal(context,state,stage)
        else if(state.definition.id==='session_compact') drawSessionCompact(context,state,stage)
        if(surface.mapDrawn&&surface.attribution) drawShareMapAttribution(context,surface.attribution,state.format.width,state.format.height,54,true,24)
        return {mapDrawn:surface.mapDrawn,roadsDrawn:false}
    }
    const drawShareSegmentsOverview = async (context, token, options = {}) => drawShareSessionComposition(context, token, options)

    const drawShareCard = async token => {
        if (!shareCanvas || !shareData) return
        updateShareControlAvailability()
        const format = getShareFormat()
        const width = format.width
        const height = format.height
        const offscreen = document.createElement('canvas')
        offscreen.width = width
        offscreen.height = height
        const context = offscreen.getContext('2d', {alpha: true})
        const contentMode = getShareContentMode()
        const segmentMode = getShareSegmentMode()
        let config = null
        let result = null
        if (contentMode === 'segments' && segmentMode === 'together') {
            if (shareStatus) shareStatus.textContent = tr('activity.share.rendering_segments')
            result = await drawShareSegmentsOverview(context, token, {renderPurpose:'preview', mapRasterScale:.55})
        } else {
            const data = contentMode === 'segments' ? shareDataForSegment(getActiveShareSegmentIndex()) : shareData
            if (!data) return
            const selectedMetrics = getSelectedShareMetrics(data)
            config = readShareConfiguration({width, height, format: format.id, data, selectedMetrics, mapRasterScale: .55, renderPurpose: 'preview'})
            if (shareStatus) shareStatus.textContent = config.showMapBase ? tr('activity.share.rendering_map') : tr('activity.share.rendering_preview')
            result = await drawShareCardSurface(context, config, token)
        }
        if (!result || token !== shareRenderToken) return
        shareCanvas.width = width
        shareCanvas.height = height
        const output = shareCanvas.getContext('2d', {alpha: true})
        output.clearRect(0, 0, width, height)
        output.drawImage(offscreen, 0, 0)
        scheduleSharePreviewFit()
        if (config?.background === 'photo' && !sharePhoto) shareStatus.textContent = tr('activity.share.photo_help')
        else if (config?.showMapBase && !result.mapDrawn) shareStatus.textContent = tr('activity.share.map_fallback')
        else shareStatus.textContent = ''
    }
    if (window.__STRIDEBR_SHARE_TEST_HOOKS__) {
        window.StrideBRShareTest = Object.freeze({
            formats: SHARE_FORMATS,
            scopes: SHARE_SCOPES,
            compositions: SHARE_COMPOSITION_REGISTRY,
            blueSeries: SHARE_BLUE_SERIES,
            compatibility: (id, context = {}) => shareCompositionCompatibility(id, context),
            compatible: (context = {}) => compatibleShareCompositions(context).map(item => item.id),
            setData: data => { shareData = data || null; return shareData },
            renderState: (data, scope) => buildShareRenderState(data, scope),
            configuration: override => readShareConfiguration(override || {}),
            comparisonCapabilities: segments => shareComparisonCapabilities(segments),
            defaultComparisonMetric: segments => defaultShareComparisonMetric(segments),
            comparisonRows: (segments, metricId, referenceId) => shareComparisonRows(segments, metricId, referenceId),
            highlight: segments => shareHighlightResult(segments),
            formatDuration: value => shareFormatDurationPrecise(value),
            formatComparisonValue: (metricId, value) => shareFormatComparisonValue(metricId, value),
            formatComparisonDelta: (metricId, value) => shareFormatComparisonDelta(metricId, value),
            formatDistance: (meters, subject = null) => shareFormatDistanceMeters(meters, subject),
            primaryMetric: segment => sessionPrimaryMetric(segment),
            sessionMetrics: indexes => sessionShareMetrics(indexes),
            sessionSurfaceState: (compositionId, renderPurpose = 'preview', mapRasterScale = .72) => sessionSurfaceState(compositionId, shareRenderToken, renderPurpose, mapRasterScale),
            sessionStage: state => shareSessionStage(state),
            compactMetricLayout: (format, count, left = 0, width = 1000, top = 0, rowHeight = 100) => compactMetricLayout(format, count, left, width, top, rowHeight),
            selectedIndexesForScope: scope => shareSelectedIndexesForScope(scope),
            geometryCountForScope: scope => shareGeometryCountForScope(scope),
            shareableSegments: data => shareableSegmentsForData(data),
            renderCard: async (canvas, override = {}) => {
                if (!canvas) return {rendered:false, configuration:null, result:null}
                if (override.data) shareData = override.data
                const configuration = readShareConfiguration(override || {})
                canvas.width = configuration.width
                canvas.height = configuration.height
                const context = canvas.getContext('2d', {alpha:true})
                const token = ++shareRenderToken
                const result = await drawShareCardSurface(context, configuration, token)
                return {rendered:Boolean(result && token === shareRenderToken), configuration, result}
            },
            beginRender: () => ++shareRenderToken,
            currentRenderToken: () => shareRenderToken,
            mapViewport: (coordinates, width, height, frame, routeScale = 100, style = 'street', rasterScale = .55) => createShareMapViewport(coordinates, width, height, frame, routeScale, style, rasterScale),
            compositeMapBackground: async (canvas, configuration, viewport, token = shareRenderToken) => {
                const context = canvas.getContext('2d', {alpha:true})
                return compositeShareMapBackground(context, configuration, viewport, token)
            },
            clearMapCaches: () => {
                shareMapPreviewCache.clear()
                shareStreetLabelFreeCache.clear()
                shareStreetLabelFreeRequests.clear()
                roadCache.clear()
            },
            renderSession: async (canvas, override = {}) => {
                if (!canvas || !override.data) return {rendered:false, result:null}
                shareData = override.data
                activeShareCompositionId = String(override.compositionId || 'session_summary')
                activeSharePresetId = String(override.presetId || 'stats')
                if (override.color) setSharePresetColor(activeSharePresetId, override.color, {persist:false})
                canvas.width = SHARE_FORMATS.story.width
                canvas.height = SHARE_FORMATS.story.height
                const context = canvas.getContext('2d', {alpha:true})
                const token = ++shareRenderToken
                const selectedIndexes = Array.isArray(override.selectedIndexes) ? override.selectedIndexes : shareableSegmentsForData(shareData).map((_, index) => index)
                const result = await drawShareSessionComposition(context, token, {renderPurpose:String(override.renderPurpose || 'export'), mapRasterScale:Number(override.mapRasterScale) || 1, scope:override.scope || SHARE_SCOPES.session, selectedIndexes, showRoute:override.showRoute})
                return {rendered:Boolean(result && token === shareRenderToken), result, compositionId:activeShareCompositionId, width:canvas.width, height:canvas.height}
            },
        })
    }

    const syncShareColorSwatches = () => {
        shareModal?.querySelectorAll('[data-share-color-swatch]').forEach(swatch => {
            const definition = SHARE_BACKGROUND_COLORS[normalizeShareBackgroundColor(swatch.dataset.shareColorSwatch)]
            swatch.style.background = `linear-gradient(180deg, ${definition.stops[0]}, ${definition.stops[1]} 62%, ${definition.stops[2]})`
        })
    }
    const drawShareChoiceThumbnail = async (context, canvas, {kind, value}) => {
        const width = canvas.width, height = canvas.height
        context.clearRect(0, 0, width, height)
        context.save()
        context.lineCap = 'round'; context.lineJoin = 'round'
        if (kind === 'background') {
            if (value === 'transparent') {
                const size = 24
                for (let y = 0; y < height; y += size) for (let x = 0; x < width; x += size) {
                    context.fillStyle = ((x / size + y / size) % 2) ? '#d7dbe2' : '#f4f5f7'
                    context.fillRect(x, y, size, size)
                }
            } else if (value === 'map') {
                context.fillStyle = '#182435'; context.fillRect(0, 0, width, height)
                drawSharePreviewMapPattern(context, width, height, false)
            } else if (value === 'photo') {
                const gradient = context.createLinearGradient(0, 0, width, height)
                gradient.addColorStop(0, '#43546a'); gradient.addColorStop(1, '#172235')
                context.fillStyle = gradient; context.fillRect(0, 0, width, height)
                context.fillStyle = 'rgba(255,255,255,.7)'; context.beginPath(); context.arc(width*.72,height*.28,12,0,Math.PI*2); context.fill()
                context.fillStyle = 'rgba(255,255,255,.34)'; context.beginPath(); context.moveTo(0,height); context.lineTo(width*.38,height*.48); context.lineTo(width*.62,height*.72); context.lineTo(width*.82,height*.42); context.lineTo(width,height*.6); context.lineTo(width,height); context.fill()
            } else {
                fillShareBackground(context, width, height, getSharePresetColor('stats'))
            }
        } else {
            fillShareBackground(context, width, height, getSharePresetColor(activeSharePresetId))
            if (value === 'route') {
                const coords = [[.12,.68],[.27,.47],[.43,.59],[.57,.31],[.74,.44],[.88,.24]].map(([x,y]) => [x*width,y*height])
                drawRoute(context, coords, false, false, {color:'#5f82ff',coreColor:'#eef3ff',showEndpoints:false,widthScale:.42})
            } else if (value === 'sport') {
                await drawShareSportIcon(context, 'track_and_field', width/2, height/2, Math.min(width,height)*.42, '#6d8cff', false, true)
            } else {
                const metrics = [[.24,.34],[.68,.34],[.46,.68]]
                context.fillStyle = 'rgba(230,236,248,.75)'
                metrics.forEach(([x,y],index) => { context.fillRect(width*x, height*y, width*(index===2?.28:.22), 5) })
            }
        }
        context.restore()
    }

    const sharePresetPreviewFingerprint = () => JSON.stringify({
        preset: activeSharePresetId,
        colors: SHARE_CARD_PRESETS.map(preset => [preset.id, getSharePresetColor(preset.id)]),
    })
    const renderSharePresetPreviews = async () => {
        if ((!shareStyleGrid && !shareContentGrid) || sharePreviewRenderQueued) return
        const fingerprint = sharePresetPreviewFingerprint()
        if (fingerprint === sharePreviewFingerprint) return
        sharePreviewRenderQueued = true
        const token = shareRenderToken
        try {
            for (const preset of SHARE_CARD_PRESETS) {
                const canvas = shareStyleGrid?.querySelector(`[data-share-preset-preview="${preset.id}"]`)
                if (!canvas) continue
                await drawShareChoiceThumbnail(canvas.getContext('2d', {alpha: true}), canvas, {kind:'background', value:preset.id})
            }
            for (const content of SHARE_CONTENT_PRESETS) {
                const canvas = shareContentGrid?.querySelector(`[data-share-content-preview="${content.id}"]`)
                if (!canvas) continue
                await drawShareChoiceThumbnail(canvas.getContext('2d', {alpha: true}), canvas, {kind:'content', value:content.id})
            }
            if (token === shareRenderToken) sharePreviewFingerprint = fingerprint
        } finally {
            sharePreviewRenderQueued = false
        }
    }
    const scheduleSharePresetPreviews = () => {
        const run = () => {
            sharePreviewIdleHandle = 0
            renderSharePresetPreviews().catch(() => {})
        }
        if (sharePreviewIdleHandle) return
        if (typeof window.requestIdleCallback === 'function') sharePreviewIdleHandle = window.requestIdleCallback(run, {timeout: 220})
        else sharePreviewIdleHandle = window.setTimeout(run, 32)
    }
    function scheduleShareDraw() {
        const token = ++shareRenderToken
        syncSharePresetCards()
        syncShareContentCards()
        syncShareSessionLayoutCards()
        shareRenderPromise = Promise.resolve().then(() => drawShareCard(token)).catch(() => {})
        shareRenderPromise.then(scheduleSharePresetPreviews)
        return shareRenderPromise
    }
    const scheduleShareRouteScaleDraw = () => {
        syncShareRouteScaleLabel()
        if (shareRouteScaleFrame) return
        shareRouteScaleFrame = window.requestAnimationFrame(() => {
            shareRouteScaleFrame = 0
            scheduleShareDraw()
        })
    }
    const setSharePhoto = ({src, label, rememberPhotoPreset = true}) => {
        const image = new Image()
        image.onload = () => {
            sharePhoto = image
            if (sharePhotoName) sharePhotoName.textContent = label || tr('activity.share.photo_ready')
            updateSharePhotoPreview()
            if (rememberPhotoPreset) applySharePreset('photo')
            else { updateShareControlAvailability(); syncSharePresetCards(); scheduleShareDraw() }
        }
        image.src = src
    }
    const loadSharePhoto = input => {
        const file = input?.files?.[0]
        if (!file || !file.type.startsWith('image/')) {
            sharePhoto = null
            updateSharePhotoPreview()
            if (sharePhotoName) sharePhotoName.textContent = tr('activity.share.invalid_image')
            scheduleShareDraw()
            return
        }
        if (sharePhotoName) sharePhotoName.textContent = tr('activity.share.loading_photo')
        const reader = new FileReader()
        reader.onerror = () => { if (sharePhotoName) sharePhotoName.textContent = tr('activity.share.photo_open_error') }
        reader.onload = () => setSharePhoto({src: String(reader.result || ''), label: file.name || tr('activity.share.photo_selected')})
        reader.readAsDataURL(file)
    }
    const stopShareCameraStream = () => {
        if (shareCameraStream) shareCameraStream.getTracks().forEach(track => track.stop())
        shareCameraStream = null
        if (shareCameraVideo) shareCameraVideo.srcObject = null
    }
    const closeShareCamera = () => {
        stopShareCameraStream()
        if (shareCameraSheet) shareCameraSheet.hidden = true
    }
    const openShareCamera = async () => {
        if (!shareCameraSheet || !shareCameraVideo) {
            shareCameraInput?.click()
            return
        }
        if (!navigator.mediaDevices?.getUserMedia) {
            shareCameraInput?.click()
            return
        }
        shareCameraSheet.hidden = false
        if (shareCameraStatus) shareCameraStatus.textContent = tr('activity.share.camera_opening')
        try {
            shareCameraStream = await navigator.mediaDevices.getUserMedia({video: {facingMode: {ideal: 'environment'}}, audio: false})
            shareCameraVideo.srcObject = shareCameraStream
            await shareCameraVideo.play().catch(() => {})
            if (shareCameraStatus) shareCameraStatus.textContent = 'Ajuste o enquadramento e use a foto.'
        } catch (_) {
            closeShareCamera()
            shareCameraInput?.click()
        }
    }
    const captureShareCameraPhoto = () => {
        if (!shareCameraVideo || !shareCameraVideo.videoWidth || !shareCameraVideo.videoHeight) return
        const canvas = document.createElement('canvas')
        canvas.width = shareCameraVideo.videoWidth
        canvas.height = shareCameraVideo.videoHeight
        canvas.getContext('2d').drawImage(shareCameraVideo, 0, 0)
        const dataUrl = canvas.toDataURL('image/jpeg', .92)
        closeShareCamera()
        setSharePhoto({src: dataUrl, label: 'Foto capturada'})
    }
    const resetShareControls = () => {
        const segmented = shareSegments().length > 1
        const scope = segmented ? SHARE_SCOPES.session : SHARE_SCOPES.activity
        if (shareMasterSwitch) shareMasterSwitch.dataset.shareScope = scope
        shareSegmentList?.querySelectorAll('[data-share-segment-check]').forEach(input => { input.checked = true })
        if (shareSegmentPreviewSelect) shareSegmentPreviewSelect.selectedIndex = 0
        const hasRoute = segmented ? shareGeometryCountForScope(scope) > 0 : shareHasRoute(shareData)
        const preference = defaultSharePreference(segmented ? shareData : shareData, scope)
        selectShareFormat(preference.format)
        activeShareCompositionId = preference.composition
        activeShareContentId = hasRoute && !segmented ? 'route' : 'sport'
        activeSharePresetId = SHARE_PRESET_FALLBACK
        lastNonMapSharePresetId = SHARE_PRESET_FALLBACK
        sharePresetColors = {...shareDefaultPresetColors}
        syncShareColorSelection(activeSharePresetId)
        if (shareRouteScaleInput) shareRouteScaleInput.value = '100'
        setShareMapStyleValue('street', {persist: false})
        if (shareHeadingMode) shareHeadingMode.checked = true
        if (shareActivityRouteToggle) shareActivityRouteToggle.checked = hasRoute
        if (shareSessionRouteToggle) {
            shareSessionRouteToggle.checked = hasRoute
            delete shareSessionRouteToggle.dataset.sessionTouched
        }
        const dateToggle = shareModal?.querySelector('[data-share-show="date"]')
        if (dateToggle) dateToggle.checked = false
        if (shareMapToggle) shareMapToggle.checked = false
        if (shareCaption) shareCaption.value = ''
        populateShareMetricOptions({preserve: false})
        updateShareControlAvailability()
        scheduleShareDraw()
    }
    const closeShareCustomize = () => {
        if (!shareModal) return
        shareModal.classList.remove('is-customizing')
        if (window.innerWidth <= 900) {
            shareCustomizeSheet?.setAttribute('aria-hidden', 'true')
            shareMobileCustomizeButton?.focus?.({preventScroll: true})
        } else shareCustomizeSheet?.removeAttribute('aria-hidden')
    }
    const openShareCustomize = () => {
        if (!shareModal || window.innerWidth > 900) return
        shareModal.classList.add('is-customizing')
        shareCustomizeSheet?.setAttribute('aria-hidden', 'false')
        shareCustomizeSheet?.querySelector('[data-share-mobile-customize-close]')?.focus()
    }
    const syncShareViewportHeight = () => {
        const height = Math.round(window.visualViewport?.height || window.innerHeight || document.documentElement.clientHeight || 0)
        if (height > 0) document.documentElement.style.setProperty('--stridebr-share-vh', `${height}px`)
        scheduleSharePreviewFit()
    }
    const closeShareModal = () => {
        closeShareCamera()
        if (shareRouteExportSheet) shareRouteExportSheet.hidden = true
        shareRouteExportSets = []
        closeShareCustomize()
        if (shareModal) shareModal.hidden = true
        document.documentElement.classList.remove('activity-share-open')
        document.documentElement.style.removeProperty('--stridebr-share-vh')
    }
    buildShareContentCards()
    buildSharePresetCards()
    buildShareSessionLayoutCards()
    syncShareColorSwatches()
    enableShareStyleScroller()
    shareScopeButtons.forEach(button => button.addEventListener('click', () => {
        if (!shareMasterSwitch) return
        const scope = normalizeShareScope(button.dataset.shareScope)
        if (![SHARE_SCOPES.session, SHARE_SCOPES.singleSegment, SHARE_SCOPES.multipleSegments].includes(scope)) return
        shareMasterSwitch.dataset.shareScope = scope
        if (scope === SHARE_SCOPES.multipleSegments) {
            const checks = Array.from(shareSegmentList?.querySelectorAll('[data-share-segment-check]') || [])
            let selected = checks.filter(input => input.checked)
            if (selected.length < 2) {
                checks.forEach(input => {
                    if (selected.length >= 2 || input.checked) return
                    input.checked = true
                    selected.push(input)
                })
            }
        } else if (scope === SHARE_SCOPES.singleSegment && shareSegmentPreviewSelect && shareSegmentPreviewSelect.selectedIndex < 0) {
            shareSegmentPreviewSelect.selectedIndex = 0
        }
        const preferenceData = scope === SHARE_SCOPES.singleSegment ? shareDataForSegment(getActiveShareSegmentIndex()) : shareData
        activeShareCompositionId = firstCompatibleShareComposition(scope)
        populateShareMetricOptions({preserve: false})
        const preference = loadLastSharePreference(preferenceData || shareData, scope)
        applySharePreferenceToEditor(preferenceData || shareData, preference, scope)
        updateShareControlAvailability()
        scheduleShareDraw()
    }))
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-share-activity]')
        if (!button) return
        const drawer = button.closest('[data-activity-detail-drawer], [data-activity-detail]')
        const shareScript = drawer?.querySelector('[data-activity-share-data]')
        try { shareData = JSON.parse(shareScript?.textContent || '') } catch (_) { shareData = null }
        if (!shareData || !shareModal) return
        populateShareSegments()
        const segmented = shareSegments().length > 1
        const scope = segmented ? SHARE_SCOPES.session : SHARE_SCOPES.activity
        if (shareMasterSwitch) shareMasterSwitch.dataset.shareScope = scope
        shareSegmentList?.querySelectorAll('[data-share-segment-check]').forEach(input => { input.checked = true })
        if (shareSegmentPreviewSelect) shareSegmentPreviewSelect.selectedIndex = 0
        const preferenceData = shareData
        populateShareMetricOptions({preserve: false})
        if (shareCaption) shareCaption.value = ''
        if (shareRouteScaleInput && !shareRouteScaleInput.value) shareRouteScaleInput.value = '100'
        syncShareRouteScaleLabel()
        updateSharePhotoPreview()
        const preference = loadLastSharePreference(preferenceData, scope)
        shareModal.hidden = false
        document.documentElement.classList.add('activity-share-open')
        syncShareViewportHeight()
        applySharePreferenceToEditor(preferenceData, preference, scope)
        updateShareControlAvailability()
        scheduleSharePreviewFit()
        scheduleShareDraw()
        shareModal.querySelector('.activity-share-panel > header [data-close-share]')?.focus()
    })
    shareModal?.querySelectorAll('[data-close-share]').forEach(button => button.addEventListener('click', closeShareModal))
    window.visualViewport?.addEventListener('resize', () => { if (!shareModal?.hidden) syncShareViewportHeight() })
    window.visualViewport?.addEventListener('scroll', () => { if (!shareModal?.hidden) syncShareViewportHeight() })
    window.addEventListener('resize', () => { if (!shareModal?.hidden) syncShareViewportHeight() })
    window.addEventListener('orientationchange', () => { if (!shareModal?.hidden) window.setTimeout(syncShareViewportHeight, 120) })
    if (sharePreviewStage && window.ResizeObserver) {
        sharePreviewResizeObserver = new ResizeObserver(() => scheduleSharePreviewFit())
        sharePreviewResizeObserver.observe(sharePreviewStage)
    }
    shareMobileCustomizeButton?.addEventListener('click', openShareCustomize)
    shareModal?.querySelectorAll('[data-share-mobile-customize-close]').forEach(button => button.addEventListener('click', closeShareCustomize))
    shareMobilePhotoButton?.addEventListener('click', () => sharePhotoInput?.click())
    shareFormatInputs.forEach(input => input.addEventListener('change', () => { updateShareControlAvailability(); scheduleShareDraw() }))
    shareCompositionInputs.forEach(input => input.addEventListener('change', () => {
        if (!input.checked) return
        activeShareCompositionId = input.value === 'compact' ? 'compact' : 'standard'
        updateShareControlAvailability()
        scheduleShareDraw()
    }))
    shareColorInputs.forEach(input => input.addEventListener('change', () => { const selected = shareColorInputs.find(candidate => candidate.checked)?.value || getShareColorValue(); setSharePresetColor(activeSharePresetId, selected); scheduleShareDraw() }))
    shareRouteScaleInput?.addEventListener('input', scheduleShareRouteScaleDraw)
    shareRouteScaleInput?.addEventListener('change', () => {
        syncShareRouteScaleLabel()
        if (shareRouteScaleFrame) { window.cancelAnimationFrame(shareRouteScaleFrame); shareRouteScaleFrame = 0 }
        scheduleShareDraw()
    })
    shareMapStyleInputs.forEach(input => input.addEventListener('change', () => {
        if (!input.checked || input.disabled) return
        setShareMapStyleValue(input.value)
        scheduleShareDraw()
    }))
    shareMapToggle?.addEventListener('change', scheduleShareDraw)
    shareModal?.querySelectorAll('[data-share-show]').forEach(input => input.addEventListener('change', () => {
        if (input.dataset.shareShow === 'route') input.dataset.sessionTouched = '1'
        updateShareControlAvailability()
        scheduleShareDraw()
    }))
    ;[shareSessionCompactPrimary, shareSessionCompactSecondary].forEach(input => input?.addEventListener('change', () => {
        updateShareControlAvailability()
        scheduleShareDraw()
    }))
    shareHeadingMode?.addEventListener('change', () => {
        try { localStorage.setItem(SHARE_HEADING_MODE_KEY, getShareHeadingMode()) } catch (_) {}
        updateShareControlAvailability()
        scheduleShareDraw()
    })
    shareContentModeInputs.forEach(input => input.addEventListener('change', () => { updateShareControlAvailability(); scheduleShareDraw() }))
    shareSegmentModeInputs.forEach(input => input.addEventListener('change', () => { updateShareControlAvailability(); scheduleShareDraw() }))
    shareSegmentList?.addEventListener('change', event => {
        let selected = getSelectedShareSegmentIndexes()
        if (getShareScope() === SHARE_SCOPES.multipleSegments && selected.length < 2 && event.target?.matches?.('[data-share-segment-check]')) {
            event.target.checked = true
            selected = getSelectedShareSegmentIndexes()
            if (shareStatus) shareStatus.textContent = tr('activity.share.keep_two_segments', {}, 'Selecione pelo menos 2 trechos.')
        }
        if (!selected.includes(getActiveShareSegmentIndex()) && shareSegmentPreviewSelect) shareSegmentPreviewSelect.value = String(selected[0] ?? 0)
        populateShareMetricOptions({preserve: true})
        syncShareSegmentSelectionSummary()
        updateShareControlAvailability()
        scheduleShareDraw()
    })
    shareEditSegmentsButton?.addEventListener('click', () => {
        if (shareSegmentDisclosure) shareSegmentDisclosure.open = true
        if (window.innerWidth <= 900) openShareCustomize()
        window.requestAnimationFrame(() => {
            shareSegmentDisclosure?.scrollIntoView?.({block: 'nearest', behavior: 'smooth'})
            shareSegmentList?.querySelector('[data-share-segment-check]')?.focus?.({preventScroll: true})
        })
    })
    shareSelectAllButton?.addEventListener('click', () => {
        shareSegmentList?.querySelectorAll('[data-share-segment-check]').forEach(input => { input.checked = true })
        populateShareMetricOptions({preserve: true})
        syncShareSegmentSelectionSummary()
        updateShareControlAvailability()
        scheduleShareDraw()
    })
    shareComparisonMetric?.addEventListener('change', () => {
        populateShareComparisonControls()
        scheduleShareDraw()
    })
    shareComparisonReference?.addEventListener('change', scheduleShareDraw)
    shareSegmentPreviewSelect?.addEventListener('change', () => { populateShareMetricOptions({preserve: false}); updateShareControlAvailability(); scheduleShareDraw() })
    shareCaption?.addEventListener('input', scheduleShareDraw)
    shareMetricOptions?.addEventListener('change', event => {
        const checked = shareMetricOptions.querySelectorAll('[data-share-metric]:checked')
        if (checked.length > 4) {
            event.target.checked = false
            if (shareStatus) shareStatus.textContent = tr('activity.share.max_stats')
        }
        scheduleShareDraw()
    })
    sharePhotoInput?.addEventListener('change', () => loadSharePhoto(sharePhotoInput))
    shareCameraInput?.addEventListener('change', () => loadSharePhoto(shareCameraInput))
    shareModal?.querySelector('[data-share-open-camera]')?.addEventListener('click', openShareCamera)
    shareModal?.querySelectorAll('[data-share-close-camera]').forEach(button => button.addEventListener('click', closeShareCamera))
    shareCameraCaptureButton?.addEventListener('click', captureShareCameraPhoto)
    shareModal?.querySelectorAll('[data-reset-share]').forEach(button => button.addEventListener('click', () => {
        resetShareControls()
        applySharePreset(shareHasRoute(shareData) ? SHARE_PRESET_FALLBACK : 'stats', {remember: false})
        button.closest('details')?.removeAttribute('open')
        closeShareCustomize()
    }))
    const canvasPngBlob = canvas => new Promise(resolve => canvas?.toBlob(resolve, 'image/png'))
    const renderCurrentShareOutputCanvas = async () => {
        if (!shareData) return null
        const format = getShareFormat()
        const canvas = document.createElement('canvas')
        canvas.width = format.width
        canvas.height = format.height
        const context = canvas.getContext('2d', {alpha: true})
        const token = ++shareRenderToken
        const contentMode = getShareContentMode()
        const segmentMode = getShareSegmentMode()
        let rendered = null
        if (contentMode === 'segments' && segmentMode === 'together') {
            rendered = await drawShareSegmentsOverview(context, token, {renderPurpose:'export', mapRasterScale:1})
        } else {
            const data = contentMode === 'segments' ? shareDataForSegment(getActiveShareSegmentIndex()) : shareData
            if (!data) return null
            const selectedMetrics = getSelectedShareMetrics(data)
            const config = readShareConfiguration({
                width: format.width,
                height: format.height,
                format: format.id,
                data,
                selectedMetrics,
                mapRasterScale: 1,
                renderPurpose: 'export',
            })
            rendered = await drawShareCardSurface(context, config, token)
        }
        return rendered && token === shareRenderToken ? canvas : null
    }
    const shareBlob = async () => {
        await shareRenderPromise
        const previousStatus = shareStatus?.textContent || ''
        const mapActive = getSharePreset(activeSharePresetId).mode === 'map' && activeShareContentId === 'route'
        if (shareStatus && mapActive) shareStatus.textContent = tr('activity.share.rendering_map')
        try {
            const canvas = await renderCurrentShareOutputCanvas()
            return canvasPngBlob(canvas)
        } finally {
            if (shareStatus && shareStatus.textContent === tr('activity.share.rendering_map')) shareStatus.textContent = previousStatus
        }
    }
    const safeShareFilePart = value => String(value || 'trecho').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 42) || 'trecho'
    // Nome de arquivo único por atividade + data + horário do download, pra não
    // sobrescrever nem acumular "(1)", "(2)", "(3)"... quando a pessoa baixa
    // cards de atividades diferentes (ou a mesma atividade mais de uma vez).
    const buildShareFilename = (data, mode) => {
        const base = safeShareFilePart(data?.titulo || data?.modalidade || (mode === 'segments' ? 'trechos' : 'atividade'))
        const dateMatch = String(data?.data || '').match(/^(\d{2})\/(\d{2})\/(\d{4})$/)
        const datePart = dateMatch ? `${dateMatch[3]}${dateMatch[2]}${dateMatch[1]}` : ''
        const now = new Date()
        const stamp = `${String(now.getHours()).padStart(2, '0')}${String(now.getMinutes()).padStart(2, '0')}${String(now.getSeconds()).padStart(2, '0')}`
        const prefix = mode === 'segments' ? 'trechos-' : ''
        return prefix + [base, datePart, stamp].filter(Boolean).join('-') + '-stridebr.png'
    }
    const renderShareDataBlob = async data => {
        if (!data) return null
        const format = getShareFormat()
        const canvas = document.createElement('canvas')
        canvas.width = format.width
        canvas.height = format.height
        const context = canvas.getContext('2d', {alpha: true})
        const config = readShareConfiguration({data, format: format.id, width: format.width, height: format.height, selectedMetrics: availableShareMetrics(data).slice(0, 4), mapRasterScale: 1, renderPurpose: 'export'})
        const rendered = await drawShareCardSurface(context, config, shareRenderToken)
        if (!rendered) return null
        return canvasPngBlob(canvas)
    }
    const selectedSegmentFiles = async () => {
        const files = []
        for (const index of getSelectedShareSegmentIndexes()) {
            const data = shareDataForSegment(index)
            const blob = await renderShareDataBlob(data)
            if (!blob) continue
            files.push(new File([blob], `${String(index + 1).padStart(2, '0')}-${safeShareFilePart(data?.titulo)}-stridebr.png`, {type: 'image/png'}))
        }
        return files
    }
    const downloadBlob = (blob, filename) => {
        if (!blob) return
        const link = document.createElement('a')
        link.href = URL.createObjectURL(blob)
        link.download = filename
        document.body.append(link)
        link.click()
        link.remove()
        window.setTimeout(() => URL.revokeObjectURL(link.href), 1200)
    }
    shareDownloadButton?.addEventListener('click', async () => {
        if (getShareContentMode() === 'segments' && getShareSegmentMode() === 'separate') {
            const files = await selectedSegmentFiles()
            if (!files.length) {
                if (shareStatus) shareStatus.textContent = tr('activity.share.select_segment')
                return
            }
            files.forEach((file, index) => window.setTimeout(() => downloadBlob(file, file.name), index * 140))
            persistLastSharePreference(shareData)
            if (shareStatus) shareStatus.textContent = trn('activity.share.image_prepared.one', 'activity.share.image_prepared.other', files.length)
            return
        }
        const blob = await shareBlob()
        if (!blob) return
        const filename = buildShareFilename(shareData, getShareContentMode())
        downloadBlob(blob, filename)
        persistLastSharePreference(shareData)
    })
    shareCopyButton?.addEventListener('click', async () => {
        const blob = await shareBlob()
        if (!blob) return
        if (!navigator.clipboard?.write || typeof ClipboardItem === 'undefined') {
            if (shareStatus) shareStatus.textContent = tr('activity.share.clipboard_unavailable')
            return
        }
        try {
            await navigator.clipboard.write([new ClipboardItem({'image/png': blob})])
            persistLastSharePreference(shareData)
            if (shareStatus) shareStatus.textContent = tr('activity.share.copied')
        } catch (_) {
            if (shareStatus) shareStatus.textContent = tr('activity.share.copy_error')
        }
    })
    shareNativeButton?.addEventListener('click', async () => {
        let files = []
        if (getShareContentMode() === 'segments' && getShareSegmentMode() === 'separate') files = await selectedSegmentFiles()
        else {
            const blob = await shareBlob()
            if (blob) files = [new File([blob], buildShareFilename(shareData, getShareContentMode()), {type: 'image/png'})]
        }
        if (!files.length) return
        if (navigator.share && navigator.canShare?.({files})) {
            try {
                await navigator.share({files, title: tr('activity.share.native_title')})
                persistLastSharePreference(shareData)
            } catch (_) {}
        } else if (shareStatus) {
            shareStatus.textContent = tr('activity.share.native_unavailable')
        }
    })
    const selectedRouteExportColor = () => shareRouteExportColors.find(input => input.checked)?.value || '#4f72df'
    const selectedRouteExportWidth = () => Math.max(55, Math.min(180, Number(shareRouteExportWidth?.value) || 100))
    const routeExportWidthScale = canvasWidth => (selectedRouteExportWidth() / 100) * (Math.max(1, Number(canvasWidth) || 640) / 640)
    const currentRouteExportSets = () => {
        const exportingSegmentsTogether = getShareContentMode() === 'segments' && getShareSegmentMode() === 'together'
        return exportingSegmentsTogether
            ? getSelectedShareSegmentIndexes().map(index => routeCoordinatesForSharing(shareDataForSegment(index))).filter(coordinates => coordinates.length >= 2)
            : [routeCoordinatesForSharing(currentShareRouteData())].filter(coordinates => coordinates.length >= 2)
    }
    const drawRouteExportSurface = (canvas, routeSets = shareRouteExportSets) => {
        if (!canvas || !routeSets.length) return false
        const context = canvas.getContext('2d', {alpha: true})
        context.clearRect(0, 0, canvas.width, canvas.height)
        const allCoordinates = routeSets.flat()
        const pad = Math.round(Math.min(canvas.width, canvas.height) * .09)
        const viewport = {x: pad, y: pad, width: canvas.width - pad * 2, height: canvas.height - pad * 2}
        const sharedVisual = createShareGeometryVisual({geometry: {type: 'LineString', coordinates: allCoordinates}, viewport})
        if (!sharedVisual) return false
        const color = selectedRouteExportColor()
        const style = {
            color,
            widthScale: routeExportWidthScale(canvas.width),
            endpointOutline: true,
        }
        const bounds = shareGeometryBounds(allCoordinates)
        routeSets.forEach(coordinates => {
            const visual = createShareGeometryVisual({
                geometry: {type: 'LineString', coordinates},
                bounds,
                style,
                viewport,
            })
            if (visual) drawRoute(context, visual.points, true, false, style)
        })
        return true
    }
    const syncRouteExportPreview = () => {
        if (shareRouteExportWidthValue) shareRouteExportWidthValue.textContent = `${selectedRouteExportWidth()}%`
        const previewBox = shareRouteExportPreview?.closest('.activity-share-route-export-preview')
        previewBox?.classList.toggle('is-light', selectedRouteExportColor().toLowerCase() === '#111827')
        drawRouteExportSurface(shareRouteExportPreview)
    }
    const closeRouteExportSheet = () => {
        if (!shareRouteExportSheet) return
        shareRouteExportSheet.hidden = true
        shareRouteExportSets = []
        shareExportRouteButton?.closest('details')?.querySelector('summary')?.focus?.({preventScroll: true})
    }
    const openRouteExportSheet = () => {
        shareRouteExportSets = currentRouteExportSets()
        if (!shareRouteExportSets.length) {
            if (shareStatus) shareStatus.textContent = tr('activity.share.no_route_export')
            return
        }
        shareExportRouteButton?.closest('details')?.removeAttribute('open')
        if (shareRouteExportWidth) shareRouteExportWidth.value = '100'
        shareRouteExportColors.forEach((input, index) => { input.checked = index === 0 })
        if (shareRouteExportSheet) shareRouteExportSheet.hidden = false
        syncRouteExportPreview()
        shareRouteExportColors[0]?.focus?.({preventScroll: true})
    }
    shareExportRouteButton?.addEventListener('click', openRouteExportSheet)
    shareModal?.querySelectorAll('[data-route-export-close]').forEach(button => button.addEventListener('click', closeRouteExportSheet))
    shareRouteExportColors.forEach(input => input.addEventListener('change', syncRouteExportPreview))
    shareRouteExportWidth?.addEventListener('input', syncRouteExportPreview)
    shareRouteExportConfirm?.addEventListener('click', async () => {
        if (!shareRouteExportSets.length) return
        const exportingSegmentsTogether = getShareContentMode() === 'segments' && getShareSegmentMode() === 'together'
        const canvas = document.createElement('canvas')
        canvas.width = 1600
        canvas.height = 1600
        if (!drawRouteExportSurface(canvas)) return
        const blob = await canvasPngBlob(canvas)
        downloadBlob(blob, exportingSegmentsTogether ? 'rotas-trechos-stridebr.png' : 'rota-stridebr.png')
        const routeCount = shareRouteExportSets.length
        closeRouteExportSheet()
        if (shareStatus) shareStatus.textContent = routeCount > 1 ? tr('activity.share.routes_exported') : tr('activity.share.route_exported')
    })
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && shareRouteExportSheet && !shareRouteExportSheet.hidden) {
            event.preventDefault()
            closeRouteExportSheet()
        }
    })
    if (sharePreviewStage) {
        let swipePointer = null
        let swipeStartX = 0
        let swipeStartY = 0
        sharePreviewStage.addEventListener('pointerdown', event => {
            if (window.innerWidth > 900 || event.button !== 0) return
            swipePointer = event.pointerId
            swipeStartX = event.clientX
            swipeStartY = event.clientY
        })
        sharePreviewStage.addEventListener('pointerup', event => {
            if (swipePointer !== event.pointerId || window.innerWidth > 900) return
            const dx = event.clientX - swipeStartX
            const dy = event.clientY - swipeStartY
            swipePointer = null
            if (Math.abs(dx) < 52 || Math.abs(dx) < Math.abs(dy) * 1.25) return
            const visiblePresetIds = Array.from(shareStyleGrid?.querySelectorAll('[data-share-preset-card]:not([hidden])') || []).map(card => card.dataset.sharePresetValue).filter(Boolean)
            const index = visiblePresetIds.indexOf(activeSharePresetId)
            const direction = dx < 0 ? 1 : -1
            const nextIndex = Math.max(0, Math.min(visiblePresetIds.length - 1, index + direction))
            if (nextIndex !== index && visiblePresetIds[nextIndex]) applySharePreset(visiblePresetIds[nextIndex])
        })
        sharePreviewStage.addEventListener('pointercancel', () => { swipePointer = null })
    }
    const historyRoot = document.querySelector('[data-activity-history]')
    const historyList = historyRoot?.querySelector('[data-activity-list]')
    const historySummary = document.querySelector('[data-history-summary]')
    const summaryActivities = historySummary?.querySelector('[data-summary-activities]')
    const summaryTime = historySummary?.querySelector('[data-summary-time]')
    const summaryDistance = historySummary?.querySelector('[data-summary-distance]')
    const summaryElevation = historySummary?.querySelector('[data-summary-elevation]')
    const summaryPeriod = historySummary?.querySelector('[data-summary-period]')
    const historySkeleton = historyRoot?.querySelector('[data-history-skeleton]')
    const historyEmpty = historyRoot?.querySelector('[data-history-empty]')
    const historyEmptyText = historyRoot?.querySelector('[data-history-empty-text]')
    const historyError = historyRoot?.querySelector('[data-history-error]')
    const historyMore = historyRoot?.querySelector('[data-history-more]')
    const historyLoadMore = historyRoot?.querySelector('[data-history-load-more]')
    const historyCount = historyRoot?.querySelector('[data-history-count]')
    const historySearch = historyRoot?.querySelector('[data-history-search]')
    const historySport = historyRoot?.querySelector('[data-history-sport]')
    const historyStatus = document.querySelector('[data-activity-history-status]')
    const detailDrawer = document.querySelector('[data-activity-detail-drawer]')
    const detailPanel = detailDrawer?.querySelector('[data-activity-detail-panel]')
    const detailContent = detailDrawer?.querySelector('[data-detail-content]')
    const detailLoading = detailDrawer?.querySelector('[data-detail-loading]')
    const detailExpandButton = detailDrawer?.querySelector('[data-expand-activity-detail]')
    const detailCloseButton = detailDrawer?.querySelector('.activity-detail-header-actions [data-close-activity-detail]')
    const detailPlaceholder = document.querySelector('[data-detail-desktop-placeholder]')
    const bulkToggle = historyRoot?.querySelector('[data-bulk-toggle]')
    const bulkNormal = historyRoot?.querySelector('[data-history-toolbar-normal]')
    const bulkToolbar = historyRoot?.querySelector('[data-bulk-toolbar]')
    const bulkDialog = historyRoot?.querySelector('[data-bulk-dialog]')
    const bulkBar = historyRoot?.querySelector('[data-bulk-bar]')
    const bulkCount = historyRoot?.querySelector('[data-bulk-count]')
    const bulkSelectVisible = historyRoot?.querySelector('[data-bulk-select-visible]')
    const bulkEdit = historyRoot?.querySelector('[data-bulk-edit]')
    const bulkDelete = historyRoot?.querySelector('[data-bulk-delete]')
    const bulkCancel = historyRoot?.querySelector('[data-bulk-cancel]')
    const bulkDialogCount = bulkDialog?.querySelector('.activity-bulk-dialog-head span')
    const bulkDurationMode = bulkBar?.querySelector('[data-bulk-duration-mode]')
    const bulkDurationValue = bulkBar?.querySelector('[data-bulk-duration-value]')
    const bulkDurationInput = bulkDurationValue?.querySelector('input[name="duracao_minutos"]')
    let historyCursor = null
    let historyTotal = 0
    let historyRequest = null
    let historyDebounce = null
    let detailRequest = null
    let detailOpenSequence = 0
    let detailSkeletonTimer = 0
    let activeDetailId = ''
    let detailExpanded = false
    let historyReturnScrollY = 0
    const detailCache = new Map()
    const detailPrefetches = new Map()
    let bulkMode = false
    const bulkSelected = new Set()
    const bulkHasChanges = () => {
        if (!bulkBar) return false
        const sport = String(bulkBar.elements.idmodalidade?.value || '')
        const durationMode = String(bulkBar.elements.duracao_modo?.value || 'keep')
        const visibility = String(bulkBar.elements.visibilidade?.value || '')
        return sport !== '' || durationMode !== 'keep' || visibility !== ''
    }

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char]))
    const usesDesktopActivityPanel = () => window.matchMedia('(min-width: 901px)').matches
    let detailViewportFrame = 0
    const syncDesktopDetailViewport = () => {
        detailViewportFrame = 0
        if (!detailDrawer || detailDrawer.hidden || !usesDesktopActivityPanel()) {
            detailDrawer?.style.removeProperty('--activity-detail-available-height')
            return
        }
        const stickyTop = 54
        const top = Math.max(stickyTop, detailDrawer.getBoundingClientRect().top)
        const available = Math.max(320, window.innerHeight - top - 12)
        detailDrawer.style.setProperty('--activity-detail-available-height', `${Math.round(available)}px`)
    }
    const scheduleDesktopDetailViewportSync = () => {
        if (detailViewportFrame) return
        detailViewportFrame = window.requestAnimationFrame(syncDesktopDetailViewport)
    }
    window.addEventListener('scroll', scheduleDesktopDetailViewportSync, {passive: true})
    window.addEventListener('resize', scheduleDesktopDetailViewportSync)
    const updateHistorySummary = (summary) => {
        if (!historySummary || !summary) return
        if (summaryActivities) summaryActivities.textContent = String(summary.atividades ?? 0)
        if (summaryTime) summaryTime.textContent = String(summary.tempo || '0min')
        if (summaryDistance) summaryDistance.textContent = String(summary.distancia || '0 m')
        if (summaryElevation) summaryElevation.textContent = String(summary.elevacao || '0 m')
        if (summaryPeriod) summaryPeriod.textContent = String(summary.periodo || tr('activity.summary.last_7_days'))
    }
    const syncActiveDetailRow = () => {
        historyList?.querySelectorAll('[data-history-row]').forEach(row => row.classList.toggle('is-active-detail', String(row.dataset.activityId || '') === activeDetailId))
    }

    const activityMetricKind = (label) => {
        const value = String(label || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
        if (value.includes('codigo')) return 'code'
        if (value.includes('foco')) return 'focus'
        if (value.includes('duracao') || value.includes('tempo')) return 'duration'
        if (value.includes('exercicio')) return 'exercises'
        if (value.includes('serie') || value.includes('set')) return 'sets'
        if (value.includes('esforco') || value.includes('sensacao')) return 'effort'
        return 'metric'
    }
    const renderMetricas = (metrics) => {
        if (!Array.isArray(metrics) || !metrics.length) return ''
        return `<div class="activity-row-metrics">${metrics.map((metric) => `<span data-metric-kind="${activityMetricKind(metric.rotulo)}"><small>${escapeHtml(metric.rotulo)}</small><strong>${escapeHtml(metric.valor)}</strong></span>`).join('')}</div>`
    }

    const renderStrengthPreview = (item) => {
        const strength = item?.strength && typeof item.strength === 'object' ? item.strength : {}
        const facts = []
        const duration = (item.metricas || []).find(metric => activityMetricKind(metric?.rotulo || '') === 'duration')
        if (duration?.valor) facts.push(String(duration.valor))
        const exercises = Number(strength.exercises || 0)
        const sets = Number(strength.sets || 0)
        if (exercises > 0) facts.push(trn('activity.history.exercise_count.one', 'activity.history.exercise_count.other', exercises))
        if (sets > 0) facts.push(trn('activity.history.set_count.one', 'activity.history.set_count.other', sets))
        const code = String(strength.code || '').trim()
        const focus = String(strength.focus || '').trim()
        if (!code && !focus && !facts.length) return ''
        return `<div class="activity-row-strength-preview"><div class="activity-row-strength-facts">${code ? `<span class="activity-row-strength-code">${escapeHtml(code)}</span>` : ''}${facts.length ? `<span>${escapeHtml(facts.join(' · '))}</span>` : ''}</div>${focus ? `<span class="activity-row-strength-focus" title="${escapeHtml(focus)}">${escapeHtml(focus)}</span>` : ''}</div>`
    }

    const renderHistoryRow = (item) => {
        const article = document.createElement('article')
        article.className = 'activity-list-row'
        article.dataset.historyRow = ''
        article.dataset.activityId = item.id
        article.dataset.activityTitle = item.titulo || item.modalidade || tr('common.activity')
        article.dataset.activitySport = item.modalidade_slug || ''
        article.classList.toggle('is-strength-row', ['musculacao', 'calistenia', 'crossfit'].includes(String(item.modalidade_slug || '')))
        article.innerHTML = `
            <label class="activity-row-select" aria-label="Selecionar ${escapeHtml(item.titulo)}"><input type="checkbox" value="${escapeHtml(item.id)}" data-bulk-row-select><span aria-hidden="true"></span></label>
            <button type="button" class="activity-row-main" data-open-activity-detail="${escapeHtml(item.id)}">
                <div class="activity-row-date"><strong>${escapeHtml(item.dia)}</strong><span>${escapeHtml(item.mes)}</span></div>
                <div class="activity-row-icon">${item.icone_html || ''}</div>
                <div class="activity-row-title">
                    <strong>${escapeHtml(item.titulo)}</strong>
                    <span>${escapeHtml(item.modalidade)} · ${escapeHtml(item.hora)}</span>
                </div>
                ${article.classList.contains('is-strength-row') ? renderStrengthPreview(item) : renderMetricas(item.metricas)}
                <span class="activity-row-open" aria-hidden="true">›</span>
            </button>`
        article.classList.toggle('is-bulk-mode', bulkMode)
        article.classList.toggle('is-active-detail', String(item.id) === activeDetailId)
        const checkbox = article.querySelector('[data-bulk-row-select]')
        if (checkbox) checkbox.checked = bulkSelected.has(String(item.id))
        article.classList.toggle('is-selected', bulkSelected.has(String(item.id)))
        return article
    }

    const HISTORY_CACHE_PREFIX = 'stridebr.activity.history.v3:'
    const PENDING_IMPORT_REFRESH_KEY = 'stridebr.activity.import.pendingRefresh.v1'
    const historyCacheKey = () => `${HISTORY_CACHE_PREFIX}${String(historySearch?.value || '').trim()}|${String(historySport?.value || '').trim()}`
    const clearHistoryCache = () => {
        try {
            for (let index = sessionStorage.length - 1; index >= 0; index -= 1) {
                const key = sessionStorage.key(index)
                if (key?.startsWith(HISTORY_CACHE_PREFIX)) sessionStorage.removeItem(key)
            }
        } catch (_) {}
    }
    const saveHistoryCache = data => {
        try {
            sessionStorage.setItem(historyCacheKey(), JSON.stringify({savedAt: Date.now(), items: data.items || [], next_cursor: data.next_cursor || null, resumo: data.resumo || null}))
        } catch (_) {}
    }
    const restoreHistoryCache = () => {
        if (!historyRoot || !historyList) return false
        try {
            const cached = JSON.parse(sessionStorage.getItem(historyCacheKey()) || 'null')
            if (!cached || !Array.isArray(cached.items) || Date.now() - Number(cached.savedAt || 0) > 180000) return false
            historyList.replaceChildren()
            const fragment = document.createDocumentFragment()
            cached.items.forEach(item => fragment.appendChild(renderHistoryRow(item)))
            historyList.appendChild(fragment)
            historyCursor = cached.next_cursor || null
            historyTotal = cached.items.length
            updateHistorySummary(cached.resumo)
            if (!historyTotal) return false
            setHistoryState('ready')
            if (historyCount) historyCount.textContent = trn('activity.history.loaded.one', 'activity.history.loaded.other', historyTotal)
            if (historyMore) historyMore.hidden = !historyCursor
            return true
        } catch (_) {
            return false
        }
    }

    const updateBulkUi = () => {
        if (!historyRoot) return
        historyRoot.classList.toggle('is-bulk-mode', bulkMode)
        if (bulkNormal) bulkNormal.hidden = bulkMode
        if (bulkToolbar) bulkToolbar.hidden = !bulkMode
        if (bulkToggle) bulkToggle.setAttribute('aria-pressed', bulkMode ? 'true' : 'false')
        historyList?.querySelectorAll('[data-history-row]').forEach(row => {
            const id = String(row.dataset.activityId || '')
            const checked = bulkSelected.has(id)
            row.classList.toggle('is-bulk-mode', bulkMode)
            row.classList.toggle('is-selected', checked)
            const input = row.querySelector('[data-bulk-row-select]')
            if (input) input.checked = checked
        })
        const selectedLabel = trn('activity.bulk_selected.one', 'activity.bulk_selected.other', bulkSelected.size)
        if (bulkCount) bulkCount.textContent = selectedLabel
        if (bulkDialogCount) bulkDialogCount.textContent = selectedLabel
        const loadedRows = Array.from(historyList?.querySelectorAll('[data-history-row]') || [])
        const allLoadedSelected = loadedRows.length > 0 && loadedRows.every(row => bulkSelected.has(String(row.dataset.activityId || '')))
        if (bulkSelectVisible) {
            bulkSelectVisible.textContent = allLoadedSelected
                ? tr('activity.bulk_clear_loaded')
                : tr('activity.bulk_select_loaded_count', {count: i18n.number?.(loadedRows.length, 0) ?? String(loadedRows.length)})
            bulkSelectVisible.disabled = loadedRows.length === 0
        }
        const submit = bulkBar?.querySelector('button[type="submit"]')
        if (submit) submit.disabled = bulkSelected.size === 0 || !bulkHasChanges()
        if (bulkEdit) bulkEdit.disabled = bulkSelected.size === 0
        if (bulkDelete) bulkDelete.disabled = bulkSelected.size === 0
    }
    const closeBulkDialog = () => { if (bulkDialog?.open) bulkDialog.close() }
    const leaveBulkMode = () => { closeBulkDialog(); bulkMode = false; bulkSelected.clear(); updateBulkUi() }
    const openBulkDialog = () => {
        if (!bulkDialog || bulkSelected.size === 0) return
        if (typeof bulkDialog.showModal === 'function') bulkDialog.showModal()
        else bulkDialog.setAttribute('open', 'open')
        requestAnimationFrame(() => bulkDialog.querySelector('select, input, button')?.focus({preventScroll: true}))
    }
    const setRowSelected = (row, selected) => {
        const id = String(row?.dataset.activityId || '')
        if (!id) return
        if (selected) bulkSelected.add(id); else bulkSelected.delete(id)
        updateBulkUi()
    }
    const runBulkRequest = async (action = 'update') => {
        if (!bulkBar || !bulkSelected.size || (action === 'update' && !bulkHasChanges())) return
        if (action === 'delete') {
            const message = trn('activity.history.delete_confirm.one', 'activity.history.delete_confirm.other', bulkSelected.size)
            const confirmed = window.StrideBRUI?.confirm ? await window.StrideBRUI.confirm(message, {title: tr('activity.delete_many_title'), confirmLabel: tr('activity.delete_label'), danger: true}) : window.confirm(message)
            if (!confirmed) return
        }
        const selectedIds = Array.from(bulkSelected)
        const submit = bulkBar.querySelector('button[type="submit"]')
        if (submit) submit.disabled = true
        if (bulkDelete) bulkDelete.disabled = true
        try {
            const body = new FormData(bulkBar)
            body.set('action', action)
            body.set('_idempotency_key', requestKey())
            selectedIds.forEach(id => body.append('ids[]', id))
            const response = await fetchWithDeadline('/api/atividades-lote.php', {method: 'POST', body, credentials: 'same-origin', headers: {'Accept': 'application/json'}}, 15000)
            const data = await response.json().catch(() => null)
            if (!response.ok || !data?.ok) throw new Error(data?.error || tr('activity.history.update_error'))
            const total = Number(data.result?.total || bulkSelected.size)
            if (action === 'delete' && window.StrideBRUI?.undo) {
                window.StrideBRUI.undo(trn('activity.history.deleted.one', 'activity.history.deleted.other', total), () => restoreActivities(selectedIds), 9000)
            } else {
                window.StrideBRUI?.notify?.(action === 'delete' ? trn('activity.history.deleted.one', 'activity.history.deleted.other', total) : trn('activity.history.updated.one', 'activity.history.updated.other', total), 'success')
            }
            leaveBulkMode()
            clearHistoryCache()
            if (bulkBar.elements.idmodalidade) bulkBar.elements.idmodalidade.value = ''
            if (bulkBar.elements.duracao_modo) bulkBar.elements.duracao_modo.value = 'keep'
            if (bulkBar.elements.duracao_minutos) bulkBar.elements.duracao_minutos.value = ''
            if (bulkDurationValue) bulkDurationValue.hidden = true
            if (bulkBar.elements.visibilidade) bulkBar.elements.visibilidade.value = ''
            await loadHistory()
        } catch (error) {
            window.StrideBRUI?.notify?.(error?.message || tr('activity.history.update_error'), 'error')
        } finally {
            updateBulkUi()
        }
    }

    const setHistoryState = (state) => {
        if (historySkeleton) historySkeleton.hidden = state !== 'loading'
        if (historyError) historyError.hidden = state !== 'error'
        if (historyEmpty) historyEmpty.hidden = state !== 'empty'
        if (historyList) historyList.hidden = state === 'loading' || state === 'error' || state === 'empty'
        if (historyMore && state !== 'ready') historyMore.hidden = true
    }

    const loadHistory = async ({append = false, background = false, preserveDetail = false} = {}) => {
        if (!historyRoot || !historyList) return
        historyRequest?.abort()
        historyRequest = new AbortController()
        const hasVisibleRows = historyList.childElementCount > 0 && !historyList.hidden
        const preserveVisibleRows = !append && hasVisibleRows
        if (!append) {
            if (!background && !preserveVisibleRows) setHistoryState('loading')
            historyRoot.setAttribute('aria-busy', 'true')
            historyRoot.classList.toggle('is-refreshing', preserveVisibleRows)
            if (historyStatus) historyStatus.textContent = preserveVisibleRows ? tr('activity.history.refreshing') : tr('common.loading')
            historyCursor = background ? historyCursor : null
            historyTotal = background ? historyTotal : 0
            if (bulkSelected.size && !background) bulkSelected.clear()
            updateBulkUi()
        } else {
            historyLoadMore?.setAttribute('disabled', 'disabled')
            historyLoadMore?.classList.add('is-loading')
        }
        const params = new URLSearchParams({limit: '20'})
        const query = String(historySearch?.value || '').trim()
        const sport = String(historySport?.value || '').trim()
        if (query) params.set('q', query)
        if (sport) params.set('sport', sport)
        if (append && historyCursor) params.set('cursor', historyCursor)
        try {
            const response = await fetchWithRetry(`/api/atividades-historico.php?${params.toString()}`, {
                headers: {'Accept': 'application/json'},
                signal: historyRequest.signal,
                credentials: 'same-origin'
            }, {timeout: 7500, retries: 0})
            const data = await response.json().catch(() => null)
            if (!response.ok || !data?.ok) throw new Error(data?.error || tr('activity.load_history_error'))
            if (!append) {
                saveHistoryCache(data)
                updateHistorySummary(data.resumo)
                historyList.replaceChildren()
                if (!preserveDetail) {
                    activeDetailId = ''
                    closeActivityDetails()
                }
                syncActiveDetailRow()
            }
            const fragment = document.createDocumentFragment()
            ;(data.items || []).forEach((item) => fragment.appendChild(renderHistoryRow(item)))
            historyList.appendChild(fragment)
            historyCursor = data.next_cursor || null
            historyTotal = append ? historyTotal + (data.items || []).length : (data.items || []).length
            syncActiveDetailRow()
            if (historyTotal === 0) {
                if (historyEmptyText) historyEmptyText.textContent = query || sport ? tr('activity.history.no_filter_match') : tr('activity.first_help')
                setHistoryState('empty')
            } else {
                setHistoryState('ready')
                if (historyCount) historyCount.textContent = trn('activity.history.loaded.one', 'activity.history.loaded.other', historyTotal)
                if (historyMore) historyMore.hidden = !historyCursor
            }
            const highlightId = new URLSearchParams(window.location.search).get('highlight')
            if (highlightId) {
                const highlighted = historyList.querySelector(`[data-activity-id="${CSS.escape(highlightId)}"]`)
                highlighted?.classList.add('is-highlighted')
                highlighted?.scrollIntoView({behavior: 'smooth', block: 'center'})
            }
        } catch (error) {
            if (error?.name === 'AbortError') return
            if (!append && historyList.childElementCount) {
                setHistoryState('ready')
                showActivityToast(error?.message || tr('activity.history.update_error'), 'error')
            } else if (!append) {
                setHistoryState('error')
            }
        } finally {
            historyRoot.removeAttribute('aria-busy')
            historyRoot.classList.remove('is-refreshing')
            if (historyStatus) historyStatus.textContent = ''
            historyLoadMore?.removeAttribute('disabled')
            historyLoadMore?.classList.remove('is-loading')
        }
    }

    let importedHistoryRefreshTimer = 0
    const pendingImportedActivityIds = new Set()
    const highlightImportedActivities = ids => {
        ids.forEach(id => {
            const row = historyList?.querySelector(`[data-activity-id="${CSS.escape(id)}"]`)
            if (row) row.classList.add('is-highlighted')
        })
    }
    const refreshHistoryAfterImport = async ({notify = true} = {}) => {
        importedHistoryRefreshTimer = 0
        const ids = Array.from(pendingImportedActivityIds)
        pendingImportedActivityIds.clear()
        clearHistoryCache()
        await loadHistory({background: true, preserveDetail: true})
        highlightImportedActivities(ids)
        if (notify && ids.length) {
            const label = trn('activity.imported.one', 'activity.imported.other', ids.length)
            showActivityToast(label)
        }
    }
    const consumePendingImportRefresh = ({notify = true, force = false} = {}) => {
        let ids = []
        try {
            const stored = JSON.parse(sessionStorage.getItem(PENDING_IMPORT_REFRESH_KEY) || 'null')
            ids = Array.isArray(stored?.ids) ? stored.ids.map(String).filter(Boolean) : []
            if (ids.length) sessionStorage.removeItem(PENDING_IMPORT_REFRESH_KEY)
        } catch (_) {}
        ids.forEach(id => pendingImportedActivityIds.add(id))
        if (!ids.length && !force) return false
        clearHistoryCache()
        window.clearTimeout(importedHistoryRefreshTimer)
        importedHistoryRefreshTimer = window.setTimeout(() => refreshHistoryAfterImport({notify}), 80)
        return true
    }
    window.addEventListener('stridebr:activity-imported', event => {
        const ids = Array.isArray(event?.detail?.ids) ? event.detail.ids : []
        ids.forEach(id => { if (id) pendingImportedActivityIds.add(String(id)) })
        try { sessionStorage.removeItem(PENDING_IMPORT_REFRESH_KEY) } catch (_) {}
        clearHistoryCache()
        window.clearTimeout(importedHistoryRefreshTimer)
        importedHistoryRefreshTimer = window.setTimeout(() => refreshHistoryAfterImport({notify: true}), 80)
    })
    window.addEventListener('pageshow', event => {
        if (consumePendingImportRefresh({notify: true})) return
        if (event.persisted) consumePendingImportRefresh({notify: false, force: true})
    })

    let lastPassiveHistoryCheckAt = Date.now()
    let passiveHistoryRefreshRunning = false
    const visibleHistoryIds = () => new Set(Array.from(historyList?.querySelectorAll('[data-history-row]') || []).map(row => String(row.dataset.activityId || '')).filter(Boolean))
    const passiveHistoryRefresh = async () => {
        if (!historyRoot || !historyList || passiveHistoryRefreshRunning || bulkMode || document.visibilityState !== 'visible') return
        if (document.documentElement.classList.contains('activity-editor-open') || document.documentElement.classList.contains('activity-edit-open')) return
        const now = Date.now()
        if (now - lastPassiveHistoryCheckAt < 60000) return
        lastPassiveHistoryCheckAt = now
        passiveHistoryRefreshRunning = true
        const before = visibleHistoryIds()
        const scrollY = window.scrollY
        try {
            clearHistoryCache()
            await loadHistory({background: true, preserveDetail: true})
            const added = Array.from(visibleHistoryIds()).filter(id => !before.has(id))
            requestAnimationFrame(() => window.scrollTo(0, scrollY))
            if (added.length) showActivityToast(trn('activity.new_imports_available.one', 'activity.new_imports_available.other', added.length))
        } finally {
            passiveHistoryRefreshRunning = false
        }
    }
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState !== 'visible') return
        if (!consumePendingImportRefresh({notify: true})) passiveHistoryRefresh()
    })
    window.addEventListener('focus', passiveHistoryRefresh)

    const detailSection = (title, body, extraClass = '') => body ? `<div class="activity-detail-section${extraClass ? ` ${extraClass}` : ''}"><h3>${escapeHtml(title)}</h3>${body}</div>` : ''
    const cleanDetailValues = (values) => (Array.isArray(values) ? values : []).filter((item) => String(item?.rotulo || '').trim() !== '' && String(item?.valor || '').trim() !== '')
    const detailPairKey = (item) => `${String(item?.rotulo || '').trim().toLocaleLowerCase('pt-BR')}::${String(item?.valor || '').trim().toLocaleLowerCase('pt-BR')}`
    const normalizedDetailLabel = (value) => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toLowerCase()
    const detailStats = (values, extraClass = '') => {
        const clean = cleanDetailValues(values)
        if (!clean.length) return ''
        const classes = ['activity-detail-stats']
        if (extraClass) classes.push(extraClass)
        if (clean.length === 1) classes.push('is-single')
        if (clean.length % 2 === 1 && clean.length > 1) classes.push('has-odd-last')
        return `<div class="${classes.join(' ')}">${clean.map((item) => `<div data-detail-kind="${activityMetricKind(item.rotulo)}"><span>${escapeHtml(item.rotulo)}</span><strong>${escapeHtml(item.valor)}</strong></div>`).join('')}</div>`
    }
    const findDetailValue = (values, terms) => {
        const item = cleanDetailValues(values).find((entry) => {
            const label = normalizedDetailLabel(entry.rotulo)
            return terms.some(term => label.includes(term))
        })
        return item ? String(item.valor || '').trim() : ''
    }
    const buildStrengthDetail = (activity) => {
        const values = [...cleanDetailValues(activity.metricas || []), ...cleanDetailValues(activity.dados || [])]
        let code = String(activity.treino?.codigo || findDetailValue(values, ['codigo']) || '').trim()
        let focus = String(activity.treino?.foco || findDetailValue(values, ['foco']) || '').trim()
        if ((!code || !focus)) {
            const match = String(activity.titulo || '').trim().match(/^(?:academia|treino)\s+([a-z0-9]+)\s*[-–—]\s*(.+)$/i)
            if (match) {
                if (!code) code = String(match[1] || '').toUpperCase()
                if (!focus) focus = String(match[2] || '').trim()
            }
        }
        const notes = String(activity.observacoes || '')
        const exerciseMatch = notes.match(/(\d+)\s*\/\s*(\d+)\s*exerc[ií]cios?/i)
        const setMatch = notes.match(/(\d+)\s*\/\s*(\d+)\s*s[eé]ries?/i)
        const feelingMatch = notes.match(/sensa[cç][aã]o\s*:\s*(\d+)\s*\/\s*(\d+)/i)
        const duration = findDetailValue(values, ['duracao', 'tempo'])
        const exercises = exerciseMatch ? `${exerciseMatch[1]}/${exerciseMatch[2]}` : (Array.isArray(activity.unidades) && activity.unidades.length ? String(activity.unidades.length) : '')
        const sets = setMatch ? `${setMatch[1]}/${setMatch[2]}` : findDetailValue(values, ['series', 'sets'])
        const effort = activity.esforco ? `${activity.esforco}/10` : (feelingMatch ? `${feelingMatch[1]}/${feelingMatch[2]}` : '')
        const top = code || focus ? `<div class="activity-strength-identity">${code ? `<div class="activity-strength-code"><span>${escapeHtml(tr('activity.strength.code'))}</span><strong>${escapeHtml(code)}</strong></div>` : ''}${focus ? `<div class="activity-strength-focus"><span>${escapeHtml(tr('activity.strength.focus'))}</span><strong>${escapeHtml(focus)}</strong></div>` : ''}</div>` : ''
        const strengthData = activity?.forca || null
        const stats = [
            duration ? {rotulo: tr('activity.share.duration'), valor: duration} : null,
            strengthData?.total_exercicios ? {rotulo: tr('activity.strength.exercises'), valor: String(strengthData.total_exercicios)} : (exercises ? {rotulo: tr('activity.strength.exercises'), valor: exercises} : null),
            strengthData?.total_series ? {rotulo: tr('activity.strength.sets'), valor: String(strengthData.total_series)} : (sets ? {rotulo: tr('activity.strength.sets'), valor: sets} : null),
            Number(strengthData?.volume_kg) > 0 ? {rotulo: 'Volume', valor: `${Number(strengthData.volume_kg).toLocaleString('pt-BR', {maximumFractionDigits: 0})} kg`} : null,
            effort ? {rotulo: activity.esforco ? tr('activity.strength.effort') : tr('activity.strength.feeling'), valor: effort} : null,
        ].filter(Boolean)
        const exerciseRows = Array.isArray(strengthData?.exercicios) ? strengthData.exercicios.map(exercise => {
            const setRows = (exercise.series || []).map(set => {
                const load = set.carga_kg !== null && set.carga_kg !== undefined ? `${Number(set.carga_kg).toLocaleString('pt-BR', {maximumFractionDigits: 2})} kg` : '—'
                const reps = set.repeticoes !== null && set.repeticoes !== undefined ? String(set.repeticoes) : '—'
                const rir = set.rir !== null && set.rir !== undefined ? String(set.rir) : '—'
                return `<div class="activity-strength-detail-set${set.concluida ? '' : ' is-incomplete'}"><span>${escapeHtml(set.numero)}</span><strong>${escapeHtml(load)}</strong><strong>${escapeHtml(reps)}</strong><span>${escapeHtml(rir)}</span></div>`
            }).join('')
            const best = exercise.melhor_carga_kg !== null && exercise.melhor_carga_kg !== undefined ? `${Number(exercise.melhor_carga_kg).toLocaleString('pt-BR', {maximumFractionDigits: 2})} kg` : ''
            return `<article class="activity-strength-detail-exercise"><header><strong>${escapeHtml(exercise.nome || tr('activity.strength.exercise'))}</strong>${best ? `<small>${escapeHtml(tr('activity.strength.best_load_label', {value: best}))}</small>` : ''}</header><div class="activity-strength-detail-set activity-strength-detail-set-head"><span>${escapeHtml(tr('activity.strength.set'))}</span><span>${escapeHtml(tr('common.load'))}</span><span>Reps</span><span>RIR</span></div>${setRows}</article>`
        }).join('') : ''
        return {
            html: `${(top || stats.length) ? `<div class="activity-strength-summary">${top}${detailStats(stats, 'activity-strength-compact-stats')}</div>` : ''}${exerciseRows ? `<div class="activity-strength-detail-exercises">${exerciseRows}</div>` : ''}`,
            labels: new Set(['codigo', 'codigo do treino', 'foco', 'foco muscular', 'duracao', 'tempo', 'exercicios', 'series', 'sets', 'volume', 'esforco', 'sensacao'])
        }
    }

    const unitRoutePreviewHtml = (unit) => {
        const coords = Array.isArray(unit?.rota?.geojson?.coordinates) ? unit.rota.geojson.coordinates : []
        const clean = coords.filter(point => Array.isArray(point) && point.length >= 2 && Number.isFinite(Number(point[0])) && Number.isFinite(Number(point[1])))
        if (clean.length < 2) return ''
        const sampled = clean.length > 140 ? clean.filter((_, index) => index === 0 || index === clean.length - 1 || index % Math.ceil(clean.length / 140) === 0) : clean
        const xs = sampled.map(point => Number(point[0]))
        const ys = sampled.map(point => Number(point[1]))
        const minX = Math.min(...xs), maxX = Math.max(...xs), minY = Math.min(...ys), maxY = Math.max(...ys)
        const spanX = Math.max(maxX - minX, .000001), spanY = Math.max(maxY - minY, .000001)
        const padding = 12
        const width = 260, height = 104
        const points = sampled.map(point => {
            const x = padding + ((Number(point[0]) - minX) / spanX) * (width - padding * 2)
            const y = height - padding - ((Number(point[1]) - minY) / spanY) * (height - padding * 2)
            return `${x.toFixed(1)},${y.toFixed(1)}`
        }).join(' ')
        const distance = Number(unit?.rota?.distancia_m || 0)
        const distanceText = distance > 0 ? (distance >= 1000 ? `${(distance / 1000).toLocaleString('pt-BR', {maximumFractionDigits: 2})} km` : `${Math.round(distance)} m`) : ''
        return `<div class="activity-unit-route-preview"><div><strong>${escapeHtml(tr('activity.unit.route'))}</strong>${distanceText ? `<small>${escapeHtml(distanceText)}</small>` : ''}</div><svg viewBox="0 0 ${width} ${height}" preserveAspectRatio="xMidYMid meet" role="img" aria-label="${escapeHtml(tr('activity.unit.route_aria'))}"><polyline points="${points}" /></svg></div>`
    }

    const gpsWebDetailHtml = (activity) => {
        const gps = activity?.gps_web
        if (!gps) return ''
        const avg = Number(gps.precisao_media_m)
        const accepted = Number(gps.pontos_aceitos || 0)
        const rejected = Number(gps.pontos_rejeitados || 0)
        const gaps = Number(gps.lacunas_visibilidade || 0)
        const quality = Number.isFinite(avg)
            ? (avg <= 15 ? tr('activity.gps.quality_good') : avg <= 30 ? tr('activity.gps.quality_fair') : tr('activity.gps.quality_low'))
            : tr('activity.gps.quality_unknown')
        const precision = Number.isFinite(avg) ? tr('activity.gps.precision', {value: Math.round(avg), quality}) : tr('activity.gps.precision_unknown')
        const adjusted = gps.usuario_ajustou ? ` ${tr('activity.gps.adjusted')}` : ''
        const interruptions = gaps > 0 ? ` ${trn('activity.gps.interruptions.one', 'activity.gps.interruptions.other', gaps)}` : ''
        const filtered = rejected > 0 ? ` ${trn('activity.gps.filtered.one', 'activity.gps.filtered.other', rejected, {rejected, accepted})}` : ''
        return `<div class="activity-gps-web-notice" role="note"><div><strong>GPS Web</strong><span>${escapeHtml(tr('activity.gps.browser_estimate'))}</span></div><p>${escapeHtml(precision + interruptions + filtered + adjusted)} ${escapeHtml(tr('activity.gps.review_if_needed'))}</p></div>`
    }

    const renderDetail = (activity) => {
        if (!detailContent || !detailDrawer) return
        const title = detailDrawer.querySelector('[data-detail-title]')
        const sport = detailDrawer.querySelector('[data-detail-sport]')
        const date = detailDrawer.querySelector('[data-detail-date]')
        const visibility = detailDrawer.querySelector('[data-detail-visibility]')
        const compareLink = detailDrawer.querySelector('[data-detail-compare]')
        const strength = ['musculacao', 'calistenia', 'crossfit'].includes(String(activity.modalidade_slug || ''))
        detailPanel?.classList.toggle('is-strength-detail', strength)
        if (title) title.textContent = activity.titulo
        if (sport) sport.textContent = activity.modalidade
        if (date) date.textContent = `${activity.data} · ${activity.hora}`
        if (compareLink) compareLink.href = `/user/comparar-atividades.php?a=${encodeURIComponent(activity.id)}`
        if (visibility) {
            const labels = {privado: tr('activity.only_me'), amigos: tr('common.friends'), publico: tr('common.public')}
            visibility.textContent = labels[String(activity.visibilidade || '')] || ''
            visibility.hidden = visibility.textContent === ''
        }
        const metricValues = cleanDetailValues(activity.metricas || [])
        const metricPairs = new Set(metricValues.map(detailPairKey))
        const strengthDetail = strength ? buildStrengthDetail(activity) : {html: '', labels: new Set()}
        const withoutSummaryDuplicates = (values) => cleanDetailValues(values).filter((item) => {
            if (metricPairs.has(detailPairKey(item))) return false
            const label = normalizedDetailLabel(item.rotulo)
            if (strength && Array.from(strengthDetail.labels).some(term => label.includes(term))) return false
            return true
        })
        const metrics = strength ? strengthDetail.html : detailStats(metricValues)
        const effort = !strength && activity.esforco ? `<div class="activity-detail-line"><span>${escapeHtml(tr('activity.effort_perceived'))}</span><strong>${escapeHtml(activity.esforco)}/10</strong></div>` : ''
        const energy = (() => {
            const item = activity?.energia
            const kcal = Number(item?.kcal)
            if (!Number.isFinite(kcal) || kcal <= 0) return ''
            const value = `${item?.estimated ? '~' : ''}${i18n.number?.(Math.round(kcal), 0) ?? String(Math.round(kcal))} kcal`
            const source = String(item?.source || '').trim()
            const confidence = String(item?.confidence || '').trim()
            const meta = item?.estimated
                ? [tr('activity.energy_estimate'), confidence ? tr('activity.confidence', {value: confidence}) : '', Number(item?.weight_kg) > 0 ? tr('activity.reference_weight', {value: i18n.number?.(Number(item.weight_kg), 2, true) ?? String(item.weight_kg)}) : ''].filter(Boolean).join(' · ')
                : (source ? tr('activity.energy_reported_by', {source}) : tr('activity.energy_reported_source'))
            return detailSection(tr('activity.energy'), `<div class="activity-detail-energy"><strong>${escapeHtml(value)}</strong><small>${escapeHtml(meta)}</small></div>`)
        })()
        const equipment = Array.isArray(activity.equipamentos) && activity.equipamentos.length
            ? detailSection(tr('common.equipment'), `<div class="detail-chips">${activity.equipamentos.map((item) => `<span>${escapeHtml(item.nome)}</span>`).join('')}</div>`)
            : ''
        const workoutTitle = String(activity.treino?.titulo || '').trim()
        const workout = strength && workoutTitle && workoutTitle !== String(activity.titulo || '').trim()
            ? detailSection(tr('activity.linked_workout'), `<p class="activity-linked-workout-name">${escapeHtml(workoutTitle)}</p>`)
            : (!strength && activity.treino ? detailSection(tr('activity.linked_workout'), detailStats([
                activity.treino.codigo ? {rotulo: tr('common.code'), valor: activity.treino.codigo} : null,
                activity.treino.foco ? {rotulo: tr('common.focus'), valor: activity.treino.foco} : null,
                workoutTitle ? {rotulo: tr('activity.workout_fallback'), valor: workoutTitle} : null,
            ].filter(Boolean))) : '')
        const dataSection = detailSection(tr('activity.data_section'), detailStats(withoutSummaryDuplicates(activity.dados || [])))
        const units = (activity.unidades || []).map((unit) => {
            const values = withoutSummaryDuplicates(unit?.valores || [])
            const routePreview = unitRoutePreviewHtml(unit)
            const body = `${values.length ? detailStats(values) : ''}${routePreview}`
            const unitTitle = activity.usa_trechos && unit?.modalidade ? `${unit.rotulo || tr('activity.share.segment')} · ${unit.modalidade}` : unit.rotulo || tr('activity.unit_fallback')
            return body ? detailSection(unitTitle, body, strength ? 'activity-strength-unit' : 'activity-detail-unit') : ''
        }).join('')
        const notes = activity.observacoes ? detailSection(tr('activity.notes'), `<p>${escapeHtml(activity.observacoes).replace(/\n/g, '<br>')}</p>`) : ''
        const activityShareData = activityDetailToShareData(activity)
        let route = ''
        if (activity.rota?.geojson?.coordinates?.length >= 2) {
            const routeDistance = Number(activity.rota.distancia_m || 0)
            const routeDistanceText = routeDistance >= 1000
                ? `${i18n.number?.(routeDistance / 1000, 2, true) ?? String(Number((routeDistance / 1000).toFixed(2)))} km`
                : `${Math.round(routeDistance)} m`
            route = `<div class="activity-detail-section activity-detail-route" data-activity-route>
                <script type="application/json" data-route-data>${JSON.stringify(activity.rota).replace(/</g, '\u003c')}</script>
                <h3>${escapeHtml(tr('activity.route_title'))}</h3>
                <div class="activity-detail-route-preview" data-detail-route-preview aria-label="${escapeHtml(tr('activity.route_map_aria'))}">${shareRouteSilhouetteSvg(activity.rota)}</div>
                <div class="activity-detail-route-metrics">
                    <span><small>${escapeHtml(tr('activity.route_distance'))}</small><strong>${escapeHtml(routeDistanceText)}</strong></span>
                    ${activity.rota.ganho_m !== null ? `<span><small>${escapeHtml(tr('activity.route_gain'))}</small><strong>${Math.round(activity.rota.ganho_m)} m</strong></span>` : ''}
                </div>
                ${Array.isArray(activity.rota.perfil) && activity.rota.perfil.length ? `<div class="activity-elevation-profile"><span>${escapeHtml(tr('activity.elevation_profile'))}</span><svg data-elevation-profile role="img" aria-label="${escapeHtml(tr('activity.elevation_profile_aria'))}" viewBox="0 0 640 150" preserveAspectRatio="none"></svg></div>` : ''}
                <small class="activity-route-attribution">${escapeHtml(tr('activity.route_attribution'))}</small>
            </div>`
        }
        const sharePayload = `<script type="application/json" data-activity-share-data>${JSON.stringify(activityShareData).replace(/</g, '\\u003c')}</script>`
        const gpsWebNotice = gpsWebDetailHtml(activity)
        detailContent.innerHTML = `${gpsWebNotice}${metrics}${effort}${energy}${workout}${route}${equipment}${dataSection}${units}${notes}${sharePayload}
            <footer class="activity-detail-actions has-share">
                <button type="button" class="activity-secondary-button" data-share-activity>${escapeHtml(tr('activity.detail_share'))}</button>
                <a class="activity-secondary-button" data-repeat-activity href="/user/atividades.php?repetir=${encodeURIComponent(activity.id)}">${escapeHtml(tr('activity.repeat'))}</a>
                <a class="activity-primary-action" href="/user/editatividade.php?id=${encodeURIComponent(activity.id)}">${escapeHtml(tr('activity.detail_edit'))}</a>
                <button type="button" class="activity-danger-button" data-delete-activity="${escapeHtml(activity.id)}" data-activity-title="${escapeHtml(activity.titulo)}">${escapeHtml(tr('activity.detail_delete'))}</button>
            </footer>`
        detailContent.hidden = false
        if (detailLoading) detailLoading.hidden = true
        const elevationSvg = detailDrawer.querySelector('[data-elevation-profile]')
        if (elevationSvg) drawElevationProfile(elevationSvg, activity.rota?.perfil)
        scheduleDesktopDetailViewportSync()
    }

    const setDetailExpanded = expanded => {
        detailExpanded = Boolean(expanded)
        detailDrawer?.classList.toggle('is-expanded-detail', detailExpanded)
        document.documentElement.classList.toggle('activity-detail-expanded', detailExpanded)
        if (detailExpandButton) {
            detailExpandButton.hidden = detailExpanded
            detailExpandButton.textContent = tr('activity.detail.expand')
        }
        if (detailCloseButton) {
            const label = tr(detailExpanded ? 'activity.detail.collapse' : 'activity.close_details')
            detailCloseButton.setAttribute('aria-label', label)
            detailCloseButton.title = label
        }
        scheduleDesktopDetailViewportSync()
    }
    const closeActivityDetails = () => {
        const restoreScroll = !usesDesktopActivityPanel() ? historyReturnScrollY : null
        detailOpenSequence += 1
        window.clearTimeout(detailSkeletonTimer)
        detailSkeletonTimer = 0
        detailRequest?.abort()
        activeDetailId = ''
        syncActiveDetailRow()
        setDetailExpanded(false)
        if (detailDrawer) detailDrawer.hidden = true
        if (detailLoading) detailLoading.hidden = true
        if (detailPlaceholder) detailPlaceholder.hidden = false
        document.documentElement.classList.remove('activity-detail-open')
        if (restoreScroll !== null) window.requestAnimationFrame(() => window.scrollTo({top: restoreScroll, behavior: 'auto'}))
    }
    detailExpandButton?.addEventListener('click', () => setDetailExpanded(!detailExpanded))

    const activityContextMenu = document.querySelector('[data-activity-context-menu]')
    const activityContextEdit = activityContextMenu?.querySelector('[data-context-edit]')
    let contextActivityId = ''
    let contextActivityTitle = ''

    const showActivityToast = (message, type = 'success') => {
        if (window.StrideBRUI?.notify) {
            window.StrideBRUI.notify(message, type)
            return
        }
        const status = document.querySelector('[data-activity-history-status]')
        if (status) status.textContent = message
    }

    const requestKey = () => {
        if (window.crypto?.getRandomValues) {
            const bytes = new Uint8Array(16)
            window.crypto.getRandomValues(bytes)
            return Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('')
        }
        return `${Date.now().toString(16)}${Math.random().toString(16).slice(2)}`.slice(0, 32).padEnd(32, '0')
    }

    const restoreActivities = async ids => {
        const token = page?.dataset.csrfToken || form?.querySelector('[name="csrf_token"]')?.value || ''
        const body = new URLSearchParams({csrf_token: token, _idempotency_key: requestKey()})
        ids.forEach(id => body.append('ids[]', id))
        const response = await fetchWithDeadline('/api/atividades-restaurar.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'Accept': 'application/json'},
            credentials: 'same-origin',
            body: body.toString()
        }, 12000)
        const data = await response.json().catch(() => null)
        if (!response.ok || !data?.ok) throw new Error(data?.error || tr('activity.restore_error'))
        clearHistoryCache()
        if (historyRoot) await loadHistory()
        showActivityToast(trn('activity.restore_many.one', 'activity.restore_many.other', Number(data.restored || 0)))
    }

    const closeActivityContextMenu = () => {
        if (activityContextMenu) activityContextMenu.hidden = true
        contextActivityId = ''
        contextActivityTitle = ''
    }

    const openActivityContextMenu = (row, x, y) => {
        if (!activityContextMenu || !row?.dataset.activityId) return
        contextActivityId = row.dataset.activityId
        contextActivityTitle = row.dataset.activityTitle || tr('common.activity')
        if (activityContextEdit) activityContextEdit.href = `/user/editatividade.php?id=${encodeURIComponent(contextActivityId)}`
        activityContextMenu.hidden = false
        activityContextMenu.style.left = '0px'
        activityContextMenu.style.top = '0px'
        const rect = activityContextMenu.getBoundingClientRect()
        const margin = 8
        activityContextMenu.style.left = `${Math.max(margin, Math.min(x, window.innerWidth - rect.width - margin))}px`
        activityContextMenu.style.top = `${Math.max(margin, Math.min(y, window.innerHeight - rect.height - margin))}px`
    }

    const deleteActivity = async (id, title = tr('common.activity'), options = {}) => {
        const key = String(id || '')
        if (!key) return false
        const message = tr('activity.delete_confirm_named_primary', {title})
        const confirmed = options.confirmed === true ? true : (window.StrideBRUI?.confirm
            ? await window.StrideBRUI.confirm(message, {title: tr('activity.delete_title'), confirmLabel: tr('activity.delete_label'), danger: true, messageBlocks: [message, tr('activity.delete_confirm_named_secondary')]})
            : window.confirm(message))
        if (!confirmed) return false

        const row = historyList?.querySelector(`[data-history-row][data-activity-id="${CSS.escape(key)}"]`) || null
        const rowParent = row?.parentNode || null
        const rowNext = row?.nextSibling || null
        const detailWasOpen = activeDetailId === key && detailDrawer && !detailDrawer.hidden
        const previousSummaryActivities = summaryActivities?.textContent || ''
        const previousHistoryTotal = historyTotal
        const parsedSummaryCount = Number(String(previousSummaryActivities).replace(/[^0-9-]/g, ''))

        row?.classList.add('is-optimistic-removed')
        row?.remove()
        if (detailWasOpen) closeActivityDetails()
        closeActivityContextMenu()
        historyTotal = Math.max(0, historyTotal - (row ? 1 : 0))
        if (Number.isFinite(parsedSummaryCount) && summaryActivities) summaryActivities.textContent = String(Math.max(0, parsedSummaryCount - 1))
        if (historyCount) historyCount.textContent = trn('activity.history.loaded.one', 'activity.history.loaded.other', historyTotal)
        historySummary?.classList.add('is-optimistic-pending')
        if (historyList && historyList.childElementCount === 0 && !historyCursor) {
            if (historyEmptyText) historyEmptyText.textContent = tr('activity.first_help')
            setHistoryState('empty')
        }

        let undoRequested = false
        const rollbackOptimisticDelete = async () => {
            if (row && rowParent && !row.isConnected) {
                row.classList.remove('is-optimistic-removed')
                const anchor = rowNext && rowNext.parentNode === rowParent ? rowNext : null
                rowParent.insertBefore(row, anchor)
            }
            historyTotal = previousHistoryTotal
            if (summaryActivities) summaryActivities.textContent = previousSummaryActivities
            if (historyCount) historyCount.textContent = trn('activity.history.loaded.one', 'activity.history.loaded.other', historyTotal)
            historySummary?.classList.remove('is-optimistic-pending')
            if (historyTotal > 0) setHistoryState('ready')
            syncActiveDetailRow()
            if (detailWasOpen) await openActivityDetail(key)
        }

        const token = page?.dataset.csrfToken || form?.querySelector('[name="csrf_token"]')?.value || ''
        const body = new URLSearchParams({id: key, csrf_token: token, _idempotency_key: requestKey()})
        const backendDelete = (async () => {
            const response = await fetchWithDeadline('/function/apagaratividade.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'Accept': 'application/json'},
                credentials: 'same-origin',
                body: body.toString()
            }, 12000)
            const data = await response.json().catch(() => null)
            if (!response.ok || !data?.ok) throw new Error(data?.message || tr('activity.delete_error'))
            detailCache.delete(key)
            detailPrefetches.delete(key)
            clearHistoryCache()
            return true
        })()

        if (window.StrideBRUI?.undo) {
            window.StrideBRUI.undo(tr('activity.deleted'), async () => {
                undoRequested = true
                await backendDelete
                await restoreActivities([key])
                historySummary?.classList.remove('is-optimistic-pending')
                if (detailWasOpen) await openActivityDetail(key)
            }, 9000)
        }

        try {
            await backendDelete
            historySummary?.classList.remove('is-optimistic-pending')
            if (!window.StrideBRUI?.undo) showActivityToast(tr('activity.deleted'))
            if (!undoRequested && historyRoot) await loadHistory({background: true, preserveDetail: true})
            return true
        } catch (error) {
            await rollbackOptimisticDelete()
            showActivityToast(error?.message || tr('activity.delete_error'), 'error')
            return false
        }
    }

    const postSaveMetricItems = activity => {
        const values = Array.isArray(activity?.metricas_compartilhamento) ? [...activity.metricas_compartilhamento] : (Array.isArray(activity?.metricas) ? [...activity.metricas] : [])
        const strength = activity?.forca
        if (strength) {
            values.unshift(
                {rotulo: tr('activity.strength.exercises'), valor: String(strength.total_exercicios || 0)},
                {rotulo: tr('activity.strength.sets'), valor: String(strength.total_series || 0)}
            )
            if (Number(strength.volume_kg) > 0) values.push({rotulo: 'Volume', valor: `${Number(strength.volume_kg).toLocaleString('pt-BR', {maximumFractionDigits: 0})} kg`})
        }
        const seen = new Set()
        return values.filter(item => {
            if (!item || String(item.valor ?? '').trim() === '') return false
            const key = normalizedDetailLabel(item.rotulo)
            if (seen.has(key)) return false
            seen.add(key)
            return true
        }).slice(0, 4)
    }
    const closePostSaveShare = () => {
        if (postSaveModal) postSaveModal.hidden = true
        document.documentElement.classList.remove('activity-post-save-open')
        const url = new URL(window.location.href)
        if (url.searchParams.has('saved')) {
            url.searchParams.delete('saved')
            history.replaceState(history.state, '', `${url.pathname}${url.search}${url.hash}`)
        }
    }
    const drawPostSaveShare = async activity => {
        if (!postSaveCanvas) return false
        const data = activityDetailToShareData(activity)
        const preference = loadLastSharePreference(data)
        const format = getShareFormat(preference.format)
        const allMetrics = availableShareMetrics(data)
        let selectedMetrics = preference.metricKeys.map(key => allMetrics.find(metric => String(metric.key || '') === key)).filter(Boolean).slice(0, 4)
        if (!selectedMetrics.length) selectedMetrics = allMetrics.filter(metric => metric.defaultSelected !== false).slice(0, 4)
        if (!selectedMetrics.length) selectedMetrics = allMetrics.slice(0, 4)
        postSaveCanvas.width = format.width
        postSaveCanvas.height = format.height
        postSaveCanvas.style.aspectRatio = `${format.width} / ${format.height}`
        const context = postSaveCanvas.getContext('2d', {alpha: true})
        const preset = getSharePreset(preference.preset)
        const config = readShareConfiguration({
            data,
            preset,
            format: format.id,
            width: format.width,
            height: format.height,
            color: preference.color,
            content: preference.content,
            headingMode: preference.headingMode,
            showRoute: preference.showRoute,
            showDate: preference.showDate,
            showLogo: preference.showLogo,
            routeScale: preference.routeScale,
            selectedMetrics,
            caption: ''
        })
        const token = ++shareRenderToken
        const rendered = await drawShareCardSurface(context, config, token)
        if (!rendered) return false
        postSaveShareData = data
        postSaveSharePreference = preference
        return true
    }
    const openPostSaveShare = async id => {
        if (!postSaveModal || !id) return
        postSaveActivityId = String(id)
        postSaveShareData = null
        postSaveSharePreference = null
        if (postSaveStatus) postSaveStatus.textContent = ''
        try {
            const activity = await fetchActivityDetail(postSaveActivityId)
            await drawPostSaveShare(activity)
            if (postSaveTitle) postSaveTitle.textContent = activity?.titulo || activity?.modalidade || tr('common.activity')
            if (postSaveSummary) {
                const summary = postSaveMetricItems(activity).slice(0, 3).map(item => String(item.valor || '')).filter(Boolean).join(' · ')
                postSaveSummary.textContent = summary
                postSaveSummary.hidden = summary === ''
            }
            postSaveModal.hidden = false
            document.documentElement.classList.add('activity-post-save-open')
            postSaveNative?.focus()
        } catch (error) {
            if (postSaveStatus) postSaveStatus.textContent = error?.message || tr('activity.preview_prepare_error')
            postSaveModal.hidden = false
            document.documentElement.classList.add('activity-post-save-open')
        }
    }

    const fetchActivityDetail = async (id, signal = undefined) => {
        const response = await fetchWithRetry(`/api/atividade-detalhe.php?id=${encodeURIComponent(id)}`, {
            headers: {'Accept': 'application/json'},
            signal,
            credentials: 'same-origin'
        }, {timeout: 8000, retries: 0})
        const data = await response.json().catch(() => null)
        if (!response.ok || !data?.ok) throw new Error(data?.error || tr('activity.load_error'))
        detailCache.set(String(id), data.atividade)
        return data.atividade
    }
    const prefetchActivityDetail = id => {
        const key = String(id || '')
        if (!key || detailCache.has(key) || detailPrefetches.has(key)) return
        const request = fetchActivityDetail(key).catch(() => null).finally(() => detailPrefetches.delete(key))
        detailPrefetches.set(key, request)
    }

    const openActivityDetail = async (id) => {
        if (!detailDrawer || !detailContent) return
        const key = String(id || '')
        if (!key) return
        const sequence = ++detailOpenSequence
        window.clearTimeout(detailSkeletonTimer)
        detailSkeletonTimer = 0
        detailRequest?.abort()
        detailRequest = new AbortController()
        if (!usesDesktopActivityPanel() && detailDrawer.hidden) historyReturnScrollY = window.scrollY
        activeDetailId = key
        if (!detailExpanded) setDetailExpanded(false)
        syncActiveDetailRow()
        detailDrawer.hidden = false
        if (detailPlaceholder) detailPlaceholder.hidden = true
        scheduleDesktopDetailViewportSync()
        document.documentElement.classList.toggle('activity-detail-open', !usesDesktopActivityPanel())

        const cached = detailCache.get(key) || null
        if (cached) {
            if (detailLoading) detailLoading.hidden = true
            renderDetail(cached)
            return
        }

        const title = detailDrawer.querySelector('[data-detail-title]')
        const sport = detailDrawer.querySelector('[data-detail-sport]')
        const date = detailDrawer.querySelector('[data-detail-date]')
        const visibility = detailDrawer.querySelector('[data-detail-visibility]')
        const sourceRow = historyList?.querySelector(`[data-history-row][data-activity-id="${CSS.escape(key)}"]`)
        detailContent.hidden = true
        detailContent.replaceChildren()
        if (detailLoading) detailLoading.hidden = true
        if (title) title.textContent = sourceRow?.dataset.activityTitle || tr('activity.loading')
        if (sport) sport.textContent = tr('common.activity')
        if (date) date.textContent = ''
        if (visibility) { visibility.textContent = ''; visibility.hidden = true }
        detailSkeletonTimer = window.setTimeout(() => {
            if (sequence !== detailOpenSequence || activeDetailId !== key) return
            if (detailLoading) detailLoading.hidden = false
        }, 140)

        try {
            let activity = null
            if (detailPrefetches.has(key)) activity = await detailPrefetches.get(key)
            if (!activity) activity = await fetchActivityDetail(key, detailRequest.signal)
            if (sequence !== detailOpenSequence || activeDetailId !== key) return
            if (!activity) throw new Error(tr('activity.load_error'))
            window.clearTimeout(detailSkeletonTimer)
            detailSkeletonTimer = 0
            renderDetail(activity)
        } catch (error) {
            if (error?.name === 'AbortError' || sequence !== detailOpenSequence || activeDetailId !== key) return
            window.clearTimeout(detailSkeletonTimer)
            detailSkeletonTimer = 0
            if (detailLoading) detailLoading.hidden = true
            detailContent.hidden = false
            detailContent.innerHTML = `<div class="activity-detail-section"><h3>${escapeHtml(tr('activity.load_short_error'))}</h3><p>${escapeHtml(error?.message || tr('common.try_again'))}</p></div>`
        }
    }

    postSaveModal?.querySelectorAll('[data-post-save-close]').forEach(button => button.addEventListener('click', closePostSaveShare))
    postSaveNative?.addEventListener('click', async () => {
        if (!postSaveCanvas) return
        const blob = await new Promise(resolve => postSaveCanvas.toBlob(resolve, 'image/png'))
        if (!blob) return
        const file = new File([blob], `atividade-stridebr-${postSaveActivityId || 'story'}.png`, {type: 'image/png'})
        if (navigator.share && navigator.canShare?.({files: [file]})) {
            try {
                await navigator.share({files: [file], title: tr('activity.post_share_title')})
                if (postSaveShareData && postSaveSharePreference) storeSharePreference(postSaveShareData, postSaveSharePreference)
                if (postSaveStatus) postSaveStatus.textContent = tr('activity.share_opened')
            } catch (_) {}
            return
        }
        const link = document.createElement('a')
        link.href = URL.createObjectURL(blob)
        link.download = file.name
        document.body.append(link)
        link.click()
        link.remove()
        window.setTimeout(() => URL.revokeObjectURL(link.href), 1200)
        if (postSaveShareData && postSaveSharePreference) storeSharePreference(postSaveShareData, postSaveSharePreference)
        if (postSaveStatus) postSaveStatus.textContent = tr('activity.downloaded_image')
    })
    postSaveEdit?.addEventListener('click', async () => {
        const id = postSaveActivityId
        closePostSaveShare()
        if (!id) return
        await openActivityDetail(id)
        const shareButton = detailContent?.querySelector('[data-share-activity]')
        if (!shareButton) return
        shareButton.click()
        closeActivityDetails()
    })
    postSaveDelete?.addEventListener('click', async () => {
        const id = postSaveActivityId
        if (!id) return
        const title = String(postSaveTitle?.textContent || tr('common.activity'))
        const confirmed = window.StrideBRUI?.confirm
            ? await window.StrideBRUI.confirm(tr('activity.post_delete_confirm', {title}), {title: tr('activity.post_delete_title'), confirmLabel: tr('activity.delete_label'), danger: true})
            : window.confirm(tr('activity.post_delete_confirm_short', {title}))
        if (!confirmed) return
        postSaveDelete.disabled = true
        const removed = await deleteActivity(id, title, {confirmed: true})
        postSaveDelete.disabled = false
        if (!removed) return
        closePostSaveShare()
        postSaveActivityId = ''
    })

    document.addEventListener('click', async (event) => {
        if (bulkMode) {
            const bulkRow = event.target.closest('[data-history-row]')
            if (bulkRow && !event.target.closest('[data-bulk-row-select]')) {
                event.preventDefault()
                setRowSelected(bulkRow, !bulkSelected.has(String(bulkRow.dataset.activityId || '')))
                return
            }
        }
        const deleteButton = event.target.closest('[data-delete-activity]')
        if (deleteButton) {
            deleteActivity(deleteButton.dataset.deleteActivity || '', deleteButton.dataset.activityTitle || tr('common.activity'))
            return
        }
        const contextDelete = event.target.closest('[data-context-delete]')
        if (contextDelete && contextActivityId) {
            deleteActivity(contextActivityId, contextActivityTitle)
            return
        }
        const contextOpen = event.target.closest('[data-context-open]')
        if (contextOpen && contextActivityId) {
            const id = contextActivityId
            closeActivityContextMenu()
            openActivityDetail(id)
            return
        }
        const confirmDelete = event.target.closest('[data-confirm-delete-submit]')
        if (confirmDelete) {
            event.preventDefault()
            const message = tr('activity.delete_confirm')
            const confirmed = window.StrideBRUI?.confirm
                ? await window.StrideBRUI.confirm(message, {title: tr('activity.delete_title'), confirmLabel: tr('activity.delete_label'), danger: true})
                : window.confirm(message)
            if (confirmed) confirmDelete.form?.requestSubmit(confirmDelete)
            return
        }
        const open = event.target.closest('[data-open-activity-detail]')
        if (open) {
            closeActivityContextMenu()
            openActivityDetail(open.dataset.openActivityDetail || '')
            return
        }
        if (event.target.closest('[data-close-activity-detail]')) {
            if (detailExpanded && usesDesktopActivityPanel()) setDetailExpanded(false)
            else closeActivityDetails()
        }
        if (activityContextMenu && !event.target.closest('[data-activity-context-menu]')) closeActivityContextMenu()
    })
    bulkToggle?.addEventListener('click', () => { bulkMode = !bulkMode; if (!bulkMode) bulkSelected.clear(); updateBulkUi() })
    bulkCancel?.addEventListener('click', leaveBulkMode)
    bulkEdit?.addEventListener('click', openBulkDialog)
    historyRoot?.querySelectorAll('[data-bulk-dialog-close]').forEach(button => button.addEventListener('click', closeBulkDialog))
    bulkDialog?.addEventListener('click', event => { if (event.target === bulkDialog) closeBulkDialog() })
    bulkSelectVisible?.addEventListener('click', () => {
        const rows = Array.from(historyList?.querySelectorAll('[data-history-row]') || [])
        const allSelected = rows.length > 0 && rows.every(row => bulkSelected.has(String(row.dataset.activityId || '')))
        rows.forEach(row => {
            const id = String(row.dataset.activityId || '')
            if (!id) return
            if (allSelected) bulkSelected.delete(id); else bulkSelected.add(id)
        })
        updateBulkUi()
    })
    const syncBulkDuration = () => {
        if (!bulkDurationMode || !bulkDurationValue) return
        const setMode = bulkDurationMode.value === 'set'
        bulkDurationValue.hidden = !setMode
        if (bulkDurationInput) {
            bulkDurationInput.disabled = !setMode
            bulkDurationInput.required = setMode
        }
    }
    bulkBar?.addEventListener('change', updateBulkUi)
    bulkBar?.addEventListener('input', updateBulkUi)
    bulkDurationMode?.addEventListener('change', syncBulkDuration)
    bulkDurationInput?.addEventListener('input', () => {
        if (bulkDurationMode && bulkDurationInput.value !== '') { bulkDurationMode.value = 'set'; syncBulkDuration() }
    })
    syncBulkDuration()
    bulkBar?.addEventListener('submit', event => { event.preventDefault(); runBulkRequest('update') })
    bulkDelete?.addEventListener('click', () => runBulkRequest('delete'))
    historyList?.addEventListener('change', event => {
        const input = event.target.closest('[data-bulk-row-select]')
        if (!input) return
        setRowSelected(input.closest('[data-history-row]'), input.checked)
    })

    historyList?.addEventListener('contextmenu', (event) => {
        if (bulkMode) return
        const row = event.target.closest('[data-history-row]')
        if (!row) return
        event.preventDefault()
        openActivityContextMenu(row, event.clientX, event.clientY)
    })
    window.addEventListener('blur', closeActivityContextMenu)
    window.addEventListener('resize', closeActivityContextMenu)
    window.addEventListener('scroll', closeActivityContextMenu, true)
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePostSaveShare()
            closeShareModal()
            closeActivityDetails()
            closeActivityContextMenu()
            closeSportPopover()
        }
    })
    historyLoadMore?.addEventListener('click', () => loadHistory({append: true}))
    historyRoot?.querySelector('[data-history-retry]')?.addEventListener('click', () => loadHistory())
    historySearch?.addEventListener('input', () => {
        window.clearTimeout(historyDebounce)
        historyDebounce = window.setTimeout(() => loadHistory(), 220)
    })
    historySport?.addEventListener('change', () => loadHistory())
    const initialHistoryState = String(historyRoot?.dataset.initialState || '')
    if (initialHistoryState === 'ready' || initialHistoryState === 'empty') {
        historyCursor = String(historyRoot?.dataset.initialCursor || '') || null
        historyTotal = Math.max(0, Number.parseInt(String(historyRoot?.dataset.initialTotal || '0'), 10) || 0)
        setHistoryState(initialHistoryState)
        if (initialHistoryState === 'ready') {
            if (historyCount) historyCount.textContent = trn('activity.history.loaded.one', 'activity.history.loaded.other', historyTotal)
            if (historyMore) historyMore.hidden = !historyCursor
        }
        const highlightId = new URLSearchParams(window.location.search).get('highlight')
        if (highlightId) {
            const highlighted = historyList?.querySelector(`[data-activity-id="${CSS.escape(highlightId)}"]`)
            highlighted?.classList.add('is-highlighted')
            highlighted?.scrollIntoView({behavior: 'smooth', block: 'center'})
        }
    } else {
        const historyRestored = restoreHistoryCache()
        loadHistory({background: historyRestored})
    }
    if (shell?.classList.contains('is-open')) loadEditorDetails(modelSelect?.value || '')

    syncEffortUI()
    syncActivityTitlePresentation()
    syncLogDetailButtons()

    if (modelSelect) {
        updateModelsForModality()
    } else {
        document.querySelectorAll('[data-model-panel]:not([hidden])').forEach((panel) => {
            updateDerivedMetric(panel)
            updateOptionalChips(panel)
            updateSummary(panel)
        })
    }
    updateSportCurrent()
    const initialSavedActivityId = new URLSearchParams(window.location.search).get('saved')
    if (initialSavedActivityId) {
        clearHistoryCache()
        loadHistory({background: true, preserveDetail: true}).then(() => highlightImportedActivities([String(initialSavedActivityId)]))
        openPostSaveShare(initialSavedActivityId)
    } else {
        consumePendingImportRefresh({notify: true})
    }
    const initialToolUrl = new URL(window.location.href)
    const initialTool = initialToolUrl.searchParams.get('tool') || ''
    if (initialTool && activityTools[initialTool]) {
        const target = initialTool === 'compare'
            ? `/user/comparar-atividades.php?${new URLSearchParams([...initialToolUrl.searchParams].filter(([key]) => key === 'a' || key === 'b')).toString()}`
            : '/user/importar-exportar.php'
        openActivityTool(initialTool, target, {historyMode: 'none'})
    }
})
