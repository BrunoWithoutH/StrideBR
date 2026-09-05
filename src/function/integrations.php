<?php

declare(strict_types=1);

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
            'description' => (function_exists('stridebr_t') ? stridebr_t('integrations.provider.garmin.description') : 'Sincronize atividades e, quando habilitado pela Garmin, envie treinos e percursos para dispositivos compatíveis.'),
            'kind' => 'cloud',
            'oauth' => true,
            'implementation_ready' => false,
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
            'scope' => 'read,activity:read_all',
            'token_auth' => 'body',
            'capabilities' => ['activities_in'],
            'profile_link' => true,
        ],
        'polar' => [
            'label' => 'Polar Flow',
            'short' => 'Polar',
            'description' => (function_exists('stridebr_t') ? stridebr_t('integrations.provider.polar.description') : 'Receba exercícios do Polar Flow, incluindo dados do dispositivo e rota quando disponíveis.'),
            'kind' => 'cloud',
            'oauth' => true,
            'client_id' => trim((string) (getenv('POLAR_CLIENT_ID') ?: '')),
            'client_secret' => trim((string) (getenv('POLAR_CLIENT_SECRET') ?: '')),
            'authorize_url' => trim((string) (getenv('POLAR_OAUTH_AUTHORIZE_URL') ?: 'https://flow.polar.com/oauth2/authorization')),
            'token_url' => trim((string) (getenv('POLAR_OAUTH_TOKEN_URL') ?: 'https://polarremote.com/v2/oauth2/token')),
            'scope' => trim((string) (getenv('POLAR_OAUTH_SCOPE') ?: 'accesslink.read_all')),
            'token_auth' => 'basic',
            'capabilities' => ['activities_in'],
            'profile_link' => true,
        ],
        'suunto' => [
            'label' => 'Suunto',
            'short' => 'Suunto',
            'description' => (function_exists('stridebr_t') ? stridebr_t('integrations.provider.suunto.description') : 'Importe automaticamente os treinos da Suunto App pela Suunto Cloud API.'),
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
        'fitbit' => [
            'label' => 'Fitbit',
            'short' => 'Fitbit',
            'description' => (function_exists('stridebr_t') ? stridebr_t('integrations.provider.fitbit.description') : 'Importe exercícios do Fitbit automaticamente, com rota e detalhes quando o registro disponibilizar TCX.'),
            'kind' => 'cloud',
            'oauth' => true,
            'client_id' => trim((string) (getenv('FITBIT_CLIENT_ID') ?: '')),
            'client_secret' => trim((string) (getenv('FITBIT_CLIENT_SECRET') ?: '')),
            'authorize_url' => trim((string) (getenv('FITBIT_OAUTH_AUTHORIZE_URL') ?: 'https://www.fitbit.com/oauth2/authorize')),
            'token_url' => trim((string) (getenv('FITBIT_OAUTH_TOKEN_URL') ?: 'https://api.fitbit.com/oauth2/token')),
            'scope' => trim((string) (getenv('FITBIT_OAUTH_SCOPE') ?: 'activity profile heartrate location')),
            'token_auth' => 'basic',
            'capabilities' => ['activities_in'],
            'profile_link' => true,
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

function stridebr_integrations_configured(array $provider): bool
{
    if (($provider['kind'] ?? '') !== 'cloud') return false;
    if (array_key_exists('implementation_ready', $provider) && !$provider['implementation_ready']) return false;
    if (stridebr_integrations_secret() === null) return false;
    $base = trim((string) ($provider['client_id'] ?? '')) !== ''
        && trim((string) ($provider['client_secret'] ?? '')) !== ''
        && filter_var((string) ($provider['authorize_url'] ?? ''), FILTER_VALIDATE_URL) !== false
        && filter_var((string) ($provider['token_url'] ?? ''), FILTER_VALIDATE_URL) !== false;
    if (!$base) return false;
    if (($provider['id'] ?? '') === 'suunto' || ($provider['label'] ?? '') === 'Suunto') {
        return trim((string) ($provider['subscription_key'] ?? '')) !== '';
    }
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

function stridebr_integrations_start(string $providerId, string $returnTo = '/user/edit-profile.php#conexoes'): string
{
    $provider = stridebr_integrations_provider($providerId);
    if (!stridebr_integrations_configured($provider)) throw new RuntimeException($provider['label'] . ' ainda não está configurado no servidor.');
    $state = bin2hex(random_bytes(24));
    $_SESSION['StrideBRIntegrationOAuth'][$state] = [
        'provider' => $providerId,
        'return' => stridebr_safe_redirect($returnTo, '/user/edit-profile.php#conexoes'),
        'created_at' => time(),
    ];
    $params = [
        'client_id' => $provider['client_id'],
        'redirect_uri' => stridebr_integrations_callback_uri($providerId),
        'response_type' => 'code',
        'state' => $state,
    ];
    if (trim((string) ($provider['scope'] ?? '')) !== '') $params['scope'] = $provider['scope'];
    if ($providerId === 'strava') $params['approval_prompt'] = 'auto';
    return $provider['authorize_url'] . (str_contains($provider['authorize_url'], '?') ? '&' : '?') . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function stridebr_integrations_http(string $method, string $url, array $options = []): array
{
    $headers = array_values(array_filter(array_map('strval', $options['headers'] ?? [])));
    $body = $options['body'] ?? null;
    if (is_array($body)) $body = http_build_query($body, '', '&', PHP_QUERY_RFC3986);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 7,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($body !== null) $curlOptions[CURLOPT_POSTFIELDS] = (string) $body;
        curl_setopt_array($ch, $curlOptions);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if (!is_string($response)) throw new RuntimeException('Não foi possível falar com o serviço externo.' . ($error !== '' ? ' ' . $error : ''));
    } else {
        $context = stream_context_create(['http' => [
            'method' => strtoupper($method),
            'header' => implode("\r\n", $headers) . "\r\n",
            'content' => $body !== null ? (string) $body : '',
            'timeout' => 20,
            'ignore_errors' => true,
        ]]);
        $response = @file_get_contents($url, false, $context);
        if (!is_string($response)) throw new RuntimeException('Não foi possível falar com o serviço externo.');
        $status = 200;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match)) $status = (int) $match[1];
        }
    }
    if ($status < 200 || $status >= 300) {
        $message = '';
        $decoded = json_decode($response, true);
        if (is_array($decoded)) $message = trim((string) ($decoded['message'] ?? $decoded['error_description'] ?? $decoded['error'] ?? ''));
        throw new RuntimeException($message !== '' ? $message : 'O serviço externo recusou a solicitação.');
    }
    $decoded = json_decode($response, true);
    return ['status' => $status, 'body' => $response, 'json' => is_array($decoded) ? $decoded : null];
}

function stridebr_integrations_exchange(string $providerId, string $code): array
{
    $provider = stridebr_integrations_provider($providerId);
    if (!stridebr_integrations_configured($provider)) throw new RuntimeException($provider['label'] . ' não está configurado.');
    $fields = [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => stridebr_integrations_callback_uri($providerId),
    ];
    $headers = ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'];
    if (($provider['token_auth'] ?? 'basic') === 'body') {
        $fields['client_id'] = $provider['client_id'];
        $fields['client_secret'] = $provider['client_secret'];
    } else {
        $headers[] = 'Authorization: Basic ' . base64_encode($provider['client_id'] . ':' . $provider['client_secret']);
        if ($providerId === 'garmin' && trim((string) (getenv('GARMIN_OAUTH_INCLUDE_CLIENT_ID') ?: '')) === '1') $fields['client_id'] = $provider['client_id'];
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
    } elseif ($providerId === 'fitbit') {
        $externalId = isset($token['user_id']) ? (string) $token['user_id'] : null;
        if ($externalId !== null && $externalId !== '') $metadata['fitbit_user_id'] = $externalId;
    } elseif ($providerId === 'suunto') {
        $claim = stridebr_integrations_jwt_claim((string) ($token['access_token'] ?? ''), 'user');
        if (is_scalar($claim) && trim((string) $claim) !== '') {
            $externalId = trim((string) $claim);
            $externalName = $externalId;
            $metadata['suunto_user'] = $externalId;
        }
    }
    $expiresAt = null;
    if (is_numeric($token['expires_at'] ?? null)) $expiresAt = (new DateTimeImmutable('@' . (int) $token['expires_at']))->format('c');
    elseif (is_numeric($token['expires_in'] ?? null)) $expiresAt = (new DateTimeImmutable('now'))->modify('+' . max(0, (int) $token['expires_in']) . ' seconds')->format('c');
    $stmt = $pdo->prepare(
        "INSERT INTO integracoes_usuario
        (idintegracao, idusuario, provedor, status, usuario_externo_id, usuario_externo_nome, access_token_enc, refresh_token_enc, token_expira_em, escopos, metadados, ultima_sincronizacao_em, ultimo_erro, atualizado_em)
        VALUES (:id, :usuario, :provedor, 'conectado', :externo, :nome, :access, :refresh, :expira, :escopos, CAST(:metadados AS jsonb), NULL, NULL, NOW())
        ON CONFLICT (idusuario, provedor) DO UPDATE SET
            status = 'conectado', usuario_externo_id = COALESCE(EXCLUDED.usuario_externo_id, integracoes_usuario.usuario_externo_id),
            usuario_externo_nome = COALESCE(EXCLUDED.usuario_externo_nome, integracoes_usuario.usuario_externo_nome),
            access_token_enc = EXCLUDED.access_token_enc,
            refresh_token_enc = COALESCE(EXCLUDED.refresh_token_enc, integracoes_usuario.refresh_token_enc),
            token_expira_em = EXCLUDED.token_expira_em, escopos = EXCLUDED.escopos,
            metadados = integracoes_usuario.metadados || EXCLUDED.metadados, ultimo_erro = NULL, atualizado_em = NOW()
        RETURNING *"
    );
    $stmt->execute([
        ':id' => stridebr_integrations_id(),
        ':usuario' => $userId,
        ':provedor' => $providerId,
        ':externo' => $externalId,
        ':nome' => $externalName,
        ':access' => stridebr_integrations_encrypt((string) $token['access_token']),
        ':refresh' => stridebr_integrations_encrypt(trim((string) ($token['refresh_token'] ?? '')) ?: null),
        ':expira' => $expiresAt,
        ':escopos' => is_array($token['scope'] ?? null) ? implode(' ', $token['scope']) : trim((string) ($token['scope'] ?? '')),
        ':metadados' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ]);
    return $stmt->fetch() ?: [];
}

function stridebr_integrations_register_polar(PDO $pdo, string $userId, array $connection): void
{
    $token = stridebr_integrations_decrypt((string) ($connection['access_token_enc'] ?? ''));
    if (!$token) return;
    try {
        $response = stridebr_integrations_http('POST', 'https://www.polaraccesslink.com/v3/users', [
            'headers' => ['Authorization: Bearer ' . $token, 'Accept: application/json', 'Content-Type: application/json'],
            'body' => json_encode(['member-id' => $userId], JSON_UNESCAPED_SLASHES),
        ]);
        $data = $response['json'];
        $polarId = is_array($data) ? (string) ($data['polar-user-id'] ?? '') : '';
        if ($polarId !== '') {
            $stmt = $pdo->prepare("UPDATE integracoes_usuario SET usuario_externo_id = :externo, metadados = metadados || CAST(:meta AS jsonb), atualizado_em = NOW() WHERE idusuario = :usuario AND provedor = 'polar'");
            $stmt->execute([':externo' => $polarId, ':meta' => json_encode(['registered' => true]), ':usuario' => $userId]);
        }
    } catch (Throwable $e) {
        if (!str_contains(stridebr_lower($e->getMessage()), 'already')) throw $e;
    }
}

function stridebr_integrations_disconnect(PDO $pdo, string $userId, string $providerId): void
{
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
        'fitbit' => [
            'RUN' => 'corrida', 'RUNNING' => 'corrida', 'TREADMILL' => 'corrida-em-esteira', 'WALK' => 'caminhada', 'WALKING' => 'caminhada', 'HIKE' => 'trilha', 'HIKING' => 'trilha',
            'BIKE' => 'ciclismo', 'BIKING' => 'ciclismo', 'CYCLING' => 'ciclismo', 'MOUNTAIN BIKE' => 'mountain-bike', 'INDOOR CYCLING' => 'ciclismo-indoor', 'SPINNING' => 'ciclismo-indoor',
            'SWIM' => 'natacao', 'SWIMMING' => 'natacao', 'ROWING' => 'remo',
            'WEIGHTS' => 'musculacao', 'WEIGHT TRAINING' => 'musculacao', 'STRENGTH TRAINING' => 'musculacao', 'WORKOUT' => 'treino-funcional', 'CIRCUIT TRAINING' => 'treino-funcional',
            'TENNIS' => 'tenis', 'BADMINTON' => 'badminton', 'YOGA' => 'yoga', 'BOXING' => 'boxe', 'BASKETBALL' => 'basquete', 'VOLLEYBALL' => 'volei',
        ],
        'suunto' => [
            'RUNNING' => 'corrida', 'RUN' => 'corrida', 'TRAIL RUNNING' => 'corrida-em-trilha', 'TREADMILL' => 'corrida-em-esteira', 'WALKING' => 'caminhada', 'HIKING' => 'trilha', 'TREKKING' => 'trekking',
            'CYCLING' => 'ciclismo', 'ROAD CYCLING' => 'ciclismo-de-estrada', 'MOUNTAIN BIKING' => 'mountain-bike', 'INDOOR CYCLING' => 'ciclismo-indoor',
            'SWIMMING' => 'natacao', 'POOL SWIMMING' => 'natacao-em-piscina', 'OPENWATER SWIMMING' => 'natacao-aguas-abertas', 'ROWING' => 'remo',
            'STRENGTH TRAINING' => 'musculacao', 'CIRCUIT TRAINING' => 'treino-funcional', 'YOGA' => 'yoga', 'TENNIS' => 'tenis', 'FOOTBALL' => 'futebol',
        ],
    ];
    if (isset($maps[$providerId][$key])) return $maps[$providerId][$key];
    if (in_array($providerId, ['fitbit', 'suunto'], true)) {
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
    require_once __DIR__ . '/atividade_modelo.php';
    require_once __DIR__ . '/activity_file_exchange.php';
    $externalId = trim((string) ($activity['external_id'] ?? ''));
    if ($externalId === '' || stridebr_integrations_duplicate($pdo, $userId, $providerId, $externalId)) return null;
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
    $fields = atividadeArquivoPayloadCampos($pdo, (string) $modalidade['idmodelo'], $summary);
    $start = new DateTimeImmutable((string) $activity['start_at']);
    $duration = is_numeric($activity['duration_s'] ?? null) ? max(0, (int) round((float) $activity['duration_s'])) : null;
    $end = $duration !== null ? $start->modify('+' . $duration . ' seconds') : null;
    $defaults = atividadePadroesUsuario($pdo, $userId);
    $title = trim((string) ($activity['title'] ?? '')) ?: (string) $modalidade['nome'];
    $payload = [
        'idmodelo' => (string) $modalidade['idmodelo'],
        'titulo' => $title,
        'observacoes' => trim((string) ($activity['notes'] ?? '')),
        'data_inicio' => $start->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d H:i:s'),
        'data_fim' => $end ? $end->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d H:i:s') : '',
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
    $id = atividadeSalvarRegistro($pdo, $userId, $payload);
    $device = is_array($activity['device'] ?? null) ? $activity['device'] : [];
    $update = $pdo->prepare('UPDATE registros_atividade SET origem_provedor = :provedor, id_externo = :externo, dispositivo_origem = CAST(:device AS jsonb), data_atualizacao = NOW() WHERE idregistro = :registro AND idusuario = :usuario');
    $update->execute([
        ':provedor' => $providerId,
        ':externo' => $externalId,
        ':device' => json_encode($device, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':registro' => $id,
        ':usuario' => $userId,
    ]);
    return $id;
}

function stridebr_integrations_refresh_strava(PDO $pdo, string $userId, array $connection): array
{
    $provider = stridebr_integrations_provider('strava');
    $expires = !empty($connection['token_expira_em']) ? strtotime((string) $connection['token_expira_em']) : null;
    if (!$expires || $expires > time() + 120) return $connection;
    $refresh = stridebr_integrations_decrypt((string) ($connection['refresh_token_enc'] ?? ''));
    if (!$refresh) return $connection;
    $response = stridebr_integrations_http('POST', $provider['token_url'], ['headers' => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'], 'body' => [
        'client_id' => $provider['client_id'],
        'client_secret' => $provider['client_secret'],
        'grant_type' => 'refresh_token',
        'refresh_token' => $refresh,
    ]]);
    $token = $response['json'];
    if (!is_array($token)) throw new RuntimeException('Não foi possível renovar a conexão com o Strava.');
    return stridebr_integrations_save_token($pdo, $userId, 'strava', $token);
}

function stridebr_integrations_sync_strava(PDO $pdo, string $userId, array $connection): int
{
    $connection = stridebr_integrations_refresh_strava($pdo, $userId, $connection);
    $token = stridebr_integrations_decrypt((string) ($connection['access_token_enc'] ?? ''));
    if (!$token) throw new RuntimeException('Reconecte sua conta Strava.');
    $after = !empty($connection['ultima_sincronizacao_em']) ? max(0, strtotime((string) $connection['ultima_sincronizacao_em']) - 172800) : time() - 2592000;
    $url = 'https://www.strava.com/api/v3/athlete/activities?' . http_build_query(['after' => $after, 'page' => 1, 'per_page' => 100]);
    $response = stridebr_integrations_http('GET', $url, ['headers' => ['Authorization: Bearer ' . $token, 'Accept: application/json']]);
    $items = is_array($response['json']) ? $response['json'] : [];
    $created = 0;
    foreach ($items as $item) {
        if (!is_array($item) || empty($item['id']) || empty($item['start_date'])) continue;
        $route = [];
        $polyline = trim((string) ($item['map']['summary_polyline'] ?? ''));
        if ($polyline !== '') $route = stridebr_integrations_polyline($polyline);
        $saved = stridebr_integrations_store_activity($pdo, $userId, 'strava', [
            'external_id' => (string) $item['id'],
            'title' => trim((string) ($item['name'] ?? '')),
            'sport' => (string) ($item['sport_type'] ?? $item['type'] ?? ''),
            'start_at' => (string) $item['start_date'],
            'duration_s' => $item['elapsed_time'] ?? $item['moving_time'] ?? null,
            'distance_m' => $item['distance'] ?? null,
            'elevation_gain_m' => $item['total_elevation_gain'] ?? null,
            'avg_hr' => $item['average_heartrate'] ?? null,
            'max_hr' => $item['max_heartrate'] ?? null,
            'avg_cadence' => $item['average_cadence'] ?? null,
            'avg_power' => $item['average_watts'] ?? null,
            'calories' => $item['calories'] ?? null,
            'route' => $route,
            'device' => ['source' => 'Strava'],
        ]);
        if ($saved !== null) $created++;
    }
    return $created;
}

function stridebr_integrations_sync_polar(PDO $pdo, string $userId, array $connection): int
{
    $token = stridebr_integrations_decrypt((string) ($connection['access_token_enc'] ?? ''));
    if (!$token) throw new RuntimeException('Reconecte sua conta Polar.');
    $response = stridebr_integrations_http('GET', 'https://www.polaraccesslink.com/v3/exercises?route=true', ['headers' => ['Authorization: Bearer ' . $token, 'Accept: application/json']]);
    $items = is_array($response['json']) ? $response['json'] : [];
    $created = 0;
    foreach ($items as $item) {
        if (!is_array($item) || empty($item['id']) || empty($item['start_time'])) continue;
        $duration = stridebr_integrations_iso_duration_seconds((string) ($item['duration'] ?? ''));
        $offset = is_numeric($item['start_time_utc_offset'] ?? null) ? (int) $item['start_time_utc_offset'] : 0;
        $start = new DateTimeImmutable((string) $item['start_time'], new DateTimeZone(sprintf('%+03d:%02d', intdiv($offset, 60), abs($offset) % 60)));
        $route = [];
        foreach ((array) ($item['route'] ?? []) as $point) {
            if (!is_array($point) || !is_numeric($point['longitude'] ?? null) || !is_numeric($point['latitude'] ?? null)) continue;
            $route[] = [(float) $point['longitude'], (float) $point['latitude']];
        }
        $saved = stridebr_integrations_store_activity($pdo, $userId, 'polar', [
            'external_id' => (string) $item['id'],
            'title' => '',
            'sport' => (string) ($item['detailed_sport_info'] ?? $item['sport'] ?? ''),
            'start_at' => $start->format(DateTimeInterface::ATOM),
            'duration_s' => $duration,
            'distance_m' => $item['distance'] ?? null,
            'avg_hr' => $item['heart_rate']['average'] ?? null,
            'calories' => $item['calories'] ?? null,
            'route' => $route,
            'device' => ['source' => 'Polar', 'name' => $item['device'] ?? null, 'id' => $item['device_id'] ?? null],
        ]);
        if ($saved !== null) $created++;
    }
    return $created;
}


function stridebr_integrations_refresh_fitbit(PDO $pdo, string $userId, array $connection): array
{
    $provider = stridebr_integrations_provider('fitbit');
    $expires = !empty($connection['token_expira_em']) ? strtotime((string) $connection['token_expira_em']) : null;
    if (!$expires || $expires > time() + 120) return $connection;
    $refresh = stridebr_integrations_decrypt((string) ($connection['refresh_token_enc'] ?? ''));
    if (!$refresh) return $connection;
    $response = stridebr_integrations_http('POST', $provider['token_url'], [
        'headers' => [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode($provider['client_id'] . ':' . $provider['client_secret']),
        ],
        'body' => [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh,
        ],
    ]);
    $token = $response['json'];
    if (!is_array($token)) throw new RuntimeException('Não foi possível renovar a conexão com o Fitbit.');
    return stridebr_integrations_save_token($pdo, $userId, 'fitbit', $token);
}

function stridebr_integrations_fitbit_start(array $item, ?array $parsed): ?string
{
    $summary = is_array($parsed['summary'] ?? null) ? $parsed['summary'] : [];
    if (is_numeric($summary['start_ts'] ?? null)) return (new DateTimeImmutable('@' . (int) $summary['start_ts']))->format(DateTimeInterface::ATOM);
    foreach (['originalStartTime', 'startTime'] as $key) {
        $value = trim((string) ($item[$key] ?? ''));
        if ($value === '') continue;
        try {
            return (new DateTimeImmutable($value))->format(DateTimeInterface::ATOM);
        } catch (Throwable) {
        }
    }
    return null;
}

function stridebr_integrations_sync_fitbit(PDO $pdo, string $userId, array $connection): int
{
    require_once __DIR__ . '/activity_file_exchange.php';
    $connection = stridebr_integrations_refresh_fitbit($pdo, $userId, $connection);
    $token = stridebr_integrations_decrypt((string) ($connection['access_token_enc'] ?? ''));
    if (!$token) throw new RuntimeException('Reconecte sua conta Fitbit.');
    $after = !empty($connection['ultima_sincronizacao_em'])
        ? (new DateTimeImmutable((string) $connection['ultima_sincronizacao_em']))->modify('-2 days')
        : (new DateTimeImmutable('now'))->modify('-30 days');
    $url = 'https://api.fitbit.com/1/user/-/activities/list.json?' . http_build_query([
        'afterDate' => $after->format('Y-m-d'),
        'sort' => 'asc',
        'offset' => 0,
        'limit' => 100,
    ], '', '&', PHP_QUERY_RFC3986);
    $response = stridebr_integrations_http('GET', $url, [
        'headers' => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
    ]);
    $items = is_array($response['json']['activities'] ?? null) ? $response['json']['activities'] : [];
    $created = 0;
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $logId = trim((string) ($item['logId'] ?? ''));
        if ($logId === '' || stridebr_integrations_duplicate($pdo, $userId, 'fitbit', $logId)) continue;
        $parsed = null;
        try {
            $tcx = stridebr_integrations_http(
                'GET',
                'https://api.fitbit.com/1/user/-/activities/' . rawurlencode($logId) . '.tcx',
                ['headers' => ['Authorization: Bearer ' . $token, 'Accept: application/vnd.garmin.tcx+xml, application/xml, text/xml']]
            );
            if (trim((string) $tcx['body']) !== '') $parsed = atividadeArquivoTcx((string) $tcx['body']);
        } catch (Throwable) {
            $parsed = null;
        }
        $summary = is_array($parsed['summary'] ?? null) ? $parsed['summary'] : [];
        $start = stridebr_integrations_fitbit_start($item, $parsed);
        if ($start === null) continue;
        $duration = is_numeric($summary['duration_s'] ?? null)
            ? (int) round((float) $summary['duration_s'])
            : (is_numeric($item['activeDuration'] ?? null) ? (int) round((float) $item['activeDuration'] / 1000) : (is_numeric($item['duration'] ?? null) ? (int) round((float) $item['duration'] / 1000) : null));
        $coordinates = is_array($summary['coordinates'] ?? null) ? $summary['coordinates'] : [];
        $device = ['source' => 'Fitbit'];
        if (is_array($item['source'] ?? null)) {
            $source = $item['source'];
            if (trim((string) ($source['name'] ?? '')) !== '') $device['name'] = trim((string) $source['name']);
            if (trim((string) ($source['type'] ?? '')) !== '') $device['type'] = trim((string) $source['type']);
        }
        $saved = stridebr_integrations_store_activity($pdo, $userId, 'fitbit', [
            'external_id' => $logId,
            'title' => trim((string) ($item['activityName'] ?? '')),
            'sport' => (string) ($item['activityName'] ?? $parsed['sport'] ?? ''),
            'start_at' => $start,
            'duration_s' => $duration,
            'distance_m' => is_numeric($summary['distance_m'] ?? null) ? (float) $summary['distance_m'] : null,
            'elevation_gain_m' => is_numeric($summary['elevation_gain_m'] ?? null) ? (float) $summary['elevation_gain_m'] : null,
            'avg_hr' => is_numeric($summary['avg_hr'] ?? null) ? (float) $summary['avg_hr'] : null,
            'max_hr' => is_numeric($summary['max_hr'] ?? null) ? (float) $summary['max_hr'] : null,
            'avg_cadence' => is_numeric($summary['avg_cadence'] ?? null) ? (float) $summary['avg_cadence'] : null,
            'avg_power' => is_numeric($summary['avg_power'] ?? null) ? (float) $summary['avg_power'] : null,
            'calories' => is_numeric($item['calories'] ?? null) ? (float) $item['calories'] : (is_numeric($summary['calories'] ?? null) ? (float) $summary['calories'] : null),
            'route' => $coordinates,
            'device' => $device,
        ]);
        if ($saved !== null) $created++;
    }
    return $created;
}

function stridebr_integrations_refresh_suunto(PDO $pdo, string $userId, array $connection): array
{
    $provider = stridebr_integrations_provider('suunto');
    $expires = !empty($connection['token_expira_em']) ? strtotime((string) $connection['token_expira_em']) : null;
    if (!$expires || $expires > time() + 120) return $connection;
    $refresh = stridebr_integrations_decrypt((string) ($connection['refresh_token_enc'] ?? ''));
    if (!$refresh) return $connection;
    $response = stridebr_integrations_http('POST', (string) $provider['token_url'], [
        'headers' => [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode((string) $provider['client_id'] . ':' . (string) $provider['client_secret']),
        ],
        'body' => ['grant_type' => 'refresh_token', 'refresh_token' => $refresh],
    ]);
    if (!is_array($response['json']) || trim((string) ($response['json']['access_token'] ?? '')) === '') {
        throw new RuntimeException('Não foi possível renovar a conexão com a Suunto.');
    }
    return stridebr_integrations_save_token($pdo, $userId, 'suunto', $response['json']);
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

function stridebr_integrations_sync_suunto(PDO $pdo, string $userId, array $connection): int
{
    $connection = stridebr_integrations_refresh_suunto($pdo, $userId, $connection);
    $provider = stridebr_integrations_provider('suunto');
    $token = stridebr_integrations_decrypt((string) ($connection['access_token_enc'] ?? ''));
    $subscriptionKey = trim((string) ($provider['subscription_key'] ?? ''));
    if (!$token || $subscriptionKey === '') throw new RuntimeException('Reconecte sua conta Suunto ou configure a chave da Cloud API.');
    $response = stridebr_integrations_http('GET', rtrim((string) $provider['api_base_url'], '/') . '/v2/workouts', [
        'headers' => [
            'Authorization: Bearer ' . $token,
            'Ocp-Apim-Subscription-Key: ' . $subscriptionKey,
            'Accept: application/json',
        ],
    ]);
    $items = is_array($response['json']) ? $response['json'] : [];
    if (isset($items['workouts']) && is_array($items['workouts'])) $items = $items['workouts'];
    $created = 0;
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $externalId = trim((string) ($item['workoutKey'] ?? $item['id'] ?? ''));
        $start = stridebr_integrations_suunto_start($item);
        if ($externalId === '' || $start === null || stridebr_integrations_duplicate($pdo, $userId, 'suunto', $externalId)) continue;
        $hr = is_array($item['hrdata'] ?? null) ? $item['hrdata'] : [];
        $activityName = trim((string) ($item['activityName'] ?? $item['sport'] ?? $item['activity'] ?? ''));
        if ($activityName === '' && (string) ($item['activityId'] ?? '') === '1') $activityName = 'running';
        $device = ['source' => 'Suunto'];
        foreach (['deviceName' => 'name', 'device' => 'name', 'productName' => 'name'] as $sourceKey => $targetKey) {
            if (!empty($item[$sourceKey]) && is_scalar($item[$sourceKey])) $device[$targetKey] = trim((string) $item[$sourceKey]);
        }
        $saved = stridebr_integrations_store_activity($pdo, $userId, 'suunto', [
            'external_id' => $externalId,
            'title' => trim((string) ($item['description'] ?? $item['name'] ?? '')),
            'sport' => $activityName !== '' ? $activityName : (string) ($item['activityId'] ?? ''),
            'start_at' => $start,
            'duration_s' => is_numeric($item['totalTime'] ?? null) ? (float) $item['totalTime'] : (is_numeric($item['duration'] ?? null) ? (float) $item['duration'] : null),
            'distance_m' => is_numeric($item['totalDistance'] ?? null) ? (float) $item['totalDistance'] : null,
            'elevation_gain_m' => is_numeric($item['totalAscent'] ?? null) ? (float) $item['totalAscent'] : null,
            'avg_hr' => is_numeric($hr['workoutAvgHR'] ?? null) ? (float) $hr['workoutAvgHR'] : (is_numeric($item['avgHeartRate'] ?? null) ? (float) $item['avgHeartRate'] : null),
            'max_hr' => is_numeric($hr['workoutMaxHR'] ?? null) ? (float) $hr['workoutMaxHR'] : (is_numeric($item['maxHeartRate'] ?? null) ? (float) $item['maxHeartRate'] : null),
            'avg_cadence' => is_numeric($item['avgCadence'] ?? null) ? (float) $item['avgCadence'] : null,
            'avg_power' => is_numeric($item['avgPower'] ?? null) ? (float) $item['avgPower'] : null,
            'calories' => is_numeric($item['energyConsumption'] ?? null) ? (float) $item['energyConsumption'] : (is_numeric($item['calories'] ?? null) ? (float) $item['calories'] : null),
            'route' => [],
            'device' => $device,
        ]);
        if ($saved !== null) $created++;
    }
    return $created;
}

function stridebr_integrations_sync(PDO $pdo, string $userId, string $providerId): int
{
    $connection = stridebr_integrations_get($pdo, $userId, $providerId);
    if (!$connection || !in_array((string) ($connection['status'] ?? ''), ['conectado', 'erro'], true)) throw new InvalidArgumentException('Essa conta não está conectada.');
    if (!stridebr_db_bool($connection['sincronizar_atividades'] ?? true)) return 0;
    try {
        $created = match ($providerId) {
            'strava' => stridebr_integrations_sync_strava($pdo, $userId, $connection),
            'polar' => stridebr_integrations_sync_polar($pdo, $userId, $connection),
            'fitbit' => stridebr_integrations_sync_fitbit($pdo, $userId, $connection),
            'suunto' => stridebr_integrations_sync_suunto($pdo, $userId, $connection),
            default => throw new RuntimeException('A sincronização automática deste serviço será ativada quando a API do provedor estiver configurada.'),
        };
        $stmt = $pdo->prepare("UPDATE integracoes_usuario SET ultima_sincronizacao_em = NOW(), ultimo_erro = NULL, status = 'conectado', atualizado_em = NOW() WHERE idusuario = :usuario AND provedor = :provedor");
        $stmt->execute([':usuario' => $userId, ':provedor' => $providerId]);
        return $created;
    } catch (Throwable $e) {
        $stmt = $pdo->prepare("UPDATE integracoes_usuario SET ultimo_erro = :erro, status = 'erro', atualizado_em = NOW() WHERE idusuario = :usuario AND provedor = :provedor");
        $stmt->execute([':erro' => substr($e->getMessage(), 0, 1000), ':usuario' => $userId, ':provedor' => $providerId]);
        throw $e;
    }
}
