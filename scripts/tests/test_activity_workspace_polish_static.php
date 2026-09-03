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
$presenter = $read('src/function/atividade_presenter.php');
$library = $read('public/user/biblioteca.php');
$libraryJs = $read('public/assets/js/library.js');
$libraryCss = $read('public/assets/css/cronogramas.css');
$workoutAlias = $read('public/user/bibliotecatreinos.php');
$exerciseAlias = $read('public/user/bibliotecaexercicios.php');
$exchangeJs = $read('public/assets/js/activity-exchange.js');
$editorApi = $read('public/api/atividade-editor-detalhes.php');

$assert(str_contains($page, 'value="compactWide" data-share-format') && str_contains($page, 'share-format-thumb is-compact-wide'), 'compartilhamento precisa ter compacto vertical e horizontal.');
$assert(str_contains($js, "compactWide: {id: 'compactWide', label: 'Compacto horizontal', width: 1080, height: 1350}"), 'compacto horizontal precisa usar canvas retrato 4:5.');
$assert(str_contains($js, 'strokeOnly = false') && str_contains($js, "modalidade_icone: activity?.modalidade_icone"), 'card compacto precisa usar o ícone real da modalidade como contorno.');
$assert(str_contains($presenter, "'modalidade_icone' => function_exists('stridebr_sport_icon_id')"), 'API de detalhe precisa informar o Sporticon da atividade.');
$assert(!str_contains($js, 'shareRoundedRect(context, x, y, tileWidth, tileHeight, tileRadius)') && str_contains($js, 'const metricColumns = isWide ? Math.min(2, Math.max(1, metrics.length)) : 1') && str_contains($js, 'const metricRowHeight = isWide ? 194 : 174'), 'compactos precisam separar métricas horizontais 2 × 2 e verticais em uma coluna.');
$assert(str_contains($js, "const mapCompatible = singleEditor && activeShareContentId === 'route' && hasRoute && !compact") && str_contains($js, "showMapBase: Boolean(override.showMapBase ?? (preset.id === 'map' && content === 'route' && !compact))"), 'compactos não devem usar mapa-base e o mapa deve depender de conteúdo de rota.');
$assert(str_contains($css, '.activity-share-preview-shell.is-compact-wide canvas[data-share-canvas]{aspect-ratio:4/5}') && str_contains($css, '.activity-share-format-rail{'), 'preview horizontal precisa continuar retrato 4:5 e usar seletor visual vertical.');
$assert(str_contains($js, 'data-route-data>${JSON.stringify(activity.rota)') && str_contains($js, 'const waitForVisibleRouteBox = async'), 'detalhe precisa fornecer dados da rota e esperar dimensões válidas antes do mapa.');
$assert(str_contains($js, 'new ResizeObserver(refreshRouteLayout)') && str_contains($js, 'invalidateSize({pan: false})'), 'mapa do detalhe precisa reagir a resize/abertura tardia.');
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

$assert(str_contains($page, 'data-activity-title-preview') && str_contains($page, 'data-edit-activity-title'), 'registro manual precisa usar título automático com edição sob demanda.');
$assert(str_contains($page, 'data-effort-range') && str_contains($page, 'data-clear-effort') && str_contains($page, 'data-toggle-log-detail="equipment"'), 'esforço precisa ficar visível em slider e equipamento deve continuar sob demanda.');
$assert(str_contains($page, 'data-route-privacy-fields') && str_contains($page, "$" . "formRoute !== '' ? '' : ' hidden'"), 'privacidade da rota precisa ficar oculta enquanto não houver rota.');
$assert(str_contains($page, 'data-activity-summary hidden') && !str_contains($page, 'Preencha distância e duração para calcular ritmo ou velocidade.'), 'resumo vazio não deve ocupar espaço no registro manual.');
$assert(str_contains($js, "summaryWrap.hidden = parts.length === 0") && str_contains($js, "editor.dataset.routeHasPoints = route ? '1' : '0'"), 'resumo e opções de rota precisam responder ao conteúdo real.');

// Mobile modal layering must keep activity actions above persistent bottom navigation/tools.
$uiRefreshCss = file_get_contents(__DIR__ . '/../../public/assets/css/ui-refresh.css');
$assert(str_contains($uiRefreshCss, '--ui-z-modal: 7000;'), 'Modal layer should sit above persistent mobile chrome');
$assert(str_contains($uiRefreshCss, 'html.activity-editor-open .mobile-bottom-nav'), 'Activity editor should hide mobile bottom navigation while open');
$assert(str_contains($uiRefreshCss, 'html.activity-editor-open .floating-utility-dock'), 'Activity editor should hide floating timer/tools while open');
$assert(str_contains($uiRefreshCss, '.activity-editor .activity-form-actions'), 'Activity editor actions should account for mobile safe area');

printf("✓ activity workspace polish static: %d assertions\n", $checks);
