from pathlib import Path
import shutil
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[2]
CSS = "\n".join((ROOT / path).read_text(encoding="utf-8") for path in [
    "public/assets/css/style.css",
    "public/assets/css/ui-refresh.css",
    "public/assets/css/atividades.css",
])
HTML = """
<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>
<style>__CSS__</style></head><body>
<main style='min-height:2400px;padding:24px'>
<section class='settings-connections-groups'>
  <section class='integration-group' data-integration-group='ready'><div class='integration-group-heading'><div><h3>Conectadas e disponíveis</h3><p>Serviços prontos.</p></div><span>1</span></div><div class='settings-connections-grid'>
    <article class='integration-card is-connected' data-integration-provider='strava'><div class='integration-card-main'><span class='integration-provider-mark'>S</span><div class='integration-card-copy'><div class='integration-card-title'><h3>Strava</h3></div><div class='integration-strava-state'><strong>Histórico sincronizado</strong><p>Informação útil de sincronização.</p><small>124 atividades</small></div></div></div><div class='integration-card-actions'><button class='integration-button'>Sincronizar</button></div></article>
  </div></section>
  <section class='integration-group' data-integration-group='app'><div class='integration-group-heading'><div><h3>Pelo app</h3><p>Pontes do sistema.</p></div><span>3</span></div><div class='settings-connections-grid'>
    <article class='integration-card' data-integration-provider='health_connect'><div class='integration-card-main'><span class='integration-provider-mark'>HC</span><div class='integration-card-copy'><div class='integration-card-title'><h3>Health Connect</h3><span class='integration-status'>Disponível pelo app Android</span></div><p>Android bridge.</p></div></div><div class='integration-card-actions'></div></article>
    <article class='integration-card' data-integration-provider='samsung_health'><div class='integration-card-main'><span class='integration-provider-mark'>SH</span><div class='integration-card-copy'><div class='integration-card-title'><h3>Samsung Health</h3><span class='integration-status'>Disponível pelo app Android</span></div><p>Android bridge.</p></div></div><div class='integration-card-actions'></div></article>
    <article class='integration-card' data-integration-provider='apple_health'><div class='integration-card-main'><span class='integration-provider-mark'>AH</span><div class='integration-card-copy'><div class='integration-card-title'><h3>Apple Health</h3><span class='integration-status'>Disponível pelo app iOS</span></div><p>iOS bridge.</p></div></div><div class='integration-card-actions'></div></article>
  </div></section>
  <section class='integration-group' data-integration-group='external'><div class='integration-group-heading'><div><h3>Acesso externo</h3><p>Aguardando APIs.</p></div><span>2</span></div><div class='settings-connections-grid'>
    <article class='integration-card' data-integration-provider='garmin'><div class='integration-card-main'><span class='integration-provider-mark'>G</span><div class='integration-card-copy'><div class='integration-card-title'><h3>Garmin Connect</h3><span class='integration-status'>Aguardando acesso externo</span></div><p>API externa.</p></div></div><div class='integration-card-actions'></div></article>
    <article class='integration-card' data-integration-provider='halo'><div class='integration-card-main'><span class='integration-provider-mark'>H</span><div class='integration-card-copy'><div class='integration-card-title'><h3>HALO</h3><span class='integration-status'>Aguardando acesso externo</span></div><p>Parceria externa.</p></div></div><div class='integration-card-actions'></div></article>
  </div></section>
</section>
<nav class='ux-context-nav'><a class='is-active'>Como atleta</a><a>Como treinador</a></nav>
<section class='trainer-permission-summary'><span>Pode prescrever</span><span>Ver atividades</span></section>
<section class='people-grid'><article class='person-card'><a class='person-identity-link'><span><strong>João da Silva</strong><small>@joao</small></span></a><details class='person-action-menu'><summary aria-label='Ações'><svg class='ui-icon' viewBox='0 0 24 24'><circle cx='5' cy='12' r='1'/><circle cx='12' cy='12' r='1'/><circle cx='19' cy='12' r='1'/></svg></summary><div><a>Perfil</a></div></details></article></section>
<div style='height:1100px'></div>
<div class='activity-detail-drawer' data-activity-detail-drawer><button class='activity-detail-backdrop'></button><aside class='activity-detail-panel' data-activity-detail-panel><header><h2>Atividade</h2></header><div style='height:1400px;padding:16px'>Conteúdo longo da atividade</div></aside></div>
</main></body></html>
""".replace("__CSS__", CSS)

checks = 0
chromium = shutil.which("chromium") or shutil.which("chromium-browser") or shutil.which("google-chrome")
with sync_playwright() as p:
    launch = {"args": ["--no-sandbox"]}
    if chromium:
        launch["executable_path"] = chromium
    browser = p.chromium.launch(**launch)
    page = browser.new_page(viewport={"width": 1440, "height": 900})
    page.set_content(HTML, wait_until="domcontentloaded")

    marks = page.locator(".integration-provider-mark")
    for i in range(marks.count()):
        mark = marks.nth(i)
        box = mark.bounding_box()
        assert box and box["width"] <= 38 and box["height"] <= 38
        assert len(mark.inner_text().strip()) <= 2
        checks += 2
    assert page.locator('[data-integration-group="ready"]').is_visible(); checks += 1
    assert page.locator('[data-integration-group="app"]').is_visible(); checks += 1
    assert page.locator('[data-integration-group="external"]').is_visible(); checks += 1
    assert page.locator('[data-integration-provider="garmin"]').bounding_box()["height"] < page.locator('[data-integration-provider="strava"]').bounding_box()["height"]; checks += 1
    assert page.evaluate("document.documentElement.scrollWidth <= innerWidth"); checks += 1

    panel = page.locator(".activity-detail-panel")
    assert panel.evaluate("e => getComputedStyle(e).overscrollBehaviorY") == "auto"; checks += 1
    assert page.evaluate("getComputedStyle(document.documentElement).overflowY") != "hidden"; checks += 1
    page.evaluate("window.scrollTo(0, 700)")
    before = page.evaluate("scrollY")
    panel.evaluate("e => { e.scrollTop = e.scrollHeight; }")
    panel.hover()
    page.mouse.wheel(0, 700)
    page.wait_for_timeout(80)
    assert page.evaluate("scrollY") > before; checks += 1

    page.set_viewport_size({"width": 390, "height": 844})
    page.evaluate("document.documentElement.dataset.theme='dark'; document.documentElement.classList.add('activity-detail-open')")
    assert page.evaluate("getComputedStyle(document.documentElement).overflowY") == "hidden"; checks += 1
    drawer = page.locator(".activity-detail-drawer").bounding_box()
    assert drawer and drawer["width"] <= 390 and drawer["height"] <= 844; checks += 1
    assert page.locator(".activity-detail-panel").evaluate("e => getComputedStyle(e).position") == "absolute"; checks += 1
    page.evaluate("document.documentElement.classList.remove('activity-detail-open'); document.querySelector('.activity-detail-drawer').hidden=true")
    nav = page.locator(".ux-context-nav").bounding_box()
    person = page.locator(".person-card").bounding_box()
    assert nav and nav["width"] <= 390; checks += 1
    assert person and person["width"] <= 390; checks += 1
    assert page.evaluate("document.documentElement.scrollWidth <= innerWidth"); checks += 1
    browser.close()

print(f"PASS product UX consolidation browser: {checks} checks")
