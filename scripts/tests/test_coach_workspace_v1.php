<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/function/workout_session_service.php';

class CoachWorkspaceCountingPDO extends PDO
{
    public int $prepares = 0;

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->prepares++;
        return parent::prepare($query, $options);
    }
}

return function (PDO $pdo): void {
    $linkUsers = static function (PDO $pdo, string $coach, string $athlete, string $status = 'aceito'): string {
        $id = stridebr_generate_id();
        $stmt = $pdo->prepare("INSERT INTO vinculos_treinador_atleta (idvinculo,idtreinador,idatleta,solicitado_por,status,aceito_em,encerrado_em) VALUES (:id,:coach,:athlete,'treinador',:status,CASE WHEN :accepted='aceito' THEN NOW() ELSE NULL END,CASE WHEN :ended='encerrado' THEN NOW() ELSE NULL END)");
        $stmt->execute([':id'=>$id,':coach'=>$coach,':athlete'=>$athlete,':status'=>$status,':accepted'=>$status,':ended'=>$status]);
        return $id;
    };

    $countingPdo = static function (): CoachWorkspaceCountingPDO {
        $counting = new CoachWorkspaceCountingPDO((string) $GLOBALS['dsn'], (string) $GLOBALS['dbuser'], (string) $GLOBALS['dbpassword'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $counting->exec('SET search_path TO stridebr, public');
        return $counting;
    };

    $coach = alphaTestUser($pdo, 'coach_workspace_main', ['trainer'=>true]);
    $athlete = alphaTestUser($pdo, 'coach_workspace_athlete');
    $link = $linkUsers($pdo, $coach, $athlete);
    $coachB = alphaTestUser($pdo, 'coach_workspace_coach_b', ['trainer'=>true]);
    $intruder = alphaTestUser($pdo, 'coach_workspace_intruder');

    AlphaTest::throws(fn() => treinadorWorkspaceAutorizar($pdo, $coachB, $athlete), 'Coach B accessed Athlete A workspace without link');
    AlphaTest::throws(fn() => treinadorCalendarioAtleta($pdo, $coachB, $athlete, date('Y-m-d'), date('Y-m-d', strtotime('+6 days'))), 'Coach B listed Athlete A calendar');

    $pendingAthlete = alphaTestUser($pdo, 'coach_workspace_pending');
    $linkUsers($pdo, $coachB, $pendingAthlete, 'pendente');
    AlphaTest::throws(fn() => treinadorWorkspaceAutorizar($pdo, $coachB, $pendingAthlete), 'Pending link opened Coach Workspace');
    $endedAthlete = alphaTestUser($pdo, 'coach_workspace_ended');
    $linkUsers($pdo, $coachB, $endedAthlete, 'encerrado');
    AlphaTest::throws(fn() => treinadorWorkspaceAutorizar($pdo, $coachB, $endedAthlete), 'Ended link opened Coach Workspace');

    for ($i = 2; $i <= 50; $i++) {
        $extra = alphaTestUser($pdo, 'coach_workspace_roster_' . $i);
        $linkUsers($pdo, $coach, $extra);
    }
    $oneCoach = alphaTestUser($pdo, 'coach_workspace_one_coach', ['trainer'=>true]);
    $oneAthlete = alphaTestUser($pdo, 'coach_workspace_one_athlete');
    $linkUsers($pdo, $oneCoach, $oneAthlete);

    $count50 = $countingPdo();
    $roster50 = treinadorListarAtletasWorkspace($count50, $coach, '', 'all', 1, 50);
    $queries50 = $count50->prepares;
    AlphaTest::same(50, (int) $roster50['total'], 'Coach roster fixture must contain 50 accepted athletes');
    $count1 = $countingPdo();
    $roster1 = treinadorListarAtletasWorkspace($count1, $oneCoach, '', 'all', 1, 50);
    $queries1 = $count1->prepares;
    AlphaTest::same(1, (int) $roster1['total'], 'Single-athlete fixture invalid');
    AlphaTest::same($queries1, $queries50, 'Athlete list query count grows with roster size');

    $overview50 = $countingPdo();
    treinadorWorkspaceOverview($overview50, $coach);
    $overviewQueries50 = $overview50->prepares;
    $overview1 = $countingPdo();
    treinadorWorkspaceOverview($overview1, $oneCoach);
    AlphaTest::same($overview1->prepares, $overviewQueries50, 'Overview query count grows with roster size');

    $sourceStart = new DateTimeImmutable('next monday');
    $sourceEnd = $sourceStart->modify('+25 days');
    $schedule = cronogramaCriar($pdo, $coach, 'Coach Workspace 4 weeks');
    $personalExercise = cronogramaCriarExercicio($pdo, $coach, 'Coach private movement', null, [], null, null, ['coach movement'], 'halteres', 'load_reps');
    $sourceWorkouts = [];
    foreach ([[1,'Rodagem leve'],[3,'Intervalado'],[5,'Força técnica']] as [$weekday,$title]) {
        $workout = cronogramaSalvarTreino($pdo, $coach, [
            'idcronograma'=>$schedule,
            'titulo'=>$title,
            'dia_semana'=>$weekday,
            'hora_inicio'=>'18:00',
            'hora_fim'=>'19:00',
            'vigencia_inicio'=>$sourceStart->format('Y-m-d'),
            'vigencia_fim'=>$sourceEnd->format('Y-m-d'),
        ]);
        cronogramaSalvarExercicios($pdo, $workout, $coach, [[
            'idexercicio'=>$personalExercise,
            'nome'=>'Coach private movement',
            'series'=>'3',
            'repeticoes'=>'12',
            'carga'=>'40 kg',
            'duracao'=>'45 s',
            'distancia'=>'500 m',
            'descanso'=>'60 s',
            'observacoes'=>'Snapshot source',
        ]], []);
        $sourceWorkouts[] = $workout;
    }

    $targetStart = $sourceStart->modify('+42 days');
    $targetEnd = $targetStart->modify('+25 days');
    $conflict = treinadorCriarPrescricao($pdo, $coach, $athlete, [
        'titulo'=>'Existing same-day workout',
        'data_treino'=>$targetStart->format('Y-m-d'),
        'hora_inicio'=>'07:00',
        'status'=>'publicado',
    ], []);
    AlphaTest::assert($conflict !== '', 'Conflict fixture was not created');

    $preview = treinadorPreverAplicacaoCronograma($pdo, $coach, $athlete, $schedule, $targetStart->format('Y-m-d'));
    AlphaTest::same(12, (int) $preview['total'], 'SEG/QUA/SEX x 4 weeks must materialize 12 occurrences');
    AlphaTest::assert((int) ($preview['conflicts'][$targetStart->format('Y-m-d')] ?? 0) >= 1, 'Plan preview did not surface existing date conflict');
    AlphaTest::same($targetEnd->format('Y-m-d'), (string) $preview['end'], 'Finite plan end date shifted incorrectly');

    AlphaTest::throws(fn() => treinadorPreverAplicacaoCronograma($pdo, $coachB, $athlete, $schedule, $targetStart->format('Y-m-d')), 'Other coach previewed another coach schedule for Athlete A');

    $applyPdo = $countingPdo();
    $applyPdo->prepares = 0;
    $application = treinadorAplicarCronograma($applyPdo, $coach, $athlete, $schedule, $targetStart->format('Y-m-d'), null, 'publicado', 'coach-workspace-v1-plan-key');
    $applyQueries = $applyPdo->prepares;
    AlphaTest::assert($applyQueries <= 18, 'Plan apply prepare count indicates per-workout/per-exercise N+1');
    $applicationId = (string) ($application['idaplicacao'] ?? '');
    AlphaTest::assert($applicationId !== '', 'Plan application id missing');

    $appointmentStmt = $pdo->prepare("SELECT idagendamento,idtreino_origem,data_treino,status,titulo FROM treinos_agendados WHERE idaplicacao_cronograma=:app ORDER BY data_treino,hora_inicio");
    $appointmentStmt->execute([':app'=>$applicationId]);
    $appointments = $appointmentStmt->fetchAll();
    AlphaTest::same(12, count($appointments), 'Plan application did not create exactly 12 workouts');
    $dates = array_column($appointments, 'data_treino');
    AlphaTest::same(12, count(array_unique(array_map(static fn($row): string => (string) $row['idtreino_origem'] . ':' . (string) $row['data_treino'], $appointments))), 'Materialization duplicated source/date identity');
    AlphaTest::assert(min($dates) >= $targetStart->format('Y-m-d') && max($dates) <= $targetEnd->format('Y-m-d'), 'Materialized workout escaped target plan range');

    $retry = treinadorAplicarCronograma($pdo, $coach, $athlete, $schedule, $targetStart->format('Y-m-d'), null, 'publicado', 'coach-workspace-v1-plan-key');
    AlphaTest::same($applicationId, (string) ($retry['idaplicacao'] ?? ''), 'Retry with same idempotency key returned another application');
    $countApplied = $pdo->prepare('SELECT COUNT(*) FROM treinos_agendados WHERE idaplicacao_cronograma=:app');
    $countApplied->execute([':app'=>$applicationId]);
    AlphaTest::same(12, (int) $countApplied->fetchColumn(), 'Retry duplicated plan workouts');
    AlphaTest::throws(fn() => treinadorAplicarCronograma($pdo, $coach, $athlete, $schedule, $targetStart->format('Y-m-d'), null, 'rascunho', 'coach-workspace-v1-plan-key'), 'Same idempotency key accepted different payload');

    $snapshotStmt = $pdo->prepare('SELECT tae.* FROM treinos_agendados_exercicios tae JOIN treinos_agendados ta ON ta.idagendamento=tae.idagendamento WHERE ta.idaplicacao_cronograma=:app ORDER BY ta.data_treino,tae.ordem');
    $snapshotStmt->execute([':app'=>$applicationId]);
    $snapshots = $snapshotStmt->fetchAll();
    AlphaTest::same(12, count($snapshots), 'Exercise snapshot materialization did not batch-copy all exercises');
    AlphaTest::same($personalExercise, (string) $snapshots[0]['idexercicio'], 'Coach personal exercise identity was lost in snapshot');
    AlphaTest::same('Coach private movement', (string) $snapshots[0]['nome_snapshot'], 'Coach personal exercise snapshot name was lost');
    AlphaTest::same('45 s', (string) $snapshots[0]['duracao'], 'Workout Execution V2 duration target was lost during plan materialization');
    AlphaTest::same('500 m', (string) $snapshots[0]['distancia'], 'Workout Execution V2 distance target was lost during plan materialization');
    $copiedOwner = $pdo->prepare('SELECT COUNT(*) FROM exercicios WHERE idexercicio=:exercise AND idusuario=:athlete');
    $copiedOwner->execute([':exercise'=>$personalExercise,':athlete'=>$athlete]);
    AlphaTest::same(0, (int) $copiedOwner->fetchColumn(), 'Applying coach plan copied personal exercise ownership to athlete');

    $originalMaterializedTitle = (string) $appointments[0]['titulo'];
    $pdo->prepare("UPDATE treinos_cronograma SET titulo='SOURCE CHANGED AFTER APPLY' WHERE idtreino=:id")->execute([':id'=>$sourceWorkouts[0]]);
    $pdo->prepare("UPDATE treinos_exercicios SET nome_snapshot='SOURCE EXERCISE CHANGED' WHERE idtreino=:id")->execute([':id'=>$sourceWorkouts[0]]);
    $snapshotAfterSourceEdit = $pdo->prepare('SELECT titulo FROM treinos_agendados WHERE idagendamento=:id');
    $snapshotAfterSourceEdit->execute([':id'=>$appointments[0]['idagendamento']]);
    AlphaTest::same($originalMaterializedTitle, (string) $snapshotAfterSourceEdit->fetchColumn(), 'Changing source schedule silently changed applied workout snapshot');
    $exerciseAfterSourceEdit = $pdo->prepare('SELECT nome_snapshot FROM treinos_agendados_exercicios WHERE idagendamento=:id ORDER BY ordem LIMIT 1');
    $exerciseAfterSourceEdit->execute([':id'=>$appointments[0]['idagendamento']]);
    AlphaTest::same('Coach private movement', (string) $exerciseAfterSourceEdit->fetchColumn(), 'Changing source exercise silently changed applied exercise snapshot');

    $completedAppointment = (string) $appointments[0]['idagendamento'];
    $startedAppointment = (string) $appointments[1]['idagendamento'];
    $activityId = atividadeSalvarRegistro($pdo, $athlete, [
        'idmodelo'=>alphaTestGeneralModel($pdo),
        'titulo'=>'Preserved completed plan activity',
        'data_inicio'=>(new DateTimeImmutable('+1 day 08:00'))->format('Y-m-d H:i:s'),
        'status'=>'concluido',
        'visibilidade'=>'privado',
    ]);
    $pdo->prepare("UPDATE treinos_agendados SET status='concluido' WHERE idagendamento=:id")->execute([':id'=>$completedAppointment]);
    $completedSession = stridebr_generate_id();
    $pdo->prepare("INSERT INTO sessoes_treino (idsessao,idusuario,idagendamento_origem,idregistro_atividade,titulo_snapshot,status,data_inicio,data_fim) VALUES (:id,:user,:appointment,:activity,'Completed plan workout','concluido',NOW()-INTERVAL '1 hour',NOW())")->execute([':id'=>$completedSession,':user'=>$athlete,':appointment'=>$completedAppointment,':activity'=>$activityId]);

    $started = sessaoIniciarAgendado($pdo, $athlete, $startedAppointment);
    AlphaTest::same('Coach private movement', (string) ($started['exercicios'][0]['nome_snapshot'] ?? ''), 'Coach personal exercise snapshot was not executable by athlete');
    AlphaTest::same($personalExercise, (string) ($started['exercicios'][0]['idexercicio'] ?? ''), 'Coach personal exercise identity was not preserved in Workout Session');

    $removal = treinadorRemoverAplicacaoCronograma($pdo, $coach, $applicationId);
    AlphaTest::same(10, (int) $removal['cancelled'], 'Plan removal did not cancel only future unstarted workouts');
    $statusCheck = $pdo->prepare('SELECT status FROM treinos_agendados WHERE idagendamento=:id');
    $statusCheck->execute([':id'=>$completedAppointment]);
    AlphaTest::same('concluido', (string) $statusCheck->fetchColumn(), 'Plan removal rewrote completed workout');
    $statusCheck->execute([':id'=>$startedAppointment]);
    AlphaTest::same('publicado', (string) $statusCheck->fetchColumn(), 'Plan removal cancelled started workout');
    $activityCheck = $pdo->prepare('SELECT COUNT(*) FROM registros_atividade WHERE idregistro=:id AND excluido_em IS NULL');
    $activityCheck->execute([':id'=>$activityId]);
    AlphaTest::same(1, (int) $activityCheck->fetchColumn(), 'Plan removal deleted historical Activity');
    $appStatus = $pdo->prepare('SELECT status,removido_em FROM aplicacoes_cronograma_treinador WHERE idaplicacao=:id');
    $appStatus->execute([':id'=>$applicationId]);
    $removed = $appStatus->fetch();
    AlphaTest::same('removido', (string) ($removed['status'] ?? ''), 'Plan application was not logically removed');
    AlphaTest::assert(!empty($removed['removido_em']), 'Plan application removal timestamp missing');

    $reviewDate = (new DateTimeImmutable('+10 days'))->format('Y-m-d');
    $reviewWorkout = treinadorCriarPrescricao($pdo, $coach, $athlete, [
        'titulo'=>'Coach review fixture',
        'data_treino'=>$reviewDate,
        'hora_inicio'=>'09:00',
        'duracao_prevista_min'=>'20',
        'distancia_prevista_m'=>'500',
        'status'=>'publicado',
    ], [
        ['nome'=>'Strength actual fixture','series'=>'1','repeticoes'=>'12','carga'=>'40 kg','duracao'=>'1200','distancia'=>'500'],
        ['nome'=>'Range fixture','series'=>'1','repeticoes'=>'8–12'],
    ]);
    $reviewSession = stridebr_generate_id();
    $pdo->prepare("INSERT INTO sessoes_treino (idsessao,idusuario,idagendamento_origem,titulo_snapshot,status,data_inicio,data_fim) VALUES (:id,:user,:appointment,'Coach review fixture','concluido',NOW()-INTERVAL '25 minutes',NOW())")->execute([':id'=>$reviewSession,':user'=>$athlete,':appointment'=>$reviewWorkout]);
    $sessionExercise = stridebr_generate_id();
    $pdo->prepare("INSERT INTO sessoes_treino_exercicios (idsessao_exercicio,idsessao,nome_snapshot,series_planejadas,repeticoes_snapshot,carga_snapshot,duracao_snapshot,distancia_snapshot,ordem,concluido) VALUES (:id,:session,'Strength actual fixture',1,'12','40 kg','1200','500',1,TRUE)")->execute([':id'=>$sessionExercise,':session'=>$reviewSession]);
    $pdo->prepare("INSERT INTO sessoes_treino_series (idserie,idsessao_exercicio,numero,concluida,repeticoes_realizadas,carga_realizada,duracao_realizada_s,distancia_realizada_m,data_conclusao) VALUES (:id,:exercise,1,TRUE,'10','45 kg',1500,600,NOW())")->execute([':id'=>stridebr_generate_id(),':exercise'=>$sessionExercise]);

    $comparison = treinadorCompararPlanejadoRealizado($pdo, $coach, $athlete, $reviewWorkout);
    $firstSet = $comparison['exercises'][0]['sets'][0];
    AlphaTest::same('12', (string) $firstSet['planned']['repetitions'], 'Planned reps changed during comparison');
    AlphaTest::same('40 kg', (string) $firstSet['planned']['load'], 'Planned load changed during comparison');
    AlphaTest::same('10', (string) $firstSet['actual']['repetitions'], 'Actual reps not read from Workout Session');
    AlphaTest::same('45 kg', (string) $firstSet['actual']['load'], 'Actual load not read from Workout Session');
    AlphaTest::same(1500, (int) $firstSet['actual']['duration_s'], 'Actual duration not read from Workout Session V2');
    AlphaTest::same(600.0, (float) $firstSet['actual']['distance_m'], 'Actual distance not read from Workout Session V2');
    AlphaTest::same(null, $comparison['exercises'][1]['sets'][0]['actual']['repetitions'], 'Range target fabricated an actual repetition value');
    AlphaTest::same(1200, (int) $comparison['endurance']['planned_duration_s'], 'Planned workout duration lost in comparison');
    AlphaTest::same(1500, (int) $comparison['endurance']['actual_duration_s'], 'Actual workout duration lost in comparison');
    AlphaTest::same(500.0, (float) $comparison['endurance']['planned_distance_m'], 'Planned workout distance lost in comparison');

    $coachComment = treinadorCriarComentario($pdo, $coach, $reviewWorkout, 'Boa execução.');
    $athleteComment = treinadorCriarComentario($pdo, $athlete, $reviewWorkout, '<b>Foi pesado</b>');
    AlphaTest::assert($coachComment !== '' && $athleteComment !== '', 'Coach/athlete comments were not created');
    $comments = treinadorListarComentarios($pdo, $coach, $reviewWorkout);
    AlphaTest::same(2, count($comments), 'Contextual comments were not readable');
    $athleteStoredComment = null;
    foreach ($comments as $comment) {
        if ((string) ($comment['idcomentario'] ?? '') === $athleteComment) {
            $athleteStoredComment = (string) ($comment['texto'] ?? '');
            break;
        }
    }
    AlphaTest::same('<b>Foi pesado</b>', $athleteStoredComment, 'Comment domain unexpectedly rewrote user text instead of escaping at render');
    AlphaTest::throws(fn() => treinadorCriarComentario($pdo, $coachB, $reviewWorkout, 'IDOR'), 'Another coach commented on Coach A workout');
    AlphaTest::throws(fn() => treinadorCriarComentario($pdo, $intruder, $reviewWorkout, 'IDOR'), 'Intruder commented on athlete workout');
    AlphaTest::throws(fn() => treinadorListarComentarios($pdo, $coachB, $reviewWorkout), 'Another coach read Coach A contextual comments');
    AlphaTest::throws(fn() => treinadorListarComentarios($pdo, $intruder, $reviewWorkout), 'Intruder read athlete contextual comments');
    AlphaTest::throws(fn() => treinadorCriarComentario($pdo, $coach, $reviewWorkout, '   '), 'Empty comment accepted');
    AlphaTest::throws(fn() => treinadorCriarComentario($pdo, $coach, $reviewWorkout, str_repeat('x', 2001)), 'Oversized comment accepted');

    treinadorAtualizarPermissoes($pdo, $athlete, $link, []);
    AlphaTest::throws(fn() => treinadorCriarPrescricao($pdo, $coach, $athlete, ['titulo'=>'Revoked','data_treino'=>$reviewDate,'status'=>'rascunho'], []), 'Revoked prescribe permission was cached/ignored');
    AlphaTest::throws(fn() => treinadorCriarComentario($pdo, $coach, $reviewWorkout, 'After revoke'), 'Revoked feedback permission was cached/ignored');
    AlphaTest::throws(fn() => treinadorAtividadeReadOnly($pdo, $coach, $athlete, $activityId), 'Revoked activity permission was cached/ignored');

    treinadorEncerrarVinculo($pdo, $athlete, $link);
    AlphaTest::throws(fn() => treinadorWorkspaceAutorizar($pdo, $coach, $athlete), 'Ended link remained authorized in Coach Workspace');
};
