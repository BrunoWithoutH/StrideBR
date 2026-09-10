from pathlib import Path
import os
import shutil
import subprocess
from playwright.sync_api import sync_playwright
ROOT = Path(__file__).resolve().parents[2]
OUT = Path('/tmp/stridebr-integration-cards'); OUT.mkdir(exist_ok=True)
server = subprocess.Popen(['php','-S','127.0.0.1:8097','-t',str(ROOT/'public'),str(ROOT/'scripts/tests/integration_cards_fixture.php')], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
checks = 0
engine = os.environ.get('STRIDEBR_TEST_BROWSER', 'chromium')
try:
    with sync_playwright() as p:
        launch = {'args':['--no-sandbox']} if engine == 'chromium' else {}
        executable = os.environ.get('STRIDEBR_TEST_BROWSER_EXECUTABLE')
        if executable:
            launch['executable_path'] = executable
            browser = getattr(p, engine).launch(**launch)
        else:
            try:
                browser = getattr(p, engine).launch(**launch)
            except Exception as error:
                fallback = shutil.which('chromium') or shutil.which('chromium-browser') or shutil.which('google-chrome') if engine == 'chromium' else None
                if not fallback or 'Executable doesn\'t exist' not in str(error): raise
                launch['executable_path'] = fallback
                browser = getattr(p, engine).launch(**launch)
        page = browser.new_page()
        errors = []; page.on('pageerror',lambda e: errors.append(str(e)))
        for locale in ['pt-BR','en']:
            for width in [360,375,390,620,679,680,681,768,1024,1440]:
                for theme in ['light','dark']:
                    page.set_viewport_size({'width':width,'height':1000})
                    page.goto(f'http://127.0.0.1:8097/__integration_cards?lang={locale}')
                    page.evaluate('(theme)=>document.documentElement.dataset.theme=theme',theme)
                    strava = page.locator('[data-integration-provider=strava]')
                    assert ('Automatic sync' if locale=='en' else 'Sincronização automática') in strava.inner_text(); checks += 1
                    assert 'Atleta Teste' in strava.inner_text(); checks += 1
                    assert ('Last sync failed' if locale=='en' else 'Erro na última sincronização') in page.locator('[data-integration-provider=polar]').inner_text(); checks += 1
                    assert ('Reauthorization needed' if locale=='en' else 'Precisa reautorizar') in page.locator('[data-integration-provider=google_health]').inner_text(); checks += 1
                    assert ('Not connected' if locale=='en' else 'Não conectado') in page.locator('[data-integration-provider=suunto]').inner_text(); checks += 1
                    assert ('Unavailable' if locale=='en' else 'Indisponível') in page.locator('[data-integration-provider=garmin]').inner_text(); checks += 1
                    assert ('Manual sync' if locale=='en' else 'Sincronização manual') in page.locator('[data-integration-provider=coros]').inner_text(); checks += 1
                    manual = strava.locator('[data-integration-sync] button')
                    assert manual.evaluate('e=>getComputedStyle(e).appearance') == 'none'; checks += 1
                    assert manual.bounding_box()['height'] >= 40; checks += 1
                    connect = page.locator('[data-integration-provider=suunto] .integration-button')
                    assert abs(manual.bounding_box()['height'] - connect.bounding_box()['height']) < 1; checks += 1
                    more = strava.locator('.integration-more').bounding_box()
                    assert abs(more['width'] - more['height']) < 1 and abs(more['height'] - manual.bounding_box()['height']) < 1; checks += 1
                    if width > 680:
                        garmin = page.locator('[data-integration-provider=garmin]').bounding_box()
                        strava_box = strava.bounding_box()
                        assert abs(garmin['y'] - strava_box['y']) < 1 and abs(garmin['height'] - strava_box['height']) < 1; checks += 1
                    else:
                        cards = page.locator('.integration-card').all()
                        assert all(card.bounding_box()['width'] <= width for card in cards); checks += 1
                        assert all(card.evaluate('e=>getComputedStyle(e).minHeight') == '0px' for card in cards); checks += 1
                    assert not strava.locator('.integration-disconnect button').is_visible(); checks += 1
                    strava.locator('.integration-menu > summary').click()
                    assert strava.locator('.integration-disconnect button').is_visible(); checks += 1
                    strava.locator('.integration-preferences > summary').click()
                    assert strava.locator('input[name=sync_activities]').is_checked(); checks += 1
                    box=strava.locator('.integration-menu-panel').bounding_box()
                    assert box['x'] >= 0 and box['x']+box['width'] <= width; checks += 1
                    assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'); checks += 1
                    strava.locator('.integration-preferences > summary').press('Escape')
                    assert not strava.locator('.integration-menu').evaluate('e=>e.open'); checks += 1
                    if width in [390,1280] and locale=='pt-BR':
                        page.screenshot(path=str(OUT/f'{engine}-cards-{width}-{theme}.png'),full_page=True)
        # Observe real submit feedback without allowing any provider request.
        page.route('**/function/integration-action.php', lambda route: route.fulfill(status=200,body='<p>Fixture completed</p>'))
        page.locator('[data-integration-provider=strava] [data-integration-sync]').evaluate("form=>{form.addEventListener('submit',e=>e.preventDefault()); form.requestSubmit();}")
        assert page.locator('[data-integration-provider=strava] .integration-status').inner_text() == 'Syncing…'; checks += 1
        assert page.locator('[data-integration-provider=strava] [data-integration-sync] button').is_disabled(); checks += 1
        assert not errors, errors
        browser.close()
    print(f'PASS integration browser ({engine}): {checks} checks; screenshots {OUT}')
finally:
    server.terminate(); server.wait(timeout=10)
