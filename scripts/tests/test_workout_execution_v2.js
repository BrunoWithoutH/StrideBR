const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict')
const context={};vm.runInNewContext(fs.readFileSync('public/assets/js/workout-prescription.js','utf8'),context)
const p=context.StrideBRWorkoutPrescription
for(const [source,fields] of [[{repetitions:12},['load','reps']],[{load:40,repetitions:12},['load','reps']],[{duration:'20 min'},['duration']],[{distance:'500 m'},['distance']],[{duration:'20 min',distance:'500 m'},['duration','distance']]]) assert.deepEqual([...p.loggingFields(p.resolve(source))],fields)
assert.equal(p.durationSeconds('20 min'),1200);assert.equal(p.durationSeconds('01:30'),90);assert.equal(p.distanceMeters('1,5 km'),1500);assert.equal(p.distanceMeters('500 m'),500)
console.log('Workout Execution V2 JS passed: reps/load and editable duration/distance contracts.')
