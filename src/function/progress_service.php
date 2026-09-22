<?php

declare(strict_types=1);

require_once __DIR__ . '/sport_hub.php';
require_once __DIR__ . '/planejamento.php';
require_once __DIR__ . '/training_platform_service.php';

function progressTimezone(): DateTimeZone
{
    return new DateTimeZone('America/Sao_Paulo');
}

function progressParseDate(mixed $value, string $field): DateTimeImmutable
{
    $raw = trim((string) $value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, progressTimezone());
    if (!$date || $date->format('Y-m-d') !== $raw) throw new InvalidArgumentException($field . ' deve usar YYYY-MM-DD.');
    return $date;
}

function progressResolveRange(array $filters, int $defaultDays = 28): array
{
    $hasFrom = array_key_exists('from', $filters) && trim((string) $filters['from']) !== '';
    $hasTo = array_key_exists('to', $filters) && trim((string) $filters['to']) !== '';
    if ($hasFrom !== $hasTo) throw new InvalidArgumentException('from e to precisam ser enviados juntos.');
    if ($hasFrom) {
        $from = progressParseDate($filters['from'], 'from');
        $to = progressParseDate($filters['to'], 'to');
    } else {
        $to = new DateTimeImmutable('today', progressTimezone());
        $from = $to->modify('-' . max(0, $defaultDays - 1) . ' days');
    }
    if ($to < $from) throw new InvalidArgumentException('to precisa ser igual ou posterior a from.');
    $days = (int) ($from->diff($to)->days ?? 0) + 1;
    if ($days > 1827) throw new InvalidArgumentException('O intervalo de progresso deve ter no máximo 1827 dias.');
    $end = $to->modify('+1 day');
    $previousEnd = $from;
    $previousStart = $previousEnd->modify('-' . $days . ' days');
    return [
        'from' => $from->format('Y-m-d'),
        'to' => $to->format('Y-m-d'),
        'days' => $days,
        'start' => $from,
        'end' => $end,
        'previous_from' => $previousStart->format('Y-m-d'),
        'previous_to' => $previousEnd->modify('-1 day')->format('Y-m-d'),
        'previous_start' => $previousStart,
        'previous_end' => $previousEnd,
        'timezone' => 'America/Sao_Paulo',
    ];
}

function progressResolveSport(PDO $pdo, string $userId, mixed $value): ?array
{
    $sportRef = trim((string) $value);
    $normalized = stridebr_lower($sportRef);
    if ($normalized === '' || $normalized === 'all') return null;
    $stmt = $pdo->prepare("SELECT idmodalidade, nome, slug, categoria, familia_hub, metrica_derivada, permite_rota
        FROM modalidades
        WHERE ativo = TRUE
          AND (idusuario IS NULL OR idusuario = :user)
          AND (lower(slug) = :slug OR idmodalidade = :id)
        ORDER BY CASE WHEN idusuario = :owner THEN 0 ELSE 1 END
        LIMIT 1");
    $stmt->execute([':user' => $userId, ':slug' => $normalized, ':id' => $sportRef, ':owner' => $userId]);
    $row = $stmt->fetch();
    if (!$row) throw new InvalidArgumentException('sport inválido.');
    $family = sportHubBucket((string) ($row['categoria'] ?? ''), (string) $row['slug'], (string) ($row['familia_hub'] ?? ''));
    return [
        'id' => (string) $row['idmodalidade'],
        'slug' => (string) $row['slug'],
        'name' => (string) $row['nome'],
        'family' => $family,
        'route_capable' => stridebr_db_bool($row['permite_rota'] ?? false),
        'derived_metric' => trim((string) ($row['metrica_derivada'] ?? '')) ?: 'nenhuma',
        'behavior' => progressSportBehavior((string) $row['slug'], $family, (string) ($row['metrica_derivada'] ?? '')),
    ];
}
function progressSportBehavior(string $slug, string $family, string $derivedMetric = ''): ?string
{
    $derivedMetric = stridebr_lower(trim($derivedMetric));
    if ($derivedMetric === 'pace_km') return 'pace';
    if ($derivedMetric === 'velocidade_kmh') return 'speed';
    if ($derivedMetric === 'pace_100m') return 'pace_100m';
    if ($derivedMetric === 'split_500m') return 'distance_time';
    if ($family === 'cardio') return 'distance_time';
    return null;
}

function progressFilterSport(array $rows, ?array $sport): array
{
    if ($sport === null) return array_values($rows);
    $sportId = (string) ($sport['id'] ?? '');
    return array_values(array_filter($rows, static fn(array $row): bool => (string) ($row['idmodalidade'] ?? '') === $sportId));
}

function progressLoadActivities(PDO $pdo, string $userId, array $range, ?array $sport = null): array
{
    $rows = sportHubActivityRowsQuery($pdo, $userId, $range['previous_start'], $range['end']);
    $rows = progressFilterSport($rows, $sport);
    $current = [];
    $previous = [];
    foreach ($rows as $row) {
        try {
            $date = (new DateTimeImmutable((string) $row['data_inicio']))->setTimezone(progressTimezone());
        } catch (Throwable) {
            continue;
        }
        if ($date >= $range['start'] && $date < $range['end']) $current[] = $row;
        elseif ($date >= $range['previous_start'] && $date < $range['previous_end']) $previous[] = $row;
    }
    return ['all' => $rows, 'current' => $current, 'previous' => $previous];
}

function progressKnownSum(array $rows, string $field): int|float|null
{
    if ($rows === []) return 0;
    $sum = 0.0;
    $known = 0;
    foreach ($rows as $row) {
        if (!is_numeric($row[$field] ?? null)) continue;
        $sum += max(0.0, (float) $row[$field]);
        $known++;
    }
    if ($known === 0) return null;
    return $sum;
}

function progressEffortSummary(array $rows): ?array
{
    $values = [];
    foreach ($rows as $row) {
        if (!is_numeric($row['esforco_percebido'] ?? null)) continue;
        $value = (float) $row['esforco_percebido'];
        if ($value < 1 || $value > 10) continue;
        $values[] = $value;
    }
    if ($values === []) return null;
    return [
        'count' => count($values),
        'average' => array_sum($values) / count($values),
        'min' => min($values),
        'max' => max($values),
    ];
}

function progressTrainingLoad(array $rows): int|float|null
{
    if ($rows === []) return 0;
    $load = sportHubTrainingLoad($rows);
    return (int) ($load['covered'] ?? 0) > 0 ? (float) $load['value'] : null;
}

function progressWeeksWithActivity(array $rows, array $range): array
{
    $weeks = [];
    $cursor = $range['start']->modify('monday this week')->setTime(0, 0);
    $last = $range['to'];
    while ($cursor->format('Y-m-d') <= $last) {
        $weeks[$cursor->format('Y-m-d')] = false;
        $cursor = $cursor->modify('+7 days');
    }
    foreach ($rows as $row) {
        try {
            $date = (new DateTimeImmutable((string) $row['data_inicio']))->setTimezone(progressTimezone());
        } catch (Throwable) {
            continue;
        }
        $weeks[$date->modify('monday this week')->format('Y-m-d')] = true;
    }
    return ['weeks_with_activity' => count(array_filter($weeks)), 'weeks_in_range' => count($weeks)];
}

function progressActivitySummary(array $rows, array $range): array
{
    $days = [];
    foreach ($rows as $row) {
        try {
            $days[(new DateTimeImmutable((string) $row['data_inicio']))->setTimezone(progressTimezone())->format('Y-m-d')] = true;
        } catch (Throwable) {
        }
    }
    $weeks = progressWeeksWithActivity($rows, $range);
    return [
        'activities_count' => count($rows),
        'active_days' => count($days),
        'total_duration_s' => progressKnownSum($rows, 'duration_raw_s'),
        'total_distance_m' => progressKnownSum($rows, 'distancia_raw_m'),
        'elevation_gain_m' => progressKnownSum($rows, 'elevacao_raw_m'),
        'total_training_load' => progressTrainingLoad($rows),
        'perceived_effort' => progressEffortSummary($rows),
        'weeks_with_activity' => $weeks['weeks_with_activity'],
        'weeks_in_range' => $weeks['weeks_in_range'],
    ];
}

function progressComparison(int|float|null $current, int|float|null $previous): array
{
    $delta = $current !== null && $previous !== null ? $current - $previous : null;
    $percent = $delta !== null && $previous != 0.0 ? ($delta / $previous) * 100.0 : null;
    return ['current' => $current, 'previous' => $previous, 'delta' => $delta, 'change_percent' => $percent];
}

function progressSummaryComparisons(array $current, array $previous): array
{
    $keys = ['activities_count', 'active_days', 'total_duration_s', 'total_distance_m', 'elevation_gain_m', 'total_training_load'];
    $result = [];
    foreach ($keys as $key) $result[$key] = progressComparison($current[$key] ?? null, $previous[$key] ?? null);
    $result['perceived_effort_average'] = progressComparison($current['perceived_effort']['average'] ?? null, $previous['perceived_effort']['average'] ?? null);
    return $result;
}

function progressRecurringOccurrences(PDO $pdo, string $userId, array $range): array
{
    $items = [];
    $cursor = $range['start'];
    while ($cursor < $range['end']) {
        $chunkEnd = $cursor->modify('+93 days');
        if ($chunkEnd >= $range['end']) $chunkEnd = $range['end']->modify('-1 day');
        $from = $cursor->format('Y-m-d');
        $to = $chunkEnd->format('Y-m-d');
        $chunk = cronogramaListarOcorrencias($pdo, $userId, $from, $to, null, true);
        foreach ($chunk as &$item) $item['progress_planned_duration_s'] = progressPlannedDurationSeconds($item['hora_inicio'] ?? null, $item['hora_fim'] ?? null, stridebr_db_bool($item['termina_dia_seguinte'] ?? false));
        unset($item);
        $chunk = cronogramaConciliarOcorrenciasComRegistros($pdo, $userId, $chunk, $from, $to, null);
        foreach ($chunk as $item) {
            $planned = (string) ($item['data_planejada'] ?? $item['data_treino'] ?? '');
            if ($planned < $range['from'] || $planned > $range['to']) continue;
            $key = (string) ($item['idtreino'] ?? '') . ':' . (string) ($item['data_original'] ?? $planned);
            $items[$key] = $item;
        }
        $cursor = $chunkEnd->modify('+1 day');
    }
    return array_values($items);
}

function progressPlannedDurationSeconds(mixed $start, mixed $end, bool $nextDay = false): ?int
{
    $start = substr(trim((string) $start), 0, 5);
    $end = substr(trim((string) $end), 0, 5);
    if (preg_match('/^\d{2}:\d{2}$/', $start) !== 1 || preg_match('/^\d{2}:\d{2}$/', $end) !== 1) return null;
    $startMinutes = ((int) substr($start, 0, 2)) * 60 + (int) substr($start, 3, 2);
    $endMinutes = ((int) substr($end, 0, 2)) * 60 + (int) substr($end, 3, 2);
    $minutes = $nextDay ? 1440 - $startMinutes + $endMinutes : $endMinutes - $startMinutes;
    return $minutes > 0 ? $minutes * 60 : null;
}

function progressAppointmentOccurrences(PDO $pdo, string $userId, array $range): array
{
    $stmt = $pdo->prepare("SELECT ta.idagendamento, ta.idtreino_origem, ta.idmodalidade, ta.data_treino, ta.hora_inicio, ta.duracao_prevista_min, ta.distancia_prevista_m, ta.status,
            COALESCE(ta.idmodalidade, tc.idmodalidade, tm.idmodalidade) AS resolved_idmodalidade,
            m.slug AS modalidade_slug, m.categoria, m.familia_hub, m.permite_rota,
            completed.idregistro, completed.data_inicio AS realizada
        FROM treinos_agendados ta
        LEFT JOIN treinos_cronograma tc ON tc.idtreino = ta.idtreino_origem
        LEFT JOIN treinos_modelo tm ON tm.idtreino_modelo = COALESCE(ta.idtreino_modelo_origem, tc.idtreino_modelo)
        LEFT JOIN modalidades m ON m.idmodalidade = COALESCE(ta.idmodalidade, tc.idmodalidade, tm.idmodalidade)
        LEFT JOIN LATERAL (
            SELECT st.idregistro_atividade AS idregistro, ra.data_inicio
            FROM sessoes_treino st
            JOIN registros_atividade ra ON ra.idregistro = st.idregistro_atividade
            WHERE st.idagendamento_origem = ta.idagendamento AND st.idusuario = ta.idatleta
              AND st.status = 'concluido' AND ra.idusuario = ta.idatleta AND ra.status = 'concluido' AND ra.excluido_em IS NULL AND COALESCE(ra.excluir_estatisticas,FALSE)=FALSE
            ORDER BY ra.data_inicio DESC LIMIT 1
        ) completed ON TRUE
        WHERE ta.idatleta = :user AND ta.data_treino BETWEEN :from AND :to
          AND ta.status IN ('publicado','concluido','cancelado')
        ORDER BY ta.data_treino, ta.hora_inicio, ta.idagendamento");
    $stmt->execute([':user' => $userId, ':from' => $range['from'], ':to' => $range['to']]);
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $row['data_planejada'] = (string) $row['data_treino'];
        $row['concluido'] = !empty($row['idregistro']) || (string) ($row['status'] ?? '') === 'concluido';
        $row['realizado_fora_planejado'] = !empty($row['realizada']) && substr((string) $row['realizada'], 0, 10) !== (string) $row['data_treino'];
        $row['progress_planned_duration_s'] = $row['duracao_prevista_min'] !== null ? (int) $row['duracao_prevista_min'] * 60 : null;
        $row['progress_planned_distance_m'] = is_numeric($row['distancia_prevista_m'] ?? null) ? (float) $row['distancia_prevista_m'] : null;
        $row['progress_route_capable'] = stridebr_db_bool($row['permite_rota'] ?? false);
        $row['progress_family'] = sportHubBucket((string) ($row['categoria'] ?? ''), (string) ($row['modalidade_slug'] ?? ''), (string) ($row['familia_hub'] ?? ''));
        $items[] = $row;
    }
    return $items;
}

function progressAdherence(PDO $pdo, string $userId, array $range, ?array $sport = null): array
{
    $items = [];
    $recurring = progressRecurringOccurrences($pdo, $userId, $range);
    $sportIds = array_values(array_unique(array_filter(array_map(static fn(array $row): string => trim((string) ($row['idmodalidade'] ?? '')), $recurring))));
    $sportMeta = [];
    if ($sportIds !== []) {
        $placeholders = implode(',', array_fill(0, count($sportIds), '?'));
        $sportStmt = $pdo->prepare("SELECT idmodalidade,slug,categoria,familia_hub,permite_rota FROM modalidades WHERE idmodalidade IN ({$placeholders})");
        $sportStmt->execute($sportIds);
        foreach ($sportStmt->fetchAll() as $meta) $sportMeta[(string) $meta['idmodalidade']] = $meta;
    }
    foreach ($recurring as $row) {
        $row['progress_kind'] = 'recurring';
        $row['progress_planned_distance_m'] = null;
        $row['progress_route_capable'] = null;
        $row['progress_sport_slug'] = null;
        $row['progress_sport_id'] = trim((string) ($row['idmodalidade'] ?? '')) ?: null;
        $meta = $sportMeta[(string) ($row['idmodalidade'] ?? '')] ?? null;
        if ($meta) {
            $row['progress_sport_slug'] = (string) $meta['slug'];
            $row['progress_route_capable'] = stridebr_db_bool($meta['permite_rota'] ?? false);
            $row['progress_family'] = sportHubBucket((string) ($meta['categoria'] ?? ''), (string) $meta['slug'], (string) ($meta['familia_hub'] ?? ''));
        }
        $items[] = $row;
    }
    foreach (progressAppointmentOccurrences($pdo, $userId, $range) as $row) {
        $row['progress_kind'] = 'appointment';
        $row['progress_sport_slug'] = (string) ($row['modalidade_slug'] ?? '');
        $row['progress_sport_id'] = trim((string) ($row['resolved_idmodalidade'] ?? '')) ?: null;
        $items[] = $row;
    }
    if ($sport !== null) {
        $sportId = (string) $sport['id'];
        $items = array_values(array_filter($items, static fn(array $row): bool => (string) ($row['progress_sport_id'] ?? '') === $sportId));
    }
    $today = (new DateTimeImmutable('today', progressTimezone()))->format('Y-m-d');
    $counts = ['total_count' => count($items), 'planned_count' => 0, 'completed_count' => 0, 'cancelled_count' => 0, 'pending_count' => 0, 'past_due_count' => 0];
    $linkedIds = [];
    foreach ($items as &$row) {
        $state = planejamentoEstado($row, $today);
        $row['progress_state'] = $state;
        if ($state === 'cancelled') {
            $counts['cancelled_count']++;
            continue;
        }
        $counts['planned_count']++;
        if (in_array($state, ['completed', 'shifted'], true)) $counts['completed_count']++;
        elseif ($state === 'missed') $counts['past_due_count']++;
        else $counts['pending_count']++;
        if (!empty($row['idregistro'])) $linkedIds[] = (string) $row['idregistro'];
    }
    unset($row);
    $actual = [];
    foreach (sportHubActivityRowsQuery($pdo, $userId, null, null, $linkedIds) as $row) $actual[(string) $row['idregistro']] = $row;
    $durationPlanned = 0.0;
    $durationActual = 0.0;
    $durationComparable = 0;
    $distancePlanned = 0.0;
    $distanceActual = 0.0;
    $distanceComparable = 0;
    foreach ($items as $row) {
        if (!in_array($row['progress_state'] ?? '', ['completed', 'shifted'], true)) continue;
        $activity = !empty($row['idregistro']) ? ($actual[(string) $row['idregistro']] ?? null) : null;
        if (!$activity) continue;
        $plannedDuration = is_numeric($row['progress_planned_duration_s'] ?? null) ? (float) $row['progress_planned_duration_s'] : null;
        $actualDuration = is_numeric($activity['duration_raw_s'] ?? null) ? (float) $activity['duration_raw_s'] : null;
        if ($plannedDuration !== null && $actualDuration !== null) {
            $durationPlanned += $plannedDuration;
            $durationActual += $actualDuration;
            $durationComparable++;
        }
        $plannedDistance = is_numeric($row['progress_planned_distance_m'] ?? null) ? (float) $row['progress_planned_distance_m'] : null;
        $actualDistance = is_numeric($activity['distancia_raw_m'] ?? null) ? (float) $activity['distancia_raw_m'] : null;
        $routeCapable = $row['progress_route_capable'] === true || stridebr_db_bool($row['progress_route_capable'] ?? false);
        if ($routeCapable && $plannedDistance !== null && $actualDistance !== null) {
            $distancePlanned += $plannedDistance;
            $distanceActual += $actualDistance;
            $distanceComparable++;
        }
    }
    $counts['due_count'] = $counts['completed_count'] + $counts['past_due_count'];
    $counts['completion_rate'] = $counts['due_count'] > 0 ? $counts['completed_count'] / $counts['due_count'] : null;
    $counts['planned_vs_executed'] = [
        'duration' => [
            'planned_duration_s' => $durationComparable > 0 ? $durationPlanned : null,
            'actual_duration_s' => $durationComparable > 0 ? $durationActual : null,
            'comparable_completed_count' => $durationComparable,
        ],
        'distance' => [
            'planned_distance_m' => $distanceComparable > 0 ? $distancePlanned : null,
            'actual_distance_m' => $distanceComparable > 0 ? $distanceActual : null,
            'comparable_completed_count' => $distanceComparable,
        ],
    ];
    return $counts;
}

function progressOverview(PDO $pdo, string $userId, array $filters): array
{
    $range = progressResolveRange($filters);
    $sport = progressResolveSport($pdo, $userId, $filters['sport'] ?? null);
    $activities = progressLoadActivities($pdo, $userId, $range, $sport);
    $current = progressActivitySummary($activities['current'], $range);
    $previousRange = $range;
    $previousRange['start'] = $range['previous_start'];
    $previousRange['end'] = $range['previous_end'];
    $previousRange['from'] = $range['previous_from'];
    $previousRange['to'] = $range['previous_to'];
    $previous = progressActivitySummary($activities['previous'], $previousRange);
    $adherence = progressAdherence($pdo, $userId, $range, $sport);
    $current['total_workouts'] = $adherence['total_count'];
    $current['planned_workouts'] = $adherence['planned_count'];
    $current['completed_workouts'] = $adherence['completed_count'];
    $current['consistency'] = [
        'active_days' => $current['active_days'],
        'weeks_with_activity' => $current['weeks_with_activity'],
        'weeks_in_range' => $current['weeks_in_range'],
        'planned_completion_rate' => $adherence['completion_rate'],
    ];
    unset($current['weeks_with_activity'], $current['weeks_in_range']);
    return [
        'range' => progressRangePayload($range),
        'previous_range' => ['from' => $range['previous_from'], 'to' => $range['previous_to'], 'timezone' => $range['timezone']],
        'sport' => $sport,
        'summary' => $current,
        'adherence' => $adherence,
        'previous_period' => progressSummaryComparisons($current, $previous),
        'units' => progressUnits(),
    ];
}

function progressRangePayload(array $range): array
{
    return ['from' => $range['from'], 'to' => $range['to'], 'days' => $range['days'], 'timezone' => $range['timezone']];
}

function progressUnits(): array
{
    return [
        'duration' => 's',
        'distance' => 'm',
        'elevation_gain' => 'm',
        'load' => 'kg',
        'strength_volume' => 'kg',
        'pace' => 's_per_km',
        'pace_100m' => 's_per_100m',
        'speed' => 'km_h',
        'perceived_effort' => 'rpe_1_10',
        'training_load' => 'session_rpe_au',
        'count' => 'count',
    ];
}

function progressBucketDefinitions(array $range, string $bucket): array
{
    if (!in_array($bucket, ['day', 'week', 'month'], true)) throw new InvalidArgumentException('bucket inválido. Use day, week ou month.');
    $definitions = [];
    if ($bucket === 'day') {
        $cursor = $range['start'];
        while ($cursor < $range['end']) {
            $definitions[] = ['start' => $cursor, 'end' => $cursor->modify('+1 day')];
            $cursor = $cursor->modify('+1 day');
        }
        return $definitions;
    }
    $cursor = $bucket === 'week'
        ? $range['start']->modify('monday this week')->setTime(0, 0)
        : $range['start']->modify('first day of this month')->setTime(0, 0);
    while ($cursor < $range['end']) {
        $next = $bucket === 'week' ? $cursor->modify('+7 days') : $cursor->modify('first day of next month')->setTime(0, 0);
        $definitions[] = ['start' => $cursor, 'end' => $next];
        $cursor = $next;
    }
    return $definitions;
}

function progressRowsInBucket(array $rows, DateTimeImmutable $start, DateTimeImmutable $end, array $range): array
{
    return array_values(array_filter($rows, static function (array $row) use ($start, $end, $range): bool {
        try {
            $date = (new DateTimeImmutable((string) $row['data_inicio']))->setTimezone(progressTimezone());
        } catch (Throwable) {
            return false;
        }
        return $date >= $start && $date < $end && $date >= $range['start'] && $date < $range['end'];
    }));
}

function progressStrengthRows(PDO $pdo, string $userId, array $range): array
{
    return sportHubStrengthSets($pdo, $userId, $range['start'], $range['end'], true);
}

function progressTimeseries(PDO $pdo, string $userId, array $filters): array
{
    $allowedMetrics = ['activities', 'duration', 'distance', 'elevation_gain', 'training_load', 'perceived_effort', 'strength_volume'];
    $metric = stridebr_lower(trim((string) ($filters['metric'] ?? '')));
    if (!in_array($metric, $allowedMetrics, true)) throw new InvalidArgumentException('metric inválido.');
    $bucket = stridebr_lower(trim((string) ($filters['bucket'] ?? 'day')));
    $range = progressResolveRange($filters);
    $sport = progressResolveSport($pdo, $userId, $filters['sport'] ?? null);
    $activities = progressLoadActivities($pdo, $userId, $range, $sport)['current'];
    $strengthRows = $metric === 'strength_volume' ? progressStrengthRows($pdo, $userId, $range) : [];
    $data = [];
    foreach (progressBucketDefinitions($range, $bucket) as $definition) {
        $rows = progressRowsInBucket($activities, $definition['start'], $definition['end'], $range);
        $value = match ($metric) {
            'activities' => count($rows),
            'duration' => progressKnownSum($rows, 'duration_raw_s'),
            'distance' => progressKnownSum($rows, 'distancia_raw_m'),
            'elevation_gain' => progressKnownSum($rows, 'elevacao_raw_m'),
            'training_load' => progressTrainingLoad($rows),
            'perceived_effort' => progressEffortSummary($rows)['average'] ?? null,
            'strength_volume' => progressStrengthVolumeForBucket($rows, $strengthRows, $definition['start'], $definition['end'], $range),
        };
        $bucketEnd = $definition['end']->modify('-1 day');
        $data[] = ['start' => $definition['start']->format('Y-m-d'), 'end' => $bucketEnd->format('Y-m-d'), 'value' => $value];
    }
    $unit = match ($metric) {
        'activities' => 'count',
        'duration' => 's',
        'distance', 'elevation_gain' => 'm',
        'training_load' => 'session_rpe_au',
        'perceived_effort' => 'rpe_1_10',
        'strength_volume' => 'kg',
    };
    return ['range' => progressRangePayload($range), 'sport' => $sport, 'metric' => $metric, 'bucket' => $bucket, 'unit' => $unit, 'data' => $data];
}

function progressStrengthVolumeForBucket(array $activities, array $sets, DateTimeImmutable $start, DateTimeImmutable $end, array $range): int|float|null
{
    $strengthActivities = array_values(array_filter($activities, static fn(array $row): bool => (string) ($row['hub_bucket'] ?? '') === 'strength'));
    if ($strengthActivities === []) return 0;
    $activityIds = array_fill_keys(array_map(static fn(array $row): string => (string) $row['idregistro'], $strengthActivities), true);
    $sum = 0.0;
    $known = 0;
    foreach ($sets as $row) {
        if (!isset($activityIds[(string) ($row['idregistro'] ?? '')]) || !stridebr_db_bool($row['concluida'] ?? false)) continue;
        try {
            $date = (new DateTimeImmutable((string) $row['data_inicio']))->setTimezone(progressTimezone());
        } catch (Throwable) {
            continue;
        }
        if ($date < $start || $date >= $end || $date < $range['start'] || $date >= $range['end']) continue;
        if (!is_numeric($row['carga_kg'] ?? null) || !is_numeric($row['repeticoes'] ?? null)) continue;
        $sum += max(0.0, (float) $row['carga_kg']) * max(0, (int) $row['repeticoes']);
        $known++;
    }
    return $known > 0 ? $sum : null;
}

function progressSports(PDO $pdo, string $userId, array $filters): array
{
    $range = progressResolveRange($filters);
    $rows = sportHubActivityRowsQuery($pdo, $userId, $range['start'], $range['end']);
    $groups = [];
    foreach ($rows as $row) {
        $slug = (string) ($row['modalidade_slug'] ?? '');
        if ($slug === '') continue;
        if (!isset($groups[$slug])) {
            $family = (string) ($row['hub_bucket'] ?? 'other');
            $groups[$slug] = [
                'sport' => [
                    'id' => (string) ($row['idmodalidade'] ?? ''),
                    'slug' => $slug,
                    'name' => (string) ($row['modalidade_nome'] ?? $slug),
                    'family' => $family,
                    'route_capable' => stridebr_db_bool($row['permite_rota'] ?? false),
                    'derived_metric' => trim((string) ($row['metrica_derivada'] ?? '')) ?: 'nenhuma',
                    'behavior' => progressSportBehavior($slug, $family, (string) ($row['metrica_derivada'] ?? '')),
                ],
                'rows' => [],
            ];
        }
        $groups[$slug]['rows'][] = $row;
    }
    $total = count($rows);
    $data = [];
    foreach ($groups as $group) {
        $activityRows = $group['rows'];
        $data[] = [
            'sport' => $group['sport'],
            'activities_count' => count($activityRows),
            'duration_s' => progressKnownSum($activityRows, 'duration_raw_s'),
            'distance_m' => progressKnownSum($activityRows, 'distancia_raw_m'),
            'elevation_gain_m' => progressKnownSum($activityRows, 'elevacao_raw_m'),
            'activities_percentage' => $total > 0 ? count($activityRows) / $total * 100.0 : 0.0,
        ];
    }
    usort($data, static fn(array $a, array $b): int => $b['activities_count'] <=> $a['activities_count'] ?: strcmp($a['sport']['name'], $b['sport']['name']));
    return ['range' => progressRangePayload($range), 'data' => $data, 'units' => progressUnits()];
}

function progressCalendar(PDO $pdo, string $userId, array $filters): array
{
    $range = progressResolveRange($filters);
    $sport = progressResolveSport($pdo, $userId, $filters['sport'] ?? null);
    $rows = progressLoadActivities($pdo, $userId, $range, $sport)['current'];
    $byDay = [];
    foreach ($rows as $row) {
        try {
            $day = (new DateTimeImmutable((string) $row['data_inicio']))->setTimezone(progressTimezone())->format('Y-m-d');
        } catch (Throwable) {
            continue;
        }
        $byDay[$day][] = $row;
    }
    $data = [];
    $cursor = $range['start'];
    while ($cursor < $range['end']) {
        $day = $cursor->format('Y-m-d');
        $dayRows = $byDay[$day] ?? [];
        $data[] = [
            'date' => $day,
            'activities_count' => count($dayRows),
            'duration_s' => progressKnownSum($dayRows, 'duration_raw_s'),
            'distance_m' => progressKnownSum($dayRows, 'distancia_raw_m'),
            'training_load' => progressTrainingLoad($dayRows),
        ];
        $cursor = $cursor->modify('+1 day');
    }
    return ['range' => progressRangePayload($range), 'sport' => $sport, 'data' => $data, 'units' => progressUnits()];
}

function progressCardioAggregate(array $rows, string $behavior): array
{
    if ($rows === []) {
        return [
            'activities_count' => 0,
            'active_days' => 0,
            'duration_s' => 0,
            'distance_m' => 0,
            'elevation_gain_m' => 0,
            'longest_distance_m' => 0,
            'longest_duration_s' => 0,
            'average_pace_s_per_km' => null,
            'average_pace_s_per_100m' => null,
            'average_speed_kmh' => null,
            'paired_activities_count' => 0,
        ];
    }
    $days = [];
    $pairedDistance = 0.0;
    $pairedDuration = 0.0;
    $paired = 0;
    $longestDistance = null;
    $longestDuration = null;
    foreach ($rows as $row) {
        try {
            $days[(new DateTimeImmutable((string) $row['data_inicio']))->setTimezone(progressTimezone())->format('Y-m-d')] = true;
        } catch (Throwable) {
        }
        $distance = is_numeric($row['distancia_raw_m'] ?? null) ? (float) $row['distancia_raw_m'] : null;
        $duration = is_numeric($row['duration_raw_s'] ?? null) ? (float) $row['duration_raw_s'] : null;
        if ($distance !== null) $longestDistance = $longestDistance === null ? $distance : max($longestDistance, $distance);
        if ($duration !== null) $longestDuration = $longestDuration === null ? $duration : max($longestDuration, $duration);
        if ($distance !== null && $distance > 0 && $duration !== null && $duration > 0) {
            $pairedDistance += $distance;
            $pairedDuration += $duration;
            $paired++;
        }
    }
    return [
        'activities_count' => count($rows),
        'active_days' => count($days),
        'duration_s' => progressKnownSum($rows, 'duration_raw_s'),
        'distance_m' => progressKnownSum($rows, 'distancia_raw_m'),
        'elevation_gain_m' => progressKnownSum($rows, 'elevacao_raw_m'),
        'longest_distance_m' => $longestDistance,
        'longest_duration_s' => $longestDuration,
        'average_pace_s_per_km' => $behavior === 'pace' && $pairedDistance > 0 ? $pairedDuration / ($pairedDistance / 1000.0) : null,
        'average_pace_s_per_100m' => $behavior === 'pace_100m' && $pairedDistance > 0 ? $pairedDuration / ($pairedDistance / 100.0) : null,
        'average_speed_kmh' => $behavior === 'speed' && $pairedDuration > 0 ? ($pairedDistance / 1000.0) / ($pairedDuration / 3600.0) : null,
        'paired_activities_count' => $paired,
    ];
}

function progressCardio(PDO $pdo, string $userId, array $filters): array
{
    $range = progressResolveRange($filters);
    $sport = progressResolveSport($pdo, $userId, $filters['sport'] ?? null);
    if ($sport === null) throw new InvalidArgumentException('sport é obrigatório para progresso cardio.');
    $behavior = (string) ($sport['behavior'] ?? '');
    if ($behavior === '' || $sport['family'] === 'strength') throw new InvalidArgumentException('sport não possui analytics cardio suportado.');
    $activities = progressLoadActivities($pdo, $userId, $range, $sport);
    $current = progressCardioAggregate($activities['current'], $behavior);
    $previous = progressCardioAggregate($activities['previous'], $behavior);
    $metricKey = $behavior === 'pace' ? 'average_pace_s_per_km' : ($behavior === 'pace_100m' ? 'average_pace_s_per_100m' : ($behavior === 'speed' ? 'average_speed_kmh' : null));
    $trends = [
        'distance' => progressComparison($current['distance_m'], $previous['distance_m']),
        'duration' => progressComparison($current['duration_s'], $previous['duration_s']),
        'activities' => progressComparison($current['activities_count'], $previous['activities_count']),
        'performance' => $metricKey !== null ? progressComparison($current[$metricKey], $previous[$metricKey]) : null,
    ];
    $bucket = stridebr_lower(trim((string) ($filters['bucket'] ?? ($range['days'] <= 92 ? 'week' : 'month'))));
    $series = [];
    foreach (progressBucketDefinitions($range, $bucket) as $definition) {
        $rows = progressRowsInBucket($activities['current'], $definition['start'], $definition['end'], $range);
        $summary = progressCardioAggregate($rows, $behavior);
        $series[] = [
            'start' => $definition['start']->format('Y-m-d'),
            'end' => $definition['end']->modify('-1 day')->format('Y-m-d'),
            'distance_m' => $summary['distance_m'],
            'duration_s' => $summary['duration_s'],
            'average_pace_s_per_km' => $summary['average_pace_s_per_km'],
            'average_pace_s_per_100m' => $summary['average_pace_s_per_100m'],
            'average_speed_kmh' => $summary['average_speed_kmh'],
        ];
    }
    return [
        'range' => progressRangePayload($range),
        'sport' => $sport,
        'behavior' => $behavior,
        'current' => $current,
        'previous' => $previous,
        'trends' => $trends,
        'bucket' => $bucket,
        'timeseries' => $series,
        'units' => progressUnits(),
    ];
}

function progressStrength(PDO $pdo, string $userId, array $filters): array
{
    $range = progressResolveRange($filters);
    $sport = progressResolveSport($pdo, $userId, $filters['sport'] ?? null);
    if ($sport !== null && $sport['family'] !== 'strength') throw new InvalidArgumentException('sport precisa ser uma modalidade de força.');
    $activities = progressLoadActivities($pdo, $userId, $range, $sport)['current'];
    $activities = array_values(array_filter($activities, static fn(array $row): bool => (string) ($row['hub_bucket'] ?? '') === 'strength'));
    $sets = progressStrengthRows($pdo, $userId, $range);
    $activityIds = array_fill_keys(array_map(static fn(array $row): string => (string) $row['idregistro'], $activities), true);
    $sets = array_values(array_filter($sets, static fn(array $row): bool => isset($activityIds[(string) ($row['idregistro'] ?? '')])));
    $completedSets = array_values(array_filter($sets, static fn(array $row): bool => stridebr_db_bool($row['concluida'] ?? false)));
    $reps = 0;
    $knownReps = 0;
    $volume = 0.0;
    $knownVolume = 0;
    $exercises = [];
    $muscles = [];
    foreach ($completedSets as $row) {
        if (is_numeric($row['repeticoes'] ?? null)) {
            $reps += max(0, (int) $row['repeticoes']);
            $knownReps++;
        }
        if (is_numeric($row['repeticoes'] ?? null) && is_numeric($row['carga_kg'] ?? null)) {
            $volume += max(0, (int) $row['repeticoes']) * max(0.0, (float) $row['carga_kg']);
            $knownVolume++;
        }
        $key = trim((string) ($row['idexercicio'] ?? '')) ?: stridebr_lower((string) ($row['nome_exercicio'] ?? ''));
        if ($key !== '') {
            $exercises[$key] ??= ['exercise_id' => $row['idexercicio'] !== null ? (string) $row['idexercicio'] : null, 'name' => (string) ($row['nome_exercicio'] ?? ''), 'sets_count' => 0, 'reps' => 0, 'volume_load_kg' => 0.0, 'volume_known' => 0];
            $exercises[$key]['sets_count']++;
            if (is_numeric($row['repeticoes'] ?? null)) $exercises[$key]['reps'] += max(0, (int) $row['repeticoes']);
            if (is_numeric($row['repeticoes'] ?? null) && is_numeric($row['carga_kg'] ?? null)) {
                $exercises[$key]['volume_load_kg'] += max(0, (int) $row['repeticoes']) * max(0.0, (float) $row['carga_kg']);
                $exercises[$key]['volume_known']++;
            }
        }
        foreach (sportHubDecodeGroups($row['grupos_musculares_primarios'] ?? []) as $muscle) $muscles[$muscle] = ($muscles[$muscle] ?? 0) + 1;
    }
    $distributionExercises = [];
    foreach ($exercises as $exercise) {
        if ($exercise['volume_known'] === 0) $exercise['volume_load_kg'] = null;
        unset($exercise['volume_known']);
        $distributionExercises[] = $exercise;
    }
    usort($distributionExercises, static fn(array $a, array $b): int => $b['sets_count'] <=> $a['sets_count'] ?: strcmp($a['name'], $b['name']));
    arsort($muscles);
    $muscleDistribution = [];
    foreach ($muscles as $name => $count) $muscleDistribution[] = ['name' => (string) $name, 'completed_sets' => $count];
    $days = [];
    foreach ($activities as $row) {
        try {
            $days[(new DateTimeImmutable((string) $row['data_inicio']))->setTimezone(progressTimezone())->format('Y-m-d')] = true;
        } catch (Throwable) {
        }
    }
    return [
        'range' => progressRangePayload($range),
        'sport' => $sport,
        'sessions' => count($activities),
        'active_days' => count($days),
        'total_sets' => count($sets),
        'completed_sets' => count($completedSets),
        'total_reps' => $knownReps > 0 ? $reps : (count($activities) === 0 ? 0 : null),
        'volume_load_kg' => $knownVolume > 0 ? $volume : (count($activities) === 0 ? 0 : null),
        'exercises_count' => count($exercises),
        'duration_s' => progressKnownSum($activities, 'duration_raw_s'),
        'distribution_by_exercise' => $distributionExercises,
        'distribution_by_primary_muscle' => $muscleDistribution,
        'units' => progressUnits(),
    ];
}

function progressExerciseList(PDO $pdo, string $userId, array $filters): array
{
    $range = progressResolveRange($filters);
    $sport = progressResolveSport($pdo, $userId, $filters['sport'] ?? null);
    if ($sport !== null && $sport['family'] !== 'strength') {
        return ['range' => progressRangePayload($range), 'sport' => $sport, 'data' => [], 'meta' => ['page' => 1, 'limit' => max(1, min(100, (int) ($filters['limit'] ?? 30))), 'has_more' => false]];
    }
    $page = max(1, (int) ($filters['page'] ?? 1));
    $limit = max(1, min(100, (int) ($filters['limit'] ?? 30)));
    $query = trim((string) ($filters['q'] ?? ''));
    $whereSearch = '';
    $params = [
        ':user' => $userId,
        ':start' => $range['start']->format('Y-m-d H:i:sP'),
        ':end' => $range['end']->format('Y-m-d H:i:sP'),
    ];
    $sportClause = '';
    if ($sport !== null) {
        $sportClause = ' AND ra.idmodalidade = :sport_id';
        $params[':sport_id'] = $sport['id'];
    }
    if ($query !== '') {
        $whereSearch = ' AND lower(COALESCE(e.nome,sea.nome_exercicio)) LIKE :search';
        $params[':search'] = '%' . stridebr_lower($query) . '%';
    }
    $sql = "WITH scoped AS (
        SELECT sea.idexercicio,COALESCE(e.nome,sea.nome_exercicio) AS name,ra.idregistro,ra.data_inicio,sea.carga_kg,sea.repeticoes
        FROM series_exercicio_atividade sea
        JOIN registros_atividade ra ON ra.idregistro=sea.idregistro
        LEFT JOIN exercicios e ON e.idexercicio=sea.idexercicio
        WHERE ra.idusuario=:user AND ra.excluido_em IS NULL AND ra.status='concluido' AND COALESCE(ra.excluir_estatisticas,FALSE)=FALSE
          AND sea.concluida=TRUE AND sea.idexercicio IS NOT NULL
          AND ra.data_inicio>=:start AND ra.data_inicio<:end{$sportClause}{$whereSearch}
    ), latest AS (
        SELECT DISTINCT ON (idexercicio) idexercicio,idregistro,data_inicio
        FROM scoped ORDER BY idexercicio,data_inicio DESC,idregistro DESC
    ), grouped AS (
        SELECT s.idexercicio,MAX(s.name) AS name,COUNT(DISTINCT s.idregistro) AS sessions_count,COUNT(*) AS sets_count,MAX(s.data_inicio) AS last_performed_at
        FROM scoped s GROUP BY s.idexercicio
    ), recent AS (
        SELECT s.idexercicio,MAX(s.carga_kg) FILTER (WHERE s.idregistro=l.idregistro) AS recent_load_kg,
               SUM(s.carga_kg*s.repeticoes) FILTER (WHERE s.idregistro=l.idregistro AND s.carga_kg IS NOT NULL AND s.repeticoes IS NOT NULL) AS recent_volume_load_kg
        FROM scoped s JOIN latest l ON l.idexercicio=s.idexercicio GROUP BY s.idexercicio
    )
    SELECT g.*,r.recent_load_kg,r.recent_volume_load_kg
    FROM grouped g JOIN recent r ON r.idexercicio=g.idexercicio
    ORDER BY g.last_performed_at DESC,g.name
    LIMIT :page_limit OFFSET :page_offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) $stmt->bindValue($key, $value);
    $stmt->bindValue(':page_limit', $limit + 1, PDO::PARAM_INT);
    $stmt->bindValue(':page_offset', ($page - 1) * $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $hasMore = count($rows) > $limit;
    if ($hasMore) array_pop($rows);
    $bestLoads = treinoExerciciosMelhoresCargasKg($pdo, $userId, array_column($rows, 'idexercicio'));
    $data = [];
    foreach ($rows as $row) {
        $exerciseId = (string) $row['idexercicio'];
        $data[] = [
            'exercise_id' => $exerciseId,
            'name' => (string) $row['name'],
            'sessions_count' => (int) $row['sessions_count'],
            'sets_count' => (int) $row['sets_count'],
            'last_performed_at' => (new DateTimeImmutable((string) $row['last_performed_at']))->setTimezone(progressTimezone())->format(DateTimeInterface::ATOM),
            'best_load_kg' => $bestLoads[$exerciseId] ?? null,
            'recent_load_kg' => $row['recent_load_kg'] !== null ? (float) $row['recent_load_kg'] : null,
            'recent_volume_load_kg' => $row['recent_volume_load_kg'] !== null ? (float) $row['recent_volume_load_kg'] : null,
        ];
    }
    return ['range' => progressRangePayload($range), 'sport' => $sport, 'data' => $data, 'meta' => ['page' => $page, 'limit' => $limit, 'has_more' => $hasMore]];
}

function progressExerciseDetail(PDO $pdo, string $userId, string $exerciseId, array $filters): array
{
    $exerciseId = trim($exerciseId);
    if ($exerciseId === '') throw new InvalidArgumentException('Exercício não encontrado.');
    $sport = progressResolveSport($pdo, $userId, $filters['sport'] ?? null);
    if ($sport !== null && $sport['family'] !== 'strength') throw new InvalidArgumentException('Exercício não encontrado.');
    $range = progressResolveRange($filters);
    $sportClause = $sport !== null ? ' AND ra.idmodalidade=:sport_id' : '';
    $owner = $pdo->prepare("SELECT COALESCE(e.nome,MAX(sea.nome_exercicio)) AS name
        FROM series_exercicio_atividade sea
        JOIN registros_atividade ra ON ra.idregistro=sea.idregistro
        LEFT JOIN exercicios e ON e.idexercicio=sea.idexercicio
        WHERE ra.idusuario=:user AND ra.excluido_em IS NULL AND ra.status='concluido' AND COALESCE(ra.excluir_estatisticas,FALSE)=FALSE AND sea.idexercicio=:exercise{$sportClause}
        GROUP BY e.nome LIMIT 1");
    $ownerParams = [':user' => $userId, ':exercise' => $exerciseId];
    if ($sport !== null) $ownerParams[':sport_id'] = $sport['id'];
    $owner->execute($ownerParams);
    $name = $owner->fetchColumn();
    if ($name === false) throw new InvalidArgumentException('Exercício não encontrado.');
    $stmt = $pdo->prepare("SELECT ra.idregistro,ra.data_inicio,ra.titulo,sea.ordem_serie,sea.repeticoes,sea.carga_kg,sea.rir,sea.rpe,sea.concluida
        FROM series_exercicio_atividade sea
        JOIN registros_atividade ra ON ra.idregistro=sea.idregistro
        WHERE ra.idusuario=:user AND ra.excluido_em IS NULL AND ra.status='concluido' AND COALESCE(ra.excluir_estatisticas,FALSE)=FALSE AND sea.idexercicio=:exercise{$sportClause}
          AND ra.data_inicio>=:start AND ra.data_inicio<:end
        ORDER BY ra.data_inicio,ra.idregistro,sea.ordem_serie");
    $detailParams = [':user' => $userId, ':exercise' => $exerciseId, ':start' => $range['start']->format('Y-m-d H:i:sP'), ':end' => $range['end']->format('Y-m-d H:i:sP')];
    if ($sport !== null) $detailParams[':sport_id'] = $sport['id'];
    $stmt->execute($detailParams);
    $sessions = [];
    foreach ($stmt->fetchAll() as $row) {
        $id = (string) $row['idregistro'];
        if (!isset($sessions[$id])) {
            $sessions[$id] = [
                'activity_id' => $id,
                'date' => (new DateTimeImmutable((string) $row['data_inicio']))->setTimezone(progressTimezone())->format('Y-m-d'),
                'title' => $row['titulo'] !== null ? (string) $row['titulo'] : null,
                'sets_count' => 0,
                'completed_sets' => 0,
                'total_reps' => null,
                'best_load_kg' => null,
                'volume_load_kg' => null,
                'sets' => [],
                '_reps' => 0,
                '_reps_known' => 0,
                '_volume' => 0.0,
                '_volume_known' => 0,
            ];
        }
        $completed = stridebr_db_bool($row['concluida'] ?? false);
        $sessions[$id]['sets_count']++;
        if ($completed) $sessions[$id]['completed_sets']++;
        $reps = $row['repeticoes'] !== null ? (int) $row['repeticoes'] : null;
        $load = $row['carga_kg'] !== null ? (float) $row['carga_kg'] : null;
        if ($completed && $reps !== null) {
            $sessions[$id]['_reps'] += max(0, $reps);
            $sessions[$id]['_reps_known']++;
        }
        if ($completed && $load !== null) $sessions[$id]['best_load_kg'] = $sessions[$id]['best_load_kg'] === null ? $load : max($sessions[$id]['best_load_kg'], $load);
        if ($completed && $load !== null && $reps !== null) {
            $sessions[$id]['_volume'] += max(0.0, $load) * max(0, $reps);
            $sessions[$id]['_volume_known']++;
        }
        $sessions[$id]['sets'][] = [
            'number' => (int) $row['ordem_serie'],
            'repetitions' => $reps,
            'load_kg' => $load,
            'rir' => $row['rir'] !== null ? (float) $row['rir'] : null,
            'rpe' => $row['rpe'] !== null ? (float) $row['rpe'] : null,
            'completed' => $completed,
        ];
    }
    foreach ($sessions as &$session) {
        $session['total_reps'] = $session['_reps_known'] > 0 ? $session['_reps'] : null;
        $session['volume_load_kg'] = $session['_volume_known'] > 0 ? $session['_volume'] : null;
        unset($session['_reps'], $session['_reps_known'], $session['_volume'], $session['_volume_known']);
    }
    unset($session);
    $bestLoad = treinoExercicioMelhorCargaKg($pdo, $userId, $exerciseId);
    $sessionList = array_values($sessions);
    $recent = $sessionList !== [] ? $sessionList[array_key_last($sessionList)] : null;
    return [
        'range' => progressRangePayload($range),
        'exercise' => ['id' => $exerciseId, 'name' => (string) $name],
        'sessions_count' => count($sessionList),
        'sets_count' => array_sum(array_column($sessionList, 'sets_count')),
        'best_load_kg' => $bestLoad,
        'recent_load_kg' => $recent['best_load_kg'] ?? null,
        'recent_volume_load_kg' => $recent['volume_load_kg'] ?? null,
        'data' => $sessionList,
        'units' => progressUnits(),
    ];
}

function progressDashboard(PDO $pdo, string $userId, array $filters): array
{
    $range = progressResolveRange($filters);
    $sport = progressResolveSport($pdo, $userId, $filters['sport'] ?? null);
    $overview = progressOverview($pdo, $userId, $filters);
    $timeseriesFilters = $filters;
    $timeseriesFilters['metric'] = 'activities';
    $timeseriesFilters['bucket'] = $range['days'] <= 92 ? 'week' : 'month';
    return [
        'range' => progressRangePayload($range),
        'overview' => $overview,
        'timeseries' => progressTimeseries($pdo, $userId, $timeseriesFilters),
        'sports' => progressSports($pdo, $userId, $filters),
        'adherence' => ['range' => progressRangePayload($range), 'sport' => $sport] + $overview['adherence'],
        'cardio' => $sport !== null && $sport['family'] === 'cardio' ? progressCardio($pdo, $userId, $filters) : null,
        'strength' => $sport === null || $sport['family'] === 'strength' ? progressStrength($pdo, $userId, $filters) : null,
    ];
}
