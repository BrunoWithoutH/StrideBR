const fs = require('fs')
const path = require('path')
const root = path.resolve(__dirname, '../..')
const source = fs.readFileSync(path.join(root, 'public/assets/js/trainer.js'), 'utf8')
const checks = [
  [source.includes("event.key === 'Escape'"), 'Escape closes prescription dialog'],
  [source.includes('prescriptionTrigger?.focus()'), 'close restores trigger focus'],
  [source.includes("event.key !== 'Tab'"), 'Tab focus containment exists'],
  [source.includes('dataset.coachCreateDate'), 'calendar create prefills selected date'],
  [source.includes('data-start-scheduled-workout'), 'existing scheduled workout start remains wired'],
  [!source.includes('history.pushState'), 'workspace relies on real links for back/forward'],
  [!source.includes('fetch("/api/treinador'), 'server-rendered workspace does not depend on redundant coach AJAX']
]
for (const [ok, message] of checks) if (!ok) throw new Error(message)
console.log(`Coach Workspace V1 JS passed (${checks.length} contracts).`)
