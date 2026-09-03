<?php
return function (PDO $pdo): void {
    $route = atividadeValidarRotaGeoJson('{"type":"LineString","coordinates":[[-51.23,-30.03],[-51.22,-30.02]]}', false);
    AlphaTest::same(2, count($route['coordinates']), 'GeoJSON válido não foi aceito');
    AlphaTest::assert(atividadeDistanciaRota($route['coordinates']) > 1000, 'Distância backend não foi calculada');
    AlphaTest::throws(fn() => atividadeValidarRotaGeoJson('{x}', false), 'JSON inválido foi aceito');
    AlphaTest::throws(fn() => atividadeValidarRotaGeoJson(['type' => 'LineString', 'coordinates' => [[0, 0]]], false), 'Rota de um ponto foi aceita');
    AlphaTest::throws(fn() => atividadeValidarRotaGeoJson(['type' => 'LineString', 'coordinates' => [[181, 0], [0, 0]]], false), 'Longitude inválida foi aceita');
    AlphaTest::throws(fn() => atividadeValidarRotaGeoJson(['type' => 'LineString', 'coordinates' => [[0, 91], [0, 0]]], false), 'Latitude inválida foi aceita');

    $oversized = ['type' => 'LineString', 'coordinates' => array_fill(0, ATIVIDADE_ROTA_MAX_PONTOS + 1, [0, 0])];
    AlphaTest::throws(fn() => atividadeValidarRotaGeoJson($oversized, false), 'Rota com pontos demais foi aceita');

    $user = alphaTestUser($pdo, 'route_owner');
    $other = alphaTestUser($pdo, 'route_other');
    $id = atividadeSalvarRegistro($pdo, $user, [
        'idmodelo' => alphaTestRouteModel($pdo),
        'titulo' => 'Rota alpha',
        'data_inicio' => '2026-08-25 08:00',
        'status' => 'concluido',
        'visibilidade' => 'privado',
        'rota_coordenadas' => $route,
    ]);
    $stmt = $pdo->prepare('SELECT distancia_metros, coordenadas FROM rotas_atividade WHERE idregistro = :id');
    $stmt->execute([':id' => $id]);
    $saved = $stmt->fetch();
    AlphaTest::assert(is_array($saved), 'Rota não foi persistida');
    AlphaTest::assert((float) $saved['distancia_metros'] > 1000, 'Distância da rota não foi persistida');
    AlphaTest::same([], atividadeCarregarRegistro($pdo, $id, $other), 'Rota/atividade vazou para outro usuário');

    atividadeSalvarRegistro($pdo, $user, [
        'idmodelo' => alphaTestRouteModel($pdo),
        'titulo' => 'Sem rota',
        'data_inicio' => '2026-08-25 08:00',
        'status' => 'concluido',
        'visibilidade' => 'privado',
        'rota_coordenadas' => '',
    ], $id);
    $stmt->execute([':id' => $id]);
    AlphaTest::assert(!$stmt->fetch(), 'Remover a rota na edição não apagou o registro');

    atividadeSalvarRegistro($pdo, $user, [
        'idmodelo' => alphaTestRouteModel($pdo),
        'titulo' => 'Rota novamente',
        'data_inicio' => '2026-08-25 08:00',
        'status' => 'concluido',
        'visibilidade' => 'privado',
        'rota_coordenadas' => $route,
    ], $id);
    AlphaTest::assert(atividadeExcluirRegistro($pdo, $id, $user), 'Atividade com rota não foi excluída');
    $stmt->execute([':id' => $id]);
    AlphaTest::assert((bool) $stmt->fetch(), 'Soft delete removeu a rota antes da janela de restauração');
    AlphaTest::same([], atividadeCarregarRegistro($pdo, $id, $user), 'Atividade excluída continuou visível ao proprietário');
    AlphaTest::assert(atividadeRestaurarRegistro($pdo, $id, $user), 'Atividade com rota não pôde ser restaurada');
    $stmt->execute([':id' => $id]);
    AlphaTest::assert((bool) $stmt->fetch(), 'Restaurar atividade não preservou a rota');
    AlphaTest::assert(atividadeExcluirRegistro($pdo, $id, $user), 'Atividade restaurada não pôde ser excluída novamente');
};
