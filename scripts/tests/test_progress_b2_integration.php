<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/function/benchmarks.php';
require_once dirname(__DIR__, 2) . '/src/function/benchmark_goals.php';

return function (PDO $pdo): void {
    $sport = static function (PDO $pdo, string $slug): array {
        $stmt = $pdo->prepare('SELECT idmodalidade,slug,familia_hub FROM modalidades WHERE slug=:slug AND ativo=TRUE ORDER BY idusuario NULLS FIRST LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw new RuntimeException("Modalidade seed ausente: {$slug}");
        return $row;
    };
    $goalByName = static function (PDO $pdo, string $userId, string $name): array {
        $stmt = $pdo->prepare('SELECT * FROM metas_usuario WHERE idusuario=:usuario AND nome=:nome ORDER BY data_criacao DESC LIMIT 1');
        $stmt->execute([':usuario' => $userId, ':nome' => $name]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw new RuntimeException("Meta não encontrada: {$name}");
        return $row;
    };
    $conclusion = static function (PDO $pdo, string $goalId): ?array {
        $stmt = $pdo->prepare('SELECT * FROM metas_conclusoes WHERE idmeta=:meta ORDER BY atingida_em DESC LIMIT 1');
        $stmt->execute([':meta' => $goalId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    };

    $strength = $sport($pdo, 'musculacao');
    $cycling = $sport($pdo, 'ciclismo');
    $swimming = $sport($pdo, 'natacao');
    $running = $sport($pdo, 'corrida');
    $today = new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'));
    $yesterday = $today->modify('-1 day')->format('Y-m-d');
    $twoDaysAgo = $today->modify('-2 days')->format('Y-m-d');
    $threeDaysAgo = $today->modify('-3 days')->format('Y-m-d');
    $todayDate = $today->format('Y-m-d');

    $user = alphaTestUser($pdo, 'progress_b2');
    $other = alphaTestUser($pdo, 'progress_b2_other');
    $exercise = 'alpha_b2_benchpress';
    $exerciseOther = 'alpha_b2_squat';
    $pdo->prepare("INSERT INTO exercicios (idexercicio,idusuario,nome,slug,ativo,visibilidade,status_publicacao) VALUES (:id,:usuario,'Supino B2','supino-b2',TRUE,'privado','privado'),(:id2,:usuario,'Agachamento B2','agachamento-b2',TRUE,'privado','privado')")
        ->execute([':id' => $exercise, ':id2' => $exerciseOther, ':usuario' => $user]);

    dashboardCriarMeta($pdo, $user, ['tipo_meta'=>'metrica','metrica'=>'carga_maxima','periodo'=>'continuo','idexercicio'=>$exercise,'valor_alvo'=>'120','nome'=>'Carga legado B2']);
    $legacy = $goalByName($pdo, $user, 'Carga legado B2');
    AlphaTest::same('metrica', $legacy['tipo_meta'], 'meta antiga/criada como prática permanece tipo metrica');
    AlphaTest::same('carga_maxima', $legacy['metrica'], 'carga_maxima preserva semântica legada e não vira one_rm');
    AlphaTest::same(null, $legacy['benchmark_tipo'], 'meta de carga máxima não recebe tipo benchmark');

    benchmarkCreate($pdo, $user, ['tipo'=>'one_rm','idmodalidade'=>$strength['idmodalidade'],'idexercicio'=>$exercise,'valor_canonico'=>80,'data_resultado'=>$twoDaysAgo,'origem'=>'manual','metodo'=>'medido']);
    $baselineOne = benchmarkCreate($pdo, $user, ['tipo'=>'one_rm','idmodalidade'=>$strength['idmodalidade'],'idexercicio'=>$exercise,'valor_canonico'=>85,'data_resultado'=>$yesterday,'origem'=>'manual','metodo'=>'medido']);
    $excludedOne = benchmarkCreate($pdo, $user, ['tipo'=>'one_rm','idmodalidade'=>$strength['idmodalidade'],'idexercicio'=>$exercise,'valor_canonico'=>140,'data_resultado'=>$yesterday,'origem'=>'manual','metodo'=>'medido']);
    $pdo->prepare('UPDATE benchmarks_usuario SET excluido_progresso=TRUE WHERE idbenchmark=:benchmark')->execute([':benchmark'=>$excludedOne['idbenchmark']]);
    benchmarkCreate($pdo, $user, ['tipo'=>'one_rm','idmodalidade'=>$strength['idmodalidade'],'idexercicio'=>$exerciseOther,'valor_canonico'=>130,'data_resultado'=>$yesterday,'origem'=>'manual','metodo'=>'medido']);
    benchmarkCreate($pdo, $other, ['tipo'=>'one_rm','idmodalidade'=>$strength['idmodalidade'],'idexercicio'=>'e_supino','valor_canonico'=>200,'data_resultado'=>$yesterday,'origem'=>'manual','metodo'=>'medido']);

    dashboardCriarMeta($pdo, $user, ['tipo_meta'=>'benchmark','benchmark_tipo'=>'one_rm','idmodalidade'=>$strength['idmodalidade'],'idexercicio'=>$exercise,'valor_alvo'=>'100','periodo'=>'continuo','nome'=>'1RM B2']);
    $oneGoal = $goalByName($pdo, $user, '1RM B2');
    AlphaTest::same('benchmark', $oneGoal['tipo_meta'], '1RM goal é persistido como benchmark');
    AlphaTest::same(85.0, (float)$oneGoal['valor_inicial'], 'baseline de 1RM usa melhor 1RM medido do exercício');
    AlphaTest::same((string)$baselineOne['idbenchmark'], (string)$oneGoal['idbenchmark_inicial'], 'baseline de 1RM preserva benchmark de origem');
    AlphaTest::same($exercise, $oneGoal['idexercicio'], '1RM goal preserva exercício específico');

    AlphaTest::throws(fn()=>dashboardCriarMeta($pdo, $user, ['tipo_meta'=>'benchmark','benchmark_tipo'=>'one_rm','idmodalidade'=>$strength['idmodalidade'],'valor_alvo'=>'110','periodo'=>'continuo','nome'=>'1RM sem exercício']), 'one_rm goal exige exercício');
    AlphaTest::throws(fn()=>dashboardCriarMeta($pdo, $user, ['tipo_meta'=>'benchmark','benchmark_tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_alvo'=>'260','periodo'=>'mensal','nome'=>'FTP recorrente']), 'benchmark goal rejeita período recorrente');

    benchmarkCreate($pdo, $user, ['tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_canonico'=>240,'data_resultado'=>$twoDaysAgo,'origem'=>'manual','metodo'=>'informado']);
    $ftpLatest = benchmarkCreate($pdo, $user, ['tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_canonico'=>230,'data_resultado'=>$yesterday,'origem'=>'manual','metodo'=>'informado']);
    dashboardCriarMeta($pdo, $user, ['tipo_meta'=>'benchmark','benchmark_tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_alvo'=>'250','periodo'=>'continuo','nome'=>'FTP B2']);
    $ftpGoal = $goalByName($pdo, $user, 'FTP B2');
    AlphaTest::same(230.0, (float)$ftpGoal['valor_inicial'], 'baseline FTP usa referência atual B1, o registro mais recente');
    AlphaTest::same((string)$ftpLatest['idbenchmark'], (string)$ftpGoal['idbenchmark_inicial'], 'baseline FTP aponta para registro atual da criação');

    benchmarkCreate($pdo, $user, ['tipo'=>'css','idmodalidade'=>$swimming['idmodalidade'],'valor_canonico'=>104,'data_resultado'=>$twoDaysAgo,'origem'=>'manual','metodo'=>'informado']);
    $cssLatest = benchmarkCreate($pdo, $user, ['tipo'=>'css','idmodalidade'=>$swimming['idmodalidade'],'valor_canonico'=>106,'data_resultado'=>$yesterday,'origem'=>'manual','metodo'=>'informado']);
    dashboardCriarMeta($pdo, $user, ['tipo_meta'=>'benchmark','benchmark_tipo'=>'css','idmodalidade'=>$swimming['idmodalidade'],'valor_alvo'=>'1:40','periodo'=>'continuo','nome'=>'CSS B2']);
    $cssGoal = $goalByName($pdo, $user, 'CSS B2');
    AlphaTest::same(106.0, (float)$cssGoal['valor_inicial'], 'baseline CSS usa referência atual B1, não melhor histórico antigo');
    AlphaTest::same((string)$cssLatest['idbenchmark'], (string)$cssGoal['idbenchmark_inicial'], 'baseline CSS preserva referência atual da criação');
    AlphaTest::same(100.0, (float)$cssGoal['valor_alvo'], 'alvo CSS 1:40 persiste como 100 segundos/100m');

    benchmarkCreate($pdo, $user, ['tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'valor_canonico'=>1800,'distancia_m'=>5000,'data_resultado'=>$twoDaysAgo,'origem'=>'manual','metodo'=>'medido']);
    $fiveBest = benchmarkCreate($pdo, $user, ['tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'valor_canonico'=>1740,'distancia_m'=>5000,'data_resultado'=>$yesterday,'origem'=>'manual','metodo'=>'medido']);
    benchmarkCreate($pdo, $user, ['tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'valor_canonico'=>1500,'distancia_m'=>10000,'data_resultado'=>$yesterday,'origem'=>'manual','metodo'=>'medido']);
    dashboardCriarMeta($pdo, $user, ['tipo_meta'=>'benchmark','benchmark_tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'benchmark_distancia_m'=>'5000','valor_alvo'=>'28:00','periodo'=>'continuo','nome'=>'5K B2']);
    $fiveGoal = $goalByName($pdo, $user, '5K B2');
    AlphaTest::same(1740.0, (float)$fiveGoal['valor_inicial'], 'baseline de corrida usa melhor teste da mesma distância');
    AlphaTest::same((string)$fiveBest['idbenchmark'], (string)$fiveGoal['idbenchmark_inicial'], 'baseline de 5 km aponta para benchmark compatível');
    AlphaTest::same(5000.0, (float)$fiveGoal['benchmark_distancia_m'], 'meta 5 km persiste distância canônica');
    AlphaTest::throws(fn()=>dashboardCriarMeta($pdo, $user, ['tipo_meta'=>'benchmark','benchmark_tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'valor_alvo'=>'27:00','periodo'=>'continuo','nome'=>'Sem distância']), 'distance_time goal exige distância');

    $achievedHigher = alphaTestUser($pdo, 'progress_b2_achieved_higher');
    benchmarkCreate($pdo, $achievedHigher, ['tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_canonico'=>250,'data_resultado'=>$yesterday,'origem'=>'manual','metodo'=>'informado']);
    AlphaTest::throws(fn()=>dashboardCriarMeta($pdo, $achievedHigher, ['tipo_meta'=>'benchmark','benchmark_tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_alvo'=>'250','periodo'=>'continuo','nome'=>'FTP já igual']), 'higher goal já atingida no valor igual deve ser rejeitada');
    AlphaTest::throws(fn()=>dashboardCriarMeta($pdo, $achievedHigher, ['tipo_meta'=>'benchmark','benchmark_tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_alvo'=>'240','periodo'=>'continuo','nome'=>'FTP já abaixo']), 'higher goal já superada deve ser rejeitada');
    dashboardCriarMeta($pdo, $achievedHigher, ['tipo_meta'=>'benchmark','benchmark_tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_alvo'=>'260','periodo'=>'continuo','nome'=>'FTP futuro']);

    $achievedLower = alphaTestUser($pdo, 'progress_b2_achieved_lower');
    benchmarkCreate($pdo, $achievedLower, ['tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'valor_canonico'=>1680,'distancia_m'=>5000,'data_resultado'=>$yesterday,'origem'=>'manual','metodo'=>'medido']);
    AlphaTest::throws(fn()=>dashboardCriarMeta($pdo, $achievedLower, ['tipo_meta'=>'benchmark','benchmark_tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'benchmark_distancia_m'=>5000,'valor_alvo'=>'29:00','periodo'=>'continuo','nome'=>'5K já mais lento']), 'lower goal mais fácil que resultado existente deve ser rejeitada');
    AlphaTest::throws(fn()=>dashboardCriarMeta($pdo, $achievedLower, ['tipo_meta'=>'benchmark','benchmark_tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'benchmark_distancia_m'=>5000,'valor_alvo'=>'28:00','periodo'=>'continuo','nome'=>'5K já igual']), 'lower goal igual ao melhor existente deve ser rejeitada');
    dashboardCriarMeta($pdo, $achievedLower, ['tipo_meta'=>'benchmark','benchmark_tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'benchmark_distancia_m'=>5000,'valor_alvo'=>'27:30','periodo'=>'continuo','nome'=>'5K futuro']);

    $noBaseline = alphaTestUser($pdo, 'progress_b2_no_baseline');
    dashboardCriarMeta($pdo, $noBaseline, ['tipo_meta'=>'benchmark','benchmark_tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_alvo'=>'250','periodo'=>'continuo','nome'=>'FTP sem baseline']);
    $noBaselineGoal = $goalByName($pdo, $noBaseline, 'FTP sem baseline');
    AlphaTest::same(null, $noBaselineGoal['valor_inicial'], 'meta sem resultado anterior nasce sem baseline inventado');
    $firstFtp = benchmarkCreate($pdo, $noBaseline, ['tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_canonico'=>230,'data_resultado'=>$todayDate,'origem'=>'manual','metodo'=>'informado']);
    $evaluatedNoBaseline = benchmarkGoalEvaluate($pdo, $noBaseline, $noBaselineGoal, true);
    AlphaTest::assert(!$evaluatedNoBaseline['percentual_disponivel'], 'primeiro resultado sem baseline não fabrica percentual');
    AlphaTest::same(230.0, (float)$evaluatedNoBaseline['benchmark_best']['valor_canonico'], 'primeiro resultado ainda aparece como melhor desde o início');
    benchmarkCreate($pdo, $noBaseline, ['tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_canonico'=>240,'data_resultado'=>$todayDate,'origem'=>'manual','metodo'=>'informado']);
    $evaluatedNoBaseline2 = benchmarkGoalEvaluate($pdo, $noBaseline, $noBaselineGoal, true);
    AlphaTest::assert($evaluatedNoBaseline2['percentual_disponivel'] && abs((float)$evaluatedNoBaseline2['percentual'] - 50.0) < 0.001, 'segunda observação pode usar a primeira pós-início como baseline visual');
    AlphaTest::same(null, $goalByName($pdo, $noBaseline, 'FTP sem baseline')['valor_inicial'], 'baseline dinâmico não é persistido durante leitura');

    $evidenceUser = alphaTestUser($pdo, 'progress_b2_evidence');
    benchmarkCreate($pdo, $evidenceUser, ['tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_canonico'=>220,'data_resultado'=>$yesterday,'origem'=>'manual','metodo'=>'informado']);
    dashboardCriarMeta($pdo, $evidenceUser, ['tipo_meta'=>'benchmark','benchmark_tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_alvo'=>'250','periodo'=>'continuo','nome'=>'FTP evidência']);
    $evidenceGoal = $goalByName($pdo, $evidenceUser, 'FTP evidência');
    $hitA = benchmarkCreate($pdo, $evidenceUser, ['tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_canonico'=>252,'data_resultado'=>$todayDate,'origem'=>'manual','metodo'=>'informado']);
    benchmarkGoalSyncForUser($pdo, $evidenceUser);
    $savedConclusion = $conclusion($pdo, (string)$evidenceGoal['idmeta']);
    AlphaTest::assert(is_array($savedConclusion), 'benchmark elegível dentro da janela deve concluir meta');
    AlphaTest::same((string)$hitA['idbenchmark'], (string)$savedConclusion['idbenchmark'], 'conclusão guarda benchmark de evidência');
    AlphaTest::same(252.0, (float)$savedConclusion['valor_atingido'], 'conclusão guarda valor canônico real atingido');
    AlphaTest::same($todayDate, (string)$savedConclusion['data_resultado'], 'histórico guarda data esportiva do resultado');

    $hitB = benchmarkCreate($pdo, $evidenceUser, ['tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_canonico'=>255,'data_resultado'=>$todayDate,'origem'=>'manual','metodo'=>'informado']);
    benchmarkUpdate($pdo, $evidenceUser, (string)$hitA['idbenchmark'], ['valor_canonico'=>245]);
    benchmarkGoalSyncForUser($pdo, $evidenceUser);
    $replacementConclusion = $conclusion($pdo, (string)$evidenceGoal['idmeta']);
    AlphaTest::same((string)$hitB['idbenchmark'], (string)$replacementConclusion['idbenchmark'], 'editar evidência inválida deve usar outra evidência satisfatória quando existir');
    benchmarkDelete($pdo, $evidenceUser, (string)$hitB['idbenchmark']);
    benchmarkGoalSyncForUser($pdo, $evidenceUser);
    AlphaTest::same(null, $conclusion($pdo, (string)$evidenceGoal['idmeta']), 'excluir última evidência válida remove conclusão benchmark');
    $reopened = $goalByName($pdo, $evidenceUser, 'FTP evidência');
    AlphaTest::same(null, $reopened['concluida_em'], 'meta volta a ativa/não concluída quando evidência desaparece');

    $windowUser = alphaTestUser($pdo, 'progress_b2_window');
    benchmarkCreate($pdo, $windowUser, ['tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_canonico'=>220,'data_resultado'=>$twoDaysAgo,'origem'=>'manual','metodo'=>'informado']);
    dashboardCriarMeta($pdo, $windowUser, ['tipo_meta'=>'benchmark','benchmark_tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_alvo'=>'250','periodo'=>'continuo','nome'=>'FTP janela']);
    $windowGoal = $goalByName($pdo, $windowUser, 'FTP janela');
    $pdo->prepare("UPDATE metas_usuario SET data_inicio=:inicio,data_fim=:fim,periodo='personalizado' WHERE idmeta=:meta")
        ->execute([':inicio'=>$twoDaysAgo, ':fim'=>$yesterday, ':meta'=>$windowGoal['idmeta']]);
    $windowGoal = $goalByName($pdo, $windowUser, 'FTP janela');
    benchmarkCreate($pdo, $windowUser, ['tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_canonico'=>270,'data_resultado'=>$threeDaysAgo,'origem'=>'manual','metodo'=>'informado']);
    $beforeStartEvaluation = benchmarkGoalEvaluate($pdo, $windowUser, $windowGoal, true);
    AlphaTest::assert(!$beforeStartEvaluation['atingida'], 'resultado satisfatório anterior ao início não pode concluir meta nova');
    benchmarkCreate($pdo, $windowUser, ['tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_canonico'=>260,'data_resultado'=>$todayDate,'origem'=>'manual','metodo'=>'informado']);
    $windowEvaluation = benchmarkGoalEvaluate($pdo, $windowUser, $windowGoal, true);
    AlphaTest::assert(!$windowEvaluation['atingida'], 'resultado depois do deadline não pode concluir meta');
    $eligibleBackdated = benchmarkCreate($pdo, $windowUser, ['tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_canonico'=>251,'data_resultado'=>$yesterday,'origem'=>'manual','metodo'=>'informado']);
    $windowEvaluation2 = benchmarkGoalEvaluate($pdo, $windowUser, $windowGoal, true);
    AlphaTest::assert($windowEvaluation2['atingida'], 'resultado cadastrado depois, mas com data esportiva dentro da janela, pode concluir');
    AlphaTest::same((string)$eligibleBackdated['idbenchmark'], (string)$windowEvaluation2['benchmark_evidence']['idbenchmark'], 'evidência da janela usa data_resultado, não timestamp técnico de criação');

    AlphaTest::throws(fn()=>dashboardEditarMeta($pdo, $user, (string)$oneGoal['idmeta'], ['tipo_meta'=>'benchmark','benchmark_tipo'=>'ftp','idmodalidade'=>$strength['idmodalidade'],'idexercicio'=>$exercise,'valor_alvo'=>'105','periodo'=>'continuo']), 'edição não pode trocar tipo benchmark');
    AlphaTest::throws(fn()=>dashboardEditarMeta($pdo, $user, (string)$fiveGoal['idmeta'], ['tipo_meta'=>'benchmark','benchmark_tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'benchmark_distancia_m'=>10000,'valor_alvo'=>'27:00','periodo'=>'continuo']), 'edição não pode trocar distância/protocolo');

    $export = accountExportData($pdo, $user);
    $exportGoal = null;
    foreach (($export['metas'] ?? []) as $row) if (($row['nome'] ?? '') === '1RM B2') $exportGoal = $row;
    AlphaTest::assert(is_array($exportGoal) && ($exportGoal['tipo_meta'] ?? '') === 'benchmark' && ($exportGoal['benchmark_tipo'] ?? '') === 'one_rm', 'exportação inclui novos campos estruturais da meta benchmark');
    AlphaTest::assert(array_key_exists('idbenchmark', ($export['metas_conclusoes'][0] ?? ['idbenchmark'=>null])) && array_key_exists('data_resultado', ($export['metas_conclusoes'][0] ?? ['data_resultado'=>null])), 'exportação de conclusões suporta evidência benchmark');

    AlphaTest::assert(dashboardArquivarMeta($pdo, $windowUser, (string)$windowGoal['idmeta']), 'meta benchmark concluída pode ser arquivada');
    AlphaTest::assert(!dashboardReativarMeta($pdo, $windowUser, (string)$windowGoal['idmeta']), 'meta benchmark concluída não pode ser reativada para reescrever conquista');
    $archivedWindow = $goalByName($pdo, $windowUser, 'FTP janela');
    AlphaTest::assert(!stridebr_db_bool($archivedWindow['ativa']) && !empty($archivedWindow['concluida_em']), 'arquivamento preserva conclusão benchmark histórica');
};
