<?php

declare(strict_types=1);

require_once __DIR__ . '/atividade_modelo.php';

const GPS_WEB_MAX_POINTS = 2000;
const GPS_WEB_MAX_SEGMENTS = 100;

function gpsWebRouteModalities(PDO $pdo, string $idUsuario): array
{
    $stmt = $pdo->prepare(
        "SELECT m.idmodalidade, m.nome, m.slug, m.icone, m.metrica_derivada, m.categoria, m.familia_hub,
                mm.idmodelo, mm.nome AS modelo_nome, mm.permite_multiplas_unidades
         FROM modalidades m
         JOIN LATERAL (
             SELECT mm.idmodelo, mm.nome, mm.permite_multiplas_unidades
             FROM modelos_modalidade mm
             LEFT JOIN modalidades_usuario mu
               ON mu.idusuario = :usuario_pref AND mu.idmodalidade = mm.idmodalidade
             WHERE mm.idmodalidade = m.idmodalidade
               AND mm.ativo = TRUE
               AND (mm.idusuario IS NULL OR mm.idusuario = :usuario_modelo)
             ORDER BY CASE WHEN mu.idmodelo_ativo = mm.idmodelo THEN 0 WHEN mm.padrao = TRUE THEN 1 ELSE 2 END,
                      mm.versao DESC, mm.idmodelo
             LIMIT 1
         ) mm ON TRUE
         WHERE m.ativo = TRUE
           AND m.permite_rota = TRUE
           AND (m.idusuario IS NULL OR m.idusuario = :usuario_modalidade)
         ORDER BY COALESCE(m.ordem_catalogo, 9999), m.nome"
    );
    $stmt->execute([
        ':usuario_pref' => $idUsuario,
        ':usuario_modelo' => $idUsuario,
        ':usuario_modalidade' => $idUsuario,
    ]);
    return $stmt->fetchAll() ?: [];
}

function gpsWebFindModality(array $modalities, string $requested): array
{
    $requested = trim($requested);
    foreach ($modalities as $modality) {
        if ((string) ($modality['idmodalidade'] ?? '') === $requested || (string) ($modality['slug'] ?? '') === $requested) {
            return $modality;
        }
    }
    foreach ($modalities as $modality) {
        if ((string) ($modality['slug'] ?? '') === 'corrida') return $modality;
    }
    return $modalities[0] ?? [];
}

function gpsWebRecordingKey(array $recording): string
{
    $key = trim((string) ($recording['recording_id'] ?? ''));
    if ($key === '' || preg_match('/^[A-Za-z0-9_-]{12,80}$/', $key) !== 1) {
        throw new InvalidArgumentException('Identificador da gravação GPS inválido.');
    }
    return $key;
}

function gpsWebFindExistingRecording(PDO $pdo, string $idUsuario, string $key): ?string
{
    try {
        $stmt = $pdo->prepare(
            'SELECT g.idregistro
             FROM gravacoes_gps_web g
             JOIN registros_atividade ra ON ra.idregistro = g.idregistro
             WHERE g.chave_gravacao = :chave AND ra.idusuario = :usuario
             LIMIT 1'
        );
        $stmt->execute([':chave' => $key, ':usuario' => $idUsuario]);
        $id = $stmt->fetchColumn();
        return is_string($id) && $id !== '' ? $id : null;
    } catch (PDOException $e) {
        if (in_array($e->getCode(), ['42P01', '42703'], true)) return null;
        throw $e;
    }
}

function gpsWebParseRecording(mixed $raw): array
{
    if (!is_string($raw) || trim($raw) === '') throw new InvalidArgumentException('A gravação GPS não foi enviada.');
    if (strlen($raw) > 450000) throw new InvalidArgumentException('A gravação GPS enviada é muito grande.');
    try {
        $payload = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new InvalidArgumentException('A gravação GPS enviada é inválida.');
    }
    if (!is_array($payload)) throw new InvalidArgumentException('A gravação GPS enviada é inválida.');
    return $payload;
}

function gpsWebValidatePoints(array $points): array
{
    if (count($points) < 2) throw new InvalidArgumentException('A gravação precisa de pelo menos dois pontos GPS aceitos.');
    if (count($points) > GPS_WEB_MAX_POINTS) throw new InvalidArgumentException('A gravação GPS excede o limite de pontos.');
    $coordinates = [];
    foreach ($points as $point) {
        if (!is_array($point) || !is_numeric($point['lat'] ?? null) || !is_numeric($point['lon'] ?? null)) {
            throw new InvalidArgumentException('Uma das posições GPS é inválida.');
        }
        $lat = (float) $point['lat'];
        $lon = (float) $point['lon'];
        if (!is_finite($lat) || !is_finite($lon) || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            throw new InvalidArgumentException('Uma das posições GPS está fora dos limites geográficos.');
        }
        $coordinates[] = [round($lon, 7), round($lat, 7)];
    }
    return $coordinates;
}

function gpsWebSecondsToInterval(float|int $seconds): string
{
    return atividadeSegundosParaIntervalo(max(0.0, min(604800.0, (float) $seconds)));
}

function gpsWebValueForField(array $field, float $distanceM, float $durationS, ?float $elevationM): mixed
{
    $slug = stridebr_lower((string) ($field['slug'] ?? ''));
    if ($slug === 'distancia') {
        return (string) ($field['unidade_simbolo'] ?? '') === 'km' ? round($distanceM / 1000, 4) : round($distanceM, 2);
    }
    if ($slug === 'duracao' && (string) ($field['tipo_campo'] ?? '') === 'intervalo') {
        return gpsWebSecondsToInterval($durationS);
    }
    if (in_array($slug, ['elevacao', 'desnivel'], true) && $elevationM !== null) {
        return (string) ($field['unidade_simbolo'] ?? '') === 'km' ? round($elevationM / 1000, 4) : round($elevationM, 1);
    }
    return null;
}

function gpsWebBuildFieldValues(array $fields, string $scope, float $distanceM, float $durationS, ?float $elevationM): array
{
    $values = [];
    foreach ($fields as $field) {
        if ((string) ($field['escopo'] ?? '') !== $scope) continue;
        $value = gpsWebValueForField($field, $distanceM, $durationS, $elevationM);
        if ($value !== null) $values[(string) $field['idcampo']] = $value;
    }
    return $values;
}

function gpsWebValidateSegments(array $rawSegments, array $allCoordinates, float $displayDistanceM, float $displayDurationS, ?float $displayElevationM): array
{
    if (count($rawSegments) > GPS_WEB_MAX_SEGMENTS) throw new InvalidArgumentException('A gravação possui trechos demais.');
    $segments = [];
    $measuredTotal = 0.0;
    $durationTotal = 0.0;
    foreach ($rawSegments as $index => $segment) {
        if (!is_array($segment)) continue;
        $startIndex = max(0, (int) ($segment['start_index'] ?? 0));
        $endIndex = min(count($allCoordinates) - 1, (int) ($segment['end_index'] ?? $startIndex));
        if ($endIndex < $startIndex) [$startIndex, $endIndex] = [$endIndex, $startIndex];
        $coordinates = array_slice($allCoordinates, $startIndex, $endIndex - $startIndex + 1);
        $measuredDistance = is_numeric($segment['distance_m'] ?? null) ? max(0.0, (float) $segment['distance_m']) : (count($coordinates) >= 2 ? atividadeDistanciaRota($coordinates) : 0.0);
        $duration = max(0.0, (float) ($segment['duration_s'] ?? 0));
        $elevation = is_numeric($segment['elevation_gain_m'] ?? null) ? max(0.0, (float) $segment['elevation_gain_m']) : null;
        $measuredTotal += $measuredDistance;
        $durationTotal += $duration;
        $segments[] = [
            'label' => trim((string) ($segment['label'] ?? '')) ?: 'Trecho ' . ($index + 1),
            'coordinates' => $coordinates,
            'measured_distance_m' => $measuredDistance,
            'duration_s' => $duration,
            'elevation_gain_m' => $elevation,
        ];
    }
    if ($segments === []) {
        return [[
            'label' => 'Trecho 1',
            'coordinates' => $allCoordinates,
            'measured_distance_m' => max(0.0, $displayDistanceM),
            'distance_m' => max(0.0, $displayDistanceM),
            'duration_s' => max(0.0, $displayDurationS),
            'elevation_gain_m' => $displayElevationM,
        ]];
    }

    $distanceScale = $measuredTotal > 0 && $displayDistanceM >= 0 ? $displayDistanceM / $measuredTotal : 1.0;
    $durationScale = $durationTotal > 0 && $displayDurationS >= 0 ? $displayDurationS / $durationTotal : 1.0;
    $segmentElevationTotal = array_sum(array_map(static fn(array $segment): float => (float) ($segment['elevation_gain_m'] ?? 0), $segments));
    $elevationScale = $displayElevationM !== null && $segmentElevationTotal > 0 ? $displayElevationM / $segmentElevationTotal : 1.0;

    foreach ($segments as &$segment) {
        $segment['distance_m'] = max(0.0, $segment['measured_distance_m'] * $distanceScale);
        $segment['duration_s'] = max(0.0, round((float) $segment['duration_s'] * $durationScale, 3));
        if ($displayElevationM === null) {
            $segment['elevation_gain_m'] = null;
        } elseif ($segment['elevation_gain_m'] !== null) {
            $segment['elevation_gain_m'] = max(0.0, $segment['elevation_gain_m'] * $elevationScale);
        }
    }
    unset($segment);
    return $segments;
}

function gpsWebBuildActivityPayload(PDO $pdo, string $idUsuario, array $recording): array
{
    $idModalidade = trim((string) ($recording['idmodalidade'] ?? ''));
    if ($idModalidade === '') throw new InvalidArgumentException('Escolha a modalidade da atividade.');
    $model = atividadeBuscarModeloPadraoModalidade($pdo, $idModalidade, $idUsuario);
    if ($model === [] || empty($model['permite_rota'])) throw new InvalidArgumentException('Esta modalidade não está disponível para gravação GPS.');
    $fields = atividadeBuscarCamposModelo($pdo, (string) $model['idmodelo']);

    $points = is_array($recording['points'] ?? null) ? $recording['points'] : [];
    $coordinates = gpsWebValidatePoints($points);
    $geojson = ['type' => 'LineString', 'coordinates' => $coordinates];
    $measuredDistanceM = max(0.0, (float) ($recording['measured_distance_m'] ?? atividadeDistanciaRota($coordinates)));
    $displayDistanceM = is_numeric($recording['distance_m'] ?? null) ? max(0.0, (float) $recording['distance_m']) : $measuredDistanceM;
    $displayDurationS = max(0.001, round((float) ($recording['duration_s'] ?? 0), 3));
    if ($displayDurationS > 604800) throw new InvalidArgumentException('A duração da atividade é inválida.');
    $displayElevationM = is_numeric($recording['elevation_gain_m'] ?? null) ? max(0.0, (float) $recording['elevation_gain_m']) : null;

    $startedAtMs = (int) ($recording['started_at_ms'] ?? 0);
    if ($startedAtMs <= 0) throw new InvalidArgumentException('O horário inicial da gravação é inválido.');
    $started = (new DateTimeImmutable('@' . intdiv($startedAtMs, 1000)))->setTimezone(new DateTimeZone('America/Sao_Paulo'));
    $endedAtMs = max($startedAtMs, (int) ($recording['ended_at_ms'] ?? ($startedAtMs + (int) round($displayDurationS * 1000))));
    $ended = (new DateTimeImmutable('@' . intdiv($endedAtMs, 1000)))->setTimezone(new DateTimeZone('America/Sao_Paulo'));

    $segments = gpsWebValidateSegments(
        is_array($recording['segments'] ?? null) ? $recording['segments'] : [],
        $coordinates,
        $displayDistanceM,
        $displayDurationS,
        $displayElevationM
    );
    if (empty($model['permite_multiplas_unidades']) && count($segments) > 1) {
        $segments = [[
            'label' => (string) ($model['rotulo_unidade'] ?? 'Trecho') . ' 1',
            'coordinates' => $coordinates,
            'distance_m' => $displayDistanceM,
            'duration_s' => $displayDurationS,
            'elevation_gain_m' => $displayElevationM,
        ]];
    }

    $units = [];
    foreach ($segments as $index => $segment) {
        $unit = [
            'rotulo' => (string) ($segment['label'] ?? ('Trecho ' . ($index + 1))),
            'values' => gpsWebBuildFieldValues($fields, 'unidade', (float) ($segment['distance_m'] ?? 0), (float) ($segment['duration_s'] ?? 0), isset($segment['elevation_gain_m']) ? (float) $segment['elevation_gain_m'] : null),
        ];
        $segmentCoords = is_array($segment['coordinates'] ?? null) ? $segment['coordinates'] : [];
        if (count($segmentCoords) >= 2) {
            $unit['rota_coordenadas'] = ['type' => 'LineString', 'coordinates' => $segmentCoords];
            $unit['rota_modo'] = 'gps';
            $unit['rota_metricas'] = [
                'ganho_elevacao_m' => $segment['elevation_gain_m'] ?? null,
                'fonte_elevacao' => 'gps_web_dispositivo',
            ];
        }
        $units[] = $unit;
    }

    return [
        'idmodelo' => (string) $model['idmodelo'],
        'titulo' => trim((string) ($recording['title'] ?? '')),
        'observacoes' => trim((string) ($recording['notes'] ?? '')),
        'data_inicio' => $started->format('Y-m-d H:i'),
        'data_fim' => $ended->format('Y-m-d H:i'),
        'status' => 'concluido',
        'visibilidade' => trim((string) ($recording['visibility'] ?? '')),
        'ocultar_inicio_m' => max(0, min(10000, (int) ($recording['hide_route_start_m'] ?? 0))),
        'ocultar_fim_m' => max(0, min(10000, (int) ($recording['hide_route_end_m'] ?? 0))),
        'esforco_percebido' => trim((string) ($recording['effort'] ?? '')),
        'origem' => 'gps',
        'record_values' => gpsWebBuildFieldValues($fields, 'registro', $displayDistanceM, $displayDurationS, $displayElevationM),
        'unidades' => $units,
        'rota_coordenadas' => $geojson,
        'rota_modo' => 'gps',
        'rota_metricas' => [
            'distancia_metros' => $displayDistanceM,
            'ganho_elevacao_m' => $displayElevationM,
            'elevacao_min_m' => is_numeric($recording['elevation_min_m'] ?? null) ? (float) $recording['elevation_min_m'] : null,
            'elevacao_max_m' => is_numeric($recording['elevation_max_m'] ?? null) ? (float) $recording['elevation_max_m'] : null,
            'fonte_elevacao' => 'gps_web_dispositivo',
        ],
        '_gps_meta' => [
            'recording_key' => gpsWebRecordingKey($recording),
            'started_at' => gmdate('c', intdiv($startedAtMs, 1000)),
            'ended_at' => gmdate('c', intdiv($endedAtMs, 1000)),
            'measured_distance_m' => $measuredDistanceM,
            'display_distance_m' => $displayDistanceM,
            'duration_s' => $displayDurationS,
            'points_received' => max(0, (int) ($recording['points_received'] ?? count($points))),
            'points_accepted' => count($coordinates),
            'points_rejected' => max(0, (int) ($recording['points_rejected'] ?? 0)),
            'accuracy_avg_m' => is_numeric($recording['accuracy_avg_m'] ?? null) ? max(0.0, (float) $recording['accuracy_avg_m']) : null,
            'accuracy_best_m' => is_numeric($recording['accuracy_best_m'] ?? null) ? max(0.0, (float) $recording['accuracy_best_m']) : null,
            'accuracy_worst_m' => is_numeric($recording['accuracy_worst_m'] ?? null) ? max(0.0, (float) $recording['accuracy_worst_m']) : null,
            'visibility_gaps' => max(0, (int) ($recording['visibility_gaps'] ?? 0)),
            'goal_type' => in_array((string) ($recording['goal_type'] ?? ''), ['distance', 'time'], true) ? (string) $recording['goal_type'] : null,
            'goal_value' => is_numeric($recording['goal_value'] ?? null) ? max(0.0, (float) $recording['goal_value']) : null,
            'ended_by_goal' => !empty($recording['ended_by_goal']),
            'user_adjusted' => abs($displayDistanceM - $measuredDistanceM) > 0.5 || !empty($recording['user_adjusted']),
        ],
    ];
}

function gpsWebSaveMetadata(PDO $pdo, string $idRegistro, array $meta): void
{
    $stmt = $pdo->prepare(
            'INSERT INTO gravacoes_gps_web
             (idregistro, chave_gravacao, iniciado_em, finalizado_em, distancia_medida_m, distancia_final_m, duracao_s,
              pontos_recebidos, pontos_aceitos, pontos_rejeitados, precisao_media_m, precisao_melhor_m,
              precisao_pior_m, lacunas_visibilidade, tipo_meta, valor_meta, finalizado_por_meta, usuario_ajustou)
             VALUES (:registro, :chave, CAST(:inicio AS timestamptz), CAST(:fim AS timestamptz), :medida, :final, :duracao,
                     :recebidos, :aceitos, :rejeitados, :media, :melhor, :pior, :lacunas, :tipo_meta, :valor_meta,
                     :por_meta, :ajustou)
             ON CONFLICT (idregistro) DO UPDATE SET
                chave_gravacao=EXCLUDED.chave_gravacao, finalizado_em=EXCLUDED.finalizado_em, distancia_medida_m=EXCLUDED.distancia_medida_m,
                distancia_final_m=EXCLUDED.distancia_final_m, duracao_s=EXCLUDED.duracao_s,
                pontos_recebidos=EXCLUDED.pontos_recebidos, pontos_aceitos=EXCLUDED.pontos_aceitos,
                pontos_rejeitados=EXCLUDED.pontos_rejeitados, precisao_media_m=EXCLUDED.precisao_media_m,
                precisao_melhor_m=EXCLUDED.precisao_melhor_m, precisao_pior_m=EXCLUDED.precisao_pior_m,
                lacunas_visibilidade=EXCLUDED.lacunas_visibilidade, tipo_meta=EXCLUDED.tipo_meta,
                valor_meta=EXCLUDED.valor_meta, finalizado_por_meta=EXCLUDED.finalizado_por_meta,
                usuario_ajustou=EXCLUDED.usuario_ajustou'
        );
    $stmt->execute([
        ':registro' => $idRegistro,
        ':chave' => $meta['recording_key'] ?? '',
        ':inicio' => $meta['started_at'] ?? null,
        ':fim' => $meta['ended_at'] ?? null,
        ':medida' => $meta['measured_distance_m'] ?? null,
        ':final' => $meta['display_distance_m'] ?? null,
        ':duracao' => $meta['duration_s'] ?? null,
        ':recebidos' => $meta['points_received'] ?? 0,
        ':aceitos' => $meta['points_accepted'] ?? 0,
        ':rejeitados' => $meta['points_rejected'] ?? 0,
        ':media' => $meta['accuracy_avg_m'] ?? null,
        ':melhor' => $meta['accuracy_best_m'] ?? null,
        ':pior' => $meta['accuracy_worst_m'] ?? null,
        ':lacunas' => $meta['visibility_gaps'] ?? 0,
        ':tipo_meta' => $meta['goal_type'] ?? null,
        ':valor_meta' => $meta['goal_value'] ?? null,
        ':por_meta' => !empty($meta['ended_by_goal']) ? 1 : 0,
        ':ajustou' => !empty($meta['user_adjusted']) ? 1 : 0,
    ]);
}
