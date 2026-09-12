<?php

declare(strict_types=1);

ini_set('display_errors', '0');
require_once dirname(__DIR__, 3) . '/src/includes/environment.php';
require_once dirname(__DIR__, 3) . '/src/function/api_v1.php';

$path = trim((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: ''), '/');
$path = preg_replace('#^api/v1/?#', '', $path) ?? '';
$parts = array_values(array_filter(explode('/', $path), static fn(string $part): bool => $part !== ''));
$route = implode('/', $parts);

if ($route === 'health') {
    stridebr_api_require_method('GET');
    stridebr_api_response(200, ['data'=>['status'=>'ok', 'api_version'=>'v1']]);
}
if ($route === 'meta') {
    stridebr_api_require_method('GET');
    stridebr_api_response(200, ['data'=>['api_version'=>'v1', 'server_time'=>(new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM)]]);
}

try {
    if (!defined('STRIDEBR_API_JSON')) define('STRIDEBR_API_JSON', true);
    require dirname(__DIR__, 3) . '/src/config/pg_config.php';
    if ($route === 'auth/login') {
        stridebr_api_require_method('POST');
        stridebr_api_response(200, ['data'=>stridebr_api_login($pdo, stridebr_api_json_input())]);
    }
    if ($route === 'auth/refresh') {
        stridebr_api_require_method('POST');
        stridebr_api_response(200, ['data'=>stridebr_api_refresh($pdo, stridebr_api_json_input())]);
    }
    if ($route === 'auth/logout') {
        stridebr_api_require_method('POST');
        $user = stridebr_api_user($pdo);
        stridebr_api_logout($pdo, (string) $user['idsessao']);
        stridebr_api_response(204);
    }
    if ($route === 'me') {
        stridebr_api_require_method('GET');
        stridebr_api_response(200, ['data'=>stridebr_api_user_payload(stridebr_api_user($pdo))]);
    }
    if ($route === 'activities') {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST') {
            $user = stridebr_api_user($pdo);
            try {
                $created = stridebr_api_create_activity($pdo, (string) $user['idusuario'], stridebr_api_json_input(524288), stridebr_api_idempotency_key());
            } catch (InvalidArgumentException $e) {
                stridebr_api_error(422, 'validation_error', $e->getMessage());
            }
            header('Location: /api/v1/activities/' . rawurlencode((string) $created['id']));
            stridebr_api_response(!empty($created['reused']) ? 200 : 201, ['data'=>$created]);
        }
        stridebr_api_require_method('GET', 'POST');
        $user = stridebr_api_user($pdo);
        try {
            $result = stridebr_api_list_activities($pdo, (string) $user['idusuario'], $_GET);
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        }
        stridebr_api_response(200, $result);
    }
    if (count($parts) === 2 && $parts[0] === 'activities') {
        stridebr_api_require_method('GET');
        $user = stridebr_api_user($pdo);
        $detail = stridebr_api_activity_detail($pdo, $parts[1], (string)$user['idusuario']);
        if ($detail === []) stridebr_api_error(404, 'not_found', 'Atividade não encontrada.');
        stridebr_api_response(200, ['data'=>$detail]);
    }
    stridebr_api_error(404, 'not_found', 'Endpoint não encontrado.');
} catch (Throwable $e) {
    error_log('StrideBR API v1 failure: ' . get_class($e));
    stridebr_api_error(500, 'internal_error', 'Não foi possível concluir a requisição.');
}
