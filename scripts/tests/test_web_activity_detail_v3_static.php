<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "Web Activity Detail V3 static failed: {$message}\n");
        exit(1);
    }
};

$page = $read('public/user/atividades.php');
$activitiesJs = $read('public/assets/js/atividades.js');
$detailJs = $read('public/assets/js/activity-detail-v3.js');
$mapJs = $read('public/assets/js/web-map.js');
$basemapsJs = $read('public/assets/js/map-basemaps.js');
$detailCss = $read('public/assets/css/activity-detail-v3.css');
$activitiesCss = $read('public/assets/css/atividades.css');
$presenter = $read('src/function/atividade_presenter.php');
$detailApi = $read('public/api/atividade-detalhe.php');
$streamsApi = $read('public/api/atividade-streams.php');
$splitsApi = $read('public/api/atividade-splits.php');
$lapsApi = $read('public/api/atividade-laps.php');
$analysisApi = $read('public/api/atividade-analysis.php');
$zonesPage = $read('public/user/zonas.php');
$zonesJs = $read('public/assets/js/zone-profiles.js');
$streamService = $read('src/function/activity_stream_service.php');

$assert(str_contains($page, '/assets/css/activity-detail-v3.css') && str_contains($page, '/assets/js/web-map.js') && str_contains($page, '/assets/js/activity-detail-v3.js'), 'Atividades precisa carregar os assets do detalhe V3.');
$assert(str_contains($activitiesJs, 'StrideBRActivityDetailV3?.render') && str_contains($activitiesJs, 'data-detail-header-metrics'), 'renderer principal precisa delegar ao Detail V3 e preencher métricas compactas do header.');
$assert(str_contains($page, 'data-detail-open-full') && str_contains($page, 'activity-detail-export-submenu') && str_contains($page, 'data-detail-delete'), 'preview/detalhe deve manter ação principal no header e export/delete no overflow.');
$assert(!str_contains($detailJs, 'activity-v3-preview-actions') && str_contains($activitiesCss, '.activity-detail-preview-host .activity-detail-panel{max-height:none;overflow:visible'), 'preview não deve duplicar ações no rodapé nem manter scroll interno no desktop.');
$assert(str_contains($detailJs, "{id:'summary',label:'Resumo'}") && str_contains($detailJs, "id:'charts',label:'Gráficos'") && str_contains($detailJs, "id:'splits',label:'Splits'") && str_contains($detailJs, "id:'laps',label:'Voltas'") && str_contains($detailJs, "id:'zones',label:'Zonas'") && str_contains($detailJs, "id:'analysis',label:'Análise'"), 'Detail V3 precisa expor a navegação esportiva completa de forma condicional.');
$assert(str_contains($detailCss, '.activity-v3-panel[hidden]{display:none!important}') && str_contains($detailJs, 'panel.hidden=panel.dataset.activityV3Panel!==id'), 'tabs do detalhe devem manter apenas um painel visível por vez.');
$assert(str_contains($detailJs, 'caps.has_streams') && str_contains($detailJs, 'caps.has_laps') && str_contains($detailJs, 'caps.has_heart_rate'), 'tabs pesadas precisam depender das capabilities reais da Activity.');
$assert(str_contains($detailJs, 'data-activity-v3-map') && str_contains($detailJs, 'routeCollections') && str_contains($detailJs, 'StrideBRWebMap?.mountMany?.'), 'Activity com rota simples ou por trechos precisa montar um único mapa com múltiplas polylines.');
$assert(str_contains($mapJs, 'StrideBRBasemaps?.attach') && str_contains($mapJs, 'mountMany') && str_contains($mapJs, 'normalizedRoutes.forEach') && str_contains($mapJs, 'L.polyline') && str_contains($mapJs, 'fitBounds'), 'componente compartilhado precisa manter cada rota como polyline independente e calcular bounds agregados.');
$assert(str_contains($mapJs, 'StrideBRBasemaps?.attach') && str_contains($basemapsJs, 'tile.openstreetmap.org') && str_contains($basemapsJs, 'OpenStreetMap'), 'componente precisa reutilizar o basemap geográfico real OSM e sua atribuição.');
$assert(str_contains($mapJs, 'L.circleMarker') && str_contains($mapJs, "bindTooltip('Início'") && str_contains($mapJs, "bindTooltip('Fim'"), 'mapa precisa diferenciar início e fim sem dezenas de markers intermediários.');
$assert(str_contains($detailJs, "resolution=medium") && str_contains($detailJs, '&axis=${resolvedAxis}&resolution=medium'), 'gráficos devem consumir Streams com downsampling e eixo selecionável.');
$assert(str_contains($detailJs, "definition.unit === 's_per_100m' ? 20 : 90") && str_contains($detailJs, "(max-min)*.08"), 'escala do pace precisa impor domínio mínimo estável sem exagerar microvariações.');
$assert(str_contains($detailJs, 'gap_before_ms') && str_contains($detailJs, 'route_point_index'), 'gráficos/mapa precisam respeitar gaps conhecidos.');
$assert(str_contains($detailJs, 'data-chart-metric') && str_contains($detailJs, 'data-chart-cursor') && str_contains($detailJs, 'activity-v3-chart-tooltip') && !str_contains($detailJs, 'data-chart-toggle'), 'gráficos devem exibir todas as métricas disponíveis com cursor/tooltip sincronizado.');
$assert(str_contains($detailJs, '/api/atividade-splits.php') && str_contains($detailJs, '500') && str_contains($detailJs, '1000') && str_contains($detailJs, '5000'), 'splits precisam ser calculados no Core para distâncias predefinidas.');
$assert(str_contains($detailJs, 'data-split-distance-value') && str_contains($detailJs, 'data-split-preset') && !str_contains($detailJs, 'data-split-custom'), 'seletor de split deve usar um único campo numérico com atalhos, sem input visual duplicado.');
$assert(str_contains($detailJs, '/api/atividade-laps.php') && str_contains($detailJs, 'Nenhuma volta manual registrada.') && str_contains($detailJs, "row.origin==='manual'?'Manual'"), 'voltas precisam permanecer separadas de splits automáticos.');
$assert(str_contains($detailJs, '/api/atividade-analysis.php') && str_contains($detailJs, 'Melhores trechos desta atividade') && str_contains($detailJs, 'métrica esportiva, não médica'), 'Analysis precisa usar serviço canônico, diferenciar best efforts locais e evitar interpretação médica.');
$assert(str_contains($detailJs, 'findingDetail') && !str_contains($detailJs, 'item.formula'), 'Analysis não deve expor formula IDs internos ao usuário.');
$assert(str_contains($detailJs, '/user/zonas.php') && str_contains($detailJs, 'Configurar zonas'), 'Activity sem perfil aplicável precisa oferecer CTA simples de configuração.');
$assert(str_contains($detailCss, '@media') && str_contains($detailCss, '.activity-v3-table-wrap') && (str_contains($detailCss, 'overflow:auto') || str_contains($detailCss, 'overflow-x:auto')), 'layout V3 precisa tratar viewport estreito e tabelas extensas.');
$assert(str_contains($detailCss, '.activity-v3-chart') && str_contains($detailCss, '.activity-v3-map') && str_contains($detailCss, 'height:clamp(180px,25vw,250px)') && str_contains($detailCss, 'overflow:hidden'), 'preview precisa conter o mapa e manter gráficos no detalhe completo.');

foreach ([$streamsApi, $splitsApi, $lapsApi, $analysisApi] as $api) {
    $assert(str_contains($api, 'stridebr_require_login()'), 'endpoints Web analíticos precisam exigir sessão autenticada.');
    $assert(str_contains($api, "Cache-Control: private, no-store"), 'endpoints Web analíticos precisam impedir cache público.');
}
$assert(str_contains($streamsApi, 'activityStreamRead(') && str_contains($streamsApi, 'activityStreamEnsureMaterialized('), 'Streams Web precisa reutilizar o service canônico e lazy materialization.');
$assert(str_contains($splitsApi, 'activityStreamSplits('), 'Splits Web precisa reutilizar a split engine canônica.');
$assert(str_contains($lapsApi, 'activityStreamLaps('), 'Laps Web precisa reutilizar o domínio canônico de laps.');
$assert(str_contains($analysisApi, 'activityAnalysisCompute('), 'Analysis Web precisa reutilizar a engine canônica.');
$assert(str_contains($detailApi, 'activity_stream_service.php') && str_contains($presenter, 'activityStreamCapabilities(') && str_contains($presenter, "'stream_capabilities' => \$streamCapabilities"), 'Activity Detail precisa expor somente metadata leve de capabilities.');
$assert(str_contains($presenter, "'metrica_derivada'") && (str_contains($detailJs, 'derived_metric') || str_contains($detailJs, 'metrica_derivada')), 'comportamento pace/speed precisa usar metadata canônica da modalidade.');
$assert(str_contains($streamService, "'gap_before_ms'") && str_contains($streamService, "'route_point_index'"), 'read model de Streams precisa fornecer metadata suficiente para gaps sincronizados.');

$assert(str_contains($zonesPage, 'zoneProfileList(') && str_contains($zonesPage, 'zoneProfileSave(') && str_contains($zonesPage, 'zoneProfileDelete('), 'página de Zonas precisa operar sobre o service canônico.');
$assert(str_contains($zonesPage, 'O StrideBR não calcula zonas por idade.'), 'UI de zonas precisa deixar explícito que não inventa zonas por idade.');
$assert(str_contains($zonesPage, 'heart_rate') && str_contains($zonesPage, 'pace'), 'UI precisa suportar perfis manuais de FC e pace.');
$assert(str_contains($zonesJs, 'data-zone-add') && str_contains($zonesJs, 'data-zone-remove'), 'editor de zonas precisa permitir faixas variáveis sem JS inline extenso.');
$assert(str_contains($zonesPage, 'data-zones-page-help') && str_contains($zonesPage, 'data-zones-help-dialog') && str_contains($zonesJs, "showModal"), 'página de Zonas precisa expor ajuda contextual reutilizando o mesmo conteúdo da Activity.');
$assert(!str_contains($zonesPage, '220 -') && !str_contains($zonesJs, '220 -'), 'Web não pode adotar fórmula automática 220-idade.');

printf("✓ Web Activity Detail V3 static: %d assertions\n", $checks);
