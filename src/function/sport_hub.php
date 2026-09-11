<?php

declare(strict_types=1);

require_once __DIR__ . '/sport_catalog.php';

function sportHubFamilies(): array
{
    return sportCatalogFamilies();
}

function sportHubBucket(string $category, string $slug, string $family = ''): string
{
    return sportCatalogFamilyKey($family, $category, $slug);
}

function sportHubResolvePeriod(string $view = '12w', string $anchor = ''): array
{
    $timezone = new DateTimeZone('America/Sao_Paulo');
    $now = new DateTimeImmutable('now', $timezone);
    $currentMonth = $now->modify('first day of this month')->setTime(0, 0);
    $aliases = ['month' => '4w', '3m' => '12w', '12m' => '1y'];
    $view = $aliases[$view] ?? $view;
    $valid = ['4w', '12w', '6m', '1y'];
    if (!in_array($view, $valid, true)) $view = '12w';
    $anchorStart = $currentMonth;
    if (preg_match('/^\d{4}-\d{2}$/', $anchor) === 1) {
        try {
            $candidate = new DateTimeImmutable($anchor . '-01 00:00:00', $timezone);
            if ($candidate <= $currentMonth) $anchorStart = $candidate;
        } catch (Throwable) {
        }
    }
    $calendarEnd = $anchorStart->modify('+1 month');
    $currentEnd = $anchorStart == $currentMonth ? $now->modify('+1 second') : $calendarEnd;
    $currentStart = match ($view) {
        '4w' => $currentEnd->modify('-28 days'),
        '12w' => $currentEnd->modify('-84 days'),
        '6m' => $currentEnd->modify('-6 months'),
        default => $currentEnd->modify('-1 year'),
    };
    $spanSeconds = max(1, $currentEnd->getTimestamp() - $currentStart->getTimestamp());
    $previousEnd = $currentStart;
    $previousStart = $previousEnd->modify('-' . $spanSeconds . ' seconds');
    return [
        'view' => $view,
        'months' => $view === '6m' ? 6 : ($view === '1y' ? 12 : 0),
        'anchor' => $anchorStart->format('Y-m'),
        'anchor_start' => $anchorStart,
        'current_start' => $currentStart,
        'current_end' => $currentEnd,
        'previous_start' => $previousStart,
        'previous_end' => $previousEnd,
        'span_seconds' => $spanSeconds,
    ];
}

function sportHubActivityRows(PDO $pdo, string $userId, int $days = 370): array
{
    $days = max(30, min(36500, $days));
    $stmt = $pdo->prepare("SELECT ra.idregistro, ra.titulo, ra.data_inicio, ra.data_fim, ra.origem_provedor, ra.dispositivo_origem, ra.esforco_percebido,
        m.nome AS modalidade_nome, m.slug AS modalidade_slug, m.categoria, m.familia_hub,
        COALESCE(NULLIF(r.distancia_metros, 0), metric.distancia_m, 0) AS distancia_metros, COALESCE(NULLIF(r.ganho_elevacao_m, 0), metric.elevacao_m, 0) AS ganho_elevacao_m,
        COALESCE(ra.calorias_externas, ra.calorias_ativas_estimadas, 0) AS calorias_kcal,
        perf.fc_media_bpm, perf.fc_maxima_bpm, perf.cadencia_media, perf.potencia_media_w, perf.vento_m_s, perf.tempo_reacao_s,
        perf.tipo_sessao, perf.formato_jogo, perf.resultado, perf.adversario, perf.placar, perf.placar_favor, perf.placar_contra, perf.posicao, perf.rounds, perf.pontuacao, perf.rodadas,
        COALESCE(NULLIF(GREATEST(0, EXTRACT(EPOCH FROM (COALESCE(ra.data_fim, ra.data_inicio) - ra.data_inicio))), 0), metric.duracao_s, 0) AS duration_db_s
        FROM registros_atividade ra
        JOIN modalidades m ON m.idmodalidade = ra.idmodalidade
        LEFT JOIN rotas_atividade r ON r.idregistro = ra.idregistro
        LEFT JOIN LATERAL (
            SELECT
                SUM(va.valor_normalizado) FILTER (WHERE lower(c.slug) = 'distancia') AS distancia_m,
                SUM(va.valor_normalizado) FILTER (WHERE lower(c.slug) = 'duracao') AS duracao_s,
                SUM(va.valor_normalizado) FILTER (WHERE lower(c.slug) IN ('elevacao', 'desnivel')) AS elevacao_m
            FROM valores_atividade va
            JOIN campos_modelo c ON c.idcampo = va.idcampo
            WHERE va.idregistro = ra.idregistro
        ) metric ON TRUE
        LEFT JOIN LATERAL (
            SELECT
                AVG(COALESCE(va.valor_decimal, va.valor_inteiro::numeric)) FILTER (WHERE lower(c.slug) IN ('fc-media','fc_media','frequencia-cardiaca-media')) AS fc_media_bpm,
                MAX(COALESCE(va.valor_decimal, va.valor_inteiro::numeric)) FILTER (WHERE lower(c.slug) IN ('fc-maxima','fc_maxima','frequencia-cardiaca-maxima')) AS fc_maxima_bpm,
                AVG(COALESCE(va.valor_decimal, va.valor_inteiro::numeric)) FILTER (WHERE lower(c.slug) = 'cadencia') AS cadencia_media,
                AVG(COALESCE(va.valor_decimal, va.valor_inteiro::numeric)) FILTER (WHERE lower(c.slug) = 'potencia') AS potencia_media_w,
                AVG(COALESCE(va.valor_decimal, va.valor_inteiro::numeric)) FILTER (WHERE lower(c.slug) = 'vento') AS vento_m_s,
                AVG(COALESCE(va.valor_decimal, va.valor_inteiro::numeric)) FILTER (WHERE lower(c.slug) = 'tempo-reacao') AS tempo_reacao_s,
                MAX(COALESCE(o.valor, va.valor_texto)) FILTER (WHERE lower(c.slug) = 'tipo-sessao') AS tipo_sessao,
                MAX(COALESCE(o.valor, va.valor_texto)) FILTER (WHERE lower(c.slug) = 'formato-jogo') AS formato_jogo,
                MAX(COALESCE(o.valor, va.valor_texto)) FILTER (WHERE lower(c.slug) = 'resultado') AS resultado,
                MAX(va.valor_texto) FILTER (WHERE lower(c.slug) = 'adversario') AS adversario,
                MAX(va.valor_texto) FILTER (WHERE lower(c.slug) = 'placar') AS placar,
                MAX(COALESCE(va.valor_inteiro, va.valor_decimal::bigint)) FILTER (WHERE lower(c.slug) = 'placar-favor') AS placar_favor,
                MAX(COALESCE(va.valor_inteiro, va.valor_decimal::bigint)) FILTER (WHERE lower(c.slug) = 'placar-contra') AS placar_contra,
                MAX(va.valor_texto) FILTER (WHERE lower(c.slug) = 'posicao') AS posicao,
                MAX(COALESCE(va.valor_inteiro, va.valor_decimal::bigint)) FILTER (WHERE lower(c.slug) = 'rounds') AS rounds,
                MAX(COALESCE(va.valor_decimal, va.valor_inteiro::numeric)) FILTER (WHERE lower(c.slug) = 'pontuacao') AS pontuacao,
                MAX(COALESCE(va.valor_inteiro, va.valor_decimal::bigint)) FILTER (WHERE lower(c.slug) = 'rodadas') AS rodadas
            FROM valores_atividade va
            JOIN campos_modelo c ON c.idcampo = va.idcampo
            LEFT JOIN campos_modelo_opcoes o ON o.idcampo = va.idcampo AND o.idopcao = va.idopcao
            WHERE va.idregistro = ra.idregistro
        ) perf ON TRUE
        WHERE ra.idusuario = :usuario AND ra.excluido_em IS NULL AND ra.status = 'concluido'
          AND ra.data_inicio >= NOW() - (CAST(:dias AS integer) * INTERVAL '1 day')
        ORDER BY ra.data_inicio DESC");
    $stmt->execute([':usuario' => $userId, ':dias' => $days]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['hub_bucket'] = sportHubBucket((string) ($row['categoria'] ?? ''), (string) ($row['modalidade_slug'] ?? ''), (string) ($row['familia_hub'] ?? ''));
        $row['duration_s'] = is_numeric($row['duration_db_s'] ?? null) ? max(0.0, (float) $row['duration_db_s']) : 0.0;
    }
    unset($row);
    return $rows;
}

function sportHubAvailableSports(array $activities): array
{
    $sports = [];
    foreach ($activities as $row) {
        $slug = stridebr_lower(trim((string) ($row['modalidade_slug'] ?? '')));
        if ($slug === '') continue;
        $sports[$slug] ??= [
            'slug' => $slug,
            'name' => (string) ($row['modalidade_nome'] ?? $slug),
            'family' => (string) ($row['hub_bucket'] ?? 'other'),
            'count' => 0,
        ];
        $sports[$slug]['count']++;
    }
    uasort($sports, static fn(array $a, array $b): int => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);
    return array_values($sports);
}

/** Navigation is based on both a user's chosen modalities and recorded history. */
function sportHubNavigationSports(PDO $pdo, string $userId, array $activities): array
{
    $sports = [];
    foreach (sportHubAvailableSports($activities) as $sport) $sports[(string) $sport['slug']] = $sport + ['active' => false];
    try {
        $stmt = $pdo->prepare("SELECT m.slug, m.nome, m.categoria, m.familia_hub
            FROM modalidades_usuario mu JOIN modalidades m ON m.idmodalidade=mu.idmodalidade
            WHERE mu.idusuario=:usuario AND COALESCE(mu.ativo, FALSE)=TRUE");
        $stmt->execute([':usuario' => $userId]);
        foreach ($stmt->fetchAll() as $row) {
            $slug = stridebr_lower(trim((string) $row['slug']));
            if ($slug === '') continue;
            $sports[$slug] = ($sports[$slug] ?? [
                'slug' => $slug, 'name' => (string) $row['nome'],
                'family' => sportHubBucket((string) $row['categoria'], $slug, (string) $row['familia_hub']), 'count' => 0,
            ]) + ['active' => true];
            $sports[$slug]['active'] = true;
        }
    } catch (PDOException $error) {
        if (!in_array($error->getCode(), ['42P01', '42703'], true)) throw $error;
    }
    uasort($sports, static fn(array $a, array $b): int => [empty($b['active']) ? 0 : 1, (int) ($b['count'] ?? 0), $a['name']] <=> [empty($a['active']) ? 0 : 1, (int) ($a['count'] ?? 0), $b['name']]);
    return array_values($sports);
}

function sportHubTrainingLoad(array $activities): array
{
    $total = 0.0; $covered = 0; $eligible = 0;
    foreach ($activities as $row) {
        $duration = max(0.0, (float) ($row['duration_s'] ?? 0));
        if ($duration <= 0) continue;
        $eligible++;
        $rpe = $row['esforco_percebido'] ?? null;
        if (!is_numeric($rpe) || (float) $rpe < 1 || (float) $rpe > 10) continue;
        $covered++;
        $total += ($duration / 60) * (float) $rpe;
    }
    return ['value' => $total, 'covered' => $covered, 'eligible' => $eligible, 'coverage' => $eligible > 0 ? $covered / $eligible : null];
}

function sportHubProgressRenderer(string $slug, string $family): string
{
    $slug = stridebr_lower($slug);
    if (in_array($slug, ['corrida', 'corrida-em-esteira'], true)) return 'running';
    if (in_array($slug, ['ciclismo', 'ciclismo-indoor', 'mountain-bike'], true)) return 'cycling';
    if (in_array($slug, ['natacao', 'aguas-abertas'], true)) return 'swimming';
    if (in_array($slug, ['musculacao', 'powerlifting', 'levantamento-olimpico'], true)) return 'strength';
    if ($family === 'athletics') return 'athletics';
    if ($family === 'team') return 'team';
    if ($family === 'racket') return 'racket';
    if ($family === 'combat') return 'combat';
    return 'fallback';
}

function sportHubFilterSport(array $activities, string $sport = 'all'): array
{
    $sport = stridebr_lower(trim($sport));
    if ($sport === '' || $sport === 'all') return array_values($activities);
    return array_values(array_filter($activities, static fn(array $row): bool => stridebr_lower((string) ($row['modalidade_slug'] ?? '')) === $sport));
}

function sportHubActivitiesInWindow(array $activities, DateTimeImmutable $start, DateTimeImmutable $end): array
{
    return array_values(array_filter($activities, static function (array $row) use ($start, $end): bool {
        try { $date = new DateTimeImmutable((string) ($row['data_inicio'] ?? '')); } catch (Throwable) { return false; }
        return $date >= $start && $date < $end;
    }));
}

function sportHubSummaryMetrics(array $activities): array
{
    $summary = ['activities' => 0, 'active_days' => 0, 'duration_s' => 0.0, 'distance_m' => 0.0, 'elevation_m' => 0.0];
    $days = [];
    foreach ($activities as $row) {
        $summary['activities']++;
        $summary['duration_s'] += max(0.0, (float) ($row['duration_s'] ?? 0));
        $summary['distance_m'] += max(0.0, (float) ($row['distancia_metros'] ?? 0));
        $summary['elevation_m'] += max(0.0, (float) ($row['ganho_elevacao_m'] ?? 0));
        try { $days[(new DateTimeImmutable((string) ($row['data_inicio'] ?? '')))->format('Y-m-d')] = true; } catch (Throwable) {}
    }
    $summary['active_days'] = count($days);
    return $summary;
}

function sportHubWeeklySeries(array $activities, DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $span = max(1, $end->getTimestamp() - $start->getTimestamp());
    $weeks = max(1, (int) ceil($span / 604800));
    $series = [];
    for ($index = 0; $index < $weeks; $index++) {
        $bucketStart = $start->modify('+' . ($index * 7) . ' days');
        if ($bucketStart >= $end) break;
        $bucketEnd = $bucketStart->modify('+7 days');
        if ($bucketEnd > $end) $bucketEnd = $end;
        $series[$index] = [
            'index' => $index,
            'start' => $bucketStart,
            'end' => $bucketEnd,
            'activities' => 0,
            'duration_s' => 0.0,
            'distance_m' => 0.0,
            'elevation_m' => 0.0,
            'duration_known' => false,
            'distance_known' => false,
            'elevation_known' => false,
            'sports' => [],
        ];
    }
    foreach ($activities as $row) {
        try { $date = new DateTimeImmutable((string) ($row['data_inicio'] ?? '')); } catch (Throwable) { continue; }
        if ($date < $start || $date >= $end) continue;
        $index = (int) floor(($date->getTimestamp() - $start->getTimestamp()) / 604800);
        if (!isset($series[$index])) continue;
        $duration = max(0.0, (float) ($row['duration_s'] ?? 0));
        $distance = max(0.0, (float) ($row['distancia_metros'] ?? 0));
        $elevation = max(0.0, (float) ($row['ganho_elevacao_m'] ?? 0));
        $series[$index]['activities']++;
        $series[$index]['duration_s'] += $duration;
        $series[$index]['distance_m'] += $distance;
        $series[$index]['elevation_m'] += $elevation;
        if ($duration > 0) $series[$index]['duration_known'] = true;
        if ($distance > 0) $series[$index]['distance_known'] = true;
        if ($elevation > 0) $series[$index]['elevation_known'] = true;
        $slug = stridebr_lower((string) ($row['modalidade_slug'] ?? ''));
        if ($slug !== '') $series[$index]['sports'][$slug] = ($series[$index]['sports'][$slug] ?? 0) + 1;
    }
    return array_values($series);
}

function sportHubConsistencyDays(array $activities, DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $days = [];
    $cursor = $start->setTime(0, 0);
    $last = $end->setTime(0, 0);
    while ($cursor < $last) {
        $key = $cursor->format('Y-m-d');
        $days[$key] = ['date' => $cursor, 'count' => 0, 'sports' => []];
        $cursor = $cursor->modify('+1 day');
    }
    foreach ($activities as $row) {
        try { $date = (new DateTimeImmutable((string) ($row['data_inicio'] ?? '')))->setTimezone(new DateTimeZone('America/Sao_Paulo')); } catch (Throwable) { continue; }
        $key = $date->format('Y-m-d');
        if (!isset($days[$key])) continue;
        $days[$key]['count']++;
        $slug = stridebr_lower((string) ($row['modalidade_slug'] ?? ''));
        if ($slug !== '') $days[$key]['sports'][$slug] = ($days[$key]['sports'][$slug] ?? 0) + 1;
    }
    return array_values($days);
}

function sportHubModalitiesBreakdown(array $activities): array
{
    $items = [];
    foreach ($activities as $row) {
        $slug = stridebr_lower((string) ($row['modalidade_slug'] ?? ''));
        if ($slug === '') continue;
        $items[$slug] ??= ['slug' => $slug, 'name' => (string) ($row['modalidade_nome'] ?? $slug), 'activities' => 0, 'duration_s' => 0.0, 'distance_m' => 0.0];
        $items[$slug]['activities']++;
        $items[$slug]['duration_s'] += max(0.0, (float) ($row['duration_s'] ?? 0));
        $items[$slug]['distance_m'] += max(0.0, (float) ($row['distancia_metros'] ?? 0));
    }
    uasort($items, static fn(array $a, array $b): int => [$b['activities'], $b['duration_s'], $a['name']] <=> [$a['activities'], $a['duration_s'], $b['name']]);
    return array_values($items);
}

function sportHubOverview(array $activities, ?array $periodWindow = null): array
{
    $periodWindow ??= sportHubResolvePeriod();
    $currentStart = $periodWindow['current_start'];
    $currentEnd = $periodWindow['current_end'];
    $previousStart = $periodWindow['previous_start'];
    $result = [];
    foreach (array_merge(['all'], array_keys(sportHubFamilies())) as $bucket) {
        $result[$bucket] = [
            'current' => ['activities' => 0, 'duration_s' => 0.0, 'distance_m' => 0.0, 'elevation_m' => 0.0, 'calories_kcal' => 0.0],
            'previous' => ['activities' => 0, 'duration_s' => 0.0, 'distance_m' => 0.0, 'elevation_m' => 0.0, 'calories_kcal' => 0.0],
        ];
    }
    foreach ($activities as $row) {
        $date = new DateTimeImmutable((string) $row['data_inicio']);
        $period = $date >= $currentStart && $date < $currentEnd ? 'current' : ($date >= $previousStart && $date < $currentStart ? 'previous' : null);
        if ($period === null) continue;
        $targets = ['all'];
        $bucket = (string) ($row['hub_bucket'] ?? 'other');
        if (isset($result[$bucket])) $targets[] = $bucket;
        foreach ($targets as $target) {
            $result[$target][$period]['activities']++;
            $result[$target][$period]['duration_s'] += (float) ($row['duration_s'] ?? 0);
            $result[$target][$period]['distance_m'] += (float) ($row['distancia_metros'] ?? 0);
            $result[$target][$period]['elevation_m'] += (float) ($row['ganho_elevacao_m'] ?? 0);
            $result[$target][$period]['calories_kcal'] += (float) ($row['calorias_kcal'] ?? 0);
        }
    }
    return $result;
}

function sportHubActiveFamilies(array $activities): array
{
    $counts = [];
    foreach ($activities as $row) {
        $bucket = (string) ($row['hub_bucket'] ?? 'other');
        $counts[$bucket] = ($counts[$bucket] ?? 0) + 1;
    }
    $active = [];
    foreach (sportHubFamilies() as $key => $meta) if (($counts[$key] ?? 0) > 0) $active[$key] = $meta;
    return $active;
}

function sportHubStrengthSets(PDO $pdo, string $userId): array
{
    $rows = [];
    try {
        $stmt = $pdo->prepare("SELECT sa.idserie, sa.idregistro, sa.idexercicio, sa.nome_exercicio, sa.ordem_serie, sa.carga_kg, sa.repeticoes, sa.concluida,
            ra.data_inicio, e.grupos_musculares_primarios, e.grupos_musculares_secundarios
            FROM series_exercicio_atividade sa
            JOIN registros_atividade ra ON ra.idregistro = sa.idregistro
            LEFT JOIN exercicios e ON e.idexercicio = sa.idexercicio
            WHERE ra.idusuario = :usuario AND ra.excluido_em IS NULL AND ra.status = 'concluido'
              AND ra.data_inicio >= NOW() - INTERVAL '48 months'
            ORDER BY ra.data_inicio DESC, sa.ordem_exercicio, sa.ordem_serie");
        $stmt->execute([':usuario' => $userId]);
        foreach ($stmt->fetchAll() as $row) {
            $row['source'] = 'normalized';
            $rows[] = $row;
        }
    } catch (PDOException $e) {
        if ($e->getCode() !== '42P01' && $e->getCode() !== '42703') throw $e;
    }

    $legacy = $pdo->prepare("SELECT st.idserie, ra.idregistro, se.idexercicio, se.nome_snapshot AS nome_exercicio, st.numero AS ordem_serie,
        st.carga_realizada, st.repeticoes_realizadas, st.concluida, ra.data_inicio,
        e.grupos_musculares_primarios, e.grupos_musculares_secundarios
        FROM sessoes_treino_series st
        JOIN sessoes_treino_exercicios se ON se.idsessao_exercicio = st.idsessao_exercicio
        JOIN sessoes_treino s ON s.idsessao = se.idsessao
        JOIN registros_atividade ra ON ra.idregistro = s.idregistro_atividade
        LEFT JOIN exercicios e ON e.idexercicio = se.idexercicio
        WHERE s.idusuario = :usuario AND s.status = 'concluido' AND st.concluida = TRUE
          AND ra.excluido_em IS NULL AND ra.data_inicio >= NOW() - INTERVAL '48 months'
          AND NOT EXISTS (SELECT 1 FROM series_exercicio_atividade sa WHERE sa.idregistro = ra.idregistro)
        ORDER BY ra.data_inicio DESC, se.ordem, st.numero");
    try {
        $legacy->execute([':usuario' => $userId]);
        foreach ($legacy->fetchAll() as $row) {
            $loadRaw = str_replace(',', '.', trim((string) ($row['carga_realizada'] ?? '')));
            $repsRaw = trim((string) ($row['repeticoes_realizadas'] ?? ''));
            $row['carga_kg'] = preg_match('/^\d+(?:\.\d+)?$/', $loadRaw) === 1 ? (float) $loadRaw : null;
            $row['repeticoes'] = preg_match('/^\d+$/', $repsRaw) === 1 ? (int) $repsRaw : null;
            $row['source'] = 'legacy';
            $rows[] = $row;
        }
    } catch (PDOException $e) {
        if ($e->getCode() !== '42P01' && $e->getCode() !== '42703') throw $e;
    }
    return $rows;
}

function sportHubDecodeGroups(mixed $raw): array
{
    if (is_array($raw)) return array_values(array_filter(array_map('strval', $raw)));
    $decoded = json_decode((string) ($raw ?? '[]'), true);
    return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : [];
}

function sportHubStrengthDashboard(PDO $pdo, string $userId, array $activities, ?array $periodWindow = null, string $sportSlug = ''): array
{
    $sets = sportHubStrengthSets($pdo, $userId);
    $now = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
    $periodWindow ??= sportHubResolvePeriod();
    $currentStart = $periodWindow['current_start'];
    $currentEnd = $periodWindow['current_end'];
    $previousStart = $periodWindow['previous_start'];
    $strengthActivities = array_values(array_filter($activities, static fn(array $row): bool => ($row['hub_bucket'] ?? '') === 'strength' && ($sportSlug === '' || stridebr_lower((string) ($row['modalidade_slug'] ?? '')) === $sportSlug)));
    $allowedActivityIds = array_fill_keys(array_map(static fn(array $row): string => (string) ($row['idregistro'] ?? ''), $strengthActivities), true);
    $summary = [
        'current' => ['workouts' => 0, 'duration_s' => 0.0, 'sets' => 0, 'reps' => 0, 'volume_kg' => 0.0, 'prs' => 0],
        'previous' => ['workouts' => 0, 'duration_s' => 0.0, 'sets' => 0, 'reps' => 0, 'volume_kg' => 0.0, 'prs' => 0],
    ];
    $activityIds = ['current' => [], 'previous' => []];
    foreach ($strengthActivities as $row) {
        $date = new DateTimeImmutable((string) $row['data_inicio']);
        $period = $date >= $currentStart && $date < $currentEnd ? 'current' : ($date >= $previousStart && $date < $currentStart ? 'previous' : null);
        if ($period === null) continue;
        $activityIds[$period][(string) $row['idregistro']] = true;
        $summary[$period]['duration_s'] += (float) ($row['duration_s'] ?? 0);
    }
    foreach (['current', 'previous'] as $period) $summary[$period]['workouts'] = count($activityIds[$period]);

    $exerciseGroups = [];
    $muscles = [];
    foreach ($sets as $row) {
        if ($sportSlug !== '' && !isset($allowedActivityIds[(string) ($row['idregistro'] ?? '')])) continue;
        if (!stridebr_db_bool($row['concluida'] ?? true)) continue;
        $date = new DateTimeImmutable((string) $row['data_inicio']);
        if ($date >= $currentEnd) continue;
        $period = $date >= $currentStart && $date < $currentEnd ? 'current' : ($date >= $previousStart && $date < $currentStart ? 'previous' : null);
        $load = is_numeric($row['carga_kg'] ?? null) ? max(0.0, (float) $row['carga_kg']) : null;
        $reps = is_numeric($row['repeticoes'] ?? null) ? max(0, (int) $row['repeticoes']) : null;
        if ($period !== null) {
            $summary[$period]['sets']++;
            if ($reps !== null) $summary[$period]['reps'] += $reps;
            if ($load !== null && $reps !== null) $summary[$period]['volume_kg'] += $load * $reps;
        }
        $key = trim((string) ($row['idexercicio'] ?? '')) ?: stridebr_lower(trim((string) ($row['nome_exercicio'] ?? 'Exercício')));
        $exerciseGroups[$key] ??= ['nome' => (string) ($row['nome_exercicio'] ?? 'Exercício'), 'sessions' => [], 'best_load' => null, 'best_e1rm' => null, 'sets' => 0, 'volume' => 0.0];
        $day = $date->format('Y-m-d');
        $exerciseGroups[$key]['sessions'][$day] ??= ['date' => $day, 'max_load' => null, 'best_e1rm' => null, 'volume' => 0.0, 'sets' => 0, 'reps' => 0];
        $exerciseGroups[$key]['sets']++;
        $exerciseGroups[$key]['sessions'][$day]['sets']++;
        if ($reps !== null) $exerciseGroups[$key]['sessions'][$day]['reps'] += $reps;
        if ($load !== null) {
            $exerciseGroups[$key]['best_load'] = $exerciseGroups[$key]['best_load'] === null ? $load : max($exerciseGroups[$key]['best_load'], $load);
            $exerciseGroups[$key]['sessions'][$day]['max_load'] = $exerciseGroups[$key]['sessions'][$day]['max_load'] === null ? $load : max($exerciseGroups[$key]['sessions'][$day]['max_load'], $load);
            if ($reps !== null && $reps > 0) {
                $volume = $load * $reps;
                $exerciseGroups[$key]['volume'] += $volume;
                $exerciseGroups[$key]['sessions'][$day]['volume'] += $volume;
                if ($reps <= 12) {
                    $e1rm = $load * (1 + ($reps / 30));
                    $exerciseGroups[$key]['best_e1rm'] = $exerciseGroups[$key]['best_e1rm'] === null ? $e1rm : max($exerciseGroups[$key]['best_e1rm'], $e1rm);
                    $exerciseGroups[$key]['sessions'][$day]['best_e1rm'] = $exerciseGroups[$key]['sessions'][$day]['best_e1rm'] === null ? $e1rm : max($exerciseGroups[$key]['sessions'][$day]['best_e1rm'], $e1rm);
                }
            }
        }
        if ($period === 'current') {
            foreach (sportHubDecodeGroups($row['grupos_musculares_primarios'] ?? []) as $group) $muscles[$group] = ($muscles[$group] ?? 0) + 1;
            foreach (sportHubDecodeGroups($row['grupos_musculares_secundarios'] ?? []) as $group) $muscles[$group] = ($muscles[$group] ?? 0) + .5;
        }
    }

    $progress = [];
    $recentCutoff = $currentEnd->modify('-42 days')->format('Y-m-d');
    $priorCutoff = $currentEnd->modify('-84 days')->format('Y-m-d');
    foreach ($exerciseGroups as $key => $data) {
        $sessions = array_values($data['sessions']);
        usort($sessions, static fn(array $a, array $b): int => strcmp($b['date'], $a['date']));
        $latest = $sessions[0] ?? null;
        $previous = $sessions[1] ?? null;
        $recentBestLoad = $priorBestLoad = $recentBestE1rm = $priorBestE1rm = null;
        $recentVolume = $priorVolume = 0.0;
        foreach ($sessions as $session) {
            $day = (string) ($session['date'] ?? '');
            if ($day >= $recentCutoff) {
                if (is_numeric($session['max_load'] ?? null)) $recentBestLoad = $recentBestLoad === null ? (float) $session['max_load'] : max($recentBestLoad, (float) $session['max_load']);
                if (is_numeric($session['best_e1rm'] ?? null)) $recentBestE1rm = $recentBestE1rm === null ? (float) $session['best_e1rm'] : max($recentBestE1rm, (float) $session['best_e1rm']);
                $recentVolume += (float) ($session['volume'] ?? 0);
            } elseif ($day >= $priorCutoff) {
                if (is_numeric($session['max_load'] ?? null)) $priorBestLoad = $priorBestLoad === null ? (float) $session['max_load'] : max($priorBestLoad, (float) $session['max_load']);
                if (is_numeric($session['best_e1rm'] ?? null)) $priorBestE1rm = $priorBestE1rm === null ? (float) $session['best_e1rm'] : max($priorBestE1rm, (float) $session['best_e1rm']);
                $priorVolume += (float) ($session['volume'] ?? 0);
            }
        }
        $ascending = array_reverse($sessions);
        $runningBestLoad = null;
        $runningBestE1rm = null;
        $currentPrs = 0;
        $previousPrs = 0;
        foreach ($ascending as $session) {
            $day = (string) ($session['date'] ?? '');
            $isPr = false;
            if (is_numeric($session['max_load'] ?? null) && ($runningBestLoad === null || (float) $session['max_load'] > $runningBestLoad + .0001)) {
                $runningBestLoad = (float) $session['max_load'];
                $isPr = true;
            }
            if (is_numeric($session['best_e1rm'] ?? null) && ($runningBestE1rm === null || (float) $session['best_e1rm'] > $runningBestE1rm + .0001)) {
                $runningBestE1rm = (float) $session['best_e1rm'];
                $isPr = true;
            }
            if (!$isPr) continue;
            if ($day >= $currentStart->format('Y-m-d') && $day < $currentEnd->format('Y-m-d')) $currentPrs++;
            elseif ($day >= $previousStart->format('Y-m-d') && $day < $currentStart->format('Y-m-d')) $previousPrs++;
        }
        $summary['current']['prs'] += $currentPrs;
        $summary['previous']['prs'] += $previousPrs;
        $percent = static function (?float $current, ?float $previous): ?float {
            return $current !== null && $previous !== null && $previous > 0 ? (($current - $previous) / $previous) * 100.0 : null;
        };
        $progress[] = [
            'key' => (string) $key,
            'nome' => $data['nome'],
            'best_load' => $data['best_load'],
            'best_e1rm' => $data['best_e1rm'],
            'sets' => $data['sets'],
            'volume' => $data['volume'],
            'latest_load' => $latest['max_load'] ?? null,
            'previous_load' => $previous['max_load'] ?? null,
            'latest_is_pr' => $latest !== null && is_numeric($latest['max_load'] ?? null) && $data['best_load'] !== null && abs((float) $latest['max_load'] - (float) $data['best_load']) < .0001,
            'recent_best_load' => $recentBestLoad,
            'prior_best_load' => $priorBestLoad,
            'load_trend_pct' => $percent($recentBestLoad, $priorBestLoad),
            'e1rm_trend_pct' => $percent($recentBestE1rm, $priorBestE1rm),
            'volume_trend_pct' => $priorVolume > 0 ? (($recentVolume - $priorVolume) / $priorVolume) * 100.0 : null,
            'recent_volume' => $recentVolume,
            'prior_volume' => $priorVolume,
            'history' => array_reverse(array_slice($sessions, 0, 8)),
        ];
    }
    usort($progress, static function (array $a, array $b): int {
        $aDate = end($a['history'])['date'] ?? '';
        $bDate = end($b['history'])['date'] ?? '';
        return strcmp($bDate, $aDate);
    });
    arsort($muscles);

    $calendar = [];
    foreach ($strengthActivities as $row) {
        $date = (new DateTimeImmutable((string) $row['data_inicio']))->setTimezone(new DateTimeZone('America/Sao_Paulo'));
        if ($date < $currentStart || $date >= $currentEnd) continue;
        $day = $date->format('Y-m-d');
        $calendar[$day] = ($calendar[$day] ?? 0) + 1;
    }
    return ['summary' => $summary, 'exercises' => array_slice($progress, 0, 24), 'muscles' => $muscles, 'calendar' => $calendar];
}

function sportHubCardioDiscipline(string $slug): string
{
    $slug = stridebr_lower(trim($slug));
    if ($slug === '') return 'other';
    if (preg_match('/corrida|jogging|running/', $slug) === 1) return 'run';
    if (preg_match('/caminhada|nordic-walking|rucking|marcha/', $slug) === 1) return 'walk';
    if (preg_match('/ciclismo|bike|bmx|spinning|handcycle|ciclo/', $slug) === 1) return 'cycle';
    if (preg_match('/natacao|natação|aguas-abertas|águas-abertas/', $slug) === 1) return 'swim';
    if (preg_match('/remo|canoagem|caiaque/', $slug) === 1) return 'row';
    if (preg_match('/triatlo|duatlo|aquatlo|swimrun|multiesporte/', $slug) === 1) return 'multi';
    if (preg_match('/eliptico|elíptico|escada|stair|step|corda/', $slug) === 1) return 'machine';
    if (preg_match('/patinacao|patinação|roller|skating/', $slug) === 1) return 'skating';
    return 'other';
}

function sportHubCardioDisciplines(): array
{
    return [
        'all' => ['label' => stridebr_t('progress.cardio.all'), 'metric' => 'distance'],
        'run' => ['label' => stridebr_t('progress.cardio.run'), 'metric' => 'pace_km'],
        'walk' => ['label' => stridebr_t('progress.cardio.walk'), 'metric' => 'pace_km'],
        'cycle' => ['label' => stridebr_t('progress.cardio.cycle'), 'metric' => 'speed'],
        'swim' => ['label' => stridebr_t('progress.cardio.swim'), 'metric' => 'pace_100m'],
        'row' => ['label' => stridebr_t('progress.cardio.row'), 'metric' => 'speed'],
        'multi' => ['label' => stridebr_t('progress.cardio.multi'), 'metric' => 'distance'],
        'machine' => ['label' => stridebr_t('progress.cardio.machine'), 'metric' => 'time'],
        'skating' => ['label' => stridebr_t('progress.cardio.skating'), 'metric' => 'speed'],
        'other' => ['label' => stridebr_t('progress.cardio.other'), 'metric' => 'time'],
    ];
}

function sportHubCardioDashboard(array $activities, ?array $periodWindow = null): array
{
    $definitions = sportHubCardioDisciplines();
    $periodWindow ??= sportHubResolvePeriod();
    $currentStart = $periodWindow['current_start'];
    $currentEnd = $periodWindow['current_end'];
    $previousStart = $periodWindow['previous_start'];
    $weekAnchor = $currentEnd->modify('-1 second');
    $weekStart = $weekAnchor->modify('monday this week')->setTime(0, 0)->modify('-7 weeks');
    $summary = [];
    $weekly = [];
    $recent = [];
    $active = [];
    foreach ($definitions as $key => $definition) {
        $base = [
            'activities' => 0,
            'duration_s' => 0.0,
            'distance_m' => 0.0,
            'elevation_m' => 0.0,
            'calories_kcal' => 0.0,
            'best_pace_s' => null,
            'best_speed_kmh' => null,
            'longest_distance_m' => 0.0,
            'hr_weighted' => 0.0,
            'hr_weight_s' => 0.0,
            'max_hr_bpm' => null,
            'cadence_weighted' => 0.0,
            'cadence_weight_s' => 0.0,
            'power_weighted' => 0.0,
            'power_weight_s' => 0.0,
        ];
        $summary[$key] = ['current' => $base, 'previous' => $base];
        $weekly[$key] = [];
        $recent[$key] = [];
    }
    for ($i = 0; $i < 8; $i++) {
        $start = $weekStart->modify('+' . $i . ' weeks');
        foreach ($definitions as $key => $_) {
            $weekly[$key][$start->format('Y-m-d')] = ['distance_m' => 0.0, 'duration_s' => 0.0, 'activities' => 0, 'calories_kcal' => 0.0];
        }
    }

    foreach ($activities as $row) {
        if (($row['hub_bucket'] ?? '') !== 'cardio') continue;
        $discipline = sportHubCardioDiscipline((string) ($row['modalidade_slug'] ?? ''));
        $active[$discipline] = ($active[$discipline] ?? 0) + 1;
        $date = new DateTimeImmutable((string) $row['data_inicio']);
        if ($date >= $currentEnd) continue;
        $duration = max(0.0, (float) ($row['duration_s'] ?? 0));
        $distance = max(0.0, (float) ($row['distancia_metros'] ?? 0));
        $elevation = max(0.0, (float) ($row['ganho_elevacao_m'] ?? 0));
        $calories = max(0.0, (float) ($row['calorias_kcal'] ?? 0));
        $avgHr = is_numeric($row['fc_media_bpm'] ?? null) ? max(0.0, (float) $row['fc_media_bpm']) : null;
        $maxHr = is_numeric($row['fc_maxima_bpm'] ?? null) ? max(0.0, (float) $row['fc_maxima_bpm']) : null;
        $cadence = is_numeric($row['cadencia_media'] ?? null) ? max(0.0, (float) $row['cadencia_media']) : null;
        $power = is_numeric($row['potencia_media_w'] ?? null) ? max(0.0, (float) $row['potencia_media_w']) : null;
        $paceUnit = $discipline === 'swim' ? 100.0 : 1000.0;
        $pace = $duration > 0 && $distance > 0 ? $duration / ($distance / $paceUnit) : null;
        $speed = $duration > 0 && $distance > 0 ? ($distance / 1000.0) / ($duration / 3600.0) : null;
        $period = $date >= $currentStart && $date < $currentEnd ? 'current' : ($date >= $previousStart && $date < $currentStart ? 'previous' : null);
        $targets = ['all', $discipline];
        foreach ($targets as $target) {
            if ($period !== null) {
                $data = &$summary[$target][$period];
                $data['activities']++;
                $data['duration_s'] += $duration;
                $data['distance_m'] += $distance;
                $data['elevation_m'] += $elevation;
                $data['calories_kcal'] += $calories;
                $data['longest_distance_m'] = max((float) $data['longest_distance_m'], $distance);
                if ($duration > 0 && $distance > 0) {
                    $minimumDistance = $discipline === 'swim' ? 100.0 : ($discipline === 'cycle' || $discipline === 'skating' ? 1000.0 : 500.0);
                    if ($distance >= $minimumDistance) {
                        if ($data['best_pace_s'] === null || $pace < $data['best_pace_s']) $data['best_pace_s'] = $pace;
                        if ($data['best_speed_kmh'] === null || $speed > $data['best_speed_kmh']) $data['best_speed_kmh'] = $speed;
                    }
                }
                $weight = max(60.0, $duration);
                if ($avgHr !== null && $avgHr > 0) {
                    $data['hr_weighted'] += $avgHr * $weight;
                    $data['hr_weight_s'] += $weight;
                }
                if ($maxHr !== null && $maxHr > 0) $data['max_hr_bpm'] = $data['max_hr_bpm'] === null ? $maxHr : max((float) $data['max_hr_bpm'], $maxHr);
                if ($cadence !== null && $cadence > 0) {
                    $data['cadence_weighted'] += $cadence * $weight;
                    $data['cadence_weight_s'] += $weight;
                }
                if ($power !== null && $power > 0) {
                    $data['power_weighted'] += $power * $weight;
                    $data['power_weight_s'] += $weight;
                }
                unset($data);
            }
            if ($date >= $weekStart) {
                $weekOffset = (int) floor(($date->getTimestamp() - $weekStart->getTimestamp()) / 604800);
                if ($weekOffset >= 0 && $weekOffset < 8) {
                    $key = $weekStart->modify('+' . $weekOffset . ' weeks')->format('Y-m-d');
                    $weekly[$target][$key]['activities']++;
                    $weekly[$target][$key]['duration_s'] += $duration;
                    $weekly[$target][$key]['distance_m'] += $distance;
                    $weekly[$target][$key]['calories_kcal'] += $calories;
                }
            }
            if (count($recent[$target]) < 16) {
                $recent[$target][] = [
                    'idregistro' => (string) ($row['idregistro'] ?? ''),
                    'date' => (string) $row['data_inicio'],
                    'title' => stridebr_present_activity_title((string) ($row['titulo'] ?? $row['modalidade_nome'] ?? stridebr_t('activity.activity')), (string) ($row['modalidade_slug'] ?? ''), (string) ($row['modalidade_nome'] ?? '')),
                    'sport' => stridebr_sport_name((string) ($row['modalidade_slug'] ?? ''), (string) ($row['modalidade_nome'] ?? stridebr_t('activity.activity'))),
                    'distance_m' => $distance,
                    'duration_s' => $duration,
                    'pace_s' => $pace,
                    'speed_kmh' => $speed,
                    'elevation_m' => $elevation,
                    'avg_hr_bpm' => $avgHr,
                    'max_hr_bpm' => $maxHr,
                    'cadence' => $cadence,
                    'power_w' => $power,
                    'calories_kcal' => $calories,
                    'provider' => trim((string) ($row['origem_provedor'] ?? '')),
                ];
            }
        }
    }

    foreach ($summary as &$periods) {
        foreach ($periods as &$data) {
            $data['avg_speed_kmh'] = $data['duration_s'] > 0 && $data['distance_m'] > 0 ? ($data['distance_m'] / 1000.0) / ($data['duration_s'] / 3600.0) : null;
            $data['avg_pace_km_s'] = $data['distance_m'] > 0 ? $data['duration_s'] / ($data['distance_m'] / 1000.0) : null;
            $data['avg_pace_100m_s'] = $data['distance_m'] > 0 ? $data['duration_s'] / ($data['distance_m'] / 100.0) : null;
            $data['avg_hr_bpm'] = $data['hr_weight_s'] > 0 ? $data['hr_weighted'] / $data['hr_weight_s'] : null;
            $data['avg_cadence'] = $data['cadence_weight_s'] > 0 ? $data['cadence_weighted'] / $data['cadence_weight_s'] : null;
            $data['avg_power_w'] = $data['power_weight_s'] > 0 ? $data['power_weighted'] / $data['power_weight_s'] : null;
            unset($data['hr_weighted'], $data['hr_weight_s'], $data['cadence_weighted'], $data['cadence_weight_s'], $data['power_weighted'], $data['power_weight_s']);
        }
        unset($data);
    }
    unset($periods);

    $trends = [];
    foreach ($definitions as $key => $definition) {
        $weeks = array_values($weekly[$key]);
        $older = array_slice($weeks, 0, 4);
        $newer = array_slice($weeks, 4, 4);
        $sum = static function (array $items, string $metric): float {
            $total = 0.0;
            foreach ($items as $item) $total += (float) ($item[$metric] ?? 0);
            return $total;
        };
        $oldDistance = $sum($older, 'distance_m');
        $newDistance = $sum($newer, 'distance_m');
        $oldDuration = $sum($older, 'duration_s');
        $newDuration = $sum($newer, 'duration_s');
        $oldActivities = $sum($older, 'activities');
        $newActivities = $sum($newer, 'activities');
        $oldPace = $oldDistance > 0 ? $oldDuration / ($oldDistance / ($key === 'swim' ? 100.0 : 1000.0)) : null;
        $newPace = $newDistance > 0 ? $newDuration / ($newDistance / ($key === 'swim' ? 100.0 : 1000.0)) : null;
        $oldSpeed = $oldDuration > 0 ? ($oldDistance / 1000.0) / ($oldDuration / 3600.0) : null;
        $newSpeed = $newDuration > 0 ? ($newDistance / 1000.0) / ($newDuration / 3600.0) : null;
        $pct = static function (?float $current, ?float $previous, bool $lowerBetter = false): ?float {
            if ($current === null || $previous === null || $previous <= 0) return null;
            $delta = (($current - $previous) / $previous) * 100.0;
            return $lowerBetter ? -$delta : $delta;
        };
        $trends[$key] = [
            'distance_pct' => $pct($newDistance, $oldDistance),
            'duration_pct' => $pct($newDuration, $oldDuration),
            'activities_pct' => $pct($newActivities, $oldActivities),
            'pace_pct' => $pct($newPace, $oldPace, true),
            'speed_pct' => $pct($newSpeed, $oldSpeed),
            'current_distance_m' => $newDistance,
            'previous_distance_m' => $oldDistance,
            'current_duration_s' => $newDuration,
            'previous_duration_s' => $oldDuration,
        ];
    }

    $activeDefinitions = ['all' => $definitions['all']];
    foreach ($definitions as $key => $definition) if ($key !== 'all' && ($active[$key] ?? 0) > 0) $activeDefinitions[$key] = $definition;
    return ['summary' => $summary, 'weekly' => $weekly, 'recent' => $recent, 'trends' => $trends, 'active' => $activeDefinitions];
}

function sportHubAthleticsDashboard(PDO $pdo, string $userId, array $activities, ?array $periodWindow = null): array
{
    $rows = [];
    try {
        $stmt = $pdo->prepare("SELECT ra.idregistro, ra.data_inicio, m.nome AS modalidade_nome, m.slug AS modalidade_slug,
            ua.idunidade_atividade, ua.ordem,
            MAX(va.valor_decimal) FILTER (WHERE lower(c.slug) = 'marca') AS marca_m,
            MAX(va.valor_decimal) FILTER (WHERE lower(c.slug) = 'vento') AS vento_m_s,
            COALESCE(BOOL_OR(COALESCE(va.valor_booleano, FALSE)) FILTER (WHERE lower(c.slug) = 'tentativa-nula'), FALSE) AS tentativa_nula
            FROM registros_atividade ra
            JOIN modalidades m ON m.idmodalidade = ra.idmodalidade
            JOIN unidades_atividade ua ON ua.idregistro = ra.idregistro
            LEFT JOIN valores_atividade va ON va.idunidade_atividade = ua.idunidade_atividade
            LEFT JOIN campos_modelo c ON c.idcampo = va.idcampo
            WHERE ra.idusuario = :usuario AND ra.excluido_em IS NULL AND ra.status = 'concluido'
              AND m.familia_hub = 'athletics' AND ua.tipo_unidade = 'tentativa'
              AND ra.data_inicio >= NOW() - INTERVAL '48 months'
            GROUP BY ra.idregistro, ra.data_inicio, m.nome, m.slug, ua.idunidade_atividade, ua.ordem
            ORDER BY ra.data_inicio DESC, ua.ordem");
        $stmt->execute([':usuario' => $userId]);
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        if ($e->getCode() !== '42P01' && $e->getCode() !== '42703') throw $e;
    }

    $periodWindow ??= sportHubResolvePeriod();
    $currentStart = $periodWindow['current_start'];
    $currentEnd = $periodWindow['current_end'];
    $events = [];
    $currentAttempts = 0;
    $currentValid = 0;
    foreach ($rows as $row) {
        $slug = (string) ($row['modalidade_slug'] ?? '');
        if ($slug === '') continue;
        $date = new DateTimeImmutable((string) $row['data_inicio']);
        if ($date >= $currentEnd) continue;
        $invalid = stridebr_db_bool($row['tentativa_nula'] ?? false);
        $mark = is_numeric($row['marca_m'] ?? null) ? max(0.0, (float) $row['marca_m']) : null;
        $wind = is_numeric($row['vento_m_s'] ?? null) ? (float) $row['vento_m_s'] : null;
        $event = &$events[$slug];
        if (!is_array($event ?? null)) {
            $event = [
                'nome' => (string) ($row['modalidade_nome'] ?? 'Prova'),
                'sessions' => [],
                'attempts' => 0,
                'valid_attempts' => 0,
                'best_mark' => null,
                'best_wind' => null,
                'best_legal_mark' => null,
                'best_legal_wind' => null,
                'latest_mark' => null,
                'history' => [],
            ];
        }
        $event['attempts']++;
        $sessionId = (string) $row['idregistro'];
        $event['sessions'][$sessionId] = true;
        if ($date >= $currentStart && $date < $currentEnd) $currentAttempts++;
        if (!$invalid && $mark !== null && $mark > 0) {
            $event['valid_attempts']++;
            if ($date >= $currentStart && $date < $currentEnd) $currentValid++;
            $day = $date->format('Y-m-d');
            $event['history'][$day] ??= ['date' => $day, 'best_mark' => null];
            if ($event['history'][$day]['best_mark'] === null || $mark > $event['history'][$day]['best_mark']) $event['history'][$day]['best_mark'] = $mark;
            if ($event['latest_mark'] === null) $event['latest_mark'] = $mark;
            if ($event['best_mark'] === null || $mark > $event['best_mark']) {
                $event['best_mark'] = $mark;
                $event['best_wind'] = $wind;
            }
            $windSensitive = in_array($slug, ['salto-em-distancia','salto-triplo'], true);
            $legalWind = !$windSensitive || $wind === null || $wind <= 2.0;
            if ($legalWind && ($event['best_legal_mark'] === null || $mark > $event['best_legal_mark'])) {
                $event['best_legal_mark'] = $mark;
                $event['best_legal_wind'] = $wind;
            }
        }
        unset($event);
    }

    $fieldEvents = [];
    foreach ($events as $slug => $event) {
        $history = array_values($event['history']);
        usort($history, static fn(array $a, array $b): int => strcmp($a['date'], $b['date']));
        $fieldEvents[] = [
            'slug' => $slug,
            'nome' => $event['nome'],
            'sessions' => count($event['sessions']),
            'attempts' => $event['attempts'],
            'valid_attempts' => $event['valid_attempts'],
            'best_mark' => $event['best_mark'],
            'best_wind' => $event['best_wind'],
            'best_legal_mark' => $event['best_legal_mark'],
            'best_legal_wind' => $event['best_legal_wind'],
            'wind_aided_best' => in_array($slug, ['salto-em-distancia','salto-triplo'], true) && $event['best_wind'] !== null && (float) $event['best_wind'] > 2.0,
            'latest_mark' => $event['latest_mark'],
            'history' => array_slice($history, -10),
        ];
    }
    usort($fieldEvents, static function (array $a, array $b): int {
        $aDate = end($a['history'])['date'] ?? '';
        $bDate = end($b['history'])['date'] ?? '';
        return strcmp($bDate, $aDate);
    });

    $athleticsActivities = array_values(array_filter($activities, static fn(array $row): bool => ($row['hub_bucket'] ?? '') === 'athletics'));
    $eventsPracticed = [];
    $timedSlugs = [
        'atletismo-60m', 'atletismo-100m', 'atletismo-200m', 'atletismo-400m', 'atletismo-800m', 'atletismo-1500m', 'atletismo-milha',
        'atletismo-3000m', 'atletismo-5000m', 'atletismo-10000m', '60m-com-barreiras', '100m-com-barreiras', '110m-com-barreiras',
        '400m-com-barreiras', '3000m-com-obstaculos', 'revezamento-4x100m', 'revezamento-4x400m',
    ];
    $track = [];
    foreach ($athleticsActivities as $row) {
        $date = new DateTimeImmutable((string) $row['data_inicio']);
        if ($date >= $currentEnd) continue;
        $slug = (string) ($row['modalidade_slug'] ?? '');
        if ($slug !== '' && $date >= $currentStart && $date < $currentEnd) $eventsPracticed[$slug] = true;
        if (!in_array($slug, $timedSlugs, true)) continue;
        $duration = (float) ($row['duration_s'] ?? 0);
        if ($duration <= 0) continue;
        $track[$slug] ??= [
            'slug' => $slug,
            'nome' => (string) ($row['modalidade_nome'] ?? 'Prova'),
            'best_time_s' => null,
            'latest_time_s' => null,
            'best_legal_time_s' => null,
            'best_wind_m_s' => null,
            'best_reaction_s' => null,
            'sessions' => 0,
            'history' => [],
        ];
        $track[$slug]['sessions']++;
        if ($track[$slug]['latest_time_s'] === null) $track[$slug]['latest_time_s'] = $duration;
        $wind = is_numeric($row['vento_m_s'] ?? null) ? (float) $row['vento_m_s'] : null;
        $reaction = is_numeric($row['tempo_reacao_s'] ?? null) ? max(0.0, (float) $row['tempo_reacao_s']) : null;
        if ($track[$slug]['best_time_s'] === null || $duration < $track[$slug]['best_time_s']) {
            $track[$slug]['best_time_s'] = $duration;
            $track[$slug]['best_wind_m_s'] = $wind;
        }
        $windSensitive = in_array($slug, ['atletismo-100m','atletismo-200m','100m-com-barreiras','110m-com-barreiras'], true);
        if ((!$windSensitive || $wind === null || $wind <= 2.0) && ($track[$slug]['best_legal_time_s'] === null || $duration < $track[$slug]['best_legal_time_s'])) $track[$slug]['best_legal_time_s'] = $duration;
        if ($reaction !== null && ($track[$slug]['best_reaction_s'] === null || $reaction < $track[$slug]['best_reaction_s'])) $track[$slug]['best_reaction_s'] = $reaction;
        $track[$slug]['history'][] = ['date' => substr((string) $row['data_inicio'], 0, 10), 'time_s' => $duration, 'wind_m_s' => $wind, 'reaction_s' => $reaction];
    }
    unset($eventsPracticed['']);
    $trackRecords = array_values($track);
    foreach ($trackRecords as &$record) $record['history'] = array_reverse(array_slice($record['history'], 0, 10));
    unset($record);
    usort($trackRecords, static fn(array $a, array $b): int => strcmp((string) ($b['history'][array_key_last($b['history'])]['date'] ?? ''), (string) ($a['history'][array_key_last($a['history'])]['date'] ?? '')));

    return [
        'field_events' => $fieldEvents,
        'track_records' => $trackRecords,
        'current_attempts' => $currentAttempts,
        'current_valid_attempts' => $currentValid,
        'events_practiced' => count($eventsPracticed),
    ];
}


function sportHubSessionDashboard(array $activities, string $bucket, ?array $periodWindow = null): array
{
    $periodWindow ??= sportHubResolvePeriod();
    $currentStart = $periodWindow['current_start'];
    $currentEnd = $periodWindow['current_end'];
    $previousStart = $periodWindow['previous_start'];
    $now = $currentEnd->modify('-1 second');
    $base = [
        'activities' => 0,
        'duration_s' => 0.0,
        'matches' => 0,
        'wins' => 0,
        'draws' => 0,
        'losses' => 0,
        'rounds' => 0,
        'score_for' => 0,
        'score_against' => 0,
        'score_samples' => 0,
        'points_total' => 0.0,
        'points_samples' => 0,
        'best_score' => null,
    ];
    $summary = ['current' => $base, 'previous' => $base];
    $weekStart = $now->modify('monday this week')->setTime(0, 0);
    $weeks = [];
    for ($index = 7; $index >= 0; $index--) {
        $start = $weekStart->modify('-' . $index . ' weeks');
        $key = $start->format('Y-m-d');
        $weeks[$key] = [
            'start' => $key,
            'label' => $start->format('d/m'),
            'activities' => 0,
            'duration_s' => 0.0,
            'matches' => 0,
            'wins' => 0,
            'rounds' => 0,
            'points' => 0.0,
        ];
    }
    $recent = [];
    $sports = [];
    $sessionTypes = [];
    $formats = [];
    foreach ($activities as $row) {
        if (($row['hub_bucket'] ?? '') !== $bucket) continue;
        $date = new DateTimeImmutable((string) $row['data_inicio']);
        if ($date >= $currentEnd) continue;
        $period = $date >= $currentStart && $date < $currentEnd ? 'current' : ($date >= $previousStart && $date < $currentStart ? 'previous' : null);
        $type = stridebr_lower(trim((string) ($row['tipo_sessao'] ?? '')));
        $result = stridebr_lower(trim((string) ($row['resultado'] ?? '')));
        $format = stridebr_lower(trim((string) ($row['formato_jogo'] ?? '')));
        $duration = max(0.0, (float) ($row['duration_s'] ?? 0));
        $rounds = is_numeric($row['rounds'] ?? null) ? max(0, (int) $row['rounds']) : 0;
        $scoreFor = is_numeric($row['placar_favor'] ?? null) ? max(0, (int) $row['placar_favor']) : null;
        $scoreAgainst = is_numeric($row['placar_contra'] ?? null) ? max(0, (int) $row['placar_contra']) : null;
        $points = is_numeric($row['pontuacao'] ?? null) ? (float) $row['pontuacao'] : null;
        $isMatch = in_array($type, ['partida','jogo','amistoso','luta','competicao'], true);
        $activityWeek = $date->modify('monday this week')->setTime(0, 0)->format('Y-m-d');
        if (isset($weeks[$activityWeek])) {
            $weeks[$activityWeek]['activities']++;
            $weeks[$activityWeek]['duration_s'] += $duration;
            if ($isMatch) $weeks[$activityWeek]['matches']++;
            if ($result === 'vitoria') $weeks[$activityWeek]['wins']++;
            $weeks[$activityWeek]['rounds'] += $rounds;
            if ($points !== null) $weeks[$activityWeek]['points'] += $points;
        }
        if ($period !== null) {
            $data = &$summary[$period];
            $data['activities']++;
            $data['duration_s'] += $duration;
            if ($isMatch) $data['matches']++;
            if ($result === 'vitoria') $data['wins']++;
            elseif ($result === 'empate') $data['draws']++;
            elseif ($result === 'derrota') $data['losses']++;
            $data['rounds'] += $rounds;
            if ($scoreFor !== null && $scoreAgainst !== null) {
                $data['score_for'] += $scoreFor;
                $data['score_against'] += $scoreAgainst;
                $data['score_samples']++;
            }
            if ($points !== null) {
                $data['points_total'] += $points;
                $data['points_samples']++;
                $data['best_score'] = $data['best_score'] === null ? $points : max((float) $data['best_score'], $points);
            }
            unset($data);
        }
        $sport = stridebr_sport_name((string) ($row['modalidade_slug'] ?? ''), trim((string) ($row['modalidade_nome'] ?? stridebr_t('activity.activity'))));
        $sports[$sport] = ($sports[$sport] ?? 0) + 1;
        if ($type !== '') $sessionTypes[$type] = ($sessionTypes[$type] ?? 0) + 1;
        if ($format !== '') $formats[$format] = ($formats[$format] ?? 0) + 1;
        if (count($recent) < 12) {
            $recent[] = [
                'idregistro' => (string) ($row['idregistro'] ?? ''),
                'date' => (string) ($row['data_inicio'] ?? ''),
                'title' => stridebr_present_activity_title((string) ($row['titulo'] ?? $sport), (string) ($row['modalidade_slug'] ?? ''), (string) ($row['modalidade_nome'] ?? '')),
                'sport' => $sport,
                'duration_s' => $duration,
                'type' => $type,
                'format' => $format,
                'result' => $result,
                'opponent' => trim((string) ($row['adversario'] ?? '')),
                'score' => trim((string) ($row['placar'] ?? '')),
                'score_for' => $scoreFor,
                'score_against' => $scoreAgainst,
                'position' => trim((string) ($row['posicao'] ?? '')),
                'rounds' => $rounds,
                'points' => $points,
            ];
        }
    }
    foreach ($summary as &$data) {
        $decided = (int) $data['wins'] + (int) $data['draws'] + (int) $data['losses'];
        $data['decided'] = $decided;
        $data['win_rate'] = $decided > 0 ? ((int) $data['wins'] / $decided) * 100.0 : null;
        $data['avg_duration_s'] = (int) $data['activities'] > 0 ? (float) $data['duration_s'] / (int) $data['activities'] : 0.0;
        $data['avg_score'] = (int) $data['points_samples'] > 0 ? (float) $data['points_total'] / (int) $data['points_samples'] : null;
    }
    unset($data);
    arsort($sports);
    arsort($sessionTypes);
    arsort($formats);
    return ['summary' => $summary, 'recent' => $recent, 'sports' => $sports, 'session_types' => $sessionTypes, 'formats' => $formats, 'weeks' => array_values($weeks)];
}

function sportHubSessionTypeLabel(string $value): string
{
    $key = match ($value) {
        'treino', 'partida', 'aula', 'jogo', 'amistoso', 'tecnica', 'saco-manopla', 'sparring', 'luta', 'competicao' => $value,
        default => '',
    };
    if ($key !== '') return stridebr_t('progress.session_type.' . str_replace('-', '_', $key));
    return $value !== '' ? ucfirst(str_replace('-', ' ', $value)) : stridebr_t('progress.session');
}

function sportHubResultLabel(string $value): string
{
    return match ($value) {
        'vitoria' => stridebr_t('progress.result.win'),
        'empate' => stridebr_t('progress.result.draw'),
        'derrota' => stridebr_t('progress.result.loss'),
        default => '',
    };
}

function sportHubSportBreakdown(array $activities, string $bucket): array
{
    $counts = [];
    foreach ($activities as $row) {
        if (($row['hub_bucket'] ?? '') !== $bucket) continue;
        $name = stridebr_sport_name((string) ($row['modalidade_slug'] ?? ''), (string) ($row['modalidade_nome'] ?? stridebr_t('activity.activity')));
        $counts[$name] ??= ['activities' => 0, 'duration_s' => 0.0, 'distance_m' => 0.0, 'calories_kcal' => 0.0];
        $counts[$name]['activities']++;
        $counts[$name]['duration_s'] += (float) ($row['duration_s'] ?? 0);
        $counts[$name]['distance_m'] += (float) ($row['distancia_metros'] ?? 0);
        $counts[$name]['calories_kcal'] += (float) ($row['calorias_kcal'] ?? 0);
    }
    uasort($counts, static fn(array $a, array $b): int => $b['activities'] <=> $a['activities']);
    return $counts;
}
