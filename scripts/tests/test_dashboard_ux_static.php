<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$home = file_get_contents($root . '/public/home.php');
$dashboardPhp = file_get_contents($root . '/src/function/dashboard.php');
$dashboardJs = file_get_contents($root . '/public/assets/js/dashboard.js');
$dashboardCss = file_get_contents($root . '/public/assets/css/dashboard.css');
$uiCss = file_get_contents($root . '/public/assets/css/ui-refresh.css');
$onboarding = file_get_contents($root . '/public/user/onboarding.php');

$todayPos = strpos($home, 'data-dashboard-today');
$weekPos = strpos($home, 'dashboard-week-consistency');
$goalsPos = strpos($home, 'dashboard-goals');

$checks = [
    'home present first' => $todayPos !== false && $weekPos !== false && $todayPos < $weekPos,
    'home weekly consistency' => str_contains($home, "stridebr_t('home.week_consistency')") && str_contains($dashboardPhp, 'dashboardVisaoAtividades') && str_contains($dashboardCss, '.dashboard-week-consistency'),
    'home no weekly volume bars' => !str_contains($home, 'dashboard-progress-bars') && !str_contains($home, 'data-dashboard-module="progress"'),
    'home not freely customizable' => !str_contains($home, 'data-dashboard-customize-dialog'),
    'home today before secondary sections' => $goalsPos === false || ($todayPos !== false && $todayPos < $goalsPos),
    'home primary actions' => str_contains($home, "stridebr_t('home.record_gps')") && str_contains($home, "stridebr_t('home.log_activity')"),
    'dashboard start workout remains' => str_contains($dashboardJs, 'data-dashboard-start-workout') && str_contains($dashboardJs, 'data_ocorrencia_planejada'),
    'onboarding untouched sports' => str_contains($onboarding, "stridebr_t('onboarding.sports_question')") && !str_contains($onboarding, 'name="sports[]" required'),
    'onboarding untouched experience' => str_contains($onboarding, 'name="experience"') && str_contains($onboarding, 'name="weekly_frequency"'),
    'desktop motion' => str_contains($uiCss, '--ui-motion-fast') && str_contains($uiCss, 'prefers-reduced-motion'),
];

$failed = [];
foreach ($checks as $label => $ok) if (!$ok) $failed[] = $label;
if ($failed !== []) {
    fwrite(STDERR, 'Falhas desktop UX: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo '✓ desktop UX static: ' . count($checks) . " assertions\n";
