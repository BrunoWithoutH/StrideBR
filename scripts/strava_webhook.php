<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/function/integrations.php';

$command = $argv[1] ?? 'status';
if (!in_array($command, ['status', 'create', 'delete'], true)) { fwrite(STDERR, "Uso: php scripts/strava_webhook.php [status|create|delete]\n"); exit(2); }
$provider = stridebr_integrations_provider('strava'); $hook = stridebr_strava_webhook_config();
$clientId = trim((string) ($provider['client_id'] ?? '')); $secret = trim((string) ($provider['client_secret'] ?? ''));
if ($clientId === '' || $secret === '') { fwrite(STDERR, "Credenciais Strava não configuradas.\n"); exit(2); }
$base = 'https://www.strava.com/api/v3/push_subscriptions';
$safePrint = static function (array $rows): void { foreach ($rows as $row) if (is_array($row)) echo 'subscription_id=' . (string) ($row['id'] ?? '?') . ' callback=' . (string) ($row['callback_url'] ?? 'configured') . PHP_EOL; };
try {
    if ($command === 'status' || $command === 'create') {
        $current = stridebr_integrations_http('GET', $base . '?' . http_build_query(['client_id'=>$clientId, 'client_secret'=>$secret]), ['headers'=>['Accept: application/json']]);
        $rows = is_array($current['json']) && array_is_list($current['json']) ? $current['json'] : (is_array($current['json']) ? [$current['json']] : []);
        if ($command === 'status') { $safePrint($rows); exit(0); }
        if ($rows !== []) { fwrite(STDERR, "Já existe uma subscription; não foi criada outra.\n"); $safePrint($rows); exit(1); }
        if ($hook['verify_token'] === '') { fwrite(STDERR, "STRAVA_WEBHOOK_VERIFY_TOKEN não configurado.\n"); exit(2); }
        $callback = stridebr_app_url() . '/webhooks/strava.php';
        $created = stridebr_integrations_http('POST', $base, ['headers'=>['Accept: application/json'], 'body'=>['client_id'=>$clientId, 'client_secret'=>$secret, 'callback_url'=>$callback, 'verify_token'=>$hook['verify_token']]]);
        $id = is_array($created['json']) ? ($created['json']['id'] ?? null) : null;
        if (!is_scalar($id)) throw new RuntimeException('Resposta de subscription inválida.');
        echo 'Subscription criada. Configure STRAVA_WEBHOOK_SUBSCRIPTION_ID=' . $id . " no ambiente.\n"; exit(0);
    }
    $id = $hook['subscription_id'];
    if ($id === '' || !ctype_digit($id)) { fwrite(STDERR, "Defina STRAVA_WEBHOOK_SUBSCRIPTION_ID antes de excluir.\n"); exit(2); }
    stridebr_integrations_http('DELETE', $base . '/' . rawurlencode($id) . '?' . http_build_query(['client_id'=>$clientId, 'client_secret'=>$secret]), ['headers'=>['Accept: application/json']]);
    echo "Subscription removida.\n";
} catch (Throwable) { fwrite(STDERR, "Operação Strava webhook falhou.\n"); exit(1); }
