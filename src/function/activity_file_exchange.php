<?php

declare(strict_types=1);

const STRIDEBR_ACTIVITY_FILE_MAX_BYTES = 26214400;
const STRIDEBR_ACTIVITY_IMPORT_MAX_STREAM_POINTS = 24000;
const STRIDEBR_ACTIVITY_ROUTE_POINTS = 1800;

function atividadeArquivoExtensao(string $name): string
{
    return strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
}

function atividadeArquivoXmlTexto(string $value): string
{
    return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_XML1, 'UTF-8'));
}

function atividadeArquivoXmlTag(string $xml, string $tag): ?string
{
    $quoted = preg_quote($tag, '~');
    if (!preg_match('~<(?:[A-Za-z0-9_-]+:)?' . $quoted . '\b[^>]*>(.*?)</(?:[A-Za-z0-9_-]+:)?' . $quoted . '>~si', $xml, $match)) return null;
    $value = atividadeArquivoXmlTexto((string) $match[1]);
    return $value !== '' ? $value : null;
}

function atividadeArquivoXmlAttr(string $attributes, string $name): ?string
{
    $quoted = preg_quote($name, '~');
    if (!preg_match('~\b' . $quoted . '\s*=\s*(["\'])(.*?)\1~si', $attributes, $match)) return null;
    return html_entity_decode((string) $match[2], ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function atividadeArquivoTimestamp(?string $value): ?int
{
    $value = trim((string) $value);
    if ($value === '') return null;
    try {
        return (new DateTimeImmutable($value))->getTimestamp();
    } catch (Throwable) {
        return null;
    }
}

function atividadeArquivoIso(?int $timestamp): ?string
{
    if ($timestamp === null) return null;
    return (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM);
}

function atividadeArquivoLocalDateTime(?int $timestamp): ?string
{
    if ($timestamp === null) return null;
    return (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d H:i');
}

function atividadeArquivoHaversine(array $a, array $b): float
{
    $earth = 6371008.8;
    $lat1 = deg2rad((float) $a[1]);
    $lat2 = deg2rad((float) $b[1]);
    $dLat = $lat2 - $lat1;
    $dLon = deg2rad((float) $b[0] - (float) $a[0]);
    $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;
    return $earth * 2 * atan2(sqrt($h), sqrt(max(0.0, 1 - $h)));
}

function atividadeArquivoDistanciaRota(array $coordinates): float
{
    $distance = 0.0;
    for ($i = 1, $count = count($coordinates); $i < $count; $i++) $distance += atividadeArquivoHaversine($coordinates[$i - 1], $coordinates[$i]);
    return $distance;
}

function atividadeArquivoSimplificarRota(array $coordinates, int $limit = STRIDEBR_ACTIVITY_ROUTE_POINTS): array
{
    $coordinates = array_values(array_filter($coordinates, static fn($point): bool => is_array($point) && count($point) >= 2 && is_numeric($point[0]) && is_numeric($point[1])));
    $count = count($coordinates);
    if ($count <= $limit) return array_map(static fn(array $p): array => [round((float) $p[0], 7), round((float) $p[1], 7)], $coordinates);
    $result = [];
    $step = ($count - 1) / ($limit - 1);
    for ($i = 0; $i < $limit; $i++) {
        $index = min($count - 1, (int) round($i * $step));
        $point = $coordinates[$index];
        $result[] = [round((float) $point[0], 7), round((float) $point[1], 7)];
    }
    return $result;
}

function atividadeArquivoResumoSeries(array $series): array
{
    $coordinates = [];
    $altitudes = [];
    $hrs = [];
    $cadences = [];
    $powers = [];
    $speeds = [];
    $timestamps = [];
    foreach ($series as $point) {
        if (isset($point['lon'], $point['lat']) && is_numeric($point['lon']) && is_numeric($point['lat'])) $coordinates[] = [(float) $point['lon'], (float) $point['lat']];
        if (isset($point['altitude_m']) && is_numeric($point['altitude_m'])) $altitudes[] = (float) $point['altitude_m'];
        if (isset($point['heart_rate']) && is_numeric($point['heart_rate']) && (float) $point['heart_rate'] > 0) $hrs[] = (float) $point['heart_rate'];
        if (isset($point['cadence']) && is_numeric($point['cadence']) && (float) $point['cadence'] >= 0) $cadences[] = (float) $point['cadence'];
        if (isset($point['power']) && is_numeric($point['power']) && (float) $point['power'] >= 0) $powers[] = (float) $point['power'];
        if (isset($point['speed_mps']) && is_numeric($point['speed_mps']) && (float) $point['speed_mps'] >= 0) $speeds[] = (float) $point['speed_mps'];
        if (isset($point['timestamp']) && is_int($point['timestamp'])) $timestamps[] = $point['timestamp'];
    }
    $gain = 0.0;
    $loss = 0.0;
    for ($i = 1, $count = count($altitudes); $i < $count; $i++) {
        $delta = $altitudes[$i] - $altitudes[$i - 1];
        if ($delta > .35) $gain += $delta;
        elseif ($delta < -.35) $loss += abs($delta);
    }
    $avg = static fn(array $values): ?float => $values === [] ? null : array_sum($values) / count($values);
    return [
        'coordinates' => $coordinates,
        'distance_m' => $coordinates === [] ? null : atividadeArquivoDistanciaRota($coordinates),
        'start_ts' => $timestamps === [] ? null : min($timestamps),
        'end_ts' => $timestamps === [] ? null : max($timestamps),
        'duration_s' => count($timestamps) >= 2 ? max(0, max($timestamps) - min($timestamps)) : null,
        'elevation_gain_m' => $altitudes === [] ? null : $gain,
        'elevation_loss_m' => $altitudes === [] ? null : $loss,
        'elevation_min_m' => $altitudes === [] ? null : min($altitudes),
        'elevation_max_m' => $altitudes === [] ? null : max($altitudes),
        'avg_hr' => $avg($hrs),
        'max_hr' => $hrs === [] ? null : max($hrs),
        'avg_cadence' => $avg($cadences),
        'max_cadence' => $cadences === [] ? null : max($cadences),
        'avg_power' => $avg($powers),
        'max_power' => $powers === [] ? null : max($powers),
        'avg_speed_mps' => $avg($speeds),
        'max_speed_mps' => $speeds === [] ? null : max($speeds),
    ];
}

function atividadeArquivoSportSlug(string $sport, ?string $subSport = null): string
{
    $sport = strtolower(trim($sport));
    $subSport = strtolower(trim((string) $subSport));
    if ($sport === 'running') {
        if (in_array($subSport, ['trail', 'ultra'], true)) return 'corrida-em-trilha';
        if (in_array($subSport, ['treadmill', 'indoor_running'], true)) return 'corrida-em-esteira';
        return 'corrida';
    }
    if ($sport === 'walking') return 'caminhada';
    if ($sport === 'hiking' || $sport === 'mountaineering') return 'trilha';
    if ($sport === 'cycling') {
        if (in_array($subSport, ['mountain', 'downhill'], true)) return 'mountain-bike';
        if (in_array($subSport, ['spin', 'indoor_cycling'], true)) return 'ciclismo-indoor';
        if ($subSport === 'gravel_cycling') return 'gravel';
        if ($subSport === 'bmx') return 'bmx';
        return 'ciclismo';
    }
    return match ($sport) {
        'e_biking' => 'bicicleta-eletrica',
        'swimming' => 'natacao',
        'rowing' => 'remo',
        'kayaking' => 'caiaque',
        'paddling' => 'canoagem',
        'stand_up_paddleboarding' => 'stand-up-paddle',
        'sailing' => 'vela',
        'inline_skating' => 'patinacao-inline',
        'ice_skating' => 'patinacao-no-gelo',
        'alpine_skiing' => 'esqui-alpino',
        'cross_country_skiing' => 'esqui-nordico',
        'snowboarding' => 'snowboard',
        'yoga' => 'yoga',
        'pilates' => 'pilates',
        'hiit' => 'hiit',
        'training', 'fitness_equipment', 'strength_training' => $subSport === 'cardio_training' ? 'cardio' : 'musculacao',
        default => 'outra-atividade',
    };
}

function atividadeArquivoGpx(string $xml): array
{
    $isTrack = preg_match('~<(?:[A-Za-z0-9_-]+:)?trk\b~i', $xml) === 1;
    $isRoute = preg_match('~<(?:[A-Za-z0-9_-]+:)?rte\b~i', $xml) === 1;
    $pointTag = $isTrack ? 'trkpt' : 'rtept';
    $series = [];
    if (preg_match_all('~<(?:[A-Za-z0-9_-]+:)?' . $pointTag . '\b([^>]*)>(.*?)</(?:[A-Za-z0-9_-]+:)?' . $pointTag . '>~si', $xml, $points, PREG_SET_ORDER)) {
        foreach ($points as $match) {
            $lat = atividadeArquivoXmlAttr((string) $match[1], 'lat');
            $lon = atividadeArquivoXmlAttr((string) $match[1], 'lon');
            if (!is_numeric($lat) || !is_numeric($lon)) continue;
            $body = (string) $match[2];
            $point = ['lat' => (float) $lat, 'lon' => (float) $lon];
            $time = atividadeArquivoTimestamp(atividadeArquivoXmlTag($body, 'time'));
            if ($time !== null) $point['timestamp'] = $time;
            $ele = atividadeArquivoXmlTag($body, 'ele');
            if (is_numeric($ele)) $point['altitude_m'] = (float) $ele;
            foreach ([['heart_rate', 'hr'], ['cadence', 'cad']] as [$key, $tag]) {
                if (preg_match('~<(?:[A-Za-z0-9_-]+:)?' . $tag . '\b[^>]*>([^<]+)~i', $body, $value) && is_numeric(trim($value[1]))) $point[$key] = (int) trim($value[1]);
            }
            $series[] = $point;
            if (count($series) >= STRIDEBR_ACTIVITY_IMPORT_MAX_STREAM_POINTS) break;
        }
    }
    $summary = atividadeArquivoResumoSeries($series);
    $name = atividadeArquivoXmlTag($xml, 'name');
    $type = strtolower((string) atividadeArquivoXmlTag($xml, 'type'));
    // Muitos relógios/apps (ex.: Samsung Health) exportam GPX sem a tag
    // <type> — que é opcional no formato. Nesses casos, sem mais nenhuma
    // pista, tudo caía em "outra atividade". Como reforço, também olhamos o
    // nome/descrição da trilha, com palavras em português e inglês.
    if ($type === '') {
        $type = strtolower((string) (atividadeArquivoXmlTag($xml, 'name') ?? atividadeArquivoXmlTag($xml, 'desc') ?? atividadeArquivoXmlTag($xml, 'cmt') ?? ''));
    }
    $sport = match (true) {
        str_contains($type, 'run') || str_contains($type, 'corrid') || str_contains($type, 'jog') => 'running',
        str_contains($type, 'walk') || str_contains($type, 'caminh') => 'walking',
        str_contains($type, 'cycl') || str_contains($type, 'bik') || str_contains($type, 'ciclis') || str_contains($type, 'bicicl') || str_contains($type, 'pedal') => 'cycling',
        str_contains($type, 'hik') || str_contains($type, 'trilha') || str_contains($type, 'trail') => 'hiking',
        str_contains($type, 'swim') || str_contains($type, 'nata') => 'swimming',
        default => 'generic',
    };
    $hasTimestamps = is_int($summary['start_ts'] ?? null) && is_int($summary['end_ts'] ?? null);
    return [
        'format' => 'gpx',
        'file_type' => $isRoute || ($isTrack && !$hasTimestamps) ? 'percurso' : ($isTrack ? 'atividade' : 'desconhecido'),
        'title' => $name,
        'sport' => $sport,
        'sub_sport' => null,
        'series' => $series,
        'summary' => $summary,
        'device' => [],
    ];
}

function atividadeArquivoTcx(string $xml): array
{
    $isActivity = preg_match('~<(?:[A-Za-z0-9_-]+:)?Activities\b~i', $xml) === 1;
    $isCourse = preg_match('~<(?:[A-Za-z0-9_-]+:)?Courses\b~i', $xml) === 1;
    $sport = 'generic';
    if (preg_match('~<(?:[A-Za-z0-9_-]+:)?Activity\b([^>]*)>~i', $xml, $activity)) {
        $rawSport = strtolower((string) atividadeArquivoXmlAttr((string) $activity[1], 'Sport'));
        $sport = match ($rawSport) {'running' => 'running', 'biking' => 'cycling', default => 'generic'};
    }
    $series = [];
    if (preg_match_all('~<(?:[A-Za-z0-9_-]+:)?Trackpoint\b[^>]*>(.*?)</(?:[A-Za-z0-9_-]+:)?Trackpoint>~si', $xml, $matches)) {
        foreach ($matches[1] as $body) {
            $lat = atividadeArquivoXmlTag((string) $body, 'LatitudeDegrees');
            $lon = atividadeArquivoXmlTag((string) $body, 'LongitudeDegrees');
            $point = [];
            if (is_numeric($lat) && is_numeric($lon)) { $point['lat'] = (float) $lat; $point['lon'] = (float) $lon; }
            $time = atividadeArquivoTimestamp(atividadeArquivoXmlTag((string) $body, 'Time'));
            if ($time !== null) $point['timestamp'] = $time;
            $ele = atividadeArquivoXmlTag((string) $body, 'AltitudeMeters');
            if (is_numeric($ele)) $point['altitude_m'] = (float) $ele;
            $distance = atividadeArquivoXmlTag((string) $body, 'DistanceMeters');
            if (is_numeric($distance)) $point['distance_m'] = (float) $distance;
            $hrBlock = null;
            if (preg_match('~<(?:[A-Za-z0-9_-]+:)?HeartRateBpm\b[^>]*>(.*?)</(?:[A-Za-z0-9_-]+:)?HeartRateBpm>~si', (string) $body, $hrMatch)) $hrBlock = $hrMatch[1];
            $hr = $hrBlock !== null ? atividadeArquivoXmlTag((string) $hrBlock, 'Value') : null;
            if (is_numeric($hr)) $point['heart_rate'] = (int) $hr;
            $cad = atividadeArquivoXmlTag((string) $body, 'Cadence');
            if (is_numeric($cad)) $point['cadence'] = (int) $cad;
            $speed = atividadeArquivoXmlTag((string) $body, 'Speed');
            if (is_numeric($speed)) $point['speed_mps'] = (float) $speed;
            $watts = atividadeArquivoXmlTag((string) $body, 'Watts');
            if (is_numeric($watts)) $point['power'] = (int) $watts;
            if ($point !== []) $series[] = $point;
            if (count($series) >= STRIDEBR_ACTIVITY_IMPORT_MAX_STREAM_POINTS) break;
        }
    }
    $summary = atividadeArquivoResumoSeries($series);
    $totalTime = atividadeArquivoXmlTag($xml, 'TotalTimeSeconds');
    $distance = atividadeArquivoXmlTag($xml, 'DistanceMeters');
    $calories = atividadeArquivoXmlTag($xml, 'Calories');
    $maxSpeed = atividadeArquivoXmlTag($xml, 'MaximumSpeed');
    if (is_numeric($totalTime)) $summary['duration_s'] = (float) $totalTime;
    if (is_numeric($distance)) $summary['distance_m'] = (float) $distance;
    if (is_numeric($calories)) $summary['calories'] = (int) $calories;
    if (is_numeric($maxSpeed)) $summary['max_speed_mps'] = (float) $maxSpeed;
    $start = atividadeArquivoTimestamp(atividadeArquivoXmlTag($xml, 'Id'));
    if ($start === null && preg_match('~<(?:[A-Za-z0-9_-]+:)?Lap\b([^>]*)>~i', $xml, $lap)) $start = atividadeArquivoTimestamp(atividadeArquivoXmlAttr((string) $lap[1], 'StartTime'));
    if ($summary['start_ts'] === null) $summary['start_ts'] = $start;
    if ($summary['end_ts'] === null && $start !== null && is_numeric($summary['duration_s'] ?? null)) $summary['end_ts'] = $start + (int) round((float) $summary['duration_s']);
    $name = atividadeArquivoXmlTag($xml, 'Name');
    $creator = null;
    if (preg_match('~<(?:[A-Za-z0-9_-]+:)?Creator\b[^>]*>(.*?)</(?:[A-Za-z0-9_-]+:)?Creator>~si', $xml, $creatorMatch)) $creator = atividadeArquivoXmlTag((string) $creatorMatch[1], 'Name');
    return [
        'format' => 'tcx',
        'file_type' => $isActivity ? 'atividade' : ($isCourse ? 'percurso' : 'desconhecido'),
        'title' => $isCourse ? $name : null,
        'sport' => $sport,
        'sub_sport' => null,
        'series' => $series,
        'summary' => $summary,
        'device' => $creator ? ['name' => $creator] : [],
    ];
}

function atividadeFitSportName(?int $value): string
{
    return match ($value) {
        1 => 'running', 2 => 'cycling', 4 => 'fitness_equipment', 5 => 'swimming', 10 => 'training', 11 => 'walking',
        12 => 'cross_country_skiing', 13 => 'alpine_skiing', 14 => 'snowboarding', 15 => 'rowing', 16 => 'mountaineering',
        17 => 'hiking', 18 => 'multisport', 19 => 'paddling', 21 => 'e_biking', 30 => 'inline_skating', 32 => 'sailing',
        33 => 'ice_skating', 37 => 'stand_up_paddleboarding', 41 => 'kayaking', 62 => 'hiit', default => 'generic',
    };
}

function atividadeFitSubSportName(?int $value): ?string
{
    return match ($value) {
        1 => 'treadmill', 3 => 'trail', 5 => 'spin', 6 => 'indoor_cycling', 8 => 'mountain', 9 => 'downhill', 14 => 'indoor_rowing',
        17 => 'lap_swimming', 18 => 'open_water', 20 => 'strength_training', 26 => 'cardio_training', 29 => 'bmx', 43 => 'yoga',
        44 => 'pilates', 45 => 'indoor_running', 46 => 'gravel_cycling', 47 => 'e_bike_mountain', 67 => 'ultra', 70 => 'hiit', default => null,
    };
}

function atividadeFitManufacturerName(?int $value): ?string
{
    return match ($value) {1 => 'Garmin', 15 => 'Dynastream', 32 => 'Wahoo Fitness', 255 => 'Development', default => $value === null ? null : 'Fabricante FIT ' . $value};
}

function atividadeFitSigned(int|float $value, int $bits): int|float
{
    $limit = 2 ** ($bits - 1);
    $full = 2 ** $bits;
    return $value >= $limit ? $value - $full : $value;
}

function atividadeFitDecodeScalar(string $bytes, int $baseType, bool $littleEndian): mixed
{
    $type = $baseType & 0x1F;
    $size = strlen($bytes);
    if ($type === 7) return rtrim($bytes, "\0\xFF");
    if ($size === 0) return null;
    if (in_array($type, [0, 2, 10, 13], true)) {
        $value = ord($bytes[0]);
        if (($type === 10 && $value === 0) || ($type !== 10 && $value === 0xFF)) return null;
        return $value;
    }
    if ($type === 1) {
        $value = atividadeFitSigned(ord($bytes[0]), 8);
        return $value === 127 ? null : $value;
    }
    if (in_array($type, [3, 4, 11], true) && $size >= 2) {
        $unsigned = unpack($littleEndian ? 'v' : 'n', substr($bytes, 0, 2))[1];
        if ($type === 3) { $signed = atividadeFitSigned($unsigned, 16); return $signed === 32767 ? null : $signed; }
        if (($type === 11 && $unsigned === 0) || ($type === 4 && $unsigned === 0xFFFF)) return null;
        return $unsigned;
    }
    if (in_array($type, [5, 6, 12], true) && $size >= 4) {
        $unsigned = unpack($littleEndian ? 'V' : 'N', substr($bytes, 0, 4))[1];
        if ($type === 5) { $signed = atividadeFitSigned($unsigned, 32); return $signed === 2147483647 ? null : $signed; }
        if (($type === 12 && $unsigned === 0) || ($type === 6 && $unsigned === 0xFFFFFFFF)) return null;
        return $unsigned;
    }
    if ($type === 8 && $size >= 4) return unpack($littleEndian ? 'g' : 'G', substr($bytes, 0, 4))[1];
    if ($type === 9 && $size >= 8) return unpack($littleEndian ? 'e' : 'E', substr($bytes, 0, 8))[1];
    return null;
}

function atividadeFitDecodeField(string $bytes, int $baseType, bool $littleEndian): mixed
{
    $type = $baseType & 0x1F;
    $unitSize = match ($type) {3, 4, 11 => 2, 5, 6, 8, 12 => 4, 9, 14, 15, 16 => 8, default => 1};
    if ($type === 7 || strlen($bytes) <= $unitSize) return atividadeFitDecodeScalar($bytes, $baseType, $littleEndian);
    $values = [];
    for ($offset = 0; $offset + $unitSize <= strlen($bytes); $offset += $unitSize) $values[] = atividadeFitDecodeScalar(substr($bytes, $offset, $unitSize), $baseType, $littleEndian);
    return $values;
}

function atividadeFitTimestamp(mixed $value): ?int
{
    if (!is_numeric($value)) return null;
    return 631065600 + (int) $value;
}

function atividadeFitSemicircles(mixed $value): ?float
{
    if (!is_numeric($value)) return null;
    return (float) $value * (180.0 / 2147483648.0);
}

function atividadeArquivoFit(string $binary): array
{
    $length = strlen($binary);
    if ($length < 14) throw new InvalidArgumentException('Arquivo FIT incompleto.');
    $headerSize = ord($binary[0]);
    if ($headerSize < 12 || $headerSize > 32 || $length < $headerSize + 2) throw new InvalidArgumentException('Cabeçalho FIT inválido.');
    $dataSize = unpack('V', substr($binary, 4, 4))[1];
    if (substr($binary, 8, 4) !== '.FIT') throw new InvalidArgumentException('Assinatura FIT inválida.');
    $end = min($length, $headerSize + $dataSize);
    $offset = $headerSize;
    $definitions = [];
    $messages = [];
    $lastTimestampRaw = null;
    while ($offset < $end) {
        $header = ord($binary[$offset++]);
        $compressed = ($header & 0x80) !== 0;
        if ($compressed) {
            $local = ($header >> 5) & 0x03;
            $definition = $definitions[$local] ?? null;
            if (!$definition) throw new InvalidArgumentException('FIT usa uma definição compactada ausente.');
            $timeOffset = $header & 0x1F;
            $fields = [];
            foreach ($definition['fields'] as $field) {
                if ((int) $field['num'] === 253) continue;
                if ($offset + $field['size'] > $end) throw new InvalidArgumentException('Dados FIT truncados.');
                $fields[$field['num']] = atividadeFitDecodeField(substr($binary, $offset, $field['size']), $field['base'], $definition['little']);
                $offset += $field['size'];
            }
            foreach ($definition['dev_fields'] as $field) { if ($offset + $field['size'] > $end) throw new InvalidArgumentException('Dados FIT truncados.'); $offset += $field['size']; }
            if ($lastTimestampRaw !== null) {
                $timestampRaw = ($lastTimestampRaw & ~0x1F) + $timeOffset;
                if ($timestampRaw < $lastTimestampRaw) $timestampRaw += 0x20;
                $lastTimestampRaw = $timestampRaw;
                $fields[253] = $timestampRaw;
            }
            $messages[] = ['global' => $definition['global'], 'fields' => $fields];
            continue;
        }
        $local = $header & 0x0F;
        $isDefinition = ($header & 0x40) !== 0;
        if ($isDefinition) {
            $hasDev = ($header & 0x20) !== 0;
            if ($offset + 5 > $end) throw new InvalidArgumentException('Definição FIT truncada.');
            $offset++;
            $architecture = ord($binary[$offset++]);
            $little = $architecture === 0;
            $global = unpack($little ? 'v' : 'n', substr($binary, $offset, 2))[1];
            $offset += 2;
            $fieldCount = ord($binary[$offset++]);
            $fields = [];
            for ($i = 0; $i < $fieldCount; $i++) {
                if ($offset + 3 > $end) throw new InvalidArgumentException('Definição FIT truncada.');
                $fields[] = ['num' => ord($binary[$offset]), 'size' => ord($binary[$offset + 1]), 'base' => ord($binary[$offset + 2])];
                $offset += 3;
            }
            $devFields = [];
            if ($hasDev) {
                if ($offset >= $end) throw new InvalidArgumentException('Definição FIT truncada.');
                $devCount = ord($binary[$offset++]);
                for ($i = 0; $i < $devCount; $i++) {
                    if ($offset + 3 > $end) throw new InvalidArgumentException('Definição FIT truncada.');
                    $devFields[] = ['num' => ord($binary[$offset]), 'size' => ord($binary[$offset + 1]), 'index' => ord($binary[$offset + 2])];
                    $offset += 3;
                }
            }
            $definitions[$local] = ['global' => $global, 'little' => $little, 'fields' => $fields, 'dev_fields' => $devFields];
            continue;
        }
        $definition = $definitions[$local] ?? null;
        if (!$definition) throw new InvalidArgumentException('FIT referencia uma definição ausente.');
        $fields = [];
        foreach ($definition['fields'] as $field) {
            if ($offset + $field['size'] > $end) throw new InvalidArgumentException('Dados FIT truncados.');
            $value = atividadeFitDecodeField(substr($binary, $offset, $field['size']), $field['base'], $definition['little']);
            $offset += $field['size'];
            $fields[$field['num']] = $value;
            if ((int) $field['num'] === 253 && is_numeric($value)) $lastTimestampRaw = (int) $value;
        }
        foreach ($definition['dev_fields'] as $field) { if ($offset + $field['size'] > $end) throw new InvalidArgumentException('Dados FIT truncados.'); $offset += $field['size']; }
        $messages[] = ['global' => $definition['global'], 'fields' => $fields];
    }

    $fileId = null;
    $session = null;
    $series = [];
    foreach ($messages as $message) {
        $global = (int) $message['global'];
        $f = $message['fields'];
        if ($global === 0 && $fileId === null) $fileId = $f;
        if ($global === 18) $session = $f;
        if ($global !== 20) continue;
        $lat = atividadeFitSemicircles($f[0] ?? null);
        $lon = atividadeFitSemicircles($f[1] ?? null);
        $point = [];
        if ($lat !== null && $lon !== null && abs($lat) <= 90 && abs($lon) <= 180) { $point['lat'] = $lat; $point['lon'] = $lon; }
        $timestamp = atividadeFitTimestamp($f[253] ?? null);
        if ($timestamp !== null) $point['timestamp'] = $timestamp;
        $altRaw = $f[78] ?? $f[2] ?? null;
        if (is_numeric($altRaw)) $point['altitude_m'] = ((float) $altRaw / 5.0) - 500.0;
        if (is_numeric($f[3] ?? null)) $point['heart_rate'] = (int) $f[3];
        if (is_numeric($f[4] ?? null)) $point['cadence'] = (int) $f[4];
        if (is_numeric($f[7] ?? null)) $point['power'] = (int) $f[7];
        $speedRaw = $f[73] ?? $f[29] ?? $f[6] ?? null;
        if (is_numeric($speedRaw)) $point['speed_mps'] = (float) $speedRaw / 1000.0;
        if (is_numeric($f[5] ?? null)) $point['distance_m'] = (float) $f[5] / 100.0;
        if ($point !== []) $series[] = $point;
        if (count($series) >= STRIDEBR_ACTIVITY_IMPORT_MAX_STREAM_POINTS) break;
    }
    $summary = atividadeArquivoResumoSeries($series);
    if (is_array($session)) {
        if (is_numeric($session[2] ?? null)) $summary['start_ts'] = atividadeFitTimestamp($session[2]);
        if (is_numeric($session[7] ?? null)) $summary['elapsed_s'] = (float) $session[7] / 1000.0;
        if (is_numeric($session[8] ?? null)) $summary['duration_s'] = (float) $session[8] / 1000.0;
        if (is_numeric($session[9] ?? null)) $summary['distance_m'] = (float) $session[9] / 100.0;
        if (is_numeric($session[11] ?? null)) $summary['calories'] = (int) $session[11];
        if (is_numeric($session[14] ?? null)) $summary['avg_speed_mps'] = (float) $session[14] / 1000.0;
        if (is_numeric($session[15] ?? null)) $summary['max_speed_mps'] = (float) $session[15] / 1000.0;
        if (is_numeric($session[16] ?? null)) $summary['avg_hr'] = (int) $session[16];
        if (is_numeric($session[17] ?? null)) $summary['max_hr'] = (int) $session[17];
        if (is_numeric($session[18] ?? null)) $summary['avg_cadence'] = (int) $session[18];
        if (is_numeric($session[19] ?? null)) $summary['max_cadence'] = (int) $session[19];
        if (is_numeric($session[20] ?? null)) $summary['avg_power'] = (int) $session[20];
        if (is_numeric($session[21] ?? null)) $summary['max_power'] = (int) $session[21];
        if (is_numeric($session[22] ?? null)) $summary['elevation_gain_m'] = (float) $session[22];
        if (is_numeric($session[23] ?? null)) $summary['elevation_loss_m'] = (float) $session[23];
        if ($summary['end_ts'] === null && $summary['start_ts'] !== null && is_numeric($summary['duration_s'] ?? null)) $summary['end_ts'] = $summary['start_ts'] + (int) round((float) $summary['duration_s']);
    }
    $fileTypeValue = is_array($fileId) && is_numeric($fileId[0] ?? null) ? (int) $fileId[0] : null;
    $fileType = match ($fileTypeValue) {4 => 'atividade', 5 => 'treino', 6 => 'percurso', default => 'desconhecido'};
    $sportValue = is_array($session) && is_numeric($session[5] ?? null) ? (int) $session[5] : null;
    $subSportValue = is_array($session) && is_numeric($session[6] ?? null) ? (int) $session[6] : null;
    $manufacturer = is_array($fileId) && is_numeric($fileId[1] ?? null) ? (int) $fileId[1] : null;
    $product = is_array($fileId) && is_numeric($fileId[2] ?? null) ? (int) $fileId[2] : null;
    $serial = is_array($fileId) && is_numeric($fileId[3] ?? null) ? (string) $fileId[3] : null;
    return [
        'format' => 'fit',
        'file_type' => $fileType,
        'title' => null,
        'sport' => atividadeFitSportName($sportValue),
        'sub_sport' => atividadeFitSubSportName($subSportValue),
        'series' => $series,
        'summary' => $summary,
        'device' => array_filter(['manufacturer' => atividadeFitManufacturerName($manufacturer), 'manufacturer_id' => $manufacturer, 'product_id' => $product, 'serial' => $serial], static fn($v): bool => $v !== null && $v !== ''),
        'fit' => ['file_type' => $fileTypeValue, 'sport' => $sportValue, 'sub_sport' => $subSportValue, 'messages' => count($messages)],
    ];
}

function atividadeArquivoAnalisar(string $name, string $binary): array
{
    if ($binary === '') throw new InvalidArgumentException('O arquivo está vazio.');
    if (strlen($binary) > STRIDEBR_ACTIVITY_FILE_MAX_BYTES) throw new InvalidArgumentException('O arquivo excede 25 MB.');
    $format = atividadeArquivoExtensao($name);
    if (!in_array($format, ['fit', 'tcx', 'gpx'], true)) throw new InvalidArgumentException('Use um arquivo FIT, TCX ou GPX.');
    $parsed = match ($format) {
        'fit' => atividadeArquivoFit($binary),
        'tcx' => atividadeArquivoTcx($binary),
        'gpx' => atividadeArquivoGpx($binary),
    };
    $summary = is_array($parsed['summary'] ?? null) ? $parsed['summary'] : [];
    $series = is_array($parsed['series'] ?? null) ? $parsed['series'] : [];
    $coordinates = is_array($summary['coordinates'] ?? null) ? $summary['coordinates'] : [];
    unset($summary['coordinates']);
    $route = count($coordinates) >= 2 ? ['type' => 'LineString', 'coordinates' => atividadeArquivoSimplificarRota($coordinates)] : null;
    $title = trim((string) ($parsed['title'] ?? ''));
    $sport = strtolower(trim((string) ($parsed['sport'] ?? 'generic')));
    if (in_array($sport, ['', 'generic', 'unknown', 'other'], true)) {
        $hint = strtolower($name . ' ' . $title);
        if (preg_match('/\b(corrida|correr|running|run)\b/u', $hint)) $sport = 'running';
        elseif (preg_match('/\b(ciclismo|cycling|bike|biking|bicicleta|pedal)\b/u', $hint)) $sport = 'cycling';
        elseif (preg_match('/\b(caminhada|walking|walk)\b/u', $hint)) $sport = 'walking';
        elseif (preg_match('/\b(trilha|hiking|hike)\b/u', $hint)) $sport = 'hiking';
    }
    $parsed['sport'] = $sport;
    $slug = atividadeArquivoSportSlug($sport, isset($parsed['sub_sport']) ? (string) $parsed['sub_sport'] : null);
    return [
        'format' => $format,
        'file_type' => (string) ($parsed['file_type'] ?? 'desconhecido'),
        'title' => $title !== '' ? $title : null,
        'sport' => (string) ($parsed['sport'] ?? 'generic'),
        'sub_sport' => $parsed['sub_sport'] ?? null,
        'modality_slug' => $slug,
        'summary' => $summary,
        'series' => $series,
        'route' => $route,
        'device' => is_array($parsed['device'] ?? null) ? $parsed['device'] : [],
        'source_meta' => is_array($parsed['fit'] ?? null) ? ['fit' => $parsed['fit']] : [],
    ];
}

function atividadeArquivoModalidade(PDO $pdo, string $idUsuario, string $slug): array
{
    $stmt = $pdo->prepare(
        "SELECT m.idmodalidade, m.nome, m.slug, mm.idmodelo
         FROM modalidades m
         JOIN modelos_modalidade mm ON mm.idmodalidade = m.idmodalidade AND mm.ativo = TRUE
         WHERE m.ativo = TRUE AND lower(m.slug) = lower(:slug)
           AND (m.idusuario IS NULL OR m.idusuario = :usuario_modalidade)
           AND (mm.idusuario IS NULL OR mm.idusuario = :usuario_modelo)
         ORDER BY mm.padrao DESC, mm.versao DESC LIMIT 1"
    );
    $stmt->execute([':slug' => $slug, ':usuario_modalidade' => $idUsuario, ':usuario_modelo' => $idUsuario]);
    $row = $stmt->fetch();
    if ($row) return $row;
    if ($slug !== 'outra-atividade') return atividadeArquivoModalidade($pdo, $idUsuario, 'outra-atividade');
    throw new RuntimeException('Nenhum modelo de atividade está disponível para importar.');
}

function atividadeArquivoEncontrarDuplicata(PDO $pdo, string $idUsuario, array $summary, string $sha256): ?array
{
    $exact = $pdo->prepare("SELECT ai.idregistro, ra.titulo, ra.data_inicio FROM stridebr.atividade_importacoes ai LEFT JOIN registros_atividade ra ON ra.idregistro = ai.idregistro AND ra.excluido_em IS NULL WHERE ai.idusuario = :usuario AND ai.sha256 = :hash AND ai.status = 'importado' ORDER BY ai.data_criacao DESC LIMIT 1");
    $exact->execute([':usuario' => $idUsuario, ':hash' => $sha256]);
    if ($row = $exact->fetch()) return ['type' => 'arquivo', 'activity' => $row];
    $start = $summary['start_ts'] ?? null;
    if (!is_int($start)) return null;
    $startAt = (new DateTimeImmutable('@' . $start))->format('Y-m-d H:i:sP');
    $distance = is_numeric($summary['distance_m'] ?? null) ? (float) $summary['distance_m'] : null;
    $duration = is_numeric($summary['duration_s'] ?? null) ? (float) $summary['duration_s'] : null;
    $stmt = $pdo->prepare("SELECT ra.idregistro, ra.titulo, ra.data_inicio, r.distancia_metros,
                                  EXTRACT(EPOCH FROM (ra.data_fim - ra.data_inicio)) AS duracao_s
                           FROM registros_atividade ra
                           LEFT JOIN rotas_atividade r ON r.idregistro = ra.idregistro
                           WHERE ra.idusuario = :usuario
                             AND ra.excluido_em IS NULL
                             AND ra.data_inicio BETWEEN CAST(:inicio_min AS timestamptz) - INTERVAL '4 minutes' AND CAST(:inicio_max AS timestamptz) + INTERVAL '4 minutes'
                           ORDER BY ABS(EXTRACT(EPOCH FROM (ra.data_inicio - CAST(:inicio_ordem AS timestamptz)))) LIMIT 8");
    $stmt->execute([
        ':usuario' => $idUsuario,
        ':inicio_min' => $startAt,
        ':inicio_max' => $startAt,
        ':inicio_ordem' => $startAt,
    ]);
    foreach ($stmt->fetchAll() as $row) {
        $distanceMatch = $distance === null || $row['distancia_metros'] === null || abs((float) $row['distancia_metros'] - $distance) <= max(120.0, $distance * .025);
        $durationMatch = $duration === null || $row['duracao_s'] === null || abs((float) $row['duracao_s'] - $duration) <= max(90.0, $duration * .04);
        if ($distanceMatch && $durationMatch) return ['type' => 'semelhante', 'activity' => $row];
    }
    return null;
}

function atividadeArquivoCriarPreview(PDO $pdo, string $idUsuario, array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('Não foi possível receber o arquivo.');
    $name = trim((string) ($file['name'] ?? 'atividade'));
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_file($tmp)) throw new InvalidArgumentException('Arquivo temporário indisponível.');
    $binary = file_get_contents($tmp);
    if ($binary === false) throw new InvalidArgumentException('Não foi possível ler o arquivo.');

    try {
        $parsed = atividadeArquivoAnalisar($name, $binary);
    } catch (InvalidArgumentException $e) {
        throw $e;
    } catch (Throwable $e) {
        throw new RuntimeException('import_parse|' . $e->getMessage(), 0, $e);
    }

    $hash = hash('sha256', $binary);
    try {
        $duplicate = atividadeArquivoEncontrarDuplicata($pdo, $idUsuario, $parsed['summary'], $hash);
    } catch (Throwable $e) {
        throw new RuntimeException('import_duplicate|' . $e->getMessage(), 0, $e);
    }

    try {
        $modalidade = atividadeArquivoModalidade($pdo, $idUsuario, $parsed['modality_slug']);
    } catch (Throwable $e) {
        throw new RuntimeException('import_modality|' . $e->getMessage(), 0, $e);
    }

    $id = atividadeGerarId();
    $rawStored = strlen($binary) <= 8388608 ? $binary : null;
    $summaryJson = json_encode(['title' => $parsed['title'], 'sport' => $parsed['sport'], 'sub_sport' => $parsed['sub_sport'], 'summary' => $parsed['summary'], 'source_meta' => $parsed['source_meta']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $seriesJson = json_encode($parsed['series'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $routeJson = $parsed['route'] ? json_encode($parsed['route'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : null;
    $deviceJson = json_encode($parsed['device'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    try {
        if ($rawStored === null) {
            $stmt = $pdo->prepare("INSERT INTO stridebr.atividade_importacoes
                (idimportacao, idusuario, formato, tipo_arquivo, nome_arquivo, mime_type, tamanho_bytes, sha256, modalidade_detectada, resumo, series_temporais, rota_geojson, dispositivo, arquivo_original, status)
                VALUES (:id, :usuario, :formato, :tipo, :nome, :mime, :tamanho, :hash, :modalidade, CAST(:resumo AS jsonb), CAST(:series AS jsonb), CAST(:rota AS jsonb), CAST(:dispositivo AS jsonb), NULL, 'pendente')");
        } else {
            $stmt = $pdo->prepare("INSERT INTO stridebr.atividade_importacoes
                (idimportacao, idusuario, formato, tipo_arquivo, nome_arquivo, mime_type, tamanho_bytes, sha256, modalidade_detectada, resumo, series_temporais, rota_geojson, dispositivo, arquivo_original, status)
                VALUES (:id, :usuario, :formato, :tipo, :nome, :mime, :tamanho, :hash, :modalidade, CAST(:resumo AS jsonb), CAST(:series AS jsonb), CAST(:rota AS jsonb), CAST(:dispositivo AS jsonb), decode(:arquivo_hex, 'hex'), 'pendente')");
        }
        $params = [
            ':id' => $id,
            ':usuario' => $idUsuario,
            ':formato' => $parsed['format'],
            ':tipo' => $parsed['file_type'],
            ':nome' => substr($name, 0, 255),
            ':mime' => trim((string) ($file['type'] ?? '')) ?: null,
            ':tamanho' => strlen($binary),
            ':hash' => $hash,
            ':modalidade' => $parsed['modality_slug'],
            ':resumo' => $summaryJson,
            ':series' => $seriesJson,
            ':rota' => $routeJson,
            ':dispositivo' => $deviceJson,
        ];
        if ($rawStored !== null) $params[':arquivo_hex'] = bin2hex($rawStored);
        $stmt->execute($params);
    } catch (Throwable $e) {
        throw new RuntimeException('import_store|' . $e->getMessage(), 0, $e);
    }

    return [
        'id' => $id,
        'format' => strtoupper($parsed['format']),
        'file_type' => $parsed['file_type'],
        'file_name' => $name,
        'file_size' => strlen($binary),
        'modality' => ['id' => $modalidade['idmodalidade'], 'model' => $modalidade['idmodelo'], 'name' => $modalidade['nome'], 'slug' => $modalidade['slug']],
        'title' => $parsed['title'] ?: $modalidade['nome'],
        'start' => atividadeArquivoIso(is_int($parsed['summary']['start_ts'] ?? null) ? $parsed['summary']['start_ts'] : null),
        'duration_s' => is_numeric($parsed['summary']['duration_s'] ?? null) ? round((float) $parsed['summary']['duration_s']) : null,
        'distance_m' => is_numeric($parsed['summary']['distance_m'] ?? null) ? round((float) $parsed['summary']['distance_m'], 1) : null,
        'elevation_gain_m' => is_numeric($parsed['summary']['elevation_gain_m'] ?? null) ? round((float) $parsed['summary']['elevation_gain_m'], 1) : null,
        'avg_hr' => is_numeric($parsed['summary']['avg_hr'] ?? null) ? round((float) $parsed['summary']['avg_hr']) : null,
        'max_hr' => is_numeric($parsed['summary']['max_hr'] ?? null) ? round((float) $parsed['summary']['max_hr']) : null,
        'avg_cadence' => is_numeric($parsed['summary']['avg_cadence'] ?? null) ? round((float) $parsed['summary']['avg_cadence']) : null,
        'avg_power' => is_numeric($parsed['summary']['avg_power'] ?? null) ? round((float) $parsed['summary']['avg_power']) : null,
        'route_points' => is_array($parsed['route']['coordinates'] ?? null) ? count($parsed['route']['coordinates']) : 0,
        'route_preview' => is_array($parsed['route']['coordinates'] ?? null) ? atividadeArquivoSimplificarRota($parsed['route']['coordinates'], 140) : [],
        'stream_points' => count($parsed['series']),
        'device' => $parsed['device'],
        'duplicate' => $duplicate,
        'original_saved' => $rawStored !== null,
    ];
}

function atividadeArquivoIntervalo(float $seconds): string
{
    $seconds = max(0, (int) round($seconds));
    return sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
}

function atividadeArquivoPayloadCampos(PDO $pdo, string $idModelo, array $summary): array
{
    $fields = atividadeBuscarCamposModelo($pdo, $idModelo, true);
    $record = [];
    $unit = [];
    foreach ($fields as $field) {
        $slug = strtolower((string) ($field['slug'] ?? ''));
        $value = null;
        if ($slug === 'duracao' && is_numeric($summary['duration_s'] ?? null)) $value = atividadeArquivoIntervalo((float) $summary['duration_s']);
        elseif ($slug === 'distancia' && is_numeric($summary['distance_m'] ?? null)) {
            $symbol = strtolower((string) ($field['unidade_simbolo'] ?? ''));
            $value = $symbol === 'm' ? (float) $summary['distance_m'] : (float) $summary['distance_m'] / 1000.0;
        } elseif (in_array($slug, ['elevacao', 'desnivel'], true) && is_numeric($summary['elevation_gain_m'] ?? null)) $value = (float) $summary['elevation_gain_m'];
        elseif (in_array($slug, ['fc-media', 'fc_media', 'frequencia-cardiaca-media'], true) && is_numeric($summary['avg_hr'] ?? null)) $value = (int) round((float) $summary['avg_hr']);
        elseif (in_array($slug, ['fc-maxima', 'fc_maxima', 'frequencia-cardiaca-maxima'], true) && is_numeric($summary['max_hr'] ?? null)) $value = (int) round((float) $summary['max_hr']);
        elseif ($slug === 'cadencia' && is_numeric($summary['avg_cadence'] ?? null)) $value = (int) round((float) $summary['avg_cadence']);
        elseif ($slug === 'potencia' && is_numeric($summary['avg_power'] ?? null)) $value = (int) round((float) $summary['avg_power']);
        if ($value === null) continue;
        if (($field['escopo'] ?? '') === 'registro') $record[$field['idcampo']] = $value;
        else $unit[$field['idcampo']] = $value;
    }
    return ['record_values' => $record, 'unidades' => [['values' => $unit]]];
}

function atividadeArquivoConfirmarImportacao(PDO $pdo, string $idUsuario, string $idImportacao, array $options): string
{
    $stmt = $pdo->prepare("SELECT * FROM stridebr.atividade_importacoes WHERE idimportacao = :id AND idusuario = :usuario LIMIT 1 FOR UPDATE");
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $stmt->execute([':id' => $idImportacao, ':usuario' => $idUsuario]);
        $import = $stmt->fetch();
        if (!$import) throw new InvalidArgumentException('Importação não encontrada.');
        if ((string) $import['status'] === 'importado' && $import['idregistro']) {
            if ($ownsTransaction && $pdo->inTransaction()) $pdo->commit();
            return (string) $import['idregistro'];
        }
        if ((string) $import['tipo_arquivo'] !== 'atividade') throw new InvalidArgumentException('Este arquivo é um percurso/treino, não uma atividade gravada.');
        $summaryEnvelope = json_decode((string) $import['resumo'], true) ?: [];
        $summary = is_array($summaryEnvelope['summary'] ?? null) ? $summaryEnvelope['summary'] : [];
        $slug = trim((string) ($options['modalidade_slug'] ?? $import['modalidade_detectada'] ?? '')) ?: 'outra-atividade';
        $modalidade = atividadeArquivoModalidade($pdo, $idUsuario, $slug);
        $startTs = is_numeric($summary['start_ts'] ?? null) ? (int) $summary['start_ts'] : null;
        $startDate = trim((string) ($options['start_date'] ?? ''));
        $startTime = trim((string) ($options['start_time'] ?? ''));
        if ($startDate !== '' || $startTime !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{2}:\d{2}$/', $startTime)) throw new InvalidArgumentException('Informe uma data e um horário válidos.');
            $timezone = new DateTimeZone('America/Sao_Paulo');
            $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $startDate . ' ' . $startTime, $timezone);
            $errors = DateTimeImmutable::getLastErrors();
            if (!$dateTime || (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))) throw new InvalidArgumentException('Informe uma data e um horário válidos.');
            $startTs = $dateTime->getTimestamp();
        }
        if ($startTs === null) throw new InvalidArgumentException('O arquivo não informa quando a atividade começou.');
        $duplicateSummary = $summary;
        $duplicateSummary['start_ts'] = $startTs;
        $duplicate = atividadeArquivoEncontrarDuplicata($pdo, $idUsuario, $duplicateSummary, (string) $import['sha256']);
        if ($duplicate && empty($options['allow_duplicate'])) throw new InvalidArgumentException('Já existe uma atividade muito parecida. Marque a opção de duplicata para importar mesmo assim.');
        $duration = is_numeric($summary['duration_s'] ?? null) ? max(0.0, (float) $summary['duration_s']) : null;
        if ($duration === null && is_numeric($summary['start_ts'] ?? null) && is_numeric($summary['end_ts'] ?? null)) $duration = max(0.0, (float) $summary['end_ts'] - (float) $summary['start_ts']);
        $endTs = $duration !== null ? $startTs + (int) round($duration) : null;
        $fields = atividadeArquivoPayloadCampos($pdo, (string) $modalidade['idmodelo'], $summary);
        $route = json_decode((string) ($import['rota_geojson'] ?? ''), true);
        $title = trim((string) ($options['title'] ?? $summaryEnvelope['title'] ?? ''));
        if ($title === '') $title = (string) $modalidade['nome'];
        $payload = [
            'idmodelo' => (string) $modalidade['idmodelo'],
            'titulo' => $title,
            'observacoes' => trim((string) ($options['notes'] ?? '')),
            'data_inicio' => atividadeArquivoLocalDateTime($startTs),
            'data_fim' => $endTs !== null ? atividadeArquivoLocalDateTime($endTs) : '',
            'status' => 'concluido',
            'visibilidade' => in_array((string) ($options['visibility'] ?? ''), ['privado', 'amigos', 'publico'], true) ? (string) $options['visibility'] : 'privado',
            'origem' => 'importacao',
            'record_values' => $fields['record_values'],
            'unidades' => $fields['unidades'],
            'calorias_externas' => is_numeric($summary['calories'] ?? null) ? (float) $summary['calories'] : null,
            'fonte_calorias_externa' => strtoupper((string) $import['formato']),
        ];
        if (is_array($route) && ($route['type'] ?? '') === 'LineString' && count($route['coordinates'] ?? []) >= 2) {
            $payload['rota_coordenadas'] = $route;
            $payload['rota_modo'] = 'importada';
            $payload['rota_metricas'] = [
                'distancia_metros' => is_numeric($summary['distance_m'] ?? null) ? (float) $summary['distance_m'] : null,
                'ganho_elevacao_m' => is_numeric($summary['elevation_gain_m'] ?? null) ? (float) $summary['elevation_gain_m'] : null,
                'perda_elevacao_m' => is_numeric($summary['elevation_loss_m'] ?? null) ? (float) $summary['elevation_loss_m'] : null,
                'elevacao_min_m' => is_numeric($summary['elevation_min_m'] ?? null) ? (float) $summary['elevation_min_m'] : null,
                'elevacao_max_m' => is_numeric($summary['elevation_max_m'] ?? null) ? (float) $summary['elevation_max_m'] : null,
                'fonte_elevacao' => strtoupper((string) $import['formato']),
            ];
        }
        $idRegistro = atividadeSalvarRegistro($pdo, $idUsuario, $payload);
        $update = $pdo->prepare("UPDATE stridebr.atividade_importacoes SET idregistro = :registro, status = 'importado', data_atualizacao = NOW() WHERE idimportacao = :id AND idusuario = :usuario");
        $update->execute([':registro' => $idRegistro, ':id' => $idImportacao, ':usuario' => $idUsuario]);
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->commit();
        return $idRegistro;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function atividadeArquivoDescartarPreview(PDO $pdo, string $idUsuario, string $idImportacao): void
{
    $stmt = $pdo->prepare("UPDATE stridebr.atividade_importacoes SET status = 'descartado', data_atualizacao = NOW() WHERE idimportacao = :id AND idusuario = :usuario AND status = 'pendente'");
    $stmt->execute([':id' => $idImportacao, ':usuario' => $idUsuario]);
}

function atividadeArquivoAplicarPercurso(PDO $pdo, string $idUsuario, string $idImportacao, string $idRegistro): string
{
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM stridebr.atividade_importacoes WHERE idimportacao = :id AND idusuario = :usuario LIMIT 1 FOR UPDATE");
        $stmt->execute([':id' => $idImportacao, ':usuario' => $idUsuario]);
        $import = $stmt->fetch();
        if (!$import) throw new InvalidArgumentException('Percurso não encontrado.');
        if ((string) $import['tipo_arquivo'] !== 'percurso') throw new InvalidArgumentException('Este arquivo não foi identificado como percurso/rota.');
        if ((string) $import['status'] !== 'pendente') throw new InvalidArgumentException('Este percurso já foi utilizado ou descartado.');

        $activity = $pdo->prepare("SELECT ra.idregistro FROM stridebr.registros_atividade ra LEFT JOIN stridebr.rotas_atividade rota ON rota.idregistro = ra.idregistro WHERE ra.idregistro = :registro AND ra.idusuario = :usuario AND ra.excluido_em IS NULL AND rota.idregistro IS NULL LIMIT 1 FOR UPDATE OF ra");
        $activity->execute([':registro' => $idRegistro, ':usuario' => $idUsuario]);
        if (!$activity->fetchColumn()) throw new InvalidArgumentException('Escolha uma atividade sua que ainda não tenha rota.');

        $route = json_decode((string) ($import['rota_geojson'] ?? ''), true);
        if (!is_array($route) || ($route['type'] ?? '') !== 'LineString' || count($route['coordinates'] ?? []) < 2) throw new InvalidArgumentException('Este percurso não possui coordenadas suficientes.');
        $summaryEnvelope = json_decode((string) ($import['resumo'] ?? ''), true) ?: [];
        $summary = is_array($summaryEnvelope['summary'] ?? null) ? $summaryEnvelope['summary'] : [];
        $distance = is_numeric($summary['distance_m'] ?? null) ? (float) $summary['distance_m'] : atividadeArquivoDistanciaRota($route['coordinates']);
        $routeStmt = $pdo->prepare("INSERT INTO stridebr.rotas_atividade
            (idrota, idregistro, modo, coordenadas, distancia_metros, ganho_elevacao_m, perda_elevacao_m, elevacao_min_m, elevacao_max_m, perfil_elevacao, fonte_elevacao, data_atualizacao)
            VALUES (:idrota, :registro, 'importada', CAST(:coordenadas AS jsonb), :distancia, :ganho, :perda, :minima, :maxima, NULL, :fonte, NOW())");
        $routeStmt->execute([
            ':idrota' => atividadeGerarId(),
            ':registro' => $idRegistro,
            ':coordenadas' => json_encode($route, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ':distancia' => round($distance, 3),
            ':ganho' => is_numeric($summary['elevation_gain_m'] ?? null) ? (float) $summary['elevation_gain_m'] : null,
            ':perda' => is_numeric($summary['elevation_loss_m'] ?? null) ? (float) $summary['elevation_loss_m'] : null,
            ':minima' => is_numeric($summary['elevation_min_m'] ?? null) ? (float) $summary['elevation_min_m'] : null,
            ':maxima' => is_numeric($summary['elevation_max_m'] ?? null) ? (float) $summary['elevation_max_m'] : null,
            ':fonte' => strtoupper((string) $import['formato']),
        ]);
        $update = $pdo->prepare("UPDATE stridebr.atividade_importacoes SET idregistro = :registro, status = 'importado', data_atualizacao = NOW() WHERE idimportacao = :id AND idusuario = :usuario");
        $update->execute([':registro' => $idRegistro, ':id' => $idImportacao, ':usuario' => $idUsuario]);
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->commit();
        return $idRegistro;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function atividadeArquivoImportacaoDoRegistro(PDO $pdo, string $idUsuario, string $idRegistro): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM stridebr.atividade_importacoes WHERE idusuario = :usuario AND idregistro = :registro AND status = 'importado' ORDER BY data_criacao DESC LIMIT 1");
    $stmt->execute([':usuario' => $idUsuario, ':registro' => $idRegistro]);
    return $stmt->fetch() ?: null;
}

function atividadeArquivoXmlEscape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function atividadeArquivoExportData(PDO $pdo, string $idUsuario, string $idRegistro): array
{
    $record = atividadeCarregarRegistro($pdo, $idRegistro, $idUsuario);
    if ($record === []) throw new InvalidArgumentException('Atividade não encontrada.');
    $fieldById = [];
    foreach ($record['campos'] ?? [] as $field) $fieldById[(string) $field['idcampo']] = $field;
    $known = [];
    foreach ($record['record_values'] ?? [] as $id => $value) {
        $slug = (string) ($fieldById[(string) $id]['slug'] ?? '');
        if ($slug !== '') $known[$slug] = $value;
    }
    foreach ($record['unidades'] ?? [] as $unit) {
        foreach ($unit['values'] ?? [] as $id => $value) {
            $slug = (string) ($fieldById[(string) $id]['slug'] ?? '');
            if ($slug !== '' && !array_key_exists($slug, $known)) $known[$slug] = $value;
        }
    }
    $route = null;
    if (is_array($record['rota'] ?? null)) {
        $decoded = json_decode((string) ($record['rota']['coordenadas'] ?? ''), true);
        if (is_array($decoded) && ($decoded['type'] ?? '') === 'LineString') $route = $decoded;
    }
    $import = atividadeArquivoImportacaoDoRegistro($pdo, $idUsuario, $idRegistro);
    $series = $import ? (json_decode((string) $import['series_temporais'], true) ?: []) : [];
    return ['record' => $record, 'known' => $known, 'route' => $route, 'import' => $import, 'series' => is_array($series) ? $series : []];
}

function atividadeArquivoKnownDuration(array $data): ?float
{
    $value = $data['known']['duracao'] ?? null;
    if (is_string($value) && preg_match('/^(\d+):([0-5]\d):([0-5]\d)$/', $value, $m)) return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3];
    $start = strtotime((string) ($data['record']['data_inicio'] ?? ''));
    $end = strtotime((string) ($data['record']['data_fim'] ?? ''));
    return $start !== false && $end !== false && $end >= $start ? (float) ($end - $start) : null;
}

function atividadeArquivoExportGpx(array $data): string
{
    $record = $data['record'];
    $series = $data['series'];
    $route = $data['route'];
    $title = atividadeArquivoXmlEscape((string) ($record['titulo'] ?: $record['modalidade_nome']));
    $type = atividadeArquivoXmlEscape((string) $record['modalidade_nome']);
    $points = [];
    if ($series !== []) {
        foreach ($series as $point) if (isset($point['lat'], $point['lon'])) $points[] = $point;
    } elseif (is_array($route['coordinates'] ?? null)) {
        $duration = atividadeArquivoKnownDuration($data);
        $startTs = strtotime((string) $record['data_inicio']);
        $count = count($route['coordinates']);
        foreach ($route['coordinates'] as $index => $coord) {
            $point = ['lon' => $coord[0], 'lat' => $coord[1]];
            if ($startTs !== false && $duration !== null && $count > 1) $point['timestamp'] = $startTs + (int) round($duration * $index / ($count - 1));
            $points[] = $point;
        }
    }
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<gpx version="1.1" creator="StrideBR" xmlns="http://www.topografix.com/GPX/1/1" xmlns:gpxtpx="http://www.garmin.com/xmlschemas/TrackPointExtension/v1">' . "\n";
    $xml .= '  <trk><name>' . $title . '</name><type>' . $type . '</type><trkseg>' . "\n";
    foreach ($points as $point) {
        $xml .= '    <trkpt lat="' . number_format((float) $point['lat'], 7, '.', '') . '" lon="' . number_format((float) $point['lon'], 7, '.', '') . '">';
        if (is_numeric($point['altitude_m'] ?? null)) $xml .= '<ele>' . number_format((float) $point['altitude_m'], 2, '.', '') . '</ele>';
        if (is_int($point['timestamp'] ?? null)) $xml .= '<time>' . atividadeArquivoIso($point['timestamp']) . '</time>';
        if (isset($point['heart_rate']) || isset($point['cadence'])) {
            $xml .= '<extensions><gpxtpx:TrackPointExtension>';
            if (is_numeric($point['heart_rate'] ?? null)) $xml .= '<gpxtpx:hr>' . (int) $point['heart_rate'] . '</gpxtpx:hr>';
            if (is_numeric($point['cadence'] ?? null)) $xml .= '<gpxtpx:cad>' . (int) $point['cadence'] . '</gpxtpx:cad>';
            $xml .= '</gpxtpx:TrackPointExtension></extensions>';
        }
        $xml .= '</trkpt>' . "\n";
    }
    $xml .= '  </trkseg></trk>' . "\n</gpx>\n";
    return $xml;
}

function atividadeArquivoExportTcx(array $data): string
{
    $record = $data['record'];
    $series = $data['series'];
    $route = $data['route'];
    $startTs = strtotime((string) $record['data_inicio']);
    if ($startTs === false) $startTs = time();
    $duration = atividadeArquivoKnownDuration($data) ?? 0.0;
    $distance = is_numeric($record['rota']['distancia_metros'] ?? null) ? (float) $record['rota']['distancia_metros'] : null;
    if ($distance === null && is_numeric($data['known']['distancia'] ?? null)) $distance = (float) $data['known']['distancia'] * 1000.0;
    $sport = in_array((string) $record['modalidade_slug'], ['corrida', 'corrida-em-trilha', 'corrida-em-esteira', 'caminhada', 'trilha'], true) ? 'Running' : (str_contains((string) $record['modalidade_slug'], 'cicl') || in_array((string) $record['modalidade_slug'], ['mountain-bike', 'gravel', 'bmx'], true) ? 'Biking' : 'Other');
    $points = [];
    if ($series !== []) foreach ($series as $point) if (isset($point['lat'], $point['lon'])) $points[] = $point;
    elseif (is_array($route['coordinates'] ?? null)) {
        $count = count($route['coordinates']);
        foreach ($route['coordinates'] as $index => $coord) $points[] = ['lon' => $coord[0], 'lat' => $coord[1], 'timestamp' => $startTs + ($count > 1 ? (int) round($duration * $index / ($count - 1)) : 0)];
    }
    $id = atividadeArquivoIso($startTs);
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<TrainingCenterDatabase xmlns="http://www.garmin.com/xmlschemas/TrainingCenterDatabase/v2" xmlns:ns3="http://www.garmin.com/xmlschemas/ActivityExtension/v2"><Activities><Activity Sport="' . $sport . '"><Id>' . $id . '</Id>';
    $xml .= '<Lap StartTime="' . $id . '"><TotalTimeSeconds>' . number_format($duration, 1, '.', '') . '</TotalTimeSeconds><DistanceMeters>' . number_format((float) ($distance ?? 0), 1, '.', '') . '</DistanceMeters><Intensity>Active</Intensity><TriggerMethod>Manual</TriggerMethod><Track>';
    foreach ($points as $point) {
        $xml .= '<Trackpoint>';
        if (is_int($point['timestamp'] ?? null)) $xml .= '<Time>' . atividadeArquivoIso($point['timestamp']) . '</Time>';
        $xml .= '<Position><LatitudeDegrees>' . number_format((float) $point['lat'], 7, '.', '') . '</LatitudeDegrees><LongitudeDegrees>' . number_format((float) $point['lon'], 7, '.', '') . '</LongitudeDegrees></Position>';
        if (is_numeric($point['altitude_m'] ?? null)) $xml .= '<AltitudeMeters>' . number_format((float) $point['altitude_m'], 2, '.', '') . '</AltitudeMeters>';
        if (is_numeric($point['distance_m'] ?? null)) $xml .= '<DistanceMeters>' . number_format((float) $point['distance_m'], 1, '.', '') . '</DistanceMeters>';
        if (is_numeric($point['heart_rate'] ?? null)) $xml .= '<HeartRateBpm><Value>' . (int) $point['heart_rate'] . '</Value></HeartRateBpm>';
        if (is_numeric($point['cadence'] ?? null)) $xml .= '<Cadence>' . (int) $point['cadence'] . '</Cadence>';
        if (is_numeric($point['speed_mps'] ?? null) || is_numeric($point['power'] ?? null)) {
            $xml .= '<Extensions><ns3:TPX>';
            if (is_numeric($point['speed_mps'] ?? null)) $xml .= '<ns3:Speed>' . number_format((float) $point['speed_mps'], 3, '.', '') . '</ns3:Speed>';
            if (is_numeric($point['power'] ?? null)) $xml .= '<ns3:Watts>' . (int) $point['power'] . '</ns3:Watts>';
            $xml .= '</ns3:TPX></Extensions>';
        }
        $xml .= '</Trackpoint>';
    }
    $xml .= '</Track></Lap><Creator xsi:type="Device_t" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><Name>StrideBR</Name><UnitId>0</UnitId><ProductID>0</ProductID><Version><VersionMajor>1</VersionMajor><VersionMinor>0</VersionMinor><BuildMajor>0</BuildMajor><BuildMinor>0</BuildMinor></Version></Creator></Activity></Activities></TrainingCenterDatabase>';
    return $xml;
}
