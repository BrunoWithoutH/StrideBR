<?php
return function (PDO $pdo): void {
    $tz = new DateTimeZone('America/Sao_Paulo');
    $tomorrow = new DateTimeImmutable('2026-09-21 00:00:00', $tz);
    $b = ['idtreino'=>'home_b','data_original'=>'2026-09-21','titulo'=>'Academia B','hora_inicio'=>'14:00','proxima_data'=>new DateTimeImmutable('2026-09-21 14:00:00',$tz)];
    $c = ['idtreino'=>'home_c','data_original'=>'2026-09-25','titulo'=>'Academia C','hora_inicio'=>'07:00','proxima_data'=>new DateTimeImmutable('2026-09-25 07:00:00',$tz)];
    AlphaTest::same('home_c', (string) dashboardTreinoSeguinteContexto([$b,$c], $b, $tomorrow)['idtreino'], 'Home repetiu o treino já mostrado em Amanhã');
    AlphaTest::same('home_c', (string) dashboardTreinoSeguinteContexto([$c], null, $tomorrow)['idtreino'], 'Home não encontrou o primeiro treino após um amanhã de descanso');
    $sameTitle = $c; $sameTitle['titulo'] = 'Academia B';
    AlphaTest::same('home_c', (string) dashboardTreinoSeguinteContexto([$b,$sameTitle], $b, $tomorrow)['idtreino'], 'Home confundiu ocorrências diferentes com o mesmo título');
    AlphaTest::same(null, dashboardTreinoSeguinteContexto([$b], $b, $tomorrow), 'Home fabricou follow-up quando não há outro treino');

    $athlete = alphaTestUser($pdo, 'planning_v61_athlete');
    $schedule = cronogramaCriar($pdo, $athlete, 'Timeline V6.1');
    $a = cronogramaSalvarTreino($pdo, $athlete, ['idcronograma'=>$schedule,'titulo'=>'Academia A','codigo'=>'A','dia_semana'=>1,'hora_inicio'=>'14:00','hora_fim'=>'15:00','vigencia_inicio'=>'2026-09-01']);
    $bId = cronogramaSalvarTreino($pdo, $athlete, ['idcronograma'=>$schedule,'titulo'=>'Academia B','codigo'=>'B','dia_semana'=>3,'hora_inicio'=>'14:00','hora_fim'=>'15:00','vigencia_inicio'=>'2026-09-01']);
    $cId = cronogramaSalvarTreino($pdo, $athlete, ['idcronograma'=>$schedule,'titulo'=>'Academia C','codigo'=>'C','dia_semana'=>5,'hora_inicio'=>'07:00','hora_fim'=>'08:00','vigencia_inicio'=>'2026-09-01']);
    $rows = cronogramaListarOcorrencias($pdo, $athlete, '2026-09-21', '2026-09-27', $schedule);
    $byDate = [];
    foreach ($rows as $row) $byDate[(string)$row['data_treino']][] = (string)$row['idtreino'];
    AlphaTest::same([$a], $byDate['2026-09-21'] ?? [], 'Timeline perdeu treino de segunda');
    AlphaTest::same([], $byDate['2026-09-22'] ?? [], 'Terça deveria permanecer vazia');
    AlphaTest::same([$bId], $byDate['2026-09-23'] ?? [], 'Timeline perdeu treino de quarta');
    AlphaTest::same([], $byDate['2026-09-24'] ?? [], 'Quinta deveria permanecer vazia antes do move');
    AlphaTest::same([$cId], $byDate['2026-09-25'] ?? [], 'Timeline perdeu treino de sexta');
    AlphaTest::same([], $byDate['2026-09-26'] ?? [], 'Sábado deveria permanecer vazio');
    AlphaTest::same([], $byDate['2026-09-27'] ?? [], 'Domingo deveria permanecer vazio');

    cronogramaAlterarOcorrencia($pdo, $athlete, $bId, '2026-09-23', '2026-09-24', 'this', '14:00');
    $moved = cronogramaListarOcorrencias($pdo, $athlete, '2026-09-21', '2026-09-27', $schedule);
    $movedDates = array_values(array_map(static fn(array $row): string => (string)$row['data_treino'], array_filter($moved, static fn(array $row): bool => (string)$row['idtreino'] === $bId)));
    AlphaTest::same(['2026-09-24'], $movedDates, 'Ocorrência movida apareceu no dia original e no novo');

    $model = alphaTestGeneralModel($pdo);
    $activity = atividadeSalvarRegistro($pdo, $athlete, [
        'idmodelo'=>$model,'titulo'=>'Academia A realizada','data_inicio'=>'2026-09-21 14:00','data_fim'=>'2026-09-21 15:00','status'=>'concluido','visibilidade'=>'privado',
        'idcronograma'=>$schedule,'idtreino_cronograma'=>$a,'data_ocorrencia_origem'=>'2026-09-21','data_ocorrencia_planejada'=>'2026-09-21','hora_ocorrencia_planejada'=>'14:00',
    ]);
    $reconciled = cronogramaListarOcorrenciasConciliadas($pdo, $athlete, '2026-09-21', '2026-09-27', $schedule);
    $completed = array_values(array_filter($reconciled, static fn(array $row): bool => (string)$row['idtreino'] === $a && !empty($row['concluido'])));
    AlphaTest::same(1, count($completed), 'Treino concluído virou row duplicada na timeline');
    AlphaTest::same($activity, (string)$completed[0]['idregistro'], 'Timeline não preservou vínculo com Activity concluída');

    $coach = alphaTestUser($pdo, 'planning_v61_coach', ['trainer'=>true]);
    $link = treinadorCriarConvite($pdo, $coach, 'alpha_planning_v61_athlete', 'treinador');
    treinadorResponderVinculo($pdo, $athlete, $link, 'aceitar');
    treinadorAtualizarPermissoes($pdo, $athlete, $link, ['pode_prescrever'=>true,'pode_ver_cronograma'=>true,'pode_ver_atividades'=>true,'pode_ver_feedback'=>true]);
    $scheduled = treinadorCriarPrescricao($pdo, $coach, $athlete, ['titulo'=>'Treino do treinador','data_treino'=>'2026-09-22','hora_inicio'=>'18:00','status'=>'publicado'], []);
    $stmt = $pdo->prepare("SELECT data_treino,status,origem FROM treinos_agendados WHERE idagendamento=:id AND idatleta=:athlete");
    $stmt->execute([':id'=>$scheduled,':athlete'=>$athlete]);
    $scheduledRow = $stmt->fetch();
    AlphaTest::same('2026-09-22', (string)($scheduledRow['data_treino'] ?? ''), 'Treino avulso/agendado não ficou disponível no dia real');
    AlphaTest::same('publicado', (string)($scheduledRow['status'] ?? ''), 'Treino do treinador não ficou publicado');
    AlphaTest::same('treinador', (string)($scheduledRow['origem'] ?? ''), 'Provenance do treino do treinador foi perdida');
};
