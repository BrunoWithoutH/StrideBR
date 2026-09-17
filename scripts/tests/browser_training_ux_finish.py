#!/usr/bin/env python3
from pathlib import Path
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
PACER_JS = ROOT / 'public/assets/js/pacer-web.js'
PACER_CSS = ROOT / 'public/assets/css/pacer-web.css'
PRESCRIPTION_JS = ROOT / 'public/assets/js/workout-prescription.js'
WORKOUT_JS = ROOT / 'public/assets/js/workout-session.js'
STYLE_CSS = ROOT / 'public/assets/css/style.css'

assertions = 0

def check(condition, message):
    global assertions
    assertions += 1
    if not condition:
        raise AssertionError(message)

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])

    for width in (1440, 390):
        page = browser.new_page(viewport={'width': width, 'height': 820})
        page.set_content('''<!doctype html><html><body>
        <main data-pacer-page data-csrf="x">
          <div class="pacer-layout"><section class="pacer-editor">
            <div class="pacer-editor-head"><strong>Plano</strong><div class="pacer-editor-head-actions"><button type="button" class="pacer-help-button" data-pacer-help-open="overview" aria-controls="pacer-help-dialog">ⓘ <span>Como funciona?</span></button></div></div>
            <form data-pacer-form><select data-pacer-strategy name="strategy"><option value="even">Ritmo constante</option></select><input name="target_distance_km" value=""><input name="target_time" value=""><div data-generated-options></div><div data-custom-segments hidden><div data-pacer-segments></div></div><div data-pacer-preview></div></form>
          </section></div>
          <dialog class="pacer-help-dialog" id="pacer-help-dialog" data-pacer-help-dialog aria-labelledby="pacer-help-title"><div class="pacer-help-dialog-inner"><header><h2 id="pacer-help-title">Ajuda</h2><button type="button" data-pacer-help-close>×</button></header><div class="pacer-help-content"><section data-pacer-help-section="goal"><h3>Objetivo</h3></section><section data-pacer-help-section="strategy"><h3>Estratégia</h3></section><section data-pacer-help-section="advanced"><h3>Opções avançadas</h3></section></div></div></dialog>
        </main></body></html>''')
        page.add_style_tag(path=str(PACER_CSS))
        page.add_script_tag(path=str(PACER_JS))
        button = page.locator('[data-pacer-help-open]')
        dialog = page.locator('[data-pacer-help-dialog]')
        check(not dialog.is_visible(), f'{width}px: ajuda deve começar fechada')
        check(page.locator('.pacer-guide').count() == 0, f'{width}px: guia lateral permanente não pode existir')
        button.focus(); button.click()
        check(dialog.is_visible(), f'{width}px: ajuda deve abrir sob demanda')
        check(page.locator('[data-pacer-help-section="strategy"]').is_visible(), f'{width}px: seção Estratégia deve estar no dialog')
        check(page.locator('[data-pacer-help-section="advanced"]').is_visible(), f'{width}px: seção avançada deve estar no dialog')
        page.keyboard.press('Escape')
        check(not dialog.is_visible(), f'{width}px: Escape deve fechar ajuda')
        check(page.evaluate('document.activeElement === document.querySelector("[data-pacer-help-open]")'), f'{width}px: foco deve retornar ao botão de ajuda')
        check(page.evaluate('document.documentElement.scrollWidth <= innerWidth'), f'{width}px: Pacer help não pode criar overflow global')
        page.close()

    page = browser.new_page(viewport={'width': 900, 'height': 800})
    page.set_content('''<!doctype html><html data-theme="dark"><body>
      <div data-global-tools data-csrf="x">
        <button data-active-workout-pill hidden></button>
        <div data-workout-session-modal hidden><span data-session-title></span><span data-session-progress></span><span data-session-time></span><div data-session-exercises></div></div>
      </div>
      <span class="session-exercise-number" id="number-sample">1</span>
    </body></html>''')
    fixture = {
        'titulo_snapshot': 'Treino semântico',
        'started_at_ms': 1,
        'exercicios': [
            {'idsessao_exercicio':'e1','nome_snapshot':'Aeróbico','series_planejadas':1,'repeticoes_snapshot':None,'carga_snapshot':None,'duracao_snapshot':'20 min','distancia_snapshot':None,'concluido':False,'historico':{},'series':[{'idserie':'s1','numero':1,'concluida':False,'repeticoes_realizadas':None,'carga_realizada':None}]},
            {'idsessao_exercicio':'e2','nome_snapshot':'Agachamento','series_planejadas':1,'repeticoes_snapshot':'12','carga_snapshot':'40','duracao_snapshot':None,'distancia_snapshot':None,'concluido':False,'historico':{},'series':[{'idserie':'s2','numero':1,'concluida':False,'repeticoes_realizadas':None,'carga_realizada':None}]},
            {'idsessao_exercicio':'e3','nome_snapshot':'Intervalo','series_planejadas':1,'repeticoes_snapshot':None,'carga_snapshot':None,'duracao_snapshot':'5 min','distancia_snapshot':'1 km','concluido':False,'historico':{},'series':[{'idserie':'s3','numero':1,'concluida':False,'repeticoes_realizadas':None,'carga_realizada':None}]},
        ]
    }
    page.evaluate("fixture => { window.StrideBRNet={fetch:async()=>({ok:true,json:async()=>({session:fixture})})}; window.StrideBRUI={notify:()=>{}}; }", fixture)
    page.add_style_tag(path=str(STYLE_CSS))
    page.add_script_tag(path=str(PRESCRIPTION_JS))
    page.add_script_tag(path=str(WORKOUT_JS))
    page.evaluate('async()=>{await StrideBRWorkout.refresh();StrideBRWorkout.open()}')
    page.locator('.session-exercise').first.wait_for()

    cardio = page.locator('.session-exercise').nth(0)
    check('20 min' in cardio.inner_text(), 'duration-only deve mostrar 20 min')
    check('20 reps' not in cardio.inner_text().lower(), 'duration-only não pode virar 20 reps')
    check(cardio.locator('[data-session-set-reps]').count() == 0 and cardio.locator('[data-session-set-load]').count() == 0, 'duration-only não deve renderizar inputs carga/reps')
    check('DURAÇÃO' in cardio.locator('.session-set-table-head').inner_text().upper(), 'header duration-only deve ser Duração')

    strength = page.locator('.session-exercise').nth(1)
    check(strength.locator('[data-session-set-load]').count() == 1 and strength.locator('[data-session-set-reps]').count() == 1, 'LOAD_REPS deve manter inputs carga/reps')
    check('40 kg' in strength.inner_text() and '12 reps' in strength.inner_text(), 'LOAD_REPS deve formatar carga e reps')

    interval = page.locator('.session-exercise').nth(2)
    header_text = interval.locator('.session-set-table-head').inner_text().upper()
    check('DURAÇÃO' in header_text and 'DISTÂNCIA' in header_text and 'REPS' not in header_text, 'DURATION_DISTANCE deve usar headers corretos sem Reps')

    bg = page.locator('#number-sample').evaluate("e=>getComputedStyle(e).backgroundColor")
    fg = page.locator('#number-sample').evaluate("e=>getComputedStyle(e).color")
    check(bg not in ('rgb(255, 255, 255)', 'rgba(255, 255, 255, 1)'), 'indicador numérico dark não pode ter fundo branco')
    check(bg != fg, 'indicador numérico precisa manter contraste no dark')
    check(page.evaluate('document.documentElement.scrollWidth <= innerWidth'), 'execução semântica não pode criar overflow global')
    browser.close()

print(f'✓ browser training UX finish: {assertions} assertions')
