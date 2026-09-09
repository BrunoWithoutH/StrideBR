<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
putenv('STRIDEBR_APP_ENV=development');
putenv('STRIDEBR_APP_URL=https://stridebr.test');
putenv('STRIDEBR_INTEGRATIONS_SECRET=fixture-integration-secret-that-is-long-enough-20260908');
putenv('STRAVA_CLIENT_ID=fixture-strava-id');
putenv('STRAVA_CLIENT_SECRET=fixture-strava-secret');
putenv('POLAR_CLIENT_ID=fixture-polar-id');
putenv('POLAR_CLIENT_SECRET=fixture-polar-secret');
putenv('POLAR_OAUTH_AUTHORIZE_URL=https://auth.polar.com/oauth/authorize');
putenv('POLAR_OAUTH_TOKEN_URL=https://auth.polar.com/oauth/token');
putenv('POLAR_OAUTH_SCOPE=training_sessions:read activity:read profile:read');
putenv('GOOGLE_HEALTH_CLIENT_ID=fixture-google-health-id');
putenv('GOOGLE_HEALTH_CLIENT_SECRET=fixture-google-health-secret');
putenv('COROS_MCP_URL=https://mcp.coros.test/mcp');
putenv('SUUNTO_CLIENT_ID=fixture-suunto-id');
putenv('SUUNTO_CLIENT_SECRET=fixture-suunto-secret');
putenv('SUUNTO_SUBSCRIPTION_KEY=');

require_once $root . '/src/includes/app.php';
require_once $root . '/src/function/integrations.php';

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($message);
};

$registry = stridebr_integrations_registry();
$assert(isset($registry['strava'], $registry['polar'], $registry['google_health'], $registry['coros'], $registry['suunto']), 'providers conectáveis ausentes');
$assert(!isset($registry['fitbit']) && !isset($registry['whoop']), 'Fitbit legado/WHOOP não podem estar ativos');
$assert(($registry['garmin']['implementation_ready'] ?? null) === false, 'Garmin deve continuar bloqueado');
$assert(($registry['halo']['oauth'] ?? true) === false && ($registry['halo']['availability'] ?? '') === 'waiting', 'HALO não pode iniciar OAuth');
$assert(!stridebr_integrations_configured(stridebr_integrations_provider('suunto')), 'Suunto sem subscription key deve ficar indisponível');
$assert(!stridebr_integrations_configured(stridebr_integrations_provider('garmin')), 'Garmin não pode ficar configurado');
$assert(!stridebr_integrations_configured(stridebr_integrations_provider('halo')), 'HALO não pode ficar configurado');

$plain = 'token-fixture-not-real';
$encrypted = stridebr_integrations_encrypt($plain);
$assert(is_string($encrypted) && $encrypted !== $plain && stridebr_integrations_decrypt($encrypted) === $plain, 'AES-256-GCM roundtrip falhou');
$assert(stridebr_integrations_scopes_cover('a b c', 'a c') && !stridebr_integrations_scopes_cover('a b', 'a c'), 'comparação de scopes falhou');
$assert(stridebr_integrations_token_expiring(['token_expira_em' => date('c', time() + 30)]) && !stridebr_integrations_token_expiring(['token_expira_em' => date('c', time() + 3600)]), 'detecção de token expirando falhou');
$assert(stridebr_integrations_strava_rate_low(['headers' => ['x-ratelimit-usage' => ['198,1000'], 'x-ratelimit-limit' => ['200,2000']]]) && stridebr_integrations_strava_rate_low(['headers' => ['x-readratelimit-usage' => ['96,500'], 'x-readratelimit-limit' => ['100,1000']]]), 'Strava precisa respeitar headers de rate limit');
$corosSummary = stridebr_integrations_coros_summary_activity(['label_id' => 'coros-1', 'sport_type' => 'RUNNING', 'raw' => ['startTime' => '2026-09-08T10:00:00Z']], ['durationSeconds' => 1800, 'distanceMeters' => 5000, 'averageHeartRate' => 150]);
$assert(is_array($corosSummary) && ($corosSummary['distance_m'] ?? null) === 5000.0 && ($corosSummary['avg_hr'] ?? null) === 150, 'fallback de detalhe COROS precisa preservar resumo utilizável');

$_SESSION['StrideBRIntegrationOAuth'] = [];
$googleUrl = stridebr_integrations_start('google_health', '/user/edit-profile.php#conexoes');
$googleQuery = [];
parse_str((string) parse_url($googleUrl, PHP_URL_QUERY), $googleQuery);
$assert(($googleQuery['access_type'] ?? '') === 'offline', 'Google Health precisa access_type=offline');
$assert(!isset($googleQuery['prompt']), 'Google Health não deve forçar consent sem necessidade');
$assert(($googleQuery['scope'] ?? '') === $registry['google_health']['scope'], 'Google Health scopes divergentes');
$googleConsentUrl = stridebr_integrations_start('google_health', '/user/edit-profile.php#conexoes', true);
parse_str((string) parse_url($googleConsentUrl, PHP_URL_QUERY), $googleConsentQuery);
$assert(($googleConsentQuery['prompt'] ?? '') === 'consent', 'Google Health reautorização precisa prompt=consent');

$polarUrl = stridebr_integrations_start('polar');
$assert(str_starts_with($polarUrl, 'https://auth.polar.com/oauth/authorize?'), 'Polar precisa endpoint OAuth atual');
parse_str((string) parse_url($polarUrl, PHP_URL_QUERY), $polarQuery);
$assert(($polarQuery['scope'] ?? '') === 'training_sessions:read activity:read profile:read', 'Polar v4 scopes divergentes');

try {
    stridebr_integrations_provider('provider_inexistente');
    $assert(false, 'provider desconhecido aceito');
} catch (InvalidArgumentException) {
    $assert(true, 'provider desconhecido bloqueado');
}

$calls = [];
$GLOBALS['stridebr_integrations_http_mock'] = static function (string $method, string $url, array $options) use (&$calls): array {
    $calls[] = [$method, $url, $options];
    if ($url === 'https://mcp.coros.test/mcp' && $method === 'POST') {
        return ['status' => 401, 'body' => '', 'json' => null, 'headers' => ['www-authenticate' => ['Bearer resource_metadata="https://mcp.coros.test/.well-known/oauth-protected-resource"']]];
    }
    if ($url === 'https://mcp.coros.test/.well-known/oauth-protected-resource') {
        return ['status' => 200, 'body' => '{}', 'json' => ['resource' => 'https://mcp.coros.test/mcp', 'authorization_servers' => ['https://auth.coros.test'], 'scopes_supported' => ['activity:read']], 'headers' => []];
    }
    if ($url === 'https://auth.coros.test/.well-known/oauth-authorization-server') {
        $json = ['issuer' => 'https://auth.coros.test', 'authorization_endpoint' => 'https://auth.coros.test/authorize', 'token_endpoint' => 'https://auth.coros.test/token', 'code_challenge_methods_supported' => ['S256'], 'client_id_metadata_document_supported' => true];
        return ['status' => 200, 'body' => json_encode($json), 'json' => $json, 'headers' => []];
    }
    if ($url === 'https://auth.coros.test/token') {
        $json = ['access_token' => 'coros-access-fixture', 'refresh_token' => 'coros-refresh-fixture', 'expires_in' => 3600, 'scope' => 'activity:read'];
        return ['status' => 200, 'body' => json_encode($json), 'json' => $json, 'headers' => []];
    }
    return ['status' => 500, 'body' => '{"error":"fixture_unexpected"}', 'json' => ['error' => 'fixture_unexpected'], 'headers' => []];
};

$corosUrl = stridebr_integrations_start('coros', '/user/edit-profile.php#conexoes');
$corosQuery = [];
parse_str((string) parse_url($corosUrl, PHP_URL_QUERY), $corosQuery);
$assert(($corosQuery['code_challenge_method'] ?? '') === 'S256' && !empty($corosQuery['code_challenge']), 'COROS precisa PKCE S256');
$assert(($corosQuery['resource'] ?? '') === 'https://mcp.coros.test/mcp', 'COROS precisa resource indicator do MCP');
$assert(str_starts_with((string) ($corosQuery['client_id'] ?? ''), 'https://stridebr.test/auth/mcp-client-metadata.php'), 'COROS não deve depender de client secret inventado');
$states = array_keys($_SESSION['StrideBRIntegrationOAuth']);
$pending = $_SESSION['StrideBRIntegrationOAuth'][end($states)];
$corosToken = stridebr_integrations_exchange('coros', 'fixture-code', $pending);
$assert(($corosToken['access_token'] ?? '') === 'coros-access-fixture' && isset($corosToken['_stridebr_meta']), 'COROS token exchange mockado falhou');
$tokenCall = end($calls);
$assert($tokenCall[1] === 'https://auth.coros.test/token' && trim((string) ($tokenCall[2]['body']['code_verifier'] ?? '')) !== '', 'COROS exchange precisa usar endpoint descoberto e verifier');
$metadataCandidates = stridebr_integrations_coros_resource_metadata_candidates('https://mcp.coros.test/mcp');
$assert(($metadataCandidates[0] ?? '') === 'https://mcp.coros.test/.well-known/oauth-protected-resource/mcp', 'COROS discovery precisa priorizar Protected Resource Metadata path-aware');

$modernRequest = [];
$GLOBALS['stridebr_integrations_http_mock'] = static function (string $method, string $url, array $options) use (&$modernRequest): array {
    $modernRequest = [$method, $url, $options];
    $json = ['jsonrpc' => '2.0', 'id' => 'stridebr-protocol', 'result' => ['capabilities' => []]];
    return ['status' => 200, 'body' => json_encode($json), 'json' => $json, 'headers' => []];
};
$assert(stridebr_integrations_coros_mcp_mode('https://mcp.coros.test/mcp', 'fixture-token') === 'modern', 'COROS deve negociar MCP atual quando server/discover estiver disponível');
$modernHeaders = implode("\n", $modernRequest[2]['headers'] ?? []);
$modernPayload = $modernRequest[2]['json'] ?? [];
$assert(str_contains($modernHeaders, 'MCP-Protocol-Version: 2026-07-28') && str_contains($modernHeaders, 'Mcp-Method: server/discover'), 'COROS MCP atual precisa enviar versão e método nos headers');
$assert(($modernPayload['params']['_meta']['io.modelcontextprotocol/protocolVersion'] ?? '') === '2026-07-28', 'COROS MCP atual precisa anunciar protocolVersion em _meta');

$GLOBALS['stridebr_integrations_http_mock'] = static function (): array {
    $json = ['jsonrpc' => '2.0', 'id' => 'stridebr-protocol', 'error' => ['code' => -32601, 'message' => 'Method not found']];
    return ['status' => 200, 'body' => json_encode($json), 'json' => $json, 'headers' => []];
};
$assert(stridebr_integrations_coros_mcp_mode('https://mcp.coros.test/mcp', 'fixture-token') === 'legacy', 'COROS precisa fallback controlado quando servidor legado não reconhece server/discover');

$GLOBALS['stridebr_integrations_http_mock'] = static fn(): array => ['status' => 401, 'body' => '{"error":"invalid_token"}', 'json' => ['error' => 'invalid_token'], 'headers' => []];
try {
    stridebr_integrations_http('GET', 'https://provider.test/resource');
    $assert(false, 'erro HTTP externo não propagado');
} catch (RuntimeException $e) {
    $assert(str_contains($e->getMessage(), 'invalid_token'), 'erro HTTP precisa ser compreensível');
}
unset($GLOBALS['stridebr_integrations_http_mock']);

$callback = (string) file_get_contents($root . '/public/auth/integration-callback.php');
$integrations = (string) file_get_contents($root . '/src/function/integrations.php');
$envExample = (string) file_get_contents($root . '/.env.example');
$callbackAction = (string) file_get_contents($root . '/public/function/integration-action.php');
$integrationStart = (string) file_get_contents($root . '/public/auth/integration.php');
$assert(str_contains($callback, 'hash_equals') && str_contains($callback, "time() - 900"), 'callback precisa validar state e expiração');
$assert(str_contains($integrations, "'grant_type' => 'refresh_token'") && str_contains($integrations, 'stridebr_integrations_refresh('), 'refresh sob demanda precisa existir');
$assert(!str_contains($integrations, 'api.fitbit.com') && !str_contains($integrations, 'fitbit.com/oauth2'), 'Fitbit Web legado não pode permanecer ativo');
$assert(!str_contains($integrations, 'polaraccesslink.com/v3') && !str_contains($integrations, 'polarremote.com'), 'Polar legado não pode permanecer ativo');
$assert(str_contains($integrations, "modify('+1 day')") && str_contains($integrations, "features=routes&features=statistics&features=laps"), 'Polar v4 precisa tratar janela exclusiva e enriquecimento suportado');
$assert(str_contains($integrations, ':exportExerciseTcx?alt=media') && str_contains($integrations, 'elevationGainMillimeters'), 'Google Health precisa aproveitar TCX e métricas de exercício');
$assert(str_contains($integrations, "'name' => 'getActivityDetail'") && str_contains($integrations, '$fitDownloads < 50'), 'COROS precisa respeitar limite FIT e fallback de detalhe');
$assert(str_contains($integrations, 'https://www.strava.com/oauth/revoke') && str_contains($integrations, 'stridebr_integrations_duplicate'), 'Strava precisa tratar desconexão e deduplicação');
$assert(!preg_match('/^(?:STRAVA_CLIENT_SECRET|POLAR_CLIENT_SECRET|GOOGLE_HEALTH_CLIENT_SECRET|SUUNTO_CLIENT_SECRET|STRIDEBR_INTEGRATIONS_SECRET)=.+$/m', $envExample), '.env.example não pode conter secrets reais');
$assert(!str_contains($envExample, 'FITBIT_CLIENT_ID=') && !str_contains($envExample, 'FITBIT_CLIENT_SECRET=') && !str_contains($envExample, 'COROS_CLIENT_ID=') && !str_contains($envExample, 'COROS_CLIENT_SECRET='), '.env.example não pode reativar Fitbit nem inventar credenciais COROS');
$assert(!preg_match('/error_log\([^;]*getMessage/s', $callback . $callbackAction . $integrationStart), 'fluxos OAuth não podem registrar mensagens externas potencialmente sensíveis');
$syncRunner = (string) file_get_contents($root . '/scripts/sync_integrations.php');
$assert(!str_contains($syncRunner, '$error->getMessage()'), 'runner de integração não pode despejar erro externo bruto nos logs');

printf("✓ external integrations RC4: %d assertions\n", $checks);
