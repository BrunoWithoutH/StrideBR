<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/sport_hub.php';
require_once dirname(__DIR__, 2) . '/src/includes/sport_icons.php';

$requestedPeriod = stridebr_lower(trim((string) ($_GET['period'] ?? 'all')));
$periodAnchor = trim((string) ($_GET['month'] ?? ''));
$periodWindow = sportHubResolvePeriod($requestedPeriod === 'all' ? '1y' : $requestedPeriod, $periodAnchor);
$period = $requestedPeriod === 'all' ? 'all' : (string) $periodWindow['view'];
$now = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
$lookbackSeconds = max(0, $now->getTimestamp() - $periodWindow['previous_start']->getTimestamp());
$activityDays = $period === 'all' ? 36500 : min(1500, max(90, (int) ceil($lookbackSeconds / 86400) + 14));
$activities = sportHubActivityRows($pdo, $idUsuario, $activityDays);
$availableSports = sportHubNavigationSports($pdo, $idUsuario, $activities);

$requestedSport = stridebr_lower(trim((string) ($_GET['sport'] ?? 'all')));
$selectedSport = 'all';
$selectedSportMeta = null;
foreach ($availableSports as $sportMeta) {
    if ((string) $sportMeta['slug'] === $requestedSport) {
        $selectedSport = $requestedSport;
        $selectedSportMeta = $sportMeta;
        break;
    }
}
$selectedFamily = (string) ($selectedSportMeta['family'] ?? 'all');
$filteredActivities = sportHubFilterSport($activities, $selectedSport);
if ($period === 'all') {
    $historyStart = null;
    foreach ($filteredActivities as $row) {
        try { $date = new DateTimeImmutable((string) $row['data_inicio']); } catch (Throwable) { continue; }
        if ($historyStart === null || $date < $historyStart) $historyStart = $date;
    }
    $historyStart ??= $now;
    $periodWindow = ['view'=>'all', 'current_start'=>$historyStart, 'current_end'=>$now->modify('+1 second'), 'previous_start'=>$historyStart, 'previous_end'=>$historyStart, 'span_seconds'=>0, 'anchor'=>''];
}
$currentActivities = sportHubActivitiesInWindow($filteredActivities, $periodWindow['current_start'], $periodWindow['current_end']);
$previousActivities = sportHubActivitiesInWindow($filteredActivities, $periodWindow['previous_start'], $periodWindow['previous_end']);
$currentSummary = sportHubSummaryMetrics($currentActivities);
$previousSummary = sportHubSummaryMetrics($previousActivities);
$weekly = sportHubWeeklySeries($currentActivities, $periodWindow['current_start'], $periodWindow['current_end']);
$consistencyDays = sportHubConsistencyDays($currentActivities, $periodWindow['current_start'], $periodWindow['current_end']);
$breakdown = sportHubModalitiesBreakdown($currentActivities);
$renderer = $selectedSport === 'all' ? 'overview' : sportHubProgressRenderer($selectedSport, $selectedFamily);
$trainingLoad = sportHubTrainingLoad($currentActivities);
$previousTrainingLoad = sportHubTrainingLoad($previousActivities);
$strengthDashboard = $renderer === 'strength' ? sportHubStrengthDashboard($pdo, $idUsuario, $activities, $periodWindow, $selectedSport) : null;
$cardioDashboard = in_array($renderer, ['running', 'cycling', 'swimming'], true) ? sportHubCardioDashboard($filteredActivities, $periodWindow) : null;
$athleticsDashboard = $renderer === 'athletics' ? sportHubAthleticsDashboard($pdo, $idUsuario, $filteredActivities, $periodWindow) : null;
$sessionDashboard = in_array($renderer, ['team', 'racket', 'combat'], true) ? sportHubSessionDashboard($filteredActivities, $selectedFamily, $periodWindow) : null;
$goalsStmt = $pdo->prepare("SELECT g.nome, g.metrica, g.periodo, g.valor_alvo, m.slug AS modalidade_slug
    FROM metas_usuario g LEFT JOIN modalidades m ON m.idmodalidade=g.idmodalidade
    WHERE g.idusuario=:usuario AND g.ativa=TRUE AND (:sport='all' OR m.slug=:sport) ORDER BY g.data_criacao DESC LIMIT 4");
$goalsStmt->execute([':usuario'=>$idUsuario, ':sport'=>$selectedSport]);
$progressGoals = $goalsStmt->fetchAll();

$metricAvailability = [
    'activities' => true,
    'duration' => $currentSummary['duration_s'] > 0 || $previousSummary['duration_s'] > 0,
    'distance' => $currentSummary['distance_m'] > 0 || $previousSummary['distance_m'] > 0,
    'elevation' => $currentSummary['elevation_m'] > 0 || $previousSummary['elevation_m'] > 0,
];
$defaultMetric = 'activities';
if ($selectedSport !== 'all') {
    if ($selectedFamily !== 'strength' && $metricAvailability['distance']) $defaultMetric = 'distance';
    elseif ($metricAvailability['duration']) $defaultMetric = 'duration';
}
$requestedMetric = stridebr_lower(trim((string) ($_GET['metric'] ?? $defaultMetric)));
$metric = isset($metricAvailability[$requestedMetric]) && $metricAvailability[$requestedMetric] ? $requestedMetric : $defaultMetric;

$fmtDuration = static function (float $seconds): string {
    $seconds = max(0, (int) round($seconds));
    if ($seconds === 0) return '0 min';
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    if ($hours > 0) return $hours . 'h' . ($minutes > 0 ? ' ' . $minutes . 'min' : '');
    return max(1, $minutes) . ' min';
};
$fmtDistance = static function (float $meters): string {
    if ($meters >= 1000) return stridebr_format_number($meters / 1000, $meters >= 10000 ? 0 : 1) . ' km';
    return stridebr_format_number($meters, 0) . ' m';
};
$fmtElevation = static fn(float $meters): string => stridebr_format_number($meters, 0) . ' m';
$fmtMetric = static function (string $metricKey, float $value) use ($fmtDuration, $fmtDistance, $fmtElevation): string {
    return match ($metricKey) {
        'duration' => $fmtDuration($value),
        'distance' => $fmtDistance($value),
        'elevation' => $fmtElevation($value),
        default => stridebr_format_number($value, 0),
    };
};
$metricValue = static function (array $week, string $metricKey): ?float {
    if ($metricKey === 'activities') return (float) ($week['activities'] ?? 0);
    if ((int) ($week['activities'] ?? 0) === 0) return 0.0;
    return match ($metricKey) {
        'duration' => !empty($week['duration_known']) ? (float) ($week['duration_s'] ?? 0) : null,
        'distance' => !empty($week['distance_known']) ? (float) ($week['distance_m'] ?? 0) : null,
        'elevation' => !empty($week['elevation_known']) ? (float) ($week['elevation_m'] ?? 0) : null,
        default => null,
    };
};
$metricLabels = [
    'activities' => stridebr_t('progress.activities'),
    'duration' => stridebr_t('progress.time'),
    'distance' => stridebr_t('progress.distance'),
    'elevation' => stridebr_t('progress.elevation'),
];
$periodLabels = [
    'all' => stridebr_t('progress.period.all'),
    '4w' => stridebr_t('progress.period.4w'),
    '12w' => stridebr_t('progress.period.12w'),
    '6m' => stridebr_t('progress.period.6m'),
    '1y' => stridebr_t('progress.period.1y'),
];
$selectedSportLabel = $selectedSportMeta !== null
    ? stridebr_sport_name((string) $selectedSportMeta['slug'], (string) $selectedSportMeta['name'])
    : stridebr_t('progress.overview');
$periodRangeLabel = stridebr_t('progress.range_label', [
    'start' => stridebr_format_date_short($periodWindow['current_start']),
    'end' => stridebr_format_date_short($periodWindow['current_end']->modify('-1 second')),
]);

$summaryItems = [
    ['key' => 'activities', 'label' => stridebr_t('progress.activities'), 'value' => stridebr_format_number((float) $currentSummary['activities'], 0)],
];
if ($currentSummary['duration_s'] > 0) $summaryItems[] = ['key' => 'duration', 'label' => stridebr_t('progress.time'), 'value' => $fmtDuration((float) $currentSummary['duration_s'])];
if ($currentSummary['distance_m'] > 0) $summaryItems[] = ['key' => 'distance', 'label' => stridebr_t('progress.distance'), 'value' => $fmtDistance((float) $currentSummary['distance_m'])];
if ($currentSummary['elevation_m'] > 0) $summaryItems[] = ['key' => 'elevation', 'label' => stridebr_t('progress.elevation'), 'value' => $fmtElevation((float) $currentSummary['elevation_m'])];
if (count($summaryItems) < 3 && $currentSummary['active_days'] > 0) $summaryItems[] = ['key' => 'days', 'label' => stridebr_t('progress.active_days'), 'value' => stridebr_format_number((float) $currentSummary['active_days'], 0)];

$comparisonItems = [];
$comparisonDefinitions = [
    'activities' => [(float) $currentSummary['activities'], (float) $previousSummary['activities'], static fn(float $v): string => stridebr_format_number(abs($v), 0)],
    'duration' => [(float) $currentSummary['duration_s'], (float) $previousSummary['duration_s'], $fmtDuration],
    'distance' => [(float) $currentSummary['distance_m'], (float) $previousSummary['distance_m'], $fmtDistance],
    'elevation' => [(float) $currentSummary['elevation_m'], (float) $previousSummary['elevation_m'], $fmtElevation],
];
foreach ($comparisonDefinitions as $key => [$current, $previous, $formatter]) {
    if ($key !== 'activities' && $current <= 0 && $previous <= 0) continue;
    $delta = $current - $previous;
    $comparisonItems[] = [
        'label' => $metricLabels[$key] ?? $key,
        'current' => $fmtMetric($key, $current),
        'delta' => abs($delta) < .0001 ? stridebr_t('progress.no_change') : (($delta > 0 ? '+' : '−') . $formatter(abs($delta))),
    ];
}

$weeklyValues = [];
foreach ($weekly as $week) $weeklyValues[] = $metricValue($week, $metric);
$knownValues = array_values(array_filter($weeklyValues, static fn($value): bool => $value !== null));
$maxWeekly = max(1.0, ...($knownValues ?: [1.0]));
$knownPointCount = count($knownValues);
$trendUsesLine = $knownPointCount >= 3;
$weekLabelStep = count($weekly) > 32 ? 4 : (count($weekly) > 16 ? 2 : 1);
$trendDense = count($weekly) > 16;
$totalMetricCurrent = match ($metric) {
    'duration' => (float) $currentSummary['duration_s'],
    'distance' => (float) $currentSummary['distance_m'],
    'elevation' => (float) $currentSummary['elevation_m'],
    default => (float) $currentSummary['activities'],
};
$volumeSummary = stridebr_t('progress.volume_summary', ['value' => $fmtMetric($metric, $totalMetricCurrent), 'period' => $periodLabels[$period] ?? $period]);

$topMultiples = [];
if ($selectedSport === 'all' && count($breakdown) >= 2) {
    foreach (array_slice($breakdown, 0, 3) as $sportBreakdown) {
        $sportRows = sportHubFilterSport($currentActivities, (string) $sportBreakdown['slug']);
        $sportSeries = sportHubWeeklySeries($sportRows, $periodWindow['current_start'], $periodWindow['current_end']);
        $sportMetric = (float) ($sportBreakdown['distance_m'] ?? 0) > 0 ? 'distance' : ((float) ($sportBreakdown['duration_s'] ?? 0) > 0 ? 'duration' : 'activities');
        $values = array_map(static function (array $row) use ($sportMetric): ?float {
            if ($sportMetric === 'activities') return (float) ($row['activities'] ?? 0);
            if ($sportMetric === 'distance') return !empty($row['distance_known']) ? (float) ($row['distance_m'] ?? 0) : null;
            return !empty($row['duration_known']) ? (float) ($row['duration_s'] ?? 0) : null;
        }, $sportSeries);
        $knownSportValues = array_values(array_filter($values, static fn(?float $value): bool => $value !== null));
        $topMultiples[] = [
            'slug' => (string) $sportBreakdown['slug'],
            'name' => (string) $sportBreakdown['name'],
            'metric' => $sportMetric,
            'values' => $values,
            'series' => $sportSeries,
            'max' => max(1.0, ...($knownSportValues ?: [1.0])),
        ];
    }
}

$buildUrl = static function (array $changes) use ($period, $periodAnchor, $selectedSport, $metric): string {
    $params = ['period' => $period, 'sport' => $selectedSport, 'metric' => $metric];
    if ($periodAnchor !== '') $params['month'] = $periodAnchor;
    foreach ($changes as $key => $value) {
        if ($value === null || $value === '') unset($params[$key]); else $params[$key] = $value;
    }
    return '/user/progresso.php?' . http_build_query($params);
};

$flashes = stridebr_take_flashes();
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/product-insights.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/sport-hub.css')); ?>">
    <title><?php echo stridebr_e(stridebr_t('progress.page_title')); ?> | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body>
<div class="container-fluid">
<?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
<main class="main-content progress-page" data-progress-page>
    <div class="progress-shell">
        <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e((string) ($flash['type'] ?? 'info')); ?>"><?php echo stridebr_e((string) ($flash['message'] ?? '')); ?></div><?php endforeach; ?>

        <header class="progress-heading">
            <div>
                <span class="progress-eyebrow"><?php echo stridebr_e(stridebr_t('nav.progress')); ?></span>
                <h1><?php echo stridebr_e(stridebr_t('progress.page_title')); ?></h1>
            </div>
        </header>

        <nav class="segmented-nav progress-sport-nav" aria-label="<?php echo stridebr_e(stridebr_t('common.sport')); ?>">
            <a href="<?php echo stridebr_e($buildUrl(['sport' => 'all'])); ?>" class="<?php echo $selectedSport === 'all' ? 'is-active' : ''; ?>"<?php echo $selectedSport === 'all' ? ' aria-current="page"' : ''; ?>><?php echo stridebr_e(stridebr_t('progress.overview')); ?></a>
            <?php foreach ($availableSports as $sportMeta): $slug=(string)$sportMeta['slug']; ?><a href="<?php echo stridebr_e($buildUrl(['sport' => $slug])); ?>" class="<?php echo $selectedSport === $slug ? 'is-active' : ''; ?>"<?php echo $selectedSport === $slug ? ' aria-current="page"' : ''; ?>><?php echo stridebr_e(stridebr_sport_name($slug,(string)$sportMeta['name'])); ?></a><?php endforeach; ?>
        </nav>

        <div data-progress-dynamic>
        <form class="progress-filterbar" method="GET" action="/user/progresso.php" aria-label="<?php echo stridebr_e(stridebr_t('progress.filters_aria')); ?>">
            <label class="progress-filter-select"><span><?php echo stridebr_e(stridebr_t('progress.period')); ?></span><select name="period" data-progress-auto-submit>
                <?php foreach ($periodLabels as $key => $label): ?><option value="<?php echo stridebr_e($key); ?>"<?php echo $period === $key ? ' selected' : ''; ?>><?php echo stridebr_e($label); ?></option><?php endforeach; ?>
            </select></label>
            <input type="hidden" name="sport" value="<?php echo stridebr_e($selectedSport); ?>">
            <input type="hidden" name="metric" value="<?php echo stridebr_e($metric); ?>">
            <?php if ($periodAnchor !== ''): ?><input type="hidden" name="month" value="<?php echo stridebr_e($periodAnchor); ?>"><?php endif; ?>
            <noscript><button type="submit"><?php echo stridebr_e(stridebr_t('common.apply')); ?></button></noscript>
        </form>

        <div class="progress-context-line"><strong><?php echo stridebr_e($selectedSportLabel); ?></strong><span><?php echo stridebr_e($periodRangeLabel); ?></span></div>

        <?php if ($currentActivities === []): ?>
            <section class="progress-empty" aria-labelledby="progress-empty-title">
                <div><h2 id="progress-empty-title"><?php echo stridebr_e($selectedSport === 'all' ? stridebr_t('progress.empty_title') : stridebr_t('progress.empty_sport', ['sport' => $selectedSportLabel])); ?></h2><p><?php echo stridebr_e(stridebr_t('progress.empty_help')); ?></p></div>
                <div><a class="progress-button is-primary" href="/user/atividades.php?new=1"><?php echo stridebr_e(stridebr_t('progress.log_activity')); ?></a><a class="progress-button" href="/user/gravar-atividade.php"><?php echo stridebr_e(stridebr_t('home.record_gps')); ?></a></div>
            </section>
        <?php else: ?>
            <section class="progress-kpi-strip" aria-label="<?php echo stridebr_e(stridebr_t('progress.summary_aria')); ?>">
                <?php foreach ($summaryItems as $item): ?><div><span><?php echo stridebr_e($item['label']); ?></span><strong><?php echo stridebr_e($item['value']); ?></strong></div><?php endforeach; ?>
            </section>

            <?php if ($trainingLoad['eligible'] > 0): ?>
            <section class="progress-section progress-load" aria-labelledby="progress-load-title">
                <header><div><h2 id="progress-load-title"><?php echo stridebr_e(stridebr_t('progress.training_load')); ?></h2><p><?php echo stridebr_e(stridebr_t('progress.training_load_help')); ?></p></div></header>
                <div class="progress-kpi-strip"><div><span><?php echo stridebr_e(stridebr_t('progress.training_load')); ?></span><strong><?php echo stridebr_e(stridebr_format_number($trainingLoad['value'], 0)); ?> UA</strong><small><?php echo stridebr_e(stridebr_t('progress.rpe_coverage', ['covered'=>$trainingLoad['covered'], 'eligible'=>$trainingLoad['eligible']])); ?></small></div></div>
            </section>
            <?php endif; ?>

            <?php if ($progressGoals !== []): ?>
            <section class="progress-section" aria-labelledby="progress-goals-title">
                <header><div><h2 id="progress-goals-title"><?php echo stridebr_e(stridebr_t('nav.goals')); ?></h2><p><?php echo stridebr_e(stridebr_t('progress.goals_help')); ?></p></div><a class="progress-button" href="/user/metas.php"><?php echo stridebr_e(stridebr_t('progress.open_goals')); ?></a></header>
                <div class="progress-detail-list"><?php foreach ($progressGoals as $goal): ?><article><strong><?php echo stridebr_e(trim((string)($goal['nome'] ?? '')) ?: ($metricLabels[(string)$goal['metrica']] ?? (string)$goal['metrica'])); ?></strong><span><?php echo stridebr_e(stridebr_format_number((float)$goal['valor_alvo'], 1)); ?></span><small><?php echo stridebr_e(stridebr_t('progress.goal_period.' . (string)$goal['periodo'])); ?></small></article><?php endforeach; ?></div>
            </section>
            <?php endif; ?>

            <?php if ($renderer === 'strength' && is_array($strengthDashboard)): ?>
            <section class="progress-section" aria-labelledby="progress-exercises-title">
                <header><div><h2 id="progress-exercises-title"><?php echo stridebr_e(stridebr_t('progress.exercise_evolution')); ?></h2><p><?php echo stridebr_e(stridebr_t('progress.exercise_evolution_help')); ?></p></div></header>
                <div class="progress-detail-list">
                <?php foreach (array_slice((array) ($strengthDashboard['exercises'] ?? []), 0, 8) as $exercise): ?><article><strong><?php echo stridebr_e((string) ($exercise['nome'] ?? '')); ?></strong><span><?php if (is_numeric($exercise['latest_load'] ?? null)): ?><?php echo stridebr_e(stridebr_t('progress.last_session')); ?> · <?php echo stridebr_e(stridebr_format_number((float)$exercise['latest_load'], 1)); ?> kg<?php endif; ?></span><?php if (is_numeric($exercise['best_e1rm'] ?? null)): ?><small><?php echo stridebr_e(stridebr_t('progress.estimated_1rm')); ?> · <?php echo stridebr_e(stridebr_format_number((float)$exercise['best_e1rm'], 1)); ?> kg · <?php echo stridebr_e(stridebr_t('progress.estimated')); ?></small><?php endif; ?></article><?php endforeach; ?>
                </div>
            </section>
            <?php elseif (in_array($renderer, ['running','cycling','swimming'], true) && is_array($cardioDashboard)): $discipline = $renderer === 'running' ? 'run' : ($renderer === 'cycling' ? 'cycle' : 'swim'); $cardio = (array) (($cardioDashboard['summary'][$discipline]['current'] ?? [])); ?>
            <section class="progress-section" aria-labelledby="progress-performance-title">
                <header><div><h2 id="progress-performance-title"><?php echo stridebr_e(stridebr_t('progress.sport_performance')); ?></h2><p><?php echo stridebr_e(stridebr_t('progress.performance_context')); ?></p></div></header>
                <div class="progress-kpi-strip">
                    <?php if (is_numeric($cardio['best_pace_s'] ?? null) && $renderer !== 'cycling'): ?><div><span><?php echo stridebr_e(stridebr_t('progress.best_record')); ?></span><strong><?php echo stridebr_e($fmtDuration((float)$cardio['best_pace_s'])); ?><?php echo $renderer === 'swimming' ? '/100 m' : '/km'; ?></strong></div><?php endif; ?>
                    <?php if (is_numeric($cardio['avg_speed_kmh'] ?? null) && $renderer === 'cycling'): ?><div><span><?php echo stridebr_e(stridebr_t('progress.avg_speed')); ?></span><strong><?php echo stridebr_e(stridebr_format_number((float)$cardio['avg_speed_kmh'], 1)); ?> km/h</strong></div><?php endif; ?>
                    <?php if (is_numeric($cardio['avg_hr_bpm'] ?? null)): ?><div><span><?php echo stridebr_e(stridebr_t('progress.avg_hr')); ?></span><strong><?php echo stridebr_e(stridebr_format_number((float)$cardio['avg_hr_bpm'], 0)); ?> bpm</strong></div><?php endif; ?>
                    <?php if (is_numeric($cardio['avg_power_w'] ?? null)): ?><div><span><?php echo stridebr_e(stridebr_t('progress.avg_power')); ?></span><strong><?php echo stridebr_e(stridebr_format_number((float)$cardio['avg_power_w'], 0)); ?> W</strong></div><?php endif; ?>
                </div>
            </section>
            <?php elseif ($renderer === 'athletics' && is_array($athleticsDashboard)): ?>
            <section class="progress-section" aria-labelledby="progress-events-title"><header><div><h2 id="progress-events-title"><?php echo stridebr_e(stridebr_t('progress.by_event')); ?></h2><p><?php echo stridebr_e(stridebr_t('progress.athletics_training_note')); ?></p></div></header><div class="progress-detail-list"><?php foreach (array_merge((array)($athleticsDashboard['track'] ?? []),(array)($athleticsDashboard['field'] ?? [])) as $event): ?><article><strong><?php echo stridebr_e((string)($event['nome'] ?? '')); ?></strong><span><?php echo stridebr_e(stridebr_t('progress.best_record')); ?></span><small><?php echo stridebr_e(stridebr_format_number((float)($event['best_mark'] ?? $event['best_time_s'] ?? 0), 2)); ?></small></article><?php endforeach; ?></div></section>
            <?php elseif (in_array($renderer, ['team','racket','combat'], true) && is_array($sessionDashboard)): ?>
            <section class="progress-section" aria-labelledby="progress-session-title"><header><div><h2 id="progress-session-title"><?php echo stridebr_e($selectedSportLabel); ?></h2><p><?php echo stridebr_e(stridebr_t('progress.recent_details_help')); ?></p></div></header></section>
            <?php endif; ?>

            <section class="progress-section" aria-labelledby="progress-consistency-title">
                <header><div><h2 id="progress-consistency-title"><?php echo stridebr_e(stridebr_t('progress.consistency')); ?></h2><p><?php echo stridebr_e(stridebr_tn('progress.active_days_count.one', 'progress.active_days_count.other', (int) $currentSummary['active_days'], ['count' => (int) $currentSummary['active_days']])); ?></p></div></header>
                <div class="progress-consistency-wrap" tabindex="0" aria-label="<?php echo stridebr_e(stridebr_t('progress.consistency_aria')); ?>">
                    <div class="progress-consistency-grid" style="--progress-days:<?php echo count($consistencyDays); ?>">
                        <?php foreach ($consistencyDays as $day): $count = (int) $day['count']; $sportNames = []; foreach (array_keys((array) $day['sports']) as $slug) { foreach ($availableSports as $meta) if ((string) $meta['slug'] === (string) $slug) { $sportNames[] = stridebr_sport_name((string) $slug, (string) $meta['name']); break; } } $dayText = stridebr_tn('progress.consistency_day.one', 'progress.consistency_day.other', $count, ['date' => stridebr_format_date($day['date']), 'sports' => $sportNames !== [] ? ' · ' . implode(', ', $sportNames) : '']); ?>
                            <?php if ($count > 0): ?><button type="button" class="progress-consistency-day is-active" aria-label="<?php echo stridebr_e($dayText); ?>" data-progress-tooltip="<?php echo stridebr_e($dayText); ?>"><span aria-hidden="true"><?php echo $count > 1 ? $count : ''; ?></span></button><?php else: ?><span class="progress-consistency-day" aria-hidden="true"></span><?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="progress-consistency-legend"><span><i></i><?php echo stridebr_e(stridebr_t('progress.no_activity')); ?></span><span><i class="is-active"></i><?php echo stridebr_e(stridebr_t('progress.activity_recorded')); ?></span></div>
            </section>

            <section class="progress-section" aria-labelledby="progress-volume-title">
                <header><div><h2 id="progress-volume-title"><?php echo stridebr_e(stridebr_t('progress.volume')); ?></h2><p><?php echo stridebr_e($volumeSummary); ?></p></div><strong class="progress-section-unit"><?php echo stridebr_e($metricLabels[$metric]); ?> · <?php echo stridebr_e($selectedSportLabel); ?></strong></header>
                <div class="progress-bar-chart" style="--progress-week-count:<?php echo count($weekly); ?>" role="group" aria-label="<?php echo stridebr_e(stridebr_t('progress.volume_chart_aria', ['metric' => $metricLabels[$metric], 'sport' => $selectedSportLabel])); ?>">
                    <div class="progress-zero-line" aria-hidden="true"></div>
                    <?php foreach ($weekly as $index => $week): $value = $weeklyValues[$index] ?? null; $height = $value === null ? 0 : max(0, min(100, ($value / $maxWeekly) * 100)); $weekLabel = stridebr_t('progress.week_range', ['start' => stridebr_format_date_short($week['start']), 'end' => stridebr_format_date_short($week['end']->modify('-1 second'))]); $tooltipParts = [$weekLabel, stridebr_tn('progress.activity.one','progress.activity.other',(int)$week['activities'])]; if ((float)$week['distance_m'] > 0) $tooltipParts[] = $fmtDistance((float)$week['distance_m']); if ((float)$week['duration_s'] > 0) $tooltipParts[] = $fmtDuration((float)$week['duration_s']); if ((float)$week['elevation_m'] > 0) $tooltipParts[] = '+' . $fmtElevation((float)$week['elevation_m']); $tooltip = implode(' · ', $tooltipParts); ?>
                        <div class="progress-bar-column">
                            <button type="button" class="progress-bar-hit<?php echo $value === null ? ' is-missing' : ''; ?>" data-progress-tooltip="<?php echo stridebr_e($tooltip); ?>" aria-label="<?php echo stridebr_e($tooltip); ?>"><span class="progress-bar" style="height:<?php echo number_format($height, 2, '.', ''); ?>%"></span></button>
                            <small><?php echo $index % $weekLabelStep === 0 ? stridebr_e(stridebr_format_date_short($week['start'])) : ''; ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (in_array(null, $weeklyValues, true)): ?><p class="progress-chart-note"><?php echo stridebr_e(stridebr_t('progress.missing_data_note')); ?></p><?php endif; ?>
            </section>

            <section class="progress-section" aria-labelledby="progress-trend-title">
                <header><div><h2 id="progress-trend-title"><?php echo stridebr_e(stridebr_t('progress.trend')); ?></h2></div></header>
                <?php if ($trendUsesLine): ?>
                    <?php
                    $plotW = 1000.0; $plotH = 270.0; $left = 28.0; $top = 20.0; $bottom = 225.0; $usableW = $plotW - ($left * 2); $usableH = $bottom - $top; $lastIndex = max(1, count($weekly) - 1);
                    $segments = []; $currentSegment = [];
                    foreach ($weeklyValues as $idx => $value) {
                        if ($value === null) { if ($currentSegment !== []) { $segments[] = $currentSegment; $currentSegment = []; } continue; }
                        $x = $left + ($idx / $lastIndex) * $usableW; $y = $bottom - (($value / $maxWeekly) * $usableH); $currentSegment[] = [$x, $y, $idx, $value];
                    }
                    if ($currentSegment !== []) $segments[] = $currentSegment;
                    ?>
                    <div class="progress-line-chart-wrap">
                        <svg class="progress-line-chart<?php echo $trendDense ? ' is-dense' : ''; ?>" viewBox="0 0 1000 270" role="img" aria-labelledby="progress-trend-svg-title progress-trend-svg-desc">
                            <title id="progress-trend-svg-title"><?php echo stridebr_e($metricLabels[$metric]); ?></title><desc id="progress-trend-svg-desc"><?php echo stridebr_e(stridebr_t('progress.trend_chart_desc', ['sport' => $selectedSportLabel, 'period' => $periodLabels[$period]])); ?></desc>
                            <line x1="28" y1="225" x2="972" y2="225" class="progress-chart-axis" />
                            <line x1="28" y1="122.5" x2="972" y2="122.5" class="progress-chart-grid" />
                            <?php foreach ($segments as $segment): if (count($segment) < 2) continue; ?><polyline class="progress-line-path" points="<?php echo implode(' ', array_map(static fn(array $point): string => number_format($point[0], 1, '.', '') . ',' . number_format($point[1], 1, '.', ''), $segment)); ?>" /><?php endforeach; ?>
                            <?php foreach ($segments as $segment): foreach ($segment as [$x,$y,$idx,$value]): $week = $weekly[$idx]; $tooltip = stridebr_t('progress.week_range', ['start' => stridebr_format_date_short($week['start']), 'end' => stridebr_format_date_short($week['end']->modify('-1 second'))]) . ' · ' . $fmtMetric($metric, (float)$value); ?><circle cx="<?php echo number_format($x,1,'.',''); ?>" cy="<?php echo number_format($y,1,'.',''); ?>" r="8" tabindex="0" role="img" aria-label="<?php echo stridebr_e($tooltip); ?>" data-progress-tooltip="<?php echo stridebr_e($tooltip); ?>" class="progress-line-point"><title><?php echo stridebr_e($tooltip); ?></title></circle><?php endforeach; endforeach; ?>
                        </svg>
                    </div>
                <?php else: ?>
                    <div class="progress-trend-sparse" role="list">
                        <?php foreach ($weekly as $idx => $week): $value = $weeklyValues[$idx] ?? null; ?><div role="listitem"><span><?php echo stridebr_e(stridebr_format_date_short($week['start'])); ?></span><strong><?php echo $value === null ? '—' : stridebr_e($fmtMetric($metric, (float)$value)); ?></strong></div><?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!$trendUsesLine): ?><p class="progress-chart-note"><?php echo stridebr_e(stridebr_t('progress.trend_sparse_help', ['metric' => $metricLabels[$metric]])); ?></p><?php endif; ?>
            </section>

            <?php if ($breakdown !== []): ?>
            <section class="progress-section" aria-labelledby="progress-sports-title">
                <header><div><h2 id="progress-sports-title"><?php echo stridebr_e(stridebr_t('progress.modalities')); ?></h2></div></header>
                <div class="progress-sport-list">
                    <?php foreach ($breakdown as $item): $slug = (string)$item['slug']; ?><a href="<?php echo stridebr_e($buildUrl(['sport' => $slug])); ?>" class="progress-sport-row"><span class="progress-sport-icon"><?php echo stridebr_sport_icon_html($slug, 'sport-icon'); ?></span><span><strong><?php echo stridebr_e(stridebr_sport_name($slug, (string)$item['name'])); ?></strong><small><?php echo stridebr_e(stridebr_tn('progress.activity.one','progress.activity.other',(int)$item['activities'])); ?></small></span><span class="progress-sport-values"><?php if ((float)$item['distance_m'] > 0): ?><strong><?php echo stridebr_e($fmtDistance((float)$item['distance_m'])); ?></strong><?php endif; ?><?php if ((float)$item['duration_s'] > 0): ?><small><?php echo stridebr_e($fmtDuration((float)$item['duration_s'])); ?></small><?php endif; ?></span><span aria-hidden="true">›</span></a><?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <section class="progress-section" aria-labelledby="progress-compare-title">
                <header><div><h2 id="progress-compare-title"><?php echo stridebr_e(stridebr_t('progress.period_comparison')); ?></h2></div></header>
                <div class="progress-comparison-list">
                    <?php foreach ($comparisonItems as $item): ?><div><span><?php echo stridebr_e($item['label']); ?></span><strong><?php echo stridebr_e($item['current']); ?></strong><small><?php echo stridebr_e($item['delta']); ?> · <?php echo stridebr_e(stridebr_t('progress.vs_previous_period')); ?></small></div><?php endforeach; ?>
                </div>
            </section>

            <?php if ($topMultiples !== []): ?>
            <details class="progress-disclosure">
                <summary><?php echo stridebr_e(stridebr_t('progress.sport_trends')); ?><span><?php echo stridebr_e(stridebr_t('common.view_more')); ?></span></summary>
                <div class="progress-small-multiples">
                    <?php foreach ($topMultiples as $multiple): $multipleA11y = []; foreach ($multiple['values'] as $index => $value) { $week = $multiple['series'][$index] ?? null; if (!$week) continue; $weekText = stridebr_format_date_short($week['start']); $valueText = $value === null ? stridebr_t('progress.no_data') : $fmtMetric((string)$multiple['metric'], (float)$value); $multipleA11y[] = $weekText . ': ' . $valueText; } ?><article><header><span class="progress-sport-icon"><?php echo stridebr_sport_icon_html((string)$multiple['slug'], 'sport-icon'); ?></span><div><strong><?php echo stridebr_e(stridebr_sport_name((string)$multiple['slug'], (string)$multiple['name'])); ?></strong><small><?php echo stridebr_e($metricLabels[$multiple['metric']]); ?></small></div></header><span class="visually-hidden"><?php echo stridebr_e(implode(' · ', $multipleA11y)); ?></span><div class="progress-mini-bars" aria-hidden="true"><?php foreach ($multiple['values'] as $value): ?><?php if ($value === null): ?><i class="is-missing"></i><?php else: ?><i style="height:<?php echo number_format(max(4, min(100, ($value / $multiple['max']) * 100)),1,'.',''); ?>%"></i><?php endif; ?><?php endforeach; ?></div></article><?php endforeach; ?>
                </div>
            </details>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    </div>
    <div class="progress-tooltip" data-progress-tooltip-popover role="tooltip" hidden></div>
</main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/progresso.js')); ?>"></script>
</body>
</html>
