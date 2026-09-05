(() => {
    const root = document.querySelector('[data-onboarding]')
    if (!root) return
    const fallbackLocale = String(document.documentElement.dataset.locale || document.documentElement.lang || 'pt-BR').toLowerCase().startsWith('en') ? 'en' : 'pt-BR'
    const onboardingFallbacks = fallbackLocale === 'en' ? {
        'common.continue': 'Continue',
        'onboarding.skip_step': 'Skip step',
        'onboarding.step_count': 'Step {step} of {total}',
        'onboarding.optional_step': 'Optional step',
        'onboarding.account_step': 'Account',
        'onboarding.summary_sports': 'Sports',
        'onboarding.summary_goals': 'Goals',
        'onboarding.summary_routine': 'Routine',
        'onboarding.summary_progress': 'Progress',
        'onboarding.days_per_week.one': '1 day per week',
        'onboarding.days_per_week.other': '{count} days per week',
    } : {
        'common.continue': 'Continuar',
        'onboarding.skip_step': 'Pular etapa',
        'onboarding.step_count': 'Etapa {step} de {total}',
        'onboarding.optional_step': 'Etapa opcional',
        'onboarding.account_step': 'Conta',
        'onboarding.summary_sports': 'Esportes',
        'onboarding.summary_goals': 'Objetivos',
        'onboarding.summary_routine': 'Rotina',
        'onboarding.summary_progress': 'Progresso',
        'onboarding.days_per_week.one': '1 dia por semana',
        'onboarding.days_per_week.other': '{count} dias por semana',
    }
    const replaceFallback = (text, values = {}) => Object.entries(values || {}).reduce((result, [key, value]) => result.split(`{${key}}`).join(String(value ?? '')), String(text || ''))
    const tr = (key, values = {}, fallback = null) => {
        const humanFallback = fallback ?? onboardingFallbacks[key] ?? ''
        const translated = window.StrideBRI18n?.t?.(key, values, humanFallback || null)
        if (translated && String(translated).trim() !== '' && String(translated).toLowerCase() !== String(key).toLowerCase()) return translated
        return replaceFallback(humanFallback, values)
    }
    const trn = (one, other, count, values = {}) => {
        const key = Number(count) === 1 ? one : other
        return tr(key, {...values, count: values.count ?? count})
    }
    const activeLocale = () => window.StrideBRI18n?.locale || fallbackLocale

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
    const signupFlow = root.classList.contains('signup-onboarding-card')

    const syncPrimaryAction = () => {
        if (!next) return
        const hasSportSelection = !!root.querySelector('input[name="sports[]"]:checked')
        const key = signupFlow && index === 0 && !hasSportSelection ? 'onboarding.skip_step' : 'common.continue'
        next.textContent = tr(key)
    }

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

        if (sports.length) lines.push([tr('onboarding.summary_sports'), sports.join(' · ')])
        if (goals.length) lines.push([tr('onboarding.summary_goals'), goals.join(' · ')])
        if (frequencyValue > 0 || experience) {
            const routine = []
            if (frequencyValue > 0) routine.push(trn('onboarding.days_per_week.one', 'onboarding.days_per_week.other', frequencyValue))
            if (experience) routine.push(experience)
            lines.push([tr('onboarding.summary_routine'), routine.join(' · ')])
        }
        if (tracking.length) lines.push([tr('onboarding.summary_progress'), tracking.join(' · ')])

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
        if (label) label.textContent = tr('onboarding.step_count', {step:index + 1, total:steps.length})
        if (stepKind) stepKind.textContent = index === steps.length - 1 ? tr('onboarding.account_step') : tr('onboarding.optional_step')
        if (bar) bar.style.width = `${((index + 1) / steps.length) * 100}%`
        if (prev) {
            prev.hidden = false
            prev.disabled = index === 0
            prev.classList.toggle('is-invisible', index === 0)
        }
        if (next) next.hidden = index === steps.length - 1
        if (finish) finish.hidden = index !== steps.length - 1
        if (skipToAccount) skipToAccount.hidden = index === steps.length - 1
        syncPrimaryAction()
        if (index === steps.length - 1) buildSummary()
        if (steps[index]) steps[index].scrollTop = 0
    }

    const renderSportFamilies = () => {
        const query = String(sportSearch?.value || '').trim().toLocaleLowerCase(activeLocale() === 'en' ? 'en-US' : 'pt-BR')
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
        const query = sportSearch.value.trim().toLocaleLowerCase(activeLocale() === 'en' ? 'en-US' : 'pt-BR')
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
        syncPrimaryAction()
        if (index === steps.length - 1) buildSummary()
    })

    sportSearch?.addEventListener('input', filterSports)
    document.addEventListener('stridebr:i18n-ready', () => { render(); if (index === steps.length - 1) buildSummary() }, {once:true})

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
