<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();

require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma.php';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
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
                is_array($_POST['modalidades'] ?? null) ? $_POST['modalidades'] : []
            );
            stridebr_flash('success', 'Exercício salvo na sua biblioteca.');
        } elseif ($action === 'duplicate_system') {
            $id = cronogramaDuplicarExercicioSistema($pdo, $idUsuario, (string) ($_POST['idexercicio'] ?? ''));
            stridebr_flash('success', 'Uma cópia editável foi adicionada à sua biblioteca.');
            header('Location: /user/bibliotecaexercicios.php?edit=' . urlencode($id));
            exit;
        } elseif ($action === 'update_exercise') {
            if (!cronogramaAtualizarExercicioPessoal(
                $pdo,
                $idUsuario,
                (string) ($_POST['idexercicio'] ?? ''),
                (string) ($_POST['nome'] ?? ''),
                $_POST['descricao'] ?? null,
                is_array($_POST['categorias'] ?? null) ? $_POST['categorias'] : [],
                is_array($_POST['modalidades'] ?? null) ? $_POST['modalidades'] : []
            )) {
                throw new RuntimeException('Exercício pessoal não encontrado.');
            }
            stridebr_flash('success', 'Exercício atualizado.');
        } elseif ($action === 'archive_exercise') {
            if (!cronogramaDesativarExercicioPessoal($pdo, $idUsuario, (string) ($_POST['idexercicio'] ?? ''))) {
                throw new RuntimeException('Exercício pessoal não encontrado.');
            }
            stridebr_flash('success', 'Exercício removido da sua biblioteca. Os treinos existentes mantêm o nome salvo.');
        }
        header('Location: /user/bibliotecaexercicios.php');
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível concluir a operação.';
        if (!$e instanceof InvalidArgumentException && !$e instanceof RuntimeException) {
            error_log($e->getMessage());
        }
    }
}

$exercicios = cronogramaListarExerciciosBiblioteca($pdo, $idUsuario);
$categorias = cronogramaListarCategorias($pdo, $idUsuario);
$modalidades = cronogramaListarModalidadesExercicio($pdo, $idUsuario);
$linksCategorias = [];
$linksModalidades = [];
if ($exercicios !== []) {
    $ids = array_column($exercicios, 'idexercicio');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT idexercicio, idcategoria FROM exercicios_categorias WHERE idexercicio IN ({$placeholders})");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $linksCategorias[$row['idexercicio']][] = $row['idcategoria'];
    }
    $stmt = $pdo->prepare("SELECT idexercicio, idmodalidade FROM exercicios_modalidades WHERE idexercicio IN ({$placeholders})");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $linksModalidades[$row['idexercicio']][] = $row['idmodalidade'];
    }
}
$editId = (string) ($_GET['edit'] ?? '');
$flashes = stridebr_take_flashes();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/assets/img/favicon/favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/cronogramas.css">
    <title>Biblioteca de exercícios | StrideBR</title>
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content library-page">
        <div class="library-shell">
            <div class="exercise-heading">
                <div>
                    <a class="back-link" href="/user/cronogramatreinos.php">← Cronogramas</a>
                    <h1>Biblioteca de exercícios</h1>
                    <p>Use exercícios do StrideBR por referência ou crie versões pessoais sem duplicar conteúdo desnecessariamente.</p>
                </div>
            </div>

            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div>
            <?php endforeach; ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger"><?php echo stridebr_e($error); ?></div>
            <?php endforeach; ?>

            <div class="library-create-grid">
                <section class="library-form-card">
                    <h2>Novo exercício pessoal</h2>
                    <form method="POST" class="stack-form">
                        <?php echo stridebr_csrf_field(); ?>
                        <input type="hidden" name="action" value="create_exercise">
                        <label>Nome<input type="text" name="nome" maxlength="120" required></label>
                        <label>Descrição<textarea name="descricao" rows="3"></textarea></label>
                        <fieldset>
                            <legend>Categorias</legend>
                            <div class="choice-grid">
                                <?php foreach ($categorias as $categoria): ?>
                                    <label class="check-label"><input type="checkbox" name="categorias[]" value="<?php echo stridebr_e($categoria['idcategoria']); ?>"> <?php echo stridebr_e($categoria['nome']); ?><?php echo $categoria['idusuario'] === null ? ' · StrideBR' : ''; ?></label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <fieldset>
                            <legend>Modalidades relacionadas</legend>
                            <div class="choice-grid">
                                <?php foreach ($modalidades as $modalidade): ?>
                                    <label class="check-label"><input type="checkbox" name="modalidades[]" value="<?php echo stridebr_e($modalidade['idmodalidade']); ?>"> <?php echo stridebr_e($modalidade['nome']); ?></label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <button type="submit" class="primary-button">Salvar na biblioteca</button>
                    </form>
                </section>

                <section class="library-form-card">
                    <h2>Nova categoria pessoal</h2>
                    <form method="POST" class="compact-form vertical">
                        <?php echo stridebr_csrf_field(); ?>
                        <input type="hidden" name="action" value="create_category">
                        <label>Nome<input type="text" name="nome" maxlength="80" placeholder="Ex.: Mobilidade de ombro" required></label>
                        <button type="submit" class="secondary-button">Adicionar categoria</button>
                    </form>
                    <p class="library-note">As categorias do StrideBR continuam disponíveis e suas categorias ficam privadas na estrutura atual.</p>
                </section>
            </div>

            <div class="library-tabs" data-library-filters>
                <button type="button" class="view-button is-active" data-library-filter="all">Todos</button>
                <button type="button" class="view-button" data-library-filter="system">StrideBR</button>
                <button type="button" class="view-button" data-library-filter="personal">Meus exercícios</button>
                <input type="search" placeholder="Buscar exercício" data-library-search>
            </div>

            <section class="exercise-library-grid">
                <?php foreach ($exercicios as $item): ?>
                    <?php $isPersonal = $item['idusuario'] !== null; ?>
                    <article class="library-exercise-card" data-library-card data-library-type="<?php echo $isPersonal ? 'personal' : 'system'; ?>" data-library-text="<?php echo stridebr_e(stridebr_lower($item['nome'] . ' ' . $item['categorias'] . ' ' . ($item['modalidades'] ?? ''))); ?>">
                        <?php if ($isPersonal && $editId === $item['idexercicio']): ?>
                            <form method="POST" class="stack-form">
                                <?php echo stridebr_csrf_field(); ?>
                                <input type="hidden" name="action" value="update_exercise">
                                <input type="hidden" name="idexercicio" value="<?php echo stridebr_e($item['idexercicio']); ?>">
                                <label>Nome<input type="text" name="nome" value="<?php echo stridebr_e($item['nome']); ?>" maxlength="120" required></label>
                                <label>Descrição<textarea name="descricao" rows="3"><?php echo stridebr_e($item['descricao'] ?? ''); ?></textarea></label>
                                <fieldset>
                                    <legend>Categorias</legend>
                                    <div class="choice-grid small">
                                        <?php foreach ($categorias as $categoria): ?>
                                            <label class="check-label"><input type="checkbox" name="categorias[]" value="<?php echo stridebr_e($categoria['idcategoria']); ?>"<?php echo in_array($categoria['idcategoria'], $linksCategorias[$item['idexercicio']] ?? [], true) ? ' checked' : ''; ?>> <?php echo stridebr_e($categoria['nome']); ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </fieldset>
                                <fieldset>
                                    <legend>Modalidades</legend>
                                    <div class="choice-grid small">
                                        <?php foreach ($modalidades as $modalidade): ?>
                                            <label class="check-label"><input type="checkbox" name="modalidades[]" value="<?php echo stridebr_e($modalidade['idmodalidade']); ?>"<?php echo in_array($modalidade['idmodalidade'], $linksModalidades[$item['idexercicio']] ?? [], true) ? ' checked' : ''; ?>> <?php echo stridebr_e($modalidade['nome']); ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </fieldset>
                                <div class="form-actions"><button type="submit" class="primary-button">Salvar</button><a class="secondary-button" href="/user/bibliotecaexercicios.php">Cancelar</a></div>
                            </form>
                        <?php else: ?>
                            <div class="library-card-top">
                                <span class="library-origin"><?php echo $isPersonal ? 'Sua biblioteca' : 'StrideBR'; ?></span>
                                <h2><?php echo stridebr_e($item['nome']); ?></h2>
                            </div>
                            <?php if (!empty($item['descricao'])): ?><p><?php echo stridebr_e($item['descricao']); ?></p><?php endif; ?>
                            <?php if (!empty($item['categorias'])): ?><div class="library-meta"><strong>Categorias:</strong> <?php echo stridebr_e($item['categorias']); ?></div><?php endif; ?>
                            <?php if (!empty($item['modalidades'])): ?><div class="library-meta"><strong>Modalidades:</strong> <?php echo stridebr_e($item['modalidades']); ?></div><?php endif; ?>
                            <div class="library-card-actions">
                                <?php if ($isPersonal): ?>
                                    <a class="secondary-button" href="/user/bibliotecaexercicios.php?edit=<?php echo urlencode($item['idexercicio']); ?>">Editar</a>
                                    <form method="POST" onsubmit="return confirm('Remover este exercício da sua biblioteca?');">
                                        <?php echo stridebr_csrf_field(); ?>
                                        <input type="hidden" name="action" value="archive_exercise">
                                        <input type="hidden" name="idexercicio" value="<?php echo stridebr_e($item['idexercicio']); ?>">
                                        <button type="submit" class="danger-button">Remover</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST">
                                        <?php echo stridebr_csrf_field(); ?>
                                        <input type="hidden" name="action" value="duplicate_system">
                                        <input type="hidden" name="idexercicio" value="<?php echo stridebr_e($item['idexercicio']); ?>">
                                        <button type="submit" class="secondary-button">Criar cópia editável</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        </div>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="/assets/js/cronogramas.js"></script>
</body>
</html>
