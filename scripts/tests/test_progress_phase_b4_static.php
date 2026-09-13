<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/src/includes/app.php';
require_once $root . '/src/function/benchmarks.php';
require_once $root . '/src/function/benchmark_goals.php';

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Progress Phase B4 failed: {$message}\n");
        exit(1);
    }
};
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$migration = $read('src/database/migrations/20260913_progress_b4.sql');
$athletics = $read('src/function/athletics.php');
$seasons = $read('src/function/seasons.php');
$benchmarks = $read('src/function/benchmarks.php');
$goals = $read('src/function/benchmark_goals.php');
$progress = $read('public/user/progresso.php');
$goalsPage = $read('public/user/metas.php');
$goalsJs = $read('public/assets/js/goals.js');
$account = $read('src/function/account_data.php');
$pt = $read('src/i18n/pt-BR.php');
$en = $read('src/i18n/en.php');

$expected = ['60m','100m','200m','400m','800m','1500m','3000m','5000m','10000m','100m_hurdles','110m_hurdles','400m_hurdles','3000m_steeplechase','long_jump','triple_jump','high_jump','pole_vault','shot_put','discus_throw','javelin_throw','hammer_throw'];
$assert(array_keys(athleticsCatalog()) === $expected, 'Catálogo inicial de Atletismo deve permanecer pequeno e estável.');
$assert(athleticsEventConfig('100m')['direction'] === 'lower', '100 m deve usar lower is better.');
$assert(athleticsEventConfig('long_jump')['direction'] === 'higher', 'Salto em distância deve usar higher is better.');
$assert(athleticsEventConfig('shot_put')['measurement'] === 'distance', 'Arremesso de peso deve ser medido por distância.');
$assert(athleticsEventConfig('unknown_event') === null, 'Evento desconhecido precisa ser rejeitado.');
$assert(athleticsWindLimitMps() === 2.0, 'Limite de vento deve ficar centralizado.');
$assert(athleticsEventConfig('100m')['wind'] === true && athleticsEventConfig('400m')['wind'] === false, 'Vento deve ser aplicado somente às provas configuradas.');
$assert(athleticsFormatTime(11.02) === '11.02', 'Atletismo deve preservar centésimos em provas curtas.');

$eligible = athleticsRecordEligibility(['athletics_event_code'=>'100m','contexto'=>'competicao','effective_officiality'=>'verificado','athletics_environment'=>'outdoor','wind_mps'=>1.1,'timing_method'=>'fat']);
$assert($eligible['status'] === 'eligible', '100 m com contexto oficial, vento legal e FAT deve ser elegível.');
$assert(athleticsRecordEligibility(['athletics_event_code'=>'100m','contexto'=>'competicao','effective_officiality'=>'verificado','athletics_environment'=>'outdoor','wind_mps'=>2.5,'timing_method'=>'fat'])['status'] === 'ineligible', 'Vento acima do limite deve tornar a marca inelegível.');
$windUnknown = athleticsRecordEligibility(['athletics_event_code'=>'100m','contexto'=>'competicao','effective_officiality'=>'verificado','athletics_environment'=>'outdoor','timing_method'=>'fat']);
$assert($windUnknown['status'] === 'unknown' && in_array('wind_unknown', $windUnknown['reasons'], true), 'Vento ausente deve resultar em elegibilidade desconhecida.');
$timingUnknown = athleticsRecordEligibility(['athletics_event_code'=>'100m','contexto'=>'competicao','effective_officiality'=>'verificado','athletics_environment'=>'indoor','timing_method'=>'unknown']);
$assert($timingUnknown['status'] === 'unknown' && in_array('timing_unknown', $timingUnknown['reasons'], true), 'Timing desconhecido deve permanecer desconhecido.');
$assert(athleticsRecordEligibility(['athletics_event_code'=>'100m','contexto'=>'competicao','effective_officiality'=>'verificado','athletics_environment'=>'indoor','timing_method'=>'hand'])['status'] === 'ineligible', 'Cronometragem manual não pode ser convertida silenciosamente em FAT.');
$noWind = athleticsRecordEligibility(['athletics_event_code'=>'shot_put','contexto'=>'competicao','effective_officiality'=>'verificado','athletics_environment'=>'outdoor','timing_method'=>'unknown']);
$assert($noWind['status'] === 'eligible' && !in_array('wind_unknown', $noWind['reasons'], true), 'Evento sem regra de vento não deve exigir vento.');
$assert(athleticsRecordEligibility(['athletics_event_code'=>'long_jump','contexto'=>'treino','effective_officiality'=>'verificado','athletics_environment'=>'outdoor','wind_mps'=>1.0])['status'] === 'ineligible', 'Treino pode ser performance, mas não record-eligible.');

$a100 = ['tipo'=>'athletics','athletics_event_code'=>'100m','athletics_environment'=>'outdoor','valor_canonico'=>11.2,'data_resultado'=>'2026-05-10'];
$a200 = ['tipo'=>'athletics','athletics_event_code'=>'200m','athletics_environment'=>'outdoor','valor_canonico'=>22.0,'data_resultado'=>'2026-05-10'];
$jump = ['tipo'=>'athletics','athletics_event_code'=>'long_jump','athletics_environment'=>'outdoor','valor_canonico'=>6.0,'data_resultado'=>'2026-05-10'];
$assert(benchmarkComparisonKey($a100) !== benchmarkComparisonKey($a200), '100 m não pode ser comparável a 200 m.');
$assert(benchmarkComparisonKey($a100) !== benchmarkComparisonKey($jump), 'Corrida não pode ser comparável a salto.');
$assert(benchmarkComparisonKey($a100) !== benchmarkComparisonKey(array_merge($a100, ['athletics_environment'=>'indoor'])), 'Ambiente conhecido incompatível não pode ser mesclado automaticamente.');
$assert(benchmarkEvidenceBetter(array_merge($a100, ['valor_canonico'=>10.9]), $a100), 'Tempo menor deve ser melhor.');
$assert(benchmarkEvidenceBetter(array_merge($jump, ['valor_canonico'=>6.2]), $jump), 'Salto maior deve ser melhor.');

$rows = [
    array_merge($a100, ['idbenchmark'=>'old','valor_canonico'=>11.40,'data_resultado'=>'2025-06-01','record_eligibility'=>['status'=>'eligible','reasons'=>[]]]),
    array_merge($a100, ['idbenchmark'=>'season-eligible','valor_canonico'=>11.02,'data_resultado'=>'2026-05-10','record_eligibility'=>['status'=>'eligible','reasons'=>[]]]),
    array_merge($a100, ['idbenchmark'=>'season-fast','valor_canonico'=>10.90,'data_resultado'=>'2026-06-01','record_eligibility'=>['status'=>'ineligible','reasons'=>['wind_over_limit']]]),
    array_merge($a100, ['idbenchmark'=>'after-season','valor_canonico'=>10.80,'data_resultado'=>'2026-12-01','record_eligibility'=>['status'=>'unknown','reasons'=>['wind_unknown']]]),
];
$class = benchmarkClassifyComparable($rows, ['data_inicio'=>'2026-01-01','data_fim'=>'2026-10-31']);
$assert($class['best_performance']['idbenchmark'] === 'after-season', 'PB de desempenho deve considerar todo o histórico.');
$assert($class['best_eligible']['idbenchmark'] === 'season-eligible', 'Melhor elegível deve ser independente do melhor desempenho.');
$assert($class['season_best_performance']['idbenchmark'] === 'season-fast', 'SB de desempenho deve ficar dentro da temporada.');
$assert($class['season_best_eligible']['idbenchmark'] === 'season-eligible', 'SB elegível deve ser derivado separadamente.');
$assert(benchmarkClassifyComparable([], null)['best_performance'] === null, 'Ausência de marca deve permanecer nula.');
$tie = benchmarkPickBest([array_merge($a100, ['idbenchmark'=>'first','valor_canonico'=>11.0,'data_resultado'=>'2026-01-01']), array_merge($a100, ['idbenchmark'=>'second','valor_canonico'=>11.0,'data_resultado'=>'2026-02-01'])]);
$assert($tie['idbenchmark'] === 'first', 'Empate deve preservar a evidência mais antiga.');

$assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS temporadas_usuario') && str_contains($migration, 'idusuario VARCHAR(21) NOT NULL REFERENCES usuarios'), 'Temporada deve ser user-owned.');
$assert(str_contains($migration, 'data_fim IS NULL OR data_fim >= data_inicio'), 'Migration deve rejeitar período incoerente.');
$assert(str_contains($migration, 'ux_temporadas_usuario_ativa_modalidade'), 'Só uma temporada ativa por usuário/modalidade deve ser protegida no banco.');
$assert(str_contains($migration, 'daterange') && str_contains($migration, '&& daterange'), 'Sobreposição histórica deve ser impedida para manter resolução por data inequívoca.');
$assert(!str_contains($migration, 'INSERT INTO temporadas_usuario') && !str_contains($migration, 'INSERT INTO benchmarks_usuario'), 'B4 não pode fazer backfill especulativo.');
$assert(str_contains($migration, 'athletics_event_code') && str_contains($migration, 'wind_mps') && str_contains($migration, 'timing_method'), 'Benchmark B1 deve ser estendido, não duplicado.');
$assert(!str_contains($migration, 'is_pb') && !str_contains($migration, 'is_sb'), 'PB/SB não podem ser persistidos como flags permanentes.');
$assert(str_contains($migration, 'benchmark_require_eligible BOOLEAN NOT NULL DEFAULT FALSE'), 'Meta pessoal não deve exigir elegibilidade por padrão.');
$assert(str_contains($migration, 'idregistro VARCHAR(21) REFERENCES registros_atividade') && str_contains($migration, 'idunidade_atividade VARCHAR(21) REFERENCES unidades_atividade'), 'Conclusão de meta deve apontar para evidência derivada sem copiar marca.');
$assert(str_contains($seasons, 'seasonResolveForDate') && !str_contains($migration, 'temporada_id') && !str_contains($migration, 'ADD COLUMN IF NOT EXISTS idtemporada'), 'Temporada deve ser resolvida pela data, sem FK obrigatória nas evidências.');
$assert(str_contains($benchmarks, 'benchmarkComparisonKey') && str_contains($benchmarks, 'benchmarkClassifyComparable'), 'PB/SB deve usar uma engine central derivada.');
$assert(str_contains($benchmarks, 'athleticsActivityEvidence') && str_contains($benchmarks, '$linked'), 'Atividades devem virar evidência sem duplicar benchmark já ligado.');
$assert(str_contains($goals, 'benchmarkGoalAthleticsRows') && str_contains($goals, 'benchmark_require_eligible'), 'Metas B2 devem consumir Atletismo tipado e eligibility opcional.');
$assert(str_contains($progress, 'benchmarkAthleticsClassifications') && str_contains($progress, 'seasonList'), 'Progresso deve consumir classificação central e temporadas.');
$assert(str_contains($goalsPage, 'benchmark_event_code') && str_contains($goalsJs, 'data-goal-athletics-event'), 'UI de Metas deve usar identidade de evento estruturada.');
$assert(str_contains($account, "'temporadas' =>") && str_contains($account, 'temporadas_usuario'), 'Exportação da conta deve incluir temporadas.');

$magicOutside = '';
foreach (['src/function/sport_hub.php','public/user/progresso.php','src/function/benchmarks.php'] as $file) $magicOutside .= $read($file);
$assert(!str_contains($magicOutside, '> 2.0') && !str_contains($magicOutside, '<= 2.0'), 'Limite 2.0 de vento não deve ficar espalhado por UI/domínio.');

foreach (['seasons.empty','seasons.view','athletics.event.long_jump','athletics.event.100m','athletics.eligibility.eligible','athletics.eligibility.unknown','goals.benchmark.choose_athletics_event','goals.benchmark.require_eligible'] as $key) {
    $assert(str_contains($pt, "'{$key}'") && str_contains($en, "'{$key}'"), "Tradução {$key} deve existir em PT-BR e EN.");
}

printf("✓ progress phase B4 static/domain: %d assertions\n", $checks);
