<?php

declare(strict_types=1);

require_once __DIR__ . '/integration_sync_support.php';

function stridebr_integrations_id(int $length = 21): string
{
    $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz-';
    $result = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) $result .= $alphabet[random_int(0, $max)];
    return $result;
}

function stridebr_integrations_registry(): array
{
    $garminAuthorize = trim((string) (getenv('GARMIN_OAUTH_AUTHORIZE_URL') ?: ''));
    $garminToken = trim((string) (getenv('GARMIN_OAUTH_TOKEN_URL') ?: ''));
    $suuntoAuthorize = trim((string) (getenv('SUUNTO_OAUTH_AUTHORIZE_URL') ?: 'https://cloudapi-oauth.suunto.com/oauth/authorize'));
    $suuntoToken = trim((string) (getenv('SUUNTO_OAUTH_TOKEN_URL') ?: 'https://cloudapi-oauth.suunto.com/oauth/token'));
    $suuntoApi = rtrim(trim((string) (getenv('SUUNTO_API_BASE_URL') ?: 'https://cloudapi.suunto.com')), '/');
    $suuntoSubscriptionKey = trim((string) (getenv('SUUNTO_SUBSCRIPTION_KEY') ?: ''));
    return [
        'garmin' => [
            'label' => 'Garmin Connect',
            'short' => 'Garmin',
            'description' => (function_exists('stridebr_t') ? stridebr_t('integrations.provider.garmin.description') : 'Integração preparada para as APIs oficiais da Garmin quando o acesso ao programa for liberado.'),
            'kind' => 'cloud',
            'oauth' => true,
            'implementation_ready' => false,
            'availability' => 'external_blocked',
            'client_id' => trim((string) (getenv('GARMIN_OAUTH_CLIENT_ID') ?: '')),
            'client_secret' => trim((string) (getenv('GARMIN_OAUTH_CLIENT_SECRET') ?: '')),
            'authorize_url' => $garminAuthorize,
            'token_url' => $garminToken,
            'scope' => trim((string) (getenv('GARMIN_OAUTH_SCOPE') ?: '')),
            'token_auth' => trim((string) (getenv('GARMIN_OAUTH_TOKEN_AUTH') ?: 'basic')),
            'capabilities' => ['activities_in', 'workouts_out', 'courses_out'],
            'profile_link' => true,
        ],
        'strava' => [
            'label' => 'Strava',
            'short' => 'Strava',
            'description' => (function_exists('stridebr_t') ? stridebr_t('integrations.provider.strava.description') : 'Importe suas atividades do Strava e mantenha a origem registrada no histórico.'),
            'kind' => 'cloud',
            'oauth' => true,
            'client_id' => trim((string) (getenv('STRAVA_CLIENT_ID') ?: '')),
            'client_secret' => trim((string) (getenv('STRAVA_CLIENT_SECRET') ?: '')),
            'authorize_url' => 'https://www.strava.com/oauth/authorize',
            'token_url' => 'https://www.strava.com/api/v3/oauth/token',
            'api_base_url' => 'https://www.strava.com/api/v3',
            'scope' => 'read,activity:read_all',
            'token_auth' => 'body',
            'capabilities' => ['activities_in'],
            'profile_link' => true,
        ],
        'polar' => [
            'label' => 'Polar Flow',
            'short' => 'Polar',
            'description' => (function_exists('stridebr_t') ? stridebr_t('integrations.provider.polar.description') : 'Receba sessões do Polar Flow pela AccessLink API v4, com rota e métricas quando disponíveis.'),
            'kind' => 'cloud',
            'oauth' => true,
            'client_id' => trim((string) (getenv('POLAR_CLIENT_ID') ?: '')),
            'client_secret' => trim((string) (getenv('POLAR_CLIENT_SECRET') ?: '')),
            'authorize_url' => trim((string) (getenv('POLAR_OAUTH_AUTHORIZE_URL') ?: 'https://auth.polar.com/oauth/authorize')),
            'token_url' => trim((string) (getenv('POLAR_OAUTH_TOKEN_URL') ?: 'https://auth.polar.com/oauth/token')),
            'api_base_url' => 'https://www.polaraccesslink.com/v4/data',
            'scope' => trim((string) (getenv('POLAR_OAUTH_SCOPE') ?: 'training_sessions:read activity:read profile:read')),
            'token_auth' => 'basic',
            'capabilities' => ['activities_in'],
            'profile_link' => true,
        ],
        'google_health' => [
            'label' => 'Google Health',
            'short' => 'Google Health',
            'description' => (function_exists('stridebr_t') ? stridebr_t('integrations.provider.google_health.description') : 'Importe atividades do Fitbit e de dispositivos compatíveis pela Google Health API.'),
            'kind' => 'cloud',
            'oauth' => true,
            'client_id' => trim((string) (getenv('GOOGLE_HEALTH_CLIENT_ID') ?: '')),
            'client_secret' => trim((string) (getenv('GOOGLE_HEALTH_CLIENT_SECRET') ?: '')),
            'authorize_url' => trim((string) (getenv('GOOGLE_HEALTH_OAUTH_AUTHORIZE_URL') ?: 'https://accounts.google.com/o/oauth2/v2/auth')),
            'token_url' => trim((string) (getenv('GOOGLE_HEALTH_OAUTH_TOKEN_URL') ?: 'https://oauth2.googleapis.com/token')),
            'api_base_url' => 'https://health.googleapis.com/v4',
            'scope' => trim((string) (getenv('GOOGLE_HEALTH_OAUTH_SCOPE') ?: 'https://www.googleapis.com/auth/googlehealth.activity_and_fitness.readonly https://www.googleapis.com/auth/googlehealth.location.readonly https://www.googleapis.com/auth/googlehealth.health_metrics_and_measurements.readonly')),
            'token_auth' => 'body',
            'capabilities' => ['activities_in', 'health_in'],
            'profile_link' => false,
        ],
        'coros' => [
            'label' => 'COROS',
            'short' => 'COROS',
            'description' => (function_exists('stridebr_t') ? stridebr_t('integrations.provider.coros.description') : 'Importe atividades da sua conta COROS pelo MCP oficial, com FIT quando disponível.'),
            'kind' => 'cloud',
            'oauth' => true,
            'oauth_discovery' => 'mcp',
            'mcp_url' => trim((string) (getenv('COROS_MCP_URL') ?: 'https://mcp.coros.com/mcp')),
            'capabilities' => ['activities_in'],
            'profile_link' => false,
        ],
        'suunto' => [
            'label' => 'Suunto',
            'short' => 'Suunto',
            'description' => (function_exists('stridebr_t') ? stridebr_t('integrations.provider.suunto.description') : 'Importe treinos da Suunto App pela Suunto Cloud API quando a integração estiver aprovada e configurada.'),
            'kind' => 'cloud',
            'oauth' => true,
            'client_id' => trim((string) (getenv('SUUNTO_CLIENT_ID') ?: '')),
            'client_secret' => trim((string) (getenv('SUUNTO_CLIENT_SECRET') ?: '')),
            'authorize_url' => $suuntoAuthorize,
            'token_url' => $suuntoToken,
            'scope' => trim((string) (getenv('SUUNTO_OAUTH_SCOPE') ?: 'workout')),
            'token_auth' => trim((string) (getenv('SUUNTO_OAUTH_TOKEN_AUTH') ?: 'basic')),
            'api_base_url' => $suuntoApi,
            'subscription_key' => $suuntoSubscriptionKey,
            'capabilities' => ['activities_in'],
            'profile_link' => true,
        ],
        'halo' => [
            'label' => 'HALO',
            'short' => 'HALO',
            'description' => (function_exists('stridebr_t') ? stridebr_t('integrations.provider.halo.description') : 'Integração direta aguardando disponibilidade ou parceria; no mobile poderá usar Health Connect ou Apple Health.'),
            'kind' => 'future',
            'oauth' => false,
            'availability' => 'waiting',
            'capabilities' => [],
            'profile_link' => false,
        ],
        'health_connect' => [
            'label' => 'Health Connect',
            'short' => 'Health Connect',
            'description' => (function_exists('stridebr_t') ? stridebr_t('integrations.provider.health_connect.description') : 'Ponte do Android para exercícios gravados por Samsung Health e outros aplicativos compatíveis. Requer o app Android do StrideBR.'),
            'kind' => 'mobile',
            'oauth' => false,
            'capabilities' => ['activities_in', 'health_in'],
            'profile_link' => false,
        ],
        'samsung_health' => [
            'label' => 'Samsung Health',
            'short' => 'Samsung Health',
            'description' => (function_exists('stridebr_t') ? stridebr_t('integrations.provider.samsung_health.description') : 'No Android, os exercícios do Galaxy Watch podem chegar ao StrideBR pelo Health Connect.'),
            'kind' => 'bridge',
            'oauth' => false,
            'capabilities' => ['activities_in'],
            'profile_link' => false,
        ],
        'apple_health' => [
            'label' => 'Apple Health',
            'short' => 'Apple Health',
            'description' => (function_exists('stridebr_t') ? stridebr_t('integrations.provider.apple_health.description') : 'Sincronização com Apple Watch e Saúde via HealthKit. Requer o app iOS do StrideBR.'),
            'kind' => 'mobile',
            'oauth' => false,
            'capabilities' => ['activities_in', 'health_in'],
            'profile_link' => false,
        ],
    ];
}

function stridebr_integrations_provider(string $provider): array
{
    $provider = stridebr_lower(trim($provider));
    $config = stridebr_integrations_registry()[$provider] ?? null;
    if (!is_array($config)) throw new InvalidArgumentException('Integração desconhecida.');
    $config['id'] = $provider;
    return $config;
}

function stridebr_integrations_secret(): ?string
{
    $secret = trim((string) (getenv('STRIDEBR_INTEGRATIONS_SECRET') ?: ''));
    return strlen($secret) >= 32 ? hash('sha256', $secret, true) : null;
}

function stridebr_integrations_encrypt(?string $value): ?string
{
    if ($value === null || $value === '') return null;
    $key = stridebr_integrations_secret();
    if ($key === null || !function_exists('openssl_encrypt')) throw new RuntimeException('Defina STRIDEBR_INTEGRATIONS_SECRET para conectar contas externas.');
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if (!is_string($cipher) || $tag === '') throw new RuntimeException('Não foi possível proteger a credencial da integração.');
    return rtrim(strtr(base64_encode($iv . $tag . $cipher), '+/', '-_'), '=');
}

function stridebr_integrations_decrypt(?string $value): ?string
{
    if ($value === null || trim($value) === '') return null;
    $key = stridebr_integrations_secret();
    if ($key === null || !function_exists('openssl_decrypt')) return null;
    $raw = base64_decode(strtr($value, '-_', '+/'), true);
    if (!is_string($raw) || strlen($raw) < 29) return null;
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return is_string($plain) ? $plain : null;
}

function stridebr_integrations_https_url(string $url): bool
{
    return filter_var($url, FILTER_VALIDATE_URL) !== false && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
}

function stridebr_integrations_metadata(array $connection): array
{
    $meta = $connection['metadados'] ?? [];
    if (is_array($meta)) return array_is_list($meta) ? [] : $meta;
    if (!is_string($meta) || trim($meta) === '') return [];
    $decoded = json_decode($meta, true);
    return is_array($decoded) && !array_is_list($decoded) ? $decoded : [];
}

function stridebr_integrations_scope_set(string|array|null $scope): array
{
    $items = is_array($scope) ? $scope : (preg_split('/[\s,]+/', trim((string) $scope)) ?: []);
    $result = [];
    foreach ($items as $item) {
        $item = trim((string) $item);
        if ($item !== '') $result[$item] = true;
    }
    return array_keys($result);
}

function stridebr_integrations_scopes_cover(string|array|null $granted, string|array|null $requested): bool
{
    $have = array_fill_keys(stridebr_integrations_scope_set($granted), true);
    foreach (stridebr_integrations_scope_set($requested) as $scope) if (!isset($have[$scope])) return false;
    return true;
}

function stridebr_integrations_configured(array $provider): bool
{
    if (($provider['kind'] ?? '') !== 'cloud') return false;
    if (array_key_exists('implementation_ready', $provider) && !$provider['implementation_ready']) return false;
    if (stridebr_integrations_secret() === null) return false;
    if (($provider['id'] ?? '') === 'coros' || ($provider['oauth_discovery'] ?? '') === 'mcp') {
        return stridebr_integrations_https_url((string) ($provider['mcp_url'] ?? ''));
    }
    $base = trim((string) ($provider['client_id'] ?? '')) !== ''
        && trim((string) ($provider['client_secret'] ?? '')) !== ''
        && stridebr_integrations_https_url((string) ($provider['authorize_url'] ?? ''))
        && stridebr_integrations_https_url((string) ($provider['token_url'] ?? ''));
    if (!$base) return false;
    if (($provider['id'] ?? '') === 'suunto' || ($provider['label'] ?? '') === 'Suunto') return trim((string) ($provider['subscription_key'] ?? '')) !== '';
    return true;
}

function stridebr_integrations_callback_uri(string $provider): string
{
    return stridebr_app_url() . '/auth/integration-callback.php?provider=' . rawurlencode($provider);
}

function stridebr_integrations_list(PDO $pdo, string $userId): array
{
    try {
        $stmt = $pdo->prepare('SELECT * FROM integracoes_usuario WHERE idusuario = :usuario ORDER BY provedor');
        $stmt->execute([':usuario' => $userId]);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) $rows[(string) $row['provedor']] = $row;
        return $rows;
    } catch (PDOException $e) {
        if ($e->getCode() === '42P01') return [];
        throw $e;
    }
}

function stridebr_integrations_get(PDO $pdo, string $userId, string $provider): ?array
{
    try {
        $stmt = $pdo->prepare('SELECT * FROM integracoes_usuario WHERE idusuario = :usuario AND provedor = :provedor LIMIT 1');
        $stmt->execute([':usuario' => $userId, ':provedor' => $provider]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        if ($e->getCode() === '42P01') return null;
        throw $e;
    }
}

function stridebr_integrations_http(string $method, string $url, array $options = []): array
{
    if (!empty($GLOBALS['stridebr_integrations_http_mock']) && is_callable($GLOBALS['stridebr_integrations_http_mock'])) {
        $mocked = ($GLOBALS['stridebr_integrations_http_mock'])($method, $url, $options);
        if (!is_array($mocked)) throw new RuntimeException('Mock HTTP inválido.');
        $mocked += ['status' => 200, 'body' => '', 'json' => null, 'headers' => []];
        if (($mocked['status'] < 200 || $mocked['status'] >= 300) && empty($options['allow_error'])) {
            throw stridebr_integrations_http_error((int) $mocked['status'], $mocked['headers']);
        }
        return $mocked;
    }
    if (!stridebr_integrations_https_url($url)) throw new RuntimeException('Endpoint externo inválido.');
    $headers = array_values(array_filter(array_map('strval', $options['headers'] ?? [])));
    $body = $options['body'] ?? null;
    if (isset($options['json'])) {
        $body = json_encode($options['json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $headers[] = 'Content-Type: application/json';
    } elseif (is_array($body)) {
        $body = http_build_query($body, '', '&', PHP_QUERY_RFC3986);
    }
    $responseHeaders = [];
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) ($options['timeout'] ?? 20),
            CURLOPT_CONNECTTIMEOUT => 7,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => !empty($options['follow_redirects']),
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $parts = explode(':', trim($line), 2);
                if (count($parts) === 2) $responseHeaders[strtolower(trim($parts[0]))][] = trim($parts[1]);
                return $length;
            },
        ];
        if ($body !== null) $curlOptions[CURLOPT_POSTFIELDS] = (string) $body;
        curl_setopt_array($ch, $curlOptions);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if (!is_string($response)) throw new RuntimeException('Não foi possível falar com o serviço externo.' );
    } else {
        $context = stream_context_create(['http' => [
            'method' => strtoupper($method), 'header' => implode("\r\n", $headers) . "\r\n",
            'content' => $body !== null ? (string) $body : '', 'timeout' => (int) ($options['timeout'] ?? 20), 'ignore_errors' => true,
            'follow_location' => !empty($options['follow_redirects']) ? 1 : 0, 'max_redirects' => 3,
        ]]);
        $response = @file_get_contents($url, false, $context);
        if (!is_string($response)) throw new RuntimeException('Não foi possível falar com o serviço externo.');
        $status = 200;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\\S+\\s+(\\d{3})#', $header, $match)) $status = (int) $match[1];
            elseif (str_contains($header, ':')) {
                [$name, $value] = explode(':', $header, 2);
                $responseHeaders[strtolower(trim($name))][] = trim($value);
            }
        }
    }
    $decoded = json_decode($response, true);
    if (($status < 200 || $status >= 300) && empty($options['allow_error'])) {
        throw stridebr_integrations_http_error($status, $responseHeaders);
    }
    return ['status' => $status, 'body' => $response, 'json' => is_array($decoded) ? $decoded : null, 'headers' => $responseHeaders];
}

function stridebr_integrations_header_first(array $response, string $name): ?string
{
    $values = $response['headers'][strtolower($name)] ?? [];
    if (!is_array($values)) $values = [$values];
    foreach ($values as $value) if (trim((string) $value) !== '') return trim((string) $value);
    return null;
}

function stridebr_integrations_coros_resource_metadata_candidates(string $mcpUrl): array
{
    $parts = parse_url($mcpUrl);
    if (!is_array($parts) || empty($parts['host'])) throw new RuntimeException('Não foi possível descobrir a autorização da COROS.');
    $origin = 'https://' . $parts['host'] . (isset($parts['port']) ? ':' . (int) $parts['port'] : '');
    $path = trim((string) ($parts['path'] ?? ''), '/');
    $candidates = [];
    if ($path !== '') $candidates[] = $origin . '/.well-known/oauth-protected-resource/' . $path;
    $candidates[] = $origin . '/.well-known/oauth-protected-resource';
    return array_values(array_unique($candidates));
}

function stridebr_integrations_coros_discovery(string $mcpUrl): array
{
    $meta = [
        'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
        'io.modelcontextprotocol/clientInfo' => ['name' => 'StrideBR', 'version' => '1.0.0-rc.5'],
        'io.modelcontextprotocol/clientCapabilities' => [],
    ];
    $probe = stridebr_integrations_http('POST', $mcpUrl, [
        'headers' => [
            'Accept: application/json, text/event-stream',
            'Content-Type: application/json',
            'MCP-Protocol-Version: 2026-07-28',
            'Mcp-Method: server/discover',
        ],
        'json' => ['jsonrpc' => '2.0', 'id' => 'stridebr-auth-discovery', 'method' => 'server/discover', 'params' => ['_meta' => $meta]],
        'allow_error' => true,
        'follow_redirects' => true,
    ]);
    $authenticate = stridebr_integrations_header_first($probe, 'www-authenticate') ?? '';
    $resourceMetadataUrl = null;
    $scope = '';
    if (preg_match('/resource_metadata="([^"]+)"/i', $authenticate, $match) && stridebr_integrations_https_url($match[1])) $resourceMetadataUrl = $match[1];
    if (preg_match('/(?:^|[,\s])scope="([^"]+)"/i', $authenticate, $match)) $scope = trim($match[1]);

    $candidates = $resourceMetadataUrl !== null ? [$resourceMetadataUrl] : stridebr_integrations_coros_resource_metadata_candidates($mcpUrl);
    foreach ($candidates as $candidate) {
        try {
            $response = stridebr_integrations_http('GET', $candidate, ['headers' => ['Accept: application/json'], 'allow_error' => true, 'follow_redirects' => true]);
            if ($response['status'] >= 200 && $response['status'] < 300 && is_array($response['json'])) {
                return ['resource_metadata_url' => $candidate, 'resource' => $response['json'], 'challenge_scope' => $scope];
            }
        } catch (Throwable) {
        }
    }
    throw new RuntimeException('A COROS não publicou metadados OAuth de recurso utilizáveis.');
}

function stridebr_integrations_coros_auth_metadata(string $mcpUrl): array
{
    $discovery = stridebr_integrations_coros_discovery($mcpUrl);
    $resourceUrl = (string) $discovery['resource_metadata_url'];
    $resource = $discovery['resource'];
    if (!is_array($resource)) throw new RuntimeException('A COROS não retornou metadados OAuth válidos.');
    $canonicalResource = trim((string) ($resource['resource'] ?? $mcpUrl));
    if (!stridebr_integrations_https_url($canonicalResource)) throw new RuntimeException('A COROS publicou um identificador de recurso inválido.');
    $servers = is_array($resource['authorization_servers'] ?? null) ? $resource['authorization_servers'] : [];
    $issuer = trim((string) ($servers[0] ?? ''));
    if (!stridebr_integrations_https_url($issuer)) throw new RuntimeException('A COROS não anunciou um servidor OAuth seguro.');
    $issuerParts = parse_url($issuer);
    $origin = 'https://' . (string) $issuerParts['host'] . (isset($issuerParts['port']) ? ':' . (int) $issuerParts['port'] : '');
    $path = rtrim((string) ($issuerParts['path'] ?? ''), '/');
    $candidates = [];
    if ($path !== '') {
        $candidates[] = $origin . '/.well-known/oauth-authorization-server' . $path;
        $candidates[] = $origin . '/.well-known/openid-configuration' . $path;
        $candidates[] = rtrim($issuer, '/') . '/.well-known/openid-configuration';
    } else {
        $candidates[] = $origin . '/.well-known/oauth-authorization-server';
        $candidates[] = $origin . '/.well-known/openid-configuration';
    }
    $metadata = null;
    foreach (array_values(array_unique($candidates)) as $candidate) {
        try {
            $response = stridebr_integrations_http('GET', $candidate, ['headers' => ['Accept: application/json'], 'allow_error' => true]);
            if ($response['status'] >= 200 && $response['status'] < 300 && is_array($response['json']) && hash_equals($issuer, trim((string) ($response['json']['issuer'] ?? '')))) { $metadata = $response['json']; break; }
        } catch (Throwable) {
        }
    }
    if (!is_array($metadata)) throw new RuntimeException('Não foi possível descobrir os endpoints OAuth da COROS.');
    $authorize = trim((string) ($metadata['authorization_endpoint'] ?? ''));
    $token = trim((string) ($metadata['token_endpoint'] ?? ''));
    if (!stridebr_integrations_https_url($authorize) || !stridebr_integrations_https_url($token)) throw new RuntimeException('A COROS anunciou endpoints OAuth inválidos.');
    $pkce = is_array($metadata['code_challenge_methods_supported'] ?? null) ? $metadata['code_challenge_methods_supported'] : [];
    if (!in_array('S256', $pkce, true)) throw new RuntimeException('O servidor OAuth da COROS não anunciou PKCE S256.');
    $scope = trim((string) ($discovery['challenge_scope'] ?? ''));
    if ($scope === '') {
        $supported = is_array($resource['scopes_supported'] ?? null) ? array_values(array_filter(array_map('strval', $resource['scopes_supported']))) : [];
        $scope = implode(' ', $supported);
    }
    return ['resource' => $resource, 'authorization' => $metadata, 'resource_metadata_url' => $resourceUrl, 'issuer' => $issuer, 'authorize_url' => $authorize, 'token_url' => $token, 'scope' => $scope, 'mcp_url' => $canonicalResource];
}

function stridebr_integrations_coros_client(array $metadata): array
{
    $auth = $metadata['authorization'];
    $redirect = stridebr_integrations_callback_uri('coros');
    $clientMetadataUrl = stridebr_app_url() . '/auth/mcp-client-metadata.php';
    if (!empty($auth['client_id_metadata_document_supported'])) return ['client_id' => $clientMetadataUrl, 'token_auth' => 'none'];
    $registration = trim((string) ($auth['registration_endpoint'] ?? ''));
    if (!stridebr_integrations_https_url($registration)) throw new RuntimeException('O servidor OAuth da COROS não oferece registro dinâmico compatível.');
    $response = stridebr_integrations_http('POST', $registration, ['headers' => ['Accept: application/json'], 'json' => [
        'client_name' => 'StrideBR', 'client_uri' => stridebr_app_url(), 'redirect_uris' => [$redirect],
        'grant_types' => ['authorization_code', 'refresh_token'], 'response_types' => ['code'], 'token_endpoint_auth_method' => 'none',
    ]]);
    $data = $response['json'];
    $clientId = is_array($data) ? trim((string) ($data['client_id'] ?? '')) : '';
    if ($clientId === '') throw new RuntimeException('A COROS não retornou um identificador OAuth válido.');
    return ['client_id' => $clientId, 'client_secret' => is_array($data) ? trim((string) ($data['client_secret'] ?? '')) : '', 'token_auth' => is_array($data) ? trim((string) ($data['token_endpoint_auth_method'] ?? 'none')) : 'none'];
}

function stridebr_integrations_start(string $providerId, string $returnTo = '/user/settings.php?view=connections#conexoes', bool $forceConsent = false): string
{
    $provider = stridebr_integrations_provider($providerId);
    if (!stridebr_integrations_configured($provider)) throw new RuntimeException($provider['label'] . ' ainda não está configurado no servidor.');
    $state = bin2hex(random_bytes(24));
    $pending = ['provider' => $providerId, 'return' => stridebr_safe_redirect($returnTo, '/user/settings.php?view=connections#conexoes'), 'created_at' => time()];
    if ($providerId === 'coros') {
        $metadata = stridebr_integrations_coros_auth_metadata((string) $provider['mcp_url']);
        $client = stridebr_integrations_coros_client($metadata);
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $pending['pkce_verifier'] = $verifier;
        $pending['token_url'] = $metadata['token_url'];
        $pending['issuer'] = $metadata['issuer'];
        $pending['mcp_url'] = $metadata['mcp_url'];
        $pending['client_id'] = $client['client_id'];
        $pending['token_auth'] = $client['token_auth'] ?? 'none';
        if (trim((string) ($client['client_secret'] ?? '')) !== '') $pending['client_secret_enc'] = stridebr_integrations_encrypt((string) $client['client_secret']);
        $_SESSION['StrideBRIntegrationOAuth'][$state] = $pending;
        $params = ['client_id' => $client['client_id'], 'redirect_uri' => stridebr_integrations_callback_uri('coros'), 'response_type' => 'code', 'state' => $state, 'code_challenge' => $challenge, 'code_challenge_method' => 'S256', 'resource' => $metadata['mcp_url']];
        if (trim((string) ($metadata['scope'] ?? '')) !== '') $params['scope'] = trim((string) $metadata['scope']);
        return $metadata['authorize_url'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
    $_SESSION['StrideBRIntegrationOAuth'][$state] = $pending;
    $params = ['client_id' => $provider['client_id'], 'redirect_uri' => stridebr_integrations_callback_uri($providerId), 'response_type' => 'code', 'state' => $state];
    if (trim((string) ($provider['scope'] ?? '')) !== '') $params['scope'] = $provider['scope'];
    if ($providerId === 'strava') $params['approval_prompt'] = $forceConsent ? 'force' : 'auto';
    if ($providerId === 'google_health') {
        $params['access_type'] = 'offline';
        $params['include_granted_scopes'] = 'true';
        if ($forceConsent) $params['prompt'] = 'consent';
    }
    return $provider['authorize_url'] . (str_contains($provider['authorize_url'], '?') ? '&' : '?') . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function stridebr_integrations_exchange(string $providerId, string $code, array $pending = []): array
{
    $provider = stridebr_integrations_provider($providerId);
    if (!stridebr_integrations_configured($provider)) throw new RuntimeException($provider['label'] . ' não está configurado.');
    if ($providerId === 'coros') {
        $tokenUrl = trim((string) ($pending['token_url'] ?? ''));
        $clientId = trim((string) ($pending['client_id'] ?? ''));
        $verifier = trim((string) ($pending['pkce_verifier'] ?? ''));
        if (!stridebr_integrations_https_url($tokenUrl) || $clientId === '' || $verifier === '') throw new RuntimeException('A autorização COROS expirou.');
        $fields = ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => stridebr_integrations_callback_uri('coros'), 'client_id' => $clientId, 'code_verifier' => $verifier, 'resource' => (string) ($pending['mcp_url'] ?? $provider['mcp_url'])];
        $headers = ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'];
        $secret = stridebr_integrations_decrypt((string) ($pending['client_secret_enc'] ?? ''));
        if ($secret && in_array((string) ($pending['token_auth'] ?? ''), ['client_secret_basic', 'basic'], true)) $headers[] = 'Authorization: Basic ' . base64_encode($clientId . ':' . $secret);
        elseif ($secret) $fields['client_secret'] = $secret;
        $response = stridebr_integrations_http('POST', $tokenUrl, ['headers' => $headers, 'body' => $fields]);
        $token = $response['json'];
        if (!is_array($token) || trim((string) ($token['access_token'] ?? '')) === '') throw new RuntimeException('A COROS não retornou uma credencial válida.');
        $token['_stridebr_meta'] = ['token_url' => $tokenUrl, 'issuer' => (string) ($pending['issuer'] ?? ''), 'mcp_url' => (string) ($pending['mcp_url'] ?? $provider['mcp_url']), 'client_id' => $clientId, 'token_auth' => (string) ($pending['token_auth'] ?? 'none'), 'client_secret_enc' => (string) ($pending['client_secret_enc'] ?? '')];
        return $token;
    }
    $fields = ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => stridebr_integrations_callback_uri($providerId)];
    $headers = ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'];
    if (($provider['token_auth'] ?? 'basic') === 'body') {
        $fields['client_id'] = $provider['client_id']; $fields['client_secret'] = $provider['client_secret'];
    } else {
        $headers[] = 'Authorization: Basic ' . base64_encode($provider['client_id'] . ':' . $provider['client_secret']);
    }
    $response = stridebr_integrations_http('POST', $provider['token_url'], ['headers' => $headers, 'body' => $fields]);
    $token = $response['json'];
    if (!is_array($token) || trim((string) ($token['access_token'] ?? '')) === '') throw new RuntimeException('O serviço não retornou uma credencial válida.');
    return $token;
}

function stridebr_integrations_jwt_claim(string $token, string $claim): mixed
{
    $parts = explode('.', $token);
    if (count($parts) < 2) return null;
    $payload = strtr($parts[1], '-_', '+/');
    $padding = strlen($payload) % 4;
    if ($padding > 0) $payload .= str_repeat('=', 4 - $padding);
    $decoded = base64_decode($payload, true);
    if (!is_string($decoded)) return null;
    $json = json_decode($decoded, true);
    return is_array($json) ? ($json[$claim] ?? null) : null;
}

function stridebr_integrations_save_token(PDO $pdo, string $userId, string $providerId, array $token): array
{
    $provider = stridebr_integrations_provider($providerId);
    $externalId = null;
    $externalName = null;
    $metadata = [];
    if ($providerId === 'strava' && is_array($token['athlete'] ?? null)) {
        $athlete = $token['athlete'];
        $externalId = isset($athlete['id']) ? (string) $athlete['id'] : null;
        $externalName = trim((string) (($athlete['firstname'] ?? '') . ' ' . ($athlete['lastname'] ?? ''))) ?: null;
        $metadata['athlete'] = array_intersect_key($athlete, array_flip(['id', 'username', 'firstname', 'lastname', 'profile_medium']));
    } elseif ($providerId === 'polar') {
        $externalId = isset($token['x_user_id']) ? (string) $token['x_user_id'] : null;
    } elseif ($providerId === 'suunto') {
        $claim = stridebr_integrations_jwt_claim((string) ($token['access_token'] ?? ''), 'user');
        if (is_scalar($claim) && trim((string) $claim) !== '') {
            $externalId = trim((string) $claim);
            $externalName = $externalId;
            $metadata['suunto_user'] = $externalId;
        }
    }
    if ($providerId === 'google_health' && is_numeric($token['refresh_token_expires_in'] ?? null)) {
        $metadata['refresh_token_expires_at'] = (new DateTimeImmutable('now'))->modify('+' . max(0, (int) $token['refresh_token_expires_in']) . ' seconds')->format('c');
    }
    if ($providerId === 'coros' && is_array($token['_stridebr_meta'] ?? null)) {
        $metadata['oauth'] = array_intersect_key($token['_stridebr_meta'], array_flip(['token_url', 'issuer', 'mcp_url', 'client_id', 'token_auth', 'client_secret_enc']));
    }
    $expiresAt = null;
    if (is_numeric($token['expires_at'] ?? null)) $expiresAt = (new DateTimeImmutable('@' . (int) $token['expires_at']))->format('c');
    elseif (is_numeric($token['expires_in'] ?? null)) $expiresAt = (new DateTimeImmutable('now'))->modify('+' . max(0, (int) $token['expires_in']) . ' seconds')->format('c');
    $scope = is_array($token['scope'] ?? null) ? implode(' ', $token['scope']) : trim((string) ($token['scope'] ?? ''));
    if ($scope === '') $scope = trim((string) ($provider['scope'] ?? ''));
    $stmt = $pdo->prepare(
        "INSERT INTO integracoes_usuario
        (idintegracao, idusuario, provedor, status, usuario_externo_id, usuario_externo_nome, access_token_enc, refresh_token_enc, token_expira_em, escopos, metadados, sincronizar_atividades, ultima_sincronizacao_em, ultimo_erro, atualizado_em)
        VALUES (:id, :usuario, :provedor, 'conectado', :externo, :nome, :access, :refresh, :expira, :escopos, CAST(:metadados AS jsonb), :atividades, NULL, NULL, NOW())
        ON CONFLICT (idusuario, provedor) DO UPDATE SET
            status = 'conectado', usuario_externo_id = COALESCE(EXCLUDED.usuario_externo_id, integracoes_usuario.usuario_externo_id),
            usuario_externo_nome = COALESCE(EXCLUDED.usuario_externo_nome, integracoes_usuario.usuario_externo_nome),
            access_token_enc = EXCLUDED.access_token_enc,
            refresh_token_enc = COALESCE(EXCLUDED.refresh_token_enc, integracoes_usuario.refresh_token_enc),
            token_expira_em = EXCLUDED.token_expira_em, escopos = CASE WHEN EXCLUDED.escopos <> '' THEN EXCLUDED.escopos ELSE integracoes_usuario.escopos END,
            metadados =
                (CASE WHEN jsonb_typeof(integracoes_usuario.metadados) = 'object' THEN integracoes_usuario.metadados
                      WHEN jsonb_typeof(integracoes_usuario.metadados) = 'array' AND jsonb_array_length(integracoes_usuario.metadados) > 0 THEN jsonb_build_object('_legacy_array', integracoes_usuario.metadados)
                      ELSE '{}'::jsonb END)
                || EXCLUDED.metadados,
            ultimo_erro = NULL, atualizado_em = NOW()
        RETURNING *"
    );
    $stmt->execute([
        ':atividades' => in_array('activities_in', $provider['capabilities'] ?? [], true) ? 1 : 0,
        ':id' => stridebr_integrations_id(), ':usuario' => $userId, ':provedor' => $providerId,
        ':externo' => $externalId, ':nome' => $externalName,
        ':access' => stridebr_integrations_encrypt((string) $token['access_token']),
        ':refresh' => stridebr_integrations_encrypt(trim((string) ($token['refresh_token'] ?? '')) ?: null),
        ':expira' => $expiresAt, ':escopos' => $scope,
        ':metadados' => $metadata === [] ? '{}' : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ]);
    return $stmt->fetch() ?: [];
}

function stridebr_integrations_disconnect(PDO $pdo, string $userId, string $providerId): void
{
    $connection = stridebr_integrations_get($pdo, $userId, $providerId);
    if ($providerId === 'strava' && is_array($connection)) {
        $strava = stridebr_integrations_provider('strava');
        $refreshToken = stridebr_integrations_decrypt((string) ($connection['refresh_token_enc'] ?? ''));
        $token = $refreshToken ?: stridebr_integrations_decrypt((string) ($connection['access_token_enc'] ?? ''));
        if ($token) {
            try {
                $body = ['token' => $token];
                if ($refreshToken) $body['token_type_hint'] = 'refresh_token';
                stridebr_integrations_http('POST', 'https://www.strava.com/oauth/revoke', [
                    'headers' => ['Authorization: Basic ' . base64_encode((string) $strava['client_id'] . ':' . (string) $strava['client_secret']), 'Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
                    'body' => $body,
                ]);
            } catch (Throwable) {
            }
        }
    }
    $stmt = $pdo->prepare("UPDATE integracoes_usuario SET status = 'revogado', access_token_enc = NULL, refresh_token_enc = NULL, token_expira_em = NULL, ultimo_erro = NULL, atualizado_em = NOW() WHERE idusuario = :usuario AND provedor = :provedor");
    $stmt->execute([':usuario' => $userId, ':provedor' => $providerId]);
}

function stridebr_integrations_update_preferences(PDO $pdo, string $userId, string $providerId, array $input): void
{
    $provider = stridebr_integrations_provider($providerId);
    $showProfile = !empty($input['show_profile']) && !empty($provider['profile_link']);
    $profileUrl = trim((string) ($input['profile_url'] ?? ''));
    if ($profileUrl !== '' && (filter_var($profileUrl, FILTER_VALIDATE_URL) === false || !in_array(strtolower((string) parse_url($profileUrl, PHP_URL_SCHEME)), ['http', 'https'], true))) {
        throw new InvalidArgumentException('Use uma URL pública válida para o perfil conectado.');
    }
    if (!$showProfile) $profileUrl = '';
    $stmt = $pdo->prepare('UPDATE integracoes_usuario SET sincronizar_atividades = :atividades, sincronizar_treinos = :treinos, mostrar_perfil = :mostrar, perfil_publico_url = :url, atualizado_em = NOW() WHERE idusuario = :usuario AND provedor = :provedor');
    $stmt->execute([
        ':atividades' => !empty($input['sync_activities']) ? 1 : 0,
        ':treinos' => !empty($input['sync_workouts']) ? 1 : 0,
        ':mostrar' => $showProfile ? 1 : 0,
        ':url' => $profileUrl !== '' ? $profileUrl : null,
        ':usuario' => $userId,
        ':provedor' => $providerId,
    ]);
}

function stridebr_integrations_iso_duration_seconds(string $value): ?int
{
    try {
        $interval = new DateInterval($value);
        return (int) round(($interval->d * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s + ($interval->f ?? 0));
    } catch (Throwable) {
        return null;
    }
}

function stridebr_integrations_polyline(string $encoded): array
{
    $points = [];
    $index = 0;
    $lat = 0;
    $lng = 0;
    $length = strlen($encoded);
    while ($index < $length) {
        $result = 0;
        $shift = 0;
        do {
            if ($index >= $length) return $points;
            $byte = ord($encoded[$index++]) - 63;
            $result |= ($byte & 0x1f) << $shift;
            $shift += 5;
        } while ($byte >= 0x20);
        $lat += ($result & 1) ? ~($result >> 1) : ($result >> 1);
        $result = 0;
        $shift = 0;
        do {
            if ($index >= $length) return $points;
            $byte = ord($encoded[$index++]) - 63;
            $result |= ($byte & 0x1f) << $shift;
            $shift += 5;
        } while ($byte >= 0x20);
        $lng += ($result & 1) ? ~($result >> 1) : ($result >> 1);
        $points[] = [$lng / 1e5, $lat / 1e5];
    }
    return $points;
}

function stridebr_integrations_sport_slug(string $providerId, string $sport): string
{
    $key = strtoupper(trim($sport));
    $maps = [
        'strava' => [
            'RUN' => 'corrida', 'TRAILRUN' => 'corrida-em-trilha', 'VIRTUALRUN' => 'corrida-em-esteira', 'WALK' => 'caminhada', 'HIKE' => 'trilha',
            'RIDE' => 'ciclismo', 'MOUNTAINBIKERIDE' => 'mountain-bike', 'GRAVELRIDE' => 'gravel', 'VIRTUALRIDE' => 'ciclismo-indoor', 'EBIKERIDE' => 'bicicleta-eletrica',
            'SWIM' => 'natacao', 'ROWING' => 'remo', 'YOGA' => 'yoga', 'WEIGHTTRAINING' => 'musculacao', 'WORKOUT' => 'treino-funcional',
            'TENNIS' => 'tenis', 'BADMINTON' => 'badminton', 'PICKLEBALL' => 'pickleball', 'SOCCER' => 'futebol',
        ],
        'polar' => [
            'RUNNING' => 'corrida', 'ROAD_RUNNING' => 'corrida', 'TRAIL_RUNNING' => 'corrida-em-trilha', 'TREADMILL_RUNNING' => 'corrida-em-esteira', 'WALKING' => 'caminhada', 'HIKING' => 'trilha',
            'CYCLING' => 'ciclismo', 'ROAD_BIKING' => 'ciclismo', 'MOUNTAIN_BIKING' => 'mountain-bike', 'INDOOR_CYCLING' => 'ciclismo-indoor',
            'SWIMMING' => 'natacao', 'POOL_SWIMMING' => 'natacao', 'OPEN_WATER_SWIMMING' => 'natacao', 'ROWING' => 'remo',
            'STRENGTH_TRAINING' => 'musculacao', 'WEIGHT_TRAINING' => 'musculacao', 'CALISTHENICS' => 'calistenia', 'CIRCUIT_TRAINING' => 'treino-funcional',
            'TENNIS' => 'tenis', 'BADMINTON' => 'badminton', 'BEACH_TENNIS' => 'beach-tennis', 'YOGA' => 'yoga', 'BOXING' => 'boxe', 'BASKETBALL' => 'basquete', 'VOLLEYBALL' => 'volei',
        ],
        'google_health' => [
            'RUNNING' => 'corrida', 'RUN' => 'corrida', 'TREADMILL_RUNNING' => 'corrida-em-esteira', 'WALKING' => 'caminhada', 'HIKING' => 'trilha',
            'BIKING' => 'ciclismo', 'CYCLING' => 'ciclismo', 'MOUNTAIN_BIKING' => 'mountain-bike', 'INDOOR_CYCLING' => 'ciclismo-indoor',
            'SWIMMING' => 'natacao', 'ROWING' => 'remo', 'STRENGTH_TRAINING' => 'musculacao', 'WEIGHT_TRAINING' => 'musculacao', 'YOGA' => 'yoga',
        ],
        'coros' => [
            'RUNNING' => 'corrida', 'TRAIL_RUNNING' => 'corrida-em-trilha', 'TREADMILL' => 'corrida-em-esteira', 'WALKING' => 'caminhada', 'HIKING' => 'trilha',
            'CYCLING' => 'ciclismo', 'MOUNTAIN_BIKING' => 'mountain-bike', 'INDOOR_CYCLING' => 'ciclismo-indoor', 'SWIMMING' => 'natacao',
            'ROWING' => 'remo', 'STRENGTH_TRAINING' => 'musculacao', 'YOGA' => 'yoga',
        ],
        'suunto' => [
            'RUNNING' => 'corrida', 'RUN' => 'corrida', 'TRAIL RUNNING' => 'corrida-em-trilha', 'TREADMILL' => 'corrida-em-esteira', 'WALKING' => 'caminhada', 'HIKING' => 'trilha', 'TREKKING' => 'trekking',
            'CYCLING' => 'ciclismo', 'ROAD CYCLING' => 'ciclismo-de-estrada', 'MOUNTAIN BIKING' => 'mountain-bike', 'INDOOR CYCLING' => 'ciclismo-indoor',
            'SWIMMING' => 'natacao', 'POOL SWIMMING' => 'natacao-em-piscina', 'OPENWATER SWIMMING' => 'natacao-aguas-abertas', 'ROWING' => 'remo',
            'STRENGTH TRAINING' => 'musculacao', 'CIRCUIT TRAINING' => 'treino-funcional', 'YOGA' => 'yoga', 'TENNIS' => 'tenis', 'FOOTBALL' => 'futebol',
        ],
    ];
    if (isset($maps[$providerId][$key])) return $maps[$providerId][$key];
    if (in_array($providerId, ['google_health', 'coros', 'suunto'], true)) {
        $normalized = stridebr_slug($sport);
        if (str_contains($normalized, 'run') || str_contains($normalized, 'corrid')) return 'corrida';
        if (str_contains($normalized, 'walk') || str_contains($normalized, 'caminh')) return 'caminhada';
        if (str_contains($normalized, 'hike') || str_contains($normalized, 'trilh')) return 'trilha';
        if (str_contains($normalized, 'bike') || str_contains($normalized, 'cycl') || str_contains($normalized, 'cicl')) return 'ciclismo';
        if (str_contains($normalized, 'swim') || str_contains($normalized, 'nat')) return 'natacao';
        if (str_contains($normalized, 'weight') || str_contains($normalized, 'strength') || str_contains($normalized, 'muscul')) return 'musculacao';
        if (str_contains($normalized, 'tennis') || str_contains($normalized, 'tenis')) return 'tenis';
    }
    return 'outra-atividade';
}

function stridebr_integrations_duplicate(PDO $pdo, string $userId, string $providerId, string $externalId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM registros_atividade WHERE idusuario = :usuario AND origem_provedor = :provedor AND id_externo = :externo AND excluido_em IS NULL LIMIT 1');
    $stmt->execute([':usuario' => $userId, ':provedor' => $providerId, ':externo' => $externalId]);
    return (bool) $stmt->fetchColumn();
}

function stridebr_integrations_store_activity(PDO $pdo, string $userId, string $providerId, array $activity): ?string
{
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    else $pdo->exec('SAVEPOINT integration_activity');
    $stage = 'deduplication';
    try {
        // Works across processes/containers; identity and activity commit together.
        $lock = $pdo->prepare('SELECT pg_advisory_xact_lock(hashtextextended(:key, 0))');
        $lock->execute([':key' => 'activity:' . $userId . ':' . $providerId . ':' . (string) ($activity['external_id'] ?? '')]);
        $id = stridebr_integrations_store_activity_data($pdo, $userId, $providerId, $activity, $stage);
        if ($ownsTransaction) $pdo->commit();
        else $pdo->exec('RELEASE SAVEPOINT integration_activity');
        return $id;
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        elseif ($pdo->inTransaction()) {
            $pdo->exec('ROLLBACK TO SAVEPOINT integration_activity');
            $pdo->exec('RELEASE SAVEPOINT integration_activity');
        }
        throw new StridebrIntegrationError($stage, str_starts_with($stage, 'persistence.') ? 'persistence_failed' : 'activity_invalid', previous: $error);
    }
}

function stridebr_integrations_store_activity_data(PDO $pdo, string $userId, string $providerId, array $activity, string &$stage): ?string
{
    require_once __DIR__ . '/atividade_modelo.php';
    require_once __DIR__ . '/activity_file_exchange.php';
    $stage = 'deduplication';
    $externalId = trim((string) ($activity['external_id'] ?? ''));
    if ($externalId === '') throw new StridebrIntegrationError('normalization.identity', 'activity_invalid');
    if (stridebr_integrations_duplicate($pdo, $userId, $providerId, $externalId)) return null;
    $stage = 'sport_mapping';
    $slug = stridebr_integrations_sport_slug($providerId, (string) ($activity['sport'] ?? ''));
    $modalidade = atividadeArquivoModalidade($pdo, $userId, $slug);
    $summary = [
        'duration_s' => $activity['duration_s'] ?? null,
        'distance_m' => $activity['distance_m'] ?? null,
        'elevation_gain_m' => $activity['elevation_gain_m'] ?? null,
        'avg_hr' => $activity['avg_hr'] ?? null,
        'max_hr' => $activity['max_hr'] ?? null,
        'avg_cadence' => $activity['avg_cadence'] ?? null,
        'avg_power' => $activity['avg_power'] ?? null,
        'calories' => $activity['calories'] ?? null,
    ];
    $stage = 'normalization.metrics';
    $fields = atividadeArquivoPayloadCampos($pdo, (string) $modalidade['idmodelo'], $summary);
    $stage = 'normalization.datetime';
    if (empty($activity['start_at'])) throw new InvalidArgumentException('Missing start time.');
    $start = new DateTimeImmutable((string) $activity['start_at']);
    $duration = is_numeric($activity['duration_s'] ?? null) ? max(0, (int) round((float) $activity['duration_s'])) : null;
    $end = $duration !== null ? $start->modify('+' . $duration . ' seconds') : null;
    $defaults = atividadePadroesUsuario($pdo, $userId);
    $title = trim((string) ($activity['title'] ?? '')) ?: (string) $modalidade['nome'];
    $payload = [
        'idmodelo' => (string) $modalidade['idmodelo'],
        'titulo' => $title,
        'observacoes' => trim((string) ($activity['notes'] ?? '')),
        'data_inicio' => $start->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d H:i'),
        'data_fim' => $end ? $end->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d H:i') : '',
        'status' => 'concluido',
        'visibilidade' => (string) ($defaults['visibility'] ?? 'privado'),
        'origem' => 'api',
        'record_values' => $fields['record_values'],
        'unidades' => $fields['unidades'],
        'permitir_campos_vazios' => true,
        'calorias_externas' => is_numeric($activity['calories'] ?? null) ? (float) $activity['calories'] : null,
        'fonte_calorias_externa' => $providerId,
    ];
    $route = is_array($activity['route'] ?? null) ? $activity['route'] : [];
    if (count($route) >= 2) {
        $payload['rota_coordenadas'] = ['type' => 'LineString', 'coordinates' => $route];
        $payload['rota_modo'] = 'importada';
        $payload['rota_metricas'] = [
            'distancia_metros' => is_numeric($activity['distance_m'] ?? null) ? (float) $activity['distance_m'] : null,
            'ganho_elevacao_m' => is_numeric($activity['elevation_gain_m'] ?? null) ? (float) $activity['elevation_gain_m'] : null,
            'fonte_elevacao' => strtoupper($providerId),
        ];
    }
    $stage = 'persistence.activity';
    $id = atividadeSalvarRegistro($pdo, $userId, $payload);
    $device = is_array($activity['device'] ?? null) ? $activity['device'] : [];
    if (is_numeric($activity['moving_time_s'] ?? null)) $device['moving_time_s'] = (float) $activity['moving_time_s'];
    if (trim((string) ($activity['timezone'] ?? '')) !== '') $device['timezone'] = trim((string) $activity['timezone']);
    $stage = 'persistence.external_identity';
    $update = $pdo->prepare('UPDATE registros_atividade SET origem_provedor = :provedor, id_externo = :externo, dispositivo_origem = CAST(:device AS jsonb), data_inicio = :inicio, data_fim = :fim, data_atualizacao = NOW() WHERE idregistro = :registro AND idusuario = :usuario');
    $update->execute([
        ':inicio' => $start->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d H:i:s'),
        ':fim' => $end?->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d H:i:s'),
        ':provedor' => $providerId,
        ':externo' => $externalId,
        ':device' => json_encode($device, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':registro' => $id,
        ':usuario' => $userId,
    ]);
    return $id;
}

function stridebr_integrations_result(): array
{
    return ['created' => 0, 'existing' => 0, 'failed' => 0];
}

function stridebr_integrations_token_expiring(array $connection, int $margin = 120): bool
{
    if (empty($connection['token_expira_em'])) return false;
    $expires = strtotime((string) $connection['token_expira_em']);
    return $expires !== false && $expires <= time() + $margin;
}

function stridebr_integrations_refresh(PDO $pdo, string $userId, string $providerId, array $connection): array
{
    if (!stridebr_integrations_token_expiring($connection)) return $connection;
    $refresh = stridebr_integrations_decrypt((string) ($connection['refresh_token_enc'] ?? ''));
    if (!$refresh) throw new StridebrIntegrationError('token', 'reauthorize');
    $provider = stridebr_integrations_provider($providerId);
    $headers = ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'];
    $fields = ['grant_type' => 'refresh_token', 'refresh_token' => $refresh];
    $tokenUrl = trim((string) ($provider['token_url'] ?? ''));
    if ($providerId === 'coros') {
        $meta = stridebr_integrations_metadata($connection);
        $oauth = is_array($meta['oauth'] ?? null) ? $meta['oauth'] : [];
        $tokenUrl = trim((string) ($oauth['token_url'] ?? ''));
        $clientId = trim((string) ($oauth['client_id'] ?? ''));
        if (!stridebr_integrations_https_url($tokenUrl) || $clientId === '') throw new RuntimeException('Reautorize a COROS para atualizar a configuração OAuth.');
        $fields['client_id'] = $clientId;
        $mcpUrl = trim((string) ($oauth['mcp_url'] ?? $provider['mcp_url'] ?? ''));
        if ($mcpUrl !== '') $fields['resource'] = $mcpUrl;
        $secret = stridebr_integrations_decrypt((string) ($oauth['client_secret_enc'] ?? ''));
        if ($secret && in_array((string) ($oauth['token_auth'] ?? ''), ['client_secret_basic', 'basic'], true)) $headers[] = 'Authorization: Basic ' . base64_encode($clientId . ':' . $secret);
        elseif ($secret) $fields['client_secret'] = $secret;
    } elseif (($provider['token_auth'] ?? 'basic') === 'body') {
        $fields['client_id'] = $provider['client_id'];
        $fields['client_secret'] = $provider['client_secret'];
    } else {
        $headers[] = 'Authorization: Basic ' . base64_encode((string) $provider['client_id'] . ':' . (string) $provider['client_secret']);
    }
    $response = stridebr_integrations_http('POST', $tokenUrl, ['headers' => $headers, 'body' => $fields]);
    $token = $response['json'];
    if (!is_array($token) || trim((string) ($token['access_token'] ?? '')) === '') throw new RuntimeException('Não foi possível renovar a conexão com ' . $provider['label'] . '.');
    if ($providerId === 'coros') $token['_stridebr_meta'] = ['token_url' => $tokenUrl] + (is_array($oauth ?? null) ? $oauth : []);
    return stridebr_integrations_save_token($pdo, $userId, $providerId, $token);
}

function stridebr_integrations_connection_token(PDO $pdo, string $userId, string $providerId, array $connection): array
{
    $connection = stridebr_integrations_refresh($pdo, $userId, $providerId, $connection);
    $token = stridebr_integrations_decrypt((string) ($connection['access_token_enc'] ?? ''));
    if (!$token) throw new StridebrIntegrationError('token', 'reauthorize');
    return [$connection, $token];
}

function stridebr_integrations_parsed_activity(array $parsed, string $externalId, string $fallbackTitle = '', array $device = []): array
{
    $summary = is_array($parsed['summary'] ?? null) ? $parsed['summary'] : [];
    $startTs = is_numeric($summary['start_ts'] ?? null) ? (float) $summary['start_ts'] : null;
    if ($startTs === null) throw new RuntimeException('A atividade externa não possui horário inicial utilizável.');
    return [
        'external_id' => $externalId,
        'title' => trim((string) ($parsed['title'] ?? '')) ?: $fallbackTitle,
        'sport' => (string) ($parsed['sport'] ?? 'generic'),
        'start_at' => (new DateTimeImmutable('@' . (int) floor($startTs)))->format(DateTimeInterface::ATOM),
        'duration_s' => $summary['duration_s'] ?? null,
        'distance_m' => $summary['distance_m'] ?? null,
        'elevation_gain_m' => $summary['elevation_gain_m'] ?? null,
        'avg_hr' => $summary['avg_hr'] ?? null,
        'max_hr' => $summary['max_hr'] ?? null,
        'avg_cadence' => $summary['avg_cadence'] ?? null,
        'avg_power' => $summary['avg_power'] ?? null,
        'calories' => $summary['calories'] ?? null,
        'route' => is_array($summary['coordinates'] ?? null) ? $summary['coordinates'] : [],
        'device' => $device + (is_array($parsed['device'] ?? null) ? $parsed['device'] : []),
    ];
}

function stridebr_integrations_strava_rate_low(array $response): bool
{
    foreach ([['x-ratelimit-usage', 'x-ratelimit-limit'], ['x-readratelimit-usage', 'x-readratelimit-limit']] as [$usageHeader, $limitHeader]) {
        $usage = stridebr_integrations_header_first($response, $usageHeader);
        $limit = stridebr_integrations_header_first($response, $limitHeader);
        if ($usage === null || $limit === null) continue;
        $used = array_map('intval', array_map('trim', explode(',', $usage)));
        $caps = array_map('intval', array_map('trim', explode(',', $limit)));
        foreach ($used as $index => $count) {
            $cap = $caps[$index] ?? 0;
            if ($cap > 0 && $count >= max(1, $cap - 5)) return true;
        }
    }
    return false;
}

function stridebr_integrations_normalize_strava(array $data): array
{
    if (empty($data['id']) || !is_scalar($data['id']) || empty($data['start_date']) || !is_string($data['start_date'])) {
        throw new StridebrIntegrationError('normalization.datetime', 'activity_invalid');
    }
    try { new DateTimeImmutable($data['start_date']); }
    catch (Throwable $error) { throw new StridebrIntegrationError('normalization.datetime', 'activity_invalid', previous: $error); }
    $route = [];
    $map = is_array($data['map'] ?? null) ? $data['map'] : [];
    $polyline = trim((string) ($map['polyline'] ?? '')) ?: trim((string) ($map['summary_polyline'] ?? ''));
    if ($polyline !== '') {
        $route = stridebr_integrations_polyline($polyline);
        foreach ($route as [$lon, $lat]) {
            if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) throw new StridebrIntegrationError('normalization.route', 'invalid_route');
        }
    }
    $device = ['source' => 'Strava'];
    if (is_string($data['device_name'] ?? null) && trim($data['device_name']) !== '') $device['name'] = trim($data['device_name']);
    if (is_array($data['laps'] ?? null)) {
        $device['laps'] = array_map(static fn(array $lap): array => array_intersect_key($lap, array_flip(['id', 'name', 'elapsed_time', 'moving_time', 'distance', 'total_elevation_gain', 'average_speed', 'average_heartrate', 'average_cadence', 'average_watts'])), array_slice(array_values(array_filter($data['laps'], 'is_array')), 0, 100));
    }
    $activity = [
        'external_id' => (string) $data['id'], 'title' => trim((string) ($data['name'] ?? '')),
        'sport' => (string) ($data['sport_type'] ?? $data['type'] ?? ''), 'start_at' => $data['start_date'],
        'duration_s' => $data['elapsed_time'] ?? $data['moving_time'] ?? null, 'moving_time_s' => $data['moving_time'] ?? null,
        'distance_m' => $data['distance'] ?? null, 'elevation_gain_m' => $data['total_elevation_gain'] ?? null,
        'avg_hr' => $data['average_heartrate'] ?? null, 'max_hr' => $data['max_heartrate'] ?? null,
        'avg_cadence' => $data['average_cadence'] ?? null, 'avg_power' => $data['average_watts'] ?? null,
        'calories' => $data['calories'] ?? null, 'route' => $route, 'device' => $device,
        'timezone' => is_string($data['timezone'] ?? null) ? $data['timezone'] : '',
    ];
    foreach (['duration_s', 'moving_time_s', 'distance_m', 'elevation_gain_m', 'avg_hr', 'max_hr', 'avg_cadence', 'avg_power', 'calories'] as $key) {
        if ($activity[$key] !== null && (!is_numeric($activity[$key]) || !is_finite((float) $activity[$key]) || (float) $activity[$key] < 0)) {
            throw new StridebrIntegrationError('normalization.metrics', 'invalid_metric');
        }
    }
    return $activity;
}

function stridebr_integrations_sync_strava_recent(PDO $pdo, string $userId, array $connection): array
{
    $stage = 'token';
    try {
        $trigger = (string) ($connection['_sync_trigger'] ?? 'manual');
        $initial = $trigger === 'initial';
        $manual = $trigger === 'manual';
        [$connection, $token] = stridebr_integrations_connection_token($pdo, $userId, 'strava', $connection);
        $provider = stridebr_integrations_provider('strava');
        $after = !empty($connection['ultima_sincronizacao_em']) ? max(0, strtotime((string) $connection['ultima_sincronizacao_em']) - 172800) : time() - 2592000;
        $headers = ['Authorization: Bearer ' . $token, 'Accept: application/json'];
        $result = stridebr_integrations_result();
        $deadline = microtime(true) + ($initial ? 5 : ($manual ? 12 : 30));
        $detailBudget = $initial ? 1 : ($manual ? 4 : 10);
        // Bound work per invocation. A deferred run keeps its checkpoint so older pages are retried.
        for ($page = 1; $page <= 10; $page++) {
            $stage = 'listing';
            $url = $provider['api_base_url'] . '/athlete/activities?' . http_build_query(['after' => $after, 'page' => $page, 'per_page' => 100], '', '&', PHP_QUERY_RFC3986);
            $response = stridebr_integrations_http('GET', $url, ['headers' => $headers, 'timeout' => max(1, min(20, (int) ceil($deadline - microtime(true))))]);
            if (!is_array($response['json']) || !array_is_list($response['json'])) throw new StridebrIntegrationError('listing', 'invalid_response', $response['status']);
            $items = $response['json'];
            if (stridebr_integrations_strava_rate_low($response)) return $result + ['deferred' => true, 'rate_limited' => true, 'retry_after' => stridebr_integrations_strava_retry($response)];
            foreach ($items as $item) {
                $externalId = is_array($item) && is_scalar($item['id'] ?? null) ? (string) $item['id'] : null;
                $stage = 'deduplication';
                try {
                    if ($externalId === null) throw new StridebrIntegrationError('normalization.identity', 'activity_invalid');
                    if (stridebr_integrations_duplicate($pdo, $userId, 'strava', $externalId)) { $result['existing']++; continue; }
                    if ($detailBudget <= 0 || microtime(true) >= $deadline) return $result + ['deferred' => true, 'retry_after' => 900];
                    $stage = 'detail';
                    $detailBudget--;
                    $detail = stridebr_integrations_http('GET', $provider['api_base_url'] . '/activities/' . rawurlencode($externalId), ['headers' => $headers, 'timeout' => max(1, min(20, (int) ceil($deadline - microtime(true))))]);
                    if (!is_array($detail['json']) || (string) ($detail['json']['id'] ?? '') !== $externalId) throw new StridebrIntegrationError('detail', 'invalid_response', $detail['status']);
                    $stage = 'normalization';
                    $activity = stridebr_integrations_normalize_strava($detail['json']);
                    $stage = 'persistence';
                    $saved = stridebr_integrations_store_activity($pdo, $userId, 'strava', $activity);
                    if ($saved !== null) $result['created']++; else $result['existing']++;
                    if (stridebr_integrations_strava_rate_low($detail)) return $result + ['deferred' => true, 'rate_limited' => true, 'retry_after' => stridebr_integrations_strava_retry($detail)];
                } catch (Throwable $error) {
                    stridebr_integrations_log_failure('strava', $stage, $externalId, $error);
                    $result['failed']++;
                    if ($error instanceof StridebrIntegrationError) {
                        $result['error_code'] = $error->internalCode;
                        if ($error->internalCode === 'reauthorize') return $result + ['reauthorize' => true];
                        if ($error->httpStatus === 429) return $result + ['deferred' => true, 'rate_limited' => true, 'retry_after' => max(900, $error->retryAfter)];
                    }
                }
            }
            if (count($items) < 100) return $result;
        }
        return $result + ['deferred' => true, 'retry_after' => 900];
    } catch (Throwable $error) {
        if ($error instanceof StridebrIntegrationError) throw new StridebrIntegrationError($stage, $error->internalCode, $error->httpStatus, $error->retryAfter, $error);
        throw new StridebrIntegrationError($stage, 'provider_failed', previous: $error);
    }
}


function stridebr_integrations_strava_backfill_write(PDO $pdo, string $userId, array $state): void
{
    $stmt = $pdo->prepare("UPDATE integracoes_usuario SET metadados = jsonb_set(CASE WHEN jsonb_typeof(metadados) = 'object' THEN metadados WHEN jsonb_typeof(metadados) = 'array' AND jsonb_array_length(metadados) > 0 THEN jsonb_build_object('_legacy_array', metadados) ELSE '{}'::jsonb END, '{backfill}', CAST(:state AS jsonb), true), atualizado_em = NOW() WHERE idusuario = :user AND provedor = 'strava'");
    $stmt->execute([':state' => json_encode($state, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ':user' => $userId]);
}

function stridebr_integrations_strava_backfill_initial_state(PDO $pdo, string $userId): array
{
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total, MIN(data_inicio) AS oldest FROM registros_atividade WHERE idusuario = :user AND origem_provedor = 'strava' AND excluido_em IS NULL");
    $stmt->execute([':user' => $userId]);
    $row = $stmt->fetch() ?: [];
    $oldest = trim((string) ($row['oldest'] ?? ''));
    $oldestTs = $oldest !== '' ? strtotime($oldest) : false;
    return [
        'status' => 'pending',
        'before' => $oldestTs !== false ? max(1, $oldestTs) : time() + 60,
        'oldest_at' => $oldest !== '' ? (new DateTimeImmutable($oldest))->format(DateTimeInterface::ATOM) : null,
        'started_at' => null,
        'last_attempt_at' => null,
        'last_progress_at' => null,
        'retry_at' => 0,
        'completed_at' => null,
        'created' => 0,
        'existing' => 0,
        'failed' => 0,
    ];
}

function stridebr_integrations_strava_backfill_state(PDO $pdo, string $userId, array $connection, bool $persist = false): array
{
    $meta = stridebr_integrations_metadata($connection);
    $state = is_array($meta['backfill'] ?? null) && !array_is_list($meta['backfill']) ? $meta['backfill'] : [];
    if ($state === []) {
        $state = stridebr_integrations_strava_backfill_initial_state($pdo, $userId);
        if ($persist) stridebr_integrations_strava_backfill_write($pdo, $userId, $state);
    }
    return $state;
}

function stridebr_integrations_strava_backfill_is_due(array $state, ?int $now = null): bool
{
    $now ??= time();
    if (($state['status'] ?? 'pending') === 'completed') return false;
    return (int) ($state['retry_at'] ?? 0) <= $now;
}

function stridebr_integrations_strava_backfill_step(PDO $pdo, string $userId, array $connection): array
{
    $state = stridebr_integrations_strava_backfill_state($pdo, $userId, $connection, true);
    if (!stridebr_db_bool($connection['sincronizar_atividades'] ?? true)) return stridebr_integrations_result() + ['backfill_skipped' => 'disabled'];
    if (!stridebr_integrations_strava_backfill_is_due($state)) return stridebr_integrations_result() + ['backfill_skipped' => 'not_due'];

    $now = time();
    $state['status'] = 'running';
    $state['started_at'] ??= $now;
    $state['last_attempt_at'] = $now;
    $state['retry_at'] = 0;
    stridebr_integrations_strava_backfill_write($pdo, $userId, $state);

    $result = stridebr_integrations_result();
    $perPage = 10;
    $deadline = microtime(true) + 12;
    $detailBudget = 3;
    try {
        [$connection, $token] = stridebr_integrations_connection_token($pdo, $userId, 'strava', $connection);
        $provider = stridebr_integrations_provider('strava');
        $headers = ['Authorization: Bearer ' . $token, 'Accept: application/json'];
        $before = max(1, (int) ($state['before'] ?? ($now + 60)));
        $url = $provider['api_base_url'] . '/athlete/activities?' . http_build_query(['before' => $before, 'page' => 1, 'per_page' => $perPage], '', '&', PHP_QUERY_RFC3986);
        $listing = stridebr_integrations_http('GET', $url, ['headers' => $headers, 'timeout' => 20]);
        if (!is_array($listing['json']) || !array_is_list($listing['json'])) throw new StridebrIntegrationError('backfill.listing', 'invalid_response', $listing['status'] ?? null);
        if (stridebr_integrations_strava_rate_low($listing)) {
            $retry = stridebr_integrations_strava_retry($listing);
            $state['status'] = 'paused';
            $state['retry_at'] = $now + $retry;
            $state['pause_reason'] = 'rate_limit';
            stridebr_integrations_strava_backfill_write($pdo, $userId, $state);
            return $result + ['backfill_paused' => true, 'rate_limited' => true, 'retry_after' => $retry];
        }
        $items = $listing['json'];
        if ($items === []) {
            $state['status'] = 'completed';
            $state['completed_at'] = $now;
            $state['retry_at'] = 0;
            unset($state['pause_reason'], $state['error_code']);
            stridebr_integrations_strava_backfill_write($pdo, $userId, $state);
            return $result + ['backfill_completed' => true];
        }

        $oldestTs = null;
        $batchFailed = false;
        foreach ($items as $item) {
            $externalId = is_array($item) && is_scalar($item['id'] ?? null) ? (string) $item['id'] : '';
            $startRaw = is_array($item) ? trim((string) ($item['start_date'] ?? '')) : '';
            $startTs = $startRaw !== '' ? strtotime($startRaw) : false;
            if ($externalId === '' || $startTs === false) {
                $result['failed']++;
                $batchFailed = true;
                continue;
            }
            $oldestTs = $oldestTs === null ? $startTs : min($oldestTs, $startTs);
            try {
                if (stridebr_integrations_duplicate($pdo, $userId, 'strava', $externalId)) {
                    $result['existing']++;
                    continue;
                }
                if ($detailBudget <= 0 || microtime(true) >= $deadline) {
                    $state['status'] = 'pending';
                    $state['retry_at'] = $now + 900;
                    $state['created'] = (int) ($state['created'] ?? 0) + (int) $result['created'];
                    $state['existing'] = (int) ($state['existing'] ?? 0) + (int) $result['existing'];
                    $state['failed'] = (int) ($state['failed'] ?? 0) + (int) $result['failed'];
                    stridebr_integrations_strava_backfill_write($pdo, $userId, $state);
                    return $result + ['backfill_pending' => true, 'backfill_deferred' => true];
                }
                $detailBudget--;
                $detail = stridebr_integrations_http('GET', $provider['api_base_url'] . '/activities/' . rawurlencode($externalId), ['headers' => $headers, 'timeout' => max(1, min(20, (int) ceil($deadline - microtime(true))))]);
                if (!is_array($detail['json']) || (string) ($detail['json']['id'] ?? '') !== $externalId) throw new StridebrIntegrationError('backfill.detail', 'invalid_response', $detail['status'] ?? null);
                $activity = stridebr_integrations_normalize_strava($detail['json']);
                $saved = stridebr_integrations_store_activity($pdo, $userId, 'strava', $activity);
                if ($saved !== null) $result['created']++; else $result['existing']++;
                if (stridebr_integrations_strava_rate_low($detail)) {
                    $retry = stridebr_integrations_strava_retry($detail);
                    $state['status'] = 'paused';
                    $state['retry_at'] = $now + $retry;
                    $state['pause_reason'] = 'rate_limit';
                    $state['created'] = (int) ($state['created'] ?? 0) + (int) $result['created'];
                    $state['existing'] = (int) ($state['existing'] ?? 0) + (int) $result['existing'];
                    stridebr_integrations_strava_backfill_write($pdo, $userId, $state);
                    return $result + ['backfill_paused' => true, 'rate_limited' => true, 'retry_after' => $retry];
                }
            } catch (Throwable $error) {
                stridebr_integrations_log_failure('strava', 'backfill', $externalId, $error);
                $result['failed']++;
                $batchFailed = true;
                if ($error instanceof StridebrIntegrationError && $error->internalCode === 'reauthorize') {
                    $state['status'] = 'retry';
                    $state['retry_at'] = 0;
                    $state['error_code'] = 'reauthorize';
                    stridebr_integrations_strava_backfill_write($pdo, $userId, $state);
                    return $result + ['reauthorize' => true, 'hard_failed' => true];
                }
                if ($error instanceof StridebrIntegrationError && $error->httpStatus === 429) {
                    $retry = max(900, $error->retryAfter);
                    $state['status'] = 'paused';
                    $state['retry_at'] = $now + $retry;
                    $state['pause_reason'] = 'rate_limit';
                    stridebr_integrations_strava_backfill_write($pdo, $userId, $state);
                    return $result + ['backfill_paused' => true, 'rate_limited' => true, 'retry_after' => $retry];
                }
            }
        }

        $state['created'] = (int) ($state['created'] ?? 0) + (int) $result['created'];
        $state['existing'] = (int) ($state['existing'] ?? 0) + (int) $result['existing'];
        $state['failed'] = (int) ($state['failed'] ?? 0) + (int) $result['failed'];
        if ($batchFailed || $oldestTs === null) {
            $state['status'] = 'retry';
            $state['retry_at'] = $now + 900;
            $state['error_code'] = 'activity_retry';
            stridebr_integrations_strava_backfill_write($pdo, $userId, $state);
            return $result + ['backfill_retry' => true, 'backfill_only_failure' => true];
        }

        $state['before'] = max(1, $oldestTs - 1);
        $state['oldest_at'] = (new DateTimeImmutable('@' . $oldestTs))->format(DateTimeInterface::ATOM);
        $state['last_progress_at'] = $now;
        unset($state['pause_reason'], $state['error_code']);
        if (count($items) < $perPage) {
            $state['status'] = 'completed';
            $state['completed_at'] = $now;
            $state['retry_at'] = 0;
        } else {
            $state['status'] = 'pending';
            $state['retry_at'] = $now + 900;
        }
        stridebr_integrations_strava_backfill_write($pdo, $userId, $state);
        return $result + ['backfill_completed' => $state['status'] === 'completed', 'backfill_pending' => $state['status'] !== 'completed'];
    } catch (Throwable $error) {
        stridebr_integrations_log_failure('strava', 'backfill', null, $error);
        if ($error instanceof StridebrIntegrationError && $error->internalCode === 'reauthorize') {
            $state['status'] = 'retry';
            $state['retry_at'] = 0;
            $state['error_code'] = 'reauthorize';
            stridebr_integrations_strava_backfill_write($pdo, $userId, $state);
            return $result + ['failed' => 1, 'reauthorize' => true, 'hard_failed' => true];
        }
        $state['status'] = 'retry';
        $state['retry_at'] = $now + max(900, $error instanceof StridebrIntegrationError ? $error->retryAfter : 0);
        $state['error_code'] = 'provider_failed';
        stridebr_integrations_strava_backfill_write($pdo, $userId, $state);
        return $result + ['failed' => 1, 'backfill_retry' => true, 'backfill_only_failure' => true];
    }
}

function stridebr_integrations_strava_backfill_view(PDO $pdo, string $userId, array $connection): array
{
    $state = stridebr_integrations_strava_backfill_state($pdo, $userId, $connection, false);
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total, MIN(data_inicio) AS oldest FROM registros_atividade WHERE idusuario=:user AND origem_provedor='strava' AND excluido_em IS NULL");
    $stmt->execute([':user' => $userId]);
    $row = $stmt->fetch() ?: [];
    return [
        'state' => $state,
        'total' => (int) ($row['total'] ?? 0),
        'oldest_at' => trim((string) ($row['oldest'] ?? '')),
    ];
}

function stridebr_integrations_sync_strava(PDO $pdo, string $userId, array $connection): array
{
    $trigger = (string) ($connection['_sync_trigger'] ?? 'manual');
    $recentDue = $trigger !== 'periodic' || empty($connection['ultima_sincronizacao_em']) || (strtotime((string) $connection['ultima_sincronizacao_em']) ?: 0) + stridebr_integrations_periodic_interval('strava') <= time();
    $result = stridebr_integrations_result();
    if ($recentDue) {
        $recent = stridebr_integrations_sync_strava_recent($pdo, $userId, $connection);
        foreach (['created','existing','failed'] as $key) $result[$key] += (int) ($recent[$key] ?? 0);
        $result += array_diff_key($recent, $result);
        $result['recent_attempted'] = true;
        if (!empty($recent['reauthorize']) || !empty($recent['rate_limited']) || !empty($recent['deferred']) || (int) ($recent['failed'] ?? 0) > 0) return $result;
        $connection = stridebr_integrations_get($pdo, $userId, 'strava') ?: $connection;
    }

    $backfillState = stridebr_integrations_strava_backfill_state($pdo, $userId, $connection, true);
    if ($trigger === 'initial' || !stridebr_integrations_strava_backfill_is_due($backfillState)) return $result + ['backfill_pending' => ($backfillState['status'] ?? 'pending') !== 'completed'];
    $backfill = stridebr_integrations_strava_backfill_step($pdo, $userId, $connection);
    foreach (['created','existing','failed'] as $key) $result[$key] += (int) ($backfill[$key] ?? 0);
    foreach ($backfill as $key => $value) if (!array_key_exists($key, $result)) $result[$key] = $value;
    return $result;
}

function stridebr_integrations_strava_retry(array $response): int
{
    // Daily quota resets at midnight UTC; short-window quota at the next quarter hour.
    foreach ([['x-ratelimit-usage', 'x-ratelimit-limit'], ['x-readratelimit-usage', 'x-readratelimit-limit']] as [$usage, $limit]) {
        $used = explode(',', stridebr_integrations_header_first($response, $usage) ?? '');
        $caps = explode(',', stridebr_integrations_header_first($response, $limit) ?? '');
        if ((int) ($caps[1] ?? 0) > 0 && (int) ($used[1] ?? 0) >= (int) $caps[1] - 5) return max(900, strtotime('tomorrow UTC') - time() + 60);
    }
    return 900;
}

/** Webhook helpers deliberately retain only the documented event fields. */
function stridebr_strava_webhook_config(): array
{
    return [
        'verify_token' => trim((string) (getenv('STRAVA_WEBHOOK_VERIFY_TOKEN') ?: '')),
        'signing_secret' => trim((string) (getenv('STRAVA_WEBHOOK_SIGNING_SECRET') ?: '')),
        'subscription_id' => trim((string) (getenv('STRAVA_WEBHOOK_SUBSCRIPTION_ID') ?: '')),
    ];
}

function stridebr_strava_webhook_verify_signature(string $raw, ?string $header, ?int $now = null): bool
{
    $secret = stridebr_strava_webhook_config()['signing_secret'];
    if ($secret === '' || !is_string($header) || $header === '') return false;
    $parts = [];
    foreach (explode(',', $header) as $part) {
        [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
        if (in_array($key, ['t', 'v1'], true) && $value !== '') $parts[$key] = $value;
    }
    if (!isset($parts['t'], $parts['v1']) || !ctype_digit($parts['t']) || !preg_match('/^[a-f0-9]{64}$/i', $parts['v1'])) return false;
    $now ??= time();
    if (abs($now - (int) $parts['t']) > 300) return false;
    $expected = hash_hmac('sha256', $parts['t'] . '.' . $raw, $secret);
    return hash_equals($expected, strtolower($parts['v1']));
}

function stridebr_strava_webhook_event(array $input): ?array
{
    $objectType = $input['object_type'] ?? null; $aspect = $input['aspect_type'] ?? null;
    foreach (['object_id', 'owner_id', 'subscription_id', 'event_time'] as $key) if (!is_int($input[$key] ?? null) && !ctype_digit((string) ($input[$key] ?? ''))) return null;
    if (!is_string($objectType) || !in_array($objectType, ['activity', 'athlete'], true) || !is_string($aspect) || !in_array($aspect, ['create', 'update', 'delete'], true)) return null;
    $updates = $input['updates'] ?? [];
    if (!is_array($updates) || ($updates !== [] && array_is_list($updates))) return null;
    $allowed = $objectType === 'activity' ? ['title', 'type', 'private'] : ['authorized'];
    foreach ($updates as $key => $value) if (!in_array($key, $allowed, true) || (!is_scalar($value) && $value !== null)) return null;
    if ($objectType === 'athlete' && !($aspect === 'update' && ($updates['authorized'] ?? null) === 'false')) return null;
    ksort($updates, SORT_STRING);
    $event = ['provider'=>'strava','subscription_id'=>(string) $input['subscription_id'],'owner_external_id'=>(string) $input['owner_id'],'object_type'=>$objectType,'object_id'=>(string) $input['object_id'],'aspect_type'=>$aspect,'event_time'=>(int) $input['event_time'],'updates'=>$updates];
    $event['fingerprint'] = hash('sha256', json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    return $event;
}

function stridebr_strava_webhook_enqueue(PDO $pdo, array $event): void
{
    $stmt = $pdo->prepare("INSERT INTO integracao_webhook_eventos (provider, subscription_id, owner_external_id, object_type, object_id, aspect_type, event_time, updates, fingerprint, signature_verified) VALUES ('strava', :subscription, :owner, :type, :object, :aspect, :time, CAST(:updates AS jsonb), :fingerprint, CAST(:signed AS boolean)) ON CONFLICT (fingerprint) DO NOTHING");
    $stmt->execute([':subscription'=>$event['subscription_id'], ':owner'=>$event['owner_external_id'], ':type'=>$event['object_type'], ':object'=>$event['object_id'], ':aspect'=>$event['aspect_type'], ':time'=>$event['event_time'], ':updates'=>json_encode($event['updates'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), ':fingerprint'=>$event['fingerprint'], ':signed'=>!empty($event['signature_verified']) ? 'true' : 'false']);
}

function stridebr_integrations_lock_key(string $userId, string $provider): string { return 'integration:' . $userId . ':' . $provider; }
function stridebr_integrations_try_lock(PDO $pdo, string $userId, string $provider): bool { $stmt=$pdo->prepare('SELECT pg_try_advisory_lock(hashtextextended(:key, 0))'); $stmt->execute([':key'=>stridebr_integrations_lock_key($userId,$provider)]); return (bool)$stmt->fetchColumn(); }
function stridebr_integrations_unlock(PDO $pdo, string $userId, string $provider): void { $stmt=$pdo->prepare('SELECT pg_advisory_unlock(hashtextextended(:key, 0))'); $stmt->execute([':key'=>stridebr_integrations_lock_key($userId,$provider)]); }
function stridebr_integrations_provider_cooldown(PDO $pdo, string $provider): int { $stmt=$pdo->prepare("SELECT MAX(COALESCE((metadados->'sync'->>'rate_limit_until')::bigint,0)) FROM integracoes_usuario WHERE provedor=:provider"); $stmt->execute([':provider'=>$provider]); return (int)$stmt->fetchColumn(); }
function stridebr_integrations_provider_cooldown_set(PDO $pdo, string $provider, int $until): void { $stmt=$pdo->prepare("UPDATE integracoes_usuario SET metadados = (CASE WHEN jsonb_typeof(metadados) = 'object' THEN metadados WHEN jsonb_typeof(metadados) = 'array' AND jsonb_array_length(metadados) > 0 THEN jsonb_build_object('_legacy_array', metadados) ELSE '{}'::jsonb END) || jsonb_build_object('sync', COALESCE(metadados->'sync','{}'::jsonb) || jsonb_build_object('rate_limit_until', CAST(:until AS bigint))) WHERE provedor=:provider"); $stmt->execute([':until'=>$until,':provider'=>$provider]); }

function stridebr_strava_webhook_connection(PDO $pdo, string $ownerId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM integracoes_usuario WHERE provedor = 'strava' AND usuario_externo_id = :owner LIMIT 2");
    $stmt->execute([':owner'=>$ownerId]); $rows = $stmt->fetchAll();
    if (count($rows) > 1) throw new RuntimeException('Ambiguous external owner.');
    return $rows[0] ?? null;
}

function stridebr_strava_webhook_fetch_activity(PDO $pdo, array $connection, string $objectId): array
{
    $userId = (string) $connection['idusuario'];
    [$connection, $token] = stridebr_integrations_connection_token($pdo, $userId, 'strava', $connection);
    $provider = stridebr_integrations_provider('strava');
    $response = stridebr_integrations_http('GET', $provider['api_base_url'] . '/activities/' . rawurlencode($objectId), ['headers'=>['Authorization: Bearer ' . $token, 'Accept: application/json']]);
    if (!is_array($response['json']) || (string) ($response['json']['id'] ?? '') !== $objectId) throw new StridebrIntegrationError('detail', 'invalid_response', $response['status']);
    return ['activity'=>stridebr_integrations_normalize_strava($response['json']), 'cooldown'=>stridebr_integrations_strava_rate_low($response) ? stridebr_integrations_strava_retry($response) : 0];
}

function stridebr_strava_webhook_update_activity(PDO $pdo, string $userId, string $externalId, array $activity, array $updates): void
{
    // Existing imported record only; never changes manual activity or another provider.
    $setPrivacy = array_key_exists('private', $updates) && $updates['private'] === 'true';
    $existing = $pdo->prepare("SELECT idregistro FROM registros_atividade WHERE idusuario=:user AND origem_provedor='strava' AND id_externo=:external AND excluido_em IS NULL LIMIT 1"); $existing->execute([':user'=>$userId,':external'=>$externalId]); $id = $existing->fetchColumn(); if (!$id) return;
    if (array_key_exists('type', $updates)) {
        // Reuse the same modality resolver used during import; only classification changes.
        require_once __DIR__ . '/activity_file_exchange.php';
        $modalidade = atividadeArquivoModalidade($pdo, $userId, stridebr_integrations_sport_slug('strava', (string) $activity['sport']));
        $pdo->prepare('UPDATE registros_atividade SET idmodelo=:model, idmodalidade=:modality, data_atualizacao=NOW() WHERE idregistro=:id AND idusuario=:user')->execute([':model'=>$modalidade['idmodelo'], ':modality'=>$modalidade['idmodalidade'], ':id'=>$id, ':user'=>$userId]);
    }
    $stmt = $pdo->prepare("UPDATE registros_atividade SET titulo = CASE WHEN :title_update THEN :title ELSE titulo END, data_inicio = :start, data_fim = :end, visibilidade = CASE WHEN :private THEN 'privado' ELSE visibilidade END, data_atualizacao = NOW() WHERE idregistro=:id AND idusuario = :user AND origem_provedor = 'strava' AND id_externo = :external AND excluido_em IS NULL");
    $start = new DateTimeImmutable((string) $activity['start_at']); $duration = (int) ($activity['duration_s'] ?? 0);
    $stmt->execute([':title'=>(string) $activity['title'], ':title_update'=>array_key_exists('title',$updates), ':start'=>$start->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d H:i:s'), ':end'=>$duration > 0 ? $start->modify('+' . $duration . ' seconds')->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d H:i:s') : null, ':private'=>$setPrivacy, ':id'=>$id, ':user'=>$userId, ':external'=>$externalId]);
}

function stridebr_strava_webhook_process(PDO $pdo, array $event): string
{
    $connection = stridebr_strava_webhook_connection($pdo, (string) $event['owner_external_id']);
    if (!$connection) return 'ignored';
    $userId = (string) $connection['idusuario']; $updates = is_array($event['updates'] ?? null) ? $event['updates'] : (json_decode((string) ($event['updates'] ?? '{}'), true) ?: []); $signed = stridebr_db_bool($event['signature_verified'] ?? false);
    if (!stridebr_integrations_try_lock($pdo, $userId, 'strava')) throw new StridebrIntegrationError('lock', 'busy', null, 15);
    try {
        $cooldown = stridebr_integrations_provider_cooldown($pdo, 'strava');
        if ($cooldown > time()) throw new StridebrIntegrationError('rate_limit', 'rate_limit', 429, $cooldown - time());
        if ($event['object_type'] === 'athlete') {
            if (!$signed) {
                // A valid token proves a forged deauthorization event; 401 confirms actual revocation.
                try { [, $token] = stridebr_integrations_connection_token($pdo, $userId, 'strava', $connection); stridebr_integrations_http('GET', stridebr_integrations_provider('strava')['api_base_url'] . '/athlete', ['headers'=>['Authorization: Bearer '.$token]]); return 'ignored'; }
                catch (StridebrIntegrationError $e) { if ($e->httpStatus !== 401) throw $e; }
            }
            $stmt = $pdo->prepare("UPDATE integracoes_usuario SET status='revogado', access_token_enc=NULL, refresh_token_enc=NULL, token_expira_em=NULL, metadados=jsonb_set(CASE WHEN jsonb_typeof(metadados) = 'object' THEN metadados WHEN jsonb_typeof(metadados) = 'array' AND jsonb_array_length(metadados) > 0 THEN jsonb_build_object('_legacy_array', metadados) ELSE '{}'::jsonb END, '{sync}', '{}'::jsonb, true), ultimo_erro=NULL, atualizado_em=NOW() WHERE idusuario=:user AND provedor='strava' AND usuario_externo_id=:owner");
            $stmt->execute([':user'=>$userId, ':owner'=>$event['owner_external_id']]); return 'complete';
        }
        if ($event['aspect_type'] === 'delete' && !$signed) {
            // Only 404/410 from the owner's token confirms an unsigned deletion.
            try { stridebr_strava_webhook_fetch_activity($pdo, $connection, (string)$event['object_id']); return 'ignored'; }
            catch (StridebrIntegrationError $e) { if (!in_array($e->httpStatus, [404,410], true)) throw $e; }
        }
        if ($event['aspect_type'] === 'delete') {
            $stmt = $pdo->prepare("UPDATE registros_atividade SET excluido_em=NOW(), data_atualizacao=NOW() WHERE idusuario=:user AND origem_provedor='strava' AND id_externo=:external AND excluido_em IS NULL");
            $stmt->execute([':user'=>$userId, ':external'=>$event['object_id']]); return 'complete';
        }
        if (!stridebr_integrations_eligible($connection, 'webhook')) return 'ignored';
        $fetched = stridebr_strava_webhook_fetch_activity($pdo, $connection, (string) $event['object_id']); $activity = $fetched['activity'];
        if ($event['aspect_type'] === 'update' && stridebr_integrations_duplicate($pdo, $userId, 'strava', (string) $event['object_id'])) stridebr_strava_webhook_update_activity($pdo, $userId, (string) $event['object_id'], $activity, $updates);
        else stridebr_integrations_store_activity($pdo, $userId, 'strava', $activity);
        if ($fetched['cooldown'] > 0) stridebr_integrations_provider_cooldown_set($pdo, 'strava', time() + $fetched['cooldown']);
        return 'complete';
    } catch (StridebrIntegrationError $error) {
        if ($error->internalCode === 'rate_limit') {
            // A real 429 applies to every athlete in this Strava app, not only this queue item.
            stridebr_integrations_provider_cooldown_set($pdo, 'strava', time() + max(900, $error->retryAfter));
        }
        if ($error->internalCode === 'reauthorize') {
            // Match the normal sync state so the runner and future webhook work do not retry a revoked token.
            $state = stridebr_integrations_metadata($connection)['sync'] ?? [];
            $state['started_at'] = null;
            $state['last_attempt_at'] = time();
            $state['retry_at'] = 0;
            $state['reauthorize'] = true;
            $state['error_code'] = 'reauthorize';
            stridebr_integrations_sync_state($pdo, $userId, 'strava', $state);
            $stmt = $pdo->prepare("UPDATE integracoes_usuario SET status='erro', ultimo_erro='reauthorize', atualizado_em=NOW() WHERE idusuario=:user AND provedor='strava' AND usuario_externo_id=:owner");
            $stmt->execute([':user' => $userId, ':owner' => $event['owner_external_id']]);
        }
        throw $error;
    } finally { stridebr_integrations_unlock($pdo, $userId, 'strava'); }
}

function stridebr_integrations_find_coordinates(mixed $value, array &$out): void
{
    if (!is_array($value)) return;
    $lat = $value['latitude'] ?? $value['lat'] ?? $value['latitudeDegrees'] ?? null;
    $lon = $value['longitude'] ?? $value['lon'] ?? $value['lng'] ?? $value['longitudeDegrees'] ?? null;
    if (is_numeric($lat) && is_numeric($lon)) {
        $lat = (float) $lat; $lon = (float) $lon;
        if ($lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180) $out[] = [$lon, $lat];
    }
    foreach ($value as $child) if (is_array($child)) stridebr_integrations_find_coordinates($child, $out);
}

function stridebr_integrations_polar_items(array $json): array
{
    foreach (['trainingSessions', 'training_sessions', 'sessions', 'data'] as $key) if (is_array($json[$key] ?? null)) return array_values($json[$key]);
    return array_is_list($json) ? $json : [];
}

function stridebr_integrations_sync_polar(PDO $pdo, string $userId, array $connection): array
{
    [$connection, $token] = stridebr_integrations_connection_token($pdo, $userId, 'polar', $connection);
    $provider = stridebr_integrations_provider('polar');
    $from = !empty($connection['ultima_sincronizacao_em']) ? (new DateTimeImmutable((string) $connection['ultima_sincronizacao_em']))->modify('-2 days') : (new DateTimeImmutable('now'))->modify('-30 days');
    $to = (new DateTimeImmutable('now'))->modify('+1 day');
    $url = $provider['api_base_url'] . '/training-sessions/list?' . http_build_query(['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')], '', '&', PHP_QUERY_RFC3986);
    $response = stridebr_integrations_http('GET', $url, ['headers' => ['Authorization: Bearer ' . $token, 'Accept: application/json']]);
    $items = is_array($response['json']) ? stridebr_integrations_polar_items($response['json']) : [];
    $result = stridebr_integrations_result();
    $enhancedByDay = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $externalId = trim((string) ($item['identifier']['id'] ?? $item['id'] ?? ''));
        $startRaw = trim((string) ($item['startTime'] ?? $item['start_time'] ?? ''));
        if ($externalId === '' || $startRaw === '') continue;
        if (stridebr_integrations_duplicate($pdo, $userId, 'polar', $externalId)) { $result['existing']++; continue; }
        try {
            $timezoneOffset = is_numeric($item['timezoneOffsetMinutes'] ?? null) ? (int) $item['timezoneOffsetMinutes'] : null;
            $timezone = null;
            if ($timezoneOffset !== null && !preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/i', $startRaw)) {
                $absolute = abs($timezoneOffset);
                $timezone = new DateTimeZone(sprintf('%s%02d:%02d', $timezoneOffset >= 0 ? '+' : '-', intdiv($absolute, 60), $absolute % 60));
            }
            $start = $timezone ? new DateTimeImmutable($startRaw, $timezone) : new DateTimeImmutable($startRaw);
            $day = $start->format('Y-m-d');
            if (!array_key_exists($day, $enhancedByDay)) {
                $nextDay = (new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d');
                $featureQuery = 'from=' . rawurlencode($day) . '&to=' . rawurlencode($nextDay) . '&features=routes&features=statistics&features=laps';
                try {
                    $detailResponse = stridebr_integrations_http('GET', $provider['api_base_url'] . '/training-sessions/list?' . $featureQuery, ['headers' => ['Authorization: Bearer ' . $token, 'Accept: application/json']]);
                    $enhancedByDay[$day] = is_array($detailResponse['json']) ? stridebr_integrations_polar_items($detailResponse['json']) : [];
                } catch (Throwable) { $enhancedByDay[$day] = []; }
            }
            $detail = $item;
            foreach ($enhancedByDay[$day] as $candidate) {
                if (is_array($candidate) && (string) ($candidate['identifier']['id'] ?? $candidate['id'] ?? '') === $externalId) { $detail = $candidate; break; }
            }
            $route = [];
            stridebr_integrations_find_coordinates($detail['routes'] ?? $detail['route'] ?? [], $route);
            $route = atividadeArquivoSimplificarRota($route);
            $durationMs = $detail['durationMillis'] ?? $detail['duration_ms'] ?? null;
            $duration = is_numeric($durationMs) ? (float) $durationMs / 1000 : null;
            $sport = (string) ($detail['sport']['name'] ?? $detail['sport']['slug'] ?? $detail['sportName'] ?? $detail['detailedSportInfo'] ?? '');
            $device = ['source' => 'Polar'];
            $model = trim((string) ($detail['product']['modelName'] ?? $detail['deviceName'] ?? ''));
            if ($model !== '') $device['name'] = $model;
            if (trim((string) ($detail['deviceId'] ?? '')) !== '') $device['id'] = trim((string) $detail['deviceId']);
            if (is_array($detail['laps'] ?? null)) $device['laps'] = array_slice($detail['laps'], 0, 100);
            $saved = stridebr_integrations_store_activity($pdo, $userId, 'polar', [
                'external_id' => $externalId, 'title' => trim((string) ($detail['name'] ?? '')), 'sport' => $sport,
                'start_at' => $start->format(DateTimeInterface::ATOM), 'duration_s' => $duration,
                'distance_m' => $detail['distanceMeters'] ?? $detail['distance'] ?? null,
                'elevation_gain_m' => $detail['ascentMeters'] ?? $detail['ascent'] ?? $detail['totalAscent'] ?? null,
                'avg_hr' => $detail['hrAvg'] ?? $detail['averageHeartRate'] ?? null, 'max_hr' => $detail['hrMax'] ?? $detail['maximumHeartRate'] ?? null,
                'avg_cadence' => $detail['cadenceAvg'] ?? $detail['averageCadence'] ?? null, 'avg_power' => $detail['powerAvg'] ?? $detail['averagePower'] ?? null,
                'calories' => $detail['calories'] ?? null, 'route' => $route, 'device' => $device,
                'timezone' => $timezoneOffset !== null ? sprintf('%+d minutes', $timezoneOffset) : '',
            ]);
            if ($saved !== null) $result['created']++; else $result['existing']++;
        } catch (Throwable $error) { stridebr_integrations_log_failure('polar', 'normalization', $externalId, $error); $result['failed']++; }
    }
    return $result;
}

function stridebr_integrations_google_health_start(array $item): ?string
{
    $interval = is_array($item['exercise']['interval'] ?? null) ? $item['exercise']['interval'] : (is_array($item['interval'] ?? null) ? $item['interval'] : []);
    foreach (['startTime', 'start_time'] as $key) {
        $value = trim((string) ($interval[$key] ?? ''));
        if ($value !== '') { try { return (new DateTimeImmutable($value))->format(DateTimeInterface::ATOM); } catch (Throwable) {} }
    }
    return null;
}

function stridebr_integrations_google_health_external_id(array $item): string
{
    $name = trim((string) ($item['name'] ?? ''));
    if ($name === '') return '';
    $parts = explode('/', $name);
    return trim((string) end($parts));
}

function stridebr_integrations_sync_google_health(PDO $pdo, string $userId, array $connection): array
{
    require_once __DIR__ . '/activity_file_exchange.php';
    [$connection, $token] = stridebr_integrations_connection_token($pdo, $userId, 'google_health', $connection);
    $provider = stridebr_integrations_provider('google_health');
    $url = $provider['api_base_url'] . '/users/me/dataTypes/exercise/dataPoints?pageSize=100';
    $result = stridebr_integrations_result();
    $pages = 0;
    do {
        $response = stridebr_integrations_http('GET', $url, ['headers' => ['Authorization: Bearer ' . $token, 'Accept: application/json']]);
        $json = is_array($response['json']) ? $response['json'] : [];
        $items = is_array($json['dataPoints'] ?? null) ? $json['dataPoints'] : (is_array($json['data_points'] ?? null) ? $json['data_points'] : []);
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $externalId = stridebr_integrations_google_health_external_id($item);
            $start = stridebr_integrations_google_health_start($item);
            if ($externalId === '' || $start === null) continue;
            if (stridebr_integrations_duplicate($pdo, $userId, 'google_health', $externalId)) { $result['existing']++; continue; }
            try {
                $exercise = is_array($item['exercise'] ?? null) ? $item['exercise'] : [];
                $metrics = is_array($exercise['metricsSummary'] ?? null) ? $exercise['metricsSummary'] : (is_array($exercise['metrics_summary'] ?? null) ? $exercise['metrics_summary'] : []);
                $active = $exercise['activeDuration'] ?? $exercise['active_duration'] ?? null;
                $duration = null;
                if (is_string($active) && preg_match('/^([0-9.]+)s$/', $active, $m)) $duration = (float) $m[1];
                elseif (is_numeric($active)) $duration = (float) $active;
                $distanceMm = $metrics['distanceMillimeters'] ?? $metrics['distanceMillimiters'] ?? $metrics['distance_millimeters'] ?? null;
                $elevationMm = $metrics['elevationGainMillimeters'] ?? $metrics['elevation_gain_millimeters'] ?? null;
                $activity = [
                    'external_id' => $externalId, 'title' => trim((string) ($exercise['displayName'] ?? $exercise['display_name'] ?? '')),
                    'sport' => (string) ($exercise['exerciseType'] ?? $exercise['exercise_type'] ?? ''), 'start_at' => $start, 'duration_s' => $duration,
                    'distance_m' => is_numeric($distanceMm) ? (float) $distanceMm / 1000 : null,
                    'elevation_gain_m' => is_numeric($elevationMm) ? (float) $elevationMm / 1000 : null,
                    'avg_hr' => $metrics['averageHeartRateBeatsPerMinute'] ?? $metrics['average_heart_rate_beats_per_minute'] ?? null,
                    'calories' => $metrics['caloriesKcal'] ?? $metrics['calories_kcal'] ?? null, 'route' => [],
                    'device' => ['source' => 'Google Health', 'platform' => $item['dataSource']['platform'] ?? $item['data_source']['platform'] ?? null],
                ];
                if (is_array($exercise['splitSummaries'] ?? $exercise['split_summaries'] ?? null)) $activity['device']['splits'] = array_slice($exercise['splitSummaries'] ?? $exercise['split_summaries'], 0, 100);
                try {
                    $tcxUrl = $provider['api_base_url'] . '/users/me/dataTypes/exercise/dataPoints/' . rawurlencode($externalId) . ':exportExerciseTcx?alt=media';
                    $tcx = stridebr_integrations_http('GET', $tcxUrl, ['headers' => ['Authorization: Bearer ' . $token, 'Accept: application/vnd.garmin.tcx+xml, application/xml, text/xml']]);
                    if (trim((string) $tcx['body']) !== '') {
                        $parsed = atividadeArquivoTcx((string) $tcx['body']);
                        $fromFile = stridebr_integrations_parsed_activity($parsed, $externalId, $activity['title'], $activity['device']);
                        foreach (['duration_s','distance_m','elevation_gain_m','avg_hr','max_hr','avg_cadence','avg_power','calories','route'] as $key) if (($fromFile[$key] ?? null) !== null && ($fromFile[$key] ?? []) !== []) $activity[$key] = $fromFile[$key];
                        if (($fromFile['sport'] ?? 'generic') !== 'generic') $activity['sport'] = $fromFile['sport'];
                        $activity['device'] = $fromFile['device'];
                    }
                } catch (Throwable) {
                }
                $saved = stridebr_integrations_store_activity($pdo, $userId, 'google_health', $activity);
                if ($saved !== null) $result['created']++; else $result['existing']++;
            } catch (Throwable $error) { stridebr_integrations_log_failure('google_health', 'normalization', $externalId, $error); $result['failed']++; }
        }
        $pageToken = trim((string) ($json['nextPageToken'] ?? $json['next_page_token'] ?? ''));
        $url = $pageToken !== '' ? $provider['api_base_url'] . '/users/me/dataTypes/exercise/dataPoints?' . http_build_query(['pageSize' => 100, 'pageToken' => $pageToken], '', '&', PHP_QUERY_RFC3986) : '';
        $pages++;
    } while ($url !== '' && $pages < 10);
    return $result;
}

function stridebr_integrations_coros_mcp_json(string $body): ?array
{
    $decoded = json_decode($body, true);
    if (is_array($decoded)) return $decoded;
    $candidate = null;
    foreach (preg_split('/\\r?\\n/', $body) ?: [] as $line) {
        if (!str_starts_with($line, 'data:')) continue;
        $data = trim(substr($line, 5));
        $json = json_decode($data, true);
        if (is_array($json)) $candidate = $json;
    }
    return $candidate;
}

function stridebr_integrations_coros_mcp_modern(string $mcpUrl, string $token, string $method, array $params, int|string|null $id = 1, bool $allowError = false): array
{
    $headers = [
        'Authorization: Bearer ' . $token,
        'Accept: application/json, text/event-stream',
        'Content-Type: application/json',
        'MCP-Protocol-Version: 2026-07-28',
        'Mcp-Method: ' . $method,
    ];
    if (in_array($method, ['tools/call', 'prompts/get'], true) && trim((string) ($params['name'] ?? '')) !== '') $headers[] = 'Mcp-Name: ' . trim((string) $params['name']);
    elseif ($method === 'resources/read' && trim((string) ($params['uri'] ?? '')) !== '') $headers[] = 'Mcp-Name: ' . trim((string) $params['uri']);
    $params['_meta'] = [
        'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
        'io.modelcontextprotocol/clientInfo' => ['name' => 'StrideBR', 'version' => '1.0.0-rc.5'],
        'io.modelcontextprotocol/clientCapabilities' => [],
    ];
    $payload = ['jsonrpc' => '2.0', 'method' => $method, 'params' => $params];
    if ($id !== null) $payload['id'] = $id;
    $response = stridebr_integrations_http('POST', $mcpUrl, ['headers' => $headers, 'json' => $payload, 'allow_error' => $allowError]);
    $json = stridebr_integrations_coros_mcp_json((string) $response['body']) ?? $response['json'];
    if ($allowError && (($response['status'] < 200 || $response['status'] >= 300) || is_array($json['error'] ?? null))) return ['_http_status' => (int) $response['status'], '_error' => is_array($json) ? $json : null];
    if ($id === null) return [];
    if (!is_array($json)) throw new RuntimeException('A COROS retornou uma resposta MCP inválida.');
    if (is_array($json['error'] ?? null)) throw new RuntimeException(trim((string) ($json['error']['message'] ?? 'Erro retornado pela COROS.')));
    return is_array($json['result'] ?? null) ? $json['result'] : [];
}

function stridebr_integrations_coros_mcp_legacy(string $mcpUrl, string $token, string $method, array $params, ?string &$sessionId, int|string|null $id = 1): array
{
    $headers = ['Authorization: Bearer ' . $token, 'Accept: application/json, text/event-stream', 'Content-Type: application/json', 'MCP-Protocol-Version: 2025-11-25'];
    if ($sessionId) $headers[] = 'Mcp-Session-Id: ' . $sessionId;
    $payload = ['jsonrpc' => '2.0', 'method' => $method];
    if ($id !== null) $payload['id'] = $id;
    if ($params !== []) $payload['params'] = $params;
    $response = stridebr_integrations_http('POST', $mcpUrl, ['headers' => $headers, 'json' => $payload]);
    $newSession = stridebr_integrations_header_first($response, 'mcp-session-id');
    if ($newSession) $sessionId = $newSession;
    $json = stridebr_integrations_coros_mcp_json((string) $response['body']) ?? $response['json'];
    if ($id === null) return [];
    if (!is_array($json)) throw new RuntimeException('A COROS retornou uma resposta MCP inválida.');
    if (is_array($json['error'] ?? null)) throw new RuntimeException(trim((string) ($json['error']['message'] ?? 'Erro retornado pela COROS.')));
    return is_array($json['result'] ?? null) ? $json['result'] : [];
}

function stridebr_integrations_coros_mcp_mode(string $mcpUrl, string $token): string
{
    $probe = stridebr_integrations_coros_mcp_modern($mcpUrl, $token, 'server/discover', [], 'stridebr-protocol', true);
    if (!isset($probe['_http_status'])) return 'modern';
    $status = (int) ($probe['_http_status'] ?? 0);
    $error = is_array($probe['_error'] ?? null) ? $probe['_error'] : [];
    $code = is_array($error['error'] ?? null) ? (int) ($error['error']['code'] ?? 0) : 0;
    if (in_array($status, [400, 404, 405], true) || $code === -32601) return 'legacy';
    throw new RuntimeException('A COROS recusou a negociação do protocolo MCP.');
}

function stridebr_integrations_coros_mcp_call(string $mode, string $mcpUrl, string $token, string $method, array $params, ?string &$sessionId, int|string|null $id = 1): array
{
    if ($mode === 'modern') return stridebr_integrations_coros_mcp_modern($mcpUrl, $token, $method, $params, $id);
    return stridebr_integrations_coros_mcp_legacy($mcpUrl, $token, $method, $params, $sessionId, $id);
}

function stridebr_integrations_coros_content(array $result): mixed
{
    if (isset($result['structuredContent'])) return $result['structuredContent'];
    $content = is_array($result['content'] ?? null) ? $result['content'] : [];
    foreach ($content as $entry) {
        if (!is_array($entry)) continue;
        if (isset($entry['json'])) return $entry['json'];
        $text = trim((string) ($entry['text'] ?? ''));
        if ($text !== '') { $decoded = json_decode($text, true); return is_array($decoded) ? $decoded : $text; }
    }
    return null;
}

function stridebr_integrations_coros_records(mixed $value): array
{
    $records = [];
    $walk = static function (mixed $node) use (&$walk, &$records): void {
        if (!is_array($node)) return;
        $label = $node['labelId'] ?? $node['label_id'] ?? null;
        $sport = $node['sportType'] ?? $node['sport_type'] ?? $node['sportTypeCode'] ?? null;
        if (is_scalar($label) && trim((string) $label) !== '') $records[] = ['label_id' => trim((string) $label), 'sport_type' => is_scalar($sport) ? trim((string) $sport) : '', 'raw' => $node];
        foreach ($node as $child) $walk($child);
    };
    $walk($value);
    if (is_string($value)) {
        if (preg_match_all('/LabelId:\\s*([^|\\r\\n]+).*?SportType:\\s*([^|\\r\\n]+)/i', $value, $matches, PREG_SET_ORDER)) foreach ($matches as $match) $records[] = ['label_id' => trim($match[1]), 'sport_type' => trim($match[2]), 'raw' => []];
    }
    $unique = [];
    foreach ($records as $record) $unique[$record['label_id']] = $record;
    return array_values($unique);
}

function stridebr_integrations_coros_find(mixed $value, array $keys): mixed
{
    if (!is_array($value)) return null;
    foreach ($keys as $key) if (array_key_exists($key, $value) && is_scalar($value[$key])) return $value[$key];
    foreach ($value as $child) {
        $found = stridebr_integrations_coros_find($child, $keys);
        if ($found !== null && $found !== '') return $found;
    }
    return null;
}

function stridebr_integrations_coros_start(mixed $value): ?string
{
    $raw = stridebr_integrations_coros_find($value, ['startTime', 'start_time', 'startDateTime', 'start_date_time', 'startTimestamp', 'start_timestamp']);
    if ($raw === null || $raw === '') return null;
    try {
        if (is_numeric($raw)) {
            $number = (float) $raw;
            if ($number > 100000000000) $number /= 1000;
            return (new DateTimeImmutable('@' . (int) floor($number)))->format(DateTimeInterface::ATOM);
        }
        return (new DateTimeImmutable((string) $raw))->format(DateTimeInterface::ATOM);
    } catch (Throwable) {
        return null;
    }
}

function stridebr_integrations_coros_summary_activity(array $record, mixed $detail): ?array
{
    $combined = ['record' => $record['raw'] ?? [], 'detail' => $detail];
    $start = stridebr_integrations_coros_start($combined);
    if ($start === null) return null;
    $duration = stridebr_integrations_coros_find($combined, ['durationSeconds', 'duration_seconds', 'duration', 'totalTime', 'total_time']);
    $distance = stridebr_integrations_coros_find($combined, ['distanceMeters', 'distance_meters', 'distance', 'totalDistance', 'total_distance']);
    $elevation = stridebr_integrations_coros_find($combined, ['elevationGainMeters', 'elevation_gain_meters', 'ascentMeters', 'ascent_meters', 'totalAscent', 'total_ascent']);
    return [
        'external_id' => (string) $record['label_id'],
        'title' => trim((string) (stridebr_integrations_coros_find($combined, ['name', 'activityName', 'activity_name', 'title']) ?? '')),
        'sport' => (string) (($record['sport_type'] ?? '') ?: (stridebr_integrations_coros_find($combined, ['sportType', 'sport_type', 'sport']) ?? '')),
        'start_at' => $start,
        'duration_s' => is_numeric($duration) ? (float) $duration : null,
        'distance_m' => is_numeric($distance) ? (float) $distance : null,
        'elevation_gain_m' => is_numeric($elevation) ? (float) $elevation : null,
        'avg_hr' => stridebr_integrations_coros_find($combined, ['averageHeartRate', 'avgHeartRate', 'average_heart_rate', 'avg_hr']),
        'max_hr' => stridebr_integrations_coros_find($combined, ['maximumHeartRate', 'maxHeartRate', 'maximum_heart_rate', 'max_hr']),
        'avg_cadence' => stridebr_integrations_coros_find($combined, ['averageCadence', 'avgCadence', 'average_cadence']),
        'avg_power' => stridebr_integrations_coros_find($combined, ['averagePower', 'avgPower', 'average_power']),
        'calories' => stridebr_integrations_coros_find($combined, ['calories', 'caloriesKcal', 'calories_kcal']),
        'route' => [],
        'device' => ['source' => 'COROS'],
    ];
}

function stridebr_integrations_coros_urls(mixed $value): array
{
    $urls = [];
    $walk = static function (mixed $node) use (&$walk, &$urls): void {
        if (is_string($node)) {
            if (preg_match_all('~https://[^\\s"<>]+~', $node, $m)) foreach ($m[0] as $url) $urls[] = rtrim($url, '.,);');
            return;
        }
        if (is_array($node)) foreach ($node as $child) $walk($child);
    };
    $walk($value);
    return array_values(array_unique(array_filter($urls, 'stridebr_integrations_https_url')));
}

function stridebr_integrations_sync_coros(PDO $pdo, string $userId, array $connection): array
{
    require_once __DIR__ . '/activity_file_exchange.php';
    [$connection, $token] = stridebr_integrations_connection_token($pdo, $userId, 'coros', $connection);
    $provider = stridebr_integrations_provider('coros');
    $meta = stridebr_integrations_metadata($connection);
    $mcpUrl = trim((string) ($meta['oauth']['mcp_url'] ?? $provider['mcp_url'] ?? ''));
    if (!stridebr_integrations_https_url($mcpUrl)) throw new RuntimeException('A configuração MCP da COROS ficou inválida; reautorize a conta.');
    $session = null;
    $mode = stridebr_integrations_coros_mcp_mode($mcpUrl, $token);
    if ($mode === 'legacy') {
        $initialize = stridebr_integrations_coros_mcp_legacy($mcpUrl, $token, 'initialize', ['protocolVersion' => '2025-11-25', 'capabilities' => [], 'clientInfo' => ['name' => 'StrideBR', 'version' => '1.0.0-rc.5']], $session, 1);
        if ($initialize === []) throw new RuntimeException('A COROS não aceitou a inicialização MCP.');
        stridebr_integrations_coros_mcp_legacy($mcpUrl, $token, 'notifications/initialized', [], $session, null);
    }
    $from = !empty($connection['ultima_sincronizacao_em']) ? (new DateTimeImmutable((string) $connection['ultima_sincronizacao_em']))->modify('-2 days') : (new DateTimeImmutable('now'))->modify('-30 days');
    $to = new DateTimeImmutable('now');
    $query = stridebr_integrations_coros_mcp_call($mode, $mcpUrl, $token, 'tools/call', ['name' => 'querySportRecords', 'arguments' => ['startDate' => $from->format('Y-m-d'), 'endDate' => $to->format('Y-m-d'), 'limit' => 50, 'timezone' => date_default_timezone_get()]], $session, 2);
    $records = stridebr_integrations_coros_records(stridebr_integrations_coros_content($query));
    $result = stridebr_integrations_result();
    $fitDownloads = 0;
    foreach ($records as $record) {
        $externalId = $record['label_id'];
        if (stridebr_integrations_duplicate($pdo, $userId, 'coros', $externalId)) { $result['existing']++; continue; }
        try {
            $arguments = ['labelId' => $externalId];
            if ($record['sport_type'] !== '') $arguments['sportType'] = $record['sport_type'];
            $activity = null;
            if ($fitDownloads < 50) {
                try {
                    $fitResponse = stridebr_integrations_coros_mcp_call($mode, $mcpUrl, $token, 'tools/call', ['name' => 'queryActivityFitFileDownloadUrls', 'arguments' => $arguments], $session, 'fit-' . $externalId);
                    $urls = stridebr_integrations_coros_urls(stridebr_integrations_coros_content($fitResponse));
                    if ($urls !== []) {
                        $fit = stridebr_integrations_http('GET', $urls[0], ['headers' => ['Accept: application/octet-stream'], 'timeout' => 30]);
                        $fitDownloads++;
                        $parsed = atividadeArquivoFit((string) $fit['body']);
                        $activity = stridebr_integrations_parsed_activity($parsed, $externalId, '', ['source' => 'COROS']);
                    }
                } catch (Throwable) {
                }
            }
            if ($activity === null) {
                $detailResponse = stridebr_integrations_coros_mcp_call($mode, $mcpUrl, $token, 'tools/call', ['name' => 'getActivityDetail', 'arguments' => $arguments], $session, 'detail-' . $externalId);
                $activity = stridebr_integrations_coros_summary_activity($record, stridebr_integrations_coros_content($detailResponse));
            }
            if (!is_array($activity)) throw new RuntimeException('A atividade COROS não possui dados suficientes para importação.');
            $saved = stridebr_integrations_store_activity($pdo, $userId, 'coros', $activity);
            if ($saved !== null) $result['created']++; else $result['existing']++;
        } catch (Throwable $error) { stridebr_integrations_log_failure('coros', 'normalization', $externalId, $error); $result['failed']++; }
    }
    return $result;
}

function stridebr_integrations_suunto_start(array $item): ?string
{
    if (is_numeric($item['startTime'] ?? null)) {
        $milliseconds = (int) $item['startTime'];
        $seconds = $milliseconds > 100000000000 ? intdiv($milliseconds, 1000) : $milliseconds;
        return (new DateTimeImmutable('@' . $seconds))->format(DateTimeInterface::ATOM);
    }
    foreach (['start_time', 'startTimeLocal'] as $key) {
        $value = trim((string) ($item[$key] ?? ''));
        if ($value === '') continue;
        try { return (new DateTimeImmutable($value))->format(DateTimeInterface::ATOM); } catch (Throwable) {}
    }
    return null;
}

function stridebr_integrations_sync_suunto(PDO $pdo, string $userId, array $connection): array
{
    [$connection, $token] = stridebr_integrations_connection_token($pdo, $userId, 'suunto', $connection);
    $provider = stridebr_integrations_provider('suunto');
    $subscriptionKey = trim((string) ($provider['subscription_key'] ?? ''));
    if ($subscriptionKey === '') throw new RuntimeException('A chave da Suunto Cloud API ainda não está configurada.');
    $response = stridebr_integrations_http('GET', rtrim((string) $provider['api_base_url'], '/') . '/v2/workouts', ['headers' => ['Authorization: Bearer ' . $token, 'Ocp-Apim-Subscription-Key: ' . $subscriptionKey, 'Accept: application/json']]);
    $items = is_array($response['json']) ? $response['json'] : [];
    if (isset($items['workouts']) && is_array($items['workouts'])) $items = $items['workouts'];
    $result = stridebr_integrations_result();
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $externalId = trim((string) ($item['workoutKey'] ?? $item['id'] ?? ''));
        $start = stridebr_integrations_suunto_start($item);
        if ($externalId === '' || $start === null) continue;
        if (stridebr_integrations_duplicate($pdo, $userId, 'suunto', $externalId)) { $result['existing']++; continue; }
        try {
            $hr = is_array($item['hrdata'] ?? null) ? $item['hrdata'] : [];
            $activityName = trim((string) ($item['activityName'] ?? $item['sport'] ?? $item['activity'] ?? ''));
            if ($activityName === '' && (string) ($item['activityId'] ?? '') === '1') $activityName = 'running';
            $device = ['source' => 'Suunto'];
            foreach (['deviceName' => 'name', 'device' => 'name', 'productName' => 'name'] as $sourceKey => $targetKey) if (!empty($item[$sourceKey]) && is_scalar($item[$sourceKey])) $device[$targetKey] = trim((string) $item[$sourceKey]);
            $saved = stridebr_integrations_store_activity($pdo, $userId, 'suunto', [
                'external_id' => $externalId, 'title' => trim((string) ($item['description'] ?? $item['name'] ?? '')), 'sport' => $activityName !== '' ? $activityName : (string) ($item['activityId'] ?? ''), 'start_at' => $start,
                'duration_s' => is_numeric($item['totalTime'] ?? null) ? (float) $item['totalTime'] : (is_numeric($item['duration'] ?? null) ? (float) $item['duration'] : null),
                'distance_m' => is_numeric($item['totalDistance'] ?? null) ? (float) $item['totalDistance'] : null, 'elevation_gain_m' => is_numeric($item['totalAscent'] ?? null) ? (float) $item['totalAscent'] : null,
                'avg_hr' => is_numeric($hr['workoutAvgHR'] ?? null) ? (float) $hr['workoutAvgHR'] : (is_numeric($item['avgHeartRate'] ?? null) ? (float) $item['avgHeartRate'] : null),
                'max_hr' => is_numeric($hr['workoutMaxHR'] ?? null) ? (float) $hr['workoutMaxHR'] : (is_numeric($item['maxHeartRate'] ?? null) ? (float) $item['maxHeartRate'] : null),
                'avg_cadence' => $item['avgCadence'] ?? null, 'avg_power' => $item['avgPower'] ?? null, 'calories' => $item['energyConsumption'] ?? $item['calories'] ?? null, 'route' => [], 'device' => $device,
            ]);
            if ($saved !== null) $result['created']++; else $result['existing']++;
        } catch (Throwable $error) { stridebr_integrations_log_failure('suunto', 'normalization', $externalId, $error); $result['failed']++; }
    }
    return $result;
}

function stridebr_integrations_sync_detailed(PDO $pdo, string $userId, string $providerId, string $trigger = 'manual'): array
{
    if (!stridebr_integrations_try_lock($pdo, $userId, $providerId)) return stridebr_integrations_result() + ['skipped' => 'busy'];
    try {
        // Re-read after acquiring the lock: the other process may have refreshed/synchronized.
        $connection = stridebr_integrations_get($pdo, $userId, $providerId);
        if (!$connection || !stridebr_integrations_eligible($connection, $trigger)) return stridebr_integrations_result() + ['skipped' => 'not_due'];
        if (stridebr_integrations_provider_cooldown($pdo, $providerId) > time()) return stridebr_integrations_result() + ['skipped' => 'provider_backoff'];
        $connection['_sync_trigger'] = $trigger;
        $started = time();
        $state = stridebr_integrations_metadata($connection)['sync'] ?? [];
        $state['started_at'] = $started;
        stridebr_integrations_sync_state($pdo, $userId, $providerId, $state);
        try {
            $result = match ($providerId) {
                'strava' => stridebr_integrations_sync_strava($pdo, $userId, $connection),
                'polar' => stridebr_integrations_sync_polar($pdo, $userId, $connection),
                'google_health' => stridebr_integrations_sync_google_health($pdo, $userId, $connection),
                'coros' => stridebr_integrations_sync_coros($pdo, $userId, $connection),
                'suunto' => stridebr_integrations_sync_suunto($pdo, $userId, $connection),
                default => throw new StridebrIntegrationError('provider', 'unavailable'),
            };
        } catch (Throwable $error) {
            stridebr_integrations_log_failure($providerId, 'provider', null, $error);
            $result = stridebr_integrations_result();
            $result['failed'] = 1;
            $result['error_code'] = $error instanceof StridebrIntegrationError ? $error->internalCode : 'provider_failed';
            $result['reauthorize'] = $result['error_code'] === 'reauthorize';
            $result['rate_limited'] = $result['error_code'] === 'rate_limit';
            $result['retry_after'] = $error instanceof StridebrIntegrationError ? $error->retryAfter : 0;
        }
        $failedCount = (int) ($result['failed'] ?? 0);
        $backfillOnlyFailure = !empty($result['backfill_only_failure']);
        $failed = $failedCount > 0 && !$backfillOnlyFailure;
        $deferred = !empty($result['deferred']);
        $failures = $failed ? min(8, (int) ($state['failures'] ?? 0) + 1) : 0;
        $delay = max((int) ($result['retry_after'] ?? 0), $failed ? min(21600, 900 * (2 ** ($failures - 1))) : 900);
        $state = [
            'started_at' => null, 'last_attempt_at' => $started, 'failures' => $failures,
            'retry_at' => ($failed || $deferred) ? time() + $delay : 0,
            'reauthorize' => !empty($result['reauthorize']),
            'error_code' => $result['error_code'] ?? null,
            'rate_limit_until' => !empty($result['rate_limited']) ? time() + $delay : 0,
        ];
        stridebr_integrations_sync_state($pdo, $userId, $providerId, $state);
        if (!empty($result['rate_limited'])) stridebr_integrations_provider_cooldown_set($pdo, $providerId, time() + $delay);
        $recentSucceeded = !empty($result['recent_attempted']) && !$failed && !$deferred;
        $stmt = $pdo->prepare("UPDATE integracoes_usuario SET ultima_sincronizacao_em = CASE WHEN :completed = 1 THEN to_timestamp(:started) ELSE ultima_sincronizacao_em END, ultimo_erro = :erro, status = :status, atualizado_em = NOW() WHERE idusuario = :usuario AND provedor = :provedor");
        $stmt->execute([
            ':completed' => $recentSucceeded ? 1 : 0, ':started' => $started,
            ':erro' => $failed ? ($state['reauthorize'] ? 'reauthorize' : 'sync_failed') : null,
            ':status' => $failed ? 'erro' : 'conectado', ':usuario' => $userId, ':provedor' => $providerId,
        ]);
        return $result + stridebr_integrations_result();
    } finally {
        stridebr_integrations_unlock($pdo, $userId, $providerId);
    }
}

function stridebr_integrations_sync(PDO $pdo, string $userId, string $providerId): int
{
    return (int) stridebr_integrations_sync_detailed($pdo, $userId, $providerId)['created'];
}
