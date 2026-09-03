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
        stridebr_flash('success', 'Exercícios do treino salvo foram atualizados.');
        header('Location: ' . $returnTo);
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível salvar os exercícios.';
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
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
    <title><?php echo stridebr_e((string) $modelo['titulo']); ?> | StrideBR</title>
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content model-exercise-page">
        <div class="draft-exercise-heading"><div><span class="eyebrow">Meus treinos</span><h1><?php echo stridebr_e((string) $modelo['titulo']); ?></h1><p>Edite os exercícios desta versão reutilizável. Novos agendamentos usarão esta estrutura.</p></div><div class="draft-exercise-heading-actions"><a class="secondary-button" href="/user/biblioteca.php?tab=treinos&edit=<?php echo rawurlencode($idModelo); ?>#editar-treino">Editar geral</a><a class="secondary-button" href="<?php echo stridebr_e($returnTo); ?>">Voltar</a></div></div>
        <?php if ($errors !== []): ?><div class="alert error"><?php echo stridebr_e(implode(' ', $errors)); ?></div><?php endif; ?>
        <form method="POST" class="model-exercise-editor" data-model-exercise-editor>
            <?php echo stridebr_csrf_field(); ?>
            <input type="hidden" name="idtreino_modelo" value="<?php echo stridebr_e($idModelo); ?>">
            <input type="hidden" name="return_to" value="<?php echo stridebr_e($returnTo); ?>">
            <div class="draft-exercise-list" data-model-exercise-list>
                <?php foreach ($exercicios as $index => $row): ?>
                    <article class="draft-exercise-card" data-model-exercise-row>
                        <div class="draft-exercise-card-heading"><strong data-model-number>Exercício <?php echo $index + 1; ?></strong><button type="button" class="danger-link" data-remove-model-exercise>Remover</button></div>
                        <div class="draft-exercise-core">
                            <label>Biblioteca<select name="rows[<?php echo $index; ?>][idexercicio]" data-library-select><option value="">Manual</option><?php foreach ($biblioteca as $item): ?><option value="<?php echo stridebr_e((string) $item['idexercicio']); ?>" data-name="<?php echo stridebr_e((string) $item['nome']); ?>"<?php echo (string) ($row['idexercicio'] ?? '') === (string) $item['idexercicio'] ? ' selected' : ''; ?>><?php echo stridebr_e((string) $item['nome']); ?></option><?php endforeach; ?></select></label>
                            <label class="draft-exercise-name">Exercício<input type="text" name="rows[<?php echo $index; ?>][nome]" value="<?php echo stridebr_e((string) ($row['nome_snapshot'] ?? '')); ?>" data-exercise-name maxlength="120" required></label>
                            <label>Séries<input type="number" name="rows[<?php echo $index; ?>][series]" value="<?php echo stridebr_e((string) ($row['series'] ?? '')); ?>" min="1" max="99"></label>
                            <label>Repetições<input type="text" name="rows[<?php echo $index; ?>][repeticoes]" value="<?php echo stridebr_e((string) ($row['repeticoes'] ?? '')); ?>" maxlength="40"></label>
                            <label>Carga<input type="text" name="rows[<?php echo $index; ?>][carga]" value="<?php echo stridebr_e((string) ($row['carga'] ?? '')); ?>" maxlength="40"></label>
                            <label>Descanso<input type="text" name="rows[<?php echo $index; ?>][descanso]" value="<?php echo stridebr_e((string) ($row['descanso'] ?? '')); ?>" maxlength="40"></label>
                        </div>
                        <details class="draft-exercise-more"><summary>Mais campos</summary><div class="draft-exercise-more-grid">
                            <label>Bloco<input type="text" name="rows[<?php echo $index; ?>][bloco]" value="<?php echo stridebr_e((string) ($row['bloco'] ?? '')); ?>" maxlength="40"></label>
                            <label>Cluster<input type="text" name="rows[<?php echo $index; ?>][cluster]" value="<?php echo stridebr_e((string) ($row['cluster'] ?? '')); ?>" maxlength="80"></label>
                            <label>Duração<input type="text" name="rows[<?php echo $index; ?>][duracao]" value="<?php echo stridebr_e((string) ($row['duracao'] ?? '')); ?>" maxlength="40"></label>
                            <label>Distância<input type="text" name="rows[<?php echo $index; ?>][distancia]" value="<?php echo stridebr_e((string) ($row['distancia'] ?? '')); ?>" maxlength="40"></label>
                            <label>Intensidade<input type="text" name="rows[<?php echo $index; ?>][intensidade]" value="<?php echo stridebr_e((string) ($row['intensidade'] ?? '')); ?>" maxlength="80"></label>
                            <label>RPE<input type="number" name="rows[<?php echo $index; ?>][rpe]" value="<?php echo stridebr_e((string) ($row['rpe'] ?? '')); ?>" min="0" max="10" step="0.5"></label>
                            <label>RIR<input type="number" name="rows[<?php echo $index; ?>][rir]" value="<?php echo stridebr_e((string) ($row['rir'] ?? '')); ?>" min="0" max="10" step="0.5"></label>
                            <label>Tempo de execução<input type="text" name="rows[<?php echo $index; ?>][tempo_execucao]" value="<?php echo stridebr_e((string) ($row['tempo_execucao'] ?? '')); ?>" maxlength="40"></label>
                            <label>Cadência<input type="text" name="rows[<?php echo $index; ?>][cadencia]" value="<?php echo stridebr_e((string) ($row['cadencia'] ?? '')); ?>" maxlength="40"></label>
                            <label class="draft-exercise-notes">Observações<textarea name="rows[<?php echo $index; ?>][observacoes]" rows="2"><?php echo stridebr_e((string) ($row['observacoes'] ?? '')); ?></textarea></label>
                        </div></details>
                    </article>
                <?php endforeach; ?>
            </div>
            <template data-model-exercise-template>
                <article class="draft-exercise-card" data-model-exercise-row>
                    <div class="draft-exercise-card-heading"><strong data-model-number></strong><button type="button" class="danger-link" data-remove-model-exercise>Remover</button></div>
                    <div class="draft-exercise-core">
                        <label>Biblioteca<select name="rows[__INDEX__][idexercicio]" data-library-select><option value="">Manual</option><?php foreach ($biblioteca as $item): ?><option value="<?php echo stridebr_e((string) $item['idexercicio']); ?>" data-name="<?php echo stridebr_e((string) $item['nome']); ?>"><?php echo stridebr_e((string) $item['nome']); ?></option><?php endforeach; ?></select></label>
                        <label class="draft-exercise-name">Exercício<input type="text" name="rows[__INDEX__][nome]" data-exercise-name maxlength="120"></label>
                        <label>Séries<input type="number" name="rows[__INDEX__][series]" min="1" max="99"></label>
                        <label>Repetições<input type="text" name="rows[__INDEX__][repeticoes]" maxlength="40"></label>
                        <label>Carga<input type="text" name="rows[__INDEX__][carga]" maxlength="40"></label>
                        <label>Descanso<input type="text" name="rows[__INDEX__][descanso]" maxlength="40"></label>
                    </div>
                    <details class="draft-exercise-more"><summary>Mais campos</summary><div class="draft-exercise-more-grid">
                        <label>Bloco<input type="text" name="rows[__INDEX__][bloco]" maxlength="40"></label><label>Cluster<input type="text" name="rows[__INDEX__][cluster]" maxlength="80"></label><label>Duração<input type="text" name="rows[__INDEX__][duracao]" maxlength="40"></label><label>Distância<input type="text" name="rows[__INDEX__][distancia]" maxlength="40"></label><label>Intensidade<input type="text" name="rows[__INDEX__][intensidade]" maxlength="80"></label><label>RPE<input type="number" name="rows[__INDEX__][rpe]" min="0" max="10" step="0.5"></label><label>RIR<input type="number" name="rows[__INDEX__][rir]" min="0" max="10" step="0.5"></label><label>Tempo de execução<input type="text" name="rows[__INDEX__][tempo_execucao]" maxlength="40"></label><label>Cadência<input type="text" name="rows[__INDEX__][cadencia]" maxlength="40"></label><label class="draft-exercise-notes">Observações<textarea name="rows[__INDEX__][observacoes]" rows="2"></textarea></label>
                    </div></details>
                </article>
            </template>
            <div class="draft-exercise-footer"><button type="button" class="secondary-button" data-add-model-exercise>Adicionar exercício</button><div><a class="secondary-button" href="<?php echo stridebr_e($returnTo); ?>">Cancelar</a><button type="submit" class="primary-button">Salvar exercícios</button></div></div>
        </form>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/exercicios-modelo.js')); ?>"></script>
</body>
</html>
