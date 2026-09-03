<?php
require_once dirname(__DIR__, 2) . '/src/function/strength_activity.php';
require_once dirname(__DIR__, 2) . '/src/function/activity_energy.php';
return function (PDO $pdo): void {
    $a = alphaTestUser($pdo, 'activity_a');
    $b = alphaTestUser($pdo, 'activity_b');
    $pdo->prepare('UPDATE usuarios SET pesousuario = 70 WHERE idusuario = :usuario')->execute([':usuario' => $a]);
    $model = alphaTestGeneralModel($pdo);
    $id = atividadeSalvarRegistro($pdo, $a, ['idmodelo' => $model, 'titulo' => 'Alpha activity', 'data_inicio' => '2026-08-25 08:00', 'status' => 'concluido', 'visibilidade' => 'privado', 'esforco_percebido' => '7']);
    AlphaTest::same('Alpha activity', atividadeCarregarRegistro($pdo, $id, $a)['titulo'], 'Atividade não foi criada');
    atividadeSalvarRegistro($pdo, $a, ['idmodelo' => $model, 'titulo' => 'Editada', 'data_inicio' => '2026-08-25 08:00', 'status' => 'concluido', 'visibilidade' => 'privado'], $id);
    AlphaTest::same('Editada', atividadeCarregarRegistro($pdo, $id, $a)['titulo'], 'Atividade não foi editada');

    // Registro manual deve aceitar dados parciais. Distância + duração são suficientes,
    // e até uma atividade sem métricas deve poder ser salva para completar depois.
    $partialModel = alphaTestRouteModel($pdo);
    $partialFields = atividadeBuscarCamposModelo($pdo, $partialModel, true);
    $partialValues = [];
    foreach ($partialFields as $field) {
        if (($field['escopo'] ?? '') !== 'unidade') continue;
        $slug = strtolower((string) ($field['slug'] ?? ''));
        if ($slug === 'distancia') $partialValues[(string) $field['idcampo']] = '2';
        if ($slug === 'duracao') $partialValues[(string) $field['idcampo']] = '00:20:00';
    }
    $partialId = atividadeSalvarRegistro($pdo, $a, [
        'idmodelo' => $partialModel,
        'titulo' => 'Parcial distância e duração',
        'data_inicio' => '2026-08-25 09:00',
        'status' => 'concluido',
        'visibilidade' => 'privado',
        'permitir_campos_vazios' => true,
        'unidades' => [['values' => $partialValues]],
    ]);
    $partialUnitStmt = $pdo->prepare('SELECT distancia_metros, duracao_segundos FROM unidades_atividade WHERE idregistro = :id ORDER BY ordem LIMIT 1');
    $partialUnitStmt->execute([':id' => $partialId]);
    $partialUnit = $partialUnitStmt->fetch();
    AlphaTest::same('2000.000', (string) ($partialUnit['distancia_metros'] ?? ''), 'Distância parcial não foi preservada na unidade');
    AlphaTest::same(1200, (int) ($partialUnit['duracao_segundos'] ?? -1), 'Duração parcial não foi preservada na unidade');

    $emptyId = atividadeSalvarRegistro($pdo, $a, [
        'idmodelo' => $partialModel,
        'titulo' => 'Parcial vazio',
        'data_inicio' => '2026-08-25 09:30',
        'status' => 'concluido',
        'visibilidade' => 'privado',
        'permitir_campos_vazios' => true,
        'unidades' => [['values' => []]],
    ]);
    AlphaTest::assert($emptyId !== '', 'Atividade manual vazia não pôde ser salva');
    $strengthModelStmt = $pdo->query("SELECT idmodelo FROM modelos_modalidade WHERE idmodalidade = 'm_musculacao' AND ativo = TRUE ORDER BY padrao DESC, versao DESC LIMIT 1");
    $strengthModel = $strengthModelStmt->fetchColumn();
    AlphaTest::assert(is_string($strengthModel) && $strengthModel !== '', 'Modelo de musculação não encontrado');
    $strengthId = atividadeSalvarRegistro($pdo, $a, [
        'idmodelo' => (string) $strengthModel,
        'titulo' => 'Treino de pernas',
        'data_inicio' => '2026-08-25 18:00',
        'data_fim' => '2026-08-25 19:00',
        'status' => 'concluido',
        'visibilidade' => 'privado',
        'esforco_percebido' => '8',
        'permitir_campos_vazios' => true,
    ]);
    atividadeForcaPersistirSeriesManuais($pdo, $a, $strengthId, [[
        'nome' => 'Leg press',
        'series' => [
            ['tipo' => 'aquecimento', 'carga_kg' => '100', 'repeticoes' => '12', 'rir' => '4', 'concluida' => '1'],
            ['tipo' => 'trabalho', 'carga_kg' => '160', 'repeticoes' => '8', 'rir' => '2', 'concluida' => '0'],
        ],
    ]]);
    $strengthRowsStmt = $pdo->prepare('SELECT carga_kg, repeticoes, concluida FROM series_exercicio_atividade WHERE idregistro = :registro ORDER BY ordem_serie');
    $strengthRowsStmt->execute([':registro' => $strengthId]);
    $strengthRows = $strengthRowsStmt->fetchAll();
    AlphaTest::same(2, count($strengthRows), 'Séries manuais não foram persistidas');
    AlphaTest::same('100.000', (string) $strengthRows[0]['carga_kg'], 'Carga manual foi salva incorretamente');
    AlphaTest::assert(stridebr_db_bool($strengthRows[0]['concluida']) && !stridebr_db_bool($strengthRows[1]['concluida']), 'Booleano de série manual foi salvo incorretamente no PostgreSQL');
    $loadedStrength = atividadeForcaBuscarSeries($pdo, $a, $strengthId);
    AlphaTest::same('Leg press', (string) ($loadedStrength[0]['nome'] ?? ''), 'Histórico manual de força não pôde ser recarregado');
    atividadeEnergiaAtualizarRegistro($pdo, $strengthId, $a);
    $strengthEnergy = $pdo->prepare('SELECT calorias_ativas_estimadas FROM registros_atividade WHERE idregistro = :registro');
    $strengthEnergy->execute([':registro' => $strengthId]);
    AlphaTest::assert((float) $strengthEnergy->fetchColumn() > 0, 'Séries manuais não alimentaram a estimativa de energia');
    AlphaTest::throws(fn() => atividadeSalvarRegistro($pdo, $b, ['idmodelo' => $model, 'data_inicio' => '2026-08-25 08:00'], $id), 'Outro usuário editou atividade alheia');
    AlphaTest::throws(fn() => atividadeSalvarRegistro($pdo, $a, ['idmodelo' => $model, 'data_inicio' => 'inválida']), 'Data inválida foi aceita');
    $bulkA = atividadeSalvarRegistro($pdo, $a, ['idmodelo' => $model, 'titulo' => 'Bulk A', 'data_inicio' => '2026-08-26 10:00', 'data_fim' => '2026-08-26 11:15', 'status' => 'concluido', 'visibilidade' => 'privado']);
    $bulkB = atividadeSalvarRegistro($pdo, $a, ['idmodelo' => $model, 'titulo' => 'Bulk B', 'data_inicio' => '2026-08-26 12:00', 'data_fim' => '2026-08-26 12:45', 'status' => 'concluido', 'visibilidade' => 'privado']);
    atividadeAtualizarRegistrosEmLote($pdo, $a, [$bulkA, $bulkB], ['idmodalidade' => 'm_musculacao', 'duracao_modo' => 'keep']);
    $bulkStmt = $pdo->prepare('SELECT idregistro, idmodalidade, data_inicio, data_fim FROM registros_atividade WHERE idregistro IN (:a, :b) ORDER BY idregistro');
    $bulkStmt->execute([':a' => $bulkA, ':b' => $bulkB]);
    $bulkRows = [];
    foreach ($bulkStmt->fetchAll() as $row) $bulkRows[(string) $row['idregistro']] = $row;
    AlphaTest::same('m_musculacao', (string) $bulkRows[$bulkA]['idmodalidade'], 'Edição em lote não alterou a modalidade');
    AlphaTest::same(4500, (new DateTimeImmutable((string) $bulkRows[$bulkA]['data_fim']))->getTimestamp() - (new DateTimeImmutable((string) $bulkRows[$bulkA]['data_inicio']))->getTimestamp(), 'Trocar modalidade apagou ou alterou a duração existente');
    AlphaTest::same(2700, (new DateTimeImmutable((string) $bulkRows[$bulkB]['data_fim']))->getTimestamp() - (new DateTimeImmutable((string) $bulkRows[$bulkB]['data_inicio']))->getTimestamp(), 'Trocar modalidade alterou a duração da segunda atividade');
    atividadeAtualizarRegistrosEmLote($pdo, $a, [$bulkA, $bulkB], ['duracao_modo' => 'set', 'duracao_minutos' => '120']);
    $durationStmt = $pdo->prepare('SELECT data_inicio, data_fim FROM registros_atividade WHERE idregistro = :id');
    $durationStmt->execute([':id' => $bulkA]);
    $durationRow = $durationStmt->fetch();
    AlphaTest::same(7200, (new DateTimeImmutable((string) $durationRow['data_fim']))->getTimestamp() - (new DateTimeImmutable((string) $durationRow['data_inicio']))->getTimestamp(), 'Duração em lote não foi aplicada');
    atividadeAtualizarRegistrosEmLote($pdo, $a, [$bulkA], ['duracao_modo' => 'clear']);
    $durationStmt->execute([':id' => $bulkA]);
    $clearedDuration = $durationStmt->fetch();
    AlphaTest::same(null, $clearedDuration['data_fim'], 'Limpar duração em lote não removeu data_fim');
    atividadeExcluirRegistro($pdo, $partialId, $a);
    atividadeExcluirRegistro($pdo, $strengthId, $a);
    atividadeExcluirRegistro($pdo, $emptyId, $a);
    atividadeExcluirRegistro($pdo, $bulkA, $a);
    atividadeExcluirRegistro($pdo, $bulkB, $a);
    AlphaTest::assert(!atividadeExcluirRegistro($pdo, $id, $b), 'Outro usuário excluiu atividade alheia');
    AlphaTest::assert(atividadeExcluirRegistro($pdo, $id, $a), 'Owner não excluiu atividade');
};
