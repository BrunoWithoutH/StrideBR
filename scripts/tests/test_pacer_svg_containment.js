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
assert.equal(typeof geometry,'function','Pacer chart geometry test hook must be available')

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

assert.equal(geometry([],10000,{}),null,'missing segments must not create an invalid SVG')
console.log('✓ Pacer SVG containment: 5 fixtures')
