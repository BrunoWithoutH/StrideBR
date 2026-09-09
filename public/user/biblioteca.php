<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
require_once dirname(__DIR__, 2) . '/src/layout/ads.php';

$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma.php';
require_once dirname(__DIR__, 2) . '/src/layout/sport_picker.php';

$mediaEnabled = stridebr_feature_enabled($pdo, 'exercise_media.enabled', false);
$tab = (string) ($_GET['tab'] ?? 'treinos');
if (!in_array($tab, ['treinos', 'exercicios'], true)) $tab = 'treinos';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = trim((string) ($_POST['action'] ?? ''));
    try {
        if ($action === 'save_model') {
            $idModelo = trim((string) ($_POST['idtreino_modelo'] ?? '')) ?: null;
            $isExisting = $idModelo !== null;
            cronogramaSalvarTreinoModelo($pdo, $idUsuario, [
                'titulo' => $_POST['titulo'] ?? '',
                'codigo' => $_POST['codigo'] ?? '',
                'foco' => $_POST['foco'] ?? '',
                'descricao' => $_POST['descricao'] ?? '',
                'idmodalidade' => $_POST['idmodalidade'] ?? '',
            ], $idModelo, $isExisting && !empty($_POST['propagar_vinculados']));
            stridebr_flash('success', $isExisting ? stridebr_t('library.workout_updated') : stridebr_t('library.workout_added'));
            header('Location: /user/biblioteca.php?tab=treinos');
            exit;
        }
        if ($action === 'archive_model') {
            if (!cronogramaArquivarTreinoModelo($pdo, $idUsuario, (string) ($_POST['idtreino_modelo'] ?? ''))) {
                throw new RuntimeException(stridebr_t('library.workout_not_found'));
            }
            stridebr_flash('success', stridebr_t('library.workout_removed'));
            header('Location: /user/biblioteca.php?tab=treinos');
            exit;
        }
        if ($action === 'create_category') {
            cronogramaCriarCategoria($pdo, $idUsuario, (string) ($_POST['nome'] ?? ''));
            stridebr_flash('success', stridebr_t('library.category_added'));
        } elseif ($action === 'create_exercise') {
            cronogramaCriarExercicioCompleto(
                $pdo,
                $idUsuario,
                (string) ($_POST['nome'] ?? ''),
                $_POST['descricao'] ?? null,
                is_array($_POST['categorias'] ?? null) ? $_POST['categorias'] : [],
                is_array($_POST['modalidades'] ?? null) ? $_POST['modalidades'] : [],
                $mediaEnabled ? ($_POST['imagem_url'] ?? null) : null,
                $mediaEnabled ? ($_POST['video_url'] ?? null) : null
            );
            stridebr_flash('success', stridebr_t('library.exercise_saved'));
        } elseif ($action === 'duplicate_system') {
            $id = cronogramaDuplicarExercicioSistema($pdo, $idUsuario, (string) ($_POST['idexercicio'] ?? ''));
            stridebr_flash('success', stridebr_t('library.copy_added'));
            header('Location: /user/biblioteca.php?tab=exercicios&edit=' . urlencode($id));
            exit;
        } elseif ($action === 'update_exercise') {
            if (!cronogramaAtualizarExercicioPessoal(
                $pdo,
                $idUsuario,
                (string) ($_POST['idexercicio'] ?? ''),
                (string) ($_POST['nome'] ?? ''),
                $_POST['descricao'] ?? null,
                is_array($_POST['categorias'] ?? null) ? $_POST['categorias'] : [],
                is_array($_POST['modalidades'] ?? null) ? $_POST['modalidades'] : [],
                $mediaEnabled ? ($_POST['imagem_url'] ?? null) : null,
                $mediaEnabled ? ($_POST['video_url'] ?? null) : null
            )) {
                throw new RuntimeException(stridebr_t('library.personal_exercise_not_found'));
            }
            stridebr_flash('success', stridebr_t('library.exercise_updated'));
        } elseif ($action === 'archive_exercise') {
            $idExercicio = (string) ($_POST['idexercicio'] ?? '');
            if (!cronogramaDesativarExercicioPessoal($pdo, $idUsuario, $idExercicio)) {
                throw new RuntimeException(stridebr_t('library.personal_exercise_not_found'));
            }
            $_SESSION['exercise_library_undo'] = [
                'idexercicio' => $idExercicio,
                'token' => bin2hex(random_bytes(16)),
                'expires' => time() + 300,
            ];
            stridebr_flash('success', stridebr_t('library.exercise_removed'));
        } elseif ($action === 'restore_exercise') {
            $undo = is_array($_SESSION['exercise_library_undo'] ?? null) ? $_SESSION['exercise_library_undo'] : [];
            $token = (string) ($_POST['undo_token'] ?? '');
            if ($undo === [] || (int) ($undo['expires'] ?? 0) < time() || !hash_equals((string) ($undo['token'] ?? ''), $token)) {
                unset($_SESSION['exercise_library_undo']);
                throw new RuntimeException(stridebr_t('library.undo_expired'));
            }
            if (!cronogramaRestaurarExercicioPessoal($pdo, $idUsuario, (string) ($undo['idexercicio'] ?? ''))) {
                throw new RuntimeException(stridebr_t('library.exercise_restore_error'));
            }
            unset($_SESSION['exercise_library_undo']);
            stridebr_flash('success', stridebr_t('library.exercise_restored'));
        } elseif (!in_array($action, ['save_model', 'archive_model'], true)) {
            throw new InvalidArgumentException(stridebr_t('common.invalid_action'));
        }
        header('Location: /user/biblioteca.php?tab=exercicios');
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : stridebr_t('common.operation_failed');
        if (!$e instanceof InvalidArgumentException && !$e instanceof RuntimeException) error_log($e->getMessage());
    }
}

$treinos = cronogramaListarTreinosModelo($pdo, $idUsuario);
$modalidadesTreino = cronogramaListarModalidadesTreino($pdo, $idUsuario);
$editWorkoutId = $tab === 'treinos' ? trim((string) ($_GET['edit'] ?? '')) : '';
$editModel = $editWorkoutId !== '' ? cronogramaBuscarTreinoModelo($pdo, $idUsuario, $editWorkoutId) : [];
$workoutData = [];
foreach ($treinos as $treino) {
    $workoutData[(string) $treino['idtreino_modelo']] = [
        'idtreino_modelo' => (string) $treino['idtreino_modelo'],
        'titulo' => (string) ($treino['titulo'] ?? ''),
        'codigo' => (string) ($treino['codigo'] ?? ''),
        'foco' => (string) ($treino['foco'] ?? ''),
        'descricao' => (string) ($treino['descricao'] ?? ''),
        'idmodalidade' => (string) ($treino['idmodalidade'] ?? ''),
        'usos_total' => (int) ($treino['usos_total'] ?? 0),
    ];
}

$exercicios = cronogramaListarExerciciosBiblioteca($pdo, $idUsuario);
$categorias = cronogramaListarCategorias($pdo, $idUsuario);
$modalidadesExercicio = cronogramaListarModalidadesExercicio($pdo, $idUsuario);
$linksCategorias = [];
$linksModalidades = [];
if ($exercicios !== []) {
    $ids = array_column($exercicios, 'idexercicio');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT idexercicio, idcategoria FROM exercicios_categorias WHERE idexercicio IN ({$placeholders})");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) $linksCategorias[$row['idexercicio']][] = $row['idcategoria'];
    $stmt = $pdo->prepare("SELECT idexercicio, idmodalidade FROM exercicios_modalidades WHERE idexercicio IN ({$placeholders})");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) $linksModalidades[$row['idexercicio']][] = $row['idmodalidade'];
}
$editExerciseId = $tab === 'exercicios' ? (string) ($_GET['edit'] ?? '') : '';
$exerciseData = [];
foreach ($exercicios as $item) {
    if ($item['idusuario'] === null) continue;
    $idExercicio = (string) $item['idexercicio'];
    $exerciseData[$idExercicio] = [
        'idexercicio' => $idExercicio,
        'nome' => (string) ($item['nome'] ?? ''),
        'descricao' => (string) ($item['descricao'] ?? ''),
        'imagem_url' => (string) ($item['imagem_url'] ?? ''),
        'video_url' => (string) ($item['video_url'] ?? ''),
        'categorias' => array_values(array_map('strval', $linksCategorias[$idExercicio] ?? [])),
        'modalidades' => array_values(array_map('strval', $linksModalidades[$idExercicio] ?? [])),
    ];
}
$flashes = stridebr_take_flashes();
$exerciseUndo = is_array($_SESSION['exercise_library_undo'] ?? null) && (int) ($_SESSION['exercise_library_undo']['expires'] ?? 0) >= time() ? $_SESSION['exercise_library_undo'] : null;
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

    <title><?php echo stridebr_e(stridebr_t('library.page_title')); ?> | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content library-page workout-library-page" data-library-page data-library-active-tab="<?php echo stridebr_e($tab); ?>">
        <div class="workout-library-shell">
            <header class="library-page-heading">
                <div>
                    <span class="eyebrow"><?php echo stridebr_e(stridebr_t('schedule.planning')); ?></span>
                    <h1><?php echo stridebr_e(stridebr_t('library.page_title')); ?></h1>
                    <p data-library-tab-help="treinos"<?php echo $tab !== 'treinos' ? ' hidden' : ''; ?>><?php echo stridebr_e(stridebr_t('library.new_workout_help')); ?></p>
                    <p data-library-tab-help="exercicios"<?php echo $tab !== 'exercicios' ? ' hidden' : ''; ?>><?php echo stridebr_e(stridebr_t('library.exercises_help')); ?></p>
                </div>
                <div class="library-page-heading-actions">
                    <div class="library-heading-actions" data-library-heading-actions="treinos"<?php echo $tab !== 'treinos' ? ' hidden' : ''; ?>><button type="button" class="primary-button" data-new-workout-library>+ <?php echo stridebr_e(stridebr_t('library.new_workout')); ?></button></div>
                    <div class="library-heading-actions" data-library-heading-actions="exercicios"<?php echo $tab !== 'exercicios' ? ' hidden' : ''; ?>><button type="button" class="secondary-button" data-new-category><?php echo stridebr_e(stridebr_t('library.new_category')); ?></button><button type="button" class="primary-button" data-new-exercise>+ <?php echo stridebr_e(stridebr_t('library.new_exercise')); ?></button></div>
                </div>
            </header>

            <nav class="workout-library-tabs" aria-label="<?php echo stridebr_e(stridebr_t('library.page_title')); ?>" data-library-tabs>
                <a class="<?php echo $tab === 'treinos' ? 'is-active' : ''; ?>" href="/user/biblioteca.php?tab=treinos" data-library-tab="treinos"><?php echo stridebr_e(stridebr_t('common.workouts')); ?></a>
                <a class="<?php echo $tab === 'exercicios' ? 'is-active' : ''; ?>" href="/user/biblioteca.php?tab=exercicios" data-library-tab="exercicios"><?php echo stridebr_e(stridebr_t('common.exercises')); ?></a>
            </nav>

            <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div><?php endforeach; ?>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
            <?php if ($exerciseUndo !== null): ?><div class="schedule-undo-bar" role="status" data-library-exercise-undo<?php echo $tab !== 'exercicios' ? ' hidden' : ''; ?>><span><?php echo stridebr_e(stridebr_t('library.exercise_removed')); ?></span><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="restore_exercise"><input type="hidden" name="undo_token" value="<?php echo stridebr_e((string) ($exerciseUndo['token'] ?? '')); ?>"><button type="submit"><?php echo stridebr_e(stridebr_t('common.undo')); ?></button></form></div><?php endif; ?>

            <div class="library-tab-stage" data-library-tab-stage>
                <section class="library-tab-view<?php echo $tab === 'treinos' ? ' is-active' : ''; ?>" data-library-view="treinos"<?php echo $tab !== 'treinos' ? ' hidden' : ''; ?>>
                    <section class="library-toolbar content-card">
                        <div><strong><?php echo stridebr_e(stridebr_t('library.saved_workouts')); ?></strong><span><?php echo count($treinos); ?> <?php echo stridebr_e(stridebr_t('library.in_library')); ?></span></div>
                        <label class="library-search-field"><span class="sr-only"><?php echo stridebr_e(stridebr_t('library.search_workout')); ?></span><input type="search" placeholder="<?php echo stridebr_e(stridebr_t('library.search_name_code_focus')); ?>" data-workout-library-search></label>
                    </section>
                    <?php if ($treinos === []): ?>
                        <section class="workout-library-empty content-card"><strong><?php echo stridebr_e(stridebr_t('library.no_workouts')); ?></strong><button type="button" class="primary-button" data-new-workout-library><?php echo stridebr_e(stridebr_t('library.create_workout')); ?></button></section>
                    <?php else: ?>
                        <section class="workout-library-grid" data-workout-library-grid>
                            <?php foreach ($treinos as $treino): ?>
                                <article class="workout-library-card" data-workout-library-card data-search-text="<?php echo stridebr_e(stridebr_lower(trim((string) ($treino['codigo'] ?? '')) . ' ' . (string) $treino['titulo'] . ' ' . trim((string) ($treino['foco'] ?? '')))); ?>">
                                    <div class="workout-library-card-top"><div class="workout-library-code"><?php echo trim((string) ($treino['codigo'] ?? '')) !== '' ? stridebr_e((string) $treino['codigo']) : '—'; ?></div><div class="workout-library-card-title"><strong title="<?php echo stridebr_e((string) $treino['titulo']); ?>"><?php echo stridebr_e((string) $treino['titulo']); ?></strong><span><?php echo !empty($treino['modalidade_nome']) ? stridebr_e((string) $treino['modalidade_nome']) : 'Tipo automático'; ?></span></div></div>
                                    <?php if (!empty($treino['foco'])): ?><p class="workout-library-focus"><?php echo stridebr_e((string) $treino['foco']); ?></p><?php endif; ?>
                                    <div class="workout-library-stats"><span><strong><?php echo (int) $treino['exercicios_total']; ?></strong> <?php echo stridebr_e(stridebr_tn('library.exercises_count.one', 'library.exercises_count.other', (int) $treino['exercicios_total'], ['count' => ''])); ?></span><span><strong><?php echo (int) $treino['usos_total']; ?></strong> <?php echo stridebr_e(stridebr_t('library.schedules_count')); ?></span></div>
                                    <div class="workout-library-card-actions"><button type="button" class="secondary-button" data-edit-workout-library="<?php echo stridebr_e((string) $treino['idtreino_modelo']); ?>"><?php echo stridebr_e(stridebr_t('library.edit_workout')); ?></button><a class="secondary-button" href="/user/exerciciostreinomodelo.php?idtreino_modelo=<?php echo rawurlencode((string) $treino['idtreino_modelo']); ?>&return_to=<?php echo rawurlencode('/user/biblioteca.php?tab=treinos'); ?>"><?php echo stridebr_e(stridebr_t('library.manage_exercises')); ?></a><details class="library-more-menu"><summary aria-label="<?php echo stridebr_e(stridebr_t('schedule.more_actions')); ?>">•••</summary><div><form method="POST" data-confirm="<?php echo stridebr_e(stridebr_t('library.remove_workout_confirm')); ?>"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="archive_model"><input type="hidden" name="idtreino_modelo" value="<?php echo stridebr_e((string) $treino['idtreino_modelo']); ?>"><button type="submit" class="is-danger"><?php echo stridebr_e(stridebr_t('library.archive_workout')); ?></button></form></div></details></div>
                                </article>
                            <?php endforeach; ?>
                        </section>
                    <?php endif; ?>
                </section>

                <section class="library-tab-view<?php echo $tab === 'exercicios' ? ' is-active' : ''; ?>" data-library-view="exercicios"<?php echo $tab !== 'exercicios' ? ' hidden' : ''; ?>>
                    <section class="library-toolbar content-card">
                        <div class="library-filter-group" data-library-filters><button type="button" class="view-button is-active" data-library-filter="all"><?php echo stridebr_e(stridebr_t('common.all')); ?></button><button type="button" class="view-button" data-library-filter="system"><?php echo stridebr_e(stridebr_t('library.stridebr')); ?></button><button type="button" class="view-button" data-library-filter="personal"><?php echo stridebr_e(stridebr_t('library.my_exercises')); ?></button><span class="library-result-count"><strong data-library-visible-count><?php echo count($exercicios); ?></strong> <?php echo stridebr_e(stridebr_t('library.visible')); ?></span></div>
                        <label class="library-search-field"><span class="sr-only"><?php echo stridebr_e(stridebr_t('library.search_exercise')); ?></span><input type="search" placeholder="<?php echo stridebr_e(stridebr_t('library.search_exercise')); ?>" data-library-search></label>
                    </section>
                    <?php if ($exercicios === []): ?>
                        <section class="workout-library-empty content-card"><strong><?php echo stridebr_e(stridebr_t('library.no_exercises')); ?></strong><button type="button" class="primary-button" data-new-exercise><?php echo stridebr_e(stridebr_t('library.create_exercise')); ?></button></section>
                    <?php else: ?>
                        <section class="exercise-library-grid library-exercise-grid-modern">
                            <?php foreach ($exercicios as $item): $isPersonal = $item['idusuario'] !== null; ?>
                                <article class="library-exercise-card" data-library-card data-library-type="<?php echo $isPersonal ? 'personal' : 'system'; ?>" data-library-text="<?php echo stridebr_e(stridebr_lower($item['nome'] . ' ' . $item['categorias'] . ' ' . ($item['modalidades'] ?? ''))); ?>">
                                    <?php if ($mediaEnabled && !empty($item['imagem_url'])): ?><div class="library-exercise-media"><img src="<?php echo stridebr_e($item['imagem_url']); ?>" alt="<?php echo stridebr_e(stridebr_t('library.demo_alt', ['name' => (string) $item['nome']])); ?>" loading="lazy" decoding="async"></div><?php endif; ?>
                                    <div class="library-exercise-main"><div class="library-card-top"><span class="library-origin<?php echo $isPersonal ? ' is-personal' : ''; ?>"><?php echo stridebr_e(stridebr_t($isPersonal ? 'library.personal_exercise' : 'library.stridebr')); ?></span><h2 title="<?php echo stridebr_e($item['nome']); ?>"><?php echo stridebr_e($item['nome']); ?></h2></div><?php if (!empty($item['descricao'])): ?><p class="library-exercise-description"><?php echo stridebr_e($item['descricao']); ?></p><?php endif; ?></div>
                                    <div class="library-exercise-tags"><?php if (!empty($item['categorias'])): ?><span><b><?php echo stridebr_e(stridebr_t('library.categories')); ?></b><?php echo stridebr_e($item['categorias']); ?></span><?php endif; ?><?php if (!empty($item['modalidades'])): ?><span><b><?php echo stridebr_e(stridebr_t('library.sports')); ?></b><?php echo stridebr_e($item['modalidades']); ?></span><?php endif; ?><?php if ($mediaEnabled && !empty($item['video_url'])): ?><a class="exercise-video-link" href="<?php echo stridebr_e($item['video_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo stridebr_e(stridebr_t('library.view_demo')); ?></a><?php endif; ?></div>
                                    <div class="library-card-actions"><?php if ($isPersonal): ?><button type="button" class="secondary-button" data-edit-exercise="<?php echo stridebr_e((string) $item['idexercicio']); ?>"><?php echo stridebr_e(stridebr_t('common.edit')); ?></button><details class="library-more-menu"><summary aria-label="<?php echo stridebr_e(stridebr_t('schedule.more_actions')); ?>">•••</summary><div><form method="POST" data-confirm="<?php echo stridebr_e(stridebr_t('library.remove_exercise_confirm')); ?>"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="archive_exercise"><input type="hidden" name="idexercicio" value="<?php echo stridebr_e($item['idexercicio']); ?>"><button type="submit" class="is-danger"><?php echo stridebr_e(stridebr_t('library.remove_exercise')); ?></button></form></div></details><?php else: ?><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="duplicate_system"><input type="hidden" name="idexercicio" value="<?php echo stridebr_e($item['idexercicio']); ?>"><button type="submit" class="secondary-button"><?php echo stridebr_e(stridebr_t('library.personalize_exercise')); ?></button></form><?php endif; ?></div>
                                </article>
                            <?php endforeach; ?>
                        </section>
                        <section class="workout-library-empty content-card" data-library-filter-empty hidden><strong><?php echo stridebr_e(stridebr_t('library.empty')); ?></strong></section>
                    <?php endif; ?>
                </section>
            </div>
            <?php stridebr_render_ad_slot('library-end', '/user/biblioteca.php', true); ?>
        </div>
    </main>
</div>

<div class="library-editor-modal" data-workout-library-modal data-initial-edit="<?php echo stridebr_e((string) ($editModel['idtreino_modelo'] ?? '')); ?>"<?php echo $editModel === [] ? ' hidden' : ''; ?>>
    <button type="button" class="library-editor-backdrop" data-close-workout-library aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>"></button>
    <section class="library-editor-dialog" role="dialog" aria-modal="true" aria-labelledby="workout-library-dialog-title"><header class="library-editor-header"><div><span class="eyebrow"><?php echo stridebr_e(stridebr_t('library.reusable_workout')); ?></span><h2 id="workout-library-dialog-title" data-library-modal-title><?php echo stridebr_e($editModel !== [] ? stridebr_t('library.edit_workout') : stridebr_t('library.new_workout')); ?></h2></div><button type="button" class="library-editor-close" data-close-workout-library aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button></header><form method="POST" class="workout-library-form" data-workout-library-form><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="save_model"><input type="hidden" name="idtreino_modelo" value="<?php echo stridebr_e((string) ($editModel['idtreino_modelo'] ?? '')); ?>"><label class="is-wide"><?php echo stridebr_e(stridebr_t('library.workout_name')); ?><input type="text" name="titulo" maxlength="120" value="<?php echo stridebr_e((string) ($editModel['titulo'] ?? '')); ?>" required></label><label><?php echo stridebr_e(stridebr_t('schedule.code')); ?><input type="text" name="codigo" maxlength="24" value="<?php echo stridebr_e((string) ($editModel['codigo'] ?? '')); ?>"></label><div class="workout-library-sport-field"><span class="form-field-label"><?php echo stridebr_e(stridebr_t('library.modality')); ?></span><?php echo sportPickerRenderSelect($modalidadesTreino, ['name' => 'idmodalidade', 'selected' => (string) ($editModel['idmodalidade'] ?? ''), 'empty_label' => stridebr_t('library.auto_modality')]); ?></div><label class="is-wide"><?php echo stridebr_e(stridebr_t('schedule.focus')); ?><input type="text" name="foco" maxlength="160" value="<?php echo stridebr_e((string) ($editModel['foco'] ?? '')); ?>"></label><label class="is-wide"><?php echo stridebr_e(stridebr_t('common.description')); ?><textarea name="descricao" rows="4"><?php echo stridebr_e((string) ($editModel['descricao'] ?? '')); ?></textarea></label><label class="workout-library-propagate is-wide" data-workout-propagate<?php echo $editModel === [] ? ' hidden' : ''; ?>><input type="checkbox" name="propagar_vinculados" value="1"<?php echo $editModel !== [] ? ' checked' : ''; ?>><span><?php echo stridebr_e(stridebr_t('library.update_linked_schedules')); ?> <small><strong data-workout-uses><?php echo (int) ($editModel['usos_total'] ?? 0); ?></strong> <?php echo stridebr_e(stridebr_t('library.uses')); ?></small></span></label><div class="workout-library-form-actions is-wide"><a class="secondary-button" data-workout-exercises-link<?php if ($editModel === []): ?> hidden<?php else: ?> href="/user/exerciciostreinomodelo.php?idtreino_modelo=<?php echo rawurlencode((string) $editModel['idtreino_modelo']); ?>&return_to=<?php echo rawurlencode('/user/biblioteca.php?tab=treinos'); ?>"<?php endif; ?>><?php echo stridebr_e(stridebr_t('schedule.edit_exercises')); ?></a><span></span><button type="button" class="secondary-button" data-close-workout-library><?php echo stridebr_e(stridebr_t('common.cancel')); ?></button><button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('schedule.save_workout')); ?></button></div></form></section>
</div>

<div class="library-editor-modal" data-exercise-library-modal data-initial-edit="<?php echo isset($exerciseData[$editExerciseId]) ? stridebr_e($editExerciseId) : ''; ?>"<?php echo $editExerciseId === '' || !isset($exerciseData[$editExerciseId]) ? ' hidden' : ''; ?>>
    <button type="button" class="library-editor-backdrop" data-close-exercise-library aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>"></button>
    <section class="library-editor-dialog library-exercise-dialog" role="dialog" aria-modal="true" aria-labelledby="exercise-library-dialog-title"><header class="library-editor-header"><div><span class="eyebrow"><?php echo stridebr_e(stridebr_t('library.personal_exercise')); ?></span><h2 id="exercise-library-dialog-title" data-library-modal-title><?php echo stridebr_e($editExerciseId !== '' ? stridebr_t('library.edit_exercise') : stridebr_t('library.new_exercise')); ?></h2></div><button type="button" class="library-editor-close" data-close-exercise-library aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button></header><form method="POST" class="library-exercise-form" data-exercise-library-form><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="<?php echo $editExerciseId !== '' ? 'update_exercise' : 'create_exercise'; ?>"><input type="hidden" name="idexercicio" value="<?php echo stridebr_e($editExerciseId); ?>"><label><?php echo stridebr_e(stridebr_t('common.name')); ?><input type="text" name="nome" maxlength="120" required></label><label><?php echo stridebr_e(stridebr_t('common.description')); ?><textarea name="descricao" rows="4"></textarea></label><?php if ($mediaEnabled): ?><div class="library-form-columns"><label><?php echo stridebr_e(stridebr_t('library.main_image_url')); ?><input type="url" name="imagem_url" maxlength="2000" placeholder="https://..."></label><label><?php echo stridebr_e(stridebr_t('library.demo_video_url')); ?><input type="url" name="video_url" maxlength="2000" placeholder="https://..."></label></div><?php endif; ?><details class="library-selector-panel"><summary><?php echo stridebr_e(stridebr_t('library.categories')); ?> <span><?php echo stridebr_e(stridebr_t('library.optional')); ?></span></summary><div class="choice-grid small"><?php foreach ($categorias as $categoria): ?><label class="check-label"><input type="checkbox" name="categorias[]" data-category-option value="<?php echo stridebr_e($categoria['idcategoria']); ?>"> <?php echo stridebr_e($categoria['nome']); ?><?php echo $categoria['idusuario'] === null ? ' · StrideBR' : ''; ?></label><?php endforeach; ?></div></details><details class="library-selector-panel"><summary><?php echo stridebr_e(stridebr_t('library.related_sports')); ?> <span><?php echo stridebr_e(stridebr_t('library.optional')); ?></span></summary><div class="choice-grid small"><?php foreach ($modalidadesExercicio as $modalidade): ?><label class="check-label"><input type="checkbox" name="modalidades[]" data-modality-option value="<?php echo stridebr_e($modalidade['idmodalidade']); ?>"> <?php echo stridebr_e(stridebr_sport_name((string) ($modalidade['slug'] ?? ''), (string) $modalidade['nome'])); ?></label><?php endforeach; ?></div></details><div class="library-modal-actions"><button type="button" class="secondary-button" data-close-exercise-library><?php echo stridebr_e(stridebr_t('common.cancel')); ?></button><button type="submit" class="primary-button" data-exercise-submit><?php echo stridebr_e(stridebr_t('library.save_exercise')); ?></button></div></form></section>
</div>

<div class="library-editor-modal" data-category-library-modal hidden><button type="button" class="library-editor-backdrop" data-close-category-library aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>"></button><section class="library-editor-dialog library-category-dialog" role="dialog" aria-modal="true" aria-labelledby="category-library-dialog-title"><header class="library-editor-header"><div><span class="eyebrow"><?php echo stridebr_e(stridebr_t('library.organization')); ?></span><h2 id="category-library-dialog-title"><?php echo stridebr_e(stridebr_t('library.new_category')); ?></h2></div><button type="button" class="library-editor-close" data-close-category-library aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button></header><form method="POST" class="library-exercise-form"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="create_category"><label><?php echo stridebr_e(stridebr_t('common.name')); ?><input type="text" name="nome" maxlength="80" placeholder="<?php echo stridebr_e(stridebr_t('library.category_placeholder')); ?>" required></label><div class="library-modal-actions"><button type="button" class="secondary-button" data-close-category-library><?php echo stridebr_e(stridebr_t('common.cancel')); ?></button><button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('library.add_category')); ?></button></div></form></section></div>
<script type="application/json" data-workout-library-data><?php echo json_encode($workoutData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script type="application/json" data-exercise-library-data><?php echo json_encode($exerciseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/library.js')); ?>"></script>
</body>
</html>
