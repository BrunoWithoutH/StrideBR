<?php
return function (PDO $pdo): void {
    $trainer = alphaTestUser($pdo, 'trainer_a', ['trainer' => true]);
    $athlete = alphaTestUser($pdo, 'trainer_b');
    $intruder = alphaTestUser($pdo, 'trainer_c');
    $link = treinadorCriarConvite($pdo, $trainer, 'alpha_trainer_b', 'treinador');
    AlphaTest::throws(fn() => treinadorResponderVinculo($pdo, $intruder, $link, 'aceitar'), 'Terceiro aceitou vínculo alheio');
    treinadorResponderVinculo($pdo, $athlete, $link, 'aceitar');
    AlphaTest::assert(treinadorVinculoAceito($pdo, $trainer, $athlete) !== [], 'Vínculo não foi aceito');
    treinadorAtualizarPermissoes($pdo, $athlete, $link, ['pode_prescrever' => '1']);
    AlphaTest::throws(fn() => treinadorAtualizarPermissoes($pdo, $intruder, $link, ['pode_prescrever' => '1']), 'Terceiro mudou permissões do atleta');
    $date = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
    $payload = ['titulo'=>'Treino com conteúdo livre', 'data_treino'=>$date, 'status'=>'rascunho'];
    $draft = treinadorCriarPrescricao($pdo, $trainer, $athlete, $payload, [['nome'=>'Agachamento', 'series'=>3]]);
    AlphaTest::throws(fn() => treinadorPublicarPrescricao($pdo, $intruder, $draft), 'Terceiro publicou prescrição');
    treinadorPublicarPrescricao($pdo, $trainer, $draft);
    AlphaTest::throws(fn() => treinadorSalvarFeedback($pdo, $athlete, $draft, 4, 'Ainda não concluído'), 'Feedback em treino não concluído');
    $pdo->prepare("UPDATE treinos_agendados SET status='concluido' WHERE idagendamento=:id")->execute([':id'=>$draft]);
    treinadorSalvarFeedback($pdo, $athlete, $draft, 4, 'Comentário livre em português');
    AlphaTest::throws(fn() => treinadorSalvarFeedback($pdo, $intruder, $draft, 1, 'IDOR'), 'Terceiro alterou feedback');
    $feedback = $pdo->prepare('SELECT feedback_atleta FROM treinos_agendados WHERE idagendamento=:id');
    $feedback->execute([':id'=>$draft]);
    AlphaTest::same('Comentário livre em português', $feedback->fetchColumn(), 'Conteúdo livre alterado');
    treinadorAtualizarPermissoes($pdo, $athlete, $link, []);
    AlphaTest::throws(fn() => treinadorCriarPrescricao($pdo, $trainer, $athlete, $payload, []), 'Criou prescrição sem permissão');
    $secondAthlete = alphaTestUser($pdo, 'trainer_second');
    AlphaTest::same([], treinadorVinculoAceito($pdo, $trainer, $secondAthlete), 'Segundo atleta herdou vínculo');
    $secondLink = treinadorCriarConvite($pdo, $trainer, 'alpha_trainer_second', 'treinador');
    AlphaTest::same([], treinadorVinculoAceito($pdo, $trainer, $secondAthlete), 'Convite pendente liberou dados');
    treinadorResponderVinculo($pdo, $secondAthlete, $secondLink, 'aceitar');
    AlphaTest::assert(treinadorVinculoAceito($pdo, $trainer, $secondAthlete) !== [], 'Segundo atleta não ficou independente');

    treinadorAtualizarPermissoes($pdo, $secondAthlete, $secondLink, ['pode_prescrever'=>'1','pode_ver_atividades'=>'1','pode_ver_cronograma'=>'1']);
    $model = alphaTestGeneralModel($pdo);
    $readonlyActivityId = atividadeSalvarRegistro($pdo, $secondAthlete, ['idmodelo'=>$model,'titulo'=>'Atividade read-only','data_inicio'=>'2026-09-10 08:00','data_fim'=>'2026-09-10 09:00','status'=>'concluido','visibilidade'=>'privado']);
    $readonlyActivity = treinadorAtividadeReadOnly($pdo, $trainer, $secondAthlete, $readonlyActivityId);
    AlphaTest::same($readonlyActivityId, (string) ($readonlyActivity['idregistro'] ?? ''), 'Treinador autorizado não abriu atividade read-only');
    AlphaTest::same(null, $readonlyActivity['distancia_metros'] ?? null, 'Atividade sem rota/distância fabricou métrica de distância');
    AlphaTest::throws(fn() => treinadorAtividadeReadOnly($pdo, $intruder, $secondAthlete, $readonlyActivityId), 'Treinador sem vínculo abriu atividade read-only');

    $routeActivityId = atividadeSalvarRegistro($pdo, $secondAthlete, [
        'idmodelo'=>alphaTestRouteModel($pdo),
        'titulo'=>'Atividade com rota read-only',
        'data_inicio'=>'2026-09-10 10:00',
        'data_fim'=>'2026-09-10 11:00',
        'status'=>'concluido',
        'visibilidade'=>'privado',
        'rota_coordenadas'=>['type'=>'LineString','coordinates'=>[[-53.3900,-27.3600],[-53.3800,-27.3500]]],
        'rota_metricas'=>['distancia_metros'=>4321.0],
    ]);
    $routeReadonly = treinadorAtividadeReadOnly($pdo, $trainer, $secondAthlete, $routeActivityId);
    AlphaTest::assert(abs((float) ($routeReadonly['distancia_metros'] ?? 0) - 4321.0) < 0.001, 'Read-only não usou distância canônica da rota');

    $otherAthlete = alphaTestUser($pdo, 'trainer_other_activity');
    $otherActivityId = atividadeSalvarRegistro($pdo, $otherAthlete, ['idmodelo'=>$model,'titulo'=>'Atividade de outro atleta','data_inicio'=>'2026-09-10 12:00','status'=>'concluido','visibilidade'=>'privado']);
    AlphaTest::throws(fn() => treinadorAtividadeReadOnly($pdo, $trainer, $secondAthlete, $otherActivityId), 'Trainer acessou atividade pertencente a outro atleta usando vínculo válido');

    $pendingAthlete = alphaTestUser($pdo, 'trainer_pending_activity');
    treinadorCriarConvite($pdo, $trainer, 'alpha_trainer_pending_activity', 'treinador');
    $pendingActivityId = atividadeSalvarRegistro($pdo, $pendingAthlete, ['idmodelo'=>$model,'titulo'=>'Atividade vínculo pendente','data_inicio'=>'2026-09-10 13:00','status'=>'concluido','visibilidade'=>'privado']);
    AlphaTest::throws(fn() => treinadorAtividadeReadOnly($pdo, $trainer, $pendingAthlete, $pendingActivityId), 'Vínculo não aceito liberou atividade read-only');

    $readonlyScheduleId = cronogramaCriar($pdo, $secondAthlete, 'Cronograma read-only');
    cronogramaSalvarTreino($pdo, $secondAthlete, ['idcronograma'=>$readonlyScheduleId,'titulo'=>'Treino read-only','dia_semana'=>'2','hora_inicio'=>'08:00','hora_fim'=>'09:00','vigencia_inicio'=>'2026-09-01']);
    $readonlySchedule = treinadorCronogramaReadOnly($pdo, $trainer, $secondAthlete, $readonlyScheduleId);
    AlphaTest::same($readonlyScheduleId, (string) ($readonlySchedule['idcronograma'] ?? ''), 'Treinador autorizado não abriu cronograma read-only');
    AlphaTest::same(1, count($readonlySchedule['treinos'] ?? []), 'Cronograma read-only perdeu seus treinos');
    AlphaTest::throws(fn() => treinadorCronogramaReadOnly($pdo, $intruder, $secondAthlete, $readonlyScheduleId), 'Treinador sem vínculo abriu cronograma read-only');

    $editDate = (new DateTimeImmutable('+2 days'))->format('Y-m-d');
    $editable = treinadorCriarPrescricao($pdo, $trainer, $secondAthlete, ['titulo'=>'Prescrição editável','data_treino'=>$editDate,'status'=>'rascunho'], [['nome'=>'Remada','series'=>3]]);
    treinadorEditarPrescricao($pdo, $trainer, $editable, ['titulo'=>'Prescrição revisada','data_treino'=>$editDate,'status'=>'rascunho','duracao_prevista_min'=>'55'], [['nome'=>'Remada baixa','series'=>4,'repeticoes'=>'10']]);
    $edited = treinadorPrescricao($pdo, $trainer, $editable);
    AlphaTest::same('Prescrição revisada', (string) ($edited['titulo'] ?? ''), 'Edição não atualizou título da prescrição');
    AlphaTest::same('Remada baixa', (string) (($edited['exercicios'][0]['nome_snapshot'] ?? '')), 'Edição não substituiu exercícios da prescrição');
    AlphaTest::throws(fn() => treinadorEditarPrescricao($pdo, $intruder, $editable, ['titulo'=>'IDOR','data_treino'=>$editDate,'status'=>'rascunho'], []), 'Outro treinador editou prescrição alheia');

    treinadorPublicarPrescricao($pdo, $trainer, $editable);
    treinadorEditarPrescricao($pdo, $trainer, $editable, ['titulo'=>'Publicada revisada','data_treino'=>$editDate,'status'=>'rascunho'], [['nome'=>'Remada baixa','series'=>4]]);
    $published = treinadorPrescricao($pdo, $trainer, $editable);
    AlphaTest::same('publicado', (string) ($published['status'] ?? ''), 'Editar prescrição publicada rebaixou status para rascunho');
    $pdo->prepare("UPDATE treinos_agendados SET status='concluido' WHERE idagendamento=:id")->execute([':id'=>$editable]);
    AlphaTest::throws(fn() => treinadorEditarPrescricao($pdo, $trainer, $editable, ['titulo'=>'Histórico reescrito','data_treino'=>$editDate,'status'=>'publicado'], []), 'Prescrição concluída foi editada');

    $cancelledEdit = treinadorCriarPrescricao($pdo, $trainer, $secondAthlete, ['titulo'=>'Cancelar edição','data_treino'=>$editDate,'status'=>'rascunho'], []);
    treinadorCancelarPrescricao($pdo, $trainer, $cancelledEdit);
    AlphaTest::throws(fn() => treinadorEditarPrescricao($pdo, $trainer, $cancelledEdit, ['titulo'=>'Cancelada reescrita','data_treino'=>$editDate,'status'=>'rascunho'], []), 'Prescrição cancelada foi editada');

    $historicalEdit = treinadorCriarPrescricao($pdo, $trainer, $secondAthlete, ['titulo'=>'Histórico','data_treino'=>$editDate,'status'=>'rascunho'], []);
    $pdo->prepare("UPDATE treinos_agendados SET data_treino=CURRENT_DATE - INTERVAL '1 day' WHERE idagendamento=:id")->execute([':id'=>$historicalEdit]);
    AlphaTest::throws(fn() => treinadorEditarPrescricao($pdo, $trainer, $historicalEdit, ['titulo'=>'Histórico reescrito','data_treino'=>$editDate,'status'=>'rascunho'], []), 'Prescrição histórica foi editada');

    $permissionEdit = treinadorCriarPrescricao($pdo, $trainer, $secondAthlete, ['titulo'=>'Permissão de edição','data_treino'=>$editDate,'status'=>'rascunho'], []);
    treinadorAtualizarPermissoes($pdo, $secondAthlete, $secondLink, []);
    AlphaTest::throws(fn() => treinadorAtividadeReadOnly($pdo, $trainer, $secondAthlete, $readonlyActivityId), 'Atividade read-only ignorou remoção da permissão');
    AlphaTest::throws(fn() => treinadorCronogramaReadOnly($pdo, $trainer, $secondAthlete, $readonlyScheduleId), 'Cronograma read-only ignorou remoção da permissão');
    AlphaTest::throws(fn() => treinadorEditarPrescricao($pdo, $trainer, $permissionEdit, ['titulo'=>'Sem permissão','data_treino'=>$editDate,'status'=>'rascunho'], []), 'Edição de prescrição ignorou remoção da permissão');
    treinadorEncerrarVinculo($pdo, $athlete, $link);
    AlphaTest::same([], treinadorVinculoAceito($pdo, $trainer, $athlete), 'Vínculo encerrado continuou autorizado');
};
