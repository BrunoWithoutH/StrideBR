(() => {
    const root = document.querySelector('[data-routes-page]')
    if (!root) return
    const dataNode = root.querySelector('[data-routes-map-data]')
    let routes = []
    try { routes = JSON.parse(dataNode?.textContent || '[]') } catch (_) { routes = [] }
    const map = new Map(routes.map(route => [String(route.id || ''), Array.isArray(route.coordinates) ? route.coordinates : []]))
    // Saved geometry thumbnails avoid one interactive map per library row.
    const thumbnail = coordinates => {
        const projected = coordinates.filter(point => Array.isArray(point) && Number.isFinite(Number(point[0])) && Number.isFinite(Number(point[1]))).map(point => {
            const latitude = Math.max(-85, Math.min(85, Number(point[1]))) * Math.PI / 180
            return [Number(point[0]) * Math.PI / 180, Math.log(Math.tan(Math.PI / 4 + latitude / 2))]
        })
        if (projected.length < 2) return ''
        const xs = projected.map(point => point[0]), ys = projected.map(point => point[1])
        const minX = xs.reduce((a, b) => Math.min(a, b), Infinity), maxX = xs.reduce((a, b) => Math.max(a, b), -Infinity), minY = ys.reduce((a, b) => Math.min(a, b), Infinity), maxY = ys.reduce((a, b) => Math.max(a, b), -Infinity)
        const width = 160, height = 110, padding = 12
        const scale = Math.min((width - padding * 2) / Math.max(maxX - minX, 1e-9), (height - padding * 2) / Math.max(maxY - minY, 1e-9))
        const centerX = (minX + maxX) / 2, centerY = (minY + maxY) / 2
        const points = projected.filter((_, index) => index % Math.max(1, Math.ceil(projected.length / 2000)) === 0 || index === projected.length - 1).map(([x, y]) => `${(width / 2 + (x - centerX) * scale).toFixed(2)},${(height / 2 - (y - centerY) * scale).toFixed(2)}`).join(' ')
        return `<svg viewBox="0 0 ${width} ${height}" aria-hidden="true"><polyline points="${points}" fill="none" stroke="currentColor" stroke-width="3" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round"></polyline></svg>`
    }
    const mount = element => {
        if (!element || element.dataset.routeMounted === '1') return
        const id = element.dataset.routeMiniMap || element.dataset.routeDetailMap || ''
        const coordinates = map.get(id) || []
        if (coordinates.length < 2) return
        element.dataset.routeMounted = '1'
        const detail = Boolean(element.dataset.routeDetailMap)
        element.innerHTML = thumbnail(coordinates)
        if (!detail || !window.StrideBRWebMap) return
        window.StrideBRWebMap.mount(element, coordinates, {controls: detail, zoomControl: detail, scrollWheelZoom: false, weight: detail ? 5 : 4, padding: detail ? [28, 28] : [10, 10], maxZoom: detail ? 16 : 14}).catch(() => { element.innerHTML = thumbnail(coordinates); element.dataset.routeMounted = '0' })
    }
    root.querySelectorAll('[data-route-detail-map]').forEach(mount)
    const minis = [...root.querySelectorAll('[data-route-mini-map]')]
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => entries.forEach(entry => { if (entry.isIntersecting) { mount(entry.target); observer.unobserve(entry.target) } }), {rootMargin: '180px'})
        minis.forEach(element => observer.observe(element))
    } else minis.slice(0, 8).forEach(mount)
})()
