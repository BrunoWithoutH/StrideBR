<?php

declare(strict_types=1);

require_once __DIR__ . '/atividade_modelo.php';
require_once __DIR__ . '/strength_activity.php';
require_once __DIR__ . '/workout_session_service.php';

final class MobileApiIdempotencyConflictException extends RuntimeException
{
}

final class MobileApiNotFoundException extends RuntimeException
{
}

function stridebr_api_mobile_canonical_value(mixed $value): mixed
{
    if (!is_array($value)) return $value;
    if (array_is_list($value)) return array_map('stridebr_api_mobile_canonical_value', $value);
    ksort($value);
    foreach ($value as $key => $item) $value[$key] = stridebr_api_mobile_canonical_value($item);
    return $value;
}

function stridebr_api_mobile_payload_hash(array $payload): string
{
    return hash('sha256', json_encode(stridebr_api_mobile_canonical_value($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

function stridebr_api_mobile_sport(PDO $pdo, string $userId, mixed $value): array
{
    $raw = trim((string) $value);
    if ($raw === '') throw new InvalidArgumentException('sport é obrigatório.');
    $stmt = $pdo->prepare("SELECT idmodalidade,nome,slug,familia_hub,categoria FROM modalidades WHERE ativo=TRUE AND (idusuario IS NULL OR idusuario=:user) AND (idmodalidade=:sport OR lower(slug)=lower(:sport)) ORDER BY CASE WHEN idmodalidade=:sport THEN 0 ELSE 1 END LIMIT 1");
    $stmt->execute([':user' => $userId, ':sport' => $raw]);
    $row = $stmt->fetch();
    if (!$row) throw new InvalidArgumentException('Modalidade inválida.');
    $row['family'] = function_exists('sportCatalogFamilyKey') ? sportCatalogFamilyKey((string) ($row['familia_hub'] ?? ''), (string) ($row['categoria'] ?? ''), (string) ($row['slug'] ?? '')) : (string) ($row['familia_hub'] ?? '');
    return $row;
}

function stridebr_api_mobile_iso_datetime(mixed $value, string $field): DateTimeImmutable
{
    $raw = trim((string) $value);
    if ($raw === '' || preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $raw) !== 1) throw new InvalidArgumentException($field . ' precisa usar ISO 8601 com timezone.');
    try {
        return new DateTimeImmutable($raw);
    } catch (Throwable) {
        throw new InvalidArgumentException($field . ' inválido.');
    }
}

function stridebr_api_mobile_model_values(array $fields, ?float $durationS, ?float $distanceM): array
{
    $record = [];
    $units = [['rotulo' => '', 'observacoes' => '', 'values' => []]];
    if ($durationS !== null) atividadeAplicarDuracaoCalculada($record, $units, $fields, $durationS);
    if ($distanceM !== null) {
        foreach ($fields as $field) {
            if (stridebr_api_lower(trim((string) ($field['slug'] ?? ''))) !== 'distancia') continue;
            $value = $distanceM;
            if (stridebr_api_lower(trim((string) ($field['unidade_simbolo'] ?? ''))) === 'km') $value = $distanceM / 1000;
            if (($field['escopo'] ?? '') === 'registro') $record[(string) $field['idcampo']] = round($value, 3);
            else $units[0]['values'][(string) $field['idcampo']] = round($value, 3);
            break;
        }
    }
    return ['record_values' => $record, 'unidades' => $units];
}

function stridebr_api_mobile_strength_input(array $source): array
{
    $out = [];
    foreach (array_slice($source, 0, 80) as $exercise) {
        if (!is_array($exercise)) continue;
        $sets = [];
        foreach (array_slice((array) ($exercise['sets'] ?? []), 0, 100) as $set) {
            if (!is_array($set)) continue;
            $sets[] = [
                'tipo' => $set['type'] ?? 'trabalho',
                'repeticoes' => $set['repetitions'] ?? null,
                'carga_kg' => $set['load_kg'] ?? null,
                'duracao_segundos' => $set['duration_s'] ?? null,
                'distancia_metros' => $set['distance_m'] ?? null,
                'rir' => $set['rir'] ?? null,
                'rpe' => $set['rpe'] ?? null,
                'concluida' => $set['completed'] ?? true,
                'observacoes' => $set['notes'] ?? null,
            ];
        }
        $out[] = [
            'idexercicio' => trim((string) ($exercise['exercise_id'] ?? '')),
            'nome' => trim((string) ($exercise['name'] ?? '')),
            'series' => $sets,
        ];
    }
    return $out;
}

function stridebr_api_mobile_strength_payload(PDO $pdo, string $userId, string $activityId): array
{
    $data = [];
    foreach (atividadeForcaBuscarSeries($pdo, $userId, $activityId) as $exercise) {
        $data[] = [
            'exercise_id' => trim((string) ($exercise['idexercicio'] ?? '')) ?: null,
            'name' => (string) ($exercise['nome'] ?? ''),
            'sets' => array_map(static fn(array $set): array => [
                'number' => (int) ($set['ordem_serie'] ?? 0),
                'type' => (string) ($set['tipo'] ?? 'trabalho'),
                'repetitions' => $set['repeticoes'] !== null ? (int) $set['repeticoes'] : null,
                'load_kg' => $set['carga_kg'] !== null ? (float) $set['carga_kg'] : null,
                'duration_s' => $set['duracao_segundos'] !== null ? (float) $set['duracao_segundos'] : null,
                'distance_m' => $set['distancia_metros'] !== null ? (float) $set['distancia_metros'] : null,
                'rir' => $set['rir'] !== null ? (float) $set['rir'] : null,
                'rpe' => $set['rpe'] !== null ? (float) $set['rpe'] : null,
                'completed' => !empty($set['concluida']),
                'notes' => trim((string) ($set['observacoes'] ?? '')) ?: null,
            ], (array) ($exercise['series'] ?? [])),
        ];
    }
    return $data;
}

function stridebr_api_mobile_activity_context(PDO $pdo, string $userId, string $activityId, bool $includeDeleted = false): array
{
    $deleted = $includeDeleted ? '' : ' AND ra.excluido_em IS NULL';
    $stmt = $pdo->prepare("SELECT ra.idregistro,ra.idusuario,ra.idmodalidade,ra.idmodelo,ra.titulo,ra.observacoes,ra.data_inicio,ra.data_fim,ra.visibilidade,ra.origem,ra.esforco_percebido,ra.data_atualizacao,ra.excluido_em,m.slug,m.familia_hub,m.categoria, EXISTS(SELECT 1 FROM rotas_atividade r WHERE r.idregistro=ra.idregistro) OR EXISTS(SELECT 1 FROM rotas_unidades_atividade ru JOIN unidades_atividade ua ON ua.idunidade_atividade=ru.idunidade_atividade WHERE ua.idregistro=ra.idregistro) AS has_route, EXISTS(SELECT 1 FROM sessoes_treino s WHERE s.idregistro_atividade=ra.idregistro AND s.idusuario=ra.idusuario) AS has_workout_session FROM registros_atividade ra JOIN modalidades m ON m.idmodalidade=ra.idmodalidade WHERE ra.idregistro=:id AND ra.idusuario=:user{$deleted} LIMIT 1");
    $stmt->execute([':id' => $activityId, ':user' => $userId]);
    return $stmt->fetch() ?: [];
}

function stridebr_api_mobile_activity_capabilities(array $context): array
{
    $origin = (string) ($context['origem'] ?? '');
    $hasRoute = stridebr_db_bool($context['has_route'] ?? false);
    $hasWorkout = stridebr_db_bool($context['has_workout_session'] ?? false);
    $structural = in_array($origin, ['manual', 'api'], true) && !$hasRoute && !$hasWorkout;
    return [
        'can_edit' => true,
        'can_delete' => true,
        'can_edit_title' => true,
        'can_edit_notes' => true,
        'can_edit_effort' => true,
        'can_edit_visibility' => true,
        'can_edit_datetime' => $structural,
        'can_edit_sport' => false,
        'can_edit_equipment' => true,
        'can_edit_metrics' => false,
        'can_edit_strength' => false,
        'can_trim_route' => false,
    ];
}

function stridebr_api_mobile_activity_enrich(PDO $pdo, string $userId, array $detail): array
{
    if ($detail === []) return [];
    $context = stridebr_api_mobile_activity_context($pdo, $userId, (string) $detail['id']);
    if ($context === []) return $detail;
    $detail['updated_at'] = stridebr_api_iso((string) ($context['data_atualizacao'] ?? ''));
    $detail['version'] = stridebr_api_training_version((string) $detail['id'], $context['data_atualizacao'] ?? null);
    $detail['capabilities'] = stridebr_api_mobile_activity_capabilities($context);
    if (($detail['sport']['family'] ?? '') === 'strength') $detail['strength_exercises'] = stridebr_api_mobile_strength_payload($pdo, $userId, (string) $detail['id']);
    return $detail;
}

function stridebr_api_mobile_activity_create(PDO $pdo, string $userId, array $payload, string $idempotencyKey): array
{
    $scope = 'activity_manual_create';
    $keyHash = hash('sha256', $idempotencyKey);
    $payloadHash = stridebr_api_mobile_payload_hash($payload);
    $owns = !$pdo->inTransaction();
    if ($owns) $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT pg_advisory_xact_lock(hashtext(:lock_key))');
        $lock->execute([':lock_key' => $userId . ':' . $scope . ':' . $keyHash]);
        $stored = $pdo->prepare('SELECT payload_hash,idregistro FROM api_workout_idempotencias WHERE idusuario=:user AND escopo=:scope AND chave_hash=:key LIMIT 1');
        $stored->execute([':user' => $userId, ':scope' => $scope, ':key' => $keyHash]);
        $existing = $stored->fetch();
        if ($existing) {
            if (!hash_equals((string) $existing['payload_hash'], $payloadHash)) throw new MobileApiIdempotencyConflictException('Idempotency-Key já foi usada com outro payload.');
            $detail = !empty($existing['idregistro']) ? stridebr_api_activity_detail($pdo, (string) $existing['idregistro'], $userId) : [];
            if ($detail === []) throw new RuntimeException('A atividade idempotente não está mais disponível.');
            if ($owns) $pdo->commit();
            return ['activity' => stridebr_api_mobile_activity_enrich($pdo, $userId, $detail), 'reused' => true];
        }

        $sport = stridebr_api_mobile_sport($pdo, $userId, $payload['sport'] ?? null);
        $model = atividadeBuscarModeloPadraoModalidade($pdo, (string) $sport['idmodalidade'], $userId);
        if ($model === []) throw new InvalidArgumentException('A modalidade não possui modelo disponível.');
        $started = stridebr_api_mobile_iso_datetime($payload['started_at'] ?? null, 'started_at');
        $ended = array_key_exists('ended_at', $payload) && $payload['ended_at'] !== null ? stridebr_api_mobile_iso_datetime($payload['ended_at'], 'ended_at') : null;
        $durationS = array_key_exists('duration_s', $payload) && $payload['duration_s'] !== null ? filter_var($payload['duration_s'], FILTER_VALIDATE_FLOAT) : null;
        if ($durationS === false || ($durationS !== null && ((float) $durationS < 0 || (float) $durationS > 604800))) throw new InvalidArgumentException('duration_s inválido.');
        if ($ended !== null && $ended < $started) throw new InvalidArgumentException('ended_at precisa ser igual ou posterior a started_at.');
        if ($ended === null && $durationS !== null) $ended = $started->modify('+' . (int) round((float) $durationS) . ' seconds');
        if ($ended !== null && $durationS === null) $durationS = max(0.0, (float) ($ended->getTimestamp() - $started->getTimestamp()));
        if ($ended !== null && $durationS !== null && abs(($ended->getTimestamp() - $started->getTimestamp()) - (float) $durationS) > 2) throw new InvalidArgumentException('ended_at e duration_s são inconsistentes.');
        $distanceM = array_key_exists('distance_m', $payload) && $payload['distance_m'] !== null ? filter_var($payload['distance_m'], FILTER_VALIDATE_FLOAT) : null;
        if ($distanceM === false || ($distanceM !== null && ((float) $distanceM < 0 || (float) $distanceM > 2000000))) throw new InvalidArgumentException('distance_m inválido.');
        $fields = atividadeBuscarCamposModelo($pdo, (string) $model['idmodelo']);
        $values = stridebr_api_mobile_model_values($fields, $durationS !== null ? (float) $durationS : null, $distanceM !== null ? (float) $distanceM : null);
        $local = new DateTimeZone('America/Sao_Paulo');
        $save = [
            'idmodelo' => (string) $model['idmodelo'],
            'titulo' => trim((string) ($payload['title'] ?? '')),
            'observacoes' => trim((string) ($payload['notes'] ?? '')),
            'data_inicio' => $started->setTimezone($local)->format('Y-m-d H:i:s'),
            'data_fim' => $ended?->setTimezone($local)->format('Y-m-d H:i:s') ?? '',
            'status' => 'concluido',
            'visibilidade' => trim((string) ($payload['visibility'] ?? '')),
            'esforco_percebido' => $payload['perceived_effort'] ?? '',
            'equipamentos' => is_array($payload['equipment_ids'] ?? null) ? $payload['equipment_ids'] : [],
            'record_values' => $values['record_values'],
            'unidades' => $values['unidades'],
            'usa_trechos' => false,
            'permitir_campos_vazios' => true,
            'origem' => 'manual',
        ];
        $activityId = atividadeSalvarRegistro($pdo, $userId, $save);
        if (($sport['family'] ?? '') === 'strength' && is_array($payload['strength_exercises'] ?? null)) atividadeForcaPersistirSeriesManuais($pdo, $userId, $activityId, stridebr_api_mobile_strength_input($payload['strength_exercises']));
        if (!empty($payload['workout_id'])) {
            $link = stridebr_api_workout_prepare_activity_link($pdo, $userId, trim((string) $payload['workout_id']), (string) $sport['idmodalidade']);
            stridebr_api_workout_link_activity($pdo, $userId, $activityId, $link);
        }
        $insert = $pdo->prepare('INSERT INTO api_workout_idempotencias (idusuario,escopo,chave_hash,payload_hash,recurso_id,idregistro) VALUES (:user,:scope,:key,:payload,:resource,:activity)');
        $insert->execute([':user' => $userId, ':scope' => $scope, ':key' => $keyHash, ':payload' => $payloadHash, ':resource' => $activityId, ':activity' => $activityId]);
        $detail = stridebr_api_activity_detail($pdo, $activityId, $userId);
        if ($owns) $pdo->commit();
        return ['activity' => stridebr_api_mobile_activity_enrich($pdo, $userId, $detail), 'reused' => false];
    } catch (Throwable $e) {
        if ($owns && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function stridebr_api_mobile_replace_activity_equipment(PDO $pdo, string $userId, string $activityId, array $equipmentIds): void
{
    $ids = array_values(array_unique(array_filter(array_map(static fn(mixed $v): string => trim((string) $v), $equipmentIds))));
    if ($ids !== []) {
        $placeholders = [];
        $params = [':user' => $userId];
        foreach ($ids as $i => $id) {
            $key = ':e' . $i;
            $placeholders[] = $key;
            $params[$key] = $id;
        }
        $stmt = $pdo->prepare('SELECT idequipamento FROM equipamentos_usuario WHERE idusuario=:user AND ativo=TRUE AND idequipamento IN (' . implode(',', $placeholders) . ')');
        $stmt->execute($params);
        if (count($stmt->fetchAll(PDO::FETCH_COLUMN)) !== count($ids)) throw new InvalidArgumentException('Um dos equipamentos não está disponível.');
    }
    $pdo->prepare('DELETE FROM registros_atividade_equipamentos WHERE idregistro=:activity')->execute([':activity' => $activityId]);
    if ($ids !== []) {
        $insert = $pdo->prepare('INSERT INTO registros_atividade_equipamentos (idregistro,idequipamento) VALUES (:activity,:equipment) ON CONFLICT DO NOTHING');
        foreach ($ids as $id) $insert->execute([':activity' => $activityId, ':equipment' => $id]);
    }
}

function stridebr_api_mobile_activity_patch(PDO $pdo, string $userId, string $activityId, array $payload): array
{
    $context = stridebr_api_mobile_activity_context($pdo, $userId, $activityId);
    if ($context === []) throw new MobileApiNotFoundException('Atividade não encontrada.');
    stridebr_api_training_assert_version($activityId, $context['data_atualizacao'] ?? null, $payload);
    $cap = stridebr_api_mobile_activity_capabilities($context);
    foreach (['sport','distance_m','duration_s','strength_exercises'] as $unsupported) {
        if (array_key_exists($unsupported, $payload) && !in_array($unsupported, ['duration_s'], true)) throw new InvalidArgumentException($unsupported . ' não é editável neste contrato.');
    }
    $sets = [];
    $params = [':id' => $activityId, ':user' => $userId];
    if (array_key_exists('title', $payload)) {
        $title = trim((string) $payload['title']);
        if ($title === '' || stridebr_length($title) > 255) throw new InvalidArgumentException('title inválido.');
        $sets[] = 'titulo=:title'; $params[':title'] = $title;
    }
    if (array_key_exists('notes', $payload)) {
        $notes = trim((string) ($payload['notes'] ?? ''));
        $sets[] = 'observacoes=:notes'; $params[':notes'] = $notes !== '' ? $notes : null;
    }
    if (array_key_exists('perceived_effort', $payload)) {
        $rpe = $payload['perceived_effort'];
        if ($rpe !== null && (filter_var($rpe, FILTER_VALIDATE_INT) === false || (int) $rpe < 1 || (int) $rpe > 10)) throw new InvalidArgumentException('perceived_effort inválido.');
        $sets[] = 'esforco_percebido=:effort'; $params[':effort'] = $rpe !== null ? (int) $rpe : null;
    }
    if (array_key_exists('visibility', $payload)) {
        $visibility = trim((string) $payload['visibility']);
        if (!in_array($visibility, ['privado','amigos','publico'], true)) throw new InvalidArgumentException('visibility inválida.');
        $sets[] = 'visibilidade=:visibility'; $params[':visibility'] = $visibility;
    }
    $hasDatetime = array_key_exists('started_at', $payload) || array_key_exists('ended_at', $payload) || array_key_exists('duration_s', $payload);
    if ($hasDatetime) {
        if (empty($cap['can_edit_datetime'])) throw new InvalidArgumentException('Data e duração não podem ser alteradas nesta atividade.');
        $started = array_key_exists('started_at', $payload) ? stridebr_api_mobile_iso_datetime($payload['started_at'], 'started_at') : new DateTimeImmutable((string) $context['data_inicio']);
        $ended = array_key_exists('ended_at', $payload) && $payload['ended_at'] !== null ? stridebr_api_mobile_iso_datetime($payload['ended_at'], 'ended_at') : (!empty($context['data_fim']) ? new DateTimeImmutable((string) $context['data_fim']) : null);
        $duration = array_key_exists('duration_s', $payload) && $payload['duration_s'] !== null ? filter_var($payload['duration_s'], FILTER_VALIDATE_FLOAT) : null;
        if ($duration === false || ($duration !== null && (float) $duration < 0)) throw new InvalidArgumentException('duration_s inválido.');
        if ($duration !== null && !array_key_exists('ended_at', $payload)) $ended = $started->modify('+' . (int) round((float) $duration) . ' seconds');
        if ($ended !== null && $ended < $started) throw new InvalidArgumentException('ended_at precisa ser igual ou posterior a started_at.');
        $local = new DateTimeZone('America/Sao_Paulo');
        $sets[] = 'data_inicio=:started'; $params[':started'] = $started->setTimezone($local)->format('Y-m-d H:i:s');
        $sets[] = 'data_fim=:ended'; $params[':ended'] = $ended?->setTimezone($local)->format('Y-m-d H:i:s');
    }
    $owns = !$pdo->inTransaction();
    if ($owns) $pdo->beginTransaction();
    try {
        if ($sets !== []) {
            $sets[] = 'data_atualizacao=NOW()';
            $stmt = $pdo->prepare('UPDATE registros_atividade SET ' . implode(',', $sets) . ' WHERE idregistro=:id AND idusuario=:user AND excluido_em IS NULL');
            $stmt->execute($params);
        }
        if (array_key_exists('equipment_ids', $payload)) {
            if (!is_array($payload['equipment_ids'])) throw new InvalidArgumentException('equipment_ids precisa ser uma lista.');
            stridebr_api_mobile_replace_activity_equipment($pdo, $userId, $activityId, $payload['equipment_ids']);
            $pdo->prepare('UPDATE registros_atividade SET data_atualizacao=NOW() WHERE idregistro=:id AND idusuario=:user')->execute([':id' => $activityId, ':user' => $userId]);
        }
        $detail = stridebr_api_activity_detail($pdo, $activityId, $userId);
        if ($owns) $pdo->commit();
        return stridebr_api_mobile_activity_enrich($pdo, $userId, $detail);
    } catch (Throwable $e) {
        if ($owns && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function stridebr_api_mobile_activity_delete(PDO $pdo, string $userId, string $activityId): array
{
    $context = stridebr_api_mobile_activity_context($pdo, $userId, $activityId, true);
    if ($context === []) throw new MobileApiNotFoundException('Atividade não encontrada.');
    if (!empty($context['excluido_em'])) return ['id' => $activityId, 'deleted' => true, 'reused' => true];
    if (!atividadeExcluirRegistro($pdo, $activityId, $userId)) throw new RuntimeException('Não foi possível excluir a atividade.');
    return ['id' => $activityId, 'deleted' => true, 'reused' => false];
}

function stridebr_api_mobile_me(PDO $pdo, string $userId): array
{
    $stmt = $pdo->prepare('SELECT idusuario,nomeusuario,nome_exibicao,username,emailusuario,fotousuario,papelusuario,onboarding_concluido,preferenciasusuario,biousuario,foneusuario,datanascimentousuario,visibilidadeperfil,descobrivel FROM usuarios WHERE idusuario=:user LIMIT 1');
    $stmt->execute([':user' => $userId]);
    $row = $stmt->fetch();
    if (!$row) throw new MobileApiNotFoundException('Usuário não encontrado.');
    $payload = stridebr_api_user_payload($row);
    $payload['bio'] = trim((string) ($row['biousuario'] ?? '')) ?: null;
    $payload['phone'] = trim((string) ($row['foneusuario'] ?? '')) ?: null;
    $payload['birth_date'] = $row['datanascimentousuario'] !== null ? (string) $row['datanascimentousuario'] : null;
    $payload['profile_visibility'] = (string) ($row['visibilidadeperfil'] ?? 'privado');
    $payload['discoverable'] = stridebr_db_bool($row['descobrivel'] ?? true);
    return $payload;
}

function stridebr_api_mobile_username_valid(string $username): bool
{
    if ($username === '' || strlen($username) < 3 || strlen($username) > 30 || preg_match('/^[a-z0-9](?:[a-z0-9._-]*[a-z0-9])?$/D', $username) !== 1) return false;
    return !in_array($username, ['admin','api','app','auth','login','logout','me','user','users','usuario','usuarios','stridebr','support','suporte','www'], true);
}

function stridebr_api_mobile_profile_patch(PDO $pdo, string $userId, array $payload): array
{
    foreach (['email','password','new_password','delete_account'] as $field) if (array_key_exists($field, $payload)) throw new InvalidArgumentException($field . ' não pode ser alterado por este endpoint.');
    $sets = [];
    $params = [':user' => $userId];
    if (array_key_exists('name', $payload)) {
        $name = preg_replace('/\s+/u', ' ', trim((string) $payload['name'])) ?? '';
        if ($name === '' || stridebr_length($name) > 60) throw new InvalidArgumentException('name inválido.');
        $sets[] = 'nome_exibicao=:name'; $params[':name'] = $name;
    }
    if (array_key_exists('username', $payload)) {
        $username = stridebr_api_lower(trim((string) ($payload['username'] ?? '')));
        if ($username !== '' && !stridebr_api_mobile_username_valid($username)) throw new InvalidArgumentException('username inválido.');
        if ($username !== '') {
            $check = $pdo->prepare('SELECT 1 FROM usuarios WHERE lower(username)=lower(:username) AND idusuario<>:user LIMIT 1');
            $check->execute([':username' => $username, ':user' => $userId]);
            if ($check->fetchColumn()) throw new InvalidArgumentException('username já está em uso.');
        }
        $sets[] = 'username=:username'; $params[':username'] = $username !== '' ? $username : null;
    }
    if (array_key_exists('bio', $payload)) {
        $bio = trim((string) ($payload['bio'] ?? ''));
        if (stridebr_length($bio) > 1000) throw new InvalidArgumentException('bio é muito longa.');
        $sets[] = 'biousuario=:bio'; $params[':bio'] = $bio !== '' ? $bio : null;
    }
    if (array_key_exists('phone', $payload)) {
        $phone = trim((string) ($payload['phone'] ?? ''));
        if (strlen($phone) > 20) throw new InvalidArgumentException('phone inválido.');
        $sets[] = 'foneusuario=:phone'; $params[':phone'] = $phone !== '' ? $phone : null;
    }
    if (array_key_exists('birth_date', $payload)) {
        $birth = trim((string) ($payload['birth_date'] ?? ''));
        if ($birth !== '') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $birth);
            if (!$date || $date->format('Y-m-d') !== $birth || $date > new DateTimeImmutable('today')) throw new InvalidArgumentException('birth_date inválida.');
        }
        $sets[] = 'datanascimentousuario=:birth'; $params[':birth'] = $birth !== '' ? $birth : null;
    }
    if ($sets !== []) {
        $stmt = $pdo->prepare('UPDATE usuarios SET ' . implode(',', $sets) . ' WHERE idusuario=:user');
        $stmt->execute($params);
    }
    return stridebr_api_mobile_me($pdo, $userId);
}

function stridebr_api_mobile_privacy(PDO $pdo, string $userId): array
{
    $stmt = $pdo->prepare('SELECT preferenciasusuario,visibilidadeperfil,descobrivel FROM usuarios WHERE idusuario=:user LIMIT 1');
    $stmt->execute([':user' => $userId]);
    $row = $stmt->fetch();
    if (!$row) throw new MobileApiNotFoundException('Usuário não encontrado.');
    $prefs = is_array($row['preferenciasusuario']) ? $row['preferenciasusuario'] : (json_decode((string) ($row['preferenciasusuario'] ?? '{}'), true) ?: []);
    $defaults = is_array($prefs['activity_defaults'] ?? null) ? $prefs['activity_defaults'] : [];
    return [
        'default_activity_visibility' => in_array(($defaults['visibility'] ?? ''), ['privado','amigos','publico'], true) ? (string) $defaults['visibility'] : 'privado',
        'hide_route_start_m' => max(0, min(10000, (int) ($defaults['hide_route_start_m'] ?? 0))),
        'hide_route_end_m' => max(0, min(10000, (int) ($defaults['hide_route_end_m'] ?? ($defaults['hide_route_start_m'] ?? 0)))),
        'profile_visibility' => (string) ($row['visibilidadeperfil'] ?? 'privado'),
        'discoverable' => stridebr_db_bool($row['descobrivel'] ?? true),
    ];
}

function stridebr_api_mobile_privacy_patch(PDO $pdo, string $userId, array $payload): array
{
    $current = stridebr_api_mobile_privacy($pdo, $userId);
    $visibility = array_key_exists('default_activity_visibility', $payload) ? trim((string) $payload['default_activity_visibility']) : $current['default_activity_visibility'];
    $profile = array_key_exists('profile_visibility', $payload) ? trim((string) $payload['profile_visibility']) : $current['profile_visibility'];
    if (!in_array($visibility, ['privado','amigos','publico'], true) || !in_array($profile, ['privado','amigos','publico'], true)) throw new InvalidArgumentException('Visibilidade inválida.');
    $start = array_key_exists('hide_route_start_m', $payload) ? filter_var($payload['hide_route_start_m'], FILTER_VALIDATE_INT) : $current['hide_route_start_m'];
    $end = array_key_exists('hide_route_end_m', $payload) ? filter_var($payload['hide_route_end_m'], FILTER_VALIDATE_INT) : $current['hide_route_end_m'];
    if ($start === false || $end === false || $start < 0 || $start > 10000 || $end < 0 || $end > 10000) throw new InvalidArgumentException('Privacidade de rota inválida.');
    $discoverable = array_key_exists('discoverable', $payload) ? filter_var($payload['discoverable'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : $current['discoverable'];
    if ($discoverable === null) throw new InvalidArgumentException('discoverable inválido.');
    $stmt = $pdo->prepare('SELECT preferenciasusuario FROM usuarios WHERE idusuario=:user LIMIT 1');
    $stmt->execute([':user' => $userId]);
    $raw = $stmt->fetchColumn();
    $prefs = is_array($raw) ? $raw : (json_decode((string) ($raw ?: '{}'), true) ?: []);
    $prefs['activity_defaults'] = ['visibility' => $visibility, 'hide_route_start_m' => (int) $start, 'hide_route_end_m' => (int) $end];
    $update = $pdo->prepare('UPDATE usuarios SET preferenciasusuario=CAST(:prefs AS jsonb),visibilidadeperfil=:profile,descobrivel=:discoverable WHERE idusuario=:user');
    $update->bindValue(':prefs', json_encode($prefs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $update->bindValue(':profile', $profile);
    $update->bindValue(':discoverable', $discoverable, PDO::PARAM_BOOL);
    $update->bindValue(':user', $userId);
    $update->execute();
    return stridebr_api_mobile_privacy($pdo, $userId);
}

function stridebr_api_mobile_equipment_payload(array $row): array
{
    return [
        'id' => (string) $row['idequipamento'],
        'name' => (string) $row['nome'],
        'category' => (string) ($row['categoria'] ?? 'outro'),
        'brand' => trim((string) ($row['marca'] ?? '')) ?: null,
        'model' => trim((string) ($row['modelo'] ?? '')) ?: null,
        'started_on' => ($row['data_inicio_uso'] ?? null) !== null ? (string) $row['data_inicio_uso'] : null,
        'retired_on' => ($row['data_fim_uso'] ?? null) !== null ? (string) $row['data_fim_uso'] : null,
        'initial_distance_km' => is_numeric($row['distancia_inicial_km'] ?? null) ? (float) $row['distancia_inicial_km'] : 0.0,
        'distance_alert_km' => is_numeric($row['limite_alerta_km'] ?? null) ? (float) $row['limite_alerta_km'] : null,
        'notes' => trim((string) ($row['observacoes'] ?? '')) ?: null,
        'active' => stridebr_db_bool($row['ativo'] ?? false),
    ];
}

function stridebr_api_mobile_equipment_list(PDO $pdo, string $userId): array
{
    $stmt = $pdo->prepare('SELECT * FROM equipamentos_usuario WHERE idusuario=:user ORDER BY ativo DESC,nome ASC,idequipamento ASC');
    $stmt->execute([':user' => $userId]);
    return array_map('stridebr_api_mobile_equipment_payload', $stmt->fetchAll());
}

function stridebr_api_mobile_equipment_get(PDO $pdo, string $userId, string $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM equipamentos_usuario WHERE idequipamento=:id AND idusuario=:user LIMIT 1');
    $stmt->execute([':id' => $id, ':user' => $userId]);
    $row = $stmt->fetch();
    if (!$row) throw new MobileApiNotFoundException('Equipamento não encontrado.');
    return stridebr_api_mobile_equipment_payload($row);
}

function stridebr_api_mobile_equipment_save(PDO $pdo, string $userId, array $payload, ?string $id = null): array
{
    $existing = $id !== null ? stridebr_api_mobile_equipment_get($pdo, $userId, $id) : null;
    $mapped = [
        'nome' => $payload['name'] ?? ($existing['name'] ?? ''),
        'categoria' => $payload['category'] ?? ($existing['category'] ?? 'outro'),
        'marca' => $payload['brand'] ?? ($existing['brand'] ?? ''),
        'modelo' => $payload['model'] ?? ($existing['model'] ?? ''),
        'data_inicio_uso' => $payload['started_on'] ?? ($existing['started_on'] ?? ''),
        'data_fim_uso' => $payload['retired_on'] ?? ($existing['retired_on'] ?? ''),
        'distancia_inicial_km' => $payload['initial_distance_km'] ?? ($existing['initial_distance_km'] ?? 0),
        'limite_alerta_km' => $payload['distance_alert_km'] ?? ($existing['distance_alert_km'] ?? ''),
        'observacoes' => $payload['notes'] ?? ($existing['notes'] ?? ''),
    ];
    $saved = atividadeSalvarEquipamento($pdo, $userId, $mapped, $id);
    if (array_key_exists('active', $payload)) {
        $active = filter_var($payload['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($active === null) throw new InvalidArgumentException('active inválido.');
        atividadeDefinirEquipamentoAtivo($pdo, $userId, $saved, $active);
    }
    return stridebr_api_mobile_equipment_get($pdo, $userId, $saved);
}

function stridebr_api_mobile_equipment_delete(PDO $pdo, string $userId, string $id): array
{
    $equipment = stridebr_api_mobile_equipment_get($pdo, $userId, $id);
    if (!$equipment['active']) return ['id' => $id, 'deleted' => true, 'reused' => true];
    atividadeDefinirEquipamentoAtivo($pdo, $userId, $id, false);
    return ['id' => $id, 'deleted' => true, 'reused' => false];
}

function stridebr_api_mobile_append_set(PDO $pdo, string $userId, string $sessionId, string $exerciseId, string $idempotencyKey): array
{
    $scope = 'workout_append_set';
    $keyHash = hash('sha256', $idempotencyKey);
    $payloadHash = stridebr_api_mobile_payload_hash(['session_id' => $sessionId, 'exercise_id' => $exerciseId]);
    $owns = !$pdo->inTransaction();
    if ($owns) $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT pg_advisory_xact_lock(hashtext(:lock_key))');
        $lock->execute([':lock_key' => $userId . ':' . $scope . ':' . $keyHash]);
        $stored = $pdo->prepare('SELECT payload_hash,recurso_id FROM api_workout_idempotencias WHERE idusuario=:user AND escopo=:scope AND chave_hash=:key LIMIT 1');
        $stored->execute([':user' => $userId, ':scope' => $scope, ':key' => $keyHash]);
        $row = $stored->fetch();
        if ($row) {
            if (!hash_equals((string) $row['payload_hash'], $payloadHash)) throw new MobileApiIdempotencyConflictException('Idempotency-Key já foi usada com outro payload.');
            $session = sessaoCarregarPorId($pdo, $userId, $sessionId, true);
            if ($session === []) throw new MobileApiNotFoundException('Sessão não encontrada.');
            if ($owns) $pdo->commit();
            return ['session' => stridebr_api_workout_session_payload($session), 'set_id' => (string) $row['recurso_id'], 'reused' => true];
        }
        $exercise = $pdo->prepare("SELECT se.idsessao_exercicio,s.status FROM sessoes_treino_exercicios se JOIN sessoes_treino s ON s.idsessao=se.idsessao WHERE se.idsessao_exercicio=:exercise AND se.idsessao=:session AND s.idusuario=:user FOR UPDATE OF se,s");
        $exercise->execute([':exercise' => $exerciseId, ':session' => $sessionId, ':user' => $userId]);
        $owned = $exercise->fetch();
        if (!$owned) throw new MobileApiNotFoundException('Exercício da sessão não encontrado.');
        if ((string) $owned['status'] !== 'ativo') throw new RuntimeException('A sessão não está ativa.');
        $next = $pdo->prepare('SELECT COALESCE(MAX(numero),0)+1 FROM sessoes_treino_series WHERE idsessao_exercicio=:exercise');
        $next->execute([':exercise' => $exerciseId]);
        $number = (int) $next->fetchColumn();
        $setId = atividadeGerarId();
        $insertSet = $pdo->prepare('INSERT INTO sessoes_treino_series (idserie,idsessao_exercicio,numero) VALUES (:id,:exercise,:number)');
        $insertSet->execute([':id' => $setId, ':exercise' => $exerciseId, ':number' => $number]);
        $saveKey = $pdo->prepare('INSERT INTO api_workout_idempotencias (idusuario,escopo,chave_hash,payload_hash,recurso_id) VALUES (:user,:scope,:key,:payload,:resource)');
        $saveKey->execute([':user' => $userId, ':scope' => $scope, ':key' => $keyHash, ':payload' => $payloadHash, ':resource' => $setId]);
        $session = sessaoCarregarPorId($pdo, $userId, $sessionId, true);
        if ($owns) $pdo->commit();
        return ['session' => stridebr_api_workout_session_payload($session), 'set_id' => $setId, 'reused' => false];
    } catch (Throwable $e) {
        if ($owns && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function stridebr_api_mobile_execution_summary(PDO $pdo, string $userId, string $workoutId): array
{
    $parsed = stridebr_api_workout_parse_id($workoutId);
    $where = '';
    $params = [':user' => $userId];
    if ($parsed['kind'] === 'scheduled') {
        $where = 's.idagendamento_origem=:source';
        $params[':source'] = $parsed['id'];
    } elseif ($parsed['kind'] === 'recurring') {
        $where = 's.idtreino_origem=:source AND COALESCE(s.data_ocorrencia_origem,s.data_ocorrencia_planejada)=CAST(:date AS date)';
        $params[':source'] = $parsed['id'];
        $params[':date'] = $parsed['date'];
    } else {
        return ['available' => false, 'execution_mode' => null, 'workout_id' => $workoutId];
    }
    $stmt = $pdo->prepare("SELECT s.idsessao FROM sessoes_treino s WHERE s.idusuario=:user AND s.status='concluido' AND {$where} ORDER BY s.data_fim DESC NULLS LAST,s.data_inicio DESC LIMIT 1");
    $stmt->execute($params);
    $sessionId = $stmt->fetchColumn();
    if ($sessionId === false) {
        $detail = stridebr_api_workout_detail($pdo, $userId, $workoutId);
        if (!empty($detail['activity']['id'])) return ['available' => false, 'execution_mode' => 'quick_register', 'workout_id' => $workoutId, 'activity' => ['id' => (string) $detail['activity']['id']]];
        return ['available' => false, 'execution_mode' => null, 'workout_id' => $workoutId];
    }
    $session = sessaoCarregarPorId($pdo, $userId, (string) $sessionId, false);
    $exercises = [];
    foreach ((array) ($session['exercicios'] ?? []) as $exercise) {
        $sets = [];
        foreach ((array) ($exercise['series'] ?? []) as $set) {
            $hasActual = trim((string) ($set['repeticoes_realizadas'] ?? '')) !== '' || trim((string) ($set['carga_realizada'] ?? '')) !== '' || $set['duracao_realizada_s'] !== null || $set['distancia_realizada_m'] !== null;
            if (!$hasActual && !stridebr_db_bool($set['concluida'] ?? false)) continue;
            $sets[] = [
                'id' => (string) $set['idserie'],
                'number' => (int) $set['numero'],
                'repetitions' => trim((string) ($set['repeticoes_realizadas'] ?? '')) ?: null,
                'load' => trim((string) ($set['carga_realizada'] ?? '')) ?: null,
                'duration_s' => is_numeric($set['duracao_realizada_s'] ?? null) ? (int) $set['duracao_realizada_s'] : null,
                'distance_m' => is_numeric($set['distancia_realizada_m'] ?? null) ? (float) $set['distancia_realizada_m'] : null,
                'completed' => stridebr_db_bool($set['concluida'] ?? false),
            ];
        }
        if ($sets === [] && !stridebr_db_bool($exercise['concluido'] ?? false)) continue;
        $exercises[] = ['id' => (string) $exercise['idsessao_exercicio'], 'exercise_id' => !empty($exercise['idexercicio']) ? (string) $exercise['idexercicio'] : null, 'name' => (string) $exercise['nome_snapshot'], 'sets' => $sets];
    }
    return [
        'available' => true,
        'execution_mode' => 'session',
        'workout_id' => $workoutId,
        'session_id' => (string) $session['idsessao'],
        'started_at' => stridebr_api_iso((string) ($session['data_inicio'] ?? '')),
        'ended_at' => stridebr_api_iso((string) ($session['data_fim'] ?? '')),
        'activity' => !empty($session['idregistro_atividade']) ? ['id' => (string) $session['idregistro_atividade']] : null,
        'exercises' => $exercises,
    ];
}
