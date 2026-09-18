(() => {
    const selector = 'input[data-exercise-name], input[data-exercise-entry], .draft-exercise-name input[data-field="nome"], input[name="exercise_name[]"]'
    const t = (key, params = {}) => window.StrideBRI18n?.t?.(key, params) || key
    let sequence = 0
    const bind = input => {
        if (input.dataset.exerciseEntryBound) return
        input.dataset.exerciseEntryBound = '1'
        const panel = document.createElement('div')
        panel.className = 'exercise-entry-options'
        panel.id = `exercise-entry-${++sequence}`
        panel.hidden = true
        panel.setAttribute('role', 'group')
        panel.setAttribute('aria-label', t('exercise.entry.suggestions'))
        input.after(panel)
        input.setAttribute('aria-controls', panel.id)
        input.setAttribute('aria-expanded', 'false')
        input.autocomplete = 'off'
        let timer, controller, latest = null, latestValue = '', kept = '', version = 0
        const hide = () => { panel.hidden = true; input.setAttribute('aria-expanded', 'false') }
        const librarySelect = () => input.closest('tr, [data-exercise-row], [data-draft-exercise-row], .draft-exercise-card')?.querySelector('[data-exercise-id], [data-library-select], [data-field="idexercicio"]')
        const use = item => {
            input.value = item.nome
            const select = librarySelect()
            if (select) select.value = item.idexercicio
            latest = null
            hide()
            input.dispatchEvent(new Event('change', {bubbles: true}))
            input.focus()
        }
        const button = (label, callback) => {
            const node = document.createElement('button')
            node.type = 'button'; node.textContent = label
            node.addEventListener('click', callback)
            panel.append(node)
        }
        const render = data => {
            panel.replaceChildren()
            if (data.resolution.status === 'suggest') {
                const copy = document.createElement('span'); copy.textContent = t('exercise.entry.did_you_mean'); panel.append(copy)
            }
            data.options.forEach(item => button(data.resolution.status === 'suggest' ? t('exercise.entry.use', {name: item.nome}) : item.nome, () => use(item)))
            if (data.resolution.status === 'suggest') button(t('exercise.entry.keep', {name: input.value}), () => { kept = input.value; latest = null; hide(); input.focus() })
            panel.hidden = !panel.childElementCount || kept === input.value
            input.setAttribute('aria-expanded', String(!panel.hidden))
        }
        input.addEventListener('input', () => {
            clearTimeout(timer); controller?.abort(); latest = null; kept = ''; hide()
            const select = librarySelect(); if (select) select.value = ''
            const name = input.value.trim(), requestVersion = ++version
            if (name.length < 2) return
            timer = setTimeout(async () => {
                controller = new AbortController()
                try {
                    const response = await fetch(`/api/exercicio-resolver.php?name=${encodeURIComponent(name)}`, {credentials: 'same-origin', signal: controller.signal})
                    const data = await response.json()
                    if (!response.ok || !data.ok || requestVersion !== version || input.value.trim() !== name) return
                    latest = data
                    if (data.resolution.status === 'matched' && data.resolution.match) {
                        const item = data.resolution.match
                        input.value = item.nome
                        const select = librarySelect()
                        if (select) select.value = item.idexercicio
                        input.dispatchEvent(new Event('change', {bubbles: true}))
                    }
                    latestValue = input.value
                    render(data)
                    if (document.activeElement !== input && !panel.contains(document.activeElement)) hide()
                } catch (error) { if (error.name !== 'AbortError') hide() }
            }, 220)
        })
        input.addEventListener('blur', () => {
            if (latestValue === input.value && latest?.resolution.status === 'matched' && latest.resolution.match && kept !== input.value) {
                const item = latest.resolution.match
                input.value = item.nome
                const select = librarySelect(); if (select) select.value = item.idexercicio
                input.dispatchEvent(new Event('change', {bubbles: true}))
            }
            setTimeout(() => { if (!panel.contains(document.activeElement)) hide() }, 0)
        })
        input.addEventListener('focus', () => {
            if (latestValue !== input.value) { latest = null; controller?.abort(); clearTimeout(timer); ++version; hide() }
            else if (latest) render(latest)
        })
        const keydown = event => {
            if (event.key === 'Escape') { input.focus(); hide(); event.stopPropagation(); return }
            if (panel.hidden || !['ArrowDown', 'ArrowUp'].includes(event.key)) return
            event.preventDefault()
            const buttons = [...panel.querySelectorAll('button')], index = buttons.indexOf(document.activeElement)
            buttons[index < 0 ? (event.key === 'ArrowDown' ? 0 : buttons.length - 1) : (index + (event.key === 'ArrowDown' ? 1 : -1) + buttons.length) % buttons.length]?.focus()
        }
        input.addEventListener('keydown', keydown); panel.addEventListener('keydown', keydown)
        panel.addEventListener('focusout', event => { if (event.relatedTarget !== input && !panel.contains(event.relatedTarget)) hide() })
    }
    const scan = root => { if (root.matches?.(selector)) bind(root); root.querySelectorAll?.(selector).forEach(bind) }
    const start = () => {
        scan(document)
        new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(scan))).observe(document.body, {childList: true, subtree: true})
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start()
})()
