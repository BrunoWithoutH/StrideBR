<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$helper = $read('src/function/strength_activity.php');
$layout = $read('src/layout/activity_strength_editor.php');
$activities = $read('public/user/atividades.php');
$edit = $read('public/user/editatividade.php');
$api = $read('public/api/atividade-editor-detalhes.php');
$presenter = $read('src/function/atividade_presenter.php');
$js = $read('public/assets/js/atividades.js');
$picker = $read('src/layout/sport_picker.php');
$referenceApi = $read('public/api/exercicio-forca-referencia.php');

$checks = [
    'séries manuais têm persistência normalizada' => str_contains($helper, 'function atividadeForcaPersistirSeriesManuais') && str_contains($helper, 'series_exercicio_atividade'),
    'booleano concluída usa 1/0 no bind' => str_contains($helper, "':concluida' => \$set['concluida'] ? 1 : 0"),
    'registro manual persiste força na mesma transação' => str_contains($activities, 'atividadeForcaPersistirSeriesManuais') && str_contains($activities, '$pdo->beginTransaction()'),
    'edição carrega e persiste séries' => str_contains($edit, 'atividadeForcaBuscarSeries') && str_contains($edit, 'atividadeForcaPersistirSeriesManuais'),
    'editor mostra exercícios e séries apenas para força' => str_contains($layout, 'data-strength-editor') && str_contains($js, "family === 'strength'"),
    'editor suporta carga reps RIR e tipo' => str_contains($layout, '[carga_kg]') && str_contains($layout, '[repeticoes]') && str_contains($layout, '[rir]') && str_contains($layout, '[tipo]'),
    'biblioteca de exercícios chega no editor lazy' => str_contains($api, "'exercises' => \$exercises") && str_contains($js, 'setStrengthLibrary(data.exercises || [])'),
    'detalhe da atividade expõe séries de força' => str_contains($presenter, "'forca' => \$strength") && str_contains($js, 'activity-strength-detail-exercise'),
    'pós-salvamento usa métricas de força' => str_contains($js, 'strength.total_exercicios') && str_contains($js, 'strength.total_series'),
    'seletor esportivo expõe família no valor nativo' => str_contains($picker, 'data-family=') && str_contains($picker, 'data-sport-family='),
    'salvar atividade manual abre compartilhamento rápido' => str_contains($activities, "?saved=' . rawurlencode((string) \$savedActivityId)") && str_contains($js, 'openPostSaveShare(initialSavedActivityId)'),
    'edição usa slider de esforço' => str_contains($edit, 'data-effort-range') && !str_contains($edit, '<?php for ($i = 1; $i <= 10; $i++): ?>'),
    'histórico do exercício alimenta referência no registro' => str_contains($helper, 'function atividadeForcaReferenciaExercicio') && str_contains($referenceApi, 'atividadeForcaReferenciaExercicio') && str_contains($js, '/api/exercicio-forca-referencia.php'),
    'última sessão vira referência sem preencher silenciosamente' => str_contains($js, 'Última vez ·') && str_contains($js, 'placeholder') && str_contains($js, '_stridebrStrengthReference'),
    'usuário pode reaplicar explicitamente as séries da última sessão' => str_contains($js, 'data-strength-use-last') && str_contains($js, 'useLatestStrengthSession') && str_contains($js, 'Última sessão aplicada'),
];

$failed = [];
foreach ($checks as $label => $ok) if (!$ok) $failed[] = $label;
if ($failed !== []) {
    fwrite(STDERR, "Falhas no registro manual de força:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}
printf("✓ manual strength UI static: %d assertions\n", count($checks));
