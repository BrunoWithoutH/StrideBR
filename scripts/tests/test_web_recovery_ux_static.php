<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$count = 0;
$assert = static function (bool $ok, string $message) use (&$count): void {
    $count++;
    if (!$ok) {
        fwrite(STDERR, "Web recovery UX static failed: {$message}\n");
        exit(1);
    }
};

$home = $read('public/home.php');
$homeCss = $read('public/assets/css/dashboard-v2.css');
$activitiesPage = $read('public/user/atividades.php');
$activitiesJs = $read('public/assets/js/atividades.js');
$activitiesCss = $read('public/assets/css/atividades.css');
$detailJs = $read('public/assets/js/activity-detail-v3.js');
$detailCss = $read('public/assets/css/activity-detail-v3.css');
$mapJs = $read('public/assets/js/web-map.js');
$routeEditor = $read('src/layout/activity_route_editor.php');
$unitHelpers = $read('src/layout/activity_unit_helpers.php');
$routesPage = $read('public/user/rotas.php');
$progressPage = $read('public/user/progresso.php');
$comparisonPage = $read('public/user/comparar-atividades.php');
$signupPage = $read('public/signup.php');
$onboardingPage = $read('public/user/onboarding.php');
$settingsPage = $read('public/user/settings.php');
$goalsPage = $read('public/user/metas.php');
$exchangeJs = $read('public/assets/js/activity-exchange.js');
$pacerService = $read('src/function/pacer_service.php');
$streamService = $read('src/function/activity_stream_service.php');
$pacerMigration = $read('src/database/migrations/20260915_activity_streams_analysis_pacer_v1.sql');

$assert(str_contains($home, 'dashboard-tomorrow-context') && str_contains($home, "stridebr_t('common.tomorrow')"), 'Home precisa incorporar amanhã ao card principal com copy localizada.');
$assert(!str_contains($home, '>Próximo treino<') && !str_contains($home, 'data-dashboard-module="upcoming"'), 'Home não pode reintroduzir card redundante de próximo treino.');
$assert(str_contains($home, 'dashboard-home-rail') && str_contains($home, 'Próximas competições') && str_contains($home, 'count($nextCompetitions) >= 3'), 'Home precisa limitar competições no right rail.');
$assert(str_contains($home, 'dashboard-period-strip') && str_contains($home, 'data-dashboard-module="recent"') && !str_contains($home, 'data-home-last-map'), 'Home precisa ter faixa 28 dias e lista recente sem mapa da última atividade.');
$assert(str_contains($homeCss, 'grid-template-columns:minmax(0,1fr) minmax(280px,320px)') && str_contains($homeCss, '@media(max-width:900px){.dashboard-home-layout{grid-template-columns:1fr}'), 'Home precisa usar main + rail no desktop e fluxo único em telas menores.');
$assert(!str_contains($home, "\$resumo['elevacao_m']"), 'Resumo semanal da Home não deve expor elevação.');

$assert(str_contains($activitiesCss, '.activity-history-filter-panel{position:fixed') && str_contains($activitiesCss, 'width:min(760px,calc(100vw - 32px))'), 'Popover de filtros deve ser fixado à viewport, não cortado pelo workspace.');
$assert(str_contains($activitiesJs, 'getBoundingClientRect()') && str_contains($activitiesJs, 'const margin = 16') && str_contains($activitiesJs, 'Math.max(margin, window.innerWidth - width - margin)'), 'Posicionamento do filtro precisa aplicar clamp horizontal.');
$assert(str_contains($activitiesJs, "historyFilterMenu?.addEventListener('toggle'") && str_contains($activitiesJs, "window.addEventListener('resize', positionHistoryFilterPanel)") && str_contains($activitiesJs, "window.addEventListener('scroll', positionHistoryFilterPanel"), 'Filtro deve reposicionar em abertura, resize e scroll.');
$assert(str_contains($activitiesCss, '@media(max-width:760px)') && str_contains($activitiesCss, '.activity-history-filter-panel{position:fixed'), 'Mobile precisa preservar sheet/popover próprio.');

$previewStart = strpos($detailJs, "if (mode === 'preview')");
$detailStart = strpos($detailJs, 'const tabs = tabsFor(activity)', $previewStart ?: 0);
$previewBlock = $previewStart !== false && $detailStart !== false ? substr($detailJs, $previewStart, $detailStart - $previewStart) : '';
$assert($previewBlock !== '' && str_contains($detailJs, 'visibleMetrics(activity.metricas || []).slice(0, 4)') && !str_contains($previewBlock, 'data-open-full-activity-details') && str_contains($activitiesPage, 'data-detail-open-full'), 'Preview precisa limitar métricas e manter Abrir detalhes no header da superfície.');
$assert(!str_contains($previewBlock, 'activity-v3-tabs') && !str_contains($previewBlock, 'data-activity-v3-lazy'), 'Preview lateral não pode conter tabs ou painéis analíticos.');
$assert(str_contains($activitiesJs, "mode: detailExpanded ? 'detail' : 'preview'") && str_contains($activitiesJs, "event.target.closest('[data-open-full-activity-details]')"), 'Abrir detalhes precisa rerenderizar a atividade em modo completo.');
$assert(str_contains($detailJs, "{id:'summary',label:'Resumo'}") && str_contains($detailJs, "id:'charts',label:'Gráficos'") && str_contains($detailJs, "id:'analysis',label:'Análise'"), 'Detalhe completo precisa preservar tabs condicionais.');

$assert(str_contains($activitiesJs, "event.target.closest('[data-share-activity]')") && str_contains($activitiesJs, 'if (!shareData && activeDetailActivity)') && str_contains($activitiesJs, 'shareModal.hidden = false'), 'Share do preview precisa abrir mesmo após rerender V3.');
$assert(str_contains($activitiesJs, "shareModal.querySelector('.activity-share-panel > header [data-close-share]')?.focus()"), 'Share composer precisa receber foco ao abrir.');

$assert(str_contains($detailCss, 'height:clamp(180px,25vw,250px)') && str_contains($detailCss, 'overflow:hidden') && str_contains($detailCss, '.leaflet-container'), 'Mapa do preview precisa ficar contido e compacto.');
$assert(str_contains($mapJs, 'const mountMany = async') && str_contains($mapJs, 'normalizedRoutes.forEach') && str_contains($mapJs, 'normalizedRoutes.flatMap') && str_contains($mapJs, 'map.fitBounds(bounds'), 'Mapa compartilhado precisa desenhar rotas separadas e agregar bounds.');
$assert(str_contains($mapJs, 'state.resizeObserver = new ResizeObserver(scheduleFit)') && str_contains($mapJs, 'map.invalidateSize') && str_contains($mapJs, 'destroy(element)'), 'Leaflet precisa reagir a resize e destruir a instância anterior.');
$assert(str_contains($detailJs, 'const unitRoutes = collect') && str_contains($detailJs, 'return unitRoutes.length ? unitRoutes : collect([activity?.rota])'), 'Rotas por trecho devem vencer a geometria top-level para não duplicar desenho.');

$assert(str_contains($detailJs, "label:'Elevação'") && str_contains($detailJs, 'Ganho +'), 'Activity Detail precisa permitir elevação quando houver stream ou rota válida.');
$assert(!str_contains($routeEditor, 'data-route-elevation') && str_contains($unitHelpers, 'data-web-hidden-elevation-field'), 'Editor de rota deve esconder elevação sem apagar o campo persistido.');
$assert(str_contains($exchangeJs, 'exchange-preview-metrics'), 'Importação deve manter preview de métricas sem inventar elevação ausente.');
$assert(!str_contains($signupPage, "'elevacao' => [stridebr_t('onboarding.tracking.elevation')") && !str_contains($onboardingPage, "'elevacao' => [stridebr_t('onboarding.tracking.elevation')") && !str_contains($settingsPage, "'elevacao' => [stridebr_t('settings.track_elevation')"), 'Cadastro, onboarding e configurações não devem oferecer elevação como preferência visível.');
$assert(str_contains($onboardingPage, "\$hiddenTracking = in_array('elevacao'") && str_contains($settingsPage, "\$hiddenTrainingTracking = in_array('elevacao'"), 'Preferências legadas ocultas de elevação devem ser preservadas ao salvar.');
$assert(str_contains($goalsPage, "(string) (\$meta['metrica'] ?? '') !== 'elevacao'") && str_contains($progressPage, "if ((string) (\$goal['metrica'] ?? '') === 'elevacao') continue;"), 'Metas legadas de elevação não devem reaparecer nas superfícies Web.');
$assert(str_contains($comparisonPage, "preg_match('/eleva|desn[ií]vel|relevo|altitude|ganho/i'"), 'Comparação mantém seu filtro próprio sem alterar dados persistidos.');

$assert(str_contains($pacerMigration, 'ck_pacer_segment_instruction_metadata') && str_contains($pacerMigration, "jsonb_typeof(instruction_metadata) = 'object'"), 'Schema do Pacer precisa exigir instruction_metadata como objeto.');
$assert(str_contains($pacerService, "activityStreamEncodeJsonObject(\$segment['instruction_metadata'])") && str_contains($streamService, 'json_encode($value === [] ? (object) [] : $value'), 'Pacer precisa persistir metadata vazia como JSON object, não array.');

printf("✓ web recovery UX static: %d assertions\n", $count);
