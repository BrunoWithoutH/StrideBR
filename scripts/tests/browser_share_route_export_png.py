#!/usr/bin/env python3
from pathlib import Path
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
js = (ROOT / 'public/assets/js/atividades.js').read_text()
css = '\n'.join((ROOT / p).read_text() for p in [
    'public/assets/css/style.css',
    'public/assets/css/atividades.css',
    'public/assets/css/ui-refresh.css',
    'public/assets/css/activity-sharing.css',
])
style_block = js[js.index('    const SHARE_ROUTE_STYLE ='):js.index('    const wrapCanvasText =')]
projector = js[js.index('    const canvasGeoProjector ='):js.index('    const coverImage =')]
draw_start = js.index('    const shareRouteColorWithAlpha =')
draw = js[draw_start:js.index('    const shareRoundedRect =', draw_start)]
source = style_block + projector + draw + r'''
window.__routeTest={
  render(canvas,color,widthPct){
    const coords=[[-53.4,-27.36],[-53.3995,-27.3596],[-53.399,-27.36],[-53.39945,-27.36045],[-53.4,-27.36]];
    const ctx=canvas.getContext('2d',{alpha:true}); ctx.clearRect(0,0,canvas.width,canvas.height);
    const pad=Math.round(Math.min(canvas.width,canvas.height)*.09);
    const p=canvasGeoProjector(coords,pad,pad,canvas.width-pad*2,canvas.height-pad*2);
    const scale=(widthPct/100)*(canvas.width/640);
    drawRoute(ctx,coords.map(p),true,false,{color,widthScale:scale,endpointOutline:true});
  },
  renderStroke(canvas,color,widthPct){
    const ctx=canvas.getContext('2d',{alpha:true}); ctx.clearRect(0,0,canvas.width,canvas.height);
    const scale=(widthPct/100)*(canvas.width/640);
    const y=canvas.height/2;
    drawRoute(ctx,[[canvas.width*.18,y],[canvas.width*.82,y]],true,false,{color,widthScale:scale,showEndpoints:false});
  },
  pixels(canvas){const d=canvas.getContext('2d').getImageData(0,0,canvas.width,canvas.height).data;let n=0;for(let i=3;i<d.length;i+=4)if(d[i]>12)n++;return n;},
  sample(canvas){const d=canvas.getContext('2d').getImageData(0,0,canvas.width,canvas.height).data;let r=0,g=0,b=0,n=0;for(let i=0;i<d.length;i+=4){if(d[i+3]>180){r+=d[i];g+=d[i+1];b+=d[i+2];n++;}}return n?[r/n,g/n,b/n]:[0,0,0];},
  pixel(canvas,x,y){const d=canvas.getContext('2d').getImageData(Math.round(x),Math.round(y),1,1).data;return [...d];},
  layers(canvas,widthPct){
    this.renderStroke(canvas,'#4f72df',widthPct);
    const scale=(widthPct/100)*(canvas.width/640), x=canvas.width/2, y=canvas.height/2;
    return {core:this.pixel(canvas,x,y), band:this.pixel(canvas,x,y+6*scale), glow:this.pixel(canvas,x,y+17*scale)};
  },
  bounds(canvas){
    const ctx=canvas.getContext('2d'), d=ctx.getImageData(0,0,canvas.width,canvas.height).data;
    let minX=canvas.width,minY=canvas.height,maxX=-1,maxY=-1;
    for(let y=0;y<canvas.height;y++)for(let x=0;x<canvas.width;x++){const a=d[(y*canvas.width+x)*4+3];if(a>8){if(x<minX)minX=x;if(x>maxX)maxX=x;if(y<minY)minY=y;if(y>maxY)maxY=y;}}
    return maxX<0?null:{x:minX/canvas.width,y:minY/canvas.height,w:(maxX-minX+1)/canvas.width,h:(maxY-minY+1)/canvas.height};
  }
};
'''

HTML = '''<!doctype html><html data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>
<div class="activity-share-route-export-sheet" data-route-export-sheet>
<button class="activity-share-route-export-backdrop"></button>
<section class="activity-share-route-export-dialog" role="dialog" aria-modal="true"><header><div><strong>Exportar rota para PNG</strong><small>Ajuste somente a aparência.</small></div><button>×</button></header>
<div class="activity-share-route-export-preview"><canvas width="640" height="640" data-route-export-preview></canvas></div>
<div class="activity-share-route-export-controls"><fieldset class="activity-share-route-export-colors"><legend>Cor da rota</legend>
<label><input type="radio" name="c" value="#4f72df" checked><span class="is-blue"></span></label><label><input type="radio" name="c" value="#e5484d"><span class="is-red"></span></label></fieldset>
<label class="activity-share-route-export-width"><span><strong>Espessura</strong><output>100%</output></span><input type="range" min="55" max="180" value="100"></label></div>
<footer><button class="activity-secondary-button">Cancelar</button><button class="activity-primary-action">Exportar PNG</button></footer></section></div>
<script>const c=document.querySelector('canvas'),range=document.querySelector('input[type=range]'),out=document.querySelector('output');function render(){const color=document.querySelector('input[type=radio]:checked').value;out.textContent=range.value+'%';__routeTest.render(c,color,Number(range.value));}range.addEventListener('input',render);document.querySelectorAll('input[type=radio]').forEach(x=>x.addEventListener('change',render));render();</script>
</body></html>'''

assertions = 0
failures = []

def check(value, message):
    global assertions
    assertions += 1
    if not value:
        raise AssertionError(message)

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for theme in ['light', 'dark']:
        page = browser.new_page(viewport={'width': 900, 'height': 760})
        errors = []
        page.on('pageerror', lambda exc: errors.append(str(exc)))
        try:
            page.set_content(HTML.replace('<script>', '<script>' + source, 1), wait_until='domcontentloaded')
            page.add_style_tag(content=css)
            page.evaluate('t=>document.documentElement.dataset.theme=t', theme)
            page.wait_for_timeout(50)
            dialog = page.locator('.activity-share-route-export-dialog')
            check(dialog.is_visible(), f'{theme}: popup invisível')
            box = dialog.bounding_box()
            check(box and box['x'] >= 0 and box['y'] >= 0 and box['x'] + box['width'] <= 900 and box['y'] + box['height'] <= 760, f'{theme}: popup fora da viewport')

            p100 = page.evaluate('__routeTest.pixels(document.querySelector("canvas"))')
            page.locator('input[type=range]').fill('180')
            page.locator('input[type=range]').dispatch_event('input')
            page.wait_for_timeout(20)
            p180 = page.evaluate('__routeTest.pixels(document.querySelector("canvas"))')
            check(p180 > p100 * 1.18, f'{theme}: espessura não alterou preview {p100}->{p180}')

            page.evaluate('''()=>{const el=document.querySelector('input[type=radio][value="#e5484d"]');el.checked=true;el.dispatchEvent(new Event('change',{bubbles:true}))}''')
            page.wait_for_timeout(20)
            rgb = page.evaluate('__routeTest.sample(document.querySelector("canvas"))')
            check(rgb[0] > rgb[2] * 1.08, f'{theme}: cor vermelha não chegou ao canvas {rgb}')
            check(page.locator('output').inner_text() == '180%', f'{theme}: output da espessura não sincronizou')
            check(not errors, f'{theme}: erros JS {errors}')
        except Exception as exc:
            failures.append(str(exc))
        finally:
            page.close()

    page = browser.new_page()
    try:
        page.set_content('<canvas id="preview" width="640" height="640"></canvas><canvas id="export" width="1600" height="1600"></canvas>')
        page.add_script_tag(content=source)
        widths = page.evaluate('()=>({story:SHARE_ROUTE_STYLE.storyWidth,standard:SHARE_ROUTE_STYLE.standardWidth})')
        check(widths['story'] > 15 and widths['standard'] > 11.5, f'espessura padrão não aumentou {widths}')

        preview_layers = page.evaluate('__routeTest.layers(document.querySelector("#preview"),100)')
        export_layers = page.evaluate('__routeTest.layers(document.querySelector("#export"),100)')
        for name, layers in [('preview', preview_layers), ('export', export_layers)]:
            core, band, glow = layers['core'], layers['band'], layers['glow']
            check(core[3] > 220 and min(core[:3]) > 200, f'{name}: linha interna clara ausente {core}')
            check(band[3] > 190 and band[2] > band[0] * 1.45, f'{name}: stroke azul externo ausente {band}')
            check(glow[3] > 3, f'{name}: glow externo ausente {glow}')

        page.evaluate('__routeTest.render(document.querySelector("#preview"),"#4f72df",100)')
        page.evaluate('__routeTest.render(document.querySelector("#export"),"#4f72df",100)')
        preview_bounds = page.evaluate('__routeTest.bounds(document.querySelector("#preview"))')
        export_bounds = page.evaluate('__routeTest.bounds(document.querySelector("#export"))')
        check(preview_bounds is not None and export_bounds is not None, 'bounds da rota não foram calculados')
        for key in ['x', 'y', 'w', 'h']:
            check(abs(preview_bounds[key] - export_bounds[key]) < 0.012, f'preview/export divergiram em {key}: {preview_bounds} vs {export_bounds}')
    except Exception as exc:
        failures.append(str(exc))
    finally:
        page.close()
        browser.close()

if failures:
    print('Falhas no regression route export PNG:', file=sys.stderr)
    for failure in failures:
        print('- ' + failure, file=sys.stderr)
    sys.exit(1)

print(f'✓ share route export PNG browser: {assertions} assertions; glow/core/geometry light/dark')
