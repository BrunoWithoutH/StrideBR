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
