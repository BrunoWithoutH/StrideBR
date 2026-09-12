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

function sportHubResolvePeriod(string $view = '12w', string $anchor = '', ?DateTimeImmutable $historyStart = null): array
{
    $timezone = new DateTimeZone('America/Sao_Paulo');
    $now = new DateTimeImmutable('now', $timezone);
    $aliases = ['month' => '4w', '3m' => '12w', '12m' => '1y'];
    $view = $aliases[$view] ?? $view;

    if ($view === 'all') {
        $start = $historyStart?->setTimezone($timezone) ?? $now;
        if ($start > $now) $start = $now;
        return [
            'view' => 'all',
            'months' => 0,
            'anchor' => '',
            'anchor_start' => null,
            'current_start' => $start,
            'current_end' => $now->modify('+1 second'),
            'previous_start' => null,
            'previous_end' => null,
            'span_seconds' => max(0, $now->getTimestamp() - $start->getTimestamp()),
            'has_previous' => false,
        ];
    }

    if (!in_array($view, ['4w', '12w', '6m', '1y'], true)) $view = '12w';
    $currentMonth = $now->modify('first day of this month')->setTime(0, 0);
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
        'has_previous' => true,
    ];
}

function sportHubPeriodForDate(DateTimeImmutable $date, array $periodWindow): ?string
{
    $currentStart = $periodWindow['current_start'];
    $currentEnd = $periodWindow['current_end'];
    if ($date >= $currentStart && $date < $currentEnd) return 'current';
    if (empty($periodWindow['has_previous'])) return null;
    $previousStart = $periodWindow['previous_start'] ?? null;
    $previousEnd = $periodWindow['previous_end'] ?? null;
    if ($previousStart instanceof DateTimeImmutable && $previousEnd instanceof DateTimeImmutable && $date >= $previousStart && $date < $previousEnd) return 'previous';
    return null;
}

function sportHubActivityRows(PDO $pdo, string $userId, ?int $days = 370): array
{
    $whereDate = '';
    $params = [':usuario' => $userId];
    if ($days !== null) {
        $days = max(30, min(36500, $days));
        $whereDate = " AND ra.data_inicio >= NOW() - (CAST(:dias AS integer) * INTERVAL '1 day')";
        $params[':dias'] = $days;
    }
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
        WHERE ra.idusuario = :usuario AND ra.excluido_em IS NULL AND ra.status = 'concluido'{$whereDate}
        ORDER BY ra.data_inicio DESC");
    $stmt->execute($params);
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

function sportHubNavigationSports(PDO $pdo, string $userId, array $activities = []): array
{
    $sports = [];
    try {
        $stmt = $pdo->prepare("SELECT m.idmodalidade, m.slug, m.nome, m.categoria, m.familia_hub,
                COUNT(ra.idregistro) AS history_count,
                MIN(ra.data_inicio) AS first_activity,
                MAX(ra.data_inicio) AS last_activity,
                COALESCE(MAX(b.benchmark_count),0) AS benchmark_count,
                MAX(b.first_benchmark) AS first_benchmark,
                MAX(b.last_benchmark) AS last_benchmark,
                COALESCE(BOOL_OR(COALESCE(mu.ativo, FALSE)), FALSE) AS active
            FROM modalidades m
            LEFT JOIN registros_atividade ra ON ra.idmodalidade=m.idmodalidade AND ra.idusuario=:usuario_atividade AND ra.excluido_em IS NULL AND ra.status='concluido'
            LEFT JOIN modalidades_usuario mu ON mu.idmodalidade=m.idmodalidade AND mu.idusuario=:usuario_modalidade
            LEFT JOIN (
                SELECT idmodalidade,COUNT(*) AS benchmark_count,MIN(data_resultado) AS first_benchmark,MAX(data_resultado) AS last_benchmark
                FROM benchmarks_usuario
                WHERE idusuario=:usuario_benchmark AND excluido_progresso=FALSE
                GROUP BY idmodalidade
            ) b ON b.idmodalidade=m.idmodalidade
            GROUP BY m.idmodalidade, m.slug, m.nome, m.categoria, m.familia_hub
            HAVING COUNT(ra.idregistro) > 0 OR COALESCE(MAX(b.benchmark_count),0) > 0 OR COALESCE(BOOL_OR(COALESCE(mu.ativo, FALSE)), FALSE)=TRUE");
        $stmt->execute([':usuario_atividade' => $userId, ':usuario_modalidade' => $userId, ':usuario_benchmark' => $userId]);
        foreach ($stmt->fetchAll() as $row) {
            $slug = stridebr_lower(trim((string) ($row['slug'] ?? '')));
            if ($slug === '') continue;
            $family = sportHubBucket((string) ($row['categoria'] ?? ''), $slug, (string) ($row['familia_hub'] ?? ''));
            $activityCount = (int) ($row['history_count'] ?? 0);
            $benchmarkCount = (int) ($row['benchmark_count'] ?? 0);
            $firstActivity = trim((string) ($row['first_activity'] ?? ''));
            $firstBenchmark = trim((string) ($row['first_benchmark'] ?? ''));
            $lastActivity = trim((string) ($row['last_activity'] ?? ''));
            $lastBenchmark = trim((string) ($row['last_benchmark'] ?? ''));
            $firstHistory = $firstActivity;
            if ($firstBenchmark !== '' && ($firstHistory === '' || $firstBenchmark < substr($firstHistory, 0, 10))) $firstHistory = $firstBenchmark;
            $lastHistory = $lastActivity;
            if ($lastBenchmark !== '' && ($lastHistory === '' || $lastBenchmark > substr($lastHistory, 0, 10))) $lastHistory = $lastBenchmark;
            $item = [
                'idmodalidade' => (string) ($row['idmodalidade'] ?? ''),
                'idmodalidade' => (string) ($row['idmodalidade'] ?? ''),
                'slug' => $slug,
                'name' => (string) ($row['nome'] ?? $slug),
                'family' => $family,
                'count' => $activityCount,
                'history_count' => $activityCount + $benchmarkCount,
                'activity_history_count' => $activityCount,
                'benchmark_count' => $benchmarkCount,
                'first_activity' => $firstActivity !== '' ? $firstActivity : null,
                'last_activity' => $lastActivity !== '' ? $lastActivity : null,
                'first_history' => $firstHistory !== '' ? $firstHistory : null,
                'last_history' => $lastHistory !== '' ? $lastHistory : null,
                'active' => stridebr_db_bool($row['active'] ?? false),
                'has_history' => ($activityCount + $benchmarkCount) > 0,
            ];
            if ($family !== 'athletics') {
                $sports[$slug] = $item;
                continue;
            }
            if (!isset($sports['atletismo'])) {
                $sports['atletismo'] = [
                    'idmodalidade' => null,
                    'slug' => 'atletismo',
                    'name' => stridebr_t('progress.athletics'),
                    'family' => 'athletics',
                    'count' => 0,
                    'history_count' => 0,
                    'activity_history_count' => 0,
                    'benchmark_count' => 0,
                    'first_activity' => null,
                    'last_activity' => null,
                    'active' => false,
                    'has_history' => false,
                    'synthetic' => true,
                    'event_count' => 0,
                ];
            }
            $athletics = &$sports['atletismo'];
            $athletics['count'] += $item['count'];
            $athletics['history_count'] += $item['history_count'];
            $athletics['activity_history_count'] += $item['activity_history_count'];
            $athletics['benchmark_count'] += $item['benchmark_count'];
            $athletics['has_history'] = $athletics['history_count'] > 0;
            $athletics['event_count']++;
            $athletics['active'] = $athletics['active'] || $item['active'];
            if ($item['first_activity'] !== null && ($athletics['first_activity'] === null || strcmp((string) $item['first_activity'], (string) $athletics['first_activity']) < 0)) $athletics['first_activity'] = $item['first_activity'];
            if ($item['last_activity'] !== null && ($athletics['last_activity'] === null || strcmp((string) $item['last_activity'], (string) $athletics['last_activity']) > 0)) $athletics['last_activity'] = $item['last_activity'];
            unset($athletics);
        }
    } catch (PDOException $error) {
        if (!in_array($error->getCode(), ['42P01', '42703'], true)) throw $error;
        foreach (sportHubAvailableSports($activities) as $sport) {
            $sports[(string) $sport['slug']] = $sport + [
                'idmodalidade' => null,
                'history_count' => (int) ($sport['count'] ?? 0),
                'activity_history_count' => (int) ($sport['count'] ?? 0),
                'benchmark_count' => 0,
                'first_activity' => null,
                'last_activity' => null,
                'first_history' => null,
                'last_history' => null,
                'active' => false,
                'has_history' => (int) ($sport['count'] ?? 0) > 0,
            ];
        }
    }
    uasort($sports, static function (array $a, array $b): int {
        $active = (int) !empty($b['active']) <=> (int) !empty($a['active']);
        if ($active !== 0) return $active;
        $history = (int) ($b['history_count'] ?? 0) <=> (int) ($a['history_count'] ?? 0);
        if ($history !== 0) return $history;
        return strnatcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });
    return array_values($sports);
}

function sportHubHistoryStart(array $sports, string $sport = 'all', string $event = ''): ?DateTimeImmutable
{
    $candidate = '';
    if ($event !== '') {
        foreach ($sports as $meta) {
            if ((string) ($meta['slug'] ?? '') !== $event) continue;
            $candidate = trim((string) ($meta['first_activity'] ?? ''));
            break;
        }
    } elseif ($sport !== 'all') {
        foreach ($sports as $meta) {
            if ((string) ($meta['slug'] ?? '') !== $sport) continue;
            $candidate = trim((string) ($meta['first_activity'] ?? ''));
            break;
        }
    } else {
        foreach ($sports as $meta) {
            $first = trim((string) ($meta['first_activity'] ?? ''));
            if ($first !== '' && ($candidate === '' || $first < $candidate)) $candidate = $first;
        }
    }
    if ($candidate === '') return null;
    try {
        return new DateTimeImmutable($candidate, new DateTimeZone('America/Sao_Paulo'));
    } catch (Throwable) {
        return null;
    }
}

function sportHubAthleticsEvents(PDO $pdo, string $userId): array
{
    $events = [];
    try {
        $stmt = $pdo->prepare("SELECT m.idmodalidade, m.slug, m.nome, m.categoria, m.familia_hub,
                COUNT(ra.idregistro) AS history_count,
                MIN(ra.data_inicio) AS first_activity,
                MAX(ra.data_inicio) AS last_activity,
                COALESCE(BOOL_OR(COALESCE(mu.ativo, FALSE)), FALSE) AS active
            FROM modalidades m
            LEFT JOIN registros_atividade ra ON ra.idmodalidade=m.idmodalidade AND ra.idusuario=:usuario AND ra.excluido_em IS NULL AND ra.status='concluido'
            LEFT JOIN modalidades_usuario mu ON mu.idmodalidade=m.idmodalidade AND mu.idusuario=:usuario
            WHERE m.familia_hub='athletics'
            GROUP BY m.idmodalidade, m.slug, m.nome, m.categoria, m.familia_hub
            HAVING COUNT(ra.idregistro) > 0 OR COALESCE(BOOL_OR(COALESCE(mu.ativo, FALSE)), FALSE)=TRUE");
        $stmt->execute([':usuario' => $userId]);
        foreach ($stmt->fetchAll() as $row) {
            $slug = stridebr_lower(trim((string) ($row['slug'] ?? '')));
            if ($slug === '') continue;
            $events[] = [
                'slug' => $slug,
                'name' => (string) ($row['nome'] ?? $slug),
                'family' => 'athletics',
                'history_count' => (int) ($row['history_count'] ?? 0),
                'has_history' => (int) ($row['history_count'] ?? 0) > 0,
                'first_activity' => $row['first_activity'] ?? null,
                'last_activity' => $row['last_activity'] ?? null,
                'active' => stridebr_db_bool($row['active'] ?? false),
            ];
        }
    } catch (PDOException $error) {
        if (!in_array($error->getCode(), ['42P01', '42703'], true)) throw $error;
    }
    usort($events, static function (array $a, array $b): int {
        $active = (int) !empty($b['active']) <=> (int) !empty($a['active']);
        if ($active !== 0) return $active;
        $last = strcmp((string) ($b['last_activity'] ?? ''), (string) ($a['last_activity'] ?? ''));
        if ($last !== 0) return $last;
        return strnatcasecmp((string) $a['name'], (string) $b['name']);
    });
    return $events;
}

function sportHubFirstHistoryDate(array $sports): ?DateTimeImmutable
{
    $first = null;
    foreach ($sports as $sport) {
        if (empty($sport['first_activity'])) continue;
        try { $date = new DateTimeImmutable((string) $sport['first_activity']); } catch (Throwable) { continue; }
        if ($first === null || $date < $first) $first = $date;
    }
    return $first;
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

function sportHubFilterSport(array $activities, string $sport = 'all', string $event = ''): array
{
    $sport = stridebr_lower(trim($sport));
    $event = stridebr_lower(trim($event));
    if ($sport === '' || $sport === 'all') return array_values($activities);
    if ($sport === 'atletismo') {
        return array_values(array_filter($activities, static function (array $row) use ($event): bool {
            if ((string) ($row['hub_bucket'] ?? '') !== 'athletics') return false;
            return $event === '' || stridebr_lower((string) ($row['modalidade_slug'] ?? '')) === $event;
        }));
    }
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

function sportHubAdaptiveSeries(array $activities, array $periodWindow): array
{
    $start = $periodWindow['current_start'];
    $end = $periodWindow['current_end'];
    $view = (string) ($periodWindow['view'] ?? '12w');
    $spanDays = max(1.0, ($end->getTimestamp() - $start->getTimestamp()) / 86400);
    $granularity = match ($view) {
        '4w', '12w' => 'week',
        '6m', '1y' => 'month',
        'all' => $spanDays <= 730 ? 'month' : ($spanDays <= 1826 ? 'quarter' : 'year'),
        default => 'month',
    };
    $buckets = [];
    $cursor = $start;
    while ($cursor < $end) {
        $next = match ($granularity) {
            'week' => $cursor->modify('+7 days'),
            'month' => $cursor->modify('+1 month'),
            'quarter' => $cursor->modify('+3 months'),
            default => $cursor->modify('+1 year'),
        };
        if ($next > $end) $next = $end;
        $buckets[] = [
            'start' => $cursor,
            'end' => $next,
            'activities' => 0,
            'duration_s' => 0.0,
            'distance_m' => 0.0,
            'elevation_m' => 0.0,
            'duration_known' => false,
            'distance_known' => false,
            'elevation_known' => false,
            'sports' => [],
        ];
        if ($next <= $cursor) break;
        $cursor = $next;
    }
    foreach ($activities as $row) {
        try { $date = new DateTimeImmutable((string) ($row['data_inicio'] ?? '')); } catch (Throwable) { continue; }
        if ($date < $start || $date >= $end) continue;
        foreach ($buckets as &$bucket) {
            if ($date < $bucket['start'] || $date >= $bucket['end']) continue;
            $duration = max(0.0, (float) ($row['duration_s'] ?? 0));
            $distance = max(0.0, (float) ($row['distancia_metros'] ?? 0));
            $elevation = max(0.0, (float) ($row['ganho_elevacao_m'] ?? 0));
            $bucket['activities']++;
            $bucket['duration_s'] += $duration;
            $bucket['distance_m'] += $distance;
            $bucket['elevation_m'] += $elevation;
            if ($duration > 0) $bucket['duration_known'] = true;
            if ($distance > 0) $bucket['distance_known'] = true;
            if ($elevation > 0) $bucket['elevation_known'] = true;
            $slug = stridebr_lower((string) ($row['modalidade_slug'] ?? ''));
            if ($slug !== '') $bucket['sports'][$slug] = ($bucket['sports'][$slug] ?? 0) + 1;
            break;
        }
        unset($bucket);
    }
    return ['granularity' => $granularity, 'buckets' => $buckets];
}

function sportHubConsistencySummary(array $activities, array $periodWindow): array
{
    $timezone = new DateTimeZone('America/Sao_Paulo');
    $view = (string) ($periodWindow['view'] ?? '12w');
    $end = $periodWindow['current_end'];
    $start = $periodWindow['current_start'];
    $unit = in_array($view, ['6m', '1y'], true) ? 'month' : 'week';
    $recentOnly = false;
    if ($view === 'all') {
        $unit = 'week';
        $start = $end->modify('-12 weeks');
        $recentOnly = true;
    }
    $units = [];
    $cursor = $start;
    while ($cursor < $end) {
        $next = $unit === 'month' ? $cursor->modify('+1 month') : $cursor->modify('+7 days');
        if ($next > $end) $next = $end;
        $units[] = ['start' => $cursor, 'end' => $next, 'active' => false, 'activities' => 0];
        if ($next <= $cursor) break;
        $cursor = $next;
    }
    foreach ($activities as $row) {
        try { $date = (new DateTimeImmutable((string) ($row['data_inicio'] ?? '')))->setTimezone($timezone); } catch (Throwable) { continue; }
        if ($date < $start || $date >= $end) continue;
        foreach ($units as &$bucket) {
            if ($date < $bucket['start'] || $date >= $bucket['end']) continue;
            $bucket['active'] = true;
            $bucket['activities']++;
            break;
        }
        unset($bucket);
    }
    $active = count(array_filter($units, static fn(array $bucket): bool => !empty($bucket['active'])));
    return ['unit' => $unit, 'active' => $active, 'total' => count($units), 'recent_only' => $recentOnly, 'units' => $units];
}

function sportHubSportsPeriodSummary(array $inventory, array $activities): array
{
    $bySlug = [];
    foreach ($inventory as $meta) {
        $slug = (string) ($meta['slug'] ?? '');
        if ($slug === '') continue;
        $bySlug[$slug] = $meta + ['activities' => 0, 'duration_s' => 0.0, 'distance_m' => 0.0, 'last_period_activity' => ''];
    }
    foreach ($activities as $row) {
        $slug = stridebr_lower((string) ($row['modalidade_slug'] ?? ''));
        if (($row['hub_bucket'] ?? '') === 'athletics') $slug = 'atletismo';
        if ($slug === '' || !isset($bySlug[$slug])) continue;
        $bySlug[$slug]['activities']++;
        $bySlug[$slug]['duration_s'] += max(0.0, (float) ($row['duration_s'] ?? 0));
        $bySlug[$slug]['distance_m'] += max(0.0, (float) ($row['distancia_metros'] ?? 0));
        $date = (string) ($row['data_inicio'] ?? '');
        if ($date !== '' && ($bySlug[$slug]['last_period_activity'] === '' || $date > $bySlug[$slug]['last_period_activity'])) $bySlug[$slug]['last_period_activity'] = $date;
    }
    uasort($bySlug, static function (array $a, array $b): int {
        $periodCmp = ((int) ($b['activities'] ?? 0)) <=> ((int) ($a['activities'] ?? 0));
        if ($periodCmp !== 0) return $periodCmp;
        $activeCmp = (empty($b['active']) ? 0 : 1) <=> (empty($a['active']) ? 0 : 1);
        if ($activeCmp !== 0) return $activeCmp;
        return strcmp((string) ($b['last_activity'] ?? ''), (string) ($a['last_activity'] ?? ''));
    });
    return array_values($bySlug);
}

function sportHubBucketMode(array $periodWindow): string
{
    $view = (string) ($periodWindow['view'] ?? '12w');
    if (in_array($view, ['4w', '12w'], true)) return 'week';
    if (in_array($view, ['6m', '1y'], true)) return 'month';
    $start = $periodWindow['current_start'] ?? null;
    $end = $periodWindow['current_end'] ?? null;
    if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable) return 'month';
    $days = max(0.0, ($end->getTimestamp() - $start->getTimestamp()) / 86400);
    if ($days <= 731) return 'month';
    if ($days <= 1827) return 'quarter';
    return 'year';
}

function sportHubPeriodSeries(array $activities, array $periodWindow): array
{
    $start = $periodWindow['current_start'];
    $end = $periodWindow['current_end'];
    $mode = sportHubBucketMode($periodWindow);
    if ($mode === 'week') return sportHubWeeklySeries($activities, $start, $end);
    $series = [];
    $cursor = $start;
    $step = $mode === 'year' ? '+1 year' : ($mode === 'quarter' ? '+3 months' : '+1 month');
    while ($cursor < $end && count($series) < 240) {
        $bucketEnd = $cursor->modify($step);
        if ($bucketEnd > $end) $bucketEnd = $end;
        $series[] = [
            'index' => count($series),
            'start' => $cursor,
            'end' => $bucketEnd,
            'activities' => 0,
            'duration_s' => 0.0,
            'distance_m' => 0.0,
            'elevation_m' => 0.0,
            'duration_known' => false,
            'distance_known' => false,
            'elevation_known' => false,
            'sports' => [],
            'mode' => $mode,
        ];
        $cursor = $bucketEnd;
    }
    foreach ($activities as $row) {
        try { $date = new DateTimeImmutable((string) ($row['data_inicio'] ?? '')); } catch (Throwable) { continue; }
        if ($date < $start || $date >= $end) continue;
        foreach ($series as &$bucket) {
            if ($date < $bucket['start'] || $date >= $bucket['end']) continue;
            $duration = max(0.0, (float) ($row['duration_s'] ?? 0));
            $distance = max(0.0, (float) ($row['distancia_metros'] ?? 0));
            $elevation = max(0.0, (float) ($row['ganho_elevacao_m'] ?? 0));
            $bucket['activities']++;
            $bucket['duration_s'] += $duration;
            $bucket['distance_m'] += $distance;
            $bucket['elevation_m'] += $elevation;
            if ($duration > 0) $bucket['duration_known'] = true;
            if ($distance > 0) $bucket['distance_known'] = true;
            if ($elevation > 0) $bucket['elevation_known'] = true;
            $slug = stridebr_lower((string) ($row['modalidade_slug'] ?? ''));
            if ($slug !== '') $bucket['sports'][$slug] = ($bucket['sports'][$slug] ?? 0) + 1;
            break;
        }
        unset($bucket);
    }
    return $series;
}

function sportHubConsistencyWeeks(array $activities, DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $weeks = [];
    $cursor = $start;
    while ($cursor < $end && count($weeks) < 260) {
        $weeks[$cursor->format('Y-m-d')] = 0;
        $cursor = $cursor->modify('+7 days');
    }
    foreach ($activities as $row) {
        try { $date = new DateTimeImmutable((string) ($row['data_inicio'] ?? '')); } catch (Throwable) { continue; }
        if ($date < $start || $date >= $end) continue;
        $index = (int) floor(($date->getTimestamp() - $start->getTimestamp()) / 604800);
        $key = $start->modify('+' . ($index * 7) . ' days')->format('Y-m-d');
        if (array_key_exists($key, $weeks)) $weeks[$key]++;
    }
    return [
        'weeks' => $weeks,
        'active_weeks' => count(array_filter($weeks, static fn(int $count): bool => $count > 0)),
        'total_weeks' => count($weeks),
    ];
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
    $hasPrevious = !empty($periodWindow['has_previous']) && $periodWindow['previous_start'] instanceof DateTimeImmutable;
    $previousStart = $hasPrevious ? $periodWindow['previous_start'] : null;
    $result = [];
    foreach (array_merge(['all'], array_keys(sportHubFamilies())) as $bucket) {
        $result[$bucket] = [
            'current' => ['activities' => 0, 'duration_s' => 0.0, 'distance_m' => 0.0, 'elevation_m' => 0.0, 'calories_kcal' => 0.0],
            'previous' => ['activities' => 0, 'duration_s' => 0.0, 'distance_m' => 0.0, 'elevation_m' => 0.0, 'calories_kcal' => 0.0],
        ];
    }
    foreach ($activities as $row) {
        $date = new DateTimeImmutable((string) $row['data_inicio']);
        $period = $date >= $currentStart && $date < $currentEnd ? 'current' : ($hasPrevious && $date >= $previousStart && $date < $currentStart ? 'previous' : null);
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

function sportHubStrengthSets(PDO $pdo, string $userId, ?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null): array
{
    $rows = [];
    $filters = [];
    $params = [':usuario' => $userId];
    if ($from instanceof DateTimeImmutable) { $filters[] = 'ra.data_inicio >= :inicio'; $params[':inicio'] = $from->format('Y-m-d H:i:sP'); }
    if ($to instanceof DateTimeImmutable) { $filters[] = 'ra.data_inicio < :fim'; $params[':fim'] = $to->format('Y-m-d H:i:sP'); }
    $filter = $filters === [] ? '' : ' AND ' . implode(' AND ', $filters);
    try {
        $stmt = $pdo->prepare("SELECT sa.idserie, sa.idregistro, sa.idexercicio, sa.nome_exercicio, sa.ordem_serie, sa.carga_kg, sa.repeticoes, sa.concluida,
            ra.data_inicio, e.grupos_musculares_primarios, e.grupos_musculares_secundarios
            FROM series_exercicio_atividade sa
            JOIN registros_atividade ra ON ra.idregistro = sa.idregistro
            LEFT JOIN exercicios e ON e.idexercicio = sa.idexercicio
            WHERE ra.idusuario = :usuario AND ra.excluido_em IS NULL AND ra.status = 'concluido'{$filter}
            ORDER BY ra.data_inicio DESC, sa.ordem_exercicio, sa.ordem_serie");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $row['source'] = 'normalized';
            $rows[] = $row;
        }
    } catch (PDOException $e) {
        if (!in_array($e->getCode(), ['42P01', '42703'], true)) throw $e;
    }
    try {
        $legacy = $pdo->prepare("SELECT st.idserie, ra.idregistro, se.idexercicio, se.nome_snapshot AS nome_exercicio, st.numero AS ordem_serie,
            st.carga_realizada, st.repeticoes_realizadas, st.concluida, ra.data_inicio,
            e.grupos_musculares_primarios, e.grupos_musculares_secundarios
            FROM sessoes_treino_series st
            JOIN sessoes_treino_exercicios se ON se.idsessao_exercicio = st.idsessao_exercicio
            JOIN sessoes_treino s ON s.idsessao = se.idsessao
            JOIN registros_atividade ra ON ra.idregistro = s.idregistro_atividade
            LEFT JOIN exercicios e ON e.idexercicio = se.idexercicio
            WHERE s.idusuario = :usuario AND s.status = 'concluido' AND st.concluida = TRUE
              AND ra.excluido_em IS NULL{$filter}
              AND NOT EXISTS (SELECT 1 FROM series_exercicio_atividade sa WHERE sa.idregistro = ra.idregistro)
            ORDER BY ra.data_inicio DESC, se.ordem, st.numero");
        $legacy->execute($params);
        foreach ($legacy->fetchAll() as $row) {
            $loadRaw = str_replace(',', '.', trim((string) ($row['carga_realizada'] ?? '')));
            $repsRaw = trim((string) ($row['repeticoes_realizadas'] ?? ''));
            $row['carga_kg'] = preg_match('/^\d+(?:\.\d+)?$/', $loadRaw) === 1 ? (float) $loadRaw : null;
            $row['repeticoes'] = preg_match('/^\d+$/', $repsRaw) === 1 ? (int) $repsRaw : null;
            $row['source'] = 'legacy';
            $rows[] = $row;
        }
    } catch (PDOException $e) {
        if (!in_array($e->getCode(), ['42P01', '42703'], true)) throw $e;
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
    $periodWindow ??= sportHubResolvePeriod();
    $currentStart = $periodWindow['current_start'];
    $currentEnd = $periodWindow['current_end'];
    $hasPrevious = !empty($periodWindow['has_previous']) && $periodWindow['previous_start'] instanceof DateTimeImmutable;
    $previousStart = $hasPrevious ? $periodWindow['previous_start'] : null;
    $sets = sportHubStrengthSets($pdo, $userId, $hasPrevious ? $previousStart : $currentStart, $currentEnd);
    $strengthActivities = array_values(array_filter($activities, static fn(array $row): bool => ($row['hub_bucket'] ?? '') === 'strength' && ($sportSlug === '' || stridebr_lower((string) ($row['modalidade_slug'] ?? '')) === $sportSlug)));
    $allowedActivityIds = array_fill_keys(array_map(static fn(array $row): string => (string) ($row['idregistro'] ?? ''), $strengthActivities), true);
    $base = ['workouts' => 0, 'duration_s' => 0.0, 'sets' => 0, 'reps' => 0, 'volume_kg' => 0.0];
    $summary = ['current' => $base, 'previous' => $base];
    $activityIds = ['current' => [], 'previous' => []];
    foreach ($strengthActivities as $row) {
        $date = new DateTimeImmutable((string) $row['data_inicio']);
        $period = $date >= $currentStart && $date < $currentEnd ? 'current' : ($hasPrevious && $date >= $previousStart && $date < $currentStart ? 'previous' : null);
        if ($period === null) continue;
        $activityIds[$period][(string) $row['idregistro']] = true;
        $summary[$period]['duration_s'] += (float) ($row['duration_s'] ?? 0);
    }
    foreach (['current', 'previous'] as $period) $summary[$period]['workouts'] = count($activityIds[$period]);

    $exerciseGroups = [];
    $directMuscles = [];
    $secondaryMuscles = [];
    foreach ($sets as $row) {
        if ($sportSlug !== '' && !isset($allowedActivityIds[(string) ($row['idregistro'] ?? '')])) continue;
        if (!stridebr_db_bool($row['concluida'] ?? true)) continue;
        $date = new DateTimeImmutable((string) $row['data_inicio']);
        if ($date >= $currentEnd) continue;
        $period = $date >= $currentStart && $date < $currentEnd ? 'current' : ($hasPrevious && $date >= $previousStart && $date < $currentStart ? 'previous' : null);
        if ($period === null) continue;
        $load = is_numeric($row['carga_kg'] ?? null) ? max(0.0, (float) $row['carga_kg']) : null;
        $reps = is_numeric($row['repeticoes'] ?? null) ? max(0, (int) $row['repeticoes']) : null;
        $summary[$period]['sets']++;
        if ($reps !== null) $summary[$period]['reps'] += $reps;
        if ($load !== null && $reps !== null) $summary[$period]['volume_kg'] += $load * $reps;

        $key = trim((string) ($row['idexercicio'] ?? '')) ?: stridebr_lower(trim((string) ($row['nome_exercicio'] ?? 'Exercício')));
        $exerciseGroups[$key] ??= [
            'nome' => (string) ($row['nome_exercicio'] ?? 'Exercício'),
            'latest_date' => null,
            'sessions' => [],
            'current' => ['best_load' => null, 'best_e1rm' => null, 'best_e1rm_source' => null, 'volume' => 0.0, 'sets' => 0],
            'previous' => ['best_load' => null, 'best_e1rm' => null, 'best_e1rm_source' => null, 'volume' => 0.0, 'sets' => 0],
        ];
        $group = &$exerciseGroups[$key];
        $group[$period]['sets']++;
        $day = $date->format('Y-m-d');
        if ($group['latest_date'] === null || $day > $group['latest_date']) $group['latest_date'] = $day;
        $group['sessions'][$day] ??= ['date' => $day, 'max_load' => null, 'best_e1rm' => null, 'best_e1rm_source' => null, 'volume' => 0.0, 'sets' => 0, 'reps' => 0];
        $group['sessions'][$day]['sets']++;
        if ($reps !== null) $group['sessions'][$day]['reps'] += $reps;
        if ($load !== null) {
            $group[$period]['best_load'] = $group[$period]['best_load'] === null ? $load : max((float) $group[$period]['best_load'], $load);
            $group['sessions'][$day]['max_load'] = $group['sessions'][$day]['max_load'] === null ? $load : max((float) $group['sessions'][$day]['max_load'], $load);
            if ($reps !== null && $reps > 0) {
                $volume = $load * $reps;
                $group[$period]['volume'] += $volume;
                $group['sessions'][$day]['volume'] += $volume;
                if ($reps <= 12) {
                    $e1rm = $load * (1 + ($reps / 30));
                    $source = ['load_kg' => $load, 'reps' => $reps, 'date' => $day, 'idregistro' => (string) ($row['idregistro'] ?? '')];
                    if ($group[$period]['best_e1rm'] === null || $e1rm > $group[$period]['best_e1rm']) {
                        $group[$period]['best_e1rm'] = $e1rm;
                        $group[$period]['best_e1rm_source'] = $source;
                    }
                    if ($group['sessions'][$day]['best_e1rm'] === null || $e1rm > $group['sessions'][$day]['best_e1rm']) {
                        $group['sessions'][$day]['best_e1rm'] = $e1rm;
                        $group['sessions'][$day]['best_e1rm_source'] = $source;
                    }
                }
            }
        }
        if ($period === 'current') {
            foreach (sportHubDecodeGroups($row['grupos_musculares_primarios'] ?? []) as $muscle) $directMuscles[$muscle] = ($directMuscles[$muscle] ?? 0) + 1;
            foreach (sportHubDecodeGroups($row['grupos_musculares_secundarios'] ?? []) as $muscle) $secondaryMuscles[$muscle] = ($secondaryMuscles[$muscle] ?? 0) + 1;
        }
        unset($group);
    }

    $percent = static fn(?float $current, ?float $previous): ?float => $current !== null && $previous !== null && $previous > 0 ? (($current - $previous) / $previous) * 100.0 : null;
    $exercises = [];
    foreach ($exerciseGroups as $key => $data) {
        $sessions = array_values($data['sessions']);
        usort($sessions, static fn(array $a, array $b): int => strcmp((string) $b['date'], (string) $a['date']));
        $current = $data['current'];
        $previous = $data['previous'];
        if ((int) $current['sets'] === 0 && (int) $previous['sets'] === 0) continue;
        $exercises[] = [
            'key' => (string) $key,
            'nome' => $data['nome'],
            'latest_date' => $data['latest_date'],
            'current_sets' => $current['sets'],
            'current_volume' => $current['volume'],
            'current_best_load' => $current['best_load'],
            'current_best_e1rm' => $current['best_e1rm'],
            'current_best_e1rm_source' => $current['best_e1rm_source'],
            'previous_sets' => $previous['sets'],
            'previous_volume' => $previous['volume'],
            'previous_best_load' => $previous['best_load'],
            'previous_best_e1rm' => $previous['best_e1rm'],
            'previous_best_e1rm_source' => $previous['best_e1rm_source'],
            'load_trend_pct' => $hasPrevious ? $percent($current['best_load'], $previous['best_load']) : null,
            'e1rm_trend_pct' => $hasPrevious ? $percent($current['best_e1rm'], $previous['best_e1rm']) : null,
            'volume_trend_pct' => $hasPrevious && $previous['volume'] > 0 ? (($current['volume'] - $previous['volume']) / $previous['volume']) * 100.0 : null,
            'history' => array_reverse(array_slice($sessions, 0, 8)),
        ];
    }
    usort($exercises, static fn(array $a, array $b): int => strcmp((string) $b['latest_date'], (string) $a['latest_date']));
    arsort($directMuscles);
    arsort($secondaryMuscles);
    $calendar = [];
    foreach ($strengthActivities as $row) {
        $date = (new DateTimeImmutable((string) $row['data_inicio']))->setTimezone(new DateTimeZone('America/Sao_Paulo'));
        if ($date < $currentStart || $date >= $currentEnd) continue;
        $day = $date->format('Y-m-d');
        $calendar[$day] = ($calendar[$day] ?? 0) + 1;
    }
    return [
        'summary' => $summary,
        'exercises' => array_slice($exercises, 0, 48),
        'direct_muscles' => $directMuscles,
        'secondary_muscles' => $secondaryMuscles,
        'calendar' => $calendar,
        'has_previous' => $hasPrevious,
    ];
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
    $hasPrevious = !empty($periodWindow['has_previous']) && $periodWindow['previous_start'] instanceof DateTimeImmutable;
    $previousStart = $hasPrevious ? $periodWindow['previous_start'] : null;
    $summary = [];
    $recent = [];
    $active = [];
    foreach ($definitions as $key => $definition) {
        $base = [
            'activities' => 0, 'active_days' => 0, 'duration_s' => 0.0, 'distance_m' => 0.0, 'elevation_m' => 0.0, 'calories_kcal' => 0.0,
            'best_pace_s' => null, 'best_speed_kmh' => null, 'longest_distance_m' => 0.0,
            'hr_weighted' => 0.0, 'hr_weight_s' => 0.0, 'max_hr_bpm' => null,
            'cadence_weighted' => 0.0, 'cadence_weight_s' => 0.0, 'power_weighted' => 0.0, 'power_weight_s' => 0.0, 'days' => [],
        ];
        $summary[$key] = ['current' => $base, 'previous' => $base];
        $recent[$key] = [];
    }
    foreach ($activities as $row) {
        if (($row['hub_bucket'] ?? '') !== 'cardio') continue;
        $discipline = sportHubCardioDiscipline((string) ($row['modalidade_slug'] ?? ''));
        $active[$discipline] = ($active[$discipline] ?? 0) + 1;
        $date = new DateTimeImmutable((string) $row['data_inicio']);
        if ($date >= $currentEnd) continue;
        $period = $date >= $currentStart && $date < $currentEnd ? 'current' : ($hasPrevious && $date >= $previousStart && $date < $currentStart ? 'previous' : null);
        if ($period === null) continue;
        $duration = max(0.0, (float) ($row['duration_s'] ?? 0));
        $distance = max(0.0, (float) ($row['distancia_metros'] ?? 0));
        $elevation = max(0.0, (float) ($row['ganho_elevacao_m'] ?? 0));
        $calories = max(0.0, (float) ($row['calorias_kcal'] ?? 0));
        $avgHr = is_numeric($row['fc_media_bpm'] ?? null) ? max(0.0, (float) $row['fc_media_bpm']) : null;
        $maxHr = is_numeric($row['fc_maxima_bpm'] ?? null) ? max(0.0, (float) $row['fc_maxima_bpm']) : null;
        $cadence = is_numeric($row['cadencia_media'] ?? null) ? max(0.0, (float) $row['cadencia_media']) : null;
        $power = is_numeric($row['potencia_media_w'] ?? null) ? max(0.0, (float) $row['potencia_media_w']) : null;
        $pace = $distance > 0 && $duration > 0 ? $duration / ($distance / ($discipline === 'swim' ? 100.0 : 1000.0)) : null;
        $speed = $distance > 0 && $duration > 0 ? ($distance / 1000.0) / ($duration / 3600.0) : null;
        foreach (['all', $discipline] as $target) {
            $data = &$summary[$target][$period];
            $data['activities']++;
            $data['duration_s'] += $duration;
            $data['distance_m'] += $distance;
            $data['elevation_m'] += $elevation;
            $data['calories_kcal'] += $calories;
            $data['longest_distance_m'] = max($data['longest_distance_m'], $distance);
            $data['days'][$date->format('Y-m-d')] = true;
            if ($pace !== null && ($data['best_pace_s'] === null || $pace < $data['best_pace_s'])) $data['best_pace_s'] = $pace;
            if ($speed !== null && ($data['best_speed_kmh'] === null || $speed > $data['best_speed_kmh'])) $data['best_speed_kmh'] = $speed;
            $weight = $duration > 0 ? $duration : 1.0;
            if ($avgHr !== null && $avgHr > 0) { $data['hr_weighted'] += $avgHr * $weight; $data['hr_weight_s'] += $weight; }
            if ($maxHr !== null && $maxHr > 0) $data['max_hr_bpm'] = $data['max_hr_bpm'] === null ? $maxHr : max((float) $data['max_hr_bpm'], $maxHr);
            if ($cadence !== null && $cadence > 0) { $data['cadence_weighted'] += $cadence * $weight; $data['cadence_weight_s'] += $weight; }
            if ($power !== null && $power > 0) { $data['power_weighted'] += $power * $weight; $data['power_weight_s'] += $weight; }
            unset($data);
            if ($period === 'current' && count($recent[$target]) < 16) {
                $recent[$target][] = [
                    'idregistro' => (string) ($row['idregistro'] ?? ''), 'date' => (string) $row['data_inicio'],
                    'title' => stridebr_present_activity_title((string) ($row['titulo'] ?? $row['modalidade_nome'] ?? stridebr_t('activity.activity')), (string) ($row['modalidade_slug'] ?? ''), (string) ($row['modalidade_nome'] ?? '')),
                    'sport' => stridebr_sport_name((string) ($row['modalidade_slug'] ?? ''), (string) ($row['modalidade_nome'] ?? stridebr_t('activity.activity'))),
                    'distance_m' => $distance, 'duration_s' => $duration, 'pace_s' => $pace, 'speed_kmh' => $speed, 'elevation_m' => $elevation,
                    'avg_hr_bpm' => $avgHr, 'max_hr_bpm' => $maxHr, 'cadence' => $cadence, 'power_w' => $power, 'calories_kcal' => $calories,
                    'provider' => trim((string) ($row['origem_provedor'] ?? '')),
                ];
            }
        }
    }
    foreach ($summary as &$periods) {
        foreach ($periods as &$data) {
            $data['active_days'] = count($data['days']);
            $data['avg_speed_kmh'] = $data['duration_s'] > 0 && $data['distance_m'] > 0 ? ($data['distance_m'] / 1000.0) / ($data['duration_s'] / 3600.0) : null;
            $data['avg_pace_km_s'] = $data['distance_m'] > 0 ? $data['duration_s'] / ($data['distance_m'] / 1000.0) : null;
            $data['avg_pace_100m_s'] = $data['distance_m'] > 0 ? $data['duration_s'] / ($data['distance_m'] / 100.0) : null;
            $data['avg_hr_bpm'] = $data['hr_weight_s'] > 0 ? $data['hr_weighted'] / $data['hr_weight_s'] : null;
            $data['avg_cadence'] = $data['cadence_weight_s'] > 0 ? $data['cadence_weighted'] / $data['cadence_weight_s'] : null;
            $data['avg_power_w'] = $data['power_weight_s'] > 0 ? $data['power_weighted'] / $data['power_weight_s'] : null;
            unset($data['days'], $data['hr_weighted'], $data['hr_weight_s'], $data['cadence_weighted'], $data['cadence_weight_s'], $data['power_weighted'], $data['power_weight_s']);
        }
        unset($data);
    }
    unset($periods);
    $percent = static fn(?float $current, ?float $previous): ?float => $current !== null && $previous !== null && $previous > 0 ? (($current - $previous) / $previous) * 100.0 : null;
    $trends = [];
    foreach ($definitions as $key => $definition) {
        $current = $summary[$key]['current'];
        $previous = $summary[$key]['previous'];
        $trends[$key] = [
            'distance_pct' => $hasPrevious ? $percent((float) $current['distance_m'], (float) $previous['distance_m']) : null,
            'duration_pct' => $hasPrevious ? $percent((float) $current['duration_s'], (float) $previous['duration_s']) : null,
            'activities_pct' => $hasPrevious ? $percent((float) $current['activities'], (float) $previous['activities']) : null,
            'pace_pct' => $hasPrevious ? $percent(is_numeric($current['avg_pace_km_s'] ?? null) ? (float) $current['avg_pace_km_s'] : null, is_numeric($previous['avg_pace_km_s'] ?? null) ? (float) $previous['avg_pace_km_s'] : null) : null,
            'speed_pct' => $hasPrevious ? $percent(is_numeric($current['avg_speed_kmh'] ?? null) ? (float) $current['avg_speed_kmh'] : null, is_numeric($previous['avg_speed_kmh'] ?? null) ? (float) $previous['avg_speed_kmh'] : null) : null,
            'current_distance_m' => $current['distance_m'], 'previous_distance_m' => $previous['distance_m'],
            'current_duration_s' => $current['duration_s'], 'previous_duration_s' => $previous['duration_s'],
        ];
    }
    $activeDefinitions = ['all' => $definitions['all']];
    foreach ($definitions as $key => $definition) if ($key !== 'all' && ($active[$key] ?? 0) > 0) $activeDefinitions[$key] = $definition;
    return ['summary' => $summary, 'recent' => $recent, 'trends' => $trends, 'active' => $activeDefinitions, 'has_previous' => $hasPrevious];
}

function sportHubAthleticsDashboard(PDO $pdo, string $userId, array $activities, ?array $periodWindow = null, string $eventSlug = ''): array
{
    $periodWindow ??= sportHubResolvePeriod();
    $currentStart = $periodWindow['current_start'];
    $currentEnd = $periodWindow['current_end'];
    $eventSlug = stridebr_lower(trim($eventSlug));
    $allowedSlugs = [];
    foreach ($activities as $row) {
        if (($row['hub_bucket'] ?? '') !== 'athletics') continue;
        $slug = stridebr_lower((string) ($row['modalidade_slug'] ?? ''));
        if ($slug !== '') $allowedSlugs[$slug] = true;
    }
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
              AND ra.data_inicio >= :inicio AND ra.data_inicio < :fim
            GROUP BY ra.idregistro, ra.data_inicio, m.nome, m.slug, ua.idunidade_atividade, ua.ordem
            ORDER BY ra.data_inicio DESC, ua.ordem");
        $stmt->execute([
            ':usuario' => $userId,
            ':inicio' => $currentStart->format('Y-m-d H:i:sP'),
            ':fim' => $currentEnd->format('Y-m-d H:i:sP'),
        ]);
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        if (!in_array($e->getCode(), ['42P01', '42703'], true)) throw $e;
    }

    $events = [];
    $currentAttempts = 0;
    $currentValid = 0;
    foreach ($rows as $row) {
        $slug = stridebr_lower((string) ($row['modalidade_slug'] ?? ''));
        if ($slug === '') continue;
        if ($eventSlug !== '' && $slug !== $eventSlug) continue;
        if ($eventSlug === '' && $allowedSlugs !== [] && !isset($allowedSlugs[$slug])) continue;
        $invalid = stridebr_db_bool($row['tentativa_nula'] ?? false);
        $mark = is_numeric($row['marca_m'] ?? null) ? max(0.0, (float) $row['marca_m']) : null;
        $wind = is_numeric($row['vento_m_s'] ?? null) ? (float) $row['vento_m_s'] : null;
        $event = &$events[$slug];
        if (!is_array($event ?? null)) {
            $event = [
                'nome' => (string) ($row['modalidade_nome'] ?? stridebr_t('progress.event')),
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
        $currentAttempts++;
        $sessionId = (string) ($row['idregistro'] ?? '');
        if ($sessionId !== '') $event['sessions'][$sessionId] = true;
        if (!$invalid && $mark !== null && $mark > 0) {
            $event['valid_attempts']++;
            $currentValid++;
            $day = substr((string) ($row['data_inicio'] ?? ''), 0, 10);
            $event['history'][$day] ??= ['date' => $day, 'best_mark' => null];
            if ($event['history'][$day]['best_mark'] === null || $mark > $event['history'][$day]['best_mark']) $event['history'][$day]['best_mark'] = $mark;
            if ($event['latest_mark'] === null) $event['latest_mark'] = $mark;
            if ($event['best_mark'] === null || $mark > $event['best_mark']) {
                $event['best_mark'] = $mark;
                $event['best_wind'] = $wind;
            }
            $windSensitive = in_array($slug, ['salto-em-distancia', 'salto-triplo'], true);
            $legalWind = !$windSensitive || ($wind !== null && $wind <= 2.0);
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
        usort($history, static fn(array $a, array $b): int => strcmp((string) $a['date'], (string) $b['date']));
        $fieldEvents[] = [
            'slug' => $slug,
            'nome' => $event['nome'],
            'direction' => 'higher',
            'sessions' => count($event['sessions']),
            'attempts' => $event['attempts'],
            'valid_attempts' => $event['valid_attempts'],
            'best_mark' => $event['best_mark'],
            'best_wind' => $event['best_wind'],
            'best_legal_mark' => $event['best_legal_mark'],
            'best_legal_wind' => $event['best_legal_wind'],
            'wind_aided_best' => in_array($slug, ['salto-em-distancia', 'salto-triplo'], true) && $event['best_wind'] !== null && (float) $event['best_wind'] > 2.0,
            'latest_mark' => $event['latest_mark'],
            'history' => array_slice($history, -12),
        ];
    }
    usort($fieldEvents, static function (array $a, array $b): int {
        $aDate = (string) (($a['history'][array_key_last($a['history'])]['date'] ?? ''));
        $bDate = (string) (($b['history'][array_key_last($b['history'])]['date'] ?? ''));
        return strcmp($bDate, $aDate);
    });

    $timedSlugs = [
        'atletismo-60m', 'atletismo-100m', 'atletismo-200m', 'atletismo-400m', 'atletismo-800m', 'atletismo-1500m', 'atletismo-milha',
        'atletismo-3000m', 'atletismo-5000m', 'atletismo-10000m', '60m-com-barreiras', '100m-com-barreiras', '110m-com-barreiras',
        '400m-com-barreiras', '3000m-com-obstaculos', 'revezamento-4x100m', 'revezamento-4x400m',
    ];
    $eventsPracticed = [];
    $track = [];
    foreach ($activities as $row) {
        if (($row['hub_bucket'] ?? '') !== 'athletics') continue;
        try { $date = new DateTimeImmutable((string) ($row['data_inicio'] ?? '')); } catch (Throwable) { continue; }
        if ($date < $currentStart || $date >= $currentEnd) continue;
        $slug = stridebr_lower((string) ($row['modalidade_slug'] ?? ''));
        if ($slug === '') continue;
        $eventsPracticed[$slug] = true;
        if (!in_array($slug, $timedSlugs, true)) continue;
        $duration = max(0.0, (float) ($row['duration_s'] ?? 0));
        if ($duration <= 0) continue;
        $track[$slug] ??= [
            'slug' => $slug,
            'nome' => (string) ($row['modalidade_nome'] ?? stridebr_t('progress.event')),
            'direction' => 'lower',
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
        $windSensitive = in_array($slug, ['atletismo-100m', 'atletismo-200m', '100m-com-barreiras', '110m-com-barreiras'], true);
        $legalWind = !$windSensitive || ($wind !== null && $wind <= 2.0);
        if ($legalWind && ($track[$slug]['best_legal_time_s'] === null || $duration < $track[$slug]['best_legal_time_s'])) $track[$slug]['best_legal_time_s'] = $duration;
        if ($reaction !== null && ($track[$slug]['best_reaction_s'] === null || $reaction < $track[$slug]['best_reaction_s'])) $track[$slug]['best_reaction_s'] = $reaction;
        $track[$slug]['history'][] = ['date' => substr((string) ($row['data_inicio'] ?? ''), 0, 10), 'time_s' => $duration, 'wind_m_s' => $wind, 'reaction_s' => $reaction];
    }
    $trackRecords = array_values($track);
    foreach ($trackRecords as &$record) {
        usort($record['history'], static fn(array $a, array $b): int => strcmp((string) $a['date'], (string) $b['date']));
        $record['history'] = array_slice($record['history'], -12);
    }
    unset($record);
    usort($trackRecords, static function (array $a, array $b): int {
        $aDate = (string) (($a['history'][array_key_last($a['history'])]['date'] ?? ''));
        $bDate = (string) (($b['history'][array_key_last($b['history'])]['date'] ?? ''));
        return strcmp($bDate, $aDate);
    });

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
    $hasPrevious = !empty($periodWindow['has_previous']) && $periodWindow['previous_start'] instanceof DateTimeImmutable;
    $previousStart = $hasPrevious ? $periodWindow['previous_start'] : null;
    $base = ['activities'=>0,'duration_s'=>0.0,'matches'=>0,'wins'=>0,'draws'=>0,'losses'=>0,'rounds'=>0,'score_for'=>0,'score_against'=>0,'score_samples'=>0,'points_total'=>0.0,'points_samples'=>0,'best_score'=>null];
    $summary = ['current'=>$base,'previous'=>$base];
    $recent = [];
    $sports = [];
    $sessionTypes = [];
    $formats = [];
    foreach ($activities as $row) {
        if (($row['hub_bucket'] ?? '') !== $bucket) continue;
        $date = new DateTimeImmutable((string) $row['data_inicio']);
        if ($date >= $currentEnd) continue;
        $period = $date >= $currentStart && $date < $currentEnd ? 'current' : ($hasPrevious && $date >= $previousStart && $date < $currentStart ? 'previous' : null);
        $type = stridebr_lower(trim((string) ($row['tipo_sessao'] ?? '')));
        $result = stridebr_lower(trim((string) ($row['resultado'] ?? '')));
        $format = stridebr_lower(trim((string) ($row['formato_jogo'] ?? '')));
        $duration = max(0.0, (float) ($row['duration_s'] ?? 0));
        $rounds = is_numeric($row['rounds'] ?? null) ? max(0, (int) $row['rounds']) : 0;
        $scoreFor = is_numeric($row['placar_favor'] ?? null) ? max(0, (int) $row['placar_favor']) : null;
        $scoreAgainst = is_numeric($row['placar_contra'] ?? null) ? max(0, (int) $row['placar_contra']) : null;
        $points = is_numeric($row['pontuacao'] ?? null) ? (float) $row['pontuacao'] : null;
        $isMatch = in_array($type, ['partida','jogo','amistoso','luta','competicao'], true);
        if ($period !== null) {
            $data = &$summary[$period];
            $data['activities']++;
            $data['duration_s'] += $duration;
            if ($isMatch) $data['matches']++;
            if ($result === 'vitoria') $data['wins']++;
            elseif ($result === 'empate') $data['draws']++;
            elseif ($result === 'derrota') $data['losses']++;
            $data['rounds'] += $rounds;
            if ($scoreFor !== null && $scoreAgainst !== null) { $data['score_for'] += $scoreFor; $data['score_against'] += $scoreAgainst; $data['score_samples']++; }
            if ($points !== null) { $data['points_total'] += $points; $data['points_samples']++; $data['best_score'] = $data['best_score'] === null ? $points : max((float) $data['best_score'], $points); }
            unset($data);
        }
        if ($period !== 'current') continue;
        $sport = stridebr_sport_name((string) ($row['modalidade_slug'] ?? ''), trim((string) ($row['modalidade_nome'] ?? stridebr_t('activity.activity'))));
        $sports[$sport] = ($sports[$sport] ?? 0) + 1;
        if ($type !== '') $sessionTypes[$type] = ($sessionTypes[$type] ?? 0) + 1;
        if ($format !== '') $formats[$format] = ($formats[$format] ?? 0) + 1;
        if (count($recent) < 12) {
            $recent[] = [
                'idregistro'=>(string)($row['idregistro']??''),'date'=>(string)($row['data_inicio']??''),
                'title'=>stridebr_present_activity_title((string)($row['titulo']??$sport),(string)($row['modalidade_slug']??''),(string)($row['modalidade_nome']??'')),
                'sport'=>$sport,'duration_s'=>$duration,'type'=>$type,'format'=>$format,'result'=>$result,'opponent'=>trim((string)($row['adversario']??'')),
                'score'=>trim((string)($row['placar']??'')),'score_for'=>$scoreFor,'score_against'=>$scoreAgainst,'position'=>trim((string)($row['posicao']??'')),'rounds'=>$rounds,'points'=>$points,
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
    arsort($sports); arsort($sessionTypes); arsort($formats);
    return ['summary'=>$summary,'recent'=>$recent,'sports'=>$sports,'session_types'=>$sessionTypes,'formats'=>$formats,'has_previous'=>$hasPrevious];
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
