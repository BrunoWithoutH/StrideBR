<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/dashboard.php';
require_once dirname(__DIR__, 2) . '/src/function/product_analytics.php';
require_once dirname(__DIR__, 2) . '/src/includes/sport_icons.php';
require_once dirname(__DIR__, 2) . '/src/layout/sport_picker.php';

$errors = [];
$available = dashboardMetasDisponiveis($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = trim((string) ($_POST['action'] ?? ''));
    try {
        if ($action === 'create') {
            dashboardCriarMeta($pdo, $idUsuario, $_POST);
            productAnalyticsRegistrar($pdo, $idUsuario, 'goal_created', ['type' => substr((string) ($_POST['tipo_meta'] ?? 'metrica'), 0, 20), 'metric' => substr((string) ($_POST['metrica'] ?? $_POST['benchmark_tipo'] ?? ''), 0, 40)]);
            stridebr_flash('success', stridebr_t('goals.flash.created'));
        } elseif ($action === 'edit') {
            dashboardEditarMeta($pdo, $idUsuario, trim((string) ($_POST['idmeta'] ?? '')), $_POST);
            stridebr_flash('success', stridebr_t('goals.flash.updated'));
        } elseif ($action === 'archive') {
            if (!dashboardArquivarMeta($pdo, $idUsuario, trim((string) ($_POST['idmeta'] ?? '')))) throw new InvalidArgumentException(stridebr_t('goals.error.not_found'));
            stridebr_flash('success', stridebr_t('goals.flash.archived'));
        } elseif ($action === 'reactivate') {
            $idMeta = trim((string) ($_POST['idmeta'] ?? ''));
            $candidate = null;
            foreach (dashboardListarMetas($pdo, $idUsuario, false) as $goal) if ((string) $goal['idmeta'] === $idMeta) { $candidate = $goal; break; }
            if (is_array($candidate) && (string) ($candidate['tipo_meta'] ?? 'metrica') === 'benchmark' && !empty($candidate['concluida_em'])) throw new InvalidArgumentException(stridebr_t('goals.error.benchmark_reactivate_completed'));
            if (!dashboardReativarMeta($pdo, $idUsuario, $idMeta)) throw new InvalidArgumentException(stridebr_t('goals.error.not_found'));
            stridebr_flash('success', stridebr_t('goals.flash.reactivated'));
        } else {
            throw new InvalidArgumentException(stridebr_t('goals.error.invalid_action'));
        }
        header('Location: /user/metas.php');
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : stridebr_t('goals.error.update');
        if (!$e instanceof InvalidArgumentException && !$e instanceof RuntimeException) error_log($e->getMessage());
    }
}

$modalidades = $available ? dashboardListarModalidades($pdo, $idUsuario) : [];
$exerciciosMeta = $available ? dashboardListarExerciciosMeta($pdo, $idUsuario) : [];
$metas = $available ? dashboardListarMetas($pdo, $idUsuario, false) : [];
$conclusoes = $available ? dashboardListarConclusoesMetas($pdo, $idUsuario, 40) : [];
$benchmarkRows = $available && benchmarkTableExists($pdo) ? benchmarkList($pdo, $idUsuario, ['limit' => 1000]) : [];
$benchmarkClientRows = array_map(static fn(array $row): array => [
    'id' => (string) ($row['idbenchmark'] ?? ''), 'type' => (string) ($row['tipo'] ?? ''), 'sport' => (string) ($row['idmodalidade'] ?? ''), 'exercise' => (string) ($row['idexercicio'] ?? ''), 'distance' => is_numeric($row['distancia_m'] ?? null) ? (float) $row['distancia_m'] : null, 'value' => is_numeric($row['valor_canonico'] ?? null) ? (float) $row['valor_canonico'] : null, 'date' => (string) ($row['data_resultado'] ?? ''),
], $benchmarkRows);
$benchmarkClientRegistry = [];
foreach (benchmarkGoalTypes() as $type) {
    $config = benchmarkTypeConfig($type) ?? [];
    $benchmarkClientRegistry[$type] = ['direction' => $config['direction'] ?? 'higher', 'primary' => $config['primary'] ?? 'best', 'slugs' => array_values((array) ($config['slugs'] ?? [])), 'families' => array_values((array) ($config['families'] ?? [])), 'requiresExercise' => !empty($config['requires_exercise']), 'requiresDistance' => !empty($config['requires_distance'])];
}

$editId = trim((string) ($_GET['edit'] ?? ''));
$editMeta = null;
foreach ($metas as $meta) if ((string) $meta['idmeta'] === $editId) { $editMeta = $meta; break; }
$showForm = isset($_GET['new']) || $editMeta !== null || $errors !== [];
$flashes = stridebr_take_flashes();
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/dashboard.css')); ?>">
    <title><?php echo stridebr_e(stridebr_t('goals.page_title')); ?> | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content">
        <div class="page-shell goals-page">
            <header class="page-heading goals-heading">
                <div><span class="dashboard-eyebrow"><?php echo stridebr_e(stridebr_t('goals.progress')); ?></span><h1><?php echo stridebr_e(stridebr_t('goals.page_title')); ?></h1></div>
                <?php if ($available): ?><a class="primary-button" href="/user/metas.php?new=1">+ <?php echo stridebr_e(stridebr_t('goals.new')); ?></a><?php endif; ?>
            </header>

            <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e((string) ($flash['type'] ?? 'info')); ?>"><?php echo stridebr_e((string) ($flash['message'] ?? '')); ?></div><?php endforeach; ?>
            <?php if (!$showForm): ?><?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?><?php endif; ?>

            <?php if (!$available): ?>
                <section class="content-card"><h2><?php echo stridebr_e(stridebr_t('goals.unavailable')); ?></h2></section>
            <?php else: ?>
                <?php if ($showForm):
                    $form = $editMeta ?? [];
                    $formType = (string) ($form['tipo_meta'] ?? ($_POST['tipo_meta'] ?? 'metrica'));
                    $formMetric = (string) ($form['metrica'] ?? ($_POST['metrica'] ?? 'distancia'));
                    $formBenchmarkType = (string) ($form['benchmark_tipo'] ?? ($_POST['benchmark_tipo'] ?? ''));
                    $formPeriod = (string) ($form['periodo'] ?? ($_POST['periodo'] ?? 'continuo'));
                    $defaultGoalStart = (new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
                    $formSport = (string) ($form['idmodalidade'] ?? ($_POST['idmodalidade'] ?? ''));
                    $formExercise = (string) ($form['idexercicio'] ?? ($_POST['idexercicio'] ?? ''));
                    $rawTarget = $form['valor_alvo'] ?? ($_POST['valor_alvo'] ?? '');
                    $formTarget = $formType === 'benchmark' && is_numeric($rawTarget) && in_array($formBenchmarkType, ['css', 'distance_time'], true) ? benchmarkFormatClock((float) $rawTarget) : (string) $rawTarget;
                    $formName = (string) ($form['nome'] ?? ($_POST['nome'] ?? ''));
                    $formStart = (string) ($form['data_inicio'] ?? ($_POST['data_inicio'] ?? ($formPeriod === 'personalizado' || $formPeriod === 'continuo' ? $defaultGoalStart : '')));
                    $formEnd = (string) ($form['data_fim'] ?? ($_POST['data_fim'] ?? ''));
                    $formDistance = is_numeric($form['benchmark_distancia_m'] ?? null) ? (float) $form['benchmark_distancia_m'] : (is_numeric($_POST['benchmark_distancia_m'] ?? null) ? (float) $_POST['benchmark_distancia_m'] : null);
                    $distancePresets = [1000.0, 3000.0, 5000.0, 10000.0, 21097.5, 42195.0];
                    $distancePreset = $formDistance !== null && array_reduce($distancePresets, static fn(bool $carry, float $distance): bool => $carry || abs($distance - $formDistance) < .001, false) ? $formDistance : ($formDistance !== null ? 'custom' : 5000.0);
                    $benchmarkCompleted = $formType === 'benchmark' && !empty($form['concluida_em']);
                ?>
                <div class="goals-editor-modal" data-goals-editor role="dialog" aria-modal="true" aria-labelledby="goal-editor-title">
                    <a class="goals-editor-backdrop" href="/user/metas.php" aria-label="<?php echo stridebr_e(stridebr_t('goals.close_editor')); ?>"></a>
                    <section class="content-card goals-editor" id="meta-form">
                        <div class="goals-editor-header"><div><h2 id="goal-editor-title"><?php echo stridebr_e($editMeta ? stridebr_t('goals.edit') : stridebr_t('goals.new')); ?></h2><p><?php echo stridebr_e(stridebr_t('goals.editor_help')); ?></p></div><a class="goals-editor-close" href="/user/metas.php" aria-label="<?php echo stridebr_e(stridebr_t('goals.close_editor')); ?>">×</a></div>
                        <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
                        <?php if ($benchmarkCompleted): ?><div class="alert alert-info"><?php echo stridebr_e(stridebr_t('goals.benchmark.completed_locked')); ?></div><?php endif; ?>
                        <form method="POST" class="goals-form" data-goals-form>
                            <?php echo stridebr_csrf_field(); ?>
                            <input type="hidden" name="action" value="<?php echo $editMeta ? 'edit' : 'create'; ?>">
                            <?php if ($editMeta): ?><input type="hidden" name="idmeta" value="<?php echo stridebr_e((string) $editMeta['idmeta']); ?>"><input type="hidden" name="tipo_meta" value="<?php echo stridebr_e($formType); ?>"><?php endif; ?>

                            <?php if (!$editMeta): ?>
                            <fieldset class="goal-type-switch" data-goal-type-switch>
                                <legend><?php echo stridebr_e(stridebr_t('goals.type_prompt')); ?></legend>
                                <label><input type="radio" name="tipo_meta" value="metrica"<?php echo $formType === 'metrica' ? ' checked' : ''; ?>><span><strong><?php echo stridebr_e(stridebr_t('goals.type.practice')); ?></strong><small><?php echo stridebr_e(stridebr_t('goals.type.practice_help')); ?></small></span></label>
                                <label><input type="radio" name="tipo_meta" value="benchmark"<?php echo $formType === 'benchmark' ? ' checked' : ''; ?>><span><strong><?php echo stridebr_e(stridebr_t('goals.type.benchmark')); ?></strong><small><?php echo stridebr_e(stridebr_t('goals.type.benchmark_help')); ?></small></span></label>
                            </fieldset>
                            <?php else: ?><div class="goal-type-readonly"><strong><?php echo stridebr_e(stridebr_t($formType === 'benchmark' ? 'goals.type.benchmark' : 'goals.type.practice')); ?></strong></div><?php endif; ?>

                            <div class="goals-form-layout">
                                <section class="goals-form-block" aria-labelledby="goal-track-heading">
                                    <div class="goals-form-block-heading"><span>1</span><div><strong id="goal-track-heading"><?php echo stridebr_e(stridebr_t('home.what_to_track')); ?></strong><small><?php echo stridebr_e(stridebr_t('goals.track_help')); ?></small></div></div>
                                    <div class="goal-sport-field"><span class="goal-field-label"><?php echo stridebr_e(stridebr_t('common.sport')); ?></span><?php echo sportPickerRenderSelect($modalidades, ['name'=>'idmodalidade','selected'=>$formSport,'empty_label'=>stridebr_t('goals.all_sports'),'placeholder'=>stridebr_t('goals.choose_sport'),'native_attributes'=>$editMeta && $formType === 'benchmark' ? ['data-goal-locked'=>'1'] : []]); ?></div>

                                    <div data-goal-practice-fields<?php echo $formType === 'metrica' ? '' : ' hidden'; ?>>
                                        <label><?php echo stridebr_e(stridebr_t('home.metric')); ?><select name="metrica" data-goal-metric><option value="distancia"<?php echo $formMetric === 'distancia' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('common.distance')); ?></option><option value="duracao"<?php echo $formMetric === 'duracao' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('activity.summary.time')); ?></option><option value="atividades"<?php echo $formMetric === 'atividades' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('activity.page_title')); ?></option><option value="elevacao"<?php echo $formMetric === 'elevacao' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('common.elevation')); ?></option><option value="dias_ativos"<?php echo $formMetric === 'dias_ativos' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('home.active_days')); ?></option><option value="carga_maxima"<?php echo $formMetric === 'carga_maxima' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('goals.load_metric_label')); ?></option></select></label>
                                    </div>

                                    <div data-goal-benchmark-fields<?php echo $formType === 'benchmark' ? '' : ' hidden'; ?>>
                                        <label><?php echo stridebr_e(stridebr_t('goals.benchmark.choose_type')); ?><select name="benchmark_tipo" data-goal-benchmark-type<?php echo $editMeta && $formType === 'benchmark' ? ' data-goal-locked="1" disabled' : ''; ?>><option value=""><?php echo stridebr_e(stridebr_t('goals.benchmark.choose_type_placeholder')); ?></option><?php foreach (benchmarkGoalTypes() as $type): $cfg=benchmarkTypeConfig($type) ?? []; ?><option value="<?php echo stridebr_e($type); ?>" data-slugs="<?php echo stridebr_e(implode(',', (array) ($cfg['slugs'] ?? []))); ?>" data-families="<?php echo stridebr_e(implode(',', (array) ($cfg['families'] ?? []))); ?>"<?php echo $formBenchmarkType === $type ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t((string) ($cfg['label_key'] ?? 'benchmarks.' . $type))); ?></option><?php endforeach; ?></select><?php if ($editMeta && $formType === 'benchmark'): ?><input type="hidden" name="benchmark_tipo" value="<?php echo stridebr_e($formBenchmarkType); ?>"><?php endif; ?><small data-goal-benchmark-unavailable hidden><?php echo stridebr_e(stridebr_t('goals.benchmark.unavailable_sport')); ?></small></label>
                                    </div>

                                    <label class="goal-exercise-field" data-goal-exercise-field<?php echo ($formMetric === 'carga_maxima' || $formBenchmarkType === 'one_rm') ? '' : ' hidden'; ?>><?php echo stridebr_e(stridebr_t('home.exercise')); ?><select name="idexercicio" data-goal-exercise<?php echo $editMeta && $formType === 'benchmark' ? ' data-goal-locked="1" disabled' : ''; ?>><option value=""><?php echo stridebr_e($formType === 'benchmark' && $formBenchmarkType === 'one_rm' && $formExercise === '' && !empty($form['benchmark_referencia_nome_snapshot']) ? (string) $form['benchmark_referencia_nome_snapshot'] : stridebr_t('home.choose_exercise')); ?></option><?php foreach ($exerciciosMeta as $exercicio): ?><option value="<?php echo stridebr_e((string) $exercicio['idexercicio']); ?>" data-modalidades="<?php echo stridebr_e((string) ($exercicio['modalidades'] ?? '')); ?>"<?php echo $formExercise === (string) $exercicio['idexercicio'] ? ' selected' : ''; ?>><?php echo stridebr_e((string) $exercicio['nome']); ?></option><?php endforeach; ?></select><?php if ($editMeta && $formType === 'benchmark'): ?><input type="hidden" name="idexercicio" value="<?php echo stridebr_e($formExercise); ?>"><?php endif; ?><small class="goal-field-help" data-goal-load-help<?php echo $formType === 'metrica' && $formMetric === 'carga_maxima' ? '' : ' hidden'; ?>><?php echo stridebr_e(stridebr_t('goals.load_help_precise')); ?></small></label>

                                    <div class="goal-benchmark-distance" data-goal-distance-field<?php echo $formBenchmarkType === 'distance_time' ? '' : ' hidden'; ?>>
                                        <label><?php echo stridebr_e(stridebr_t('benchmarks.distance')); ?><select name="benchmark_distancia_m" data-goal-distance<?php echo $editMeta && $formType === 'benchmark' ? ' data-goal-locked="1" disabled' : ''; ?>><?php foreach ($distancePresets as $distance): ?><option value="<?php echo stridebr_e((string) $distance); ?>"<?php echo $distancePreset !== 'custom' && abs((float) $distancePreset - $distance) < .001 ? ' selected' : ''; ?>><?php echo stridebr_e(benchmarkFormatDistance($distance)); ?></option><?php endforeach; ?><option value="custom"<?php echo $distancePreset === 'custom' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('goals.benchmark.distance_custom')); ?></option></select></label>
                                        <label data-goal-custom-distance<?php echo $distancePreset === 'custom' ? '' : ' hidden'; ?>><?php echo stridebr_e(stridebr_t('goals.benchmark.custom_distance_m')); ?><input type="number" min="1" max="1000000" step="0.1" name="benchmark_distancia_custom_m" value="<?php echo $distancePreset === 'custom' && $formDistance !== null ? stridebr_e((string) $formDistance) : ''; ?>" inputmode="decimal"></label>
                                        <?php if ($editMeta && $formType === 'benchmark' && $formDistance !== null): ?><input type="hidden" name="benchmark_distancia_m" value="<?php echo stridebr_e((string) $formDistance); ?>"><?php endif; ?>
                                    </div>
                                    <div class="goal-benchmark-reference" data-goal-benchmark-reference hidden aria-live="polite"></div>
                                </section>

                                <section class="goals-form-block" aria-labelledby="goal-target-heading">
                                    <div class="goals-form-block-heading"><span>2</span><div><strong id="goal-target-heading"><?php echo stridebr_e(stridebr_t('goals.define_target')); ?></strong><small><?php echo stridebr_e(stridebr_t('goals.target_help')); ?></small></div></div>
                                    <label><span data-goal-target-label><?php echo stridebr_e(stridebr_t('goals.target')); ?></span><span class="dashboard-target-input"><input name="valor_alvo" value="<?php echo stridebr_e($formTarget); ?>" inputmode="decimal" required placeholder="20"<?php echo $benchmarkCompleted ? ' readonly' : ''; ?>><span data-goal-unit><?php echo stridebr_e($formType === 'benchmark' && $formBenchmarkType !== '' ? (benchmarkTypeConfig($formBenchmarkType)['unit'] ?? '') : dashboardMetaUnidade($formMetric)); ?></span></span></label>
                                    <label><?php echo stridebr_e(stridebr_t('goals.deadline')); ?><select name="periodo" data-goal-period<?php echo $benchmarkCompleted ? ' disabled' : ''; ?>><option value="continuo"<?php echo $formPeriod === 'continuo' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('home.no_deadline')); ?></option><option value="personalizado"<?php echo $formPeriod === 'personalizado' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('home.until_date')); ?></option><option value="semanal" data-practice-period<?php echo $formPeriod === 'semanal' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('schedule.every_week')); ?></option><option value="mensal" data-practice-period<?php echo $formPeriod === 'mensal' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('home.every_month')); ?></option><option value="anual" data-practice-period<?php echo $formPeriod === 'anual' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('home.every_year')); ?></option></select><?php if ($benchmarkCompleted): ?><input type="hidden" name="periodo" value="<?php echo stridebr_e($formPeriod); ?>"><?php endif; ?><small class="goal-field-help"><?php echo stridebr_e(stridebr_t('goals.period_help')); ?></small></label>
                                    <div class="goals-custom-dates<?php echo $formPeriod === 'personalizado' ? ' is-visible' : ''; ?>" data-goal-custom-dates><label data-goal-start-field><?php echo stridebr_e(stridebr_t('goals.start_counting')); ?><input type="date" name="data_inicio" value="<?php echo stridebr_e($formStart); ?>"></label><label><?php echo stridebr_e(stridebr_t('home.reach_by')); ?><input type="date" name="data_fim" value="<?php echo stridebr_e($formEnd); ?>"<?php echo $benchmarkCompleted ? ' readonly' : ''; ?>></label></div>
                                </section>
                            </div>

                            <label class="goals-name-field"><?php echo stridebr_e(stridebr_t('home.goal_name')); ?> <small><?php echo stridebr_e(stridebr_t('common.optional')); ?></small><input name="nome" maxlength="80" value="<?php echo stridebr_e($formName); ?>" placeholder="<?php echo stridebr_e(stridebr_t('goals.name_placeholder')); ?>"></label>
                            <div class="goal-live-summary" data-goal-summary aria-live="polite"></div>
                            <div class="goals-form-actions"><a class="secondary-button" href="/user/metas.php"><?php echo stridebr_e(stridebr_t('common.cancel')); ?></a><button class="primary-button" type="submit"><?php echo stridebr_e($editMeta ? stridebr_t('goals.save_changes') : stridebr_t('goals.create')); ?></button></div>
                        </form>
                    </section>
                </div>
                <?php endif; ?>

                <section class="goals-section" data-goals-section="active">
                    <div class="section-title-row"><div><h2><?php echo stridebr_e(stridebr_t('goals.active')); ?></h2><p><?php echo stridebr_e(stridebr_t('goals.active_help')); ?></p></div></div>
                    <?php $ativas = array_values(array_filter($metas, static fn(array $meta): bool => stridebr_db_bool($meta['ativa'] ?? false))); ?>
                    <?php if ($ativas === []): ?><div class="content-card goals-empty"><strong><?php echo stridebr_e(stridebr_t('goals.empty_active')); ?></strong><p><?php echo stridebr_e(stridebr_t('goals.empty_active_help')); ?></p><a class="primary-button" href="/user/metas.php?new=1"><?php echo stridebr_e(stridebr_t('goals.create')); ?></a></div><?php else: ?>
                    <div class="goals-grid">
                        <?php foreach ($ativas as $meta): $isBenchmark=(string)($meta['tipo_meta']??'metrica')==='benchmark'; ?>
                        <?php if ($isBenchmark):
                            $type=(string)$meta['benchmark_tipo']; $best=is_array($meta['benchmark_best']??null)?$meta['benchmark_best']:null; $bestValue=$best!==null?(float)$best['valor_canonico']:null; $target=(float)$meta['valor_alvo']; $remaining=(float)($meta['restante']??0); $hasResult=$bestValue!==null; $percentAvailable=!empty($meta['percentual_disponivel']);
                        ?>
                        <article class="goal-card goal-card-benchmark<?php echo !empty($meta['atingida'])?' is-complete':''; ?><?php echo !empty($meta['expirada'])?' is-expired':''; ?>">
                            <header><span class="goal-card-icon"><?php echo !empty($meta['modalidade_slug']) ? stridebr_sport_icon_html((string)$meta['modalidade_slug'],'sport-icon') : ''; ?></span><div><span><?php echo stridebr_e(stridebr_sport_name((string)($meta['modalidade_slug']??''),(string)($meta['modalidade_nome']??''))); ?></span><h3><?php echo stridebr_e(benchmarkGoalTitle($meta)); ?></h3></div><?php if ($percentAvailable): ?><strong class="goal-percent"><?php echo (int)round((float)$meta['percentual']); ?>%</strong><?php endif; ?></header>
                            <?php if ($percentAvailable): ?><div class="goal-progress"><span style="width:<?php echo number_format((float)$meta['percentual'],2,'.',''); ?>%"></span></div><?php endif; ?>
                            <div class="goal-benchmark-values"><div><span><?php echo stridebr_e(stridebr_t('goals.benchmark.best_since_start')); ?></span><strong><?php echo $hasResult ? stridebr_e(benchmarkFormatValue($type,$bestValue,(float)($meta['benchmark_distancia_m']??0))) : '—'; ?></strong></div><div><span><?php echo stridebr_e(stridebr_t('goals.target')); ?></span><strong><?php echo stridebr_e(benchmarkFormatValue($type,$target,(float)($meta['benchmark_distancia_m']??0))); ?></strong></div></div>
                            <?php if (is_numeric($meta['valor_inicial']??null)): ?><div class="goal-benchmark-baseline"><span><?php echo stridebr_e(stridebr_t('goals.benchmark.at_creation')); ?></span><strong><?php echo stridebr_e(benchmarkFormatValue($type,(float)$meta['valor_inicial'],(float)($meta['benchmark_distancia_m']??0))); ?></strong></div><?php endif; ?>
                            <div class="goal-status"><span><?php echo stridebr_e(dashboardMetaPrazoLabel($meta)); ?></span><strong><?php if (!empty($meta['atingida'])) echo stridebr_e(stridebr_t('goals.benchmark.completed')); elseif (!empty($meta['expirada'])) echo stridebr_e(stridebr_t('goals.benchmark.expired')); elseif (!$hasResult) echo stridebr_e(match($type){'one_rm'=>stridebr_t('goals.benchmark.no_one_rm'),'ftp'=>stridebr_t('goals.benchmark.no_ftp'),'css'=>stridebr_t('goals.benchmark.no_css'),'distance_time'=>stridebr_t('goals.benchmark.no_test'),default=>stridebr_t('goals.benchmark.no_result')}); else echo stridebr_e(stridebr_t('goals.benchmark.remaining') . ': ' . benchmarkFormatAbsoluteDifference($type,$remaining)); ?></strong><?php if (!$hasResult): ?><a href="<?php echo stridebr_e(benchmarkGoalResultHref($meta)); ?>"><?php echo stridebr_e(stridebr_t('goals.benchmark.register_result')); ?></a><?php elseif (!$percentAvailable && empty($meta['atingida'])): ?><small><?php echo stridebr_e(stridebr_t('goals.benchmark.progress_unavailable')); ?></small><?php endif; ?></div>
                            <footer><a href="/user/metas.php?edit=<?php echo rawurlencode((string)$meta['idmeta']); ?>#meta-form"><?php echo stridebr_e(stridebr_t('common.edit')); ?></a><form method="POST" data-confirm="<?php echo stridebr_e(stridebr_t('goals.archive_confirm')); ?>"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="archive"><input type="hidden" name="idmeta" value="<?php echo stridebr_e((string)$meta['idmeta']); ?>"><button type="submit"><?php echo stridebr_e(stridebr_t('goals.archive')); ?></button></form></footer>
                        </article>
                        <?php else:
                            $unit=dashboardMetaUnidade((string)$meta['metrica']); $integer=in_array((string)$meta['metrica'],['atividades','dias_ativos'],true); $progress=(float)$meta['progresso']; $target=(float)$meta['valor_alvo']; $remaining=(float)$meta['restante'];
                        ?>
                        <article class="goal-card<?php echo !empty($meta['atingida'])?' is-complete':''; ?><?php echo !empty($meta['expirada'])?' is-expired':''; ?>">
                            <header><span class="goal-card-icon"><?php if (!empty($meta['modalidade_slug'])) echo stridebr_sport_icon_html((string)$meta['modalidade_slug'],'sport-icon'); else echo '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"></circle><circle cx="12" cy="12" r="4"></circle><circle cx="12" cy="12" r="1"></circle></svg>'; ?></span><div><span><?php echo stridebr_e((string)($meta['modalidade_nome']?:stridebr_t('goals.all_sports'))); ?></span><h3><?php echo stridebr_e(dashboardMetaTitulo($meta)); ?></h3></div><strong class="goal-percent"><?php echo (int)round((float)$meta['percentual_real']); ?>%</strong></header>
                            <div class="goal-progress"><span style="width:<?php echo number_format((float)$meta['percentual'],2,'.',''); ?>%"></span></div>
                            <div class="goal-numbers"><strong><?php echo stridebr_e(dashboardFormatarNumero($progress,$integer?0:1)); ?> <small><?php echo stridebr_e($unit); ?></small></strong><span><?php echo stridebr_e(stridebr_t('goals.of_target',['value'=>dashboardFormatarNumero($target,$integer?0:1),'unit'=>$unit])); ?></span></div>
                            <div class="goal-status"><span><?php echo stridebr_e(dashboardMetaMetrica((string)$meta['metrica'])); ?> · <?php echo stridebr_e(dashboardMetaPrazoLabel($meta)); ?></span><strong><?php echo stridebr_e(!empty($meta['atingida'])?stridebr_t('goals.benchmark.completed'):(!empty($meta['expirada'])?stridebr_t('goals.benchmark.expired'):stridebr_t('goals.benchmark.remaining').': '.dashboardFormatarNumero($remaining,$integer?0:1).' '.$unit)); ?></strong></div>
                            <footer><a href="/user/metas.php?edit=<?php echo rawurlencode((string)$meta['idmeta']); ?>#meta-form"><?php echo stridebr_e(stridebr_t('common.edit')); ?></a><form method="POST" data-confirm="<?php echo stridebr_e(stridebr_t('goals.archive_confirm')); ?>"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="archive"><input type="hidden" name="idmeta" value="<?php echo stridebr_e((string)$meta['idmeta']); ?>"><button type="submit"><?php echo stridebr_e(stridebr_t('goals.archive')); ?></button></form></footer>
                        </article>
                        <?php endif; endforeach; ?>
                    </div>
                    <?php endif; ?>
                </section>

                <?php $arquivadas=array_values(array_filter($metas,static fn(array $meta):bool=>!stridebr_db_bool($meta['ativa']??true))); ?>
                <section class="goals-section goals-history" data-goals-section="history">
                    <div class="section-title-row"><div><h2><?php echo stridebr_e(stridebr_t('common.history')); ?></h2><p><?php echo stridebr_e(stridebr_t('goals.history_help')); ?></p></div></div>
                    <?php if ($conclusoes===[]&&$arquivadas===[]): ?><div class="content-card goals-empty"><strong><?php echo stridebr_e(stridebr_t('goals.history_empty')); ?></strong><p><?php echo stridebr_e(stridebr_t('goals.history_empty_help')); ?></p></div><?php else: ?>
                    <div class="content-card goals-history-list">
                        <?php foreach ($conclusoes as $conclusao): $isBenchmark=(string)($conclusao['tipo_meta']??'metrica')==='benchmark'; ?>
                        <div class="goal-history-row"><span class="goal-history-check">✓</span><div><strong><?php echo stridebr_e($isBenchmark?benchmarkGoalTitle($conclusao):dashboardMetaTitulo($conclusao)); ?></strong><?php if ($isBenchmark): ?><span><?php echo stridebr_e(stridebr_t('goals.benchmark.completed_on',['date'=>stridebr_format_date((string)($conclusao['data_resultado']??$conclusao['atingida_em']))])); ?> · <?php echo stridebr_e(benchmarkFormatValue((string)$conclusao['benchmark_tipo'],(float)$conclusao['valor_atingido'],is_numeric($conclusao['benchmark_distancia_m']??null)?(float)$conclusao['benchmark_distancia_m']:null)); ?></span><?php else: ?><span><?php echo stridebr_e(stridebr_format_date((string)$conclusao['periodo_inicio'])); ?> – <?php echo stridebr_e(stridebr_format_date((string)$conclusao['periodo_fim'])); ?> · <?php echo stridebr_e(dashboardFormatarNumero((float)$conclusao['valor_atingido'],in_array((string)$conclusao['metrica'],['atividades','dias_ativos'],true)?0:1)); ?> <?php echo stridebr_e(dashboardMetaUnidade((string)$conclusao['metrica'])); ?></span><?php endif; ?></div><?php if ($isBenchmark && !empty($conclusao['idbenchmark'])): ?><a href="/user/progresso.php?sport=<?php echo rawurlencode((string)($conclusao['modalidade_slug']??'')); ?>&edit_benchmark=<?php echo rawurlencode((string)$conclusao['idbenchmark']); ?>"><?php echo stridebr_e(stridebr_t('goals.benchmark.view_result')); ?></a><?php else: ?><time datetime="<?php echo stridebr_e((string)$conclusao['atingida_em']); ?>"><?php echo stridebr_e(stridebr_format_date((string)$conclusao['atingida_em'])); ?></time><?php endif; ?></div>
                        <?php endforeach; ?>
                        <?php foreach ($arquivadas as $meta): $archivedBenchmark=(string)($meta['tipo_meta']??'metrica')==='benchmark'; ?><div class="goal-history-row is-archived"><span class="goal-history-check">—</span><div><strong><?php echo stridebr_e(dashboardMetaTitulo($meta)); ?></strong><span><?php echo stridebr_e(stridebr_t('goals.archived_label',['metric'=>$archivedBenchmark?stridebr_t('goals.type.benchmark'):dashboardMetaMetrica((string)$meta['metrica'])])); ?></span></div><?php if (!$archivedBenchmark || empty($meta['concluida_em'])): ?><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="reactivate"><input type="hidden" name="idmeta" value="<?php echo stridebr_e((string)$meta['idmeta']); ?>"><button class="secondary-button" type="submit"><?php echo stridebr_e(stridebr_t('goals.reactivate')); ?></button></form><?php endif; ?></div><?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
    </main>
</div>
<script type="application/json" id="goal-benchmark-data"><?php echo json_encode(['rows'=>$benchmarkClientRows,'registry'=>$benchmarkClientRegistry], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?></script>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/goals.js')); ?>"></script>
</body>
</html>
