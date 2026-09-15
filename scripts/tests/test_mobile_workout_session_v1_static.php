<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "Falha Mobile Workout Session v1: {$message}\n");
        exit(1);
    }
};

$router = $read('public/api/v1/index.php');
$api = $read('src/function/api_workout_sessions.php');
$service = $read('src/function/workout_session_service.php');
$web = $read('src/function/treino_sessao_api.php');
$workouts = $read('src/function/api_workouts.php');
$migration = $read('src/database/migrations/20260914_mobile_workout_session_v1.sql');
$openapi = $read('docs/api/openapi.yaml');
$docs = $read('docs/MOBILE_API.md');

foreach (['workout-sessions/current', "['start', 'quick-register']", "['finish', 'cancel', 'mark-all']", "parts[2] === 'sets'", "parts[2] === 'exercises'"] as $needle) {
    $assert(str_contains($router, $needle), 'Router precisa registrar ' . $needle);
}
$assert(str_contains($web, "require_once __DIR__ . '/workout_session_service.php'"), 'Endpoint Web deve usar serviço compartilhado.');
$assert(!str_contains($web, 'INSERT INTO sessoes_treino_exercicios'), 'Endpoint Web não pode manter cópia da engine de snapshot.');
foreach (['sessaoIniciarCronograma', 'sessaoIniciarAgendado', 'sessaoAtualizarSerie', 'sessaoAlternarSerie', 'sessaoAlternarExercicio', 'sessaoMarcarTudo', 'sessaoRegistroRapidoCronograma', 'sessaoRegistroRapidoAgendado', 'sessaoFinalizar'] as $fn) {
    $assert(str_contains($service, 'function ' . $fn), 'Serviço compartilhado deve expor ' . $fn);
}
$assert(str_contains($service, 'FOR UPDATE') && str_contains($service, "status = 'concluido'"), 'Finalização deve usar lock/estado para idempotência.');
$assert(str_contains($service, 'bloco_snapshot') && str_contains($service, 'cluster_snapshot'), 'Snapshot da sessão deve preservar bloco e cluster.');
$assert(str_contains($migration, 'ADD COLUMN IF NOT EXISTS idmodalidade_origem'), 'Sessão deve preservar modalidade de origem.');
$assert(str_contains($migration, 'ADD COLUMN IF NOT EXISTS bloco_snapshot') && str_contains($migration, 'ADD COLUMN IF NOT EXISTS cluster_snapshot'), 'Migration deve completar snapshot de exercícios.');
$assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS api_workout_idempotencias'), 'Quick register precisa de idempotência persistida.');
$assert(str_contains($migration, 'ON DELETE CASCADE'), 'Idempotência precisa respeitar ownership/exclusão de conta.');
foreach (['workout_id', 'planned_occurrence', 'progress', 'history', 'best_load', 'completed_at'] as $field) {
    $assert(str_contains($api, "'{$field}'"), 'Payload de sessão deve expor ' . $field);
}
$assert(str_contains($api, 'Idempotency-Key já foi usada com outro payload'), 'Quick register deve rejeitar reuso conflitante de chave.');
$assert(substr_count($api, 'FOR UPDATE') >= 2 && str_contains($api, 'treinos_agendados') && str_contains($api, 'treinos_cronograma'), 'Quick register deve serializar pela chave e pela ocorrência do domínio.');
$assert(str_contains($workouts, "'can_start_session'") && str_contains($workouts, "'can_quick_register'") && str_contains($workouts, "'can_start_gps'") && str_contains($workouts, "'preferred_execution'"), 'Workout detail deve expor capabilities de execução.');
$assert(str_contains($workouts, "sportCatalogFamilyKey"), 'Capabilities devem usar taxonomia canônica.');
$assert(!str_contains($migration, 'mobile_workouts') && !str_contains($migration, 'mobile_sessions'), 'Migration não pode criar domínio Mobile paralelo.');
foreach (['/workout-sessions/current', '/workouts/{workout_id}/start', '/workouts/{workout_id}/quick-register', '/workout-sessions/{session_id}/finish', '/workout-sessions/{session_id}/cancel'] as $path) {
    $assert(str_contains($openapi, $path . ':'), 'OpenAPI precisa documentar ' . $path);
}
$assert(str_contains($docs, 'MOBILE WORKOUT SESSION V1'), 'MOBILE_API precisa documentar a sessão nativa.');
$assert(str_contains($docs, 'MOBILE WORKOUT EDITOR API') && str_contains($docs, 'MOBILE TRAINING PLATFORM V1'), 'MOBILE_API precisa apontar para o editor consolidado da Training Platform.');

printf("✓ Mobile Workout Session v1 static: %d assertions\n", $checks);
