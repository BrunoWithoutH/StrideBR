(() => {
    const params = new URLSearchParams(window.location.search)
    const keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term']
    const payload = {path: window.location.pathname}
    let hasAttribution = false
    keys.forEach(key => {
        const value = (params.get(key) || '').trim()
        if (!value) return
        payload[key] = value.slice(0, key === 'utm_content' || key === 'utm_term' ? 120 : 80)
        hasAttribution = true
    })

    let alreadySent = false
    try {
        alreadySent = sessionStorage.getItem('stridebr.marketing.landingSent') === '1'
    } catch (_) {}
    if (alreadySent && !hasAttribution) return

    fetch('/api/marketing-entry.php', {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: true,
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    }).then(response => {
        if (!response.ok) return
        try { sessionStorage.setItem('stridebr.marketing.landingSent', '1') } catch (_) {}
    }).catch(() => {})
})()
