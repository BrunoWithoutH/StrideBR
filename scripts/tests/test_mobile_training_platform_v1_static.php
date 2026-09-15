<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Mobile Training Platform v1 failed: {$message}\n");
        exit(1);
    }
};

$router = $read('public/api/v1/index.php');
$workouts = $read('src/function/api_workouts.php');
$platform = $read('src/function/api_training_platform.php');
$service = $read('src/function/training_platform_service.php');
$sessions = $read('src/function/api_workout_sessions.php');
$docs = $read('docs/MOBILE_TRAINING_API.md');
$openapi = $read('docs/api/openapi.yaml');

foreach (['workout-schedules', 'workout-templates', "if (\$route === 'exercises')", "parts[2] === 'history'", "if (\$method === 'DELETE')"] as $needle) {
    $assert(str_contains($router, $needle), 'Router precisa expor ' . $needle);
}
foreach (['stridebr_api_training_exercises', 'stridebr_api_training_exercise_create', 'stridebr_api_training_exercise_update', 'stridebr_api_training_exercise_history', 'stridebr_api_training_template_save', 'stridebr_api_training_recurring_create', 'stridebr_api_training_recurring_update'] as $fn) {
    $assert(str_contains($platform, 'function ' . $fn), 'Plataforma precisa implementar ' . $fn);
}
$assert(str_contains($service, 'treinoEstruturaNormalizar') && str_contains($service, 'treinoAgendadoSalvarEstrutura') && str_contains($service, 'treinoTemplateSalvarEstrutura'), 'Editor deve reutilizar serviço estrutural compartilhado.');
$assert(str_contains($service, 'beginTransaction') && str_contains($service, "DELETE FROM treinos_agendados_exercicios"), 'Salvar estrutura agendada precisa ser transacional.');
foreach (['sets', 'repetitions', 'load', 'rest_s', 'block', 'cluster', 'duration_s', 'distance_m', 'intensity', 'rpe', 'rir', 'cadence'] as $field) {
    $assert(str_contains($service, "'{$field}'") || str_contains($service, "['{$field}']"), 'Editor deve reconhecer ' . $field);
}
$assert(str_contains($workouts, "array_key_exists('structure', \$payload)") && str_contains($workouts, 'treinoAgendadoSalvarEstrutura'), 'POST/PATCH workout devem aceitar estrutura rica.');
$assert(str_contains($workouts, "array_key_exists('recurrence', \$payload)") && str_contains($workouts, 'stridebr_api_training_recurring_create'), 'POST workout deve suportar recorrência canônica existente.');
$assert(str_contains($workouts, 'stridebr_api_training_recurring_update'), 'PATCH workout deve suportar escopos da recorrência real.');
$assert(str_contains($platform, "['this', 'future', 'all']"), 'Recorrência precisa expor this/future/all sem RRULE paralelo.');
$assert(!str_contains($platform, 'RRULE') && !str_contains($platform, 'rrule'), 'Plataforma não pode inventar RRULE.');
$assert(str_contains($workouts, "'blocks' =>") || str_contains($platform, "'blocks' =>"), 'Estrutura deve agrupar exercícios por bloco.');
$assert(str_contains($workouts, "'can_edit'") && str_contains($workouts, "'can_start_gps'") && str_contains($workouts, "'can_start_session'") && str_contains($workouts, "'can_quick_register'"), 'Capabilities precisam orientar o Mobile.');
$assert(str_contains($platform, 'TrainingPlatformVersionConflictException') && str_contains($platform, "'if_version'"), 'Editor deve oferecer proteção opcional contra overwrite concorrente.');
$assert(str_contains($platform, "'workout_create'") && str_contains($platform, 'pg_advisory_xact_lock') && str_contains($router, 'stridebr_api_training_optional_idempotency_key'), 'POST workout deve oferecer idempotência opcional sem domínio paralelo.');
$assert(str_contains($sessions, "'planned_repetitions'") && str_contains($sessions, "'actual_repetitions'") && str_contains($sessions, "'planned_load'") && str_contains($sessions, "'actual_load'"), 'Séries devem separar planejado e realizado.');
$assert(str_contains($sessions, "'rest_s'"), 'Sessão deve expor descanso em segundos quando derivável.');
$assert(str_contains($platform, 'series_exercicio_atividade') && str_contains($platform, "'best_load_kg'"), 'Histórico deve vir do domínio real da Activity.');
$assert(str_contains($platform, '(e.idusuario IS NULL OR e.idusuario=:user)'), 'Catálogo de exercícios precisa respeitar ownership.');
$assert(str_contains($platform, 'cronogramaCriarExercicioCompleto') && str_contains($platform, 'cronogramaAtualizarExercicioPessoal') && str_contains($platform, 'cronogramaDesativarExercicioPessoal'), 'CRUD de exercício deve reutilizar domínio Web.');
$assert(str_contains($platform, 'cronogramaSalvarTreinoModelo') && str_contains($platform, 'cronogramaArquivarTreinoModelo'), 'CRUD de template deve reutilizar biblioteca Web.');
$assert(str_contains($workouts, "'version' =>") && str_contains($platform, 'stridebr_api_training_version'), 'Recursos editáveis devem expor versão estável.');
$assert(str_contains($workouts, 'ta.data_treino BETWEEN :from AND :to') && !str_contains($workouts, 'SELECT tae.* FROM treinos_agendados_exercicios tae WHERE tae.idagendamento = ta.idagendamento'), 'Calendário deve permanecer summary/range sem carregar árvore completa.');
$assert(str_contains($docs, '# MOBILE TRAINING PLATFORM V1 CONTRACT'), 'Docs consolidados precisam conter contrato autocontido.');
foreach (['/workout-schedules:', '/workout-templates:', '/exercises:', '/exercises/{id}/history:', '/workout-sessions/current:', '/workouts/{workout_id}/start:'] as $path) {
    $assert(str_contains($openapi, $path), 'OpenAPI precisa documentar ' . $path);
}
$assert(str_contains($openapi, 'WorkoutStructureInput:') && str_contains($openapi, 'ExerciseCatalogItem:') && str_contains($openapi, 'WorkoutCapabilities:'), 'OpenAPI precisa tipar editor, catálogo e capabilities.');

printf("✓ Mobile Training Platform v1 static: %d assertions\n", $checks);
