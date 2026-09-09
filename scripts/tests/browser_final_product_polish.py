#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[2]
SCREEN_DIR = ROOT / 'docs' / 'reports' / 'screenshots' / 'FINAL_PRODUCT_POLISH_2026-09-09'
SCREEN_DIR.mkdir(parents=True, exist_ok=True)
CSS_FILES = [
    ROOT / 'public/assets/css/style.css',
    ROOT / 'public/assets/css/ui-refresh.css',
    ROOT / 'public/assets/css/atividades.css',
    ROOT / 'public/assets/css/cronogramas.css',
    ROOT / 'public/assets/css/sport-hub.css',
    ROOT / 'public/assets/css/loginsignup.css',
]

BASE_HEAD = '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">'
FIXTURE_STYLE = '''
body{margin:0}.fixture-shell{width:min(1220px,calc(100% - 24px));margin:0 auto;padding:18px 0 36px}.fixture-section{margin-bottom:28px}.fixture-section>h1{margin:0 0 12px;font-size:1.25rem}.fixture-grid{display:grid;gap:12px}.fixture-bars{display:flex;align-items:end;gap:8px;height:180px}.fixture-bars span{flex:1;min-width:24px;background:var(--ui-accent);border-radius:var(--radius-detail) var(--radius-detail) 0 0}.fixture-bars span:nth-child(1){height:38%}.fixture-bars span:nth-child(2){height:72%}.fixture-bars span:nth-child(3){height:55%}.fixture-bars span:nth-child(4){height:88%}.fixture-bars span:nth-child(5){height:64%}
'''

ACTIVITY_HTML = '''
<section class="activity-history" data-activity-history>
  <div class="activity-history-toolbar"><div><h2>Histórico</h2><span>Mais recentes primeiro</span></div><div class="activity-history-filters"><input type="search" placeholder="Buscar"><select><option>Todos os esportes</option></select><button class="activity-secondary-button">Selecionar</button></div></div>
  <form class="activity-bulk-bar" data-bulk-bar>
    <div class="activity-bulk-count"><strong data-bulk-count>4 selecionadas</strong><button type="button">Selecionar 20 carregadas</button></div>
    <div class="activity-bulk-fields"><div class="activity-bulk-sport-field"><span>Modalidade</span><select name="idmodalidade"><option>Não alterar</option></select></div><label>Duração<span class="activity-bulk-duration"><select><option>Não alterar</option></select></span></label><label>Visibilidade<select><option>Não alterar</option></select></label><button type="submit" class="activity-primary-action" disabled>Aplicar</button></div>
    <div class="activity-bulk-actions"><button type="button" class="activity-secondary-button">Cancelar seleção</button><button type="button" class="activity-secondary-button is-danger">Apagar</button></div>
  </form>
  <div class="activity-list">
    <article class="activity-list-row is-bulk-mode is-selected" data-history-row><label class="activity-row-select"><input type="checkbox" checked><span></span></label><button class="activity-row-main"><div class="activity-row-date"><strong>08</strong><span>SET</span></div><div class="activity-row-icon">🏋</div><div class="activity-row-title"><strong>Academia</strong><span>Musculação · 08:15</span></div><div class="activity-row-strength-preview"><div class="activity-row-strength-facts"><span class="activity-row-strength-code">A</span><span>54 min · 4 exercícios · 16 séries</span></div><span class="activity-row-strength-focus" title="Peito, tríceps, ombro e abdômen">Peito, tríceps, ombro e abdômen</span></div><span class="activity-row-open">›</span></button></article>
    <article class="activity-list-row" data-history-row><button class="activity-row-main"><div class="activity-row-date"><strong>07</strong><span>SET</span></div><div class="activity-row-icon">🏃</div><div class="activity-row-title"><strong>Corrida leve</strong><span>Corrida · 18:20</span></div><div class="activity-row-metrics"><span><small>Distância</small><strong>5,23 km</strong></span><span><small>Duração</small><strong>31:10</strong></span><span><small>Ritmo</small><strong>5:58/km</strong></span></div><span class="activity-row-open">›</span></button></article>
  </div>
</section>'''

LIBRARY_WORKOUTS = '''
<main class="main-content library-page workout-library-page"><div class="workout-library-shell"><header class="library-page-heading"><div><span class="eyebrow">PLANEJAMENTO</span><h1>Biblioteca</h1></div><div class="library-heading-actions" data-library-heading-actions="treinos"><button class="primary-button">+ Novo treino</button></div></header><nav class="workout-library-tabs"><a class="is-active">Treinos</a><a>Exercícios</a></nav><section class="library-toolbar content-card"><div><strong>Treinos salvos</strong><span>6 na biblioteca</span></div><label class="library-search-field"><input type="search" placeholder="Buscar por nome, código ou foco"></label></section><section class="workout-library-grid"><article class="workout-library-card"><div class="workout-library-card-top"><div class="workout-library-code">A</div><div class="workout-library-card-title"><strong>Peito e tríceps</strong><span>Musculação</span></div></div><p class="workout-library-focus">Peito, tríceps, ombro e abdômen</p><div class="workout-library-stats"><span><strong>4</strong> exercícios</span><span><strong>2</strong> cronogramas</span></div></article></section></div></main>'''

LIBRARY_EXERCISES = '''
<main class="main-content library-page workout-library-page"><div class="workout-library-shell"><header class="library-page-heading"><div><span class="eyebrow">PLANEJAMENTO</span><h1>Biblioteca</h1></div><div class="library-heading-actions" data-library-heading-actions="exercicios"><button class="secondary-button">Nova categoria</button><button class="primary-button">+ Novo exercício</button></div></header><nav class="workout-library-tabs"><a>Treinos</a><a class="is-active">Exercícios</a></nav><section class="library-toolbar content-card"><div class="library-filter-group"><button class="view-button is-active">Todos</button><button class="view-button">StrideBR</button><button class="view-button">Meus exercícios</button><span class="library-result-count"><strong>24</strong> visíveis</span></div><label class="library-search-field"><input type="search" placeholder="Buscar exercício"></label></section><section class="exercise-library-grid library-exercise-grid-modern"><article class="library-exercise-card"><div class="library-exercise-main"><div class="library-card-top"><span class="library-origin">StrideBR</span><h2>Supino reto</h2></div><p class="library-exercise-description">Exercício de empurrar para peitoral e tríceps.</p></div><div class="library-exercise-tags"><span><b>Categorias</b> Peito</span><span><b>Modalidades</b> Musculação</span></div></article></section></div></main>'''

TRAINER_OFF = '''
<main class="main-content"><div class="page-shell trainer-shell"><div class="page-heading trainer-heading"><div><span class="eyebrow">TREINO CONECTADO</span><h1>Treinador e atletas</h1></div></div><nav class="planning-subnav monthly-planning-subnav"><a>Cronogramas</a><a>Agenda mensal</a><a class="is-active">Treinador e atletas</a></nav><section class="trainer-section"><div class="section-title-row"><div><h2>Como atleta</h2></div></div><div class="content-card trainer-empty rich"><strong>Você ainda não tem treinador.</strong><button class="primary-button">Encontrar treinador</button></div></section><section class="trainer-section" data-trainer-coach-section><div class="section-title-row"><div><h2>Modo treinador</h2></div></div><div class="content-card trainer-enable-card"><p>Acompanhe atletas e prescreva treinos.</p><button class="primary-button">Ativar modo treinador</button></div></section></div></main>'''

TRAINER_ACTIVE = '''
<main class="main-content"><div class="page-shell trainer-shell"><div class="page-heading trainer-heading"><div><span class="eyebrow">TREINO CONECTADO</span><h1>Treinador e atletas</h1></div></div><nav class="planning-subnav monthly-planning-subnav"><a>Cronogramas</a><a>Agenda mensal</a><a class="is-active">Treinador e atletas</a></nav><section class="trainer-section" data-trainer-coach-section><div class="section-title-row"><div><h2>Modo treinador</h2></div></div><div class="trainer-people-grid trainer-athlete-list"><article class="content-card trainer-person-card is-selected"><div class="trainer-person-heading"><div><strong>Atleta exemplo</strong><span>@atleta</span></div><span class="status-pill">Atleta</span></div><div class="trainer-card-actions"><a class="primary-button">Abrir atleta</a><button class="secondary-button">•••</button></div></article></div></section><section class="trainer-athlete-workspace" id="athlete-workspace" tabindex="-1"><div class="trainer-athlete-header content-card"><div class="trainer-person-heading"><div><span>Atleta selecionado</span><h2>Atleta exemplo</h2><small>@atleta</small></div></div><div class="trainer-athlete-actions"><button class="primary-button">Prescrever treino</button><a class="secondary-button">Agenda mensal</a></div></div><p class="trainer-context-line">2 próximos treinos · 1 feedback recebido</p><div class="trainer-workspace-grid"><section class="content-card"><h2>Prescrições</h2><div class="trainer-mini-list"><article><div><span>10 set · 14:00</span><strong>Treino B</strong><small>4 exercícios · Publicado</small></div></article></div></section><section class="content-card"><h2>Cronogramas</h2><div class="trainer-mini-list"><article><div><strong>Base 5 km</strong><small>Privado</small></div></article></div></section></div></section></div></main>'''

PROGRESS = '''
<main class="main-content progress-page" data-progress-page><div class="progress-shell"><header class="progress-heading"><span class="eyebrow">ANÁLISE</span><h1>Progresso</h1></header><form class="progress-filterbar"><div class="progress-period-presets"><button class="view-button is-active">30 dias</button><button class="view-button">90 dias</button></div><label class="progress-filter-select">Esporte<select><option>Todos</option></select></label><label class="progress-filter-select">Métrica<select><option>Distância</option></select></label></form><section class="progress-kpi-strip"><div><span>Atividades</span><strong>18</strong></div><div><span>Tempo</span><strong>12h 42min</strong></div><div><span>Distância</span><strong>83,4 km</strong></div><div><span>Elevação</span><strong>1.420 m</strong></div></section><section class="progress-section"><header><div><h2>Consistência</h2><p>12 dias ativos</p></div></header><div class="progress-consistency-wrap"><div class="fixture-bars"><span></span><span></span><span></span><span></span><span></span></div></div></section><section class="progress-section"><header><div><h2>Volume</h2><p>18 atividades</p></div><strong class="progress-section-unit">Distância · Todos os esportes</strong></header><div class="progress-bar-chart" style="--progress-week-count:5"><div class="progress-bar-column"><span>1</span><div class="progress-bar-track"><i style="height:32%"></i></div></div><div class="progress-bar-column"><span>2</span><div class="progress-bar-track"><i style="height:68%"></i></div></div><div class="progress-bar-column"><span>3</span><div class="progress-bar-track"><i style="height:84%"></i></div></div><div class="progress-bar-column"><span>4</span><div class="progress-bar-track"><i style="height:56%"></i></div></div><div class="progress-bar-column"><span>5</span><div class="progress-bar-track"><i style="height:76%"></i></div></div></div></section><section class="progress-section"><header><div><h2>Comparação entre períodos</h2></div></header><div class="progress-comparison-list"><div><span>Distância</span><strong>83,4 km</strong><small>Anterior 64,4 km · Δ +19 km</small></div><div><span>Atividades</span><strong>18</strong><small>Anterior 15 · Δ +3</small></div></div></section></div></main>'''

RESET = '''
<div class="onboarding-shell signup-onboarding-shell auth-unified-shell"><main class="onboarding-card auth-unified-card"><div class="auth-unified-heading"><h1>Código de redefinição</h1><p>Digite o código de 6 dígitos enviado por e-mail.</p></div><div class="alert alert-info" role="status">Se houver uma conta com esse e-mail, enviaremos um código.</div><form class="auth-modern-form verification-code-form"><label class="auth-modern-field" for="reset-code">Código<input id="reset-code" class="auth-modern-code-input" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" value="123456"></label><button class="auth-modern-submit">Confirmar código</button></form><form class="auth-modern-resend"><button>Enviar outro código</button></form><div class="auth-modern-footer"><a>Usar outro e-mail</a></div></main></div>'''


def render(page, body, viewport, theme, screenshot):
    page.set_viewport_size({'width': viewport[0], 'height': viewport[1]})
    page.set_content(f'<!doctype html><html data-theme="{theme}"><head>{BASE_HEAD}</head><body><div class="fixture-shell">{body}</div></body></html>')
    for css in CSS_FILES:
        page.add_style_tag(path=str(css))
    page.add_style_tag(content=FIXTURE_STYLE)
    page.wait_for_timeout(30)
    width = page.evaluate('document.documentElement.scrollWidth')
    inner = page.evaluate('window.innerWidth')
    if width > inner + 1:
        raise AssertionError(f'body overflow {width}>{inner} at {viewport}')
    if screenshot.startswith('01-activities'):
        heights = page.locator('.activity-list-row').evaluate_all('(rows)=>rows.map(row=>row.getBoundingClientRect().height)')
        if len(heights) != 2 or abs(heights[0] - heights[1]) > 12:
            raise AssertionError(f'activity strength row density diverged: {heights}')
    if screenshot.startswith('02-activities'):
        delete_box = page.locator('.activity-bulk-actions .is-danger').bounding_box()
        if not delete_box or delete_box['x'] < 0 or delete_box['x'] + delete_box['width'] > inner + 1:
            raise AssertionError('mobile destructive batch action is clipped')
    if screenshot.startswith('10-progress'):
        chart_height = page.locator('.progress-bar-chart').evaluate('(node)=>node.getBoundingClientRect().height')
        if chart_height > 220:
            raise AssertionError(f'mobile progress chart is too tall: {chart_height}')
    page.screenshot(path=str(SCREEN_DIR / screenshot), full_page=True)


def test_activity_batch(page):
    html = '<!doctype html><html><body><section data-activity-history data-initial-state="ready" data-initial-cursor="" data-initial-total="2"><button data-bulk-toggle>Selecionar</button><form data-bulk-bar hidden><strong data-bulk-count></strong><button type="button" data-bulk-select-visible></button><select name="idmodalidade"><option value="">Não alterar</option><option value="x">X</option></select><select name="duracao_modo"><option value="keep">Não alterar</option><option value="set">Definir</option></select><span data-bulk-duration-value hidden><input name="duracao_minutos"></span><select name="visibilidade"><option value="">Não alterar</option><option value="publico">Público</option></select><button type="submit">Aplicar</button><button type="button" data-bulk-cancel>Cancelar seleção</button><button type="button" data-bulk-delete>Apagar</button></form><div data-activity-list><article data-history-row data-activity-id="1"><label><input type="checkbox" data-bulk-row-select></label><button></button></article><article data-history-row data-activity-id="2"><label><input type="checkbox" data-bulk-row-select></label><button></button></article></div><div data-history-empty hidden></div><div data-history-error hidden></div><div data-history-more hidden></div><div data-activity-history-status></div></section></body></html>'
    page.set_content(html)
    page.evaluate("""()=>{const values={};const storage={getItem:k=>values[k]??null,setItem:(k,v)=>values[k]=String(v),removeItem:k=>delete values[k],key:i=>Object.keys(values)[i]||null,get length(){return Object.keys(values).length}};Object.defineProperty(window,'localStorage',{configurable:true,value:storage});Object.defineProperty(window,'sessionStorage',{configurable:true,value:storage});Object.defineProperty(document,'cookie',{configurable:true,get(){return ''},set(){}});window.StrideBRI18n={t:(k,v,f)=>f||k,tn:(a,b,c)=>String(c),number:n=>String(n),locale:'pt-BR'};window.matchMedia=()=>({matches:false,addEventListener(){},removeEventListener(){}})}""")
    errors = []
    page.on('pageerror', lambda exc: errors.append(str(exc)))
    page.add_script_tag(path=str(ROOT / 'public/assets/js/atividades.js'))
    page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
    page.wait_for_timeout(40)
    page.locator('[data-bulk-toggle]').click()
    page.locator('[data-bulk-row-select]').first.check()
    if not page.locator('[data-bulk-bar] button[type=submit]').is_disabled():
        raise AssertionError('batch Apply enabled without changes')
    page.select_option('[data-bulk-bar] select[name=visibilidade]', 'publico')
    if page.locator('[data-bulk-bar] button[type=submit]').is_disabled():
        raise AssertionError('batch Apply stayed disabled after a real change')
    page.locator('[data-bulk-select-visible]').click()
    if page.locator('[data-bulk-row-select]:checked').count() != 2:
        raise AssertionError('select loaded did not select loaded rows')
    page.locator('[data-bulk-cancel]').click()
    if not page.locator('[data-bulk-bar]').is_hidden():
        raise AssertionError('cancel selection did not leave batch mode')
    if errors:
        raise AssertionError('activity batch browser errors: ' + ' | '.join(errors))


def test_trainer_partial(page):
    initial = '<!doctype html><html><body><main><div class="trainer-shell"><nav class="planning-subnav">old nav</nav><section class="trainer-section" data-trainer-coach-section><div class="trainer-athlete-list"><a data-trainer-athlete-link href="https://stridebr.test/user/treinador.php?atleta=b#athlete-workspace">Atleta A</a></div></section><section id="athlete-workspace">old workspace</section></div></main></body></html>'
    response_selected = '<!doctype html><html><head><title>Atleta B</title></head><body><nav class="planning-subnav">new nav</nav><section class="trainer-section" data-trainer-coach-section><div class="trainer-athlete-list"><a data-trainer-athlete-link href="https://stridebr.test/user/treinador.php?atleta=b#athlete-workspace">Atleta B</a></div></section><section id="athlete-workspace" tabindex="-1">workspace B</section></body></html>'
    response_empty = '<!doctype html><html><head><title>Treinador</title></head><body><nav class="planning-subnav">base nav</nav><section class="trainer-section" data-trainer-coach-section><div class="trainer-athlete-list"><a data-trainer-athlete-link href="https://stridebr.test/user/treinador.php?atleta=b#athlete-workspace">Atleta B</a></div></section></body></html>'
    page.set_content(initial)
    page.evaluate("""selected => {window.__trainerResponse=selected;window.__trainerFetches=0;window.fetch=async()=>{window.__trainerFetches++;return new Response(window.__trainerResponse,{status:200,headers:{'Content-Type':'text/html'}})};history.pushState=(s,t,u)=>window.__trainerPushed=String(u);history.replaceState=(s,t,u)=>window.__trainerReplaced=String(u);window.StrideBRI18n={t:(k,v,f)=>f||k};window.matchMedia=()=>({matches:true,addListener(){},removeListener(){}})}""", response_selected)
    errors = []
    page.on('pageerror', lambda exc: errors.append(str(exc)))
    page.add_script_tag(path=str(ROOT / 'public/assets/js/trainer.js'))
    page.locator('[data-trainer-athlete-link]').click()
    page.wait_for_timeout(50)
    if page.locator('#athlete-workspace').inner_text() != 'workspace B':
        raise AssertionError('trainer selected workspace did not update')
    if page.locator('[data-trainer-coach-section]').inner_text().strip() != 'Atleta B':
        raise AssertionError('trainer coach section did not update')
    if page.evaluate('window.__trainerFetches') != 1:
        raise AssertionError('trainer partial navigation did not use one fetch')
    if not page.evaluate('window.__trainerPushed || ""'):
        raise AssertionError('trainer partial navigation did not push history')
    page.evaluate('(empty)=>window.__trainerResponse=empty', response_empty)
    page.dispatch_event('body', 'popstate')
    page.wait_for_timeout(50)
    if page.locator('#athlete-workspace').count() != 0:
        raise AssertionError('trainer popstate did not restore no-athlete state')
    if errors:
        raise AssertionError('trainer browser errors: ' + ' | '.join(errors))


def test_schedule_partial(page):
    initial = '<!doctype html><html><body><div class="schedule-view-layout"><select data-schedule-selector><option value="a">A</option><option value="b">B</option></select><button data-view="week" class="is-active">Semana</button><div class="schedule-side-panel">old side</div><div data-week-summary-compact>old summary</div><div data-view-context="week">old context</div><div data-calendar-view="week"><div data-calendar-scroll><div data-week-calendar>old week</div></div></div><form data-test-delete data-confirm="Remover?"><input name="action" value="delete_workout"><input name="idtreino" value="w1"><button type="submit">Apagar</button></form><section data-schedule-create hidden></section><script data-workout-editor-data type="application/json">[]</script></div></body></html>'
    response = '<!doctype html><html><head><title>Cronograma B</title></head><body><select data-schedule-selector><option value="a">A</option><option value="b" selected>B</option></select><div class="schedule-side-panel">new side</div><div data-week-summary-compact>new summary</div><div data-view-context="week">new context</div><div data-week-calendar>new week</div><script data-workout-editor-data type="application/json">[]</script></body></html>'
    response_after_delete = '<!doctype html><html><head><title>Cronograma B</title></head><body><div class="schedule-undo-bar"><span>Removido</span><form><button type="submit">Desfazer</button></form></div><select data-schedule-selector><option value="a">A</option><option value="b" selected>B</option></select><div class="schedule-side-panel">after delete</div><div data-week-summary-compact>new summary</div><div data-view-context="week">new context</div><div data-week-calendar>after delete week</div><script data-workout-editor-data type="application/json">[]</script></body></html>'
    page.set_content(initial)
    page.evaluate("""payloads=>{const store={};const storage={getItem:k=>Object.prototype.hasOwnProperty.call(store,k)?store[k]:null,setItem:(k,v)=>store[k]=String(v),removeItem:k=>delete store[k],key:i=>Object.keys(store)[i]||null,get length(){return Object.keys(store).length}};Object.defineProperty(window,'localStorage',{configurable:true,value:storage});Object.defineProperty(window,'sessionStorage',{configurable:true,value:storage});Object.defineProperty(document,'cookie',{configurable:true,get(){return ''},set(v){window.__cookie=v}});history.pushState=(s,t,u)=>window.__schedulePushed=String(u);history.replaceState=(s,t,u)=>window.__scheduleReplaced=String(u);window.StrideBRI18n={t:(k,v,f)=>f||k,tn:(a,b,c)=>String(c),locale:'pt-BR',weekdayShort:()=>''};window.StrideBRUI={confirm:async()=>{window.__scheduleConfirms=(window.__scheduleConfirms||0)+1;return true},notify:(m,t)=>{window.__scheduleNotice=[m,t]}};window.StrideBRNet={fetch:async(url,opts={})=>{window.__scheduleFetches=(window.__scheduleFetches||0)+1;if(String(opts.method||'GET').toUpperCase()==='POST'){window.__schedulePosts=(window.__schedulePosts||0)+1;return new Response(JSON.stringify({ok:true,message:'Treino removido'}),{status:200,headers:{'Content-Type':'application/json'}})}window.__scheduleGets=(window.__scheduleGets||0)+1;const payload=window.__scheduleGets>1?payloads[1]:payloads[0];return new Response(payload,{status:200,headers:{'Content-Type':'text/html'}})}};}""", [response, response_after_delete])
    errors = []
    page.on('pageerror', lambda exc: errors.append(str(exc)))
    page.add_script_tag(path=str(ROOT / 'public/assets/js/cronogramas.js'))
    page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
    page.wait_for_timeout(40)
    page.select_option('[data-schedule-selector]', 'b')
    page.wait_for_timeout(80)
    if page.locator('.schedule-side-panel').inner_text() != 'new side' or page.locator('[data-week-calendar]').inner_text() != 'new week':
        raise AssertionError('schedule partial refresh did not replace workspace fragments')
    if page.locator('[data-schedule-selector]').input_value() != 'b':
        raise AssertionError('schedule partial refresh did not sync selector')
    if page.evaluate('window.__scheduleFetches || 0') != 1:
        raise AssertionError('schedule selector did not use one partial fetch')
    if 'id=b' not in page.evaluate('window.__schedulePushed || ""'):
        raise AssertionError('schedule selector did not push perceived state')
    page.locator('[data-test-delete] button').click()
    page.wait_for_timeout(100)
    if page.evaluate('window.__schedulePosts || 0') != 1 or page.evaluate('window.__scheduleConfirms || 0') != 1:
        raise AssertionError('schedule delete did not confirm and post asynchronously exactly once')
    if page.locator('.schedule-undo-bar').count() != 1:
        raise AssertionError('schedule delete did not preserve undo UI after partial revalidation')
    if page.locator('.schedule-side-panel').inner_text() != 'after delete':
        raise AssertionError('schedule delete did not revalidate the local workspace')
    if errors:
        raise AssertionError('schedule browser errors: ' + ' | '.join(errors))


def main():
    checks = 0
    with sync_playwright() as p:
        browser = p.chromium.launch(executable_path='/usr/bin/chromium', headless=True, args=['--no-sandbox'])
        page = browser.new_page()
        scenarios = [
            (ACTIVITY_HTML, (1440, 900), 'dark', '01-activities-batch-strength-desktop-dark.png'),
            (ACTIVITY_HTML, (390, 844), 'light', '02-activities-batch-strength-mobile-light.png'),
            (LIBRARY_WORKOUTS, (1440, 900), 'dark', '03-library-workouts-desktop-dark.png'),
            (LIBRARY_WORKOUTS, (390, 844), 'light', '04-library-workouts-mobile-light.png'),
            (LIBRARY_EXERCISES, (1024, 768), 'light', '05-library-exercises-tablet-light.png'),
            (LIBRARY_EXERCISES, (375, 812), 'dark', '06-library-exercises-mobile-dark.png'),
            (TRAINER_OFF, (1024, 768), 'dark', '07-trainer-mode-off-tablet-dark.png'),
            (TRAINER_ACTIVE, (390, 844), 'light', '08-trainer-active-mobile-light.png'),
            (PROGRESS, (1440, 900), 'dark', '09-progress-desktop-dark.png'),
            (PROGRESS, (390, 844), 'light', '10-progress-mobile-light.png'),
            (RESET, (360, 800), 'dark', '11-reset-code-mobile-dark.png'),
        ]
        for body, viewport, theme, screenshot in scenarios:
            render(page, body, viewport, theme, screenshot)
            checks += 1
        render(page, ACTIVITY_HTML, (768, 900), 'dark', '12-activities-batch-tablet-dark.png')
        checks += 1
        render(page, PROGRESS, (375, 812), 'dark', '13-progress-375-dark.png')
        checks += 1
        render(page, LIBRARY_WORKOUTS, (360, 800), 'light', '14-library-workouts-360-light.png')
        checks += 1
        test_activity_batch(page)
        checks += 5
        test_trainer_partial(page)
        checks += 5
        test_schedule_partial(page)
        checks += 5
        browser.close()
    print(f'PASS final product polish browser: {checks} checks, {len(list(SCREEN_DIR.glob("*.png")))} screenshots')

if __name__ == '__main__':
    main()
