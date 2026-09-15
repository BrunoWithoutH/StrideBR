<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/function/api_v1.php';

return function (PDO $pdo): void {
    $owner = alphaTestUser($pdo, 'progress-mobile-owner');
    $empty = alphaTestUser($pdo, 'progress-mobile-empty');
    $other = alphaTestUser($pdo, 'progress-mobile-other');
    $single = alphaTestUser($pdo, 'progress-mobile-single');
    $boundary = alphaTestUser($pdo, 'progress-mobile-boundary');
    $tz = new DateTimeZone('America/Sao_Paulo');
    $today = new DateTimeImmutable('today', $tz);
    $from = $today->modify('-20 days')->format('Y-m-d');
    $to = $today->modify('+10 days')->format('Y-m-d');
    $currentDate = $today->modify('-2 days')->format('Y-m-d');
    $previousFrom = $today->modify('-51 days')->format('Y-m-d');
    $previousTo = $today->modify('-21 days')->format('Y-m-d');

    $emptyOverview = stridebr_api_progress_overview($pdo, $empty, ['from' => $from, 'to' => $to]);
    AlphaTest::same(0, (int) $emptyOverview['summary']['activities_count'], 'Usuário vazio deve ter zero Activities');
    AlphaTest::same(0.0, (float) $emptyOverview['summary']['total_distance_m'], 'Usuário vazio deve ter distância zero');
    AlphaTest::same(null, $emptyOverview['summary']['perceived_effort'], 'Usuário vazio não deve inventar RPE');

    $sports = $pdo->query("SELECT m.idmodalidade,m.slug,mm.idmodelo
        FROM modalidades m JOIN modelos_modalidade mm ON mm.idmodalidade=m.idmodalidade AND mm.ativo=TRUE
        WHERE m.ativo=TRUE AND m.slug IN ('corrida','ciclismo') ORDER BY m.slug")->fetchAll();
    $sportBySlug = [];
    foreach ($sports as $row) $sportBySlug[(string) $row['slug']] = $row;
    AlphaTest::assert(isset($sportBySlug['corrida'], $sportBySlug['ciclismo']), 'Seeds de corrida e ciclismo são necessários para Progress v1');

    $createGps = static function (PDO $pdo, string $userId, string $slug, string $date, float $distance, int $duration, float $elevation, int $rpe, string $key, ?string $workoutId = null): array {
        $start = $date . 'T07:00:00-03:00';
        $end = (new DateTimeImmutable($start))->modify('+' . $duration . ' seconds')->format(DateTimeInterface::ATOM);
        $payload = [
            'sport' => $slug,
            'title' => 'Progress ' . $slug . ' ' . $date,
            'visibility' => 'privado',
            'perceived_effort' => $rpe,
            'started_at' => $start,
            'ended_at' => $end,
            'metrics' => ['distance_m' => $distance, 'duration_s' => $duration, 'elevation_gain_m' => $elevation],
            'gps' => ['points' => [['lat' => -27.35, 'lon' => -53.39], ['lat' => -27.351, 'lon' => -53.388]]],
        ];
        if ($workoutId !== null) $payload['workout_id'] = $workoutId;
        return stridebr_api_create_activity($pdo, $userId, $payload, $key);
    };

    $singleActivity = $createGps($pdo, $single, 'corrida', $currentDate, 5000, 1800, 30, 6, 'progress-single-activity-1');
    $singleOverview = stridebr_api_progress_overview($pdo, $single, ['from' => $from, 'to' => $to]);
    AlphaTest::same(1, (int) $singleOverview['summary']['activities_count'], 'Usuário com uma Activity deve contar exatamente uma');
    AlphaTest::same(5000.0, (float) $singleOverview['summary']['total_distance_m'], 'Activity única deve agregar distância real');

    $createGps($pdo, $boundary, 'corrida', $from, 1000, 360, 0, 3, 'progress-boundary-from');
    $createGps($pdo, $boundary, 'corrida', $to, 2000, 720, 0, 3, 'progress-boundary-to');
    $createGps($pdo, $boundary, 'corrida', $today->modify('+11 days')->format('Y-m-d'), 3000, 1080, 0, 3, 'progress-boundary-after');
    $boundaryOverview = stridebr_api_progress_overview($pdo, $boundary, ['from' => $from, 'to' => $to]);
    AlphaTest::same(2, (int) $boundaryOverview['summary']['activities_count'], 'from/to devem ser inclusivos e o dia seguinte a to deve ficar fora do range');

    $previousRun = $createGps($pdo, $owner, 'corrida', $today->modify('-30 days')->format('Y-m-d'), 3000, 1200, 20, 5, 'progress-run-previous');
    $run1 = $createGps($pdo, $owner, 'corrida', $today->modify('-10 days')->format('Y-m-d'), 5000, 1800, 35, 6, 'progress-run-1');
    $run2 = $createGps($pdo, $owner, 'corrida', $currentDate, 10000, 3500, 120, 8, 'progress-run-2');
    $ride = $createGps($pdo, $owner, 'ciclismo', $today->modify('-5 days')->format('Y-m-d'), 30000, 3600, 250, 5, 'progress-ride-1');
    $originUpdate = $pdo->prepare('UPDATE registros_atividade SET origem=:origin, origem_provedor=:provider WHERE idregistro=:id AND idusuario=:user');
    $originUpdate->execute([':origin' => 'api', ':provider' => 'strava', ':id' => (string) $run1['id'], ':user' => $owner]);
    $originUpdate->execute([':origin' => 'importacao', ':provider' => null, ':id' => (string) $run2['id'], ':user' => $owner]);

    $midnightDate = $today->modify('-3 days')->format('Y-m-d');
    $midnightPayload = [
        'sport' => 'corrida',
        'title' => 'Progress timezone local',
        'visibility' => 'privado',
        'perceived_effort' => 4,
        'started_at' => $midnightDate . 'T00:30:00-03:00',
        'ended_at' => $midnightDate . 'T01:00:00-03:00',
        'metrics' => ['distance_m' => 4000, 'duration_s' => 1800, 'elevation_gain_m' => 10],
        'gps' => ['points' => [['lat' => -27.35, 'lon' => -53.39], ['lat' => -27.351, 'lon' => -53.388]]],
    ];
    $midnight = stridebr_api_create_activity($pdo, $owner, $midnightPayload, 'progress-timezone-1');

    $strengthModel = $pdo->query("SELECT mm.idmodelo,m.idmodalidade FROM modelos_modalidade mm JOIN modalidades m ON m.idmodalidade=mm.idmodalidade WHERE mm.ativo=TRUE AND m.ativo=TRUE AND m.familia_hub='strength' ORDER BY CASE WHEN m.slug='musculacao' THEN 0 ELSE 1 END LIMIT 1")->fetch();
    AlphaTest::assert((bool) $strengthModel, 'Modelo strength é necessário para Progress v1');
    $exercise = stridebr_api_training_exercise_create($pdo, $owner, ['name' => 'Supino Progress Alpha', 'sport_ids' => [(string) $strengthModel['idmodalidade']]]);
    $strengthActivity = atividadeSalvarRegistro($pdo, $owner, [
        'idmodelo' => (string) $strengthModel['idmodelo'],
        'titulo' => 'Força Progress Alpha',
        'data_inicio' => $today->modify('-4 days')->format('Y-m-d') . ' 18:00:00',
        'data_fim' => $today->modify('-4 days')->format('Y-m-d') . ' 19:00:00',
        'status' => 'concluido',
        'visibilidade' => 'privado',
        'esforco_percebido' => 7,
        'origem' => 'manual',
        'permitir_campos_vazios' => true,
    ]);
    $insertSet = $pdo->prepare('INSERT INTO series_exercicio_atividade (idserie,idregistro,idexercicio,nome_exercicio,ordem_exercicio,ordem_serie,carga_kg,repeticoes,concluida) VALUES (:id,:activity,:exercise,:name,1,:set,:load,:reps,:done)');
    $insertSet->execute([':id' => stridebr_api_id(), ':activity' => $strengthActivity, ':exercise' => $exercise['id'], ':name' => $exercise['name'], ':set' => 1, ':load' => 70, ':reps' => 10, ':done' => 'true']);
    $insertSet->execute([':id' => stridebr_api_id(), ':activity' => $strengthActivity, ':exercise' => $exercise['id'], ':name' => $exercise['name'], ':set' => 2, ':load' => 75, ':reps' => 8, ':done' => 'true']);
    $insertSet->execute([':id' => stridebr_api_id(), ':activity' => $strengthActivity, ':exercise' => $exercise['id'], ':name' => $exercise['name'], ':set' => 3, ':load' => null, ':reps' => 12, ':done' => 'true']);
    $insertSet->execute([':id' => stridebr_api_id(), ':activity' => $strengthActivity, ':exercise' => $exercise['id'], ':name' => $exercise['name'], ':set' => 4, ':load' => 80, ':reps' => 5, ':done' => 'false']);

    $plannedDate = $today->modify('-1 day')->format('Y-m-d');
    $planned = stridebr_api_training_workout_create($pdo, $owner, [
        'title' => 'Corrida planejada Progress',
        'sport' => 'corrida',
        'date' => $plannedDate,
        'time' => '07:00',
        'planned_duration_s' => 1800,
        'planned_distance_m' => 5000,
    ]);
    $plannedActivity = $createGps($pdo, $owner, 'corrida', $plannedDate, 5100, 1860, 15, 6, 'progress-linked-workout-1', (string) $planned['workout']['id']);
    AlphaTest::assert(!empty($plannedActivity['id']), 'Workout vinculado deve gerar Activity canônica');

    $cancelled = stridebr_api_training_workout_create($pdo, $owner, [
        'title' => 'Cancelado Progress', 'sport' => 'corrida', 'date' => $today->modify('-6 days')->format('Y-m-d'), 'time' => '08:00', 'planned_duration_s' => 1200,
    ]);
    stridebr_api_workout_cancel($pdo, $owner, (string) $cancelled['workout']['id']);
    stridebr_api_training_workout_create($pdo, $owner, [
        'title' => 'Futuro Progress', 'sport' => 'ciclismo', 'date' => $today->modify('+5 days')->format('Y-m-d'), 'time' => '08:00', 'planned_duration_s' => 3600,
    ]);
    stridebr_api_training_workout_create($pdo, $owner, [
        'title' => 'Perdido Progress', 'sport' => 'corrida', 'date' => $today->modify('-8 days')->format('Y-m-d'), 'time' => '08:00', 'planned_duration_s' => 1800,
    ]);

    $quick = stridebr_api_training_workout_create($pdo, $owner, [
        'title' => 'Quick Progress', 'sport' => (string) $strengthModel['idmodalidade'], 'date' => $today->modify('-7 days')->format('Y-m-d'), 'time' => '18:00', 'planned_duration_s' => 1800,
    ]);
    $quickResult = stridebr_api_workout_quick_register($pdo, $owner, (string) $quick['workout']['id'], [
        'performed_date' => $today->modify('-7 days')->format('Y-m-d'), 'start_time' => '18:00', 'duration_min' => 30, 'intensity' => 'moderado',
    ], 'progress-quick-1');
    AlphaTest::assert(!empty($quickResult['activity']['id']), 'Quick Register deve criar Activity canônica');

    $overview = stridebr_api_progress_overview($pdo, $owner, ['from' => $from, 'to' => $to]);
    AlphaTest::assert((int) $overview['summary']['activities_count'] >= 7, 'Overview deve agregar Activities de múltiplas origens');
    AlphaTest::assert((float) $overview['summary']['total_distance_m'] >= 54100, 'Overview deve somar distância medida sem inventar academia');
    AlphaTest::assert($overview['summary']['total_training_load'] !== null, 'Training load deve reutilizar duration × RPE quando suportado');
    AlphaTest::same(1, (int) $overview['previous_period']['activities_count']['previous'], 'Comparação deve consultar período anterior de mesmo tamanho');
    AlphaTest::assert(is_numeric($overview['previous_period']['activities_count']['change_percent']), 'Comparação com período anterior não zero deve calcular percentual');

    $zeroPrevious = stridebr_api_progress_overview($pdo, $single, ['from' => $from, 'to' => $to]);
    AlphaTest::same(0, (int) $zeroPrevious['previous_period']['activities_count']['previous'], 'Período anterior vazio deve ser zero para contagem');
    AlphaTest::same(null, $zeroPrevious['previous_period']['activities_count']['change_percent'], 'Período anterior zero não pode gerar infinito');

    foreach (['day', 'week', 'month'] as $bucket) {
        $series = stridebr_api_progress_timeseries($pdo, $owner, ['from' => $from, 'to' => $to, 'metric' => 'distance', 'bucket' => $bucket]);
        AlphaTest::assert(count($series['data']) > 0, "Timeseries {$bucket} deve produzir buckets");
    }
    $loadSeries = stridebr_api_progress_timeseries($pdo, $owner, ['from' => $from, 'to' => $to, 'metric' => 'training_load', 'bucket' => 'week']);
    AlphaTest::same('session_rpe_au', $loadSeries['unit'], 'Timeseries deve expor unidade canônica de training load');
    $volumeSeries = stridebr_api_progress_timeseries($pdo, $owner, ['from' => $from, 'to' => $to, 'metric' => 'strength_volume', 'bucket' => 'week']);
    AlphaTest::assert((bool) array_filter($volumeSeries['data'], static fn(array $point): bool => is_numeric($point['value']) && (float) $point['value'] >= 1300), 'Timeseries deve derivar volume de força de reps × carga');

    $sportsSummary = stridebr_api_progress_sports($pdo, $owner, ['from' => $from, 'to' => $to]);
    AlphaTest::assert((bool) array_filter($sportsSummary['data'], static fn(array $row): bool => ($row['sport']['slug'] ?? '') === 'corrida'), 'Distribuição deve conter corrida');
    AlphaTest::assert((bool) array_filter($sportsSummary['data'], static fn(array $row): bool => ($row['sport']['slug'] ?? '') === 'ciclismo'), 'Distribuição deve conter ciclismo');

    $calendar = stridebr_api_progress_calendar($pdo, $owner, ['from' => $from, 'to' => $to]);
    $midnightDay = array_values(array_filter($calendar['data'], static fn(array $row): bool => $row['date'] === $midnightDate));
    AlphaTest::assert($midnightDay !== [] && (int) $midnightDay[0]['activities_count'] >= 1, 'Calendar deve respeitar data civil America/Sao_Paulo');

    $running = stridebr_api_progress_cardio($pdo, $owner, ['from' => $from, 'to' => $to, 'sport' => 'corrida', 'bucket' => 'week']);
    AlphaTest::same('pace_km', $running['sport']['derived_metric'], 'Corrida deve expor metrica_derivada canônica');
    AlphaTest::same('pace', $running['behavior'], 'Corrida deve usar pace');
    AlphaTest::assert($running['current']['average_pace_s_per_km'] !== null, 'Corrida deve derivar pace de distância/duração pareadas');
    $cycling = stridebr_api_progress_cardio($pdo, $owner, ['from' => $from, 'to' => $to, 'sport' => 'ciclismo']);
    AlphaTest::same('velocidade_kmh', $cycling['sport']['derived_metric'], 'Ciclismo deve expor metrica_derivada canônica');
    AlphaTest::same('speed', $cycling['behavior'], 'Ciclismo deve usar velocidade');
    AlphaTest::same(null, $cycling['current']['average_pace_s_per_km'], 'Ciclismo não pode expor pace min/km');
    AlphaTest::assert($cycling['current']['average_speed_kmh'] !== null, 'Ciclismo deve derivar velocidade');

    $strength = stridebr_api_progress_strength($pdo, $owner, ['from' => $from, 'to' => $to]);
    AlphaTest::assert((int) $strength['sessions'] >= 2, 'Strength deve incluir Activity detalhada e Quick Register canônico');
    AlphaTest::assert((int) $strength['completed_sets'] >= 3, 'Strength deve contar séries concluídas');
    AlphaTest::assert((int) $strength['total_reps'] >= 30, 'Strength deve somar reps conhecidas');
    AlphaTest::assert(abs((float) $strength['volume_load_kg'] - 1300.0) < 0.001, 'Série sem carga e série não concluída não podem entrar no volume');

    $exerciseList = stridebr_api_progress_exercises($pdo, $owner, ['from' => $from, 'to' => $to, 'q' => 'Supino Progress']);
    AlphaTest::same(1, count($exerciseList['data']), 'Busca de progresso por exercício deve respeitar histórico do usuário');
    AlphaTest::same(75.0, (float) $exerciseList['data'][0]['best_load_kg'], 'best_load_kg deve considerar somente séries concluídas');
    $exerciseDetail = stridebr_api_progress_exercise($pdo, $owner, (string) $exercise['id'], ['from' => $from, 'to' => $to]);
    AlphaTest::same(75.0, (float) $exerciseDetail['best_load_kg'], 'Detalhe deve reutilizar semântica canônica de melhor carga');
    AlphaTest::same(1300.0, (float) $exerciseDetail['data'][0]['volume_load_kg'], 'Detalhe deve calcular volume somente com carga numérica disponível');
    AlphaTest::throws(fn() => stridebr_api_progress_exercise($pdo, $other, (string) $exercise['id'], ['from' => $from, 'to' => $to]), 'Outro usuário não pode acessar histórico de exercício alheio');

    $adherence = stridebr_api_progress_adherence($pdo, $owner, ['from' => $from, 'to' => $to]);
    AlphaTest::assert((int) $adherence['completed_count'] >= 2, 'Aderência deve reconhecer workout vinculado e Quick Register');
    AlphaTest::assert((int) $adherence['cancelled_count'] >= 1, 'Aderência deve preservar cancelados');
    AlphaTest::assert((int) $adherence['pending_count'] >= 1, 'Treino futuro deve permanecer pendente');
    AlphaTest::assert((int) $adherence['past_due_count'] >= 1, 'Treino passado não realizado deve ser past_due');
    AlphaTest::assert($adherence['completion_rate'] !== null, 'Completion rate deve existir quando há treinos devidos');
    AlphaTest::assert((float) ($adherence['planned_vs_executed']['distance']['planned_distance_m'] ?? 0) >= 5000, 'Planejado × realizado deve comparar distância route-capable disponível');
    AlphaTest::assert((float) ($adherence['planned_vs_executed']['distance']['actual_distance_m'] ?? 0) >= 5100, 'Planejado × realizado deve usar distância real da Activity vinculada');

    $ownerCount = (int) $overview['summary']['activities_count'];
    $ids = [(string) $run1['id'], (string) $run2['id'], (string) $ride['id'], (string) $midnight['id'], $strengthActivity, (string) $plannedActivity['id'], (string) $quickResult['activity']['id']];
    AlphaTest::same(count($ids), count(array_unique($ids)), 'Fixtures de origens diferentes devem convergir para Activities únicas');
    AlphaTest::same(count($ids), $ownerCount, 'Analytics deve contar cada Activity canônica uma vez, independentemente da origem');
    $originCheck = $pdo->prepare('SELECT origem,origem_provedor FROM registros_atividade WHERE idregistro IN (:strava,:imported) ORDER BY idregistro');
    $originCheck->execute([':strava' => (string) $run1['id'], ':imported' => (string) $run2['id']]);
    $originRows = $originCheck->fetchAll();
    AlphaTest::assert((bool) array_filter($originRows, static fn(array $row): bool => (string) ($row['origem_provedor'] ?? '') === 'strava'), 'Fixture Strava deve continuar entrando pela Activity canônica');
    AlphaTest::assert((bool) array_filter($originRows, static fn(array $row): bool => (string) ($row['origem'] ?? '') === 'importacao'), 'Fixture importada deve continuar entrando pela Activity canônica');

    $dashboard = stridebr_api_progress_dashboard($pdo, $owner, ['from' => $from, 'to' => $to]);
    AlphaTest::assert(isset($dashboard['overview'], $dashboard['timeseries'], $dashboard['sports'], $dashboard['adherence'], $dashboard['strength']), 'Dashboard composto deve conter blocos fundamentais');

    AlphaTest::throws(fn() => stridebr_api_progress_overview($pdo, $owner, ['from' => $today->modify('-2000 days')->format('Y-m-d'), 'to' => $today->format('Y-m-d')]), 'Range acima do limite deve falhar');
    AlphaTest::throws(fn() => stridebr_api_progress_timeseries($pdo, $owner, ['from' => $from, 'to' => $to, 'metric' => 'sql_injection', 'bucket' => 'day']), 'metric arbitrário deve falhar');
    AlphaTest::throws(fn() => stridebr_api_progress_timeseries($pdo, $owner, ['from' => $from, 'to' => $to, 'metric' => 'distance', 'bucket' => 'quarter']), 'bucket não suportado deve falhar');

    $previousOnly = stridebr_api_progress_overview($pdo, $owner, ['from' => $previousFrom, 'to' => $previousTo]);
    AlphaTest::assert(isset($previousOnly['summary']['activities_count']), 'Ranges anteriores devem continuar consultáveis');
};
