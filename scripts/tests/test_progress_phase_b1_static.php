<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/src/includes/app.php';
require_once $root . '/src/function/benchmarks.php';

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Progress Phase B1 failed: {$message}\n");
        exit(1);
    }
};
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$migration = $read('src/database/migrations/20260911_progress_benchmarks.sql');
$benchmarks = $read('src/function/benchmarks.php');
$api = $read('public/api/progress-benchmarks.php');
$progress = $read('public/user/progresso.php');
$hub = $read('src/function/sport_hub.php');
$account = $read('src/function/account_data.php');
$js = $read('public/assets/js/progresso.js');
$css = $read('public/assets/css/sport-hub.css');
$pt = $read('src/i18n/pt-BR.php');
$en = $read('src/i18n/en.php');
$gitignore = $read('.gitignore');

$registry = benchmarkRegistry();
$assert(array_keys($registry) === ['one_rm', 'ftp', 'css', 'distance_time'], 'B1 precisa registrar somente os quatro tipos iniciais persistidos.');
$assert($registry['one_rm']['direction'] === 'higher' && $registry['one_rm']['primary'] === 'best' && $registry['one_rm']['requires_exercise'] === true, '1RM medido precisa ser maior-é-melhor, melhor histórico e exigir exercício.');
$assert($registry['ftp']['direction'] === 'higher' && $registry['ftp']['primary'] === 'latest', 'FTP precisa usar valor atual mais recente sem perder melhor histórico.');
$assert($registry['css']['direction'] === 'lower' && $registry['css']['primary'] === 'latest', 'CSS precisa ser menor-é-melhor e atual pelo registro mais recente.');
$assert($registry['distance_time']['direction'] === 'lower' && $registry['distance_time']['primary'] === 'best' && $registry['distance_time']['requires_distance'] === true, 'Teste por distância precisa ser menor-é-melhor e comparar protocolo compatível.');

$assert(abs((float) benchmarkParseClockSeconds('1:46') - 106.0) < .001, 'CSS amigável min:s precisa virar segundos canônicos.');
$assert(abs((float) benchmarkParseClockSeconds('29:58') - 1798.0) < .001, 'Tempo de corrida precisa virar segundos canônicos.');
$assert(benchmarkParseClockSeconds('1:75') === null, 'Parser de tempo precisa rejeitar segundos inválidos.');
$assert(benchmarkFormatValue('css', 106.0) === '1:46/100 m', 'CSS precisa formatar segundos por 100 m.');
$assert(benchmarkFormatValue('distance_time', 1798.0) === '29:58', 'Teste de corrida precisa formatar tempo canônico.');
$assert(str_contains(benchmarkFormatDistance(42195.0), '42') && !str_contains(file_get_contents(dirname(__DIR__, 2) . '/src/function/benchmarks.php'), "return '42,195 km'"), 'Distâncias conhecidas precisam usar formatter de locale, não separador hardcoded.');
$assert(benchmarkFormatDifference('distance_time', -104.0) === '−1:44', 'Diferença de corrida deve priorizar diferença absoluta.');

$assert(benchmarkNormalizeMetadata(null) === [], 'Metadata ausente deve normalizar para objeto vazio canônico.');
$assert(benchmarkNormalizeMetadata([]) === [], 'Array PHP vazio usado como metadata deve representar objeto vazio.');
$metadataObject = benchmarkNormalizeMetadata(['protocol' => ['name' => 'ramp'], 'quality' => 'synthetic']);
$assert(($metadataObject['quality'] ?? null) === 'synthetic', 'Metadata estruturada deve preservar objeto preenchido.');
$assert(benchmarkMetadataJson([]) === '{}', 'Metadata vazia precisa persistir como JSON object, nunca array.');
$assert(str_starts_with(benchmarkMetadataJson($metadataObject), '{'), 'Metadata preenchida precisa persistir com raiz JSON object.');
foreach ([['invalid-list'], '["invalid-list"]'] as $invalidMetadata) {
    $rejected = false;
    try { benchmarkNormalizeMetadata($invalidMetadata); } catch (InvalidArgumentException) { $rejected = true; }
    $assert($rejected, 'Metadata com raiz list/JSON array precisa ser rejeitada.');
}

$officialCompetitionAccepted = true;
try { benchmarkValidateOfficialContext('informado_oficial', 'competicao'); } catch (Throwable) { $officialCompetitionAccepted = false; }
$assert($officialCompetitionAccepted, 'Resultado informado como oficial deve aceitar somente contexto Competição.');
foreach (['treino', 'teste', null] as $invalidOfficialContext) {
    $rejected = false;
    try { benchmarkValidateOfficialContext('informado_oficial', $invalidOfficialContext); } catch (InvalidArgumentException) { $rejected = true; }
    $assert($rejected, 'Resultado informado como oficial precisa rejeitar contexto incompatível ou vazio.');
}
foreach (['treino', 'teste', null] as $regularContext) {
    $accepted = true;
    try { benchmarkValidateOfficialContext('nao_aplicavel', $regularContext); } catch (Throwable) { $accepted = false; }
    $assert($accepted, 'Resultado não oficial deve continuar aceitando treino, teste ou contexto vazio.');
}
$normalizedOfficial = benchmarkApplyReportedOfficialInput(['contexto' => 'treino'], true);
$assert($normalizedOfficial['contexto'] === 'competicao' && $normalizedOfficial['oficialidade'] === 'informado_oficial', 'Normalização do formulário oficial deve forçar Competição antes do domínio.');
$normalizedRegular = benchmarkApplyReportedOfficialInput(['contexto' => 'teste'], false);
$assert($normalizedRegular['contexto'] === 'teste' && $normalizedRegular['oficialidade'] === 'nao_aplicavel', 'Resultado não oficial deve preservar contexto escolhido.');

$ftpRows = [
    ['tipo'=>'ftp','valor_canonico'=>250,'data_resultado'=>'2026-08-01','data_criacao'=>'2026-08-01 10:00:00','excluido_progresso'=>false],
    ['tipo'=>'ftp','valor_canonico'=>238,'data_resultado'=>'2026-09-02','data_criacao'=>'2026-09-02 10:00:00','excluido_progresso'=>false],
];
$ftp = benchmarkSummarize($ftpRows, 'ftp');
$assert((float) $ftp['latest']['valor_canonico'] === 238.0 && (float) $ftp['primary']['valor_canonico'] === 238.0, 'FTP atual precisa ser o registro mais recente, mesmo quando não é o maior histórico.');
$assert((float) $ftp['best']['valor_canonico'] === 250.0, 'FTP deve preservar melhor histórico separadamente.');

$cssRows = [
    ['tipo'=>'css','valor_canonico'=>106,'data_resultado'=>'2026-08-01','data_criacao'=>'2026-08-01 10:00:00','excluido_progresso'=>false],
    ['tipo'=>'css','valor_canonico'=>110,'data_resultado'=>'2026-09-02','data_criacao'=>'2026-09-02 10:00:00','excluido_progresso'=>false],
];
$cssSummary = benchmarkSummarize($cssRows, 'css');
$assert((float) $cssSummary['latest']['valor_canonico'] === 110.0 && (float) $cssSummary['best']['valor_canonico'] === 106.0, 'CSS atual e melhor histórico precisam ter semânticas independentes.');

$distanceRows = [
    ['tipo'=>'distance_time','valor_canonico'=>1798,'distancia_m'=>5000,'data_resultado'=>'2026-09-01','data_criacao'=>'2026-09-01','excluido_progresso'=>false],
    ['tipo'=>'distance_time','valor_canonico'=>1900,'distancia_m'=>5000,'data_resultado'=>'2026-08-01','data_criacao'=>'2026-08-01','excluido_progresso'=>false],
    ['tipo'=>'distance_time','valor_canonico'=>3734,'distancia_m'=>10000,'data_resultado'=>'2026-09-03','data_criacao'=>'2026-09-03','excluido_progresso'=>false],
];
$distanceGroups = benchmarkGroupDistanceTests($distanceRows);
$assert(count($distanceGroups) === 2, '5 km e 10 km precisam ficar em históricos separados.');
$assert((float) $distanceGroups['5000.000']['summary']['best']['valor_canonico'] === 1798.0, 'Melhor teste de 5 km precisa usar menor tempo apenas dentro da mesma distância.');
$samePeriodFacts = benchmarkHighlightFacts([
    ['tipo'=>'one_rm','idexercicio'=>'e1','valor_canonico'=>80,'data_resultado'=>'2026-09-02','data_criacao'=>'2026-09-02 08:00:00','excluido_progresso'=>false],
    ['tipo'=>'one_rm','idexercicio'=>'e1','valor_canonico'=>85,'data_resultado'=>'2026-09-08','data_criacao'=>'2026-09-08 08:00:00','excluido_progresso'=>false],
], new DateTimeImmutable('2026-09-01'), new DateTimeImmutable('2026-10-01'));
$assert(count($samePeriodFacts) === 1 && (float) $samePeriodFacts[0]['previous']['valor_canonico'] === 80.0, 'Novo melhor resultado deve comparar com referência anterior mesmo quando ambos estão no período atual.');

$persisted = benchmarkObservationPersisted(['tipo'=>'one_rm','idmodalidade'=>'m1','modalidade_slug'=>'musculacao','idexercicio'=>'e1','referencia_nome_snapshot'=>'Supino reto','valor_canonico'=>82.5,'data_resultado'=>'2026-08-14','origem'=>'manual','metodo'=>'medido','contexto'=>'teste','oficialidade'=>'nao_aplicavel','excluido_progresso'=>false]);
$assert($persisted['kind'] === 'persisted' && $persisted['origin'] === 'manual' && $persisted['method'] === 'medido', 'Observação persistida precisa manter provenance separada.');
$derived = benchmarkObservationEstimatedOneRm(['key'=>'e1','nome'=>'Supino reto','current_best_e1rm'=>88.7,'latest_date'=>'2026-08-28','current_best_e1rm_source'=>['load_kg'=>70,'reps'=>8,'date'=>'2026-08-28','idregistro'=>'r1']]);
$assert(is_array($derived) && $derived['kind'] === 'derived' && $derived['method'] === 'estimado' && $derived['evidence']['activity_id'] === 'r1' && $derived['evidence']['load_kg'] === 70, 'e1RM deve continuar derivado e apontar para série/atividade de origem.');
$athletics = benchmarkObservationAthletics(['slug'=>'atletismo-100m','nome'=>'100 m','best_time_s'=>12.34,'date'=>'2026-08-20','idregistro'=>'r2','idunidade_atividade'=>'u1'], 'athletics_time');
$assert(is_array($athletics) && $athletics['kind'] === 'derived' && $athletics['evidence']['unit_id'] === 'u1', 'Atletismo precisa continuar derivado da tentativa original.');

$assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS benchmarks_usuario') && str_contains($migration, 'valor_canonico NUMERIC') && str_contains($migration, 'distancia_m NUMERIC'), 'Migration precisa criar entidade transversal com valores canônicos estruturais.');
foreach (['origem VARCHAR', 'metodo VARCHAR', 'contexto VARCHAR', 'oficialidade VARCHAR', 'protocolo VARCHAR', 'provider VARCHAR', 'external_source_id VARCHAR', 'excluido_progresso BOOLEAN'] as $fragment) $assert(str_contains($migration, $fragment), "Migration precisa estruturar {$fragment}.");
$assert(str_contains($migration, 'metadados JSONB') && !str_contains($migration, "INSERT INTO benchmarks_usuario"), 'JSONB deve ficar restrito a detalhes e não pode existir backfill automático.');
$assert(str_contains($migration, "ON DELETE CASCADE") && str_contains($migration, "idexercicio VARCHAR(21) REFERENCES exercicios(idexercicio) ON DELETE SET NULL") && str_contains($migration, 'referencia_nome_snapshot'), 'Cleanup da conta e snapshot histórico de exercício precisam estar cobertos.');
$assert(str_contains($migration, "tipo <> 'one_rm' OR idexercicio IS NOT NULL OR referencia_nome_snapshot IS NOT NULL"), 'Exclusão futura do exercício precisa preservar 1RM via snapshot sem quebrar constraint.');
$assert(str_contains($migration, 'ix_benchmarks_usuario_modalidade_tipo_data') && str_contains($migration, 'ix_benchmarks_usuario_exercicio_tipo_data') && str_contains($migration, 'ix_benchmarks_usuario_tipo_distancia_data'), 'Consultas principais precisam de índices por modalidade, exercício e distância.');
$assert(!str_contains($migration, 'e1rm') && !str_contains($migration, 'atletismo'), 'Migration não pode persistir automaticamente e1RM nem marcas de Atletismo.');

$assert(str_contains($api, 'stridebr_require_login()') && str_contains($api, 'stridebr_verify_csrf()'), 'CRUD mutável precisa exigir login e CSRF.');
$assert(str_contains($benchmarks, 'b.idusuario=:usuario') && str_contains($benchmarks, 'idusuario = :usuario') && str_contains($benchmarks, 'ra.idusuario = :usuario'), 'CRUD/evidências precisam filtrar ownership no servidor.');
$assert(str_contains($benchmarks, "(idusuario IS NULL OR idusuario = :usuario)"), 'Exercício/modalidade privada de outro usuário não pode ser relacionada.');
$assert(str_contains($benchmarks, "if (\$officiality === 'verificado' && \$origin === 'manual')"), 'Entrada manual nunca pode virar verificação do StrideBR.');
$assert(str_contains($api, "'origem' => 'manual'") && str_contains($api, "'metodo' = 'medido'") === false && str_contains($api, "\$payload['metodo'] = 'medido'"), 'Formulários precisam definir provenance segura no backend em vez de expor enums técnicos.');
$assert(str_contains($api, "benchmarkApplyReportedOfficialInput(\$payload, isset(\$_POST['reported_official']))"), 'Endpoint deve normalizar oficialidade/contexto antes de persistir teste de corrida.');
$assert(str_contains($benchmarks, "if (\$reportedOfficial) \$input['contexto'] = 'competicao';") && str_contains($benchmarks, "benchmarkValidateOfficialContext(\$officiality, \$context);"), 'API deve ajudar o usuário e domínio deve proteger a invariável oficial=Competição.');
$assert(!str_contains($api, "\$payload['contexto'] = 'teste'") && str_contains($progress, "\$benchmarkFormContext = (string) (\$benchmarkFormRow['contexto'] ?? '');"), 'Contexto opcional não pode ser preenchido silenciosamente como teste.');
$assert(str_contains($progress, 'data-progress-reported-official') && str_contains($progress, 'data-progress-official-context') && str_contains($progress, 'data-progress-benchmark-context'), 'Dialog de corrida precisa expor hooks para sincronizar oficialidade e contexto sem perder fallback HTML.');
$assert(str_contains($js, "context.value = 'competicao'") && str_contains($js, 'context.disabled = true') && str_contains($js, 'officialContext.disabled = false'), 'JS deve forçar Competição e manter hidden enviado quando o select estiver desabilitado.');
$assert(str_contains($gitignore, '__pycache__/') && str_contains($gitignore, '*.py[cod]'), 'Cache/bytecode Python precisa estar ignorado sem esconder scripts .py reais.');

$assert(str_contains($progress, "stridebr_t('benchmarks.measured_one_rm')") && str_contains($progress, "stridebr_t('progress.estimated_1rm')"), 'Musculação precisa mostrar 1RM medido separado do e1RM.');
$assert(str_contains($progress, "benchmarkObservationEstimatedOneRm") && str_contains($progress, "progress.e1rm_source"), 'UI de e1RM precisa manter provenance da série-fonte.');
$assert(str_contains($progress, "new_benchmark'=>'ftp'") && str_contains($progress, "benchmarks.current_ftp"), 'Ciclismo precisa integrar FTP contextual.');
$assert(str_contains($progress, "new_benchmark'=>'css'") && str_contains($progress, "benchmarks.current_css"), 'Natação precisa integrar CSS contextual.');
$assert(str_contains($progress, "new_benchmark'=>'distance_time'") && str_contains($progress, 'benchmarkGroupDistanceTests'), 'Corrida precisa integrar testes por distância sem misturar protocolos.');
$assert(str_contains($progress, 'data-progress-benchmark-dialog') && str_contains($js, 'activateBenchmarkDialog'), 'CRUD contextual precisa usar dialog acessível com progressive enhancement.');
$assert(str_contains($progress, 'progress-benchmark-check') && str_contains($progress, "benchmarks.report_as_official_help"), 'UI precisa explicar que oficialidade manual é apenas informada.');
$assert(str_contains($hub, 'benchmark_count') && str_contains($hub, 'benchmarks_usuario'), 'Modalidade com benchmark histórico precisa continuar acessível em Progresso.');
$assert(str_contains($account, "'benchmarks' =>") && str_contains($account, "'benchmarks_usuario'"), 'Exportação completa da conta precisa incluir marcas/testes.');
$assert(str_contains($css, '.progress-benchmark-dialog') && str_contains($css, '.progress-benchmark-summary') && str_contains($css, '@media(max-width:620px)'), 'B1 precisa reutilizar design system com desktop/mobile.');
$assert(str_contains($progress, 'progress-strength-exercise-list') && str_contains($css, '.progress-strength-exercise-list .progress-benchmark-history{grid-column:1/-1'), 'Histórico de 1RM precisa ocupar linha própria sem comprimir valores do exercício.');
$assert(str_contains($js, 'AbortController') && str_contains($js, 'history.pushState') && str_contains($js, "window.addEventListener('popstate'"), 'B1 não pode regredir progressive enhancement da Fase A.');

foreach (['benchmarks.title','benchmarks.register_one_rm','benchmarks.register_ftp','benchmarks.register_css','benchmarks.register_test','benchmarks.measured_one_rm','benchmarks.reported_official','benchmarks.method.estimado','benchmarks.context.competicao','benchmarks.error.verified_not_available','benchmarks.error.official_requires_competition','benchmarks.error.invalid_metadata'] as $key) {
    $assert(str_contains($pt, "'{$key}'") && str_contains($en, "'{$key}'"), "Tradução {$key} precisa existir em PT-BR e EN.");
}

printf("✓ progress phase B1 static/domain: %d assertions\n", $checks);
