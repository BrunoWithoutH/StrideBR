(() => {
    const rawConfig = (() => {
        try { return JSON.parse(document.getElementById('activity-sport-context-config')?.textContent || '{}') } catch (_) { return {} }
    })()
    const config = {
        distance_switch_m: Number(rawConfig.distance_switch_m || 1000),
        track_meters_max: Number(rawConfig.track_meters_max || 10000),
        series_abs_tolerance_m: Number(rawConfig.series_abs_tolerance_m || 2),
        series_rel_tolerance: Number(rawConfig.series_rel_tolerance || 0.01),
        nominal_distances_m: rawConfig.nominal_distances_m || {},
        track_slugs: Array.isArray(rawConfig.track_slugs) ? rawConfig.track_slugs : [],
        sprint_slugs: Array.isArray(rawConfig.sprint_slugs) ? rawConfig.sprint_slugs : [],
    }
    const normalizeSlug = value => String(value || '').trim().toLowerCase()
    const context = (input = {}) => {
        const slug = normalizeSlug(input.slug)
        const nominal = Number.isFinite(Number(config.nominal_distances_m[slug])) ? Number(config.nominal_distances_m[slug]) : null
        const isTrack = config.track_slugs.includes(slug) || /^(?:atletismo-)?(?:60|100|110|200|400|800|1000|1500|3000|5000|10000)m(?:-|$)/.test(slug)
        const isSprint = config.sprint_slugs.includes(slug) || (isTrack && nominal !== null && nominal <= 400)
        const registeredM = Number.isFinite(Number(input.registered_m)) && Number(input.registered_m) >= 0 ? Number(input.registered_m) : null
        const segment = Boolean(input.segment)
        return {
            slug,
            family: normalizeSlug(input.family),
            is_track: isTrack,
            is_sprint: isSprint,
            nominal_distance_m: nominal,
            registered_m: registeredM,
            segment,
            structured_series: Boolean(input.structured_series),
            series_unit: input.series_unit === 'm' ? 'm' : input.series_unit === 'km' ? 'km' : '',
            prefers_milliseconds: isSprint || (segment && registeredM !== null && registeredM > 0 && registeredM <= 400) || (isTrack && registeredM !== null && registeredM > 0 && registeredM <= 400),
            performance_priority: isSprint ? 'time' : isTrack ? 'time_pace' : 'default',
        }
    }
    const chooseDistanceUnit = (meters, inputContext = {}, manualUnit = '') => {
        if (manualUnit === 'm' || manualUnit === 'km') return manualUnit
        const ctx = context(inputContext)
        const value = Math.max(0, Number(meters) || 0)
        if (ctx.nominal_distance_m !== null && ctx.is_track && ctx.nominal_distance_m <= config.track_meters_max) return 'm'
        if (ctx.is_track && value > 0 && value <= config.track_meters_max) return 'm'
        if (ctx.segment && ctx.structured_series && ctx.series_unit === 'm') return 'm'
        if (value > 0 && value < config.distance_switch_m) return 'm'
        return 'km'
    }
    const convertDistance = (value, fromUnit, toUnit) => {
        const numeric = Number(String(value ?? '').replace(',', '.'))
        if (!Number.isFinite(numeric)) return null
        if (fromUnit === toUnit) return numeric
        if (fromUnit === 'm' && toUnit === 'km') return numeric / 1000
        if (fromUnit === 'km' && toUnit === 'm') return numeric * 1000
        return numeric
    }
    const metersFrom = (value, unit) => {
        const numeric = Number(String(value ?? '').replace(',', '.'))
        if (!Number.isFinite(numeric)) return null
        return unit === 'm' ? numeric : numeric * 1000
    }
    const inputNumber = (value, decimals = 3) => {
        if (!Number.isFinite(value)) return ''
        const fixed = value.toFixed(Math.max(0, decimals)).replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1')
        return fixed === '-0' ? '0' : fixed
    }
    const localeNumber = (value, decimals = 3, locale = document.documentElement.lang?.startsWith('en') ? 'en' : 'pt-BR') => {
        if (!Number.isFinite(Number(value))) return ''
        return new Intl.NumberFormat(locale === 'en' ? 'en-US' : 'pt-BR', {minimumFractionDigits: 0, maximumFractionDigits: Math.max(0, decimals)}).format(Number(value))
    }
    const formatDistance = (meters, inputContext = {}, locale = document.documentElement.lang?.startsWith('en') ? 'en' : 'pt-BR', manualUnit = '') => {
        const value = Math.max(0, Number(meters) || 0)
        const unit = chooseDistanceUnit(value, inputContext, manualUnit)
        if (unit === 'm') return `${localeNumber(value, 3, locale)} m`
        return `${localeNumber(value / 1000, 3, locale)} km`
    }
    const formatDuration = (seconds, locale = document.documentElement.lang?.startsWith('en') ? 'en' : 'pt-BR', forceClock = false) => {
        const totalMs = Math.max(0, Math.round((Number(seconds) || 0) * 1000))
        const h = Math.floor(totalMs / 3600000)
        const remH = totalMs % 3600000
        const m = Math.floor(remH / 60000)
        const remM = remH % 60000
        const s = Math.floor(remM / 1000)
        const ms = remM % 1000
        const fraction = ms ? `${locale === 'en' ? '.' : ','}${String(ms).padStart(3, '0').replace(/0+$/, '')}` : ''
        if (h > 0) return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}${fraction}`
        if (m > 0 || forceClock) return `${m}:${String(s).padStart(2, '0')}${fraction}`
        return `${s}${fraction} s`
    }
    const formatPace = (seconds, suffix = '/km') => {
        const value = Number(seconds)
        if (!Number.isFinite(value) || value <= 0) return ''
        const total = Math.round(value)
        return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}${suffix}`
    }
    const equivalentSeries = (values, inputContext = {}) => {
        const meters = (Array.isArray(values) ? values : []).map(Number).filter(value => Number.isFinite(value) && value > 0).sort((a, b) => a - b)
        if (meters.length < 2) return null
        const mid = Math.floor(meters.length / 2)
        let median = meters.length % 2 ? meters[mid] : (meters[mid - 1] + meters[mid]) / 2
        const tolerance = Math.max(config.series_abs_tolerance_m, Math.abs(median) * config.series_rel_tolerance)
        if (meters.some(value => Math.abs(value - median) > tolerance)) return null
        const ctx = context(inputContext)
        if (ctx.nominal_distance_m !== null && Math.abs(median - ctx.nominal_distance_m) <= Math.max(tolerance, ctx.nominal_distance_m * 0.01)) median = ctx.nominal_distance_m
        const rounded = Math.abs(median - Math.round(median)) < 0.25 ? Math.round(median) : Math.round(median * 10) / 10
        const seriesContext = {...ctx, segment: true, structured_series: true, series_unit: ctx.is_track || rounded < 1000 ? 'm' : chooseDistanceUnit(rounded, ctx)}
        return {count: meters.length, distance_m: rounded, unit: chooseDistanceUnit(rounded, seriesContext), tolerance_m: tolerance}
    }
    window.StrideBRActivitySportContext = {config, context, chooseDistanceUnit, convertDistance, metersFrom, inputNumber, localeNumber, formatDistance, formatDuration, formatPace, equivalentSeries}
})()
