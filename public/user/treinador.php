<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/treinador.php';
require_once dirname(__DIR__, 2) . '/src/function/planejamento.php';
require_once dirname(__DIR__, 2) . '/src/function/notificacoes.php';

if (!stridebr_feature_enabled($pdo, 'trainer.enabled', false)) {
    stridebr_error_document(404);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $redirectAthlete = trim((string) ($_POST['redirect_atleta'] ?? ''));
    try {
        if ($action === 'activate_trainer') {
            $stmt = $pdo->prepare('UPDATE usuarios SET modo_treinador = TRUE WHERE idusuario = :id');
            $stmt->execute([':id' => $idUsuario]);
            stridebr_flash('success', stridebr_t('trainer.mode_enabled'));
        } elseif ($action === 'deactivate_trainer') {
            $stmt = $pdo->prepare('UPDATE usuarios SET modo_treinador = FALSE WHERE idusuario = :id');
            $stmt->execute([':id' => $idUsuario]);
            stridebr_flash('success', stridebr_t('trainer.mode_paused'));
        } elseif ($action === 'invite_athlete') {
            if (stridebr_auth_limit_is_blocked($pdo, 'trainer-link-user', $idUsuario)) {
                throw new RuntimeException(stridebr_t('trainer.rate_limit'));
            }
            stridebr_auth_limit_record_attempt($pdo, 'trainer-link-user', $idUsuario, 20, 3600, 3600);
            $linkId = treinadorCriarConvite($pdo, $idUsuario, (string) ($_POST['username'] ?? ''), 'treinador');
            $newLink = treinadorVinculo($pdo, $linkId);
            notificacaoCriar($pdo, (string) ($newLink['idatleta'] ?? ''), 'treinador_convite', stridebr_t('notification.coach_invite.title'), stridebr_t('notification.coach_invite.message'), '/user/treinador.php');
            stridebr_flash('success', stridebr_t('trainer.invite_sent'));
        } elseif ($action === 'request_trainer') {
            if (stridebr_auth_limit_is_blocked($pdo, 'trainer-link-user', $idUsuario)) {
                throw new RuntimeException(stridebr_t('trainer.rate_limit'));
            }
            stridebr_auth_limit_record_attempt($pdo, 'trainer-link-user', $idUsuario, 20, 3600, 3600);
            $linkId = treinadorCriarConvite($pdo, $idUsuario, (string) ($_POST['username'] ?? ''), 'atleta');
            $newLink = treinadorVinculo($pdo, $linkId);
            notificacaoCriar($pdo, (string) ($newLink['idtreinador'] ?? ''), 'treinador_solicitacao', stridebr_t('notification.athlete_request.title'), stridebr_t('notification.athlete_request.message'), '/user/treinador.php');
            stridebr_flash('success', stridebr_t('trainer.request_sent'));
        } elseif ($action === 'accept_link') {
            $linkId = (string) ($_POST['idvinculo'] ?? '');
            $linkBefore = treinadorVinculo($pdo, $linkId);
            treinadorResponderVinculo($pdo, $idUsuario, $linkId, 'aceitar');
            $other = (string) (($linkBefore['idtreinador'] ?? '') === $idUsuario ? ($linkBefore['idatleta'] ?? '') : ($linkBefore['idtreinador'] ?? ''));
            notificacaoCriar($pdo, $other, 'treinador_vinculo_aceito', stridebr_t('notification.coach_link_accepted.title'), stridebr_t('notification.coach_link_accepted.message'), '/user/treinador.php');
            stridebr_flash('success', stridebr_t('trainer.link_accepted'));
        } elseif ($action === 'reject_link') {
            treinadorResponderVinculo($pdo, $idUsuario, (string) ($_POST['idvinculo'] ?? ''), 'recusar');
            stridebr_flash('success', stridebr_t('trainer.invite_declined'));
        } elseif ($action === 'end_link') {
            treinadorEncerrarVinculo($pdo, $idUsuario, (string) ($_POST['idvinculo'] ?? ''));
            stridebr_flash('success', stridebr_t('trainer.link_ended'));
        } elseif ($action === 'update_permissions') {
            treinadorAtualizarPermissoes($pdo, $idUsuario, (string) ($_POST['idvinculo'] ?? ''), $_POST);
            stridebr_flash('success', stridebr_t('trainer.permissions_updated'));
        } elseif ($action === 'create_prescription') {
            $names = is_array($_POST['exercise_name'] ?? null) ? $_POST['exercise_name'] : [];
            $series = is_array($_POST['exercise_series'] ?? null) ? $_POST['exercise_series'] : [];
            $reps = is_array($_POST['exercise_reps'] ?? null) ? $_POST['exercise_reps'] : [];
            $loads = is_array($_POST['exercise_load'] ?? null) ? $_POST['exercise_load'] : [];
            $rests = is_array($_POST['exercise_rest'] ?? null) ? $_POST['exercise_rest'] : [];
            $notes = is_array($_POST['exercise_notes'] ?? null) ? $_POST['exercise_notes'] : [];
            $exerciseRows = [];
            foreach ($names as $index => $name) {
                $exerciseRows[] = [
                    'nome' => $name,
                    'series' => $series[$index] ?? '',
                    'repeticoes' => $reps[$index] ?? '',
                    'carga' => $loads[$index] ?? '',
                    'descanso' => $rests[$index] ?? '',
                    'observacoes' => $notes[$index] ?? '',
                ];
            }
            $status = (string) ($_POST['submit_mode'] ?? '') === 'publish' ? 'publicado' : 'rascunho';
            $athleteId = trim((string) ($_POST['idatleta'] ?? ''));
            $prescriptionId = treinadorCriarPrescricao($pdo, $idUsuario, $athleteId, [
                'titulo' => $_POST['titulo'] ?? '',
                'descricao' => $_POST['descricao'] ?? '',
                'data_treino' => $_POST['data_treino'] ?? '',
                'hora_inicio' => $_POST['hora_inicio'] ?? '',
                'duracao_prevista_min' => $_POST['duracao_prevista_min'] ?? '',
                'status' => $status,
            ], $exerciseRows);
            $redirectAthlete = $athleteId;
            if ($status === 'publicado') notificacaoCriar($pdo, $athleteId, 'treino_prescrito', stridebr_t('notification.prescription.title'), stridebr_t('notification.prescription.message'), '/user/treinador.php');
            stridebr_flash('success', stridebr_t($status === 'publicado' ? 'trainer.workout_published' : 'trainer.prescription_draft_saved'));
        } elseif ($action === 'publish_prescription') {
            $appointmentId = (string) ($_POST['idagendamento'] ?? '');
            $appointmentStmt = $pdo->prepare("SELECT idatleta FROM treinos_agendados WHERE idagendamento=:id AND idcriador=:treinador LIMIT 1");
            $appointmentStmt->execute([':id'=>$appointmentId, ':treinador'=>$idUsuario]);
            $athleteTarget = (string) ($appointmentStmt->fetchColumn() ?: '');
            treinadorPublicarPrescricao($pdo, $idUsuario, $appointmentId);
            if ($athleteTarget !== '') notificacaoCriar($pdo, $athleteTarget, 'treino_prescrito', stridebr_t('notification.prescription.title'), stridebr_t('notification.prescription.message'), '/user/treinador.php');
            stridebr_flash('success', stridebr_t('trainer.prescription_published'));
        } elseif ($action === 'cancel_prescription') {
            treinadorCancelarPrescricao($pdo, $idUsuario, (string) ($_POST['idagendamento'] ?? ''));
            stridebr_flash('success', stridebr_t('trainer.scheduled_cancelled'));
        } elseif ($action === 'save_feedback') {
            $appointmentId = (string) ($_POST['idagendamento'] ?? '');
            $trainerStmt = $pdo->prepare("SELECT idcriador FROM treinos_agendados WHERE idagendamento=:id AND idatleta=:atleta AND origem='treinador' LIMIT 1");
            $trainerStmt->execute([':id'=>$appointmentId, ':atleta'=>$idUsuario]);
            $trainerTarget = (string) ($trainerStmt->fetchColumn() ?: '');
            treinadorSalvarFeedback($pdo, $idUsuario, $appointmentId, (int) ($_POST['nota'] ?? 0), (string) ($_POST['feedback'] ?? ''));
            if ($trainerTarget !== '') notificacaoCriar($pdo, $trainerTarget, 'treino_feedback', stridebr_t('notification.feedback.title'), stridebr_t('notification.feedback.message'), '/user/treinador.php');
            stridebr_flash('success', stridebr_t('trainer.feedback_saved'));
        } else {
            throw new InvalidArgumentException(stridebr_t('trainer.invalid_action'));
        }

        $location = '/user/treinador.php';
        if ($redirectAthlete !== '') {
            $location .= '?atleta=' . rawurlencode($redirectAthlete);
        }
        header('Location: ' . $location);
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : stridebr_t('trainer.operation_error');
        if (!$e instanceof InvalidArgumentException && !$e instanceof RuntimeException) {
            error_log('StrideBR trainer action failed: ' . $e->getMessage());
        }
    }
}

$current = treinadorUsuario($pdo, $idUsuario);
$trainerMode = stridebr_db_bool($current['modo_treinador'] ?? false);

$asAthleteStmt = $pdo->prepare("SELECT v.*, u.username, COALESCE(NULLIF(u.nome_exibicao,''), u.nomeusuario) AS nome_exibicao, u.fotousuario
    FROM vinculos_treinador_atleta v
    JOIN usuarios u ON u.idusuario = v.idtreinador
    WHERE v.idatleta = :me AND v.status IN ('pendente','aceito')
    ORDER BY CASE WHEN v.status = 'aceito' THEN 0 ELSE 1 END, v.data_atualizacao DESC");
$asAthleteStmt->execute([':me' => $idUsuario]);
$asAthlete = $asAthleteStmt->fetchAll();

$asTrainer = [];
if ($trainerMode) {
    $asTrainerStmt = $pdo->prepare("SELECT v.*, u.username, COALESCE(NULLIF(u.nome_exibicao,''), u.nomeusuario) AS nome_exibicao, u.fotousuario,
        (SELECT ta.titulo FROM treinos_agendados ta WHERE ta.idatleta = v.idatleta AND ta.idcriador = v.idtreinador AND ta.status = 'publicado' AND ta.data_treino >= CURRENT_DATE AND v.status = 'aceito' ORDER BY ta.data_treino, ta.hora_inicio LIMIT 1) AS proximo_treino,
        (SELECT ra.data_inicio FROM registros_atividade ra WHERE ra.idusuario = v.idatleta AND ra.excluido_em IS NULL AND ra.status = 'concluido' AND v.status = 'aceito' AND v.pode_ver_atividades ORDER BY ra.data_inicio DESC LIMIT 1) AS ultima_atividade,
        (SELECT count(*) FROM treinos_agendados ta WHERE ta.idatleta = v.idatleta AND ta.idcriador = v.idtreinador AND ta.status = 'concluido' AND ta.feedback_em IS NULL AND v.status = 'aceito' AND v.pode_ver_feedback) AS feedback_pendente,
        (SELECT count(*) FROM treinos_agendados ta WHERE ta.idatleta = v.idatleta AND ta.idcriador = v.idtreinador AND ta.status = 'publicado' AND ta.data_treino >= CURRENT_DATE - 7 AND ta.data_treino < CURRENT_DATE AND v.status = 'aceito' AND v.pode_ver_atividades) AS prescricoes_nao_realizadas
        FROM vinculos_treinador_atleta v
        JOIN usuarios u ON u.idusuario = v.idatleta
        WHERE v.idtreinador = :me AND v.status IN ('pendente','aceito')
        ORDER BY CASE WHEN v.status = 'aceito' THEN 0 ELSE 1 END, v.data_atualizacao DESC");
    $asTrainerStmt->execute([':me' => $idUsuario]);
    $asTrainer = $asTrainerStmt->fetchAll();
}

$myPrescriptionsStmt = $pdo->prepare("SELECT ta.*, COALESCE(NULLIF(u.nome_exibicao,''), u.nomeusuario) AS treinador_nome, u.username AS treinador_username,
    (SELECT COUNT(*) FROM treinos_agendados_exercicios tae WHERE tae.idagendamento = ta.idagendamento) AS exercicios_total
    FROM treinos_agendados ta
    LEFT JOIN usuarios u ON u.idusuario = ta.idcriador
    WHERE ta.idatleta = :me AND ta.origem = 'treinador' AND ta.status IN ('publicado','concluido')
    ORDER BY CASE WHEN ta.status = 'publicado' AND ta.data_treino >= CURRENT_DATE THEN 0 WHEN ta.status = 'concluido' AND ta.feedback_em IS NULL THEN 1 ELSE 2 END, CASE WHEN ta.status = 'publicado' AND ta.data_treino >= CURRENT_DATE THEN ta.data_treino END ASC, ta.data_treino DESC, ta.hora_inicio NULLS LAST
    LIMIT 30");
$myPrescriptionsStmt->execute([':me' => $idUsuario]);
$myPrescriptions = $myPrescriptionsStmt->fetchAll();

$selectedAthleteId = trim((string) ($_GET['atleta'] ?? ''));
$selectedLink = [];
$selectedAthlete = [];
$selectedSchedules = [];
$selectedActivities = [];
$selectedPrescriptions = [];
if ($trainerMode && $selectedAthleteId !== '') {
    $selectedLink = treinadorVinculoAceito($pdo, $idUsuario, $selectedAthleteId);
    if ($selectedLink !== []) {
        $selectedAthlete = treinadorUsuario($pdo, $selectedAthleteId);
        $prescriptionStmt = $pdo->prepare("SELECT ta.*, (SELECT COUNT(*) FROM treinos_agendados_exercicios tae WHERE tae.idagendamento = ta.idagendamento) AS exercicios_total FROM treinos_agendados ta WHERE ta.idcriador = :treinador AND ta.idatleta = :atleta AND ta.origem = 'treinador' AND ta.status <> 'cancelado' ORDER BY CASE WHEN ta.status IN ('publicado','rascunho') AND ta.data_treino >= CURRENT_DATE THEN 0 ELSE 1 END, CASE WHEN ta.data_treino >= CURRENT_DATE THEN ta.data_treino END ASC, ta.data_treino DESC, ta.data_criacao DESC LIMIT 30");
        $prescriptionStmt->execute([':treinador' => $idUsuario, ':atleta' => $selectedAthleteId]);
        $selectedPrescriptions = $prescriptionStmt->fetchAll();

        if (stridebr_db_bool($selectedLink['pode_ver_cronograma'] ?? false)) {
            $scheduleStmt = $pdo->prepare("SELECT nome, descricao, visibilidade, data_atualizacao FROM cronogramas WHERE idusuario = :atleta AND ativo = TRUE ORDER BY data_atualizacao DESC LIMIT 12");
            $scheduleStmt->execute([':atleta' => $selectedAthleteId]);
            $selectedSchedules = $scheduleStmt->fetchAll();
        }
        if (stridebr_db_bool($selectedLink['pode_ver_atividades'] ?? false)) {
            $activityStmt = $pdo->prepare("SELECT ra.idregistro, COALESCE(NULLIF(ra.titulo,''), m.nome) AS titulo, ra.data_inicio, m.nome AS modalidade_nome, m.slug AS modalidade_slug, mm.nome AS modelo_nome FROM registros_atividade ra JOIN modalidades m ON m.idmodalidade = ra.idmodalidade JOIN modelos_modalidade mm ON mm.idmodelo = ra.idmodelo WHERE ra.idusuario = :atleta AND ra.excluido_em IS NULL AND ra.status = 'concluido' ORDER BY ra.data_inicio DESC LIMIT 10");
            $activityStmt->execute([':atleta' => $selectedAthleteId]);
            $selectedActivities = $activityStmt->fetchAll();
        }
    } else {
        $errors[] = stridebr_t('trainer.no_active_link');
        $selectedAthleteId = '';
    }
}

$planningToday = new DateTimeImmutable('today');
$planningStart = $planningToday->modify('-' . $planningToday->format('w') . ' days')->format('Y-m-d');
$planningSummary = null;
if ($selectedLink && stridebr_db_bool($selectedLink['pode_ver_cronograma']) && stridebr_db_bool($selectedLink['pode_ver_atividades'])) {
    $planningSummary = planejamentoSemana($pdo, $selectedAthleteId, $planningStart);
}

$flashes = stridebr_take_flashes();
$defaultDate = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');

function treinadorAvatar(array $user): string
{
    return stridebr_profile_photo_url((string) ($user['fotousuario'] ?? ''), 96);
}
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <title><?php echo stridebr_e(stridebr_t('trainer.html_title')); ?></title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/cronogramas.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content">
        <div class="page-shell trainer-shell">
            <div class="page-heading trainer-heading">
                <div><span class="eyebrow"><?php echo stridebr_e(stridebr_t('trainer.connected_training')); ?></span><h1><?php echo stridebr_e(stridebr_t('trainer.page_title')); ?></h1></div>
            </div>

            <nav class="planning-subnav monthly-planning-subnav" aria-label="<?php echo stridebr_e(stridebr_t('schedule.planning_navigation')); ?>">
                <a href="/user/cronogramatreinos.php"><?php echo stridebr_e(stridebr_t('common.schedules')); ?></a>
                <a href="/user/agenda-mensal.php<?php echo $selectedAthleteId !== '' ? '?atleta=' . rawurlencode($selectedAthleteId) : ''; ?>"><?php echo stridebr_e(stridebr_t('schedule.monthly_agenda')); ?></a>
                <a class="is-active" href="/user/treinador.php<?php echo $selectedAthleteId !== '' ? '?atleta=' . rawurlencode($selectedAthleteId) : ''; ?>"><?php echo stridebr_e(stridebr_t('trainer.page_title')); ?></a>
            </nav>

            <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div><?php endforeach; ?>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>

            <?php require dirname(__DIR__, 2) . '/src/layout/trainer_as_athlete.php'; ?>
            <section class="trainer-section" data-trainer-coach-section>
                <div class="section-title-row"><div><h2><?php echo stridebr_e(stridebr_t('trainer.coach_mode')); ?></h2></div></div>
                <?php if (!$trainerMode): ?>
                    <div class="content-card trainer-enable-card"><p><?php echo stridebr_e(stridebr_t('trainer.enable_help')); ?></p><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="activate_trainer"><button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('trainer.enable_mode')); ?></button></form></div>
                <?php else: ?>


                    <div class="trainer-people-grid trainer-athlete-list">
                        <?php foreach ($asTrainer as $link): ?>
                            <article class="content-card trainer-person-card<?php echo $selectedAthleteId === $link['idatleta'] ? ' is-selected' : ''; ?>">
                                <div class="trainer-person-heading"><img src="<?php echo stridebr_e(treinadorAvatar($link)); ?>" alt="" width="48" height="48" loading="lazy" decoding="async"><div><strong><?php echo stridebr_e(stridebr_person_name_for_display((string) $link['nome_exibicao'], (string) ($link['username'] ?? ''), stridebr_t('common.user'), 60)); ?></strong><?php if ($link['username']): ?><span>@<?php echo stridebr_e($link['username']); ?></span><?php endif; ?></div><span class="status-pill"><?php echo stridebr_t($link['status'] === 'aceito' ? 'trainer.athlete' : ($link['solicitado_por'] === 'atleta' ? 'trainer.athlete_request_received' : 'trainer.athlete_invite_sent')); ?></span></div>
                                <?php if ($link['status'] === 'aceito'): ?><div class="trainer-athlete-facts">
                                    <?php if (!empty($link['proximo_treino'])): ?><p><?php echo stridebr_e(stridebr_t('trainer.upcoming_prescriptions')); ?>: <strong><?php echo stridebr_e($link['proximo_treino']); ?></strong></p><?php endif; ?>
                                    <?php if (!empty($link['ultima_atividade'])): ?><p><?php echo stridebr_e(stridebr_t('trainer.recent_activities')); ?>: <?php echo stridebr_e(stridebr_format_datetime_short($link['ultima_atividade'])); ?></p><?php endif; ?>
                                    <?php if ((int) $link['prescricoes_nao_realizadas'] > 0): ?><p><?php echo stridebr_e(stridebr_tn('trainer.recent_missed.one', 'trainer.recent_missed.other', (int) $link['prescricoes_nao_realizadas'])); ?></p><?php endif; ?>
                                    <?php if ((int) $link['feedback_pendente'] > 0): ?><p><?php echo stridebr_e(stridebr_t('planning.feedback_pending')); ?>: <?php echo (int) $link['feedback_pendente']; ?></p><?php endif; ?>
                                </div><?php endif; ?>
                                <?php if ($link['status'] === 'pendente' && $link['solicitado_por'] === 'atleta'): ?><div class="trainer-card-actions"><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="accept_link"><input type="hidden" name="idvinculo" value="<?php echo stridebr_e($link['idvinculo']); ?>"><button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('trainer.accept_athlete')); ?></button></form><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="reject_link"><input type="hidden" name="idvinculo" value="<?php echo stridebr_e($link['idvinculo']); ?>"><button type="submit" class="secondary-button"><?php echo stridebr_e(stridebr_t('friends.decline')); ?></button></form></div><?php endif; ?>
                                <?php if ($link['status'] === 'aceito'): ?><div class="trainer-card-actions"><a class="primary-button" data-trainer-athlete-link href="/user/treinador.php?atleta=<?php echo rawurlencode((string) $link['idatleta']); ?>#athlete-workspace"><?php echo stridebr_e(stridebr_t('trainer.open_athlete')); ?></a><details class="trainer-more-menu"><summary aria-label="<?php echo stridebr_e(stridebr_t('schedule.more_actions')); ?>">•••</summary><div><form method="POST" data-confirm="<?php echo stridebr_e(stridebr_t('trainer.end_athlete_confirm')); ?>"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="end_link"><input type="hidden" name="idvinculo" value="<?php echo stridebr_e($link['idvinculo']); ?>"><button type="submit" class="is-danger"><?php echo stridebr_e(stridebr_t('trainer.end_link')); ?></button></form></div></details></div><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                        <?php if ($asTrainer === []): ?><div class="content-card trainer-empty rich"><strong><?php echo stridebr_e(stridebr_t('trainer.no_athletes')); ?></strong><p><?php echo stridebr_e(stridebr_t('trainer.no_athletes_help')); ?></p></div><?php endif; ?>
                    </div>
                    <details class="trainer-section"><summary><?php echo stridebr_e(stridebr_t('trainer.invite_athlete')); ?> · <?php echo stridebr_e(stridebr_t('trainer.coach_mode')); ?></summary>
                    <div class="trainer-mode-toolbar content-card"><form method="POST" class="trainer-invite-form"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="invite_athlete"><label><?php echo stridebr_e(stridebr_t('trainer.invite_athlete')); ?><input type="text" name="username" maxlength="40" placeholder="@username" autocapitalize="none" spellcheck="false" required></label><button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('trainer.send_invite')); ?></button></form><form method="POST" data-confirm="<?php echo stridebr_e(stridebr_t('trainer.pause_confirm')); ?>"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="deactivate_trainer"><button type="submit" class="secondary-button"><?php echo stridebr_e(stridebr_t('trainer.pause_mode')); ?></button></form></div>                    </details>
                <?php endif; ?>
            </section>

            <?php if ($selectedAthlete !== [] && $selectedLink !== []): ?>
                <section class="trainer-athlete-workspace" id="athlete-workspace" tabindex="-1">
                    <div class="trainer-athlete-header content-card"><div class="trainer-person-heading"><img src="<?php echo stridebr_e(treinadorAvatar($selectedAthlete)); ?>" alt="" width="56" height="56" decoding="async"><div><span><?php echo stridebr_e(stridebr_t('trainer.selected_athlete')); ?></span><h2><?php echo stridebr_e(stridebr_person_name_for_display((string) $selectedAthlete['nome_exibicao'], (string) ($selectedAthlete['username'] ?? ''), stridebr_t('common.user'), 60)); ?></h2><?php if ($selectedAthlete['username']): ?><small>@<?php echo stridebr_e($selectedAthlete['username']); ?></small><?php endif; ?></div></div><div class="trainer-athlete-actions"><?php if (stridebr_db_bool($selectedLink['pode_prescrever'])): ?><button type="button" class="primary-button" data-open-prescription><?php echo stridebr_e(stridebr_t('trainer.prescribe_action')); ?></button><?php endif; ?><a class="secondary-button" href="/user/agenda-mensal.php?atleta=<?php echo rawurlencode($selectedAthleteId); ?>"><?php echo stridebr_e(stridebr_t('schedule.monthly_agenda')); ?></a></div></div>

                    <?php if ($planningSummary !== null) require dirname(__DIR__, 2) . '/src/layout/planning_week.php';
                    else echo '<p>' . stridebr_e(stridebr_t('planning.permissions_help')) . '</p>'; ?>

                    <?php if (stridebr_db_bool($selectedLink['pode_prescrever'])): ?>
                    <div class="trainer-prescription-modal" data-prescription-modal hidden>
                        <button type="button" class="trainer-prescription-backdrop" data-close-prescription aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>"></button>
                        <section class="content-card trainer-prescription-editor trainer-prescription-dialog" role="dialog" aria-modal="true" aria-labelledby="trainer-prescription-title">
                            <div class="trainer-prescription-modal-heading"><div><span class="eyebrow"><?php echo stridebr_e(stridebr_t('trainer.prescription')); ?></span><h2 id="trainer-prescription-title"><?php echo stridebr_e(stridebr_t('trainer.new_date_workout')); ?></h2><p><?php echo stridebr_e(stridebr_t('trainer.prescription_help')); ?></p></div><button type="button" class="icon-button" data-close-prescription aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button></div>
                        <form method="POST" data-prescription-form>
                            <?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="create_prescription"><input type="hidden" name="idatleta" value="<?php echo stridebr_e($selectedAthleteId); ?>"><input type="hidden" name="redirect_atleta" value="<?php echo stridebr_e($selectedAthleteId); ?>">
                            <div class="trainer-form-grid"><label><?php echo stridebr_e(stridebr_t('common.title')); ?><input type="text" name="titulo" maxlength="120" required placeholder="<?php echo stridebr_e(stridebr_t('trainer.title_example')); ?>"></label><label><?php echo stridebr_e(stridebr_t('common.date')); ?><input type="date" name="data_treino" value="<?php echo stridebr_e($defaultDate); ?>" required></label><label class="time24-field-label"><?php echo stridebr_e(stridebr_t('common.time')); ?><div class="time24-control" data-time24><div class="time24-input-row"><input type="text" inputmode="numeric" maxlength="2" data-time24-hours><span class="time24-separator">:</span><input type="text" inputmode="numeric" maxlength="2" data-time24-minutes><button type="button" class="time24-toggle" data-time24-toggle aria-label="<?php echo stridebr_e(stridebr_t('agenda.choose_time')); ?>">⌄</button></div><div class="time24-menu" data-time24-menu hidden><div class="time24-menu-head"><span><?php echo stridebr_e(stridebr_t('schedule.time_24h')); ?></span><button type="button" data-time24-now><?php echo stridebr_e(stridebr_t('common.now')); ?></button></div><div class="time24-hours-grid" data-time24-hours-grid></div><div class="time24-minutes-grid" data-time24-minutes-grid></div></div><input type="hidden" name="hora_inicio" value="" data-time24-value></div></label><label><?php echo stridebr_e(stridebr_t('trainer.expected_duration')); ?><input type="number" name="duracao_prevista_min" min="1" max="1440" inputmode="numeric" placeholder="60"></label><label class="trainer-form-wide"><?php echo stridebr_e(stridebr_t('trainer.orientation')); ?><textarea name="descricao" rows="3" maxlength="5000" placeholder="<?php echo stridebr_e(stridebr_t('trainer.orientation_example')); ?>"></textarea></label></div>
                            <div class="prescription-exercises-heading"><div><strong><?php echo stridebr_e(stridebr_t('trainer.exercise_blocks')); ?></strong></div><button type="button" class="secondary-button" data-add-prescription-exercise><?php echo stridebr_e(stridebr_t('trainer.add_exercise')); ?></button></div>
                            <div class="prescription-exercises" data-prescription-exercises>
                                <div class="prescription-exercise-row" data-prescription-exercise><input name="exercise_name[]" maxlength="120" aria-label="<?php echo stridebr_e(stridebr_t('trainer.exercise_block')); ?>" placeholder="<?php echo stridebr_e(stridebr_t('trainer.exercise_block')); ?>"><input name="exercise_series[]" type="number" min="1" max="99" inputmode="numeric" aria-label="<?php echo stridebr_e(stridebr_t('common.series')); ?>" placeholder="<?php echo stridebr_e(stridebr_t('common.series')); ?>"><input name="exercise_reps[]" maxlength="40" aria-label="<?php echo stridebr_e(stridebr_t('trainer.reps_distance')); ?>" placeholder="<?php echo stridebr_e(stridebr_t('trainer.reps_distance')); ?>"><input name="exercise_load[]" maxlength="40" aria-label="<?php echo stridebr_e(stridebr_t('trainer.load_target')); ?>" placeholder="<?php echo stridebr_e(stridebr_t('trainer.load_target')); ?>"><input name="exercise_rest[]" maxlength="40" aria-label="<?php echo stridebr_e(stridebr_t('home.rest')); ?>" placeholder="<?php echo stridebr_e(stridebr_t('home.rest')); ?>"><input name="exercise_notes[]" maxlength="1000" aria-label="<?php echo stridebr_e(stridebr_t('activity.notes')); ?>" placeholder="<?php echo stridebr_e(stridebr_t('activity.notes')); ?>"><button type="button" class="icon-button" data-remove-prescription-exercise aria-label="<?php echo stridebr_e(stridebr_t('library.remove_exercise')); ?>">×</button></div>
                            </div>
                            <div class="trainer-card-actions"><button type="submit" name="submit_mode" value="draft" class="secondary-button"><?php echo stridebr_e(stridebr_t('trainer.save_draft')); ?></button><button type="submit" name="submit_mode" value="publish" class="primary-button"><?php echo stridebr_e(stridebr_t('trainer.publish')); ?></button></div>
                        </form>
                        </section>
                    </div>
                    <?php else: ?><div class="alert alert-info"><?php echo stridebr_e(stridebr_t('trainer.no_prescribe_permission')); ?></div><?php endif; ?>

                    <?php
                    $trainerUpcoming = count(array_filter($selectedPrescriptions, static fn(array $item): bool => in_array((string) ($item['status'] ?? ''), ['publicado','rascunho'], true) && (string) ($item['data_treino'] ?? '') >= date('Y-m-d')));
                    $trainerFeedbackCount = stridebr_db_bool($selectedLink['pode_ver_feedback'] ?? false) ? count(array_filter($selectedPrescriptions, static fn(array $item): bool => !empty($item['nota_atleta']) || trim((string) ($item['feedback_atleta'] ?? '')) !== '')) : null;
                    ?>
                    <p class="trainer-context-line"><?php echo $trainerUpcoming; ?> <?php echo stridebr_e(stridebr_t('trainer.upcoming_prescriptions')); ?><?php if ($trainerFeedbackCount !== null): ?> · <?php echo $trainerFeedbackCount; ?> <?php echo stridebr_e(stridebr_t('trainer.received_feedback')); ?><?php endif; ?></p>

                    <div class="trainer-workspace-grid">
                        <section class="content-card"><h2><?php echo stridebr_e(stridebr_t('trainer.prescriptions')); ?></h2><div class="trainer-mini-list"><?php if ($selectedPrescriptions === []): ?><div class="trainer-empty rich"><strong><?php echo stridebr_e(stridebr_t('trainer.no_prescriptions')); ?></strong></div><?php endif; ?><?php foreach ($selectedPrescriptions as $item): ?><article><div><span><?php echo stridebr_e(stridebr_format_date_short((string) $item['data_treino'])); ?><?php echo $item['hora_inicio'] ? ' · ' . stridebr_e(substr((string) $item['hora_inicio'], 0, 5)) : ''; ?></span><strong><?php echo stridebr_e($item['titulo']); ?></strong><small><?php echo stridebr_e(stridebr_tn('trainer.exercise_count.one','trainer.exercise_count.other',(int)$item['exercicios_total'],['count'=>(int)$item['exercicios_total']])); ?> · <?php echo stridebr_e(stridebr_t('planning.status.' . $item['status'])); ?></small><?php if (stridebr_db_bool($selectedLink['pode_ver_feedback']) && $item['status'] === 'concluido' && empty($item['feedback_em'])): ?><em><?php echo stridebr_e(stridebr_t('planning.feedback_pending')); ?></em><?php endif; ?><?php if (stridebr_db_bool($selectedLink['pode_ver_feedback']) && $item['nota_atleta']): ?><em><?php echo stridebr_e(stridebr_t('trainer.feedback_label')); ?> <?php echo (int) $item['nota_atleta']; ?>/5<?php echo $item['feedback_atleta'] ? ' · ' . stridebr_e($item['feedback_atleta']) : ''; ?></em><?php endif; ?></div><div class="trainer-card-actions"><?php if ($item['status'] === 'rascunho'): ?><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="publish_prescription"><input type="hidden" name="idagendamento" value="<?php echo stridebr_e($item['idagendamento']); ?>"><input type="hidden" name="redirect_atleta" value="<?php echo stridebr_e($selectedAthleteId); ?>"><button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('trainer.publish')); ?></button></form><?php endif; ?><?php if (in_array($item['status'], ['rascunho','publicado'], true)): ?><details class="trainer-more-menu"><summary aria-label="<?php echo stridebr_e(stridebr_t('schedule.more_actions')); ?>">•••</summary><div><form method="POST" data-confirm="<?php echo stridebr_e(stridebr_t('trainer.cancel_prescription_confirm')); ?>"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="cancel_prescription"><input type="hidden" name="idagendamento" value="<?php echo stridebr_e($item['idagendamento']); ?>"><input type="hidden" name="redirect_atleta" value="<?php echo stridebr_e($selectedAthleteId); ?>"><button type="submit" class="is-danger"><?php echo stridebr_e(stridebr_t('trainer.cancel_prescription')); ?></button></form></div></details><?php endif; ?></div></article><?php endforeach; ?></div></section>

                        <section class="content-card"><h2><?php echo stridebr_e(stridebr_t('common.schedules')); ?></h2><?php if (!stridebr_db_bool($selectedLink['pode_ver_cronograma'])): ?><p><?php echo stridebr_e(stridebr_t('trainer.no_schedule_permission')); ?></p><?php elseif ($selectedSchedules === []): ?><div class="trainer-empty rich"><strong><?php echo stridebr_e(stridebr_t('trainer.no_active_schedules')); ?></strong><p><?php echo stridebr_e(stridebr_t('trainer.no_schedule_available')); ?></p></div><?php else: ?><div class="trainer-mini-list"><?php foreach ($selectedSchedules as $schedule): ?><article><div><strong><?php echo stridebr_e($schedule['nome']); ?></strong><small><?php echo stridebr_e(stridebr_t(['privado' => 'common.private', 'amigos' => 'common.friends', 'publico' => 'common.public'][$schedule['visibilidade']] ?? 'common.private')); ?><?php echo $schedule['descricao'] ? ' · ' . stridebr_e($schedule['descricao']) : ''; ?></small></div></article><?php endforeach; ?></div><?php endif; ?></section>

                        <section class="content-card"><h2><?php echo stridebr_e(stridebr_t('home.recent_activities')); ?></h2><?php if (!stridebr_db_bool($selectedLink['pode_ver_atividades'])): ?><p><?php echo stridebr_e(stridebr_t('trainer.no_activity_permission')); ?></p><?php elseif ($selectedActivities === []): ?><div class="trainer-empty rich"><strong><?php echo stridebr_e(stridebr_t('trainer.no_shared_activities')); ?></strong><p><?php echo stridebr_e(stridebr_t('trainer.no_shared_help')); ?></p></div><?php else: ?><div class="trainer-mini-list"><?php foreach ($selectedActivities as $activity): ?><article><div><span><?php echo stridebr_e(stridebr_format_datetime_short((string) $activity['data_inicio'])); ?></span><strong><?php echo stridebr_e($activity['titulo']); ?></strong><small><?php echo stridebr_e(stridebr_sport_name((string) $activity['modalidade_slug'], (string) $activity['modalidade_nome'])); ?></small></div></article><?php endforeach; ?></div><?php endif; ?></section>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/time24.js')); ?>"></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/trainer.js')); ?>"></script>
</body>
</html>
