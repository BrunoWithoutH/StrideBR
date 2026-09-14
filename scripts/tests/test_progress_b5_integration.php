<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/function/combat_progress.php';

return function (PDO $pdo): void {
    $sport = static function (PDO $pdo, string $slug): array {
        $stmt = $pdo->prepare('SELECT idmodalidade,slug,familia_hub FROM modalidades WHERE slug=:slug AND ativo=TRUE ORDER BY idusuario NULLS FIRST LIMIT 1');
        $stmt->execute([':slug'=>$slug]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw new RuntimeException("Modalidade seed ausente: {$slug}");
        return $row;
    };
    $model = static function (PDO $pdo, string $modalityId): string {
        $stmt = $pdo->prepare('SELECT idmodelo FROM modelos_modalidade WHERE idmodalidade=:modalidade AND ativo=TRUE ORDER BY padrao DESC,versao DESC LIMIT 1');
        $stmt->execute([':modalidade'=>$modalityId]);
        $id = $stmt->fetchColumn();
        if (!is_string($id) || $id === '') throw new RuntimeException("Modelo seed ausente para {$modalityId}");
        return $id;
    };

    $user = alphaTestUser($pdo, 'progress_b5');
    $other = alphaTestUser($pdo, 'progress_b5_other');
    $jiu = $sport($pdo, 'jiu-jitsu');
    $boxe = $sport($pdo, 'boxe');
    $running = $sport($pdo, 'corrida');

    AlphaTest::same('combat', sportCatalogFamilyKey((string)$jiu['familia_hub'], '', (string)$jiu['slug']), 'Jiu-jítsu deve resolver pela taxonomia canônica combat');
    AlphaTest::throws(fn()=>combatRankCreate($pdo, $user, ['idmodalidade'=>$running['idmodalidade'],'graduacao'=>'Faixa azul','data_graduacao'=>'2025-01-01']), 'Graduação deve rejeitar modalidade não-combat');
    AlphaTest::throws(fn()=>combatRankCreate($pdo, $user, ['idmodalidade'=>$jiu['idmodalidade'],'graduacao'=>'Faixa azul','data_graduacao'=>'2099-01-01']), 'Graduação futura deve ser rejeitada');

    $emptyBoxing = combatProgressDashboard($pdo, $user, (string)$boxe['idmodalidade']);
    AlphaTest::same(null, $emptyBoxing['ranks']['current'] ?? null, 'Modalidade combat sem graduação deve continuar válida');
    AlphaTest::same([], $emptyBoxing['techniques'] ?? null, 'Modalidade combat sem repertório deve continuar válida');

    $white = combatRankCreate($pdo, $user, ['idmodalidade'=>$jiu['idmodalidade'],'sistema'=>'Academia Alpha','graduacao'=>'Faixa branca','data_graduacao'=>'2024-03-14','emissor'=>'Equipe Alpha']);
    $blue = combatRankCreate($pdo, $user, ['idmodalidade'=>$jiu['idmodalidade'],'sistema'=>'Academia Alpha','graduacao'=>'Faixa azul','detalhe'=>'2 graus','data_graduacao'=>'2025-03-14','emissor'=>'Equipe Alpha']);
    $lineage = combatRankCreate($pdo, $user, ['idmodalidade'=>$jiu['idmodalidade'],'sistema'=>'Linha B','graduacao'=>'Nível avançado','data_graduacao'=>'2025-08-10']);
    $rankSummary = combatRankSummary($pdo, $user, (string)$jiu['idmodalidade']);
    AlphaTest::same((string)$lineage['idgraduacao'], (string)($rankSummary['current']['idgraduacao'] ?? ''), 'Graduação geral atual deve ser a mais recente por data');
    AlphaTest::same((string)$blue['idgraduacao'], (string)($rankSummary['current_by_system'][combatProgressNormalizeKey('Academia Alpha')]['idgraduacao'] ?? ''), 'Sistema A deve manter sua graduação atual própria');
    AlphaTest::same((string)$lineage['idgraduacao'], (string)($rankSummary['current_by_system'][combatProgressNormalizeKey('Linha B')]['idgraduacao'] ?? ''), 'Sistemas diferentes não devem ser fundidos');
    AlphaTest::same((string)$lineage['idgraduacao'], (string)($rankSummary['history'][0]['idgraduacao'] ?? ''), 'Histórico deve ordenar cronologicamente pela data');

    $blueEdited = combatRankUpdate($pdo, $user, (string)$blue['idgraduacao'], ['idmodalidade'=>$jiu['idmodalidade'],'sistema'=>'Academia Alpha','graduacao'=>'Faixa azul','detalhe'=>'3 graus','data_graduacao'=>'2025-03-14','emissor'=>'Equipe Alpha']);
    AlphaTest::same('3 graus', (string)$blueEdited['detalhe'], 'Graduação própria deve ser editável');
    AlphaTest::throws(fn()=>combatRankUpdate($pdo, $other, (string)$blue['idgraduacao'], ['graduacao'=>'Outra','data_graduacao'=>'2025-03-14']), 'Outro usuário não pode editar graduação');
    AlphaTest::assert(!combatRankDelete($pdo, $other, (string)$white['idgraduacao']), 'Outro usuário não pode excluir graduação');
    AlphaTest::assert(combatRankDelete($pdo, $user, (string)$white['idgraduacao']), 'Proprietário pode corrigir histórico excluindo registro');

    $armbar = combatTechniqueCreate($pdo, $user, ['idmodalidade'=>$jiu['idmodalidade'],'nome'=>'Armbar','categoria_code'=>'submission','estado'=>'learning','observacoes'=>'Entrada da guarda']);
    AlphaTest::same('armbar', (string)$armbar['nome_normalizado'], 'Nome de técnica deve ser normalizado');
    AlphaTest::throws(fn()=>combatTechniqueCreate($pdo, $user, ['idmodalidade'=>$jiu['idmodalidade'],'nome'=>'  armbar  ','categoria_code'=>'submission','estado'=>'learning']), 'Duplicata equivalente deve ser rejeitada');
    $armBar = combatTechniqueCreate($pdo, $user, ['idmodalidade'=>$jiu['idmodalidade'],'nome'=>'Arm bar','categoria_code'=>'submission','estado'=>'learning']);
    AlphaTest::assert((string)$armBar['idtecnica'] !== (string)$armbar['idtecnica'], 'Nomes distintos devem ser preservados sem fuzzy matching');
    AlphaTest::throws(fn()=>combatTechniqueCreate($pdo, $user, ['idmodalidade'=>$running['idmodalidade'],'nome'=>'Técnica corrida','estado'=>'learning']), 'Técnica deve rejeitar modalidade não-combat');
    AlphaTest::throws(fn()=>combatTechniqueCreate($pdo, $user, ['idmodalidade'=>$jiu['idmodalidade'],'nome'=>'Inválida','categoria_code'=>'mastery','estado'=>'learning']), 'Categoria fora da taxonomia deve ser rejeitada');
    AlphaTest::throws(fn()=>combatTechniqueCreate($pdo, $user, ['idmodalidade'=>$jiu['idmodalidade'],'nome'=>'Inválida 2','estado'=>'mastered']), 'Estado não suportado deve ser rejeitado');

    $armbarUpdated = combatTechniqueUpdate($pdo, $user, (string)$armbar['idtecnica'], ['idmodalidade'=>$jiu['idmodalidade'],'nome'=>'Armbar','categoria_code'=>'submission','estado'=>'practicing','observacoes'=>'Praticar controle do punho']);
    AlphaTest::same('practicing', (string)$armbarUpdated['estado'], 'Autoavaliação deve ser editável');
    AlphaTest::throws(fn()=>combatTechniqueUpdate($pdo, $other, (string)$armbar['idtecnica'], ['nome'=>'Invadida']), 'Outro usuário não pode editar técnica');
    $archived = combatTechniqueSetArchived($pdo, $user, (string)$armBar['idtecnica'], true);
    AlphaTest::same('archived', (string)$archived['estado'], 'Técnica deve poder ser arquivada');
    AlphaTest::assert(!empty($archived['archived_at']), 'Arquivamento deve registrar timestamp');
    $reactivated = combatTechniqueSetArchived($pdo, $user, (string)$armBar['idtecnica'], false);
    AlphaTest::same('learning', (string)$reactivated['estado'], 'Técnica deve poder ser reativada');
    AlphaTest::same(null, $reactivated['archived_at'], 'Reativação deve limpar archived_at');

    $jiuModel = $model($pdo, (string)$jiu['idmodalidade']);
    $runningModel = $model($pdo, (string)$running['idmodalidade']);
    $pdo->prepare("INSERT INTO registros_atividade (idregistro,idusuario,idmodalidade,idmodelo,titulo,data_inicio,status,visibilidade,origem) VALUES ('alpha_b5_jiu_act',:usuario,:modalidade,:modelo,'Treino Jiu B5','2026-08-10 19:00:00-03','concluido','privado','manual')")
        ->execute([':usuario'=>$user, ':modalidade'=>$jiu['idmodalidade'], ':modelo'=>$jiuModel]);
    $pdo->prepare("INSERT INTO registros_atividade (idregistro,idusuario,idmodalidade,idmodelo,titulo,data_inicio,status,visibilidade,origem) VALUES ('alpha_b5_run_act',:usuario,:modalidade,:modelo,'Corrida B5','2026-08-11 18:00:00-03','concluido','privado','manual')")
        ->execute([':usuario'=>$user, ':modalidade'=>$running['idmodalidade'], ':modelo'=>$runningModel]);
    $pdo->prepare("INSERT INTO registros_atividade (idregistro,idusuario,idmodalidade,idmodelo,titulo,data_inicio,status,visibilidade,origem) VALUES ('alpha_b5_other_act',:usuario,:modalidade,:modelo,'Outro B5','2026-08-12 19:00:00-03','concluido','privado','manual')")
        ->execute([':usuario'=>$other, ':modalidade'=>$jiu['idmodalidade'], ':modelo'=>$jiuModel]);

    $p1 = combatPracticeCreate($pdo, $user, ['idtecnica'=>$armbar['idtecnica'],'data_pratica'=>'2026-07-01','observacoes'=>'Drill']);
    AlphaTest::same('manual', (string)$p1['origem'], 'Prática sem atividade deve ser manual');
    $p2 = combatPracticeCreate($pdo, $user, ['idtecnica'=>$armbar['idtecnica'],'data_pratica'=>'2026-08-10','idregistro'=>'alpha_b5_jiu_act','observacoes'=>'Sparring']);
    AlphaTest::same('activity', (string)$p2['origem'], 'Prática ligada a atividade deve derivar origem activity');
    AlphaTest::same('alpha_b5_jiu_act', (string)$p2['idregistro'], 'Prática deve referenciar atividade sem copiar métricas');
    AlphaTest::throws(fn()=>combatPracticeCreate($pdo, $user, ['idtecnica'=>$armbar['idtecnica'],'data_pratica'=>'2026-08-11','idregistro'=>'alpha_b5_run_act']), 'Atividade de outra modalidade deve ser rejeitada');
    AlphaTest::throws(fn()=>combatPracticeCreate($pdo, $user, ['idtecnica'=>$armbar['idtecnica'],'data_pratica'=>'2026-08-12','idregistro'=>'alpha_b5_other_act']), 'Atividade de outro usuário deve ser rejeitada');
    AlphaTest::throws(fn()=>combatPracticeCreate($pdo, $user, ['idtecnica'=>$armbar['idtecnica'],'data_pratica'=>'2099-01-01']), 'Prática futura deve ser rejeitada');
    combatPracticeCreate($pdo, $user, ['idtecnica'=>$armBar['idtecnica'],'data_pratica'=>'2026-08-10','idregistro'=>'alpha_b5_jiu_act']);
    $sameActivity = $pdo->prepare("SELECT count(*) FROM praticas_tecnica WHERE idusuario=:usuario AND idregistro='alpha_b5_jiu_act'");
    $sameActivity->execute([':usuario'=>$user]);
    AlphaTest::same(2, (int)$sameActivity->fetchColumn(), 'Uma atividade pode evidenciar múltiplas técnicas');

    $armbarSummary = combatTechniqueGet($pdo, $user, (string)$armbar['idtecnica']);
    AlphaTest::same(2, (int)$armbarSummary['practice_count'], 'Técnica deve derivar quantidade de práticas');
    AlphaTest::same('2026-07-01', (string)$armbarSummary['first_practice'], 'Técnica deve derivar primeira prática');
    AlphaTest::same('2026-08-10', (string)$armbarSummary['last_practice'], 'Técnica deve derivar última prática');
    $practiceHistory = combatPracticeList($pdo, $user, (string)$armbar['idtecnica']);
    AlphaTest::same((string)$p2['idpratica'], (string)$practiceHistory[0]['idpratica'], 'Histórico de práticas deve ordenar pela data mais recente');
    AlphaTest::assert(combatPracticeDelete($pdo, $user, (string)$p1['idpratica']), 'Proprietário pode excluir prática');
    AlphaTest::assert(!combatPracticeDelete($pdo, $other, (string)$p2['idpratica']), 'Outro usuário não pode excluir prática');

    $dashboard = combatProgressDashboard($pdo, $user, (string)$jiu['idmodalidade']);
    AlphaTest::assert((int)($dashboard['state_counts']['practicing'] ?? 0) >= 1, 'Dashboard deve derivar técnicas por autoavaliação');
    AlphaTest::assert(count($dashboard['activity_options'] ?? []) >= 1, 'Dashboard deve oferecer atividades compatíveis para vínculo opcional');

    $export = accountExportData($pdo, $user);
    AlphaTest::assert(count($export['graduacoes'] ?? []) >= 2, 'Exportação da conta deve incluir histórico de graduação');
    AlphaTest::assert(count($export['tecnicas'] ?? []) >= 2, 'Exportação da conta deve incluir repertório técnico');
    AlphaTest::assert(count($export['praticas_tecnicas'] ?? []) >= 2, 'Exportação da conta deve incluir práticas técnicas');

    $cascade = alphaTestUser($pdo, 'progress_b5_cascade');
    $cascadeRank = combatRankCreate($pdo, $cascade, ['idmodalidade'=>$jiu['idmodalidade'],'graduacao'=>'Faixa branca','data_graduacao'=>'2025-01-01']);
    $cascadeTechnique = combatTechniqueCreate($pdo, $cascade, ['idmodalidade'=>$jiu['idmodalidade'],'nome'=>'Triangle','categoria_code'=>'submission','estado'=>'learning']);
    $cascadePractice = combatPracticeCreate($pdo, $cascade, ['idtecnica'=>$cascadeTechnique['idtecnica'],'data_pratica'=>'2025-02-01']);
    $pdo->prepare('DELETE FROM usuarios WHERE idusuario=:usuario')->execute([':usuario'=>$cascade]);
    foreach ([['graduacoes_usuario','idgraduacao',$cascadeRank['idgraduacao']], ['tecnicas_usuario','idtecnica',$cascadeTechnique['idtecnica']], ['praticas_tecnica','idpratica',$cascadePractice['idpratica']]] as [$table,$column,$id]) {
        $stmt = $pdo->prepare("SELECT count(*) FROM {$table} WHERE {$column}=:id");
        $stmt->execute([':id'=>$id]);
        AlphaTest::same(0, (int)$stmt->fetchColumn(), "Exclusão da conta deve remover {$table} por cascade");
    }
};
