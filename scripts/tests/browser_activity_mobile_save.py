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
]
VIEWPORTS = [(390, 844), (375, 812), (360, 640), (844, 390)]

HTML = '''<!doctype html><html data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"></head><body>
<section class="activity-editor-shell is-open" data-activity-form>
<button type="button" class="activity-editor-backdrop"></button>
<form class="activity-editor" id="activity-form">
<div class="activity-editor-heading"><div><h2>Log activity</h2></div><button type="button" class="activity-icon-button">×</button></div>
<section class="activity-smart-title"><label class="input-field">Title<input value="Track session"></label></section>
<div class="activity-context-grid"><label class="input-field">Sport<input value="Running"></label><label class="input-field">Date<input type="date" value="2026-09-03"></label></div>
<section class="activity-model-panel">
<div class="activity-metrics-strip">
<label class="input-field">Distance<input inputmode="decimal" value="4.00"></label>
<div class="input-field"><span>Duration</span><div class="duration-segments" data-duration-field><label><span>h</span><input value="0"></label><span>:</span><label><span>min</span><input value="15"></label><span>:</span><label><span>s</span><input value="12"></label><span data-duration-ms-separator>.</span><label class="duration-ms-field" data-duration-ms-wrap><span>ms</span><input data-duration-milliseconds inputmode="numeric" maxlength="3" value="438" aria-label="Milliseconds"></label></div></div>
</div>
<div class="activity-units-editor">''' + ''.join(f'''<article class="activity-unit-row" data-unit-row><div class="input-field"><label>Segment {i}<input value="100 m"></label></div><div class="duration-segments"><label><span>s</span><input value="12"></label><span>.</span><label class="duration-ms-field"><span>ms</span><input value="438"></label></div></article>''' for i in range(1, 7)) + '''</div>
</section>
<section class="activity-route-builder has-route" data-route-editor data-route-mode="circuit"><div class="activity-route-heading"><strong>Circuit / laps</strong></div><div class="activity-route-map" style="height:210px"></div><div class="activity-route-laps"><span>10 laps</span></div></section>
<section class="activity-effort-section"><label class="input-field">Perceived effort<input type="range" min="1" max="10" value="8"></label></section>
<section class="activity-enrichment-section"><div class="input-field"><label>Equipment<input value="Track shoes"></label></div></section>
<div class="input-field activity-observations-field" data-last-field><label>Notes<textarea rows="4">Long session with several segments.</textarea></label></div>
<details class="activity-more-options" open><summary>More options</summary><div class="activity-more-options-content"><label class="input-field">Visibility<select><option>Only me</option></select></label></div></details>
<div class="activity-form-actions"><button type="button" class="activity-secondary-button">Cancel</button><button type="submit" class="activity-primary-action" data-save-activity>Save activity</button></div>
</form>
</section>
</body></html>'''

assertions = 0
failures = []

def check(condition, message):
    global assertions
    assertions += 1
    if not condition:
        raise AssertionError(message)

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for width, height in VIEWPORTS:
        for theme in ('light', 'dark'):
            page = browser.new_page(viewport={'width': width, 'height': height})
            page.set_content(HTML, wait_until='domcontentloaded')
            for css in CSS_FILES:
                page.add_style_tag(path=str(css))
            page.evaluate("theme => document.documentElement.dataset.theme = theme", theme)
            try:
                save = page.locator('[data-save-activity]')
                footer = page.locator('.activity-form-actions')
                editor = page.locator('.activity-editor')
                save_box = save.bounding_box()
                footer_box = footer.bounding_box()
                editor_box = editor.bounding_box()
                check(save_box is not None and save_box['y'] >= -0.5 and save_box['y'] + save_box['height'] <= height + 0.5, f'{width}x{height} {theme}: Save fora da viewport: {save_box}')
                check(footer_box is not None and footer_box['y'] + footer_box['height'] <= height + 0.5, f'{width}x{height} {theme}: footer fora da viewport: {footer_box}')
                check(editor_box is not None and editor_box['height'] <= height + 0.5, f'{width}x{height} {theme}: editor maior que viewport: {editor_box}')
                scroll_info = editor.evaluate("e => ({scrollHeight:e.scrollHeight,clientHeight:e.clientHeight,scrollTop:e.scrollTop})")
                check(scroll_info['scrollHeight'] > scroll_info['clientHeight'], f'{width}x{height} {theme}: fixture longa não ficou rolável')

                last = page.locator('[data-last-field] textarea')
                last.scroll_into_view_if_needed()
                page.wait_for_timeout(30)
                last_box = last.bounding_box()
                footer_box = footer.bounding_box()
                check(last_box is not None and footer_box is not None and last_box['y'] + last_box['height'] <= footer_box['y'] + 1.0, f'{width}x{height} {theme}: último campo ficou sob footer: field={last_box}, footer={footer_box}')

                last.focus()
                page.wait_for_timeout(30)
                check(page.evaluate("document.activeElement === document.querySelector('[data-last-field] textarea')"), f'{width}x{height} {theme}: foco no último campo falhou')
                keyboard_height = max(300, height - 260)
                page.set_viewport_size({'width': width, 'height': keyboard_height})
                last.scroll_into_view_if_needed()
                page.wait_for_timeout(30)
                keyboard_footer = footer.bounding_box()
                keyboard_last = last.bounding_box()
                check(keyboard_footer is not None and keyboard_footer['y'] + keyboard_footer['height'] <= keyboard_height + 0.5, f'{width}x{height} {theme}: footer escapou da viewport reduzida pelo teclado: {keyboard_footer}')
                check(keyboard_last is not None and keyboard_footer is not None and keyboard_last['y'] + keyboard_last['height'] <= keyboard_footer['y'] + 1.0, f'{width}x{height} {theme}: campo focado ficou sob footer com teclado: field={keyboard_last}, footer={keyboard_footer}')
                page.set_viewport_size({'width': width, 'height': height})
                page.evaluate("document.activeElement.blur()")
                page.wait_for_timeout(20)
                save_box = save.bounding_box()
                check(save_box is not None and save_box['y'] + save_box['height'] <= height + 0.5, f'{width}x{height} {theme}: Save não voltou à área visível após foco')

                check(page.locator('[data-duration-milliseconds]').is_visible(), f'{width}x{height} {theme}: ms ativo não está presente')
                check(page.locator('[data-unit-row]').count() == 6, f'{width}x{height} {theme}: trechos da fixture sumiram')
                check(page.locator('[data-route-mode="circuit"]').is_visible(), f'{width}x{height} {theme}: circuito aberto não está presente')
            except Exception as exc:
                failures.append(str(exc))
            finally:
                page.close()
    browser.close()

if failures:
    print('Falhas no regression activity_mobile_save_action_visible:', file=sys.stderr)
    for failure in failures:
        print(f'- {failure}', file=sys.stderr)
    sys.exit(1)

print(f'✓ activity_mobile_save_action_visible: {assertions} assertions, 4 viewports, light/dark')
