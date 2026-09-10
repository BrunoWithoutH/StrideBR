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
    $payload = stridebr_api_user_payload($ownerRow);
    AlphaTest::assert(!array_key_exists('senhausuario', $payload) && !array_key_exists('sessao_versao', $payload), 'Serializador de usuário não pode expor campos internos');
};
