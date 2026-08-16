<?php

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

function stridebr_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    if (!headers_sent()) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    session_start();
}

function stridebr_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function stridebr_lower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function stridebr_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function stridebr_db_bool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value)) {
        return $value !== 0;
    }
    return in_array(stridebr_lower(trim((string) $value)), ['1', 't', 'true', 'y', 'yes', 'on'], true);
}

function stridebr_is_logged_in(): bool
{
    return isset($_SESSION['IdUsuario']) && is_string($_SESSION['IdUsuario']) && $_SESSION['IdUsuario'] !== '';
}

function stridebr_user_id(): ?string
{
    return stridebr_is_logged_in() ? $_SESSION['IdUsuario'] : null;
}

function stridebr_require_login(string $loginUrl = '/login.php'): string
{
    if (!stridebr_is_logged_in()) {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        if (str_starts_with($uri, '/') && !str_starts_with($uri, '//')) {
            $_SESSION['previous_page'] = $uri;
        }
        header('Location: ' . $loginUrl);
        exit;
    }

    return $_SESSION['IdUsuario'];
}

function stridebr_safe_redirect(?string $target, string $fallback = '/home.php'): string
{
    if (!is_string($target) || $target === '') {
        return $fallback;
    }

    $parts = parse_url($target);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
        return $fallback;
    }

    if (!str_starts_with($target, '/') || str_starts_with($target, '//') || str_contains($target, '\\') || preg_match('/[\x00-\x1F\x7F]/', $target)) {
        return $fallback;
    }

    return $target;
}

function stridebr_csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function stridebr_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . stridebr_e(stridebr_csrf_token()) . '">';
}

function stridebr_verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(stridebr_csrf_token(), $token)) {
        http_response_code(403);
        exit('Solicitação inválida. Atualize a página e tente novamente.');
    }
}

function stridebr_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function stridebr_take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($flashes) ? $flashes : [];
}

function stridebr_client_ip(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return null;
    }
    return $ip;
}

function stridebr_slug(string $value): string
{
    $value = trim($value);
    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($transliterated)) {
        $value = $transliterated;
    }
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

stridebr_start_session();

function stridebr_display_name(): string
{
    $name = trim((string) ($_SESSION['NomeExibicao'] ?? $_SESSION['NomeUsuario'] ?? ''));
    return $name !== '' ? $name : 'Usuário';
}

function stridebr_username_is_valid(string $username): bool
{
    return preg_match('/^[a-z0-9][a-z0-9._-]{2,39}$/', $username) === 1;
}

function stridebr_user_role(): string
{
    $role = (string) ($_SESSION['PapelUsuario'] ?? 'user');
    return in_array($role, ['user', 'moderator', 'admin', 'owner'], true) ? $role : 'user';
}

function stridebr_role_rank(string $role): int
{
    return match ($role) {
        'owner' => 40,
        'admin' => 30,
        'moderator' => 20,
        default => 10,
    };
}

function stridebr_has_role(string $minimumRole): bool
{
    return stridebr_role_rank(stridebr_user_role()) >= stridebr_role_rank($minimumRole);
}

function stridebr_require_role(string $minimumRole): void
{
    stridebr_require_login();
    if (!stridebr_has_role($minimumRole)) {
        http_response_code(403);
        exit('Você não tem permissão para acessar esta área.');
    }
}

function stridebr_generate_id(int $length = 21): string
{
    $bytes = random_bytes((int) ceil($length * 3 / 4) + 2);
    return substr(rtrim(strtr(base64_encode($bytes), '+/', '-_'), '='), 0, $length);
}

function stridebr_request_ip(): ?string
{
    return stridebr_client_ip();
}

function stridebr_feature_enabled(PDO $pdo, string $key, bool $default = false): bool
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $stmt = $pdo->prepare('SELECT ativo FROM feature_flags WHERE chave = :chave LIMIT 1');
        $stmt->execute([':chave' => $key]);
        $value = $stmt->fetchColumn();
        $cache[$key] = $value === false ? $default : stridebr_db_bool($value);
    } catch (Throwable) {
        $cache[$key] = $default;
    }
    return $cache[$key];
}


function stridebr_terms_version(): string
{
    return trim((string) (getenv('STRIDEBR_TERMS_VERSION') ?: '2026-08-15-alpha-1'));
}

function stridebr_privacy_version(): string
{
    return trim((string) (getenv('STRIDEBR_PRIVACY_VERSION') ?: '2026-08-15-alpha-1'));
}

function stridebr_app_url(): string
{
    $configured = rtrim(trim((string) (getenv('STRIDEBR_APP_URL') ?: '')), '/');
    if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_URL)) {
        $scheme = stridebr_lower((string) parse_url($configured, PHP_URL_SCHEME));
        if (in_array($scheme, ['http', 'https'], true)) {
            return $configured;
        }
    }

    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[a-z0-9.-]+(?::\d{1,5})?$/i', $host)) {
        $host = 'localhost';
    }
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($secure ? 'https://' : 'http://') . $host;
}

function stridebr_destroy_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function stridebr_rate_limit(string $key, int $maxAttempts, int $windowSeconds): bool
{
    $now = time();
    $bucket = $_SESSION['rate_limits'][$key] ?? ['count' => 0, 'started' => $now];
    if (!is_array($bucket)) {
        $bucket = ['count' => 0, 'started' => $now];
    }
    $started = (int) ($bucket['started'] ?? $now);
    if ($now - $started >= $windowSeconds) {
        $bucket = ['count' => 0, 'started' => $now];
    }
    if ((int) ($bucket['count'] ?? 0) >= $maxAttempts) {
        $_SESSION['rate_limits'][$key] = $bucket;
        return false;
    }
    $bucket['count'] = (int) ($bucket['count'] ?? 0) + 1;
    $_SESSION['rate_limits'][$key] = $bucket;
    return true;
}

function stridebr_asset(string $path): string
{
    $path = '/' . ltrim($path, '/');
    $fullPath = dirname(__DIR__, 2) . '/public' . $path;
    if (!is_file($fullPath)) {
        return $path;
    }
    return $path . '?v=' . filemtime($fullPath);
}
