(() => {
    const root = document.querySelector('[data-gps-recorder]')
    if (!root) return

    const $ = selector => root.querySelector(selector)
    const setup = $('[data-gps-setup]')
    const live = $('[data-gps-live]')
    const review = $('[data-gps-review]')
    const sport = $('[data-gps-sport]')
    const startButton = $('[data-gps-start]')
    const pauseButton = $('[data-gps-pause]')
    const lapButton = $('[data-gps-lap]')
    const finishButton = $('[data-gps-finish]')
    const timeEl = $('[data-gps-time]')
    const distanceEl = $('[data-gps-distance]')
    const paceEl = $('[data-gps-pace]')
    const paceLabel = $('[data-gps-pace-label]')
    const elevationEl = $('[data-gps-elevation]')
    const accuracyEl = $('[data-gps-accuracy]')
    const accuracyLargeEl = $('[data-gps-accuracy-large]')
    const qualityEl = $('[data-gps-quality]')
    const networkEl = $('[data-gps-network]')
    const liveSportEl = $('[data-gps-live-sport]')
    const goalCard = $('[data-gps-goal-card]')
    const goalProgressEl = $('[data-gps-goal-progress]')
    const currentLapEl = $('[data-gps-current-lap]')
    const currentLapMetrics = $('[data-gps-current-lap-metrics]')
    const mapElement = $('[data-gps-map]')
    const mapFallback = $('[data-gps-map-fallback]')
    const restoreBox = $('[data-gps-restore]')
    const restoreSummary = $('[data-gps-restore-summary]')
    const resumeButton = $('[data-gps-resume]')
    const discardSavedButton = $('[data-gps-discard-saved]')
    const goalValueWrap = $('[data-gps-goal-value]')
    const goalNumber = $('[data-gps-goal-number]')
    const goalUnit = $('[data-gps-goal-unit]')
    const autoStopWrap = $('[data-gps-autostop-wrap]')
    const autoStopInput = $('[data-gps-autostop]')
    const wakeLockInput = $('[data-gps-wakelock]')
    const saveForm = $('[data-gps-save-form]')
    const saveState = $('[data-gps-save-state]')
    const reviewTitle = $('[data-gps-review-title]')
    const reviewDistance = $('[data-gps-review-distance]')
    const reviewDuration = $('[data-gps-review-duration]')
    const reviewElevation = $('[data-gps-review-elevation]')
    const reviewVisibility = $('[data-gps-review-visibility]')
    const reviewEffort = $('[data-gps-review-effort]')
    const reviewHideStart = $('[data-gps-review-hide-start]')
    const reviewHideEnd = $('[data-gps-review-hide-end]')
    const reviewNotes = $('[data-gps-review-notes]')
    const qualitySummary = $('[data-gps-quality-summary]')
    const reviewQuality = $('[data-gps-review-quality]')
    const reviewWarning = $('[data-gps-review-warning]')
    const segmentsHost = $('[data-gps-segments]')
    const reviewMap = $('[data-gps-review-map]')
    const backLive = $('[data-gps-back-live]')

    const DB_NAME = 'stridebr-gps-web'
    const DB_VERSION = 1
    const STORE = 'recordings'
    const ACTIVE_KEY = 'active'
    const MAX_FINAL_POINTS = 1850
    let dbPromise = null
    let watchId = null
    let tickId = null
    let persistTimer = 0
    let wakeLock = null
    let leafletPromise = null
    let liveMap = null
    let liveLine = null
    let reviewMapInstance = null
    let reviewLine = null
    let lastMapFollowAt = 0
    let pendingAutoSave = false

    const createRecordingId = () => crypto.randomUUID ? crypto.randomUUID() : Array.from(crypto.getRandomValues(new Uint8Array(16)), byte => byte.toString(16).padStart(2, '0')).join('')

    const freshState = () => ({
        version: 1,
        status: 'idle',
        id: '',
        idmodalidade: '',
        sportSlug: '',
        sportName: '',
        metric: 'pace_km',
        startedAtMs: 0,
        endedAtMs: 0,
        pausedTotalMs: 0,
        pauseStartedMs: 0,
        points: [],
        pointsReceived: 0,
        pointsRejected: 0,
        distanceM: 0,
        accuracySum: 0,
        accuracyCount: 0,
        accuracyBest: null,
        accuracyWorst: null,
        currentAccuracy: null,
        altitudeWindow: [],
        lastSmoothAltitude: null,
        elevationGainM: 0,
        elevationMinM: null,
        elevationMaxM: null,
        segments: [],
        segmentStartIndex: 0,
        segmentStartDistanceM: 0,
        segmentStartActiveMs: 0,
        segmentStartElevationM: 0,
        goalType: 'none',
        goalValue: 0,
        autoStop: false,
        endedByGoal: false,
        hiddenSinceMs: 0,
        visibilityGaps: 0,
        lastPointAtMs: 0,
        lastGoodPointAtMs: 0,
        needsPositionAnchor: false,
        goalCandidateAtMs: 0,
        goalCandidatePointCount: 0,
        userAdjusted: false,
        mapUnavailable: false
    })
    let state = freshState()

    const openDb = () => {
        if (!('indexedDB' in window)) return Promise.resolve(null)
        if (dbPromise) return dbPromise
        dbPromise = new Promise(resolve => {
            const request = indexedDB.open(DB_NAME, DB_VERSION)
            request.onupgradeneeded = () => {
                const db = request.result
                if (!db.objectStoreNames.contains(STORE)) db.createObjectStore(STORE)
            }
            request.onsuccess = () => resolve(request.result)
            request.onerror = () => resolve(null)
        })
        return dbPromise
    }

    const idbPut = async value => {
        const db = await openDb()
        if (!db) return
        await new Promise(resolve => {
            const tx = db.transaction(STORE, 'readwrite')
            tx.objectStore(STORE).put(value, ACTIVE_KEY)
            tx.oncomplete = () => resolve()
            tx.onerror = () => resolve()
            tx.onabort = () => resolve()
        })
    }

    const idbGet = async () => {
        const db = await openDb()
        if (!db) return null
        return await new Promise(resolve => {
            const tx = db.transaction(STORE, 'readonly')
            const request = tx.objectStore(STORE).get(ACTIVE_KEY)
            request.onsuccess = () => resolve(request.result || null)
            request.onerror = () => resolve(null)
        })
    }

    const idbDelete = async () => {
        const db = await openDb()
        if (!db) return
        await new Promise(resolve => {
            const tx = db.transaction(STORE, 'readwrite')
            tx.objectStore(STORE).delete(ACTIVE_KEY)
            tx.oncomplete = () => resolve()
            tx.onerror = () => resolve()
        })
    }

    const schedulePersist = immediate => {
        window.clearTimeout(persistTimer)
        if (immediate) {
            idbPut(state)
            return
        }
        persistTimer = window.setTimeout(() => idbPut(state), 2500)
    }

    const selectedOption = () => sport?.selectedOptions?.[0] || null
    const sportConfig = () => {
        const option = selectedOption()
        const slug = option?.dataset.slug || ''
        const metric = option?.dataset.metric || 'pace_km'
        let maxSpeed = 16
        let maxAccuracy = 65
        if (/cicl|bike|bmx|gravel|handcycle|velomovel/.test(slug)) {
            maxSpeed = 35
            maxAccuracy = 80
        } else if (/cano|caiaque|remo|vela|surf|paddle|kitesurf|windsurf/.test(slug)) {
            maxSpeed = 25
            maxAccuracy = 90
        } else if (/caminhada|marcha/.test(slug)) {
            maxSpeed = 7
            maxAccuracy = 60
        }
        return {slug, metric, maxSpeed, maxAccuracy, name: option?.textContent?.trim() || 'Atividade'}
    }

    const haversine = (a, b) => {
        const R = 6371008.8
        const toRad = value => value * Math.PI / 180
        const lat1 = toRad(a.lat)
        const lat2 = toRad(b.lat)
        const dLat = lat2 - lat1
        const dLon = toRad(b.lon - a.lon)
        const h = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon / 2) ** 2
        return R * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(Math.max(0, 1 - h)))
    }

    const formatDuration = seconds => {
        const value = Math.max(0, Math.floor(seconds || 0))
        const hours = Math.floor(value / 3600)
        const minutes = Math.floor((value % 3600) / 60)
        const secs = value % 60
        return [hours, minutes, secs].map(part => String(part).padStart(2, '0')).join(':')
    }

    const parseDuration = raw => {
        const parts = String(raw || '').trim().split(':').map(Number)
        if (parts.some(value => !Number.isFinite(value) || value < 0)) return null
        if (parts.length === 2 && parts[1] < 60) return parts[0] * 60 + parts[1]
        if (parts.length === 3 && parts[1] < 60 && parts[2] < 60) return parts[0] * 3600 + parts[1] * 60 + parts[2]
        return null
    }

    const activeElapsedMs = (now = Date.now()) => {
        if (!state.startedAtMs) return 0
        let paused = state.pausedTotalMs || 0
        if (state.status === 'paused' && state.pauseStartedMs) paused += Math.max(0, now - state.pauseStartedMs)
        return Math.max(0, now - state.startedAtMs - paused)
    }

    const accuracyQuality = accuracy => {
        if (!Number.isFinite(accuracy)) return {key: 'waiting', label: 'GPS aguardando'}
        if (accuracy <= 12) return {key: 'good', label: 'GPS muito bom'}
        if (accuracy <= 25) return {key: 'good', label: 'GPS bom'}
        if (accuracy <= 45) return {key: 'fair', label: 'GPS regular'}
        return {key: 'poor', label: 'GPS impreciso'}
    }

    const updateGoalControls = () => {
        const type = root.querySelector('input[name="gps_goal_type"]:checked')?.value || 'none'
        const enabled = type !== 'none'
        if (goalValueWrap) goalValueWrap.hidden = !enabled
        if (autoStopWrap) autoStopWrap.hidden = !enabled
        if (goalUnit) {
            goalUnit.innerHTML = type === 'time' ? '<option value="min">min</option>' : '<option value="km">km</option>'
        }
        if (goalNumber) {
            goalNumber.placeholder = type === 'time' ? '30' : '5,0'
            goalNumber.step = type === 'time' ? '1' : '0.1'
            goalNumber.min = type === 'time' ? '1' : '0.1'
        }
    }

    const readGoal = () => {
        const type = root.querySelector('input[name="gps_goal_type"]:checked')?.value || 'none'
        const value = Number(String(goalNumber?.value || '').replace(',', '.'))
        if (type === 'none') return {type: 'none', value: 0, autoStop: false}
        if (!Number.isFinite(value) || value <= 0) return null
        return {type, value: type === 'distance' ? value * 1000 : value * 60, autoStop: Boolean(autoStopInput?.checked)}
    }

    const ensureLeaflet = () => {
        if (window.L?.map) return Promise.resolve(window.L)
        if (leafletPromise) return leafletPromise
        leafletPromise = new Promise((resolve, reject) => {
            if (!document.querySelector('link[data-stridebr-gps-leaflet]')) {
                const link = document.createElement('link')
                link.rel = 'stylesheet'
                link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
                link.dataset.stridebrGpsLeaflet = '1'
                document.head.appendChild(link)
            }
            const existing = document.querySelector('script[data-stridebr-gps-leaflet]')
            if (existing) {
                existing.addEventListener('load', () => resolve(window.L), {once: true})
                existing.addEventListener('error', reject, {once: true})
                return
            }
            const script = document.createElement('script')
            script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
            script.dataset.stridebrGpsLeaflet = '1'
            script.onload = () => resolve(window.L)
            script.onerror = reject
            document.head.appendChild(script)
        }).catch(error => {
            state.mapUnavailable = true
            leafletPromise = null
            throw error
        })
        return leafletPromise
    }

    const initLiveMap = async () => {
        if (!mapElement || liveMap || state.mapUnavailable) return
        try {
            await ensureLeaflet()
            liveMap = window.L.map(mapElement, {zoomControl: true, attributionControl: true}).setView([-14.2, -51.9], 4)
            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'}).addTo(liveMap)
            liveLine = window.L.polyline([], {weight: 5, opacity: .9}).addTo(liveMap)
            if (mapFallback) mapFallback.hidden = true
            redrawLiveMap(true)
        } catch (_) {
            if (mapFallback) mapFallback.hidden = false
        }
    }

    const redrawLiveMap = fit => {
        if (!liveMap || !liveLine || state.points.length === 0) return
        const latlngs = state.points.map(point => [point.lat, point.lon])
        liveLine.setLatLngs(latlngs)
        const last = latlngs[latlngs.length - 1]
        if (fit && latlngs.length > 1) liveMap.fitBounds(liveLine.getBounds(), {padding: [24, 24], maxZoom: 17})
        else if (fit) liveMap.setView(last, 17)
        else if (Date.now() - lastMapFollowAt > 3500) {
            liveMap.panTo(last, {animate: true, duration: .35})
            lastMapFollowAt = Date.now()
        }
    }

    const renderReviewMap = async () => {
        if (!reviewMap || state.points.length < 2) return
        try {
            await ensureLeaflet()
            if (!reviewMapInstance) {
                reviewMapInstance = window.L.map(reviewMap, {zoomControl: true, attributionControl: false, scrollWheelZoom: false})
                window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19}).addTo(reviewMapInstance)
                reviewLine = window.L.polyline([], {weight: 5, opacity: .9}).addTo(reviewMapInstance)
            }
            const latlngs = state.points.map(point => [point.lat, point.lon])
            reviewLine.setLatLngs(latlngs)
            reviewMapInstance.fitBounds(reviewLine.getBounds(), {padding: [20, 20], maxZoom: 16})
            window.setTimeout(() => reviewMapInstance?.invalidateSize(), 60)
        } catch (_) {
            reviewMap.textContent = 'A rota foi gravada, mas o mapa não pôde ser carregado.'
        }
    }

    const requestWakeLock = async () => {
        if (!wakeLockInput?.checked || !('wakeLock' in navigator) || document.visibilityState !== 'visible') return
        try {
            if (wakeLock && !wakeLock.released) return
            wakeLock = await navigator.wakeLock.request('screen')
            wakeLock.addEventListener('release', () => { wakeLock = null }, {once: true})
        } catch (_) {
            wakeLock = null
        }
    }

    const releaseWakeLock = async () => {
        try { await wakeLock?.release?.() } catch (_) {}
        wakeLock = null
    }

    const addAccuracy = accuracy => {
        if (!Number.isFinite(accuracy) || accuracy < 0) return
        state.currentAccuracy = accuracy
        state.accuracySum += accuracy
        state.accuracyCount++
        state.accuracyBest = state.accuracyBest === null ? accuracy : Math.min(state.accuracyBest, accuracy)
        state.accuracyWorst = state.accuracyWorst === null ? accuracy : Math.max(state.accuracyWorst, accuracy)
    }

    const altitudeUpdate = coords => {
        if (coords.altitude === null || coords.altitude === undefined) return
        const altitude = Number(coords.altitude)
        const altitudeAccuracy = coords.altitudeAccuracy === null || coords.altitudeAccuracy === undefined ? null : Number(coords.altitudeAccuracy)
        if (!Number.isFinite(altitude)) return
        if (Number.isFinite(altitudeAccuracy) && altitudeAccuracy > 35) return
        state.altitudeWindow.push(altitude)
        if (state.altitudeWindow.length > 5) state.altitudeWindow.shift()
        const sorted = [...state.altitudeWindow].sort((a, b) => a - b)
        const smooth = sorted[Math.floor(sorted.length / 2)]
        state.elevationMinM = state.elevationMinM === null ? smooth : Math.min(state.elevationMinM, smooth)
        state.elevationMaxM = state.elevationMaxM === null ? smooth : Math.max(state.elevationMaxM, smooth)
        if (state.lastSmoothAltitude !== null) {
            const delta = smooth - state.lastSmoothAltitude
            if (delta >= 3) state.elevationGainM += delta
        }
        state.lastSmoothAltitude = smooth
    }

    const acceptPosition = position => {
        state.pointsReceived++
        const coords = position.coords || {}
        const lat = Number(coords.latitude)
        const lon = Number(coords.longitude)
        const accuracy = Number(coords.accuracy)
        const timestamp = Number(position.timestamp) || Date.now()
        addAccuracy(accuracy)
        if (state.status !== 'recording') {
            schedulePersist(false)
            updateLiveUi()
            return
        }
        if (!Number.isFinite(lat) || !Number.isFinite(lon) || !Number.isFinite(accuracy)) {
            state.pointsRejected++
            return
        }
        const cfg = sportConfig()
        if (accuracy <= 0 || accuracy > cfg.maxAccuracy) {
            state.pointsRejected++
            schedulePersist(false)
            updateLiveUi()
            return
        }
        const point = {lat, lon, accuracy, t: timestamp, active_ms: activeElapsedMs(timestamp)}
        const last = state.points[state.points.length - 1]
        let increment = 0
        if (last && state.needsPositionAnchor) {
            state.points.push(point)
            state.needsPositionAnchor = false
            state.lastPointAtMs = timestamp
            state.lastGoodPointAtMs = Date.now()
            state.altitudeWindow = []
            state.lastSmoothAltitude = null
            altitudeUpdate(coords)
            schedulePersist(false)
            updateLiveUi()
            redrawLiveMap(false)
            checkGoal()
            return
        }
        if (last) {
            const dt = Math.max(.001, (timestamp - last.t) / 1000)
            if (dt > 15) {
                state.visibilityGaps++
                state.points.push(point)
                state.lastPointAtMs = timestamp
                state.lastGoodPointAtMs = Date.now()
                state.altitudeWindow = []
                state.lastSmoothAltitude = null
                altitudeUpdate(coords)
                schedulePersist(true)
                updateLiveUi()
                redrawLiveMap(false)
                checkGoal()
                return
            }
            if (dt < .55) {
                state.pointsRejected++
                return
            }
            const rawDistance = haversine(last, point)
            const jitterFloor = Math.max(2.2, Math.min(8, ((last.accuracy || accuracy) + accuracy) * .16))
            const speed = rawDistance / dt
            const deviceSpeed = Number(coords.speed)
            if ((Number.isFinite(deviceSpeed) && deviceSpeed > cfg.maxSpeed * 1.35) || speed > cfg.maxSpeed) {
                state.pointsRejected++
                schedulePersist(false)
                return
            }
            if (accuracy > 30 && rawDistance < accuracy * .5) {
                state.pointsRejected++
                schedulePersist(false)
                return
            }
            if (rawDistance < jitterFloor && dt < 10) {
                state.pointsRejected++
                schedulePersist(false)
                return
            }
            increment = rawDistance
        }
        state.points.push(point)
        state.distanceM += increment
        state.lastPointAtMs = timestamp
        state.lastGoodPointAtMs = Date.now()
        altitudeUpdate(coords)
        if (state.segmentStartIndex >= state.points.length) state.segmentStartIndex = Math.max(0, state.points.length - 1)
        schedulePersist(false)
        updateLiveUi()
        redrawLiveMap(state.points.length <= 2)
        checkGoal()
    }

    const locationError = error => {
        const permissionDenied = error?.code === 1
        const message = permissionDenied
            ? 'Localização negada. Permita o acesso ao GPS para gravar a rota.'
            : error?.code === 2
                ? 'O aparelho não conseguiu determinar sua localização agora.'
                : 'O GPS demorou para responder. O StrideBR continuará tentando enquanto a gravação estiver aberta.'
        if (qualityEl) {
            qualityEl.dataset.quality = 'lost'
            qualityEl.textContent = 'GPS sem sinal'
        }
        if (root.querySelector('[data-gps-live-warning]')) root.querySelector('[data-gps-live-warning]').textContent = message + ' Você poderá revisar os dados antes de salvar.'
        if (permissionDenied && ['recording', 'paused', 'starting'].includes(state.status)) {
            clearWatch()
            window.clearInterval(tickId)
            releaseWakeLock()
            state = freshState()
            idbDelete().catch(() => {})
            startButton.disabled = false
            startButton.textContent = '▶ Tentar novamente'
            setView('setup')
            return
        }
        if (state.status === 'idle' || state.status === 'starting') {
            startButton.disabled = false
            startButton.textContent = '▶ Tentar novamente'
            state.status = 'idle'
            setView('setup')
        }
    }

    const startWatch = () => {
        if (!navigator.geolocation) throw new Error('Este navegador não oferece a API de geolocalização.')
        if (watchId !== null) navigator.geolocation.clearWatch(watchId)
        watchId = navigator.geolocation.watchPosition(acceptPosition, locationError, {
            enableHighAccuracy: true,
            maximumAge: 0,
            timeout: 15000
        })
    }

    const clearWatch = () => {
        if (watchId !== null && navigator.geolocation) navigator.geolocation.clearWatch(watchId)
        watchId = null
    }

    const setView = view => {
        if (setup) setup.hidden = view !== 'setup'
        if (live) live.hidden = view !== 'live'
        if (review) review.hidden = view !== 'review'
        document.body.classList.toggle('is-gps-recording', view === 'live')
        if (view === 'live') {
            initLiveMap()
            window.setTimeout(() => liveMap?.invalidateSize(), 80)
        }
        if (view === 'review') renderReviewMap()
    }

    const currentPace = () => {
        if (state.points.length < 2) return null
        const last = state.points[state.points.length - 1]
        let firstIndex = state.points.length - 2
        for (let i = state.points.length - 2; i >= 0; i--) {
            if (last.active_ms - state.points[i].active_ms > 30000) break
            firstIndex = i
        }
        const subset = state.points.slice(firstIndex)
        let distance = 0
        for (let i = 1; i < subset.length; i++) distance += haversine(subset[i - 1], subset[i])
        const seconds = Math.max(1, (last.active_ms - subset[0].active_ms) / 1000)
        if (distance < 18) return null
        return {distance, seconds}
    }

    const formatPaceOrSpeed = () => {
        const recent = currentPace()
        if (!recent) return '—'
        if (state.metric === 'velocidade_kmh' || /cicl|bike|bmx|gravel/.test(state.sportSlug)) {
            const kmh = recent.distance / recent.seconds * 3.6
            return `${kmh.toFixed(1).replace('.', ',')} km/h`
        }
        const secKm = recent.seconds / (recent.distance / 1000)
        if (!Number.isFinite(secKm) || secKm > 3600) return '— /km'
        const minutes = Math.floor(secKm / 60)
        const seconds = Math.round(secKm % 60)
        return `${minutes}:${String(seconds).padStart(2, '0')} /km`
    }

    const updateGoalUi = () => {
        if (!goalCard || state.goalType === 'none' || !state.goalValue) {
            if (goalCard) goalCard.hidden = true
            return
        }
        goalCard.hidden = false
        if (state.goalType === 'distance') {
            goalProgressEl.textContent = `${(state.distanceM / 1000).toFixed(2).replace('.', ',')} / ${(state.goalValue / 1000).toFixed(2).replace('.', ',')} km`
        } else {
            goalProgressEl.textContent = `${formatDuration(activeElapsedMs() / 1000)} / ${formatDuration(state.goalValue)}`
        }
    }

    const updateLiveUi = () => {
        const elapsedSeconds = activeElapsedMs() / 1000
        if (timeEl) timeEl.textContent = formatDuration(elapsedSeconds)
        if (distanceEl) distanceEl.textContent = `${(state.distanceM / 1000).toFixed(2).replace('.', ',')} km`
        if (paceEl) paceEl.textContent = formatPaceOrSpeed()
        if (paceLabel) paceLabel.textContent = state.metric === 'velocidade_kmh' || /cicl|bike|bmx|gravel/.test(state.sportSlug) ? 'Velocidade' : 'Ritmo'
        if (elevationEl) elevationEl.textContent = state.lastSmoothAltitude === null ? '— m' : `+${Math.round(state.elevationGainM)} m`
        const quality = accuracyQuality(state.currentAccuracy)
        if (qualityEl) {
            qualityEl.dataset.quality = quality.key
            qualityEl.textContent = quality.label
        }
        if (accuracyEl) accuracyEl.textContent = Number.isFinite(state.currentAccuracy) ? `${Math.round(state.currentAccuracy)} m` : '— m'
        if (accuracyLargeEl) accuracyLargeEl.textContent = Number.isFinite(state.currentAccuracy) ? `± ${Math.round(state.currentAccuracy)} m` : '—'
        if (liveSportEl) liveSportEl.textContent = state.sportName || 'Atividade'
        if (pauseButton) pauseButton.textContent = state.status === 'paused' ? 'Continuar' : 'Pausar'
        const currentLap = state.segments.length + 1
        if (currentLapEl) currentLapEl.textContent = String(currentLap)
        const lapDistance = Math.max(0, state.distanceM - state.segmentStartDistanceM)
        const lapDuration = Math.max(0, activeElapsedMs() - state.segmentStartActiveMs) / 1000
        if (currentLapMetrics) currentLapMetrics.textContent = `${(lapDistance / 1000).toFixed(2).replace('.', ',')} km · ${formatDuration(lapDuration).replace(/^00:/, '')}`
        updateGoalUi()
    }

    const finalizeCurrentSegment = force => {
        if (!state.points.length) return
        const endIndex = state.points.length - 1
        const distance = Math.max(0, state.distanceM - state.segmentStartDistanceM)
        const duration = Math.max(0, Math.round((activeElapsedMs() - state.segmentStartActiveMs) / 1000))
        if (!force && distance < 5 && duration < 5) return
        if (force && state.segments.length > 0 && distance < 1 && duration < 2) return
        state.segments.push({
            label: `Trecho ${state.segments.length + 1}`,
            start_index: Math.min(state.segmentStartIndex, endIndex),
            end_index: endIndex,
            distance_m: distance,
            duration_s: duration,
            elevation_gain_m: Math.max(0, state.elevationGainM - state.segmentStartElevationM)
        })
        state.segmentStartIndex = endIndex
        state.segmentStartDistanceM = state.distanceM
        state.segmentStartActiveMs = activeElapsedMs()
        state.segmentStartElevationM = state.elevationGainM
        schedulePersist(true)
        updateLiveUi()
    }

    const checkGoal = () => {
        if (!state.autoStop || state.goalType === 'none' || state.goalValue <= 0 || state.status !== 'recording') return
        if (state.goalType === 'time') {
            if (activeElapsedMs() / 1000 >= state.goalValue) finishRecording(true)
            return
        }
        if (state.distanceM < state.goalValue) {
            state.goalCandidateAtMs = 0
            state.goalCandidatePointCount = 0
            return
        }
        const accuracy = Number(state.currentAccuracy)
        const confirmationMargin = Number.isFinite(accuracy) ? Math.max(3, Math.min(15, accuracy * .35)) : 8
        if (state.distanceM >= state.goalValue + confirmationMargin) {
            finishRecording(true)
            return
        }
        if (!state.goalCandidateAtMs) {
            state.goalCandidateAtMs = Date.now()
            state.goalCandidatePointCount = state.points.length
            return
        }
        if (state.points.length > state.goalCandidatePointCount && Date.now() - state.goalCandidateAtMs >= 800) {
            finishRecording(true)
        }
    }

    const startTick = () => {
        window.clearInterval(tickId)
        tickId = window.setInterval(() => {
            updateLiveUi()
            if (state.status === 'recording') checkGoal()
            if (state.status === 'recording' && state.lastGoodPointAtMs && Date.now() - state.lastGoodPointAtMs > 20000 && qualityEl) {
                qualityEl.dataset.quality = 'lost'
                qualityEl.textContent = 'GPS sem atualização'
            }
        }, 500)
    }

    const startRecording = async () => {
        if (state.status === 'recording' || state.status === 'paused') return
        const goal = readGoal()
        if (!goal) {
            goalNumber?.focus()
            goalNumber?.setCustomValidity('Informe uma meta válida.')
            goalNumber?.reportValidity()
            goalNumber?.setCustomValidity('')
            return
        }
        if (!window.isSecureContext) {
            window.alert('O GPS Web exige uma conexão HTTPS segura. Abra o StrideBR por HTTPS ou use o registro manual.')
            return
        }
        if (!navigator.geolocation) {
            window.alert('Este navegador não oferece geolocalização. Use o registro manual de atividade.')
            return
        }
        const cfg = sportConfig()
        state = freshState()
        state.status = 'starting'
        state.id = createRecordingId()
        state.idmodalidade = sport.value
        state.sportSlug = cfg.slug
        state.sportName = cfg.name
        state.metric = cfg.metric
        state.startedAtMs = Date.now()
        state.segmentStartActiveMs = 0
        state.goalType = goal.type
        state.goalValue = goal.value
        state.autoStop = goal.autoStop
        startButton.disabled = true
        startButton.textContent = 'Obtendo GPS…'
        try {
            if (navigator.storage?.persist) navigator.storage.persist().catch(() => {})
            if (navigator.permissions?.query) {
                try {
                    const permission = await navigator.permissions.query({name: 'geolocation'})
                    if (permission.state === 'denied') throw new Error('permission-denied')
                } catch (error) {
                    if (error?.message === 'permission-denied') throw error
                }
            }
            state.status = 'recording'
            setView('live')
            startWatch()
            startTick()
            requestWakeLock()
            schedulePersist(true)
            updateLiveUi()
        } catch (_) {
            state.status = 'idle'
            startButton.disabled = false
            startButton.textContent = '▶ Iniciar'
            window.alert('O navegador está bloqueando o acesso à localização. Libere a permissão de localização para o StrideBR e tente novamente.')
        }
    }

    const togglePause = () => {
        if (state.status === 'recording') {
            state.status = 'paused'
            state.pauseStartedMs = Date.now()
            schedulePersist(true)
            updateLiveUi()
            return
        }
        if (state.status === 'paused') {
            state.pausedTotalMs += Math.max(0, Date.now() - state.pauseStartedMs)
            state.pauseStartedMs = 0
            state.status = 'recording'
            state.needsPositionAnchor = state.points.length > 0
            if (watchId === null) startWatch()
            schedulePersist(true)
            updateLiveUi()
        }
    }

    const finishRecording = async endedByGoal => {
        if (!['recording', 'paused'].includes(state.status)) return
        if (state.status === 'paused' && state.pauseStartedMs) {
            state.pausedTotalMs += Math.max(0, Date.now() - state.pauseStartedMs)
            state.pauseStartedMs = 0
        }
        if (state.points.length < 2) {
            window.alert('Ainda não há pontos GPS suficientes para formar uma rota. Aguarde um sinal melhor ou use o registro manual.')
            return
        }
        finalizeCurrentSegment(true)
        state.status = 'review'
        state.endedAtMs = Date.now()
        state.endedByGoal = Boolean(endedByGoal)
        clearWatch()
        window.clearInterval(tickId)
        await releaseWakeLock()
        schedulePersist(true)
        fillReview()
        setView('review')
    }

    const qualityText = () => {
        const avg = state.accuracyCount ? state.accuracySum / state.accuracyCount : null
        if (!Number.isFinite(avg)) return 'Sem precisão suficiente'
        if (avg <= 15 && state.visibilityGaps === 0) return 'Gravação boa para GPS Web'
        if (avg <= 30 && state.visibilityGaps <= 1) return 'Gravação razoável para GPS Web'
        return 'Revise os dados com atenção'
    }

    const fillReview = () => {
        const durationS = Math.max(1, Math.round(activeElapsedMs(state.endedAtMs || Date.now()) / 1000))
        reviewTitle.value = state.sportName
        reviewDistance.value = (state.distanceM / 1000).toFixed(2)
        reviewDuration.value = formatDuration(durationS)
        reviewElevation.value = state.lastSmoothAltitude === null ? '' : String(Math.max(0, Math.round(state.elevationGainM)))
        reviewQuality.textContent = qualityText()
        const avg = state.accuracyCount ? state.accuracySum / state.accuracyCount : null
        qualitySummary.innerHTML = [
            ['Precisão média', Number.isFinite(avg) ? `± ${Math.round(avg)} m` : '—'],
            ['Melhor precisão', Number.isFinite(state.accuracyBest) ? `± ${Math.round(state.accuracyBest)} m` : '—'],
            ['Pontos aceitos', String(state.points.length)],
            ['Pontos descartados', String(state.pointsRejected)],
            ['Lacunas de tela/aba', String(state.visibilityGaps)],
            ['Origem', 'GPS Web']
        ].map(([label, value]) => `<div><span>${label}</span><strong>${value}</strong></div>`).join('')
        if (reviewWarning) {
            const warnings = []
            if (state.visibilityGaps > 0) warnings.push('A página ficou oculta/bloqueada durante a atividade; o navegador pode ter interrompido atualizações GPS nesse período.')
            if (Number.isFinite(avg) && avg > 30) warnings.push('A precisão média recebida foi baixa. Confira principalmente a distância e a rota.')
            if (state.pointsRejected > state.points.length) warnings.push('Muitos pontos foram descartados pelo filtro de ruído/saltos.')
            reviewWarning.hidden = warnings.length === 0
            reviewWarning.textContent = warnings.join(' ')
        }
        renderSegments()
    }

    const renderSegments = () => {
        if (!segmentsHost) return
        segmentsHost.innerHTML = ''
        state.segments.forEach((segment, index) => {
            const row = document.createElement('div')
            row.className = 'gps-segment-row'
            row.innerHTML = `<strong>${index + 1}</strong><div><b></b><span></span></div><small></small>`
            row.querySelector('b').textContent = segment.label || `Trecho ${index + 1}`
            row.querySelector('span').textContent = `${(Number(segment.distance_m || 0) / 1000).toFixed(2).replace('.', ',')} km · ${formatDuration(segment.duration_s || 0).replace(/^00:/, '')}`
            const pace = segment.distance_m > 0 ? segment.duration_s / (segment.distance_m / 1000) : null
            row.querySelector('small').textContent = Number.isFinite(pace) ? `${Math.floor(pace / 60)}:${String(Math.round(pace % 60)).padStart(2, '0')}/km` : '—'
            segmentsHost.appendChild(row)
        })
    }

    const compactForServer = () => {
        const points = state.points
        if (points.length <= MAX_FINAL_POINTS) return {points: points.map(point => ({lat: point.lat, lon: point.lon, accuracy: point.accuracy, t: point.t})), segments: state.segments}
        const boundaries = new Set([0, points.length - 1])
        state.segments.forEach(segment => {
            boundaries.add(Math.max(0, Math.min(points.length - 1, Number(segment.start_index) || 0)))
            boundaries.add(Math.max(0, Math.min(points.length - 1, Number(segment.end_index) || 0)))
        })
        const step = Math.ceil(points.length / (MAX_FINAL_POINTS - boundaries.size))
        const kept = new Set(boundaries)
        for (let i = 0; i < points.length; i += step) kept.add(i)
        const indexes = [...kept].sort((a, b) => a - b).slice(0, MAX_FINAL_POINTS - 1)
        if (indexes[indexes.length - 1] !== points.length - 1) indexes.push(points.length - 1)
        const indexMap = new Map(indexes.map((oldIndex, newIndex) => [oldIndex, newIndex]))
        const nearestMapped = oldIndex => {
            if (indexMap.has(oldIndex)) return indexMap.get(oldIndex)
            let best = 0
            let bestDelta = Infinity
            indexes.forEach((candidate, newIndex) => {
                const delta = Math.abs(candidate - oldIndex)
                if (delta < bestDelta) { bestDelta = delta; best = newIndex }
            })
            return best
        }
        return {
            points: indexes.map(index => ({lat: points[index].lat, lon: points[index].lon, accuracy: points[index].accuracy, t: points[index].t})),
            segments: state.segments.map(segment => ({...segment, start_index: nearestMapped(segment.start_index), end_index: nearestMapped(segment.end_index)}))
        }
    }

    const saveRecording = async event => {
        event.preventDefault()
        const distanceKm = Number(String(reviewDistance.value || '').replace(',', '.'))
        const durationS = parseDuration(reviewDuration.value)
        const elevation = reviewElevation.value === '' ? null : Number(String(reviewElevation.value).replace(',', '.'))
        if (!Number.isFinite(distanceKm) || distanceKm < 0 || durationS === null || durationS <= 0 || (elevation !== null && (!Number.isFinite(elevation) || elevation < 0))) {
            saveState.textContent = 'Revise distância, tempo e elevação antes de salvar.'
            saveState.className = 'gps-save-state is-error'
            return
        }
        if (!state.id) state.id = createRecordingId()
        const compact = compactForServer()
        const adjustedDistanceM = distanceKm * 1000
        const payload = {
            recording_id: state.id,
            idmodalidade: state.idmodalidade,
            title: reviewTitle.value.trim(),
            notes: reviewNotes.value.trim(),
            visibility: reviewVisibility.value,
            effort: reviewEffort.value,
            hide_route_start_m: Number(reviewHideStart.value || 0),
            hide_route_end_m: Number(reviewHideEnd.value || 0),
            started_at_ms: state.startedAtMs,
            ended_at_ms: state.endedAtMs || Date.now(),
            duration_s: durationS,
            measured_distance_m: state.distanceM,
            distance_m: adjustedDistanceM,
            elevation_gain_m: elevation,
            elevation_min_m: state.elevationMinM,
            elevation_max_m: state.elevationMaxM,
            points: compact.points,
            segments: compact.segments,
            points_received: state.pointsReceived,
            points_rejected: state.pointsRejected,
            accuracy_avg_m: state.accuracyCount ? state.accuracySum / state.accuracyCount : null,
            accuracy_best_m: state.accuracyBest,
            accuracy_worst_m: state.accuracyWorst,
            visibility_gaps: state.visibilityGaps,
            goal_type: state.goalType === 'none' ? null : state.goalType,
            goal_value: state.goalValue || null,
            ended_by_goal: state.endedByGoal,
            user_adjusted: Math.abs(adjustedDistanceM - state.distanceM) > 0.5 || durationS !== Math.round(activeElapsedMs(state.endedAtMs) / 1000) || (elevation !== null && Math.abs(elevation - state.elevationGainM) > 0.5)
        }
        const body = new URLSearchParams()
        body.set('csrf_token', root.dataset.csrfToken || '')
        body.set('recording', JSON.stringify(payload))
        const submit = saveForm.querySelector('button[type="submit"]')
        submit.disabled = true
        saveState.textContent = navigator.onLine ? 'Salvando…' : 'Sem internet. A gravação continua salva neste navegador; tente novamente quando a conexão voltar.'
        saveState.className = navigator.onLine ? 'gps-save-state' : 'gps-save-state is-error'
        if (!navigator.onLine) {
            pendingAutoSave = true
            submit.disabled = false
            schedulePersist(true)
            return
        }
        try {
            const fetcher = window.StrideBRNet?.fetch || fetch
            const response = await fetcher(root.dataset.saveEndpoint || '/api/gps-salvar.php', {method: 'POST', headers: {'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'}, body}, 20000)
            const result = await response.json().catch(() => ({}))
            if (!response.ok || !result.ok) throw new Error(result.error || 'Não foi possível salvar a atividade.')
            pendingAutoSave = false
            saveState.textContent = result.reused ? 'Atividade já estava salva; abrindo o registro existente.' : 'Atividade salva.'
            saveState.className = 'gps-save-state is-success'
            await idbDelete()
            window.location.href = `/user/atividades.php?saved=${encodeURIComponent(result.idregistro)}`
        } catch (error) {
            saveState.textContent = `${error?.message || 'Falha ao salvar.'} A gravação continua guardada neste navegador.`
            saveState.className = 'gps-save-state is-error'
            submit.disabled = false
            schedulePersist(true)
        }
    }

    const resumeSaved = async saved => {
        if (!saved || !['recording', 'paused', 'review'].includes(saved.status)) return
        state = {...freshState(), ...saved, points: Array.isArray(saved.points) ? saved.points : [], segments: Array.isArray(saved.segments) ? saved.segments : []}
        const option = [...sport.options].find(item => item.value === state.idmodalidade)
        if (option) {
            sport.value = state.idmodalidade
            sport.dispatchEvent(new Event('change', {bubbles: true}))
        }
        if (state.status === 'review') {
            fillReview()
            setView('review')
            return
        }
        if (state.status === 'recording') {
            const lastTrackedAt = Math.max(state.lastGoodPointAtMs || state.startedAtMs, state.startedAtMs)
            const gap = Date.now() - lastTrackedAt
            if (gap > 15000) {
                state.visibilityGaps++
                state.status = 'paused'
                state.pauseStartedMs = lastTrackedAt
                state.needsPositionAnchor = state.points.length > 0
            } else {
                startWatch()
            }
        }
        startTick()
        requestWakeLock()
        setView('live')
        updateLiveUi()
        initLiveMap()
        schedulePersist(true)
    }

    const discardSaved = async () => {
        if (!window.confirm('Descartar a gravação GPS salva neste navegador?')) return
        clearWatch()
        window.clearInterval(tickId)
        await releaseWakeLock()
        await idbDelete()
        state = freshState()
        restoreBox.hidden = true
        setView('setup')
    }

    const updateNetwork = () => {
        if (!networkEl) return
        networkEl.textContent = navigator.onLine ? 'Online' : 'Offline · salvo localmente'
        networkEl.dataset.online = navigator.onLine ? '1' : '0'
    }

    root.addEventListener('change', event => {
        if (event.target.matches('input[name="gps_goal_type"]')) updateGoalControls()
    })
    root.querySelectorAll('[data-gps-quick-sport]').forEach(button => button.addEventListener('click', () => {
        sport.value = button.dataset.gpsQuickSport || sport.value
        sport.dispatchEvent(new Event('change', {bubbles: true}))
    }))
    startButton?.addEventListener('click', startRecording)
    pauseButton?.addEventListener('click', togglePause)
    lapButton?.addEventListener('click', () => finalizeCurrentSegment(false))
    finishButton?.addEventListener('click', () => finishRecording(false))
    saveForm?.addEventListener('submit', saveRecording)
    backLive?.addEventListener('click', () => {
        state.status = 'paused'
        state.pauseStartedMs = state.endedAtMs || Date.now()
        state.endedAtMs = 0
        state.endedByGoal = false
        state.needsPositionAnchor = state.points.length > 0
        setView('live')
        startWatch()
        startTick()
        requestWakeLock()
        updateLiveUi()
        schedulePersist(true)
    })
    resumeButton?.addEventListener('click', async () => resumeSaved(await idbGet()))
    discardSavedButton?.addEventListener('click', discardSaved)

    window.addEventListener('online', () => {
        updateNetwork()
        if (pendingAutoSave && state.status === 'review' && saveForm) {
            pendingAutoSave = false
            saveForm.requestSubmit()
        }
    })
    window.addEventListener('offline', updateNetwork)
    document.addEventListener('visibilitychange', () => {
        if (!['recording', 'paused'].includes(state.status)) return
        if (document.visibilityState === 'hidden') {
            state.hiddenSinceMs = Date.now()
            schedulePersist(true)
            return
        }
        if (state.hiddenSinceMs) {
            const hiddenFor = Date.now() - state.hiddenSinceMs
            if (hiddenFor > 5000) state.visibilityGaps++
            state.hiddenSinceMs = 0
            schedulePersist(true)
        }
        requestWakeLock()
        updateLiveUi()
    })
    window.addEventListener('pagehide', () => {
        if (['recording', 'paused', 'review'].includes(state.status)) schedulePersist(true)
    })
    window.addEventListener('beforeunload', event => {
        if (!['recording', 'paused'].includes(state.status)) return
        schedulePersist(true)
        event.preventDefault()
        event.returnValue = ''
    })

    updateGoalControls()
    updateNetwork()
    idbGet().then(saved => {
        if (saved && ['recording', 'paused', 'review'].includes(saved.status)) {
            restoreBox.hidden = false
            const elapsed = saved.status === 'review'
                ? Math.max(0, ((saved.endedAtMs || Date.now()) - saved.startedAtMs - (saved.pausedTotalMs || 0)) / 1000)
                : Math.max(0, (Date.now() - saved.startedAtMs - (saved.pausedTotalMs || 0)) / 1000)
            restoreSummary.textContent = `${saved.sportName || 'Atividade'} · ${formatDuration(elapsed)} · ${((saved.distanceM || 0) / 1000).toFixed(2).replace('.', ',')} km`
        } else if (root.dataset.autostart === '1') {
            window.setTimeout(() => startRecording(), 120)
        }
    })

    window.StrideBRGPSMath = {haversine, formatDuration, parseDuration, accuracyQuality}
})()
