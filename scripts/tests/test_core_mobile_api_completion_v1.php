<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/function/api_v1.php';

return function (PDO $pdo): void {
    $owner = alphaTestUser($pdo, 'mobile_completion_owner');
    $other = alphaTestUser($pdo, 'mobile_completion_other');
    $now = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));

    $sportStmt = $pdo->query("SELECT idmodalidade,slug,familia_hub FROM modalidades WHERE ativo=TRUE AND slug IN ('corrida','musculacao') ORDER BY slug");
    $sports = [];
    foreach ($sportStmt->fetchAll() as $row) $sports[(string) $row['slug']] = $row;
    AlphaTest::assert(isset($sports['corrida']), 'Fixture precisa de corrida.');
    AlphaTest::assert(isset($sports['musculacao']), 'Fixture precisa de musculação.');

    $manualPayload = [
        'sport' => 'corrida',
        'title' => 'Corrida manual API',
        'notes' => 'Criada pelo contrato Mobile',
        'started_at' => $now->modify('-3 hours')->format(DateTimeInterface::ATOM),
        'duration_s' => 1800,
        'distance_m' => 5000,
        'visibility' => 'privado',
        'perceived_effort' => 6,
    ];
    $created = stridebr_api_mobile_activity_create($pdo, $owner, $manualPayload, 'manual-create-0001');
    $activityId = (string) $created['activity']['id'];
    AlphaTest::assert($activityId !== '', 'Manual create precisa retornar Activity.');
    AlphaTest::same('manual', $created['activity']['origin'], 'Manual create precisa preservar origin.');
    AlphaTest::assert($created['activity']['route'] === null, 'Manual create não pode exigir rota GPS.');
    AlphaTest::assert(!empty($created['activity']['version']), 'Activity Detail precisa expor version.');
    AlphaTest::assert(!empty($created['activity']['capabilities']['can_edit']), 'Activity Detail precisa expor capabilities.');
    AlphaTest::same(false, $created['activity']['capabilities']['can_trim_route'], 'Trim permanece fora da V1.');
    $replayed = stridebr_api_mobile_activity_create($pdo, $owner, $manualPayload, 'manual-create-0001');
    AlphaTest::same($activityId, (string) $replayed['activity']['id'], 'Retry manual não pode duplicar Activity.');
    AlphaTest::assert(!empty($replayed['reused']), 'Retry manual precisa indicar reused.');
    AlphaTest::throws(fn() => stridebr_api_mobile_activity_create($pdo, $owner, array_replace($manualPayload, ['title' => 'Outro']), 'manual-create-0001'), 'Mesma key com payload diferente precisa conflitar.');

    $exercise = $pdo->query("SELECT idexercicio,nome FROM exercicios WHERE ativo=TRUE AND idusuario IS NULL ORDER BY CASE WHEN nome='Supino reto' THEN 0 ELSE 1 END,nome LIMIT 1")->fetch();
    AlphaTest::assert((bool) $exercise, 'Catálogo global precisa de exercício para strength manual.');
    $strengthPayload = [
        'sport' => 'musculacao',
        'title' => 'Força manual API',
        'started_at' => $now->modify('-2 hours')->format(DateTimeInterface::ATOM),
        'duration_s' => 2400,
        'strength_exercises' => [[
            'exercise_id' => (string) $exercise['idexercicio'],
            'name' => (string) $exercise['nome'],
            'sets' => [[
                'repetitions' => 12,
                'load_kg' => 40,
                'duration_s' => 30,
                'distance_m' => null,
                'completed' => true,
            ]],
        ]],
    ];
    $strengthCreated = stridebr_api_mobile_activity_create($pdo, $owner, $strengthPayload, 'manual-strength-0001');
    AlphaTest::same(1, count($strengthCreated['activity']['strength_exercises'] ?? []), 'Manual strength precisa retornar estrutura canônica.');
    AlphaTest::same(12, (int) ($strengthCreated['activity']['strength_exercises'][0]['sets'][0]['repetitions'] ?? 0), 'Manual strength precisa persistir reps.');

    $version = (string) $created['activity']['version'];
    $patched = stridebr_api_mobile_activity_patch($pdo, $owner, $activityId, [
        'if_version' => $version,
        'title' => 'Corrida editada no Android',
        'notes' => 'Notas novas',
        'perceived_effort' => 7,
        'visibility' => 'amigos',
        'started_at' => $now->modify('-4 hours')->format(DateTimeInterface::ATOM),
        'duration_s' => 2100,
    ]);
    AlphaTest::same('Corrida editada no Android', $patched['title'], 'PATCH Activity precisa atualizar title.');
    AlphaTest::same('Notas novas', $patched['notes'], 'PATCH Activity precisa atualizar notes.');
    AlphaTest::same(7, $patched['perceived_effort'], 'PATCH Activity precisa atualizar RPE.');
    AlphaTest::same('amigos', $patched['visibility'], 'PATCH Activity precisa atualizar visibility.');
    AlphaTest::assert($patched['version'] !== $version, 'PATCH precisa gerar nova version.');
    AlphaTest::throws(fn() => stridebr_api_mobile_activity_patch($pdo, $owner, $activityId, ['if_version' => $version, 'title' => 'Stale']), 'Versão stale precisa gerar conflito.');
    AlphaTest::throws(fn() => stridebr_api_mobile_activity_patch($pdo, $other, $activityId, ['title' => 'Leak']), 'Outro owner não pode editar Activity.');

    $equipment = stridebr_api_mobile_equipment_save($pdo, $owner, ['name' => 'Tênis API', 'category' => 'tenis', 'brand' => 'Stride']);
    AlphaTest::assert($equipment['active'], 'Equipment criado precisa estar ativo.');
    $equipment = stridebr_api_mobile_equipment_save($pdo, $owner, ['model' => 'V2'], (string) $equipment['id']);
    AlphaTest::same('V2', $equipment['model'], 'Equipment PATCH precisa preservar e alterar dados.');
    AlphaTest::throws(fn() => stridebr_api_mobile_equipment_save($pdo, $other, ['model' => 'Leak'], (string) $equipment['id']), 'Equipment precisa ser owner-scoped.');
    $patchedEquipment = stridebr_api_mobile_activity_patch($pdo, $owner, $activityId, ['if_version' => $patched['version'], 'equipment_ids' => [$equipment['id']]]);
    AlphaTest::same((string) $equipment['id'], (string) ($patchedEquipment['equipment'][0]['id'] ?? ''), 'PATCH Activity precisa ligar equipamento.');

    $profile = stridebr_api_mobile_profile_patch($pdo, $owner, ['name' => 'Atleta Mobile', 'bio' => 'Bio API', 'phone' => '55999999999', 'birth_date' => '2000-01-01']);
    AlphaTest::same('Atleta Mobile', $profile['name'], 'PATCH /me precisa atualizar nome.');
    AlphaTest::same('Bio API', $profile['bio'], 'PATCH /me precisa retornar bio.');
    $otherProfile = stridebr_api_mobile_me($pdo, $other);
    AlphaTest::assert($otherProfile['name'] !== 'Atleta Mobile', 'PATCH /me não pode alterar outro usuário.');
    AlphaTest::throws(fn() => stridebr_api_mobile_profile_patch($pdo, $owner, ['email' => 'x@example.com']), 'PATCH /me precisa rejeitar email.');
    $duplicateUsername = (string) $otherProfile['username'];
    AlphaTest::throws(fn() => stridebr_api_mobile_profile_patch($pdo, $owner, ['username' => $duplicateUsername]), 'Username duplicado precisa falhar.');

    $privacy = stridebr_api_mobile_privacy_patch($pdo, $owner, ['default_activity_visibility' => 'amigos', 'hide_route_start_m' => 250, 'hide_route_end_m' => 150, 'profile_visibility' => 'publico', 'discoverable' => false]);
    AlphaTest::same('amigos', $privacy['default_activity_visibility'], 'Privacy precisa persistir visibilidade padrão.');
    AlphaTest::same(250, $privacy['hide_route_start_m'], 'Privacy precisa persistir hide start.');
    AlphaTest::same(false, $privacy['discoverable'], 'Privacy precisa persistir discoverable boolean.');

    $from = $now->modify('-30 days')->format('Y-m-d');
    $to = $now->format('Y-m-d');
    $dashboardAll = stridebr_api_progress_dashboard($pdo, $owner, ['from' => $from, 'to' => $to]);
    AlphaTest::assert(is_array($dashboardAll['overview'] ?? null), 'Progress Todas precisa manter dashboard válido.');
    $dashboardRun = stridebr_api_progress_dashboard($pdo, $owner, ['from' => $from, 'to' => $to, 'sport' => 'corrida']);
    AlphaTest::same('corrida', (string) ($dashboardRun['overview']['sport']['slug'] ?? $dashboardRun['overview']['sport'] ?? ''), 'Progress corrida precisa aceitar slug real.');
    $dashboardStrength = stridebr_api_progress_dashboard($pdo, $owner, ['from' => $from, 'to' => $to, 'sport' => 'musculacao']);
    AlphaTest::assert(is_array($dashboardStrength['strength'] ?? null), 'Progress musculação precisa retornar strength.');
    $cardio = stridebr_api_progress_cardio($pdo, $owner, ['from' => $from, 'to' => $to, 'sport' => 'corrida', 'bucket' => 'week']);
    AlphaTest::same('corrida', (string) ($cardio['sport']['slug'] ?? ''), 'Progress cardio precisa filtrar corrida.');
    $strength = stridebr_api_progress_strength($pdo, $owner, ['from' => $from, 'to' => $to, 'sport' => 'musculacao']);
    AlphaTest::assert(is_array($strength), 'Progress strength filtrado precisa responder shape válido.');
    $exerciseList = stridebr_api_progress_exercises($pdo, $owner, ['from' => $from, 'to' => $to, 'sport' => 'musculacao']);
    AlphaTest::assert(is_array($exerciseList['data'] ?? null), 'Progress exercises filtrado precisa manter data array.');
    foreach ([28, 92, 365] as $days) {
        $rangeFrom = $now->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
        $payload = stridebr_api_progress_dashboard($pdo, $owner, ['from' => $rangeFrom, 'to' => $to, 'sport' => 'corrida']);
        AlphaTest::assert(is_array($payload), "Progress {$days} dias precisa responder.");
    }
    $monthSeries = stridebr_api_progress_timeseries($pdo, $owner, ['from' => $now->modify('-365 days')->format('Y-m-d'), 'to' => $to, 'sport' => 'corrida', 'metric' => 'activities', 'bucket' => 'month']);
    AlphaTest::assert(is_array($monthSeries['data'] ?? null), 'Progress bucket month precisa responder lista.');
    $otherDashboard = stridebr_api_progress_dashboard($pdo, $other, ['from' => $from, 'to' => $to, 'sport' => 'corrida']);
    AlphaTest::same(0, (int) ($otherDashboard['overview']['summary']['activities_count'] ?? -1), 'Progress precisa isolar owner.');

    $templateId = stridebr_api_id();
    $pdo->prepare("INSERT INTO treinos_modelo (idtreino_modelo,idusuario,idmodalidade,titulo,codigo) VALUES (:id,:user,:sport,'Treino API V2','API-V2')")->execute([':id' => $templateId, ':user' => $owner, ':sport' => $sports['musculacao']['idmodalidade']]);
    $pdo->prepare("INSERT INTO treinos_modelo_exercicios (idtreino_modelo_exercicio,idtreino_modelo,idexercicio,nome_snapshot,series,repeticoes,carga,duracao,distancia,ordem) VALUES (:id,:template,:exercise,:name,3,'12','40 kg','20 min','500 m',1)")->execute([':id' => stridebr_api_id(), ':template' => $templateId, ':exercise' => $exercise['idexercicio'], ':name' => $exercise['nome']]);
    $workout = stridebr_api_workout_create($pdo, $owner, ['template_id' => $templateId, 'date' => $now->format('Y-m-d'), 'time' => '18:00']);
    $session = stridebr_api_workout_session_start($pdo, $owner, (string) $workout['id']);
    $sessionId = (string) $session['id'];
    $sessionExerciseId = (string) $session['exercises'][0]['id'];
    $setId = (string) $session['exercises'][0]['sets'][0]['id'];
    stridebr_api_workout_session_set_update($pdo, $owner, $sessionId, $setId, ['repetitions' => '13', 'edited_field' => 'reps']);
    stridebr_api_workout_session_set_update($pdo, $owner, $sessionId, $setId, ['load' => '42 kg', 'edited_field' => 'load']);
    $durationUpdated = stridebr_api_workout_session_set_update($pdo, $owner, $sessionId, $setId, ['duration_s' => 1500, 'edited_field' => 'duration']);
    AlphaTest::same(1500, $durationUpdated['exercises'][0]['sets'][0]['actual_duration_s'], 'Workout HTTP precisa devolver actual duration.');
    $distanceUpdated = stridebr_api_workout_session_set_update($pdo, $owner, $sessionId, $setId, ['distance_m' => 600, 'edited_field' => 'distance']);
    $first = $distanceUpdated['exercises'][0]['sets'][0];
    AlphaTest::same('13', $first['actual_repetitions'], 'Workout HTTP precisa preservar actual reps após patches independentes.');
    AlphaTest::same('42 kg', $first['actual_load'], 'Workout HTTP precisa preservar actual load após patches independentes.');
    AlphaTest::same(1500, $first['actual_duration_s'], 'Workout HTTP precisa preservar actual duration após patch de distance.');
    AlphaTest::same(600.0, (float) $first['actual_distance_m'], 'Workout HTTP precisa devolver actual distance.');

    $append = stridebr_api_mobile_append_set($pdo, $owner, $sessionId, $sessionExerciseId, 'append-set-0001');
    AlphaTest::same(4, count($append['session']['exercises'][0]['sets']), 'Append set precisa transformar 3 em 4 séries.');
    $appendReplay = stridebr_api_mobile_append_set($pdo, $owner, $sessionId, $sessionExerciseId, 'append-set-0001');
    AlphaTest::same(4, count($appendReplay['session']['exercises'][0]['sets']), 'Retry append não pode criar quinta série.');
    AlphaTest::same($append['set_id'], $appendReplay['set_id'], 'Retry append precisa reutilizar set.');
    AlphaTest::throws(fn() => stridebr_api_mobile_append_set($pdo, $other, $sessionId, $sessionExerciseId, 'append-other-0001'), 'Outro owner não pode adicionar série.');
    $extraSet = (string) $append['set_id'];
    stridebr_api_workout_session_set_update($pdo, $owner, $sessionId, $extraSet, ['repetitions' => '8', 'edited_field' => 'reps']);
    stridebr_api_workout_session_set_update($pdo, $owner, $sessionId, $extraSet, ['load' => '45 kg', 'edited_field' => 'load']);
    stridebr_api_workout_session_set_update($pdo, $owner, $sessionId, $extraSet, ['duration_s' => 60, 'edited_field' => 'duration']);
    stridebr_api_workout_session_set_update($pdo, $owner, $sessionId, $extraSet, ['distance_m' => 100, 'edited_field' => 'distance']);
    stridebr_api_workout_session_set_toggle($pdo, $owner, $sessionId, $extraSet, ['completed' => true]);
    stridebr_api_workout_session_mark_all($pdo, $owner, $sessionId, ['completed' => true]);
    $start = $now->modify('-50 minutes')->format('Y-m-d\TH:i');
    $end = $now->modify('-5 minutes')->format('Y-m-d\TH:i');
    $finished = stridebr_api_workout_session_finish($pdo, $owner, $sessionId, ['started_at_local' => $start, 'ended_at_local' => $end]);
    $finishedActivityId = (string) $finished['activity']['id'];
    $extraPersisted = $pdo->prepare('SELECT repeticoes,carga_kg,duracao_segundos,distancia_metros FROM series_exercicio_atividade WHERE idregistro=:activity AND ordem_serie=4 LIMIT 1');
    $extraPersisted->execute([':activity' => $finishedActivityId]);
    $extraRow = $extraPersisted->fetch();
    AlphaTest::same(8, (int) ($extraRow['repeticoes'] ?? 0), 'Finish precisa copiar reps da série extra.');
    AlphaTest::same(60, (int) ($extraRow['duracao_segundos'] ?? 0), 'Finish precisa copiar duration da série extra.');
    AlphaTest::same(100.0, (float) ($extraRow['distancia_metros'] ?? 0), 'Finish precisa copiar distance da série extra.');
    $summary = stridebr_api_mobile_execution_summary($pdo, $owner, (string) $workout['id']);
    AlphaTest::same(true, $summary['available'], 'Execution summary precisa existir para sessão concluída.');
    AlphaTest::same($finishedActivityId, (string) ($summary['activity']['id'] ?? ''), 'Execution summary precisa apontar para Activity.');
    AlphaTest::assert(count($summary['exercises'][0]['sets'] ?? []) >= 4, 'Execution summary precisa incluir set extra realizado.');

    $secondWorkout = stridebr_api_workout_create($pdo, $owner, ['template_id' => $templateId, 'date' => $now->modify('+1 day')->format('Y-m-d'), 'time' => '18:00']);
    $historySession = stridebr_api_workout_session_start($pdo, $owner, (string) $secondWorkout['id']);
    $history = $historySession['exercises'][0]['history']['last'] ?? null;
    AlphaTest::assert(is_array($history), 'History V2 precisa existir na próxima sessão.');
    AlphaTest::assert(array_key_exists('duration_s', $history) && array_key_exists('distance_m', $history), 'History V2 precisa expor duration/distance realizados.');
    stridebr_api_workout_session_cancel($pdo, $owner, (string) $historySession['id']);

    $deleted = stridebr_api_mobile_activity_delete($pdo, $owner, $activityId);
    AlphaTest::same(true, $deleted['deleted'], 'DELETE Activity precisa soft-delete.');
    $deleteReplay = stridebr_api_mobile_activity_delete($pdo, $owner, $activityId);
    AlphaTest::same(true, $deleteReplay['reused'], 'DELETE replay precisa ser seguro.');
    AlphaTest::same([], stridebr_api_activity_detail($pdo, $activityId, $owner), 'Activity excluída precisa desaparecer do detail.');
    AlphaTest::throws(fn() => stridebr_api_mobile_activity_delete($pdo, $other, $strengthCreated['activity']['id']), 'Outro owner não pode excluir Activity.');
    $afterDelete = stridebr_api_progress_dashboard($pdo, $owner, ['from' => $from, 'to' => $to, 'sport' => 'corrida']);
    AlphaTest::same(0, (int) ($afterDelete['overview']['summary']['activities_count'] ?? -1), 'Progress precisa ignorar Activity excluída quando era a única corrida no intervalo.');

    $equipmentDeleted = stridebr_api_mobile_equipment_delete($pdo, $owner, (string) $equipment['id']);
    AlphaTest::same(true, $equipmentDeleted['deleted'], 'Equipment DELETE precisa arquivar.');
};
