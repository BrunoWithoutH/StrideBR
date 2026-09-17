(() => {
    const states = new WeakMap()
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]))
    const number = (value, digits = 1) => Number.isFinite(Number(value)) ? Number(value).toLocaleString('pt-BR', {maximumFractionDigits: digits}) : '—'
    const duration = value => {
        const seconds = Number(value)
        if (!Number.isFinite(seconds) || seconds < 0) return '—'
        const total = Math.round(seconds)
        const hours = Math.floor(total / 3600)
        const minutes = Math.floor((total % 3600) / 60)
        const secs = total % 60
        return hours > 0 ? `${hours}:${String(minutes).padStart(2,'0')}:${String(secs).padStart(2,'0')}` : `${minutes}:${String(secs).padStart(2,'0')}`
    }
    const elapsed = ms => duration(Number(ms) / 1000)
    const distance = value => {
        const meters = Number(value)
        if (!Number.isFinite(meters)) return '—'
        return meters >= 1000 ? `${number(meters / 1000, meters >= 10000 ? 1 : 2)} km` : `${number(meters, 0)} m`
    }
    const pace = value => {
        const seconds = Number(value)
        if (!Number.isFinite(seconds) || seconds <= 0) return '—'
        const whole = Math.round(seconds)
        return `${Math.floor(whole / 60)}:${String(whole % 60).padStart(2,'0')}/km`
    }
    const pace100 = value => {
        const seconds = Number(value)
        if (!Number.isFinite(seconds) || seconds <= 0) return '—'
        const whole = Math.round(seconds)
        return `${Math.floor(whole / 60)}:${String(whole % 60).padStart(2,'0')}/100m`
    }
    const speed = value => Number.isFinite(Number(value)) ? `${number(value, 1)} km/h` : '—'
    const percent = value => Number.isFinite(Number(value)) ? `${number(value, 1)}%` : '—'
    const fetchJson = async (url, {signal = undefined, timeout = 12000} = {}) => {
        const controller = new AbortController()
        let timedOut = false
        const forwardAbort = () => controller.abort()
        signal?.addEventListener?.('abort', forwardAbort, {once:true})
        const timer = window.setTimeout(() => {
            timedOut = true
            controller.abort()
        }, timeout)
        try {
            const response = await fetch(url, {headers:{Accept:'application/json'}, credentials:'same-origin', signal:controller.signal})
            const payload = await response.json().catch(() => null)
            if (!response.ok || !payload?.ok) throw new Error(payload?.error || 'Não foi possível carregar os dados.')
            return payload.data
        } catch (error) {
            if (timedOut) throw new Error('A solicitação demorou demais. Tente novamente.')
            throw error
        } finally {
            window.clearTimeout(timer)
            signal?.removeEventListener?.('abort', forwardAbort)
        }
    }
    const requestData = async (state, key, url, timeout = 12000) => {
        state.controllers.get(key)?.abort()
        const controller = new AbortController()
        state.controllers.set(key, controller)
        try {
            const data = await fetchJson(url, {signal:controller.signal, timeout})
            if (state.destroyed) throw new DOMException('Aborted', 'AbortError')
            return data
        } finally {
            if (state.controllers.get(key) === controller) state.controllers.delete(key)
        }
    }
    const sourceLabel = value => ({gps:'GPS do app',gps_web:'GPS Web',stridebr_android:'GPS do app',manual:'Registro manual',web:'Web',strava:'Importação',import:'Importação',importacao:'Importação',workout_session:'Treino',quick_register:'Registro manual'}[String(value || '').toLowerCase()] || String(value || '').replaceAll('_',' ') || '—')
    const formatPerformance = (value, unit) => {
        if (value === null || value === undefined || !Number.isFinite(Number(value))) return '—'
        if (unit === 's_per_km') return pace(value)
        if (unit === 's_per_100m') return pace100(value)
        if (unit === 'km_h') return speed(value)
        return number(value, 2)
    }
    const isElevationPresentation = value => /eleva(?:ção|cao|tion)|altitude|desn[ií]vel|desnivel|gain|loss|ganho|perda|ascent|descent|inclina(?:ção|cao|tion)|grade|relevo|terrain\s+elevation/i.test(String(value || ''))
    const visibleMetrics = items => (Array.isArray(items) ? items : []).filter(item => item && !isElevationPresentation(`${item.rotulo || ''} ${item.slug || ''} ${item.chave || ''}`))
    const metricCards = items => {
        const clean = visibleMetrics(items).filter(item => String(item.rotulo || '').trim() && String(item.valor || '').trim())
        if (!clean.length) return ''
        return `<div class="activity-v3-metric-grid">${clean.map(item => `<div><span>${escapeHtml(item.rotulo)}</span><strong>${escapeHtml(item.valor)}</strong></div>`).join('')}</div>`
    }
    const infoRows = activity => {
        const rows = []
        if (activity.origem) rows.push(['Origem', sourceLabel(activity.origem)])
        if (activity.competicao) rows.push(['Competição', activity.competicao])
        if (activity.treino?.titulo) rows.push(['Treino', activity.treino.titulo])
        if (Array.isArray(activity.equipamentos) && activity.equipamentos.length) rows.push(['Equipamento', activity.equipamentos.map(item => item.nome).filter(Boolean).join(' · ')])
        if (activity.esforco !== null && activity.esforco !== undefined) rows.push(['Esforço percebido', `${activity.esforco}/10`])
        return rows
    }
    const routeCollections = activity => {
        const collect = sources => {
            const routes = []
            const seen = new Set()
            sources.forEach(route => {
                const coordinates = route?.geojson?.coordinates
                if (!Array.isArray(coordinates) || coordinates.length < 2) return
                const key = JSON.stringify(coordinates)
                if (seen.has(key)) return
                seen.add(key)
                routes.push({coordinates, breakIndices: Array.isArray(route?.break_indices) ? route.break_indices : []})
            })
            return routes
        }
        const unitRoutes = collect((Array.isArray(activity?.unidades) ? activity.unidades : []).map(unit => unit?.rota))
        return unitRoutes.length ? unitRoutes : collect([activity?.rota])
    }
    const routeHtml = (activity, {saveAction = false, contextRail = false} = {}) => {
        const routes = routeCollections(activity)
        if (!routes.length) return ''
        const distanceValue = Number(activity?.rota?.distancia_m)
        const action = saveAction ? `<button type="button" class="activity-secondary-button activity-v3-route-save" data-save-activity-route="${escapeHtml(activity.id)}">Salvar rota</button>` : ''
        return `<section class="activity-v3-route activity-map-frame${contextRail ? ' has-context-rail' : ''}" data-activity-v3-route><div class="activity-v3-section-head"><div><span>Percurso</span>${Number.isFinite(distanceValue) && distanceValue > 0 ? `<strong>${escapeHtml(distance(distanceValue))}</strong>` : ''}</div>${action}</div><div class="activity-v3-map" data-activity-v3-map aria-label="Mapa da rota"></div><small class="activity-v3-map-fallback" data-activity-v3-map-status>Carregando mapa…</small></section>`
    }
    const strengthHtml = activity => {
        const strength = activity?.forca
        if (!strength) return ''
        const exercises = Array.isArray(strength.exercicios) ? strength.exercicios : []
        return `<section class="activity-v3-section"><div class="activity-v3-section-head"><div><span>Força</span><strong>${escapeHtml(`${strength.total_exercicios || 0} exercícios · ${strength.total_series || 0} séries`)}</strong></div>${Number(strength.volume_kg) > 0 ? `<small>${escapeHtml(number(strength.volume_kg,0))} kg de volume</small>` : ''}</div><div class="activity-v3-strength-list">${exercises.map(exercise => `<article><header><strong>${escapeHtml(exercise.nome || 'Exercício')}</strong>${exercise.melhor_carga_kg !== null && exercise.melhor_carga_kg !== undefined ? `<small>Melhor carga: ${escapeHtml(number(exercise.melhor_carga_kg,2))} kg</small>` : ''}</header><div class="activity-v3-strength-sets">${(exercise.series || []).map(set => `<span class="${set.concluida ? '' : 'is-incomplete'}"><b>${escapeHtml(set.numero)}</b><strong>${set.carga_kg !== null ? `${escapeHtml(number(set.carga_kg,2))} kg` : '—'}</strong><em>${set.repeticoes !== null ? `${escapeHtml(set.repeticoes)} reps` : '—'}</em></span>`).join('')}</div></article>`).join('')}</div></section>`
    }
    const detailsHtml = activity => {
        const rows = infoRows(activity)
        const primaryMetricLabels = new Set(visibleMetrics(activity.metricas).map(item => String(item?.rotulo || '').normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim().toLowerCase()).filter(Boolean))
        const data = visibleMetrics(activity.dados).filter(item => item?.rotulo && item?.valor && !primaryMetricLabels.has(String(item.rotulo).normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim().toLowerCase()))
        const units = (Array.isArray(activity.unidades) ? activity.unidades : []).map(unit => ({...unit, valores: visibleMetrics(unit.valores)})).filter(unit => unit.valores.length)
        const contextClass = rows.length >= 2 ? ' activity-v3-context is-rail-worthy' : ' activity-v3-context is-compact'
        const context = rows.length ? `<section class="activity-v3-section${contextClass}"><div class="activity-v3-section-head"><div><span>Contexto</span></div></div><div class="activity-v3-info-list">${rows.map(([label,value]) => `<div><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`).join('')}</div></section>` : ''
        const extraData = data.length ? `<section class="activity-v3-section"><div class="activity-v3-section-head"><div><span>Dados</span></div></div>${metricCards(data)}</section>` : ''
        const segments = units.length ? `<section class="activity-v3-section activity-v3-segments"><div class="activity-v3-section-head"><div><span>Trechos registrados</span></div></div><div class="activity-v3-unit-list">${units.map(unit => `<article><div class="activity-v3-unit-title"><strong>${escapeHtml(unit.rotulo || 'Trecho')}</strong>${unit.modalidade ? `<small> · ${escapeHtml(unit.modalidade)}</small>` : ''}</div><div class="activity-v3-unit-metrics">${visibleMetrics(unit.valores).filter(item=>String(item?.rotulo||'').trim()&&String(item?.valor||'').trim()).map(item=>`<span><b>${escapeHtml(item.valor)}</b><small>${escapeHtml(item.rotulo)}</small></span>`).join('')}</div></article>`).join('')}</div></section>` : ''
        const notes = activity.observacoes ? `<section class="activity-v3-section activity-v3-notes-section"><div class="activity-v3-section-head"><div><span>Observações</span></div></div><p class="activity-v3-notes">${escapeHtml(activity.observacoes).replace(/\n/g,'<br>')}</p></section>` : ''
        return `${context}${extraData}${segments}${notes}`
    }
    const tabsFor = activity => {
        const caps = activity?.stream_capabilities || {}
        const available = new Set(caps.available_streams || [])
        const graphable = ['pace','speed','heart_rate','cadence','power','temperature'].some(stream => available.has(stream))
        const tabs = [{id:'summary',label:'Resumo'}]
        if (caps.has_streams && graphable) tabs.push({id:'charts',label:'Gráficos'})
        if (caps.has_streams && available.has('distance')) tabs.push({id:'splits',label:'Splits'})
        if (caps.has_laps) tabs.push({id:'laps',label:'Voltas'})
        if (caps.has_streams && (caps.has_heart_rate || available.has('pace'))) tabs.push({id:'zones',label:'Zonas'})
        if (caps.has_streams && graphable) tabs.push({id:'analysis',label:'Análise'})
        return tabs
    }
    const shellHtml = (activity, mode = 'preview') => {
        const previewMetrics = visibleMetrics(activity.metricas || []).slice(0, 4)
        if (mode === 'preview') {
            return `<div class="activity-v3 is-preview" data-activity-v3>${metricCards(previewMetrics)}${routeHtml(activity)}<footer class="activity-detail-actions has-share activity-v3-preview-actions"><button type="button" class="activity-secondary-button" data-share-activity>Compartilhar</button><button type="button" class="activity-primary-action" data-open-full-activity-details>Abrir detalhes</button></footer></div>`
        }
        const tabs = tabsFor(activity)
        const contextRail = infoRows(activity).length >= 2
        const summary = `${routeHtml(activity,{saveAction:true,contextRail})}${detailsHtml(activity)}${strengthHtml(activity)}`
        const tabBar = tabs.length > 1 ? `<nav class="activity-v3-tabs" role="tablist" aria-label="Detalhes da atividade">${tabs.map((tab,index) => `<button type="button" id="activity-v3-tab-${tab.id}" role="tab" data-activity-v3-tab="${tab.id}" aria-controls="activity-v3-panel-${tab.id}" aria-selected="${index===0?'true':'false'}" tabindex="${index===0?'0':'-1'}">${escapeHtml(tab.label)}</button>`).join('')}</nav>` : ''
        const panels = tabs.map((tab,index) => `<section id="activity-v3-panel-${tab.id}"${tabs.length > 1 ? ` role="tabpanel" aria-labelledby="activity-v3-tab-${tab.id}"` : ''} class="activity-v3-panel" data-activity-v3-panel="${tab.id}"${index===0?'':' hidden'}>${tab.id==='summary' ? summary : ''}</section>`).join('')
        return `<div class="activity-v3 is-detail${tabs.length === 1 ? ' has-single-tab' : ''}" data-activity-v3>${tabBar}<div class="activity-v3-panels">${panels}</div></div>`
    }
    const bindRouteSave = (container, activity) => {
        const button=container.querySelector('[data-save-activity-route]')
        if(!button)return
        button.addEventListener('click',async()=>{
            const page=document.querySelector('[data-activities-page]')
            const token=page?.dataset.csrfToken||''
            const original=button.textContent
            button.disabled=true
            button.textContent='Salvando…'
            try{
                const body=new URLSearchParams({id:String(activity.id||''),csrf_token:token})
                const response=await fetch('/api/rota-salvar.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','Accept':'application/json'},credentials:'same-origin',body})
                const payload=await response.json().catch(()=>null)
                if(!response.ok||!payload?.ok)throw new Error(payload?.error||'Não foi possível salvar a rota.')
                const link=document.createElement('a')
                link.className='activity-secondary-button'
                link.href=`/user/rotas.php?id=${encodeURIComponent(payload.data.id)}`
                link.textContent='Abrir rota'
                button.replaceWith(link)
                window.StrideBRUI?.notify?.('Rota salva.','success')
            }catch(error){
                button.disabled=false
                button.textContent=original
                window.StrideBRUI?.notify?.(error?.message||'Não foi possível salvar a rota.','error')
            }
        })
    }
    const bindStatsToggle = (container, activity) => {
        const button=container.querySelector('[data-stats-activity]')
        if(!button)return
        button.addEventListener('click',async()=>{
            const page=document.querySelector('[data-activities-page]')
            const excluded=button.getAttribute('aria-pressed')!=='true'
            button.disabled=true
            try{
                const body=new URLSearchParams({id:activity.id,excluded:excluded?'1':'0',csrf_token:page?.dataset.csrfToken||''})
                const response=await fetch('/api/atividade-estatisticas.php',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body,credentials:'same-origin'})
                const payload=await response.json().catch(()=>null)
                if(!response.ok||!payload?.ok)throw new Error(payload?.error||'Não foi possível atualizar as estatísticas.')
                activity.excluded_from_stats=excluded
                button.setAttribute('aria-pressed',excluded?'true':'false')
                button.textContent=excluded?'Incluir nas estatísticas':'Excluir das estatísticas'
            }catch(error){window.alert(error.message||'Não foi possível atualizar as estatísticas.')}finally{button.disabled=false}
        })
    }
    const metricDefinitions = data => {
        const available = new Set(data.available_streams || [])
        const definitions = []
        const derived = String(data?.sport?.derived_metric || '')
        if (derived === 'pace_km' && available.has('pace')) definitions.push({id:'pace',label:'Pace',unit:'s_per_km',invert:true})
        else if (derived === 'pace_100m' && available.has('pace')) definitions.push({id:'pace',label:'Pace',unit:'s_per_100m',invert:true})
        else if (available.has('speed')) definitions.push({id:'speed',label:'Velocidade',unit:'m_s'})
        if (available.has('heart_rate')) definitions.push({id:'heart_rate',label:'Frequência cardíaca',unit:'bpm'})
        if (available.has('cadence')) definitions.push({id:'cadence',label:'Cadência',unit:data?.units?.cadence || 'spm'})
        if (available.has('power')) definitions.push({id:'power',label:'Potência',unit:'W'})
        if (available.has('temperature')) definitions.push({id:'temperature',label:'Temperatura',unit:'celsius'})
        return definitions
    }
    const metricValue = (sample, metric) => {
        const id = typeof metric === 'string' ? metric : metric?.id
        if (id === 'pace') return sample.pace
        if (id === 'speed') return Number.isFinite(Number(sample.speed)) ? Number(sample.speed) * 3.6 : null
        return id ? sample[id] : null
    }
    const metricFormat = (value, definition) => {
        if (!Number.isFinite(Number(value))) return '—'
        if (definition.id === 'pace') return definition.unit === 's_per_100m' ? pace100(value) : pace(value)
        if (definition.id === 'speed') return speed(value)
        if (definition.id === 'heart_rate') return `${number(value,0)} bpm`
        if (definition.id === 'cadence') return `${number(value,0)} ${definition.unit}`
        if (definition.id === 'power') return `${number(value,0)} W`
        if (definition.id === 'temperature') return `${number(value,1)} °C`
        return number(value,2)
    }
    const pathSegments = (samples, definition, width, height) => {
        const valid = samples.map((sample,index) => ({sample,index,value:metricValue(sample,definition)})).filter(item => Number.isFinite(Number(item.value)) && Number.isFinite(Number(item.sample.x)))
        if (valid.length < 2) return {segments:[],min:null,max:null}
        const xs = valid.map(item => Number(item.sample.x))
        const values = valid.map(item => Number(item.value))
        const minX = Math.min(...xs)
        const maxX = Math.max(...xs)
        const min = Math.min(...values)
        const max = Math.max(...values)
        const rangeX = Math.max(maxX-minX,1)
        const rangeY = Math.max(max-min,0.000001)
        const padX = 8
        const padY = 10
        const groups = []
        let current = []
        valid.forEach(item => {
            if (item.sample.gap_before_ms && current.length) {
                groups.push(current)
                current=[]
            }
            const ratio = (Number(item.value)-min)/rangeY
            const visual = definition.invert ? ratio : 1-ratio
            const x = padX + ((Number(item.sample.x)-minX)/rangeX)*(width-padX*2)
            const y = padY + visual*(height-padY*2)
            current.push(`${x.toFixed(2)},${y.toFixed(2)}`)
        })
        if (current.length) groups.push(current)
        return {segments:groups,min,max}
    }
    const chartHtml = (definition, samples, primary = false) => {
        const width=900
        const height=primary?180:110
        const path=pathSegments(samples,definition,width,height)
        if (!path.segments.length) return ''
        return `<article class="activity-v3-chart${primary?' is-primary':''}" data-chart-metric="${definition.id}"><header><div><strong>${escapeHtml(definition.label)}</strong><small>${escapeHtml(metricFormat(path.min,definition))} – ${escapeHtml(metricFormat(path.max,definition))}</small></div></header><div class="activity-v3-chart-surface" data-chart-surface><svg viewBox="0 0 ${width} ${height}" preserveAspectRatio="none" role="img" aria-label="${escapeHtml(`${definition.label}: ${metricFormat(path.min,definition)} a ${metricFormat(path.max,definition)}`)}">${path.segments.map(points => `<polyline points="${points.join(' ')}" />`).join('')}<line x1="0" y1="0" x2="0" y2="${height}" data-chart-cursor hidden /></svg></div></article>`
    }
    const selectedMetricIds = (state, definitions) => {
        const available = new Set(definitions.map(item => item.id))
        const selected = Array.from(state.chartMetrics || []).filter(id => available.has(id))
        if (selected.length) return selected
        return definitions.slice(0,4).map(item => item.id)
    }
    const chartsMarkup = (state, data) => {
        const definitions = metricDefinitions(data)
        const selected = selectedMetricIds(state, definitions)
        state.chartMetrics = new Set(selected)
        if (!definitions.length) return '<div class="activity-v3-empty">Nenhum dado contínuo disponível para gráficos nesta atividade.</div>'
        const enabled = new Set(selected)
        const toggles = definitions.map(def => `<label><input type="checkbox" value="${def.id}" data-chart-toggle${enabled.has(def.id)?' checked':''}><span>${escapeHtml(def.label)}</span></label>`).join('')
        const visible = definitions.filter(def => enabled.has(def.id))
        const charts = visible.map((def,index) => chartHtml(def,data.samples || [],index===0)).join('')
        const distanceAvailable = (data.available_streams || []).includes('distance')
        const axisControls = distanceAvailable ? `<div class="activity-v3-axis" role="group" aria-label="Eixo dos gráficos"><button type="button" data-chart-axis="distance" class="${data.axis==='distance'?'is-active':''}">Distância</button><button type="button" data-chart-axis="time" class="${data.axis==='time'?'is-active':''}">Tempo</button></div>` : `<div class="activity-v3-axis"><button type="button" data-chart-axis="time" class="is-active" disabled>Tempo</button></div>`
        return `<div class="activity-v3-chart-tools">${axisControls}<div class="activity-v3-chart-toggles">${toggles}</div></div><div class="activity-v3-chart-tooltip" data-chart-tooltip><strong>Mova o cursor sobre um gráfico</strong><span>As séries usam o mesmo eixo.</span></div><div class="activity-v3-chart-list" data-chart-list>${charts || '<div class="activity-v3-empty">Nenhum dado contínuo disponível para gráficos nesta atividade.</div>'}</div>`
    }
    const nearestSample = (samples, ratio) => {
        if (!samples.length) return null
        const min = Number(samples[0]?.x)
        const max = Number(samples[samples.length-1]?.x)
        if (!Number.isFinite(min) || !Number.isFinite(max)) return samples[0]
        const target=min+(max-min)*Math.max(0,Math.min(1,ratio))
        let low=0
        let high=samples.length-1
        while(low<high){const mid=Math.floor((low+high)/2); if(Number(samples[mid].x)<target) low=mid+1; else high=mid}
        if(low>0 && Math.abs(Number(samples[low-1].x)-target)<Math.abs(Number(samples[low].x)-target)) return samples[low-1]
        return samples[low]
    }
    const tooltipHtml = (sample, definitions, axis) => {
        if (!sample) return '<strong>Sem ponto</strong>'
        const head = axis === 'distance' ? `${distance(sample.distance_m)} · ${elapsed(sample.elapsed_ms)}` : `${elapsed(sample.elapsed_ms)} · ${distance(sample.distance_m)}`
        const rows = definitions.map(def => {
            const value=metricValue(sample,def)
            return Number.isFinite(Number(value)) ? `<span><b>${escapeHtml(def.label)}</b><strong>${escapeHtml(metricFormat(value,def))}</strong></span>` : ''
        }).join('')
        return `<strong>${escapeHtml(head)}</strong><div>${rows}</div>`
    }
    const bindCharts = (state, panel, data) => {
        state.streamData=data
        const definitions=metricDefinitions(data)
        const enabled=()=>new Set(Array.from(panel.querySelectorAll('[data-chart-toggle]:checked')).map(input=>input.value))
        const rerender=()=>{
            state.chartMetrics=enabled()
            const active=state.chartMetrics
            const list=panel.querySelector('[data-chart-list]')
            if(list) list.innerHTML=definitions.filter(def=>active.has(def.id)).map((def,index)=>chartHtml(def,data.samples||[],index===0)).join('') || '<div class="activity-v3-empty">Selecione uma métrica.</div>'
            bindSurfaces()
        }
        const updateCursor=(event,surface)=>{
            const rect=surface.getBoundingClientRect()
            if(rect.width<=0)return
            const ratio=(event.clientX-rect.left)/rect.width
            const sample=nearestSample(data.samples||[],ratio)
            const active=definitions.filter(def=>enabled().has(def.id))
            const tooltip=panel.querySelector('[data-chart-tooltip]')
            if(tooltip) tooltip.innerHTML=tooltipHtml(sample,active,data.axis)
            const x=Math.max(0,Math.min(1,ratio))*900
            panel.querySelectorAll('[data-chart-cursor]').forEach(line=>{line.hidden=false;line.setAttribute('x1',String(x));line.setAttribute('x2',String(x))})
        }
        const bindSurfaces=()=>panel.querySelectorAll('[data-chart-surface]').forEach(surface=>{
            surface.onpointerdown=event=>{surface.setPointerCapture?.(event.pointerId);updateCursor(event,surface)}
            surface.onpointermove=event=>updateCursor(event,surface)
            surface.onpointerleave=()=>panel.querySelectorAll('[data-chart-cursor]').forEach(line=>{line.hidden=true})
        })
        panel.querySelectorAll('[data-chart-toggle]').forEach(input=>input.addEventListener('change',rerender))
        panel.querySelectorAll('[data-chart-axis]').forEach(button=>button.addEventListener('click',async()=>{
            const axis=button.dataset.chartAxis
            if(axis===data.axis||button.disabled)return
            state.chartMetrics=enabled()
            await loadCharts(state,panel,axis,{force:false})
        }))
        bindSurfaces()
        const breaks=(data.samples||[]).filter(sample=>Number(sample.gap_before_ms)>0 && Number.isInteger(Number(sample.route_point_index))).map(sample=>Number(sample.route_point_index))
        if(breaks.length) state.mapController?.setBreakIndices?.(breaks)
    }
    const chartsError = (panel, message) => {
        panel.innerHTML=`<div class="activity-v3-error"><strong>Não foi possível carregar os gráficos.</strong><span>${escapeHtml(message||'Tente novamente.')}</span><button type="button" class="activity-secondary-button" data-retry-tab="charts">Tentar novamente</button></div>`
    }
    const loadCharts = async (state,panel,axis='distance',{force=false}={}) => {
        const available=state.activity.stream_capabilities?.available_streams||[]
        let resolvedAxis=axis
        if(axis==='distance' && !available.includes('distance')) resolvedAxis='time'
        const cacheKey=`streams:${resolvedAxis}:medium`
        panel.innerHTML='<div class="activity-v3-lazy"><span></span><strong>Carregando gráficos…</strong></div>'
        try {
            const streams=available.filter(value=>['pace','speed','heart_rate','cadence','power','temperature'].includes(value)).join(',')
            const data=!force&&state.cache.has(cacheKey)?state.cache.get(cacheKey):await requestData(state,'charts',`/api/atividade-streams.php?id=${encodeURIComponent(state.activity.id)}&axis=${resolvedAxis}&resolution=medium&streams=${encodeURIComponent(streams)}`)
            state.cache.set(cacheKey,data)
            if(!metricDefinitions(data).length || !(data.samples||[]).some(sample=>metricDefinitions(data).some(def=>Number.isFinite(Number(metricValue(sample,def)))))) {
                panel.innerHTML='<div class="activity-v3-empty">Nenhum dado contínuo disponível para gráficos nesta atividade.</div>'
                return 'empty'
            }
            panel.innerHTML=chartsMarkup(state,data)
            bindCharts(state,panel,data)
            return 'loaded'
        } catch(error) {
            if(error?.name==='AbortError' && state.destroyed) return 'idle'
            chartsError(panel,error?.message)
            return 'error'
        }
    }
    const splitTable = data => {
        const rows=Array.isArray(data?.data)?data.data:[]
        if(!rows.length)return '<div class="activity-v3-empty">Sem splits para esta distância.</div>'
        const speedMode=String(data?.sport?.derived_metric||'')==='velocidade_kmh'
        const performanceLabel=speedMode?'Velocidade':'Pace'
        const performance=row=>speedMode?(row.speed_kmh!==null?speed(row.speed_kmh):'—'):(row.pace_s_per_km!==null?pace(row.pace_s_per_km):'—')
        return `<div class="activity-v3-table-wrap"><table class="activity-v3-table"><thead><tr><th>Split</th><th>Distância</th><th>Tempo</th><th>${performanceLabel}</th><th>FC méd.</th><th>FC máx.</th><th>Cadência</th></tr></thead><tbody>${rows.map(row=>`<tr><th>${escapeHtml(row.index)}${row.partial?'*':''}</th><td>${escapeHtml(distance(row.distance_m))}</td><td>${escapeHtml(duration(row.moving_duration_s ?? row.elapsed_duration_s))}</td><td>${escapeHtml(performance(row))}</td><td>${row.heart_rate_avg_bpm!==null?`${escapeHtml(number(row.heart_rate_avg_bpm,0))} bpm`:'—'}</td><td>${row.heart_rate_max_bpm!==null?`${escapeHtml(number(row.heart_rate_max_bpm,0))} bpm`:'—'}</td><td>${row.cadence_avg!==null?escapeHtml(number(row.cadence_avg,0)):'—'}</td></tr>`).join('')}</tbody></table></div>${rows.some(row=>row.partial)?'<small class="activity-v3-table-note">* Split parcial no final da atividade.</small>':''}`
    }
    const loadSplits = async (state,panel,distanceM=1000) => {
        panel.innerHTML='<div class="activity-v3-lazy"><span></span><strong>Calculando splits…</strong></div>'
        try {
            const data=await requestData(state,'splits',`/api/atividade-splits.php?id=${encodeURIComponent(state.activity.id)}&distance_m=${encodeURIComponent(distanceM)}`)
            panel.innerHTML=`<div class="activity-v3-split-tools"><label>Distância do split<select data-split-distance><option value="500"${distanceM===500?' selected':''}>500 m</option><option value="1000"${distanceM===1000?' selected':''}>1 km</option><option value="5000"${distanceM===5000?' selected':''}>5 km</option><option value="custom"${![500,1000,5000].includes(distanceM)?' selected':''}>Personalizado</option></select></label><label data-split-custom${[500,1000,5000].includes(distanceM)?' hidden':''}>Metros<input type="number" min="100" max="100000" step="100" value="${escapeHtml(distanceM)}" data-split-custom-value></label><button type="button" class="activity-secondary-button" data-split-apply>Aplicar</button></div><div data-split-table>${splitTable(data)}</div>`
            const select=panel.querySelector('[data-split-distance]')
            const custom=panel.querySelector('[data-split-custom]')
            select?.addEventListener('change',()=>{if(custom) custom.hidden=select.value!=='custom'})
            panel.querySelector('[data-split-apply]')?.addEventListener('click',()=>{
                const value=select?.value==='custom'?Number(panel.querySelector('[data-split-custom-value]')?.value):Number(select?.value)
                if(Number.isFinite(value)) loadSplits(state,panel,Math.max(100,Math.min(100000,Math.round(value))))
            })
            return 'loaded'
        } catch(error) {
            panel.innerHTML=`<div class="activity-v3-error"><strong>Não foi possível carregar os splits.</strong><span>${escapeHtml(error.message)}</span><button type="button" class="activity-secondary-button" data-retry-tab="splits">Tentar novamente</button></div>`
            return 'error'
        }
    }
    const lapsTable = data => {
        const rows=Array.isArray(data?.data)?data.data:[]
        if(!rows.length)return '<div class="activity-v3-empty">Nenhuma volta manual registrada.</div>'
        const speedMode=String(data?.sport?.derived_metric||'')==='velocidade_kmh'
        const performanceLabel=speedMode?'Velocidade':'Pace'
        const performance=row=>speedMode?(row.speed_kmh!==null?speed(row.speed_kmh):'—'):(row.pace_s_per_km!==null?pace(row.pace_s_per_km):'—')
        return `<div class="activity-v3-table-wrap"><table class="activity-v3-table"><thead><tr><th>Volta</th><th>Distância</th><th>Tempo</th><th>${performanceLabel}</th><th>FC méd.</th><th>Origem</th></tr></thead><tbody>${rows.map(row=>`<tr><th>${escapeHtml(row.order)}</th><td>${escapeHtml(distance(row.distance_m))}</td><td>${escapeHtml(duration(row.moving_duration_s ?? row.elapsed_duration_s))}</td><td>${escapeHtml(performance(row))}</td><td>${row.heart_rate_avg_bpm!==null?`${escapeHtml(number(row.heart_rate_avg_bpm,0))} bpm`:'—'}</td><td>${escapeHtml(row.origin==='manual'?'Manual':row.origin==='import'?'Importada':row.origin)}</td></tr>`).join('')}</tbody></table></div>`
    }
    const loadLaps = async (state,panel) => {
        try {
            const data=await requestData(state,'laps',`/api/atividade-laps.php?id=${encodeURIComponent(state.activity.id)}`)
            panel.innerHTML=lapsTable(data)
            return 'loaded'
        } catch(error) {
            panel.innerHTML=`<div class="activity-v3-error"><strong>Não foi possível carregar as voltas.</strong><span>${escapeHtml(error.message)}</span><button type="button" class="activity-secondary-button" data-retry-tab="laps">Tentar novamente</button></div>`
            return 'error'
        }
    }
    const zoneBlock = (title,distribution,unit) => {
        if(!distribution?.zones?.length)return ''
        return `<section class="activity-v3-zone-block"><div class="activity-v3-section-head"><div><span>${escapeHtml(title)}</span><strong>${escapeHtml(distribution.profile?.name || 'Perfil configurado')}</strong></div></div><div class="activity-v3-zones">${distribution.zones.map(zone=>`<div><span><b>${escapeHtml(zone.code || zone.label || 'Zona')}</b><small>${escapeHtml(duration(zone.time_s || 0))}${zone.distance_m?` · ${escapeHtml(distance(zone.distance_m))}`:''}</small></span><i><em style="width:${Math.max(0,Math.min(100,Number(zone.percentage)||0))}%"></em></i><strong>${escapeHtml(number(zone.percentage,1))}%</strong></div>`).join('')}</div>${unit?`<small class="activity-v3-zone-unit">Unidade do perfil: ${escapeHtml(unit)}</small>`:''}</section>`
    }
    const getAnalysis = state => {
        if(!state.analysisPromise) state.analysisPromise=requestData(state,'analysis',`/api/atividade-analysis.php?id=${encodeURIComponent(state.activity.id)}`).catch(error=>{state.analysisPromise=null;throw error})
        return state.analysisPromise
    }
    const getPlannedActual = state => {
        if(!state.plannedActualPromise) state.plannedActualPromise=requestData(state,'planned-actual',`/api/atividade-planned-actual.php?id=${encodeURIComponent(state.activity.id)}`).catch(error=>{state.plannedActualPromise=null;throw error})
        return state.plannedActualPromise
    }
    const loadZones = async (state,panel) => {
        try {
            const analysis=await getAnalysis(state)
            const hr=zoneBlock('Zonas de frequência cardíaca',analysis.heart_rate?.zones,'bpm')
            const paceZones=zoneBlock('Zonas de pace',analysis.pace_zones,'s/km')
            panel.innerHTML=hr+paceZones || `<div class="activity-v3-empty"><strong>Nenhum perfil de zonas aplicável.</strong><span>Configure limites manuais para analisar a distribuição desta atividade.</span><a class="activity-primary-action" href="/user/zonas.php">Configurar zonas</a></div>`
            return hr+paceZones ? 'loaded' : 'empty'
        } catch(error) {
            panel.innerHTML=`<div class="activity-v3-error"><strong>Não foi possível carregar as zonas.</strong><span>${escapeHtml(error.message)}</span><button type="button" class="activity-secondary-button" data-retry-tab="zones">Tentar novamente</button></div>`
            return 'error'
        }
    }
    const findingText = finding => ({negative_split:'Você completou a segunda metade mais rápido que a primeira.',positive_split:'A segunda metade foi mais lenta que a primeira.',even:'As duas metades tiveram ritmo semelhante.',strong_finish:'Os últimos 10% foram mais rápidos que o trecho anterior.',pace_variability_high:'O ritmo variou bastante ao longo da atividade.',heart_rate_drift_detected:'A relação entre ritmo e frequência cardíaca mudou ao longo da atividade.'}[finding?.code] || String(finding?.code || '').replaceAll('_',' '))
    const analysisMetric = (label,value) => value && value!=='—' ? `<div><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>` : ''
    const analysisHtml = analysis => {
        const p=analysis.pacing
        const hr=analysis.heart_rate
        const cadence=analysis.cadence
        const performanceUnit=p?.unit
        const metrics=[]
        if(p){metrics.push(['Média',formatPerformance(p.average,performanceUnit)],['Mediana',formatPerformance(p.median,performanceUnit)],['Primeira metade',formatPerformance(p.first_half,performanceUnit)],['Segunda metade',formatPerformance(p.second_half,performanceUnit)],['Variação',percent(p.variability_percent)],['Últimos 10%',formatPerformance(p.finish?.last_10_percent,performanceUnit)])}
        if(hr){metrics.push(['FC média',`${number(hr.average_bpm,0)} bpm`],['FC máxima',`${number(hr.max_bpm,0)} bpm`],['Cobertura FC',percent(hr.coverage_percent)])}
        if(cadence){metrics.push(['Cadência média',`${number(cadence.average,0)} ${cadence.unit}`])}
        const findings=(analysis.findings||[]).map(item=>`<article><strong>${escapeHtml(findingText(item))}</strong><small>${escapeHtml(item.formula || '')}</small></article>`).join('')
        const efforts=(analysis.best_efforts||[]).map(item=>`<div><span>${escapeHtml(item.label)}</span><strong>${escapeHtml(duration(item.duration_s))}</strong><small>${escapeHtml(pace(item.pace_s_per_km))}</small></div>`).join('')
        const drift=hr?.decoupling ? `<section class="activity-v3-section"><div class="activity-v3-section-head"><div><span>Cardiac drift</span><strong>${escapeHtml(percent(hr.decoupling.decoupling_percent))}</strong></div></div><p class="activity-v3-notes">Mede a mudança da relação entre velocidade e frequência cardíaca entre as duas metades. É uma métrica esportiva, não médica.</p></section>` : ''
        return `${p ? `<section class="activity-v3-analysis-hero"><span>Padrão de ritmo</span><strong>${escapeHtml(({negative_split:'Negative split',positive_split:'Positive split',even:'Ritmo estável',insufficient_data:'Dados insuficientes'}[p.pattern]||p.pattern))}</strong>${Number.isFinite(Number(p.difference_percent))?`<small>${escapeHtml(percent(p.difference_percent))} entre as metades</small>`:''}</section>`:''}<section class="activity-v3-section"><div class="activity-v3-analysis-grid">${metrics.map(([label,value])=>analysisMetric(label,value)).join('')}</div></section>${findings?`<section class="activity-v3-section"><div class="activity-v3-section-head"><div><span>Leituras da atividade</span></div></div><div class="activity-v3-findings">${findings}</div></section>`:''}${efforts?`<section class="activity-v3-section"><div class="activity-v3-section-head"><div><span>Melhores trechos desta atividade</span></div></div><div class="activity-v3-best-efforts">${efforts}</div></section>`:''}${drift}`
    }
    const targetLabel = target => {
        if(!target)return '—'
        const values=[target.min,target.max].filter(value=>Number.isFinite(Number(value)))
        if(!values.length)return '—'
        const format=value=>target.unit==='s_per_km'?pace(value):target.unit==='km_h'?speed(value):target.unit==='bpm'?`${number(value,0)} bpm`:target.unit==='s'?duration(value):target.unit==='m'?distance(value):number(value,1)
        return values.length===1?format(values[0]):`${format(values[0])} – ${format(values[1])}`
    }
    const plannedActualHtml = data => {
        const steps=Array.isArray(data?.steps)?data.steps:[]
        if(!steps.length)return ''
        return `<section class="activity-v3-section activity-v3-planned-actual"><div class="activity-v3-section-head"><div><span>Planejado × realizado</span><strong>${escapeHtml(`${data.comparable_steps||0} de ${steps.length} blocos comparáveis`)}</strong></div></div><div class="activity-v3-table-wrap"><table class="activity-v3-table"><thead><tr><th>Bloco</th><th>Planejado</th><th>Realizado</th><th>Alvo</th><th>Resultado</th></tr></thead><tbody>${steps.map(step=>{const planned=[step.planned_distance_m?distance(step.planned_distance_m):'',step.planned_duration_s?duration(step.planned_duration_s):''].filter(Boolean).join(' · ')||'—';const actual=step.actual;const actualText=actual?[actual.distance_m?distance(actual.distance_m):'',actual.moving_duration_s?duration(actual.moving_duration_s):'',Number.isFinite(Number(actual.pace_s_per_km))?pace(actual.pace_s_per_km):'',Number.isFinite(Number(actual.speed_kmh))?speed(actual.speed_kmh):''].filter(Boolean).join(' · '):'—';const comparison=step.target_comparison;const result=comparison?.within_target===true?'Dentro da faixa':comparison?.within_target===false?'Fora da faixa':'—';const repetition=Number(step.repeat_count)>1?` ${step.repeat_index}/${step.repeat_count}`:'';return `<tr><th><span>${escapeHtml(step.name||step.type)}${escapeHtml(repetition)}</span><small>${escapeHtml(({warmup:'Aquecimento',work:'Trabalho',recovery:'Recuperação',cooldown:'Desaquecimento',interval_group:'Intervalo'}[step.type]||step.type))}</small></th><td>${escapeHtml(planned)}</td><td>${escapeHtml(actualText)}</td><td>${escapeHtml(targetLabel(step.target))}</td><td>${escapeHtml(result)}</td></tr>`}).join('')}</tbody></table></div><small class="activity-v3-table-note">Blocos sem duração ou distância delimitável não recebem métricas realizadas.</small></section>`
    }
    const loadAnalysis = async (state,panel) => {
        const [analysisResult,plannedResult]=await Promise.allSettled([getAnalysis(state),getPlannedActual(state)])
        if(analysisResult.status==='rejected') {
            panel.innerHTML=`<div class="activity-v3-error"><strong>Não foi possível carregar a análise.</strong><span>${escapeHtml(analysisResult.reason?.message||'Tente novamente.')}</span><button type="button" class="activity-secondary-button" data-retry-tab="analysis">Tentar novamente</button></div>`
            return 'error'
        }
        panel.innerHTML=analysisHtml(analysisResult.value)+(plannedResult.status==='fulfilled'?plannedActualHtml(plannedResult.value):'')
        return 'loaded'
    }
    const activateTab = async (state,id,{force=false}={}) => {
        state.root.querySelectorAll('[data-activity-v3-tab]').forEach(button=>{
            const active=button.dataset.activityV3Tab===id
            button.setAttribute('aria-selected',active?'true':'false')
            button.tabIndex=active?0:-1
        })
        state.root.querySelectorAll('[data-activity-v3-panel]').forEach(panel=>{panel.hidden=panel.dataset.activityV3Panel!==id})
        const panel=state.root.querySelector(`[data-activity-v3-panel="${CSS.escape(id)}"]`)
        if(!panel)return
        const status=state.tabStates.get(id)||'idle'
        if(!force && (status==='loading'||status==='loaded'||status==='empty'))return
        state.tabStates.set(id,'loading')
        if(!panel.hasChildNodes() || force) panel.innerHTML=`<div class="activity-v3-lazy"><span></span><strong>Carregando ${escapeHtml(id==='charts'?'gráficos':id==='splits'?'splits':id==='laps'?'voltas':id==='zones'?'zonas':'análise')}…</strong></div>`
        let result='loaded'
        if(id==='charts')result=await loadCharts(state,panel,state.streamData?.axis||'distance',{force})
        if(id==='splits')result=await loadSplits(state,panel,1000)
        if(id==='laps')result=await loadLaps(state,panel)
        if(id==='zones')result=await loadZones(state,panel)
        if(id==='analysis')result=await loadAnalysis(state,panel)
        state.tabStates.set(id,result||'loaded')
        panel.querySelectorAll('[data-retry-tab]').forEach(button=>button.addEventListener('click',()=>activateTab(state,button.dataset.retryTab,{force:true})))
    }
    const mountMap = async state => {
        const element = state.root.querySelector('[data-activity-v3-map]')
        const status = state.root.querySelector('[data-activity-v3-map-status]')
        const routes = routeCollections(state.activity)
        if (!element || !routes.length) return
        const mount = async () => {
            if (!element.isConnected || element.clientWidth <= 0 || element.clientHeight <= 0) return
            state.visibilityObserver?.disconnect?.()
            state.visibilityObserver = null
            try {
                state.mapController = await window.StrideBRWebMap?.mountMany?.(element, routes, {controls:true,maxZoom:17})
                if (status) status.hidden = true
            } catch (error) {
                if (status) {
                    status.hidden = false
                    status.textContent = 'Mapa indisponível. A rota continua disponível nos dados da atividade.'
                }
            }
        }
        if (window.IntersectionObserver) {
            state.visibilityObserver = new IntersectionObserver(entries => {
                if (entries.some(entry => entry.isIntersecting)) mount()
            }, {threshold: .01})
            state.visibilityObserver.observe(element)
            return
        }
        await mount()
    }
    const destroy = content => {
        const state = states.get(content)
        if (!state) return
        state.destroyed=true
        state.visibilityObserver?.disconnect?.()
        state.mapController?.destroy?.()
        state.controllers.forEach(controller=>controller.abort())
        state.controllers.clear()
        states.delete(content)
    }
    const render = ({content,activity,mode = 'preview'}) => {
        if (!content || !activity) return false
        destroy(content)
        content.innerHTML = shellHtml(activity, mode)
        content.hidden = false
        const root = content.querySelector('[data-activity-v3]')
        if (!root) return false
        if (mode === 'detail') {
            bindStatsToggle(content,activity)
            bindRouteSave(content,activity)
        }
        const state = {root,activity,mode,tabStates:new Map(mode === 'detail' ? [['summary','loaded']] : []),controllers:new Map(),cache:new Map(),chartMetrics:new Set(),analysisPromise:null,plannedActualPromise:null,mapController:null,visibilityObserver:null,streamData:null,destroyed:false}
        states.set(content,state)
        if (mode === 'detail') {
            root.querySelectorAll('[data-activity-v3-tab]').forEach(button => {
                button.addEventListener('click', () => activateTab(state,button.dataset.activityV3Tab))
                button.addEventListener('keydown',event=>{
                    if(!['ArrowLeft','ArrowRight'].includes(event.key))return
                    const tabs=Array.from(root.querySelectorAll('[data-activity-v3-tab]'))
                    const index=tabs.indexOf(button)
                    const next=event.key==='ArrowRight'?(index+1)%tabs.length:(index-1+tabs.length)%tabs.length
                    event.preventDefault()
                    tabs[next]?.focus()
                    tabs[next]?.click()
                })
            })
        }
        requestAnimationFrame(() => mountMap(state))
        return true
    }
    window.StrideBRActivityDetailV3=Object.freeze({render,destroy})
})()
