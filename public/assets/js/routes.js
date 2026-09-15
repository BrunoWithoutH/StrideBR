(() => {
    const root = document.querySelector('[data-routes-page]')
    if (!root || !window.StrideBRWebMap) return
    const dataNode = root.querySelector('[data-routes-map-data]')
    let routes = []
    try { routes = JSON.parse(dataNode?.textContent || '[]') } catch (_) { routes = [] }
    const map = new Map(routes.map(route => [String(route.id || ''), Array.isArray(route.coordinates) ? route.coordinates : []]))
    const mount = element => {
        if (!element || element.dataset.routeMounted === '1') return
        const id = element.dataset.routeMiniMap || element.dataset.routeDetailMap || ''
        const coordinates = map.get(id) || []
        if (coordinates.length < 2) return
        element.dataset.routeMounted = '1'
        const detail = Boolean(element.dataset.routeDetailMap)
        window.StrideBRWebMap.mount(element, coordinates, {controls: detail, zoomControl: detail, scrollWheelZoom: false, weight: detail ? 5 : 4, padding: detail ? [28, 28] : [10, 10], maxZoom: detail ? 16 : 14}).catch(() => { element.dataset.routeMounted = '0' })
    }
    root.querySelectorAll('[data-route-detail-map]').forEach(mount)
    const minis = [...root.querySelectorAll('[data-route-mini-map]')]
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => entries.forEach(entry => { if (entry.isIntersecting) { mount(entry.target); observer.unobserve(entry.target) } }), {rootMargin: '180px'})
        minis.forEach(element => observer.observe(element))
    } else minis.slice(0, 8).forEach(mount)
})()
