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
        return `${decimal(meters, meters % 1 === 0 ? 0 : 2)} m`
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
    const repTargetText = reps => {
        const target = reps && typeof reps === 'object' ? reps : {}
        if (target.mode === 'range') return `${target.min ?? ''}–${target.max ?? ''} reps`
        if (target.mode === 'amrap') return 'AMRAP'
        if (target.mode === 'failure') return 'Até a falha'
        if (target.mode === 'legacy') return text(target.text)
        if (target.mode === 'fixed' && target.value !== null && target.value !== undefined && text(target.value) !== '') return `${target.value} reps`
        return ''
    }
    const structuredSummary = (method, config = {}) => {
        if (method === 'cluster') {
            const pattern = Array.isArray(config.clusters) ? config.clusters.map(item => item?.reps).filter(value => value !== null && value !== undefined && value !== '').join('+') : ''
            const load = config.load?.value !== undefined ? formatLoad(`${config.load.value}${config.load.unit ? ` ${config.load.unit}` : ''}`) : ''
            return [`${config.blocks || 1} blocos`, pattern, load, config.intra_cluster_rest_s !== undefined ? `${config.intra_cluster_rest_s} s intra` : '', config.between_blocks_rest_s !== undefined ? `${config.between_blocks_rest_s} s descanso` : ''].filter(Boolean).join(' · ')
        }
        if (method === 'drop_set') {
            const stages = Array.isArray(config.stages) ? config.stages.map(stage => {
                const load = stage?.load?.value !== undefined ? decimal(stage.load.value, 2) : ''
                const reps = repTargetText(stage?.reps || {}).replace(/ reps$/i, '')
                return `${load}${load && reps ? '×' : ''}${reps}`
            }).filter(Boolean) : []
            return `${Number(config.rounds || 1) > 1 ? `${config.rounds} rodadas · ` : ''}${stages.join(' → ')}`
        }
        const parts = []
        const reps = repTargetText(config.reps || {})
        if (config.sets) parts.push(`${config.sets} × ${reps || '—'}`)
        else if (reps) parts.push(reps)
        if (config.load?.value !== undefined && config.load?.value !== null && text(config.load.value) !== '') parts.push(formatLoad(`${config.load.value} ${config.load.unit || 'kg'}`))
        if (config.duration_s !== undefined && config.duration_s !== null && text(config.duration_s) !== '') parts.push(formatDurationSeconds(config.duration_s))
        if (config.distance_m !== undefined && config.distance_m !== null && text(config.distance_m) !== '') {
            const unit = config.distance_display_unit === 'km' ? 'km' : 'm'
            const meters = Number(config.distance_m)
            parts.push(unit === 'km' ? `${decimal(meters / 1000, 3)} km` : `${decimal(meters, 2)} m`)
        }
        if (config.rest_after_s !== undefined && config.rest_after_s !== null && text(config.rest_after_s) !== '') parts.push(`${config.rest_after_s} s`)
        return parts.filter(Boolean).join(' · ')
    }
    const structured = source => {
        const raw = source || {}
        let config = raw.prescription && typeof raw.prescription === 'object' ? raw.prescription : {}
        if (!Object.keys(config).length && raw.config_prescricao) { try { config = typeof raw.config_prescricao === 'string' ? JSON.parse(raw.config_prescricao) : raw.config_prescricao } catch (_) { config = {} } }
        const method = text(raw.prescription_method || raw.metodo_prescricao || config.method || 'standard') || 'standard'
        if (method === 'standard' && !Object.keys(config).length) return null
        const summary = structuredSummary(method, config)
        let fields = []
        if (method === 'cluster' || method === 'drop_set') fields = ['load','reps']
        else {
            if (config.load?.value !== undefined || config.load?.text) fields.push('load')
            if (repTargetText(config.reps || {})) fields.push('reps')
            if (config.duration_s !== undefined && config.duration_s !== null) fields.push('duration')
            if (config.distance_m !== undefined && config.distance_m !== null) fields.push('distance')
        }
        return {method, config, summary, fields, summaryParts: summary ? [summary] : []}
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
        structured,
        structuredSummary,
        repTargetText,
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
