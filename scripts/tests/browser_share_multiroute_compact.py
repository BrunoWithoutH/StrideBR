#!/usr/bin/env python3
from pathlib import Path
import sys
try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr); sys.exit(2)
ROOT=Path(__file__).resolve().parents[2]

HTML='''<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>
<div data-activity-detail-drawer>
<script type="application/json" data-activity-share-data id="payload"></script>
<button type="button" data-share-activity>Compartilhar</button>
</div>
<div class="activity-share-modal" data-share-modal hidden>
  <div class="activity-share-preview-shell" data-share-preview-shell><div data-share-preview-format></div><div class="activity-share-preview-stage"><canvas data-share-canvas width="1080" height="1920"></canvas></div></div>
  <div class="activity-share-route-picker" data-share-route-picker hidden><div data-share-route-picker-list></div><button data-share-route-picker-close></button></div>
  <section class="activity-share-panel"><header><button data-close-share>Fechar</button></header><div class="activity-share-body"><div class="activity-share-controls">
    <section data-share-master-switch hidden><button data-share-scope="session" aria-pressed="true">Sessão</button><button data-share-scope="single_segment" aria-pressed="false">Um trecho</button><button data-share-scope="multiple_segments" aria-pressed="false">Vários trechos</button></section>
    <section data-share-single-segment-picker hidden><div data-share-single-segment-list></div></section>
    <section data-share-multiple-summary hidden><strong data-share-segment-selection-summary></strong><button data-share-edit-segments>Alterar</button></section>
    <section data-share-single-only><div data-share-content-grid></div></section>
    <section data-share-background-block><div data-share-style-grid></div></section>
    <section data-share-session-only hidden><div data-share-session-layout-grid></div></section>
    <section data-share-content-options hidden><input type="radio" name="contentmode" value="activity" data-share-content-mode checked><input type="radio" name="contentmode" value="segments" data-share-content-mode><div data-share-segment-mode-options><input type="radio" name="segmentmode" value="together" data-share-segment-mode checked><input type="radio" name="segmentmode" value="separate" data-share-segment-mode></div></section>
    <fieldset data-share-composition-options><label><input type="radio" name="composition" value="standard" data-share-composition checked><span>Padrão</span></label><label><input type="radio" name="composition" value="compact" data-share-composition><span>Compacto</span></label></fieldset>
    <fieldset><label data-format-label="story"><input type="radio" name="format" value="story" data-share-format checked>Story</label><label data-format-label="portrait"><input type="radio" name="format" value="portrait" data-share-format>Retrato</label><label data-format-label="square"><input type="radio" name="format" value="square" data-share-format>Quadrado</label></fieldset>
    <fieldset data-share-color-options><input type="radio" data-share-background-color value="deep" checked></fieldset>
    <div data-share-map-style-options hidden><label><input type="radio" value="street" data-share-map-style checked>Ruas</label><label><input type="radio" value="satellite" data-share-map-style>Satélite</label></div>
    <input type="checkbox" data-share-show="route" checked><input type="checkbox" data-share-show="date">
    <input type="checkbox" data-share-heading-mode checked>
    <input type="range" min="50" max="200" value="100" data-share-route-scale><output data-share-route-scale-value></output>
    <div data-share-metric-options></div><input data-share-caption>
    <section data-share-segments-group hidden><details data-share-segment-disclosure><summary>Escolher <small data-share-segment-selection-inline></small></summary><button type="button" data-share-select-all>Selecionar todos</button><div data-share-segment-list></div></details><label data-share-segment-preview-picker hidden><select data-share-segment-preview></select></label></section>
    <section data-share-comparison-controls hidden><select data-share-comparison-metric></select><select data-share-comparison-reference></select></section>
  </div></div><p data-share-status></p><footer><button data-copy-share></button><button data-download-share></button><button data-native-share></button></footer></section>
</div>
</body></html>'''

ACTIVITY={
  'id':'a1','titulo':'Treino misto','modalidade':'Corrida','modalidade_slug':'corrida','data':'05/09/2026','hora':'07:00','usa_trechos':True,
  'metricas':[{'key':'duration','rotulo':'Duração','valor':'30:00'}],
  'trechos':[
    {'id':'u1','titulo':'Aquecimento','modalidade':'Corrida','modalidade_slug':'corrida','duracao_segundos':360.0,'distancia_metros':1000,'metricas':[{'key':'distance','rotulo':'Distância','valor':'1 km'}],'geojson':{'type':'LineString','coordinates':[[-53.40,-27.36],[-53.399,-27.359]]}},
    {'id':'u2','titulo':'Tiro 1','modalidade':'Corrida','modalidade_slug':'corrida','duracao_segundos':240.438,'distancia_metros':1000,'metricas':[{'key':'distance','rotulo':'Distância','valor':'1 km'}],'geojson':{'type':'LineString','coordinates':[[-53.39,-27.35],[-53.388,-27.349],[-53.387,-27.351]]}},
    {'id':'u3','titulo':'Desaquecimento','modalidade':'Corrida','modalidade_slug':'corrida','duracao_segundos':420.0,'distancia_metros':1500,'metricas':[{'key':'distance','rotulo':'Distância','valor':'1,5 km'}],'geojson':{'type':'LineString','coordinates':[[-53.38,-27.34],[-53.379,-27.341],[-53.378,-27.342]]}},
    {'id':'u4','titulo':'Tiro 2','modalidade':'Corrida','modalidade_slug':'corrida','duracao_segundos':238.921,'distancia_metros':1000,'metricas':[{'key':'distance','rotulo':'Distância','valor':'1 km'}],'geojson':{'type':'LineString','coordinates':[[-53.37,-27.33],[-53.368,-27.329],[-53.367,-27.331]]}}
  ]
}
SINGLE={**ACTIVITY,'usa_trechos':False,'trechos':[],'geojson':{'type':'LineString','coordinates':[[-53.4,-27.36],[-53.39,-27.35]]},'distancia_metros':5000}
ONE_REAL={**ACTIVITY,'usa_trechos':True,'trechos':[
  {'id':'summary','tipo':'sessao','titulo':'Resumo','synthetic':True},
  ACTIVITY['trechos'][0],
]}

TRANSLATIONS={
 'activity.share.segment':'Trecho','common.activity':'Atividade','nav.physical_activity':'Atividade física',
 'activity.share.format_story':'Story','activity.share.format_portrait':'Retrato','activity.share.format_square':'Quadrado',
 'activity.share.composition_standard':'Padrão','activity.share.composition_compact':'Compacto','activity.share.session_overview':'Visão geral','activity.share.session_by_segment':'Por trecho','activity.share.comparison':'Comparação','activity.share.highlight':'Destaque','activity.share.sequence':'Sequência','activity.share.session_summary':'Resumo','activity.share.list':'Lista','activity.share.minimal':'Minimal','activity.share.family_routes':'Percursos','activity.share.family_summary':'Resumo','activity.share.scope_session':'Sessão','activity.share.scope_single_segment':'Um trecho','activity.share.scope_multiple_segments':'Vários trechos','activity.share.segments':'Trechos','activity.share.total_time':'Tempo total','activity.share.logged_time':'Tempo registrado','activity.share.total_distance':'Distância total','activity.share.logged_distance':'Distância registrada','activity.share.average_pace':'Ritmo médio','activity.share.total_elevation':'Elevação','activity.share.logged_elevation':'Elevação registrada','activity.share.max_segment_gain':'Maior ganho em trecho','activity.share.max_altitude':'Maior altitude','activity.share.best_segment_pace':'Melhor ritmo de trecho','activity.share.compare_time':'Tempo','activity.share.compare_pace':'Ritmo','activity.share.compare_speed':'Velocidade','activity.share.compare_power':'Potência','activity.share.compare_heart':'FC média','activity.share.reference_best':'Melhor','activity.share.reference_average':'Média','activity.share.reference_target':'Alvo','activity.share.best':'MELHOR','activity.share.best_time':'MELHOR TEMPO','activity.share.fastest':'MAIS RÁPIDO','activity.share.highest_power':'MAIOR POTÊNCIA','activity.share.segment_count':'{count} trechos','activity.share.segments_selected':'{selected} de {total} trechos selecionados','activity.share.multiple_segments_help':'Compartilhe o subset','activity.share.change_selection':'Alterar','activity.share.rendering_segments':'Montando os trechos…','activity.share.rendering_preview':'Montando…','activity.share.rendering_map':'Montando mapa…','activity.share.download':'Baixar','activity.share.native':'Compartilhar','activity.share.choose_photo':'Escolher foto','activity.share.change_photo':'Trocar foto'
}
BOOT='''() => {window.__STRIDEBR_SHARE_TEST_HOOKS__=true; const tr='''+repr(TRANSLATIONS)+'''; window.StrideBRI18n={locale:'pt-BR',t:(key,values={},fallback=null)=>{let s=tr[key]||fallback||key;Object.entries(values).forEach(([k,v])=>s=String(s).replaceAll(`{${k}}`,String(v)));return s},number:(v,d=1)=>Number(v).toLocaleString('pt-BR',{maximumFractionDigits:d}),sport:(_s,f)=>f}; window.StrideBRBasemaps={share:{available:()=>false}}; window.requestIdleCallback=(cb)=>setTimeout(()=>cb({didTimeout:false,timeRemaining:()=>20}),0);}'''

def run_case(page,data):
    page.set_content(HTML,wait_until='domcontentloaded'); page.evaluate(BOOT)
    page.locator('#payload').evaluate('(el,data)=>el.textContent=JSON.stringify(data)',data)
    page.add_script_tag(path=str(ROOT/'public/assets/js/atividades.js'))
    page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
    page.wait_for_timeout(50); page.click('[data-share-activity]'); page.wait_for_timeout(90)

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    count=[0]
    def check(v,m): count.__setitem__(0,count[0]+1) or (None if v else (_ for _ in ()).throw(AssertionError(m)))

    single=browser.new_page(viewport={'width':1000,'height':760}); errors=[]; single.on('pageerror',lambda e:errors.append(str(e))); run_case(single,SINGLE)
    check(not single.locator('[data-share-modal]').is_hidden(),'atividade simples abre editor direto')
    check(single.locator('[data-share-master-switch]').is_hidden(),'atividade simples não precisa seletor de escopo')
    check(single.locator('[data-share-composition-options]').is_visible(),'atividade simples mostra composição Padrão/Compacto')
    check(all(not single.locator(f'[data-format-label="{f}"]').is_hidden() for f in ['story','portrait','square']),'atividade simples oferece Story/Retrato/Quadrado')
    check(single.locator('[data-share-route-picker]').is_hidden(),'atividade simples não abre wizard de rota')
    check(not errors,f'atividade simples sem erros JS {errors}')
    single.close()

    one=browser.new_page(viewport={'width':1000,'height':760}); one_errors=[]; one.on('pageerror',lambda e:one_errors.append(str(e))); run_case(one,ONE_REAL)
    check(one.locator('[data-share-master-switch]').is_hidden(),'resumo sintético + um Trecho real não cria Escopo artificial')
    check(len(one.evaluate('d=>StrideBRShareTest.shareableSegments(d)',ONE_REAL))==1,'shareableSegments contém somente o Trecho real')
    check(one.locator('[data-share-single-segment-picker]').is_hidden() and one.locator('[data-share-multiple-summary]').is_hidden(),'um Trecho real abre editor simples sem seletores redundantes')
    check('0 de 0' not in one.locator('body').inner_text() and '1 de 1' not in one.locator('body').inner_text(),'um Trecho real não exibe contagens artificiais 0 de 0 / 1 de 1')
    check(not one_errors,f'um Trecho real sem erros JS {one_errors}')
    one.close()

    page=browser.new_page(viewport={'width':1000,'height':760}); errors=[]; page.on('pageerror',lambda e:errors.append(str(e))); run_case(page,ACTIVITY)
    check(page.locator('[data-share-route-picker]').is_hidden(),'sessão não abre seletor/wizard antes do editor')
    check(page.locator('[data-share-master-switch]').is_visible(),'sessão mostra seletor de escopo dentro do editor')
    check(page.locator('[data-share-master-switch]').get_attribute('data-share-scope')=='session','Sessão é o escopo inicial')
    check(page.locator('[data-share-session-only]').is_visible(),'composições de sessão aparecem no editor')
    check(page.locator('[data-share-composition-options]').is_hidden(),'Padrão/Compacto de atividade não aparecem como picker paralelo na sessão')
    check(not page.locator('[data-format-label="story"]').is_hidden(),'Story permanece disponível em sessão')
    check(page.locator('[data-format-label="portrait"]').is_hidden() and page.locator('[data-format-label="square"]').is_hidden(),'Retrato/Quadrado são escondidos na sessão Web 1.0')
    labels=page.locator('[data-share-session-layout-card]').all_inner_texts()
    check(any('Visão geral' in x for x in labels) and any('Comparação' in x for x in labels) and any('Minimal' in x for x in labels),'picker contém intenções de percurso e resumo')
    check(sum('Compacto' in x for x in labels)==1,'Compacto de sessão é composição única, não formato')

    page.click('[data-share-scope="single_segment"]'); page.wait_for_timeout(50)
    check(page.locator('[data-share-single-segment-picker]').is_visible(),'Um trecho abre seletor visual dentro do editor')
    check(page.locator('[data-share-single-segment-index]').count()==4,'Um trecho lista os quatro Trechos')
    check(page.locator('[data-share-single-segment-index] .activity-share-route-picker-visual svg').count()==4,'Um trecho usa mini geometrias quando disponíveis')
    check(not page.locator('[data-format-label="portrait"]').is_hidden() and not page.locator('[data-format-label="square"]').is_hidden(),'Um trecho reutiliza formatos dos Cards de atividade')
    check(page.locator('[data-share-single-segment-picker]').is_visible(),'Um trecho usa o próprio seletor visual sem controle Rota selecionada/Trocar duplicado')
    page.locator('[data-share-single-segment-index]').nth(1).click(); page.wait_for_timeout(40)
    check(page.locator('[data-share-single-segment-index]').nth(1).get_attribute('aria-pressed')=='true','troca individual atualiza diretamente o seletor visual')
    check(page.locator('[data-share-segment-check]:checked').count()==4,'escolher Um trecho não destrói o subset de Vários trechos')

    page.click('[data-share-scope="multiple_segments"]'); page.wait_for_timeout(50)
    check(page.locator('[data-share-single-segment-picker]').is_hidden(),'seletor individual não vaza para Vários trechos')
    check(page.locator('[data-share-segments-group]').is_visible(),'Vários trechos mostra seleção contextual')
    checks=page.locator('[data-share-segment-check]')
    check(checks.count()==4 and page.locator('[data-share-segment-check]:checked').count()==4,'Vários trechos começa com subset completo selecionado')
    check(page.locator('[data-share-multiple-summary]').is_visible(),'Vários trechos mostra resumo compacto antes da lista')
    page.click('[data-share-edit-segments]'); page.wait_for_timeout(30)
    check(page.locator('[data-share-segment-disclosure]').get_attribute('open') is not None,'Alterar abre o disclosure de seleção')
    page.locator('[data-share-segment-check]').nth(1).uncheck(); page.wait_for_timeout(30)
    check(page.locator('[data-share-segment-check]:checked').count()==3,'subset de vários trechos é independente')
    check('3 de 4' in page.locator('[data-share-segment-selection-summary]').inner_text(),'Vários trechos mostra resumo compacto da seleção')
    page.locator('[data-share-segment-check]').nth(2).uncheck(); page.wait_for_timeout(30)
    check(page.locator('[data-share-segment-check]:checked').count()==2,'Vários trechos aceita exatamente dois selecionados')
    page.locator('[data-share-segment-check]').nth(3).click(); page.wait_for_timeout(30)
    check(page.locator('[data-share-segment-check]:checked').count()==2 and page.locator('[data-share-segment-check]').nth(3).is_checked(),'Vários trechos impede descer abaixo de dois')
    check('2 de 4' in page.locator('[data-share-segment-selection-summary]').inner_text(),'resumo permanece coerente no mínimo de dois')
    check(page.evaluate('StrideBRShareTest.compatibility("session_comparison",{scope:"multiple_segments",format:"story",segmentCount:1,geometryCount:1,comparableSegmentCount:1})') is False,'Comparação some com apenas um valor comparável')
    check(page.evaluate('StrideBRShareTest.compatibility("session_comparison",{scope:"multiple_segments",format:"story",segmentCount:2,geometryCount:2,comparableSegmentCount:2})') is True,'Comparação aparece com dois valores comparáveis')
    page.click('[data-share-scope="session"]'); page.wait_for_timeout(30)
    check(page.locator('[data-share-single-segment-picker]').is_hidden() and page.locator('[data-share-multiple-summary]').is_hidden() and page.locator('[data-share-segments-group]').is_hidden(),'Sessão não mostra seleção individual nem subset visual')
    check(page.locator('[data-share-segment-check]:checked').count()==2,'Sessão usa todos semanticamente sem sobrescrever subset salvo de Vários trechos')
    session_indexes=page.evaluate('StrideBRShareTest.selectedIndexesForScope("session")')
    check(session_indexes==[0,1,2,3],'Sessão representa automaticamente todos os Trechos')
    page.click('[data-share-scope="single_segment"]'); page.wait_for_timeout(30)
    check(page.locator('[data-share-single-segment-picker]').is_visible() and page.locator('[data-share-multiple-summary]').is_hidden() and page.locator('[data-share-segments-group]').is_hidden(),'Sessão → Um trecho não deixa subset visual vazar')
    page.locator('[data-share-single-segment-index]').nth(1).click(); page.wait_for_timeout(30)
    check(page.locator('[data-share-single-segment-index]').nth(1).get_attribute('aria-pressed')=='true','fluxo final seleciona Trecho 2 em Um trecho')
    page.click('[data-share-scope="multiple_segments"]'); page.wait_for_timeout(30)
    check(page.locator('[data-share-segment-check]:checked').count()==2,'Um trecho → Vários preserva o subset de dois')
    check(page.locator('[data-share-single-segment-picker]').is_hidden() and page.locator('[data-share-multiple-summary]').is_visible(),'Vários não deixa seletor de rota individual vazar')
    page.click('[data-share-scope="session"]'); page.wait_for_timeout(30)
    check(page.evaluate('StrideBRShareTest.selectedIndexesForScope("session")')==[0,1,2,3],'Vários → Sessão volta a todos os Trechos reais')
    page.click('[data-share-scope="single_segment"]'); page.wait_for_timeout(30)
    check(page.locator('[data-share-single-segment-picker]').is_visible() and page.locator('[data-share-multiple-summary]').is_hidden(),'Sessão → Um trecho final volta ao único seletor visual')
    page.click('[data-share-scope="multiple_segments"]'); page.wait_for_timeout(30)
    page.click('[data-share-select-all]'); page.wait_for_timeout(30)
    check(page.locator('[data-share-segment-check]:checked').count()==4,'Selecionar todos restaura o subset inteiro')
    check(page.locator('[data-format-label="portrait"]').is_hidden() and page.locator('[data-format-label="square"]').is_hidden(),'Vários trechos continua Story-only nesta versão')
    check(not errors,f'sessão sem erros JS {errors}')
    page.close(); browser.close(); print(f'✓ share final scope flow: {count[0]} assertions')
