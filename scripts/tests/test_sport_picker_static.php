<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$catalog = $read('src/function/sport_catalog.php');
$picker = $read('src/layout/sport_picker.php');
$scripts = $read('public/assets/js/scripts.js');
$signup = $read('public/signup.php');
$settings = $read('public/user/settings.php');
$activities = $read('public/user/atividades.php');
$gps = $read('public/user/gravar-atividade.php');
$goals = $read('public/user/metas.php');
$schedule = $read('public/user/cronogramatreinos.php');
$library = $read('public/user/biblioteca.php');
$calendar = $read('public/calendario.php');
$unitHelpers = $read('src/layout/activity_unit_helpers.php');
$activityJs = $read('public/assets/js/atividades.js');
$uiCss = $read('public/assets/css/ui-refresh.css');

$checks = [
    'catálogo define famílias e esportes comuns' => str_contains($catalog, "'cardio' => ['label' => 'Cardio'") && str_contains($catalog, "'popular' => ['corrida','caminhada','ciclismo','natacao'") && str_contains($catalog, "'athletics' => ['label' => 'Atletismo'"),
    'seletor abre categorias antes dos esportes' => str_contains($picker, 'data-generic-sport-family-open') && str_contains($picker, 'Mais comuns') && str_contains($picker, 'Mais esportes'),
    'busca alcança esportes fora da lista comum' => str_contains($scripts, "browser?.classList.add('is-searching')") && str_contains($scripts, "panel.querySelectorAll('[data-generic-sport-more-list]').forEach(list => list.hidden = false)"),
    'cadastro usa categorias comuns e mais' => str_contains($signup, 'data-signup-sport-family-open') && str_contains($signup, 'data-signup-sport-more'),
    'configurações usa categorias comuns e mais' => str_contains($settings, 'data-settings-sport-family-open') && str_contains($settings, 'data-settings-sport-more'),
    'registro e histórico usam seletor categorizado' => str_contains($activities, 'sportPickerRenderFamilyBrowser') && substr_count($activities, 'sportPickerRenderSelect(') >= 2,
    'GPS usa seletor categorizado' => str_contains($gps, 'sportPickerRenderSelect($modalidades') && str_contains($gps, "'data-gps-sport' => true"),
    'metas usa seletor categorizado' => str_contains($goals, 'sportPickerRenderSelect($modalidades'),
    'cronogramas usa seletor categorizado' => substr_count($schedule, 'sportPickerRenderSelect($modalidadesTreino') >= 2,
    'biblioteca usa seletor categorizado' => str_contains($library, 'sportPickerRenderSelect($modalidadesTreino'),
    'eventos usam seletor categorizado' => str_contains($calendar, 'sportPickerRenderSelect($modalidades'),
    'trechos também usam categorias e mais esportes' => str_contains($unitHelpers, 'sportPickerRenderSelect($catalogo') && str_contains($unitHelpers, "'data-unit-sport-select' => true"),
    'seletores inseridos dinamicamente são inicializados' => str_contains($scripts, 'window.StrideBRSportPickerInit') && str_contains($activityJs, 'window.StrideBRSportPickerInit?.(unit || container)'),
    'popover global escapa de contêineres com overflow' => str_contains($scripts, 'const placePopover = () =>') && str_contains($scripts, "popover.style.position = 'fixed'") && str_contains($scripts, "window.addEventListener('scroll', placePopover, true)"),
    'popover global fica acima dos painéis do produto' => str_contains($uiCss, 'z-index:10050'),
];

$failed = [];
foreach ($checks as $label => $ok) if (!$ok) $failed[] = $label;
if ($failed !== []) {
    fwrite(STDERR, "Falhas no seletor esportivo:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}
printf("✓ sport picker static: %d assertions\n", count($checks));
