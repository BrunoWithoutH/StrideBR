<?php

declare(strict_types=1);

require_once __DIR__ . '/treinador_vinculos.php';
require_once __DIR__ . '/notificacoes.php';

function peopleFeatureEnabled(PDO $pdo, string $key, bool $default = false): bool
{
    try {
        $stmt = $pdo->prepare('SELECT ativo FROM feature_flags WHERE chave = :key LIMIT 1');
        $stmt->execute([':key' => $key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : stridebr_db_bool($value);
    } catch (PDOException $e) {
        if (in_array($e->getCode(), ['42P01', '42703'], true)) return $default;
        throw $e;
    }
}

function peoplePersonRow(PDO $pdo, string $personId): array
{
    $stmt = $pdo->prepare("SELECT idusuario,username,COALESCE(NULLIF(nome_exibicao,''),nomeusuario) AS nome_exibicao,fotousuario,biousuario,modo_treinador,descobrivel,visibilidadeperfil,statususuario FROM usuarios WHERE idusuario=:id LIMIT 1");
    $stmt->execute([':id' => $personId]);
    return $stmt->fetch() ?: [];
}

function peopleAcceptedFriendship(PDO $pdo, string $viewerId, string $personId): array
{
    $stmt = $pdo->prepare("SELECT * FROM amizades WHERE status='aceita' AND LEAST(idusuario_solicitante,idusuario_destino)=LEAST(:me1,:person1) AND GREATEST(idusuario_solicitante,idusuario_destino)=GREATEST(:me2,:person2) LIMIT 1");
    $stmt->execute([':me1'=>$viewerId, ':person1'=>$personId, ':me2'=>$viewerId, ':person2'=>$personId]);
    return $stmt->fetch() ?: [];
}

function peopleAnyFriendship(PDO $pdo, string $viewerId, string $personId): array
{
    $stmt = $pdo->prepare('SELECT * FROM amizades WHERE LEAST(idusuario_solicitante,idusuario_destino)=LEAST(:me1,:person1) AND GREATEST(idusuario_solicitante,idusuario_destino)=GREATEST(:me2,:person2) LIMIT 1');
    $stmt->execute([':me1'=>$viewerId, ':person1'=>$personId, ':me2'=>$viewerId, ':person2'=>$personId]);
    return $stmt->fetch() ?: [];
}

function peopleAcceptedCoaching(PDO $pdo, string $viewerId, string $personId): array
{
    $stmt = $pdo->prepare("SELECT * FROM vinculos_treinador_atleta WHERE status='aceito' AND ((idtreinador=:me1 AND idatleta=:person1) OR (idtreinador=:person2 AND idatleta=:me2)) ORDER BY data_atualizacao DESC LIMIT 1");
    $stmt->execute([':me1'=>$viewerId, ':person1'=>$personId, ':person2'=>$personId, ':me2'=>$viewerId]);
    return $stmt->fetch() ?: [];
}

function peopleAnyCoaching(PDO $pdo, string $viewerId, string $personId): array
{
    $stmt = $pdo->prepare("SELECT * FROM vinculos_treinador_atleta WHERE status IN ('pendente','aceito') AND ((idtreinador=:me1 AND idatleta=:person1) OR (idtreinador=:person2 AND idatleta=:me2)) ORDER BY data_atualizacao DESC LIMIT 1");
    $stmt->execute([':me1'=>$viewerId, ':person1'=>$personId, ':person2'=>$personId, ':me2'=>$viewerId]);
    return $stmt->fetch() ?: [];
}

function peoplePersonVisible(PDO $pdo, string $viewerId, array $person): bool
{
    if ($person === [] || (string) ($person['statususuario'] ?? '') !== 'Ativo') return false;
    $personId = (string) $person['idusuario'];
    if ($personId === $viewerId) return true;
    if (peopleAcceptedFriendship($pdo, $viewerId, $personId) !== []) return true;
    if (peopleAcceptedCoaching($pdo, $viewerId, $personId) !== []) return true;
    return stridebr_db_bool($person['descobrivel'] ?? false) && (string) ($person['visibilidadeperfil'] ?? 'privado') === 'publico';
}

function peoplePersonPayload(PDO $pdo, string $viewerId, array $person, bool $searchContext = false): array
{
    $personId = (string) ($person['idusuario'] ?? '');
    $friend = $personId !== '' && $personId !== $viewerId ? peopleAcceptedFriendship($pdo, $viewerId, $personId) : [];
    $coaching = $personId !== '' && $personId !== $viewerId ? peopleAcceptedCoaching($pdo, $viewerId, $personId) : [];
    $public = (string) ($person['visibilidadeperfil'] ?? 'privado') === 'publico';
    $bioAllowed = !$searchContext && ($personId === $viewerId || $public || $friend !== [] || $coaching !== []);
    return [
        'id' => $personId,
        'username' => trim((string) ($person['username'] ?? '')) ?: null,
        'display_name' => (string) ($person['nome_exibicao'] ?? ''),
        'avatar_url' => trim((string) ($person['fotousuario'] ?? '')) ?: null,
        'bio' => $bioAllowed ? (trim((string) ($person['biousuario'] ?? '')) ?: null) : null,
        'trainer_mode' => stridebr_db_bool($person['modo_treinador'] ?? false),
    ];
}

function peopleFriendshipStatus(array $relation, string $viewerId): ?string
{
    $status = (string) ($relation['status'] ?? '');
    if ($status === 'aceita') return 'accepted';
    if ($status === 'recusada') return 'rejected';
    if ($status === 'bloqueada') return 'blocked';
    if ($status !== 'pendente') return null;
    return (string) ($relation['idusuario_solicitante'] ?? '') === $viewerId ? 'outgoing' : 'incoming';
}

function peopleCoachingStatus(string $status): string
{
    return match ($status) {
        'pendente' => 'pending',
        'aceito' => 'accepted',
        'recusado' => 'rejected',
        'encerrado' => 'ended',
        default => $status,
    };
}

function peopleSearch(PDO $pdo, string $viewerId, array $filters): array
{
    $query = trim((string) ($filters['q'] ?? ''));
    if ($query === '') return [];
    $type = stridebr_api_lower(trim((string) ($filters['type'] ?? 'all')));
    if (!in_array($type, ['all','friend','trainer'], true)) throw new InvalidArgumentException('type inválido.');
    $username = ltrim(stridebr_api_lower($query), '@');
    $stmt = $pdo->prepare("SELECT idusuario,username,COALESCE(NULLIF(nome_exibicao,''),nomeusuario) AS nome_exibicao,fotousuario,biousuario,modo_treinador,descobrivel,visibilidadeperfil,statususuario
        FROM usuarios
        WHERE idusuario<>:viewer AND statususuario='Ativo' AND descobrivel=TRUE AND username IS NOT NULL
          AND (:trainer=FALSE OR modo_treinador=TRUE)
          AND (username ILIKE :username OR COALESCE(NULLIF(nome_exibicao,''),nomeusuario) ILIKE :name)
        ORDER BY CASE WHEN lower(username)=lower(:exact) THEN 0 ELSE 1 END, lower(COALESCE(NULLIF(nome_exibicao,''),nomeusuario)), lower(username)
        LIMIT 20");
    $stmt->bindValue(':viewer', $viewerId);
    $stmt->bindValue(':trainer', $type === 'trainer', PDO::PARAM_BOOL);
    $stmt->bindValue(':username', '%' . $username . '%');
    $stmt->bindValue(':name', '%' . $query . '%');
    $stmt->bindValue(':exact', $username);
    $stmt->execute();
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $personId = (string) $row['idusuario'];
        $friendship = peopleAnyFriendship($pdo, $viewerId, $personId);
        if ($type === 'friend' && peopleFriendshipStatus($friendship, $viewerId) !== 'accepted') continue;
        $item = peoplePersonPayload($pdo, $viewerId, $row, true);
        $item['friendship_status'] = $friendship !== [] ? peopleFriendshipStatus($friendship, $viewerId) : null;
        $coaching = peopleAnyCoaching($pdo, $viewerId, $personId);
        $item['coaching_status'] = $coaching !== [] ? peopleCoachingStatus((string) $coaching['status']) : null;
        $items[] = $item;
    }
    return $items;
}

function peoplePersonDetail(PDO $pdo, string $viewerId, string $personId): array
{
    $person = peoplePersonRow($pdo, $personId);
    if (!peoplePersonVisible($pdo, $viewerId, $person)) return [];
    $payload = peoplePersonPayload($pdo, $viewerId, $person);
    if ($personId === $viewerId) {
        $payload['friendship_status'] = null;
        $payload['coaching_status'] = null;
        $payload['capabilities'] = ['can_send_friend_request'=>false, 'can_request_coaching'=>false];
        return $payload;
    }
    $friendship = peopleAnyFriendship($pdo, $viewerId, $personId);
    $coaching = peopleAnyCoaching($pdo, $viewerId, $personId);
    $payload['friendship_status'] = $friendship !== [] ? peopleFriendshipStatus($friendship, $viewerId) : null;
    $payload['coaching_status'] = $coaching !== [] ? peopleCoachingStatus((string) $coaching['status']) : null;
    $payload['capabilities'] = [
        'can_send_friend_request' => peopleFeatureEnabled($pdo, 'friends.enabled', false) && $friendship === [],
        'can_request_coaching' => peopleFeatureEnabled($pdo, 'trainer.enabled', false) && $coaching === [],
    ];
    return $payload;
}

function peopleFriendshipPayload(PDO $pdo, string $viewerId, array $relation): array
{
    $requester = (string) $relation['idusuario_solicitante'];
    $target = (string) $relation['idusuario_destino'];
    $otherId = $requester === $viewerId ? $target : $requester;
    $status = peopleFriendshipStatus($relation, $viewerId);
    return [
        'id' => (string) $relation['idamizade'],
        'status' => $status,
        'person' => peoplePersonPayload($pdo, $viewerId, peoplePersonRow($pdo, $otherId)),
        'capabilities' => [
            'can_accept' => $status === 'incoming',
            'can_reject' => $status === 'incoming',
            'can_delete' => in_array($status, ['outgoing','accepted'], true),
        ],
    ];
}

function peopleFriendshipById(PDO $pdo, string $viewerId, string $friendshipId): array
{
    $stmt = $pdo->prepare('SELECT * FROM amizades WHERE idamizade=:id AND (idusuario_solicitante=:me1 OR idusuario_destino=:me2) LIMIT 1');
    $stmt->execute([':id'=>$friendshipId, ':me1'=>$viewerId, ':me2'=>$viewerId]);
    return $stmt->fetch() ?: [];
}

function peopleFriends(PDO $pdo, string $viewerId): array
{
    $stmt = $pdo->prepare("SELECT * FROM amizades WHERE (idusuario_solicitante=:me1 OR idusuario_destino=:me2) AND status IN ('pendente','aceita') ORDER BY data_atualizacao DESC,idamizade");
    $stmt->execute([':me1'=>$viewerId, ':me2'=>$viewerId]);
    $result = ['friends'=>[], 'incoming'=>[], 'outgoing'=>[]];
    foreach ($stmt->fetchAll() as $row) {
        $payload = peopleFriendshipPayload($pdo, $viewerId, $row);
        $status = $payload['status'];
        if ($status === 'accepted') $result['friends'][] = $payload;
        elseif ($status === 'incoming') $result['incoming'][] = $payload;
        elseif ($status === 'outgoing') $result['outgoing'][] = $payload;
    }
    return $result;
}

function peopleFriendshipCreate(PDO $pdo, string $viewerId, string $targetId): array
{
    if ($targetId === '' || $targetId === $viewerId) throw new InvalidArgumentException('user_id inválido.');
    $target = peoplePersonRow($pdo, $targetId);
    if ($target === [] || (string) ($target['statususuario'] ?? '') !== 'Ativo' || !stridebr_db_bool($target['descobrivel'] ?? false)) throw new RuntimeException('Pessoa não encontrada.');
    if (peopleAnyFriendship($pdo, $viewerId, $targetId) !== []) throw new RuntimeException('Já existe uma relação de amizade entre estas pessoas.');
    $id = function_exists('stridebr_api_id') ? stridebr_api_id() : treinadorVinculoGerarId();
    $pdo->prepare('INSERT INTO amizades (idamizade,idusuario_solicitante,idusuario_destino) VALUES (:id,:me,:target)')->execute([':id'=>$id, ':me'=>$viewerId, ':target'=>$targetId]);
    notificacaoCriar($pdo, $targetId, 'amizade_solicitacao', stridebr_t('notification.friend_request.title'), stridebr_t('notification.friend_request.message'), '/user/amigos.php');
    return peopleFriendshipPayload($pdo, $viewerId, peopleFriendshipById($pdo, $viewerId, $id));
}

function peopleFriendshipRespond(PDO $pdo, string $viewerId, string $friendshipId, string $action): array
{
    if (!in_array($action, ['accept','reject'], true)) throw new InvalidArgumentException('Ação inválida.');
    $relation = peopleFriendshipById($pdo, $viewerId, $friendshipId);
    if ($relation === [] || (string) $relation['status'] !== 'pendente' || (string) $relation['idusuario_destino'] !== $viewerId) throw new RuntimeException('Solicitação de amizade não encontrada.');
    if ($action === 'reject') {
        $pdo->prepare("DELETE FROM amizades WHERE idamizade=:id AND idusuario_destino=:me AND status='pendente'")->execute([':id'=>$friendshipId, ':me'=>$viewerId]);
        return ['id'=>$friendshipId, 'status'=>'rejected', 'deleted'=>true];
    }
    $pdo->prepare("UPDATE amizades SET status='aceita',data_atualizacao=NOW() WHERE idamizade=:id AND idusuario_destino=:me AND status='pendente'")->execute([':id'=>$friendshipId, ':me'=>$viewerId]);
    notificacaoCriar($pdo, (string) $relation['idusuario_solicitante'], 'amizade_aceita', stridebr_t('notification.friend_accepted.title'), stridebr_t('notification.friend_accepted.message'), '/user/amigos.php');
    return peopleFriendshipPayload($pdo, $viewerId, peopleFriendshipById($pdo, $viewerId, $friendshipId));
}

function peopleFriendshipDelete(PDO $pdo, string $viewerId, string $friendshipId): array
{
    $relation = peopleFriendshipById($pdo, $viewerId, $friendshipId);
    if ($relation === []) return ['id'=>$friendshipId, 'deleted'=>true, 'reused'=>true];
    $status = (string) $relation['status'];
    if ($status === 'pendente' && (string) $relation['idusuario_solicitante'] !== $viewerId) throw new RuntimeException('Somente quem enviou pode cancelar esta solicitação.');
    if (!in_array($status, ['pendente','aceita'], true)) throw new RuntimeException('Esta relação não pode ser removida.');
    $pdo->prepare('DELETE FROM amizades WHERE idamizade=:id')->execute([':id'=>$friendshipId]);
    return ['id'=>$friendshipId, 'deleted'=>true, 'reused'=>false];
}

function peopleCoachingPermissions(array $row): array
{
    return [
        'can_prescribe' => stridebr_db_bool($row['pode_prescrever'] ?? false),
        'can_view_schedule' => stridebr_db_bool($row['pode_ver_cronograma'] ?? false),
        'can_view_activities' => stridebr_db_bool($row['pode_ver_atividades'] ?? false),
        'can_view_feedback' => stridebr_db_bool($row['pode_ver_feedback'] ?? false),
    ];
}

function peopleCoachingPayload(PDO $pdo, string $viewerId, array $relation): array
{
    $status = (string) $relation['status'];
    $requestedBy = (string) $relation['solicitado_por'];
    $viewerRole = (string) $relation['idtreinador'] === $viewerId ? 'trainer' : 'athlete';
    $isRequester = $requestedBy === ($viewerRole === 'trainer' ? 'treinador' : 'atleta');
    return [
        'id' => (string) $relation['idvinculo'],
        'status' => peopleCoachingStatus($status),
        'trainer' => peoplePersonPayload($pdo, $viewerId, peoplePersonRow($pdo, (string) $relation['idtreinador'])),
        'athlete' => peoplePersonPayload($pdo, $viewerId, peoplePersonRow($pdo, (string) $relation['idatleta'])),
        'requested_by' => $requestedBy === 'treinador' ? 'trainer' : 'athlete',
        'permissions' => peopleCoachingPermissions($relation),
        'capabilities' => [
            'can_accept' => $status === 'pendente' && !$isRequester,
            'can_reject' => $status === 'pendente' && !$isRequester,
            'can_cancel' => $status === 'pendente' && $isRequester,
            'can_end' => $status === 'aceito',
            'can_edit_permissions' => $status === 'aceito' && (string) $relation['idatleta'] === $viewerId,
        ],
    ];
}

function peopleCoaching(PDO $pdo, string $viewerId): array
{
    $stmt = $pdo->prepare("SELECT * FROM vinculos_treinador_atleta WHERE (idtreinador=:me1 OR idatleta=:me2) AND status IN ('pendente','aceito') ORDER BY data_atualizacao DESC,idvinculo");
    $stmt->execute([':me1'=>$viewerId, ':me2'=>$viewerId]);
    $result = ['trainers'=>[], 'athletes'=>[], 'incoming'=>[], 'outgoing'=>[]];
    foreach ($stmt->fetchAll() as $row) {
        $payload = peopleCoachingPayload($pdo, $viewerId, $row);
        if ((string) $row['status'] === 'aceito') {
            if ((string) $row['idtreinador'] === $viewerId) $result['athletes'][] = $payload;
            else $result['trainers'][] = $payload;
        } elseif ($payload['capabilities']['can_accept']) $result['incoming'][] = $payload;
        else $result['outgoing'][] = $payload;
    }
    return $result;
}

function peopleCoachingById(PDO $pdo, string $viewerId, string $linkId): array
{
    $stmt = $pdo->prepare('SELECT * FROM vinculos_treinador_atleta WHERE idvinculo=:id AND (idtreinador=:me1 OR idatleta=:me2) LIMIT 1');
    $stmt->execute([':id'=>$linkId, ':me1'=>$viewerId, ':me2'=>$viewerId]);
    return $stmt->fetch() ?: [];
}

function peopleCoachingCreate(PDO $pdo, string $viewerId, string $targetId, string $roleForMe): array
{
    if (!in_array($roleForMe, ['trainer','athlete'], true)) throw new InvalidArgumentException('role_for_me inválido.');
    if ($targetId === '' || $targetId === $viewerId) throw new InvalidArgumentException('user_id inválido.');
    $target = peoplePersonRow($pdo, $targetId);
    if ($target === [] || (string) ($target['statususuario'] ?? '') !== 'Ativo' || !stridebr_db_bool($target['descobrivel'] ?? false) || trim((string) ($target['username'] ?? '')) === '') throw new RuntimeException('Pessoa não encontrada.');
    $linkId = treinadorCriarConvite($pdo, $viewerId, (string) $target['username'], $roleForMe === 'trainer' ? 'treinador' : 'atleta');
    $relation = peopleCoachingById($pdo, $viewerId, $linkId);
    $targetUser = $roleForMe === 'trainer' ? (string) $relation['idatleta'] : (string) $relation['idtreinador'];
    notificacaoCriar($pdo, $targetUser, 'treinador_convite', stridebr_t('notification.coach_invite.title'), stridebr_t('notification.coach_invite.message'), '/user/treinador.php');
    return peopleCoachingPayload($pdo, $viewerId, $relation);
}

function peopleCoachingRespond(PDO $pdo, string $viewerId, string $linkId, string $action): array
{
    if (!in_array($action, ['accept','reject'], true)) throw new InvalidArgumentException('Ação inválida.');
    treinadorResponderVinculo($pdo, $viewerId, $linkId, $action === 'accept' ? 'aceitar' : 'recusar');
    $relation = peopleCoachingById($pdo, $viewerId, $linkId);
    if ($relation === []) throw new RuntimeException('Vínculo não encontrado.');
    if ($action === 'accept') {
        $target = (string) $relation['idtreinador'] === $viewerId ? (string) $relation['idatleta'] : (string) $relation['idtreinador'];
        notificacaoCriar($pdo, $target, 'treinador_vinculo_aceito', stridebr_t('notification.coach_link_accepted.title'), stridebr_t('notification.coach_link_accepted.message'), '/user/treinador.php');
    }
    return peopleCoachingPayload($pdo, $viewerId, $relation);
}

function peopleCoachingDelete(PDO $pdo, string $viewerId, string $linkId): array
{
    $relation = peopleCoachingById($pdo, $viewerId, $linkId);
    if ($relation === []) return ['id'=>$linkId, 'deleted'=>true, 'reused'=>true];
    if ((string) $relation['status'] === 'pendente') {
        $viewerRole = (string) $relation['idtreinador'] === $viewerId ? 'treinador' : 'atleta';
        if ((string) $relation['solicitado_por'] !== $viewerRole) throw new RuntimeException('Somente quem enviou pode cancelar este convite.');
        $stmt = $pdo->prepare("UPDATE vinculos_treinador_atleta SET status='encerrado',encerrado_em=NOW(),data_atualizacao=NOW() WHERE idvinculo=:id AND status='pendente'");
        $stmt->execute([':id'=>$linkId]);
        return ['id'=>$linkId, 'deleted'=>true, 'reused'=>false];
    }
    if ((string) $relation['status'] === 'aceito') {
        treinadorEncerrarVinculo($pdo, $viewerId, $linkId);
        return ['id'=>$linkId, 'deleted'=>true, 'reused'=>false];
    }
    return ['id'=>$linkId, 'deleted'=>true, 'reused'=>true];
}

function peopleCoachingPermissionsUpdate(PDO $pdo, string $viewerId, string $linkId, array $payload): array
{
    $relation = peopleCoachingById($pdo, $viewerId, $linkId);
    if ($relation === [] || (string) $relation['status'] !== 'aceito') throw new RuntimeException('Vínculo não encontrado.');
    if ((string) $relation['idatleta'] !== $viewerId) throw new LogicException('Somente o atleta pode alterar as permissões do vínculo.');
    $current = peopleCoachingPermissions($relation);
    $map = [
        'can_prescribe'=>'pode_prescrever',
        'can_view_schedule'=>'pode_ver_cronograma',
        'can_view_activities'=>'pode_ver_atividades',
        'can_view_feedback'=>'pode_ver_feedback',
    ];
    $domain = [];
    foreach ($map as $api => $internal) {
        $value = array_key_exists($api, $payload) ? filter_var($payload[$api], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : $current[$api];
        if ($value === null) throw new InvalidArgumentException($api . ' inválido.');
        if ($value) $domain[$internal] = true;
    }
    treinadorAtualizarPermissoes($pdo, $viewerId, $linkId, $domain);
    return peopleCoachingPayload($pdo, $viewerId, peopleCoachingById($pdo, $viewerId, $linkId));
}
