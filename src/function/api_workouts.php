<?php

declare(strict_types=1);

if (!function_exists('stridebr_db_bool')) {
    function stridebr_db_bool(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
    }
}
if (!function_exists('stridebr_generate_id')) {
    function stridebr_generate_id(int $length = 21): string
    {
        return stridebr_api_id($length);
    }
}
if (!function_exists('stridebr_length')) {
    function stridebr_length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
if (!function_exists('stridebr_lower')) {
    function stridebr_lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}
if (!function_exists('stridebr_t')) {
    function stridebr_t(string $key, array $replace = [], ?string $fallback = null): string
    {
        $message = $fallback ?? $key;
        foreach ($replace as $name => $value) $message = str_replace('{' . $name . '}', (string) $value, $message);
        return $message;
    }
}

require_once __DIR__ . '/cronograma.php';

function stridebr_api_workout_timezone(): string
{
    return 'America/Sao_Paulo';
}

function stridebr_api_workout_date(mixed $value, string $field = 'date'): string
{
    $raw = trim((string) $value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, new DateTimeZone(stridebr_api_workout_timezone()));
    if (!$date || $date->format('Y-m-d') !== $raw) throw new InvalidArgumentException($field . ' deve usar YYYY-MM-DD.');
    return $raw;
}

function stridebr_api_workout_time(mixed $value, string $field = 'time'): ?string
{
    if ($value === null || trim((string) $value) === '') return null;
    $raw = trim((string) $value);
    if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $raw) !== 1) throw new InvalidArgumentException($field . ' deve usar HH:MM ou null.');
    return $raw;
}

function stridebr_api_workout_text(mixed $value, int $max, string $field, bool $required = false): ?string
{
    if ($value === null) {
        if ($required) throw new InvalidArgumentException($field . ' é obrigatório.');
        return null;
    }
    $raw = trim((string) $value);
    if ($required && $raw === '') throw new InvalidArgumentException($field . ' é obrigatório.');
    if (stridebr_length($raw) > $max) throw new InvalidArgumentException($field . ' excede o tamanho permitido.');
    return $raw !== '' ? $raw : null;
}

function stridebr_api_workout_duration_minutes(mixed $value): ?int
{
    if ($value === null || $value === '') return null;
    if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new InvalidArgumentException('planned_duration_s precisa ser inteiro.');
    $seconds = (int) $value;
    if ($seconds < 60 || $seconds > 86400 || $seconds % 60 !== 0) throw new InvalidArgumentException('planned_duration_s precisa ficar entre 60 e 86400 e usar precisão de minutos.');
    return intdiv($seconds, 60);
}

function stridebr_api_workout_distance(mixed $value): ?float
{
    if ($value === null || $value === '') return null;
    if (!is_numeric($value)) throw new InvalidArgumentException('planned_distance_m precisa ser numérico.');
    $distance = (float) $value;
    if (!is_finite($distance) || $distance < 0 || $distance > 10000000) throw new InvalidArgumentException('planned_distance_m está fora do intervalo permitido.');
    return $distance;
}

function stridebr_api_workout_id_scheduled(string $id): string
{
    return 'scheduled:' . $id;
}

function stridebr_api_workout_id_recurring(string $id, string $originalDate): string
{
    return 'recurring:' . $id . ':' . $originalDate;
}

function stridebr_api_workout_parse_id(string $apiId): array
{
    $apiId = trim($apiId);
    if (preg_match('/^scheduled:([A-Za-z0-9_-]{8,40})$/D', $apiId, $match) === 1) return ['kind' => 'scheduled', 'id' => $match[1]];
    if (preg_match('/^recurring:([A-Za-z0-9_-]{8,40}):(\d{4}-\d{2}-\d{2})$/D', $apiId, $match) === 1) {
        stridebr_api_workout_date($match[2], 'workout occurrence date');
        return ['kind' => 'recurring', 'id' => $match[1], 'date' => $match[2]];
    }
    throw new InvalidArgumentException('workout id inválido.');
}

function stridebr_api_workout_modality(PDO $pdo, string $userId, string $requested): array
{
    $requested = trim($requested);
    if ($requested === '') throw new InvalidArgumentException('sport é obrigatório.');
    $stmt = $pdo->prepare(
        "SELECT idmodalidade, nome, slug, familia_hub, permite_rota
           FROM modalidades
          WHERE ativo = TRUE
            AND (idusuario IS NULL OR idusuario = :user)
            AND (idmodalidade = :requested OR lower(slug) = lower(:requested_slug))
          ORDER BY CASE WHEN idusuario = :user_order THEN 0 ELSE 1 END, ordem_catalogo, nome
          LIMIT 1"
    );
    $stmt->execute([':user' => $userId, ':requested' => $requested, ':requested_slug' => $requested, ':user_order' => $userId]);
    $row = $stmt->fetch();
    if (!$row) throw new InvalidArgumentException('A modalidade informada não está disponível.');
    return $row;
}

function stridebr_api_workout_modality_by_id(PDO $pdo, string $userId, ?string $id): ?array
{
    $id = trim((string) $id);
    if ($id === '') return null;
    $stmt = $pdo->prepare('SELECT idmodalidade, nome, slug, familia_hub, permite_rota FROM modalidades WHERE idmodalidade = :id AND ativo = TRUE AND (idusuario IS NULL OR idusuario = :user) LIMIT 1');
    $stmt->execute([':id' => $id, ':user' => $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function stridebr_api_workout_sport_payload(?array $row): ?array
{
    if (!$row) return null;
    return [
        'id' => (string) $row['idmodalidade'],
        'slug' => (string) $row['slug'],
        'name' => (string) $row['nome'],
        'family' => $row['familia_hub'] !== null ? (string) $row['familia_hub'] : null,
        'route_capable' => stridebr_api_bool($row['permite_rota'] ?? false),
    ];
}

function stridebr_api_workout_recurring_duration_s(array $item): ?int
{
    $start = substr((string) ($item['hora_inicio'] ?? ''), 0, 5);
    $end = substr((string) ($item['hora_fim'] ?? ''), 0, 5);
    if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) return null;
    $startMin = ((int) substr($start, 0, 2)) * 60 + (int) substr($start, 3, 2);
    $endMin = ((int) substr($end, 0, 2)) * 60 + (int) substr($end, 3, 2);
    if (stridebr_db_bool($item['termina_dia_seguinte'] ?? false)) $endMin += 1440;
    $duration = $endMin - $startMin;
    return $duration > 0 ? $duration * 60 : null;
}

function stridebr_api_workout_recurring_occurrence(PDO $pdo, string $userId, string $workoutId, string $originalDate): array
{
    $workout = cronogramaBuscarTreino($pdo, $workoutId, $userId);
    if ($workout === []) return [];
    $original = stridebr_api_workout_date($originalDate, 'workout occurrence date');
    $stmt = $pdo->prepare('SELECT * FROM treinos_cronograma_excecoes WHERE idtreino = :workout AND data_original = :original LIMIT 1');
    $stmt->execute([':workout' => $workoutId, ':original' => $original]);
    $exception = $stmt->fetch() ?: null;
    if (!$exception) {
        $date = new DateTimeImmutable($original, new DateTimeZone(stridebr_api_workout_timezone()));
        $start = new DateTimeImmutable((string) $workout['vigencia_inicio'], new DateTimeZone(stridebr_api_workout_timezone()));
        $end = !empty($workout['vigencia_fim']) ? new DateTimeImmutable((string) $workout['vigencia_fim'], new DateTimeZone(stridebr_api_workout_timezone())) : null;
        if ($date < $start || ($end !== null && $date > $end) || (int) $date->format('w') !== (int) $workout['dia_semana']) return [];
    }
    $item = $workout;
    $item['data_original'] = $original;
    $item['status'] = ($exception['tipo'] ?? '') === 'cancelar' ? 'cancelado' : 'publicado';
    $item['data_treino'] = $exception && ($exception['tipo'] ?? '') === 'alterar' ? (string) $exception['data_treino'] : $original;
    if ($exception && ($exception['tipo'] ?? '') === 'alterar') {
        foreach (['titulo', 'codigo', 'foco', 'idmodalidade', 'descricao', 'hora_inicio', 'hora_fim', 'termina_dia_seguinte'] as $field) {
            if ($exception[$field] !== null) $item[$field] = $exception[$field];
        }
    }
    $activityStmt = $pdo->prepare(
        "SELECT idregistro, data_inicio
           FROM registros_atividade
          WHERE idusuario = :user
            AND idtreino_cronograma = :workout
            AND excluido_em IS NULL
            AND status = 'concluido'
            AND (
                data_ocorrencia_origem = :original
                OR data_ocorrencia_planejada = :planned
                OR (data_ocorrencia_origem IS NULL AND data_ocorrencia_planejada IS NULL AND (data_inicio AT TIME ZONE 'America/Sao_Paulo')::date = CAST(:performed AS date))
            )
          ORDER BY CASE WHEN data_ocorrencia_origem = :original_order THEN 0 WHEN data_ocorrencia_planejada = :planned_order THEN 1 ELSE 2 END, data_inicio
          LIMIT 1"
    );
    $activityStmt->execute([
        ':user' => $userId,
        ':workout' => $workoutId,
        ':original' => $original,
        ':planned' => (string) $item['data_treino'],
        ':performed' => (string) $item['data_treino'],
        ':original_order' => $original,
        ':planned_order' => (string) $item['data_treino'],
    ]);
    $activity = $activityStmt->fetch() ?: null;
    if ($activity) {
        $item['status'] = 'concluido';
        $item['idregistro'] = (string) $activity['idregistro'];
    }
    return $item;
}

function stridebr_api_workout_schedule(PDO $pdo, string $userId, array $filters): array
{
    $from = stridebr_api_workout_date($filters['from'] ?? '', 'from');
    $to = stridebr_api_workout_date($filters['to'] ?? '', 'to');
    $start = new DateTimeImmutable($from, new DateTimeZone(stridebr_api_workout_timezone()));
    $end = new DateTimeImmutable($to, new DateTimeZone(stridebr_api_workout_timezone()));
    if ($end < $start || ($start->diff($end)->days ?? 0) > 93) throw new InvalidArgumentException('O intervalo do calendário deve ter no máximo 94 dias.');

    $recurring = cronogramaListarOcorrencias($pdo, $userId, $from, $to, null, true);
    $recurring = cronogramaConciliarOcorrenciasComRegistros($pdo, $userId, $recurring, $from, $to, null);
    $recurring = array_values(array_filter($recurring, static fn(array $item): bool => (string) ($item['data_treino'] ?? '') >= $from && (string) ($item['data_treino'] ?? '') <= $to));

    $workoutIds = array_values(array_unique(array_filter(array_map(static fn(array $item): string => (string) ($item['idtreino'] ?? ''), $recurring))));
    $exerciseCounts = [];
    if ($workoutIds !== []) {
        $placeholders = implode(',', array_fill(0, count($workoutIds), '?'));
        $stmt = $pdo->prepare("SELECT idtreino, COUNT(*) AS total FROM treinos_exercicios WHERE idtreino IN ({$placeholders}) GROUP BY idtreino");
        $stmt->execute($workoutIds);
        foreach ($stmt->fetchAll() as $row) $exerciseCounts[(string) $row['idtreino']] = (int) $row['total'];
    }

    $sportIds = array_values(array_unique(array_filter(array_map(static fn(array $item): string => (string) ($item['idmodalidade'] ?? ''), $recurring))));
    $sports = [];
    if ($sportIds !== []) {
        $placeholders = implode(',', array_fill(0, count($sportIds), '?'));
        $stmt = $pdo->prepare("SELECT idmodalidade,nome,slug,familia_hub,permite_rota FROM modalidades WHERE idmodalidade IN ({$placeholders})");
        $stmt->execute($sportIds);
        foreach ($stmt->fetchAll() as $row) $sports[(string) $row['idmodalidade']] = $row;
    }

    $items = [];
    foreach ($recurring as $row) {
        $status = !empty($row['concluido']) || !empty($row['idregistro']) ? 'concluido' : ((string) ($row['status'] ?? '') === 'cancelado' ? 'cancelado' : 'publicado');
        $items[] = [
            'id' => stridebr_api_workout_id_recurring((string) $row['idtreino'], (string) $row['data_original']),
            'kind' => 'recurring',
            'title' => (string) $row['titulo'],
            'sport' => stridebr_api_workout_sport_payload($sports[(string) ($row['idmodalidade'] ?? '')] ?? null),
            'date' => (string) $row['data_treino'],
            'time' => substr((string) $row['hora_inicio'], 0, 5),
            'planned_duration_s' => stridebr_api_workout_recurring_duration_s($row),
            'planned_distance_m' => null,
            'status' => $status,
            'source' => 'cronograma',
            'template_id' => !empty($row['idtreino_modelo']) ? (string) $row['idtreino_modelo'] : null,
            'updated_at' => stridebr_api_iso(isset($row['data_atualizacao']) ? (string) $row['data_atualizacao'] : null),
            'has_structure' => ($exerciseCounts[(string) $row['idtreino']] ?? 0) > 0,
            'exercise_count' => $exerciseCounts[(string) $row['idtreino']] ?? 0,
            'activity' => !empty($row['idregistro']) ? ['id' => (string) $row['idregistro']] : null,
        ];
    }

    $stmt = $pdo->prepare(
        "SELECT ta.*,
                COALESCE(ta.idmodalidade, tc.idmodalidade, tm.idmodalidade) AS resolved_idmodalidade,
                COALESCE(ta.idtreino_modelo_origem, tc.idtreino_modelo) AS resolved_template_id,
                m.nome AS modalidade_nome, m.slug AS modalidade_slug, m.familia_hub AS modalidade_family, m.permite_rota AS modalidade_route,
                COALESCE(NULLIF(u.nome_exibicao,''),u.nomeusuario) AS creator_name,
                (SELECT COUNT(*) FROM treinos_agendados_exercicios tae WHERE tae.idagendamento = ta.idagendamento) AS exercise_count,
                completed.idregistro AS activity_id
           FROM treinos_agendados ta
           LEFT JOIN treinos_cronograma tc ON tc.idtreino = ta.idtreino_origem
           LEFT JOIN treinos_modelo tm ON tm.idtreino_modelo = COALESCE(ta.idtreino_modelo_origem, tc.idtreino_modelo)
           LEFT JOIN modalidades m ON m.idmodalidade = COALESCE(ta.idmodalidade, tc.idmodalidade, tm.idmodalidade)
           LEFT JOIN usuarios u ON u.idusuario = ta.idcriador
           LEFT JOIN LATERAL (
                SELECT st.idregistro_atividade AS idregistro
                  FROM sessoes_treino st
                  JOIN registros_atividade ra ON ra.idregistro = st.idregistro_atividade AND ra.idusuario = ta.idatleta AND ra.excluido_em IS NULL
                 WHERE st.idagendamento_origem = ta.idagendamento
                   AND st.idusuario = ta.idatleta
                   AND st.status = 'concluido'
                   AND st.idregistro_atividade IS NOT NULL
                 ORDER BY st.data_fim DESC NULLS LAST, st.data_criacao DESC
                 LIMIT 1
           ) completed ON TRUE
          WHERE ta.idatleta = :user
            AND ta.data_treino BETWEEN :from AND :to
            AND (ta.status IN ('publicado','concluido','cancelado') OR (ta.status = 'rascunho' AND ta.idcriador = :viewer))
          ORDER BY ta.data_treino, ta.hora_inicio NULLS LAST, ta.data_criacao"
    );
    $stmt->execute([':user' => $userId, ':from' => $from, ':to' => $to, ':viewer' => $userId]);
    foreach ($stmt->fetchAll() as $row) {
        $sport = !empty($row['resolved_idmodalidade']) ? [
            'idmodalidade' => $row['resolved_idmodalidade'],
            'nome' => $row['modalidade_nome'],
            'slug' => $row['modalidade_slug'],
            'familia_hub' => $row['modalidade_family'],
            'permite_rota' => $row['modalidade_route'],
        ] : null;
        $items[] = [
            'id' => stridebr_api_workout_id_scheduled((string) $row['idagendamento']),
            'kind' => 'scheduled',
            'title' => (string) $row['titulo'],
            'sport' => stridebr_api_workout_sport_payload($sport),
            'date' => (string) $row['data_treino'],
            'time' => $row['hora_inicio'] !== null ? substr((string) $row['hora_inicio'], 0, 5) : null,
            'planned_duration_s' => $row['duracao_prevista_min'] !== null ? (int) $row['duracao_prevista_min'] * 60 : null,
            'planned_distance_m' => $row['distancia_prevista_m'] !== null ? (float) $row['distancia_prevista_m'] : null,
            'status' => (string) $row['status'],
            'source' => (string) $row['origem'],
            'template_id' => $row['resolved_template_id'] !== null ? (string) $row['resolved_template_id'] : null,
            'updated_at' => stridebr_api_iso((string) $row['data_atualizacao']),
            'has_structure' => (int) $row['exercise_count'] > 0,
            'exercise_count' => (int) $row['exercise_count'],
            'activity' => $row['activity_id'] !== null ? ['id' => (string) $row['activity_id']] : null,
        ];
    }

    usort($items, static function (array $a, array $b): int {
        $aTime = $a['time'] ?? '99:99';
        $bTime = $b['time'] ?? '99:99';
        return [$a['date'], $aTime, $a['title'], $a['id']] <=> [$b['date'], $bTime, $b['title'], $b['id']];
    });
    return ['data' => $items, 'meta' => ['from' => $from, 'to' => $to, 'timezone' => stridebr_api_workout_timezone(), 'count' => count($items)]];
}

function stridebr_api_workout_exercise_payload(array $row): array
{
    return [
        'id' => isset($row['idexercicio']) && $row['idexercicio'] !== null ? (string) $row['idexercicio'] : null,
        'name' => (string) ($row['nome_snapshot'] ?? ''),
        'sets' => isset($row['series']) && $row['series'] !== null ? (int) $row['series'] : (isset($row['series_planejadas']) && $row['series_planejadas'] !== null ? (int) $row['series_planejadas'] : null),
        'repetitions' => ($row['repeticoes'] ?? $row['repeticoes_snapshot'] ?? null) !== null ? (string) ($row['repeticoes'] ?? $row['repeticoes_snapshot']) : null,
        'load' => ($row['carga'] ?? $row['carga_snapshot'] ?? null) !== null ? (string) ($row['carga'] ?? $row['carga_snapshot']) : null,
        'rest' => ($row['descanso'] ?? $row['descanso_snapshot'] ?? null) !== null ? (string) ($row['descanso'] ?? $row['descanso_snapshot']) : null,
        'block' => isset($row['bloco']) && $row['bloco'] !== null ? (string) $row['bloco'] : null,
        'cluster' => isset($row['cluster']) && $row['cluster'] !== null ? (string) $row['cluster'] : null,
        'duration' => ($row['duracao'] ?? $row['duracao_snapshot'] ?? null) !== null ? (string) ($row['duracao'] ?? $row['duracao_snapshot']) : null,
        'distance' => ($row['distancia'] ?? $row['distancia_snapshot'] ?? null) !== null ? (string) ($row['distancia'] ?? $row['distancia_snapshot']) : null,
        'intensity' => ($row['intensidade'] ?? $row['intensidade_snapshot'] ?? null) !== null ? (string) ($row['intensidade'] ?? $row['intensidade_snapshot']) : null,
        'rpe' => ($row['rpe'] ?? $row['rpe_snapshot'] ?? null) !== null ? (float) ($row['rpe'] ?? $row['rpe_snapshot']) : null,
        'rir' => ($row['rir'] ?? $row['rir_snapshot'] ?? null) !== null ? (float) ($row['rir'] ?? $row['rir_snapshot']) : null,
        'tempo' => ($row['tempo_execucao'] ?? $row['tempo_execucao_snapshot'] ?? null) !== null ? (string) ($row['tempo_execucao'] ?? $row['tempo_execucao_snapshot']) : null,
        'cadence' => ($row['cadencia'] ?? $row['cadencia_snapshot'] ?? null) !== null ? (string) ($row['cadencia'] ?? $row['cadencia_snapshot']) : null,
        'notes' => ($row['observacoes'] ?? $row['observacoes_snapshot'] ?? null) !== null ? (string) ($row['observacoes'] ?? $row['observacoes_snapshot']) : null,
        'order' => isset($row['ordem']) ? (int) $row['ordem'] : null,
    ];
}

function stridebr_api_workout_detail(PDO $pdo, string $userId, string $apiId): array
{
    $parsed = stridebr_api_workout_parse_id($apiId);
    if ($parsed['kind'] === 'scheduled') {
        $stmt = $pdo->prepare(
            "SELECT ta.*,
                    COALESCE(ta.idmodalidade, tc.idmodalidade, tm.idmodalidade) AS resolved_idmodalidade,
                    COALESCE(ta.idtreino_modelo_origem, tc.idtreino_modelo) AS resolved_template_id,
                    m.nome AS modalidade_nome, m.slug AS modalidade_slug, m.familia_hub AS modalidade_family, m.permite_rota AS modalidade_route,
                    COALESCE(NULLIF(u.nome_exibicao,''),u.nomeusuario) AS creator_name, u.username AS creator_username,
                    completed.idsessao AS session_id,
                    completed.idregistro AS activity_id
               FROM treinos_agendados ta
               LEFT JOIN treinos_cronograma tc ON tc.idtreino = ta.idtreino_origem
               LEFT JOIN treinos_modelo tm ON tm.idtreino_modelo = COALESCE(ta.idtreino_modelo_origem, tc.idtreino_modelo)
               LEFT JOIN modalidades m ON m.idmodalidade = COALESCE(ta.idmodalidade, tc.idmodalidade, tm.idmodalidade)
               LEFT JOIN usuarios u ON u.idusuario = ta.idcriador
               LEFT JOIN LATERAL (
                    SELECT st.idsessao, st.idregistro_atividade AS idregistro
                      FROM sessoes_treino st
                      JOIN registros_atividade ra ON ra.idregistro = st.idregistro_atividade AND ra.idusuario = ta.idatleta AND ra.excluido_em IS NULL
                     WHERE st.idagendamento_origem = ta.idagendamento AND st.idusuario = ta.idatleta AND st.status = 'concluido' AND st.idregistro_atividade IS NOT NULL
                     ORDER BY st.data_fim DESC NULLS LAST, st.data_criacao DESC LIMIT 1
               ) completed ON TRUE
              WHERE ta.idagendamento = :id AND ta.idatleta = :user LIMIT 1"
        );
        $stmt->execute([':id' => $parsed['id'], ':user' => $userId]);
        $row = $stmt->fetch();
        if (!$row) return [];
        $exerciseStmt = $pdo->prepare('SELECT * FROM treinos_agendados_exercicios WHERE idagendamento = :id ORDER BY ordem');
        $exerciseStmt->execute([':id' => $parsed['id']]);
        $exercises = array_map('stridebr_api_workout_exercise_payload', $exerciseStmt->fetchAll());
        $sport = !empty($row['resolved_idmodalidade']) ? [
            'idmodalidade' => $row['resolved_idmodalidade'], 'nome' => $row['modalidade_nome'], 'slug' => $row['modalidade_slug'],
            'familia_hub' => $row['modalidade_family'], 'permite_rota' => $row['modalidade_route'],
        ] : null;
        $editable = (string) $row['origem'] === 'usuario' && (string) $row['idcriador'] === $userId && in_array((string) $row['status'], ['rascunho', 'publicado'], true);
        return [
            'id' => $apiId,
            'kind' => 'scheduled',
            'title' => (string) $row['titulo'],
            'sport' => stridebr_api_workout_sport_payload($sport),
            'date' => (string) $row['data_treino'],
            'time' => $row['hora_inicio'] !== null ? substr((string) $row['hora_inicio'], 0, 5) : null,
            'timezone' => stridebr_api_workout_timezone(),
            'notes' => $row['descricao'] !== null ? (string) $row['descricao'] : null,
            'objective' => $row['objetivo'] !== null ? (string) $row['objetivo'] : null,
            'planned_duration_s' => $row['duracao_prevista_min'] !== null ? (int) $row['duracao_prevista_min'] * 60 : null,
            'planned_distance_m' => $row['distancia_prevista_m'] !== null ? (float) $row['distancia_prevista_m'] : null,
            'intensity' => $row['intensidade'] !== null ? (string) $row['intensidade'] : null,
            'status' => (string) $row['status'],
            'source' => (string) $row['origem'],
            'author' => $row['idcriador'] !== null ? ['id' => (string) $row['idcriador'], 'name' => (string) ($row['creator_name'] ?? ''), 'username' => $row['creator_username'] !== null ? (string) $row['creator_username'] : null] : null,
            'template_id' => $row['resolved_template_id'] !== null ? (string) $row['resolved_template_id'] : null,
            'schedule_id' => $row['idcronograma_origem'] !== null ? (string) $row['idcronograma_origem'] : null,
            'schedule_workout_id' => $row['idtreino_origem'] !== null ? (string) $row['idtreino_origem'] : null,
            'structure' => ['exercise_count' => count($exercises), 'exercises' => $exercises],
            'session' => $row['session_id'] !== null ? ['id' => (string) $row['session_id']] : null,
            'activity' => $row['activity_id'] !== null ? ['id' => (string) $row['activity_id']] : null,
            'created_at' => stridebr_api_iso((string) $row['data_criacao']),
            'updated_at' => stridebr_api_iso((string) $row['data_atualizacao']),
            'permissions' => [
                'can_edit' => $editable,
                'can_reschedule' => $editable,
                'can_cancel' => in_array((string) $row['status'], ['rascunho', 'publicado'], true),
                'can_complete' => (string) $row['status'] === 'publicado',
            ],
        ];
    }

    $row = stridebr_api_workout_recurring_occurrence($pdo, $userId, (string) $parsed['id'], (string) $parsed['date']);
    if ($row === []) return [];
    $exerciseRows = cronogramaListarTreinoExercicios($pdo, (string) $parsed['id'], $userId);
    $exercises = array_map('stridebr_api_workout_exercise_payload', $exerciseRows);
    $sport = stridebr_api_workout_modality_by_id($pdo, $userId, $row['idmodalidade'] ?? null);
    return [
        'id' => $apiId,
        'kind' => 'recurring',
        'title' => (string) $row['titulo'],
        'sport' => stridebr_api_workout_sport_payload($sport),
        'date' => (string) $row['data_treino'],
        'original_date' => (string) $row['data_original'],
        'time' => substr((string) $row['hora_inicio'], 0, 5),
        'timezone' => stridebr_api_workout_timezone(),
        'notes' => !empty($row['descricao']) ? (string) $row['descricao'] : null,
        'objective' => !empty($row['foco']) ? (string) $row['foco'] : null,
        'planned_duration_s' => stridebr_api_workout_recurring_duration_s($row),
        'planned_distance_m' => null,
        'intensity' => null,
        'status' => (string) ($row['status'] ?? 'publicado'),
        'source' => 'cronograma',
        'author' => ['id' => $userId],
        'template_id' => !empty($row['idtreino_modelo']) ? (string) $row['idtreino_modelo'] : null,
        'schedule_id' => (string) $row['idcronograma'],
        'schedule_workout_id' => (string) $row['idtreino'],
        'structure' => ['exercise_count' => count($exercises), 'exercises' => $exercises],
        'session' => null,
        'activity' => !empty($row['idregistro']) ? ['id' => (string) $row['idregistro']] : null,
        'created_at' => stridebr_api_iso(isset($row['data_criacao']) ? (string) $row['data_criacao'] : null),
        'updated_at' => stridebr_api_iso(isset($row['data_atualizacao']) ? (string) $row['data_atualizacao'] : null),
        'permissions' => [
            'can_edit' => false,
            'can_reschedule' => false,
            'can_cancel' => (string) ($row['status'] ?? '') !== 'cancelado' && empty($row['idregistro']),
            'can_complete' => false,
        ],
    ];
}

function stridebr_api_workout_template(PDO $pdo, string $userId, string $templateId): array
{
    $stmt = $pdo->prepare(
        'SELECT tm.*, m.nome AS modalidade_nome, m.slug AS modalidade_slug, m.familia_hub AS modalidade_family, m.permite_rota AS modalidade_route
           FROM treinos_modelo tm
           LEFT JOIN modalidades m ON m.idmodalidade = tm.idmodalidade
          WHERE tm.idtreino_modelo = :id AND tm.idusuario = :user AND tm.ativo = TRUE LIMIT 1'
    );
    $stmt->execute([':id' => $templateId, ':user' => $userId]);
    $row = $stmt->fetch();
    if (!$row) return [];
    $exerciseStmt = $pdo->prepare('SELECT * FROM treinos_modelo_exercicios WHERE idtreino_modelo = :id ORDER BY ordem');
    $exerciseStmt->execute([':id' => $templateId]);
    $exercises = array_map('stridebr_api_workout_exercise_payload', $exerciseStmt->fetchAll());
    $sport = !empty($row['idmodalidade']) ? [
        'idmodalidade' => $row['idmodalidade'], 'nome' => $row['modalidade_nome'], 'slug' => $row['modalidade_slug'],
        'familia_hub' => $row['modalidade_family'], 'permite_rota' => $row['modalidade_route'],
    ] : null;
    return [
        'id' => (string) $row['idtreino_modelo'],
        'title' => (string) $row['titulo'],
        'code' => $row['codigo'] !== null ? (string) $row['codigo'] : null,
        'objective' => $row['foco'] !== null ? (string) $row['foco'] : null,
        'notes' => $row['descricao'] !== null ? (string) $row['descricao'] : null,
        'sport' => stridebr_api_workout_sport_payload($sport),
        'exercise_count' => count($exercises),
        'created_at' => stridebr_api_iso(isset($row['data_criacao']) ? (string) $row['data_criacao'] : null),
        'updated_at' => stridebr_api_iso(isset($row['data_atualizacao']) ? (string) $row['data_atualizacao'] : null),
        'structure' => ['exercise_count' => count($exercises), 'exercises' => $exercises],
    ];
}

function stridebr_api_workout_templates(PDO $pdo, string $userId, array $filters): array
{
    $page = max(1, min(100000, (int) ($filters['page'] ?? 1)));
    $limit = max(1, min(100, (int) ($filters['limit'] ?? 25)));
    $q = trim((string) ($filters['q'] ?? ''));
    $where = ['tm.idusuario = :user', 'tm.ativo = TRUE'];
    $params = [':user' => $userId];
    if ($q !== '') {
        $where[] = '(tm.titulo ILIKE :q OR tm.codigo ILIKE :q OR tm.foco ILIKE :q)';
        $params[':q'] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
    }
    $condition = implode(' AND ', $where);
    $count = $pdo->prepare("SELECT COUNT(*) FROM treinos_modelo tm WHERE {$condition}");
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $stmt = $pdo->prepare(
        "SELECT tm.idtreino_modelo, tm.titulo, tm.codigo, tm.foco, tm.descricao, tm.idmodalidade, tm.data_atualizacao,
                m.nome AS modalidade_nome, m.slug AS modalidade_slug, m.familia_hub AS modalidade_family, m.permite_rota AS modalidade_route,
                (SELECT COUNT(*) FROM treinos_modelo_exercicios tme WHERE tme.idtreino_modelo = tm.idtreino_modelo) AS exercise_count
           FROM treinos_modelo tm
           LEFT JOIN modalidades m ON m.idmodalidade = tm.idmodalidade
          WHERE {$condition}
          ORDER BY tm.data_atualizacao DESC, tm.titulo
          LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $name => $value) $stmt->bindValue($name, $value, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', ($page - 1) * $limit, PDO::PARAM_INT);
    $stmt->execute();
    $data = [];
    foreach ($stmt->fetchAll() as $row) {
        $sport = !empty($row['idmodalidade']) ? [
            'idmodalidade' => $row['idmodalidade'], 'nome' => $row['modalidade_nome'], 'slug' => $row['modalidade_slug'],
            'familia_hub' => $row['modalidade_family'], 'permite_rota' => $row['modalidade_route'],
        ] : null;
        $data[] = [
            'id' => (string) $row['idtreino_modelo'],
            'title' => (string) $row['titulo'],
            'code' => $row['codigo'] !== null ? (string) $row['codigo'] : null,
            'objective' => $row['foco'] !== null ? (string) $row['foco'] : null,
            'sport' => stridebr_api_workout_sport_payload($sport),
            'exercise_count' => (int) $row['exercise_count'],
            'updated_at' => stridebr_api_iso((string) $row['data_atualizacao']),
        ];
    }
    return ['data' => $data, 'meta' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => max(1, (int) ceil($total / $limit))]];
}

function stridebr_api_workout_create(PDO $pdo, string $userId, array $payload): array
{
    $templateId = trim((string) ($payload['template_id'] ?? ''));
    $template = $templateId !== '' ? stridebr_api_workout_template($pdo, $userId, $templateId) : [];
    if ($templateId !== '' && $template === []) throw new InvalidArgumentException('Template não encontrado.');
    $title = array_key_exists('title', $payload)
        ? stridebr_api_workout_text($payload['title'], 120, 'title', true)
        : ($template['title'] ?? null);
    if ($title === null || $title === '') throw new InvalidArgumentException('title é obrigatório.');
    $date = stridebr_api_workout_date($payload['date'] ?? '', 'date');
    $time = array_key_exists('time', $payload) ? stridebr_api_workout_time($payload['time']) : null;
    $duration = array_key_exists('planned_duration_s', $payload) ? stridebr_api_workout_duration_minutes($payload['planned_duration_s']) : null;
    $distance = array_key_exists('planned_distance_m', $payload) ? stridebr_api_workout_distance($payload['planned_distance_m']) : null;
    $notes = array_key_exists('notes', $payload) ? stridebr_api_workout_text($payload['notes'], 5000, 'notes') : ($template['notes'] ?? null);
    $objective = array_key_exists('objective', $payload) ? stridebr_api_workout_text($payload['objective'], 160, 'objective') : ($template['objective'] ?? null);
    $intensity = array_key_exists('intensity', $payload) ? stridebr_api_workout_text($payload['intensity'], 80, 'intensity') : null;
    if (array_key_exists('sport', $payload)) {
        $sport = stridebr_api_workout_modality($pdo, $userId, (string) $payload['sport']);
    } elseif (!empty($template['sport']['id'])) {
        $sport = stridebr_api_workout_modality($pdo, $userId, (string) $template['sport']['id']);
    } else {
        throw new InvalidArgumentException('sport é obrigatório.');
    }

    $id = stridebr_api_id();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO treinos_agendados
                (idagendamento,idatleta,idcriador,idtreino_modelo_origem,idmodalidade,data_treino,hora_inicio,duracao_prevista_min,distancia_prevista_m,titulo,descricao,intensidade,objetivo,origem,status,publicado_em)
             VALUES
                (:id,:user,:creator,:template,:sport,:date,:time,:duration,:distance,:title,:notes,:intensity,:objective,'usuario','publicado',NOW())"
        );
        $stmt->execute([
            ':id' => $id, ':user' => $userId, ':creator' => $userId, ':template' => $templateId !== '' ? $templateId : null,
            ':sport' => $sport['idmodalidade'], ':date' => $date, ':time' => $time, ':duration' => $duration, ':distance' => $distance,
            ':title' => $title, ':notes' => $notes, ':intensity' => $intensity, ':objective' => $objective,
        ]);
        if ($templateId !== '') {
            $copy = $pdo->prepare(
                'INSERT INTO treinos_agendados_exercicios
                    (idagendamento_exercicio,idagendamento,idexercicio,nome_snapshot,series,repeticoes,carga,bloco,cluster,descanso,observacoes,duracao,distancia,intensidade,rpe,rir,tempo_execucao,cadencia,ordem)
                 SELECT substr(md5(:appointment || idtreino_modelo_exercicio || random()::text),1,21), :appointment2, idexercicio, nome_snapshot, series, repeticoes, carga, bloco, cluster, descanso, observacoes, duracao, distancia, intensidade, rpe, rir, tempo_execucao, cadencia, ordem
                   FROM treinos_modelo_exercicios
                  WHERE idtreino_modelo = :template
                  ORDER BY ordem'
            );
            $copy->execute([':appointment' => $id, ':appointment2' => $id, ':template' => $templateId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return stridebr_api_workout_detail($pdo, $userId, stridebr_api_workout_id_scheduled($id));
}

function stridebr_api_workout_update(PDO $pdo, string $userId, string $apiId, array $payload): array
{
    $parsed = stridebr_api_workout_parse_id($apiId);
    if ($parsed['kind'] !== 'scheduled') throw new RuntimeException('Ocorrências recorrentes continuam editáveis pelo cronograma Web nesta versão da API.');
    $stmt = $pdo->prepare("SELECT * FROM treinos_agendados WHERE idagendamento = :id AND idatleta = :user AND idcriador = :user_creator AND origem = 'usuario' AND status IN ('rascunho','publicado') LIMIT 1");
    $stmt->execute([':id' => $parsed['id'], ':user' => $userId, ':user_creator' => $userId]);
    $current = $stmt->fetch();
    if (!$current) throw new RuntimeException('Este treino não pode ser editado pelo usuário atual.');

    $values = [
        'title' => (string) $current['titulo'],
        'date' => (string) $current['data_treino'],
        'time' => $current['hora_inicio'] !== null ? substr((string) $current['hora_inicio'], 0, 5) : null,
        'duration' => $current['duracao_prevista_min'] !== null ? (int) $current['duracao_prevista_min'] : null,
        'distance' => $current['distancia_prevista_m'] !== null ? (float) $current['distancia_prevista_m'] : null,
        'notes' => $current['descricao'] !== null ? (string) $current['descricao'] : null,
        'objective' => $current['objetivo'] !== null ? (string) $current['objetivo'] : null,
        'intensity' => $current['intensidade'] !== null ? (string) $current['intensidade'] : null,
        'sport' => (string) ($current['idmodalidade'] ?? ''),
    ];
    if (array_key_exists('title', $payload)) $values['title'] = (string) stridebr_api_workout_text($payload['title'], 120, 'title', true);
    if (array_key_exists('date', $payload)) $values['date'] = stridebr_api_workout_date($payload['date'], 'date');
    if (array_key_exists('time', $payload)) $values['time'] = stridebr_api_workout_time($payload['time']);
    if (array_key_exists('planned_duration_s', $payload)) $values['duration'] = stridebr_api_workout_duration_minutes($payload['planned_duration_s']);
    if (array_key_exists('planned_distance_m', $payload)) $values['distance'] = stridebr_api_workout_distance($payload['planned_distance_m']);
    if (array_key_exists('notes', $payload)) $values['notes'] = stridebr_api_workout_text($payload['notes'], 5000, 'notes');
    if (array_key_exists('objective', $payload)) $values['objective'] = stridebr_api_workout_text($payload['objective'], 160, 'objective');
    if (array_key_exists('intensity', $payload)) $values['intensity'] = stridebr_api_workout_text($payload['intensity'], 80, 'intensity');
    if (array_key_exists('sport', $payload)) $values['sport'] = (string) stridebr_api_workout_modality($pdo, $userId, (string) $payload['sport'])['idmodalidade'];
    if ($values['sport'] === '') throw new InvalidArgumentException('sport é obrigatório.');

    $update = $pdo->prepare(
        'UPDATE treinos_agendados
            SET titulo=:title,data_treino=:date,hora_inicio=:time,duracao_prevista_min=:duration,distancia_prevista_m=:distance,
                descricao=:notes,objetivo=:objective,intensidade=:intensity,idmodalidade=:sport,data_atualizacao=NOW()
          WHERE idagendamento=:id AND idatleta=:user AND idcriador=:creator AND origem=\'usuario\' AND status IN (\'rascunho\',\'publicado\')'
    );
    $update->execute([
        ':title' => $values['title'], ':date' => $values['date'], ':time' => $values['time'], ':duration' => $values['duration'],
        ':distance' => $values['distance'], ':notes' => $values['notes'], ':objective' => $values['objective'], ':intensity' => $values['intensity'],
        ':sport' => $values['sport'], ':id' => $parsed['id'], ':user' => $userId, ':creator' => $userId,
    ]);
    return stridebr_api_workout_detail($pdo, $userId, $apiId);
}

function stridebr_api_workout_cancel(PDO $pdo, string $userId, string $apiId): array
{
    $parsed = stridebr_api_workout_parse_id($apiId);
    if ($parsed['kind'] === 'scheduled') {
        $stmt = $pdo->prepare("UPDATE treinos_agendados SET status='cancelado',data_atualizacao=NOW() WHERE idagendamento=:id AND idatleta=:user AND status IN ('rascunho','publicado')");
        $stmt->execute([':id' => $parsed['id'], ':user' => $userId]);
        if ($stmt->rowCount() !== 1) {
            $detail = stridebr_api_workout_detail($pdo, $userId, $apiId);
            if ($detail === [] || (string) ($detail['status'] ?? '') !== 'cancelado') throw new RuntimeException('Este treino não pode ser cancelado.');
        }
        return stridebr_api_workout_detail($pdo, $userId, $apiId);
    }
    $row = stridebr_api_workout_recurring_occurrence($pdo, $userId, (string) $parsed['id'], (string) $parsed['date']);
    if ($row === []) throw new RuntimeException('Treino não encontrado.');
    if (!empty($row['idregistro'])) throw new RuntimeException('Treino já realizado não pode ser cancelado.');
    if ((string) ($row['status'] ?? '') !== 'cancelado') cronogramaCancelarOcorrencia($pdo, $userId, (string) $parsed['id'], (string) $parsed['date']);
    return stridebr_api_workout_detail($pdo, $userId, $apiId);
}

function stridebr_api_workout_complete(PDO $pdo, string $userId, string $apiId): array
{
    $parsed = stridebr_api_workout_parse_id($apiId);
    if ($parsed['kind'] !== 'scheduled') throw new RuntimeException('Treinos recorrentes são concluídos por uma atividade vinculada nesta versão.');
    $stmt = $pdo->prepare("UPDATE treinos_agendados SET status='concluido',data_atualizacao=NOW() WHERE idagendamento=:id AND idatleta=:user AND status='publicado'");
    $stmt->execute([':id' => $parsed['id'], ':user' => $userId]);
    if ($stmt->rowCount() !== 1) {
        $detail = stridebr_api_workout_detail($pdo, $userId, $apiId);
        if ($detail === [] || (string) ($detail['status'] ?? '') !== 'concluido') throw new RuntimeException('Este treino não pode ser concluído.');
    }
    return stridebr_api_workout_detail($pdo, $userId, $apiId);
}

function stridebr_api_workout_prepare_activity_link(PDO $pdo, string $userId, string $apiId, string $activitySportId): array
{
    $detail = stridebr_api_workout_detail($pdo, $userId, $apiId);
    if ($detail === []) throw new InvalidArgumentException('workout_id não pertence ao usuário ou não existe.');
    $status = (string) ($detail['status'] ?? '');
    if (!in_array($status, ['publicado', 'concluido'], true)) throw new InvalidArgumentException('O treino precisa estar publicado para receber uma atividade.');
    $workoutSport = (string) ($detail['sport']['id'] ?? '');
    if ($workoutSport !== '' && $activitySportId !== '' && $workoutSport !== $activitySportId) throw new InvalidArgumentException('A modalidade da atividade não corresponde ao treino planejado.');
    return ['id' => $apiId, 'detail' => $detail, 'parsed' => stridebr_api_workout_parse_id($apiId)];
}

function stridebr_api_workout_apply_activity_payload(array $activityPayload, array $link): array
{
    if (($link['parsed']['kind'] ?? '') !== 'recurring') return $activityPayload;
    $detail = $link['detail'];
    $activityPayload['idcronograma'] = $detail['schedule_id'] ?? null;
    $activityPayload['idtreino_cronograma'] = $detail['schedule_workout_id'] ?? null;
    $activityPayload['data_ocorrencia_origem'] = $detail['original_date'] ?? $detail['date'];
    $activityPayload['data_ocorrencia_planejada'] = $detail['date'] ?? null;
    $activityPayload['hora_ocorrencia_planejada'] = $detail['time'] ?? null;
    return $activityPayload;
}

function stridebr_api_workout_link_activity(PDO $pdo, string $userId, string $activityId, array $link): void
{
    $parsed = $link['parsed'];
    if (($parsed['kind'] ?? '') === 'recurring') {
        $detail = $link['detail'];
        if (!empty($detail['activity']['id']) && (string) $detail['activity']['id'] !== $activityId) throw new RuntimeException('Esta ocorrência já está ligada a outra atividade.');
        $stmt = $pdo->prepare('SELECT idtreino_cronograma FROM registros_atividade WHERE idregistro=:activity AND idusuario=:user AND excluido_em IS NULL LIMIT 1');
        $stmt->execute([':activity' => $activityId, ':user' => $userId]);
        $current = $stmt->fetchColumn();
        if ($current !== false && $current !== null && $current !== '' && (string) $current !== (string) $detail['schedule_workout_id']) throw new RuntimeException('A atividade já está vinculada a outro treino.');
        $update = $pdo->prepare('UPDATE registros_atividade SET idcronograma=:schedule,idtreino_cronograma=:workout,data_ocorrencia_origem=:original,data_ocorrencia_planejada=:planned,hora_ocorrencia_planejada=:time,data_atualizacao=NOW() WHERE idregistro=:activity AND idusuario=:user AND excluido_em IS NULL');
        $update->execute([
            ':schedule' => $detail['schedule_id'], ':workout' => $detail['schedule_workout_id'], ':original' => $detail['original_date'] ?? $detail['date'],
            ':planned' => $detail['date'], ':time' => $detail['time'] !== null ? $detail['time'] . ':00' : null, ':activity' => $activityId, ':user' => $userId,
        ]);
        return;
    }

    $appointmentId = (string) $parsed['id'];
    $activityStmt = $pdo->prepare('SELECT data_inicio,data_fim FROM registros_atividade WHERE idregistro=:activity AND idusuario=:user AND excluido_em IS NULL LIMIT 1');
    $activityStmt->execute([':activity' => $activityId, ':user' => $userId]);
    $activity = $activityStmt->fetch();
    if (!$activity) throw new RuntimeException('Atividade não encontrada para vínculo.');

    $conflict = $pdo->prepare('SELECT idagendamento_origem FROM sessoes_treino WHERE idusuario=:user AND idregistro_atividade=:activity AND idagendamento_origem IS NOT NULL LIMIT 1');
    $conflict->execute([':user' => $userId, ':activity' => $activityId]);
    $linkedAppointment = $conflict->fetchColumn();
    if ($linkedAppointment !== false && (string) $linkedAppointment !== $appointmentId) throw new RuntimeException('A atividade já está vinculada a outro treino agendado.');

    $existing = $pdo->prepare('SELECT idsessao,idregistro_atividade FROM sessoes_treino WHERE idusuario=:user AND idagendamento_origem=:appointment AND status=\'concluido\' ORDER BY data_criacao DESC LIMIT 1');
    $existing->execute([':user' => $userId, ':appointment' => $appointmentId]);
    $session = $existing->fetch();
    if ($session) {
        if ((string) ($session['idregistro_atividade'] ?? '') !== $activityId) throw new RuntimeException('O treino agendado já está ligado a outra atividade.');
    } else {
        $appointment = $pdo->prepare("SELECT idcronograma_origem,idtreino_origem,data_treino,hora_inicio,titulo FROM treinos_agendados WHERE idagendamento=:id AND idatleta=:user AND status IN ('publicado','concluido') LIMIT 1");
        $appointment->execute([':id' => $appointmentId, ':user' => $userId]);
        $row = $appointment->fetch();
        if (!$row) throw new RuntimeException('Treino agendado não encontrado.');
        $insert = $pdo->prepare("INSERT INTO sessoes_treino (idsessao,idusuario,idcronograma_origem,idtreino_origem,idagendamento_origem,idregistro_atividade,titulo_snapshot,status,data_inicio,data_fim,data_ocorrencia_planejada,hora_ocorrencia_planejada) VALUES (:id,:user,:schedule,:workout,:appointment,:activity,:title,'concluido',:start,:end,:planned,:time)");
        $insert->execute([
            ':id' => stridebr_api_id(), ':user' => $userId, ':schedule' => $row['idcronograma_origem'] ?: null, ':workout' => $row['idtreino_origem'] ?: null,
            ':appointment' => $appointmentId, ':activity' => $activityId, ':title' => $row['titulo'], ':start' => $activity['data_inicio'], ':end' => $activity['data_fim'],
            ':planned' => $row['data_treino'], ':time' => $row['hora_inicio'],
        ]);
    }
    $pdo->prepare("UPDATE treinos_agendados SET status='concluido',data_atualizacao=NOW() WHERE idagendamento=:id AND idatleta=:user AND status IN ('publicado','concluido')")->execute([':id' => $appointmentId, ':user' => $userId]);
}
