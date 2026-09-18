(() => {
    const text = value => String(value ?? '').trim()
    const first = (source, keys) => {
        for (const key of keys) {
            if (!Object.prototype.hasOwnProperty.call(source || {}, key)) continue
            const value = source[key]
            if (value !== null && value !== undefined && text(value) !== '') return value
        }
        return null
    }
    const number = value => {
        const raw = text(value).replace(',', '.')
        if (!raw || !/^[-+]?\d+(?:\.\d+)?$/.test(raw)) return null
        const parsed = Number(raw)
        return Number.isFinite(parsed) ? parsed : null
    }
    const decimal = (value, maximum = 2) => Number(value).toLocaleString('pt-BR', {maximumFractionDigits: maximum})
    const durationSeconds = value => {
        if (value === null || value === undefined || value === '') return null
        if (typeof value === 'number' && Number.isFinite(value)) return Math.max(0, value)
        const raw = text(value).toLowerCase().replace(',', '.')
        if (!raw) return null
        let match = raw.match(/^(\d{1,3}):([0-5]\d)(?::([0-5]\d))?$/)
        if (match) return match[3] === undefined ? Number(match[1]) * 60 + Number(match[2]) : Number(match[1]) * 3600 + Number(match[2]) * 60 + Number(match[3])
        match = raw.match(/^(\d+(?:\.\d+)?)\s*(h|hr|hrs|hora|horas)$/)
        if (match) return Number(match[1]) * 3600
        match = raw.match(/^(\d+(?:\.\d+)?)\s*(m|min|mins|minuto|minutos|mn)$/)
        if (match) return Number(match[1]) * 60
        match = raw.match(/^(\d+(?:\.\d+)?)\s*(s|seg|segs|segundo|segundos)$/)
        if (match) return Number(match[1])
        return null
    }
    const distanceMeters = value => {
        if (value === null || value === undefined || value === '') return null
        if (typeof value === 'number' && Number.isFinite(value)) return Math.max(0, value)
        const raw = text(value).toLowerCase().replace(',', '.')
        if (!raw) return null
        let match = raw.match(/^(\d+(?:\.\d+)?)\s*(km|quil[oô]metros?)$/)
        if (match) return Number(match[1]) * 1000
        match = raw.match(/^(\d+(?:\.\d+)?)\s*(m|metros?)$/)
        if (match) return Number(match[1])
        return null
    }
    const looksLikeDuration = value => durationSeconds(value) !== null && /[a-z:]|\s/i.test(text(value))
    const looksLikeDistance = value => distanceMeters(value) !== null && /[a-z]/i.test(text(value))
    const formatDurationSeconds = seconds => {
        const total = Math.max(0, Math.round(Number(seconds) || 0))
        const hours = Math.floor(total / 3600)
        const minutes = Math.floor((total % 3600) / 60)
        const secs = total % 60
        if (hours > 0) return [hours ? `${hours} h` : '', minutes ? `${minutes} min` : '', secs ? `${secs} s` : ''].filter(Boolean).join(' ')
        if (minutes > 0) return [minutes ? `${minutes} min` : '', secs ? `${secs} s` : ''].filter(Boolean).join(' ')
        return `${secs} s`
    }
    const formatDuration = value => {
        const seconds = durationSeconds(value)
        return seconds === null ? text(value) : formatDurationSeconds(seconds)
    }
    const formatDistance = value => {
        const meters = distanceMeters(value)
        if (meters === null) return text(value)
        if (meters >= 1000) return `${decimal(meters / 1000, meters % 1000 === 0 ? 0 : 2)} km`
        return `${decimal(meters, meters % 1 === 0 ? 0 : 1)} m`
    }
    const formatLoad = value => {
        const raw = text(value)
        if (!raw) return ''
        if (/[a-z]/i.test(raw)) return raw
        const numeric = number(raw)
        return numeric === null ? raw : `${decimal(numeric, 2)} kg`
    }
    const formatReps = value => {
        const raw = text(value)
        if (!raw) return ''
        if (/\b(?:rep|reps|repeti(?:ção|ções|cao|coes))\b/i.test(raw)) return raw
        const numeric = number(raw)
        return numeric === null ? raw : `${decimal(numeric, 0)} reps`
    }
    const resolve = source => {
        const raw = source || {}
        let reps = first(raw, ['repetitions', 'repeticoes', 'repeticoes_snapshot', 'planned_repetitions'])
        let duration = first(raw, ['duration_s', 'planned_duration_s', 'duration', 'duracao', 'duracao_snapshot'])
        let distance = first(raw, ['distance_m', 'planned_distance_m', 'distance', 'distancia', 'distancia_snapshot'])
        const load = first(raw, ['load', 'carga', 'carga_snapshot', 'planned_load'])

        if ((duration === null || text(duration) === '') && reps !== null && looksLikeDuration(reps)) {
            duration = reps
            reps = null
        }
        if ((distance === null || text(distance) === '') && reps !== null && looksLikeDistance(reps)) {
            distance = reps
            reps = null
        }

        const has = {
            load: load !== null && text(load) !== '',
            reps: reps !== null && text(reps) !== '',
            duration: duration !== null && text(duration) !== '',
            distance: distance !== null && text(distance) !== '',
        }
        let mode = 'EMPTY'
        if (has.load && has.duration && has.distance) mode = 'LOAD_DURATION_DISTANCE'
        else if (has.duration && has.distance) mode = 'DURATION_DISTANCE'
        else if (has.load && has.reps) mode = 'LOAD_REPS'
        else if (has.load && has.duration) mode = 'LOAD_DURATION'
        else if (has.load && has.distance) mode = 'LOAD_DISTANCE'
        else if (has.reps) mode = 'REPS'
        else if (has.duration) mode = 'DURATION'
        else if (has.distance) mode = 'DISTANCE'
        else if (has.load) mode = 'LOAD'

        const values = {
            load: has.load ? formatLoad(load) : '',
            reps: has.reps ? formatReps(reps) : '',
            duration: has.duration ? formatDuration(duration) : '',
            distance: has.distance ? formatDistance(distance) : '',
        }
        const fields = ['load', 'reps', 'duration', 'distance'].filter(key => has[key])
        const labels = {load:'Carga', reps:'Reps', duration:'Duração', distance:'Distância'}
        return {
            mode,
            fields,
            values,
            labels,
            raw: {load, reps, duration, distance},
            summaryParts: fields.map(key => values[key]).filter(Boolean),
        }
    }
    globalThis.StrideBRWorkoutPrescription = {
        resolve,
        loggingFields: prescription => {
            const fields = [...(prescription?.fields || [])]
            if (!fields.length) return ['load','reps']
            if (fields.includes('reps') && !fields.includes('load')) fields.unshift('load')
            return fields
        },
        formatDuration,
        formatDurationSeconds,
        formatDistance,
        formatLoad,
        formatReps,
        durationSeconds,
        distanceMeters,
    }
})()
