<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/function/benchmarks.php';
require_once dirname(__DIR__, 2) . '/src/function/benchmark_goals.php';
require_once dirname(__DIR__, 2) . '/src/function/seasons.php';
require_once dirname(__DIR__, 2) . '/src/function/competitions.php';

return function (PDO $pdo): void {
    $sport = static function (PDO $pdo, string $slug): array {
        $stmt = $pdo->prepare('SELECT idmodalidade,slug,familia_hub FROM modalidades WHERE slug=:slug AND ativo=TRUE ORDER BY idusuario NULLS FIRST LIMIT 1');
        $stmt->execute([':slug'=>$slug]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw new RuntimeException("Modalidade seed ausente: {$slug}");
        return $row;
    };
    $goalByName = static function (PDO $pdo, string $userId, string $name): array {
        $stmt = $pdo->prepare('SELECT * FROM metas_usuario WHERE idusuario=:usuario AND nome=:nome ORDER BY data_criacao DESC LIMIT 1');
        $stmt->execute([':usuario'=>$userId, ':nome'=>$name]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw new RuntimeException("Meta não encontrada: {$name}");
        return $row;
    };
    $conclusion = static function (PDO $pdo, string $goalId): ?array {
        $stmt = $pdo->prepare('SELECT * FROM metas_conclusoes WHERE idmeta=:meta ORDER BY atingida_em DESC LIMIT 1');
        $stmt->execute([':meta'=>$goalId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    };

    AlphaTest::assert((bool)$pdo->query("SELECT to_regclass('stridebr.temporadas_usuario')")->fetchColumn(), 'Tabela de temporadas B4 ausente');
    $athleticsBase = $sport($pdo, 'atletismo');
    $sprint100 = $sport($pdo, 'atletismo-100m');
    $sprint200 = $sport($pdo, 'atletismo-200m');
    $longJump = $sport($pdo, 'salto-em-distancia');
    $running = $sport($pdo, 'corrida');

    $user = alphaTestUser($pdo, 'progress_b4');
    $other = alphaTestUser($pdo, 'progress_b4_other');

    $season = seasonCreate($pdo, $user, [
        'idmodalidade'=>$sprint100['idmodalidade'],
        'nome'=>'Atletismo 2025 B4',
        'data_inicio'=>'2025-01-01',
        'data_fim'=>'2025-10-31',
        'status'=>'encerrada',
    ]);
    AlphaTest::same((string)$athleticsBase['idmodalidade'], (string)$season['idmodalidade'], 'Temporada de prova de Atletismo deve canonicalizar para Atletismo');
    AlphaTest::same((string)$season['idtemporada'], (string)(seasonResolveForDate($pdo, $user, (string)$sprint200['idmodalidade'], '2025-06-01')['idtemporada'] ?? ''), 'Temporada deve resolver por data para qualquer prova do Atletismo');
    AlphaTest::same(null, seasonResolveForDate($pdo, $user, (string)$sprint100['idmodalidade'], '2024-12-31'), 'Data fora da temporada não pode resolver temporada');
    AlphaTest::throws(fn()=>seasonCreate($pdo, $user, [
        'idmodalidade'=>$longJump['idmodalidade'], 'nome'=>'Sobreposta B4', 'data_inicio'=>'2025-06-01', 'data_fim'=>'2025-12-01', 'status'=>'encerrada',
    ]), 'Temporadas da mesma modalidade canônica não podem se sobrepor');
    AlphaTest::throws(fn()=>seasonCreate($pdo, $user, [
        'idmodalidade'=>$running['idmodalidade'], 'nome'=>'Inválida B4', 'data_inicio'=>'2026-09-10', 'data_fim'=>'2026-09-01', 'status'=>'encerrada',
    ]), 'Temporada deve rejeitar data final anterior à inicial');
    AlphaTest::throws(fn()=>seasonUpdate($pdo, $other, (string)$season['idtemporada'], ['nome'=>'Roubo B4']), 'Outro usuário não pode editar temporada alheia');

    $currentRun = seasonCreate($pdo, $user, [
        'idmodalidade'=>$running['idmodalidade'], 'nome'=>'Corrida atual B4', 'data_inicio'=>'2026-01-01', 'status'=>'ativa',
    ]);
    AlphaTest::same((string)$currentRun['idtemporada'], (string)(seasonCurrent($pdo, $user, (string)$running['idmodalidade'], '2026-09-13')['idtemporada'] ?? ''), 'Temporada ativa deve ser identificável como atual');
    $closedRun = seasonClose($pdo, $user, (string)$currentRun['idtemporada'], '2026-09-30');
    AlphaTest::same('encerrada', (string)$closedRun['status'], 'Temporada precisa poder ser encerrada');
    $reopenedRun = seasonReopen($pdo, $user, (string)$closedRun['idtemporada']);
    AlphaTest::same('ativa', (string)$reopenedRun['status'], 'Temporada pode ser reaberta quando não cria sobreposição');
    seasonClose($pdo, $user, (string)$reopenedRun['idtemporada'], '2026-09-30');
    seasonCreate($pdo, $user, ['idmodalidade'=>$running['idmodalidade'], 'nome'=>'Corrida futura B4', 'data_inicio'=>'2026-10-01', 'status'=>'ativa']);
    AlphaTest::throws(fn()=>seasonReopen($pdo, $user, (string)$closedRun['idtemporada']), 'Reabertura deve ser recusada quando passaria a sobrepor outra temporada');
    AlphaTest::assert(count(seasonList($pdo, $user, (string)$running['idmodalidade'])) === 2, 'Histórico de temporadas precisa manter atual e encerradas');

    $baseInput = [
        'tipo'=>'athletics',
        'idmodalidade'=>$sprint100['idmodalidade'],
        'origem'=>'manual',
        'metodo'=>'medido',
        'contexto'=>'competicao',
        'oficialidade'=>'informado_oficial',
        'athletics_environment'=>'outdoor',
        'timing_method'=>'fat',
    ];
    benchmarkCreate($pdo, $user, $baseInput + ['valor_canonico'=>11.40, 'data_resultado'=>'2024-06-01', 'wind_mps'=>1.0]);
    $eligiblePb = benchmarkCreate($pdo, $user, array_merge($baseInput, ['valor_canonico'=>11.02, 'data_resultado'=>'2025-05-10', 'wind_mps'=>1.1]));
    $windAided = benchmarkCreate($pdo, $user, array_merge($baseInput, ['valor_canonico'=>10.90, 'data_resultado'=>'2025-06-01', 'wind_mps'=>2.5]));
    $unknown = benchmarkCreate($pdo, $user, array_merge($baseInput, ['valor_canonico'=>10.80, 'data_resultado'=>'2025-12-01', 'wind_mps'=>null]));
    benchmarkCreate($pdo, $user, [
        'tipo'=>'athletics','idmodalidade'=>$sprint200['idmodalidade'],'valor_canonico'=>21.8,'data_resultado'=>'2025-06-01','origem'=>'manual','metodo'=>'medido','contexto'=>'competicao','oficialidade'=>'informado_oficial','athletics_environment'=>'outdoor','wind_mps'=>1.0,'timing_method'=>'fat',
    ]);
    benchmarkCreate($pdo, $user, [
        'tipo'=>'athletics','idmodalidade'=>$longJump['idmodalidade'],'valor_canonico'=>6.35,'data_resultado'=>'2025-06-01','origem'=>'manual','metodo'=>'medido','contexto'=>'competicao','oficialidade'=>'informado_oficial','athletics_environment'=>'outdoor','wind_mps'=>1.0,
    ]);

    $classifications = benchmarkAthleticsClassifications($pdo, $user, $season, '100m');
    $key = 'athletics:100m:outdoor';
    AlphaTest::assert(isset($classifications[$key]), 'Classificação integrada de 100 m deve existir');
    $class = $classifications[$key];
    AlphaTest::same((string)$unknown['idbenchmark'], (string)$class['best_performance']['idbenchmark'], 'PB de desempenho deve considerar melhor histórico independentemente de eligibility');
    AlphaTest::same((string)$eligiblePb['idbenchmark'], (string)$class['best_eligible']['idbenchmark'], 'Melhor marca elegível deve ficar separada do melhor desempenho');
    AlphaTest::same((string)$windAided['idbenchmark'], (string)$class['season_best_performance']['idbenchmark'], 'SB de desempenho deve respeitar limites da temporada');
    AlphaTest::same((string)$eligiblePb['idbenchmark'], (string)$class['season_best_eligible']['idbenchmark'], 'SB elegível deve ser derivado separadamente');
    AlphaTest::same('manual', (string)$class['best_eligible']['origem'], 'PB/SB não pode apagar origem B1');
    AlphaTest::same('medido', (string)$class['best_eligible']['metodo'], 'PB/SB não pode apagar método B1');
    AlphaTest::same('competicao', (string)$class['best_eligible']['contexto'], 'PB/SB não pode apagar contexto B1');
    AlphaTest::same('ineligible', (string)($class['season_best_performance']['record_eligibility']['status'] ?? ''), 'Melhor desempenho com vento acima do limite deve continuar performance, mas inelegível');
    AlphaTest::same('unknown', (string)(athleticsRecordEligibility($unknown)['status'] ?? ''), 'Ausência de vento em prova sensível deve permanecer unknown');

    $competition = competitionCreate($pdo, $user, [
        'nome'=>'Competição B4','data_inicio'=>'2026-07-01','status'=>'realizada','oficialidade'=>'informado_oficial','origem'=>'manual',
    ]);
    $competitionWind = benchmarkCreate($pdo, $user, array_merge($baseInput, [
        'valor_canonico'=>10.95,'data_resultado'=>'2026-07-01','wind_mps'=>2.4,'idcompeticao'=>$competition['idcompeticao'],
    ]));
    AlphaTest::same('ineligible', athleticsRecordEligibility($competitionWind)['status'], 'Competition/oficialidade não podem transformar vento ilegal em marca elegível');

    $legacy = benchmarkCreate($pdo, $user, [
        'tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'valor_canonico'=>1800,'distancia_m'=>5000,'data_resultado'=>'2026-08-01','origem'=>'manual','metodo'=>'medido',
    ]);
    AlphaTest::same('distance_time', (string)$legacy['tipo'], 'Benchmark B1 legado deve continuar funcionando após B4');
    AlphaTest::same(null, $legacy['athletics_event_code'], 'Benchmark legado não deve receber evento de Atletismo por backfill');

    $goalUser = alphaTestUser($pdo, 'progress_b4_goal_personal');
    benchmarkCreate($pdo, $goalUser, array_merge($baseInput, ['valor_canonico'=>11.30,'data_resultado'=>'2026-09-12','wind_mps'=>1.0]));
    dashboardCriarMeta($pdo, $goalUser, [
        'tipo_meta'=>'benchmark','benchmark_tipo'=>'athletics','benchmark_event_code'=>'100m','benchmark_environment'=>'outdoor','benchmark_require_eligible'=>false,'valor_alvo'=>'11.00','periodo'=>'continuo','nome'=>'100 m pessoal B4',
    ]);
    $personalGoal = $goalByName($pdo, $goalUser, '100 m pessoal B4');
    AlphaTest::assert(!stridebr_db_bool($personalGoal['benchmark_require_eligible'] ?? true), 'Meta de Atletismo pessoal deve persistir benchmark_require_eligible=false');
    dashboardEditarMeta($pdo, $goalUser, (string)$personalGoal['idmeta'], [
        'tipo_meta'=>'benchmark','benchmark_tipo'=>'athletics','benchmark_event_code'=>'100m','benchmark_environment'=>'outdoor','benchmark_require_eligible'=>false,'valor_alvo'=>'11.00','periodo'=>'continuo','nome'=>'100 m pessoal B4',
    ]);
    $personalGoal = $goalByName($pdo, $goalUser, '100 m pessoal B4');
    AlphaTest::assert(!stridebr_db_bool($personalGoal['benchmark_require_eligible'] ?? true), 'Edição deve preservar benchmark_require_eligible=false em Atletismo');
    benchmarkCreate($pdo, $goalUser, array_merge($baseInput, ['valor_canonico'=>10.98,'data_resultado'=>(string)$personalGoal['data_inicio'],'wind_mps'=>2.6]));
    $personalEval = benchmarkGoalEvaluate($pdo, $goalUser, $personalGoal, true);
    AlphaTest::assert($personalEval['atingida'], 'Meta pessoal de Atletismo não deve exigir eligibility por padrão');
    AlphaTest::assert(is_array($conclusion($pdo, (string)$personalGoal['idmeta'])), 'Conclusão de meta pessoal deve persistir evidência');

    $eligibleGoalUser = alphaTestUser($pdo, 'progress_b4_goal_eligible');
    benchmarkCreate($pdo, $eligibleGoalUser, array_merge($baseInput, ['valor_canonico'=>11.30,'data_resultado'=>'2026-09-12','wind_mps'=>1.0]));
    dashboardCriarMeta($pdo, $eligibleGoalUser, [
        'tipo_meta'=>'benchmark','benchmark_tipo'=>'athletics','benchmark_event_code'=>'100m','benchmark_environment'=>'outdoor','benchmark_require_eligible'=>true,'valor_alvo'=>'11.00','periodo'=>'continuo','nome'=>'100 m elegível B4',
    ]);
    $eligibleGoal = $goalByName($pdo, $eligibleGoalUser, '100 m elegível B4');
    AlphaTest::assert(stridebr_db_bool($eligibleGoal['benchmark_require_eligible'] ?? false), 'Meta de Atletismo elegível deve persistir benchmark_require_eligible=true');
    dashboardEditarMeta($pdo, $eligibleGoalUser, (string)$eligibleGoal['idmeta'], [
        'tipo_meta'=>'benchmark','benchmark_tipo'=>'athletics','benchmark_event_code'=>'100m','benchmark_environment'=>'outdoor','benchmark_require_eligible'=>true,'valor_alvo'=>'11.00','periodo'=>'continuo','nome'=>'100 m elegível B4',
    ]);
    $eligibleGoal = $goalByName($pdo, $eligibleGoalUser, '100 m elegível B4');
    AlphaTest::assert(stridebr_db_bool($eligibleGoal['benchmark_require_eligible'] ?? false), 'Edição deve preservar benchmark_require_eligible=true em Atletismo');
    benchmarkCreate($pdo, $eligibleGoalUser, array_merge($baseInput, ['valor_canonico'=>10.97,'data_resultado'=>(string)$eligibleGoal['data_inicio'],'wind_mps'=>2.7]));
    $beforeEligible = benchmarkGoalEvaluate($pdo, $eligibleGoalUser, $eligibleGoal, true);
    AlphaTest::assert(!$beforeEligible['atingida'], 'Meta que exige eligibility não pode ser concluída por marca inelegível');
    $eligibleHit = benchmarkCreate($pdo, $eligibleGoalUser, array_merge($baseInput, ['valor_canonico'=>10.99,'data_resultado'=>(string)$eligibleGoal['data_inicio'],'wind_mps'=>1.2]));
    $afterEligible = benchmarkGoalEvaluate($pdo, $eligibleGoalUser, $eligibleGoal, true);
    AlphaTest::assert($afterEligible['atingida'], 'Meta que exige eligibility deve concluir com evidência comparável elegível');
    AlphaTest::same((string)$eligibleHit['idbenchmark'], (string)($afterEligible['benchmark_evidence']['idbenchmark'] ?? ''), 'Meta elegível deve apontar para a evidência correta');

    $export = accountExportData($pdo, $user);
    AlphaTest::assert(isset($export['temporadas']) && count($export['temporadas']) >= 3, 'Exportação da conta deve incluir temporadas B4');
    AlphaTest::assert(array_key_exists('athletics_event_code', $export['benchmarks'][0] ?? []), 'Exportação de benchmarks deve carregar extensão tipada de Atletismo');
};
