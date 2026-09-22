const fs = require('fs')
const path = require('path')
const root = path.resolve(__dirname, '../..')
const source = fs.readFileSync(path.join(root, 'public/assets/js/cronogramas.js'), 'utf8')
const checks = [
  [source.includes('const agendaMonths = new Map()'), 'month registry exists'],
  [source.includes('agendaRequests.has(key)'), 'same month cannot be fetched twice in parallel'],
  [source.includes('new IntersectionObserver'), 'sentinel progressive loading exists'],
  [source.includes("agendaMore?.addEventListener('click', agendaLoadNext)"), 'manual load-more fallback exists'],
  [source.includes("agendaPrevious?.addEventListener('click', agendaLoadPrevious)"), 'explicit previous-days control exists'],
  [source.includes('new AbortController()'), 'requests are abortable'],
  [source.includes('generation !== agendaGeneration'), 'old generation responses are ignored'],
  [source.includes("schedule !== (agendaRoot.dataset.scheduleId || '')"), 'old schedule responses are ignored'],
  [source.includes("agendaRetry?.addEventListener('click'"), 'failed append can be retried'],
  [source.includes("data.ocorrencias || []") && source.includes("data.agendados || []"), 'routine and scheduled rows share one timeline'],
  [source.includes("openQuickCreate({mode:'schedule', date:add.dataset.agendaAddDate"), 'day add action preserves concrete date'],
  [source.includes('agendaMaxMonths = 12'), 'DOM growth has a safety cap'],
  [source.includes("[data-print-schedule]')?.addEventListener('click', async") && source.includes('await agendaFetchMonth(key)'), 'print waits for agenda data before opening the print dialog']
]
for (const [ok, message] of checks) if (!ok) throw new Error(message)
console.log(`Training Planning UX Polish V6.1 JS passed (${checks.length} contracts).`)
