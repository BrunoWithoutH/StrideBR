<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/app.php';
require_once dirname(__DIR__, 2) . '/src/function/activity_sport_context.php';

$fixture = json_decode((string) file_get_contents(__DIR__ . '/fixtures/activity_sport_context.json'), true, 512, JSON_THROW_ON_ERROR);
$assertions = 0;
$ok = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "✗ activity sport context: {$message}\n");
        exit(1);
    }
};
$near = static function (float $a, float $b, float $epsilon, string $message) use ($ok): void {
    $ok(abs($a - $b) <= $epsilon, $message . ": {$a} vs {$b}");
};

foreach ($fixture['distances'] as $case) {
    $context = atividadeContextoEsportivo((string) $case['slug'], '', ['registered_m' => (float) $case['meters']]);
    $ok(atividadeContextoFormatarDistancia((float) $case['meters'], $context, 'pt-BR') === $case['pt'], $case['slug'] . ' distância pt-BR');
    $ok(atividadeContextoFormatarDistancia((float) $case['meters'], $context, 'en') === $case['en'], $case['slug'] . ' distância en');
}

foreach ($fixture['durations'] as $case) {
    $ok(atividadeContextoFormatarTempo((float) $case['seconds'], 'pt-BR', (bool) $case['forceClock']) === $case['pt'], 'duração pt-BR ' . $case['seconds']);
    $ok(atividadeContextoFormatarTempo((float) $case['seconds'], 'en', (bool) $case['forceClock']) === $case['en'], 'duração en ' . $case['seconds']);
}

foreach ($fixture['paces'] as $case) {
    $ok(atividadeContextoFormatarRitmo((float) $case['seconds']) === $case['value'], 'ritmo arredondado ' . $case['seconds']);
}

foreach ($fixture['series'] as $case) {
    $context = atividadeContextoEsportivo((string) $case['slug'], '', ['segment' => true]);
    $series = atividadeContextoSerieEquivalente($case['values'], $context);
    $ok(is_array($series), 'série equivalente ' . $case['slug']);
    $ok((int) ($series['count'] ?? 0) === (int) $case['count'], 'contagem da série ' . $case['slug']);
    $near((float) ($series['distance_m'] ?? 0), (float) $case['distance'], 0.001, 'distância comum da série ' . $case['slug']);
    $ok(($series['unit'] ?? '') === $case['unit'], 'unidade da série ' . $case['slug']);
}

$generic = atividadeContextoEsportivo('corrida', '', ['registered_m' => 5000]);
$track = atividadeContextoEsportivo('atletismo-5000m', '', ['registered_m' => 5000]);
$ok(atividadeContextoDistanciaUnidade(5000, $generic) === 'km', '5000 m corrida comum usa km');
$ok(atividadeContextoDistanciaUnidade(5000, $track) === 'm', '5000 m pista usa m');
$ok(atividadeContextoDistanciaUnidade(1500, atividadeContextoEsportivo('atletismo-1500m'), 'km') === 'km', 'override manual vence disciplina');
$ok(atividadeContextoDistanciaUnidade(400, atividadeContextoEsportivo('atletismo-400m'), 'km') === 'km', 'override manual m para km preservado');
$ok(atividadeContextoEsportivo('atletismo-100m')['nominal_distance_m'] === 100.0, '100 m possui distância nominal');
$ok(atividadeContextoEsportivo('atletismo-400m')['prefers_milliseconds'] === true, '400 m sugere ms');
$ok(atividadeContextoEsportivo('corrida', '', ['registered_m' => 5000])['prefers_milliseconds'] === false, 'corrida comum 5 km não sugere ms');
$ok(atividadeContextoSerieEquivalente([400, 411, 389], atividadeContextoEsportivo('atletismo-400m', '', ['segment' => true])) === null, 'série fora da tolerância não é inferida');

fwrite(STDOUT, "✓ activity sport context PHP: {$assertions} assertions\n");
