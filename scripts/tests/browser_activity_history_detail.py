#!/usr/bin/env python3
from pathlib import Path
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f"Playwright indisponível: {exc}", file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]

HTML = r"""<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"></head><body>
<nav data-global-navbar>Navbar global</nav>
<main class="main-content activities-page" data-activities-page>
  <section class="activities-history-view" data-activities-history-view>
    <header class="activities-toolbar"><h1>Activities</h1><div><a>Quick run</a><a>Record with GPS</a><button>Tools</button><button>+ Log activity</button></div></header>
    <div class="activity-history-workspace" data-activity-history-workspace>
      <div class="activity-history-left">
        <section class="activity-history-summary" data-history-summary><article><span>Atividades</span><strong>2</strong><small>7 dias</small></article><article><span>Tempo</span><strong>1h07</strong><small>registrado</small></article><article><span>Distância</span><strong>13 km</strong><small>acumulado</small></article></section>
        <section class="activity-history" data-activity-history data-initial-state="ready" data-initial-cursor="" data-initial-total="2">
          <div class="activity-history-toolbar"><div><h2>Histórico</h2></div></div>
          <div class="activity-list" data-activity-list>
            <article class="activity-list-row" data-history-row data-activity-id="1" data-activity-title="Corrida A"><button type="button" class="activity-row-main" data-open-activity-detail="1"><span>Corrida A</span></button></article>
            <article class="activity-list-row" data-history-row data-activity-id="2" data-activity-title="Corrida B"><button type="button" class="activity-row-main" data-open-activity-detail="2"><span>Corrida B</span></button></article>
          </div>
          <div data-history-more hidden><button data-history-load-more></button><span data-history-count></span></div>
          <span data-activity-history-status></span><div data-history-scroll-fixture style="height:1400px"></div>
        </section>
      </div>
      <div class="activity-detail-preview-host" data-activity-detail-preview-host>
        <aside class="activity-detail-placeholder" data-detail-desktop-placeholder><div><strong>Detalhes</strong><span>Selecione</span></div></aside>
        <div class="activity-detail-drawer" data-activity-detail-drawer hidden>
          <section class="activity-detail-panel" role="region" data-activity-detail-panel>
            <div class="activity-detail-topbar" data-detail-topbar hidden><button type="button" class="activity-detail-back-link" data-back-activity-workspace>← <span>Voltar às atividades</span></button></div>
            <header><div class="activity-detail-header-copy"><div class="activity-detail-kicker"><span class="activity-detail-kicker-main"><span data-detail-sport>Atividade</span><span data-detail-visibility></span></span></div><h2 data-detail-title>Carregando</h2><p data-detail-date></p><div class="activity-detail-badges" data-detail-badges></div><div class="activity-detail-header-metrics" data-detail-header-metrics></div></div><div class="activity-detail-header-actions"><div class="activity-detail-full-actions" data-detail-full-actions hidden><button type="button" class="activity-secondary-button" data-share-activity>Compartilhar</button><a class="activity-primary-action" data-detail-edit href="#">Editar</a><details class="activity-v3-actions-menu activity-detail-header-menu" data-detail-actions-menu><summary class="activity-secondary-button">…</summary><div role="menu"><a role="menuitem" data-detail-compare href="#">Comparar</a><a role="menuitem" data-detail-repeat href="#">Repetir / usar como base</a><button type="button" role="menuitem" data-detail-stats>Excluir das estatísticas</button><button type="button" role="menuitem" class="is-danger" data-detail-delete>Excluir</button></div></details></div><button type="button" data-close-activity-detail>×</button></div></header>
            <div class="activity-detail-loading" data-detail-loading><i></i><span>Carregando</span></div><div data-detail-content hidden></div>
          </section>
        </div>
      </div>
    </div>
  </section>
  <section class="activity-detail-page" data-activity-detail-view hidden></section>
</main>
<footer data-global-footer>Footer global</footer>
</body></html>"""

MOCK = r'''() => {
  const activities = {
    '1': {
      id:'1', titulo:'Corrida A', modalidade:'Corrida', modalidade_slug:'corrida', data:'05/09/2026', hora:'07:30', visibilidade:'privado',
      metricas:[{rotulo:'Distância',valor:'5,00 km'},{rotulo:'Duração',valor:'25:00'},{rotulo:'Ritmo',valor:'5:00/km'}],
      metricas_compartilhamento:[{key:'distance',rotulo:'Distância',valor:'5,00 km'},{key:'duration',rotulo:'Duração',valor:'25:00'},{key:'pace',rotulo:'Ritmo',valor:'5:00/km'}],
      dados:[], equipamentos:[{nome:'Tênis A'}], unidades:[], usa_trechos:false, observacoes:'Treino leve', esforco:5, origem:'gps',
      rota:{geojson:{type:'LineString',coordinates:[[-53.390,-27.360],[-53.389,-27.361],[-53.388,-27.360],[-53.390,-27.360]]},distancia_m:5000,ganho_m:80,perfil:[100,104,102,106,101]}
    },
    '2': {
      id:'2', titulo:'Corrida B', modalidade:'Corrida', modalidade_slug:'corrida', data:'04/09/2026', hora:'18:10', visibilidade:'publico',
      metricas:[{rotulo:'Distância',valor:'8,00 km'},{rotulo:'Duração',valor:'42:00'},{rotulo:'Ritmo',valor:'5:15/km'}],
      metricas_compartilhamento:[{key:'distance',rotulo:'Distância',valor:'8,00 km'},{key:'duration',rotulo:'Duração',valor:'42:00'},{key:'pace',rotulo:'Ritmo',valor:'5:15/km'}],
      dados:[], equipamentos:[], unidades:[], usa_trechos:false, observacoes:'', esforco:null, origem:'manual', stream_capabilities:{has_streams:true,has_laps:false,has_heart_rate:false,available_streams:['distance','pace']},
      rota:{geojson:{type:'LineString',coordinates:[[-53.400,-27.350],[-53.395,-27.355],[-53.390,-27.350]]},distancia_m:8000,ganho_m:null,perfil:[]}
    }
  };
  window.__detailFetchCount = {'1':0,'2':0};
  window.StrideBRI18n = {
    locale:'pt-BR',
    t:(key, values={}, fallback=null) => {
      const dict = {
        'common.activity':'Atividade','common.loading':'Carregando','common.public':'Público','common.friends':'Amigos','common.equipment':'Equipamentos','common.try_again':'Tentar novamente',
        'activity.loading':'Carregando','activity.loading_details':'Carregando detalhes','activity.load_error':'Erro','activity.load_short_error':'Erro ao carregar','activity.only_me':'Somente eu',
        'activity.effort_perceived':'Esforço percebido','activity.energy':'Energia','activity.data_section':'Dados','activity.notes':'Observações','activity.route_title':'Rota','activity.route_map_aria':'Prévia da rota',
        'activity.route_distance':'Distância','activity.route_gain':'Elevação','activity.elevation_profile':'Perfil de elevação','activity.elevation_profile_aria':'Perfil','activity.route_attribution':'Prévia simplificada',
        'activity.detail_share':'Compartilhar','activity.repeat':'Repetir','activity.detail_edit':'Editar','activity.detail_delete':'Apagar','activity.detail.expand':'Abrir detalhes','activity.detail.collapse':'Voltar à prévia','activity.close_details':'Fechar detalhes',
        'activity.compare':'Comparar','activity.history.loaded.one':'1 atividade carregada','activity.history.loaded.other':'{count} atividades carregadas'
      };
      let out = dict[key] ?? fallback ?? key;
      Object.entries(values).forEach(([name,value]) => out = String(out).replaceAll(`{${name}}`, String(value)));
      return out;
    },
    number:(value,digits=0)=>Number(value).toLocaleString('pt-BR',{maximumFractionDigits:digits}),
    sport:(_slug,fallback)=>fallback,
    date:(date,opts)=>date.toLocaleDateString('pt-BR',opts)
  };
  window.fetch = (url, options={}) => {
    const parsed = new URL(String(url), 'http://stridebr.local');
    if (!parsed.pathname.endsWith('/api/atividade-detalhe.php')) return Promise.resolve({ok:true,json:async()=>({ok:true})});
    const id = parsed.searchParams.get('id');
    window.__detailFetchCount[id] = (window.__detailFetchCount[id] || 0) + 1;
    const delay = id === '1' ? 90 : 18;
    return new Promise((resolve,reject) => {
      const timer = setTimeout(() => resolve({ok:true,json:async()=>({ok:true,atividade:activities[id]})}), delay);
      options.signal?.addEventListener('abort', () => { clearTimeout(timer); reject(new DOMException('Aborted','AbortError')); }, {once:true});
    });
  };
}'''

PAGE_CSS = (
    'style.css',
    'atividades.css',
    'product-insights.css',
    'activity-exchange.css',
    'ui-refresh.css',
    'activity-sharing.css',
)

def boot(page):
    page.set_content(HTML, wait_until='domcontentloaded')
    for css_file in PAGE_CSS:
        page.add_style_tag(path=str(ROOT / 'public/assets/css' / css_file))
    page.evaluate(MOCK)
    page.evaluate("""() => {
      const replace = history.replaceState.bind(history);
      const stack = [{state:{}, url:''}]; let index = 0;
      history.pushState = (state,title,url) => { stack.splice(index+1); stack.push({state,url:String(url||'')}); index += 1; window.__historyPush={state,url:String(url||'')}; replace(state,title,location.href); };
      history.replaceState = (state,title,url) => { stack[index]={state,url:String(url||'')}; replace(state,title,location.href); };
      history.back = () => { if(index>0) index-=1; const item=stack[index]; replace(item.state,'',location.href); window.dispatchEvent(new PopStateEvent('popstate',{state:item.state})); };
      history.forward = () => { if(index<stack.length-1) index+=1; const item=stack[index]; replace(item.state,'',location.href); window.dispatchEvent(new PopStateEvent('popstate',{state:item.state})); };
    }""")
    page.add_script_tag(path=str(ROOT / 'public/assets/js/scripts.js'))
    page.add_script_tag(path=str(ROOT / 'public/assets/js/web-map.js'))
    page.add_script_tag(path=str(ROOT / 'public/assets/js/activity-detail-v3.js'))
    page.add_script_tag(path=str(ROOT / 'public/assets/js/atividades.js'))
    page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
    page.wait_for_timeout(40)

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    count = [0]
    def a(condition, message):
        count[0] += 1
        if not condition:
            raise AssertionError(message)

    desktop = browser.new_page(viewport={'width': 1440, 'height': 900})
    errors = []
    desktop.on('pageerror', lambda exc: errors.append(str(exc)))
    boot(desktop)

    desktop.click('[data-open-activity-detail="1"]')
    desktop.wait_for_selector('[data-detail-content]:not([hidden])')
    a(desktop.locator('[data-detail-title]').inner_text() == 'Corrida A', 'seleção abre a activity correta')
    a(desktop.locator('[data-activity-v3].is-preview').count() == 1, 'seleção abre preview compacto')
    a(desktop.locator('[data-activity-v3].is-preview [role="tab"]').count() == 0, 'preview não contém tabs')
    a(desktop.locator('[data-activity-v3].is-preview [data-delete-activity]').count() == 0, 'preview não expõe excluir')
    a(desktop.locator('[data-activity-v3].is-preview [data-share-activity]').count() == 1, 'preview expõe Compartilhar')
    a(desktop.locator('[data-activity-v3].is-preview [data-open-full-activity-details]').count() == 1, 'preview expõe Abrir detalhes')
    a(desktop.locator('[data-elevation-profile]').count() == 0, 'preview não expõe perfil de elevação')
    a(desktop.locator('[data-activity-detail-panel]').get_attribute('role') == 'region', 'surface de Activity é região da página')
    a(desktop.locator('[data-activity-detail-panel]').get_attribute('aria-modal') is None, 'surface de Activity não é modal')
    panel_box = desktop.locator('[data-activity-detail-panel]').bounding_box()
    map_box = desktop.locator('[data-activity-v3-map]').bounding_box()
    a(map_box is not None and panel_box is not None and map_box['width'] <= panel_box['width'] + 1, 'mapa permanece dentro do frame/painel')

    desktop.click('[data-open-full-activity-details]')
    desktop.wait_for_selector('[data-activity-v3].is-detail')
    a(desktop.locator('[data-activities-history-view]').is_hidden(), 'detail oculta toda a surface de History')
    a(desktop.locator('[data-activity-detail-view]').is_visible(), 'detail assume a main como surface irmã')
    a(desktop.locator('[data-activity-detail-drawer]').evaluate("el=>el.parentElement?.matches('[data-activity-detail-view]')"), 'drawer é movido para o host full-page')
    a(desktop.get_by_text('Quick run', exact=True).is_hidden(), 'Quick run desaparece no detail')
    a(desktop.get_by_text('Record with GPS', exact=True).is_hidden(), 'Record with GPS desaparece no detail')
    a(desktop.get_by_text('Tools', exact=True).is_hidden(), 'Tools desaparece no detail')
    a(desktop.get_by_text('+ Log activity', exact=True).is_hidden(), 'Log activity desaparece no detail')
    a(desktop.locator('[data-global-navbar]').is_visible(), 'navbar permanece visível')
    a(desktop.locator('[data-global-footer]').count() == 1, 'footer permanece no documento')
    a(desktop.locator('[data-back-activity-workspace]').is_visible(), 'detail mostra Voltar às atividades')
    a(desktop.locator('[data-detail-topbar]').is_visible(), 'Voltar fica em topbar própria')
    a(desktop.locator('[data-detail-full-actions]').is_visible(), 'ações full detail ficam no header')
    a(desktop.locator('[data-activity-v3].is-detail [role="tablist"]').count() == 0, 'detail sem capabilities extras não mostra tab Resumo sozinha')
    a(desktop.locator('[data-detail-badges] span').all_inner_texts().count('Somente eu') == 1, 'privacidade aparece uma única vez nos badges')
    a('GPS do app' in desktop.locator('[data-detail-badges]').inner_text(), 'origem gps é humanizada no header')
    a(desktop.locator('.activity-v3-route.has-context-rail').count() == 1, 'múltiplos contextos habilitam layout mapa 8/4')
    for width in (1920, 1440, 1366, 1280, 1024, 768, 430, 390):
        desktop.set_viewport_size({'width': width, 'height': 900 if width >= 768 else 844})
        desktop.wait_for_timeout(20)
        a(desktop.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1'), f'detail não cria overflow global em {width}px')
    desktop.set_viewport_size({'width': 1440, 'height': 900})
    for theme in ('light','dark'):
        desktop.evaluate('(theme) => document.documentElement.dataset.theme = theme', theme)
        a(desktop.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1'), f'detail permanece contido em tema {theme}')
    a(desktop.locator('[data-detail-full-actions] [data-detail-delete]').count() == 1, 'delete fica apenas no menu do detail header')
    a(not desktop.evaluate("document.documentElement.classList.contains('ui-modal-scroll-locked')"), 'detail full-page não bloqueia body como modal')

    desktop.click('[data-back-activity-workspace]')
    desktop.wait_for_timeout(100)
    a(desktop.locator('[data-activities-history-view]').is_visible(), 'Voltar restaura History')
    a(desktop.locator('[data-activity-detail-view]').is_hidden(), 'Voltar oculta host full-page')
    a(desktop.locator('[data-activity-detail-drawer]').evaluate("el=>el.parentElement?.matches('[data-activity-detail-preview-host]')"), 'Voltar devolve drawer ao preview host')
    a(desktop.locator('[data-history-row][data-activity-id="1"]').evaluate("el=>el.classList.contains('is-active-detail')"), 'seleção permanece preservada ao voltar')

    desktop.click('[data-open-activity-detail="2"]')
    desktop.wait_for_function("document.querySelector('[data-detail-title]')?.textContent === 'Corrida B'")
    desktop.click('[data-open-full-activity-details]')
    desktop.wait_for_selector('[data-activity-v3].is-detail')
    a(desktop.locator('[data-activity-v3].is-detail [role="tablist"]').count() == 1, 'múltiplas capabilities exibem tab bar')
    a('Registro manual' in desktop.locator('[data-detail-badges]').inner_text(), 'origem manual é humanizada no header')
    a(desktop.locator('.activity-v3-route.has-context-rail').count() == 0, 'um único contexto não reserva rail 4/12')
    desktop.click('[data-back-activity-workspace]')
    desktop.wait_for_timeout(60)

    desktop.evaluate("() => { window.__detailFetchCount['1']=0; window.__detailFetchCount['2']=0; }")
    desktop.click('[data-activity-detail-panel] [data-close-activity-detail]')
    desktop.click('[data-open-activity-detail="1"]')
    desktop.wait_for_timeout(8)
    desktop.click('[data-open-activity-detail="2"]')
    desktop.wait_for_function("document.querySelector('[data-detail-title]')?.textContent === 'Corrida B'")
    desktop.wait_for_timeout(120)
    a(desktop.locator('[data-detail-title]').inner_text() == 'Corrida B', 'resposta antiga não sobrescreve Activity nova')
    a(not errors, 'desktop não gera erros JS')

    mobile = browser.new_page(viewport={'width': 390, 'height': 844})
    mobile_errors = []
    mobile.on('pageerror', lambda exc: mobile_errors.append(str(exc)))
    boot(mobile)
    mobile.click('[data-open-activity-detail="2"]')
    mobile.wait_for_selector('[data-detail-content]:not([hidden])')
    a(mobile.locator('[data-activity-detail-panel]').get_attribute('aria-modal') is None, 'preview mobile também não usa aria-modal')
    mobile.click('[data-open-full-activity-details]')
    mobile.wait_for_selector('[data-activity-v3].is-detail')
    a(mobile.locator('[data-activities-history-view]').is_hidden(), 'detail mobile também assume toda a main')
    a(mobile.locator('[data-activity-detail-view]').is_visible(), 'host full-page mobile fica visível')
    a(not mobile.evaluate("document.documentElement.classList.contains('ui-modal-scroll-locked')"), 'detail mobile não usa lock modal global')
    a(not mobile_errors, 'mobile não gera erros JS')

    browser.close()
    print(f'✓ browser activity history detail: {count[0]} assertions')
