<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$home = file_get_contents($root . '/public/home.php');
$dashboard = file_get_contents($root . '/src/function/dashboard.php');
$progress = file_get_contents($root . '/public/user/progresso.php');
$progressFn = file_get_contents($root . '/src/function/sport_hub.php');
$progressCss = file_get_contents($root . '/public/assets/css/sport-hub.css');
$progressJs = file_get_contents($root . '/public/assets/js/progresso.js');
$activities = file_get_contents($root . '/public/user/atividades.php');
$activitiesJs = file_get_contents($root . '/public/assets/js/atividades.js');
$activitiesCss = file_get_contents($root . '/public/assets/css/atividades.css');
$sportPicker = file_get_contents($root . '/src/layout/sport_picker.php');

$checks = [
    'home asks what happened this week by presence' => str_contains($dashboard, 'dashboardVisaoAtividades') && str_contains($home, 'dashboard-week-consistency') && str_contains($home, 'dashboard-week-markers'),
    'home activity count does not depend on duration' => str_contains($dashboard, 'COUNT(*) AS atividades') && str_contains($home, "(int) \$dia['atividades'] > 0"),
    'home avoids week magnitude bars' => !str_contains($home, 'dashboard-progress-bars') && !str_contains($home, 'chart-bar'),
    'home present before analysis' => strpos($home, 'data-dashboard-today') < strpos($home, 'dashboard-week-consistency'),

    'progress compact period presets' => str_contains($progress, "'4w'") && str_contains($progress, "'12w'") && str_contains($progress, "'6m'") && str_contains($progress, "'1y'"),
    'progress sport filter from available data' => str_contains($progress, 'sportHubAvailableSports') && str_contains($progress, 'progress.all_sports'),
    'progress contextual kpis without forced cards' => str_contains($progress, '$summaryItems') && str_contains($progressCss, '.progress-kpi-strip') && !str_contains($progressCss, '.progress-kpi-card'),
    'progress binary consistency' => str_contains($progress, 'sportHubConsistencyDays') && str_contains($progressCss, '.progress-consistency-day.is-active'),
    'progress weekly volume zero baseline' => str_contains($progress, 'progress-zero-line') && str_contains($progressCss, '.progress-zero-line'),
    'progress missing data breaks line' => str_contains($progress, '$segments = []') && str_contains($progress, 'if ($value === null)'),
    'progress sparse trend fallback' => str_contains($progress, '$trendUsesLine = $knownPointCount >= 3') && str_contains($progress, 'progress-trend-sparse'),
    'progress modalities are readable list' => str_contains($progress, 'progress-sport-list') && !str_contains(strtolower($progress), 'donut') && !str_contains(strtolower($progress), '<canvas'),
    'progress comparison neutral' => str_contains($progress, 'progress-comparison-list') && str_contains($progress, 'progress.no_change'),
    'progress small multiples disclosed' => str_contains($progress, '<details class="progress-disclosure">') && str_contains($progress, 'progress-small-multiples'),
    'progress small multiples preserve missing metrics' => str_contains($progress, '!empty($row[\'distance_known\'])') && str_contains($progress, 'class="is-missing"'),
    'progress small multiples expose text alternative' => str_contains($progress, 'class="visually-hidden"') && str_contains($progress, "stridebr_t('progress.no_data')"),
    'progress stale navigation protected' => str_contains($progressJs, 'AbortController') && str_contains($progressJs, 'navigationId'),
    'progress no invented training score' => !preg_match('/Fitness|Fatigue|Readiness|Training Load|Stride Score/i', $progress),

    'activities primary actions reprioritized' => str_contains($activities, 'activity-toolbar-essential') && str_contains($activities, 'activity-toolbar-tools'),
    'activities global progress removed' => !str_contains($activities, 'activity-toolbar-progress'),
    'activities compare is action in detail header' => str_contains($activities, 'activity-detail-header-actions') && str_contains($activities, 'activity-detail-compare'),
    'activities detail uses structural skeleton' => str_contains($activities, 'activity-detail-skeleton-metrics') && str_contains($activitiesCss, 'activity-detail-skeleton-block') && str_contains($activitiesCss, '.activity-detail-skeleton-metrics i{width:auto;height:58px;flex:auto;border:0'),
    'activities detail cache avoids skeleton' => str_contains($activitiesJs, 'if (cached)') && str_contains($activitiesJs, 'renderDetail(cached)') && str_contains($activitiesJs, 'detailSkeletonTimer'),
    'activities stale detail protected' => str_contains($activitiesJs, 'detailOpenSequence') && str_contains($activitiesJs, 'sequence !== detailOpenSequence'),
    'activities optimistic delete moves pixels before request' => strpos($activitiesJs, 'row?.remove()') < strpos($activitiesJs, "fetchWithDeadline('/function/apagaratividade.php'"),
    'activities delete has rollback and undo' => str_contains($activitiesJs, 'rollbackOptimisticDelete') && str_contains($activitiesJs, 'StrideBRUI.undo') && str_contains($activitiesJs, 'restoreActivities([key])'),
    'activities empty state has two useful actions' => str_contains($activities, 'activity-empty-actions') && str_contains($activities, '/user/gravar-atividade.php'),
    'activities favorite responds optimistically with rollback' => strpos($activitiesJs, 'applyFavoriteState(nextFavorite)') < strpos($activitiesJs, "fetch('/api/modalidade-favorita.php'") && str_contains($activitiesJs, 'applyFavoriteState(!nextFavorite)'),
    'activities favorite exposes pressed state' => str_contains($sportPicker, 'aria-pressed="') && str_contains($activitiesJs, "setAttribute('aria-pressed'"),
];

$failed = [];
foreach ($checks as $label => $ok) if (!$ok) $failed[] = $label;
if ($failed !== []) {
    fwrite(STDERR, 'Falhas product UX: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo '✓ product UX round static: ' . count($checks) . " assertions\n";
