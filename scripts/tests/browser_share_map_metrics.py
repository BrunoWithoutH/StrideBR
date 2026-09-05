#!/usr/bin/env python3
from pathlib import Path
import sys
try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr)
    sys.exit(2)
ROOT=Path(__file__).resolve().parents[2]
js=(ROOT/'public/assets/js/atividades.js').read_text()
css='\n'.join((ROOT/p).read_text() for p in ['public/assets/css/style.css','public/assets/css/atividades.css','public/assets/css/ui-refresh.css','public/assets/css/activity-sharing.css'])

def extract(a,b):
    start=js.index(a); end=js.index(b,start); return js[start:end]
metricorder=extract('    const normalizeShareMetricLabel =','    const isSharePrivateMetric =')
metric=extract('    const shareMetricRowCount =','    const drawRouteLessShareCard =')
merc=extract('    const mercatorWorld =','    const tileUrl =')
mapblock=extract('    const shareMapProvider =','    const roadColorByKind =')
source=r'''
const tileCache=new Map(); const shareMapPreviewCache=new Map(); let shareRenderToken=1; const tr=(key)=>key; const isShareMapBackgroundApplicable=(singleEditor,contentId,hasRoute)=>Boolean(singleEditor&&contentId==='route'&&hasRoute);
window.StrideBRBasemaps={share:{
 available:(style)=>['street','satellite'].includes(style),
 definition:(style)=>style==='street'?{id:style,available:true,worldTileSize:512,maxZoom:23,attribution:'Mock street'}:{id:style,available:true,worldTileSize:256,maxZoom:23,attribution:'Mock satellite'},
 tile:(style,z,x,y)=>{if(!['street','satellite'].includes(style))return null;const size=style==='street'?512:256;const colors={street:'#dfe7ee',satellite:'#34533b'};const svg=`<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}"><rect width="${size}" height="${size}" fill="${colors[style]}"/><path d="M0 ${size/2}H${size}M${size/2} 0V${size}" stroke="rgba(255,255,255,.45)" stroke-width="4"/></svg>`;return {url:'data:image/svg+xml;charset=utf-8,'+encodeURIComponent(svg),worldTileSize:size,attribution:'Mock '+style,id:style}}
}};
'''+metricorder+merc+mapblock+metric+r'''
const shapes={simple:[[-53.4,-27.36],[-53.395,-27.357],[-53.39,-27.36]],circuit:[[-53.4,-27.36],[-53.398,-27.358],[-53.396,-27.36],[-53.398,-27.362],[-53.4,-27.36]]};
function bounds(vp,coords){const pts=coords.map(([lon,lat])=>vp.project(lon,lat));const xs=pts.map(p=>p[0]),ys=pts.map(p=>p[1]);return {x0:Math.min(...xs),x1:Math.max(...xs),y0:Math.min(...ys),y1:Math.max(...ys)}}
window.__shareTest={
 metric:shareMetricLayout,
 rows:shareMetricRowCount,
 order:(metrics)=>canonicalizeShareMetrics(metrics),
 fill:shareRouteFillForScale,
 applicable:isShareMapBackgroundApplicable,
 async renderMap(canvas,style,shape='circuit',pct=100,rasterScale=.55){const coords=shapes[shape];const frame={x:40,y:120,width:canvas.width-80,height:canvas.height-240};const vp=createShareMapViewport(coords,canvas.width,canvas.height,frame,pct,style,rasterScale);const ctx=canvas.getContext('2d',{alpha:false});const result=await drawShareMapTiles(ctx,{width:canvas.width,height:canvas.height,viewport:vp,style,token:1});const b=bounds(vp,coords);return {drawn:result.drawn,attribution:result.attribution,routeBounds:b,frame,zoom:vp.zoom,outputScale:vp.outputScale,physicalScale:vp.outputScale*rasterScale,data:canvas.toDataURL('image/png')};},
 occupancy(shape,pct){const coords=shapes[shape];const frame={x:40,y:120,width:560,height:400};const vp=createMapViewport(coords,640,640,frame,shareRouteFillForScale(pct));const b=bounds(vp,coords);return Math.max((b.x1-b.x0)/frame.width,(b.y1-b.y0)/frame.height)}
};
'''
HTML='''<!doctype html><html data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>
<div class="activity-share-quick-controls">
<fieldset class="activity-share-color-options" data-share-color-options><legend>Cor</legend><label><input type="radio" checked><span><b>Escuro</b></span></label></fieldset>
<fieldset class="activity-share-map-style-options" data-share-map-style-options hidden><legend>Tipo de mapa</legend><label><input type="radio" name="m" value="street" checked><span>Ruas</span></label><label><input type="radio" name="m" value="satellite"><span>Satélite</span></label></fieldset>
</div>
<label class="activity-share-route-scale"><input id="scale" type="range" min="50" max="200" step="5" value="100"><output>100%</output></label>
<canvas id="map" width="1080" height="1920"></canvas>
</body></html>'''
assertions=0; failures=[]
def check(v,m):
    global assertions; assertions+=1
    if not v: raise AssertionError(m)
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for theme in ['light','dark']:
        page=browser.new_page(viewport={'width':900,'height':760})
        errors=[]; page.on('pageerror',lambda e:errors.append(str(e)))
        try:
            page.set_content(HTML,wait_until='domcontentloaded'); page.add_style_tag(content=css); page.add_script_tag(content=source)
            page.evaluate("t=>document.documentElement.dataset.theme=t",theme)
            check(page.locator('#scale').get_attribute('min')=='50' and page.locator('#scale').get_attribute('max')=='200',f'{theme}: range incorreto')
            occ=[page.evaluate('(p)=>__shareTest.occupancy("circuit",p)',pct) for pct in [50,75,100,125,150,175,200]]
            check(all(occ[i+1]>occ[i]+.001 for i in range(len(occ)-1)),f'{theme}: slider tem faixa morta {occ}')
            check(occ[-1]>.92 and occ[0]<.53,f'{theme}: extremos do slider incorretos {occ[0]}->{occ[-1]}')
            check(page.evaluate('()=>__shareTest.applicable(true,"route",true)') is True,f'{theme}: mapa não aplicável à rota')
            check(page.evaluate('()=>__shareTest.applicable(true,"sport",true)') is False and page.evaluate('()=>__shareTest.applicable(true,"none",true)') is False,f'{theme}: mapa vazou para conteúdo sem rota')
            color=page.locator('[data-share-color-options]'); maps=page.locator('[data-share-map-style-options]')
            check(color.is_visible() and not maps.is_visible(),f'{theme}: controles de Cor iniciais incorretos')
            page.evaluate("()=>{document.querySelector('[data-share-color-options]').hidden=true;document.querySelector('[data-share-map-style-options]').hidden=false}")
            check(not color.is_visible() and maps.is_visible(),f'{theme}: controles de Mapa não ficaram contextuais')
            check(page.locator('[data-share-map-style-options] input').count()==2 and page.locator('[value="terrain"]').count()==0,f'{theme}: Relevo ainda aparece no share')
            outputs={}
            for style in ['street','satellite']:
                preview=page.evaluate('(s)=>__shareTest.renderMap(document.querySelector("#map"),s,"circuit",150,.55)',style)
                export=page.evaluate('(s)=>__shareTest.renderMap(document.querySelector("#map"),s,"circuit",150,1)',style)
                outputs[style]=export['data']
                check(preview['drawn'] and export['drawn'],f'{theme}/{style}: tiles não renderizaram')
                check(export['attribution']==f'Mock {style}',f'{theme}/{style}: attribution perdida')
                check(export['zoom']>=preview['zoom'],f'{theme}/{style}: export não usa zoom igual/maior {preview["zoom"]}->{export["zoom"]}')
                check(export['physicalScale']<=1.081,f'{theme}/{style}: export ainda amplia tile nativo ({export["physicalScale"]})')
                pb,eb=preview['routeBounds'],export['routeBounds']
                check(max(abs(pb[k]-eb[k]) for k in pb)<.01,f'{theme}/{style}: preview/export mudaram enquadramento {pb} vs {eb}')
                f=export['frame']; b=eb
                check(b['x0']>=f['x']-1 and b['x1']<=f['x']+f['width']+1 and b['y0']>=f['y']-1 and b['y1']<=f['y']+f['height']+1,f'{theme}/{style}: rota saiu da safe area')
            check(len(set(outputs.values()))==2,f'{theme}: estilos de mapa não alteraram o canvas')
            ordered=page.evaluate('()=>__shareTest.order([{key:"calories",rotulo:"Calorias"},{key:"pace",rotulo:"Ritmo"},{key:"duration",rotulo:"Duração"},{key:"distance",rotulo:"Distância"}]).map(m=>m.key)')
            check(ordered==['distance','duration','pace','calories'],f'{theme}: ordem canônica das métricas incorreta {ordered}')
            layouts={n:page.evaluate('(n)=>__shareTest.metric(n,100,800,200,120)',n) for n in [1,2,3,4]}
            check(len(layouts[4])==4 and layouts[4][0]['y']==layouts[4][1]['y'] and layouts[4][2]['y']==layouts[4][3]['y'] and layouts[4][2]['y']>layouts[4][0]['y'],f'{theme}: 4 métricas não formam 2x2')
            check(len(layouts[3])==3 and layouts[3][0]['x']<500<layouts[3][1]['x'] and layouts[3][0]['y']==layouts[3][1]['y'] and layouts[3][2]['x']==500 and layouts[3][2]['y']>layouts[3][0]['y'],f'{theme}: 3 métricas não formam triângulo para baixo')
            check(len(layouts[2])==2 and layouts[2][0]['y']==layouts[2][1]['y'] and layouts[2][0]['x']<500<layouts[2][1]['x'],f'{theme}: 2 métricas não estão equilibradas')
            check(len(layouts[1])==1 and layouts[1][0]['x']==500,f'{theme}: 1 métrica não centralizada')
            check(not errors,f'{theme}: erros JS {errors}')
        except Exception as exc: failures.append(str(exc))
        finally: page.close()
    browser.close()
if failures:
    print('Falhas no regression share map/metrics:',file=sys.stderr)
    for f in failures: print('- '+f,file=sys.stderr)
    sys.exit(1)
print(f'✓ share map/metrics browser: {assertions} assertions; streets/satellite, preview/export density, route-only map, 1–4 metrics, light/dark')
