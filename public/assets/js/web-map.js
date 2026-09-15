(() => {
    let leafletPromise = null
    const states = new WeakMap()
    const ensureLeaflet = () => {
        if (window.L) return Promise.resolve(window.L)
        if (leafletPromise) return leafletPromise
        leafletPromise = new Promise((resolve, reject) => {
            let settled = false
            const finish = (ok, value) => {
                if (settled) return
                settled = true
                window.clearTimeout(timer)
                ok ? resolve(value) : reject(value)
            }
            const timer = window.setTimeout(() => finish(false, new Error('O mapa demorou demais para carregar.')), 8000)
            if (!document.querySelector('link[data-stridebr-leaflet]')) {
                const link = document.createElement('link')
                link.rel = 'stylesheet'
                link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
                link.crossOrigin = 'anonymous'
                link.dataset.stridebrLeaflet = '1'
                document.head.appendChild(link)
            }
            const existing = document.querySelector('script[data-stridebr-leaflet]')
            if (existing) {
                existing.addEventListener('load', () => finish(true, window.L), {once: true})
                existing.addEventListener('error', () => finish(false, new Error('Mapa indisponível.')), {once: true})
                return
            }
            const script = document.createElement('script')
            script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
            script.crossOrigin = 'anonymous'
            script.dataset.stridebrLeaflet = '1'
            script.onload = () => finish(true, window.L)
            script.onerror = () => finish(false, new Error('Mapa indisponível.'))
            document.head.appendChild(script)
        }).catch(error => {
            leafletPromise = null
            throw error
        })
        return leafletPromise
    }
    const normalizeCoordinates = coordinates => (Array.isArray(coordinates) ? coordinates : []).map(point => {
        if (!Array.isArray(point) || point.length < 2) return null
        const longitude = Number(point[0])
        const latitude = Number(point[1])
        return Number.isFinite(longitude) && Number.isFinite(latitude) ? [latitude, longitude] : null
    }).filter(Boolean)
    const routeColor = () => getComputedStyle(document.documentElement).getPropertyValue('--ui-route').trim() || '#4f72df'
    const splitSegments = (latLngs, breakIndices) => {
        const breaks = new Set((Array.isArray(breakIndices) ? breakIndices : []).map(value => Number(value)).filter(Number.isInteger))
        if (!breaks.size) return latLngs.length ? [latLngs] : []
        const segments = []
        let current = []
        latLngs.forEach((point, index) => {
            if (index > 0 && breaks.has(index) && current.length) {
                segments.push(current)
                current = []
            }
            current.push(point)
        })
        if (current.length) segments.push(current)
        return segments.filter(segment => segment.length >= 2)
    }
    const destroy = element => {
        const state = states.get(element)
        if (!state) return
        state.resizeObserver?.disconnect?.()
        state.basemap?.destroy?.()
        state.map?.remove?.()
        states.delete(element)
        element.replaceChildren()
    }
    const mount = async (element, coordinates, options = {}) => {
        if (!element) return null
        destroy(element)
        const latLngs = normalizeCoordinates(coordinates)
        if (latLngs.length < 2) return null
        const L = await ensureLeaflet()
        element.replaceChildren()
        const map = L.map(element, {
            scrollWheelZoom: Boolean(options.scrollWheelZoom),
            zoomControl: options.zoomControl !== false,
            attributionControl: true,
            keyboard: true
        })
        const basemap = window.StrideBRBasemaps?.attach?.(map, {initial: 'street', remember: true, controls: options.controls !== false}) || null
        const state = {map, basemap, resizeObserver: null, latLngs, routeLayers: []}
        states.set(element, state)
        const redraw = breakIndices => {
            state.routeLayers.forEach(layer => layer.remove())
            state.routeLayers = []
            splitSegments(latLngs, breakIndices).forEach(segment => {
                const layer = L.polyline(segment, {
                    color: routeColor(),
                    weight: options.weight || 5,
                    opacity: .96,
                    lineCap: 'round',
                    lineJoin: 'round'
                }).addTo(map)
                state.routeLayers.push(layer)
            })
        }
        redraw(options.breakIndices || [])
        const start = latLngs[0]
        const finish = latLngs[latLngs.length - 1]
        L.circleMarker(start, {radius: 6, weight: 3, color: routeColor(), fillColor: '#ffffff', fillOpacity: 1}).bindTooltip('Início', {direction: 'top'}).addTo(map)
        L.circleMarker(finish, {radius: 6, weight: 3, color: '#111827', fillColor: '#ffffff', fillOpacity: 1}).bindTooltip('Fim', {direction: 'top'}).addTo(map)
        const bounds = L.latLngBounds(latLngs)
        const fit = () => {
            map.invalidateSize({pan: false})
            map.fitBounds(bounds, {padding: options.padding || [24, 24], maxZoom: options.maxZoom || 16, animate: false})
        }
        requestAnimationFrame(fit)
        window.setTimeout(fit, 120)
        if (window.ResizeObserver) {
            state.resizeObserver = new ResizeObserver(() => map.invalidateSize({pan: false}))
            state.resizeObserver.observe(element)
        }
        return {
            map,
            fit,
            setBreakIndices: breakIndices => redraw(breakIndices),
            destroy: () => destroy(element)
        }
    }
    window.StrideBRWebMap = Object.freeze({ensureLeaflet, mount, destroy})
})()
