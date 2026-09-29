<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
require_once dirname(__DIR__, 2) . '/src/function/integrations.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_presenter.php';

$root = dirname(__DIR__, 2);
$fixture = json_decode((string) file_get_contents(__DIR__ . '/fixtures/strava_activity.json'), true, 512, JSON_THROW_ON_ERROR);
$raceFixture = json_decode((string) file_get_contents(__DIR__ . '/fixtures/strava_activity_race.json'), true, 512, JSON_THROW_ON_ERROR);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException($message);
};
$throws = static function (callable $callback, string $message) use (&$checks): void {
    $checks++;
    try {
        $callback();
    } catch (StridebrIntegrationError) {
        return;
    }
    throw new RuntimeException($message);
};
$pace = static function (array $normalized): ?string {
    $metric = atividadeCardMetricaDerivada([
        'modalidade_slug' => 'corrida',
        'metrica_derivada' => 'pace_km',
        'usa_trechos' => false,
        'campos' => [
            ['idcampo' => 'distance', 'slug' => 'distancia', 'unidade_simbolo' => 'km'],
            ['idcampo' => 'duration', 'slug' => 'duracao', 'unidade_simbolo' => ''],
        ],
        'record_values' => [
            'distance' => ((float) ($normalized['distance_m'] ?? 0)) / 1000,
            'duration' => atividadeSegundosParaIntervalo((float) ($normalized['duration_s'] ?? 0)),
        ],
        'unidades' => [],
    ]);
    return is_array($metric) ? (string) ($metric['valor'] ?? '') : null;
};

try {
    $normal = stridebr_integrations_normalize_strava($fixture);
    $assert((int) $normal['duration_s'] === 1800, 'Paused Run precisa usar moving time como duração principal.');
    $assert((int) $normal['elapsed_time_s'] === 1864, 'Elapsed time precisa permanecer separado.');
    $assert(($normal['duration_basis'] ?? null) === 'moving_time', 'Paused Run precisa registrar a base moving_time.');
    $assert($pace($normal) === '5:51/km', 'Presenter precisa derivar aproximadamente 5:51/km do tempo principal do Strava.');

    $noPause = $fixture;
    $noPause['elapsed_time'] = 1800;
    $noPause['average_speed'] = $noPause['distance'] / 1800;
    $normalizedNoPause = stridebr_integrations_normalize_strava($noPause);
    $assert((int) $normalizedNoPause['duration_s'] === 1800 && ($normalizedNoPause['duration_basis'] ?? null) === 'moving_time', 'Run sem pausa não pode sofrer regressão.');

    $race = $raceFixture;
    $normalizedRace = stridebr_integrations_normalize_strava($race);
    $assert((int) $normalizedRace['duration_s'] === 1800 && ($normalizedRace['duration_basis'] ?? null) === 'elapsed_time', 'Run marcada como Race precisa usar elapsed time.');
    $assert($pace($normalizedRace) === '6:00/km', 'Race de 5 km em 1800 s precisa apresentar 6:00/km.');

    $averageMoving = $race;
    $averageMoving['workout_type'] = 0;
    $averageMoving['average_speed'] = 5000 / 1750;
    $normalizedAverageMoving = stridebr_integrations_normalize_strava($averageMoving);
    $assert((int) $normalizedAverageMoving['duration_s'] === 1750 && ($normalizedAverageMoving['duration_basis'] ?? null) === 'moving_time', 'Average speed coerente com moving precisa confirmar moving time.');

    $averageElapsed = $race;
    $averageElapsed['workout_type'] = 0;
    $normalizedAverageElapsed = stridebr_integrations_normalize_strava($averageElapsed);
    $assert((int) $normalizedAverageElapsed['duration_s'] === 1800 && ($normalizedAverageElapsed['duration_basis'] ?? null) === 'elapsed_time', 'Average speed coerente somente com elapsed precisa preservar a base do provider.');

    $missingMoving = $fixture;
    unset($missingMoving['moving_time']);
    $missingMoving['average_speed'] = null;
    $normalizedMissingMoving = stridebr_integrations_normalize_strava($missingMoving);
    $assert((int) $normalizedMissingMoving['duration_s'] === 1864 && ($normalizedMissingMoving['duration_basis'] ?? null) === 'elapsed_time', 'Sem moving time deve usar elapsed.');

    $missingElapsed = $fixture;
    unset($missingElapsed['elapsed_time']);
    $missingElapsed['average_speed'] = null;
    $normalizedMissingElapsed = stridebr_integrations_normalize_strava($missingElapsed);
    $assert((int) $normalizedMissingElapsed['duration_s'] === 1800 && ($normalizedMissingElapsed['duration_basis'] ?? null) === 'moving_time', 'Sem elapsed time deve manter moving como melhor duração disponível.');

    foreach ([null, 0] as $averageSpeed) {
        $variant = $fixture;
        $variant['average_speed'] = $averageSpeed;
        $normalized = stridebr_integrations_normalize_strava($variant);
        $assert((int) $normalized['duration_s'] === 1800, 'Average speed ausente ou zero não pode quebrar o fallback de Run.');
    }

    $zeroDistance = $fixture;
    $zeroDistance['distance'] = 0;
    $zeroDistance['average_speed'] = 0;
    $normalizedZeroDistance = stridebr_integrations_normalize_strava($zeroDistance);
    $assert((int) $normalizedZeroDistance['duration_s'] === 1800, 'Distância zero não pode causar divisão por zero.');

    $zeroMoving = $fixture;
    $zeroMoving['moving_time'] = 0;
    $zeroMoving['average_speed'] = null;
    $normalizedZeroMoving = stridebr_integrations_normalize_strava($zeroMoving);
    $assert((int) $normalizedZeroMoving['duration_s'] === 1864 && ($normalizedZeroMoving['duration_basis'] ?? null) === 'elapsed_time', 'Moving time zero não deve substituir elapsed válido.');

    $zeroElapsed = $fixture;
    $zeroElapsed['elapsed_time'] = 0;
    $zeroElapsed['average_speed'] = null;
    $normalizedZeroElapsed = stridebr_integrations_normalize_strava($zeroElapsed);
    $assert((int) $normalizedZeroElapsed['duration_s'] === 1800 && ($normalizedZeroElapsed['duration_basis'] ?? null) === 'moving_time', 'Elapsed time zero não deve substituir moving válido.');

    $yoga = $fixture;
    $yoga['sport_type'] = 'Yoga';
    $yoga['type'] = 'Yoga';
    $yoga['distance'] = 0;
    $yoga['average_speed'] = 0;
    $normalizedYoga = stridebr_integrations_normalize_strava($yoga);
    $assert((int) $normalizedYoga['duration_s'] === 1864 && ($normalizedYoga['duration_basis'] ?? null) === 'elapsed_time', 'Yoga não deve herdar moving-time semantics de corrida/ciclismo.');

    foreach (['moving_time', 'elapsed_time', 'distance', 'average_speed'] as $field) {
        $invalid = $fixture;
        $invalid[$field] = 'invalid';
        $throws(fn() => stridebr_integrations_normalize_strava($invalid), "Métrica inválida {$field} precisa ser rejeitada.");
    }

    $integrations = (string) file_get_contents($root . '/src/function/integrations.php');
    $hub = (string) file_get_contents($root . '/src/function/sport_hub.php');
    $activityJs = (string) file_get_contents($root . '/public/assets/js/atividades.js');
    $assert(str_contains($integrations, "'elapsed_time_s' => \$data['elapsed_time']") && str_contains($integrations, "\$elapsedDuration = is_numeric(\$activity['elapsed_time_s']"), 'Storage precisa separar duração principal do fim cronológico.');
    $assert(str_contains($integrations, "\$device['moving_time_s']") && str_contains($integrations, "\$device['elapsed_time_s']") && str_contains($integrations, "\$device['average_speed_mps']") && str_contains($integrations, "\$device['workout_type']"), 'Metadata Strava precisa preservar timings, average speed e workout type.');
    $assert(substr_count($hub, "ra.origem_provedor = 'strava' AND metric.duracao_s IS NOT NULL") === 2, 'Sport Hub e Progress precisam preferir a duração canônica apenas para Strava.');
    $assert(str_contains($activityJs, "const rawShareMetrics = activity?.metricas_compartilhamento || activity?.metricas || []"), 'Story/share precisa consumir as métricas canônicas do Activity Detail.');
    $assert(preg_match('/strava.{0,100}(pace|ritmo)|(pace|ritmo).{0,100}strava/i', $activityJs) !== 1, 'Story não pode ter cálculo de pace específico para Strava.');
    $assert(substr_count($integrations, 'stridebr_integrations_strava_primary_duration(') === 2, 'Seleção de duração Strava precisa ficar restrita ao helper e ao normalizador Strava.');

    echo "✓ Strava Activity Time & Pace Fidelity V1 static: {$checks} assertions\n";
} catch (Throwable $error) {
    fwrite(STDERR, "✗ Strava Activity Time & Pace Fidelity V1 static\n  {$error->getMessage()}\n");
    exit(1);
}
