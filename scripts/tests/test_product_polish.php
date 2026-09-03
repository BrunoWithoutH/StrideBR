<?php
return function (PDO $pdo): void {
    foreach (['metas_usuario', 'metas_conclusoes', 'eventos_esportivos', 'eventos_fontes', 'eventos_imagens', 'eventos_salvos'] as $table) {
        AlphaTest::assert((bool) $pdo->query("SELECT to_regclass('stridebr.$table')")->fetchColumn(), "Tabela do Product Polish ausente: $table");
    }

    $flags = $pdo->query("SELECT chave, ativo FROM feature_flags WHERE chave IN ('registration.enabled','registration.invite_only.enabled','events.enabled')")->fetchAll(PDO::FETCH_KEY_PAIR);
    AlphaTest::assert(stridebr_db_bool($flags['registration.enabled'] ?? false), 'Cadastro público deveria estar habilitado');
    AlphaTest::assert(!stridebr_db_bool($flags['registration.invite_only.enabled'] ?? true), 'Cadastro por convite deveria estar desabilitado');
    AlphaTest::assert(stridebr_db_bool($flags['events.enabled'] ?? false), 'Eventos deveria estar habilitado');

    $user = alphaTestUser($pdo, 'product_polish');

    dashboardCriarMeta($pdo, $user, [
        'nome' => 'Treinar cinco dias',
        'metrica' => 'dias_ativos',
        'periodo' => 'semanal',
        'valor_alvo' => '5',
        'idmodalidade' => '',
    ]);
    $goals = dashboardListarMetas($pdo, $user);
    AlphaTest::same(1, count($goals), 'Meta criada não apareceu');
    AlphaTest::same('dias_ativos', (string) $goals[0]['metrica'], 'Métrica dias_ativos não foi persistida');
    AlphaTest::throws(fn() => dashboardCriarMeta($pdo, $user, [
        'metrica' => 'dias_ativos', 'periodo' => 'semanal', 'valor_alvo' => '8',
    ]), 'Meta impossível de dias ativos foi aceita');
    AlphaTest::throws(fn() => dashboardCriarMeta($pdo, $user, [
        'metrica' => 'atividades', 'periodo' => 'personalizado', 'valor_alvo' => '3', 'data_inicio' => '2026-09-10', 'data_fim' => '2026-09-01',
    ]), 'Prazo de meta invertido foi aceito');


    dashboardCriarMeta($pdo, $user, [
        'nome' => 'Correr 42 km sem prazo',
        'metrica' => 'distancia',
        'periodo' => 'continuo',
        'valor_alvo' => '42',
        'idmodalidade' => '',
    ]);
    $continuous = array_values(array_filter(dashboardListarMetas($pdo, $user), static fn(array $goal): bool => (string) $goal['periodo'] === 'continuo'));
    AlphaTest::same(1, count($continuous), 'Meta sem prazo não foi criada');
    AlphaTest::assert(!empty($continuous[0]['data_inicio']), 'Meta sem prazo não recebeu data inicial');
    AlphaTest::assert(empty($continuous[0]['data_fim']), 'Meta sem prazo recebeu data final indevida');
    AlphaTest::same('0', dashboardFormatarNumero(0, 0), 'Formatação de zero sem casas decimais está incorreta');
    AlphaTest::same('10', dashboardFormatarNumero(10, 0), 'Formatação de inteiro removeu zero significativo');

    $goalId = (string) $goals[0]['idmeta'];
    AlphaTest::assert(dashboardArquivarMeta($pdo, $user, $goalId), 'Meta não foi arquivada');
    AlphaTest::assert(dashboardReativarMeta($pdo, $user, $goalId), 'Meta não foi reativada');

    $eventId = eventosSalvar($pdo, $user, [
        'titulo' => 'Alpha test event corrida',
        'tipo' => 'Corrida de rua',
        'descricao' => 'Evento criado pela suíte de integração.',
        'data_inicio' => '2027-05-12T08:00',
        'data_fim' => '2027-05-12T12:00',
        'cidade' => 'Cidade Teste',
        'estado' => 'RS',
        'pais' => 'Brasil',
        'local_nome' => 'Parque Teste',
        'organizador' => 'Organizador Teste',
        'distancias' => "5 km\n10 km",
        'url_oficial' => 'https://example.com/evento',
        'url_inscricao' => 'https://example.com/inscricao',
        'status' => 'publicado',
        'destaque' => '1',
    ]);
    eventosSalvarFontes($pdo, $eventId, ['Site oficial'], ['https://example.com/evento']);
    $event = eventosBuscarPublico($pdo, $eventId, $user);
    AlphaTest::assert(is_array($event), 'Evento publicado não ficou acessível');
    AlphaTest::same('Cidade Teste', (string) $event['cidade'], 'Local do evento não foi persistido');
    AlphaTest::same(1, count($event['fontes'] ?? []), 'Fonte do evento não foi persistida');
    AlphaTest::throws(fn() => eventosSalvarFontes($pdo, $eventId, ['Fonte ruim'], ['javascript:alert(1)']), 'URL de fonte insegura foi aceita');

    AlphaTest::assert(eventosAlternarSalvo($pdo, $user, $eventId), 'Evento não foi salvo pelo usuário');
    $export = accountExportData($pdo, $user);
    AlphaTest::same($user, (string) ($export['perfil']['idusuario'] ?? ''), 'Exportação não trouxe o perfil correto');
    AlphaTest::assert(count($export['metas'] ?? []) >= 1, 'Exportação não trouxe metas');
    AlphaTest::assert(count($export['eventos_salvos'] ?? []) >= 1, 'Exportação não trouxe eventos salvos');
    AlphaTest::assert(!eventosAlternarSalvo($pdo, $user, $eventId), 'Evento não foi removido dos salvos');

    $pdo->prepare('DELETE FROM eventos_esportivos WHERE idevento=:id')->execute([':id' => $eventId]);
};
