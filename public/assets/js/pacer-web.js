(() => {
    const root=document.querySelector('[data-pacer-page]')
    const form=root?.querySelector('[data-pacer-form]')
    if(!root||!form)return
    const strategy=form.querySelector('[data-pacer-strategy]')
    const generated=form.querySelector('[data-generated-options]')
    const custom=form.querySelector('[data-custom-segments]')
    const rows=form.querySelector('[data-pacer-segments]')
    const preview=form.querySelector('[data-pacer-preview]')
    let timer=0
    let request=0
    const escape=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]))
    const seconds=value=>{
        const raw=String(value??'').trim()
        if(!raw)return null
        if(/^\d+(?:\.\d+)?$/.test(raw))return Number(raw)
        const parts=raw.split(':').map(Number)
        if(parts.some(value=>!Number.isFinite(value)))return null
        if(parts.length===2)return parts[0]*60+parts[1]
        if(parts.length===3)return parts[0]*3600+parts[1]*60+parts[2]
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
        preview.innerHTML=`<div class="pacer-preview-summary"><div><span>Distância</span><strong>${(Number(data.target_distance_m)/1000).toLocaleString('pt-BR',{maximumFractionDigits:2})} km</strong></div><div><span>Meta</span><strong>${clock(data.target_time_s)}</strong></div><div><span>Pace médio</span><strong>${pace(data.target_average_pace_s_per_km)}</strong></div></div><div class="pacer-preview-chart" aria-label="Perfil de pace da estratégia">${segments.map(segment=>{const width=(segment.end_distance_m-segment.start_distance_m)/data.target_distance_m*100;const p=Number(segment.target_pace_s_per_km);return `<i style="--segment-width:${Math.max(2,width)}%;--segment-height:${Math.max(14,Math.min(66,14+(420-Math.min(Math.max(60,p),420))*.22))}px" title="${escape(pace(p))}"></i>`}).join('')}</div><div class="pacer-preview-table"><div class="pacer-preview-row is-head"><span>Trecho</span><span>Pace</span><span>Faixa</span><span>Acumulado</span></div>${segments.map(segment=>{const segmentTime=(segment.end_distance_m-segment.start_distance_m)/1000*segment.target_pace_s_per_km;cumulative+=segmentTime;return `<div class="pacer-preview-row"><strong>${(segment.start_distance_m/1000).toLocaleString('pt-BR',{maximumFractionDigits:2})}–${(segment.end_distance_m/1000).toLocaleString('pt-BR',{maximumFractionDigits:2})} km</strong><span>${escape(pace(segment.target_pace_s_per_km))}</span><span>±${escape(String(Math.round(segment.tolerance_s_per_km)))} s/km</span><span>${escape(clock(cumulative))}</span></div>`}).join('')}</div>`
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
        if(event.target.matches('[name="progression_percent"],[name="segment_distance_m"],[name="heart_rate_floor_bpm"],[name="heart_rate_ceiling_bpm"]')){form.dataset.generatedOptionsChanged='1';const marker=form.querySelector('[data-generated-options-changed]');if(marker)marker.value='1'}
        schedule()
    })
    form.addEventListener('change',event=>{if(event.target===strategy)syncMode();schedule()})
    form.querySelector('[data-add-pacer-segment]')?.addEventListener('click',()=>{addRow();schedule()})
    rows?.addEventListener('click',event=>{const button=event.target.closest('[data-remove-pacer-segment]');if(!button)return;button.closest('[data-pacer-segment]')?.remove();reindex();schedule()})
    syncMode()
    schedule()
})()
