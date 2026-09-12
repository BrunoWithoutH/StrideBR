<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/function/api_v1.php';

return function (PDO $pdo): void {
    AlphaTest::assert((bool) $pdo->query("SELECT to_regclass('stridebr.api_sessoes')")->fetchColumn(), 'Tabela de sessões da API ausente');
    $owner = alphaTestUser($pdo, 'api-owner');
    $other = alphaTestUser($pdo, 'api-other');
    $user = $pdo->prepare('SELECT idusuario, nomeusuario, nome_exibicao, username, emailusuario, fotousuario, papelusuario, statususuario, onboarding_concluido, preferenciasusuario, sessao_versao FROM usuarios WHERE idusuario = :id');
    $user->execute([':id'=>$owner]);
    $ownerRow = $user->fetch();

    $login = stridebr_api_login($pdo, ['email'=>'api-owner@alpha-test.invalid', 'password'=>'Alpha-test-password-123', 'platform'=>'android']);
    AlphaTest::same($owner, $login['user']['id'], 'Login mobile deve usar a mesma identidade do Core');
    AlphaTest::assert(($login['tokens']['token_type'] ?? null) === 'Bearer', 'Login mobile deve emitir Bearer token');

    $issued = stridebr_api_issue_session($pdo, $ownerRow, ['device_id'=>'logical-device', 'device_name'=>'Alpha Android', 'platform'=>'android']);
    AlphaTest::assert(strlen($issued['access_token']) >= 32 && strlen($issued['refresh_token']) >= 48, 'Tokens opacos não foram emitidos');
    $stored = $pdo->prepare('SELECT * FROM api_sessoes WHERE access_token_hash = :hash');
    $stored->execute([':hash'=>stridebr_api_token_hash($issued['access_token'])]);
    $session = $stored->fetch();
    AlphaTest::assert($session && $session['access_token_hash'] !== $issued['access_token'] && $session['refresh_token_hash'] !== $issued['refresh_token'], 'Tokens não podem ser persistidos em texto puro');
    AlphaTest::same($owner, $session['idusuario'], 'Sessão da API deve pertencer ao usuário correto');

    $rotated = stridebr_api_refresh($pdo, ['refresh_token'=>$issued['refresh_token']]);
    AlphaTest::assert($rotated['tokens']['refresh_token'] !== $issued['refresh_token'], 'Refresh deve rotacionar token');
    $old = $pdo->prepare('SELECT revogado_em FROM api_sessoes WHERE idsessao = :id'); $old->execute([':id'=>$session['idsessao']]);
    AlphaTest::assert($old->fetchColumn() !== null, 'Sessão anterior deve ser revogada após refresh');
    $oldRefresh = $pdo->prepare('SELECT revogado_em FROM api_sessoes WHERE refresh_token_hash = :hash');
    $oldRefresh->execute([':hash'=>stridebr_api_token_hash($issued['refresh_token'])]);
    AlphaTest::assert($oldRefresh->fetchColumn() !== null, 'Refresh anterior não pode continuar ativo após rotação');

    $model = alphaTestGeneralModel($pdo);
    $activity = atividadeSalvarRegistro($pdo, $owner, ['idmodelo'=>$model, 'titulo'=>'API owner activity', 'data_inicio'=>'2026-09-10 08:00', 'data_fim'=>'2026-09-10 09:00', 'status'=>'concluido', 'visibilidade'=>'privado']);
    $otherActivity = atividadeSalvarRegistro($pdo, $other, ['idmodelo'=>$model, 'titulo'=>'API other activity', 'data_inicio'=>'2026-09-10 08:00', 'data_fim'=>'2026-09-10 09:00', 'status'=>'concluido', 'visibilidade'=>'privado']);
    $detail = stridebr_api_activity_detail($pdo, $activity, $owner);
    AlphaTest::same($activity, $detail['id'], 'Detalhe deve respeitar ownership');
    AlphaTest::assert(str_contains((string)$detail['started_at'], 'T'), 'Datas da API devem usar ISO 8601');
    AlphaTest::same([], stridebr_api_activity_detail($pdo, $otherActivity, $owner), 'Detalhe não pode expor atividade de outro usuário');
    AlphaTest::same([], stridebr_api_activity_detail($pdo, 'nao-existe', $owner), 'Detalhe inexistente deve ser vazio no domínio');
    $routeModel = alphaTestRouteModel($pdo);
    $routeSport = $pdo->prepare('SELECT m.idmodalidade, m.slug FROM modelos_modalidade mm JOIN modalidades m ON m.idmodalidade = mm.idmodalidade WHERE mm.idmodelo = :id');
    $routeSport->execute([':id'=>$routeModel]);
    $routeSportRow = $routeSport->fetch();
    AlphaTest::assert((bool)$routeSportRow, 'Modalidade de rota seed ausente');
    $mobilePayload = [
        'sport'=>(string)$routeSportRow['slug'],
        'title'=>'Alpha Android GPS',
        'notes'=>'mobile api integration',
        'visibility'=>'privado',
        'perceived_effort'=>6,
        'started_at'=>'2026-09-11T10:00:00-03:00',
        'ended_at'=>'2026-09-11T10:10:00-03:00',
        'metrics'=>['distance_m'=>1000.0, 'duration_s'=>600.0, 'elevation_gain_m'=>10.0],
        'gps'=>['points'=>[['lat'=>-27.3581,'lon'=>-53.3942],['lat'=>-27.3582,'lon'=>-53.3939]], 'measured_distance_m'=>995.0],
        'privacy'=>['hide_route_start_m'=>0, 'hide_route_end_m'=>0],
    ];
    $created = stridebr_api_create_activity($pdo, $owner, $mobilePayload, 'alpha-mobile-idem-0001');
    AlphaTest::assert(empty($created['reused']), 'Primeira publicação mobile não pode ser marcada como reutilizada');
    AlphaTest::assert((string)($created['activity']['origin'] ?? '') === 'gps', 'Atividade publicada pelo Android precisa manter origem GPS');
    AlphaTest::same($owner, (string)$pdo->query("SELECT idusuario FROM registros_atividade WHERE idregistro = " . $pdo->quote((string)$created['id']))->fetchColumn(), 'Atividade mobile precisa pertencer ao usuário autenticado');
    $again = stridebr_api_create_activity($pdo, $owner, $mobilePayload, 'alpha-mobile-idem-0001');
    AlphaTest::assert(!empty($again['reused']), 'Reenvio com a mesma Idempotency-Key precisa reutilizar atividade');
    AlphaTest::same((string)$created['id'], (string)$again['id'], 'Idempotência não pode gerar segundo ID');
    $duplicates = $pdo->prepare("SELECT COUNT(*) FROM registros_atividade WHERE idusuario = :user AND titulo = 'Alpha Android GPS'");
    $duplicates->execute([':user'=>$owner]);
    AlphaTest::same(1, (int)$duplicates->fetchColumn(), 'Reenvio mobile não pode duplicar atividade');
    AlphaTest::same([], stridebr_api_activity_detail($pdo, (string)$created['id'], $other), 'Outro usuário não pode abrir atividade criada pelo mobile');

    $listed = stridebr_api_list_activities($pdo, $owner, ['page'=>1, 'limit'=>100]);
    AlphaTest::assert((bool)array_filter($listed['data'], static fn(array $item): bool => (string)$item['id'] === (string)$created['id']), 'GET activities precisa listar atividade publicada pelo mobile');
    $filteredSport = stridebr_api_list_activities($pdo, $owner, ['sport'=>(string)$routeSportRow['slug'], 'q'=>'Alpha Android']);
    AlphaTest::same(1, count(array_filter($filteredSport['data'], static fn(array $item): bool => (string)$item['id'] === (string)$created['id'])), 'Filtros sport/q precisam encontrar atividade mobile');
    $otherList = stridebr_api_list_activities($pdo, $other, ['q'=>'Alpha Android GPS']);
    AlphaTest::same(0, count($otherList['data']), 'Listagem nunca pode vazar atividade de outro usuário');

    $logoutTokens = stridebr_api_issue_session($pdo, $ownerRow, ['platform'=>'android']);
    $logoutLookup = $pdo->prepare('SELECT idsessao FROM api_sessoes WHERE access_token_hash = :hash');
    $logoutLookup->execute([':hash'=>stridebr_api_token_hash($logoutTokens['access_token'])]);
    $logoutSessionId = (string)$logoutLookup->fetchColumn();
    stridebr_api_logout($pdo, $logoutSessionId);
    $logoutCheck = $pdo->prepare('SELECT revogado_em FROM api_sessoes WHERE idsessao = :id');
    $logoutCheck->execute([':id'=>$logoutSessionId]);
    AlphaTest::assert($logoutCheck->fetchColumn() !== null, 'Logout precisa revogar a sessão mobile');

    $payload = stridebr_api_user_payload($ownerRow);
    AlphaTest::assert(!array_key_exists('senhausuario', $payload) && !array_key_exists('sessao_versao', $payload), 'Serializador de usuário não pode expor campos internos');
};
