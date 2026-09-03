<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/activity_insights.php';
require_once dirname(__DIR__, 2) . '/src/function/sport_hub.php';

$requestedArea = stridebr_lower(trim((string) ($_GET['area'] ?? 'all')));
$familyDefinitions = sportHubFamilies();
$validAreas = array_merge(['all'], array_keys($familyDefinitions));
$area = in_array($requestedArea, $validAreas, true) ? $requestedArea : 'all';
$periodView = stridebr_lower(trim((string) ($_GET['period'] ?? 'month')));
$periodAnchor = trim((string) ($_GET['month'] ?? ''));
$periodWindow = sportHubResolvePeriod($periodView, $periodAnchor);
$periodView = (string) $periodWindow['view'];
$periodAnchor = (string) $periodWindow['anchor'];
$now = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
$lookbackSeconds = max(0, $now->getTimestamp() - $periodWindow['previous_start']->getTimestamp());
$activityDays = min(1500, max(370, (int) ceil($lookbackSeconds / 86400) + 45));
$progress = activityInsightsProgress($pdo, $idUsuario, $periodWindow['current_end']);
$activities = sportHubActivityRows($pdo, $idUsuario, $activityDays);
$activitiesForPeriod = array_values(array_filter($activities, static function (array $row) use ($periodWindow): bool {
    $date = new DateTimeImmutable((string) ($row['data_inicio'] ?? 'now'));
    return $date >= $periodWindow['current_start'] && $date < $periodWindow['current_end'];
}));
$hub = sportHubOverview($activities, $periodWindow);
$activeFamilies = sportHubActiveFamilies($activities);
$areaLabels = ['all' => 'Visão geral'];
foreach ($activeFamilies as $key => $meta) $areaLabels[$key] = (string) $meta['label'];
if ($area !== 'all' && !isset($areaLabels[$area])) $areaLabels[$area] = (string) ($familyDefinitions[$area]['label'] ?? ucfirst($area));
$strength = $area === 'strength' ? sportHubStrengthDashboard($pdo, $idUsuario, $activities, $periodWindow) : null;
$requestedExercise = trim((string) ($_GET['exercise'] ?? ''));
$strengthExercise = null;
if ($area === 'strength' && is_array($strength) && $requestedExercise !== '') {
    foreach ($strength['exercises'] as $exercise) {
        if (hash_equals((string) ($exercise['key'] ?? ''), $requestedExercise)) { $strengthExercise = $exercise; break; }
    }
}
$cardio = $area === 'cardio' ? sportHubCardioDashboard($activities, $periodWindow) : null;
$requestedSport = stridebr_lower(trim((string) ($_GET['sport'] ?? 'all')));
$cardioSport = $area === 'cardio' && is_array($cardio) && isset($cardio['active'][$requestedSport]) ? $requestedSport : 'all';
$athletics = $area === 'athletics' ? sportHubAthleticsDashboard($pdo, $idUsuario, $activities, $periodWindow) : null;
$sessionDashboard = in_array($area, ['racket','team','combat','precision'], true) ? sportHubSessionDashboard($activities, $area, $periodWindow) : null;
$breakdownActivities = $activitiesForPeriod;
if ($area === 'cardio' && $cardioSport !== 'all') $breakdownActivities = array_values(array_filter($activitiesForPeriod, static fn(array $row): bool => ($row['hub_bucket'] ?? '') === 'cardio' && sportHubCardioDiscipline((string) ($row['modalidade_slug'] ?? '')) === $cardioSport));
$breakdown = $area !== 'all' && $area !== 'strength' ? sportHubSportBreakdown($breakdownActivities, $area) : [];
$weeks = $progress['weeks'];
$frequency = (int) $progress['weekly_frequency'];
$maxWeekActivities = max(1, ...array_map(static fn(array $w): int => (int) $w['activities'], $weeks ?: [['activities' => 1]]));
$fmtDuration = static function (float $seconds): string {
    if ($seconds <= 0) return '0 min';
    $minutes = (int) round($seconds / 60);
    if ($minutes < 60) return $minutes . ' min';
    $hours = intdiv($minutes, 60);
    $rest = $minutes % 60;
    return $hours . 'h' . ($rest ? sprintf(' %02dmin', $rest) : '');
};
$fmtDistance = static fn(float $meters): string => $meters >= 1000 ? number_format($meters / 1000, 1, ',', '.') . ' km' : number_format($meters, 0, ',', '.') . ' m';
$fmtVolume = static function (float $kg): string {
    if ($kg >= 1000000) return number_format($kg / 1000000, 2, ',', '.') . ' mi kg';
    if ($kg >= 1000) return number_format($kg / 1000, $kg >= 10000 ? 0 : 1, ',', '.') . ' mil kg';
    return number_format($kg, 0, ',', '.') . ' kg';
};
$fmtCalories = static function (float $kcal): string {
    if ($kcal <= 0) return '—';
    if ($kcal >= 10000) return number_format($kcal / 1000, 1, ',', '.') . ' mil kcal';
    return number_format($kcal, 0, ',', '.') . ' kcal';
};
$fmtRaceTime = static function (float $seconds): string {
    if ($seconds <= 0) return '—';
    if ($seconds < 60) return number_format($seconds, abs($seconds - round($seconds)) > .001 ? 2 : 0, ',', '.') . ' s';
    $minutes = (int) floor($seconds / 60);
    $rest = $seconds - ($minutes * 60);
    return $minutes . ':' . str_pad(number_format($rest, abs($rest - round($rest)) > .001 ? 2 : 0, ',', ''), 2, '0', STR_PAD_LEFT);
};
$fmtPace = static function (?float $seconds, string $suffix): string {
    if ($seconds === null || $seconds <= 0 || !is_finite($seconds)) return '—';
    $minutes = (int) floor($seconds / 60);
    $rest = (int) round($seconds - ($minutes * 60));
    if ($rest >= 60) { $minutes++; $rest = 0; }
    return $minutes . ':' . str_pad((string)$rest, 2, '0', STR_PAD_LEFT) . $suffix;
};
$fmtSpeed = static fn(?float $speed): string => $speed !== null && $speed > 0 ? number_format($speed, 1, ',', '.') . ' km/h' : '—';
$fmtSignedPercent = static function (?float $value): string {
    if ($value === null || !is_finite($value)) return '—';
    if (abs($value) < .5) return 'estável';
    return ($value > 0 ? '+' : '') . number_format($value, 0, ',', '.') . '%';
};
$metricSparkline = static function (array $history, string $key, bool $lowerIsBetter = false): string {
    $values = [];
    foreach ($history as $entry) {
        if (!is_numeric($entry[$key] ?? null)) continue;
        $value = (float) $entry[$key];
        $values[] = $lowerIsBetter ? -$value : $value;
    }
    if (count($values) < 2) return '';
    $width = 150;
    $height = 50;
    $pad = 4;
    $min = min($values);
    $max = max($values);
    $range = max(.0001, $max - $min);
    $last = max(1, count($values) - 1);
    $points = [];
    foreach ($values as $index => $value) {
        $x = $pad + ($index / $last) * ($width - $pad * 2);
        $y = $height - $pad - (($value - $min) / $range) * ($height - $pad * 2);
        $points[] = number_format($x, 1, '.', '') . ',' . number_format($y, 1, '.', '');
    }
    return '<svg viewBox="0 0 '.$width.' '.$height.'" aria-hidden="true"><polyline points="'.implode(' ', $points).'" /></svg>';
};
$strengthSparkline = static function (array $history): string {
    $pointsRaw = [];
    foreach ($history as $entry) {
        if (!is_numeric($entry['max_load'] ?? null)) continue;
        $pointsRaw[] = (float) $entry['max_load'];
    }
    if (count($pointsRaw) < 2) return '';
    $width = 150;
    $height = 50;
    $pad = 4;
    $min = min($pointsRaw);
    $max = max($pointsRaw);
    $range = max(1.0, $max - $min);
    $last = max(1, count($pointsRaw) - 1);
    $points = [];
    foreach ($pointsRaw as $index => $value) {
        $x = $pad + ($index / $last) * ($width - $pad * 2);
        $y = $height - $pad - (($value - $min) / $range) * ($height - $pad * 2);
        $points[] = number_format($x, 1, '.', '') . ',' . number_format($y, 1, '.', '');
    }
    return '<svg viewBox="0 0 '.$width.' '.$height.'" role="img" aria-label="Evolução recente de carga"><polyline points="'.implode(' ', $points).'" /></svg>';
};
$periodComparison = (int) $periodWindow['months'] === 1 ? 'vs. mês anterior' : 'vs. período anterior';
$progressAnchorDate = $periodWindow['current_end']->modify('-1 second')->format('d/m/Y');
$deltaText = static function (float $current, float $previous, string $unit = '') use ($periodComparison): string {
    if ($previous <= 0) return $current > 0 ? 'Primeiros dados deste período' : 'Sem registros no período';
    $delta = (($current - $previous) / $previous) * 100;
    return ($delta >= 0 ? '+' : '') . number_format($delta, 0, ',', '.') . '%' . ($unit !== '' ? ' ' . $unit : '') . ' ' . $periodComparison;
};
$fmtTrend = static function (float $current, float $previous, callable $formatter): array {
    if ($previous <= 0) return [$formatter($current), $current > 0 ? 'Há dados neste período' : 'Sem registros'];
    $delta = (($current - $previous) / $previous) * 100;
    return [$formatter($current), ($delta >= 0 ? '+' : '') . number_format($delta, 0, ',', '.') . '% vs. 4 semanas anteriores'];
};
$trends = $progress['trends'];
$trendCards = [
    ['label' => 'Atividades', ...$fmtTrend((float)$trends['activities'][0], (float)$trends['activities'][1], static fn(float $v): string => number_format($v,0,',','.'))],
    ['label' => 'Distância', ...$fmtTrend((float)$trends['distance_m'][0], (float)$trends['distance_m'][1], $fmtDistance)],
    ['label' => 'Tempo', ...$fmtTrend((float)$trends['duration_s'][0], (float)$trends['duration_s'][1], $fmtDuration)],
    ['label' => 'Elevação', ...$fmtTrend((float)$trends['elevation_m'][0], (float)$trends['elevation_m'][1], static fn(float $v): string => number_format($v,0,',','.').' m')],
];
$records = $progress['records'];
$currentMetrics = $hub[$area]['current'] ?? $hub['all']['current'];
$previousMetrics = $hub[$area]['previous'] ?? $hub['all']['previous'];
$monthNow = $periodWindow['anchor_start'];
$daysInMonth = (int) $monthNow->format('t');
$firstWeekday = (int) $monthNow->format('w');
$muscleLabels = ['peito'=>'Peito','costas'=>'Costas','quadriceps'=>'Quadríceps','posteriores'=>'Posteriores','gluteos'=>'Glúteos','ombros'=>'Ombros','biceps'=>'Bíceps','triceps'=>'Tríceps','panturrilhas'=>'Panturrilhas','core'=>'Core','corpo-inteiro'=>'Corpo inteiro'];
$monthNames = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
$formatPeriodMonth = static fn(DateTimeImmutable $date): string => $monthNames[(int) $date->format('n')] . ' de ' . $date->format('Y');
$periodEndLabelDate = $periodWindow['current_end']->modify('-1 second');
$periodLabel = (int) $periodWindow['months'] === 1
    ? $formatPeriodMonth($periodWindow['anchor_start'])
    : $formatPeriodMonth($periodWindow['current_start']) . ' – ' . $formatPeriodMonth($periodEndLabelDate);
$currentMonthAnchor = (new DateTimeImmutable('first day of this month', new DateTimeZone('America/Sao_Paulo')))->format('Y-m');
$previousMonthAnchor = $periodWindow['anchor_start']->modify('-1 month')->format('Y-m');
$nextMonthCandidate = $periodWindow['anchor_start']->modify('+1 month')->format('Y-m');
$nextMonthAnchor = $nextMonthCandidate <= $currentMonthAnchor ? $nextMonthCandidate : null;
$periodBaseParams = ['period' => $periodView, 'month' => $periodAnchor];
$progressAreaUrl = static function (string $targetArea, array $extra = []) use ($periodBaseParams): string {
    return '/user/progresso.php?' . http_build_query(array_merge($periodBaseParams, ['area' => $targetArea], $extra));
};
$progressContextParams = ['area' => $area, 'period' => $periodView, 'month' => $periodAnchor];
if ($area === 'cardio' && $cardioSport !== 'all') $progressContextParams['sport'] = $cardioSport;
if ($area === 'strength' && $requestedExercise !== '') $progressContextParams['exercise'] = $requestedExercise;
?>
<!DOCTYPE html><html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>"><head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/product-insights.css')); ?>"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/sport-hub.css')); ?>"><title>Progresso | StrideBR</title></head><body><div class="container-fluid">
<?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
<main class="main-content product-page sport-hub" data-progress-page><header class="product-page-header"><div><h1>Progresso</h1><p>Uma visão geral do que você pratica, com detalhes próprios para cada tipo de esporte.</p></div><div class="product-toolbar"><a class="product-button-secondary" href="/user/comparar-atividades.php">Comparar atividades</a><a class="product-button-secondary" href="/user/metas.php">Metas</a></div></header>
<section class="progress-period-toolbar" aria-label="Período do progresso">
    <div class="progress-period-heading"><span>Período analisado</span><strong><?php echo stridebr_e($periodLabel); ?></strong></div>
    <div class="progress-period-ranges" aria-label="Duração do período">
        <?php foreach (['month' => '1 mês', '3m' => '3 meses', '6m' => '6 meses', '12m' => '12 meses'] as $rangeKey => $rangeLabel): $rangeParams = array_merge($progressContextParams, ['period' => $rangeKey, 'month' => $periodAnchor]); ?>
            <a href="/user/progresso.php?<?php echo stridebr_e(http_build_query($rangeParams)); ?>"<?php echo $periodView === $rangeKey ? ' class="is-active" aria-current="true"' : ''; ?>><?php echo stridebr_e($rangeLabel); ?></a>
        <?php endforeach; ?>
    </div>
    <form class="progress-period-month" method="get" action="/user/progresso.php">
        <input type="hidden" name="area" value="<?php echo stridebr_e($area); ?>">
        <input type="hidden" name="period" value="<?php echo stridebr_e($periodView); ?>">
        <?php if ($area === 'cardio' && $cardioSport !== 'all'): ?><input type="hidden" name="sport" value="<?php echo stridebr_e($cardioSport); ?>"><?php endif; ?>
        <?php if ($area === 'strength' && $requestedExercise !== ''): ?><input type="hidden" name="exercise" value="<?php echo stridebr_e($requestedExercise); ?>"><?php endif; ?>
        <a class="progress-period-arrow" href="/user/progresso.php?<?php echo stridebr_e(http_build_query(array_merge($progressContextParams, ['month' => $previousMonthAnchor]))); ?>" aria-label="Período anterior">‹</a>
        <label><span>Mês de referência</span><input type="month" name="month" value="<?php echo stridebr_e($periodAnchor); ?>" max="<?php echo stridebr_e($currentMonthAnchor); ?>"></label>
        <button type="submit">Ver</button>
        <?php if ($nextMonthAnchor !== null): ?><a class="progress-period-arrow" href="/user/progresso.php?<?php echo stridebr_e(http_build_query(array_merge($progressContextParams, ['month' => $nextMonthAnchor]))); ?>" aria-label="Próximo período">›</a><?php else: ?><span class="progress-period-arrow is-disabled" aria-hidden="true">›</span><?php endif; ?>
        <?php if ($periodAnchor !== $currentMonthAnchor): ?><a class="progress-period-current" href="/user/progresso.php?<?php echo stridebr_e(http_build_query(array_merge($progressContextParams, ['month' => $currentMonthAnchor]))); ?>">Atual</a><?php endif; ?>
    </form>
</section>
<nav class="sport-hub-tabs" aria-label="Área de progresso"><?php foreach ($areaLabels as $key => $label): ?><a href="<?php echo stridebr_e($progressAreaUrl($key)); ?>"<?php echo $area === $key ? ' class="is-active" aria-current="page"' : ''; ?>><?php echo stridebr_e($label); ?></a><?php endforeach; ?></nav>
<?php if ((int)($records['activities_total'] ?? 0) === 0): ?>
<section class="empty-action-state"><h2>Seu progresso começa no primeiro registro</h2><p>Depois de registrar atividades, o StrideBR passa a mostrar frequência, evolução e detalhes próprios de cada esporte.</p><a class="product-button" href="/user/atividades.php?new=1">Registrar atividade</a></section>
<?php elseif ($area === 'all'): ?>
<section class="sport-overview-strip"><article><span>Atividades</span><strong><?php echo (int)$currentMetrics['activities']; ?></strong><small><?php echo stridebr_e($deltaText((float)$currentMetrics['activities'], (float)$previousMetrics['activities'])); ?></small></article><article><span>Tempo</span><strong><?php echo stridebr_e($fmtDuration((float)$currentMetrics['duration_s'])); ?></strong><small><?php echo stridebr_e($deltaText((float)$currentMetrics['duration_s'], (float)$previousMetrics['duration_s'])); ?></small></article><article><span>Distância</span><strong><?php echo stridebr_e($fmtDistance((float)$currentMetrics['distance_m'])); ?></strong><small>Somada onde a modalidade usa distância</small></article><article><span>Elevação</span><strong><?php echo number_format((float)$currentMetrics['elevation_m'], 0, ',', '.'); ?> m</strong><small>Ganho registrado no período</small></article><article><span>Energia</span><strong><?php echo stridebr_e($fmtCalories((float)$currentMetrics['calories_kcal'])); ?></strong><small>Informada pelo dispositivo ou estimada pelo StrideBR</small></article></section>
<section class="insight-section"><header><div><h2>Consistência</h2><?php if ($frequency > 0): ?><p class="insight-muted"><strong><?php echo (int)$progress['weeks_met']; ?> das 8 semanas até <?php echo stridebr_e($progressAnchorDate); ?></strong> atingiram sua frequência planejada de <?php echo $frequency; ?> dia<?php echo $frequency===1?'':'s'; ?> por semana.</p><?php else: ?><p class="insight-muted">Atividades das últimas 8 semanas até <?php echo stridebr_e($progressAnchorDate); ?>.</p><?php endif; ?></div><a class="product-button-secondary" href="/user/settings.php#preferencias-treino">Ajustar preferências</a></header><div class="week-chart" aria-label="Atividades por semana"><?php foreach($weeks as $week): $count=(int)$week['activities'];$height=max(4,(int)round(($count/$maxWeekActivities)*120));$met=$frequency>0&&$count>=$frequency; ?><div class="week-column<?php echo $met?' is-met':''; ?>"><div class="week-bar-wrap"><div class="week-bar" style="height:<?php echo $height; ?>px" title="<?php echo $count; ?> atividade(s)"></div></div><strong><?php echo $count; ?></strong><small><?php echo (new DateTimeImmutable((string)$week['week_start']))->format('d/m'); ?></small></div><?php endforeach; ?></div></section>
<section class="insight-section"><header><div><h2>4 semanas até <?php echo stridebr_e($progressAnchorDate); ?></h2><p class="insight-muted">Comparação com as quatro semanas anteriores. A janela atual termina em <?php echo stridebr_e($progressAnchorDate); ?>.</p></div></header><div class="progress-trends"><?php foreach($trendCards as $card): ?><article class="trend-card"><span><?php echo stridebr_e((string)$card['label']); ?></span><strong><?php echo stridebr_e((string)$card[0]); ?></strong><small><?php echo stridebr_e((string)$card[1]); ?></small></article><?php endforeach; ?></div></section>
<section class="insight-section"><header><div><h2>Por tipo</h2><p class="insight-muted">Cada área abre análises próprias sem misturar métricas que não são comparáveis.</p></div></header><div class="sport-area-cards"><?php foreach ($activeFamilies as $bucket => $meta): $metric=$hub[$bucket]['current']; ?><a href="<?php echo stridebr_e($progressAreaUrl((string)$bucket)); ?>"><span><?php echo stridebr_e((string)$meta['label']); ?></span><strong><?php echo (int)$metric['activities']; ?> atividade<?php echo (int)$metric['activities']===1?'':'s'; ?></strong><small><?php echo stridebr_e($fmtDuration((float)$metric['duration_s'])); ?> no período<?php echo (float)$metric['calories_kcal']>0?' · '.stridebr_e($fmtCalories((float)$metric['calories_kcal'])):''; ?></small></a><?php endforeach; ?></div></section>
<section class="insight-section"><header><div><h2>Marcas pessoais</h2><p class="insight-muted">Histórico geral, independentemente do período selecionado.</p></div></header><div class="insight-grid"><article class="insight-card"><span>Maior distância registrada</span><strong><?php echo !empty($records['max_distance'])?$fmtDistance((float)$records['max_distance']):'—'; ?></strong><small>em uma atividade com rota</small></article><article class="insight-card"><span>Maior ganho de elevação</span><strong><?php echo !empty($records['max_elevation'])?number_format((float)$records['max_elevation'],0,',','.').' m':'—'; ?></strong><small>em uma atividade com rota</small></article><article class="insight-card"><span>Atividade mais longa</span><strong><?php echo !empty($records['max_duration'])?$fmtDuration((float)$records['max_duration']):'—'; ?></strong><small>por duração registrada</small></article><article class="insight-card"><span>Maior carga registrada</span><strong><?php echo $progress['max_load']!==null?number_format((float)$progress['max_load'],1,',','.').' kg':'—'; ?></strong><small>maior valor registrado</small></article></div></section>
<?php elseif ($area === 'strength' && is_array($strength)): $s=$strength['summary']; ?>
<section class="sport-overview-strip strength-summary"><article><span>Treinos</span><strong><?php echo (int)$s['current']['workouts']; ?></strong><small><?php echo stridebr_e($deltaText((float)$s['current']['workouts'], (float)$s['previous']['workouts'])); ?></small></article><article><span>Duração</span><strong><?php echo stridebr_e($fmtDuration((float)$s['current']['duration_s'])); ?></strong><small><?php echo stridebr_e($deltaText((float)$s['current']['duration_s'], (float)$s['previous']['duration_s'])); ?></small></article><article><span>Séries</span><strong><?php echo number_format((int)$s['current']['sets'],0,',','.'); ?></strong><small><?php echo stridebr_e($deltaText((float)$s['current']['sets'], (float)$s['previous']['sets'])); ?></small></article><article><span>Volume</span><strong><?php echo stridebr_e($fmtVolume((float)$s['current']['volume_kg'])); ?></strong><small><?php echo stridebr_e($deltaText((float)$s['current']['volume_kg'], (float)$s['previous']['volume_kg'])); ?></small></article><article><span>Novas marcas</span><strong><?php echo (int)$s['current']['prs']; ?></strong><small><?php echo (int)$s['current']['prs']===0?'Sem nova melhor carga/e1RM no período':stridebr_e($deltaText((float)$s['current']['prs'], (float)$s['previous']['prs'])); ?></small></article></section>
<?php if (is_array($strengthExercise)): $exerciseHistory=(array)$strengthExercise['history']; $exerciseSpark=$strengthSparkline($exerciseHistory); ?>
<section class="insight-section strength-exercise-detail">
<header><div><span class="eyebrow">Exercício</span><h2><?php echo stridebr_e((string)$strengthExercise['nome']); ?></h2><p class="insight-muted">Evolução registrada nas últimas sessões com carga.</p></div><a class="product-button-secondary" href="<?php echo stridebr_e($progressAreaUrl('strength')); ?>">Fechar detalhe</a></header>
<div class="strength-exercise-detail-summary"><article><span>Melhor carga</span><strong><?php echo $strengthExercise['best_load']!==null?number_format((float)$strengthExercise['best_load'],1,',','.').' kg':'—'; ?></strong></article><article><span>1RM estimado</span><strong><?php echo $strengthExercise['best_e1rm']!==null?number_format((float)$strengthExercise['best_e1rm'],1,',','.').' kg':'—'; ?></strong><small>estimativa por Epley</small></article><article><span>Volume acumulado</span><strong><?php echo stridebr_e($fmtVolume((float)$strengthExercise['volume'])); ?></strong></article><article><span>Séries registradas</span><strong><?php echo (int)$strengthExercise['sets']; ?></strong></article></div>
<div class="strength-exercise-detail-grid"><div class="strength-exercise-chart<?php echo $exerciseSpark===''?' is-empty':''; ?>"><?php echo $exerciseSpark!==''?$exerciseSpark:'<span>Registre mais sessões para formar a curva.</span>'; ?></div><div class="strength-session-table"><div class="strength-session-row is-head"><span>Data</span><span>Carga</span><span>e1RM</span><span>Séries</span><span>Volume</span></div><?php foreach(array_reverse($exerciseHistory) as $session): ?><div class="strength-session-row"><span><?php echo (new DateTimeImmutable((string)$session['date']))->format('d/m/Y'); ?></span><strong><?php echo $session['max_load']!==null?number_format((float)$session['max_load'],1,',','.').' kg':'—'; ?></strong><span><?php echo $session['best_e1rm']!==null?number_format((float)$session['best_e1rm'],1,',','.').' kg':'—'; ?></span><span><?php echo (int)$session['sets']; ?></span><span><?php echo stridebr_e($fmtVolume((float)$session['volume'])); ?></span></div><?php endforeach; ?></div></div>
</section>
<?php endif; ?>
<div class="sport-hub-columns"><section class="insight-section"><header><div><h2>Progresso por exercício</h2><p class="insight-muted">Carga, volume e 1RM estimado a partir das séries registradas.</p></div></header><?php if ($strength['exercises'] === []): ?><div class="sport-hub-empty"><strong>Registre carga e repetições durante o treino</strong><p>Os próximos treinos passam a alimentar o histórico de cada exercício automaticamente.</p></div><?php else: ?><div class="strength-exercise-list"><?php foreach ($strength['exercises'] as $exercise): $delta=($exercise['latest_load']!==null&&$exercise['previous_load']!==null)?(float)$exercise['latest_load']-(float)$exercise['previous_load']:null; ?><article<?php echo $strengthExercise!==null&&(string)$strengthExercise['key']===(string)$exercise['key']?' class="is-selected"':''; ?>><div><strong><?php echo stridebr_e($exercise['nome']); ?></strong><small><?php echo (int)$exercise['sets']; ?> séries registradas</small></div><?php $spark=$strengthSparkline((array)$exercise['history']); ?><div class="strength-exercise-sparkline<?php echo $spark===''?' is-empty':''; ?>"><?php echo $spark!==''?$spark:'<span>Mais sessões para mostrar tendência</span>'; ?></div><div class="strength-exercise-values"><span><small>Melhor carga</small><b><?php echo $exercise['best_load']!==null?number_format((float)$exercise['best_load'],1,',','.').' kg':'—'; ?></b></span><span><small>1RM estimado</small><b><?php echo $exercise['best_e1rm']!==null?number_format((float)$exercise['best_e1rm'],1,',','.').' kg':'—'; ?></b></span><?php if ($delta !== null): ?><span class="<?php echo $delta>0?'is-up':($delta<0?'is-down':''); ?>"><small>Última sessão</small><b><?php echo ($delta>0?'+':'').number_format($delta,1,',','.'); ?> kg</b></span><?php endif; ?><?php if ($exercise['load_trend_pct']!==null): ?><span class="<?php echo (float)$exercise['load_trend_pct']>0?'is-up':((float)$exercise['load_trend_pct']<0?'is-down':''); ?>"><small>6 semanas</small><b><?php echo stridebr_e($fmtSignedPercent((float)$exercise['load_trend_pct'])); ?></b></span><?php endif; ?><?php if (!empty($exercise['latest_is_pr'])): ?><span class="is-pr"><small>Última sessão</small><b>Nova marca</b></span><?php endif; ?></div><a class="strength-exercise-open" href="<?php echo stridebr_e($progressAreaUrl('strength', ['exercise' => (string)$exercise['key']])); ?>">Ver histórico</a></article><?php endforeach; ?></div><?php endif; ?></section>
<section class="insight-section strength-calendar-section"><header><div><h2><?php echo stridebr_e($monthNames[(int)$monthNow->format('n')]); ?></h2><p class="insight-muted">Dias com treino de força.</p></div></header><div class="strength-calendar"><div class="strength-calendar-head"><span>S</span><span>T</span><span>Q</span><span>Q</span><span>S</span><span>S</span><span>D</span></div><div class="strength-calendar-grid"><?php for($blank=0;$blank<$firstWeekday;$blank++): ?><span class="is-empty"></span><?php endfor; ?><?php for($day=1;$day<=$daysInMonth;$day++): $date=$monthNow->format('Y-m-').sprintf('%02d',$day);$count=(int)($strength['calendar'][$date]??0); ?><span class="<?php echo $count>0?'has-workout':''; ?>" title="<?php echo $count>0?$count.' treino(s)':''; ?>"><?php echo $day; ?></span><?php endfor; ?></div></div></section></div>
<section class="insight-section"><header><div><h2>Distribuição muscular</h2><p class="insight-muted">Estimativa pelas séries dos exercícios que possuem grupos musculares cadastrados.</p></div></header><?php if ($strength['muscles'] === []): ?><div class="sport-hub-empty"><p>Os exercícios ainda não têm dados musculares suficientes para montar a distribuição.</p></div><?php else: $maxMuscle=max($strength['muscles']); ?><div class="muscle-bars"><?php foreach($strength['muscles'] as $muscle=>$value): ?><div><span><?php echo stridebr_e($muscleLabels[$muscle]??ucfirst(str_replace('-',' ',$muscle))); ?></span><div><i style="width:<?php echo max(3,(int)round(($value/$maxMuscle)*100)); ?>%"></i></div><strong><?php echo number_format((float)$value,1,',','.'); ?></strong></div><?php endforeach; ?></div><?php endif; ?></section>
<?php elseif ($area === 'cardio' && is_array($cardio)): $cardioMeta=$cardio['active'][$cardioSport]??$cardio['active']['all']; $c=$cardio['summary'][$cardioSport]['current']; $cp=$cardio['summary'][$cardioSport]['previous']; $cardioMetric=(string)($cardioMeta['metric']??'distance'); $cardioTrend=$cardio['trends'][$cardioSport]??[]; $cardioRecent=$cardio['recent'][$cardioSport]??[]; ?>
<nav class="sport-subtabs" aria-label="Modalidade de cardio"><?php foreach($cardio['active'] as $key=>$meta): ?><a href="<?php echo stridebr_e($progressAreaUrl('cardio', ['sport' => (string)$key])); ?>"<?php echo $cardioSport===$key?' class="is-active" aria-current="page"':''; ?>><?php echo stridebr_e((string)$meta['label']); ?></a><?php endforeach; ?></nav>
<section class="sport-overview-strip">
    <article><span><?php echo $cardioSport==='all'?'Atividades':'Sessões'; ?></span><strong><?php echo (int)$c['activities']; ?></strong><small><?php echo stridebr_e($deltaText((float)$c['activities'],(float)$cp['activities'])); ?></small></article>
    <article><span>Distância</span><strong><?php echo stridebr_e($fmtDistance((float)$c['distance_m'])); ?></strong><small><?php echo $cardioSport==='machine'?'Quando registrada':'Somada no período'; ?></small></article>
    <article><span>Tempo</span><strong><?php echo stridebr_e($fmtDuration((float)$c['duration_s'])); ?></strong><small><?php echo stridebr_e($deltaText((float)$c['duration_s'],(float)$cp['duration_s'])); ?></small></article>
    <?php if ($cardioMetric==='pace_km'): ?><article><span>Ritmo médio</span><strong><?php echo stridebr_e($fmtPace($c['avg_pace_km_s']!==null?(float)$c['avg_pace_km_s']:null,'/km')); ?></strong><small><?php echo $c['best_pace_s']!==null?'Melhor registro '.$fmtPace((float)$c['best_pace_s'],'/km'):'Com distância e duração'; ?></small></article>
    <?php elseif ($cardioMetric==='pace_100m'): ?><article><span>Ritmo médio</span><strong><?php echo stridebr_e($fmtPace($c['avg_pace_100m_s']!==null?(float)$c['avg_pace_100m_s']:null,'/100 m')); ?></strong><small><?php echo $c['best_pace_s']!==null?'Melhor registro '.$fmtPace((float)$c['best_pace_s'],'/100 m'):'Com distância e duração'; ?></small></article>
    <?php elseif ($cardioMetric==='speed'): ?><article><span>Velocidade média</span><strong><?php echo stridebr_e($fmtSpeed($c['avg_speed_kmh']!==null?(float)$c['avg_speed_kmh']:null)); ?></strong><small><?php echo $c['best_speed_kmh']!==null?'Melhor registro '.$fmtSpeed((float)$c['best_speed_kmh']):'Com distância e duração'; ?></small></article>
    <?php else: ?><article><span>Elevação</span><strong><?php echo number_format((float)$c['elevation_m'],0,',','.'); ?> m</strong><small>Ganho acumulado</small></article><?php endif; ?>
    <article><span>Energia</span><strong><?php echo stridebr_e($fmtCalories((float)$c['calories_kcal'])); ?></strong><small>Informada ou estimada</small></article>
</section>
<?php
$performanceTrend = $cardioMetric === 'pace_km' || $cardioMetric === 'pace_100m' ? ($cardioTrend['pace_pct'] ?? null) : ($cardioMetric === 'speed' ? ($cardioTrend['speed_pct'] ?? null) : null);
$hasTechnique = $c['avg_hr_bpm'] !== null || $c['max_hr_bpm'] !== null || $c['avg_cadence'] !== null || $c['avg_power_w'] !== null;
?>
<section class="insight-section cardio-trend-section"><header><div><h2>Últimas 4 semanas</h2><p class="insight-muted">Comparação com as quatro semanas anteriores desta modalidade.</p></div></header><div class="cardio-trend-grid">
<article><span>Frequência</span><strong><?php echo (int)array_sum(array_map(static fn(array $w): int => (int)$w['activities'], array_slice(array_values($cardio['weekly'][$cardioSport]??[]),4,4))); ?> sessões</strong><small><?php echo stridebr_e($fmtSignedPercent($cardioTrend['activities_pct']??null)); ?></small></article>
<article><span><?php echo $cardioMetric==='time'?'Tempo':'Distância'; ?></span><strong><?php echo $cardioMetric==='time'?stridebr_e($fmtDuration((float)($cardioTrend['current_duration_s']??0))):stridebr_e($fmtDistance((float)($cardioTrend['current_distance_m']??0))); ?></strong><small><?php echo stridebr_e($fmtSignedPercent($cardioMetric==='time'?($cardioTrend['duration_pct']??null):($cardioTrend['distance_pct']??null))); ?></small></article>
<?php if ($performanceTrend !== null): ?><article><span><?php echo str_starts_with($cardioMetric,'pace')?'Ritmo':'Velocidade'; ?></span><strong><?php echo stridebr_e($fmtSignedPercent((float)$performanceTrend)); ?></strong><small><?php echo (float)$performanceTrend>0?'melhora estimada':((float)$performanceTrend<0?'redução no período':'sem mudança relevante'); ?></small></article><?php endif; ?>
<?php if ((float)$c['longest_distance_m']>0): ?><article><span>Maior distância</span><strong><?php echo stridebr_e($fmtDistance((float)$c['longest_distance_m'])); ?></strong><small>Maior sessão do período</small></article><?php endif; ?>
</div></section>
<?php if ($hasTechnique): ?><section class="insight-section cardio-technique-section"><header><div><h2>Esforço & técnica</h2><p class="insight-muted">Médias disponíveis nos registros do período. Dados de relógio, arquivo ou preenchimento manual.</p></div></header><div class="cardio-technique-grid">
<?php if ($c['avg_hr_bpm']!==null): ?><article><span>FC média</span><strong><?php echo number_format((float)$c['avg_hr_bpm'],0,',','.'); ?> bpm</strong><?php if ($c['max_hr_bpm']!==null): ?><small>máxima registrada <?php echo number_format((float)$c['max_hr_bpm'],0,',','.'); ?> bpm</small><?php endif; ?></article><?php endif; ?>
<?php if ($c['avg_cadence']!==null): ?><article><span>Cadência média</span><strong><?php echo number_format((float)$c['avg_cadence'],0,',','.'); ?></strong><small><?php echo in_array($cardioSport,['run','walk'],true)?'passos/min quando informado':'rpm ou ciclos/min conforme a modalidade'; ?></small></article><?php endif; ?>
<?php if ($c['avg_power_w']!==null): ?><article><span>Potência média</span><strong><?php echo number_format((float)$c['avg_power_w'],0,',','.'); ?> W</strong><small>quando disponível no dispositivo</small></article><?php endif; ?>
</div></section><?php endif; ?>
<?php $weeklySeries=$cardio['weekly'][$cardioSport]??[]; $weeklyUseDistance=$cardioMetric!=='time' && array_sum(array_column($weeklySeries,'distance_m'))>0; $weeklyMax=1.0; foreach($weeklySeries as $week) $weeklyMax=max($weeklyMax,$weeklyUseDistance?(float)$week['distance_m']:(float)$week['duration_s']); ?>
<section class="insight-section"><header><div><h2>Últimas 8 semanas</h2><p class="insight-muted"><?php echo $weeklyUseDistance?'Volume por distância.':'Volume por tempo.'; ?></p></div></header><div class="cardio-week-bars"><?php foreach($weeklySeries as $date=>$week): $value=$weeklyUseDistance?(float)$week['distance_m']:(float)$week['duration_s']; ?><div><span style="height:<?php echo max(3,(int)round(($value/$weeklyMax)*100)); ?>%"></span><strong><?php echo $weeklyUseDistance?stridebr_e($fmtDistance((float)$week['distance_m'])):stridebr_e($fmtDuration((float)$week['duration_s'])); ?></strong><small><?php echo (new DateTimeImmutable($date))->format('d/m'); ?></small></div><?php endforeach; ?></div></section>
<?php if ($cardioRecent !== []): ?><section class="insight-section"><header><div><h2>Sessões recentes</h2><p class="insight-muted">Comparação rápida sem misturar métricas de modalidades diferentes.</p></div></header><div class="cardio-recent-list">
<?php foreach(array_slice($cardioRecent,0,8) as $session): ?>
<a href="/user/atividade.php?id=<?php echo rawurlencode((string)$session['idregistro']); ?>" class="cardio-recent-row"><div class="cardio-recent-main"><strong><?php echo stridebr_e((string)$session['title']); ?></strong><small><?php echo (new DateTimeImmutable((string)$session['date']))->format('d/m/Y'); ?><?php echo $cardioSport==='all'?' · '.stridebr_e((string)$session['sport']):''; ?><?php echo trim((string)$session['provider'])!==''?' · '.stridebr_e(ucfirst((string)$session['provider'])):''; ?></small></div><div><span>Distância</span><strong><?php echo (float)$session['distance_m']>0?stridebr_e($fmtDistance((float)$session['distance_m'])):'—'; ?></strong></div><div><span>Tempo</span><strong><?php echo stridebr_e($fmtDuration((float)$session['duration_s'])); ?></strong></div><div><span><?php echo $cardioMetric==='speed'?'Velocidade':(str_starts_with($cardioMetric,'pace')?'Ritmo':'Esforço'); ?></span><strong><?php if ($cardioMetric==='pace_km') echo stridebr_e($fmtPace($session['pace_s']!==null?(float)$session['pace_s']:null,'/km')); elseif($cardioMetric==='pace_100m') echo stridebr_e($fmtPace($session['pace_s']!==null?(float)$session['pace_s']:null,'/100 m')); elseif($cardioMetric==='speed') echo stridebr_e($fmtSpeed($session['speed_kmh']!==null?(float)$session['speed_kmh']:null)); elseif($session['avg_hr_bpm']!==null) echo number_format((float)$session['avg_hr_bpm'],0,',','.').' bpm'; else echo '—'; ?></strong></div><?php if ($session['avg_hr_bpm']!==null || $session['power_w']!==null): ?><div><span><?php echo $session['power_w']!==null?'Potência':'FC média'; ?></span><strong><?php echo $session['power_w']!==null?number_format((float)$session['power_w'],0,',','.').' W':number_format((float)$session['avg_hr_bpm'],0,',','.').' bpm'; ?></strong></div><?php endif; ?></a>
<?php endforeach; ?>
</div></section><?php endif; ?>
<section class="insight-section"><header><div><h2><?php echo stridebr_e((string)$cardioMeta['label']); ?></h2><p class="insight-muted"><?php echo $cardioSport==='all'?'Corrida, caminhada, ciclismo, natação e outras atividades de cardio, sem misturar o que não é comparável.':'Detalhes das modalidades que você registrou nesta área.'; ?></p></div></header><?php if($breakdown===[]): ?><div class="sport-hub-empty"><p>Nenhuma atividade desta área foi registrada no período selecionado.</p></div><?php else: ?><div class="sport-breakdown"><?php foreach($breakdown as $name=>$item): ?><article><strong><?php echo stridebr_e($name); ?></strong><span><?php echo (int)$item['activities']; ?> atividade<?php echo (int)$item['activities']===1?'':'s'; ?></span><small><?php echo stridebr_e($fmtDuration((float)$item['duration_s'])); ?><?php echo (float)$item['distance_m']>0?' · '.stridebr_e($fmtDistance((float)$item['distance_m'])):''; ?><?php echo (float)($item['calories_kcal']??0)>0?' · '.stridebr_e($fmtCalories((float)$item['calories_kcal'])):''; ?></small></article><?php endforeach; ?></div><?php endif; ?></section>
<?php elseif ($area === 'athletics' && is_array($athletics)): ?>
<section class="sport-overview-strip">
    <article><span>Sessões</span><strong><?php echo (int)$currentMetrics['activities']; ?></strong><small><?php echo stridebr_e($deltaText((float)$currentMetrics['activities'], (float)$previousMetrics['activities'])); ?></small></article>
    <article><span>Tempo</span><strong><?php echo stridebr_e($fmtDuration((float)$currentMetrics['duration_s'])); ?></strong><small>Treinos e provas no período</small></article>
    <article><span>Provas praticadas</span><strong><?php echo (int)$athletics['events_practiced']; ?></strong><small>No período selecionado</small></article>
    <article><span>Tentativas</span><strong><?php echo (int)$athletics['current_attempts']; ?></strong><small><?php echo (int)$athletics['current_valid_attempts']; ?> com marca válida no período</small></article>
    <article><span>Energia</span><strong><?php echo stridebr_e($fmtCalories((float)$currentMetrics['calories_kcal'])); ?></strong><small>Informada ou estimada</small></article>
</section>
<?php if ($athletics['field_events'] !== []): ?>
<section class="insight-section">
    <header><div><h2>Melhores marcas</h2><p class="insight-muted">Histórico de saltos, lançamentos e arremessos até o fim do período de referência.</p></div></header>
    <div class="athletics-record-grid">
        <?php foreach ($athletics['field_events'] as $event): $spark=$metricSparkline((array)$event['history'],'best_mark'); ?>
            <article>
                <div><strong><?php echo stridebr_e((string)$event['nome']); ?></strong><small><?php echo (int)$event['sessions']; ?> sessão<?php echo (int)$event['sessions']===1?'':'ões'; ?> · <?php echo (int)$event['attempts']; ?> tentativa<?php echo (int)$event['attempts']===1?'':'s'; ?></small></div>
                <div class="athletics-record-value"><span>Melhor marca</span><b><?php echo $event['best_mark']!==null?number_format((float)$event['best_mark'],2,',','.').' m':'—'; ?></b><?php if ($event['best_wind']!==null): ?><small>vento <?php echo number_format((float)$event['best_wind'],1,',','.'); ?> m/s<?php echo !empty($event['wind_aided_best'])?' · acima de +2,0 m/s':''; ?></small><?php endif; ?><?php if (!empty($event['wind_aided_best']) && $event['best_legal_mark']!==null): ?><small>melhor com vento válido <?php echo number_format((float)$event['best_legal_mark'],2,',','.'); ?> m</small><?php endif; ?></div>
                <div class="athletics-sparkline<?php echo $spark===''?' is-empty':''; ?>"><?php echo $spark!==''?$spark:'<span>Mais sessões para mostrar evolução</span>'; ?></div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<?php if ($athletics['track_records'] !== []): ?>
<section class="insight-section">
    <header><div><h2>Melhores tempos</h2><p class="insight-muted">Histórico de provas de pista até o fim do período de referência.</p></div></header>
    <div class="athletics-record-grid">
        <?php foreach ($athletics['track_records'] as $event): $spark=$metricSparkline((array)$event['history'],'time_s',true); ?>
            <article>
                <div><strong><?php echo stridebr_e((string)$event['nome']); ?></strong><small><?php echo (int)$event['sessions']; ?> registro<?php echo (int)$event['sessions']===1?'':'s'; ?></small></div>
                <div class="athletics-record-value"><span>Melhor tempo</span><b><?php echo stridebr_e($fmtRaceTime((float)$event['best_time_s'])); ?></b><?php if ($event['best_wind_m_s']!==null): ?><small>vento <?php echo number_format((float)$event['best_wind_m_s'],1,',','.'); ?> m/s<?php echo (float)$event['best_wind_m_s']>2.0?' · acima de +2,0 m/s':''; ?></small><?php endif; ?><?php if ($event['best_legal_time_s']!==null && $event['best_time_s']!==null && abs((float)$event['best_legal_time_s']-(float)$event['best_time_s'])>.001): ?><small>melhor com vento válido <?php echo stridebr_e($fmtRaceTime((float)$event['best_legal_time_s'])); ?></small><?php endif; ?><?php if ($event['best_reaction_s']!==null): ?><small>melhor reação <?php echo number_format((float)$event['best_reaction_s'],3,',','.'); ?> s</small><?php endif; ?><?php if ($event['latest_time_s']!==null && $event['best_time_s']!==null && abs((float)$event['latest_time_s']-(float)$event['best_time_s'])>.001): ?><small>último <?php echo stridebr_e($fmtRaceTime((float)$event['latest_time_s'])); ?></small><?php endif; ?></div>
                <div class="athletics-sparkline<?php echo $spark===''?' is-empty':''; ?>"><?php echo $spark!==''?$spark:'<span>Mais provas para mostrar evolução</span>'; ?></div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<section class="insight-section"><header><div><h2>Por prova</h2><p class="insight-muted">Sessões do período separadas por modalidade do atletismo.</p></div></header><?php if ($breakdown===[]): ?><div class="sport-hub-empty"><p>Nenhum registro de atletismo no período selecionado.</p></div><?php else: ?><div class="sport-breakdown"><?php foreach($breakdown as $name=>$item): ?><article><strong><?php echo stridebr_e($name); ?></strong><span><?php echo (int)$item['activities']; ?> atividade<?php echo (int)$item['activities']===1?'':'s'; ?></span><small><?php echo stridebr_e($fmtDuration((float)$item['duration_s'])); ?><?php echo (float)($item['calories_kcal']??0)>0?' · '.stridebr_e($fmtCalories((float)$item['calories_kcal'])):''; ?></small></article><?php endforeach; ?></div><?php endif; ?></section>
<?php elseif (in_array($area, ['racket','team','combat','precision'], true) && is_array($sessionDashboard)): $familyMeta=$familyDefinitions[$area]??['label'=>ucfirst($area),'description'=>'']; $sd=$sessionDashboard['summary']['current']; $sdp=$sessionDashboard['summary']['previous']; ?>
<section class="sport-overview-strip sport-session-overview">
    <article><span>Sessões</span><strong><?php echo (int)$sd['activities']; ?></strong><small><?php echo stridebr_e($deltaText((float)$sd['activities'], (float)$sdp['activities'])); ?></small></article>
    <article><span>Tempo</span><strong><?php echo stridebr_e($fmtDuration((float)$sd['duration_s'])); ?></strong><small><?php echo (int)$sd['activities']>0?'média de '.stridebr_e($fmtDuration((float)$sd['avg_duration_s'])):'Sem sessões no período'; ?></small></article>
    <?php if ($area === 'racket' || $area === 'team'): ?>
        <article><span><?php echo $area==='racket'?'Partidas':'Jogos'; ?></span><strong><?php echo (int)$sd['matches']; ?></strong><small><?php echo (int)$sd['decided']>0?((int)$sd['wins'].' V · '.(int)$sd['draws'].' E · '.(int)$sd['losses'].' D'):'Resultado opcional'; ?></small></article>
        <article><span>Aproveitamento</span><strong><?php echo $sd['win_rate']!==null?number_format((float)$sd['win_rate'],0,',','.').'%':'—'; ?></strong><small>Entre resultados informados</small></article>
    <?php elseif ($area === 'combat'): ?>
        <article><span>Rounds</span><strong><?php echo (int)$sd['rounds']; ?></strong><small>Quando informados</small></article>
        <article><span>Resultados</span><strong><?php echo (int)$sd['decided']; ?></strong><small><?php echo (int)$sd['decided']>0?((int)$sd['wins'].' V · '.(int)$sd['draws'].' E · '.(int)$sd['losses'].' D'):'Competições e lutas opcionais'; ?></small></article>
    <?php else: ?>
        <article><span>Melhor pontuação</span><strong><?php echo $sd['best_score']!==null?number_format((float)$sd['best_score'],2,',','.'):'—'; ?></strong><small>Entre sessões com pontuação</small></article>
        <article><span>Média</span><strong><?php echo $sd['avg_score']!==null?number_format((float)$sd['avg_score'],2,',','.'):'—'; ?></strong><small>Pontuação média no período</small></article>
    <?php endif; ?>
    <article><span>Energia</span><strong><?php echo stridebr_e($fmtCalories((float)$currentMetrics['calories_kcal'])); ?></strong><small>Informada ou estimada</small></article>
</section>
<?php $sessionWeeks=(array)($sessionDashboard['weeks']??[]); $sessionWeekMax=1; foreach($sessionWeeks as $week){$sessionWeekMax=max($sessionWeekMax,(int)($week['activities']??0));} ?>
<?php if($sessionWeeks!==[]): ?><section class="insight-section sport-session-trend-section"><header><div><h2>Últimas 8 semanas</h2><p class="insight-muted">Frequência semanal sem transformar mais sessões em uma meta automática.</p></div></header><div class="sport-session-weekly"><?php foreach($sessionWeeks as $week): $height=max(6,((int)($week['activities']??0)/$sessionWeekMax)*100); ?><article title="<?php echo (int)($week['activities']??0); ?> sessão(ões)"><div class="sport-session-weekly-bar"><span style="height:<?php echo number_format($height,1,'.',''); ?>%"></span></div><strong><?php echo (int)($week['activities']??0); ?></strong><small><?php echo stridebr_e((string)($week['label']??'')); ?></small></article><?php endforeach; ?></div></section><?php endif; ?>
<section class="insight-section"><header><div><h2><?php echo stridebr_e((string)$familyMeta['label']); ?></h2><p class="insight-muted"><?php echo stridebr_e((string)$familyMeta['description']); ?></p></div></header><?php if($breakdown===[]): ?><div class="sport-hub-empty"><p>Nenhuma atividade desta área foi registrada no período selecionado.</p></div><?php else: ?><div class="sport-breakdown"><?php foreach($breakdown as $name=>$item): ?><article><strong><?php echo stridebr_e($name); ?></strong><span><?php echo (int)$item['activities']; ?> atividade<?php echo (int)$item['activities']===1?'':'s'; ?></span><small><?php echo stridebr_e($fmtDuration((float)$item['duration_s'])); ?><?php echo (float)($item['calories_kcal']??0)>0?' · '.stridebr_e($fmtCalories((float)$item['calories_kcal'])):''; ?></small></article><?php endforeach; ?></div><?php endif; ?></section>
<?php if (($sessionDashboard['recent'] ?? []) !== []): ?>
<section class="insight-section"><header><div><h2>Últimas sessões</h2><p class="insight-muted">Detalhes esportivos aparecem quando você os registra.</p></div></header><div class="sport-session-list">
<?php foreach ($sessionDashboard['recent'] as $session): $resultLabel=sportHubResultLabel((string)$session['result']); $typeLabel=sportHubSessionTypeLabel((string)$session['type']); ?>
<a href="/user/atividade.php?id=<?php echo rawurlencode((string)$session['idregistro']); ?>" class="sport-session-row">
    <div><strong><?php echo stridebr_e((string)$session['title']); ?></strong><small><?php echo stridebr_e((new DateTimeImmutable((string)$session['date']))->format('d/m/Y')); ?> · <?php echo stridebr_e((string)$session['sport']); ?> · <?php echo stridebr_e($typeLabel); ?></small></div>
    <div class="sport-session-facts">
        <?php if ($resultLabel !== ''): ?><span class="sport-session-result is-<?php echo stridebr_e((string)$session['result']); ?>"><?php echo stridebr_e($resultLabel); ?></span><?php endif; ?>
        <?php if ((string)$session['opponent'] !== ''): ?><span>vs. <?php echo stridebr_e((string)$session['opponent']); ?></span><?php endif; ?>
        <?php if ((string)$session['score'] !== ''): ?><span><?php echo stridebr_e((string)$session['score']); ?></span><?php elseif ($session['score_for'] !== null && $session['score_against'] !== null): ?><span><?php echo (int)$session['score_for']; ?> × <?php echo (int)$session['score_against']; ?></span><?php endif; ?>
        <?php if ((int)$session['rounds'] > 0): ?><span><?php echo (int)$session['rounds']; ?> rounds</span><?php endif; ?>
        <?php if ($session['points'] !== null): ?><span><?php echo number_format((float)$session['points'],2,',','.'); ?> pts</span><?php endif; ?>
        <span><?php echo stridebr_e($fmtDuration((float)$session['duration_s'])); ?></span>
    </div>
</a>
<?php endforeach; ?>
</div></section>
<?php endif; ?>
<?php else: $familyMeta=$familyDefinitions[$area]??['label'=>ucfirst($area),'description'=>'']; $isCardio=$area==='cardio'; $isRacket=$area==='racket'; ?>
<section class="sport-overview-strip"><article><span>Atividades</span><strong><?php echo (int)$currentMetrics['activities']; ?></strong><small><?php echo stridebr_e($deltaText((float)$currentMetrics['activities'], (float)$previousMetrics['activities'])); ?></small></article><article><span>Tempo</span><strong><?php echo stridebr_e($fmtDuration((float)$currentMetrics['duration_s'])); ?></strong><small><?php echo stridebr_e($deltaText((float)$currentMetrics['duration_s'], (float)$previousMetrics['duration_s'])); ?></small></article><?php if ($isCardio): ?><article><span>Distância</span><strong><?php echo stridebr_e($fmtDistance((float)$currentMetrics['distance_m'])); ?></strong><small>Somada no período</small></article><article><span>Elevação</span><strong><?php echo number_format((float)$currentMetrics['elevation_m'],0,',','.'); ?> m</strong><small>Ganho no período</small></article><?php elseif ($isRacket): ?><article><span>Sessões</span><strong><?php echo (int)$currentMetrics['activities']; ?></strong><small>Treinos e partidas registrados</small></article><article><span>Média por sessão</span><strong><?php echo stridebr_e($fmtDuration((float)$currentMetrics['duration_s']/max(1,(int)$currentMetrics['activities']))); ?></strong><small>Duração média no período</small></article><?php else: ?><article><span>Média por sessão</span><strong><?php echo stridebr_e($fmtDuration((float)$currentMetrics['duration_s']/max(1,(int)$currentMetrics['activities']))); ?></strong><small>Duração média no período</small></article><article><span>Energia</span><strong><?php echo stridebr_e($fmtCalories((float)$currentMetrics['calories_kcal'])); ?></strong><small>Informada ou estimada</small></article><?php endif; ?></section>
<section class="insight-section"><header><div><h2><?php echo stridebr_e((string)$familyMeta['label']); ?></h2><p class="insight-muted"><?php echo stridebr_e((string)$familyMeta['description']); ?></p></div></header><?php if ($breakdown===[]): ?><div class="sport-hub-empty"><p>Nenhuma atividade desta área foi registrada no período selecionado.</p></div><?php else: ?><div class="sport-breakdown"><?php foreach($breakdown as $name=>$item): ?><article><strong><?php echo stridebr_e($name); ?></strong><span><?php echo (int)$item['activities']; ?> atividade<?php echo (int)$item['activities']===1?'':'s'; ?></span><small><?php echo stridebr_e($fmtDuration((float)$item['duration_s'])); ?><?php echo $isCardio&&$item['distance_m']>0?' · '.stridebr_e($fmtDistance((float)$item['distance_m'])):''; ?><?php echo (float)($item['calories_kcal']??0)>0?' · '.stridebr_e($fmtCalories((float)$item['calories_kcal'])):''; ?></small></article><?php endforeach; ?></div><?php endif; ?></section>
<?php endif; ?>
</main></div><?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?><script src="<?php echo stridebr_e(stridebr_asset('/assets/js/progresso.js')); ?>"></script></body></html>
