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
$redirectAthleteWorkout = '';
$exerciseRowsFromPost = static function (array $post): array {
    if (is_array($post['rows'] ?? null)) return array_values(array_filter($post['rows'], 'is_array'));
    $ids = is_array($post['exercise_id'] ?? null) ? $post['exercise_id'] : [];
    $names = is_array($post['exercise_name'] ?? null) ? $post['exercise_name'] : [];
    $series = is_array($post['exercise_series'] ?? null) ? $post['exercise_series'] : [];
    $reps = is_array($post['exercise_reps'] ?? null) ? $post['exercise_reps'] : [];
    $loads = is_array($post['exercise_load'] ?? null) ? $post['exercise_load'] : [];
    $durations = is_array($post['exercise_duration'] ?? null) ? $post['exercise_duration'] : [];
    $distances = is_array($post['exercise_distance'] ?? null) ? $post['exercise_distance'] : [];
    $rests = is_array($post['exercise_rest'] ?? null) ? $post['exercise_rest'] : [];
    $notes = is_array($post['exercise_notes'] ?? null) ? $post['exercise_notes'] : [];
    $rows = [];
    foreach ($names as $index => $name) $rows[] = ['idexercicio'=>$ids[$index] ?? '','nome'=>$name,'series'=>$series[$index] ?? '','repeticoes'=>$reps[$index] ?? '','carga'=>$loads[$index] ?? '','duracao'=>$durations[$index] ?? '','distancia'=>$distances[$index] ?? '','descanso'=>$rests[$index] ?? '','observacoes'=>$notes[$index] ?? ''];
    return $rows;
};

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
            $exerciseRows = $exerciseRowsFromPost($_POST);
            $status = (string) ($_POST['submit_mode'] ?? '') === 'publish' ? 'publicado' : 'rascunho';
            $athleteId = trim((string) ($_POST['idatleta'] ?? ''));
            $prescriptionId = treinadorCriarPrescricao($pdo, $idUsuario, $athleteId, [
                'titulo' => $_POST['titulo'] ?? '',
                'descricao' => $_POST['descricao'] ?? '',
                'data_treino' => $_POST['data_treino'] ?? '',
                'hora_inicio' => $_POST['hora_inicio'] ?? '',
                'duracao_prevista_min' => $_POST['duracao_prevista_min'] ?? '',
                'idmodalidade' => $_POST['idmodalidade'] ?? '',
                'distancia_prevista_m' => $_POST['distancia_prevista_m'] ?? '',
                'status' => $status,
            ], $exerciseRows);
            $redirectAthlete = $athleteId;
            if ($status === 'publicado') notificacaoCriar($pdo, $athleteId, 'treino_prescrito', stridebr_t('notification.prescription.title'), stridebr_t('notification.prescription.message'), '/user/treinador.php');
            stridebr_flash('success', stridebr_t($status === 'publicado' ? 'trainer.workout_published' : 'trainer.prescription_draft_saved'));
        } elseif ($action === 'edit_prescription') {
            $appointmentId = trim((string) ($_POST['idagendamento'] ?? ''));
            $currentPrescription = treinadorPrescricao($pdo, $idUsuario, $appointmentId);
            $redirectAthlete = (string) ($currentPrescription['idatleta'] ?? '');
            treinadorEditarPrescricao($pdo, $idUsuario, $appointmentId, [
                'titulo' => $_POST['titulo'] ?? '',
                'descricao' => $_POST['descricao'] ?? '',
                'data_treino' => $_POST['data_treino'] ?? '',
                'hora_inicio' => $_POST['hora_inicio'] ?? '',
                'duracao_prevista_min' => $_POST['duracao_prevista_min'] ?? '',
                'idmodalidade' => $_POST['idmodalidade'] ?? '',
                'distancia_prevista_m' => $_POST['distancia_prevista_m'] ?? '',
                'status' => (string) ($_POST['submit_mode'] ?? '') === 'publish' ? 'publicado' : 'rascunho',
            ], $exerciseRowsFromPost($_POST));
            stridebr_flash('success', stridebr_t('trainer.prescription_updated'));
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
        } elseif ($action === 'apply_model') {
            $athleteId = trim((string) ($_POST['idatleta'] ?? ''));
            $status = (string) ($_POST['submit_mode'] ?? '') === 'draft' ? 'rascunho' : 'publicado';
            treinadorAplicarTreinoModelo($pdo, $idUsuario, $athleteId, (string) ($_POST['idtreino_modelo'] ?? ''), [
                'data_treino' => $_POST['data_treino'] ?? '',
                'hora_inicio' => $_POST['hora_inicio'] ?? '',
                'status' => $status,
            ]);
            $redirectAthlete = $athleteId;
            if ($status === 'publicado') notificacaoCriar($pdo, $athleteId, 'treino_prescrito', stridebr_t('notification.prescription.title'), stridebr_t('notification.prescription.message'), '/user/treinador.php');
            stridebr_flash('success', stridebr_t('trainer.library_workout_applied'));
        } elseif ($action === 'apply_plan') {
            $athleteId = trim((string) ($_POST['idatleta'] ?? ''));
            $status = (string) ($_POST['submit_mode'] ?? '') === 'draft' ? 'rascunho' : 'publicado';
            $application = treinadorAplicarCronograma(
                $pdo,
                $idUsuario,
                $athleteId,
                (string) ($_POST['idcronograma'] ?? ''),
                (string) ($_POST['data_inicio'] ?? ''),
                trim((string) ($_POST['data_fim'] ?? '')) ?: null,
                $status,
                (string) ($_POST['idempotency_key'] ?? '')
            );
            $redirectAthlete = $athleteId;
            if ($status === 'publicado') notificacaoCriar($pdo, $athleteId, 'treino_prescrito', stridebr_t('notification.plan_applied.title'), stridebr_t('notification.plan_applied.message'), '/user/treinador.php');
            stridebr_flash('success', stridebr_t('trainer.plan_applied'));
        } elseif ($action === 'remove_plan') {
            $redirectAthlete = trim((string) ($_POST['idatleta'] ?? $_POST['redirect_atleta'] ?? ''));
            $result = treinadorRemoverAplicacaoCronograma($pdo, $idUsuario, (string) ($_POST['idaplicacao'] ?? ''));
            stridebr_flash('success', stridebr_t('trainer.plan_removed', ['count'=>(int)($result['cancelled'] ?? 0)]));
        } elseif ($action === 'create_comment') {
            $appointmentId = (string) ($_POST['idagendamento'] ?? '');
            $auth = treinadorComentarioAutorizar($pdo, $idUsuario, $appointmentId, true);
            treinadorCriarComentario($pdo, $idUsuario, $appointmentId, (string) ($_POST['texto'] ?? ''));
            if (($auth['role'] ?? '') === 'coach') {
                $redirectAthlete = (string) ($auth['appointment']['idatleta'] ?? '');
            } else {
                $redirectAthleteWorkout = $appointmentId;
            }
            stridebr_flash('success', stridebr_t('trainer.comment_saved'));
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
        if ($redirectAthleteWorkout !== '') {
            $location .= '?context=athlete&workout=' . rawurlencode($redirectAthleteWorkout) . '#trainer-comments-' . rawurlencode($redirectAthleteWorkout);
        } elseif ($redirectAthlete !== '') {
            $location .= '?context=coach&view=athlete&id=' . rawurlencode($redirectAthlete);
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
$trainerContext = (string) ($_GET['context'] ?? (trim((string) ($_GET['atleta'] ?? '')) !== '' ? 'coach' : 'athlete'));
if (!in_array($trainerContext, ['athlete', 'coach'], true)) $trainerContext = 'athlete';
$trainerSearch = trim((string) ($_GET['trainer_q'] ?? ''));
$trainerSearchResults = $trainerContext === 'athlete' && $trainerSearch !== '' ? treinadorBuscarPessoas($pdo, $trainerSearch, $idUsuario, true, 8) : [];

$asAthleteStmt = $pdo->prepare("SELECT v.*, u.username, COALESCE(NULLIF(u.nome_exibicao,''), u.nomeusuario) AS nome_exibicao, u.fotousuario
    FROM vinculos_treinador_atleta v
    JOIN usuarios u ON u.idusuario = v.idtreinador
    WHERE v.idatleta = :me AND v.status IN ('pendente','aceito')
    ORDER BY CASE WHEN v.status = 'aceito' THEN 0 ELSE 1 END, v.data_atualizacao DESC");
$asAthleteStmt->execute([':me' => $idUsuario]);
$asAthlete = $asAthleteStmt->fetchAll();

$coachView = (string) ($_GET['view'] ?? 'overview');
if (!in_array($coachView, ['overview','athletes','calendar','library','athlete'], true)) $coachView = 'overview';
$athleteTab = (string) ($_GET['tab'] ?? 'summary');
if (!in_array($athleteTab, ['summary','calendar','schedules','activities'], true)) $athleteTab = 'summary';
$selectedAthleteId = trim((string) ($_GET['id'] ?? $_GET['atleta'] ?? ''));
if ($trainerContext === 'coach' && $selectedAthleteId !== '' && !isset($_GET['view'])) $coachView = 'athlete';
$coachSearch = trim((string) ($_GET['q'] ?? ''));
$coachFilter = (string) ($_GET['filter'] ?? 'all');
$coachPage = max(1, (int) ($_GET['page'] ?? 1));
$coachLibraryTab = (string) ($_GET['library_tab'] ?? 'workouts');
if (!in_array($coachLibraryTab, ['workouts','plans'], true)) $coachLibraryTab = 'workouts';

$asTrainer = [];
$coachOverview = ['stats'=>['athletes'=>0,'today'=>0,'completed'=>0,'attention'=>0],'attention'=>[],'today'=>[],'recent'=>[]];
$coachAthletes = ['items'=>[],'total'=>0,'page'=>1,'pages'=>1];
$coachLibrary = ['workouts'=>[],'schedules'=>[]];
$selectedLink = [];
$selectedAthlete = [];
$selectedSchedules = [];
$selectedActivities = [];
$selectedPrescriptions = [];
$selectedApplications = [];
$athleteSummary = null;
$athleteCalendar = [];
$planPreview = null;
$planSourceId = trim((string) ($_GET['plan_source'] ?? ''));
$applyModelId = trim((string) ($_GET['apply_model'] ?? ''));
$applyModelData = [];
$workoutDetailId = trim((string) ($_GET['workout'] ?? ''));
$workoutComparison = null;
$workoutComments = [];

$today = new DateTimeImmutable('today');
$weekRaw = trim((string) ($_GET['week_start'] ?? ''));
if ($weekRaw !== '') {
    try { $weekStartDate = cronogramaValidarDataIso($weekRaw); } catch (Throwable) { $weekStartDate = $today->modify('-' . ((int)$today->format('N') - 1) . ' days'); }
} else {
    $weekStartDate = $today->modify('-' . ((int)$today->format('N') - 1) . ' days');
}
$weekStart = $weekStartDate->format('Y-m-d');
$weekEnd = $weekStartDate->modify('+6 days')->format('Y-m-d');

if ($trainerMode && $trainerContext === 'coach') {
    if ($coachView === 'overview') $coachOverview = treinadorWorkspaceOverview($pdo, $idUsuario);
    if (in_array($coachView, ['athletes','calendar'], true)) $coachAthletes = treinadorListarAtletasWorkspace($pdo, $idUsuario, $coachSearch, $coachFilter, $coachPage, 50);
    if ($coachView === 'library') $coachLibrary = treinadorListarBiblioteca($pdo, $idUsuario);

    if ($selectedAthleteId !== '') {
        $selectedLink = treinadorVinculoAceito($pdo, $idUsuario, $selectedAthleteId);
        if ($selectedLink !== []) {
            $selectedAthlete = treinadorUsuario($pdo, $selectedAthleteId);
            $prescriptionStmt = $pdo->prepare("SELECT ta.*, (SELECT COUNT(*) FROM treinos_agendados_exercicios tae WHERE tae.idagendamento=ta.idagendamento) AS exercicios_total FROM treinos_agendados ta WHERE ta.idcriador=:trainer AND ta.idatleta=:athlete AND ta.origem='treinador' AND ta.status <> 'cancelado' ORDER BY CASE WHEN ta.data_treino>=CURRENT_DATE THEN 0 ELSE 1 END, CASE WHEN ta.data_treino>=CURRENT_DATE THEN ta.data_treino END, ta.data_treino DESC, ta.hora_inicio NULLS LAST LIMIT 40");
            $prescriptionStmt->execute([':trainer'=>$idUsuario,':athlete'=>$selectedAthleteId]);
            $selectedPrescriptions = $prescriptionStmt->fetchAll();
            if (stridebr_db_bool($selectedLink['pode_ver_cronograma'] ?? false)) {
                $scheduleStmt = $pdo->prepare("SELECT idcronograma,nome,descricao,visibilidade,data_atualizacao FROM cronogramas WHERE idusuario=:athlete AND ativo=TRUE ORDER BY data_atualizacao DESC LIMIT 20");
                $scheduleStmt->execute([':athlete'=>$selectedAthleteId]); $selectedSchedules=$scheduleStmt->fetchAll();
            }
            if (stridebr_db_bool($selectedLink['pode_ver_atividades'] ?? false)) {
                $activityStmt=$pdo->prepare("SELECT ra.idregistro,COALESCE(NULLIF(ra.titulo,''),m.nome) AS titulo,ra.data_inicio,m.nome AS modalidade_nome,m.slug AS modalidade_slug FROM registros_atividade ra JOIN modalidades m ON m.idmodalidade=ra.idmodalidade WHERE ra.idusuario=:athlete AND ra.excluido_em IS NULL AND ra.status='concluido' ORDER BY ra.data_inicio DESC LIMIT 20");
                $activityStmt->execute([':athlete'=>$selectedAthleteId]);$selectedActivities=$activityStmt->fetchAll();
            }
            $athleteSummary = treinadorResumoAtleta($pdo,$idUsuario,$selectedAthleteId,$weekStart);
            if ($athleteTab === 'calendar' || $coachView === 'calendar') $athleteCalendar = $athleteSummary['calendar'];
            $applications=$pdo->prepare("SELECT a.*,COUNT(ta.idagendamento) AS workouts_total,COUNT(*) FILTER (WHERE ta.status='concluido') AS completed_total FROM aplicacoes_cronograma_treinador a LEFT JOIN treinos_agendados ta ON ta.idaplicacao_cronograma=a.idaplicacao WHERE a.idtreinador=:trainer AND a.idatleta=:athlete GROUP BY a.idaplicacao ORDER BY a.criado_em DESC LIMIT 30");
            $applications->execute([':trainer'=>$idUsuario,':athlete'=>$selectedAthleteId]);$selectedApplications=$applications->fetchAll();
            if ($applyModelId !== '') $applyModelData = cronogramaBuscarTreinoModelo($pdo,$idUsuario,$applyModelId);
            if ($planSourceId !== '') {
                $planStart = trim((string) ($_GET['plan_start'] ?? ''));
                $planEnd = trim((string) ($_GET['plan_end'] ?? ''));
                if ($planStart !== '') {
                    try { $planPreview = treinadorPreverAplicacaoCronograma($pdo,$idUsuario,$selectedAthleteId,$planSourceId,$planStart,$planEnd !== '' ? $planEnd : null); }
                    catch (Throwable $e) { $errors[] = $e->getMessage(); }
                }
            }
            if ($workoutDetailId !== '') {
                try {
                    $workoutComparison=treinadorCompararPlanejadoRealizado($pdo,$idUsuario,$selectedAthleteId,$workoutDetailId);
                    if (stridebr_db_bool($selectedLink['pode_ver_feedback'] ?? false)) $workoutComments=treinadorListarComentarios($pdo,$idUsuario,$workoutDetailId);
                } catch (RuntimeException $e) { $errors[]=$e->getMessage(); }
            }
        } else {
            $errors[] = stridebr_t('trainer.no_active_link');
            $selectedAthleteId = '';
        }
    }
}

$myPrescriptions = [];
if ($trainerContext === 'athlete') {
    $myPrescriptionsStmt = $pdo->prepare("SELECT ta.*, COALESCE(NULLIF(u.nome_exibicao,''),u.nomeusuario) AS treinador_nome,u.username AS treinador_username,(SELECT COUNT(*) FROM treinos_agendados_exercicios tae WHERE tae.idagendamento=ta.idagendamento) AS exercicios_total FROM treinos_agendados ta LEFT JOIN usuarios u ON u.idusuario=ta.idcriador WHERE ta.idatleta=:me AND ta.origem='treinador' AND ta.status IN ('publicado','concluido') ORDER BY CASE WHEN ta.status='publicado' AND ta.data_treino>=CURRENT_DATE THEN 0 WHEN ta.status='concluido' AND ta.feedback_em IS NULL THEN 1 ELSE 2 END,CASE WHEN ta.status='publicado' AND ta.data_treino>=CURRENT_DATE THEN ta.data_treino END ASC,ta.data_treino DESC,ta.hora_inicio NULLS LAST LIMIT 30");
    $myPrescriptionsStmt->execute([':me'=>$idUsuario]); $myPrescriptions=$myPrescriptionsStmt->fetchAll();
}

$athleteComments = [];
$athleteCommentWorkout = $trainerContext === 'athlete' ? trim((string) ($_GET['workout'] ?? '')) : '';
if ($athleteCommentWorkout !== '') {
    try {
        $auth = treinadorComentarioAutorizar($pdo, $idUsuario, $athleteCommentWorkout, false);
        if (($auth['role'] ?? '') === 'athlete') $athleteComments = treinadorListarComentarios($pdo, $idUsuario, $athleteCommentWorkout);
    } catch (RuntimeException $e) {
        $errors[] = $e->getMessage();
        $athleteCommentWorkout = '';
    }
}

$editingPrescription = [];
$editPrescriptionId = trim((string) ($_GET['edit_prescription'] ?? ''));
if ($trainerMode && $selectedLink !== [] && $editPrescriptionId !== '') {
    $candidate = treinadorPrescricao($pdo, $idUsuario, $editPrescriptionId);
    if ($candidate !== [] && (string) ($candidate['idatleta'] ?? '') === $selectedAthleteId && in_array((string) ($candidate['status'] ?? ''), ['rascunho','publicado'], true) && (string) ($candidate['data_treino'] ?? '') >= date('Y-m-d')) $editingPrescription = $candidate;
}
$readonlyActivity = [];
$readonlySchedule = [];
if ($trainerMode && $selectedLink !== []) {
    $viewActivity = trim((string) ($_GET['view_activity'] ?? ''));
    $viewSchedule = trim((string) ($_GET['view_schedule'] ?? ''));
    try {
        if ($viewActivity !== '') $readonlyActivity = treinadorAtividadeReadOnly($pdo, $idUsuario, $selectedAthleteId, $viewActivity);
        if ($viewSchedule !== '') $readonlySchedule = treinadorCronogramaReadOnly($pdo, $idUsuario, $selectedAthleteId, $viewSchedule);
    } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
}

$planningSummary = null;
if ($selectedLink && stridebr_db_bool($selectedLink['pode_ver_cronograma']) && stridebr_db_bool($selectedLink['pode_ver_atividades'])) {
    $planningSummary = planejamentoSemana($pdo, $selectedAthleteId, $weekStart);
}

$flashes = stridebr_take_flashes();
$defaultDate = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
$prescriptionModalities = $trainerMode && $trainerContext === 'coach' && $selectedLink !== [] && stridebr_db_bool($selectedLink['pode_prescrever'] ?? false) ? cronogramaListarModalidadesTreino($pdo, $idUsuario) : [];

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
                <a class="is-active" href="/user/treinador.php?context=<?php echo stridebr_e($trainerContext); ?>"><?php echo stridebr_e(stridebr_t('trainer.page_title')); ?></a>
            </nav>
            <nav class="ux-context-nav trainer-context-nav" aria-label="<?php echo stridebr_e(stridebr_t('trainer.context_navigation')); ?>">
                <a class="<?php echo $trainerContext === 'athlete' ? 'is-active' : ''; ?>" href="/user/treinador.php?context=athlete"><?php echo stridebr_e(stridebr_t('trainer.as_athlete')); ?></a>
                <a class="<?php echo $trainerContext === 'coach' ? 'is-active' : ''; ?>" href="/user/treinador.php?context=coach"><?php echo stridebr_e(stridebr_t('trainer.as_coach')); ?></a>
            </nav>

            <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div><?php endforeach; ?>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>

            <?php if ($trainerContext === 'athlete') require dirname(__DIR__, 2) . '/src/layout/trainer_as_athlete.php'; ?>
            <?php if ($trainerContext === 'coach') require dirname(__DIR__, 2) . '/src/layout/trainer/coach_workspace.php'; ?>
        </div>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/time24.js')); ?>"></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/exercise-entry.js')); ?>"></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/workout-builder.js')); ?>"></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/trainer.js')); ?>"></script>
</body>
</html>
