(() => {
    const pacerChartGeometry=(rawSegments,rawDistance,rawRules={})=>{
        const chart={width:800,height:220,left:52,right:780,top:24,bottom:176}
        const totalDistance=Number(rawDistance)
        if(!Number.isFinite(totalDistance)||totalDistance<=0)return null
        const clamp=(value,min,max)=>Math.min(max,Math.max(min,value))
        const segments=(Array.isArray(rawSegments)?rawSegments:[]).map(segment=>({
            start:Number(segment?.start_distance_m),
            end:Number(segment?.end_distance_m),
            pace:Number(segment?.target_pace_s_per_km),
            tolerance:Math.max(0,Number(segment?.tolerance_s_per_km)||0)
        })).filter(segment=>Number.isFinite(segment.start)&&Number.isFinite(segment.end)&&Number.isFinite(segment.pace)&&segment.pace>0&&segment.end>=segment.start).sort((a,b)=>a.start-b.start)
        if(!segments.length)return null
        const plotWidth=chart.right-chart.left
        const plotHeight=chart.bottom-chart.top
        const paces=segments.flatMap(segment=>[segment.pace-segment.tolerance,segment.pace+segment.tolerance])
        let minPace=Math.min(...paces)
        let maxPace=Math.max(...paces)
        const naturalRange=maxPace-minPace
        const minimumSpan=Math.max(16,Math.max(...segments.map(segment=>segment.pace))*.055)
        const padding=Math.max(5,naturalRange*.14)
        if(naturalRange<minimumSpan){const center=(minPace+maxPace)/2;minPace=center-minimumSpan/2;maxPace=center+minimumSpan/2}
        minPace-=padding
        maxPace+=padding
        const paceRange=Math.max(1,maxPace-minPace)
        const xAt=distance=>clamp(chart.left+clamp(distance,0,totalDistance)/totalDistance*plotWidth,chart.left,chart.right)
        const yAt=pace=>clamp(chart.top+(pace-minPace)/paceRange*plotHeight,chart.top,chart.bottom)
        const points=[]
        segments.forEach((segment,index)=>{
            const startX=xAt(segment.start)
            const endX=xAt(segment.end)
            const y=yAt(segment.pace)
            if(index===0)points.push([startX,y])
            else if(points.at(-1)?.[0]!==startX)points.push([startX,points.at(-1)?.[1]??y])
            points.push([endX,y])
            const next=segments[index+1]
            if(next)points.push([endX,yAt(next.pace)])
        })
        const finalPhase=rawRules?.final_phase||{}
        const configuredMin=Number(finalPhase.min_distance_m)
        const minDistance=Number.isFinite(configuredMin)&&configuredMin>=0?configuredMin:150
        const configuredMax=Number(finalPhase.max_distance_m)
        const maxDistance=Math.max(minDistance,Number.isFinite(configuredMax)&&configuredMax>0?configuredMax:1000)
        const configuredPercent=Number(finalPhase.percent)
        const percent=Number.isFinite(configuredPercent)&&configuredPercent>=0?configuredPercent:10
        const finalDistance=clamp(Math.min(maxDistance,Math.max(minDistance,totalDistance*percent/100)),0,totalDistance)
        const finalStartX=xAt(totalDistance-finalDistance)
        return {
            chart,
            plot:{x:chart.left,y:chart.top,width:plotWidth,height:plotHeight,right:chart.right,bottom:chart.bottom},
            points:points.map(([x,y])=>`${x.toFixed(2)},${y.toFixed(2)}`).join(' '),
            finalBand:{x:finalStartX,y:chart.top,width:chart.right-finalStartX,height:plotHeight},
            totalDistance,segments,minPace,maxPace,
        }
    }
    const pacerPointerRatio=(svg,clientX,clientY,plot)=>{
        const matrix=svg?.getScreenCTM?.()
        if(!matrix)return null
        try{
            const point=svg.createSVGPoint()
            point.x=clientX;point.y=clientY
            const local=point.matrixTransform(matrix.inverse())
            return Number.isFinite(local.x)?Math.max(0,Math.min(1,(local.x-plot.x)/plot.width)):null
        }catch(_){return null}
    }
    const pacerChartInspector=(chart,distance)=>{
        if(!chart)return null
        const d=Math.max(0,Math.min(chart.totalDistance,Number(distance)||0))
        const index=Math.max(0,chart.segments.findIndex((segment,index)=>d>=segment.start&&(d<segment.end||index===chart.segments.length-1)))
        const segment=chart.segments[index]||chart.segments.at(-1)
        const targetElapsed=chart.segments.reduce((total,item)=>total+Math.max(0,Math.min(d,item.end)-item.start)/1000*item.pace,0)
        return {distance:d,targetElapsed,segment,index,finalPhase:d>=chart.totalDistance-(chart.finalBand.width/chart.plot.width*chart.totalDistance)}
    }
    if(typeof globalThis!=='undefined'&&globalThis.__stridebrPacerChartTest){globalThis.__stridebrPacerChartTest.pointerRatio=pacerPointerRatio;globalThis.__stridebrPacerChartTest.geometry=pacerChartGeometry;globalThis.__stridebrPacerChartTest.inspector=pacerChartInspector}
    const root=document.querySelector('[data-pacer-page]')
    const form=root?.querySelector('[data-pacer-form]')
    if(!root||!form)return
    const strategy=form.querySelector('[data-pacer-strategy]')
    const generated=form.querySelector('[data-generated-options]')
    const custom=form.querySelector('[data-custom-segments]')
    const rows=form.querySelector('[data-pacer-segments]')
    const preview=form.querySelector('[data-pacer-preview]')
    const helpDialog=root.querySelector('[data-pacer-help-dialog]')
    const helpClose=helpDialog?.querySelector('[data-pacer-help-close]')
    let helpReturnFocus=null
    let timer=0
    let request=0
    let simulationDistance=null
    const escape=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]))
    const seconds=value=>{
        const raw=String(value??'').trim()
        if(!raw)return null
        if(/^\d+(?:\.\d+)?$/.test(raw))return Number(raw)
        const parts=raw.split(':').map(Number)
        if(parts.some(value=>!Number.isFinite(value)||value<0))return null
        if(parts.length===2&&parts[1]<60)return parts[0]*60+parts[1]
        if(parts.length===3&&parts[1]<60&&parts[2]<60)return parts[0]*3600+parts[1]*60+parts[2]
        return null
    }
    const pace=value=>{
        const total=Math.round(Number(value))
        if(!Number.isFinite(total)||total<=0)return '—'
        return `${Math.floor(total/60)}:${String(total%60).padStart(2,'0')}/km`
    }
    const clock=value=>{
        const total=Math.round(Number(value))
        if(!Number.isFinite(total)||total<0)return '—'
        const h=Math.floor(total/3600),m=Math.floor(total%3600/60),s=total%60
        return h?`${h}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`:`${m}:${String(s).padStart(2,'0')}`
    }
    const openHelp=section=>{
        if(!helpDialog)return
        helpReturnFocus=document.activeElement instanceof HTMLElement?document.activeElement:null
        if(typeof helpDialog.showModal==='function'){
            if(!helpDialog.open)helpDialog.showModal()
        }else helpDialog.setAttribute('open','')
        const target=helpDialog.querySelector(`[data-pacer-help-section="${section||'goal'}"]`)||helpDialog.querySelector('[data-pacer-help-section]')
        window.requestAnimationFrame(()=>{
            target?.scrollIntoView({block:'nearest'})
            helpClose?.focus()
        })
    }
    const closeHelp=()=>{
        if(!helpDialog)return
        if(typeof helpDialog.close==='function'&&helpDialog.open)helpDialog.close()
        else{helpDialog.removeAttribute('open');helpReturnFocus?.focus();helpReturnFocus=null}
    }
    root.querySelectorAll('[data-pacer-help-open]').forEach(button=>button.addEventListener('click',()=>openHelp(button.dataset.pacerHelpOpen||'goal')))
    helpClose?.addEventListener('click',closeHelp)
    helpDialog?.addEventListener('click',event=>{if(event.target===helpDialog)closeHelp()})
    helpDialog?.addEventListener('close',()=>{helpReturnFocus?.focus();helpReturnFocus=null})
    const validateInputs=()=>{
        const time=form.elements.namedItem('target_time'),floor=form.elements.namedItem('heart_rate_floor_bpm'),ceiling=form.elements.namedItem('heart_rate_ceiling_bpm')
        if(time)time.setCustomValidity(String(time.value||'').trim()!==''&&(!Number.isFinite(seconds(time.value))||seconds(time.value)<=0)?'Informe um tempo válido, como 52:00 ou 1:02:30.':'')
        ;[floor,ceiling].forEach(input=>{if(!input)return;const value=String(input.value||'').trim();input.setCustomValidity(value!==''&&(!Number.isFinite(Number(value))||Number(value)<20||Number(value)>260)?'Informe um valor entre 20 e 260 bpm.':'')})
        if(floor&&ceiling&&String(floor.value||'')!==''&&String(ceiling.value||'')!==''&&Number(floor.value)>Number(ceiling.value))ceiling.setCustomValidity('A FC mínima não pode ser maior que a FC máxima.')
    }
    const syncMode=()=>{
        const current=String(strategy?.value||'even')
        const isCustom=current==='custom'
        if(custom)custom.hidden=!isCustom
        if(generated)generated.hidden=isCustom
        form.querySelectorAll('[data-progressive-option]').forEach(field=>{field.hidden=!(current==='negative_split'||current==='positive_split')})
        form.querySelectorAll('[data-global-hr]').forEach(field=>{field.hidden=isCustom})
        if(isCustom&&!rows?.children.length)addRow()
    }
    const addRow=(values={})=>{
        if(!rows)return
        const index=rows.children.length
        const row=document.createElement('div')
        row.className='pacer-segment-row'
        row.dataset.pacerSegment='1'
        row.innerHTML=`<input type="number" step="0.1" name="segments[${index}][start_km]" value="${escape(values.start_km??'')}" aria-label="Início em km"><input type="number" step="0.1" name="segments[${index}][end_km]" value="${escape(values.end_km??'')}" aria-label="Fim em km"><input type="text" name="segments[${index}][pace]" value="${escape(values.pace??'')}" placeholder="5:10" aria-label="Pace alvo"><input type="number" name="segments[${index}][tolerance]" value="${escape(values.tolerance??form.elements.namedItem('tolerance_s_per_km')?.value??10)}" aria-label="Tolerância"><input type="number" name="segments[${index}][hr_floor]" value="${escape(values.hr_floor??'')}" aria-label="FC mínima"><input type="number" name="segments[${index}][hr_ceiling]" value="${escape(values.hr_ceiling??'')}" aria-label="FC máxima"><button type="button" data-remove-pacer-segment aria-label="Remover segmento">×</button>`
        rows.appendChild(row)
    }
    const reindex=()=>rows?.querySelectorAll('[data-pacer-segment]').forEach((row,index)=>row.querySelectorAll('[name]').forEach(input=>{input.name=input.name.replace(/segments\[\d+\]/,`segments[${index}]`)}))
    const payload=()=>{
        const value=name=>form.elements.namedItem(name)?.value??''
        const data={
            sport:value('sport'),
            target_distance_m:Number(String(value('target_distance_km')).replace(',','.'))*1000,
            target_time_s:seconds(value('target_time')),
            goal_mode:value('goal_mode')||'target_time',
            clock_mode:value('clock_mode')||'auto',
            strategy:value('strategy'),
            tolerance_s_per_km:Number(value('tolerance_s_per_km')||10),
            constraints:{
                progression_percent:Number(value('progression_percent')||8),
                segment_distance_m:Number(value('segment_distance_m')||2000)
            }
        }
        const floor=value('heart_rate_floor_bpm'),ceiling=value('heart_rate_ceiling_bpm')
        if(floor!=='')data.constraints.heart_rate_floor_bpm=Number(floor)
        if(ceiling!=='')data.constraints.heart_rate_ceiling_bpm=Number(ceiling)
        if(data.strategy==='custom')data.segments=[...rows.querySelectorAll('[data-pacer-segment]')].map(row=>{
            const fields=row.querySelectorAll('input')
            const get=suffix=>[...fields].find(input=>input.name.endsWith(`[${suffix}]`))?.value??''
            return {basis:'distance',start_distance_m:Number(String(get('start_km')).replace(',','.'))*1000,end_distance_m:Number(String(get('end_km')).replace(',','.'))*1000,target_pace_s_per_km:seconds(get('pace')),tolerance_s_per_km:Number(get('tolerance')||data.tolerance_s_per_km),heart_rate_floor_bpm:get('hr_floor')===''?null:Number(get('hr_floor')),heart_rate_ceiling_bpm:get('hr_ceiling')===''?null:Number(get('hr_ceiling'))}
        })
        return data
    }
    const renderPreview=data=>{
        const segments=Array.isArray(data?.segments)?data.segments:[]
        let cumulative=0
        const chart=pacerChartGeometry(segments,data.target_distance_m,data.guidance_rules)
        if(!chart){preview.innerHTML='<div class="pacer-preview-error"><strong>Revise a estratégia.</strong><span>Não foi possível desenhar a curva de pace.</span></div>';return}
        const plot=chart.plot,band=chart.finalBand
        const maxDistance=Number(data.target_distance_m);simulationDistance=Number.isFinite(simulationDistance)?Math.max(0,Math.min(maxDistance,simulationDistance)):maxDistance*.5
        preview.innerHTML=`<div class="pacer-preview-summary"><div><span>Distância</span><strong>${(Number(data.target_distance_m)/1000).toLocaleString('pt-BR',{maximumFractionDigits:2})} km</strong></div><div><span>${data.guidance_rules?.goal_mode==='best_effort'?'Referência':'Meta'}</span><strong>${clock(data.target_time_s)}</strong></div><div><span>Pace médio</span><strong>${pace(data.target_average_pace_s_per_km)}</strong></div></div><div class="pacer-preview-chart" aria-label="Curva de pace da estratégia"><div class="pacer-chart-label">PACE · mais rápido acima</div><div class="pacer-chart-frame" data-pacer-chart-frame><svg class="pacer-plan-chart" viewBox="0 0 ${chart.chart.width} ${chart.chart.height}" preserveAspectRatio="xMidYMid meet" role="img" aria-labelledby="pacer-chart-title pacer-chart-description"><title id="pacer-chart-title">Perfil de pace do plano</title><desc id="pacer-chart-description">Passe ou arraste horizontalmente para consultar um ponto do plano. Pace mais rápido aparece acima.</desc><defs><clipPath id="pacer-plan-plot-clip"><rect x="${plot.x}" y="${plot.y}" width="${plot.width}" height="${plot.height}"></rect></clipPath></defs><rect class="pacer-plot-outline" x="${plot.x}" y="${plot.y}" width="${plot.width}" height="${plot.height}"></rect><g clip-path="url(#pacer-plan-plot-clip)"><rect class="pacer-final-band" x="${band.x}" y="${band.y}" width="${band.width}" height="${band.height}"></rect><polyline class="pacer-plan-line" points="${chart.points}" fill="none" vector-effect="non-scaling-stroke"></polyline><line class="pacer-chart-cursor" x1="${plot.x}" x2="${plot.x}" y1="${plot.y}" y2="${plot.bottom}" data-pacer-chart-cursor></line></g></svg></div><div class="pacer-chart-axis"><span>0</span><span>${(Number(data.target_distance_m)/2000).toLocaleString('pt-BR',{maximumFractionDigits:1})}</span><span>${(Number(data.target_distance_m)/1000).toLocaleString('pt-BR',{maximumFractionDigits:1})} km</span></div></div><div class="pacer-chart-inspector" data-pacer-chart-inspector aria-live="polite"></div><div class="pacer-preview-table"><div class="pacer-preview-row is-head"><span>Trecho</span><span>Alvo</span><span>Faixa</span><span>Acumulado</span></div>${segments.map(segment=>{const segmentTime=(segment.end_distance_m-segment.start_distance_m)/1000*segment.target_pace_s_per_km;cumulative+=segmentTime;return `<div class="pacer-preview-row"><strong>${(segment.start_distance_m/1000).toLocaleString('pt-BR',{maximumFractionDigits:2})}–${(segment.end_distance_m/1000).toLocaleString('pt-BR',{maximumFractionDigits:2})} km</strong><span>${escape(pace(segment.target_pace_s_per_km))}</span><span>${escape(pace(segment.target_pace_s_per_km-segment.tolerance_s_per_km))}–${escape(pace(segment.target_pace_s_per_km+segment.tolerance_s_per_km))}</span><span>${escape(clock(cumulative))}</span></div>`}).join('')}</div>`
        const frame=preview.querySelector('[data-pacer-chart-frame]'),inspector=preview.querySelector('[data-pacer-chart-inspector]'),cursor=preview.querySelector('[data-pacer-chart-cursor]')
        const updateInspector=(clientX,clientY)=>{const ratio=pacerPointerRatio(frame?.querySelector('svg'),clientX,clientY,plot);if(ratio===null)return;const item=pacerChartInspector(chart,ratio*maxDistance);if(!item)return;simulationDistance=item.distance;const x=plot.x+ratio*plot.width;cursor?.setAttribute('x1',String(x));cursor?.setAttribute('x2',String(x));const segment=item.segment;inspector.innerHTML=`<strong>${(item.distance/1000).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2})} km · ${clock(item.targetElapsed)}</strong><div><span>Alvo <b>${pace(segment.pace)}</b></span><span>Faixa <b>${pace(segment.pace-segment.tolerance)}–${pace(segment.pace+segment.tolerance)}</b></span><span>Trecho <b>${item.index+1} de ${chart.segments.length}</b></span>${item.finalPhase?'<span>Fase <b>Final</b></span>':''}</div>`}
        frame?.addEventListener('pointermove',event=>{if(event.pointerType==='mouse'||event.buttons)updateInspector(event.clientX,event.clientY)})
        frame?.addEventListener('pointerdown',event=>{frame.setPointerCapture?.(event.pointerId);updateInspector(event.clientX,event.clientY)})
        updateInspector((frame?.getBoundingClientRect().left||0)+(frame?.getBoundingClientRect().width||0)*simulationDistance/maxDistance)
    }
    const updatePreview=async()=>{
        const current=++request
        const data=payload()
        if(!Number.isFinite(data.target_distance_m)||!Number.isFinite(data.target_time_s)||data.target_distance_m<=0||data.target_time_s<=0){preview.innerHTML='<div class="pacer-preview-empty"><strong>Prévia da estratégia</strong><span>Preencha distância e tempo-alvo.</span></div>';return}
        const body=new URLSearchParams({csrf_token:root.dataset.csrf||'',payload:JSON.stringify(data)})
        try{
            const response=await fetch('/api/pacer-preview.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','Accept':'application/json'},credentials:'same-origin',body})
            const result=await response.json().catch(()=>null)
            if(current!==request)return
            if(!response.ok||!result?.ok)throw new Error(result?.error||'Não foi possível gerar a prévia.')
            renderPreview(result.data)
        }catch(error){if(current===request)preview.innerHTML=`<div class="pacer-preview-error"><strong>Revise a estratégia.</strong><span>${escape(error.message)}</span></div>`}
    }
    const schedule=()=>{clearTimeout(timer);timer=setTimeout(updatePreview,220)}
    form.addEventListener('input',event=>{
        validateInputs()
        if(event.target.matches('[name="progression_percent"],[name="segment_distance_m"],[name="heart_rate_floor_bpm"],[name="heart_rate_ceiling_bpm"]')){form.dataset.generatedOptionsChanged='1';const marker=form.querySelector('[data-generated-options-changed]');if(marker)marker.value='1'}
        schedule()
    })
    form.addEventListener('change',event=>{if(event.target===strategy)syncMode();validateInputs();schedule()})
    form.addEventListener('change',event=>{if(event.target.name==='goal_mode'){const best=event.target.value==='best_effort';form.querySelector('[data-target-time-label]').textContent=best?'Tempo de referência':'Tempo-alvo';const note=form.parentElement.querySelector('[data-best-effort-explainer]');if(note)note.hidden=!best}schedule()})
    form.querySelector('[data-add-pacer-segment]')?.addEventListener('click',()=>{addRow();schedule()})
    rows?.addEventListener('click',event=>{const button=event.target.closest('[data-remove-pacer-segment]');if(!button)return;button.closest('[data-pacer-segment]')?.remove();reindex();schedule()})
    syncMode()
    validateInputs()
    schedule()
})()
