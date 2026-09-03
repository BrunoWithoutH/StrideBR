<?php
return function (PDO $pdo): void {
    $a = alphaTestUser($pdo, 'share_a');
    $b = alphaTestUser($pdo, 'share_b');
    $c = alphaTestUser($pdo, 'share_c');

    $friend = $pdo->prepare("INSERT INTO amizades (idamizade, idusuario_solicitante, idusuario_destino, status) VALUES (:id, :a, :b, 'aceita')");
    $friend->execute([':id' => stridebr_generate_id(), ':a' => $a, ':b' => $b]);

    $original = cronogramaCriar($pdo, $a, 'Original');
    $workout = cronogramaSalvarTreino($pdo, $a, [
        'idcronograma' => $original,
        'titulo' => 'Treino original',
        'codigo' => 'A1',
        'foco' => 'Força',
        'dia_semana' => 1,
        'hora_inicio' => '08:00',
        'hora_fim' => '09:00',
    ]);
    cronogramaSalvarExercicios($pdo, $workout, $a, [[
        'nome' => 'Exercício manual',
        'series' => '3',
        'repeticoes' => '8-10',
        'carga' => '40 kg',
        'descanso' => '90 s',
    ]], []);

    AlphaTest::throws(fn() => compartilhamentoEnviarSnapshot($pdo, $a, $original, $c), 'Compartilhamento para não-amigo foi aceito');
    $share = compartilhamentoEnviarSnapshot($pdo, $a, $original, $b);
    AlphaTest::throws(fn() => compartilhamentoEnviarSnapshot($pdo, $a, $original, $b), 'Compartilhamento pendente duplicado foi aceito');

    $statusStmt = $pdo->prepare('SELECT status FROM cronograma_compartilhamentos WHERE idcompartilhamento = :id');
    $statusStmt->execute([':id' => $share]);
    AlphaTest::same('pendente', $statusStmt->fetchColumn(), 'Compartilhamento não ficou pendente');
    AlphaTest::throws(fn() => compartilhamentoAceitarSnapshot($pdo, $c, $share), 'Terceiro aceitou compartilhamento alheio');

    $copy = compartilhamentoAceitarSnapshot($pdo, $b, $share);
    $statusStmt->execute([':id' => $share]);
    AlphaTest::same('aceito', $statusStmt->fetchColumn(), 'Compartilhamento não foi marcado como aceito');
    $copyWorkouts = cronogramaListarTreinos($pdo, $copy, $b);
    AlphaTest::same(1, count($copyWorkouts), 'Cópia não recebeu o treino');
    AlphaTest::same('A1', (string) ($copyWorkouts[0]['codigo'] ?? ''), 'Código do treino se perdeu no snapshot');
    AlphaTest::same('Força', (string) ($copyWorkouts[0]['foco'] ?? ''), 'Foco do treino se perdeu no snapshot');
    AlphaTest::same(1, count(cronogramaListarTreinoExercicios($pdo, (string) $copyWorkouts[0]['idtreino'], $b)), 'Cópia não recebeu exercícios');
    AlphaTest::throws(fn() => compartilhamentoAceitarSnapshot($pdo, $b, $share), 'Compartilhamento já aceito foi aceito novamente');

    cronogramaSalvarTreino($pdo, $a, ['idcronograma' => $original, 'titulo' => 'Novo no original', 'dia_semana' => 2, 'hora_inicio' => '08:00', 'hora_fim' => '09:00']);
    AlphaTest::same(1, count(cronogramaListarTreinos($pdo, $copy, $b)), 'Cópia foi alterada junto com original');
    cronogramaSalvarTreino($pdo, $b, ['idcronograma' => $copy, 'titulo' => 'Novo na cópia', 'dia_semana' => 3, 'hora_inicio' => '08:00', 'hora_fim' => '09:00']);
    AlphaTest::same(2, count(cronogramaListarTreinos($pdo, $original, $a)), 'Original foi alterado junto com cópia');
    cronogramaExcluir($pdo, $original, $a);
    AlphaTest::assert(cronogramaBuscar($pdo, $copy, $b) !== [], 'Excluir original removeu a cópia');

    $originalReject = cronogramaCriar($pdo, $a, 'Recusar');
    $rejectShare = compartilhamentoEnviarSnapshot($pdo, $a, $originalReject, $b);
    compartilhamentoRecusarSnapshot($pdo, $b, $rejectShare);
    $statusStmt->execute([':id' => $rejectShare]);
    AlphaTest::same('recusado', $statusStmt->fetchColumn(), 'Recusa não atualizou o compartilhamento');
    AlphaTest::throws(fn() => compartilhamentoAceitarSnapshot($pdo, $b, $rejectShare), 'Compartilhamento recusado pôde ser aceito');

    AlphaTest::throws(fn() => compartilhamentoImportarSnapshot($pdo, $b, ['format' => 'stridebr-schedule', 'version' => 1, 'cronograma' => ['nome' => 'Grande'], 'treinos' => array_fill(0, 101, [])]), 'Importação excessiva foi aceita');
};
