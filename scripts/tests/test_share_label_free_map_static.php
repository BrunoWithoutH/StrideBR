<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
$js=(string)file_get_contents($root.'/public/assets/js/atividades.js');
$maps=(string)file_get_contents($root.'/public/assets/js/map-basemaps.js');
$interactive=(string)file_get_contents($root.'/public/assets/js/map-basemaps.js');
$tests=[
 ['share street provider is geometry-only', str_contains($maps,"renderer: 'geometry'") && str_contains($maps,"attribution: 'Map data © OpenStreetMap contributors'")],
 ['share street no longer points to labeled raster street tiles', !str_contains($maps,'static-basemap-tiles-service/v1/arcgis/streets/static/tile')],
 ['interactive street layer stays OpenStreetMap raster', str_contains($interactive,'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png')],
 ['satellite share stays World Imagery', str_contains($maps,'World_Imagery/MapServer/tile/{z}/{y}/{x}?token=')],
 ['label-free renderer uses existing road geometry', str_contains($js,'const drawShareLabelFreeStreetBackground = async') && str_contains($js,'await fetchRoadNetwork(routeCoordinates)')],
 ['label-free renderer draws areas and roads but no labels', str_contains($js,"drawMapAreas(context, areas") && str_contains($js,'drawBaseRoadNetwork(context, roads')],
 ['street map routes through geometry while satellite keeps tiles', str_contains($js,"style === 'street'") && str_contains($js,'drawShareLabelFreeStreetBackground') && str_contains($js,'drawShareMapTiles(backgroundContext')],
 ['preview and export share same composite path', substr_count($js,'compositeShareMapBackground(context, configuration, viewport, token)')>=2],
 ['attribution remains drawn on final card', str_contains($js,'drawShareMapAttribution(context, mapAttribution')],
];
foreach($tests as [$name,$ok]){if(!$ok){fwrite(STDERR,"FAIL: $name\n");exit(1);}}
echo '✓ share label-free map static: '.count($tests)." assertions\n";
