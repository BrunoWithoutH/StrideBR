<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$home = file_get_contents($root . '/public/home.php');
$dashboardJs = file_get_contents($root . '/public/assets/js/dashboard.js');
$dashboardCss = file_get_contents($root . '/public/assets/css/dashboard.css');
$uiCss = file_get_contents($root . '/public/assets/css/ui-refresh.css');
$onboarding = file_get_contents($root . '/public/user/onboarding.php');
$preferencesApi = file_get_contents($root . '/public/api/dashboard-preferences.php');

$checks = [
    'home contextual' => str_contains($home, 'data-dashboard-today') && str_contains($home, 'Hoje é dia de descanso'),
    'home customization' => str_contains($home, 'data-dashboard-customize-dialog') && str_contains($home, 'data-dashboard-module="progress"'),
    'dashboard preferences api' => str_contains($preferencesApi, 'dashboardSalvarPreferenciasHome') && str_contains($preferencesApi, 'stridebr_verify_csrf'),
    'dashboard start workout' => str_contains($dashboardJs, 'data-dashboard-start-workout') && str_contains($dashboardJs, 'data_ocorrencia_planejada'),
    'dashboard module persistence' => str_contains($dashboardJs, '/api/dashboard-preferences.php') && str_contains($dashboardJs, 'applyPreferences'),
    'onboarding optional sports' => str_contains($onboarding, 'O que você pratica?') && !str_contains($onboarding, 'name="sports[]" required'),
    'onboarding experience' => str_contains($onboarding, 'name="experience"') && str_contains($onboarding, 'name="weekly_frequency"'),
    'onboarding tracking' => str_contains($onboarding, 'name="tracking[]"') && str_contains($onboarding, 'Mostrar primeiro em Progresso') === false,
    'desktop motion' => str_contains($uiCss, '--ui-motion-fast') && str_contains($uiCss, 'prefers-reduced-motion'),
    'today layout' => str_contains($dashboardCss, '.dashboard-today') && str_contains($dashboardCss, '.dashboard-modules'),
];

$failed = [];
foreach ($checks as $label => $ok) {
    if (!$ok) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, 'Falhas desktop UX: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo '✓ desktop UX static: ' . count($checks) . " assertions\n";
