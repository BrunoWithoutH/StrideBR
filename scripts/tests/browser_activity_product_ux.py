#!/usr/bin/env python3
from pathlib import Path
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f"Playwright indisponível: {exc}", file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]

HTML = r'''<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"></head><body>
<main class="activities-page" data-activities-page data-csrf-token="token">
  <div class="activity-history-workspace" data-activity-history-workspace>
    <div class="activity-history-left">
      <section class="activity-history-summary" data-history-summary>
        <article><span>Atividades</span><strong data-summary-activities>2</strong><small data-summary-period>7 dias</small></article>
        <article><span>Tempo</span><strong data-summary-time>1h07</strong></article>
        <article><span>Distância</span><strong data-summary-distance>13 km</strong></article>
        <article><span>Elevação</span><strong data-summary-elevation>80 m</strong></article>
      </section>
      <section class="activity-history" data-activity-history data-initial-state="ready" data-initial-cursor="" data-initial-total="2">
        <div class="activity-list" data-activity-list>
          <article class="activity-list-row" data-history-row data-activity-id="1" data-activity-title="Corrida A"><button data-open-activity-detail="1">Corrida A</button></article>
          <article class="activity-list-row" data-history-row data-activity-id="2" data-activity-title="Corrida B"><button data-open-activity-detail="2">Corrida B</button></article>
        </div>
        <div class="activity-empty-state" data-history-empty hidden><strong>Nenhuma atividade</strong><span data-history-empty-text></span></div>
        <div data-history-skeleton hidden></div><div data-history-error hidden></div>
        <div data-history-more hidden><button data-history-load-more></button><span data-history-count></span></div>
        <span data-activity-history-status></span>
      </section>
    </div>
    <aside class="activity-detail-placeholder" data-detail-desktop-placeholder><div><strong>Detalhes</strong><span>Selecione</span></div></aside>
    <div class="activity-detail-drawer" data-activity-detail-drawer hidden>
      <button class="activity-detail-backdrop" data-close-activity-detail></button>
      <section class="activity-detail-panel" data-activity-detail-panel>
        <header><div><div class="activity-detail-kicker"><span data-detail-sport>Atividade</span><span data-detail-visibility></span></div><h2 data-detail-title>Detalhes</h2><p data-detail-date></p></div><div class="activity-detail-header-actions"><a class="activity-secondary-button activity-detail-compare" data-detail-compare href="#">↔ Comparar</a><button data-expand-activity-detail>Abrir detalhes</button><button data-close-activity-detail>×</button></div></header>
        <div class="activity-detail-loading" data-detail-loading hidden><div class="activity-detail-skeleton-head"><span></span><strong></strong></div><div class="activity-detail-skeleton-metrics"><i></i><i></i><i></i></div><div class="activity-detail-skeleton-block"></div></div>
        <div data-detail-content hidden></div>
      </section>
    </div>
  </div>
  <div data-activity-context-menu hidden><a data-context-edit></a><button data-context-delete></button></div>
</main></body></html>'''

MOCK = r'''() => {
  const makeActivity = (id, title) => ({
    id, titulo:title, modalidade:'Corrida', modalidade_slug:'corrida', data:'07/09/2026', hora:id==='1'?'07:30':'08:00', visibilidade:'privado',
    metricas:[{rotulo:'Distância',valor:id==='1'?'5,00 km':'8,00 km'},{rotulo:'Duração',valor:id==='1'?'25:00':'42:00'}], metricas_compartilhamento:[], dados:[], equipamentos:[], unidades:[], usa_trechos:false, observacoes:'', esforco:null,
    rota:{geojson:{type:'LineString',coordinates:[]},distancia_m:0,ganho_m:null,perfil:[]}
  });
  const activities = {'1':makeActivity('1','Corrida A'),'2':makeActivity('2','Corrida B')};
  const listItem = (id) => ({id, titulo:activities[id].titulo, modalidade:'Corrida', modalidade_slug:'corrida', dia:id==='1'?'07':'06', mes:'SET', hora:activities[id].hora, icone_html:'', metricas:[{rotulo:'Distância',valor:id==='1'?'5 km':'8 km'}]});
  window.__state = {deleted:new Set(), fetches:{detail:0,history:0,delete:0,restore:0}, failNextDelete:false, detailDelay:{'1':25,'2':25}, undoAction:null, notices:[]};
  const t = {
    'common.activity':'Atividade','common.loading':'Carregando','common.public':'Público','common.friends':'Amigos','common.equipment':'Equipamentos','common.try_again':'Tentar novamente',
    'activity.loading':'Carregando','activity.loading_details':'Carregando detalhes','activity.load_error':'Erro','activity.load_short_error':'Erro ao carregar','activity.only_me':'Somente eu',
    'activity.effort_perceived':'Esforço percebido','activity.energy':'Energia','activity.data_section':'Dados','activity.notes':'Observações','activity.route_title':'Rota','activity.route_map_aria':'Rota',
    'activity.route_distance':'Distância','activity.route_gain':'Elevação','activity.elevation_profile':'Perfil','activity.elevation_profile_aria':'Perfil','activity.route_attribution':'Prévia',
    'activity.detail_share':'Compartilhar','activity.repeat':'Repetir','activity.detail_edit':'Editar','activity.detail_delete':'Apagar','activity.detail.expand':'Abrir detalhes','activity.detail.collapse':'Voltar à prévia','activity.close_details':'Fechar detalhes',
    'activity.delete_confirm_named_primary':'Apagar {title}?','activity.delete_confirm_named_secondary':'Você poderá desfazer.','activity.delete_title':'Apagar atividade','activity.delete_label':'Apagar','activity.delete_error':'Não foi possível apagar','activity.deleted':'Atividade apagada',
    'activity.restore_error':'Não foi possível restaurar','activity.restore_many.one':'1 atividade restaurada','activity.restore_many.other':'{count} atividades restauradas',
    'activity.history.loaded.one':'1 atividade carregada','activity.history.loaded.other':'{count} atividades carregadas','activity.history.refreshing':'Atualizando','activity.history.no_filter_match':'Nenhum resultado','activity.first_help':'Registre uma atividade para começar.','activity.load_history_error':'Erro histórico','activity.history.update_error':'Erro ao atualizar','activity.summary.last_7_days':'Últimos 7 dias',
    'activity.compare':'Comparar'
  };
  window.StrideBRI18n = {locale:'pt-BR', t:(key,values={},fallback=null)=>{let out=t[key]??fallback??key; Object.entries(values).forEach(([k,v])=>out=String(out).replaceAll(`{${k}}`,String(v))); return out;}, number:(v)=>String(v), sport:(_s,f)=>f, date:(d)=>String(d)};
  window.StrideBRUI = {
    confirm: async () => true,
    notify: (message,type='info') => window.__state.notices.push({message,type}),
    undo: (message, action) => { window.__state.notices.push({message,type:'undo'}); window.__state.undoAction = action; return {close(){}}; }
  };
  const response = (payload, ok=true) => ({ok, json:async()=>payload});
  window.fetch = (url, options={}) => {
    const parsed = new URL(String(url), 'http://stridebr.local');
    if (parsed.pathname.endsWith('/api/atividade-detalhe.php')) {
      const id = parsed.searchParams.get('id'); window.__state.fetches.detail++;
      return new Promise((resolve,reject) => {
        const timer=setTimeout(()=>resolve(response({ok:true,atividade:activities[id]})), window.__state.detailDelay[id] || 25);
        options.signal?.addEventListener('abort',()=>{clearTimeout(timer); reject(new DOMException('Aborted','AbortError'));},{once:true});
      });
    }
    if (parsed.pathname.endsWith('/function/apagaratividade.php')) {
      window.__state.fetches.delete++; const body = new URLSearchParams(String(options.body||'')); const id=body.get('id');
      return new Promise(resolve=>setTimeout(()=>{
        if (window.__state.failNextDelete) { window.__state.failNextDelete=false; resolve(response({ok:false,message:'Falha simulada'}, false)); return; }
        window.__state.deleted.add(id); resolve(response({ok:true}));
      },160));
    }
    if (parsed.pathname.endsWith('/api/atividades-restaurar.php')) {
      window.__state.fetches.restore++; const body = new URLSearchParams(String(options.body||'')); for (const id of body.getAll('ids[]')) window.__state.deleted.delete(id);
      return Promise.resolve(response({ok:true,restored:1}));
    }
    if (parsed.pathname.endsWith('/api/atividades-historico.php')) {
      window.__state.fetches.history++;
      const ids=['1','2'].filter(id=>!window.__state.deleted.has(id));
      return Promise.resolve(response({ok:true,items:ids.map(listItem),next_cursor:null,resumo:{atividades:ids.length,tempo:ids.length===2?'1h07':'42 min',distancia:ids.length===2?'13 km':'8 km',elevacao:ids.length===2?'80 m':'0 m',periodo:'Últimos 7 dias'}}));
    }
    return Promise.resolve(response({ok:true}));
  };
}'''

def boot(page):
    page.set_content(HTML, wait_until='domcontentloaded')
    page.add_style_tag(path=str(ROOT / 'public/assets/css/atividades.css'))
    page.evaluate(MOCK)
    page.add_script_tag(path=str(ROOT / 'public/assets/js/atividades.js'))
    page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
    page.wait_for_timeout(20)

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    page = browser.new_page(viewport={'width':1440,'height':900})
    errors=[]; page.on('pageerror', lambda exc: errors.append(str(exc)))
    boot(page)
    assertions=0
    def a(cond,msg):
        nonlocal_dummy = None
        global assertions
        assertions += 1
        if not cond: raise AssertionError(msg)

    # Fast details avoid skeleton flicker and cache renders immediately.
    page.click('[data-open-activity-detail="1"]')
    page.wait_for_timeout(70)
    a(page.locator('[data-detail-title]').inner_text() == 'Corrida A', 'detalhe rápido renderiza atividade')
    a(page.locator('[data-detail-loading]').is_hidden(), 'request rápido não pisca skeleton')
    a(page.evaluate('__state.fetches.detail') == 1, 'primeira abertura faz um request')
    page.click('[data-activity-detail-panel] [data-close-activity-detail]')
    page.click('[data-open-activity-detail="1"]')
    a(page.locator('[data-detail-title]').inner_text() == 'Corrida A', 'cache pinta detalhe imediatamente')
    a(page.locator('[data-detail-loading]').is_hidden(), 'cache não mostra skeleton')
    a(page.evaluate('__state.fetches.detail') == 1, 'cache evita request repetido')

    page.close(); page=browser.new_page(viewport={'width':1440,'height':900}); errors=[]; page.on('pageerror',lambda exc:errors.append(str(exc))); boot(page)
    page.evaluate("() => { __state.detailDelay['1']=260; }")
    page.click('[data-open-activity-detail="1"]'); page.wait_for_timeout(170)
    a(not page.locator('[data-detail-loading]').is_hidden(), 'request lento mostra skeleton estrutural após atraso')
    metric_boxes = page.locator('.activity-detail-skeleton-metrics i').evaluate_all('(els) => els.map(el => el.getBoundingClientRect().width)')
    a(len(metric_boxes) == 3 and min(metric_boxes) > 100, 'skeleton de métricas ocupa colunas reais em vez de spinners')
    page.wait_for_function("document.querySelector('[data-detail-title]')?.textContent === 'Corrida A' && document.querySelector('[data-detail-content]') && !document.querySelector('[data-detail-content]').hidden")

    # Stale request cannot overwrite current selection.
    page.click('[data-activity-detail-panel] [data-close-activity-detail]')
    page.evaluate("() => { __state.detailDelay['1']=260; __state.detailDelay['2']=25; }")
    # Reload fixture to clear detail cache.
    page.close(); page=browser.new_page(viewport={'width':1440,'height':900}); errors=[]; page.on('pageerror',lambda exc:errors.append(str(exc))); boot(page)
    page.evaluate("() => { __state.detailDelay['1']=260; __state.detailDelay['2']=25; }")
    page.click('[data-open-activity-detail="1"]'); page.wait_for_timeout(20); page.click('[data-open-activity-detail="2"]')
    page.wait_for_function("document.querySelector('[data-detail-title]')?.textContent === 'Corrida B'")
    page.wait_for_timeout(300)
    a(page.locator('[data-detail-title]').inner_text() == 'Corrida B', 'resposta antiga não sobrescreve detalhe novo')

    # Optimistic delete: pixels/counter/preview move before backend resolves.
    page.click('[data-activity-detail-panel] [data-close-activity-detail]')
    page.click('[data-open-activity-detail="1"]'); page.wait_for_selector('[data-delete-activity="1"]')
    page.click('[data-delete-activity="1"]')
    page.wait_for_timeout(35)
    a(page.locator('[data-history-row][data-activity-id="1"]').count() == 0, 'atividade desaparece imediatamente antes do backend')
    a(page.locator('[data-activity-detail-drawer]').is_hidden(), 'preview fecha imediatamente ao apagar atividade aberta')
    a(page.locator('[data-summary-activities]').inner_text() == '1', 'contador de atividades atualiza otimisticamente')
    a(page.evaluate('__state.fetches.delete') == 1, 'delete backend foi iniciado em paralelo')
    a(page.evaluate('__state.undoAction !== null'), 'undo fica disponível imediatamente')
    page.wait_for_timeout(190)
    a(page.locator('[data-history-row]').count() == 1, 'sucesso mantém item removido após refresh exato')

    # Undo restores item, exact summary and row ordering via existing restore endpoint.
    page.evaluate('() => __state.undoAction()')
    page.wait_for_function("document.querySelectorAll('[data-history-row]').length === 2")
    a(page.locator('[data-history-row]').nth(0).get_attribute('data-activity-id') == '1', 'undo recoloca atividade na posição correta')
    a(page.locator('[data-summary-activities]').inner_text() == '2', 'undo restaura contador exato')
    a(page.evaluate('__state.fetches.restore') == 1, 'undo usa endpoint de restauração')
    page.wait_for_function("document.querySelector('[data-detail-title]')?.textContent === 'Corrida A'")
    a(not page.locator('[data-activity-detail-drawer]').is_hidden(), 'undo reabre preview quando a atividade apagada estava aberta')
    a(page.locator('[data-detail-title]').inner_text() == 'Corrida A', 'undo restaura o preview da atividade correta')

    # Failed optimistic delete rolls row/count/detail back.
    page.click('[data-open-activity-detail="2"]'); page.wait_for_selector('[data-delete-activity="2"]')
    page.evaluate('() => { __state.failNextDelete=true; }')
    page.click('[data-delete-activity="2"]'); page.wait_for_timeout(35)
    a(page.locator('[data-history-row][data-activity-id="2"]').count() == 0, 'falha ainda começa com resposta otimista')
    page.wait_for_timeout(190)
    a(page.locator('[data-history-row][data-activity-id="2"]').count() == 1, 'falha restaura linha')
    a(page.locator('[data-summary-activities]').inner_text() == '2', 'falha restaura contador')
    a(not page.locator('[data-activity-detail-drawer]').is_hidden(), 'falha restaura preview anteriormente aberto')
    a(page.locator('[data-detail-title]').inner_text() == 'Corrida B', 'preview restaurado corresponde à atividade correta')
    a(page.evaluate("__state.notices.some(x => x.type === 'error')"), 'falha comunica mensagem curta')
    a(not errors, 'sem erros JavaScript')

    browser.close()
    print(f'✓ browser activity product UX: {assertions} assertions')
