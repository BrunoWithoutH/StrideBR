<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/function/workout_session_service.php';

return function (PDO $pdo): void {
    $user = alphaTestUser($pdo, 'workout_builder_v2');
    $catalogStmt = $pdo->query("SELECT idexercicio,nome FROM exercicios WHERE idusuario IS NULL AND ativo=TRUE ORDER BY nome LIMIT 7");
    $catalog = $catalogStmt->fetchAll();
    AlphaTest::same(7, count($catalog), 'Workout Builder V2 requires seven canonical exercises');

    $javelinUnit = $pdo->query("SELECT u.simbolo FROM modelos_modalidade mm JOIN modalidades m ON m.idmodalidade=mm.idmodalidade JOIN campos_modelo cm ON cm.idmodelo=mm.idmodelo LEFT JOIN unidades u ON u.idunidade=cm.idunidade WHERE mm.ativo=TRUE AND mm.padrao=TRUE AND m.slug='lancamento-de-dardo' AND lower(cm.slug)='marca' ORDER BY cm.ordem LIMIT 1")->fetchColumn();
    AlphaTest::same('m', (string) $javelinUnit, 'Javelin mark field must use meters in the real activity model');

    $schedule = cronogramaCriar($pdo, $user, 'Workout Builder V2');
    $workout = cronogramaSalvarTreino($pdo, $user, [
        'idcronograma' => $schedule,
        'titulo' => 'Builder structured workout',
        'dia_semana' => 1,
        'hora_inicio' => '18:00',
        'hora_fim' => '19:00',
        'vigencia_inicio' => '2026-09-21',
        'vigencia_fim' => '2026-12-31',
        'idmodalidade' => 'm_musculacao',
    ]);

    $rows = [
        [
            'idexercicio' => $catalog[0]['idexercicio'], 'nome' => $catalog[0]['nome'], 'metodo_prescricao' => 'standard',
            'prescription' => ['version'=>1,'method'=>'standard','sets'=>4,'reps'=>['mode'=>'range','min'=>8,'max'=>12],'load'=>['value'=>30,'unit'=>'kg'],'rest_after_s'=>90],
            'grupo_chave' => 'superset-a', 'grupo_tipo' => 'superset', 'grupo_voltas' => 3, 'grupo_descanso_pos_volta_s' => 90,
        ],
        [
            'idexercicio' => $catalog[1]['idexercicio'], 'nome' => $catalog[1]['nome'], 'metodo_prescricao' => 'standard',
            'prescription' => ['version'=>1,'method'=>'standard','sets'=>3,'reps'=>['mode'=>'amrap'],'rest_after_s'=>60],
            'grupo_chave' => 'superset-a', 'grupo_tipo' => 'superset', 'grupo_voltas' => 3, 'grupo_descanso_pos_volta_s' => 90,
        ],
        [
            'idexercicio' => $catalog[2]['idexercicio'], 'nome' => $catalog[2]['nome'], 'metodo_prescricao' => 'cluster',
            'prescription' => ['version'=>1,'method'=>'cluster','blocks'=>3,'clusters'=>[['reps'=>4],['reps'=>4],['reps'=>3]],'load'=>['value'=>80,'unit'=>'kg'],'intra_cluster_rest_s'=>20,'between_blocks_rest_s'=>120],
        ],
        [
            'idexercicio' => $catalog[3]['idexercicio'], 'nome' => $catalog[3]['nome'], 'metodo_prescricao' => 'drop_set',
            'prescription' => ['version'=>1,'method'=>'drop_set','rounds'=>1,'stages'=>[
                ['load'=>['value'=>40,'unit'=>'kg'],'reps'=>['mode'=>'fixed','value'=>10]],
                ['load'=>['value'=>30,'unit'=>'kg'],'reps'=>['mode'=>'fixed','value'=>8]],
                ['load'=>['value'=>20,'unit'=>'kg'],'reps'=>['mode'=>'amrap']],
            ],'round_rest_s'=>120],
        ],
        [
            'idexercicio' => $catalog[4]['idexercicio'], 'nome' => $catalog[4]['nome'], 'metodo_prescricao' => 'standard',
            'prescription' => ['version'=>1,'method'=>'standard','sets'=>2,'reps'=>['mode'=>'failure'],'rest_after_s'=>60],
            'grupo_chave' => 'circuit-b', 'grupo_tipo' => 'circuit', 'grupo_voltas' => 3, 'grupo_descanso_entre_exercicios_s' => 20, 'grupo_descanso_pos_volta_s' => 90,
        ],
        [
            'idexercicio' => $catalog[5]['idexercicio'], 'nome' => $catalog[5]['nome'], 'metodo_prescricao' => 'standard',
            'prescription' => ['version'=>1,'method'=>'standard','sets'=>2,'duration_s'=>45,'rest_after_s'=>30],
            'grupo_chave' => 'circuit-b', 'grupo_tipo' => 'circuit', 'grupo_voltas' => 3, 'grupo_descanso_entre_exercicios_s' => 20, 'grupo_descanso_pos_volta_s' => 90,
        ],
        [
            'idexercicio' => $catalog[6]['idexercicio'], 'nome' => $catalog[6]['nome'], 'metodo_prescricao' => 'standard',
            'prescription' => ['version'=>1,'method'=>'standard','sets'=>2,'distance_m'=>400,'distance_display_unit'=>'m','rest_after_s'=>60],
            'grupo_chave' => 'circuit-b', 'grupo_tipo' => 'circuit', 'grupo_voltas' => 3, 'grupo_descanso_entre_exercicios_s' => 20, 'grupo_descanso_pos_volta_s' => 90,
        ],
    ];
    cronogramaSalvarExercicios($pdo, $workout, $user, $rows, []);
    $saved = cronogramaListarTreinoExercicios($pdo, $workout, $user);
    AlphaTest::same(7, count($saved), 'Structured workout did not round-trip all exercises');
    AlphaTest::same('standard', $saved[0]['prescription_method'], 'Standard method was not preserved');
    AlphaTest::same('range', $saved[0]['prescription']['reps']['mode'] ?? null, 'Rep range mode was not preserved');
    AlphaTest::same(8, $saved[0]['prescription']['reps']['min'] ?? null, 'Rep range minimum changed');
    AlphaTest::same(12, $saved[0]['prescription']['reps']['max'] ?? null, 'Rep range maximum changed');
    AlphaTest::same('amrap', $saved[1]['prescription']['reps']['mode'] ?? null, 'AMRAP target changed');
    AlphaTest::same('cluster', $saved[2]['prescription_method'], 'Cluster method was not preserved');
    AlphaTest::same([4,4,3], array_column($saved[2]['prescription']['clusters'] ?? [], 'reps'), 'Irregular cluster changed');
    AlphaTest::same('drop_set', $saved[3]['prescription_method'], 'Drop set method was not preserved');
    AlphaTest::same(['fixed','fixed','amrap'], array_map(static fn(array $stage): string => (string) ($stage['reps']['mode'] ?? ''), $saved[3]['prescription']['stages'] ?? []), 'Drop stages changed');
    AlphaTest::same('failure', $saved[4]['prescription']['reps']['mode'] ?? null, 'Failure target changed');
    AlphaTest::same(45, $saved[5]['prescription']['duration_s'] ?? null, 'Duration target changed');
    AlphaTest::same(400.0, (float) ($saved[6]['prescription']['distance_m'] ?? -1), 'Distance target changed');
    AlphaTest::same('m', $saved[6]['prescription']['distance_display_unit'] ?? null, 'Distance display unit changed');
    AlphaTest::same((string) $saved[0]['idgrupo_prescricao'], (string) $saved[1]['idgrupo_prescricao'], 'Superset membership was not shared');
    AlphaTest::same('superset', $saved[0]['grupo_tipo'] ?? null, 'Superset type changed');
    AlphaTest::same((string) $saved[4]['idgrupo_prescricao'], (string) $saved[6]['idgrupo_prescricao'], 'Circuit membership was not shared');
    AlphaTest::same('circuit', $saved[4]['grupo_tipo'] ?? null, 'Circuit type changed');

    $reordered = [$saved[3], $saved[2], $saved[0], $saved[1], $saved[4], $saved[5], $saved[6]];
    cronogramaSalvarExercicios($pdo, $workout, $user, $reordered, []);
    $afterReorder = cronogramaListarTreinoExercicios($pdo, $workout, $user);
    AlphaTest::same((string) $saved[3]['idexercicio'], (string) $afterReorder[0]['idexercicio'], 'Reorder did not persist first exercise');
    AlphaTest::same(range(1, 7), array_map('intval', array_column($afterReorder, 'ordem')), 'Reorder did not normalize order to 1..N');
    AlphaTest::same((string) $afterReorder[2]['idgrupo_prescricao'], (string) $afterReorder[3]['idgrupo_prescricao'], 'Reorder broke superset membership');

    $duplicate = cronogramaDuplicarTreino($pdo, $user, $workout);
    $duplicateRows = cronogramaListarTreinoExercicios($pdo, $duplicate, $user);
    AlphaTest::same('drop_set', $duplicateRows[0]['prescription_method'], 'Workout duplication lost prescription method');
    AlphaTest::same('amrap', $duplicateRows[0]['prescription']['stages'][2]['reps']['mode'] ?? null, 'Workout duplication lost drop stage target');
    AlphaTest::assert((string) $duplicateRows[2]['idgrupo_prescricao'] !== (string) $afterReorder[2]['idgrupo_prescricao'], 'Workout duplication reused source group id');
    AlphaTest::same((string) $duplicateRows[2]['idgrupo_prescricao'], (string) $duplicateRows[3]['idgrupo_prescricao'], 'Workout duplication broke group membership');

    $model = cronogramaSalvarTreinoAtualNaBiblioteca($pdo, $user, $workout);
    $modelData = cronogramaBuscarTreinoModelo($pdo, $user, $model);
    AlphaTest::same('drop_set', $modelData['exercicios'][0]['prescription_method'], 'Schedule to model lost method');
    AlphaTest::same('cluster', $modelData['exercicios'][1]['prescription_method'], 'Schedule to model lost cluster');
    $fromModel = cronogramaAdicionarTreinoModeloAoCronograma($pdo, $user, $model, $schedule, 3, '18:00', '19:00', false, '2026-09-21', '2026-12-31');
    $fromModelRows = cronogramaListarTreinoExercicios($pdo, $fromModel, $user);
    AlphaTest::same('drop_set', $fromModelRows[0]['prescription_method'], 'Model to schedule lost method');
    AlphaTest::same('amrap', $fromModelRows[0]['prescription']['stages'][2]['reps']['mode'] ?? null, 'Model to schedule lost AMRAP stage');

    $snapshot = compartilhamentoCronogramaSnapshot($pdo, $user, $schedule);
    AlphaTest::same(2, $snapshot['version'] ?? null, 'Schedule export version changed unexpectedly');
    $importedSchedule = compartilhamentoImportarSnapshot($pdo, $user, $snapshot);
    $importedWorkouts = cronogramaListarTreinos($pdo, $importedSchedule, $user);
    $importedRows = cronogramaListarTreinoExercicios($pdo, (string) $importedWorkouts[0]['idtreino'], $user);
    AlphaTest::same('drop_set', $importedRows[0]['prescription_method'], 'Export/import lost method');
    AlphaTest::same('amrap', $importedRows[0]['prescription']['stages'][2]['reps']['mode'] ?? null, 'Export/import lost stage target');

    $session = sessaoIniciarCronograma($pdo, $user, $workout);
    $sessionId = (string) $session['idsessao'];
    $clusterExercise = null;
    $dropExercise = null;
    foreach ($session['exercicios'] as $exercise) {
        if (($exercise['metodo_prescricao'] ?? '') === 'cluster') $clusterExercise = $exercise;
        if (($exercise['metodo_prescricao'] ?? '') === 'drop_set') $dropExercise = $exercise;
    }
    AlphaTest::assert(is_array($clusterExercise), 'Session snapshot lost cluster exercise');
    AlphaTest::assert(is_array($dropExercise), 'Session snapshot lost drop set exercise');
    AlphaTest::same(9, count($clusterExercise['series']), 'Cluster did not materialize 3x3 microsets');
    AlphaTest::same(['cluster'], array_values(array_unique(array_column($clusterExercise['series'], 'segmento_tipo'))), 'Cluster segments lost semantic type');
    AlphaTest::same([1,2,3], array_values(array_unique(array_map('intval', array_column($clusterExercise['series'], 'bloco_indice')))), 'Cluster block indexes changed');
    AlphaTest::same(3, count($dropExercise['series']), 'Drop set did not materialize all stages');
    AlphaTest::same('amrap', workoutPrescriptionDecode($dropExercise['series'][2]['meta_repeticoes'] ?? null)['mode'] ?? null, 'Drop AMRAP was materialized as a numeric target');

    $sourceBeforeEdit = workoutPrescriptionDecode($clusterExercise['config_prescricao'] ?? null);
    $sourceRows = cronogramaListarTreinoExercicios($pdo, $workout, $user);
    foreach ($sourceRows as &$sourceRow) {
        if (($sourceRow['metodo_prescricao'] ?? '') !== 'cluster') continue;
        $sourceRow['prescription']['clusters'] = [['reps'=>2],['reps'=>2]];
    }
    unset($sourceRow);
    cronogramaSalvarExercicios($pdo, $workout, $user, $sourceRows, []);
    $sessionAfterSourceEdit = sessaoCarregarPorId($pdo, $user, $sessionId);
    $snapshotCluster = array_values(array_filter($sessionAfterSourceEdit['exercicios'], static fn(array $row): bool => ($row['metodo_prescricao'] ?? '') === 'cluster'))[0];
    AlphaTest::same($sourceBeforeEdit, workoutPrescriptionDecode($snapshotCluster['config_prescricao'] ?? null), 'Editing source workout changed active session snapshot');
    AlphaTest::same(9, count($snapshotCluster['series']), 'Editing source workout changed active session microsets');

    $clusterSet = $snapshotCluster['series'][0];
    $dropSnapshot = array_values(array_filter($sessionAfterSourceEdit['exercicios'], static fn(array $row): bool => ($row['metodo_prescricao'] ?? '') === 'drop_set'))[0];
    $dropSet = $dropSnapshot['series'][2];
    sessaoAtualizarSerie($pdo, $user, (string) $clusterSet['idserie'], null, '82.5', false, 'load', [], $sessionId);
    sessaoAtualizarSerie($pdo, $user, (string) $clusterSet['idserie'], '5', null, false, 'reps', [], $sessionId);
    sessaoAtualizarSerie($pdo, $user, (string) $dropSet['idserie'], null, '20', false, 'load', [], $sessionId);
    sessaoAtualizarSerie($pdo, $user, (string) $dropSet['idserie'], '13', null, false, 'reps', [], $sessionId);
    sessaoAlternarSerie($pdo, $user, (string) $clusterSet['idserie'], true, $sessionId);
    sessaoAlternarSerie($pdo, $user, (string) $dropSet['idserie'], true, $sessionId);
    $now = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
    $finished = sessaoFinalizar($pdo, $user, $sessionId, ['inicio_real'=>$now->modify('-5 minutes')->format('Y-m-d H:i'),'fim_real'=>$now->format('Y-m-d H:i')]);
    $activityId = (string) ($finished['activity_id'] ?? '');
    AlphaTest::assert($activityId !== '', 'Finish did not create Activity');
    $history = $pdo->prepare("SELECT metodo_prescricao,segmento_tipo,bloco_indice,etapa_indice,repeticoes,carga_kg,meta_planejada FROM series_exercicio_atividade WHERE idregistro=:activity AND concluida=TRUE ORDER BY ordem_exercicio,ordem_serie");
    $history->execute([':activity'=>$activityId]);
    $historyRows = $history->fetchAll();
    $clusterActual = array_values(array_filter($historyRows, static fn(array $row): bool => $row['metodo_prescricao'] === 'cluster'))[0] ?? null;
    $dropActual = array_values(array_filter($historyRows, static fn(array $row): bool => $row['metodo_prescricao'] === 'drop_set'))[0] ?? null;
    AlphaTest::same('cluster', $clusterActual['segmento_tipo'] ?? null, 'Activity history lost cluster segment type');
    AlphaTest::same(5, isset($clusterActual['repeticoes']) ? (int) $clusterActual['repeticoes'] : null, 'Cluster actual repetitions were flattened to planned value');
    AlphaTest::same(82.5, isset($clusterActual['carga_kg']) ? (float) $clusterActual['carga_kg'] : null, 'Cluster actual load was lost');
    AlphaTest::same('drop_stage', $dropActual['segmento_tipo'] ?? null, 'Activity history lost drop stage type');
    AlphaTest::same(13, isset($dropActual['repeticoes']) ? (int) $dropActual['repeticoes'] : null, 'AMRAP actual repetitions were not preserved');

    $legacyWorkout = cronogramaSalvarTreino($pdo, $user, [
        'idcronograma'=>$schedule,'titulo'=>'Legacy builder preservation','dia_semana'=>5,'hora_inicio'=>'18:00','hora_fim'=>'19:00','vigencia_inicio'=>'2026-09-21','vigencia_fim'=>'2026-12-31','idmodalidade'=>'m_musculacao',
    ]);
    $legacyId = stridebr_generate_id();
    $pdo->prepare("INSERT INTO treinos_exercicios(idtreino_exercicio,idtreino,idexercicio,nome_snapshot,series,repeticoes,carga,descanso,ordem) VALUES(:id,:workout,:exercise,:name,4,'pirâmide antiga','elástico forte','quando pronto',1)")
        ->execute([':id'=>$legacyId,':workout'=>$legacyWorkout,':exercise'=>$catalog[0]['idexercicio'],':name'=>$catalog[0]['nome']]);
    $legacyRows = cronogramaListarTreinoExercicios($pdo, $legacyWorkout, $user);
    cronogramaSalvarExercicios($pdo, $legacyWorkout, $user, $legacyRows, []);
    $legacyReload = cronogramaListarTreinoExercicios($pdo, $legacyWorkout, $user)[0];
    AlphaTest::same('pirâmide antiga', $legacyReload['repeticoes'], 'Save without edit destroyed unknown legacy repetitions');
    AlphaTest::same('elástico forte', $legacyReload['carga'], 'Save without edit destroyed unknown legacy load');
    AlphaTest::same('quando pronto', $legacyReload['descanso'], 'Save without edit destroyed unknown legacy rest');

    $coach = alphaTestUser($pdo, 'workout_builder_v2_coach', ['trainer'=>true]);
    $athlete = alphaTestUser($pdo, 'workout_builder_v2_athlete');
    $linkId = stridebr_generate_id();
    $pdo->prepare("INSERT INTO vinculos_treinador_atleta(idvinculo,idtreinador,idatleta,solicitado_por,status,aceito_em) VALUES(:id,:coach,:athlete,'treinador','aceito',NOW())")
        ->execute([':id'=>$linkId,':coach'=>$coach,':athlete'=>$athlete]);
    $coachCatalog = $pdo->query("SELECT idexercicio,nome FROM exercicios WHERE idusuario IS NULL AND ativo=TRUE ORDER BY nome LIMIT 2")->fetchAll();
    $appointment = treinadorCriarPrescricao($pdo, $coach, $athlete, [
        'titulo'=>'Coach structured cluster','data_treino'=>(new DateTimeImmutable('+3 days'))->format('Y-m-d'),'hora_inicio'=>'18:00','status'=>'publicado','idmodalidade'=>'m_musculacao',
    ], [[
        'idexercicio'=>$coachCatalog[0]['idexercicio'],'nome'=>$coachCatalog[0]['nome'],'metodo_prescricao'=>'cluster',
        'prescription'=>['version'=>1,'method'=>'cluster','blocks'=>2,'clusters'=>[['reps'=>2],['reps'=>2]],'load'=>['value'=>60,'unit'=>'kg'],'intra_cluster_rest_s'=>15,'between_blocks_rest_s'=>90],
        'grupo_chave'=>'coach-superset','grupo_tipo'=>'superset','grupo_voltas'=>3,'grupo_descanso_pos_volta_s'=>90,
    ],[
        'idexercicio'=>$coachCatalog[1]['idexercicio'],'nome'=>$coachCatalog[1]['nome'],'metodo_prescricao'=>'standard',
        'prescription'=>['version'=>1,'method'=>'standard','sets'=>3,'reps'=>['mode'=>'fixed','value'=>10],'rest_after_s'=>60],
        'grupo_chave'=>'coach-superset','grupo_tipo'=>'superset','grupo_voltas'=>3,'grupo_descanso_pos_volta_s'=>90,
    ]]);
    $coachRowStmt = $pdo->prepare('SELECT metodo_prescricao,config_prescricao,idgrupo_prescricao FROM treinos_agendados_exercicios WHERE idagendamento=:id ORDER BY ordem');
    $coachRowStmt->execute([':id'=>$appointment]);
    $coachRows = $coachRowStmt->fetchAll();
    AlphaTest::same('cluster', $coachRows[0]['metodo_prescricao'] ?? null, 'Coach creation discarded structured method');
    AlphaTest::same([2,2], array_column(workoutPrescriptionDecode($coachRows[0]['config_prescricao'] ?? null)['clusters'] ?? [], 'reps'), 'Coach creation discarded cluster structure');
    AlphaTest::assert(!empty($coachRows[0]['idgrupo_prescricao']) && (string) $coachRows[0]['idgrupo_prescricao'] === (string) ($coachRows[1]['idgrupo_prescricao'] ?? ''), 'Coach creation discarded Superset membership');
    $athleteSession = sessaoIniciarAgendado($pdo, $athlete, $appointment);
    AlphaTest::same('cluster', $athleteSession['exercicios'][0]['metodo_prescricao'] ?? null, 'Coach to athlete scheduled snapshot lost cluster');
    AlphaTest::same(4, count($athleteSession['exercicios'][0]['series'] ?? []), 'Coach cluster was not executable for athlete');
    AlphaTest::assert(!empty($athleteSession['exercicios'][0]['idgrupo_prescricao']) && (string) $athleteSession['exercicios'][0]['idgrupo_prescricao'] === (string) ($athleteSession['exercicios'][1]['idgrupo_prescricao'] ?? ''), 'Coach to athlete session lost Superset membership');
    AlphaTest::same('superset', $athleteSession['exercicios'][0]['grupo_tipo'] ?? null, 'Coach Superset type was not hydrated in athlete session');
    sessaoCancelar($pdo, $athlete, (string) $athleteSession['idsessao']);
};
