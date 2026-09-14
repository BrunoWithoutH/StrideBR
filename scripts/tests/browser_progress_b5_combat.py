#!/usr/bin/env python3
from pathlib import Path
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f"Playwright indisponível: {exc}", file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
HTML = '''<!doctype html><html data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><main class="progress-page"><div class="progress-shell">
<section class="progress-section combat-rank-section" data-combat-rank-section><header><div><h2>Graduação</h2><p>Acompanhe seu histórico.</p></div><details class="progress-inline-form" data-combat-rank-create><summary class="progress-button">Registrar graduação</summary><form class="combat-form-grid" data-form="rank"><input type="hidden" name="action" value="rank_create"><label><span>Graduação</span><input name="graduacao" required maxlength="120"></label><label><span>Detalhe</span><input name="detalhe"></label><label><span>Sistema</span><input name="sistema"></label><label><span>Data</span><input type="date" name="data_graduacao" required></label><label><span>Emissor</span><input name="emissor"></label><label class="combat-form-wide"><span>Observações</span><textarea name="observacoes"></textarea></label><div class="combat-form-actions combat-form-wide"><button type="submit" class="progress-button is-primary">Salvar</button></div></form></details></header><article class="combat-current-rank"><div><span>Graduação atual</span><strong>Faixa azul · 2 graus</strong><small>Academia X · desde 14/03/2025</small></div></article><details class="progress-benchmark-history combat-rank-history"><summary>Histórico · 2</summary><div><article class="combat-history-row"><div><strong>Faixa azul · 2 graus</strong><small>14/03/2025 · Academia X</small></div></article><article class="combat-history-row"><div><strong>Faixa branca</strong><small>10/02/2023 · Academia X</small></div></article></div></details></section>
<section class="progress-section combat-technique-section" data-combat-technique-section><header><div><h2>Repertório técnico</h2><p>Técnicas que você acompanha.</p></div><details class="progress-inline-form" data-combat-technique-create><summary class="progress-button">Adicionar técnica</summary><form class="combat-form-grid" data-form="technique"><label><span>Nome</span><input name="nome" required></label><label><span>Categoria</span><select name="categoria_code"><option value="submission">Finalização</option><option value="takedown">Queda</option></select></label><label><span>Categoria personalizada</span><input name="categoria_custom"></label><label><span>Autoavaliação</span><select name="estado"><option value="learning">Aprendendo</option><option value="practicing">Praticando</option><option value="consolidated">Consolidada</option></select></label><label class="combat-form-wide"><span>Observações</span><textarea name="observacoes"></textarea></label><div class="combat-form-actions combat-form-wide"><button type="submit" class="progress-button is-primary">Salvar</button></div></form></details></header><div class="combat-technique-summary"><span><strong>2</strong>Acompanhadas</span><span><strong>1</strong>Aprendendo</span><span><strong>1</strong>Praticando</span><span><strong>0</strong>Consolidadas</span></div><div class="combat-technique-list"><a href="#detail" class="combat-technique-row"><span><strong>Armbar</strong><small>Finalização · Praticando</small></span><span><strong>7</strong><small>práticas · 08/09/2026</small></span><span class="progress-row-chevron">›</span></a><a href="#detail" class="combat-technique-row"><span><strong>Single leg</strong><small>Queda · Aprendendo</small></span><span><strong>3</strong><small>práticas · 02/09/2026</small></span><span class="progress-row-chevron">›</span></a></div></section>
<section class="progress-section combat-technique-detail" data-combat-technique-detail id="detail"><header><div><h2>Armbar</h2><p>Detalhes da técnica.</p></div></header><div class="combat-technique-detail-meta"><span><small>Categoria</small><strong>Finalização</strong></span><span><small>Autoavaliação</small><strong>Praticando</strong></span><span><small>Primeira prática</small><strong>12/08/2026</strong></span><span><small>Última prática</small><strong>08/09/2026</strong></span><span><small>Práticas</small><strong>7</strong></span></div><div class="combat-technique-actions"><details class="progress-inline-form"><summary class="progress-button">Editar</summary><form class="combat-form-grid" data-form="edit"><label><span>Nome</span><input name="nome" value="Armbar"></label><label><span>Autoavaliação</span><select name="estado"><option value="learning">Aprendendo</option><option value="practicing" selected>Praticando</option><option value="consolidated">Consolidada</option></select></label><button type="submit" class="progress-button is-primary">Salvar</button></form></details><details class="progress-inline-form combat-practice-form" data-combat-practice-create><summary class="progress-button is-primary">Registrar prática</summary><form class="combat-form-grid" data-form="practice"><label><span>Data</span><input type="date" name="data_pratica" value="2026-09-08"></label><label><span>Atividade</span><select name="idregistro"><option value="">Sem atividade</option><option value="act1">Treino BJJ · 08/09/2026</option></select></label><label class="combat-form-wide"><span>Observações</span><textarea name="observacoes"></textarea></label><button type="submit" class="progress-button is-primary">Salvar</button></form></details></div><div class="combat-practice-history"><h3>Histórico de práticas</h3><div class="progress-detail-list"><article><div><strong>08/09/2026</strong><small><a href="/user/atividades.php?activity=act1">Treino BJJ</a></small></div></article></div></div></section>
<section class="progress-section" data-empty-combat><p class="progress-benchmark-empty">Você ainda não registrou uma graduação nesta modalidade.</p><div class="progress-benchmark-empty"><strong>Nenhuma técnica no repertório.</strong><p>Adicione técnicas que você está aprendendo ou praticando.</p></div></section>
</div></main><script>window.__submitted=[];document.querySelectorAll('form').forEach(form=>form.addEventListener('submit',event=>{event.preventDefault();window.__submitted.push(Object.fromEntries(new FormData(form).entries()));}));</script></body></html>'''

count = 0
failures = []

def check(condition, message):
    global count
    count += 1
    if not condition:
        raise AssertionError(message)

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for theme in ('light', 'dark'):
        for viewport in ({'width': 1280, 'height': 900}, {'width': 390, 'height': 844}):
            label = f"{theme}-{viewport['width']}"
            page = browser.new_page(viewport=viewport)
            errors = []
            page.on('pageerror', lambda exc: errors.append(str(exc)))
            page.set_content(HTML, wait_until='domcontentloaded')
            for css in ('style.css', 'ui-refresh.css', 'sport-hub.css'):
                page.add_style_tag(path=str(ROOT / 'public/assets/css' / css))
            page.evaluate("theme => document.documentElement.dataset.theme = theme", theme)
            page.wait_for_timeout(80)
            try:
                check(page.locator('[data-combat-rank-section]').is_visible(), f'{label}: graduação não renderizou')
                check(page.locator('[data-combat-technique-section]').is_visible(), f'{label}: repertório não renderizou')
                check(page.locator('[data-combat-technique-detail]').is_visible(), f'{label}: detalhe não renderizou')
                check(page.locator('[data-empty-combat]').is_visible(), f'{label}: estados vazios não renderizaram')
                page.click('[data-combat-rank-create] > summary')
                check(page.locator('[data-combat-rank-create] form').is_visible(), f'{label}: formulário de graduação não abriu')
                page.fill('[data-form="rank"] input[name="graduacao"]', 'Faixa roxa')
                page.fill('[data-form="rank"] input[name="data_graduacao"]', '2026-09-01')
                page.click('[data-form="rank"] button[type="submit"]')
                check(page.evaluate("window.__submitted.at(-1)?.action") == 'rank_create', f'{label}: graduação não submeteu')
                page.click('[data-combat-technique-create] > summary')
                page.fill('[data-form="technique"] input[name="nome"]', 'Triangle')
                page.select_option('[data-form="technique"] select[name="estado"]', 'practicing')
                page.click('[data-form="technique"] button[type="submit"]')
                check(page.evaluate("window.__submitted.at(-1)?.estado") == 'practicing', f'{label}: autoavaliação da técnica não submeteu')
                page.click('[data-combat-technique-detail] .combat-technique-actions details:first-child > summary')
                page.select_option('[data-form="edit"] select[name="estado"]', 'consolidated')
                page.click('[data-form="edit"] button[type="submit"]')
                check(page.evaluate("window.__submitted.at(-1)?.estado") == 'consolidated', f'{label}: alteração de estado não submeteu')
                page.click('[data-combat-practice-create] > summary')
                page.select_option('[data-form="practice"] select[name="idregistro"]', 'act1')
                page.click('[data-form="practice"] button[type="submit"]')
                check(page.evaluate("window.__submitted.at(-1)?.idregistro") == 'act1', f'{label}: prática não preservou vínculo opcional')
                check(page.locator('.combat-practice-history a[href*="atividades.php"]').count() == 1, f'{label}: prática ligada não aponta para atividade')
                grid = page.locator('[data-form="rank"]').evaluate("el => getComputedStyle(el).gridTemplateColumns")
                summary_grid = page.locator('.combat-technique-summary').evaluate("el => getComputedStyle(el).gridTemplateColumns")
                meta_grid = page.locator('.combat-technique-detail-meta').evaluate("el => getComputedStyle(el).gridTemplateColumns")
                if viewport['width'] <= 760:
                    check(len(grid.split()) == 1, f'{label}: formulário mobile não colapsou para uma coluna: {grid}')
                    check(len(summary_grid.split()) == 2, f'{label}: resumo mobile não usa duas colunas: {summary_grid}')
                    check(len(meta_grid.split()) == 2, f'{label}: detalhe mobile não usa duas colunas: {meta_grid}')
                    check(page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), f'{label}: layout criou overflow horizontal')
                else:
                    check(len(grid.split()) == 2, f'{label}: formulário desktop não usa duas colunas: {grid}')
                    check(len(summary_grid.split()) == 4, f'{label}: resumo desktop não usa quatro colunas: {summary_grid}')
                    check(len(meta_grid.split()) == 5, f'{label}: detalhe desktop não usa cinco colunas: {meta_grid}')
                panel = page.locator('.combat-current-rank').evaluate("el => ({bg:getComputedStyle(el).backgroundColor,color:getComputedStyle(el).color})")
                check(panel['bg'] != 'rgba(0, 0, 0, 0)', f'{label}: graduação sem superfície visível')
                check(not errors, f'{label}: erros JS: {errors}')
            except Exception as exc:
                failures.append(str(exc))
            finally:
                page.close()
    browser.close()

if failures:
    print('Falhas no browser Progress B5 combat:', file=sys.stderr)
    for failure in failures:
        print(f'- {failure}', file=sys.stderr)
    sys.exit(1)

print(f'✓ browser progress B5 combat: {count} assertions; desktop/mobile light/dark')
