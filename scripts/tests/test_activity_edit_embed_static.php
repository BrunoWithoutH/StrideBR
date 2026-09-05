<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
$ht=(string)file_get_contents($root.'/public/.htaccess');
$js=(string)file_get_contents($root.'/public/assets/js/atividades.js');
$php=(string)file_get_contents($root.'/public/user/editatividade.php');
$delete=(string)file_get_contents($root.'/src/function/apagaratividade.php');
$bridge=(string)file_get_contents($root.'/public/assets/js/activity-edit-bridge.js');
$tests=[
 ['global XFO DENY preserved', str_contains($ht,'X-Frame-Options "DENY"')],
 ['global frame ancestors none preserved', str_contains($ht,"frame-ancestors 'none'")],
 ['embed scoped env', str_contains($ht,'STRIDEBR_ACTIVITY_EDIT_EMBED')],
 ['embed SAMEORIGIN', str_contains($ht,'X-Frame-Options "SAMEORIGIN" env=STRIDEBR_ACTIVITY_EDIT_EMBED')],
 ['embed frame ancestors self', str_contains($ht,"frame-ancestors 'self'")],
 ['embed CSP still blocks inline script', str_contains($ht,"script-src 'self' https://unpkg.com") && !str_contains($ht,"script-src 'self' 'unsafe-inline'")],
 ['delete endpoint scoped too', str_contains($ht,"/function/apagaratividade.php")],
 ['iframe src same origin relative', str_contains($js,'activityEditFrame.src = activityEditUrl') && str_contains($js,'`${url.pathname}?${url.searchParams.toString()}`')],
 ['ready handshake external bridge', str_contains($bridge,"stridebr:activity-edit-ready") && str_contains($js,"stridebr:activity-edit-ready")],
 ['embed has no inline bridge', !str_contains($php,"window.parent.postMessage")],
 ['save result uses bridge asset', str_contains($php,"activity-edit-bridge.js") && str_contains($php,"data-activity-edit-result")],
 ['delete result uses bridge asset', str_contains($delete,"activity-edit-bridge.js") && str_contains($delete,"data-activity-edit-result")],
 ['delete embed query preserved', str_contains($php,"apagaratividade.php<?php echo \$embedded ? '?embed=1'")],
 ['embed keeps query on POST', str_contains($php,'action="/user/editatividade.php?id=') && str_contains($php,'&embed=1')],
 ['embed skips header', str_contains($php,"if (!\$embedded) require dirname(__DIR__, 2) . '/src/layout/header.php'")],
 ['embed skips full footer', str_contains($php,"if (!\$embedded):") && str_contains($php,"require dirname(__DIR__, 2) . '/src/layout/footer.php'")],
 ['embed keeps i18n runtime', str_contains($php,'stridebr_i18n_runtime_script(false)')],
 ['embed keeps common UI only', str_contains($php,"stridebr_asset('/assets/js/scripts.js')")],
 ['error fallback exists', str_contains($js,'data-activity-edit-retry') && str_contains($js,'data-activity-edit-new-page')],
];
foreach($tests as [$name,$ok]){if(!$ok){fwrite(STDERR,"FAIL: $name\n");exit(1);}}
echo '✓ activity edit embed static: '.count($tests)." assertions\n";
