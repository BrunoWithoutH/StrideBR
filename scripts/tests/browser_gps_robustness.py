#!/usr/bin/env python3
from pathlib import Path
import sys
try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr); sys.exit(2)
ROOT=Path(__file__).resolve().parents[2]
HTML='''<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"></head><body class="gps-page"><main data-gps-recorder data-csrf-token="x" data-save-endpoint="/api/gps-salvar.php" data-elevation-endpoint="/api/atividade-elevacao.php">
<div data-gps-restore hidden><span data-gps-restore-summary></span><button data-gps-resume>Continuar</button><button data-gps-discard-saved>Descartar</button></div>
<section data-gps-setup><select data-gps-sport><option value="1" data-slug="corrida" data-metric="pace_km" selected>Corrida</option></select><input type="radio" name="gps_goal_type" value="none" checked><button data-gps-start>Iniciar</button></section>
<section class="gps-live" data-gps-live hidden><strong data-gps-live-sport></strong><span data-gps-network></span><span data-gps-quality></span><span data-gps-accuracy></span><div data-gps-live-warning></div><div data-gps-wakelock-fallback hidden></div>
<strong data-gps-time></strong><strong data-gps-distance></strong><span data-gps-pace-label></span><strong data-gps-pace></strong><strong data-gps-elevation></strong><strong data-gps-accuracy-large></strong><div data-gps-goal-card hidden><strong data-gps-goal-progress></strong></div><div data-gps-map></div><div data-gps-map-fallback></div><strong data-gps-current-lap></strong><small data-gps-current-lap-metrics></small>
<div data-gps-live-controls><button data-gps-pause>Pausar</button><button data-gps-lap>Trecho</button><button data-gps-lock>Bloquear</button><button data-gps-finish>Finalizar</button><button data-gps-discard-current>Cancelar</button></div><div data-gps-controls-lock-state hidden><button data-gps-unlock></button></div></section>
<section data-gps-review hidden><form data-gps-save-form><input data-gps-review-title><input data-gps-review-distance><input data-gps-review-duration><input data-gps-review-elevation><select data-gps-review-visibility><option value="privado">Privado</option></select><select data-gps-review-effort><option value=""></option></select><input data-gps-review-hide-start value="0"><input data-gps-review-hide-end value="0"><textarea data-gps-review-notes></textarea><div data-gps-save-state></div><button type="submit">Salvar</button></form><span data-gps-review-quality></span><div data-gps-quality-summary></div><div data-gps-review-warning hidden></div><div data-gps-segments></div><div data-gps-review-map></div><button data-gps-discard-review></button><button data-gps-back-live></button></section>
</main></body></html>'''
INIT=r'''() => {
 window.__watchCallback=null; window.__online=true; window.__fetchMode='network'; window.__fetchCalls=[]; window.__latlngs=null; window.__idbStore={};
 Object.defineProperty(window,'indexedDB',{configurable:true,value:{open(){const request={result:null};setTimeout(()=>{const db={objectStoreNames:{contains:()=>true},createObjectStore(){},transaction(){const tx={oncomplete:null,onerror:null,onabort:null,objectStore(){return {put(value,key){window.__idbStore[key]=structuredClone(value)},delete(key){delete window.__idbStore[key]},get(key){const r={result:null,onsuccess:null,onerror:null};setTimeout(()=>{r.result=window.__idbStore[key]||null;r.onsuccess?.()},0);return r}}}};setTimeout(()=>tx.oncomplete?.(),0);return tx}};request.result=db;request.onsuccess?.()},0);return request}}});
 Object.defineProperty(navigator,'onLine',{configurable:true,get(){return window.__online}});
 Object.defineProperty(navigator,'geolocation',{configurable:true,value:{watchPosition(cb){window.__watchCallback=cb;return 7},clearWatch(){window.__watchCallback=null}}});
 Object.defineProperty(navigator,'permissions',{configurable:true,value:{query:async()=>({state:'granted'})}});
 Object.defineProperty(navigator,'storage',{configurable:true,value:{persist:async()=>true}});
 Object.defineProperty(window,'isSecureContext',{configurable:true,value:true});
 window.__emitPosition=(lat,lon,t,alt=null)=>window.__watchCallback?.({coords:{latitude:lat,longitude:lon,accuracy:5,altitude:alt,altitudeAccuracy:alt===null?null:5,speed:null},timestamp:t});
 window.StrideBRBasemaps={attach(){}};
 window.L={map(){return {setView(){return this},fitBounds(){return this},panTo(){return this},invalidateSize(){return this}}},polyline(){return {addTo(){return this},setLatLngs(v){window.__latlngs=v;return this},getBounds(){return {}}}}};
 window.StrideBRI18n={locale:'pt-BR',t:(k,v={},f=null)=>{const d={'gps.pause':'Pausar','gps.continue':'Continuar','gps.online':'Online','gps.offline_local':'Offline','gps.activity':'Atividade','gps.sport_fallback':'Atividade','gps.pace':'Ritmo','gps.speed':'Velocidade','gps.waiting':'Aguardando','gps.quality_good':'Bom','gps.quality_fair':'Regular','gps.quality_poor':'Ruim','gps.quality_waiting':'Aguardando','gps.current_accuracy':'Precisão','gps.segment_number':'Trecho {number}','gps.quality_good_recording':'Boa','gps.avg_accuracy':'Média','gps.best_accuracy':'Melhor','gps.points_accepted':'Aceitos','gps.points_rejected':'Rejeitados','gps.visibility_gaps':'Lacunas','gps.source':'Fonte','gps.saving':'Salvando…','gps.offline_pending':'Salva neste aparelho · aguardando conexão.','gps.pending_upload':'Atividade aguardando envio.','gps.pending_upload_restore':'Atividade aguardando envio · gravação segura neste aparelho.','gps.retry_save':'Tentar novamente','gps.auth_required':'Sua sessão expirou.','gps.auth_recording_safe':'Sua gravação está segura neste aparelho. Entre novamente e tente salvar.','gps.save_failed':'Falha ao salvar','gps.save_error':'Falha ao salvar','gps.save_error_kept':'{message} A gravação continua guardada neste navegador.','gps.calculating_elevation':'Calculando elevação…','gps.saved':'Salva','gps.already_saved':'Já salva','gps.review_invalid_metrics':'Métricas inválidas','gps.warning_hidden':'Lacuna','gps.warning_accuracy':'Precisão','gps.warning_rejected':'Rejeitados'};let s=d[k]??f??k;Object.entries(v).forEach(([a,b])=>s=String(s).replaceAll(`{${a}}`,String(b)));return s},number:(v,d=0)=>Number(v).toFixed(d),sport:(_s,f)=>f,duration:(s)=>String(s)};
 window.StrideBRNet={fetch:async (url)=>{window.__fetchCalls.push(String(url)); if(String(url).includes('atividade-elevacao')) return {ok:true,status:200,json:async()=>({ok:true,elevation:{ganho_elevacao_m:18,elevacao_min_m:100,elevacao_max_m:118}})}; if(window.__fetchMode==='auth') return {ok:false,status:401,json:async()=>({ok:false,code:'auth_required',error:'Sua sessão expirou.'})}; if(window.__fetchMode==='500') return {ok:false,status:500,json:async()=>({ok:false,code:'server_error',error:'Servidor indisponível.'})}; throw new TypeError('NetworkError');}};
 window.confirm=()=>true; window.alert=()=>{};
}'''

def idb_active(page):
    return page.evaluate('window.__idbStore.active || null')

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,args=['--no-sandbox'])
    page=browser.new_page(viewport={'width':390,'height':844}); errors=[]; page.on('pageerror',lambda e:errors.append(str(e)))
    page.set_content(HTML,wait_until='domcontentloaded'); page.evaluate(INIT)
    page.add_script_tag(path=str(ROOT/'public/assets/js/gps-recorder.js')); page.wait_for_timeout(80)
    count=0
    def check(v,m):
        nonlocal_box[0]+=1
        if not v: raise AssertionError(m)
    nonlocal_box=[0]
    page.click('[data-gps-start]'); page.wait_for_selector('[data-gps-live]:not([hidden])')
    t=page.evaluate('Date.now()')
    page.evaluate('([t])=>__emitPosition(-27.36,-53.39,t,null)',[t])
    page.evaluate('([t])=>__emitPosition(-27.36,-53.3897,t,null)',[t+18000])
    page.wait_for_timeout(60)
    dist=page.locator('[data-gps-distance]').inner_text()
    check(dist not in ('0,00 km','0.00 km',''),f'amostra visível após 18s soma distância: {dist}')
    before=dist
    page.evaluate("Object.defineProperty(document,'visibilityState',{configurable:true,value:'hidden'});document.dispatchEvent(new Event('visibilitychange'))")
    page.evaluate('([t])=>__emitPosition(-27.36,-53.385,t,null)',[t+25000])
    page.wait_for_timeout(20)
    page.evaluate("Object.defineProperty(document,'visibilityState',{configurable:true,value:'visible'});const real=Date.now;window.__realDateNow=real;Date.now=()=>real()+6000;document.dispatchEvent(new Event('visibilitychange'));Date.now=real")
    page.evaluate('([t])=>__emitPosition(-27.36,-53.3848,t,null)',[t+26000])
    page.wait_for_timeout(50)
    check(page.locator('[data-gps-distance]').inner_text()==before,'gap/background não inventa deslocamento')
    check(page.evaluate('Array.isArray(__latlngs)&&Array.isArray(__latlngs[0])&&Array.isArray(__latlngs[0][0])'),'mapa ao vivo usa chunks quando existe gap')
    page.click('[data-gps-finish]'); page.wait_for_selector('[data-gps-review]:not([hidden])'); page.wait_for_timeout(100)
    check(page.locator('[data-gps-review-elevation]').input_value()=='18','sem altitude do iPhone review usa terreno quando rede disponível')
    page.evaluate("__online=false;__fetchMode='network'")
    page.locator('[data-gps-save-form]').evaluate('f=>f.requestSubmit()'); page.wait_for_timeout(100)
    check('aguardando conexão' in page.locator('[data-gps-save-state]').inner_text(),'save offline é estado pendente/neutro')
    saved=idb_active(page)
    check(bool(saved and saved.get('pendingUpload')),'pendingUpload persiste no IndexedDB')
    check(saved.get('saveRequestedAtMs',0)>0,'saveRequestedAtMs persiste')
    page.evaluate("__online=true;__fetchMode='network';window.dispatchEvent(new Event('online'))"); page.wait_for_timeout(100)
    check(page.evaluate("__fetchCalls.filter(x=>x.includes('gps-salvar')).length")>=1,'reconnect dispara retry seguro')
    saved=idb_active(page); check(bool(saved and saved.get('pendingUpload')),'falha de retry mantém gravação local')
    page.evaluate("__fetchMode='auth'"); page.locator('[data-gps-save-form]').evaluate('f=>f.requestSubmit()'); page.wait_for_timeout(80)
    check('segura neste aparelho' in page.locator('[data-gps-save-state]').inner_text(),'sessão expirada comunica gravação segura')
    saved=idb_active(page); check(bool(saved and saved.get('pendingUpload')),'401 não apaga IndexedDB')
    check(not errors,f'sem erros JS: {errors}')
    browser.close(); print(f'✓ GPS robustness iPhone/PWA simulated: {nonlocal_box[0]} assertions')
