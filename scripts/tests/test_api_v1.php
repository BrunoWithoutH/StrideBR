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
        'metrics'=>['distance_m'=>1000.0, 'duration_s'=>600.0, 'elevation_gain_m'=>10.0, 'elevation_min_m'=>420.5, 'elevation_max_m'=>438.25],
        'gps'=>[
            'points'=>[
                ['lat'=>-27.3581,'lon'=>-53.3942,'altitude_m'=>421.2,'accuracy_m'=>4.5,'timestamp_ms'=>1789120800000],
                ['lat'=>-27.3582,'lon'=>-53.3939,'altitude_m'=>423.8,'accuracy_m'=>3.2,'timestamp_ms'=>1789120805000],
            ],
            'measured_distance_m'=>995.0,
            'points_received'=>2,
            'points_rejected'=>0,
            'accuracy_avg_m'=>3.85,
            'accuracy_best_m'=>3.2,
            'accuracy_worst_m'=>4.5,
        ],
        'privacy'=>['hide_route_start_m'=>35, 'hide_route_end_m'=>45],
    ];
    $created = stridebr_api_create_activity($pdo, $owner, $mobilePayload, 'alpha-mobile-idem-0001');
    AlphaTest::assert(empty($created['reused']), 'Primeira publicação mobile não pode ser marcada como reutilizada');
    AlphaTest::assert((string)($created['activity']['origin'] ?? '') === 'gps', 'Atividade publicada pelo Android precisa manter origem GPS');
    AlphaTest::same('stridebr_android', (string)$pdo->query("SELECT origem_provedor FROM registros_atividade WHERE idregistro = " . $pdo->quote((string)$created['id']))->fetchColumn(), 'Atividade Android precisa persistir provenance GPS do app');
    AlphaTest::same($owner, (string)$pdo->query("SELECT idusuario FROM registros_atividade WHERE idregistro = " . $pdo->quote((string)$created['id']))->fetchColumn(), 'Atividade mobile precisa pertencer ao usuário autenticado');
    $again = stridebr_api_create_activity($pdo, $owner, $mobilePayload, 'alpha-mobile-idem-0001');
    AlphaTest::assert(!empty($again['reused']), 'Reenvio com a mesma Idempotency-Key precisa reutilizar atividade');
    AlphaTest::same((string)$created['id'], (string)$again['id'], 'Idempotência não pode gerar segundo ID');
    $duplicates = $pdo->prepare("SELECT COUNT(*) FROM registros_atividade WHERE idusuario = :user AND titulo = 'Alpha Android GPS'");
    $duplicates->execute([':user'=>$owner]);
    AlphaTest::same(1, (int)$duplicates->fetchColumn(), 'Reenvio mobile não pode duplicar atividade');
    AlphaTest::same([], stridebr_api_activity_detail($pdo, (string)$created['id'], $other), 'Outro usuário não pode abrir atividade criada pelo mobile');
    $mobileDetail = stridebr_api_activity_detail($pdo, (string)$created['id'], $owner);
    AlphaTest::same(1000.0, (float)$mobileDetail['distance_m'], 'Detalhe v2 precisa reconstruir distância canônica');
    AlphaTest::same(600.0, (float)$mobileDetail['duration_s'], 'Detalhe v2 precisa reconstruir duração canônica');
    AlphaTest::same(10.0, (float)$mobileDetail['elevation_gain_m'], 'Detalhe v2 precisa reconstruir ganho de elevação');
    AlphaTest::same(420.5, (float)$mobileDetail['elevation_min_m'], 'Detalhe v2 precisa preservar elevação mínima');
    AlphaTest::same(438.25, (float)$mobileDetail['elevation_max_m'], 'Detalhe v2 precisa preservar elevação máxima');
    AlphaTest::assert(abs((float)$mobileDetail['average_speed_mps'] - (1000.0 / 600.0)) < 0.00001, 'Velocidade média deve ser derivável de distância/duração');
    AlphaTest::same(35, (int)$mobileDetail['route_privacy']['hide_start_m'], 'Privacidade de início da rota precisa ser preservada');
    AlphaTest::same(45, (int)$mobileDetail['route_privacy']['hide_end_m'], 'Privacidade de fim da rota precisa ser preservada');
    AlphaTest::same(2, count($mobileDetail['route']['points'] ?? []), 'Detalhe v2 precisa devolver os pontos GPS disponíveis');
    AlphaTest::same(421.2, (float)$mobileDetail['route']['points'][0]['altitude_m'], 'Altitude por ponto precisa sobreviver ao roundtrip');
    AlphaTest::same(4.5, (float)$mobileDetail['route']['points'][0]['accuracy_m'], 'Precisão por ponto precisa sobreviver ao roundtrip');
    AlphaTest::same(1789120800000, (int)$mobileDetail['route']['points'][0]['timestamp_ms'], 'Timestamp por ponto precisa sobreviver ao roundtrip');
    AlphaTest::same(995.0, (float)$mobileDetail['gps']['measured_distance_m'], 'Detalhe v2 precisa expor distância medida bruta');
    AlphaTest::same(2, (int)$mobileDetail['gps']['points_received'], 'Detalhe v2 precisa expor pontos recebidos');
    AlphaTest::same(3.85, (float)$mobileDetail['gps']['accuracy_avg_m'], 'Detalhe v2 precisa expor precisão média');
    AlphaTest::same(3.2, (float)$mobileDetail['gps']['accuracy_best_m'], 'Detalhe v2 precisa expor melhor precisão');
    AlphaTest::same(4.5, (float)$mobileDetail['gps']['accuracy_worst_m'], 'Detalhe v2 precisa expor pior precisão');
    $legacyDetail = stridebr_api_activity_detail($pdo, $activity, $owner);
    AlphaTest::same(null, $legacyDetail['route'], 'Atividade antiga sem GPS continua válida com route nula');
    AlphaTest::same(null, $legacyDetail['gps'], 'Atividade antiga sem metadata GPS continua válida com gps nulo');

    $listed = stridebr_api_list_activities($pdo, $owner, ['page'=>1, 'limit'=>100]);
    AlphaTest::assert((bool)array_filter($listed['data'], static fn(array $item): bool => (string)$item['id'] === (string)$created['id']), 'GET activities precisa listar atividade publicada pelo mobile');
    $mobileSummary = array_values(array_filter($listed['data'], static fn(array $item): bool => (string)$item['id'] === (string)$created['id']))[0] ?? [];
    AlphaTest::same(1000.0, (float)($mobileSummary['distance_m'] ?? 0), 'Summary v2 precisa expor distância sem abrir detalhe');
    AlphaTest::same(600.0, (float)($mobileSummary['duration_s'] ?? 0), 'Summary v2 precisa expor duração sem abrir detalhe');
    AlphaTest::same(10.0, (float)($mobileSummary['elevation_gain_m'] ?? 0), 'Summary v2 precisa expor ganho de elevação sem abrir detalhe');
    AlphaTest::assert(!array_key_exists('route', $mobileSummary) && !array_key_exists('gps', $mobileSummary), 'Listagem não pode carregar track GPS completo');
    $filteredSport = stridebr_api_list_activities($pdo, $owner, ['sport'=>(string)$routeSportRow['slug'], 'q'=>'Alpha Android']);
    AlphaTest::same(1, count(array_filter($filteredSport['data'], static fn(array $item): bool => (string)$item['id'] === (string)$created['id'])), 'Filtros sport/q precisam encontrar atividade mobile');
    $otherList = stridebr_api_list_activities($pdo, $other, ['q'=>'Alpha Android GPS']);
    AlphaTest::same(0, count($otherList['data']), 'Listagem nunca pode vazar atividade de outro usuário');

    $aBundleCount = $pdo->prepare('SELECT COUNT(*) FROM activity_stream_bundles WHERE idregistro = :id');
    $aBundleCount->execute([':id' => (string) $created['id']]);
    AlphaTest::same(0, (int) $aBundleCount->fetchColumn(), 'Variante A não deve criar bundle de streams');
    $aLapCount = $pdo->prepare('SELECT COUNT(*) FROM activity_laps WHERE idregistro = :id');
    $aLapCount->execute([':id' => (string) $created['id']]);
    AlphaTest::same(0, (int) $aLapCount->fetchColumn(), 'Variante A não deve criar laps');

    $streamSamples = [
        ['elapsed_ms'=>0, 'moving_ms'=>0, 'distance_m'=>0.0, 'altitude_m'=>421.2, 'horizontal_accuracy_m'=>4.5, 'route_point_index'=>0],
        ['elapsed_ms'=>5000, 'moving_ms'=>5000, 'distance_m'=>20.0, 'speed_mps'=>4.0, 'altitude_m'=>423.8, 'horizontal_accuracy_m'=>3.2, 'route_point_index'=>1],
    ];
    $streamsPayload = $mobilePayload;
    $streamsPayload['title'] = 'Alpha Android GPS Streams';
    $streamsPayload['streams'] = ['schema_version'=>1, 'source'=>'stridebr_android', 'samples'=>$streamSamples];
    $streamsCreated = stridebr_api_create_activity($pdo, $owner, $streamsPayload, 'alpha-mobile-idem-streams-0001');
    AlphaTest::assert(empty($streamsCreated['reused']), 'Variante B precisa criar Activity com streams');
    $streamBundle = $pdo->prepare('SELECT sample_count, source FROM activity_stream_bundles WHERE idregistro = :id');
    $streamBundle->execute([':id'=>(string)$streamsCreated['id']]);
    $streamBundleRow = $streamBundle->fetch();
    AlphaTest::assert((bool)$streamBundleRow, 'Variante B precisa persistir bundle de streams');
    AlphaTest::same(2, (int)$streamBundleRow['sample_count'], 'Variante B precisa persistir todos os samples');
    AlphaTest::same('stridebr_android', (string)$streamBundleRow['source'], 'Variante B precisa preservar source dos streams');
    $streamSampleCount = $pdo->prepare('SELECT COUNT(*) FROM activity_stream_samples s JOIN activity_stream_bundles b ON b.idbundle=s.idbundle WHERE b.idregistro=:id');
    $streamSampleCount->execute([':id'=>(string)$streamsCreated['id']]);
    AlphaTest::same(2, (int)$streamSampleCount->fetchColumn(), 'Variante B precisa persistir samples do bundle');

    $lapsPayload = $streamsPayload;
    $lapsPayload['title'] = 'Alpha Android GPS Streams Laps';
    $lapsPayload['laps'] = [
        ['start_elapsed_ms'=>0, 'end_elapsed_ms'=>5000, 'start_moving_ms'=>0, 'end_moving_ms'=>5000, 'start_distance_m'=>0.0, 'end_distance_m'=>20.0],
    ];
    $lapsCreated = stridebr_api_create_activity($pdo, $owner, $lapsPayload, 'alpha-mobile-idem-streams-laps-0001');
    AlphaTest::assert(empty($lapsCreated['reused']), 'Variante C precisa criar Activity com streams e laps');
    $lapCount = $pdo->prepare('SELECT COUNT(*) FROM activity_laps WHERE idregistro=:id AND origin=:origin');
    $lapCount->execute([':id'=>(string)$lapsCreated['id'], ':origin'=>'manual']);
    AlphaTest::same(1, (int)$lapCount->fetchColumn(), 'Variante C precisa persistir lap manual');
    $lapsAgain = stridebr_api_create_activity($pdo, $owner, $lapsPayload, 'alpha-mobile-idem-streams-laps-0001');
    AlphaTest::assert(!empty($lapsAgain['reused']), 'Retry da variante C precisa reutilizar Activity');
    AlphaTest::same((string)$lapsCreated['id'], (string)$lapsAgain['id'], 'Retry da variante C precisa manter o mesmo ID');
    $lapCount->execute([':id'=>(string)$lapsCreated['id'], ':origin'=>'manual']);
    AlphaTest::same(1, (int)$lapCount->fetchColumn(), 'Retry da variante C não pode duplicar lap');
    $streamSampleCount->execute([':id'=>(string)$lapsCreated['id']]);
    AlphaTest::same(2, (int)$streamSampleCount->fetchColumn(), 'Retry da variante C não pode duplicar samples');
    $cDuplicates = $pdo->prepare("SELECT COUNT(*) FROM registros_atividade WHERE idusuario = :user AND titulo = 'Alpha Android GPS Streams Laps'");
    $cDuplicates->execute([':user'=>$owner]);
    AlphaTest::same(1, (int)$cDuplicates->fetchColumn(), 'Retry da variante C não pode duplicar Activity');

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
