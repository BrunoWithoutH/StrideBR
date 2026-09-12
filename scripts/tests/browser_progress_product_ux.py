#!/usr/bin/env python3
from pathlib import Path
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f"Playwright indisponível: {exc}", file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
STYLE = ROOT / 'public/assets/css/style.css'
INSIGHTS = ROOT / 'public/assets/css/product-insights.css'
CSS = ROOT / 'public/assets/css/sport-hub.css'
UI_REFRESH = ROOT / 'public/assets/css/ui-refresh.css'
JS = ROOT / 'public/assets/js/progresso.js'
SPORTS = [
    'Corrida', 'Musculação', 'Ciclismo', 'Natação', 'Atletismo', 'Futebol', 'Karatê',
    'Tênis', 'Caminhada', 'Dardos', 'Escalada', 'Esqui', 'Yoga', 'Vôlei', 'Basquete',
    'Jiu-jitsu', 'Badminton', 'Remo', 'Triatlo', 'Handebol',
]


def nav(selected='Visão geral', athletics=False):
    visible = ['Corrida', 'Musculação', 'Ciclismo', 'Natação', 'Atletismo']
    if selected not in ('Visão geral', *visible):
        visible = [selected] + visible[:4]
    direct = ''.join(
        f'<a href="/user/progresso.php?sport={name.lower()}" class="progress-priority-tab{(" is-mobile-primary" if index < 2 or name == selected else "")}{(" is-active" if name == selected else "")}"{(" aria-current=\"page\"" if name == selected else "")}>{name}</a>'
        for index, name in enumerate(visible)
    )
    overflow = [name for name in SPORTS if name not in visible]
    current = '' if selected == 'Visão geral' else f'<a class="progress-nav-current-item" href="#" aria-current="page"><span>{selected}</span></a>'
    mobile_extra = ''.join(f'<a class="is-mobile-only" href="#"><span>{name}</span></a>' for name in visible[2:])
    rest = ''.join(f'<a href="#"><span>{name}</span></a>' for name in overflow)
    return f'''<nav class="segmented-nav progress-sport-nav" aria-label="Esporte"><a href="#" class="progress-overview-tab{(" is-active" if selected == "Visão geral" else "")}">Visão geral</a>{direct}<details class="progress-nav-more" data-progress-nav-more><summary>Mais</summary><div class="progress-nav-menu">{current}{mobile_extra}{rest}</div></details></nav>'''


def event_nav():
    visible = ['100 m', '200 m', 'Dardo', 'Peso', 'Salto em distância']
    rest = ['400 m', '800 m', '1.500 m', 'Disco', 'Martelo', 'Salto em altura']
    direct = ''.join(f'<a href="#" class="progress-priority-tab{(" is-mobile-primary" if i < 2 else "")}{(" is-active" if i == 0 else "")}">{name}</a>' for i, name in enumerate(visible))
    mobile_extra = ''.join(f'<a class="is-mobile-only" href="#"><span>{name}</span></a>' for name in visible[2:])
    overflow = ''.join(f'<a href="#"><span>{name}</span></a>' for name in rest)
    return f'''<nav class="sport-subtabs progress-event-nav"><a href="#" class="progress-event-summary-tab">Resumo</a>{direct}<details class="progress-nav-more" data-progress-nav-more><summary>Mais</summary><div class="progress-nav-menu"><a class="progress-nav-current-item" href="#" aria-current="page"><span>100 m</span></a>{mobile_extra}{overflow}</div></details></nav>'''


def sport_rows():
    rows = []
    for index, name in enumerate(SPORTS):
        empty = index >= 8
        info = 'Última atividade: 18/06/2026' if empty else f'{18 - min(index, 12)} atividades'
        values = '' if empty else f'<strong>{118 - index * 4},4 km</strong><small>{12 - min(index, 8)}h 40min</small>'
        rows.append(f'''<a href="#" class="progress-sport-row{(" is-period-empty" if empty else "")}"><span class="progress-sport-icon">○</span><span><strong>{name}</strong><small>{info}</small></span><span class="progress-sport-values">{values}</span><span class="progress-row-chevron">›</span></a>''')
    first = ''.join(rows[:7])
    rest = ''.join(rows[7:])
    return f'''<div class="progress-sport-list">{first}<details class="progress-list-disclosure" data-test-sports-disclosure><summary><span class="is-closed">+ {len(rows)-7} a mais</span><span class="is-open">Mostrar menos</span></summary><div class="progress-sport-list progress-sport-list-extra">{rest}</div></details></div>'''


def distribution():
    rows = []
    for i, name in enumerate(SPORTS[:10]):
        share = max(3, 32 - i * 3)
        rows.append(f'<div><span><strong>{name}</strong><small>{max(1, 12-i)}h 20min</small></span><div><i style="width:{share}%"></i></div><b>{share}%</b></div>')
    return f'''<div class="progress-distribution-list">{''.join(rows[:6])}</div><details class="progress-list-disclosure progress-distribution-disclosure"><summary><span class="is-closed">+ 4 a mais</span><span class="is-open">Mostrar menos</span></summary><div class="progress-distribution-list progress-distribution-extra">{''.join(rows[6:])}</div></details>'''


def common(body, sport='Visão geral', period='Todo o histórico', theme='light', athletics=False):
    return f'''<!doctype html><html lang="pt-BR" data-theme="{theme}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><style>*,*:before,*:after{{box-sizing:border-box}}</style></head><body><div class="container-fluid"><main class="main-content progress-page" data-progress-page><div class="progress-shell"><header class="progress-heading"><div><span class="progress-eyebrow">PROGRESSO</span><h1>Progresso</h1></div></header>{nav(sport)}<div data-progress-dynamic>{event_nav() if athletics else ''}<form class="progress-filterbar" action="/user/progresso.php" data-progress-preference-url="/api/progress-preferences.php" data-progress-csrf-token="fixture"><label class="progress-filter-select"><span>Período</span><select name="period" data-progress-auto-submit data-progress-period-select><option selected>{period}</option></select></label><input type="hidden" name="sport" value="all"></form><div class="progress-context-line"><strong>{sport}</strong><span>Desde 03/02/2021</span></div>{body}</div></div><div class="progress-tooltip" data-progress-tooltip-popover role="tooltip" hidden></div></main></div><footer class="site-footer"><div class="footer-inner"><div class="footer-top"><div class="footer-brand"><strong>StrideBR</strong><p>Dados esportivos.</p></div><div class="footer-column"><h4>Produto</h4><a href="#">Sobre</a></div></div></div></footer><nav class="mobile-bottom-nav" aria-label="Navegação"><a class="mobile-nav-item">Início</a><a class="mobile-nav-item">Treino</a><a class="mobile-nav-item">Atividades</a><a class="mobile-nav-item is-active">Progresso</a><a class="mobile-nav-item">Perfil</a></nav><div class="global-tools" data-global-tools><div class="floating-utility-dock"><div class="pinned-tools"><button class="pinned-tool-chip">00:45</button></div><button class="quick-tools-launcher" aria-label="Ferramentas">+</button></div></div></body></html>'''


def overview(theme='light'):
    highlights = '''<section class="progress-section" data-order="highlights"><header><div><h2>Destaques</h2></div></header><div class="progress-highlight-grid"><article><span>Maior distância registrada</span><strong>18,4 km</strong></article><article><span>Volume do período</span><strong>+12%</strong><small>vs. período anterior</small></article></div></section>'''
    body = f'''<section class="progress-section progress-summary-section" data-order="summary"><header><div><h2>Resumo do período</h2></div></header><div class="progress-kpi-strip"><div><span>Dias ativos</span><strong>42</strong></div><div><span>Atividades</span><strong>68</strong></div><div><span>Tempo em atividade</span><strong>61h 20min</strong></div><div><span>Esportes praticados</span><strong>14</strong></div></div></section>{highlights}<section class="progress-section" data-order="sports"><header><div><h2>Seus esportes</h2></div></header>{sport_rows()}</section><section class="progress-section progress-goals-section" data-order="goals"><header><div><h2>Metas</h2></div><a class="progress-button">Abrir metas</a></header><div class="progress-goal-list"><article><div><strong>Correr 100 km</strong><small>Mensal</small></div><div><span>72 / 100 km</span><progress max="100" value="72"></progress></div></article></div></section><section class="progress-section" data-order="consistency"><header><div><h2>Consistência</h2><p>Atividade em 10 de 12 semanas</p></div></header><div class="progress-consistency-band">{''.join('<span class="is-active"></span>' if i not in (4,9) else '<span></span>' for i in range(12))}</div></section><section class="progress-section" data-order="distribution"><header><div><h2>Distribuição da prática</h2><p>Participação no tempo total de atividade do período.</p></div></header>{distribution()}</section>'''
    return common(body, theme=theme)


def running(theme='light'):
    bars = ''.join(f'<div class="progress-bar-column"><button type="button" class="progress-bar-hit" data-progress-tooltip="Semana {i+1} · {5+i},2 km"><span class="progress-bar" style="height:{35+i*10}%"></span></button><small>{i+1}/8</small></div>' for i in range(8))
    body = f'''<section class="progress-section progress-summary-section"><header><div><h2>Resumo do período</h2></div></header><div class="progress-kpi-strip"><div><span>Distância</span><strong>28,4 km</strong></div><div><span>Tempo</span><strong>2h 51min</strong></div><div><span>Corridas</span><strong>5</strong></div><div><span>Dias ativos</span><strong>5</strong></div></div></section><section class="progress-section"><header><div><h2>Distância</h2></div><div class="progress-chart-controls"><nav class="progress-chart-switch"><a class="is-active">Distância</a><a>Tempo</a></nav><strong class="progress-section-unit">4 semanas</strong></div></header><div class="progress-bar-chart" style="--progress-week-count:8">{bars}</div></section><section class="progress-section progress-load"><header><div><h2>Carga de treino</h2></div></header><div class="progress-kpi-strip"><div><span>Carga de treino</span><strong>1240 UA</strong><small>RPE registrado em 4 de 5 atividades</small></div></div></section>'''
    return common(body, sport='Corrida', period='4 semanas', theme=theme)


def strength(theme='light'):
    body = '''<section class="progress-section progress-summary-section"><header><div><h2>Resumo do período</h2></div></header><div class="progress-kpi-strip"><div><span>Treinos</span><strong>9</strong></div><div><span>Séries</span><strong>146</strong></div><div><span>Repetições</span><strong>1120</strong></div><div><span>Volume de carga</span><strong>42.380 kg</strong></div></div></section><section class="progress-section"><header><div><h2>Progresso por exercício</h2></div></header><label class="progress-exercise-search"><span>Buscar exercício</span><input type="search" data-progress-exercise-search></label><div class="strength-exercise-list" data-progress-exercise-list><article data-progress-exercise="supino reto"><div><strong>Supino reto</strong><small>Última atividade: 09/09/2026</small></div><div class="strength-exercise-values"><span><small>1RM estimado</small><b>88,7 kg</b><small>Estimado a partir de 70 kg × 8 · Epley</small></span></div></article><article data-progress-exercise="agachamento"><div><strong>Agachamento</strong></div><div class="strength-exercise-values"><span><small>Melhor carga</small><b>100 kg</b></span></div></article></div></section>'''
    return common(body, sport='Musculação', period='12 semanas', theme=theme)


def athletics(theme='light'):
    body = '''<section class="progress-section progress-summary-section"><header><div><h2>Resumo do período</h2></div></header><div class="progress-kpi-strip"><div><span>Sessões</span><strong>7</strong></div><div><span>Provas praticadas</span><strong>8</strong></div><div><span>Tentativas</span><strong>14</strong></div><div><span>Marcas válidas</span><strong>11</strong></div></div></section><section class="progress-section"><header><div><h2>Por prova</h2></div></header><div class="athletics-record-grid"><article><div><strong>100 m</strong><small>3 sessões</small></div><div class="athletics-record-value"><span>Melhor tempo registrado</span><b>12,84 s</b></div></article><article><div><strong>Dardo</strong><small>2 sessões</small></div><div class="athletics-record-value"><span>Melhor marca registrada</span><b>38,42 m</b></div></article></div></section>'''
    return common(body, sport='Atletismo', period='6 meses', theme=theme, athletics=True)


with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    checks = 0

    def check(value, message):
        global checks
        checks += 1
        if not value:
            raise AssertionError(message)

    def load(page, html):
        page.set_content(html)
        page.add_style_tag(path=str(STYLE))
        page.add_style_tag(path=str(INSIGHTS))
        page.add_style_tag(path=str(CSS))
        page.add_style_tag(path=str(UI_REFRESH))
        page.add_script_tag(path=str(JS))

    page = browser.new_page(viewport={'width': 1440, 'height': 1000})
    load(page, overview('light'))
    order = [page.locator(f'[data-order="{name}"]').bounding_box()['y'] for name in ['summary', 'highlights', 'sports', 'goals', 'consistency', 'distribution']]
    check(order == sorted(order), 'Overview mantém hierarquia principal')
    check(page.locator('.progress-sport-nav > a:visible').count() <= 6, 'Desktop limita tabs esportivas diretas')
    check(page.locator('.progress-sport-nav details').count() == 1, 'Desktop oferece More')
    page.locator('.progress-sport-nav summary').click()
    check(page.locator('.progress-sport-nav .progress-nav-menu a:visible').count() >= 15, 'More mantém esportes restantes acessíveis')
    menu_box = page.locator('.progress-sport-nav .progress-nav-menu').bounding_box()
    check(menu_box['x'] >= 0 and menu_box['x'] + menu_box['width'] <= 1440, 'More desktop permanece dentro da viewport')
    check(page.locator('.progress-filterbar select[name="period"]').count() == 1 and page.locator('.progress-filterbar select[name="metric"]').count() == 0, 'Topo mantém apenas seletor de período')
    check(page.locator('[data-order="sports"] > .progress-sport-list > .progress-sport-row').count() == 7, 'Your sports limita a lista inicial a sete')
    page.locator('[data-test-sports-disclosure] > summary').click()
    check(page.locator('.progress-sport-list-extra .progress-sport-row:visible').count() == len(SPORTS) - 7, 'Your sports expandido mostra todos os restantes')
    check('nav.goals' not in page.locator('main').inner_text(), 'Nenhuma chave nav.goals vaza na UI')

    page.set_content(running('dark'))
    page.add_style_tag(path=str(STYLE)); page.add_style_tag(path=str(INSIGHTS)); page.add_style_tag(path=str(CSS)); page.add_style_tag(path=str(UI_REFRESH)); page.add_script_tag(path=str(JS))
    check(page.locator('.progress-chart-switch').count() == 1, 'Corrida move alternância de métrica para o gráfico')
    check(page.locator('.progress-filterbar select').count() == 1, 'Corrida não recoloca métrica no filtro global')
    check(page.evaluate("document.documentElement.dataset.theme") == 'dark', 'Corrida renderiza em dark')

    page.set_content(strength('light'))
    page.add_style_tag(path=str(STYLE)); page.add_style_tag(path=str(INSIGHTS)); page.add_style_tag(path=str(CSS)); page.add_style_tag(path=str(UI_REFRESH)); page.add_script_tag(path=str(JS))
    page.locator('[data-progress-exercise-search]').fill('supino')
    check(page.locator('[data-progress-exercise="agachamento"]').get_attribute('hidden') is not None, 'Busca de exercício segue funcionando')

    page.set_content(athletics('dark'))
    page.add_style_tag(path=str(STYLE)); page.add_style_tag(path=str(INSIGHTS)); page.add_style_tag(path=str(CSS)); page.add_style_tag(path=str(UI_REFRESH)); page.add_script_tag(path=str(JS))
    check(page.locator('.progress-event-nav > a:visible').count() <= 6, 'Atletismo limita provas diretas')
    page.locator('.progress-event-nav summary').click()
    check(page.locator('.progress-event-nav .progress-nav-menu a:visible').count() >= 6, 'More de Atletismo mantém provas acessíveis')
    check(page.locator('.progress-event-nav .progress-nav-current-item[aria-current="page"]').count() == 1, 'More de Atletismo indica prova atual')

    for width in [360, 390, 430, 768, 1024, 1366, 1440, 1920, 2560]:
        height = 844 if width <= 430 else 900
        vp = browser.new_page(viewport={'width': width, 'height': height})
        load(vp, overview('dark' if width in (390, 1440) else 'light'))
        check(vp.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1'), f'Overview sem overflow global em {width}px')
        shell = vp.locator('.progress-page').bounding_box()
        check(shell['width'] <= min(width, 1236) + 1, f'Shell respeita max-width em {width}px')
        if width >= 1366:
            left = shell['x']
            right = width - (shell['x'] + shell['width'])
            check(abs(left - right) <= 2, f'Shell centralizada em {width}px')
        if width <= 620:
            check(vp.locator('.progress-sport-nav > a:visible').count() <= 3, f'Mobile limita tabs diretas em {width}px')
            vp.locator('.progress-sport-nav summary').click()
            box = vp.locator('.progress-sport-nav .progress-nav-menu').bounding_box()
            check(box['x'] >= 0 and box['x'] + box['width'] <= width + 1, f'More mobile cabe em {width}px')
            menu_z = int(vp.locator('.progress-sport-nav .progress-nav-menu').evaluate("el => getComputedStyle(el).zIndex"))
            tools_z = int(vp.locator('.floating-utility-dock').evaluate("el => getComputedStyle(el).zIndex"))
            check(menu_z > tools_z, f'More fica acima das ferramentas flutuantes em {width}px')
        vp.close()

    browser.close()
    print(f'✓ browser progress polish UX: {checks} assertions')
