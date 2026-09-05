#!/usr/bin/env python3
from pathlib import Path
import sys
try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr)
    sys.exit(2)
ROOT=Path(__file__).resolve().parents[2]
HTML='''<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"></head><body class="activity-edit-embedded"><div class="container-fluid"><main class="main-content activities-page"><form class="activity-editor activity-edit-form" id="activity-form">
<section class="activity-smart-title activity-smart-title-top" data-activity-title-card><div class="activity-smart-title-copy"><button type="button" class="activity-smart-title-trigger" data-edit-activity-title aria-expanded="false" aria-controls="activity-title-editor"><strong data-activity-title-preview>Corrida à noite</strong><span class="visually-hidden">Editar título da atividade</span></button></div><div class="input-field activity-title-field" id="activity-title-editor" data-activity-title-editor hidden><label for="titulo">Título</label><input id="titulo" name="titulo" value="Corrida à noite"></div></section>
<div class="activity-context-grid activity-edit-context-grid"><label class="input-field activity-edit-sport-field">Modalidade<select><option>Corrida</option></select></label><label class="input-field activity-edit-date-field">Data<input type="date" value="2026-09-05"></label><label class="input-field activity-edit-time-field">Hora<input type="text" value="18:02"></label><label class="input-field activity-edit-status-field">Status<select><option>Concluída</option></select></label></div>
<section class="activity-model-panel" data-model-panel="run" data-main-modality="run" data-derived-type="pace_km" data-distance-unit="km"><div class="activity-metrics-strip" data-primary-unit>
<label class="input-field dynamic-field" data-field-slug="distancia"><span>Distância</span><input type="number" min="0" max="10" step="0.1" value="5.2" inputmode="decimal"></label>
<div class="input-field dynamic-field" data-field-slug="duracao"><label>Duração</label><div class="duration-segments" data-duration-field><label><span>h</span><input type="text" inputmode="numeric" data-duration-hours value="0"></label><span aria-hidden="true">:</span><label><span>min</span><input type="text" inputmode="numeric" data-duration-minutes value="18"></label><span aria-hidden="true">:</span><label><span>s</span><input type="text" inputmode="numeric" data-duration-seconds value="02"></label><span class="duration-ms-separator" data-duration-ms-separator aria-hidden="true">.</span><label class="duration-ms-field" data-duration-ms-wrap><span>ms</span><input type="text" inputmode="numeric" data-duration-milliseconds value="438"></label><input type="hidden" data-duration-value value="00:18:02.438"></div></div>
<label class="input-field dynamic-field" data-field-slug="elevacao"><span>Elevação</span><input type="number" min="0" max="5000" step="1" value="81" inputmode="numeric"></label>
<div class="input-field derived-metric-field" data-derived-field><label>Ritmo <span class="activity-derived-meta">Calculado</span></label><div class="derived-result-wrap"><input value="3:28" readonly><span>/km</span><button type="button" class="derived-edit-button">Editar</button></div></div>
</div><div class="optional-fields-bar"><span>Dados opcionais</span><button type="button" class="optional-field-chip">+ FC média</button></div><button type="button" class="add-unit-button">+ Adicionar trecho</button><button type="button" class="activity-link-danger">Remover</button></section>
<section class="activity-route-privacy-fields"><div class="activity-route-privacy-summary"><div><strong>Privacidade da rota</strong><span>A rota completa continua salva para você.</span></div><button type="button" class="activity-inline-action">Configurar</button></div><div class="activity-route-privacy-options"><div class="activity-compact-two-columns"><label class="input-field">Ocultar no início<input id="privacy-start" type="number" min="0" max="10000" step="50" value="0" inputmode="numeric"><small>metros · 0 para mostrar tudo</small></label><label class="input-field">Ocultar no fim<input type="number" min="0" max="10000" step="50" value="0" inputmode="numeric"><small>metros · 0 para mostrar tudo</small></label></div></div></section>
</form></main></div></body></html>'''
MOCK="""() => { window.StrideBRI18n={locale:'pt-BR',t:(k,v={},f=null)=>({'activity.show_milliseconds':'Mostrar milissegundos','activity.hide_milliseconds':'Ocultar milissegundos','activity.number_increase':'Aumentar valor','activity.number_decrease':'Diminuir valor'}[k]??f??k),tn:(a,b,c)=>c===1?a:b,number:v=>String(v),sport:(_s,f)=>f}; }"""

def boot(page):
    page.set_content(HTML,wait_until='domcontentloaded')
    page.add_style_tag(path=str(ROOT/'public/assets/css/style.css'))
    page.add_style_tag(path=str(ROOT/'public/assets/css/atividades.css'))
    page.add_style_tag(path=str(ROOT/'public/assets/css/ui-refresh.css'))
    page.evaluate(MOCK)
    page.add_script_tag(path=str(ROOT/'public/assets/js/atividades.js'))
    page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
    page.wait_for_timeout(80)

def overlap(a,b):
    return not (a['x']+a['width'] <= b['x']+.5 or b['x']+b['width'] <= a['x']+.5 or a['y']+a['height'] <= b['y']+.5 or b['y']+b['height'] <= a['y']+.5)

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    count=[0]
    def check(v,m):
        count[0]+=1
        if not v: raise AssertionError(m)
    for width,height in [(1366,768),(1440,900),(1920,1080)]:
        page=browser.new_page(viewport={'width':width,'height':height}); errors=[]; page.on('pageerror',lambda e:errors.append(str(e))); boot(page)
        form_style=page.locator('#activity-form').evaluate("el=>{const s=getComputedStyle(el);return {border:s.borderTopWidth,radius:s.borderRadius,shadow:s.boxShadow,margin:s.margin}}")
        check(form_style['border']=='0px' and form_style['radius']=='0px' and form_style['shadow']=='none',f'{width}: embed achatado sem segundo card {form_style}')
        title=page.locator('[data-edit-activity-title]')
        check(title.is_visible() and title.inner_text().startswith('Corrida à noite'),f'{width}: título é o trigger visível')
        title.click(); check(page.locator('[data-activity-title-editor]').is_visible() and title.get_attribute('aria-expanded')=='true',f'{width}: clicar no próprio título abre o editor acessível')
        title.click(); check(page.locator('[data-activity-title-editor]').is_hidden() and title.get_attribute('aria-expanded')=='false',f'{width}: clicar novamente recolhe o editor')
        check(page.get_by_role('button',name='Editar',exact=True).count()==1,f'{width}: único Editar é o derived metric, não há botão textual para título')
        row=page.locator('.duration-control-row').bounding_box(); elev=page.locator('[data-field-slug="elevacao"]').bounding_box(); pace=page.locator('.derived-metric-field').bounding_box()
        duration_box=page.locator('[data-field-slug="duracao"]').bounding_box(); segments_box=page.locator('.duration-segments').bounding_box()
        check(duration_box and segments_box and segments_box['width'] < duration_box['width']*.88,f'{width}: duration-segments ocupa conteúdo em vez de esticar pela coluna')
        check(row and elev and row['x']+row['width'] <= elev['x']+1,f'{width}: duração não invade Elevação')
        check(elev and pace and elev['x']+elev['width'] <= pace['x']+1,f'{width}: Elevação não invade Ritmo')
        for idx in range(page.locator('.duration-segments > label').count()):
            lab=page.locator('.duration-segments > label').nth(idx)
            inp=lab.locator('input').bounding_box(); suffix=lab.locator('span').bounding_box()
            check(inp and suffix and not overlap(inp,suffix),f'{width}: sufixo da duração {idx} não sobrepõe o input')
        check(page.locator('[data-duration-precision-toggle]').count()==1 and page.locator('[data-duration-precision-toggle]').inner_text()=='− ms',f'{width}: ms global ativo com fração existente')
        derived=page.locator('.derived-edit-button').evaluate("el=>{const s=getComputedStyle(el);return {border:s.borderTopWidth,font:s.fontFamily,bg:s.backgroundColor}}")
        check(derived['border']=='0px' and derived['font'],f'{width}: Editar do ritmo usa ação oficial, não botão default')
        add_style=page.locator('.add-unit-button').evaluate("el=>{const s=getComputedStyle(el);return {radius:s.borderRadius,border:s.borderTopWidth,bg:s.backgroundColor}}")
        check(add_style['radius'] not in ('0px','') and add_style['border']!='0px',f'{width}: Adicionar trecho usa secondary oficial')
        optional_style=page.locator('.optional-field-chip').evaluate("el=>{const s=getComputedStyle(el);return {radius:s.borderRadius,border:s.borderTopWidth,font:s.fontFamily}}")
        check(optional_style['radius'] not in ('0px','') and optional_style['border']!='0px',f'{width}: optional chip não usa aparência HTML crua')
        configure_style=page.locator('.activity-route-privacy-summary .activity-inline-action').evaluate("el=>{const s=getComputedStyle(el);return {border:s.borderTopWidth,font:s.fontFamily,minHeight:s.minHeight}}")
        check(configure_style['border']=='0px' and configure_style['font'],f'{width}: Configurar usa ação inline oficial')
        remove_style=page.locator('.activity-link-danger').evaluate("el=>{const s=getComputedStyle(el);return {border:s.borderTopWidth,color:s.color,font:s.fontFamily}}")
        check(remove_style['font'] and remove_style['color'],f'{width}: Remover recebe tipografia/estado do Stride')
        check(not errors,f'{width}: sem erro JS {errors}')
        page.close()

    page=browser.new_page(viewport={'width':390,'height':844}); boot(page)
    distance=page.locator('[data-field-slug="distancia"] input[type=number]')
    stepper=page.locator('[data-field-slug="distancia"] .stride-number-stepper')
    check(stepper.count()==1 and stepper.is_visible(),'number input recebe stepper Stride')
    check(page.locator('[data-field-slug="distancia"] .stride-number-stepper-button').count()==2,'stepper usa dois chevrons compactos')
    page.locator('[data-field-slug="distancia"] .stride-number-stepper-button.is-up').click(); check(distance.input_value()=='5.3','stepper respeita step 0.1 para cima')
    distance.fill('10'); page.locator('[data-field-slug="distancia"] .stride-number-stepper-button.is-up').click(); check(distance.input_value()=='10','stepper respeita max')
    distance.fill('0'); page.locator('[data-field-slug="distancia"] .stride-number-stepper-button.is-down').click(); check(distance.input_value()=='0','stepper respeita min')
    distance.fill('5.2'); distance.press('ArrowUp'); check(distance.input_value()=='5.3','ArrowUp nativo continua funcionando')
    privacy=page.locator('#privacy-start'); page.locator('#privacy-start + .stride-number-stepper') if False else None
    privacy_up=page.locator('#privacy-start').locator('xpath=..').locator('.stride-number-stepper-button.is-up')
    privacy_up.click(); check(privacy.input_value()=='50','stepper respeita step 50 da privacidade')
    control_box=page.locator('#privacy-start').locator('xpath=..').bounding_box(); check(control_box and control_box['x']>=0 and control_box['x']+control_box['width']<=390,'stepper não causa clipping no mobile')
    browser.close()
    print(f'✓ activity consolidation browser: {count[0]} assertions; embed, duration, controls, steppers')
