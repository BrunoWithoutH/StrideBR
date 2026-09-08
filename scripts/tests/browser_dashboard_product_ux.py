#!/usr/bin/env python3
from pathlib import Path
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f"Playwright indisponível: {exc}", file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
HTML = '''<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"></head><body>
<main class="main-content dashboard-page"><div class="dashboard-shell">
<header class="dashboard-heading"><div><span class="dashboard-eyebrow">INÍCIO</span><h1>Boa tarde, Bruno</h1><p>Seu treino e sua semana, sem distrações.</p></div><div class="dashboard-heading-actions"><a class="dashboard-button dashboard-button-secondary">Gravar com GPS</a><a class="dashboard-button dashboard-button-primary">+ Registrar atividade</a></div></header>
<section class="dashboard-today"><div class="dashboard-today-date"><span class="dashboard-eyebrow">HOJE</span><strong>Segunda-feira, 7 de setembro</strong></div><div class="dashboard-today-main"><span class="dashboard-today-status">PLANEJADO</span><h2>Corrida leve · 6 km</h2><p>18:30 · Base 10 km</p></div><div class="dashboard-today-actions"><a class="dashboard-button dashboard-button-primary">Começar</a><a class="dashboard-button dashboard-button-secondary">Ver treino</a></div></section>
<section class="dashboard-panel dashboard-week-panel"><div class="dashboard-panel-heading"><div><span class="dashboard-eyebrow">ESTA SEMANA</span><h2>Consistência</h2></div><strong>31 AGO – 6 SET</strong></div><div class="dashboard-week-consistency" role="list">
<div class="dashboard-week-day has-activity"><strong>SEG</strong><span class="dashboard-week-date">31</span><span class="dashboard-week-markers"><span class="dashboard-week-sport">🏃</span></span></div>
<div class="dashboard-week-day"><strong>TER</strong><span class="dashboard-week-date">1</span><span class="dashboard-week-markers"><i class="dashboard-week-empty-mark"></i></span></div>
<div class="dashboard-week-day has-activity"><strong>QUA</strong><span class="dashboard-week-date">2</span><span class="dashboard-week-markers"><span class="dashboard-week-sport">🏋️</span></span></div>
<div class="dashboard-week-day has-activity"><strong>QUI</strong><span class="dashboard-week-date">3</span><span class="dashboard-week-markers"><span class="dashboard-week-sport">🏃</span><span class="dashboard-week-sport">🚲</span></span><small>2</small></div>
<div class="dashboard-week-day"><strong>SEX</strong><span class="dashboard-week-date">4</span><span class="dashboard-week-markers"><i class="dashboard-week-empty-mark"></i></span></div>
<div class="dashboard-week-day has-activity"><strong>SÁB</strong><span class="dashboard-week-date">5</span><span class="dashboard-week-markers"><span class="dashboard-week-sport">🏋️</span></span></div>
<div class="dashboard-week-day is-today"><strong>DOM</strong><span class="dashboard-week-date">6</span><span class="dashboard-week-markers"><i class="dashboard-week-empty-mark"></i></span></div>
</div><div class="dashboard-week-summary"><strong>5 atividades · 3h18 · 22,4 km · +210 m</strong><a>Ver progresso</a></div></section>
<div class="dashboard-modules dashboard-modules-fixed"><section class="dashboard-panel dashboard-module"><div class="dashboard-panel-heading"><h2>Próximos treinos</h2><a>Ver cronograma</a></div></section><section class="dashboard-panel dashboard-module"><div class="dashboard-panel-heading"><h2>Atividades recentes</h2><a>Histórico</a></div></section></div>
</div></main></body></html>'''

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    n = 0
    def check(value, message):
        nonlocal_dummy = None
        global n
        n += 1
        if not value:
            raise AssertionError(message)

    desktop = browser.new_page(viewport={'width': 1440, 'height': 1000})
    desktop.set_content(HTML)
    desktop.add_style_tag(path=str(ROOT / 'public/assets/css/style.css'))
    desktop.add_style_tag(path=str(ROOT / 'public/assets/css/dashboard.css'))
    desktop.add_style_tag(path=str(ROOT / 'public/assets/css/ui-refresh.css'))
    today = desktop.locator('.dashboard-today').bounding_box()
    week = desktop.locator('.dashboard-week-panel').bounding_box()
    check(today['y'] < week['y'], 'Hoje vem antes da semana')
    check(desktop.locator('.dashboard-week-day').count() == 7, 'semana mostra os sete dias')
    check(desktop.locator('.dashboard-week-day').nth(3).locator('.dashboard-week-sport').count() == 2, 'duas atividades permanecem distinguíveis')

    mobile = browser.new_page(viewport={'width': 390, 'height': 844})
    mobile.set_content(HTML)
    mobile.add_style_tag(path=str(ROOT / 'public/assets/css/style.css'))
    mobile.add_style_tag(path=str(ROOT / 'public/assets/css/dashboard.css'))
    mobile.add_style_tag(path=str(ROOT / 'public/assets/css/ui-refresh.css'))
    grid = mobile.locator('.dashboard-week-consistency')
    grid_box = grid.bounding_box()
    last_box = mobile.locator('.dashboard-week-day').nth(6).bounding_box()
    check(last_box['x'] + last_box['width'] <= grid_box['x'] + grid_box['width'] + 1, 'os sete dias cabem na Home mobile sem corte horizontal')
    check(mobile.evaluate('document.documentElement.scrollWidth <= window.innerWidth'), 'Home mobile não cria overflow horizontal global')
    heading_buttons = mobile.locator('.dashboard-heading-actions .dashboard-button')
    check(all((heading_buttons.nth(i).bounding_box()['height'] >= 44) for i in range(heading_buttons.count())), 'ações principais no mobile têm alvo de toque de 44px')
    today_buttons = mobile.locator('.dashboard-today-actions .dashboard-button')
    check(all((today_buttons.nth(i).bounding_box()['height'] >= 44) for i in range(today_buttons.count())), 'ações de Hoje no mobile têm alvo de toque de 44px')
    check(mobile.locator('.dashboard-week-day').nth(3).locator('.dashboard-week-sport').count() == 2, 'mobile não transforma duas atividades em barra de magnitude')

    browser.close()
    print(f'✓ browser dashboard product UX: {n} assertions')
