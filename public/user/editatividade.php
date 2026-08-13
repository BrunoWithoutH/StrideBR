<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();

require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';

date_default_timezone_set('America/Sao_Paulo');
$idRegistro = (string) ($_GET['id'] ?? $_POST['id'] ?? '');
$registro = atividadeCarregarRegistro($pdo, $idRegistro, $idUsuario);

if ($registro === []) {
    http_response_code(404);
    exit('Atividade não encontrada.');
}

$errors = [];
$camposAgrupados = atividadeAgruparCampos($registro['campos']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    try {
        atividadeSalvarRegistro($pdo, $idUsuario, [
            'idmodelo' => $registro['idmodelo'],
            'titulo' => $_POST['titulo'] ?? '',
            'observacoes' => $_POST['observacoes'] ?? '',
            'data_inicio' => trim((string) ($_POST['data'] ?? '')) . ' ' . trim((string) ($_POST['hora'] ?? '')),
            'status' => $_POST['status'] ?? 'concluido',
            'visibilidade' => $_POST['visibilidade'] ?? 'privado',
            'record_values' => is_array($_POST['record_values'] ?? null) ? $_POST['record_values'] : [],
            'unidades' => is_array($_POST['unidades'] ?? null) ? $_POST['unidades'] : [],
        ], $idRegistro);
        stridebr_flash('success', 'Atividade atualizada.');
        header('Location: /user/atividades.php');
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Não foi possível atualizar a atividade.';
        if (!$e instanceof InvalidArgumentException) {
            error_log($e->getMessage());
        }
        $registro = atividadeCarregarRegistro($pdo, $idRegistro, $idUsuario);
        $camposAgrupados = atividadeAgruparCampos($registro['campos']);
    }
}

$inicio = new DateTimeImmutable($registro['data_inicio']);
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
    <link rel="stylesheet" href="/assets/css/atividades.css">
    <title>Editar atividade | StrideBR</title>
</head>
<body>
    <div class="container-fluid">
        <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
        <main class="main-content activities-page">
            <div class="activity-heading">
                <div>
                    <a href="/user/atividades.php">← Voltar</a>
                    <h1>Editar atividade</h1>
                    <p><?php echo stridebr_e($registro['modalidade_nome']); ?> · <?php echo stridebr_e($registro['modelo_nome']); ?></p>
                </div>
            </div>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger"><?php echo stridebr_e($error); ?></div>
            <?php endforeach; ?>
            <form method="POST" class="AtividadeForm activity-edit-form">
                <?php echo stridebr_csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo stridebr_e($idRegistro); ?>">
                <div class="activity-form-grid">
                    <div class="input-field">
                        <label for="titulo">Título</label>
                        <input type="text" id="titulo" name="titulo" value="<?php echo stridebr_e($registro['titulo']); ?>" maxlength="255">
                    </div>
                    <div class="input-field">
                        <label for="data">Data</label>
                        <input type="date" id="data" name="data" value="<?php echo $inicio->format('Y-m-d'); ?>" required>
                    </div>
                    <div class="input-field">
                        <label for="hora">Hora</label>
                        <input type="time" id="hora" name="hora" value="<?php echo $inicio->format('H:i'); ?>" required>
                    </div>
                    <div class="input-field">
                        <label for="visibilidade">Visibilidade</label>
                        <select id="visibilidade" name="visibilidade">
                            <?php foreach (['privado' => 'Privado', 'amigos' => 'Amigos', 'publico' => 'Público'] as $value => $label): ?>
                                <option value="<?php echo $value; ?>"<?php echo $registro['visibilidade'] === $value ? ' selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-field">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <?php foreach (['rascunho' => 'Rascunho', 'ativo' => 'Em andamento', 'concluido' => 'Concluído', 'cancelado' => 'Cancelado'] as $value => $label): ?>
                                <option value="<?php echo $value; ?>"<?php echo $registro['status'] === $value ? ' selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <?php if ($camposAgrupados['registro']): ?>
                    <section class="activity-record-fields">
                        <h2>Informações da atividade</h2>
                        <div class="activity-form-grid">
                            <?php foreach ($camposAgrupados['registro'] as $campo): ?>
                                <?php echo atividadeRenderizarCampo($campo, "record_values[{$campo['idcampo']}]", "record_{$campo['idcampo']}", $registro['record_values'][$campo['idcampo']] ?? null); ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <div class="activity-units" data-units data-model="edit">
                    <?php foreach ($registro['unidades'] as $index => $unidade): ?>
                        <div class="activity-unit" data-unit-index="<?php echo $index; ?>">
                            <div class="activity-unit-header">
                                <h3><?php echo stridebr_e($registro['rotulo_unidade']); ?> <?php echo $index + 1; ?></h3>
                                <?php if ($registro['permite_multiplas_unidades']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-remove-unit<?php echo count($registro['unidades']) === 1 ? ' hidden' : ''; ?>>Remover</button>
                                <?php endif; ?>
                            </div>
                            <div class="activity-form-grid">
                                <div class="input-field">
                                    <label>Rótulo opcional</label>
                                    <input type="text" name="unidades[<?php echo $index; ?>][rotulo]" maxlength="120" value="<?php echo stridebr_e($unidade['rotulo'] ?? ''); ?>">
                                </div>
                                <?php foreach ($camposAgrupados['unidade'] as $campo): ?>
                                    <?php echo atividadeRenderizarCampo($campo, "unidades[{$index}][values][{$campo['idcampo']}]", "unit_{$index}_{$campo['idcampo']}", $unidade['values'][$campo['idcampo']] ?? null); ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($registro['permite_multiplas_unidades']): ?>
                    <template data-unit-template="edit">
                        <div class="activity-unit" data-unit-index="__INDEX__">
                            <div class="activity-unit-header">
                                <h3><?php echo stridebr_e($registro['rotulo_unidade']); ?> __NUMBER__</h3>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-remove-unit>Remover</button>
                            </div>
                            <div class="activity-form-grid">
                                <div class="input-field">
                                    <label>Rótulo opcional</label>
                                    <input type="text" name="unidades[__INDEX__][rotulo]" maxlength="120">
                                </div>
                                <?php foreach ($camposAgrupados['unidade'] as $campo): ?>
                                    <?php echo atividadeRenderizarCampo($campo, "unidades[__INDEX__][values][{$campo['idcampo']}]", "unit___INDEX___{$campo['idcampo']}"); ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </template>
                    <button type="button" class="btn btn-outline-secondary add-unit-button" data-add-unit="edit">+ Adicionar <?php echo stridebr_lower(stridebr_e($registro['rotulo_unidade'])); ?></button>
                <?php endif; ?>

                <div class="input-field">
                    <label for="observacoes">Observações gerais</label>
                    <textarea id="observacoes" name="observacoes" rows="3"><?php echo stridebr_e($registro['observacoes']); ?></textarea>
                </div>
                <div class="activity-form-actions">
                    <a class="btn btn-light" href="/user/atividades.php">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Salvar alterações</button>
                </div>
            </form>
        </main>
    </div>
    <?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
    <script src="/assets/js/atividades.js"></script>
</body>
</html>
