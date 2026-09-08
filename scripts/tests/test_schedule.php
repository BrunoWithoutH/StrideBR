<?php
return function (PDO $pdo): void {
    $a = alphaTestUser($pdo, 'schedule_a');
    $b = alphaTestUser($pdo, 'schedule_b');
    $schedule = cronogramaCriar($pdo, $a, 'Alpha schedule');
    $workout = cronogramaSalvarTreino($pdo, $a, ['idcronograma' => $schedule, 'titulo' => 'Treino', 'dia_semana' => '2', 'hora_inicio' => '08:00', 'hora_fim' => '09:00', 'vigencia_inicio' => '2026-08-01']);
    AlphaTest::same(1, count(cronogramaListarTreinos($pdo, $schedule, $a)), 'Treino não foi adicionado');
    AlphaTest::throws(fn() => cronogramaSalvarTreino($pdo, $b, ['idcronograma' => $schedule, 'titulo' => 'IDOR', 'dia_semana' => 2, 'hora_inicio' => '08:00', 'hora_fim' => '09:00']), 'Usuário alterou cronograma alheio');
    AlphaTest::throws(fn() => cronogramaSalvarTreino($pdo, $a, ['idcronograma' => $schedule, 'titulo' => 'Inválido', 'dia_semana' => 9, 'hora_inicio' => '08:00', 'hora_fim' => '09:00']), 'Dia inválido foi aceito');
    cronogramaAlterarOcorrencia($pdo, $a, $workout, '2026-08-25', '2026-08-27', 'this', '10:15');
    $occurrences = cronogramaListarOcorrencias($pdo, $a, '2026-08-23', '2026-08-29', $schedule);
    AlphaTest::same(1, count($occurrences), 'Ocorrência movida apareceu duplicada');
    AlphaTest::same('2026-08-27', (string) $occurrences[0]['data_treino'], 'Ocorrência não mudou de dia');
    AlphaTest::same('10:15:00', (string) $occurrences[0]['hora_inicio'], 'Ocorrência não mudou de horário');
    AlphaTest::same('11:15:00', (string) $occurrences[0]['hora_fim'], 'Duração do treino não foi preservada');
    AlphaTest::assert(!empty($occurrences[0]['excecao']), 'Ocorrência movida não foi marcada como exceção');
    $workoutB = cronogramaSalvarTreino($pdo, $a, ['idcronograma' => $schedule, 'titulo' => 'Treino B', 'codigo' => 'B', 'dia_semana' => '6', 'hora_inicio' => '12:00', 'hora_fim' => '13:00', 'vigencia_inicio' => '2026-08-01']);
    cronogramaTrocarOcorrencias($pdo, $a, $workout, '2026-08-25', $workoutB, '2026-08-29');
    $swapped = cronogramaListarOcorrencias($pdo, $a, '2026-08-23', '2026-08-29', $schedule);
    $swappedById = [];
    foreach ($swapped as $item) $swappedById[(string) $item['idtreino']] = $item;
    AlphaTest::same('2026-08-29', (string) $swappedById[$workout]['data_treino'], 'Primeiro treino não trocou de dia');
    AlphaTest::same('12:00:00', (string) $swappedById[$workout]['hora_inicio'], 'Primeiro treino não recebeu o horário do segundo');
    AlphaTest::same('2026-08-27', (string) $swappedById[$workoutB]['data_treino'], 'Segundo treino não trocou de dia');
    AlphaTest::same('10:15:00', (string) $swappedById[$workoutB]['hora_inicio'], 'Segundo treino não recebeu o horário do primeiro');
    cronogramaPreferenciaSemanalSalvar($pdo, $a, $schedule, '2026-08-23', $workoutB);
    AlphaTest::same($workoutB, cronogramaPreferenciaSemanalBuscar($pdo, $a, $schedule, '2026-08-23'), 'Preferência semanal não foi salva');
    $model = alphaTestGeneralModel($pdo);
    atividadeSalvarRegistro($pdo, $a, [
        'idmodelo' => $model, 'titulo' => 'Treino realizado fora do planejado', 'data_inicio' => '2026-08-28 12:00',
        'data_fim' => '2026-08-28 13:00', 'status' => 'concluido', 'visibilidade' => 'privado',
        'idcronograma' => $schedule, 'idtreino_cronograma' => $workout,
        'data_ocorrencia_origem' => '2026-08-25', 'data_ocorrencia_planejada' => '2026-08-29',
    ]);
    $reconciled = cronogramaListarOcorrenciasConciliadas($pdo, $a, '2026-08-23', '2026-08-29', $schedule);
    $reconciledA = null;
    foreach ($reconciled as $item) if ((string) $item['idtreino'] === $workout) $reconciledA = $item;
    AlphaTest::assert(is_array($reconciledA) && !empty($reconciledA['concluido']), 'Registro concluído não foi ligado à ocorrência planejada');
    AlphaTest::same('2026-08-28', (string) $reconciledA['data_treino'], 'Treino realizado não apareceu no dia real');
    AlphaTest::same('2026-08-29', (string) $reconciledA['data_planejada'], 'Planejamento anterior à realização não foi preservado');
    $historyWorkout = cronogramaSalvarTreino($pdo, $a, ['idcronograma' => $schedule, 'titulo' => 'Histórico flexível', 'codigo' => 'H', 'dia_semana' => '6', 'hora_inicio' => '08:00', 'hora_fim' => '09:00', 'vigencia_inicio' => '2026-08-16']);
    $historyActivity = atividadeSalvarRegistro($pdo, $a, [
        'idmodelo' => $model, 'titulo' => 'Histórico antes da rotina', 'data_inicio' => '2026-08-14 14:00', 'data_fim' => '2026-08-14 15:00',
        'status' => 'concluido', 'visibilidade' => 'privado', 'idcronograma' => $schedule, 'idtreino_cronograma' => $historyWorkout,
        'data_ocorrencia_origem' => '2026-08-22', 'data_ocorrencia_planejada' => '2026-08-22', 'hora_ocorrencia_planejada' => '08:00',
    ]);
    cronogramaAjustarHistoricoRegistro($pdo, $a, $historyActivity, '2026-08-14', '14:00', '2026-08-14', '14:00');
    $historyItems = cronogramaListarOcorrenciasConciliadas($pdo, $a, '2026-08-09', '2026-08-29', $schedule);
    $historyDone = null;
    $futureStillPlanned = false;
    foreach ($historyItems as $item) {
        if ((string) $item['idtreino'] !== $historyWorkout) continue;
        if (!empty($item['concluido']) && (string) $item['data_treino'] === '2026-08-14') $historyDone = $item;
        if (empty($item['concluido']) && (string) $item['data_treino'] === '2026-08-22') $futureStillPlanned = true;
    }
    AlphaTest::assert(is_array($historyDone), 'Correção histórica não criou a realização na data corrigida');
    AlphaTest::assert(empty($historyDone['realizado_fora_planejado']), 'Mesmo dia planejado e realizado foi marcado como deslocado por diferença de horário');
    AlphaTest::assert($futureStillPlanned, 'Corrigir o histórico consumiu indevidamente uma ocorrência futura da rotina');
    $historyStmt = $pdo->prepare('SELECT data_inicio, data_fim, data_ocorrencia_planejada, hora_ocorrencia_planejada FROM registros_atividade WHERE idregistro = :id');
    $historyStmt->execute([':id' => $historyActivity]);
    $historyRow = $historyStmt->fetch();
    AlphaTest::same('2026-08-14', (new DateTimeImmutable((string) $historyRow['data_ocorrencia_planejada']))->format('Y-m-d'), 'Data planejada histórica não foi salva');
    AlphaTest::same(3600, (new DateTimeImmutable((string) $historyRow['data_fim']))->getTimestamp() - (new DateTimeImmutable((string) $historyRow['data_inicio']))->getTimestamp(), 'Corrigir a realização alterou a duração da atividade');
    require_once dirname(__DIR__, 2) . '/src/function/planejamento.php';
    $facts = planejamentoSemana($pdo, $a, '2026-08-24', $schedule);
    AlphaTest::assert($facts['completed'] >= 1, 'Resumo semanal perdeu realização vinculada');
    $allFacts = planejamentoSemana($pdo, $a, '2026-08-24');
    AlphaTest::assert($allFacts['planned'] >= $facts['planned'], 'Resumo com agendamentos perdeu cronograma');
    $pdo->prepare("UPDATE registros_atividade SET data_inicio='2026-06-01 14:00', data_fim='2026-06-01 15:00' WHERE idregistro=:id")->execute([':id'=>$historyActivity]);
    $distantFacts = planejamentoSemana($pdo, $a, '2026-08-10', $schedule);
    AlphaTest::same(1, $distantFacts['completed'], 'Realização distante com vínculo explícito virou falta');
    cronogramaCancelarOcorrencia($pdo, $a, $workoutB, '2026-09-05');
    $cancelledFacts = planejamentoSemana($pdo, $a, '2026-08-31', $schedule);
    AlphaTest::assert(count(array_filter($cancelledFacts['items'], fn($item) => $item['planning_state'] === 'cancelled')) === 1, 'Cancelamento não apareceu no resumo');
    $rescheduleWorkout = cronogramaSalvarTreino($pdo, $a, ['idcronograma' => $schedule, 'titulo' => 'Reagendamento', 'dia_semana' => '3', 'hora_inicio' => '07:00', 'hora_fim' => '08:30', 'vigencia_inicio' => '2026-09-01']);
    cronogramaAlterarOcorrencia($pdo, $a, $rescheduleWorkout, '2026-09-02', '2026-09-03', 'all', '15:30');
    $rescheduled = cronogramaBuscarTreino($pdo, $rescheduleWorkout, $a);
    AlphaTest::same(4, (int) $rescheduled['dia_semana'], 'Reagendamento de todas as ocorrências não mudou o dia');
    AlphaTest::same('15:30:00', (string) $rescheduled['hora_inicio'], 'Reagendamento de todas as ocorrências não mudou o horário');
    AlphaTest::same('17:00:00', (string) $rescheduled['hora_fim'], 'Reagendamento não preservou a duração do treino');
    AlphaTest::throws(fn() => cronogramaAlterarOcorrencia($pdo, $a, $workout, '2026-09-01', '2026-09-03', 'this', '25:00'), 'Horário inválido foi aceito');
    AlphaTest::assert(!cronogramaExcluirTreino($pdo, $workout, $b), 'Usuário excluiu treino alheio');
    AlphaTest::assert(cronogramaExcluir($pdo, $schedule, $a), 'Cronograma não foi excluído');
};
