<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [
    'page' => $root . '/public/user/treinador.php',
    'domain' => $root . '/src/function/treinador.php',
    'migration' => $root . '/src/database/migrations/20260920_coach_workspace_v1.sql',
    'workspace' => $root . '/src/layout/trainer/coach_workspace.php',
    'nav' => $root . '/src/layout/trainer/coach_nav.php',
    'overview' => $root . '/src/layout/trainer/coach_overview.php',
    'athletes' => $root . '/src/layout/trainer/coach_athletes.php',
    'athlete' => $root . '/src/layout/trainer/coach_athlete_workspace.php',
    'library' => $root . '/src/layout/trainer/coach_library.php',
    'modal' => $root . '/src/layout/trainer/coach_prescription_modal.php',
    'athleteSide' => $root . '/src/layout/trainer_as_athlete.php',
    'js' => $root . '/public/assets/js/trainer.js',
    'css' => $root . '/public/assets/css/cronogramas.css',
    'pt' => $root . '/src/i18n/pt-BR.php',
    'en' => $root . '/src/i18n/en.php',
];
foreach ($files as $name => $path) {
    if (!is_file($path)) throw new RuntimeException("Coach Workspace V1 file missing: {$name}");
    $files[$name] = (string) file_get_contents($path);
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$assert(str_contains($files['migration'], 'aplicacoes_cronograma_treinador'), 'Plan application table missing');
$assert(str_contains($files['migration'], 'comentarios_treino'), 'Workout comments table missing');
$assert(str_contains($files['migration'], 'idaplicacao_cronograma'), 'Scheduled workout application provenance missing');
$assert(str_contains($files['migration'], 'ux_treinos_agendados_aplicacao_ocorrencia'), 'Materialization uniqueness missing');
$assert(!str_contains($files['migration'], 'DROP TABLE'), 'Migration must stay additive');

foreach (['treinadorWorkspaceOverview','treinadorListarAtletasWorkspace','treinadorCalendarioAtleta','treinadorPreverAplicacaoCronograma','treinadorAplicarCronograma','treinadorRemoverAplicacaoCronograma','treinadorCompararPlanejadoRealizado','treinadorCriarComentario'] as $fn) {
    $assert(str_contains($files['domain'], 'function ' . $fn . '('), "Domain helper missing: {$fn}");
}
$assert(str_contains($files['domain'], 'ta.idcriador=:trainer AND ta.idvinculo=:link'), 'Calendar/conflict queries must bind coach and link');
$assert(str_contains($files['domain'], "['view'=>true,'edit'=>\$mutable,'cancel'=>\$mutable,'analyze'"), 'Calendar capabilities missing');
$assert(str_contains($files['domain'], "['view'=>true,'edit'=>false,'cancel'=>false"), 'Athlete personal schedule must remain read-only');
$assert(str_contains($files['domain'], "status='removido'"), 'Application removal is not logical');
$assert(str_contains($files['domain'], "NOT EXISTS (SELECT 1 FROM sessoes_treino"), 'Application removal must preserve started workouts');
$assert(str_contains($files['domain'], 'stridebr_exercise_catalog_for_user($pdo,$idTreinador)'), 'Coach catalog preload missing');
$assert(!preg_match('/cronogramaCriar\s*\(\s*\$pdo\s*,\s*\$idAtleta/', $files['domain']), 'Coach must not impersonate athlete schedule ownership');

$assert(str_contains($files['workspace'], 'coach_nav.php'), 'Coach workspace navigation decomposition missing');
$assert(str_contains($files['nav'], 'aria-current'), 'Server navigation must expose aria-current');
$assert(!str_contains($files['nav'], 'role="tablist"'), 'Navigation must not fake tab semantics');
$assert(str_contains($files['athletes'], '<table'), 'Desktop athlete surface must use semantic table');
$assert(str_contains($files['athletes'], 'name="q"'), 'Athlete search missing');
$assert(str_contains($files['athletes'], 'name="filter"'), 'Athlete filters missing');
$assert(str_contains($files['athletes'], 'trainer.workspace.no_access'), 'Permission-aware no-access copy missing');
$assert(str_contains($files['athlete'], 'data-coach-create-date'), 'Contextual calendar create missing');
$assert(str_contains($files['athlete'], 'trainer.workspace.planned_actual'), 'Planned vs actual surface missing');
$assert(str_contains($files['athlete'], 'trainer.workspace.comments'), 'Contextual comments surface missing');
$assert(str_contains($files['athlete'], 'stridebr_e((string)$comment[\'texto\'])'), 'Comment output must be escaped');
$assert(str_contains($files['library'], 'trainer.workspace.library_workouts'), 'Workout library surface missing');
$assert(str_contains($files['library'], 'trainer.workspace.schedules'), 'Schedule library surface missing');
$assert(str_contains($files['modal'], 'name="exercise_name[]"') && str_contains($files['page'], "/assets/js/exercise-entry.js"), 'Prescription must reuse exercise typeahead');
$assert(substr_count($files['page'], "'idmodalidade' => \$_POST['idmodalidade']") >= 2 && substr_count($files['page'], "'distancia_prevista_m' => \$_POST['distancia_prevista_m']") >= 2, 'Create/edit prescription must preserve sport and planned distance');
$assert(!preg_match('/<select[^>]+(?:exercise|exercicio)[^>]*>[\s\S]{10000,}<\/select>/i', $files['modal']), 'Prescription must not dump exercise catalog into select');
$assert(str_contains($files['page'], '/src/layout/trainer/coach_workspace.php'), 'Coach workspace layout not mounted');
$assert(str_contains($files['page'], 'context=coach'), 'Coach context routing missing');
$assert(str_contains($files['athleteSide'], 'create_comment'), 'Athlete contextual comments missing');

$assert(str_contains($files['js'], "event.key === 'Escape'"), 'Modal Escape handling missing');
$assert(str_contains($files['js'], 'prescriptionTrigger?.focus()'), 'Modal return-focus missing');
$assert(str_contains($files['js'], "event.key !== 'Tab'"), 'Modal focus containment missing');
$assert(str_contains($files['js'], 'data-coach-create-date') || str_contains($files['js'], 'dataset.coachCreateDate'), 'Calendar date prefill missing');
$assert(!str_contains($files['js'], 'history.pushState'), 'Deep links should use browser-native navigation');

foreach (['.coach-athlete-table','.coach-week-grid','.coach-day','.coach-workspace-nav','.trainer-prescription-dialog'] as $selector) {
    $assert(str_contains($files['css'], $selector), "Coach CSS missing {$selector}");
}
$assert(str_contains($files['css'], '@media (max-width: 920px)'), '920 responsive contract missing');
$assert(str_contains($files['css'], '@media (max-width: 760px)'), 'Mobile agenda/list contract missing');
$assert(str_contains($files['css'], 'var(--radius-card)') || str_contains($files['css'], 'var(--radius-control)'), 'Coach UI must reuse radius tokens');

$keys = [
    'trainer.workspace.overview','trainer.workspace.athletes','trainer.workspace.calendar','trainer.workspace.library',
    'trainer.workspace.needs_attention','trainer.workspace.search_athlete','trainer.workspace.add_workout',
    'trainer.workspace.apply_plan','trainer.workspace.planned','trainer.workspace.actual',
    'trainer.workspace.athlete_feedback','trainer.workspace.comments','trainer.workspace.write_comment','trainer.workspace.no_access'
];
foreach ($keys as $key) {
    $needle = "'{$key}'";
    $assert(str_contains($files['pt'], $needle), "PT-BR missing {$key}");
    $assert(str_contains($files['en'], $needle), "EN missing {$key}");
}

echo "Coach Workspace V1 static: {$assertions} assertions\n";
