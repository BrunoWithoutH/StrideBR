#!/usr/bin/env python3
from pathlib import Path
import sys
try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr); sys.exit(2)
ROOT=Path(__file__).resolve().parents[2]

def html(ms=''):
    canonical='00:00:12'+(f'.{ms}' if ms else '')
    return f'''<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><form id="activity-form">
    <section data-primary-unit><div class="input-field dynamic-field"><label>Duração</label><div class="duration-segments" data-duration-field><label><span>h</span><input data-duration-hours value="0"></label><span>:</span><label><span>min</span><input data-duration-minutes value="00"></label><span>:</span><label><span>s</span><input data-duration-seconds value="12"></label><span data-duration-ms-separator{'' if ms else ' hidden'}>.</span><label data-duration-ms-wrap{'' if ms else ' hidden'}><span>ms</span><input data-duration-milliseconds value="{ms}"></label><input type="hidden" name="duracao" data-duration-value value="{canonical}"></div></div></section>
    <section data-segment><div class="duration-segments" data-duration-field><label><input data-duration-hours value="0"></label><span>:</span><label><input data-duration-minutes value="00"></label><span>:</span><label><input data-duration-seconds value="10"></label><span data-duration-ms-separator hidden>.</span><label data-duration-ms-wrap hidden><input data-duration-milliseconds></label><input type="hidden" name="trecho_duracao" data-duration-value value="00:00:10"></div></section>
    </form></body></html>'''

def mock(locale):
    show='Mostrar milissegundos' if locale=='pt-BR' else 'Show milliseconds'
    hide='Ocultar milissegundos' if locale=='pt-BR' else 'Hide milliseconds'
    return f"""() => {{ window.StrideBRI18n={{locale:'{locale}',t:(k,v={{}},f=null)=>({{'activity.show_milliseconds':'{show}','activity.hide_milliseconds':'{hide}'}}[k]??f??k),tn:(a,b,c)=>c===1?a:b,number:v=>String(v),sport:(_s,f)=>f}} }}"""

def boot(page, locale='pt-BR', ms=''):
    page.set_content(html(ms),wait_until='domcontentloaded')
    page.add_style_tag(path=str(ROOT/'public/assets/css/atividades.css'))
    page.add_style_tag(path=str(ROOT/'public/assets/css/ui-refresh.css'))
    page.evaluate(mock(locale))
    page.add_script_tag(path=str(ROOT/'public/assets/js/atividades.js'))
    page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
    page.wait_for_timeout(50)

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    count=[0]
    def check(v,m):
        count[0]+=1
        if not v: raise AssertionError(m)
    for locale in ('pt-BR','en'):
        page=browser.new_page(viewport={'width':900,'height':600});errors=[];page.on('pageerror',lambda e:errors.append(str(e)));boot(page,locale)
        toggle=page.locator('[data-duration-precision-toggle]')
        primary=page.locator('[data-primary-unit] [data-duration-field]')
        check(toggle.is_visible() and toggle.inner_text()=='+ ms',f'{locale}: + ms visível na duração principal')
        check(page.locator('[data-duration-precision-toggle]').count()==1,f'{locale}: existe somente um toggle global de ms')
        check(page.locator('[data-segment] [data-duration-precision-toggle]').count()==0,f'{locale}: Trecho não possui + ms próprio')
        row=page.locator('.duration-control-row').bounding_box(); field=primary.bounding_box(); button=toggle.bounding_box()
        check(row and field and button and button['x']>=field['x']+field['width']-1,f'{locale}: botão fica à direita do controle principal')
        check(abs((button['y']+button['height']/2)-(field['y']+field['height']/2))<=4,f'{locale}: controle permanece na mesma linha em desktop')
        toggle.click()
        check(page.locator('[data-primary-unit] [data-duration-ms-wrap]').is_visible(),f'{locale}: ativação exibe ms principal')
        check(page.locator('[data-segment] [data-duration-ms-wrap]').is_visible(),f'{locale}: trechos continuam compatíveis com precisão')
        ms_input=page.locator('[data-primary-unit] [data-duration-milliseconds]')
        ms_input.fill('5'); ms_input.blur(); page.wait_for_timeout(15)
        check(ms_input.input_value()=='005',f'{locale}: 5 ms normaliza visualmente para 005')
        check(page.locator('[data-primary-unit] [data-duration-value]').input_value()=='00:00:12.005',f'{locale}: 5 ms persiste como .005 e não .500')
        segment_ms=page.locator('[data-segment] [data-duration-milliseconds]');segment_ms.fill('7');segment_ms.blur()
        check(page.locator('[data-segment] [data-duration-value]').input_value()=='00:00:10.007',f'{locale}: trecho persiste milissegundos')
        segment_ms.fill('0');segment_ms.blur()
        check(page.locator('[data-segment] [data-duration-value]').input_value()=='00:00:10',f'{locale}: .000 não é serializado sem necessidade')
        check(not errors,f'{locale}: sem erros JS {errors}')
        page.close()

    page=browser.new_page(viewport={'width':900,'height':600});boot(page,'pt-BR','438')
    check(page.locator('[data-duration-precision-toggle]').inner_text()=='− ms', 'atividade 12.438 reabre com precisão ativa')
    check(page.locator('[data-primary-unit] [data-duration-milliseconds]').input_value()=='438', 'atividade 12.438 reabre sem alterar a fração')
    check(page.locator('[data-primary-unit] [data-duration-value]').input_value()=='00:00:12.438', 'atividade 12.438 permanece canônica ao reabrir')
    browser.close();print(f'✓ activity duration ms UI: {count[0]} assertions; PT/EN')
