document.addEventListener('DOMContentLoaded', () => {
    const t = (key, values = {}, fallback = key) => window.StrideBRI18n?.t?.(key, values, fallback) ?? fallback
    const localDate = value => window.StrideBRI18n?.date?.(value, {year:'numeric', month:'short', day:'numeric'}) || String(value || '')
    const page = document.querySelector('.goals-page')
    if (!page) return

    let benchmarkData = {rows: [], registry: {}}
    try { benchmarkData = JSON.parse(document.getElementById('goal-benchmark-data')?.textContent || '{}') } catch {}

    const number = (value, digits = 1) => {
        try { return new Intl.NumberFormat(document.documentElement.lang || 'pt-BR', {maximumFractionDigits: digits}).format(value) } catch { return String(value) }
    }
    const clock = value => {
        let seconds = Math.max(0, Math.round(Number(value) || 0))
        const hours = Math.floor(seconds / 3600)
        seconds %= 3600
        const minutes = Math.floor(seconds / 60)
        const rest = seconds % 60
        return hours > 0 ? `${hours}:${String(minutes).padStart(2,'0')}:${String(rest).padStart(2,'0')}` : `${minutes}:${String(rest).padStart(2,'0')}`
    }
    const preciseClock = value => {
        let seconds = Math.max(0, Number(value) || 0)
        if (seconds < 60) return number(seconds, 2)
        const minutes = Math.floor(seconds / 60)
        seconds -= minutes * 60
        return `${minutes}:${seconds.toFixed(2).padStart(5,'0')}`
    }
    const formatBenchmark = (type, value, event = null) => {
        if (!Number.isFinite(Number(value))) return ''
        if (type === 'one_rm') return `${number(Number(value), 1)} kg`
        if (type === 'ftp') return `${number(Number(value), 0)} W`
        if (type === 'css') return `${clock(value)}/100 m`
        if (type === 'distance_time') return clock(value)
        if (type === 'athletics') return event?.measurement === 'time' ? `${preciseClock(value)} s` : `${number(Number(value), 2)} m`
        return number(Number(value), 2)
    }
    const parseTarget = (type, raw, event = null) => {
        const value = String(raw || '').trim().replace(',', '.')
        if (!value) return null
        const clockValue = ['css','distance_time'].includes(type) || (type === 'athletics' && event?.measurement === 'time')
        if (!clockValue) {
            const parsed = Number(value)
            return Number.isFinite(parsed) && parsed > 0 ? parsed : null
        }
        if (/^\d+(?:\.\d+)?$/.test(value)) return Number(value)
        const parts = value.split(':').map(Number)
        if (parts.some(part => !Number.isFinite(part)) || parts.length < 2 || parts.length > 3) return null
        if (parts.length === 2) return parts[0] * 60 + parts[1]
        return parts[0] * 3600 + parts[1] * 60 + parts[2]
    }

    const bindEditor = form => {
        if (!form || form.dataset.goalBound === '1') return
        form.dataset.goalBound = '1'
        const typeRadios = [...form.querySelectorAll('input[name="tipo_meta"]')]
        const fixedType = form.querySelector('input[type="hidden"][name="tipo_meta"]')
        const metric = form.querySelector('[data-goal-metric]')
        const benchmarkType = form.querySelector('[data-goal-benchmark-type]')
        const practiceFields = form.querySelector('[data-goal-practice-fields]')
        const benchmarkFields = form.querySelector('[data-goal-benchmark-fields]')
        const period = form.querySelector('[data-goal-period]')
        const practicePeriods = [...form.querySelectorAll('[data-practice-period]')]
        const dates = form.querySelector('[data-goal-custom-dates]')
        const startField = form.querySelector('[data-goal-start-field]')
        const unit = form.querySelector('[data-goal-unit]')
        const targetLabel = form.querySelector('[data-goal-target-label]')
        const target = form.querySelector('input[name="valor_alvo"]')
        const sport = form.querySelector('select[name="idmodalidade"]')
        const sportField = form.querySelector('[data-goal-sport-field]')
        const athleticsFields = form.querySelector('[data-goal-athletics-fields]')
        const athleticsEvent = form.querySelector('[data-goal-athletics-event]')
        const athleticsEnvironment = form.querySelector('[data-goal-athletics-environment]')
        const athleticsEligible = form.querySelector('[data-goal-athletics-eligible]')
        const exerciseField = form.querySelector('[data-goal-exercise-field]')
        const exercise = form.querySelector('[data-goal-exercise]')
        const loadHelp = form.querySelector('[data-goal-load-help]')
        const distanceField = form.querySelector('[data-goal-distance-field]')
        const distance = form.querySelector('[data-goal-distance]')
        const customDistanceField = form.querySelector('[data-goal-custom-distance]')
        const customDistance = customDistanceField?.querySelector('input')
        const endDate = form.querySelector('input[name="data_fim"]')
        const summary = form.querySelector('[data-goal-summary]')
        const reference = form.querySelector('[data-goal-benchmark-reference]')
        const unavailable = form.querySelector('[data-goal-benchmark-unavailable]')
        const units = {
            distancia: ['km', '20'], duracao: ['min', '180'], atividades: [t('goals.unit.activities', {}, 'activities'), '4'], elevacao: ['m', '500'], dias_ativos: [t('goals.unit.days', {}, 'days'), '4'], carga_maxima: ['kg', '200']
        }

        const goalType = () => fixedType?.value || typeRadios.find(radio => radio.checked)?.value || 'metrica'
        const selectedSport = () => sport?.selectedOptions?.[0] || null
        const selectedSportId = () => sport?.value || ''
        const selectedDistance = () => {
            if (!distance) return null
            if (distance.value === 'custom') return Number(customDistance?.value || 0) || null
            return Number(distance.value || 0) || null
        }
        const selectedAthleticsEvent = () => benchmarkData.registry?.athletics?.events?.[athleticsEvent?.value || ''] || null
        const typeSupported = (option, sportOption) => {
            if (!option?.value) return false
            if (option.value === 'athletics') return Object.keys(benchmarkData.registry?.athletics?.events || {}).length > 0
            if (!sportOption?.value) return false
            const slugs = String(option.dataset.slugs || '').split(',').filter(Boolean)
            const families = String(option.dataset.families || '').split(',').filter(Boolean)
            const slug = sportOption.dataset.slug || ''
            const family = sportOption.dataset.family || ''
            return slugs.length ? slugs.includes(slug) : families.includes(family)
        }
        const matchingRows = () => {
            const type = benchmarkType?.value || ''
            let rows = (benchmarkData.rows || []).filter(row => row.type === type && Number.isFinite(Number(row.value)))
            if (type === 'athletics') {
                rows = rows.filter(row => row.event === (athleticsEvent?.value || '') && row.environment === (athleticsEnvironment?.value || 'unknown'))
                if (athleticsEligible?.checked) rows = rows.filter(row => row.eligibility === 'eligible')
            } else rows = rows.filter(row => row.sport === selectedSportId())
            if (type === 'one_rm') rows = rows.filter(row => row.exercise === (exercise?.value || ''))
            if (type === 'distance_time') {
                const meters = selectedDistance()
                rows = meters === null ? [] : rows.filter(row => Math.abs(Number(row.distance) - meters) < .001)
            }
            return rows
        }
        const referenceRow = (best = false) => {
            const type = benchmarkType?.value || ''
            const cfg = benchmarkData.registry?.[type]
            const event = type === 'athletics' ? selectedAthleticsEvent() : null
            const direction = event?.direction || cfg?.direction
            const rows = matchingRows()
            if (!cfg || !direction || !rows.length) return null
            if (!best && cfg.primary === 'latest') return [...rows].sort((a,b) => String(b.date).localeCompare(String(a.date)))[0]
            return rows.reduce((chosen,row) => {
                if (!chosen) return row
                return direction === 'lower' ? (Number(row.value) < Number(chosen.value) ? row : chosen) : (Number(row.value) > Number(chosen.value) ? row : chosen)
            }, null)
        }
        const syncReference = () => {
            if (!reference || goalType() !== 'benchmark') return
            const type = benchmarkType?.value || ''
            const row = referenceRow(false)
            if (!type || !row) { reference.hidden = true; reference.textContent = ''; return }
            const label = t('goals.benchmark.current_reference', {}, 'Recorded reference')
            const event = type === 'athletics' ? selectedAthleticsEvent() : null
            let text = `${label}: ${formatBenchmark(type, row.value, event)}`
            const targetValue = parseTarget(type, target?.value, event)
            const best = referenceRow(true)
            const cfg = benchmarkData.registry?.[type]
            const direction = event?.direction || cfg?.direction
            if (targetValue !== null && best && direction) {
                const reached = direction === 'lower' ? Number(best.value) <= targetValue : Number(best.value) >= targetValue
                if (reached) text += ` · ${t('goals.error.benchmark_already_achieved', {}, 'This goal has already been reached.')}`
            }
            reference.textContent = text
            reference.hidden = false
        }
        const syncBenchmarkOptions = () => {
            if (!benchmarkType) return
            const sportOption = selectedSport()
            let available = 0
            ;[...benchmarkType.options].forEach(option => {
                if (!option.value) return
                const supported = typeSupported(option, sportOption)
                option.hidden = !supported
                option.disabled = !supported
                if (supported) available++
            })
            if (benchmarkType.value && benchmarkType.selectedOptions[0]?.disabled) benchmarkType.value = ''
            if (unavailable) unavailable.hidden = goalType() !== 'benchmark' || available > 0
        }
        const syncMetric = () => {
            const type = goalType()
            const benchmark = benchmarkType?.value || ''
            const isAthletics = type === 'benchmark' && benchmark === 'athletics'
            if (sportField) sportField.hidden = isAthletics
            if (sport) {
                sport.disabled = sport.dataset.goalLocked === '1' || isAthletics
                const trigger = sport.closest('[data-generic-sport-picker]')?.querySelector('[data-generic-sport-trigger]')
                if (trigger) trigger.disabled = sport.disabled
            }
            practiceFields && (practiceFields.hidden = type !== 'metrica')
            benchmarkFields && (benchmarkFields.hidden = type !== 'benchmark')
            if (metric) metric.disabled = type !== 'metrica'
            if (benchmarkType) benchmarkType.disabled = type !== 'benchmark' || benchmarkType.dataset.goalLocked === '1'
            if (athleticsFields) athleticsFields.hidden = !isAthletics
            if (athleticsEvent) athleticsEvent.disabled = !isAthletics || athleticsEvent.dataset.goalLocked === '1'
            if (athleticsEnvironment) athleticsEnvironment.disabled = !isAthletics || athleticsEnvironment.dataset.goalLocked === '1'
            if (athleticsEligible) athleticsEligible.disabled = !isAthletics || athleticsEligible.dataset.goalLocked === '1'
            const isLoad = type === 'metrica' && metric?.value === 'carga_maxima'
            const isOneRm = type === 'benchmark' && benchmark === 'one_rm'
            if (exerciseField) exerciseField.hidden = !(isLoad || isOneRm)
            if (exercise) exercise.disabled = exercise.dataset.goalLocked === '1' || !(isLoad || isOneRm)
            if (loadHelp) loadHelp.hidden = !isLoad
            const isDistance = type === 'benchmark' && benchmark === 'distance_time'
            if (distanceField) distanceField.hidden = !isDistance
            if (distance) distance.disabled = distance.dataset.goalLocked === '1' || !isDistance
            if (customDistanceField) customDistanceField.hidden = !isDistance || distance?.value !== 'custom'
            if (customDistance) customDistance.disabled = !isDistance || distance?.value !== 'custom'

            if (type === 'benchmark') {
                practicePeriods.forEach(option => { option.disabled = true; option.hidden = true })
                if (period && !['continuo','personalizado'].includes(period.value)) period.value = 'continuo'
                if (startField) startField.hidden = true
                const event = isAthletics ? selectedAthleticsEvent() : null
                const labels = {one_rm:t('goals.benchmark.target_one_rm',{},'1RM target'),ftp:t('goals.benchmark.target_ftp',{},'FTP target'),css:t('goals.benchmark.target_css',{},'CSS target'),distance_time:t('goals.benchmark.target_time',{},'Target time'),athletics:event?.measurement === 'time' ? t('goals.benchmark.target_athletics_time',{},'Target time') : t('goals.benchmark.target_athletics_mark',{},'Target mark')}
                const benchmarkUnits = {one_rm:'kg',ftp:'W',css:'/100 m',distance_time:'',athletics:event?.measurement === 'time' ? 's' : event ? 'm' : ''}
                if (targetLabel) targetLabel.textContent = labels[benchmark] || t('goals.target',{},'Target')
                if (unit) unit.textContent = benchmarkUnits[benchmark] || ''
                if (target) {
                    target.placeholder = benchmark === 'one_rm' ? '100' : benchmark === 'ftp' ? '250' : benchmark === 'css' ? '1:40' : benchmark === 'distance_time' ? '28:00' : isAthletics && event?.measurement === 'time' ? '12.00' : isAthletics ? '6.00' : ''
                    target.inputMode = ['css','distance_time'].includes(benchmark) || (isAthletics && event?.measurement === 'time') ? 'numeric' : 'decimal'
                }
            } else {
                practicePeriods.forEach(option => { option.disabled = false; option.hidden = false })
                if (startField) startField.hidden = false
                const [label, placeholder] = units[metric?.value] || units.distancia
                if (targetLabel) targetLabel.textContent = t('goals.target',{},'Target')
                if (unit) unit.textContent = label
                if (target) { target.placeholder = placeholder; target.inputMode = ['atividades','dias_ativos'].includes(metric?.value) ? 'numeric' : 'decimal' }
            }
            syncPeriod()
            syncReference()
            syncSummary()
        }
        const syncPeriod = () => {
            const custom = period?.value === 'personalizado'
            dates?.classList.toggle('is-visible', custom)
            dates?.querySelectorAll('input').forEach(input => { input.required = custom && !(goalType() === 'benchmark' && input.name === 'data_inicio') })
            if (custom && goalType() === 'metrica') {
                const start = dates?.querySelector('input[name="data_inicio"]')
                if (start && !start.value) start.value = new Date().toISOString().slice(0,10)
            }
            syncSummary()
        }
        const syncSummary = () => {
            if (!summary) return
            const sportLabel = selectedSport()?.textContent?.replace(/^★\s*/, '').trim() || t('goals.all_sports',{},'All sports')
            const value = target?.value?.trim() || target?.placeholder || '0'
            let deadline = t('goals.no_deadline',{},'no deadline')
            if (period?.value === 'personalizado') deadline = endDate?.value ? t('goals.until_date',{date:localDate(`${endDate.value}T12:00:00`)},`until ${localDate(`${endDate.value}T12:00:00`)}`) : t('goals.until_chosen_date',{},'until the selected date')
            if (goalType() === 'benchmark') {
                const label = benchmarkType?.selectedOptions?.[0]?.textContent?.trim() || t('goals.type.benchmark',{},'Mark or test')
                const subject = benchmarkType?.value === 'athletics' ? (athleticsEvent?.selectedOptions?.[0]?.textContent?.trim() || t('athletics.event',{},'Event')) : sportLabel
                summary.textContent = `${label}: ${value}${unit?.textContent ? ` ${unit.textContent}` : ''} · ${subject} · ${deadline}`
                return
            }
            if (period?.value === 'semanal') deadline=t('goals.every_week',{},'every week')
            if (period?.value === 'mensal') deadline=t('goals.every_month',{},'every month')
            if (period?.value === 'anual') deadline=t('goals.every_year',{},'every year')
            const metricLabel=metric?.selectedOptions?.[0]?.textContent?.trim()||t('goals.metric_fallback',{},'metric')
            const exerciseLabel=exercise?.selectedOptions?.[0]?.textContent?.trim()||''
            const subject=metric?.value==='carga_maxima'&&exerciseLabel?exerciseLabel:sportLabel
            summary.textContent=t('goals.summary_template',{metric:metricLabel.toLowerCase(),value,unit:unit?.textContent?.trim()||'',subject,deadline},`${metricLabel}: ${value} · ${subject} · ${deadline}`)
        }

        typeRadios.forEach(radio => radio.addEventListener('change', () => { syncBenchmarkOptions(); syncMetric() }))
        metric?.addEventListener('change', syncMetric)
        benchmarkType?.addEventListener('change', syncMetric)
        athleticsEvent?.addEventListener('change', syncMetric)
        athleticsEnvironment?.addEventListener('change', () => { syncReference(); syncSummary() })
        athleticsEligible?.addEventListener('change', () => { syncReference(); syncSummary() })
        sport?.addEventListener('change', () => { syncBenchmarkOptions(); syncMetric() })
        exercise?.addEventListener('change', () => { syncReference(); syncSummary() })
        distance?.addEventListener('change', syncMetric)
        customDistance?.addEventListener('input', syncReference)
        period?.addEventListener('change', syncPeriod)
        target?.addEventListener('input', () => { syncReference(); syncSummary() })
        endDate?.addEventListener('change', syncSummary)
        syncBenchmarkOptions()
        syncMetric()
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
        if (!form.matches('[data-goals-form]') && !['archive','reactivate'].includes(action)) return
        if (!window.fetch) return
        event.preventDefault()
        if (form.dataset.ajaxSubmitting === '1') return
        form.dataset.ajaxSubmitting='1'
        form.classList.add('is-submitting')
        form.setAttribute('aria-busy','true')
        const submit=event.submitter||form.querySelector('button[type="submit"]')
        if (submit) submit.disabled=true
        const undoPayload=action==='archive'?new FormData(form):null
        if (undoPayload) undoPayload.set('action','reactivate')
        try {
            const response=await (window.StrideBRNet?.fetch||fetch)(form.action||window.location.href,{method:'POST',body:new FormData(form),headers:{'Accept':'text/html','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'},15000)
            const html=await response.text()
            const doc=new DOMParser().parseFromString(html,'text/html')
            const error=doc.querySelector('.goals-page .alert-danger, .goals-page .alert-error')?.textContent?.trim()
            if(!response.ok||error) throw new Error(error||t('goals.update_error',{},'Could not update the goal.'))
            const message=doc.querySelector('.goals-page .alert-success, .goals-page .alert-info')?.textContent?.trim()||(action==='archive'?t('goals.success.archived'):action==='reactivate'?t('goals.success.reactivated'):action==='edit'?t('goals.success.updated'):t('goals.success.created'))
            replaceSection(doc,'active'); replaceSection(doc,'history')
            if(form.matches('[data-goals-form]')){page.querySelector('[data-goals-editor]')?.remove(); history.replaceState({},'','/user/metas.php')}
            if(action==='archive'&&undoPayload&&window.StrideBRUI?.undo){window.StrideBRUI.undo(message,async()=>{const undoResponse=await(window.StrideBRNet?.fetch||fetch)(form.action||window.location.href,{method:'POST',body:undoPayload,headers:{'Accept':'text/html','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'},15000);const undoHtml=await undoResponse.text();const undoDoc=new DOMParser().parseFromString(undoHtml,'text/html');const undoError=undoDoc.querySelector('.goals-page .alert-danger, .goals-page .alert-error')?.textContent?.trim();if(!undoResponse.ok||undoError)throw new Error(undoError||t('goals.reactivate_error',{},'Could not reactivate the goal.'));replaceSection(undoDoc,'active');replaceSection(undoDoc,'history');window.StrideBRUI?.notify(t('goals.success.reactivated'),'success',2600)},9000)}else window.StrideBRUI?.notify(message,'success')
        } catch(error){window.StrideBRUI?.notify(error?.message||t('goals.update_error',{},'Could not update the goal.'),'error',6000)} finally {delete form.dataset.ajaxSubmitting;form.classList.remove('is-submitting');form.removeAttribute('aria-busy');if(submit)submit.disabled=false}
    })
})
