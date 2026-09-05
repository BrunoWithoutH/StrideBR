(() => {
    const MAX_POINTS = 2000
    const EPS = 1e-7
    const normalizePoint = point => Array.isArray(point) && Number.isFinite(Number(point[0])) && Number.isFinite(Number(point[1]))
        ? [Number(Number(point[0]).toFixed(7)), Number(Number(point[1]).toFixed(7))]
        : null
    const normalizePoints = points => (Array.isArray(points) ? points : []).map(normalizePoint).filter(Boolean)
    const samePoint = (a, b, epsilon = EPS) => Boolean(a && b) && Math.abs(a[0] - b[0]) <= epsilon && Math.abs(a[1] - b[1]) <= epsilon
    const closeCircuit = points => {
        const base = normalizePoints(points)
        if (base.length < 3) return base
        if (!samePoint(base[0], base.at(-1))) base.push([...base[0]])
        else base[base.length - 1] = [...base[0]]
        return base
    }
    const haversine = (a, b) => {
        const toRad = value => value * Math.PI / 180
        const lat1 = toRad(a[1]); const lat2 = toRad(b[1])
        const dLat = lat2 - lat1; const dLon = toRad(b[0] - a[0])
        const h = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon / 2) ** 2
        return 6371000 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h))
    }
    const distanceMeters = points => {
        const list = normalizePoints(points)
        let total = 0
        for (let i = 1; i < list.length; i += 1) total += haversine(list[i - 1], list[i])
        return total
    }
    const expandedPointCount = (basePoints, laps) => {
        const closed = closeCircuit(basePoints)
        const count = Math.max(1, Math.trunc(Number(laps) || 1))
        return closed.length < 2 ? closed.length : 1 + (closed.length - 1) * count
    }
    const expandCircuit = (basePoints, laps, maxPoints = MAX_POINTS) => {
        const closed = closeCircuit(basePoints)
        const count = Math.max(1, Math.trunc(Number(laps) || 1))
        if (closed.length < 4) return {ok: false, reason: 'not_closed', coordinates: [], base: closed, laps: count, pointCount: closed.length}
        const pointCount = expandedPointCount(closed, count)
        if (pointCount > maxPoints) return {ok: false, reason: 'point_limit', coordinates: [], base: closed, laps: count, pointCount}
        const coordinates = [closed[0]]
        for (let lap = 0; lap < count; lap += 1) coordinates.push(...closed.slice(1).map(point => [...point]))
        return {ok: true, coordinates, base: closed, laps: count, pointCount}
    }
    const detectCircuit = points => {
        const list = normalizePoints(points)
        if (list.length < 4 || !samePoint(list[0], list.at(-1))) return null
        const start = list[0]
        const closes = []
        for (let i = 1; i < list.length; i += 1) if (samePoint(list[i], start)) closes.push(i)
        if (!closes.length) return null
        for (const period of closes) {
            if (period < 3) continue
            const laps = (list.length - 1) / period
            if (!Number.isInteger(laps) || laps < 2) continue
            const base = list.slice(0, period + 1)
            const expanded = expandCircuit(base, laps, Math.max(MAX_POINTS, list.length))
            if (!expanded.ok || expanded.coordinates.length !== list.length) continue
            if (!expanded.coordinates.every((point, index) => samePoint(point, list[index]))) continue
            return {base, laps, closed: true}
        }
        return null
    }
    const geojson = coordinates => {
        const list = normalizePoints(coordinates)
        return list.length >= 2 ? {type: 'LineString', coordinates: list} : null
    }
    window.StrideBRRoute = {MAX_POINTS, normalizePoints, samePoint, closeCircuit, haversine, distanceMeters, expandedPointCount, expandCircuit, detectCircuit, geojson}
})()
