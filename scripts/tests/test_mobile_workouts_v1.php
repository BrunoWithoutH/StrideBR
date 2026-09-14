<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/function/api_v1.php';

return function (PDO $pdo): void {
    AlphaTest::assert((bool) $pdo->query("SELECT to_regclass('stridebr.treinos_agendados')")->fetchColumn(), 'Treinos agendados ausentes');
    AlphaTest::assert((bool) $pdo->query("SELECT to_regclass('stridebr.treinos_modelo')")->fetchColumn(), 'Biblioteca de treinos ausente');
    $owner = alphaTestUser($pdo, 'mobile-workouts-owner');
    $other = alphaTestUser($pdo, 'mobile-workouts-other');
    $trainer = alphaTestUser($pdo, 'mobile-workouts-trainer', ['trainer' => true]);

    $routeModel = alphaTestRouteModel($pdo);
    $routeSportStmt = $pdo->prepare('SELECT m.idmodalidade,m.slug,m.nome FROM modelos_modalidade mm JOIN modalidades m ON m.idmodalidade=mm.idmodalidade WHERE mm.idmodelo=:model LIMIT 1');
    $routeSportStmt->execute([':model' => $routeModel]);
    $routeSport = $routeSportStmt->fetch();
    AlphaTest::assert((bool) $routeSport, 'Modalidade com rota indisponível para treino mobile');
    $sportId = (string) $routeSport['idmodalidade'];
    $sportSlug = (string) $routeSport['slug'];

    $personal = stridebr_api_workout_create($pdo, $owner, [
        'title' => 'Rodagem sem horário',
        'sport' => $sportSlug,
        'date' => '2026-09-10',
        'planned_duration_s' => 3600,
        'planned_distance_m' => 10000,
        'objective' => 'Base aeróbica',
        'intensity' => 'leve',
        'notes' => 'Treino pessoal',
    ]);
    AlphaTest::same('scheduled', $personal['kind'], 'Treino pessoal deve usar ocorrência agendada existente');
    AlphaTest::same(null, $personal['time'], 'Treino sem horário precisa permanecer null');
    AlphaTest::same('publicado', $personal['status'], 'Treino pessoal criado deve usar status real publicado');
    AlphaTest::same('usuario', $personal['source'], 'Origem pessoal precisa ser preservada');
    AlphaTest::same(3600, $personal['planned_duration_s'], 'Duração planejada deve retornar em segundos');
    AlphaTest::same(10000.0, (float) $personal['planned_distance_m'], 'Distância planejada deve retornar em metros');
    AlphaTest::assert(!empty($personal['permissions']['can_edit']), 'Treino pessoal deve ser editável pelo dono');
    AlphaTest::assert(!empty($personal['created_at']) && !empty($personal['updated_at']), 'Detalhe persistido deve expor timestamps estáveis para cache/sync');

    $day = stridebr_api_workout_schedule($pdo, $owner, ['from' => '2026-09-10', 'to' => '2026-09-10']);
    AlphaTest::same('America/Sao_Paulo', $day['meta']['timezone'], 'Calendário deve declarar timezone canônico atual');
    AlphaTest::assert((bool) array_filter($day['data'], static fn(array $item): bool => $item['id'] === $personal['id']), 'Intervalo de um dia deve listar treino pessoal');
    $dayItem = array_values(array_filter($day['data'], static fn(array $item): bool => $item['id'] === $personal['id']))[0];
    AlphaTest::same(null, $dayItem['time'], 'Summary não pode inventar 00:00 para treino sem horário');
    AlphaTest::same(null, $dayItem['template_id'], 'Treino pessoal sem modelo deve expor template_id null');
    AlphaTest::assert(!empty($dayItem['updated_at']), 'Summary deve expor updated_at sem carregar detalhe');
    AlphaTest::assert(!array_key_exists('structure', $dayItem), 'Calendário não pode carregar árvore pesada de estrutura');

    $week = stridebr_api_workout_schedule($pdo, $owner, ['from' => '2026-09-07', 'to' => '2026-09-13']);
    AlphaTest::assert((bool) array_filter($week['data'], static fn(array $item): bool => $item['id'] === $personal['id']), 'Intervalo semanal deve usar o mesmo contrato');
    $month = stridebr_api_workout_schedule($pdo, $owner, ['from' => '2026-09-01', 'to' => '2026-09-30']);
    AlphaTest::assert((bool) array_filter($month['data'], static fn(array $item): bool => $item['id'] === $personal['id']), 'Intervalo mensal deve usar o mesmo contrato');

    $past = stridebr_api_workout_create($pdo, $owner, [
        'title' => 'Passado ainda planejado',
        'sport' => $sportId,
        'date' => '2025-01-10',
    ]);
    $pastSchedule = stridebr_api_workout_schedule($pdo, $owner, ['from' => '2025-01-10', 'to' => '2025-01-10']);
    $pastItem = array_values(array_filter($pastSchedule['data'], static fn(array $item): bool => $item['id'] === $past['id']))[0] ?? [];
    AlphaTest::same('publicado', $pastItem['status'] ?? null, 'Treino passado não pode virar concluído automaticamente');

    $updated = stridebr_api_workout_update($pdo, $owner, $personal['id'], [
        'title' => 'Rodagem reagendada',
        'date' => '2026-09-11',
        'time' => '23:30',
        'planned_duration_s' => 4200,
        'planned_distance_m' => 11000,
    ]);
    AlphaTest::same('2026-09-11', $updated['date'], 'Reagendamento deve alterar a data local sem deslocamento de timezone');
    AlphaTest::same('23:30', $updated['time'], 'Horário local 23:30 precisa permanecer no mesmo dia');
    AlphaTest::same(4200, $updated['planned_duration_s'], 'Edição deve atualizar duração planejada');
    AlphaTest::same(11000.0, (float) $updated['planned_distance_m'], 'Edição deve atualizar distância planejada');

    $noGps = stridebr_api_workout_create($pdo, $owner, ['title' => 'Mobilidade', 'sport' => $sportId, 'date' => '2026-09-12']);
    $completedNoGps = stridebr_api_workout_complete($pdo, $owner, $noGps['id']);
    AlphaTest::same('concluido', $completedNoGps['status'], 'Treino não-GPS deve poder ser concluído sem Activity falsa');
    AlphaTest::same(null, $completedNoGps['activity'], 'Conclusão manual não deve fabricar Activity');

    $toCancel = stridebr_api_workout_create($pdo, $owner, ['title' => 'Treino a cancelar', 'sport' => $sportId, 'date' => '2026-09-13']);
    $cancelled = stridebr_api_workout_cancel($pdo, $owner, $toCancel['id']);
    AlphaTest::same('cancelado', $cancelled['status'], 'Cancelamento lógico deve preservar histórico');

    AlphaTest::same([], stridebr_api_workout_detail($pdo, $other, $personal['id']), 'Usuário B não pode abrir treino do usuário A');
    AlphaTest::throws(fn() => stridebr_api_workout_update($pdo, $other, $personal['id'], ['title' => 'Ataque']), 'Usuário B não pode editar treino do usuário A');

    $prescriptionId = stridebr_api_id();
    $prescription = $pdo->prepare("INSERT INTO treinos_agendados (idagendamento,idatleta,idcriador,idmodalidade,data_treino,hora_inicio,duracao_prevista_min,titulo,descricao,origem,status,publicado_em) VALUES (:id,:athlete,:creator,:sport,'2026-09-14','08:00',45,'Prescrição do treinador','Não editável pelo atleta','treinador','publicado',NOW())");
    $prescription->execute([':id' => $prescriptionId, ':athlete' => $owner, ':creator' => $trainer, ':sport' => $sportId]);
    $prescribed = stridebr_api_workout_detail($pdo, $owner, stridebr_api_workout_id_scheduled($prescriptionId));
    AlphaTest::same('treinador', $prescribed['source'], 'Prescrição precisa preservar autoria/origem real');
    AlphaTest::assert(empty($prescribed['permissions']['can_edit']), 'Atleta não pode editar prescrição de treinador');
    AlphaTest::throws(fn() => stridebr_api_workout_update($pdo, $owner, $prescribed['id'], ['title' => 'Mutação indevida']), 'PATCH não pode alterar prescrição de treinador');
    $prescriptionDay = stridebr_api_workout_schedule($pdo, $owner, ['from' => '2026-09-14', 'to' => '2026-09-14']);
    $prescriptionItem = array_values(array_filter($prescriptionDay['data'], static fn(array $item): bool => $item['id'] === $prescribed['id']))[0] ?? [];
    AlphaTest::same('treinador', $prescriptionItem['source'] ?? null, 'Calendário deve distinguir prescrição de treinador');
    $cancelledPrescription = stridebr_api_workout_cancel($pdo, $owner, $prescribed['id']);
    AlphaTest::same('cancelado', $cancelledPrescription['status'], 'Cancelamento pelo atleta deve seguir a semântica já existente de prescrição');

    $templateId = stridebr_api_id();
    $pdo->prepare("INSERT INTO treinos_modelo (idtreino_modelo,idusuario,idmodalidade,titulo,codigo,foco,descricao) VALUES (:id,:user,:sport,'Intervalado modelo','INT-1K','Velocidade','Cinco blocos')")->execute([':id' => $templateId, ':user' => $owner, ':sport' => $sportId]);
    $pdo->prepare("INSERT INTO treinos_modelo_exercicios (idtreino_modelo_exercicio,idtreino_modelo,nome_snapshot,series,repeticoes,bloco,cluster,descanso,ordem) VALUES (:id,:template,'Tiro de 1 km',5,'1 km','A','4+4','2 min',1)")->execute([':id' => stridebr_api_id(), ':template' => $templateId]);
    $templates = stridebr_api_workout_templates($pdo, $owner, ['q' => 'Intervalado']);
    AlphaTest::assert((bool) array_filter($templates['data'], static fn(array $item): bool => $item['id'] === $templateId), 'Biblioteca deve listar template real do usuário');
    $templateSummary = array_values(array_filter($templates['data'], static fn(array $item): bool => $item['id'] === $templateId))[0] ?? [];
    AlphaTest::assert(!empty($templateSummary['updated_at']), 'Template summary deve expor updated_at para cache local');
    AlphaTest::same([], stridebr_api_workout_template($pdo, $other, $templateId), 'Biblioteca deve respeitar ownership');
    $templateDetail = stridebr_api_workout_template($pdo, $owner, $templateId);
    AlphaTest::same(1, $templateDetail['structure']['exercise_count'], 'Detalhe do template deve retornar estrutura real');
    $fromTemplate = stridebr_api_workout_create($pdo, $owner, ['template_id' => $templateId, 'date' => '2026-09-15', 'time' => '17:30']);
    AlphaTest::same($templateId, $fromTemplate['template_id'], 'Ocorrência deve preservar proveniência do template');
    AlphaTest::same(1, $fromTemplate['structure']['exercise_count'], 'Agendar template deve copiar estrutura real da biblioteca');
    $scheduledTemplateId = substr((string) $fromTemplate['id'], strlen('scheduled:'));
    $snapshotExercise = $pdo->prepare('SELECT bloco, cluster FROM treinos_agendados_exercicios WHERE idagendamento=:appointment ORDER BY ordem LIMIT 1');
    $snapshotExercise->execute([':appointment' => $scheduledTemplateId]);
    $snapshotRow = $snapshotExercise->fetch();
    AlphaTest::same('A', (string) ($snapshotRow['bloco'] ?? ''), 'Snapshot agendado deve preservar bloco do exercício do template');
    AlphaTest::same('4+4', (string) ($snapshotRow['cluster'] ?? ''), 'Snapshot agendado deve preservar cluster do exercício do template');
    AlphaTest::same('A', $fromTemplate['structure']['exercises'][0]['block'] ?? null, 'GET /workouts/{id} deve devolver block preservado no snapshot');
    AlphaTest::same('4+4', $fromTemplate['structure']['exercises'][0]['cluster'] ?? null, 'GET /workouts/{id} deve devolver cluster preservado no snapshot');

    $plainTemplateId = stridebr_api_id();
    $pdo->prepare("INSERT INTO treinos_modelo (idtreino_modelo,idusuario,idmodalidade,titulo,codigo) VALUES (:id,:user,:sport,'Modelo sem bloco','PLAIN-1')")->execute([':id' => $plainTemplateId, ':user' => $owner, ':sport' => $sportId]);
    $pdo->prepare("INSERT INTO treinos_modelo_exercicios (idtreino_modelo_exercicio,idtreino_modelo,nome_snapshot,series,repeticoes,ordem) VALUES (:id,:template,'Exercício simples',3,'10',1)")->execute([':id' => stridebr_api_id(), ':template' => $plainTemplateId]);
    $plainFromTemplate = stridebr_api_workout_create($pdo, $owner, ['template_id' => $plainTemplateId, 'date' => '2026-09-16']);
    AlphaTest::same(null, $plainFromTemplate['structure']['exercises'][0]['block'] ?? null, 'Template sem bloco deve continuar válido e retornar block null');
    AlphaTest::same(null, $plainFromTemplate['structure']['exercises'][0]['cluster'] ?? null, 'Template sem cluster deve continuar válido e retornar cluster null');
    $templateDay = stridebr_api_workout_schedule($pdo, $owner, ['from' => '2026-09-15', 'to' => '2026-09-15']);
    $templateDayItem = array_values(array_filter($templateDay['data'], static fn(array $item): bool => $item['id'] === $fromTemplate['id']))[0] ?? [];
    AlphaTest::same($templateId, $templateDayItem['template_id'] ?? null, 'Summary deve preservar referência do template sem carregar exercícios');

    $scheduleId = cronogramaCriar($pdo, $owner, 'Alpha mobile recurrence');
    $recurringId = cronogramaSalvarTreino($pdo, $owner, [
        'idcronograma' => $scheduleId,
        'titulo' => 'Rodagem recorrente',
        'idmodalidade' => $sportId,
        'dia_semana' => 4,
        'hora_inicio' => '06:00',
        'hora_fim' => '07:00',
        'vigencia_inicio' => '2026-09-01',
        'vigencia_fim' => '2026-09-30',
    ]);
    $recurringApiId = stridebr_api_workout_id_recurring($recurringId, '2026-09-10');
    $recurringDetail = stridebr_api_workout_detail($pdo, $owner, $recurringApiId);
    AlphaTest::same('recurring', $recurringDetail['kind'], 'Recorrência deve reutilizar treinos_cronograma');
    AlphaTest::same('cronograma', $recurringDetail['source'], 'Origem recorrente deve refletir o cronograma real');
    $recurringRange = stridebr_api_workout_schedule($pdo, $owner, ['from' => '2026-09-09', 'to' => '2026-09-11']);
    AlphaTest::assert((bool) array_filter($recurringRange['data'], static fn(array $item): bool => $item['id'] === $recurringApiId), 'Range deve materializar recorrência existente sem RRULE paralelo');

    $linkWorkout = stridebr_api_workout_create($pdo, $owner, [
        'title' => 'Treino GPS vinculado',
        'sport' => $sportId,
        'date' => '2026-09-09',
        'time' => '18:00',
        'planned_duration_s' => 600,
        'planned_distance_m' => 1000,
    ]);
    $mobilePayload = [
        'workout_id' => $linkWorkout['id'],
        'sport' => $sportSlug,
        'title' => 'GPS do treino planejado',
        'visibility' => 'privado',
        'started_at' => '2026-09-09T18:00:00-03:00',
        'ended_at' => '2026-09-09T18:10:00-03:00',
        'metrics' => ['distance_m' => 1000.0, 'duration_s' => 600.0],
        'gps' => [
            'points' => [
                ['lat' => -27.3581, 'lon' => -53.3942, 'timestamp_ms' => 1788991200000],
                ['lat' => -27.3582, 'lon' => -53.3939, 'timestamp_ms' => 1788991800000],
            ],
            'measured_distance_m' => 998.0,
            'points_received' => 2,
            'points_rejected' => 0,
        ],
    ];
    $created = stridebr_api_create_activity($pdo, $owner, $mobilePayload, 'alpha-workout-link-idem-1');
    AlphaTest::assert(empty($created['reused']), 'Primeiro POST Activity com workout_id deve criar atividade');
    $linkedWorkout = stridebr_api_workout_detail($pdo, $owner, $linkWorkout['id']);
    AlphaTest::same('concluido', $linkedWorkout['status'], 'Activity vinculada deve concluir ocorrência agendada');
    AlphaTest::same((string) $created['id'], (string) ($linkedWorkout['activity']['id'] ?? ''), 'Detalhe do treino deve apontar para Activity realizada');
    AlphaTest::assert(!empty($linkedWorkout['session']['id']), 'Detalhe deve expor a sessão persistida que liga planejamento e Activity');
    $linkedActivity = stridebr_api_activity_detail($pdo, (string) $created['id'], $owner);
    AlphaTest::same($linkWorkout['id'], (string) ($linkedActivity['workout']['id'] ?? ''), 'Detalhe da Activity deve apontar de volta para o workout');
    $again = stridebr_api_create_activity($pdo, $owner, $mobilePayload, 'alpha-workout-link-idem-1');
    AlphaTest::assert(!empty($again['reused']), 'Idempotência de Activity precisa sobreviver ao vínculo de workout');
    $sessionCount = $pdo->prepare('SELECT COUNT(*) FROM sessoes_treino WHERE idusuario=:user AND idagendamento_origem=:appointment AND idregistro_atividade=:activity');
    $sessionCount->execute([':user' => $owner, ':appointment' => substr($linkWorkout['id'], strlen('scheduled:')), ':activity' => $created['id']]);
    AlphaTest::same(1, (int) $sessionCount->fetchColumn(), 'Reenvio não pode duplicar ponte sessão↔Activity');

    $recurringMobile = $mobilePayload;
    $recurringMobile['workout_id'] = $recurringApiId;
    $recurringMobile['title'] = 'GPS recorrente';
    $recurringMobile['started_at'] = '2026-09-10T06:00:00-03:00';
    $recurringMobile['ended_at'] = '2026-09-10T06:10:00-03:00';
    $recurringCreated = stridebr_api_create_activity($pdo, $owner, $recurringMobile, 'alpha-recurring-link-1');
    $recurringAfter = stridebr_api_workout_detail($pdo, $owner, $recurringApiId);
    AlphaTest::same('concluido', $recurringAfter['status'], 'Activity deve concluir ocorrência recorrente conciliada');
    AlphaTest::same((string) $recurringCreated['id'], (string) ($recurringAfter['activity']['id'] ?? ''), 'Ocorrência recorrente deve expor Activity vinculada');

    $otherPersonal = stridebr_api_workout_create($pdo, $other, ['title' => 'Outro usuário', 'sport' => $sportId, 'date' => '2026-09-10']);
    AlphaTest::same([], stridebr_api_workout_detail($pdo, $owner, $otherPersonal['id']), 'GET detail não pode vazar workout de outra conta');
    $ownerMonth = stridebr_api_workout_schedule($pdo, $owner, ['from' => '2026-09-01', 'to' => '2026-09-30']);
    AlphaTest::assert(!(bool) array_filter($ownerMonth['data'], static fn(array $item): bool => $item['id'] === $otherPersonal['id']), 'Calendário não pode vazar workout de outra conta');
};
