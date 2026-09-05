#!/usr/bin/env python3
from pathlib import Path
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f"Playwright indisponível: {exc}", file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
SCRIPTS_JS = ROOT / 'public/assets/js/scripts.js'
LIBRARY_JS = ROOT / 'public/assets/js/library.js'
TRANSLATIONS = {
    'pt-BR': {
        'common.cancel': 'Cancelar',
        'common.confirm': 'Confirmar',
        'common.confirm_action': 'Confirmar ação',
        'library.new_workout': 'Novo treino',
        'library.new_workout_help': 'Crie um treino reutilizável para montar seus cronogramas.',
    },
    'en': {
        'common.cancel': 'Cancel',
        'common.confirm': 'Confirm',
        'common.confirm_action': 'Confirm action',
        'library.new_workout': 'New workout',
        'library.new_workout_help': 'Create a reusable workout for schedules.',
    },
}

assertions = 0
failures = []

def check(condition, message):
    global assertions
    assertions += 1
    if not condition:
        raise AssertionError(message)

def install_i18n(page, locale, dictionary):
    page.evaluate("""payload => {
        window.StrideBRI18n = {
            locale: payload.locale,
            t: (key, values = {}, fallback = key) => {
                let text = payload.dictionary[key] ?? fallback
                Object.entries(values || {}).forEach(([name, value]) => { text = String(text).split(`{${name}}`).join(String(value ?? '')) })
                return text
            }
        }
    }""", {'locale': locale, 'dictionary': dictionary})

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for locale, dictionary in TRANSLATIONS.items():
        page = browser.new_page(viewport={'width': 1024, 'height': 700})
        errors = []
        page.on('pageerror', lambda error, bucket=errors: bucket.append(str(error)))
        page.set_content('<!doctype html><html><body></body></html>', wait_until='domcontentloaded')
        install_i18n(page, locale, dictionary)
        try:
            page.add_script_tag(path=str(SCRIPTS_JS))
            page.evaluate("() => { window.StrideBRUI.confirm('Proceed?').then(value => { window.__confirmResult = value }) }")
            check(page.locator('.ui-confirm-overlay').count() == 1, f'{locale}: confirm global não abriu')
            check(page.locator('.ui-confirm-cancel').text_content() == dictionary['common.cancel'], f'{locale}: cancelar global não localizado')
            check(page.locator('.ui-confirm-ok').text_content() == dictionary['common.confirm'], f'{locale}: confirmar global não localizado')
            check(page.locator('.ui-confirm-dialog h2').text_content() == dictionary['common.confirm_action'], f'{locale}: título global não localizado')
            page.locator('.ui-confirm-cancel').click()
            check(errors == [], f'{locale}: scripts.js gerou erro JS: {errors}')
        except Exception as exc:
            failures.append(str(exc))
        finally:
            page.close()

        page = browser.new_page(viewport={'width': 1024, 'height': 700})
        errors = []
        page.on('pageerror', lambda error, bucket=errors: bucket.append(str(error)))
        page.set_content('''<!doctype html><html><body>
        <main data-library-page data-library-active-tab="treinos">
          <a data-library-tab="treinos"></a><section data-library-view="treinos"></section><div data-library-heading-actions="treinos"></div>
          <button data-new-workout-library type="button">New</button>
          <div data-workout-library-modal hidden><h2 data-library-modal-title></h2><p data-library-modal-subtitle></p><form data-workout-library-form><input name="idtreino_modelo"><input name="titulo"><input name="codigo"><input name="foco"><input name="idmodalidade"><textarea name="descricao"></textarea><input type="checkbox" name="propagar_vinculados"></form><div data-workout-propagate></div><span data-workout-uses></span><a data-workout-exercises-link></a></div>
          <script type="application/json" data-workout-library-data>{}</script>
        </main></body></html>''', wait_until='domcontentloaded')
        install_i18n(page, locale, dictionary)
        try:
            page.add_script_tag(path=str(LIBRARY_JS))
            page.locator('[data-new-workout-library]').click()
            check(page.locator('[data-workout-library-modal]').is_visible(), f'{locale}: modal de treino da Biblioteca não abriu')
            check(page.locator('[data-library-modal-title]').text_content() == dictionary['library.new_workout'], f'{locale}: título da Biblioteca não localizado')
            check(page.locator('[data-library-modal-subtitle]').text_content() == dictionary['library.new_workout_help'], f'{locale}: ajuda da Biblioteca não localizada')
            check(errors == [], f'{locale}: library.js gerou erro JS: {errors}')
        except Exception as exc:
            failures.append(str(exc))
        finally:
            page.close()
    browser.close()

if failures:
    print('Falhas no browser i18n core:', file=sys.stderr)
    for failure in failures:
        print(f'- {failure}', file=sys.stderr)
    sys.exit(1)

print(f'✓ browser i18n core: {assertions} assertions; PT-BR/English em globals e Biblioteca')
