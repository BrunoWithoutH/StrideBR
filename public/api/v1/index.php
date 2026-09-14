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
    if ($route === 'workouts/schedule') {
        stridebr_api_require_method('GET');
        $user = stridebr_api_user($pdo);
        try {
            $result = stridebr_api_workout_schedule($pdo, (string) $user['idusuario'], $_GET);
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        }
        stridebr_api_response(200, $result);
    }
    if ($route === 'workouts/templates') {
        stridebr_api_require_method('GET');
        $user = stridebr_api_user($pdo);
        stridebr_api_response(200, stridebr_api_workout_templates($pdo, (string) $user['idusuario'], $_GET));
    }
    if (count($parts) === 3 && $parts[0] === 'workouts' && $parts[1] === 'templates') {
        stridebr_api_require_method('GET');
        $user = stridebr_api_user($pdo);
        $template = stridebr_api_workout_template($pdo, (string) $user['idusuario'], $parts[2]);
        if ($template === []) stridebr_api_error(404, 'not_found', 'Treino modelo não encontrado.');
        stridebr_api_response(200, ['data' => $template]);
    }
    if ($route === 'workouts') {
        stridebr_api_require_method('POST');
        $user = stridebr_api_user($pdo);
        try {
            $workout = stridebr_api_workout_create($pdo, (string) $user['idusuario'], stridebr_api_json_input());
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        }
        header('Location: /api/v1/workouts/' . rawurlencode((string) $workout['id']));
        stridebr_api_response(201, ['data' => $workout]);
    }
    if (count($parts) === 3 && $parts[0] === 'workouts' && in_array($parts[2], ['complete', 'cancel'], true)) {
        stridebr_api_require_method('POST');
        $user = stridebr_api_user($pdo);
        $workoutId = rawurldecode($parts[1]);
        try {
            $workout = $parts[2] === 'complete'
                ? stridebr_api_workout_complete($pdo, (string) $user['idusuario'], $workoutId)
                : stridebr_api_workout_cancel($pdo, (string) $user['idusuario'], $workoutId);
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        } catch (RuntimeException $e) {
            stridebr_api_error(409, 'invalid_state', $e->getMessage());
        }
        stridebr_api_response(200, ['data' => $workout]);
    }
    if (count($parts) === 2 && $parts[0] === 'workouts') {
        $user = stridebr_api_user($pdo);
        $workoutId = rawurldecode($parts[1]);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'GET') {
            try {
                $workout = stridebr_api_workout_detail($pdo, (string) $user['idusuario'], $workoutId);
            } catch (InvalidArgumentException $e) {
                stridebr_api_error(404, 'not_found', 'Treino não encontrado.');
            }
            if ($workout === []) stridebr_api_error(404, 'not_found', 'Treino não encontrado.');
            stridebr_api_response(200, ['data' => $workout]);
        }
        if ($method === 'PATCH') {
            try {
                $workout = stridebr_api_workout_update($pdo, (string) $user['idusuario'], $workoutId, stridebr_api_json_input());
            } catch (InvalidArgumentException $e) {
                stridebr_api_error(422, 'validation_error', $e->getMessage());
            } catch (RuntimeException $e) {
                stridebr_api_error(403, 'forbidden', $e->getMessage());
            }
            stridebr_api_response(200, ['data' => $workout]);
        }
        stridebr_api_require_method('GET', 'PATCH');
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
