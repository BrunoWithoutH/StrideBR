#!/usr/bin/env python3
from pathlib import Path
import sys
try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr); sys.exit(2)
ROOT=Path(__file__).resolve().parents[2]
HTML='''<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"></head><body class="gps-page"><main data-gps-recorder data-csrf-token="x">
<div data-gps-restore hidden><span data-gps-restore-summary></span><button data-gps-resume></button><button data-gps-discard-saved></button></div>
<section data-gps-setup><select data-gps-sport><option value="1" data-slug="corrida" data-metric="pace_km" selected>Corrida</option></select><input type="radio" name="gps_goal_type" value="none" checked><button data-gps-start>Iniciar</button></section>
<section class="gps-live" data-gps-live hidden>
<header><strong data-gps-live-sport></strong><span data-gps-network></span><span data-gps-quality></span><span data-gps-accuracy></span></header>
<div data-gps-live-warning></div><div data-gps-wakelock-fallback hidden role="status">Mantenha a tela ligada.</div>
<div class="gps-live-layout"><div class="gps-live-metrics"><article><strong data-gps-time>00:00:00</strong></article><article><strong data-gps-distance></strong></article><article><span data-gps-pace-label></span><strong data-gps-pace></strong></article><article><strong data-gps-elevation></strong></article><article><strong data-gps-accuracy-large></strong></article><article data-gps-goal-card hidden><strong data-gps-goal-progress></strong></article></div><div class="gps-map-shell"><div data-gps-map></div><div data-gps-map-fallback></div></div></div>
<div><strong data-gps-current-lap></strong><small data-gps-current-lap-metrics></small></div>
<div class="gps-live-controls" data-gps-live-controls><button data-gps-pause>Pausar</button><button data-gps-lap>Trecho</button><button data-gps-lock aria-pressed="false">Bloquear controles</button><button data-gps-finish>Finalizar</button><button data-gps-discard-current>Cancelar</button></div>
<div class="gps-controls-lock-state" data-gps-controls-lock-state hidden role="status"><strong>Controles bloqueados</strong><button data-gps-unlock><span>Segure para desbloquear</span></button></div>
</section>
<section data-gps-review hidden><input data-gps-review-title><input data-gps-review-distance><input data-gps-review-duration><input data-gps-review-elevation><span data-gps-review-quality></span><div data-gps-quality-summary></div><div data-gps-segments></div><button data-gps-discard-review></button></section>
</main></body></html>'''
INIT=r'''() => {
 window.__watchCallback=null; window.__wakeRequests=0; window.__wakeReleases=0; window.__wakeSentinel=null;
 Object.defineProperty(navigator,'geolocation',{configurable:true,value:{watchPosition(cb){window.__watchCallback=cb;return 7},clearWatch(){window.__watchCallback=null}}});
 Object.defineProperty(navigator,'permissions',{configurable:true,value:{query:async()=>({state:'granted'})}});
 Object.defineProperty(navigator,'storage',{configurable:true,value:{persist:async()=>true}});
 window.__installWakeLock=() => Object.defineProperty(navigator,'wakeLock',{configurable:true,value:{request:async type=>{window.__wakeRequests+=1;const listeners=[];const s={released:false,addEventListener(name,cb){if(name==='release')listeners.push(cb)},async release(){if(this.released)return;this.released=true;window.__wakeReleases+=1;listeners.forEach(cb=>cb())}};window.__wakeSentinel=s;return s}}});
 window.__removeWakeLock=() => { try{delete navigator.wakeLock}catch(_){} };
 window.__emitPosition=(lat,lon,t) => window.__watchCallback?.({coords:{latitude:lat,longitude:lon,accuracy:5,altitude:100,altitudeAccuracy:5},timestamp:t});
 window.StrideBRBasemaps={attach(){}};
 window.L={map(){return {setView(){return this},fitBounds(){return this},panTo(){return this},invalidateSize(){return this}}},polyline(){return {addTo(){return this},setLatLngs(){return this},getBounds(){return {}}}}};
 window.StrideBRI18n={locale:'pt-BR',t:(k,v={},f=null)=>{const d={'gps.pause':'Pausar','gps.continue':'Continuar','gps.online':'Online','gps.offline_local':'Offline','gps.activity':'Atividade','gps.sport_fallback':'Atividade','gps.pace':'Ritmo','gps.speed':'Velocidade','gps.waiting':'Aguardando','gps.quality_good':'Bom','gps.quality_fair':'Regular','gps.quality_poor':'Ruim','gps.quality_waiting':'Aguardando','gps.current_accuracy':'Precisão','gps.segment_number':'Trecho {number}','gps.quality_good_recording':'Boa','gps.avg_accuracy':'Média','gps.best_accuracy':'Melhor','gps.points_accepted':'Aceitos','gps.points_rejected':'Rejeitados','gps.visibility_gaps':'Lacunas','gps.source':'Fonte'};let s=d[k]??f??k;Object.entries(v).forEach(([a,b])=>s=String(s).replaceAll(`{${a}}`,String(b)));return s},number:(v,d=0)=>Number(v).toFixed(d),sport:(_s,f)=>f,duration:(s)=>String(s)};
 window.confirm=()=>true; window.alert=()=>{};
}'''

def boot(page, wake=True):
    page.set_content(HTML,wait_until='domcontentloaded')
    page.evaluate(INIT)
    page.evaluate("Object.defineProperty(window,'isSecureContext',{configurable:true,value:true});delete window.indexedDB")
    page.evaluate('__installWakeLock()' if wake else '__removeWakeLock()')
    page.add_style_tag(path=str(ROOT/'public/assets/css/gps-recorder.css'))
    page.add_script_tag(path=str(ROOT/'public/assets/js/gps-recorder.js'))
    page.wait_for_timeout(80)

def start_with_points(page):
    page.click('[data-gps-start]')
    page.wait_for_selector('[data-gps-live]:not([hidden])')
    now=page.evaluate('Date.now()')
    page.evaluate('([t])=>__emitPosition(-27.36,-53.39,t)',[now])
    page.evaluate('([t])=>__emitPosition(-27.36,-53.38994,t)',[now+1000])
    page.wait_for_timeout(80)

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,args=['--no-sandbox'])
    count=[0]
    def check(v,m):
        count[0]+=1
        if not v: raise AssertionError(m)

    page=browser.new_page(viewport={'width':390,'height':844});errors=[];page.on('pageerror',lambda e:errors.append(str(e)));boot(page,True)
    check(page.evaluate('window.isSecureContext') is True,'fixture simula contexto seguro para exercitar Wake Lock')
    check(page.locator('[data-gps-wakelock-fallback]').is_hidden(),'com API disponível helper de limitação fica oculto')
    start_with_points(page)
    check(page.evaluate('__wakeRequests')==1,'iniciar gravação ativa solicita Screen Wake Lock')
    page.click('[data-gps-pause]');page.wait_for_timeout(30)
    check(page.evaluate('__wakeReleases')==1,'pausar libera Wake Lock')
    page.click('[data-gps-pause]');page.wait_for_timeout(30)
    check(page.evaluate('__wakeRequests')==2,'continuar gravação solicita Wake Lock novamente')
    page.evaluate('__wakeSentinel.release()');page.wait_for_timeout(20)
    page.evaluate("Object.defineProperty(document,'visibilityState',{configurable:true,value:'hidden'});document.dispatchEvent(new Event('visibilitychange'))")
    page.evaluate("Object.defineProperty(document,'visibilityState',{configurable:true,value:'visible'});document.dispatchEvent(new Event('visibilitychange'))")
    page.wait_for_timeout(40)
    check(page.evaluate('__wakeRequests')==3,'retorno à página visível recupera Wake Lock durante recording')

    before=page.locator('[data-gps-time]').inner_text()
    page.click('[data-gps-lock]')
    check(page.locator('[data-gps-live]').evaluate("el=>el.classList.contains('is-controls-locked')"),'bloquear controles ativa estado sem trocar de tela')
    check(page.locator('[data-gps-live-controls]').is_hidden() and page.locator('[data-gps-controls-lock-state]').is_visible(),'ações normais somem e estado bloqueado fica visível')
    page.evaluate("document.querySelector('[data-gps-finish]').click()")
    check(page.locator('[data-gps-review]').is_hidden(),'finish acidental é bloqueado')
    page.wait_for_timeout(2100)
    after=page.locator('[data-gps-time]').inner_text()
    check(after!=before,'cronômetro continua atualizando com controles bloqueados')
    unlock=page.locator('[data-gps-unlock]')
    box=unlock.bounding_box();page.mouse.move(box['x']+box['width']/2,box['y']+box['height']/2);page.mouse.down();page.wait_for_timeout(220);page.mouse.up();page.wait_for_timeout(30)
    check(page.locator('[data-gps-live]').evaluate("el=>el.classList.contains('is-controls-locked')"),'toque/hold curto não desbloqueia')
    page.mouse.move(box['x']+box['width']/2,box['y']+box['height']/2);page.mouse.down();page.wait_for_timeout(1320);page.mouse.up();page.wait_for_timeout(40)
    check(not page.locator('[data-gps-live]').evaluate("el=>el.classList.contains('is-controls-locked')"),'hold completo desbloqueia')
    check(page.locator('[data-gps-live-controls]').is_visible(),'interface normal retorna após desbloquear')
    page.click('[data-gps-finish]');page.wait_for_selector('[data-gps-review]:not([hidden])')
    check(page.evaluate('__wakeReleases')>=3,'finalizar libera Wake Lock ativo')
    check(not errors,f'com Wake Lock não há erros JS {errors}')
    page.close()

    page=browser.new_page(viewport={'width':390,'height':844});errors2=[];page.on('pageerror',lambda e:errors2.append(str(e)));boot(page,False)
    check(not page.locator('[data-gps-wakelock-fallback]').get_attribute('hidden'),'sem API deixa o aviso de limitação preparado')
    start_with_points(page)
    check(page.locator('[data-gps-wakelock-fallback]').is_visible(),'sem API mostra aviso discreto durante a atividade')
    check(page.locator('[data-gps-live]').is_visible(),'sem Wake Lock gravação GPS continua funcionando')
    check(page.evaluate('__wakeRequests')==0,'sem API não tenta chamada inexistente')
    check(not errors2,f'sem Wake Lock não há exceções JS {errors2}')
    page.close()
    browser.close();print(f'✓ GPS Wake Lock + touch lock: {count[0]} assertions')
