<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/function/api_v1.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_presenter.php';
require_once dirname(__DIR__, 2) . '/src/function/notificacoes.php';
require_once dirname(__DIR__, 2) . '/src/function/shared_activity_service.php';

return function (PDO $pdo): void {
    $owner = alphaTestUser($pdo, 'shared_owner');
    $invitee = alphaTestUser($pdo, 'shared_invitee');
    $unrelated = alphaTestUser($pdo, 'shared_unrelated');
    $friend = $pdo->prepare("INSERT INTO amizades (idamizade,idusuario_solicitante,idusuario_destino,status) VALUES (:id,:owner,:invitee,'aceita')");
    $friend->execute([':id' => stridebr_generate_id(), ':owner' => $owner, ':invitee' => $invitee]);

    $started = new DateTimeImmutable('2026-09-17T07:00:00-03:00');
    $created = stridebr_api_create_activity($pdo, $owner, [
        'sport' => 'corrida', 'title' => 'Corrida compartilhada', 'visibility' => 'privado',
        'started_at' => $started->format(DateTimeInterface::ATOM), 'ended_at' => $started->modify('+27 minutes')->format(DateTimeInterface::ATOM),
        'metrics' => ['distance_m' => 5020, 'duration_s' => 1620, 'elevation_gain_m' => 42],
        'gps' => ['points' => [
            ['lat' => -27.358, 'lon' => -53.398, 'timestamp_ms' => 1789675200000],
            ['lat' => -27.350, 'lon' => -53.390, 'timestamp_ms' => 1789676820000],
        ]],
    ], 'shared-activity-v1');
    $activityId = (string) $created['id'];
    $pdo->prepare('UPDATE registros_atividade SET esforco_percebido=8,calorias_externas=421 WHERE idregistro=:id')->execute([':id' => $activityId]);
    $equipment = atividadeSalvarEquipamento($pdo, $owner, ['nome' => 'Tênis Shared', 'tipo' => 'tenis']);
    $pdo->prepare('INSERT INTO registros_atividade_equipamentos (idregistro,idequipamento) VALUES (:activity,:equipment)')->execute([':activity' => $activityId, ':equipment' => $equipment]);

    // A/B/C: only the recorder can invite, and only an accepted friend is eligible.
    // Regression: service tests previously missed the endpoint calling a nonexistent CSRF helper.
    $probe = static function (string $user, string $activity, string $participant, string $token): array {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __DIR__ . '/shared_activity_api_probe.php', $user, $activity, $participant, $token], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Unable to launch participants endpoint probe');
        fclose($pipes[0]);
        $body = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
        proc_close($process);
        preg_match('/HTTP_STATUS=(\d+)/', $stderr, $status);
        return [(int) ($status[1] ?? 0), json_decode($body, true) ?: []];
    };
    [$invalidStatus] = $probe($owner, $activityId, $invitee, 'invalid');
    AlphaTest::same(403, $invalidStatus, 'Real endpoint rejects invalid CSRF before invite');
    [$status, $response] = $probe($owner, $activityId, $invitee, 'valid');
    AlphaTest::same(200, $status, 'Real web endpoint accepts form POST with valid CSRF');
    AlphaTest::same(true, $response['ok'] ?? false, 'Endpoint returns success instead of catching undefined CSRF function');
    AlphaTest::same('pending', $response['data']['status'] ?? '', 'Real endpoint creates pending invite and notification');
    // Reuse the endpoint result as the first invite; all original contracts below remain intact.
    $invitation = $response['data'];
    AlphaTest::same('pending', $invitation['status'], 'Owner cria convite pendente para amizade aceita');
    AlphaTest::assert($invitation['created'], 'Primeiro convite é criado');
    AlphaTest::throws(fn() => sharedActivityInvite($pdo, $owner, $activityId, $unrelated), 'Owner não pode convidar pessoa sem amizade aceita');
    AlphaTest::throws(fn() => sharedActivityInvite($pdo, $unrelated, $activityId, $invitee), 'Não-owner não pode convidar');
    $duplicate = sharedActivityInvite($pdo, $owner, $activityId, $invitee);
    AlphaTest::same(false, $duplicate['created'], 'Convite duplicado é idempotente');
    $count = $pdo->prepare('SELECT count(*) FROM activity_participants WHERE idregistro=:activity AND idusuario=:user');
    $count->execute([':activity' => $activityId, ':user' => $invitee]);
    AlphaTest::same(1, (int) $count->fetchColumn(), 'UNIQUE activity/user impede linha duplicada');

    // A pending invitee can view exactly enough shared event data to respond.
    AlphaTest::assert(sharedActivityCanView($pdo, $invitee, $activityId), 'Convite pendente autoriza a abertura da Activity para resposta');
    $pendingDetail = atividadeDetalheApi($pdo, $activityId, $invitee);
    AlphaTest::same($activityId, (string) ($pendingDetail['id'] ?? ''), 'Invitee pendente abre o detalhe da Activity');
    AlphaTest::same('pending', (string) ($pendingDetail['participants']['viewer_status'] ?? ''), 'Detalhe identifica convite pendente');

    // D/E/H/I/J: acceptance is self-scoped; event data is visible, personal data and editing are not.
    AlphaTest::same('accepted', sharedActivityRespond($pdo, $invitee, $activityId, 'accepted')['status'], 'Invitee aceita o próprio convite');
    AlphaTest::throws(fn() => sharedActivityRespond($pdo, $unrelated, $activityId, 'accepted'), 'Outro usuário não aceita convite alheio');
    $detail = atividadeDetalheApi($pdo, $activityId, $invitee);
    AlphaTest::same(true, (bool) ($detail['is_shared_participant'] ?? false), 'Detalhe identifica participante aceito');
    AlphaTest::same('Corrida compartilhada', (string) ($detail['titulo'] ?? ''), 'Participante recebe título compartilhável');
    AlphaTest::assert(!empty($detail['data']) && !empty($detail['metricas']), 'Participante recebe data e métricas do evento');
    AlphaTest::same(null, $detail['esforco'] ?? null, 'RPE do recorder não vaza para participante');
    AlphaTest::same(null, $detail['energia'] ?? null, 'Calorias do recorder não vazam para participante');
    AlphaTest::same([], $detail['equipamentos'] ?? null, 'Equipamento do recorder não vaza para participante');
    AlphaTest::same(null, $detail['stream_capabilities'] ?? null, 'Streams/sensores do recorder não vazam para participante');
    AlphaTest::throws(fn() => atividadeSalvarRegistro($pdo, $invitee, ['idmodelo' => alphaTestGeneralModel($pdo), 'titulo' => 'Tentativa', 'data_inicio' => '2026-09-17 08:00'], $activityId), 'Participante aceito não edita Activity do recorder');

    $history = atividadeListarRegistrosPagina($pdo, $invitee, 20);
    $sharedRows = array_values(array_filter($history['items'], static fn(array $item): bool => (string) $item['id'] === $activityId));
    AlphaTest::same(1, count($sharedRows), 'Histórico do participante lista a Activity aceita uma vez');
    AlphaTest::same(true, (bool) ($sharedRows[0]['is_shared_participant'] ?? false), 'Histórico marca Activity registrada por outra pessoa');
    AlphaTest::same(0, count(array_filter(atividadeListarRegistrosPagina($pdo, $owner, 20)['items'], static fn(array $item): bool => (string) $item['id'] === $activityId)) - 1, 'Histórico do recorder não duplica a própria Activity');

    // K: accepted participant can leave, without altering the recorder's Activity.
    AlphaTest::assert(sharedActivityRemove($pdo, $invitee, $activityId, $invitee), 'Participante aceito sai da participação');
    AlphaTest::assert(!sharedActivityCanView($pdo, $invitee, $activityId), 'Participante removido perde acesso por convite');

    // F: decline removes invite access; the relation remains auditable with declined status.
    $declined = alphaTestUser($pdo, 'shared_declined');
    $friend->execute([':id' => stridebr_generate_id(), ':owner' => $owner, ':invitee' => $declined]);
    sharedActivityInvite($pdo, $owner, $activityId, $declined);
    AlphaTest::same('declined', sharedActivityRespond($pdo, $declined, $activityId, 'declined')['status'], 'Invitee recusa o próprio convite');
    AlphaTest::assert(!sharedActivityCanView($pdo, $declined, $activityId), 'Convite recusado não mantém acesso');

    // Notification is created only for the invited user and remains unread until its own action/destination.
    $notification = $pdo->prepare("SELECT idusuario,lida_em,url FROM notificacoes WHERE tipo='activity_participant_invite' AND dados->>'activity_id'=:activity ORDER BY data_criacao DESC LIMIT 1");
    $notification->execute([':activity' => $activityId]);
    $notice = $notification->fetch();
    AlphaTest::same($declined, (string) ($notice['idusuario'] ?? ''), 'Convite notifica somente o convidado');
    AlphaTest::same(null, $notice['lida_em'] ?? null, 'Criar convite não marca a notificação como lida');
    AlphaTest::same('/user/atividades.php?activity=' . rawurlencode($activityId), (string) ($notice['url'] ?? ''), 'Notificação mantém destino factual da Activity');

    $notificationFailureUser = alphaTestUser($pdo, 'shared_notice_failure');
    $friend->execute([':id'=>stridebr_generate_id(), ':owner'=>$owner, ':invitee'=>$notificationFailureUser]);
    $pdo->exec("CREATE FUNCTION alpha_shared_notice_failure() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF NEW.tipo='activity_participant_invite' THEN RAISE EXCEPTION 'notification test outage'; END IF; RETURN NEW; END $$");
    $pdo->exec('CREATE TRIGGER alpha_shared_notice_failure BEFORE INSERT ON notificacoes FOR EACH ROW EXECUTE FUNCTION alpha_shared_notice_failure()');
    try {
        $saved = sharedActivityInvite($pdo, $owner, $activityId, $notificationFailureUser);
        AlphaTest::same('pending', $saved['status'], 'Auxiliary notification failure does not report a committed invite as failed');
        AlphaTest::assert(!$pdo->inTransaction(), 'Invite leaves no failed/open transaction after notification outage');
        AlphaTest::assert(sharedActivityCanView($pdo, $notificationFailureUser, $activityId), 'Committed invite persists after optional notification failure');
    } finally {
        $pdo->exec('DROP TRIGGER alpha_shared_notice_failure ON notificacoes');
        $pdo->exec('DROP FUNCTION alpha_shared_notice_failure()');
    }

    // Migration constraints: check status/unique at the database boundary and cascade on hard deletion.
    AlphaTest::throws(fn() => $pdo->prepare("INSERT INTO activity_participants (idactivityparticipant,idregistro,idusuario,status,invited_by) VALUES (:id,:activity,:user,'invalid',:owner)")->execute([':id' => stridebr_generate_id(), ':activity' => $activityId, ':user' => $unrelated, ':owner' => $owner]), 'Constraint de status rejeita valor inválido');
    $cascadeUser = alphaTestUser($pdo, 'shared_cascade');
    $friend->execute([':id' => stridebr_generate_id(), ':owner' => $owner, ':invitee' => $cascadeUser]);
    sharedActivityInvite($pdo, $owner, $activityId, $cascadeUser);
    $pdo->prepare('DELETE FROM registros_atividade WHERE idregistro=:activity')->execute([':activity' => $activityId]);
    $count->execute([':activity' => $activityId, ':user' => $cascadeUser]);
    AlphaTest::same(0, (int) $count->fetchColumn(), 'Excluir Activity remove participações por cascade');

    $rolloutActivityId = atividadeSalvarRegistro($pdo, $owner, [
        'idmodelo' => alphaTestGeneralModel($pdo),
        'titulo' => 'Activity local durante rollout',
        'data_inicio' => '2026-09-17 09:00',
    ]);
    $pdo->beginTransaction();
    try {
        $pdo->exec('ALTER TABLE activity_participants RENAME TO activity_participants_rollout_test');
        $rolloutHistory = atividadeListarRegistrosPagina($pdo, $owner, 20);
        AlphaTest::same(1, count(array_filter($rolloutHistory['items'], static fn(array $item): bool => (string) $item['id'] === $rolloutActivityId)), 'Histórico próprio continua disponível antes da migration de Shared Activity');
        AlphaTest::same('Activity local durante rollout', (string) (atividadeCarregarRegistro($pdo, $rolloutActivityId, $owner)['titulo'] ?? ''), 'Detalhe próprio continua disponível antes da migration de Shared Activity');
        $pdo->rollBack();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
};
