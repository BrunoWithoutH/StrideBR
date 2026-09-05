<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/src/includes/app.php';
require_once $root . '/src/function/atividade_modelo.php';
require_once $root . '/src/function/activity_file_exchange.php';

$assertions = 0;
$ok = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "✗ circuit export: {$message}\n");
        exit(1);
    }
};

$base = [
    [-53.3900, -27.3600],
    [-53.3890, -27.3600],
    [-53.3890, -27.3610],
    [-53.3900, -27.3610],
    [-53.3900, -27.3600],
];
$coordinates = [$base[0]];
for ($lap = 0; $lap < 10; $lap++) {
    foreach (array_slice($base, 1) as $point) $coordinates[] = $point;
}
$ok(count($coordinates) === 41, 'fixture de 10 voltas usa 41 pontos sem duplicar junções');

$data = [
    'record' => [
        'titulo' => 'Circuito teste',
        'modalidade_nome' => 'Corrida',
        'modalidade_slug' => 'corrida',
        'data_inicio' => '2026-09-03 12:00:00',
        'data_fim' => '2026-09-03 12:06:43',
        'rota' => ['distancia_metros' => atividadeDistanciaRota($coordinates)],
    ],
    'known' => ['duracao' => '00:06:42.500'],
    'route' => ['type' => 'LineString', 'coordinates' => $coordinates],
    'series' => [],
];

$ok(abs((atividadeArquivoKnownDuration($data) ?? 0) - 402.5) < 0.000001, 'exportador preserva duração fracionária');
$gpx = atividadeArquivoExportGpx($data);
$tcx = atividadeArquivoExportTcx($data);
$ok(substr_count($gpx, '<trkpt ') === 41, 'GPX exporta toda a geometria das 10 voltas');
$ok(substr_count($tcx, '<Trackpoint>') === 41, 'TCX exporta toda a geometria das 10 voltas');
$ok(str_contains($gpx, '.063Z</time>') || preg_match('/<time>[^<]+\.\d{3}Z<\/time>/', $gpx) === 1, 'GPX preserva timestamp subsegundo quando calculado');
$ok(str_contains($tcx, '.063Z</Time>') || preg_match('/<Time>[^<]+\.\d{3}Z<\/Time>/', $tcx) === 1, 'TCX preserva timestamp subsegundo quando calculado');
$ok(substr_count($gpx, 'lat="-27.3600000" lon="-53.3900000"') === 11, 'ponto inicial aparece no início e no fechamento de cada volta');

printf("✓ activity route circuit export: %d assertions\n", $assertions);
