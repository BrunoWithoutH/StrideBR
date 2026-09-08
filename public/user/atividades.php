<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();

require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_presenter.php';
require_once dirname(__DIR__, 2) . '/src/function/product_analytics.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma.php';
require_once dirname(__DIR__, 2) . '/src/function/strength_activity.php';
require_once dirname(__DIR__, 2) . '/src/function/activity_energy.php';
require_once dirname(__DIR__, 2) . '/src/includes/sport_icons.php';
require_once dirname(__DIR__, 2) . '/src/layout/sport_picker.php';
require_once dirname(__DIR__, 2) . '/src/layout/activity_strength_editor.php';

date_default_timezone_set('America/Sao_Paulo');
$errors = [];
$activitySaveJson = $_SERVER['REQUEST_METHOD'] === 'POST' && str_contains(stridebr_lower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'restore_activity') {
    stridebr_verify_csrf();
    $undo = is_array($_SESSION['activity_undo'] ?? null) ? $_SESSION['activity_undo'] : [];
    $token = (string) ($_POST['undo_token'] ?? '');
    if ($undo === [] || (int) ($undo['expires'] ?? 0) < time() || !hash_equals((string) ($undo['token'] ?? ''), $token)) {
        unset($_SESSION['activity_undo']);
        stridebr_flash('danger', 'O prazo para desfazer terminou.');
    } elseif (atividadeRestaurarRegistro($pdo, (string) ($undo['idregistro'] ?? ''), $idUsuario)) {
        unset($_SESSION['activity_undo']);
        stridebr_flash('success', stridebr_t('activity.restored'));
    } else {
        unset($_SESSION['activity_undo']);
        stridebr_flash('danger', stridebr_t('activity.restore_error'));
    }
    header('Location: /user/atividades.php');
    exit;
}

$catalogStartedAt = microtime(true);
$catalogo = atividadeListarCatalogo($pdo, $idUsuario);
$activityDefaults = atividadePadroesUsuario($pdo, $idUsuario);
stridebr_timing_measure('activity_catalog', $catalogStartedAt, 'Catálogo de atividades');

$repeatRecord = [];
$repeatId = trim((string) ($_GET['repetir'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $repeatId !== '') {
    $repeatRecord = atividadeCarregarRegistro($pdo, $repeatId, $idUsuario);
    if ($repeatRecord === []) {
        stridebr_flash('info', stridebr_t('activity.base_unavailable'));
        header('Location: /user/atividades.php');
        exit;
    }
}

$editorDetailsLoaded = $_SERVER['REQUEST_METHOD'] === 'POST' || $repeatId !== '';
$modelosDetalhados = [];
$equipamentos = [];
$treinosUsuario = [];
$exerciciosBiblioteca = [];

if ($editorDetailsLoaded) {
    $detailsStartedAt = microtime(true);
    $editorModelId = trim((string) ($_POST['idmodelo'] ?? ($repeatRecord['idmodelo'] ?? '')));
    $catalogoModelo = [];
    foreach ($catalogo as $modalidadeCatalogo) {
        $modelos = array_values(array_filter((array) ($modalidadeCatalogo['modelos'] ?? []), static fn(array $modelo): bool => (string) ($modelo['idmodelo'] ?? '') === $editorModelId));
        if ($modelos === []) continue;
        $item = $modalidadeCatalogo;
        $item['modelos'] = $modelos;
        $catalogoModelo[] = $item;
        break;
    }
    if ($editorModelId !== '' && $catalogoModelo !== []) {
        $camposPorModelo = atividadeBuscarCamposModelos($pdo, [$editorModelId]);
        $modelosDetalhados = atividadeMontarModelosDetalhados($catalogoModelo, $camposPorModelo);
    }
    $equipamentos = atividadeListarEquipamentosLeve($pdo, $idUsuario);
    $treinosUsuario = cronogramaListarTreinosUsuario($pdo, $idUsuario);
    $exerciciosBiblioteca = cronogramaListarExerciciosBiblioteca($pdo, $idUsuario);
    stridebr_timing_measure('activity_editor', $detailsStartedAt, 'Dados do editor');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $repeatId !== '' && !isset($modelosDetalhados[(string) ($repeatRecord['idmodelo'] ?? '')])) {
    stridebr_flash('info', stridebr_t('activity.base_unavailable'));
    header('Location: /user/atividades.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $idModelo = (string) ($_POST['idmodelo'] ?? '');
    $modelPayload = $_POST['models'][$idModelo] ?? [];

    try {
        $idTreinoSelecionado = trim((string) ($_POST['idtreino_cronograma'] ?? ''));
        $treinoSelecionado = [];
        if ($idTreinoSelecionado !== '') {
            $treinoSelecionado = cronogramaBuscarTreino($pdo, $idTreinoSelecionado, $idUsuario);
            if ($treinoSelecionado === []) {
                throw new InvalidArgumentException(stridebr_t('activity.selected_workout_unavailable'));
            }
        }
        $tituloAtividade = trim((string) ($_POST['titulo'] ?? ''));
        if ($tituloAtividade === '' && $treinoSelecionado !== []) {
            $tituloAtividade = (string) $treinoSelecionado['titulo'];
        }
        $ocorrenciaPlanejada = [];
        if ($treinoSelecionado !== []) {
            $ocorrenciaPlanejada = cronogramaInferirOcorrenciaParaData($pdo, $idUsuario, (string) $treinoSelecionado['idtreino'], trim((string) ($_POST['data'] ?? '')));
        }
        $selectedSport = null;
        $selectedSportId = trim((string) ($_POST['idmodalidade'] ?? ''));
        foreach ($catalogo as $sport) {
            if ((string) ($sport['idmodalidade'] ?? '') === $selectedSportId) {
                $selectedSport = $sport;
                break;
            }
        }
        $selectedFamily = sportCatalogFamilyKey((string) ($selectedSport['familia_hub'] ?? ''), (string) ($selectedSport['categoria'] ?? ''), (string) ($selectedSport['slug'] ?? ''));
        $strengthPayload = is_array($_POST['strength_exercises'] ?? null) ? $_POST['strength_exercises'] : [];
        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) $pdo->beginTransaction();
        try {
            $savedActivityId = atividadeSalvarRegistro($pdo, $idUsuario, [
                'idmodelo' => $idModelo,
                'titulo' => $tituloAtividade,
                'observacoes' => $_POST['observacoes'] ?? '',
                'data_inicio' => trim((string) ($_POST['data'] ?? '')) . ' ' . trim((string) ($_POST['hora'] ?? '')),
                'status' => 'concluido',
                'visibilidade' => $_POST['visibilidade'] ?? '',
                'ocultar_inicio_m' => $_POST['ocultar_inicio_m'] ?? null,
                'ocultar_fim_m' => $_POST['ocultar_fim_m'] ?? null,
                'esforco_percebido' => $_POST['esforco_percebido'] ?? '',
                'idcronograma' => $treinoSelecionado['idcronograma'] ?? null,
                'idtreino_cronograma' => $treinoSelecionado['idtreino'] ?? null,
                'data_ocorrencia_origem' => $ocorrenciaPlanejada['data_ocorrencia_origem'] ?? null,
                'data_ocorrencia_planejada' => $ocorrenciaPlanejada['data_ocorrencia_planejada'] ?? null,
                'hora_ocorrencia_planejada' => $ocorrenciaPlanejada['hora_ocorrencia_planejada'] ?? null,
                'equipamentos' => is_array($_POST['equipamentos'] ?? null) ? $_POST['equipamentos'] : [],
                'record_values' => is_array($modelPayload['record_values'] ?? null) ? $modelPayload['record_values'] : [],
                'unidades' => is_array($modelPayload['unidades'] ?? null) ? $modelPayload['unidades'] : [],
                'usa_trechos' => !empty($modelPayload['usa_trechos']),
                'rota_coordenadas' => $_POST['rota_coordenadas'] ?? '',
                'rota_metricas' => is_array($_POST['rota_metricas'] ?? null) ? $_POST['rota_metricas'] : [],
                'permitir_campos_vazios' => true,
                'origem' => 'manual',
            ]);
            if ($selectedFamily === 'strength') atividadeForcaPersistirSeriesManuais($pdo, $idUsuario, $savedActivityId, $strengthPayload);
            if ($selectedFamily === 'strength' && stridebr_db_column_exists($pdo, 'registros_atividade', 'calorias_ativas_estimadas')) {
                $pdo->exec('SAVEPOINT stridebr_strength_energy');
                try {
                    atividadeEnergiaAtualizarRegistro($pdo, $savedActivityId, $idUsuario);
                    $pdo->exec('RELEASE SAVEPOINT stridebr_strength_energy');
                } catch (Throwable $energyError) {
                    $pdo->exec('ROLLBACK TO SAVEPOINT stridebr_strength_energy');
                    error_log('StrideBR strength energy after manual save: ' . $energyError->getMessage());
                }
            }
            if ($startedTransaction) $pdo->commit();
        } catch (Throwable $saveError) {
            if ($startedTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $saveError;
        }
        try {
            productAnalyticsRegistrar($pdo, $idUsuario, 'activity_saved', ['source' => 'manual']);
        } catch (Throwable $analyticsError) {
            error_log('StrideBR analytics after activity save: ' . $analyticsError->getMessage());
        }
        if ($activitySaveJson) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => true, 'id' => (string) $savedActivityId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        stridebr_flash('success', stridebr_t('activity.registered_success'));
        header('Location: /user/atividades.php?saved=' . rawurlencode((string) $savedActivityId));
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException ? $e->getMessage() : stridebr_t('activity.save_error');
        if (!$e instanceof InvalidArgumentException) {
            error_log($e->getMessage());
        }
        if ($activitySaveJson) {
            http_response_code(422);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => false, 'error' => $errors[0], 'errors' => $errors], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
}

$flashes = stridebr_take_flashes();

$initialHistory = ['items' => [], 'next_cursor' => null];
$initialHistorySummary = null;
$initialHistoryState = 'error';
$historyStartedAt = microtime(true);
$historyStatementTimeout = min((int) ($dbStatementTimeout ?? 15000), 5000);
$historyLockTimeout = min((int) ($dbLockTimeout ?? 5000), 1500);
try {
    $pdo->exec("SET statement_timeout TO '{$historyStatementTimeout}ms'; SET lock_timeout TO '{$historyLockTimeout}ms'");
    $initialHistory = atividadeListarRegistrosPagina($pdo, $idUsuario, 20);
    $initialHistorySummary = atividadeResumoHistorico($pdo, $idUsuario);
    $initialHistory['resumo'] = $initialHistorySummary;
    $initialHistoryState = !empty($initialHistory['items']) ? 'ready' : 'empty';
} catch (Throwable $historyError) {
    error_log('StrideBR initial activity history: ' . $historyError->getMessage());
} finally {
    try {
        $pdo->exec("SET statement_timeout TO '{$dbStatementTimeout}ms'; SET lock_timeout TO '{$dbLockTimeout}ms'");
    } catch (Throwable) {}
}
stridebr_timing_measure('activity_history_ssr', $historyStartedAt, 'Histórico inicial de atividades');
$initialHistoryItems = is_array($initialHistory['items'] ?? null) ? $initialHistory['items'] : [];
$initialHistoryCursor = is_string($initialHistory['next_cursor'] ?? null) ? $initialHistory['next_cursor'] : '';
$initialHistoryTotal = count($initialHistoryItems);

$repeatRoute = $repeatRecord['rota']['coordenadas'] ?? '';
if (is_array($repeatRoute)) $repeatRoute = json_encode($repeatRoute, JSON_UNESCAPED_SLASHES);
$formModalidade = (string) ($_POST['idmodalidade'] ?? ($repeatRecord['idmodalidade'] ?? ($catalogo[0]['idmodalidade'] ?? '')));
$formModelo = (string) ($_POST['idmodelo'] ?? ($repeatRecord['idmodelo'] ?? ($catalogo[0]['modelos'][0]['idmodelo'] ?? '')));
$formWorkout = (string) ($_POST['idtreino_cronograma'] ?? ($repeatRecord['idtreino_cronograma'] ?? ''));
$formDate = (string) ($_POST['data'] ?? date('Y-m-d'));
$formHour = (string) ($_POST['hora'] ?? date('H:i'));
$formTitle = (string) ($_POST['titulo'] ?? ($repeatRecord['titulo'] ?? ''));
$formObservations = (string) ($_POST['observacoes'] ?? '');
$formEffort = (string) ($_POST['esforco_percebido'] ?? '');
$formVisibility = (string) ($_POST['visibilidade'] ?? ($repeatRecord['visibilidade'] ?? ($activityDefaults['visibility'] ?? 'privado')));
$formHideRouteStart = (int) ($_POST['ocultar_inicio_m'] ?? ($repeatRecord['ocultar_inicio_m'] ?? ($activityDefaults['hide_route_start_m'] ?? 0)));
$formHideRouteEnd = (int) ($_POST['ocultar_fim_m'] ?? ($repeatRecord['ocultar_fim_m'] ?? ($activityDefaults['hide_route_end_m'] ?? 0)));
$formRoute = (string) ($_POST['rota_coordenadas'] ?? $repeatRoute);
$formStrengthExercises = is_array($_POST['strength_exercises'] ?? null) ? $_POST['strength_exercises'] : (($repeatId !== '' && $repeatRecord !== []) ? atividadeForcaBuscarSeries($pdo, $idUsuario, $repeatId) : []);
$formEquipment = is_array($_POST['equipamentos'] ?? null)
    ? array_map('strval', $_POST['equipamentos'])
    : array_map(static fn(array $item): string => (string) $item['idequipamento'], (array) ($repeatRecord['equipamentos'] ?? []));
$firstModalidade = $formModalidade;
$firstModelo = $formModelo;
$favoritas = array_values(array_filter($catalogo, static fn(array $item): bool => !empty($item['favorita'])));
$recentes = array_values(array_filter($catalogo, static fn(array $item): bool => empty($item['favorita']) && !empty($item['ultimo_uso'])));
usort($recentes, static fn(array $a, array $b): int => strcmp((string) $b['ultimo_uso'], (string) $a['ultimo_uso']));
$recentes = array_slice($recentes, 0, 5);
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
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/product-insights.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/activity-exchange.css')); ?>">
    <title><?php echo stridebr_e(stridebr_t('activity.page_title')); ?> | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/activity-sharing.css')); ?>">
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content activities-page" data-activities-page data-csrf-token="<?php echo stridebr_e(stridebr_csrf_token()); ?>">
        <header class="activities-toolbar">
            <div class="activities-toolbar-title">
                <h1><?php echo stridebr_e(stridebr_t('activity.page_title')); ?></h1>
                <span data-activity-history-status></span>
            </div>
            <div class="activities-toolbar-actions">
                <a href="/user/gravar-atividade.php?quick=corrida&autostart=1" class="activity-toolbar-link activity-toolbar-gps activity-toolbar-quick-run"><?php echo stridebr_sport_icon_html('corrida', 'activity-toolbar-sport-icon'); ?><span><?php echo stridebr_e(stridebr_t('activity.quick_run')); ?></span></a>
                <a href="/user/gravar-atividade.php" class="activity-toolbar-link activity-toolbar-essential"><?php echo stridebr_e(stridebr_t('activity.record_gps')); ?></a>
                <details class="activity-toolbar-tools">
                    <summary class="activity-toolbar-link" aria-label="<?php echo stridebr_e(stridebr_t('common.tools')); ?>"><?php echo stridebr_e(stridebr_t('common.tools')); ?> <span aria-hidden="true">⌄</span></summary>
                    <div class="activity-toolbar-tools-menu">
                        <a href="/user/importar-exportar.php" data-activity-tool="exchange"><?php echo stridebr_e(stridebr_t('activity.import_export')); ?></a>
                        <a href="/user/equipamentos.php"><?php echo stridebr_e(stridebr_t('activity.equipment')); ?></a>
                    </div>
                </details>
                <button type="button" class="activity-primary-action" data-toggle-activity-form>+ <?php echo stridebr_e(stridebr_t('activity.log')); ?></button>
            </div>
        </header>

        <?php foreach ($flashes as $flash): ?>
            <div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?> activity-alert"><?php echo stridebr_e($flash['message'] ?? ''); ?></div>
        <?php endforeach; ?>
        <?php $activityUndo = is_array($_SESSION['activity_undo'] ?? null) && (int) ($_SESSION['activity_undo']['expires'] ?? 0) >= time() ? $_SESSION['activity_undo'] : null; ?>
        <?php if ($activityUndo !== null): ?>
            <div class="ui-server-undo" role="status"><span><?php echo stridebr_e(stridebr_t('activity.removed')); ?></span><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="restore_activity"><input type="hidden" name="undo_token" value="<?php echo stridebr_e((string) ($activityUndo['token'] ?? '')); ?>"><button type="submit"><?php echo stridebr_e(stridebr_t('common.undo')); ?></button></form></div>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger activity-alert"><?php echo stridebr_e($error); ?></div>
        <?php endforeach; ?>

        <section id="nova-atividade" class="activity-editor-shell<?php echo ($errors || $repeatRecord || isset($_GET['new']) || isset($_GET['registrar'])) ? ' is-open' : ''; ?>" data-activity-form>
            <button type="button" class="activity-editor-backdrop" data-close-activity-form aria-label="<?php echo stridebr_e(stridebr_t('activity.close_registration')); ?>"></button>
            <form method="POST" class="activity-editor" id="activity-form" autocomplete="off" data-draft-key="activity-new">
                <?php echo stridebr_csrf_field(); ?>
                <?php if ($repeatRecord): ?><div class="activity-repeat-banner"><div><strong><?php echo stridebr_e(stridebr_t('activity.repeating')); ?> <?php echo stridebr_e((string) ($repeatRecord['titulo'] ?: $repeatRecord['modalidade_nome'])); ?></strong><span><?php echo stridebr_e(stridebr_t('activity.repeat_help')); ?></span></div><a href="/user/atividades.php"><?php echo stridebr_e(stridebr_t('activity.start_fresh')); ?></a></div><?php endif; ?>
                <div class="activity-editor-heading">
                    <div>
                        <h2><?php echo stridebr_e(stridebr_t($repeatRecord ? 'activity.repeat_activity' : 'activity.log')); ?></h2>
                    </div>
                    <button type="button" class="activity-icon-button" data-close-activity-form aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button>
                </div>

                <?php $activityEditorTitle = $formTitle; require dirname(__DIR__, 2) . '/src/layout/activity_smart_title.php'; ?>

                <div class="activity-context-grid">
                    <div class="input-field activity-sport-field">
                        <label for="activity-sport-search"><?php echo stridebr_e(stridebr_t('activity.sport_activity')); ?></label>
                        <div class="sport-combobox" data-sport-combobox>
                            <button type="button" class="sport-combobox-trigger" data-sport-trigger aria-haspopup="listbox" aria-expanded="false">
                                <span data-sport-current class="sport-current"><?php echo stridebr_e(stridebr_t('activity.choose_sport')); ?></span>
                                <span aria-hidden="true">⌄</span>
                            </button>
                            <div class="sport-combobox-popover" data-sport-popover hidden>
                                <div class="sport-search-row">
                                    <input type="search" id="activity-sport-search" placeholder="<?php echo stridebr_e(stridebr_t('activity.search_sport_placeholder')); ?>" data-sport-search spellcheck="false">
                                </div>
                                <div class="sport-options" role="listbox" data-sport-options>
                                    <?php if ($favoritas): ?>
                                        <div class="sport-favorites-quick" data-sport-quick aria-label="<?php echo stridebr_e(stridebr_t('activity.favorites_aria')); ?>">
                                            <div class="sport-group-title"><?php echo stridebr_e(stridebr_t('activity.favorites')); ?></div>
                                            <div class="sport-favorites-chips">
                                                <?php foreach ($favoritas as $modalidade): ?>
                                                    <button
                                                        type="button"
                                                        class="sport-option sport-favorite-quick"
                                                        role="option"
                                                        data-sport-option
                                                        data-sport-id="<?php echo stridebr_e($modalidade['idmodalidade']); ?>"
                                                        data-sport-name="<?php echo stridebr_e(stridebr_sport_name((string) $modalidade['slug'], (string) $modalidade['nome'])); ?>"
                                                        data-sport-slug="<?php echo stridebr_e($modalidade['slug']); ?>"
                                                        data-sport-icon-id="<?php echo stridebr_e(stridebr_sport_icon_id((string) $modalidade['slug'])); ?>"
                                                        data-sport-favorite="1"
                                                        data-sport-family="<?php echo stridebr_e(sportCatalogFamilyKey((string) ($modalidade['familia_hub'] ?? ''), (string) ($modalidade['categoria'] ?? ''), (string) ($modalidade['slug'] ?? ''))); ?>"
                                                    ><span class="sport-option-icon"><?php echo stridebr_sport_icon_html((string) $modalidade['slug']); ?></span><span><?php echo stridebr_e(stridebr_sport_name((string) $modalidade['slug'], (string) $modalidade['nome'])); ?></span></button>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($recentes): ?>
                                        <div class="sport-favorites-quick" data-sport-quick aria-label="<?php echo stridebr_e(stridebr_t('activity.recents_aria')); ?>">
                                            <div class="sport-group-title"><?php echo stridebr_e(stridebr_t('activity.recents')); ?></div>
                                            <div class="sport-favorites-chips">
                                                <?php foreach ($recentes as $modalidade): ?>
                                                    <button
                                                        type="button"
                                                        class="sport-option sport-favorite-quick"
                                                        role="option"
                                                        data-sport-option
                                                        data-sport-id="<?php echo stridebr_e($modalidade['idmodalidade']); ?>"
                                                        data-sport-name="<?php echo stridebr_e(stridebr_sport_name((string) $modalidade['slug'], (string) $modalidade['nome'])); ?>"
                                                        data-sport-slug="<?php echo stridebr_e($modalidade['slug']); ?>"
                                                        data-sport-icon-id="<?php echo stridebr_e(stridebr_sport_icon_id((string) $modalidade['slug'])); ?>"
                                                        data-sport-favorite="0"
                                                        data-sport-family="<?php echo stridebr_e(sportCatalogFamilyKey((string) ($modalidade['familia_hub'] ?? ''), (string) ($modalidade['categoria'] ?? ''), (string) ($modalidade['slug'] ?? ''))); ?>"
                                                    ><span class="sport-option-icon"><?php echo stridebr_sport_icon_html((string) $modalidade['slug']); ?></span><span><?php echo stridebr_e(stridebr_sport_name((string) $modalidade['slug'], (string) $modalidade['nome'])); ?></span></button>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php echo sportPickerRenderFamilyBrowser($catalogo, $firstModalidade, true); ?>
                                </div>
                            </div>
                        </div>
                        <select id="modalidade" name="idmodalidade" required class="activity-native-select" tabindex="-1" aria-hidden="true">
                            <?php foreach ($catalogo as $modalidade): ?>
                                <option value="<?php echo stridebr_e($modalidade['idmodalidade']); ?>" data-slug="<?php echo stridebr_e($modalidade['slug']); ?>" data-family="<?php echo stridebr_e(sportCatalogFamilyKey((string) ($modalidade['familia_hub'] ?? ''), (string) ($modalidade['categoria'] ?? ''), (string) ($modalidade['slug'] ?? ''))); ?>" data-permite-rota="<?php echo $modalidade['permite_rota'] ? '1' : '0'; ?>"<?php echo $modalidade['idmodalidade'] === $firstModalidade ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_sport_name((string) $modalidade['slug'], (string) $modalidade['nome'])); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="input-field activity-model-field" data-model-field>
                        <label for="modelo"><?php echo stridebr_e(stridebr_t('activity.format')); ?></label>
                        <select id="modelo" name="idmodelo" required>
                            <?php foreach ($catalogo as $modalidade): ?>
                                <?php foreach ($modalidade['modelos'] as $modelo): ?>
                                    <option value="<?php echo stridebr_e($modelo['idmodelo']); ?>" data-modalidade="<?php echo stridebr_e($modalidade['idmodalidade']); ?>"<?php echo $modelo['idmodelo'] === $firstModelo ? ' selected' : ''; ?>><?php echo stridebr_e($modelo['nome']); ?><?php echo $modelo['versao'] > 1 ? ' v' . (int) $modelo['versao'] : ''; ?></option>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="input-field activity-workout-field" data-workout-field hidden>
                        <label for="idtreino_cronograma"><?php echo stridebr_e(stridebr_t('activity.workout_routine')); ?> <span class="field-hint"><?php echo stridebr_e(stridebr_t('common.optional')); ?></span></label>
                        <select id="idtreino_cronograma" name="idtreino_cronograma" data-workout-select data-selected-workout="<?php echo stridebr_e($formWorkout); ?>">
                            <option value=""><?php echo stridebr_e(stridebr_t('activity.no_linked_workout')); ?></option>
                            <?php $ultimoCronograma = null; ?>
                            <?php foreach ($treinosUsuario as $treinoUsuario): ?>
                                <?php if ($ultimoCronograma !== $treinoUsuario['cronograma_nome']): ?>
                                    <?php if ($ultimoCronograma !== null): ?></optgroup><?php endif; ?>
                                    <optgroup label="<?php echo stridebr_e((string) $treinoUsuario['cronograma_nome']); ?>">
                                    <?php $ultimoCronograma = $treinoUsuario['cronograma_nome']; ?>
                                <?php endif; ?>
                                <?php $treinoRotulo = implode(' · ', array_filter([(string) ($treinoUsuario['codigo'] ?? ''), (string) ($treinoUsuario['foco'] ?? '')])); ?>
                                <option value="<?php echo stridebr_e((string) $treinoUsuario['idtreino']); ?>" data-workout-title="<?php echo stridebr_e((string) $treinoUsuario['titulo']); ?>" data-workout-code="<?php echo stridebr_e((string) ($treinoUsuario['codigo'] ?? '')); ?>" data-workout-focus="<?php echo stridebr_e((string) ($treinoUsuario['foco'] ?? '')); ?>"<?php echo $formWorkout === (string) $treinoUsuario['idtreino'] ? ' selected' : ''; ?>><?php echo $treinoRotulo !== '' ? stridebr_e($treinoRotulo) . ' — ' : ''; ?><?php echo stridebr_e((string) $treinoUsuario['titulo']); ?></option>
                            <?php endforeach; ?>
                            <?php if ($ultimoCronograma !== null): ?></optgroup><?php endif; ?>
                        </select>
                        <small><?php echo stridebr_e(stridebr_t('activity.link_workout_help')); ?></small>
                    </div>

                    <div class="input-field activity-date-field">
                        <label for="data"><?php echo stridebr_e(stridebr_t('common.date')); ?></label>
                        <input type="date" id="data" name="data" value="<?php echo stridebr_e($formDate); ?>" required>
                    </div>

                    <div class="input-field activity-time-field">
                        <label for="hora_h"><?php echo stridebr_e(stridebr_t('common.time')); ?></label>
                        <div class="clock-segments" data-clock-field>
                            <input type="text" id="hora_h" inputmode="numeric" maxlength="2" value="<?php echo stridebr_e(substr($formHour, 0, 2)); ?>" data-clock-hours aria-label="Horas">
                            <span aria-hidden="true">:</span>
                            <input type="text" inputmode="numeric" maxlength="2" value="<?php echo stridebr_e(substr($formHour, 3, 2)); ?>" data-clock-minutes aria-label="Minutos">
                            <button type="button" class="time-now-button" data-time-now><?php echo stridebr_e(stridebr_t('common.now')); ?></button>
                            <button type="button" class="time-quick-toggle" data-time-quick-toggle aria-label="<?php echo stridebr_e(stridebr_t('activity.quick_times')); ?>">⌄</button>
                            <div class="time-quick-menu" data-time-quick-menu hidden></div>
                            <input type="hidden" id="hora" name="hora" value="<?php echo stridebr_e($formHour); ?>" data-clock-value>
                        </div>
                    </div>
                </div>

                <div class="activity-model-panels-host" data-activity-model-panels-host data-details-loaded="<?php echo $editorDetailsLoaded ? '1' : '0'; ?>">
                    <?php if ($editorDetailsLoaded): ?>
                        <?php require dirname(__DIR__, 2) . '/src/layout/activity_model_panels.php'; ?>
                    <?php else: ?>
                        <div class="activity-editor-inline-loading" data-activity-editor-loading><?php echo stridebr_e(stridebr_t('activity.fields_load_help')); ?></div>
                    <?php endif; ?>
                </div>
                <?php echo atividadeForcaRenderEditor($formStrengthExercises, $exerciciosBiblioteca); ?>
                <?php
                $activityRouteAllowed = false;
                foreach ($catalogo as $modalidadeCatalogo) {
                    if ((string) $modalidadeCatalogo['idmodalidade'] === $formModalidade) {
                        $activityRouteAllowed = !empty($modalidadeCatalogo['permite_rota']);
                        break;
                    }
                }
                $activityRouteValue = $formRoute;
                $activityRouteMode = $_POST['route_editor_mode'] ?? 'free';
                $activityRouteLaps = $_POST['route_editor_laps'] ?? 1;
                $activityRouteBase = $_POST['route_editor_base'] ?? '';
                $activityRouteCompact = true;
                require dirname(__DIR__, 2) . '/src/layout/activity_route_editor.php';
                ?>

                <?php
                $activityRoutePrivacyHasRoute = $formRoute !== '';
                $activityRoutePrivacyStart = $formHideRouteStart;
                $activityRoutePrivacyEnd = $formHideRouteEnd;
                require dirname(__DIR__, 2) . '/src/layout/activity_route_privacy.php';
                ?>

                <?php
                $activityEditorEffort = $formEffort;
                $activityEditorEquipment = $equipamentos;
                $activityEditorSelectedEquipment = $formEquipment;
                $activityEditorEquipmentLoaded = $editorDetailsLoaded;
                $activityEditorObservations = $formObservations;
                $activityEditorVisibility = $formVisibility;
                require dirname(__DIR__, 2) . '/src/layout/activity_log_details.php';
                ?>

                <div class="activity-form-actions">
                    <button type="button" class="activity-secondary-button" data-close-activity-form><?php echo stridebr_e(stridebr_t('common.cancel')); ?></button>
                    <button type="submit" class="activity-primary-action"><?php echo stridebr_e(stridebr_t('activity.save')); ?></button>
                </div>
            </form>
        </section>

        <div class="activity-history-workspace" data-activity-history-workspace>
        <div class="activity-history-left">
        <section class="activity-history-summary" data-history-summary aria-label="<?php echo stridebr_e(stridebr_t('activity.history_summary_aria')); ?>">
            <article><span><?php echo stridebr_e(stridebr_t('activity.summary.activities')); ?></span><strong data-summary-activities><?php echo $initialHistorySummary !== null ? (int) ($initialHistorySummary['atividades'] ?? 0) : '—'; ?></strong><small data-summary-period><?php echo stridebr_e((string) ($initialHistorySummary['periodo'] ?? stridebr_t('activity.summary.last_7_days'))); ?></small></article>
            <article><span><?php echo stridebr_e(stridebr_t('activity.summary.time')); ?></span><strong data-summary-time><?php echo stridebr_e((string) ($initialHistorySummary['tempo'] ?? '—')); ?></strong><small><?php echo stridebr_e(stridebr_t('activity.summary.logged')); ?></small></article>
            <article><span><?php echo stridebr_e(stridebr_t('activity.summary.distance')); ?></span><strong data-summary-distance><?php echo stridebr_e((string) ($initialHistorySummary['distancia'] ?? '—')); ?></strong><small><?php echo stridebr_e(stridebr_t('activity.summary.accumulated')); ?></small></article>
            <article><span><?php echo stridebr_e(stridebr_t('activity.summary.elevation')); ?></span><strong data-summary-elevation><?php echo stridebr_e((string) ($initialHistorySummary['elevacao'] ?? '—')); ?></strong><small><?php echo stridebr_e(stridebr_t('activity.summary.accumulated')); ?></small></article>
        </section>
        <section class="activity-history" id="historico" data-activity-history data-initial-state="<?php echo stridebr_e($initialHistoryState); ?>" data-initial-cursor="<?php echo stridebr_e($initialHistoryCursor); ?>" data-initial-total="<?php echo $initialHistoryTotal; ?>">
            <div class="activity-history-toolbar">
                <div>
                    <h2><?php echo stridebr_e(stridebr_t('activity.history')); ?></h2>
                    <span><?php echo stridebr_e(stridebr_t('activity.recent_first')); ?></span>
                </div>
                <div class="activity-history-filters">
                    <input type="search" placeholder="<?php echo stridebr_e(stridebr_t('activity.search')); ?>" data-history-search autocomplete="off">
                    <?php echo sportPickerRenderSelect($catalogo, ['name' => 'history_sport', 'value_key' => 'slug', 'empty_label' => stridebr_t('activity.all_sports'), 'native_attributes' => ['data-history-sport' => true]]); ?>
                    <button type="button" class="activity-secondary-button activity-bulk-toggle" data-bulk-toggle><?php echo stridebr_e(stridebr_t('activity.select')); ?></button>
                </div>
            </div>
            <form class="activity-bulk-bar" data-bulk-bar hidden>
                <?php echo stridebr_csrf_field(); ?>
                <div class="activity-bulk-count"><strong data-bulk-count><?php echo stridebr_e(stridebr_t('activity.bulk_selected.none')); ?></strong><button type="button" data-bulk-select-visible><?php echo stridebr_e(stridebr_t('activity.bulk_select_loaded')); ?></button></div>
                <div class="activity-bulk-sport-field"><span><?php echo stridebr_e(stridebr_t('activity.modality')); ?></span><?php echo sportPickerRenderSelect($catalogo, ['name' => 'idmodalidade', 'empty_label' => stridebr_t('activity.keep_value'), 'native_attributes' => ['data-bulk-sport' => true]]); ?></div>
                <label><?php echo stridebr_e(stridebr_t('common.duration')); ?> <span class="activity-bulk-duration" data-bulk-duration-control><select name="duracao_modo" data-bulk-duration-mode><option value="keep"><?php echo stridebr_e(stridebr_t('activity.keep_value')); ?></option><option value="set"><?php echo stridebr_e(stridebr_t('activity.set_value')); ?></option><option value="clear"><?php echo stridebr_e(stridebr_t('common.clear')); ?></option></select><span data-bulk-duration-value hidden><input type="number" name="duracao_minutos" min="1" max="1440" inputmode="numeric" placeholder="120"><small>min</small></span></span></label>
                <label><?php echo stridebr_e(stridebr_t('activity.visibility')); ?><select name="visibilidade"><option value=""><?php echo stridebr_e(stridebr_t('activity.keep_value')); ?></option><option value="privado"><?php echo stridebr_e(stridebr_t('activity.only_me')); ?></option><option value="amigos"><?php echo stridebr_e(stridebr_t('common.friends')); ?></option><option value="publico"><?php echo stridebr_e(stridebr_t('common.public')); ?></option></select></label>
                <div class="activity-bulk-actions"><button type="submit" class="activity-primary-action"><?php echo stridebr_e(stridebr_t('common.apply')); ?></button><button type="button" class="activity-secondary-button is-danger" data-bulk-delete><?php echo stridebr_e(stridebr_t('activity.bulk_delete')); ?></button><button type="button" class="activity-secondary-button" data-bulk-cancel><?php echo stridebr_e(stridebr_t('common.cancel')); ?></button></div>
            </form>
            <div class="activity-history-skeleton" data-history-skeleton aria-label="<?php echo stridebr_e(stridebr_t('activity.loading_history_aria')); ?>" hidden>
                <?php for ($i = 0; $i < 5; $i++): ?>
                    <div class="activity-history-skeleton-row"><span></span><i></i><div><b></b><em></em></div><small></small></div>
                <?php endfor; ?>
            </div>
            <div class="activity-list" data-activity-list<?php echo $initialHistoryState === 'ready' ? '' : ' hidden'; ?>>
                <?php foreach ($initialHistoryItems as $initialHistoryItem): ?>
                    <?php echo atividadeHistoricoLinhaHtml($initialHistoryItem); ?>
                <?php endforeach; ?>
            </div>
            <div class="activity-empty-state" data-history-empty<?php echo $initialHistoryState === 'empty' ? '' : ' hidden'; ?>>
                <strong><?php echo stridebr_e(stridebr_t('activity.none_found')); ?></strong>
                <span data-history-empty-text><?php echo stridebr_e(stridebr_t('activity.first_help')); ?></span>
                <div class="activity-empty-actions"><button type="button" class="activity-primary-action" data-toggle-activity-form><?php echo stridebr_e(stridebr_t('activity.log')); ?></button><a class="activity-secondary-button" href="/user/gravar-atividade.php"><?php echo stridebr_e(stridebr_t('activity.record_gps')); ?></a></div>
            </div>
            <div class="activity-history-error" data-history-error<?php echo $initialHistoryState === 'error' ? '' : ' hidden'; ?>>
                <strong><?php echo stridebr_e(stridebr_t('activity.load_history_error')); ?></strong>
                <span><?php echo stridebr_e(stridebr_t('activity.load_history_error_help')); ?></span>
                <button type="button" class="activity-secondary-button" data-history-retry><?php echo stridebr_e(stridebr_t('activity.try_again')); ?></button>
            </div>
            <div class="activity-history-more" data-history-more<?php echo $initialHistoryState === 'ready' && $initialHistoryCursor !== '' ? '' : ' hidden'; ?>>
                <button type="button" class="activity-secondary-button" data-history-load-more><?php echo stridebr_e(stridebr_t('activity.load_more')); ?></button>
                <span data-history-count><?php echo $initialHistoryState === 'ready' ? $initialHistoryTotal . ' atividade' . ($initialHistoryTotal === 1 ? ' carregada.' : 's carregadas.') : ''; ?></span>
            </div>
        </section>
        </div>
        <aside class="activity-detail-placeholder" data-detail-desktop-placeholder aria-hidden="true">
            <div>
                <strong><?php echo stridebr_e(stridebr_t('activity.detail_title')); ?></strong>
                <span><?php echo stridebr_e(stridebr_t('activity.detail_empty')); ?></span>
            </div>
        </aside>
        <div class="activity-detail-drawer" data-activity-detail-drawer hidden>
            <button type="button" class="activity-detail-backdrop" data-close-activity-detail aria-label="<?php echo stridebr_e(stridebr_t('activity.close_details')); ?>"></button>
            <section class="activity-detail-panel" role="dialog" aria-modal="true" aria-labelledby="activity-detail-title" data-activity-detail-panel>
                <header>
                    <div><div class="activity-detail-kicker"><span class="activity-detail-kicker-main"><span data-detail-sport><?php echo stridebr_e(stridebr_t('common.activity')); ?></span><span class="activity-detail-visibility" data-detail-visibility></span></span></div><h2 id="activity-detail-title" data-detail-title><?php echo stridebr_e(stridebr_t('common.loading')); ?></h2><p data-detail-date></p></div>
                    <div class="activity-detail-header-actions"><a class="activity-secondary-button activity-detail-compare" data-detail-compare data-activity-tool="compare" href="/user/comparar-atividades.php"><span aria-hidden="true">↔</span><?php echo stridebr_e(stridebr_t('activity.compare')); ?></a><button type="button" class="activity-secondary-button activity-detail-expand" data-expand-activity-detail><?php echo stridebr_e(stridebr_t('activity.detail.expand')); ?></button><button type="button" data-close-activity-detail aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button></div>
                </header>
                <div class="activity-detail-loading" data-detail-loading hidden aria-label="<?php echo stridebr_e(stridebr_t('activity.loading_details')); ?>">
                    <div class="activity-detail-skeleton-head"><span></span><strong></strong></div>
                    <div class="activity-detail-skeleton-metrics"><i></i><i></i><i></i></div>
                    <div class="activity-detail-skeleton-block"></div>
                </div>
                <div data-detail-content hidden></div>
            </section>
        </div>
        </div>
        <div class="activity-context-menu" data-activity-context-menu role="menu" aria-label="<?php echo stridebr_e(stridebr_t('activity.actions_aria')); ?>" hidden>
            <button type="button" role="menuitem" data-context-open><?php echo stridebr_e(stridebr_t('common.open')); ?></button>
            <a role="menuitem" data-context-edit href="#"><?php echo stridebr_e(stridebr_t('activity.edit')); ?></a>
            <button type="button" role="menuitem" class="is-danger" data-context-delete><?php echo stridebr_e(stridebr_t('activity.bulk_delete')); ?></button>
        </div>
    </main>
</div>
<div class="activity-edit-modal" data-activity-edit-modal hidden>
    <button type="button" class="activity-edit-modal-backdrop" data-close-activity-edit aria-label="<?php echo stridebr_e(stridebr_t('activity.close_edit')); ?>"></button>
    <section class="activity-edit-modal-panel" role="dialog" aria-modal="true" aria-labelledby="activity-edit-modal-title">
        <header class="activity-edit-modal-header">
            <div><span><?php echo stridebr_e(stridebr_t('activity.summary.activities')); ?></span><h2 id="activity-edit-modal-title"><?php echo stridebr_e(stridebr_t('activity.edit_activity')); ?></h2></div>
            <button type="button" data-close-activity-edit aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button>
        </header>
        <div class="activity-edit-modal-body">
            <div class="activity-edit-modal-loading" data-activity-edit-loading><span></span><strong><?php echo stridebr_e(stridebr_t('activity.loading_activity')); ?></strong></div>
            <div class="activity-edit-modal-error"><strong><?php echo stridebr_e(stridebr_t('activity.edit_load_error')); ?></strong><div><button type="button" class="activity-secondary-button" data-activity-edit-retry><?php echo stridebr_e(stridebr_t('common.try_again')); ?></button><a class="activity-secondary-button" data-activity-edit-new-page href="#" target="_blank" rel="noopener"><?php echo stridebr_e(stridebr_t('activity.open_new_page')); ?></a></div></div>
            <iframe title="<?php echo stridebr_e(stridebr_t('activity.edit_activity')); ?>" data-activity-edit-frame></iframe>
        </div>
    </section>
</div>

<div class="activity-tool-overlay" data-activity-tool-overlay hidden>
    <button type="button" class="activity-tool-backdrop" data-close-activity-tool aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>"></button>
    <section class="activity-tool-panel" role="dialog" aria-modal="true" aria-labelledby="activity-tool-title">
        <header class="activity-tool-panel-header"><div><span><?php echo stridebr_e(stridebr_t('activity.summary.activities')); ?></span><h2 id="activity-tool-title" data-activity-tool-title><?php echo stridebr_e(stridebr_t('activity.tool')); ?></h2></div><button type="button" data-close-activity-tool aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button></header>
        <div class="activity-tool-panel-content" data-activity-tool-content><div class="activity-tool-loading"><?php echo stridebr_e(stridebr_t('common.loading')); ?></div></div>
    </section>
</div>

<div class="activity-post-save-modal" data-post-save-share hidden>
    <button type="button" class="activity-post-save-backdrop" data-post-save-close aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>"></button>
    <section class="activity-post-save-panel" role="dialog" aria-modal="true" aria-labelledby="post-save-share-title">
        <header>
            <div><span><?php echo stridebr_e(stridebr_t('activity.saved')); ?></span><h2 id="post-save-share-title"><?php echo stridebr_e(stridebr_t('activity.share_question')); ?></h2></div>
            <button type="button" data-post-save-close aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button>
        </header>
        <div class="activity-post-save-body">
            <div class="activity-post-save-preview">
                <canvas width="1080" height="1920" data-post-save-canvas aria-label="<?php echo stridebr_e(stridebr_t('activity.default_share_preview_aria')); ?>"></canvas>
            </div>
            <div class="activity-post-save-copy">
                <strong data-post-save-title><?php echo stridebr_e(stridebr_t('common.activity')); ?></strong>
                <span data-post-save-summary></span>
                <p><?php echo stridebr_e(stridebr_t('activity.share_ready_help')); ?></p>
                <span class="activity-post-save-status" data-post-save-status role="status" aria-live="polite"></span>
            </div>
        </div>
        <footer>
            <button type="button" class="activity-danger-button activity-post-save-delete" data-post-save-delete><?php echo stridebr_e(stridebr_t('activity.delete_activity')); ?></button>
            <button type="button" class="activity-primary-action" data-post-save-native><?php echo stridebr_e(stridebr_t('activity.share')); ?></button>
            <button type="button" class="activity-secondary-button" data-post-save-edit><?php echo stridebr_e(stridebr_t('activity.edit_share')); ?></button>
            <button type="button" class="activity-secondary-button" data-post-save-close><?php echo stridebr_e(stridebr_t('common.done')); ?></button>
        </footer>
    </section>
</div>

<div class="activity-share-modal" data-share-modal hidden>
    <button type="button" class="activity-share-backdrop" data-close-share aria-label="<?php echo stridebr_e(stridebr_t('activity.close_share')); ?>"></button>

    <div class="activity-share-workspace" role="dialog" aria-modal="true" aria-labelledby="share-title">
        <aside class="activity-share-preview-window activity-share-preview-shell" data-share-preview-shell aria-label="<?php echo stridebr_e(stridebr_t('activity.card_preview_aria')); ?>">
            <div class="activity-share-preview-heading">
                <strong><?php echo stridebr_e(stridebr_t('activity.card_preview')); ?></strong>
                <small data-share-preview-format><?php echo stridebr_e(stridebr_t('activity.share.format_story')); ?> · 1080 × 1920</small>
            </div>
            <div class="activity-share-preview-layout">
                <div class="activity-share-preview-stage">
                    <canvas data-share-canvas width="1080" height="1920" aria-label="<?php echo stridebr_e(stridebr_t('activity.share_preview_aria')); ?>"></canvas>
                </div>
                <fieldset class="activity-share-format-options activity-share-format-rail">
                    <legend><?php echo stridebr_e(stridebr_t('activity.format')); ?></legend>
                    <label><input type="radio" name="share-format" value="story" data-share-format checked><span><i class="share-format-thumb is-story" aria-hidden="true"></i><b><strong><?php echo stridebr_e(stridebr_t('activity.share.format_story')); ?></strong><small>9:16</small></b></span></label>
                    <label><input type="radio" name="share-format" value="portrait" data-share-format><span><i class="share-format-thumb is-portrait" aria-hidden="true"></i><b><strong><?php echo stridebr_e(stridebr_t('activity.share.format_portrait')); ?></strong><small>4:5</small></b></span></label>
                    <label><input type="radio" name="share-format" value="square" data-share-format><span><i class="share-format-thumb is-square" aria-hidden="true"></i><b><strong><?php echo stridebr_e(stridebr_t('activity.share.format_square')); ?></strong><small>1:1</small></b></span></label>
                </fieldset>
            </div>
        </aside>

        <section class="activity-share-panel">
            <header>
                <div><span><?php echo stridebr_e(stridebr_t('activity.share')); ?></span><h2 id="share-title"><?php echo stridebr_e(stridebr_t('activity.share_title')); ?></h2></div>
                <button type="button" data-close-share aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button>
            </header>

            <div class="activity-share-body">
                <div class="activity-share-controls">
                    <section class="activity-share-scope-switch" data-share-master-switch hidden>
                        <div class="activity-share-block-heading"><div><strong><?php echo stridebr_e(stridebr_t('activity.share.scope')); ?></strong></div></div>
                        <div class="activity-share-scope-options" role="group" aria-label="<?php echo stridebr_e(stridebr_t('activity.share.scope')); ?>">
                            <button type="button" data-share-scope="session" aria-pressed="true"><?php echo stridebr_e(stridebr_t('activity.share.scope_session')); ?></button>
                            <button type="button" data-share-scope="single_segment" aria-pressed="false"><?php echo stridebr_e(stridebr_t('activity.share.scope_single_segment')); ?></button>
                            <button type="button" data-share-scope="multiple_segments" aria-pressed="false"><?php echo stridebr_e(stridebr_t('activity.share.scope_multiple_segments')); ?></button>
                        </div>
                    </section>

                    <section class="activity-share-choice-block activity-share-single-segment-picker" data-share-single-segment-picker hidden>
                        <div class="activity-share-block-heading"><div><strong><?php echo stridebr_e(stridebr_t('activity.share.choose_segment')); ?></strong></div></div>
                        <div class="activity-share-single-segment-list" data-share-single-segment-list></div>
                    </section>

                    <section class="activity-share-multiple-summary" data-share-multiple-summary hidden>
                        <div><strong data-share-segment-selection-summary></strong><small><?php echo stridebr_e(stridebr_t('activity.share.multiple_segments_help')); ?></small></div>
                        <button type="button" class="activity-inline-action" data-share-edit-segments><?php echo stridebr_e(stridebr_t('activity.share.change_selection')); ?></button>
                    </section>

                    <section class="activity-share-choice-block activity-share-content-block" data-share-single-only>
                        <div class="activity-share-block-heading">
                            <div><strong><?php echo stridebr_e(stridebr_t('activity.share_content')); ?></strong></div>
                        </div>
                        <div class="activity-share-choice-grid is-content" data-share-content-grid></div>
                    </section>

                    <section class="activity-share-choice-block activity-share-background-block" data-share-background-block>
                        <div class="activity-share-block-heading">
                            <div><strong><?php echo stridebr_e(stridebr_t('activity.share.background')); ?></strong></div>
                        </div>
                        <div class="activity-share-choice-grid is-background activity-share-style-grid" data-share-style-grid></div>
                    </section>

                    <section class="activity-share-choice-block activity-share-session-layout-block" data-share-session-only hidden>
                        <div class="activity-share-block-heading">
                            <div><strong><?php echo stridebr_e(stridebr_t('activity.session_layout')); ?></strong><small><?php echo stridebr_e(stridebr_t('activity.session_layout_help')); ?></small></div>
                        </div>
                        <div class="activity-share-choice-grid is-session" data-share-session-layout-grid></div>
                    </section>

                    <section class="activity-share-content-options" data-share-content-options hidden aria-hidden="true">
                        <fieldset class="activity-share-mode-options">
                            <label><input type="radio" name="share-content-mode" value="activity" data-share-content-mode checked><span><?php echo stridebr_e(stridebr_t('activity.summary.activities')); ?></span></label>
                            <label><input type="radio" name="share-content-mode" value="segments" data-share-content-mode><span><?php echo stridebr_e(stridebr_t('activity.share.segments')); ?></span></label>
                        </fieldset>
                        <fieldset class="activity-share-segment-mode-options" data-share-segment-mode-options hidden>
                            <legend><?php echo stridebr_e(stridebr_t('activity.share.segments')); ?></legend>
                            <label><input type="radio" name="share-segment-mode" value="together" data-share-segment-mode checked><span><?php echo stridebr_e(stridebr_t('activity.all_together')); ?></span></label>
                            <label><input type="radio" name="share-segment-mode" value="separate" data-share-segment-mode><span><?php echo stridebr_e(stridebr_t('activity.share.separate')); ?></span></label>
                        </fieldset>
                    </section>

                    <section class="activity-share-quick-controls" aria-label="<?php echo stridebr_e(stridebr_t('activity.quick_adjustments')); ?>">
                        <input type="checkbox" data-share-show="route" checked hidden>

                        <fieldset class="activity-share-composition-options" data-share-composition-options>
                            <legend><?php echo stridebr_e(stridebr_t('activity.share.composition')); ?></legend>
                            <label><input type="radio" name="share-composition" value="standard" data-share-composition checked><span><i class="share-composition-thumb is-standard" aria-hidden="true"><b></b><b></b><b></b></i><strong><?php echo stridebr_e(stridebr_t('activity.share.composition_standard')); ?></strong></span></label>
                            <label><input type="radio" name="share-composition" value="compact" data-share-composition><span><i class="share-composition-thumb is-compact" aria-hidden="true"><b></b><b></b><b></b></i><strong><?php echo stridebr_e(stridebr_t('activity.share.composition_compact')); ?></strong></span></label>
                        </fieldset>

                        <fieldset class="activity-share-color-options" data-share-color-options>
                            <legend><?php echo stridebr_e(stridebr_t('activity.share.background_color')); ?></legend>
                            <label title="<?php echo stridebr_e(stridebr_t('activity.share.color_deep')); ?>"><input type="radio" name="share-color" value="deep" data-share-background-color checked><span><i class="share-color-swatch" data-share-color-swatch="deep"></i><b><?php echo stridebr_e(stridebr_t('activity.share.color_deep')); ?></b></span></label>
                            <label title="<?php echo stridebr_e(stridebr_t('activity.share.color_dark')); ?>"><input type="radio" name="share-color" value="dark" data-share-background-color><span><i class="share-color-swatch" data-share-color-swatch="dark"></i><b><?php echo stridebr_e(stridebr_t('activity.share.color_dark')); ?></b></span></label>
                        </fieldset>

                        <fieldset class="activity-share-map-style-options" data-share-map-style-options hidden>
                            <legend><?php echo stridebr_e(stridebr_t('activity.share.map_style')); ?></legend>
                            <label><input type="radio" name="share-map-style" value="street" data-share-map-style checked><span><?php echo stridebr_e(stridebr_t('activity.share.map_streets')); ?></span></label>
                            <label><input type="radio" name="share-map-style" value="satellite" data-share-map-style><span><?php echo stridebr_e(stridebr_t('activity.share.map_satellite')); ?></span></label>
                        </fieldset>
                    </section>

                    <button type="button" class="activity-share-mobile-photo" data-share-mobile-photo hidden><?php echo stridebr_e(stridebr_t('activity.share.choose_photo')); ?></button>
                    <button type="button" class="activity-share-mobile-customize" data-share-mobile-customize><span><?php echo stridebr_e(stridebr_t('home.customize')); ?></span><span aria-hidden="true">›</span></button>
                    <button type="button" class="activity-share-customize-backdrop" data-share-mobile-customize-close aria-label="<?php echo stridebr_e(stridebr_t('activity.close_customization')); ?>"></button>

                    <aside class="activity-share-sidebar" data-share-customize-sheet aria-label="<?php echo stridebr_e(stridebr_t('activity.customize_card')); ?>">
                        <div class="activity-share-mobile-sheet-head">
                            <button type="button" data-share-mobile-customize-close aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">‹</button>
                            <strong><?php echo stridebr_e(stridebr_t('activity.customize_card')); ?></strong>
                            <span aria-hidden="true"></span>
                        </div>

                        <section class="activity-share-group activity-share-visual-options-group">
                            <h3><?php echo stridebr_e(stridebr_t('settings.theme')); ?></h3>
                            <div class="activity-share-map-option" data-share-map-option hidden>
                                <label><span><strong><?php echo stridebr_e(stridebr_t('activity.share.show_map')); ?></strong><small><?php echo stridebr_e(stridebr_t('activity.share.show_map_help')); ?></small></span><input type="checkbox" data-share-map-toggle></label>
                            </div>

                            <div class="activity-share-route-scale">
                                <div><strong><?php echo stridebr_e(stridebr_t('activity.share.route_size')); ?></strong></div>
                                <label>
                                    <input type="range" min="50" max="200" step="5" value="100" data-share-route-scale>
                                    <output data-share-route-scale-value>100%</output>
                                </label>
                            </div>
                        </section>

                        <section class="activity-share-group activity-share-elements-group">
                            <h3><?php echo stridebr_e(stridebr_t('activity.card_elements')); ?></h3>
                            <div class="activity-share-visibility">
                                <label class="activity-share-heading-mode"><span><?php echo stridebr_e(stridebr_t('activity.share.show_title')); ?></span><input type="checkbox" data-share-heading-mode checked></label>
                                <label data-share-session-route-option hidden><span><?php echo stridebr_e(stridebr_t('activity.share.show_route')); ?></span><input type="checkbox" data-share-show="route" checked></label>
                                <label><span><?php echo stridebr_e(stridebr_t('common.date')); ?></span><input type="checkbox" data-share-show="date"></label>
                            </div>

                            <details class="activity-share-details" open>
                                <summary><?php echo stridebr_e(stridebr_t('activity.statistics')); ?> <small><?php echo stridebr_e(stridebr_t('activity.up_to_four')); ?></small></summary>
                                <div class="activity-share-metrics" data-share-metric-options></div>
                            </details>

                            <label class="activity-share-caption activity-share-caption-direct"><?php echo stridebr_e(stridebr_t('activity.share.short_caption')); ?> <input type="text" maxlength="60" placeholder="<?php echo stridebr_e(stridebr_t('common.optional')); ?>" data-share-caption></label>
                        </section>

                        <section class="activity-share-group activity-share-segments-group" data-share-segments-group hidden>
                            <details class="activity-share-details" data-share-segment-disclosure>
                                <summary><?php echo stridebr_e(stridebr_t('activity.share.choose_segments')); ?> <small data-share-segment-selection-inline></small></summary>
                                <div class="activity-share-segments-heading"><h3><?php echo stridebr_e(stridebr_t('activity.share.segments')); ?></h3><button type="button" class="activity-inline-action" data-share-select-all><?php echo stridebr_e(stridebr_t('activity.share.select_all')); ?></button></div>
                                <div class="activity-share-segment-list" data-share-segment-list></div>
                            </details>
                            <label class="activity-share-segment-preview-picker" data-share-segment-preview-picker hidden>
                                <span><?php echo stridebr_e(stridebr_t('activity.individual_preview')); ?></span>
                                <select data-share-segment-preview></select>
                            </label>
                        </section>

                        <section class="activity-share-group activity-share-comparison-controls" data-share-comparison-controls hidden>
                            <h3><?php echo stridebr_e(stridebr_t('activity.share.comparison')); ?></h3>
                            <div class="activity-share-comparison-grid">
                                <label><span><?php echo stridebr_e(stridebr_t('activity.share.compare_by')); ?></span><select data-share-comparison-metric></select></label>
                                <label><span><?php echo stridebr_e(stridebr_t('activity.share.reference')); ?></span><select data-share-comparison-reference></select></label>
                            </div>
                        </section>

                        <section class="activity-share-group activity-share-comparison-controls" data-share-session-compact-controls hidden>
                            <h3><?php echo stridebr_e(stridebr_t('activity.share.composition_compact')); ?></h3>
                            <div class="activity-share-comparison-grid">
                                <label><span><?php echo stridebr_e(stridebr_t('activity.share.primary_metric')); ?></span><select data-share-session-compact-primary></select></label>
                                <label><span><?php echo stridebr_e(stridebr_t('activity.share.secondary_metric')); ?></span><select data-share-session-compact-secondary></select></label>
                            </div>
                        </section>

                        <section class="activity-share-group activity-share-photo-group" data-share-photo-field hidden>
                            <h3><?php echo stridebr_e(stridebr_t('activity.share.photo')); ?></h3>
                            <div class="activity-share-photo-actions">
                                <div class="activity-share-photo-preview" data-share-photo-preview><span data-share-photo-empty><?php echo stridebr_e(stridebr_t('activity.share.no_photo_short')); ?></span></div>
                                <div class="activity-share-photo-buttons">
                                    <button type="button" class="activity-secondary-button" data-share-open-camera><?php echo stridebr_e(stridebr_t('activity.share.take_photo')); ?></button>
                                    <label class="activity-secondary-button"><?php echo stridebr_e(stridebr_t('activity.share.choose_file')); ?><input type="file" accept="image/*" data-share-photo></label>
                                    <input type="file" accept="image/*" capture="environment" data-share-camera hidden>
                                    <small data-share-photo-name><?php echo stridebr_e(stridebr_t('activity.share.photo_help')); ?></small>
                                </div>
                            </div>
                        </section>


                    </aside>
                </div>
            </div>

            <p data-share-status></p>
            <footer>
                <details class="activity-share-more-menu">
                    <summary aria-label="<?php echo stridebr_e(stridebr_t('activity.more_options')); ?>" title="<?php echo stridebr_e(stridebr_t('activity.more_options')); ?>">•••</summary>
                    <div>
                        <button type="button" data-export-route-png hidden><?php echo stridebr_e(stridebr_t('activity.share.export_route_png')); ?></button>
                        <button type="button" data-reset-share><?php echo stridebr_e(stridebr_t('activity.reset_card')); ?></button>
                    </div>
                </details>
                <button type="button" class="activity-secondary-button" data-copy-share><?php echo stridebr_e(stridebr_t('activity.copy_card')); ?></button>
                <button type="button" class="activity-secondary-button" data-download-share><?php echo stridebr_e(stridebr_t('activity.download')); ?></button>
                <button type="button" class="activity-primary-action" data-native-share><?php echo stridebr_e(stridebr_t('activity.share')); ?></button>
            </footer>

            <div class="activity-share-route-export-sheet" data-route-export-sheet hidden>
                <button type="button" class="activity-share-route-export-backdrop" data-route-export-close aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>"></button>
                <section class="activity-share-route-export-dialog" role="dialog" aria-modal="true" aria-labelledby="share-route-export-title">
                    <header>
                        <div>
                            <strong id="share-route-export-title"><?php echo stridebr_e(stridebr_t('activity.share.route_png_title')); ?></strong>
                            <small><?php echo stridebr_e(stridebr_t('activity.share.route_png_help')); ?></small>
                        </div>
                        <button type="button" class="activity-icon-button" data-route-export-close aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button>
                    </header>
                    <div class="activity-share-route-export-preview">
                        <canvas width="640" height="640" data-route-export-preview aria-label="<?php echo stridebr_e(stridebr_t('activity.share.route_png_preview')); ?>"></canvas>
                    </div>
                    <div class="activity-share-route-export-controls">
                        <fieldset class="activity-share-route-export-colors">
                            <legend><?php echo stridebr_e(stridebr_t('activity.share.route_png_color')); ?></legend>
                            <label><input type="radio" name="route-export-color" value="#4f72df" checked data-route-export-color><span class="is-blue" title="<?php echo stridebr_e(stridebr_t('activity.share.route_color_blue')); ?>"></span></label>
                            <label><input type="radio" name="route-export-color" value="#ffffff" data-route-export-color><span class="is-white" title="<?php echo stridebr_e(stridebr_t('activity.share.route_color_white')); ?>"></span></label>
                            <label><input type="radio" name="route-export-color" value="#111827" data-route-export-color><span class="is-black" title="<?php echo stridebr_e(stridebr_t('activity.share.color_black')); ?>"></span></label>
                            <label><input type="radio" name="route-export-color" value="#e5484d" data-route-export-color><span class="is-red" title="<?php echo stridebr_e(stridebr_t('activity.share.route_color_red')); ?>"></span></label>
                            <label><input type="radio" name="route-export-color" value="#2e9b65" data-route-export-color><span class="is-green" title="<?php echo stridebr_e(stridebr_t('activity.share.route_color_green')); ?>"></span></label>
                            <label><input type="radio" name="route-export-color" value="#e5b94c" data-route-export-color><span class="is-yellow" title="<?php echo stridebr_e(stridebr_t('activity.share.route_color_yellow')); ?>"></span></label>
                            <label><input type="radio" name="route-export-color" value="#8b5cf6" data-route-export-color><span class="is-purple" title="<?php echo stridebr_e(stridebr_t('activity.share.route_color_purple')); ?>"></span></label>
                        </fieldset>
                        <label class="activity-share-route-export-width">
                            <span><strong><?php echo stridebr_e(stridebr_t('activity.share.route_png_thickness')); ?></strong><output data-route-export-width-value>100%</output></span>
                            <input type="range" min="55" max="180" step="5" value="100" data-route-export-width>
                        </label>
                    </div>
                    <footer>
                        <button type="button" class="activity-secondary-button" data-route-export-close><?php echo stridebr_e(stridebr_t('common.cancel')); ?></button>
                        <button type="button" class="activity-primary-action" data-route-export-confirm><?php echo stridebr_e(stridebr_t('activity.share.export_route_png_action')); ?></button>
                    </footer>
                </section>
            </div>

            <div class="activity-share-camera-sheet" data-share-camera-sheet hidden>
                <div class="activity-share-camera-backdrop" data-share-close-camera></div>
                <div class="activity-share-camera-dialog" role="dialog" aria-modal="true" aria-label="Capturar foto">
                    <header><strong><?php echo stridebr_e(stridebr_t('activity.take_photo')); ?></strong><button type="button" data-share-close-camera aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button></header>
                    <video autoplay playsinline muted data-share-camera-video></video>
                    <p data-share-camera-status><?php echo stridebr_e(stridebr_t('activity.camera_help')); ?></p>
                    <footer><button type="button" class="activity-secondary-button" data-share-close-camera><?php echo stridebr_e(stridebr_t('common.cancel')); ?></button><button type="button" class="activity-primary-action" data-share-capture-camera><?php echo stridebr_e(stridebr_t('activity.share.use_photo')); ?></button></footer>
                </div>
            </div>
        </section>
    </div>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/activity-exchange.js')); ?>"></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/activity-route-utils.js')); ?>"></script>
<?php echo stridebr_maps_runtime_script(); ?>
<?php echo atividadeContextoJsConfigScript(); ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/activity-sport-context.js')); ?>"></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/atividades.js')); ?>"></script>
</body>
</html>
