from pathlib import Path
from playwright.sync_api import sync_playwright
import os
BASE = os.environ.get('STRIDEBR_BROWSER_URL', 'http://localhost:8080')
OUT = Path('/tmp/stridebr-planning-screenshots'); OUT.mkdir(exist_ok=True)
with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, args=['--no-sandbox'])
    page = browser.new_page(viewport={'width':1440,'height':1000})
    errors=[]
    page.on('pageerror', lambda error: errors.append(str(error)))
    def login(user):
        page.context.clear_cookies()
        page.goto(BASE+'/login.php?lang=pt-BR')
        page.locator('[name=UEmail]').fill(user+'@alpha-test.invalid')
        page.locator('[name=USenha]').fill('Planning-browser-123!')
        page.locator('button[name=submit]').click()
        page.wait_for_url('**/home.php')
    login('round_ux_athlete')
    for locale,options in [('pt-BR',['Automático (idioma do dispositivo)','Português (Brasil)','English']),('en',['Automatic (device language)','Português (Brasil)','English']),('pt-BR',['Automático (idioma do dispositivo)','Português (Brasil)','English'])]:
        page.goto(BASE+'/user/settings.php')
        select=page.locator('select[name=locale]')
        select.select_option(locale)
        form=select.locator('xpath=ancestor::form')
        form.locator('button[type=submit]').last.click()
        page.wait_for_load_state('networkidle')
        page.reload()
        assert page.locator('select[name=locale]').input_value()==locale, (locale, page.locator('select[name=locale]').input_value())
        assert page.locator('select[name=locale] option').all_text_contents()==options
        if locale == 'en':
            assert 'Preferências' not in page.locator('main').inner_text()
            assert page.locator('.settings-profile-back').inner_text() == '← Back to profile'
        page.screenshot(path=str(OUT/('settings-'+locale+'.png')), full_page=True)
    for locale in ['pt-BR','en']:
        for route in ['/home.php','/user/progresso.php']:
            page.goto(BASE+route+'?lang='+locale)
            assert page.locator('main').count()
            assert 'Fatal error' not in page.locator('body').inner_text()
            assert page.locator('html').get_attribute('lang')==locale
    page.goto(BASE+'/user/cronogramatreinos.php?planning_week=-1&lang=en')
    summary=page.locator('.planning-week')
    assert '3 planned' in summary.inner_text() and '3 not completed' in summary.inner_text() and '3 other activities' in summary.inner_text()
    page.screenshot(path=str(OUT/'schedule-previous-week.png'),full_page=True)
    for locale in ['pt-BR','en']:
        for route,label in [('cronogramatreinos.php','schedule'),('agenda-mensal.php','agenda'),('treinador.php','athlete')]:
            page.goto(BASE+'/user/'+route+'?lang='+locale)
            assert page.locator('main').count()
            assert 'Fatal error' not in page.inner_text('body')
            page.screenshot(path=str(OUT/(label+'-'+locale+'.png')),full_page=True)
            page.set_viewport_size({'width':390,'height':844})
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'), label+' mobile overflow'
            page.screenshot(path=str(OUT/(label+'-'+locale+'-mobile.png')),full_page=True)
            page.set_viewport_size({'width':1440,'height':1000})
    login('round_ux_coach')
    for width,label in [(1440,'desktop'),(390,'mobile')]:
        page.set_viewport_size({'width':width,'height':1000})
        for query,name in [('', 'coach'),('?atleta=round_ux_athlete','workspace')]:
            page.goto(BASE+'/user/treinador.php'+query)
            assert 'Fatal error' not in page.inner_text('body')
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'), name+' overflow'
            page.screenshot(path=str(OUT/(name+'-'+label+'.png')),full_page=True)
    page.goto(BASE+'/user/treinador.php?atleta=round_ux_athlete&lang=en')
    page.locator('[data-open-prescription]').click()
    assert page.locator('[data-prescription-modal] [role=dialog]').is_visible()
    assert page.locator('[name=titulo]').evaluate('(el)=>document.activeElement===el')
    page.locator('[data-add-prescription-exercise]').click()
    page.locator('[data-remove-prescription-exercise]').last.click()
    assert page.locator('[data-prescription-modal] [role=dialog]').evaluate('(el)=>el.scrollWidth<=el.clientWidth'), 'prescription overflow'
    page.screenshot(path=str(OUT/'prescription-mobile.png'),full_page=True)
    page.keyboard.press('Escape')
    assert page.locator('[data-open-prescription]').evaluate('(el)=>document.activeElement===el')
    page.goto(BASE+'/user/treinador.php?atleta=round_ux_empty&lang=en')
    assert page.locator('.planning-week').count()==0
    assert page.locator('[data-open-prescription]').count()==0
    page.screenshot(path=str(OUT/'workspace-no-permissions-mobile.png'),full_page=True)
    login('round_ux_athlete')
    page.goto(BASE+'/user/treinador.php?lang=en')
    permissions=page.locator('.trainer-permissions')
    activity_permission=permissions.locator('[name=pode_ver_atividades]')
    activity_permission.uncheck()
    permissions.locator('button[type=submit]').click()
    page.wait_for_load_state('networkidle')
    try:
        login('round_ux_coach')
        page.goto(BASE+'/user/treinador.php?atleta=round_ux_athlete&lang=en')
        assert page.locator('.planning-week').count()==0
        assert 'Corrida extra' not in page.locator('main').inner_text()
        response=page.request.get(BASE+'/api/cronograma-ocorrencias.php?atleta=round_ux_athlete&start=2026-09-01&end=2026-09-30')
        assert response.ok
        for item in response.json()['ocorrencias']:
            assert item['acompanhamento_disponivel'] is False
            assert not item['idregistro'] and not item['data_realizada']
        page.screenshot(path=str(OUT/'workspace-schedule-only.png'),full_page=True)
    finally:
        login('round_ux_athlete')
        page.goto(BASE+'/user/treinador.php')
        permissions=page.locator('.trainer-permissions')
        permissions.locator('[name=pode_ver_atividades]').check()
        permissions.locator('button[type=submit]').click()
        page.wait_for_load_state('networkidle')
    login('round_ux_empty')
    page.goto(BASE+'/user/cronogramatreinos.php?lang=en')
    page.screenshot(path=str(OUT/'empty-schedules-mobile.png'),full_page=True)
    page.goto(BASE+'/user/treinador.php?lang=en')
    page.screenshot(path=str(OUT/'empty-activities-mobile.png'),full_page=True)
    for user,label in [('round_ux_unlinked','no-coach'),('round_ux_pending','pending-invite')]:
        login(user)
        page.goto(BASE+'/user/treinador.php?lang=en')
        assert page.locator('main').count()
        page.screenshot(path=str(OUT/(label+'-mobile.png')),full_page=True)
    assert not errors,errors
    browser.close()
print('✓ Real Settings persistence, PT/EN planning pages, coach desktop/mobile; screenshots: '+str(OUT))
