<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma.php';

$returnTo = stridebr_safe_redirect((string) ($_GET['return_to'] ?? ''), '/user/cronogramatreinos.php');
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
    <title>Editar exercícios | StrideBR</title>
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content draft-exercise-page" data-draft-exercise-page data-return-to="<?php echo stridebr_e($returnTo); ?>">
        <div class="draft-exercise-heading">
            <div><span class="eyebrow">Novo treino</span><h1>Editar exercícios</h1><p>O treino que você estava montando ficou salvo neste navegador. Salve os exercícios e você volta para o popup do calendário.</p></div>
            <a class="secondary-button" href="<?php echo stridebr_e($returnTo); ?>">Voltar</a>
        </div>
        <div class="draft-exercise-missing" data-draft-missing hidden>
            <strong>Não encontrei o rascunho deste treino.</strong>
            <p>Volte ao cronograma e abra o editor novamente.</p>
            <a class="primary-button" href="<?php echo stridebr_e($returnTo); ?>">Voltar ao cronograma</a>
        </div>
        <section class="draft-exercise-editor" data-draft-editor>
            <div class="draft-exercise-list" data-draft-exercise-list></div>
            <button type="button" class="secondary-button" data-add-draft-exercise>Adicionar exercício</button>
            <div class="draft-exercise-footer">
                <span data-draft-save-state>Alterações guardadas neste navegador</span>
                <div><a class="secondary-button" href="<?php echo stridebr_e($returnTo); ?>">Cancelar</a><button type="button" class="primary-button" data-save-draft-exercises>Salvar exercícios e voltar</button></div>
            </div>
        </section>
        <template data-draft-exercise-template>
            <article class="draft-exercise-card" data-draft-exercise-row>
                <div class="draft-exercise-card-heading"><strong data-draft-number></strong><button type="button" class="danger-link" data-remove-draft-exercise>Remover</button></div>
                <div class="draft-exercise-core">
                    <label>Biblioteca<select data-field="idexercicio"><option value="">Manual</option><?php foreach ($biblioteca as $item): ?><option value="<?php echo stridebr_e((string) $item['idexercicio']); ?>" data-name="<?php echo stridebr_e((string) $item['nome']); ?>"><?php echo stridebr_e((string) $item['nome']); ?><?php echo !empty($item['categorias']) ? ' · ' . stridebr_e((string) $item['categorias']) : ''; ?></option><?php endforeach; ?></select></label>
                    <label class="draft-exercise-name">Exercício<input type="text" data-field="nome" maxlength="120" required></label>
                    <label>Séries<input type="number" data-field="series" min="1" max="99" step="1"></label>
                    <label>Repetições<input type="text" data-field="repeticoes" maxlength="40" placeholder="8–12"></label>
                    <label>Carga<input type="text" data-field="carga" maxlength="40" placeholder="40 kg"></label>
                    <label>Descanso<input type="text" data-field="descanso" maxlength="40" placeholder="90 s"></label>
                </div>
                <details class="draft-exercise-more"><summary>Mais campos</summary><div class="draft-exercise-more-grid">
                    <label>Bloco<input type="text" data-field="bloco" maxlength="40"></label>
                    <label>Cluster<input type="text" data-field="cluster" maxlength="80"></label>
                    <label>Duração<input type="text" data-field="duracao" maxlength="40"></label>
                    <label>Distância<input type="text" data-field="distancia" maxlength="40"></label>
                    <label>Intensidade<input type="text" data-field="intensidade" maxlength="80"></label>
                    <label>RPE<input type="number" data-field="rpe" min="0" max="10" step="0.5"></label>
                    <label>RIR<input type="number" data-field="rir" min="0" max="10" step="0.5"></label>
                    <label>Tempo de execução<input type="text" data-field="tempo_execucao" maxlength="40"></label>
                    <label>Cadência<input type="text" data-field="cadencia" maxlength="40"></label>
                    <label class="draft-exercise-notes">Observações<textarea data-field="observacoes" rows="2"></textarea></label>
                </div></details>
            </article>
        </template>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/exercicios-rascunho.js')); ?>"></script>
</body>
</html>
