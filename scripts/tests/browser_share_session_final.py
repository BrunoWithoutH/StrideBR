#!/usr/bin/env python3
from pathlib import Path
import sys
try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
HTML = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"></head><body><div data-share-modal></div></body></html>'
TR = {
    'activity.share.format_story':'Story',
    'activity.share.format_portrait':'Retrato',
    'activity.share.format_square':'Quadrado',
    'activity.share.composition_standard':'Padrão',
    'activity.share.composition_compact':'Compacto',
    'activity.share.session_overview':'Visão geral',
    'activity.share.session_by_segment':'Por trecho',
    'activity.share.comparison':'Comparação',
    'activity.share.highlight':'Destaque',
    'activity.share.sequence':'Sequência',
    'activity.share.session_summary':'Resumo',
    'activity.share.list':'Lista',
    'activity.share.minimal':'Minimal',
    'activity.share.compare_time':'Tempo',
    'activity.share.compare_pace':'Ritmo',
    'activity.share.compare_speed':'Velocidade',
    'activity.share.compare_power':'Potência',
    'activity.share.compare_heart':'FC média',
    'activity.share.reference_best':'Melhor',
    'activity.share.reference_average':'Média',
    'activity.share.reference_target':'Alvo',
    'activity.share.best_time':'MELHOR TEMPO',
    'activity.share.best_pace':'MELHOR RITMO',
    'activity.share.fastest':'MAIS RÁPIDO',
    'activity.share.highest_power':'MAIOR POTÊNCIA',
    'activity.share.highlight':'DESTAQUE',
    'activity.share.segment':'Trecho',
    'activity.share.segments':'Trechos',
    'activity.share.total_distance':'Distância total',
    'activity.share.logged_distance':'Distância registrada',
    'activity.share.total_time':'Tempo total',
    'activity.share.logged_time':'Tempo registrado',
    'activity.share.average_pace':'Ritmo médio',
    'activity.share.average_speed':'Velocidade média',
    'activity.share.average_split':'Parcial média',
    'activity.share.total_elevation':'Elevação',
    'activity.share.logged_elevation':'Elevação registrada',
    'activity.share.max_segment_gain':'Maior ganho em trecho',
    'activity.share.average_gain':'Ganho médio',
    'activity.share.max_altitude':'Maior altitude',
    'activity.share.duration':'Duração',
    'activity.share.pace':'Ritmo',
    'activity.share.distance':'Distância',
    'activity.share.cadence':'Cadência',
    'activity.share.show_route':'Mostrar rota',
    'activity.share.primary_metric':'Métrica principal',
    'activity.share.secondary_metric':'Métrica secundária',
    'activity.share.segment_count':'{count} trechos',
    'activity.statistics':'Estatísticas',
    'common.none':'Nenhuma',
    'common.activity':'Atividade',
    'nav.physical_activity':'Atividade física',
}

def make_route(i):
    x = -53.4 + i * .005
    y = -27.36 + i * .003
    return {'type':'LineString','coordinates':[[x,y],[x+.001,y+.001],[x+.002,y-.0005]]}

def segment(i, distance, duration, route=True, name=None):
    data = {
        'id': f'u{i+1}',
        'titulo': name or f'Trecho {i+1}',
        'rotulo': name or f'Trecho {i+1}',
        'modalidade': 'Corrida',
        'modalidade_slug': 'corrida',
        'distancia_metros': distance,
        'duracao_segundos': duration,
        'metricas': [
            {'key':'distance','rotulo':'Distância','valor':f'{distance/1000:.2f} km'},
            {'key':'duration','rotulo':'Duração','valor':str(duration)},
        ],
    }
    if route:
        data['geojson'] = make_route(i)
    return data

def activity(segments):
    return {'id':'a','titulo':'Treino intervalado','modalidade':'Corrida','modalidade_slug':'corrida','usa_trechos':True,'trechos':segments,'metricas':[]}

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    page = browser.new_page(viewport={'width':1280,'height':900})
    errors = []
    page.on('pageerror', lambda e: errors.append(str(e)))
    page.set_content(HTML, wait_until='domcontentloaded')
    page.evaluate('(tr)=>{window.__STRIDEBR_SHARE_TEST_HOOKS__=true;window.StrideBRI18n={locale:"pt-BR",t:(k,v={},f=null)=>{let s=tr[k]||f||k;Object.entries(v).forEach(([a,b])=>s=String(s).replaceAll(`{${a}}`,String(b)));return s},number:(v,d=0,trim=false)=>{let s=Number(v).toLocaleString("pt-BR",{minimumFractionDigits:d,maximumFractionDigits:d});if(trim&&d)s=s.replace(/,0+$/," ").trim().replace(/(,\\d*?)0+$/,"$1");return s},sport:(_s,f)=>f};window.StrideBRBasemaps={share:{available:()=>false}}}', TR)
    page.add_script_tag(path=str(ROOT/'public/assets/js/atividades.js'))
    page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
    page.wait_for_timeout(80)

    checks = [0]
    def check(value, message):
        checks[0] += 1
        if not value:
            raise AssertionError(message)

    check(page.evaluate('Boolean(window.StrideBRShareTest)'), 'test hook disponível')
    comps = page.evaluate('Object.fromEntries(Object.entries(StrideBRShareTest.compositions).map(([k,v])=>[k,{formats:[...v.supportedFormats],minGeo:v.minGeometryCount||0,map:v.supportsMap,title:v.supportsTitle,max:v.maxVisibleSegments,routeToggle:Boolean(v.supportsRouteToggle)}]))')
    session_ids = ['session_overview','session_by_segment','session_comparison','session_highlight','session_sequence','session_summary','session_list','session_minimal','session_compact']
    check(all(key in comps for key in session_ids), 'nove composições públicas de sessão registradas')
    check(all(comps[key]['formats'] == ['story'] for key in session_ids), 'sessão é story only')
    check(comps['session_comparison']['minGeo'] == 0 and comps['session_by_segment']['minGeo'] == 1 and comps['session_overview']['minGeo'] == 1, 'contratos de geometria finais corretos')
    check(comps['session_highlight']['routeToggle'] and comps['session_sequence']['routeToggle'], 'toggle contextual de rota existe')
    check(comps['session_compact']['title'] is False and comps['session_compact']['map'] is False and comps['session_compact']['max'] == 5, 'compacto final sem título e sem mapa')
    check(page.evaluate('StrideBRShareTest.compatibility("session_comparison",{scope:"session",format:"story",segmentCount:3,geometryCount:0,comparableSegmentCount:3})') is True, 'comparação funciona sem geometria')
    check(page.evaluate('StrideBRShareTest.compatibility("session_overview",{scope:"session",format:"story",segmentCount:5,geometryCount:0,comparableSegmentCount:5})') is False, 'visão geral some sem geometria')
    check(page.evaluate('StrideBRShareTest.compatibility("session_summary",{scope:"session",format:"story",segmentCount:5,geometryCount:0,comparableSegmentCount:5})') is True, 'resumo funciona sem geometria')
    check(page.evaluate('StrideBRShareTest.compositions.compact.layoutByFormat.square') == 'grid', 'compacto de atividade preservado')
    colors = page.evaluate('[...StrideBRShareTest.blueSeries]')
    check(len(colors) >= 4, 'família azul preservada')

    equal = activity([segment(i, 400, t, True, f'Tiro {i+1}') for i, t in enumerate([78.438, 77.921, 79.004, 79.504])])
    page.evaluate('d=>StrideBRShareTest.setData(d)', equal)
    check(page.evaluate('d=>StrideBRShareTest.defaultComparisonMetric(d.trechos)', equal) == 'time', '4x400 usa tempo')
    rows = page.evaluate('d=>StrideBRShareTest.comparisonRows(d.trechos,"time","best")', equal)
    check(abs(rows['best'] - 77.921) < 1e-9, 'melhor tempo exato')
    check(page.evaluate('StrideBRShareTest.formatDuration(78.438)') == '1:18,438', 'milissegundos preservados com locale pt-BR')
    check(page.evaluate('StrideBRShareTest.formatDuration(517.5)') == '8:37,5', 'zeros finais de duração são removidos')
    check(page.evaluate('StrideBRShareTest.formatDuration(300)') == '5:00', 'duração inteira não inventa casas decimais')
    check(page.evaluate('StrideBRShareTest.formatDuration(79.004)') == '1:19,004', 'subsegundo real mantém até três casas')
    check(page.evaluate('StrideBRShareTest.formatComparisonValue("pace",250.833)') == '4:11/km', 'ritmo arredonda para segundo inteiro')
    check(page.evaluate('StrideBRShareTest.formatComparisonValue("pace",300.833)') == '5:01/km', 'ritmo arredonda sem truncar')
    check(page.evaluate('StrideBRShareTest.formatDuration(Number.NaN)') == '', 'duração inválida não gera NaN')
    delta = page.evaluate('StrideBRShareTest.formatComparisonDelta("time",78.438-77.921)')
    check(delta.startswith('+') and '0,517' in delta, 'delta exato')
    hi = page.evaluate('d=>StrideBRShareTest.highlight(d.trechos)', equal)
    check(hi['headline'] == 'MELHOR TEMPO', 'headline do destaque combina com tempo')

    varied = activity([segment(0,800,220,True,'800 m'),segment(1,1000,260,True,'1 km'),segment(2,1200,300,True,'1,2 km'),segment(3,1400,350,True,'1,4 km')])
    page.evaluate('d=>StrideBRShareTest.setData(d)', varied)
    check(page.evaluate('d=>StrideBRShareTest.defaultComparisonMetric(d.trechos)', varied) == 'pace', 'distâncias diferentes usam ritmo')
    hi = page.evaluate('d=>StrideBRShareTest.highlight(d.trechos)', varied)
    check(hi['metricId'] == 'pace', 'destaque usa métrica correta em distâncias diferentes')

    some = activity([segment(i, 1000 + i*50, 250 + i*5, i % 2 == 0) for i in range(5)])
    page.evaluate('d=>StrideBRShareTest.setData(d)', some)
    state = page.evaluate('d=>StrideBRShareTest.renderState(d,"session")', some)
    check(len(state['selectedSegmentIds']) == 5, 'subset de sessão preserva seleção')
    check(len([r for r in state['routes'] if str(r['id']).startswith('segment:')]) == 3, 'somente geometrias existentes entram no state')

    none = activity([segment(i, 1000, 250 + i*5, False) for i in range(5)])
    page.evaluate('d=>StrideBRShareTest.setData(d)', none)
    state = page.evaluate('d=>StrideBRShareTest.renderState(d,"session")', none)
    check(len(state['selectedSegmentIds']) == 5 and not state['routes'], 'sessão sem rotas continua compartilhável por dados')

    preview = page.evaluate('d=>StrideBRShareTest.configuration({data:d,scope:"activity",format:"story",composition:"standard",renderPurpose:"preview",mapRasterScale:.55})', {'id':'s','titulo':'Corrida','modalidade':'Corrida','modalidade_slug':'corrida','metricas':[{'key':'distance','rotulo':'Distância','valor':'4,37 km'}],'geojson':make_route(0)})
    export = page.evaluate('d=>StrideBRShareTest.configuration({data:d,scope:"activity",format:"story",composition:"standard",renderPurpose:"export",mapRasterScale:1})', {'id':'s','titulo':'Corrida','modalidade':'Corrida','modalidade_slug':'corrida','metricas':[{'key':'distance','rotulo':'Distância','valor':'4,37 km'}],'geojson':make_route(0)})
    check(preview['layoutModel'] == export['layoutModel'] and preview['routeCoordinates'] == export['routeCoordinates'], 'preview e export compartilham layout')
    check(not errors, f'sem erros JS: {errors}')

    page_en = browser.new_page(viewport={'width':1280,'height':900})
    errors_en = []
    page_en.on('pageerror', lambda e: errors_en.append(str(e)))
    page_en.set_content(HTML, wait_until='domcontentloaded')
    page_en.evaluate('(tr)=>{window.__STRIDEBR_SHARE_TEST_HOOKS__=true;window.StrideBRI18n={locale:"en",t:(k,v={},f=null)=>{let s=tr[k]||f||k;Object.entries(v).forEach(([a,b])=>s=String(s).replaceAll(`{${a}}`,String(b)));return s},number:(v,d=0)=>Number(v).toLocaleString("en-US",{minimumFractionDigits:d,maximumFractionDigits:d}),sport:(_s,f)=>f};window.StrideBRBasemaps={share:{available:()=>false}}}', TR)
    page_en.add_script_tag(path=str(ROOT/'public/assets/js/atividades.js'))
    page_en.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
    page_en.wait_for_timeout(80)
    check(page_en.evaluate('StrideBRShareTest.formatDuration(78.438)') == '1:18.438', 'locale en usa ponto em milissegundos')
    check(page_en.evaluate('StrideBRShareTest.formatDuration(517.5)') == '8:37.5', 'locale en remove zeros finais')
    check(page_en.evaluate('StrideBRShareTest.formatComparisonDelta("time",0.517)') == '+0.517 s', 'delta en usa ponto e espaço antes de s')
    check(page_en.evaluate('StrideBRShareTest.formatComparisonDelta("time",1.083)') == '+1.083 s', 'delta en preserva três casas reais')
    check(page_en.evaluate('StrideBRShareTest.formatComparisonValue("pace",250.833)') == '4:11/km', 'pace en continua no segundo inteiro')
    check(not errors_en, f'sem erros JS em en: {errors_en}')
    page_en.close()
    browser.close()
    print(f'✓ share session final architecture: {checks[0]} assertions')
