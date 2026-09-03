<?php
return function (PDO $pdo): void {
    $user = alphaTestUser($pdo, 'session_a');
    $other = alphaTestUser($pdo, 'session_b');
    $schedule = cronogramaCriar($pdo, $user, 'Sessão');
    $workout = cronogramaSalvarTreino($pdo, $user, [
        'idcronograma' => $schedule,
        'titulo' => 'Treino sessão',
        'dia_semana' => 1,
        'hora_inicio' => '08:00',
        'hora_fim' => '09:00',
    ]);

    $session = stridebr_generate_id();
    $insert = $pdo->prepare("INSERT INTO sessoes_treino (idsessao, idusuario, idcronograma_origem, idtreino_origem, titulo_snapshot) VALUES (:id, :usuario, :cronograma, :treino, 'Treino sessão')");
    $insert->execute([':id' => $session, ':usuario' => $user, ':cronograma' => $schedule, ':treino' => $workout]);
    AlphaTest::throws(fn() => $insert->execute([':id' => stridebr_generate_id(), ':usuario' => $user, ':cronograma' => $schedule, ':treino' => $workout]), 'Usuário conseguiu criar duas sessões ativas');

    $otherSession = stridebr_generate_id();
    $insert->execute([':id' => $otherSession, ':usuario' => $other, ':cronograma' => null, ':treino' => null]);
    AlphaTest::assert($otherSession !== '', 'Outro usuário não conseguiu ter sessão própria');

    $exercise = stridebr_generate_id();
    $pdo->prepare("INSERT INTO sessoes_treino_exercicios (idsessao_exercicio, idsessao, nome_snapshot, series_planejadas, ordem) VALUES (:id, :sessao, 'Agachamento', 2, 1)")
        ->execute([':id' => $exercise, ':sessao' => $session]);
    $setInsert = $pdo->prepare('INSERT INTO sessoes_treino_series (idserie, idsessao_exercicio, numero) VALUES (:id, :exercicio, :numero)');
    $setInsert->execute([':id' => stridebr_generate_id(), ':exercicio' => $exercise, ':numero' => 1]);
    $setInsert->execute([':id' => stridebr_generate_id(), ':exercicio' => $exercise, ':numero' => 2]);
    AlphaTest::throws(fn() => $setInsert->execute([':id' => stridebr_generate_id(), ':exercicio' => $exercise, ':numero' => 2]), 'Número de série duplicado foi aceito');

    $pdo->prepare("UPDATE sessoes_treino SET status = 'cancelado', data_fim = NOW() WHERE idsessao = :id AND idusuario = :usuario")
        ->execute([':id' => $session, ':usuario' => $user]);
    $replacement = stridebr_generate_id();
    $insert->execute([':id' => $replacement, ':usuario' => $user, ':cronograma' => $schedule, ':treino' => $workout]);
    AlphaTest::assert($replacement !== '', 'Cancelar sessão não liberou nova sessão ativa');

    $pdo->beginTransaction();
    try {
        $id = atividadeSalvarRegistro($pdo, $user, [
            'idmodelo' => alphaTestGeneralModel($pdo),
            'titulo' => 'Sessão concluída',
            'data_inicio' => '2026-08-25 08:00',
            'data_fim' => '2026-08-25 09:00',
            'status' => 'concluido',
            'visibilidade' => 'privado',
        ]);
        AlphaTest::assert($pdo->inTransaction(), 'Salvar atividade encerrou transação externa da sessão');
        $pdo->rollBack();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM registros_atividade WHERE idregistro = :id');
    $stmt->execute([':id' => $id]);
    AlphaTest::assert(!$stmt->fetchColumn(), 'Rollback da conclusão deixou atividade órfã');
};
