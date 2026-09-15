<?php

declare(strict_types=1);

require_once __DIR__ . '/atividade_modelo.php';
require_once __DIR__ . '/atividade_presenter.php';
require_once __DIR__ . '/activity_stream_service.php';
require_once __DIR__ . '/activity_analysis_service.php';

function activityInsightsList(PDO $pdo, string $idUsuario, int $limit = 240): array
{
    $limit = max(2, min(500, $limit));
    $stmt = $pdo->prepare(
        "SELECT ra.idregistro, ra.idmodalidade, COALESCE(NULLIF(ra.titulo, ''), m.nome) AS titulo,
                ra.data_inicio, ra.data_fim, ra.esforco_percebido,
                m.nome AS modalidade_nome, m.slug AS modalidade_slug, m.categoria,
                rota.distancia_metros, rota.ganho_elevacao_m, rota.coordenadas
         FROM registros_atividade ra
         JOIN modalidades m ON m.idmodalidade = ra.idmodalidade
         LEFT JOIN rotas_atividade rota ON rota.idregistro = ra.idregistro
         WHERE ra.idusuario = :usuario AND ra.excluido_em IS NULL AND ra.status = 'concluido'
         ORDER BY ra.data_inicio DESC, ra.idregistro DESC
         LIMIT {$limit}"
    );
    $stmt->execute([':usuario' => $idUsuario]);
    return $stmt->fetchAll();
}

function activityInsightsDistanceMeters(array $a, array $b): float
{
    $lat1 = deg2rad((float) ($a[1] ?? 0));
    $lat2 = deg2rad((float) ($b[1] ?? 0));
    $dLat = $lat2 - $lat1;
    $dLon = deg2rad((float) ($b[0] ?? 0) - (float) ($a[0] ?? 0));
    $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;
    return 6371000 * 2 * atan2(sqrt($h), sqrt(max(0.0, 1 - $h)));
}

function activityInsightsRoutePoints(array $row): array
{
    $raw = $row['coordenadas'] ?? null;
    $geo = is_array($raw) ? $raw : json_decode((string) $raw, true);
    $points = is_array($geo) && ($geo['type'] ?? '') === 'LineString' ? ($geo['coordinates'] ?? []) : [];
    return is_array($points) ? array_values(array_filter($points, static fn($p): bool => is_array($p) && count($p) >= 2)) : [];
}

function activityInsightsRoutesParecidas(array $a, array $b): bool
{
    $distA = (float) ($a['distancia_metros'] ?? 0);
    $distB = (float) ($b['distancia_metros'] ?? 0);
    if ($distA <= 0 || $distB <= 0 || abs($distA - $distB) > max(300.0, $distA * .12)) return false;
    $pointsA = activityInsightsRoutePoints($a);
    $pointsB = activityInsightsRoutePoints($b);
    if (count($pointsA) < 2 || count($pointsB) < 2) return false;
    $sameDirection = activityInsightsDistanceMeters($pointsA[0], $pointsB[0]) <= 250
        && activityInsightsDistanceMeters($pointsA[array_key_last($pointsA)], $pointsB[array_key_last($pointsB)]) <= 250;
    $reverseDirection = activityInsightsDistanceMeters($pointsA[0], $pointsB[array_key_last($pointsB)]) <= 250
        && activityInsightsDistanceMeters($pointsA[array_key_last($pointsA)], $pointsB[0]) <= 250;
    return $sameDirection || $reverseDirection;
}

function activityInsightsSuggestions(PDO $pdo, string $idUsuario, array $activities, string $idA): array
{
    $a = null;
    foreach ($activities as $row) if ((string) $row['idregistro'] === $idA) { $a = $row; break; }
    if (!$a) return [];
    $sameSport = [];
    $similarDistance = [];
    foreach ($activities as $row) {
        if ((string) $row['idregistro'] === $idA) continue;
        if ((string) $row['idmodalidade'] === (string) $a['idmodalidade']) $sameSport[] = $row;
        $distA = (float) ($a['distancia_metros'] ?? 0);
        $distB = (float) ($row['distancia_metros'] ?? 0);
        if ($distA > 0 && $distB > 0 && (string) $row['idmodalidade'] === (string) $a['idmodalidade'] && abs($distA - $distB) <= max(250.0, $distA * .08)) {
            $similarDistance[] = $row;
        }
    }
    $out = [];
    if ($sameSport) $out[] = ['kind' => 'recent', 'label' => 'Última do mesmo esporte', 'activity' => $sameSport[0]];

    if ($sameSport && !empty($a['distancia_metros'])) {
        $candidateIds = array_slice(array_map(static fn(array $row): string => (string) $row['idregistro'], $sameSport), 0, 20);
        $candidateIds[] = $idA;
        $params = [':usuario' => $idUsuario];
        $placeholders = [];
        foreach (array_values(array_unique($candidateIds)) as $i => $id) { $key = ':r' . $i; $placeholders[] = $key; $params[$key] = $id; }
        if ($placeholders) {
            $routeStmt = $pdo->prepare("SELECT ra.idregistro, r.distancia_metros, r.coordenadas FROM registros_atividade ra JOIN rotas_atividade r ON r.idregistro = ra.idregistro WHERE ra.idusuario = :usuario AND ra.excluido_em IS NULL AND ra.idregistro IN (" . implode(',', $placeholders) . ")");
            $routeStmt->execute($params);
            $routes = [];
            foreach ($routeStmt->fetchAll() as $row) $routes[(string) $row['idregistro']] = $row;
            if (isset($routes[$idA])) {
                foreach ($sameSport as $row) {
                    $id = (string) $row['idregistro'];
                    if (isset($routes[$id]) && activityInsightsRoutesParecidas($routes[$idA], $routes[$id])) {
                        $out[] = ['kind' => 'route', 'label' => 'Rota parecida', 'activity' => $row];
                        break;
                    }
                }
            }
        }
    }

    if ($similarDistance) {
        usort($similarDistance, static function (array $x, array $y): int {
            $dx = !empty($x['data_fim']) ? strtotime((string) $x['data_fim']) - strtotime((string) $x['data_inicio']) : PHP_INT_MAX;
            $dy = !empty($y['data_fim']) ? strtotime((string) $y['data_fim']) - strtotime((string) $y['data_inicio']) : PHP_INT_MAX;
            return $dx <=> $dy;
        });
        $best = $similarDistance[0];
        if (!array_filter($out, static fn(array $s): bool => (string) $s['activity']['idregistro'] === (string) $best['idregistro'])) {
            $out[] = ['kind' => 'distance', 'label' => 'Melhor tempo em distância parecida', 'activity' => $best];
        }
    }
    $unique = [];
    foreach ($out as $item) {
        $id = (string) $item['activity']['idregistro'];
        if (!isset($unique[$id])) $unique[$id] = $item;
    }
    return array_values($unique);
}

function activityInsightsDefaultB(PDO $pdo, string $idUsuario, array $activities, string $idA): string
{
    $suggestions = activityInsightsSuggestions($pdo, $idUsuario, $activities, $idA);
    if ($suggestions) return (string) $suggestions[0]['activity']['idregistro'];
    foreach ($activities as $row) if ((string) $row['idregistro'] !== $idA) return (string) $row['idregistro'];
    return '';
}

function activityInsightsStrength(PDO $pdo, string $idUsuario, string $idRegistro): array
{
    $stmt = $pdo->prepare("SELECT sea.idexercicio,sea.nome_exercicio,sea.concluida,sea.repeticoes,sea.carga_kg
        FROM series_exercicio_atividade sea
        JOIN registros_atividade ra ON ra.idregistro=sea.idregistro
        WHERE sea.idregistro=:registro AND ra.idusuario=:usuario AND ra.excluido_em IS NULL
        ORDER BY sea.ordem_exercicio,sea.ordem_serie,sea.idserie");
    $stmt->execute([':registro' => $idRegistro, ':usuario' => $idUsuario]);
    $rows = $stmt->fetchAll();
    if ($rows === []) return [];
    $exercises=[];$setsDone=0;$repsTotal=0.0;$volume=0.0;$maxLoad=null;
    foreach($rows as $row){
        $name=trim((string)($row['nome_exercicio']??'Exercício'));$key=stridebr_lower($name);
        $exercises[$key]??=['nome'=>$name,'series'=>0,'repeticoes'=>0.0,'volume'=>0.0,'maior_carga'=>null];
        if(!stridebr_db_bool($row['concluida']??false))continue;
        $setsDone++;$exercises[$key]['series']++;
        $reps=is_numeric($row['repeticoes']??null)?max(0.0,(float)$row['repeticoes']):0.0;
        $load=is_numeric($row['carga_kg']??null)?max(0.0,(float)$row['carga_kg']):null;
        $repsTotal+=$reps;$exercises[$key]['repeticoes']+=$reps;
        if($load!==null){$maxLoad=$maxLoad===null?$load:max($maxLoad,$load);$exercises[$key]['maior_carga']=$exercises[$key]['maior_carga']===null?$load:max((float)$exercises[$key]['maior_carga'],$load);if($reps>0){$setVolume=$load*$reps;$volume+=$setVolume;$exercises[$key]['volume']+=$setVolume;}}
    }
    return ['series'=>$setsDone,'repeticoes'=>$repsTotal,'volume'=>$volume,'maior_carga'=>$maxLoad,'exercicios'=>array_values($exercises)];
}

function activityInsightsCompare(PDO $pdo, string $idUsuario, string $idA, string $idB): array
{
    $a = atividadeDetalheApi($pdo, $idA, $idUsuario);
    $b = atividadeDetalheApi($pdo, $idB, $idUsuario);
    if ($a === [] || $b === []) throw new RuntimeException('Não foi possível carregar uma das atividades selecionadas.');
    $metricMap = static function (array $activity): array {
        $out = [];
        foreach ($activity['metricas'] ?? [] as $metric) {
            $label = trim((string) ($metric['rotulo'] ?? ''));
            if ($label !== '') $out[stridebr_lower($label)] = ['rotulo' => $label, 'valor' => (string) ($metric['valor'] ?? '')];
        }
        return $out;
    };
    $metricsA = $metricMap($a);
    $metricsB = $metricMap($b);
    $labels = array_values(array_unique(array_merge(array_keys($metricsA), array_keys($metricsB))));
    $metrics = [];
    foreach ($labels as $key) {
        $metrics[] = [
            'rotulo' => $metricsA[$key]['rotulo'] ?? $metricsB[$key]['rotulo'] ?? $key,
            'a' => $metricsA[$key]['valor'] ?? '—',
            'b' => $metricsB[$key]['valor'] ?? '—',
        ];
    }
    $streamQuery=['axis'=>'distance','resolution'=>'medium','streams'=>'pace,speed,heart_rate,altitude,cadence'];
    try { $streamsA=activityStreamRead($pdo,$idUsuario,$idA,$streamQuery); } catch(Throwable) { $streamsA=null; }
    try { $streamsB=activityStreamRead($pdo,$idUsuario,$idB,$streamQuery); } catch(Throwable) { $streamsB=null; }
    try { $splitsA=activityStreamSplits($pdo,$idUsuario,$idA,1000); } catch(Throwable) { $splitsA=null; }
    try { $splitsB=activityStreamSplits($pdo,$idUsuario,$idB,1000); } catch(Throwable) { $splitsB=null; }
    try { $analysisA=activityAnalysisCompute($pdo,$idUsuario,$idA); } catch(Throwable) { $analysisA=null; }
    try { $analysisB=activityAnalysisCompute($pdo,$idUsuario,$idB); } catch(Throwable) { $analysisB=null; }
    return ['a'=>$a,'b'=>$b,'metricas'=>$metrics,'forca_a'=>activityInsightsStrength($pdo,$idUsuario,$idA),'forca_b'=>activityInsightsStrength($pdo,$idUsuario,$idB),'streams_a'=>$streamsA,'streams_b'=>$streamsB,'splits_a'=>$splitsA,'splits_b'=>$splitsB,'analysis_a'=>$analysisA,'analysis_b'=>$analysisB];
}

function activityInsightsProgress(PDO $pdo, string $idUsuario, ?DateTimeImmutable $anchorEnd = null): array
{
    $prefStmt = $pdo->prepare('SELECT preferenciasusuario FROM usuarios WHERE idusuario = :usuario');
    $prefStmt->execute([':usuario' => $idUsuario]);
    $raw = $prefStmt->fetchColumn();
    $prefs = is_array($raw) ? $raw : (json_decode((string) ($raw ?: '{}'), true) ?: []);
    $frequency = max(0, min(7, (int) ($prefs['weekly_frequency'] ?? 0)));
    $timezone = new DateTimeZone('America/Sao_Paulo');
    $anchorEnd ??= (new DateTimeImmutable('now', $timezone))->modify('+1 day')->setTime(0, 0);
    $anchorDate = $anchorEnd->setTimezone($timezone)->modify('-1 second')->format('Y-m-d');

    $weekStmt = $pdo->prepare("WITH params AS (
        SELECT CAST(:anchor_date AS date) AS anchor_date
    ), weeks AS (
        SELECT generate_series(date_trunc('week', p.anchor_date::timestamp) - INTERVAL '7 weeks', date_trunc('week', p.anchor_date::timestamp), INTERVAL '1 week')::date AS week_start
        FROM params p
    ), data AS (
        SELECT date_trunc('week', ra.data_inicio AT TIME ZONE 'America/Sao_Paulo')::date AS week_start,
               COUNT(DISTINCT ra.idregistro) AS activities,
               COALESCE(SUM(COALESCE(r.distancia_metros, 0)), 0) AS distance_m,
               COALESCE(SUM(EXTRACT(EPOCH FROM (COALESCE(ra.data_fim, ra.data_inicio) - ra.data_inicio))), 0) AS duration_s,
               COALESCE(SUM(COALESCE(r.ganho_elevacao_m, 0)), 0) AS elevation_m
        FROM registros_atividade ra
        LEFT JOIN rotas_atividade r ON r.idregistro = ra.idregistro
        CROSS JOIN params p
        WHERE ra.idusuario = :usuario AND ra.excluido_em IS NULL AND ra.status = 'concluido' AND COALESCE(ra.excluir_estatisticas,FALSE)=FALSE
          AND (ra.data_inicio AT TIME ZONE 'America/Sao_Paulo')::date >= (date_trunc('week', p.anchor_date::timestamp) - INTERVAL '7 weeks')::date
          AND (ra.data_inicio AT TIME ZONE 'America/Sao_Paulo')::date <= p.anchor_date
        GROUP BY 1
    )
    SELECT w.week_start, COALESCE(d.activities,0) AS activities, COALESCE(d.distance_m,0) AS distance_m,
           COALESCE(d.duration_s,0) AS duration_s, COALESCE(d.elevation_m,0) AS elevation_m
    FROM weeks w LEFT JOIN data d USING (week_start) ORDER BY w.week_start");
    $weekStmt->execute([':usuario' => $idUsuario, ':anchor_date' => $anchorDate]);
    $weeks = $weekStmt->fetchAll();
    $met = $frequency > 0 ? count(array_filter($weeks, static fn(array $w): bool => (int) $w['activities'] >= $frequency)) : null;

    $recordStmt = $pdo->prepare("SELECT
        MAX(r.distancia_metros) AS max_distance,
        MAX(r.ganho_elevacao_m) AS max_elevation,
        MAX(EXTRACT(EPOCH FROM (ra.data_fim - ra.data_inicio))) FILTER (WHERE ra.data_fim IS NOT NULL) AS max_duration,
        COUNT(*) AS activities_total
        FROM registros_atividade ra LEFT JOIN rotas_atividade r ON r.idregistro = ra.idregistro
        WHERE ra.idusuario = :usuario AND ra.excluido_em IS NULL AND ra.status = 'concluido' AND COALESCE(ra.excluir_estatisticas,FALSE)=FALSE");
    $recordStmt->execute([':usuario' => $idUsuario]);
    $records = $recordStmt->fetch() ?: [];

    $setsStmt = $pdo->prepare("SELECT MAX(sea.carga_kg) FROM series_exercicio_atividade sea
        JOIN registros_atividade ra ON ra.idregistro=sea.idregistro
        WHERE ra.idusuario=:usuario AND ra.excluido_em IS NULL AND ra.status='concluido'
          AND COALESCE(ra.excluir_estatisticas,FALSE)=FALSE AND sea.concluida=TRUE AND sea.carga_kg IS NOT NULL");
    $setsStmt->execute([':usuario' => $idUsuario]);
    $maxLoadRaw = $setsStmt->fetchColumn();
    $maxLoad = is_numeric($maxLoadRaw) ? max(0.0, (float) $maxLoadRaw) : null;

    $recent = array_slice($weeks, -4);
    $previous = array_slice($weeks, 0, 4);
    $sum = static function (array $rows, string $key): float { $v = 0.0; foreach ($rows as $row) $v += (float) ($row[$key] ?? 0); return $v; };
    return [
        'weekly_frequency' => $frequency,
        'weeks' => $weeks,
        'weeks_met' => $met,
        'records' => $records,
        'max_load' => $maxLoad,
        'trends' => [
            'activities' => [$sum($recent, 'activities'), $sum($previous, 'activities')],
            'distance_m' => [$sum($recent, 'distance_m'), $sum($previous, 'distance_m')],
            'duration_s' => [$sum($recent, 'duration_s'), $sum($previous, 'duration_s')],
            'elevation_m' => [$sum($recent, 'elevation_m'), $sum($previous, 'elevation_m')],
        ],
    ];
}
