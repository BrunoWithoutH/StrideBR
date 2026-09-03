<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
stridebr_require_login();
stridebr_session_release();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=3600');

try {
    $south = filter_input(INPUT_GET, 'south', FILTER_VALIDATE_FLOAT);
    $west = filter_input(INPUT_GET, 'west', FILTER_VALIDATE_FLOAT);
    $north = filter_input(INPUT_GET, 'north', FILTER_VALIDATE_FLOAT);
    $east = filter_input(INPUT_GET, 'east', FILTER_VALIDATE_FLOAT);

    foreach ([$south, $west, $north, $east] as $value) {
        if ($value === false || $value === null || !is_finite((float) $value)) {
            throw new InvalidArgumentException('Área do mapa inválida.');
        }
    }

    $south = (float) $south;
    $west = (float) $west;
    $north = (float) $north;
    $east = (float) $east;

    if ($south < -85 || $north > 85 || $west < -180 || $east > 180 || $south >= $north || $west >= $east) {
        throw new InvalidArgumentException('Área do mapa inválida.');
    }
    if (($north - $south) > 0.18 || ($east - $west) > 0.18) {
        throw new InvalidArgumentException('Área do mapa muito grande.');
    }

    $bbox = implode(',', array_map(static fn(float $value): string => number_format($value, 6, '.', ''), [$south, $west, $north, $east]));
    $key = hash('sha256', 'v2|' . $bbox);
    $cacheDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'stridebr-map-geometry';
    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . $key . '.json';

    if (is_file($cacheFile) && (time() - (int) filemtime($cacheFile)) < 86400) {
        $cached = file_get_contents($cacheFile);
        if (is_string($cached) && $cached !== '') {
            echo $cached;
            exit;
        }
    }

    $query = '[out:json][timeout:12];(' .
        'way["highway"~"^(motorway|trunk|primary|secondary|tertiary|residential|living_street|unclassified|service|road|pedestrian|footway|cycleway)$"](' . $bbox . ');' .
        'way["leisure"~"^(park|garden|recreation_ground|nature_reserve)$"](' . $bbox . ');' .
        'way["landuse"~"^(grass|forest|meadow|recreation_ground|cemetery|reservoir)$"](' . $bbox . ');' .
        'way["natural"~"^(water|wood|grassland)$"](' . $bbox . ');' .
        ');out geom;';
    $url = 'https://overpass-api.de/api/interpreter?data=' . rawurlencode($query);
    $body = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: StrideBR/1.0'],
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if (is_string($response) && $response !== '' && $status >= 200 && $status < 300) {
            $body = $response;
        }
    }

    if ($body === null) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'header' => "Accept: application/json\r\nUser-Agent: StrideBR/1.0\r\n",
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if (is_string($response) && $response !== '') {
            $body = $response;
        }
    }

    if ($body === null) {
        throw new RuntimeException('Serviço de geometria indisponível.');
    }

    $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    $roads = [];
    $areas = [];
    foreach (($decoded['elements'] ?? []) as $element) {
        if (($element['type'] ?? '') !== 'way' || !is_array($element['geometry'] ?? null) || count($element['geometry']) < 2) {
            continue;
        }
        $coordinates = [];
        foreach ($element['geometry'] as $point) {
            $lat = isset($point['lat']) ? (float) $point['lat'] : null;
            $lon = isset($point['lon']) ? (float) $point['lon'] : null;
            if ($lat === null || $lon === null) {
                continue;
            }
            $coordinates[] = [$lon, $lat];
        }
        if (count($coordinates) < 2) {
            continue;
        }
        $tags = is_array($element['tags'] ?? null) ? $element['tags'] : [];
        if (isset($tags['highway'])) {
            if (count($roads) < 360) {
                $roads[] = [
                    'kind' => (string) $tags['highway'],
                    'coordinates' => $coordinates,
                ];
            }
            continue;
        }
        if (count($coordinates) >= 3 && count($areas) < 90) {
            $kind = isset($tags['natural']) ? 'natural:' . (string) $tags['natural']
                : (isset($tags['landuse']) ? 'landuse:' . (string) $tags['landuse']
                : 'leisure:' . (string) ($tags['leisure'] ?? 'area'));
            $areas[] = ['kind' => $kind, 'coordinates' => $coordinates];
        }
    }

    $payload = json_encode(['ok' => true, 'roads' => $roads, 'areas' => $areas], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0700, true);
    }
    if (is_dir($cacheDir)) {
        @file_put_contents($cacheFile, $payload, LOCK_EX);
    }
    echo $payload;
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 400 : 503);
    if (!$e instanceof InvalidArgumentException) {
        error_log('StrideBR map geometry API: ' . $e->getMessage());
    }
    echo json_encode(['ok' => false, 'error' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Não foi possível carregar as ruas agora.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
