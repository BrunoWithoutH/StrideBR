'use strict'
const assert=require('node:assert/strict')
const fs=require('node:fs')
const vm=require('node:vm')
const source=fs.readFileSync('public/assets/js/atividades.js','utf8')
const helper=source.slice(source.indexOf('    const renderDetailOpenAction ='),source.indexOf('    const renderDetail ='))
const render=vm.runInNewContext(helper+'\nrenderDetailOpenAction')
let button=null
const template={content:{cloneNode:()=>({text:'Abrir detalhes',remove(){button=null}})},after(node){button=node}}
const container={querySelector(selector){return selector==='[data-detail-open-full]'?button:template}}
render(container,false)
assert.equal(button.text,'Abrir detalhes','preview inserts open details action')
render(container,true)
assert.equal(button,null,'full detail removes action markup')
render(container,false)
assert.equal(button.text,'Abrir detalhes','returning to preview restores action')
render(container,false)
assert.ok(button,'repeated preview render retains one action')
console.log('✓ Activity detail action capability: preview/full/preview')

const routesSource=fs.readFileSync('public/assets/js/routes.js','utf8')
const thumbnailSource=routesSource.slice(routesSource.indexOf('    const thumbnail ='),routesSource.indexOf('    const mount ='))
const thumbnail=vm.runInNewContext(thumbnailSource+'\nthumbnail')
assert.equal(thumbnail([]),'','missing route geometry has no fabricated preview')
const preview=thumbnail([[-51.23,-30.03],[-51.22,-30.02],[-51.21,-30.03]])
assert.ok(preview.includes('<polyline')&&!preview.includes('NaN')&&!preview.includes('Infinity'),'real geometry produces a finite lightweight preview')
for(const pair of preview.match(/points="([^"]+)"/)[1].split(' ')){
 const [x,y]=pair.split(',').map(Number)
 assert.ok(x>=12&&x<=148&&y>=12&&y<=98,'thumbnail stays contained')
}
console.log('✓ Route lightweight thumbnail: actual geometry and containment')
