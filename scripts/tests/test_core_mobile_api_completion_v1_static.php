<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$router = file_get_contents($root . '/public/api/v1/index.php') ?: '';
$workout = file_get_contents($root . '/src/function/api_workout_sessions.php') ?: '';
$mobile = file_get_contents($root . '/src/function/api_mobile_completion.php') ?: '';
$progress = file_get_contents($root . '/src/function/progress_service.php') ?: '';
$api = file_get_contents($root . '/src/function/api_v1.php') ?: '';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$assert(str_contains($workout, "['duration_s']"), 'Workout adapter precisa aceitar duration_s.');
$assert(str_contains($workout, "['distance_m']"), 'Workout adapter precisa aceitar distance_m.');
$assert(str_contains($workout, "'actual_duration_s'"), 'Workout payload precisa expor actual_duration_s.');
$assert(str_contains($workout, "'actual_distance_m'"), 'Workout payload precisa expor actual_distance_m.');
$assert(str_contains($workout, <<<'TXT'
'duration_s' => is_numeric($latest['duracao_s']
TXT
), 'History precisa usar duração realizada.');
$assert(str_contains($workout, <<<'TXT'
'distance_m' => is_numeric($latest['distancia_m']
TXT
), 'History precisa usar distância realizada.');
$assert(str_contains($router, "activities/manual"), 'Router precisa expor criação manual.');
$assert(str_contains($router, "stridebr_api_mobile_activity_patch"), 'Router precisa expor PATCH Activity.');
$assert(str_contains($router, "stridebr_api_mobile_activity_delete"), 'Router precisa expor DELETE Activity.');
$assert(str_contains($router, "stridebr_api_mobile_profile_patch"), 'Router precisa expor PATCH /me.');
$assert(str_contains($router, "me/privacy"), 'Router precisa expor privacy API.');
$assert(str_contains($router, "route === 'equipment'"), 'Router precisa expor Equipment API.');
$assert(str_contains($router, "stridebr_api_mobile_append_set"), 'Router precisa expor append set.');
$assert(str_contains($router, "parts[1] === 'by-workout'") && str_contains($router, "stridebr_api_mobile_execution_summary"), 'Router precisa expor execution summary.');
$assert(str_contains($mobile, "activity_manual_create"), 'Manual create precisa usar idempotência persistida.');
$assert(str_contains($mobile, "workout_append_set"), 'Append set precisa usar idempotência persistida.');
$assert(str_contains($mobile, "MobileApiIdempotencyConflictException"), 'Contrato precisa distinguir conflito idempotente.');
$assert(str_contains($mobile, "stridebr_api_training_assert_version"), 'Activity PATCH precisa usar versão.');
$assert(str_contains($mobile, "'can_trim_route' => false"), 'Trim precisa permanecer fora da V1.');
$assert(str_contains($mobile, "atividadeSalvarRegistro"), 'Manual Activity precisa reutilizar domínio Web.');
$assert(str_contains($mobile, "atividadeExcluirRegistro"), 'Delete precisa reutilizar soft delete do domínio.');
$assert(str_contains($mobile, "atividadeForcaPersistirSeriesManuais"), 'Manual strength precisa reutilizar domínio de séries.');
$assert(str_contains($mobile, "api_workout_idempotencias"), 'Idempotência precisa reutilizar infraestrutura existente.');
$assert(str_contains($mobile, "PDO::PARAM_BOOL"), 'Privacy precisa tipar boolean PostgreSQL.');
$assert(str_contains($progress, "ra.idmodalidade = :sport_id") || str_contains($progress, "ra.idmodalidade=:sport_id"), 'Progress exercises precisa filtrar modalidade.');
$assert(str_contains($api, "'version'"), 'Activity Detail precisa expor version.');
$assert(str_contains($api, "'capabilities'"), 'Activity Detail precisa expor capabilities.');
$assert(!str_contains($mobile, "can_trim_route' => true"), 'Trim não pode ser habilitado nesta rodada.');
$assert(!str_contains($router, 'Hevy'), 'Router não pode abrir import Hevy.');
$assert(!str_contains($router, 'Strong CSV'), 'Router não pode abrir import Strong.');

echo "Core Mobile API Completion V1 static: {$assertions} assertions\n";
