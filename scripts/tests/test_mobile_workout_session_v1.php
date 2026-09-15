<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/function/api_v1.php';

return function (PDO $pdo): void {
    AlphaTest::assert((bool) $pdo->query("SELECT to_regclass('stridebr.api_workout_idempotencias')")->fetchColumn(), 'Idempotência de quick register ausente');
    $owner = alphaTestUser($pdo, 'mobile-session-owner');
    $other = alphaTestUser($pdo, 'mobile-session-other');
    $sportStmt = $pdo->query("SELECT m.idmodalidade,m.slug FROM modalidades m WHERE m.ativo=TRUE AND m.familia_hub='strength' AND EXISTS (SELECT 1 FROM modelos_modalidade mm WHERE mm.idmodalidade=m.idmodalidade AND mm.ativo=TRUE) ORDER BY CASE WHEN m.slug='musculacao' THEN 0 ELSE 1 END,m.ordem_catalogo LIMIT 1");
    $sport = $sportStmt->fetch();
    AlphaTest::assert((bool) $sport, 'Modalidade strength com modelo de Activity não encontrada');
    $sportId = (string) $sport['idmodalidade'];
    $today = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
    $date = $today->format('Y-m-d');
    $weekday = (int) $today->format('w');
    $startLocal = $today->modify('-45 minutes')->format('Y-m-d\TH:i');
    $endLocal = $today->modify('-5 minutes')->format('Y-m-d\TH:i');
    $quickStart = $today->modify('-2 hours');
    $quickDate = $quickStart->format('Y-m-d');
    $quickTime = $quickStart->format('H:i');

    AlphaTest::same(null, stridebr_api_workout_session_current($pdo, $owner), 'Current sem sessão deve retornar null');

    $templateId = stridebr_api_id();
    $pdo->prepare("INSERT INTO treinos_modelo (idtreino_modelo,idusuario,idmodalidade,titulo,codigo) VALUES (:id,:user,:sport,'Força Mobile','FORCA-MOB')")
        ->execute([':id' => $templateId, ':user' => $owner, ':sport' => $sportId]);
    $pdo->prepare("INSERT INTO treinos_modelo_exercicios (idtreino_modelo_exercicio,idtreino_modelo,nome_snapshot,series,repeticoes,carga,bloco,cluster,descanso,ordem) VALUES (:id,:template,'Supino reto',3,'10','60 kg','A','3x','90 s',1)")
        ->execute([':id' => stridebr_api_id(), ':template' => $templateId]);

    $workout = stridebr_api_workout_create($pdo, $owner, ['template_id' => $templateId, 'date' => $date, 'time' => '18:00']);
    $otherWorkout = stridebr_api_workout_create($pdo, $owner, ['template_id' => $templateId, 'date' => $date, 'time' => '20:00']);
    AlphaTest::assert(!empty($workout['capabilities']['can_start_session']), 'Workout estruturado deve permitir iniciar sessão');
    AlphaTest::same('workout_session', $workout['capabilities']['preferred_execution'], 'Strength estruturado deve preferir workout session');

    $session = stridebr_api_workout_session_start($pdo, $owner, $workout['id']);
    AlphaTest::same($workout['id'], $session['workout_id'], 'Sessão deve preservar workout canônico');
    AlphaTest::same('A', $session['exercises'][0]['block'] ?? null, 'Snapshot da sessão deve preservar bloco');
    AlphaTest::same('3x', $session['exercises'][0]['cluster'] ?? null, 'Snapshot da sessão deve preservar cluster');
    AlphaTest::same(3, count($session['exercises'][0]['sets'] ?? []), 'Sessão deve materializar séries planejadas');
    $current = stridebr_api_workout_session_current($pdo, $owner);
    AlphaTest::same($session['id'], $current['id'], 'Current deve retornar a sessão ativa');
    $activeWorkout = stridebr_api_workout_detail($pdo, $owner, $workout['id']);
    AlphaTest::same($session['id'], (string) ($activeWorkout['session']['id'] ?? ''), 'Detalhe deve apontar para sessão ativa');
    AlphaTest::assert(!empty($activeWorkout['capabilities']['can_resume_session']) && empty($activeWorkout['capabilities']['can_start_session']), 'Workout com sessão ativa deve oferecer resume e bloquear novo start');
    AlphaTest::throws(fn() => stridebr_api_workout_session_start($pdo, $owner, $otherWorkout['id']), 'Segunda sessão ativa deve ser bloqueada');

    $set1 = (string) $session['exercises'][0]['sets'][0]['id'];
    $set2 = (string) $session['exercises'][0]['sets'][1]['id'];
    $exerciseId = (string) $session['exercises'][0]['id'];
    $updated = stridebr_api_workout_session_set_update($pdo, $owner, $session['id'], $set1, ['repetitions' => '10', 'load' => '67.5 kg', 'propagate_load' => true]);
    AlphaTest::same('10', $updated['exercises'][0]['sets'][0]['repetitions'], 'Update set deve persistir reps realizadas');
    AlphaTest::same('67.5 kg', $updated['exercises'][0]['sets'][0]['load'], 'Update set deve persistir carga realizada');
    AlphaTest::same('67.5 kg', $updated['exercises'][0]['sets'][1]['load'], 'Propagação deve preencher próxima série não concluída');
    AlphaTest::throws(fn() => stridebr_api_workout_session_set_update($pdo, $other, $session['id'], $set1, ['load' => '999']), 'Outro usuário não pode editar série da sessão');

    $toggled = stridebr_api_workout_session_set_toggle($pdo, $owner, $session['id'], $set1, ['completed' => true]);
    AlphaTest::assert(!empty($toggled['exercises'][0]['sets'][0]['completed']), 'Toggle set deve concluir série');
    AlphaTest::assert(!empty($toggled['exercises'][0]['sets'][0]['completed_at']), 'Série concluída deve registrar horário');
    $exerciseDone = stridebr_api_workout_session_exercise_toggle($pdo, $owner, $session['id'], $exerciseId, ['completed' => true]);
    AlphaTest::assert(!empty($exerciseDone['exercises'][0]['completed']), 'Toggle exercise deve concluir exercício');
    AlphaTest::assert((bool) array_reduce($exerciseDone['exercises'][0]['sets'], static fn(bool $carry, array $set): bool => $carry && !empty($set['completed']), true), 'Concluir exercício deve concluir suas séries');
    $exerciseOpen = stridebr_api_workout_session_exercise_toggle($pdo, $owner, $session['id'], $exerciseId, ['completed' => false]);
    AlphaTest::assert(empty($exerciseOpen['exercises'][0]['completed']), 'Desmarcar exercício deve reabrir exercício');
    $allDone = stridebr_api_workout_session_mark_all($pdo, $owner, $session['id'], ['completed' => true]);
    AlphaTest::same($allDone['progress']['sets_total'], $allDone['progress']['sets_completed'], 'Mark all deve concluir todas as séries');

    $finished = stridebr_api_workout_session_finish($pdo, $owner, $session['id'], ['started_at_local' => $startLocal, 'ended_at_local' => $endLocal, 'intensity' => 'moderado', 'feeling' => 4, 'notes' => 'Sessão mobile']);
    $activityId = (string) $finished['activity']['id'];
    AlphaTest::assert($activityId !== '', 'Finish deve criar Activity real');
    AlphaTest::assert(empty($finished['reused']), 'Primeiro finish não pode ser reutilizado');
    $activityStmt = $pdo->prepare('SELECT idusuario,status,idtreino_cronograma FROM registros_atividade WHERE idregistro=:id');
    $activityStmt->execute([':id' => $activityId]);
    $activity = $activityStmt->fetch();
    AlphaTest::same($owner, (string) ($activity['idusuario'] ?? ''), 'Activity final pertence ao dono da sessão');
    AlphaTest::same('concluido', (string) ($activity['status'] ?? ''), 'Activity final deve estar concluída');
    $seriesCount = $pdo->prepare('SELECT COUNT(*) FROM series_exercicio_atividade WHERE idregistro=:id');
    $seriesCount->execute([':id' => $activityId]);
    AlphaTest::same(3, (int) $seriesCount->fetchColumn(), 'Finish deve persistir séries na Activity');
    $finishAgain = stridebr_api_workout_session_finish($pdo, $owner, $session['id'], ['started_at_local' => $startLocal, 'ended_at_local' => $endLocal]);
    AlphaTest::assert(!empty($finishAgain['reused']), 'Retry de finish deve reutilizar Activity da sessão');
    AlphaTest::same($activityId, (string) $finishAgain['activity']['id'], 'Retry de finish não pode duplicar Activity');
    AlphaTest::same(null, stridebr_api_workout_session_current($pdo, $owner), 'Sessão concluída não deve continuar current');
    $finishedWorkout = stridebr_api_workout_detail($pdo, $owner, $workout['id']);
    AlphaTest::same($activityId, (string) ($finishedWorkout['activity']['id'] ?? ''), 'Workout deve apontar para Activity da sessão');

    $historyWorkout = stridebr_api_workout_create($pdo, $owner, ['template_id' => $templateId, 'date' => $date, 'time' => '21:00']);
    $historySession = stridebr_api_workout_session_start($pdo, $owner, $historyWorkout['id']);
    AlphaTest::assert(!empty($historySession['exercises'][0]['history']['last']), 'Nova sessão deve carregar histórico do exercício');
    AlphaTest::same('67.5 kg', $historySession['exercises'][0]['history']['best_load'] ?? null, 'Histórico deve expor melhor carga no Core');
    $historySet = (string) $historySession['exercises'][0]['sets'][0]['id'];
    AlphaTest::throws(fn() => stridebr_api_workout_session_set_toggle($pdo, $other, $historySession['id'], $historySet, ['completed' => true]), 'Outro usuário não pode concluir série');
    $cancelled = stridebr_api_workout_session_cancel($pdo, $owner, $historySession['id']);
    AlphaTest::same('cancelado', $cancelled['status'], 'Cancel deve preservar sessão cancelada');

    $scheduleId = cronogramaCriar($pdo, $owner, 'Sessão recorrente mobile');
    $recurringId = cronogramaSalvarTreino($pdo, $owner, [
        'idcronograma' => $scheduleId,
        'titulo' => 'Força recorrente',
        'idmodalidade' => $sportId,
        'dia_semana' => $weekday,
        'hora_inicio' => '07:00',
        'hora_fim' => '08:00',
        'vigencia_inicio' => $date,
        'vigencia_fim' => $date,
    ]);
    $pdo->prepare("INSERT INTO treinos_exercicios (idtreino_exercicio,idtreino,nome_snapshot,series,repeticoes,carga,bloco,cluster,ordem) VALUES (:id,:workout,'Agachamento',2,'8','80 kg','B','2x',1)")
        ->execute([':id' => stridebr_api_id(), ':workout' => $recurringId]);
    $recurringApiId = stridebr_api_workout_id_recurring($recurringId, $date);
    $recurringSession = stridebr_api_workout_session_start($pdo, $owner, $recurringApiId);
    AlphaTest::same($recurringApiId, $recurringSession['workout_id'], 'Start deve resolver ID recurring opaco no Core');
    $recurringDuring = stridebr_api_workout_detail($pdo, $owner, $recurringApiId);
    AlphaTest::same($recurringSession['id'], (string) ($recurringDuring['session']['id'] ?? ''), 'Detalhe recurring deve expor sessão ativa da ocorrência');
    AlphaTest::same('recurring', $recurringSession['source'], 'Sessão recorrente deve preservar origem');
    stridebr_api_workout_session_cancel($pdo, $owner, $recurringSession['id']);

    $quickWorkout = stridebr_api_workout_create($pdo, $owner, ['title' => 'Registro rápido', 'sport' => $sportId, 'date' => $quickDate, 'time' => $quickTime, 'planned_duration_s' => 1800]);
    $quickPayload = ['performed_date' => $quickDate, 'start_time' => $quickTime, 'duration_min' => 30, 'intensity' => 'leve', 'feeling' => 5, 'notes' => 'Quick mobile'];
    $quick = stridebr_api_workout_quick_register($pdo, $owner, $quickWorkout['id'], $quickPayload, 'alpha-quick-register-0001');
    AlphaTest::assert(empty($quick['reused']), 'Primeiro quick register deve criar Activity');
    $quickAgain = stridebr_api_workout_quick_register($pdo, $owner, $quickWorkout['id'], $quickPayload, 'alpha-quick-register-0001');
    AlphaTest::assert(!empty($quickAgain['reused']), 'Retry de quick register deve ser idempotente');
    AlphaTest::same((string) $quick['activity']['id'], (string) $quickAgain['activity']['id'], 'Quick register retry não pode duplicar Activity');
    $quickOtherKey = stridebr_api_workout_quick_register($pdo, $owner, $quickWorkout['id'], $quickPayload, 'alpha-quick-register-0002');
    AlphaTest::assert(!empty($quickOtherKey['reused']), 'Workout já realizado deve reutilizar Activity mesmo com outra chave de retry');
    AlphaTest::same((string) $quick['activity']['id'], (string) $quickOtherKey['activity']['id'], 'Lock do domínio deve impedir segunda Activity para o mesmo workout');
    AlphaTest::throws(fn() => stridebr_api_workout_quick_register($pdo, $owner, $quickWorkout['id'], array_replace($quickPayload, ['notes' => 'Outro payload']), 'alpha-quick-register-0001'), 'Mesma Idempotency-Key com outro payload deve falhar');
    $quickDetail = stridebr_api_workout_detail($pdo, $owner, $quickWorkout['id']);
    AlphaTest::same('concluido', $quickDetail['status'], 'Quick register deve refletir realização no workout');
    AlphaTest::same((string) $quick['activity']['id'], (string) ($quickDetail['activity']['id'] ?? ''), 'Quick register deve ligar Activity ao workout');
};
