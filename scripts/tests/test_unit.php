<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_presenter.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma.php';
require_once dirname(__DIR__, 2) . '/src/function/strength_activity.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$throws = static function (callable $callback, string $message) use (&$assertions): void {
    $assertions++;
    try {
        $callback();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException($message);
};

try {
    $assert(stridebr_safe_redirect('/user/atividades.php') === '/user/atividades.php', 'Redirect interno válido foi rejeitado');
    $assert(stridebr_safe_redirect('https://example.com', '/home.php') === '/home.php', 'Redirect externo foi aceito');
    $assert(stridebr_safe_redirect('//example.com', '/home.php') === '/home.php', 'Redirect protocol-relative foi aceito');
    $assert(stridebr_username_is_valid('atleta_01'), 'Username válido foi rejeitado');
    $assert(!stridebr_username_is_valid('admin'), 'Username reservado foi aceito');
    $assert(!stridebr_username_is_valid('nome__duplo'), 'Separadores duplicados foram aceitos');

    $route = atividadeValidarRotaGeoJson(['type' => 'LineString', 'coordinates' => [[-51.23, -30.03], [-51.22, -30.02]]], false);
    $assert(count($route['coordinates']) === 2, 'Rota válida foi rejeitada');
    $assert(atividadeDistanciaRota($route['coordinates']) > 1000, 'Distância da rota não foi calculada');
    $throws(fn() => atividadeValidarRotaGeoJson(['type' => 'Point', 'coordinates' => [-51, -30]], false), 'GeoJSON que não é LineString foi aceito');

    $sampled = atividadeReamostrarRota([[-51.23, -30.03], [-51.22, -30.02]], 20);
    $assert(count($sampled) >= 2 && count($sampled) <= 20, 'Reamostragem não respeitou o limite');
    $profile = atividadeCalcularPerfilElevacao([[-51.23, -30.03], [-51.225, -30.025], [-51.22, -30.02]], [10, 20, 15]);
    $assert(is_array($profile) && isset($profile['ganho_elevacao_m'], $profile['perda_elevacao_m']), 'Perfil de elevação não foi calculado');

    $strengthMetrics = atividadeCardMetricas([
        'modalidade_slug' => 'musculacao',
        'treino_codigo' => 'C',
        'treino_foco' => 'Costas, bíceps e abdômen',
        'campos' => [['idcampo' => 'dur', 'slug' => 'duracao', 'rotulo' => 'Duração', 'tipo_campo' => 'intervalo', 'ordem' => 1]],
        'record_values' => [],
        'unidades' => [['values' => ['dur' => '00:45:00']]],
    ], 4);
    $assert(count($strengthMetrics) === 1 && ($strengthMetrics[0]['rotulo'] ?? '') === stridebr_t('activity.duration') && ($strengthMetrics[0]['valor'] ?? '') === '45:00', 'Histórico de musculação deve manter duração compacta sem promover código/foco a métricas');

    $segmentFields = [
        ['idcampo' => 'dist', 'slug' => 'distancia', 'rotulo' => 'Distância', 'tipo_campo' => 'decimal', 'escopo' => 'unidade', 'unidade_simbolo' => 'km', 'ordem' => 1],
        ['idcampo' => 'dur', 'slug' => 'duracao', 'rotulo' => 'Duração', 'tipo_campo' => 'intervalo', 'escopo' => 'unidade', 'unidade_simbolo' => '', 'ordem' => 2],
        ['idcampo' => 'elev', 'slug' => 'elevacao', 'rotulo' => 'Elevação', 'tipo_campo' => 'decimal', 'escopo' => 'unidade', 'unidade_simbolo' => 'm', 'ordem' => 3],
    ];
    $segmentDetails = [
        'usa_trechos' => true,
        'modalidade_slug' => 'corrida',
        'metrica_derivada' => 'pace_km',
        'campos' => $segmentFields,
        'record_values' => [],
        'unidades' => [
            ['distancia_metros' => 1000, 'duracao_segundos' => 300, 'elevacao_m' => 12, 'modalidade_metrica_derivada' => 'pace_km', 'values' => []],
            ['distancia_metros' => 500, 'duracao_segundos' => 150, 'elevacao_m' => 8, 'modalidade_metrica_derivada' => 'pace_km', 'values' => []],
        ],
    ];
    $segmentTotals = atividadeTotaisCanonicosUnidades($segmentDetails);
    $assert(abs((float) $segmentTotals['distancia_m'] - 1500.0) < 0.001 && (int) $segmentTotals['duracao_s'] === 450, 'Totais canônicos dos trechos estão incorretos');
    $segmentMetrics = atividadeCardMetricas($segmentDetails, 4);
    $segmentMetricMap = [];
    foreach ($segmentMetrics as $metric) $segmentMetricMap[(string) $metric['rotulo']] = (string) $metric['valor'];
    $assert(($segmentMetricMap[stridebr_t('activity.distance')] ?? '') === stridebr_format_number(1.5, 1, true) . ' km', 'Distância agregada dos trechos não foi exibida');
    $assert(($segmentMetricMap[stridebr_t('activity.duration')] ?? '') === '7:30', 'Duração agregada dos trechos não foi exibida com a política temporal atual');
    $assert(($segmentMetricMap[stridebr_t('activity.pace')] ?? '') === '5:00/km', 'Ritmo agregado dos trechos não foi calculado pelos totais');
    $mixedSegments = $segmentDetails;
    $mixedSegments['unidades'][1]['modalidade_metrica_derivada'] = 'velocidade_kmh';
    $mixedMetrics = atividadeCardMetricas($mixedSegments, 6);
    $assert(!in_array(stridebr_t('activity.pace'), array_column($mixedMetrics, 'rotulo'), true) && !in_array(stridebr_t('activity.speed'), array_column($mixedMetrics, 'rotulo'), true), 'Sessão com modalidades incompatíveis ganhou métrica derivada geral enganosa');

    $assert(cronogramaDuracaoMinutos(['hora_inicio' => '08:00:00', 'hora_fim' => '09:30:00', 'termina_dia_seguinte' => false]) === 90, 'Duração normal do treino incorreta');
    $assert(cronogramaDuracaoMinutos(['hora_inicio' => '23:30:00', 'hora_fim' => '00:30:00', 'termina_dia_seguinte' => true]) === 60, 'Duração atravessando meia-noite incorreta');

    $strengthInput = atividadeForcaNormalizarEntrada([[
        'nome' => 'Leg press',
        'series' => [
            ['tipo' => 'aquecimento', 'carga_kg' => '100,5', 'repeticoes' => '12', 'rir' => '4', 'concluida' => '1'],
            ['tipo' => 'trabalho', 'carga_kg' => '160', 'repeticoes' => '8', 'rir' => '2', 'concluida' => '0'],
            ['tipo' => 'trabalho', 'carga_kg' => '', 'repeticoes' => '', 'rir' => ''],
        ],
    ]]);
    $assert(count($strengthInput) === 1 && count($strengthInput[0]['series']) === 2, 'Normalização manual de força não descartou série vazia corretamente');
    $assert(abs((float) $strengthInput[0]['series'][0]['carga_kg'] - 100.5) < 0.001 && $strengthInput[0]['series'][0]['repeticoes'] === 12, 'Carga/repetições manuais foram normalizadas incorretamente');
    $assert($strengthInput[0]['series'][1]['concluida'] === false, 'Série manual desmarcada virou concluída');
    $throws(fn() => atividadeForcaNormalizarEntrada([['nome' => 'Teste', 'series' => [['carga_kg' => '100000', 'repeticoes' => '8']]]]), 'Carga manual absurda foi aceita');

    echo "✓ unit ($assertions assertions)\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "✗ unit\n  {$e->getMessage()}\n");
    exit(1);
}
