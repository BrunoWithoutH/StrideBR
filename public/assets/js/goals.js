document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.goals-page')
    if (!page) return

    const bindEditor = form => {
        if (!form || form.dataset.goalBound === '1') return
        form.dataset.goalBound = '1'
        const metric = form.querySelector('[data-goal-metric]')
        const period = form.querySelector('[data-goal-period]')
        const dates = form.querySelector('[data-goal-custom-dates]')
        const unit = form.querySelector('[data-goal-unit]')
        const target = form.querySelector('input[name="valor_alvo"]')
        const sport = form.querySelector('select[name="idmodalidade"]')
        const exerciseField = form.querySelector('[data-goal-exercise-field]')
        const exercise = form.querySelector('[data-goal-exercise]')
        const endDate = form.querySelector('input[name="data_fim"]')
        const summary = form.querySelector('[data-goal-summary]')
        const units = {
            distancia: ['km', '20'],
            duracao: ['min', '180'],
            atividades: ['atividades', '4'],
            elevacao: ['m', '500'],
            dias_ativos: ['dias', '4'],
            carga_maxima: ['kg', '200']
        }

        const syncSummary = () => {
            if (!summary) return
            const metricLabel = metric?.selectedOptions?.[0]?.textContent?.trim() || 'métrica'
            const sportLabel = sport?.selectedOptions?.[0]?.textContent?.replace(/^★\s*/, '').trim() || 'Todos os esportes'
            const exerciseLabel = exercise?.selectedOptions?.[0]?.textContent?.trim() || ''
            const value = target?.value?.trim() || target?.placeholder || '0'
            const unitLabel = unit?.textContent?.trim() || ''
            let deadline = 'sem prazo'
            if (period?.value === 'personalizado') deadline = endDate?.value ? `até ${new Date(`${endDate.value}T12:00:00`).toLocaleDateString('pt-BR')}` : 'até a data escolhida'
            if (period?.value === 'semanal') deadline = 'a cada semana'
            if (period?.value === 'mensal') deadline = 'a cada mês'
            if (period?.value === 'anual') deadline = 'a cada ano'
            const subject = metric?.value === 'carga_maxima' && exerciseLabel ? exerciseLabel : sportLabel
            summary.textContent = `Objetivo: ${metricLabel.toLowerCase()} de ${value} ${unitLabel} em ${subject}, ${deadline}. As atividades concluídas atualizam isso automaticamente.`
        }

        const syncMetric = () => {
            const [label, placeholder] = units[metric?.value] || units.distancia
            if (unit) unit.textContent = label
            if (target) {
                target.placeholder = placeholder
                target.inputMode = ['atividades', 'dias_ativos'].includes(metric?.value) ? 'numeric' : 'decimal'
            }
            const isLoad = metric?.value === 'carga_maxima'
            if (exerciseField) exerciseField.hidden = !isLoad
            if (exercise) exercise.required = isLoad
            syncSummary()
        }

        const syncPeriod = () => {
            const custom = period?.value === 'personalizado'
            dates?.classList.toggle('is-visible', custom)
            dates?.querySelectorAll('input').forEach(input => input.required = custom)
            if (custom) {
                const start = dates?.querySelector('input[name="data_inicio"]')
                if (start && !start.value) start.value = new Date().toISOString().slice(0, 10)
            }
            syncSummary()
        }

        metric?.addEventListener('change', syncMetric)
        period?.addEventListener('change', syncPeriod)
        sport?.addEventListener('change', syncSummary)
        exercise?.addEventListener('change', syncSummary)
        target?.addEventListener('input', syncSummary)
        endDate?.addEventListener('change', syncSummary)
        syncMetric()
        syncPeriod()
        syncSummary()
    }

    const replaceSection = (doc, key) => {
        const current = page.querySelector(`[data-goals-section="${key}"]`)
        const incoming = doc.querySelector(`[data-goals-section="${key}"]`)
        if (current && incoming) current.replaceWith(incoming)
    }

    bindEditor(page.querySelector('[data-goals-form]'))

    document.addEventListener('submit', async event => {
        const form = event.target.closest?.('.goals-page form')
        if (!form || event.defaultPrevented) return
        const action = form.querySelector('input[name="action"]')?.value || ''
        if (!form.matches('[data-goals-form]') && !['archive', 'reactivate'].includes(action)) return
        if (!window.fetch) return
        event.preventDefault()
        if (form.dataset.ajaxSubmitting === '1') return
        form.dataset.ajaxSubmitting = '1'
        form.classList.add('is-submitting')
        form.setAttribute('aria-busy', 'true')
        const submit = event.submitter || form.querySelector('button[type="submit"]')
        if (submit) submit.disabled = true
        const undoPayload = action === 'archive' ? new FormData(form) : null
        if (undoPayload) undoPayload.set('action', 'reactivate')
        try {
            const response = await (window.StrideBRNet?.fetch || fetch)(form.action || window.location.href, {
                method: 'POST',
                body: new FormData(form),
                headers: {'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest'},
                credentials: 'same-origin'
            }, 15000)
            const html = await response.text()
            const doc = new DOMParser().parseFromString(html, 'text/html')
            const error = doc.querySelector('.goals-page .alert-danger, .goals-page .alert-error')?.textContent?.trim()
            if (!response.ok || error) throw new Error(error || 'Não foi possível atualizar a meta.')
            const message = doc.querySelector('.goals-page .alert-success, .goals-page .alert-info')?.textContent?.trim()
                || (action === 'archive' ? 'Meta arquivada.' : action === 'reactivate' ? 'Meta reativada.' : action === 'edit' ? 'Meta atualizada.' : 'Meta criada.')
            replaceSection(doc, 'active')
            replaceSection(doc, 'history')
            if (form.matches('[data-goals-form]')) {
                page.querySelector('[data-goals-editor]')?.remove()
                history.replaceState({}, '', '/user/metas.php')
            }
            if (action === 'archive' && undoPayload && window.StrideBRUI?.undo) {
                window.StrideBRUI.undo(message, async () => {
                    const undoResponse = await (window.StrideBRNet?.fetch || fetch)(form.action || window.location.href, {
                        method: 'POST',
                        body: undoPayload,
                        headers: {'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest'},
                        credentials: 'same-origin'
                    }, 15000)
                    const undoHtml = await undoResponse.text()
                    const undoDoc = new DOMParser().parseFromString(undoHtml, 'text/html')
                    const undoError = undoDoc.querySelector('.goals-page .alert-danger, .goals-page .alert-error')?.textContent?.trim()
                    if (!undoResponse.ok || undoError) throw new Error(undoError || 'Não foi possível reativar a meta.')
                    replaceSection(undoDoc, 'active')
                    replaceSection(undoDoc, 'history')
                    window.StrideBRUI?.notify('Meta reativada.', 'success', 2600)
                }, 9000)
            } else {
                window.StrideBRUI?.notify(message, 'success')
            }
        } catch (error) {
            window.StrideBRUI?.notify(error?.message || 'Não foi possível atualizar a meta.', 'error', 6000)
        } finally {
            delete form.dataset.ajaxSubmitting
            form.classList.remove('is-submitting')
            form.removeAttribute('aria-busy')
            if (submit) submit.disabled = false
        }
    })
})
