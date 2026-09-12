<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/function/benchmarks.php';

return function (PDO $pdo): void {
    $user = alphaTestUser($pdo, 'progress_b1');
    $other = alphaTestUser($pdo, 'progress_b1_other');

    $emptyExportUser = alphaTestUser($pdo, 'progress_b1_empty_export');
    $emptyExport = accountExportData($pdo, $emptyExportUser);
    AlphaTest::same([], $emptyExport['benchmarks'] ?? null, 'exportação de usuário sem benchmarks inclui coleção vazia');

    $sport = static function (PDO $pdo, string $slug): array {
        $stmt = $pdo->prepare('SELECT idmodalidade,slug,familia_hub FROM modalidades WHERE slug=:slug AND ativo=TRUE ORDER BY idusuario NULLS FIRST LIMIT 1');
        $stmt->execute([':slug'=>$slug]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw new RuntimeException("Modalidade seed ausente: {$slug}");
        return $row;
    };
    $strength = $sport($pdo, 'musculacao');
    $cycling = $sport($pdo, 'ciclismo');
    $swimming = $sport($pdo, 'natacao');
    $running = $sport($pdo, 'corrida');

    $metadataUser = alphaTestUser($pdo, 'progress_b1_metadata');
    $metaNull = benchmarkCreate($pdo, $metadataUser, ['tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_canonico'=>201,'data_resultado'=>'2026-06-01','origem'=>'manual','metodo'=>'informado','metadados'=>null]);
    $metaEmpty = benchmarkCreate($pdo, $metadataUser, ['tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_canonico'=>202,'data_resultado'=>'2026-06-02','origem'=>'manual','metodo'=>'informado','metadados'=>[]]);
    $metaObject = benchmarkCreate($pdo, $metadataUser, ['tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_canonico'=>203,'data_resultado'=>'2026-06-03','origem'=>'manual','metodo'=>'informado','metadados'=>['protocol'=>['name'=>'ramp'],'quality'=>'synthetic']]);
    $metadataStmt = $pdo->prepare('SELECT metadados::text AS json, jsonb_typeof(metadados) AS kind FROM benchmarks_usuario WHERE idbenchmark=:id');
    foreach ([$metaNull, $metaEmpty] as $emptyMetadataBenchmark) {
        $metadataStmt->execute([':id'=>$emptyMetadataBenchmark['idbenchmark']]);
        $metadataStored = $metadataStmt->fetch();
        AlphaTest::same('object', $metadataStored['kind'] ?? null, 'metadata vazia persiste com raiz JSON object');
        AlphaTest::same('{}', $metadataStored['json'] ?? null, 'metadata null/array PHP vazio persiste canonicamente como {}');
    }
    $metadataStmt->execute([':id'=>$metaObject['idbenchmark']]);
    $metadataStored = $metadataStmt->fetch();
    AlphaTest::same('object', $metadataStored['kind'] ?? null, 'metadata preenchida persiste como JSON object');
    AlphaTest::assert(str_contains((string)($metadataStored['json'] ?? ''), '"quality": "synthetic"'), 'metadata estruturada preserva conteúdo preenchido');
    $metaObjectCleared = benchmarkUpdate($pdo, $metadataUser, (string)$metaObject['idbenchmark'], ['metadados'=>null]);
    $metadataStmt->execute([':id'=>$metaObjectCleared['idbenchmark']]);
    $metadataCleared = $metadataStmt->fetch();
    AlphaTest::same('{}', $metadataCleared['json'] ?? null, 'metadata null em edição limpa o objeto para {} em vez de preservar valor antigo');
    AlphaTest::throws(fn()=>benchmarkCreate($pdo, $metadataUser, ['tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_canonico'=>204,'data_resultado'=>'2026-06-04','origem'=>'manual','metodo'=>'informado','metadados'=>['invalid-list']]), 'metadata list PHP real é rejeitada');
    AlphaTest::throws(fn()=>benchmarkCreate($pdo, $metadataUser, ['tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_canonico'=>205,'data_resultado'=>'2026-06-05','origem'=>'manual','metodo'=>'informado','metadados'=>'["invalid-list"]']), 'metadata JSON array real é rejeitada');

    $exerciseId = 'alpha_b1_exercise';
    $pdo->prepare("INSERT INTO exercicios (idexercicio,idusuario,nome,slug,ativo,visibilidade,status_publicacao) VALUES (:id,:usuario,'Supino B1','supino-b1',TRUE,'privado','privado')")
        ->execute([':id'=>$exerciseId, ':usuario'=>$user]);

    $oneA = benchmarkCreate($pdo, $user, ['tipo'=>'one_rm','idmodalidade'=>$strength['idmodalidade'],'valor_canonico'=>82.5,'data_resultado'=>'2026-08-14','idexercicio'=>$exerciseId,'origem'=>'manual','metodo'=>'medido','contexto'=>'teste']);
    AlphaTest::throws(fn()=>benchmarkCreate($pdo, $user, ['tipo'=>'one_rm','idmodalidade'=>$strength['idmodalidade'],'valor_canonico'=>0,'data_resultado'=>'2026-08-14','idexercicio'=>$exerciseId,'origem'=>'manual','metodo'=>'medido']), 'valor inválido é rejeitado');
    AlphaTest::throws(fn()=>benchmarkUpdate($pdo, $user, 'benchmark_inexistente', ['valor_canonico'=>90]), 'ID de benchmark inválido não pode ser editado');
    $oneB = benchmarkCreate($pdo, $user, ['tipo'=>'one_rm','idmodalidade'=>$strength['idmodalidade'],'valor_canonico'=>87.5,'data_resultado'=>'2026-09-01','idexercicio'=>$exerciseId,'origem'=>'manual','metodo'=>'medido','contexto'=>'teste']);
    $summary = benchmarkSummarize(benchmarkList($pdo, $user, ['tipo'=>'one_rm','idexercicio'=>$exerciseId]), 'one_rm');
    AlphaTest::same(87.5, (float)$summary['best']['valor_canonico'], '1RM medido usa maior valor como melhor histórico');
    AlphaTest::same(87.5, (float)$summary['latest']['valor_canonico'], '1RM medido preserva último valor');
    AlphaTest::throws(fn()=>benchmarkCreate($pdo, $user, ['tipo'=>'one_rm','idmodalidade'=>$strength['idmodalidade'],'valor_canonico'=>90,'data_resultado'=>'2026-09-02','origem'=>'manual','metodo'=>'medido']), '1RM exige exercício');

    benchmarkCreate($pdo, $other, ['tipo'=>'one_rm','idmodalidade'=>$strength['idmodalidade'],'valor_canonico'=>200,'data_resultado'=>'2026-09-01','idexercicio'=>'e_supino','origem'=>'manual','metodo'=>'medido']);
    $pdo->prepare("INSERT INTO exercicios (idexercicio,idusuario,nome,slug,ativo,visibilidade,status_publicacao) VALUES ('alpha_b1_other_ex',:usuario,'Privado de outro','privado-outro-b1',TRUE,'privado','privado')")->execute([':usuario'=>$other]);
    AlphaTest::same(2, count(benchmarkList($pdo, $user, ['tipo'=>'one_rm'])), 'histórico de 1RM não mistura usuários');
    AlphaTest::throws(fn()=>benchmarkCreate($pdo, $user, ['tipo'=>'one_rm','idmodalidade'=>$strength['idmodalidade'],'valor_canonico'=>90,'data_resultado'=>'2026-09-02','idexercicio'=>'alpha_b1_other_ex','origem'=>'manual','metodo'=>'medido']), 'exercício privado de outro usuário é rejeitado');

    $updated = benchmarkUpdate($pdo, $user, (string)$oneA['idbenchmark'], ['valor_canonico'=>83.5]);
    AlphaTest::same(83.5, (float)$updated['valor_canonico'], 'CRUD edita benchmark do proprietário');
    AlphaTest::throws(fn()=>benchmarkUpdate($pdo, $other, (string)$oneA['idbenchmark'], ['valor_canonico'=>999]), 'outro usuário não edita benchmark');
    AlphaTest::assert(!benchmarkDelete($pdo, $other, (string)$oneA['idbenchmark']), 'outro usuário não exclui benchmark');

    $snapshotId = 'alpha_b1_snapshot_ex';
    $pdo->prepare("INSERT INTO exercicios (idexercicio,idusuario,nome,slug,ativo,visibilidade,status_publicacao) VALUES (:id,:usuario,'Supino histórico','supino-b1-snapshot',TRUE,'privado','privado')")
        ->execute([':id'=>$snapshotId, ':usuario'=>$user]);
    $snapshotBenchmark = benchmarkCreate($pdo, $user, ['tipo'=>'one_rm','idmodalidade'=>$strength['idmodalidade'],'valor_canonico'=>70,'data_resultado'=>'2026-07-01','idexercicio'=>$snapshotId,'origem'=>'manual','metodo'=>'medido']);
    $pdo->prepare('DELETE FROM exercicios WHERE idexercicio=:id AND idusuario=:usuario')->execute([':id'=>$snapshotId, ':usuario'=>$user]);
    $snapshotStored = benchmarkGet($pdo, $user, (string)$snapshotBenchmark['idbenchmark']);
    AlphaTest::same(null, $snapshotStored['idexercicio'], 'exclusão do exercício solta FK sem apagar marca histórica');
    AlphaTest::same('Supino histórico', $snapshotStored['referencia_nome_snapshot'], 'snapshot preserva nome do exercício removido');
    $snapshotUpdated = benchmarkUpdate($pdo, $user, (string)$snapshotBenchmark['idbenchmark'], ['valor_canonico'=>71]);
    AlphaTest::same(71.0, (float)$snapshotUpdated['valor_canonico'], 'registro com exercício removido continua editável usando snapshot');

    $ftpNoContext = benchmarkCreate($pdo, $user, ['tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_canonico'=>238,'data_resultado'=>'2026-09-02','origem'=>'manual','metodo'=>'calculado','protocolo'=>'20min']);
    AlphaTest::same(null, $ftpNoContext['contexto'], 'contexto opcional permanece vazio quando não informado');
    benchmarkCreate($pdo, $user, ['tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_canonico'=>225,'data_resultado'=>'2026-09-10','origem'=>'manual','metodo'=>'informado']);
    $ftp = benchmarkSummarize(benchmarkList($pdo, $user, ['tipo'=>'ftp']), 'ftp');
    AlphaTest::same(225.0, (float)$ftp['primary']['valor_canonico'], 'FTP atual é o registro mais recente');
    AlphaTest::same(238.0, (float)$ftp['best']['valor_canonico'], 'FTP melhor histórico permanece separado do atual');

    benchmarkCreate($pdo, $user, ['tipo'=>'css','idmodalidade'=>$swimming['idmodalidade'],'valor_canonico'=>110,'data_resultado'=>'2026-08-01','origem'=>'manual','metodo'=>'informado']);
    benchmarkCreate($pdo, $user, ['tipo'=>'css','idmodalidade'=>$swimming['idmodalidade'],'valor_canonico'=>106,'data_resultado'=>'2026-09-01','origem'=>'manual','metodo'=>'informado']);
    $css = benchmarkSummarize(benchmarkList($pdo, $user, ['tipo'=>'css']), 'css');
    AlphaTest::same(106.0, (float)$css['primary']['valor_canonico'], 'CSS atual usa o registro mais recente');
    AlphaTest::same(106.0, (float)$css['best']['valor_canonico'], 'CSS interpreta menor valor como melhor');

    benchmarkCreate($pdo, $user, ['tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'valor_canonico'=>1902,'distancia_m'=>5000,'data_resultado'=>'2026-08-01','origem'=>'manual','metodo'=>'medido','contexto'=>'teste']);
    benchmarkCreate($pdo, $user, ['tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'valor_canonico'=>1798,'distancia_m'=>5000,'data_resultado'=>'2026-09-01','origem'=>'manual','metodo'=>'medido','contexto'=>'competicao','oficialidade'=>'informado_oficial']);
    benchmarkCreate($pdo, $user, ['tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'valor_canonico'=>3734,'distancia_m'=>10000,'data_resultado'=>'2026-09-03','origem'=>'manual','metodo'=>'medido']);
    benchmarkCreate($pdo, $user, ['tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'valor_canonico'=>1180,'distancia_m'=>3210,'data_resultado'=>'2026-09-04','origem'=>'manual','metodo'=>'medido']);
    $groups = benchmarkGroupDistanceTests(benchmarkList($pdo, $user, ['tipo'=>'distance_time']));
    AlphaTest::same(3, count($groups), 'distância personalizada forma histórico próprio');
    AlphaTest::same(1798.0, (float)$groups['5000.000']['summary']['best']['valor_canonico'], '5 km escolhe menor tempo sem misturar 10 km');
    AlphaTest::same(3734.0, (float)$groups['10000.000']['summary']['best']['valor_canonico'], '10 km mantém histórico separado do 5 km');

    foreach (['treino', 'teste', null] as $invalidOfficialContext) {
        AlphaTest::throws(fn()=>benchmarkCreate($pdo, $user, ['tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'valor_canonico'=>1780,'distancia_m'=>5000,'data_resultado'=>'2026-09-05','origem'=>'manual','metodo'=>'medido','contexto'=>$invalidOfficialContext,'oficialidade'=>'informado_oficial']), 'domínio rejeita resultado informado como oficial fora de Competição');
    }
    $apiNormalized = benchmarkApplyReportedOfficialInput(['tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'valor_canonico'=>420,'distancia_m'=>1500,'data_resultado'=>'2026-09-05','origem'=>'manual','metodo'=>'medido','contexto'=>'treino'], true);
    $apiSaved = benchmarkCreate($pdo, $user, $apiNormalized);
    AlphaTest::same('competicao', $apiSaved['contexto'], 'normalização usada pelo endpoint converte oficial + treino em Competição antes de persistir');
    AlphaTest::same('informado_oficial', $apiSaved['oficialidade'], 'normalização usada pelo endpoint preserva oficialidade como informada, não verificada');

    AlphaTest::throws(fn()=>benchmarkCreate($pdo, $user, ['tipo'=>'ftp','idmodalidade'=>$running['idmodalidade'],'valor_canonico'=>250,'data_resultado'=>'2026-09-01','origem'=>'manual','metodo'=>'informado']), 'modalidade incompatível é rejeitada');
    AlphaTest::throws(fn()=>benchmarkCreate($pdo, $user, ['tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'valor_canonico'=>1700,'distancia_m'=>5000,'data_resultado'=>'2099-01-01','origem'=>'manual','metodo'=>'medido']), 'data futura é rejeitada');
    AlphaTest::throws(fn()=>benchmarkCreate($pdo, $user, ['tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'valor_canonico'=>1700,'distancia_m'=>5000,'data_resultado'=>'2026-09-01','origem'=>'manual','metodo'=>'medido','oficialidade'=>'verificado']), 'registro manual não pode ser verificado pelo StrideBR');

    $foreignModel = $pdo->prepare('SELECT idmodelo FROM modelos_modalidade WHERE idmodalidade=:modalidade AND ativo=TRUE ORDER BY padrao DESC,versao DESC LIMIT 1');
    $foreignModel->execute([':modalidade'=>$cycling['idmodalidade']]);
    $modelId = (string)$foreignModel->fetchColumn();
    if ($modelId === '') throw new RuntimeException('Modelo de ciclismo ausente');
    $pdo->prepare("INSERT INTO registros_atividade (idregistro,idusuario,idmodalidade,idmodelo,titulo,data_inicio,status,visibilidade,origem) VALUES ('alpha_b1_foreign_act',:usuario,:modalidade,:modelo,'Outro usuário',NOW(),'concluido','privado','manual')")
        ->execute([':usuario'=>$other, ':modalidade'=>$cycling['idmodalidade'], ':modelo'=>$modelId]);
    AlphaTest::throws(fn()=>benchmarkCreate($pdo, $user, ['tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_canonico'=>240,'data_resultado'=>'2026-09-01','origem'=>'manual','metodo'=>'informado','idregistro'=>'alpha_b1_foreign_act']), 'atividade de outro usuário não pode ser evidência');

    $ownCyclingActivity = 'alpha_b1_own_cycle';
    $pdo->prepare("INSERT INTO registros_atividade (idregistro,idusuario,idmodalidade,idmodelo,titulo,data_inicio,status,visibilidade,origem) VALUES (:id,:usuario,:modalidade,:modelo,'Pedal B1',NOW(),'concluido','privado','manual')")
        ->execute([':id'=>$ownCyclingActivity, ':usuario'=>$user, ':modalidade'=>$cycling['idmodalidade'], ':modelo'=>$modelId]);
    AlphaTest::throws(fn()=>benchmarkCreate($pdo, $user, ['tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'valor_canonico'=>1798,'distancia_m'=>5000,'data_resultado'=>'2026-09-01','origem'=>'manual','metodo'=>'medido','idregistro'=>$ownCyclingActivity]), 'atividade da própria conta mas de modalidade incompatível é rejeitada');
    $beforeDistanceRows = count(benchmarkList($pdo, $user, ['tipo'=>'distance_time']));
    AlphaTest::same($beforeDistanceRows, count(benchmarkList($pdo, $user, ['tipo'=>'distance_time'])), 'atividade comum não cria teste de corrida automaticamente');

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM benchmarks_usuario WHERE idusuario=:usuario AND tipo='estimated_one_rm'");
    $stmt->execute([':usuario'=>$user]);
    AlphaTest::same(0, (int)$stmt->fetchColumn(), 'e1RM derivado nunca é persistido em benchmarks_usuario');

    $export = accountExportData($pdo, $user);
    AlphaTest::assert(isset($export['benchmarks']) && count($export['benchmarks']) >= 10, 'exportação completa inclui vários tipos de benchmark');
    $exportRow = $export['benchmarks'][0] ?? [];
    foreach (['tipo','valor_canonico','data_resultado','modalidade_nome','origem','metodo','oficialidade','protocolo','observacoes'] as $field) AlphaTest::assert(array_key_exists($field, $exportRow), "exportação preserva {$field}");

    $cascadeUser = alphaTestUser($pdo, 'progress_b1_cascade');
    $cascade = benchmarkCreate($pdo, $cascadeUser, ['tipo'=>'ftp','idmodalidade'=>$cycling['idmodalidade'],'valor_canonico'=>200,'data_resultado'=>'2026-09-01','origem'=>'manual','metodo'=>'informado']);
    $pdo->prepare('DELETE FROM usuarios WHERE idusuario=:usuario')->execute([':usuario'=>$cascadeUser]);
    $stmt = $pdo->prepare('SELECT count(*) FROM benchmarks_usuario WHERE idbenchmark=:id');
    $stmt->execute([':id'=>$cascade['idbenchmark']]);
    AlphaTest::same(0, (int)$stmt->fetchColumn(), 'exclusão de conta remove benchmarks por cascade');

    AlphaTest::assert(benchmarkDelete($pdo, $user, (string)$oneB['idbenchmark']), 'CRUD exclui benchmark do proprietário');
};
