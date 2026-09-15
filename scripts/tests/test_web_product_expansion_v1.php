<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/function/api_v1.php';
require_once dirname(__DIR__, 2) . '/src/function/api_training_platform.php';
require_once dirname(__DIR__, 2) . '/src/function/competitions.php';
require_once dirname(__DIR__, 2) . '/src/function/routes.php';
require_once dirname(__DIR__, 2) . '/src/function/sport_hub.php';

return function (PDO $pdo): void {
    $owner = alphaTestUser($pdo, 'web-product-owner');
    $other = alphaTestUser($pdo, 'web-product-other');
    $started = new DateTimeImmutable('2026-09-14T07:00:00-03:00');
    $activity = stridebr_api_create_activity($pdo, $owner, [
        'sport' => 'corrida',
        'title' => 'Activity Web Product',
        'visibility' => 'privado',
        'started_at' => $started->format(DateTimeInterface::ATOM),
        'ended_at' => $started->modify('+30 minutes')->format(DateTimeInterface::ATOM),
        'metrics' => ['distance_m' => 5000, 'duration_s' => 1800, 'elevation_gain_m' => 45],
        'gps' => ['points' => [
            ['lat' => -27.358, 'lon' => -53.398, 'timestamp_ms' => 1789376400000],
            ['lat' => -27.350, 'lon' => -53.390, 'timestamp_ms' => 1789378200000],
        ]],
    ], 'web-product-activity-1');
    $activityId = (string) $activity['id'];

    $route = routeSavedCreateFromActivity($pdo, $owner, $activityId, 'Rota Alpha');
    AlphaTest::same('Rota Alpha', (string) $route['nome'], 'Salvar rota deve criar Route reutilizável a partir da Activity');
    AlphaTest::same($activityId, (string) $route['idatividade_origem'], 'Route precisa preservar somente referência da Activity de origem');
    AlphaTest::same(null, routeSavedGet($pdo, $other, (string) $route['idrota_salva']), 'Route deve ser owner-scoped');
    AlphaTest::same((string) $route['idrota_salva'], (string) routeSavedValidateForWorkout($pdo, $owner, (string) $route['idrota_salva'], (string) $route['idmodalidade']), 'Route compatível deve poder ser usada em Workout');
    $reusedRoute = routeSavedCreateFromActivity($pdo, $owner, $activityId, 'Outra cópia');
    AlphaTest::same((string) $route['idrota_salva'], (string) $reusedRoute['idrota_salva'], 'Salvar a mesma Activity de novo não deve duplicar Route');

    AlphaTest::same(true, atividadeDefinirExclusaoEstatisticas($pdo, $owner, $activityId, true), 'Activity precisa poder sair das estatísticas sem ser excluída');
    $stillVisible = stridebr_api_activity_detail($pdo, $activityId, $owner);
    AlphaTest::same($activityId, (string) $stillVisible['id'], 'Activity fora das estatísticas precisa continuar abrindo normalmente');
    AlphaTest::same([], sportHubActivityRowsQuery($pdo, $owner, null, null, [$activityId]), 'Sport Hub deve ignorar Activity marcada fora das estatísticas');
    atividadeDefinirExclusaoEstatisticas($pdo, $owner, $activityId, false);
    AlphaTest::same(1, count(sportHubActivityRowsQuery($pdo, $owner, null, null, [$activityId])), 'Reincluir Activity deve devolvê-la aos agregados');

    $equipmentId = atividadeSalvarEquipamento($pdo, $owner, [
        'nome' => 'Corre Alpha', 'categoria' => 'tenis', 'data_inicio_uso' => '2026-09-01', 'limite_alerta_km' => '600',
    ]);
    $pdo->prepare('INSERT INTO registros_atividade_equipamentos (idregistro,idequipamento) VALUES (:registro,:equipamento)')->execute([':registro' => $activityId, ':equipamento' => $equipmentId]);
    $equipment = atividadeDetalheEquipamento($pdo, $owner, $equipmentId);
    AlphaTest::same(1, (int) $equipment['total_atividades'], 'Equipment detail precisa contar Activities associadas');
    AlphaTest::assert((float) $equipment['distancia_total_km'] > 0, 'Equipment detail precisa acumular distância objetiva');
    AlphaTest::same('600.000', (string) $equipment['limite_alerta_km'], 'Limite configurável deve ser persistido sem inferir desgaste');
    AlphaTest::same([], atividadeDetalheEquipamento($pdo, $other, $equipmentId), 'Equipment detail deve respeitar owner');

    $competition = competitionCreate($pdo, $owner, [
        'nome' => '10 km Alpha', 'data_inicio' => '2026-10-18', 'status' => 'planejada', 'participacao_status' => 'inscrito', 'url_oficial' => 'https://example.test/evento',
    ]);
    competitionLinkActivity($pdo, $owner, $activityId, (string) $competition['idcompeticao']);
    $competition = competitionUpdate($pdo, $owner, (string) $competition['idcompeticao'], [
        'status' => 'realizada', 'participacao_status' => 'participou', 'resultado_tempo_s' => '3120', 'resultado_distancia_m' => '10000', 'resultado_posicao_geral' => '42', 'resultado_posicao_categoria' => '8', 'resultado_categoria' => '20-29', 'resultado_medalha' => 'Finisher',
    ]);
    AlphaTest::same('participou', (string) $competition['participacao_status'], 'Competition deve persistir participação realizada');
    AlphaTest::same(3120, (int) $competition['resultado_tempo_s'], 'Competition deve persistir tempo factual');
    $activityCompetition = $pdo->prepare('SELECT idcompeticao FROM registros_atividade WHERE idregistro=:id AND idusuario=:usuario');
    $activityCompetition->execute([':id' => $activityId, ':usuario' => $owner]);
    AlphaTest::same((string) $competition['idcompeticao'], (string) $activityCompetition->fetchColumn(), 'Competition precisa fechar o vínculo Event→Activity');

    $endurance = stridebr_api_training_workout_create($pdo, $owner, [
        'title' => 'Intervalado Alpha', 'sport' => 'corrida', 'date' => '2026-09-20', 'time' => '07:00',
        'structure' => ['exercises' => [
            ['step_type' => 'warmup', 'name' => 'Aquecimento', 'duration_s' => 600, 'target' => ['type' => 'rpe', 'min' => 2, 'max' => 3, 'unit' => 'rpe_1_10']],
            ['step_type' => 'work', 'name' => 'Intervalos', 'distance_m' => 1000, 'repeat_count' => 5, 'target' => ['type' => 'pace', 'min' => 285, 'max' => 295, 'unit' => 's_per_km'], 'recovery' => ['duration_s' => 120]],
            ['step_type' => 'cooldown', 'name' => 'Desaquecimento', 'duration_s' => 600],
        ]],
    ], 'web-product-endurance-1');
    $steps = $endurance['workout']['structure']['exercises'] ?? [];
    AlphaTest::same(3, count($steps), 'Workout endurance precisa persistir passos estruturados sem catálogo de academia');
    AlphaTest::same('warmup', (string) $steps[0]['step_type'], 'Aquecimento deve sobreviver no contrato da Training Platform');
    AlphaTest::same(5, (int) $steps[1]['repeat_count'], 'Intervalo deve preservar repetição de bloco');
    AlphaTest::same('pace', (string) ($steps[1]['target']['type'] ?? ''), 'Intervalo deve preservar target de pace');
    AlphaTest::same(120, (int) ($steps[1]['recovery']['duration_s'] ?? 0), 'Intervalo deve preservar recuperação configurada');
};
