#!/usr/bin/env python3
from pathlib import Path
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f"Playwright indisponível: {exc}", file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / 'public/assets/js/quick-tools.js'
HTML = '''<!doctype html><html><body>
<div data-global-tools>
  <div data-pinned-tools></div>
  <div data-quick-tools-modal hidden><button data-quick-tools-close></button></div>
  <button data-pin-tool="timer"></button><button data-pin-tool="stopwatch"></button><button data-pin-tool="sets"></button>
  <button data-quick-tool-tab="timer"></button><button data-quick-tool-tab="stopwatch"></button><button data-quick-tool-tab="sets"></button>
  <section data-quick-tool-view="timer"></section><section data-quick-tool-view="stopwatch"></section><section data-quick-tool-view="sets"></section>
  <output data-quick-timer-output></output><input data-quick-timer-minutes value="1"><input data-quick-timer-seconds value="0">
  <button data-quick-timer-start></button><button data-quick-timer-pause></button><button data-quick-timer-reset></button>
  <output data-stopwatch-output></output><button data-stopwatch-toggle></button><button data-stopwatch-reset></button>
  <output data-sets-output></output><button data-sets-plus></button><button data-sets-minus></button><button data-sets-reset></button>
</div>
</body></html>'''

TRANSLATIONS = {
    'pt-BR': {
        'quick_tools.timer': 'Timer', 'quick_tools.stopwatch': 'Cronômetro', 'quick_tools.sets': 'Séries',
        'quick_tools.running': 'Rodando', 'quick_tools.resume': 'Continuar', 'quick_tools.start': 'Iniciar', 'quick_tools.pause': 'Pausar',
    },
    'en': {
        'quick_tools.timer': 'Timer', 'quick_tools.stopwatch': 'Stopwatch', 'quick_tools.sets': 'Sets',
        'quick_tools.running': 'Running', 'quick_tools.resume': 'Resume', 'quick_tools.start': 'Start', 'quick_tools.pause': 'Pause',
    },
}

assertions = 0
failures = []

def check(condition, message):
    global assertions
    assertions += 1
    if not condition:
        raise AssertionError(message)

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for locale, dictionary in TRANSLATIONS.items():
        page = browser.new_page(viewport={'width': 390, 'height': 844})
        errors = []
        page.on('pageerror', lambda error, bucket=errors: bucket.append(str(error)))
        page.set_content(HTML, wait_until='domcontentloaded')
        page.evaluate("""payload => {
            const store = new Map()
            Object.defineProperty(window, 'localStorage', {value: {
                getItem: key => store.has(String(key)) ? store.get(String(key)) : null,
                setItem: (key, value) => store.set(String(key), String(value)),
                removeItem: key => store.delete(String(key)),
                clear: () => store.clear()
            }})
            window.StrideBRI18n = {
                locale: payload.locale,
                t: (key, values = {}, fallback = key) => payload.dictionary[key] ?? fallback
            }
        }""", {'locale': locale, 'dictionary': dictionary})
        try:
            page.add_script_tag(path=str(SCRIPT))
            start = page.locator('[data-quick-timer-start]')
            pause = page.locator('[data-quick-timer-pause]')
            stopwatch = page.locator('[data-stopwatch-toggle]')
            check(start.text_content() == dictionary['quick_tools.start'], f'{locale}: estado inicial do timer não localizado')
            check(stopwatch.text_content() == dictionary['quick_tools.start'], f'{locale}: estado inicial do cronômetro não localizado')
            start.click()
            check(start.text_content() == dictionary['quick_tools.running'], f'{locale}: estado rodando não localizado')
            pause.click()
            check(start.text_content() == dictionary['quick_tools.resume'], f'{locale}: estado continuar não localizado')
            stopwatch.click()
            check(stopwatch.text_content() == dictionary['quick_tools.pause'], f'{locale}: estado pausar não localizado')
            check(errors == [], f'{locale}: erro JS nas ferramentas rápidas: {errors}')
        except Exception as exc:
            failures.append(str(exc))
        finally:
            page.close()
    browser.close()

if failures:
    print('Falhas no browser i18n quick tools:', file=sys.stderr)
    for failure in failures:
        print(f'- {failure}', file=sys.stderr)
    sys.exit(1)

print(f'✓ browser i18n quick tools: {assertions} assertions; PT-BR/English por seletores')
