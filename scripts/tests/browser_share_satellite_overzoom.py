#!/usr/bin/env python3
from pathlib import Path
import sys
try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
js = (ROOT/'public/assets/js/atividades.js').read_text()

def extract(a,b):
    start=js.index(a); end=js.index(b,start); return js[start:end]

metric = extract('    const shareMetricRowCount =','    const drawRouteLessShareCard =')
merc = extract('    const mercatorWorld =','    const tileUrl =')
mapblock = extract('    const shareMapProvider =','    const roadColorByKind =')

source = r'''
const tileCache=new Map(); const shareMapPreviewCache=new Map(); let shareRenderToken=1; const tr=(key)=>key;
window.__requests=[];
window.StrideBRBasemaps={share:{
 available:(style)=>style==='satellite',
 definition:(style)=>style==='satellite'?{id:'satellite',available:true,worldTileSize:256,maxZoom:23,maxRequestZoom:23,minFallbackZoom:3,attribution:'Mock satellite'}:null,
 tile:(style,z,x,y)=>{
   if(style!=='satellite') return null;
   window.__requests.push({z,x,y});
   if(z>17) return null;
   const svg=`<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><rect width="256" height="256" fill="#34533b"/><text x="8" y="24" fill="white">z${z}</text></svg>`;
   return {url:'data:image/svg+xml;charset=utf-8,'+encodeURIComponent(svg),worldTileSize:256,attribution:'Mock satellite',id:'satellite'};
 }
}};
''' + metric + merc + mapblock + r'''
const shapes={
 short:[[-53.3900,-27.3600],[-53.3897,-27.3598],[-53.3895,-27.3600]],
 circuit:[[-53.3900,-27.3600],[-53.3896,-27.3597],[-53.3892,-27.3600],[-53.3896,-27.3603],[-53.3900,-27.3600]],
 long:[[-53.45,-27.31],[-53.42,-27.34],[-53.39,-27.36],[-53.35,-27.39]]
};
window.__satTest={
 async fallback(z,x=100,y=100){return await loadMapTileWithFallback('satellite',z,x,y)},
 async render(width,height,shape,pct,rasterScale=1){
   const coords=shapes[shape]; const side=Math.round(width*.08); const frame={x:side,y:Math.round(height*.18),width:width-side*2,height:Math.round(height*.62)};
   const vp=createShareMapViewport(coords,width,height,frame,pct,'satellite',rasterScale);
   const canvas=document.createElement('canvas');canvas.width=width;canvas.height=height;const ctx=canvas.getContext('2d',{alpha:false});
   const result=await drawShareMapTiles(ctx,{width,height,viewport:vp,style:'satellite',token:1});
   const projected=coords.map(([lon,lat])=>vp.project(lon,lat));
   return {drawn:result.drawn,zoom:vp.zoom,points:projected,frame,attribution:result.attribution,data:canvas.toDataURL('image/png')};
 }
};
'''

formats={'story':(1080,1920),'portrait':(1080,1350),'square':(1080,1080)}
scales=[90,95,100,200]
shapes=['short','circuit','long']

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    page=browser.new_page(viewport={'width':900,'height':700})
    errors=[]; page.on('pageerror',lambda e:errors.append(str(e)))
    page.set_content('<!doctype html><html><body></body></html>',wait_until='domcontentloaded')
    page.add_script_tag(content=source)
    assertions=[0]
    def check(condition,message):
        assertions[0]+=1
        if not condition: raise AssertionError(message)

    fallback=page.evaluate('()=>__satTest.fallback(20,100000,100000)')
    check(fallback is not None,'satélite deve encontrar tile-pai quando zoom desejado não existe')
    check(fallback['zoom']==17 and fallback['requestedZoom']==20,'fallback deve usar maior nível válido inferior sem alterar zoom lógico')
    check(fallback.get('factor')==8,'overzoom deve recortar corretamente o tile-pai para o nível lógico')

    for fmt,(width,height) in formats.items():
        for shape in shapes:
            previous=None
            for pct in scales:
                result=page.evaluate('([w,h,s,p])=>__satTest.render(w,h,s,p,1)',[width,height,shape,pct])
                check(result['drawn'],f'{fmt}/{shape}/{pct}: Satélite não pode desaparecer em zoom/composição alta')
                check(result['attribution']=='Mock satellite',f'{fmt}/{shape}/{pct}: fallback deve permanecer Satélite')
                frame=result['frame']; pts=result['points']
                check(all(frame['x']-2 <= pt[0] <= frame['x']+frame['width']+2 and frame['y']-2 <= pt[1] <= frame['y']+frame['height']+2 for pt in pts),f'{fmt}/{shape}/{pct}: rota saiu da safe area')
                check(result['data'].startswith('data:image/png'),f'{fmt}/{shape}/{pct}: composição final não foi gerada')
                if previous is not None:
                    # Route-scale changes composition, but the provider fallback must never reset/blank it.
                    check(result['zoom']>=3 and previous['zoom']>=3,f'{fmt}/{shape}/{pct}: zoom lógico inválido')
                previous=result

    check(any(item['z']>17 for item in page.evaluate('__requests')),'teste precisa realmente exercitar pedidos acima da cobertura simulada')
    check(any(item['z']==17 for item in page.evaluate('__requests')),'teste precisa realmente exercitar fallback no maior zoom nativo simulado')
    check(not errors,'nenhum erro JS durante overzoom/fallback')
    browser.close()
    print(f'✓ share satellite overzoom: {assertions[0]} assertions; 90/95/100/200, 3 formatos, 3 geometrias')
