(() => {
    const root = document.querySelector('[data-onboarding]')
    if (!root) return

    const steps = [...root.querySelectorAll('[data-step]')]
    const next = root.querySelector('[data-next-step]')
    const prev = root.querySelector('[data-prev-step]')
    const finish = root.querySelector('[data-finish-step]')
    const label = root.querySelector('[data-step-label]')
    const stepKind = root.querySelector('[data-step-kind]')
    const bar = root.querySelector('[data-progress-bar]')
    const summary = root.querySelector('[data-onboarding-summary]')
    const summaryBlock = root.querySelector('[data-summary-block]')
    const skipToAccount = root.querySelector('[data-skip-to-account]')
    const sportSearch = root.querySelector('[data-signup-sport-search]')
    const sportCards = [...root.querySelectorAll('[data-signup-sport-card]')]
    const sportGroups = [...root.querySelectorAll('[data-signup-sport-group]')]
    const sportEmpty = root.querySelector('[data-signup-sport-empty]')
    const sportFamilies = root.querySelector('[data-signup-sport-families]')
    const sportFamilyPanels = [...root.querySelectorAll('[data-signup-sport-family-panel]')]
    let activeSportFamily = ''
    let index = Math.max(0, Math.min(steps.length - 1, Number(root.dataset.initialStep || 0) || 0))

    const escapeHtml = value => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;')

    const selectedLabels = selector => [...root.querySelectorAll(selector)]
        .filter(input => input.checked)
        .map(input => input.dataset.summaryLabel || input.closest('label')?.querySelector('span')?.textContent?.trim())
        .filter(Boolean)

    const buildSummary = () => {
        if (!summary) return
        const sports = selectedLabels('input[name="sports[]"]')
        const goals = selectedLabels('input[name="goals[]"]')
        const tracking = selectedLabels('input[name="tracking[]"]')
        const experienceSelect = root.querySelector('select[name="experience"]')
        const experience = experienceSelect?.value ? experienceSelect.selectedOptions?.[0]?.textContent?.trim() || '' : ''
        const frequencyValue = Number(root.querySelector('select[name="weekly_frequency"]')?.value || 0)
        const lines = []

        if (sports.length) lines.push(['Esportes', sports.join(' · ')])
        if (goals.length) lines.push(['Objetivos', goals.join(' · ')])
        if (frequencyValue > 0 || experience) {
            const routine = []
            if (frequencyValue > 0) routine.push(`${frequencyValue} dia${frequencyValue === 1 ? '' : 's'} por semana`)
            if (experience) routine.push(experience)
            lines.push(['Rotina', routine.join(' · ')])
        }
        if (tracking.length) lines.push(['Progresso', tracking.join(' · ')])

        summary.innerHTML = lines.map(([name, value]) => `<div><span>${escapeHtml(name)}</span><strong>${escapeHtml(value)}</strong></div>`).join('')
        const empty = lines.length === 0
        summary.hidden = empty
        if (summaryBlock) summaryBlock.hidden = empty
    }

    const validateCurrentStep = () => {
        const current = steps[index]
        if (!current) return true
        for (const field of current.querySelectorAll('input, select, textarea')) {
            if (!field.checkValidity()) {
                field.reportValidity()
                field.focus()
                return false
            }
        }
        return true
    }

    const render = () => {
        steps.forEach((step, i) => {
            step.hidden = i !== index
            step.classList.toggle('is-active', i === index)
        })
        if (label) label.textContent = `${index + 1} de ${steps.length}`
        if (stepKind) stepKind.textContent = index === steps.length - 1 ? 'Conta' : 'Etapa opcional'
        if (bar) bar.style.width = `${((index + 1) / steps.length) * 100}%`
        if (prev) {
            prev.hidden = false
            prev.disabled = index === 0
            prev.classList.toggle('is-invisible', index === 0)
        }
        if (next) next.hidden = index === steps.length - 1
        if (finish) finish.hidden = index !== steps.length - 1
        if (skipToAccount) skipToAccount.hidden = index === steps.length - 1
        if (index === steps.length - 1) buildSummary()
        if (steps[index]) steps[index].scrollTop = 0
    }

    const renderSportFamilies = () => {
        const query = String(sportSearch?.value || '').trim().toLocaleLowerCase('pt-BR')
        const searching = query !== ''
        if (sportFamilies) sportFamilies.hidden = searching || activeSportFamily !== ''
        sportFamilyPanels.forEach(panel => {
            if (searching) {
                panel.hidden = !panel.querySelector('[data-signup-sport-card]:not([hidden])')
                const more = panel.querySelector('[data-signup-sport-more-list]')
                if (more) more.hidden = false
            } else {
                panel.hidden = panel.dataset.signupSportFamilyPanel !== activeSportFamily
                const more = panel.querySelector('[data-signup-sport-more-list]')
                const toggle = panel.querySelector('[data-signup-sport-more]')
                if (more) more.hidden = true
                if (toggle) toggle.setAttribute('aria-expanded', 'false')
            }
        })
    }

    const filterSports = () => {
        if (!sportSearch || !sportCards.length) return
        const query = sportSearch.value.trim().toLocaleLowerCase('pt-BR')
        let visibleCards = 0
        sportCards.forEach(card => {
            const match = !query || String(card.dataset.sportName || '').includes(query)
            card.hidden = !match
            if (match) visibleCards++
        })
        renderSportFamilies()
        if (sportEmpty) sportEmpty.hidden = visibleCards !== 0
    }

    next?.addEventListener('click', () => {
        if (!validateCurrentStep()) return
        if (index < steps.length - 1) {
            index++
            render()
        }
    })

    prev?.addEventListener('click', () => {
        if (index > 0) {
            index--
            render()
        }
    })

    skipToAccount?.addEventListener('click', () => {
        index = steps.length - 1
        render()
        root.querySelector('[data-step]:not([hidden]) input, [data-step]:not([hidden]) select')?.focus()
    })

    root.addEventListener('change', () => {
        if (index === steps.length - 1) buildSummary()
    })

    sportSearch?.addEventListener('input', filterSports)

    root.addEventListener('click', event => {
        const family = event.target.closest('[data-signup-sport-family-open]')
        if (family) {
            activeSportFamily = family.dataset.signupSportFamilyOpen || ''
            renderSportFamilies()
            return
        }
        if (event.target.closest('[data-signup-sport-family-back]')) {
            activeSportFamily = ''
            renderSportFamilies()
            return
        }
        const more = event.target.closest('[data-signup-sport-more]')
        if (more) {
            const panel = more.closest('[data-signup-sport-family-panel]')
            const list = panel?.querySelector('[data-signup-sport-more-list]')
            if (!list) return
            const opening = list.hidden
            list.hidden = !opening
            more.setAttribute('aria-expanded', opening ? 'true' : 'false')
        }
    })

    renderSportFamilies()
    render()
})()
