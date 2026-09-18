<?php

declare(strict_types=1);

/** Shared Activity V1 links an event to another athlete; it never duplicates a recording. */
function sharedActivityOwner(PDO $pdo, string $activityId): ?array
{
    $stmt = $pdo->prepare("SELECT ra.idregistro,ra.idusuario,ra.titulo,ra.data_inicio,ra.data_fim,ra.excluido_em,m.nome AS modalidade_nome,COALESCE(NULLIF(u.nome_exibicao,''),u.nomeusuario) AS owner_name FROM registros_atividade ra JOIN modalidades m ON m.idmodalidade=ra.idmodalidade JOIN usuarios u ON u.idusuario=ra.idusuario WHERE ra.idregistro=:id LIMIT 1");
    $stmt->execute([':id' => $activityId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function sharedActivityAreFriends(PDO $pdo, string $firstUserId, string $secondUserId): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM amizades WHERE status='aceita' AND ((idusuario_solicitante=:a AND idusuario_destino=:b) OR (idusuario_solicitante=:b AND idusuario_destino=:a)) LIMIT 1");
    $stmt->execute([':a' => $firstUserId, ':b' => $secondUserId]);
    return (bool) $stmt->fetchColumn();
}

function sharedActivityCanView(PDO $pdo, string $userId, string $activityId): bool
{
    $owner = sharedActivityOwner($pdo, $activityId);
    if ($owner === null || $owner['excluido_em'] !== null) return false;
    if ((string) $owner['idusuario'] === $userId) return true;
    // A pending invitee needs to open the activity once in order to accept or decline
    // it. A declined/removed relation deliberately grants no access.
    $stmt = $pdo->prepare("SELECT 1 FROM activity_participants WHERE idregistro=:activity AND idusuario=:user AND status IN ('pending','accepted') LIMIT 1");
    $stmt->execute([':activity' => $activityId, ':user' => $userId]);
    return (bool) $stmt->fetchColumn();
}

function sharedActivityParticipants(PDO $pdo, string $viewerId, string $activityId): array
{
    $owner = sharedActivityOwner($pdo, $activityId);
    if ($owner === null || !sharedActivityCanView($pdo, $viewerId, $activityId)) throw new InvalidArgumentException(stridebr_t('activity.participants.not_found'));
    $stmt = $pdo->prepare("SELECT p.idactivityparticipant,p.idusuario,p.status,p.invited_by,p.invited_at,p.responded_at,COALESCE(NULLIF(u.nome_exibicao,''),u.nomeusuario) AS nome,u.username FROM activity_participants p JOIN usuarios u ON u.idusuario=p.idusuario WHERE p.idregistro=:activity ORDER BY CASE p.status WHEN 'accepted' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END,p.created_at");
    $stmt->execute([':activity' => $activityId]);
    $participants = array_map(static fn(array $row): array => ['id' => (string) $row['idactivityparticipant'], 'user_id' => (string) $row['idusuario'], 'name' => (string) $row['nome'], 'username' => (string) $row['username'], 'status' => (string) $row['status'], 'role' => 'participant', 'invited_at' => $row['invited_at'], 'responded_at' => $row['responded_at']], $stmt->fetchAll());
    $canManage = $viewerId === (string) $owner['idusuario'];
    $eligible = [];
    if ($canManage) {
        $friends = $pdo->prepare("SELECT u.idusuario,COALESCE(NULLIF(u.nome_exibicao,''),u.nomeusuario) AS nome,u.username FROM amizades a JOIN usuarios u ON u.idusuario=CASE WHEN a.idusuario_solicitante=:owner THEN a.idusuario_destino ELSE a.idusuario_solicitante END WHERE a.status='aceita' AND (:owner IN (a.idusuario_solicitante,a.idusuario_destino)) AND u.statususuario='Ativo' AND NOT EXISTS (SELECT 1 FROM activity_participants p WHERE p.idregistro=:activity AND p.idusuario=u.idusuario AND p.status IN ('pending','accepted')) ORDER BY nome LIMIT 100");
        $friends->execute([':owner'=>$viewerId, ':activity'=>$activityId]);
        $eligible = array_map(static fn(array $row): array => ['user_id'=>(string)$row['idusuario'],'name'=>(string)$row['nome'],'username'=>(string)$row['username']], $friends->fetchAll());
    }
    $viewerStatus = null; foreach ($participants as $participant) if ($participant['user_id'] === $viewerId) { $viewerStatus = $participant['status']; break; }
    return ['activity_id' => $activityId, 'owner' => ['user_id' => (string) $owner['idusuario'], 'name' => (string) $owner['owner_name'], 'role' => 'recorder'], 'participants' => $participants, 'eligible_friends' => $eligible, 'can_manage' => $canManage, 'viewer_status' => $viewerStatus];
}

function sharedActivityInvite(PDO $pdo, string $ownerId, string $activityId, string $participantId): array
{
    $owner = sharedActivityOwner($pdo, $activityId);
    if ($owner === null || $owner['excluido_em'] !== null || (string) $owner['idusuario'] !== $ownerId) throw new InvalidArgumentException(stridebr_t('activity.participants.not_found'));
    if ($participantId === '' || $participantId === $ownerId) throw new InvalidArgumentException(stridebr_t('activity.participants.invalid_person'));
    if (!sharedActivityAreFriends($pdo, $ownerId, $participantId)) throw new InvalidArgumentException(stridebr_t('activity.participants.friends_only'));
    $user = $pdo->prepare("SELECT COALESCE(NULLIF(nome_exibicao,''),nomeusuario) FROM usuarios WHERE idusuario=:id AND statususuario='Ativo' LIMIT 1");
    $user->execute([':id' => $participantId]);
    if (!$user->fetchColumn()) throw new InvalidArgumentException(stridebr_t('activity.participants.person_not_found'));
    $existing = $pdo->prepare('SELECT idactivityparticipant,status FROM activity_participants WHERE idregistro=:activity AND idusuario=:user FOR UPDATE');
    $pdo->beginTransaction();
    try {
        $existing->execute([':activity' => $activityId, ':user' => $participantId]);
        $row = $existing->fetch();
        if ($row && in_array((string) $row['status'], ['pending','accepted'], true)) { $pdo->commit(); return ['id' => (string) $row['idactivityparticipant'], 'status' => (string) $row['status'], 'created' => false]; }
        $id = $row ? (string) $row['idactivityparticipant'] : stridebr_generate_id();
        if ($row) $pdo->prepare("UPDATE activity_participants SET status='pending',invited_by=:owner,invited_at=NOW(),responded_at=NULL,updated_at=NOW() WHERE idactivityparticipant=:id")->execute([':owner'=>$ownerId, ':id'=>$id]);
        else $pdo->prepare("INSERT INTO activity_participants (idactivityparticipant,idregistro,idusuario,status,invited_by) VALUES (:id,:activity,:user,'pending',:owner)")->execute([':id'=>$id,':activity'=>$activityId,':user'=>$participantId,':owner'=>$ownerId]);
        $pdo->commit();
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
    // Notifications are optional in Core (feature flag/schema rollout). The saved
    // invitation must not be reported as failed after its transaction committed.
    try {
        if (function_exists('notificacaoCriar')) {
            $nameStmt=$pdo->prepare("SELECT COALESCE(NULLIF(nome_exibicao,''),nomeusuario) FROM usuarios WHERE idusuario=:id"); $nameStmt->execute([':id'=>$ownerId]); $name=(string)$nameStmt->fetchColumn();
            notificacaoCriar($pdo,$participantId,'activity_participant_invite',stridebr_t('activity.participants.notification', ['name' => $name]),trim((string) $owner['modalidade_nome']),'/user/atividades.php?activity='.rawurlencode($activityId),['activity_id'=>$activityId,'invited_by'=>$ownerId]);
        }
    } catch (Throwable $error) {
        error_log('StrideBR participant notification: ' . get_class($error) . ': ' . $error->getMessage());
    }
    return ['id'=>$id,'status'=>'pending','created'=>true];
}

function sharedActivityRespond(PDO $pdo, string $userId, string $activityId, string $status): array
{
    if (!in_array($status, ['accepted','declined'], true)) throw new InvalidArgumentException(stridebr_t('activity.participants.invalid_response'));
    $stmt=$pdo->prepare("UPDATE activity_participants SET status=:status,responded_at=COALESCE(responded_at,NOW()),updated_at=NOW() WHERE idregistro=:activity AND idusuario=:user AND status='pending' RETURNING idactivityparticipant,status");
    $stmt->execute([':status'=>$status,':activity'=>$activityId,':user'=>$userId]);
    $row=$stmt->fetch();
    if ($row) return ['id'=>(string)$row['idactivityparticipant'],'status'=>(string)$row['status']];
    $current=$pdo->prepare('SELECT idactivityparticipant,status FROM activity_participants WHERE idregistro=:activity AND idusuario=:user LIMIT 1'); $current->execute([':activity'=>$activityId,':user'=>$userId]); $row=$current->fetch();
    if ($row && (string)$row['status']===$status) return ['id'=>(string)$row['idactivityparticipant'],'status'=>$status];
    throw new InvalidArgumentException(stridebr_t('activity.participants.invite_not_found'));
}

function sharedActivityRemove(PDO $pdo, string $actorId, string $activityId, string $participantId): bool
{
    $owner=sharedActivityOwner($pdo,$activityId);
    if ($owner===null || $owner['excluido_em']!==null) throw new InvalidArgumentException(stridebr_t('activity.participants.not_found'));
    if ($actorId !== (string)$owner['idusuario'] && $actorId !== $participantId) throw new InvalidArgumentException(stridebr_t('activity.participants.remove_forbidden'));
    $stmt=$pdo->prepare("UPDATE activity_participants SET status='removed',responded_at=COALESCE(responded_at,NOW()),updated_at=NOW() WHERE idregistro=:activity AND idusuario=:participant AND status IN ('pending','accepted')");
    $stmt->execute([':activity'=>$activityId,':participant'=>$participantId]);
    return $stmt->rowCount() === 1;
}
