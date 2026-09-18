<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$count = 0;
$assert = static function (bool $ok, string $message) use (&$count): void {
    $count++;
    if (!$ok) {
        fwrite(STDERR, "Web Training UX + Pacer Help failed: {$message}\n");
        exit(1);
    }
};

$pacerPage = $read('public/user/pacer.php');
$pacerJs = $read('public/assets/js/pacer-web.js');
$pacerCss = $read('public/assets/css/pacer-web.css');
$footer = $read('src/layout/footer.php');
$prescription = $read('public/assets/js/workout-prescription.js');
$scheduleJs = $read('public/assets/js/cronogramas.js');
$sessionJs = $read('public/assets/js/workout-session.js');
$style = $read('public/assets/css/style.css');
$exercisePage = $read('public/user/exercicioscronograma.php');
$exerciseCss = $read('public/assets/css/cronogramas.css');

$assert(!str_contains($pacerPage, 'class="pacer-guide"') && !str_contains($pacerCss, '.pacer-guide{') && !str_contains($pacerJs, 'setGuide('), 'guia permanente do Pacer deve ter sido removido.');
$assert(str_contains($pacerPage, 'data-pacer-help-open="overview"') && str_contains($pacerPage, 'data-pacer-help-dialog') && str_contains($pacerPage, 'Como funciona?'), 'Pacer precisa expor ajuda discreta sob demanda.');
$assert(str_contains($pacerPage, 'data-pacer-help-section="strategy"') && str_contains($pacerPage, 'Negative split') && str_contains($pacerPage, 'Positive split') && str_contains($pacerPage, 'Personalizada'), 'ajuda precisa explicar estratégias.');
$assert(str_contains($pacerPage, 'data-pacer-help-section="advanced"') && str_contains($pacerPage, 'Tolerância') && str_contains($pacerPage, 'Tamanho do segmento') && str_contains($pacerPage, 'FC mínima / máxima'), 'ajuda precisa explicar opções avançadas.');
$assert(str_contains($pacerJs, 'helpDialog.showModal()') && str_contains($pacerJs, "helpDialog?.addEventListener('close'") && str_contains($pacerJs, 'helpReturnFocus?.focus()'), 'dialog Pacer precisa abrir modalmente e restaurar foco.');
$assert(str_contains($pacerCss, '.pacer-help-dialog') && str_contains($pacerCss, '::backdrop') && !str_contains($pacerCss, 'grid-template-columns:minmax(0,1fr) 230px'), 'ajuda fechada não pode reservar terceira coluna.');
$assert(str_contains($pacerPage, 'aria-label="Fechar ajuda"') && str_contains($pacerPage, 'aria-haspopup="dialog"') && str_contains($pacerPage, 'aria-controls="pacer-help-dialog"'), 'controles de ajuda precisam de semântica acessível.');

$helperPos = strpos($footer, '/assets/js/workout-prescription.js');
$sessionPos = strpos($footer, '/assets/js/workout-session.js');
$assert($helperPos !== false && $sessionPos !== false && $helperPos < $sessionPos, 'resolver de prescrição precisa carregar antes da execução Web.');
foreach (['LOAD_REPS', 'REPS', 'DURATION', 'DISTANCE', 'LOAD_DURATION', 'DURATION_DISTANCE', 'EMPTY'] as $mode) {
    $assert(str_contains($prescription, "'{$mode}'"), "resolver precisa suportar {$mode}.");
}
$assert(str_contains($prescription, 'looksLikeDuration(reps)') && str_contains($prescription, 'reps = null'), 'resolver precisa corrigir dado legado de duração salvo como reps sem renderizar reps falsas.');
$assert(str_contains($scheduleJs, 'StrideBRWorkoutPrescription?.resolve?.(exercise)') && str_contains($scheduleJs, '...prescription.summaryParts'), 'preview de cronograma precisa reutilizar resolver central.');
$assert(str_contains($sessionJs, 'plannedPrescription(exercise)') && str_contains($sessionJs, 'const fields = workoutPrescription.loggingFields(prescription)'), 'execução Web precisa resolver campos dinamicamente.');
$assert(str_contains($sessionJs, 'prescription.labels[field]') && str_contains($sessionJs, 'data-session-field-count') && str_contains($sessionJs, '--session-field-count:${fields.length}'), 'headers e grid da execução precisam seguir campos realmente presentes.');
$assert(str_contains($sessionJs, "if (field === 'load')") && str_contains($sessionJs, "if (field === 'reps')") && str_contains($sessionJs, 'data-session-set-${field}'), 'Todos os campos de execução precisam ser editáveis, com metas planejadas separadas.');
$assert(!str_contains($sessionJs, '<span>Load</span><span>Reps</span>'), 'execução não pode manter header fixo Carga/Reps.');

$assert(str_contains($exercisePage, 'data-exercise-empty') && str_contains($exercisePage, 'Nenhum exercício neste treino.') && str_contains($exercisePage, 'data-add-exercise-empty'), 'treino vazio precisa de empty state e ação de adicionar exercício.');
$assert(str_contains($scheduleJs, "rowsContainer.querySelector('[data-exercise-empty]')?.remove()") && str_contains($scheduleJs, "[data-add-exercise-empty]"), 'adicionar exercício pelo empty state precisa reutilizar o editor existente.');
$assert(str_contains($exerciseCss, '.exercise-empty-row'), 'empty state do editor precisa de layout próprio.');

if (!preg_match('/\.session-exercise-number\s*\{([^}]*)\}/s', $style, $numberRule)) {
    $assert(false, 'regra do número do exercício não encontrada.');
} else {
    $rule = $numberRule[1];
    $assert(str_contains($rule, 'background: var(--ui-panel-strong') && str_contains($rule, 'color: var(--ui-text-strong)') && str_contains($rule, 'border: 1px solid var(--ui-border)'), 'indicador numérico precisa usar tokens semânticos com contraste light/dark.');
    $assert(!preg_match('/background\s*:\s*(#(?:fff|ffffff)|white)/i', $rule), 'indicador numérico não pode voltar a background branco hardcoded.');
}
$assert(str_contains($style, '.workout-finish-time-grid input') && str_contains($style, 'color: var(--ui-text-secondary)') && str_contains($style, 'border: 1px solid var(--ui-border)'), 'inputs de finalização precisam usar tokens no dark/light.');
$assert(str_contains($style, '.session-set-row input') && str_contains($style, 'color: var(--ui-text-strong)') && str_contains($style, 'border-color: var(--ui-accent)'), 'inputs de séries precisam evitar texto hardcoded incompatível com dark.');

printf("✓ Web Training UX + Pacer Help: %d assertions\n", $count);
