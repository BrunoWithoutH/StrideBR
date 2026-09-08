#!/usr/bin/env python3
from pathlib import Path
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f"Playwright indisponível: {exc}", file=sys.stderr); sys.exit(2)

ROOT=Path(__file__).resolve().parents[2]
HTML='''<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"></head><body><main class="progress-page" data-progress-page><div class="progress-shell">
<header class="progress-heading"><div><span class="progress-eyebrow">Progresso</span><h1>Como você está treinando</h1><p>Consistência, volume e tendências com os dados já registrados.</p></div></header>
<form class="progress-filterbar" action="/user/progresso.php"><nav class="progress-period-presets" aria-label="Período"><a href="?period=4w" class="is-active">4 semanas</a><a href="?period=12w">12 semanas</a><a href="?period=6m">6 meses</a><a href="?period=1y">1 ano</a></nav><label class="progress-filter-select"><span>Modalidade</span><select name="sport" data-progress-auto-submit><option>Todos os esportes</option><option>Corrida</option></select></label><label class="progress-filter-select"><span>Métrica</span><select name="metric" data-progress-auto-submit><option>Distância</option></select></label></form>
<section class="progress-kpi-strip" aria-label="Resumo"><div><span>ATIVIDADES</span><strong>14</strong></div><div><span>TEMPO</span><strong>9h 42min</strong></div><div><span>DISTÂNCIA</span><strong>76,4 km</strong></div><div><span>DIAS ATIVOS</span><strong>11</strong></div></section>
<section class="progress-section"><header><div><span class="progress-section-question">Tenho mantido frequência?</span><h2>Consistência</h2><p>Presença de atividades ao longo do período.</p></div></header><div class="progress-consistency-wrap"><div class="progress-consistency-grid" role="list" aria-label="Dias com atividade">'''+''.join(f'<button type="button" class="progress-consistency-day {"is-active" if i in [1,3,4,8,10,14,17,18,22,24,27] else ""}" data-progress-tooltip="{i+1} ago: {"1 atividade · Corrida" if i in [1,3,8] else "Nenhuma atividade"}" aria-label="Dia {i+1}" role="listitem"></button>' for i in range(28))+'''</div></div><div class="progress-consistency-legend"><span><i></i>Sem atividade</span><span><i class="is-active"></i>Atividade registrada</span></div></section>
<section class="progress-section"><header><div><span class="progress-section-question">Quanto volume houve?</span><h2>Volume</h2><p>32,4 km nas últimas 4 semanas</p></div><strong class="progress-section-unit">Distância · Corrida</strong></header><div class="progress-bar-chart" style="--progress-week-count:4" role="group" aria-label="Distância semanal, Corrida"><div class="progress-zero-line"></div>
<div class="progress-bar-column"><button class="progress-bar-hit" data-progress-tooltip="10–16 ago · 3 atividades · 8,2 km · 45 min" aria-label="10–16 ago · 3 atividades · 8,2 km"><span class="progress-bar" style="height:52%"></span></button><small>10 ago</small></div>
<div class="progress-bar-column"><button class="progress-bar-hit" data-progress-tooltip="17–23 ago · 4 atividades · 10,1 km · 56 min" aria-label="17–23 ago · 4 atividades · 10,1 km"><span class="progress-bar" style="height:64%"></span></button><small>17 ago</small></div>
<div class="progress-bar-column"><button class="progress-bar-hit is-missing" data-progress-tooltip="24–30 ago · 2 atividades · sem distância" aria-label="24–30 ago · 2 atividades · sem distância"><span class="progress-bar" style="height:0%"></span></button><small>24 ago</small></div>
<div class="progress-bar-column"><button class="progress-bar-hit" data-progress-tooltip="31 ago–6 set · 5 atividades · 14,1 km · 1h12" aria-label="31 ago–6 set · 5 atividades · 14,1 km"><span class="progress-bar" style="height:88%"></span></button><small>31 ago</small></div></div><p class="progress-chart-note">— indica atividade sem essa métrica registrada.</p></section>
<section class="progress-section"><header><div><span class="progress-section-question">Como o volume mudou?</span><h2>Tendência</h2><p>Distância semanal ao longo do período.</p></div></header><div class="progress-line-chart-wrap"><svg class="progress-line-chart" viewBox="0 0 1000 270" role="img" aria-label="Tendência de distância semanal"><line x1="28" y1="225" x2="972" y2="225" class="progress-chart-axis"/><line x1="28" y1="122" x2="972" y2="122" class="progress-chart-grid"/><polyline class="progress-line-path" points="28,180 260,150"/><polyline class="progress-line-path" points="730,130 972,80"/><circle class="progress-line-point" cx="28" cy="180" r="8" tabindex="0" data-progress-tooltip="10–16 ago · 8,2 km" aria-label="10–16 ago, 8,2 km"/><circle class="progress-line-point" cx="972" cy="80" r="8" tabindex="0" data-progress-tooltip="31 ago–6 set · 14,1 km" aria-label="31 ago–6 set, 14,1 km"/></svg></div></section>
<section class="progress-section"><header><div><span class="progress-section-question">Quais modalidades pratiquei?</span><h2>Modalidades</h2></div></header><div class="progress-sport-list"><a class="progress-sport-row" href="#"><span class="progress-sport-icon">●</span><span><strong>Corrida</strong><small>8 atividades</small></span><span class="progress-sport-values"><strong>52,4 km</strong><small>5h18</small></span><span>›</span></a><a class="progress-sport-row" href="#"><span class="progress-sport-icon">■</span><span><strong>Musculação</strong><small>6 atividades</small></span><span class="progress-sport-values"><small>4h24</small></span><span>›</span></a></div></section>
<section class="progress-section"><header><div><span class="progress-section-question">Como este período se compara?</span><h2>Comparação</h2></div></header><div class="progress-comparison-list"><div><span>DISTÂNCIA</span><strong>52,4 km</strong><small>+8,2 km · período anterior</small></div><div><span>ATIVIDADES</span><strong>14</strong><small>+2 · período anterior</small></div><div><span>TEMPO</span><strong>9h42</strong><small>−32 min · período anterior</small></div></div></section>
<details class="progress-disclosure"><summary>Tendências por modalidade <span>Ver mais</span></summary><div class="progress-small-multiples"><article><header><span class="progress-sport-icon">●</span><div><strong>Corrida</strong><small>Distância</small></div></header><div class="progress-mini-bars"><i style="height:40%"></i><i style="height:70%"></i><i style="height:55%"></i><i style="height:85%"></i></div></article><article><header><span class="progress-sport-icon">■</span><div><strong>Musculação</strong><small>Atividades</small></div></header><div class="progress-mini-bars"><i style="height:55%"></i><i style="height:45%"></i><i style="height:75%"></i><i style="height:60%"></i></div></article></div></details>
</div><div class="progress-tooltip" data-progress-tooltip-popover role="tooltip" hidden></div></main></body></html>'''

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    desktop=browser.new_page(viewport={'width':1440,'height':1000})
    desktop.set_content(HTML)
    desktop.add_style_tag(path=str(ROOT/'public/assets/css/sport-hub.css'))
    desktop.add_script_tag(path=str(ROOT/'public/assets/js/progresso.js'))
    errors=[]; desktop.on('pageerror',lambda e:errors.append(str(e)))
    n=0
    def check(value,msg):
        nonlocal_dummy=None
        global n
        n+=1
        if not value: raise AssertionError(msg)
    check(desktop.locator('.progress-bar-chart').get_attribute('role')=='group','gráfico interativo não achata botões como role=img')
    zero=desktop.locator('.progress-zero-line').bounding_box(); bars=desktop.locator('.progress-bar-hit').all()
    check(all(abs((b.bounding_box()['y']+b.bounding_box()['height'])-zero['y']) <= 2 for b in bars),'barras de magnitude terminam na linha zero')
    check(desktop.locator('.progress-line-path').count()==2,'dado ausente quebra a linha em segmentos')
    desktop.locator('.progress-bar-hit').nth(0).hover(); desktop.wait_for_timeout(30)
    check(not desktop.locator('[data-progress-tooltip-popover]').is_hidden(),'hover mostra tooltip completo no desktop')
    check('3 atividades' in desktop.locator('[data-progress-tooltip-popover]').inner_text(),'tooltip inclui contexto factual')
    desktop.locator('.progress-line-point').nth(0).focus(); desktop.wait_for_timeout(20)
    check('8,2 km' in desktop.locator('[data-progress-tooltip-popover]').inner_text(),'foco por teclado revela detalhe')
    desktop.click('body', position={'x':5,'y':5}); check(desktop.locator('[data-progress-tooltip-popover]').is_hidden(),'tooltip pode ser dispensado')
    check(not errors,'sem erros JS no desktop')

    mobile=browser.new_page(viewport={'width':390,'height':844})
    mobile.set_content(HTML); mobile.add_style_tag(path=str(ROOT/'public/assets/css/sport-hub.css')); mobile.add_script_tag(path=str(ROOT/'public/assets/js/progresso.js'))
    check(mobile.locator('.progress-filterbar').bounding_box()['width'] <= 390,'filtros cabem no mobile sem reduzir controles a microbotões')
    mobile.locator('.progress-bar-hit').nth(1).scroll_into_view_if_needed(); mobile.wait_for_timeout(30); mobile.locator('.progress-bar-hit').nth(1).click(); mobile.wait_for_timeout(10)
    check(not mobile.locator('[data-progress-tooltip-popover]').is_hidden(),'toque abre tooltip no mobile')
    mobile.locator('.progress-disclosure').evaluate('el=>el.open=true')
    grid=mobile.locator('.progress-small-multiples').evaluate("el=>getComputedStyle(el).gridTemplateColumns")
    check(' ' not in grid.strip(),'small multiples viram uma coluna no mobile')
    check(mobile.locator('.progress-kpi-strip').evaluate("el=>getComputedStyle(el).overflowX") in ['auto','scroll'],'KPIs densos podem rolar horizontalmente em vez de esmagar')
    browser.close(); print(f'✓ browser progress product UX: {n} assertions')
