<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/src/includes/app.php';
require_once $root . '/src/function/dashboard.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$today = new DateTimeImmutable('2026-09-07 12:00:00', new DateTimeZone('America/Sao_Paulo'));

$empty = dashboardMontarVisaoAtividades([], $today);
$assert(count($empty['dias']) === 7, 'sem atividades ainda produz os sete dias da semana');
$assert($empty['resumo']['atividades'] === 0, 'sem atividades resume zero presença');
$assert($empty['dias'][0]['hoje'] === true, 'dia atual é identificado');

$strengthNoDuration = dashboardMontarVisaoAtividades([
    [
        'dia' => '2026-09-07',
        'atividades' => 1,
        'atividades_itens' => [[
            'idregistro' => 'strength-1',
            'titulo' => 'Musculação',
            'data_inicio' => '2026-09-07T14:03:00-03:00',
            'modalidade_nome' => 'Musculação',
            'modalidade_slug' => 'musculacao',
            'modalidade_familia_hub' => 'strength',
            'distancia_m' => 0,
            'duracao_s' => 0,
        ]],
        'duracao_s' => 0,
        'distancia_m' => 0,
        'elevacao_m' => 0,
    ],
], $today);
$assert($strengthNoDuration['resumo']['atividades'] === 1, 'musculação sem duração conta como atividade');
$assert($strengthNoDuration['resumo']['dias_ativos'] === 1, 'musculação sem duração conta como dia ativo');
$assert($strengthNoDuration['dias'][0]['atividades'] === 1, 'presença semanal não depende de duração');
$assert(count($strengthNoDuration['dias'][0]['atividades_itens']) === 1, 'atividade real é preservada no dia');
$assert($strengthNoDuration['dias'][0]['atividades_itens'][0]['metadata'] === '14:03', 'tooltip omite distância e duração ausentes');

$strengthTimed = dashboardAtividadeSemanaApresentar([
    'idregistro' => 'strength-52', 'titulo' => 'Musculação', 'data_inicio' => '2026-09-07T14:03:00-03:00',
    'modalidade_nome' => 'Musculação', 'modalidade_slug' => 'musculacao', 'modalidade_familia_hub' => 'strength', 'distancia_m' => 0, 'duracao_s' => 3120,
], 'pt-BR');
$assert($strengthTimed['metadata'] === '14:03 · 52 min', 'duração inteira sem distância usa apresentação compacta já existente');


$multipleSameSport = dashboardMontarVisaoAtividades([
    [
        'dia' => '2026-09-07',
        'atividades' => 3,
        'atividades_itens' => [
            ['idregistro' => 'run-1', 'titulo' => 'Corrida cedo', 'data_inicio' => '2026-09-07T07:00:00-03:00', 'modalidade_nome' => 'Corrida', 'modalidade_slug' => 'corrida', 'modalidade_familia_hub' => 'cardio', 'distancia_m' => 5000, 'duracao_s' => 1902],
            ['idregistro' => 'run-2', 'titulo' => 'Corrida à noite', 'data_inicio' => '2026-09-07T19:05:00-03:00', 'modalidade_nome' => 'Corrida', 'modalidade_slug' => 'corrida', 'modalidade_familia_hub' => 'cardio', 'distancia_m' => 5000, 'duracao_s' => 1902],
            ['idregistro' => 'run-3', 'titulo' => 'Soltura', 'data_inicio' => '2026-09-07T21:10:00-03:00', 'modalidade_nome' => 'Corrida', 'modalidade_slug' => 'corrida', 'modalidade_familia_hub' => 'cardio', 'distancia_m' => 823, 'duracao_s' => 320],
        ],
        'duracao_s' => 4124,
        'distancia_m' => 10823,
        'elevacao_m' => 80,
    ],
], $today);
$assert($multipleSameSport['dias'][0]['atividades'] === 3, 'três atividades no mesmo dia permanecem três atividades');
$assert(count($multipleSameSport['dias'][0]['atividades_itens']) === 3, 'mesma modalidade não é deduplicada');
$assert($multipleSameSport['dias'][0]['atividades_itens'][0]['idregistro'] === 'run-1', 'ordem das atividades reais é preservada');
$assert($multipleSameSport['dias'][0]['atividades_itens'][1]['idregistro'] === 'run-2', 'segunda corrida permanece marcador distinto');
$assert($multipleSameSport['resumo']['dias_ativos'] === 1, 'várias atividades no mesmo dia contam como um dia ativo');
$assert(abs($multipleSameSport['resumo']['distancia_km'] - 10.823) < 0.001, 'resumo semanal agrega distância existente');

$roadPt = dashboardAtividadeSemanaApresentar([
    'idregistro' => 'road-1500', 'titulo' => 'Corrida curta', 'data_inicio' => '2026-09-07T18:00:00-03:00',
    'modalidade_nome' => 'Corrida', 'modalidade_slug' => 'corrida', 'modalidade_familia_hub' => 'cardio', 'distancia_m' => 1500, 'duracao_s' => 420,
], 'pt-BR');
$assert($roadPt['distancia'] === '1,5 km', 'corrida comum usa política central de distância em pt-BR');
$assert($roadPt['metadata'] === '18:00 · 1,5 km · 7:00', 'tooltip reúne hora, distância e duração sem formatter paralelo');
$assert($roadPt['href'] === '/user/atividades.php#atividade-road-1500', 'marcador usa o contrato atual do histórico');

$trackPt = dashboardAtividadeSemanaApresentar([
    'idregistro' => 'track-400', 'titulo' => '400 m', 'data_inicio' => '2026-09-07T18:27:00-03:00',
    'modalidade_nome' => '400 m', 'modalidade_slug' => 'atletismo-400m', 'modalidade_familia_hub' => 'athletics', 'distancia_m' => 400, 'duracao_s' => 77.921,
], 'pt-BR');
$assert($trackPt['distancia'] === '400 m', '400 m de pista reutiliza ActivitySportContext');
$assert($trackPt['duracao'] === '1:17,921', 'tempo preciso usa formatter central em pt-BR');
$assert(str_contains($trackPt['aria_label'], '400 m') && str_contains($trackPt['aria_label'], '1:17,921'), 'aria label contém contexto esportivo útil');

$trackEn = dashboardAtividadeSemanaApresentar([
    'idregistro' => 'track-400-en', 'titulo' => '400 m', 'data_inicio' => '2026-09-07T18:27:00-03:00',
    'modalidade_nome' => '400 m', 'modalidade_slug' => 'atletismo-400m', 'modalidade_familia_hub' => 'athletics', 'distancia_m' => 400, 'duracao_s' => 77.921,
], 'en');
$assert($trackEn['distancia'] === '400 m', 'distância de pista permanece em metros em inglês');
$assert($trackEn['duracao'] === '1:17.921', 'tempo preciso usa ponto em inglês');

$shortRoad = dashboardAtividadeSemanaApresentar([
    'idregistro' => 'road-823', 'titulo' => 'Corrida curta', 'data_inicio' => '2026-09-07T08:00:00-03:00',
    'modalidade_nome' => 'Corrida', 'modalidade_slug' => 'corrida', 'modalidade_familia_hub' => 'cardio', 'distancia_m' => 823, 'duracao_s' => 0,
], 'pt-BR');
$assert($shortRoad['metadata'] === '08:00 · 823 m', 'tooltip omite duração ausente e preserva distância curta em metros');

$futureIgnored = dashboardMontarVisaoAtividades([
    ['dia' => '2026-09-08', 'atividades' => 1, 'modalidades' => ['ciclismo'], 'duracao_s' => 3600, 'distancia_m' => 25000, 'elevacao_m' => 200],
], $today);
$assert($futureIgnored['dias'][1]['futuro'] === true, 'dias futuros são marcados');
$assert($futureIgnored['resumo']['atividades'] === 0, 'resumo da semana até agora não contabiliza futuro');

$previous = dashboardMontarVisaoAtividades([['dia' => '2026-09-06', 'atividades' => 3]], $today, -1);
$assert($previous['inicio']->format('Y-m-d') === '2026-08-31', 'navegação usa início da semana anterior');
$assert($previous['resumo']['atividades'] === 3, 'semana anterior soma até domingo');
$assert(!$previous['dias'][6]['hoje'] && !$previous['dias'][6]['futuro'], 'navegar não altera a data real de hoje');

echo "✓ dashboard week consistency: {$assertions} assertions\n";
