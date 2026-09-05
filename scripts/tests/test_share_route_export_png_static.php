<?php
$root = dirname(__DIR__, 2);
$html = file_get_contents($root . '/public/user/atividades.php');
$css = file_get_contents($root . '/public/assets/css/activity-sharing.css');
$js = file_get_contents($root . '/public/assets/js/atividades.js');
$tests = [
    ['modal hidden', str_contains($html, 'data-route-export-sheet hidden') && str_contains($css, '.activity-share-route-export-sheet[hidden]')],
    ['modal dialog', str_contains($html, 'activity-share-route-export-dialog') && str_contains($html, 'aria-modal="true"')],
    ['palette', substr_count($html, 'data-route-export-color') >= 7],
    ['width', str_contains($html, 'min="55" max="180" step="5" value="100" data-route-export-width')],
    ['preview', str_contains($html, 'width="640" height="640" data-route-export-preview') && str_contains($js, 'syncRouteExportPreview')],
    ['same renderer', substr_count($js, 'drawRouteExportSurface(') >= 2 && str_contains($js, 'resolveShareRouteVisualStyle')],
    ['export preserves route core', !str_contains(substr($js, strpos($js, 'const drawRouteExportSurface ='), strpos($js, 'const syncRouteExportPreview =') - strpos($js, 'const drawRouteExportSurface =')), 'coreColor: color')],
    ['default thicker', str_contains($js, 'storyWidth: 19.5') && str_contains($js, 'standardWidth: 15')],
    ['no database persistence', !str_contains($js, 'STRIDEBR_ROUTE_EXPORT') && !str_contains($html, 'name="route_export')],
    ['performance cache', str_contains($js, 'shareRouteCoordinateCache = new WeakMap()') && str_contains($js, 'sharePresetPreviewFingerprint')],
    ['idle previews', str_contains($js, 'requestIdleCallback') && str_contains($js, 'shareRenderPromise.then(scheduleSharePresetPreviews)')],
];
foreach ($tests as [$name, $ok]) {
    if (!$ok) { fwrite(STDERR, "Falha: {$name}\n"); exit(1); }
}
echo '✓ share route PNG static: ' . count($tests) . " assertions\n";
