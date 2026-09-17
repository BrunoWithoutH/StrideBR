<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$count = 0;
$assert = static function (bool $ok, string $message) use (&$count): void {
    $count++;
    if (!$ok) {
        fwrite(STDERR, "Activities UX recovery static failed: {$message}\n");
        exit(1);
    }
};

$page = $read('public/user/atividades.php');
$activitiesJs = $read('public/assets/js/atividades.js');
$activitiesCss = $read('public/assets/css/atividades.css');
$detailJs = $read('public/assets/js/activity-detail-v3.js');
$detailCss = $read('public/assets/css/activity-detail-v3.css');
$mapJs = $read('public/assets/js/web-map.js');

$previewStart = strpos($detailJs, "if (mode === 'preview')");
$detailStart = strpos($detailJs, 'const tabs = tabsFor(activity)', $previewStart ?: 0);
$preview = $previewStart !== false && $detailStart !== false ? substr($detailJs, $previewStart, $detailStart - $previewStart) : '';

$assert($preview !== '' && str_contains($detailJs, 'visibleMetrics(activity.metricas || []).slice(0, 4)'), 'Preview precisa limitar as métricas essenciais.');
$assert(str_contains($preview, 'data-share-activity') && str_contains($preview, 'data-open-full-activity-details'), 'Preview precisa expor Compartilhar e Abrir detalhes.');
$assert(!str_contains($preview, 'data-delete-activity') && !str_contains($preview, 'activity-v3-tabs'), 'Preview não pode expor excluir ou tabs analíticas.');
$assert(str_contains($page, 'class="activity-detail-panel" role="region"') && !str_contains($page, 'class="activity-detail-panel" role="dialog"'), 'Activity Detail precisa ser região da página, não dialog modal.');
$assert(str_contains($page, 'data-activities-history-view') && str_contains($page, 'data-activity-detail-view') && str_contains($activitiesJs, "targetHost = detailExpanded ? activityDetailView : detailPreviewHost") && str_contains($activitiesCss, '.activities-page.is-detail-mode'), 'Detail full-page precisa substituir toda a main específica de Activities sem overlay fixo.');
$assert(str_contains($activitiesJs, 'detailViewUrl') && str_contains($activitiesJs, "history.pushState") && str_contains($activitiesJs, "window.addEventListener('popstate'"), 'Detail precisa integrar pushState e popstate.');
$assert(str_contains($activitiesJs, 'syncActivityDetailFromLocation') && preg_match("/url\.searchParams\.set\('view',\s*'detail'\)/", $activitiesJs) === 1, 'Detail precisa suportar deep link por URL.');
$assert(str_contains($activitiesJs, 'historyReturnScrollY') && str_contains($activitiesJs, 'window.scrollTo'), 'Voltar do Detail precisa preservar a posição do histórico.');
$assert(str_contains($page, 'data-detail-badges') && preg_match("/(?:badges|items)\.push\('Rota'\)/", $activitiesJs) === 1, 'Badges relevantes precisam ficar junto à identidade da Activity.');
$assert(str_contains($page, 'data-detail-full-actions') && str_contains($page, 'data-share-activity') && str_contains($page, 'data-detail-edit') && str_contains($page, 'activity-detail-header-menu'), 'Detail precisa agrupar Share, Editar e menu secundário no header.');
$assert(str_contains($page, 'data-detail-delete') && str_contains($page, 'is-danger'), 'Excluir precisa ficar como ação danger no menu secundário.');
$assert(str_contains($detailJs, 'routeHtml(activity,{saveAction:true,contextRail})') && str_contains($detailJs, 'activity-v3-route-save'), 'Salvar rota precisa pertencer ao bloco Percurso.');

$assert(str_contains($detailCss, '.activity-map-frame') && str_contains($detailCss, 'isolation:isolate') && str_contains($detailCss, 'contain:paint'), 'Frame do mapa precisa criar stacking context local.');
$assert(str_contains($mapJs, 'mountMany') && str_contains($mapJs, 'normalizedRoutes.forEach') && str_contains($mapJs, 'normalizedRoutes.flatMap'), 'Mapa precisa preservar múltiplas polylines e bounds agregados.');
$assert(str_contains($detailJs, 'return unitRoutes.length ? unitRoutes : collect([activity?.rota])'), 'Rotas de trechos precisam evitar duplicação da rota top-level.');

$assert(str_contains($detailJs, 'const metricDefinitions') && str_contains($detailJs, "['pace','speed','heart_rate','cadence','power','temperature']"), 'Charts devem usar somente séries esportivas graphable existentes.');
$assert(!preg_match("/available\.has\('(altitude|elevation|grade)'\)/", $detailJs), 'Charts Web não podem habilitar altitude, elevação ou grade.');
$assert(str_contains($detailJs, 'activity-v3-chart-list') && str_contains($detailJs, 'is-primary') && str_contains($detailJs, 'primary?180:110'), 'Charts precisam usar small multiples com série principal maior.');
$assert(str_contains($detailJs, "data-chart-axis=\"distance\"") && str_contains($detailJs, "data-chart-axis=\"time\"") && str_contains($detailJs, "if(axis==='distance' && !available.includes('distance')) resolvedAxis='time'"), 'Eixo deve alternar Distância/Tempo e cair para Tempo sem distância.');
$assert(str_contains($detailJs, 'data-chart-cursor') && str_contains($detailJs, 'panel.querySelectorAll(\'[data-chart-cursor]\')') && str_contains($detailJs, 'setPointerCapture'), 'Crosshair precisa ser compartilhado e aceitar pointer/touch.');
$assert(str_contains($detailJs, 'gap_before_ms') && str_contains($detailJs, 'groups.push(current)'), 'Gaps precisam quebrar a linha do gráfico.');
$assert(str_contains($detailJs, 'definition.invert ? ratio : 1-ratio'), 'Pace precisa manter orientação visual invertida.');
$assert(str_contains($detailJs, 'new AbortController()') && str_contains($detailJs, 'timeout = 12000') && str_contains($detailJs, 'state.controllers'), 'Lazy loading precisa ter abort e timeout explícitos.');
$assert(str_contains($detailJs, 'tabStates:new Map') && !str_contains($detailJs, 'state.loaded.add'), 'Tabs precisam usar estados explícitos sem marcar loaded antes da request.');
$assert(str_contains($detailJs, "state.tabStates.set(id,'loading')") && str_contains($detailJs, "state.tabStates.set(id,result||'loaded')"), 'Estado loading só pode virar loaded/empty/error após o loader terminar.');
$assert(substr_count($detailJs, 'data-retry-tab=') >= 5 && str_contains($detailJs, '{force:true}'), 'Erros lazy precisam oferecer retry que força nova request.');
$assert(str_contains($detailJs, 'state.cache.has(cacheKey)') && str_contains($detailJs, 'state.cache.set(cacheKey,data)'), 'Streams precisam ter cache por eixo/resolução durante o Detail.');
$assert(str_contains($detailJs, 'state.destroyed') && str_contains($detailJs, 'controller.abort()'), 'Troca/fechamento de Activity precisa invalidar requests antigos.');
$assert(str_contains($detailJs, 'Nenhum dado contínuo disponível para gráficos nesta atividade.'), 'Charts sem série útil precisam terminar em empty state.');

$assert(str_contains($detailJs, '<th>FC méd.</th><th>FC máx.</th><th>Cadência</th>') && !str_contains($detailJs, '<th>Elevação</th>'), 'Splits devem permanecer compactos e sem elevação.');
$assert(str_contains($detailJs, '<th>FC méd.</th><th>Origem</th>') && str_contains($detailJs, 'Nenhuma volta manual registrada.'), 'Voltas devem ser compactas e ter empty state objetivo.');
$assert(str_contains($detailJs, 'Zonas de frequência cardíaca') && str_contains($detailJs, 'Zonas de pace') && str_contains($detailJs, 'zone.percentage'), 'Zonas precisam separar FC/Pace e mostrar percentual.');
$assert(str_contains($detailJs, 'Padrão de ritmo') && str_contains($detailJs, 'Leituras da atividade') && str_contains($detailJs, 'Melhores trechos desta atividade'), 'Analysis precisa ter hierarquia de insight, métricas e melhores trechos.');
$assert(str_contains($detailJs, 'Planejado × realizado') && str_contains($detailJs, 'getPlannedActual'), 'Planejado × realizado deve aparecer apenas quando houver dados.');

$assert(str_contains($activitiesJs, "black: Object.freeze({id: 'black', base: '#000000'") && str_contains($activitiesJs, "white: Object.freeze({id: 'white', base: '#FFFFFF'"), 'Share precisa restaurar Preto e Branco reais.');
$assert(str_contains($activitiesJs, "if (id === 'light') return 'white'") && !str_contains($activitiesJs, "if (id === 'black') return 'dark'"), 'Normalização do Share precisa preservar black e migrar light legado para white.');
$assert(str_contains($activitiesJs, 'if (definition.solid) return'), 'Black/White sólidos não podem receber gradient decorativo.');
$assert(substr_count($activitiesJs, "color === 'white'") >= 2 && str_contains($activitiesJs, 'shareLogoDark'), 'White precisa usar foreground/logo escuros independente do tema do site.');
$assert(str_contains($page, 'value="black" data-share-background-color') && str_contains($page, 'value="white" data-share-background-color'), 'Composer precisa expor swatches Preto e Branco.');
$assert(substr_count($activitiesJs, 'drawShareCardSurface(') >= 4, 'Preview e export precisam compartilhar o mesmo renderer de card.');
$assert(str_contains($activitiesJs, 'SHARE_PRESET_COLORS_KEY') && str_contains($activitiesJs, 'normalizeShareBackgroundColor(value)'), 'Persistência do Share precisa aceitar a paleta normalizada.');

$assert(str_contains($detailJs, 'role="tablist"') && str_contains($detailJs, 'role="tab"') && str_contains($detailJs, 'role="tabpanel"') && str_contains($detailJs, 'aria-selected'), 'Tabs precisam manter semântica ARIA correta.');
$assert(str_contains($detailJs, "['ArrowLeft','ArrowRight'].includes(event.key)") || str_contains($detailJs, "event.key!=='ArrowLeft'&&event.key!=='ArrowRight'"), 'Tabs devem aceitar setas esquerda/direita no teclado.');
$assert(str_contains($detailJs, 'isElevationPresentation') && !preg_match('/(?:label\s*:\s*[\"\'](?:Altitude|Elevação|Perfil de elevação|Ganho de elevação|Perda de elevação)|<th>(?:Altitude|Elevação|Relevo)<\/th>)/iu', $detailJs), 'Detail V3 não pode renderizar labels visíveis de elevação.');

printf("✓ Activities UX recovery static: %d assertions\n", $count);
