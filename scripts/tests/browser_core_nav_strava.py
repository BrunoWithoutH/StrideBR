#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'docs' / 'reports' / 'screenshots' / 'CORE_NAV_STRAVA_2026-09-10'
OUT.mkdir(parents=True, exist_ok=True)
CSS = '\n'.join((ROOT / p).read_text() for p in [
    'public/assets/css/style.css',
    'public/assets/css/ui-refresh.css',
    'public/assets/css/atividades.css',
])
SCRIPTS = (ROOT / 'public/assets/js/scripts.js').read_text()
ACTIVITIES = (ROOT / 'public/assets/js/atividades.js').read_text()
INTEGRATIONS = (ROOT / 'public/assets/js/integrations.js').read_text()

HTML = '''<!doctype html><html lang="pt-BR" data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"></head><body>
<header class="site-header"><div class="header-inner"><a class="brand-link" href="#">StrideBR</a><div class="usersection">
<details data-header-menu="toggle" class="header-notification-menu"><summary class="header-notification-button" aria-label="Notificações">N</summary><div class="header-notification-popover">Notificações</div></details>
<details data-header-menu="toggle" class="user-menu desktop-account-menu"><summary aria-label="Conta"><img class="userimage" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='34' height='34'%3E%3Crect width='34' height='34' rx='17' fill='%2340507c'/%3E%3C/svg%3E"></summary><div class="user-menu-content"><div class="user-menu-identity"><img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='42' height='42'%3E%3Crect width='42' height='42' rx='21' fill='%2340507c'/%3E%3C/svg%3E"><span><strong>Bruno</strong><small>@bruno</small><a href="#profile">Ver meu perfil</a></span></div><div class="user-menu-section"><a href="#connections">Conexões e integrações</a></div></div></details>
<details data-header-menu="toggle" class="mobile-global-menu"><summary aria-label="Menu"><svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"></path></svg></summary><button class="mobile-global-menu-backdrop" type="button" data-header-menu-close aria-label="Fechar"></button><div class="mobile-global-menu-content"><div class="mobile-global-menu-head"><strong>Menu</strong><span>@bruno</span></div><div class="user-menu-section"><a href="#friends">Amigos</a><a href="#trainer">Treinador e atletas</a></div><div class="user-menu-section"><a href="#connections">Conexões e integrações</a><a href="#settings">Configurações</a></div></div></details>
</div></div></header>
<main class="main-content"><div class="page-shell">
<nav class="settings-tabs segmented-nav"><a href="#profile">Perfil</a><a href="#prefs">Preferências</a><a href="#connections" class="is-active" aria-current="page">Conexões</a><a href="#account">Conta e segurança</a></nav>
<section class="settings-connections"><div class="integrations-grid">
<article class="integration-card is-connected" data-integration-provider="strava" data-strava-state="history" data-syncing-label="Sincronizando…"><div class="integration-card-main"><span class="integration-provider-mark is-wordmark">Strava</span><div class="integration-card-copy"><div class="integration-card-title"><h3>Strava</h3></div><small class="integration-athlete">Atleta Teste</small><div class="integration-strava-state" aria-live="polite"><strong>Importando histórico</strong><p>Suas atividades recentes já estão disponíveis. O restante do seu histórico está sendo importado em segundo plano.</p><small>436 atividades importadas · histórico alcançado até 18/03/2022</small></div><small>Última sincronização: agora</small></div></div><div class="integration-card-actions"><form data-integration-sync><button type="submit" class="integration-button">Sincronizar agora</button></form><details class="integration-menu"><summary class="integration-button integration-more">•••</summary><div class="integration-menu-panel"><details class="integration-preferences"><summary class="integration-menu-action">Preferências</summary><form class="integration-preferences-form"><label><input type="checkbox" name="sync_activities" checked> Sincronização automática</label></form></details></div></details></div></article>
<article class="integration-card" data-integration-provider="strava-disconnected"><div class="integration-card-main"><span class="integration-provider-mark is-wordmark">Strava</span><div class="integration-card-copy"><div class="integration-card-title"><h3>Strava</h3><span class="integration-status">Não conectado</span></div><p>Traga suas atividades e histórico autorizado.</p></div></div><div class="integration-card-actions"><a class="integration-button integration-strava-connect" href="#connect">Conectar com Strava</a></div></article>
</div></section>
<section class="activity-history" data-activity-history data-initial-state="ready" data-initial-total="3" data-initial-cursor=""><div class="activity-history-toolbar"><div class="activity-history-toolbar-normal" data-history-toolbar-normal><div class="activity-history-heading-copy"><h2>Histórico</h2><span>Mais recentes primeiro</span></div><div class="activity-history-filters"><input data-history-search type="search"><select data-history-sport><option>Todos</option></select><button type="button" class="activity-secondary-button" data-bulk-toggle aria-pressed="false">Selecionar</button></div></div><div class="activity-bulk-toolbar" data-bulk-toolbar hidden><strong data-bulk-count>0 selecionadas</strong><div class="activity-bulk-toolbar-actions"><button type="button" class="activity-secondary-button" data-bulk-select-visible>Selecionar carregadas</button><button type="button" class="activity-secondary-button" data-bulk-edit disabled>Editar</button><button type="button" class="activity-secondary-button is-danger" data-bulk-delete disabled>Excluir</button><button type="button" class="activity-secondary-button" data-bulk-cancel>Cancelar</button></div></div></div>
<dialog class="activity-bulk-dialog" data-bulk-dialog><form class="activity-bulk-dialog-form" data-bulk-bar><header class="activity-bulk-dialog-head"><div><span>0 selecionadas</span><h3>Editar selecionadas</h3></div><button type="button" data-bulk-dialog-close>×</button></header><div class="activity-bulk-fields"><div class="activity-bulk-sport-field"><span>Modalidade</span><select name="idmodalidade"><option value="">Não alterar</option><option value="run">Corrida</option></select></div><label>Duração<span class="activity-bulk-duration"><select name="duracao_modo" data-bulk-duration-mode><option value="keep">Não alterar</option><option value="set">Definir</option></select><span data-bulk-duration-value hidden><input name="duracao_minutos" type="number"></span></span></label><label>Visibilidade<select name="visibilidade"><option value="">Não alterar</option><option value="privado">Privado</option></select></label></div><footer class="activity-bulk-dialog-actions"><button type="button" data-bulk-dialog-close>Cancelar</button><button type="submit" disabled>Aplicar</button></footer></form></dialog>
<div class="activity-list" data-activity-list>
<article class="activity-list-row" data-history-row data-activity-id="a1"><label class="activity-row-select"><input type="checkbox" data-bulk-row-select value="a1"><span></span></label><button class="activity-row-main">Corrida A</button></article>
<article class="activity-list-row" data-history-row data-activity-id="a2"><label class="activity-row-select"><input type="checkbox" data-bulk-row-select value="a2"><span></span></label><button class="activity-row-main">Corrida B</button></article>
<article class="activity-list-row" data-history-row data-activity-id="a3"><label class="activity-row-select"><input type="checkbox" data-bulk-row-select value="a3"><span></span></label><button class="activity-row-main">Corrida C</button></article>
</div><div data-history-skeleton hidden></div><div data-history-empty hidden><span data-history-empty-text></span></div><div data-history-error hidden></div><div data-history-more hidden><span data-history-count></span></div></section>
</div></main>
<nav class="mobile-bottom-nav"><a class="mobile-nav-item" href="#home"><span>Início</span></a><a class="mobile-nav-item" href="#training"><span>Treinos</span></a><a class="mobile-nav-item" href="#activities"><span>Atividades</span></a><a class="mobile-nav-item" href="#progress"><span>Progresso</span></a><a class="mobile-nav-item mobile-profile-tab is-active" href="#profile"><img class="mobile-nav-avatar" alt="" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='22' height='22'%3E%3Crect width='22' height='22' rx='11' fill='%2340507c'/%3E%3C/svg%3E"><span>Perfil</span></a></nav>
</body></html>'''

checks = 0
def ok(value, message):
    global checks
    checks += 1
    if not value:
        raise AssertionError(message)

with sync_playwright() as p:
    launch = {'headless': True, 'args': ['--no-sandbox']}
    try:
        browser = p.chromium.launch(**launch)
    except Exception:
        import shutil
        fallback = shutil.which('chromium') or shutil.which('chromium-browser') or shutil.which('google-chrome')
        if not fallback:
            raise
        launch['executable_path'] = fallback
        browser = p.chromium.launch(**launch)
    page = browser.new_page(viewport={'width': 390, 'height': 844})
    errors = []
    page.on('pageerror', lambda e: errors.append(str(e)))
    page.set_content(HTML)
    page.add_style_tag(content=CSS)
    page.evaluate("""() => {
      window.StrideBRI18n = {locale:'pt-BR', number:v=>String(v), sport:(_s,f)=>f, t:(k,v={}) => {
        const map = {
          'activity.bulk_selected.one':'1 selecionada', 'activity.bulk_selected.other':`${v.count ?? 0} selecionadas`,
          'activity.bulk_select_loaded_count':`Selecionar ${v.count ?? 0} carregadas`, 'activity.bulk_clear_loaded':'Limpar carregadas',
          'activity.history.loaded.one':`${v.count ?? 0} atividade carregada`, 'activity.history.loaded.other':`${v.count ?? 0} atividades carregadas`
        }; return map[k] || k;
      }};
      window.fetch = async () => ({ok:true, json:async()=>({ok:true,items:[],next_cursor:null,resumo:{},total:0})});
    }""")
    page.add_script_tag(content=SCRIPTS)
    page.add_script_tag(content=INTEGRATIONS)
    page.add_script_tag(content=ACTIVITIES)
    page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
    page.wait_for_timeout(100)

    # Mobile menu actual click/keyboard/backdrop contract.
    mobile = page.locator('.mobile-global-menu')
    summary = mobile.locator(':scope > summary')
    ok(summary.bounding_box()['height'] >= 40, 'mobile menu touch target')
    summary.click(); ok(mobile.evaluate('e=>e.open'), 'tap opens mobile menu')
    ok(mobile.locator('.mobile-global-menu-backdrop').is_visible(), 'backdrop visible')
    mobile.locator('.mobile-global-menu-backdrop').click(); ok(not mobile.evaluate('e=>e.open'), 'backdrop closes menu')
    summary.focus(); summary.press('Enter'); ok(mobile.evaluate('e=>e.open'), 'keyboard opens menu')
    summary.press('Escape'); ok(not mobile.evaluate('e=>e.open'), 'Escape closes menu')
    notif = page.locator('.header-notification-menu')
    notif.locator('summary').click(); ok(notif.evaluate('e=>e.open'), 'notification opens')
    summary.click(); ok(mobile.evaluate('e=>e.open') and not notif.evaluate('e=>e.open'), 'global menu coexists and closes notification')
    ok(page.locator('.desktop-account-menu').is_hidden(), 'desktop account hidden on mobile')
    ok(page.locator('.mobile-profile-tab').is_visible(), 'Profile bottom tab visible')
    ok(page.locator('.mobile-bottom-nav').inner_text().find('Mais') == -1, 'More removed from bottom nav')
    ok(page.evaluate('document.documentElement.scrollWidth <= innerWidth'), 'no mobile body overflow')
    page.screenshot(path=str(OUT/'01-mobile-menu-dark.png'), full_page=True)
    summary.press('Escape')
    ok(not mobile.evaluate('e=>e.open'), 'mobile menu closes before returning to page content')

    # Activities contextual header keeps footprint and dialog editing.
    normal = page.locator('[data-history-toolbar-normal]')
    bulk = page.locator('[data-bulk-toolbar]')
    toolbar = page.locator('.activity-history-toolbar')
    h0 = toolbar.bounding_box()['height']
    list_y0 = page.locator('[data-activity-list]').bounding_box()['y']
    page.locator('[data-bulk-toggle]').click(); page.wait_for_timeout(30)
    ok(normal.is_hidden() and bulk.is_visible(), 'selection replaces header contents')
    h1 = toolbar.bounding_box()['height']
    list_y1 = page.locator('[data-activity-list]').bounding_box()['y']
    ok(h1 <= h0 + 8 and list_y1 <= list_y0 + 8, f'contextual header does not push list down: toolbar {h0}/{h1}, list {list_y0}/{list_y1}')
    page.locator('[data-history-row][data-activity-id="a1"] .activity-row-main').click()
    ok('1 selecionada' in page.locator('[data-bulk-count]').inner_text(), 'row click toggles selection')
    ok(not page.locator('[data-bulk-edit]').is_disabled(), 'Edit enabled after selection')
    page.locator('[data-bulk-edit]').click(); ok(page.locator('[data-bulk-dialog]').evaluate('e=>e.open'), 'Edit opens dialog/sheet')
    ok(page.locator('[data-bulk-dialog] button[type="submit"]').is_disabled(), 'Apply disabled with no changes')
    page.locator('[data-bulk-dialog] select[name="visibilidade"]').select_option('privado')
    ok(not page.locator('[data-bulk-dialog] button[type="submit"]').is_disabled(), 'Apply enabled after actual change')
    page.locator('[data-bulk-dialog-close]').last.click(); ok(not page.locator('[data-bulk-dialog]').evaluate('e=>e.open'), 'dialog closes')
    page.locator('[data-bulk-select-visible]').click(); ok('3 selecionadas' in page.locator('[data-bulk-count]').inner_text(), 'select loaded works')
    page.screenshot(path=str(OUT/'02-activities-selection-mobile.png'), full_page=True)
    page.locator('[data-bulk-cancel]').click(); ok(bulk.is_hidden() and normal.is_visible(), 'Cancel returns normal header')

    # Desktop account menu and integration/settings states.
    page.set_viewport_size({'width': 1280, 'height': 900}); page.wait_for_timeout(50)
    desktop = page.locator('.desktop-account-menu')
    ok(desktop.is_visible(), 'desktop avatar menu visible')
    ok(mobile.is_hidden(), 'hamburger hidden on desktop')
    desktop.locator('summary').click(); ok(desktop.evaluate('e=>e.open'), 'desktop avatar opens account dropdown')
    ok('Conexões e integrações' in desktop.inner_text(), 'connections discoverable in account menu')
    ok(page.locator('.settings-tabs [aria-current="page"]').inner_text() == 'Conexões', 'Connections first-class settings tab')
    strava = page.locator('[data-integration-provider="strava"]')
    ok(strava.get_attribute('data-strava-state') == 'history', 'Strava history visual state')
    ok('Importando histórico' in strava.inner_text(), 'Strava history copy visible')
    ok('%' not in strava.inner_text(), 'no fake percentage')
    ok('Strava' in page.locator('.integration-provider-mark.is-wordmark').first.inner_text(), 'no isolated S brand substitute')
    page.screenshot(path=str(OUT/'03-desktop-account-connections-dark.png'), full_page=True)

    # Light mode representative.
    page.evaluate("document.documentElement.dataset.theme='light'")
    ok(page.evaluate('document.documentElement.scrollWidth <= innerWidth'), 'desktop light no body overflow')
    page.screenshot(path=str(OUT/'04-desktop-connections-light.png'), full_page=True)
    ok(not errors, f'page errors: {errors}')
    browser.close()

print(f'PASS core nav/Strava browser: {checks} checks; screenshots {OUT}')
