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
        stridebr_flash('success', 'Atividade restaurada.');
    } else {
        unset($_SESSION['activity_undo']);
        stridebr_flash('danger', 'Não foi possível restaurar a atividade.');
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
        stridebr_flash('info', 'A atividade usada como base não está mais disponível.');
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
    stridebr_flash('info', 'A atividade usada como base não está mais disponível.');
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
                throw new InvalidArgumentException('O treino selecionado não está disponível.');
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
        stridebr_flash('success', 'Atividade física registrada.');
        header('Location: /user/atividades.php?saved=' . rawurlencode((string) $savedActivityId));
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Não foi possível salvar a atividade.';
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
                <a href="/user/gravar-atividade.php?quick=corrida&autostart=1" class="activity-toolbar-link activity-toolbar-gps"><?php echo stridebr_sport_icon_html('corrida', 'activity-toolbar-sport-icon'); ?><span><?php echo stridebr_e(stridebr_t('activity.quick_run')); ?></span></a>
                <a href="/user/gravar-atividade.php" class="activity-toolbar-link"><?php echo stridebr_e(stridebr_t('activity.record_gps')); ?></a>
                <a href="/user/progresso.php" class="activity-toolbar-link"><?php echo stridebr_e(stridebr_t('nav.progress')); ?></a>
                <a href="/user/comparar-atividades.php" class="activity-toolbar-link" data-activity-tool="compare"><?php echo stridebr_e(stridebr_t('activity.compare')); ?></a>
                <a href="/user/importar-exportar.php" class="activity-toolbar-link" data-activity-tool="exchange"><?php echo stridebr_e(stridebr_t('activity.import_export')); ?></a>
                <a href="/user/equipamentos.php" class="activity-toolbar-link"><?php echo stridebr_e(stridebr_t('activity.equipment')); ?></a>
                <button type="button" class="activity-primary-action" data-toggle-activity-form>+ <?php echo stridebr_e(stridebr_t('activity.log')); ?></button>
            </div>
        </header>

        <?php foreach ($flashes as $flash): ?>
            <div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?> activity-alert"><?php echo stridebr_e($flash['message'] ?? ''); ?></div>
        <?php endforeach; ?>
        <?php $activityUndo = is_array($_SESSION['activity_undo'] ?? null) && (int) ($_SESSION['activity_undo']['expires'] ?? 0) >= time() ? $_SESSION['activity_undo'] : null; ?>
        <?php if ($activityUndo !== null): ?>
            <div class="ui-server-undo" role="status"><span>Atividade removida.</span><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="restore_activity"><input type="hidden" name="undo_token" value="<?php echo stridebr_e((string) ($activityUndo['token'] ?? '')); ?>"><button type="submit">Desfazer</button></form></div>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger activity-alert"><?php echo stridebr_e($error); ?></div>
        <?php endforeach; ?>

        <section id="nova-atividade" class="activity-editor-shell<?php echo ($errors || $repeatRecord || isset($_GET['new']) || isset($_GET['registrar'])) ? ' is-open' : ''; ?>" data-activity-form>
            <button type="button" class="activity-editor-backdrop" data-close-activity-form aria-label="Fechar registro"></button>
            <form method="POST" class="activity-editor" id="activity-form" autocomplete="off" data-draft-key="activity-new">
                <?php echo stridebr_csrf_field(); ?>
                <?php if ($repeatRecord): ?><div class="activity-repeat-banner"><div><strong>Repetindo <?php echo stridebr_e((string) ($repeatRecord['titulo'] ?: $repeatRecord['modalidade_nome'])); ?></strong><span>Dados, equipamentos e rota foram reaproveitados. Data, hora, esforço e observações começam como novos.</span></div><a href="/user/atividades.php">Começar do zero</a></div><?php endif; ?>
                <div class="activity-editor-heading">
                    <div>
                        <h2><?php echo $repeatRecord ? 'Repetir atividade física' : 'Registrar atividade física'; ?></h2>
                    </div>
                    <button type="button" class="activity-icon-button" data-close-activity-form aria-label="Fechar">×</button>
                </div>

                <section class="activity-smart-title activity-smart-title-top" data-activity-title-card>
                    <div class="activity-smart-title-copy">
                        <span>Atividade</span>
                        <strong data-activity-title-preview><?php echo stridebr_e($formTitle !== '' ? $formTitle : 'Título automático'); ?></strong>
                        <div class="activity-summary" data-activity-summary hidden>
                            <span data-summary-text></span>
                        </div>
                    </div>
                    <button type="button" class="activity-inline-action" data-edit-activity-title aria-expanded="false">Editar</button>
                    <div class="input-field activity-title-field" data-activity-title-editor hidden>
                        <label for="titulo">Título</label>
                        <input type="text" id="titulo" name="titulo" maxlength="255" value="<?php echo stridebr_e($formTitle); ?>" placeholder="Ex.: Corrida no parque">
                    </div>
                </section>

                <div class="activity-context-grid">
                    <div class="input-field activity-sport-field">
                        <label for="activity-sport-search">Esporte / atividade física</label>
                        <div class="sport-combobox" data-sport-combobox>
                            <button type="button" class="sport-combobox-trigger" data-sport-trigger aria-haspopup="listbox" aria-expanded="false">
                                <span data-sport-current class="sport-current">Escolher esporte</span>
                                <span aria-hidden="true">⌄</span>
                            </button>
                            <div class="sport-combobox-popover" data-sport-popover hidden>
                                <div class="sport-search-row">
                                    <input type="search" id="activity-sport-search" placeholder="Buscar esporte..." data-sport-search spellcheck="false">
                                </div>
                                <div class="sport-options" role="listbox" data-sport-options>
                                    <?php if ($favoritas): ?>
                                        <div class="sport-favorites-quick" data-sport-quick aria-label="Esportes favoritos">
                                            <div class="sport-group-title">Favoritos</div>
                                            <div class="sport-favorites-chips">
                                                <?php foreach ($favoritas as $modalidade): ?>
                                                    <button
                                                        type="button"
                                                        class="sport-option sport-favorite-quick"
                                                        role="option"
                                                        data-sport-option
                                                        data-sport-id="<?php echo stridebr_e($modalidade['idmodalidade']); ?>"
                                                        data-sport-name="<?php echo stridebr_e($modalidade['nome']); ?>"
                                                        data-sport-slug="<?php echo stridebr_e($modalidade['slug']); ?>"
                                                        data-sport-icon-id="<?php echo stridebr_e(stridebr_sport_icon_id((string) $modalidade['slug'])); ?>"
                                                        data-sport-favorite="1"
                                                        data-sport-family="<?php echo stridebr_e(sportCatalogFamilyKey((string) ($modalidade['familia_hub'] ?? ''), (string) ($modalidade['categoria'] ?? ''), (string) ($modalidade['slug'] ?? ''))); ?>"
                                                    ><span class="sport-option-icon"><?php echo stridebr_sport_icon_html((string) $modalidade['slug']); ?></span><span><?php echo stridebr_e($modalidade['nome']); ?></span></button>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($recentes): ?>
                                        <div class="sport-favorites-quick" data-sport-quick aria-label="Esportes usados recentemente">
                                            <div class="sport-group-title">Recentes</div>
                                            <div class="sport-favorites-chips">
                                                <?php foreach ($recentes as $modalidade): ?>
                                                    <button
                                                        type="button"
                                                        class="sport-option sport-favorite-quick"
                                                        role="option"
                                                        data-sport-option
                                                        data-sport-id="<?php echo stridebr_e($modalidade['idmodalidade']); ?>"
                                                        data-sport-name="<?php echo stridebr_e($modalidade['nome']); ?>"
                                                        data-sport-slug="<?php echo stridebr_e($modalidade['slug']); ?>"
                                                        data-sport-icon-id="<?php echo stridebr_e(stridebr_sport_icon_id((string) $modalidade['slug'])); ?>"
                                                        data-sport-favorite="0"
                                                        data-sport-family="<?php echo stridebr_e(sportCatalogFamilyKey((string) ($modalidade['familia_hub'] ?? ''), (string) ($modalidade['categoria'] ?? ''), (string) ($modalidade['slug'] ?? ''))); ?>"
                                                    ><span class="sport-option-icon"><?php echo stridebr_sport_icon_html((string) $modalidade['slug']); ?></span><span><?php echo stridebr_e($modalidade['nome']); ?></span></button>
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
                                <option value="<?php echo stridebr_e($modalidade['idmodalidade']); ?>" data-slug="<?php echo stridebr_e($modalidade['slug']); ?>" data-family="<?php echo stridebr_e(sportCatalogFamilyKey((string) ($modalidade['familia_hub'] ?? ''), (string) ($modalidade['categoria'] ?? ''), (string) ($modalidade['slug'] ?? ''))); ?>" data-permite-rota="<?php echo $modalidade['permite_rota'] ? '1' : '0'; ?>"<?php echo $modalidade['idmodalidade'] === $firstModalidade ? ' selected' : ''; ?>><?php echo stridebr_e($modalidade['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="input-field activity-model-field" data-model-field>
                        <label for="modelo">Formato</label>
                        <select id="modelo" name="idmodelo" required>
                            <?php foreach ($catalogo as $modalidade): ?>
                                <?php foreach ($modalidade['modelos'] as $modelo): ?>
                                    <option value="<?php echo stridebr_e($modelo['idmodelo']); ?>" data-modalidade="<?php echo stridebr_e($modalidade['idmodalidade']); ?>"<?php echo $modelo['idmodelo'] === $firstModelo ? ' selected' : ''; ?>><?php echo stridebr_e($modelo['nome']); ?><?php echo $modelo['versao'] > 1 ? ' v' . (int) $modelo['versao'] : ''; ?></option>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="input-field activity-workout-field" data-workout-field hidden>
                        <label for="idtreino_cronograma">Treino / rotina <span class="field-hint">opcional</span></label>
                        <select id="idtreino_cronograma" name="idtreino_cronograma" data-workout-select data-selected-workout="<?php echo stridebr_e($formWorkout); ?>">
                            <option value="">Sem treino vinculado</option>
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
                        <small>Útil para musculação e calistenia: vincula a atividade a um treino A/B/C ou por foco.</small>
                    </div>

                    <div class="input-field activity-date-field">
                        <label for="data">Data</label>
                        <input type="date" id="data" name="data" value="<?php echo stridebr_e($formDate); ?>" required>
                    </div>

                    <div class="input-field activity-time-field">
                        <label for="hora_h">Hora</label>
                        <div class="clock-segments" data-clock-field>
                            <input type="text" id="hora_h" inputmode="numeric" maxlength="2" value="<?php echo stridebr_e(substr($formHour, 0, 2)); ?>" data-clock-hours aria-label="Horas">
                            <span aria-hidden="true">:</span>
                            <input type="text" inputmode="numeric" maxlength="2" value="<?php echo stridebr_e(substr($formHour, 3, 2)); ?>" data-clock-minutes aria-label="Minutos">
                            <button type="button" class="time-now-button" data-time-now>Agora</button>
                            <button type="button" class="time-quick-toggle" data-time-quick-toggle aria-label="Abrir horários rápidos">⌄</button>
                            <div class="time-quick-menu" data-time-quick-menu hidden></div>
                            <input type="hidden" id="hora" name="hora" value="<?php echo stridebr_e($formHour); ?>" data-clock-value>
                        </div>
                    </div>
                </div>

                <div class="activity-model-panels-host" data-activity-model-panels-host data-details-loaded="<?php echo $editorDetailsLoaded ? '1' : '0'; ?>">
                    <?php if ($editorDetailsLoaded): ?>
                        <?php require dirname(__DIR__, 2) . '/src/layout/activity_model_panels.php'; ?>
                    <?php else: ?>
                        <div class="activity-editor-inline-loading" data-activity-editor-loading>Campos do treino serão carregados ao abrir.</div>
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
                $activityRouteCompact = true;
                require dirname(__DIR__, 2) . '/src/layout/activity_route_editor.php';
                ?>

                <section class="activity-route-privacy-fields activity-contextual-detail" data-route-privacy-fields<?php echo $formRoute !== '' ? '' : ' hidden'; ?>>
                    <div class="activity-detail-heading">
                        <div>
                            <strong>Privacidade da rota</strong>
                            <span>A rota completa continua salva para você.</span>
                        </div>
                    </div>
                    <div class="activity-compact-two-columns">
                        <label class="input-field">
                            <span>Ocultar no início</span>
                            <input type="number" name="ocultar_inicio_m" min="0" max="10000" step="50" value="<?php echo $formHideRouteStart; ?>" inputmode="numeric">
                            <small>metros · 0 para mostrar tudo</small>
                        </label>
                        <label class="input-field">
                            <span>Ocultar no fim</span>
                            <input type="number" name="ocultar_fim_m" min="0" max="10000" step="50" value="<?php echo $formHideRouteEnd; ?>" inputmode="numeric">
                            <small>metros · 0 para mostrar tudo</small>
                        </label>
                    </div>
                </section>

                <div class="activity-log-details">
                    <section class="activity-effort-section" data-effort-selector>
                        <div class="activity-detail-heading">
                            <div><strong>Esforço percebido</strong><span>Opcional · de 1 a 10.</span></div>
                            <button type="button" class="activity-inline-action" data-clear-effort<?php echo $formEffort === '' ? ' hidden' : ''; ?>>Não informar</button>
                        </div>
                        <input type="hidden" name="esforco_percebido" value="<?php echo stridebr_e($formEffort); ?>" data-effort-value>
                        <div class="effort-range-row">
                            <input type="range" min="1" max="10" step="1" value="<?php echo stridebr_e($formEffort !== '' ? $formEffort : '5'); ?>" data-effort-range aria-label="Esforço percebido de 1 a 10">
                            <output data-effort-output><?php echo $formEffort !== '' ? stridebr_e($formEffort) : '—'; ?></output>
                        </div>
                        <div class="effort-scale"><span>Fácil</span><span>Moderado</span><span>Máximo</span></div>
                    </section>

                    <section class="activity-enrichment-section">
                        <div class="activity-enrichment-heading">
                            <div><strong>Detalhes</strong></div>
                            <div class="activity-enrichment-actions">
                                <button type="button" class="optional-field-chip" data-toggle-log-detail="equipment" aria-expanded="<?php echo $formEquipment ? 'true' : 'false'; ?>">+ Equipamento</button>
                            </div>
                        </div>

                        <div class="activity-contextual-detail" data-log-detail="equipment"<?php echo $formEquipment ? '' : ' hidden'; ?>>
                            <div class="activity-detail-heading">
                                <div><strong>Equipamento</strong><span>Tênis, bicicleta ou outro item usado.</span></div>
                                <div class="activity-detail-heading-actions"><a href="/user/equipamentos.php">Gerenciar</a><button type="button" class="activity-inline-action" data-close-log-detail="equipment">Fechar</button></div>
                            </div>
                            <div data-activity-equipment-host data-selected-equipment="<?php echo stridebr_e(implode(',', $formEquipment)); ?>">
                                <?php if ($editorDetailsLoaded && $equipamentos): ?>
                                    <div class="equipment-picker">
                                        <?php foreach ($equipamentos as $equipamento): ?>
                                            <label class="equipment-chip">
                                                <input type="checkbox" name="equipamentos[]" value="<?php echo stridebr_e($equipamento['idequipamento']); ?>"<?php echo in_array((string) $equipamento['idequipamento'], $formEquipment, true) ? ' checked' : ''; ?>>
                                                <span><?php echo stridebr_e($equipamento['nome']); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif ($editorDetailsLoaded): ?>
                                    <a href="/user/equipamentos.php" class="activity-empty-action">+ Adicionar primeiro equipamento</a>
                                <?php else: ?>
                                    <span class="activity-inline-muted">Carrega ao abrir o registro.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <div class="input-field activity-observations-field">
                        <label for="observacoes">Observações <span class="field-hint">opcional</span></label>
                        <textarea id="observacoes" name="observacoes" rows="1" placeholder="Como foi a atividade?"><?php echo stridebr_e($formObservations); ?></textarea>
                    </div>

                    <details class="activity-more-options"<?php echo $formVisibility !== 'privado' ? ' open' : ''; ?>>
                        <summary>Mais opções</summary>
                        <div class="activity-more-options-content">
                            <div class="input-field visibility-field">
                                <label for="visibilidade">Quem pode ver</label>
                                <select id="visibilidade" name="visibilidade">
                                    <option value="privado"<?php echo $formVisibility === 'privado' ? ' selected' : ''; ?>>Só eu</option>
                                    <option value="amigos"<?php echo $formVisibility === 'amigos' ? ' selected' : ''; ?>>Amigos</option>
                                    <option value="publico"<?php echo $formVisibility === 'publico' ? ' selected' : ''; ?>>Público</option>
                                </select>
                            </div>
                        </div>
                    </details>
                </div>

                <div class="activity-form-actions">
                    <button type="button" class="activity-secondary-button" data-close-activity-form>Cancelar</button>
                    <button type="submit" class="activity-primary-action">Salvar atividade</button>
                </div>
            </form>
        </section>

        <section class="activity-history-summary" data-history-summary aria-label="Resumo das atividades dos últimos 7 dias">
            <article><span><?php echo stridebr_e(stridebr_t('activity.summary.activities')); ?></span><strong data-summary-activities><?php echo $initialHistorySummary !== null ? (int) ($initialHistorySummary['atividades'] ?? 0) : '—'; ?></strong><small data-summary-period><?php echo stridebr_e((string) ($initialHistorySummary['periodo'] ?? stridebr_t('activity.summary.last_7_days'))); ?></small></article>
            <article><span><?php echo stridebr_e(stridebr_t('activity.summary.time')); ?></span><strong data-summary-time><?php echo stridebr_e((string) ($initialHistorySummary['tempo'] ?? '—')); ?></strong><small><?php echo stridebr_e(stridebr_t('activity.summary.logged')); ?></small></article>
            <article><span><?php echo stridebr_e(stridebr_t('activity.summary.distance')); ?></span><strong data-summary-distance><?php echo stridebr_e((string) ($initialHistorySummary['distancia'] ?? '—')); ?></strong><small><?php echo stridebr_e(stridebr_t('activity.summary.accumulated')); ?></small></article>
            <article><span><?php echo stridebr_e(stridebr_t('activity.summary.elevation')); ?></span><strong data-summary-elevation><?php echo stridebr_e((string) ($initialHistorySummary['elevacao'] ?? '—')); ?></strong><small><?php echo stridebr_e(stridebr_t('activity.summary.accumulated')); ?></small></article>
        </section>

        <div class="activity-history-workspace" data-activity-history-workspace>
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
                <div class="activity-bulk-count"><strong data-bulk-count>0 selecionadas</strong><button type="button" data-bulk-select-visible>Selecionar carregadas</button></div>
                <div class="activity-bulk-sport-field"><span>Modalidade</span><?php echo sportPickerRenderSelect($catalogo, ['name' => 'idmodalidade', 'empty_label' => 'Não alterar', 'native_attributes' => ['data-bulk-sport' => true]]); ?></div>
                <label>Duração <span class="activity-bulk-duration" data-bulk-duration-control><select name="duracao_modo" data-bulk-duration-mode><option value="keep">Não alterar</option><option value="set">Definir</option><option value="clear">Limpar</option></select><span data-bulk-duration-value hidden><input type="number" name="duracao_minutos" min="1" max="1440" inputmode="numeric" placeholder="120"><small>min</small></span></span></label>
                <label>Visibilidade<select name="visibilidade"><option value="">Não alterar</option><option value="privado">Só eu</option><option value="amigos">Amigos</option><option value="publico">Público</option></select></label>
                <div class="activity-bulk-actions"><button type="submit" class="activity-primary-action">Aplicar</button><button type="button" class="activity-secondary-button is-danger" data-bulk-delete>Apagar</button><button type="button" class="activity-secondary-button" data-bulk-cancel>Sair</button></div>
            </form>
            <div class="activity-history-skeleton" data-history-skeleton aria-label="Carregando histórico" hidden>
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
                <strong>Nenhuma atividade encontrada.</strong>
                <span data-history-empty-text>Registre sua primeira atividade para começar a montar seu histórico.</span>
                <button type="button" class="activity-primary-action" data-toggle-activity-form>Registrar atividade física</button>
            </div>
            <div class="activity-history-error" data-history-error<?php echo $initialHistoryState === 'error' ? '' : ' hidden'; ?>>
                <strong>Não foi possível carregar o histórico.</strong>
                <span>O restante da página continua disponível. Tente carregar o histórico novamente.</span>
                <button type="button" class="activity-secondary-button" data-history-retry>Tentar novamente</button>
            </div>
            <div class="activity-history-more" data-history-more<?php echo $initialHistoryState === 'ready' && $initialHistoryCursor !== '' ? '' : ' hidden'; ?>>
                <button type="button" class="activity-secondary-button" data-history-load-more>Carregar mais</button>
                <span data-history-count><?php echo $initialHistoryState === 'ready' ? $initialHistoryTotal . ' atividade' . ($initialHistoryTotal === 1 ? ' carregada.' : 's carregadas.') : ''; ?></span>
            </div>
        </section>
        <aside class="activity-detail-placeholder" data-detail-desktop-placeholder aria-hidden="true">
            <div>
                <strong><?php echo stridebr_e(stridebr_t('activity.detail_title')); ?></strong>
                <span><?php echo stridebr_e(stridebr_t('activity.detail_empty')); ?></span>
            </div>
        </aside>
        <div class="activity-detail-drawer" data-activity-detail-drawer hidden>
            <button type="button" class="activity-detail-backdrop" data-close-activity-detail aria-label="Fechar detalhes"></button>
            <section class="activity-detail-panel" role="dialog" aria-modal="true" aria-labelledby="activity-detail-title" data-activity-detail-panel>
                <header>
                    <div><div class="activity-detail-kicker"><span class="activity-detail-kicker-main"><span data-detail-sport>Atividade</span><span class="activity-detail-visibility" data-detail-visibility></span></span><a class="activity-detail-compare" data-detail-compare data-activity-tool="compare" href="/user/comparar-atividades.php"><?php echo stridebr_e(stridebr_t('activity.compare')); ?></a></div><h2 id="activity-detail-title" data-detail-title>Carregando…</h2><p data-detail-date></p></div>
                    <button type="button" data-close-activity-detail aria-label="Fechar">×</button>
                </header>
                <div class="activity-detail-loading" data-detail-loading><i aria-hidden="true"></i><span>Carregando detalhes…</span></div>
                <div data-detail-content hidden></div>
            </section>
        </div>
        </div>
        <div class="activity-context-menu" data-activity-context-menu role="menu" aria-label="Ações da atividade" hidden>
            <button type="button" role="menuitem" data-context-open>Abrir</button>
            <a role="menuitem" data-context-edit href="#">Editar</a>
            <button type="button" role="menuitem" class="is-danger" data-context-delete>Apagar</button>
        </div>
    </main>
</div>
<div class="activity-edit-modal" data-activity-edit-modal hidden>
    <button type="button" class="activity-edit-modal-backdrop" data-close-activity-edit aria-label="Fechar edição"></button>
    <section class="activity-edit-modal-panel" role="dialog" aria-modal="true" aria-labelledby="activity-edit-modal-title">
        <header class="activity-edit-modal-header">
            <div><span>Atividades</span><h2 id="activity-edit-modal-title">Editar atividade</h2></div>
            <button type="button" data-close-activity-edit aria-label="Fechar">×</button>
        </header>
        <div class="activity-edit-modal-body">
            <div class="activity-edit-modal-loading" data-activity-edit-loading><span></span><strong>Carregando atividade…</strong></div>
            <iframe title="Editar atividade" data-activity-edit-frame></iframe>
        </div>
    </section>
</div>

<div class="activity-tool-overlay" data-activity-tool-overlay hidden>
    <button type="button" class="activity-tool-backdrop" data-close-activity-tool aria-label="Fechar"></button>
    <section class="activity-tool-panel" role="dialog" aria-modal="true" aria-labelledby="activity-tool-title">
        <header class="activity-tool-panel-header"><div><span>Atividades</span><h2 id="activity-tool-title" data-activity-tool-title>Ferramenta</h2></div><button type="button" data-close-activity-tool aria-label="Fechar">×</button></header>
        <div class="activity-tool-panel-content" data-activity-tool-content><div class="activity-tool-loading">Carregando…</div></div>
    </section>
</div>

<div class="activity-post-save-modal" data-post-save-share hidden>
    <button type="button" class="activity-post-save-backdrop" data-post-save-close aria-label="Fechar"></button>
    <section class="activity-post-save-panel" role="dialog" aria-modal="true" aria-labelledby="post-save-share-title">
        <header>
            <div><span>Atividade salva</span><h2 id="post-save-share-title">Compartilhar?</h2></div>
            <button type="button" data-post-save-close aria-label="Fechar">×</button>
        </header>
        <div class="activity-post-save-body">
            <div class="activity-post-save-preview">
                <canvas width="1080" height="1920" data-post-save-canvas aria-label="Prévia padrão de compartilhamento"></canvas>
            </div>
            <div class="activity-post-save-copy">
                <strong data-post-save-title>Atividade</strong>
                <span data-post-save-summary></span>
                <p>Seu compartilhamento padrão já está pronto. Compartilhe agora ou abra o editor completo.</p>
                <span class="activity-post-save-status" data-post-save-status role="status" aria-live="polite"></span>
            </div>
        </div>
        <footer>
            <button type="button" class="activity-danger-button activity-post-save-delete" data-post-save-delete>Apagar atividade</button>
            <button type="button" class="activity-primary-action" data-post-save-native>Compartilhar</button>
            <button type="button" class="activity-secondary-button" data-post-save-edit>Editar compartilhamento</button>
            <button type="button" class="activity-secondary-button" data-post-save-close>Concluir</button>
        </footer>
    </section>
</div>

<div class="activity-share-modal" data-share-modal hidden>
    <button type="button" class="activity-share-backdrop" data-close-share aria-label="Fechar compartilhamento"></button>

    <div class="activity-share-workspace" role="dialog" aria-modal="true" aria-labelledby="share-title">
        <aside class="activity-share-preview-window activity-share-preview-shell" data-share-preview-shell aria-label="Prévia do cartão">
            <div class="activity-share-preview-heading">
                <strong>Prévia</strong>
                <small data-share-preview-format>Story · 1080 × 1920</small>
            </div>
            <div class="activity-share-preview-layout">
                <div class="activity-share-preview-stage">
                    <canvas data-share-canvas width="1080" height="1920" aria-label="Prévia do compartilhamento"></canvas>
                </div>
                <fieldset class="activity-share-format-options activity-share-format-rail">
                    <legend>Formato</legend>
                    <label><input type="radio" name="share-format" value="story" data-share-format checked><span><i class="share-format-thumb is-story" aria-hidden="true"></i><b><strong>Story</strong><small>9:16</small></b></span></label>
                    <label><input type="radio" name="share-format" value="portrait" data-share-format><span><i class="share-format-thumb is-portrait" aria-hidden="true"></i><b><strong>Retrato</strong><small>4:5</small></b></span></label>
                    <label><input type="radio" name="share-format" value="square" data-share-format><span><i class="share-format-thumb is-square" aria-hidden="true"></i><b><strong>Quadrado</strong><small>1:1</small></b></span></label>
                    <label><input type="radio" name="share-format" value="compact" data-share-format><span><i class="share-format-thumb is-compact" aria-hidden="true"></i><b><strong>Compacto</strong><small>vertical</small></b></span></label>
                    <label><input type="radio" name="share-format" value="compactWide" data-share-format><span><i class="share-format-thumb is-compact-wide" aria-hidden="true"></i><b><strong>Compacto</strong><small>horizontal</small></b></span></label>
                </fieldset>
            </div>
        </aside>

        <section class="activity-share-panel">
            <header>
                <div><span>Compartilhar</span><h2 id="share-title">Compartilhar atividade</h2></div>
                <button type="button" data-close-share aria-label="Fechar">×</button>
            </header>

            <div class="activity-share-body">
                <div class="activity-share-controls">
                    <section class="activity-share-choice-block activity-share-content-block" data-share-single-only>
                        <div class="activity-share-block-heading">
                            <div><strong>Conteúdo</strong></div>
                        </div>
                        <div class="activity-share-choice-grid is-content" data-share-content-grid></div>
                    </section>

                    <section class="activity-share-choice-block activity-share-background-block" data-share-background-block>
                        <div class="activity-share-block-heading">
                            <div><strong>Fundo</strong></div>
                        </div>
                        <div class="activity-share-choice-grid is-background activity-share-style-grid" data-share-style-grid></div>
                    </section>

                    <section class="activity-share-choice-block activity-share-session-layout-block" data-share-session-only hidden>
                        <div class="activity-share-block-heading">
                            <div><strong>Layout da sessão</strong><small>Esta área recebe os layouts de várias rotas.</small></div>
                        </div>
                        <div class="activity-share-choice-grid is-session" data-share-session-layout-grid></div>
                    </section>

                    <section class="activity-share-content-options" data-share-content-options hidden aria-hidden="true">
                        <fieldset class="activity-share-mode-options">
                            <label><input type="radio" name="share-content-mode" value="activity" data-share-content-mode checked><span>Atividade</span></label>
                            <label><input type="radio" name="share-content-mode" value="segments" data-share-content-mode><span>Trechos</span></label>
                        </fieldset>
                        <fieldset class="activity-share-segment-mode-options" data-share-segment-mode-options hidden>
                            <legend>Trechos</legend>
                            <label><input type="radio" name="share-segment-mode" value="together" data-share-segment-mode checked><span>Todos juntos</span></label>
                            <label><input type="radio" name="share-segment-mode" value="separate" data-share-segment-mode><span>Separados</span></label>
                        </fieldset>
                    </section>

                    <section class="activity-share-quick-controls" aria-label="Ajustes rápidos">
                        <input type="checkbox" data-share-show="route" checked hidden>

                        <fieldset class="activity-share-color-options" data-share-color-options>
                            <legend>Cor do fundo</legend>
                            <label title="Azul profundo"><input type="radio" name="share-color" value="deep" data-share-background-color checked><span><i class="share-color-swatch is-deep"></i><b>Azul profundo</b></span></label>
                            <label title="Azul escuro"><input type="radio" name="share-color" value="dark" data-share-background-color><span><i class="share-color-swatch is-dark"></i><b>Azul escuro</b></span></label>
                            <label title="Claro"><input type="radio" name="share-color" value="light" data-share-background-color><span><i class="share-color-swatch is-light"></i><b>Claro</b></span></label>
                            <label title="Preto"><input type="radio" name="share-color" value="black" data-share-background-color><span><i class="share-color-swatch is-black"></i><b>Preto</b></span></label>
                        </fieldset>
                    </section>

                    <button type="button" class="activity-share-mobile-photo" data-share-mobile-photo hidden>Escolher foto</button>
                    <button type="button" class="activity-share-mobile-customize" data-share-mobile-customize><span>Personalizar</span><span aria-hidden="true">›</span></button>
                    <button type="button" class="activity-share-customize-backdrop" data-share-mobile-customize-close aria-label="Fechar personalização"></button>

                    <aside class="activity-share-sidebar" data-share-customize-sheet aria-label="Personalizar cartão">
                        <div class="activity-share-mobile-sheet-head">
                            <button type="button" data-share-mobile-customize-close aria-label="Fechar">‹</button>
                            <strong>Personalizar cartão</strong>
                            <span aria-hidden="true"></span>
                        </div>

                        <section class="activity-share-group activity-share-visual-options-group">
                            <h3>Aparência</h3>
                            <div class="activity-share-map-option" data-share-map-option hidden>
                                <label><span><strong>Mostrar mapa</strong><small>Desenha as ruas sem nomes nem locais.</small></span><input type="checkbox" data-share-map-toggle></label>
                            </div>

                            <div class="activity-share-route-scale">
                                <div><strong>Tamanho da rota</strong></div>
                                <label>
                                    <input type="range" min="60" max="200" step="5" value="100" data-share-route-scale>
                                    <output data-share-route-scale-value>100%</output>
                                </label>
                            </div>
                        </section>

                        <section class="activity-share-group activity-share-elements-group">
                            <h3>Elementos do cartão</h3>
                            <div class="activity-share-visibility">
                                <label class="activity-share-heading-mode"><span>Texto no topo</span><select data-share-heading-mode><option value="title">Título da atividade</option><option value="sport">Modalidade</option><option value="none">Ocultar</option></select></label>
                                <label><span>Data</span><input type="checkbox" data-share-show="date"></label>
                                <label><span>Logo</span><input type="checkbox" data-share-show="logo" checked></label>
                            </div>

                            <details class="activity-share-details" open>
                                <summary>Estatísticas <small>até 4</small></summary>
                                <div class="activity-share-metrics" data-share-metric-options></div>
                            </details>

                            <details class="activity-share-details">
                                <summary>Mais opções</summary>
                                <label class="activity-share-caption">Legenda curta <input type="text" maxlength="60" placeholder="Opcional" data-share-caption></label>
                            </details>
                        </section>

                        <section class="activity-share-group activity-share-segments-group" data-share-segments-group hidden>
                            <h3>Trechos</h3>
                            <div class="activity-share-segment-list" data-share-segment-list></div>
                            <label class="activity-share-segment-preview-picker" data-share-segment-preview-picker hidden>
                                <span>Prévia individual</span>
                                <select data-share-segment-preview></select>
                            </label>
                        </section>

                        <section class="activity-share-group activity-share-photo-group" data-share-photo-field hidden>
                            <h3>Foto</h3>
                            <div class="activity-share-photo-actions">
                                <div class="activity-share-photo-preview" data-share-photo-preview><span data-share-photo-empty>Sem foto</span></div>
                                <div class="activity-share-photo-buttons">
                                    <button type="button" class="activity-secondary-button" data-share-open-camera>Tirar foto</button>
                                    <label class="activity-secondary-button">Escolher arquivo<input type="file" accept="image/*" data-share-photo></label>
                                    <input type="file" accept="image/*" capture="environment" data-share-camera hidden>
                                    <small data-share-photo-name>Escolha ou capture uma foto para este estilo.</small>
                                </div>
                            </div>
                        </section>


                    </aside>
                </div>
            </div>

            <p data-share-status></p>
            <footer>
                <details class="activity-share-more-menu">
                    <summary aria-label="Mais opções" title="Mais opções">•••</summary>
                    <div>
                        <button type="button" data-export-route-png hidden>Exportar rota como PNG</button>
                        <button type="button" data-reset-share>Redefinir cartão</button>
                    </div>
                </details>
                <button type="button" class="activity-secondary-button" data-copy-share>Copiar card</button>
                <button type="button" class="activity-secondary-button" data-download-share>Baixar</button>
                <button type="button" class="activity-primary-action" data-native-share>Compartilhar</button>
            </footer>

            <div class="activity-share-camera-sheet" data-share-camera-sheet hidden>
                <div class="activity-share-camera-backdrop" data-share-close-camera></div>
                <div class="activity-share-camera-dialog" role="dialog" aria-modal="true" aria-label="Capturar foto">
                    <header><strong>Tirar foto</strong><button type="button" data-share-close-camera aria-label="Fechar">×</button></header>
                    <video autoplay playsinline muted data-share-camera-video></video>
                    <p data-share-camera-status>Abra a câmera para usar a foto no cartão.</p>
                    <footer><button type="button" class="activity-secondary-button" data-share-close-camera>Cancelar</button><button type="button" class="activity-primary-action" data-share-capture-camera>Usar foto</button></footer>
                </div>
            </div>
        </section>
    </div>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/activity-exchange.js')); ?>"></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/atividades.js')); ?>"></script>
</body>
</html>
