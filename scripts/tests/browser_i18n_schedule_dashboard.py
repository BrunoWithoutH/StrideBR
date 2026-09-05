#!/usr/bin/env python3
from pathlib import Path
import json
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f"Playwright indisponível: {exc}", file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
SCHEDULE_JS = ROOT / 'public/assets/js/cronogramas.js'
DASHBOARD_JS = ROOT / 'public/assets/js/dashboard.js'

TRANSLATIONS = {
    'pt-BR': {
        'schedule.no_file': 'Nenhum arquivo selecionado',
        'schedule.imported_fallback': 'Cronograma importado',
        'schedule.file_invalid': 'Não foi possível ler este arquivo do StrideBR.',
        'schedule.previous_month': 'Mês anterior',
        'schedule.next_month': 'Próximo mês',
        'schedule.month_load_error': 'Não foi possível carregar este mês.',
        'schedule.connection_wobbled': 'Sua conexão pode ter oscilado.',
        'schedule.retry': 'Tentar novamente',
        'schedule.add_workout_on': 'Adicionar treino em {date}',
        'common.today': 'Hoje',
        'goals.dashboard_updated': 'Painel atualizado.',
        'goals.save_dashboard_error': 'Não foi possível salvar o painel.',
    },
    'en': {
        'schedule.no_file': 'No file selected',
        'schedule.imported_fallback': 'Imported schedule',
        'schedule.file_invalid': 'Could not read this StrideBR file.',
        'schedule.previous_month': 'Previous month',
        'schedule.next_month': 'Next month',
        'schedule.month_load_error': 'Could not load this month.',
        'schedule.connection_wobbled': 'Your connection may have dropped.',
        'schedule.retry': 'Try again',
        'schedule.add_workout_on': 'Add workout on {date}',
        'common.today': 'Today',
        'goals.dashboard_updated': 'Dashboard updated.',
        'goals.save_dashboard_error': 'Could not save the dashboard.',
    },
}

assertions = 0
failures = []

def check(condition, message):
    global assertions
    assertions += 1
    if not condition:
        raise AssertionError(message)

def load_html(page, html):
    page.set_content(html, wait_until='domcontentloaded')
    page.evaluate("""() => {
        const store = () => {
            const values = new Map()
            return {
                getItem: key => values.has(String(key)) ? values.get(String(key)) : null,
                setItem: (key, value) => values.set(String(key), String(value)),
                removeItem: key => values.delete(String(key)),
                clear: () => values.clear()
            }
        }
        Object.defineProperty(window, 'localStorage', {value: store()})
        Object.defineProperty(window, 'sessionStorage', {value: store()})
        Object.defineProperty(document, 'cookie', {get: () => '', set: () => true})
        history.replaceState = () => {}
        history.pushState = () => {}
    }""")

def install_i18n(page, locale, dictionary):
    page.evaluate("""payload => {
        const tag = payload.locale === 'en' ? 'en-US' : 'pt-BR'
        const replace = (text, values = {}) => Object.entries(values || {}).reduce((result, [key, value]) => result.split(`{${key}}`).join(String(value ?? '')), String(text ?? ''))
        window.StrideBRI18n = {
            locale: payload.locale,
            t: (key, values = {}, fallback = key) => replace(payload.dictionary[key] ?? fallback, values),
            tn: (oneKey, otherKey, count, values = {}) => replace(payload.dictionary[Number(count) === 1 ? oneKey : otherKey] ?? (Number(count) === 1 ? oneKey : otherKey), {...values, count}),
            weekdayShort: index => new Intl.DateTimeFormat(tag, {weekday:'short'}).format(new Date(2023, 0, 1 + Number(index || 0))).replace(/\\.$/, ''),
            date: (value, options = {}) => {
                const match = String(value || '').match(/^(\\d{4})-(\\d{2})-(\\d{2})/)
                const parsed = match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : new Date(value)
                return new Intl.DateTimeFormat(tag, options).format(parsed)
            },
            monthYear: value => {
                const match = String(value || '').match(/^(\\d{4})-(\\d{2})/)
                const parsed = match ? new Date(Number(match[1]), Number(match[2]) - 1, 1) : new Date(value)
                return new Intl.DateTimeFormat(tag, {month:'long', year:'numeric'}).format(parsed)
            }
        }
    }""", {'locale': locale, 'dictionary': dictionary})

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for locale, dictionary in TRANSLATIONS.items():
        page = browser.new_page(viewport={'width': 1100, 'height': 760})
        errors = []
        page.on('pageerror', lambda error, bucket=errors: bucket.append(str(error)))
        load_html(page, '''<!doctype html><html><body>
          <button data-view="month" class="is-active"></button>
          <section data-calendar-view="month"></section>
          <section data-schedule-create hidden>
            <div data-schedule-create-options></div>
            <h2 data-schedule-create-title></h2>
            <form data-schedule-create-form="import"></form>
            <input type="file" data-schedule-import-file>
            <div data-schedule-import-preview hidden></div>
            <div data-schedule-import-error hidden></div>
            <button data-schedule-import-submit disabled></button>
            <span data-schedule-file-name></span>
            <strong data-import-preview-name></strong>
            <span data-import-preview-workouts></span>
            <span data-import-preview-exercises></span>
          </section>
          <section data-month-calendar-shell data-current-month="2026-09" data-schedule-id="schedule-1">
            <h2 data-month-title></h2>
            <button data-month-nav aria-label=""></button>
            <div data-month-grid></div>
          </section>
        </body></html>''')
        install_i18n(page, locale, dictionary)
        page.evaluate("""() => {
            window.StrideBRNet = {fetch: async () => ({ok:true, json: async () => ({ok:true, ocorrencias:[], agendados:[]})})}
            window.StrideBRUI = {notify: () => {}, confirm: async () => true}
        }""")
        try:
            page.add_script_tag(path=str(SCHEDULE_JS))
            page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
            page.wait_for_timeout(80)
            expected_month = 'September 2026' if locale == 'en' else 'Setembro de 2026'
            check(page.locator('[data-month-title]').text_content() == expected_month, f'{locale}: mês do cronograma não respeitou locale')

            valid_payload = json.dumps({'format':'stridebr-schedule','version':1,'cronograma':{},'treinos':[]})
            page.locator('[data-schedule-import-file]').set_input_files({'name':'schedule.stridebr.json','mimeType':'application/json','buffer':valid_payload.encode()})
            page.wait_for_timeout(50)
            check(page.locator('[data-import-preview-name]').text_content() == dictionary['schedule.imported_fallback'], f'{locale}: fallback do cronograma importado não localizado')
            check(page.locator('[data-schedule-import-preview]').get_attribute('hidden') is None, f'{locale}: preview válido não abriu')

            page.locator('[data-schedule-import-file]').set_input_files({'name':'invalid.stridebr.json','mimeType':'application/json','buffer':b'{}'})
            page.wait_for_timeout(50)
            check(page.locator('[data-schedule-import-error]').text_content() == dictionary['schedule.file_invalid'], f'{locale}: erro de importação não localizado')
            check(errors == [], f'{locale}: cronogramas.js gerou erro JS: {errors}')
        except Exception as exc:
            failures.append(str(exc))
        finally:
            page.close()

        page = browser.new_page(viewport={'width': 1000, 'height': 700})
        errors = []
        page.on('pageerror', lambda error, bucket=errors: bucket.append(str(error)))
        load_html(page, '''<!doctype html><html><body>
          <main data-dashboard-root data-dashboard-csrf="csrf"></main>
          <div data-dashboard-modules><section data-dashboard-module="progress"></section></div>
          <dialog data-dashboard-customize-dialog open>
            <div data-dashboard-customize-list>
              <div data-dashboard-customize-item="progress"><input type="checkbox" data-dashboard-module-visible checked><button data-dashboard-move="up"></button><button data-dashboard-move="down"></button></div>
            </div>
            <button type="button" data-dashboard-customize-save>save</button>
            <button type="button" data-dashboard-customize-reset>reset</button>
          </dialog>
        </body></html>''')
        install_i18n(page, locale, dictionary)
        page.evaluate("""() => {
            window.StrideBRNet = {fetch: async () => ({ok:true, json: async () => ({ok:true, preferences:{order:['progress'], hidden:[]}})})}
            window.StrideBRUI = {notify: message => { window.__dashboardNotice = message }}
        }""")
        try:
            page.add_script_tag(path=str(DASHBOARD_JS))
            page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
            page.locator('[data-dashboard-customize-save]').click()
            page.wait_for_function("window.__dashboardNotice !== undefined")
            check(page.evaluate('window.__dashboardNotice') == dictionary['goals.dashboard_updated'], f'{locale}: sucesso do dashboard não localizado')
            check(errors == [], f'{locale}: dashboard.js gerou erro JS: {errors}')
        except Exception as exc:
            failures.append(str(exc))
        finally:
            page.close()
    browser.close()

if failures:
    print('Falhas no browser i18n schedule/dashboard:', file=sys.stderr)
    for failure in failures:
        print(f'- {failure}', file=sys.stderr)
    sys.exit(1)

print(f'✓ browser i18n schedule/dashboard: {assertions} assertions; PT-BR/English')
