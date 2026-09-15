(()=>{
    const dataNode=document.getElementById('activity-comparison-v2-data')
    const chart=document.querySelector('[data-comparison-chart]')
    const picker=document.querySelector('[data-comparison-metrics]')
    if(!dataNode||!chart||!picker)return
    let payload={}
    try{payload=JSON.parse(dataNode.textContent||'{}')}catch{return}
    const samples=key=>Array.isArray(payload[key]?.streams?.samples)?payload[key].streams.samples:[]
    const available=[...new Set([...(payload.a?.streams?.available_streams||[]),...(payload.b?.streams?.available_streams||[])])]
    const definitions={pace:{label:'Pace',key:'pace',invert:true},speed:{label:'Velocidade',key:'speed',invert:false},heart_rate:{label:'FC',key:'heart_rate',invert:false},altitude:{label:'Elevação',key:'altitude',invert:false},cadence:{label:'Cadência',key:'cadence',invert:false}}
    const metrics=available.filter(item=>definitions[item])
    let active=metrics[0]||null
    const path=(rows,def,min,max,maxX)=>{
        const points=rows.filter(row=>Number.isFinite(Number(row.x))&&Number.isFinite(Number(row[def.key]))).map(row=>({x:Number(row.x),y:Number(row[def.key]),gap:Number(row.gap_before_ms||0)>0}))
        if(points.length<2)return[]
        const groups=[];let current=[]
        points.forEach(point=>{if(point.gap&&current.length){groups.push(current);current=[]}const x=48+(point.x/Math.max(maxX,1))*820;const ratio=(point.y-min)/Math.max(max-min,.000001);const y=18+(def.invert?ratio:1-ratio)*190;current.push(`${x.toFixed(1)},${y.toFixed(1)}`)})
        if(current.length)groups.push(current)
        return groups
    }
    const render=()=>{
        if(!active){chart.innerHTML='<div class="empty-action-state"><p>Sem séries comparáveis.</p></div>';return}
        const def=definitions[active]
        const a=samples('a'),b=samples('b')
        const vals=[...a,...b].map(row=>Number(row[def.key])).filter(Number.isFinite)
        const xs=[...a,...b].map(row=>Number(row.x)).filter(Number.isFinite)
        if(vals.length<2||!xs.length){chart.innerHTML='<div class="empty-action-state"><p>Sem dados suficientes para esta métrica.</p></div>';return}
        const min=Math.min(...vals),max=Math.max(...vals),maxX=Math.max(...xs)
        const pathsA=path(a,def,min,max,maxX),pathsB=path(b,def,min,max,maxX)
        const format=value=>active==='pace'?`${Math.floor(value/60)}:${String(Math.round(value%60)).padStart(2,'0')}/km`:active==='speed'?`${value.toFixed(1)} km/h`:active==='heart_rate'?`${Math.round(value)} bpm`:active==='altitude'?`${Math.round(value)} m`:`${Math.round(value)}`
        chart.innerHTML=`<svg viewBox="0 0 900 230" preserveAspectRatio="none"><line class="grid" x1="48" y1="18" x2="48" y2="208"/><line class="grid" x1="48" y1="208" x2="868" y2="208"/><text x="4" y="26">${format(def.invert?min:max)}</text><text x="4" y="205">${format(def.invert?max:min)}</text>${pathsA.map(points=>`<polyline class="series-a" points="${points.join(' ')}"/>`).join('')}${pathsB.map(points=>`<polyline class="series-b" points="${points.join(' ')}"/>`).join('')}</svg>`
    }
    metrics.forEach((metric,index)=>{const button=document.createElement('button');button.type='button';button.textContent=definitions[metric].label;if(index===0)button.classList.add('is-active');button.addEventListener('click',()=>{active=metric;picker.querySelectorAll('button').forEach(item=>item.classList.toggle('is-active',item===button));render()});picker.append(button)})
    render()
})()
