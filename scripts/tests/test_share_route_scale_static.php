<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
$html=(string)file_get_contents($root.'/public/user/atividades.php');
$js=(string)file_get_contents($root.'/public/assets/js/atividades.js');
$tests=[
 ['slider 50 to 200', str_contains($html,'min="50" max="200"') && str_contains($html,'data-share-route-scale')],
 ['single getter clamp', str_contains($js,'Math.max(50, Math.min(200, Number(shareRouteScaleInput?.value) || 100))')],
 ['preference clamp matches slider', str_contains($js,'Math.max(50, Math.min(200, Number(raw.routeScale) || fallback.routeScale))')],
 ['explicit route fill mapping', str_contains($js,'const shareRouteFillForScale = value =>') && str_contains($js,"return .68 + ((percent - 100) / 100) * .26")],
 ['renderer uses route fill', substr_count($js,'shareRouteFillForScale(routeScale)')>=2],
 ['real route bbox', str_contains($js,'const rawSpanX = Math.max(0, maxX - minX)') && str_contains($js,'dominantSpan * .035')],
 ['safe frame projection', str_contains($js,'const baseScale = Math.min(target.width / spanX, target.height / spanY)') && str_contains($js,'baseScale * fill')],
 ['preview uses canonical renderer', str_contains($js,'result = await drawShareCardSurface(context, config, token)')],
 ['export rerenders canonical surface at final quality', str_contains($js,'const renderCurrentShareOutputCanvas = async () =>') && str_contains($js,"mapRasterScale: 1") && str_contains($js,'drawShareCardSurface(context, config, token)')],
 ['route scale input is frame throttled', str_contains($js,"shareRouteScaleInput?.addEventListener('input', scheduleShareRouteScaleDraw)") && str_contains($js,'window.requestAnimationFrame')],
 ['compact route follows metrics', str_contains($js,'const visualTop = metricBottom + (isWide ? 20 : 28)')],
];
foreach($tests as [$name,$ok]){if(!$ok){fwrite(STDERR,"FAIL: $name\n");exit(1);}}
echo '✓ share route scale static: '.count($tests)." assertions\n";
