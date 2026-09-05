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
        return '<select name="' . $escapedName . '"><option value="">—</option><option value="1"' . ($raw === '1' ? ' selected' : '') . '>' . stridebr_e(stridebr_t('common.yes')) . '</option><option value="0"' . ($raw === '0' ? ' selected' : '') . '>' . stridebr_e(stridebr_t('common.no')) . '</option></select>';
    }
    return '<input type="text" name="' . $escapedName . '" value="' . stridebr_e($valor ?? '') . '">';
}
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
        <div class="exercise-shell">
            <div class="exercise-heading">
                <div>
                    <a class="back-link" href="/user/cronogramatreinos.php?id=<?php echo urlencode($treino['idcronograma']); ?>">← <?php echo stridebr_e($treino['cronograma_nome']); ?></a>
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

            <section class="exercise-tools-card">
                <div>
                    <h2><?php echo stridebr_e(stridebr_t('exercise.columns_title')); ?></h2>
                    <p><?php echo stridebr_e(stridebr_t('exercise.columns_help')); ?></p>
                </div>
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
                            <th><?php echo stridebr_e(stridebr_t('library.page_title')); ?></th>
                            <th><?php echo stridebr_e(stridebr_t('home.exercise')); ?></th>
                            <th><?php echo stridebr_e(stridebr_t('common.series')); ?></th>
                            <th><?php echo stridebr_e(stridebr_t('common.repetitions')); ?></th>
                            <th><?php echo stridebr_e(stridebr_t('common.load')); ?></th>
                            <th><?php echo stridebr_e(stridebr_t('common.block')); ?></th>
                            <th><?php echo stridebr_e(stridebr_t('common.cluster')); ?></th>
                            <th><?php echo stridebr_e(stridebr_t('home.rest')); ?></th>
                            <?php foreach ($camposExtras as $campo): ?><th><?php echo stridebr_e($campo['nome']); ?></th><?php endforeach; ?>
                            <th><?php echo stridebr_e(stridebr_t('activity.notes')); ?></th>
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
                                        <option value=""><?php echo stridebr_e(stridebr_t('common.manual')); ?></option>
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
                                <td><button type="button" class="remove-row-button" data-remove-exercise aria-label="<?php echo stridebr_e(stridebr_t('exercise.remove_row')); ?>">×</button></td>
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
                                <option value=""><?php echo stridebr_e(stridebr_t('common.manual')); ?></option>
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
                        <td><button type="button" class="remove-row-button" data-remove-exercise aria-label="<?php echo stridebr_e(stridebr_t('exercise.remove_row')); ?>">×</button></td>
                    </tr>
                </template>
                <div class="exercise-editor-actions">
                    <button type="button" class="secondary-button" data-add-exercise><?php echo stridebr_e(stridebr_t('common.add_exercise')); ?></button>
                    <button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('settings.save_changes')); ?></button>
                </div>
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
</body>
</html>
