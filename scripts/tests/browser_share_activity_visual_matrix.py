#!/usr/bin/env python3
from pathlib import Path
import subprocess, time, sys
try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr); sys.exit(2)
ROOT=Path(__file__).resolve().parents[2]
PUBLIC=ROOT/'public'
server=subprocess.Popen(['python3','-m','http.server','8765','--bind','127.0.0.1'],cwd=PUBLIC,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
time.sleep(.35)
TR={'activity.share.format_story':'Story','activity.share.format_portrait':'Retrato','activity.share.format_square':'Quadrado','activity.share.composition_standard':'Padrão','activity.share.composition_compact':'Compacto','activity.share.distance':'Distância','activity.share.duration':'Duração','activity.share.pace':'Ritmo','activity.share.elevation':'Elevação','nav.physical_activity':'Atividade física'}
DATA={'id':'a','titulo':'Corrida à noite','modalidade':'Corrida','modalidade_slug':'corrida','metricas':[{'key':'distance','rotulo':'Distância','valor':'4,37 km'},{'key':'duration','rotulo':'Duração','valor':'00:32:04'},{'key':'pace','rotulo':'Ritmo','valor':'7:20/km'},{'key':'elevation','rotulo':'Elevação','valor':'26,2 m'}],'geojson':{'type':'LineString','coordinates':[[-53.405,-27.365],[-53.400,-27.360],[-53.394,-27.364],[-53.389,-27.359]]}}
try:
  with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    page=browser.new_page(viewport={'width':1400,'height':1000}); errors=[]; page.on('pageerror',lambda e:errors.append(str(e)))
    page.set_content('<!doctype html><html><head><base href="http://127.0.0.1:8765/"></head><body><div data-share-modal></div><canvas id="c"></canvas></body></html>')
    page.evaluate('(tr)=>{window.__STRIDEBR_SHARE_TEST_HOOKS__=true;window.StrideBRI18n={locale:"pt-BR",t:(k,v={},f=null)=>tr[k]||f||k,number:(v,d=0)=>Number(v).toLocaleString("pt-BR",{minimumFractionDigits:d,maximumFractionDigits:d}),sport:(_s,f)=>f};window.StrideBRBasemaps={share:{available:()=>false}}}',TR)
    page.add_script_tag(url='http://127.0.0.1:8765/assets/js/atividades.js')
    page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))"); page.wait_for_timeout(150)
    page.evaluate('d=>StrideBRShareTest.setData(d)',DATA)
    formats={'story':(1080,1920),'portrait':(1080,1350),'square':(1080,1080)}
    count=0
    for fmt,(w,h) in formats.items():
      for composition in ['standard','compact']:
        for content in ['route','sport','none']:
          result=page.evaluate('''async ([d,f,w,h,composition,content])=>{
            const c=document.querySelector('#c');
            return await StrideBRShareTest.renderCard(c,{data:d,scope:'activity',format:f,width:w,height:h,composition,content,showTitle:composition==='standard',showRoute:content==='route',showMapBase:false,selectedMetrics:d.metricas,renderPurpose:'export'})
          }''',[DATA,fmt,w,h,composition,content])
          assert result['configuration']['format']==fmt
          assert result['configuration']['composition']==composition
          assert result['configuration']['content']==content
          count+=1
    assert not errors, errors
    browser.close()
  print(f'✓ share activity visual matrix: {count} rendered cards; Story/Retrato/Quadrado × Padrão/Compacto × Rota/Modalidade/Sem elemento')
finally:
  server.terminate(); server.wait(timeout=2)
