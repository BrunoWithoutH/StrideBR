#!/usr/bin/env python3
from pathlib import Path
import os
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f"Playwright indisponível: {exc}", file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
CSS_FILES = [
    ROOT / 'public/assets/css/style.css',
    ROOT / 'public/assets/css/product-insights.css',
    ROOT / 'public/assets/css/sport-hub.css',
    ROOT / 'public/assets/css/ui-refresh.css',
]
JS = ROOT / 'public/assets/js/progresso.js'
SHOT_DIR = Path(os.environ.get('STRIDEBR_BROWSER_SHOTS', '/tmp/stridebr-progress-b1-shots'))
SHOT_DIR.mkdir(parents=True, exist_ok=True)


def dialog(kind):
    specific = {
        'one_rm': '<label>Exercício<select name="idexercicio"><option>Supino reto</option></select></label><label>Carga<div class="progress-input-unit"><input value="82.5"><span>kg</span></div></label>',
        'ftp': '<label>FTP<div class="progress-input-unit"><input value="238"><span>W</span></div></label><label>Protocolo<select><option>Teste de 20 min</option></select></label>',
        'css': '<label>CSS<div class="progress-input-unit"><input value="1:46"><span>/100 m</span></div></label>',
        'distance_time': '<label>Distância<div class="progress-input-unit"><input value="5"><span>km</span></div></label><label>Tempo<input value="29:58"></label><label class="progress-benchmark-check"><input type="checkbox" name="reported_official" value="1" data-progress-reported-official checked><span><strong>Informar como resultado oficial</strong><small>O StrideBR registra esta informação, mas não verifica o resultado. Resultados oficiais usam o contexto Competição.</small></span></label>',
    }[kind]
    title = {'one_rm':'Registrar 1RM','ftp':'Registrar FTP','css':'Registrar CSS','distance_time':'Registrar teste'}[kind]
    return f'''<dialog class="progress-benchmark-dialog" data-progress-benchmark-dialog data-return-url="" aria-labelledby="fixture-dialog-title" open><div class="progress-benchmark-dialog-card"><header><div><span class="progress-eyebrow">MARCAS E TESTES</span><h2 id="fixture-dialog-title">{title}</h2></div><a class="progress-dialog-close" href="#" aria-label="Fechar">×</a></header><form class="progress-benchmark-form">{specific}<label>Data<input type="date" value="2026-09-02"></label><label>Contexto<select name="contexto" data-progress-benchmark-context><option value="">Opcional</option><option value="treino">Treino</option><option value="teste">Teste</option><option value="competicao">Competição</option></select></label>{'<input type="hidden" name="contexto" value="competicao" data-progress-official-context disabled>' if kind == 'distance_time' else ''}<label class="progress-benchmark-notes">Observações<textarea rows="3"></textarea></label><div class="progress-benchmark-form-actions"><a class="progress-button">Cancelar</a><button class="progress-button is-primary">Salvar</button></div></form></div></dialog>'''


def shell(body, theme='light', dlg=''):
    return f'''<!doctype html><html lang="pt-BR" data-theme="{theme}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"></head><body><div class="container-fluid"><main class="main-content progress-page" data-progress-page><div class="progress-shell"><header class="progress-heading"><div><span class="progress-eyebrow">PROGRESSO</span><h1>Progresso</h1></div></header><nav class="segmented-nav progress-sport-nav"><a class="progress-overview-tab">Visão geral</a><a class="progress-priority-tab is-mobile-primary">Corrida</a><a class="progress-priority-tab is-mobile-primary">Musculação</a><a class="progress-priority-tab">Ciclismo</a><a class="progress-priority-tab">Natação</a><details class="progress-nav-more" data-progress-nav-more><summary>Mais</summary><div class="progress-nav-menu"><a class="is-mobile-only">Ciclismo</a><a class="is-mobile-only">Natação</a><a>Atletismo</a></div></details></nav><div data-progress-dynamic>{body}{dlg}</div></div><div class="progress-tooltip" data-progress-tooltip-popover hidden></div></main></div></body></html>'''


def strength(theme='light', with_dialog=False):
    body = '''<section class="progress-section progress-summary-section"><header><div><h2>Resumo do período</h2></div></header><div class="progress-kpi-strip"><div><span>Treinos</span><strong>9</strong></div><div><span>Séries</span><strong>146</strong></div><div><span>Repetições</span><strong>1120</strong></div><div><span>Volume de carga</span><strong>42.380 kg</strong></div></div></section><section class="progress-section"><header><div><h2>Progresso por exercício</h2><p>1RM medido e 1RM estimado são exibidos separadamente.</p></div><a class="progress-button">Registrar 1RM</a></header><div class="strength-exercise-list progress-strength-exercise-list"><article><div><strong>Supino reto</strong><small>Última atividade: 09/09/2026</small></div><div class="strength-exercise-values"><span><small>Melhor carga</small><b>70 kg</b></span><span><small>1RM medido</small><b>82,5 kg</b><small>Medido em 14/08/2026</small></span><span><small>1RM estimado</small><b>88,7 kg</b><small>Estimado a partir de 70 kg × 8 · Epley · Abrir atividade de origem</small></span></div><details class="progress-benchmark-history"><summary>Histórico · 2</summary><div><article class="progress-benchmark-history-row"><div><strong>82,5 kg</strong><small>14/08/2026</small></div><div class="progress-benchmark-actions"><a>Editar</a><button>Excluir</button></div></article></div></details></article></div></section>'''
    return shell(body, theme, dialog('one_rm') if with_dialog else '')


def cycling(theme='light', with_dialog=False):
    body = '''<section class="progress-section progress-benchmarks-section"><header><div><h2>Marcas e testes</h2></div><a class="progress-button">Registrar FTP</a></header><div class="progress-benchmark-summary"><div><span>FTP atual</span><strong>238 W</strong><small>02/09/2026</small></div><div><span>Anterior</span><strong>225 W</strong><small>+13 W</small></div></div><details class="progress-benchmark-history"><summary>Histórico · 3</summary><div><article class="progress-benchmark-history-row"><div><strong>238 W</strong><small>02/09/2026 · Teste de 20 min</small></div><div class="progress-benchmark-actions"><a>Editar</a><button>Excluir</button></div></article></div></details></section>'''
    return shell(body, theme, dialog('ftp') if with_dialog else '')


def swimming(theme='light', with_dialog=False):
    body = '''<section class="progress-section progress-benchmarks-section"><header><div><h2>Marcas e testes</h2></div><a class="progress-button">Registrar CSS</a></header><div class="progress-benchmark-summary"><div><span>CSS atual</span><strong>1:46/100 m</strong><small>04/09/2026</small></div><div><span>Anterior</span><strong>1:50/100 m</strong><small>−4 s/100 m</small></div></div></section>'''
    return shell(body, theme, dialog('css') if with_dialog else '')


def running(theme='light', with_dialog=False):
    body = '''<section class="progress-section progress-benchmarks-section"><header><div><h2>Marcas e testes</h2></div><a class="progress-button">Registrar teste</a></header><div class="progress-distance-tests"><article><div><span>5 km</span><strong>29:58</strong><small>Melhor teste registrado</small></div><details class="progress-benchmark-history" open><summary>Histórico · 2</summary><div><article class="progress-benchmark-history-row"><div><strong>29:58</strong><small>02/09/2026 · Competição · Informado como resultado oficial</small></div><div class="progress-benchmark-actions"><a>Editar</a><button>Excluir</button></div></article><article class="progress-benchmark-history-row"><div><strong>31:42</strong><small>12/08/2026 · Teste</small></div></article></div></details></article><article><div><span>10 km</span><strong>1:02:14</strong><small>Melhor teste registrado</small></div></article></div></section>'''
    return shell(body, theme, dialog('distance_time') if with_dialog else '')


def overview(theme='light'):
    body = '''<section class="progress-section"><header><div><h2>Destaques</h2></div></header><div class="progress-highlight-grid"><article><span>Novo 1RM medido</span><strong>87,5 kg</strong><small>Supino reto</small></article><article><span>FTP atualizado</span><strong>238 W</strong><small>+18 W · desde o anterior</small></article><article><span>Novo melhor teste de 5 km</span><strong>29:58</strong><small>−1:44 · desde o anterior</small></article></div></section>'''
    return shell(body, theme)


with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    checks = 0

    def check(ok, message):
        nonlocal_holder[0] += 1
        if not ok:
            raise AssertionError(message)

    nonlocal_holder = [0]

    def load(page, html):
        page.set_content(html)
        for path in CSS_FILES:
            page.add_style_tag(path=str(path))
        page.add_script_tag(path=str(JS))

    scenarios = [
        ('strength-light', strength('light'), 1440, 980),
        ('strength-dark', strength('dark'), 390, 844),
        ('cycling-light', cycling('light'), 1440, 900),
        ('swimming-dark', swimming('dark'), 390, 844),
        ('running-light', running('light'), 1440, 980),
        ('overview-dark', overview('dark'), 1440, 900),
    ]
    for name, html, width, height in scenarios:
        page = browser.new_page(viewport={'width':width,'height':height})
        load(page, html)
        check(page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1'), f'{name} sem overflow horizontal')
        check(page.locator('text=benchmarks.').count() == 0, f'{name} sem chave i18n vazada')
        page.screenshot(path=str(SHOT_DIR / f'{name}.png'), full_page=True)
        page.close()

    page = browser.new_page(viewport={'width':1440,'height':980})
    load(page, strength('light'))
    check(page.get_by_text('1RM medido', exact=True).count() == 1, '1RM medido aparece separado')
    check(page.get_by_text('1RM estimado', exact=True).count() == 1, 'e1RM aparece separado')
    check('70 kg × 8' in page.locator('.strength-exercise-values').inner_text(), 'e1RM mostra evidência da série')
    article_box = page.locator('.progress-strength-exercise-list > article').bounding_box()
    values_box = page.locator('.progress-strength-exercise-list .strength-exercise-values').bounding_box()
    history_box = page.locator('.progress-strength-exercise-list .progress-benchmark-history').bounding_box()
    check(article_box is not None and values_box is not None and values_box['width'] >= 420, 'métricas de força mantêm largura legível no desktop')
    check(article_box is not None and history_box is not None and history_box['width'] >= article_box['width'] - 30, 'histórico de 1RM ocupa linha própria do exercício')
    page.close()

    for kind, html in [('one-rm-dialog',strength('light',True)),('ftp-dialog',cycling('dark',True)),('css-dialog',swimming('light',True)),('run-dialog',running('dark',True))]:
        for width, height in [(1440,900),(390,844)]:
            page = browser.new_page(viewport={'width':width,'height':height})
            load(page, html)
            dlg = page.locator('[data-progress-benchmark-dialog]')
            check(dlg.count() == 1 and dlg.evaluate('el => el.open'), f'{kind} abre em {width}px')
            box = dlg.bounding_box()
            check(box is not None and box['x'] >= -1 and box['x'] + box['width'] <= width + 1, f'{kind} cabe na viewport em {width}px')
            check(page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1'), f'{kind} não cria overflow em {width}px')
            if kind == 'run-dialog':
                check('não verifica' in dlg.inner_text(), 'dialog de corrida não promete verificação oficial')
            page.screenshot(path=str(SHOT_DIR / f'{kind}-{width}.png'), full_page=True)
            page.close()

    page = browser.new_page(viewport={'width':390,'height':844})
    load(page, running('dark', True))
    dlg = page.locator('[data-progress-benchmark-dialog]')
    official = dlg.locator('[data-progress-reported-official]')
    context = dlg.locator('[data-progress-benchmark-context]')
    hidden_context = dlg.locator('[data-progress-official-context]')
    check(official.is_checked(), 'edição de resultado oficial abre com checkbox marcado')
    check(context.input_value() == 'competicao' and context.is_disabled(), 'edição oficial abre em Competição com contexto bloqueado')
    check(not hidden_context.is_disabled(), 'edição oficial mantém contexto Competição submetível mesmo com select desabilitado')
    official.uncheck()
    check(not context.is_disabled(), 'desmarcar oficial torna contexto editável novamente')
    context.select_option('treino')
    check(context.input_value() == 'treino', 'resultado não oficial pode usar Treino')
    official.check()
    check(context.input_value() == 'competicao' and context.is_disabled(), 'marcar oficial força Competição e impede manter Treino')
    check(not hidden_context.is_disabled(), 'hidden oficial permanece habilitado para POST')
    official.uncheck()
    check(not context.is_disabled(), 'desmarcar novamente libera contexto')
    check(page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1'), 'hotfix oficialidade não cria overflow em 390px')
    page.close()

    page = browser.new_page(viewport={'width':390,'height':844})
    load(page, running('dark', True))
    page.keyboard.press('Escape')
    check(not page.locator('[data-progress-benchmark-dialog]').evaluate('el => el.open'), 'Escape fecha dialog nativo')
    page.close()

    checks = nonlocal_holder[0]
    browser.close()
    print(f'✓ browser progress B1 UX: {checks} assertions; screenshots: {SHOT_DIR}')
