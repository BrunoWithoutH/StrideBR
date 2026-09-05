#!/usr/bin/env python3
from pathlib import Path
import re
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
SOURCE = (ROOT / 'public/assets/js/onboarding.js').read_text()

HTML = '''<!doctype html>
<html lang="en" data-locale="en"><head><meta charset="utf-8"></head><body>
<main class="onboarding-card signup-onboarding-card" data-onboarding data-initial-step="0">
  <div><span data-step-kind>Optional step</span><button type="button" data-skip-to-account>Skip personalization</button><span data-step-label>Step 1 of 5</span></div>
  <div><span data-progress-bar></span></div>
  <form>
    <section data-step="0" class="is-active">
      <label><input type="checkbox" name="sports[]" value="corrida"><span>Running</span></label>
    </section>
    <section data-step="1" hidden><input name="goals[]" value="routine"></section>
    <section data-step="2" hidden><select name="experience"><option value="">Prefer not to say</option></select><select name="weekly_frequency"><option value="0">Not set</option></select></section>
    <section data-step="3" hidden><input name="tracking[]" value="distance"></section>
    <section data-step="4" hidden><input name="NomeUsuario"><div data-summary-block hidden><div data-onboarding-summary></div></div></section>
    <button type="button" data-prev-step>Back</button>
    <button type="button" data-next-step>Continue</button>
    <button type="submit" data-finish-step hidden>Create account</button>
  </form>
</main>
</body></html>'''

assertions = 0
failures = []

def check(value, message):
    global assertions
    assertions += 1
    if not value:
        raise AssertionError(message)

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    page = browser.new_page(viewport={'width': 900, 'height': 760})
    errors = []
    page.on('pageerror', lambda exc: errors.append(str(exc)))
    try:
        page.set_content(HTML, wait_until='domcontentloaded')
        page.add_script_tag(content="window.StrideBRI18n={locale:'en',t:(key)=>key};")
        page.add_script_tag(content=SOURCE)
        page.wait_for_timeout(30)

        text = page.locator('body').inner_text()
        check('Optional step' in text, 'optional step não recebeu fallback humano')
        check('Step 1 of 5' in text, f'contagem inicial quebrada: {text}')
        check(page.locator('[data-next-step]').inner_text() == 'Skip step', 'CTA sem esporte não virou Skip step')
        check(not re.search(r'\b(?:onboarding|auth|common)\.[A-Za-z0-9_.-]+\b', text, re.I), f'key técnica vazou na UI: {text}')

        sport = page.locator('input[name="sports[]"]')
        sport.check()
        page.wait_for_timeout(10)
        check(page.locator('[data-next-step]').inner_text() == 'Continue', 'CTA com esporte selecionado não virou Continue')

        page.locator('[data-next-step]').click()
        page.wait_for_timeout(10)
        check(page.locator('[data-step-label]').inner_text() == 'Step 2 of 5', 'contagem não avançou para Step 2 of 5')
        check(page.locator('[data-step-kind]').inner_text() == 'Optional step', 'etapa intermediária perdeu label opcional')

        page.locator('[data-skip-to-account]').click()
        page.wait_for_timeout(10)
        check(page.locator('[data-step-label]').inner_text() == 'Step 5 of 5', 'skip personalization não atualizou contagem final')
        check(page.locator('[data-step-kind]').inner_text() == 'Account', 'etapa final não virou Account')
        check(page.locator('[data-next-step]').is_hidden(), 'Continue/Skip step deveria sumir na etapa da conta')
        check(page.locator('[data-finish-step]').is_visible(), 'Create account deveria aparecer somente na etapa final')
        check(page.locator('[data-finish-step]').inner_text() == 'Create account', 'CTA final foi alterado indevidamente')
        final_text = page.locator('body').inner_text()
        check(not re.search(r'\b(?:onboarding|auth|common)\.[A-Za-z0-9_.-]+\b', final_text, re.I), f'key técnica vazou após navegação: {final_text}')
        check(not errors, f'erros JS: {errors}')
    except Exception as exc:
        failures.append(str(exc))
    finally:
        page.close()
        browser.close()

if failures:
    print('Falhas no browser onboarding i18n:', file=sys.stderr)
    for failure in failures:
        print('- ' + failure, file=sys.stderr)
    sys.exit(1)

print(f'✓ onboarding i18n browser: {assertions} assertions; EN fallbacks/step count/CTA')
