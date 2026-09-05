#!/usr/bin/env python3
from pathlib import Path
import sys
try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr)
    sys.exit(2)
ROOT=Path(__file__).resolve().parents[2]
PWA=(ROOT/'public/assets/js/pwa.js').read_text()
HTML='<!doctype html><html><head></head><body><main>StrideBR</main></body></html>'

def boot(page, media, ios):
    page.set_content(HTML, wait_until='domcontentloaded')
    page.evaluate("([m,i])=>{window.matchMedia=()=>({matches:m,addEventListener(){}});Object.defineProperty(navigator,'standalone',{configurable:true,value:i});window.__pwaMedia=m}", [media, ios])
    page.evaluate("([code])=>{const s=document.createElement('script');s.textContent=code;document.head.appendChild(s)}", [PWA])
    page.wait_for_timeout(20)

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    count=0
    def check(value, message):
        nonlocal_holder=None
        if not value:
            raise AssertionError(message)
    for media,ios,expected in [(True,False,True),(False,True,True),(False,False,False)]:
        page=browser.new_page()
        boot(page,media,ios)
        state=page.evaluate("() => ({html:document.documentElement.classList.contains('is-standalone'),body:document.body.classList.contains('is-standalone'),htmlMode:document.documentElement.dataset.displayMode,bodyMode:document.body.dataset.displayMode,central:window.StrideBRPWA?.isStandalone})")
        for key in ['html','body','central']:
            count+=1
            check(state[key] is expected, f'{key} reflete standalone={expected}')
        count+=1
        check(state['htmlMode']==('standalone' if expected else 'browser') and state['bodyMode']==state['htmlMode'], 'dataset central reflete display mode')
        page.close()
    browser.close()
    print(f'✓ PWA display mode browser: {count} assertions')
