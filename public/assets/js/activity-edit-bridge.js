(() => {
    const result = document.querySelector('[data-activity-edit-result]')
    if (result) {
        const type = String(result.dataset.type || '')
        const id = String(result.dataset.id || '')
        if (type) window.parent.postMessage({type, id}, window.location.origin)
        return
    }
    if (!document.body?.classList.contains('activity-edit-embedded')) return
    const post = (type, extra = {}) => window.parent.postMessage({type, ...extra}, window.location.origin)
    post('stridebr:activity-edit-ready')
    window.addEventListener('stridebr:activity-route-subview', event => {
        post(event.detail?.active ? 'stridebr:activity-route-subview-open' : 'stridebr:activity-route-subview-close')
    })
    document.addEventListener('click', event => {
        const cancel = event.target.closest('.activity-back-link,.activity-edit-actions .activity-secondary-button')
        if (!cancel) return
        event.preventDefault()
        post('stridebr:activity-edit-cancel')
    })
})()
