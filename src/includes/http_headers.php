<?php

declare(strict_types=1);
require_once __DIR__ . '/environment.php';
require_once __DIR__ . '/seo.php';

function stridebr_content_security_policy(bool $embedded = false): string
{
    $scripts = "'self' https://unpkg.com";
    $frames = "'self'";
    $connect = "'self' https://unpkg.com";
    if (stridebr_env_enabled('STRIDEBR_ADS_ENABLED') && preg_match('/^ca-pub-\d+$/D', (string) getenv('STRIDEBR_ADSENSE_CLIENT'))) {
        $scripts .= ' https://pagead2.googlesyndication.com';
        $frames .= ' https://*.googlesyndication.com https://*.doubleclick.net';
        $connect .= ' https://*.googlesyndication.com https://*.doubleclick.net';
    }
    return "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors " . ($embedded ? "'self'" : "'none'")
        . "; form-action 'self'; script-src {$scripts}; style-src 'self' 'unsafe-inline' https://unpkg.com; img-src 'self' data: blob: https:; media-src 'self' blob:; worker-src 'self' blob:; frame-src {$frames}; connect-src {$connect}";
}
function stridebr_send_security_headers(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) return;
    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $embedded = in_array($path, ['/user/editatividade.php','/function/apagaratividade.php'], true) && ($_GET['embed'] ?? '') === '1';
    header('Content-Security-Policy: ' . stridebr_content_security_policy($embedded));
    header('X-Frame-Options: ' . ($embedded ? 'SAMEORIGIN' : 'DENY'));
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(self)');
    if (stridebr_robots_noindex() || !stridebr_seo_public_path($path) || ($path === '/calendario.php' && isset($_GET['salvos']))) header('X-Robots-Tag: noindex, nofollow, noarchive');
    // TLS redirects and HSTS are owned by Infra; no header on local HTTP.
}
