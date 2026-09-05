<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/app.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_presenter.php';
require_once dirname(__DIR__, 2) . '/src/function/activity_file_exchange.php';

$assertions = 0;
$ok = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "✗ activity duration precision: {$message}\n");
        exit(1);
    }
};
$near = static function (float $a, float $b, float $epsilon, string $message) use ($ok): void {
    $ok(abs($a - $b) <= $epsilon, $message . ": {$a} vs {$b}");
};

$near((float) atividadeIntervaloParaSegundos('00:00:12'), 12.0, 0.000001, 'duração inteira');
$near((float) atividadeIntervaloParaSegundos('00:00:12.438'), 12.438, 0.000001, 'duração precisa');
$near((float) atividadeIntervaloParaSegundos('00:00:12.4'), 12.400, 0.000001, 'uma casa normaliza em décimos');
$near((float) atividadeIntervaloParaSegundos('00:00:12.43'), 12.430, 0.000001, 'duas casas normalizam em centésimos');
$ok(atividadeIntervaloParaSegundos('00:00:12.4389') === null, 'mais de três casas rejeitadas');
$near((float) atividadeIntervaloParaSegundos('00:00:59.999'), 59.999, 0.000001, '59.999 preservado');
$near((float) atividadeIntervaloParaSegundos('00:01:02.345'), 62.345, 0.000001, 'acima de um minuto');
$near((float) atividadeIntervaloParaSegundos('01:02:03.456'), 3723.456, 0.000001, 'acima de uma hora');
$ok(atividadeSegundosParaIntervalo(12.0) === '00:00:12', 'inteiro não ganha .000');
$ok(atividadeSegundosParaIntervalo(12.438) === '00:00:12.438', 'intervalo canônico preserva ms');
$ok(atividadeSegundosParaIntervalo(62.345) === '00:01:02.345', 'intervalo canônico minuto');
$ok(atividadeSegundosParaIntervalo(3723.456) === '01:02:03.456', 'intervalo canônico hora');
$ok(stridebr_format_sport_duration(12.438, false, 'pt-BR') === '12,438 s', 'texto PT usa vírgula');
$ok(stridebr_format_sport_duration(12.438, false, 'en') === '12.438 s', 'texto EN usa ponto');
$ok(stridebr_format_sport_duration(62.345, true, 'pt-BR') === '1:02,345', 'relógio PT preserva fração');
$ok(stridebr_format_sport_duration(62.345, true, 'en') === '1:02.345', 'relógio EN preserva fração');
$ok(stridebr_format_sport_duration(12, false, 'pt-BR') === '12 s', 'produto não mostra .000');

$details = [
    'usa_trechos' => true,
    'metrica_derivada' => 'velocidade_kmh',
    'campos' => [
        ['idcampo' => 'distance', 'slug' => 'distancia', 'escopo' => 'unidade', 'unidade_simbolo' => 'm'],
        ['idcampo' => 'duration', 'slug' => 'duracao', 'escopo' => 'unidade'],
    ],
    'unidades' => [
        ['distancia_metros' => 100.0, 'duracao_segundos' => 12.438, 'modalidade_metrica_derivada' => 'velocidade_kmh', 'values' => []],
        ['distancia_metros' => 100.0, 'duracao_segundos' => 13.005, 'modalidade_metrica_derivada' => 'velocidade_kmh', 'values' => []],
    ],
];
$canonical = atividadeTotaisCanonicosUnidades($details);
$near((float) $canonical['duracao_s'], 25.443, 0.000001, 'soma de trechos preserva milissegundos');
$totals = atividadeCardTotaisUnidades($details);
$ok(($totals['duration'] ?? '') === '00:00:25.443', 'total apresentado preserva milissegundos dos trechos');
$single = $details;
$single['unidades'] = [$details['unidades'][0]];
$derived = atividadeCardMetricaDerivada($single);
$expectedSpeed = stridebr_format_number(0.1 / (12.438 / 3600), 1) . ' km/h';
$ok(($derived['valor'] ?? '') === $expectedSpeed, 'velocidade derivada usa duração fracionária');

$preciseTimestamp = atividadeArquivoTimestamp('2026-09-03T12:00:12.438Z');
$ok($preciseTimestamp !== null && str_ends_with((string) atividadeArquivoIso($preciseTimestamp), '12.438Z'), 'timestamp de import/export preserva milissegundos');
$ok(atividadeArquivoSegundosDecimal(12.438) === '12.438', 'TCX serializa milissegundos quando existem');
$ok(atividadeArquivoSegundosDecimal(12.0) === '12', 'TCX não inventa .000');
$exchangeData = [
    'record' => [
        'titulo' => '100 m preciso',
        'modalidade_nome' => 'Corrida',
        'modalidade_slug' => 'corrida',
        'data_inicio' => '2026-09-03 12:00:00',
        'data_fim' => '2026-09-03 12:00:12',
        'rota' => ['distancia_metros' => 100.0],
    ],
    'known' => ['duracao' => '00:00:12.438'],
    'route' => ['type' => 'LineString', 'coordinates' => [[-53.39, -27.36], [-53.389, -27.36]]],
    'series' => [],
];
$tcx = atividadeArquivoExportTcx($exchangeData);
$gpx = atividadeArquivoExportGpx($exchangeData);
$ok(str_contains($tcx, '<TotalTimeSeconds>12.438</TotalTimeSeconds>'), 'TCX exporta duração precisa');
$ok(str_contains($tcx, '12.438Z</Time>'), 'TCX exporta fim preciso da geometria');
$ok(str_contains($gpx, '12.438Z</time>'), 'GPX exporta fim preciso da geometria');

$migration = file_get_contents(dirname(__DIR__, 2) . '/src/database/migrations/20260903_z_activity_duration_precision_ms.sql');
$ok(str_contains($migration, 'ALTER COLUMN duracao_segundos TYPE NUMERIC(14,3)'), 'migration usa NUMERIC(14,3)');
$ok(str_contains($migration, 'USING duracao_segundos::NUMERIC(14,3)'), 'migration preserva valores existentes');
$ok(str_contains($migration, 'duracao_segundos IS NULL OR duracao_segundos >= 0'), 'constraint não negativa preservada');
$ok(!str_contains($migration, 'gravacoes_gps_web'), 'migration não altera GPS fora do escopo');
$js = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atividades.js');
$ok(str_contains($js, 'Math.round(totalSeconds * 1000)'), 'JS estabiliza precisão em 1 ms');
$ok(str_contains($js, 'sec * 1000 + ms) / 1000'), 'JS inclui ms no valor canônico');
$ok(!preg_match('/Math\.round\(\s*durationToSeconds/', $js), 'JS não arredonda durationToSeconds para segundo inteiro');
$ok(str_contains($js, 'const totalMilliseconds = Math.max(0, Math.round((Number(seconds) || 0) * 1000))'), 'share card não arredonda duração para segundo inteiro');
$ok(str_contains($js, "const decimal = i18n.locale === 'pt-BR' ? ',' : '.'"), 'share card respeita separador decimal do locale');
$edit = file_get_contents(dirname(__DIR__, 2) . '/public/user/editatividade.php');
$ok(str_contains($edit, 'name="duration_source" value="preserve"'), 'edição começa preservando a duração existente');
$ok(str_contains($edit, '$durationSource !== \'end\' && $postedDurationSeconds !== null'), 'edição só recalcula duração pelo horário final quando o usuário escolhe essa fonte');
$ok(str_contains($edit, '(int) floor(max(0.0, $postedDurationSeconds))'), 'horário final derivado não arredonda fração para o minuto seguinte');

echo "✓ activity duration precision: {$assertions} assertions\n";
