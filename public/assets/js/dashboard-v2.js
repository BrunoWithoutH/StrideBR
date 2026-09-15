(() => {
    const target = document.querySelector('[data-home-last-map]')
    const data = document.querySelector('[data-home-last-map-data]')
    if (!target || !data || !window.StrideBRWebMap) return
    let mounted = false
    const mount = async () => {
        if (mounted) return
        mounted = true
        let coordinates = []
        try { coordinates = JSON.parse(data.textContent || '[]') } catch (_) { coordinates = [] }
        if (!Array.isArray(coordinates) || coordinates.length < 2) return
        try {
            await window.StrideBRWebMap.mount(target, coordinates, {controls: false, zoomControl: false, scrollWheelZoom: false, maxZoom: 14, weight: 4, padding: [10, 10]})
        } catch (_) {
            target.hidden = true
        }
    }
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            if (!entries.some(entry => entry.isIntersecting)) return
            observer.disconnect()
            mount()
        }, {rootMargin: '180px'})
        observer.observe(target)
    } else mount()
})()
