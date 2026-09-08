#!/usr/bin/env python3
from pathlib import Path
import re, sys
try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
VIEW = (ROOT / 'public/user/atividades.php').read_text()
start = VIEW.index('<div class="activity-share-modal" data-share-modal hidden>')
end = VIEW.index("<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>", start)
modal = re.sub(r'<\?php.*?\?>', 'X', VIEW[start:end], flags=re.S)
HTML = f'<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"></head><body>{modal}</body></html>'
TARGETS = [
    '[data-share-master-switch]','[data-share-single-segment-picker]','[data-share-multiple-summary]','[data-share-session-only]',
    '[data-share-comparison-controls]','[data-share-session-compact-controls]','[data-share-session-route-option]',
    '[data-share-segments-group]','[data-share-segment-preview-picker]','[data-share-map-style-options]','[data-share-map-option]',
    '[data-share-photo-field]','[data-share-mobile-photo]','[data-share-content-options]','[data-route-export-sheet]','[data-share-camera-sheet]',
    '[data-share-color-options]'
]

def state(node):
    return node.evaluate('''el=>{const r=el.getBoundingClientRect();return {hidden:el.hidden,display:getComputedStyle(el).display,w:r.width,h:r.height}}''')

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    page = browser.new_page(viewport={'width':1280,'height':900})
    errors=[]
    page.on('pageerror', lambda e: errors.append(str(e)))
    page.set_content(HTML, wait_until='domcontentloaded')
    page.add_style_tag(path=str(ROOT/'public/assets/css/activity-sharing.css'))
    page.evaluate('''()=>{window.__STRIDEBR_SHARE_TEST_HOOKS__=true;window.StrideBRI18n={locale:'pt-BR',t:(k,v={},f=null)=>f||k,number:(v)=>String(v),sport:(_s,f)=>f};window.StrideBRBasemaps={share:{available:()=>false}};window.requestIdleCallback=()=>0}''')
    page.add_script_tag(path=str(ROOT/'public/assets/js/atividades.js'))
    page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
    page.wait_for_timeout(60)

    checks=0
    modal_node=page.locator('[data-share-modal]')
    initial=state(modal_node)
    assert initial['hidden'] is True and initial['display']=='none' and initial['w']==0 and initial['h']==0 and modal_node.is_hidden(), initial
    checks+=1

    page.evaluate('''selectors=>{const modal=document.querySelector('[data-share-modal]');modal.hidden=false;selectors.forEach(selector=>document.querySelectorAll(selector).forEach(node=>node.hidden=true))}''', TARGETS)
    assert modal_node.is_visible()
    checks+=1

    for selector in TARGETS:
        nodes=page.locator(selector)
        assert nodes.count()>0, f'missing real HTML selector {selector}'
        for index in range(nodes.count()):
            node=nodes.nth(index)
            value=state(node)
            assert value['hidden'] is True, f'hidden=false {selector}[{index}]'
            assert value['display']=='none', f'display={value["display"]} {selector}[{index}]'
            assert value['w']==0 and value['h']==0, f'bounding box {value} {selector}[{index}]'
            assert node.is_hidden(), f'Playwright visible {selector}[{index}]'
            checks+=4

    page.evaluate('''()=>document.querySelectorAll('[data-share-modal] [hidden]').forEach(node=>{node.dataset.hiddenAudit='1'})''')
    hidden_nodes=page.locator('[data-share-modal] [data-hidden-audit="1"]')
    for index in range(hidden_nodes.count()):
        node=hidden_nodes.nth(index)
        value=state(node)
        assert value['hidden'] is True and value['display']=='none' and value['w']==0 and value['h']==0 and node.is_hidden(), value
        checks+=4

    assert page.evaluate('Boolean(window.StrideBRShareTest)'), 'real share JS did not initialize'
    checks+=1
    assert not errors, errors
    browser.close()
    print(f'✓ share hidden contract: {checks} assertions using real share HTML + real CSS + real JS')
