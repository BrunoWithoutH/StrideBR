from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[2]
CSS_FILES = [ROOT / 'public/assets/css/style.css', ROOT / 'public/assets/css/dashboard.css', ROOT / 'public/assets/css/ui-refresh.css']
PROGRESS_CSS_FILES = [ROOT / 'public/assets/css/style.css', ROOT / 'public/assets/css/product-insights.css', ROOT / 'public/assets/css/sport-hub.css', ROOT / 'public/assets/css/ui-refresh.css']
JS = ROOT / 'public/assets/js/goals.js'
SHOT_DIR = Path('/tmp/stridebr-progress-b2-shots')
SHOT_DIR.mkdir(parents=True, exist_ok=True)

BENCHMARK_DATA = '''{"rows":[{"id":"b1","type":"one_rm","sport":"m_strength","exercise":"e_bench","distance":null,"value":85,"date":"2026-09-01"},{"id":"b2","type":"ftp","sport":"m_cycle","exercise":"","distance":null,"value":238,"date":"2026-09-02"},{"id":"b3","type":"css","sport":"m_swim","exercise":"","distance":null,"value":106,"date":"2026-09-03"},{"id":"b4","type":"distance_time","sport":"m_run","exercise":"","distance":5000,"value":1798,"date":"2026-09-04"},{"id":"b5","type":"distance_time","sport":"m_run","exercise":"","distance":10000,"value":3734,"date":"2026-09-05"}],"registry":{"one_rm":{"direction":"higher","primary":"best","slugs":[],"families":["strength"],"requiresExercise":true,"requiresDistance":false},"ftp":{"direction":"higher","primary":"latest","slugs":["ciclismo"],"families":[],"requiresExercise":false,"requiresDistance":false},"css":{"direction":"lower","primary":"latest","slugs":["natacao"],"families":[],"requiresExercise":false,"requiresDistance":false},"distance_time":{"direction":"lower","primary":"best","slugs":["corrida"],"families":[],"requiresExercise":false,"requiresDistance":true}}}'''


def form_html():
    return f'''<section class="goals-editor" data-goals-editor><form method="post" data-goals-form>
    <input type="hidden" name="action" value="create">
    <fieldset class="goal-type-switch"><legend>O que você quer alcançar?</legend>
      <label><input type="radio" name="tipo_meta" value="metrica" checked><span><strong>Prática</strong><small>Volume e frequência.</small></span></label>
      <label><input type="radio" name="tipo_meta" value="benchmark"><span><strong>Marca ou teste</strong><small>Resultado esportivo específico.</small></span></label>
    </fieldset>
    <label>Esporte<select name="idmodalidade"><option value="">Todos os esportes</option><option value="m_strength" data-slug="musculacao" data-family="strength">Musculação</option><option value="m_cycle" data-slug="ciclismo" data-family="cardio">Ciclismo</option><option value="m_swim" data-slug="natacao" data-family="cardio">Natação</option><option value="m_run" data-slug="corrida" data-family="cardio">Corrida</option><option value="m_soccer" data-slug="futebol" data-family="team">Futebol</option></select></label>
    <div data-goal-practice-fields><label>Métrica<select name="metrica" data-goal-metric><option value="distancia">Distância</option><option value="carga_maxima">Maior carga registrada no exercício</option></select></label></div>
    <div data-goal-benchmark-fields hidden><label>Marca ou teste<select name="benchmark_tipo" data-goal-benchmark-type><option value="">Escolha</option><option value="one_rm" data-slugs="" data-families="strength">1RM medido</option><option value="ftp" data-slugs="ciclismo" data-families="">FTP</option><option value="css" data-slugs="natacao" data-families="">CSS</option><option value="distance_time" data-slugs="corrida" data-families="">Teste por distância</option></select><small data-goal-benchmark-unavailable hidden>Ainda não há metas de marca ou teste disponíveis para este esporte.</small></label></div>
    <label data-goal-exercise-field hidden>Exercício<select name="idexercicio" data-goal-exercise><option value="">Escolha</option><option value="e_bench">Supino reto</option></select><small class="goal-field-help" data-goal-load-help>Usa a maior carga registrada em uma série; não é o mesmo que 1RM medido.</small></label>
    <div data-goal-distance-field hidden><label>Distância<select name="benchmark_distancia_m" data-goal-distance><option value="1000">1 km</option><option value="3000">3 km</option><option value="5000">5 km</option><option value="10000">10 km</option><option value="21097.5">21,0975 km</option><option value="42195">42,195 km</option><option value="custom">Personalizada</option></select></label><label data-goal-custom-distance hidden>Distância (m)<input name="benchmark_distancia_custom_m" type="number"></label></div>
    <div data-goal-benchmark-reference hidden aria-live="polite"></div>
    <label><span data-goal-target-label>Meta</span><span class="dashboard-target-input"><input name="valor_alvo" value="20" required><span data-goal-unit>km</span></span></label>
    <label>Prazo<select name="periodo" data-goal-period><option value="continuo">Sem prazo</option><option value="personalizado">Até uma data</option><option value="semanal" data-practice-period>Toda semana</option><option value="mensal" data-practice-period>Todo mês</option><option value="anual" data-practice-period>Todo ano</option></select></label>
    <div class="goals-custom-dates" data-goal-custom-dates><label data-goal-start-field>Início<input name="data_inicio" type="date"></label><label>Prazo<input name="data_fim" type="date"></label></div>
    <label>Nome opcional<input name="nome"></label><div data-goal-summary></div><button type="submit">Salvar meta</button>
    </form></section>'''


def cards_html():
    return '''<section data-goals-section="active"><div class="goal-grid">
    <article class="goal-card"><header><div><span>Corrida</span><h3>Distância semanal</h3></div><strong class="goal-percent">60%</strong></header><div class="goal-status"><span>Distância</span><strong>Faltam: 8 km</strong></div></article>
    <article class="goal-card goal-card-benchmark"><header><div><span>MUSCULAÇÃO</span><h3>Supino reto · 1RM medido</h3></div><strong class="goal-percent">25%</strong></header><div class="goal-benchmark-values"><div><span>Melhor desde o início da meta</span><strong>87,5 kg</strong></div><div><span>Alvo</span><strong>100 kg</strong></div></div><div class="goal-benchmark-baseline"><span>Ao criar</span><strong>82,5 kg</strong></div><div class="goal-status"><span>Sem prazo</span><strong>Faltam: 12,5 kg</strong></div></article>
    <article class="goal-card goal-card-benchmark"><header><div><span>CICLISMO</span><h3>FTP</h3></div></header><div class="goal-benchmark-values"><div><span>Melhor desde o início da meta</span><strong>242 W</strong></div><div><span>Alvo</span><strong>250 W</strong></div></div><div class="goal-status"><span>Sem prazo</span><strong>Faltam: 8 W</strong></div></article>
    <article class="goal-card goal-card-benchmark"><header><div><span>NATAÇÃO</span><h3>CSS</h3></div></header><div class="goal-benchmark-values"><div><span>Melhor desde o início da meta</span><strong>1:44/100 m</strong></div><div><span>Alvo</span><strong>1:40/100 m</strong></div></div><div class="goal-status"><span>Sem prazo</span><strong>Faltam: 4 s/100 m</strong></div></article>
    <article class="goal-card goal-card-benchmark"><header><div><span>CORRIDA</span><h3>5 km abaixo de 28:00</h3></div><strong class="goal-percent">47%</strong></header><div class="goal-benchmark-values"><div><span>Melhor desde o início da meta</span><strong>29:58</strong></div><div><span>Alvo</span><strong>28:00</strong></div></div><div class="goal-benchmark-baseline"><span>Ao criar</span><strong>31:42</strong></div><div class="goal-status"><span>Até 30/11/2026</span><strong>Faltam: 1:58</strong></div></article>
    <article class="goal-card goal-card-benchmark"><header><div><span>CICLISMO</span><h3>FTP</h3></div></header><div class="goal-benchmark-values"><div><span>Melhor desde o início da meta</span><strong>—</strong></div><div><span>Alvo</span><strong>250 W</strong></div></div><div class="goal-status"><span>Sem prazo</span><strong>Nenhum FTP registrado desde o início da meta.</strong><a href="#">Registrar resultado</a></div></article>
    <article class="goal-card goal-card-benchmark is-complete"><header><div><span>CORRIDA</span><h3>5 km abaixo de 28:00</h3></div><strong class="goal-percent">100%</strong></header><div class="goal-benchmark-values"><div><span>Melhor desde o início da meta</span><strong>27:54</strong></div><div><span>Alvo</span><strong>28:00</strong></div></div><div class="goal-status"><span>Atingida em 03/11/2026</span><strong>Meta atingida</strong></div></article>
    </div></section>'''


def page_html(theme='light'):
    return f'''<!doctype html><html lang="pt-BR" data-theme="{theme}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><main><div class="goals-page"><header class="dashboard-page-header"><span class="dashboard-eyebrow">METAS</span><h1>Metas</h1></header>{form_html()}{cards_html()}<section data-goals-section="history"><div class="goal-history-row"><span class="goal-history-check">✓</span><div><strong>5 km abaixo de 28:00</strong><span>Atingida em 3 nov. 2026 · 27:54</span></div><a>Ver resultado</a></div></section><script type="application/json" id="goal-benchmark-data">{BENCHMARK_DATA}</script></div></main></body></html>'''


def load(page, html):
    page.set_content(html)
    for path in CSS_FILES:
        page.add_style_tag(path=str(path))
    page.add_script_tag(path=str(JS))
    page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")


def progress_goal_page(label, title, value, theme='light'):
    return f'''<!doctype html><html lang="pt-BR" data-theme="{theme}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><main class="main-content"><div class="progress-page-shell"><span class="progress-eyebrow">PROGRESSO</span><h1>Progresso</h1><section class="progress-section progress-goals-section"><header><div><h2>Metas</h2></div><a class="progress-button">Abrir metas</a></header><div class="progress-goal-list"><article><div><strong>{title}</strong><small>Desde 11/09/2026</small></div><div><span>{value}</span><progress max="100" value="50"></progress></div></article></div></section><section class="progress-section"><header><div><h2>{label}</h2></div></header><div class="progress-kpi-strip"><div><span>Atividades</span><strong>12</strong></div><div><span>Tempo</span><strong>8h 10min</strong></div></div></section></div></main></body></html>'''


def load_progress(page, html):
    page.set_content(html)
    for path in PROGRESS_CSS_FILES:
        page.add_style_tag(path=str(path))


with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    checks = [0]

    def check(ok, message):
        checks[0] += 1
        if not ok:
            raise AssertionError(message)

    for theme, width, height in [('light',1440,1000),('dark',1440,1000),('light',390,844),('dark',390,844)]:
        pg = browser.new_page(viewport={'width':width,'height':height})
        load(pg, page_html(theme))
        check(pg.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1'), f'{theme} {width}px sem overflow horizontal')
        check(pg.locator('.goal-card-benchmark').count() == 6, f'{theme} {width}px mantém cards benchmark compactos')
        check(pg.get_by_text('87,5 kg', exact=True).count() >= 1, '1RM card usa unidade esportiva')
        check(pg.get_by_text('1:44/100 m', exact=True).count() == 1, 'CSS card não expõe segundos canônicos')
        check(pg.get_by_text('29:58', exact=True).count() == 1, 'corrida card não expõe segundos canônicos')
        check(pg.get_by_text('Nenhum FTP registrado desde o início da meta.', exact=True).count() == 1, 'estado sem benchmark não mostra zero falso')
        check(pg.get_by_text('Atingida em 3 nov. 2026 · 27:54', exact=True).count() == 1, 'histórico usa data esportiva e formatter')
        pg.screenshot(path=str(SHOT_DIR / f'goals-{theme}-{width}.png'), full_page=True)
        pg.close()

    pg = browser.new_page(viewport={'width':1440,'height':1000})
    load(pg, page_html('light'))
    practice = pg.locator('input[name="tipo_meta"][value="metrica"]')
    benchmark = pg.locator('input[name="tipo_meta"][value="benchmark"]')
    check(practice.is_checked(), 'criação abre em Prática por padrão')
    check(not pg.locator('[data-goal-practice-fields]').is_hidden(), 'campos de prática aparecem por padrão')
    benchmark.check()
    check(pg.locator('[data-goal-practice-fields]').is_hidden(), 'Marca ou teste oculta campos de prática')
    check(not pg.locator('[data-goal-benchmark-fields]').is_hidden(), 'Marca ou teste revela seletor B1')
    check(pg.locator('option[data-practice-period]:not([disabled])').count() == 0, 'benchmark não oferece recorrência semanal/mensal/anual')

    sport = pg.locator('select[name="idmodalidade"]')
    kind = pg.locator('[data-goal-benchmark-type]')
    sport.select_option('m_strength')
    check(not kind.locator('option[value="one_rm"]').evaluate('o => o.disabled'), 'Musculação oferece 1RM medido')
    check(kind.locator('option[value="ftp"]').evaluate('o => o.disabled'), 'Musculação não oferece FTP')
    kind.select_option('one_rm')
    exercise = pg.locator('[data-goal-exercise]')
    exercise.select_option('e_bench')
    check(not pg.locator('[data-goal-exercise-field]').is_hidden(), '1RM exige exercício na UX')
    check(pg.locator('[data-goal-load-help]').is_hidden(), 'help de carga máxima não aparece no fluxo de 1RM medido')
    check(pg.locator('[data-goal-unit]').inner_text() == 'kg', '1RM mostra kg')
    check('85 kg' in pg.locator('[data-goal-benchmark-reference]').inner_text(), '1RM mostra melhor referência medida da B1')
    target = pg.locator('input[name="valor_alvo"]')
    target.fill('80')
    check('already been reached' in pg.locator('[data-goal-benchmark-reference]').inner_text(), 'UX sinaliza alvo 1RM já atingido')
    target.fill('100')
    check('already been reached' not in pg.locator('[data-goal-benchmark-reference]').inner_text(), 'alvo 1RM mais difícil deixa de mostrar already-achieved')

    sport.select_option('m_cycle')
    kind.select_option('ftp')
    check(pg.locator('[data-goal-unit]').inner_text() == 'W', 'FTP mostra watts')
    check('238 W' in pg.locator('[data-goal-benchmark-reference]').inner_text(), 'FTP mostra referência atual B1')

    sport.select_option('m_swim')
    kind.select_option('css')
    check(pg.locator('[data-goal-unit]').inner_text() == '/100 m', 'CSS mostra unidade por 100 m')
    check('1:46/100 m' in pg.locator('[data-goal-benchmark-reference]').inner_text(), 'CSS mostra referência formatada')

    sport.select_option('m_run')
    kind.select_option('distance_time')
    check(not pg.locator('[data-goal-distance-field]').is_hidden(), 'corrida revela distância/protocolo')
    pg.locator('[data-goal-distance]').select_option('5000')
    target.fill('30:00')
    check('29:58' in pg.locator('[data-goal-benchmark-reference]').inner_text(), '5 km usa referência da mesma distância')
    check('already been reached' in pg.locator('[data-goal-benchmark-reference]').inner_text(), 'tempo alvo mais lento que melhor 5 km é sinalizado como já atingido')
    target.fill('28:00')
    check('already been reached' not in pg.locator('[data-goal-benchmark-reference]').inner_text(), '5 km mais rápido que referência é alvo válido na UX')

    sport.select_option('m_soccer')
    check(not pg.locator('[data-goal-benchmark-unavailable]').is_hidden(), 'esporte sem tipo B1 mostra indisponibilidade discreta')
    pg.close()

    pg = browser.new_page(viewport={'width':390,'height':844})
    load(pg, page_html('dark'))
    pg.locator('input[name="tipo_meta"][value="benchmark"]').check()
    pg.locator('select[name="idmodalidade"]').select_option('m_run')
    pg.locator('[data-goal-benchmark-type]').select_option('distance_time')
    check(pg.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1'), 'form B2 não cria overflow em 390px')
    form_box = pg.locator('[data-goals-form]').bounding_box()
    check(form_box is not None and form_box['x'] >= -1 and form_box['x'] + form_box['width'] <= 391, 'form benchmark cabe na viewport mobile')
    pg.screenshot(path=str(SHOT_DIR / 'goal-create-distance-dark-390.png'), full_page=True)
    pg.close()

    progress_scenarios = [
        ('overview', 'Visão geral', '5 km abaixo de 28:00', 'Melhor: 29:58 · Alvo: 28:00'),
        ('running', 'Corrida', '5 km abaixo de 28:00', 'Melhor: 29:58 · Alvo: 28:00'),
        ('strength', 'Musculação', 'Supino reto · 1RM medido', '87,5 / 100 kg'),
        ('cycling', 'Ciclismo', 'FTP', '242 / 250 W'),
        ('swimming', 'Natação', 'CSS', '1:44/100 m → 1:40/100 m'),
    ]
    for index, (slug, label, title, value) in enumerate(progress_scenarios):
        width, height = (390, 844) if index % 2 else (1440, 900)
        theme = 'dark' if index in (2, 4) else 'light'
        pg = browser.new_page(viewport={'width':width,'height':height})
        load_progress(pg, progress_goal_page(label, title, value, theme))
        check(pg.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1'), f'Progresso {slug} sem overflow')
        check(pg.get_by_text(title, exact=True).count() == 1, f'Progresso {slug} mostra meta benchmark no esporte correto')
        check(pg.get_by_text(value, exact=True).count() == 1, f'Progresso {slug} mostra valor formatado, não canônico cru')
        pg.screenshot(path=str(SHOT_DIR / f'progress-{slug}-{theme}-{width}.png'), full_page=True)
        pg.close()

    browser.close()
    print(f'✓ browser progress B2 UX: {checks[0]} assertions; screenshots: {SHOT_DIR}')
