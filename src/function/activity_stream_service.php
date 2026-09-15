<?php

declare(strict_types=1);

final class ActivityStreamIdempotencyConflictException extends RuntimeException
{
}

const ACTIVITY_STREAM_SCHEMA_VERSION = 1;
const ACTIVITY_STREAM_MAX_SAMPLES = 50000;
const ACTIVITY_STREAM_MAX_READ_POINTS = 5000;

function activityStreamId(int $length = 21): string
{
    if (function_exists('atividadeGerarId')) return atividadeGerarId();
    if (function_exists('stridebr_api_id')) return stridebr_api_id($length);
    $bytes = random_bytes((int) ceil($length * 3 / 4) + 2);
    return substr(rtrim(strtr(base64_encode($bytes), '+/', '-_'), '='), 0, $length);
}

function activityStreamIso(?string $value): ?string
{
    if ($value === null || trim($value) === '') return null;
    try { return (new DateTimeImmutable($value))->format(DateTimeInterface::ATOM); }
    catch (Throwable) { return null; }
}

function activityStreamBool(mixed $value): bool
{
    return $value === true || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
}

function activityStreamJsonCanonicalize(mixed $value): mixed
{
    if (!is_array($value)) return $value;
    if (array_is_list($value)) return array_map('activityStreamJsonCanonicalize', $value);
    ksort($value);
    foreach ($value as $key => $item) $value[$key] = activityStreamJsonCanonicalize($item);
    return $value;
}

function activityStreamPayloadHash(array $payload): string
{
    return hash('sha256', json_encode(activityStreamJsonCanonicalize($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
}

function activityStreamMetadataObject(mixed $value, string $field): array
{
    if ($value === null) return [];
    if (!is_array($value) || ($value !== [] && array_is_list($value))) throw new InvalidArgumentException($field . ' precisa ser um objeto.');
    return $value;
}

function activityStreamEncodeJsonObject(array $value): string
{
    return json_encode($value === [] ? (object) [] : $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function activityStreamOwner(PDO $pdo, string $userId, string $activityId): array
{
    $stmt = $pdo->prepare("SELECT ra.idregistro,ra.idusuario,ra.idmodalidade,ra.data_inicio,ra.data_fim,ra.status,ra.origem,ra.origem_provedor,m.slug AS modalidade_slug,m.nome AS modalidade_nome,m.familia_hub,m.metrica_derivada,m.permite_rota FROM registros_atividade ra JOIN modalidades m ON m.idmodalidade=ra.idmodalidade WHERE ra.idregistro=:id AND ra.idusuario=:user AND ra.excluido_em IS NULL LIMIT 1");
    $stmt->execute([':id' => $activityId, ':user' => $userId]);
    $row = $stmt->fetch();
    if (!$row) throw new InvalidArgumentException('Atividade não encontrada.');
    return $row;
}

function activityStreamFinite(mixed $value): ?float
{
    if (!is_numeric($value)) return null;
    $number = (float) $value;
    return is_finite($number) ? $number : null;
}

function activityStreamMedian(array $values): ?float
{
    $values = array_values(array_filter($values, static fn(mixed $value): bool => is_numeric($value) && is_finite((float) $value)));
    if ($values === []) return null;
    sort($values, SORT_NUMERIC);
    $count = count($values);
    $middle = intdiv($count, 2);
    return $count % 2 === 1 ? (float) $values[$middle] : ((float) $values[$middle - 1] + (float) $values[$middle]) / 2.0;
}

function activityStreamNormalizeSamples(array $samples): array
{
    if ($samples === []) return [];
    if (count($samples) > ACTIVITY_STREAM_MAX_SAMPLES) throw new InvalidArgumentException('O bundle excede 50000 samples.');
    $normalized = [];
    $previousElapsed = -1;
    $previousMoving = -1;
    $previousDistance = -1.0;
    foreach ($samples as $index => $sample) {
        if (!is_array($sample)) throw new InvalidArgumentException('Cada sample precisa ser um objeto.');
        $elapsed = filter_var($sample['elapsed_ms'] ?? null, FILTER_VALIDATE_INT);
        if ($elapsed === false || $elapsed < 0) throw new InvalidArgumentException('samples.elapsed_ms precisa ser inteiro não negativo.');
        if ($elapsed < $previousElapsed) throw new InvalidArgumentException('samples.elapsed_ms precisa ser monotônico.');
        $moving = array_key_exists('moving_ms', $sample) && $sample['moving_ms'] !== null ? filter_var($sample['moving_ms'], FILTER_VALIDATE_INT) : null;
        if ($moving === false || ($moving !== null && $moving < 0)) throw new InvalidArgumentException('samples.moving_ms precisa ser inteiro não negativo.');
        if ($moving !== null && $moving > $elapsed) throw new InvalidArgumentException('samples.moving_ms não pode ultrapassar elapsed_ms.');
        if ($moving !== null && $moving < $previousMoving) throw new InvalidArgumentException('samples.moving_ms precisa ser monotônico.');
        $distance = activityStreamFinite($sample['distance_m'] ?? null);
        if ($distance !== null && $distance < 0) throw new InvalidArgumentException('samples.distance_m precisa ser não negativo.');
        if ($distance !== null && $previousDistance >= 0 && $distance + 0.5 < $previousDistance) throw new InvalidArgumentException('samples.distance_m precisa ser monotônico.');
        $speed = activityStreamFinite($sample['speed_mps'] ?? $sample['speed'] ?? null);
        if ($speed !== null && ($speed < 0 || $speed > 60)) throw new InvalidArgumentException('samples.speed_mps está fora do intervalo aceito.');
        $hr = array_key_exists('heart_rate_bpm', $sample) ? filter_var($sample['heart_rate_bpm'], FILTER_VALIDATE_INT) : (array_key_exists('heart_rate', $sample) ? filter_var($sample['heart_rate'], FILTER_VALIDATE_INT) : null);
        if ($hr === false || ($hr !== null && ($hr < 20 || $hr > 260))) throw new InvalidArgumentException('samples.heart_rate_bpm precisa ficar entre 20 e 260 bpm.');
        $cadence = activityStreamFinite($sample['cadence'] ?? null);
        if ($cadence !== null && ($cadence < 0 || $cadence > 400)) throw new InvalidArgumentException('samples.cadence está fora do intervalo aceito.');
        $power = activityStreamFinite($sample['power_w'] ?? $sample['power'] ?? null);
        if ($power !== null && ($power < 0 || $power > 5000)) throw new InvalidArgumentException('samples.power_w está fora do intervalo aceito.');
        $temperature = activityStreamFinite($sample['temperature_c'] ?? null);
        if ($temperature !== null && ($temperature < -100 || $temperature > 100)) throw new InvalidArgumentException('samples.temperature_c está fora do intervalo aceito.');
        $altitude = activityStreamFinite($sample['altitude_m'] ?? null);
        $horizontalAccuracy = activityStreamFinite($sample['horizontal_accuracy_m'] ?? $sample['accuracy_m'] ?? null);
        $verticalAccuracy = activityStreamFinite($sample['vertical_accuracy_m'] ?? null);
        $speedAccuracy = activityStreamFinite($sample['speed_accuracy_mps'] ?? null);
        foreach ([$horizontalAccuracy, $verticalAccuracy, $speedAccuracy] as $accuracy) {
            if ($accuracy !== null && $accuracy < 0) throw new InvalidArgumentException('Accuracy precisa ser não negativa.');
        }
        $bearing = activityStreamFinite($sample['bearing_deg'] ?? null);
        if ($bearing !== null && ($bearing < 0 || $bearing >= 360)) throw new InvalidArgumentException('samples.bearing_deg precisa ficar entre 0 e 360.');
        $gap = array_key_exists('gap_before_ms', $sample) && $sample['gap_before_ms'] !== null ? filter_var($sample['gap_before_ms'], FILTER_VALIDATE_INT) : null;
        if ($gap === false || ($gap !== null && $gap < 0)) throw new InvalidArgumentException('samples.gap_before_ms precisa ser não negativo.');
        $routeIndex = array_key_exists('route_point_index', $sample) && $sample['route_point_index'] !== null ? filter_var($sample['route_point_index'], FILTER_VALIDATE_INT) : null;
        if ($routeIndex === false || ($routeIndex !== null && $routeIndex < 0)) throw new InvalidArgumentException('samples.route_point_index precisa ser não negativo.');
        $source = trim((string) ($sample['source'] ?? ''));
        if ($source !== '' && strlen($source) > 40) throw new InvalidArgumentException('samples.source é muito longo.');
        $normalized[] = [
            'sample_index' => $index,
            'elapsed_ms' => (int) $elapsed,
            'moving_ms' => $moving !== null ? (int) $moving : null,
            'distance_m' => $distance,
            'speed_mps' => $speed,
            'heart_rate_bpm' => $hr !== null ? (int) $hr : null,
            'altitude_m' => $altitude,
            'grade_pct' => null,
            'cadence' => $cadence,
            'power_w' => $power,
            'temperature_c' => $temperature,
            'horizontal_accuracy_m' => $horizontalAccuracy,
            'vertical_accuracy_m' => $verticalAccuracy,
            'speed_accuracy_mps' => $speedAccuracy,
            'bearing_deg' => $bearing,
            'gap_before_ms' => $gap !== null ? (int) $gap : null,
            'route_point_index' => $routeIndex !== null ? (int) $routeIndex : null,
            'sample_source' => $source !== '' ? $source : null,
        ];
        $previousElapsed = (int) $elapsed;
        if ($moving !== null) $previousMoving = (int) $moving;
        if ($distance !== null) $previousDistance = $distance;
    }
    activityStreamDeriveCanonicalMetrics($normalized);
    return $normalized;
}

function activityStreamDeriveCanonicalMetrics(array &$samples): void
{
    $previous = null;
    foreach ($samples as &$sample) {
        if ($sample['moving_ms'] === null) $sample['moving_ms'] = $sample['elapsed_ms'];
        if ($sample['speed_mps'] === null && $previous !== null && $sample['distance_m'] !== null && $previous['distance_m'] !== null) {
            $distanceDelta = $sample['distance_m'] - $previous['distance_m'];
            $timeDelta = ($sample['moving_ms'] - $previous['moving_ms']) / 1000.0;
            if ($distanceDelta >= 0 && $timeDelta > 0 && $distanceDelta / $timeDelta <= 60) $sample['speed_mps'] = $distanceDelta / $timeDelta;
        }
        $previous = $sample;
    }
    unset($sample);
    $count = count($samples);
    for ($index = 0; $index < $count - 1; $index++) {
        if ($samples[$index]['speed_mps'] !== null) continue;
        $next = $samples[$index + 1];
        if (($next['gap_before_ms'] ?? null) !== null && (int) $next['gap_before_ms'] > 0) continue;
        if ($samples[$index]['distance_m'] === null || $next['distance_m'] === null) continue;
        if ($samples[$index]['moving_ms'] === null || $next['moving_ms'] === null) continue;
        $distanceDelta = (float) $next['distance_m'] - (float) $samples[$index]['distance_m'];
        $timeDelta = ((int) $next['moving_ms'] - (int) $samples[$index]['moving_ms']) / 1000.0;
        if ($distanceDelta >= 0 && $timeDelta > 0 && $distanceDelta / $timeDelta <= 60) {
            $samples[$index]['speed_mps'] = $distanceDelta / $timeDelta;
        }
    }
    $altitudes = array_map(static fn(array $sample): ?float => $sample['altitude_m'], $samples);
    $smooth = [];
    foreach ($altitudes as $index => $value) {
        if ($value === null) {
            $smooth[$index] = null;
            continue;
        }
        $window = [];
        for ($i = max(0, $index - 2); $i <= min(count($altitudes) - 1, $index + 2); $i++) if ($altitudes[$i] !== null) $window[] = $altitudes[$i];
        $smooth[$index] = activityStreamMedian($window);
    }
    foreach ($samples as $index => &$sample) {
        if ($sample['distance_m'] === null || $smooth[$index] === null) continue;
        $left = $index;
        while ($left > 0) {
            $candidateDistance = $samples[$left]['distance_m'];
            if ($candidateDistance !== null && $sample['distance_m'] - $candidateDistance >= 20) break;
            $left--;
        }
        $right = $index;
        while ($right < count($samples) - 1) {
            $candidateDistance = $samples[$right]['distance_m'];
            if ($candidateDistance !== null && $candidateDistance - $sample['distance_m'] >= 20) break;
            $right++;
        }
        $candidates = [];
        foreach ([$left, $right] as $candidate) {
            if ($candidate === $index || $samples[$candidate]['distance_m'] === null || $smooth[$candidate] === null) continue;
            $span = abs((float) $samples[$candidate]['distance_m'] - (float) $sample['distance_m']);
            if ($span >= 20) $candidates[] = [$span, $candidate];
        }
        if ($candidates === []) continue;
        usort($candidates, static fn(array $a, array $b): int => abs($a[0] - 30) <=> abs($b[0] - 30));
        $candidate = $candidates[0][1];
        $distanceDelta = (float) $sample['distance_m'] - (float) $samples[$candidate]['distance_m'];
        $altitudeDelta = (float) $smooth[$index] - (float) $smooth[$candidate];
        if (abs($distanceDelta) >= 20) $sample['grade_pct'] = max(-100.0, min(100.0, $altitudeDelta / $distanceDelta * 100.0));
    }
    unset($sample);
}

function activityStreamAvailableStreams(array $samples): array
{
    $map = [
        'elapsed_time' => 'elapsed_ms', 'moving_time' => 'moving_ms', 'distance' => 'distance_m', 'speed' => 'speed_mps',
        'heart_rate' => 'heart_rate_bpm', 'altitude' => 'altitude_m', 'elevation' => 'altitude_m', 'grade' => 'grade_pct',
        'cadence' => 'cadence', 'power' => 'power_w', 'temperature' => 'temperature_c',
    ];
    $available = ['elapsed_time'];
    foreach ($map as $name => $field) {
        if ($name === 'elapsed_time') continue;
        foreach ($samples as $sample) {
            if ($sample[$field] !== null) {
                $available[] = $name;
                break;
            }
        }
    }
    if (in_array('speed', $available, true)) $available[] = 'pace';
    return array_values(array_unique($available));
}

function activityStreamSaveBundle(PDO $pdo, string $userId, string $activityId, array $payload, ?string $idempotencyKey = null, ?string $defaultSource = null): array
{
    activityStreamOwner($pdo, $userId, $activityId);
    $version = filter_var($payload['schema_version'] ?? ACTIVITY_STREAM_SCHEMA_VERSION, FILTER_VALIDATE_INT);
    if ($version !== ACTIVITY_STREAM_SCHEMA_VERSION) throw new InvalidArgumentException('schema_version de streams não suportada.');
    $rawSamples = $payload['samples'] ?? null;
    if (!is_array($rawSamples) || !array_is_list($rawSamples)) throw new InvalidArgumentException('streams.samples precisa ser uma lista.');
    $samples = activityStreamNormalizeSamples($rawSamples);
    $source = trim((string) ($payload['source'] ?? $defaultSource ?? ''));
    if ($source !== '' && strlen($source) > 40) throw new InvalidArgumentException('streams.source é muito longo.');
    $sourceMetadata = activityStreamMetadataObject($payload['source_metadata'] ?? null, 'streams.source_metadata');
    $canonicalPayload = ['schema_version' => ACTIVITY_STREAM_SCHEMA_VERSION, 'source' => $source ?: null, 'source_metadata' => $sourceMetadata, 'samples' => $samples];
    $payloadHash = activityStreamPayloadHash($canonicalPayload);
    $idempotencyHash = $idempotencyKey !== null && trim($idempotencyKey) !== '' ? hash('sha256', $idempotencyKey) : null;
    $existingStmt = $pdo->prepare('SELECT idbundle,idempotency_key_hash,payload_hash FROM activity_stream_bundles WHERE idregistro=:id LIMIT 1 FOR UPDATE');
    $existingStmt->execute([':id' => $activityId]);
    $existing = $existingStmt->fetch();
    if ($existing && $idempotencyHash !== null && hash_equals((string) ($existing['idempotency_key_hash'] ?? ''), $idempotencyHash)) {
        if (!hash_equals((string) $existing['payload_hash'], $payloadHash)) throw new ActivityStreamIdempotencyConflictException('Idempotency-Key já foi usada com outro bundle de streams.');
        return ['bundle_id' => (string) $existing['idbundle'], 'reused' => true, 'sample_count' => count($samples), 'available_streams' => activityStreamAvailableStreams($samples)];
    }
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $bundleId = $existing ? (string) $existing['idbundle'] : activityStreamId();
        $available = activityStreamAvailableStreams($samples);
        $bundle = $pdo->prepare("INSERT INTO activity_stream_bundles (idbundle,idregistro,schema_version,source,source_metadata,idempotency_key_hash,payload_hash,sample_count,available_streams,data_atualizacao) VALUES (:bundle,:record,:version,:source,CAST(:metadata AS jsonb),:idempotency,:hash,:count,CAST(:available AS jsonb),NOW()) ON CONFLICT (idregistro) DO UPDATE SET schema_version=EXCLUDED.schema_version,source=EXCLUDED.source,source_metadata=EXCLUDED.source_metadata,idempotency_key_hash=EXCLUDED.idempotency_key_hash,payload_hash=EXCLUDED.payload_hash,sample_count=EXCLUDED.sample_count,available_streams=EXCLUDED.available_streams,data_atualizacao=NOW() RETURNING idbundle");
        $bundle->execute([
            ':bundle' => $bundleId, ':record' => $activityId, ':version' => ACTIVITY_STREAM_SCHEMA_VERSION, ':source' => $source !== '' ? $source : null,
            ':metadata' => activityStreamEncodeJsonObject($sourceMetadata), ':idempotency' => $idempotencyHash,
            ':hash' => $payloadHash, ':count' => count($samples), ':available' => json_encode($available, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
        $bundleId = (string) $bundle->fetchColumn();
        $pdo->prepare('DELETE FROM activity_stream_samples WHERE idbundle=:bundle')->execute([':bundle' => $bundleId]);
        if ($samples !== []) {
            $insert = $pdo->prepare('INSERT INTO activity_stream_samples (idbundle,sample_index,elapsed_ms,moving_ms,distance_m,speed_mps,heart_rate_bpm,altitude_m,grade_pct,cadence,power_w,temperature_c,horizontal_accuracy_m,vertical_accuracy_m,speed_accuracy_mps,bearing_deg,gap_before_ms,route_point_index,sample_source) VALUES (:bundle,:idx,:elapsed,:moving,:distance,:speed,:hr,:altitude,:grade,:cadence,:power,:temperature,:hacc,:vacc,:sacc,:bearing,:gap,:route_idx,:source)');
            foreach ($samples as $sample) {
                $insert->execute([
                    ':bundle' => $bundleId, ':idx' => $sample['sample_index'], ':elapsed' => $sample['elapsed_ms'], ':moving' => $sample['moving_ms'], ':distance' => $sample['distance_m'], ':speed' => $sample['speed_mps'], ':hr' => $sample['heart_rate_bpm'], ':altitude' => $sample['altitude_m'], ':grade' => $sample['grade_pct'], ':cadence' => $sample['cadence'], ':power' => $sample['power_w'], ':temperature' => $sample['temperature_c'], ':hacc' => $sample['horizontal_accuracy_m'], ':vacc' => $sample['vertical_accuracy_m'], ':sacc' => $sample['speed_accuracy_mps'], ':bearing' => $sample['bearing_deg'], ':gap' => $sample['gap_before_ms'], ':route_idx' => $sample['route_point_index'], ':source' => $sample['sample_source'],
                ]);
            }
        }
        $pdo->prepare('DELETE FROM activity_analysis_cache WHERE idregistro=:id')->execute([':id' => $activityId]);
        if ($ownsTransaction) $pdo->commit();
        return ['bundle_id' => $bundleId, 'reused' => false, 'sample_count' => count($samples), 'available_streams' => $available];
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function activityStreamRows(PDO $pdo, string $userId, string $activityId): array
{
    activityStreamOwner($pdo, $userId, $activityId);
    $stmt = $pdo->prepare('SELECT b.idbundle,b.schema_version,b.source,b.source_metadata,b.available_streams,b.sample_count,b.data_atualizacao,s.* FROM activity_stream_bundles b LEFT JOIN activity_stream_samples s ON s.idbundle=b.idbundle WHERE b.idregistro=:id ORDER BY s.sample_index');
    $stmt->execute([':id' => $activityId]);
    $rows = $stmt->fetchAll();
    if ($rows === []) return ['bundle' => null, 'samples' => []];
    $first = $rows[0];
    $metadata = is_array($first['source_metadata'] ?? null) ? $first['source_metadata'] : json_decode((string) ($first['source_metadata'] ?? '{}'), true);
    $available = is_array($first['available_streams'] ?? null) ? $first['available_streams'] : json_decode((string) ($first['available_streams'] ?? '[]'), true);
    $samples = [];
    foreach ($rows as $row) {
        if ($row['sample_index'] === null) continue;
        $samples[] = [
            'sample_index' => (int) $row['sample_index'], 'elapsed_ms' => (int) $row['elapsed_ms'], 'moving_ms' => $row['moving_ms'] !== null ? (int) $row['moving_ms'] : null,
            'distance_m' => $row['distance_m'] !== null ? (float) $row['distance_m'] : null, 'speed_mps' => $row['speed_mps'] !== null ? (float) $row['speed_mps'] : null,
            'heart_rate_bpm' => $row['heart_rate_bpm'] !== null ? (int) $row['heart_rate_bpm'] : null, 'altitude_m' => $row['altitude_m'] !== null ? (float) $row['altitude_m'] : null,
            'grade_pct' => $row['grade_pct'] !== null ? (float) $row['grade_pct'] : null, 'cadence' => $row['cadence'] !== null ? (float) $row['cadence'] : null,
            'power_w' => $row['power_w'] !== null ? (float) $row['power_w'] : null, 'temperature_c' => $row['temperature_c'] !== null ? (float) $row['temperature_c'] : null,
            'horizontal_accuracy_m' => $row['horizontal_accuracy_m'] !== null ? (float) $row['horizontal_accuracy_m'] : null, 'vertical_accuracy_m' => $row['vertical_accuracy_m'] !== null ? (float) $row['vertical_accuracy_m'] : null,
            'speed_accuracy_mps' => $row['speed_accuracy_mps'] !== null ? (float) $row['speed_accuracy_mps'] : null, 'bearing_deg' => $row['bearing_deg'] !== null ? (float) $row['bearing_deg'] : null,
            'gap_before_ms' => $row['gap_before_ms'] !== null ? (int) $row['gap_before_ms'] : null, 'route_point_index' => $row['route_point_index'] !== null ? (int) $row['route_point_index'] : null,
            'source' => $row['sample_source'] !== null ? (string) $row['sample_source'] : null,
        ];
    }
    return ['bundle' => ['id' => (string) $first['idbundle'], 'schema_version' => (int) $first['schema_version'], 'source' => $first['source'] !== null ? (string) $first['source'] : null, 'source_metadata' => is_array($metadata) ? $metadata : [], 'available_streams' => is_array($available) ? array_values($available) : [], 'sample_count' => (int) $first['sample_count'], 'updated_at' => activityStreamIso((string) $first['data_atualizacao'])], 'samples' => $samples];
}

function activityStreamLttbIndices(array $points, int $threshold, string $xField, string $yField): array
{
    $count = count($points);
    if ($threshold >= $count || $threshold < 3) return array_keys($points);
    $result = [0];
    $every = ($count - 2) / ($threshold - 2);
    $a = 0;
    for ($i = 0; $i < $threshold - 2; $i++) {
        $avgStart = (int) floor(($i + 1) * $every) + 1;
        $avgEnd = min($count, (int) floor(($i + 2) * $every) + 1);
        $avgX = 0.0;
        $avgY = 0.0;
        $avgCount = max(1, $avgEnd - $avgStart);
        for ($j = $avgStart; $j < $avgEnd; $j++) {
            $avgX += (float) ($points[$j][$xField] ?? 0);
            $avgY += (float) ($points[$j][$yField] ?? 0);
        }
        $avgX /= $avgCount;
        $avgY /= $avgCount;
        $rangeStart = (int) floor($i * $every) + 1;
        $rangeEnd = min($count - 1, (int) floor(($i + 1) * $every) + 1);
        $pointAX = (float) ($points[$a][$xField] ?? 0);
        $pointAY = (float) ($points[$a][$yField] ?? 0);
        $maxArea = -1.0;
        $nextA = $rangeStart;
        for ($j = $rangeStart; $j < $rangeEnd; $j++) {
            $area = abs(($pointAX - $avgX) * ((float) ($points[$j][$yField] ?? 0) - $pointAY) - ($pointAX - (float) ($points[$j][$xField] ?? 0)) * ($avgY - $pointAY));
            if ($area > $maxArea) {
                $maxArea = $area;
                $nextA = $j;
            }
        }
        $result[] = $nextA;
        $a = $nextA;
    }
    $result[] = $count - 1;
    return array_values(array_unique($result));
}

function activityStreamDownsample(array $samples, int $maxPoints, string $axis, array $streams): array
{
    if (count($samples) <= $maxPoints) return $samples;
    $xField = $axis === 'distance' ? 'distance_m' : 'elapsed_ms';
    $priority = ['pace' => 'pace_s_per_km', 'heart_rate' => 'heart_rate_bpm', 'altitude' => 'altitude_m', 'elevation' => 'altitude_m', 'cadence' => 'cadence', 'speed' => 'speed_mps', 'power' => 'power_w', 'grade' => 'grade_pct'];
    $work = [];
    foreach ($samples as $sample) {
        $copy = $sample;
        $copy['pace_s_per_km'] = is_numeric($sample['speed_mps'] ?? null) && (float) $sample['speed_mps'] > 0 ? 1000.0 / (float) $sample['speed_mps'] : null;
        $work[] = $copy;
    }
    $yField = null;
    foreach ($streams as $stream) {
        $candidate = $priority[$stream] ?? null;
        if ($candidate === null) continue;
        foreach ($work as $row) if (is_numeric($row[$candidate] ?? null)) { $yField = $candidate; break 2; }
    }
    if ($yField === null) $yField = $axis === 'distance' ? 'elapsed_ms' : 'distance_m';
    foreach ($work as &$row) if (!is_numeric($row[$yField] ?? null)) $row[$yField] = 0.0;
    unset($row);
    $indices = activityStreamLttbIndices($work, $maxPoints, $xField, $yField);
    $extremaFields = [];
    foreach ($streams as $stream) {
        $candidate = $priority[$stream] ?? null;
        if ($candidate !== null) $extremaFields[] = $candidate;
    }
    $forced = [0, count($work) - 1];
    foreach (array_unique($extremaFields) as $field) {
        $minIndex = $maxIndex = null;
        $min = INF;
        $max = -INF;
        foreach ($work as $index => $row) {
            if (!is_numeric($row[$field] ?? null)) continue;
            $value = (float) $row[$field];
            if ($value < $min) { $min = $value; $minIndex = $index; }
            if ($value > $max) { $max = $value; $maxIndex = $index; }
        }
        if ($minIndex !== null) $forced[] = $minIndex;
        if ($maxIndex !== null) $forced[] = $maxIndex;
    }
    $indices = array_values(array_unique(array_merge($indices, $forced)));
    sort($indices, SORT_NUMERIC);
    if (count($indices) > $maxPoints) {
        $forced = array_values(array_unique($forced));
        sort($forced, SORT_NUMERIC);
        $remaining = array_values(array_diff($indices, $forced));
        $needed = max(0, $maxPoints - count($forced));
        if ($needed < count($remaining) && $needed > 0) {
            $picked = [];
            for ($i = 0; $i < $needed; $i++) $picked[] = $remaining[(int) floor($i * count($remaining) / $needed)];
            $indices = array_values(array_unique(array_merge($forced, $picked)));
            sort($indices, SORT_NUMERIC);
        } else {
            $indices = array_slice($indices, 0, $maxPoints);
        }
    }
    return array_values(array_map(static fn(int $index): array => $samples[$index], $indices));
}

function activityStreamRead(PDO $pdo, string $userId, string $activityId, array $query = []): array
{
    $owner = activityStreamOwner($pdo, $userId, $activityId);
    $stored = activityStreamRows($pdo, $userId, $activityId);
    $axis = strtolower(trim((string) ($query['axis'] ?? 'time')));
    if (!in_array($axis, ['time', 'distance'], true)) throw new InvalidArgumentException('axis precisa ser time ou distance.');
    $available = $stored['bundle']['available_streams'] ?? [];
    $requestedRaw = trim((string) ($query['streams'] ?? ''));
    $requested = $requestedRaw === '' ? array_values(array_intersect(['pace', 'speed', 'heart_rate', 'altitude', 'grade', 'cadence', 'power'], $available)) : array_values(array_filter(array_map('trim', explode(',', $requestedRaw))));
    $allowed = ['elapsed_time','moving_time','distance','speed','pace','heart_rate','altitude','elevation','grade','cadence','power','temperature'];
    foreach ($requested as $stream) if (!in_array($stream, $allowed, true)) throw new InvalidArgumentException('Stream não suportado: ' . $stream . '.');
    $resolution = strtolower(trim((string) ($query['resolution'] ?? 'medium')));
    $defaults = ['high' => 2000, 'medium' => 800, 'low' => 300];
    if ($resolution !== 'raw' && !isset($defaults[$resolution])) throw new InvalidArgumentException('resolution precisa ser raw, high, medium ou low.');
    $maxPoints = $resolution === 'raw' ? ACTIVITY_STREAM_MAX_SAMPLES : $defaults[$resolution];
    if (isset($query['max_points']) && $query['max_points'] !== '') {
        $candidate = filter_var($query['max_points'], FILTER_VALIDATE_INT);
        if ($candidate === false || $candidate < 50 || $candidate > ACTIVITY_STREAM_MAX_READ_POINTS) throw new InvalidArgumentException('max_points precisa ficar entre 50 e 5000.');
        $maxPoints = (int) $candidate;
    }
    $samples = $stored['samples'];
    if ($axis === 'distance') $samples = array_values(array_filter($samples, static fn(array $sample): bool => $sample['distance_m'] !== null));
    $returnedRaw = $resolution === 'raw' && !isset($query['max_points']);
    if (!$returnedRaw && count($samples) > $maxPoints) $samples = activityStreamDownsample($samples, $maxPoints, $axis, $requested);
    $data = [];
    $gaps = [];
    foreach ($samples as $sample) {
        $item = ['x' => $axis === 'distance' ? $sample['distance_m'] : $sample['elapsed_ms'], 'elapsed_ms' => $sample['elapsed_ms'], 'moving_ms' => $sample['moving_ms'], 'distance_m' => $sample['distance_m'], 'gap_before_ms' => $sample['gap_before_ms'], 'route_point_index' => $sample['route_point_index']];
        foreach ($requested as $stream) {
            $item[$stream] = match ($stream) {
                'elapsed_time' => $sample['elapsed_ms'], 'moving_time' => $sample['moving_ms'], 'distance' => $sample['distance_m'], 'speed' => $sample['speed_mps'],
                'pace' => $sample['speed_mps'] !== null && $sample['speed_mps'] > 0 ? 1000.0 / $sample['speed_mps'] : null,
                'heart_rate' => $sample['heart_rate_bpm'], 'altitude', 'elevation' => $sample['altitude_m'], 'grade' => $sample['grade_pct'],
                'cadence' => $sample['cadence'], 'power' => $sample['power_w'], 'temperature' => $sample['temperature_c'], default => null,
            };
        }
        if (in_array('pace', $requested, true)) $item['pace_s_per_km'] = $item['pace'];
        if ($sample['gap_before_ms'] !== null && $sample['gap_before_ms'] > 0) $gaps[] = ['at_elapsed_ms' => $sample['elapsed_ms'], 'gap_ms' => $sample['gap_before_ms']];
        $data[] = $item;
    }
    return [
        'activity_id' => $activityId,
        'sport' => ['id' => (string) $owner['idmodalidade'], 'slug' => (string) $owner['modalidade_slug'], 'name' => (string) $owner['modalidade_nome'], 'derived_metric' => (string) ($owner['metrica_derivada'] ?? 'nenhuma')],
        'schema_version' => ACTIVITY_STREAM_SCHEMA_VERSION,
        'axis' => $axis,
        'axis_unit' => $axis === 'distance' ? 'm' : 'ms',
        'resolution' => $returnedRaw ? 'raw' : $resolution,
        'requested_streams' => $requested,
        'available_streams' => $available,
        'source' => $stored['bundle']['source'] ?? null,
        'sample_count_raw' => (int) ($stored['bundle']['sample_count'] ?? 0),
        'sample_count_returned' => count($data),
        'gaps' => $gaps,
        'samples' => $data,
        'units' => ['elapsed_time' => 'ms', 'moving_time' => 'ms', 'distance' => 'm', 'speed' => 'm_s', 'pace' => 's_per_km', 'heart_rate' => 'bpm', 'altitude' => 'm', 'elevation' => 'm', 'grade' => 'percent', 'cadence' => activityStreamCadenceUnit((string) ($owner['modalidade_slug'] ?? ''), (string) ($owner['familia_hub'] ?? '')), 'power' => 'W', 'temperature' => 'celsius'],
    ];
}

function activityStreamCadenceUnit(string $sportSlug, string $family): string
{
    $slug = strtolower($sportSlug);
    if (str_contains($slug, 'cicl') || str_contains($slug, 'bike') || str_contains($slug, 'bmx') || $family === 'cycling') return 'rpm';
    return 'spm';
}

function activityStreamInterpolateAtDistance(array $samples, float $distance): ?array
{
    $previous = null;
    foreach ($samples as $sample) {
        if ($sample['distance_m'] === null) continue;
        if ((float) $sample['distance_m'] === $distance) return $sample;
        if ((float) $sample['distance_m'] > $distance && $previous !== null && $previous['distance_m'] !== null) {
            $span = (float) $sample['distance_m'] - (float) $previous['distance_m'];
            if ($span <= 0) return $previous;
            $ratio = ($distance - (float) $previous['distance_m']) / $span;
            $result = $previous;
            foreach (['elapsed_ms','moving_ms','speed_mps','heart_rate_bpm','altitude_m','grade_pct','cadence','power_w','temperature_c'] as $field) {
                if (is_numeric($previous[$field] ?? null) && is_numeric($sample[$field] ?? null)) $result[$field] = (float) $previous[$field] + ((float) $sample[$field] - (float) $previous[$field]) * $ratio;
            }
            $result['distance_m'] = $distance;
            return $result;
        }
        $previous = $sample;
    }
    return $previous !== null && (float) ($previous['distance_m'] ?? -1) >= $distance ? $previous : null;
}

function activityStreamSegmentMetrics(array $samples, float $startDistance, float $endDistance): array
{
    $start = activityStreamInterpolateAtDistance($samples, $startDistance);
    $end = activityStreamInterpolateAtDistance($samples, $endDistance);
    if ($start === null || $end === null) return [];
    $inside = array_values(array_filter($samples, static fn(array $sample): bool => $sample['distance_m'] !== null && (float) $sample['distance_m'] >= $startDistance && (float) $sample['distance_m'] <= $endDistance));
    $inside = array_merge([$start], $inside, [$end]);
    usort($inside, static fn(array $a, array $b): int => ((float) $a['distance_m']) <=> ((float) $b['distance_m']));
    $duration = max(0.0, ((float) $end['elapsed_ms'] - (float) $start['elapsed_ms']) / 1000.0);
    $moving = max(0.0, ((float) ($end['moving_ms'] ?? $end['elapsed_ms']) - (float) ($start['moving_ms'] ?? $start['elapsed_ms'])) / 1000.0);
    $distance = max(0.0, $endDistance - $startDistance);
    $hrValues = array_values(array_filter(array_map(static fn(array $sample): mixed => $sample['heart_rate_bpm'], $inside), 'is_numeric'));
    $cadenceValues = array_values(array_filter(array_map(static fn(array $sample): mixed => $sample['cadence'], $inside), 'is_numeric'));
    $powerValues = array_values(array_filter(array_map(static fn(array $sample): mixed => $sample['power_w'], $inside), 'is_numeric'));
    $gain = 0.0;
    $loss = 0.0;
    $previousAltitude = null;
    foreach ($inside as $sample) {
        if (!is_numeric($sample['altitude_m'] ?? null)) continue;
        $altitude = (float) $sample['altitude_m'];
        if ($previousAltitude !== null) {
            $delta = $altitude - $previousAltitude;
            if (abs($delta) >= 0.8) {
                if ($delta > 0) $gain += $delta; else $loss += abs($delta);
            }
        }
        $previousAltitude = $altitude;
    }
    return [
        'start_distance_m' => $startDistance, 'end_distance_m' => $endDistance, 'distance_m' => $distance, 'elapsed_duration_s' => $duration, 'moving_duration_s' => $moving,
        'pace_s_per_km' => $distance > 0 && $moving > 0 ? $moving / ($distance / 1000.0) : null, 'speed_kmh' => $moving > 0 ? ($distance / 1000.0) / ($moving / 3600.0) : null,
        'heart_rate_avg_bpm' => $hrValues !== [] ? array_sum($hrValues) / count($hrValues) : null, 'heart_rate_max_bpm' => $hrValues !== [] ? max($hrValues) : null,
        'elevation_gain_m' => $previousAltitude !== null ? $gain : null, 'elevation_loss_m' => $previousAltitude !== null ? $loss : null,
        'cadence_avg' => $cadenceValues !== [] ? array_sum($cadenceValues) / count($cadenceValues) : null, 'power_avg_w' => $powerValues !== [] ? array_sum($powerValues) / count($powerValues) : null,
    ];
}

function activityStreamSplits(PDO $pdo, string $userId, string $activityId, int $distanceM = 1000): array
{
    $owner = activityStreamOwner($pdo, $userId, $activityId);
    if ($distanceM < 100 || $distanceM > 100000) throw new InvalidArgumentException('distance_m precisa ficar entre 100 e 100000.');
    $stored = activityStreamRows($pdo, $userId, $activityId);
    $samples = array_values(array_filter($stored['samples'], static fn(array $sample): bool => $sample['distance_m'] !== null));
    if ($samples === []) return ['activity_id' => $activityId, 'split_distance_m' => $distanceM, 'data' => []];
    $maxDistance = max(array_map(static fn(array $sample): float => (float) $sample['distance_m'], $samples));
    $data = [];
    $index = 1;
    for ($start = 0.0; $start < $maxDistance; $start += $distanceM) {
        $end = min($maxDistance, $start + $distanceM);
        if ($end <= $start) break;
        $metrics = activityStreamSegmentMetrics($samples, $start, $end);
        if ($metrics === []) continue;
        $data[] = ['index' => $index++, 'partial' => $end - $start + 0.001 < $distanceM] + $metrics;
    }
    return ['activity_id' => $activityId, 'sport' => ['slug' => (string) $owner['modalidade_slug'], 'derived_metric' => (string) ($owner['metrica_derivada'] ?? 'nenhuma')], 'split_distance_m' => $distanceM, 'data' => $data, 'units' => ['distance' => 'm', 'duration' => 's', 'pace' => 's_per_km', 'speed' => 'km_h', 'heart_rate' => 'bpm', 'elevation' => 'm', 'cadence' => activityStreamCadenceUnit((string) $owner['modalidade_slug'], (string) ($owner['familia_hub'] ?? '')), 'power' => 'W']];
}

function activityStreamSaveLaps(PDO $pdo, string $userId, string $activityId, array $laps, string $origin = 'manual', ?string $source = null): array
{
    activityStreamOwner($pdo, $userId, $activityId);
    if (!in_array($origin, ['manual', 'import'], true)) throw new InvalidArgumentException('Origem de lap inválida.');
    if (count($laps) > 1000) throw new InvalidArgumentException('A Activity possui laps demais.');
    $normalized = [];
    $previousEndElapsed = -1;
    $previousEndDistance = -1.0;
    foreach ($laps as $index => $lap) {
        if (!is_array($lap)) throw new InvalidArgumentException('Cada lap precisa ser um objeto.');
        $startElapsed = filter_var($lap['start_elapsed_ms'] ?? null, FILTER_VALIDATE_INT);
        $endElapsed = filter_var($lap['end_elapsed_ms'] ?? null, FILTER_VALIDATE_INT);
        if ($startElapsed === false || $endElapsed === false || $startElapsed < 0 || $endElapsed < $startElapsed) throw new InvalidArgumentException('Intervalo elapsed do lap é inválido.');
        $startDistance = activityStreamFinite($lap['start_distance_m'] ?? null);
        $endDistance = activityStreamFinite($lap['end_distance_m'] ?? null);
        if (($startDistance !== null && $startDistance < 0) || ($endDistance !== null && ($startDistance === null || $endDistance < $startDistance))) throw new InvalidArgumentException('Intervalo de distância do lap é inválido.');
        if ($startElapsed < $previousEndElapsed) throw new InvalidArgumentException('Laps não podem se sobrepor.');
        if ($startDistance !== null && $previousEndDistance >= 0 && $startDistance + 0.5 < $previousEndDistance) throw new InvalidArgumentException('Laps não podem voltar na distância.');
        $normalized[] = ['order' => $index + 1, 'start_elapsed_ms' => (int) $startElapsed, 'end_elapsed_ms' => (int) $endElapsed, 'start_moving_ms' => isset($lap['start_moving_ms']) && is_numeric($lap['start_moving_ms']) ? (int) $lap['start_moving_ms'] : null, 'end_moving_ms' => isset($lap['end_moving_ms']) && is_numeric($lap['end_moving_ms']) ? (int) $lap['end_moving_ms'] : null, 'start_distance_m' => $startDistance, 'end_distance_m' => $endDistance, 'metadata' => activityStreamMetadataObject($lap['metadata'] ?? null, 'laps.metadata')];
        $previousEndElapsed = (int) $endElapsed;
        if ($endDistance !== null) $previousEndDistance = $endDistance;
    }
    $owns = !$pdo->inTransaction();
    if ($owns) $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM activity_laps WHERE idregistro=:id AND origin=:origin')->execute([':id' => $activityId, ':origin' => $origin]);
        $insert = $pdo->prepare('INSERT INTO activity_laps (idlap,idregistro,lap_order,origin,start_elapsed_ms,end_elapsed_ms,start_moving_ms,end_moving_ms,start_distance_m,end_distance_m,source,source_metadata) VALUES (:id,:record,:ord,:origin,:se,:ee,:sm,:em,:sd,:ed,:source,CAST(:metadata AS jsonb))');
        foreach ($normalized as $lap) $insert->execute([':id' => activityStreamId(), ':record' => $activityId, ':ord' => $lap['order'], ':origin' => $origin, ':se' => $lap['start_elapsed_ms'], ':ee' => $lap['end_elapsed_ms'], ':sm' => $lap['start_moving_ms'], ':em' => $lap['end_moving_ms'], ':sd' => $lap['start_distance_m'], ':ed' => $lap['end_distance_m'], ':source' => $source, ':metadata' => activityStreamEncodeJsonObject($lap['metadata'])]);
        $pdo->prepare('DELETE FROM activity_analysis_cache WHERE idregistro=:id')->execute([':id' => $activityId]);
        if ($owns) $pdo->commit();
    } catch (Throwable $e) {
        if ($owns && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return activityStreamLaps($pdo, $userId, $activityId);
}

function activityStreamLaps(PDO $pdo, string $userId, string $activityId): array
{
    $owner = activityStreamOwner($pdo, $userId, $activityId);
    $stored = activityStreamRows($pdo, $userId, $activityId);
    $stmt = $pdo->prepare('SELECT * FROM activity_laps WHERE idregistro=:id ORDER BY lap_order,origin');
    $stmt->execute([':id' => $activityId]);
    $data = [];
    foreach ($stmt->fetchAll() as $row) {
        $metrics = [];
        if ($row['start_distance_m'] !== null && $row['end_distance_m'] !== null && $stored['samples'] !== []) $metrics = activityStreamSegmentMetrics($stored['samples'], (float) $row['start_distance_m'], (float) $row['end_distance_m']);
        if ($metrics === []) {
            $elapsed = max(0.0, ((int) $row['end_elapsed_ms'] - (int) $row['start_elapsed_ms']) / 1000.0);
            $moving = $row['start_moving_ms'] !== null && $row['end_moving_ms'] !== null ? max(0.0, ((int) $row['end_moving_ms'] - (int) $row['start_moving_ms']) / 1000.0) : null;
            $distance = $row['start_distance_m'] !== null && $row['end_distance_m'] !== null ? max(0.0, (float) $row['end_distance_m'] - (float) $row['start_distance_m']) : null;
            $metrics = ['start_distance_m' => $row['start_distance_m'] !== null ? (float) $row['start_distance_m'] : null, 'end_distance_m' => $row['end_distance_m'] !== null ? (float) $row['end_distance_m'] : null, 'distance_m' => $distance, 'elapsed_duration_s' => $elapsed, 'moving_duration_s' => $moving, 'pace_s_per_km' => $distance !== null && $distance > 0 && $moving !== null && $moving > 0 ? $moving / ($distance / 1000.0) : null, 'speed_kmh' => $distance !== null && $moving !== null && $moving > 0 ? ($distance / 1000.0) / ($moving / 3600.0) : null, 'heart_rate_avg_bpm' => null, 'heart_rate_max_bpm' => null, 'elevation_gain_m' => null, 'elevation_loss_m' => null, 'cadence_avg' => null, 'power_avg_w' => null];
        }
        $data[] = ['id' => (string) $row['idlap'], 'order' => (int) $row['lap_order'], 'origin' => (string) $row['origin'], 'start_elapsed_ms' => (int) $row['start_elapsed_ms'], 'end_elapsed_ms' => (int) $row['end_elapsed_ms'], 'source' => $row['source'] !== null ? (string) $row['source'] : null] + $metrics;
    }
    return ['activity_id' => $activityId, 'sport' => ['slug' => (string) $owner['modalidade_slug'], 'derived_metric' => (string) ($owner['metrica_derivada'] ?? 'nenhuma')], 'data' => $data];
}

function activityStreamCapabilities(PDO $pdo, string $userId, string $activityId): array
{
    activityStreamOwner($pdo, $userId, $activityId);
    $stmt = $pdo->prepare("SELECT b.available_streams,b.sample_count,EXISTS(SELECT 1 FROM activity_laps l WHERE l.idregistro=:lap_id) AS has_laps,EXISTS(SELECT 1 FROM activity_analysis_cache a WHERE a.idregistro=:analysis_id) AS has_analysis FROM activity_stream_bundles b WHERE b.idregistro=:id LIMIT 1");
    $stmt->execute([':id' => $activityId, ':lap_id' => $activityId, ':analysis_id' => $activityId]);
    $row = $stmt->fetch();
    if (!$row) {
        $lap = $pdo->prepare('SELECT EXISTS(SELECT 1 FROM activity_laps WHERE idregistro=:id)');
        $lap->execute([':id' => $activityId]);
        return ['has_streams' => false, 'available_streams' => [], 'has_analysis' => false, 'has_heart_rate' => false, 'has_cadence' => false, 'has_laps' => activityStreamBool($lap->fetchColumn())];
    }
    $available = is_array($row['available_streams'] ?? null) ? $row['available_streams'] : json_decode((string) ($row['available_streams'] ?? '[]'), true);
    if (!is_array($available)) $available = [];
    return ['has_streams' => (int) $row['sample_count'] > 0, 'available_streams' => array_values($available), 'has_analysis' => activityStreamBool($row['has_analysis']), 'has_heart_rate' => in_array('heart_rate', $available, true), 'has_cadence' => in_array('cadence', $available, true), 'has_laps' => activityStreamBool($row['has_laps'])];
}

function activityStreamRouteDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $earth = 6371000.0;
    $phi1 = deg2rad($lat1);
    $phi2 = deg2rad($lat2);
    $deltaPhi = deg2rad($lat2 - $lat1);
    $deltaLambda = deg2rad($lon2 - $lon1);
    $a = sin($deltaPhi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($deltaLambda / 2) ** 2;
    return $earth * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
}

function activityStreamBundleFromImportSeries(array $series, int $activityStartedMs): array
{
    if ($series === []) return [];
    $samples = [];
    $firstTimestampMs = null;
    $distance = 0.0;
    $previousLat = null;
    $previousLon = null;
    foreach ($series as $index => $point) {
        if (!is_array($point)) continue;
        $timestampRaw = $point['timestamp'] ?? $point['timestamp_ms'] ?? null;
        $timestampMs = null;
        if (is_numeric($timestampRaw)) {
            $timestamp = (float) $timestampRaw;
            $timestampMs = $timestamp > 100000000000 ? (int) round($timestamp) : (int) round($timestamp * 1000.0);
            $firstTimestampMs ??= $timestampMs;
        }
        $elapsed = $timestampMs !== null ? max(0, $timestampMs - ($firstTimestampMs ?? $activityStartedMs)) : ($samples === [] ? 0 : (int) end($samples)['elapsed_ms'] + 1000);
        $lat = activityStreamFinite($point['lat'] ?? null);
        $lon = activityStreamFinite($point['lon'] ?? null);
        if (is_numeric($point['distance_m'] ?? null)) {
            $distance = max($distance, (float) $point['distance_m']);
        } elseif ($lat !== null && $lon !== null && $previousLat !== null && $previousLon !== null) {
            $distance += activityStreamRouteDistance($previousLat, $previousLon, $lat, $lon);
        }
        if ($lat !== null && $lon !== null) { $previousLat = $lat; $previousLon = $lon; }
        $samples[] = [
            'elapsed_ms' => $elapsed,
            'moving_ms' => $elapsed,
            'distance_m' => $distance > 0 || $index > 0 ? $distance : (is_numeric($point['distance_m'] ?? null) ? (float) $point['distance_m'] : null),
            'speed_mps' => activityStreamFinite($point['speed_mps'] ?? null),
            'heart_rate_bpm' => isset($point['heart_rate']) && is_numeric($point['heart_rate']) ? (int) $point['heart_rate'] : null,
            'altitude_m' => activityStreamFinite($point['altitude_m'] ?? null),
            'cadence' => activityStreamFinite($point['cadence'] ?? null),
            'power_w' => activityStreamFinite($point['power'] ?? null),
            'temperature_c' => activityStreamFinite($point['temperature_c'] ?? null),
            'route_point_index' => $lat !== null && $lon !== null ? $index : null,
        ];
    }
    return $samples;
}

function activityStreamBundleFromRoute(PDO $pdo, string $activityId, int $activityStartedMs): array
{
    $stmt = $pdo->prepare('SELECT coordenadas,pontos_metadata FROM rotas_atividade WHERE idregistro=:id LIMIT 1');
    $stmt->execute([':id' => $activityId]);
    $row = $stmt->fetch();
    if (!$row) return [];
    $geometry = is_array($row['coordenadas'] ?? null) ? $row['coordenadas'] : json_decode((string) ($row['coordenadas'] ?? ''), true);
    $metadata = is_array($row['pontos_metadata'] ?? null) ? $row['pontos_metadata'] : json_decode((string) ($row['pontos_metadata'] ?? ''), true);
    $coordinates = is_array($geometry) && ($geometry['type'] ?? '') === 'LineString' && is_array($geometry['coordinates'] ?? null) ? $geometry['coordinates'] : [];
    if (count($coordinates) < 2) return [];
    if (!is_array($metadata)) $metadata = [];
    $samples = [];
    $distance = 0.0;
    $firstTimestamp = null;
    $previousCoordinate = null;
    foreach ($coordinates as $index => $coordinate) {
        if (!is_array($coordinate) || !is_numeric($coordinate[0] ?? null) || !is_numeric($coordinate[1] ?? null)) continue;
        if ($previousCoordinate !== null) $distance += activityStreamRouteDistance((float) $previousCoordinate[1], (float) $previousCoordinate[0], (float) $coordinate[1], (float) $coordinate[0]);
        $previousCoordinate = $coordinate;
        $meta = is_array($metadata[$index] ?? null) ? $metadata[$index] : [];
        $timestamp = isset($meta['timestamp_ms']) && is_numeric($meta['timestamp_ms']) ? (int) $meta['timestamp_ms'] : null;
        if ($timestamp === null) continue;
        $firstTimestamp ??= $timestamp;
        $elapsed = max(0, $timestamp - $firstTimestamp);
        $samples[] = ['elapsed_ms' => $elapsed, 'moving_ms' => $elapsed, 'distance_m' => $distance, 'altitude_m' => activityStreamFinite($meta['altitude_m'] ?? $coordinate[2] ?? null), 'horizontal_accuracy_m' => activityStreamFinite($meta['accuracy_m'] ?? null), 'route_point_index' => $index];
    }
    if (count($samples) < 2 || (int) end($samples)['elapsed_ms'] <= 0) return [];
    return $samples;
}

function activityStreamEnsureMaterialized(PDO $pdo, string $userId, string $activityId): array
{
    $owner = activityStreamOwner($pdo, $userId, $activityId);
    $existing = activityStreamRows($pdo, $userId, $activityId);
    if ($existing['bundle'] !== null) return ['materialized' => false, 'source' => $existing['bundle']['source'], 'bundle' => $existing['bundle']];
    $startedMs = (int) round((new DateTimeImmutable((string) $owner['data_inicio']))->format('U.u') * 1000);
    $importStmt = $pdo->prepare("SELECT formato,series_temporais FROM atividade_importacoes WHERE idusuario=:user AND idregistro=:id AND status='importado' ORDER BY data_criacao DESC LIMIT 1");
    $importStmt->execute([':user' => $userId, ':id' => $activityId]);
    $import = $importStmt->fetch();
    if ($import) {
        $series = is_array($import['series_temporais'] ?? null) ? $import['series_temporais'] : json_decode((string) ($import['series_temporais'] ?? '[]'), true);
        if (is_array($series) && $series !== []) {
            $samples = activityStreamBundleFromImportSeries($series, $startedMs);
            if ($samples !== []) {
                $saved = activityStreamSaveBundle($pdo, $userId, $activityId, ['schema_version' => 1, 'source' => 'import', 'source_metadata' => ['format' => strtolower((string) $import['formato'])], 'samples' => $samples], null, 'import');
                return ['materialized' => true, 'source' => 'import', 'bundle' => $saved];
            }
        }
    }
    $samples = activityStreamBundleFromRoute($pdo, $activityId, $startedMs);
    $hasTimestamp = false;
    if ($samples !== []) {
        foreach ($samples as $sample) if (($sample['elapsed_ms'] ?? 0) > 0) { $hasTimestamp = true; break; }
    }
    if ($samples !== [] && $hasTimestamp) {
        $saved = activityStreamSaveBundle($pdo, $userId, $activityId, ['schema_version' => 1, 'source' => 'route_backfill', 'source_metadata' => ['derived_from' => 'rotas_atividade.pontos_metadata'], 'samples' => $samples], null, 'route_backfill');
        return ['materialized' => true, 'source' => 'route_backfill', 'bundle' => $saved];
    }
    return ['materialized' => false, 'source' => null, 'bundle' => null];
}
