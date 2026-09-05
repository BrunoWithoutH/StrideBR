<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Activity workspace polish static failed: {$message}\n");
        exit(1);
    }
};

$page = $read('public/user/atividades.php');
$js = $read('public/assets/js/atividades.js');
$css = $read('public/assets/css/atividades.css');
$sharingCss = $read('public/assets/css/activity-sharing.css');
$presenter = $read('src/function/atividade_presenter.php');
$library = $read('public/user/biblioteca.php');
$libraryJs = $read('public/assets/js/library.js');
$libraryCss = $read('public/assets/css/cronogramas.css');
$workoutAlias = $read('public/user/bibliotecatreinos.php');
$exerciseAlias = $read('public/user/bibliotecaexercicios.php');
$exchangeJs = $read('public/assets/js/activity-exchange.js');
$editorApi = $read('public/api/atividade-editor-detalhes.php');
$smartTitle = $read('src/layout/activity_smart_title.php');
$routePrivacy = $read('src/layout/activity_route_privacy.php');
$logDetails = $read('src/layout/activity_log_details.php');

$assert(str_contains($page, 'value="compact" data-share-composition') && !str_contains($page, 'value="compactWide" data-share-format'), 'Compacto precisa existir como composição, não formato.');
$assert(str_contains($js, "const isWide = format === 'square'") && str_contains($js, "const layoutHeight = format === 'story' ? Math.min(height, 1350) : height"), 'Compacto deve adaptar para grade no Quadrado e vertical centralizado no Story.');
$assert(str_contains($js, 'strokeOnly = false') && str_contains($js, "modalidade_icone: activity?.modalidade_icone"), 'card compacto precisa usar o ícone real da modalidade como contorno.');
$assert(str_contains($presenter, "'modalidade_icone' => function_exists('stridebr_sport_icon_id')"), 'API de detalhe precisa informar o Sporticon da atividade.');
$assert(!str_contains($js, 'shareRoundedRect(context, x, y, tileWidth, tileHeight, tileRadius)') && str_contains($js, 'const metricLayout = shareMetricLayout(metrics.length, side, contentWidth, metricTop, metricRowHeight)') && str_contains($js, 'const metricRowHeight = isWide ? 194 : 174'), 'compactos precisam usar a composição dinâmica de métricas sem tiles.');
$assert(str_contains($js, "supportsMap: false") && str_contains($js, "definition.supportsMap && hasRoute") && str_contains($js, "showMapBase: Boolean(override.showMapBase ?? (preset.id === 'map' && content === 'route'))"), 'mapa-base deve depender da composição/rota e desaparecer no Compacto.');
$assert(str_contains($sharingCss, '.activity-share-format-rail') && str_contains($sharingCss, '.activity-share-composition-options'), 'formato e composição precisam de controles separados.');
$assert(str_contains($js, 'data-route-data>${JSON.stringify(activity.rota)') && str_contains($js, 'activity-detail-route-preview') && str_contains($js, 'shareRouteSilhouetteSvg(activity.rota)'), 'prévia do Histórico precisa usar silhueta SVG leve da rota.');
$assert(!str_contains(substr($js, strpos($js, 'const renderDetail ='), strpos($js, 'const closeActivityDetails =') - strpos($js, 'const renderDetail =')), 'initializeDetailRoute(detailDrawer)') && str_contains($js, 'drawElevationProfile(elevationSvg'), 'prévia rápida não deve inicializar Leaflet; perfil de elevação continua leve.');
$assert(str_contains($library, 'data-library-view="treinos"') && str_contains($library, 'data-library-view="exercicios"'), 'Biblioteca precisa renderizar Treinos e Exercícios no mesmo shell.');
$assert(str_contains($libraryJs, 'const setLibraryTab =') && str_contains($libraryJs, "history.pushState({libraryTab: tab}"), 'tabs da Biblioteca precisam trocar via JS e preservar history/URL.');
$assert(str_contains($libraryCss, '@keyframes library-tab-enter'), 'troca de aba da Biblioteca precisa ter transição curta.');
$assert(str_contains($workoutAlias, "require __DIR__ . '/biblioteca.php';") && str_contains($exerciseAlias, "require __DIR__ . '/biblioteca.php';"), 'URLs antigas da Biblioteca precisam continuar compatíveis.');
$assert(substr_count($page, 'data-activity-tool=') >= 3 && str_contains($page, 'data-activity-tool-overlay'), 'Atividades precisa abrir Comparar/Importar no próprio shell.');
$assert(str_contains($js, 'new DOMParser().parseFromString') && str_contains($js, 'const openActivityTool = async'), 'ferramentas de Atividades precisam carregar subviews sem reload completo.');
$assert(str_contains($js, "window.addEventListener('popstate'") && str_contains($js, "url.searchParams.set('tool', type)"), 'subviews de Atividades precisam manter navegação do navegador.');
$assert(str_contains($exchangeJs, 'window.StrideBRActivityExchangeInit = stridebrInitActivityExchange') && str_contains($exchangeJs, "page.dataset.exchangeBound === '1'"), 'Importar/exportar precisa poder inicializar depois de injetado na página sem duplicar listeners.');
$assert(str_contains($exchangeJs, "stridebr:activity-imported") && str_contains($exchangeJs, 'notifyActivityImports') && str_contains($exchangeJs, 'historyCachePrefix'), 'importação concluída precisa invalidar cache e avisar o histórico sem recarregar a página.');
$assert(str_contains($js, "window.addEventListener('stridebr:activity-imported'") && str_contains($js, 'loadHistory({background: true, preserveDetail: true})'), 'histórico precisa atualizar sozinho após importação mantendo a página aberta.');
$assert(str_contains($exchangeJs, 'stridebr.activity.import.pendingRefresh.v1') && str_contains($js, 'consumePendingImportRefresh') && str_contains($js, "window.addEventListener('pageshow'"), 'histórico precisa atualizar ao voltar da importação, inclusive via bfcache.');
$assert(str_contains($editorApi, "\$_GET['model']") && str_contains($editorApi, 'atividadeBuscarCamposModelos($pdo, [$requestedModel])') && str_contains($js, 'const editorDetailsRequests = new Map()') && str_contains($js, 'loadEditorDetails(modelSelect.value)'), 'editor de atividade precisa carregar apenas o modelo selecionado e buscar outros sob demanda.');
$assert(str_contains($css, '.activity-tool-panel{') && str_contains($css, '.activity-tool-overlay.is-open'), 'subviews de Atividades precisam ter painel e transição próprios.');

$assert(str_contains($page, 'activity_smart_title.php') && str_contains($smartTitle, 'data-activity-title-preview') && str_contains($smartTitle, 'data-edit-activity-title'), 'registro manual precisa usar título automático com edição sob demanda.');
$assert(str_contains($page, 'activity_log_details.php') && str_contains($logDetails, 'data-effort-range') && str_contains($logDetails, 'data-clear-effort') && str_contains($logDetails, 'data-toggle-log-detail="equipment"'), 'esforço precisa ficar visível em slider e equipamento deve continuar sob demanda.');
$assert(str_contains($page, 'activity_route_privacy.php') && str_contains($routePrivacy, 'data-route-privacy-fields') && str_contains($routePrivacy, "$" . "routePrivacyHasRoute ? '' : ' hidden'"), 'privacidade da rota precisa ficar oculta enquanto não houver rota.');
$assert(str_contains($smartTitle, 'data-activity-summary hidden') && !str_contains($page, 'Preencha distância e duração para calcular ritmo ou velocidade.'), 'resumo vazio não deve ocupar espaço no registro manual.');
$assert(str_contains($js, "summaryWrap.hidden = parts.length === 0") && str_contains($js, "editor.dataset.routeHasPoints = route ? '1' : '0'"), 'resumo e opções de rota precisam responder ao conteúdo real.');

// Mobile modal layering must keep activity actions above persistent bottom navigation/tools.
$uiRefreshCss = file_get_contents(__DIR__ . '/../../public/assets/css/ui-refresh.css');
$assert(str_contains($uiRefreshCss, '--ui-z-modal: var(--z-modal);'), 'Modal layer should sit above persistent mobile chrome');
$assert(str_contains($uiRefreshCss, 'html.activity-editor-open .mobile-bottom-nav'), 'Activity editor should hide mobile bottom navigation while open');
$assert(str_contains($uiRefreshCss, 'html.activity-editor-open .floating-utility-dock'), 'Activity editor should hide floating timer/tools while open');
$assert(str_contains($uiRefreshCss, '.activity-editor .activity-form-actions'), 'Activity editor actions should account for mobile safe area');

printf("✓ activity workspace polish static: %d assertions\n", $checks);
