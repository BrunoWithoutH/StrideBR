#!/usr/bin/env python3
from pathlib import Path
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
js = (ROOT / 'public/assets/js/dashboard.js').read_text()
html = f'''<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"></head><body>
<div class="container-fluid"><main class="main-content dashboard-page"><div class="dashboard-shell">
<section class="dashboard-panel dashboard-week-panel" style="overflow:hidden"><div class="dashboard-panel-heading"><div><span class="dashboard-eyebrow">ESTA SEMANA</span><h2>Esta semana</h2></div></div>
<div class="dashboard-week-consistency" role="list">
<div class="dashboard-week-day has-activity" role="listitem"><strong>SEG</strong><span class="dashboard-week-date">07</span><span class="dashboard-week-markers">
<a class="dashboard-week-sport" href="/user/atividades.php#atividade-run-1" data-week-popover-trigger data-week-popover-template="t-run-1" aria-haspopup="dialog" aria-expanded="false" aria-label="Corrida à noite, 19:05, 5 km, 31:42"><span class="sport-icon">🏃</span></a>
<template id="t-run-1"><div class="dashboard-week-popover-activity"><strong>Corrida à noite</strong><span>19:05 · 5 km · 31:42</span><a href="/user/atividades.php#atividade-run-1">Ver atividade</a></div></template>
</span></div>
<div class="dashboard-week-day has-activity" role="listitem"><strong>TER</strong><span class="dashboard-week-date">08</span><span class="dashboard-week-markers">
<a class="dashboard-week-sport" href="/user/atividades.php#atividade-strength-1" data-week-popover-trigger data-week-popover-template="t-strength" aria-haspopup="dialog" aria-expanded="false" aria-label="Musculação, 14:03"><span class="sport-icon">🏋️</span></a>
<template id="t-strength"><div class="dashboard-week-popover-activity"><strong>Musculação</strong><span>14:03</span><a href="/user/atividades.php#atividade-strength-1">Ver atividade</a></div></template>
</span></div>
<div class="dashboard-week-day has-activity" role="listitem"><strong>QUA</strong><span class="dashboard-week-date">09</span><span class="dashboard-week-markers">
<a class="dashboard-week-sport" href="/user/atividades.php#atividade-run-a" data-week-popover-trigger data-week-popover-template="t-run-a" aria-haspopup="dialog" aria-expanded="false" aria-label="Corrida cedo, 07:00, 5 km, 30:00"><span class="sport-icon">🏃</span></a>
<template id="t-run-a"><div class="dashboard-week-popover-activity"><strong>Corrida cedo</strong><span>07:00 · 5 km · 30:00</span><a href="/user/atividades.php#atividade-run-a">Ver atividade</a></div></template>
<a class="dashboard-week-sport" href="/user/atividades.php#atividade-run-b" data-week-popover-trigger data-week-popover-template="t-run-b" aria-haspopup="dialog" aria-expanded="false" aria-label="Corrida à noite, 19:00, 5 km, 31:00"><span class="sport-icon">🏃</span></a>
<template id="t-run-b"><div class="dashboard-week-popover-activity"><strong>Corrida à noite</strong><span>19:00 · 5 km · 31:00</span><a href="/user/atividades.php#atividade-run-b">Ver atividade</a></div></template>
</span><small>2</small></div>
<div class="dashboard-week-day"><strong>QUI</strong><span class="dashboard-week-date">10</span><span class="dashboard-week-markers"><i class="dashboard-week-empty-mark" aria-hidden="true"></i></span></div>
<div class="dashboard-week-day"><strong>SEX</strong><span class="dashboard-week-date">11</span><span class="dashboard-week-markers"><i class="dashboard-week-empty-mark" aria-hidden="true"></i></span></div>
<div class="dashboard-week-day"><strong>SÁB</strong><span class="dashboard-week-date">12</span><span class="dashboard-week-markers"><i class="dashboard-week-empty-mark" aria-hidden="true"></i></span></div>
<div class="dashboard-week-day has-activity" role="listitem"><strong>DOM</strong><span class="dashboard-week-date">13</span><span class="dashboard-week-markers">
<a class="dashboard-week-sport" href="/user/atividades.php#atividade-track-400" data-week-popover-trigger data-week-popover-template="t-track" aria-haspopup="dialog" aria-expanded="false" aria-label="400 m, 18:27, 400 m, 1:17,921"><span class="sport-icon">🏃</span></a>
<template id="t-track"><div class="dashboard-week-popover-activity"><strong>400 m</strong><span>18:27 · 400 m · 1:17,921</span><a href="/user/atividades.php#atividade-track-400">Ver atividade</a></div></template>
<a class="dashboard-week-sport" href="/user/atividades.php#atividade-ride" data-week-popover-trigger data-week-popover-template="t-ride" aria-haspopup="dialog" aria-expanded="false" aria-label="Pedal, 09:00, 24 km, 1:10:00"><span class="sport-icon">🚲</span></a>
<template id="t-ride"><div class="dashboard-week-popover-activity"><strong>Pedal</strong><span>09:00 · 24 km · 1:10:00</span><a href="/user/atividades.php#atividade-ride">Ver atividade</a></div></template>
<button type="button" class="dashboard-week-more" data-week-popover-trigger data-week-popover-template="t-more" aria-haspopup="dialog" aria-expanded="false" aria-label="2 atividades a mais">+2</button>
<template id="t-more"><div class="dashboard-week-popover-list"><strong>2 atividades a mais</strong><a href="/user/atividades.php#atividade-extra-1"><span>Corrida leve</span><small>07:10 · 5 km</small></a><a href="/user/atividades.php#atividade-extra-2"><span>Musculação</span><small>18:30 · 46 min</small></a></div></template>
</span><small>4</small></div>
</div>
<div class="dashboard-week-summary"><strong>8 atividades · 15 km</strong><a href="/user/atividades.php">Ver atividades</a></div>
<div class="dashboard-week-popover" data-dashboard-week-popover role="dialog" aria-modal="false" hidden></div>
</section></div></main></div>
<script>{js}</script></body></html>'''

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    assertions = 0
    def check(value, message):
        nonlocal_dummy = None
        global assertions
        assertions += 1
        if not value:
            raise AssertionError(message)

    desktop = browser.new_page(viewport={'width': 1440, 'height': 900})
    desktop.set_content(html)
    desktop.add_style_tag(path=str(ROOT / 'public/assets/css/style.css'))
    desktop.add_style_tag(path=str(ROOT / 'public/assets/css/dashboard.css'))
    desktop.add_style_tag(path=str(ROOT / 'public/assets/css/ui-refresh.css'))

    markers = desktop.locator('.dashboard-week-sport')
    check(markers.count() == 6, 'atividades da mesma modalidade continuam marcadores individuais')
    first = desktop.locator('a[href="#invalid"]') if False else markers.nth(0)
    first.hover()
    popover = desktop.locator('[data-dashboard-week-popover]')
    check(popover.is_visible(), 'hover desktop abre detalhe contextual')
    check('Corrida à noite' in popover.inner_text(), 'tooltip mostra título da atividade')
    check('19:05 · 5 km · 31:42' in popover.inner_text(), 'tooltip mostra hora, distância e duração')
    check(popover.locator('a').get_attribute('href') == '/user/atividades.php#atividade-run-1', 'popover abre a atividade correta')
    desktop.keyboard.press('Escape')

    strength = markers.nth(1)
    strength.focus()
    desktop.wait_for_timeout(30)
    check(popover.is_visible(), 'focus por teclado abre detalhe contextual')
    strength_text = popover.inner_text()
    check('14:03' in strength_text and 'km' not in strength_text and 'min' not in strength_text and ':00' not in strength_text, 'atividade sem métricas omite placeholders')

    sunday = desktop.locator('.dashboard-week-day').nth(6).locator('.dashboard-week-sport').nth(0)
    sunday.hover()
    box = popover.bounding_box()
    check(box['x'] >= 0 and box['x'] + box['width'] <= 1440, 'tooltip de última coluna permanece dentro do viewport')
    check(box['y'] >= 0 and box['y'] + box['height'] <= 900, 'tooltip não corta verticalmente')
    check(desktop.evaluate("document.querySelector('[data-dashboard-week-popover]').parentElement === document.body"), 'popover é movido para body e escapa do overflow do painel')
    check('400 m' in popover.inner_text() and '1:17,921' in popover.inner_text(), 'atividade de pista preserva apresentação semântica no tooltip')

    more = desktop.locator('.dashboard-week-more')
    more.hover()
    check(popover.locator('.dashboard-week-popover-list a').count() == 2, '+N lista exatamente as atividades restantes')
    check('Corrida leve' in popover.inner_text() and 'Musculação' in popover.inner_text(), '+N revela quais atividades ficaram ocultas')

    desktop.keyboard.press('Escape')
    check(not popover.is_visible(), 'Escape fecha popover')

    context = browser.new_context(viewport={'width': 390, 'height': 844}, is_mobile=True, has_touch=True)
    mobile = context.new_page()
    mobile.set_content(html)
    mobile.add_style_tag(path=str(ROOT / 'public/assets/css/style.css'))
    mobile.add_style_tag(path=str(ROOT / 'public/assets/css/dashboard.css'))
    mobile.add_style_tag(path=str(ROOT / 'public/assets/css/ui-refresh.css'))
    mobile.wait_for_timeout(80)
    mobile_marker = mobile.locator('.dashboard-week-sport').nth(0)
    mobile_marker.tap()
    mobile.wait_for_timeout(30)
    mobile_popover = mobile.locator('[data-dashboard-week-popover]')
    check(mobile_popover.is_visible(), 'primeiro tap mobile abre popover em vez de depender de hover')
    check(mobile.url == 'about:blank', 'primeiro tap mobile não navega imediatamente')
    check(mobile_popover.locator('a').get_attribute('href') == '/user/atividades.php#atividade-run-1', 'popover mobile oferece ação clara para abrir atividade')
    mbox = mobile_popover.bounding_box()
    check(mbox['x'] >= 0 and mbox['x'] + mbox['width'] <= 390, 'popover mobile cabe horizontalmente')
    check(mbox['y'] >= 0 and mbox['y'] + mbox['height'] <= 844, 'popover mobile cabe verticalmente')
    mobile.locator('.dashboard-week-more').tap()
    check(mobile_popover.locator('.dashboard-week-popover-list a').count() == 2, '+N também é explorável por toque')
    check(mobile.evaluate('document.documentElement.scrollWidth <= window.innerWidth'), 'interação semanal não cria overflow horizontal')
    context.close()

    browser.close()
    print(f'✓ browser dashboard week activity popover: {assertions} assertions')
