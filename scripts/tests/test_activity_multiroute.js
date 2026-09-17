const fs = require('fs')
const vm = require('vm')
const path = require('path')

const root = path.resolve(__dirname, '../..')
const source = fs.readFileSync(path.join(root, 'public/assets/js/activity-detail-v3.js'), 'utf8')
const start = source.indexOf('    const routeCollections = activity => {')
const end = source.indexOf('    const routeHtml = (activity', start)
if (start < 0 || end < 0) throw new Error('routeCollections helper not found')
const helper = source.slice(start, end)
const sandbox = {}
vm.createContext(sandbox)
vm.runInContext(`${helper}\nthis.routeCollections = routeCollections`, sandbox)
const routeCollections = sandbox.routeCollections
let assertions = 0
const check = (condition, message) => { assertions += 1; if (!condition) throw new Error(message) }
const a = [[-53.1,-27.1],[-53.2,-27.2]]
const b = [[-53.4,-27.4],[-53.5,-27.5]]
const top = [[-53.0,-27.0],[-53.6,-27.6]]
let routes = routeCollections({rota:{geojson:{coordinates:top}},unidades:[{rota:{geojson:{coordinates:a}}},{rota:{geojson:{coordinates:b}}}]})
check(routes.length === 2, 'unit routes must replace top-level aggregate route when present')
check(JSON.stringify(routes[0].coordinates) === JSON.stringify(a), 'first unit route must remain independent')
check(JSON.stringify(routes[1].coordinates) === JSON.stringify(b), 'second unit route must remain independent')
check(!routes.some(route => JSON.stringify(route.coordinates) === JSON.stringify([...a,...b])), 'routes must not be concatenated')
routes = routeCollections({rota:{geojson:{coordinates:top}},unidades:[]})
check(routes.length === 1 && JSON.stringify(routes[0].coordinates) === JSON.stringify(top), 'top-level route remains fallback')
routes = routeCollections({unidades:[{rota:{geojson:{coordinates:a}}},{rota:{geojson:{coordinates:a}}}]})
check(routes.length === 1, 'duplicate unit geometry must render once')
console.log(`✓ activity multiroute: ${assertions} assertions`)
