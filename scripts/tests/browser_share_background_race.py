#!/usr/bin/env python3
from pathlib import Path
from http.server import ThreadingHTTPServer, SimpleHTTPRequestHandler
from functools import partial
import json
import threading
import time
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
PUBLIC = ROOT / 'public'
SVG = b'<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><rect width="256" height="256" fill="#d7dde3"/><path d="M0 80L256 130M0 180L256 115M90 0L145 256" stroke="#9da8b4" stroke-width="8"/><path d="M0 84L256 134M0 184L256 119M94 0L149 256" stroke="#f7f8f5" stroke-width="3"/></svg>'

class Handler(SimpleHTTPRequestHandler):
    def log_message(self, *args):
        pass

    def do_GET(self):
        if self.path.startswith('/slow-tile.svg'):
            time.sleep(.28)
            self.send_response(200)
            self.send_header('Content-Type', 'image/svg+xml')
            self.send_header('Access-Control-Allow-Origin', '*')
            self.end_headers()
            try:
                self.wfile.write(SVG)
            except BrokenPipeError:
                pass
            return
        if self.path.startswith('/fast-tile.svg'):
            self.send_response(200)
            self.send_header('Content-Type', 'image/svg+xml')
            self.send_header('Access-Control-Allow-Origin', '*')
            self.end_headers()
            try:
                self.wfile.write(SVG)
            except BrokenPipeError:
                pass
            return
        super().do_GET()

server = ThreadingHTTPServer(('127.0.0.1', 8876), partial(Handler, directory=str(PUBLIC)))
thread = threading.Thread(target=server.serve_forever, daemon=True)
thread.start()
time.sleep(.15)

TR = {
    'activity.share.format_story': 'Story',
    'activity.share.format_portrait': 'Retrato',
    'activity.share.format_square': 'Quadrado',
    'activity.share.composition_standard': 'Padrão',
    'activity.share.composition_compact': 'Compacto',
    'activity.share.distance': 'Distância',
    'activity.share.duration': 'Duração',
    'activity.share.pace': 'Ritmo',
    'activity.share.map_streets': 'Ruas',
    'activity.share.map_satellite': 'Satélite',
    'nav.physical_activity': 'Atividade física',
}

ACTIVITY = {
    'id': 'race',
    'titulo': 'Corrida teste',
    'modalidade': 'Corrida',
    'modalidade_slug': 'corrida',
    'data': '07/09/2026',
    'distancia_metros': 5000,
    'duracao_segundos': 1500,
    'metricas': [
        {'key': 'distance', 'rotulo': 'Distância', 'valor': '5,00 km'},
        {'key': 'duration', 'rotulo': 'Duração', 'valor': '25:00'},
        {'key': 'pace', 'rotulo': 'Ritmo', 'valor': '5:00/km'},
    ],
    'geojson': {
        'type': 'LineString',
        'coordinates': [[-53.405, -27.365], [-53.401, -27.361], [-53.395, -27.364], [-53.390, -27.359]],
    },
    'usa_trechos': False,
    'trechos': [],
}

GEOMETRY = {
    'ok': True,
    'roads': [
        {'kind': 'primary', 'coordinates': [[-53.410, -27.366], [-53.386, -27.357]]},
        {'kind': 'residential', 'coordinates': [[-53.404, -27.370], [-53.398, -27.355]]},
        {'kind': 'secondary', 'coordinates': [[-53.409, -27.360], [-53.389, -27.365]]},
    ],
    'areas': [
        {'kind': 'park', 'coordinates': [[-53.402, -27.366], [-53.398, -27.366], [-53.398, -27.362], [-53.402, -27.362], [-53.402, -27.366]]},
    ],
}

BOOT = '''([tr,geometry])=>{
window.__STRIDEBR_SHARE_TEST_HOOKS__=true;
window.StrideBRI18n={locale:'pt-BR',t:(k,v={},f=null)=>tr[k]||f||k,number:(v,d=1)=>Number(v).toLocaleString('pt-BR',{maximumFractionDigits:d}),sport:(_s,f)=>f};
window.StrideBRBasemaps={share:{available:()=>true,definition:(style)=>({id:style,available:true,worldTileSize:256,maxZoom:18,minFallbackZoom:3,attribution:'Test tiles'}),tile:(style,z,x,y)=>({url:`http://127.0.0.1:8876/${window.__slowTiles?'slow':'fast'}-tile.svg?${style}-${z}-${x}-${y}`})}};
window.requestIdleCallback=(cb)=>setTimeout(()=>cb({didTimeout:false,timeRemaining:()=>20}),0);
const nativeFetch=window.fetch.bind(window);
window.fetch=(url,opts={})=>{
  if(String(url).includes('/api/map-geometry.php')){
    const payload=window.__geometryFail?{ok:true,roads:[],areas:[]}:geometry;
    return new Promise(resolve=>setTimeout(()=>resolve(new Response(JSON.stringify(payload),{status:200,headers:{'Content-Type':'application/json'}})),Number(window.__geometryDelay)||0));
  }
  return nativeFetch(url,opts);
};
}'''

try:
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
        page = browser.new_page(viewport={'width': 1200, 'height': 900})
        errors = []
        page.on('pageerror', lambda exc: errors.append(str(exc)))
        page.set_content('<!doctype html><html><head></head><body><div data-share-modal></div></body></html>')
        page.evaluate(BOOT, [TR, GEOMETRY])
        page.add_script_tag(url='http://127.0.0.1:8876/assets/js/atividades.js')
        page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
        page.wait_for_timeout(80)
        page.evaluate('d=>StrideBRShareTest.setData(d)', ACTIVITY)
        checks = 0
        coords = ACTIVITY['geojson']['coordinates']

        progressive = page.evaluate('''async coords=>{
          StrideBRShareTest.clearMapCaches();
          window.__slowTiles=false;
          window.__geometryFail=false;
          window.__geometryDelay=1200;
          const c=document.createElement('canvas');c.width=360;c.height=640;
          const v=StrideBRShareTest.mapViewport(coords,360,640,{x:0,y:0,width:360,height:640},100,'street',1);
          const token=StrideBRShareTest.beginRender();
          const cfg={width:360,height:640,mapStyle:'street',mapRasterScale:1,renderPurpose:'preview',routeCoordinates:coords};
          const started=performance.now();
          const first=await StrideBRShareTest.compositeMapBackground(c,cfg,v,token);
          const elapsed=performance.now()-started;
          const firstImage=c.toDataURL();
          await new Promise(r=>setTimeout(r,1300));
          c.getContext('2d').clearRect(0,0,360,640);
          const second=await StrideBRShareTest.compositeMapBackground(c,cfg,v,token);
          return {first,second,elapsed,changed:firstImage!==c.toDataURL()};
        }''', coords)
        assert progressive['first']['drawn'] and progressive['first']['labelFree'] is False, progressive
        checks += 1
        assert progressive['elapsed'] < 1000, progressive
        checks += 1
        assert progressive['second']['drawn'] and progressive['second']['labelFree'] is True and progressive['changed'], progressive
        checks += 1

        failure = page.evaluate('''async coords=>{
          StrideBRShareTest.clearMapCaches();
          window.__slowTiles=false;
          window.__geometryFail=true;
          window.__geometryDelay=40;
          const c=document.createElement('canvas');c.width=360;c.height=640;
          const v=StrideBRShareTest.mapViewport(coords,360,640,{x:0,y:0,width:360,height:640},100,'street',1);
          const token=StrideBRShareTest.beginRender();
          const cfg={width:360,height:640,mapStyle:'street',mapRasterScale:1,renderPurpose:'preview',routeCoordinates:coords};
          const first=await StrideBRShareTest.compositeMapBackground(c,cfg,v,token);
          await new Promise(r=>setTimeout(r,100));
          c.getContext('2d').clearRect(0,0,360,640);
          const second=await StrideBRShareTest.compositeMapBackground(c,cfg,v,token);
          const pixel=Array.from(c.getContext('2d').getImageData(180,320,1,1).data);
          return {first,second,pixel};
        }''', coords)
        assert failure['first']['drawn'] and failure['first']['labelFree'] is False and failure['second']['drawn'] and failure['second']['labelFree'] is False, failure
        checks += 1
        assert failure['pixel'][3] > 0, failure
        checks += 1

        race = page.evaluate('''async d=>{
          StrideBRShareTest.clearMapCaches();
          window.__slowTiles=true;
          window.__geometryFail=false;
          window.__geometryDelay=240;
          const c=document.createElement('canvas');
          const base={data:d,scope:'activity',format:'story',width:1080,height:1920,composition:'standard',content:'route',showTitle:true,showRoute:true,selectedMetrics:d.metricas,renderPurpose:'preview',mapRasterScale:.55};
          const delay=ms=>new Promise(r=>setTimeout(r,ms));
          const fp=()=>{const x=c.getContext('2d').getImageData(0,0,c.width,c.height).data;let h=2166136261;for(let i=0;i<x.length;i+=997){h^=x[i];h=Math.imul(h,16777619);h^=x[i+1]||0;h=Math.imul(h,16777619);h^=x[i+2]||0;h=Math.imul(h,16777619)}return String(h>>>0)};
          const pending=[];
          pending.push(StrideBRShareTest.renderCard(c,{...base,mode:'map',mapStyle:'street',showMapBase:true}));await delay(8);
          pending.push(StrideBRShareTest.renderCard(c,{...base,mode:'map',mapStyle:'satellite',showMapBase:true}));await delay(8);
          pending.push(StrideBRShareTest.renderCard(c,{...base,mode:'stats',color:'deep',showMapBase:false}));await delay(8);
          pending.push(StrideBRShareTest.renderCard(c,{...base,mode:'photo',showMapBase:false}));await delay(8);
          pending.push(StrideBRShareTest.renderCard(c,{...base,mode:'transparent',showMapBase:false}));await delay(8);
          pending.push(StrideBRShareTest.renderCard(c,{...base,mode:'map',mapStyle:'street',showMapBase:true}));await delay(8);
          const final=await StrideBRShareTest.renderCard(c,{...base,mode:'stats',color:'deep',showMapBase:false});
          const stable1=fp();
          await Promise.allSettled(pending);
          await delay(80);
          const stable2=fp();
          const kinds=[['map','street'],['map','satellite'],['stats','street'],['photo','street'],['transparent','street'],['map','street'],['stats','street']];
          const cfgs=kinds.map(([mode,mapStyle])=>StrideBRShareTest.configuration({...base,mode,mapStyle,color:'deep',showMapBase:mode==='map'}));
          return {stable1,stable2,finalRendered:final.rendered,anchors:cfgs.map(x=>JSON.stringify(x.layoutModel.anchors)),stages:cfgs.map(x=>JSON.stringify(x.layoutModel.contentStage))};
        }''', ACTIVITY)
        assert race['finalRendered'] and race['stable1'] == race['stable2'], race
        checks += 1
        assert len(set(race['anchors'])) == 1 and len(set(race['stages'])) == 1, race
        checks += 1

        map_to_color = page.evaluate('''async d=>{
          StrideBRShareTest.clearMapCaches();
          window.__slowTiles=false;
          window.__geometryFail=false;
          window.__geometryDelay=0;
          const make=()=>document.createElement('canvas');
          const fp=c=>{const x=c.getContext('2d').getImageData(0,0,c.width,c.height).data;let h=2166136261;for(let i=0;i<x.length;i+=997){h^=x[i];h=Math.imul(h,16777619);h^=x[i+1]||0;h=Math.imul(h,16777619);h^=x[i+2]||0;h=Math.imul(h,16777619)}return String(h>>>0)};
          const base={data:d,scope:'activity',format:'story',width:1080,height:1920,composition:'standard',content:'route',showTitle:true,showRoute:true,selectedMetrics:d.metricas,renderPurpose:'export',mapRasterScale:.55};
          const c=make();
          await StrideBRShareTest.renderCard(c,{...base,mode:'map',mapStyle:'street',showMapBase:true});
          const map=fp(c);
          await StrideBRShareTest.renderCard(c,{...base,mode:'stats',color:'deep',showMapBase:false});
          const colorAfter=fp(c);
          const clean=make();
          await StrideBRShareTest.renderCard(clean,{...base,mode:'stats',color:'deep',showMapBase:false});
          return {map,colorAfter,clean:fp(clean)};
        }''', ACTIVITY)
        assert map_to_color['map'] != map_to_color['colorAfter'] and map_to_color['colorAfter'] == map_to_color['clean'], map_to_color
        checks += 1
        assert not errors, errors
        browser.close()
        print(f'✓ share background progressive + race: {checks} assertions')
finally:
    server.shutdown()
    server.server_close()
    thread.join(timeout=1)
