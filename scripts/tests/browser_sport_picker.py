#!/usr/bin/env python3
from pathlib import Path
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f"Playwright indisponível: {exc}", file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
HTML = '''<!doctype html><html data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>
<article class="activity-list-row"><button class="activity-row-main"><div class="activity-row-date"><strong>03</strong><span>SET</span></div><div class="activity-row-icon"><span class="sport-icon" style="--sport-icon-url:url(/assets/icons/sporticon/track_and_field.svg)"></span></div><div class="activity-row-title"><strong>Corrida</strong><span>Corrida · 07:00</span></div><span class="activity-row-open">›</span></button></article>
<div class="sport-select-picker" data-generic-sport-picker>
<select class="sport-select-native" data-generic-sport-native tabindex="-1" aria-hidden="true"><option value="">Todos os esportes</option><option value="1">Musculação</option><option value="2">Corrida</option></select>
<button type="button" class="sport-select-trigger" data-generic-sport-trigger aria-expanded="false"><span data-generic-sport-trigger-icon>◎</span><span data-generic-sport-trigger-label>Todos os esportes</span><span>⌄</span></button>
<div class="sport-select-popover" data-generic-sport-popover hidden>
<label class="sport-select-search"><span>Buscar esporte</span><input type="search" data-generic-sport-search></label>
<button type="button" class="sport-select-empty" data-generic-sport-empty>Todos</button>
<div class="sport-family-browser" data-generic-sport-browser>
<div class="sport-family-grid" data-generic-sport-family-grid>
<button type="button" class="sport-family-card" data-generic-sport-family-open="strength"><span class="sport-family-card-copy"><strong>Força</strong><small>Musculação</small></span><span class="sport-family-card-count">1</span><span>›</span></button>
<button type="button" class="sport-family-card" data-generic-sport-family-open="cardio"><span class="sport-family-card-copy"><strong>Cardio</strong><small>Corrida</small></span><span class="sport-family-card-count">1</span><span>›</span></button>
</div>
<section class="sport-family-panel" data-generic-sport-family-panel="strength" hidden><div class="sport-family-panel-head"><button type="button" class="sport-family-back context-back-button" data-generic-sport-family-back>← Categorias</button><div><strong>Força</strong></div></div><button type="button" class="sport-option sport-generic-option" data-generic-sport-option data-sport-id="1" data-sport-name="Musculação" data-search-text="musculação força">Musculação</button></section>
<section class="sport-family-panel" data-generic-sport-family-panel="cardio" hidden><div class="sport-family-panel-head"><button type="button" class="sport-family-back context-back-button" data-generic-sport-family-back>← Categorias</button><div><strong>Cardio</strong></div></div><button type="button" class="sport-option sport-generic-option" data-generic-sport-option data-sport-id="2" data-sport-name="Corrida" data-search-text="corrida cardio">Corrida</button></section>
<div data-generic-sport-no-results hidden>Nenhum</div>
</div></div></div>
</body></html>'''

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    page = browser.new_page(viewport={"width": 900, "height": 700})
    errors = []
    page.on('pageerror', lambda exc: errors.append(str(exc)))
    page.set_content(HTML)
    for css in ('style.css', 'atividades.css', 'ui-refresh.css'):
        page.add_style_tag(path=str(ROOT / 'public/assets/css' / css))
    page.add_script_tag(path=str(ROOT / 'public/assets/js/scripts.js'))
    page.evaluate('window.StrideBRSportPickerInit(document)')

    page.click('[data-generic-sport-trigger]')
    page.click('[data-generic-sport-family-open="strength"]')
    if page.locator('[data-generic-sport-family-grid]').is_visible():
        raise AssertionError('Grade de categorias continuou visível após abrir Força')
    if not page.locator('[data-generic-sport-family-panel="strength"]').is_visible():
        raise AssertionError('Painel Força não abriu')
    page.click('[data-generic-sport-family-back]')
    if not page.locator('[data-generic-sport-family-grid]').is_visible():
        raise AssertionError('Voltar não restaurou as categorias')

    row = page.locator('.activity-list-row')
    row.hover()
    bg = row.evaluate('e => getComputedStyle(e).backgroundColor')
    if bg in ('rgb(246, 247, 248)', 'rgb(255, 255, 255)'):
        raise AssertionError(f'Hover da atividade ficou claro no dark: {bg}')
    icon = page.locator('.activity-row-icon')
    icon_bg = icon.evaluate('e => getComputedStyle(e).backgroundColor')
    icon_color = icon.evaluate('e => getComputedStyle(e).color')
    if icon_bg == 'rgb(248, 249, 250)':
        raise AssertionError('Ícone da atividade manteve fundo claro no dark')
    if errors:
        raise AssertionError('Erros JS: ' + ' | '.join(errors))

    print(f'✓ sport picker browser: categorias, dark hover e ícone ({bg}, {icon_bg}, {icon_color})')
    browser.close()
