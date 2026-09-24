<?php

declare(strict_types=1);

require_once __DIR__ . '/training_platform_service.php';
require_once __DIR__ . '/workout_session_service.php';

final class TrainingPlatformVersionConflictException extends RuntimeException
{
}

function stridebr_api_training_optional_idempotency_key(): ?string
{
    $key = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
    if ($key === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strcasecmp((string) $name, 'Idempotency-Key') === 0) {
                    $key = trim((string) $value);
                    break;
                }
            }
        }
    }
    if ($key === '') return null;
    if (strlen($key) < 8 || strlen($key) > 128 || preg_match('/^[A-Za-z0-9._:-]+$/D', $key) !== 1) {
        throw new InvalidArgumentException('Idempotency-Key inválida. Use entre 8 e 128 caracteres ASCII seguros.');
    }
    return $key;
}

function stridebr_api_training_version(string $id, mixed $updatedAt): ?string
{
    $raw = trim((string) $updatedAt);
    if ($raw === '') return null;
    return substr(hash('sha256', $id . '|' . $raw), 0, 24);
}

function stridebr_api_training_assert_version(string $id, mixed $updatedAt, array $payload): void
{
    $expected = trim((string) ($payload['if_version'] ?? ''));
    if ($expected === '') return;
    $current = stridebr_api_training_version($id, $updatedAt);
    if ($current === null || !hash_equals($current, $expected)) throw new TrainingPlatformVersionConflictException('O recurso foi alterado em outro dispositivo. Atualize os dados antes de salvar novamente.');
}

function stridebr_api_training_text_seconds(?string $value): ?int
{
    $raw = trim((string) $value);
    if ($raw === '') return null;
    if (preg_match('/^(\d{1,5})\s*s(?:eg(?:undos?)?)?$/i', $raw, $match) === 1) return min(86400, (int) $match[1]);
    if (preg_match('/^(\d{1,3}):(\d{2})$/', $raw, $match) === 1) {
        $seconds = ((int) $match[1]) * 60 + (int) $match[2];
        return $seconds <= 86400 ? $seconds : null;
    }
    return null;
}

function stridebr_api_training_structure_payload(array $exercises): array
{
    $blocks = [];
    $groups = [];
    foreach ($exercises as $exercise) {
        $label = $exercise['block'] ?? null;
        $key = $label === null ? '__unblocked__' : (string) $label;
        if (!isset($blocks[$key])) $blocks[$key] = ['label' => $label, 'exercises' => []];
        $blocks[$key]['exercises'][] = $exercise;
        if (is_array($exercise['group'] ?? null) && !empty($exercise['group']['id'])) {
            $groupId = (string) $exercise['group']['id'];
            if (!isset($groups[$groupId])) $groups[$groupId] = $exercise['group'] + ['exercises' => []];
            $groups[$groupId]['exercises'][] = $exercise['id'];
        }
    }
    return ['exercise_count' => count($exercises), 'exercises' => $exercises, 'blocks' => array_values($blocks), 'groups' => array_values($groups)];
}

function stridebr_api_training_decode_json_array(mixed $value): array
{
    if (is_array($value)) return array_values($value);
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? array_values($decoded) : [];
}


function stridebr_api_training_workout_create(PDO $pdo, string $userId, array $payload, ?string $idempotencyKey = null): array
{
    if ($idempotencyKey === null) return ['workout' => stridebr_api_workout_create($pdo, $userId, $payload), 'reused' => false];
    $scope = 'workout_create';
    $keyHash = hash('sha256', $idempotencyKey);
    $payloadHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $owns = !$pdo->inTransaction();
    if ($owns) $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT pg_advisory_xact_lock(hashtext(:lock_key))');
        $lock->execute([':lock_key' => $userId . ':' . $scope . ':' . $keyHash]);
        $existing = $pdo->prepare('SELECT payload_hash,recurso_id FROM api_workout_idempotencias WHERE idusuario=:user AND escopo=:scope AND chave_hash=:key LIMIT 1');
        $existing->execute([':user' => $userId, ':scope' => $scope, ':key' => $keyHash]);
        $row = $existing->fetch();
        if ($row) {
            if (!hash_equals((string) $row['payload_hash'], $payloadHash)) throw new WorkoutSessionIdempotencyConflictException('Idempotency-Key já foi usada com outro payload.');
            $workout = stridebr_api_workout_detail($pdo, $userId, (string) $row['recurso_id']);
            if ($workout === []) throw new RuntimeException('O recurso idempotente não está mais disponível.');
            if ($owns) $pdo->commit();
            return ['workout' => $workout, 'reused' => true];
        }
        $workout = stridebr_api_workout_create($pdo, $userId, $payload);
        $insert = $pdo->prepare('INSERT INTO api_workout_idempotencias (idusuario,escopo,chave_hash,payload_hash,recurso_id) VALUES (:user,:scope,:key,:payload,:resource)');
        $insert->execute([':user' => $userId, ':scope' => $scope, ':key' => $keyHash, ':payload' => $payloadHash, ':resource' => (string) $workout['id']]);
        if ($owns) $pdo->commit();
        return ['workout' => $workout, 'reused' => false];
    } catch (Throwable $e) {
        if ($owns && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function stridebr_api_training_exercise_payload(array $row): array
{
    $categories = stridebr_api_training_decode_json_array($row['categories_json'] ?? []);
    $sports = stridebr_api_training_decode_json_array($row['sports_json'] ?? []);
    return [
        'id' => (string) $row['idexercicio'],
        'name' => (string) $row['nome'],
        'description' => $row['descricao'] !== null ? (string) $row['descricao'] : null,
        'custom' => $row['idusuario'] !== null,
        'owner_id' => $row['idusuario'] !== null ? (string) $row['idusuario'] : null,
        'categories' => $categories,
        'sports' => $sports,
        'primary_muscles' => stridebr_api_training_decode_json_array($row['grupos_musculares_primarios'] ?? []),
        'secondary_muscles' => stridebr_api_training_decode_json_array($row['grupos_musculares_secundarios'] ?? []),
        'media' => [
            'image_url' => $row['imagem_url'] !== null ? (string) $row['imagem_url'] : null,
            'video_url' => $row['video_url'] !== null ? (string) $row['video_url'] : null,
        ],
        'created_at' => stridebr_api_iso(isset($row['data_criacao']) ? (string) $row['data_criacao'] : null),
        'updated_at' => stridebr_api_iso(isset($row['data_atualizacao']) ? (string) $row['data_atualizacao'] : null),
        'version' => stridebr_api_training_version((string) $row['idexercicio'], $row['data_atualizacao'] ?? null),
    ];
}

function stridebr_api_training_exercise_query_base(): string
{
    return "SELECT e.*,
        COALESCE((SELECT jsonb_agg(jsonb_build_object('id',c.idcategoria,'name',c.nome,'slug',c.slug) ORDER BY c.nome) FROM exercicios_categorias ec JOIN categorias_exercicio c ON c.idcategoria=ec.idcategoria AND c.ativo=TRUE WHERE ec.idexercicio=e.idexercicio),'[]'::jsonb) AS categories_json,
        COALESCE((SELECT jsonb_agg(jsonb_build_object('id',m.idmodalidade,'name',m.nome,'slug',m.slug) ORDER BY m.nome) FROM exercicios_modalidades em JOIN modalidades m ON m.idmodalidade=em.idmodalidade AND m.ativo=TRUE WHERE em.idexercicio=e.idexercicio),'[]'::jsonb) AS sports_json
      FROM exercicios e";
}

function stridebr_api_training_exercises(PDO $pdo, string $userId, array $filters): array
{
    $page = max(1, min(100000, (int) ($filters['page'] ?? 1)));
    $limit = max(1, min(100, (int) ($filters['limit'] ?? 30)));
    $q = trim((string) ($filters['q'] ?? $filters['search'] ?? ''));
    $category = trim((string) ($filters['category'] ?? ''));
    $muscle = stridebr_lower(trim((string) ($filters['muscle'] ?? '')));
    $sportRaw = trim((string) ($filters['sport'] ?? ''));
    $sport = $sportRaw !== '' ? stridebr_api_workout_modality($pdo, $userId, $sportRaw) : null;
    $where = ['e.ativo=TRUE', '(e.idusuario IS NULL OR e.idusuario=:user)'];
    $params = [':user' => $userId];
    if ($q !== '') {
        $where[] = '(e.nome ILIKE :q OR e.descricao ILIKE :q)';
        $params[':q'] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
    }
    if ($category !== '') {
        $where[] = 'EXISTS (SELECT 1 FROM exercicios_categorias ec JOIN categorias_exercicio c ON c.idcategoria=ec.idcategoria WHERE ec.idexercicio=e.idexercicio AND c.ativo=TRUE AND (c.idcategoria=:category OR lower(c.slug)=lower(:category_slug)))';
        $params[':category'] = $category;
        $params[':category_slug'] = $category;
    }
    if ($sport !== null) {
        $where[] = 'EXISTS (SELECT 1 FROM exercicios_modalidades em WHERE em.idexercicio=e.idexercicio AND em.idmodalidade=:sport)';
        $params[':sport'] = (string) $sport['idmodalidade'];
    }
    if ($muscle !== '') {
        $where[] = '(jsonb_exists(e.grupos_musculares_primarios,:muscle) OR jsonb_exists(e.grupos_musculares_secundarios,:muscle))';
        $params[':muscle'] = $muscle;
    }
    $condition = implode(' AND ', $where);
    $count = $pdo->prepare('SELECT COUNT(*) FROM exercicios e WHERE ' . $condition);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $sql = stridebr_api_training_exercise_query_base() . ' WHERE ' . $condition . ' ORDER BY CASE WHEN e.idusuario=:order_user THEN 0 ELSE 1 END,e.nome LIMIT :limit OFFSET :offset';
    $stmt = $pdo->prepare($sql);
    foreach ($params as $name => $value) $stmt->bindValue($name, $value, PDO::PARAM_STR);
    $stmt->bindValue(':order_user', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', ($page - 1) * $limit, PDO::PARAM_INT);
    $stmt->execute();
    return [
        'data' => array_map('stridebr_api_training_exercise_payload', $stmt->fetchAll()),
        'meta' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => max(1, (int) ceil($total / $limit))],
    ];
}

function stridebr_api_training_exercise(PDO $pdo, string $userId, string $exerciseId): array
{
    $sql = stridebr_api_training_exercise_query_base() . ' WHERE e.idexercicio=:id AND e.ativo=TRUE AND (e.idusuario IS NULL OR e.idusuario=:user) LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $exerciseId, ':user' => $userId]);
    $row = $stmt->fetch();
    return $row ? stridebr_api_training_exercise_payload($row) : [];
}

function stridebr_api_training_string_ids(mixed $value, string $field): array
{
    if ($value === null) return [];
    if (!is_array($value)) throw new InvalidArgumentException($field . ' precisa ser uma lista.');
    $result = [];
    foreach ($value as $item) {
        $id = trim((string) $item);
        if ($id !== '') $result[] = $id;
    }
    return array_values(array_unique($result));
}

function stridebr_api_training_exercise_create(PDO $pdo, string $userId, array $payload): array
{
    $name = (string) stridebr_api_workout_text($payload['name'] ?? null, 120, 'name', true);
    $description = stridebr_api_workout_text($payload['description'] ?? null, 5000, 'description');
    $categories = stridebr_api_training_string_ids($payload['category_ids'] ?? [], 'category_ids');
    $sports = stridebr_api_training_string_ids($payload['sport_ids'] ?? [], 'sport_ids');
    $image = stridebr_api_workout_text($payload['image_url'] ?? null, 2000, 'image_url');
    $video = stridebr_api_workout_text($payload['video_url'] ?? null, 2000, 'video_url');
    $id = cronogramaCriarExercicioCompleto($pdo, $userId, $name, $description, $categories, $sports, $image, $video);
    return stridebr_api_training_exercise($pdo, $userId, $id);
}

function stridebr_api_training_exercise_update(PDO $pdo, string $userId, string $exerciseId, array $payload): array
{
    $current = stridebr_api_training_exercise($pdo, $userId, $exerciseId);
    if ($current === []) throw new InvalidArgumentException('Exercício não encontrado.');
    if (empty($current['custom']) || (string) ($current['owner_id'] ?? '') !== $userId) throw new RuntimeException('Exercícios do catálogo do sistema não podem ser editados.');
    $row = $pdo->prepare('SELECT data_atualizacao FROM exercicios WHERE idexercicio=:id AND idusuario=:user LIMIT 1');
    $row->execute([':id' => $exerciseId, ':user' => $userId]);
    $updatedAt = $row->fetchColumn();
    stridebr_api_training_assert_version($exerciseId, $updatedAt, $payload);
    $categories = array_key_exists('category_ids', $payload) ? stridebr_api_training_string_ids($payload['category_ids'], 'category_ids') : array_column($current['categories'], 'id');
    $sports = array_key_exists('sport_ids', $payload) ? stridebr_api_training_string_ids($payload['sport_ids'], 'sport_ids') : array_column($current['sports'], 'id');
    $name = array_key_exists('name', $payload) ? (string) stridebr_api_workout_text($payload['name'], 120, 'name', true) : (string) $current['name'];
    $description = array_key_exists('description', $payload) ? stridebr_api_workout_text($payload['description'], 5000, 'description') : $current['description'];
    $image = array_key_exists('image_url', $payload) ? stridebr_api_workout_text($payload['image_url'], 2000, 'image_url') : ($current['media']['image_url'] ?? null);
    $video = array_key_exists('video_url', $payload) ? stridebr_api_workout_text($payload['video_url'], 2000, 'video_url') : ($current['media']['video_url'] ?? null);
    if (!cronogramaAtualizarExercicioPessoal($pdo, $userId, $exerciseId, $name, $description, $categories, $sports, $image, $video)) throw new RuntimeException('Exercício não encontrado.');
    return stridebr_api_training_exercise($pdo, $userId, $exerciseId);
}

function stridebr_api_training_exercise_archive(PDO $pdo, string $userId, string $exerciseId): void
{
    if (!cronogramaDesativarExercicioPessoal($pdo, $userId, $exerciseId)) throw new RuntimeException('Exercício pessoal não encontrado ou já arquivado.');
}

function stridebr_api_training_exercise_history(PDO $pdo, string $userId, string $exerciseId, array $filters): array
{
    if (stridebr_api_training_exercise($pdo, $userId, $exerciseId) === []) throw new InvalidArgumentException('Exercício não encontrado.');
    $limit = max(1, min(20, (int) ($filters['limit'] ?? 5)));
    $sql = "WITH latest AS (
        SELECT ra.idregistro,ra.data_inicio,ra.titulo
          FROM series_exercicio_atividade sea
          JOIN registros_atividade ra ON ra.idregistro=sea.idregistro
         WHERE ra.idusuario=:user AND ra.excluido_em IS NULL AND ra.status='concluido' AND sea.idexercicio=:exercise
         GROUP BY ra.idregistro,ra.data_inicio,ra.titulo
         ORDER BY ra.data_inicio DESC
         LIMIT :limit
    )
    SELECT l.idregistro,l.data_inicio,l.titulo,sea.ordem_serie,sea.carga_kg,sea.repeticoes,sea.rir,sea.rpe,sea.concluida
      FROM latest l
      JOIN series_exercicio_atividade sea ON sea.idregistro=l.idregistro AND sea.idexercicio=:exercise_join
     ORDER BY l.data_inicio DESC,sea.ordem_serie";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':exercise', $exerciseId, PDO::PARAM_STR);
    $stmt->bindValue(':exercise_join', $exerciseId, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $executions = [];
    foreach ($stmt->fetchAll() as $row) {
        $id = (string) $row['idregistro'];
        if (!isset($executions[$id])) {
            $executions[$id] = [
                'activity_id' => $id,
                'date' => (new DateTimeImmutable((string) $row['data_inicio']))->setTimezone(new DateTimeZone(stridebr_api_workout_timezone()))->format('Y-m-d'),
                'title' => $row['titulo'] !== null ? (string) $row['titulo'] : null,
                'sets' => [],
            ];
        }
        $executions[$id]['sets'][] = [
            'number' => (int) $row['ordem_serie'],
            'repetitions' => $row['repeticoes'] !== null ? (int) $row['repeticoes'] : null,
            'load_kg' => $row['carga_kg'] !== null ? (float) $row['carga_kg'] : null,
            'rir' => $row['rir'] !== null ? (float) $row['rir'] : null,
            'rpe' => $row['rpe'] !== null ? (float) $row['rpe'] : null,
            'completed' => stridebr_api_bool($row['concluida'] ?? false),
        ];
    }
    $bestLoad = treinoExercicioMelhorCargaKg($pdo, $userId, $exerciseId);
    return ['data' => array_values($executions), 'meta' => ['limit' => $limit, 'best_load_kg' => $bestLoad]];
}

function stridebr_api_training_schedules(PDO $pdo, string $userId): array
{
    $data = [];
    foreach (cronogramaListar($pdo, $userId) as $row) {
        $data[] = [
            'id' => (string) $row['idcronograma'],
            'name' => (string) $row['nome'],
            'description' => $row['descricao'] !== null ? (string) $row['descricao'] : null,
            'active' => stridebr_api_bool($row['ativo'] ?? true),
            'updated_at' => stridebr_api_iso(isset($row['data_atualizacao']) ? (string) $row['data_atualizacao'] : null),
            'version' => stridebr_api_training_version((string) $row['idcronograma'], $row['data_atualizacao'] ?? null),
        ];
    }
    return ['data' => $data];
}

function stridebr_api_training_schedule_create(PDO $pdo, string $userId, array $payload): array
{
    $name = (string) stridebr_api_workout_text($payload['name'] ?? null, 120, 'name', true);
    $description = stridebr_api_workout_text($payload['description'] ?? null, 5000, 'description');
    $id = cronogramaCriar($pdo, $userId, $name, $description);
    foreach (stridebr_api_training_schedules($pdo, $userId)['data'] as $item) if ($item['id'] === $id) return $item;
    throw new RuntimeException('Não foi possível carregar o cronograma criado.');
}

function stridebr_api_training_template_save(PDO $pdo, string $userId, array $payload, ?string $templateId = null): array
{
    $current = $templateId !== null ? cronogramaBuscarTreinoModelo($pdo, $userId, $templateId) : [];
    if ($templateId !== null && $current === []) throw new InvalidArgumentException('Template não encontrado.');
    if ($templateId !== null) stridebr_api_training_assert_version($templateId, $current['data_atualizacao'] ?? null, $payload);
    $title = array_key_exists('title', $payload) ? $payload['title'] : ($current['titulo'] ?? null);
    $sport = array_key_exists('sport', $payload) ? stridebr_api_workout_modality($pdo, $userId, (string) $payload['sport'])['idmodalidade'] : ($current['idmodalidade'] ?? null);
    $domainPayload = [
        'titulo' => $title,
        'codigo' => array_key_exists('code', $payload) ? (string) ($payload['code'] ?? '') : (string) ($current['codigo'] ?? ''),
        'foco' => array_key_exists('objective', $payload) ? (string) ($payload['objective'] ?? '') : (string) ($current['foco'] ?? ''),
        'descricao' => array_key_exists('notes', $payload) ? (string) ($payload['notes'] ?? '') : (string) ($current['descricao'] ?? ''),
        'idmodalidade' => $sport,
    ];
    $owns = !$pdo->inTransaction();
    if ($owns) $pdo->beginTransaction();
    try {
        $id = cronogramaSalvarTreinoModelo($pdo, $userId, $domainPayload, $templateId, false);
        if (array_key_exists('structure', $payload)) {
            if (!is_array($payload['structure'])) throw new InvalidArgumentException('structure precisa ser um objeto.');
            treinoTemplateSalvarEstrutura($pdo, $userId, $id, (array) ($payload['structure']['exercises'] ?? []));
        }
        if ($owns) $pdo->commit();
    } catch (Throwable $e) {
        if ($owns && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return stridebr_api_workout_template($pdo, $userId, $id);
}

function stridebr_api_training_template_archive(PDO $pdo, string $userId, string $templateId): void
{
    if (!cronogramaArquivarTreinoModelo($pdo, $userId, $templateId)) throw new RuntimeException('Template não encontrado ou já arquivado.');
}

function stridebr_api_training_recurring_create(PDO $pdo, string $userId, array $payload): array
{
    $recurrence = $payload['recurrence'] ?? null;
    if (!is_array($recurrence)) throw new InvalidArgumentException('recurrence precisa ser um objeto.');
    $frequency = strtolower(trim((string) ($recurrence['frequency'] ?? 'weekly')));
    $interval = (int) ($recurrence['interval'] ?? 1);
    if ($frequency !== 'weekly' || $interval !== 1) throw new InvalidArgumentException('A recorrência atual do Core suporta frequency=weekly e interval=1.');
    $scheduleId = trim((string) ($recurrence['schedule_id'] ?? ''));
    if ($scheduleId === '' || cronogramaBuscar($pdo, $scheduleId, $userId) === []) throw new InvalidArgumentException('schedule_id inválido.');
    $templateId = trim((string) ($payload['template_id'] ?? ''));
    $template = $templateId !== '' ? stridebr_api_workout_template($pdo, $userId, $templateId) : [];
    if ($templateId !== '' && $template === []) throw new InvalidArgumentException('Template não encontrado.');
    $title = array_key_exists('title', $payload) ? stridebr_api_workout_text($payload['title'], 120, 'title', true) : ($template['title'] ?? null);
    if ($title === null || $title === '') throw new InvalidArgumentException('title é obrigatório.');
    $date = stridebr_api_workout_date($payload['date'] ?? $recurrence['start_date'] ?? '', 'date');
    $time = stridebr_api_workout_time($payload['time'] ?? null);
    if ($time === null) throw new InvalidArgumentException('time é obrigatório para recorrência semanal no domínio atual.');
    $durationSeconds = $payload['planned_duration_s'] ?? null;
    if ($durationSeconds === null) throw new InvalidArgumentException('planned_duration_s é obrigatório para recorrência semanal.');
    $durationMinutes = stridebr_api_workout_duration_minutes($durationSeconds);
    if ($durationMinutes === null) throw new InvalidArgumentException('planned_duration_s é obrigatório.');
    $startMinutes = ((int) substr($time, 0, 2)) * 60 + (int) substr($time, 3, 2);
    $endMinutes = $startMinutes + $durationMinutes;
    $nextDay = $endMinutes >= 1440;
    $endMinutes %= 1440;
    $endTime = sprintf('%02d:%02d', intdiv($endMinutes, 60), $endMinutes % 60);
    $endDate = isset($recurrence['end_date']) && trim((string) $recurrence['end_date']) !== '' ? stridebr_api_workout_date($recurrence['end_date'], 'recurrence.end_date') : null;
    if ($endDate !== null && $endDate < $date) throw new InvalidArgumentException('recurrence.end_date não pode ser anterior à data inicial.');
    $sport = array_key_exists('sport', $payload)
        ? stridebr_api_workout_modality($pdo, $userId, (string) $payload['sport'])
        : (!empty($template['sport']['id']) ? stridebr_api_workout_modality($pdo, $userId, (string) $template['sport']['id']) : null);
    if ($sport === null) throw new InvalidArgumentException('sport é obrigatório.');
    $pacerPlanId = function_exists('pacerPlanValidateForWorkout') ? pacerPlanValidateForWorkout($pdo, $userId, $payload['pacer_plan_id'] ?? null, (string) $sport['idmodalidade']) : null;
    $weekday = (int) (new DateTimeImmutable($date, new DateTimeZone(stridebr_api_workout_timezone())))->format('w');
    $rows = [];
    if (array_key_exists('structure', $payload)) {
        if (!is_array($payload['structure'])) throw new InvalidArgumentException('structure precisa ser um objeto.');
        $rows = treinoEstruturaNormalizar($pdo, $userId, $payload['structure']);
    } elseif ($template !== []) {
        $rows = treinoEstruturaNormalizar($pdo, $userId, ['exercises' => $template['structure']['exercises'] ?? []]);
    }
    $owns = !$pdo->inTransaction();
    if ($owns) $pdo->beginTransaction();
    try {
        $id = cronogramaSalvarTreino($pdo, $userId, [
            'idcronograma' => $scheduleId,
            'titulo' => $title,
            'codigo' => '',
            'foco' => (string) ($payload['objective'] ?? $template['objective'] ?? ''),
            'descricao' => (string) ($payload['notes'] ?? $template['notes'] ?? ''),
            'idmodalidade' => (string) $sport['idmodalidade'],
            'dia_semana' => $weekday,
            'hora_inicio' => $time,
            'hora_fim' => $endTime,
            'termina_dia_seguinte' => $nextDay ? '1' : '',
            'vigencia_inicio' => $date,
            'vigencia_fim' => $endDate ?? '',
        ]);
        if ($templateId !== '') $pdo->prepare('UPDATE treinos_cronograma SET idtreino_modelo=:template WHERE idtreino=:id')->execute([':template' => $templateId, ':id' => $id]);
        if ($pacerPlanId !== null) $pdo->prepare('UPDATE treinos_cronograma SET idpacerplan=:pacer WHERE idtreino=:id')->execute([':pacer' => $pacerPlanId, ':id' => $id]);
        if ($rows !== []) cronogramaSalvarExercicios($pdo, $id, $userId, $rows, []);
        if ($owns) $pdo->commit();
    } catch (Throwable $e) {
        if ($owns && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return stridebr_api_workout_detail($pdo, $userId, stridebr_api_workout_id_recurring($id, $date));
}

function stridebr_api_training_recurring_update(PDO $pdo, string $userId, string $apiId, array $payload): array
{
    $parsed = stridebr_api_workout_parse_id($apiId);
    if ($parsed['kind'] !== 'recurring') throw new InvalidArgumentException('O treino informado não é recorrente.');
    $base = cronogramaBuscarTreino($pdo, (string) $parsed['id'], $userId);
    if ($base === []) throw new InvalidArgumentException('Treino recorrente não encontrado.');
    stridebr_api_training_assert_version((string) $parsed['id'], $base['data_atualizacao'] ?? null, $payload);
    $scope = strtolower(trim((string) ($payload['scope'] ?? 'this')));
    if (!in_array($scope, ['this', 'future', 'all'], true)) throw new InvalidArgumentException('scope precisa ser this, future ou all.');
    if ($scope === 'this' && array_key_exists('structure', $payload)) throw new InvalidArgumentException('A estrutura de uma única ocorrência recorrente não pode ser alterada isoladamente no domínio atual. Use scope=future ou scope=all.');
    if ($scope === 'this' && array_key_exists('pacer_plan_id', $payload)) throw new InvalidArgumentException('O Pacer Plan de uma única ocorrência recorrente não pode ser alterado isoladamente no domínio atual. Use scope=future ou scope=all.');
    if (array_key_exists('intensity', $payload)) throw new InvalidArgumentException('Treinos recorrentes do domínio atual não persistem intensidade por ocorrência.');
    $current = stridebr_api_workout_recurring_occurrence($pdo, $userId, (string) $parsed['id'], (string) $parsed['date']);
    if ($current === []) throw new InvalidArgumentException('Ocorrência recorrente não encontrada.');
    if (!empty($current['idregistro'])) throw new RuntimeException('Uma ocorrência já realizada não pode ser editada.');
    $date = array_key_exists('date', $payload) ? stridebr_api_workout_date($payload['date'], 'date') : (string) $current['data_treino'];
    $startTime = array_key_exists('time', $payload) ? stridebr_api_workout_time($payload['time']) : substr((string) $current['hora_inicio'], 0, 5);
    if ($startTime === null) throw new InvalidArgumentException('time não pode ser null para recorrência semanal.');
    $duration = array_key_exists('planned_duration_s', $payload) ? stridebr_api_workout_duration_minutes($payload['planned_duration_s']) : (int) ((stridebr_api_workout_recurring_duration_s($current) ?? 3600) / 60);
    $startMinutes = ((int) substr($startTime, 0, 2)) * 60 + (int) substr($startTime, 3, 2);
    $endMinutes = $startMinutes + $duration;
    $nextDay = $endMinutes >= 1440;
    $endMinutes %= 1440;
    $endTime = sprintf('%02d:%02d', intdiv($endMinutes, 60), $endMinutes % 60);
    $sportId = (string) ($current['idmodalidade'] ?? '');
    if (array_key_exists('sport', $payload)) $sportId = (string) stridebr_api_workout_modality($pdo, $userId, (string) $payload['sport'])['idmodalidade'];
    $basePacerPlanId = !empty($base['idpacerplan']) ? (string) $base['idpacerplan'] : null;
    $pacerPlanId = array_key_exists('pacer_plan_id', $payload)
        ? (function_exists('pacerPlanValidateForWorkout') ? pacerPlanValidateForWorkout($pdo, $userId, $payload['pacer_plan_id'], $sportId) : null)
        : ($basePacerPlanId !== null && function_exists('pacerPlanValidateForWorkout') ? pacerPlanValidateForWorkout($pdo, $userId, $basePacerPlanId, $sportId) : $basePacerPlanId);
    $domainPayload = [
        'titulo' => array_key_exists('title', $payload) ? (string) stridebr_api_workout_text($payload['title'], 120, 'title', true) : (string) $current['titulo'],
        'codigo' => (string) ($current['codigo'] ?? ''),
        'foco' => array_key_exists('objective', $payload) ? (string) ($payload['objective'] ?? '') : (string) ($current['foco'] ?? ''),
        'descricao' => array_key_exists('notes', $payload) ? (string) ($payload['notes'] ?? '') : (string) ($current['descricao'] ?? ''),
        'idmodalidade' => $sportId,
        'dia_semana' => (int) (new DateTimeImmutable($date, new DateTimeZone(stridebr_api_workout_timezone())))->format('w'),
        'hora_inicio' => $startTime,
        'hora_fim' => $endTime,
        'termina_dia_seguinte' => $nextDay ? '1' : '',
    ];
    $owns = !$pdo->inTransaction();
    if ($owns) $pdo->beginTransaction();
    try {
        $result = cronogramaEditarTreinoEscopo($pdo, $userId, (string) $parsed['id'], (string) $parsed['date'], $scope, $domainPayload);
        $targetId = (string) ($result['idtreino'] ?? $parsed['id']);
        if ($scope !== 'this') $pdo->prepare('UPDATE treinos_cronograma SET idpacerplan=:pacer,data_atualizacao=NOW() WHERE idtreino=:id')->execute([':pacer' => $pacerPlanId, ':id' => $targetId]);
        if (array_key_exists('structure', $payload)) {
            if (!is_array($payload['structure'])) throw new InvalidArgumentException('structure precisa ser um objeto.');
            $rows = treinoEstruturaNormalizar($pdo, $userId, $payload['structure']);
            cronogramaSalvarExercicios($pdo, $targetId, $userId, $rows, []);
        }
        if ($owns) $pdo->commit();
    } catch (Throwable $e) {
        if ($owns && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    $targetDate = (string) ($result['data_treino'] ?? $date);
    return stridebr_api_workout_detail($pdo, $userId, stridebr_api_workout_id_recurring($targetId, $scope === 'this' ? (string) $parsed['date'] : $targetDate));
}
