#!/usr/bin/env python3
from pathlib import Path
import sys
try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr); sys.exit(2)
ROOT=Path(__file__).resolve().parents[2]
js=(ROOT/'public/assets/js/atividades.js').read_text()
def extract(a,b):
    start=js.index(a); end=js.index(b,start); return js[start:end]
merc=extract('    const mercatorWorld =','    const tileUrl =')
viewport=extract('    const chooseMapZoom =','    const roadColorByKind =')
label=extract('    const drawShareLabelFreeStreetBackground =','    const drawShareMapTiles =')
roads=extract('    const roadColorByKind =','    const drawPhotoPlaceholder =')
label=label.replace('await fetchRoadNetwork(routeCoordinates)','await Promise.resolve(window.__mockGeometry)')
source='''let shareRenderToken=1;\n'''+merc+viewport+roads+label+r'''
window.__labelFreeTest=async(canvas)=>{
 const route=[[-53.405,-27.365],[-53.400,-27.360],[-53.394,-27.364]];
 const vp=createMapViewport(route,canvas.width,canvas.height,{x:80,y:120,width:920,height:1500},.72);
 const ctx=canvas.getContext('2d'); let textCalls=0; const original=ctx.fillText.bind(ctx); ctx.fillText=(...args)=>{textCalls++;return original(...args)};
 const result=await drawShareLabelFreeStreetBackground(ctx,{width:canvas.width,height:canvas.height,viewport:vp,routeCoordinates:route,token:1});
 const data=ctx.getImageData(0,0,canvas.width,canvas.height).data; let changed=0; for(let i=0;i<data.length;i+=1600){ if(data[i]!==18||data[i+1]!==27||data[i+2]!==40) changed++; }
 return {result,textCalls,changed};
};
'''
HTML='<canvas id="c" width="1080" height="1920"></canvas>'
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    page=browser.new_page(); errors=[]; page.on('pageerror',lambda e:errors.append(str(e)))
    page.set_content(HTML)
    page.evaluate('''()=>{window.__mockGeometry={
      roads:[
       {kind:'primary',coordinates:[[-53.408,-27.363],[-53.390,-27.363]]},
       {kind:'residential',coordinates:[[-53.402,-27.370],[-53.402,-27.353]]},
       {kind:'residential',coordinates:[[-53.398,-27.370],[-53.398,-27.353]]},
       {kind:'secondary',coordinates:[[-53.408,-27.358],[-53.390,-27.358]]}
      ],
      areas:[
       {kind:'leisure:park',coordinates:[[-53.404,-27.362],[-53.401,-27.362],[-53.401,-27.359],[-53.404,-27.359]]},
       {kind:'natural:water',coordinates:[[-53.397,-27.366],[-53.394,-27.366],[-53.394,-27.364],[-53.397,-27.364]]}
      ]
    }}''')
    page.add_script_tag(content=source)
    result=page.evaluate('()=>__labelFreeTest(document.querySelector("#c"))')
    assert result['result']['drawn'] is True, 'urban geometry was not drawn'
    assert 'OpenStreetMap' in result['result']['attribution'], 'OSM attribution missing'
    assert result['textCalls']==0, f'basemap renderer drew text labels: {result["textCalls"]}'
    assert result['changed']>10, 'roads/areas did not visibly change canvas'
    assert not errors, errors
    browser.close()
print('✓ share label-free map browser: 5 assertions; urban roads/green/water, zero cartographic text labels')
