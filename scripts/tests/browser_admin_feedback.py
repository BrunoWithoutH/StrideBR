#!/usr/bin/env python3
from pathlib import Path
import json, sys
try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr); sys.exit(2)
ROOT=Path(__file__).resolve().parents[2]
OUT=Path('/mnt/data/stridebr_admin_feedback_visuals'); OUT.mkdir(parents=True, exist_ok=True)
A=0
def ok(cond,msg):
    global A; A+=1
    if not cond: raise AssertionError(msg)

ROWS=''.join(f'''<article class="admin-feedback-row is-unread" data-feedback-row data-feedback-id="fb00000{i}" data-feedback-state="unread"><label class="admin-feedback-check"><input type="checkbox" data-feedback-select aria-label="Selecionar feedback {i}"></label><div class="admin-feedback-main"><div class="admin-feedback-line1"><span class="status-pill feedback-state-unread" data-feedback-state-label>Não lido</span><strong>Feedback {i}</strong></div><p>Mensagem de teste {i} para validar densidade e operações em lote.</p><div class="admin-feedback-meta"><span>Bug</span><span>@usuario{i}</span><time>Hoje · 18:4{i}</time><span>/user/atividades.php</span></div></div><div class="admin-feedback-row-side"><a class="secondary-action compact" href="?state=unread&view=fb00000{i}">Abrir</a></div></article>''' for i in range(1,4))

def html(detail=False, theme='dark'):
    detail_html='''<div class="admin-feedback-detail-backdrop" data-feedback-detail><article class="admin-feedback-detail" data-feedback-open-id="fb000001" data-feedback-open-state="unread"><header class="admin-feedback-detail-head"><a class="context-back-button" href="#">← Voltar aos feedbacks</a><nav class="admin-feedback-detail-nav"><a href="#">← anterior</a><a href="#">próximo →</a></nav></header><div class="admin-feedback-detail-content"><div class="admin-feedback-detail-primary"><div class="admin-feedback-detail-status"><span class="status-pill feedback-state-unread" data-feedback-detail-state>Não lido</span><span class="status-pill">Bug</span></div><h2>Falha ao registrar atividade</h2><div class="admin-feedback-detail-author"><span>@bruno</span><span>Hoje · 18:42</span></div><p class="admin-feedback-full-message">Ao tentar salvar a atividade a página ficou carregando. Esta é a mensagem principal e deve continuar em destaque.</p></div><aside class="admin-feedback-detail-meta"><div><span>Origem</span><strong>/user/atividades.php</strong></div><div><span>Recebido em</span><strong>08/09/2026 · 18:42</strong></div></aside></div><div class="admin-feedback-detail-actions"><button data-feedback-single-action="mark_read">Marcar como lido</button><button data-feedback-single-action="mark_resolved">Marcar como resolvido</button></div><form class="admin-feedback-detail-form"><label>Prioridade<select name="prioridade"><option>normal</option></select></label><label class="admin-feedback-notes">Notas internas<textarea name="notas_admin"></textarea></label><div class="admin-feedback-workflow"><label>Estado interno<select name="status"><option value="novo">Novo</option><option value="lendo">Lendo</option><option value="planejado">Planejado</option><option value="resolvido">Resolvido</option><option value="arquivado">Arquivado</option></select></label></div></form></article></div>''' if detail else ''
    return f'''<!doctype html><html data-theme="{theme}"><head><base href="http://stride.test/"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"></head><body class="admin-body"><main class="main-content"><div class="admin-shell" data-feedback-admin data-current-state="unread" data-csrf="token"><nav class="admin-subnav"><a href="#">Visão geral</a><a class="is-active" href="#">Feedback</a><a href="#">Usuários</a><a href="#">Eventos</a><a href="#">Diagnóstico</a></nav><div class="admin-heading"><div><span class="eyebrow">Administração</span><h1>Feedback</h1><p>Revisão operacional de bugs, ideias e observações recebidas.</p></div><a class="secondary-action">Exportar feedbacks</a></div><div class="admin-feedback-filters"><nav class="admin-feedback-state-tabs"><a class="is-active" data-state-tab="unread"><span>Não lidos</span><b data-state-count="unread">3</b></a><a data-state-tab="all"><span>Todos</span><b data-state-count="all">27</b></a><a data-state-tab="read"><span>Lidos</span><b data-state-count="read">20</b></a><a data-state-tab="resolved"><span>Resolvidos</span><b data-state-count="resolved">4</b></a></nav><form class="admin-feedback-search-form"><label>Buscar<input value="" placeholder="Texto, usuário, categoria ou origem"></label><label>Tipo<select><option>Todos os tipos</option></select></label><label>Autor<select><option>Todos</option></select></label><button>Filtrar</button><button>Limpar</button></form></div><section class="admin-feedback-list-panel"><div class="admin-feedback-list-head"><label class="admin-feedback-select-all"><input type="checkbox" data-feedback-select-all>Selecionar os visíveis</label><span>3 feedbacks</span></div><div class="admin-feedback-bulk" data-feedback-bulk hidden><strong><span data-feedback-selection-count>0</span> selecionados</strong><div><button data-feedback-bulk-action="mark_read">Marcar como lido</button><button data-feedback-bulk-action="mark_unread">Marcar como não lido</button><button data-feedback-bulk-action="mark_resolved">Marcar como resolvido</button></div></div><div class="admin-feedback-table">{ROWS}</div></section>{detail_html}</div></main></body></html>'''

def load(page, detail=False, theme='dark'):
    page.set_content(html(detail,theme))
    for css in ('style.css','ui-refresh.css','admin.css'): page.add_style_tag(path=str(ROOT/'public/assets/css'/css))
    page.evaluate("window.__toasts=[]; window.StrideBRUI={notify:(m,t='success')=>window.__toasts.push({m,t})}")
    page.add_script_tag(path=str(ROOT/'public/assets/js/feedback-admin.js'))

def no_overflow(page,label):
    d=page.evaluate('() => ({sw:document.documentElement.scrollWidth,cw:document.documentElement.clientWidth,bw:document.body.scrollWidth})')
    ok(d['sw']<=d['cw']+1 and d['bw']<=d['cw']+1,f'{label} overflow {d}')

with sync_playwright() as p:
    b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    page=b.new_page(viewport={'width':1440,'height':900}); load(page)
    errors=[]; page.on('pageerror',lambda e: errors.append(str(e)))
    bulk=page.locator('[data-feedback-bulk]'); selall=page.locator('[data-feedback-select-all]')
    ok(bulk.is_hidden(),'bulk começa oculto')
    checks=page.locator('[data-feedback-select]')
    checks.nth(0).check(); ok(selall.is_checked() is False,'select all não marca com seleção parcial'); ok(selall.evaluate('e=>e.indeterminate'),'select all fica indeterminate')
    ok(bulk.is_visible(),'bulk aparece com seleção'); ok(page.locator('[data-feedback-selection-count]').inner_text()=='1','contador da seleção atualiza')
    selall.check(); ok(checks.evaluate_all('els=>els.every(e=>e.checked)'),'select all marca visíveis'); ok(not selall.evaluate('e=>e.indeterminate'),'select all completo não é indeterminate')
    page.screenshot(path=str(OUT/'feedback_selected_desktop.png'),full_page=True)
    def success(route): route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'updated':3,'message':'3 feedbacks marcados como lidos.','unread_count':0}))
    page.route('**/admin/feedback.php',success)
    page.locator('[data-feedback-bulk-action="mark_read"]').click()
    page.wait_for_timeout(80)
    ok(page.locator('[data-feedback-row]:visible').count()==0,'itens saem do filtro unread após bulk')
    ok(page.locator('[data-state-count="unread"]').inner_text()=='0','contador unread atualiza')
    ok(page.locator('[data-state-count="read"]').inner_text()=='23','contador read atualiza')
    ok(bulk.is_hidden(),'bulk some após sucesso'); ok(len(page.evaluate('window.__toasts'))==1,'bulk mostra um toast agregado')
    page.unroute('**/admin/feedback.php')

    # rollback
    load(page)
    page.route('**/admin/feedback.php',lambda r:r.fulfill(status=422,content_type='application/json',body=json.dumps({'ok':False,'error':'Falha simulada'})))
    checks=page.locator('[data-feedback-select]'); checks.nth(0).check(); page.locator('[data-feedback-bulk-action="mark_resolved"]').click(); page.wait_for_timeout(80)
    row=page.locator('[data-feedback-row]').first
    ok(row.is_visible(),'falha restaura visibilidade'); ok(row.get_attribute('data-feedback-state')=='unread','falha restaura estado'); ok(row.locator('[data-feedback-state-label]').inner_text()=='Não lido','falha restaura label'); ok(page.locator('[data-state-count="unread"]').inner_text()=='3','falha restaura contador')
    page.unroute('**/admin/feedback.php')

    # deliberate detail auto-read and workflow sync
    load(page,detail=True)
    requests=[]
    def detail_route(route, request):
        requests.append(request.post_data or '')
        route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'updated':1,'message':'Feedback atualizado.','unread_count':2}))
    page.route('**/admin/feedback.php',detail_route)
    # reinsert JS after route because initial load auto-read already fired before route
    page.evaluate("document.querySelector('[data-feedback-detail]')?.remove()")
    page.unroute('**/admin/feedback.php')
    load(page,detail=True)
    page.route('**/admin/feedback.php',detail_route)
    page.add_script_tag(path=str(ROOT/'public/assets/js/feedback-admin.js'))
    page.wait_for_timeout(100)
    card=page.locator('[data-feedback-open-id]')
    ok(card.get_attribute('data-feedback-open-state')=='read','abrir detalhe marca como read')
    ok(card.locator('select[name="status"]').input_value()=='lendo','auto-read sincroniza workflow')
    ok(any('mark_read_open' in x for x in requests),'auto-read usa ação dedicada')
    page.screenshot(path=str(OUT/'feedback_detail_desktop.png'),full_page=False)

    page.set_viewport_size({'width':390,'height':844}); no_overflow(page,'feedback detail mobile')
    detail=page.locator('[data-feedback-detail]'); ok(detail.is_visible(),'detalhe permanece acessível no mobile')
    box=page.locator('.admin-feedback-detail').bounding_box(); ok(box and box['width']<=390,'detail cabe na largura mobile')
    page.screenshot(path=str(OUT/'feedback_detail_mobile.png'),full_page=False)

    # list mobile and select toolbar
    page.unroute('**/admin/feedback.php'); load(page); page.set_viewport_size({'width':390,'height':844}); no_overflow(page,'feedback mobile')
    page.locator('[data-feedback-select]').first.check(); ok(page.locator('[data-feedback-bulk]').is_visible(),'bulk é utilizável no mobile')
    toolbar_box=page.locator('[data-feedback-bulk]').bounding_box(); ok(toolbar_box and toolbar_box['width']<=390,'bulk não extrapola mobile')
    page.screenshot(path=str(OUT/'feedback_mobile.png'),full_page=False)

    # lightweight admin notification: admin summary is visually secondary and independent from personal badge
    notif=b.new_page(viewport={'width':420,'height':700})
    notif.set_content('''<!doctype html><html data-theme="dark"><body><div class="header-notification-popover"><div class="header-notification-head"><strong>Notificações</strong><small>2 não lidas</small></div><div class="header-notification-list"><a class="header-notification-item is-unread"><span class="header-notification-dot"></span><span><strong>Solicitação de amizade</strong><small>Nova solicitação pessoal</small></span></a></div><div class="header-notification-admin"><span class="header-notification-admin-label">Administração</span><a href="/admin/feedback.php?state=unread"><span>Feedback novo</span><strong>4</strong></a></div><a class="header-notification-all">Ver todas</a></div></body></html>''')
    for css in ('style.css','ui-refresh.css','admin.css'): notif.add_style_tag(path=str(ROOT/'public/assets/css'/css))
    ok(notif.locator('.header-notification-admin').is_visible(),'resumo admin aparece no popover admin')
    ok(notif.locator('.header-notification-admin strong').inner_text()=='4','resumo admin mostra contador agregado')
    notif.locator('.header-notification-popover').screenshot(path=str(OUT/'notification_popover_admin.png'))
    common=b.new_page(); common.set_content('<!doctype html><html><body><div class="header-notification-popover"><div class="header-notification-list"></div></div></body></html>'); common.add_style_tag(path=str(ROOT/'public/assets/css/ui-refresh.css'))
    ok(common.locator('.header-notification-admin').count()==0,'usuário comum não recebe resumo administrativo no fixture')

    # users mobile structure and events admin shell representation
    u=b.new_page(viewport={'width':390,'height':844}); u.set_content('''<!doctype html><html data-theme="dark"><body class="admin-body"><div class="admin-shell"><div class="admin-table-wrap admin-users-table"><table><tbody><tr><td data-label="Usuário"><strong>Bruno</strong></td><td data-label="Papel">admin</td><td data-label="Status">Ativo</td><td data-label="Cadastro">08/09/2026</td><td data-label="Ações"><a>Gerenciar</a></td></tr></tbody></table></div></div></body></html>''');
    for css in ('style.css','ui-refresh.css','admin.css'): u.add_style_tag(path=str(ROOT/'public/assets/css'/css))
    no_overflow(u,'users mobile'); ok(u.locator('td[data-label="Usuário"]').is_visible(),'users mobile preserva identidade')
    ok(not errors,'erros JS: '+' | '.join(errors))
    b.close()
print(f'✓ admin feedback browser: {A} assertions')
