#!/usr/bin/env python3
from pathlib import Path
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
    ROOT / 'public/assets/css/ui-refresh.css',
    ROOT / 'public/assets/css/activity-sharing.css',
]
CASES = [
    ('light', 1366, 768), ('dark', 1366, 768),
    ('light', 390, 844), ('dark', 390, 844),
    ('light', 375, 812), ('dark', 375, 812),
    ('light', 360, 640), ('dark', 360, 640),
    ('light', 844, 390), ('dark', 844, 390),
]
HTML = '''<!doctype html><html data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"></head><body>
<div class="activity-share-modal">
  <button type="button" class="activity-share-backdrop"></button>
  <div class="activity-share-workspace" role="dialog" aria-modal="true">
    <aside class="activity-share-preview-window"><div class="activity-share-preview-heading"><strong>Preview</strong></div><div class="activity-share-preview-layout"><div class="activity-share-preview-stage">Preview</div></div></aside>
    <section class="activity-share-panel">
      <header><div><span>Compartilhar</span><h2>Card</h2></div><button type="button">×</button></header>
      <div class="activity-share-panel-scroll"><div style="min-height:260px;padding:16px">Controles</div></div>
      <p data-share-status></p>
      <footer>
        <details class="activity-share-more-menu">
          <summary aria-label="Mais opções" title="Mais opções">•••</summary>
          <div><button type="button" data-export-route-png>Exportar rota para PNG</button><button type="button" data-reset-share>Redefinir card</button></div>
        </details>
        <button type="button" class="activity-secondary-button" data-copy-share>Copiar card</button>
        <button type="button" class="activity-secondary-button" data-download-share>Baixar</button>
        <button type="button" class="activity-primary-action" data-native-share>Compartilhar</button>
      </footer>
    </section>
  </div>
</div>
<script>window.__clicked=[];document.querySelector('[data-export-route-png]').addEventListener('click',()=>__clicked.push('export'));document.querySelector('[data-reset-share]').addEventListener('click',()=>__clicked.push('reset'));</script>
</body></html>'''

assertions = 0
failures = []

def check(condition, message):
    global assertions
    assertions += 1
    if not condition:
        raise AssertionError(message)

def inside(inner, outer, tol=1.5):
    return inner['x'] >= outer['x'] - tol and inner['y'] >= outer['y'] - tol and inner['x'] + inner['width'] <= outer['x'] + outer['width'] + tol and inner['y'] + inner['height'] <= outer['y'] + outer['height'] + tol

def hit_test(page, selector):
    return page.locator(selector).evaluate("el => { const r=el.getBoundingClientRect(); const hit=document.elementFromPoint(r.left+r.width/2,r.top+r.height/2); return hit===el || el.contains(hit); }")

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for theme, width, height in CASES:
        page = browser.new_page(viewport={'width': width, 'height': height})
        page.set_default_timeout(2500)
        errors = []
        page.on('pageerror', lambda exc: errors.append(str(exc)))
        try:
            page.set_content(HTML, wait_until='domcontentloaded')
            for css in CSS_FILES:
                page.add_style_tag(path=str(css))
            page.evaluate("theme => document.documentElement.dataset.theme=theme", theme)
            page.wait_for_timeout(30)
            details = page.locator('.activity-share-more-menu')
            summary = details.locator('summary')
            menu = details.locator('> div')
            panel = page.locator('.activity-share-panel')
            workspace = page.locator('.activity-share-workspace')
            preview = page.locator('.activity-share-preview-window')

            summary.click()
            check(details.get_attribute('open') is not None, f'{theme} {width}x{height}: menu não abriu por clique')
            check(summary.get_attribute('aria-label') == 'Mais opções', f'{theme} {width}x{height}: nome acessível mudou')
            menu_box = menu.bounding_box()
            workspace_box = workspace.bounding_box()
            panel_box = panel.bounding_box()
            preview_box = preview.bounding_box()
            check(menu_box and workspace_box and inside(menu_box, workspace_box), f'{theme} {width}x{height}: menu saiu do workspace')
            check(menu_box and panel_box and inside(menu_box, panel_box), f'{theme} {width}x{height}: menu saiu da coluna de controles')
            if width > 720:
                check(menu_box['x'] >= preview_box['x'] + preview_box['width'] - 2, f'{theme} {width}x{height}: menu invadiu Preview')
            else:
                check(menu_box['x'] >= workspace_box['x'] - 1, f'{theme} {width}x{height}: menu saiu pela esquerda no mobile')
            check(hit_test(page, '[data-export-route-png]'), f'{theme} {width}x{height}: primeiro item está interceptado')
            check(hit_test(page, '[data-reset-share]'), f'{theme} {width}x{height}: último item está interceptado')
            page.locator('[data-export-route-png]').click()
            page.locator('[data-reset-share]').click()
            check(page.evaluate("__clicked.join(',')") == 'export,reset', f'{theme} {width}x{height}: itens não são clicáveis')

            if details.get_attribute('open') is not None:
                summary.click()
            summary.focus()
            page.keyboard.press('Enter')
            check(details.get_attribute('open') is not None, f'{theme} {width}x{height}: Enter não abriu details')
            summary.focus()
            page.keyboard.press('Space')
            check(details.get_attribute('open') is None, f'{theme} {width}x{height}: Space não alternou details')
            summary.focus()
            page.keyboard.press('Tab')
            check(page.evaluate("document.activeElement === document.querySelector('[data-copy-share]')"), f'{theme} {width}x{height}: Tab saiu da ordem do footer')
            page.keyboard.press('Shift+Tab')
            check(page.evaluate("document.activeElement === document.querySelector('.activity-share-more-menu > summary')"), f'{theme} {width}x{height}: Shift+Tab não voltou ao summary')
            check(page.evaluate("document.documentElement.scrollWidth <= innerWidth"), f'{theme} {width}x{height}: menu criou scroll horizontal')
            check(not errors, f'{theme} {width}x{height}: erros JS: {errors}')
        except Exception as exc:
            failures.append(str(exc))
        finally:
            page.close()
    browser.close()

if failures:
    print('Falhas no regression share_more_popover:', file=sys.stderr)
    for failure in failures:
        print(f'- {failure}', file=sys.stderr)
    sys.exit(1)

print(f'✓ share_more_popover: {assertions} assertions; light/dark desktop/mobile/landscape')
