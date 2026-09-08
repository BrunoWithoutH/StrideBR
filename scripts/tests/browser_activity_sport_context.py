#!/usr/bin/env python3
from pathlib import Path
import json
import subprocess
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
php = "require '%s'; require '%s'; echo json_encode(atividadeContextoEsportivoConfig());" % (
    str(ROOT / 'src/includes/app.php').replace("'", "\\'"),
    str(ROOT / 'src/function/activity_sport_context.php').replace("'", "\\'"),
)
CONFIG = subprocess.check_output(['php', '-r', php], text=True)


def duration_markup():
    return '''<div class="input-field dynamic-field" data-dynamic-field data-field-slug="duracao"><label>Duração</label><div class="duration-segments" data-duration-field><label><span>h</span><input data-duration-hours value="0"></label><span>:</span><label><span>min</span><input data-duration-minutes value="00"></label><span>:</span><label><span>s</span><input data-duration-seconds value="00"></label><span data-duration-ms-separator hidden>.</span><label data-duration-ms-wrap hidden><span>ms</span><input data-duration-milliseconds value=""></label><input type="hidden" data-duration-value value="00:00:00"></div></div>'''


def distance_markup(value='', canonical='km'):
    return f'''<div class="input-field dynamic-field" data-dynamic-field data-field-slug="distancia" data-distance-canonical-unit="{canonical}" data-distance-display-unit="{canonical}"><label>Distância <select data-distance-unit-select><option value="m">m</option><option value="km" selected>km</option></select><span data-distance-unit-label hidden>{canonical}</span></label><input type="number" step="any" value="{value}"></div>'''


def panel(model, modality, slug, value=''):
    return f'''<section data-model-panel="{model}" data-main-modality="{modality}" data-sport-slug="{slug}" data-sport-family="{'athletics' if slug.startswith('atletismo-') else 'cardio'}" data-derived-type="pace_km" data-unit-kind="trecho" hidden><input type="hidden" data-segment-mode-input value="0"><div data-primary-unit-card><div data-primary-unit>{distance_markup(value)}{duration_markup()}<div class="input-field dynamic-field" data-dynamic-field data-field-slug="fc_media"><label>FC média</label><input type="number"></div><div data-derived-field hidden><span data-derived-label>Ritmo</span><input data-derived-input readonly><span data-derived-unit>/km</span></div></div><div data-segments-entry><button type="button" data-enable-segments>+ Trechos</button></div></div><div data-segments-workspace hidden><div data-units data-model="{model}"></div></div></section>'''


def editor_html(initial='run', values=None, lang='pt-BR'):
    values = values or {}
    modalities = [
        ('run', 'corrida', 'Corrida', 'cardio'),
        ('s100', 'atletismo-100m', '100 m', 'athletics'),
        ('s400', 'atletismo-400m', '400 m', 'athletics'),
        ('s1500', 'atletismo-1500m', '1500 m', 'athletics'),
    ]
    options = ''.join(f'<option value="{mid}" data-slug="{slug}" data-family="{family}" data-permite-rota="1"' + (' selected' if mid == initial else '') + f'>{name}</option>' for mid, slug, name, family in modalities)
    models = ''.join(f'<option value="m-{mid}" data-modalidade="{mid}"' + (' selected' if mid == initial else '') + f'>Modelo {name}</option>' for mid, slug, name, family in modalities)
    panels = ''.join(panel(f'm-{mid}', mid, slug, values.get(mid, '')) for mid, slug, name, family in modalities)
    return f'''<!doctype html><html lang="{lang}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><script type="application/json" id="activity-sport-context-config">{CONFIG}</script></head><body><div data-activity-form><form id="activity-form"><select id="modalidade">{options}</select><div data-model-field><select id="modelo">{models}</select></div>{panels}<div data-summary-label></div><div data-summary-text></div></form></div></body></html>'''


def segment_distance(prefix, value='', unit='km'):
    return f'''<div data-segment-distance-field data-distance-display-unit="{unit}"><label>Distância <select data-segment-distance-unit-select><option value="m"{' selected' if unit == 'm' else ''}>m</option><option value="km"{' selected' if unit == 'km' else ''}>km</option></select><span data-segment-distance-unit hidden>{unit}</span></label><input type="number" step="any" name="{prefix}[distancia]" value="{value}" data-segment-distance><input type="hidden" name="{prefix}[distancia_unidade]" value="{unit}" data-segment-distance-unit-value></div>'''


def segment_duration(prefix):
    return f'''<div data-segment-duration-field><div data-duration-field><input data-duration-hours value="0"><input data-duration-minutes value="00"><input data-duration-seconds value="00"><span data-duration-ms-separator hidden>.</span><label data-duration-ms-wrap hidden><input data-duration-milliseconds></label><input type="hidden" name="{prefix}[duracao]" data-duration-value value="00:00:00"></div></div>'''


def segment_html(slug='atletismo-400m'):
    family = 'athletics' if slug.startswith('atletismo-') else 'cardio'
    primary = segment_distance('units[0]', '', 'km') + segment_duration('units[0]')
    template = segment_distance('units[__INDEX__]', '', 'km') + segment_duration('units[__INDEX__]')
    return f'''<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><script type="application/json" id="activity-sport-context-config">{CONFIG}</script></head><body><div data-activity-form><form id="activity-form"><select id="modalidade"><option value="sport" data-slug="{slug}" data-family="{family}" selected>Sport</option></select><select id="modelo"><option value="model" data-modalidade="sport" selected>Model</option></select><section data-model-panel="model" data-main-modality="sport" data-sport-slug="{slug}" data-sport-family="{family}" data-derived-type="pace_km" data-unit-kind="trecho"><input type="hidden" data-segment-mode-input value="1"><div data-primary-unit-card><div data-primary-unit-header></div><div data-primary-unit-context></div><div data-primary-segment-core>{primary}<div data-unit-derived-field hidden><span data-unit-derived-label>Ritmo</span><input data-unit-derived-input><span data-unit-derived-unit>/km</span><span data-unit-derived-badge>AUTO</span></div></div><div data-primary-unit></div></div><div data-segments-workspace><div data-units data-model="model"></div><template data-unit-template="model"><article data-unit-index="__INDEX__"><div data-unit-title></div><div data-segment-core-metrics>{template}<div data-unit-derived-field hidden><span data-unit-derived-label>Ritmo</span><input data-unit-derived-input><span data-unit-derived-unit>/km</span><span data-unit-derived-badge>AUTO</span></div></div></article></template><button type="button" data-add-unit="model">Adicionar trecho</button></div></section><div data-summary-label></div><div data-summary-text></div></form></div></body></html>'''


def boot(page, html, share=False):
    page.set_content(html, wait_until='domcontentloaded')
    page.evaluate("""() => { window.StrideBRI18n={locale:document.documentElement.lang.startsWith('en')?'en':'pt-BR',dictionary:{},t:(k,v={},f=null)=>f??k,tn:(a,b,c)=>c===1?a:b,number:(v,d=0,trim=false)=>new Intl.NumberFormat(document.documentElement.lang.startsWith('en')?'en-US':'pt-BR',{minimumFractionDigits:trim?0:d,maximumFractionDigits:d}).format(Number(v)),sport:(_s,f)=>f}; window.__STRIDEBR_SHARE_TEST_HOOKS__=%s }""" % ('true' if share else 'false'))
    page.add_script_tag(path=str(ROOT / 'public/assets/js/activity-sport-context.js'))
    page.add_script_tag(path=str(ROOT / 'public/assets/js/atividades.js'))
    page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
    page.wait_for_timeout(100)


with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    assertions = 0
    def check(value, message):
        global assertions
        assertions += 1
        if not value:
            raise AssertionError(message)

    page = browser.new_page(viewport={'width': 1100, 'height': 850})
    errors = []
    page.on('pageerror', lambda exc: errors.append(str(exc)))
    boot(page, editor_html('run', {'run':'0.823'}))
    generic = page.locator('[data-model-panel="m-run"] [data-dynamic-field][data-field-slug="distancia"]')
    check(generic.locator('input[type="number"]').input_value() == '823', 'Corrida comum 823 m abre em metros')
    check(generic.locator('[data-distance-unit-select]').input_value() == 'm', 'Corrida comum 823 m usa m')
    generic.locator('input[type="number"]').fill('1500')
    generic.locator('input[type="number"]').blur()
    page.wait_for_timeout(30)
    check(generic.locator('input[type="number"]').input_value() == '1.5' and generic.locator('[data-distance-unit-select]').input_value() == 'km', 'Corrida comum 1500 m normaliza para 1,5 km após blur')

    page.locator('#modalidade').select_option('s100')
    page.locator('#modalidade').dispatch_event('change')
    page.wait_for_timeout(40)
    d100 = page.locator('[data-model-panel="m-s100"] [data-dynamic-field][data-field-slug="distancia"]')
    check(d100.locator('input[type="number"]').input_value() == '100' and d100.locator('[data-distance-unit-select]').input_value() == 'm', '100 m preenche distância nominal em m')
    toggle = page.locator('[data-duration-precision-toggle]')
    check(toggle.count() == 1 and toggle.inner_text() == '− ms', '100 m sugere ms')
    toggle.click()
    check(toggle.inner_text() == '+ ms', 'usuário pode desligar ms')
    d100.locator('input[type="number"]').fill('100.2')
    d100.locator('input[type="number"]').blur()
    page.locator('[data-model-panel="m-s100"] [data-field-slug="fc_media"] input').fill('175')
    page.wait_for_timeout(30)
    check(toggle.inner_text() == '+ ms', 'editar distância e FC não reativa ms após decisão manual')

    page.locator('#modalidade').select_option('s400')
    page.locator('#modalidade').dispatch_event('change')
    page.wait_for_timeout(40)
    d400 = page.locator('[data-model-panel="m-s400"] [data-dynamic-field][data-field-slug="distancia"]')
    check(d400.locator('input[type="number"]').input_value() == '400' and d400.locator('[data-distance-unit-select]').input_value() == 'm', '400 m nominal usa m')
    check(toggle.inner_text() == '− ms', 'mudança semântica para 400 m recalcula default de ms')
    unit400 = d400.locator('[data-distance-unit-select]')
    unit400.select_option('km')
    unit400.dispatch_event('change')
    page.wait_for_timeout(20)
    check(d400.locator('input[type="number"]').input_value() == '0.4', '400 m vira 0,4 km em override manual')
    page.locator('[data-model-panel="m-s400"] [data-field-slug="fc_media"] input').fill('180')
    d400.locator('input[type="number"]').blur()
    check(unit400.input_value() == 'km' and d400.locator('input[type="number"]').input_value() == '0.4', 'override km sobrevive a mudanças não semânticas')
    unit400.select_option('m')
    unit400.dispatch_event('change')
    check(d400.locator('input[type="number"]').input_value() == '400', '0,4 km volta para 400 m sem perda')

    page.locator('#modalidade').select_option('s1500')
    page.locator('#modalidade').dispatch_event('change')
    page.wait_for_timeout(40)
    d1500 = page.locator('[data-model-panel="m-s1500"] [data-dynamic-field][data-field-slug="distancia"]')
    check(d1500.locator('input[type="number"]').input_value() == '1500' and d1500.locator('[data-distance-unit-select]').input_value() == 'm', '1500 m pista permanece em metros')
    check(toggle.inner_text() == '+ ms', '1500 m pista não força ms por default')
    check(not errors, f'editor sem erros JS: {errors}')
    page.close()

    page = browser.new_page(viewport={'width': 1100, 'height': 850})
    errors = []
    page.on('pageerror', lambda exc: errors.append(str(exc)))
    boot(page, editor_html('s400', {'s400':'0.4017'}))
    edit400 = page.locator('[data-model-panel="m-s400"] [data-dynamic-field][data-field-slug="distancia"]')
    check(edit400.locator('[data-distance-unit-select]').input_value() == 'm', 'Editar 400 m existente abre em m')
    check(edit400.locator('input[type="number"]').input_value() == '401.7', 'Editar preserva distância real 401,7 m e não sobrescreve por nominal')
    check(page.locator('[data-duration-precision-toggle]').inner_text() == '− ms', 'Editar 400 m usa mesma política de precisão')
    check(not errors, f'editar sem erros JS: {errors}')
    page.close()

    page = browser.new_page(viewport={'width': 1100, 'height': 900})
    errors = []
    page.on('pageerror', lambda exc: errors.append(str(exc)))
    boot(page, segment_html('atletismo-400m'))
    primary = page.locator('[data-primary-unit-card] [data-segment-distance-field]')
    check(primary.locator('[data-segment-distance-unit-select]').input_value() == 'm' and primary.locator('[data-segment-distance]').input_value() == '400', 'Trecho 1 de 400 m herda contexto nominal')
    for _ in range(5):
        page.locator('[data-add-unit="model"]').click()
        page.wait_for_timeout(15)
    fields = page.locator('[data-segment-distance-field]')
    check(fields.count() == 6, '6 × 400 cria seis Trechos')
    check(fields.evaluate_all("els=>els.every(el=>el.querySelector('[data-segment-distance-unit-select]').value==='m')"), '6 × 400 mantém unidade m em todos os Trechos')
    check(fields.evaluate_all("els=>els.every(el=>el.querySelector('[data-segment-distance]').value==='400')"), '6 × 400 herda distância nominal/comum')
    check(not errors, f'Trechos sem erros JS: {errors}')
    page.close()

    page = browser.new_page(viewport={'width': 900, 'height': 700})
    errors = []
    page.on('pageerror', lambda exc: errors.append(str(exc)))
    boot(page, '<!doctype html><html lang="pt-BR"><head><script type="application/json" id="activity-sport-context-config">'+CONFIG+'</script></head><body></body></html>', share=True)
    share = page.evaluate("""() => ({
        a:StrideBRShareTest.formatDistance(100,{modalidade_slug:'atletismo-100m'}),
        b:StrideBRShareTest.formatDistance(400,{modalidade_slug:'atletismo-400m'}),
        c:StrideBRShareTest.formatDistance(1500,{modalidade_slug:'atletismo-1500m'}),
        d:StrideBRShareTest.formatDistance(1500,{modalidade_slug:'corrida'}),
        e:StrideBRShareTest.formatDistance(3145,{modalidade_slug:'corrida'})
    })""")
    check(share == {'a':'100 m','b':'400 m','c':'1.500 m','d':'1,5 km','e':'3,145 km'}, f'Share reutiliza política esportiva semântica: {share}')
    check(not errors, f'Share sem erros JS: {errors}')
    page.close()

    browser.close()
    print(f'✓ browser activity sport context: {assertions} assertions; corrida, pista, override, ms, editar, Trechos e Share')
