<?php

declare(strict_types=1);

require_once __DIR__ . '/people_service.php';

final class PeopleApiNotFoundException extends RuntimeException {}
final class PeopleApiConflictException extends RuntimeException {}
final class PeopleApiForbiddenException extends RuntimeException {}
final class PeopleApiFeatureDisabledException extends RuntimeException {}

function stridebr_api_people_features(PDO $pdo): array
{
    return [
        'friends_enabled' => peopleFeatureEnabled($pdo, 'friends.enabled', false),
        'trainer_enabled' => peopleFeatureEnabled($pdo, 'trainer.enabled', false),
    ];
}

function stridebr_api_people_search(PDO $pdo, string $userId, array $filters): array
{
    return ['items'=>peopleSearch($pdo, $userId, $filters), 'capabilities'=>stridebr_api_people_features($pdo)];
}

function stridebr_api_people_detail(PDO $pdo, string $userId, string $personId): array
{
    $person = peoplePersonDetail($pdo, $userId, $personId);
    if ($person === []) throw new PeopleApiNotFoundException('Pessoa não encontrada.');
    $person['people_capabilities'] = stridebr_api_people_features($pdo);
    return $person;
}

function stridebr_api_people_friends(PDO $pdo, string $userId): array
{
    if (!peopleFeatureEnabled($pdo, 'friends.enabled', false)) throw new PeopleApiFeatureDisabledException('Amigos está temporariamente desativado.');
    return peopleFriends($pdo, $userId);
}

function stridebr_api_people_friendship_create(PDO $pdo, string $userId, array $payload): array
{
    if (!peopleFeatureEnabled($pdo, 'friends.enabled', false)) throw new PeopleApiFeatureDisabledException('Amigos está temporariamente desativado.');
    try {
        return peopleFriendshipCreate($pdo, $userId, trim((string) ($payload['user_id'] ?? '')));
    } catch (RuntimeException $e) {
        if ($e->getMessage() === 'Pessoa não encontrada.') throw new PeopleApiNotFoundException($e->getMessage());
        throw new PeopleApiConflictException($e->getMessage());
    }
}

function stridebr_api_people_friendship_respond(PDO $pdo, string $userId, string $friendshipId, string $action): array
{
    if (!peopleFeatureEnabled($pdo, 'friends.enabled', false)) throw new PeopleApiFeatureDisabledException('Amigos está temporariamente desativado.');
    $relation = peopleFriendshipById($pdo, $userId, $friendshipId);
    if ($relation === []) throw new PeopleApiNotFoundException('Solicitação de amizade não encontrada.');
    if ((string) ($relation['status'] ?? '') !== 'pendente' || (string) ($relation['idusuario_destino'] ?? '') !== $userId) throw new PeopleApiForbiddenException('Esta solicitação não pode ser respondida por este usuário.');
    try {
        return peopleFriendshipRespond($pdo, $userId, $friendshipId, $action);
    } catch (RuntimeException $e) {
        throw new PeopleApiConflictException($e->getMessage());
    }
}

function stridebr_api_people_friendship_delete(PDO $pdo, string $userId, string $friendshipId): array
{
    if (!peopleFeatureEnabled($pdo, 'friends.enabled', false)) throw new PeopleApiFeatureDisabledException('Amigos está temporariamente desativado.');
    $relation = peopleFriendshipById($pdo, $userId, $friendshipId);
    if ($relation !== [] && (string) ($relation['status'] ?? '') === 'pendente' && (string) ($relation['idusuario_solicitante'] ?? '') !== $userId) {
        throw new PeopleApiForbiddenException('Somente quem enviou pode cancelar esta solicitação.');
    }
    try {
        return peopleFriendshipDelete($pdo, $userId, $friendshipId);
    } catch (RuntimeException $e) {
        throw new PeopleApiConflictException($e->getMessage());
    }
}

function stridebr_api_people_coaching(PDO $pdo, string $userId): array
{
    if (!peopleFeatureEnabled($pdo, 'trainer.enabled', false)) throw new PeopleApiFeatureDisabledException('Treinador está temporariamente desativado.');
    return peopleCoaching($pdo, $userId);
}

function stridebr_api_people_coaching_create(PDO $pdo, string $userId, array $payload): array
{
    if (!peopleFeatureEnabled($pdo, 'trainer.enabled', false)) throw new PeopleApiFeatureDisabledException('Treinador está temporariamente desativado.');
    try {
        return peopleCoachingCreate($pdo, $userId, trim((string) ($payload['user_id'] ?? '')), trim((string) ($payload['role_for_me'] ?? '')));
    } catch (RuntimeException $e) {
        if ($e->getMessage() === 'Pessoa não encontrada.') throw new PeopleApiNotFoundException($e->getMessage());
        throw new PeopleApiConflictException($e->getMessage());
    }
}

function stridebr_api_people_coaching_respond(PDO $pdo, string $userId, string $linkId, string $action): array
{
    if (!peopleFeatureEnabled($pdo, 'trainer.enabled', false)) throw new PeopleApiFeatureDisabledException('Treinador está temporariamente desativado.');
    $relation = peopleCoachingById($pdo, $userId, $linkId);
    if ($relation === []) throw new PeopleApiNotFoundException('Vínculo não encontrado.');
    $viewerRole = (string) $relation['idtreinador'] === $userId ? 'treinador' : 'atleta';
    if ((string) $relation['status'] !== 'pendente' || (string) $relation['solicitado_por'] === $viewerRole) throw new PeopleApiForbiddenException('Este vínculo não pode ser respondido por este usuário.');
    try {
        return peopleCoachingRespond($pdo, $userId, $linkId, $action);
    } catch (RuntimeException $e) {
        throw new PeopleApiConflictException($e->getMessage());
    }
}

function stridebr_api_people_coaching_delete(PDO $pdo, string $userId, string $linkId): array
{
    if (!peopleFeatureEnabled($pdo, 'trainer.enabled', false)) throw new PeopleApiFeatureDisabledException('Treinador está temporariamente desativado.');
    $relation = peopleCoachingById($pdo, $userId, $linkId);
    if ($relation !== [] && (string) ($relation['status'] ?? '') === 'pendente') {
        $viewerRole = (string) ($relation['idtreinador'] ?? '') === $userId ? 'treinador' : 'atleta';
        if ((string) ($relation['solicitado_por'] ?? '') !== $viewerRole) throw new PeopleApiForbiddenException('Somente quem enviou pode cancelar este convite.');
    }
    try {
        return peopleCoachingDelete($pdo, $userId, $linkId);
    } catch (RuntimeException $e) {
        throw new PeopleApiConflictException($e->getMessage());
    }
}

function stridebr_api_people_coaching_permissions(PDO $pdo, string $userId, string $linkId, array $payload): array
{
    if (!peopleFeatureEnabled($pdo, 'trainer.enabled', false)) throw new PeopleApiFeatureDisabledException('Treinador está temporariamente desativado.');
    try {
        return peopleCoachingPermissionsUpdate($pdo, $userId, $linkId, $payload);
    } catch (LogicException $e) {
        throw new PeopleApiForbiddenException($e->getMessage());
    } catch (RuntimeException $e) {
        throw new PeopleApiNotFoundException($e->getMessage());
    }
}
