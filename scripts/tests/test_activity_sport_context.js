const fs = require('fs')
const path = require('path')
const vm = require('vm')
const {execFileSync} = require('child_process')
const ROOT = path.resolve(__dirname, '..', '..')
const fixture = JSON.parse(fs.readFileSync(path.join(__dirname, 'fixtures/activity_sport_context.json'), 'utf8'))
const helperPath = path.join(ROOT, 'src/function/activity_sport_context.php').replace(/\\/g, '/')
const appPath = path.join(ROOT, 'src/includes/app.php').replace(/\\/g, '/')
const phpConfig = JSON.parse(execFileSync('php', ['-r', `require '${appPath}'; require '${helperPath}'; echo json_encode(atividadeContextoEsportivoConfig());`], {encoding:'utf8'}))
const configNode = {textContent: JSON.stringify(phpConfig)}
const sandbox = {
    window:{},
    document:{
        getElementById:id => id === 'activity-sport-context-config' ? configNode : null,
        documentElement:{lang:'pt-BR'}
    },
    Intl,
    Number,
    Math,
    String,
    Array,
    Boolean,
    JSON,
    console
}
vm.createContext(sandbox)
vm.runInContext(fs.readFileSync(path.join(ROOT, 'public/assets/js/activity-sport-context.js'), 'utf8'), sandbox)
const engine = sandbox.window.StrideBRActivitySportContext
let assertions = 0
const ok = (value, message) => {
    assertions++
    if (!value) throw new Error(`✗ activity sport context JS: ${message}`)
}
const near = (a,b,epsilon,message) => ok(Math.abs(a-b) <= epsilon, `${message}: ${a} vs ${b}`)
for (const item of fixture.distances) {
    const context = engine.context({slug:item.slug,registered_m:item.meters})
    ok(engine.formatDistance(item.meters, context, 'pt-BR') === item.pt, `${item.slug} distância pt-BR`)
    ok(engine.formatDistance(item.meters, context, 'en') === item.en, `${item.slug} distância en`)
}
for (const item of fixture.durations) {
    ok(engine.formatDuration(item.seconds, 'pt-BR', item.forceClock) === item.pt, `duração pt-BR ${item.seconds}`)
    ok(engine.formatDuration(item.seconds, 'en', item.forceClock) === item.en, `duração en ${item.seconds}`)
}
for (const item of fixture.paces) ok(engine.formatPace(item.seconds) === item.value, `ritmo ${item.seconds}`)
for (const item of fixture.series) {
    const series = engine.equivalentSeries(item.values, engine.context({slug:item.slug,segment:true}))
    ok(Boolean(series), `série equivalente ${item.slug}`)
    ok(series.count === item.count, `contagem ${item.slug}`)
    near(series.distance_m, item.distance, 0.001, `distância da série ${item.slug}`)
    ok(series.unit === item.unit, `unidade da série ${item.slug}`)
}
ok(engine.chooseDistanceUnit(5000, engine.context({slug:'corrida',registered_m:5000})) === 'km', '5000 m comum usa km')
ok(engine.chooseDistanceUnit(5000, engine.context({slug:'atletismo-5000m',registered_m:5000})) === 'm', '5000 m pista usa m')
ok(engine.chooseDistanceUnit(1500, engine.context({slug:'atletismo-1500m'}), 'km') === 'km', 'manual vence disciplina')
ok(engine.convertDistance(400,'m','km') === 0.4, '400 m vira 0.4 km')
ok(engine.convertDistance(0.4,'km','m') === 400, '0.4 km volta para 400 m')
ok(engine.context({slug:'atletismo-100m'}).prefers_milliseconds === true, '100 m sugere ms')
ok(engine.context({slug:'corrida',registered_m:5000}).prefers_milliseconds === false, '5 km comum não sugere ms')
ok(engine.equivalentSeries([400,411,389], engine.context({slug:'atletismo-400m',segment:true})) === null, 'fora da tolerância não vira série')
console.log(`✓ activity sport context JS: ${assertions} assertions`)
