#!/usr/bin/env python3
from pathlib import Path
import json, sys
try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f'Playwright indisponível: {exc}', file=sys.stderr); sys.exit(2)
ROOT=Path(__file__).resolve().parents[2]
BASEMAP=(ROOT/'public/assets/js/map-basemaps.js').read_text()
HTML='''<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"></head><body>
<div class="activity-editor-shell is-open" data-activity-form><form id="activity-form" class="activity-editor">
<section class="other-field"><input name="observacoes" value="fica"></section>
<section class="activity-route-builder" data-route-editor data-route-allowed="1" data-route-has-points="0" data-route-compact="1">
<input type="hidden" data-route-value name="rota_coordenadas"><input type="hidden" data-route-mode-value value="free"><input type="hidden" data-route-laps-value value="1"><input type="hidden" data-route-base-value>
<input type="hidden" disabled data-route-metric="distancia_metros"><input type="hidden" disabled data-route-metric="ganho_elevacao_m"><input type="hidden" disabled data-route-metric="perda_elevacao_m"><input type="hidden" disabled data-route-metric="elevacao_min_m"><input type="hidden" disabled data-route-metric="elevacao_max_m"><input type="hidden" disabled data-route-metric="fonte_elevacao">
<div class="activity-route-heading"><button type="button" data-route-toggle>Adicionar rota</button><span data-route-distance></span></div>
<div class="activity-route-workspace" data-route-workspace hidden>
<div class="activity-route-subview-header"><button type="button" data-route-subview-back>← Voltar</button><div><strong>Rota</strong><span data-route-subview-summary></span><small data-route-subview-status hidden></small></div><button type="button" data-route-subview-done>Pronto</button></div>
<div class="activity-route-mode"><span>Traçado</span><button type="button" data-route-mode="free">Livre</button><button type="button" data-route-mode="circuit">Circuito</button></div>
<div class="activity-route-toolbar"><button type="button" data-route-locate>Local</button><button type="button" data-route-undo>Desfazer</button><button type="button" data-route-clear>Limpar</button><button type="button" data-route-close-free hidden>Fechar rota</button><button type="button" data-route-close-circuit hidden>Fechar circuito</button><button type="button" data-route-done hidden>Pronto</button></div>
<div class="activity-route-map" data-route-map></div>
<div class="activity-route-circuit-panel" data-route-circuit-panel hidden><div><strong data-route-lap-summary></strong><button type="button" data-route-edit-base>Editar</button></div><div><button type="button" data-route-laps-dec>-</button><output data-route-laps>1</output><button type="button" data-route-laps-inc>+</button></div><strong data-route-total></strong></div>
<div class="activity-route-status"><span data-route-points></span><span data-route-elevation></span></div><small class="activity-route-attribution">Elev.</small>
</div></section>
<section class="activity-route-privacy-fields" data-route-privacy-fields hidden><div class="activity-route-privacy-summary"><div><strong>Privacidade da rota</strong><span>Rota completa salva</span></div><button type="button" data-route-privacy-toggle aria-expanded="false">Configurar</button></div><div class="activity-route-privacy-options" data-route-privacy-options hidden><div class="activity-compact-two-columns"><label class="input-field"><span>Ocultar no início</span><input name="ocultar_inicio_m" value="125"><small>metros · 0 = mostrar tudo</small></label><label class="input-field"><span>Ocultar no fim</span><input name="ocultar_fim_m" value="250"><small>metros · 0 = mostrar tudo</small></label></div></div></section>
<section class="other-field second">FC / equipamento / observação</section>
</form></div></body></html>'''
MOCK=r'''() => {
 const state={maps:[],markers:[],lines:[],invalidates:0}; window.__leafletState=state;
 window.L={
  map(){const handlers={};const map={handlers,_layers:new Set(),setView(){return this},on(n,cb){handlers[n]=cb;return this},fitBounds(){return this},invalidateSize(){state.invalidates+=1;return this},trigger(n,p){handlers[n]?.(p)},hasLayer(l){return this._layers.has(l)},removeLayer(l){this._layers.delete(l);return this}};state.maps.push(map);return map},
  tileLayer(){return {on(){return this},addTo(map){map?._layers?.add(this);return this}}},
  polyline(latlngs,options){return {latlngs,options,addTo(){state.lines.push(this);return this},remove(){},getBounds(){return {}}}},
  marker(latlng,options){const m={latlng,options,addTo(){state.markers.push(this);return this},remove(){},on(){return this},getLatLng(){return {lat:this.latlng[0],lng:this.latlng[1]}}};return m}, divIcon(o){return o},latLngBounds(){return {}}
 };
 window.StrideBRI18n={locale:'pt-BR',t:(k,v={})=>{let s=({'route.lap.one':'1 volta','route.lap.other':'{count} voltas','route.closed':'Circuito fechado','route.route_closed':'Rota fechada','route.add_manual':'Adicionar rota','route.need_three':'3 pontos','route.need_two':'2 pontos','common.configure':'Configurar','common.close':'Fechar'}[k]||k);Object.entries(v).forEach(([a,b])=>s=s.replaceAll(`{${a}}`,String(b)));return s},number:v=>String(v),sport:(_s,f)=>f};
 window.fetch=async()=>({ok:true,json:async()=>({ok:true,elevation:{ganho_elevacao_m:8,perda_elevacao_m:8,elevacao_min_m:100,elevacao_max_m:108,fonte_elevacao:'test'}})});
}'''

def boot(page):
    page.set_content(HTML,wait_until='domcontentloaded')
    page.add_style_tag(path=str(ROOT/'public/assets/css/atividades.css'))
    page.add_style_tag(path=str(ROOT/'public/assets/css/ui-refresh.css'))
    page.evaluate(MOCK)
    page.evaluate("([code])=>{const s=document.createElement('script');s.dataset.arcgisKey='test';s.textContent=code;document.head.appendChild(s)}",[BASEMAP])
    page.add_script_tag(path=str(ROOT/'public/assets/js/activity-route-utils.js'))
    page.add_script_tag(path=str(ROOT/'public/assets/js/atividades.js'))
    page.evaluate("document.dispatchEvent(new Event('DOMContentLoaded'))")
    page.wait_for_timeout(70)

def add_points(page):
    pts=[[-27.36,-53.39],[-27.36,-53.389],[-27.361,-53.389],[-27.361,-53.39]]
    for lat,lng in pts:
        page.evaluate('([a,b])=>__leafletState.maps[0].trigger("click",{latlng:{lat:a,lng:b}})',[lat,lng])

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    count=[0]
    def check(v,m):
        count[0]+=1
        if not v: raise AssertionError(m)
    viewports=[(1366,768),(1440,900),(1920,1080),(390,844),(375,812),(360,640),(844,390)]
    for width,height in viewports:
        page=browser.new_page(viewport={'width':width,'height':height}); errors=[];page.on('pageerror',lambda e:errors.append(str(e)));boot(page)
        page.click('[data-route-toggle]');page.wait_for_timeout(80)
        check(page.locator('#activity-form').evaluate("el=>el.classList.contains('is-route-subview')"),f'{width}x{height}: rota entra em subview')
        check(page.locator('.other-field').first.is_hidden(),f'{width}x{height}: campos alheios somem durante desenho')
        map_box=page.locator('[data-route-map]').bounding_box(); editor_box=page.locator('#activity-form').bounding_box(); done_box=page.locator('[data-route-subview-done]').bounding_box()
        check(map_box and map_box['height']>=150,f'{width}x{height}: mapa recebe altura flexível útil {map_box}')
        check(map_box['x']>=-1 and map_box['x']+map_box['width']<=width+1,f'{width}x{height}: mapa sem clipping direito')
        check(map_box['y']>=-1 and map_box['y']+map_box['height']<=height+1,f'{width}x{height}: mapa sem clipping inferior')
        check(done_box and done_box['x']+done_box['width']<=width+1 and done_box['y']+done_box['height']<=height+1,f'{width}x{height}: Pronto acessível')
        if width<=760 or height<=520:
            check(abs(editor_box['height']-height)<=3 and abs(editor_box['width']-width)<=3,f'{width}x{height}: subview móvel/paisagem ocupa viewport inteira')
        else:
            check(editor_box['height']>=height-28 and editor_box['width']<=width-20,f'{width}x{height}: workspace desktop usa viewport com pequenas margens')
        check(page.evaluate('__leafletState.invalidates')>0,f'{width}x{height}: Leaflet invalidado ao abrir')
        check(page.locator('[data-route-privacy-fields]').is_hidden(),f'{width}x{height}: privacidade não aparece sem rota')

        add_points(page)
        check(page.locator('[data-route-privacy-fields]').get_attribute('hidden') is None,f'{width}x{height}: rota criada habilita a seção de privacidade')
        open_distance=page.locator('[data-route-distance]').inner_text()
        check(page.locator('[data-route-close-free]').is_visible(),f'{width}x{height}: Fechar rota aparece no Livre')
        page.click('[data-route-close-free]');page.wait_for_timeout(1000)
        free=json.loads(page.locator('[data-route-value]').input_value())['coordinates']
        check('+8 m' in page.locator('[data-route-subview-summary]').inner_text(),f'{width}x{height}: ganho estimado sobe para o resumo do header')
        check(page.locator('[data-route-subview-status]').is_visible(),f'{width}x{height}: conclusão da rota é comunicada no header')
        check(page.locator('.activity-route-status').is_hidden(),f'{width}x{height}: footer operacional some quando a rota está concluída')
        check(page.locator('[data-route-elevation]').is_hidden(),f'{width}x{height}: ganho não ocupa faixa inferior redundante')
        check(free[0]==free[-1],f'{width}x{height}: Livre fecha último ponto no primeiro')
        check(page.locator('[data-route-mode-value]').input_value()=='free',f'{width}x{height}: fechamento continua free')
        check(page.locator('[data-route-laps-value]').input_value()=='1' and page.locator('[data-route-base-value]').input_value()=='',f'{width}x{height}: fechamento livre não cria circuito/voltas')
        check(page.locator('[data-route-circuit-panel]').is_hidden(),f'{width}x{height}: painel de voltas não aparece no Livre')
        check(page.locator('[data-route-distance]').inner_text()!=open_distance,f'{width}x{height}: fechar rota atualiza distância')
        page.click('[data-route-undo]');page.wait_for_timeout(20)
        reopened=json.loads(page.locator('[data-route-value]').input_value())['coordinates']
        check(page.locator('.activity-route-status').is_visible(),f'{width}x{height}: footer operacional retorna ao reabrir a rota')
        check(page.locator('[data-route-subview-status]').is_hidden(),f'{width}x{height}: status de conclusão sai ao desfazer fechamento')
        check(reopened[0]!=reopened[-1] and len(reopened)==4,f'{width}x{height}: Desfazer reabre a rota anterior')

        page.click('[data-route-subview-done]')
        check(page.locator('[data-route-privacy-fields]').is_visible(),f'{width}x{height}: privacidade aparece na ficha quando existe rota')
        route_row=page.locator('[data-route-editor]').bounding_box(); privacy_row=page.locator('[data-route-privacy-fields]').bounding_box()
        check(route_row and privacy_row and abs(route_row['x']-privacy_row['x'])<=1 and abs(route_row['width']-privacy_row['width'])<=2,f'{width}x{height}: Rota e Privacidade compartilham o mesmo inset')
        check(page.locator('[data-route-privacy-options]').is_hidden(),f'{width}x{height}: privacidade começa compacta')
        page.click('[data-route-privacy-toggle]')
        check(page.locator('[data-route-privacy-options]').is_visible(),f'{width}x{height}: Configurar expande os dois campos')
        privacy_box=page.locator('[data-route-privacy-fields]').bounding_box(); close_box=page.locator('[data-route-privacy-toggle]').bounding_box()
        check(privacy_box and close_box and close_box['x']+close_box['width']<=privacy_box['x']+privacy_box['width']+1,f'{width}x{height}: Fechar permanece dentro do content width')
        fields=page.locator('.activity-route-privacy-options .input-field'); first_box=fields.nth(0).bounding_box(); second_box=fields.nth(1).bounding_box(); helper_box=fields.nth(0).locator('small').bounding_box()
        if width>760:
            check(abs(first_box['y']-second_box['y'])<=2,f'{width}x{height}: campos de privacidade ficam lado a lado quando há largura')
        else:
            check(second_box['y']>=first_box['y']+first_box['height']-1,f'{width}x{height}: campos de privacidade empilham no mobile')
        no_helper_overlap=helper_box['x']+helper_box['width']<=second_box['x']+1 or helper_box['y']+helper_box['height']<=second_box['y']+1 or second_box['x']+second_box['width']<=helper_box['x']+1
        check(no_helper_overlap,f'{width}x{height}: helper do primeiro campo não cola no label seguinte')
        page.click('[data-route-privacy-toggle]')
        check(page.locator('[data-route-privacy-options]').is_hidden(),f'{width}x{height}: fechar privacidade volta à linha compacta')
        check(page.locator('input[name="ocultar_inicio_m"]').input_value()=='125' and page.locator('input[name="ocultar_fim_m"]').input_value()=='250',f'{width}x{height}: valores de privacidade permanecem no draft')
        page.click('[data-route-toggle]')
        page.click('[data-route-clear]')
        check(page.locator('[data-route-privacy-fields]').is_hidden(),f'{width}x{height}: limpar rota oculta privacidade novamente')
        page.click('[data-route-mode="circuit"]')
        add_points(page)
        page.click('[data-route-close-circuit]');page.click('[data-route-laps-inc]')
        raw=page.locator('[data-route-value]').input_value(); closed=json.loads(raw)['coordinates']
        check(closed[0]==closed[-1],f'{width}x{height}: circuito existente continua fechado')
        check(page.locator('[data-route-laps]').inner_text()=='2',f'{width}x{height}: circuito mantém duas voltas')

        before=page.evaluate('__leafletState.invalidates')
        page.set_viewport_size({'width':width-1 if width>400 else width+1,'height':height-1 if height>500 else height+1})
        page.wait_for_timeout(80)
        check(page.evaluate('__leafletState.invalidates')>before,f'{width}x{height}: resize recalcula Leaflet')
        page.click('[data-route-subview-done]')
        check(not page.locator('#activity-form').evaluate("el=>el.classList.contains('is-route-subview')"),f'{width}x{height}: Pronto volta ao formulário')
        check(page.locator('input[name="observacoes"]').input_value()=='fica',f'{width}x{height}: draft dos outros campos preservado')
        check(not errors,f'{width}x{height}: sem erros JS {errors}')
        page.close()
    browser.close();print(f'✓ activity route subview: {count[0]} assertions; seven viewports')
