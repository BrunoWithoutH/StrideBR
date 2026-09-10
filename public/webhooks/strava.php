<?php

declare(strict_types=1);

// app.php supplies shared helpers only; this endpoint never starts a session or CSRF flow.
require_once dirname(__DIR__, 2) . '/src/includes/environment.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
require_once dirname(__DIR__, 2) . '/src/function/integrations.php';

ini_set('display_errors', '0');
header('X-Content-Type-Options: nosniff');
$method = $_SERVER['REQUEST_METHOD'] ?? '';
$config = stridebr_strava_webhook_config();
if ($method === 'GET') {
    $mode = (string) ($_GET['hub.mode'] ?? ''); $token = (string) ($_GET['hub.verify_token'] ?? ''); $challenge = (string) ($_GET['hub.challenge'] ?? '');
    if ($config['verify_token'] === '' || $mode !== 'subscribe' || $challenge === '' || !hash_equals($config['verify_token'], $token)) { http_response_code(403); exit; }
    header('Content-Type: application/json; charset=utf-8'); echo json_encode(['hub.challenge'=>$challenge], JSON_UNESCAPED_SLASHES); exit;
}
if ($method !== 'POST') { header('Allow: GET, POST'); http_response_code(405); exit; }
$contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
if ($contentType !== 'application/json') { http_response_code(415); exit; }
$length = $_SERVER['CONTENT_LENGTH'] ?? null;
if ($length !== null && (!ctype_digit((string) $length) || (int) $length > 16384)) { http_response_code(413); exit; }
$raw = file_get_contents('php://input', false, null, 0, 16385);
if (!is_string($raw) || $raw === '' || strlen($raw) > 16384) { http_response_code(400); exit; }
$signed = $config['signing_secret'] !== '';
if ($signed && !stridebr_strava_webhook_verify_signature($raw, $_SERVER['HTTP_X_STRAVA_SIGNATURE'] ?? null)) { http_response_code(403); exit; }
try { $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); $event = is_array($input) ? stridebr_strava_webhook_event($input) : null; }
catch (Throwable) { $event = null; }
if (!$event) { http_response_code(400); exit; }
if ($config['subscription_id'] === '' && !$signed) { http_response_code(503); exit; }
if ($config['subscription_id'] !== '' && !hash_equals($config['subscription_id'], $event['subscription_id'])) { http_response_code(400); exit; }
$event['signature_verified'] = $signed;
try {
    require dirname(__DIR__, 2) . '/src/config/pg_config.php';
    stridebr_strava_webhook_enqueue($pdo, $event); http_response_code(200); header('Content-Type: text/plain; charset=utf-8'); echo 'EVENT_RECEIVED';
}
catch (Throwable $error) { error_log('StrideBR Strava webhook enqueue failed [' . get_class($error) . ']'); http_response_code(503); }
