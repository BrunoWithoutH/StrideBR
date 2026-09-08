#!/usr/bin/env python3
from pathlib import Path
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f"Playwright indisponível: {exc}", file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
CSS_FILES = [ROOT / 'public/assets/css/style.css', ROOT / 'public/assets/css/ui-refresh.css']
ADS_JS = ROOT / 'public/assets/js/ads.js'

PREVIEW_HTML = '''<!doctype html><html data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"></head><body><main style="max-width:1120px;margin:0 auto;padding:24px"><section style="min-height:180px">Conteúdo anterior</section><aside class="site-ad-placement site-ad-placement--content-break" data-ad-placement="home-after-week" data-ad-preview="1" aria-label="Publicidade · Preview"><span class="site-ad-label">Publicidade · Preview</span><div class="site-ad-preview" aria-hidden="true"><span>home-after-week</span></div></aside><section style="min-height:180px">Conteúdo seguinte</section></main></body></html>'''

REAL_HTML = '''<!doctype html><html data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"></head><body><main style="max-width:1120px;margin:0 auto;padding:24px"><aside class="site-ad-placement site-ad-placement--content-end" data-ad-placement="event-detail-end" aria-label="Publicidade"><span class="site-ad-label">Publicidade</span><ins class="adsbygoogle site-ad-provider" data-ad-client="ca-pub-1234567890123456" data-ad-slot="1234567890" data-ad-format="auto" data-full-width-responsive="true"></ins></aside></main><script id="stridebr-ads-config" type="application/json">{"enabled":true,"client":"ca-pub-1234567890123456"}</script></body></html>'''

assertions = 0

def check(value, message):
    global assertions
    assertions += 1
    if not value:
        raise AssertionError(message)


def add_css(page):
    for path in CSS_FILES:
        page.add_style_tag(path=str(path))


with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])

    page = browser.new_page(viewport={'width': 1440, 'height': 900})
    requested = []
    page.on('request', lambda req: requested.append(req.url))
    page.set_content(PREVIEW_HTML)
    add_css(page)

    for width, height in ((1440, 900), (1024, 800), (768, 800), (390, 844), (360, 800), (844, 390)):
        page.set_viewport_size({'width': width, 'height': height})
        page.wait_for_timeout(20)
        wrapper = page.locator('.site-ad-placement')
        check(wrapper.is_visible(), f'preview precisa permanecer visível em {width}x{height}')
        box = wrapper.bounding_box()
        check(box is not None and box['x'] >= 0 and box['x'] + box['width'] <= width + 1, f'preview precisa caber no viewport em {width}x{height}')
        check(page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'), f'preview não pode criar overflow em {width}x{height}')

    google_preview = [u for u in requested if 'googlesyndication.com' in u or 'doubleclick.net' in u]
    check(not google_preview, 'preview local não pode fazer request ao Google')
    check(page.locator('.adsbygoogle').count() == 0, 'preview não pode criar ins adsbygoogle')
    check(page.locator('#stridebr-ads-config').count() == 0, 'preview não pode criar configuração real')

    page.evaluate("document.documentElement.setAttribute('data-theme','light')")
    border_light = page.locator('.site-ad-preview').evaluate('e => getComputedStyle(e).borderTopColor')
    page.evaluate("document.documentElement.setAttribute('data-theme','dark')")
    border_dark = page.locator('.site-ad-preview').evaluate('e => getComputedStyle(e).borderTopColor')
    check(bool(border_light) and bool(border_dark), 'preview precisa manter borda válida em light e dark')

    off = browser.new_page(viewport={'width': 390, 'height': 844})
    off.set_content('<!doctype html><html><body><main><section id="before">Antes</section><section id="after">Depois</section></main></body></html>')
    check(off.locator('.site-ad-placement').count() == 0, 'ADS OFF precisa ter zero wrapper')
    check(off.locator('.adsbygoogle').count() == 0, 'ADS OFF precisa ter zero ins')
    check(off.locator('#stridebr-ads-config').count() == 0, 'ADS OFF precisa ter zero config')

    real = browser.new_page(viewport={'width': 1024, 'height': 800})
    real_requests = []
    real.on('request', lambda req: real_requests.append(req.url))
    real.set_content(REAL_HTML)
    real.evaluate('window.__stridebrAdsProviderPromise = Promise.resolve(true); window.adsbygoogle = [];')
    add_css(real)
    real.add_script_tag(path=str(ADS_JS))
    real.wait_for_timeout(50)
    check(len(real.evaluate('window.adsbygoogle')) == 1, 'slot real deve ser inicializado uma vez')
    real.add_script_tag(path=str(ADS_JS))
    real.wait_for_timeout(50)
    check(len(real.evaluate('window.adsbygoogle')) == 1, 'reexecução do runtime não pode duplicar push do slot')
    check(not [u for u in real_requests if 'googlesyndication.com' in u or 'doubleclick.net' in u], 'teste de runtime não pode fazer request ao Google')
    real.locator('.adsbygoogle').evaluate("e => e.setAttribute('data-ad-status','unfilled')")
    real.wait_for_timeout(30)
    check(real.locator('.site-ad-placement').evaluate('e => e.hidden === true'), 'no-fill deve recolher wrapper')
    check(not real.locator('.site-ad-placement').is_visible(), 'no-fill não pode deixar espaço visível')

    browser.close()

print(f'✓ browser ads placements: {assertions} assertions; zero Google requests')
