from pathlib import Path
from playwright.sync_api import sync_playwright
import os
OUT=Path('/tmp/stridebr-workout-screenshots');OUT.mkdir(exist_ok=True)
with sync_playwright() as p:
    engine=os.environ.get('STRIDEBR_BROWSER_ENGINE','chromium')
    browser=getattr(p,engine).launch(headless=True)
    page=browser.new_page(viewport={'width':375,'height':812})
    page.goto('http://localhost:8080/login.php?lang=pt-BR')
    page.locator('[name=UEmail]').fill('round_ux_athlete@alpha-test.invalid')
    page.locator('[name=USenha]').fill('Planning-browser-123!')
    page.locator('button[name=submit]').click();page.wait_for_url('**/home.php')
    page.goto('http://localhost:8080/user/cronogramatreinos.php?view=week&lang=pt-BR')
    page.locator('[data-view="week"]').click()
    week=page.locator('[data-calendar-view="week"]')
    for width in [320,360,375,390,768,1024,1440]:
        page.set_viewport_size({'width':width,'height':812});page.wait_for_timeout(200)
        assert page.evaluate('document.documentElement.scrollWidth<=document.documentElement.clientWidth'),width
        if width<=390:
            assert week.evaluate('(e)=>e.scrollHeight<=e.clientHeight+1'),week.evaluate('(e)=>[e.scrollHeight,e.clientHeight]')
            assert week.evaluate('(e)=>getComputedStyle(e).overflowY') not in ['auto','scroll']
            assert week.evaluate('(e)=>e.scrollWidth>e.clientWidth')
            assert page.evaluate('document.documentElement.scrollHeight>innerHeight')
            assert week.locator('.day-header').first.bounding_box()['width']>=240
            week.evaluate('(e)=>e.scrollLeft=280');assert week.evaluate('(e)=>e.scrollLeft')>0
        if width in [375,1440]:
            week.scroll_into_view_if_needed();page.screenshot(path=str(OUT/f'{engine}-week-{width}.png'),full_page=True)
    browser.close()
print('Week mobile natural vertical page scroll, horizontal day navigation and desktop: PASS')
