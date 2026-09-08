<?php

declare(strict_types=1);
require_once __DIR__ . '/env.php';

function stridebr_app_env(): string
{
    $env = strtolower(trim((string) (getenv('STRIDEBR_APP_ENV') ?: 'development')));
    if (!in_array($env, ['development', 'staging', 'production'], true)) throw new RuntimeException('Invalid STRIDEBR_APP_ENV');
    return $env;
}
function stridebr_is_development(): bool { return stridebr_app_env() === 'development'; }
function stridebr_is_staging(): bool { return stridebr_app_env() === 'staging'; }
function stridebr_is_production(): bool { return stridebr_app_env() === 'production'; }
function stridebr_env_enabled(string $name, bool $default = false): bool
{
    $value = getenv($name);
    return $value === false || $value === '' ? $default : in_array(strtolower(trim($value)), ['1','true','yes','on'], true);
}
function stridebr_ip_in_cidr(string $ip, string $cidr): bool
{
    $parts = explode('/', trim($cidr));
    $address = @inet_pton($ip); $network = @inet_pton($parts[0]);
    if ($address === false || $network === false || strlen($address) !== strlen($network) || count($parts) > 2) return false;
    $bits = $parts[1] ?? (string) (strlen($address) * 8);
    if (!ctype_digit($bits) || (int) $bits > strlen($address) * 8) return false;
    $bytes = intdiv((int) $bits, 8); $remaining = (int) $bits % 8;
    return substr($address, 0, $bytes) === substr($network, 0, $bytes)
        && ($remaining === 0 || ((ord($address[$bytes]) ^ ord($network[$bytes])) & (255 << (8 - $remaining))) === 0);
}
function stridebr_trusted_proxy(string $ip): bool
{
    foreach (explode(',', (string) getenv('STRIDEBR_TRUSTED_PROXIES')) as $cidr) {
        if (trim($cidr) !== '' && !preg_match('#/0+$#', trim($cidr)) && stridebr_ip_in_cidr($ip, $cidr)) return true;
    }
    return false;
}
function stridebr_client_ip(): ?string
{
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (!filter_var($remote, FILTER_VALIDATE_IP)) return null;
    if (!stridebr_trusted_proxy($remote)) return $remote;
    // Strip trusted hops from the right. Never select an unverified leftmost value.
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $raw = (string) $_SERVER['HTTP_X_FORWARDED_FOR'];
        if (strlen($raw) > 2048) return $remote;
        $chain = array_map('trim', explode(',', $raw));
        if (count($chain) > 32) return $remote;
        foreach ($chain as $ip) if (!filter_var($ip, FILTER_VALIDATE_IP)) return $remote;
        $chain[] = $remote;
        while (count($chain) > 1 && stridebr_trusted_proxy($chain[count($chain) - 1])) array_pop($chain);
        return $chain[count($chain) - 1];
    }
    $real = (string) ($_SERVER['HTTP_X_REAL_IP'] ?? '');
    return filter_var($real, FILTER_VALIDATE_IP) ? $real : $remote;
}
function stridebr_request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') return true;
    return stridebr_trusted_proxy((string) ($_SERVER['REMOTE_ADDR'] ?? ''))
        && strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))) === 'https';
}
function stridebr_app_url(): string
{
    $url = rtrim(trim((string) getenv('STRIDEBR_APP_URL')), '/');
    if ($url === '' && stridebr_is_development()) return 'http://localhost:8080';
    $parts = parse_url($url);
    if (!is_array($parts) || !filter_var($url, FILTER_VALIDATE_URL)
        || !in_array($parts['scheme'] ?? '', ['http','https'], true)
        || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['query']) || isset($parts['fragment']) || !empty($parts['path'])
        || (!stridebr_is_development() && $parts['scheme'] !== 'https')) {
        throw new RuntimeException('STRIDEBR_APP_URL must be an origin URL; HTTPS is required in staging/production.');
    }
    return $url;
}
function stridebr_public_url(): string { return stridebr_app_url(); }
function stridebr_secure_cookie(): bool
{
    return !stridebr_is_development() || stridebr_request_is_https() || parse_url(stridebr_app_url(), PHP_URL_SCHEME) === 'https';
}
function stridebr_robots_noindex(): bool { return stridebr_env_enabled('STRIDEBR_ROBOTS_NOINDEX', stridebr_is_staging()); }
