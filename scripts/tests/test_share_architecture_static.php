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
 ['session has final public compositions only', str_contains($js,"id: 'session_overview'") && str_contains($js,"id: 'session_by_segment'") && str_contains($js,"id: 'session_comparison'") && str_contains($js,"id: 'session_highlight'") && str_contains($js,"id: 'session_sequence'") && str_contains($js,"id: 'session_summary'") && str_contains($js,"id: 'session_list'") && str_contains($js,"id: 'session_minimal'") && str_contains($js,"id: 'session_compact'") && !str_contains($js,"id: 'session_highlight_route'") && !str_contains($js,"id: 'session_sequence_route'")],
 ['session formats Story only', substr_count($js,"supportedFormats: Object.freeze(['story'])")>=9],
 ['registry contracts include limits geometry and metric behavior', substr_count($js,'maxVisibleSegments:')>=11 && substr_count($js,'metricBehavior:')>=11 && substr_count($js,'minGeometryCount:')>=11 && substr_count($js,'minComparableSegments:')>=11],
 ['comparison no longer requires geometry', preg_match("/id: 'session_comparison'.*?requiresGeometry: false.*?minGeometryCount: 0.*?minComparableSegments: 2/s",$js)===1],
 ['by segment and overview require only some geometry', preg_match("/id: 'session_overview'.*?minGeometryCount: 1/s",$js)===1 && preg_match("/id: 'session_by_segment'.*?minGeometryCount: 1/s",$js)===1],
 ['highlight and sequence support contextual route toggle', str_contains($js,'supportsRouteToggle: true') && substr_count($js,'supportsRouteToggle: true')===2 && str_contains($view,'data-share-session-route-option') && str_contains($view,'data-share-show="route"')],
 ['session compact metric controls exist', str_contains($view,'data-share-session-compact-controls') && str_contains($view,'data-share-session-compact-primary') && str_contains($view,'data-share-session-compact-secondary') && str_contains($js,'populateShareCompactMetricControls')],
 ['scope selector lives inside editor', str_contains($view,'data-share-scope') && str_contains($view,"activity.share.scope_session") && str_contains($view,"activity.share.scope_single_segment") && str_contains($view,"activity.share.scope_multiple_segments")],
 ['single and multiple segment pickers exist', str_contains($view,'data-share-single-segment-list') && str_contains($view,'data-share-select-all') && str_contains($view,'data-share-segment-list') && str_contains($view,'data-share-multiple-summary') && str_contains($view,'data-share-edit-segments')],
 ['single segment uses one visual selector without legacy route picker', str_contains($view,'data-share-single-segment-list') && !str_contains($view,'data-share-selected-route') && !str_contains($view,'data-share-change-route') && !str_contains($view,'data-share-route-picker') && !str_contains($js,'shareSingleRouteMode')],
 ['session picker is one flat list and stays responsive', str_contains($js,'const orderedLayouts = [') && str_contains($css,'.activity-share-choice-grid.is-session{')],
 ['English final scopes exist', str_contains($en,"'activity.share.scope_session' => 'Session'") && str_contains($en,"'activity.share.scope_single_segment' => 'One segment'") && str_contains($en,"'activity.share.scope_multiple_segments' => 'Multiple segments'")],
 ['share future state arrays', str_contains($js,'const buildShareRenderState =') && str_contains($js,'routes,') && str_contains($js,'segments: segments.map') && str_contains($js,'selectedSegmentIds') && str_contains($js,'sessionMetrics')],
 ['legacy route coordinate remains compatible', str_contains($js,'routeCoordinates,') && str_contains($js,'routeCoordinatesForSharing(data)')],
 ['layout model shared in configuration', str_contains($js,'const layoutModel = Object.freeze') && str_contains($js,'layoutModel,') && str_contains($js,'renderPurpose')],
 ['geometry renderer reusable', str_contains($js,'const createShareGeometryVisual =') && str_contains($js,'const drawShareGeometry =') && str_contains($js,"geometry: {type: 'LineString'")],
 ['route export uses shared geometry normalization', str_contains($js,'const sharedVisual = createShareGeometryVisual') && str_contains($js,'shareGeometryBounds(allCoordinates)')],
 ['comparison has contextual capabilities', str_contains($js,'const shareComparisonCapabilities =') && str_contains($js,'equalDistance') && str_contains($js,"capabilities.push({id:'time'")],
 ['comparison preserves precise localized durations', str_contains($js,'const shareFormatDurationPrecise = seconds => formatShareDuration(seconds)') && str_contains($js,"replace(/0+$/, '')") && str_contains($js,'const shareLocaleTag =')],
 ['highlight reuses comparison model and pace label', str_contains($js,'const shareHighlightResult =') && str_contains($js,"tr('activity.share.best_pace')")],
 ['sequence renderer does not use absolute clock labels', str_contains($js,'const drawSessionSequence =') && !preg_match('/08:00|08:07|08:14|08:21/',$js)],
 ['visual overflow uses plus N', str_contains($js,'const drawSessionOverflow =')],
 ['blue route family has no rainbow palette', str_contains($js,'SHARE_BLUE_SERIES') && !preg_match('/#(?:ff0000|00ff00|ffff00|ff00ff)/i',$js)],
 ['preview and export use same configuration reader', substr_count($js,'readShareConfiguration({')>=4 && str_contains($js,"renderPurpose: 'preview'") && str_contains($js,"renderPurpose: 'export'")],
 ['map styles remain street satellite in share UI', str_contains($view,'value="street"') && str_contains($view,'value="satellite"') && !preg_match('/data-share-map-style[^>]*value="terrain"/',$view)],
 ['no obsolete compact format terminology in product', !preg_match('/Compacto Vertical|Compacto Horizontal|rota única|rotas múltiplas|single route|multi route/i',$js.$view.$pt.$en)],
 ['share title is boolean toggle', preg_match('/type="checkbox" data-share-heading-mode checked/',$view)===1 && !preg_match('/<select[^>]*data-share-heading-mode/',$view)],
 ['share logo has no user toggle', !str_contains($view,'data-share-show="logo"') && str_contains($js,'showLogo: true')],
 ['share background colors are only blue and dark blue', substr_count($view,'data-share-background-color')===2 && str_contains($view,'value="deep" data-share-background-color') && str_contains($view,'value="dark" data-share-background-color') && !str_contains($view,'value="light" data-share-background-color') && !str_contains($view,'value="black" data-share-background-color')],
 ['content and background thumbnails keep label outside canvas', str_contains($js,'button.append(canvas, title)') && str_contains($js,'drawShareChoiceThumbnail')],
 ['thumbnail styling uses product component not browser default', str_contains($css,'.activity-share-composition-options .share-composition-thumb')],
 ['new session translations exist', str_contains($pt,"'activity.share.show_route' => 'Mostrar rota'") && str_contains($pt,"'activity.share.primary_metric' => 'Métrica principal'") && str_contains($pt,"'activity.share.secondary_metric' => 'Métrica secundária'")],
];
foreach($tests as [$name,$ok]){if(!$ok){fwrite(STDERR,"FAIL: $name\n");exit(1);}}
echo '✓ share architecture static: '.count($tests)." assertions\n";
