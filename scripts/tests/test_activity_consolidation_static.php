<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
$edit=(string)file_get_contents($root.'/public/user/editatividade.php');
$title=(string)file_get_contents($root.'/src/layout/activity_smart_title.php');
$route=(string)file_get_contents($root.'/src/layout/activity_route_editor.php');
$privacy=(string)file_get_contents($root.'/src/layout/activity_route_privacy.php');
$css=(string)file_get_contents($root.'/public/assets/css/ui-refresh.css');
$js=(string)file_get_contents($root.'/public/assets/js/atividades.js');
$bridge=(string)file_get_contents($root.'/public/assets/js/activity-edit-bridge.js');
$tests=[
 ['embedded heading conditional', str_contains($edit,"if (!\$embedded):") && str_contains($edit,'activity-editor-heading')],
 ['embedded form flattened', str_contains($css,'body.activity-edit-embedded .activity-edit-form') && str_contains($css,'border: 0 !important') && str_contains($css,'border-radius: 0 !important') && str_contains($css,'box-shadow: none !important')],
 ['title itself is trigger', str_contains($title,'class="activity-smart-title-trigger"') && str_contains($title,'data-edit-activity-title') && str_contains($title,'<strong data-activity-title-preview>')],
 ['no separate title edit action', !str_contains($title,'activity-inline-action') && !str_contains($title,'>Editar</button>')],
 ['duration suffixes flow normally', str_contains($css,'.duration-segments > label > span') && str_contains($css,'position: static !important')],
 ['duration receives semantic width', str_contains($css,'minmax(270px,1.6fr)') && str_contains($css,'data-field-slug="duracao"')],
 ['derived metric shared styling', str_contains($css,'body.activity-edit-embedded .activity-editor') && str_contains($css,'.derived-result-wrap') && str_contains($css,'.derived-edit-button')],
 ['native number spinners removed cross engine', str_contains($css,'-moz-appearance: textfield') && str_contains($css,'::-webkit-inner-spin-button')],
 ['number stepper enhancer exists', str_contains($js,'const enhanceNumberInput = input =>') && str_contains($js,'stride-number-stepper-button') && str_contains($js,'stepNumberInput(input, 1)')],
 ['number stepper honors min max step', str_contains($js,"input.getAttribute('step')") && str_contains($js,'input.min') && str_contains($js,'input.max')],
 ['route and privacy share content inset', str_contains($css,'> .activity-route-builder[data-route-compact="1"]') && str_contains($css,'> .activity-route-privacy-fields') && str_contains($css,'margin: 0 18px')],
 ['route gain moved to header state', str_contains($route,'data-route-subview-summary') && str_contains($route,'data-route-subview-status') && str_contains($route,'data-route-elevation hidden')],
 ['route child notifies parent', str_contains($bridge,'stridebr:activity-route-subview-open') && str_contains($bridge,'stridebr:activity-route-subview-close')],
 ['parent modal owns route state', str_contains($js,'setActivityEditRouteSubview') && str_contains($js,"classList.toggle('is-route-subview'") && str_contains($js,'stridebr:activity-route-parent-resized')],
 ['parent route modal uses workspace size', str_contains($css,'.activity-edit-modal.is-route-subview .activity-edit-modal-panel') && str_contains($css,'width: min(1560px,96vw)') && str_contains($css,'calc(100dvh - 24px)')],
 ['parent route header hidden', str_contains($css,'.activity-edit-modal.is-route-subview .activity-edit-modal-header { display: none; }')],
 ['route flex bounds protected', str_contains($css,'.activity-route-map') && str_contains($css,'min-width: 0') && str_contains($css,'min-height: 0')],
 ['mobile and short landscape route fullscreen', str_contains($css,'@media (max-width: 760px), (max-height: 520px)') && str_contains($css,'width: 100vw') && str_contains($css,'height: 100dvh')],
 ['privacy keeps two fields and helpers', substr_count($privacy,'activity.meters_zero_help')===2 && str_contains($privacy,'activity-compact-two-columns')],
];
foreach($tests as [$name,$ok]){if(!$ok){fwrite(STDERR,"FAIL: $name\n");exit(1);}}
echo '✓ activity consolidation static: '.count($tests)." assertions\n";
