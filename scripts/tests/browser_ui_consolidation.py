#!/usr/bin/env python3
from pathlib import Path
import json
import math
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
    ROOT / 'public/assets/css/events.css',
    ROOT / 'public/assets/css/dashboard.css',
    ROOT / 'public/assets/css/sport-hub.css',
    ROOT / 'public/assets/css/gps-recorder.css',
    ROOT / 'public/assets/css/cronogramas.css',
    ROOT / 'public/assets/css/ui-refresh.css',
    ROOT / 'public/assets/css/activity-sharing.css',
]
JS_FILE = ROOT / 'public/assets/js/atividades.js'
SCREEN_DIR = ROOT / 'docs/ui-regression'
SCREEN_DIR.mkdir(parents=True, exist_ok=True)

share_data = {
    "titulo": "Corrida noturna",
    "modalidade": "Corrida",
    "data": "03/09/2026",
    "ganho_m": 84,
    "metricas": [
        {"rotulo": "Distância", "valor": "10,24 km"},
        {"rotulo": "Duração", "valor": "48:31"},
        {"rotulo": "Ritmo", "valor": "4:44/km"},
        {"rotulo": "Elevação", "valor": "84 m"},
    ],
    "geojson": {"coordinates": [[-53.40, -27.36], [-53.39, -27.355], [-53.38, -27.36], [-53.37, -27.35]]},
    "usa_trechos": False,
    "trechos": [],
}

HTML = f'''<!doctype html><html data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"></head><body>
<header class="site-header"><div class="header-inner"><a class="brand-link">StrideBR</a><div></div><div class="usersection"><details class="user-menu"><summary><span class="userimage"></span></summary><div class="user-menu-content"><a href="#">Ver meu perfil</a><a href="#">Configurações</a></div></details></div></div></header>
<main class="main-content activities-page" data-activities-page>
<section id="activities-fixture" style="padding:16px;display:grid;gap:16px">
  <header class="activities-toolbar"><div class="activities-toolbar-title"><span>Atividades</span><h1>Histórico</h1></div><div class="activities-toolbar-actions"><a class="activity-toolbar-link" href="#">Comparar</a><button class="activity-secondary-button">Importar</button><button class="activity-primary-action">Registrar atividade</button></div></header>
  <section class="activity-history"><div class="activity-history-toolbar"><div><strong>Histórico</strong><span>12 atividades</span></div></div><div class="activity-history-filters"><input type="search" placeholder="Buscar"><div class="sport-select-picker"><button type="button" class="sport-select-trigger">Todos os esportes</button></div><select><option>Mais recentes</option></select><button class="activity-secondary-button">Selecionar</button></div><div data-activity-history data-initial-state="ready" data-initial-total="0"><div data-history-list></div><span data-history-count></span><button data-history-more hidden></button></div></section>
  <section class="activity-editor"><div class="activity-editor-heading"><h2>Registrar atividade</h2><p>Campos essenciais</p></div><div class="activity-context-grid"><label class="input-field"><span>Esporte</span><input value="Corrida"></label><label class="input-field"><span>Data</span><input type="date" value="2026-09-03"></label></div></section>
  <div class="sport-favorites-quick">Favoritos</div><div class="activity-repeat-banner">Repetir atividade</div><div class="activity-unit-route-editor">Editor de rota</div>
  <form class="events-filter content-card"><label>Busca<input type="search" value="Corrida"></label><div class="event-sport-filter"><span class="form-field-label">Esporte</span><div class="sport-select-picker"><button type="button" class="sport-select-trigger">Corrida</button></div></div><label>Estado<select><option>RS</option></select></label><label>Mês<select><option>Setembro</option></select></label><button type="button" class="primary-button">Filtrar</button><a class="secondary-button events-clear">Limpar</a></form>
  <div class="settings-heading-row"><div class="page-heading settings-workspace-heading"><span class="settings-workspace-kicker">Configurações</span><h1>Preferências</h1><p>Ajuste sua experiência.</p></div></div>
  <nav class="settings-tabs" aria-label="Configurações"><a href="#">Perfil</a><a href="#" class="is-active" aria-current="page">Preferências</a><a href="#">Conta e segurança</a></nav>
  <article class="static-page-card"><div class="static-content"><h1>Termos de Uso</h1><p class="static-lead">Informações legais do StrideBR.</p><p>Texto de exemplo para conferir o tema.</p><code>StrideBR</code></div></article>
  <div class="floating-utility-dock"><button class="pinned-tool-chip"><span>Timer</span><strong>01:00</strong></button><button class="quick-tools-launcher">+</button></div>
  <section class="profile-stat-grid"><article><strong>3</strong><span>atividades</span></article><article><strong>5,1 km</strong><span>distância</span></article></section>
  <div class="faq-list"><details open><summary>O StrideBR é só para corrida?</summary><p>Não. O sistema usa modalidades diferentes.</p></details><details><summary>Como funcionam as rotas?</summary></details></div>
  <nav class="sport-hub-tabs"><a class="is-active" href="#">Visão geral</a><a href="#">Cardio</a></nav>
  <section class="gps-goal-value"><input type="number" value="5"><select><option>km</option></select></section>
  <section class="gps-live-metrics"><article class="gps-primary-metric"><span>Tempo</span><strong>00:00:17</strong></article></section>
  <section class="schedule-toolbar"><button class="secondary-button">Semana</button><button class="secondary-button">Mês</button></section>
  <aside class="schedule-side-panel"><div class="schedule-mini-month"><div class="schedule-mini-month-heading"><strong>Setembro de 2026</strong></div></div><div class="schedule-side-list"><div class="schedule-side-list-heading"><strong>Meus cronogramas</strong></div></div></aside>
  <aside class="activity-detail-drawer" data-activity-detail-drawer><button type="button" data-share-activity>Compartilhar</button><script type="application/json" data-activity-share-data>{json.dumps(share_data, ensure_ascii=False)}</script></aside>
</section>
</main>
<div class="activity-share-modal" data-share-modal hidden>
  <button type="button" class="activity-share-backdrop" data-close-share aria-label="Fechar compartilhamento"></button>
  <div class="activity-share-workspace" role="dialog" aria-modal="true" aria-labelledby="share-title">
    <aside class="activity-share-preview-window activity-share-preview-shell" data-share-preview-shell aria-label="Prévia do cartão">
      <div class="activity-share-preview-heading"><strong>Prévia</strong><small data-share-preview-format>Story · 1080 × 1920</small></div>
      <div class="activity-share-preview-layout">
        <div class="activity-share-preview-stage"><canvas data-share-canvas width="1080" height="1920"></canvas></div>
        <fieldset class="activity-share-format-options activity-share-format-rail"><legend>Formato</legend>
          <label><input type="radio" name="share-format" value="story" data-share-format checked><span><i class="share-format-thumb is-story"></i><b><strong>Story</strong><small>9:16</small></b></span></label>
          <label><input type="radio" name="share-format" value="portrait" data-share-format><span><i class="share-format-thumb is-portrait"></i><b><strong>Retrato</strong><small>4:5</small></b></span></label>
          <label><input type="radio" name="share-format" value="square" data-share-format><span><i class="share-format-thumb is-square"></i><b><strong>Quadrado</strong><small>1:1</small></b></span></label>
        </fieldset>
      </div>
    </aside>
    <section class="activity-share-panel"><header><div><span>Compartilhar</span><h2 id="share-title">Compartilhar atividade</h2></div><button type="button" data-close-share>×</button></header>
      <div class="activity-share-body"><div class="activity-share-controls">
        <section class="activity-share-choice-block" data-share-single-only><div class="activity-share-block-heading"><div><strong>Conteúdo</strong></div></div><div class="activity-share-choice-grid is-content" data-share-content-grid></div></section>
        <section class="activity-share-choice-block" data-share-background-block><div class="activity-share-block-heading"><div><strong>Fundo</strong></div></div><div class="activity-share-choice-grid is-background activity-share-style-grid" data-share-style-grid></div></section>
        <section class="activity-share-choice-block" data-share-session-only hidden><div class="activity-share-choice-grid is-session" data-share-session-layout-grid></div></section>
        <section class="activity-share-content-options" data-share-content-options hidden><fieldset class="activity-share-mode-options"><label><input type="radio" name="share-content-mode" value="activity" data-share-content-mode checked><span>Atividade</span></label><label><input type="radio" name="share-content-mode" value="segments" data-share-content-mode><span>Trechos</span></label></fieldset><fieldset class="activity-share-segment-mode-options" data-share-segment-mode-options hidden><legend>Trechos</legend><label><input type="radio" name="share-segment-mode" value="together" data-share-segment-mode checked><span>Todos juntos</span></label><label><input type="radio" name="share-segment-mode" value="separate" data-share-segment-mode><span>Separados</span></label></fieldset></section>
        <section class="activity-share-quick-controls"><input type="checkbox" data-share-show="route" checked hidden><fieldset class="activity-share-color-options" data-share-color-options><legend>Cor do fundo</legend><label><input type="radio" name="share-color" value="deep" data-share-background-color checked><span><i class="share-color-swatch is-deep"></i><b>Azul profundo</b></span></label><label><input type="radio" name="share-color" value="dark" data-share-background-color><span><i class="share-color-swatch is-dark"></i><b>Azul escuro</b></span></label><label><input type="radio" name="share-color" value="light" data-share-background-color><span><i class="share-color-swatch is-light"></i><b>Claro</b></span></label><label><input type="radio" name="share-color" value="black" data-share-background-color><span><i class="share-color-swatch is-black"></i><b>Preto</b></span></label></fieldset></section>
        <button type="button" class="activity-share-mobile-photo" data-share-mobile-photo hidden>Escolher foto</button><button type="button" class="activity-share-mobile-customize" data-share-mobile-customize><span>Personalizar</span><span>›</span></button><button type="button" class="activity-share-customize-backdrop" data-share-mobile-customize-close></button>
        <aside class="activity-share-sidebar" data-share-customize-sheet><div class="activity-share-mobile-sheet-head"><button type="button" data-share-mobile-customize-close>‹</button><strong>Personalizar cartão</strong><span></span></div><section class="activity-share-group"><h3>Aparência</h3><div class="activity-share-route-scale"><div><strong>Tamanho da rota</strong></div><label><input type="range" min="60" max="200" value="100" data-share-route-scale><output data-share-route-scale-value>100%</output></label></div></section><section class="activity-share-group"><h3>Elementos do cartão</h3><div class="activity-share-visibility"><label class="activity-share-heading-mode"><span>Texto no topo</span><select data-share-heading-mode><option value="title">Título</option><option value="sport">Modalidade</option><option value="none">Ocultar</option></select></label><label><span>Data</span><input type="checkbox" data-share-show="date"></label><label><span>Logo</span><input type="checkbox" data-share-show="logo" checked></label></div><details class="activity-share-details" open><summary>Estatísticas <small>até 4</small></summary><div class="activity-share-metrics" data-share-metric-options></div></details><details class="activity-share-details"><summary>Mais opções</summary><label class="activity-share-caption">Legenda curta<input data-share-caption></label></details></section><section class="activity-share-group" data-share-segments-group hidden><h3>Trechos</h3><div data-share-segment-list></div><label data-share-segment-preview-picker hidden><select data-share-segment-preview></select></label></section><section class="activity-share-group activity-share-photo-group" data-share-photo-field hidden><h3>Foto</h3><div class="activity-share-photo-actions"><div class="activity-share-photo-preview" data-share-photo-preview><span data-share-photo-empty>Sem foto</span></div><div class="activity-share-photo-buttons"><button type="button" data-share-open-camera>Tirar foto</button><label>Escolher arquivo<input type="file" data-share-photo></label><small data-share-photo-name></small></div></div></section></aside>
      </div></div><p data-share-status></p><footer><details class="activity-share-more-menu"><summary>•••</summary><div><button type="button" data-export-route-png hidden>Exportar rota</button><button type="button" data-reset-share>Redefinir</button></div></details><button type="button" class="activity-secondary-button" data-copy-share>Copiar card</button><button type="button" class="activity-secondary-button" data-download-share>Baixar</button><button type="button" class="activity-primary-action" data-native-share>Compartilhar</button></footer>
      <div class="activity-share-camera-sheet" data-share-camera-sheet hidden><div class="activity-share-camera-backdrop" data-share-close-camera></div><div class="activity-share-camera-dialog"><header><strong>Tirar foto</strong><button data-share-close-camera>×</button></header><video data-share-camera-video></video><p data-share-camera-status></p><footer><button data-share-close-camera>Cancelar</button><button data-share-capture-camera>Usar foto</button></footer></div></div>
    </section>
  </div>
</div>
</body></html>'''

VIEWPORTS = [(1440,900), (1366,592), (1024,768), (390,844), (360,640), (844,390)]

def luminanceish(rgb):
    vals = [int(x) for x in rgb.replace('rgba(', '').replace('rgb(', '').split(')')[0].split(',')[:3]]
    return sum(vals) / 3

def assert_close(a, b, tolerance, message):
    if abs(a-b) > tolerance:
        raise AssertionError(f"{message}: {a} vs {b}")

def rects(page, selector):
    return page.eval_on_selector_all(selector, "els => els.map(e => { const r=e.getBoundingClientRect(); return ({x:r.x,y:r.y,width:r.width,height:r.height,cx:r.x+r.width/2,cy:r.y+r.height/2}) })")

def check_same_line(page, selector, expected, label):
    rs = [r for r in rects(page, selector) if r['height'] > 1 and r['width'] > 1]
    if len(rs) < 2:
        raise AssertionError(f"{label}: poucos controles ({len(rs)})")
    rows=[]
    for r in sorted(rs, key=lambda x: (x['cy'], x['x'])):
        row=next((row for row in rows if abs(row[0]['cy']-r['cy']) <= 3), None)
        if row is None:
            row=[]; rows.append(row)
        row.append(r)
    for row in rows:
        heights=[r['height'] for r in row]
        centers=[r['cy'] for r in row]
        if len(row) > 1 and max(heights)-min(heights) > 1.0:
            raise AssertionError(f"{label}: alturas {heights}")
        if len(row) > 1 and max(centers)-min(centers) > 1.0:
            raise AssertionError(f"{label}: centros {centers}")
        for h in heights:
            assert_close(h, expected, 1.0, f"{label} altura")

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    failures=[]
    for width,height in VIEWPORTS:
        for theme in ('light','dark'):
            page=browser.new_page(viewport={"width":width,"height":height})
            console_errors=[]
            page.on('pageerror', lambda exc: console_errors.append(str(exc)))
            page.set_content(HTML, wait_until='domcontentloaded')
            for css in CSS_FILES:
                page.add_style_tag(path=str(css))
            page.evaluate("theme => document.documentElement.dataset.theme = theme", theme)
            page.add_script_tag(path=str(JS_FILE))
            page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
            page.wait_for_timeout(120)
            try:
                desktop = width > 720
                if desktop:
                    check_same_line(page, '.activities-toolbar-actions > :is(a,button)', 36, f'Atividades toolbar {width}x{height}')
                    check_same_line(page, '.activity-history-filters > :is(input,select,.sport-select-picker,.activity-secondary-button), .activity-history-filters .sport-select-trigger', 36, f'Histórico filtros {width}x{height}')
                    check_same_line(page, '.events-filter :is(input,select,.sport-select-trigger,.primary-button,.secondary-button,.events-clear)', 40, f'Eventos filtros {width}x{height}')
                else:
                    for sel,label in [('.activities-toolbar-actions > :is(a,button)','Atividades mobile'),('.activity-history-filters > :is(input,select,.activity-secondary-button), .activity-history-filters .sport-select-trigger','Histórico mobile'),('.events-filter :is(input,select,.sport-select-trigger,.primary-button,.secondary-button,.events-clear)','Eventos mobile')]:
                        hs=[r['height'] for r in rects(page,sel) if r['height'] > 1 and r['width'] > 1]
                        if hs and min(hs) < 43:
                            raise AssertionError(f'{label}: alvo touch abaixo de 44px: {hs}')
                if page.evaluate('document.documentElement.scrollWidth') > width + 1:
                    raise AssertionError(f'Overflow horizontal global em {width}px: {page.evaluate("document.documentElement.scrollWidth")}px')
                page.eval_on_selector('.user-menu', 'e => e.open = true')
                page.wait_for_timeout(20)
                menu_box = page.locator('.user-menu-content').bounding_box()
                if menu_box:
                    hit = page.evaluate('([x,y]) => { const e = document.elementFromPoint(x,y); return e ? !!e.closest(".user-menu-content") : false }', [menu_box['x'] + min(12, menu_box['width']/2), menu_box['y'] + min(12, menu_box['height']/2)])
                    if not hit:
                        raise AssertionError('Menu do usuário ficou sob o drawer de atividade')
                page.eval_on_selector('.user-menu', 'e => e.open = false')
                if theme=='dark':
                    for sel in ('.activity-history-toolbar','.activity-context-grid','.activity-editor-heading','.pinned-tool-chip','.static-page-card','.sport-favorites-quick','.activity-repeat-banner','.activity-unit-route-editor','.profile-stat-grid article','.faq-list details','.gps-goal-value input','.gps-primary-metric','.schedule-toolbar','.schedule-mini-month','.schedule-side-list'):
                        bg=page.eval_on_selector(sel, "e => getComputedStyle(e).backgroundColor")
                        if luminanceish(bg) > 110:
                            raise AssertionError(f'{sel} claro demais no dark: {bg}')
                tab = page.eval_on_selector('.settings-tabs [aria-current="page"]', "e => ({bg:getComputedStyle(e).backgroundColor,border:getComputedStyle(e).borderBottomColor,shadow:getComputedStyle(e).boxShadow})")
                if tab['shadow']=='none':
                    raise AssertionError('Aba ativa de Configurações sem marcador estrutural')
                progress_tab = page.eval_on_selector('.sport-hub-tabs .is-active', 'e => ({bg:getComputedStyle(e).backgroundColor,color:getComputedStyle(e).color,shadow:getComputedStyle(e).boxShadow})')
                if progress_tab['shadow'] == 'none' or progress_tab['bg'] in ('rgba(0, 0, 0, 0)', 'transparent'):
                    raise AssertionError('Aba ativa de Progresso sem estado estrutural visível')
                timer_label = page.locator('.gps-primary-metric span').bounding_box()
                timer_value = page.locator('.gps-primary-metric strong').bounding_box()
                if timer_label and timer_value and timer_label['y'] + timer_label['height'] > timer_value['y'] + .5:
                    raise AssertionError(f'Label Tempo sobrepõe cronômetro: label={timer_label} value={timer_value}')
                if (width,height)==(1440,900) and theme=='dark':
                    page.screenshot(path=str(SCREEN_DIR/'core-dark-1440x900.png'))
                if (width,height)==(1024,768) and theme=='light':
                    page.screenshot(path=str(SCREEN_DIR/'core-light-1024x768.png'))
                if (width,height)==(360,640) and theme=='dark':
                    page.screenshot(path=str(SCREEN_DIR/'core-dark-360x640.png'))

                page.click('[data-share-activity]')
                page.wait_for_timeout(250)
                stage=page.locator('.activity-share-preview-stage').bounding_box()
                canvas=page.locator('canvas[data-share-canvas]').bounding_box()
                if not stage or not canvas:
                    raise AssertionError('Preview de compartilhamento sem geometria')
                tol=1.5
                if canvas['x'] < stage['x']-tol or canvas['y'] < stage['y']-tol or canvas['x']+canvas['width'] > stage['x']+stage['width']+tol or canvas['y']+canvas['height'] > stage['y']+stage['height']+tol:
                    raise AssertionError(f'Canvas fora do stage: stage={stage} canvas={canvas}')
                ratio=canvas['width']/canvas['height']
                assert_close(ratio,1080/1920,.015,'Aspect ratio Story')
                stage_cx=stage['x']+stage['width']/2
                stage_cy=stage['y']+stage['height']/2
                canvas_cx=canvas['x']+canvas['width']/2
                canvas_cy=canvas['y']+canvas['height']/2
                assert_close(stage_cx,canvas_cx,2.0,'Canvas centralizado horizontalmente')
                assert_close(stage_cy,canvas_cy,2.0,'Canvas centralizado verticalmente')
                attr=page.eval_on_selector('canvas[data-share-canvas]', "c => [c.width,c.height]")
                if attr != [1080,1920]:
                    raise AssertionError(f'Resolução exportável alterada pela preview: {attr}')
                if (width,height)==(1366,592) and canvas['height'] < 260:
                    raise AssertionError(f'Story microscópico em 1366x592: {canvas["height"]}px')
                format_rects=rects(page,'.activity-share-format-rail label')
                if len(format_rects)!=3:
                    raise AssertionError('Seletor de formatos incompleto')
                if desktop and max(r['cy'] for r in format_rects)-min(r['cy'] for r in format_rects)>1.5:
                    raise AssertionError('Formatos não estão numa barra horizontal')
                content_heights=page.eval_on_selector_all('.activity-share-choice-grid.is-content canvas', "els => els.map(e => e.getBoundingClientRect().height)")
                if content_heights and min(content_heights) < 70:
                    raise AssertionError(f'Miniaturas de conteúdo comprimidas: {content_heights}')
                font_sizes=page.eval_on_selector_all('.activity-share-choice-grid strong,.activity-share-choice-grid small,.activity-share-format-rail strong,.activity-share-format-rail small', "els => els.map(e => parseFloat(getComputedStyle(e).fontSize))")
                if font_sizes and min(font_sizes)<10:
                    raise AssertionError(f'Texto microscópico no compartilhamento: {min(font_sizes)}px')
                if theme=='dark':
                    bg=page.eval_on_selector('.activity-share-panel', "e => getComputedStyle(e).backgroundColor")
                    if luminanceish(bg)>110:
                        raise AssertionError(f'Chrome do share claro no dark: {bg}')
                if not desktop:
                    ws=page.locator('.activity-share-workspace').bounding_box()
                    if ws and (abs(ws['width']-width)>2 or abs(ws['height']-height)>2):
                        raise AssertionError(f'Share mobile não fullscreen: {ws}')
                    footer_buttons = page.eval_on_selector_all('.activity-share-panel > footer > button', 'els => els.filter(e => getComputedStyle(e).display !== "none").map(e => ({name:e.getAttribute("data-copy-share")!==null?"copy":e.getAttribute("data-download-share")!==null?"download":e.getAttribute("data-native-share")!==null?"share":"other",w:e.getBoundingClientRect().width,h:e.getBoundingClientRect().height}))')
                    for item in footer_buttons:
                        if item['name'] in ('copy','download','share') and item['h'] < 43:
                            raise AssertionError(f'Botão do footer do share abaixo de 44px: {item}')
                        if item['name'] in ('copy','download') and item['w'] < 80:
                            raise AssertionError(f'Botão secundário do share estreito demais: {item}')
                shot=None
                if (width,height)==(1366,592) and theme=='dark': shot='share-dark-1366x592.png'
                elif (width,height)==(390,844) and theme=='dark': shot='share-dark-390x844.png'
                elif (width,height)==(844,390) and theme=='dark': shot='share-dark-844x390.png'
                if shot: page.screenshot(path=str(SCREEN_DIR/shot))
                if console_errors:
                    filtered=[e for e in console_errors if 'Failed to load resource' not in e]
                    if filtered:
                        raise AssertionError('Erros JS: '+ ' | '.join(filtered[:3]))
            except Exception as exc:
                failures.append(f'{theme} {width}x{height}: {exc}')
            finally:
                page.close()
    browser.close()

if failures:
    print('Falhas browser UI consolidation:', file=sys.stderr)
    for item in failures:
        print('- '+item, file=sys.stderr)
    sys.exit(1)
print(f'✓ browser UI consolidation: {len(VIEWPORTS)*2} combinações, light/dark, geometria e screenshots')
