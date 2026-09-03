<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

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
            stridebr_flash('success', $isExisting ? 'Treino atualizado.' : 'Treino adicionado à biblioteca.');
            header('Location: /user/biblioteca.php?tab=treinos');
            exit;
        }
        if ($action === 'archive_model') {
            if (!cronogramaArquivarTreinoModelo($pdo, $idUsuario, (string) ($_POST['idtreino_modelo'] ?? ''))) {
                throw new RuntimeException('Treino salvo não encontrado.');
            }
            stridebr_flash('success', 'Treino removido da biblioteca. Os cronogramas existentes foram preservados.');
            header('Location: /user/biblioteca.php?tab=treinos');
            exit;
        }
        if ($action === 'create_category') {
            cronogramaCriarCategoria($pdo, $idUsuario, (string) ($_POST['nome'] ?? ''));
            stridebr_flash('success', 'Categoria adicionada.');
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
            stridebr_flash('success', 'Exercício salvo na sua biblioteca.');
        } elseif ($action === 'duplicate_system') {
            $id = cronogramaDuplicarExercicioSistema($pdo, $idUsuario, (string) ($_POST['idexercicio'] ?? ''));
            stridebr_flash('success', 'Uma cópia editável foi adicionada à sua biblioteca.');
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
                throw new RuntimeException('Exercício pessoal não encontrado.');
            }
            stridebr_flash('success', 'Exercício atualizado.');
        } elseif ($action === 'archive_exercise') {
            $idExercicio = (string) ($_POST['idexercicio'] ?? '');
            if (!cronogramaDesativarExercicioPessoal($pdo, $idUsuario, $idExercicio)) {
                throw new RuntimeException('Exercício pessoal não encontrado.');
            }
            $_SESSION['exercise_library_undo'] = [
                'idexercicio' => $idExercicio,
                'token' => bin2hex(random_bytes(16)),
                'expires' => time() + 300,
            ];
            stridebr_flash('success', 'Exercício removido da sua biblioteca.');
        } elseif ($action === 'restore_exercise') {
            $undo = is_array($_SESSION['exercise_library_undo'] ?? null) ? $_SESSION['exercise_library_undo'] : [];
            $token = (string) ($_POST['undo_token'] ?? '');
            if ($undo === [] || (int) ($undo['expires'] ?? 0) < time() || !hash_equals((string) ($undo['token'] ?? ''), $token)) {
                unset($_SESSION['exercise_library_undo']);
                throw new RuntimeException('O prazo para desfazer terminou.');
            }
            if (!cronogramaRestaurarExercicioPessoal($pdo, $idUsuario, (string) ($undo['idexercicio'] ?? ''))) {
                throw new RuntimeException('Não foi possível restaurar o exercício.');
            }
            unset($_SESSION['exercise_library_undo']);
            stridebr_flash('success', 'Exercício restaurado na sua biblioteca.');
        } elseif (!in_array($action, ['save_model', 'archive_model'], true)) {
            throw new InvalidArgumentException('Ação inválida.');
        }
        header('Location: /user/biblioteca.php?tab=exercicios');
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível concluir a operação.';
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
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
    <title>Biblioteca | StrideBR</title>
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content library-page workout-library-page" data-library-page data-library-active-tab="<?php echo stridebr_e($tab); ?>">
        <div class="workout-library-shell">
            <header class="library-page-heading">
                <div>
                    <span class="eyebrow">Planejamento</span>
                    <h1>Biblioteca</h1>
                    <p>Treinos e exercícios reutilizáveis.</p>
                </div>
                <div class="library-heading-actions" data-library-heading-actions="treinos"<?php echo $tab !== 'treinos' ? ' hidden' : ''; ?>><button type="button" class="primary-button" data-new-workout-library>+ Novo treino</button></div>
                <div class="library-heading-actions" data-library-heading-actions="exercicios"<?php echo $tab !== 'exercicios' ? ' hidden' : ''; ?>><button type="button" class="secondary-button" data-new-category>Nova categoria</button><button type="button" class="primary-button" data-new-exercise>+ Novo exercício</button></div>
            </header>

            <nav class="workout-library-tabs" aria-label="Biblioteca" data-library-tabs>
                <a class="<?php echo $tab === 'treinos' ? 'is-active' : ''; ?>" href="/user/biblioteca.php?tab=treinos" data-library-tab="treinos">Treinos</a>
                <a class="<?php echo $tab === 'exercicios' ? 'is-active' : ''; ?>" href="/user/biblioteca.php?tab=exercicios" data-library-tab="exercicios">Exercícios</a>
            </nav>

            <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div><?php endforeach; ?>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
            <?php if ($exerciseUndo !== null): ?><div class="schedule-undo-bar" role="status" data-library-exercise-undo<?php echo $tab !== 'exercicios' ? ' hidden' : ''; ?>><span>Exercício removido da biblioteca.</span><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="restore_exercise"><input type="hidden" name="undo_token" value="<?php echo stridebr_e((string) ($exerciseUndo['token'] ?? '')); ?>"><button type="submit">Desfazer</button></form></div><?php endif; ?>

            <div class="library-tab-stage" data-library-tab-stage>
                <section class="library-tab-view<?php echo $tab === 'treinos' ? ' is-active' : ''; ?>" data-library-view="treinos"<?php echo $tab !== 'treinos' ? ' hidden' : ''; ?>>
                    <section class="library-toolbar content-card">
                        <div><strong>Treinos salvos</strong><span><?php echo count($treinos); ?> na biblioteca</span></div>
                        <label class="library-search-field"><span class="sr-only">Buscar treino</span><input type="search" placeholder="Buscar por nome, código ou foco" data-workout-library-search></label>
                    </section>
                    <?php if ($treinos === []): ?>
                        <section class="workout-library-empty content-card"><strong>Nenhum treino salvo ainda.</strong><button type="button" class="primary-button" data-new-workout-library>Criar treino</button></section>
                    <?php else: ?>
                        <section class="workout-library-grid" data-workout-library-grid>
                            <?php foreach ($treinos as $treino): ?>
                                <article class="workout-library-card" data-workout-library-card data-search-text="<?php echo stridebr_e(stridebr_lower(trim((string) ($treino['codigo'] ?? '')) . ' ' . (string) $treino['titulo'] . ' ' . trim((string) ($treino['foco'] ?? '')))); ?>">
                                    <div class="workout-library-card-top"><div class="workout-library-code"><?php echo trim((string) ($treino['codigo'] ?? '')) !== '' ? stridebr_e((string) $treino['codigo']) : '—'; ?></div><div class="workout-library-card-title"><strong title="<?php echo stridebr_e((string) $treino['titulo']); ?>"><?php echo stridebr_e((string) $treino['titulo']); ?></strong><span><?php echo !empty($treino['modalidade_nome']) ? stridebr_e((string) $treino['modalidade_nome']) : 'Tipo automático'; ?></span></div></div>
                                    <?php if (!empty($treino['foco'])): ?><p class="workout-library-focus"><?php echo stridebr_e((string) $treino['foco']); ?></p><?php endif; ?>
                                    <div class="workout-library-stats"><span><strong><?php echo (int) $treino['exercicios_total']; ?></strong> exercícios</span><span><strong><?php echo (int) $treino['usos_total']; ?></strong> cronograma(s)</span></div>
                                    <div class="workout-library-card-actions"><button type="button" class="secondary-button" data-edit-workout-library="<?php echo stridebr_e((string) $treino['idtreino_modelo']); ?>">Editar</button><a class="secondary-button" href="/user/exerciciostreinomodelo.php?idtreino_modelo=<?php echo rawurlencode((string) $treino['idtreino_modelo']); ?>&return_to=<?php echo rawurlencode('/user/biblioteca.php?tab=treinos'); ?>">Exercícios</a><details class="library-more-menu"><summary aria-label="Mais ações">•••</summary><div><form method="POST" data-confirm="Remover este treino da biblioteca? Os cronogramas que já usam ele continuam existindo."><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="archive_model"><input type="hidden" name="idtreino_modelo" value="<?php echo stridebr_e((string) $treino['idtreino_modelo']); ?>"><button type="submit" class="is-danger">Arquivar treino</button></form></div></details></div>
                                </article>
                            <?php endforeach; ?>
                        </section>
                    <?php endif; ?>
                </section>

                <section class="library-tab-view<?php echo $tab === 'exercicios' ? ' is-active' : ''; ?>" data-library-view="exercicios"<?php echo $tab !== 'exercicios' ? ' hidden' : ''; ?>>
                    <section class="library-toolbar content-card">
                        <div class="library-filter-group" data-library-filters><button type="button" class="view-button is-active" data-library-filter="all">Todos</button><button type="button" class="view-button" data-library-filter="system">StrideBR</button><button type="button" class="view-button" data-library-filter="personal">Meus exercícios</button><span class="library-result-count"><strong data-library-visible-count><?php echo count($exercicios); ?></strong> visíveis</span></div>
                        <label class="library-search-field"><span class="sr-only">Buscar exercício</span><input type="search" placeholder="Buscar exercício" data-library-search></label>
                    </section>
                    <?php if ($exercicios === []): ?>
                        <section class="workout-library-empty content-card"><strong>Nenhum exercício disponível.</strong><button type="button" class="primary-button" data-new-exercise>Criar exercício</button></section>
                    <?php else: ?>
                        <section class="exercise-library-grid library-exercise-grid-modern">
                            <?php foreach ($exercicios as $item): $isPersonal = $item['idusuario'] !== null; ?>
                                <article class="library-exercise-card" data-library-card data-library-type="<?php echo $isPersonal ? 'personal' : 'system'; ?>" data-library-text="<?php echo stridebr_e(stridebr_lower($item['nome'] . ' ' . $item['categorias'] . ' ' . ($item['modalidades'] ?? ''))); ?>">
                                    <?php if ($mediaEnabled && !empty($item['imagem_url'])): ?><div class="library-exercise-media"><img src="<?php echo stridebr_e($item['imagem_url']); ?>" alt="Demonstração de <?php echo stridebr_e($item['nome']); ?>" loading="lazy" decoding="async"></div><?php endif; ?>
                                    <div class="library-exercise-main"><div class="library-card-top"><span class="library-origin<?php echo $isPersonal ? ' is-personal' : ''; ?>"><?php echo $isPersonal ? 'Seu' : 'StrideBR'; ?></span><h2 title="<?php echo stridebr_e($item['nome']); ?>"><?php echo stridebr_e($item['nome']); ?></h2></div><?php if (!empty($item['descricao'])): ?><p class="library-exercise-description"><?php echo stridebr_e($item['descricao']); ?></p><?php endif; ?></div>
                                    <div class="library-exercise-tags"><?php if (!empty($item['categorias'])): ?><span><b>Categorias</b><?php echo stridebr_e($item['categorias']); ?></span><?php endif; ?><?php if (!empty($item['modalidades'])): ?><span><b>Modalidades</b><?php echo stridebr_e($item['modalidades']); ?></span><?php endif; ?><?php if ($mediaEnabled && !empty($item['video_url'])): ?><a class="exercise-video-link" href="<?php echo stridebr_e($item['video_url']); ?>" target="_blank" rel="noopener noreferrer">Ver demonstração</a><?php endif; ?></div>
                                    <div class="library-card-actions"><?php if ($isPersonal): ?><button type="button" class="secondary-button" data-edit-exercise="<?php echo stridebr_e((string) $item['idexercicio']); ?>">Editar</button><details class="library-more-menu"><summary aria-label="Mais ações">•••</summary><div><form method="POST" data-confirm="Remover este exercício da sua biblioteca?"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="archive_exercise"><input type="hidden" name="idexercicio" value="<?php echo stridebr_e($item['idexercicio']); ?>"><button type="submit" class="is-danger">Remover exercício</button></form></div></details><?php else: ?><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="duplicate_system"><input type="hidden" name="idexercicio" value="<?php echo stridebr_e($item['idexercicio']); ?>"><button type="submit" class="secondary-button">Criar cópia</button></form><?php endif; ?></div>
                                </article>
                            <?php endforeach; ?>
                        </section>
                        <section class="workout-library-empty content-card" data-library-filter-empty hidden><strong>Nada por aqui.</strong></section>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>
</div>

<div class="library-editor-modal" data-workout-library-modal data-initial-edit="<?php echo stridebr_e((string) ($editModel['idtreino_modelo'] ?? '')); ?>"<?php echo $editModel === [] ? ' hidden' : ''; ?>>
    <button type="button" class="library-editor-backdrop" data-close-workout-library aria-label="Fechar"></button>
    <section class="library-editor-dialog" role="dialog" aria-modal="true" aria-labelledby="workout-library-dialog-title"><header class="library-editor-header"><div><span class="eyebrow">Treino reutilizável</span><h2 id="workout-library-dialog-title" data-library-modal-title><?php echo $editModel !== [] ? 'Editar treino' : 'Novo treino'; ?></h2></div><button type="button" class="library-editor-close" data-close-workout-library aria-label="Fechar">×</button></header><form method="POST" class="workout-library-form" data-workout-library-form><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="save_model"><input type="hidden" name="idtreino_modelo" value="<?php echo stridebr_e((string) ($editModel['idtreino_modelo'] ?? '')); ?>"><label class="is-wide">Nome do treino<input type="text" name="titulo" maxlength="120" value="<?php echo stridebr_e((string) ($editModel['titulo'] ?? '')); ?>" required></label><label>Código<input type="text" name="codigo" maxlength="24" value="<?php echo stridebr_e((string) ($editModel['codigo'] ?? '')); ?>"></label><div class="workout-library-sport-field"><span class="form-field-label">Modalidade</span><?php echo sportPickerRenderSelect($modalidadesTreino, ['name' => 'idmodalidade', 'selected' => (string) ($editModel['idmodalidade'] ?? ''), 'empty_label' => 'Automática']); ?></div><label class="is-wide">Foco<input type="text" name="foco" maxlength="160" value="<?php echo stridebr_e((string) ($editModel['foco'] ?? '')); ?>"></label><label class="is-wide">Descrição<textarea name="descricao" rows="4"><?php echo stridebr_e((string) ($editModel['descricao'] ?? '')); ?></textarea></label><label class="workout-library-propagate is-wide" data-workout-propagate<?php echo $editModel === [] ? ' hidden' : ''; ?>><input type="checkbox" name="propagar_vinculados" value="1"<?php echo $editModel !== [] ? ' checked' : ''; ?>><span>Atualizar também os cronogramas vinculados <small><strong data-workout-uses><?php echo (int) ($editModel['usos_total'] ?? 0); ?></strong> uso(s)</small></span></label><div class="workout-library-form-actions is-wide"><a class="secondary-button" data-workout-exercises-link<?php if ($editModel === []): ?> hidden<?php else: ?> href="/user/exerciciostreinomodelo.php?idtreino_modelo=<?php echo rawurlencode((string) $editModel['idtreino_modelo']); ?>&return_to=<?php echo rawurlencode('/user/biblioteca.php?tab=treinos'); ?>"<?php endif; ?>>Editar exercícios</a><span></span><button type="button" class="secondary-button" data-close-workout-library>Cancelar</button><button type="submit" class="primary-button">Salvar treino</button></div></form></section>
</div>

<div class="library-editor-modal" data-exercise-library-modal data-initial-edit="<?php echo isset($exerciseData[$editExerciseId]) ? stridebr_e($editExerciseId) : ''; ?>"<?php echo $editExerciseId === '' || !isset($exerciseData[$editExerciseId]) ? ' hidden' : ''; ?>>
    <button type="button" class="library-editor-backdrop" data-close-exercise-library aria-label="Fechar"></button>
    <section class="library-editor-dialog library-exercise-dialog" role="dialog" aria-modal="true" aria-labelledby="exercise-library-dialog-title"><header class="library-editor-header"><div><span class="eyebrow">Exercício pessoal</span><h2 id="exercise-library-dialog-title" data-library-modal-title><?php echo $editExerciseId !== '' ? 'Editar exercício' : 'Novo exercício'; ?></h2></div><button type="button" class="library-editor-close" data-close-exercise-library aria-label="Fechar">×</button></header><form method="POST" class="library-exercise-form" data-exercise-library-form><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="<?php echo $editExerciseId !== '' ? 'update_exercise' : 'create_exercise'; ?>"><input type="hidden" name="idexercicio" value="<?php echo stridebr_e($editExerciseId); ?>"><label>Nome<input type="text" name="nome" maxlength="120" required></label><label>Descrição<textarea name="descricao" rows="4"></textarea></label><?php if ($mediaEnabled): ?><div class="library-form-columns"><label>Imagem principal (URL)<input type="url" name="imagem_url" maxlength="2000" placeholder="https://..."></label><label>Vídeo demonstrativo (URL)<input type="url" name="video_url" maxlength="2000" placeholder="https://..."></label></div><?php endif; ?><details class="library-selector-panel"><summary>Categorias <span>Opcional</span></summary><div class="choice-grid small"><?php foreach ($categorias as $categoria): ?><label class="check-label"><input type="checkbox" name="categorias[]" data-category-option value="<?php echo stridebr_e($categoria['idcategoria']); ?>"> <?php echo stridebr_e($categoria['nome']); ?><?php echo $categoria['idusuario'] === null ? ' · StrideBR' : ''; ?></label><?php endforeach; ?></div></details><details class="library-selector-panel"><summary>Modalidades relacionadas <span>Opcional</span></summary><div class="choice-grid small"><?php foreach ($modalidadesExercicio as $modalidade): ?><label class="check-label"><input type="checkbox" name="modalidades[]" data-modality-option value="<?php echo stridebr_e($modalidade['idmodalidade']); ?>"> <?php echo stridebr_e($modalidade['nome']); ?></label><?php endforeach; ?></div></details><div class="library-modal-actions"><button type="button" class="secondary-button" data-close-exercise-library>Cancelar</button><button type="submit" class="primary-button" data-exercise-submit>Salvar exercício</button></div></form></section>
</div>

<div class="library-editor-modal" data-category-library-modal hidden><button type="button" class="library-editor-backdrop" data-close-category-library aria-label="Fechar"></button><section class="library-editor-dialog library-category-dialog" role="dialog" aria-modal="true" aria-labelledby="category-library-dialog-title"><header class="library-editor-header"><div><span class="eyebrow">Organização</span><h2 id="category-library-dialog-title">Nova categoria</h2></div><button type="button" class="library-editor-close" data-close-category-library aria-label="Fechar">×</button></header><form method="POST" class="library-exercise-form"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="create_category"><label>Nome<input type="text" name="nome" maxlength="80" placeholder="Ex.: Mobilidade de ombro" required></label><div class="library-modal-actions"><button type="button" class="secondary-button" data-close-category-library>Cancelar</button><button type="submit" class="primary-button">Adicionar categoria</button></div></form></section></div>
<script type="application/json" data-workout-library-data><?php echo json_encode($workoutData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script type="application/json" data-exercise-library-data><?php echo json_encode($exerciseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/library.js')); ?>"></script>
</body>
</html>
