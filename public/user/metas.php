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
            productAnalyticsRegistrar($pdo, $idUsuario, 'goal_created', ['metric' => substr((string) ($_POST['metrica'] ?? ''), 0, 40)]);
            stridebr_flash('success', stridebr_t('goals.flash.created'));
        } elseif ($action === 'edit') {
            $idMeta = trim((string) ($_POST['idmeta'] ?? ''));
            dashboardEditarMeta($pdo, $idUsuario, $idMeta, $_POST);
            stridebr_flash('success', 'Meta atualizada.');
        } elseif ($action === 'archive') {
            $idMeta = trim((string) ($_POST['idmeta'] ?? ''));
            if (!dashboardArquivarMeta($pdo, $idUsuario, $idMeta)) throw new InvalidArgumentException(stridebr_t('goals.error.not_found'));
            stridebr_flash('success', stridebr_t('goals.flash.archived'));
        } elseif ($action === 'reactivate') {
            $idMeta = trim((string) ($_POST['idmeta'] ?? ''));
            if (!dashboardReativarMeta($pdo, $idUsuario, $idMeta)) throw new InvalidArgumentException(stridebr_t('goals.error.not_found'));
            stridebr_flash('success', 'Meta reativada.');
        } else {
            throw new InvalidArgumentException(stridebr_t('goals.error.invalid_action'));
        }
        header('Location: /user/metas.php');
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException
            ? $e->getMessage()
            : stridebr_t('goals.error.update');
        if (!$e instanceof InvalidArgumentException && !$e instanceof RuntimeException) error_log($e->getMessage());
    }
}

$modalidades = $available ? dashboardListarModalidades($pdo, $idUsuario) : [];
$exerciciosMeta = $available ? dashboardListarExerciciosMeta($pdo, $idUsuario) : [];
$metas = $available ? dashboardListarMetas($pdo, $idUsuario, false) : [];
$conclusoes = $available ? dashboardListarConclusoesMetas($pdo, $idUsuario, 40) : [];
$editId = trim((string) ($_GET['edit'] ?? ''));
$editMeta = null;
foreach ($metas as $meta) {
    if ((string) $meta['idmeta'] === $editId) {
        $editMeta = $meta;
        break;
    }
}
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
                <div><span class="dashboard-eyebrow"><?php echo stridebr_e(stridebr_t('goals.progress')); ?></span><h1><?php echo stridebr_e(stridebr_t('goals.page_title')); ?></h1><p><?php echo stridebr_e(stridebr_t('goals.subtitle')); ?></p></div>
                <?php if ($available): ?><a class="primary-button" href="/user/metas.php?new=1">+ <?php echo stridebr_e(stridebr_t('goals.new')); ?></a><?php endif; ?>
            </header>

            <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e((string) ($flash['type'] ?? 'info')); ?>"><?php echo stridebr_e((string) ($flash['message'] ?? '')); ?></div><?php endforeach; ?>
            <?php if (!$showForm): ?><?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?><?php endif; ?>

            <?php if (!$available): ?>
                <section class="content-card"><h2><?php echo stridebr_e(stridebr_t('goals.unavailable')); ?></h2></section>
            <?php else: ?>
                <?php if ($showForm):
                    $form = $editMeta ?? [];
                    $formMetric = (string) ($form['metrica'] ?? ($_POST['metrica'] ?? 'distancia'));
                    $formPeriod = (string) ($form['periodo'] ?? ($_POST['periodo'] ?? 'continuo'));
                    $defaultGoalStart = (new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
                    $formSport = (string) ($form['idmodalidade'] ?? ($_POST['idmodalidade'] ?? ''));
                    $formExercise = (string) ($form['idexercicio'] ?? ($_POST['idexercicio'] ?? ''));
                    $formTarget = (string) ($form['valor_alvo'] ?? ($_POST['valor_alvo'] ?? ''));
                    $formName = (string) ($form['nome'] ?? ($_POST['nome'] ?? ''));
                    $formStart = (string) ($form['data_inicio'] ?? ($_POST['data_inicio'] ?? ($formPeriod === 'personalizado' || $formPeriod === 'continuo' ? $defaultGoalStart : '')));
                    $formEnd = (string) ($form['data_fim'] ?? ($_POST['data_fim'] ?? ''));
                ?>
                <div class="goals-editor-modal" data-goals-editor role="dialog" aria-modal="true" aria-labelledby="goal-editor-title">
                    <a class="goals-editor-backdrop" href="/user/metas.php" aria-label="<?php echo stridebr_e(stridebr_t('goals.close_editor')); ?>"></a>
                    <section class="content-card goals-editor" id="meta-form">
                        <div class="goals-editor-header"><div><h2 id="goal-editor-title"><?php echo stridebr_e($editMeta ? stridebr_t('goals.edit') : stridebr_t('goals.new')); ?></h2><p><?php echo stridebr_e(stridebr_t('goals.editor_help')); ?></p></div><a class="goals-editor-close" href="/user/metas.php" aria-label="<?php echo stridebr_e(stridebr_t('goals.close_editor')); ?>">×</a></div>
                        <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
                        <form method="POST" class="goals-form" data-goals-form>
                        <?php echo stridebr_csrf_field(); ?>
                        <input type="hidden" name="action" value="<?php echo $editMeta ? 'edit' : 'create'; ?>">
                        <?php if ($editMeta): ?><input type="hidden" name="idmeta" value="<?php echo stridebr_e((string) $editMeta['idmeta']); ?>"><?php endif; ?>

                        <div class="goals-form-layout">
                            <section class="goals-form-block" aria-labelledby="goal-track-heading">
                                <div class="goals-form-block-heading"><span>1</span><div><strong id="goal-track-heading"><?php echo stridebr_e(stridebr_t('home.what_to_track')); ?></strong><small><?php echo stridebr_e(stridebr_t('goals.track_help')); ?></small></div></div>
                                <div class="goal-sport-field"><span class="goal-field-label"><?php echo stridebr_e(stridebr_t('common.sport')); ?></span><?php echo sportPickerRenderSelect($modalidades, ['name' => 'idmodalidade', 'selected' => $formSport, 'empty_label' => stridebr_t('goals.all_sports'), 'placeholder' => stridebr_t('goals.choose_sport')]); ?></div>
                                <label><?php echo stridebr_e(stridebr_t('home.metric')); ?><select name="metrica" data-goal-metric><option value="distancia"<?php echo $formMetric === 'distancia' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('common.distance')); ?></option><option value="duracao"<?php echo $formMetric === 'duracao' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('activity.summary.time')); ?></option><option value="atividades"<?php echo $formMetric === 'atividades' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('activity.page_title')); ?></option><option value="elevacao"<?php echo $formMetric === 'elevacao' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('common.elevation')); ?></option><option value="dias_ativos"<?php echo $formMetric === 'dias_ativos' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('home.active_days')); ?></option><option value="carga_maxima"<?php echo $formMetric === 'carga_maxima' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('home.exercise_max_load')); ?></option></select></label>
                                <label class="goal-exercise-field" data-goal-exercise-field<?php echo $formMetric === 'carga_maxima' ? '' : ' hidden'; ?>><?php echo stridebr_e(stridebr_t('home.exercise')); ?><select name="idexercicio" data-goal-exercise><option value=""><?php echo stridebr_e(stridebr_t('home.choose_exercise')); ?></option><?php foreach ($exerciciosMeta as $exercicio): ?><option value="<?php echo stridebr_e((string) $exercicio['idexercicio']); ?>" data-modalidades="<?php echo stridebr_e((string) ($exercicio['modalidades'] ?? '')); ?>"<?php echo $formExercise === (string) $exercicio['idexercicio'] ? ' selected' : ''; ?>><?php echo stridebr_e((string) $exercicio['nome']); ?></option><?php endforeach; ?></select><small class="goal-field-help"><?php echo stridebr_e(stridebr_t('goals.load_help')); ?></small></label>
                            </section>

                            <section class="goals-form-block" aria-labelledby="goal-target-heading">
                                <div class="goals-form-block-heading"><span>2</span><div><strong id="goal-target-heading"><?php echo stridebr_e(stridebr_t('goals.define_target')); ?></strong><small><?php echo stridebr_e(stridebr_t('goals.target_help')); ?></small></div></div>
                                <label><?php echo stridebr_e(stridebr_t('goals.target')); ?><span class="dashboard-target-input"><input name="valor_alvo" value="<?php echo stridebr_e($formTarget); ?>" inputmode="decimal" required placeholder="20"><span data-goal-unit><?php echo stridebr_e(dashboardMetaUnidade($formMetric)); ?></span></span></label>
                                <label><?php echo stridebr_e(stridebr_t('goals.deadline')); ?><select name="periodo" data-goal-period><option value="continuo"<?php echo $formPeriod === 'continuo' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('home.no_deadline')); ?></option><option value="personalizado"<?php echo $formPeriod === 'personalizado' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('home.until_date')); ?></option><option value="semanal"<?php echo $formPeriod === 'semanal' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('schedule.every_week')); ?></option><option value="mensal"<?php echo $formPeriod === 'mensal' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('home.every_month')); ?></option><option value="anual"<?php echo $formPeriod === 'anual' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('home.every_year')); ?></option></select><small class="goal-field-help"><?php echo stridebr_e(stridebr_t('goals.period_help')); ?></small></label>
                                <div class="goals-custom-dates<?php echo $formPeriod === 'personalizado' ? ' is-visible' : ''; ?>" data-goal-custom-dates><label><?php echo stridebr_e(stridebr_t('goals.start_counting')); ?><input type="date" name="data_inicio" value="<?php echo stridebr_e($formStart); ?>"<?php echo $formPeriod === 'personalizado' ? ' required' : ''; ?>></label><label><?php echo stridebr_e(stridebr_t('home.reach_by')); ?><input type="date" name="data_fim" value="<?php echo stridebr_e($formEnd); ?>"<?php echo $formPeriod === 'personalizado' ? ' required' : ''; ?>></label></div>
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
                        <?php foreach ($ativas as $meta):
                            $unit = dashboardMetaUnidade((string) $meta['metrica']);
                            $integer = in_array((string) $meta['metrica'], ['atividades', 'dias_ativos'], true);
                            $progress = (float) $meta['progresso'];
                            $target = (float) $meta['valor_alvo'];
                            $remaining = (float) $meta['restante'];
                        ?>
                        <article class="goal-card<?php echo !empty($meta['atingida']) ? ' is-complete' : ''; ?><?php echo !empty($meta['expirada']) ? ' is-expired' : ''; ?>">
                            <header><span class="goal-card-icon"><?php if (!empty($meta['modalidade_slug'])) echo stridebr_sport_icon_html((string) $meta['modalidade_slug'], 'sport-icon'); else echo '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"></circle><circle cx="12" cy="12" r="4"></circle><circle cx="12" cy="12" r="1"></circle></svg>'; ?></span><div><span><?php echo stridebr_e((string) ($meta['modalidade_nome'] ?: 'Todos os esportes')); ?></span><h3><?php echo stridebr_e(dashboardMetaTitulo($meta)); ?></h3></div><strong class="goal-percent"><?php echo (int) round((float) $meta['percentual_real']); ?>%</strong></header>
                            <div class="goal-progress"><span style="width: <?php echo number_format((float) $meta['percentual'], 2, '.', ''); ?>%"></span></div>
                            <div class="goal-numbers"><strong><?php echo stridebr_e(dashboardFormatarNumero($progress, $integer ? 0 : 1)); ?> <small><?php echo stridebr_e($unit); ?></small></strong><span><?php echo stridebr_e(stridebr_t('goals.of_target', ['value' => dashboardFormatarNumero($target, $integer ? 0 : 1), 'unit' => $unit])); ?></span></div>
                            <div class="goal-status"><span><?php echo stridebr_e(dashboardMetaMetrica((string) $meta['metrica'])); ?> · <?php echo stridebr_e(dashboardMetaPrazoLabel($meta)); ?></span><strong><?php echo !empty($meta['atingida']) ? 'Meta atingida' : (!empty($meta['expirada']) ? 'Prazo encerrado' : 'Faltam ' . dashboardFormatarNumero($remaining, $integer ? 0 : 1) . ' ' . $unit); ?></strong></div>
                            <footer><a href="/user/metas.php?edit=<?php echo rawurlencode((string) $meta['idmeta']); ?>#meta-form"><?php echo stridebr_e(stridebr_t('common.edit')); ?></a><form method="POST" data-confirm="<?php echo stridebr_e(stridebr_t('goals.archive_confirm')); ?>"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="archive"><input type="hidden" name="idmeta" value="<?php echo stridebr_e((string) $meta['idmeta']); ?>"><button type="submit"><?php echo stridebr_e(stridebr_t('goals.archive')); ?></button></form></footer>
                        </article>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </section>

                <?php $arquivadas = array_values(array_filter($metas, static fn(array $meta): bool => !stridebr_db_bool($meta['ativa'] ?? true))); ?>
                <section class="goals-section goals-history" data-goals-section="history">
                    <div class="section-title-row"><div><h2><?php echo stridebr_e(stridebr_t('common.history')); ?></h2><p><?php echo stridebr_e(stridebr_t('goals.history_help')); ?></p></div></div>
                    <?php if ($conclusoes === [] && $arquivadas === []): ?><div class="content-card goals-empty"><strong><?php echo stridebr_e(stridebr_t('goals.history_empty')); ?></strong><p><?php echo stridebr_e(stridebr_t('goals.history_empty_help')); ?></p></div><?php else: ?>
                    <div class="content-card goals-history-list">
                        <?php foreach ($conclusoes as $conclusao): ?><div class="goal-history-row"><span class="goal-history-check">✓</span><div><strong><?php $conclusaoTitulo = trim((string) ($conclusao['nome'] ?? '')); if ($conclusaoTitulo === '') $conclusaoTitulo = ((string) ($conclusao['metrica'] ?? '')) === 'carga_maxima' && !empty($conclusao['exercicio_nome']) ? ((string) $conclusao['exercicio_nome'] . ' · ' . stridebr_t('goals.max_load')) : (stridebr_sport_name((string) ($conclusao['modalidade_slug'] ?? ''), (string) ($conclusao['modalidade_nome'] ?: stridebr_t('goals.all_sports'))) . ' · ' . dashboardMetaMetrica((string) $conclusao['metrica'])); echo stridebr_e($conclusaoTitulo); ?></strong><span><?php echo stridebr_e(stridebr_format_date((string) $conclusao['periodo_inicio'])); ?> – <?php echo stridebr_e(stridebr_format_date((string) $conclusao['periodo_fim'])); ?> · <?php echo stridebr_e(dashboardFormatarNumero((float) $conclusao['valor_atingido'], in_array((string) $conclusao['metrica'], ['atividades', 'dias_ativos'], true) ? 0 : 1)); ?> <?php echo stridebr_e(dashboardMetaUnidade((string) $conclusao['metrica'])); ?></span></div><time datetime="<?php echo stridebr_e((string) $conclusao['atingida_em']); ?>"><?php echo stridebr_e(stridebr_format_date((string) $conclusao['atingida_em'])); ?></time></div><?php endforeach; ?>
                        <?php foreach ($arquivadas as $meta): ?><div class="goal-history-row is-archived"><span class="goal-history-check">—</span><div><strong><?php echo stridebr_e(dashboardMetaTitulo($meta)); ?></strong><span><?php echo stridebr_e(stridebr_t('goals.archived_label', ['metric' => dashboardMetaMetrica((string) $meta['metrica'])])); ?></span></div><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="reactivate"><input type="hidden" name="idmeta" value="<?php echo stridebr_e((string) $meta['idmeta']); ?>"><button class="secondary-button" type="submit"><?php echo stridebr_e(stridebr_t('goals.reactivate')); ?></button></form></div><?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/goals.js')); ?>"></script>
</body>
</html>
