<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/src/includes/app.php';
require_once $root . '/src/function/sport_hub.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$activities = [
    ['data_inicio' => '2026-08-03 07:00:00-03', 'modalidade_slug' => 'corrida', 'modalidade_nome' => 'Corrida', 'hub_bucket' => 'endurance', 'duration_s' => 1800, 'distancia_metros' => 5000, 'ganho_elevacao_m' => 90],
    ['data_inicio' => '2026-08-04 18:00:00-03', 'modalidade_slug' => 'musculacao', 'modalidade_nome' => 'Musculação', 'hub_bucket' => 'strength', 'duration_s' => 0, 'distancia_metros' => 0, 'ganho_elevacao_m' => 0],
    ['data_inicio' => '2026-08-04 20:00:00-03', 'modalidade_slug' => 'corrida', 'modalidade_nome' => 'Corrida', 'hub_bucket' => 'endurance', 'duration_s' => 0, 'distancia_metros' => 0, 'ganho_elevacao_m' => 0],
    ['data_inicio' => '2026-08-12 08:00:00-03', 'modalidade_slug' => 'ciclismo', 'modalidade_nome' => 'Ciclismo', 'hub_bucket' => 'endurance', 'duration_s' => 3600, 'distancia_metros' => 28000, 'ganho_elevacao_m' => 250],
];

$sports = sportHubAvailableSports($activities);
$assert(count($sports) === 3, 'filtro oferece apenas modalidades existentes');
$assert($sports[0]['slug'] === 'corrida' && $sports[0]['count'] === 2, 'modalidades são priorizadas por uso real');
$assert(count(sportHubFilterSport($activities, 'corrida')) === 2, 'filtro de corrida mantém somente corrida');
$assert(count(sportHubFilterSport($activities, 'all')) === 4, 'todos os esportes preserva conjunto completo');

$summary = sportHubSummaryMetrics($activities);
$assert($summary['activities'] === 4, 'atividade sem duração continua contando no KPI');
$assert($summary['active_days'] === 3, 'consistência usa dias com presença e não minutos');
$assert(abs($summary['distance_m'] - 33000) < .01, 'distância soma somente valores existentes');
$assert(abs($summary['duration_s'] - 5400) < .01, 'tempo soma apenas duração conhecida');

$start = new DateTimeImmutable('2026-08-03 00:00:00-03');
$end = new DateTimeImmutable('2026-08-24 00:00:00-03');
$series = sportHubWeeklySeries($activities, $start, $end);
$assert(count($series) === 3, 'período de três semanas produz três buckets semanais');
$assert($series[0]['activities'] === 3, 'primeira semana agrega eventos discretos');
$assert($series[0]['distance_known'] === true, 'semana conhece distância se pelo menos uma atividade possui distância');
$assert($series[0]['duration_known'] === true, 'semana conhece duração se pelo menos uma atividade possui duração');
$assert($series[1]['activities'] === 1 && $series[1]['distance_known'] === true, 'segunda semana mantém magnitude de ciclismo');
$assert($series[2]['activities'] === 0 && $series[2]['distance_known'] === false, 'semana realmente vazia fica distinguível de medição presente');

$onlyUnknownMetric = sportHubWeeklySeries([
    ['data_inicio' => '2026-08-03 12:00:00-03', 'modalidade_slug' => 'musculacao', 'duration_s' => 0, 'distancia_metros' => 0, 'ganho_elevacao_m' => 0],
], $start, $start->modify('+7 days'));
$assert(count($onlyUnknownMetric) === 1, 'uma semana usa representação discreta única');
$assert($onlyUnknownMetric[0]['activities'] === 1, 'sessão sem distância/duração continua presente');
$assert($onlyUnknownMetric[0]['distance_known'] === false && $onlyUnknownMetric[0]['duration_known'] === false, 'métrica ausente não é inventada como medição');

$days = sportHubConsistencyDays($activities, $start, $start->modify('+7 days'));
$aug4 = array_values(array_filter($days, static fn(array $day): bool => $day['date']->format('Y-m-d') === '2026-08-04'))[0] ?? null;
$assert(is_array($aug4) && $aug4['count'] === 2, 'duas atividades no mesmo dia ficam visíveis como quantidade');
$assert(isset($aug4['sports']['musculacao'], $aug4['sports']['corrida']), 'consistência não depende apenas de cor e preserva modalidade');

$breakdown = sportHubModalitiesBreakdown($activities);
$assert($breakdown[0]['slug'] === 'corrida' && $breakdown[0]['activities'] === 2, 'lista de modalidades favorece leitura direta em vez de donut');

foreach (['4w','12w','6m','1y'] as $period) {
    $window = sportHubResolvePeriod($period);
    $assert($window['view'] === $period, "período {$period} é aceito");
    $assert($window['current_start'] < $window['current_end'] && $window['previous_end'] == $window['current_start'], "período {$period} possui janela anterior equivalente contígua");
}

echo "✓ progress product data: {$assertions} assertions\n";
