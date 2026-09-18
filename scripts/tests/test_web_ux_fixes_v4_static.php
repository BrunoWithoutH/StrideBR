<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$assert = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$api = $read('public/api/atividade-participantes.php');
$assert(str_contains($api, 'stridebr_verify_csrf()') && !str_contains($api, 'stridebr_csrf_validate_request'), 'Participant endpoint must use real CSRF helper.');
$pacer = $read('public/assets/css/pacer-web.css');
$assert(str_contains($pacer, '.pacer-page .pacer-editor .pacer-input-unit.field-with-unit input[type="number"]{outline:0;border:0;background:transparent;border-radius:0;') && str_contains($pacer, 'box-shadow:none') && str_contains($pacer, '.pacer-input-unit.field-with-unit:focus-within'), 'Pacer unit input must have one border and wrapper focus.');
$css = $read('public/assets/css/ui-refresh.css');
$assert((bool) preg_match('/\.ui-toast-close\s*\{[^}]*width: 40px;[^}]*height: 40px/s', $css), 'Toast close target must be 40px.');
$assert((bool) preg_match('/\.ui-toast-host\s*\{[^}]*top: calc\(/s', $css) && str_contains($css, '.ui-toast.is-dragging'), 'Toast must sit below header and support gestures.');
$assert(!str_contains($read('public/user/atividades.php'), 'class="activity-toolbar-tools"') && substr_count($read('src/layout/header.php'), "\$headerMenuLink('/user/equipamentos.php'") === 2, 'Equipment belongs to both user menus.');
$assert(str_contains($read('src/includes/i18n.php'), "'exercise.entry.'"), 'Exercise copy must reach JS i18n.');
$assert(str_contains($read('public/assets/js/exercise-entry.js'), 'input[name="exercise_name[]"]') && str_contains($read('src/layout/footer.php'), '/assets/js/exercise-entry.js'), 'Manual exercise surfaces share typeahead.');
require $root . '/src/function/exercise_resolver.php';
$catalog = [['idexercicio'=>'personal','nome'=>'Supino reto','slug'=>'own'], ['idexercicio'=>'system','nome'=>'Supino reto','slug'=>'supino-reto'], ['idexercicio'=>'scott','nome'=>'Rosca Scott','slug'=>'rosca-scott']];
foreach (['Supino reto','SUPINO RETO'] as $name) { $r = stridebr_exercise_resolve_entry($catalog, ['nome'=>$name]); $assert($r['match']['idexercicio'] === 'personal' && $r['match']['nome'] === 'Supino reto', 'Personal exact/case match first.'); }
$r = stridebr_exercise_resolve_entry($catalog, ['nome'=>'Bench'], ['Bench'=>'Supino reto']); $assert($r['reason'] === 'alias', 'Configured alias uses same resolver.');
$r = stridebr_exercise_resolve_entry($catalog, ['nome'=>'Rosca scut']); $assert($r['status'] === 'suggest' && $r['match'] === null && $r['suggestions'][0]['nome'] === 'Rosca Scott', 'Fuzzy must remain explicit suggestion.');
foreach (['RDL','TRX','T-Bar','EZ','Meu exercício personalizado'] as $name) { $r = stridebr_exercise_resolve_entry($catalog, ['nome'=>$name]); $assert($r['match'] === null, 'Custom/acronym must not gain guessed identity.'); }
$long = str_repeat('a', 80); $r = stridebr_exercise_resolve_entry([['idexercicio'=>'long','nome'=>$long]], ['nome'=>$long.'x']); $assert($r['status'] === 'suggest' && $r['match'] === null, 'High-confidence fuzzy must not silently match.');
echo "Web UX Fixes V4 static/domain contracts passed.\n";
