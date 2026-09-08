#!/usr/bin/env python3
from pathlib import Path
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f"Playwright indisponível: {exc}", file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
OUT = Path('/mnt/data/stridebr_visual_consistency_final')
OUT.mkdir(parents=True, exist_ok=True)

sport_options = ''.join(
    f'<button type="button" class="sport-option sport-generic-option" data-generic-sport-option data-sport-id="{i}" data-sport-name="Modalidade {i}" data-search-text="modalidade {i}"><span>Modalidade {i}</span></button>'
    for i in range(1, 13)
)

exercise_rows = ''.join(f'''
<article class="library-exercise-card" data-library-card data-library-type="{'personal' if i % 3 == 0 else 'system'}" data-library-text="exercise {i} running strength">
  <div class="library-exercise-main">
    <div class="library-card-top"><span class="library-origin{' is-personal' if i % 3 == 0 else ''}">{'Seu' if i % 3 == 0 else 'StrideBR'}</span><h2>Exercise {i} — unilateral strength</h2></div>
    <p class="library-exercise-description">Movimento técnico com descrição curta para validar densidade e quebra responsiva.</p>
  </div>
  <div class="library-exercise-tags"><span><b>Categoria</b> Força funcional</span><span><b>Esportes</b> Corrida, Musculação</span></div>
  <div class="library-card-actions">
    {f'<button type="button" class="secondary-button">Editar</button><details class="library-more-menu"><summary aria-label="Mais ações">•••</summary><div><button type="button" class="is-danger">Remover</button></div></details>' if i % 3 == 0 else '<button type="button" class="secondary-button">Create copy</button>'}
  </div>
</article>''' for i in range(1, 7))

HTML = f'''<!doctype html>
<html data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>StrideBR visual consistency</title></head>
<body><div class="container-fluid"><main class="main-content library-page workout-library-page">
<div class="workout-library-shell">
<header class="library-page-heading"><div><span class="eyebrow">TRAINING</span><h1>Library</h1><p>Workouts and exercises you can reuse in training.</p></div><div class="library-heading-actions"><button type="button" class="secondary-button">New category</button><button type="button" class="primary-button">+ New exercise</button></div></header>
<nav class="workout-library-tabs" aria-label="Library"><a href="#">Workouts</a><a class="is-active" href="#">Exercises</a></nav>
<section class="library-toolbar content-card"><div class="library-filter-group"><button type="button" class="view-button is-active">All</button><button type="button" class="view-button">StrideBR</button><button type="button" class="view-button">My exercises</button><span class="library-result-count"><strong>6</strong> visible</span></div><label class="library-search-field"><span class="sr-only">Search exercise</span><input type="search" placeholder="Search exercise"></label></section>
<section class="exercise-library-grid library-exercise-grid-modern">{exercise_rows}</section>
<section class="content-card visual-actions-panel"><div><span class="eyebrow">AÇÕES</span><h2>Estados equivalentes</h2></div><div class="visual-actions-row"><button class="primary-button">Registrar</button><button class="secondary-button">View agenda</button><button class="context-back-button">← Voltar</button><button class="ghost-button visual-ghost">Ver mais</button><button class="danger-action">Excluir</button></div></section>
<section class="content-card visual-picker-panel"><div><span class="eyebrow">SELETOR</span><h2>Modalidade</h2></div><div class="sport-select-picker" data-generic-sport-picker>
<select class="sport-select-native" data-generic-sport-native tabindex="-1" aria-hidden="true"><option value="">Todos os esportes</option>{''.join(f'<option value="{i}">Modalidade {i}</option>' for i in range(1,13))}</select>
<button type="button" class="sport-select-trigger" data-generic-sport-trigger aria-expanded="false"><span data-generic-sport-trigger-icon>◎</span><span data-generic-sport-trigger-label>Todos os esportes</span><span class="sport-select-trigger-arrow">⌄</span></button>
<div class="sport-select-popover" data-generic-sport-popover hidden><label class="sport-select-search"><span>Buscar esporte</span><input type="search" data-generic-sport-search></label><button type="button" class="sport-select-empty" data-generic-sport-empty>Todos os esportes</button><div class="sport-family-browser" data-generic-sport-browser>
<div class="sport-family-grid" data-generic-sport-family-grid><button type="button" class="sport-family-card" data-generic-sport-family-open="running"><span class="sport-family-card-copy"><strong>Running</strong><small>Road, track and related disciplines</small></span><span class="sport-family-card-count">12</span><span class="sport-family-card-arrow">›</span></button><button type="button" class="sport-family-card" data-generic-sport-family-open="strength"><span class="sport-family-card-copy"><strong>Strength</strong><small>Strength training</small></span><span class="sport-family-card-count">4</span><span class="sport-family-card-arrow">›</span></button></div>
<section class="sport-family-panel" data-generic-sport-family-panel="running" hidden><div class="sport-family-panel-head"><button type="button" class="sport-family-back context-back-button" data-generic-sport-family-back>← Categories</button><div><strong>Running</strong><small>Choose a discipline</small></div></div><div class="sport-family-section"><div class="sport-family-section-title">Most common</div>{sport_options}</div></section>
<section class="sport-family-panel" data-generic-sport-family-panel="strength" hidden><div class="sport-family-panel-head"><button type="button" class="sport-family-back context-back-button" data-generic-sport-family-back>← Categories</button><div><strong>Strength</strong><small>Choose a discipline</small></div></div><div class="sport-family-section">{sport_options[:0]}</div></section>
<div data-generic-sport-no-results hidden>Nenhum resultado</div></div></div></div></section>
</div></main></div></body></html>'''

EXTRA_CSS = '''
.visual-actions-panel,.visual-picker-panel{display:grid;gap:12px;margin-top:14px;padding:14px}.visual-actions-panel h2,.visual-picker-panel h2{margin:0}.visual-actions-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.visual-ghost{min-height:var(--control-compact);padding:0 var(--space-2);border:1px solid transparent;background:transparent;color:var(--ui-text-secondary);font:inherit;font-weight:650;cursor:pointer}.visual-ghost:hover{background:var(--ui-surface-hover);color:var(--ui-text-strong)}.visual-picker-panel .sport-select-picker{width:min(520px,100%)}@media(max-width:520px){.visual-actions-row{display:grid;grid-template-columns:1fr 1fr}.visual-actions-row>*{width:100%}}
'''

assertions = 0

def assert_true(condition, message):
    global assertions
    assertions += 1
    if not condition:
        raise AssertionError(message)


def apply_assets(page):
    page.set_content(HTML)
    for css in ('style.css', 'cronogramas.css', 'ui-refresh.css'):
        page.add_style_tag(path=str(ROOT / 'public/assets/css' / css))
    page.add_style_tag(content=EXTRA_CSS)
    page.add_script_tag(path=str(ROOT / 'public/assets/js/scripts.js'))
    page.evaluate('window.StrideBRSportPickerInit(document)')


def no_overflow(page, label):
    values = page.evaluate('''() => ({sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth, bw: document.body.scrollWidth})''')
    assert_true(values['sw'] <= values['cw'] + 1, f'{label}: document overflow {values}')
    assert_true(values['bw'] <= values['cw'] + 1, f'{label}: body overflow {values}')


def rgb_tuple(value):
    start = value.find('(')
    end = value.find(')')
    return tuple(int(float(x.strip())) for x in value[start+1:end].split(',')[:3])



with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    page = browser.new_page(viewport={'width': 1440, 'height': 1000})
    errors = []
    page.on('pageerror', lambda exc: errors.append(str(exc)))
    apply_assets(page)

    for width, height in ((1440, 1000), (1024, 900), (768, 900), (390, 844), (360, 800), (844, 390)):
        page.set_viewport_size({'width': width, 'height': height})
        page.evaluate("document.documentElement.setAttribute('data-theme','dark')")
        page.wait_for_timeout(25)
        no_overflow(page, f'{width}x{height} dark')

    page.set_viewport_size({'width': 1440, 'height': 1000})
    page.evaluate("document.documentElement.setAttribute('data-theme','dark')")
    card = page.locator('.library-exercise-grid-modern .library-exercise-card').first
    border = card.evaluate('e => getComputedStyle(e).borderTopColor')
    token_probe = page.evaluate('''() => { const e=document.createElement('i'); e.style.cssText='position:absolute;border:1px solid var(--ui-border);visibility:hidden'; document.body.appendChild(e); const c=getComputedStyle(e).borderTopColor; e.remove(); return c }''')
    assert_true(border == token_probe, f'Library border divergiu do token: {border} != {token_probe}')
    assert_true(max(rgb_tuple(border)) < 180, f'Library dark border claro demais: {border}')
    create_copy = page.locator('.library-card-actions .secondary-button').filter(has_text='Create copy').first
    assert_true(create_copy.is_visible(), 'Create copy não está visível')
    assert_true(create_copy.evaluate("e => getComputedStyle(e).whiteSpace") == 'nowrap', 'Create copy pode quebrar linha')
    grid_box = page.locator('.library-exercise-grid-modern').bounding_box()
    page.screenshot(path=str(OUT / 'library_exercises_dark_desktop.png'), clip={'x':0,'y':0,'width':1440,'height':min(page.evaluate('document.documentElement.scrollHeight'), grid_box['y'] + grid_box['height'] + 30)})

    page.set_viewport_size({'width': 390, 'height': 844})
    page.evaluate("document.documentElement.setAttribute('data-theme','dark')")
    no_overflow(page, 'library mobile')
    grid_box = page.locator('.library-exercise-grid-modern').bounding_box()
    page.screenshot(path=str(OUT / 'library_exercises_dark_mobile.png'), clip={'x':0,'y':0,'width':390,'height':min(page.evaluate('document.documentElement.scrollHeight'), grid_box['y'] + grid_box['height'] + 24)})

    page.set_viewport_size({'width': 1024, 'height': 800})
    page.click('[data-generic-sport-trigger]')
    page.click('[data-generic-sport-family-open="running"]')
    back = page.locator('[data-generic-sport-family-back]').first
    assert_true(back.is_visible(), 'Back/Categories não aparece na subcategoria')
    assert_true(back.evaluate('e => e.getBoundingClientRect().height') >= 30, 'Back desktop tem alvo pequeno demais')
    back_border = back.evaluate('e => getComputedStyle(e).borderTopColor')
    assert_true(rgb_tuple(back_border) != (0, 0, 0), 'Back não possui borda perceptível')
    back.focus()
    page.keyboard.press('Tab')
    page.keyboard.press('Shift+Tab')
    outline = back.evaluate('e => getComputedStyle(e).outlineStyle')
    assert_true(outline != 'none', 'Back não possui focus-visible perceptível')
    grid_hidden = page.locator('[data-generic-sport-family-grid]')
    assert_true(grid_hidden.get_attribute('hidden') is not None, 'grade não recebeu hidden ao abrir família')
    assert_true(grid_hidden.evaluate("e => getComputedStyle(e).display") == 'none', 'hidden foi sobrescrito por display grid')
    assert_true(grid_hidden.bounding_box() is None, 'hidden ainda possui bounding box')
    panel = page.locator('[data-generic-sport-family-panel="running"]')
    panel.screenshot(path=str(OUT / 'sport_picker_subcategory_dark.png'))
    back.screenshot(path=str(OUT / 'back_categories_dark.png'))
    cols = page.locator('[data-generic-sport-family-panel="running"] .sport-generic-option').all()
    assert_true(len(cols) == 12, 'fixture de Most common não possui 12 itens')
    no_overflow(page, 'sport picker 1024 open')

    page.click('[data-generic-sport-family-back]')
    page.set_viewport_size({'width': 390, 'height': 844})
    page.locator('[data-generic-sport-family-open="running"]').click()
    back = page.locator('[data-generic-sport-family-back]').first
    assert_true(back.evaluate('e => e.getBoundingClientRect().height') >= 40, 'Back mobile não possui alvo touch confortável')
    no_overflow(page, 'sport picker 390 open')
    mobile_pop = page.locator('[data-generic-sport-popover]')
    assert_true(mobile_pop.evaluate('e => e.getBoundingClientRect().bottom <= innerHeight + 1'), 'popover mobile extrapola viewport')
    page.screenshot(path=str(OUT / 'sport_picker_mobile_dark.png'), full_page=False)

    page.set_viewport_size({'width': 844, 'height': 390})
    page.evaluate("window.dispatchEvent(new Event('resize'))")
    page.wait_for_timeout(40)
    no_overflow(page, 'sport picker landscape open')
    pop = page.locator('[data-generic-sport-popover]')
    metrics = pop.evaluate("e => { const r=e.getBoundingClientRect(); return {top:r.top,bottom:r.bottom,height:r.height,innerHeight,style:e.getAttribute('style'),position:getComputedStyle(e).position,maxHeight:getComputedStyle(e).maxHeight} }")
    assert_true(metrics['bottom'] <= metrics['innerHeight'] + 1, 'popover extrapola viewport em landscape')
    page.screenshot(path=str(OUT / 'sport_picker_landscape_dark.png'), full_page=False)

    page.set_viewport_size({'width': 1024, 'height': 760})
    page.keyboard.press('Escape')
    page.wait_for_timeout(20)
    page.evaluate("document.documentElement.setAttribute('data-theme','dark')")
    page.locator('.visual-actions-panel').screenshot(path=str(OUT / 'buttons_representative_dark.png'))

    page.set_viewport_size({'width': 1440, 'height': 1000})
    page.evaluate("document.documentElement.setAttribute('data-theme','light')")
    no_overflow(page, 'library light desktop')
    light_border = card.evaluate('e => getComputedStyle(e).borderTopColor')
    light_probe = page.evaluate('''() => { const e=document.createElement('i'); e.style.cssText='position:absolute;border:1px solid var(--ui-border);visibility:hidden'; document.body.appendChild(e); const c=getComputedStyle(e).borderTopColor; e.remove(); return c }''')
    assert_true(light_border == light_probe, f'Library light não acompanha token: {light_border} != {light_probe}')
    grid_box = page.locator('.library-exercise-grid-modern').bounding_box()
    page.screenshot(path=str(OUT / 'library_exercises_light_desktop.png'), clip={'x':0,'y':0,'width':1440,'height':min(page.evaluate('document.documentElement.scrollHeight'), grid_box['y'] + grid_box['height'] + 30)})

    assert_true(not errors, 'Erros JS no fixture: ' + ' | '.join(errors))

    nav = browser.new_page(viewport={'width': 390, 'height': 844})
    nav.set_content('<!doctype html><html><body><a id="back" data-safe-back href="#fallback">← Back</a></body></html>')
    nav.add_script_tag(path=str(ROOT / 'public/assets/js/scripts.js'))
    nav.evaluate("history.pushState({},'', '#from'); history.pushState({},'', '#sub'); Object.defineProperty(document,'referrer',{configurable:true,value:'about:blank#from'}); window.StrideBRSafeBack.init(document)")
    assert_true(nav.locator('#back').is_visible(), 'subview não possui Back visível')
    nav.click('#back')
    nav.wait_for_timeout(60)
    assert_true(nav.evaluate('location.hash') == '#from', 'Back seguro não respeitou histórico interno')
    nav.close()

    browser.close()

print(f'✓ visual consistency browser: {assertions} assertions; screenshots em {OUT}')
