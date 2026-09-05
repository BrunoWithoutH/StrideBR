#!/usr/bin/env python3
from pathlib import Path
import sys
try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr); sys.exit(2)
ROOT=Path(__file__).resolve().parents[2]
assertions=0
def check(v,m):
    global assertions; assertions+=1
    if not v: raise AssertionError(m)
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    page=browser.new_page()
    page.set_content('<body class="activity-edit-embedded"><a class="activity-back-link" href="/x">Voltar</a><div class="activity-edit-actions"><a class="activity-secondary-button" href="/x">Cancelar</a></div></body>')
    page.evaluate("window.__msgs=[]; window.postMessage=(m,o)=>window.__msgs.push(m)")
    page.add_script_tag(path=str(ROOT/'public/assets/js/activity-edit-bridge.js'))
    check(page.evaluate("__msgs.some(m=>m && m.type==='stridebr:activity-edit-ready')"),'ready não enviado')
    page.locator('.activity-secondary-button').click()
    check(page.evaluate("__msgs.some(m=>m && m.type==='stridebr:activity-edit-cancel')"),'cancel não enviado')
    page.evaluate("window.dispatchEvent(new CustomEvent('stridebr:activity-route-subview',{detail:{active:true}}))")
    check(page.evaluate("__msgs.some(m=>m && m.type==='stridebr:activity-route-subview-open')"),'abertura da subview de rota não enviada ao parent')
    page.evaluate("window.dispatchEvent(new CustomEvent('stridebr:activity-route-subview',{detail:{active:false}}))")
    check(page.evaluate("__msgs.some(m=>m && m.type==='stridebr:activity-route-subview-close')"),'fechamento da subview de rota não enviado ao parent')
    page2=browser.new_page(); page2.set_content('<body><div data-activity-edit-result data-type="stridebr:activity-edit-saved" data-id="42"></div></body>')
    page2.evaluate("window.__msgs=[]; window.postMessage=(m,o)=>window.__msgs.push(m)")
    page2.add_script_tag(path=str(ROOT/'public/assets/js/activity-edit-bridge.js'))
    check(page2.evaluate("__msgs.some(m=>m && m.type==='stridebr:activity-edit-saved' && m.id==='42')"),'saved result incorreto')
    page3=browser.new_page(); page3.set_content('<body><div data-activity-edit-result data-type="stridebr:activity-edit-deleted" data-id="84"></div></body>')
    page3.evaluate("window.__msgs=[]; window.postMessage=(m,o)=>window.__msgs.push(m)")
    page3.add_script_tag(path=str(ROOT/'public/assets/js/activity-edit-bridge.js'))
    check(page3.evaluate("__msgs.some(m=>m && m.type==='stridebr:activity-edit-deleted' && m.id==='84')"),'deleted result incorreto')
    browser.close()
print(f'✓ activity edit bridge: {assertions} assertions; ready/cancel/save/delete')
