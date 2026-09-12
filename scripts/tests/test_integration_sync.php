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
    $backfillItems = [];
    $status = 200;
    $GLOBALS['stridebr_integrations_http_mock'] = static function ($method, $url, $options) use (&$calls, &$items, &$backfillItems, &$status): array {
        $calls[] = $url;
        if (str_contains($url, '/oauth/token')) return ['json' => ['access_token' => 'synthetic-access-token', 'refresh_token' => 'synthetic-refresh-token', 'expires_at' => time() + 7200, 'athlete' => ['id' => 42, 'firstname' => 'Atleta', 'lastname' => 'Teste']]];
        if (str_contains($url, '/athlete/activities')) {
            if (!str_contains($url, 'before=')) return ['json' => $items];
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $before = (int) ($query['before'] ?? PHP_INT_MAX);
            $eligible = array_values(array_filter($backfillItems, static fn(array $item): bool => (strtotime((string) ($item['start_date'] ?? '')) ?: PHP_INT_MAX) < $before));
            usort($eligible, static fn(array $a, array $b): int => (strtotime((string) $b['start_date']) ?: 0) <=> (strtotime((string) $a['start_date']) ?: 0));
            return ['json' => array_slice($eligible, 0, 10)];
        }
        if ($status !== 200) return ['status' => $status, 'headers' => ['retry-after' => ['1800']], 'json' => ['message' => 'synthetic-access-token synthetic-refresh-token synthetic-only-credential-for-tests-12345678 Authorization: SECRET']];
        foreach (array_merge($items, $backfillItems) as $item) if (str_ends_with($url, '/' . $item['id'])) return ['json' => $item];
        throw new RuntimeException('Unexpected fixture request');
    };
    try {
        $user = alphaTestUser($pdo, 'integration_sync');
        $token = stridebr_integrations_exchange('strava', 'synthetic-code', []);
        $connection = stridebr_integrations_save_token($pdo, $user, 'strava', $token);
        AlphaTest::same('conectado', $connection['status'], 'OAuth connected');
        AlphaTest::assert(stridebr_db_bool($connection['sincronizar_atividades']), 'Default activity sync ON');
        $refreshed = stridebr_integrations_save_token($pdo, $user, 'strava', ['access_token' => 'refreshed-access-token', 'refresh_token' => 'refreshed-refresh-token', 'expires_at' => time() + 7200]);
        AlphaTest::same('object', $pdo->query("SELECT jsonb_typeof(metadados) FROM integracoes_usuario WHERE idusuario = '$user' AND provedor = 'strava'")->fetchColumn(), 'Empty refresh metadata remains an object');
        $refreshMetadata = stridebr_integrations_metadata($refreshed);
        AlphaTest::same('42', (string) ($refreshMetadata['athlete']['id'] ?? ''), 'Refresh without metadata preserves existing athlete metadata');

        $runtimeLegacyUser = alphaTestUser($pdo, 'integration_runtime_legacy');
        stridebr_integrations_save_token($pdo, $runtimeLegacyUser, 'strava', $token);
        $pdo->exec('ALTER TABLE integracoes_usuario DROP CONSTRAINT ck_integracoes_usuario_metadados_object');
        $runtimeLegacy = $pdo->prepare("UPDATE integracoes_usuario SET metadados = CAST(:metadata AS jsonb) WHERE idusuario = :user AND provedor = 'strava'");
        $runtimeLegacy->execute([':metadata' => '[{"athlete":{"id":91}}]', ':user' => $runtimeLegacyUser]);
        stridebr_integrations_sync_state($pdo, $runtimeLegacyUser, 'strava', ['retry_at' => 123]);
        $runtimeState = $pdo->prepare("SELECT metadados FROM integracoes_usuario WHERE idusuario = :user AND provedor = 'strava'");
        $runtimeState->execute([':user' => $runtimeLegacyUser]);
        $runtimeMetadata = json_decode((string) $runtimeState->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);
        AlphaTest::same(123, $runtimeMetadata['sync']['retry_at'] ?? null, 'sync_state repairs a legacy array root before writing sync state');
        AlphaTest::same('91', (string) ($runtimeMetadata['_legacy_array'][0]['athlete']['id'] ?? ''), 'sync_state retains a legacy array value');
        $runtimeLegacy->execute([':metadata' => '[{"athlete":{"id":93}}]', ':user' => $runtimeLegacyUser]);
        stridebr_integrations_strava_backfill_write($pdo, $runtimeLegacyUser, ['status' => 'pending', 'before' => 123456789]);
        $runtimeState->execute([':user' => $runtimeLegacyUser]);
        $runtimeBackfillMetadata = json_decode((string) $runtimeState->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);
        AlphaTest::same(123456789, (int) ($runtimeBackfillMetadata['backfill']['before'] ?? 0), 'backfill writer repairs a legacy array root before writing checkpoint');
        AlphaTest::same('93', (string) ($runtimeBackfillMetadata['_legacy_array'][0]['athlete']['id'] ?? ''), 'backfill writer retains legacy array metadata');

        $migrationLegacyUser = alphaTestUser($pdo, 'integration_migration_legacy');
        stridebr_integrations_save_token($pdo, $migrationLegacyUser, 'strava', $token);
        $migrationLegacy = $pdo->prepare("UPDATE integracoes_usuario SET metadados = CAST(:metadata AS jsonb) WHERE idusuario = :user AND provedor = 'strava'");
        $migrationLegacy->execute([':metadata' => '[{"athlete":{"id":92},"oauth":{"issuer":"https://issuer.example"}},"unstructured"]', ':user' => $migrationLegacyUser]);
        $migrationSql = file_get_contents(dirname(__DIR__, 2) . '/src/database/migrations/20260910_integrations_metadata_object.sql');
        $pdo->exec($migrationSql);
        $pdo->exec($migrationSql);
        $migrationState = $pdo->prepare("SELECT metadados FROM integracoes_usuario WHERE idusuario = :user AND provedor = 'strava'");
        $migrationState->execute([':user' => $migrationLegacyUser]);
        $migrationMetadata = json_decode((string) $migrationState->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);
        AlphaTest::same('92', (string) ($migrationMetadata['athlete']['id'] ?? ''), 'Migration recovers object metadata from a legacy array');
        AlphaTest::same('https://issuer.example', (string) ($migrationMetadata['oauth']['issuer'] ?? ''), 'Migration preserves OAuth metadata from a legacy array');
        AlphaTest::same('unstructured', (string) ($migrationMetadata['_legacy_array'][1] ?? ''), 'Migration preserves non-object legacy metadata');
        AlphaTest::throws(fn() => $migrationLegacy->execute([':metadata' => '[]', ':user' => $migrationLegacyUser]), 'Metadata object constraint rejects a new array root');
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
        stridebr_integrations_provider_cooldown_set($pdo, 'strava', time() - 1);
        $pdo->exec("UPDATE integracoes_usuario SET status='conectado' WHERE idusuario='$user'");
        $status = 401;
        $result = stridebr_integrations_sync_detailed($pdo, $user, 'strava');
        AlphaTest::assert(($result['reauthorize'] ?? false) === true, '401 requires reauthorization');
        $reauthConnection = stridebr_integrations_get($pdo, $user, 'strava');
        $reauthState = stridebr_integrations_metadata($reauthConnection ?? [])['sync'] ?? [];
        AlphaTest::assert(($reauthState['reauthorize'] ?? false) === true, 'Reauthorization requirement persists in sync state for UI and future runners');
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
        AlphaTest::assert(!stridebr_integrations_eligible($connection, 'manual'), 'OFF blocks manual activity sync and preserves checkpoint');
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
        $backfillUser = alphaTestUser($pdo, 'integration_backfill');
        $backfillToken = $token;
        $backfillToken['athlete'] = ['id' => 77, 'firstname' => 'Historico'];
        stridebr_integrations_save_token($pdo, $backfillUser, 'strava', $backfillToken);
        $items = [];
        $backfillItems = [];
        $baseTs = strtotime('2025-12-31 12:00:00 UTC');
        for ($i = 0; $i < 12; $i++) {
            $history = $fixture;
            $history['id'] = (int) $fixture['id'] + 1000 + $i;
            $history['start_date'] = gmdate(DATE_ATOM, $baseTs - ($i * 86400));
            $history['name'] = 'History ' . $i;
            $backfillItems[] = $history;
        }
        $backfillConnection = stridebr_integrations_get($pdo, $backfillUser, 'strava');
        $initialBackfill = stridebr_integrations_strava_backfill_state($pdo, $backfillUser, $backfillConnection, true);
        $initialCursor = (int) $initialBackfill['before'];
        $firstBackfill = stridebr_integrations_strava_backfill_step($pdo, $backfillUser, $backfillConnection);
        AlphaTest::same(3, (int) $firstBackfill['created'], 'Backfill limits detail work per step');
        $afterFirst = stridebr_integrations_strava_backfill_state($pdo, $backfillUser, stridebr_integrations_get($pdo, $backfillUser, 'strava'), false);
        AlphaTest::same($initialCursor, (int) $afterFirst['before'], 'Partial batch does not advance temporal checkpoint');
        AlphaTest::assert(!empty($firstBackfill['backfill_deferred']), 'Partial batch is resumable');
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $state = stridebr_integrations_strava_backfill_state($pdo, $backfillUser, stridebr_integrations_get($pdo, $backfillUser, 'strava'), false);
            if (($state['status'] ?? '') === 'completed') break;
            $state['retry_at'] = 0;
            stridebr_integrations_strava_backfill_write($pdo, $backfillUser, $state);
            stridebr_integrations_strava_backfill_step($pdo, $backfillUser, stridebr_integrations_get($pdo, $backfillUser, 'strava'));
        }
        $finalBackfill = stridebr_integrations_strava_backfill_state($pdo, $backfillUser, stridebr_integrations_get($pdo, $backfillUser, 'strava'), false);
        AlphaTest::same('completed', $finalBackfill['status'], 'Backfill eventually completes');
        AlphaTest::same(12, (int) $pdo->query("SELECT count(*) FROM registros_atividade WHERE idusuario='{$backfillUser}' AND origem_provedor='strava'")->fetchColumn(), 'Backfill imports full synthetic history exactly once');
        $manualCheckpoint = (int) ($finalBackfill['before'] ?? 0);
        $manualResult = stridebr_integrations_sync_detailed($pdo, $backfillUser, 'strava', 'manual');
        $manualState = stridebr_integrations_strava_backfill_state($pdo, $backfillUser, stridebr_integrations_get($pdo, $backfillUser, 'strava'), false);
        AlphaTest::same($manualCheckpoint, (int) ($manualState['before'] ?? 0), 'Manual sync does not reset completed checkpoint');
        AlphaTest::assert(empty($manualResult['backfill_pending']), 'Completed history stays completed');

        $disabledState = $finalBackfill;
        $disabledState['status'] = 'pending';
        $disabledState['before'] = 1234567890;
        $disabledState['retry_at'] = 0;
        stridebr_integrations_strava_backfill_write($pdo, $backfillUser, $disabledState);
        $pdo->exec("UPDATE integracoes_usuario SET sincronizar_atividades=FALSE WHERE idusuario='{$backfillUser}' AND provedor='strava'");
        $disabledResult = stridebr_integrations_strava_backfill_step($pdo, $backfillUser, stridebr_integrations_get($pdo, $backfillUser, 'strava'));
        AlphaTest::same('disabled', $disabledResult['backfill_skipped'] ?? null, 'Disabled activity sync pauses backfill');
        $disabledAfter = stridebr_integrations_strava_backfill_state($pdo, $backfillUser, stridebr_integrations_get($pdo, $backfillUser, 'strava'), false);
        AlphaTest::same(1234567890, (int) $disabledAfter['before'], 'Disabled sync preserves backfill checkpoint');
        $pdo->exec("UPDATE integracoes_usuario SET sincronizar_atividades=TRUE, ultima_sincronizacao_em=NOW() WHERE idusuario='{$backfillUser}' AND provedor='strava'");

        $oldAccount = alphaTestUser($pdo, 'integration_old_account');
        $oldToken = $token; $oldToken['athlete'] = ['id' => 78];
        stridebr_integrations_save_token($pdo, $oldAccount, 'strava', $oldToken);
        $pdo->exec("UPDATE integracoes_usuario SET ultima_sincronizacao_em=NOW() WHERE idusuario='{$oldAccount}' AND provedor='strava'");
        AlphaTest::same(1, count(stridebr_integrations_due_connections($pdo, 'strava', $oldAccount)), 'Existing connection without backfill metadata is automatically due');

        $pdo->exec("UPDATE integracoes_usuario SET sincronizar_atividades=FALSE WHERE idusuario LIKE 'alpha_test_%' AND provedor='strava'");
        $fairCandidates = [];
        foreach ([
            ['integration_fair_never', 81, null, 0],
            ['integration_fair_old', 82, 100, 0],
            ['integration_fair_recent', 83, 200, 0],
            ['integration_fair_cooldown', 84, null, time() + 3600],
        ] as [$suffix, $athlete, $lastAttempt, $retryAt]) {
            $fairUser = alphaTestUser($pdo, $suffix);
            $fairCandidates[$suffix] = $fairUser;
            $fairToken = $token; $fairToken['athlete'] = ['id' => $athlete];
            stridebr_integrations_save_token($pdo, $fairUser, 'strava', $fairToken);
            $pdo->exec("UPDATE integracoes_usuario SET ultima_sincronizacao_em=NOW() WHERE idusuario='{$fairUser}' AND provedor='strava'");
            $fairState = stridebr_integrations_strava_backfill_initial_state($pdo, $fairUser);
            $fairState['last_attempt_at'] = $lastAttempt;
            $fairState['retry_at'] = $retryAt;
            stridebr_integrations_strava_backfill_write($pdo, $fairUser, $fairState);
        }

        $fairDue = stridebr_integrations_due_connections($pdo, 'strava', '', 4);
        $fairIds = array_map(static fn(array $row): string => (string) ($row['idusuario'] ?? ''), $fairDue);
        AlphaTest::same($fairCandidates['integration_fair_never'], $fairIds[0] ?? '', 'Runner fairness prioritizes a never-attempted eligible backfill');
        AlphaTest::same($fairCandidates['integration_fair_old'], $fairIds[1] ?? '', 'Runner fairness then prioritizes the least recently attempted backfill');
        AlphaTest::same($fairCandidates['integration_fair_recent'], $fairIds[2] ?? '', 'Runner fairness leaves the most recently attempted eligible backfill after older candidates');
        AlphaTest::assert(!in_array($fairCandidates['integration_fair_cooldown'], $fairIds, true), 'Runner fairness excludes a backfill candidate still in cooldown');

        $neverState = stridebr_integrations_strava_backfill_state($pdo, $fairCandidates['integration_fair_never'], stridebr_integrations_get($pdo, $fairCandidates['integration_fair_never'], 'strava'), false);
        $neverState['last_attempt_at'] = 300;
        stridebr_integrations_strava_backfill_write($pdo, $fairCandidates['integration_fair_never'], $neverState);
        $oldState = stridebr_integrations_strava_backfill_state($pdo, $fairCandidates['integration_fair_old'], stridebr_integrations_get($pdo, $fairCandidates['integration_fair_old'], 'strava'), false);
        $oldState['last_attempt_at'] = 300;
        stridebr_integrations_strava_backfill_write($pdo, $fairCandidates['integration_fair_old'], $oldState);
        $recentState = stridebr_integrations_strava_backfill_state($pdo, $fairCandidates['integration_fair_recent'], stridebr_integrations_get($pdo, $fairCandidates['integration_fair_recent'], 'strava'), false);
        $recentState['last_attempt_at'] = 400;
        stridebr_integrations_strava_backfill_write($pdo, $fairCandidates['integration_fair_recent'], $recentState);

        $tieDue = stridebr_integrations_due_connections($pdo, 'strava', '', 2);
        $tieIds = array_map(static fn(array $row): string => (string) ($row['idusuario'] ?? ''), $tieDue);
        sort($tieIds);
        $expectedTie = [$fairCandidates['integration_fair_never'], $fairCandidates['integration_fair_old']];
        sort($expectedTie);
        AlphaTest::same($expectedTie, $tieIds, 'Equal fairness timestamps may use either order but both tied candidates precede newer attempts');

        $firstTieUser = (string) (($tieDue[0]['idusuario'] ?? ''));
        $firstTieConnection = stridebr_integrations_get($pdo, $firstTieUser, 'strava');
        $firstTieState = stridebr_integrations_strava_backfill_state($pdo, $firstTieUser, $firstTieConnection ?? [], false);
        $firstTieState['last_attempt_at'] = 500;
        stridebr_integrations_strava_backfill_write($pdo, $firstTieUser, $firstTieState);
        $nextDue = stridebr_integrations_due_connections($pdo, 'strava', '', 1);
        $otherTieUser = $firstTieUser === $fairCandidates['integration_fair_never'] ? $fairCandidates['integration_fair_old'] : $fairCandidates['integration_fair_never'];
        AlphaTest::same($otherTieUser, (string) ($nextDue[0]['idusuario'] ?? ''), 'Updating the selected tie candidate lets the other tied candidate run next without starvation');

        $rateUser = alphaTestUser($pdo, 'integration_backfill_rate');
        $rateToken = $token; $rateToken['athlete'] = ['id' => 79];
        stridebr_integrations_save_token($pdo, $rateUser, 'strava', $rateToken);
        $rateHistory = $fixture; $rateHistory['id'] = (int) $fixture['id'] + 5000; $rateHistory['start_date'] = gmdate(DATE_ATOM, strtotime('2024-01-01 UTC'));
        $backfillItems = [$rateHistory]; $status = 429;
        $rateResult = stridebr_integrations_strava_backfill_step($pdo, $rateUser, stridebr_integrations_get($pdo, $rateUser, 'strava'));
        AlphaTest::assert(!empty($rateResult['backfill_paused']) && !empty($rateResult['rate_limited']), 'Backfill pauses on Strava rate limit');
        $rateState = stridebr_integrations_strava_backfill_state($pdo, $rateUser, stridebr_integrations_get($pdo, $rateUser, 'strava'), false);
        AlphaTest::same('paused', $rateState['status'], 'Rate-limited backfill state is persisted');
        AlphaTest::assert((int) $rateState['retry_at'] > time(), 'Rate-limited backfill has a future retry');
        $status = 200; $backfillItems = [];

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
