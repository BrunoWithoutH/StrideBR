const fs = require('fs')
const path = require('path')
const vm = require('vm')
const root = path.resolve(__dirname, '..', '..')
const source = fs.readFileSync(path.join(root, 'public/assets/js/cronogramas.js'), 'utf8')
let assertions = 0
const ok = (value, message) => { assertions++; if (!value) throw new Error(message) }

global.window = {}
global.document = {addEventListener() {}}
vm.runInThisContext(source, {filename: 'cronogramas.js'})
const factory = window.StrideBRWorkoutPreviewSlot?.create
ok(typeof factory === 'function', 'Preview slot factory is not exposed for behavioral regression tests')

const host = {
    children: [],
    replaceChildren(...nodes) { this.children = nodes },
}
const slot = factory(host)
const node = (id, state = 'preview') => ({id, state})

const a = slot.begin({workoutId: 'A', occurrenceOriginal: '2026-09-01'}, node('A', 'loading'))
ok(host.children.length === 1 && host.children[0].id === 'A', 'Loading did not occupy exactly one preview slot')
ok(slot.replace(a, node('A')), 'Current A request could not commit')
ok(host.children.length === 1 && host.children[0].id === 'A', 'A success did not remain single-slot')

const b = slot.begin({workoutId: 'B', occurrenceOriginal: '2026-09-02'}, node('B', 'loading'))
ok(a.controller.signal.aborted, 'Opening B did not abort A')
ok(!slot.replace(a, node('A', 'late')), 'Late A response replaced B')
ok(slot.replace(b, node('B')), 'Current B request could not commit')
ok(host.children.length === 1 && host.children[0].id === 'B', 'A → B produced stacked preview content')

const sameWorkoutNewOccurrence = slot.begin({workoutId: 'B', occurrenceOriginal: '2026-09-09'}, node('B2', 'loading'))
ok(Object.isFrozen(sameWorkoutNewOccurrence.context), 'Request context is mutable')
ok(sameWorkoutNewOccurrence.context.occurrenceOriginal === '2026-09-09', 'Same workout did not capture the new occurrence context')
ok(!slot.replace(b, node('B', 'old occurrence')), 'Old occurrence for same workout replaced the current occurrence')

const c = slot.begin({workoutId: 'C'}, node('C', 'loading'))
const d = slot.begin({workoutId: 'D'}, node('D', 'loading'))
const e = slot.begin({workoutId: 'E'}, node('E', 'loading'))
ok(!slot.replace(d, node('D', 'late')), 'Three-way race allowed middle response to commit')
ok(slot.replace(e, node('E')), 'Three-way race rejected current response')
ok(!slot.replace(c, node('C', 'latest arrival')), 'Three-way race allowed oldest response to commit last')
ok(host.children.length === 1 && host.children[0].id === 'E', 'A → B → C race did not end with the newest request')

const closing = slot.begin({workoutId: 'CLOSE'}, node('CLOSE', 'loading'))
slot.close()
ok(closing.controller.signal.aborted, 'Close did not abort active preview request')
ok(host.children.length === 0, 'Close did not clear preview host')
ok(!slot.replace(closing, node('CLOSE', 'late')), 'Closed request committed after modal close')

const retryA = slot.begin({workoutId: 'A', retry: true}, node('A-retry', 'loading'))
const afterRetry = slot.begin({workoutId: 'B'}, node('B', 'loading'))
ok(retryA.controller.signal.aborted, 'Opening B did not abort retry A')
ok(!slot.replace(retryA, node('A-retry', 'late')), 'Stale retry replaced newer preview')
ok(slot.replace(afterRetry, node('B')), 'Newer preview after retry could not commit')
ok(host.children.length === 1 && host.children[0].id === 'B', 'Retry race did not finish on B')

console.log(`Workout Preview Stability V1 JS: ${assertions} assertions`)
