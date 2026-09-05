(() => {
    const script = document.currentScript
    const arcgisKey = String(script?.dataset.arcgisKey || '').trim()
    const storageKey = 'stridebr.map.basemap'
    const states = new WeakMap()
    const validIds = new Set(['street', 'satellite', 'terrain'])
    const i18n = () => window.StrideBRI18n || null
    const t = (key, fallback) => i18n()?.t?.(key, {}, fallback) || fallback
    const definitions = () => ({
        street: {
            id: 'street',
            available: true,
            url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            options: {
                maxZoom: 23,
                maxNativeZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'
            }
        },
        satellite: {
            id: 'satellite',
            available: arcgisKey !== '',
            url: `https://ibasemaps-api.arcgis.com/arcgis/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}?token=${encodeURIComponent(arcgisKey)}`,
            options: {
                maxZoom: 23,
                attribution: 'Powered by <a href="https://www.esri.com/" target="_blank" rel="noopener">Esri</a> | Esri, Maxar, Earthstar Geographics, GIS User Community'
            }
        },
        terrain: {
            id: 'terrain',
            available: arcgisKey !== '',
            url: `https://ibasemaps-api.arcgis.com/arcgis/rest/services/Elevation/World_Hillshade/MapServer/tile/{z}/{y}/{x}?token=${encodeURIComponent(arcgisKey)}`,
            options: {
                maxZoom: 23,
                attribution: 'Powered by <a href="https://www.esri.com/" target="_blank" rel="noopener">Esri</a> | Esri, Vantor, Airbus DS, USGS, NGA, NASA, CGIAR, N Robinson, NCEAS, NLS, OS, NMA, Geodatastyrelsen, Rijkswaterstaat, GSA, Geoland, FEMA, Intermap, GIS User Community'
            }
        }
    })
    const shareDefinitions = () => ({
        street: {
            id: 'street',
            available: arcgisKey !== '',
            worldTileSize: 512,
            zoomOffset: -1,
            maxZoom: 23,
            maxRequestZoom: 22,
            template: `https://static-map-tiles-api.arcgis.com/arcgis/rest/services/static-basemap-tiles-service/v1/arcgis/streets/static/tile/{z}/{y}/{x}?token=${encodeURIComponent(arcgisKey)}`,
            attribution: 'Map layer © Esri · Sources: TomTom, Garmin, FAO, NOAA, USGS, © OpenStreetMap contributors, GIS User Community'
        },
        satellite: {
            id: 'satellite',
            available: arcgisKey !== '',
            worldTileSize: 256,
            zoomOffset: 0,
            maxZoom: 23,
            maxRequestZoom: 23,
            template: `https://ibasemaps-api.arcgis.com/arcgis/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}?token=${encodeURIComponent(arcgisKey)}`,
            attribution: 'Imagery © Esri · Sources: Maxar, Earthstar Geographics, GIS User Community'
        }
    })
    const shareTile = (id, logicalZoom, x, y) => {
        const definition = shareDefinitions()[id]
        if (!definition?.available) return null
        const requestZoom = Number(logicalZoom) + definition.zoomOffset
        if (!Number.isInteger(requestZoom) || requestZoom < 0 || requestZoom > Number(definition.maxRequestZoom ?? 22)) return null
        const n = 2 ** requestZoom
        const wrappedX = ((Number(x) % n) + n) % n
        const row = Number(y)
        if (!Number.isInteger(row) || row < 0 || row >= n) return null
        const url = definition.template
            .replace('{z}', String(requestZoom))
            .replace('{x}', String(wrappedX))
            .replace('{y}', String(row))
        return {url, worldTileSize: definition.worldTileSize, attribution: definition.attribution, id: definition.id}
    }
    const shareDefinition = id => {
        const definition = shareDefinitions()[id]
        return definition ? {...definition} : null
    }
    const preferred = () => {
        try {
            const value = localStorage.getItem(storageKey)
            return validIds.has(value) ? value : 'street'
        } catch (_) {
            return 'street'
        }
    }
    const persist = id => {
        try { localStorage.setItem(storageKey, id) } catch (_) {}
    }
    const label = id => ({
        street: t('route.basemap.map', 'Mapa'),
        satellite: t('route.basemap.satellite', 'Satélite'),
        terrain: t('route.basemap.terrain', 'Relevo')
    }[id] || id)
    const syncControl = state => {
        if (!state?.control) return
        state.control.querySelectorAll('[data-stride-basemap]').forEach(button => {
            const active = button.dataset.strideBasemap === state.activeId
            button.classList.toggle('is-active', active)
            button.setAttribute('aria-pressed', active ? 'true' : 'false')
        })
        state.control.querySelectorAll('[data-stride-basemap-current]').forEach(node => { node.textContent = label(state.activeId) })
    }
    const announce = (state, message) => {
        if (!state?.status) return
        state.status.textContent = message
        state.status.hidden = !message
        clearTimeout(state.statusTimer)
        if (message) state.statusTimer = window.setTimeout(() => { state.status.hidden = true }, 5000)
    }
    const ensureLayer = (state, id) => {
        if (state.layers.has(id)) return state.layers.get(id)
        const definition = definitions()[id]
        if (!definition?.available || !window.L?.tileLayer) return null
        const layer = window.L.tileLayer(definition.url, definition.options)
        if (id !== 'street') {
            let errors = 0
            layer.on('tileload', () => { errors = 0 })
            layer.on('tileerror', () => {
                if (state.activeId !== id) return
                errors += 1
                if (errors < 2 || state.fallingBack) return
                state.fallingBack = true
                setBaseLayer(state.map, 'street', {persistChoice: true, announceFailure: false})
                announce(state, t('route.basemap.failed_fallback', 'Camada indisponível. Voltando para Mapa.'))
                window.setTimeout(() => { state.fallingBack = false }, 0)
            })
        }
        state.layers.set(id, layer)
        return layer
    }
    const setBaseLayer = (map, id, options = {}) => {
        const state = states.get(map)
        if (!state) return false
        const requested = validIds.has(id) ? id : 'street'
        const definition = definitions()[requested]
        const targetId = definition?.available ? requested : 'street'
        const layer = ensureLayer(state, targetId)
        if (!layer) return false
        if (state.activeLayer === layer) {
            syncControl(state)
            return true
        }
        if (state.activeLayer && map.hasLayer?.(state.activeLayer)) map.removeLayer(state.activeLayer)
        layer.addTo(map)
        state.activeLayer = layer
        state.activeId = targetId
        syncControl(state)
        if (options.persistChoice !== false && state.remember) persist(targetId)
        if (requested !== targetId && options.announceFailure !== false) announce(state, t('route.basemap.arcgis_unavailable', 'Satélite e Relevo exigem a configuração de mapas.'))
        return true
    }
    const createControl = state => {
        if (!window.L?.Control) return null
        const unavailable = t('route.basemap.arcgis_unavailable', 'Satélite e Relevo exigem a configuração de mapas.')
        const control = window.L.control({position: 'topright'})
        control.onAdd = () => {
            const root = document.createElement('div')
            root.className = 'stride-map-basemap-control leaflet-control'
            root.setAttribute('aria-label', t('route.basemap.layers', 'Camadas'))
            const desktop = document.createElement('div')
            desktop.className = 'stride-map-basemap-segment'
            desktop.setAttribute('role', 'group')
            desktop.setAttribute('aria-label', t('route.basemap.layers', 'Camadas'))
            const mobile = document.createElement('details')
            mobile.className = 'stride-map-basemap-mobile'
            const summary = document.createElement('summary')
            summary.innerHTML = `<span>${t('route.basemap.layers', 'Camadas')}</span><small data-stride-basemap-current>${label(state.activeId)}</small>`
            const menu = document.createElement('div')
            menu.className = 'stride-map-basemap-menu'
            ;['street', 'satellite', 'terrain'].forEach(id => {
                const definition = definitions()[id]
                const makeButton = () => {
                    const button = document.createElement('button')
                    button.type = 'button'
                    button.dataset.strideBasemap = id
                    button.textContent = label(id)
                    button.setAttribute('aria-pressed', 'false')
                    if (!definition.available) {
                        button.disabled = true
                        button.title = unavailable
                        button.setAttribute('aria-label', `${label(id)} — ${unavailable}`)
                    }
                    button.addEventListener('click', () => {
                        if (!setBaseLayer(state.map, id)) return
                        if (mobile.open) mobile.open = false
                    })
                    return button
                }
                desktop.appendChild(makeButton())
                menu.appendChild(makeButton())
            })
            mobile.append(summary, menu)
            const status = document.createElement('div')
            status.className = 'stride-map-basemap-status'
            status.setAttribute('role', 'status')
            status.setAttribute('aria-live', 'polite')
            status.hidden = true
            root.append(desktop, mobile, status)
            state.control = root
            state.status = status
            window.L.DomEvent?.disableClickPropagation?.(root)
            window.L.DomEvent?.disableScrollPropagation?.(root)
            root.addEventListener('keydown', event => {
                if (event.key === 'Escape' && mobile.open) {
                    mobile.open = false
                    summary.focus()
                }
            })
            syncControl(state)
            return root
        }
        control.addTo(state.map)
        state.leafletControl = control
        return control
    }
    const attach = (map, options = {}) => {
        if (!map || !window.L?.tileLayer) return null
        const existing = states.get(map)
        if (existing) return existing.controller
        const remember = options.remember !== false
        const state = {
            map,
            remember,
            layers: new Map(),
            activeId: 'street',
            activeLayer: null,
            control: null,
            status: null,
            statusTimer: 0,
            fallingBack: false,
            controller: null
        }
        const controller = {
            set: id => setBaseLayer(map, id),
            get: () => state.activeId,
            available: id => Boolean(definitions()[id]?.available),
            destroy: () => {
                clearTimeout(state.statusTimer)
                state.leafletControl?.remove?.()
                state.layers.forEach(layer => { if (map.hasLayer?.(layer)) map.removeLayer(layer) })
                states.delete(map)
            }
        }
        state.controller = controller
        states.set(map, state)
        const initial = options.initial || (remember ? preferred() : 'street')
        setBaseLayer(map, initial, {persistChoice: false})
        if (options.controls) createControl(state)
        return controller
    }
    window.StrideBRBasemaps = Object.freeze({
        attach,
        setBaseLayer,
        preferred,
        storageKey,
        share: Object.freeze({
            available: id => Boolean(shareDefinitions()[id]?.available),
            definition: shareDefinition,
            tile: shareTile,
            ids: Object.freeze(['street', 'satellite'])
        })
    })
})()
