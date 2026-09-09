<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
require_once dirname(__DIR__, 2) . '/src/function/integrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') stridebr_error_document(405);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$url = stridebr_app_url() . '/auth/mcp-client-metadata.php';
echo json_encode([
    'client_id' => $url,
    'client_name' => 'StrideBR',
    'client_uri' => stridebr_app_url(),
    'redirect_uris' => [stridebr_integrations_callback_uri('coros')],
    'grant_types' => ['authorization_code', 'refresh_token'],
    'response_types' => ['code'],
    'token_endpoint_auth_method' => 'none',
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
