<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);

$migration = $read('src/database/migrations/20260903_v1_rc.sql');
$model = $read('src/function/atividade_modelo.php');
$presenter = $read('src/function/atividade_presenter.php');
$routeLayout = $read('src/layout/activity_unit_route.php');
$modelPanels = $read('src/layout/activity_model_panels.php');
$sharedPanel = $read('src/layout/activity_model_panel_shared.php');
$editPage = $read('public/user/editatividade.php');
$activitiesPage = $read('public/user/atividades.php');
$activitiesJs = $read('public/assets/js/atividades.js');
$activitiesCss = $read('public/assets/css/atividades.css');
$sharingCss = $read('public/assets/css/activity-sharing.css');
$accountData = $read('src/function/account_data.php');
$privacy = $read('public/pages/legal/privacy.php');
$terms = $read('public/pages/legal/terms.php');
$spec = $read('docs/ACTIVITY_SHARING_V2.md');

$checks = [
    'migration cria rota por unidade com ownership estrutural' => str_contains($migration, 'CREATE TABLE IF NOT EXISTS rotas_unidades_atividade') && str_contains($migration, 'idunidade_atividade VARCHAR(21) NOT NULL UNIQUE REFERENCES unidades_atividade') && str_contains($migration, 'idregistro VARCHAR(21) NOT NULL REFERENCES registros_atividade'),
    'migration indexa rotas de unidades' => str_contains($migration, 'CREATE INDEX IF NOT EXISTS ix_rotas_unidades_registro'),
    'backend valida e salva rota por unidade' => str_contains($model, "\$unidadePayload['rota_coordenadas']") && str_contains($model, 'atividadeValidarRotaGeoJson($unitRouteRaw, false)') && str_contains($model, 'INSERT INTO rotas_unidades_atividade'),
    'backend carrega rotas de unidades em detalhe e lote' => substr_count($model, 'SELECT * FROM rotas_unidades_atividade WHERE idregistro') >= 2,
    'API expõe rota e métricas próprias de cada trecho' => str_contains($presenter, "'metricas_compartilhamento' => \$unitMetrics") && str_contains($presenter, "'rota' => \$unitRoute") && str_contains($presenter, '$unitValuesForShare'),
    'privacidade de rota é aplicada ao primeiro e último trecho' => str_contains($presenter, "\$index === 0 ? max(0, (int) (\$registro['ocultar_inicio_m']") && str_contains($presenter, "\$index === \$lastUnitIndex ? max(0, (int) (\$registro['ocultar_fim_m']"),
    'formulários possuem target de rota por unidade compartilhado entre create e edit' => str_contains($routeLayout, 'data-unit-route-editor') && str_contains($sharedPanel, 'atividadeRenderizarRotaUnidade') && str_contains($modelPanels, 'activity_model_panel_shared.php') && str_contains($editPage, 'activity_model_panel_shared.php'),
    'rota por unidade reutiliza o único workspace Leaflet sem mapa inline' => !str_contains($routeLayout, 'data-unit-route-map') && !str_contains($routeLayout, 'activity-unit-route-map') && str_contains($activitiesJs, "editor.addEventListener('activity:route-open-target'") && str_contains($activitiesJs, "routeEditor.dispatchEvent(new CustomEvent('activity:route-open-target'"),
    'editor dinâmico rebinda rotas por trecho após fetch' => str_contains($activitiesJs, 'bindUnitRouteEditors(panel)') && str_contains($activitiesJs, 'insertAdjacentHTML'),
    'compartilhamento guarda somente a última configuração realmente usada' => str_contains($activitiesJs, "stridebr.share.lastUsed.v1") && str_contains($activitiesJs, 'persistLastSharePreference(shareData)') && str_contains($activitiesJs, 'storeSharePreference(postSaveShareData, postSaveSharePreference)'),
    'primeiro compartilhamento usa Story com azul profundo e rota quando disponível' => str_contains($activitiesJs, "format: 'story'") && str_contains($activitiesJs, "preset: 'stats'") && str_contains($activitiesJs, "color: 'deep'") && str_contains($activitiesJs, "content: hasRoute && !sessionContext ? 'route' : 'sport'"),
    'prévia pós-salvamento reutiliza o renderizador completo' => str_contains($activitiesJs, 'const drawPostSaveShare = async activity =>') && str_contains($activitiesJs, 'await drawShareCardSurface(context, config, token)') && !str_contains($activitiesJs, 'const drawPostSaveStory = activity =>'),
    'atividade sem rota continua com botão compartilhar' => str_contains($activitiesJs, 'data-share-activity>${escapeHtml(tr(\'activity.detail_share\'))}</button>') && str_contains($activitiesJs, 'data-activity-share-data'),
    'share escolhe rota pelo conteúdo visual' => str_contains($activitiesPage, 'data-share-content-grid') && str_contains($activitiesPage, 'data-share-show="route"') && str_contains($activitiesJs, "{id: 'route', label: tr('activity.share.route')"),
    'share separa conteúdo e fundo' => str_contains($activitiesJs, "{id: 'stats', label: tr('activity.share.background_color_mode'") && str_contains($activitiesPage, 'data-share-background-block') && str_contains($activitiesJs, "let activeSharePresetId = SHARE_PRESET_FALLBACK"),
    'canvas sem rota não reserva a composição do mapa' => str_contains($activitiesJs, 'const hasVisibleRoute = Boolean(showRoute && Array.isArray(routeCoordinates) && routeCoordinates.length >= 2)') && str_contains($activitiesJs, 'if (hasVisibleRoute)'),
    'copiar card usa clipboard de imagem com fallback' => str_contains($activitiesPage, "data-copy-share><?php echo stridebr_e(stridebr_t('activity.copy_card')); ?></button>") && str_contains($activitiesJs, "new ClipboardItem({'image/png': blob})") && str_contains($activitiesJs, "tr('activity.share.clipboard_unavailable')") && str_contains($activitiesJs, "tr('activity.share.copy_error')"),
    'exportar rota gera PNG transparente' => str_contains($activitiesPage, 'data-export-route-png') && str_contains($activitiesJs, "'rotas-trechos-stridebr.png' : 'rota-stridebr.png'") && str_contains($activitiesJs, "tr('activity.share.route_exported')") && str_contains($activitiesJs, "tr('activity.share.routes_exported')"),
    'exportação de rota usa versão privada recortada' => str_contains($activitiesJs, 'routeCoordinatesForSharing(currentShareRouteData())') && str_contains($activitiesJs, 'getSelectedShareSegmentIndexes().map(index => routeCoordinatesForSharing'),
    'share oferece trechos juntos e separados' => str_contains($activitiesPage, 'value="together" data-share-segment-mode') && str_contains($activitiesPage, 'value="separate" data-share-segment-mode') && str_contains($activitiesJs, 'drawShareSegmentsOverview'),
    'share permite escolher trechos e prévia individual' => str_contains($activitiesPage, 'data-share-segment-list') && str_contains($activitiesPage, 'data-share-segment-preview') && str_contains($activitiesJs, 'getSelectedShareSegmentIndexes'),
    'download separado gera um PNG por trecho' => str_contains($activitiesJs, 'const selectedSegmentFiles = async') && str_contains($activitiesJs, 'files.forEach((file, index)') && str_contains($activitiesJs, 'downloadBlob(file, file.name)'),
    'web share envia múltiplos trechos quando suportado' => str_contains($activitiesJs, 'navigator.canShare?.({files})') && str_contains($activitiesJs, 'await navigator.share({files'),
    'detalhe mostra preview leve da rota de unidade' => str_contains($activitiesJs, 'const unitRoutePreviewHtml') && str_contains($activitiesJs, 'activity-unit-route-preview') && str_contains($activitiesCss, '.activity-unit-route-preview'),
    'preview principal usa contain calculado pela area real do stage' => str_contains($activitiesJs, 'const sharePreviewContainSize =') && str_contains($activitiesJs, 'const availableHeight = Math.max(0, stageRect.height') && str_contains($activitiesJs, 'shareCanvas.style.width = `${fitted.width}px`') && !str_contains($activitiesJs, 'shareCanvas.style.setProperty(\'width\', `${fitted.width}px`, \'important\')'),
    'preview principal reage a resize e mudancas de breakpoint' => str_contains($activitiesJs, 'new ResizeObserver(() => scheduleSharePreviewFit())') && str_contains($activitiesJs, "window.addEventListener('resize', () => { if (!shareModal?.hidden) syncShareViewportHeight() })") && str_contains($activitiesJs, 'window.setTimeout(() => {') && str_contains($activitiesJs, 'fitSharePreviewCanvas()'),
    'preview mobile reserva enquadramento em telas baixas' => str_contains($sharingCss, '.activity-share-preview-window') && str_contains($sharingCss, 'flex: 0 0 clamp(280px, 46dvh, 390px)') && str_contains($sharingCss, '@media (max-height: 520px)'),
    'mobile share usa bottom sheet' => str_contains($sharingCss, '.activity-share-modal.is-customizing .activity-share-sidebar') && str_contains($sharingCss, 'max-height: min(82dvh, 720px)') && str_contains($sharingCss, 'border-radius: var(--radius-modal) var(--radius-modal) 0 0'),
    'bottom sheet respeita reduced motion' => str_contains($sharingCss, '@media (prefers-reduced-motion: reduce)') && str_contains($sharingCss, '.activity-share-customize-backdrop'),
    'exportação da conta inclui unidades e rotas por unidade' => str_contains($accountData, "accountTableExists(\$pdo, 'unidades_atividade')") && str_contains($accountData, "accountTableExists(\$pdo, 'rotas_unidades_atividade')"),
    'legais cobrem rotas por trecho e compartilhamento sem rota' => str_contains($privacy, 'cada unidade também pode ter um traçado próprio') && str_contains($privacy, 'O compartilhamento pode ser feito sem rota') && str_contains($terms, 'A rota é opcional no cartão'),
    'documentação estável da rodada existe' => str_contains($spec, 'Trechos — todos juntos') && str_contains($spec, 'Trechos — separados'),
];

$failed = [];
foreach ($checks as $label => $ok) {
    if (!$ok) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, "Falhas no compartilhamento v2:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo '✓ activity sharing v2 static: ' . count($checks) . " assertions\n";
