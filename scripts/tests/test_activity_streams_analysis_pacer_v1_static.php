<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/src/function/activity_stream_service.php';
require_once $root . '/src/function/zone_profile_service.php';
require_once $root . '/src/function/activity_analysis_service.php';
require_once $root . '/src/function/pacer_service.php';

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($message);
};

$samples = [];
for ($i = 0; $i <= 120; $i++) {
    $distance = $i * 50.0;
    $elapsed = $i * 15000;
    $samples[] = [
        'elapsed_ms' => $elapsed,
        'moving_ms' => $elapsed,
        'distance_m' => $distance,
        'heart_rate_bpm' => 145 + (int) floor($i / 30),
        'altitude_m' => 400 + sin($i / 8) * 3 + ($i > 60 ? 4 : 0),
        'cadence' => 174 + ($i % 3),
    ];
}
$normalized = activityStreamNormalizeSamples($samples);
$assert(count($normalized) === 121, 'Normalização não pode perder samples válidos.');
$assert((float) $normalized[0]['speed_mps'] > 0, 'Primeiro sample deve derivar speed do primeiro intervalo futuro válido.');
$assert((float) $normalized[1]['speed_mps'] > 0, 'Speed canônico deve ser derivado de distance/moving time.');
$assert(in_array('pace', activityStreamAvailableStreams($normalized), true), 'Pace derivado deve aparecer como stream disponível.');
$assert(in_array('grade', activityStreamAvailableStreams($normalized), true), 'Grade derivado deve aparecer quando altitude/distância permitem.');
$gapStart = activityStreamNormalizeSamples([
    ['elapsed_ms' => 0, 'moving_ms' => 0, 'distance_m' => 0],
    ['elapsed_ms' => 60000, 'moving_ms' => 30000, 'distance_m' => 100, 'gap_before_ms' => 30000],
]);
$assert($gapStart[0]['speed_mps'] === null, 'Derivação futura não pode atravessar gap explícito para fabricar pace inicial.');
$assert(activityStreamEncodeJsonObject([]) === '{}', 'Metadata vazio precisa ser serializado como objeto JSON, não array.');
$metadataRejected = false;
try { activityStreamMetadataObject(['invalid-list'], 'metadata'); } catch (InvalidArgumentException) { $metadataRejected = true; }
$assert($metadataRejected, 'Metadata de streams/laps precisa respeitar contrato JSON object.');
$assert(activityStreamCadenceUnit('corrida', 'cardio') === 'spm', 'Corrida deve usar spm.');
$assert(activityStreamCadenceUnit('ciclismo', 'cardio') === 'rpm', 'Ciclismo deve usar rpm.');


$intervalSampling31 = activityAnalysisIntervals([
    ['elapsed_ms' => 0, 'moving_ms' => 0, 'distance_m' => 0, 'heart_rate_bpm' => 145],
    ['elapsed_ms' => 31000, 'moving_ms' => 31000, 'distance_m' => 100, 'heart_rate_bpm' => 146],
]);
$assert(count($intervalSampling31) === 1, 'Amostragem legítima de 31 s sem gap precisa continuar utilizável.');
$assert(abs((float) ($intervalSampling31[0]['pace_s_per_km'] ?? 0) - 310.0) < 0.01, '100 m em 31 s precisa resultar em 310 s/km, não ser tratado como gap.');

$intervalSampling60 = activityAnalysisIntervals([
    ['elapsed_ms' => 0, 'moving_ms' => 0, 'distance_m' => 0, 'heart_rate_bpm' => 145],
    ['elapsed_ms' => 60000, 'moving_ms' => 60000, 'distance_m' => 100, 'heart_rate_bpm' => 146],
]);
$assert(count($intervalSampling60) === 1, 'Amostragem espaçada de 60 s sem gap explícito precisa continuar utilizável.');

$intervalExplicitGap = activityAnalysisIntervals([
    ['elapsed_ms' => 0, 'moving_ms' => 0, 'distance_m' => 0, 'heart_rate_bpm' => 145],
    ['elapsed_ms' => 60000, 'moving_ms' => 60000, 'distance_m' => 100, 'heart_rate_bpm' => 146, 'gap_before_ms' => 60000],
]);
$assert($intervalExplicitGap === [], 'gap_before_ms no sample atual precisa impedir interpolação A → B.');

$down = activityStreamDownsample($normalized, 30, 'time', ['pace','heart_rate','altitude']);
$assert(count($down) <= 30, 'Downsampling deve respeitar max_points.');
$assert((int) $down[0]['elapsed_ms'] === 0, 'LTTB deve preservar o primeiro sample.');
$assert((int) end($down)['elapsed_ms'] === (int) end($normalized)['elapsed_ms'], 'LTTB deve preservar o último sample.');

$owner = ['metrica_derivada' => 'pace_km', 'modalidade_slug' => 'corrida', 'familia_hub' => 'cardio'];
$intervals = activityAnalysisIntervals($normalized);
$pacing = activityAnalysisPacing($owner, $normalized, $intervals);
$assert(is_array($pacing), 'Pace analysis deve existir para pace_km.');
$assert(($pacing['pattern'] ?? null) === 'even', 'Pace constante deve ser classificado como even.');
$assert(abs((float) $pacing['average'] - 300.0) < 0.01, 'Pace médio sintético deve ser 300 s/km.');
$assert((float) ($pacing['variability_percent'] ?? 100) < 1.0, 'Pace constante deve ter baixa variabilidade.');

$negative = [];
for ($i = 0; $i <= 100; $i++) {
    $distance = $i * 100.0;
    $first = $distance <= 5000;
    $movingS = $first ? $distance / 1000 * 330 : 1650 + (($distance - 5000) / 1000 * 300);
    $negative[] = ['elapsed_ms' => (int) round($movingS * 1000), 'moving_ms' => (int) round($movingS * 1000), 'distance_m' => $distance];
}
$negative = activityStreamNormalizeSamples($negative);
$negativePacing = activityAnalysisPacing($owner, $negative, activityAnalysisIntervals($negative));
$assert(($negativePacing['pattern'] ?? null) === 'negative_split', 'Segunda metade mais rápida deve ser negative_split.');
$assert((float) $negativePacing['difference_percent'] < -2.0, 'Negative split deve expor diferença percentual negativa para pace.');

$positive = [];
for ($i = 0; $i <= 100; $i++) {
    $distance = $i * 100.0;
    $first = $distance <= 5000;
    $movingS = $first ? $distance / 1000 * 300 : 1500 + (($distance - 5000) / 1000 * 330);
    $positive[] = ['elapsed_ms' => (int) round($movingS * 1000), 'moving_ms' => (int) round($movingS * 1000), 'distance_m' => $distance];
}
$positive = activityStreamNormalizeSamples($positive);
$positivePacing = activityAnalysisPacing($owner, $positive, activityAnalysisIntervals($positive));
$assert(($positivePacing['pattern'] ?? null) === 'positive_split', 'Segunda metade mais lenta deve ser positive_split.');

$segments = pacerGeneratedSegments(10000, 3120, 'negative_split', 10, ['progression_percent' => 8, 'segment_distance_m' => 2000]);
$assert(count($segments) === 5, 'Negative split de 10 km/2 km deve gerar cinco segmentos.');
$total = 0.0;
foreach ($segments as $segment) $total += (($segment['end_distance_m'] - $segment['start_distance_m']) / 1000.0) * $segment['target_pace_s_per_km'];
$assert(abs($total - 3120.0) < 0.01, 'Plano gerado deve fechar exatamente o target_time.');
$assert((float) $segments[0]['target_pace_s_per_km'] > (float) end($segments)['target_pace_s_per_km'], 'Negative split deve acelerar ao longo do plano.');

$plan = [
    'target_distance_m' => 5000.0,
    'target_time_s' => 1500.0,
    'target_average_pace_s_per_km' => 300.0,
    'default_tolerance_s_per_km' => 10.0,
    'guidance_rules' => pacerDefaultRules(),
    'segments' => pacerGeneratedSegments(5000, 1500, 'even', 10),
];
$inside = pacerEvaluate($plan, ['distance_m' => 1000, 'elapsed_s' => 300, 'moving_time_s' => 300, 'recent_pace_s_per_km' => 304, 'average_pace_s_per_km' => 300]);
$assert($inside['code'] === 'on_target', 'Dentro da tolerância deve permanecer on_target.');
$assert(abs((float) $inside['ahead_behind_s']) < 0.001, 'No alvo deve ter ahead/behind zero.');

$slow1 = pacerEvaluate($plan, ['distance_m' => 1000, 'elapsed_s' => 320, 'moving_time_s' => 320, 'recent_pace_s_per_km' => 330, 'average_pace_s_per_km' => 320]);
$assert($slow1['code'] === 'on_target', 'Persistence deve impedir correção instantânea.');
$slow2 = pacerEvaluate($plan, ['distance_m' => 1100, 'elapsed_s' => 353, 'moving_time_s' => 338, 'recent_pace_s_per_km' => 330, 'average_pace_s_per_km' => 321, 'guidance_state' => $slow1['next_state']]);
$assert($slow2['code'] === 'speed_up', 'Pace persistentemente lento deve produzir speed_up.');
$assert((float) $slow2['ahead_behind_s'] > 0, 'Estado lento deve estar atrasado.');

$fast1 = pacerEvaluate($plan, ['distance_m' => 1000, 'elapsed_s' => 280, 'moving_time_s' => 280, 'recent_pace_s_per_km' => 270, 'average_pace_s_per_km' => 280]);
$fast2 = pacerEvaluate($plan, ['distance_m' => 1100, 'elapsed_s' => 307, 'moving_time_s' => 297, 'recent_pace_s_per_km' => 270, 'average_pace_s_per_km' => 279, 'guidance_state' => $fast1['next_state']]);
$assert($fast2['code'] === 'slow_down', 'Pace persistentemente rápido deve produzir slow_down.');
$assert((float) $fast2['ahead_behind_s'] < 0, 'Estado rápido deve estar adiantado.');

$hysteresis = pacerEvaluate($plan, ['distance_m' => 1200, 'elapsed_s' => 336, 'moving_time_s' => 317, 'recent_pace_s_per_km' => 311, 'average_pace_s_per_km' => 280, 'guidance_state' => $fast2['next_state']]);
$assert($hysteresis['code'] === 'on_target', 'Hysteresis deve impedir inversão imediata ao cruzar pouco a banda.');

$hrPlan = $plan;
$hrPlan['segments'][0]['heart_rate_ceiling_bpm'] = 165;
$hr1 = pacerEvaluate($hrPlan, ['distance_m' => 1000, 'elapsed_s' => 300, 'moving_time_s' => 300, 'recent_pace_s_per_km' => 300, 'heart_rate_bpm' => 170]);
$hr2 = pacerEvaluate($hrPlan, ['distance_m' => 1100, 'elapsed_s' => 330, 'moving_time_s' => 316, 'recent_pace_s_per_km' => 300, 'heart_rate_bpm' => 170, 'guidance_state' => $hr1['next_state']]);
$assert($hr2['code'] === 'hr_limit', 'HR acima do ceiling de forma persistente deve produzir hr_limit.');
$missingHr = pacerEvaluate($hrPlan, ['distance_m' => 1200, 'elapsed_s' => 360, 'moving_time_s' => 360, 'recent_pace_s_per_km' => 300]);
$assert(($missingHr['heart_rate_constraint']['status'] ?? null) === 'unavailable', 'Sensor de HR ausente deve marcar constraint unavailable sem interromper pace.');

$finalPlan = $plan;
$finalPlan['guidance_rules']['final_push'] = ['enabled' => true, 'remaining_distance_m' => 1000.0, 'max_behind_s' => 5.0];
$final = pacerEvaluate($finalPlan, ['distance_m' => 4200, 'elapsed_s' => 1260, 'moving_time_s' => 1260, 'recent_pace_s_per_km' => 300]);
$assert(in_array($final['code'], ['final_phase_available','final_push','final_kick'], true), 'Fase final configurada deve aparecer somente na janela final e sem atraso excessivo.');
$assert(abs((float) $final['projected_finish']['if_plan_followed_s'] - 1500.0) < 0.01, 'Projected finish seguindo o plano deve ser matematicamente consistente.');

$gapRejected = false;
try {
    pacerValidateSegments([
        ['start_distance_m' => 0, 'end_distance_m' => 2000, 'target_pace_s_per_km' => 300],
        ['start_distance_m' => 2100, 'end_distance_m' => 5000, 'target_pace_s_per_km' => 300],
    ], 5000, 10);
} catch (InvalidArgumentException) {
    $gapRejected = true;
}
$assert($gapRejected, 'Custom plan com gap deve ser rejeitado.');

$router = file_get_contents($root . '/public/api/v1/index.php');
$migration = file_get_contents($root . '/src/database/migrations/20260915_activity_streams_analysis_pacer_v1.sql');
foreach (['streams','splits','laps','analysis','zone-profiles','pacer-plans'] as $term) $assert(str_contains($router, $term), "Router sem {$term}.");
foreach (['activity_stream_bundles','activity_stream_samples','activity_laps','activity_analysis_cache','zone_profiles','pacer_plans','pacer_plan_segments'] as $table) $assert(str_contains($migration, $table), "Migration sem {$table}.");
$assert(!str_contains($migration, 'latitude') && !str_contains($migration, 'longitude'), 'Stream storage não deve duplicar latitude/longitude.');
$assert(str_contains(file_get_contents($root . '/src/function/activity_analysis_service.php'), 'ACTIVITY_ANALYSIS_VERSION = 1'), 'Analysis version precisa ser explícita.');
$assert(str_contains(file_get_contents($root . '/src/function/pacer_service.php'), "':metadata' => activityStreamEncodeJsonObject(\$segment['instruction_metadata'])"), 'Pacer precisa persistir instruction_metadata usando o helper canônico de JSON object.');
$assert(str_contains(file_get_contents($root . '/src/function/zone_profile_service.php'), "'heart_rate'") && !str_contains(file_get_contents($root . '/src/function/zone_profile_service.php'), '220 -'), 'Zones devem ser manuais, sem 220-idade.');
$assert(str_contains(file_get_contents($root . '/src/function/api_workouts.php'), 'pacer_plan_id'), 'Workout API precisa aceitar Pacer Plan.');
$assert(str_contains(file_get_contents($root . '/src/function/cronograma.php'), 'idpacerplan'), 'Split future do cronograma deve preservar Pacer Plan.');
$assert(str_contains(file_get_contents($root . '/src/function/account_data.php'), 'stream_samples') && str_contains(file_get_contents($root . '/src/function/account_data.php'), 'pacer_plans'), 'Account export precisa incluir Streams e Pacer.');
$assert(str_contains(file_get_contents($root . '/src/function/activity_file_exchange.php'), 'activityStreamEnsureMaterialized'), 'Importação rica precisa promover Streams canônicos.');

printf("✓ Activity Streams + Analysis + Pacer v1 static: %d assertions\n", $checks);
