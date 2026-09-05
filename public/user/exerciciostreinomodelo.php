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
$biblioteca = cronogramaListarExerciciosBiblioteca($pdo, $idUsuario);
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
        <div class="draft-exercise-heading"><div><span class="eyebrow"><?php echo stridebr_e(stridebr_t('exercise_model.my_workouts')); ?></span><h1><?php echo stridebr_e((string) $modelo['titulo']); ?></h1><p><?php echo stridebr_e(stridebr_t('exercise_model.subtitle')); ?></p></div><div class="draft-exercise-heading-actions"><a class="secondary-button" href="/user/biblioteca.php?tab=treinos&edit=<?php echo rawurlencode($idModelo); ?>#editar-treino"><?php echo stridebr_e(stridebr_t('exercise_model.edit_general')); ?></a><a class="secondary-button" href="<?php echo stridebr_e($returnTo); ?>"><?php echo stridebr_e(stridebr_t('common.back')); ?></a></div></div>
        <?php if ($errors !== []): ?><div class="alert error"><?php echo stridebr_e(implode(' ', $errors)); ?></div><?php endif; ?>
        <form method="POST" class="model-exercise-editor" data-model-exercise-editor>
            <?php echo stridebr_csrf_field(); ?>
            <input type="hidden" name="idtreino_modelo" value="<?php echo stridebr_e($idModelo); ?>">
            <input type="hidden" name="return_to" value="<?php echo stridebr_e($returnTo); ?>">
            <div class="draft-exercise-list" data-model-exercise-list>
                <?php foreach ($exercicios as $index => $row): ?>
                    <article class="draft-exercise-card" data-model-exercise-row>
                        <div class="draft-exercise-card-heading"><strong data-model-number><?php echo stridebr_e(stridebr_t('home.exercise')); ?> <?php echo $index + 1; ?></strong><button type="button" class="danger-link" data-remove-model-exercise><?php echo stridebr_e(stridebr_t('common.remove')); ?></button></div>
                        <div class="draft-exercise-core">
                            <label><?php echo stridebr_e(stridebr_t('library.page_title')); ?><select name="rows[<?php echo $index; ?>][idexercicio]" data-library-select><option value=""><?php echo stridebr_e(stridebr_t('common.manual')); ?></option><?php foreach ($biblioteca as $item): ?><option value="<?php echo stridebr_e((string) $item['idexercicio']); ?>" data-name="<?php echo stridebr_e((string) $item['nome']); ?>"<?php echo (string) ($row['idexercicio'] ?? '') === (string) $item['idexercicio'] ? ' selected' : ''; ?>><?php echo stridebr_e((string) $item['nome']); ?></option><?php endforeach; ?></select></label>
                            <label class="draft-exercise-name"><?php echo stridebr_e(stridebr_t('home.exercise')); ?><input type="text" name="rows[<?php echo $index; ?>][nome]" value="<?php echo stridebr_e((string) ($row['nome_snapshot'] ?? '')); ?>" data-exercise-name maxlength="120" required></label>
                            <label><?php echo stridebr_e(stridebr_t('common.series')); ?><input type="number" name="rows[<?php echo $index; ?>][series]" value="<?php echo stridebr_e((string) ($row['series'] ?? '')); ?>" min="1" max="99"></label>
                            <label><?php echo stridebr_e(stridebr_t('common.repetitions')); ?><input type="text" name="rows[<?php echo $index; ?>][repeticoes]" value="<?php echo stridebr_e((string) ($row['repeticoes'] ?? '')); ?>" maxlength="40"></label>
                            <label><?php echo stridebr_e(stridebr_t('common.load')); ?><input type="text" name="rows[<?php echo $index; ?>][carga]" value="<?php echo stridebr_e((string) ($row['carga'] ?? '')); ?>" maxlength="40"></label>
                            <label><?php echo stridebr_e(stridebr_t('home.rest')); ?><input type="text" name="rows[<?php echo $index; ?>][descanso]" value="<?php echo stridebr_e((string) ($row['descanso'] ?? '')); ?>" maxlength="40"></label>
                        </div>
                        <details class="draft-exercise-more"><summary><?php echo stridebr_e(stridebr_t('common.more_fields')); ?></summary><div class="draft-exercise-more-grid">
                            <label><?php echo stridebr_e(stridebr_t('common.block')); ?><input type="text" name="rows[<?php echo $index; ?>][bloco]" value="<?php echo stridebr_e((string) ($row['bloco'] ?? '')); ?>" maxlength="40"></label>
                            <label><?php echo stridebr_e(stridebr_t('common.cluster')); ?><input type="text" name="rows[<?php echo $index; ?>][cluster]" value="<?php echo stridebr_e((string) ($row['cluster'] ?? '')); ?>" maxlength="80"></label>
                            <label><?php echo stridebr_e(stridebr_t('common.duration')); ?><input type="text" name="rows[<?php echo $index; ?>][duracao]" value="<?php echo stridebr_e((string) ($row['duracao'] ?? '')); ?>" maxlength="40"></label>
                            <label><?php echo stridebr_e(stridebr_t('common.distance')); ?><input type="text" name="rows[<?php echo $index; ?>][distancia]" value="<?php echo stridebr_e((string) ($row['distancia'] ?? '')); ?>" maxlength="40"></label>
                            <label><?php echo stridebr_e(stridebr_t('schedule.intensity')); ?><input type="text" name="rows[<?php echo $index; ?>][intensidade]" value="<?php echo stridebr_e((string) ($row['intensidade'] ?? '')); ?>" maxlength="80"></label>
                            <label>RPE<input type="number" name="rows[<?php echo $index; ?>][rpe]" value="<?php echo stridebr_e((string) ($row['rpe'] ?? '')); ?>" min="0" max="10" step="0.5"></label>
                            <label>RIR<input type="number" name="rows[<?php echo $index; ?>][rir]" value="<?php echo stridebr_e((string) ($row['rir'] ?? '')); ?>" min="0" max="10" step="0.5"></label>
                            <label><?php echo stridebr_e(stridebr_t('common.execution_time')); ?><input type="text" name="rows[<?php echo $index; ?>][tempo_execucao]" value="<?php echo stridebr_e((string) ($row['tempo_execucao'] ?? '')); ?>" maxlength="40"></label>
                            <label><?php echo stridebr_e(stridebr_t('common.cadence')); ?><input type="text" name="rows[<?php echo $index; ?>][cadencia]" value="<?php echo stridebr_e((string) ($row['cadencia'] ?? '')); ?>" maxlength="40"></label>
                            <label class="draft-exercise-notes"><?php echo stridebr_e(stridebr_t('activity.notes')); ?><textarea name="rows[<?php echo $index; ?>][observacoes]" rows="2"><?php echo stridebr_e((string) ($row['observacoes'] ?? '')); ?></textarea></label>
                        </div></details>
                    </article>
                <?php endforeach; ?>
            </div>
            <template data-model-exercise-template>
                <article class="draft-exercise-card" data-model-exercise-row>
                    <div class="draft-exercise-card-heading"><strong data-model-number></strong><button type="button" class="danger-link" data-remove-model-exercise><?php echo stridebr_e(stridebr_t('common.remove')); ?></button></div>
                    <div class="draft-exercise-core">
                        <label><?php echo stridebr_e(stridebr_t('library.page_title')); ?><select name="rows[__INDEX__][idexercicio]" data-library-select><option value=""><?php echo stridebr_e(stridebr_t('common.manual')); ?></option><?php foreach ($biblioteca as $item): ?><option value="<?php echo stridebr_e((string) $item['idexercicio']); ?>" data-name="<?php echo stridebr_e((string) $item['nome']); ?>"><?php echo stridebr_e((string) $item['nome']); ?></option><?php endforeach; ?></select></label>
                        <label class="draft-exercise-name"><?php echo stridebr_e(stridebr_t('home.exercise')); ?><input type="text" name="rows[__INDEX__][nome]" data-exercise-name maxlength="120"></label>
                        <label><?php echo stridebr_e(stridebr_t('common.series')); ?><input type="number" name="rows[__INDEX__][series]" min="1" max="99"></label>
                        <label><?php echo stridebr_e(stridebr_t('common.repetitions')); ?><input type="text" name="rows[__INDEX__][repeticoes]" maxlength="40"></label>
                        <label><?php echo stridebr_e(stridebr_t('common.load')); ?><input type="text" name="rows[__INDEX__][carga]" maxlength="40"></label>
                        <label><?php echo stridebr_e(stridebr_t('home.rest')); ?><input type="text" name="rows[__INDEX__][descanso]" maxlength="40"></label>
                    </div>
                    <details class="draft-exercise-more"><summary><?php echo stridebr_e(stridebr_t('common.more_fields')); ?></summary><div class="draft-exercise-more-grid">
                        <label><?php echo stridebr_e(stridebr_t('common.block')); ?><input type="text" name="rows[__INDEX__][bloco]" maxlength="40"></label><label><?php echo stridebr_e(stridebr_t('common.cluster')); ?><input type="text" name="rows[__INDEX__][cluster]" maxlength="80"></label><label><?php echo stridebr_e(stridebr_t('common.duration')); ?><input type="text" name="rows[__INDEX__][duracao]" maxlength="40"></label><label><?php echo stridebr_e(stridebr_t('common.distance')); ?><input type="text" name="rows[__INDEX__][distancia]" maxlength="40"></label><label><?php echo stridebr_e(stridebr_t('schedule.intensity')); ?><input type="text" name="rows[__INDEX__][intensidade]" maxlength="80"></label><label>RPE<input type="number" name="rows[__INDEX__][rpe]" min="0" max="10" step="0.5"></label><label>RIR<input type="number" name="rows[__INDEX__][rir]" min="0" max="10" step="0.5"></label><label><?php echo stridebr_e(stridebr_t('common.execution_time')); ?><input type="text" name="rows[__INDEX__][tempo_execucao]" maxlength="40"></label><label><?php echo stridebr_e(stridebr_t('common.cadence')); ?><input type="text" name="rows[__INDEX__][cadencia]" maxlength="40"></label><label class="draft-exercise-notes"><?php echo stridebr_e(stridebr_t('activity.notes')); ?><textarea name="rows[__INDEX__][observacoes]" rows="2"></textarea></label>
                    </div></details>
                </article>
            </template>
            <div class="draft-exercise-footer"><button type="button" class="secondary-button" data-add-model-exercise><?php echo stridebr_e(stridebr_t('common.add_exercise')); ?></button><div><a class="secondary-button" href="<?php echo stridebr_e($returnTo); ?>"><?php echo stridebr_e(stridebr_t('common.cancel')); ?></a><button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('common.save_exercises')); ?></button></div></div>
        </form>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/exercicios-modelo.js')); ?>"></script>
</body>
</html>
