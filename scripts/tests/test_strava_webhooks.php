<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
putenv('STRIDEBR_APP_ENV=development');
putenv('STRAVA_WEBHOOK_VERIFY_TOKEN=test-verify-token');
putenv('STRAVA_WEBHOOK_SIGNING_SECRET=test-signing-secret');
require_once $root . '/src/includes/app.php';
require_once $root . '/src/function/integrations.php';
$checks = 0;
$assert = static function (bool $value, string $message) use (&$checks): void { $checks++; if (!$value) throw new RuntimeException($message); };
$runChallenge = static function (array $query): array {
    $endpoint = dirname(__DIR__, 2) . '/public/webhooks/strava.php';
    $code = 'putenv("STRIDEBR_APP_ENV=development"); putenv("STRAVA_WEBHOOK_VERIFY_TOKEN=test-verify-token"); '
        . '$_SERVER["REQUEST_METHOD"]="GET"; $_GET=' . var_export($query, true) . '; '
        . 'register_shutdown_function(static function (): void { fwrite(STDERR, "STATUS=" . http_response_code() . ";SESSION=" . session_status()); }); '
        . 'require ' . var_export($endpoint, true) . ';';
    $pipes = [];
    $process = proc_open([PHP_BINARY, '-d', 'session.auto_start=0', '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Não foi possível executar o endpoint isolado.');
    $body = stream_get_contents($pipes[1]); $meta = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
    return [(string) $body, (string) $meta];
};
[$challengeBody, $challengeMeta] = $runChallenge(['hub_mode'=>'subscribe', 'hub_verify_token'=>'test-verify-token', 'hub_challenge'=>'stridebr-test']);
$assert($challengeBody === '{"hub.challenge":"stridebr-test"}' && str_contains($challengeMeta, 'STATUS=200;SESSION=1'), 'challenge HTTP com chaves normalizadas pelo PHP deve responder JSON 200 sem sessão');
[, $invalidChallengeMeta] = $runChallenge(['hub_mode'=>'subscribe', 'hub_verify_token'=>'wrong', 'hub_challenge'=>'stridebr-test']);
$assert(str_contains($invalidChallengeMeta, 'STATUS=403;SESSION=1'), 'verify token inválido deve continuar rejeitado sem iniciar sessão');
$raw = '{"aspect_type":"create","event_time":1700000000,"object_id":123,"object_type":"activity","owner_id":456,"subscription_id":789}';
$timestamp = 1700000001;
$signature = hash_hmac('sha256', $timestamp . '.' . $raw, 'test-signing-secret');
$assert(stridebr_strava_webhook_verify_signature($raw, "t={$timestamp},v1={$signature}", $timestamp + 20), 'assinatura Strava válida rejeitada');
$assert(!stridebr_strava_webhook_verify_signature($raw, "t={$timestamp},v1=" . str_repeat('0', 64), $timestamp), 'assinatura inválida aceita');
$assert(!stridebr_strava_webhook_verify_signature($raw, "t={$timestamp},v1={$signature}", $timestamp + 301), 'replay antigo aceito');
$event = stridebr_strava_webhook_event(json_decode($raw, true, 512, JSON_THROW_ON_ERROR));
$assert(is_array($event) && strlen((string) $event['fingerprint']) === 64, 'evento create válido/fingerprint falhou');
$assert($event['fingerprint'] === stridebr_strava_webhook_event(json_decode($raw, true, 512, JSON_THROW_ON_ERROR))['fingerprint'], 'fingerprint precisa ser determinístico');
$update = stridebr_strava_webhook_event(['aspect_type'=>'update','event_time'=>1700000002,'object_id'=>123,'object_type'=>'activity','owner_id'=>456,'subscription_id'=>789,'updates'=>['title'=>'Novo']]);
$assert(is_array($update) && $update['fingerprint'] !== $event['fingerprint'], 'update distinto não pode deduplicar create');
$assert(stridebr_strava_webhook_event(['aspect_type'=>'update','event_time'=>1,'object_id'=>1,'object_type'=>'athlete','owner_id'=>1,'subscription_id'=>1,'updates'=>['authorized'=>'false']]) !== null, 'deauth oficial rejeitado');
$assert(stridebr_strava_webhook_event(['aspect_type'=>'update','event_time'=>1,'object_id'=>1,'object_type'=>'activity','owner_id'=>1,'subscription_id'=>1,'updates'=>['unexpected'=>'x']]) === null, 'campo não documentado aceito');
$endpoint = (string) file_get_contents($root . '/public/webhooks/strava.php');
$worker = (string) file_get_contents($root . '/scripts/process_strava_webhooks.php');
$integrations = (string) file_get_contents($root . '/src/function/integrations.php');
$migration = (string) file_get_contents($root . '/src/database/migrations/20260909_strava_webhooks.sql');
$assert(str_contains($endpoint, "file_get_contents('php://input'") && str_contains($endpoint, 'HTTP_X_STRAVA_SIGNATURE') && str_contains($endpoint, "\$_GET['hub_mode']") && !str_contains($endpoint, "includes/app.php"), 'endpoint precisa usar raw body, assinatura, chaves PHP normalizadas e nenhum bootstrap de sessão');
$assert(str_contains($worker, 'FOR UPDATE SKIP LOCKED') && str_contains($worker, 'attempts=attempts+1'), 'worker precisa reivindicar concorrentemente');
$assert(str_contains($integrations, 'CAST(:title_update AS boolean)') && str_contains($integrations, "':title_update'=>\$titleUpdate ? 'true' : 'false'"), 'update de título precisa bind boolean PostgreSQL seguro');
$assert(str_contains($integrations, 'CAST(:private AS boolean)') && str_contains($integrations, "':private'=>\$setPrivacy ? 'true' : 'false'"), 'update de privacidade precisa bind boolean PostgreSQL seguro');
$assert(str_contains($integrations, 'CAST(:terminal AS boolean)') && str_contains($integrations, "':terminal'=>\$terminal ? 'true' : 'false'"), 'falha do worker precisa bind terminal PostgreSQL seguro');
$assert(str_contains($worker, 'stridebr_strava_webhook_record_failure($pdo, $event, $error)') && !str_contains($worker, "':terminal'=>\$terminal"), 'worker precisa usar transição de falha segura sem binding boolean cru');
$assert(str_contains($migration, 'uq_integracao_webhook_eventos_fingerprint') && str_contains($migration, 'ix_integracao_webhook_eventos_due'), 'fila precisa de idempotência e índice de consumo');
printf("✓ Strava webhooks: %d assertions\n", $checks);
