<?php

declare(strict_types=1);

function atividadeContextoEsportivoConfig(): array
{
    return [
        'distance_switch_m' => 1000.0,
        'track_meters_max' => 10000.0,
        'series_abs_tolerance_m' => 2.0,
        'series_rel_tolerance' => 0.01,
        'nominal_distances_m' => [
            'atletismo-60m' => 60.0,
            'atletismo-100m' => 100.0,
            'atletismo-200m' => 200.0,
            'atletismo-400m' => 400.0,
            'atletismo-800m' => 800.0,
            'atletismo-1500m' => 1500.0,
            'atletismo-3000m' => 3000.0,
            'atletismo-5000m' => 5000.0,
            'atletismo-10000m' => 10000.0,
            '60m-com-barreiras' => 60.0,
            '100m-com-barreiras' => 100.0,
            '110m-com-barreiras' => 110.0,
            '400m-com-barreiras' => 400.0,
            '3000m-com-obstaculos' => 3000.0,
        ],
        'track_slugs' => [
            'atletismo', 'corrida-em-pista', 'track-running', 'track-and-field',
            'atletismo-60m', 'atletismo-100m', 'atletismo-200m', 'atletismo-400m', 'atletismo-800m', 'atletismo-1500m', 'atletismo-3000m', 'atletismo-5000m', 'atletismo-10000m',
            '60m-com-barreiras', '100m-com-barreiras', '110m-com-barreiras', '400m-com-barreiras', '3000m-com-obstaculos', 'revezamento-4x100m', 'revezamento-4x400m',
        ],
        'sprint_slugs' => [
            'atletismo-60m', 'atletismo-100m', 'atletismo-200m', 'atletismo-400m',
            '60m-com-barreiras', '100m-com-barreiras', '110m-com-barreiras', '400m-com-barreiras',
        ],
    ];
}

function atividadeContextoEsportivo(string $slug = '', string $family = '', array $extra = []): array
{
    $config = atividadeContextoEsportivoConfig();
    $slug = stridebr_lower(trim($slug));
    $family = stridebr_lower(trim($family));
    $nominal = $config['nominal_distances_m'][$slug] ?? null;
    $isTrack = in_array($slug, $config['track_slugs'], true);
    if (!$isTrack && preg_match('/^(?:atletismo-)?(?:60|100|110|200|400|800|1000|1500|3000|5000|10000)m(?:-|$)/', $slug)) $isTrack = true;
    $isSprint = in_array($slug, $config['sprint_slugs'], true) || ($isTrack && $nominal !== null && $nominal <= 400.0);
    $registeredMeters = isset($extra['registered_m']) && is_numeric($extra['registered_m']) ? max(0.0, (float) $extra['registered_m']) : null;
    $segment = !empty($extra['segment']);
    $structuredSeries = !empty($extra['structured_series']);
    return [
        'slug' => $slug,
        'family' => $family,
        'is_track' => $isTrack,
        'is_sprint' => $isSprint,
        'nominal_distance_m' => $nominal,
        'registered_m' => $registeredMeters,
        'segment' => $segment,
        'structured_series' => $structuredSeries,
        'prefers_milliseconds' => $isSprint || ($segment && $registeredMeters !== null && $registeredMeters > 0 && $registeredMeters <= 400.0) || ($isTrack && $registeredMeters !== null && $registeredMeters > 0 && $registeredMeters <= 400.0),
        'performance_priority' => $isSprint ? 'time' : ($isTrack ? 'time_pace' : 'default'),
    ];
}

function atividadeContextoDistanciaUnidade(float $meters, array $context = [], ?string $manualUnit = null): string
{
    $config = atividadeContextoEsportivoConfig();
    if (in_array($manualUnit, ['m', 'km'], true)) return $manualUnit;
    $meters = max(0.0, $meters);
    $nominal = isset($context['nominal_distance_m']) && is_numeric($context['nominal_distance_m']) ? (float) $context['nominal_distance_m'] : null;
    if ($nominal !== null && !empty($context['is_track']) && $nominal <= $config['track_meters_max']) return 'm';
    if (!empty($context['is_track']) && $meters > 0 && $meters <= $config['track_meters_max']) return 'm';
    if (!empty($context['segment']) && !empty($context['structured_series']) && (($context['series_unit'] ?? '') === 'm')) return 'm';
    if ($meters > 0 && $meters < $config['distance_switch_m']) return 'm';
    return 'km';
}

function atividadeContextoFormatarDistancia(float $meters, array $context = [], ?string $locale = null, ?string $manualUnit = null): string
{
    $meters = max(0.0, $meters);
    $unit = atividadeContextoDistanciaUnidade($meters, $context, $manualUnit);
    if ($unit === 'm') {
        $rounded = round($meters, 3);
        $decimals = abs($rounded - round($rounded)) < 0.0005 ? 0 : 3;
        return stridebr_format_number($rounded, $decimals, true, $locale) . ' m';
    }
    $km = $meters / 1000.0;
    return stridebr_format_number($km, 3, true, $locale) . ' km';
}

function atividadeContextoFormatarTempo(float $seconds, ?string $locale = null, bool $forceClock = false): string
{
    $seconds = max(0.0, $seconds);
    $totalMs = (int) round($seconds * 1000);
    $hours = intdiv($totalMs, 3600000);
    $remaining = $totalMs % 3600000;
    $minutes = intdiv($remaining, 60000);
    $remaining %= 60000;
    $wholeSeconds = intdiv($remaining, 1000);
    $milliseconds = $remaining % 1000;
    $normalizedLocale = function_exists('stridebr_normalize_locale') ? stridebr_normalize_locale($locale ?? stridebr_locale()) : (($locale === 'en') ? 'en' : 'pt-BR');
    $separator = $normalizedLocale === 'pt-BR' ? ',' : '.';
    $fraction = '';
    if ($milliseconds > 0) {
        $fractionDigits = rtrim(str_pad((string) $milliseconds, 3, '0', STR_PAD_LEFT), '0');
        $fraction = $separator . $fractionDigits;
    }
    if ($hours > 0) return sprintf('%d:%02d:%02d', $hours, $minutes, $wholeSeconds) . $fraction;
    if ($minutes > 0 || $forceClock) return sprintf('%d:%02d', $minutes, $wholeSeconds) . $fraction;
    return (string) $wholeSeconds . $fraction . ' s';
}

function atividadeContextoFormatarRitmo(float $secondsPerUnit, string $suffix = '/km'): string
{
    if (!is_finite($secondsPerUnit) || $secondsPerUnit <= 0) return '';
    $total = (int) round($secondsPerUnit);
    return intdiv($total, 60) . ':' . str_pad((string) ($total % 60), 2, '0', STR_PAD_LEFT) . $suffix;
}

function atividadeContextoSerieEquivalente(array $meters, array $context = []): ?array
{
    $values = array_values(array_filter(array_map(static fn($value): ?float => is_numeric($value) && (float) $value > 0 ? (float) $value : null, $meters), static fn($value): bool => $value !== null));
    if (count($values) < 2) return null;
    sort($values, SORT_NUMERIC);
    $mid = intdiv(count($values), 2);
    $median = $values[$mid];
    if (count($values) % 2 === 0) $median = ($values[$mid - 1] + $values[$mid]) / 2;
    $config = atividadeContextoEsportivoConfig();
    $tolerance = max((float) $config['series_abs_tolerance_m'], abs($median) * (float) $config['series_rel_tolerance']);
    foreach ($values as $value) if (abs($value - $median) > $tolerance) return null;
    $nominal = isset($context['nominal_distance_m']) && is_numeric($context['nominal_distance_m']) ? (float) $context['nominal_distance_m'] : null;
    if ($nominal !== null && abs($median - $nominal) <= max($tolerance, $nominal * 0.01)) $median = $nominal;
    $rounded = abs($median - round($median)) < 0.25 ? round($median) : round($median, 1);
    $seriesContext = $context + ['segment' => true, 'structured_series' => true, 'series_unit' => (!empty($context['is_track']) || $rounded < 1000 ? 'm' : atividadeContextoDistanciaUnidade((float) $rounded, $context))];
    return [
        'count' => count($values),
        'distance_m' => (float) $rounded,
        'unit' => atividadeContextoDistanciaUnidade((float) $rounded, $seriesContext),
        'tolerance_m' => $tolerance,
    ];
}

function atividadeContextoJsConfigScript(): string
{
    $config = atividadeContextoEsportivoConfig();
    return '<script type="application/json" id="activity-sport-context-config">' . json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
}
