<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma.php';
require_once dirname(__DIR__, 2) . '/src/layout/workout_builder.php';

$returnTo = stridebr_safe_redirect((string) ($_GET['return_to'] ?? ''), '/user/cronogramatreinos.php');
$builderOptions = ['sport_slug' => ''];
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
    <title><?php echo stridebr_e(stridebr_t('workout_builder.title')); ?> | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content draft-exercise-page" data-draft-exercise-page data-return-to="<?php echo stridebr_e($returnTo); ?>">
        <div class="draft-exercise-heading"><div><span class="eyebrow"><?php echo stridebr_e(stridebr_t('schedule.new_workout')); ?></span><h1><?php echo stridebr_e(stridebr_t('workout_builder.title')); ?></h1><p><?php echo stridebr_e(stridebr_t('workout_builder.empty_help')); ?></p></div><a class="secondary-button" href="<?php echo stridebr_e($returnTo); ?>"><?php echo stridebr_e(stridebr_t('common.back')); ?></a></div>
        <div class="draft-exercise-missing" data-draft-missing hidden><strong><?php echo stridebr_e(stridebr_t('schedule.draft_missing')); ?></strong><a class="primary-button" href="<?php echo stridebr_e($returnTo); ?>"><?php echo stridebr_e(stridebr_t('common.back')); ?></a></div>
        <form class="workout-builder-shell" data-workout-builder data-draft-workout-builder>
            <div class="workout-builder-toolbar">
                <div class="workout-builder-toolbar-main"><div class="workout-builder-toolbar-title"><strong><?php echo stridebr_e(stridebr_t('workout_builder.title')); ?></strong><span data-draft-save-state><?php echo stridebr_e(stridebr_t('draft.saved')); ?></span></div><span class="wb-unsaved" data-wb-dirty hidden><?php echo stridebr_e(stridebr_t('workout_builder.unsaved')); ?></span></div>
                <div class="workout-builder-toolbar-actions">
                    <details class="wb-add-menu"><summary class="secondary-button">+ <?php echo stridebr_e(stridebr_t('workout_builder.add')); ?></summary><div class="wb-add-menu-panel"><button type="button" data-wb-add-exercise><?php echo stridebr_e(stridebr_t('workout_builder.add_exercise')); ?></button><div class="wb-add-menu-divider"></div><?php foreach (['warmup','work','interval_group','recovery','cooldown'] as $step): ?><button type="button" data-wb-add-step="<?php echo $step; ?>"><?php echo stridebr_e(workoutBuilderStepLabel($step)); ?></button><?php endforeach; ?></div></details>
                    <div class="wb-selection-bar" data-wb-selection hidden><span data-wb-selected-count></span><button type="button" class="secondary-button compact-button" data-wb-create-group="superset"><?php echo stridebr_e(stridebr_t('workout_builder.group.superset')); ?></button><button type="button" class="secondary-button compact-button" data-wb-create-group="circuit"><?php echo stridebr_e(stridebr_t('workout_builder.group.circuit')); ?></button></div>
                    <button type="button" class="primary-button" data-save-draft-exercises><?php echo stridebr_e(stridebr_t('workout_builder.save')); ?></button>
                </div>
            </div>
            <div class="workout-builder-list" data-wb-list><div class="workout-builder-empty" data-wb-empty><strong><?php echo stridebr_e(stridebr_t('workout_builder.empty')); ?></strong><span><?php echo stridebr_e(stridebr_t('workout_builder.empty_help')); ?></span><button type="button" class="secondary-button" data-wb-add-exercise><?php echo stridebr_e(stridebr_t('workout_builder.add_exercise')); ?></button></div></div>
            <template data-wb-card-template><?php echo workoutBuilderRenderCard(['tipo_passo'=>'exercise','tracking_mode'=>'load_reps','metodo_prescricao'=>'standard'], '__INDEX__', $builderOptions); ?></template>
            <div class="workout-builder-footer"><a class="secondary-button" href="<?php echo stridebr_e($returnTo); ?>"><?php echo stridebr_e(stridebr_t('common.cancel')); ?></a><button type="button" class="primary-button" data-save-draft-exercises><?php echo stridebr_e(stridebr_t('workout_builder.save')); ?></button></div>
            <div class="wb-a11y-live" data-wb-live aria-live="polite" aria-atomic="true"></div>
            <dialog class="wb-picker" data-wb-picker><div class="wb-picker-head"><h2><?php echo stridebr_e(stridebr_t('workout_builder.add_exercise')); ?></h2><button type="button" data-wb-picker-close aria-label="<?php echo stridebr_e(stridebr_t('workout_builder.close')); ?>">×</button></div><div class="wb-picker-search"><input type="search" data-wb-picker-search placeholder="<?php echo stridebr_e(stridebr_t('workout_builder.search_placeholder')); ?>" autocomplete="off"></div><div class="wb-picker-results" data-wb-picker-results></div></dialog>
        </form>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/workout-builder.js')); ?>"></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/exercicios-rascunho.js')); ?>"></script>
</body>
</html>
