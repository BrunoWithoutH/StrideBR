<?php
require_once dirname(__DIR__, 2) . '/src/function/integrations.php';
return function (PDO $pdo): void {
    $env = [];
    foreach (['STRIDEBR_INTEGRATIONS_SECRET', 'STRAVA_CLIENT_ID', 'STRAVA_CLIENT_SECRET'] as $key) { $env[$key] = getenv($key); putenv($key . '=synthetic-only-credential-for-tests-12345678'); }
    $log = tempnam(sys_get_temp_dir(), 'integration-log-');
    $previousLog = ini_get('error_log');
    ini_set('error_log', $log);
    $fixture = json_decode(file_get_contents(__DIR__ . '/fixtures/strava_activity.json'), true, 512, JSON_THROW_ON_ERROR);
    $calls = [];
    $items = [$fixture];
    $status = 200;
    $GLOBALS['stridebr_integrations_http_mock'] = static function ($method, $url, $options) use (&$calls, &$items, &$status): array {
        $calls[] = $url;
        if (str_contains($url, '/oauth/token')) return ['json' => ['access_token' => 'synthetic-access-token', 'refresh_token' => 'synthetic-refresh-token', 'expires_at' => time() + 7200, 'athlete' => ['id' => 42, 'firstname' => 'Atleta', 'lastname' => 'Teste']]];
        if (str_contains($url, '/athlete/activities')) return ['json' => $items];
        if ($status !== 200) return ['status' => $status, 'headers' => ['retry-after' => ['1800']], 'json' => ['message' => 'synthetic-access-token synthetic-refresh-token synthetic-only-credential-for-tests-12345678 Authorization: SECRET']];
        foreach ($items as $item) if (str_ends_with($url, '/' . $item['id'])) return ['json' => $item];
        throw new RuntimeException('Unexpected fixture request');
    };
    try {
        $user = alphaTestUser($pdo, 'integration_sync');
        $token = stridebr_integrations_exchange('strava', 'synthetic-code', []);
        $connection = stridebr_integrations_save_token($pdo, $user, 'strava', $token);
        AlphaTest::same('conectado', $connection['status'], 'OAuth connected');
        AlphaTest::assert(stridebr_db_bool($connection['sincronizar_atividades']), 'Default activity sync ON');
        $result = stridebr_integrations_initial_sync($pdo, $user, 'strava');
        AlphaTest::same(1, $result['created'], 'Realistic Strava activity imports after OAuth');
        AlphaTest::same(0, $result['failed'], 'No failure');
        $stmt = $pdo->prepare('SELECT * FROM registros_atividade WHERE idusuario = :u AND id_externo = :e');
        $stmt->execute([':u' => $user, ':e' => (string) $fixture['id']]);
        $saved = $stmt->fetch();
        AlphaTest::same('2026-09-08 06:14:27', substr($saved['data_inicio'], 0, 19), 'UTC conversion preserves seconds');
        AlphaTest::same('2026-09-08 06:45:31', substr($saved['data_fim'], 0, 19), 'Elapsed duration preserves seconds');
        AlphaTest::same('privado', $saved['visibilidade'], 'Private by default');
        $device = json_decode($saved['dispositivo_origem'], true);
        AlphaTest::same('GPS Watch', $device['name'], 'Device preserved');
        AlphaTest::same(1, count($device['laps']), 'Lap preserved');
        AlphaTest::assert(!isset($device['laps'][0]['start_index']), 'Only supported lap data persisted');
        $route = $pdo->prepare('SELECT distancia_metros FROM rotas_atividade WHERE idregistro = :id');
        $route->execute([':id' => $saved['idregistro']]);
        AlphaTest::assert((float) $route->fetchColumn() > 0, 'Polyline persisted');
        $result = stridebr_integrations_sync_detailed($pdo, $user, 'strava');
        AlphaTest::same(1, $result['existing'], 'Second execution deduplicates');
        AlphaTest::same(0, $result['created'], 'No repeated activity');
        $callCount = count($calls);
        AlphaTest::same('not_due', stridebr_integrations_sync_detailed($pdo, $user, 'strava', 'periodic')['skipped'], 'Periodic respects last sync');
        AlphaTest::same($callCount, count($calls), 'Not due makes no HTTP requests');
        $bad = $fixture; $bad['id']++; $bad['start_date'] = 'invalid-private-date';
        $good = $fixture; $good['id'] += 2;
        $items = [$bad, $good];
        $result = stridebr_integrations_sync_detailed($pdo, $user, 'strava');
        AlphaTest::same(1, $result['failed'], 'One activity fails');
        AlphaTest::same(1, $result['created'], 'Other activity still imports');
        $connection = stridebr_integrations_get($pdo, $user, 'strava');
        AlphaTest::same('erro', $connection['status'], 'Partial failure is not marked success');
        $callCount = count($calls);
        AlphaTest::same('not_due', stridebr_integrations_sync_detailed($pdo, $user, 'strava')['skipped'], 'Manual also respects backoff');
        AlphaTest::same($callCount, count($calls), 'Backoff makes no requests');
        $logText = file_get_contents($log);
        AlphaTest::assert(str_contains($logText, 'normalization.datetime') && str_contains($logText, (string) $bad['id']), 'Log identifies exact stage and external ID');
        AlphaTest::assert(str_contains($logText, 'exception_type') && str_contains($logText, 'error_code'), 'Structured diagnostic');
        $normalized = stridebr_integrations_normalize_strava($fixture);
        $normalized['external_id'] = str_repeat('9', 191);
        $count = (int) $pdo->query("SELECT count(*) FROM registros_atividade WHERE idusuario = '$user'")->fetchColumn();
        try { stridebr_integrations_store_activity($pdo, $user, 'strava', $normalized); throw new LogicException('Expected failure'); }
        catch (StridebrIntegrationError $error) { AlphaTest::same('persistence.external_identity', $error->stage, 'Exact persistence stage'); stridebr_integrations_log_failure('strava', 'persistence', 'synthetic', $error); }
        AlphaTest::same($count, (int) $pdo->query("SELECT count(*) FROM registros_atividade WHERE idusuario = '$user'")->fetchColumn(), 'Failed identity rolls activity back');
        // Competing database session cannot enter the provider pipeline.
        $other = new PDO('pgsql:host=' . getenv('STRIDEBR_DB_HOST') . ';port=' . getenv('STRIDEBR_DB_PORT') . ';dbname=' . getenv('STRIDEBR_DB_NAME'), getenv('STRIDEBR_DB_USER'), getenv('STRIDEBR_DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $lock = $other->prepare('SELECT pg_advisory_lock(hashtextextended(:key, 0))');
        $lock->execute([':key' => 'integration:' . $user . ':strava']);
        AlphaTest::same('busy', stridebr_integrations_sync_detailed($pdo, $user, 'strava')['skipped'], 'Concurrent connection excluded');
        $other->query('SELECT pg_advisory_unlock_all()');
        $other = null;
        stridebr_integrations_sync_state($pdo, $user, 'strava', []);
        $pdo->exec("UPDATE integracoes_usuario SET status='conectado', ultima_sincronizacao_em=NULL WHERE idusuario='$user'");
        $items = [$bad]; $status = 429;
        $result = stridebr_integrations_sync_detailed($pdo, $user, 'strava');
        AlphaTest::same(1800, $result['retry_after'], 'Retry-After respected');
        AlphaTest::assert(!empty($result['deferred']), 'Rate-limited detail deferred');
        AlphaTest::assert(str_contains(file_get_contents($log), '"http_status":429') && str_contains(file_get_contents($log), '"stage":"detail"'), 'HTTP status and detail logged');
        $callsBeforeBackoffManual = count($calls);
        $manualBackoff = stridebr_integrations_sync_detailed($pdo, $user, 'strava', 'manual');
        AlphaTest::same('not_due', $manualBackoff['skipped'] ?? null, 'Manual sync cannot bypass provider backoff');
        AlphaTest::same($callsBeforeBackoffManual, count($calls), 'Manual backoff performs no provider HTTP request');
        stridebr_integrations_sync_state($pdo, $user, 'strava', []);
        $pdo->exec("UPDATE integracoes_usuario SET status='conectado' WHERE idusuario='$user'");
        $status = 401;
        $result = stridebr_integrations_sync_detailed($pdo, $user, 'strava');
        AlphaTest::assert($result['reauthorize'], '401 requires reauthorization');
        $callsBeforeReauthManual = count($calls);
        $manualReauth = stridebr_integrations_sync_detailed($pdo, $user, 'strava', 'manual');
        AlphaTest::same('not_due', $manualReauth['skipped'] ?? null, 'Manual sync cannot bypass reauthorization');
        AlphaTest::same($callsBeforeReauthManual, count($calls), 'Reauthorization gate performs no provider HTTP request');
        foreach (['synthetic-access-token', 'synthetic-refresh-token', 'synthetic-only-credential-for-tests-12345678', 'Authorization', 'invalid-private-date', 'Synthetic private text'] as $secret) AlphaTest::assert(!str_contains(file_get_contents($log), $secret), 'Log excludes private data: ' . $secret);
        $connection = stridebr_integrations_get($pdo, $user, 'strava');
        $connection['status'] = 'conectado'; $connection['metadados'] = []; $connection['ultima_sincronizacao_em'] = null;
        AlphaTest::assert(stridebr_integrations_eligible($connection), 'Connected due eligible');
        $connection['sincronizar_atividades'] = false;
        AlphaTest::assert(!stridebr_integrations_eligible($connection), 'OFF excluded');
        AlphaTest::assert(stridebr_integrations_eligible($connection, 'manual'), 'OFF retains manual fallback');
        $connection['sincronizar_atividades'] = true; $connection['provedor'] = 'coros';
        AlphaTest::assert(!stridebr_integrations_eligible($connection), 'COROS excluded from polling');
        AlphaTest::assert(stridebr_integrations_eligible($connection, 'manual'), 'COROS keeps manual');
        $pdo->exec("UPDATE integracoes_usuario SET sincronizar_atividades=FALSE, token_expira_em=NOW()-INTERVAL '1 hour', status='conectado' WHERE idusuario='$user'");
        stridebr_integrations_sync_state($pdo, $user, 'strava', []);
        $callCount = count($calls);
        stridebr_integrations_sync_detailed($pdo, $user, 'strava', 'periodic');
        AlphaTest::same($callCount, count($calls), 'OFF does not refresh expired token');
        $connection = stridebr_integrations_save_token($pdo, $user, 'strava', $token);
        AlphaTest::assert(!stridebr_db_bool($connection['sincronizar_atividades']), 'Reauthorization preserves opt-out');
        AlphaTest::same([], stridebr_integrations_due_connections($pdo, 'strava', $user), 'Runner SQL excludes OFF');
        $pdo->exec("UPDATE integracoes_usuario SET sincronizar_atividades=TRUE, ultima_sincronizacao_em=NULL WHERE idusuario='$user'");
        stridebr_integrations_sync_state($pdo, $user, 'strava', ['retry_at'=>time()+3600]);
        AlphaTest::same([], stridebr_integrations_due_connections($pdo, 'strava', $user), 'Runner SQL respects backoff');
        stridebr_integrations_sync_state($pdo, $user, 'strava', []);
        AlphaTest::same(1, count(stridebr_integrations_due_connections($pdo, 'strava', $user)), 'Runner selects due connection');
        AlphaTest::same([], stridebr_integrations_due_connections($pdo, 'coros', $user), 'Runner refuses COROS filter');
        $items = [$fixture]; $status = 200;
        $refreshBefore = count(array_filter($calls, static fn($url) => str_contains($url, '/oauth/token')));
        $pdo->exec("UPDATE integracoes_usuario SET token_expira_em=NOW()-INTERVAL '1 hour' WHERE idusuario='$user'");
        $result = stridebr_integrations_sync_detailed($pdo, $user, 'strava', 'periodic');
        AlphaTest::same(1, $result['existing'], 'Eligible expired connection still deduplicates');
        AlphaTest::same($refreshBefore + 1, count(array_filter($calls, static fn($url) => str_contains($url, '/oauth/token'))), 'Only eligible expired token refreshed once');
        $empty = stridebr_integrations_feedback(['created'=>0,'existing'=>0,'failed'=>0], 'Strava');
        AlphaTest::same('success', $empty[0], 'No new activities is successful');


        foreach (['pt-BR', 'en'] as $locale) {
            stridebr_set_locale($locale, false);
            [$type, $message] = stridebr_integrations_feedback(['created'=>0,'existing'=>0,'failed'=>1], 'Strava');
            AlphaTest::same('info', $type, 'Partial failure feedback');
            AlphaTest::assert(str_contains($message, 'Strava') && !str_contains($message, '0 '), 'Human feedback without dry counters');
        }
    } finally {
        unset($GLOBALS['stridebr_integrations_http_mock']);
        ini_set('error_log', $previousLog);
        unlink($log);
        foreach ($env as $key => $value) putenv($value === false ? $key : $key . '=' . $value);
        stridebr_set_locale('pt-BR', false);
    }
};
