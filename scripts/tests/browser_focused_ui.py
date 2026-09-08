from pathlib import Path
from playwright.sync_api import sync_playwright
OUT=Path('/tmp/stridebr-focused-ui');OUT.mkdir(exist_ok=True)
BASE='http://localhost:8080'
with sync_playwright() as p:
 b=p.chromium.launch(args=['--no-sandbox']);page=b.new_page(viewport={'width':1440,'height':1000});errors=[]
 page.on('pageerror',lambda e:errors.append(str(e)))
 for width in [1440,390]:
  page.set_viewport_size({'width':width,'height':900})
  page.goto(BASE+'/signup.php?lang=en')
  assert page.locator('[data-next-step]').is_visible()
  assert not page.locator('[data-finish-step]').is_visible()
  page.locator('[data-signup-sport-family-open=running]').click()
  panel=page.locator('[data-signup-sport-family-panel=running]')
  assert panel.is_visible()
  assert not page.locator('[data-signup-sport-families]').is_visible()
  assert panel.bounding_box()['y']<500
  assert 'Running' in panel.inner_text()
  panel.locator('input').first.check()
  panel.locator('[data-signup-sport-more]').click()
  assert panel.locator('[data-signup-sport-more-list]').is_visible()
  page.screenshot(path=str(OUT/f'signup-{width}.png'),full_page=True)
  page.locator('[data-skip-to-account]').click()
  assert page.locator('[data-finish-step]').is_visible()
  assert page.locator('[data-finish-step]').is_disabled()
  assert not page.locator('[data-next-step]').is_visible()
  for name,value in [('NomeUsuario','Browser Runner'),('EmailUsuario','browser@example.invalid'),('SenhaUsuario','Browser-pass-123!'),('ConfirmarSenhaUsuario','Browser-pass-123!')]:
   page.locator('[name='+name+']').fill(value)
  page.locator('[name=TermosUsuario]').check()
  assert page.locator('[data-finish-step]').is_enabled()
  page.screenshot(path=str(OUT/f'account-{width}.png'),full_page=True)
 page.goto(BASE+'/login.php')
 page.locator('[name=UEmail]').fill('round_ux_athlete@alpha-test.invalid');page.locator('[name=USenha]').fill('Planning-browser-123!');page.locator('button[name=submit]').click();page.wait_for_url('**/home.php')
 for width in [1440,390]:
  page.set_viewport_size({'width':width,'height':1000})
  page.goto(BASE+'/home.php?lang=en');page.evaluate("document.documentElement.dataset.theme='dark'")
  assert page.locator('.dashboard-week-day').first.evaluate("e=>getComputedStyle(e).backgroundColor")!='rgb(255, 255, 255)'
  page.locator('.dashboard-week-panel').screenshot(path=str(OUT/f'home-dark-{width}.png'))
  page.locator('[data-week-prev]').click();assert 'week=-1' in page.url
  assert '3 activities' in page.locator('.dashboard-week-summary').inner_text()
  page.locator('[data-week-next]').click();assert 'week=0' in page.url
  assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
 page.goto(BASE+'/user/onboarding.php?lang=en')
 page.locator('[data-signup-sport-family-open=running]').click()
 assert page.locator('[data-signup-sport-family-panel=running]').is_visible()
 page.goto(BASE+'/user/settings.php?lang=en')
 page.locator('#esportes > summary').click()
 page.locator('[data-settings-sport-family-open=running]').click()
 assert not page.locator('[data-settings-sport-family-grid]').is_visible()
 page.screenshot(path=str(OUT/'settings-sports.png'),full_page=True)
 for width in [1440,390]:
  page.set_viewport_size({'width':width,'height':900})
  page.goto(BASE+'/user/metas.php?new=1&lang=en')
  assert page.locator('[data-generic-sport-trigger]').count()>0
  page.locator('[data-generic-sport-trigger]').first.click()
  page.locator('[data-generic-sport-family-open=running]').first.click()
  page.locator('[data-generic-sport-family-panel=running] [data-generic-sport-option]').first.click()
  assert 'Running' in page.locator('[data-generic-sport-trigger]').first.inner_text()
  page.screenshot(path=str(OUT/f'selector-{width}.png'),full_page=True)
 assert not errors,errors
 print('PASS real signup desktop/mobile, CTA, onboarding, settings, home dark + week navigation; screenshots',OUT)
 b.close()
