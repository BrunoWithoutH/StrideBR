<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/function/dashboard.php';
require_once dirname(__DIR__) . '/src/function/cronograma.php';
require_once dirname(__DIR__) . '/src/function/eventos.php';
require_once dirname(__DIR__) . '/src/function/competitions.php';
require_once dirname(__DIR__) . '/src/function/progress_service.php';
require_once dirname(__DIR__) . '/src/function/pacer_service.php';
require_once dirname(__DIR__) . '/src/includes/sport_icons.php';
require_once dirname(__DIR__) . '/src/layout/sport_picker.php';
require_once dirname(__DIR__) . '/src/layout/ads.php';
require_once dirname(__DIR__) . '/src/function/teams_surface_provider.php';

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
$recentes = dashboardAtividadesRecentes($pdo, $idUsuario, 5);
$proximos = dashboardTreinosProximos($pdo, $idUsuario, 5);
$metas = $metasDisponiveis ? array_values(array_filter(dashboardListarMetas($pdo, $idUsuario), static fn(array $meta): bool => (string) ($meta['metrica'] ?? '') !== 'elevacao')) : [];
$goalModalidades = $metasDisponiveis ? dashboardListarModalidades($pdo, $idUsuario) : [];
$contextoHoje = dashboardContextoHoje($pdo, $idUsuario);
$homePacer = null;
$nextCompetitions = [];
$progress28 = null;
$institutionalHome = null;
if (stridebr_teams_enabled()) {
    try {
        $teamsContext = stridebr_teams_surface_context($pdo, $idUsuario);
        $teamItem = (array) (($teamsContext['my_teams'][0] ?? null) ?: []);
        if ($teamItem !== []) {
            $institutionalTrainings = stridebr_teams_surface_trainings($pdo, $idUsuario, (new DateTimeImmutable('today'))->format('Y-m-d'), (new DateTimeImmutable('today'))->modify('+550 days')->format('Y-m-d'), (string) $teamItem['team']['ref']);
            $institutionalCompetitions = stridebr_teams_surface_competitions($pdo, $idUsuario);
            $institutionalHome = ['team' => $teamItem, 'training' => $institutionalTrainings[0] ?? null, 'competition' => $institutionalCompetitions[0] ?? null];
        }
    } catch (Throwable $e) {
        error_log('StrideBR Teams home surface: ' . $e->getMessage());
        $institutionalHome = null;
    }
}
$flashes = stridebr_take_flashes();


$tz = new DateTimeZone('America/Sao_Paulo');
$hoje = new DateTimeImmutable('today', $tz);
$amanha = $hoje->modify('+1 day');
$todayLabel = stridebr_format_date_weekday($hoje);
try {
    $pacerId = trim((string) (($contextoHoje['primary']['idpacerplan'] ?? '') ?: ($proximos[0]['idpacerplan'] ?? '')));
    if ($pacerId !== '') {
        $candidate = pacerPlanGet($pdo, $idUsuario, $pacerId, false);
        if ($candidate !== []) $homePacer = $candidate;
    }
    if ($homePacer === null) {
        $activePacerPlans = pacerPlanList($pdo, $idUsuario, ['status' => 'active']);
        $homePacer = $activePacerPlans[0] ?? null;
    }
} catch (Throwable $e) {
    error_log('StrideBR home pacer: ' . $e->getMessage());
}
try {
    foreach (competitionList($pdo, $idUsuario, ['status' => 'planejada']) as $competition) {
        if ((string) ($competition['participacao_status'] ?? '') === 'cancelou') continue;
        if ((string) ($competition['data_inicio'] ?? '') < $hoje->format('Y-m-d')) continue;
        $nextCompetitions[] = $competition;
        if (count($nextCompetitions) >= 3) break;
    }
} catch (Throwable $e) {
    error_log('StrideBR home competition: ' . $e->getMessage());
}
try {
    $progress28 = progressOverview($pdo, $idUsuario, [
        'from' => $hoje->modify('-27 days')->format('Y-m-d'),
        'to' => $hoje->format('Y-m-d'),
    ]);
} catch (Throwable $e) {
    error_log('StrideBR home progress: ' . $e->getMessage());
}
$tomorrowWorkout = null;
foreach ($proximos as $workout) {
    if (($workout['proxima_data'] ?? null) instanceof DateTimeInterface && $workout['proxima_data']->format('Y-m-d') === $amanha->format('Y-m-d')) {
        $tomorrowWorkout = $workout;
        break;
    }
}
$followingWorkout = dashboardTreinoSeguinteContexto($proximos, $tomorrowWorkout, $amanha);
$followingWorkoutDate = ($followingWorkout['proxima_data'] ?? null) instanceof DateTimeInterface ? $followingWorkout['proxima_data'] : null;
$followingWorkoutHref = '/user/cronogramatreinos.php';
if ($followingWorkout !== null) {
    $followingParams = ['view' => 'agenda'];
    if (!empty($followingWorkout['idcronograma'])) $followingParams['id'] = (string) $followingWorkout['idcronograma'];
    if ($followingWorkoutDate !== null) $followingParams['date'] = $followingWorkoutDate->format('Y-m-d');
    $followingWorkoutHref .= '?' . http_build_query($followingParams);
}
$homePacerPaceLabel = null;
$homePacerTimeLabel = null;
if (is_array($homePacer) && is_numeric($homePacer['target_time_s'] ?? null)) {
    $targetSeconds = max(0, (int) round((float) $homePacer['target_time_s']));
    $targetHours = intdiv($targetSeconds, 3600);
    $targetMinutes = intdiv($targetSeconds % 3600, 60);
    $targetRemainder = $targetSeconds % 60;
    $homePacerTimeLabel = $targetHours > 0
        ? sprintf('%d:%02d:%02d', $targetHours, $targetMinutes, $targetRemainder)
        : sprintf('%d:%02d', $targetMinutes, $targetRemainder);
}
if (is_array($homePacer) && is_numeric($homePacer['target_average_pace_s_per_km'] ?? null)) {
    $paceSeconds = max(1, (int) round((float) $homePacer['target_average_pace_s_per_km']));
    $homePacerPaceLabel = sprintf('%d:%02d/km', intdiv($paceSeconds, 60), $paceSeconds % 60);
}
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
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/dashboard-v2.css')); ?>">
    <title><?php echo stridebr_e(stridebr_t('home.page_title')); ?></title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/institutional.css')); ?>">
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
                <div class="dashboard-tomorrow-context" aria-label="<?php echo stridebr_e(stridebr_t('common.tomorrow')); ?>">
                    <span class="dashboard-eyebrow"><?php echo stridebr_e(stridebr_t('common.tomorrow')); ?></span>
                    <?php if ($tomorrowWorkout): ?>
                        <strong><?php echo stridebr_e((string) ($tomorrowWorkout['titulo'] ?? stridebr_t('home.default_workout'))); ?></strong>
                        <small><?php echo stridebr_e(substr((string) ($tomorrowWorkout['hora_inicio'] ?? ''), 0, 5)); ?><?php echo !empty($tomorrowWorkout['cronograma_nome']) ? ' · ' . stridebr_e((string) $tomorrowWorkout['cronograma_nome']) : ''; ?></small>
                    <?php else: ?>
                        <strong><?php echo stridebr_e(stridebr_t('home.rest')); ?></strong>
                        <small><?php echo stridebr_e(stridebr_t('home.nothing_scheduled')); ?></small>
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
                        <?php if ($followingWorkout !== null && $followingWorkoutDate !== null):
                            $followingDays = (int) $hoje->diff($followingWorkoutDate)->format('%a');
                            $followingDateLabel = $followingDays <= 7 ? stridebr_weekday_name($followingWorkoutDate) : stridebr_format_date_short($followingWorkoutDate);
                            $followingTime = substr((string) ($followingWorkout['hora_inicio'] ?? ''), 0, 5);
                        ?>
                            <a class="dashboard-follow-up" href="<?php echo stridebr_e($followingWorkoutHref); ?>">
                                <span class="dashboard-eyebrow"><?php echo stridebr_e(stridebr_t($tomorrowWorkout !== null ? 'home.following_workout' : 'home.next_workout')); ?></span>
                                <strong><?php echo stridebr_e((string) ($followingWorkout['titulo'] ?? stridebr_t('home.default_workout'))); ?></strong>
                                <small><?php echo stridebr_e($followingDateLabel); ?><?php echo $followingTime !== '' ? ' · ' . stridebr_e($followingTime) : ''; ?></small>
                                <span class="dashboard-follow-up-arrow" aria-hidden="true">→</span>
                            </a>
                        <?php endif; ?>
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

            <?php if (is_array($institutionalHome)): $institutionalTeam = $institutionalHome['team']; ?>
            <section class="dashboard-v2-card institutional-home-card" aria-label="<?php echo stridebr_e(stridebr_t('teams.my_team')); ?>">
                <div class="dashboard-v2-card-head"><span><?php echo stridebr_e(stridebr_t('teams.my_team')); ?></span><a href="/user/equipe.php?team=<?php echo rawurlencode((string) $institutionalTeam['team']['ref']); ?>&amp;season=<?php echo rawurlencode((string) $institutionalTeam['season']['ref']); ?>"><?php echo stridebr_e(stridebr_t('teams.my_teams')); ?></a></div>
                <a href="/user/equipe.php?team=<?php echo rawurlencode((string) $institutionalTeam['team']['ref']); ?>&amp;season=<?php echo rawurlencode((string) $institutionalTeam['season']['ref']); ?>"><strong><?php echo stridebr_e((string) $institutionalTeam['team']['name']); ?></strong><small><?php echo stridebr_e((string) $institutionalTeam['organization']['name']); ?></small></a>
                <div class="institutional-meta"><?php if (is_array($institutionalHome['training'])): ?><a href="/user/treino-institucional.php?id=<?php echo rawurlencode((string) $institutionalHome['training']['training_ref']); ?>"><?php echo stridebr_e(stridebr_t('teams.next_training')); ?>: <?php echo stridebr_e((string) $institutionalHome['training']['title']); ?></a><?php endif; ?><?php if (is_array($institutionalHome['competition'])): ?><a href="/user/competicao-institucional.php?id=<?php echo rawurlencode((string) $institutionalHome['competition']['competition_ref']); ?>"><?php echo stridebr_e(stridebr_t('teams.next_competition')); ?>: <?php echo stridebr_e((string) $institutionalHome['competition']['display_name']); ?></a><?php endif; ?></div>
            </section>
            <?php endif; ?>

            <div class="dashboard-home-layout">
                <div class="dashboard-home-main">
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
                            <?php if ((int) $dia['atividades'] === 1 && !empty($visibleActivities[0]['metadata'])): ?><span class="dashboard-week-inline-meta"><?php echo stridebr_e((string) $visibleActivities[0]['metadata']); ?></span><?php endif; ?>
                            <?php if ((int) $dia['atividades'] > 1): ?><small><?php echo (int) $dia['atividades']; ?></small><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="dashboard-week-summary"><strong><?php echo stridebr_e($weekSummary); ?></strong><a href="/user/atividades.php"><?php echo stridebr_e(stridebr_t('home.view_activities')); ?></a></div>
                <div id="dashboard-week-popover" class="dashboard-week-popover" data-dashboard-week-popover role="dialog" aria-modal="false" hidden></div>
            </section>

            <?php $progressSummary = is_array($progress28['summary'] ?? null) ? $progress28['summary'] : []; ?>
            <section class="dashboard-period-strip" aria-label="Últimos 28 dias">
                <div class="dashboard-period-strip-head"><div><span class="dashboard-eyebrow"><?php echo stridebr_e(stridebr_t('home.last_28_days')); ?></span></div><a class="dashboard-period-action" href="/user/progresso.php"><?php echo stridebr_e(stridebr_t('home.view_progress')); ?> <span aria-hidden="true">→</span></a></div>
                <div class="dashboard-period-strip-stats">
                    <div><strong><?php echo (int) ($progressSummary['active_days'] ?? 0); ?></strong><span>dias ativos</span></div>
                    <div><strong><?php echo is_numeric($progressSummary['total_distance_m'] ?? null) ? stridebr_e(number_format((float) $progressSummary['total_distance_m'] / 1000, 1, ',', '.')) : '—'; ?></strong><span>km</span></div>
                    <div><strong><?php echo (int) ($progressSummary['activities_count'] ?? 0); ?></strong><span>atividades</span></div>
                    <div><strong><?php echo is_numeric($progressSummary['total_duration_s'] ?? null) && (float) $progressSummary['total_duration_s'] > 0 ? stridebr_e(dashboardFormatarDuracao((float) $progressSummary['total_duration_s'])) : '—'; ?></strong><span>tempo</span></div>
                </div>
            </section>

            <section class="dashboard-panel dashboard-module dashboard-recent-module" data-dashboard-module="recent">
                <div class="dashboard-panel-heading"><div><h2><?php echo stridebr_e(stridebr_t('home.recent_activities')); ?></h2></div><a href="/user/atividades.php"><?php echo stridebr_e(stridebr_t('common.history')); ?></a></div>
                <?php if ($recentes === []): ?>
                    <div class="dashboard-empty compact dashboard-empty-actions"><strong><?php echo stridebr_e(stridebr_t('home.no_activities')); ?></strong><span><?php echo stridebr_e(stridebr_t('home.no_activities_help')); ?></span><div><a class="secondary-button compact" href="/user/atividades.php?new=1"><?php echo stridebr_e(stridebr_t('home.log_activity')); ?></a><a class="secondary-button compact" href="/user/gravar-atividade.php"><?php echo stridebr_e(stridebr_t('home.record_gps')); ?></a></div></div>
                <?php else: ?><div class="dashboard-activity-list">
                    <?php foreach ($recentes as $atividade):
                        $distanciaKm = ((float) ($atividade['distancia_m'] ?? 0)) / 1000;
                        $duracao = (float) ($atividade['duracao_s'] ?? 0);
                        $tituloRaw = trim((string) ($atividade['titulo'] ?? ''));
                        $titulo = $tituloRaw !== '' ? stridebr_present_activity_title($tituloRaw, (string) ($atividade['modalidade_slug'] ?? '')) : stridebr_sport_name((string) ($atividade['modalidade_slug'] ?? ''), (string) ($atividade['modalidade_nome'] ?? ''));
                        $pace = $distanciaKm > 0 && $duracao > 0 ? $duracao / $distanciaKm : null;
                    ?>
                        <a class="dashboard-activity-row" href="/user/atividades.php?highlight=<?php echo rawurlencode((string) $atividade['idregistro']); ?>"><span class="dashboard-activity-icon"><?php echo stridebr_sport_icon_html((string) ($atividade['modalidade_slug'] ?? ''), 'sport-icon'); ?></span><span class="dashboard-activity-info"><strong><?php echo stridebr_e($titulo); ?></strong><small><?php echo stridebr_e(stridebr_format_datetime_short(new DateTimeImmutable((string) $atividade['data_inicio']))); ?> · <?php echo stridebr_e(stridebr_sport_name((string) ($atividade['modalidade_slug'] ?? ''), (string) ($atividade['modalidade_nome'] ?? ''))); ?></small></span><span class="dashboard-activity-values"><?php if ($distanciaKm > 0): ?><strong><?php echo stridebr_e(dashboardFormatarNumero($distanciaKm)); ?> km</strong><?php endif; ?><?php if ($duracao > 0): ?><small><?php echo stridebr_e(dashboardFormatarDuracao($duracao)); ?><?php if ($pace !== null): ?> · <?php echo stridebr_e(sprintf('%d:%02d/km', intdiv((int) round($pace), 60), ((int) round($pace)) % 60)); ?><?php endif; ?></small><?php endif; ?></span><span aria-hidden="true">›</span></a>
                    <?php endforeach; ?>
                </div><?php endif; ?>
            </section>

            <nav class="dashboard-secondary-actions" aria-label="Ações secundárias"><a href="/user/cronogramatreinos.php?new=workout">Novo treino</a><a href="/user/importar-exportar.php">Importar atividade</a></nav>

            <?php stridebr_render_ad_slot('home-after-week', '/home.php', true); ?>
                </div>
                <aside class="dashboard-home-rail" aria-label="Contexto rápido">
                    <section class="dashboard-panel dashboard-competition-rail">
                        <div class="dashboard-panel-heading"><div><h2>Próximas competições</h2></div><a href="/user/competicoes.php">Ver competições</a></div>
                        <?php if ($nextCompetitions === []): ?>
                            <div class="dashboard-empty compact"><strong>Nenhuma competição futura.</strong></div>
                        <?php else: ?>
                            <div class="dashboard-competition-list">
                                <?php foreach ($nextCompetitions as $competition): $competitionDate = new DateTimeImmutable((string) $competition['data_inicio']); $competitionPlace = implode(', ', array_filter([(string) ($competition['cidade'] ?? ''), (string) ($competition['estado'] ?? '')])); ?>
                                    <a class="dashboard-competition-row" href="/user/competicoes.php?id=<?php echo rawurlencode((string) $competition['idcompeticao']); ?>"><time datetime="<?php echo stridebr_e($competitionDate->format('Y-m-d')); ?>"><strong><?php echo stridebr_e($competitionDate->format('d')); ?></strong><span><?php echo stridebr_e(strtoupper(stridebr_month_short($competitionDate))); ?></span></time><span><strong><?php echo stridebr_e((string) $competition['nome']); ?></strong><?php if (!empty($competition['modalidade_nome'])): ?><small><?php echo stridebr_e((string) $competition['modalidade_nome']); ?></small><?php endif; ?><?php if ($competitionPlace !== ''): ?><small><?php echo stridebr_e($competitionPlace); ?></small><?php endif; ?></span></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="dashboard-panel dashboard-pacer-rail" data-dashboard-rail-pacer>
                        <div class="dashboard-panel-heading"><div><h2>Pacer</h2></div><a href="/user/pacer.php">Ver Pacer</a></div>
                        <?php if (is_array($homePacer)): $strategyLabel = ['even' => 'Ritmo constante', 'negative_split' => 'Negative split', 'positive_split' => 'Positive split', 'custom' => 'Custom'][(string) ($homePacer['strategy'] ?? '')] ?? str_replace('_', ' ', (string) ($homePacer['strategy'] ?? '')); ?>
                            <a class="dashboard-pacer-summary" href="/user/pacer.php?edit=<?php echo rawurlencode((string) $homePacer['id']); ?>"><span><?php echo stridebr_e(number_format((float) $homePacer['target_distance_m'] / 1000, 2, ',', '.')); ?> km · <?php echo stridebr_e($strategyLabel); ?></span><strong><?php echo stridebr_e($homePacerTimeLabel ?? '—'); ?></strong><?php if ($homePacerPaceLabel): ?><small><?php echo stridebr_e($homePacerPaceLabel); ?></small><?php endif; ?></a>
                        <?php else: ?>
                            <div class="dashboard-empty compact"><strong>Nenhuma estratégia ativa.</strong><a class="secondary-button compact" href="/user/pacer.php?new=1">Criar Pacer</a></div>
                        <?php endif; ?>
                    </section>

                    <section class="dashboard-panel dashboard-goals-rail" data-dashboard-module="goals">
                        <div class="dashboard-panel-heading"><div><h2><?php echo stridebr_e(stridebr_t('home.goals')); ?></h2></div><a href="/user/metas.php">Ver todas</a></div>
                        <?php if (!$metasDisponiveis || $metas === []): ?>
                            <div class="dashboard-empty compact"><strong>Nenhuma meta ativa.</strong><?php if ($metasDisponiveis): ?><button class="dashboard-button dashboard-button-secondary dashboard-goal-empty-action" type="button" data-goal-open>+ <?php echo stridebr_e(stridebr_t('home.new_goal')); ?></button><?php endif; ?></div>
                        <?php else: ?>
                            <div class="dashboard-goal-rail-list">
                                <?php foreach (array_slice($metas, 0, 3) as $meta): $alvo = (float) $meta['valor_alvo']; $progresso = (float) $meta['progresso']; $unidade = dashboardMetaUnidade((string) $meta['metrica']); $tituloMeta = dashboardMetaTitulo($meta); $inteiro = in_array((string) $meta['metrica'], ['atividades', 'dias_ativos'], true); ?>
                                    <article class="dashboard-goal-rail-row"><div><strong><?php echo stridebr_e($tituloMeta); ?></strong><span><?php echo stridebr_e(dashboardFormatarNumero($progresso, $inteiro ? 0 : 1)); ?> / <?php echo stridebr_e(dashboardFormatarNumero($alvo, $inteiro ? 0 : 1)); ?> <?php echo stridebr_e($unidade); ?></span><b><?php echo (int) round((float) $meta['percentual_real']); ?>%</b></div><div class="dashboard-progress"><span style="width: <?php echo number_format((float) $meta['percentual'], 2, '.', ''); ?>%"></span></div></article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </aside>
            </div>


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
