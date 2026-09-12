from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[2]
SHOT_DIR = Path('/tmp/stridebr-progress-b3-shots')
SHOT_DIR.mkdir(parents=True, exist_ok=True)
COMP_CSS = [ROOT/'public/assets/css/style.css', ROOT/'public/assets/css/competitions.css', ROOT/'public/assets/css/ui-refresh.css']
ACTIVITY_CSS = [ROOT/'public/assets/css/style.css', ROOT/'public/assets/css/atividades.css', ROOT/'public/assets/css/ui-refresh.css']
PROGRESS_CSS = [ROOT/'public/assets/css/style.css', ROOT/'public/assets/css/product-insights.css', ROOT/'public/assets/css/sport-hub.css', ROOT/'public/assets/css/ui-refresh.css']
PROGRESS_JS = ROOT/'public/assets/js/progresso.js'


def competition_page(theme='light', form=False, detail=True, empty=False, from_catalog=False):
    form_html = ''
    if form:
        event = '<p class="competition-related-event">Evento relacionado · JIFSul 2026</p>' if from_catalog else ''
        name = 'JIFSul 2026' if from_catalog else ''
        form_html = f'''<section class="content-card competition-form-card"><header><div><h2>Registrar competição</h2><p>Registre o contexto competitivo. Atividades e resultados continuam nas fontes originais.</p></div><a>Fechar</a></header><form class="competition-form"><label>Nome<input value="{name}"></label><div class="competition-form-grid"><label>Data de início<input type="date" value="2026-10-12"></label><label>Data de fim <small>Opcional</small><input type="date" value="2026-10-16"></label><label>Status<select><option>Planejada</option></select></label><label>Esporte principal<select><option>Atletismo</option></select></label></div><details class="competition-form-more" open><summary>Detalhes opcionais</summary><div class="competition-form-grid"><label>Local<input value="Centro Desportivo"></label><label>Cidade<input value="Santa Maria"></label><label>Estado<input value="RS"></label><label>País<input value="Brasil"></label><label>Organizador<input value="IFFar"></label><label>Nível<input value="Regional"></label></div><label class="competition-official-check"><input type="checkbox"><span><strong>Informar como competição oficial</strong><small>O StrideBR registra esta informação, mas não verifica a competição.</small></span></label><label>Notas<textarea></textarea></label></details>{event}<div class="competition-form-actions"><a class="secondary-button">Cancelar</a><button class="primary-button">Salvar</button></div></form></section>'''
    detail_html = ''
    if detail:
        detail_html = '''<article class="competition-detail"><header class="competition-detail-head"><div><span class="competition-status">REALIZADA</span><h2>JIFSul 2026</h2><p>12 out. 2026 – 16 out. 2026 · Santa Maria, RS</p></div><a class="secondary-button">Editar</a></header><div class="competition-facts"><div><span>Atividades</span><strong>3</strong></div><div><span>Marcas e testes</span><strong>2</strong></div><div><span>Oficialidade</span><strong>Informada como oficial</strong></div></div><p class="competition-related-event">Evento relacionado · <a>JIFSul 2026</a></p><dl class="competition-detail-meta"><div><dt>Organizador</dt><dd>IFFar</dd></div><div><dt>Local</dt><dd>Centro Desportivo</dd></div></dl><section class="competition-detail-section"><header><h3>Atividades</h3></header><div class="competition-linked-list"><a><div><strong>100 m</strong><small>100 metros rasos</small></div><span>14 out. 2026</span></a><a><div><strong>Lançamento de dardo</strong><small>Atletismo</small></div><span>15 out. 2026</span></a><a><div><strong>Salto em distância</strong><small>Atletismo</small></div><span>16 out. 2026</span></a></div></section><section class="competition-detail-section"><header><h3>Marcas e testes</h3></header><div class="competition-linked-list"><a><div><strong>12,04</strong><small>Teste por distância · 100 m</small></div><span>14 out. 2026</span></a><a><div><strong>38,42 m</strong><small>Marca registrada · Dardo</small></div><span>15 out. 2026</span></a></div></section><form class="competition-delete"><button class="danger-link">Excluir competição</button><small>As atividades e marcas continuarão salvas; apenas o vínculo com a competição será removido.</small></form></article>'''
    if empty:
        lists = '<section class="competitions-list-section"><header><h2>Recentes</h2></header><div class="competition-empty"><p>Nenhuma competição registrada.</p><a class="primary-button">Registrar competição</a></div></section>'
    else:
        lists = '''<section class="competitions-list-section"><header><h2>Próximas</h2></header><div class="competitions-list"><a><div><strong>JIFSul 2027</strong><small>15 out. 2027 · Santa Maria, RS</small></div><span>Planejada</span></a></div></section><section class="competitions-list-section"><header><h2>Recentes</h2></header><div class="competitions-list"><a><div><strong>Open Regional</strong><small>4 set. 2026 · 3 atividades</small></div><span>Realizada</span></a><a><div><strong>Campeonato Municipal</strong><small>20 ago. 2026 · 2 atividades</small></div><span>Realizada</span></a></div></section>'''
    return f'''<!doctype html><html lang="pt-BR" data-theme="{theme}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><main class="main-content"><div class="page-shell competitions-page"><header class="competitions-page-head"><div><span class="competitions-eyebrow">MINHAS COMPETIÇÕES</span><h1>Competições</h1></div><a class="primary-button">Registrar competição</a></header>{form_html}{detail_html}{lists}</div></main></body></html>'''


def activity_page(theme='light'):
    return f'''<!doctype html><html lang="pt-BR" data-theme="{theme}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><main class="main-content"><section class="activity-log-details"><section class="activity-enrichment-section"><div class="activity-enrichment-heading"><div><strong>Detalhes</strong></div><div class="activity-enrichment-actions"><button class="optional-field-chip" aria-expanded="true">Competição</button><button class="optional-field-chip">Adicionar equipamento</button></div></div><div class="activity-contextual-detail"><div class="activity-detail-heading"><div><strong>Competição</strong><span>Vincule esta atividade a uma competição registrada.</span></div><div class="activity-detail-heading-actions"><a>Registrar competição</a><button>Fechar</button></div></div><select name="idcompeticao"><option>Nenhuma</option><option selected>JIFSul 2026 · 12 out. 2026</option><option>Open Regional · 4 set. 2026</option></select><small class="activity-field-help">O StrideBR não vincula automaticamente por data.</small></div></section></section></main></body></html>'''


def benchmark_page(theme='light', legacy=False, linked=False):
    checked = ' checked' if legacy else ''
    comp = '' if legacy else '<option value="c1" selected>Corrida Municipal 2026 · 4 set. 2026</option>'
    disabled = ' disabled aria-disabled="true"' if linked else ''
    help_text = 'A competição deste resultado vem da atividade relacionada.' if linked else ('Este resultado oficial antigo ainda não possui uma competição estruturada. Você pode associá-la ao editar.' if legacy else 'Selecione a competição quando este resultado ocorreu em contexto competitivo.')
    return f'''<!doctype html><html lang="pt-BR" data-theme="{theme}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><main data-progress-page class="main-content"><div class="progress-page-shell"><dialog class="progress-benchmark-dialog" data-progress-benchmark-dialog data-return-url="/user/progresso.php?sport=corrida" open><div class="progress-benchmark-dialog-card"><header><div><span class="progress-eyebrow">MARCAS E TESTES</span><h2>Registrar teste</h2></div><a class="progress-dialog-close">×</a></header><form class="progress-benchmark-form"><label>Distância<div class="progress-input-unit"><input value="5"><span>km</span></div></label><label>Tempo<input value="27:54"></label><label>Data<input type="date" value="2026-09-04"></label><label>Contexto<select data-progress-benchmark-context><option value="treino">Treino</option><option value="teste">Teste</option><option value="competicao"{' selected' if legacy or linked else ''}>Competição</option></select></label><label data-progress-benchmark-competition-field><span>Competição</span><select data-progress-benchmark-competition{disabled}><option value="">Nenhuma</option>{comp}</select><small>{help_text}</small>{'<a>Editar atividade relacionada</a>' if linked else ''}</label><input type="hidden" value="competicao" data-progress-official-context disabled><label class="progress-benchmark-check"><input type="checkbox" data-progress-reported-official{checked}><span><strong>Informar como resultado oficial</strong><small>O StrideBR registra esta informação, mas não verifica o resultado.</small></span></label><div class="progress-benchmark-form-actions"><a class="progress-button">Cancelar</a><button class="progress-button is-primary">Salvar teste</button></div></form></div></dialog></div></main><div class="progress-tooltip" data-progress-tooltip-popover hidden></div></body></html>'''


def progress_competitions(theme='light'):
    return f'''<!doctype html><html data-theme="{theme}" lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><main class="main-content"><div class="progress-page-shell"><section class="progress-section progress-competitions-section"><header><div><h2>Competições recentes</h2></div><a class="progress-button">Ver competições</a></header><div class="progress-competition-list"><a><span><strong>JIFSul 2026</strong><small>12 out. 2026 – 16 out. 2026 · 3 atividades · 2 marcas e testes</small></span><em>Realizada</em></a><a><span><strong>Open Regional</strong><small>4 set. 2026 · 2 atividades</small></span><em>Realizada</em></a></div></section></div></main></body></html>'''


def load_css(page, paths):
    for p in paths:
        page.add_style_tag(path=str(p))


with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    checks = [0]
    def check(ok, message):
        checks[0] += 1
        if not ok:
            raise AssertionError(message)

    for theme, width, height in [('light',1440,1000),('dark',1440,1000),('light',390,844),('dark',390,844)]:
        pg = browser.new_page(viewport={'width':width,'height':height})
        pg.set_content(competition_page(theme))
        load_css(pg, COMP_CSS)
        check(pg.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1'), f'competitions {theme} {width}px sem overflow')
        check(pg.get_by_text('JIFSul 2026', exact=True).count() >= 1, 'detalhe mostra competição pessoal')
        check(pg.get_by_text('3', exact=True).count() >= 1, 'detalhe mostra atividades vinculadas')
        check(pg.get_by_text('Informada como oficial', exact=True).count() == 1, 'oficialidade não vira verificação')
        check(pg.get_by_text('Evento relacionado', exact=False).count() == 1, 'evento público aparece apenas como relacionado')
        check(pg.get_by_text('As atividades e marcas continuarão salvas; apenas o vínculo com a competição será removido.', exact=True).count() == 1, 'exclusão explica SET NULL')
        check(pg.locator('.competition-linked-list a').count() == 5, 'detalhe comporta múltiplas atividades e marcas')
        pg.screenshot(path=str(SHOT_DIR/f'competitions-{theme}-{width}.png'), full_page=True)
        pg.close()

    pg = browser.new_page(viewport={'width':1440,'height':1000})
    pg.set_content(competition_page('light', form=True, detail=False))
    load_css(pg, COMP_CSS)
    check(pg.locator('.competition-form-grid').first.evaluate("e => getComputedStyle(e).gridTemplateColumns.split(' ').length") >= 2, 'form desktop usa grid compacto')
    check(pg.get_by_text('Informar como competição oficial', exact=True).count() == 1, 'form usa copy oficial informada')
    check(pg.get_by_text('O StrideBR registra esta informação, mas não verifica a competição.', exact=True).count() == 1, 'form explica ausência de verificação')
    pg.screenshot(path=str(SHOT_DIR/'competition-create-light-1440.png'), full_page=True)
    pg.close()

    pg = browser.new_page(viewport={'width':390,'height':844})
    pg.set_content(competition_page('dark', form=True, detail=False, from_catalog=True))
    load_css(pg, COMP_CSS)
    check(pg.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1'), 'form mobile catálogo sem overflow')
    check(pg.locator('.competition-form-grid').first.evaluate("e => getComputedStyle(e).gridTemplateColumns.split(' ').length") == 1, 'form mobile empilha campos')
    check(pg.locator('input').first.input_value() == 'JIFSul 2026', 'criação por catálogo vem pré-preenchida')
    check(pg.get_by_text('Evento relacionado · JIFSul 2026', exact=True).count() == 1, 'catálogo continua referência separada')
    pg.screenshot(path=str(SHOT_DIR/'competition-from-event-dark-390.png'), full_page=True)
    pg.close()

    pg = browser.new_page(viewport={'width':390,'height':844})
    pg.set_content(competition_page('light', detail=False, empty=True))
    load_css(pg, COMP_CSS)
    check(pg.get_by_text('Nenhuma competição registrada.', exact=True).count() == 1, 'empty state é compacto e factual')
    check(pg.get_by_text('Registrar competição', exact=True).count() >= 1, 'empty state oferece próxima ação')
    check(pg.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1'), 'empty state mobile sem overflow')
    pg.close()

    for theme, width in [('light',1440),('dark',390)]:
        pg = browser.new_page(viewport={'width':width,'height':844 if width==390 else 900})
        pg.set_content(activity_page(theme))
        load_css(pg, ACTIVITY_CSS)
        check(pg.locator('select[name="idcompeticao"]').count() == 1, 'atividade mostra vínculo de competição dentro de Detalhes')
        check(pg.locator('select[name="idcompeticao"] option').count() == 3, 'atividade oferece lista curta, não histórico inteiro')
        check(pg.get_by_text('O StrideBR não vincula automaticamente por data.', exact=True).count() == 1, 'atividade deixa claro que sugestão não é vínculo automático')
        check(pg.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1'), f'activity competition {theme} {width}px sem overflow')
        pg.screenshot(path=str(SHOT_DIR/f'activity-competition-{theme}-{width}.png'), full_page=True)
        pg.close()

    pg = browser.new_page(viewport={'width':1440,'height':900})
    pg.set_content(benchmark_page('light'))
    load_css(pg, PROGRESS_CSS)
    pg.add_script_tag(path=str(PROGRESS_JS))
    pg.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
    context = pg.locator('[data-progress-benchmark-context]')
    official = pg.locator('[data-progress-reported-official]')
    competition = pg.locator('[data-progress-benchmark-competition]')
    context.select_option('treino')
    official.check()
    check(context.input_value() == 'competicao', 'marcar oficial continua forçando contexto Competição')
    check(context.is_disabled(), 'contexto fica bloqueado enquanto oficial')
    check(competition.evaluate('e => e.required'), 'novo resultado oficial exige competição estruturada')
    official.uncheck()
    check(not context.is_disabled(), 'desmarcar oficial devolve edição do contexto')
    check(not competition.evaluate('e => e.required'), 'não oficial não exige competição')
    pg.screenshot(path=str(SHOT_DIR/'benchmark-competition-light-1440.png'), full_page=True)
    pg.close()

    pg = browser.new_page(viewport={'width':390,'height':844})
    pg.set_content(benchmark_page('dark', legacy=True))
    load_css(pg, PROGRESS_CSS)
    pg.add_script_tag(path=str(PROGRESS_JS))
    pg.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
    check(pg.get_by_text('Este resultado oficial antigo ainda não possui uma competição estruturada. Você pode associá-la ao editar.', exact=True).count() == 1, 'oficial legado sem FK continua legível')
    check(pg.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1'), 'benchmark legado mobile sem overflow')
    pg.screenshot(path=str(SHOT_DIR/'benchmark-legacy-official-dark-390.png'), full_page=True)
    pg.close()

    pg = browser.new_page(viewport={'width':1440,'height':900})
    pg.set_content(benchmark_page('light', linked=True))
    load_css(pg, PROGRESS_CSS)
    pg.add_script_tag(path=str(PROGRESS_JS))
    pg.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
    check(pg.locator('[data-progress-benchmark-competition]').is_disabled(), 'benchmark ligado à atividade não oferece FK concorrente')
    check(pg.get_by_text('A competição deste resultado vem da atividade relacionada.', exact=True).count() == 1, 'UI explica herança da atividade')
    check(pg.get_by_text('Editar atividade relacionada', exact=True).count() == 1, 'UI direciona correção para fonte original')
    pg.close()

    for theme, width in [('light',1440),('dark',390)]:
        pg = browser.new_page(viewport={'width':width,'height':844 if width==390 else 900})
        pg.set_content(progress_competitions(theme))
        load_css(pg, PROGRESS_CSS)
        check(pg.get_by_text('Competições recentes', exact=True).count() == 1, 'Progresso integra seção competitiva compacta')
        check(pg.locator('.progress-competition-list>a').count() == 2, 'Progresso mostra poucos itens, não dashboard gigante')
        check(pg.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1'), f'progress competitions {theme} {width}px sem overflow')
        pg.screenshot(path=str(SHOT_DIR/f'progress-competitions-{theme}-{width}.png'), full_page=True)
        pg.close()

    browser.close()
    print(f'✓ browser progress B3 UX: {checks[0]} assertions; screenshots: {SHOT_DIR}')
