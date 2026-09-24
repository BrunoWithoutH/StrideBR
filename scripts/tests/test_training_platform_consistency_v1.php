<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/function/workout_definition.php';
require_once dirname(__DIR__, 2) . '/src/function/workout_session_service.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma_compartilhar.php';
require_once dirname(__DIR__, 2) . '/src/function/api_v1.php';

return function (PDO $pdo): void {
    $user = alphaTestUser($pdo, 'training_consistency_v1');
    $catalog = $pdo->query("SELECT idexercicio,nome FROM exercicios WHERE idusuario IS NULL AND ativo=TRUE ORDER BY nome LIMIT 8")->fetchAll();
    AlphaTest::same(8, count($catalog), 'Training consistency fixture requires eight canonical exercises');

    $schedule = cronogramaCriar($pdo, $user, 'Consistency V1');
    $workout = cronogramaSalvarTreino($pdo, $user, [
        'idcronograma'=>$schedule,'titulo'=>'Canonical workout','dia_semana'=>2,'hora_inicio'=>'18:00','hora_fim'=>'19:00',
        'vigencia_inicio'=>(new DateTimeImmutable('today'))->format('Y-m-d'),'vigencia_fim'=>(new DateTimeImmutable('+60 days'))->format('Y-m-d'),'idmodalidade'=>'m_musculacao',
    ]);
    $rows = [
        ['idexercicio'=>$catalog[0]['idexercicio'],'nome'=>$catalog[0]['nome'],'metodo_prescricao'=>'standard','prescription'=>['version'=>1,'method'=>'standard','sets'=>3,'reps'=>['mode'=>'fixed','value'=>10],'load'=>['value'=>40,'unit'=>'kg'],'rest_after_s'=>60],'grupo_chave'=>'superset-a','grupo_tipo'=>'superset','grupo_voltas'=>3,'grupo_descanso_pos_volta_s'=>90],
        ['idexercicio'=>$catalog[1]['idexercicio'],'nome'=>$catalog[1]['nome'],'metodo_prescricao'=>'standard','prescription'=>['version'=>1,'method'=>'standard','sets'=>3,'reps'=>['mode'=>'fixed','value'=>12],'load'=>['value'=>50,'unit'=>'kg'],'rest_after_s'=>60],'grupo_chave'=>'superset-a','grupo_tipo'=>'superset','grupo_voltas'=>3,'grupo_descanso_pos_volta_s'=>90],
        ['idexercicio'=>$catalog[2]['idexercicio'],'nome'=>$catalog[2]['nome'],'metodo_prescricao'=>'cluster','prescription'=>['version'=>1,'method'=>'cluster','blocks'=>2,'clusters'=>[['reps'=>2],['reps'=>2]],'load'=>['value'=>70,'unit'=>'kg'],'intra_cluster_rest_s'=>20,'between_blocks_rest_s'=>120]],
        ['idexercicio'=>$catalog[3]['idexercicio'],'nome'=>$catalog[3]['nome'],'metodo_prescricao'=>'drop_set','prescription'=>['version'=>1,'method'=>'drop_set','rounds'=>1,'stages'=>[['load'=>['value'=>40,'unit'=>'kg'],'reps'=>['mode'=>'fixed','value'=>10]],['load'=>['value'=>30,'unit'=>'kg'],'reps'=>['mode'=>'fixed','value'=>8]],['load'=>['value'=>20,'unit'=>'kg'],'reps'=>['mode'=>'amrap']]],'round_rest_s'=>120]],
        ['idexercicio'=>$catalog[4]['idexercicio'],'nome'=>$catalog[4]['nome'],'metodo_prescricao'=>'standard','prescription'=>['version'=>1,'method'=>'standard','sets'=>2,'reps'=>['mode'=>'range','min'=>8,'max'=>12],'rest_after_s'=>45]],
        ['idexercicio'=>$catalog[5]['idexercicio'],'nome'=>$catalog[5]['nome'],'metodo_prescricao'=>'standard','prescription'=>['version'=>1,'method'=>'standard','sets'=>2,'reps'=>['mode'=>'failure'],'rest_after_s'=>45]],
        ['idexercicio'=>$catalog[6]['idexercicio'],'nome'=>$catalog[6]['nome'],'metodo_prescricao'=>'standard','prescription'=>['version'=>1,'method'=>'standard','sets'=>2,'duration_s'=>45],'grupo_chave'=>'circuit-b','grupo_tipo'=>'circuit','grupo_voltas'=>2,'grupo_descanso_entre_exercicios_s'=>20,'grupo_descanso_pos_volta_s'=>90],
        ['idexercicio'=>$catalog[7]['idexercicio'],'nome'=>$catalog[7]['nome'],'metodo_prescricao'=>'standard','prescription'=>['version'=>1,'method'=>'standard','sets'=>2,'distance_m'=>400,'distance_display_unit'=>'m'],'grupo_chave'=>'circuit-b','grupo_tipo'=>'circuit','grupo_voltas'=>2,'grupo_descanso_entre_exercicios_s'=>20,'grupo_descanso_pos_volta_s'=>90],
        ['nome'=>'Intervalo 400 m','tipo_passo'=>'work','repeticoes_bloco'=>6,'distancia'=>'400 m','recuperacao_duracao_s'=>90,'ordem'=>9],
    ];
    cronogramaSalvarExercicios($pdo, $workout, $user, $rows, []);

    $definition = workoutDefinitionFromSchedule($pdo, $user, $workout);
    AlphaTest::same('stridebr-workout-definition', $definition['schema'] ?? null, 'Canonical definition schema missing');
    AlphaTest::same(9, count($definition['items'] ?? []), 'Canonical definition lost items');
    AlphaTest::same(2, count($definition['groups'] ?? []), 'Canonical definition lost groups');
    AlphaTest::same('ambiguous', workoutDefinitionQuickCompleteMode($definition), 'Range/AMRAP/failure definition must be ambiguous for quick completion');
    AlphaTest::assert(workoutDefinitionCapabilities($definition)['can_start_session'] ?? false, 'Structured definition should be executable');

    $exact = workoutDefinitionBuild(['titulo'=>'Exact'], [[
        'nome'=>'Exact','metodo_prescricao'=>'standard','config_prescricao'=>workoutPrescriptionJson(['version'=>1,'method'=>'standard','sets'=>1,'reps'=>['mode'=>'fixed','value'=>12],'load'=>['value'=>30,'unit'=>'kg']]),
    ]], 'test');
    AlphaTest::same('exact', workoutDefinitionQuickCompleteMode($exact), 'Fixed reps/load definition should be exact');

    $appointment = stridebr_generate_id();
    $pdo->prepare("INSERT INTO treinos_agendados (idagendamento,idatleta,idcriador,idcronograma_origem,idtreino_origem,data_treino,hora_inicio,titulo,descricao,origem,status,publicado_em) VALUES (:id,:user,:user,:schedule,:workout,:date,'18:00','Canonical workout',NULL,'usuario','publicado',NOW())")
        ->execute([':id'=>$appointment,':user'=>$user,':schedule'=>$schedule,':workout'=>$workout,':date'=>(new DateTimeImmutable('+2 days'))->format('Y-m-d')]);
    workoutDefinitionMaterializeScheduled($pdo, $appointment, $definition);
    $scheduled = workoutDefinitionFromScheduled($pdo, $user, $appointment);
    AlphaTest::same(workoutDefinitionSemantic($definition)['items'], workoutDefinitionSemantic($scheduled)['items'], 'Schedule to specific date degraded canonical items');
    AlphaTest::assert(($definition['groups'][0]['key'] ?? '') !== ($scheduled['groups'][0]['key'] ?? ''), 'Scheduled copy reused source group id');
    AlphaTest::same(array_column($definition['groups'], 'type'), array_column($scheduled['groups'], 'type'), 'Scheduled copy changed group types');

    $snapshot = compartilhamentoCronogramaSnapshot($pdo, $user, $schedule);
    AlphaTest::same(2, $snapshot['version'] ?? null, 'Canonical StrideBR/share serializer version changed unexpectedly');
    AlphaTest::same('stridebr-workout-definition', $snapshot['treinos'][0]['definition']['schema'] ?? null, 'Export snapshot omitted canonical definition');
    $imported = compartilhamentoImportarSnapshot($pdo, $user, $snapshot);
    $importedWorkouts = cronogramaListarTreinos($pdo, $imported, $user);
    $importedDefinition = workoutDefinitionFromSchedule($pdo, $user, (string) $importedWorkouts[0]['idtreino']);
    AlphaTest::same(workoutDefinitionSemantic($definition)['items'], workoutDefinitionSemantic($importedDefinition)['items'], 'Structured export/import round-trip degraded canonical items');

    $template = cronogramaSalvarTreinoModelo($pdo, $user, [
        'titulo'=>'Canonical template','descricao'=>'Full fidelity','idmodalidade'=>'m_musculacao',
    ]);
    cronogramaSalvarExerciciosTreinoModelo($pdo, $user, $template, workoutDefinitionRows($definition));
    $templateDefinition = workoutDefinitionFromTemplate($pdo, $user, $template);
    $createdFromTemplate = stridebr_api_workout_create($pdo, $user, [
        'template_id'=>$template,'title'=>'From template','date'=>(new DateTimeImmutable('+5 days'))->format('Y-m-d'),
    ]);
    $createdApiId = (string) ($createdFromTemplate['id'] ?? '');
    $createdParsed = stridebr_api_workout_parse_id($createdApiId);
    $templateScheduled = workoutDefinitionFromScheduled($pdo, $user, (string) ($createdParsed['id'] ?? ''));
    AlphaTest::same(workoutDefinitionSemantic($templateDefinition)['items'], workoutDefinitionSemantic($templateScheduled)['items'], 'Template to scheduled API copy degraded canonical items');
    AlphaTest::same(array_column($templateDefinition['groups'], 'type'), array_column($templateScheduled['groups'], 'type'), 'Template to scheduled API copy changed group structure');

    $beforeSession = workoutDefinitionSemantic($definition)['items'];
    $session = sessaoIniciarCronograma($pdo, $user, $workout);
    $sessionDefinition = workoutDefinitionFromSessionSnapshot($pdo, $user, (string) $session['idsessao']);
    AlphaTest::same($beforeSession, workoutDefinitionSemantic($sessionDefinition)['items'], 'Scheduled workout to session snapshot degraded canonical definition');
    $sequence = (array) ($session['execution_sequence'] ?? []);
    $superset = array_values(array_filter($sequence, static fn(array $entry): bool => ($entry['kind'] ?? '') === 'group' && ($entry['group_type'] ?? '') === 'superset'))[0] ?? null;
    $circuit = array_values(array_filter($sequence, static fn(array $entry): bool => ($entry['kind'] ?? '') === 'group' && ($entry['group_type'] ?? '') === 'circuit'))[0] ?? null;
    AlphaTest::assert(is_array($superset), 'Session execution sequence lost Superset');
    AlphaTest::assert(is_array($circuit), 'Session execution sequence lost Circuit');
    AlphaTest::same(3, count($superset['rounds'] ?? []), 'Superset did not execute by declared rounds');
    AlphaTest::same(2, count($circuit['rounds'] ?? []), 'Circuit did not execute by declared rounds');
    foreach ((array) ($superset['rounds'] ?? []) as $round) AlphaTest::same(2, count($round['members'] ?? []), 'Superset round must contain A then B');
    foreach ((array) ($circuit['rounds'] ?? []) as $round) AlphaTest::same(2, count($round['members'] ?? []), 'Circuit round must contain both members');

    $sourceRows = cronogramaListarTreinoExercicios($pdo, $workout, $user);
    $sourceRows[0]['prescription']['reps'] = ['mode'=>'fixed','value'=>99];
    cronogramaSalvarExercicios($pdo, $workout, $user, $sourceRows, []);
    $sessionAfterEdit = workoutDefinitionFromSessionSnapshot($pdo, $user, (string) $session['idsessao']);
    AlphaTest::same($beforeSession, workoutDefinitionSemantic($sessionAfterEdit)['items'], 'Editing source changed active session snapshot');
    sessaoCancelar($pdo, $user, (string) $session['idsessao']);

    $quick = sessaoRegistroRapidoCronograma($pdo, $user, $workout, ['data'=>(new DateTimeImmutable('today'))->format('Y-m-d'),'hora'=>'12:00']);
    $activityId = (string) ($quick['activity_id'] ?? '');
    AlphaTest::assert($activityId !== '', 'Quick register did not create Activity');
    $count = $pdo->prepare('SELECT COUNT(*) FROM series_exercicio_atividade WHERE idregistro=:id');
    $count->execute([':id'=>$activityId]);
    AlphaTest::same(0, (int) $count->fetchColumn(), 'Quick register invented set actuals for ambiguous workout');
};
