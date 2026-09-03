<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();

require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';
require_once dirname(__DIR__, 2) . '/src/function/strength_activity.php';
require_once dirname(__DIR__, 2) . '/src/function/activity_energy.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma.php';
require_once dirname(__DIR__, 2) . '/src/includes/sport_icons.php';
require_once dirname(__DIR__, 2) . '/src/layout/activity_unit_route.php';
require_once dirname(__DIR__, 2) . '/src/layout/activity_unit_helpers.php';
require_once dirname(__DIR__, 2) . '/src/layout/sport_picker.php';
require_once dirname(__DIR__, 2) . '/src/layout/activity_strength_editor.php';

date_default_timezone_set('America/Sao_Paulo');
$embedded = (string) ($_GET['embed'] ?? $_POST['embed'] ?? '') === '1';
$idRegistro = (string) ($_GET['id'] ?? $_POST['id'] ?? '');
$registro = atividadeCarregarRegistro($pdo, $idRegistro, $idUsuario);

if ($registro === []) {
    stridebr_error_document(404);
}

$errors = [];
if ($embedded && (string) ($_GET['delete_error'] ?? '') === '1') $errors[] = 'Não foi possível apagar esta atividade.';
$equipamentos = atividadeListarEquipamentos($pdo, $idUsuario, false);
$catalogo = atividadeListarModalidadesCatalogoLeve($pdo, $idUsuario);
$editFamily = sportCatalogFamilyKey((string) ($registro['modalidade_familia_hub'] ?? ''), '', (string) ($registro['modalidade_slug'] ?? ''));
$editorStrengthFamily = $editFamily;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedModality = trim((string) ($_POST['idmodalidade'] ?? ''));
    foreach ($catalogo as $sport) {
        if ((string) ($sport['idmodalidade'] ?? '') !== $postedModality) continue;
        $editorStrengthFamily = sportCatalogFamilyKey((string) ($sport['familia_hub'] ?? ''), (string) ($sport['categoria'] ?? ''), (string) ($sport['slug'] ?? ''));
        break;
    }
}
$exerciciosBiblioteca = $editorStrengthFamily === 'strength' ? cronogramaListarExerciciosBiblioteca($pdo, $idUsuario) : [];
$strengthExercises = $editorStrengthFamily === 'strength'
    ? ($_SERVER['REQUEST_METHOD'] === 'POST' && is_array($_POST['strength_exercises'] ?? null) ? $_POST['strength_exercises'] : atividadeForcaBuscarSeries($pdo, $idUsuario, $idRegistro))
    : [];
$camposAgrupados = atividadeAgruparCampos($registro['campos']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    try {
        $idModalidade = trim((string) ($_POST['idmodalidade'] ?? $registro['idmodalidade']));
        $modeloDestino = $idModalidade === (string) $registro['idmodalidade']
            ? atividadeBuscarModelo($pdo, (string) $registro['idmodelo'], $idUsuario, false)
            : atividadeBuscarModeloPadraoModalidade($pdo, $idModalidade, $idUsuario);
        if ($modeloDestino === []) throw new InvalidArgumentException('Tipo de atividade inválido.');

        $selectedSport = null;
        foreach ($catalogo as $sport) {
            if ((string) ($sport['idmodalidade'] ?? '') === $idModalidade) {
                $selectedSport = $sport;
                break;
            }
        }
        $selectedFamily = sportCatalogFamilyKey((string) ($selectedSport['familia_hub'] ?? ''), (string) ($selectedSport['categoria'] ?? ''), (string) ($selectedSport['slug'] ?? ''));
        $recordValues = is_array($_POST['record_values'] ?? null) ? $_POST['record_values'] : [];
        $unidades = is_array($_POST['unidades'] ?? null) ? $_POST['unidades'] : [];
        $camposDestino = atividadeFiltrarCamposPorModalidade(atividadeBuscarCamposModelo($pdo, (string) $modeloDestino['idmodelo'], true), $selectedFamily, (string) ($selectedSport['slug'] ?? ''));
        if ((string) $modeloDestino['idmodelo'] !== (string) $registro['idmodelo']) {
            $mapped = atividadeRemapearValoresModelo($registro['campos'], $camposDestino, $recordValues, $unidades);
            $recordValues = $mapped['record_values'];
            $unidades = $mapped['unidades'];
        }

        $inicioRaw = trim((string) ($_POST['data'] ?? '')) . ' ' . trim((string) ($_POST['hora'] ?? ''));
        $fimRaw = str_replace('T', ' ', trim((string) ($_POST['data_fim'] ?? '')));
        $durationSource = trim((string) ($_POST['duration_source'] ?? 'end'));
        $segmentsPosted = !empty($_POST['usa_trechos']);
        $postedDurationSeconds = $segmentsPosted ? null : atividadeDuracaoPayloadSegundos($recordValues, $unidades, $camposDestino);
        $currentDurationSeconds = $segmentsPosted ? null : atividadeDuracaoPayloadSegundos(
            is_array($registro['record_values'] ?? null) ? $registro['record_values'] : [],
            is_array($registro['unidades'] ?? null) ? $registro['unidades'] : [],
            is_array($registro['campos'] ?? null) ? $registro['campos'] : []
        );
        if (!$segmentsPosted && $postedDurationSeconds !== null && $currentDurationSeconds !== null && $postedDurationSeconds !== $currentDurationSeconds) {
            $durationSource = 'duration';
        }
        $inicioReal = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $inicioRaw);
        if (!$inicioReal || $inicioReal->format('Y-m-d H:i') !== $inicioRaw) throw new InvalidArgumentException('Data ou hora da atividade inválida.');
        if (!$segmentsPosted && $durationSource === 'duration') {
            $durationSeconds = $postedDurationSeconds;
            if ($durationSeconds !== null) $fimRaw = $inicioReal->modify('+' . max(0, $durationSeconds) . ' seconds')->format('Y-m-d H:i');
        } elseif (!$segmentsPosted && $fimRaw !== '') {
            $fimReal = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $fimRaw);
            if (!$fimReal || $fimReal < $inicioReal) throw new InvalidArgumentException('O término precisa ser depois do início.');
            atividadeAplicarDuracaoCalculada($recordValues, $unidades, $camposDestino, $fimReal->getTimestamp() - $inicioReal->getTimestamp());
        }

        $strengthPayload = is_array($_POST['strength_exercises'] ?? null) ? $_POST['strength_exercises'] : [];
        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) $pdo->beginTransaction();
        try {
            atividadeSalvarRegistro($pdo, $idUsuario, [
                'idmodelo' => $modeloDestino['idmodelo'],
                'titulo' => $_POST['titulo'] ?? '',
                'observacoes' => $_POST['observacoes'] ?? '',
                'data_inicio' => $inicioRaw,
                'data_fim' => $fimRaw,
                'status' => $_POST['status'] ?? 'concluido',
                'visibilidade' => $_POST['visibilidade'] ?? '',
                'ocultar_inicio_m' => $_POST['ocultar_inicio_m'] ?? null,
                'ocultar_fim_m' => $_POST['ocultar_fim_m'] ?? null,
                'esforco_percebido' => $_POST['esforco_percebido'] ?? '',
                'equipamentos' => is_array($_POST['equipamentos'] ?? null) ? $_POST['equipamentos'] : [],
                'record_values' => $recordValues,
                'unidades' => $unidades,
                'usa_trechos' => !empty($_POST['usa_trechos']),
                'rota_coordenadas' => $_POST['rota_coordenadas'] ?? '',
            ], $idRegistro);
            atividadeForcaPersistirSeriesManuais($pdo, $idUsuario, $idRegistro, $selectedFamily === 'strength' ? $strengthPayload : []);
            if ($selectedFamily === 'strength' && stridebr_db_column_exists($pdo, 'registros_atividade', 'calorias_ativas_estimadas')) {
                $pdo->exec('SAVEPOINT stridebr_strength_energy_edit');
                try {
                    atividadeEnergiaAtualizarRegistro($pdo, $idRegistro, $idUsuario);
                    $pdo->exec('RELEASE SAVEPOINT stridebr_strength_energy_edit');
                } catch (Throwable $energyError) {
                    $pdo->exec('ROLLBACK TO SAVEPOINT stridebr_strength_energy_edit');
                    error_log('StrideBR strength energy after edit: ' . $energyError->getMessage());
                }
            }
            if ($startedTransaction) $pdo->commit();
        } catch (Throwable $saveError) {
            if ($startedTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $saveError;
        }
        if ($embedded) {
            header('Content-Type: text/html; charset=utf-8');
            $message = json_encode(['type' => 'stridebr:activity-edit-saved', 'id' => $idRegistro], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            echo '<!doctype html><html><body><script>window.parent.postMessage(' . $message . ', window.location.origin);</script></body></html>';
            exit;
        }
        stridebr_flash('success', 'Atividade física atualizada.');
        header('Location: /user/atividades.php?highlight=' . rawurlencode($idRegistro));
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Não foi possível atualizar a atividade.';
        if (!$e instanceof InvalidArgumentException) {
            error_log($e->getMessage());
        }
        $registro = atividadeCarregarRegistro($pdo, $idRegistro, $idUsuario);
        $equipamentos = atividadeListarEquipamentos($pdo, $idUsuario, false);
        $camposAgrupados = atividadeAgruparCampos($registro['campos']);
        $strengthExercises = is_array($_POST['strength_exercises'] ?? null) ? $_POST['strength_exercises'] : atividadeForcaBuscarSeries($pdo, $idUsuario, $idRegistro);
    }
}

function atividadeEditCampoOpcional(array $campo): bool
{
    return empty($campo['obrigatorio']) && !stridebr_db_bool($campo['exibicao_padrao'] ?? true);
}

function atividadeEditCampoOrdemVisual(array $campo): int
{
    return match (stridebr_lower((string) ($campo['slug'] ?? ''))) {
        'distancia' => 10,
        'duracao' => 20,
        'ritmo', 'pace', 'velocidade' => 30,
        'elevacao', 'desnivel' => 40,
        default => 100 + (int) ($campo['ordem'] ?? 0),
    };
}

$inicio = new DateTimeImmutable($registro['data_inicio']);
$fim = !empty($registro['data_fim']) ? new DateTimeImmutable((string) $registro['data_fim']) : null;
$unitFields = atividadeFiltrarCamposPorModalidade($camposAgrupados['unidade'], $editFamily, (string) ($registro['modalidade_slug'] ?? ''));
usort($unitFields, static fn(array $a, array $b): int => atividadeEditCampoOrdemVisual($a) <=> atividadeEditCampoOrdemVisual($b));
$recordFieldsRaw = atividadeFiltrarCamposPorModalidade($camposAgrupados['registro'], $editFamily, (string) ($registro['modalidade_slug'] ?? ''));
foreach ($recordFieldsRaw as $campo) {
    if (stridebr_lower((string) ($campo['slug'] ?? '')) !== 'observacoes') continue;
    $legacyNote = trim((string) ($registro['record_values'][(string) ($campo['idcampo'] ?? '')] ?? ''));
    $mainNote = trim((string) ($registro['observacoes'] ?? ''));
    if ($legacyNote !== '' && !str_contains($mainNote, $legacyNote)) {
        $registro['observacoes'] = trim($mainNote . ($mainNote !== '' ? "
" : '') . $legacyNote);
    }
}
$recordFields = array_values(array_filter($recordFieldsRaw, static fn(array $campo): bool => stridebr_lower((string) ($campo['slug'] ?? '')) !== 'observacoes'));
$primaryUnit = $registro['unidades'][0] ?? ['rotulo' => '', 'values' => []];
$extraUnits = array_slice($registro['unidades'], 1);
$segmentsActive = $_SERVER['REQUEST_METHOD'] === 'POST' ? !empty($_POST['usa_trechos']) : (!empty($registro['usa_trechos']) || count($registro['unidades'] ?? []) > 1);
$attemptMode = (string) ($registro['tipo_unidade_padrao'] ?? '') === 'tentativa';
$primarySport = trim((string) ($primaryUnit['idmodalidade'] ?? '')) ?: (string) $registro['idmodalidade'];
$primaryDerivedType = atividadeMetricaDerivadaModalidadeCatalogo($catalogo, $primarySport, (string) ($registro['metrica_derivada'] ?? 'nenhuma'));
$optionalFields = [];
foreach (array_merge($unitFields, $recordFields) as $field) {
    if (atividadeEditCampoOpcional($field)) {
        $optionalFields[] = $field;
    }
}
$selectedEquipment = array_fill_keys(array_column($registro['equipamentos'] ?? [], 'idequipamento'), true);
$catalogoEditFavoritas = array_values(array_filter($catalogo, static fn(array $item): bool => !empty($item['favorita'])));
$catalogoEditRecentes = array_values(array_filter($catalogo, static fn(array $item): bool => empty($item['favorita']) && !empty($item['ultimo_uso'])));
usort($catalogoEditRecentes, static fn(array $a, array $b): int => strcmp((string) $b['ultimo_uso'], (string) $a['ultimo_uso']));
$catalogoEditRecentes = array_slice($catalogoEditRecentes, 0, 5);
$distanceUnit = '';
foreach ($unitFields as $field) {
    if (($field['slug'] ?? '') === 'distancia') {
        $distanceUnit = (string) ($field['unidade_simbolo'] ?? '');
        break;
    }
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
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/atividades.css')); ?>">
    <title>Editar atividade física | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body class="<?php echo $embedded ? 'activity-edit-embedded' : ''; ?>">
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content activities-page">
        <header class="activities-toolbar">
            <div class="activities-toolbar-title activity-edit-toolbar-title">
                <a class="activity-back-link" href="/user/atividades.php">← Atividades físicas</a>
                <h1>Editar atividade física</h1>
            </div>
            <div class="activities-toolbar-actions">
                <a href="/user/equipamentos.php" class="activity-toolbar-link">Equipamentos</a>
            </div>
        </header>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger activity-alert"><?php echo stridebr_e($error); ?></div>
        <?php endforeach; ?>

        <form method="POST" class="activity-editor activity-edit-form" id="activity-form" autocomplete="off">
            <?php echo stridebr_csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo stridebr_e($idRegistro); ?>">
            <?php if ($embedded): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
            <input type="hidden" name="duration_source" value="end" data-duration-source>

            <div class="activity-editor-heading">
                <div>
                    <h2><?php echo stridebr_e($registro['titulo'] ?: $registro['modalidade_nome']); ?></h2>
                    <p class="activity-editor-kind"><span class="activity-editor-kind-icon"><?php echo stridebr_sport_icon_html((string) $registro['modalidade_slug']); ?></span><span><?php echo stridebr_e($registro['modalidade_nome']); ?></span><span aria-hidden="true">·</span><span><?php echo stridebr_e($registro['modelo_nome']); ?></span></p>
                </div>
            </div>

            <div class="activity-context-grid activity-edit-context-grid">
                <div class="input-field activity-sport-field activity-edit-sport-field">
                    <label for="activity-edit-sport-search">Esporte / atividade física</label>
                    <div class="sport-combobox" data-sport-combobox>
                        <button type="button" class="sport-combobox-trigger" data-sport-trigger aria-haspopup="listbox" aria-expanded="false"><span data-sport-current class="sport-current">Escolher esporte</span><span aria-hidden="true">⌄</span></button>
                        <div class="sport-combobox-popover" data-sport-popover hidden>
                            <div class="sport-search-row"><input type="search" id="activity-edit-sport-search" placeholder="Buscar esporte..." data-sport-search spellcheck="false"></div>
                            <div class="sport-options" role="listbox" data-sport-options>
                                <?php if ($catalogoEditFavoritas): ?>
                                    <div class="sport-favorites-quick" data-sport-quick aria-label="Esportes favoritos"><div class="sport-group-title">Favoritos</div><div class="sport-favorites-chips">
                                        <?php foreach ($catalogoEditFavoritas as $modalidade): ?><button type="button" class="sport-option sport-favorite-quick" data-sport-option data-sport-id="<?php echo stridebr_e((string) $modalidade['idmodalidade']); ?>" data-sport-name="<?php echo stridebr_e((string) $modalidade['nome']); ?>" data-sport-slug="<?php echo stridebr_e((string) $modalidade['slug']); ?>" data-sport-family="<?php echo stridebr_e(sportCatalogFamilyKey((string) ($modalidade['familia_hub'] ?? ''), (string) ($modalidade['categoria'] ?? ''), (string) ($modalidade['slug'] ?? ''))); ?>"><span class="sport-option-icon"><?php echo stridebr_sport_icon_html((string) $modalidade['slug']); ?></span><span><?php echo stridebr_e((string) $modalidade['nome']); ?></span></button><?php endforeach; ?>
                                    </div></div>
                                <?php endif; ?>
                                <?php if ($catalogoEditRecentes): ?>
                                    <div class="sport-favorites-quick" data-sport-quick aria-label="Esportes usados recentemente"><div class="sport-group-title">Recentes</div><div class="sport-favorites-chips">
                                        <?php foreach ($catalogoEditRecentes as $modalidade): ?><button type="button" class="sport-option sport-favorite-quick" data-sport-option data-sport-id="<?php echo stridebr_e((string) $modalidade['idmodalidade']); ?>" data-sport-name="<?php echo stridebr_e((string) $modalidade['nome']); ?>" data-sport-slug="<?php echo stridebr_e((string) $modalidade['slug']); ?>" data-sport-family="<?php echo stridebr_e(sportCatalogFamilyKey((string) ($modalidade['familia_hub'] ?? ''), (string) ($modalidade['categoria'] ?? ''), (string) ($modalidade['slug'] ?? ''))); ?>"><span class="sport-option-icon"><?php echo stridebr_sport_icon_html((string) $modalidade['slug']); ?></span><span><?php echo stridebr_e((string) $modalidade['nome']); ?></span></button><?php endforeach; ?>
                                    </div></div>
                                <?php endif; ?>
                                <?php echo sportPickerRenderFamilyBrowser($catalogo, (string) $registro['idmodalidade'], false); ?>
                            </div>
                        </div>
                        <select id="modalidade" name="idmodalidade" class="activity-native-select" tabindex="-1" aria-hidden="true">
                            <?php foreach ($catalogo as $atividadeCatalogo): ?><option value="<?php echo stridebr_e((string) $atividadeCatalogo['idmodalidade']); ?>" data-slug="<?php echo stridebr_e((string) ($atividadeCatalogo['slug'] ?? '')); ?>" data-family="<?php echo stridebr_e(sportCatalogFamilyKey((string) ($atividadeCatalogo['familia_hub'] ?? ''), (string) ($atividadeCatalogo['categoria'] ?? ''), (string) ($atividadeCatalogo['slug'] ?? ''))); ?>" data-permite-rota="<?php echo !empty($atividadeCatalogo['permite_rota']) ? '1' : '0'; ?>"<?php echo (string) $registro['idmodalidade'] === (string) $atividadeCatalogo['idmodalidade'] ? ' selected' : ''; ?>><?php echo stridebr_e((string) $atividadeCatalogo['nome']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <small class="activity-field-help">Ao trocar o tipo, dados compatíveis como duração e intensidade são preservados.</small>
                </div>
                <div class="input-field activity-edit-date-field">
                    <label for="data">Data</label>
                    <input type="date" id="data" name="data" value="<?php echo $inicio->format('Y-m-d'); ?>" required>
                </div>
                <div class="input-field activity-edit-time-field">
                    <label for="hora_h">Hora</label>
                    <div class="clock-segments" data-clock-field>
                        <input type="text" id="hora_h" inputmode="numeric" maxlength="2" value="<?php echo $inicio->format('H'); ?>" data-clock-hours aria-label="Horas">
                        <span aria-hidden="true">:</span>
                        <input type="text" inputmode="numeric" maxlength="2" value="<?php echo $inicio->format('i'); ?>" data-clock-minutes aria-label="Minutos">
                        <button type="button" class="time-now-button" data-time-now>Agora</button>
                        <button type="button" class="time-quick-toggle" data-time-quick-toggle aria-label="Abrir horários rápidos">⌄</button>
                        <div class="time-quick-menu" data-time-quick-menu hidden></div>
                        <input type="hidden" id="hora" name="hora" value="<?php echo $inicio->format('H:i'); ?>" data-clock-value>
                    </div>
                </div>
                <div class="input-field activity-edit-end-field">
                    <label>Término</label>
                    <div class="activity-end-control">
                        <input type="date" value="<?php echo $fim ? stridebr_e($fim->format('Y-m-d')) : ''; ?>" data-end-date aria-label="Data de término">
                        <div class="time24-control" data-time24><div class="time24-input-row"><input type="text" inputmode="numeric" maxlength="2" data-time24-hours aria-label="Hora de término"><span class="time24-separator">:</span><input type="text" inputmode="numeric" maxlength="2" data-time24-minutes aria-label="Minutos de término"><button type="button" class="time24-toggle" data-time24-toggle aria-label="Escolher horário">⌄</button></div><div class="time24-menu" data-time24-menu hidden><div class="time24-menu-head"><span>Horário · 24 h</span><button type="button" data-time24-now>Agora</button></div><div class="time24-hours-grid" data-time24-hours-grid></div><div class="time24-minutes-grid" data-time24-minutes-grid></div></div><input type="hidden" value="<?php echo $fim ? stridebr_e($fim->format('H:i')) : ''; ?>" data-time24-value data-end-time></div>
                        <input type="hidden" id="data_fim" name="data_fim" value="<?php echo $fim ? stridebr_e($fim->format('Y-m-d\TH:i')) : ''; ?>" data-end-datetime>
                    </div>
                    <small class="activity-field-help">Edite o término ou a duração. O último campo alterado é o que vale.</small>
                </div>
                <div class="input-field activity-edit-status-field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <?php foreach (['rascunho' => 'Rascunho', 'ativo' => 'Em andamento', 'concluido' => 'Concluído', 'cancelado' => 'Cancelado'] as $value => $label): ?>
                            <option value="<?php echo $value; ?>"<?php echo $registro['status'] === $value ? ' selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <section
                class="activity-model-panel<?php echo $segmentsActive ? ' is-segmented' : ''; ?>"
                data-model-panel="edit"
                data-derived-type="<?php echo stridebr_e($registro['metrica_derivada'] ?? 'nenhuma'); ?>"
                data-distance-unit="<?php echo stridebr_e($distanceUnit); ?>"
                data-main-modality="<?php echo stridebr_e((string) $registro['idmodalidade']); ?>"
                data-unit-kind="<?php echo stridebr_e((string) ($registro['tipo_unidade_padrao'] ?? 'unidade')); ?>"
                data-segments-suggested="<?php echo !empty($registro['permite_multiplas_unidades']) ? '1' : '0'; ?>"
            >
                <input type="hidden" name="usa_trechos" value="<?php echo $segmentsActive ? '1' : '0'; ?>" data-segment-mode-input>

                <div class="activity-segments-entry<?php echo !empty($registro['permite_multiplas_unidades']) ? ' is-suggested' : ' is-subtle'; ?>" data-segments-entry>
                    <div><strong><?php echo $attemptMode ? 'Tentativas' : 'Trechos'; ?></strong><span><?php echo $attemptMode ? 'Registre cada salto, arremesso ou lançamento.' : (!empty($registro['permite_multiplas_unidades']) ? 'Separe tiros, voltas, etapas ou partes da sessão.' : 'Opcional para registrar etapas diferentes da atividade.'); ?></span></div>
                    <button type="button" class="activity-secondary-button" data-enable-segments><?php echo $attemptMode ? ($segmentsActive ? 'Tentativas ativas' : '+ Usar tentativas') : ($segmentsActive ? 'Trechos ativos' : '+ Usar trechos'); ?></button>
                </div>

                <div class="activity-primary-unit-card" data-primary-unit-card>
                    <div class="activity-unit-header activity-primary-unit-header" data-primary-unit-header<?php echo $segmentsActive ? '' : ' hidden'; ?>>
                        <div><strong data-primary-unit-title><?php echo stridebr_e($registro['rotulo_unidade']); ?> 1</strong><span><?php echo $attemptMode ? 'Primeira tentativa' : 'Primeira parte da sessão'; ?></span></div>
                        <button type="button" class="activity-link-danger" data-close-segments><?php echo $attemptMode ? 'Fechar tentativas' : 'Fechar trechos'; ?></button>
                    </div>
                    <?php if (!$attemptMode): ?>
                        <div class="activity-unit-context" data-primary-unit-context<?php echo $segmentsActive ? '' : ' hidden'; ?>>
                            <?php echo atividadeRenderizarSeletorModalidadeUnidade('unidades[0][idmodalidade]', $catalogo, $primarySport, (string) $registro['idmodalidade']); ?>
                            <div class="input-field"><label>Nome do trecho <span class="field-hint">opcional</span></label><input type="text" name="unidades[0][rotulo]" maxlength="120" value="<?php echo stridebr_e((string) ($primaryUnit['rotulo'] ?? '')); ?>" placeholder="Ex.: Aquecimento"></div>
                        </div>
                        <div data-primary-segment-core<?php echo $segmentsActive ? '' : ' hidden'; ?>>
                            <?php echo atividadeRenderizarMetricasCanonicasTrecho('unidades[0]', $primaryUnit, $primaryDerivedType, $unitFields); ?>
                        </div>
                    <?php endif; ?>
                    <div class="activity-metrics-strip" data-primary-unit data-model-unit-fields>
                        <?php foreach ($unitFields as $campo): ?>
                            <?php echo atividadeRenderizarCampo($campo, "unidades[0][values][{$campo['idcampo']}]", "edit_unit_0_{$campo['idcampo']}", $primaryUnit['values'][$campo['idcampo']] ?? null); ?>
                        <?php endforeach; ?>

                            <div class="input-field derived-metric-field" data-derived-field>
                                <label data-derived-label>Ritmo</label>
                                <div class="derived-input-wrap">
                                    <input type="text" inputmode="decimal" data-derived-input placeholder="--:--">
                                    <span data-derived-unit>/km</span>
                                    <span class="auto-badge" data-derived-badge>AUTO</span>
                                </div>
                            </div>
                    </div>
                    <?php if (!$attemptMode): ?>
                        <div data-primary-unit-route-wrap<?php echo $segmentsActive ? '' : ' hidden'; ?>>
                            <?php echo atividadeRenderizarRotaUnidade('unidades[0]', $registro['rotulo_unidade'] . ' 1', $_POST['unidades'][0]['rota_coordenadas'] ?? ($primaryUnit['rota']['coordenadas'] ?? '')); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($recordFields): ?>
                    <div class="activity-record-fields">
                        <?php foreach ($recordFields as $campo): ?>
                            <?php echo atividadeRenderizarCampo($campo, "record_values[{$campo['idcampo']}]", "edit_record_{$campo['idcampo']}", $registro['record_values'][$campo['idcampo']] ?? null); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($optionalFields): ?>
                    <div class="optional-fields-bar" data-optional-fields-bar>
                        <span>Adicionar dado</span>
                        <?php foreach ($optionalFields as $campo): ?>
                            <button type="button" class="optional-field-chip" data-show-optional-field="<?php echo stridebr_e($campo['slug']); ?>">+ <?php echo stridebr_e($campo['rotulo']); ?></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="activity-segments-workspace" data-segments-workspace<?php echo $segmentsActive ? '' : ' hidden'; ?>>
                    <div class="activity-extra-units" data-units data-model="edit">
                        <?php foreach ($extraUnits as $offset => $unidade): $index = $offset + 1; $unitSport = trim((string) ($unidade['idmodalidade'] ?? '')) ?: (string) $registro['idmodalidade']; $unitDerivedType = atividadeMetricaDerivadaModalidadeCatalogo($catalogo, $unitSport, (string) ($registro['metrica_derivada'] ?? 'nenhuma')); ?>
                            <div class="activity-unit" data-unit-index="<?php echo $index; ?>">
                                <div class="activity-unit-header"><div><strong data-unit-title><?php echo stridebr_e($registro['rotulo_unidade']); ?> <?php echo $index + 1; ?></strong><span><?php echo $attemptMode ? 'Tentativa registrada' : 'Parte da sessão'; ?></span></div><button type="button" class="activity-link-danger" data-remove-unit>Remover</button></div>
                                <?php if (!$attemptMode): ?>
                                    <div class="activity-unit-context">
                                        <?php echo atividadeRenderizarSeletorModalidadeUnidade("unidades[{$index}][idmodalidade]", $catalogo, $unitSport, (string) $registro['idmodalidade']); ?>
                                        <div class="input-field"><label>Nome do trecho <span class="field-hint">opcional</span></label><input type="text" name="unidades[<?php echo $index; ?>][rotulo]" maxlength="120" value="<?php echo stridebr_e((string) ($unidade['rotulo'] ?? '')); ?>" placeholder="Ex.: Tiro forte"></div>
                                    </div>
                                    <?php echo atividadeRenderizarMetricasCanonicasTrecho("unidades[{$index}]", $unidade, $unitDerivedType, $unitFields); ?>
                                <?php endif; ?>
                                <div class="activity-unit-grid" data-model-unit-fields>
                                    <?php foreach ($unitFields as $campo): ?><?php echo atividadeRenderizarCampo($campo, "unidades[{$index}][values][{$campo['idcampo']}]", "edit_unit_{$index}_{$campo['idcampo']}", $unidade['values'][$campo['idcampo']] ?? null); ?><?php endforeach; ?>
                                </div>
                                <?php if (!$attemptMode) echo atividadeRenderizarRotaUnidade("unidades[{$index}]", $registro['rotulo_unidade'] . ' ' . ($index + 1), $_POST['unidades'][$index]['rota_coordenadas'] ?? ($unidade['rota']['coordenadas'] ?? '')); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <template data-unit-template="edit">
                        <div class="activity-unit" data-unit-index="__INDEX__">
                            <div class="activity-unit-header"><div><strong data-unit-title><?php echo stridebr_e($registro['rotulo_unidade']); ?> __NUMBER__</strong><span><?php echo $attemptMode ? 'Tentativa registrada' : 'Parte da sessão'; ?></span></div><button type="button" class="activity-link-danger" data-remove-unit>Remover</button></div>
                            <?php if (!$attemptMode): ?>
                                <div class="activity-unit-context">
                                    <?php echo atividadeRenderizarSeletorModalidadeUnidade('unidades[__INDEX__][idmodalidade]', $catalogo, (string) $registro['idmodalidade'], (string) $registro['idmodalidade']); ?>
                                    <div class="input-field"><label>Nome do trecho <span class="field-hint">opcional</span></label><input type="text" name="unidades[__INDEX__][rotulo]" maxlength="120" placeholder="Ex.: <?php echo stridebr_e($registro['rotulo_unidade']); ?> __NUMBER__"></div>
                                </div>
                                <?php echo atividadeRenderizarMetricasCanonicasTrecho('unidades[__INDEX__]', [], (string) ($registro['metrica_derivada'] ?? 'nenhuma'), $unitFields); ?>
                            <?php endif; ?>
                            <div class="activity-unit-grid" data-model-unit-fields>
                                <?php foreach ($unitFields as $campo): ?><?php echo atividadeRenderizarCampo($campo, "unidades[__INDEX__][values][{$campo['idcampo']}]", "edit_unit___INDEX___{$campo['idcampo']}"); ?><?php endforeach; ?>
                            </div>
                            <?php if (!$attemptMode) echo atividadeRenderizarRotaUnidade('unidades[__INDEX__]', $registro['rotulo_unidade'] . ' __NUMBER__', ''); ?>
                        </div>
                    </template>
                    <button type="button" class="add-unit-button" data-add-unit="edit">+ Adicionar <?php echo stridebr_lower(stridebr_e($registro['rotulo_unidade'])); ?></button>
                </div>
            </section>

            <?php echo atividadeForcaRenderEditor($strengthExercises, $exerciciosBiblioteca); ?>

            <?php
            $activityRouteAllowed = !empty($registro['permite_rota']);
            $activityRouteRaw = $registro['rota']['coordenadas'] ?? '';
            $activityRouteValue = is_array($activityRouteRaw) ? json_encode($activityRouteRaw, JSON_UNESCAPED_SLASHES) : (string) $activityRouteRaw;
            if ($_SERVER['REQUEST_METHOD'] === 'POST') $activityRouteValue = (string) ($_POST['rota_coordenadas'] ?? '');
            require dirname(__DIR__, 2) . '/src/layout/activity_route_editor.php';
            ?>

            <div class="activity-summary" data-activity-summary>
                <span class="activity-summary-label" data-summary-label>Resumo</span>
                <strong data-summary-text>Calculando dados da atividade...</strong>
            </div>

            <div class="activity-editor-details-grid">
                <div class="activity-main-details">
                    <div class="input-field activity-title-field">
                        <label for="titulo">Título</label>
                        <input type="text" id="titulo" name="titulo" value="<?php echo stridebr_e($registro['titulo']); ?>" maxlength="255">
                    </div>
                    <div class="input-field">
                        <label for="observacoes">Observações</label>
                        <textarea id="observacoes" name="observacoes" rows="3" placeholder="Como foi a atividade?"><?php echo stridebr_e($registro['observacoes']); ?></textarea>
                    </div>
                </div>

                <aside class="activity-secondary-details">
                    <div class="activity-compact-section activity-effort-section" data-effort-selector>
                        <div class="activity-section-label-row"><span class="activity-section-label">Esforço percebido</span><button type="button" class="activity-inline-action" data-clear-effort<?php echo empty($registro['esforco_percebido']) ? ' hidden' : ''; ?>>Não informar</button></div>
                        <input type="hidden" name="esforco_percebido" value="<?php echo stridebr_e((string) ($registro['esforco_percebido'] ?? '')); ?>" data-effort-value>
                        <div class="effort-range-row"><input type="range" min="1" max="10" step="1" value="<?php echo stridebr_e((string) (($registro['esforco_percebido'] ?? '') !== '' ? $registro['esforco_percebido'] : 5)); ?>" data-effort-range aria-label="Esforço percebido de 1 a 10"><output data-effort-output><?php echo ($registro['esforco_percebido'] ?? '') !== '' ? stridebr_e((string) $registro['esforco_percebido']) : '—'; ?></output></div>
                        <div class="effort-scale"><span>Fácil</span><span>Moderado</span><span>Máximo</span></div>
                    </div>

                    <div class="activity-compact-section">
                        <div class="activity-section-label-row">
                            <span class="activity-section-label">Equipamentos</span>
                            <a href="/user/equipamentos.php">Gerenciar</a>
                        </div>
                        <?php if ($equipamentos): ?>
                            <div class="equipment-picker">
                                <?php foreach ($equipamentos as $equipamento): ?>
                                    <?php $isSelected = isset($selectedEquipment[$equipamento['idequipamento']]); ?>
                                    <?php if (!$isSelected && !stridebr_db_bool($equipamento['ativo'])) continue; ?>
                                    <label class="equipment-chip<?php echo !stridebr_db_bool($equipamento['ativo']) ? ' is-archived' : ''; ?>">
                                        <input type="checkbox" name="equipamentos[]" value="<?php echo stridebr_e($equipamento['idequipamento']); ?>"<?php echo $isSelected ? ' checked' : ''; ?>>
                                        <span><?php echo stridebr_e($equipamento['nome']); ?><?php echo !stridebr_db_bool($equipamento['ativo']) ? ' · arquivado' : ''; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <a href="/user/equipamentos.php" class="activity-empty-action">+ Adicionar primeiro equipamento</a>
                        <?php endif; ?>
                    </div>

                    <div class="activity-compact-section activity-compact-two-columns">
                        <div class="input-field visibility-field">
                            <label for="visibilidade">Quem pode ver</label>
                            <select id="visibilidade" name="visibilidade">
                                <?php foreach (['privado' => 'Só eu', 'amigos' => 'Amigos', 'publico' => 'Público'] as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"<?php echo $registro['visibilidade'] === $value ? ' selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <label class="input-field">
                            <span>Ocultar início da rota</span>
                            <input type="number" name="ocultar_inicio_m" min="0" max="10000" step="50" value="<?php echo (int) ($registro['ocultar_inicio_m'] ?? 0); ?>" inputmode="numeric">
                            <small>metros · afeta apenas a visualização compartilhada</small>
                        </label>
                        <label class="input-field">
                            <span>Ocultar fim da rota</span>
                            <input type="number" name="ocultar_fim_m" min="0" max="10000" step="50" value="<?php echo (int) ($registro['ocultar_fim_m'] ?? 0); ?>" inputmode="numeric">
                            <small>metros · 0 para mostrar tudo</small>
                        </label>
                    </div>
                </aside>
            </div>

            <div class="activity-form-actions activity-edit-actions">
                <button type="submit" class="activity-danger-button" formaction="/function/apagaratividade.php" formmethod="post" formnovalidate data-confirm-delete-submit>Apagar atividade</button>
                <span class="activity-form-actions-spacer"></span>
                <a class="activity-secondary-button" href="/user/atividades.php">Cancelar</a>
                <button type="submit" class="activity-primary-action">Salvar alterações</button>
            </div>
        </form>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/time24.js')); ?>"></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/atividades.js')); ?>"></script>
<?php if ($embedded): ?>
<script>
document.addEventListener('click',event=>{const cancel=event.target.closest('.activity-back-link,.activity-edit-actions .activity-secondary-button');if(!cancel)return;event.preventDefault();window.parent.postMessage({type:'stridebr:activity-edit-cancel'},window.location.origin)})
</script>
<?php endif; ?>
</body>
</html>
