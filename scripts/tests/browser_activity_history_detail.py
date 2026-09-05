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
<main class="activities-page" data-activities-page>
  <div class="activity-history-workspace" data-activity-history-workspace>
    <div class="activity-history-left">
      <section class="activity-history-summary" data-history-summary><article><span>Atividades</span><strong>2</strong><small>7 dias</small></article><article><span>Tempo</span><strong>1h07</strong><small>registrado</small></article><article><span>Distância</span><strong>13 km</strong><small>acumulado</small></article><article><span>Elevação</span><strong>80 m</strong><small>acumulado</small></article></section>
    <section class="activity-history" data-activity-history data-initial-state="ready" data-initial-cursor="" data-initial-total="2">
      <div class="activity-history-toolbar"><div><h2>Histórico</h2></div></div>
      <div class="activity-list" data-activity-list>
        <article class="activity-list-row" data-history-row data-activity-id="1" data-activity-title="Corrida A"><button type="button" class="activity-row-main" data-open-activity-detail="1"><span>Corrida A</span></button></article>
        <article class="activity-list-row" data-history-row data-activity-id="2" data-activity-title="Corrida B"><button type="button" class="activity-row-main" data-open-activity-detail="2"><span>Corrida B</span></button></article>
      </div>
      <div data-history-more hidden><button data-history-load-more></button><span data-history-count></span></div>
      <span data-activity-history-status></span>
    </section>
    </div>
    <aside class="activity-detail-placeholder" data-detail-desktop-placeholder><div><strong>Detalhes</strong><span>Selecione</span></div></aside>
    <div class="activity-detail-drawer" data-activity-detail-drawer hidden>
      <button type="button" class="activity-detail-backdrop" data-close-activity-detail aria-label="Fechar"></button>
      <section class="activity-detail-panel" role="dialog" data-activity-detail-panel>
        <header>
          <div><div class="activity-detail-kicker"><span class="activity-detail-kicker-main"><span data-detail-sport>Atividade</span><span data-detail-visibility></span></span><a data-detail-compare href="#">Comparar</a></div><h2 data-detail-title>Carregando</h2><p data-detail-date></p></div>
          <div class="activity-detail-header-actions"><button type="button" class="activity-secondary-button activity-detail-expand" data-expand-activity-detail>Abrir detalhes</button><button type="button" data-close-activity-detail>×</button></div>
        </header>
        <div class="activity-detail-loading" data-detail-loading><i></i><span>Carregando</span></div>
        <div data-detail-content hidden></div>
      </section>
    </div>
  </div>
  <div style="height:1800px"></div>
</main>
</body></html>'''

MOCK = r'''() => {
  const activities = {
    '1': {
      id:'1', titulo:'Corrida A', modalidade:'Corrida', modalidade_slug:'corrida', data:'05/09/2026', hora:'07:30', visibilidade:'privado',
      metricas:[{rotulo:'Distância',valor:'5,00 km'},{rotulo:'Duração',valor:'25:00'},{rotulo:'Ritmo',valor:'5:00/km'}],
      metricas_compartilhamento:[{key:'distance',rotulo:'Distância',valor:'5,00 km'},{key:'duration',rotulo:'Duração',valor:'25:00'},{key:'pace',rotulo:'Ritmo',valor:'5:00/km'}],
      dados:[], equipamentos:[{nome:'Tênis A'}], unidades:[], usa_trechos:false, observacoes:'Treino leve', esforco:5,
      rota:{geojson:{type:'LineString',coordinates:[[-53.390,-27.360],[-53.389,-27.361],[-53.388,-27.360],[-53.390,-27.360]]},distancia_m:5000,ganho_m:80,perfil:[100,104,102,106,101]}
    },
    '2': {
      id:'2', titulo:'Corrida B', modalidade:'Corrida', modalidade_slug:'corrida', data:'04/09/2026', hora:'18:10', visibilidade:'publico',
      metricas:[{rotulo:'Distância',valor:'8,00 km'},{rotulo:'Duração',valor:'42:00'},{rotulo:'Ritmo',valor:'5:15/km'}],
      metricas_compartilhamento:[{key:'distance',rotulo:'Distância',valor:'8,00 km'},{key:'duration',rotulo:'Duração',valor:'42:00'},{key:'pace',rotulo:'Ritmo',valor:'5:15/km'}],
      dados:[], equipamentos:[], unidades:[], usa_trechos:false, observacoes:'', esforco:null,
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

def boot(page):
    page.set_content(HTML, wait_until='domcontentloaded')
    page.add_style_tag(path=str(ROOT / 'public/assets/css/atividades.css'))
    page.evaluate(MOCK)
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
    a(desktop.locator('[data-detail-title]').inner_text() == 'Corrida A', 'desktop abre detalhe da atividade selecionada')
    a(desktop.locator('[data-history-row][data-activity-id="1"]').evaluate("el=>el.classList.contains('is-active-detail')"), 'linha ativa fica destacada')
    a(desktop.locator('[data-detail-route-preview] svg').count() == 1, 'prévia usa silhueta SVG leve, sem mapa Leaflet')
    a(desktop.locator('[data-elevation-profile]').count() == 1, 'perfil de elevação permanece disponível')
    a(desktop.locator('[data-repeat-activity]').count() == 1 and desktop.locator('[data-share-activity]').count() == 1, 'ações principais de repetir/compartilhar ficam acessíveis')
    a(desktop.locator('[data-activity-detail-drawer]').bounding_box()['width'] >= 430, 'painel desktop ganhou largura útil relevante')
    a(desktop.locator('[data-activity-detail-drawer]').bounding_box()['height'] >= 500, 'painel desktop aproveita a altura útil da viewport')

    left_box = desktop.locator('.activity-history-left').bounding_box()
    summary_box = desktop.locator('[data-history-summary]').bounding_box()
    history_box = desktop.locator('[data-activity-history]').bounding_box()
    drawer_box = desktop.locator('[data-activity-detail-drawer]').bounding_box()
    ratio = left_box['width'] / (left_box['width'] + drawer_box['width'])
    a(abs(summary_box['width'] - history_box['width']) <= 1, 'resumo e Histórico usam exatamente a mesma largura')
    a(.57 <= ratio <= .64, f'grade desktop reserva aproximadamente 60% à esquerda ({ratio:.3f})')
    a(drawer_box['height'] >= 800, 'Preview usa a altura disponível até perto do fim da viewport')

    desktop.click('[data-expand-activity-detail]')
    a(desktop.locator('[data-activity-detail-drawer]').evaluate("el=>el.classList.contains('is-expanded-detail')"), 'Abrir detalhes expande o mesmo painel sem duplicar conteúdo')
    a(desktop.locator('[data-expand-activity-detail]').is_hidden(), 'detalhe expandido remove a ação redundante Voltar à prévia')
    expanded = desktop.locator('[data-activity-detail-panel]').bounding_box()
    a(expanded['width'] >= 1200 and expanded['height'] >= 850, 'detalhe expandido usa praticamente toda a viewport desktop')
    desktop.click('[data-activity-detail-panel] [data-close-activity-detail]')
    a(not desktop.locator('[data-activity-detail-drawer]').evaluate("el=>el.classList.contains('is-expanded-detail')"), 'X no detalhe expandido volta à Preview')
    a(not desktop.locator('[data-activity-detail-drawer]').is_hidden(), 'X expandido mantém a atividade selecionada')
    a(desktop.locator('[data-history-row][data-activity-id="1"]').evaluate("el=>el.classList.contains('is-active-detail')"), 'atividade segue selecionada ao voltar à Preview')

    desktop.click('[data-activity-detail-panel] [data-close-activity-detail]')
    desktop.click('[data-open-activity-detail="1"]')
    desktop.wait_for_selector('[data-detail-content]:not([hidden])')
    a(desktop.evaluate("__detailFetchCount['1']") == 1, 'reabrir atividade recente reutiliza cache do detalhe')

    # Rapid switching: id 1 is deliberately slower; id 2 must win without stale overwrite.
    desktop.click('[data-activity-detail-panel] [data-close-activity-detail]')
    desktop.evaluate("() => { window.__detailFetchCount['1']=0; window.__detailFetchCount['2']=0; }")
    # Cache 1 exists, so clear it indirectly by reloading the fixture for the cancellation scenario.
    desktop.close()
    desktop = browser.new_page(viewport={'width': 1440, 'height': 900})
    errors2=[]
    desktop.on('pageerror', lambda exc: errors2.append(str(exc)))
    boot(desktop)
    desktop.click('[data-open-activity-detail="1"]')
    desktop.wait_for_timeout(8)
    desktop.click('[data-open-activity-detail="2"]')
    desktop.wait_for_function("document.querySelector('[data-detail-title]')?.textContent === 'Corrida B'")
    desktop.wait_for_timeout(120)
    a(desktop.locator('[data-detail-title]').inner_text() == 'Corrida B', 'troca rápida não deixa resposta lenta sobrescrever atividade nova')
    a(desktop.evaluate("__detailFetchCount['1']") == 1 and desktop.evaluate("__detailFetchCount['2']") == 1, 'troca rápida faz apenas os requests necessários')
    a(not errors2, 'desktop não gera erros JS')

    mobile = browser.new_page(viewport={'width': 390, 'height': 844})
    mobile_errors=[]
    mobile.on('pageerror', lambda exc: mobile_errors.append(str(exc)))
    boot(mobile)
    mobile.evaluate("document.querySelector('[data-open-activity-detail=\"2\"]')?.scrollIntoView({block:'center'})")
    mobile.wait_for_timeout(30)
    scroll_before = mobile.evaluate('window.scrollY')
    mobile.click('[data-open-activity-detail="2"]')
    mobile.wait_for_selector('[data-detail-content]:not([hidden])')
    a(mobile.locator('[data-activity-detail-drawer]').evaluate("el=>getComputedStyle(el).position") == 'fixed', 'mobile abre detalhe em tela/overlay, não em coluna lateral')
    a(mobile.evaluate("document.documentElement.classList.contains('activity-detail-open')"), 'mobile trava o histórico enquanto detalhe está aberto')
    mobile.click('[data-activity-detail-panel] [data-close-activity-detail]')
    mobile.wait_for_timeout(60)
    scroll_after = mobile.evaluate('window.scrollY')
    a(abs(scroll_after - scroll_before) <= 2, 'voltar no mobile preserva a posição anterior do Histórico')
    a(not mobile_errors, 'mobile não gera erros JS')

    if errors:
        raise AssertionError('Erros JS desktop: ' + ' | '.join(errors))
    browser.close()
    print(f'✓ browser activity history detail: {count[0]} assertions')
