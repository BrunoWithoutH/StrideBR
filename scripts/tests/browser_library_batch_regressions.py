#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[2]
SCREEN_DIR = ROOT / 'docs' / 'reports' / 'screenshots' / 'FINAL_LIBRARY_BATCH_FIXES_2026-09-09'
SCREEN_DIR.mkdir(parents=True, exist_ok=True)
CSS_FILES = [
    ROOT / 'public/assets/css/style.css',
    ROOT / 'public/assets/css/ui-refresh.css',
    ROOT / 'public/assets/css/atividades.css',
    ROOT / 'public/assets/css/cronogramas.css',
    ROOT / 'public/assets/css/sport-hub.css',
]

LIBRARY_HTML = '''<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>
<main class="main-content library-page workout-library-page" data-library-page data-library-active-tab="treinos">
  <div class="workout-library-shell">
    <header class="library-page-heading">
      <div><span class="eyebrow">PLANEJAMENTO</span><h1>Biblioteca</h1></div>
      <div class="library-page-heading-actions">
        <div class="library-heading-actions" data-library-heading-actions="treinos"><button type="button" class="primary-button" data-new-workout-library>+ Novo treino</button></div>
        <div class="library-heading-actions" data-library-heading-actions="exercicios" hidden><button type="button" class="secondary-button" data-new-category>Nova categoria</button><button type="button" class="primary-button" data-new-exercise>+ Novo exercício</button></div>
      </div>
    </header>
    <nav class="workout-library-tabs" data-library-tabs><a class="is-active" href="/user/biblioteca.php?tab=treinos" data-library-tab="treinos">Treinos</a><a href="/user/biblioteca.php?tab=exercicios" data-library-tab="exercicios">Exercícios</a></nav>
    <div class="library-tab-stage" data-library-tab-stage>
      <section class="library-tab-view is-active" data-library-view="treinos"><section class="library-toolbar content-card"><div><strong>Treinos salvos</strong><span>6 na biblioteca</span></div><label class="library-search-field"><input type="search" placeholder="Buscar por nome, código ou foco"></label></section><section class="workout-library-grid"><article class="workout-library-card"><div class="workout-library-card-top"><div class="workout-library-code">A</div><div class="workout-library-card-title"><strong>Peito e tríceps</strong><span>Musculação</span></div></div><p class="workout-library-focus">Peito, tríceps, ombro e abdômen</p></article></section></section>
      <section class="library-tab-view" data-library-view="exercicios" hidden><section class="library-toolbar content-card"><div class="library-filter-group"><button class="view-button is-active">Todos</button><button class="view-button">StrideBR</button><button class="view-button">Meus exercícios</button></div><label class="library-search-field"><input type="search" placeholder="Buscar exercício"></label></section><section class="library-exercise-grid-modern"><article class="library-exercise-card"><div class="library-card-top"><span class="library-origin">StrideBR</span><h2>Supino reto</h2></div><p class="library-exercise-description">Exercício de empurrar para peitoral e tríceps.</p></article></section></section>
    </div>
  </div>
</main></body></html>'''

ACTIVITY_HTML = '''<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>
<main class="main-content"><div class="activity-history-workspace"><div class="activity-history-left">
<section class="activity-history" data-activity-history data-initial-state="ready" data-initial-cursor="" data-initial-total="4">
  <div class="activity-history-toolbar"><div><h2>Histórico</h2><span>Mais recentes primeiro</span></div><div class="activity-history-filters"><input type="search" placeholder="Buscar"><select><option>Todos os esportes</option></select><button type="button" class="activity-secondary-button activity-bulk-toggle" data-bulk-toggle>Selecionar</button></div></div>
  <form class="activity-bulk-bar" data-bulk-bar hidden>
    <div class="activity-bulk-count"><strong data-bulk-count>0 selecionadas</strong><button type="button" data-bulk-select-visible>Selecionar 4 carregadas</button></div>
    <div class="activity-bulk-fields">
      <div class="activity-bulk-sport-field"><span>Modalidade</span><select name="idmodalidade" data-bulk-sport><option value="">Não alterar</option><option value="run">Corrida</option></select></div>
      <label>Duração<span class="activity-bulk-duration" data-bulk-duration-control><select name="duracao_modo" data-bulk-duration-mode><option value="keep">Não alterar</option><option value="set">Definir</option><option value="clear">Limpar</option></select><span data-bulk-duration-value hidden><input type="number" name="duracao_minutos"><small>min</small></span></span></label>
      <label>Visibilidade<select name="visibilidade"><option value="">Não alterar</option><option value="publico">Público</option></select></label>
      <button type="submit" class="activity-primary-action" disabled>Aplicar</button>
    </div>
    <div class="activity-bulk-actions"><button type="button" class="activity-secondary-button" data-bulk-cancel>Cancelar seleção</button><button type="button" class="activity-secondary-button is-danger" data-bulk-delete disabled>Apagar</button></div>
  </form>
  <div class="activity-list" data-activity-list>
    <article class="activity-list-row" data-history-row data-activity-id="1"><label class="activity-row-select"><input type="checkbox" data-bulk-row-select><span></span></label><button class="activity-row-main"><div class="activity-row-date"><strong>08</strong><span>SET</span></div><div class="activity-row-title"><strong>Academia</strong><span>Musculação · 08:15</span></div></button></article>
    <article class="activity-list-row" data-history-row data-activity-id="2"><label class="activity-row-select"><input type="checkbox" data-bulk-row-select><span></span></label><button class="activity-row-main"><div class="activity-row-date"><strong>07</strong><span>SET</span></div><div class="activity-row-title"><strong>Corrida leve</strong><span>Corrida · 18:20</span></div></button></article>
    <article class="activity-list-row" data-history-row data-activity-id="3"><label class="activity-row-select"><input type="checkbox" data-bulk-row-select><span></span></label><button class="activity-row-main"><div class="activity-row-date"><strong>06</strong><span>SET</span></div><div class="activity-row-title"><strong>Pedal</strong><span>Ciclismo · 17:00</span></div></button></article>
    <article class="activity-list-row" data-history-row data-activity-id="4"><label class="activity-row-select"><input type="checkbox" data-bulk-row-select><span></span></label><button class="activity-row-main"><div class="activity-row-date"><strong>05</strong><span>SET</span></div><div class="activity-row-title"><strong>Academia B</strong><span>Musculação · 08:00</span></div></button></article>
  </div>
  <div data-history-empty hidden></div><div data-history-error hidden></div><div data-history-more hidden></div><div data-activity-history-status></div>
</section></div><aside class="activity-detail-placeholder"><div><strong>Detalhes da atividade</strong><span>Selecione uma atividade para abrir.</span></div></aside></div></main></body></html>'''


def add_css(page):
    for css in CSS_FILES:
        page.add_style_tag(path=str(css))
    page.add_style_tag(content='body{margin:0}.main-content{padding:20px!important;max-width:1440px;margin:0 auto}.workout-library-page{padding:20px!important}.activity-history-workspace{width:100%}')


def set_theme(page, theme):
    page.evaluate("theme => document.documentElement.setAttribute('data-theme', theme)", theme)


def screenshot(page, name):
    page.screenshot(path=str(SCREEN_DIR / name), full_page=True)


def boxes_overlap(a, b):
    if not a or not b:
        return False
    return not (a['x'] + a['width'] <= b['x'] or b['x'] + b['width'] <= a['x'] or a['y'] + a['height'] <= b['y'] or b['y'] + b['height'] <= a['y'])


def assert_no_action_overlap(page):
    buttons = [
        page.locator('[data-bulk-bar] button[type="submit"]'),
        page.locator('[data-bulk-cancel]'),
        page.locator('[data-bulk-delete]'),
    ]
    boxes = [button.bounding_box() for button in buttons]
    if any(box is None for box in boxes):
        raise AssertionError('batch action missing from layout')
    for i in range(len(boxes)):
        for j in range(i + 1, len(boxes)):
            if boxes_overlap(boxes[i], boxes[j]):
                raise AssertionError(f'batch actions overlap: {i} and {j}')
    bar_locator = page.locator('[data-bulk-bar]')
    bar = bar_locator.bounding_box()
    if not bar:
        raise AssertionError('batch toolbar missing')
    if not bar_locator.evaluate('e => e.scrollWidth <= e.clientWidth + 1'):
        raise AssertionError('batch toolbar has internal horizontal overflow')
    for box in boxes:
        if box['x'] < bar['x'] - 1 or box['x'] + box['width'] > bar['x'] + bar['width'] + 1 or box['y'] < bar['y'] - 1 or box['y'] + box['height'] > bar['y'] + bar['height'] + 1:
            raise AssertionError('batch action escapes toolbar bounds')


def prepare_library(page, viewport, theme):
    page.set_viewport_size({'width': viewport[0], 'height': viewport[1]})
    page.set_content(LIBRARY_HTML)
    add_css(page)
    set_theme(page, theme)
    page.evaluate("""() => {
        window.__libraryPushes = 0;
        window.__libraryReloadMarker = 'same-document';
        history.pushState = (state, title, url) => { window.__libraryPushes++; window.__libraryLastUrl = String(url); };
        window.StrideBRI18n = {t:(k,v,f)=>f||k};
        window.matchMedia = () => ({matches:false,addListener(){},removeListener(){},addEventListener(){},removeEventListener(){}});
    }""")
    page.add_script_tag(path=str(ROOT / 'public/assets/js/library.js'))
    page.wait_for_timeout(30)


def assert_library_state(page, tab):
    workout = page.locator('[data-library-heading-actions="treinos"]')
    exercises = page.locator('[data-library-heading-actions="exercicios"]')
    if tab == 'treinos':
        if not workout.is_visible() or exercises.is_visible():
            raise AssertionError('Treinos tab action visibility is wrong')
    else:
        if workout.is_visible() or not exercises.is_visible():
            raise AssertionError('Exercicios tab action visibility is wrong')
    visible_groups = page.locator('[data-library-heading-actions]:visible').count()
    if visible_groups != 1:
        raise AssertionError(f'expected exactly one visible library action group, got {visible_groups}')


def prepare_activity(page, viewport, theme):
    page.set_viewport_size({'width': viewport[0], 'height': viewport[1]})
    page.set_content(ACTIVITY_HTML)
    add_css(page)
    set_theme(page, theme)
    page.evaluate("""() => {
        const store = {};
        const storage = {getItem:k=>Object.prototype.hasOwnProperty.call(store,k)?store[k]:null,setItem:(k,v)=>store[k]=String(v),removeItem:k=>delete store[k],key:i=>Object.keys(store)[i]||null,get length(){return Object.keys(store).length}};
        Object.defineProperty(window,'localStorage',{configurable:true,value:storage});
        Object.defineProperty(window,'sessionStorage',{configurable:true,value:storage});
        window.StrideBRI18n = {t:(k,v={},f)=>{const map={'activity.bulk_selected.none':'0 selecionadas','activity.bulk_selected.one':'1 selecionada','activity.bulk_selected.other':`${v.count ?? 0} selecionadas`,'activity.bulk_select_loaded_count':`Selecionar ${v.count ?? 0} carregadas`,'activity.bulk_clear_loaded':'Limpar carregadas','activity.bulk_selecting':'Selecionando…','common.select':'Selecionar'};return map[k] ?? f ?? k},tn:(a,b,c)=>c===1?'1 selecionada':`${c} selecionadas`,number:(n)=>String(n),locale:'pt-BR'};
        window.StrideBRUI = {confirm:async()=>true,notify:()=>{}};
        window.StrideBRNet = {fetch:window.fetch.bind(window)};
        window.matchMedia = () => ({matches:false,addListener(){},removeListener(){},addEventListener(){},removeEventListener(){}});
    }""")
    errors = []
    page.on('pageerror', lambda exc: errors.append(str(exc)))
    page.add_script_tag(path=str(ROOT / 'public/assets/js/atividades.js'))
    page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
    page.wait_for_timeout(50)
    if errors:
        raise AssertionError('activity JS errors: ' + ' | '.join(errors))
    page.locator('[data-bulk-toggle]').click()
    page.wait_for_timeout(20)


def main():
    checks = 0
    with sync_playwright() as p:
        browser = p.chromium.launch(executable_path='/usr/bin/chromium', headless=True, args=['--no-sandbox'])
        page = browser.new_page()

        prepare_library(page, (1440, 900), 'dark')
        assert_library_state(page, 'treinos'); checks += 2
        screenshot(page, 'A-library-workouts-desktop.png')
        marker = page.evaluate('window.__libraryReloadMarker')
        page.locator('[data-library-tab="exercicios"]').click()
        page.wait_for_timeout(30)
        assert_library_state(page, 'exercicios'); checks += 2
        if page.evaluate('window.__libraryReloadMarker') != marker or page.evaluate('window.__libraryPushes') != 1:
            raise AssertionError('library tab switch reloaded instead of updating same document')
        checks += 1
        screenshot(page, 'B-library-exercises-desktop.png')

        prepare_library(page, (390, 844), 'light')
        assert_library_state(page, 'treinos'); checks += 2
        screenshot(page, 'C-library-workouts-mobile.png')
        page.locator('[data-library-tab="exercicios"]').click(); page.wait_for_timeout(30)
        assert_library_state(page, 'exercicios'); checks += 2
        screenshot(page, 'D-library-exercises-mobile.png')

        prepare_activity(page, (1440, 900), 'dark')
        if not page.locator('[data-bulk-bar] button[type="submit"]').is_disabled() or not page.locator('[data-bulk-delete]').is_disabled():
            raise AssertionError('zero-selection batch actions should be disabled')
        assert_no_action_overlap(page); checks += 4
        screenshot(page, 'E-activities-selection-zero.png')
        page.locator('[data-bulk-select-visible]').click(); page.wait_for_timeout(20)
        if page.locator('[data-bulk-row-select]:checked').count() != 4:
            raise AssertionError('select loaded did not select four loaded rows')
        if not page.locator('[data-bulk-bar] button[type="submit"]').is_disabled() or page.locator('[data-bulk-delete]').is_disabled():
            raise AssertionError('four-selected batch disabled state is wrong')
        checks += 2
        assert_no_action_overlap(page); checks += 1
        screenshot(page, 'F-activities-selection-four.png')

        for width, height, name in [(1024, 900, 'G-batch-1024.png'), (768, 900, 'H-batch-768.png'), (390, 844, 'I-batch-390.png')]:
            prepare_activity(page, (width, height), 'dark' if width != 390 else 'light')
            page.locator('[data-bulk-select-visible]').click(); page.wait_for_timeout(20)
            assert_no_action_overlap(page); checks += 1
            if page.locator('[data-bulk-cancel]').is_hidden() or page.locator('[data-bulk-delete]').is_hidden() or page.locator('[data-bulk-bar] button[type="submit"]').is_hidden():
                raise AssertionError(f'batch action hidden at {width}px')
            checks += 1
            scroll_ok = page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth')
            if width <= 390 and not scroll_ok:
                raise AssertionError('mobile body has horizontal overflow')
            checks += 1
            screenshot(page, name)

        for width, height in [(1280, 900), (900, 900), (375, 812), (360, 800)]:
            prepare_activity(page, (width, height), 'dark' if width > 390 else 'light')
            page.locator('[data-bulk-select-visible]').click(); page.wait_for_timeout(20)
            assert_no_action_overlap(page); checks += 1
            if width <= 390 and not page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth'):
                raise AssertionError(f'mobile body has horizontal overflow at {width}px')
            checks += 1

        for width, height in [(375, 812), (360, 800)]:
            prepare_library(page, (width, height), 'light')
            assert_library_state(page, 'treinos'); checks += 1
            page.locator('[data-library-tab="exercicios"]').click(); page.wait_for_timeout(20)
            assert_library_state(page, 'exercicios'); checks += 1
            if not page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth'):
                raise AssertionError(f'library mobile overflow at {width}px')
            checks += 1

        prepare_activity(page, (390, 844), 'light')
        page.locator('[data-bulk-select-visible]').click(); page.wait_for_timeout(20)
        page.select_option('[data-bulk-bar] select[name="visibilidade"]', 'publico'); page.wait_for_timeout(10)
        if page.locator('[data-bulk-bar] button[type="submit"]').is_disabled():
            raise AssertionError('Apply did not enable after a real batch change')
        checks += 1
        page.locator('[data-bulk-cancel]').click(); page.wait_for_timeout(10)
        if not page.locator('[data-bulk-bar]').is_hidden() or page.locator('[data-bulk-row-select]:checked').count() != 0:
            raise AssertionError('Cancel selection did not leave selection mode cleanly')
        checks += 1

        browser.close()
    print(f'PASS library/batch regressions: {checks} checks, {len(list(SCREEN_DIR.glob("*.png")))} screenshots')

if __name__ == '__main__':
    main()
