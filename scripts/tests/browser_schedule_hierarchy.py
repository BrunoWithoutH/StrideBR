#!/usr/bin/env python3
from pathlib import Path
import json
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
CSS = [ROOT / 'public/assets/css/style.css', ROOT / 'public/assets/css/cronogramas.css', ROOT / 'public/assets/css/ui-refresh.css']
JS = ROOT / 'public/assets/js/cronogramas.js'
OUT = ROOT / 'docs/reports/screenshots/RC4_SCHEDULE_HIERARCHY_2026-09-08'
OUT.mkdir(parents=True, exist_ok=True)

assertions = 0
failures = []
metrics = {}

def check(condition, message):
    global assertions
    assertions += 1
    if not condition:
        raise AssertionError(message)


def week_cards():
    days = ['DOM', 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SÁB']
    html = '<div class="time-column"><div class="calendar-corner"></div><div class="time-track"></div></div>'
    for i, day in enumerate(days):
        card = ''
        if i in (1, 3, 5):
            workout_id = {1:'a',3:'b',5:'c'}[i]
            title = {1:'A Academia',3:'B Academia',5:'C Academia'}[i]
            focus = {1:'Peito e tríceps',3:'Pernas e panturrilha',5:'Costas e bíceps'}[i]
            completed = i == 1
            shifted = i == 1
            classes = ' is-complete is-realized-shifted' if completed else ''
            status = 'Realizado em outra data' if shifted else 'A fazer'
            card = f'''<button type="button" class="workout-card{classes}" data-preview-workout="{workout_id}" data-week-card data-occurrence-workout="{workout_id}" data-occurrence-original="2026-09-{7+i:02d}" data-occurrence-date="2026-09-{7+i:02d}" data-planned-date="2026-09-{7+i:02d}" data-planned-time="14:00" data-completed="{'1' if completed else '0'}" style="--start-min:840;--duration-min:75"><small class="workout-card-kicker">{workout_id.upper()}</small><strong>{title}</strong><span data-card-time>14:00–15:15</span><small>{focus}</small><em>{status}</em></button>'''
        html += f'<div class="day-column"><div class="day-header"><span>{day}</span><small>{7+i:02d}/09</small></div><div class="day-track" data-week-date="2026-09-{7+i:02d}">{card}</div></div>'
    return html


def agenda_cards():
    return ''.join([
        '<div class="agenda-day"><h2>Segunda-feira</h2><article class="agenda-card"><button class="agenda-card-main"><small>A · Peito</small><strong>A Academia</strong><span>14:00–15:15</span></button></article></div>',
        '<div class="agenda-day"><h2>Quarta-feira</h2><article class="agenda-card"><button class="agenda-card-main"><small>B · Pernas</small><strong>B Academia</strong><span>14:00–15:15</span></button></article></div>',
        '<div class="agenda-day"><h2>Sexta-feira</h2><article class="agenda-card"><button class="agenda-card-main"><small>C · Costas</small><strong>C Academia</strong><span>14:00–15:15</span></button></article></div>',
    ])


def sidebar():
    days = ''.join(f'<span class="schedule-mini-day{(" is-today" if d == 8 else "")}">{d}</span>' for d in range(1, 31))
    weekdays = ''.join(f'<span class="schedule-mini-weekday">{d}</span>' for d in 'DSTQQSS')
    return f'''<aside class="schedule-side-panel" aria-label="Navegação da agenda">
      <section class="schedule-mini-month"><div class="schedule-mini-month-heading"><strong>Setembro de 2026</strong><a href="#">Abrir mês</a></div><div class="schedule-mini-month-grid">{weekdays}{days}</div></section>
      <section class="schedule-week-progress" data-week-summary><div class="schedule-week-progress-heading"><div><span>Esta semana</span><strong>1 de 3 feitos</strong></div><span class="schedule-week-progress-count">33%</span></div><div class="schedule-week-progress-bar"><span style="width:33%"></span></div><div class="schedule-week-next"><div class="schedule-week-next-heading"><span>Próximo sugerido</span><button type="button">Trocar</button></div><button type="button" class="schedule-week-next-main" data-preview-workout="b"><span class="schedule-week-next-code">B</span><span class="schedule-week-next-copy"><strong>B Academia</strong><small>Pernas e panturrilha</small></span></button></div></section>
      <section class="schedule-side-list"><div class="schedule-side-list-heading"><strong>Meus cronogramas</strong><span>2</span></div><nav><a class="is-active"><span>Academia 3x</span></a><a><span>Corrida base</span></a></nav></section>
    </aside>'''


def planning_block():
    return '''<div data-before-pseudo><nav class="planning-subnav"><a>Semana anterior</a><a class="is-active">Esta semana</a><a>Próxima semana</a></nav><section class="planning-week"><div class="section-title-row"><h2>Esta semana</h2><span>06/09 – 12/09</span></div><p><span><strong>3</strong> planejados</span> · <span><strong>1</strong> realizados</span> · <span><strong>0</strong> não realizados</span> · <span><strong>0</strong> outras atividades</span></p><div class="trainer-mini-list"><article><div><span>segunda-feira</span><strong>A Academia</strong><small>Realizado</small></div></article><article><div><span>quarta-feira</span><strong>B Academia</strong><small>A fazer</small></div></article><article><div><span>sexta-feira</span><strong>C Academia</strong><small>A fazer</small></div></article></div></section></div>'''


def fixture(before=False):
    pseudo = planning_block() if before else ''
    workout_data = json.dumps([
        {'idtreino':'a','titulo':'A Academia','codigo':'A','foco':'Peito e tríceps','hora_inicio':'14:00'},
        {'idtreino':'b','titulo':'B Academia','codigo':'B','foco':'Pernas e panturrilha','hora_inicio':'14:00'},
        {'idtreino':'c','titulo':'C Academia','codigo':'C','foco':'Costas e bíceps','hora_inicio':'14:00'},
    ])
    return f'''<!doctype html><html lang="pt-BR" data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body class="schedule-body" data-schedule-view="month"><div class="container-fluid"><main class="main-content cronograma-page"><div class="schedule-shell">
      <div class="schedule-heading"><div><h1>Cronogramas</h1><p>Planeje sua rotina e acompanhe treinos.</p></div><div class="schedule-heading-actions"><button class="secondary-button">Biblioteca</button><button class="primary-button">Novo cronograma</button></div></div>
      <nav class="planning-subnav monthly-planning-subnav"><a class="is-active">Cronogramas</a><a>Agenda mensal</a></nav>
      <section class="schedule-toolbar" data-schedule-toolbar><label class="schedule-select-label"><span>Cronograma</span><select data-schedule-selector><option value="schedule-1">Academia 3x</option></select></label><div class="view-switch" role="group" aria-label="Visualização"><button type="button" class="view-button" data-view="week" aria-pressed="false">Semana</button><button type="button" class="view-button is-active" data-view="month" aria-pressed="true">Mês</button><button type="button" class="view-button" data-view="agenda" aria-pressed="false">Lista</button></div><button type="button" class="primary-button schedule-new-workout">Adicionar treino</button><div class="schedule-toolbar-spacer"></div><div class="zoom-controls" data-zoom-controls hidden><button class="view-button">−</button><span data-zoom-label>100%</span><button class="view-button">+</button></div><button type="button" class="secondary-button icon-only-button">•••</button></section>
      <script type="application/json" data-workout-editor-data>{workout_data}</script>
      <div class="schedule-view-layout">{sidebar()}<div class="schedule-view-main">{pseudo}
        <section class="schedule-compact-week-summary" data-week-summary-compact><div class="schedule-compact-week-status"><span>Esta semana</span><strong data-week-compact-progress>1/3 realizados</strong></div><span class="schedule-compact-week-percent" data-week-compact-percent>33%</span><button type="button" class="schedule-compact-week-next" data-week-compact-next data-preview-workout="b"><span>Próximo</span><strong data-week-compact-next-title>B · B Academia</strong></button></section>
        <section class="schedule-week-context" data-view-context="week" hidden><div class="schedule-period-toolbar"><nav class="schedule-period-nav" aria-label="Navegação semanal"><a class="schedule-period-arrow" href="#" aria-label="Semana anterior">←</a><strong>06/09 – 12/09</strong><a class="schedule-period-arrow" href="#" aria-label="Próxima semana">→</a></nav><a class="secondary-button schedule-period-current" href="#">Esta semana</a></div><div class="schedule-week-context-summary" data-week-context-summary><span data-week-compact-progress>1/3 realizados</span><span data-week-compact-pending>2 pendentes</span><button type="button" data-week-compact-next data-preview-workout="b"><span>Próximo:</span><strong data-week-compact-next-title>B · B Academia</strong></button></div></section>
        <section class="calendar-view" data-calendar-view="week" data-calendar-scroll hidden><div class="week-calendar" data-week-calendar>{week_cards()}</div></section>
        <section class="schedule-month-view" data-calendar-view="month" data-month-calendar-shell data-schedule-id="schedule-1" data-current-month="2026-09"><div class="schedule-month-toolbar schedule-period-toolbar"><div class="schedule-month-nav schedule-period-nav" aria-label="Navegação mensal"><a class="schedule-period-arrow" href="#" data-month-nav="2026-08" aria-label="Mês anterior">←</a><strong data-month-title>Setembro de 2026</strong><a class="schedule-period-arrow" href="#" data-month-nav="2026-10" aria-label="Próximo mês">→</a></div><div class="schedule-month-actions"><a class="secondary-button" href="#" data-month-nav="2026-09">Hoje</a></div></div><div class="schedule-month-calendar-wrap"><div class="monthly-calendar schedule-month-calendar" data-month-grid aria-label="Setembro de 2026"></div></div></section>
        <section class="agenda-view" data-calendar-view="agenda" hidden>{agenda_cards()}</section>
      </div></div>
    </div></main></div></body></html>'''


def install_runtime(page):
    page.evaluate("""() => {
      const store = () => { const values = new Map(); return {getItem:k=>values.has(String(k))?values.get(String(k)):null,setItem:(k,v)=>values.set(String(k),String(v)),removeItem:k=>values.delete(String(k)),clear:()=>values.clear()} }
      Object.defineProperty(window,'localStorage',{value:store()}); Object.defineProperty(window,'sessionStorage',{value:store()});
      Object.defineProperty(document,'cookie',{get:()=>'',set:()=>true}); history.replaceState=()=>{}; history.pushState=()=>{};
      const dict = {'schedule.previous_month':'Mês anterior','schedule.next_month':'Próximo mês','schedule.add_workout_on':'Adicionar treino em {date}','schedule.next_day':'dia seguinte','planning.status.todo':'A fazer','planning.status.completed':'Realizado','planning.status.shifted':'Realizado em outra data','schedule.workout_fallback':'Treino','schedule.week_progress_compact':'{done}/{total} realizados','schedule.week_pending_compact.one':'1 pendente','schedule.week_pending_compact.other':'{count} pendentes','schedule.no_workouts':'Sem treinos','common.today':'Hoje'};
      const replace=(text,values={})=>Object.entries(values).reduce((s,[k,v])=>s.split(`{${k}}`).join(String(v)),String(text));
      window.StrideBRI18n={locale:'pt-BR',t:(key,values={},fallback=key)=>replace(dict[key]??fallback,values),tn:(one,other,count,values={})=>replace(dict[Number(count)===1?one:other]??(Number(count)===1?one:other),{...values,count}),weekdayShort:i=>['dom','seg','ter','qua','qui','sex','sáb'][i],monthYear:()=> 'setembro de 2026'};
      const rows=[
        {idtreino:'a',titulo:'A Academia',codigo:'A',foco:'Peito e tríceps',data_original:'2026-09-07',data_treino:'2026-09-07',data_planejada:'2026-09-07',hora_inicio:'14:00',hora_planejada:'14:00',concluido:true,realizado_fora_planejado:false,acompanhamento_disponivel:true},
        {idtreino:'b',titulo:'B Academia',codigo:'B',foco:'Pernas e panturrilha',data_original:'2026-09-09',data_treino:'2026-09-09',data_planejada:'2026-09-09',hora_inicio:'14:00',hora_planejada:'14:00',concluido:false,realizado_fora_planejado:false,acompanhamento_disponivel:true},
        {idtreino:'c',titulo:'C Academia',codigo:'C',foco:'Costas e bíceps',data_original:'2026-09-11',data_treino:'2026-09-11',data_planejada:'2026-09-11',hora_inicio:'14:00',hora_planejada:'14:00',concluido:false,realizado_fora_planejado:false,acompanhamento_disponivel:true},
        {idtreino:'a',titulo:'A Academia',codigo:'A',foco:'Peito e tríceps',data_original:'2026-09-14',data_treino:'2026-09-15',data_planejada:'2026-09-14',data_realizada:'2026-09-15',hora_inicio:'14:00',hora_planejada:'14:00',hora_realizada:'14:12',concluido:true,realizado_fora_planejado:true,acompanhamento_disponivel:true}
      ];
      window.StrideBRNet={fetch:async()=>({ok:true,json:async()=>({ok:true,ocorrencias:rows,agendados:[]}),text:async()=>document.documentElement.outerHTML})};
      window.StrideBRUI={notify:()=>{},confirm:async()=>true};
    }""")


def setup(page, before=False):
    page.set_content(fixture(before=before), wait_until='domcontentloaded')
    for css in CSS:
        page.add_style_tag(path=str(css))
    install_runtime(page)
    if not before:
        page.add_script_tag(path=str(JS))
        page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
        page.wait_for_timeout(220)


def gap_metric(page):
    return page.evaluate("""() => {
      const main=document.querySelector('.schedule-view-main').getBoundingClientRect();
      const calendar=document.querySelector('[data-calendar-view="month"] .schedule-month-calendar-wrap').getBoundingClientRect();
      return Math.round(calendar.top-main.top);
    }""")

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    try:
        before = browser.new_page(viewport={'width':1440,'height':900})
        setup(before, before=True)
        metrics['desktop_before_calendar_offset'] = gap_metric(before)
        before.screenshot(path=str(OUT/'00-before-conceptual-desktop-month-dark.png'), full_page=True)
        before.close()

        page = browser.new_page(viewport={'width':1440,'height':900})
        errors=[]
        page.on('pageerror', lambda error: errors.append(str(error)))
        setup(page)
        metrics['desktop_after_calendar_offset'] = gap_metric(page)
        check(page.locator('[data-before-pseudo]').count()==0, 'Mês ainda contém pseudo-calendário semanal')
        check(page.locator('[data-week-summary]').is_visible(), 'Resumo lateral não está visível no desktop')
        check(not page.locator('[data-week-summary-compact]').is_visible(), 'Resumo compacto deveria ficar oculto com sidebar desktop')
        check(page.locator('[data-calendar-view=month]').is_visible(), 'Calendário mensal não é protagonista na view Mês')
        check(page.locator('.schedule-month-hint').count()==0, 'Copy explicativa do mês ainda existe')
        check(page.locator('[data-view=month]').get_attribute('aria-pressed')=='true', 'Mês não está marcado como selecionado')
        check(page.evaluate('document.documentElement.scrollWidth <= innerWidth'), 'Desktop Mês criou overflow do body')
        page.screenshot(path=str(OUT/'01-after-desktop-month-dark.png'), full_page=True)

        page.locator('[data-view=week]').click()
        page.wait_for_timeout(80)
        check(page.locator('[data-view-context=week]').is_visible(), 'Contexto compacto da Semana não apareceu')
        check(page.locator('[data-calendar-view=week]').is_visible(), 'Grade semanal não apareceu')
        check(not page.locator('[data-week-summary-compact]').is_visible(), 'Semana duplicou resumo compacto fora do próprio contexto')
        check(page.locator('[data-view=week]').get_attribute('aria-pressed')=='true', 'Semana não atualizou aria-pressed')
        check(page.locator('[data-week-context-summary]').inner_text().count('Academia') <= 1, 'Resumo semanal virou lista duplicada de treinos')
        check(page.evaluate('document.documentElement.scrollWidth <= innerWidth'), 'Desktop Semana criou overflow do body')
        page.evaluate('window.scrollTo(0,0)')
        page.screenshot(path=str(OUT/'02-after-desktop-week-dark.png'), full_page=False)

        page.locator('[data-view=agenda]').click()
        page.wait_for_timeout(60)
        check(page.locator('[data-calendar-view=agenda]').is_visible(), 'Lista não apareceu')
        check(page.locator('[data-view-context=week]').is_hidden(), 'Contexto semanal permaneceu na Lista')
        check(page.locator('.agenda-day').count()==3, 'Lista principal não preservou ocorrências representativas')
        check(page.evaluate('document.documentElement.scrollWidth <= innerWidth'), 'Desktop Lista criou overflow do body')
        page.screenshot(path=str(OUT/'03-after-desktop-list-dark.png'), full_page=True)
        check(errors==[], f'cronogramas.js gerou erros no desktop: {errors}')
        page.close()

        for width, view, filename in [(390,'month','04-after-mobile-390-month-dark.png'),(390,'week','05-after-mobile-390-week-dark.png'),(375,'agenda','06-after-mobile-375-list-dark.png')]:
            page = browser.new_page(viewport={'width':width,'height':844})
            errors=[]
            page.on('pageerror', lambda error, bucket=errors: bucket.append(str(error)))
            setup(page)
            page.locator(f'[data-view={view}]').click()
            page.wait_for_timeout(90)
            check(not page.locator('[data-week-summary]').is_visible(), f'{width}px: sidebar deveria estar oculta')
            if view == 'week':
                check(not page.locator('[data-week-summary-compact]').is_visible(), f'{width}px Semana duplicou faixa de resumo')
                check(page.locator('[data-week-context-summary]').is_visible(), f'{width}px Semana perdeu resumo integrado')
            else:
                check(page.locator('[data-week-summary-compact]').is_visible(), f'{width}px {view}: resumo compacto não apareceu')
            check(page.locator(f'[data-calendar-view={view}]').is_visible(), f'{width}px: view {view} não está visível')
            check(page.evaluate('document.documentElement.scrollWidth <= innerWidth'), f'{width}px {view}: body overflow horizontal')
            check(page.locator('[data-schedule-toolbar]').evaluate('(el)=>el.scrollWidth<=el.clientWidth'), f'{width}px {view}: toolbar overflow horizontal')
            if view == 'week': page.evaluate('window.scrollTo(0,0)')
            page.screenshot(path=str(OUT/filename), full_page=(view != 'week'))
            check(errors==[], f'{width}px {view}: erros JS: {errors}')
            page.close()

        for width in (360, 768, 1024, 1101):
            page = browser.new_page(viewport={'width':width,'height':800})
            setup(page)
            check(page.evaluate('document.documentElement.scrollWidth <= innerWidth'), f'{width}px matrix: body overflow horizontal')
            check(page.locator('[data-schedule-toolbar]').evaluate('(el)=>el.scrollWidth<=el.clientWidth'), f'{width}px matrix: toolbar overflow horizontal')
            if width <= 1100:
                check(not page.locator('[data-week-summary]').is_visible(), f'{width}px matrix: sidebar deveria estar oculta')
                check(page.locator('[data-week-summary-compact]').is_visible(), f'{width}px matrix: resumo compacto deveria substituir sidebar')
            else:
                check(page.locator('[data-week-summary]').is_visible(), f'{width}px matrix: sidebar deveria permanecer visível')
                check(not page.locator('[data-week-summary-compact]').is_visible(), f'{width}px matrix: resumo compacto não deveria duplicar sidebar')
            page.close()

        page = browser.new_page(viewport={'width':1440,'height':900})
        setup(page)
        page.evaluate("document.documentElement.dataset.theme='light'")
        page.wait_for_timeout(40)
        check(page.locator('[data-calendar-view=month]').is_visible(), 'Light mode perdeu calendário mensal')
        check(page.evaluate('document.documentElement.scrollWidth <= innerWidth'), 'Light desktop criou overflow')
        page.screenshot(path=str(OUT/'07-after-desktop-month-light.png'), full_page=True)
        page.close()

        check(metrics['desktop_after_calendar_offset'] < metrics['desktop_before_calendar_offset'] - 100, 'Redução antes do calendário não foi material')
    except Exception as exc:
        failures.append(str(exc))
    finally:
        browser.close()

(OUT / 'metrics.json').write_text(json.dumps(metrics, ensure_ascii=False, indent=2) + '\n')

if failures:
    print('Falhas no browser schedule hierarchy:', file=sys.stderr)
    for failure in failures:
        print(f'- {failure}', file=sys.stderr)
    print(json.dumps(metrics, ensure_ascii=False), file=sys.stderr)
    sys.exit(1)

print(f'✓ browser schedule hierarchy: {assertions} assertions; screenshots: {OUT}; metrics: {metrics}')
