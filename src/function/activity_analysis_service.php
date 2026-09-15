<?php

declare(strict_types=1);

const ACTIVITY_ANALYSIS_VERSION = 1;
const ACTIVITY_ANALYSIS_EVEN_THRESHOLD_PERCENT = 2.0;
const ACTIVITY_ANALYSIS_STRONG_FINISH_THRESHOLD_PERCENT = 3.0;
const ACTIVITY_ANALYSIS_VARIABILITY_HIGH_PERCENT = 10.0;
const ACTIVITY_ANALYSIS_HR_COVERAGE_MIN_PERCENT = 80.0;
const ACTIVITY_ANALYSIS_DECOUPLING_MIN_DURATION_S = 1200.0;
const ACTIVITY_ANALYSIS_DECOUPLING_MIN_DISTANCE_M = 2000.0;
const ACTIVITY_ANALYSIS_DRIFT_FINDING_PERCENT = 5.0;

function activityAnalysisPercentile(array $values, float $percentile): ?float
{
    $values = array_values(array_filter($values, static fn(mixed $value): bool => is_numeric($value) && is_finite((float) $value)));
    if ($values === []) return null;
    sort($values, SORT_NUMERIC);
    if (count($values) === 1) return (float) $values[0];
    $position = ($percentile / 100.0) * (count($values) - 1);
    $lower = (int) floor($position);
    $upper = (int) ceil($position);
    if ($lower === $upper) return (float) $values[$lower];
    $ratio = $position - $lower;
    return (float) $values[$lower] + ((float) $values[$upper] - (float) $values[$lower]) * $ratio;
}

function activityAnalysisStats(array $values): ?array
{
    $values = array_values(array_filter($values, static fn(mixed $value): bool => is_numeric($value) && is_finite((float) $value)));
    if ($values === []) return null;
    $count = count($values);
    $mean = array_sum($values) / $count;
    $variance = 0.0;
    foreach ($values as $value) $variance += ((float) $value - $mean) ** 2;
    $std = sqrt($variance / $count);
    $median = activityAnalysisPercentile($values, 50);
    $deviations = array_map(static fn(float|int $value): float => abs((float) $value - (float) $median), $values);
    return [
        'count' => $count,
        'mean' => $mean,
        'median' => $median,
        'standard_deviation' => $std,
        'median_absolute_deviation' => activityAnalysisPercentile($deviations, 50),
        'p10' => activityAnalysisPercentile($values, 10),
        'p50' => $median,
        'p90' => activityAnalysisPercentile($values, 90),
        'coefficient_of_variation_percent' => abs($mean) > 1e-9 ? $std / abs($mean) * 100.0 : null,
    ];
}

function activityAnalysisIntervals(array $samples): array
{
    $intervals = [];
    for ($i = 1; $i < count($samples); $i++) {
        $a = $samples[$i - 1];
        $b = $samples[$i];
        $elapsedDelta = ((float) $b['elapsed_ms'] - (float) $a['elapsed_ms']) / 1000.0;
        $movingA = (float) ($a['moving_ms'] ?? $a['elapsed_ms']);
        $movingB = (float) ($b['moving_ms'] ?? $b['elapsed_ms']);
        $movingDelta = ($movingB - $movingA) / 1000.0;
        $gapBeforeMs = is_numeric($b['gap_before_ms'] ?? null) ? (int) $b['gap_before_ms'] : 0;
        if ($elapsedDelta <= 0 || $elapsedDelta > 120 || $movingDelta < 0 || $movingDelta > $elapsedDelta + 0.001 || $gapBeforeMs > 0) continue;
        $distanceDelta = null;
        if (is_numeric($a['distance_m'] ?? null) && is_numeric($b['distance_m'] ?? null)) {
            $distanceDelta = max(0.0, (float) $b['distance_m'] - (float) $a['distance_m']);
        }
        $speed = null;
        if ($movingDelta > 0 && $distanceDelta !== null) $speed = $distanceDelta / $movingDelta;
        if (($speed === null || $speed <= 0) && is_numeric($a['speed_mps'] ?? null) && is_numeric($b['speed_mps'] ?? null)) $speed = ((float) $a['speed_mps'] + (float) $b['speed_mps']) / 2.0;
        if ($speed !== null && ($speed < 0 || $speed > 60)) $speed = null;
        $intervals[] = [
            'start_elapsed_ms' => (int) $a['elapsed_ms'],
            'end_elapsed_ms' => (int) $b['elapsed_ms'],
            'moving_s' => $movingDelta,
            'elapsed_s' => $elapsedDelta,
            'distance_m' => $distanceDelta,
            'mid_distance_m' => is_numeric($a['distance_m'] ?? null) && is_numeric($b['distance_m'] ?? null) ? ((float) $a['distance_m'] + (float) $b['distance_m']) / 2.0 : null,
            'speed_mps' => $speed,
            'pace_s_per_km' => $speed !== null && $speed > 0 ? 1000.0 / $speed : null,
            'heart_rate_bpm' => is_numeric($a['heart_rate_bpm'] ?? null) && is_numeric($b['heart_rate_bpm'] ?? null) ? ((float) $a['heart_rate_bpm'] + (float) $b['heart_rate_bpm']) / 2.0 : null,
            'cadence' => is_numeric($a['cadence'] ?? null) && is_numeric($b['cadence'] ?? null) ? ((float) $a['cadence'] + (float) $b['cadence']) / 2.0 : null,
            'power_w' => is_numeric($a['power_w'] ?? null) && is_numeric($b['power_w'] ?? null) ? ((float) $a['power_w'] + (float) $b['power_w']) / 2.0 : null,
            'altitude_m' => is_numeric($a['altitude_m'] ?? null) && is_numeric($b['altitude_m'] ?? null) ? ((float) $a['altitude_m'] + (float) $b['altitude_m']) / 2.0 : null,
            'grade_pct' => is_numeric($a['grade_pct'] ?? null) && is_numeric($b['grade_pct'] ?? null) ? ((float) $a['grade_pct'] + (float) $b['grade_pct']) / 2.0 : null,
        ];
    }
    return $intervals;
}

function activityAnalysisDistanceTotal(array $samples): ?float
{
    $values = array_values(array_filter(array_map(static fn(array $sample): mixed => $sample['distance_m'] ?? null, $samples), 'is_numeric'));
    return $values === [] ? null : max(array_map('floatval', $values));
}

function activityAnalysisMovingTotal(array $samples): ?float
{
    if ($samples === []) return null;
    $last = end($samples);
    $moving = $last['moving_ms'] ?? $last['elapsed_ms'] ?? null;
    return is_numeric($moving) ? max(0.0, (float) $moving / 1000.0) : null;
}

function activityAnalysisWeightedAverage(array $intervals, string $field, ?callable $filter = null): ?float
{
    $sum = 0.0;
    $weight = 0.0;
    foreach ($intervals as $interval) {
        if ($filter !== null && !$filter($interval)) continue;
        if (!is_numeric($interval[$field] ?? null)) continue;
        $w = max(0.0, (float) ($interval['moving_s'] ?? 0));
        if ($w <= 0) continue;
        $sum += (float) $interval[$field] * $w;
        $weight += $w;
    }
    return $weight > 0 ? $sum / $weight : null;
}

function activityAnalysisPerformanceByDistance(array $samples, float $startDistance, float $endDistance, string $behavior): ?float
{
    $metrics = activityStreamSegmentMetrics($samples, $startDistance, $endDistance);
    if ($metrics === []) return null;
    return match ($behavior) {
        'pace' => $metrics['pace_s_per_km'],
        'speed' => $metrics['speed_kmh'],
        'pace_100m' => $metrics['distance_m'] > 0 && $metrics['moving_duration_s'] > 0 ? $metrics['moving_duration_s'] / ($metrics['distance_m'] / 100.0) : null,
        default => null,
    };
}

function activityAnalysisBehavior(array $owner): string
{
    return match ((string) ($owner['metrica_derivada'] ?? 'nenhuma')) {
        'pace_km' => 'pace',
        'velocidade_kmh' => 'speed',
        'pace_100m' => 'pace_100m',
        default => 'distance_time',
    };
}

function activityAnalysisPacing(array $owner, array $samples, array $intervals): ?array
{
    $behavior = activityAnalysisBehavior($owner);
    if (!in_array($behavior, ['pace', 'speed', 'pace_100m'], true)) return null;
    $distance = activityAnalysisDistanceTotal($samples);
    $moving = activityAnalysisMovingTotal($samples);
    if ($distance === null || $distance <= 0 || $moving === null || $moving <= 0) return null;
    $values = [];
    foreach ($intervals as $interval) {
        if (($interval['moving_s'] ?? 0) <= 0 || ($interval['distance_m'] ?? 0) <= 0) continue;
        $value = match ($behavior) {
            'pace' => $interval['pace_s_per_km'],
            'speed' => $interval['speed_mps'] !== null ? $interval['speed_mps'] * 3.6 : null,
            'pace_100m' => $interval['speed_mps'] !== null && $interval['speed_mps'] > 0 ? 100.0 / $interval['speed_mps'] : null,
        };
        if (is_numeric($value)) $values[] = (float) $value;
    }
    $stats = activityAnalysisStats($values);
    $average = match ($behavior) {
        'pace' => $moving / ($distance / 1000.0),
        'speed' => ($distance / 1000.0) / ($moving / 3600.0),
        'pace_100m' => $moving / ($distance / 100.0),
    };
    $half = $distance / 2.0;
    $firstHalf = activityAnalysisPerformanceByDistance($samples, 0.0, $half, $behavior);
    $secondHalf = activityAnalysisPerformanceByDistance($samples, $half, $distance, $behavior);
    $differencePercent = $firstHalf !== null && abs($firstHalf) > 1e-9 && $secondHalf !== null ? ($secondHalf - $firstHalf) / $firstHalf * 100.0 : null;
    $pattern = 'insufficient_data';
    if ($differencePercent !== null) {
        if (abs($differencePercent) <= ACTIVITY_ANALYSIS_EVEN_THRESHOLD_PERCENT) $pattern = 'even';
        elseif ($behavior === 'speed') $pattern = $differencePercent > 0 ? 'negative_split' : 'positive_split';
        else $pattern = $differencePercent < 0 ? 'negative_split' : 'positive_split';
    }
    $first10End = $distance * 0.1;
    $last10Start = $distance * 0.9;
    $previous20Start = $distance * 0.7;
    $first10 = activityAnalysisPerformanceByDistance($samples, 0.0, $first10End, $behavior);
    $middle = activityAnalysisPerformanceByDistance($samples, $first10End, $last10Start, $behavior);
    $last10 = activityAnalysisPerformanceByDistance($samples, $last10Start, $distance, $behavior);
    $previous20 = activityAnalysisPerformanceByDistance($samples, $previous20Start, $last10Start, $behavior);
    $finishDifferencePercent = $previous20 !== null && abs($previous20) > 1e-9 && $last10 !== null ? ($last10 - $previous20) / $previous20 * 100.0 : null;
    return [
        'behavior' => $behavior,
        'unit' => $behavior === 'speed' ? 'km_h' : ($behavior === 'pace_100m' ? 's_per_100m' : 's_per_km'),
        'average' => $average,
        'moving_average' => $average,
        'median' => $stats['median'] ?? null,
        'p10' => $stats['p10'] ?? null,
        'p50' => $stats['p50'] ?? null,
        'p90' => $stats['p90'] ?? null,
        'standard_deviation' => $stats['standard_deviation'] ?? null,
        'median_absolute_deviation' => $stats['median_absolute_deviation'] ?? null,
        'variability_percent' => $stats['coefficient_of_variation_percent'] ?? null,
        'first_half' => $firstHalf,
        'second_half' => $secondHalf,
        'difference' => $firstHalf !== null && $secondHalf !== null ? $secondHalf - $firstHalf : null,
        'difference_percent' => $differencePercent,
        'pattern' => $pattern,
        'pattern_even_threshold_percent' => ACTIVITY_ANALYSIS_EVEN_THRESHOLD_PERCENT,
        'start' => ['first_10_percent' => $first10],
        'middle' => ['middle_80_percent' => $middle],
        'finish' => ['last_10_percent' => $last10, 'previous_20_percent' => $previous20, 'difference_percent' => $finishDifferencePercent],
    ];
}

function activityAnalysisBestEfforts(array $samples, array $owner): array
{
    $distanceTotal = activityAnalysisDistanceTotal($samples);
    if ($distanceTotal === null || $distanceTotal <= 0) return [];
    $behavior = activityAnalysisBehavior($owner);
    $targets = ['400m' => 400.0, '500m' => 500.0, '1km' => 1000.0, '1mile' => 1609.344, '5km' => 5000.0];
    $efforts = [];
    foreach ($targets as $label => $target) {
        if ($distanceTotal + 0.5 < $target) continue;
        $best = null;
        foreach ($samples as $sample) {
            if (!is_numeric($sample['distance_m'] ?? null)) continue;
            $startDistance = (float) $sample['distance_m'];
            $endDistance = $startDistance + $target;
            if ($endDistance > $distanceTotal + 0.5) break;
            $end = activityStreamInterpolateAtDistance($samples, $endDistance);
            if ($end === null) continue;
            $startMoving = (float) ($sample['moving_ms'] ?? $sample['elapsed_ms']);
            $endMoving = (float) ($end['moving_ms'] ?? $end['elapsed_ms']);
            $durationS = ($endMoving - $startMoving) / 1000.0;
            if ($durationS <= 0) continue;
            if ($best === null || $durationS < $best['duration_s']) {
                $best = ['label' => $label, 'distance_m' => $target, 'duration_s' => $durationS, 'start_distance_m' => $startDistance, 'end_distance_m' => $endDistance, 'pace_s_per_km' => $durationS / ($target / 1000.0), 'speed_kmh' => ($target / 1000.0) / ($durationS / 3600.0)];
            }
        }
        if ($best !== null) $efforts[] = $best;
    }
    return $efforts;
}

function activityAnalysisHeartRate(PDO $pdo, string $userId, array $owner, array $samples, array $intervals): ?array
{
    $hrValues = array_values(array_filter(array_map(static fn(array $sample): mixed => $sample['heart_rate_bpm'] ?? null, $samples), 'is_numeric'));
    if ($hrValues === []) return null;
    $totalMoving = 0.0;
    $covered = 0.0;
    foreach ($intervals as $interval) {
        $moving = max(0.0, (float) ($interval['moving_s'] ?? 0));
        if ($moving <= 0) continue;
        $totalMoving += $moving;
        if (is_numeric($interval['heart_rate_bpm'] ?? null)) $covered += $moving;
    }
    $coverage = $totalMoving > 0 ? $covered / $totalMoving * 100.0 : 0.0;
    $average = activityAnalysisWeightedAverage($intervals, 'heart_rate_bpm');
    $distance = activityAnalysisDistanceTotal($samples);
    $half = $distance !== null ? $distance / 2.0 : null;
    $firstHalfHr = $half !== null ? activityAnalysisWeightedAverage($intervals, 'heart_rate_bpm', static fn(array $interval): bool => is_numeric($interval['mid_distance_m'] ?? null) && (float) $interval['mid_distance_m'] <= $half) : null;
    $secondHalfHr = $half !== null ? activityAnalysisWeightedAverage($intervals, 'heart_rate_bpm', static fn(array $interval): bool => is_numeric($interval['mid_distance_m'] ?? null) && (float) $interval['mid_distance_m'] > $half) : null;
    $profile = zoneProfileResolve($pdo, $userId, 'heart_rate', (string) $owner['idmodalidade']);
    $zones = $profile !== null ? zoneProfileDistribution($samples, $profile, 'heart_rate_bpm', 'time') : null;
    $decoupling = null;
    $movingTotal = activityAnalysisMovingTotal($samples) ?? 0.0;
    if ($coverage >= ACTIVITY_ANALYSIS_HR_COVERAGE_MIN_PERCENT && $movingTotal >= ACTIVITY_ANALYSIS_DECOUPLING_MIN_DURATION_S && $distance !== null && $distance >= ACTIVITY_ANALYSIS_DECOUPLING_MIN_DISTANCE_M && activityAnalysisBehavior($owner) === 'pace' && $half !== null) {
        $firstMetrics = activityStreamSegmentMetrics($samples, 0.0, $half);
        $secondMetrics = activityStreamSegmentMetrics($samples, $half, $distance);
        if ($firstMetrics !== [] && $secondMetrics !== [] && $firstHalfHr !== null && $secondHalfHr !== null && $firstHalfHr > 0 && $secondHalfHr > 0) {
            $firstSpeed = ($firstMetrics['distance_m'] ?? 0) > 0 && ($firstMetrics['moving_duration_s'] ?? 0) > 0 ? (float) $firstMetrics['distance_m'] / (float) $firstMetrics['moving_duration_s'] : null;
            $secondSpeed = ($secondMetrics['distance_m'] ?? 0) > 0 && ($secondMetrics['moving_duration_s'] ?? 0) > 0 ? (float) $secondMetrics['distance_m'] / (float) $secondMetrics['moving_duration_s'] : null;
            if ($firstSpeed !== null && $secondSpeed !== null && $firstSpeed > 0) {
                $firstEfficiency = $firstSpeed / $firstHalfHr;
                $secondEfficiency = $secondSpeed / $secondHalfHr;
                $decoupling = ['first_half_efficiency_mps_per_bpm' => $firstEfficiency, 'second_half_efficiency_mps_per_bpm' => $secondEfficiency, 'decoupling_percent' => ($firstEfficiency - $secondEfficiency) / $firstEfficiency * 100.0, 'method' => 'speed_mps_per_bpm_halves_by_distance', 'requirements' => ['coverage_percent_min' => ACTIVITY_ANALYSIS_HR_COVERAGE_MIN_PERCENT, 'duration_s_min' => ACTIVITY_ANALYSIS_DECOUPLING_MIN_DURATION_S, 'distance_m_min' => ACTIVITY_ANALYSIS_DECOUPLING_MIN_DISTANCE_M]];
            }
        }
    }
    return ['average_bpm' => $average, 'max_bpm' => max(array_map('floatval', $hrValues)), 'min_bpm' => min(array_map('floatval', $hrValues)), 'coverage_percent' => $coverage, 'observed_time_s' => $covered, 'first_half_bpm' => $firstHalfHr, 'second_half_bpm' => $secondHalfHr, 'trend_bpm' => $firstHalfHr !== null && $secondHalfHr !== null ? $secondHalfHr - $firstHalfHr : null, 'zones' => $zones, 'decoupling' => $decoupling];
}

function activityAnalysisPaceZones(PDO $pdo, string $userId, array $owner, array $samples): ?array
{
    if (activityAnalysisBehavior($owner) !== 'pace') return null;
    $profile = zoneProfileResolve($pdo, $userId, 'pace', (string) $owner['idmodalidade']);
    if ($profile === null) return null;
    $withPace = [];
    foreach ($samples as $sample) {
        $copy = $sample;
        $copy['pace_s_per_km'] = is_numeric($sample['speed_mps'] ?? null) && (float) $sample['speed_mps'] > 0 ? 1000.0 / (float) $sample['speed_mps'] : null;
        $withPace[] = $copy;
    }
    return zoneProfileDistribution($withPace, $profile, 'pace_s_per_km', 'time');
}

function activityAnalysisElevation(array $samples): ?array
{
    $values = array_values(array_filter(array_map(static fn(array $sample): mixed => $sample['altitude_m'] ?? null, $samples), 'is_numeric'));
    if ($values === []) return null;
    $smooth = [];
    foreach ($samples as $index => $sample) {
        if (!is_numeric($sample['altitude_m'] ?? null)) { $smooth[$index] = null; continue; }
        $window = [];
        for ($i = max(0, $index - 2); $i <= min(count($samples) - 1, $index + 2); $i++) if (is_numeric($samples[$i]['altitude_m'] ?? null)) $window[] = (float) $samples[$i]['altitude_m'];
        $smooth[$index] = activityStreamMedian($window);
    }
    $gain = 0.0;
    $loss = 0.0;
    $previous = null;
    foreach ($smooth as $value) {
        if ($value === null) continue;
        if ($previous !== null) {
            $delta = $value - $previous;
            if (abs($delta) >= 0.8) {
                if ($delta > 0) $gain += $delta; else $loss += abs($delta);
            }
        }
        $previous = $value;
    }
    $grades = array_values(array_filter(array_map(static fn(array $sample): mixed => $sample['grade_pct'] ?? null, $samples), 'is_numeric'));
    $gradeStats = activityAnalysisStats($grades);
    return ['min_m' => min(array_map('floatval', $values)), 'max_m' => max(array_map('floatval', $values)), 'gain_m' => $gain, 'loss_m' => $loss, 'grade' => $gradeStats !== null ? ['average_percent' => $gradeStats['mean'], 'p10_percent' => $gradeStats['p10'], 'p50_percent' => $gradeStats['p50'], 'p90_percent' => $gradeStats['p90'], 'window_min_distance_m' => 20, 'target_window_distance_m' => 30] : null, 'smoothing' => ['altitude_median_window_samples' => 5, 'noise_threshold_m' => 0.8]];
}

function activityAnalysisCadence(array $owner, array $samples, array $intervals): ?array
{
    $values = array_values(array_filter(array_map(static fn(array $sample): mixed => $sample['cadence'] ?? null, $samples), 'is_numeric'));
    if ($values === []) return null;
    $totalMoving = 0.0;
    $covered = 0.0;
    foreach ($intervals as $interval) {
        $moving = max(0.0, (float) ($interval['moving_s'] ?? 0));
        if ($moving <= 0 || $moving > 30) continue;
        $totalMoving += $moving;
        if (is_numeric($interval['cadence'] ?? null)) $covered += $moving;
    }
    $distance = activityAnalysisDistanceTotal($samples);
    $half = $distance !== null ? $distance / 2.0 : null;
    return ['unit' => activityStreamCadenceUnit((string) $owner['modalidade_slug'], (string) ($owner['familia_hub'] ?? '')), 'average' => activityAnalysisWeightedAverage($intervals, 'cadence'), 'max' => max(array_map('floatval', $values)), 'coverage_percent' => $totalMoving > 0 ? $covered / $totalMoving * 100.0 : 0.0, 'first_half' => $half !== null ? activityAnalysisWeightedAverage($intervals, 'cadence', static fn(array $interval): bool => is_numeric($interval['mid_distance_m'] ?? null) && (float) $interval['mid_distance_m'] <= $half) : null, 'second_half' => $half !== null ? activityAnalysisWeightedAverage($intervals, 'cadence', static fn(array $interval): bool => is_numeric($interval['mid_distance_m'] ?? null) && (float) $interval['mid_distance_m'] > $half) : null];
}

function activityAnalysisPower(array $samples, array $intervals): ?array
{
    $values = array_values(array_filter(array_map(static fn(array $sample): mixed => $sample['power_w'] ?? null, $samples), 'is_numeric'));
    if ($values === []) return null;
    return ['average_w' => activityAnalysisWeightedAverage($intervals, 'power_w'), 'max_w' => max(array_map('floatval', $values)), 'coverage_percent' => (function () use ($intervals): float { $total=0.0;$covered=0.0;foreach($intervals as $interval){$w=max(0.0,(float)($interval['moving_s']??0));if($w<=0||$w>30)continue;$total+=$w;if(is_numeric($interval['power_w']??null))$covered+=$w;}return $total>0?$covered/$total*100.0:0.0; })()];
}

function activityAnalysisRollingBestTime(array $samples, int $windowS, string $behavior): ?array
{
    if (count($samples) < 2) return null;
    $windowMs = $windowS * 1000;
    $best = null;
    $left = 0;
    for ($right = 0; $right < count($samples); $right++) {
        $rightMoving = (int) ($samples[$right]['moving_ms'] ?? $samples[$right]['elapsed_ms']);
        while ($left < $right && $rightMoving - (int) ($samples[$left]['moving_ms'] ?? $samples[$left]['elapsed_ms']) > $windowMs) $left++;
        if ($left >= $right) continue;
        $leftMoving = (int) ($samples[$left]['moving_ms'] ?? $samples[$left]['elapsed_ms']);
        $duration = ($rightMoving - $leftMoving) / 1000.0;
        if ($duration < $windowS * 0.9 || !is_numeric($samples[$left]['distance_m'] ?? null) || !is_numeric($samples[$right]['distance_m'] ?? null)) continue;
        $distance = (float) $samples[$right]['distance_m'] - (float) $samples[$left]['distance_m'];
        if ($distance <= 0) continue;
        $speed = $distance / $duration;
        $value = $behavior === 'speed' ? $speed * 3.6 : ($behavior === 'pace_100m' ? 100.0 / $speed : 1000.0 / $speed);
        $better = $best === null || ($behavior === 'speed' ? $value > $best['value'] : $value < $best['value']);
        if ($better) $best = ['window_s' => $windowS, 'value' => $value, 'distance_m' => $distance, 'start_elapsed_ms' => (int) $samples[$left]['elapsed_ms'], 'end_elapsed_ms' => (int) $samples[$right]['elapsed_ms']];
    }
    return $best;
}

function activityAnalysisFindings(?array $pacing, ?array $heartRate): array
{
    $findings = [];
    if ($pacing !== null && in_array($pacing['pattern'], ['negative_split','even','positive_split'], true)) {
        $findings[] = ['code' => $pacing['pattern'], 'analysis_version' => ACTIVITY_ANALYSIS_VERSION, 'formula' => 'second_half_vs_first_half_by_distance', 'thresholds' => ['even_percent' => ACTIVITY_ANALYSIS_EVEN_THRESHOLD_PERCENT], 'supporting_values' => ['first_half' => $pacing['first_half'], 'second_half' => $pacing['second_half'], 'difference_percent' => $pacing['difference_percent']]];
    }
    if ($pacing !== null && is_numeric($pacing['finish']['difference_percent'] ?? null)) {
        $difference = (float) $pacing['finish']['difference_percent'];
        $strong = $pacing['behavior'] === 'speed' ? $difference >= ACTIVITY_ANALYSIS_STRONG_FINISH_THRESHOLD_PERCENT : $difference <= -ACTIVITY_ANALYSIS_STRONG_FINISH_THRESHOLD_PERCENT;
        if ($strong) $findings[] = ['code' => 'strong_finish', 'analysis_version' => ACTIVITY_ANALYSIS_VERSION, 'formula' => 'last_10_percent_vs_previous_20_percent', 'thresholds' => ['improvement_percent' => ACTIVITY_ANALYSIS_STRONG_FINISH_THRESHOLD_PERCENT], 'supporting_values' => ['difference_percent' => $difference, 'last_10_percent' => $pacing['finish']['last_10_percent'], 'previous_20_percent' => $pacing['finish']['previous_20_percent']]];
    }
    if ($pacing !== null && is_numeric($pacing['variability_percent'] ?? null) && (float) $pacing['variability_percent'] >= ACTIVITY_ANALYSIS_VARIABILITY_HIGH_PERCENT) $findings[] = ['code' => 'pace_variability_high', 'analysis_version' => ACTIVITY_ANALYSIS_VERSION, 'formula' => 'standard_deviation_divided_by_mean', 'thresholds' => ['coefficient_of_variation_percent' => ACTIVITY_ANALYSIS_VARIABILITY_HIGH_PERCENT], 'supporting_values' => ['variability_percent' => $pacing['variability_percent']]];
    if ($heartRate !== null && is_numeric($heartRate['decoupling']['decoupling_percent'] ?? null) && (float) $heartRate['decoupling']['decoupling_percent'] >= ACTIVITY_ANALYSIS_DRIFT_FINDING_PERCENT) $findings[] = ['code' => 'heart_rate_drift_detected', 'analysis_version' => ACTIVITY_ANALYSIS_VERSION, 'formula' => 'change_in_speed_per_bpm_between_distance_halves', 'thresholds' => ['decoupling_percent' => ACTIVITY_ANALYSIS_DRIFT_FINDING_PERCENT, 'coverage_percent' => ACTIVITY_ANALYSIS_HR_COVERAGE_MIN_PERCENT], 'supporting_values' => ['decoupling_percent' => $heartRate['decoupling']['decoupling_percent'], 'coverage_percent' => $heartRate['coverage_percent']]];
    return $findings;
}

function activityAnalysisFingerprint(PDO $pdo, string $userId, string $activityId, array $owner): string
{
    $bundle = $pdo->prepare('SELECT payload_hash,data_atualizacao FROM activity_stream_bundles WHERE idregistro=:id LIMIT 1');
    $bundle->execute([':id' => $activityId]);
    $bundleRow = $bundle->fetch() ?: [];
    $zones = $pdo->prepare('SELECT idprofile,data_atualizacao FROM zone_profiles WHERE idusuario=:user AND is_default=TRUE AND (idmodalidade=:sport OR idmodalidade IS NULL) ORDER BY idprofile');
    $zones->execute([':user' => $userId, ':sport' => $owner['idmodalidade']]);
    return hash('sha256', json_encode(['version' => ACTIVITY_ANALYSIS_VERSION, 'activity' => $activityId, 'status' => $owner['status'], 'bundle' => $bundleRow, 'zones' => $zones->fetchAll()], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

function activityAnalysisCompute(PDO $pdo, string $userId, string $activityId, bool $force = false): array
{
    $owner = activityStreamOwner($pdo, $userId, $activityId);
    activityStreamEnsureMaterialized($pdo, $userId, $activityId);
    $stored = activityStreamRows($pdo, $userId, $activityId);
    $fingerprint = activityAnalysisFingerprint($pdo, $userId, $activityId, $owner);
    if (!$force) {
        $cache = $pdo->prepare('SELECT analysis_version,input_fingerprint,payload,data_calculo FROM activity_analysis_cache WHERE idregistro=:id LIMIT 1');
        $cache->execute([':id' => $activityId]);
        $row = $cache->fetch();
        if ($row && (int) $row['analysis_version'] === ACTIVITY_ANALYSIS_VERSION && hash_equals((string) $row['input_fingerprint'], $fingerprint)) {
            $payload = is_array($row['payload'] ?? null) ? $row['payload'] : json_decode((string) $row['payload'], true);
            if (is_array($payload)) { $payload['cache'] = ['hit' => true, 'computed_at' => activityStreamIso((string) $row['data_calculo'])]; return $payload; }
        }
    }
    $samples = $stored['samples'];
    $intervals = activityAnalysisIntervals($samples);
    $pacing = activityAnalysisPacing($owner, $samples, $intervals);
    $heartRate = activityAnalysisHeartRate($pdo, $userId, $owner, $samples, $intervals);
    $paceZones = activityAnalysisPaceZones($pdo, $userId, $owner, $samples);
    $elevation = activityAnalysisElevation($samples);
    $cadence = activityAnalysisCadence($owner, $samples, $intervals);
    $power = activityAnalysisPower($samples, $intervals);
    $behavior = activityAnalysisBehavior($owner);
    $rolling = [];
    if (in_array($behavior, ['pace','speed','pace_100m'], true)) {
        foreach ([30,60] as $window) {
            $best = activityAnalysisRollingBestTime($samples, $window, $behavior);
            if ($best !== null) $rolling[] = $best;
        }
    }
    $payload = [
        'activity_id' => $activityId,
        'analysis_version' => ACTIVITY_ANALYSIS_VERSION,
        'sport' => ['id' => (string) $owner['idmodalidade'], 'slug' => (string) $owner['modalidade_slug'], 'name' => (string) $owner['modalidade_nome'], 'family' => (string) ($owner['familia_hub'] ?? ''), 'derived_metric' => (string) ($owner['metrica_derivada'] ?? 'nenhuma')],
        'data_quality' => ['sample_count' => count($samples), 'available_streams' => $stored['bundle']['available_streams'] ?? [], 'has_streams' => $stored['bundle'] !== null && count($samples) > 0],
        'pacing' => $pacing,
        'rolling' => $rolling,
        'best_efforts' => activityAnalysisBestEfforts($samples, $owner),
        'heart_rate' => $heartRate,
        'pace_zones' => $paceZones,
        'elevation' => $elevation,
        'cadence' => $cadence,
        'power' => $power,
        'findings' => activityAnalysisFindings($pacing, $heartRate),
        'cache' => ['hit' => false, 'computed_at' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM)],
        'units' => ['pace' => $behavior === 'pace_100m' ? 's_per_100m' : 's_per_km', 'speed' => 'km_h', 'heart_rate' => 'bpm', 'elevation' => 'm', 'grade' => 'percent', 'cadence' => activityStreamCadenceUnit((string) $owner['modalidade_slug'], (string) ($owner['familia_hub'] ?? '')), 'power' => 'W'],
    ];
    $stmt = $pdo->prepare("INSERT INTO activity_analysis_cache (idregistro,analysis_version,input_fingerprint,payload,data_calculo) VALUES (:id,:version,:fingerprint,CAST(:payload AS jsonb),NOW()) ON CONFLICT (idregistro) DO UPDATE SET analysis_version=EXCLUDED.analysis_version,input_fingerprint=EXCLUDED.input_fingerprint,payload=EXCLUDED.payload,data_calculo=NOW()");
    $stmt->execute([':id' => $activityId, ':version' => ACTIVITY_ANALYSIS_VERSION, ':fingerprint' => $fingerprint, ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
    return $payload;
}
