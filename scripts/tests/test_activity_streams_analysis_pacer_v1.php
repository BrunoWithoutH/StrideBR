<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/function/api_v1.php';

return function (PDO $pdo): void {
    $owner = alphaTestUser($pdo, 'streams-pacer-owner');
    $other = alphaTestUser($pdo, 'streams-pacer-other');
    $today = new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'));

    $create = static function (PDO $pdo, string $userId, string $slug, string $title, array $extra = []): array {
        $started = new DateTimeImmutable('2026-09-10T07:00:00-03:00');
        $payload = [
            'sport' => $slug,
            'title' => $title,
            'visibility' => 'privado',
            'started_at' => $started->format(DateTimeInterface::ATOM),
            'ended_at' => $started->modify('+40 minutes')->format(DateTimeInterface::ATOM),
            'metrics' => ['distance_m' => 5400, 'duration_s' => 1680, 'elevation_gain_m' => 60],
            'gps' => ['points' => [['lat' => -27.35, 'lon' => -53.39], ['lat' => -27.351, 'lon' => -53.388]]],
        ];
        return stridebr_api_create_activity($pdo, $userId, array_replace_recursive($payload, $extra), 'streams-' . sha1($title));
    };

    $noStream = $create($pdo, $owner, 'corrida', 'Sem stream');
    $capabilities = activityStreamCapabilities($pdo, $owner, (string) $noStream['id']);
    AlphaTest::same(false, $capabilities['has_streams'], 'Activity antiga/simples precisa continuar válida sem streams');
    $emptyAnalysis = activityAnalysisCompute($pdo, $owner, (string) $noStream['id']);
    AlphaTest::same(false, $emptyAnalysis['data_quality']['has_streams'], 'Analysis sem stream deve funcionar parcialmente');
    AlphaTest::same(null, $emptyAnalysis['pacing'], 'Activity sem stream não pode fabricar pace analysis');

    $samples = [];
    $movingMs = 0;
    $elapsedMs = 0;
    for ($i = 0; $i <= 54; $i++) {
        if ($i > 0) {
            $pace = $i <= 27 ? 310.0 : ($i >= 50 ? 280.0 : 290.0);
            $movingMs += (int) round(($pace / 10.0) * 1000);
            $elapsedMs = $movingMs + ($i >= 25 ? 60000 : 0);
        }
        $samples[] = [
            'elapsed_ms' => $elapsedMs,
            'moving_ms' => $movingMs,
            'distance_m' => $i * 100.0,
            'heart_rate_bpm' => 145 + (int) floor($i / 5),
            'altitude_m' => 420 + ($i * 0.7) + (($i % 2) ? 0.25 : -0.25),
            'cadence' => 172 + ($i % 4),
            'horizontal_accuracy_m' => 4.5,
            'gap_before_ms' => $i === 25 ? 60000 : null,
            'source' => 'wear_os',
        ];
    }
    $bundle = ['schema_version' => 1, 'source' => 'stridebr_android', 'source_metadata' => ['device' => 'alpha'], 'samples' => $samples];
    $activity = $create($pdo, $owner, 'corrida', 'Corrida streams', ['streams' => $bundle]);
    $activityId = (string) $activity['id'];

    $detail = stridebr_api_activity_detail($pdo, $activityId, $owner);
    AlphaTest::same(true, $detail['stream_capabilities']['has_streams'], 'Activity Detail deve anunciar streams sem embutir samples');
    AlphaTest::assert(in_array('heart_rate', $detail['stream_capabilities']['available_streams'], true), 'Capabilities deve anunciar HR');
    AlphaTest::assert(in_array('cadence', $detail['stream_capabilities']['available_streams'], true), 'Capabilities deve anunciar cadence');

    $raw = activityStreamRead($pdo, $owner, $activityId, ['axis' => 'time', 'resolution' => 'raw']);
    AlphaTest::same(55, count($raw['samples']), 'Raw deve devolver todos os samples do owner');
    AlphaTest::same('time', $raw['axis'], 'Time axis deve ser suportado');
    AlphaTest::assert(count($raw['gaps']) >= 1, 'Gap explícito precisa sobreviver à ingestão');
    $distanceView = activityStreamRead($pdo, $owner, $activityId, ['axis' => 'distance', 'resolution' => 'low', 'max_points' => 50, 'streams' => 'pace,heart_rate,altitude,cadence']);
    AlphaTest::same('distance', $distanceView['axis'], 'Distance axis deve ser suportado');
    AlphaTest::assert(count($distanceView['samples']) <= 50, 'Downsampling deve respeitar max_points');
    AlphaTest::assert(isset($distanceView['samples'][0]['pace_s_per_km']), 'Samples alinhados devem oferecer pace derivado no mesmo ponto');

    $same = activityStreamSaveBundle($pdo, $owner, $activityId, $bundle, 'alpha-retry-1', 'stridebr_android');
    AlphaTest::same(false, $same['reused'], 'Primeira gravação com nova key deve substituir sem duplicar bundle');
    $retry = activityStreamSaveBundle($pdo, $owner, $activityId, $bundle, 'alpha-retry-1', 'stridebr_android');
    AlphaTest::same(true, $retry['reused'], 'Retry idempotente precisa reutilizar o mesmo bundle');
    AlphaTest::throws(static function () use ($pdo, $owner, $activityId, $bundle): void {
        $changed = $bundle;
        $changed['samples'][10]['heart_rate_bpm'] = 200;
        activityStreamSaveBundle($pdo, $owner, $activityId, $changed, 'alpha-retry-1', 'stridebr_android');
    }, 'Mesma Idempotency-Key com payload diferente deve conflitar');
    AlphaTest::throws(static fn() => activityStreamRead($pdo, $other, $activityId, []), 'Outro usuário não pode ler streams');

    $splits = activityStreamSplits($pdo, $owner, $activityId, 1000);
    AlphaTest::same(6, count($splits['data']), '5,4 km deve gerar cinco splits completos e um parcial');
    AlphaTest::same(true, $splits['data'][5]['partial'], 'Último split de 400 m precisa ser parcial');
    AlphaTest::assert($splits['data'][0]['heart_rate_avg_bpm'] !== null, 'Split deve agregar HR quando disponível');
    AlphaTest::assert($splits['data'][0]['elevation_gain_m'] !== null, 'Split deve agregar elevação quando disponível');

    $laps = activityStreamSaveLaps($pdo, $owner, $activityId, [
        ['start_elapsed_ms' => 0, 'end_elapsed_ms' => $samples[20]['elapsed_ms'], 'start_moving_ms' => 0, 'end_moving_ms' => $samples[20]['moving_ms'], 'start_distance_m' => 0, 'end_distance_m' => 2000],
        ['start_elapsed_ms' => $samples[20]['elapsed_ms'], 'end_elapsed_ms' => $samples[40]['elapsed_ms'], 'start_moving_ms' => $samples[20]['moving_ms'], 'end_moving_ms' => $samples[40]['moving_ms'], 'start_distance_m' => 2000, 'end_distance_m' => 4000],
    ], 'manual', 'stridebr_android');
    AlphaTest::same(2, count($laps['data']), 'Manual laps devem persistir separadamente');
    AlphaTest::same('manual', $laps['data'][0]['origin'], 'Lap manual precisa manter origin=manual');
    AlphaTest::same(6, count(activityStreamSplits($pdo, $owner, $activityId, 1000)['data']), 'Salvar laps não pode alterar splits automáticos');

    zoneProfileSave($pdo, $owner, [
        'profile_type' => 'heart_rate', 'name' => 'FC manual', 'sport' => 'corrida', 'is_default' => true,
        'zones' => [
            ['code' => 'Z1', 'min' => null, 'max' => 140],
            ['code' => 'Z2', 'min' => 140, 'max' => 150],
            ['code' => 'Z3', 'min' => 150, 'max' => 160],
            ['code' => 'Z4', 'min' => 160, 'max' => 170],
            ['code' => 'Z5', 'min' => 170, 'max' => null],
        ],
    ]);
    zoneProfileSave($pdo, $owner, [
        'profile_type' => 'pace', 'name' => 'Pace manual', 'sport' => 'corrida', 'is_default' => true,
        'zones' => [
            ['code' => 'Z1', 'min' => null, 'max' => 280],
            ['code' => 'Z2', 'min' => 280, 'max' => 300],
            ['code' => 'Z3', 'min' => 300, 'max' => 320],
            ['code' => 'Z4', 'min' => 320, 'max' => 360],
            ['code' => 'Z5', 'min' => 360, 'max' => null],
        ],
    ]);

    $analysis = activityAnalysisCompute($pdo, $owner, $activityId, true);
    AlphaTest::same(1, $analysis['analysis_version'], 'Activity Analysis precisa ser versionada');
    AlphaTest::same('negative_split', $analysis['pacing']['pattern'], 'Série sintética deve detectar negative split');
    AlphaTest::assert((float) $analysis['pacing']['finish']['difference_percent'] < 0, 'Final mais rápido deve aparecer objetivamente');
    AlphaTest::assert((float) $analysis['heart_rate']['coverage_percent'] >= 80, 'HR coverage deve ser calculada');
    AlphaTest::assert(is_array($analysis['heart_rate']['zones']), 'HR zones manuais devem alimentar a análise');
    AlphaTest::assert(is_array($analysis['pace_zones']), 'Pace zones manuais devem alimentar a análise');
    AlphaTest::assert(
        is_array($analysis['heart_rate']['decoupling']),
        'Decoupling deve existir com cobertura/duração/distância suficientes'
    );
    AlphaTest::assert(
        is_numeric($analysis['heart_rate']['decoupling']['decoupling_percent'] ?? null),
        'Decoupling percent deve ser numérico quando calculável'
    );
    AlphaTest::assert($analysis['elevation']['gain_m'] > 0, 'Elevation analysis deve filtrar e agregar subida');
    AlphaTest::same('spm', $analysis['cadence']['unit'], 'Cadência de corrida deve ser spm');
    AlphaTest::assert(count($analysis['best_efforts']) >= 3, 'Best efforts locais devem existir sem virar PB global');
    $cached = activityAnalysisCompute($pdo, $owner, $activityId, false);
    AlphaTest::same(true, $cached['cache']['hit'], 'Analysis recompute idempotente deve usar cache quando fingerprint não muda');

    $shortActivity = $create($pdo, $owner, 'corrida', 'HR coverage baixa');
    activityStreamSaveBundle($pdo, $owner, (string) $shortActivity['id'], ['schema_version' => 1, 'samples' => [
        ['elapsed_ms' => 0, 'moving_ms' => 0, 'distance_m' => 0, 'heart_rate_bpm' => 150],
        ['elapsed_ms' => 600000, 'moving_ms' => 600000, 'distance_m' => 2000],
    ]], 'low-coverage', 'import');
    $lowCoverage = activityAnalysisCompute($pdo, $owner, (string) $shortActivity['id'], true);
    AlphaTest::same(
        null,
        $lowCoverage['heart_rate']['decoupling'],
        'Coverage/duração insuficientes devem desabilitar decoupling'
    );

    $oneHalfSamples = [];
    $oneHalfMovingMs = 0;
    for ($i = 0; $i <= 50; $i++) {
        if ($i > 0) {
            $pace = $i <= 25 ? 600.0 : 100.0;
            $oneHalfMovingMs += (int) round(($pace / 10.0) * 1000);
        }
        $sample = ['elapsed_ms' => $oneHalfMovingMs, 'moving_ms' => $oneHalfMovingMs, 'distance_m' => $i * 100.0];
        if ($i <= 25) $sample['heart_rate_bpm'] = 150;
        $oneHalfSamples[] = $sample;
    }
    $oneHalfActivity = $create($pdo, $owner, 'corrida', 'HR só primeira metade', ['metrics' => ['distance_m' => 5000, 'duration_s' => 1750, 'elevation_gain_m' => 0]]);
    activityStreamSaveBundle($pdo, $owner, (string) $oneHalfActivity['id'], ['schema_version' => 1, 'samples' => $oneHalfSamples], 'one-half-hr', 'import');
    $oneHalfAnalysis = activityAnalysisCompute($pdo, $owner, (string) $oneHalfActivity['id'], true);
    AlphaTest::assert((float) ($oneHalfAnalysis['heart_rate']['coverage_percent'] ?? 0) >= 80.0, 'Fixture de uma metade deve manter coverage global suficiente.');
    AlphaTest::same(null, $oneHalfAnalysis['heart_rate']['second_half_bpm'], 'Dados de HR somente na primeira metade não podem fabricar HR na segunda metade.');
    AlphaTest::same(null, $oneHalfAnalysis['heart_rate']['decoupling'], 'Decoupling exige dados válidos nas duas metades.');

    $cycling = $create($pdo, $owner, 'ciclismo', 'Bike cadence', ['metrics' => ['distance_m' => 10000, 'duration_s' => 1200, 'elevation_gain_m' => 20]]);
    activityStreamSaveBundle($pdo, $owner, (string) $cycling['id'], ['schema_version' => 1, 'samples' => [
        ['elapsed_ms' => 0, 'moving_ms' => 0, 'distance_m' => 0, 'cadence' => 80],
        ['elapsed_ms' => 600000, 'moving_ms' => 600000, 'distance_m' => 5000, 'cadence' => 85],
        ['elapsed_ms' => 1200000, 'moving_ms' => 1200000, 'distance_m' => 10000, 'cadence' => 90],
    ]], 'bike-stream', 'stridebr_android');
    $bikeAnalysis = activityAnalysisCompute($pdo, $owner, (string) $cycling['id'], true);
    AlphaTest::same('speed', $bikeAnalysis['pacing']['behavior'], 'Ciclismo deve analisar speed, não min/km');
    AlphaTest::same('rpm', $bikeAnalysis['cadence']['unit'], 'Cadência de ciclismo deve ser rpm');

    AlphaTest::same([], pacerPlanList($pdo, $owner, ['status' => 'active']), 'Pacer novo precisa iniciar com biblioteca vazia.');

    $plan = pacerPlanSave($pdo, $owner, [
        'name' => '5 km 25 min', 'sport' => 'corrida', 'strategy' => 'even', 'target_distance_m' => 5000, 'target_time_s' => 1500,
        'tolerance_s_per_km' => 10,
    ]);
    AlphaTest::same(300.0, (float) $plan['target_average_pace_s_per_km'], 'Even 5 km/25:00 deve ter alvo de 300 s/km');
    AlphaTest::same([], $plan['segments'][0]['instruction_metadata'], 'Even plan sem metadata deve continuar expondo array vazio no contrato PHP.');
    $evenMetadataType = $pdo->prepare('SELECT jsonb_typeof(instruction_metadata) FROM pacer_plan_segments WHERE idplan=:id ORDER BY segment_order LIMIT 1');
    $evenMetadataType->execute([':id' => (string) $plan['id']]);
    AlphaTest::same('object', (string) $evenMetadataType->fetchColumn(), 'Even plan precisa persistir instruction_metadata como JSON object.');
    $negativePlan = pacerPlanSave($pdo, $owner, [
        'name' => '10 km negative', 'sport' => 'corrida', 'strategy' => 'negative_split', 'target_distance_m' => 10000, 'target_time_s' => 3120,
        'constraints' => ['progression_percent' => 8, 'segment_distance_m' => 2000],
    ]);
    AlphaTest::assert((float) $negativePlan['segments'][0]['target_pace_s_per_km'] > (float) end($negativePlan['segments'])['target_pace_s_per_km'], 'Negative split plan deve acelerar por segmentos');
    AlphaTest::assert(isset($negativePlan['segments'][0]['instruction_metadata']['generated_progression_percent']), 'Negative split deve preservar instruction metadata não vazio.');
    $negativeMetadataType = $pdo->prepare('SELECT jsonb_typeof(instruction_metadata) FROM pacer_plan_segments WHERE idplan=:id ORDER BY segment_order LIMIT 1');
    $negativeMetadataType->execute([':id' => (string) $negativePlan['id']]);
    AlphaTest::same('object', (string) $negativeMetadataType->fetchColumn(), 'Negative split também precisa persistir instruction_metadata como JSON object.');


    $customPlan = pacerPlanSave($pdo, $owner, [
        'name' => '2 km custom', 'sport' => 'corrida', 'strategy' => 'custom', 'target_distance_m' => 2000, 'target_time_s' => 620,
        'tolerance_s_per_km' => 8,
        'segments' => [
            ['basis' => 'distance', 'start_distance_m' => 0, 'end_distance_m' => 1000, 'target_pace_s_per_km' => 315, 'tolerance_s_per_km' => 8],
            ['basis' => 'distance', 'start_distance_m' => 1000, 'end_distance_m' => 2000, 'target_pace_s_per_km' => 305, 'tolerance_s_per_km' => 8],
        ],
    ]);
    AlphaTest::same('custom', (string) $customPlan['strategy'], 'Pacer custom precisa salvar segmentos explícitos.');
    AlphaTest::same(2, count($customPlan['segments']), 'Pacer custom precisa preservar dois segmentos.');
    $copyPlan = pacerPlanSave($pdo, $owner, [
        'name' => $plan['name'] . ' (cópia)', 'sport' => $plan['sport']['id'], 'strategy' => 'custom',
        'target_distance_m' => $plan['target_distance_m'], 'target_time_s' => $plan['target_time_s'],
        'tolerance_s_per_km' => $plan['default_tolerance_s_per_km'], 'segments' => $plan['segments'],
    ]);
    AlphaTest::assert((string) $copyPlan['id'] !== (string) $plan['id'], 'Duplicar Pacer precisa criar novo ID.');
    pacerPlanArchive($pdo, $owner, (string) $customPlan['id']);
    AlphaTest::same('archived', (string) pacerPlanGet($pdo, $owner, (string) $customPlan['id'])['status'], 'Arquivar Pacer precisa preservar plano como read-only histórico.');
    AlphaTest::assert((bool) array_filter(pacerPlanList($pdo, $owner, ['status' => 'archived']), static fn(array $row): bool => (string) $row['id'] === (string) $customPlan['id']), 'Biblioteca arquivada precisa listar o plano arquivado.');
    $eval1 = pacerPlanEvaluate($pdo, $owner, (string) $plan['id'], ['distance_m' => 1000, 'elapsed_s' => 320, 'moving_time_s' => 320, 'recent_pace_s_per_km' => 330]);
    $eval2 = pacerPlanEvaluate($pdo, $owner, (string) $plan['id'], ['distance_m' => 1100, 'elapsed_s' => 353, 'moving_time_s' => 338, 'recent_pace_s_per_km' => 330, 'guidance_state' => $eval1['next_state']]);
    AlphaTest::same('speed_up', $eval2['code'], 'Reference evaluator deve aplicar persistence e pedir speed_up');
    AlphaTest::assert((float) $eval2['projected_finish']['at_current_average_s'] > 1500, 'Projected finish deve refletir ritmo atual mais lento');
    AlphaTest::same([], pacerPlanGet($pdo, $other, (string) $plan['id']), 'Pacer Plan deve ser owner-scoped');

    $workout = stridebr_api_workout_create($pdo, $owner, [
        'title' => 'Corrida com Pacer', 'sport' => 'corrida', 'date' => $today->modify('+2 days')->format('Y-m-d'), 'time' => '07:00',
        'planned_duration_s' => 1500, 'planned_distance_m' => 5000, 'pacer_plan_id' => (string) $plan['id'],
    ]);
    AlphaTest::same((string) $plan['id'], (string) ($workout['pacer_plan']['id'] ?? ''), 'Workout deve referenciar Pacer Plan compatível');
    AlphaTest::throws(static fn() => stridebr_api_workout_create($pdo, $owner, [
        'title' => 'Bike com Pacer errado', 'sport' => 'ciclismo', 'date' => $today->modify('+3 days')->format('Y-m-d'), 'pacer_plan_id' => (string) $plan['id'],
    ]), 'Workout deve rejeitar Pacer Plan de modalidade diferente');

    $pdo->prepare('UPDATE registros_atividade SET excluido_em=NOW() WHERE idregistro=:id')->execute([':id' => $activityId]);
    AlphaTest::throws(static fn() => activityStreamRead($pdo, $owner, $activityId, []), 'Soft-delete deve esconder streams junto com a Activity');
    $pdo->prepare('UPDATE registros_atividade SET excluido_em=NULL WHERE idregistro=:id')->execute([':id' => $activityId]);
    AlphaTest::same(55, count(activityStreamRead($pdo, $owner, $activityId, ['resolution' => 'raw'])['samples']), 'Restore deve recuperar streams preservados');

    $deleteId = (string) $shortActivity['id'];
    $pdo->prepare('DELETE FROM registros_atividade WHERE idregistro=:id AND idusuario=:user')->execute([':id' => $deleteId, ':user' => $owner]);
    $bundleCount = $pdo->prepare('SELECT COUNT(*) FROM activity_stream_bundles WHERE idregistro=:id');
    $bundleCount->execute([':id' => $deleteId]);
    AlphaTest::same(0, (int) $bundleCount->fetchColumn(), 'Hard-delete deve remover stream bundle por cascade');
    $analysisCount = $pdo->prepare('SELECT COUNT(*) FROM activity_analysis_cache WHERE idregistro=:id');
    $analysisCount->execute([':id' => $deleteId]);
    AlphaTest::same(0, (int) $analysisCount->fetchColumn(), 'Hard-delete deve remover analysis cache por cascade');
};
