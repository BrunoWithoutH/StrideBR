<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$assertions = 0;
$ok = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$definition = $read('src/function/workout_definition.php');
$agenda = $read('public/user/agenda-mensal.php');
$share = $read('src/function/cronograma_compartilhar.php');
$export = $read('public/user/cronogramatreinos.php');
$draft = $read('public/user/exerciciosrascunho.php');
$draftJs = $read('public/assets/js/exercicios-rascunho.js');
$coach = $read('src/layout/trainer/coach_prescription_modal.php');
$coachPage = $read('public/user/treinador.php');
$preview = $read('src/function/workout_preview_service.php');
$previewApi = $read('public/api/cronograma-treino-preview.php');
$session = $read('src/function/workout_session_service.php');
$sessionSnapshotMigration = $read('src/database/migrations/20260924_session_snapshot_fidelity.sql');
$sessionJs = $read('public/assets/js/workout-session.js');
$sessionApi = $read('src/function/api_workout_sessions.php');
$workoutApi = $read('src/function/api_workouts.php');
$openapi = $read('docs/api/openapi.yaml');
$prescription = $read('src/function/workout_prescription_v2.php');
$previewJs = $read('public/assets/js/cronogramas.js');
$builderCss = $read('public/assets/css/cronogramas.css');
$library = $read('public/user/biblioteca.php');

foreach (['workoutDefinitionFromSchedule','workoutDefinitionFromTemplate','workoutDefinitionFromScheduled','workoutDefinitionFromSessionSnapshot','workoutDefinitionMaterializeScheduled','workoutDefinitionPresentation','workoutDefinitionCapabilities'] as $function) {
    $ok(str_contains($definition, 'function ' . $function . '('), "Canonical workout definition helper missing: {$function}");
}
$ok(str_contains($agenda, 'workoutDefinitionFromSchedule'), 'Agenda copy does not load canonical workout definition');
$ok(str_contains($agenda, 'workoutDefinitionWriteScheduledItems'), 'Agenda copy does not use full-fidelity scheduled materializer');
$ok(!str_contains($agenda, 'INSERT INTO treinos_agendados_exercicios (idagendamento_exercicio'), 'Agenda still owns partial exercise-copy SQL');
$ok(str_contains($share, "'definition' =>") || str_contains($share, "['definition']"), 'Shared schedule serializer does not carry canonical definition');
$ok(str_contains($export, 'compartilhamentoCronogramaSnapshot'), 'StrideBR export is not using the shared canonical serializer');
$ok(str_contains($export, 'compartilhamentoImportarSnapshot'), 'StrideBR import is not using the shared canonical importer');
$ok(str_contains($draft, 'workout_builder.php') && str_contains($draft, 'data-workout-builder'), 'Draft does not use shared Workout Builder primitive');
$ok(str_contains($draftJs, 'StrideBRWorkoutBuilder') && str_contains($draftJs, '.hydrate(') && str_contains($draftJs, '.serialize('), 'Draft does not use shared Builder state API');
$ok(str_contains($coach, 'workout_builder.php') && str_contains($coach, 'data-workout-builder'), 'Coach editor does not use shared Workout Builder primitive');
$ok(str_contains($coachPage, "is_array(\$post['rows'] ?? null)"), 'Coach persistence does not accept shared Builder rows');
$ok(str_contains($preview, 'workoutDefinitionPresentation'), 'Workout preview does not use canonical presentation model');
$ok(str_contains($previewApi, "'presentation' =>"), 'Preview API does not expose canonical presentation model');
$ok(str_contains($previewApi, "'capabilities' =>"), 'Preview API does not expose execution/quick-complete capabilities');
$ok(str_contains($session, 'function sessaoExecutionSequence('), 'Session domain does not define canonical group execution sequence');
$ok(str_contains($session, "':tracking_mode' =>") && str_contains($session, "':tipo_passo' =>"), 'Session snapshot does not persist tracking/step semantics');
$ok(str_contains($session, "':repeticoes_bloco' =>") && str_contains($session, "':recuperacao_duracao_s' =>"), 'Session snapshot does not persist endurance semantics');
$ok(str_contains($sessionSnapshotMigration, 'ADD COLUMN IF NOT EXISTS tracking_mode') && str_contains($sessionSnapshotMigration, 'ADD COLUMN IF NOT EXISTS tipo_passo'), 'Session snapshot fidelity migration is missing tracking/step columns');
$ok(str_contains($sessionSnapshotMigration, 'ADD COLUMN IF NOT EXISTS repeticoes_bloco') && str_contains($sessionSnapshotMigration, 'ADD COLUMN IF NOT EXISTS recuperacao_distancia_m'), 'Session snapshot fidelity migration is missing endurance columns');
$ok(str_contains($sessionJs, 'renderGroupFromSequence'), 'Workout Session UI does not render domain execution sequence');
$ok(str_contains($sessionJs, 'session?.execution_sequence'), 'Workout Session UI ignores domain execution sequence');
$ok(str_contains($builderCss, '.wb-group-selector{display:flex;justify-content:center'), 'Mobile-width Builder hides group-authoring selector');
$ok(!preg_match('/propagar_vinculados[^>]+checked/i', $library), 'Library propagation is still default ON');
$ok(str_contains($definition, "'quick_complete_mode'"), 'Quick-complete capability is not centralized in the canonical definition');
$ok(str_contains($definition, "['range','amrap','failure']"), 'Ambiguous rep targets are not classified centrally');
$ok(str_contains($session, "'legacy_round_mismatch'"), 'Legacy group mismatch is not preserved/warned conservatively');
$ok(str_contains($sessionApi, "'execution_sequence' =>"), 'Workout Session API does not expose domain execution sequence');
$ok(str_contains($openapi, 'WorkoutSessionExecutionSequenceItem:'), 'OpenAPI does not document group execution sequence');
$ok(str_contains($workoutApi, 'workoutDefinitionMaterializeScheduled'), 'Template to scheduled API path bypasses canonical materializer');
$ok(str_contains($previewJs, 'data-quick-register-ambiguous'), 'Ambiguous quick completion has no explicit warning in the Web flow');
$ok(str_contains($prescription, "workoutPrescriptionText('workout_builder.rep.failure'"), 'Prescription presenter still hardcodes failure copy instead of i18n');

fwrite(STDOUT, "Training Platform Consistency V1 static: {$assertions} assertions\n");
