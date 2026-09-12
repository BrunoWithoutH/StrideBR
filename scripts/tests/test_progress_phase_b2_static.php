<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/src/includes/app.php';
require_once $root . '/src/function/benchmark_goals.php';

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Progress Phase B2 failed: {$message}\n");
        exit(1);
    }
};
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$migration = $read('src/database/migrations/20260911_typed_sport_goals.sql');
$goals = $read('src/function/benchmark_goals.php');
$dashboard = $read('src/function/dashboard.php');
$goalsPage = $read('public/user/metas.php');
$goalsJs = $read('public/assets/js/goals.js');
$progress = $read('public/user/progresso.php');
$benchmarks = $read('src/function/benchmarks.php');
$benchmarkApi = $read('public/api/progress-benchmarks.php');
$account = $read('src/function/account_data.php');
$pt = $read('src/i18n/pt-BR.php');
$en = $read('src/i18n/en.php');
$gitignore = $read('.gitignore');

$assert(benchmarkGoalTypes() === ['one_rm', 'ftp', 'css', 'distance_time'], 'B2 deve suportar somente os quatro tipos persistidos da B1.');
$assert(benchmarkGoalTypeConfig('one_rm')['direction'] === 'higher', 'Meta de 1RM deve herdar direção higher do registry B1.');
$assert(benchmarkGoalTypeConfig('ftp')['direction'] === 'higher', 'Meta de FTP deve herdar direção higher do registry B1.');
$assert(benchmarkGoalTypeConfig('css')['direction'] === 'lower', 'Meta de CSS deve herdar direção lower do registry B1.');
$assert(benchmarkGoalTypeConfig('distance_time')['direction'] === 'lower', 'Meta de corrida deve herdar direção lower do registry B1.');
$assert(benchmarkGoalTypeConfig('athletics_time') === null, 'B2 não deve criar metas tipadas de Atletismo.');
$assert(!in_array('e1rm', benchmarkGoalTypes(), true), 'e1RM não pode virar tipo persistido de meta benchmark.');

$assert(abs((float) benchmarkGoalParseTarget('css', '1:40') - 100.0) < 0.001, 'Meta CSS precisa reutilizar parser amigável da B1.');
$assert(abs((float) benchmarkGoalParseTarget('distance_time', '28:00') - 1680.0) < 0.001, 'Meta de corrida precisa persistir tempo canônico em segundos.');
$assert(benchmarkGoalSatisfies('one_rm', 100, 100), 'Higher deve concluir ao igualar alvo.');
$assert(!benchmarkGoalSatisfies('one_rm', 99.9, 100), 'Higher não conclui abaixo do alvo.');
$assert(benchmarkGoalSatisfies('distance_time', 1679, 1680), 'Lower deve concluir abaixo do alvo.');
$assert(!benchmarkGoalSatisfies('distance_time', 1681, 1680), 'Lower não conclui acima do alvo.');

$assert(abs((float) benchmarkGoalProgressPercent('one_rm', 80, 90, 100) - 50.0) < 0.001, 'Higher 80→100 com melhor 90 deve mostrar 50%.');
$assert(abs((float) benchmarkGoalProgressPercent('one_rm', 80, 75, 100) - 0.0) < 0.001, 'Higher pior que baseline deve clamp em 0%.');
$assert(abs((float) benchmarkGoalProgressPercent('one_rm', 80, 100, 100) - 100.0) < 0.001, 'Higher no alvo deve mostrar 100%.');
$assert(abs((float) benchmarkGoalProgressPercent('distance_time', 1800, 1740, 1680) - 50.0) < 0.001, 'Lower 30:00→28:00 com melhor 29:00 deve mostrar 50%.');
$assert(abs((float) benchmarkGoalProgressPercent('distance_time', 1800, 1860, 1680) - 0.0) < 0.001, 'Lower pior que baseline deve clamp em 0%.');
$assert(abs((float) benchmarkGoalProgressPercent('distance_time', 1800, 1679, 1680) - 100.0) < 0.001, 'Lower além do alvo deve clamp em 100%.');
$assert(benchmarkGoalProgressPercent('ftp', null, 240, 250) === null, 'Sem baseline não pode existir percentual fabricado.');
$assert(benchmarkGoalProgressPercent('css', 100, 99, 100) === null, 'Baseline já no alvo não pode fabricar denominador de progresso.');

$assert(str_contains($migration, "ADD COLUMN IF NOT EXISTS tipo_meta VARCHAR(20) NOT NULL DEFAULT 'metrica'"), 'Migration deve preservar metas existentes como tipo metrica.');
$assert(str_contains($migration, 'ALTER COLUMN metrica DROP NOT NULL'), 'Meta benchmark precisa permitir metrica nula.');
foreach (['benchmark_tipo VARCHAR', 'benchmark_distancia_m NUMERIC', 'valor_inicial NUMERIC', 'data_valor_inicial DATE', 'idbenchmark_inicial VARCHAR'] as $fragment) {
    $assert(str_contains($migration, $fragment), "Migration B2 deve estruturar {$fragment}.");
}
$assert(str_contains($migration, "tipo_meta = 'benchmark'") && str_contains($migration, "periodo IN ('continuo', 'personalizado')"), 'Benchmark goal deve rejeitar recorrência semanal/mensal/anual no banco.');
$assert(str_contains($migration, "benchmark_tipo <> 'one_rm' OR idexercicio IS NOT NULL OR benchmark_referencia_nome_snapshot IS NOT NULL"), '1RM goal precisa exigir exercício ou snapshot histórico.');
$assert(str_contains($migration, "benchmark_tipo <> 'distance_time' OR benchmark_distancia_m IS NOT NULL"), 'distance_time goal precisa exigir distância.');
$assert(str_contains($migration, "benchmark_tipo = 'distance_time' OR benchmark_distancia_m IS NULL") && str_contains($migration, "benchmark_tipo = 'one_rm' OR (idexercicio IS NULL AND benchmark_referencia_nome_snapshot IS NULL)"), 'Campos específicos de benchmark devem permanecer nulos fora do tipo aplicável.');
$assert(str_contains($migration, 'valor_inicial IS NULL AND data_valor_inicial IS NULL AND idbenchmark_inicial IS NULL') && str_contains($migration, 'data_valor_inicial <= data_inicio'), 'Snapshot de baseline precisa manter valor/data coerentes e anteriores ao início.');
$assert(str_contains($migration, 'ADD COLUMN IF NOT EXISTS idbenchmark VARCHAR(21) REFERENCES benchmarks_usuario(idbenchmark) ON DELETE SET NULL') && str_contains($migration, 'data_resultado DATE'), 'Conclusão benchmark precisa guardar evidência e data esportiva.');
$assert(!str_contains($migration, 'INSERT INTO metas_usuario') && !str_contains($migration, 'INSERT INTO metas_conclusoes'), 'Migration B2 não pode criar metas ou conclusões por backfill.');

$assert(str_contains($dashboard, "if ((string) (\$meta['tipo_meta'] ?? 'metrica') === 'benchmark') return benchmarkGoalEvaluate"), 'Dashboard deve delegar metas benchmark ao domínio B2.');
$assert(str_contains($dashboard, "if ((string) (\$meta['metrica'] ?? '') === 'carga_maxima')") && str_contains($dashboard, 'dashboardCargaMaximaMeta'), 'carga_maxima legado deve continuar baseado em séries de treino.');
$assert(!str_contains($dashboard, "'carga_maxima' => benchmark"), 'carga_maxima não pode ser silenciosamente convertido em 1RM.');
$assert(str_contains($goals, 'benchmarkGoalReferenceBefore') && str_contains($goals, "\$baseline = \$isEdit ? null : benchmarkGoalReferenceBefore"), 'Criação deve capturar baseline B1 sem recalculá-lo em edição.');
$assert(str_contains($goals, 'benchmarkGoalEligibleRows') && str_contains($goals, "b.data_resultado >= :goal_start") && str_contains($goals, "b.data_resultado <= :goal_end"), 'Conclusão precisa respeitar janela esportiva da meta.');
$assert(str_contains($goals, "b.excluido_progresso = FALSE"), 'Benchmark excluído de Progresso não pode contar para meta.');
$assert(str_contains($goals, "ABS(b.distancia_m - :goal_distance) < 0.001"), 'Meta de corrida deve usar somente a mesma distância.');
$assert(str_contains($goals, "b.idexercicio = :goal_exercise"), 'Meta de 1RM deve usar somente o mesmo exercício.');
$assert(str_contains($goals, 'benchmarkGoalFirstSatisfying') && str_contains($goals, 'benchmarkGoalSyncConclusion'), 'Conclusão precisa ser baseada em evidência elegível e sincronizada.');
$assert(str_contains($goals, 'DELETE FROM metas_conclusoes') && str_contains($goals, 'SET concluida_em=NULL'), 'Meta benchmark deve reabrir se nenhuma evidência válida continuar existindo.');
$assert(str_contains($goals, 'idbenchmark,data_resultado') && str_contains($goals, "':benchmark' => (string) \$evidence['idbenchmark']"), 'Conclusão deve persistir benchmark de evidência e data do resultado.');
$assert(str_contains($benchmarkApi, 'benchmarkGoalSyncForUser($pdo, $idUsuario)'), 'Editar/excluir benchmark B1 deve sincronizar metas B2 imediatamente.');

$assert(str_contains($goalsPage, "stridebr_t('goals.type.practice')") && str_contains($goalsPage, "stridebr_t('goals.type.benchmark')"), 'Criação deve começar por Prática / Marca ou teste.');
$assert(str_contains($goalsPage, 'data-goal-benchmark-type') && str_contains($goalsPage, 'data-goal-distance-field') && str_contains($goalsPage, 'data-goal-exercise-field'), 'Form deve revelar apenas configuração específica do benchmark.');
$assert(str_contains($goalsPage, "stridebr_t('goals.load_help_precise')"), 'Copy de carga máxima precisa explicar que não é 1RM medido.');
$assert(str_contains($goalsPage, "data-goal-load-help<?php echo \$formType === 'metrica' && \$formMetric === 'carga_maxima' ? '' : ' hidden'; ?>") && str_contains($goalsJs, 'if (loadHelp) loadHelp.hidden = !isLoad'), 'Ajuda de carga máxima deve aparecer só na meta de prática, nunca no 1RM medido.');
$assert(str_contains($goalsJs, 'data-practice-period') && str_contains($goalsJs, "type === 'benchmark'"), 'JS deve esconder períodos recorrentes no fluxo Marca ou teste.');
$assert(str_contains($goalsJs, 'data-goal-benchmark-reference') && str_contains($goalsJs, 'benchmarkData'), 'UX deve mostrar referência B1 quando houver.');
$assert(str_contains($goalsPage, 'goal-card-benchmark') && str_contains($goalsPage, "goals.benchmark.best_since_start") && str_contains($goalsPage, "goals.benchmark.at_creation"), 'Card benchmark deve priorizar valores esportivos e baseline.');
$assert(str_contains($read('public/assets/css/dashboard.css'), '.goals-page [hidden]') && str_contains($read('public/assets/css/dashboard.css'), 'display: none !important'), 'Cascade de Goals deve respeitar progressive disclosure por hidden.');
$assert(str_contains($goalsPage, "benchmarkGoalResultHref(\$meta)"), 'Estado sem resultado deve integrar CTA com B1.');
$assert(str_contains($goalsPage, "goals.benchmark.completed_on") && str_contains($goalsPage, 'idbenchmark'), 'Histórico concluído deve usar data esportiva e evidência.');
$assert(str_contains($dashboard, "benchmark_evidence']['data_resultado']") && str_contains($dashboard, "goals.benchmark.completed_on"), 'Card/meta compacta concluída deve preferir data esportiva da evidência.');

$assert(str_contains($progress, 'dashboardMetaCompactValue($goal)') && str_contains($dashboard, 'function dashboardMetaCompactValue'), 'Progresso deve formatar metas B2 semanticamente, sem valores canônicos crus.');
$assert(str_contains($dashboard, "benchmarkFormatValue(\$type") && str_contains($dashboard, "goals.benchmark.compact_best_target"), 'Compacto de Progresso deve reutilizar formatters B1.');
$assert(str_contains($progress, "\$_GET['exercise']") && str_contains($progress, "\$_GET['distance_m']"), 'CTA de meta deve permitir prefill pequeno e validado do formulário B1.');
$assert(str_contains($account, "'metas' => ['SELECT * FROM metas_usuario") && str_contains($account, "'metas_conclusoes'"), 'Exportação existente deve incluir automaticamente novos campos de metas/conclusões.');

$assert(str_contains($benchmarks, "benchmarkValidateOfficialContext(\$officiality, \$context)") && str_contains($benchmarkApi, 'benchmarkApplyReportedOfficialInput'), 'B2 não pode regredir hotfix oficial=Competição da B1.');
$assert(str_contains($gitignore, '__pycache__/') && str_contains($gitignore, '*.py[cod]'), 'B2 não pode reintroduzir cache Python no Git.');

foreach (['goals.type.practice','goals.type.benchmark','goals.benchmark.best_since_start','goals.benchmark.at_creation','goals.benchmark.remaining','goals.benchmark.no_result','goals.error.benchmark_already_achieved','goals.error.benchmark_identity_locked','goals.load_help_precise','goals.benchmark.completed_on','goals.benchmark.view_result'] as $key) {
    $assert(str_contains($pt, "'{$key}'") && str_contains($en, "'{$key}'"), "Tradução {$key} deve existir em PT-BR e EN.");
}

printf("✓ progress phase B2 static/domain: %d assertions\n", $checks);
