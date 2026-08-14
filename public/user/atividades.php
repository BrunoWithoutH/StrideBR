<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();

require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';

date_default_timezone_set('America/Sao_Paulo');
$errors = [];

$catalogo = atividadeListarCatalogo($pdo, $idUsuario);
$modelosDetalhados = [];
foreach ($catalogo as &$modalidade) {
    foreach ($modalidade['modelos'] as &$modelo) {
        $modelo['campos'] = atividadeBuscarCamposModelo($pdo, $modelo['idmodelo']);
        $modelo['campos_agrupados'] = atividadeAgruparCampos($modelo['campos']);
        $modelosDetalhados[$modelo['idmodelo']] = $modelo + [
            'idmodalidade' => $modalidade['idmodalidade'],
            'modalidade_nome' => $modalidade['nome'],
        ];
    }
    unset($modelo);
}
unset($modalidade);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $idModelo = (string) ($_POST['idmodelo'] ?? '');
    $modelPayload = $_POST['models'][$idModelo] ?? [];

    try {
        atividadeSalvarRegistro($pdo, $idUsuario, [
            'idmodelo' => $idModelo,
            'titulo' => $_POST['titulo'] ?? '',
            'observacoes' => $_POST['observacoes'] ?? '',
            'data_inicio' => trim((string) ($_POST['data'] ?? '')) . ' ' . trim((string) ($_POST['hora'] ?? '')),
            'status' => 'concluido',
            'visibilidade' => $_POST['visibilidade'] ?? 'privado',
            'record_values' => is_array($modelPayload['record_values'] ?? null) ? $modelPayload['record_values'] : [],
            'unidades' => is_array($modelPayload['unidades'] ?? null) ? $modelPayload['unidades'] : [],
        ]);
        stridebr_flash('success', 'Atividade registrada.');
        header('Location: /user/atividades.php');
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Não foi possível salvar a atividade.';
        if (!$e instanceof InvalidArgumentException) {
            error_log($e->getMessage());
        }
    }
}

$registros = atividadeListarRegistros($pdo, $idUsuario);
foreach ($registros as &$registro) {
    $registro['detalhes'] = atividadeCarregarRegistro($pdo, $registro['idregistro'], $idUsuario);
}
unset($registro);

function atividadeCardFormatarValor(array $campo, mixed $valor): string
{
    if ($valor === null || $valor === '') {
        return '';
    }

    if (($campo['tipo_campo'] ?? '') === 'booleano') {
        return stridebr_db_bool($valor) ? 'Sim' : 'Não';
    }

    if (($campo['tipo_campo'] ?? '') === 'selecao') {
        foreach ($campo['opcoes'] ?? [] as $opcao) {
            if ((string) $opcao['idopcao'] === (string) $valor) {
                return (string) $opcao['rotulo'];
            }
        }
    }

    $texto = (string) $valor;
    if (($campo['tipo_campo'] ?? '') === 'decimal' && is_numeric($texto)) {
        $texto = rtrim(rtrim(number_format((float) $texto, 3, ',', '.'), '0'), ',');
    }

    $unidade = trim((string) ($campo['unidade_simbolo'] ?? ''));
    return $unidade !== '' ? $texto . ' ' . $unidade : $texto;
}

$flashes = stridebr_take_flashes();
$firstModalidade = $catalogo[0]['idmodalidade'] ?? '';
$firstModelo = $catalogo[0]['modelos'][0]['idmodelo'] ?? '';
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
    <title>Suas Atividades | StrideBR</title>
</head>
<body>
    <div class="container-fluid">
        <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
        <main class="main-content activities-page">
            <div class="activity-heading">
                <div>
                    <h1>Suas Atividades</h1>
                    <p>Registre o que você fez. Modalidade, modelo e unidades podem mudar conforme o esporte.</p>
                </div>
                <button type="button" class="addbutton" data-toggle-activity-form>Registrar atividade</button>
            </div>

            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div>
            <?php endforeach; ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger"><?php echo stridebr_e($error); ?></div>
            <?php endforeach; ?>

            <section class="activity-form-shell<?php echo $errors ? ' is-open' : ''; ?>" data-activity-form>
                <form method="POST" class="AtividadeForm" id="activity-form">
                    <?php echo stridebr_csrf_field(); ?>
                    <span class="title">Registrar atividade</span>
                    <div class="activity-form-grid">
                        <div class="input-field">
                            <label for="titulo">Título</label>
                            <input type="text" id="titulo" name="titulo" maxlength="255" placeholder="Ex.: Corrida de terça">
                        </div>
                        <div class="input-field">
                            <label for="modalidade">Modalidade</label>
                            <select id="modalidade" name="idmodalidade" required>
                                <?php foreach ($catalogo as $modalidade): ?>
                                    <option value="<?php echo stridebr_e($modalidade['idmodalidade']); ?>"<?php echo $modalidade['idmodalidade'] === $firstModalidade ? ' selected' : ''; ?>><?php echo stridebr_e($modalidade['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="input-field">
                            <label for="modelo">Modelo</label>
                            <select id="modelo" name="idmodelo" required>
                                <?php foreach ($catalogo as $modalidade): ?>
                                    <?php foreach ($modalidade['modelos'] as $modelo): ?>
                                        <option value="<?php echo stridebr_e($modelo['idmodelo']); ?>" data-modalidade="<?php echo stridebr_e($modalidade['idmodalidade']); ?>"<?php echo $modelo['idmodelo'] === $firstModelo ? ' selected' : ''; ?>><?php echo stridebr_e($modelo['nome']); ?><?php echo $modelo['versao'] > 1 ? ' v' . (int) $modelo['versao'] : ''; ?></option>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="input-field">
                            <label for="data">Data</label>
                            <input type="date" id="data" name="data" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="input-field">
                            <label for="hora">Hora</label>
                            <input type="time" id="hora" name="hora" value="<?php echo date('H:i'); ?>" required>
                        </div>
                        <div class="input-field">
                            <label for="visibilidade">Visibilidade</label>
                            <select id="visibilidade" name="visibilidade">
                                <option value="privado">Privado</option>
                                <option value="amigos">Amigos</option>
                                <option value="publico">Público</option>
                            </select>
                        </div>
                    </div>

                    <?php foreach ($modelosDetalhados as $idModelo => $modelo): ?>
                        <section class="activity-model-panel" data-model-panel="<?php echo stridebr_e($idModelo); ?>" hidden>
                            <?php if ($modelo['campos_agrupados']['registro']): ?>
                                <div class="activity-record-fields">
                                    <h3>Informações da atividade</h3>
                                    <div class="activity-form-grid">
                                        <?php foreach ($modelo['campos_agrupados']['registro'] as $campo): ?>
                                            <?php echo atividadeRenderizarCampo($campo, "models[{$idModelo}][record_values][{$campo['idcampo']}]", "record_{$idModelo}_{$campo['idcampo']}"); ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="activity-units" data-units data-model="<?php echo stridebr_e($idModelo); ?>">
                                <div class="activity-unit" data-unit-index="0">
                                    <div class="activity-unit-header">
                                        <h3><?php echo stridebr_e($modelo['rotulo_unidade']); ?> 1</h3>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-unit hidden>Remover</button>
                                    </div>
                                    <div class="activity-form-grid">
                                        <div class="input-field">
                                            <label>Rótulo opcional</label>
                                            <input type="text" name="models[<?php echo stridebr_e($idModelo); ?>][unidades][0][rotulo]" maxlength="120" placeholder="Ex.: Tiro 1">
                                        </div>
                                        <?php foreach ($modelo['campos_agrupados']['unidade'] as $campo): ?>
                                            <?php echo atividadeRenderizarCampo($campo, "models[{$idModelo}][unidades][0][values][{$campo['idcampo']}]", "unit_{$idModelo}_0_{$campo['idcampo']}"); ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <?php if ($modelo['permite_multiplas_unidades']): ?>
                                <template data-unit-template="<?php echo stridebr_e($idModelo); ?>">
                                    <div class="activity-unit" data-unit-index="__INDEX__">
                                        <div class="activity-unit-header">
                                            <h3><?php echo stridebr_e($modelo['rotulo_unidade']); ?> __NUMBER__</h3>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-remove-unit>Remover</button>
                                        </div>
                                        <div class="activity-form-grid">
                                            <div class="input-field">
                                                <label>Rótulo opcional</label>
                                                <input type="text" name="models[<?php echo stridebr_e($idModelo); ?>][unidades][__INDEX__][rotulo]" maxlength="120" placeholder="Ex.: <?php echo stridebr_e($modelo['rotulo_unidade']); ?> __NUMBER__">
                                            </div>
                                            <?php foreach ($modelo['campos_agrupados']['unidade'] as $campo): ?>
                                                <?php echo atividadeRenderizarCampo($campo, "models[{$idModelo}][unidades][__INDEX__][values][{$campo['idcampo']}]", "unit_{$idModelo}___INDEX___{$campo['idcampo']}"); ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </template>
                                <button type="button" class="btn btn-outline-secondary add-unit-button" data-add-unit="<?php echo stridebr_e($idModelo); ?>">+ Adicionar <?php echo stridebr_lower(stridebr_e($modelo['rotulo_unidade'])); ?></button>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>

                    <div class="input-field">
                        <label for="observacoes">Observações gerais</label>
                        <textarea id="observacoes" name="observacoes" rows="3"></textarea>
                    </div>
                    <div class="activity-form-actions">
                        <button type="button" class="btn btn-light" data-close-activity-form>Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar atividade</button>
                    </div>
                </form>
            </section>

            <section class="activity-history">
                <h2>Histórico</h2>
                <?php if ($registros === []): ?>
                    <p>Você ainda não possui atividades registradas.</p>
                <?php else: ?>
                    <div class="activity-card-grid">
                        <?php foreach ($registros as $registro): ?>
                            <article class="activity-card">
                                <div class="activity-card-top">
                                    <div>
                                        <span class="activity-modality"><?php echo stridebr_e($registro['modalidade_nome']); ?></span>
                                        <h3><?php echo stridebr_e($registro['titulo'] ?: $registro['modalidade_nome']); ?></h3>
                                    </div>
                                    <span class="activity-visibility"><?php echo stridebr_e(ucfirst($registro['visibilidade'])); ?></span>
                                </div>
                                <p class="activity-card-date"><?php echo stridebr_e((new DateTimeImmutable($registro['data_inicio']))->format('d/m/Y H:i')); ?></p>

                                <?php
                                $detalhes = $registro['detalhes'] ?? [];
                                $campos = $detalhes['campos'] ?? [];
                                $camposPorId = [];
                                foreach ($campos as $campo) {
                                    $camposPorId[$campo['idcampo']] = $campo;
                                }
                                ?>

                                <?php if (!empty($detalhes['record_values'])): ?>
                                    <div class="activity-card-stats">
                                        <?php foreach ($detalhes['record_values'] as $idCampo => $valor): ?>
                                            <?php
                                            $campo = $camposPorId[$idCampo] ?? null;
                                            if ($campo === null) {
                                                continue;
                                            }
                                            $valorExibicao = atividadeCardFormatarValor($campo, $valor);
                                            if ($valorExibicao === '') {
                                                continue;
                                            }
                                            ?>
                                            <div class="activity-card-stat">
                                                <span><?php echo stridebr_e($campo['rotulo']); ?></span>
                                                <strong><?php echo stridebr_e($valorExibicao); ?></strong>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php foreach ($detalhes['unidades'] ?? [] as $indice => $unidade): ?>
                                    <?php if (!empty($unidade['values'])): ?>
                                        <div class="activity-card-unit">
                                            <?php if ((int) $registro['total_unidades'] > 1 || !empty($unidade['rotulo'])): ?>
                                                <span class="activity-card-unit-title"><?php echo stridebr_e($unidade['rotulo'] ?: (($detalhes['rotulo_unidade'] ?? 'Unidade') . ' ' . ($indice + 1))); ?></span>
                                            <?php endif; ?>
                                            <div class="activity-card-stats">
                                                <?php foreach ($unidade['values'] as $idCampo => $valor): ?>
                                                    <?php
                                                    $campo = $camposPorId[$idCampo] ?? null;
                                                    if ($campo === null) {
                                                        continue;
                                                    }
                                                    $valorExibicao = atividadeCardFormatarValor($campo, $valor);
                                                    if ($valorExibicao === '') {
                                                        continue;
                                                    }
                                                    ?>
                                                    <div class="activity-card-stat">
                                                        <span><?php echo stridebr_e($campo['rotulo']); ?></span>
                                                        <strong><?php echo stridebr_e($valorExibicao); ?></strong>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>

                                <?php if (!empty($registro['observacoes'])): ?>
                                    <p class="activity-card-notes"><?php echo nl2br(stridebr_e($registro['observacoes'])); ?></p>
                                <?php endif; ?>

                                <div class="activity-card-actions">
                                    <a class="btn btn-sm btn-outline-primary" href="/user/editatividade.php?id=<?php echo rawurlencode($registro['idregistro']); ?>">Editar</a>
                                    <form method="POST" action="/function/apagaratividade.php" onsubmit="return confirm('Excluir esta atividade?');">
                                        <?php echo stridebr_csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo stridebr_e($registro['idregistro']); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Excluir</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
    <script src="/assets/js/atividades.js"></script>
</body>
</html>
