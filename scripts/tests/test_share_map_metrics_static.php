<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
$html=(string)file_get_contents($root.'/public/user/atividades.php');
$js=(string)file_get_contents($root.'/public/assets/js/atividades.js');
$maps=(string)file_get_contents($root.'/public/assets/js/map-basemaps.js');
$css=(string)file_get_contents($root.'/public/assets/css/activity-sharing.css');
$tests=[
 ['background presets include color map photo transparent', str_contains($js,"{id: 'stats'") && str_contains($js,"{id: 'map'") && str_contains($js,"{id: 'photo'") && str_contains($js,"{id: 'transparent'")],
 ['share map style controls exist', str_contains($html,'data-share-map-style-options') && substr_count($html,'data-share-map-style')===3],
 ['share exposes streets and satellite only', str_contains($html,'value="street"') && str_contains($html,'value="satellite"') && !str_contains($html,'value="terrain"')],
 ['interactive terrain remains available', str_contains($maps,"const validIds = new Set(['street', 'satellite', 'terrain'])") && str_contains($maps,'Elevation/World_Hillshade/MapServer/tile')],
 ['share provider list excludes terrain', str_contains($maps,"ids: Object.freeze(['street', 'satellite'])")],
 ['color and map controls contextual', str_contains($js,"shareColorOptions.hidden = !colorVisible") && str_contains($js,"shareMapStyleOptions.hidden = preset.mode !== 'map' || !mapCompatible")],
 ['map background follows composition compatibility', str_contains($js,"definition.supportsMap && hasRoute") && str_contains($js,"session_overview") && str_contains($js,"supportsMap: true") && str_contains($js,"compact") && str_contains($js,"supportsMap: false")],
 ['map card disappears when not applicable', str_contains($js,"const hidden = candidate.id === 'map' && !mapCompatible") && str_contains($js,"card.hidden = hidden")],
 ['invalid map preset falls back to last non map preset', str_contains($js,'lastNonMapSharePresetId') && str_contains($js,'const previous = getSharePreset(lastNonMapSharePresetId)')],
 ['map config disables unavailable styles', str_contains($js,'shareMapApi?.available?.(input.value)') && str_contains($js,"tr('activity.share.map_config_required')")],
 ['share tile API centralized', str_contains($maps,'const shareDefinitions = () => ({') && str_contains($maps,'share: Object.freeze({')],
 ['streets and satellite providers retained', str_contains($maps,'tile.openstreetmap.org/{z}/{x}/{y}.png') && str_contains($maps,'World_Imagery/MapServer/tile')],
 ['provider specific attribution', str_contains($maps,'Map data © OpenStreetMap contributors') && str_contains($maps,'Imagery © Esri')],
 ['tile images are canvas safe', str_contains($js,"image.crossOrigin = 'anonymous'")],
 ['tile cache is reused', str_contains($js,'const tileCache = new Map()') && str_contains($js,'if (tileCache.has(key)) return tileCache.get(key)')],
 ['preview basemap composite cache exists', str_contains($js,'const shareMapPreviewCache = new Map()') && str_contains($js,'shareMapPreviewCacheKey')],
 ['map zoom is selected from physical raster density', str_contains($js,"(candidate?.outputScale || Infinity) * density <= 1.01") && str_contains($maps,'maxRequestZoom')],
 ['preview and export use separate map raster density', str_contains($js,"mapRasterScale: .55") && str_contains($js,"mapRasterScale: 1") && str_contains($js,"renderPurpose: 'preview'") && str_contains($js,"renderPurpose: 'export'")],
 ['download rerenders final output instead of reusing preview canvas', str_contains($js,'const renderCurrentShareOutputCanvas = async () =>') && str_contains($js,'const canvas = await renderCurrentShareOutputCanvas()') && !str_contains($js,'return canvasPngBlob(shareCanvas)')],
 ['real map composite uses destination over', str_contains($js,"context.globalCompositeOperation = 'destination-over'") && substr_count($js,'compositeShareMapBackground(')>=2],
 ['map attribution is readable', str_contains($js,'const fontSize = width >= 1000 ? 16 : 13') && str_contains($js,"context.fillStyle = 'rgba(248,250,253,.92)'")],
 ['map text contrast is deliberately subtle', str_contains($js,"context.lineWidth = Math.max(.7, Math.min(1.5, outlineWidth * .18))") && str_contains($js,"context.shadowBlur = Math.max(1.2, Math.min(3.2, outlineWidth * .42))")],
 ['dynamic metric helper exists', str_contains($js,'const shareMetricLayout = (count, left, width, top, rowHeight) =>') && str_contains($js,'const shareMetricRowCount = count =>')],
 ['three metric triangle points down', str_contains($js,'{x: quarter, y: top, row: 0}') && str_contains($js,'{x: threeQuarter, y: top, row: 0}') && str_contains($js,'{x: center, y: top + rowHeight, row: 1}')],
 ['canonical metric ordering exists', str_contains($js,'const shareMetricCanonicalRank = metric =>') && str_contains($js,'const canonicalizeShareMetrics = metrics =>')],
 ['metric helpers used in standard and compact cards', substr_count($js,'shareMetricLayout(')>=3 && substr_count($js,'shareMetricRowCount(')>=4 && substr_count($js,'compactMetricLayout(')>=2],
 ['map style controls hide correctly', str_contains($css,'.activity-share-modal [hidden]') && str_contains($css,'display: none !important')],
 ['map viewport route and tiles share projection', str_contains($js,'createShareMapViewport') && str_contains($js,'viewport.worldToCanvas') && str_contains($js,'viewport.project')],
];
foreach($tests as [$name,$ok]){if(!$ok){fwrite(STDERR,"FAIL: $name\n");exit(1);}}
echo '✓ share map/metrics static: '.count($tests)." assertions\n";
