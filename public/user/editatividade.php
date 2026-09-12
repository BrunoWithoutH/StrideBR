<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();

require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_presenter.php';
require_once dirname(__DIR__, 2) . '/src/function/strength_activity.php';
require_once dirname(__DIR__, 2) . '/src/function/activity_energy.php';
require_once dirname(__DIR__, 2) . '/src/function/competitions.php';
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
if ($embedded && (string) ($_GET['delete_error'] ?? '') === '1') $errors[] = stridebr_t('activity.edit_delete_error');
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
        if ($modeloDestino === []) throw new InvalidArgumentException(stridebr_t('activity.invalid_type'));

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
        $durationSource = trim((string) ($_POST['duration_source'] ?? 'preserve'));
        $segmentsPosted = !empty($_POST['usa_trechos']);
        $postedDurationSeconds = $segmentsPosted ? null : atividadeDuracaoPayloadSegundos($recordValues, $unidades, $camposDestino);
        $currentDurationSeconds = $segmentsPosted ? null : atividadeDuracaoPayloadSegundos(
            is_array($registro['record_values'] ?? null) ? $registro['record_values'] : [],
            is_array($registro['unidades'] ?? null) ? $registro['unidades'] : [],
            is_array($registro['campos'] ?? null) ? $registro['campos'] : []
        );
        if (!$segmentsPosted && $postedDurationSeconds !== null && $currentDurationSeconds !== null && abs($postedDurationSeconds - $currentDurationSeconds) >= 0.0005) {
            $durationSource = 'duration';
        }
        if (!in_array($durationSource, ['preserve', 'duration', 'end'], true)) $durationSource = 'preserve';
        $inicioReal = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $inicioRaw);
        if (!$inicioReal || $inicioReal->format('Y-m-d H:i') !== $inicioRaw) throw new InvalidArgumentException(stridebr_t('activity.invalid_datetime'));
        if (!$segmentsPosted && $durationSource !== 'end' && $postedDurationSeconds !== null) {
            $fimRaw = $inicioReal->modify('+' . (int) floor(max(0.0, $postedDurationSeconds)) . ' seconds')->format('Y-m-d H:i');
        } elseif (!$segmentsPosted && $fimRaw !== '') {
            $fimReal = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $fimRaw);
            if (!$fimReal || $fimReal < $inicioReal) throw new InvalidArgumentException(stridebr_t('activity.end_after_start'));
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
                'idcompeticao' => $_POST['idcompeticao'] ?? ($registro['idcompeticao'] ?? ''),
                'equipamentos' => is_array($_POST['equipamentos'] ?? null) ? $_POST['equipamentos'] : [],
                'record_values' => $recordValues,
                'unidades' => $unidades,
                'usa_trechos' => !empty($_POST['usa_trechos']),
                'rota_coordenadas' => $_POST['rota_coordenadas'] ?? '',
                'rota_metricas' => is_array($_POST['rota_metricas'] ?? null) ? $_POST['rota_metricas'] : [],
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
            $bridge = stridebr_asset('/assets/js/activity-edit-bridge.js');
            echo '<!doctype html><html><body><div data-activity-edit-result data-type="stridebr:activity-edit-saved" data-id="' . stridebr_e($idRegistro) . '"></div><script src="' . stridebr_e($bridge) . '"></script></body></html>';
            exit;
        }
        stridebr_flash('success', stridebr_t('activity.updated'));
        header('Location: /user/atividades.php?highlight=' . rawurlencode($idRegistro));
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException ? $e->getMessage() : stridebr_t('activity.update_error');
        if (!$e instanceof InvalidArgumentException) {
            error_log($e->getMessage());
        }
        $registro = atividadeCarregarRegistro($pdo, $idRegistro, $idUsuario);
        $equipamentos = atividadeListarEquipamentos($pdo, $idUsuario, false);
        $camposAgrupados = atividadeAgruparCampos($registro['campos']);
        $strengthExercises = is_array($_POST['strength_exercises'] ?? null) ? $_POST['strength_exercises'] : atividadeForcaBuscarSeries($pdo, $idUsuario, $idRegistro);
    }
}

$inicio = new DateTimeImmutable($registro['data_inicio']);
$fim = !empty($registro['data_fim']) ? new DateTimeImmutable((string) $registro['data_fim']) : null;
$unitFields = atividadeFiltrarCamposPorModalidade($camposAgrupados['unidade'], $editFamily, (string) ($registro['modalidade_slug'] ?? ''));
usort($unitFields, static fn(array $a, array $b): int => atividadeCampoOrdemVisual($a) <=> atividadeCampoOrdemVisual($b));
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
    if (atividadeCampoOpcional($field)) {
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
    <title><?php echo stridebr_e(stridebr_t('activity.edit_page_title')); ?> | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body class="<?php echo $embedded ? 'activity-edit-embedded' : ''; ?>">
<div class="container-fluid">
    <?php if (!$embedded) require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content activities-page">
        <header class="activities-toolbar">
            <div class="activities-toolbar-title activity-edit-toolbar-title">
                <a class="activity-back-link context-back-button" href="/user/atividades.php" data-safe-back>← <?php echo stridebr_e(stridebr_t('activity.back_activities')); ?></a>
                <h1><?php echo stridebr_e(stridebr_t('activity.edit_page_title')); ?></h1>
            </div>
            <div class="activities-toolbar-actions">
                <a href="/user/equipamentos.php" class="activity-toolbar-link"><?php echo stridebr_e(stridebr_t('activity.equipment')); ?></a>
            </div>
        </header>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger activity-alert"><?php echo stridebr_e($error); ?></div>
        <?php endforeach; ?>

        <form method="POST"<?php if ($embedded): ?> action="/user/editatividade.php?id=<?php echo rawurlencode($idRegistro); ?>&embed=1"<?php endif; ?> class="activity-editor activity-edit-form" id="activity-form" autocomplete="off">
            <?php echo stridebr_csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo stridebr_e($idRegistro); ?>">
            <?php if ($embedded): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
            <input type="hidden" name="duration_source" value="preserve" data-duration-source>

            <?php if (!$embedded): ?>
            <div class="activity-editor-heading">
                <div>
                    <h2><?php echo stridebr_e(stridebr_t('activity.edit_page_title')); ?></h2>
                    <p class="activity-editor-kind"><span class="activity-editor-kind-icon"><?php echo stridebr_sport_icon_html((string) $registro['modalidade_slug']); ?></span><span><?php echo stridebr_e(stridebr_sport_name((string) $registro['modalidade_slug'], (string) $registro['modalidade_nome'])); ?></span><span aria-hidden="true">·</span><span><?php echo stridebr_e($registro['modelo_nome']); ?></span></p>
                </div>
            </div>
            <?php endif; ?>

            <?php $activityEditorTitle = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string) ($_POST['titulo'] ?? '') : (string) ($registro['titulo'] ?? ''); $activityEditorTitleEmbedded = $embedded; require dirname(__DIR__, 2) . '/src/layout/activity_smart_title.php'; ?>

            <div class="activity-context-grid activity-edit-context-grid">
                <div class="input-field activity-sport-field activity-edit-sport-field">
                    <label for="activity-edit-sport-search"><?php echo stridebr_e(stridebr_t('activity.sport_activity')); ?></label>
                    <div class="sport-combobox" data-sport-combobox>
                        <button type="button" class="sport-combobox-trigger" data-sport-trigger aria-haspopup="listbox" aria-expanded="false"><span data-sport-current class="sport-current"><?php echo stridebr_e(stridebr_t('activity.choose_sport')); ?></span><span aria-hidden="true">⌄</span></button>
                        <div class="sport-combobox-popover" data-sport-popover hidden>
                            <div class="sport-search-row"><input type="search" id="activity-edit-sport-search" placeholder="<?php echo stridebr_e(stridebr_t('activity.search_sport_placeholder')); ?>" data-sport-search spellcheck="false"></div>
                            <div class="sport-options" role="listbox" data-sport-options>
                                <?php if ($catalogoEditFavoritas): ?>
                                    <div class="sport-favorites-quick" data-sport-quick aria-label="<?php echo stridebr_e(stridebr_t('activity.favorites_aria')); ?>"><div class="sport-group-title"><?php echo stridebr_e(stridebr_t('activity.favorites')); ?></div><div class="sport-favorites-chips">
                                        <?php foreach ($catalogoEditFavoritas as $modalidade): ?><button type="button" class="sport-option sport-favorite-quick" data-sport-option data-sport-id="<?php echo stridebr_e((string) $modalidade['idmodalidade']); ?>" data-sport-name="<?php echo stridebr_e(stridebr_sport_name((string) ($modalidade['slug'] ?? ''), (string) ($modalidade['nome'] ?? ''))); ?>" data-sport-slug="<?php echo stridebr_e((string) $modalidade['slug']); ?>" data-sport-family="<?php echo stridebr_e(sportCatalogFamilyKey((string) ($modalidade['familia_hub'] ?? ''), (string) ($modalidade['categoria'] ?? ''), (string) ($modalidade['slug'] ?? ''))); ?>"><span class="sport-option-icon"><?php echo stridebr_sport_icon_html((string) $modalidade['slug']); ?></span><span><?php echo stridebr_e(stridebr_sport_name((string) ($modalidade['slug'] ?? ''), (string) ($modalidade['nome'] ?? ''))); ?></span></button><?php endforeach; ?>
                                    </div></div>
                                <?php endif; ?>
                                <?php if ($catalogoEditRecentes): ?>
                                    <div class="sport-favorites-quick" data-sport-quick aria-label="<?php echo stridebr_e(stridebr_t('activity.recents_aria')); ?>"><div class="sport-group-title"><?php echo stridebr_e(stridebr_t('activity.recents')); ?></div><div class="sport-favorites-chips">
                                        <?php foreach ($catalogoEditRecentes as $modalidade): ?><button type="button" class="sport-option sport-favorite-quick" data-sport-option data-sport-id="<?php echo stridebr_e((string) $modalidade['idmodalidade']); ?>" data-sport-name="<?php echo stridebr_e(stridebr_sport_name((string) ($modalidade['slug'] ?? ''), (string) ($modalidade['nome'] ?? ''))); ?>" data-sport-slug="<?php echo stridebr_e((string) $modalidade['slug']); ?>" data-sport-family="<?php echo stridebr_e(sportCatalogFamilyKey((string) ($modalidade['familia_hub'] ?? ''), (string) ($modalidade['categoria'] ?? ''), (string) ($modalidade['slug'] ?? ''))); ?>"><span class="sport-option-icon"><?php echo stridebr_sport_icon_html((string) $modalidade['slug']); ?></span><span><?php echo stridebr_e(stridebr_sport_name((string) ($modalidade['slug'] ?? ''), (string) ($modalidade['nome'] ?? ''))); ?></span></button><?php endforeach; ?>
                                    </div></div>
                                <?php endif; ?>
                                <?php echo sportPickerRenderFamilyBrowser($catalogo, (string) $registro['idmodalidade'], false); ?>
                            </div>
                        </div>
                        <select id="modalidade" name="idmodalidade" class="activity-native-select" tabindex="-1" aria-hidden="true">
                            <?php foreach ($catalogo as $atividadeCatalogo): ?><option value="<?php echo stridebr_e((string) $atividadeCatalogo['idmodalidade']); ?>" data-slug="<?php echo stridebr_e((string) ($atividadeCatalogo['slug'] ?? '')); ?>" data-family="<?php echo stridebr_e(sportCatalogFamilyKey((string) ($atividadeCatalogo['familia_hub'] ?? ''), (string) ($atividadeCatalogo['categoria'] ?? ''), (string) ($atividadeCatalogo['slug'] ?? ''))); ?>" data-permite-rota="<?php echo !empty($atividadeCatalogo['permite_rota']) ? '1' : '0'; ?>"<?php echo (string) $registro['idmodalidade'] === (string) $atividadeCatalogo['idmodalidade'] ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_sport_name((string) ($atividadeCatalogo['slug'] ?? ''), (string) ($atividadeCatalogo['nome'] ?? ''))); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <small class="activity-field-help"><?php echo stridebr_e(stridebr_t('activity.type_change_help')); ?></small>
                </div>
                <div class="input-field activity-edit-date-field">
                    <label for="data"><?php echo stridebr_e(stridebr_t('common.date')); ?></label>
                    <input type="date" id="data" name="data" value="<?php echo $inicio->format('Y-m-d'); ?>" required>
                </div>
                <div class="input-field activity-edit-time-field">
                    <label for="hora_h"><?php echo stridebr_e(stridebr_t('common.time')); ?></label>
                    <div class="clock-segments" data-clock-field>
                        <input type="text" id="hora_h" inputmode="numeric" maxlength="2" value="<?php echo $inicio->format('H'); ?>" data-clock-hours aria-label="<?php echo stridebr_e(stridebr_t('activity.hours')); ?>">
                        <span aria-hidden="true">:</span>
                        <input type="text" inputmode="numeric" maxlength="2" value="<?php echo $inicio->format('i'); ?>" data-clock-minutes aria-label="<?php echo stridebr_e(stridebr_t('activity.minutes')); ?>">
                        <button type="button" class="time-now-button" data-time-now><?php echo stridebr_e(stridebr_t('common.now')); ?></button>
                        <button type="button" class="time-quick-toggle" data-time-quick-toggle aria-label="<?php echo stridebr_e(stridebr_t('activity.open_quick_times')); ?>">⌄</button>
                        <div class="time-quick-menu" data-time-quick-menu hidden></div>
                        <input type="hidden" id="hora" name="hora" value="<?php echo $inicio->format('H:i'); ?>" data-clock-value>
                    </div>
                </div>
                <div class="input-field activity-edit-end-field">
                    <label><?php echo stridebr_e(stridebr_t('activity.end')); ?></label>
                    <div class="activity-end-control">
                        <input type="date" value="<?php echo $fim ? stridebr_e($fim->format('Y-m-d')) : ''; ?>" data-end-date aria-label="<?php echo stridebr_e(stridebr_t('activity.end_date')); ?>">
                        <div class="time24-control" data-time24><div class="time24-input-row"><input type="text" inputmode="numeric" maxlength="2" data-time24-hours aria-label="<?php echo stridebr_e(stridebr_t('activity.end_hour')); ?>"><span class="time24-separator">:</span><input type="text" inputmode="numeric" maxlength="2" data-time24-minutes aria-label="<?php echo stridebr_e(stridebr_t('activity.end_minutes')); ?>"><button type="button" class="time24-toggle" data-time24-toggle aria-label="<?php echo stridebr_e(stridebr_t('agenda.choose_time')); ?>">⌄</button></div><div class="time24-menu" data-time24-menu hidden><div class="time24-menu-head"><span><?php echo stridebr_e(stridebr_t('schedule.time_24h')); ?></span><button type="button" data-time24-now><?php echo stridebr_e(stridebr_t('common.now')); ?></button></div><div class="time24-hours-grid" data-time24-hours-grid></div><div class="time24-minutes-grid" data-time24-minutes-grid></div></div><input type="hidden" value="<?php echo $fim ? stridebr_e($fim->format('H:i')) : ''; ?>" data-time24-value data-end-time></div>
                        <input type="hidden" id="data_fim" name="data_fim" value="<?php echo $fim ? stridebr_e($fim->format('Y-m-d\TH:i')) : ''; ?>" data-end-datetime>
                    </div>
                    <small class="activity-field-help"><?php echo stridebr_e(stridebr_t('activity.end_duration_help')); ?></small>
                </div>
                <div class="input-field activity-edit-status-field">
                    <label for="status"><?php echo stridebr_e(stridebr_t('common.status')); ?></label>
                    <select id="status" name="status">
                        <?php foreach (['rascunho' => stridebr_t('activity.status_draft'), 'ativo' => stridebr_t('activity.status_active'), 'concluido' => stridebr_t('activity.status_completed'), 'cancelado' => stridebr_t('activity.status_cancelled')] as $value => $label): ?>
                            <option value="<?php echo $value; ?>"<?php echo $registro['status'] === $value ? ' selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php
            $editPanelUnits = is_array($_POST['unidades'] ?? null) ? $_POST['unidades'] : (array) ($registro['unidades'] ?? []);
            $editPrimaryUnit = is_array($editPanelUnits[0] ?? null) ? $editPanelUnits[0] : $primaryUnit;
            $editExtraUnits = array_slice($editPanelUnits, 1, null, true);
            $editRecordValues = is_array($_POST['record_values'] ?? null) ? $_POST['record_values'] : (array) ($registro['record_values'] ?? []);
            $editModelo = $registro + [
                'idmodalidade' => (string) ($registro['idmodalidade'] ?? ''),
                'metrica_derivada' => (string) ($registro['metrica_derivada'] ?? 'nenhuma'),
                'rotulo_unidade' => (string) ($registro['rotulo_unidade'] ?? stridebr_t('activity.segment')),
                'tipo_unidade_padrao' => (string) ($registro['tipo_unidade_padrao'] ?? 'unidade'),
                'permite_multiplas_unidades' => !empty($registro['permite_multiplas_unidades']),
            ];
            $activityPanel = [
                'modelo' => $editModelo,
                'panel_id' => 'edit',
                'name_prefix' => '',
                'html_id_prefix' => 'edit_unit',
                'data_units_model' => 'edit',
                'hidden' => false,
                'enforce_required' => true,
                'unit_fields' => $unitFields,
                'record_fields' => $recordFields,
                'primary_unit' => $editPrimaryUnit,
                'extra_units' => $editExtraUnits,
                'record_values' => $editRecordValues,
                'segments_active' => $segmentsActive,
            ];
            require dirname(__DIR__, 2) . '/src/layout/activity_model_panel_shared.php';
            ?>

            <?php echo atividadeForcaRenderEditor($strengthExercises, $exerciciosBiblioteca); ?>

            <?php
            $activityRouteAllowed = !empty($registro['permite_rota']);
            $activityRouteRaw = $registro['rota']['coordenadas'] ?? '';
            $activityRouteValue = is_array($activityRouteRaw) ? json_encode($activityRouteRaw, JSON_UNESCAPED_SLASHES) : (string) $activityRouteRaw;
            if ($_SERVER['REQUEST_METHOD'] === 'POST') $activityRouteValue = (string) ($_POST['rota_coordenadas'] ?? '');
            $activityRouteMode = $_POST['route_editor_mode'] ?? 'free';
            $activityRouteLaps = $_POST['route_editor_laps'] ?? 1;
            $activityRouteBase = $_POST['route_editor_base'] ?? '';
            $activityRouteCompact = true;
            require dirname(__DIR__, 2) . '/src/layout/activity_route_editor.php';
            ?>

            <?php
            $activityRoutePrivacyHasRoute = $activityRouteValue !== '';
            $activityRoutePrivacyStart = $_SERVER['REQUEST_METHOD'] === 'POST' ? (int) ($_POST['ocultar_inicio_m'] ?? 0) : (int) ($registro['ocultar_inicio_m'] ?? 0);
            $activityRoutePrivacyEnd = $_SERVER['REQUEST_METHOD'] === 'POST' ? (int) ($_POST['ocultar_fim_m'] ?? 0) : (int) ($registro['ocultar_fim_m'] ?? 0);
            require dirname(__DIR__, 2) . '/src/layout/activity_route_privacy.php';

            $activityEditorEffort = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string) ($_POST['esforco_percebido'] ?? '') : (string) ($registro['esforco_percebido'] ?? '');
            $activityEditorEquipment = $equipamentos;
            $activityEditorSelectedEquipment = $_SERVER['REQUEST_METHOD'] === 'POST' && is_array($_POST['equipamentos'] ?? null) ? array_map('strval', $_POST['equipamentos']) : array_keys($selectedEquipment);
            $activityEditorEquipmentLoaded = true;
            $activityEditorObservations = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string) ($_POST['observacoes'] ?? '') : (string) ($registro['observacoes'] ?? '');
            $activityEditorVisibility = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string) ($_POST['visibilidade'] ?? 'privado') : (string) ($registro['visibilidade'] ?? 'privado');
            $activityEditorCompetition = $_SERVER['REQUEST_METHOD'] === 'POST' ? trim((string) ($_POST['idcompeticao'] ?? '')) : trim((string) ($registro['idcompeticao'] ?? ''));
            $activityCompetitionDate = $_SERVER['REQUEST_METHOD'] === 'POST' ? trim((string) ($_POST['data'] ?? '')) : $inicio->format('Y-m-d');
            $activityEditorCompetitions = competitionNearby($pdo, $idUsuario, $activityCompetitionDate, 12);
            if ($activityEditorCompetition !== '' && !array_filter($activityEditorCompetitions, static fn(array $item): bool => (string)($item['idcompeticao']??'') === $activityEditorCompetition)) {
                $currentCompetition = competitionGet($pdo, $idUsuario, $activityEditorCompetition);
                if (is_array($currentCompetition)) array_unshift($activityEditorCompetitions, $currentCompetition);
            }
            require dirname(__DIR__, 2) . '/src/layout/activity_log_details.php';
            ?>

            <div class="activity-form-actions activity-edit-actions">
                <button type="submit" class="activity-danger-button" formaction="/function/apagaratividade.php<?php echo $embedded ? '?embed=1' : ''; ?>" formmethod="post" formnovalidate data-confirm-delete-submit><?php echo stridebr_e(stridebr_t('activity.delete_activity')); ?></button>
                <span class="activity-form-actions-spacer"></span>
                <a class="activity-secondary-button" href="/user/atividades.php"><?php echo stridebr_e(stridebr_t('common.cancel')); ?></a>
                <button type="submit" class="activity-primary-action"><?php echo stridebr_e(stridebr_t('settings.save_changes')); ?></button>
            </div>
        </form>
    </main>
</div>
<?php if (!$embedded): ?>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<?php else: ?>
<?php echo stridebr_i18n_runtime_script(false); ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/scripts.js')); ?>"></script>
<?php endif; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/time24.js')); ?>"></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/activity-route-utils.js')); ?>"></script>
<?php echo stridebr_maps_runtime_script(); ?>
<?php echo atividadeContextoJsConfigScript(); ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/activity-sport-context.js')); ?>"></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/atividades.js')); ?>"></script>
<?php if ($embedded): ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/activity-edit-bridge.js')); ?>"></script>
<?php endif; ?>
</body>
</html>
