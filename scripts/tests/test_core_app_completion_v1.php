<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/function/api_v1.php';
require_once dirname(__DIR__, 2) . '/src/function/workout_preview_service.php';

return function (PDO $pdo): void {
    $pdo->exec("UPDATE feature_flags SET ativo=TRUE WHERE chave IN ('friends.enabled','trainer.enabled')");
    $owner = alphaTestUser($pdo, 'completion_owner');
    $friend = alphaTestUser($pdo, 'completion_friend');
    $third = alphaTestUser($pdo, 'completion_third');
    $trainer = alphaTestUser($pdo, 'completion_trainer', ['trainer' => true]);
    $private = alphaTestUser($pdo, 'completion_private');
    $pdo->prepare("UPDATE usuarios SET visibilidadeperfil='publico',descobrivel=TRUE,biousuario='Bio pública' WHERE idusuario IN (:owner,:friend,:third,:trainer)")->execute([':owner'=>$owner, ':friend'=>$friend, ':third'=>$third, ':trainer'=>$trainer]);
    $pdo->prepare("UPDATE usuarios SET visibilidadeperfil='privado',descobrivel=FALSE WHERE idusuario=:id")->execute([':id'=>$private]);

    $catalog = stridebr_api_sports_catalog($pdo, $owner);
    $slugs = array_column($catalog, 'slug');
    AlphaTest::assert(in_array('corrida', $slugs, true), 'Catálogo completo precisa conter corrida mesmo sem uso no período.');
    AlphaTest::assert(in_array('musculacao', $slugs, true), 'Catálogo completo precisa conter musculação mesmo sem uso no período.');

    $customId = 'alpha_custom_' . substr(sha1($owner), 0, 8);
    $inactiveId = 'alpha_inactive_' . substr(sha1($owner), 0, 6);
    $otherCustomId = 'alpha_other_' . substr(sha1($owner), 0, 8);
    $insertSport = $pdo->prepare("INSERT INTO modalidades (idmodalidade,idusuario,nome,slug,categoria,familia_hub,ativo,permite_rota,ordem_catalogo) VALUES (:id,:user,:name,:slug,:category,:family,:active,:route,50)");
    $insertSportRow = static function (PDOStatement $stmt, string $id, string $user, string $name, string $slug, string $category, string $family, bool $active, bool $route): void {
        $stmt->bindValue(':id', $id, PDO::PARAM_STR);
        $stmt->bindValue(':user', $user, PDO::PARAM_STR);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $stmt->bindValue(':category', $category, PDO::PARAM_STR);
        $stmt->bindValue(':family', $family, PDO::PARAM_STR);
        $stmt->bindValue(':active', $active, PDO::PARAM_BOOL);
        $stmt->bindValue(':route', $route, PDO::PARAM_BOOL);
        $stmt->execute();
    };
    $insertSportRow($insertSport, $customId, $owner, 'Corrida na escada', 'corrida-escada-alpha', 'Cardio', 'cardio', true, true);
    $insertSportRow($insertSport, $inactiveId, $owner, 'Inativa Alpha', 'inativa-alpha', 'Outras atividades', 'other', false, false);
    $insertSportRow($insertSport, $otherCustomId, $friend, 'Privada do outro', 'privada-outro-alpha', 'Outras atividades', 'other', true, false);
    $ownerCatalog = stridebr_api_sports_catalog($pdo, $owner);
    AlphaTest::assert(in_array($customId, array_column($ownerCatalog, 'id'), true), 'Catálogo precisa incluir modalidade custom do owner.');
    AlphaTest::assert(!in_array($inactiveId, array_column($ownerCatalog, 'id'), true), 'Catálogo precisa excluir modalidade inativa.');
    AlphaTest::assert(!in_array($otherCustomId, array_column($ownerCatalog, 'id'), true), 'Catálogo não pode vazar modalidade custom de outro usuário.');
    $filteredCatalog = stridebr_api_sports_catalog($pdo, $owner, ['family'=>'cardio','q'=>'escada']);
    AlphaTest::same($customId, (string) ($filteredCatalog[0]['id'] ?? ''), 'Filtros q/family precisam manter modalidade custom correta.');

    $today = new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'));
    $from = $today->modify('-7 days')->format('Y-m-d');
    $to = $today->format('Y-m-d');
    $emptyProgress = stridebr_api_progress_dashboard($pdo, $owner, ['from'=>$from,'to'=>$to,'sport'=>$customId]);
    AlphaTest::same(0, (int) ($emptyProgress['overview']['summary']['activities_count'] ?? -1), 'Modalidade válida sem dados precisa responder 200-shape vazio, não erro.');
    AlphaTest::same(null, $emptyProgress['strength'] ?? null, 'Cardio filtrado não deve fabricar bloco strength.');
    $adherence = stridebr_api_progress_adherence($pdo, $owner, ['from'=>$from,'to'=>$to,'sport'=>$customId]);
    AlphaTest::assert(is_array($adherence), 'Adherence filtrado precisa aceitar modalidade owner-scoped.');

    $search = stridebr_api_people_search($pdo, $owner, ['q'=>'alpha_completion_friend']);
    AlphaTest::assert(count($search['items']) >= 1, 'People search precisa encontrar perfil descobrível.');
    $privateSearch = stridebr_api_people_search($pdo, $owner, ['q'=>'alpha_completion_private']);
    AlphaTest::same([], $privateSearch['items'], 'People search não pode expor perfil não descobrível.');
    AlphaTest::throws(fn() => stridebr_api_people_detail($pdo, $owner, $private), 'People detail precisa respeitar privacidade.');
    $person = stridebr_api_people_detail($pdo, $owner, $friend);
    AlphaTest::assert(!array_key_exists('email', $person) && !array_key_exists('phone', $person) && !array_key_exists('birth_date', $person), 'DTO público não pode incluir dados privados.');

    $request = stridebr_api_people_friendship_create($pdo, $owner, ['user_id'=>$friend]);
    AlphaTest::same('outgoing', $request['status'], 'Solicitação enviada precisa ser outgoing para remetente.');
    $incoming = stridebr_api_people_friends($pdo, $friend);
    AlphaTest::same(1, count($incoming['incoming']), 'Destinatário precisa receber incoming.');
    $accepted = stridebr_api_people_friendship_respond($pdo, $friend, (string) $request['id'], 'accept');
    AlphaTest::same('accepted', $accepted['status'], 'Aceite de amizade precisa criar relação accepted.');
    $friends = stridebr_api_people_friends($pdo, $owner);
    AlphaTest::same(1, count($friends['friends']), 'Amizade aceita precisa aparecer em friends.');

    $rejectRequest = stridebr_api_people_friendship_create($pdo, $owner, ['user_id'=>$third]);
    $rejected = stridebr_api_people_friendship_respond($pdo, $third, (string) $rejectRequest['id'], 'reject');
    AlphaTest::same(true, $rejected['deleted'], 'Recusa precisa remover pending sem criar amizade.');
    $cancelRequest = stridebr_api_people_friendship_create($pdo, $friend, ['user_id'=>$third]);
    AlphaTest::throws(fn() => stridebr_api_people_friendship_delete($pdo, $third, (string) $cancelRequest['id']), 'Destinatário não pode cancelar outgoing de outra pessoa.');
    $cancelled = stridebr_api_people_friendship_delete($pdo, $friend, (string) $cancelRequest['id']);
    AlphaTest::same(true, $cancelled['deleted'], 'Remetente precisa cancelar outgoing pending.');
    $removed = stridebr_api_people_friendship_delete($pdo, $owner, (string) $request['id']);
    AlphaTest::same(true, $removed['deleted'], 'Usuário precisa remover amizade aceita.');

    $coachInvite = stridebr_api_people_coaching_create($pdo, $trainer, ['user_id'=>$owner,'role_for_me'=>'trainer']);
    AlphaTest::same('pending', $coachInvite['status'], 'Convite trainer->athlete precisa ser pending.');
    AlphaTest::same('trainer', $coachInvite['requested_by'], 'Convite precisa preservar requested_by trainer.');
    AlphaTest::throws(fn() => stridebr_api_people_coaching_respond($pdo, $third, (string) $coachInvite['id'], 'accept'), 'Terceiro não pode aceitar vínculo alheio.');
    $coachAccepted = stridebr_api_people_coaching_respond($pdo, $owner, (string) $coachInvite['id'], 'accept');
    AlphaTest::same('accepted', $coachAccepted['status'], 'Atleta precisa aceitar convite de treinador.');
    AlphaTest::throws(fn() => stridebr_api_people_coaching_permissions($pdo, $trainer, (string) $coachInvite['id'], ['can_view_activities'=>false]), 'Treinador não pode conceder/remover permissão para si mesmo.');
    $permissions = stridebr_api_people_coaching_permissions($pdo, $owner, (string) $coachInvite['id'], ['can_prescribe'=>true,'can_view_schedule'=>false,'can_view_activities'=>true,'can_view_feedback'=>false]);
    AlphaTest::same(false, $permissions['permissions']['can_view_schedule'], 'Atleta precisa controlar can_view_schedule.');
    AlphaTest::same(false, $permissions['permissions']['can_view_feedback'], 'Atleta precisa controlar can_view_feedback.');
    $coachingList = stridebr_api_people_coaching($pdo, $owner);
    AlphaTest::same(1, count($coachingList['trainers']), 'Atleta precisa ver treinador aceito.');
    $ended = stridebr_api_people_coaching_delete($pdo, $owner, (string) $coachInvite['id']);
    AlphaTest::same(true, $ended['deleted'], 'Vínculo aceito precisa poder ser encerrado.');

    $athleteRequest = stridebr_api_people_coaching_create($pdo, $friend, ['user_id'=>$trainer,'role_for_me'=>'athlete']);
    AlphaTest::same('athlete', $athleteRequest['requested_by'], 'Solicitação athlete->trainer precisa preservar requested_by athlete.');
    $coachReject = stridebr_api_people_coaching_respond($pdo, $trainer, (string) $athleteRequest['id'], 'reject');
    AlphaTest::same('rejected', $coachReject['status'], 'Treinador precisa poder recusar solicitação do atleta.');

    $strength = $pdo->query("SELECT m.idmodalidade,mm.idmodelo FROM modalidades m JOIN modelos_modalidade mm ON mm.idmodalidade=m.idmodalidade WHERE m.slug='musculacao' AND m.ativo=TRUE AND mm.ativo=TRUE ORDER BY mm.padrao DESC,mm.versao DESC LIMIT 1")->fetch();
    AlphaTest::assert(is_array($strength), 'Fixture precisa de modelo de musculação.');
    $exerciseRows = $pdo->query("SELECT idexercicio,nome FROM exercicios WHERE ativo=TRUE ORDER BY nome LIMIT 2")->fetchAll();
    AlphaTest::assert(count($exerciseRows) >= 2, 'Fixture precisa de dois exercícios.');
    $scheduleId = cronogramaCriar($pdo, $owner, 'Completion preview');
    $past = $today->modify('-2 days');
    $workoutId = cronogramaSalvarTreino($pdo, $owner, [
        'idcronograma'=>$scheduleId,
        'titulo'=>'Treino preview real',
        'idmodalidade'=>(string) $strength['idmodalidade'],
        'dia_semana'=>(int) $past->format('w'),
        'hora_inicio'=>'18:00',
        'hora_fim'=>'19:00',
        'vigencia_inicio'=>$past->modify('-7 days')->format('Y-m-d'),
    ]);
    cronogramaSalvarExercicios($pdo, $workoutId, $owner, [
        ['idexercicio'=>$exerciseRows[0]['idexercicio'],'nome'=>$exerciseRows[0]['nome'],'series'=>3,'repeticoes'=>'12','carga'=>'40 kg'],
        ['idexercicio'=>$exerciseRows[1]['idexercicio'],'nome'=>$exerciseRows[1]['nome'],'series'=>3,'repeticoes'=>'10','carga'=>'30 kg'],
    ], []);
    $missed = workoutPreviewData($pdo, $owner, $workoutId, ['planned_date'=>$past->format('Y-m-d')]);
    AlphaTest::same('missed', $missed['preview_mode'], 'Treino passado sem Activity precisa ser missed.');
    AlphaTest::same(2, count($missed['exercises']), 'Missed precisa manter exercícios planejados.');
    $future = workoutPreviewData($pdo, $owner, $workoutId, ['planned_date'=>$today->modify('+2 days')->format('Y-m-d')]);
    AlphaTest::same('planned', $future['preview_mode'], 'Treino futuro precisa ser planned.');

    $activityId = atividadeSalvarRegistro($pdo, $owner, [
        'idmodelo'=>(string) $strength['idmodelo'],
        'idtreino_cronograma'=>$workoutId,
        'data_ocorrencia_origem'=>$past->format('Y-m-d'),
        'data_ocorrencia_planejada'=>$past->format('Y-m-d'),
        'titulo'=>'Treino realizado preview',
        'data_inicio'=>$past->format('Y-m-d') . ' 18:00:00',
        'data_fim'=>$past->format('Y-m-d') . ' 18:45:00',
        'status'=>'concluido',
        'visibilidade'=>'privado',
        'origem'=>'manual',
    ]);
    atividadeForcaPersistirSeriesManuais($pdo, $owner, $activityId, [[
        'idexercicio'=>$exerciseRows[0]['idexercicio'],
        'nome'=>$exerciseRows[0]['nome'],
        'series'=>[
            ['tipo'=>'trabalho','repeticoes'=>12,'carga_kg'=>40,'concluida'=>true],
            ['tipo'=>'trabalho','repeticoes'=>10,'carga_kg'=>45,'concluida'=>true],
        ],
    ]]);
    $performed = workoutPreviewData($pdo, $owner, $workoutId, ['planned_date'=>$past->format('Y-m-d'),'activity_id'=>$activityId]);
    AlphaTest::same('performed', $performed['preview_mode'], 'Treino concluído precisa resolver performed.');
    AlphaTest::same($activityId, $performed['activity_id'], 'Performed precisa expor Activity vinculada.');
    AlphaTest::same(1, count($performed['exercises']), 'Performed não pode reinjetar exercício planejado ignorado.');
    AlphaTest::same(45.0, (float) ($performed['exercises'][0]['sets'][1]['load_kg'] ?? -1), 'Performed precisa mostrar carga realizada.');
    $intruderPreview = workoutPreviewData($pdo, $third, $workoutId, ['planned_date'=>$past->format('Y-m-d'),'activity_id'=>$activityId]);
    AlphaTest::assert(($intruderPreview['preview_mode'] ?? '') !== 'performed', 'Preview não pode aceitar Activity/workout de outro owner.');
};
