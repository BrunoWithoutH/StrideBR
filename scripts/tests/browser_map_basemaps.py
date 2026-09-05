#!/usr/bin/env python3
from pathlib import Path
import json
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f"Playwright indisponível: {exc}", file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
CSS_FILES = [
    ROOT / 'public/assets/css/style.css',
    ROOT / 'public/assets/css/atividades.css',
]
BASEMAP_JS = (ROOT / 'public/assets/js/map-basemaps.js').read_text()
VIEWPORTS = [(1440, 900), (1024, 768), (390, 844), (360, 640), (844, 390)]
HTML = '''<!doctype html><html data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>
<div id="map" class="activity-route-map"><div class="leaflet-top leaflet-left"><div class="leaflet-control zoom-fixture">＋<br>−</div></div><div class="leaflet-top leaflet-right"></div><div class="leaflet-bottom leaflet-right"><div class="leaflet-control-attribution"></div></div></div>
<style>
html,body{margin:0;overflow-x:hidden}.activity-route-map{position:relative;width:min(100%,980px);height:min(72vh,620px);margin:20px auto;overflow:hidden}.leaflet-top,.leaflet-bottom{position:absolute;z-index:800;pointer-events:none}.leaflet-top{top:0}.leaflet-bottom{bottom:0}.leaflet-left{left:0}.leaflet-right{right:0}.leaflet-control{pointer-events:auto;margin:10px}.zoom-fixture{width:34px;line-height:30px;text-align:center;background:var(--ui-panel);border:1px solid var(--ui-border);border-radius:8px}.leaflet-control-attribution{max-width:70%;padding:2px 4px;background:var(--ui-panel);font-size:9px}
</style>
</body></html>'''
MOCK = r'''() => {
  const storage = new Map();
  Object.defineProperty(window, 'localStorage', {configurable:true, value:{
    getItem:key => storage.has(key) ? storage.get(key) : null,
    setItem:(key,value) => storage.set(key, String(value)),
    removeItem:key => storage.delete(key),
    clear:() => storage.clear()
  }});
  window.__tileLayers = [];
  window.__routeState = {mode:'circuit', laps:10, coordinates:[[-53.39,-27.36],[-53.389,-27.36],[-53.39,-27.36]]};
  const container = document.getElementById('map');
  const map = {
    _container:container,
    _layers:new Set(),
    _center:{lat:-27.36,lng:-53.39},
    _zoom:17,
    getCenter(){ return {...this._center}; },
    getZoom(){ return this._zoom; },
    hasLayer(layer){ return this._layers.has(layer); },
    removeLayer(layer){ this._layers.delete(layer); },
    addLayer(layer){ this._layers.add(layer); return this; }
  };
  const overlay = {kind:'stride-route-overlay'};
  map.addLayer(overlay);
  window.__map = map;
  window.__overlay = overlay;
  window.L = {
    Control:function(){},
    control:options => ({
      options,
      onAdd:null,
      _root:null,
      addTo(target){
        this._root = this.onAdd(target);
        target._container.querySelector('.leaflet-top.leaflet-right').appendChild(this._root);
        return this;
      },
      remove(){ this._root?.remove(); }
    }),
    tileLayer:(url, options={}) => {
      const handlers = {};
      const layer = {
        url, options, handlers,
        on(name, fn){ (handlers[name] ||= []).push(fn); return this; },
        fire(name){ (handlers[name] || []).forEach(fn => fn({type:name,target:this})); },
        addTo(target){
          target._layers.add(this);
          const attribution = target._container.querySelector('.leaflet-control-attribution');
          if (attribution) attribution.innerHTML = this.options.attribution || '';
          return this;
        }
      };
      window.__tileLayers.push(layer);
      return layer;
    },
    DomEvent:{disableClickPropagation(){},disableScrollPropagation(){}}
  };
}'''

assertions = 0
failures = []

def check(condition, message):
    global assertions
    assertions += 1
    if not condition:
        raise AssertionError(message)

def inject_helper(page, key):
    page.evaluate("([code,key]) => { const s=document.createElement('script'); s.dataset.arcgisKey=key; s.textContent=code; document.head.appendChild(s); }", [BASEMAP_JS, key])

def rect_inside(inner, outer, tolerance=1.5):
    return inner['left'] >= outer['left'] - tolerance and inner['right'] <= outer['right'] + tolerance and inner['top'] >= outer['top'] - tolerance and inner['bottom'] <= outer['bottom'] + tolerance

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for width, height in VIEWPORTS:
        page = browser.new_page(viewport={'width': width, 'height': height})
        page.set_default_timeout(2500)
        errors = []
        page.on('pageerror', lambda exc: errors.append(str(exc)))
        try:
            page.set_content(HTML, wait_until='domcontentloaded')
            for css in CSS_FILES:
                page.add_style_tag(path=str(css))
            page.evaluate(MOCK)
            inject_helper(page, 'test-public-basemap-key')
            page.evaluate("localStorage.setItem('stridebr.map.basemap','satellite')")
            page.evaluate("window.__controller=StrideBRBasemaps.attach(window.__map,{controls:true,remember:true})")
            page.wait_for_timeout(50)

            initial = page.evaluate("() => ({base:__controller.get(), center:__map.getCenter(), zoom:__map.getZoom(), overlay:__map.hasLayer(__overlay), route:JSON.stringify(__routeState), stored:localStorage.getItem(StrideBRBasemaps.storageKey), attribution:document.querySelector('.leaflet-control-attribution').textContent})")
            check(initial['base'] == 'satellite', f'{width}x{height}: preferência Satélite não foi restaurada')
            check(initial['overlay'], f'{width}x{height}: overlay da rota sumiu ao anexar basemap')
            check('Esri' in initial['attribution'], f'{width}x{height}: atribuição ArcGIS não apareceu')

            control = page.locator('.stride-map-basemap-control')
            control_rect = control.bounding_box()
            map_rect = page.locator('#map').bounding_box()
            zoom_rect = page.locator('.zoom-fixture').bounding_box()
            check(control_rect is not None and map_rect is not None and rect_inside({**control_rect, 'right':control_rect['x']+control_rect['width'], 'bottom':control_rect['y']+control_rect['height'], 'left':control_rect['x'], 'top':control_rect['y']}, {**map_rect, 'right':map_rect['x']+map_rect['width'], 'bottom':map_rect['y']+map_rect['height'], 'left':map_rect['x'], 'top':map_rect['y']}), f'{width}x{height}: seletor saiu do mapa')
            check(zoom_rect is not None and control_rect['x'] >= zoom_rect['x'] + zoom_rect['width'], f'{width}x{height}: seletor cobriu zoom controls')
            check(page.evaluate("document.documentElement.scrollWidth <= innerWidth"), f'{width}x{height}: seletor criou scroll horizontal')

            selector_prefix = '.stride-map-basemap-menu' if width <= 640 else '.stride-map-basemap-segment'
            if width <= 640:
                summary = page.locator('.stride-map-basemap-mobile > summary')
                check(summary.is_visible(), f'{width}x{height}: botão Camadas mobile não apareceu')
                summary.click()
            else:
                check(page.locator('.stride-map-basemap-segment').is_visible(), f'{width}x{height}: segmented control desktop não apareceu')

            for layer_id in ('street', 'satellite', 'terrain'):
                if width <= 640 and not page.locator('.stride-map-basemap-mobile').evaluate("el => el.hasAttribute('open')"):
                    page.locator('.stride-map-basemap-mobile > summary').click()
                page.locator(f'{selector_prefix} [data-stride-basemap="{layer_id}"]').click()
                page.wait_for_timeout(10)
                state = page.evaluate("() => ({base:__controller.get(), center:__map.getCenter(), zoom:__map.getZoom(), overlay:__map.hasLayer(__overlay), route:JSON.stringify(__routeState), stored:localStorage.getItem(StrideBRBasemaps.storageKey)})")
                check(state['base'] == layer_id, f'{width}x{height}: não mudou para {layer_id}')
                check(state['center'] == initial['center'] and state['zoom'] == initial['zoom'], f'{width}x{height}: {layer_id} alterou centro/zoom')
                check(state['overlay'] and state['route'] == initial['route'], f'{width}x{height}: {layer_id} alterou overlay/estado da rota')
                check(state['stored'] == layer_id, f'{width}x{height}: preferência {layer_id} não persistiu')

            arc_layer = page.evaluate_handle("__tileLayers.find(layer => layer.url.includes('World_Hillshade'))")
            page.evaluate("layer => { layer.fire('tileerror'); layer.fire('tileerror'); }", arc_layer)
            page.wait_for_timeout(20)
            fallback = page.evaluate("() => ({base:__controller.get(), overlay:__map.hasLayer(__overlay), route:JSON.stringify(__routeState), stored:localStorage.getItem(StrideBRBasemaps.storageKey), status:document.querySelector('.stride-map-basemap-status').textContent, hidden:document.querySelector('.stride-map-basemap-status').hidden})")
            check(fallback['base'] == 'street' and fallback['stored'] == 'street', f'{width}x{height}: falha ArcGIS não voltou para Mapa')
            check(fallback['overlay'] and fallback['route'] == initial['route'], f'{width}x{height}: fallback removeu/corrompeu rota')
            check(fallback['status'] and not fallback['hidden'], f'{width}x{height}: falha ArcGIS não foi informada')
            check(not errors, f'{width}x{height}: erros JS: {errors}')
        except Exception as exc:
            failures.append(str(exc))
        finally:
            page.close()

    page = browser.new_page(viewport={'width': 1024, 'height': 768})
    page.set_default_timeout(2500)
    errors = []
    page.on('pageerror', lambda exc: errors.append(str(exc)))
    try:
        page.set_content(HTML, wait_until='domcontentloaded')
        for css in CSS_FILES:
            page.add_style_tag(path=str(css))
        page.evaluate(MOCK)
        inject_helper(page, '')
        page.evaluate("localStorage.setItem('stridebr.map.basemap','satellite')")
        page.evaluate("window.__controller=StrideBRBasemaps.attach(window.__map,{controls:true,remember:true})")
        check(page.evaluate("__controller.get()") == 'street', 'key ausente não caiu para Mapa')
        check(page.locator('.stride-map-basemap-segment [data-stride-basemap="satellite"]').is_disabled(), 'Satélite não ficou desabilitado sem key')
        check(page.locator('.stride-map-basemap-segment [data-stride-basemap="terrain"]').is_disabled(), 'Relevo não ficou desabilitado sem key')
        check(page.evaluate("__map.hasLayer(__overlay)"), 'key ausente afetou overlay da rota')
        check('OpenStreetMap' in page.locator('.leaflet-control-attribution').inner_text(), 'fallback sem key perdeu atribuição OSM')
        check(not errors, f'key ausente gerou erro JS: {errors}')
    except Exception as exc:
        failures.append(str(exc))
    finally:
        page.close()
    browser.close()

if failures:
    print('Falhas no regression map_basemaps:', file=sys.stderr)
    for failure in failures:
        print(f'- {failure}', file=sys.stderr)
    sys.exit(1)

print(f'✓ map_basemaps: {assertions} assertions; 5 viewports + key ausente + fallback de tile')
