const fs = require('fs')
const src = fs.readFileSync('public/assets/js/atividades.js','utf8')
function extract(startMarker, endMarker) {
  const start = src.indexOf(startMarker)
  if (start < 0) throw new Error(`missing ${startMarker}`)
  const end = src.indexOf(endMarker, start)
  if (end < 0) throw new Error(`missing end ${endMarker}`)
  return src.slice(start, end)
}
const code = [
  extract('    const mercatorWorld =', '    const tileUrl ='),
  extract('    const chooseMapZoom =', '    const shareRouteFillForScale ='),
  extract('    const shareRouteFillForScale =', '    const roadColorByKind ='),
].join('\n') + '\nreturn {createMapViewport, shareRouteFillForScale};'
const {createMapViewport, shareRouteFillForScale} = new Function(code)()
let assertions=0
const check=(v,m)=>{assertions++; if(!v) throw new Error(m)}
const shapes={
  oval:[[-53.4000,-27.3600],[-53.3985,-27.3592],[-53.3970,-27.3600],[-53.3985,-27.3608],[-53.4000,-27.3600]],
  wide:[[-53.42,-27.36],[-53.39,-27.361],[-53.36,-27.36]],
  tall:[[-53.39,-27.39],[-53.391,-27.36],[-53.39,-27.33]],
}
const frames={story:{w:1080,h:1920,frame:{x:104,y:330,width:872,height:930}},portrait:{w:1080,h:1350,frame:{x:78,y:220,width:924,height:650}},square:{w:1080,h:1080,frame:{x:78,y:190,width:924,height:520}},compact:{w:1080,h:1350,frame:{x:118,y:500,width:844,height:500}}}
function bounds(vp,coords){const ps=coords.map(([x,y])=>vp.project(x,y)); const xs=ps.map(p=>p[0]),ys=ps.map(p=>p[1]); return {x0:Math.min(...xs),x1:Math.max(...xs),y0:Math.min(...ys),y1:Math.max(...ys)}}
function occupancy(vp,coords,frame){const b=bounds(vp,coords); return Math.max((b.x1-b.x0)/frame.width,(b.y1-b.y0)/frame.height)}
const expected = new Map([[50,.50],[100,.68],[200,.94]])
for (const [pct,target] of expected) {
  check(Math.abs(shareRouteFillForScale(pct)-target)<1e-9,`${pct}% mapping incorreto`)
}
const steps=[50,75,100,125,150,175,200]
for(const [shape,coords] of Object.entries(shapes)) for(const [format,f] of Object.entries(frames)){
  let previous=0
  for(const pct of steps){
    const fill=shareRouteFillForScale(pct)
    const vp=createMapViewport(coords,f.w,f.h,f.frame,fill)
    const b=bounds(vp,coords)
    const occ=occupancy(vp,coords,f.frame)
    check(b.x0>=f.frame.x-0.01 && b.x1<=f.frame.x+f.frame.width+0.01,`${shape}/${format}/${pct}: x fora safe area`)
    check(b.y0>=f.frame.y-0.01 && b.y1<=f.frame.y+f.frame.height+0.01,`${shape}/${format}/${pct}: y fora safe area`)
    if(previous) check(occ>previous+0.001,`${shape}/${format}: faixa morta em ${pct}% (${previous} -> ${occ})`)
    previous=occ
  }
  check(previous>=.92,`${shape}/${format}: extremo direito não usa a safe area (${previous})`)
}
check(shareRouteFillForScale(250)===shareRouteFillForScale(200),'clamp superior deve ocorrer somente acima do max HTML')
check(shareRouteFillForScale(20)===shareRouteFillForScale(50),'clamp inferior deve ocorrer somente abaixo do min HTML')
console.log(`✓ share route scale geometry: ${assertions} assertions; continuous 50–200 mapping across Story/Portrait/Square/Compact`)
