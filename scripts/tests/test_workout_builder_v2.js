const fs = require('fs')
const path = require('path')
const vm = require('vm')

const root = path.resolve(__dirname, '..', '..')
const sandbox = {globalThis:{}, Intl, Number, String, Array, Object, Math, JSON, console}
sandbox.globalThis = sandbox
vm.createContext(sandbox)
vm.runInContext(fs.readFileSync(path.join(root, 'public/assets/js/workout-prescription.js'), 'utf8'), sandbox)
const prescription = sandbox.StrideBRWorkoutPrescription
const builderSource = fs.readFileSync(path.join(root, 'public/assets/js/workout-builder.js'), 'utf8')
let assertions = 0
const same = (expected, actual, message) => {
    assertions++
    if (expected !== actual) throw new Error(`${message}: expected ${JSON.stringify(expected)}, got ${JSON.stringify(actual)}`)
}
const ok = (condition, message) => {
    assertions++
    if (!condition) throw new Error(message)
}

same('4 × 8–12 reps · 30 kg · 90 s', prescription.structuredSummary('standard', {sets:4,reps:{mode:'range',min:8,max:12},load:{value:30,unit:'kg'},rest_after_s:90}), 'Standard range summary changed')
same('3 blocos · 4+4+3 · 80 kg · 20 s intra · 120 s descanso', prescription.structuredSummary('cluster', {blocks:3,clusters:[{reps:4},{reps:4},{reps:3}],load:{value:80,unit:'kg'},intra_cluster_rest_s:20,between_blocks_rest_s:120}), 'Cluster summary changed')
same('40×10 → 30×8 → 20×AMRAP', prescription.structuredSummary('drop_set', {rounds:1,stages:[{load:{value:40},reps:{mode:'fixed',value:10}},{load:{value:30},reps:{mode:'fixed',value:8}},{load:{value:20},reps:{mode:'amrap'}}]}), 'Drop summary changed')
same('Até a falha', prescription.repTargetText({mode:'failure'}), 'Failure target changed')
same('AMRAP', prescription.repTargetText({mode:'amrap'}), 'AMRAP target changed')
same('54,73 m', prescription.formatDistance('54.73 m'), 'Meter precision changed')
same(400, prescription.distanceMeters('0.4 km'), 'km to m parser changed')
same(1200, prescription.durationSeconds('20mn'), 'Legacy duration parser changed')
ok(builderSource.includes("event.altKey && event.key === 'ArrowUp'"), 'Keyboard reorder up missing')
ok(builderSource.includes("event.altKey && event.key === 'ArrowDown'"), 'Keyboard reorder down missing')
ok(builderSource.includes("[data-wb-move]"), 'Pointer reorder alternative missing')
ok(builderSource.includes("moveCard(card, button.dataset.wbMove)"), 'Pointer reorder action missing')
ok(builderSource.includes("[data-wb-drag]"), 'Drag handle contract missing')
ok(builderSource.includes("data-wb-live"), 'aria-live announcement contract missing')
ok(builderSource.includes("StrideBRUI?.undo"), 'Undo contract missing')
ok(builderSource.includes('groupCards.forEach(member => members?.appendChild(member))'), 'Grouped undo reconstruction missing')
ok(builderSource.includes("/api/exercicio-resolver.php"), 'Exercise Library resolver integration missing')
ok(builderSource.includes("data-wb-add-cluster-piece"), 'Cluster editor behavior missing')
ok(builderSource.includes("data-wb-add-drop-stage"), 'Drop-stage editor behavior missing')
ok(builderSource.includes("data-wb-distance-unit"), 'Distance unit conversion behavior missing')
ok(builderSource.includes("select.value === 'm' ? value * 1000 : value / 1000"), 'Distance magnitude conversion contract missing')
ok(builderSource.includes("dirtyLabels.forEach(label => { label.hidden = !value })"), 'Dirty-state labels must update without an undefined singular variable')
ok(builderSource.includes("button.closest('.wb-card-menu')?.removeAttribute('open')"), 'Pointer reorder menu should close after moving an exercise')

console.log(`Workout Builder V2 JS: ${assertions} assertions`)
