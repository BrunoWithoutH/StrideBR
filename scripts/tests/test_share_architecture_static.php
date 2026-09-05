<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
$js=(string)file_get_contents($root.'/public/assets/js/atividades.js');
$css=(string)file_get_contents($root.'/public/assets/css/activity-sharing.css');
$view=(string)file_get_contents($root.'/public/user/atividades.php');
$pt=(string)file_get_contents($root.'/src/i18n/pt-BR.php');
$en=(string)file_get_contents($root.'/src/i18n/en.php');
$tests=[
 ['formats remain story portrait square', str_contains($js,"story: Object.freeze({") && str_contains($js,"portrait: Object.freeze({") && str_contains($js,"square: Object.freeze({")],
 ['format anchors declared', substr_count($js,'titleAnchor: Object.freeze')===3 && substr_count($js,'contentStage: Object.freeze')>=3 && substr_count($js,'logoAnchor: Object.freeze')===3],
 ['scope model final', str_contains($js,"activity: 'activity'") && str_contains($js,"singleSegment: 'single_segment'") && str_contains($js,"session: 'session'") && str_contains($js,"multipleSegments: 'multiple_segments'")],
 ['composition registry separate from format', str_contains($js,'SHARE_COMPOSITION_REGISTRY') && str_contains($js,"id: 'standard'") && str_contains($js,"id: 'compact'")],
 ['activity standard and compact support three formats', str_contains($js,"supportedFormats: Object.freeze(['story', 'portrait', 'square'])")],
 ['compact format layouts final', str_contains($js,"layoutByFormat: Object.freeze({story: 'vertical', portrait: 'vertical', square: 'grid'})")],
 ['compact no map and no title', preg_match("/id: 'compact'.*?supportsMap: false, supportsTitle: false/s",$js)===1],
 ['session has all route compositions', str_contains($js,"id: 'session_overview'") && str_contains($js,"id: 'session_by_segment'") && str_contains($js,"id: 'session_comparison'") && str_contains($js,"id: 'session_highlight_route'") && str_contains($js,"id: 'session_sequence_route'")],
 ['session has all summary compositions', str_contains($js,"id: 'session_summary'") && str_contains($js,"id: 'session_list'") && str_contains($js,"id: 'session_sequence'") && str_contains($js,"id: 'session_highlight'") && str_contains($js,"id: 'session_minimal'")],
 ['session compact registered', str_contains($js,"id: 'session_compact'") && preg_match("/id: 'session_compact'.*?supportsMap: false, supportsTitle: false/s",$js)===1],
 ['session formats Story only', substr_count($js,"supportedFormats: Object.freeze(['story'])")>=11],
 ['registry contracts include limits and metric behavior', substr_count($js,'maxVisibleSegments:')>=13 && substr_count($js,'metricBehavior:')>=13 && substr_count($js,'requiresGeometry:')>=13],
 ['central compatibility predicate', str_contains($js,'const shareCompositionCompatibility =') && str_contains($js,'const compatibleShareCompositions =')],
 ['scope selector lives inside editor', str_contains($view,'data-share-scope') && str_contains($view,"activity.share.scope_session") && str_contains($view,"activity.share.scope_single_segment") && str_contains($view,"activity.share.scope_multiple_segments")],
 ['single and multiple segment pickers exist', str_contains($view,'data-share-single-segment-list') && str_contains($view,'data-share-select-all') && str_contains($view,'data-share-segment-list')],
 ['session composition families exist', str_contains($pt,"'activity.share.family_routes' => 'Percursos'") && str_contains($pt,"'activity.share.family_summary' => 'Resumo'")],
 ['English final scopes exist', str_contains($en,"'activity.share.scope_session' => 'Session'") && str_contains($en,"'activity.share.scope_single_segment' => 'One segment'") && str_contains($en,"'activity.share.scope_multiple_segments' => 'Multiple segments'")],
 ['share future state arrays', str_contains($js,'const buildShareRenderState =') && str_contains($js,'routes,') && str_contains($js,'segments: segments.map') && str_contains($js,'selectedSegmentIds') && str_contains($js,'sessionMetrics')],
 ['legacy route coordinate remains compatible', str_contains($js,'routeCoordinates,') && str_contains($js,'routeCoordinatesForSharing(data)')],
 ['layout model shared in configuration', str_contains($js,'const layoutModel = Object.freeze') && str_contains($js,'layoutModel,') && str_contains($js,'renderPurpose')],
 ['geometry renderer reusable', str_contains($js,'const createShareGeometryVisual =') && str_contains($js,'const drawShareGeometry =') && str_contains($js,"geometry: {type: 'LineString'")],
 ['route export uses shared geometry normalization', str_contains($js,'const sharedVisual = createShareGeometryVisual') && str_contains($js,'shareGeometryBounds(allCoordinates)')],
 ['comparison has contextual capabilities', str_contains($js,'const shareComparisonCapabilities =') && str_contains($js,'equalDistance') && str_contains($js,"capabilities.push({id:'time'")],
 ['comparison preserves precise durations', str_contains($js,'const shareFormatDurationPrecise =') && str_contains($js,"String(milliseconds).padStart(3,'0')")],
 ['highlight reuses comparison model', str_contains($js,'const shareHighlightResult =') && str_contains($js,'defaultShareComparisonMetric(segments)')],
 ['sequence renderer does not use absolute clock labels', str_contains($js,'const drawSessionSequence =') && !preg_match('/08:00|08:07|08:14|08:21/',$js)],
 ['visual overflow uses plus N', str_contains($js,'const drawSessionOverflow =')],
 ['blue route family has no rainbow palette', str_contains($js,'SHARE_BLUE_SERIES') && !preg_match('/#(?:ff0000|00ff00|ffff00|ff00ff)/i',$js)],
 ['preview and export use same configuration reader', substr_count($js,'readShareConfiguration({')>=6 && str_contains($js,"renderPurpose: 'preview'") && str_contains($js,"renderPurpose: 'export'")],
 ['map styles remain street satellite in share UI', str_contains($view,'value="street"') && str_contains($view,'value="satellite"') && !preg_match('/data-share-map-style[^>]*value="terrain"/',$view)],
 ['no obsolete compact format terminology in product', !preg_match('/Compacto Vertical|Compacto Horizontal|rota única|rotas múltiplas|single route|multi route/i',$js.$view.$pt.$en)],
 ['thumbnail styling uses product component not browser default', str_contains($css,'.activity-share-composition-options .share-composition-thumb')],
];
foreach($tests as [$name,$ok]){if(!$ok){fwrite(STDERR,"FAIL: $name\n");exit(1);}}
echo '✓ share architecture static: '.count($tests)." assertions\n";
