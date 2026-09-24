<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();

require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma.php';
require_once dirname(__DIR__, 2) . '/src/function/notificacoes.php';
$idTreino = (string) ($_GET['idtreino'] ?? $_POST['idtreino'] ?? '');
$treino = $idTreino !== '' ? cronogramaBuscarTreino($pdo, $idTreino, $idUsuario) : [];
if ($treino === []) {
    stridebr_error_document(404);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'save_exercises') {
            $campos = cronogramaListarCamposExtras($pdo, $idTreino, $idUsuario);
            cronogramaSalvarExercicios($pdo, $idTreino, $idUsuario, is_array($_POST['rows'] ?? null) ? $_POST['rows'] : [], $campos);
            notificacaoCronogramaSincronizadoAlterado($pdo, $idUsuario, (string) ($treino['idcronograma'] ?? ''), stridebr_t('schedule.exercises_updated_notification'));
            stridebr_flash('success', stridebr_t('schedule.exercises_saved'));
        } elseif ($action === 'add_field') {
            cronogramaAdicionarCampoExtra($pdo, $idTreino, $idUsuario, (string) ($_POST['nome'] ?? ''), (string) ($_POST['tipo'] ?? 'texto'));
            notificacaoCronogramaSincronizadoAlterado($pdo, $idUsuario, (string) ($treino['idcronograma'] ?? ''), 'A estrutura de um treino foi atualizada.');
            stridebr_flash('success', 'Coluna adicionada ao treino.');
        } elseif ($action === 'remove_field') {
            if (!cronogramaDesativarCampoExtra($pdo, $idTreino, $idUsuario, (string) ($_POST['idcampo'] ?? ''))) {
                throw new RuntimeException(stridebr_t('schedule.column_not_found'));
            }
            notificacaoCronogramaSincronizadoAlterado($pdo, $idUsuario, (string) ($treino['idcronograma'] ?? ''), 'A estrutura de um treino foi atualizada.');
            stridebr_flash('success', 'Coluna removida deste treino.');
        } elseif ($action === 'copy_exercise') {
            $idTreinoDestino = (string) ($_POST['idtreino_destino'] ?? '');
            if (!cronogramaCopiarExercicio($pdo, $idUsuario, (string) ($_POST['idtreino_exercicio'] ?? ''), $idTreinoDestino)) {
                throw new RuntimeException(stridebr_t('schedule.exercise_copy_error'));
            }
            $treinoDestino = cronogramaBuscarTreino($pdo, $idTreinoDestino, $idUsuario);
            notificacaoCronogramaSincronizadoAlterado($pdo, $idUsuario, (string) ($treinoDestino['idcronograma'] ?? ''), stridebr_t('schedule.exercise_added_notification'));
            stridebr_flash('success', stridebr_t('schedule.exercise_copied'));
        }
        header('Location: /user/exercicioscronograma.php?idtreino=' . urlencode($idTreino));
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : stridebr_t('common.operation_failed');
        if (!$e instanceof InvalidArgumentException && !$e instanceof RuntimeException) {
            error_log($e->getMessage());
        }
    }
}

$exercicios = cronogramaListarTreinoExercicios($pdo, $idTreino, $idUsuario);
$camposExtras = cronogramaListarCamposExtras($pdo, $idTreino, $idUsuario);
$valoresExtras = cronogramaCarregarValoresExtras($pdo, $exercicios);
$destinos = cronogramaListarTreinosUsuario($pdo, $idUsuario, $idTreino);
$flashes = stridebr_take_flashes();

function renderExtraInput(array $campo, mixed $valor, string $name): string
{
    $escapedName = stridebr_e($name);
    $type = $campo['tipo'];
    if ($type === 'inteiro') {
        return '<input type="number" step="1" name="' . $escapedName . '" value="' . stridebr_e($valor ?? '') . '">';
    }
    if ($type === 'decimal') {
        return '<input type="number" step="any" name="' . $escapedName . '" value="' . stridebr_e($valor ?? '') . '">';
    }
    if ($type === 'booleano') {
        $raw = $valor === null ? '' : (stridebr_db_bool($valor) ? '1' : '0');
        return '<select name="' . $escapedName . '"><option value="">—</option><option value="1"' . ($raw === '1' ? ' selected' : '') . '>' . stridebr_e(stridebr_t('common.yes')) . '</option><option value="0"' . ($raw === '0' ? ' selected' : '') . '>' . stridebr_e(stridebr_t('common.no')) . '</option></select>';
    }
    return '<input type="text" name="' . $escapedName . '" value="' . stridebr_e($valor ?? '') . '">';
}
require_once dirname(__DIR__, 2) . '/src/layout/workout_builder.php';
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/cronogramas.css')); ?>">
    <title><?php echo stridebr_e($treino['titulo']); ?> | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content exercicios-page">
        <?php $plannedReviewRows=$exercicios; $plannedReviewKind='workout'; $plannedReviewId=$idTreino; $plannedReviewReturn='/user/exercicioscronograma.php?idtreino='.rawurlencode($idTreino); require dirname(__DIR__,2).'/src/layout/planned_name_review.php'; ?>
        <div class="exercise-shell">
            <div class="exercise-heading">
                <div>
                    <a class="back-link context-back-button" data-safe-back href="/user/cronogramatreinos.php?id=<?php echo urlencode($treino['idcronograma']); ?>">← <?php echo stridebr_e($treino['cronograma_nome']); ?></a>
                    <h1><?php echo stridebr_e($treino['titulo']); ?></h1>
                    <p><?php echo stridebr_e(substr($treino['hora_inicio'], 0, 5)); ?>–<?php echo stridebr_e(substr($treino['hora_fim'], 0, 5)); ?><?php echo stridebr_db_bool($treino['termina_dia_seguinte']) ? ' · ' . stridebr_t('exercise.ends_next_day') : ''; ?></p>
                </div>
                <a class="secondary-button" href="/user/biblioteca.php?tab=exercicios"><?php echo stridebr_e(stridebr_t('common.open_library')); ?></a>
            </div>

            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div>
            <?php endforeach; ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger"><?php echo stridebr_e($error); ?></div>
            <?php endforeach; ?>

            <details class="exercise-tools-card wb-custom-fields-admin">
                <summary>
                    <span>
                        <strong><?php echo stridebr_e(stridebr_t('exercise.columns_title')); ?></strong>
                        <small><?php echo stridebr_e(stridebr_t('exercise.columns_help')); ?></small>
                    </span>
                </summary>
                <div class="wb-custom-fields-body">
                <form method="POST" class="inline-field-form">
                    <?php echo stridebr_csrf_field(); ?>
                    <input type="hidden" name="action" value="add_field">
                    <input type="hidden" name="idtreino" value="<?php echo stridebr_e($idTreino); ?>">
                    <input type="text" name="nome" maxlength="80" placeholder="<?php echo stridebr_e(stridebr_t('exercise.rpe_placeholder')); ?>" required>
                    <select name="tipo">
                        <option value="texto"><?php echo stridebr_e(stridebr_t('exercise.type_text')); ?></option>
                        <option value="inteiro"><?php echo stridebr_e(stridebr_t('exercise.type_integer')); ?></option>
                        <option value="decimal"><?php echo stridebr_e(stridebr_t('exercise.type_decimal')); ?></option>
                        <option value="booleano"><?php echo stridebr_e(stridebr_t('exercise.type_boolean')); ?></option>
                    </select>
                    <button type="submit" class="secondary-button"><?php echo stridebr_e(stridebr_t('exercise.add_column')); ?></button>
                </form>
                <?php if ($camposExtras !== []): ?>
                    <div class="custom-field-chips">
                        <?php foreach ($camposExtras as $campo): ?>
                            <form method="POST" class="field-chip" data-confirm="<?php echo stridebr_e(stridebr_t('exercise.remove_column_confirm')); ?>">
                                <?php echo stridebr_csrf_field(); ?>
                                <input type="hidden" name="action" value="remove_field">
                                <input type="hidden" name="idtreino" value="<?php echo stridebr_e($idTreino); ?>">
                                <input type="hidden" name="idcampo" value="<?php echo stridebr_e($campo['idcampo']); ?>">
                                <span><?php echo stridebr_e($campo['nome']); ?></span>
                                <button type="submit" aria-label="<?php echo stridebr_e(stridebr_t('exercise.remove_named', ['name' => (string) $campo['nome']])); ?>">×</button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>
            </details>

            <?php
            $builderOptions = [
                'sport_slug' => (string) ($treino['modalidade_slug'] ?? ''),
                'extra_fields' => $camposExtras,
                'extra_values' => $valoresExtras,
                'extra_renderer' => 'renderExtraInput',
            ];
            ?>
            <form method="POST" class="workout-builder-shell" data-workout-builder>
                <?php echo stridebr_csrf_field(); ?>
                <input type="hidden" name="action" value="save_exercises">
                <input type="hidden" name="idtreino" value="<?php echo stridebr_e($idTreino); ?>">
                <div class="workout-builder-toolbar">
                    <div class="workout-builder-toolbar-main">
                        <div class="workout-builder-toolbar-title"><strong><?php echo stridebr_e(stridebr_t('workout_builder.title')); ?></strong><span><?php echo count($exercicios); ?> <?php echo stridebr_e(stridebr_t('common.exercises')); ?></span></div>
                        <span class="wb-unsaved" data-wb-dirty hidden><?php echo stridebr_e(stridebr_t('workout_builder.unsaved')); ?></span>
                    </div>
                    <div class="workout-builder-toolbar-actions">
                        <details class="wb-add-menu">
                            <summary class="secondary-button">+ <?php echo stridebr_e(stridebr_t('workout_builder.add')); ?></summary>
                            <div class="wb-add-menu-panel">
                                <button type="button" data-wb-add-exercise><?php echo stridebr_e(stridebr_t('workout_builder.add_exercise')); ?></button>
                                <div class="wb-add-menu-divider"></div>
                                <?php foreach (['warmup','work','interval_group','recovery','cooldown'] as $step): ?>
                                    <button type="button" data-wb-add-step="<?php echo $step; ?>"><?php echo stridebr_e(workoutBuilderStepLabel($step)); ?></button>
                                <?php endforeach; ?>
                            </div>
                        </details>
                        <div class="wb-selection-bar" data-wb-selection hidden>
                            <span data-wb-selected-count></span>
                            <button type="button" class="secondary-button compact-button" data-wb-create-group="superset"><?php echo stridebr_e(stridebr_t('workout_builder.group.superset')); ?></button>
                            <button type="button" class="secondary-button compact-button" data-wb-create-group="circuit"><?php echo stridebr_e(stridebr_t('workout_builder.group.circuit')); ?></button>
                        </div>
                        <button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('workout_builder.save')); ?></button>
                    </div>
                </div>
                <div class="workout-builder-list" data-wb-list>
                    <?php workoutBuilderRender($exercicios, $builderOptions); ?>
                    <div class="workout-builder-empty" data-wb-empty<?php echo $exercicios !== [] ? ' hidden' : ''; ?>><strong><?php echo stridebr_e(stridebr_t('workout_builder.empty')); ?></strong><span><?php echo stridebr_e(stridebr_t('workout_builder.empty_help')); ?></span><button type="button" class="secondary-button" data-wb-add-exercise><?php echo stridebr_e(stridebr_t('workout_builder.add_exercise')); ?></button></div>
                </div>
                <template data-wb-card-template><?php echo workoutBuilderRenderCard(['tipo_passo'=>'exercise','tracking_mode'=>'load_reps','metodo_prescricao'=>'standard'], '__INDEX__', $builderOptions); ?></template>
                <div class="workout-builder-footer"><span class="wb-unsaved" data-wb-dirty hidden><?php echo stridebr_e(stridebr_t('workout_builder.unsaved')); ?></span><button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('workout_builder.save')); ?></button></div>
                <div class="wb-a11y-live" data-wb-live aria-live="polite" aria-atomic="true"></div>
                <dialog class="wb-picker" data-wb-picker>
                    <div class="wb-picker-head"><h2><?php echo stridebr_e(stridebr_t('workout_builder.add_exercise')); ?></h2><button type="button" data-wb-picker-close aria-label="<?php echo stridebr_e(stridebr_t('workout_builder.close')); ?>">×</button></div>
                    <div class="wb-picker-search"><input type="search" data-wb-picker-search placeholder="<?php echo stridebr_e(stridebr_t('workout_builder.search_placeholder')); ?>" autocomplete="off" aria-label="<?php echo stridebr_e(stridebr_t('workout_builder.search')); ?>"></div>
                    <div class="wb-picker-results" data-wb-picker-results></div>
                </dialog>
            </form>

            <?php if ($exercicios !== [] && $destinos !== []): ?>
                <section class="exercise-copy-card">
                    <h2><?php echo stridebr_e(stridebr_t('exercise.copy_to_workout')); ?></h2>
                    <form method="POST" class="copy-form">
                        <?php echo stridebr_csrf_field(); ?>
                        <input type="hidden" name="action" value="copy_exercise">
                        <input type="hidden" name="idtreino" value="<?php echo stridebr_e($idTreino); ?>">
                        <select name="idtreino_exercicio" required>
                            <?php foreach ($exercicios as $row): ?><option value="<?php echo stridebr_e($row['idtreino_exercicio']); ?>"><?php echo stridebr_e($row['nome_snapshot']); ?></option><?php endforeach; ?>
                        </select>
                        <select name="idtreino_destino" required>
                            <?php foreach ($destinos as $destino): ?>
                                <option value="<?php echo stridebr_e($destino['idtreino']); ?>"><?php echo stridebr_e($destino['cronograma_nome'] . ' · ' . $destino['titulo']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="secondary-button"><?php echo stridebr_e(stridebr_t('exercise.copy_exercise')); ?></button>
                    </form>
                </section>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/cronogramas.js')); ?>"></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/workout-builder.js')); ?>"></script>
</body>
</html>
