<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma.php';

$idModelo = trim((string) ($_GET['idtreino_modelo'] ?? $_POST['idtreino_modelo'] ?? ''));
$modelo = $idModelo !== '' ? cronogramaBuscarTreinoModelo($pdo, $idUsuario, $idModelo) : [];
if ($modelo === []) stridebr_error_document(404);
$returnTo = stridebr_safe_redirect((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? ''), '/user/cronogramatreinos.php#meus-treinos');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    try {
        cronogramaSalvarExerciciosTreinoModelo($pdo, $idUsuario, $idModelo, is_array($_POST['rows'] ?? null) ? $_POST['rows'] : []);
        stridebr_flash('success', stridebr_t('library.saved_workout_exercises_updated'));
        header('Location: ' . $returnTo);
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : stridebr_t('library.save_exercises_error');
        if (!$e instanceof InvalidArgumentException && !$e instanceof RuntimeException) error_log($e->getMessage());
    }
}

$modelo = cronogramaBuscarTreinoModelo($pdo, $idUsuario, $idModelo);
$exercicios = $modelo['exercicios'] ?? [];
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

    <title><?php echo stridebr_e((string) $modelo['titulo']); ?> | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content model-exercise-page">
        <?php $plannedReviewRows=$exercicios; $plannedReviewKind='template'; $plannedReviewId=$idModelo; $plannedReviewReturn='/user/exerciciostreinomodelo.php?idtreino_modelo='.rawurlencode($idModelo); require dirname(__DIR__,2).'/src/layout/planned_name_review.php'; ?>
        <div class="draft-exercise-heading"><div><span class="eyebrow"><?php echo stridebr_e(stridebr_t('exercise_model.my_workouts')); ?></span><h1><?php echo stridebr_e((string) $modelo['titulo']); ?></h1><p><?php echo stridebr_e(stridebr_t('exercise_model.subtitle')); ?></p></div><div class="draft-exercise-heading-actions"><a class="secondary-button" href="/user/biblioteca.php?tab=treinos&edit=<?php echo rawurlencode($idModelo); ?>#editar-treino"><?php echo stridebr_e(stridebr_t('exercise_model.edit_general')); ?></a><a class="secondary-button" href="<?php echo stridebr_e($returnTo); ?>"><?php echo stridebr_e(stridebr_t('common.back')); ?></a></div></div>
        <?php if ($errors !== []): ?><div class="alert error"><?php echo stridebr_e(implode(' ', $errors)); ?></div><?php endif; ?>
        <?php $builderOptions = ['sport_slug' => (string) ($modelo['modalidade_slug'] ?? '')]; ?>
        <form method="POST" class="workout-builder-shell" data-workout-builder>
            <?php echo stridebr_csrf_field(); ?>
            <input type="hidden" name="idtreino_modelo" value="<?php echo stridebr_e($idModelo); ?>">
            <input type="hidden" name="return_to" value="<?php echo stridebr_e($returnTo); ?>">
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
            <div class="workout-builder-footer"><a class="secondary-button" href="<?php echo stridebr_e($returnTo); ?>"><?php echo stridebr_e(stridebr_t('common.cancel')); ?></a><button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('workout_builder.save')); ?></button></div>
            <div class="wb-a11y-live" data-wb-live aria-live="polite" aria-atomic="true"></div>
            <dialog class="wb-picker" data-wb-picker>
                <div class="wb-picker-head"><h2><?php echo stridebr_e(stridebr_t('workout_builder.add_exercise')); ?></h2><button type="button" data-wb-picker-close aria-label="<?php echo stridebr_e(stridebr_t('workout_builder.close')); ?>">×</button></div>
                <div class="wb-picker-search"><input type="search" data-wb-picker-search placeholder="<?php echo stridebr_e(stridebr_t('workout_builder.search_placeholder')); ?>" autocomplete="off" aria-label="<?php echo stridebr_e(stridebr_t('workout_builder.search')); ?>"></div>
                <div class="wb-picker-results" data-wb-picker-results></div>
            </dialog>
        </form>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/workout-builder.js')); ?>"></script>
</body>
</html>
