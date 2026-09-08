<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma.php';
require_once dirname(__DIR__, 2) . '/src/function/product_analytics.php';
require_once dirname(__DIR__, 2) . '/src/function/notificacoes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

function quickCreateExercises(string $raw): array
{
    if (trim($raw) === '') return [];
    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) throw new InvalidArgumentException(stridebr_t('schedule.validation.exercise_list_invalid'));
    if (count($decoded) > 200) throw new InvalidArgumentException(stridebr_t('schedule.validation.exercise_limit'));
    return array_values(array_filter($decoded, 'is_array'));
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException(stridebr_t('schedule.validation.method_invalid'));
    stridebr_verify_csrf();
    stridebr_session_release();
    if (!cronogramaBibliotecaDisponivel($pdo)) throw new RuntimeException(stridebr_t('schedule.validation.library_unavailable'));

    $action = trim((string) ($_POST['action'] ?? ''));
    $titulo = trim((string) ($_POST['titulo'] ?? ''));
    $codigo = trim((string) ($_POST['codigo'] ?? ''));
    $foco = trim((string) ($_POST['foco'] ?? ''));
    $descricao = trim((string) ($_POST['descricao'] ?? ''));
    $idmodalidade = trim((string) ($_POST['idmodalidade'] ?? ''));
    $exercicios = quickCreateExercises((string) ($_POST['exercicios'] ?? ''));

    if ($action === 'create_library') {
        $pdo->beginTransaction();
        try {
            $idModelo = cronogramaSalvarTreinoModelo($pdo, $idUsuario, compact('titulo', 'codigo', 'foco', 'descricao', 'idmodalidade'));
            cronogramaSalvarExerciciosTreinoModelo($pdo, $idUsuario, $idModelo, $exercicios);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        $modelo = cronogramaBuscarTreinoModelo($pdo, $idUsuario, $idModelo);
        echo json_encode(['ok' => true, 'modelo' => [
            'idtreino_modelo' => $idModelo,
            'titulo' => (string) $modelo['titulo'],
            'codigo' => (string) ($modelo['codigo'] ?? ''),
            'foco' => (string) ($modelo['foco'] ?? ''),
            'descricao' => (string) ($modelo['descricao'] ?? ''),
            'idmodalidade' => (string) ($modelo['idmodalidade'] ?? ''),
            'exercicios_total' => count($modelo['exercicios'] ?? []),
        ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action !== 'create_schedule') throw new InvalidArgumentException(stridebr_t('planning.message.invalid_action'));

    $idCronograma = trim((string) ($_POST['idcronograma'] ?? ''));
    $dataTreino = trim((string) ($_POST['data_treino'] ?? ''));
    $date = cronogramaValidarDataIso($dataTreino);
    if (cronogramaBuscar($pdo, $idCronograma, $idUsuario) === []) throw new RuntimeException(stridebr_t('planning.message.schedule_missing'));

    $horaInicio = trim((string) ($_POST['hora_inicio'] ?? '18:00'));
    $horaFim = trim((string) ($_POST['hora_fim'] ?? '19:00'));
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $horaInicio) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $horaFim)) {
        throw new InvalidArgumentException(stridebr_t('planning.message.invalid_time'));
    }
    $terminaDiaSeguinte = !empty($_POST['termina_dia_seguinte']);
    $recorrencia = trim((string) ($_POST['recorrencia'] ?? 'once'));
    if (!in_array($recorrencia, ['once', 'weekly'], true)) throw new InvalidArgumentException(stridebr_t('schedule.validation.recurrence_invalid'));
    $vigenciaFim = $recorrencia === 'once' ? $dataTreino : (trim((string) ($_POST['vigencia_fim'] ?? '')) ?: null);
    if ($vigenciaFim !== null) cronogramaValidarDataIso($vigenciaFim);
    $sourceModel = trim((string) ($_POST['idtreino_modelo'] ?? ''));
    $saveLibrary = !empty($_POST['salvar_biblioteca']);

    $pdo->beginTransaction();
    try {
        if ($sourceModel !== '') {
            $idTreino = cronogramaAdicionarTreinoModeloAoCronograma(
                $pdo,
                $idUsuario,
                $sourceModel,
                $idCronograma,
                (int) $date->format('w'),
                $horaInicio,
                $horaFim,
                $terminaDiaSeguinte,
                $dataTreino,
                $vigenciaFim
            );
            $modelo = cronogramaBuscarTreinoModelo($pdo, $idUsuario, $sourceModel);
            if ($modelo === []) throw new RuntimeException(stridebr_t('library.workout_not_found'));
            $tituloFinal = $titulo !== '' ? $titulo : (string) $modelo['titulo'];
            cronogramaSalvarTreino($pdo, $idUsuario, [
                'idcronograma' => $idCronograma,
                'titulo' => $tituloFinal,
                'codigo' => $codigo !== '' ? $codigo : (string) ($modelo['codigo'] ?? ''),
                'foco' => $foco !== '' ? $foco : (string) ($modelo['foco'] ?? ''),
                'descricao' => $descricao !== '' ? $descricao : (string) ($modelo['descricao'] ?? ''),
                'idmodalidade' => $idmodalidade !== '' ? $idmodalidade : (string) ($modelo['idmodalidade'] ?? ''),
                'dia_semana' => (int) $date->format('w'),
                'hora_inicio' => $horaInicio,
                'hora_fim' => $horaFim,
                'termina_dia_seguinte' => $terminaDiaSeguinte ? '1' : '',
                'vigencia_inicio' => $dataTreino,
                'vigencia_fim' => $vigenciaFim ?? '',
            ], $idTreino);
        } else {
            $idTreino = cronogramaSalvarTreino($pdo, $idUsuario, [
                'idcronograma' => $idCronograma,
                'titulo' => $titulo,
                'codigo' => $codigo,
                'foco' => $foco,
                'descricao' => $descricao,
                'idmodalidade' => $idmodalidade,
                'dia_semana' => (int) $date->format('w'),
                'hora_inicio' => $horaInicio,
                'hora_fim' => $horaFim,
                'termina_dia_seguinte' => $terminaDiaSeguinte ? '1' : '',
                'vigencia_inicio' => $dataTreino,
                'vigencia_fim' => $vigenciaFim ?? '',
            ]);
            if ($exercicios !== []) cronogramaSalvarExercicios($pdo, $idTreino, $idUsuario, $exercicios, []);
            if ($saveLibrary) cronogramaSalvarTreinoAtualNaBiblioteca($pdo, $idUsuario, $idTreino);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    notificacaoCronogramaSincronizadoAlterado($pdo, $idUsuario, $idCronograma, 'Um treino foi adicionado.');
    productAnalyticsRegistrar($pdo, $idUsuario, 'schedule_created', ['source' => 'quick_create', 'kind' => 'workout']);
    echo json_encode(['ok' => true, 'idtreino' => $idTreino], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 400 : ($e instanceof RuntimeException ? 404 : 500));
    if (!$e instanceof InvalidArgumentException && !$e instanceof RuntimeException) error_log('StrideBR quick schedule create: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível salvar o treino.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
