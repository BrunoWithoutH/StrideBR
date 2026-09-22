<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/function/api_v1.php';

return function (PDO $pdo): void {
    $owner = alphaTestUser($pdo, 'training-platform-owner');
    $other = alphaTestUser($pdo, 'training-platform-other');
    $sportStmt = $pdo->query("SELECT idmodalidade,slug FROM modalidades WHERE ativo=TRUE AND familia_hub='strength' ORDER BY nome LIMIT 1");
    $sport = $sportStmt->fetch();
    if (!$sport) $sport = $pdo->query("SELECT idmodalidade,slug FROM modalidades WHERE ativo=TRUE ORDER BY nome LIMIT 1")->fetch();
    AlphaTest::assert((bool) $sport, 'Modalidade indisponível para editor mobile');
    $sportId = (string) $sport['idmodalidade'];
    $sportSlug = (string) $sport['slug'];

    $exerciseA = stridebr_api_training_exercise_create($pdo, $owner, ['name' => 'Supino Alpha', 'sport_ids' => [$sportId]]);
    $exerciseB = stridebr_api_training_exercise_create($pdo, $owner, ['name' => 'Agachamento Alpha', 'sport_ids' => [$sportId]]);
    AlphaTest::assert(!empty($exerciseA['custom']) && !empty($exerciseB['custom']), 'Exercícios pessoais devem usar domínio Web existente');
    AlphaTest::same([], stridebr_api_training_exercise($pdo, $other, (string) $exerciseA['id']), 'Exercício pessoal não pode vazar para outro usuário');
    $catalog = stridebr_api_training_exercises($pdo, $owner, ['q' => 'Supino Alpha', 'sport' => $sportSlug, 'limit' => 10]);
    AlphaTest::assert((bool) array_filter($catalog['data'], static fn(array $row): bool => $row['id'] === $exerciseA['id']), 'Busca do catálogo deve localizar exercício do usuário');

    $structure = ['exercises' => [
        ['exercise_id' => $exerciseA['id'], 'sets' => 4, 'repetitions' => '10', 'load' => '70 kg', 'rest_s' => 90, 'block' => 'A', 'rpe' => 7],
        ['exercise_id' => $exerciseB['id'], 'sets' => 4, 'repetitions' => '8', 'load' => '100 kg', 'rest_s' => 120, 'block' => 'A'],
    ]];
    $template = stridebr_api_training_template_save($pdo, $owner, ['title' => 'Academia Alpha', 'sport' => $sportSlug, 'structure' => $structure]);
    AlphaTest::same(2, (int) $template['structure']['exercise_count'], 'Template precisa persistir estrutura completa');
    AlphaTest::same('A', $template['structure']['exercises'][0]['block'], 'Template deve preservar bloco');
    $templates = stridebr_api_workout_templates($pdo, $owner, ['q' => 'Academia Alpha', 'sport' => $sportSlug]);
    AlphaTest::assert((bool) array_filter($templates['data'], static fn(array $row): bool => $row['id'] === $template['id']), 'Biblioteca deve aceitar busca e filtro por modalidade');

    $workoutPayload = [
        'title' => 'Treino direto no calendário', 'sport' => $sportSlug, 'date' => '2026-09-20', 'time' => '18:00', 'planned_duration_s' => 3600, 'structure' => $structure,
    ];
    $createdWorkout = stridebr_api_training_workout_create($pdo, $owner, $workoutPayload, 'training-platform-workout-idem-1');
    $workout = $createdWorkout['workout'];
    $replayedWorkout = stridebr_api_training_workout_create($pdo, $owner, $workoutPayload, 'training-platform-workout-idem-1');
    AlphaTest::assert(!empty($replayedWorkout['reused']), 'Retry de criação com mesma chave deve reutilizar workout.');
    AlphaTest::same((string) $workout['id'], (string) $replayedWorkout['workout']['id'], 'Retry idempotente deve retornar o mesmo workout.');
    AlphaTest::same(2, (int) $workout['structure']['exercise_count'], 'Workout pessoal deve aceitar editor estrutural sem template');
    AlphaTest::same(90, (int) $workout['structure']['exercises'][0]['rest_s'], 'Editor deve normalizar descanso em segundos no contrato');
    AlphaTest::assert(!empty($workout['capabilities']['can_start_session']), 'Workout estruturado deve habilitar sessão');
    AlphaTest::assert(!empty($workout['capabilities']['can_edit']), 'Workout pessoal deve ser editável');

    $beforeCount = (int) $pdo->query("SELECT COUNT(*) FROM treinos_agendados WHERE idatleta=" . $pdo->quote($owner))->fetchColumn();
    AlphaTest::throws(fn() => stridebr_api_workout_create($pdo, $owner, ['title' => 'Rollback Alpha', 'sport' => $sportSlug, 'date' => '2026-09-21', 'structure' => ['exercises' => [['exercise_id' => 'missing-exercise', 'sets' => 4]]]]), 'Editor deve rejeitar exercício inválido');
    $afterCount = (int) $pdo->query("SELECT COUNT(*) FROM treinos_agendados WHERE idatleta=" . $pdo->quote($owner))->fetchColumn();
    AlphaTest::same($beforeCount, $afterCount, 'Falha estrutural deve reverter criação inteira');

    $version = (string) ($workout['version'] ?? '');
    $updated = stridebr_api_workout_update($pdo, $owner, (string) $workout['id'], [
        'if_version' => $version,
        'structure' => ['exercises' => [
            ['exercise_id' => $exerciseB['id'], 'sets' => 5, 'repetitions' => '6', 'load' => '110 kg', 'rest_s' => 150, 'block' => 'Força'],
            ['exercise_id' => $exerciseA['id'], 'sets' => 3, 'repetitions' => '12', 'load' => '65 kg', 'rest_s' => 75, 'block' => 'Volume'],
        ]],
    ]);
    AlphaTest::same((string) $exerciseB['id'], (string) $updated['structure']['exercises'][0]['id'], 'PATCH deve reordenar estrutura atomicamente');
    AlphaTest::same(5, (int) $updated['structure']['exercises'][0]['sets'], 'PATCH deve alterar quantidade de séries planejadas');
    if ($version !== '') AlphaTest::throws(fn() => stridebr_api_workout_update($pdo, $owner, (string) $workout['id'], ['if_version' => $version, 'title' => 'Conflito']), 'Versão antiga deve bloquear overwrite concorrente');

    $fromTemplate = stridebr_api_workout_create($pdo, $owner, ['template_id' => $template['id'], 'date' => '2026-09-22']);
    AlphaTest::same(2, (int) $fromTemplate['structure']['exercise_count'], 'Criar por template deve snapshotar exercícios no Core');

    $schedule = stridebr_api_training_schedule_create($pdo, $owner, ['name' => 'Cronograma Alpha']);
    $recurring = stridebr_api_workout_create($pdo, $owner, [
        'title' => 'Academia recorrente', 'sport' => $sportSlug, 'date' => '2026-09-21', 'time' => '07:00', 'planned_duration_s' => 3600,
        'structure' => $structure,
        'recurrence' => ['frequency' => 'weekly', 'interval' => 1, 'schedule_id' => $schedule['id'], 'start_date' => '2026-09-21', 'end_date' => '2026-10-31'],
    ]);
    AlphaTest::same('recurring', $recurring['kind'], 'POST workout deve criar recorrência usando cronograma real');
    AlphaTest::same('weekly', $recurring['recurrence']['frequency'], 'Contrato deve explicitar recorrência semanal real');
    AlphaTest::assert(!empty($recurring['permissions']['can_edit']), 'Ocorrência recorrente própria deve expor edição permitida');
    $recurringParsed = stridebr_api_workout_parse_id((string) $recurring['id']);
    $recurringBaseId = (string) $recurringParsed['id'];
    $pacerId = stridebr_api_id();
    $routeId = stridebr_api_id();
    $pdo->prepare("INSERT INTO pacer_plans (idplan,idusuario,idmodalidade,name,strategy,target_distance_m,target_time_s,target_average_pace_s_per_km) VALUES (:id,:user,:sport,'Pacer recurrence alpha','even',5000,1800,360)")->execute([':id'=>$pacerId, ':user'=>$owner, ':sport'=>$sportId]);
    $pdo->prepare("INSERT INTO rotas_salvas (idrota_salva,idusuario,nome,idmodalidade,coordenadas) VALUES (:id,:user,'Route recurrence alpha',:sport,'[]'::jsonb)")->execute([':id'=>$routeId, ':user'=>$owner, ':sport'=>$sportId]);
    $pdo->prepare('UPDATE treinos_cronograma SET idpacerplan=:pacer,idrota_salva=:route WHERE idtreino=:id')->execute([':pacer'=>$pacerId, ':route'=>$routeId, ':id'=>$recurringBaseId]);
    $recurringUpdated = stridebr_api_workout_update($pdo, $owner, (string) $recurring['id'], ['scope' => 'all', 'title' => 'Academia recorrente editada']);
    AlphaTest::same('Academia recorrente editada', $recurringUpdated['title'], 'PATCH scope=all deve reutilizar engine Web de recorrência');
    $recurringRowStmt = $pdo->prepare('SELECT vigencia_inicio,vigencia_fim,idpacerplan,idrota_salva FROM treinos_cronograma WHERE idtreino=:id');
    $recurringRowStmt->execute([':id'=>$recurringBaseId]);
    $recurringRow = $recurringRowStmt->fetch();
    AlphaTest::same('2026-09-21', (string) ($recurringRow['vigencia_inicio'] ?? ''), 'PATCH scope=all precisa preservar vigencia_inicio original.');
    AlphaTest::same('2026-10-31', (string) ($recurringRow['vigencia_fim'] ?? ''), 'PATCH scope=all precisa preservar vigencia_fim original.');
    AlphaTest::same($pacerId, (string) ($recurringRow['idpacerplan'] ?? ''), 'PATCH parcial scope=all não pode remover Pacer existente.');
    AlphaTest::same($routeId, (string) ($recurringRow['idrota_salva'] ?? ''), 'PATCH parcial scope=all não pode remover Route existente.');
    $originalOccurrence = stridebr_api_workout_detail($pdo, $owner, (string) $recurring['id']);
    AlphaTest::same('Academia recorrente editada', (string) ($originalOccurrence['title'] ?? ''), 'Occurrence original precisa continuar resolvendo após PATCH scope=all.');

    $range = stridebr_api_workout_schedule($pdo, $owner, ['from' => '2026-09-20', 'to' => '2026-10-20']);
    AlphaTest::assert((bool) array_filter($range['data'], static fn(array $item): bool => $item['id'] === $workout['id']), 'Range mensal deve conter workout pessoal');
    $summary = array_values(array_filter($range['data'], static fn(array $item): bool => $item['id'] === $workout['id']))[0];
    AlphaTest::assert(isset($summary['capabilities']) && !array_key_exists('structure', $summary), 'Calendário deve trazer capabilities leves sem árvore estrutural');

    $history = stridebr_api_training_exercise_history($pdo, $owner, (string) $exerciseA['id'], ['limit' => 5]);
    AlphaTest::same([], $history['data'], 'Histórico vazio deve ser válido antes de execuções');

    $otherWorkout = stridebr_api_workout_create($pdo, $other, ['title' => 'Outro workout', 'sport' => $sportSlug, 'date' => '2026-09-20']);
    AlphaTest::same([], stridebr_api_workout_detail($pdo, $owner, (string) $otherWorkout['id']), 'Workout de outro usuário não pode vazar');
};
