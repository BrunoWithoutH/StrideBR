#!/usr/bin/env node
'use strict'

const assert=require('node:assert/strict')
const fs=require('node:fs')
const path=require('node:path')
const vm=require('node:vm')

const root=path.resolve(__dirname,'../..')
const source=fs.readFileSync(path.join(root,'public/assets/js/pacer-web.js'),'utf8')
const sandbox={__stridebrPacerChartTest:{},document:{querySelector:()=>null}}
sandbox.globalThis=sandbox
vm.runInNewContext(source,sandbox,{filename:'pacer-web.js'})
const geometry=sandbox.__stridebrPacerChartTest.geometry
const inspector=sandbox.__stridebrPacerChartTest.inspector
assert.equal(typeof geometry,'function','Pacer chart geometry test hook must be available')
assert.equal(typeof inspector,'function','Pacer chart inspector test hook must be available')

const fixtures=[
    {distance:1500,paces:[310,310],rules:{final_phase:{percent:10,min_distance_m:150,max_distance_m:1000}}},
    {distance:5000,paces:[300,290,280],rules:{final_phase:{percent:10,min_distance_m:150,max_distance_m:1000}}},
    {distance:10000,paces:[330,320,310,300,290],rules:{final_phase:{percent:10,min_distance_m:150,max_distance_m:1000}}},
    {distance:21097.5,paces:[320,312,304,296],rules:{final_phase:{percent:10,min_distance_m:150,max_distance_m:1000}}},
    {distance:42195,paces:[305,305,305],rules:{final_phase:{percent:10,min_distance_m:150,max_distance_m:1000}}},
]

for(const fixture of fixtures){
    const segments=fixture.paces.map((pace,index)=>({
        start_distance_m:fixture.distance*index/fixture.paces.length,
        end_distance_m:fixture.distance*(index+1)/fixture.paces.length,
        target_pace_s_per_km:pace,
    }))
    const chart=geometry(segments,fixture.distance,fixture.rules)
    assert.ok(chart,`geometry must render ${fixture.distance} m`)
    assert.equal(chart.chart.width,800)
    assert.equal(chart.chart.height,220)
    assert.ok(Number.isFinite(chart.finalBand.x)&&Number.isFinite(chart.finalBand.width),'final phase coordinates must be finite')
    assert.equal(chart.finalBand.y,chart.plot.y)
    assert.equal(chart.finalBand.height,chart.plot.height)
    assert.ok(chart.finalBand.x>=chart.plot.x&&chart.finalBand.x<=chart.plot.right,'final phase must start inside the plot')
    assert.ok(chart.finalBand.x+chart.finalBand.width<=chart.plot.right+.000001,'final phase must end inside the plot')
    assert.ok(!chart.points.includes('NaN')&&!chart.points.includes('Infinity'),'path data must remain finite')
    assert.ok(chart.maxPace>chart.minPace,'chart must keep a non-zero useful Y domain')
    for(const pair of chart.points.split(' ')){
        const [x,y]=pair.split(',').map(Number)
        assert.ok(x>=chart.plot.x&&x<=chart.plot.right,`x coordinate must remain local for ${fixture.distance} m`)
        assert.ok(y>=chart.plot.y&&y<=chart.plot.bottom,`y coordinate must remain local for ${fixture.distance} m`)
    }
    if(fixture.paces[0]!==fixture.paces.at(-1)){
        const firstY=Number(chart.points.split(' ')[0].split(',')[1])
        const lastY=Number(chart.points.split(' ').at(-1).split(',')[1])
        assert.ok(fixture.paces[0]>fixture.paces.at(-1)?firstY>lastY:firstY<lastY,'faster pace must render higher in the local plot')
    }
}

const interactive=geometry([{start_distance_m:0,end_distance_m:5000,target_pace_s_per_km:300,tolerance_s_per_km:10}],5000,{final_phase:{percent:10,min_distance_m:150,max_distance_m:1000}})
const point=inspector(interactive,2500)
assert.equal(point.segment.pace,300,'pointer lookup must use the plan segment')
assert.equal(point.targetElapsed,750,'pointer lookup must calculate target elapsed locally')
assert.equal(inspector(interactive,5000).finalPhase,true,'final phase must be exposed to the inspector')

assert.equal(geometry([],10000,{}),null,'missing segments must not create an invalid SVG')
console.log('✓ Pacer SVG containment: 5 fixtures')

const pointerRatio=sandbox.__stridebrPacerChartTest.pointerRatio
assert.equal(typeof pointerRatio,'function')
// The screen transform includes CSS scale, viewBox letterboxing and translation.
for(const [scale,offset] of [[1,80],[1.5,200],[.65,30]]){
    const svg={getScreenCTM:()=>({inverse:()=>({scale,offset})}),createSVGPoint:()=>({x:0,y:0,matrixTransform(matrix){return {x:(this.x-matrix.offset)/matrix.scale}}})}
    for(const ratio of [0,.5,1]){
        const screenX=offset+(interactive.plot.x+ratio*interactive.plot.width)*scale
        assert.ok(Math.abs(pointerRatio(svg,screenX,120,interactive.plot)-ratio)<1e-10,'screen pointer must map to the same logical plot position')
    }
    assert.equal(pointerRatio(svg,offset,120,interactive.plot),0,'left padding clamps to plot edge')
    assert.equal(pointerRatio(svg,offset+900*scale,120,interactive.plot),1,'right padding clamps to plot edge')
}
assert.equal(pointerRatio({getScreenCTM:()=>null},0,0,interactive.plot),null,'detached SVG must not move the cursor')
console.log('✓ Pacer pointer transform: left/center/right at three CSS scales')
