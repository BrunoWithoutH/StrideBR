#!/usr/bin/env python3
from pathlib import Path
import sys
try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr); sys.exit(2)
ROOT=Path(__file__).resolve().parents[2]
HTML='''<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"></head><body><div data-share-modal></div></body></html>'''
TR={
 'activity.share.format_story':'Story','activity.share.format_portrait':'Retrato','activity.share.format_square':'Quadrado','activity.share.composition_standard':'Padrão','activity.share.composition_compact':'Compacto','activity.share.session_overview':'Visão geral','activity.share.session_by_segment':'Por trecho','activity.share.comparison':'Comparação','activity.share.highlight':'Destaque','activity.share.sequence':'Sequência','activity.share.session_summary':'Resumo','activity.share.list':'Lista','activity.share.minimal':'Minimal','activity.share.compare_time':'Tempo','activity.share.compare_pace':'Ritmo','activity.share.compare_speed':'Velocidade','activity.share.compare_power':'Potência','activity.share.compare_heart':'FC média','activity.share.reference_best':'Melhor','activity.share.reference_average':'Média','activity.share.reference_target':'Alvo','activity.share.best_time':'MELHOR TEMPO','activity.share.fastest':'MAIS RÁPIDO','activity.share.highest_power':'MAIOR POTÊNCIA','activity.share.highlight':'DESTAQUE','activity.share.segment':'Trecho','activity.share.segments':'Trechos','activity.share.total_distance':'Distância total','activity.share.logged_distance':'Distância registrada','activity.share.total_time':'Tempo total','activity.share.logged_time':'Tempo registrado','activity.share.average_pace':'Ritmo médio','activity.share.average_speed':'Velocidade média','activity.share.average_split':'Parcial média','activity.share.total_elevation':'Elevação','activity.share.logged_elevation':'Elevação registrada','activity.share.max_segment_gain':'Maior ganho em trecho','activity.share.average_gain':'Ganho médio','activity.share.max_altitude':'Maior altitude','activity.share.best_segment_pace':'Melhor ritmo de trecho','activity.share.best_segment_speed':'Melhor velocidade de trecho','activity.share.duration':'Duração','activity.share.pace':'Ritmo','common.activity':'Atividade','nav.physical_activity':'Atividade física'
}

def make_route(i):
    x=-53.4+i*.005; y=-27.36+i*.003
    return {'type':'LineString','coordinates':[[x,y],[x+.001,y+.001],[x+.002,y-.0005]]}

def segment(i,distance,duration,route=True,name=None):
    return {'id':f'u{i+1}','titulo':name or f'Trecho {i+1}','rotulo':name or f'Trecho {i+1}','modalidade':'Corrida','modalidade_slug':'corrida','distancia_metros':distance,'duracao_segundos':duration,'metricas':[{'key':'distance','rotulo':'Distância','valor':f'{distance/1000:.2f} km'},{'key':'duration','rotulo':'Duração','valor':str(duration)}],**({'geojson':make_route(i)} if route else {})}

def activity(segments):
    return {'id':'a','titulo':'Treino intervalado','modalidade':'Corrida','modalidade_slug':'corrida','usa_trechos':True,'trechos':segments,'metricas':[]}

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    page=browser.new_page(viewport={'width':1280,'height':900}); errors=[]; page.on('pageerror',lambda e:errors.append(str(e)))
    page.set_content(HTML,wait_until='domcontentloaded')
    page.evaluate('(tr)=>{window.__STRIDEBR_SHARE_TEST_HOOKS__=true;window.StrideBRI18n={locale:"pt-BR",t:(k,v={},f=null)=>{let s=tr[k]||f||k;Object.entries(v).forEach(([a,b])=>s=String(s).replaceAll(`{${a}}`,String(b)));return s},number:(v,d=0,trim=false)=>{let s=Number(v).toLocaleString("pt-BR",{minimumFractionDigits:d,maximumFractionDigits:d});if(trim&&d)s=s.replace(/,0+$/," ").trim().replace(/(,\\d*?)0+$/,"$1");return s},sport:(_s,f)=>f};window.StrideBRBasemaps={share:{available:()=>false}}}',TR)
    page.add_script_tag(path=str(ROOT/'public/assets/js/atividades.js')); page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))"); page.wait_for_timeout(40)
    count=0
    def check(v,m):
        nonlocal_count[0]+=1
        if not v: raise AssertionError(m)
    nonlocal_count=[0]

    check(page.evaluate('Boolean(window.StrideBRShareTest)'), 'test hook final disponível')
    comps=page.evaluate('Object.fromEntries(Object.entries(StrideBRShareTest.compositions).map(([k,v])=>[k,{family:v.family,formats:[...v.supportedFormats],scopes:[...v.supportedScopes],min:v.minSegments,max:v.maxVisibleSegments,geo:v.requiresGeometry,map:v.supportsMap,title:v.supportsTitle,metric:v.metricBehavior}]))')
    check(set(['standard','compact']).issubset(comps),'composições de atividade preservadas')
    session_ids=['session_overview','session_by_segment','session_comparison','session_highlight_route','session_sequence_route','session_summary','session_list','session_sequence','session_highlight','session_minimal','session_compact']
    check(all(i in comps for i in session_ids),'onze composições de sessão registradas')
    check(all(comps[i]['formats']==['story'] for i in session_ids),'sessão é Story-only sem codificar formatos futuros nos dados')
    check(comps['compact']['formats']==['story','portrait','square'] and comps['compact']['title'] is False and comps['compact']['map'] is False,'Compacto de atividade é composição, sem título/mapa')
    check(comps['session_compact']['title'] is False and comps['session_compact']['map'] is False and comps['session_compact']['max']==5,'Compacto de sessão tem contrato próprio')
    check(page.evaluate('StrideBRShareTest.compositions.compact.layoutByFormat.square')=='grid','Compacto quadrado deriva grid do formato 1:1')
    anchors=page.evaluate('Object.fromEntries(Object.entries(StrideBRShareTest.formats).map(([k,v])=>[k,v.anchors]))')
    check(all('titleAnchor' in anchors[f] and 'contentStage' in anchors[f] and 'logoAnchor' in anchors[f] for f in ['story','portrait','square']),'cada formato possui anchors fixos')
    check(page.evaluate('StrideBRShareTest.compatibility("session_overview",{scope:"session",format:"story",segmentCount:5,geometryCount:5})') is True,'Visão geral compatível com sessão geográfica')
    check(page.evaluate('StrideBRShareTest.compatibility("session_overview",{scope:"session",format:"portrait",segmentCount:5,geometryCount:5})') is False,'Retrato não aparece para sessão')
    check(page.evaluate('StrideBRShareTest.compatibility("session_overview",{scope:"session",format:"story",segmentCount:5,geometryCount:0})') is False,'Visão geral some sem geometria')
    check(page.evaluate('StrideBRShareTest.compatibility("session_summary",{scope:"session",format:"story",segmentCount:5,geometryCount:0})') is True,'Resumo funciona sem geometria')
    colors=page.evaluate('[...StrideBRShareTest.blueSeries]')
    check(len(colors)>=4 and all(int(c[5:7],16)>int(c[3:5],16)>int(c[1:3],16) for c in colors),'série multi-percurso permanece em família azul')

    for n in [2,3,4,5,8,15]:
        data=activity([segment(i,1000+i*20,260+i*5,True) for i in range(n)])
        page.evaluate('d=>StrideBRShareTest.setData(d)',data)
        state=page.evaluate('d=>StrideBRShareTest.renderState(d,"session")',data)
        check(len(state['selectedSegmentIds'])==n,f'{n} trechos continuam selecionados no state')
        check(len([r for r in state['routes'] if str(r['id']).startswith('segment:')])==n,f'{n} rotas preservadas quando todas existem')

    data_some=activity([segment(i,1000,260+i*5,i%2==0) for i in range(5)])
    page.evaluate('d=>StrideBRShareTest.setData(d)',data_some)
    state=page.evaluate('d=>StrideBRShareTest.renderState(d,"session")',data_some)
    check(len(state['selectedSegmentIds'])==5 and len([r for r in state['routes'] if str(r['id']).startswith('segment:')])==3,'sessão com algumas rotas mantém todos os Trechos e só as geometrias existentes')
    data_none=activity([segment(i,1000,260+i*5,False) for i in range(5)])
    page.evaluate('d=>StrideBRShareTest.setData(d)',data_none)
    state=page.evaluate('d=>StrideBRShareTest.renderState(d,"session")',data_none)
    check(len(state['selectedSegmentIds'])==5 and not state['routes'],'sessão sem rotas continua compartilhável por dados')

    shots=[78.438,77.921,79.004,78.600,80.125,78.250]
    equal=activity([segment(i,400,t,True,f'Tiro {i+1}') for i,t in enumerate(shots)])
    page.evaluate('d=>StrideBRShareTest.setData(d)',equal)
    caps=page.evaluate('d=>StrideBRShareTest.comparisonCapabilities(d.trechos)',equal)
    check(caps['equalDistance'] is True and any(x['id']=='time' for x in caps['capabilities']),'6×400 m habilita comparação por Tempo')
    check(page.evaluate('d=>StrideBRShareTest.defaultComparisonMetric(d.trechos)',equal)=='time','Tempo é default para tiros iguais')
    rows=page.evaluate('d=>StrideBRShareTest.comparisonRows(d.trechos,"time","best")',equal)
    check(abs(rows['best']-77.921)<1e-9,'melhor tempo preserva precisão subsegundo')
    check(page.evaluate('StrideBRShareTest.formatDuration(78.438)')=='1:18.438','1:18.438 não é arredondado antes do card')
    check(page.evaluate('StrideBRShareTest.formatDuration(77.921)')=='1:17.921' and page.evaluate('StrideBRShareTest.formatDuration(79.004)')=='1:19.004','ms 921/004 preservados')
    delta=page.evaluate('StrideBRShareTest.formatComparisonDelta("time",78.438-77.921)')
    check('0,517' in delta and delta.startswith('+'),'delta em tiros iguais é contra melhor com milissegundos')
    hi=page.evaluate('d=>StrideBRShareTest.highlight(d.trechos)',equal)
    check(abs(hi['value']-77.921)<1e-9 and hi['headline']=='MELHOR TEMPO','Destaque de distâncias iguais usa menor tempo')

    varied=activity([segment(0,800,220,True,'800 m'),segment(1,1000,260,True,'1 km'),segment(2,1200,300,True,'1,2 km'),segment(3,1400,350,True,'1,4 km')])
    page.evaluate('d=>StrideBRShareTest.setData(d)',varied)
    caps=page.evaluate('d=>StrideBRShareTest.comparisonCapabilities(d.trechos)',varied)
    check(caps['equalDistance'] is False and all(x['id']!='time' for x in caps['capabilities']),'distâncias diferentes escondem Tempo direto')
    check(page.evaluate('d=>StrideBRShareTest.defaultComparisonMetric(d.trechos)',varied)=='pace','corrida com distâncias diferentes usa Ritmo')
    hi=page.evaluate('d=>StrideBRShareTest.highlight(d.trechos)',varied)
    check(hi['segment']['distancia_metros']!=800 and hi['metricId']=='pace','Destaque não escolhe menor tempo bruto em distâncias diferentes')

    twelve=activity([segment(i,400,75+i,True) for i in range(12)])
    page.evaluate('d=>StrideBRShareTest.setData(d)',twelve)
    max_overview=comps['session_overview']['max']; max_compare=comps['session_comparison']['max']; max_list=comps['session_list']['max']
    check(12-max_overview==7,'Visão geral com 12 trechos produz +7')
    check(12-max_compare==6 and 12-max_list==2,'limites visuais variam por composição sem descartar seleção')

    simple={'id':'s','titulo':'Corrida à noite','modalidade':'Corrida','modalidade_slug':'corrida','metricas':[{'key':'distance','rotulo':'Distância','valor':'4,37 km'}],'geojson':make_route(0)}
    page.evaluate('d=>StrideBRShareTest.setData(d)',simple)
    preview=page.evaluate('d=>StrideBRShareTest.configuration({data:d,scope:"activity",format:"story",composition:"standard",renderPurpose:"preview",mapRasterScale:.55})',simple)
    export=page.evaluate('d=>StrideBRShareTest.configuration({data:d,scope:"activity",format:"story",composition:"standard",renderPurpose:"export",mapRasterScale:1})',simple)
    check(preview['layoutModel']==export['layoutModel'] and preview['routeCoordinates']==export['routeCoordinates'],'Preview/Export compartilham layout model, anchors e geometria')
    check(preview['renderPurpose']=='preview' and export['renderPurpose']=='export' and preview['mapRasterScale']<export['mapRasterScale'],'export muda densidade, não composição')
    check(not errors,f'sem erros JS: {errors}')
    browser.close()
    print(f'✓ share session final architecture: {nonlocal_count[0]} assertions')
