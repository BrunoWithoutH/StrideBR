#!/usr/bin/env python3
from pathlib import Path
import sys
try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr); sys.exit(2)
ROOT=Path(__file__).resolve().parents[2]
HTML='<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"></head><body><div data-share-modal></div></body></html>'
TR={
 'activity.share.format_story':'Story','activity.share.format_portrait':'Retrato','activity.share.format_square':'Quadrado',
 'activity.share.composition_standard':'Padrão','activity.share.composition_compact':'Compacto',
 'activity.share.route':'Rota','activity.share.route_hint':'Percurso','activity.share.sport':'Modalidade','activity.share.sport_hint':'Esporte','activity.share.no_element':'Nenhum','activity.share.no_element_hint':'Sem elemento',
 'activity.share.background_color_mode':'Cor','activity.share.background_color_hint':'Fundo simples','activity.share.map':'Mapa','activity.share.map_hint':'Mapa','activity.share.photo':'Foto','activity.share.photo_hint':'Foto','activity.share.transparent':'Transparente','activity.share.transparent_hint':'PNG',
 'activity.share.distance':'Distância','activity.share.duration':'Duração','activity.share.pace':'Ritmo','activity.share.elevation':'Elevação','common.activity':'Atividade','nav.physical_activity':'Atividade física'
}
DATA={'id':'a','titulo':'Corrida à noite','modalidade':'Corrida','modalidade_slug':'corrida','metricas':[{'key':'distance','rotulo':'Distância','valor':'4,37 km'},{'key':'duration','rotulo':'Duração','valor':'32:04'},{'key':'pace','rotulo':'Ritmo','valor':'7:20/km'},{'key':'elevation','rotulo':'Elevação','valor':'26,2 m'}],'geojson':{'type':'LineString','coordinates':[[-53.4,-27.36],[-53.399,-27.359],[-53.397,-27.361]]}}
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    page=browser.new_page(viewport={'width':1280,'height':900}); errors=[]; page.on('pageerror',lambda e:errors.append(str(e)))
    page.set_content(HTML,wait_until='domcontentloaded')
    page.evaluate('(tr)=>{window.__STRIDEBR_SHARE_TEST_HOOKS__=true;window.StrideBRI18n={locale:"pt-BR",t:(k,v={},f=null)=>tr[k]||f||k,number:(v,d=0)=>Number(v).toLocaleString("pt-BR",{minimumFractionDigits:d,maximumFractionDigits:d}),sport:(_s,f)=>f};window.StrideBRBasemaps={share:{available:()=>false}}}',TR)
    page.add_script_tag(path=str(ROOT/'public/assets/js/atividades.js')); page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))"); page.wait_for_timeout(30)
    page.evaluate('d=>StrideBRShareTest.setData(d)',DATA)
    checks=0
    def check(value,msg):
        nonlocal_box[0]+=1
        if not value: raise AssertionError(msg)
    nonlocal_box=[0]
    formats=['story','portrait','square']; contents=['route','sport','none']
    anchors={}
    for fmt in formats:
        anchors[fmt]=page.evaluate('(f)=>StrideBRShareTest.formats[f].anchors',fmt)
        for content in contents:
            for title in [True,False]:
                cfg=page.evaluate('([d,f,c,t])=>StrideBRShareTest.configuration({data:d,scope:"activity",format:f,composition:"standard",content:c,showTitle:t,showRoute:c==="route",showMapBase:false,renderPurpose:"preview"})',[DATA,fmt,content,title])
                check(cfg['format']==fmt and cfg['composition']=='standard' and cfg['content']==content,f'{fmt}/{content} mantém Padrão')
                check(cfg['showTitle'] is title,f'{fmt}/{content} respeita título on/off')
                check(cfg['layoutModel']['anchors']==anchors[fmt],f'{fmt} mantém anchors ao trocar estado')
    for fmt in formats:
        for content in contents:
            cfg=page.evaluate('([d,f,c])=>StrideBRShareTest.configuration({data:d,scope:"activity",format:f,composition:"compact",content:c,showTitle:false,showMapBase:false,renderPurpose:"preview"})',[DATA,fmt,content])
            check(cfg['composition']=='compact' and cfg['showTitle'] is False,f'Compacto {fmt}/{content} sem título')
            check(cfg['compositionDefinition']['supportsMap'] is False,f'Compacto {fmt}/{content} não suporta mapa')
    check(page.evaluate('StrideBRShareTest.compositions.compact.layoutByFormat.story')=='vertical','Compacto Story vertical')
    check(page.evaluate('StrideBRShareTest.compositions.compact.layoutByFormat.portrait')=='vertical','Compacto Retrato vertical')
    check(page.evaluate('StrideBRShareTest.compositions.compact.layoutByFormat.square')=='grid','Compacto Quadrado em grade')
    check(not errors,f'sem erros JS: {errors}')
    browser.close()
    print(f'✓ share activity final architecture: {nonlocal_box[0]} assertions; 18 estados Padrão + 9 Compacto')
