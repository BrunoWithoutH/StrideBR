<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/function/competitions.php';
require_once dirname(__DIR__, 2) . '/src/function/benchmarks.php';

return function (PDO $pdo): void {
    $user = alphaTestUser($pdo, 'progress_b3');
    $other = alphaTestUser($pdo, 'progress_b3_other');

    $sport = static function (PDO $pdo, string $slug): array {
        $stmt = $pdo->prepare('SELECT idmodalidade,slug,familia_hub FROM modalidades WHERE slug=:slug AND ativo=TRUE ORDER BY idusuario NULLS FIRST LIMIT 1');
        $stmt->execute([':slug'=>$slug]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw new RuntimeException("Modalidade seed ausente: {$slug}");
        return $row;
    };
    $running = $sport($pdo, 'corrida');
    $cycling = $sport($pdo, 'ciclismo');

    $modelStmt = $pdo->prepare('SELECT idmodelo FROM modelos_modalidade WHERE idmodalidade=:modalidade AND ativo=TRUE ORDER BY padrao DESC,versao DESC LIMIT 1');
    $modelStmt->execute([':modalidade'=>$running['idmodalidade']]);
    $runModel = (string) $modelStmt->fetchColumn();
    if ($runModel === '') throw new RuntimeException('Modelo de corrida ausente para B3');

    AlphaTest::throws(fn()=>competitionCreate($pdo, $user, ['nome'=>'x','data_inicio'=>'2026-09-10','status'=>'realizada']), 'nome curto deve ser rejeitado');
    AlphaTest::throws(fn()=>competitionCreate($pdo, $user, ['nome'=>'Competição B3','data_inicio'=>'2026-09-10','data_fim'=>'2026-09-09','status'=>'realizada']), 'data fim anterior ao início deve ser rejeitada');
    AlphaTest::throws(fn()=>competitionCreate($pdo, $user, ['nome'=>'Competição B3','data_inicio'=>'2026-09-10','status'=>'invalido']), 'status inválido deve ser rejeitado');
    AlphaTest::throws(fn()=>competitionCreate($pdo, $user, ['nome'=>'Competição B3','data_inicio'=>'2026-09-10','idmodalidade_principal'=>'modalidade_inexistente']), 'modalidade inexistente deve ser rejeitada');
    AlphaTest::throws(fn()=>competitionCreate($pdo, $user, ['nome'=>'Competição B3','data_inicio'=>'2026-09-10','idevento'=>'evento_inexistente']), 'evento público inexistente deve ser rejeitado');
    AlphaTest::throws(fn()=>competitionCreate($pdo, $user, ['nome'=>'Competição B3','data_inicio'=>'2026-09-10','origem'=>'manual','oficialidade'=>'verificado']), 'manual nunca pode criar competição verificada');

    $competitionA = competitionCreate($pdo, $user, [
        'nome'=>'Corrida Municipal B3', 'data_inicio'=>'2026-09-10', 'status'=>'realizada',
        'idmodalidade_principal'=>$running['idmodalidade'], 'cidade'=>'Frederico Westphalen', 'estado'=>'RS',
        'oficialidade'=>'informado_oficial', 'origem'=>'manual',
    ]);
    AlphaTest::same($user, (string)$competitionA['idusuario'], 'competição criada pertence ao usuário');
    AlphaTest::same('informado_oficial', (string)$competitionA['oficialidade'], 'oficialidade informada fica separada de verificação');

    $competitionB = competitionCreate($pdo, $user, [
        'nome'=>'Open Regional B3', 'data_inicio'=>'2026-09-11', 'data_fim'=>'2026-09-12', 'status'=>'realizada',
        'origem'=>'manual',
    ]);
    $foreignCompetition = competitionCreate($pdo, $other, ['nome'=>'Competição estrangeira B3','data_inicio'=>'2026-09-10','status'=>'realizada']);
    AlphaTest::throws(fn()=>competitionValidateOwned($pdo, $user, (string)$foreignCompetition['idcompeticao']), 'usuário não pode usar competição de outra conta');

    $activityA = 'alpha_b3_run_a';
    $activityB = 'alpha_b3_run_b';
    foreach ([[$activityA,'Prova 5 km'],[$activityB,'Aquecimento competitivo']] as [$id,$title]) {
        $pdo->prepare("INSERT INTO registros_atividade (idregistro,idusuario,idmodalidade,idmodelo,titulo,data_inicio,status,visibilidade,origem) VALUES (:id,:usuario,:modalidade,:modelo,:titulo,'2026-09-10 09:00:00-03','concluido','privado','manual')")
            ->execute([':id'=>$id, ':usuario'=>$user, ':modalidade'=>$running['idmodalidade'], ':modelo'=>$runModel, ':titulo'=>$title]);
        competitionLinkActivity($pdo, $user, $id, (string)$competitionA['idcompeticao']);
    }
    $linkedActivities = competitionActivities($pdo, $user, (string)$competitionA['idcompeticao']);
    AlphaTest::same(2, count($linkedActivities), 'uma competição aceita múltiplas atividades');
    AlphaTest::throws(fn()=>competitionLinkActivity($pdo, $user, $activityA, (string)$foreignCompetition['idcompeticao']), 'atividade não pode apontar para competição de outro usuário');
    AlphaTest::throws(fn()=>competitionLinkActivity($pdo, $other, $activityA, (string)$foreignCompetition['idcompeticao']), 'outro usuário não pode alterar vínculo da atividade');

    $legacyOfficial = benchmarkCreate($pdo, $user, [
        'tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'valor_canonico'=>1800,'distancia_m'=>5000,
        'data_resultado'=>'2026-09-01','origem'=>'manual','metodo'=>'medido','contexto'=>'competicao','oficialidade'=>'informado_oficial',
    ]);
    AlphaTest::same(null, $legacyOfficial['idcompeticao'], 'resultado oficial B1 legado sem competição estruturada continua válido');

    $standalone = benchmarkCreate($pdo, $user, [
        'tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'valor_canonico'=>1674,'distancia_m'=>5000,
        'data_resultado'=>'2026-09-10','origem'=>'manual','metodo'=>'medido','contexto'=>'competicao','oficialidade'=>'informado_oficial',
        'idcompeticao'=>$competitionA['idcompeticao'],
    ]);
    AlphaTest::same((string)$competitionA['idcompeticao'], (string)$standalone['idcompeticao'], 'benchmark standalone pode ter competição direta');
    AlphaTest::same('Corrida Municipal B3', (string)$standalone['competicao_nome'], 'benchmark standalone expõe nome da competição');

    $linkedBenchmark = benchmarkCreate($pdo, $user, [
        'tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'valor_canonico'=>1700,'distancia_m'=>5000,
        'data_resultado'=>'2026-09-10','origem'=>'atividade','metodo'=>'medido','contexto'=>'competicao','idregistro'=>$activityA,
        'idcompeticao'=>$competitionA['idcompeticao'],
    ]);
    AlphaTest::same(null, $linkedBenchmark['idcompeticao'], 'benchmark ligado a atividade não duplica FK da competição');
    AlphaTest::same((string)$competitionA['idcompeticao'], (string)$linkedBenchmark['competicao_efetiva'], 'benchmark ligado a atividade herda competição efetiva');
    AlphaTest::throws(fn()=>benchmarkCreate($pdo, $user, [
        'tipo'=>'distance_time','idmodalidade'=>$running['idmodalidade'],'valor_canonico'=>1690,'distancia_m'=>5000,
        'data_resultado'=>'2026-09-10','origem'=>'atividade','metodo'=>'medido','contexto'=>'competicao','idregistro'=>$activityA,
        'idcompeticao'=>$competitionB['idcompeticao'],
    ]), 'benchmark não pode declarar competição diferente da atividade');

    competitionLinkActivity($pdo, $user, $activityA, (string)$competitionB['idcompeticao']);
    $movedBenchmark = benchmarkGet($pdo, $user, (string)$linkedBenchmark['idbenchmark']);
    AlphaTest::same((string)$competitionB['idcompeticao'], (string)$movedBenchmark['competicao_efetiva'], 'mover atividade muda competição efetiva do benchmark sem duplicar dado');
    AlphaTest::same(null, $movedBenchmark['idcompeticao'], 'benchmark continua sem FK concorrente depois de mover atividade');
    $competitionBMarks = competitionBenchmarks($pdo, $user, (string)$competitionB['idcompeticao']);
    AlphaTest::assert(count(array_filter($competitionBMarks, static fn(array $row): bool => (string)$row['idbenchmark'] === (string)$linkedBenchmark['idbenchmark'])) === 1, 'detalhe da nova competição inclui benchmark inferido da atividade');

    competitionLinkActivity($pdo, $user, $activityB, null);
    $stmt = $pdo->prepare('SELECT idcompeticao FROM registros_atividade WHERE idregistro=:id');
    $stmt->execute([':id'=>$activityB]);
    AlphaTest::same(null, $stmt->fetchColumn(), 'vínculo de atividade pode ser removido');

    $eventId = 'alpha_b3_event';
    $pdo->prepare("INSERT INTO eventos_esportivos (idevento,titulo,slug,idmodalidade,data_inicio,data_fim,cidade,estado,pais,organizador,status,criado_por) VALUES (:id,'JIFSul B3','jifsul-b3',:modalidade,'2026-10-12 08:00:00-03','2026-10-16 20:00:00-03','Santa Maria','RS','Brasil','IFFar','publicado',:usuario)")
        ->execute([':id'=>$eventId, ':modalidade'=>$running['idmodalidade'], ':usuario'=>$user]);
    $beforeSaved = count(competitionList($pdo, $user));
    $pdo->prepare('INSERT INTO eventos_salvos (idusuario,idevento) VALUES (:usuario,:evento)')->execute([':usuario'=>$user, ':evento'=>$eventId]);
    AlphaTest::same($beforeSaved, count(competitionList($pdo, $user)), 'salvar evento público não cria competição pessoal');
    $prefill = competitionPrefillFromEvent($pdo, $eventId);
    AlphaTest::same('JIFSul B3', $prefill['nome'], 'evento público pode pré-preencher snapshot de competição pessoal');
    AlphaTest::same('catalogo', $prefill['origem'], 'participação criada do catálogo registra origem catálogo');
    $catalogCompetition = competitionCreate($pdo, $user, $prefill);
    AlphaTest::same($eventId, (string)$catalogCompetition['idevento'], 'competição pessoal pode apontar para evento público');
    AlphaTest::same((string)$catalogCompetition['idcompeticao'], (string)competitionFindByEvent($pdo, $user, $eventId)['idcompeticao'], 'fluxo de catálogo encontra participação já existente');
    $pdo->prepare('DELETE FROM eventos_esportivos WHERE idevento=:evento')->execute([':evento'=>$eventId]);
    $afterEventDelete = competitionGet($pdo, $user, (string)$catalogCompetition['idcompeticao']);
    AlphaTest::assert(is_array($afterEventDelete), 'excluir evento público não apaga competição pessoal');
    AlphaTest::same(null, $afterEventDelete['idevento'], 'FK de evento público vira NULL com segurança');

    $export = accountExportData($pdo, $user);
    AlphaTest::assert(isset($export['competicoes']) && count($export['competicoes']) >= 3, 'exportação inclui competições pessoais');
    $exportCompetition = $export['competicoes'][0] ?? [];
    foreach (['nome','data_inicio','status','oficialidade','origem','idmodalidade_principal','idevento'] as $field) AlphaTest::assert(array_key_exists($field, $exportCompetition), "exportação preserva {$field}");
    $exportActivity = null;
    foreach (($export['atividades'] ?? []) as $item) {
        $row = is_array($item['atividade'] ?? null) ? $item['atividade'] : [];
        if (($row['idregistro'] ?? '') === $activityA) $exportActivity = $row;
    }
    AlphaTest::assert(is_array($exportActivity) && array_key_exists('idcompeticao', $exportActivity), 'atividade exportada inclui referência à competição');
    $exportBenchmark = null;
    foreach (($export['benchmarks'] ?? []) as $row) if (($row['idbenchmark'] ?? '') === $standalone['idbenchmark']) $exportBenchmark = $row;
    AlphaTest::assert(is_array($exportBenchmark) && (string)($exportBenchmark['idcompeticao'] ?? '') === (string)$competitionA['idcompeticao'], 'benchmark standalone exporta referência à competição');

    AlphaTest::assert(competitionDelete($pdo, $user, (string)$competitionB['idcompeticao']), 'proprietário pode excluir competição');
    $stmt = $pdo->prepare('SELECT idcompeticao FROM registros_atividade WHERE idregistro=:id');
    $stmt->execute([':id'=>$activityA]);
    AlphaTest::same(null, $stmt->fetchColumn(), 'excluir competição não apaga atividade e remove somente vínculo');
    AlphaTest::assert(benchmarkGet($pdo, $user, (string)$linkedBenchmark['idbenchmark']) !== null, 'excluir competição não apaga benchmark vinculado à atividade');

    AlphaTest::assert(competitionDelete($pdo, $user, (string)$competitionA['idcompeticao']), 'competição com benchmark standalone também pode ser excluída');
    $standaloneAfterDelete = benchmarkGet($pdo, $user, (string)$standalone['idbenchmark']);
    AlphaTest::assert(is_array($standaloneAfterDelete), 'excluir competição não apaga benchmark standalone');
    AlphaTest::same(null, $standaloneAfterDelete['idcompeticao'], 'benchmark standalone perde somente FK por SET NULL');

    $cascadeUser = alphaTestUser($pdo, 'progress_b3_cascade');
    $cascadeCompetition = competitionCreate($pdo, $cascadeUser, ['nome'=>'Cascade B3','data_inicio'=>'2026-09-10','status'=>'realizada']);
    $pdo->prepare('DELETE FROM usuarios WHERE idusuario=:usuario')->execute([':usuario'=>$cascadeUser]);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM competicoes_usuario WHERE idcompeticao=:id');
    $stmt->execute([':id'=>$cascadeCompetition['idcompeticao']]);
    AlphaTest::same(0, (int)$stmt->fetchColumn(), 'exclusão da conta remove competição user-owned por cascade');
};
