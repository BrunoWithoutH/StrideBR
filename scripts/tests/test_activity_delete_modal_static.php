<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
$js=(string)file_get_contents($root.'/public/assets/js/atividades.js');
$ui=(string)file_get_contents($root.'/public/assets/js/scripts.js');
$css=(string)file_get_contents($root.'/public/assets/css/ui-refresh.css');
$pt=(string)file_get_contents($root.'/src/i18n/pt-BR.php');
$en=(string)file_get_contents($root.'/src/i18n/en.php');
$tests=[
 ['named delete uses primary key', str_contains($js,"activity.delete_confirm_named_primary")],
 ['named delete uses secondary key', str_contains($js,"activity.delete_confirm_named_secondary")],
 ['confirm supports semantic blocks', str_contains($ui,'options.messageBlocks') && str_contains($ui,"document.createElement('p')")],
 ['confirm uses textContent', str_contains($ui,'paragraph.textContent = String(block')],
 ['PT primary copy', str_contains($pt,"'activity.delete_confirm_named_primary' => 'Apagar “{title}”?'" )],
 ['PT secondary copy', str_contains($pt,'esse treino volta a ficar pendente. Você poderá desfazer logo depois.')],
 ['EN equivalent', str_contains($en,"'activity.delete_confirm_named_primary' => 'Delete “{title}”?'" )],
 ['legacy named key has no escaped newline PT', !preg_match("/'activity\.delete_confirm_named'\s*=>\s*'[^']*\\\\n/",$pt)],
 ['legacy named key has no escaped newline EN', !preg_match("/'activity\.delete_confirm_named'\s*=>\s*'[^']*\\\\n/",$en)],
 ['danger confirm sets readable text', str_contains($css,'.ui-confirm-actions .ui-confirm-ok.is-danger') && str_contains($css,'color: var(--ui-on-danger, #fff)')],
 ['danger hover explicit', str_contains($css,'.ui-confirm-actions .ui-confirm-ok.is-danger:hover:not(:disabled)')],
];
foreach($tests as [$name,$ok]){if(!$ok){fwrite(STDERR,"FAIL: $name\n");exit(1);}}
echo '✓ activity delete modal static: '.count($tests)." assertions\n";
