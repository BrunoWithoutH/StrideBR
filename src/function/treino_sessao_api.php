<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/errors.php';
require_once dirname(__DIR__) . '/includes/app.php';

$idUsuario = stridebr_require_login();
require_once dirname(__DIR__) . '/config/pg_config.php';
require_once __DIR__ . '/workout_session_service.php';
require_once __DIR__ . '/teams_surface_provider.php';

header('Content-Type: application/json; charset=UTF-8');

function sessaoJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!sessaoFeatureAtiva($pdo)) {
    sessaoJson(['ok' => false, 'error' => 'A execução de treinos está temporariamente desativada.'], 503);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'current');

try {
    if ($method === 'GET' && $action === 'current') {
        $includeHistory = (string) ($_GET['history'] ?? '1') !== '0';
        stridebr_session_release();
        header('Cache-Control: private, no-store');
        sessaoJson(['ok' => true, 'session' => sessaoCarregar($pdo, $idUsuario, $includeHistory) ?: null]);
    }

    if ($method !== 'POST') sessaoJson(['ok' => false, 'message' => 'Método inválido.'], 405);
    stridebr_verify_csrf();

    if ($action === 'start') {
        $session = sessaoIniciarCronograma($pdo, $idUsuario, trim((string) ($_POST['idtreino'] ?? '')), [
            'data_ocorrencia_origem' => $_POST['data_ocorrencia_origem'] ?? null,
            'data_ocorrencia_planejada' => $_POST['data_ocorrencia_planejada'] ?? null,
            'hora_ocorrencia_planejada' => $_POST['hora_ocorrencia_planejada'] ?? null,
        ]);
        sessaoJson(['ok' => true, 'session' => $session]);
    }

    if ($action === 'start_scheduled') {
        $session = sessaoIniciarAgendado($pdo, $idUsuario, trim((string) ($_POST['idagendamento'] ?? '')));
        sessaoJson(['ok' => true, 'session' => $session]);
    }

    if ($action === 'start_institutional') {
        if (!stridebr_teams_enabled()) sessaoJson(['ok' => false, 'message' => 'Treino não encontrado.'], 404);
        $trainingRef = trim((string) ($_POST['training_ref'] ?? ''));
        $training = $trainingRef !== '' ? stridebr_teams_surface_training($pdo, $idUsuario, $trainingRef) : null;
        if (!$training) sessaoJson(['ok' => false, 'message' => 'Treino não encontrado.'], 404);
        $session = sessaoIniciarInstitucional($pdo, $idUsuario, $training);
        sessaoJson(['ok' => true, 'session' => $session]);
    }

    if ($action === 'update_set') {
        $defaults = is_array($_SESSION['workout_field_defaults'] ?? null) ? $_SESSION['workout_field_defaults'] : [];
        $legacy = is_array($_SESSION['workout_load_defaults'] ?? null) ? $_SESSION['workout_load_defaults'] : [];
        $current = sessaoCarregar($pdo, $idUsuario, false);
        $sessionDefaults = $current !== [] ? ($defaults[(string) $current['idsessao']] ?? ['load'=>$legacy[(string) $current['idsessao']] ?? []]) : [];
        $result = sessaoAtualizarSerie(
            $pdo,
            $idUsuario,
            trim((string) ($_POST['idserie'] ?? '')),
            $_POST['repeticoes'] ?? '',
            $_POST['carga'] ?? '',
            ($_POST['propagate'] ?? $_POST['propagate_load'] ?? '0') === '1',
            trim((string) ($_POST['edited_field'] ?? '')),
            is_array($sessionDefaults) ? $sessionDefaults : [],
            $current !== [] ? (string) $current['idsessao'] : null,
            $_POST['duracao'] ?? null,
            $_POST['distancia'] ?? null
        );
        if ($current !== []) $_SESSION['workout_field_defaults'] = [(string) $current['idsessao'] => $result['field_defaults']];
        sessaoJson(['ok' => true, 'session' => $result['session']]);
    }

    if ($action === 'toggle_set') {
        sessaoJson(['ok' => true, 'session' => sessaoAlternarSerie($pdo, $idUsuario, trim((string) ($_POST['idserie'] ?? '')), stridebr_db_bool($_POST['concluida'] ?? false))]);
    }

    if ($action === 'toggle_exercise') {
        sessaoJson(['ok' => true, 'session' => sessaoAlternarExercicio($pdo, $idUsuario, trim((string) ($_POST['idsessao_exercicio'] ?? '')), stridebr_db_bool($_POST['concluido'] ?? false))]);
    }

    if ($action === 'mark_all') {
        sessaoJson(['ok' => true, 'session' => sessaoMarcarTudo($pdo, $idUsuario, stridebr_db_bool($_POST['concluido'] ?? true))]);
    }

    if ($action === 'quick_register') {
        $result = sessaoRegistroRapidoCronograma($pdo, $idUsuario, trim((string) ($_POST['idtreino'] ?? '')), $_POST);
        sessaoJson(['ok' => true, 'activity_id' => $result['activity_id']]);
    }

    if ($action === 'cancel') {
        sessaoCancelar($pdo, $idUsuario);
        sessaoJson(['ok' => true, 'session' => null]);
    }

    if ($action === 'finish') {
        $session = sessaoCarregar($pdo, $idUsuario, false);
        if ($session === []) throw new RuntimeException('Nenhum treino em andamento.');
        $result = sessaoFinalizar($pdo, $idUsuario, (string) $session['idsessao'], $_POST);
        sessaoJson(['ok' => true, 'session' => null, 'activity_id' => $result['activity_id'], 'summary' => $result['summary']]);
    }

    sessaoJson(['ok' => false, 'message' => 'Ação inválida.'], 400);
} catch (WorkoutSessionAlreadyActiveException $e) {
    sessaoJson(['ok' => false, 'message' => $e->getMessage(), 'session' => $e->session], 409);
} catch (Throwable $e) {
    error_log('Workout session API: ' . $e->getMessage());
    $message = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível atualizar o treino.';
    sessaoJson(['ok' => false, 'message' => $message], 400);
}
