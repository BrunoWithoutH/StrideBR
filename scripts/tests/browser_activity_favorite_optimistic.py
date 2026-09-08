#!/usr/bin/env python3
from pathlib import Path
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f"Playwright indisponível: {exc}", file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
HTML = '''<!doctype html><html lang="pt-BR"><body>
<main data-activities-page data-csrf-token="csrf"></main>
<select id="modalidade"><option value="run">Corrida</option></select>
<div data-sport-combobox>
  <button type="button" data-sport-trigger><span data-sport-current>Corrida</span></button>
  <div data-sport-popover hidden>
    <input data-sport-search>
    <div data-sport-family-browser>
      <div data-sport-family-grid></div>
      <section data-sport-family-panel="run">
        <div data-sport-row>
          <button type="button" data-sport-option data-sport-id="run" data-sport-name="Corrida" data-sport-slug="corrida" data-sport-family="run" data-sport-favorite="0">Corrida</button>
          <button type="button" data-toggle-sport-favorite data-sport-id="run" aria-pressed="false">★</button>
        </div>
        <div data-sport-row>
          <button type="button" data-sport-option data-sport-id="run" data-sport-name="Corrida" data-sport-slug="corrida" data-sport-family="run" data-sport-favorite="0">Corrida duplicada</button>
          <button type="button" data-toggle-sport-favorite data-sport-id="run" aria-pressed="false">★</button>
        </div>
      </section>
    </div>
  </div>
</div>
</body></html>'''

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    page = browser.new_page(viewport={'width': 900, 'height': 700})
    errors = []
    page.on('pageerror', lambda exc: errors.append(str(exc)))
    page.set_content(HTML)
    page.evaluate('''() => {
      window.__favorite = {calls:0, resolve:null, reject:false, notices:[]};
      window.StrideBRI18n = {locale:'pt-BR', t:(key,_values,fallback)=>fallback || key, tn:()=>'', number:v=>String(v), sport:(_s,f)=>f};
      window.StrideBRUI = {notify:(message,type)=>window.__favorite.notices.push({message,type})};
      window.fetch = () => {
        window.__favorite.calls++;
        return new Promise(resolve => { window.__favorite.resolve = () => resolve({ok:!window.__favorite.reject, json:async()=>({ok:!window.__favorite.reject})}); });
      };
    }''')
    page.add_script_tag(path=str(ROOT / 'public/assets/js/atividades.js'))
    page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
    page.wait_for_timeout(30)
    n = 0
    def check(value, message):
        nonlocal_dummy = None
        global n
        n += 1
        if not value:
            raise AssertionError(message)

    buttons = page.locator('[data-toggle-sport-favorite][data-sport-id="run"]')
    options = page.locator('[data-sport-option][data-sport-id="run"]')
    buttons.nth(0).evaluate('el => el.click()')
    page.wait_for_timeout(10)
    check(page.evaluate('__favorite.calls') == 1, 'request inicia imediatamente')
    check(all('is-favorite' in (buttons.nth(i).get_attribute('class') or '') for i in range(buttons.count())), 'todos os controles refletem favorito antes da resposta')
    check(all(buttons.nth(i).get_attribute('aria-pressed') == 'true' for i in range(buttons.count())), 'aria-pressed acompanha estado otimista')
    check(all(options.nth(i).get_attribute('data-sport-favorite') == '1' for i in range(options.count())), 'opções duplicadas compartilham estado otimista')
    check(all(buttons.nth(i).is_disabled() for i in range(buttons.count())), 'controles ficam protegidos contra duplo clique durante request')
    page.evaluate('__favorite.resolve()')
    page.wait_for_timeout(20)
    check(all(not buttons.nth(i).is_disabled() for i in range(buttons.count())), 'sucesso libera controles')
    check(all(buttons.nth(i).get_attribute('aria-pressed') == 'true' for i in range(buttons.count())), 'sucesso preserva estado otimista')

    page.evaluate('__favorite.reject = true')
    buttons.nth(1).evaluate('el => el.click()')
    page.wait_for_timeout(10)
    check(all(buttons.nth(i).get_attribute('aria-pressed') == 'false' for i in range(buttons.count())), 'segunda ação muda estado antes da resposta')
    page.evaluate('__favorite.resolve()')
    page.wait_for_timeout(20)
    check(all(buttons.nth(i).get_attribute('aria-pressed') == 'true' for i in range(buttons.count())), 'falha faz rollback do favorito')
    check(all(options.nth(i).get_attribute('data-sport-favorite') == '1' for i in range(options.count())), 'rollback restaura dados usados pela ordenação')
    check(page.evaluate("__favorite.notices.some(x => x.type === 'error')"), 'falha comunica erro curto')
    check(not errors, 'sem erros JavaScript')
    browser.close()
    print(f'✓ browser activity favorite optimistic: {n} assertions')
