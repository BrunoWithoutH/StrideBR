#!/usr/bin/env python3
from pathlib import Path
import sys
try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr)
    sys.exit(2)
ROOT=Path(__file__).resolve().parents[2]
HTML='''<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"></head><body><div class="activity-edit-modal is-open is-ready" data-activity-edit-modal><button class="activity-edit-modal-backdrop" data-close-activity-edit></button><section class="activity-edit-modal-panel"><header class="activity-edit-modal-header"><div><span>ATIVIDADES</span><h2>Editar atividade</h2></div><button>×</button></header><div class="activity-edit-modal-body"><iframe data-activity-edit-frame srcdoc="<!doctype html><html><body></body></html>"></iframe></div></section></div></body></html>'''
MOCK="""() => { window.StrideBRI18n={locale:'pt-BR',t:(k,v={},f=null)=>f??k,tn:(a,b,c)=>c===1?a:b,number:v=>String(v),sport:(_s,f)=>f}; }"""
def boot(page):
    page.set_content(HTML,wait_until='domcontentloaded')
    page.add_style_tag(path=str(ROOT/'public/assets/css/style.css'))
    page.add_style_tag(path=str(ROOT/'public/assets/css/atividades.css'))
    page.add_style_tag(path=str(ROOT/'public/assets/css/ui-refresh.css'))
    page.evaluate(MOCK)
    page.add_script_tag(path=str(ROOT/'public/assets/js/atividades.js'))
    page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
    page.wait_for_timeout(50)

def message(page,kind):
    page.evaluate("kind=>{const frame=document.querySelector('[data-activity-edit-frame]');window.dispatchEvent(new MessageEvent('message',{data:{type:kind},origin:window.location.origin,source:frame.contentWindow}))}",kind)
    page.wait_for_timeout(50)
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    count=[0]
    def check(v,m):
        count[0]+=1
        if not v: raise AssertionError(m)
    for width,height in [(1366,768),(390,844),(375,812),(360,640),(844,390)]:
        page=browser.new_page(viewport={'width':width,'height':height}); errors=[]; page.on('pageerror',lambda e:errors.append(str(e))); boot(page)
        message(page,'stridebr:activity-route-subview-open')
        modal=page.locator('[data-activity-edit-modal]'); panel=page.locator('.activity-edit-modal-panel'); header=page.locator('.activity-edit-modal-header'); iframe=page.locator('[data-activity-edit-frame]')
        check(modal.evaluate("el=>el.classList.contains('is-route-subview')"),f'{width}x{height}: parent entra em is-route-subview')
        check(header.is_hidden(),f'{width}x{height}: header externo some em Rota')
        pb=panel.bounding_box(); fb=iframe.bounding_box()
        if width<=760 or height<=520:
            check(pb and abs(pb['width']-width)<=2 and abs(pb['height']-height)<=2,f'{width}x{height}: parent Rota fullscreen')
        else:
            check(pb and pb['width']>=width*.94 and pb['height']>=height-28,f'{width}x{height}: parent Rota usa ~96vw e quase toda altura')
        check(fb and pb and abs(fb['width']-pb['width'])<=3 and abs(fb['height']-pb['height'])<=3,f'{width}x{height}: iframe preenche shell da Rota')
        message(page,'stridebr:activity-route-subview-close')
        check(not modal.evaluate("el=>el.classList.contains('is-route-subview')"),f'{width}x{height}: parent sai do modo Rota')
        check(not errors,f'{width}x{height}: sem erros JS {errors}')
        page.close()
    browser.close()
    print(f'✓ activity edit parent route: {count[0]} assertions; desktop/mobile/landscape')
