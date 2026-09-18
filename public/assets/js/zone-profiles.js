(() => {
    const helpButton=document.querySelector('[data-zones-page-help]')
    const helpDialog=document.querySelector('[data-zones-help-dialog]')
    helpButton?.addEventListener('click',()=>{
        if(typeof helpDialog?.showModal==='function') helpDialog.showModal()
        else helpDialog?.setAttribute('open','open')
    })
    helpDialog?.addEventListener('close',()=>helpButton?.focus())
    const form=document.querySelector('[data-zone-form]')
    if(!form)return
    const rows=form.querySelector('[data-zone-rows]')
    const type=form.querySelector('[data-zone-type]')
    const unit=document.querySelector('[data-zone-unit]')
    const reindex=()=>{
        Array.from(rows.querySelectorAll('[data-zone-row]')).forEach((row,index)=>{
            row.querySelectorAll('input[name]').forEach(input=>{input.name=input.name.replace(/zones\[\d+\]/,`zones[${index}]`)})
        })
    }
    const syncType=()=>{
        const pace=type.value==='pace'
        if(unit)unit.textContent=pace?'mm:ss/km':'bpm'
        rows.querySelectorAll('[data-zone-bound]').forEach(input=>{
            input.placeholder=pace?'5:30':'160'
            input.inputMode=pace?'text':'numeric'
        })
    }
    const bindRemove=row=>row.querySelector('[data-zone-remove]')?.addEventListener('click',()=>{
        if(rows.querySelectorAll('[data-zone-row]').length<=1)return
        row.remove()
        reindex()
    })
    rows.querySelectorAll('[data-zone-row]').forEach(bindRemove)
    type.addEventListener('change',syncType)
    form.querySelector('[data-zone-add]')?.addEventListener('click',()=>{
        const index=rows.querySelectorAll('[data-zone-row]').length
        if(index>=20)return
        const row=document.createElement('div')
        row.className='zones-row'
        row.dataset.zoneRow='1'
        row.innerHTML=`<input type="text" name="zones[${index}][code]" maxlength="20" required value="Z${index+1}" placeholder="Z${index+1}" aria-label="Código da zona"><input type="text" name="zones[${index}][label]" maxlength="80" placeholder="Nome opcional" aria-label="Nome da zona"><label><span>De</span><input type="text" inputmode="decimal" name="zones[${index}][min]" data-zone-bound="min"></label><label><span>Até</span><input type="text" inputmode="decimal" name="zones[${index}][max]" data-zone-bound="max"></label><button type="button" data-zone-remove aria-label="Remover zona">×</button>`
        rows.appendChild(row)
        bindRemove(row)
        syncType()
        row.querySelector('input')?.focus()
    })
    syncType()
})()
