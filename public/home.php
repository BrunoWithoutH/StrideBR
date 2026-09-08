<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/function/dashboard.php';
require_once dirname(__DIR__) . '/src/function/cronograma.php';
require_once dirname(__DIR__) . '/src/function/eventos.php';
require_once dirname(__DIR__) . '/src/includes/sport_icons.php';
require_once dirname(__DIR__) . '/src/layout/sport_picker.php';
require_once dirname(__DIR__) . '/src/layout/ads.php';

$user = stridebr_display_name();
$errors = [];
$metasDisponiveis = dashboardMetasDisponiveis($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'criar_meta') {
            dashboardCriarMeta($pdo, $idUsuario, $_POST);
            stridebr_flash('success', stridebr_t('home.goal_created'));
        } elseif ($action === 'arquivar_meta') {
            $idMeta = trim((string) ($_POST['idmeta'] ?? ''));
            if ($idMeta === '' || !dashboardArquivarMeta($pdo, $idUsuario, $idMeta)) {
                throw new InvalidArgumentException(stridebr_t('home.goal_not_found'));
            }
            stridebr_flash('success', stridebr_t('home.goal_removed'));
        }
        header('Location: /home.php');
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException
            ? $e->getMessage()
            : stridebr_t('home.dashboard_update_error');
        if (!$e instanceof InvalidArgumentException && !$e instanceof RuntimeException) {
            error_log($e->getMessage());
        }
    }
}

$weekOffset = max(-520, min(52, (int) ($_GET['week'] ?? 0)));
$activityOverview = dashboardVisaoAtividades($pdo, $idUsuario, $weekOffset);
$resumo = $activityOverview['resumo'];
$dias = $activityOverview['dias'];
$recentes = dashboardAtividadesRecentes($pdo, $idUsuario, 3);
$proximos = dashboardTreinosProximos($pdo, $idUsuario, 5);
$metas = $metasDisponiveis ? dashboardListarMetas($pdo, $idUsuario) : [];
$goalModalidades = $metasDisponiveis ? dashboardListarModalidades($pdo, $idUsuario) : [];
$contextoHoje = dashboardContextoHoje($pdo, $idUsuario);
$flashes = stridebr_take_flashes();


$tz = new DateTimeZone('America/Sao_Paulo');
$hoje = new DateTimeImmutable('today', $tz);
$amanha = $hoje->modify('+1 day');
$todayLabel = stridebr_format_date_weekday($hoje);
$eventosHoje = [];
try {
    foreach (eventosListarPublicados($pdo, ['salvos' => true, 'mes' => $hoje->format('Y-m')], $idUsuario, 20) as $event) {
        if ((string) ($event['status'] ?? '') === 'cancelado' || empty($event['data_inicio'])) continue;
        $eventDate = (new DateTimeImmutable((string) $event['data_inicio']))->setTimezone($tz);
        if ($eventDate->format('Y-m-d') === $hoje->format('Y-m-d')) $eventosHoje[] = $event;
    }
} catch (Throwable $e) {
    error_log('StrideBR home events: ' . $e->getMessage());
}
if (in_array((string) ($contextoHoje['state'] ?? ''), ['rest', 'free'], true) && $recentes !== []) {
    $latestToday = new DateTimeImmutable((string) $recentes[0]['data_inicio']);
    if ($latestToday->setTimezone($tz)->format('Y-m-d') === $hoje->format('Y-m-d')) {
        $contextoHoje['state'] = 'activity';
        $contextoHoje['activity'] = $recentes[0];
    }
}
$weekSummaryParts = [stridebr_tn($weekOffset === 0 ? 'home.activities_week.one' : 'home.activities_selected_week.one', $weekOffset === 0 ? 'home.activities_week.other' : 'home.activities_selected_week.other', (int) $resumo['atividades'])];
if ((float) $resumo['duracao_s'] > 0) $weekSummaryParts[] = dashboardFormatarDuracao((float) $resumo['duracao_s']);
if ((float) $resumo['distancia_km'] > 0) $weekSummaryParts[] = dashboardFormatarNumero((float) $resumo['distancia_km']) . ' km';
if ((float) $resumo['elevacao_m'] > 0) $weekSummaryParts[] = '+' . dashboardFormatarNumero((float) $resumo['elevacao_m'], 0) . ' m';
$weekSummary = implode(' · ', $weekSummaryParts);
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
    <title><?php echo stridebr_e(stridebr_t('home.page_title')); ?></title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__) . '/src/layout/header.php'; ?>
    <main class="main-content dashboard-page" data-dashboard-root data-dashboard-csrf="<?php echo stridebr_e(stridebr_csrf_token()); ?>">
        <div class="dashboard-shell">
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?php echo stridebr_e((string) ($flash['type'] ?? 'info')); ?>"><?php echo stridebr_e((string) ($flash['message'] ?? '')); ?></div>
            <?php endforeach; ?>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>

            <header class="dashboard-heading">
                <div>
                    <span class="dashboard-eyebrow"><?php echo stridebr_e(stridebr_t('nav.home')); ?></span>
                    <h1><?php echo stridebr_e(stridebr_t('home.greeting', ['name' => $user])); ?></h1>
                </div>
                <div class="dashboard-heading-actions">
                    <a class="dashboard-button dashboard-button-secondary" href="/user/gravar-atividade.php"><?php echo stridebr_e(stridebr_t('home.record_gps')); ?></a>
                    <a class="dashboard-button dashboard-button-primary" href="/user/atividades.php?new=1">+ <?php echo stridebr_e(stridebr_t('home.log_activity')); ?></a>
                </div>
            </header>

            <section class="dashboard-today" aria-labelledby="dashboard-today-title" data-dashboard-today>
                <div class="dashboard-today-date">
                    <span class="dashboard-eyebrow"><?php echo stridebr_e(stridebr_t('home.today')); ?></span>
                    <strong><?php echo stridebr_e($todayLabel); ?></strong>
                </div>
                <div class="dashboard-today-main">
                    <?php if ($contextoHoje['state'] === 'active'):
                        $active = $contextoHoje['active'];
                        $activeStart = !empty($active['data_inicio']) ? new DateTimeImmutable((string) $active['data_inicio']) : null;
                    ?>
                        <span class="dashboard-today-status is-active"><i></i> <?php echo stridebr_e(stridebr_t('home.in_progress')); ?></span>
                        <h2 id="dashboard-today-title"><?php echo stridebr_e((string) ($active['titulo_snapshot'] ?? stridebr_t('home.default_workout'))); ?></h2>
                        <p><?php echo $activeStart ? stridebr_e(stridebr_t('home.started_at', ['time' => $activeStart->setTimezone($tz)->format('H:i')])) : stridebr_e(stridebr_t('home.workout_running')); ?></p>
                    <?php elseif ($contextoHoje['state'] === 'planned'):
                        $primaryToday = $contextoHoje['primary'];
                        $todayTime = trim((string) ($primaryToday['hora_inicio'] ?? ''));
                        $todayMeta = trim((string) ($primaryToday['cronograma_nome'] ?? ''));
                    ?>
                        <span class="dashboard-today-status"><?php echo stridebr_e(stridebr_t('home.planned')); ?></span>
                        <h2 id="dashboard-today-title"><?php echo stridebr_e((string) ($primaryToday['titulo'] ?? stridebr_t('home.default_workout'))); ?></h2>
                        <p><?php echo $todayTime !== '' ? stridebr_e($todayTime) : stridebr_e(stridebr_t('common.today')); ?><?php echo $todayMeta !== '' ? ' · ' . stridebr_e($todayMeta) : ''; ?></p>
                    <?php elseif ($contextoHoje['state'] === 'activity'):
                        $todayActivity = $contextoHoje['activity'];
                        $todayActivityTitle = trim((string) ($todayActivity['titulo'] ?? '')) ?: stridebr_sport_name((string) ($todayActivity['modalidade_slug'] ?? ''), (string) ($todayActivity['modalidade_nome'] ?? stridebr_t('home.default_activity')));
                    ?>
                        <span class="dashboard-today-status is-complete"><?php echo stridebr_e(stridebr_t('home.logged')); ?></span>
                        <h2 id="dashboard-today-title"><?php echo stridebr_e($todayActivityTitle); ?></h2>

                    <?php elseif ($contextoHoje['state'] === 'completed'): ?>
                        <span class="dashboard-today-status is-complete"><?php echo stridebr_e(stridebr_t('home.completed')); ?></span>
                        <h2 id="dashboard-today-title"><?php echo stridebr_e(stridebr_t('home.today_completed')); ?></h2>

                    <?php elseif ($contextoHoje['state'] === 'rest'): ?>
                        <span class="dashboard-today-status is-rest"><?php echo stridebr_e(stridebr_t('home.rest')); ?></span>
                        <h2 id="dashboard-today-title"><?php echo stridebr_e(stridebr_t('home.rest_day')); ?></h2>

                    <?php else: ?>
                        <span class="dashboard-today-status"><?php echo stridebr_e(stridebr_t('home.today')); ?></span>
                        <h2 id="dashboard-today-title"><?php echo stridebr_e(stridebr_t('home.no_workout_today')); ?></h2>

                    <?php endif; ?>
                </div>
                <div class="dashboard-today-actions">
                    <?php if ($contextoHoje['state'] === 'active'): ?>
                        <button type="button" class="dashboard-button dashboard-button-primary" data-open-workout-session><?php echo stridebr_e(stridebr_t('home.continue_workout')); ?></button>
                    <?php elseif ($contextoHoje['state'] === 'planned' && !empty($contextoHoje['primary'])):
                        $primaryToday = $contextoHoje['primary'];
                    ?>
                        <?php if (($primaryToday['kind'] ?? '') === 'scheduled'): ?>
                            <button type="button" class="dashboard-button dashboard-button-primary" data-dashboard-start-scheduled="<?php echo stridebr_e((string) $primaryToday['idagendamento']); ?>"><?php echo stridebr_e(stridebr_t('home.start_workout')); ?></button>
                        <?php else: ?>
                            <button type="button" class="dashboard-button dashboard-button-primary"
                                data-dashboard-start-workout="<?php echo stridebr_e((string) $primaryToday['idtreino']); ?>"
                                data-occurrence-origin="<?php echo stridebr_e((string) ($primaryToday['data_original'] ?? $contextoHoje['date'])); ?>"
                                data-occurrence-planned="<?php echo stridebr_e((string) ($primaryToday['data_treino'] ?? $contextoHoje['date'])); ?>"
                                data-occurrence-time="<?php echo stridebr_e((string) ($primaryToday['hora_inicio'] ?? '')); ?>"><?php echo stridebr_e(stridebr_t('home.start_workout')); ?></button>
                        <?php endif; ?>
                        <a class="dashboard-button dashboard-button-secondary" href="/user/cronogramatreinos.php"><?php echo stridebr_e(stridebr_t('home.view_agenda')); ?></a>
                    <?php elseif ($contextoHoje['state'] === 'activity'): ?>
                        <a class="dashboard-button dashboard-button-primary" href="/user/atividades.php?highlight=<?php echo rawurlencode((string) ($contextoHoje['activity']['idregistro'] ?? '')); ?>"><?php echo stridebr_e(stridebr_t('home.view_activity')); ?></a>
                        <a class="dashboard-button dashboard-button-secondary" href="/user/atividades.php?new=1"><?php echo stridebr_e(stridebr_t('home.log_another')); ?></a>
                    <?php elseif ($contextoHoje['state'] === 'completed'): ?>
                        <?php $completedToday = $contextoHoje['completed'][0] ?? null; ?>
                        <?php if ($completedToday && !empty($completedToday['idregistro'])): ?><a class="dashboard-button dashboard-button-primary" href="/user/atividades.php?highlight=<?php echo rawurlencode((string) $completedToday['idregistro']); ?>"><?php echo stridebr_e(stridebr_t('home.view_activity')); ?></a><?php endif; ?>
                        <a class="dashboard-button dashboard-button-secondary" href="/user/atividades.php?new=1"><?php echo stridebr_e(stridebr_t('home.log_another')); ?></a>
                    <?php elseif ($contextoHoje['state'] === 'rest'): ?>
                        <a class="dashboard-button dashboard-button-secondary" href="/user/cronogramatreinos.php"><?php echo stridebr_e(stridebr_t('home.view_next_workout')); ?></a>
                        <a class="dashboard-button dashboard-button-secondary" href="/user/atividades.php?new=1"><?php echo stridebr_e(stridebr_t('progress.log_activity')); ?></a>
                    <?php else: ?>
                        <a class="dashboard-button dashboard-button-primary" href="/user/atividades.php?new=1"><?php echo stridebr_e(stridebr_t('home.log_activity')); ?></a>
                        <a class="dashboard-button dashboard-button-secondary" href="/user/gravar-atividade.php"><?php echo stridebr_e(stridebr_t('home.record_gps')); ?></a>
                    <?php endif; ?>
                </div>
                <?php if (count($contextoHoje['items'] ?? []) > 1): ?>
                    <div class="dashboard-today-list" aria-label="<?php echo stridebr_e(stridebr_t('home.other_today_items')); ?>">
                        <?php foreach ($contextoHoje['items'] as $item): ?>
                            <span class="dashboard-today-chip<?php echo !empty($item['concluido']) ? ' is-complete' : ''; ?>"><strong><?php echo stridebr_e((string) ($item['titulo'] ?? stridebr_t('home.default_workout'))); ?></strong><small><?php echo stridebr_e((string) ($item['hora_inicio'] ?? '')); ?><?php echo !empty($item['concluido']) ? ' · ' . stridebr_t('home.completed') : ''; ?></small></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($eventosHoje !== []): $eventoHoje = $eventosHoje[0]; $eventoData = (new DateTimeImmutable((string) $eventoHoje['data_inicio']))->setTimezone($tz); ?>
            <section class="dashboard-today-event" aria-label="<?php echo stridebr_e(stridebr_t('home.event_today')); ?>">
                <span class="dashboard-today-event-icon"><?php echo !empty($eventoHoje['modalidade_slug']) ? stridebr_sport_icon_html((string) $eventoHoje['modalidade_slug'], 'sport-icon') : ''; ?></span>
                <div><span class="dashboard-eyebrow"><?php echo stridebr_e(stridebr_t('home.event_today')); ?></span><strong><?php echo stridebr_e((string) $eventoHoje['titulo']); ?></strong><small><?php echo stridebr_e($eventoData->format('H:i')); ?><?php echo !empty($eventoHoje['cidade']) ? ' · ' . stridebr_e((string) $eventoHoje['cidade']) : ''; ?></small></div>
                <a href="/evento.php?e=<?php echo rawurlencode((string) $eventoHoje['slug']); ?>"><?php echo stridebr_e(stridebr_t('events.view_details')); ?></a>
            </section>
            <?php endif; ?>

            <section class="dashboard-panel dashboard-week-panel" aria-labelledby="dashboard-week-title">
                <div class="dashboard-panel-heading">
                    <div><span class="dashboard-eyebrow"><?php echo stridebr_e($weekOffset === 0 ? stridebr_t('home.this_week') : stridebr_format_date_short($activityOverview['inicio']) . ' – ' . stridebr_format_date_short($activityOverview['fim']->modify('-1 day'))); ?></span><h2 id="dashboard-week-title"><?php echo stridebr_e(stridebr_t('home.week_consistency')); ?></h2></div>
                    <nav class="dashboard-week-nav" aria-label="<?php echo stridebr_e(stridebr_t('home.week_consistency')); ?>"><a data-week-prev href="?week=<?php echo $weekOffset - 1; ?>#dashboard-week-title" aria-label="<?php echo stridebr_e(stridebr_t('planning.previous')); ?>">‹</a><a href="?week=0#dashboard-week-title"><?php echo stridebr_e(stridebr_t('home.this_week')); ?></a><a data-week-next href="?week=<?php echo $weekOffset + 1; ?>#dashboard-week-title" aria-label="<?php echo stridebr_e(stridebr_t('planning.next')); ?>">›</a></nav>
                </div>
                <div class="dashboard-week-consistency" role="list" aria-label="<?php echo stridebr_e(stridebr_t('home.week_consistency_aria')); ?>">
                    <?php foreach ($dias as $dia):
                        $dayActivities = (array) ($dia['atividades_itens'] ?? []);
                        $visibleActivities = array_slice($dayActivities, 0, 2);
                        $remainingActivities = array_slice($dayActivities, 2);
                        $extraActivities = count($remainingActivities);
                        $dayAria = stridebr_tn('home.day_activities.one', 'home.day_activities.other', (int) $dia['atividades'], ['date' => (string) $dia['data_label']]);
                    ?>
                        <div class="dashboard-week-day<?php echo !empty($dia['hoje']) ? ' is-today' : ''; ?><?php echo !empty($dia['futuro']) ? ' is-future' : ''; ?><?php echo (int) $dia['atividades'] > 0 ? ' has-activity' : ''; ?>" role="listitem" aria-label="<?php echo stridebr_e($dayAria); ?>">
                            <strong><?php echo stridebr_e($dia['rotulo']); ?></strong>
                            <span class="dashboard-week-date"><?php echo stridebr_e((new DateTimeImmutable((string) $dia['data']))->format('d')); ?></span>
                            <span class="dashboard-week-markers">
                                <?php if ($visibleActivities === []): ?><i class="dashboard-week-empty-mark" aria-hidden="true"></i><?php else: ?>
                                    <?php foreach ($visibleActivities as $activity):
                                        $activityId = (string) ($activity['idregistro'] ?? '');
                                        $templateId = 'week-activity-' . substr(md5((string) $dia['data'] . '|' . $activityId), 0, 16);
                                    ?>
                                        <a class="dashboard-week-sport" href="<?php echo stridebr_e((string) ($activity['href'] ?? '/user/atividades.php')); ?>" data-week-popover-trigger data-week-popover-template="<?php echo stridebr_e($templateId); ?>" aria-haspopup="dialog" aria-controls="dashboard-week-popover" aria-expanded="false" aria-label="<?php echo stridebr_e((string) ($activity['aria_label'] ?? $activity['titulo'] ?? '')); ?>"><?php echo stridebr_sport_icon_html((string) ($activity['modalidade_slug'] ?? ''), 'sport-icon'); ?></a>
                                        <template id="<?php echo stridebr_e($templateId); ?>"><div class="dashboard-week-popover-activity"><strong><?php echo stridebr_e((string) ($activity['titulo'] ?? '')); ?></strong><?php if (!empty($activity['metadata'])): ?><span><?php echo stridebr_e((string) $activity['metadata']); ?></span><?php endif; ?><a href="<?php echo stridebr_e((string) ($activity['href'] ?? '/user/atividades.php')); ?>"><?php echo stridebr_e(stridebr_t('home.view_activity')); ?></a></div></template>
                                    <?php endforeach; ?>
                                    <?php if ($extraActivities > 0):
                                        $moreTemplateId = 'week-more-' . substr(md5((string) $dia['data']), 0, 16);
                                    ?>
                                        <button type="button" class="dashboard-week-more" data-week-popover-trigger data-week-popover-template="<?php echo stridebr_e($moreTemplateId); ?>" aria-haspopup="dialog" aria-controls="dashboard-week-popover" aria-expanded="false" aria-label="<?php echo stridebr_e(stridebr_tn('home.week_more_activities.one', 'home.week_more_activities.other', $extraActivities)); ?>">+<?php echo $extraActivities; ?></button>
                                        <template id="<?php echo stridebr_e($moreTemplateId); ?>"><div class="dashboard-week-popover-list"><strong><?php echo stridebr_e(stridebr_tn('home.week_more_activities.one', 'home.week_more_activities.other', $extraActivities)); ?></strong><?php foreach ($remainingActivities as $activity): ?><a href="<?php echo stridebr_e((string) ($activity['href'] ?? '/user/atividades.php')); ?>"><span><?php echo stridebr_e((string) ($activity['titulo'] ?? '')); ?></span><?php if (!empty($activity['metadata'])): ?><small><?php echo stridebr_e((string) $activity['metadata']); ?></small><?php endif; ?></a><?php endforeach; ?></div></template>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </span>
                            <?php if ((int) $dia['atividades'] > 1): ?><small><?php echo (int) $dia['atividades']; ?></small><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="dashboard-week-summary"><strong><?php echo stridebr_e($weekSummary); ?></strong><a href="/user/atividades.php"><?php echo stridebr_e(stridebr_t('home.view_activities')); ?></a></div>
                <div id="dashboard-week-popover" class="dashboard-week-popover" data-dashboard-week-popover role="dialog" aria-modal="false" hidden></div>
            </section>

            <?php stridebr_render_ad_slot('home-after-week', '/home.php', true); ?>

            <div class="dashboard-modules dashboard-modules-fixed">
                <?php if ($metasDisponiveis && $metas !== []): ?>
                    <section class="dashboard-panel dashboard-goals-panel dashboard-module is-wide" data-dashboard-module="goals">
                        <div class="dashboard-panel-heading"><div><h2><?php echo stridebr_e(stridebr_t('home.goals')); ?></h2><p><?php echo stridebr_e(stridebr_t('home.active_goals_help')); ?></p></div><div class="dashboard-panel-actions"><a href="/user/metas.php"><?php echo stridebr_e(stridebr_t('home.manage')); ?></a><button class="dashboard-link-button" type="button" data-goal-open>+ <?php echo stridebr_e(stridebr_t('home.new_goal')); ?></button></div></div>
                        <div class="dashboard-goal-list">
                            <?php foreach (array_slice($metas, 0, 3) as $meta):
                                $alvo = (float) $meta['valor_alvo'];
                                $progresso = (float) $meta['progresso'];
                                $unidade = dashboardMetaUnidade((string) $meta['metrica']);
                                $tituloMeta = dashboardMetaTitulo($meta);
                                $inteiro = in_array((string) $meta['metrica'], ['atividades', 'dias_ativos'], true);
                            ?>
                                <article class="dashboard-goal-row<?php echo !empty($meta['atingida']) ? ' is-complete' : ''; ?>">
                                    <div class="dashboard-goal-icon"><?php if (!empty($meta['modalidade_slug'])): ?><?php echo stridebr_sport_icon_html((string) $meta['modalidade_slug'], 'sport-icon'); ?><?php else: ?><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"></circle><circle cx="12" cy="12" r="4"></circle><circle cx="12" cy="12" r="1"></circle></svg><?php endif; ?></div>
                                    <div class="dashboard-goal-body"><div class="dashboard-goal-title"><strong><?php echo stridebr_e($tituloMeta); ?></strong><span><?php echo stridebr_e(dashboardMetaPrazoLabel($meta)); ?></span></div><div class="dashboard-progress"><span style="width: <?php echo number_format((float) $meta['percentual'], 2, '.', ''); ?>%"></span></div><div class="dashboard-goal-meta"><span><?php echo stridebr_e(dashboardFormatarNumero($progresso, $inteiro ? 0 : 1)); ?> / <?php echo stridebr_e(dashboardFormatarNumero($alvo, $inteiro ? 0 : 1)); ?> <?php echo stridebr_e($unidade); ?></span><strong><?php echo (int) round((float) $meta['percentual_real']); ?>%</strong></div></div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="dashboard-panel dashboard-module" data-dashboard-module="upcoming">
                    <div class="dashboard-panel-heading"><div><h2><?php echo stridebr_e(stridebr_t('home.upcoming_workouts')); ?></h2></div><a href="/user/cronogramatreinos.php"><?php echo stridebr_e(stridebr_t('common.schedules')); ?></a></div>
                    <?php if ($proximos === []): ?>
                        <div class="dashboard-empty compact"><strong><?php echo stridebr_e(stridebr_t('home.nothing_scheduled')); ?></strong><span><?php echo stridebr_e(stridebr_t('home.nothing_scheduled_help')); ?></span><a class="secondary-button compact" href="/user/cronogramatreinos.php"><?php echo stridebr_e(stridebr_t('home.add_workout')); ?></a></div>
                    <?php else: ?><div class="dashboard-schedule-list">
                        <?php foreach ($proximos as $treino): $data = $treino['proxima_data']; $quando = $data->format('Y-m-d') === $hoje->format('Y-m-d') ? stridebr_t('common.today') : ($data->format('Y-m-d') === $amanha->format('Y-m-d') ? stridebr_t('common.tomorrow') : strtoupper(stridebr_weekday_short($data)) . ' ' . stridebr_format_date_short($data)); $corTreino = trim((string) ($treino['cor'] ?? '')); if (!preg_match('/^#[0-9a-fA-F]{6}$/', $corTreino)) $corTreino = '#65759d'; ?>
                            <a class="dashboard-schedule-row" href="/user/cronogramatreinos.php"><span class="dashboard-schedule-mark" style="--schedule-color: <?php echo stridebr_e($corTreino); ?>"></span><span class="dashboard-schedule-time"><strong><?php echo stridebr_e($quando); ?></strong><small><?php echo stridebr_e(substr((string) $treino['hora_inicio'], 0, 5)); ?></small></span><span class="dashboard-schedule-info"><strong><?php echo stridebr_e((string) $treino['titulo']); ?></strong><small><?php echo stridebr_e((string) $treino['cronograma_nome']); ?></small></span><span aria-hidden="true">›</span></a>
                        <?php endforeach; ?>
                    </div><?php endif; ?>
                </section>

                <section class="dashboard-panel dashboard-module" data-dashboard-module="recent">
                    <div class="dashboard-panel-heading"><div><h2><?php echo stridebr_e(stridebr_t('home.recent_activities')); ?></h2></div><a href="/user/atividades.php"><?php echo stridebr_e(stridebr_t('common.history')); ?></a></div>
                    <?php if ($recentes === []): ?>
                        <div class="dashboard-empty compact dashboard-empty-actions"><strong><?php echo stridebr_e(stridebr_t('home.no_activities')); ?></strong><span><?php echo stridebr_e(stridebr_t('home.no_activities_help')); ?></span><div><a class="secondary-button compact" href="/user/atividades.php?new=1"><?php echo stridebr_e(stridebr_t('home.log_activity')); ?></a><a class="secondary-button compact" href="/user/gravar-atividade.php"><?php echo stridebr_e(stridebr_t('home.record_gps')); ?></a></div></div>
                    <?php else: ?><div class="dashboard-activity-list">
                        <?php foreach ($recentes as $atividade): $distanciaKm = ((float) $atividade['distancia_m']) / 1000; $duracao = (float) $atividade['duracao_s']; $tituloRaw = trim((string) $atividade['titulo']); $titulo = $tituloRaw !== '' ? stridebr_present_activity_title($tituloRaw, (string) $atividade['modalidade_slug']) : stridebr_sport_name((string) $atividade['modalidade_slug'], (string) $atividade['modalidade_nome']); ?>
                            <a class="dashboard-activity-row" href="/user/atividades.php#atividade-<?php echo rawurlencode((string) $atividade['idregistro']); ?>"><span class="dashboard-activity-icon"><?php echo stridebr_sport_icon_html((string) $atividade['modalidade_slug'], 'sport-icon'); ?></span><span class="dashboard-activity-info"><strong><?php echo stridebr_e($titulo); ?></strong><small><?php echo stridebr_e(stridebr_format_datetime_short(new DateTimeImmutable((string) $atividade['data_inicio']))); ?> · <?php echo stridebr_e(stridebr_sport_name((string) $atividade['modalidade_slug'], (string) $atividade['modalidade_nome'])); ?></small></span><span class="dashboard-activity-values"><?php if ($distanciaKm > 0): ?><strong><?php echo stridebr_e(dashboardFormatarNumero($distanciaKm)); ?> km</strong><?php endif; ?><?php if ($duracao > 0): ?><small><?php echo stridebr_e(dashboardFormatarDuracao($duracao)); ?></small><?php endif; ?></span><span aria-hidden="true">›</span></a>
                        <?php endforeach; ?>
                    </div><?php endif; ?>
                </section>
            </div>

            <section class="dashboard-quickbar" aria-label="<?php echo stridebr_e(stridebr_t('home.quick_access')); ?>">
                <strong><?php echo stridebr_e(stridebr_t('home.quick_access')); ?></strong>
                <a href="/user/biblioteca.php?tab=exercicios"><?php echo stridebr_e(stridebr_t('home.exercises')); ?></a>
                <a href="/user/equipamentos.php"><?php echo stridebr_e(stridebr_t('home.equipment')); ?></a>
                <a href="/user/ferramentastreino.php"><?php echo stridebr_e(stridebr_t('home.tools')); ?></a>
                <a href="/calendario.php"><?php echo stridebr_e(stridebr_t('home.events')); ?></a>
                <a href="/user/settings.php"><?php echo stridebr_e(stridebr_t('home.settings')); ?></a>
            </section>
        </div>
    </main>
</div>


<?php if ($metasDisponiveis): ?>
<dialog class="dashboard-dialog" data-goal-dialog>
    <form method="POST" class="dashboard-goal-form">
        <?php echo stridebr_csrf_field(); ?>
        <input type="hidden" name="action" value="criar_meta">
        <div class="dashboard-dialog-heading"><div><span class="dashboard-eyebrow"><?php echo stridebr_e(stridebr_t('home.goal')); ?></span><h2><?php echo stridebr_e(stridebr_t('home.new_goal')); ?></h2></div><button type="button" data-goal-close aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button></div>
        <div class="dashboard-goal-section">
            <div class="dashboard-goal-section-title"><span>1</span><strong><?php echo stridebr_e(stridebr_t('home.what_to_track')); ?></strong></div>
            <div class="dashboard-goal-sport-field"><span class="form-field-label"><?php echo stridebr_e(stridebr_t('common.sport')); ?></span><?php echo sportPickerRenderSelect($goalModalidades, ['name' => 'idmodalidade', 'empty_label' => stridebr_t('home.all_sports'), 'native_attributes' => ['data-goal-sport' => true]]); ?></div>
            <div class="dashboard-goal-form-grid">
                <label><?php echo stridebr_e(stridebr_t('home.metric')); ?>
                    <select name="metrica" data-goal-metric>
                        <option value="distancia"><?php echo stridebr_e(stridebr_t('home.distance')); ?></option>
                        <option value="duracao"><?php echo stridebr_e(stridebr_t('home.time')); ?></option>
                        <option value="atividades"><?php echo stridebr_e(stridebr_t('common.activities')); ?></option>
                        <option value="elevacao"><?php echo stridebr_e(stridebr_t('home.elevation')); ?></option>
                        <option value="dias_ativos"><?php echo stridebr_e(stridebr_t('home.active_days')); ?></option>
                        <option value="carga_maxima"><?php echo stridebr_e(stridebr_t('home.exercise_max_load')); ?></option>
                    </select>
                </label>
                <label class="goal-exercise-field" data-goal-exercise-field hidden><?php echo stridebr_e(stridebr_t('home.exercise')); ?>
                    <select name="idexercicio" data-goal-exercise>
                        <option value=""><?php echo stridebr_e(stridebr_t('home.choose_exercise')); ?></option>
                    </select>
                    <small><?php echo stridebr_e(stridebr_t('home.exercise_goal_example')); ?></small>
                </label>
                <label><?php echo stridebr_e(stridebr_t('home.target')); ?>
                    <span class="dashboard-target-input"><input name="valor_alvo" inputmode="decimal" autocomplete="off" required placeholder="20"><span data-goal-unit>km</span></span>
                </label>
            </div>
        </div>

        <div class="dashboard-goal-section">
            <div class="dashboard-goal-section-title"><span>2</span><strong><?php echo stridebr_e(stridebr_t('home.deadline')); ?></strong></div>
            <label><?php echo stridebr_e(stridebr_t('home.goal_recurrence')); ?>
                <select name="periodo" data-goal-period>
                    <option value="continuo"><?php echo stridebr_e(stridebr_t('home.no_deadline')); ?></option>
                    <option value="personalizado"><?php echo stridebr_e(stridebr_t('home.until_date')); ?></option>
                    <option value="semanal"><?php echo stridebr_e(stridebr_t('home.every_week')); ?></option>
                    <option value="mensal"><?php echo stridebr_e(stridebr_t('home.every_month')); ?></option>
                    <option value="anual"><?php echo stridebr_e(stridebr_t('home.every_year')); ?></option>
                </select>
            </label>
            <div class="dashboard-goal-form-grid dashboard-custom-dates" data-goal-custom-dates hidden><label><?php echo stridebr_e(stridebr_t('home.starts_on')); ?><input type="date" name="data_inicio"></label><label><?php echo stridebr_e(stridebr_t('home.reach_by')); ?><input type="date" name="data_fim"></label></div>
        </div>

        <label><?php echo stridebr_e(stridebr_t('home.goal_name')); ?> <small><?php echo stridebr_e(stridebr_t('common.optional')); ?></small><input name="nome" maxlength="80" placeholder="<?php echo stridebr_e(stridebr_t('home.goal_name_placeholder')); ?>"></label>
        <div class="dashboard-goal-summary" data-goal-summary aria-live="polite"></div>
        <div class="dashboard-dialog-actions"><button type="button" class="dashboard-button dashboard-button-secondary" data-goal-close><?php echo stridebr_e(stridebr_t('common.cancel')); ?></button><button type="submit" class="dashboard-button dashboard-button-primary"><?php echo stridebr_e(stridebr_t('home.create_goal_action')); ?></button></div>
    </form>
</dialog>
<?php endif; ?>

<?php require dirname(__DIR__) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/dashboard.js')); ?>"></script>
</body>
</html>
