#!/usr/bin/env python3
from pathlib import Path
import json
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f"Playwright indisponível: {exc}", file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[2]
BASEMAP_JS = (ROOT / 'public/assets/js/map-basemaps.js').read_text()
HTML = '''<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"></head><body>
<div class="activity-editor-shell is-open" data-activity-form><form id="activity-form" class="activity-editor">
<input type="hidden" name="csrf_token" value="test">
<div class="unrelated-field"><input name="observacoes" value="preservar"></div>
<section class="activity-route-builder" data-route-editor data-route-allowed="1" data-route-has-points="0" data-route-compact="1">
<input type="hidden" name="rota_coordenadas" value="" data-route-value>
<input type="hidden" name="route_editor_mode" value="free" data-route-mode-value>
<input type="hidden" name="route_editor_laps" value="1" data-route-laps-value>
<input type="hidden" name="route_editor_base" value="" data-route-base-value>
<input type="hidden" name="rota_metricas[distancia_metros]" disabled data-route-metric="distancia_metros">
<input type="hidden" name="rota_metricas[ganho_elevacao_m]" disabled data-route-metric="ganho_elevacao_m">
<input type="hidden" name="rota_metricas[perda_elevacao_m]" disabled data-route-metric="perda_elevacao_m">
<input type="hidden" name="rota_metricas[elevacao_min_m]" disabled data-route-metric="elevacao_min_m">
<input type="hidden" name="rota_metricas[elevacao_max_m]" disabled data-route-metric="elevacao_max_m">
<input type="hidden" name="rota_metricas[fonte_elevacao]" disabled data-route-metric="fonte_elevacao">
<button type="button" data-route-toggle>Adicionar rota</button>
<div class="activity-route-workspace" data-route-workspace hidden>
  <div class="activity-route-subview-header" data-route-subview-header><button type="button" data-route-subview-back>Back</button><div><strong>Rota</strong><span data-route-subview-summary></span></div><button type="button" data-route-subview-done>Pronto</button></div>
  <div class="activity-route-mode"><button type="button" data-route-mode="free">Livre</button><button type="button" data-route-mode="circuit">Circuito</button></div>
  <div class="activity-route-toolbar"><button type="button" data-route-locate>Local</button><button type="button" data-route-undo>Undo</button><button type="button" data-route-clear>Clear</button><button type="button" data-route-close-circuit hidden>Close</button><button type="button" data-route-done hidden>Done</button></div>
  <div class="activity-route-map" data-route-map></div>
  <div data-route-circuit-panel hidden><div><strong data-route-lap-summary></strong><button type="button" data-route-edit-base aria-pressed="false">Edit</button></div><div><button type="button" data-route-laps-dec>-</button><output data-route-laps>1</output><button type="button" data-route-laps-inc>+</button></div><strong data-route-total></strong></div>
  <span data-route-points></span><span data-route-distance></span><span data-route-elevation></span>
</div>
</section>
</form></div>
</body></html>'''

UNIT_HTML = '''<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"></head><body>
<div class="activity-editor-shell is-open" data-activity-form><form id="activity-form" class="activity-editor"><input type="hidden" name="csrf_token" value="test">
<section data-model-panel data-main-modality="run" data-derived-type="pace_km" class="activity-model-panel is-segmented">
<input type="hidden" data-segment-mode-input value="1">
<div class="activity-unit" data-unit-index="0">
<select data-unit-sport-select><option selected value="run" data-permite-rota="1" data-derived-type="pace_km" data-slug="corrida">Run</option></select>
<input data-segment-distance><input type="hidden" data-segment-distance-unit-value value="km"><input data-segment-elevation>
<section class="activity-unit-route-editor" data-unit-route-editor data-unit-route-label="Trecho 1">
<input type="hidden" data-unit-route-value name="unidades[0][rota_coordenadas]">
<input type="hidden" data-unit-route-mode-value name="unidades[0][route_editor_mode]" value="free">
<input type="hidden" data-unit-route-laps-value name="unidades[0][route_editor_laps]" value="1">
<input type="hidden" data-unit-route-base-value name="unidades[0][route_editor_base]">
<input type="hidden" disabled data-unit-route-metric="distancia_metros"><input type="hidden" disabled data-unit-route-metric="ganho_elevacao_m"><input type="hidden" disabled data-unit-route-metric="perda_elevacao_m"><input type="hidden" disabled data-unit-route-metric="elevacao_min_m"><input type="hidden" disabled data-unit-route-metric="elevacao_max_m"><input type="hidden" disabled data-unit-route-metric="fonte_elevacao">
<div class="activity-unit-route-summary-row"><div><strong data-unit-route-title>Rota do Trecho 1</strong><small data-unit-route-summary>Opcional</small></div><button type="button" data-unit-route-open>Adicionar rota</button></div>
</section></div>
<div class="activity-unit" data-unit-index="1">
<select data-unit-sport-select><option selected value="run" data-permite-rota="1" data-derived-type="pace_km" data-slug="corrida">Run</option></select>
<input data-segment-distance><input type="hidden" data-segment-distance-unit-value value="km"><input data-segment-elevation>
<section class="activity-unit-route-editor" data-unit-route-editor data-unit-route-label="Trecho 2">
<input type="hidden" data-unit-route-value name="unidades[1][rota_coordenadas]">
<input type="hidden" data-unit-route-mode-value name="unidades[1][route_editor_mode]" value="free">
<input type="hidden" data-unit-route-laps-value name="unidades[1][route_editor_laps]" value="1">
<input type="hidden" data-unit-route-base-value name="unidades[1][route_editor_base]">
<input type="hidden" disabled data-unit-route-metric="distancia_metros"><input type="hidden" disabled data-unit-route-metric="ganho_elevacao_m"><input type="hidden" disabled data-unit-route-metric="perda_elevacao_m"><input type="hidden" disabled data-unit-route-metric="elevacao_min_m"><input type="hidden" disabled data-unit-route-metric="elevacao_max_m"><input type="hidden" disabled data-unit-route-metric="fonte_elevacao">
<div class="activity-unit-route-summary-row"><div><strong data-unit-route-title>Rota do Trecho 2</strong><small data-unit-route-summary>Opcional</small></div><button type="button" data-unit-route-open>Adicionar rota</button></div>
</section></div>
</section>
<section class="activity-route-builder" data-route-editor data-route-allowed="1" data-route-has-points="0" data-route-compact="1" hidden>
<input type="hidden" name="rota_coordenadas" value="" data-route-value>
<input type="hidden" name="route_editor_mode" value="free" data-route-mode-value>
<input type="hidden" name="route_editor_laps" value="1" data-route-laps-value>
<input type="hidden" name="route_editor_base" value="" data-route-base-value>
<input type="hidden" disabled data-route-metric="distancia_metros"><input type="hidden" disabled data-route-metric="ganho_elevacao_m"><input type="hidden" disabled data-route-metric="perda_elevacao_m"><input type="hidden" disabled data-route-metric="elevacao_min_m"><input type="hidden" disabled data-route-metric="elevacao_max_m"><input type="hidden" disabled data-route-metric="fonte_elevacao">
<div class="activity-route-heading"><button type="button" data-route-toggle>Adicionar rota geral</button><span data-route-distance></span></div>
<div class="activity-route-workspace" data-route-workspace hidden>
<div class="activity-route-subview-header" data-route-subview-header><button type="button" data-route-subview-back>Voltar</button><div><strong data-route-subview-title>Rota</strong><span data-route-subview-summary></span></div><button type="button" data-route-subview-done>Pronto</button></div>
<div class="activity-route-mode"><button type="button" data-route-mode="free">Livre</button><button type="button" data-route-mode="circuit">Circuito</button></div>
<div class="activity-route-toolbar"><button type="button" data-route-locate>Local</button><button type="button" data-route-undo>Undo</button><button type="button" data-route-clear>Clear</button><button type="button" data-route-close-free hidden>Close route</button><button type="button" data-route-close-circuit hidden>Close circuit</button><button type="button" data-route-done hidden>Done</button></div>
<div class="activity-route-map" data-route-map></div>
<div data-route-circuit-panel hidden><div><strong data-route-lap-summary></strong><button type="button" data-route-edit-base aria-pressed="false">Edit</button></div><div><button type="button" data-route-laps-dec>-</button><output data-route-laps>1</output><button type="button" data-route-laps-inc>+</button></div><strong data-route-total></strong></div>
<span data-route-points></span><span data-route-elevation></span>
</div></section>
</form></div></body></html>'''

MOCK_LEAFLET = r'''() => {
  const state = {maps: [], markers: [], lines: [], tileLayers: []};
  const makeMap = () => {
    const handlers = {};
    const map = {handlers, _layers:new Set(), setView(){return this}, on(name, cb){handlers[name]=cb;return this}, fitBounds(){return this}, invalidateSize(){return this}, trigger(name, payload){handlers[name]?.(payload)}, hasLayer(layer){return this._layers.has(layer)}, removeLayer(layer){this._layers.delete(layer);return this}};
    state.maps.push(map); return map;
  };
  window.__leafletState = state;
  window.L = {
    map(){ return makeMap() },
    tileLayer(url, options={}){ const handlers={}; const layer={url,options,handlers,on(name,cb){(handlers[name] ||= []).push(cb);return this},fire(name){(handlers[name]||[]).forEach(cb=>cb({type:name,target:this}))},addTo(map){map?._layers?.add(this);return this}}; state.tileLayers.push(layer); return layer },
    polyline(latlngs, options){ const line={latlngs,options,addTo(){state.lines.push(this);return this},remove(){},getBounds(){return {}}}; return line },
    marker(latlng, options){ const handlers={}; const marker={latlng,options,handlers,addTo(){state.markers.push(this);return this},remove(){},on(name,cb){handlers[name]=cb;return this},getLatLng(){return {lat:this.latlng[0],lng:this.latlng[1]}},dragTo(lat,lng){this.latlng=[lat,lng];handlers.dragend?.({target:{getLatLng:()=>({lat,lng})}})}}; return marker },
    divIcon(options){ return options },
    latLngBounds(){return {}}
  };
  window.fetch = async () => ({ok:true, json:async()=>({ok:true,elevation:{ganho_elevacao_m:4,perda_elevacao_m:4,elevacao_min_m:100,elevacao_max_m:104,fonte_elevacao:'test'}})});
  window.StrideBRI18n = {
    locale:'pt-BR',
    t:(key, values={}) => { let out = ({'route.title':'Rota','route.add':'Adicionar rota','route.edit':'Editar rota','common.optional':'Opcional','route.lap.one':'1 volta','route.lap.other':'{count} voltas','route.closed':'Circuito fechado','route.editing_base':'Editando volta-base','route.edit_base':'Editar volta-base','route.finish_base_edit':'Concluir edição','route.point_limit':'Limite {laps}','route.base_point_limit':'Base no limite {count}','route.free_point_limit':'Livre no limite {count}'}[key] || key); Object.entries(values).forEach(([k,v])=>out=out.replaceAll(`{${k}}`,String(v))); return out },
    number:(value, digits=0)=>Number(value).toFixed(digits).replace(/\.0+$/,''),
    sport:(_s,f)=>f
  };
  window.__failElevation = false;
  window.fetch = async () => window.__failElevation
    ? ({ok:false, json:async()=>({ok:false,message:'fail'})})
    : ({ok:true, json:async()=>({ok:true,elevation:{ganho_elevacao_m:4,perda_elevacao_m:4,elevacao_min_m:100,elevacao_max_m:104,fonte_elevacao:'test'}})});
}'''

def route(page):
    raw = page.locator('[data-route-value]').input_value()
    return json.loads(raw) if raw else None

def base(page):
    raw = page.locator('[data-route-base-value]').input_value()
    return json.loads(raw) if raw else None

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    page = browser.new_page(viewport={"width": 900, "height": 700})
    errors=[]
    page.on('pageerror', lambda exc: errors.append(str(exc)))
    page.set_content(HTML, wait_until='domcontentloaded')
    page.add_style_tag(path=str(ROOT/'public/assets/css/atividades.css'))
    page.add_style_tag(path=str(ROOT/'public/assets/css/ui-refresh.css'))
    page.evaluate(MOCK_LEAFLET)
    page.evaluate("([code,key]) => { const s=document.createElement('script'); s.dataset.arcgisKey=key; s.textContent=code; document.head.appendChild(s); }", [BASEMAP_JS, 'test-public-basemap-key'])
    page.add_script_tag(path=str(ROOT/'public/assets/js/activity-route-utils.js'))
    page.add_script_tag(path=str(ROOT/'public/assets/js/atividades.js'))
    page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
    page.wait_for_timeout(80)
    asserts=0
    def ok(condition, message):
        nonlocal_dummy = None
        if not condition: raise AssertionError(message)
    def check(condition, message):
        nonlocal_placeholder = None
        global asserts
    # Python has no block nonlocal here; use list.
    count=[0]
    def a(condition, message):
        count[0]+=1
        if not condition: raise AssertionError(message)

    page.click('[data-route-toggle]')
    page.wait_for_timeout(20)
    a(page.locator('#activity-form').evaluate("el=>el.classList.contains('is-route-subview')"), 'Adicionar rota entra na subview usando o mesmo modal')
    a(page.locator('[data-route-subview-header]').is_visible(), 'subview mostra header próprio de rota')
    a(page.locator('.unrelated-field').is_hidden(), 'subview esconde campos não relacionados ao desenho da rota')
    a(page.locator('[data-route-map]').bounding_box()['height'] >= 300, 'mapa recebe área grande no desktop')
    a(page.locator('[data-route-done]').is_hidden(), 'ação antiga do workspace fica oculta no modo compacto')
    a(page.locator('[data-route-subview-done]').is_visible(), 'ação Pronto permanece acessível na subview')
    page.click('[data-route-mode="circuit"]')
    a(page.locator('[data-route-circuit-panel]').is_hidden(), 'painel de voltas fica oculto até o circuito ser fechado')
    m = page.evaluate_handle('window.__leafletState.maps[0]')
    pts=[(-27.3600,-53.3900),(-27.3600,-53.3890),(-27.3610,-53.3890),(-27.3610,-53.3900)]
    for lat,lng in pts:
        page.evaluate('([lat,lng]) => window.__leafletState.maps[0].trigger("click", {latlng:{lat,lng}})', [lat,lng])
    a(page.locator('[data-route-close-circuit]').is_visible(), 'Fechar circuito aparece após 3+ pontos')
    a(page.locator('[data-route-circuit-panel]').is_hidden(), 'controle de voltas continua oculto enquanto a base está aberta')
    page.click('[data-route-close-circuit]')
    r=route(page); b=base(page)
    a(r and len(r['coordinates'])==5, '1 volta gera LineString fechado')
    a(b and len(b['coordinates'])==5 and b['coordinates'][0]==b['coordinates'][-1], 'volta-base fechada fica preservada')
    a(page.locator('[data-route-circuit-panel]').is_visible(), 'painel de voltas aparece após fechar')
    initial_total = page.locator('[data-route-total]').inner_text()
    a(bool(initial_total.strip()), 'fechar circuito mostra prévia da distância total')
    a('1 volta' in page.locator('[data-route-lap-summary]').inner_text(), 'resumo descreve uma volta-base, não N voltas')
    a(page.locator('[data-route-laps]').inner_text()=='1', 'começa em uma volta')
    a(page.evaluate('window.__leafletState.markers.filter(m=>m.options.draggable===false).length') >= 1, 'circuito concluído não expõe vértices editáveis')

    for _ in range(9): page.click('[data-route-laps-inc]')
    a(page.locator('[data-route-laps]').inner_text()=='10', 'controle + atualiza visualmente a quantidade de voltas')
    a(page.locator('[data-route-total]').inner_text() != initial_total, 'controle + atualiza a prévia da distância total')
    r=route(page)
    a(page.locator('[data-route-laps]').inner_text()=='10', 'controle chega a 10 voltas')
    a(len(r['coordinates'])==41, '10 voltas não duplicam junções')
    a(r['coordinates'][0]==r['coordinates'][-1], 'rota expandida termina no início')
    first=r['coordinates'][0]
    a(all(r['coordinates'][i*4]==first for i in range(1,11)), 'todas as voltas conectam no ponto inicial')
    old_second=r['coordinates'][1]

    basemap_snapshot = page.evaluate("() => ({route:document.querySelector('[data-route-value]').value, base:document.querySelector('[data-route-base-value]').value, mode:document.querySelector('[data-route-mode-value]').value, laps:document.querySelector('[data-route-laps-value]').value, markers:window.__leafletState.markers.length})")
    switch_results = page.evaluate("() => ['satellite','terrain','street'].map(id => StrideBRBasemaps.setBaseLayer(window.__leafletState.maps[0], id))")
    basemap_after = page.evaluate("() => ({route:document.querySelector('[data-route-value]').value, base:document.querySelector('[data-route-base-value]').value, mode:document.querySelector('[data-route-mode-value]').value, laps:document.querySelector('[data-route-laps-value]').value, markers:window.__leafletState.markers.length})")
    a(all(switch_results), 'troca Mapa/Satélite/Relevo funciona durante circuito')
    a(basemap_after == basemap_snapshot, 'trocar basemap não altera geometria, volta-base, N, modo ou markers do circuito')

    page.click('[data-route-mode="free"]')
    a(page.locator('[data-route-mode-value]').input_value()=='free' and len(route(page)['coordinates'])==41, 'Circuito para Livre mantém toda a geometria')
    page.click('[data-route-mode="circuit"]')
    a(page.locator('[data-route-laps-value]').input_value()=='10' and len(route(page)['coordinates'])==41, 'Livre para Circuito recupera repetição exata sem corromper')

    page.wait_for_timeout(1000)
    a(page.locator('[data-route-metric="ganho_elevacao_m"]').input_value()=='40', 'elevação usa a volta-base e multiplica ganho pelas 10 voltas')
    page.evaluate('window.__failElevation = true')
    page.click('[data-route-laps-dec]')
    a(page.locator('[data-route-metric="ganho_elevacao_m"]').input_value()=='', 'nova estimativa limpa elevação antiga antes de uma possível falha')
    page.evaluate('window.__failElevation = false')
    page.click('[data-route-laps-inc]')

    page.evaluate("() => { document.querySelector('[data-route-base-value]').value=''; document.querySelector('[data-route-mode-value]').value='free'; document.querySelector('[data-route-laps-value]').value='1'; }")
    page.evaluate("document.querySelector('[data-route-editor]').dispatchEvent(new CustomEvent('activity:route-reload'))")
    a(page.locator('[data-route-mode-value]').input_value()=='circuit', 'reabrir LineString repetido recupera modo circuito')
    a(page.locator('[data-route-laps-value]').input_value()=='10', 'reabrir atividade recupera quantidade de voltas')
    a(len(base(page)['coordinates'])==5, 'reabrir atividade reconstrói somente a volta-base')
    a(len(route(page)['coordinates'])==41, 'reabrir atividade preserva LineString completo')
    a(page.locator('[data-route-edit-base]').get_attribute('aria-pressed')=='false', 'reabrir atividade mantém circuito concluído até edição explícita')
    a(page.locator('[data-route-points]').inner_text()=='Circuito fechado', 'status de reabertura permanece concluído')

    page.click('[data-route-undo]')
    a(route(page) is None and len(base(page)['coordinates'])==4, 'Undo após fechar desfaz o fechamento sem apagar a volta-base')
    a(page.locator('[data-route-close-circuit]').is_visible(), 'após desfazer fechamento é possível fechar novamente')
    page.click('[data-route-close-circuit]')
    a(len(route(page)['coordinates'])==41, 'fechar novamente restaura as 10 voltas')

    page.click('[data-route-edit-base]')
    a(page.locator('[data-route-edit-base]').get_attribute('aria-pressed')=='true', 'editar volta-base entra em modo de edição explícito')
    a(page.locator('[data-route-points]').inner_text()=='Editando volta-base', 'status informa edição da base')
    # latest four draggable markers are the unique vertices
    page.evaluate('''() => { const all=window.__leafletState.markers.filter(m=>m.options.draggable); const m=all[all.length-3]; m.dragTo(-27.3600,-53.3887); }''')
    r2=route(page)
    a(len(r2['coordinates'])==41, 'arrastar ponto regenera as 10 repetições')
    a(r2['coordinates'][1] != old_second and all(r2['coordinates'][1+i*4]==r2['coordinates'][1] for i in range(10)), 'ponto alterado se replica em todas as voltas')

    page.evaluate('([lat,lng]) => window.__leafletState.maps[0].trigger("click", {latlng:{lat,lng}})', [-27.3605,-53.3902])
    r3=route(page)
    a(len(r3['coordinates'])==51, 'adicionar vértice à volta-base regenera circuito completo')
    page.click('[data-route-undo]')
    a(len(route(page)['coordinates'])==41, 'Undo durante edição atua na volta-base')
    page.click('[data-route-edit-base]')
    a(page.locator('[data-route-edit-base]').get_attribute('aria-pressed')=='false', 'concluir edição volta a esconder vértices')
    saved_before_done = page.locator('[data-route-value]').input_value()
    laps_before_done = page.locator('[data-route-laps-value]').input_value()
    page.click('[data-route-subview-done]')
    a(page.locator('[data-route-workspace]').is_hidden(), 'Pronto volta ao formulário sem salvar nem apagar o draft')
    a(not page.locator('#activity-form').evaluate("el=>el.classList.contains('is-route-subview')"), 'Pronto encerra somente a subview de rota')
    a(page.locator('input[name="observacoes"]').input_value()=='preservar', 'sair da subview preserva os demais campos do draft')
    page.click('[data-route-toggle]')
    a(page.locator('[data-route-edit-base]').get_attribute('aria-pressed')=='false', 'Editar rota reabre em estado concluído, sem mover vértices por acidente')
    a(page.locator('[data-route-value]').input_value()==saved_before_done and page.locator('[data-route-laps-value]').input_value()==laps_before_done, 'reabrir a subview preserva geometria e voltas do draft')
    a(len(route(page)['coordinates'])==41, 'reabrir editor não altera geometria expandida')

    page.click('[data-route-laps-dec]')
    a(len(route(page)['coordinates'])==37 and page.locator('[data-route-laps]').inner_text()=='9', 'reduzir voltas regenera LineString')
    page.click('[data-route-clear]')
    a(route(page) is None and base(page) is None, 'Limpar remove rota e volta-base')
    a(page.locator('[data-route-laps-value]').input_value()=='1', 'Limpar reseta quantidade derivada')

    # Free route regression.
    page.click('[data-route-mode="free"]')
    page.evaluate('([lat,lng]) => window.__leafletState.maps[0].trigger("click", {latlng:{lat,lng}})', [-27.36,-53.39])
    page.evaluate('([lat,lng]) => window.__leafletState.maps[0].trigger("click", {latlng:{lat,lng}})', [-27.361,-53.389])
    a(len(route(page)['coordinates'])==2, 'rota livre continua funcionando')

    irregular = {'type':'LineString','coordinates':[[-53.39,-27.36],[-53.389,-27.36],[-53.39,-27.36],[-53.388,-27.361],[-53.39,-27.36]]}
    page.evaluate("([raw]) => { document.querySelector('[data-route-value]').value=JSON.stringify(raw); document.querySelector('[data-route-base-value]').value=''; document.querySelector('[data-route-mode-value]').value='free'; document.querySelector('[data-route-laps-value]').value='1'; document.querySelector('[data-route-editor]').dispatchEvent(new CustomEvent('activity:route-reload')); }", [irregular])
    a(page.locator('[data-route-mode-value]').input_value()=='free', 'LineString não detectável reabre com segurança como rota livre')
    a(route(page)['coordinates']==irregular['coordinates'], 'fallback livre nunca perde coordenadas da rota salva')

    free_limit = {'type':'LineString','coordinates':[[-53.39 + i*0.000001,-27.36] for i in range(2000)]}
    page.evaluate("([raw]) => { document.querySelector('[data-route-value]').value=JSON.stringify(raw); document.querySelector('[data-route-base-value]').value=''; document.querySelector('[data-route-mode-value]').value='free'; document.querySelector('[data-route-laps-value]').value='1'; document.querySelector('[data-route-editor]').dispatchEvent(new CustomEvent('activity:route-reload')); }", [free_limit])
    page.evaluate('([lat,lng]) => window.__leafletState.maps[0].trigger("click", {latlng:{lat,lng}})', [-27.361,-53.387])
    a(page.locator('[data-route-points]').inner_text()=='Livre no limite 2000', 'limite de pontos da rota livre informa o bloqueio em vez de ignorar silenciosamente')
    a(len(route(page)['coordinates'])==2000, 'limite de pontos não trunca rota livre existente')

    dense_base = [[-53.39 + i*0.000001,-27.36] for i in range(1000)]
    dense_base.append(dense_base[0])
    dense_geo = {'type':'LineString','coordinates':dense_base}
    page.evaluate("([baseRaw]) => { document.querySelector('[data-route-value]').value=''; document.querySelector('[data-route-base-value]').value=JSON.stringify(baseRaw); document.querySelector('[data-route-mode-value]').value='circuit'; document.querySelector('[data-route-laps-value]').value='2'; document.querySelector('[data-route-editor]').dispatchEvent(new CustomEvent('activity:route-reload')); }", [dense_geo])
    a(page.locator('[data-route-editor]').get_attribute('data-route-invalid')=='1', 'base × voltas acima de 2000 marca circuito como inválido')
    a(page.locator('[data-route-points]').inner_text()=='Limite 2', 'excesso base × voltas mostra mensagem localizada')
    a(len(base(page)['coordinates'])==1001 and route(page) is None, 'circuito excessivo preserva a base e não cria LineString truncado')

    if errors:
        raise AssertionError('Erros JS no browser: ' + ' | '.join(errors))

    unit_page=browser.new_page(viewport={"width": 900, "height": 700})
    unit_errors=[]
    unit_page.on('pageerror', lambda exc: unit_errors.append(str(exc)))
    unit_page.set_content(UNIT_HTML, wait_until='domcontentloaded')
    unit_page.add_style_tag(path=str(ROOT/'public/assets/css/atividades.css'))
    page.add_style_tag(path=str(ROOT/'public/assets/css/ui-refresh.css'))
    unit_page.evaluate(MOCK_LEAFLET)
    unit_page.evaluate("([code,key]) => { const s=document.createElement('script'); s.dataset.arcgisKey=key; s.textContent=code; document.head.appendChild(s); }", [BASEMAP_JS, 'test-public-basemap-key'])
    unit_page.add_script_tag(path=str(ROOT/'public/assets/js/activity-route-utils.js'))
    unit_page.add_script_tag(path=str(ROOT/'public/assets/js/atividades.js'))
    unit_page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
    unit_page.wait_for_timeout(80)
    a(unit_page.locator('[data-unit-route-map]').count()==0, 'trechos não mantêm Leaflet inline próprio')
    a(unit_page.evaluate('window.__leafletState.maps.length')==0, 'mapa compartilhado é lazy antes de abrir rota de trecho')
    unit_targets=unit_page.locator('[data-unit-route-editor]')
    first=unit_targets.nth(0); second=unit_targets.nth(1)
    first.locator('[data-unit-route-open]').click()
    unit_page.wait_for_timeout(80)
    a(unit_page.locator('#activity-form').evaluate("el=>el.classList.contains('is-route-subview')"), 'rota do trecho entra na mesma subview do formulário')
    a(unit_page.locator('[data-route-subview-title]').inner_text()=='Rota · Trecho 1', 'header identifica o target do trecho')
    a(unit_page.evaluate('window.__leafletState.maps.length')==1, 'trecho usa exatamente um mapa Leaflet compartilhado')
    unit_page.click('[data-route-mode="circuit"]')
    a(unit_page.locator('[data-route-circuit-panel]').is_hidden(), 'trecho oculta voltas antes de fechar circuito')
    for lat,lng in pts:
        unit_page.evaluate('([lat,lng]) => window.__leafletState.maps[0].trigger("click", {latlng:{lat,lng}})', [lat,lng])
    a(unit_page.locator('[data-route-close-circuit]').is_visible(), 'trecho oferece Fechar circuito no workspace compartilhado')
    unit_page.click('[data-route-close-circuit]')
    for _ in range(4): unit_page.click('[data-route-laps-inc]')
    unit_route=json.loads(first.locator('[data-unit-route-value]').input_value())
    a(len(unit_route['coordinates'])==21, 'rota de trecho expande 5 voltas')
    a(first.locator('[data-unit-route-laps-value]').input_value()=='5', 'quantidade de voltas volta para hidden do target')
    a(unit_page.locator('[data-route-laps]').inner_text()=='5', 'workspace mostra quantidade de voltas do trecho')
    a('1 volta' in unit_page.locator('[data-route-lap-summary]').inner_text(), 'workspace resume a volta-base do trecho')
    unit_page.wait_for_timeout(1000)
    a('+20 m' in unit_page.locator('[data-route-subview-summary]').inner_text(), 'header da rota do trecho mostra ganho de elevação quando disponível')
    unit_basemap_snapshot = unit_page.evaluate("() => ({route:document.querySelectorAll('[data-unit-route-value]')[0].value, base:document.querySelectorAll('[data-unit-route-base-value]')[0].value, mode:document.querySelectorAll('[data-unit-route-mode-value]')[0].value, laps:document.querySelectorAll('[data-unit-route-laps-value]')[0].value, markers:window.__leafletState.markers.length})")
    unit_switch_results = unit_page.evaluate("() => ['satellite','terrain','street'].map(id => StrideBRBasemaps.setBaseLayer(window.__leafletState.maps[0], id))")
    unit_basemap_after = unit_page.evaluate("() => ({route:document.querySelectorAll('[data-unit-route-value]')[0].value, base:document.querySelectorAll('[data-unit-route-base-value]')[0].value, mode:document.querySelectorAll('[data-unit-route-mode-value]')[0].value, laps:document.querySelectorAll('[data-unit-route-laps-value]')[0].value, markers:window.__leafletState.markers.length})")
    a(all(unit_switch_results), 'trecho usa Mapa/Satélite/Relevo do editor principal')
    a(unit_basemap_after == unit_basemap_snapshot, 'trocar basemap não altera geometria/voltas do target')
    unit_page.click('[data-route-edit-base]')
    unit_page.evaluate('([lat,lng]) => window.__leafletState.maps[0].trigger("click", {latlng:{lat,lng}})', [-27.3605,-53.3902])
    a(len(json.loads(first.locator('[data-unit-route-value]').input_value())['coordinates'])==26, 'editar volta-base regenera repetições do trecho')
    unit_page.click('[data-route-undo]')
    a(len(json.loads(first.locator('[data-unit-route-value]').input_value())['coordinates'])==21, 'Undo atua sobre target do trecho')
    unit_page.click('[data-route-subview-done]')
    unit_page.wait_for_timeout(40)
    a(not unit_page.locator('#activity-form').evaluate("el=>el.classList.contains('is-route-subview')"), 'Pronto retorna do workspace para o formulário')
    a('km' in first.locator('[data-unit-route-summary]').inner_text() or 'm' in first.locator('[data-unit-route-summary]').inner_text(), 'card do trecho mostra resumo compacto da rota')
    first_raw=first.locator('[data-unit-route-value]').input_value()
    second.locator('[data-unit-route-open]').click(); unit_page.wait_for_timeout(30)
    a(unit_page.locator('[data-route-subview-title]').inner_text()=='Rota · Trecho 2', 'mesmo workspace troca para Trecho 2')
    for lat,lng in [(-27.37,-53.40),(-27.37,-53.399),(-27.371,-53.399)]:
        unit_page.evaluate('([lat,lng]) => window.__leafletState.maps[0].trigger("click", {latlng:{lat,lng}})', [lat,lng])
    second_raw=second.locator('[data-unit-route-value]').input_value()
    a(second_raw!='' and second_raw!=first_raw, 'Trecho 2 mantém geometria própria')
    a(first.locator('[data-unit-route-value]').input_value()==first_raw, 'editar Trecho 2 não altera geometria do Trecho 1')
    unit_page.click('[data-route-subview-done]'); unit_page.wait_for_timeout(30)
    first.locator('[data-unit-route-open]').click(); unit_page.wait_for_timeout(30)
    a(first.locator('[data-unit-route-value]').input_value()==first_raw, 'reabrir Trecho 1 recupera seu draft')
    a(unit_page.evaluate('window.__leafletState.maps.length')==1, 'reabrir targets continua reutilizando o único Leaflet')
    if unit_errors:
        raise AssertionError('Erros JS no circuito de trecho: ' + ' | '.join(unit_errors))
    unit_page.close()

    print(f'✓ browser activity route circuit: {count[0]} assertions')
    browser.close()
