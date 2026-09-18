<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/function/zone_profile_service.php';

return function (PDO $pdo): void {
    $user = alphaTestUser($pdo, 'product-organization-contracts');
    $catalog = $pdo->query("SELECT idexercicio,nome,slug FROM exercicios WHERE idusuario IS NULL AND ativo=TRUE AND length(nome) >= 8 ORDER BY length(nome) DESC, nome LIMIT 2")->fetchAll();
    AlphaTest::assert(count($catalog) >= 2, 'Catálogo StrideBR precisa fornecer exercícios canônicos para os contratos de revisão.');
    [$first, $second] = $catalog;
    $canonical = (string) $first['nome'];
    $canonicalSecond = (string) $second['nome'];
    $legacyId = stridebr_generate_id();
    $fuzzyId = stridebr_generate_id();
    $customId = stridebr_generate_id();
    $insert = $pdo->prepare("INSERT INTO exercicios (idexercicio,idusuario,nome,slug,ativo,visibilidade,status_publicacao) VALUES (:id,:user,:name,:slug,TRUE,'privado','privado')");
    $insert->execute([':id' => $legacyId, ':user' => $user, ':name' => strtoupper($canonical), ':slug' => 'legacy-' . $legacyId]);
    $fuzzyName = $canonical . 'x';
    $insert->execute([':id' => $fuzzyId, ':user' => $user, ':name' => $fuzzyName, ':slug' => 'fuzzy-' . $fuzzyId]);
    $insert->execute([':id' => $customId, ':user' => $user, ':name' => 'RDL Alpha Custom', ':slug' => 'custom-' . $customId]);

    AlphaTest::same('Nome com espaço', cronogramaNormalizarNome("  Nome\u{00A0}com   espaço  "), 'Normalização técnica deve tratar trim, NBSP e espaços repetidos.');
    $resolverCatalog = [
        ['idexercicio' => (string) $first['idexercicio'], 'nome' => $canonical, 'slug' => (string) $first['slug']],
    ];
    $safe = stridebr_exercise_resolve_catalog($resolverCatalog, ['nome' => strtoupper($canonical)]);
    AlphaTest::same('normalized_name', (string) $safe['reason'], 'ALL CAPS deve resolver pelo nome canônico normalizado.');
    AlphaTest::same($canonical, cronogramaNomeExercicioCanonico($pdo, strtoupper($canonical), $resolverCatalog), 'Match seguro deve usar o nome canônico.');
    $slugMatch = stridebr_exercise_resolve_catalog($resolverCatalog, ['slug' => (string) $first['slug']]);
    AlphaTest::same('slug', (string) $slugMatch['reason'], 'Slug inequívoco deve resolver o catálogo.');
    $aliasMatch = stridebr_exercise_resolve_catalog($resolverCatalog, ['nome' => 'apelido alpha'], ['apelido alpha' => $canonical]);
    AlphaTest::same('alias', (string) $aliasMatch['reason'], 'Alias inequívoco deve resolver o catálogo.');

    $resolvedFuzzy = cronogramaResolverExercicioBiblioteca($pdo, $resolverCatalog, '', $fuzzyName);
    AlphaTest::same($fuzzyName, (string) $resolvedFuzzy['nome'], 'Fuzzy/high-confidence não pode reescrever o nome no fluxo de cronograma.');
    $custom = cronogramaResolverExercicioBiblioteca($pdo, $resolverCatalog, '', 'RDL Alpha Custom');
    AlphaTest::same('RDL Alpha Custom', (string) $custom['nome'], 'Nome customizado e acrônimo devem ser preservados.');

    $review = cronogramaRevisarNomesExercicios($pdo, $user);
    $byId = array_column($review, null, 'id');
    AlphaTest::same('safe', (string) ($byId[$legacyId]['kind'] ?? ''), 'Exercício pessoal não pode mascarar o match canônico do catálogo.');
    AlphaTest::same($canonical, (string) ($byId[$legacyId]['candidate'] ?? ''), 'Review safe deve propor o nome canônico.');
    AlphaTest::assert(isset($byId[$fuzzyId]) && ($byId[$fuzzyId]['kind'] ?? '') === 'suggestion', 'Fuzzy/high-confidence deve aparecer como sugestão, não como correção segura.');
    AlphaTest::assert(!isset($byId[$customId]), 'Nome customizado sem match não deve aparecer como correção falsa.');

    $bulk = cronogramaAplicarRevisaoNomesExercicios($pdo, $user);
    AlphaTest::assert($bulk['applied'] >= 1, 'Bulk deve aplicar pelo menos uma correção segura.');
    $name = $pdo->prepare('SELECT nome FROM exercicios WHERE idexercicio=:id AND idusuario=:user');
    $name->execute([':id' => $legacyId, ':user' => $user]);
    AlphaTest::same($canonical, (string) $name->fetchColumn(), 'Bulk deve persistir a correção segura.');
    $name->execute([':id' => $fuzzyId, ':user' => $user]);
    AlphaTest::same($fuzzyName, (string) $name->fetchColumn(), 'Bulk não pode aplicar sugestão fuzzy.');

    $collisionId = stridebr_generate_id();
    $insert->execute([':id' => $collisionId, ':user' => $user, ':name' => 'Outro exercício', ':slug' => 'collision-' . $collisionId]);
    AlphaTest::throws(fn() => cronogramaRenomearExercicioPessoal($pdo, $user, $collisionId, $canonical), 'Colisão de nome pessoal não pode sobrescrever nem mesclar exercícios.');
    $name->execute([':id' => $collisionId, ':user' => $user]);
    AlphaTest::same('Outro exercício', (string) $name->fetchColumn(), 'Colisão deve preservar o exercício original.');
    AlphaTest::assert(!cronogramaRenomearExercicioPessoal($pdo, alphaTestUser($pdo, 'product-organization-other'), $collisionId, $canonicalSecond), 'Renomeação deve respeitar ownership.');

    $manualCatalog = stridebr_exercise_catalog_for_user($pdo, $user);
    $exactEntry = cronogramaResolverExercicioBiblioteca($pdo, $manualCatalog, '', strtoupper($canonical));
    AlphaTest::same($legacyId, $exactEntry['idexercicio'], 'Manual exact prefers personal exercise over identical system name.');
    AlphaTest::same($canonical, $exactEntry['nome'], 'New manual use takes current canonical spelling.');
    $selectedEntry = cronogramaResolverExercicioBiblioteca($pdo, $manualCatalog, $legacyId, strtoupper($canonical));
    AlphaTest::same($canonical, $selectedEntry['nome'], 'New selected use does not propagate obsolete ALL CAPS snapshot.');
    $onlySystem = [$first];
    $typo = $canonical . 'x';
    $typoEntry = cronogramaResolverExercicioBiblioteca($pdo, $onlySystem, '', $typo);
    AlphaTest::same('', $typoEntry['idexercicio'], 'Fuzzy manual input must not silently link catalog identity.');
    AlphaTest::same($typo, $typoEntry['nome'], 'Fuzzy manual input retains submitted spelling until consent.');
    foreach (['RDL','TRX','T-Bar','EZ'] as $acronym) {
        $custom = cronogramaResolverExercicioBiblioteca($pdo, $onlySystem, '', $acronym);
        AlphaTest::same($acronym, $custom['nome'], 'Custom acronym keeps display spelling.');
    }

    AlphaTest::same('/user/atividades.php', zoneProfileReturnTo('/user/atividades.php'), 'return_to interno deve ser aceito.');
    AlphaTest::same('/user/atividades.php?view=history&id=123', zoneProfileReturnTo('/user/atividades.php?view=history&id=123'), 'return_to interno com query deve ser aceito.');
    foreach (['https://evil.example', 'http://evil.example', '//evil.example', '/\\evil.example', 'javascript:alert(1)', "/user/atividades.php\r\nLocation: https://evil.example"] as $invalid) {
        AlphaTest::same('', zoneProfileReturnTo($invalid), 'return_to inseguro deve ser rejeitado.');
    }
};
