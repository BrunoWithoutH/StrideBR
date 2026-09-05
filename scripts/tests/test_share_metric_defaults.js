const fs = require('fs')
const vm = require('vm')
const path = require('path')

const root = path.resolve(__dirname, '../..')
const source = fs.readFileSync(path.join(root, 'public/assets/js/atividades.js'), 'utf8')
const start = source.indexOf('    const normalizeShareMetricLabel =')
const end = source.indexOf('    const shareFocusLabel =', start)
if (start < 0 || end < 0) throw new Error('share metric helpers not found')
const helpers = source.slice(start, end)

const sandbox = {
  console,
  i18n: {number: (value) => String(value), locale: 'pt-BR'},
  tr: (key, _values = {}, fallback = null) => ({
    'activity.code':'Código', 'activity.focus':'Foco', 'activity.share.calories':'Calorias',
    'activity.strength.volume':'Volume', 'activity.strength.exercises':'Exercícios',
    'activity.strength.sets':'Séries', 'activity.share.segments':'Trechos', 'activity.share.elevation':'Elevação',
    'activity.share.segment':'Trecho', 'nav.physical_activity':'Atividade física'
  }[key] || fallback || key),
  result: null,
}
vm.createContext(sandbox)
vm.runInContext(`${helpers}\nthis.__metricApi={shareMetricType,shareMetricHasValue,shareSportFamily,activityDetailToShareData,availableShareMetrics};`, sandbox)
const api = sandbox.__metricApi
let assertions = 0
const check = (condition, message) => { assertions += 1; if (!condition) throw new Error(message) }
const keys = metrics => metrics.filter(item => item.defaultSelected !== false).map(item => api.shareMetricType(item))

const runningBase = {
  modalidade:'Corrida', modalidade_slug:'corrida',
  metricas:[
    {key:'distance',rotulo:'Distância',valor:'5,00 km'},
    {key:'duration',rotulo:'Duração',valor:'25:00'},
    {key:'pace',rotulo:'Ritmo',valor:'5:00/km'},
  ]
}
let metrics = api.availableShareMetrics({...runningBase, ganho_m:82})
check(JSON.stringify(keys(metrics)) === JSON.stringify(['distance','duration','pace','elevation']), 'corrida com elevação deve priorizar distância/duração/ritmo/elevação')

metrics = api.availableShareMetrics({...runningBase, ganho_m:null, metricas:[...runningBase.metricas,{key:'calories',rotulo:'Calorias',valor:'410 kcal'}]})
check(JSON.stringify(keys(metrics)) === JSON.stringify(['distance','duration','pace','calories']), 'sem elevação deve usar calorias como quarta métrica quando disponível')

metrics = api.availableShareMetrics({...runningBase, ganho_m:null})
check(keys(metrics).length === 3, 'sem quarta métrica válida deve manter apenas três')
check(metrics.every(item => !['--','—','N/A',''].includes(String(item.valor).trim())), 'lista manual não deve conter placeholders vazios')

metrics = api.availableShareMetrics({
  modalidade:'Ciclismo', modalidade_slug:'ciclismo',
  metricas:[
    {key:'distance',rotulo:'Distância',valor:'42 km'},
    {key:'duration',rotulo:'Duração',valor:'1:30:00'},
    {key:'pace',rotulo:'Ritmo',valor:'2:08/km'},
    {key:'speed',rotulo:'Velocidade média',valor:'28 km/h'},
    {key:'power',rotulo:'Potência média',valor:'215 W'},
  ]
})
check(keys(metrics)[2] === 'speed', 'ciclismo deve priorizar velocidade antes de ritmo')

const strengthData = api.activityDetailToShareData({
  id:'10', modalidade:'Musculação', modalidade_slug:'musculacao', titulo:'Treino A',
  metricas_compartilhamento:[{key:'duration',rotulo:'Duração',valor:'52:00'}],
  energia:{kcal:360}, forca:{volume_kg:4820,total_exercicios:6,total_series:18}, unidades:[]
})
metrics = api.availableShareMetrics(strengthData)
check(JSON.stringify(keys(metrics)) === JSON.stringify(['duration','calories','volume','exercises']), 'musculação deve usar métricas coerentes já existentes')
check(metrics.some(item => api.shareMetricType(item) === 'sets'), 'séries disponíveis devem continuar selecionáveis manualmente')

metrics = api.availableShareMetrics({
  ...runningBase,
  metricas:[...runningBase.metricas,{key:'effort',rotulo:'Esforço percebido',valor:'7/10'}]
})
check(metrics.some(item => api.shareMetricType(item) === 'effort'), 'esforço registrado deve aparecer como opção manual')
check(!keys(metrics).includes('effort'), 'esforço não deve ser fallback automático principal')

metrics = api.availableShareMetrics({
  ...runningBase,
  metricas:[...runningBase.metricas,{key:'heart_avg',rotulo:'FC média',valor:'151 bpm'},{key:'cadence',rotulo:'Cadência',valor:'174 spm'}]
})
check(keys(metrics)[3] === 'heart_avg', 'corrida sem elevação/calorias deve usar FC média antes de cadência')

check(api.shareMetricHasValue('--') === false && api.shareMetricHasValue('—') === false && api.shareMetricHasValue('N/A') === false, 'placeholders técnicos devem ser tratados como indisponíveis')
check(api.shareMetricHasValue('0') === true, 'valor numérico zero explícito continua sendo um valor disponível')

console.log(`✓ share metric defaults: ${assertions} assertions`)
