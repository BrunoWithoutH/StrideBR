(() => {
    const root = document.querySelector('[data-gps-recorder]')
    if (!root) return

    const $ = selector => root.querySelector(selector)
    const t = (key, values = {}, fallback = null) => window.StrideBRI18n?.t?.(key, values, fallback) ?? fallback ?? key
    const tn = (oneKey, otherKey, count, values = {}) => window.StrideBRI18n?.tn?.(oneKey, otherKey, count, values) ?? t(Number(count) === 1 ? oneKey : otherKey, {...values, count})
    const formatNumber = (value, decimals = 0, trimZeros = false) => window.StrideBRI18n?.number?.(value, decimals, trimZeros) ?? Number(value || 0).toFixed(decimals)
    const localizedSport = (slug, fallback = '') => window.StrideBRI18n?.sport?.(slug, fallback) ?? fallback
    const localeDecimal = () => window.StrideBRI18n?.locale === 'en' ? '.' : ','
    const setup = $('[data-gps-setup]')
    const live = $('[data-gps-live]')
    const review = $('[data-gps-review]')
    const sport = $('[data-gps-sport]')
    const startButton = $('[data-gps-start]')
    const pauseButton = $('[data-gps-pause]')
    const lapButton = $('[data-gps-lap]')
    const finishButton = $('[data-gps-finish]')
    const discardCurrentButton = $('[data-gps-discard-current]')
    const discardReviewButton = $('[data-gps-discard-review]')
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
    const wakeLockFallback = $('[data-gps-wakelock-fallback]')
    const liveControls = $('[data-gps-live-controls]')
    const lockButton = $('[data-gps-lock]')
    const lockState = $('[data-gps-controls-lock-state]')
    const unlockButton = $('[data-gps-unlock]')
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
    let persistChain = Promise.resolve()
    let wakeLock = null
    let controlsLocked = false
    let unlockHoldStartedAt = 0
    let unlockHoldFrame = 0
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

    const queuePersist = value => {
        const snapshot = JSON.parse(JSON.stringify(value))
        persistChain = persistChain.then(() => idbPut(snapshot)).catch(() => {})
        return persistChain
    }

    const schedulePersist = immediate => {
        window.clearTimeout(persistTimer)
        persistTimer = 0
        if (immediate) {
            queuePersist(state)
            return
        }
        persistTimer = window.setTimeout(() => {
            persistTimer = 0
            queuePersist(state)
        }, 2500)
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
        return {slug, metric, maxSpeed, maxAccuracy, name: localizedSport(slug, option?.textContent?.trim() || t('gps.sport_fallback'))}
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

    const formatPreciseDuration = seconds => {
        const totalMilliseconds = Math.max(0, Math.round(Number(seconds || 0) * 1000))
        const hours = Math.floor(totalMilliseconds / 3600000)
        const minutes = Math.floor((totalMilliseconds % 3600000) / 60000)
        const secs = Math.floor((totalMilliseconds % 60000) / 1000)
        const milliseconds = totalMilliseconds % 1000
        const fraction = milliseconds ? `${localeDecimal()}${String(milliseconds).padStart(3, '0')}` : ''
        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}${fraction}`
    }

    const parseDuration = raw => {
        const value = String(raw || '').trim().replace(',', '.')
        const match = value.match(/^(?:(\d+):)?(\d{1,2}):(\d{1,2})(?:\.(\d{1,3}))?$/)
        if (!match) return null
        const hours = Number(match[1] || 0)
        const minutes = Number(match[2])
        const seconds = Number(match[3])
        if (minutes >= 60 || seconds >= 60) return null
        const milliseconds = match[4] ? Number(match[4].padEnd(3, '0')) : 0
        return Math.round((hours * 3600 + minutes * 60 + seconds + milliseconds / 1000) * 1000) / 1000
    }

    const activeElapsedMs = (now = Date.now()) => {
        if (!state.startedAtMs) return 0
        let paused = state.pausedTotalMs || 0
        if (state.status === 'paused' && state.pauseStartedMs) paused += Math.max(0, now - state.pauseStartedMs)
        return Math.max(0, now - state.startedAtMs - paused)
    }

    const accuracyQuality = accuracy => {
        if (!Number.isFinite(accuracy)) return {key: 'waiting', label: t('gps.accuracy_waiting')}
        if (accuracy <= 12) return {key: 'good', label: t('gps.accuracy_very_good')}
        if (accuracy <= 25) return {key: 'good', label: t('gps.accuracy_good')}
        if (accuracy <= 45) return {key: 'fair', label: t('gps.accuracy_fair')}
        return {key: 'poor', label: t('gps.accuracy_poor')}
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
            window.StrideBRBasemaps?.attach(liveMap, {initial: 'street', remember: false})
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
                window.StrideBRBasemaps?.attach(reviewMapInstance, {initial: 'street', remember: false})
                reviewLine = window.L.polyline([], {weight: 5, opacity: .9}).addTo(reviewMapInstance)
            }
            const latlngs = state.points.map(point => [point.lat, point.lon])
            reviewLine.setLatLngs(latlngs)
            reviewMapInstance.fitBounds(reviewLine.getBounds(), {padding: [20, 20], maxZoom: 16})
            window.setTimeout(() => reviewMapInstance?.invalidateSize(), 60)
        } catch (_) {
            reviewMap.textContent = t('gps.map_load_failed')
        }
    }

    const updateWakeLockAvailability = () => {
        if (wakeLockFallback) wakeLockFallback.hidden = 'wakeLock' in navigator
    }

    const requestWakeLock = async () => {
        if (state.status !== 'recording' || !('wakeLock' in navigator) || document.visibilityState !== 'visible') return
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

    const setUnlockProgress = value => {
        const progress = Math.max(0, Math.min(1, Number(value) || 0))
        unlockButton?.style.setProperty('--gps-unlock-progress', `${Math.round(progress * 100)}%`)
    }

    const cancelUnlockHold = () => {
        unlockHoldStartedAt = 0
        if (unlockHoldFrame) window.cancelAnimationFrame(unlockHoldFrame)
        unlockHoldFrame = 0
        setUnlockProgress(0)
    }

    const setControlsLocked = locked => {
        controlsLocked = Boolean(locked)
        live?.classList.toggle('is-controls-locked', controlsLocked)
        document.body.classList.toggle('gps-controls-locked', controlsLocked)
        if (liveControls) liveControls.hidden = controlsLocked
        if (lockState) lockState.hidden = !controlsLocked
        lockButton?.setAttribute('aria-pressed', controlsLocked ? 'true' : 'false')
        if (!controlsLocked) cancelUnlockHold()
    }

    const finishUnlockHold = () => {
        cancelUnlockHold()
        setControlsLocked(false)
        lockButton?.focus?.({preventScroll: true})
    }

    const runUnlockHold = now => {
        if (!controlsLocked || !unlockHoldStartedAt) return
        const progress = (now - unlockHoldStartedAt) / 1200
        setUnlockProgress(progress)
        if (progress >= 1) {
            finishUnlockHold()
            return
        }
        unlockHoldFrame = window.requestAnimationFrame(runUnlockHold)
    }

    const startUnlockHold = () => {
        if (!controlsLocked || unlockHoldStartedAt) return
        unlockHoldStartedAt = performance.now()
        setUnlockProgress(0)
        unlockHoldFrame = window.requestAnimationFrame(runUnlockHold)
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
            ? t('gps.location_denied')
            : error?.code === 2
                ? t('gps.location_unavailable')
                : t('gps.location_timeout')
        if (qualityEl) {
            qualityEl.dataset.quality = 'lost'
            qualityEl.textContent = t('gps.no_signal')
        }
        if (root.querySelector('[data-gps-live-warning]')) root.querySelector('[data-gps-live-warning]').textContent = `${message} ${t('gps.review_later')}`
        if (permissionDenied && ['recording', 'paused', 'starting'].includes(state.status)) {
            clearWatch()
            window.clearInterval(tickId)
            releaseWakeLock()
            state = freshState()
            idbDelete().catch(() => {})
            startButton.disabled = false
            startButton.textContent = `▶ ${t('gps.try_again')}`
            setView('setup')
            return
        }
        if (state.status === 'idle' || state.status === 'starting') {
            startButton.disabled = false
            startButton.textContent = `▶ ${t('gps.try_again')}`
            state.status = 'idle'
            setView('setup')
        }
    }

    const startWatch = () => {
        if (!navigator.geolocation) throw new Error(t('gps.geolocation_api_unavailable'))
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
        if (view !== 'live') setControlsLocked(false)
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
            return `${formatNumber(kmh, 1)} km/h`
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
            goalProgressEl.textContent = `${formatNumber(state.distanceM / 1000, 2)} / ${formatNumber(state.goalValue / 1000, 2)} km`
        } else {
            goalProgressEl.textContent = `${formatDuration(activeElapsedMs() / 1000)} / ${formatDuration(state.goalValue)}`
        }
    }

    const updateLiveUi = () => {
        const elapsedSeconds = activeElapsedMs() / 1000
        if (timeEl) timeEl.textContent = formatDuration(elapsedSeconds)
        if (distanceEl) distanceEl.textContent = `${formatNumber(state.distanceM / 1000, 2)} km`
        if (paceEl) paceEl.textContent = formatPaceOrSpeed()
        if (paceLabel) paceLabel.textContent = state.metric === 'velocidade_kmh' || /cicl|bike|bmx|gravel/.test(state.sportSlug) ? t('gps.speed') : t('gps.pace')
        if (elevationEl) elevationEl.textContent = state.lastSmoothAltitude === null ? '— m' : `+${Math.round(state.elevationGainM)} m`
        const quality = accuracyQuality(state.currentAccuracy)
        if (qualityEl) {
            qualityEl.dataset.quality = quality.key
            qualityEl.textContent = quality.label
        }
        if (accuracyEl) accuracyEl.textContent = Number.isFinite(state.currentAccuracy) ? `${Math.round(state.currentAccuracy)} m` : '— m'
        if (accuracyLargeEl) accuracyLargeEl.textContent = Number.isFinite(state.currentAccuracy) ? `± ${Math.round(state.currentAccuracy)} m` : '—'
        if (liveSportEl) liveSportEl.textContent = localizedSport(state.sportSlug, state.sportName || t('gps.sport_fallback'))
        if (pauseButton) pauseButton.textContent = state.status === 'paused' ? t('gps.continue') : t('gps.pause')
        const currentLap = state.segments.length + 1
        if (currentLapEl) currentLapEl.textContent = String(currentLap)
        const lapDistance = Math.max(0, state.distanceM - state.segmentStartDistanceM)
        const lapDuration = Math.max(0, activeElapsedMs() - state.segmentStartActiveMs) / 1000
        if (currentLapMetrics) currentLapMetrics.textContent = `${formatNumber(lapDistance / 1000, 2)} km · ${formatDuration(lapDuration).replace(/^00:/, '')}`
        updateGoalUi()
    }

    const finalizeCurrentSegment = force => {
        if (!state.points.length) return
        const endIndex = state.points.length - 1
        const distance = Math.max(0, state.distanceM - state.segmentStartDistanceM)
        const duration = Math.max(0, Math.round(activeElapsedMs() - state.segmentStartActiveMs) / 1000)
        if (!force && distance < 5 && duration < 5) return
        if (force && state.segments.length > 0 && distance < 1 && duration < 2) return
        state.segments.push({
            label: t('gps.segment_number', {number: state.segments.length + 1}),
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
                qualityEl.textContent = t('gps.no_update')
            }
        }, 500)
    }

    const startRecording = async () => {
        if (state.status === 'recording' || state.status === 'paused') return
        const goal = readGoal()
        if (!goal) {
            goalNumber?.focus()
            goalNumber?.setCustomValidity(t('gps.invalid_goal'))
            goalNumber?.reportValidity()
            goalNumber?.setCustomValidity('')
            return
        }
        if (!window.isSecureContext) {
            window.alert(t('gps.https_required'))
            return
        }
        if (!navigator.geolocation) {
            window.alert(t('gps.geolocation_unavailable'))
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
        startButton.textContent = t('gps.getting_signal')
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
            startButton.textContent = `▶ ${t('gps.start')}`
            window.alert(t('gps.permission_blocked'))
        }
    }

    const togglePause = async () => {
        if (state.status === 'recording') {
            state.status = 'paused'
            state.pauseStartedMs = Date.now()
            schedulePersist(true)
            updateLiveUi()
            await releaseWakeLock()
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
            requestWakeLock()
        }
    }

    const finishRecording = async endedByGoal => {
        if (!['recording', 'paused'].includes(state.status)) return
        if (state.status === 'paused' && state.pauseStartedMs) {
            state.pausedTotalMs += Math.max(0, Date.now() - state.pauseStartedMs)
            state.pauseStartedMs = 0
        }
        if (state.points.length < 2) {
            window.alert(t('gps.not_enough_points'))
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
        if (!Number.isFinite(avg)) return t('gps.quality_insufficient')
        if (avg <= 15 && state.visibilityGaps === 0) return t('gps.quality_good_recording')
        if (avg <= 30 && state.visibilityGaps <= 1) return t('gps.quality_fair_recording')
        return t('gps.quality_review')
    }

    const fillReview = () => {
        const durationS = Math.max(0.001, Math.round(activeElapsedMs(state.endedAtMs || Date.now())) / 1000)
        reviewTitle.value = localizedSport(state.sportSlug, state.sportName || t('gps.sport_fallback'))
        reviewDistance.value = formatNumber(state.distanceM / 1000, 2)
        reviewDuration.value = formatPreciseDuration(durationS)
        reviewElevation.value = state.lastSmoothAltitude === null ? '' : String(Math.max(0, Math.round(state.elevationGainM)))
        reviewQuality.textContent = qualityText()
        const avg = state.accuracyCount ? state.accuracySum / state.accuracyCount : null
        qualitySummary.innerHTML = [
            [t('gps.avg_accuracy'), Number.isFinite(avg) ? `± ${Math.round(avg)} m` : '—'],
            [t('gps.best_accuracy'), Number.isFinite(state.accuracyBest) ? `± ${Math.round(state.accuracyBest)} m` : '—'],
            [t('gps.points_accepted'), String(state.points.length)],
            [t('gps.points_rejected'), String(state.pointsRejected)],
            [t('gps.visibility_gaps'), String(state.visibilityGaps)],
            [t('gps.source'), 'GPS Web']
        ].map(([label, value]) => `<div><span>${label}</span><strong>${value}</strong></div>`).join('')
        if (reviewWarning) {
            const warnings = []
            if (state.visibilityGaps > 0) warnings.push(t('gps.warning_hidden'))
            if (Number.isFinite(avg) && avg > 30) warnings.push(t('gps.warning_accuracy'))
            if (state.pointsRejected > state.points.length) warnings.push(t('gps.warning_rejected'))
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
            row.querySelector('b').textContent = segment.label || t('gps.segment_number', {number: index + 1})
            row.querySelector('span').textContent = `${formatNumber(Number(segment.distance_m || 0) / 1000, 2)} km · ${(window.StrideBRI18n?.duration?.(segment.duration_s || 0, true) || formatPreciseDuration(segment.duration_s || 0)).replace(/^0:/, '')}`
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
            saveState.textContent = t('gps.review_invalid_metrics')
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
            user_adjusted: Math.abs(adjustedDistanceM - state.distanceM) > 0.5 || Math.abs(durationS - Math.round(activeElapsedMs(state.endedAtMs)) / 1000) > 0.0005 || (elevation !== null && Math.abs(elevation - state.elevationGainM) > 0.5)
        }
        const body = new URLSearchParams()
        body.set('csrf_token', root.dataset.csrfToken || '')
        body.set('recording', JSON.stringify(payload))
        const submit = saveForm.querySelector('button[type="submit"]')
        submit.disabled = true
        saveState.textContent = navigator.onLine ? t('gps.saving') : t('gps.offline_saved')
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
            if (!response.ok || !result.ok) throw new Error(result.error || t('gps.save_failed'))
            pendingAutoSave = false
            saveState.textContent = result.reused ? t('gps.already_saved') : t('gps.saved')
            saveState.className = 'gps-save-state is-success'
            await idbDelete()
            window.location.href = `/user/atividades.php?saved=${encodeURIComponent(result.idregistro)}`
        } catch (error) {
            saveState.textContent = t('gps.save_error_kept', {message: error?.message || t('gps.save_error')})
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
        if (state.status === 'recording') requestWakeLock()
        setView('live')
        updateLiveUi()
        initLiveMap()
        schedulePersist(true)
    }

    const resetLocalRecording = async () => {
        clearWatch()
        window.clearInterval(tickId)
        tickId = null
        window.clearTimeout(persistTimer)
        persistTimer = 0
        pendingAutoSave = false
        await releaseWakeLock()
        await persistChain
        await idbDelete()
        state = freshState()
        if (restoreBox) restoreBox.hidden = true
        if (startButton) {
            startButton.disabled = false
            startButton.textContent = t('gps.start')
        }
        setView('setup')
    }

    const discardCurrent = async () => {
        if (!state.startedAtMs && !['recording', 'paused', 'review', 'starting'].includes(state.status)) return
        const message = state.status === 'review'
            ? t('gps.discard_review_confirm')
            : t('gps.discard_current_confirm')
        if (!window.confirm(message)) return
        await resetLocalRecording()
        window.StrideBRUI?.notify(t('gps.discarded'), 'info', 2600)
    }

    const discardSaved = async () => {
        if (!window.confirm(t('gps.discard_saved_confirm'))) return
        await resetLocalRecording()
        window.StrideBRUI?.notify(t('gps.discarded'), 'info', 2600)
    }

    const updateNetwork = () => {
        if (!networkEl) return
        networkEl.textContent = navigator.onLine ? t('gps.online') : t('gps.offline_local')
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
    lockButton?.addEventListener('click', () => {
        if (['recording', 'paused'].includes(state.status)) setControlsLocked(true)
    })
    unlockButton?.addEventListener('pointerdown', event => {
        if (!controlsLocked) return
        event.preventDefault()
        unlockButton.setPointerCapture?.(event.pointerId)
        startUnlockHold()
    })
    unlockButton?.addEventListener('pointerup', cancelUnlockHold)
    unlockButton?.addEventListener('pointercancel', cancelUnlockHold)
    unlockButton?.addEventListener('lostpointercapture', cancelUnlockHold)
    unlockButton?.addEventListener('keydown', event => {
        if (!['Enter', ' '].includes(event.key) || event.repeat) return
        event.preventDefault()
        startUnlockHold()
    })
    unlockButton?.addEventListener('keyup', event => {
        if (!['Enter', ' '].includes(event.key)) return
        event.preventDefault()
        cancelUnlockHold()
    })
    unlockButton?.addEventListener('click', event => {
        event.preventDefault()
        if (controlsLocked && event.detail === 0) finishUnlockHold()
    })
    ;['click', 'pointerdown', 'touchstart', 'touchmove', 'wheel', 'keydown'].forEach(type => live?.addEventListener(type, event => {
        if (!controlsLocked || event.target.closest?.('[data-gps-unlock]')) return
        event.preventDefault()
        event.stopImmediatePropagation()
    }, {capture: true, passive: false}))
    lapButton?.addEventListener('click', () => finalizeCurrentSegment(false))
    finishButton?.addEventListener('click', () => finishRecording(false))
    discardCurrentButton?.addEventListener('click', discardCurrent)
    discardReviewButton?.addEventListener('click', discardCurrent)
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

    updateWakeLockAvailability()
    updateGoalControls()
    updateNetwork()
    idbGet().then(saved => {
        if (saved && ['recording', 'paused', 'review'].includes(saved.status)) {
            restoreBox.hidden = false
            const elapsed = saved.status === 'review'
                ? Math.max(0, ((saved.endedAtMs || Date.now()) - saved.startedAtMs - (saved.pausedTotalMs || 0)) / 1000)
                : Math.max(0, (Date.now() - saved.startedAtMs - (saved.pausedTotalMs || 0)) / 1000)
            restoreSummary.textContent = `${localizedSport(saved.sportSlug, saved.sportName || t('gps.sport_fallback'))} · ${formatDuration(elapsed)} · ${formatNumber((saved.distanceM || 0) / 1000, 2)} km`
        } else if (root.dataset.autostart === '1') {
            window.setTimeout(() => startRecording(), 120)
        }
    })

    window.StrideBRGPSMath = {haversine, formatDuration, parseDuration, accuracyQuality}
})()
