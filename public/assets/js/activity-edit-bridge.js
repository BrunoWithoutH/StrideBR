(() => {
    const result = String(document.body?.dataset.activityEditResult || '')
    const id = String(document.body?.dataset.activityId || '')
    if (result !== 'saved' && result !== 'deleted') return
    window.parent.postMessage({type: `stridebr:activity-edit-${result}`, id}, window.location.origin)
})()
