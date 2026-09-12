<?php

declare(strict_types=1);

return function (PDO $pdo): void {
    $actor = alphaTestUser($pdo, 'marketing_actor');
    $user = alphaTestUser($pdo, 'marketing_user');

    $campaignId = stridebr_marketing_campaign_create($pdo, $actor, [
        'nome' => 'Alpha test campaign',
        'codigo' => 'alpha_test_campaign',
        'tipo' => 'paid_social',
        'status' => 'ativa',
        'inicio' => date('Y-m-d'),
        'descricao' => 'Synthetic acquisition test',
    ]);
    AlphaTest::assert($campaignId !== '', 'Campanha deve ser criada');
    AlphaTest::throws(fn() => stridebr_marketing_campaign_create($pdo, $actor, [
        'nome' => 'Duplicate', 'codigo' => 'alpha_test_campaign', 'tipo' => 'other', 'status' => 'planejada',
    ]), 'Código de campanha precisa ser único');

    $placementId = stridebr_marketing_placement_create($pdo, $campaignId, [
        'nome' => 'Story Alpha',
        'codigo' => 'alpha_test_story',
        'subtipo' => 'story',
        'destino' => '/',
        'ativo' => '1',
    ]);
    AlphaTest::assert($placementId !== '', 'Placement deve ser criado');
    AlphaTest::throws(fn() => stridebr_marketing_placement_create($pdo, $campaignId, [
        'nome' => 'Unsafe', 'codigo' => 'alpha_test_unsafe', 'destino' => 'https://evil.example/', 'ativo' => '1',
    ]), 'Placement não pode aceitar open redirect');

    $eligible = stridebr_marketing_lookup_placement($pdo, 'alpha_test_story', true);
    AlphaTest::assert(is_array($eligible) && (string) $eligible['idplacement'] === $placementId, 'Placement ativo de campanha ativa deve aceitar redirect');

    unset($_COOKIE[stridebr_marketing_cookie_name()], $_SESSION['StrideBRMarketingAttributionHash']);
    $_SESSION['StrideBRMarketingSession'] = str_repeat('a', 32);
    $_SERVER['REQUEST_URI'] = '/?utm_source=instagram&utm_medium=paid_social&utm_campaign=alpha_test_campaign&utm_content=alpha_test_story';
    $attribution = stridebr_marketing_capture_request($pdo, [
        'utm_source' => 'instagram',
        'utm_medium' => 'paid_social',
        'utm_campaign' => 'alpha_test_campaign',
        'utm_content' => 'alpha_test_story',
    ]);
    AlphaTest::assert(is_array($attribution), 'UTM conhecida deve criar atribuição');
    AlphaTest::same($campaignId, (string) $attribution['idcampanha'], 'Atribuição deve ligar campanha conhecida');
    AlphaTest::same($placementId, (string) $attribution['idplacement'], 'utm_content deve ligar placement da campanha');
    AlphaTest::same('instagram', (string) $attribution['utm_source'], 'UTM source deve ser preservada');

    $same = stridebr_marketing_capture_request($pdo, ['utm_source' => 'outra', 'utm_campaign' => 'outra']);
    AlphaTest::same((string) $attribution['chave_hash'], (string) ($same['chave_hash'] ?? ''), 'First-touch não deve ser substituído');
    AlphaTest::same('instagram', (string) ($same['utm_source'] ?? ''), 'Origem inicial permanece imutável');

    AlphaTest::assert(stridebr_marketing_record_event($pdo, 'landing_view', null, '/'), 'Primeira landing deve ser registrada');
    AlphaTest::assert(!stridebr_marketing_record_event($pdo, 'landing_view', null, '/'), 'Refresh da mesma sessão não duplica landing');
    AlphaTest::assert(stridebr_marketing_record_event($pdo, 'signup_start', null, '/signup.php'), 'Signup start deve ser registrado');
    AlphaTest::assert(!stridebr_marketing_record_event($pdo, 'signup_start', null, '/signup.php'), 'Signup start deve deduplicar sessão');

    $linked = stridebr_marketing_link_user($pdo, $user);
    AlphaTest::same($user, (string) ($linked['idusuario'] ?? ''), 'Atribuição deve acompanhar conta criada');
    AlphaTest::assert(stridebr_marketing_record_event($pdo, 'signup_complete', $user, '/signup.php'), 'Signup complete real deve ser registrado');
    AlphaTest::assert(!stridebr_marketing_record_event($pdo, 'signup_complete', $user, '/signup.php'), 'Signup complete não duplica');
    AlphaTest::assert(stridebr_marketing_record_event($pdo, 'activation', $user, '/user/atividades.php'), 'Primeira ativação deve ser registrada');
    AlphaTest::assert(!stridebr_marketing_record_event($pdo, 'activation', $user, '/user/atividades.php'), 'Ativação é única por usuário');

    $metrics = stridebr_marketing_metrics($pdo, $campaignId);
    AlphaTest::same(1, $metrics['acessos'], 'Campanha contabiliza uma entrada');
    AlphaTest::same(1, $metrics['signup_iniciados'], 'Campanha contabiliza signup iniciado');
    AlphaTest::same(1, $metrics['signup_concluidos'], 'Campanha contabiliza signup concluído');
    AlphaTest::same(1, $metrics['ativacoes'], 'Campanha contabiliza ativação');
    AlphaTest::assert(abs((float) $metrics['conversao_cadastro'] - 100.0) < 0.0001, 'Conversão visita cadastro calculada');
    AlphaTest::assert(abs((float) $metrics['conversao_ativacao'] - 100.0) < 0.0001, 'Conversão cadastro ativação calculada');

    $placementMetrics = stridebr_marketing_placements_with_metrics($pdo, $campaignId);
    $row = array_values(array_filter($placementMetrics, static fn(array $item): bool => (string) $item['idplacement'] === $placementId))[0] ?? null;
    AlphaTest::assert(is_array($row), 'Placement deve aparecer no agregado');
    AlphaTest::same(1, (int) $row['acessos'], 'Placement contabiliza acesso');
    AlphaTest::same(1, (int) $row['signup_concluidos'], 'Placement contabiliza conversão');

    unset($_COOKIE[stridebr_marketing_cookie_name()], $_SESSION['StrideBRMarketingAttributionHash']);
    $_SESSION['StrideBRMarketingSession'] = str_repeat('b', 32);
    AlphaTest::assert(stridebr_marketing_current_attribution($pdo) === null, 'Fluxo direto funciona sem atribuição');
    AlphaTest::assert(stridebr_marketing_record_event($pdo, 'landing_view', null, '/'), 'Entrada direta também é contabilizada');
    $lateCampaign = stridebr_marketing_capture_request($pdo, ['utm_source' => 'instagram', 'utm_medium' => 'paid_social', 'utm_campaign' => 'alpha_test_campaign', 'utm_content' => 'alpha_test_story']);
    AlphaTest::assert(is_array($lateCampaign), 'Origem explícita posterior pode virar primeiro touch conhecido');
    AlphaTest::assert(stridebr_marketing_record_event($pdo, 'landing_view', null, '/?utm_campaign=alpha_test_campaign'), 'Landing explícita posterior não é engolida pela entrada direta da sessão');
    $overall = stridebr_marketing_metrics($pdo);
    AlphaTest::assert($overall['diretos'] >= 1, 'Painel geral distingue entrada direta/desconhecida');
    AlphaTest::assert($overall['atribuidos'] >= 1, 'Painel geral distingue entrada atribuída');
};
