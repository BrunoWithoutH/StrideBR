#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[2]
SCREEN_DIR = ROOT / 'docs/reports/screenshots' / 'MARKETING_ATTRIBUTION_2026-09-09'
SCREEN_DIR.mkdir(parents=True, exist_ok=True)
CSS = '\n'.join((ROOT / p).read_text() for p in [
    'public/assets/css/style.css',
    'public/assets/css/ui-refresh.css',
    'public/assets/css/admin.css',
])
QR = (ROOT / 'public/assets/vendor/qrcode-generator.js').read_text()
ADMIN_JS = (ROOT / 'public/assets/js/admin-marketing.js').read_text()

HTML = '''
<div class="container-fluid"><main class="main-content"><div class="admin-shell marketing-admin">
<nav class="admin-nav"><a>Visão geral</a><a class="active">Marketing</a><a>Diagnóstico</a></nav>
<div class="admin-heading"><div><span class="eyebrow">Administração</span><h1>Marketing</h1><p>Atribuição first-party de campanhas, QR Codes e conversão até a primeira atividade.</p></div><a class="primary-action">+ Nova campanha</a></div>
<section class="marketing-metrics" aria-label="Visão geral de aquisição">
<article><span>Entradas</span><strong>128</strong></article><article><span>Atribuídas</span><strong>91</strong></article><article><span>Diretas/desconhecidas</span><strong>37</strong></article><article><span>Cadastro iniciado</span><strong>44</strong></article><article><span>Cadastro concluído</span><strong>29</strong></article><article><span>Ativados</span><strong>18</strong></article><article><span>Visita → cadastro</span><strong>22,7%</strong></article><article><span>Cadastro → ativação</span><strong>62,1%</strong></article>
</section>
<section class="admin-card marketing-campaign-summary"><div class="admin-card-heading"><div><h2>Frederico Westphalen — Lançamento local 2026</h2><p><code>fw_local_2026</code> · Offline · Ativa</p></div><a class="secondary-action compact">Todas as campanhas</a></div><div class="marketing-inline-metrics"><span><strong>64</strong> acessos</span><span><strong>23</strong> signup iniciado</span><span><strong>14</strong> signup concluído</span><span><strong>9</strong> ativados</span></div></section>
<section class="admin-card admin-table-card" id="placements"><div class="admin-card-heading"><div><h2>Placements / origens</h2><p>O QR aponta para a URL curta first-party.</p></div><a class="primary-action compact">+ Novo placement</a></div>
<div class="admin-table-wrap marketing-placement-table"><table><thead><tr><th>Placement</th><th>Link / QR</th><th>Acessos</th><th>Signup iniciado</th><th>Signup concluído</th><th>Ativados</th><th>Conversão</th><th></th></tr></thead><tbody>
<tr><td data-label="Placement"><strong>IF — Ginásio — principal</strong><small><code>fw_if_ginasio</code></small></td><td data-label="Link / QR"><div class="marketing-link-cell" data-marketing-qr="https://stridebr.com.br/r/fw_if_ginasio"><div class="marketing-qr" data-qr-preview aria-hidden="true"></div><div><code>https://stridebr.com.br/r/fw_if_ginasio</code><div class="marketing-row-actions"><button type="button" class="secondary-action compact" data-copy-short-url="https://stridebr.com.br/r/fw_if_ginasio">Copiar link</button><button type="button" class="secondary-action compact" data-download-qr="fw_if_ginasio">Baixar QR SVG</button></div></div></div></td><td data-label="Acessos">31</td><td data-label="Signup iniciado">12</td><td data-label="Signup concluído">8</td><td data-label="Ativados">5</td><td data-label="Conversão">25,8%</td><td data-label=""><a class="secondary-action compact">Editar</a></td></tr>
<tr><td data-label="Placement"><strong>SESC — Entrada</strong><small><code>fw_sesc_entrada</code></small></td><td data-label="Link / QR"><div class="marketing-link-cell" data-marketing-qr="https://stridebr.com.br/r/fw_sesc_entrada"><div class="marketing-qr" data-qr-preview aria-hidden="true"></div><div><code>https://stridebr.com.br/r/fw_sesc_entrada</code><div class="marketing-row-actions"><button type="button" class="secondary-action compact" data-copy-short-url="https://stridebr.com.br/r/fw_sesc_entrada">Copiar link</button><button type="button" class="secondary-action compact" data-download-qr="fw_sesc_entrada">Baixar QR SVG</button></div></div></div></td><td data-label="Acessos">18</td><td data-label="Signup iniciado">6</td><td data-label="Signup concluído">4</td><td data-label="Ativados">3</td><td data-label="Conversão">22,2%</td><td data-label=""><a class="secondary-action compact">Editar</a></td></tr>
</tbody></table></div></section>
</div></main></div>'''


def run_case(page, width, height, theme, name):
    page.set_viewport_size({'width': width, 'height': height})
    page.set_content(f'<!doctype html><html data-theme="{theme}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{CSS}</style></head><body class="admin-body">{HTML}</body></html>')
    page.add_script_tag(content=QR)
    page.add_script_tag(content=ADMIN_JS)
    page.wait_for_timeout(80)
    assert page.locator('.marketing-qr svg').count() == 2, 'QR previews must render locally'
    assert page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1'), f'body overflow at {width}'
    if width <= 720:
        assert page.locator('.marketing-placement-table thead').evaluate("el => getComputedStyle(el).display") == 'none'
        assert page.locator('.marketing-placement-table tr').first.evaluate("el => getComputedStyle(el).display") == 'grid'
    else:
        assert page.locator('.marketing-placement-table thead').evaluate("el => getComputedStyle(el).display") != 'none'
    page.screenshot(path=str(SCREEN_DIR / name), full_page=True)
    return 4


def main():
    checks = 0
    with sync_playwright() as p:
        browser = p.chromium.launch(executable_path='/usr/bin/chromium', headless=True, args=['--no-sandbox'])
        page = browser.new_page()
        checks += run_case(page, 1440, 950, 'dark', '01-admin-marketing-1440-dark.png')
        checks += run_case(page, 390, 844, 'dark', '02-admin-marketing-390-dark.png')
        checks += run_case(page, 390, 844, 'light', '03-admin-marketing-390-light.png')
        page.close(); browser.close()
    print(f'PASS marketing admin browser: {checks} checks, 3 screenshots')

if __name__ == '__main__':
    main()
