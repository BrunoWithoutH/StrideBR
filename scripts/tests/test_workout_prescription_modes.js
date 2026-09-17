const path = require('path')
require(path.join(process.cwd(), 'public/assets/js/workout-prescription.js'))

const p = globalThis.StrideBRWorkoutPrescription
let assertions = 0
const assert = (condition, message) => {
  assertions += 1
  if (!condition) {
    console.error(`Workout prescription modes failed: ${message}`)
    process.exit(1)
  }
}

let value = p.resolve({repeticoes: 12, carga: 40})
assert(value.mode === 'LOAD_REPS', 'load + reps deve resolver LOAD_REPS')
assert(value.values.load === '40 kg', 'carga numérica deve formatar kg')
assert(value.values.reps === '12 reps', 'reps devem formatar reps')

value = p.resolve({repeticoes: 15})
assert(value.mode === 'REPS', 'reps isoladas devem resolver REPS')
assert(value.fields.join(',') === 'reps', 'REPS não deve inventar outros campos')

value = p.resolve({duration_s: 1200})
assert(value.mode === 'DURATION', 'duration isolada deve resolver DURATION')
assert(value.values.duration === '20 min', '1200 s deve apresentar 20 min')
assert(!value.summaryParts.some(item => /rep/i.test(item)), 'duration não pode aparecer como reps')

value = p.resolve({repeticoes: '20 min'})
assert(value.mode === 'DURATION', 'legado com duração em repeticoes deve migrar para DURATION na apresentação')
assert(value.values.duration === '20 min', 'legado 20 min deve permanecer 20 min')
assert(value.values.reps === '', 'legado de duração não pode manter reps falsas')

value = p.resolve({distance_m: 5000})
assert(value.mode === 'DISTANCE', 'distance isolada deve resolver DISTANCE')
assert(value.values.distance === '5 km', '5000 m deve apresentar 5 km')

value = p.resolve({carga: 10, duracao: '1 min 30 s'})
assert(value.mode === 'LOAD_DURATION', 'load + duration deve resolver LOAD_DURATION')
assert(value.values.load === '10 kg', 'LOAD_DURATION deve manter carga')
assert(value.values.duration === '1 min 30 s', 'duração composta deve ser humanizada')

value = p.resolve({duracao: '20 min', distancia: '5 km'})
assert(value.mode === 'DURATION_DISTANCE', 'duration + distance deve resolver DURATION_DISTANCE')
assert(value.fields.join(',') === 'duration,distance', 'DURATION_DISTANCE deve expor apenas os campos relevantes')

value = p.resolve({})
assert(value.mode === 'EMPTY', 'campos vazios devem resolver EMPTY')
assert(value.fields.length === 0 && value.summaryParts.length === 0, 'EMPTY não deve inventar 0 reps/carga/duração/distância')

console.log(`✓ Workout prescription modes: ${assertions} assertions`)
