<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/sport_hub.php';
require_once dirname(__DIR__, 2) . '/src/function/dashboard.php';
require_once dirname(__DIR__, 2) . '/src/function/benchmarks.php';
require_once dirname(__DIR__, 2) . '/src/includes/sport_icons.php';

$validPeriods = ['all', '4w', '12w', '6m', '1y'];
$storedProgressPreferences = dashboardPreferenciasProgress($pdo, $idUsuario);
$requestedPeriod = isset($_GET['period']) ? stridebr_lower(trim((string) $_GET['period'])) : (string) ($storedProgressPreferences['period'] ?? 'all');
if (!in_array($requestedPeriod, $validPeriods, true)) $requestedPeriod = 'all';
$periodAnchor = trim((string) ($_GET['month'] ?? ''));

$availableSports = sportHubNavigationSports($pdo, $idUsuario);
$requestedSport = stridebr_lower(trim((string) ($_GET['sport'] ?? 'all')));
$selectedSport = 'all';
$selectedSportMeta = null;
foreach ($availableSports as $sportMeta) {
    if ((string) ($sportMeta['slug'] ?? '') !== $requestedSport) continue;
    $selectedSport = $requestedSport;
    $selectedSportMeta = $sportMeta;
    break;
}

$athleticsEvents = $selectedSport === 'atletismo' ? sportHubAthleticsEvents($pdo, $idUsuario) : [];
$requestedEvent = stridebr_lower(trim((string) ($_GET['event'] ?? '')));
$selectedEvent = '';
$selectedEventMeta = null;
if ($selectedSport === 'atletismo' && $requestedEvent !== '') {
    foreach ($athleticsEvents as $eventMeta) {
        if ((string) ($eventMeta['slug'] ?? '') !== $requestedEvent) continue;
        $selectedEvent = $requestedEvent;
        $selectedEventMeta = $eventMeta;
        break;
    }
}

$activityHistoryStart = $selectedEvent !== ''
    ? sportHubHistoryStart($athleticsEvents, 'all', $selectedEvent)
    : sportHubHistoryStart($availableSports, $selectedSport);
$selectedModalityId = $selectedSport !== 'all' && $selectedSport !== 'atletismo' ? trim((string) ($selectedSportMeta['idmodalidade'] ?? '')) : '';
$benchmarkHistoryStart = benchmarkHistoryStart($pdo, $idUsuario, $selectedModalityId !== '' ? $selectedModalityId : null);
if ($selectedSport === 'atletismo') $benchmarkHistoryStart = null;
$historyStart = $activityHistoryStart;
if ($benchmarkHistoryStart instanceof DateTimeImmutable && (!$historyStart instanceof DateTimeImmutable || $benchmarkHistoryStart < $historyStart)) $historyStart = $benchmarkHistoryStart;
$periodWindow = sportHubResolvePeriod($requestedPeriod, $periodAnchor, $historyStart);
$period = (string) $periodWindow['view'];
$now = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
$activityDays = null;
if ($period !== 'all') {
    $queryStart = !empty($periodWindow['has_previous']) && $periodWindow['previous_start'] instanceof DateTimeImmutable
        ? $periodWindow['previous_start']
        : $periodWindow['current_start'];
    $activityDays = min(36500, max(90, (int) ceil(max(0, $now->getTimestamp() - $queryStart->getTimestamp()) / 86400) + 14));
}
$activities = sportHubActivityRows($pdo, $idUsuario, $activityDays);
$filteredActivities = sportHubFilterSport($activities, $selectedSport, $selectedEvent);
$currentActivities = sportHubActivitiesInWindow($filteredActivities, $periodWindow['current_start'], $periodWindow['current_end']);
$navigationPeriodActivities = sportHubActivitiesInWindow($activities, $periodWindow['current_start'], $periodWindow['current_end']);
$previousActivities = !empty($periodWindow['has_previous']) && $periodWindow['previous_start'] instanceof DateTimeImmutable && $periodWindow['previous_end'] instanceof DateTimeImmutable
    ? sportHubActivitiesInWindow($filteredActivities, $periodWindow['previous_start'], $periodWindow['previous_end'])
    : [];
$currentSummary = sportHubSummaryMetrics($currentActivities);
$previousSummary = sportHubSummaryMetrics($previousActivities);
$selectedFamily = (string) ($selectedSportMeta['family'] ?? 'all');
$renderer = $selectedSport === 'all' ? 'overview' : sportHubProgressRenderer($selectedEvent !== '' ? $selectedEvent : $selectedSport, $selectedFamily);

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
$fmtPace = static function (float $seconds, string $suffix): string {
    $seconds = max(0, (int) round($seconds));
    return intdiv($seconds, 60) . ':' . str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT) . $suffix;
};
$fmtSignedPercent = static function (?float $value): string {
    if ($value === null) return '—';
    if (abs($value) < .05) return '0%';
    return ($value > 0 ? '+' : '−') . stridebr_format_number(abs($value), 0) . '%';
};
$fmtSeconds = static function (float $seconds): string {
    if ($seconds >= 60) {
        $minutes = intdiv((int) round($seconds), 60);
        $remaining = (int) round($seconds) % 60;
        return $minutes . ':' . str_pad((string) $remaining, 2, '0', STR_PAD_LEFT);
    }
    return stridebr_format_number($seconds, 2) . ' s';
};

$selectedSportLabel = $selectedSport === 'all'
    ? stridebr_t('progress.overview')
    : ($selectedEventMeta !== null
        ? stridebr_sport_name((string) $selectedEventMeta['slug'], (string) $selectedEventMeta['name'])
        : stridebr_sport_name((string) ($selectedSportMeta['slug'] ?? $selectedSport), (string) ($selectedSportMeta['name'] ?? $selectedSport)));
$periodLabels = [
    'all' => stridebr_t('progress.period.all'),
    '4w' => stridebr_t('progress.period.4w'),
    '12w' => stridebr_t('progress.period.12w'),
    '6m' => stridebr_t('progress.period.6m'),
    '1y' => stridebr_t('progress.period.1y'),
];
$periodContext = $period === 'all'
    ? stridebr_t('progress.since_date', ['date' => stridebr_format_date_short($periodWindow['current_start'])])
    : stridebr_t('progress.range_label', [
        'start' => stridebr_format_date_short($periodWindow['current_start']),
        'end' => stridebr_format_date_short($periodWindow['current_end']->modify('-1 second')),
    ]);

$sportsPracticed = [];
foreach ($currentActivities as $row) {
    $slug = ($row['hub_bucket'] ?? '') === 'athletics' ? 'atletismo' : stridebr_lower((string) ($row['modalidade_slug'] ?? ''));
    if ($slug !== '') $sportsPracticed[$slug] = true;
}

$strengthDashboard = null;
$cardioDashboard = null;
$athleticsDashboard = null;
$sessionDashboard = null;
if ($renderer === 'strength') $strengthDashboard = sportHubStrengthDashboard($pdo, $idUsuario, $filteredActivities, $periodWindow, $selectedSport === 'atletismo' ? '' : $selectedSport);
if (in_array($renderer, ['running', 'cycling', 'swimming'], true)) $cardioDashboard = sportHubCardioDashboard($filteredActivities, $periodWindow);
if ($renderer === 'athletics') $athleticsDashboard = sportHubAthleticsDashboard($pdo, $idUsuario, $filteredActivities, $periodWindow, $selectedEvent);
if (in_array($renderer, ['team', 'racket', 'combat'], true)) $sessionDashboard = sportHubSessionDashboard($filteredActivities, $selectedFamily, $periodWindow);

$allBenchmarks = benchmarkTableExists($pdo) ? benchmarkList($pdo, $idUsuario, ['limit' => 1000]) : [];
$selectedBenchmarks = $selectedModalityId !== '' ? array_values(array_filter($allBenchmarks, static fn(array $row): bool => (string) ($row['idmodalidade'] ?? '') === $selectedModalityId)) : [];
$oneRmRows = array_values(array_filter($selectedBenchmarks, static fn(array $row): bool => (string) ($row['tipo'] ?? '') === 'one_rm'));
$ftpSummary = benchmarkSummarize($selectedBenchmarks, 'ftp');
$cssSummary = benchmarkSummarize($selectedBenchmarks, 'css');
$distanceTestGroups = benchmarkGroupDistanceTests($selectedBenchmarks);
$oneRmByExercise = [];
foreach ($oneRmRows as $row) {
    $exerciseId = benchmarkReferenceGroupKey($row);
    $oneRmByExercise[$exerciseId][] = $row;
}
foreach ($oneRmByExercise as $exerciseId => $rows) $oneRmByExercise[$exerciseId] = benchmarkSummarize($rows, 'one_rm');
$strengthExercisesView = is_array($strengthDashboard) ? (array) ($strengthDashboard['exercises'] ?? []) : [];
if ($renderer === 'strength') {
    $knownExerciseIds = [];
    foreach ($strengthExercisesView as $exercise) $knownExerciseIds[(string) ($exercise['key'] ?? '')] = true;
    foreach ($oneRmByExercise as $exerciseId => $summary) {
        if (isset($knownExerciseIds[$exerciseId])) continue;
        $primary = (array) ($summary['primary'] ?? []);
        $strengthExercisesView[] = ['key'=>$exerciseId,'nome'=>(string)($primary['referencia_nome_snapshot'] ?? $primary['exercicio_nome'] ?? stridebr_t('benchmarks.exercise')),'latest_date'=>(string)($primary['data_resultado'] ?? ''),'current_best_load'=>null,'current_best_e1rm'=>null,'current_best_e1rm_source'=>null,'history'=>[]];
    }
}
$availableBenchmarkExercises = $renderer === 'strength' ? benchmarkAvailableExercises($pdo, $idUsuario) : [];
$availableBenchmarkExerciseIds = array_fill_keys(array_map(static fn(array $row): string => (string) ($row['idexercicio'] ?? ''), $availableBenchmarkExercises), true);

$summaryItems = [];
if ($renderer === 'overview') {
    $summaryItems = [
        ['label' => stridebr_t('progress.active_days'), 'value' => stridebr_format_number((float) $currentSummary['active_days'], 0)],
        ['label' => stridebr_t('progress.activities'), 'value' => stridebr_format_number((float) $currentSummary['activities'], 0)],
        ['label' => stridebr_t('progress.time_in_activity'), 'value' => $fmtDuration((float) $currentSummary['duration_s'])],
        ['label' => stridebr_t('progress.sports_practiced'), 'value' => stridebr_format_number((float) count($sportsPracticed), 0)],
    ];
} elseif ($renderer === 'strength' && is_array($strengthDashboard)) {
    $strengthSummary = (array) ($strengthDashboard['summary']['current'] ?? []);
    $summaryItems = [
        ['label' => stridebr_t('progress.workouts'), 'value' => stridebr_format_number((float) ($strengthSummary['workouts'] ?? 0), 0)],
        ['label' => stridebr_t('progress.sets'), 'value' => stridebr_format_number((float) ($strengthSummary['sets'] ?? 0), 0)],
        ['label' => stridebr_t('progress.repetitions'), 'value' => stridebr_format_number((float) ($strengthSummary['reps'] ?? 0), 0)],
        ['label' => stridebr_t('progress.load_volume'), 'value' => stridebr_format_number((float) ($strengthSummary['volume_kg'] ?? 0), 0) . ' kg'],
    ];
} elseif (in_array($renderer, ['running', 'cycling', 'swimming'], true) && is_array($cardioDashboard)) {
    $discipline = $renderer === 'running' ? 'run' : ($renderer === 'cycling' ? 'cycle' : 'swim');
    $cardioSummary = (array) ($cardioDashboard['summary'][$discipline]['current'] ?? []);
    if ($renderer === 'running') {
        $summaryItems = [
            ['label' => stridebr_t('progress.distance'), 'value' => $fmtDistance((float) ($cardioSummary['distance_m'] ?? 0))],
            ['label' => stridebr_t('progress.time'), 'value' => $fmtDuration((float) ($cardioSummary['duration_s'] ?? 0))],
            ['label' => stridebr_t('progress.runs'), 'value' => stridebr_format_number((float) ($cardioSummary['activities'] ?? 0), 0)],
            ['label' => stridebr_t('progress.active_days'), 'value' => stridebr_format_number((float) ($cardioSummary['active_days'] ?? 0), 0)],
        ];
    } elseif ($renderer === 'cycling') {
        $summaryItems = [
            ['label' => stridebr_t('progress.distance'), 'value' => $fmtDistance((float) ($cardioSummary['distance_m'] ?? 0))],
            ['label' => stridebr_t('progress.time'), 'value' => $fmtDuration((float) ($cardioSummary['duration_s'] ?? 0))],
            ['label' => stridebr_t('progress.rides'), 'value' => stridebr_format_number((float) ($cardioSummary['activities'] ?? 0), 0)],
            ['label' => stridebr_t('progress.elevation'), 'value' => stridebr_format_number((float) ($cardioSummary['elevation_m'] ?? 0), 0) . ' m'],
        ];
    } else {
        $summaryItems = [
            ['label' => stridebr_t('progress.distance'), 'value' => $fmtDistance((float) ($cardioSummary['distance_m'] ?? 0))],
            ['label' => stridebr_t('progress.time'), 'value' => $fmtDuration((float) ($cardioSummary['duration_s'] ?? 0))],
            ['label' => stridebr_t('progress.sessions'), 'value' => stridebr_format_number((float) ($cardioSummary['activities'] ?? 0), 0)],
        ];
    }
} elseif ($renderer === 'athletics' && is_array($athleticsDashboard)) {
    $athleticsSessions = [];
    foreach ($currentActivities as $row) $athleticsSessions[(string) ($row['idregistro'] ?? '')] = true;
    $summaryItems = [
        ['label' => stridebr_t('progress.sessions'), 'value' => stridebr_format_number((float) count(array_filter(array_keys($athleticsSessions))), 0)],
        ['label' => stridebr_t('progress.events_practiced'), 'value' => stridebr_format_number((float) ($athleticsDashboard['events_practiced'] ?? 0), 0)],
        ['label' => stridebr_t('progress.attempts'), 'value' => stridebr_format_number((float) ($athleticsDashboard['current_attempts'] ?? 0), 0)],
        ['label' => stridebr_t('progress.valid_marks_short'), 'value' => stridebr_format_number((float) ($athleticsDashboard['current_valid_attempts'] ?? 0), 0)],
    ];
} elseif (is_array($sessionDashboard)) {
    $sessionSummary = (array) ($sessionDashboard['summary']['current'] ?? []);
    $summaryItems = [
        ['label' => stridebr_t('progress.sessions'), 'value' => stridebr_format_number((float) ($sessionSummary['activities'] ?? 0), 0)],
        ['label' => stridebr_t('progress.time'), 'value' => $fmtDuration((float) ($sessionSummary['duration_s'] ?? 0))],
    ];
    if ((int) ($sessionSummary['matches'] ?? 0) > 0) $summaryItems[] = ['label' => $renderer === 'team' ? stridebr_t('progress.games') : stridebr_t('progress.matches'), 'value' => stridebr_format_number((float) $sessionSummary['matches'], 0)];
    if ($renderer === 'combat' && (int) ($sessionSummary['rounds'] ?? 0) > 0) $summaryItems[] = ['label' => stridebr_t('progress.rounds'), 'value' => stridebr_format_number((float) $sessionSummary['rounds'], 0)];
} else {
    $summaryItems = [
        ['label' => stridebr_t('progress.activities'), 'value' => stridebr_format_number((float) $currentSummary['activities'], 0)],
        ['label' => stridebr_t('progress.time'), 'value' => $fmtDuration((float) $currentSummary['duration_s'])],
        ['label' => stridebr_t('progress.active_days'), 'value' => stridebr_format_number((float) $currentSummary['active_days'], 0)],
    ];
}

$trainingLoad = sportHubTrainingLoad($currentActivities);
$highlights = [];
if ($renderer === 'running' && is_array($cardioDashboard)) {
    $run = (array) ($cardioDashboard['summary']['run']['current'] ?? []);
    if ((float) ($run['longest_distance_m'] ?? 0) > 0) $highlights[] = ['label' => stridebr_t('progress.longest_distance'), 'value' => $fmtDistance((float) $run['longest_distance_m']), 'detail' => ''];
    if (is_numeric($run['best_pace_s'] ?? null)) $highlights[] = ['label' => stridebr_t('progress.best_average_pace_activity'), 'value' => $fmtPace((float) $run['best_pace_s'], '/km'), 'detail' => stridebr_t('progress.registered_activity_average')];
} elseif ($renderer === 'cycling' && is_array($cardioDashboard)) {
    $cycle = (array) ($cardioDashboard['summary']['cycle']['current'] ?? []);
    if ((float) ($cycle['longest_distance_m'] ?? 0) > 0) $highlights[] = ['label' => stridebr_t('progress.longest_distance'), 'value' => $fmtDistance((float) $cycle['longest_distance_m']), 'detail' => ''];
} elseif ($renderer === 'strength' && is_array($strengthDashboard)) {
    $bestExercise = null;
    foreach ($strengthExercisesView as $exercise) {
        if (!is_numeric($exercise['current_best_e1rm'] ?? null)) continue;
        if ($bestExercise === null || (float) $exercise['current_best_e1rm'] > (float) $bestExercise['current_best_e1rm']) $bestExercise = $exercise;
    }
    if ($bestExercise !== null) {
        $source = (array) ($bestExercise['current_best_e1rm_source'] ?? []);
        $detail = isset($source['load_kg'], $source['reps']) ? stridebr_t('progress.e1rm_source', ['load' => stridebr_format_number((float) $source['load_kg'], 1), 'reps' => (string) $source['reps']]) : '';
        $highlights[] = ['label' => (string) ($bestExercise['nome'] ?? stridebr_t('progress.estimated_1rm')), 'value' => stridebr_format_number((float) $bestExercise['current_best_e1rm'], 1) . ' kg', 'detail' => $detail];
    }
} elseif ($renderer === 'athletics' && is_array($athleticsDashboard)) {
    foreach (array_slice((array) ($athleticsDashboard['track_records'] ?? []), 0, 2) as $record) {
        if (!is_numeric($record['best_time_s'] ?? null)) continue;
        $highlights[] = ['label' => stridebr_t('progress.best_time_registered') . ' · ' . (string) ($record['nome'] ?? ''), 'value' => $fmtSeconds((float) $record['best_time_s']), 'detail' => ''];
    }
    foreach (array_slice((array) ($athleticsDashboard['field_events'] ?? []), 0, max(0, 4 - count($highlights))) as $record) {
        if (!is_numeric($record['best_mark'] ?? null)) continue;
        $detail = !empty($record['wind_aided_best']) ? stridebr_t('progress.wind_above_limit') : '';
        $highlights[] = ['label' => stridebr_t('progress.best_mark_registered') . ' · ' . (string) ($record['nome'] ?? ''), 'value' => stridebr_format_number((float) $record['best_mark'], 2) . ' m', 'detail' => $detail];
    }
}
if (!empty($periodWindow['has_previous']) && count($highlights) < 4) {
    if ($renderer === 'strength' && is_array($strengthDashboard)) {
        $currentVolume = (float) ($strengthDashboard['summary']['current']['volume_kg'] ?? 0);
        $previousVolume = (float) ($strengthDashboard['summary']['previous']['volume_kg'] ?? 0);
        if ($previousVolume > 0 && abs($currentVolume - $previousVolume) > .01) $highlights[] = ['label' => stridebr_t('progress.volume_change'), 'value' => $fmtSignedPercent((($currentVolume - $previousVolume) / $previousVolume) * 100), 'detail' => stridebr_t('progress.vs_previous_period')];
    } elseif ($selectedSport !== 'all' && (float) $previousSummary['duration_s'] > 0 && abs((float) $currentSummary['duration_s'] - (float) $previousSummary['duration_s']) > 1) {
        $change = (((float) $currentSummary['duration_s'] - (float) $previousSummary['duration_s']) / (float) $previousSummary['duration_s']) * 100;
        $highlights[] = ['label' => stridebr_t('progress.volume_change'), 'value' => $fmtSignedPercent($change), 'detail' => stridebr_t('progress.vs_previous_period')];
    }
}
if ($renderer === 'overview' && count($highlights) < 4) {
    foreach (benchmarkHighlightFacts($allBenchmarks, $periodWindow['current_start'], $periodWindow['current_end']) as $fact) {
        $row = (array) ($fact['row'] ?? []);
        $type = (string) ($fact['type'] ?? '');
        $difference = (float) ($fact['difference'] ?? 0);
        if ($type === 'one_rm') {
            $highlights[] = ['label' => stridebr_t('benchmarks.highlight.new_one_rm'), 'value' => benchmarkFormatValue('one_rm', (float) $row['valor_canonico']), 'detail' => (string) ($row['referencia_nome_snapshot'] ?? $row['exercicio_nome'] ?? '')];
        } elseif ($type === 'ftp') {
            $highlights[] = ['label' => stridebr_t('benchmarks.highlight.ftp_updated'), 'value' => benchmarkFormatValue('ftp', (float) $row['valor_canonico']), 'detail' => benchmarkFormatDifference('ftp', $difference) . ' · ' . stridebr_t('benchmarks.since_previous')];
        } elseif ($type === 'css') {
            $highlights[] = ['label' => stridebr_t('benchmarks.highlight.css_updated'), 'value' => benchmarkFormatValue('css', (float) $row['valor_canonico']), 'detail' => benchmarkFormatDifference('css', $difference) . ' · ' . stridebr_t('benchmarks.since_previous')];
        } elseif ($type === 'distance_time') {
            $highlights[] = ['label' => stridebr_t('benchmarks.highlight.new_distance_test', ['distance' => benchmarkFormatDistance((float) ($row['distancia_m'] ?? 0))]), 'value' => benchmarkFormatValue('distance_time', (float) $row['valor_canonico']), 'detail' => benchmarkFormatDifference('distance_time', $difference) . ' · ' . stridebr_t('benchmarks.since_previous')];
        }
        if (count($highlights) >= 4) break;
    }
}
$highlights = array_slice($highlights, 0, 4);

$allGoals = dashboardListarMetas($pdo, $idUsuario, true);
$athleticsGoalSlugs = array_fill_keys(array_map(static fn(array $event): string => (string) ($event['slug'] ?? ''), $athleticsEvents), true);
$progressGoals = [];
foreach ($allGoals as $goal) {
    $goalSlug = stridebr_lower(trim((string) ($goal['modalidade_slug'] ?? '')));
    $globalGoal = $goalSlug === '';
    $matches = $selectedSport === 'all'
        || $globalGoal
        || ($selectedSport === 'atletismo' && ($selectedEvent !== '' ? $goalSlug === $selectedEvent : isset($athleticsGoalSlugs[$goalSlug])))
        || $goalSlug === $selectedSport;
    if (!$matches) continue;
    $progressGoals[] = $goal;
    if (count($progressGoals) >= 4) break;
}
$competitionModalityFilter = null;
if ($selectedSport !== 'all') {
    if ($selectedSport === 'atletismo') {
        $competitionModalityFilter = array_values(array_filter(array_map(static fn(array $event): string => (string) ($event['idmodalidade'] ?? ''), $selectedEvent !== '' && $selectedEventMeta !== null ? [$selectedEventMeta] : $athleticsEvents)));
    } elseif ($selectedModalityId !== '') {
        $competitionModalityFilter = $selectedModalityId;
    }
}
$recentCompetitions = competitionRecentForProgress($pdo, $idUsuario, $competitionModalityFilter, 3);

$sportPeriodSummary = sportHubSportsPeriodSummary($availableSports, $navigationPeriodActivities);

$navigationSports = $sportPeriodSummary;
if ($selectedSport !== 'all') {
    foreach ($navigationSports as $index => $sportItem) {
        if ((string) ($sportItem['slug'] ?? '') !== $selectedSport) continue;
        $currentNavigationSport = $sportItem;
        unset($navigationSports[$index]);
        array_unshift($navigationSports, $currentNavigationSport);
        break;
    }
    $navigationSports = array_values($navigationSports);
}
$desktopSportTabs = array_slice($navigationSports, 0, 5);
$desktopSportTabSlugs = array_fill_keys(array_map(static fn(array $item): string => (string) ($item['slug'] ?? ''), $desktopSportTabs), true);
$desktopSportOverflow = array_values(array_filter($navigationSports, static fn(array $item): bool => !isset($desktopSportTabSlugs[(string) ($item['slug'] ?? '')])));
$mobileSportTabSlugs = [];
foreach ($navigationSports as $sportItem) {
    $slug = (string) ($sportItem['slug'] ?? '');
    if ($slug === '') continue;
    if ($selectedSport !== 'all' && $slug === $selectedSport) $mobileSportTabSlugs[$slug] = true;
    if (count($mobileSportTabSlugs) >= 2) break;
}
foreach ($navigationSports as $sportItem) {
    if (count($mobileSportTabSlugs) >= 2) break;
    $slug = (string) ($sportItem['slug'] ?? '');
    if ($slug !== '') $mobileSportTabSlugs[$slug] = true;
}
$mobileOnlySportOverflow = array_values(array_filter($desktopSportTabs, static fn(array $item): bool => !isset($mobileSportTabSlugs[(string) ($item['slug'] ?? '')])));

$eventPeriodCounts = [];
if ($selectedSport === 'atletismo') {
    $athleticsPeriodActivities = sportHubActivitiesInWindow(sportHubFilterSport($activities, 'atletismo'), $periodWindow['current_start'], $periodWindow['current_end']);
    foreach ($athleticsPeriodActivities as $row) {
        $eventSlug = stridebr_lower((string) ($row['modalidade_slug'] ?? ''));
        if ($eventSlug !== '') $eventPeriodCounts[$eventSlug] = ($eventPeriodCounts[$eventSlug] ?? 0) + 1;
    }
}
$navigationEvents = $athleticsEvents;
usort($navigationEvents, static function (array $a, array $b) use ($selectedEvent, $eventPeriodCounts): int {
    $aSlug = (string) ($a['slug'] ?? '');
    $bSlug = (string) ($b['slug'] ?? '');
    if ($selectedEvent !== '') {
        if ($aSlug === $selectedEvent && $bSlug !== $selectedEvent) return -1;
        if ($bSlug === $selectedEvent && $aSlug !== $selectedEvent) return 1;
    }
    $periodCmp = (int) ($eventPeriodCounts[$bSlug] ?? 0) <=> (int) ($eventPeriodCounts[$aSlug] ?? 0);
    if ($periodCmp !== 0) return $periodCmp;
    $lastCmp = strcmp((string) ($b['last_activity'] ?? ''), (string) ($a['last_activity'] ?? ''));
    if ($lastCmp !== 0) return $lastCmp;
    return strnatcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
});
$desktopEventTabs = array_slice($navigationEvents, 0, 5);
$desktopEventTabSlugs = array_fill_keys(array_map(static fn(array $item): string => (string) ($item['slug'] ?? ''), $desktopEventTabs), true);
$desktopEventOverflow = array_values(array_filter($navigationEvents, static fn(array $item): bool => !isset($desktopEventTabSlugs[(string) ($item['slug'] ?? '')])));
$mobileEventTabSlugs = [];
foreach ($navigationEvents as $eventItem) {
    $slug = (string) ($eventItem['slug'] ?? '');
    if ($slug === '') continue;
    if ($selectedEvent !== '' && $slug === $selectedEvent) $mobileEventTabSlugs[$slug] = true;
    if (count($mobileEventTabSlugs) >= 2) break;
}
foreach ($navigationEvents as $eventItem) {
    if (count($mobileEventTabSlugs) >= 2) break;
    $slug = (string) ($eventItem['slug'] ?? '');
    if ($slug !== '') $mobileEventTabSlugs[$slug] = true;
}
$mobileOnlyEventOverflow = array_values(array_filter($desktopEventTabs, static fn(array $item): bool => !isset($mobileEventTabSlugs[(string) ($item['slug'] ?? '')])));

$primarySportSummary = array_slice($sportPeriodSummary, 0, 7);
$extraSportSummary = array_slice($sportPeriodSummary, 7);
$consistency = sportHubConsistencySummary($filteredActivities, $periodWindow);
$distribution = [];
if ($selectedSport === 'all') {
    foreach ($currentActivities as $row) {
        $slug = ($row['hub_bucket'] ?? '') === 'athletics' ? 'atletismo' : stridebr_lower((string) ($row['modalidade_slug'] ?? ''));
        if ($slug === '') continue;
        $duration = max(0.0, (float) ($row['duration_s'] ?? 0));
        if ($duration <= 0) continue;
        $distribution[$slug] = ($distribution[$slug] ?? 0.0) + $duration;
    }
    arsort($distribution);
    if (count($distribution) < 2) $distribution = [];
}
$distributionTotal = array_sum($distribution);

$series = $selectedSport === 'all' ? [] : sportHubPeriodSeries($currentActivities, $periodWindow);
$metricOptions = [];
if ($renderer === 'running') $metricOptions = ['distance' => stridebr_t('progress.distance'), 'duration' => stridebr_t('progress.time')];
elseif ($renderer === 'cycling') $metricOptions = ['duration' => stridebr_t('progress.time'), 'distance' => stridebr_t('progress.distance')];
elseif ($renderer === 'swimming') $metricOptions = ['distance' => stridebr_t('progress.distance')];
elseif ($renderer !== 'strength' && $renderer !== 'athletics') $metricOptions = ['duration' => stridebr_t('progress.time'), 'activities' => stridebr_t('progress.activities')];
$requestedMetric = stridebr_lower(trim((string) ($_GET['metric'] ?? '')));
$metric = isset($metricOptions[$requestedMetric]) ? $requestedMetric : (string) (array_key_first($metricOptions) ?? '');
$seriesValues = [];
foreach ($series as $bucket) {
    $seriesValues[] = match ($metric) {
        'distance' => (float) ($bucket['distance_m'] ?? 0),
        'duration' => (float) ($bucket['duration_s'] ?? 0),
        default => (float) ($bucket['activities'] ?? 0),
    };
}
$maxSeriesValue = max(1.0, ...($seriesValues ?: [1.0]));
$formatSeriesValue = static function (string $metricKey, float $value) use ($fmtDistance, $fmtDuration): string {
    return match ($metricKey) {
        'distance' => $fmtDistance($value),
        'duration' => $fmtDuration($value),
        default => stridebr_format_number($value, 0),
    };
};

$emptyMeta = $selectedEventMeta ?? $selectedSportMeta;
$hasAnyHistory = $selectedSport === 'all'
    ? array_sum(array_map(static fn(array $item): int => (int) ($item['history_count'] ?? 0), $availableSports)) > 0
    : (int) ($emptyMeta['history_count'] ?? 0) > 0;
$lastActivityRaw = (string) ($emptyMeta['last_activity'] ?? '');
$lastActivityLabel = '';
if ($lastActivityRaw !== '') {
    try { $lastActivityLabel = stridebr_t('progress.last_activity_date', ['date' => stridebr_format_date_short(new DateTimeImmutable($lastActivityRaw))]); } catch (Throwable) {}
}

$buildUrl = static function (array $changes) use ($period, $periodAnchor, $selectedSport, $selectedEvent, $metric): string {
    $params = ['period' => $period, 'sport' => $selectedSport];
    if ($selectedEvent !== '') $params['event'] = $selectedEvent;
    if ($metric !== '') $params['metric'] = $metric;
    if ($periodAnchor !== '') $params['month'] = $periodAnchor;
    foreach ($changes as $key => $value) {
        if ($value === null || $value === '') unset($params[$key]); else $params[$key] = $value;
    }
    return '/user/progresso.php?' . http_build_query($params);
};
$renderRecentCompetitions = static function (array $rows): void {
    if ($rows === []) return;
    ?>
    <section class="progress-section progress-competitions-section" aria-labelledby="progress-competitions-title">
        <header><div><h2 id="progress-competitions-title"><?php echo stridebr_e(stridebr_t('competitions.recent_progress')); ?></h2></div><a class="progress-button" href="/user/competicoes.php"><?php echo stridebr_e(stridebr_t('competitions.view_all')); ?></a></header>
        <div class="progress-competition-list">
            <?php foreach ($rows as $competition):
                $start = null; $end = null;
                try { $start = new DateTimeImmutable((string) ($competition['data_inicio'] ?? '')); } catch (Throwable) {}
                try { if (!empty($competition['data_fim'])) $end = new DateTimeImmutable((string) $competition['data_fim']); } catch (Throwable) {}
                $dateLabel = $start instanceof DateTimeImmutable ? stridebr_format_date_short($start) : '';
                if ($start instanceof DateTimeImmutable && $end instanceof DateTimeImmutable && $end->format('Y-m-d') !== $start->format('Y-m-d')) $dateLabel .= ' – ' . stridebr_format_date_short($end);
                $activityCount = (int) ($competition['atividades_count'] ?? 0);
                $benchmarkCount = (int) ($competition['benchmarks_count'] ?? 0);
            ?>
                <a href="/user/competicoes.php?id=<?php echo stridebr_e((string) ($competition['idcompeticao'] ?? '')); ?>">
                    <span><strong><?php echo stridebr_e((string) ($competition['nome'] ?? '')); ?></strong><small><?php echo stridebr_e($dateLabel); ?><?php if ($activityCount > 0): ?> · <?php echo stridebr_e((string) $activityCount . ' ' . stridebr_t('competitions.activities_short')); ?><?php endif; ?><?php if ($benchmarkCount > 0): ?> · <?php echo stridebr_e((string) $benchmarkCount . ' ' . stridebr_lower(stridebr_t('competitions.marks_tests'))); ?><?php endif; ?></small></span>
                    <em><?php echo stridebr_e(competitionStatusLabel((string) ($competition['status'] ?? 'realizada'))); ?></em>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
};
$benchmarkReturnTo = $buildUrl([]);
$benchmarkTypeByRenderer = ['strength'=>'one_rm','cycling'=>'ftp','swimming'=>'css','running'=>'distance_time'];
$allowedBenchmarkType = $benchmarkTypeByRenderer[$renderer] ?? '';
$editBenchmarkId = trim((string) ($_GET['edit_benchmark'] ?? ''));
$editBenchmark = $editBenchmarkId !== '' ? benchmarkGet($pdo, $idUsuario, $editBenchmarkId) : null;
$newBenchmarkType = stridebr_lower(trim((string) ($_GET['new_benchmark'] ?? '')));
$benchmarkDialogType = '';
if (is_array($editBenchmark) && (string) ($editBenchmark['tipo'] ?? '') === $allowedBenchmarkType && (string) ($editBenchmark['idmodalidade'] ?? '') === $selectedModalityId) $benchmarkDialogType = $allowedBenchmarkType;
elseif ($newBenchmarkType !== '' && $newBenchmarkType === $allowedBenchmarkType && $selectedModalityId !== '') $benchmarkDialogType = $allowedBenchmarkType;
$benchmarkFormRow = $benchmarkDialogType !== '' && is_array($editBenchmark) ? $editBenchmark : [];
$benchmarkFormDate = (string) ($benchmarkFormRow['data_resultado'] ?? (new DateTimeImmutable('today'))->format('Y-m-d'));
$benchmarkFormContext = (string) ($benchmarkFormRow['contexto'] ?? '');
$benchmarkFormNotes = (string) ($benchmarkFormRow['observacoes'] ?? '');
$benchmarkFormIsEdit = $benchmarkFormRow !== [];
$benchmarkFormOfficial = (string) ($benchmarkFormRow['oficialidade'] ?? '') === 'informado_oficial';
$benchmarkFormCompetition = trim((string) ($benchmarkFormRow['competicao_efetiva'] ?? $benchmarkFormRow['idcompeticao'] ?? ''));
$benchmarkLinkedActivityId = trim((string) ($benchmarkFormRow['idregistro'] ?? ''));
$benchmarkCompetitionOptions = $benchmarkDialogType !== '' ? competitionNearby($pdo, $idUsuario, $benchmarkFormDate, 12) : [];
if ($benchmarkFormCompetition !== '' && !array_filter($benchmarkCompetitionOptions, static fn(array $item): bool => (string)($item['idcompeticao']??'') === $benchmarkFormCompetition)) {
    $currentCompetition = competitionGet($pdo, $idUsuario, $benchmarkFormCompetition);
    if (is_array($currentCompetition)) array_unshift($benchmarkCompetitionOptions, $currentCompetition);
}
$benchmarkPrefillExercise = !$benchmarkFormIsEdit ? trim((string) ($_GET['exercise'] ?? '')) : '';
$benchmarkPrefillDistanceM = !$benchmarkFormIsEdit && is_numeric($_GET['distance_m'] ?? null) ? (float) $_GET['distance_m'] : null;
if ($benchmarkDialogType === 'one_rm' && $benchmarkPrefillExercise !== '') {
    foreach ($availableBenchmarkExercises as $exerciseOption) {
        if ((string) ($exerciseOption['idexercicio'] ?? '') === $benchmarkPrefillExercise) { $benchmarkFormRow['idexercicio'] = $benchmarkPrefillExercise; break; }
    }
}
if ($benchmarkDialogType === 'distance_time' && $benchmarkPrefillDistanceM !== null && $benchmarkPrefillDistanceM > 0 && $benchmarkPrefillDistanceM <= 1000000) $benchmarkFormRow['distancia_m'] = $benchmarkPrefillDistanceM;
$benchmarkDialogTitleKey = match ($benchmarkDialogType) {
    'one_rm' => 'benchmarks.register_one_rm',
    'ftp' => 'benchmarks.register_ftp',
    'css' => 'benchmarks.register_css',
    'distance_time' => 'benchmarks.register_test',
    default => 'benchmarks.title',
};
$benchmarkSubmitKey = $benchmarkFormIsEdit ? 'common.save' : match ($benchmarkDialogType) {
    'one_rm' => 'benchmarks.save_one_rm',
    'ftp' => 'benchmarks.save_ftp',
    'css' => 'benchmarks.save_css',
    'distance_time' => 'benchmarks.save_test',
    default => 'common.save',
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
        <header class="progress-heading"><div><span class="progress-eyebrow"><?php echo stridebr_e(stridebr_t('nav.progress')); ?></span><h1><?php echo stridebr_e(stridebr_t('progress.page_title')); ?></h1></div></header>
        <nav class="segmented-nav progress-sport-nav" aria-label="<?php echo stridebr_e(stridebr_t('common.sport')); ?>">
            <a href="<?php echo stridebr_e($buildUrl(['sport' => 'all', 'event' => null, 'metric' => null])); ?>" class="progress-overview-tab<?php echo $selectedSport === 'all' ? ' is-active' : ''; ?>"<?php echo $selectedSport === 'all' ? ' aria-current="page"' : ''; ?>><?php echo stridebr_e(stridebr_t('progress.overview')); ?></a>
            <?php foreach ($desktopSportTabs as $sportMeta): $slug = (string) $sportMeta['slug']; $mobilePrimary = isset($mobileSportTabSlugs[$slug]); ?>
                <a href="<?php echo stridebr_e($buildUrl(['sport' => $slug, 'event' => null, 'metric' => null])); ?>" class="progress-priority-tab<?php echo $mobilePrimary ? ' is-mobile-primary' : ''; ?><?php echo $selectedSport === $slug ? ' is-active' : ''; ?>"<?php echo $selectedSport === $slug ? ' aria-current="page"' : ''; ?>><?php echo stridebr_e($slug === 'atletismo' ? stridebr_t('progress.athletics') : stridebr_sport_name($slug, (string) $sportMeta['name'])); ?></a>
            <?php endforeach; ?>
            <?php if ($desktopSportOverflow !== [] || $mobileOnlySportOverflow !== []): ?>
                <details class="progress-nav-more<?php echo $desktopSportOverflow === [] ? ' is-mobile-only-trigger' : ''; ?>" data-progress-nav-more>
                    <summary><?php echo stridebr_e(stridebr_t('progress.more')); ?></summary>
                    <div class="progress-nav-menu" role="group" aria-label="<?php echo stridebr_e(stridebr_t('progress.more_sports')); ?>">
                        <?php if ($selectedSport !== 'all' && $selectedSportMeta !== null): $currentMenuSlug = (string) ($selectedSportMeta['slug'] ?? $selectedSport); ?>
                            <a class="progress-nav-current-item" href="<?php echo stridebr_e($buildUrl(['sport' => $currentMenuSlug, 'event' => null, 'metric' => null])); ?>" aria-current="page"><?php echo stridebr_sport_icon_html($currentMenuSlug, 'sport-icon'); ?><span><?php echo stridebr_e($currentMenuSlug === 'atletismo' ? stridebr_t('progress.athletics') : stridebr_sport_name($currentMenuSlug, (string) ($selectedSportMeta['name'] ?? $currentMenuSlug))); ?></span></a>
                        <?php endif; ?>
                        <?php foreach ($mobileOnlySportOverflow as $sportMeta): $slug = (string) $sportMeta['slug']; ?>
                            <a class="is-mobile-only" href="<?php echo stridebr_e($buildUrl(['sport' => $slug, 'event' => null, 'metric' => null])); ?>"><?php echo stridebr_sport_icon_html($slug, 'sport-icon'); ?><span><?php echo stridebr_e($slug === 'atletismo' ? stridebr_t('progress.athletics') : stridebr_sport_name($slug, (string) $sportMeta['name'])); ?></span></a>
                        <?php endforeach; ?>
                        <?php foreach ($desktopSportOverflow as $sportMeta): $slug = (string) $sportMeta['slug']; ?>
                            <a href="<?php echo stridebr_e($buildUrl(['sport' => $slug, 'event' => null, 'metric' => null])); ?>"<?php echo $selectedSport === $slug ? ' aria-current="page"' : ''; ?>><?php echo stridebr_sport_icon_html($slug, 'sport-icon'); ?><span><?php echo stridebr_e($slug === 'atletismo' ? stridebr_t('progress.athletics') : stridebr_sport_name($slug, (string) $sportMeta['name'])); ?></span></a>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
        </nav>
        <div data-progress-dynamic>
            <?php if ($selectedSport === 'atletismo'): ?>
                <nav class="sport-subtabs progress-event-nav" aria-label="<?php echo stridebr_e(stridebr_t('progress.by_event')); ?>">
                    <a href="<?php echo stridebr_e($buildUrl(['event' => null, 'metric' => null])); ?>" class="progress-event-summary-tab<?php echo $selectedEvent === '' ? ' is-active' : ''; ?>"<?php echo $selectedEvent === '' ? ' aria-current="page"' : ''; ?>><?php echo stridebr_e(stridebr_t('progress.event_summary')); ?></a>
                    <?php foreach ($desktopEventTabs as $eventMeta): $eventSlug = (string) $eventMeta['slug']; $mobilePrimary = isset($mobileEventTabSlugs[$eventSlug]); ?>
                        <a href="<?php echo stridebr_e($buildUrl(['event' => $eventSlug, 'metric' => null])); ?>" class="progress-priority-tab<?php echo $mobilePrimary ? ' is-mobile-primary' : ''; ?><?php echo $selectedEvent === $eventSlug ? ' is-active' : ''; ?>"<?php echo $selectedEvent === $eventSlug ? ' aria-current="page"' : ''; ?>><?php echo stridebr_e(stridebr_sport_name($eventSlug, (string) $eventMeta['name'])); ?></a>
                    <?php endforeach; ?>
                    <?php if ($desktopEventOverflow !== [] || $mobileOnlyEventOverflow !== []): ?>
                        <details class="progress-nav-more<?php echo $desktopEventOverflow === [] ? ' is-mobile-only-trigger' : ''; ?>" data-progress-nav-more>
                            <summary><?php echo stridebr_e(stridebr_t('progress.more')); ?></summary>
                            <div class="progress-nav-menu" role="group" aria-label="<?php echo stridebr_e(stridebr_t('progress.more_events')); ?>">
                                <?php if ($selectedEvent !== '' && $selectedEventMeta !== null): ?>
                                    <a class="progress-nav-current-item" href="<?php echo stridebr_e($buildUrl(['event' => $selectedEvent, 'metric' => null])); ?>" aria-current="page"><span><?php echo stridebr_e(stridebr_sport_name($selectedEvent, (string) ($selectedEventMeta['name'] ?? $selectedEvent))); ?></span></a>
                                <?php endif; ?>
                                <?php foreach ($mobileOnlyEventOverflow as $eventMeta): $eventSlug = (string) $eventMeta['slug']; ?>
                                    <a class="is-mobile-only" href="<?php echo stridebr_e($buildUrl(['event' => $eventSlug, 'metric' => null])); ?>"><span><?php echo stridebr_e(stridebr_sport_name($eventSlug, (string) $eventMeta['name'])); ?></span></a>
                                <?php endforeach; ?>
                                <?php foreach ($desktopEventOverflow as $eventMeta): $eventSlug = (string) $eventMeta['slug']; ?>
                                    <a href="<?php echo stridebr_e($buildUrl(['event' => $eventSlug, 'metric' => null])); ?>"<?php echo $selectedEvent === $eventSlug ? ' aria-current="page"' : ''; ?>><span><?php echo stridebr_e(stridebr_sport_name($eventSlug, (string) $eventMeta['name'])); ?></span></a>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>

            <form class="progress-filterbar" method="GET" action="/user/progresso.php" aria-label="<?php echo stridebr_e(stridebr_t('progress.filters_aria')); ?>" data-progress-preference-url="/api/progress-preferences.php" data-progress-csrf-token="<?php echo stridebr_e(stridebr_csrf_token()); ?>">
                <label class="progress-filter-select"><span><?php echo stridebr_e(stridebr_t('progress.period')); ?></span><select name="period" data-progress-auto-submit data-progress-period-select>
                    <?php foreach ($periodLabels as $key => $label): ?><option value="<?php echo stridebr_e($key); ?>"<?php echo $period === $key ? ' selected' : ''; ?>><?php echo stridebr_e($label); ?></option><?php endforeach; ?>
                </select></label>
                <input type="hidden" name="sport" value="<?php echo stridebr_e($selectedSport); ?>">
                <?php if ($selectedEvent !== ''): ?><input type="hidden" name="event" value="<?php echo stridebr_e($selectedEvent); ?>"><?php endif; ?>
                <?php if ($metricOptions !== [] && $metric !== ''): ?><input type="hidden" name="metric" value="<?php echo stridebr_e($metric); ?>"><?php endif; ?>
                <?php if ($periodAnchor !== ''): ?><input type="hidden" name="month" value="<?php echo stridebr_e($periodAnchor); ?>"><?php endif; ?>
                <noscript><button type="submit"><?php echo stridebr_e(stridebr_t('common.apply')); ?></button></noscript>
            </form>

            <div class="progress-context-line"><strong><?php echo stridebr_e($selectedSportLabel); ?></strong><span><?php echo stridebr_e($periodContext); ?></span></div>

            <?php if ($currentActivities === []): ?>
                <section class="progress-empty" aria-labelledby="progress-empty-title">
                    <div>
                        <h2 id="progress-empty-title"><?php echo stridebr_e($selectedSport === 'all' ? stridebr_t('progress.no_activity_in_period') : ($hasAnyHistory ? stridebr_t('progress.no_sport_in_period', ['sport' => $selectedSportLabel]) : stridebr_t('progress.no_sport_data_yet', ['sport' => $selectedSportLabel]))); ?></h2>
                        <?php if ($lastActivityLabel !== ''): ?><p><?php echo stridebr_e($lastActivityLabel); ?></p><?php elseif (!$hasAnyHistory): ?><p><?php echo stridebr_e(stridebr_t('progress.no_activities_registered')); ?></p><?php endif; ?>
                    </div>
                    <div>
                        <?php if ($hasAnyHistory && $period !== 'all'): ?><a class="progress-button" href="<?php echo stridebr_e($buildUrl(['period' => 'all'])); ?>"><?php echo stridebr_e(stridebr_t('progress.view_all_history')); ?></a><?php endif; ?>
                        <?php if (!$hasAnyHistory): ?><a class="progress-button is-primary" href="/user/atividades.php?new=1"><?php echo stridebr_e(stridebr_t('progress.log_activity')); ?></a><?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="progress-section progress-summary-section" aria-labelledby="progress-summary-title">
                <header><div><h2 id="progress-summary-title"><?php echo stridebr_e(stridebr_t('progress.period_summary')); ?></h2></div></header>
                <div class="progress-kpi-strip" aria-label="<?php echo stridebr_e(stridebr_t('progress.summary_aria')); ?>"><?php foreach ($summaryItems as $item): ?><div><span><?php echo stridebr_e($item['label']); ?></span><strong><?php echo stridebr_e($item['value']); ?></strong></div><?php endforeach; ?></div>
            </section>

            <?php if ($renderer === 'overview'): ?>
                <?php if ($highlights !== []): ?><section class="progress-section" aria-labelledby="progress-highlights-title"><header><div><h2 id="progress-highlights-title"><?php echo stridebr_e(stridebr_t('progress.highlights')); ?></h2></div></header><div class="progress-highlight-grid"><?php foreach ($highlights as $item): ?><article><span><?php echo stridebr_e($item['label']); ?></span><strong><?php echo stridebr_e($item['value']); ?></strong><?php if ($item['detail'] !== ''): ?><small><?php echo stridebr_e($item['detail']); ?></small><?php endif; ?></article><?php endforeach; ?></div></section><?php endif; ?>

                <section class="progress-section" aria-labelledby="progress-sports-title">
                    <header><div><h2 id="progress-sports-title"><?php echo stridebr_e(stridebr_t('progress.your_sports')); ?></h2></div></header>
                    <div class="progress-sport-list">
                        <?php $renderSportRow = static function (array $sportItem) use ($buildUrl, $fmtDistance, $fmtDuration): void { $sportSlug = (string) ($sportItem['slug'] ?? ''); $sportName = $sportSlug === 'atletismo' ? stridebr_t('progress.athletics') : stridebr_sport_name($sportSlug, (string) ($sportItem['name'] ?? $sportSlug)); $hasPeriodActivity = (int) ($sportItem['activities'] ?? 0) > 0; ?>
                            <a href="<?php echo stridebr_e($buildUrl(['sport' => $sportSlug, 'event' => null, 'metric' => null])); ?>" class="progress-sport-row<?php echo $hasPeriodActivity ? '' : ' is-period-empty'; ?>">
                                <span class="progress-sport-icon"><?php echo stridebr_sport_icon_html($sportSlug, 'sport-icon'); ?></span>
                                <span><strong><?php echo stridebr_e($sportName); ?></strong><small><?php if ($hasPeriodActivity): ?><?php echo stridebr_e(stridebr_tn('progress.activity.one', 'progress.activity.other', (int) $sportItem['activities'])); ?><?php elseif ((int) ($sportItem['history_count'] ?? 0) > 0 && !empty($sportItem['last_activity'])): ?><?php try { echo stridebr_e(stridebr_t('progress.last_activity_date', ['date' => stridebr_format_date_short(new DateTimeImmutable((string) $sportItem['last_activity']))])); } catch (Throwable) {} ?><?php else: ?><?php echo stridebr_e(stridebr_t('progress.no_activities_registered')); ?><?php endif; ?></small></span>
                                <span class="progress-sport-values"><?php if ((float) ($sportItem['distance_m'] ?? 0) > 0): ?><strong><?php echo stridebr_e($fmtDistance((float) $sportItem['distance_m'])); ?></strong><?php endif; ?><?php if ((float) ($sportItem['duration_s'] ?? 0) > 0): ?><small><?php echo stridebr_e($fmtDuration((float) $sportItem['duration_s'])); ?></small><?php endif; ?></span><span class="progress-row-chevron" aria-hidden="true">›</span>
                            </a>
                        <?php }; ?>
                        <?php foreach ($primarySportSummary as $sportItem) $renderSportRow($sportItem); ?>
                        <?php if ($extraSportSummary !== []): ?>
                            <details class="progress-list-disclosure">
                                <summary><span class="is-closed"><?php echo stridebr_e(stridebr_t('progress.show_more_sports', ['count' => (string) count($extraSportSummary)])); ?></span><span class="is-open"><?php echo stridebr_e(stridebr_t('progress.show_fewer_sports')); ?></span></summary>
                                <div class="progress-sport-list progress-sport-list-extra"><?php foreach ($extraSportSummary as $sportItem) $renderSportRow($sportItem); ?></div>
                            </details>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if ($progressGoals !== []): ?><section class="progress-section progress-goals-section" aria-labelledby="progress-goals-title"><header><div><h2 id="progress-goals-title"><?php echo stridebr_e(stridebr_t('common.goals')); ?></h2></div><a class="progress-button" href="/user/metas.php"><?php echo stridebr_e(stridebr_t('progress.open_goals')); ?></a></header><div class="progress-goal-list"><?php foreach ($progressGoals as $goal): ?><article><div><strong><?php echo stridebr_e(dashboardMetaTitulo($goal)); ?></strong><small><?php echo stridebr_e(dashboardMetaPrazoLabel($goal)); ?></small></div><div><span><?php echo stridebr_e(dashboardMetaCompactValue($goal)); ?></span><?php if ((string) ($goal['tipo_meta'] ?? 'metrica') !== 'benchmark' || !empty($goal['percentual_disponivel'])): ?><progress max="100" value="<?php echo stridebr_e((string) min(100, max(0, (float) ($goal['percentual'] ?? 0)))); ?>"></progress><?php endif; ?></div></article><?php endforeach; ?></div></section><?php endif; ?>

                <?php $renderRecentCompetitions($recentCompetitions); ?>

                <section class="progress-section" aria-labelledby="progress-consistency-title"><header><div><h2 id="progress-consistency-title"><?php echo stridebr_e(stridebr_t('progress.consistency')); ?></h2><p><?php echo stridebr_e(stridebr_t('progress.active_weeks_summary', ['active' => (string) $consistency['active'], 'total' => (string) $consistency['total']])); ?></p></div></header><div class="progress-consistency-band" role="list" aria-label="<?php echo stridebr_e(stridebr_t('progress.consistency_weeks_aria')); ?>"><?php foreach ($consistency['units'] as $unit): ?><span role="listitem" class="<?php echo !empty($unit['active']) ? 'is-active' : ''; ?>" title="<?php echo stridebr_e(stridebr_t('progress.week_activity_count', ['date' => stridebr_format_date_short($unit['start']), 'count' => (string) $unit['activities']])); ?>"></span><?php endforeach; ?></div></section>

                <?php if ($distribution !== [] && $distributionTotal > 0): $distributionPrimary = array_slice($distribution, 0, 6, true); $distributionExtra = array_slice($distribution, 6, null, true); $renderDistributionRows = static function (array $rows) use ($distributionTotal, $fmtDuration): void { foreach ($rows as $slug => $seconds): $name = $slug === 'atletismo' ? stridebr_t('progress.athletics') : stridebr_sport_name((string) $slug, (string) $slug); $share = ($seconds / $distributionTotal) * 100; ?><div><span><strong><?php echo stridebr_e($name); ?></strong><small><?php echo stridebr_e($fmtDuration((float) $seconds)); ?></small></span><div><i style="width:<?php echo number_format($share, 2, '.', ''); ?>%"></i></div><b><?php echo stridebr_e(stridebr_format_number($share, 0)); ?>%</b></div><?php endforeach; }; ?><section class="progress-section" aria-labelledby="progress-distribution-title"><header><div><h2 id="progress-distribution-title"><?php echo stridebr_e(stridebr_t('progress.practice_distribution')); ?></h2><p><?php echo stridebr_e(stridebr_t('progress.practice_distribution_help')); ?></p></div></header><div class="progress-distribution-list"><?php $renderDistributionRows($distributionPrimary); ?></div><?php if ($distributionExtra !== []): ?><details class="progress-list-disclosure progress-distribution-disclosure"><summary><span class="is-closed"><?php echo stridebr_e(stridebr_t('progress.show_more_sports', ['count' => (string) count($distributionExtra)])); ?></span><span class="is-open"><?php echo stridebr_e(stridebr_t('progress.show_fewer_sports')); ?></span></summary><div class="progress-distribution-list progress-distribution-extra"><?php $renderDistributionRows($distributionExtra); ?></div></details><?php endif; ?></section><?php endif; ?>
            <?php else: ?>
                <?php if ($highlights !== []): ?><section class="progress-section" aria-labelledby="progress-highlights-title"><header><div><h2 id="progress-highlights-title"><?php echo stridebr_e(stridebr_t('progress.highlights')); ?></h2></div></header><div class="progress-highlight-grid"><?php foreach ($highlights as $item): ?><article><span><?php echo stridebr_e($item['label']); ?></span><strong><?php echo stridebr_e($item['value']); ?></strong><?php if ($item['detail'] !== ''): ?><small><?php echo stridebr_e($item['detail']); ?></small><?php endif; ?></article><?php endforeach; ?></div></section><?php endif; ?>

                <?php if ($renderer === 'strength' && is_array($strengthDashboard)): ?>
                    <section class="progress-section" aria-labelledby="progress-exercises-title">
                        <header><div><h2 id="progress-exercises-title"><?php echo stridebr_e(stridebr_t('progress.exercise_progress')); ?></h2><p><?php echo stridebr_e(stridebr_t('benchmarks.strength_help')); ?></p></div><?php if ($selectedModalityId !== ''): ?><a class="progress-button" href="<?php echo stridebr_e($buildUrl(['new_benchmark'=>'one_rm'])); ?>"><?php echo stridebr_e(stridebr_t('benchmarks.register_one_rm')); ?></a><?php endif; ?></header>
                        <?php if ($oneRmRows === []): ?><p class="progress-benchmark-empty"><?php echo stridebr_e(stridebr_t('benchmarks.empty_one_rm')); ?></p><?php endif; ?>
                        <?php if (count($strengthExercisesView) > 8): ?><label class="progress-exercise-search"><span><?php echo stridebr_e(stridebr_t('progress.search_exercise')); ?></span><input type="search" placeholder="<?php echo stridebr_e(stridebr_t('progress.search_exercise_placeholder')); ?>" data-progress-exercise-search></label><?php endif; ?>
                        <div class="strength-exercise-list progress-strength-exercise-list" data-progress-exercise-list>
                            <?php foreach ($strengthExercisesView as $exercise): $exerciseKey=(string)($exercise['key']??''); $source=(array)($exercise['current_best_e1rm_source']??[]); $measuredSummary=(array)($oneRmByExercise[$exerciseKey]??[]); $measuredBest=is_array($measuredSummary['best']??null)?$measuredSummary['best']:null; $measuredHistory=(array)($measuredSummary['history']??[]); $e1rmObservation=benchmarkObservationEstimatedOneRm($exercise); ?>
                                <article data-progress-exercise="<?php echo stridebr_e(stridebr_lower((string) ($exercise['nome'] ?? ''))); ?>">
                                    <div><strong><?php echo stridebr_e((string) ($exercise['nome'] ?? '')); ?></strong><?php if (!empty($exercise['latest_date'])): ?><small><?php echo stridebr_e(stridebr_t('progress.last_activity_date', ['date' => stridebr_format_date_short(new DateTimeImmutable((string) $exercise['latest_date']))])); ?></small><?php endif; ?></div>
                                    <div class="strength-exercise-values">
                                        <?php if (is_numeric($exercise['current_best_load'] ?? null)): ?><span><small><?php echo stridebr_e(stridebr_t('progress.best_load')); ?></small><b><?php echo stridebr_e(stridebr_format_number((float) $exercise['current_best_load'], 1)); ?> kg</b></span><?php endif; ?>
                                        <?php if (is_array($measuredBest)): ?><span><small><?php echo stridebr_e(stridebr_t('benchmarks.measured_one_rm')); ?></small><b><?php echo stridebr_e(benchmarkFormatValue('one_rm',(float)$measuredBest['valor_canonico'])); ?></b><small><?php echo stridebr_e(stridebr_t('benchmarks.measured_on',['date'=>stridebr_format_date_short(new DateTimeImmutable((string)$measuredBest['data_resultado']))])); ?></small></span><?php endif; ?>
                                        <?php if (is_array($e1rmObservation)): ?><span><small><?php echo stridebr_e(stridebr_t('progress.estimated_1rm')); ?></small><b><?php echo stridebr_e(benchmarkFormatValue('one_rm',(float)$e1rmObservation['value'])); ?></b><?php if (isset($source['load_kg'], $source['reps'])): ?><small><?php echo stridebr_e(stridebr_t('progress.e1rm_source', ['load' => stridebr_format_number((float) $source['load_kg'], 1), 'reps' => (string) $source['reps']])); ?><?php if (!empty($source['idregistro'])): ?> · <a href="/user/atividades.php?highlight=<?php echo rawurlencode((string)$source['idregistro']); ?>"><?php echo stridebr_e(stridebr_t('benchmarks.view_source')); ?></a><?php endif; ?></small><?php endif; ?></span><?php endif; ?>
                                    </div>
                                    <?php if ($measuredHistory !== []): ?><details class="progress-benchmark-history"><summary><?php echo stridebr_e(stridebr_t('benchmarks.history')); ?> · <?php echo count($measuredHistory); ?></summary><div><?php foreach ($measuredHistory as $record): ?><article class="progress-benchmark-history-row"><div><strong><?php echo stridebr_e(benchmarkFormatValue('one_rm',(float)$record['valor_canonico'])); ?></strong><small><?php echo stridebr_e(stridebr_format_date_short(new DateTimeImmutable((string)$record['data_resultado']))); ?><?php $competitionName=trim((string)($record['competicao_nome']??'')); if($competitionName!==''): ?> · <?php echo stridebr_e($competitionName); ?><?php endif; ?><?php $officialLabel=benchmarkOfficialityLabel((string)($record['oficialidade']??'')); if($officialLabel!==''): ?> · <?php echo stridebr_e($officialLabel); ?><?php endif; ?></small></div><div class="progress-benchmark-actions"><a href="<?php echo stridebr_e($buildUrl(['edit_benchmark'=>(string)$record['idbenchmark']])); ?>"><?php echo stridebr_e(stridebr_t('common.edit')); ?></a><form method="POST" action="/api/progress-benchmarks.php" data-confirm="<?php echo stridebr_e(stridebr_t('benchmarks.delete_confirm')); ?>"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="idbenchmark" value="<?php echo stridebr_e((string)$record['idbenchmark']); ?>"><input type="hidden" name="return_to" value="<?php echo stridebr_e($benchmarkReturnTo); ?>"><button type="submit"><?php echo stridebr_e(stridebr_t('common.delete')); ?></button></form></div></article><?php endforeach; ?></div></details><?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php if ((array) ($strengthDashboard['direct_muscles'] ?? []) !== []): ?><section class="progress-section" aria-labelledby="progress-muscles-title"><header><div><h2 id="progress-muscles-title"><?php echo stridebr_e(stridebr_t('progress.direct_sets')); ?></h2></div></header><div class="muscle-bars"><?php $maxDirect = max(1, ...(array_values((array) $strengthDashboard['direct_muscles']) ?: [1])); foreach ((array) $strengthDashboard['direct_muscles'] as $muscle => $sets): ?><div><span><?php echo stridebr_e(stridebr_t('progress.muscle.' . str_replace('-', '_', (string) $muscle))); ?></span><div><i style="width:<?php echo number_format(((int) $sets / $maxDirect) * 100, 2, '.', ''); ?>%"></i></div><strong><?php echo stridebr_e((string) $sets); ?></strong></div><?php endforeach; ?></div></section><?php endif; ?>
                    <?php if ((array) ($strengthDashboard['secondary_muscles'] ?? []) !== []): ?><section class="progress-section" aria-labelledby="progress-secondary-title"><header><div><h2 id="progress-secondary-title"><?php echo stridebr_e(stridebr_t('progress.secondary_involvement')); ?></h2><p><?php echo stridebr_e(stridebr_t('progress.secondary_involvement_help')); ?></p></div></header><div class="progress-secondary-list"><?php foreach ((array) $strengthDashboard['secondary_muscles'] as $muscle => $sets): ?><span><?php echo stridebr_e(stridebr_t('progress.muscle.' . str_replace('-', '_', (string) $muscle))); ?><b><?php echo stridebr_e((string) $sets); ?></b></span><?php endforeach; ?></div></section><?php endif; ?>
                <?php elseif ($renderer === 'athletics' && is_array($athleticsDashboard)): ?>
                    <?php $athleticsRecords = array_merge((array) ($athleticsDashboard['track_records'] ?? []), (array) ($athleticsDashboard['field_events'] ?? [])); if ($athleticsRecords !== []): ?><section class="progress-section" aria-labelledby="progress-records-title"><header><div><h2 id="progress-records-title"><?php echo stridebr_e(stridebr_t('progress.by_event')); ?></h2></div></header><div class="athletics-record-grid"><?php foreach ($athleticsRecords as $record): ?><article><div><strong><?php echo stridebr_e((string) ($record['nome'] ?? '')); ?></strong><small><?php echo stridebr_e(stridebr_tn('progress.session.one', 'progress.session.other', (int) ($record['sessions'] ?? 0))); ?></small></div><div class="athletics-record-value"><?php if (($record['direction'] ?? '') === 'lower' && is_numeric($record['best_time_s'] ?? null)): ?><span><?php echo stridebr_e(stridebr_t('progress.best_time_registered')); ?></span><b><?php echo stridebr_e($fmtSeconds((float) $record['best_time_s'])); ?></b><?php elseif (is_numeric($record['best_mark'] ?? null)): ?><span><?php echo stridebr_e(stridebr_t('progress.best_mark_registered')); ?></span><b><?php echo stridebr_e(stridebr_format_number((float) $record['best_mark'], 2)); ?> m</b><?php endif; ?></div><?php if (!empty($record['wind_aided_best'])): ?><small><?php echo stridebr_e(stridebr_t('progress.wind_above_limit')); ?></small><?php endif; ?></article><?php endforeach; ?></div></section><?php endif; ?>
                <?php elseif (is_array($sessionDashboard)): ?>
                    <?php $sessionSummary = (array) ($sessionDashboard['summary']['current'] ?? []); ?>
                    <?php if ((int) ($sessionSummary['wins'] ?? 0) + (int) ($sessionSummary['draws'] ?? 0) + (int) ($sessionSummary['losses'] ?? 0) > 0): ?><section class="progress-section" aria-labelledby="progress-results-title"><header><div><h2 id="progress-results-title"><?php echo stridebr_e(stridebr_t('progress.results_recorded')); ?></h2></div></header><div class="progress-kpi-strip"><div><span><?php echo stridebr_e(stridebr_t('progress.results')); ?></span><strong><?php echo stridebr_e(stridebr_t('progress.record_summary', ['wins' => (string) $sessionSummary['wins'], 'draws' => (string) $sessionSummary['draws'], 'losses' => (string) $sessionSummary['losses']])); ?></strong></div></div></section><?php endif; ?>
                    <?php if ((array) ($sessionDashboard['recent'] ?? []) !== []): ?><section class="progress-section" aria-labelledby="progress-recent-title"><header><div><h2 id="progress-recent-title"><?php echo stridebr_e($renderer === 'combat' ? stridebr_t('progress.training') : stridebr_t('progress.recent_sessions')); ?></h2></div></header><div class="progress-detail-list"><?php foreach ((array) $sessionDashboard['recent'] as $session): ?><article><strong><?php echo stridebr_e((string) ($session['title'] ?? '')); ?></strong><span><?php echo stridebr_e($fmtDuration((float) ($session['duration_s'] ?? 0))); ?></span><small><?php $details = []; if (!empty($session['type'])) $details[] = sportHubSessionTypeLabel((string) $session['type']); if (!empty($session['opponent'])) $details[] = stridebr_t('progress.opponent', ['value' => (string) $session['opponent']]); if (!empty($session['score'])) $details[] = stridebr_t('progress.score', ['value' => (string) $session['score']]); if (!empty($session['rounds'])) $details[] = stridebr_t('progress.round_count', ['count' => (string) $session['rounds']]); echo stridebr_e(implode(' · ', $details)); ?></small></article><?php endforeach; ?></div></section><?php endif; ?>
                <?php endif; ?>

                <?php if ($renderer === 'cycling'): $ftpLatest=is_array($ftpSummary['latest']??null)?$ftpSummary['latest']:null; $ftpPrevious=is_array($ftpSummary['previous']??null)?$ftpSummary['previous']:null; ?>
                    <section class="progress-section progress-benchmarks-section" aria-labelledby="progress-benchmarks-title">
                        <header><div><h2 id="progress-benchmarks-title"><?php echo stridebr_e(stridebr_t('benchmarks.title')); ?></h2></div><?php if ($selectedModalityId !== ''): ?><a class="progress-button" href="<?php echo stridebr_e($buildUrl(['new_benchmark'=>'ftp'])); ?>"><?php echo stridebr_e(stridebr_t('benchmarks.register_ftp')); ?></a><?php endif; ?></header>
                        <?php if (!is_array($ftpLatest)): ?><p class="progress-benchmark-empty"><?php echo stridebr_e(stridebr_t('benchmarks.empty_ftp')); ?></p><?php else: ?>
                            <div class="progress-benchmark-summary"><div><span><?php echo stridebr_e(stridebr_t('benchmarks.current_ftp')); ?></span><strong><?php echo stridebr_e(benchmarkFormatValue('ftp',(float)$ftpLatest['valor_canonico'])); ?></strong><small><?php echo stridebr_e(stridebr_format_date_short(new DateTimeImmutable((string)$ftpLatest['data_resultado']))); ?></small></div><?php if (is_array($ftpPrevious)): ?><div><span><?php echo stridebr_e(stridebr_t('benchmarks.previous')); ?></span><strong><?php echo stridebr_e(benchmarkFormatValue('ftp',(float)$ftpPrevious['valor_canonico'])); ?></strong><small><?php echo stridebr_e(benchmarkFormatDifference('ftp',(float)$ftpLatest['valor_canonico']-(float)$ftpPrevious['valor_canonico'])); ?></small></div><?php endif; ?></div>
                            <details class="progress-benchmark-history"><summary><?php echo stridebr_e(stridebr_t('benchmarks.history')); ?> · <?php echo count((array)$ftpSummary['history']); ?></summary><div><?php foreach ((array)$ftpSummary['history'] as $record): ?><article class="progress-benchmark-history-row"><div><strong><?php echo stridebr_e(benchmarkFormatValue('ftp',(float)$record['valor_canonico'])); ?></strong><small><?php echo stridebr_e(stridebr_format_date_short(new DateTimeImmutable((string)$record['data_resultado']))); ?><?php $competitionName=trim((string)($record['competicao_nome']??'')); if($competitionName!==''): ?> · <?php echo stridebr_e($competitionName); ?><?php endif; ?><?php $protocolLabel=benchmarkProtocolLabel($record['protocolo']??null); if($protocolLabel!==''): ?> · <?php echo stridebr_e($protocolLabel); ?><?php endif; ?><?php $officialLabel=benchmarkOfficialityLabel((string)($record['oficialidade']??'')); if($officialLabel!==''): ?> · <?php echo stridebr_e($officialLabel); ?><?php endif; ?></small></div><div class="progress-benchmark-actions"><a href="<?php echo stridebr_e($buildUrl(['edit_benchmark'=>(string)$record['idbenchmark']])); ?>"><?php echo stridebr_e(stridebr_t('common.edit')); ?></a><form method="POST" action="/api/progress-benchmarks.php" data-confirm="<?php echo stridebr_e(stridebr_t('benchmarks.delete_confirm')); ?>"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="idbenchmark" value="<?php echo stridebr_e((string)$record['idbenchmark']); ?>"><input type="hidden" name="return_to" value="<?php echo stridebr_e($benchmarkReturnTo); ?>"><button type="submit"><?php echo stridebr_e(stridebr_t('common.delete')); ?></button></form></div></article><?php endforeach; ?></div></details>
                        <?php endif; ?>
                    </section>
                <?php elseif ($renderer === 'swimming'): $cssLatest=is_array($cssSummary['latest']??null)?$cssSummary['latest']:null; $cssPrevious=is_array($cssSummary['previous']??null)?$cssSummary['previous']:null; ?>
                    <section class="progress-section progress-benchmarks-section" aria-labelledby="progress-benchmarks-title">
                        <header><div><h2 id="progress-benchmarks-title"><?php echo stridebr_e(stridebr_t('benchmarks.title')); ?></h2></div><?php if ($selectedModalityId !== ''): ?><a class="progress-button" href="<?php echo stridebr_e($buildUrl(['new_benchmark'=>'css'])); ?>"><?php echo stridebr_e(stridebr_t('benchmarks.register_css')); ?></a><?php endif; ?></header>
                        <?php if (!is_array($cssLatest)): ?><p class="progress-benchmark-empty"><?php echo stridebr_e(stridebr_t('benchmarks.empty_css')); ?></p><?php else: ?>
                            <div class="progress-benchmark-summary"><div><span><?php echo stridebr_e(stridebr_t('benchmarks.current_css')); ?></span><strong><?php echo stridebr_e(benchmarkFormatValue('css',(float)$cssLatest['valor_canonico'])); ?></strong><small><?php echo stridebr_e(stridebr_format_date_short(new DateTimeImmutable((string)$cssLatest['data_resultado']))); ?></small></div><?php if (is_array($cssPrevious)): ?><div><span><?php echo stridebr_e(stridebr_t('benchmarks.previous')); ?></span><strong><?php echo stridebr_e(benchmarkFormatValue('css',(float)$cssPrevious['valor_canonico'])); ?></strong><small><?php echo stridebr_e(benchmarkFormatDifference('css',(float)$cssLatest['valor_canonico']-(float)$cssPrevious['valor_canonico'])); ?></small></div><?php endif; ?></div>
                            <details class="progress-benchmark-history"><summary><?php echo stridebr_e(stridebr_t('benchmarks.history')); ?> · <?php echo count((array)$cssSummary['history']); ?></summary><div><?php foreach ((array)$cssSummary['history'] as $record): ?><article class="progress-benchmark-history-row"><div><strong><?php echo stridebr_e(benchmarkFormatValue('css',(float)$record['valor_canonico'])); ?></strong><small><?php echo stridebr_e(stridebr_format_date_short(new DateTimeImmutable((string)$record['data_resultado']))); ?><?php $competitionName=trim((string)($record['competicao_nome']??'')); if($competitionName!==''): ?> · <?php echo stridebr_e($competitionName); ?><?php endif; ?><?php $officialLabel=benchmarkOfficialityLabel((string)($record['oficialidade']??'')); if($officialLabel!==''): ?> · <?php echo stridebr_e($officialLabel); ?><?php endif; ?></small></div><div class="progress-benchmark-actions"><a href="<?php echo stridebr_e($buildUrl(['edit_benchmark'=>(string)$record['idbenchmark']])); ?>"><?php echo stridebr_e(stridebr_t('common.edit')); ?></a><form method="POST" action="/api/progress-benchmarks.php" data-confirm="<?php echo stridebr_e(stridebr_t('benchmarks.delete_confirm')); ?>"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="idbenchmark" value="<?php echo stridebr_e((string)$record['idbenchmark']); ?>"><input type="hidden" name="return_to" value="<?php echo stridebr_e($benchmarkReturnTo); ?>"><button type="submit"><?php echo stridebr_e(stridebr_t('common.delete')); ?></button></form></div></article><?php endforeach; ?></div></details>
                        <?php endif; ?>
                    </section>
                <?php elseif ($renderer === 'running'): ?>
                    <section class="progress-section progress-benchmarks-section" aria-labelledby="progress-benchmarks-title">
                        <header><div><h2 id="progress-benchmarks-title"><?php echo stridebr_e(stridebr_t('benchmarks.title')); ?></h2></div><?php if ($selectedModalityId !== ''): ?><a class="progress-button" href="<?php echo stridebr_e($buildUrl(['new_benchmark'=>'distance_time'])); ?>"><?php echo stridebr_e(stridebr_t('benchmarks.register_test')); ?></a><?php endif; ?></header>
                        <?php if ($distanceTestGroups === []): ?><p class="progress-benchmark-empty"><?php echo stridebr_e(stridebr_t('benchmarks.empty_running')); ?></p><?php else: ?><div class="progress-distance-tests"><?php foreach ($distanceTestGroups as $group): $distance=(float)$group['distance_m']; $summary=(array)$group['summary']; $best=is_array($summary['best']??null)?$summary['best']:null; if(!is_array($best)) continue; ?><article><div><span><?php echo stridebr_e(benchmarkFormatDistance($distance)); ?></span><strong><?php echo stridebr_e(benchmarkFormatValue('distance_time',(float)$best['valor_canonico'],$distance)); ?></strong><small><?php echo stridebr_e(stridebr_t('benchmarks.best_test_registered')); ?></small></div><details class="progress-benchmark-history"><summary><?php echo stridebr_e(stridebr_t('benchmarks.history')); ?> · <?php echo count((array)$summary['history']); ?></summary><div><?php foreach ((array)$summary['history'] as $record): ?><article class="progress-benchmark-history-row"><div><strong><?php echo stridebr_e(benchmarkFormatValue('distance_time',(float)$record['valor_canonico'],$distance)); ?></strong><small><?php echo stridebr_e(stridebr_format_date_short(new DateTimeImmutable((string)$record['data_resultado']))); ?><?php $competitionName=trim((string)($record['competicao_nome']??'')); if($competitionName!==''): ?> · <?php echo stridebr_e($competitionName); ?><?php endif; ?><?php $contextLabel=benchmarkContextLabel($record['contexto']??null); if($contextLabel!==''): ?> · <?php echo stridebr_e($contextLabel); ?><?php endif; ?><?php $officialLabel=benchmarkOfficialityLabel((string)($record['oficialidade']??'')); if($officialLabel!==''): ?> · <?php echo stridebr_e($officialLabel); ?><?php endif; ?></small></div><div class="progress-benchmark-actions"><a href="<?php echo stridebr_e($buildUrl(['edit_benchmark'=>(string)$record['idbenchmark']])); ?>"><?php echo stridebr_e(stridebr_t('common.edit')); ?></a><form method="POST" action="/api/progress-benchmarks.php" data-confirm="<?php echo stridebr_e(stridebr_t('benchmarks.delete_confirm')); ?>"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="idbenchmark" value="<?php echo stridebr_e((string)$record['idbenchmark']); ?>"><input type="hidden" name="return_to" value="<?php echo stridebr_e($benchmarkReturnTo); ?>"><button type="submit"><?php echo stridebr_e(stridebr_t('common.delete')); ?></button></form></div></article><?php endforeach; ?></div></details></article><?php endforeach; ?></div><?php endif; ?>
                    </section>
                <?php endif; ?>

                <?php if ($series !== [] && $metricOptions !== []): ?>
                    <section class="progress-section" aria-labelledby="progress-chart-title">
                        <header><div><h2 id="progress-chart-title"><?php echo stridebr_e($metricOptions[$metric] ?? stridebr_t('progress.volume')); ?></h2></div><div class="progress-chart-controls"><?php if (count($metricOptions) > 1): ?><nav class="progress-chart-switch" aria-label="<?php echo stridebr_e(stridebr_t('progress.chart_metric_aria')); ?>"><?php foreach ($metricOptions as $key => $label): ?><a href="<?php echo stridebr_e($buildUrl(['metric' => $key])); ?>" class="<?php echo $metric === $key ? 'is-active' : ''; ?>"<?php echo $metric === $key ? ' aria-current="page"' : ''; ?>><?php echo stridebr_e($label); ?></a><?php endforeach; ?></nav><?php endif; ?><strong class="progress-section-unit"><?php echo stridebr_e($periodLabels[$period] ?? $period); ?></strong></div></header>
                        <div class="progress-bar-chart progress-adaptive-chart" style="--progress-week-count:<?php echo count($series); ?>" role="group" aria-label="<?php echo stridebr_e(stridebr_t('progress.period_chart_aria', ['metric' => (string) ($metricOptions[$metric] ?? ''), 'sport' => $selectedSportLabel])); ?>">
                            <div class="progress-zero-line" aria-hidden="true"></div>
                            <?php foreach ($series as $index => $bucket): $value = $seriesValues[$index] ?? 0.0; $height = max(0, min(100, ($value / $maxSeriesValue) * 100)); $tooltip = stridebr_format_date_short($bucket['start']) . ' · ' . $formatSeriesValue($metric, $value); ?><div class="progress-bar-column"><button type="button" class="progress-bar-hit" data-progress-tooltip="<?php echo stridebr_e($tooltip); ?>" aria-label="<?php echo stridebr_e($tooltip); ?>"><span class="progress-bar" style="height:<?php echo number_format($height, 2, '.', ''); ?>%"></span></button><small><?php echo $index % max(1, (int) ceil(count($series) / 8)) === 0 ? stridebr_e(stridebr_format_date_short($bucket['start'])) : ''; ?></small></div><?php endforeach; ?>
                        </div>
                        <span class="visually-hidden"><?php foreach ($series as $index => $bucket): ?><?php echo stridebr_e(stridebr_format_date_short($bucket['start']) . ': ' . $formatSeriesValue($metric, (float) ($seriesValues[$index] ?? 0))); ?>. <?php endforeach; ?></span>
                    </section>
                <?php endif; ?>

                <?php if ($trainingLoad['eligible'] > 0): ?><section class="progress-section progress-load" aria-labelledby="progress-load-title"><header><div><h2 id="progress-load-title"><?php echo stridebr_e(stridebr_t('progress.training_load')); ?></h2><p><?php echo stridebr_e(stridebr_t('progress.training_load_help')); ?></p></div></header><div class="progress-kpi-strip"><div><span><?php echo stridebr_e(stridebr_t('progress.training_load')); ?></span><strong><?php echo stridebr_e(stridebr_format_number((float) $trainingLoad['value'], 0)); ?> UA</strong><small><?php echo stridebr_e(stridebr_t('progress.rpe_coverage', ['covered' => (string) $trainingLoad['covered'], 'eligible' => (string) $trainingLoad['eligible']])); ?></small></div></div></section><?php endif; ?>

                <?php if (in_array($renderer, ['running', 'cycling', 'swimming'], true) && is_array($cardioDashboard)): $discipline = $renderer === 'running' ? 'run' : ($renderer === 'cycling' ? 'cycle' : 'swim'); $sensor = (array) ($cardioDashboard['summary'][$discipline]['current'] ?? []); $sensorItems = []; if ($renderer === 'running' && is_numeric($sensor['avg_pace_km_s'] ?? null)) $sensorItems[] = [stridebr_t('progress.avg_pace'), $fmtPace((float) $sensor['avg_pace_km_s'], '/km')]; if ($renderer === 'swimming' && is_numeric($sensor['avg_pace_100m_s'] ?? null)) $sensorItems[] = [stridebr_t('progress.avg_pace'), $fmtPace((float) $sensor['avg_pace_100m_s'], '/100 m')]; if ($renderer === 'cycling' && is_numeric($sensor['avg_speed_kmh'] ?? null)) $sensorItems[] = [stridebr_t('progress.avg_speed'), stridebr_format_number((float) $sensor['avg_speed_kmh'], 1) . ' km/h']; if ($renderer === 'running' && (float) ($sensor['elevation_m'] ?? 0) > 0) $sensorItems[] = [stridebr_t('progress.elevation'), stridebr_format_number((float) $sensor['elevation_m'], 0) . ' m']; if (is_numeric($sensor['avg_hr_bpm'] ?? null)) $sensorItems[] = [stridebr_t('progress.avg_hr'), stridebr_format_number((float) $sensor['avg_hr_bpm'], 0) . ' bpm']; if (is_numeric($sensor['max_hr_bpm'] ?? null)) $sensorItems[] = [stridebr_t('progress.max_hr'), stridebr_format_number((float) $sensor['max_hr_bpm'], 0) . ' bpm']; if (is_numeric($sensor['avg_cadence'] ?? null)) $sensorItems[] = [stridebr_t('progress.avg_cadence'), stridebr_format_number((float) $sensor['avg_cadence'], 0)]; if (is_numeric($sensor['avg_power_w'] ?? null)) $sensorItems[] = [stridebr_t('progress.avg_power'), stridebr_format_number((float) $sensor['avg_power_w'], 0) . ' W']; ?><?php if ($sensorItems !== []): ?><section class="progress-section" aria-labelledby="progress-sensors-title"><header><div><h2 id="progress-sensors-title"><?php echo stridebr_e(stridebr_t('progress.sensor_details')); ?></h2></div></header><div class="progress-kpi-strip"><?php foreach ($sensorItems as [$label, $value]): ?><div><span><?php echo stridebr_e($label); ?></span><strong><?php echo stridebr_e($value); ?></strong></div><?php endforeach; ?></div></section><?php endif; ?><?php endif; ?>

                <?php if ($progressGoals !== []): ?><section class="progress-section progress-goals-section" aria-labelledby="progress-goals-title"><header><div><h2 id="progress-goals-title"><?php echo stridebr_e(stridebr_t('common.goals')); ?></h2></div><a class="progress-button" href="/user/metas.php"><?php echo stridebr_e(stridebr_t('progress.open_goals')); ?></a></header><div class="progress-goal-list"><?php foreach ($progressGoals as $goal): ?><article><div><strong><?php echo stridebr_e(dashboardMetaTitulo($goal)); ?></strong><small><?php echo stridebr_e(dashboardMetaPrazoLabel($goal)); ?></small></div><div><span><?php echo stridebr_e(dashboardMetaCompactValue($goal)); ?></span><?php if ((string) ($goal['tipo_meta'] ?? 'metrica') !== 'benchmark' || !empty($goal['percentual_disponivel'])): ?><progress max="100" value="<?php echo stridebr_e((string) min(100, max(0, (float) ($goal['percentual'] ?? 0)))); ?>"></progress><?php endif; ?></div></article><?php endforeach; ?></div></section><?php endif; ?>

                <?php $renderRecentCompetitions($recentCompetitions); ?>

                <section class="progress-section" aria-labelledby="progress-consistency-title"><header><div><h2 id="progress-consistency-title"><?php echo stridebr_e(stridebr_t('progress.consistency')); ?></h2><p><?php echo stridebr_e(stridebr_t('progress.active_weeks_summary', ['active' => (string) $consistency['active'], 'total' => (string) $consistency['total']])); ?></p></div></header><div class="progress-consistency-band" role="list" aria-label="<?php echo stridebr_e(stridebr_t('progress.consistency_weeks_aria')); ?>"><?php foreach ($consistency['units'] as $unit): ?><span role="listitem" class="<?php echo !empty($unit['active']) ? 'is-active' : ''; ?>" title="<?php echo stridebr_e(stridebr_t('progress.week_activity_count', ['date' => stridebr_format_date_short($unit['start']), 'count' => (string) $unit['activities']])); ?>"></span><?php endforeach; ?></div></section>
            <?php endif; ?>

            <?php if ($benchmarkDialogType !== ''): ?>
                <dialog class="progress-benchmark-dialog" data-progress-benchmark-dialog data-return-url="<?php echo stridebr_e($benchmarkReturnTo); ?>" aria-labelledby="progress-benchmark-dialog-title" open>
                    <div class="progress-benchmark-dialog-card">
                        <header><div><span class="progress-eyebrow"><?php echo stridebr_e(stridebr_t('benchmarks.title')); ?></span><h2 id="progress-benchmark-dialog-title"><?php echo stridebr_e(stridebr_t($benchmarkDialogTitleKey)); ?></h2></div><a class="progress-dialog-close" href="<?php echo stridebr_e($benchmarkReturnTo); ?>" aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</a></header>
                        <form method="POST" action="/api/progress-benchmarks.php" class="progress-benchmark-form">
                            <?php echo stridebr_csrf_field(); ?>
                            <input type="hidden" name="action" value="<?php echo $benchmarkFormIsEdit ? 'update' : 'create'; ?>">
                            <input type="hidden" name="benchmark_type" value="<?php echo stridebr_e($benchmarkDialogType); ?>">
                            <input type="hidden" name="idmodalidade" value="<?php echo stridebr_e($selectedModalityId); ?>">
                            <input type="hidden" name="return_to" value="<?php echo stridebr_e($benchmarkReturnTo); ?>">
                            <?php if ($benchmarkFormIsEdit): ?><input type="hidden" name="idbenchmark" value="<?php echo stridebr_e((string) ($benchmarkFormRow['idbenchmark'] ?? '')); ?>"><?php endif; ?>

                            <?php if ($benchmarkDialogType === 'one_rm'): ?>
                                <label><?php echo stridebr_e(stridebr_t('benchmarks.exercise')); ?><select name="idexercicio"<?php echo ($benchmarkFormIsEdit && empty($benchmarkFormRow['idexercicio']) && !empty($benchmarkFormRow['referencia_nome_snapshot'])) ? '' : ' required'; ?>><?php if ($benchmarkFormIsEdit && empty($benchmarkFormRow['idexercicio']) && !empty($benchmarkFormRow['referencia_nome_snapshot'])): ?><option value="" selected><?php echo stridebr_e((string) $benchmarkFormRow['referencia_nome_snapshot']); ?></option><?php else: ?><option value="" disabled<?php echo empty($benchmarkFormRow['idexercicio']) ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('benchmarks.select_exercise')); ?></option><?php if ($benchmarkFormIsEdit && !empty($benchmarkFormRow['idexercicio']) && !isset($availableBenchmarkExerciseIds[(string) $benchmarkFormRow['idexercicio']])): ?><option value="<?php echo stridebr_e((string) $benchmarkFormRow['idexercicio']); ?>" selected><?php echo stridebr_e((string) ($benchmarkFormRow['referencia_nome_snapshot'] ?? $benchmarkFormRow['exercicio_nome'] ?? stridebr_t('benchmarks.exercise'))); ?></option><?php endif; ?><?php endif; ?><?php foreach ($availableBenchmarkExercises as $exerciseOption): ?><option value="<?php echo stridebr_e((string) $exerciseOption['idexercicio']); ?>"<?php echo (string) ($benchmarkFormRow['idexercicio'] ?? '') === (string) $exerciseOption['idexercicio'] ? ' selected' : ''; ?>><?php echo stridebr_e((string) $exerciseOption['nome']); ?></option><?php endforeach; ?></select></label>
                                <label><?php echo stridebr_e(stridebr_t('benchmarks.load')); ?><div class="progress-input-unit"><input type="number" name="carga_kg" min="0.1" max="10000" step="0.1" inputmode="decimal" value="<?php echo stridebr_e(isset($benchmarkFormRow['valor_canonico']) ? rtrim(rtrim(number_format((float) $benchmarkFormRow['valor_canonico'], 3, '.', ''), '0'), '.') : ''); ?>" required><span>kg</span></div></label>
                            <?php elseif ($benchmarkDialogType === 'ftp'): ?>
                                <label><?php echo stridebr_e(stridebr_t('benchmarks.ftp')); ?><div class="progress-input-unit"><input type="number" name="ftp_w" min="1" max="5000" step="1" inputmode="numeric" value="<?php echo stridebr_e(isset($benchmarkFormRow['valor_canonico']) ? (string) round((float) $benchmarkFormRow['valor_canonico']) : ''); ?>" required><span>W</span></div></label>
                                <label><?php echo stridebr_e(stridebr_t('benchmarks.protocol')); ?><select name="protocolo"><option value=""><?php echo stridebr_e(stridebr_t('common.optional')); ?></option><?php foreach (['ramp','20min','informado','outro'] as $protocolOption): ?><option value="<?php echo stridebr_e($protocolOption); ?>"<?php echo (string) ($benchmarkFormRow['protocolo'] ?? '') === $protocolOption ? ' selected' : ''; ?>><?php echo stridebr_e(benchmarkProtocolLabel($protocolOption)); ?></option><?php endforeach; ?></select></label>
                            <?php elseif ($benchmarkDialogType === 'css'): ?>
                                <label><?php echo stridebr_e(stridebr_t('benchmarks.css')); ?><div class="progress-input-unit"><input type="text" name="css_value" inputmode="numeric" placeholder="1:46" value="<?php echo stridebr_e(isset($benchmarkFormRow['valor_canonico']) ? benchmarkFormatClock((float) $benchmarkFormRow['valor_canonico']) : ''); ?>" required><span>/100 m</span></div></label>
                            <?php elseif ($benchmarkDialogType === 'distance_time'): ?>
                                <label><?php echo stridebr_e(stridebr_t('benchmarks.distance')); ?><div class="progress-input-unit"><input type="number" name="distance_km" list="progress-common-distances" min="0.05" max="1000" step="any" inputmode="decimal" value="<?php echo stridebr_e(isset($benchmarkFormRow['distancia_m']) ? rtrim(rtrim(number_format((float) $benchmarkFormRow['distancia_m'] / 1000, 4, '.', ''), '0'), '.') : ''); ?>" required><span>km</span></div><datalist id="progress-common-distances"><option value="1"><option value="3"><option value="5"><option value="10"><option value="21.0975"><option value="42.195"></datalist></label>
                                <label><?php echo stridebr_e(stridebr_t('benchmarks.time')); ?><input type="text" name="time_value" inputmode="numeric" placeholder="29:58" value="<?php echo stridebr_e(isset($benchmarkFormRow['valor_canonico']) ? benchmarkFormatClock((float) $benchmarkFormRow['valor_canonico']) : ''); ?>" required></label>
                            <?php endif; ?>

                            <label><?php echo stridebr_e(stridebr_t('common.date')); ?><input type="date" name="data_resultado" max="<?php echo stridebr_e((new DateTimeImmutable('today'))->format('Y-m-d')); ?>" value="<?php echo stridebr_e($benchmarkFormDate); ?>" required></label>
                            <label><?php echo stridebr_e(stridebr_t('benchmarks.context')); ?><select name="contexto" data-progress-benchmark-context><option value=""><?php echo stridebr_e(stridebr_t('common.optional')); ?></option><?php foreach (['treino','teste','competicao'] as $contextOption): ?><option value="<?php echo stridebr_e($contextOption); ?>"<?php echo $benchmarkFormContext === $contextOption ? ' selected' : ''; ?>><?php echo stridebr_e(benchmarkContextLabel($contextOption)); ?></option><?php endforeach; ?></select></label>
                            <label data-progress-benchmark-competition-field<?php echo $benchmarkFormContext === 'competicao' || $benchmarkFormOfficial || $benchmarkFormCompetition !== '' ? '' : ' hidden'; ?>><?php echo stridebr_e(stridebr_t('competitions.competition')); ?><select name="idcompeticao" data-progress-benchmark-competition<?php echo $benchmarkLinkedActivityId !== '' ? ' disabled aria-disabled="true"' : ''; ?>><option value=""><?php echo stridebr_e(stridebr_t('competitions.none')); ?></option><?php foreach ($benchmarkCompetitionOptions as $competition): ?><option value="<?php echo stridebr_e((string)$competition['idcompeticao']); ?>"<?php echo $benchmarkFormCompetition === (string)$competition['idcompeticao'] ? ' selected' : ''; ?>><?php echo stridebr_e((string)$competition['nome']); ?> · <?php echo stridebr_e(stridebr_format_date_short(new DateTimeImmutable((string)$competition['data_inicio']))); ?></option><?php endforeach; ?></select><small><?php echo stridebr_e($benchmarkLinkedActivityId !== '' ? stridebr_t('competitions.benchmark_activity_help') : ($benchmarkFormOfficial && $benchmarkFormCompetition === '' ? stridebr_t('competitions.legacy_official_help') : stridebr_t('competitions.benchmark_help'))); ?></small><?php if ($benchmarkLinkedActivityId !== ''): ?><a href="/user/editatividade.php?id=<?php echo rawurlencode($benchmarkLinkedActivityId); ?>"><?php echo stridebr_e(stridebr_t('competitions.edit_related_activity')); ?></a><?php elseif ($benchmarkCompetitionOptions === []): ?><a href="/user/competicoes.php?new=1"><?php echo stridebr_e(stridebr_t('competitions.register')); ?></a><?php endif; ?></label>
                            <?php if ($benchmarkDialogType === 'distance_time'): ?><input type="hidden" name="contexto" value="competicao" data-progress-official-context disabled><label class="progress-benchmark-check"><input type="checkbox" name="reported_official" value="1" data-progress-reported-official<?php echo $benchmarkFormOfficial ? ' checked' : ''; ?>><span><strong><?php echo stridebr_e(stridebr_t('benchmarks.report_as_official')); ?></strong><small><?php echo stridebr_e(stridebr_t('benchmarks.report_as_official_help')); ?></small></span></label><?php endif; ?>
                            <label class="progress-benchmark-notes"><?php echo stridebr_e(stridebr_t('common.notes')); ?><textarea name="observacoes" maxlength="2000" rows="3"><?php echo stridebr_e($benchmarkFormNotes); ?></textarea></label>
                            <div class="progress-benchmark-form-actions"><a class="progress-button" href="<?php echo stridebr_e($benchmarkReturnTo); ?>"><?php echo stridebr_e(stridebr_t('common.cancel')); ?></a><button type="submit" class="progress-button is-primary"><?php echo stridebr_e(stridebr_t($benchmarkSubmitKey)); ?></button></div>
                        </form>
                    </div>
                </dialog>
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
