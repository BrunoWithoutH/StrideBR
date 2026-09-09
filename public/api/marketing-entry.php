<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');

$fetchSite = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
if ($fetchSite === 'cross-site') {
    http_response_code(204);
    exit;
}

$origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
if ($origin !== '') {
    try {
        $expected = stridebr_app_url();
        if (strcasecmp(rtrim($origin, '/'), rtrim($expected, '/')) !== 0) {
            http_response_code(204);
            exit;
        }
    } catch (Throwable) {
    }
}

$raw = file_get_contents('php://input');
if (!is_string($raw) || strlen($raw) > 4096) {
    http_response_code(204);
    exit;
}
$payload = json_decode($raw, true);
if (!is_array($payload)) $payload = [];

try {
    require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
    require_once dirname(__DIR__, 2) . '/src/function/marketing.php';
    stridebr_start_session();
    stridebr_marketing_capture_request($pdo, $payload);
    $path = (string) ($payload['path'] ?? '/');
    if (!str_starts_with($path, '/') || strlen($path) > 300) $path = '/';
    stridebr_marketing_record_event($pdo, 'landing_view', null, $path);
} catch (Throwable $e) {
    error_log('StrideBR acquisition entry failed: ' . get_class($e));
}

http_response_code(204);
