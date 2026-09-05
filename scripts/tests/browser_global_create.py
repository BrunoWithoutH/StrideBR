#!/usr/bin/env python3
from pathlib import Path
import re
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f"Playwright indisponível: {exc}", file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
CSS_FILES = [ROOT / 'public/assets/css/style.css', ROOT / 'public/assets/css/ui-refresh.css']
HTML = '''<!doctype html><html data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><header class="site-header"><div class="header-inner"><span>StrideBR</span><span></span><div class="usersection"><details data-header-menu="toggle" class="global-create-menu"><summary aria-label="Create" title="Create">+</summary><div class="global-create-content"><span class="global-create-label">Create</span><a href="#"><strong>Activity</strong><span>Log manually</span></a></div></details></div></div></header></body></html>'''

assertions = 0
failures = []

def check(condition, message):
    global assertions
    assertions += 1
    if not condition:
        raise AssertionError(message)

def rgba(value):
    nums = [float(item) for item in re.findall(r'[\d.]+', value)]
    if len(nums) < 3:
        return None
    alpha = nums[3] if len(nums) >= 4 else 1.0
    return nums[0], nums[1], nums[2], alpha

def channel(value):
    value /= 255.0
    return value / 12.92 if value <= 0.04045 else ((value + 0.055) / 1.055) ** 2.4

def luminance(rgb):
    r, g, b = rgb[:3]
    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b)

def contrast(a, b):
    la, lb = luminance(a), luminance(b)
    return (max(la, lb) + 0.05) / (min(la, lb) + 0.05)

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for theme in ('light', 'dark'):
        page = browser.new_page(viewport={'width': 1024, 'height': 700})
        page.set_content(HTML, wait_until='domcontentloaded')
        for css in CSS_FILES:
            page.add_style_tag(path=str(css))
        page.evaluate("theme => document.documentElement.dataset.theme = theme", theme)
        page.wait_for_timeout(180)
        summary = page.locator('.global-create-menu > summary')
        menu = page.locator('.global-create-menu')
        content = page.locator('.global-create-content')
        try:
            check(summary.inner_text().strip() == '+', f'{theme}: trigger perdeu o +')
            check(summary.is_visible(), f'{theme}: trigger não está visível')
            normal = summary.evaluate("e => { const s=getComputedStyle(e); const h=getComputedStyle(e.closest('.site-header')); return {color:s.color, background:s.backgroundColor, fontSize:parseFloat(s.fontSize), header:h.backgroundColor} }")
            fg, header = rgba(normal['color']), rgba(normal['header'])
            check(fg is not None and header is not None and contrast(fg, header) >= 4.5, f"{theme}: contraste normal insuficiente: {normal}")
            check(normal['fontSize'] >= 18, f"{theme}: + pequeno demais: {normal['fontSize']}px")

            summary.hover()
            page.wait_for_timeout(180)
            hover = summary.evaluate("e => ({color:getComputedStyle(e).color, background:getComputedStyle(e).backgroundColor})")
            fg_hover, bg_hover = rgba(hover['color']), rgba(hover['background'])
            check(fg_hover is not None and bg_hover is not None and bg_hover[3] > 0 and contrast(fg_hover, bg_hover) >= 4.5, f'{theme}: contraste no hover insuficiente: {hover}')

            page.mouse.move(500, 500)
            summary.focus()
            focus = summary.evaluate("e => ({outlineStyle:getComputedStyle(e).outlineStyle, outlineWidth:getComputedStyle(e).outlineWidth, outlineColor:getComputedStyle(e).outlineColor})")
            check(focus['outlineStyle'] != 'none' and float(focus['outlineWidth'].replace('px', '') or 0) >= 2, f'{theme}: focus-visible não ficou claro: {focus}')

            summary.click()
            page.wait_for_timeout(180)
            check(menu.get_attribute('open') is not None, f'{theme}: menu não abriu')
            check(content.is_visible(), f'{theme}: conteúdo do Criar não apareceu')
            opened = summary.evaluate("e => ({color:getComputedStyle(e).color, background:getComputedStyle(e).backgroundColor})")
            fg_open, bg_open = rgba(opened['color']), rgba(opened['background'])
            check(fg_open is not None and bg_open is not None and bg_open[3] > 0 and contrast(fg_open, bg_open) >= 4.5, f'{theme}: contraste aberto insuficiente: {opened}')
            surfaces = page.evaluate("() => ({wrapper:getComputedStyle(document.querySelector('.global-create-menu')).backgroundColor, content:getComputedStyle(document.querySelector('.global-create-content')).backgroundColor})")
            wrapper, panel = rgba(surfaces['wrapper']), rgba(surfaces['content'])
            check(wrapper is not None and wrapper[3] == 0, f"{theme}: wrapper recebeu superfície indevida: {surfaces['wrapper']}")
            check(panel is not None and panel[3] > 0, f"{theme}: conteúdo não recebeu superfície própria: {surfaces['content']}")
        except Exception as exc:
            failures.append(str(exc))
        finally:
            page.close()
    browser.close()

if failures:
    print('Falhas no regression global_create:', file=sys.stderr)
    for failure in failures:
        print(f'- {failure}', file=sys.stderr)
    sys.exit(1)

print(f'✓ global_create: {assertions} assertions; light/dark normal/hover/focus/open')
