<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();

require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma.php';
$idTreino = (string) ($_GET['idtreino'] ?? $_POST['idtreino'] ?? '');
$treino = $idTreino !== '' ? cronogramaBuscarTreino($pdo, $idTreino, $idUsuario) : [];
if ($treino === []) {
    http_response_code(404);
    exit('Treino não encontrado.');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'save_exercises') {
            $campos = cronogramaListarCamposExtras($pdo, $idTreino, $idUsuario);
            cronogramaSalvarExercicios($pdo, $idTreino, $idUsuario, is_array($_POST['rows'] ?? null) ? $_POST['rows'] : [], $campos);
            stridebr_flash('success', 'Exercícios salvos.');
        } elseif ($action === 'add_field') {
            cronogramaAdicionarCampoExtra($pdo, $idTreino, $idUsuario, (string) ($_POST['nome'] ?? ''), (string) ($_POST['tipo'] ?? 'texto'));
            stridebr_flash('success', 'Coluna adicionada ao treino.');
        } elseif ($action === 'remove_field') {
            if (!cronogramaDesativarCampoExtra($pdo, $idTreino, $idUsuario, (string) ($_POST['idcampo'] ?? ''))) {
                throw new RuntimeException('Coluna não encontrada.');
            }
            stridebr_flash('success', 'Coluna removida deste treino.');
        } elseif ($action === 'copy_exercise') {
            if (!cronogramaCopiarExercicio($pdo, $idUsuario, (string) ($_POST['idtreino_exercicio'] ?? ''), (string) ($_POST['idtreino_destino'] ?? ''))) {
                throw new RuntimeException('Não foi possível copiar o exercício.');
            }
            stridebr_flash('success', 'Exercício copiado para o treino escolhido.');
        }
        header('Location: /user/exercicioscronograma.php?idtreino=' . urlencode($idTreino));
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível concluir a operação.';
        if (!$e instanceof InvalidArgumentException && !$e instanceof RuntimeException) {
            error_log($e->getMessage());
        }
    }
}

$exercicios = cronogramaListarTreinoExercicios($pdo, $idTreino, $idUsuario);
$camposExtras = cronogramaListarCamposExtras($pdo, $idTreino, $idUsuario);
$valoresExtras = cronogramaCarregarValoresExtras($pdo, $exercicios);
$biblioteca = cronogramaListarExerciciosBiblioteca($pdo, $idUsuario);
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
        return '<select name="' . $escapedName . '"><option value="">—</option><option value="1"' . ($raw === '1' ? ' selected' : '') . '>Sim</option><option value="0"' . ($raw === '0' ? ' selected' : '') . '>Não</option></select>';
    }
    return '<input type="text" name="' . $escapedName . '" value="' . stridebr_e($valor ?? '') . '">';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/cronogramas.css')); ?>">
    <title><?php echo stridebr_e($treino['titulo']); ?> | StrideBR</title>
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content exercicios-page">
        <div class="exercise-shell">
            <div class="exercise-heading">
                <div>
                    <a class="back-link" href="/user/cronogramatreinos.php?id=<?php echo urlencode($treino['idcronograma']); ?>">← <?php echo stridebr_e($treino['cronograma_nome']); ?></a>
                    <h1><?php echo stridebr_e($treino['titulo']); ?></h1>
                    <p><?php echo stridebr_e(substr($treino['hora_inicio'], 0, 5)); ?>–<?php echo stridebr_e(substr($treino['hora_fim'], 0, 5)); ?><?php echo stridebr_db_bool($treino['termina_dia_seguinte']) ? ' · termina no dia seguinte' : ''; ?></p>
                </div>
                <a class="secondary-button" href="/user/bibliotecaexercicios.php">Abrir biblioteca</a>
            </div>

            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div>
            <?php endforeach; ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger"><?php echo stridebr_e($error); ?></div>
            <?php endforeach; ?>

            <section class="exercise-tools-card">
                <div>
                    <h2>Colunas deste treino</h2>
                    <p>Séries, repetições, carga, bloco, cluster, descanso e observações ficam sempre disponíveis. Adicione outras informações quando precisar.</p>
                </div>
                <form method="POST" class="inline-field-form">
                    <?php echo stridebr_csrf_field(); ?>
                    <input type="hidden" name="action" value="add_field">
                    <input type="hidden" name="idtreino" value="<?php echo stridebr_e($idTreino); ?>">
                    <input type="text" name="nome" maxlength="80" placeholder="Ex.: RPE" required>
                    <select name="tipo">
                        <option value="texto">Texto</option>
                        <option value="inteiro">Inteiro</option>
                        <option value="decimal">Decimal</option>
                        <option value="booleano">Sim/Não</option>
                    </select>
                    <button type="submit" class="secondary-button">Adicionar coluna</button>
                </form>
                <?php if ($camposExtras !== []): ?>
                    <div class="custom-field-chips">
                        <?php foreach ($camposExtras as $campo): ?>
                            <form method="POST" class="field-chip" onsubmit="return confirm('Remover esta coluna do treino?');">
                                <?php echo stridebr_csrf_field(); ?>
                                <input type="hidden" name="action" value="remove_field">
                                <input type="hidden" name="idtreino" value="<?php echo stridebr_e($idTreino); ?>">
                                <input type="hidden" name="idcampo" value="<?php echo stridebr_e($campo['idcampo']); ?>">
                                <span><?php echo stridebr_e($campo['nome']); ?></span>
                                <button type="submit" aria-label="Remover <?php echo stridebr_e($campo['nome']); ?>">×</button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <form method="POST" class="exercise-editor-card" data-exercise-editor>
                <?php echo stridebr_csrf_field(); ?>
                <input type="hidden" name="action" value="save_exercises">
                <input type="hidden" name="idtreino" value="<?php echo stridebr_e($idTreino); ?>">
                <div class="exercise-table-scroll">
                    <table class="exercise-table">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Biblioteca</th>
                            <th>Exercício</th>
                            <th>Séries</th>
                            <th>Repetições</th>
                            <th>Carga</th>
                            <th>Bloco</th>
                            <th>Cluster</th>
                            <th>Descanso</th>
                            <?php foreach ($camposExtras as $campo): ?><th><?php echo stridebr_e($campo['nome']); ?></th><?php endforeach; ?>
                            <th>Observações</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody data-exercise-rows>
                        <?php foreach ($exercicios as $index => $row): ?>
                            <tr data-exercise-row>
                                <td data-row-number><?php echo $index + 1; ?></td>
                                <td>
                                    <input type="hidden" name="rows[<?php echo $index; ?>][idtreino_exercicio]" value="<?php echo stridebr_e($row['idtreino_exercicio']); ?>">
                                    <select name="rows[<?php echo $index; ?>][idexercicio]" data-library-select>
                                        <option value="">Manual</option>
                                        <?php foreach ($biblioteca as $item): ?>
                                            <option value="<?php echo stridebr_e($item['idexercicio']); ?>" data-name="<?php echo stridebr_e($item['nome']); ?>"<?php echo $row['idexercicio'] === $item['idexercicio'] ? ' selected' : ''; ?>><?php echo stridebr_e($item['nome']); ?><?php echo $item['categorias'] ? ' · ' . stridebr_e($item['categorias']) : ''; ?><?php echo $item['idusuario'] === null ? ' · StrideBR' : ''; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="text" name="rows[<?php echo $index; ?>][nome]" value="<?php echo stridebr_e($row['nome_snapshot']); ?>" data-exercise-name maxlength="120" required></td>
                                <td><input type="number" min="1" step="1" name="rows[<?php echo $index; ?>][series]" value="<?php echo stridebr_e($row['series'] ?? ''); ?>"></td>
                                <td><input type="text" name="rows[<?php echo $index; ?>][repeticoes]" maxlength="40" value="<?php echo stridebr_e($row['repeticoes'] ?? ''); ?>" placeholder="8-12"></td>
                                <td><input type="text" name="rows[<?php echo $index; ?>][carga]" maxlength="40" value="<?php echo stridebr_e($row['carga'] ?? ''); ?>" placeholder="40 kg"></td>
                                <td><input type="text" name="rows[<?php echo $index; ?>][bloco]" maxlength="40" value="<?php echo stridebr_e($row['bloco'] ?? ''); ?>" placeholder="A"></td>
                                <td><input type="text" name="rows[<?php echo $index; ?>][cluster]" maxlength="80" value="<?php echo stridebr_e($row['cluster'] ?? ''); ?>" placeholder="4+4+4"></td>
                                <td><input type="text" name="rows[<?php echo $index; ?>][descanso]" maxlength="40" value="<?php echo stridebr_e($row['descanso'] ?? ''); ?>" placeholder="90 s"></td>
                                <?php foreach ($camposExtras as $campo): ?>
                                    <td><?php echo renderExtraInput($campo, $valoresExtras[$row['idtreino_exercicio']][$campo['idcampo']] ?? null, "rows[{$index}][extras][{$campo['idcampo']}]"); ?></td>
                                <?php endforeach; ?>
                                <td><textarea name="rows[<?php echo $index; ?>][observacoes]" rows="2"><?php echo stridebr_e($row['observacoes'] ?? ''); ?></textarea></td>
                                <td><button type="button" class="remove-row-button" data-remove-exercise aria-label="Remover linha">×</button></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <template data-exercise-row-template>
                    <tr data-exercise-row>
                        <td data-row-number></td>
                        <td>
                            <input type="hidden" name="rows[__INDEX__][idtreino_exercicio]" value="">
                            <select name="rows[__INDEX__][idexercicio]" data-library-select>
                                <option value="">Manual</option>
                                <?php foreach ($biblioteca as $item): ?>
                                    <option value="<?php echo stridebr_e($item['idexercicio']); ?>" data-name="<?php echo stridebr_e($item['nome']); ?>"><?php echo stridebr_e($item['nome']); ?><?php echo $item['categorias'] ? ' · ' . stridebr_e($item['categorias']) : ''; ?><?php echo $item['idusuario'] === null ? ' · StrideBR' : ''; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="text" name="rows[__INDEX__][nome]" value="" data-exercise-name maxlength="120"></td>
                        <td><input type="number" min="1" step="1" name="rows[__INDEX__][series]"></td>
                        <td><input type="text" name="rows[__INDEX__][repeticoes]" maxlength="40" placeholder="8-12"></td>
                        <td><input type="text" name="rows[__INDEX__][carga]" maxlength="40" placeholder="40 kg"></td>
                        <td><input type="text" name="rows[__INDEX__][bloco]" maxlength="40" placeholder="A"></td>
                        <td><input type="text" name="rows[__INDEX__][cluster]" maxlength="80" placeholder="4+4+4"></td>
                        <td><input type="text" name="rows[__INDEX__][descanso]" maxlength="40" placeholder="90 s"></td>
                        <?php foreach ($camposExtras as $campo): ?><td><?php echo renderExtraInput($campo, null, "rows[__INDEX__][extras][{$campo['idcampo']}]"); ?></td><?php endforeach; ?>
                        <td><textarea name="rows[__INDEX__][observacoes]" rows="2"></textarea></td>
                        <td><button type="button" class="remove-row-button" data-remove-exercise aria-label="Remover linha">×</button></td>
                    </tr>
                </template>
                <div class="exercise-editor-actions">
                    <button type="button" class="secondary-button" data-add-exercise>Adicionar exercício</button>
                    <button type="submit" class="primary-button">Salvar alterações</button>
                </div>
            </form>

            <?php if ($exercicios !== [] && $destinos !== []): ?>
                <section class="exercise-copy-card">
                    <h2>Copiar para outro treino</h2>
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
                        <button type="submit" class="secondary-button">Copiar exercício</button>
                    </form>
                </section>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/cronogramas.js')); ?>"></script>
</body>
</html>
