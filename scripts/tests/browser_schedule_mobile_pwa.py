#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[2]
SCREEN_DIR = ROOT / 'docs/reports/screenshots' / 'MOBILE_PWA_FINISH_2026-09-09'
SCREEN_DIR.mkdir(parents=True, exist_ok=True)
CSS = '\n'.join((ROOT / path).read_text() for path in [
    'public/assets/css/style.css',
    'public/assets/css/cronogramas.css',
    'public/assets/css/ui-refresh.css',
])
PWA = (ROOT / 'public/assets/js/pwa.js').read_text()
HEAD = '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">'

WEEK = '''
<main class="main-content cronograma-page"><div class="schedule-shell"><div class="calendar-view" data-calendar-view="week"><section class="week-calendar">
<div class="time-column"><div class="calendar-corner"></div><div class="time-track"><span style="top:96px">02:00</span><span style="top:384px">08:00</span></div></div>
''' + ''.join(f'''<div class="day-column"><div class="day-header">D{i}</div><div class="day-track"><button class="workout-card" style="--start-min:{480+i*30};--duration-min:90"><strong>Treino {i}</strong><small>Academia</small></button></div></div>''' for i in range(7)) + '''</section></div></div></main>'''

MONTH = '''
<main class="main-content cronograma-page"><div class="schedule-shell"><section class="schedule-month-view"><div class="schedule-month-calendar-wrap"><div class="monthly-calendar schedule-month-calendar">''' + ''.join(f'<div class="monthly-weekday">{d}</div>' for d in ['DOM','SEG','TER','QUA','QUI','SEX','SÁB']) + ''.join(f'''<article class="monthly-day{' is-today' if i==9 else ''}"><div class="schedule-month-day-heading"><strong>{i}</strong></div><div class="monthly-events">{'' if i not in (3,6,9,12,15,18,21,24,27) else f'<article class="monthly-event is-recurring schedule-month-recurring"><button class="schedule-month-event-main"><span class="schedule-month-event-meta">14:00</span><strong><b class="workout-code-inline">A</b><span class="schedule-month-event-title">Academia</span></strong><small class="schedule-month-event-status">A fazer</small></button><button class="schedule-month-event-menu">•••</button></article>'}</div></article>''' for i in range(1,29)) + '''</div></div></section></div></main>'''

SHIFTED = '''
<main class="main-content"><div class="monthly-shell"><div class="monthly-calendar-scroll"><section class="monthly-calendar">''' + ''.join(f'<div class="monthly-weekday">{d}</div>' for d in ['DOM','SEG','TER','QUA','QUI','SEX','SÁB']) + '''
<article class="monthly-day"><header><strong>7</strong></header><div class="monthly-events"><div class="monthly-event is-recurring is-completed is-shifted"><div class="monthly-event-kicker"><span>✓ 14:00</span><em>Base</em></div><strong><b>A</b>Academia</strong><small>Realizado em outra data</small><details class="monthly-event-more"><summary aria-label="Mais ações">•••</summary><div class="monthly-event-more-menu"><span class="monthly-plan-note">Planejado 07/09</span><a>Ver atividade</a><button>Ajustar datas</button></div></details></div></div></article>
''' + ''.join(f'<article class="monthly-day"><header><strong>{i}</strong></header></article>' for i in range(8,14)) + '''</section></div></div></main>'''

MODAL = '''
<main class="main-content"><button>Novo treino</button></main><div class="calendar-quick-create"><button class="calendar-quick-create-backdrop"></button><section class="calendar-quick-create-popover" role="dialog" aria-modal="true"><header class="calendar-quick-create-header"><div><span class="eyebrow">NOVO TREINO</span><h2>Novo treino</h2><p>Adicione um treino ao cronograma.</p></div><button class="icon-button">×</button></header><form><section class="quick-create-source"><div class="quick-create-section-heading"><div><strong>Começar com</strong><span>Use um treino salvo ou comece do zero.</span></div></div><div class="quick-create-source-cards"><button class="quick-create-source-card is-active"><span class="quick-create-source-icon">+</span><span><strong>Novo treino</strong><small>Começar do zero</small></span></button><button class="quick-create-source-card"><span class="quick-create-source-icon is-saved">↗</span><span><strong>Treino A</strong><small>Peito e tríceps</small></span></button></div></section><label class="quick-create-title-field">Título<input placeholder="Ex.: Academia"></label><div class="quick-create-schedule-fields"><label>Data<input type="date" value="2026-09-09"></label><div class="quick-create-time-row"><label>Início<div class="time24-control"><div class="time24-input-row"><input value="14"><span class="time24-separator">:</span><input value="00"><button class="time24-toggle">⌄</button></div></div></label><label>Fim<div class="time24-control"><div class="time24-input-row"><input value="15"><span class="time24-separator">:</span><input value="00"><button class="time24-toggle">⌄</button></div></div></label></div></div><details class="quick-create-details"><summary>Mais detalhes</summary></details><label class="quick-create-save-library"><input type="checkbox"> Salvar na biblioteca</label><div class="quick-create-actions"><button class="secondary-button">Cancelar</button><button class="primary-button">Salvar treino</button></div></form></section></div>'''


def render(page, html, viewport, theme, name):
    page.set_viewport_size({'width': viewport[0], 'height': viewport[1]})
    page.set_content(f'<!doctype html><html data-theme="{theme}"><head>{HEAD}<style>{CSS}</style></head><body class="schedule-body">{html}</body></html>')
    page.screenshot(path=str(SCREEN_DIR / name), full_page=True)


def check_week(page, width):
    page.set_viewport_size({'width': width, 'height': 820})
    page.set_content(f'<!doctype html><html data-theme="dark"><head>{HEAD}<style>{CSS}</style></head><body class="schedule-body">{WEEK}</body></html>')
    state = page.locator('.calendar-view').evaluate('el => ({height:el.getBoundingClientRect().height, clientHeight:el.clientHeight, scrollHeight:el.scrollHeight, clientWidth:el.clientWidth, scrollWidth:el.scrollWidth, oy:getComputedStyle(el).overflowY, ox:getComputedStyle(el).overflowX, body:document.documentElement.scrollHeight, vh:innerHeight})')
    assert state['height'] > state['vh'], f'week must grow beyond the viewport when content requires it at {width}: {state}'
    assert state['scrollHeight'] <= state['clientHeight'] + 2, f'week must not have internal vertical scrolling at {width}: {state}'
    assert state['scrollWidth'] > state['clientWidth'], f'week must preserve horizontal scrolling at {width}: {state}'
    assert state['body'] > state['vh'], f'page must own vertical scrolling at {width}: {state}'
    assert state['oy'] not in ('auto', 'scroll'), f'overflow-y must not be scrollable at {width}: {state}'
    return 5


def check_month(page, width):
    page.set_viewport_size({'width': width, 'height': 844})
    page.set_content(f'<!doctype html><html data-theme="dark"><head>{HEAD}<style>{CSS}</style></head><body class="schedule-body">{MONTH}</body></html>')
    wrap_state = page.locator('.schedule-month-calendar-wrap').evaluate('el => ({clientHeight:el.clientHeight, scrollHeight:el.scrollHeight, oy:getComputedStyle(el).overflowY})')
    if width <= 760:
        assert wrap_state['scrollHeight'] <= wrap_state['clientHeight'] + 2, f'mobile month must grow with page instead of owning vertical scrolling at {width}: {wrap_state}'
    cell = page.locator('.schedule-month-calendar .monthly-day').first.evaluate('el => el.getBoundingClientRect().height')
    card = page.locator('.schedule-month-recurring').first
    lines = card.locator('.schedule-month-event-main > *').count()
    assert cell <= 104, f'month cells should be compact at {width}: {cell}'
    assert lines <= 3, f'month workout card should have at most three content rows: {lines}'
    assert card.locator('.schedule-month-event-title').inner_text() == 'Academia'
    assert card.locator('.schedule-month-event-status').inner_text() == 'A fazer'
    return 6


def check_desktop_calendar_scroll(page):
    count = 0
    page.set_viewport_size({'width': 1280, 'height': 900})
    page.set_content(f'<!doctype html><html data-theme="dark"><head>{HEAD}<style>{CSS}</style></head><body class="schedule-body">{WEEK}</body></html>')
    week = page.locator('.calendar-view').evaluate('el => ({clientHeight:el.clientHeight, scrollHeight:el.scrollHeight, oy:getComputedStyle(el).overflowY, ox:getComputedStyle(el).overflowX})')
    assert week['oy'] == 'auto' and week['scrollHeight'] > week['clientHeight'], f'desktop week should use bounded vertical scroll: {week}'
    assert week['ox'] in ('hidden', 'clip'), f'desktop week should fit columns instead of requiring horizontal scroll: {week}'
    count += 2

    page.set_content(f'<!doctype html><html data-theme="dark"><head>{HEAD}<style>{CSS}</style></head><body class="schedule-body">{MONTH}</body></html>')
    page.locator('.schedule-month-calendar').evaluate("el => {for(let i=0;i<21;i++){const d=document.createElement('article');d.className='monthly-day';d.innerHTML='<div class=\"schedule-month-day-heading\"><strong>'+(29+i)+'</strong></div>';el.appendChild(d)}}")
    month = page.locator('.schedule-month-calendar-wrap').evaluate('el => ({clientHeight:el.clientHeight, scrollHeight:el.scrollHeight, oy:getComputedStyle(el).overflowY})')
    assert month['oy'] == 'visible', f'desktop month must not own a vertical scrollbar: {month}'
    assert page.evaluate('document.documentElement.scrollHeight > window.innerHeight'), 'desktop month overflow must remain reachable through document scroll'
    count += 1
    return count


def check_modal(page, theme, width):
    page.set_viewport_size({'width': width, 'height': 844})
    page.set_content(f'<!doctype html><html data-theme="{theme}" class="calendar-quick-open"><head>{HEAD}<style>{CSS}</style></head><body class="schedule-body">{MODAL}</body></html>')
    colors = page.locator('.calendar-quick-create-popover').evaluate('el => {const p=getComputedStyle(el), h=getComputedStyle(el.querySelector(".calendar-quick-create-header")), f=getComputedStyle(el.querySelector(".quick-create-schedule-fields")); return {panel:p.backgroundColor, header:h.backgroundColor, fields:f.backgroundColor, text:p.color, height:el.getBoundingClientRect().height, viewport:innerHeight}}')
    assert colors['height'] <= colors['viewport'], f'modal must stay accessible: {colors}'
    if theme == 'dark':
        assert colors['panel'] != 'rgb(255, 255, 255)' and colors['header'] != 'rgb(251, 252, 254)' and colors['fields'] != 'rgb(251, 252, 253)', f'dark modal leaked light surfaces: {colors}'
    cols = page.locator('.quick-create-time-row').evaluate('el => getComputedStyle(el).gridTemplateColumns.split(" ").length')
    if width >= 360:
        assert cols == 2, f'start/end should stay efficient at {width}: {cols}'
    return 2


def pwa_page(browser, ua, standalone=False):
    page = browser.new_page(viewport={'width':390,'height':844}, user_agent=ua)
    page.set_content(f'<!doctype html><html data-theme="dark"><head>{HEAD}<style>{CSS}</style></head><body><header class="site-header">StrideBR</header><main><h1>Treinos</h1><p>Conteúdo</p></main><footer class="site-footer">Footer</footer></body></html>')
    page.evaluate("flag => {const store={};Object.defineProperty(window,'localStorage',{configurable:true,value:{getItem:k=>Object.prototype.hasOwnProperty.call(store,k)?store[k]:null,setItem:(k,v)=>store[k]=String(v),removeItem:k=>delete store[k]}});Object.defineProperty(navigator,'standalone',{configurable:true,value:flag});window.StrideBRI18n={t:(k,v,f)=>({ 'pwa.add_to_home':'Adicionar à tela inicial','pwa.add_to_home_help':'Acesse o StrideBR direto da tela inicial do celular.','pwa.add_action':'Adicionar','pwa.dismiss':'Agora não','pwa.ios_instruction':'No iPhone: Compartilhar → Adicionar à Tela de Início.'}[k]||f||k)} }", standalone)
    page.add_script_tag(content=PWA)
    page.wait_for_timeout(40)
    return page


def check_pwa(browser):
    count = 0
    android = pwa_page(browser, 'Mozilla/5.0 (Linux; Android 15; Pixel 8) AppleWebKit/537.36 Chrome/140.0 Mobile Safari/537.36')
    android.evaluate("() => {const e=new Event('beforeinstallprompt');e.prompt=async()=>{window.__promptCalls=(window.__promptCalls||0)+1};e.userChoice=Promise.resolve({outcome:'accepted'});window.dispatchEvent(e)}")
    android.wait_for_timeout(30)
    assert android.locator('[data-pwa-install-hint].is-visible').count() == 1
    count += 1
    android.locator('[data-pwa-install]').click()
    android.wait_for_timeout(30)
    assert android.evaluate('window.__promptCalls') == 1
    count += 1
    assert android.locator('[data-pwa-install-hint].is-visible').count() == 0
    count += 1
    android.close()

    ios = pwa_page(browser, 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1')
    assert ios.locator('[data-pwa-install-hint].is-visible').count() == 1
    count += 1
    assert 'Compartilhar' in ios.locator('.pwa-install-hint-copy span').inner_text()
    count += 1
    ios.locator('[data-pwa-dismiss]').click()
    assert ios.locator('[data-pwa-install-hint].is-visible').count() == 0
    count += 1
    ios.close()

    standalone = pwa_page(browser, 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1', True)
    assert standalone.locator('[data-pwa-install-hint].is-visible').count() == 0
    count += 1
    standalone.close()
    return count


def main():
    checks = 0
    with sync_playwright() as p:
        browser = p.chromium.launch(executable_path='/usr/bin/chromium', headless=True, args=['--no-sandbox'])
        page = browser.new_page()
        for width in [360,375,390,430]:
            checks += check_week(page, width)
        for width in [390,768,1024,1280]:
            checks += check_month(page, width)
        checks += check_desktop_calendar_scroll(page)
        checks += check_modal(page, 'dark', 390)
        checks += check_modal(page, 'light', 390)
        checks += check_pwa(browser)
        render(page, WEEK, (390,844), 'dark', '01-week-390-dark.png')
        render(page, MONTH, (390,844), 'dark', '02-month-390-dark.png')
        render(page, MONTH, (1280,900), 'light', '03-month-desktop-light.png')
        render(page, SHIFTED, (390,844), 'light', '04-agenda-shifted-390-light.png')
        render(page, MODAL, (390,844), 'dark', '05-new-workout-modal-390-dark.png')
        render(page, MODAL, (390,844), 'light', '06-new-workout-modal-390-light.png')
        # Android install hint screenshot
        install = pwa_page(browser, 'Mozilla/5.0 (Linux; Android 15; Pixel 8) AppleWebKit/537.36 Chrome/140.0 Mobile Safari/537.36')
        install.evaluate("() => {const e=new Event('beforeinstallprompt');e.prompt=async()=>{};e.userChoice=Promise.resolve({outcome:'dismissed'});window.dispatchEvent(e)}")
        install.wait_for_timeout(30)
        install.screenshot(path=str(SCREEN_DIR/'07-add-home-android-390-dark.png'), full_page=True)
        install.close()
        page.close()
        browser.close()
    print(f'PASS mobile/PWA finish browser: {checks} checks, {len(list(SCREEN_DIR.glob("*.png")))} screenshots')

if __name__ == '__main__':
    main()
