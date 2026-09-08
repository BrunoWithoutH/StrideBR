"""Run fixture_workout_mobile.php in local Docker before this test."""
from pathlib import Path
from playwright.sync_api import sync_playwright, expect
import os, re
BASE=os.environ.get('STRIDEBR_BROWSER_URL','http://localhost:8080')
OUT=Path('/tmp/stridebr-workout-screenshots'); OUT.mkdir(exist_ok=True)
with sync_playwright() as p:
    engine=os.environ.get('STRIDEBR_BROWSER_ENGINE','chromium')
    browser=getattr(p,engine).launch(headless=True)
    page=browser.new_page(viewport={'width':375,'height':812},locale='pt-BR',service_workers='block')
    errors=[]
    page.on('pageerror',lambda e:errors.append(str(e)))
    page.goto(BASE+'/login.php?lang=pt-BR')
    page.locator('[name=UEmail]').fill('workout_mobile_test@alpha-test.invalid')
    page.locator('[name=USenha]').fill('Workout-mobile-123!')
    page.locator('button[name=submit]').click();page.wait_for_url('**/home.php')
    def open_workout():
        page.wait_for_function('window.StrideBRWorkout !== undefined')
        page.evaluate('async()=>{await StrideBRWorkout.refresh();StrideBRWorkout.open()}')
        page.locator('.session-set-row').first.wait_for()
        expect(page.locator('.session-exercise-history').first).to_be_visible()
    open_workout()
    def check():
        assert not re.search(r'workout_session\.|translation\.|undefined|NaN|Invalid Date',page.locator('[data-workout-session-modal]').inner_text())
        assert page.evaluate('document.documentElement.scrollWidth<=document.documentElement.clientWidth'), 'body overflow'
        bad=page.locator('.workout-session-panel,.workout-session-exercises,.session-exercise,.session-set-row,.workout-session-actions').evaluate_all('(els)=>els.filter(e=>e.scrollWidth>e.clientWidth+1).map(e=>[e.className,e.scrollWidth,e.clientWidth])')
        assert not bad,bad
        assert page.locator('[data-session-time]').inner_text()!='00:00'
        assert page.locator('.workout-session-actions').evaluate('(e)=>e.getBoundingClientRect().right<=innerWidth')
    for locale in ['pt-BR','en']:
        page.goto(BASE+'/home.php?lang='+locale);open_workout()
        assert ('exercícios' if locale=='pt-BR' else 'exercises') in page.locator('[data-session-progress]').inner_text()
        assert ('1 série' if locale=='pt-BR' else '1 set') in page.locator('.session-exercise').last.inner_text()
        for theme in ['dark','light']:
            page.evaluate('(theme)=>document.documentElement.dataset.theme=theme',theme)
            for width in [320,360,375,390,768,1024,1440]:
                page.set_viewport_size({'width':width,'height':900 if width>700 else 812});check()
                if width in [375,390,1440]:page.screenshot(path=str(OUT/f'{engine}-workout-{locale}-{theme}-{width}.png'))
    page.set_viewport_size({'width':375,'height':812})
    page.evaluate("document.documentElement.dataset.theme='dark'")
    def loads(): return page.locator('.session-exercise').first.locator('[data-session-set-load]').evaluate_all('(els)=>els.map(e=>e.value)')
    def load(n,value):
        field=page.locator('.session-exercise').first.locator('[data-session-set-load]').nth(n-1)
        field.fill(value)
        with page.expect_response(lambda r:'/function/treino_sessao.php' in r.url and r.request.method=='POST') as response:field.dispatch_event('change')
        assert response.value.json()['ok'];field.blur();page.wait_for_timeout(100)
    load(1,'15');assert loads()==['15']*4,loads()
    load(2,'20');assert loads()==['15','20','20','20'],loads()
    load(3,'25');load(1,'17');assert loads()==['17','20','25','25'],loads()
    # Editing S2 now cascades only up to S3's manual boundary.
    load(2,'17,5');assert loads()==['17','17.5','25','25'],loads()
    done=page.locator('.session-exercise').first.locator('[data-toggle-session-set]').nth(2)
    with page.expect_response(lambda r:'/function/treino_sessao.php' in r.url and r.request.method=='POST'):done.click()
    page.wait_for_timeout(200);load(2,'18');assert loads()==['17','18','25','25'],loads()
    page.reload();open_workout();assert loads()==['17','18','25','25'],loads()
    page.evaluate("document.documentElement.dataset.theme='dark'")
    load(2,'19');assert loads()==['17','19','25','25'],loads()
    with page.expect_response(lambda r:'/function/treino_sessao.php' in r.url and r.request.method=='POST'):page.locator('[data-toggle-session-set]').first.click()
    page.wait_for_timeout(150)
    page.screenshot(path=str(OUT/f'{engine}-workout-375-first-set-completed.png'))
    # Completed rows and a fully completed card; progress 2/10.
    for i in [0,1]:
        with page.expect_response(lambda r:'/function/treino_sessao.php' in r.url and r.request.method=='POST'):page.locator('[data-toggle-session-exercise]').nth(i).click()
        page.wait_for_timeout(150)
    check();page.screenshot(path=str(OUT/f'{engine}-workout-375-completed.png'))
    field=page.locator('[data-session-set-load]').nth(8);field.focus()
    page.screenshot(path=str(OUT/f'{engine}-workout-375-focused.png'))
    page.evaluate("Object.defineProperty(visualViewport,'height',{configurable:true,value:460});visualViewport.dispatchEvent(new Event('resize'))")
    page.wait_for_timeout(100)
    assert field.bounding_box()['y']+field.bounding_box()['height'] <= page.locator('.workout-session-actions').bounding_box()['y']
    assert page.locator('.workout-session-actions').bounding_box()['y']+page.locator('.workout-session-actions').bounding_box()['height']<=461
    page.screenshot(path=str(OUT/f'{engine}-workout-375-keyboard-viewport.png'))
    page.evaluate("delete visualViewport.height;visualViewport.dispatchEvent(new Event('resize'))")
    page.wait_for_timeout(100)
    page.locator('.session-exercise').last.scroll_into_view_if_needed()
    assert page.locator('.session-exercise').last.bounding_box()['y']<page.locator('.workout-session-actions').bounding_box()['y']
    before=page.locator('[data-session-set-load]').evaluate_all('(els)=>els.map(e=>e.value)')
    with page.expect_response(lambda r:'/function/treino_sessao.php' in r.url and r.request.method=='POST'):page.locator('[data-mark-all-workout]').click()
    page.wait_for_timeout(150)
    assert page.locator('[data-session-set-load]').evaluate_all('(els)=>els.map(e=>e.value)')==before, (before,page.locator('[data-session-set-load]').evaluate_all('(els)=>els.map(e=>e.value)'))
    # Invalid/missing timestamp response must render the fallback, even with legacy raw dates.
    for start in [None,'invalid',float('inf')]:
        def malformed(route):
            response=route.fetch();data=response.json();data['session']['started_at_ms']=start if start != float('inf') else 'Infinity';data['session']['data_inicio']='2026-09-08 10:00:00'
            route.fulfill(response=response,json=data)
        page.route('**/function/treino_sessao.php?*',malformed)
        page.evaluate('StrideBRWorkout.refresh()');page.wait_for_timeout(200)
        expect(page.locator('[data-session-time]')).to_have_text('00:00')
        page.unroute('**/function/treino_sessao.php?*',malformed)
    assert not errors,errors
    browser.close()
print('Workout matrix, i18n, timer fallbacks, forward loads, overrides, completed sets and reload: PASS')
