#!/usr/bin/env python3
from pathlib import Path
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
OPTIONAL = [
    ('fc_media', 'FC média'),
    ('fc_maxima', 'FC máxima'),
    ('cadencia', 'Cadência média'),
    ('potencia', 'Potência média'),
    ('intensidade', 'Intensidade'),
    ('sensacao', 'Sensação/observação'),
]


def duration(name, value='00:00:12', segment=False):
    seconds = value.split(':')[-1].split('.')[0]
    ms = value.split('.', 1)[1] if '.' in value else ''
    attr = ' data-segment-duration-field' if segment else ''
    return f'''<div class="input-field"{attr}><label>Duração</label><div class="duration-segments" data-duration-field>
<label><span>h</span><input data-duration-hours value="0"></label><span>:</span>
<label><span>min</span><input data-duration-minutes value="00"></label><span>:</span>
<label><span>s</span><input data-duration-seconds value="{seconds}"></label>
<span data-duration-ms-separator{' ' if ms else ' hidden'}>.</span><label data-duration-ms-wrap{' ' if ms else ' hidden'}><span>ms</span><input data-duration-milliseconds value="{ms}"></label>
<input type="hidden" name="{name}" data-duration-value value="{value}"></div></div>'''


def optional_fields(prefix):
    chunks = []
    for slug, label in OPTIONAL:
        unit = '<span data-contextual-field-unit></span>' if slug in ('cadencia', 'potencia') else ''
        chunks.append(f'''<div class="input-field dynamic-field is-optional-hidden" data-dynamic-field data-field-slug="{slug}" hidden>
<label><span data-field-label-text>{label}</span>{unit}</label><input name="{prefix}[{slug}]" value=""><button type="button" data-hide-optional-field>×</button></div>''')
    return ''.join(chunks)


def optional_bar(scope):
    buttons = ''.join(f'<button type="button" class="optional-field-chip" data-show-optional-field="{slug}">+ {label}</button>' for slug, label in OPTIONAL)
    return f'<div class="optional-fields-bar" data-optional-fields-bar data-optional-scope="{scope}"><span>Dados opcionais</span>{buttons}</div>'


def sport_select(name):
    return f'''<select name="{name}" data-unit-sport-select>
<option value="run" data-slug="corrida" data-permite-rota="1" data-derived-type="pace_km" selected>Corrida</option>
<option value="bike" data-slug="ciclismo" data-permite-rota="1" data-derived-type="speed">Ciclismo</option>
</select>'''


def segment_core(prefix):
    return f'''<div class="activity-segment-core-metrics" data-segment-core-metrics>
<div class="input-field"><label>Distância</label><input data-segment-distance name="{prefix}[distancia]"><span data-segment-distance-unit>km</span><input type="hidden" data-segment-distance-unit-value value="km"></div>
{duration(prefix + '[duracao]', '00:00:00', True)}
<div class="input-field"><label>Elevação</label><input data-segment-elevation name="{prefix}[elevacao]"></div>
<div class="input-field" data-unit-derived-field><label><span data-unit-derived-label>Ritmo</span></label><input data-unit-derived-input readonly><span data-unit-derived-unit>/km</span><span data-unit-derived-badge>AUTO</span></div>
</div>'''


def unit_template(prefix):
    return f'''<div class="activity-unit" data-unit-index="__INDEX__">
<div class="activity-unit-header"><strong data-unit-title>Trecho __NUMBER__</strong><button type="button" data-remove-unit>Remover</button></div>
<div class="activity-unit-context">{sport_select(prefix + '[__INDEX__][idmodalidade]')}<input name="{prefix}[__INDEX__][rotulo]" placeholder="Nome"></div>
{segment_core(prefix + '[__INDEX__]')}
<div class="activity-unit-grid" data-model-unit-fields>{optional_fields(prefix + '[__INDEX__][values]')}</div>
{optional_bar('unit')}
</div>'''


def html(mode):
    prefix = 'models[run][unidades]' if mode == 'create' else 'unidades'
    main_duration = f'{prefix}[0][values][duracao]'
    return f'''<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>
<div data-activity-form class="activity-editor-shell is-open"><form id="activity-form">
<section class="activity-model-panel" data-model-panel="run" data-main-modality="run" data-derived-type="pace_km" data-distance-unit="km" data-unit-kind="trecho">
<input type="hidden" data-segment-mode-input value="0">
<div class="activity-primary-unit-card" data-primary-unit-card>
<div class="activity-unit-header" data-primary-unit-header hidden><strong data-primary-unit-title>Trecho 1</strong><button type="button" data-close-segments>Fechar</button></div>
<div class="activity-unit-context" data-primary-unit-context hidden>{sport_select(prefix + '[0][idmodalidade]')}<input name="{prefix}[0][rotulo]" placeholder="Nome"></div>
<div data-primary-segment-core hidden>{segment_core(prefix + '[0]')}</div>
<div class="activity-metrics-strip" data-primary-unit data-model-unit-fields>
<div class="input-field dynamic-field" data-dynamic-field data-field-slug="duracao"><label>Duração</label>{duration(main_duration)}</div>
{optional_fields(prefix + '[0][values]')}
<div data-derived-field hidden><span data-derived-label>Ritmo</span><input data-derived-input readonly><span data-derived-unit>/km</span><span data-derived-badge>AUTO</span></div>
</div>
{optional_bar('panel')}
<div data-primary-unit-optional-wrap hidden>{optional_bar('unit')}</div>
<div data-segments-entry><button type="button" data-enable-segments>+ Trechos</button></div>
<div data-primary-unit-route-wrap hidden></div>
</div>
<div class="activity-segments-workspace" data-segments-workspace hidden>
<div class="activity-extra-units" data-units data-model="run"></div>
<template data-unit-template="run">{unit_template(prefix)}</template>
<button type="button" data-add-unit="run">Adicionar trecho</button>
</div>
</section>
</form></div></body></html>'''


def boot(page, mode):
    page.set_content(html(mode), wait_until='domcontentloaded')
    page.add_style_tag(path=str(ROOT / 'public/assets/css/atividades.css'))
    page.add_style_tag(path=str(ROOT / 'public/assets/css/ui-refresh.css'))
    page.evaluate("""() => { window.StrideBRI18n={locale:'pt-BR',t:(k,v={},f=null)=>({
        'activity.show_milliseconds':'Mostrar milissegundos','activity.hide_milliseconds':'Ocultar milissegundos',
        'activity.average_cadence':'Cadência média','activity.average_power':'Potência média','activity.steps_per_minute':'passos/min',
        'activity.use_segments':'Usar trechos','activity.optional_data':'Dados opcionais','common.optional':'Opcional'
    }[k]??f??k),tn:(a,b,c)=>c===1?a:b,number:v=>String(v),sport:(_s,f)=>f}; }""")
    page.add_script_tag(path=str(ROOT / 'public/assets/js/atividades.js'))
    page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
    page.wait_for_timeout(80)


with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    assertions = 0

    def check(value, message):
        nonlocal_holder[0] += 1
        if not value:
            raise AssertionError(message)

    nonlocal_holder = [0]
    signatures = {}
    for mode in ('create', 'edit'):
        page = browser.new_page(viewport={'width': 1280, 'height': 900})
        errors = []
        page.on('pageerror', lambda exc, errors=errors: errors.append(str(exc)))
        boot(page, mode)

        toggle = page.locator('[data-duration-precision-toggle]')
        check(toggle.count() == 1 and toggle.is_visible(), f'{mode}: um único + ms global')
        check(page.locator('[data-unit-index] [data-duration-precision-toggle]').count() == 0, f'{mode}: Trechos não possuem toggle próprio')

        panel_bar = page.locator('[data-optional-fields-bar][data-optional-scope="panel"]')
        for slug, _label in OPTIONAL:
            chip = panel_bar.locator(f'[data-show-optional-field="{slug}"]')
            field = page.locator(f'[data-primary-unit-card] [data-dynamic-field][data-field-slug="{slug}"]').first
            check(chip.is_visible(), f'{mode}: chip {slug} inicia visível')
            chip.click()
            check(field.is_visible() and not chip.is_visible(), f'{mode}: abrir {slug} esconde chip')
            input_el = field.locator('input,textarea,select').first
            input_el.fill('7')
            field.locator('[data-hide-optional-field]').click()
            check(field.is_hidden() and chip.is_visible(), f'{mode}: remover {slug} restaura chip')
            check(input_el.input_value() == '', f'{mode}: remover {slug} limpa valor conforme regra atual')

        page.locator('[data-enable-segments]').click()
        page.wait_for_timeout(40)
        check(page.locator('[data-model-panel]').evaluate("el=>el.classList.contains('is-segmented')"), f'{mode}: Trechos ativam layout segmentado')
        check(panel_bar.is_hidden(), f'{mode}: barra geral some quando Trechos estão ativos')
        check(page.locator('[data-primary-unit-optional-wrap] [data-optional-fields-bar]').is_visible(), f'{mode}: Trecho 1 recebe barra opcional própria')

        add = page.locator('[data-add-unit="run"]')
        add.click(); add.click(); page.wait_for_timeout(60)
        check(page.locator('[data-unit-index]').count() == 2, f'{mode}: dois Trechos extras criam total de três')
        check(page.locator('[data-duration-precision-toggle]').count() == 1, f'{mode}: adicionar Trechos não duplica + ms')

        toggle.click(); page.wait_for_timeout(20)
        check(page.locator('[data-duration-ms-wrap]').evaluate_all("els=>els.every(el=>!el.hidden)"), f'{mode}: + ms global habilita todas as durações')

        second = page.locator('[data-unit-index]').nth(0)
        third = page.locator('[data-unit-index]').nth(1)
        second_intensity = second.locator('[data-show-optional-field="intensidade"]')
        third_intensity = third.locator('[data-show-optional-field="intensidade"]')
        check(second_intensity.is_visible() and third_intensity.is_visible(), f'{mode}: chips opcionais são independentes por Trecho')
        second_intensity.click()
        check(not second_intensity.is_visible() and third_intensity.is_visible(), f'{mode}: abrir Intensidade no Trecho 2 não afeta Trecho 3')

        cadence = second.locator('[data-dynamic-field][data-field-slug="cadencia"]')
        power = second.locator('[data-dynamic-field][data-field-slug="potencia"]')
        second.locator('[data-show-optional-field="cadencia"]').click()
        second.locator('[data-show-optional-field="potencia"]').click()
        check(cadence.locator('[data-field-label-text]').inner_text() == 'Cadência média', f'{mode}: label de cadência é clara')
        check(cadence.locator('[data-contextual-field-unit]').inner_text() == 'passos/min', f'{mode}: corrida usa passos/min')
        check(power.locator('[data-field-label-text]').inner_text() == 'Potência média' and power.locator('[data-contextual-field-unit]').inner_text() == 'W', f'{mode}: potência média usa W')
        select = second.locator('[data-unit-sport-select]')
        select.select_option('bike'); select.dispatch_event('change'); page.wait_for_timeout(20)
        check(cadence.locator('[data-contextual-field-unit]').inner_text() == 'rpm', f'{mode}: ciclismo troca cadência para rpm')

        desktop_core = page.locator('[data-unit-index]').first.locator('[data-segment-core-metrics]')
        desktop_grid = page.locator('[data-unit-index]').first.locator('.activity-unit-grid')
        check(len(page.evaluate("el=>getComputedStyle(el).gridTemplateColumns.split(' ').filter(Boolean)", desktop_core.element_handle())) == 4, f'{mode}: desktop usa quatro faixas para métricas principais')
        check(len(page.evaluate("el=>getComputedStyle(el).gridTemplateColumns.split(' ').filter(Boolean)", desktop_grid.element_handle())) == 3, f'{mode}: opcionais usam grid compacto de três colunas')

        page.set_viewport_size({'width': 760, 'height': 900}); page.wait_for_timeout(20)
        check(len(page.evaluate("el=>getComputedStyle(el).gridTemplateColumns.split(' ').filter(Boolean)", desktop_core.element_handle())) == 2, f'{mode}: tablet/mobile largo usa duas colunas')
        page.set_viewport_size({'width': 390, 'height': 844}); page.wait_for_timeout(20)
        check(len(page.evaluate("el=>getComputedStyle(el).gridTemplateColumns.split(' ').filter(Boolean)", desktop_core.element_handle())) == 1, f'{mode}: mobile estreito usa uma coluna')

        signatures[mode] = page.evaluate("""() => ({
            panels:document.querySelectorAll('[data-model-panel]').length,
            primary:document.querySelectorAll('[data-primary-unit]').length,
            optionalBars:document.querySelectorAll('[data-optional-fields-bar]').length,
            unitTemplates:document.querySelectorAll('template[data-unit-template]').length,
            routeTargets:document.querySelectorAll('[data-unit-route-editor]').length,
            effort:document.querySelectorAll('[name="esforco_percebido"]').length
        })""")
        check(not errors, f'{mode}: sem erros JS: {errors}')
        page.close()

    check(signatures['create'] == signatures['edit'], 'CREATE e EDIT expõem a mesma estrutura de componentes no editor compartilhado')
    assertions = nonlocal_holder[0]
    browser.close()
    print(f'✓ browser activity editor parity: {assertions} assertions; CREATE/EDIT, optional fields, 3 Trechos, ms global e grids responsivos')
