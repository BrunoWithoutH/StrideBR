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
    stridebr_api_response(200, ['data'=>['api_version'=>'v1', 'server_time'=>(new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM), 'build'=>stridebr_api_build_identifier()]]);
}

if (($parts[0] ?? '') === 'institutional' && !stridebr_teams_enabled()) {
    stridebr_api_error(404, 'not_found', 'Endpoint não encontrado.');
}

try {
    stridebr_api_set_stage('bootstrap.database');
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
        $user = stridebr_api_user($pdo);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        try {
            if ($method === 'GET') stridebr_api_response(200, ['data' => stridebr_api_mobile_me($pdo, (string) $user['idusuario'])]);
            if ($method === 'PATCH') stridebr_api_response(200, ['data' => stridebr_api_mobile_profile_patch($pdo, (string) $user['idusuario'], stridebr_api_json_input())]);
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        }
        stridebr_api_require_method('GET', 'PATCH');
    }
    if ($route === 'me/privacy') {
        $user = stridebr_api_user($pdo);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        try {
            if ($method === 'GET') stridebr_api_response(200, ['data' => stridebr_api_mobile_privacy($pdo, (string) $user['idusuario'])]);
            if ($method === 'PATCH') stridebr_api_response(200, ['data' => stridebr_api_mobile_privacy_patch($pdo, (string) $user['idusuario'], stridebr_api_json_input())]);
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        }
        stridebr_api_require_method('GET', 'PATCH');
    }
    if ($route === 'equipment') {
        $user = stridebr_api_user($pdo);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        try {
            if ($method === 'GET') stridebr_api_response(200, ['data' => stridebr_api_mobile_equipment_list($pdo, (string) $user['idusuario'])]);
            if ($method === 'POST') stridebr_api_response(201, ['data' => stridebr_api_mobile_equipment_save($pdo, (string) $user['idusuario'], stridebr_api_json_input())]);
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        }
        stridebr_api_require_method('GET', 'POST');
    }
    if (count($parts) === 2 && $parts[0] === 'equipment') {
        $user = stridebr_api_user($pdo);
        $equipmentId = rawurldecode($parts[1]);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        try {
            if ($method === 'PATCH') stridebr_api_response(200, ['data' => stridebr_api_mobile_equipment_save($pdo, (string) $user['idusuario'], stridebr_api_json_input(), $equipmentId)]);
            if ($method === 'DELETE') stridebr_api_response(200, ['data' => stridebr_api_mobile_equipment_delete($pdo, (string) $user['idusuario'], $equipmentId)]);
        } catch (MobileApiNotFoundException $e) {
            stridebr_api_error(404, 'not_found', $e->getMessage());
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        }
        stridebr_api_require_method('PATCH', 'DELETE');
    }
    if (($parts[0] ?? '') === 'institutional') {
        stridebr_api_require_method('GET');
        $user = stridebr_api_user($pdo);
        $userId = (string) $user['idusuario'];
        if ($route === 'institutional/context') {
            stridebr_api_response(200, ['data' => stridebr_api_institutional_context($pdo, $userId)]);
        }
        if ($route === 'institutional/competitions') {
            stridebr_api_response(200, ['data' => stridebr_api_institutional_competitions($pdo, $userId)]);
        }
        if (count($parts) === 3 && $parts[1] === 'competitions') {
            $competition = stridebr_api_institutional_competition($pdo, $userId, rawurldecode($parts[2]));
            if ($competition === null) stridebr_api_error(404, 'not_found', 'Competição institucional não encontrada.');
            stridebr_api_response(200, ['data' => $competition]);
        }
        if (count($parts) === 4 && $parts[1] === 'teams' && $parts[3] === 'roster') {
            $seasonRef = trim((string) ($_GET['season'] ?? ''));
            if ($seasonRef === '') stridebr_api_error(422, 'validation_error', 'season é obrigatório.');
            $roster = stridebr_api_institutional_roster($pdo, $userId, rawurldecode($parts[2]), $seasonRef);
            if ($roster === null) stridebr_api_error(404, 'not_found', 'Equipe institucional não encontrada.');
            stridebr_api_response(200, ['data' => $roster]);
        }
        stridebr_api_error(404, 'not_found', 'Endpoint não encontrado.');
    }
    if ($route === 'sports') {
        stridebr_api_require_method('GET');
        $user = stridebr_api_user($pdo);
        try {
            stridebr_api_response(200, ['data' => stridebr_api_sports_catalog($pdo, (string) $user['idusuario'], $_GET)]);
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        }
    }
    if (($parts[0] ?? '') === 'people') {
        $user = stridebr_api_user($pdo);
        $userId = (string) $user['idusuario'];
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        try {
            if ($route === 'people/search') {
                stridebr_api_require_method('GET');
                stridebr_api_response(200, ['data' => stridebr_api_people_search($pdo, $userId, $_GET)]);
            }
            if ($route === 'people/friends') {
                stridebr_api_require_method('GET');
                stridebr_api_response(200, ['data' => stridebr_api_people_friends($pdo, $userId)]);
            }
            if ($route === 'people/friendships') {
                stridebr_api_require_method('POST');
                stridebr_api_response(201, ['data' => stridebr_api_people_friendship_create($pdo, $userId, stridebr_api_json_input())]);
            }
            if (count($parts) === 4 && $parts[1] === 'friendships' && in_array($parts[3], ['accept', 'reject'], true)) {
                stridebr_api_require_method('POST');
                stridebr_api_response(200, ['data' => stridebr_api_people_friendship_respond($pdo, $userId, rawurldecode($parts[2]), $parts[3])]);
            }
            if (count($parts) === 3 && $parts[1] === 'friendships') {
                stridebr_api_require_method('DELETE');
                stridebr_api_response(200, ['data' => stridebr_api_people_friendship_delete($pdo, $userId, rawurldecode($parts[2]))]);
            }
            if ($route === 'people/coaching') {
                if ($method === 'GET') stridebr_api_response(200, ['data' => stridebr_api_people_coaching($pdo, $userId)]);
                if ($method === 'POST') stridebr_api_response(201, ['data' => stridebr_api_people_coaching_create($pdo, $userId, stridebr_api_json_input())]);
                stridebr_api_require_method('GET', 'POST');
            }
            if (count($parts) === 4 && $parts[1] === 'coaching' && in_array($parts[3], ['accept', 'reject'], true)) {
                stridebr_api_require_method('POST');
                stridebr_api_response(200, ['data' => stridebr_api_people_coaching_respond($pdo, $userId, rawurldecode($parts[2]), $parts[3])]);
            }
            if (count($parts) === 4 && $parts[1] === 'coaching' && $parts[3] === 'permissions') {
                stridebr_api_require_method('PATCH');
                stridebr_api_response(200, ['data' => stridebr_api_people_coaching_permissions($pdo, $userId, rawurldecode($parts[2]), stridebr_api_json_input())]);
            }
            if (count($parts) === 3 && $parts[1] === 'coaching') {
                stridebr_api_require_method('DELETE');
                stridebr_api_response(200, ['data' => stridebr_api_people_coaching_delete($pdo, $userId, rawurldecode($parts[2]))]);
            }
            if (count($parts) === 2) {
                stridebr_api_require_method('GET');
                stridebr_api_response(200, ['data' => stridebr_api_people_detail($pdo, $userId, rawurldecode($parts[1]))]);
            }
            stridebr_api_error(404, 'not_found', 'Endpoint de pessoas não encontrado.');
        } catch (PeopleApiFeatureDisabledException $e) {
            stridebr_api_error(503, 'feature_disabled', $e->getMessage());
        } catch (PeopleApiNotFoundException $e) {
            stridebr_api_error(404, 'not_found', $e->getMessage());
        } catch (PeopleApiForbiddenException $e) {
            stridebr_api_error(403, 'forbidden', $e->getMessage());
        } catch (PeopleApiConflictException $e) {
            stridebr_api_error(409, 'invalid_state', $e->getMessage());
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        }
    }
    if (str_starts_with($route, 'progress/')) {
        stridebr_api_require_method('GET');
        $user = stridebr_api_user($pdo);
        try {
            if ($route === 'progress/overview') stridebr_api_response(200, ['data' => stridebr_api_progress_overview($pdo, (string) $user['idusuario'], $_GET)]);
            if ($route === 'progress/timeseries') stridebr_api_response(200, ['data' => stridebr_api_progress_timeseries($pdo, (string) $user['idusuario'], $_GET)]);
            if ($route === 'progress/sports') stridebr_api_response(200, ['data' => stridebr_api_progress_sports($pdo, (string) $user['idusuario'], $_GET)]);
            if ($route === 'progress/calendar') stridebr_api_response(200, ['data' => stridebr_api_progress_calendar($pdo, (string) $user['idusuario'], $_GET)]);
            if ($route === 'progress/cardio') stridebr_api_response(200, ['data' => stridebr_api_progress_cardio($pdo, (string) $user['idusuario'], $_GET)]);
            if ($route === 'progress/strength') stridebr_api_response(200, ['data' => stridebr_api_progress_strength($pdo, (string) $user['idusuario'], $_GET)]);
            if ($route === 'progress/exercises') stridebr_api_response(200, ['data' => stridebr_api_progress_exercises($pdo, (string) $user['idusuario'], $_GET)]);
            if (count($parts) === 3 && $parts[0] === 'progress' && $parts[1] === 'exercises') {
                try {
                    $payload = stridebr_api_progress_exercise($pdo, (string) $user['idusuario'], rawurldecode($parts[2]), $_GET);
                } catch (InvalidArgumentException $e) {
                    if ($e->getMessage() === 'Exercício não encontrado.') stridebr_api_error(404, 'not_found', $e->getMessage());
                    throw $e;
                }
                stridebr_api_response(200, ['data' => $payload]);
            }
            if ($route === 'progress/adherence') stridebr_api_response(200, ['data' => stridebr_api_progress_adherence($pdo, (string) $user['idusuario'], $_GET)]);
            if ($route === 'progress/dashboard') stridebr_api_response(200, ['data' => stridebr_api_progress_dashboard($pdo, (string) $user['idusuario'], $_GET)]);
            stridebr_api_error(404, 'not_found', 'Endpoint de progresso não encontrado.');
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        }
    }
    if ($route === 'workout-sessions/current') {
        stridebr_api_require_method('GET');
        $user = stridebr_api_user($pdo);
        if (!sessaoFeatureAtiva($pdo)) stridebr_api_error(503, 'feature_disabled', 'A execução de treinos está temporariamente desativada.');
        $includeHistory = (string) ($_GET['history'] ?? '1') !== '0';
        stridebr_api_response(200, ['data' => stridebr_api_workout_session_current($pdo, (string) $user['idusuario'], $includeHistory)]);
    }
    if (count($parts) === 3 && $parts[0] === 'workouts' && in_array($parts[2], ['start', 'quick-register'], true)) {
        stridebr_api_require_method('POST');
        $user = stridebr_api_user($pdo);
        if (!sessaoFeatureAtiva($pdo)) stridebr_api_error(503, 'feature_disabled', 'A execução de treinos está temporariamente desativada.');
        $workoutId = rawurldecode($parts[1]);
        try {
            if ($parts[2] === 'start') {
                $session = stridebr_api_workout_session_start($pdo, (string) $user['idusuario'], $workoutId);
                stridebr_api_response(201, ['data' => $session]);
            }
            $result = stridebr_api_workout_quick_register($pdo, (string) $user['idusuario'], $workoutId, stridebr_api_json_input(), stridebr_api_idempotency_key());
            stridebr_api_response(!empty($result['reused']) ? 200 : 201, ['data' => $result]);
        } catch (WorkoutSessionAlreadyActiveException $e) {
            stridebr_api_error(409, 'active_session_exists', $e->getMessage(), ['current_session' => stridebr_api_workout_session_payload($e->session)]);
        } catch (WorkoutSessionIdempotencyConflictException $e) {
            stridebr_api_error(409, 'idempotency_conflict', $e->getMessage());
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        } catch (RuntimeException $e) {
            stridebr_api_error(409, 'invalid_state', $e->getMessage());
        }
    }
    if (count($parts) === 3 && $parts[0] === 'workout-sessions' && $parts[1] === 'by-workout') {
        stridebr_api_require_method('GET');
        $user = stridebr_api_user($pdo);
        try {
            stridebr_api_response(200, ['data' => stridebr_api_mobile_execution_summary($pdo, (string) $user['idusuario'], rawurldecode($parts[2]))]);
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        }
    }
    if (count($parts) >= 3 && $parts[0] === 'workout-sessions') {
        $user = stridebr_api_user($pdo);
        if (!sessaoFeatureAtiva($pdo)) stridebr_api_error(503, 'feature_disabled', 'A execução de treinos está temporariamente desativada.');
        $sessionId = rawurldecode($parts[1]);
        try {
            if (count($parts) === 3 && in_array($parts[2], ['finish', 'cancel', 'mark-all'], true)) {
                stridebr_api_require_method('POST');
                $payload = $parts[2] === 'cancel' ? [] : stridebr_api_json_input();
                $result = match ($parts[2]) {
                    'finish' => stridebr_api_workout_session_finish($pdo, (string) $user['idusuario'], $sessionId, $payload),
                    'cancel' => stridebr_api_workout_session_cancel($pdo, (string) $user['idusuario'], $sessionId),
                    default => stridebr_api_workout_session_mark_all($pdo, (string) $user['idusuario'], $sessionId, $payload),
                };
                stridebr_api_response(200, ['data' => $result]);
            }
            if (count($parts) === 5 && $parts[2] === 'sets' && $parts[4] === 'toggle') {
                stridebr_api_require_method('POST');
                stridebr_api_response(200, ['data' => stridebr_api_workout_session_set_toggle($pdo, (string) $user['idusuario'], $sessionId, rawurldecode($parts[3]), stridebr_api_json_input())]);
            }
            if (count($parts) === 4 && $parts[2] === 'sets') {
                stridebr_api_require_method('PATCH');
                stridebr_api_response(200, ['data' => stridebr_api_workout_session_set_update($pdo, (string) $user['idusuario'], $sessionId, rawurldecode($parts[3]), stridebr_api_json_input())]);
            }
            if (count($parts) === 5 && $parts[2] === 'exercises' && $parts[4] === 'toggle') {
                stridebr_api_require_method('POST');
                stridebr_api_response(200, ['data' => stridebr_api_workout_session_exercise_toggle($pdo, (string) $user['idusuario'], $sessionId, rawurldecode($parts[3]), stridebr_api_json_input())]);
            }
            if (count($parts) === 5 && $parts[2] === 'exercises' && $parts[4] === 'sets') {
                stridebr_api_require_method('POST');
                try {
                    $result = stridebr_api_mobile_append_set($pdo, (string) $user['idusuario'], $sessionId, rawurldecode($parts[3]), stridebr_api_idempotency_key());
                } catch (MobileApiNotFoundException $e) {
                    stridebr_api_error(404, 'not_found', $e->getMessage());
                } catch (MobileApiIdempotencyConflictException $e) {
                    stridebr_api_error(409, 'idempotency_conflict', $e->getMessage());
                }
                stridebr_api_response(!empty($result['reused']) ? 200 : 201, ['data' => ['session' => $result['session'], 'set_id' => $result['set_id'], 'reused' => !empty($result['reused'])]]);
            }
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        } catch (RuntimeException $e) {
            stridebr_api_error(409, 'invalid_state', $e->getMessage());
        }
        stridebr_api_error(404, 'not_found', 'Endpoint de sessão não encontrado.');
    }
    if ($route === 'workout-schedules') {
        $user = stridebr_api_user($pdo);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        try {
            if ($method === 'GET') stridebr_api_response(200, stridebr_api_training_schedules($pdo, (string) $user['idusuario']));
            if ($method === 'POST') stridebr_api_response(201, ['data' => stridebr_api_training_schedule_create($pdo, (string) $user['idusuario'], stridebr_api_json_input())]);
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        }
        stridebr_api_require_method('GET', 'POST');
    }
    if ($route === 'workout-templates') {
        $user = stridebr_api_user($pdo);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        try {
            if ($method === 'GET') stridebr_api_response(200, stridebr_api_workout_templates($pdo, (string) $user['idusuario'], $_GET));
            if ($method === 'POST') stridebr_api_response(201, ['data' => stridebr_api_training_template_save($pdo, (string) $user['idusuario'], stridebr_api_json_input())]);
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        }
        stridebr_api_require_method('GET', 'POST');
    }
    if (count($parts) === 2 && $parts[0] === 'workout-templates') {
        $user = stridebr_api_user($pdo);
        $templateId = rawurldecode($parts[1]);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        try {
            if ($method === 'GET') {
                $template = stridebr_api_workout_template($pdo, (string) $user['idusuario'], $templateId);
                if ($template === []) stridebr_api_error(404, 'not_found', 'Template não encontrado.');
                stridebr_api_response(200, ['data' => $template]);
            }
            if ($method === 'PATCH') stridebr_api_response(200, ['data' => stridebr_api_training_template_save($pdo, (string) $user['idusuario'], stridebr_api_json_input(), $templateId)]);
            if ($method === 'DELETE') {
                stridebr_api_training_template_archive($pdo, (string) $user['idusuario'], $templateId);
                stridebr_api_response(204);
            }
        } catch (TrainingPlatformVersionConflictException $e) {
            stridebr_api_error(409, 'state_conflict', $e->getMessage());
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        } catch (RuntimeException $e) {
            stridebr_api_error(409, 'invalid_state', $e->getMessage());
        }
        stridebr_api_require_method('GET', 'PATCH', 'DELETE');
    }
    if ($route === 'exercises') {
        $user = stridebr_api_user($pdo);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        try {
            if ($method === 'GET') stridebr_api_response(200, stridebr_api_training_exercises($pdo, (string) $user['idusuario'], $_GET));
            if ($method === 'POST') stridebr_api_response(201, ['data' => stridebr_api_training_exercise_create($pdo, (string) $user['idusuario'], stridebr_api_json_input())]);
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        }
        stridebr_api_require_method('GET', 'POST');
    }
    if (count($parts) === 3 && $parts[0] === 'exercises' && $parts[2] === 'history') {
        stridebr_api_require_method('GET');
        $user = stridebr_api_user($pdo);
        try {
            stridebr_api_response(200, stridebr_api_training_exercise_history($pdo, (string) $user['idusuario'], rawurldecode($parts[1]), $_GET));
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(404, 'not_found', $e->getMessage());
        }
    }
    if (count($parts) === 2 && $parts[0] === 'exercises') {
        $user = stridebr_api_user($pdo);
        $exerciseId = rawurldecode($parts[1]);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        try {
            if ($method === 'GET') {
                $exercise = stridebr_api_training_exercise($pdo, (string) $user['idusuario'], $exerciseId);
                if ($exercise === []) stridebr_api_error(404, 'not_found', 'Exercício não encontrado.');
                stridebr_api_response(200, ['data' => $exercise]);
            }
            if ($method === 'PATCH') stridebr_api_response(200, ['data' => stridebr_api_training_exercise_update($pdo, (string) $user['idusuario'], $exerciseId, stridebr_api_json_input())]);
            if ($method === 'DELETE') {
                stridebr_api_training_exercise_archive($pdo, (string) $user['idusuario'], $exerciseId);
                stridebr_api_response(204);
            }
        } catch (TrainingPlatformVersionConflictException $e) {
            stridebr_api_error(409, 'state_conflict', $e->getMessage());
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        } catch (RuntimeException $e) {
            stridebr_api_error(403, 'forbidden', $e->getMessage());
        }
        stridebr_api_require_method('GET', 'PATCH', 'DELETE');
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
            $created = stridebr_api_training_workout_create($pdo, (string) $user['idusuario'], stridebr_api_json_input(), stridebr_api_training_optional_idempotency_key());
            $workout = $created['workout'];
        } catch (WorkoutSessionIdempotencyConflictException $e) {
            stridebr_api_error(409, 'idempotency_conflict', $e->getMessage());
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        }
        header('Location: /api/v1/workouts/' . rawurlencode((string) $workout['id']));
        stridebr_api_response(!empty($created['reused']) ? 200 : 201, ['data' => $workout, 'reused' => !empty($created['reused'])]);
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
            } catch (TrainingPlatformVersionConflictException $e) {
                stridebr_api_error(409, 'state_conflict', $e->getMessage());
            } catch (InvalidArgumentException $e) {
                stridebr_api_error(422, 'validation_error', $e->getMessage());
            } catch (RuntimeException $e) {
                stridebr_api_error(409, 'invalid_state', $e->getMessage());
            }
            stridebr_api_response(200, ['data' => $workout]);
        }
        if ($method === 'DELETE') {
            try {
                stridebr_api_response(200, ['data' => stridebr_api_workout_cancel($pdo, (string) $user['idusuario'], $workoutId)]);
            } catch (RuntimeException $e) {
                stridebr_api_error(409, 'invalid_state', $e->getMessage());
            }
        }
        stridebr_api_require_method('GET', 'PATCH', 'DELETE');
    }
    if ($route === 'zone-profiles') {
        $user = stridebr_api_user($pdo);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        try {
            if ($method === 'GET') stridebr_api_response(200, ['data' => zoneProfileList($pdo, (string) $user['idusuario'], $_GET)]);
            if ($method === 'POST') stridebr_api_response(201, ['data' => zoneProfileSave($pdo, (string) $user['idusuario'], stridebr_api_json_input())]);
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        }
        stridebr_api_require_method('GET', 'POST');
    }
    if (count($parts) === 2 && $parts[0] === 'zone-profiles') {
        $user = stridebr_api_user($pdo);
        $profileId = rawurldecode($parts[1]);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        try {
            $existing = zoneProfileGet($pdo, (string) $user['idusuario'], $profileId);
            if ($existing === []) stridebr_api_error(404, 'not_found', 'Perfil de zonas não encontrado.');
            if ($method === 'GET') stridebr_api_response(200, ['data' => $existing]);
            if ($method === 'PATCH') {
                $input = stridebr_api_json_input();
                $merged = [
                    'profile_type' => $input['profile_type'] ?? $existing['profile_type'],
                    'name' => $input['name'] ?? $existing['name'],
                    'sport' => array_key_exists('sport', $input) ? $input['sport'] : ($existing['sport']['id'] ?? null),
                    'is_default' => array_key_exists('is_default', $input) ? $input['is_default'] : $existing['is_default'],
                    'zones' => $input['zones'] ?? $existing['zones'],
                ];
                stridebr_api_response(200, ['data' => zoneProfileSave($pdo, (string) $user['idusuario'], $merged, $profileId)]);
            }
            if ($method === 'DELETE') {
                zoneProfileDelete($pdo, (string) $user['idusuario'], $profileId);
                stridebr_api_response(204);
            }
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        }
        stridebr_api_require_method('GET', 'PATCH', 'DELETE');
    }
    if ($route === 'pacer-plans/generate') {
        stridebr_api_require_method('POST');
        $user = stridebr_api_user($pdo);
        try {
            stridebr_api_response(200, ['data' => pacerBlueprint($pdo, (string) $user['idusuario'], stridebr_api_json_input())]);
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        }
    }
    if ($route === 'pacer-plans') {
        $user = stridebr_api_user($pdo);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        try {
            if ($method === 'GET') stridebr_api_response(200, ['data' => pacerPlanList($pdo, (string) $user['idusuario'], $_GET)]);
            if ($method === 'POST') stridebr_api_response(201, ['data' => pacerPlanSave($pdo, (string) $user['idusuario'], stridebr_api_json_input())]);
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        }
        stridebr_api_require_method('GET', 'POST');
    }
    if (count($parts) === 3 && $parts[0] === 'pacer-plans' && $parts[2] === 'evaluate') {
        stridebr_api_require_method('POST');
        $user = stridebr_api_user($pdo);
        try {
            stridebr_api_response(200, ['data' => pacerPlanEvaluate($pdo, (string) $user['idusuario'], rawurldecode($parts[1]), stridebr_api_json_input())]);
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Pacer Plan não encontrado.') stridebr_api_error(404, 'not_found', $e->getMessage());
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        } catch (RuntimeException $e) {
            stridebr_api_error(409, 'invalid_state', $e->getMessage());
        }
    }
    if (count($parts) === 2 && $parts[0] === 'pacer-plans') {
        $user = stridebr_api_user($pdo);
        $planId = rawurldecode($parts[1]);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        try {
            $existing = pacerPlanGet($pdo, (string) $user['idusuario'], $planId);
            if ($existing === []) stridebr_api_error(404, 'not_found', 'Pacer Plan não encontrado.');
            if ($method === 'GET') stridebr_api_response(200, ['data' => $existing]);
            if ($method === 'PATCH') {
                $input = stridebr_api_json_input();
                $regeneratesSegments = array_key_exists('sport', $input) || array_key_exists('strategy', $input) || array_key_exists('target_distance_m', $input) || array_key_exists('target_time_s', $input) || array_key_exists('tolerance_s_per_km', $input) || array_key_exists('constraints', $input) || array_key_exists('segments', $input);
                $merged = [
                    'name' => $input['name'] ?? $existing['name'],
                    'sport' => $input['sport'] ?? $existing['sport']['id'],
                    'strategy' => $input['strategy'] ?? $existing['strategy'],
                    'target_distance_m' => $input['target_distance_m'] ?? $existing['target_distance_m'],
                    'target_time_s' => $input['target_time_s'] ?? $existing['target_time_s'],
                    'goal_mode' => $input['goal_mode'] ?? $existing['goal_mode'],
                    'clock_mode' => $input['clock_mode'] ?? $existing['clock_mode'],
                    'tolerance_s_per_km' => $input['tolerance_s_per_km'] ?? $existing['default_tolerance_s_per_km'],
                    'guidance_rules' => $input['guidance_rules'] ?? $existing['guidance_rules'],
                    'status' => $input['status'] ?? $existing['status'],
                    'constraints' => $input['constraints'] ?? [],
                    'segments' => $input['segments'] ?? $existing['segments'],
                    '_preserve_segments' => !$regeneratesSegments,
                ];
                stridebr_api_response(200, ['data' => pacerPlanSave($pdo, (string) $user['idusuario'], $merged, $planId)]);
            }
            if ($method === 'DELETE') {
                pacerPlanArchive($pdo, (string) $user['idusuario'], $planId);
                stridebr_api_response(204);
            }
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        }
        stridebr_api_require_method('GET', 'PATCH', 'DELETE');
    }
    if (count($parts) === 3 && $parts[0] === 'activities' && in_array($parts[2], ['streams', 'splits', 'laps', 'analysis'], true)) {
        $user = stridebr_api_user($pdo);
        $activityId = rawurldecode($parts[1]);
        $resource = $parts[2];
        try {
            if ($resource === 'streams') {
                $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
                if ($method === 'GET') {
                    activityStreamEnsureMaterialized($pdo, (string) $user['idusuario'], $activityId);
                    stridebr_api_response(200, ['data' => activityStreamRead($pdo, (string) $user['idusuario'], $activityId, $_GET)]);
                }
                if ($method === 'PUT') {
                    $key = stridebr_api_idempotency_key();
                    $saved = activityStreamSaveBundle($pdo, (string) $user['idusuario'], $activityId, stridebr_api_json_input(16777216), $key, 'stridebr_android');
                    stridebr_api_response(!empty($saved['reused']) ? 200 : 201, ['data' => $saved]);
                }
                stridebr_api_require_method('GET', 'PUT');
            }
            if ($resource === 'splits') {
                stridebr_api_require_method('GET');
                activityStreamEnsureMaterialized($pdo, (string) $user['idusuario'], $activityId);
                $distance = isset($_GET['distance_m']) ? filter_var($_GET['distance_m'], FILTER_VALIDATE_INT) : 1000;
                if ($distance === false) throw new InvalidArgumentException('distance_m precisa ser inteiro.');
                stridebr_api_response(200, ['data' => activityStreamSplits($pdo, (string) $user['idusuario'], $activityId, (int) $distance)]);
            }
            if ($resource === 'laps') {
                $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
                if ($method === 'GET') stridebr_api_response(200, ['data' => activityStreamLaps($pdo, (string) $user['idusuario'], $activityId)]);
                if ($method === 'PUT') {
                    $input = stridebr_api_json_input(1048576);
                    $laps = is_array($input['laps'] ?? null) ? $input['laps'] : [];
                    stridebr_api_response(200, ['data' => activityStreamSaveLaps($pdo, (string) $user['idusuario'], $activityId, $laps, 'manual', (string) ($input['source'] ?? 'stridebr_android'))]);
                }
                stridebr_api_require_method('GET', 'PUT');
            }
            if ($resource === 'analysis') {
                stridebr_api_require_method('GET');
                $force = (string) ($_GET['recompute'] ?? '0') === '1';
                stridebr_api_response(200, ['data' => activityAnalysisCompute($pdo, (string) $user['idusuario'], $activityId, $force)]);
            }
        } catch (ActivityStreamIdempotencyConflictException $e) {
            stridebr_api_error(409, 'idempotency_conflict', $e->getMessage());
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Atividade não encontrada.') stridebr_api_error(404, 'not_found', $e->getMessage());
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        }
    }
    if ($route === 'activities/manual') {
        stridebr_api_require_method('POST');
        $user = stridebr_api_user($pdo);
        try {
            $created = stridebr_api_mobile_activity_create($pdo, (string) $user['idusuario'], stridebr_api_json_input(4194304), stridebr_api_idempotency_key());
        } catch (MobileApiIdempotencyConflictException $e) {
            stridebr_api_error(409, 'idempotency_conflict', $e->getMessage());
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        } catch (RuntimeException $e) {
            stridebr_api_error(409, 'invalid_state', $e->getMessage());
        }
        header('Location: /api/v1/activities/' . rawurlencode((string) $created['activity']['id']));
        stridebr_api_response(!empty($created['reused']) ? 200 : 201, ['data' => $created['activity'], 'reused' => !empty($created['reused'])]);
    }
    if ($route === 'activities') {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST') {
            $user = stridebr_api_user($pdo);
            try {
                $created = stridebr_api_create_activity($pdo, (string) $user['idusuario'], stridebr_api_json_input(16777216), stridebr_api_idempotency_key());
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
        $user = stridebr_api_user($pdo);
        $activityId = rawurldecode($parts[1]);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        try {
            if ($method === 'GET') {
                $detail = stridebr_api_activity_detail($pdo, $activityId, (string) $user['idusuario']);
                if ($detail === []) stridebr_api_error(404, 'not_found', 'Atividade não encontrada.');
                stridebr_api_response(200, ['data' => $detail]);
            }
            if ($method === 'PATCH') stridebr_api_response(200, ['data' => stridebr_api_mobile_activity_patch($pdo, (string) $user['idusuario'], $activityId, stridebr_api_json_input())]);
            if ($method === 'DELETE') stridebr_api_response(200, ['data' => stridebr_api_mobile_activity_delete($pdo, (string) $user['idusuario'], $activityId)]);
        } catch (MobileApiNotFoundException $e) {
            stridebr_api_error(404, 'not_found', $e->getMessage());
        } catch (TrainingPlatformVersionConflictException $e) {
            stridebr_api_error(409, 'state_conflict', $e->getMessage());
        } catch (InvalidArgumentException $e) {
            stridebr_api_error(422, 'validation_error', $e->getMessage());
        }
        stridebr_api_require_method('GET', 'PATCH', 'DELETE');
    }
    stridebr_api_error(404, 'not_found', 'Endpoint não encontrado.');
} catch (Throwable $e) {
    stridebr_api_log_failure($e);
    stridebr_api_error(500, 'internal_error', 'Não foi possível concluir a requisição.');
}
