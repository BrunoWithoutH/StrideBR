<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();

require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma_compartilhar.php';
require_once dirname(__DIR__, 2) . '/src/function/product_analytics.php';
require_once dirname(__DIR__, 2) . '/src/function/notificacoes.php';
require_once dirname(__DIR__, 2) . '/src/layout/sport_picker.php';
$friendsEnabled = stridebr_feature_enabled($pdo, 'friends.enabled', false);
$workoutSessionsEnabled = stridebr_feature_enabled($pdo, 'workout_sessions.enabled', false);
$errors = [];
$dias = stridebr_weekday_names();


function cronogramaNomeImportado(PDO $pdo, string $idUsuario, string $base): string
{
    $base = trim($base) !== '' ? trim($base) : stridebr_t('schedule.imported_name');
    $candidate = $base;
    $n = 2;
    while (true) {
        $stmt = $pdo->prepare('SELECT 1 FROM cronogramas WHERE idusuario = :usuario AND lower(nome) = lower(:nome) LIMIT 1');
        $stmt->execute([':usuario' => $idUsuario, ':nome' => $candidate]);
        if (!$stmt->fetchColumn()) return $candidate;
        $candidate = $base . ' (' . $n++ . ')';
    }
}

function cronogramaExportData(PDO $pdo, string $idUsuario, string $idCronograma): array
{
    $cronograma = cronogramaBuscar($pdo, $idCronograma, $idUsuario);
    if ($cronograma === []) throw new RuntimeException(stridebr_t('schedule.not_found'));
    $treinos = cronogramaListarTreinos($pdo, $idCronograma, $idUsuario);
    foreach ($treinos as &$treino) {
        $treino['exercicios'] = cronogramaListarTreinoExercicios($pdo, (string) $treino['idtreino'], $idUsuario);
    }
    unset($treino);
    return [
        'format' => 'stridebr-schedule',
        'version' => 1,
        'cronograma' => [
            'nome' => $cronograma['nome'],
            'descricao' => $cronograma['descricao'] ?? null,
        ],
        'treinos' => $treinos,
    ];
}

function cronogramaImportData(PDO $pdo, string $idUsuario, array $data): string
{
    if (($data['format'] ?? '') !== 'stridebr-schedule' || (int) ($data['version'] ?? 0) !== 1 || !is_array($data['cronograma'] ?? null) || !is_array($data['treinos'] ?? null)) {
        throw new InvalidArgumentException(stridebr_t('schedule.import_invalid_incompatible'));
    }

    if (count($data['treinos']) > 200) {
        throw new InvalidArgumentException(stridebr_t('schedule.import_too_many_workouts'));
    }

    $nome = cronogramaNomeImportado($pdo, $idUsuario, (string) ($data['cronograma']['nome'] ?? stridebr_t('schedule.imported_name')));
    $pdo->beginTransaction();

    try {
        $idCronograma = cronogramaCriar($pdo, $idUsuario, $nome, $data['cronograma']['descricao'] ?? null);
        $totalExercicios = 0;

        foreach ($data['treinos'] as $treino) {
            if (!is_array($treino)) continue;

            $payload = [
                'idcronograma' => $idCronograma,
                'titulo' => (string) ($treino['titulo'] ?? ''),
                'codigo' => (string) ($treino['codigo'] ?? ''),
                'foco' => (string) ($treino['foco'] ?? ''),
                'descricao' => $treino['descricao'] ?? null,
                'dia_semana' => $treino['dia_semana'] ?? 1,
                'hora_inicio' => substr((string) ($treino['hora_inicio'] ?? '18:00'), 0, 5),
                'hora_fim' => substr((string) ($treino['hora_fim'] ?? '19:00'), 0, 5),
                'vigencia_inicio' => (string) ($treino['vigencia_inicio'] ?? date('Y-m-d')),
                'vigencia_fim' => (string) ($treino['vigencia_fim'] ?? ''),
            ];

            if (stridebr_db_bool($treino['termina_dia_seguinte'] ?? false)) {
                $payload['termina_dia_seguinte'] = '1';
            }

            $idTreino = cronogramaSalvarTreino($pdo, $idUsuario, $payload);
            $rows = [];

            foreach (($treino['exercicios'] ?? []) as $exercicio) {
                if (!is_array($exercicio)) continue;

                $totalExercicios++;
                if ($totalExercicios > 2000) {
                    throw new InvalidArgumentException(stridebr_t('schedule.import_too_many_exercises'));
                }

                $rows[] = [
                    'nome' => (string) ($exercicio['nome_snapshot'] ?? $exercicio['nome'] ?? ''),
                    'series' => $exercicio['series'] ?? '',
                    'repeticoes' => $exercicio['repeticoes'] ?? '',
                    'carga' => $exercicio['carga'] ?? '',
                    'bloco' => $exercicio['bloco'] ?? '',
                    'cluster' => $exercicio['cluster'] ?? '',
                    'descanso' => $exercicio['descanso'] ?? '',
                    'observacoes' => $exercicio['observacoes'] ?? '',
                    'duracao' => $exercicio['duracao'] ?? '',
                    'distancia' => $exercicio['distancia'] ?? '',
                    'intensidade' => $exercicio['intensidade'] ?? '',
                    'rpe' => $exercicio['rpe'] ?? '',
                    'rir' => $exercicio['rir'] ?? '',
                    'tempo_execucao' => $exercicio['tempo_execucao'] ?? '',
                    'cadencia' => $exercicio['cadencia'] ?? '',
                ];
            }

            if ($rows !== []) {
                cronogramaSalvarExercicios($pdo, $idTreino, $idUsuario, $rows, []);
            }
        }

        $pdo->commit();
        return $idCronograma;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'create_schedule') {
            $idNovo = cronogramaCriar($pdo, $idUsuario, (string) ($_POST['nome'] ?? ''), $_POST['descricao'] ?? null);
            productAnalyticsRegistrar($pdo, $idUsuario, 'schedule_created', ['source' => 'schedule_page']);
            stridebr_flash('success', stridebr_t('schedule.created_success'));
            header('Location: /user/cronogramatreinos.php?id=' . urlencode($idNovo));
            exit;
        }
        if ($action === 'delete_schedule') {
            $id = (string) ($_POST['idcronograma'] ?? '');
            if (cronogramaBuscar($pdo, $id, $idUsuario) === []) {
                throw new RuntimeException(stridebr_t('schedule.not_found'));
            }
            notificacaoCronogramaSincronizadoAlterado($pdo, $idUsuario, $id, stridebr_t('schedule.owner_removed_notification'), '/user/amigos.php#cronogramas-sincronizados');
            if (!cronogramaExcluir($pdo, $id, $idUsuario)) {
                throw new RuntimeException(stridebr_t('schedule.delete_error'));
            }
            stridebr_flash('success', stridebr_t('schedule.deleted_success'));
            header('Location: /user/cronogramatreinos.php');
            exit;
        }
        if ($action === 'update_schedule_visibility') {
            $id = (string) ($_POST['idcronograma'] ?? '');
            $visibility = (string) ($_POST['visibilidade'] ?? 'privado');
            if (!in_array($visibility, ['privado', 'amigos', 'publico'], true)) throw new InvalidArgumentException(stridebr_t('schedule.invalid_visibility'));
            $stmt = $pdo->prepare('UPDATE cronogramas SET visibilidade = :visibilidade, data_atualizacao = NOW() WHERE idcronograma = :id AND idusuario = :usuario');
            $stmt->execute([':visibilidade' => $visibility, ':id' => $id, ':usuario' => $idUsuario]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException(stridebr_t('schedule.not_found'));
            notificacaoCronogramaSincronizadoAlterado($pdo, $idUsuario, $id, 'A privacidade do cronograma foi atualizada.');
            stridebr_flash('success', stridebr_t('schedule.privacy_updated'));
            header('Location: /user/cronogramatreinos.php?id=' . urlencode($id));
            exit;
        }
        if ($action === 'save_workout') {
            $idTreino = trim((string) ($_POST['idtreino'] ?? '')) ?: null;
            $scope = trim((string) ($_POST['edit_scope'] ?? 'all'));
            $dataOriginal = trim((string) ($_POST['data_original'] ?? ''));
            if ($idTreino !== null && $dataOriginal !== '' && $scope !== 'all') {
                cronogramaEditarTreinoEscopo($pdo, $idUsuario, $idTreino, $dataOriginal, $scope, $_POST);
            } else {
                cronogramaSalvarTreino($pdo, $idUsuario, $_POST, $idTreino);
            }
            $idCronogramaAlterado = trim((string) ($_POST['idcronograma'] ?? ''));
            notificacaoCronogramaSincronizadoAlterado($pdo, $idUsuario, $idCronogramaAlterado, $idTreino ? 'Um treino foi atualizado.' : 'Um treino foi adicionado.');
            stridebr_flash('success', stridebr_t($idTreino ? 'schedule.workout_updated' : 'schedule.workout_added'));
            $fallback = '/user/cronogramatreinos.php?id=' . urlencode($idCronogramaAlterado);
            header('Location: ' . stridebr_safe_redirect((string) ($_POST['return_to'] ?? ''), $fallback));
            exit;
        }
        if ($action === 'undo_delete_workout') {
            $undo = is_array($_SESSION['schedule_undo_workout'] ?? null) ? $_SESSION['schedule_undo_workout'] : [];
            $token = (string) ($_POST['undo_token'] ?? '');
            if ($undo === [] || (int) ($undo['expires'] ?? 0) < time() || !hash_equals((string) ($undo['token'] ?? ''), $token)) {
                unset($_SESSION['schedule_undo_workout']);
                throw new RuntimeException('O prazo para desfazer terminou.');
            }
            $idRestaurado = cronogramaRestaurarTreinoExcluido($pdo, $idUsuario, (array) ($undo['snapshot'] ?? []));
            $idCronograma = (string) (($undo['snapshot']['treino']['idcronograma'] ?? '') ?: ($_POST['idcronograma'] ?? ''));
            unset($_SESSION['schedule_undo_workout']);
            notificacaoCronogramaSincronizadoAlterado($pdo, $idUsuario, $idCronograma, 'Um treino foi restaurado.');
            stridebr_flash('success', stridebr_t('schedule.workout_restored'));
            $fallback = '/user/cronogramatreinos.php?id=' . urlencode($idCronograma);
            header('Location: ' . stridebr_safe_redirect((string) ($_POST['return_to'] ?? ''), $fallback));
            exit;
        }
        if ($action === 'delete_workout') {
            $idCronograma = (string) ($_POST['idcronograma'] ?? '');
            $idTreinoExcluir = (string) ($_POST['idtreino'] ?? '');
            $snapshot = cronogramaCapturarTreinoParaDesfazer($pdo, $idTreinoExcluir, $idUsuario);
            if ($snapshot === [] || !cronogramaExcluirTreino($pdo, $idTreinoExcluir, $idUsuario)) {
                throw new RuntimeException(stridebr_t('schedule.workout_not_found'));
            }
            $_SESSION['schedule_undo_workout'] = [
                'token' => bin2hex(random_bytes(16)),
                'expires' => time() + 300,
                'snapshot' => $snapshot,
            ];
            notificacaoCronogramaSincronizadoAlterado($pdo, $idUsuario, $idCronograma, 'Um treino foi removido.');
            stridebr_flash('success', stridebr_t('schedule.workout_removed_from_schedule'));
            $fallback = '/user/cronogramatreinos.php?id=' . urlencode($idCronograma);
            header('Location: ' . stridebr_safe_redirect((string) ($_POST['return_to'] ?? ''), $fallback));
            exit;
        }
        if ($action === 'duplicate_workout') {
            $idCronograma = (string) ($_POST['idcronograma'] ?? '');
            $newWorkout = cronogramaDuplicarTreino($pdo, $idUsuario, (string) ($_POST['idtreino'] ?? ''));
            $mode = (string) ($_POST['duplicate_mode'] ?? 'stay');
            notificacaoCronogramaSincronizadoAlterado($pdo, $idUsuario, $idCronograma, 'Um treino foi duplicado.');
            stridebr_flash('success', stridebr_t($mode === 'edit' ? 'schedule.copy_created_edit' : 'schedule.copy_created'));
            $fallback = '/user/cronogramatreinos.php?id=' . urlencode($idCronograma);
            $location = stridebr_safe_redirect((string) ($_POST['return_to'] ?? ''), $fallback);
            if ($mode === 'edit') $location .= (str_contains($location, '?') ? '&' : '?') . 'treino=' . urlencode($newWorkout);
            header('Location: ' . $location);
            exit;
        }
        if ($action === 'share_snapshot') {
            if (!$friendsEnabled) throw new RuntimeException(stridebr_t('schedule.friends_sharing_disabled'));
            $idCronograma = trim((string) ($_POST['idcronograma'] ?? ''));
            $destino = trim((string) ($_POST['idusuario_destino'] ?? ''));
            compartilhamentoEnviarSnapshot($pdo, $idUsuario, $idCronograma, $destino);
            stridebr_flash('success', stridebr_t('schedule.copy_sent'));
            header('Location: /user/cronogramatreinos.php?id=' . urlencode($idCronograma));
            exit;
        }
        if ($action === 'create_library_workout') {
            $idModelo = cronogramaSalvarTreinoModelo($pdo, $idUsuario, $_POST);
            stridebr_flash('success', stridebr_t('schedule.saved_my_workouts_success'));
            $targetSchedule = trim((string) ($_POST['idcronograma'] ?? ''));
            header('Location: /user/cronogramatreinos.php' . ($targetSchedule !== '' ? '?id=' . urlencode($targetSchedule) : '') . '');
            exit;
        }
        if ($action === 'save_workout_to_library') {
            $idModelo = cronogramaSalvarTreinoAtualNaBiblioteca($pdo, $idUsuario, (string) ($_POST['idtreino'] ?? ''));
            stridebr_flash('success', stridebr_t('schedule.saved_library_success'));
            $fallback = '/user/cronogramatreinos.php?id=' . urlencode((string) ($_POST['idcronograma'] ?? ''));
            header('Location: ' . stridebr_safe_redirect((string) ($_POST['return_to'] ?? ''), $fallback));
            exit;
        }
        if ($action === 'update_library_from_workout') {
            cronogramaAtualizarTreinoModeloDoTreino($pdo, $idUsuario, (string) ($_POST['idtreino'] ?? ''));
            stridebr_flash('success', stridebr_t('schedule.my_workouts_updated'));
            $fallback = '/user/cronogramatreinos.php?id=' . urlencode((string) ($_POST['idcronograma'] ?? ''));
            header('Location: ' . stridebr_safe_redirect((string) ($_POST['return_to'] ?? ''), $fallback));
            exit;
        }
        if ($action === 'delete_library_workout') {
            $idModelo = trim((string) ($_POST['idtreino_modelo'] ?? ''));
            $stmt = $pdo->prepare('UPDATE treinos_modelo SET ativo = FALSE, data_atualizacao = NOW() WHERE idtreino_modelo = :id AND idusuario = :usuario');
            $stmt->execute([':id' => $idModelo, ':usuario' => $idUsuario]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException(stridebr_t('schedule.saved_workout_not_found'));
            stridebr_flash('success', stridebr_t('schedule.saved_workout_removed'));
            header('Location: /user/cronogramatreinos.php?id=' . urlencode((string) ($_POST['idcronograma'] ?? '')) . '');
            exit;
        }
        if ($action === 'add_library_to_schedule') {
            $idCronograma = trim((string) ($_POST['idcronograma'] ?? ''));
            $idModelo = trim((string) ($_POST['idtreino_modelo'] ?? ''));
            $dia = filter_var($_POST['dia_semana'] ?? null, FILTER_VALIDATE_INT);
            if ($dia === false || $dia < 0 || $dia > 6) throw new InvalidArgumentException(stridebr_t('schedule.invalid_weekday'));
            $idTreino = cronogramaAdicionarTreinoModeloAoCronograma(
                $pdo,
                $idUsuario,
                $idModelo,
                $idCronograma,
                (int) $dia,
                trim((string) ($_POST['hora_inicio'] ?? '18:00')),
                trim((string) ($_POST['hora_fim'] ?? '19:00')),
                !empty($_POST['termina_dia_seguinte']),
                trim((string) ($_POST['vigencia_inicio'] ?? date('Y-m-d'))),
                trim((string) ($_POST['vigencia_fim'] ?? '')) ?: null
            );
            notificacaoCronogramaSincronizadoAlterado($pdo, $idUsuario, $idCronograma, 'Um treino salvo foi adicionado ao cronograma.');
            stridebr_flash('success', stridebr_t('schedule.workout_added_schedule'));
            header('Location: /user/cronogramatreinos.php?id=' . urlencode($idCronograma) . '&treino=' . urlencode($idTreino));
            exit;
        }
        if ($action === 'import_schedule') {
            if (!isset($_FILES['schedule_file']) || !is_array($_FILES['schedule_file'])) {
                throw new InvalidArgumentException(stridebr_t('schedule.choose_stridebr_json'));
            }
            if ((int) ($_FILES['schedule_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($_FILES['schedule_file']['tmp_name'] ?? ''))) {
                throw new InvalidArgumentException(stridebr_t('schedule.file_receive_error'));
            }
            if ((int) ($_FILES['schedule_file']['size'] ?? 0) <= 0 || (int) $_FILES['schedule_file']['size'] > 2 * 1024 * 1024) {
                throw new InvalidArgumentException(stridebr_t('schedule.file_too_large'));
            }
            $raw = file_get_contents((string) $_FILES['schedule_file']['tmp_name']);
            if ($raw === false || trim($raw) === '') {
                throw new InvalidArgumentException(stridebr_t('schedule.file_empty_error'));
            }
            try {
                $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new InvalidArgumentException(stridebr_t('schedule.invalid_json'));
            }
            if (!is_array($data)) {
                throw new InvalidArgumentException(stridebr_t('schedule.invalid_schedule_file'));
            }
            $idImportado = cronogramaImportData($pdo, $idUsuario, $data);
            stridebr_flash('success', stridebr_t('schedule.import_private_success'));
            header('Location: /user/cronogramatreinos.php?id=' . urlencode($idImportado));
            exit;
        }
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : stridebr_t('schedule.operation_error');
        if (!$e instanceof InvalidArgumentException && !$e instanceof RuntimeException) {
            error_log($e->getMessage());
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['export'], $_GET['id'])) {
    $exportId = (string) $_GET['id'];
    try {
        $data = cronogramaExportData($pdo, $idUsuario, $exportId);
        $safe = preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($data['cronograma']['nome'] ?? 'cronograma')) ?: 'cronograma';
        if ($_GET['export'] === 'json') {
            header('Content-Type: application/json; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $safe . '.stridebr.json"');
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($_GET['export'] === 'csv') {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $safe . '.csv"');
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [stridebr_t('schedule.csv.day'), stridebr_t('schedule.csv.start'), stridebr_t('schedule.csv.end'), stridebr_t('schedule.csv.valid_from'), stridebr_t('schedule.csv.valid_until'), stridebr_t('schedule.csv.code'), stridebr_t('schedule.csv.focus'), stridebr_t('schedule.csv.workout'), stridebr_t('schedule.csv.exercise'), stridebr_t('schedule.csv.sets'), stridebr_t('schedule.csv.repetitions'), stridebr_t('schedule.csv.load'), stridebr_t('schedule.csv.block'), stridebr_t('schedule.csv.cluster'), stridebr_t('schedule.csv.rest'), stridebr_t('schedule.csv.duration'), stridebr_t('schedule.csv.distance'), stridebr_t('schedule.csv.intensity'), stridebr_t('schedule.csv.rpe'), stridebr_t('schedule.csv.rir'), stridebr_t('schedule.csv.tempo'), stridebr_t('schedule.csv.cadence'), stridebr_t('schedule.csv.notes')], ';');
            foreach ($data['treinos'] as $treino) {
                $exercicios = $treino['exercicios'] ?: [[]];
                foreach ($exercicios as $exercicio) {
                    fputcsv($out, [
                        $dias[(int) $treino['dia_semana']] ?? $treino['dia_semana'],
                        substr((string) $treino['hora_inicio'], 0, 5), substr((string) $treino['hora_fim'], 0, 5),
                        $treino['vigencia_inicio'] ?? '', $treino['vigencia_fim'] ?? '',
                        $treino['codigo'] ?? '', $treino['foco'] ?? '', $treino['titulo'],
                        $exercicio['nome_snapshot'] ?? '', $exercicio['series'] ?? '', $exercicio['repeticoes'] ?? '', $exercicio['carga'] ?? '',
                        $exercicio['bloco'] ?? '', $exercicio['cluster'] ?? '', $exercicio['descanso'] ?? '',
                        $exercicio['duracao'] ?? '', $exercicio['distancia'] ?? '', $exercicio['intensidade'] ?? '', $exercicio['rpe'] ?? '', $exercicio['rir'] ?? '', $exercicio['tempo_execucao'] ?? '', $exercicio['cadencia'] ?? '', $exercicio['observacoes'] ?? '',
                    ], ';');
                }
            }
            fclose($out);
            exit;
        }
    } catch (Throwable $e) {
        stridebr_error_document(404);
    }
}

$cronogramas = cronogramaListar($pdo, $idUsuario);
$idSelecionado = (string) ($_GET['id'] ?? '');
if ($idSelecionado === '' && $cronogramas !== []) {
    $idSelecionado = $cronogramas[0]['idcronograma'];
}
$cronograma = $idSelecionado !== '' ? cronogramaBuscar($pdo, $idSelecionado, $idUsuario) : [];
if ($cronograma === [] && $cronogramas !== []) {
    $cronograma = $cronogramas[0];
    $idSelecionado = $cronograma['idcronograma'];
}
$treinos = $cronograma !== [] ? cronogramaListarTreinos($pdo, $idSelecionado, $idUsuario) : [];
$exerciciosPorTreino = $cronograma !== [] ? cronogramaListarExerciciosPorTreinos($pdo, $idSelecionado, $idUsuario) : [];
$treinosModelo = cronogramaBibliotecaDisponivel($pdo) ? cronogramaListarTreinosModelo($pdo, $idUsuario) : [];
$modalidadesTreino = cronogramaListarModalidadesTreino($pdo, $idUsuario);
$friends = [];
if ($friendsEnabled) {
    $friendsStmt = $pdo->prepare("SELECT u.idusuario, COALESCE(NULLIF(u.nome_exibicao, ''), u.nomeusuario) AS nome_exibicao, u.username FROM amizades a JOIN usuarios u ON u.idusuario = CASE WHEN a.idusuario_solicitante = :me_case THEN a.idusuario_destino ELSE a.idusuario_solicitante END WHERE a.status = 'aceita' AND (a.idusuario_solicitante = :me_left OR a.idusuario_destino = :me_right) ORDER BY nome_exibicao");
    $friendsStmt->execute([':me_case' => $idUsuario, ':me_left' => $idUsuario, ':me_right' => $idUsuario]);
    $friends = $friendsStmt->fetchAll();
}
$treinoEdicao = [];
$idTreinoEdicao = (string) ($_GET['treino'] ?? '');
if ($idTreinoEdicao !== '') {
    $candidate = cronogramaBuscarTreino($pdo, $idTreinoEdicao, $idUsuario);
    if ($candidate !== [] && $candidate['idcronograma'] === $idSelecionado) {
        $treinoEdicao = $candidate;
    }
}


$monthlyOverlayEnabled = stridebr_feature_enabled($pdo, 'monthly_calendar.enabled', false);
$monthRaw = trim((string) ($_GET['month'] ?? ''));
$monthStart = DateTimeImmutable::createFromFormat('!Y-m', $monthRaw);
if (!$monthStart || $monthStart->format('Y-m') !== $monthRaw) {
    $monthStart = new DateTimeImmutable('first day of this month');
}
$monthEnd = $monthStart->modify('last day of this month');
$monthKey = $monthStart->format('Y-m');
$prevMonth = $monthStart->modify('-1 month')->format('Y-m');
$nextMonth = $monthStart->modify('+1 month')->format('Y-m');
$monthTitle = stridebr_format_month_year($monthStart);
$monthDayNames = stridebr_weekday_short_names();
$monthLeading = (int) $monthStart->format('w');
$monthTotalDays = (int) $monthEnd->format('j');
$monthCellCount = (int) (ceil(($monthLeading + $monthTotalDays) / 7) * 7);
$today = (new DateTimeImmutable('today'))->format('Y-m-d');

$weekToday = new DateTimeImmutable('today');
$weekStart = $weekToday->modify('-' . $weekToday->format('w') . ' days');
$weekEnd = $weekStart->modify('+6 days');
$weekDates = [];
for ($dayIndex = 0; $dayIndex < 7; $dayIndex++) {
    $weekDates[$dayIndex] = $weekStart->modify('+' . $dayIndex . ' days');
}
$weekOccurrences = $idSelecionado !== '' ? cronogramaListarOcorrenciasConciliadas($pdo, $idUsuario, $weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d'), $idSelecionado) : [];
$weekCompletedWorkoutIds = [];
foreach ($weekOccurrences as $weekOccurrence) {
    $doneWorkoutId = (string) ($weekOccurrence['idtreino'] ?? '');
    if ($doneWorkoutId !== '' && !empty($weekOccurrence['concluido'])) {
        $weekCompletedWorkoutIds[$doneWorkoutId] = true;
    }
}
$weekLastCompletedWorkoutId = null;
if ($idSelecionado !== '') {
    $lastDoneStmt = $pdo->prepare("SELECT idtreino_cronograma FROM registros_atividade WHERE idusuario = :usuario AND excluido_em IS NULL AND idcronograma = :cronograma AND idtreino_cronograma IS NOT NULL AND status = 'concluido' ORDER BY data_inicio DESC LIMIT 1");
    $lastDoneStmt->execute([':usuario' => $idUsuario, ':cronograma' => $idSelecionado]);
    $lastDoneId = $lastDoneStmt->fetchColumn();
    $weekLastCompletedWorkoutId = $lastDoneId !== false ? (string) $lastDoneId : null;
}

$weekOccurrenceByWorkout = [];
$weekPlannedWorkoutIds = [];
foreach ($weekOccurrences as &$weekOccurrence) {
    $weekWorkoutId = (string) ($weekOccurrence['idtreino'] ?? '');
    $weekOccurrence['concluido'] = isset($weekCompletedWorkoutIds[$weekWorkoutId]);
    if ($weekWorkoutId !== '' && !isset($weekOccurrenceByWorkout[$weekWorkoutId])) {
        $weekOccurrenceByWorkout[$weekWorkoutId] = $weekOccurrence;
        $weekPlannedWorkoutIds[$weekWorkoutId] = true;
    }
}
unset($weekOccurrence);

$weekSequence = [];
foreach ($treinos as $treino) {
    $idTreino = (string) ($treino['idtreino'] ?? '');
    if ($idTreino !== '' && isset($weekPlannedWorkoutIds[$idTreino])) {
        $weekSequence[] = $treino;
    }
}
foreach ($weekOccurrenceByWorkout as $idTreino => $occurrence) {
    $alreadyInSequence = false;
    foreach ($weekSequence as $sequenceWorkout) {
        if ((string) ($sequenceWorkout['idtreino'] ?? '') === $idTreino) {
            $alreadyInSequence = true;
            break;
        }
    }
    if (!$alreadyInSequence) $weekSequence[] = $occurrence;
}

$weekPlannedCount = count($weekPlannedWorkoutIds);
$weekCompletedCount = 0;
$weekPendingWorkoutIds = [];
foreach (array_keys($weekPlannedWorkoutIds) as $weekWorkoutId) {
    if (isset($weekCompletedWorkoutIds[$weekWorkoutId])) {
        $weekCompletedCount++;
    } else {
        $weekPendingWorkoutIds[$weekWorkoutId] = true;
    }
}
$weekProgress = $weekPlannedCount > 0 ? min(100, (int) round(($weekCompletedCount / $weekPlannedCount) * 100)) : 0;
$weekDone = $weekPlannedCount > 0 && $weekCompletedCount >= $weekPlannedCount;

$weekNextWorkoutId = null;
$weekPreferredWorkoutId = $idSelecionado !== '' ? cronogramaPreferenciaSemanalBuscar($pdo, $idUsuario, $idSelecionado, $weekStart->format('Y-m-d')) : null;
if ($weekPreferredWorkoutId !== null && isset($weekPendingWorkoutIds[$weekPreferredWorkoutId])) {
    $weekNextWorkoutId = $weekPreferredWorkoutId;
}
$sequenceCount = count($weekSequence);
if ($weekNextWorkoutId === null && $sequenceCount > 0 && $weekPendingWorkoutIds !== []) {
    $lastIndex = -1;
    if ($weekLastCompletedWorkoutId !== null) {
        foreach ($weekSequence as $index => $sequenceWorkout) {
            if ((string) ($sequenceWorkout['idtreino'] ?? '') === $weekLastCompletedWorkoutId) {
                $lastIndex = $index;
                break;
            }
        }
    }
    for ($offset = 1; $offset <= $sequenceCount; $offset++) {
        $candidateIndex = $lastIndex >= 0 ? ($lastIndex + $offset) % $sequenceCount : $offset - 1;
        $candidateId = (string) ($weekSequence[$candidateIndex]['idtreino'] ?? '');
        if ($candidateId !== '' && isset($weekPendingWorkoutIds[$candidateId])) {
            $weekNextWorkoutId = $candidateId;
            break;
        }
    }
}
$weekNextOccurrence = $weekNextWorkoutId !== null ? ($weekOccurrenceByWorkout[$weekNextWorkoutId] ?? null) : null;
$weekPendingChoices = [];
foreach ($weekSequence as $sequenceWorkout) {
    $candidateId = (string) ($sequenceWorkout['idtreino'] ?? '');
    if ($candidateId !== '' && isset($weekPendingWorkoutIds[$candidateId]) && $candidateId !== $weekNextWorkoutId && isset($weekOccurrenceByWorkout[$candidateId])) {
        $weekPendingChoices[] = $weekOccurrenceByWorkout[$candidateId];
    }
}

$weekPlanGhosts = [];
foreach ($weekOccurrences as $occurrence) {
    if (!empty($occurrence['concluido']) && !empty($occurrence['data_planejada']) && (string) $occurrence['data_planejada'] !== (string) $occurrence['data_treino']) {
        $plannedDate = new DateTimeImmutable((string) $occurrence['data_planejada']);
        if ($plannedDate >= $weekStart && $plannedDate <= $weekEnd) {
            $weekPlanGhosts[(int) $plannedDate->format('w')][] = $occurrence;
        }
    }
}

$segments = [];
foreach ($weekOccurrences as $occurrence) {
    $start = ((int) substr((string) $occurrence['hora_inicio'], 0, 2)) * 60 + (int) substr((string) $occurrence['hora_inicio'], 3, 2);
    $end = ((int) substr((string) $occurrence['hora_fim'], 0, 2)) * 60 + (int) substr((string) $occurrence['hora_fim'], 3, 2);
    $day = (int) (new DateTimeImmutable((string) $occurrence['data_treino']))->format('w');
    if (stridebr_db_bool($occurrence['termina_dia_seguinte'] ?? false)) {
        $segments[$day][] = ['treino' => $occurrence, 'inicio' => $start, 'fim' => 1440, 'continua' => true, 'continuidade' => false];
        if ($end > 0 && (string) $occurrence['data_treino'] < $weekEnd->format('Y-m-d')) {
            $next = ($day + 1) % 7;
            $segments[$next][] = ['treino' => $occurrence, 'inicio' => 0, 'fim' => $end, 'continua' => false, 'continuidade' => true];
        }
    } else {
        $segments[$day][] = ['treino' => $occurrence, 'inicio' => $start, 'fim' => $end, 'continua' => false, 'continuidade' => false];
    }
}

$flashes = stridebr_take_flashes();
$allowedInitialViews = ['week', 'month', 'agenda'];
$requestedInitialView = trim((string) ($_GET['view'] ?? ''));
$cookieInitialView = trim((string) ($_COOKIE['stridebr_schedule_view'] ?? ''));
$initialView = in_array($requestedInitialView, $allowedInitialViews, true)
    ? $requestedInitialView
    : (in_array($cookieInitialView, $allowedInitialViews, true) ? $cookieInitialView : 'week');
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/cronogramas.css')); ?>">
    <title><?php echo stridebr_e(stridebr_t('schedule.page_html_title')); ?></title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body class="schedule-body">
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content cronograma-page">
        <div class="schedule-shell">
            <div class="schedule-heading">
                <div>
                    <h1><?php echo stridebr_e(stridebr_t('schedule.page_title')); ?></h1>
                    <p><?php echo stridebr_e(stridebr_t('schedule.page_description')); ?></p>
                </div>
                <div class="schedule-heading-actions">
                    <a class="secondary-button" href="/user/biblioteca.php?tab=treinos"><?php echo stridebr_e(stridebr_t('schedule.library')); ?></a>
                    <button type="button" class="primary-button" data-open-schedule-create><?php echo stridebr_e(stridebr_t('schedule.new_schedule')); ?></button>
                </div>
            </div>

            <nav class="planning-subnav monthly-planning-subnav" aria-label="<?php echo stridebr_e(stridebr_t('schedule.planning_navigation')); ?>">
                <a class="is-active" href="/user/cronogramatreinos.php"><?php echo stridebr_e(stridebr_t('schedule.page_title')); ?></a>
                <?php if ($monthlyOverlayEnabled): ?><a href="/user/agenda-mensal.php<?php echo $idSelecionado !== '' ? '?cronograma=' . rawurlencode($idSelecionado) . '&month=' . rawurlencode($monthKey) : ''; ?>"><?php echo stridebr_e(stridebr_t('schedule.monthly_agenda')); ?></a><?php endif; ?>
                <?php if (stridebr_feature_enabled($pdo, 'trainer.enabled', false)): ?><a href="/user/treinador.php"><?php echo stridebr_e(stridebr_t('schedule.trainer_athletes')); ?></a><?php endif; ?>
            </nav>

            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div>
            <?php endforeach; ?>
            <?php $undoWorkout = is_array($_SESSION['schedule_undo_workout'] ?? null) && (int) ($_SESSION['schedule_undo_workout']['expires'] ?? 0) >= time() ? $_SESSION['schedule_undo_workout'] : null; ?>
            <?php if ($undoWorkout !== null): ?>
                <div class="schedule-undo-bar" role="status">
                    <span><?php echo stridebr_e(stridebr_t('schedule.workout_removed')); ?></span>
                    <form method="POST">
                        <?php echo stridebr_csrf_field(); ?>
                        <input type="hidden" name="action" value="undo_delete_workout">
                        <input type="hidden" name="undo_token" value="<?php echo stridebr_e((string) ($undoWorkout['token'] ?? '')); ?>">
                        <input type="hidden" name="idcronograma" value="<?php echo stridebr_e((string) $idSelecionado); ?>">
                        <input type="hidden" name="return_to" value="<?php echo stridebr_e((string) ($_SERVER['REQUEST_URI'] ?? '')); ?>">
                        <button type="submit"><?php echo stridebr_e(stridebr_t('common.undo')); ?></button>
                    </form>
                </div>
            <?php endif; ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger"><?php echo stridebr_e($error); ?></div>
            <?php endforeach; ?>

            <section class="schedule-create-panel" data-schedule-create hidden role="dialog" aria-modal="true" aria-labelledby="schedule-create-title">
                <button type="button" class="schedule-create-backdrop" data-close-schedule-create aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>"></button>
                <div class="schedule-create-dialog">
                <div class="schedule-create-header">
                    <div>
                        <span class="eyebrow"><?php echo stridebr_e(stridebr_t('schedule.new_schedule')); ?></span>
                        <h2 id="schedule-create-title" data-schedule-create-title><?php echo stridebr_e(stridebr_t('schedule.how_start')); ?></h2>
                    </div>
                    <button type="button" class="icon-button" data-close-schedule-create aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button>
                </div>

                <div class="schedule-create-options" data-schedule-create-options>
                    <button type="button" class="schedule-create-option" data-schedule-create-mode="blank">
                        <span class="schedule-create-option-icon" aria-hidden="true">+</span>
                        <strong><?php echo stridebr_e(stridebr_t('schedule.create_empty')); ?></strong>
                        <small><?php echo stridebr_e(stridebr_t('schedule.create_empty_help')); ?></small>
                    </button>
                    <button type="button" class="schedule-create-option" data-schedule-create-mode="import">
                        <span class="schedule-create-option-icon" aria-hidden="true">↑</span>
                        <strong><?php echo stridebr_e(stridebr_t('schedule.import_schedule')); ?></strong>
                        <small><?php echo stridebr_t('schedule.import_help_html'); ?></small>
                    </button>
                </div>

                <div class="schedule-create-form" data-schedule-create-form="blank" hidden>
                    <form method="POST" class="compact-form" data-draft-key="schedule-create">
                        <?php echo stridebr_csrf_field(); ?>
                        <input type="hidden" name="action" value="create_schedule">
                        <label><?php echo stridebr_e(stridebr_t('common.name')); ?>
                            <input type="text" name="nome" maxlength="120" placeholder="<?php echo stridebr_e(stridebr_t('schedule.name_placeholder')); ?>" required>
                        </label>
                        <label><?php echo stridebr_e(stridebr_t('common.description')); ?>
                            <input type="text" name="descricao" maxlength="300" placeholder="<?php echo stridebr_e(stridebr_t('schedule.optional_placeholder')); ?>">
                        </label>
                        <div class="form-actions">
                            <button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('schedule.create_schedule')); ?></button>
                            <button type="button" class="secondary-button" data-schedule-create-back><?php echo stridebr_e(stridebr_t('common.back')); ?></button>
                        </div>
                    </form>
                </div>

                <div class="schedule-create-form" data-schedule-create-form="import" hidden>
                    <form method="POST" enctype="multipart/form-data" class="schedule-import-create-form">
                        <?php echo stridebr_csrf_field(); ?>
                        <input type="hidden" name="action" value="import_schedule">
                        <label class="schedule-file-picker schedule-file-drop">
                            <input type="file" name="schedule_file" accept="application/json,.json,.stridebr.json" required data-schedule-import-file>
                            <span class="schedule-file-icon" aria-hidden="true">⇧</span>
                            <span class="schedule-file-copy"><strong><?php echo stridebr_e(stridebr_t('schedule.choose_file')); ?></strong><span data-schedule-file-name><?php echo stridebr_e(stridebr_t('schedule.no_file')); ?></span><small><?php echo stridebr_e(stridebr_t('schedule.file_help')); ?></small></span>
                        </label>
                        <div class="schedule-import-preview" data-schedule-import-preview hidden>
                            <div>
                                <span><?php echo stridebr_e(stridebr_t('common.name')); ?></span>
                                <strong data-import-preview-name>—</strong>
                            </div>
                            <div>
                                <span><?php echo stridebr_e(stridebr_t('common.workouts')); ?></span>
                                <strong data-import-preview-workouts>0</strong>
                            </div>
                            <div>
                                <span><?php echo stridebr_e(stridebr_t('common.exercises')); ?></span>
                                <strong data-import-preview-exercises>0</strong>
                            </div>
                        </div>
                        <p class="schedule-import-error" data-schedule-import-error hidden></p>
                        <div class="form-actions">
                            <button type="submit" class="primary-button" data-schedule-import-submit disabled><?php echo stridebr_e(stridebr_t('schedule.import_create')); ?></button>
                            <button type="button" class="secondary-button" data-schedule-create-back><?php echo stridebr_e(stridebr_t('common.back')); ?></button>
                        </div>
                    </form>
                </div>
                </div>
            </section>

            <?php if ($cronogramas === []): ?>
                <section class="empty-state">
                    <h2><?php echo stridebr_e(stridebr_t('schedule.first_schedule')); ?></h2>
                    <p><?php echo stridebr_e(stridebr_t('schedule.first_schedule_help')); ?></p>
                    <div class="form-actions">
                        <button type="button" class="primary-button" data-open-schedule-create><?php echo stridebr_e(stridebr_t('schedule.create_schedule')); ?></button>
                        <button type="button" class="secondary-button" data-open-schedule-import><?php echo stridebr_e(stridebr_t('schedule.import_file')); ?></button>
                    </div>
                </section>
            <?php else: ?>
                <section class="schedule-toolbar" data-schedule-toolbar>
                    <label class="schedule-select-label"><span><?php echo stridebr_e(stridebr_t('nav.schedule')); ?></span>
                        <select data-schedule-selector>
                            <?php foreach ($cronogramas as $item): ?>
                                <option value="<?php echo stridebr_e($item['idcronograma']); ?>"<?php echo $item['idcronograma'] === $idSelecionado ? ' selected' : ''; ?>><?php echo stridebr_e($item['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="view-switch" role="group" aria-label="<?php echo stridebr_e(stridebr_t('schedule.view')); ?>">
                        <button type="button" class="view-button<?php echo $initialView === 'week' ? ' is-active' : ''; ?>" data-view="week"><?php echo stridebr_e(stridebr_t('common.week')); ?></button>
                        <button type="button" class="view-button<?php echo $initialView === 'month' ? ' is-active' : ''; ?>" data-view="month"><?php echo stridebr_e(stridebr_t('common.month')); ?></button>
                        <button type="button" class="view-button<?php echo $initialView === 'agenda' ? ' is-active' : ''; ?>" data-view="agenda"><?php echo stridebr_e(stridebr_t('common.list')); ?></button>
                    </div>
                    <button type="button" class="primary-button schedule-new-workout" data-new-workout><?php echo stridebr_e(stridebr_t('schedule.add_workout')); ?></button>
                    <div class="schedule-toolbar-spacer"></div>
                    <div class="zoom-controls" data-zoom-controls aria-label="<?php echo stridebr_e(stridebr_t('schedule.zoom_week')); ?>">
                        <button type="button" class="view-button" data-zoom-out aria-label="<?php echo stridebr_e(stridebr_t('schedule.zoom_out')); ?>">−</button>
                        <span data-zoom-label>100%</span>
                        <button type="button" class="view-button" data-zoom-in aria-label="<?php echo stridebr_e(stridebr_t('schedule.zoom_in')); ?>">+</button>
                        <button type="button" class="view-button zoom-fit-button" data-zoom-fit title="<?php echo stridebr_e(stridebr_t('schedule.fit_visible')); ?>"><?php echo stridebr_e(stridebr_t('schedule.fit')); ?></button>
                    </div>
                    <details class="schedule-actions-menu">
                        <summary class="secondary-button icon-only-button" aria-label="<?php echo stridebr_e(stridebr_t('schedule.more_actions')); ?>" title="<?php echo stridebr_e(stridebr_t('schedule.more_actions')); ?>">•••</summary>
                        <button type="button" class="schedule-actions-backdrop" data-close-schedule-actions aria-label="<?php echo stridebr_e(stridebr_t('schedule.close_actions')); ?>"></button>
                        <div class="schedule-actions-content" role="dialog" aria-modal="true" aria-labelledby="schedule-actions-title">
                            <div class="schedule-actions-header">
                                <div>
                                    <span class="eyebrow"><?php echo stridebr_e(stridebr_t('nav.schedule')); ?></span>
                                    <strong id="schedule-actions-title"><?php echo stridebr_e(stridebr_t('schedule.more_actions')); ?></strong>
                                </div>
                                <button type="button" class="icon-button" data-close-schedule-actions aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button>
                            </div>
                            <strong><?php echo stridebr_e(stridebr_t('schedule.share_files')); ?></strong>
                            <a href="?id=<?php echo urlencode($idSelecionado); ?>&export=json"><?php echo stridebr_e(stridebr_t('schedule.export_stridebr')); ?></a>
                            <a href="?id=<?php echo urlencode($idSelecionado); ?>&export=csv"><?php echo stridebr_e(stridebr_t('schedule.export_csv')); ?></a>
                            <button type="button" data-print-schedule><?php echo stridebr_e(stridebr_t('schedule.print_pdf')); ?></button>
                            <?php if ($friendsEnabled): ?><button type="button" data-open-share><?php echo stridebr_e(stridebr_t('schedule.share_friend')); ?></button><?php endif; ?>
                            <button type="button" data-open-schedule-import><?php echo stridebr_e(stridebr_t('schedule.import_schedule')); ?></button>
                            <hr>
                            <strong><?php echo stridebr_e(stridebr_t('schedule.view')); ?></strong>
                            <button type="button" data-toggle-original-plan aria-pressed="false"><?php echo stridebr_e(stridebr_t('schedule.show_original')); ?></button>
                            <hr>
                            <strong><?php echo stridebr_e(stridebr_t('schedule.privacy')); ?></strong>
                            <form method="POST" class="schedule-visibility-form">
                                <?php echo stridebr_csrf_field(); ?>
                                <input type="hidden" name="action" value="update_schedule_visibility">
                                <input type="hidden" name="idcronograma" value="<?php echo stridebr_e($idSelecionado); ?>">
                                <select name="visibilidade" data-auto-submit aria-label="<?php echo stridebr_e(stridebr_t('schedule.visibility')); ?>">
                                    <option value="privado"<?php echo ($cronograma['visibilidade'] ?? 'privado') === 'privado' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('common.private')); ?></option>
                                    <option value="amigos"<?php echo ($cronograma['visibilidade'] ?? '') === 'amigos' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('common.friends')); ?></option>
                                    <option value="publico"<?php echo ($cronograma['visibilidade'] ?? '') === 'publico' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('common.public')); ?></option>
                                </select>
                            </form>
                            <hr>
                            <form method="POST" data-confirm="<?php echo stridebr_e(stridebr_t('schedule.confirm_delete_full')); ?>">
                                <?php echo stridebr_csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_schedule">
                                <input type="hidden" name="idcronograma" value="<?php echo stridebr_e($idSelecionado); ?>">
                                <button type="submit" class="menu-danger"><?php echo stridebr_e(stridebr_t('schedule.delete_schedule')); ?></button>
                            </form>
                            <button type="button" class="schedule-actions-mobile-close" data-close-schedule-actions><?php echo stridebr_e(stridebr_t('common.close_menu')); ?></button>
                        </div>
                    </details>
                </section>

                <?php if ($friendsEnabled): ?>
                <section class="schedule-share-panel" data-share-panel hidden>
                    <div><strong><?php echo stridebr_e(stridebr_t('schedule.share_copy')); ?></strong><p><?php echo stridebr_e(stridebr_t('schedule.share_copy_help')); ?></p></div>
                    <?php if ($friends === []): ?>
                        <div class="share-empty"><?php echo stridebr_e(stridebr_t('schedule.add_friend_first')); ?> <a href="/user/amigos.php"><?php echo stridebr_e(stridebr_t('schedule.go_friends')); ?></a></div>
                    <?php else: ?>
                        <form method="POST" class="compact-form">
                            <?php echo stridebr_csrf_field(); ?>
                            <input type="hidden" name="action" value="share_snapshot">
                            <input type="hidden" name="idcronograma" value="<?php echo stridebr_e($idSelecionado); ?>">
                            <label><?php echo stridebr_e(stridebr_t('schedule.send_to')); ?><select name="idusuario_destino" required><?php foreach ($friends as $friend): ?><option value="<?php echo stridebr_e($friend['idusuario']); ?>"><?php echo stridebr_e(stridebr_person_name_for_display((string)$friend['nome_exibicao'], (string)($friend['username'] ?? ''), stridebr_t('schedule.user_fallback'), 60)); ?><?php echo $friend['username'] ? ' (@' . stridebr_e($friend['username']) . ')' : ''; ?></option><?php endforeach; ?></select></label>
                            <button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('schedule.send_copy')); ?></button>
                            <button type="button" class="secondary-button" data-close-share><?php echo stridebr_e(stridebr_t('common.cancel')); ?></button>
                        </form>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

                <div class="workout-editor-modal" data-workout-editor<?php echo $treinoEdicao === [] ? ' hidden' : ''; ?>>
                    <button type="button" class="workout-editor-backdrop" data-close-workout aria-label="<?php echo stridebr_e(stridebr_t('schedule.close_editor')); ?>"></button>
                    <section class="workout-editor" role="dialog" aria-modal="true" aria-labelledby="workout-editor-title">
                    <div class="editor-title-row">
                        <h2 id="workout-editor-title" data-editor-title><?php echo stridebr_e(stridebr_t($treinoEdicao !== [] ? 'schedule.edit_workout_title' : 'schedule.new_workout_title')); ?></h2>
                        <button type="button" class="icon-button" data-close-workout aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button>
                    </div>
                    <form method="POST" class="workout-form">
                        <?php echo stridebr_csrf_field(); ?>
                        <input type="hidden" name="action" value="save_workout">
                        <input type="hidden" name="idcronograma" value="<?php echo stridebr_e($idSelecionado); ?>">
                        <input type="hidden" name="idtreino" value="<?php echo stridebr_e($treinoEdicao['idtreino'] ?? ''); ?>" data-workout-id>
                        <input type="hidden" name="data_original" value="" data-editor-occurrence-original>
                        <input type="hidden" name="return_to" value="" data-editor-return-to>
                        <label class="editor-title-field"><?php echo stridebr_e(stridebr_t('common.title')); ?>
                            <input type="text" name="titulo" maxlength="120" value="<?php echo stridebr_e($treinoEdicao['titulo'] ?? ''); ?>" placeholder="<?php echo stridebr_e(stridebr_t('schedule.workout_title_placeholder')); ?>" required>
                        </label>
                        <label class="editor-code-field"><?php echo stridebr_e(stridebr_t('schedule.code')); ?>
                            <input type="text" name="codigo" maxlength="24" value="<?php echo stridebr_e($treinoEdicao['codigo'] ?? ''); ?>" placeholder="<?php echo stridebr_e(stridebr_t('schedule.code_placeholder')); ?>" list="workout-code-suggestions">
                        </label>
                        <label class="editor-focus-field"><?php echo stridebr_e(stridebr_t('schedule.focus')); ?>
                            <input type="text" name="foco" maxlength="80" value="<?php echo stridebr_e($treinoEdicao['foco'] ?? ''); ?>" placeholder="<?php echo stridebr_e(stridebr_t('schedule.focus_placeholder')); ?>" list="workout-focus-suggestions">
                        </label>
                        <div class="editor-sport-field"><span class="form-field-label"><?php echo stridebr_e(stridebr_t('schedule.sport_activity')); ?></span>
                            <?php echo sportPickerRenderSelect($modalidadesTreino, ['name' => 'idmodalidade', 'selected' => (string) ($treinoEdicao['idmodalidade'] ?? ''), 'empty_label' => 'Detectar automaticamente', 'native_attributes' => ['data-editor-sport' => true]]); ?>
                        </div>
                        <datalist id="workout-code-suggestions"><option value="A"><option value="B"><option value="C"><option value="D"><option value="Push"><option value="Pull"><option value="Legs"></datalist>
                        <datalist id="workout-focus-suggestions"><option value="<?php echo stridebr_e(stridebr_t('schedule.focus_legs')); ?>"><option value="<?php echo stridebr_e(stridebr_t('schedule.focus_upper')); ?>"><option value="<?php echo stridebr_e(stridebr_t('schedule.focus_full_body')); ?>"><option value="<?php echo stridebr_e(stridebr_t('schedule.focus_chest')); ?>"><option value="<?php echo stridebr_e(stridebr_t('schedule.focus_back')); ?>"><option value="<?php echo stridebr_e(stridebr_t('schedule.focus_shoulders')); ?>"><option value="<?php echo stridebr_e(stridebr_t('schedule.focus_arms')); ?>"><option value="<?php echo stridebr_e(stridebr_t('schedule.focus_push')); ?>"><option value="<?php echo stridebr_e(stridebr_t('schedule.focus_pull')); ?>"><option value="<?php echo stridebr_e(stridebr_t('schedule.focus_legs')); ?>"><option value="<?php echo stridebr_e(stridebr_t('schedule.focus_core')); ?>"><option value="<?php echo stridebr_e(stridebr_t('schedule.focus_cardio')); ?>"><option value="<?php echo stridebr_e(stridebr_t('schedule.focus_mobility')); ?>"></datalist>
                        <label class="editor-day-field"><?php echo stridebr_e(stridebr_t('schedule.day')); ?>
                            <select name="dia_semana" required>
                                <?php foreach ($dias as $index => $dia): ?>
                                    <option value="<?php echo $index; ?>"<?php echo (int) ($treinoEdicao['dia_semana'] ?? 1) === $index ? ' selected' : ''; ?>><?php echo stridebr_e($dia); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="editor-start-field time24-field-label"><?php echo stridebr_e(stridebr_t('schedule.start')); ?>
                            <div class="time24-control" data-time24>
                                <div class="time24-input-row"><input type="text" inputmode="numeric" maxlength="2" required data-time24-hours aria-label="<?php echo stridebr_e(stridebr_t('schedule.start_time')); ?>"><span class="time24-separator">:</span><input type="text" inputmode="numeric" maxlength="2" required data-time24-minutes aria-label="<?php echo stridebr_e(stridebr_t('schedule.start_minutes')); ?>"><button type="button" class="time24-toggle" data-time24-toggle aria-expanded="false" aria-label="<?php echo stridebr_e(stridebr_t('schedule.choose_start_time')); ?>">⌄</button></div>
                                <div class="time24-menu" data-time24-menu hidden><div class="time24-menu-head"><span><?php echo stridebr_e(stridebr_t('schedule.time_24h')); ?></span><button type="button" data-time24-now><?php echo stridebr_e(stridebr_t('common.now')); ?></button></div><div class="time24-hours-grid" data-time24-hours-grid></div><div class="time24-minutes-grid" data-time24-minutes-grid></div></div>
                                <input type="hidden" name="hora_inicio" value="<?php echo stridebr_e(isset($treinoEdicao['hora_inicio']) ? substr($treinoEdicao['hora_inicio'], 0, 5) : '18:00'); ?>" data-time24-value>
                            </div>
                        </label>
                        <label class="editor-end-field time24-field-label"><?php echo stridebr_e(stridebr_t('schedule.end')); ?>
                            <div class="time24-control" data-time24>
                                <div class="time24-input-row"><input type="text" inputmode="numeric" maxlength="2" required data-time24-hours aria-label="<?php echo stridebr_e(stridebr_t('schedule.end_hour')); ?>"><span class="time24-separator">:</span><input type="text" inputmode="numeric" maxlength="2" required data-time24-minutes aria-label="<?php echo stridebr_e(stridebr_t('schedule.end_minutes')); ?>"><button type="button" class="time24-toggle" data-time24-toggle aria-expanded="false" aria-label="<?php echo stridebr_e(stridebr_t('schedule.choose_end_time')); ?>">⌄</button></div>
                                <div class="time24-menu" data-time24-menu hidden><div class="time24-menu-head"><span><?php echo stridebr_e(stridebr_t('schedule.time_24h')); ?></span><button type="button" data-time24-now><?php echo stridebr_e(stridebr_t('common.now')); ?></button></div><div class="time24-hours-grid" data-time24-hours-grid></div><div class="time24-minutes-grid" data-time24-minutes-grid></div></div>
                                <input type="hidden" name="hora_fim" value="<?php echo stridebr_e(isset($treinoEdicao['hora_fim']) ? substr($treinoEdicao['hora_fim'], 0, 5) : '19:00'); ?>" data-time24-value>
                            </div>
                        </label>
                        <label class="check-label editor-next-day-field">
                            <input type="checkbox" name="termina_dia_seguinte" value="1"<?php echo stridebr_db_bool($treinoEdicao['termina_dia_seguinte'] ?? false) ? ' checked' : ''; ?>>
                            <?php echo stridebr_e(stridebr_t('schedule.ends_next_day')); ?>
                        </label>
                        <label class="editor-series-start" data-series-only><?php echo stridebr_e(stridebr_t('schedule.repeat_from')); ?>
                            <input type="date" name="vigencia_inicio" value="<?php echo stridebr_e((string) ($treinoEdicao['vigencia_inicio'] ?? date('Y-m-d'))); ?>" required>
                            <small class="field-help"><?php echo stridebr_e(stridebr_t('schedule.repeat_from_help')); ?></small>
                        </label>
                        <label class="editor-series-end" data-series-only><?php echo stridebr_e(stridebr_t('schedule.repeat_until')); ?>
                            <input type="date" name="vigencia_fim" value="<?php echo stridebr_e((string) ($treinoEdicao['vigencia_fim'] ?? '')); ?>">
                            <small class="field-help"><?php echo stridebr_e(stridebr_t('schedule.repeat_until_help')); ?></small>
                        </label>
                        <label class="editor-description"><?php echo stridebr_e(stridebr_t('common.description')); ?>
                            <textarea name="descricao" rows="2" placeholder="<?php echo stridebr_e(stridebr_t('schedule.optional_placeholder')); ?>"><?php echo stridebr_e($treinoEdicao['descricao'] ?? ''); ?></textarea>
                        </label>
                        <div class="form-actions">
                            <button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('schedule.save_workout')); ?></button>
                            <a class="secondary-button" data-workout-exercises-link href="<?php echo $treinoEdicao !== [] ? '/user/exercicioscronograma.php?idtreino=' . rawurlencode((string) $treinoEdicao['idtreino']) : '#'; ?>"<?php echo $treinoEdicao === [] ? ' hidden' : ''; ?>><?php echo stridebr_e(stridebr_t('schedule.edit_exercises')); ?></a>
                        </div>
                    </form>
                    </section>
                </div>
                <div class="workout-save-scope-modal" data-workout-save-scope hidden>
                    <button type="button" class="workout-save-scope-backdrop" data-close-workout-scope aria-label="<?php echo stridebr_e(stridebr_t('common.cancel')); ?>"></button>
                    <section class="workout-save-scope-dialog" role="dialog" aria-modal="true" aria-labelledby="workout-save-scope-title">
                        <header><div><span class="eyebrow"><?php echo stridebr_e(stridebr_t('schedule.repetition')); ?></span><h2 id="workout-save-scope-title"><?php echo stridebr_e(stridebr_t('schedule.save_scope_title')); ?></h2><p><?php echo stridebr_e(stridebr_t('schedule.save_scope_help')); ?></p></div><button type="button" class="icon-button" data-close-workout-scope aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button></header>
                        <div class="workout-save-scope-options">
                            <button type="button" data-workout-scope-choice="this"><strong><?php echo stridebr_e(stridebr_t('schedule.this_only')); ?></strong><span><?php echo stridebr_e(stridebr_t('schedule.this_only_help')); ?></span></button>
                            <button type="button" data-workout-scope-choice="future"><strong><?php echo stridebr_e(stridebr_t('schedule.this_future')); ?></strong><span><?php echo stridebr_e(stridebr_t('schedule.this_future_help')); ?></span></button>
                            <button type="button" data-workout-scope-choice="all"><strong><?php echo stridebr_e(stridebr_t('schedule.all_occurrences')); ?></strong><span><?php echo stridebr_e(stridebr_t('schedule.all_occurrences_help')); ?></span></button>
                        </div>
                        <footer><button type="button" class="secondary-button" data-close-workout-scope><?php echo stridebr_e(stridebr_t('common.cancel')); ?></button></footer>
                    </section>
                </div>
                <script type="application/json" data-workout-editor-data><?php echo json_encode(array_map(static fn(array $item): array => [
                    'idtreino' => (string) $item['idtreino'],
                    'titulo' => (string) $item['titulo'],
                    'codigo' => (string) ($item['codigo'] ?? ''),
                    'foco' => (string) ($item['foco'] ?? ''),
                    'idmodalidade' => (string) ($item['idmodalidade'] ?? ''),
                    'descricao' => (string) ($item['descricao'] ?? ''),
                    'dia_semana' => (int) $item['dia_semana'],
                    'hora_inicio' => substr((string) $item['hora_inicio'], 0, 5),
                    'hora_fim' => substr((string) $item['hora_fim'], 0, 5),
                    'termina_dia_seguinte' => stridebr_db_bool($item['termina_dia_seguinte'] ?? false),
                    'vigencia_inicio' => (string) ($item['vigencia_inicio'] ?? ''),
                    'vigencia_fim' => (string) ($item['vigencia_fim'] ?? ''),
                ], $treinos), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>

                <div class="schedule-view-layout">
                    <aside class="schedule-side-panel" aria-label="<?php echo stridebr_e(stridebr_t('schedule.navigation_aria')); ?>">
                        <section class="schedule-mini-month">
                            <div class="schedule-mini-month-heading">
                                <strong data-month-title><?php echo stridebr_e($monthTitle); ?></strong>
                                <a href="/user/cronogramatreinos.php?id=<?php echo rawurlencode($idSelecionado); ?>&view=month&month=<?php echo rawurlencode($monthKey); ?>" data-open-month-view data-no-page-loading><?php echo stridebr_e(stridebr_t('schedule.open_month')); ?></a>
                            </div>
                            <div class="schedule-mini-month-grid" aria-label="<?php echo stridebr_e($monthTitle); ?>">
                                <?php foreach ($monthDayNames as $monthDayName): ?><span class="schedule-mini-weekday"><?php echo stridebr_e(substr($monthDayName, 0, 1)); ?></span><?php endforeach; ?>
                                <?php for ($blank = 0; $blank < $monthLeading; $blank++): ?><span class="schedule-mini-day is-outside"></span><?php endfor; ?>
                                <?php for ($day = 1; $day <= $monthTotalDays; $day++): ?>
                                    <?php $miniDate = $monthStart->setDate((int) $monthStart->format('Y'), (int) $monthStart->format('m'), $day)->format('Y-m-d'); ?>
                                    <span class="schedule-mini-day<?php echo $miniDate === $today ? ' is-today' : ''; ?>"><?php echo $day; ?></span>
                                <?php endfor; ?>
                            </div>
                        </section>
                        <section class="schedule-week-progress<?php echo $weekDone ? ' is-complete' : ''; ?>" aria-label="<?php echo stridebr_e(stridebr_t('home.week_summary')); ?>" data-week-summary data-schedule-id="<?php echo stridebr_e($idSelecionado); ?>" data-week-start="<?php echo stridebr_e($weekStart->format('Y-m-d')); ?>">
                            <div class="schedule-week-progress-heading">
                                <div><span><?php echo stridebr_e(stridebr_t('schedule.this_week')); ?></span><strong><?php echo stridebr_e($weekPlannedCount === 0 ? stridebr_t('schedule.week_no_workouts') : ($weekDone ? stridebr_t('schedule.week_completed_feminine') : stridebr_t('schedule.week_done_of_total', ['done' => $weekCompletedCount, 'total' => $weekPlannedCount]))); ?></strong></div>
                                <?php if ($weekPlannedCount > 0): ?><span class="schedule-week-progress-count"><?php echo $weekProgress; ?>%</span><?php endif; ?>
                            </div>
                            <?php if ($weekPlannedCount > 0): ?>
                                <div class="schedule-week-progress-bar" aria-hidden="true"><span style="width: <?php echo $weekProgress; ?>%"></span></div>
                                <?php if ($weekDone): ?>
                                    <p><?php echo stridebr_e(stridebr_t('schedule.all_done')); ?></p>
                                <?php elseif ($weekNextOccurrence !== null): ?>
                                    <div class="schedule-week-next">
                                        <div class="schedule-week-next-heading"><span><?php echo stridebr_e(stridebr_t('schedule.next_suggested')); ?></span><?php if ($weekPendingChoices !== []): ?><button type="button" data-week-choices-toggle aria-expanded="false"><?php echo stridebr_e(stridebr_t('schedule.change')); ?></button><?php endif; ?></div>
                                        <button type="button" class="schedule-week-next-main" data-preview-workout="<?php echo stridebr_e((string) $weekNextOccurrence['idtreino']); ?>" data-workout-date="<?php echo stridebr_e($today); ?>" data-occurrence-original="<?php echo stridebr_e((string) $weekNextOccurrence['data_original']); ?>" data-planned-date="<?php echo stridebr_e((string) ($weekNextOccurrence['data_planejada'] ?? $weekNextOccurrence['data_treino'])); ?>" data-planned-time="<?php echo stridebr_e((string) ($weekNextOccurrence['hora_planejada'] ?? substr((string) $weekNextOccurrence['hora_inicio'], 0, 5))); ?>">
                                            <?php if (!empty($weekNextOccurrence['codigo'])): ?><span class="schedule-week-next-code"><?php echo stridebr_e((string) $weekNextOccurrence['codigo']); ?></span><?php endif; ?>
                                            <span class="schedule-week-next-copy"><strong><?php echo stridebr_e((string) ($weekNextOccurrence['titulo'] ?? stridebr_t('schedule.workout_fallback'))); ?></strong><?php if (!empty($weekNextOccurrence['foco'])): ?><small><?php echo stridebr_e((string) $weekNextOccurrence['foco']); ?></small><?php endif; ?></span>
                                        </button>
                                        <?php if ($weekPendingChoices !== []): ?>
                                            <div class="schedule-week-choices" data-week-choices hidden>
                                                <?php foreach ($weekPendingChoices as $choice): ?>
                                                    <button type="button" data-week-choice="<?php echo stridebr_e((string) $choice['idtreino']); ?>">
                                                        <?php if (!empty($choice['codigo'])): ?><span class="schedule-week-choice-code"><?php echo stridebr_e((string) $choice['codigo']); ?></span><?php endif; ?>
                                                        <span><strong><?php echo stridebr_e((string) ($choice['titulo'] ?? stridebr_t('schedule.workout_fallback'))); ?></strong><?php if (!empty($choice['foco'])): ?><small><?php echo stridebr_e((string) $choice['foco']); ?></small><?php endif; ?></span>
                                                    </button>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <p><?php echo stridebr_e(stridebr_tn('schedule.pending_plain.one', 'schedule.pending_plain.other', $weekPlannedCount - $weekCompletedCount)); ?></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p><?php echo stridebr_e(stridebr_t('schedule.nothing_programmed')); ?></p>
                            <?php endif; ?>
                        </section>
                        <section class="schedule-side-list">
                            <div class="schedule-side-list-heading"><strong><?php echo stridebr_e(stridebr_t('schedule.my_schedules')); ?></strong><span><?php echo count($cronogramas); ?></span></div>
                            <nav>
                                <?php foreach ($cronogramas as $item): ?>
                                    <a href="/user/cronogramatreinos.php?id=<?php echo rawurlencode((string) $item['idcronograma']); ?>" class="<?php echo $item['idcronograma'] === $idSelecionado ? 'is-active' : ''; ?>"><span><?php echo stridebr_e($item['nome']); ?></span></a>
                                <?php endforeach; ?>
                            </nav>
                        </section>
                    </aside>
                    <div class="schedule-view-main">
                <section class="calendar-view" data-calendar-view="week" data-calendar-scroll<?php echo $initialView === 'week' ? '' : ' hidden'; ?>>
                    <div class="week-calendar" data-week-calendar>
                        <div class="time-column">
                            <div class="calendar-corner"></div>
                            <div class="time-track">
                                <?php for ($hour = 0; $hour < 24; $hour++): ?>
                                    <span style="--hour: <?php echo $hour; ?>"><?php echo sprintf('%02d:00', $hour); ?></span>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php foreach ($dias as $dayIndex => $dayName): ?>
                            <?php $weekDate = $weekDates[$dayIndex]; ?>
                            <div class="day-column">
                                <div class="day-header<?php echo $weekDate->format('Y-m-d') === $today ? ' is-today' : ''; ?>"><span><?php echo stridebr_e($dayName); ?></span><small><?php echo stridebr_e($weekDate->format('d/m')); ?></small></div>
                                <div class="day-track" data-week-date="<?php echo stridebr_e($weekDate->format('Y-m-d')); ?>">
                                    <?php for ($hour = 0; $hour < 24; $hour++): ?><div class="hour-line" style="--hour: <?php echo $hour; ?>"></div><?php endfor; ?>
                                    <?php foreach ($segments[$dayIndex] ?? [] as $segment): ?>
                                        <?php
                                        $duration = max(30, $segment['fim'] - $segment['inicio']);
                                        $item = $segment['treino'];
                                        $exerciseCount = count($exerciciosPorTreino[$item['idtreino']] ?? []);
                                        $itemStart = ((int) substr((string) $item['hora_inicio'], 0, 2)) * 60 + (int) substr((string) $item['hora_inicio'], 3, 2);
                                        $itemEnd = ((int) substr((string) $item['hora_fim'], 0, 2)) * 60 + (int) substr((string) $item['hora_fim'], 3, 2);
                                        $fullDuration = stridebr_db_bool($item['termina_dia_seguinte'] ?? false) ? (1440 - $itemStart + $itemEnd) : max(1, $itemEnd - $itemStart);
                                        $completed = !empty($item['concluido']);
                                        $draggable = !$segment['continuidade'] && !$completed;
                                        ?>
                                        <button type="button" class="workout-card<?php echo $segment['continuidade'] ? ' is-continuation' : ''; ?><?php echo $completed ? ' is-complete' : ''; ?><?php echo !$completed && !empty($item['excecao']) ? ' is-exception' : ''; ?><?php echo !empty($item['realizado_fora_planejado']) ? ' is-realized-shifted' : ''; ?>" data-preview-workout="<?php echo stridebr_e($item['idtreino']); ?>" data-workout-date="<?php echo stridebr_e((string) $item['data_treino']); ?>" data-week-card data-occurrence-workout="<?php echo stridebr_e((string) $item['idtreino']); ?>" data-occurrence-original="<?php echo stridebr_e((string) $item['data_original']); ?>" data-occurrence-date="<?php echo stridebr_e((string) $item['data_treino']); ?>" data-occurrence-title="<?php echo stridebr_e((string) $item['titulo']); ?>" data-duration-min="<?php echo (int) $fullDuration; ?>" data-planned-date="<?php echo stridebr_e((string) ($item['data_planejada'] ?? $item['data_treino'])); ?>" data-planned-time="<?php echo stridebr_e((string) ($item['hora_planejada'] ?? substr((string) $item['hora_inicio'], 0, 5))); ?>" data-realized-date="<?php echo stridebr_e((string) ($item['data_realizada'] ?? '')); ?>" data-realized-time="<?php echo stridebr_e((string) ($item['hora_realizada'] ?? '')); ?>" data-activity-id="<?php echo stridebr_e((string) ($item['idregistro'] ?? '')); ?>" data-completed="<?php echo $completed ? '1' : '0'; ?>"<?php echo $draggable ? ' draggable="true"' : ''; ?> style="--start-min: <?php echo (int) $segment['inicio']; ?>; --duration-min: <?php echo (int) $duration; ?>" title="<?php echo stridebr_e(stridebr_t($completed ? 'schedule.workout_completed_title' : ($draggable ? 'schedule.workout_drag_title' : 'schedule.workout_view_title'))); ?>">
                                            <?php if (!empty($item['codigo']) || !empty($item['foco'])): ?><small class="workout-card-kicker"><?php echo stridebr_e(implode(' · ', array_filter([(string) ($item['codigo'] ?? ''), (string) ($item['foco'] ?? '')]))); ?></small><?php endif; ?>
                                            <strong><?php echo stridebr_e($item['titulo']); ?></strong>
                                            <span data-card-time><?php echo $segment['continuidade'] ? stridebr_e(stridebr_t('schedule.continuation')) . ' · ' : ''; ?><?php echo stridebr_e(substr((string) $item['hora_inicio'], 0, 5)); ?>–<?php echo stridebr_e(substr((string) $item['hora_fim'], 0, 5)); ?><?php echo stridebr_db_bool($item['termina_dia_seguinte']) ? ' +1' : ''; ?></span>
                                            <?php if ($completed): ?><small class="workout-card-status"><?php echo stridebr_e(stridebr_t('schedule.completed_badge')); ?></small><?php elseif ($exerciseCount > 0): ?><small><?php echo stridebr_e(stridebr_tn('schedule.exercise_count.one', 'schedule.exercise_count.other', $exerciseCount)); ?></small><?php endif; ?>
                                        </button>
                                    <?php endforeach; ?>
                                    <?php foreach ($weekPlanGhosts[$dayIndex] ?? [] as $ghost): ?>
                                        <?php
                                        $ghostStart = ((int) substr((string) ($ghost['hora_planejada'] ?? $ghost['hora_inicio']), 0, 2)) * 60 + (int) substr((string) ($ghost['hora_planejada'] ?? $ghost['hora_inicio']), 3, 2);
                                        $ghostEndBase = ((int) substr((string) $ghost['hora_fim'], 0, 2)) * 60 + (int) substr((string) $ghost['hora_fim'], 3, 2);
                                        $ghostActualStart = ((int) substr((string) $ghost['hora_inicio'], 0, 2)) * 60 + (int) substr((string) $ghost['hora_inicio'], 3, 2);
                                        $ghostDuration = stridebr_db_bool($ghost['termina_dia_seguinte'] ?? false) ? max(30, 1440 - $ghostActualStart + $ghostEndBase) : max(30, $ghostEndBase - $ghostActualStart);
                                        ?>
                                        <button type="button" class="workout-card is-plan-ghost" data-preview-workout="<?php echo stridebr_e((string) $ghost['idtreino']); ?>" data-workout-date="<?php echo stridebr_e((string) $ghost['data_planejada']); ?>" data-occurrence-original="<?php echo stridebr_e((string) $ghost['data_original']); ?>" data-planned-date="<?php echo stridebr_e((string) $ghost['data_planejada']); ?>" data-planned-time="<?php echo stridebr_e((string) $ghost['hora_planejada']); ?>" data-realized-date="<?php echo stridebr_e((string) $ghost['data_realizada']); ?>" data-realized-time="<?php echo stridebr_e((string) $ghost['hora_realizada']); ?>" data-completed="1" style="--start-min: <?php echo (int) $ghostStart; ?>; --duration-min: <?php echo (int) $ghostDuration; ?>">
                                            <?php if (!empty($ghost['codigo'])): ?><small class="workout-card-kicker"><?php echo stridebr_e((string) $ghost['codigo']); ?></small><?php endif; ?>
                                            <strong><?php echo stridebr_e((string) $ghost['titulo']); ?></strong>
                                            <span><?php echo stridebr_e((string) $ghost['hora_planejada']); ?> <?php echo stridebr_e(stridebr_t('schedule.planned_badge')); ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="schedule-month-view" data-calendar-view="month" data-month-calendar-shell data-schedule-id="<?php echo stridebr_e($idSelecionado); ?>" data-current-month="<?php echo stridebr_e($monthKey); ?>"<?php echo $initialView === 'month' ? '' : ' hidden'; ?>>
                    <div class="schedule-month-toolbar">
                        <div class="schedule-month-nav" aria-label="<?php echo stridebr_e(stridebr_t('schedule.month_navigation')); ?>">
                            <a class="view-button" href="/user/cronogramatreinos.php?id=<?php echo rawurlencode($idSelecionado); ?>&view=month&month=<?php echo rawurlencode($prevMonth); ?>" data-month-nav="<?php echo stridebr_e($prevMonth); ?>" data-no-page-loading aria-label="<?php echo stridebr_e(stridebr_t('schedule.previous_month')); ?>">←</a>
                            <strong data-month-title><?php echo stridebr_e($monthTitle); ?></strong>
                            <a class="view-button" href="/user/cronogramatreinos.php?id=<?php echo rawurlencode($idSelecionado); ?>&view=month&month=<?php echo rawurlencode($nextMonth); ?>" data-month-nav="<?php echo stridebr_e($nextMonth); ?>" data-no-page-loading aria-label="<?php echo stridebr_e(stridebr_t('schedule.next_month')); ?>">→</a>
                        </div>
                        <div class="schedule-month-actions">
                            <a class="secondary-button" href="/user/cronogramatreinos.php?id=<?php echo rawurlencode($idSelecionado); ?>&view=month&month=<?php echo rawurlencode((new DateTimeImmutable('first day of this month'))->format('Y-m')); ?>#schedule-today" data-month-nav="<?php echo stridebr_e((new DateTimeImmutable('first day of this month'))->format('Y-m')); ?>" data-no-page-loading><?php echo stridebr_e(stridebr_t('schedule.go_today')); ?></a>
                            <?php if ($monthlyOverlayEnabled): ?><a class="secondary-button" href="/user/agenda-mensal.php?cronograma=<?php echo rawurlencode($idSelecionado); ?>&month=<?php echo rawurlencode($monthKey); ?>"><?php echo stridebr_e(stridebr_t('schedule.full_agenda')); ?></a><?php endif; ?>
                        </div>
                    </div>
                    <p class="schedule-month-hint"><?php echo stridebr_e(stridebr_t('schedule.month_projection_help')); ?><?php echo $monthlyOverlayEnabled ? ' ' . stridebr_e(stridebr_t('schedule.month_overlay_help')) : ''; ?></p>
                    <div class="schedule-month-calendar-wrap">
                        <div class="monthly-calendar schedule-month-calendar is-loading" data-month-grid aria-label="<?php echo stridebr_e($monthTitle); ?>">
                            <?php foreach ($monthDayNames as $monthDayName): ?><div class="monthly-weekday"><?php echo stridebr_e($monthDayName); ?></div><?php endforeach; ?>
                            <?php for ($cell = 0; $cell < $monthCellCount; $cell++): ?><div class="monthly-day month-day-skeleton" aria-hidden="true"><span></span><i></i><i></i></div><?php endfor; ?>
                        </div>
                    </div>
                </section>

                <section class="agenda-view" data-calendar-view="agenda"<?php echo $initialView === 'agenda' ? '' : ' hidden'; ?>>
                    <?php if ($treinos === []): ?>
                        <div class="empty-state rich"><strong><?php echo stridebr_e(stridebr_t('schedule.empty')); ?></strong><p><?php echo stridebr_e(stridebr_t('schedule.empty_help')); ?></p><button type="button" class="primary-button" data-new-workout><?php echo stridebr_e(stridebr_t('schedule.add_first_workout')); ?></button></div>
                    <?php else: ?>
                        <?php foreach ($dias as $dayIndex => $dayName): ?>
                            <?php $dayWorkouts = array_values(array_filter($treinos, fn(array $t): bool => (int) $t['dia_semana'] === $dayIndex)); ?>
                            <?php if ($dayWorkouts !== []): ?>
                                <div class="agenda-day">
                                    <h2><?php echo stridebr_e($dayName); ?></h2>
                                    <?php foreach ($dayWorkouts as $item): ?>
                                        <article class="agenda-card">
                                            <button type="button" class="agenda-card-main" data-preview-workout="<?php echo stridebr_e($item['idtreino']); ?>">
                                                <?php if (!empty($item['codigo']) || !empty($item['foco'])): ?><small class="workout-card-kicker"><?php echo stridebr_e(implode(' · ', array_filter([(string) ($item['codigo'] ?? ''), (string) ($item['foco'] ?? '')]))); ?></small><?php endif; ?>
                                                <strong><?php echo stridebr_e($item['titulo']); ?></strong>
                                                <span><?php echo stridebr_e(substr($item['hora_inicio'], 0, 5)); ?>–<?php echo stridebr_e(substr($item['hora_fim'], 0, 5)); ?><?php echo stridebr_db_bool($item['termina_dia_seguinte']) ? ' do dia seguinte' : ''; ?></span>
                                            </button>
                                            <div class="agenda-actions">
                                                <details class="library-more-menu agenda-more-menu"><summary aria-label="<?php echo stridebr_e(stridebr_t('schedule.more_actions')); ?>">•••</summary><div>
                                                    <button type="button" data-edit-workout="<?php echo stridebr_e((string) $item['idtreino']); ?>"><?php echo stridebr_e(stridebr_t('schedule.edit_workout')); ?></button>
                                                    <form method="POST" data-confirm="Remover este treino?"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="delete_workout"><input type="hidden" name="idcronograma" value="<?php echo stridebr_e($idSelecionado); ?>"><input type="hidden" name="idtreino" value="<?php echo stridebr_e($item['idtreino']); ?>"><button class="is-danger" type="submit"><?php echo stridebr_e(stridebr_t('schedule.remove_workout')); ?></button></form>
                                                </div></details>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                    </div>
                </div>

                <div class="workout-preview" data-workout-preview-modal hidden>
                    <div class="workout-preview-backdrop" data-close-preview></div>
                    <section class="workout-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="workout-preview-title">
                        <button type="button" class="icon-button workout-preview-close" data-close-preview aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button>
                        <?php foreach ($treinos as $item): ?>
                            <?php $previewExercises = $exerciciosPorTreino[$item['idtreino']] ?? []; ?>
                            <div class="workout-preview-content" data-workout-preview-content="<?php echo stridebr_e($item['idtreino']); ?>" hidden>
                                <div class="workout-preview-heading">
                                    <div class="workout-preview-occurrence-context" data-preview-occurrence-context hidden></div>
                                    <span><?php echo stridebr_e($dias[(int) $item['dia_semana']]); ?> · <?php echo stridebr_e(substr($item['hora_inicio'], 0, 5)); ?>–<?php echo stridebr_e(substr($item['hora_fim'], 0, 5)); ?><?php echo stridebr_db_bool($item['termina_dia_seguinte']) ? ' +1' : ''; ?></span>
                                    <?php if (!empty($item['codigo']) || !empty($item['foco'])): ?><div class="workout-preview-tags"><?php if (!empty($item['codigo'])): ?><b><?php echo stridebr_e((string) $item['codigo']); ?></b><?php endif; ?><?php if (!empty($item['foco'])): ?><b><?php echo stridebr_e((string) $item['foco']); ?></b><?php endif; ?></div><?php endif; ?>
                                    <h2 id="workout-preview-title"><?php echo stridebr_e($item['titulo']); ?></h2>
                                    <?php if (!empty($item['descricao'])): ?><p><?php echo stridebr_e($item['descricao']); ?></p><?php endif; ?>
                                </div>
                                <div class="workout-preview-exercises">
                                    <?php if ($previewExercises === []): ?>
                                        <p class="preview-empty"><?php echo stridebr_e(stridebr_t('schedule.no_exercises')); ?></p>
                                    <?php else: ?>
                                        <?php foreach ($previewExercises as $i => $exercise): ?>
                                            <article class="preview-exercise">
                                                <span class="preview-exercise-number"><?php echo $i + 1; ?></span>
                                                <div>
                                                    <strong><?php echo stridebr_e($exercise['nome_snapshot']); ?></strong>
                                                    <div class="preview-exercise-meta">
                                                        <?php if ($exercise['series'] !== null): ?><span><?php echo stridebr_e((string) $exercise['series']); ?> <?php echo stridebr_e(stridebr_t('schedule.sets')); ?></span><?php endif; ?>
                                                        <?php if (!empty($exercise['repeticoes'])): ?><span><?php echo stridebr_e(cronogramaFormatarRepeticoes((string) $exercise['repeticoes'])); ?></span><?php endif; ?>
                                                        <?php if (!empty($exercise['carga'])): ?><span><?php echo stridebr_e($exercise['carga']); ?></span><?php endif; ?>
                                                        <?php if (!empty($exercise['descanso'])): ?><span><?php echo stridebr_e(stridebr_t('schedule.rest')); ?> <?php echo stridebr_e($exercise['descanso']); ?></span><?php endif; ?>
                                                        <?php if (!empty($exercise['bloco'])): ?><span><?php echo stridebr_e(stridebr_t('schedule.block')); ?> <?php echo stridebr_e($exercise['bloco']); ?></span><?php endif; ?>
                                                        <?php if (!empty($exercise['cluster'])): ?><span><?php echo stridebr_e($exercise['cluster']); ?></span><?php endif; ?>
                                                    </div>
                                                    <?php if (!empty($exercise['observacoes'])): ?><small><?php echo stridebr_e($exercise['observacoes']); ?></small><?php endif; ?>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="workout-preview-actions">
                                    <div class="workout-preview-primary-actions">
                                        <?php if ($workoutSessionsEnabled): ?>
                                            <button type="button" class="primary-button" data-quick-register-workout="<?php echo stridebr_e($item['idtreino']); ?>" data-workout-title="<?php echo stridebr_e($item['titulo']); ?>" data-workout-time="<?php echo stridebr_e(substr((string) $item['hora_inicio'], 0, 5)); ?>" data-workout-duration="<?php echo cronogramaDuracaoMinutos($item); ?>"><?php echo stridebr_e(stridebr_t('schedule.log')); ?></button>
                                            <button type="button" class="secondary-button" data-start-workout="<?php echo stridebr_e($item['idtreino']); ?>"><?php echo stridebr_e(stridebr_t('schedule.start_live')); ?></button>
                                        <?php endif; ?>
                                        <button type="button" class="secondary-button" data-edit-workout="<?php echo stridebr_e((string) $item['idtreino']); ?>"><?php echo stridebr_e(stridebr_t('common.edit')); ?></button>
                                        <details class="workout-preview-more">
                                            <summary class="secondary-button"><?php echo stridebr_e(stridebr_t('common.more')); ?></summary>
                                            <div class="workout-preview-menu">
                                                <button type="button" data-preview-move><?php echo stridebr_e(stridebr_t('schedule.reschedule')); ?></button>
                                                <button type="button" data-preview-adjust-history hidden><?php echo stridebr_e(stridebr_t('schedule.fix_plan_actual')); ?></button>
                                                <button type="button" data-preview-skip><?php echo stridebr_e(stridebr_t('schedule.skip_occurrence')); ?></button>
                                                <a href="/user/exercicioscronograma.php?idtreino=<?php echo urlencode($item['idtreino']); ?>"><?php echo stridebr_e(stridebr_t('schedule.edit_exercises')); ?></a>
                                                <?php if (cronogramaBibliotecaDisponivel($pdo) && !empty($item['idtreino_modelo'])): ?>
                                                <form method="POST" class="preview-copy-form"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="update_library_from_workout"><input type="hidden" name="idcronograma" value="<?php echo stridebr_e($idSelecionado); ?>"><input type="hidden" name="idtreino" value="<?php echo stridebr_e($item['idtreino']); ?>"><input type="hidden" name="return_to" value="" data-return-current><button type="submit"><?php echo stridebr_e(stridebr_t('schedule.update_saved')); ?></button></form>
                                                <?php elseif (cronogramaBibliotecaDisponivel($pdo)): ?>
                                                <form method="POST" class="preview-copy-form"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="save_workout_to_library"><input type="hidden" name="idcronograma" value="<?php echo stridebr_e($idSelecionado); ?>"><input type="hidden" name="idtreino" value="<?php echo stridebr_e($item['idtreino']); ?>"><input type="hidden" name="return_to" value="" data-return-current><button type="submit"><?php echo stridebr_e(stridebr_t('schedule.save_library')); ?></button></form>
                                                <?php endif; ?>
                                                <form method="POST" class="preview-copy-form"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="duplicate_workout"><input type="hidden" name="idcronograma" value="<?php echo stridebr_e($idSelecionado); ?>"><input type="hidden" name="idtreino" value="<?php echo stridebr_e($item['idtreino']); ?>"><input type="hidden" name="duplicate_mode" value="edit"><input type="hidden" name="return_to" value="" data-return-current><button type="submit"><?php echo stridebr_e(stridebr_t('schedule.duplicate_edit')); ?></button></form>
                                                <form method="POST" class="preview-delete-form" data-confirm="Remover este treino do cronograma?"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="delete_workout"><input type="hidden" name="idcronograma" value="<?php echo stridebr_e($idSelecionado); ?>"><input type="hidden" name="idtreino" value="<?php echo stridebr_e($item['idtreino']); ?>"><input type="hidden" name="return_to" value="" data-return-current><button type="submit" class="is-danger"><?php echo stridebr_e(stridebr_t('schedule.delete_workout')); ?></button></form>
                                            </div>
                                        </details>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </section>
                </div>

                <div class="calendar-quick-create" data-calendar-quick-create hidden>
                    <button type="button" class="calendar-quick-create-backdrop" data-close-quick-create aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>"></button>
                    <section class="calendar-quick-create-popover" role="dialog" aria-modal="true" aria-labelledby="calendar-quick-create-title" data-quick-popover>
                        <header class="calendar-quick-create-header"><div><span class="eyebrow" data-quick-eyebrow><?php echo stridebr_e(stridebr_t('schedule.new_workout')); ?></span><h2 id="calendar-quick-create-title" data-quick-title><?php echo stridebr_e(stridebr_t('schedule.add_workout')); ?></h2><p><?php echo stridebr_e(stridebr_t('schedule.new_workout_help')); ?></p></div><button type="button" class="icon-button" data-close-quick-create aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button></header>
                        <form data-calendar-quick-form>
                            <?php echo stridebr_csrf_field(); ?>
                            <input type="hidden" name="mode" value="schedule" data-quick-mode>
                            <input type="hidden" name="idcronograma" value="<?php echo stridebr_e($idSelecionado); ?>" data-quick-schedule>
                            <section class="quick-create-source" data-quick-source-row>
                                <div class="quick-create-section-heading"><div><strong><?php echo stridebr_e(stridebr_t('schedule.start_with')); ?></strong><span><?php echo stridebr_e(stridebr_t('schedule.start_with_help')); ?></span></div></div>
                                <select name="idtreino_modelo" data-quick-source class="quick-create-source-select" aria-label="<?php echo stridebr_e(stridebr_t('schedule.saved_workout')); ?>">
                                    <option value=""><?php echo stridebr_e(stridebr_t('schedule.new_workout')); ?></option>
                                    <?php foreach ($treinosModelo as $modelo): ?><option value="<?php echo stridebr_e((string) $modelo['idtreino_modelo']); ?>" data-title="<?php echo stridebr_e((string) $modelo['titulo']); ?>" data-code="<?php echo stridebr_e((string) ($modelo['codigo'] ?? '')); ?>" data-focus="<?php echo stridebr_e((string) ($modelo['foco'] ?? '')); ?>" data-description="<?php echo stridebr_e((string) ($modelo['descricao'] ?? '')); ?>" data-modality="<?php echo stridebr_e((string) ($modelo['idmodalidade'] ?? '')); ?>" data-exercises="<?php echo (int) ($modelo['exercicios_total'] ?? 0); ?>"><?php echo stridebr_e((string) $modelo['titulo']); ?></option><?php endforeach; ?>
                                </select>
                                <div class="quick-create-source-cards" data-quick-source-cards>
                                    <button type="button" class="quick-create-source-card is-active" data-quick-source-card="">
                                        <span class="quick-create-source-icon">+</span><span><strong><?php echo stridebr_e(stridebr_t('schedule.new_workout')); ?></strong><small><?php echo stridebr_e(stridebr_t('schedule.start_blank')); ?></small></span>
                                    </button>
                                    <?php foreach ($treinosModelo as $modelo): ?>
                                        <button type="button" class="quick-create-source-card" data-quick-source-card="<?php echo stridebr_e((string) $modelo['idtreino_modelo']); ?>">
                                            <span class="quick-create-source-icon is-saved">↗</span>
                                            <span><strong><?php echo stridebr_e((string) $modelo['titulo']); ?></strong><small><?php echo stridebr_e(implode(' · ', array_filter([(string) ($modelo['codigo'] ?? ''), (string) ($modelo['foco'] ?? '')])) ?: stridebr_tn('schedule.exercise_count.one', 'schedule.exercise_count.other', (int) ($modelo['exercicios_total'] ?? 0))); ?></small></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                            <label class="quick-create-title-field"><?php echo stridebr_e(stridebr_t('common.title')); ?><input type="text" name="titulo" maxlength="120" placeholder="<?php echo stridebr_e(stridebr_t('schedule.library_title_placeholder')); ?>" required data-quick-workout-title></label>
                            <div class="quick-create-schedule-fields" data-quick-schedule-fields>
                                <label><?php echo stridebr_e(stridebr_t('common.date')); ?><input type="date" name="data_treino" required data-quick-date></label>
                                <div class="quick-create-time-row">
                                    <label class="time24-field-label"><?php echo stridebr_e(stridebr_t('schedule.start')); ?><div class="time24-control" data-time24><div class="time24-input-row"><input type="text" inputmode="numeric" maxlength="2" required data-time24-hours><span class="time24-separator">:</span><input type="text" inputmode="numeric" maxlength="2" required data-time24-minutes><button type="button" class="time24-toggle" data-time24-toggle aria-label="<?php echo stridebr_e(stridebr_t('agenda.choose_time')); ?>">⌄</button></div><div class="time24-menu" data-time24-menu hidden><div class="time24-menu-head"><span><?php echo stridebr_e(stridebr_t('schedule.time_24h')); ?></span><button type="button" data-time24-now><?php echo stridebr_e(stridebr_t('common.now')); ?></button></div><div class="time24-hours-grid" data-time24-hours-grid></div><div class="time24-minutes-grid" data-time24-minutes-grid></div></div><input type="hidden" name="hora_inicio" value="18:00" data-time24-value data-quick-start></div></label>
                                    <label class="time24-field-label"><?php echo stridebr_e(stridebr_t('schedule.end')); ?><div class="time24-control" data-time24><div class="time24-input-row"><input type="text" inputmode="numeric" maxlength="2" required data-time24-hours><span class="time24-separator">:</span><input type="text" inputmode="numeric" maxlength="2" required data-time24-minutes><button type="button" class="time24-toggle" data-time24-toggle aria-label="<?php echo stridebr_e(stridebr_t('agenda.choose_time')); ?>">⌄</button></div><div class="time24-menu" data-time24-menu hidden><div class="time24-menu-head"><span><?php echo stridebr_e(stridebr_t('schedule.time_24h')); ?></span><button type="button" data-time24-now><?php echo stridebr_e(stridebr_t('common.now')); ?></button></div><div class="time24-hours-grid" data-time24-hours-grid></div><div class="time24-minutes-grid" data-time24-minutes-grid></div></div><input type="hidden" name="hora_fim" value="19:00" data-time24-value data-quick-end></div></label>
                                </div>
                                <label class="quick-create-next-day"><input type="checkbox" name="termina_dia_seguinte" value="1" data-quick-next-day> <?php echo stridebr_e(stridebr_t('schedule.ends_next_day')); ?></label>
                                <div class="quick-create-recurrence-row"><label><?php echo stridebr_e(stridebr_t('schedule.repetition')); ?><select name="recorrencia" data-quick-repeat><option value="once"><?php echo stridebr_e(stridebr_t('schedule.specific_day')); ?></option><option value="weekly"><?php echo stridebr_e(stridebr_t('schedule.every_week')); ?></option></select></label><label data-quick-until hidden><?php echo stridebr_e(stridebr_t('schedule.until')); ?><input type="date" name="vigencia_fim" data-quick-until-input></label></div>
                            </div>
                            <details class="quick-create-details"><summary><?php echo stridebr_e(stridebr_t('schedule.more_details')); ?></summary><div class="quick-create-details-grid"><label><?php echo stridebr_e(stridebr_t('schedule.code')); ?><input type="text" name="codigo" maxlength="24" placeholder="<?php echo stridebr_e(stridebr_t('schedule.library_code_placeholder')); ?>" data-quick-code></label><label><?php echo stridebr_e(stridebr_t('schedule.focus')); ?><input type="text" name="foco" maxlength="80" placeholder="<?php echo stridebr_e(stridebr_t('schedule.library_focus_placeholder')); ?>" data-quick-focus></label><div class="quick-create-sport-field"><span class="form-field-label"><?php echo stridebr_e(stridebr_t('schedule.sport_activity')); ?></span><?php echo sportPickerRenderSelect($modalidadesTreino, ['name' => 'idmodalidade', 'empty_label' => 'Detectar automaticamente', 'native_attributes' => ['data-quick-modality' => true]]); ?></div><label class="quick-create-description"><?php echo stridebr_e(stridebr_t('common.description')); ?><textarea name="descricao" rows="2" maxlength="500" data-quick-description></textarea></label></div></details>
                            <div class="quick-create-exercises"><div><strong><?php echo stridebr_e(stridebr_t('common.exercises')); ?></strong><span data-quick-exercise-summary><?php echo stridebr_e(stridebr_t('schedule.no_exercises_added')); ?></span></div><button type="button" class="secondary-button" data-quick-edit-exercises><?php echo stridebr_e(stridebr_t('schedule.edit_exercises')); ?></button></div>
                            <label class="quick-create-save-library" data-quick-save-library-row><input type="checkbox" name="salvar_biblioteca" value="1" data-quick-save-library> <?php echo stridebr_e(stridebr_t('schedule.save_reusable')); ?></label>
                            <p class="quick-create-error" data-quick-error hidden></p>
                            <div class="quick-create-actions"><button type="button" class="secondary-button" data-close-quick-create><?php echo stridebr_e(stridebr_t('common.cancel')); ?></button><button type="submit" class="primary-button" data-quick-submit><?php echo stridebr_e(stridebr_t('schedule.save_workout')); ?></button></div>
                        </form>
                    </section>
                </div>

                <div class="occurrence-editor" data-occurrence-modal hidden>
                    <button type="button" class="occurrence-editor-backdrop" data-close-occurrence aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>"></button>
                    <section class="occurrence-editor-dialog" role="dialog" aria-modal="true" aria-labelledby="occurrence-editor-title">
                        <header><div><span class="eyebrow"><?php echo stridebr_e(stridebr_t('schedule.planning')); ?></span><h2 id="occurrence-editor-title" data-occurrence-title><?php echo stridebr_e(stridebr_t('schedule.reschedule_workout')); ?></h2></div><button type="button" class="icon-button" data-close-occurrence aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button></header>
                        <form data-occurrence-form><?php echo stridebr_csrf_field(); ?><input type="hidden" name="idtreino" data-occurrence-id><input type="hidden" name="data_original" data-occurrence-original>
                            <div class="occurrence-when-grid">
                                <label><?php echo stridebr_e(stridebr_t('schedule.planned_date')); ?><input type="date" name="nova_data" required data-occurrence-new-date></label>
                                <label class="time24-field-label"><?php echo stridebr_e(stridebr_t('schedule.planned_time')); ?><div class="time24-control" data-time24><div class="time24-input-row"><input type="text" inputmode="numeric" maxlength="2" required data-time24-hours><span class="time24-separator">:</span><input type="text" inputmode="numeric" maxlength="2" required data-time24-minutes><button type="button" class="time24-toggle" data-time24-toggle aria-label="<?php echo stridebr_e(stridebr_t('agenda.choose_time')); ?>">⌄</button></div><div class="time24-menu" data-time24-menu hidden><div class="time24-menu-head"><span><?php echo stridebr_e(stridebr_t('schedule.time_24h')); ?></span><button type="button" data-time24-now><?php echo stridebr_e(stridebr_t('common.now')); ?></button></div><div class="time24-hours-grid" data-time24-hours-grid></div><div class="time24-minutes-grid" data-time24-minutes-grid></div></div><input type="hidden" name="hora_inicio" value="18:00" data-time24-value data-occurrence-new-time></div></label>
                            </div>
                            <fieldset class="occurrence-scope"><legend><?php echo stridebr_e(stridebr_t('schedule.apply_to')); ?></legend><label><input type="radio" name="scope" value="this" checked><span><strong><?php echo stridebr_e(stridebr_t('schedule.this_only')); ?></strong><small><?php echo stridebr_e(stridebr_t('schedule.next_week_unchanged')); ?></small></span></label><label><input type="radio" name="scope" value="future"><span><strong><?php echo stridebr_e(stridebr_t('schedule.this_future')); ?></strong><small><?php echo stridebr_e(stridebr_t('schedule.new_phase_help')); ?></small></span></label><label><input type="radio" name="scope" value="all"><span><strong><?php echo stridebr_e(stridebr_t('schedule.all_occurrences')); ?></strong><small><?php echo stridebr_e(stridebr_t('schedule.change_entire_program')); ?></small></span></label></fieldset>
                            <div class="form-actions"><button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('schedule.save_reschedule')); ?></button><button type="button" class="secondary-button" data-skip-occurrence><?php echo stridebr_e(stridebr_t('schedule.skip_day')); ?></button><button type="button" class="secondary-button" data-close-occurrence><?php echo stridebr_e(stridebr_t('common.cancel')); ?></button></div>
                        </form>
                    </section>
                </div>

                <div class="occurrence-history-editor" data-occurrence-history-modal hidden>
                    <button type="button" class="occurrence-editor-backdrop" data-close-occurrence-history aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>"></button>
                    <section class="occurrence-editor-dialog occurrence-history-dialog" role="dialog" aria-modal="true" aria-labelledby="occurrence-history-title">
                        <header><div><span class="eyebrow"><?php echo stridebr_e(stridebr_t('schedule.planned_actual')); ?></span><h2 id="occurrence-history-title"><?php echo stridebr_e(stridebr_t('schedule.fix_dates_times')); ?></h2><p><?php echo stridebr_e(stridebr_t('schedule.history_independent_help')); ?></p></div><button type="button" class="icon-button" data-close-occurrence-history aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button></header>
                        <form data-occurrence-history-form>
                            <?php echo stridebr_csrf_field(); ?><input type="hidden" name="idregistro" data-history-activity-id>
                            <div class="occurrence-history-grid">
                                <fieldset><legend><?php echo stridebr_e(stridebr_t('home.planned')); ?></legend><label><?php echo stridebr_e(stridebr_t('common.date')); ?><input type="date" name="data_planejada" required data-history-planned-date></label><label class="time24-field-label"><?php echo stridebr_e(stridebr_t('common.time')); ?><div class="time24-control" data-time24><div class="time24-input-row"><input type="text" inputmode="numeric" maxlength="2" data-time24-hours><span class="time24-separator">:</span><input type="text" inputmode="numeric" maxlength="2" data-time24-minutes><button type="button" class="time24-toggle" data-time24-toggle aria-label="<?php echo stridebr_e(stridebr_t('agenda.choose_time')); ?>">⌄</button></div><div class="time24-menu" data-time24-menu hidden><div class="time24-menu-head"><span><?php echo stridebr_e(stridebr_t('schedule.time_24h')); ?></span><button type="button" data-time24-now><?php echo stridebr_e(stridebr_t('common.now')); ?></button></div><div class="time24-hours-grid" data-time24-hours-grid></div><div class="time24-minutes-grid" data-time24-minutes-grid></div></div><input type="hidden" name="hora_planejada" data-time24-value data-history-planned-time></div></label></fieldset>
                                <fieldset><legend><?php echo stridebr_e(stridebr_t('schedule.actual')); ?></legend><label><?php echo stridebr_e(stridebr_t('common.date')); ?><input type="date" name="data_realizada" required data-history-realized-date></label><label class="time24-field-label"><?php echo stridebr_e(stridebr_t('common.time')); ?><div class="time24-control" data-time24><div class="time24-input-row"><input type="text" inputmode="numeric" maxlength="2" required data-time24-hours><span class="time24-separator">:</span><input type="text" inputmode="numeric" maxlength="2" required data-time24-minutes><button type="button" class="time24-toggle" data-time24-toggle aria-label="<?php echo stridebr_e(stridebr_t('agenda.choose_time')); ?>">⌄</button></div><div class="time24-menu" data-time24-menu hidden><div class="time24-menu-head"><span><?php echo stridebr_e(stridebr_t('schedule.time_24h')); ?></span><button type="button" data-time24-now><?php echo stridebr_e(stridebr_t('common.now')); ?></button></div><div class="time24-hours-grid" data-time24-hours-grid></div><div class="time24-minutes-grid" data-time24-minutes-grid></div></div><input type="hidden" name="hora_realizada" data-time24-value data-history-realized-time></div></label></fieldset>
                            </div>
                            <div class="form-actions"><button type="button" class="secondary-button" data-close-occurrence-history><?php echo stridebr_e(stridebr_t('common.cancel')); ?></button><button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('schedule.save_correction')); ?></button></div>
                        </form>
                    </section>
                </div>

                <?php if ($workoutSessionsEnabled): ?>
                <div class="quick-register-workout" data-quick-register-modal hidden>
                    <button type="button" class="quick-register-backdrop" data-close-quick-register aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>"></button>
                    <section class="quick-register-dialog" role="dialog" aria-modal="true" aria-labelledby="quick-register-title">
                        <header>
                            <div><span class="eyebrow"><?php echo stridebr_e(stridebr_t('schedule.quick_log')); ?></span><h2 id="quick-register-title" data-quick-register-title><?php echo stridebr_e(stridebr_t('schedule.log_workout')); ?></h2><p><?php echo stridebr_e(stridebr_t('schedule.quick_log_help')); ?></p></div>
                            <button type="button" class="icon-button" data-close-quick-register aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button>
                        </header>
                        <form data-quick-register-form>
                            <input type="hidden" name="idtreino" data-quick-register-id>
                            <div class="quick-register-grid">
                                <label><?php echo stridebr_e(stridebr_t('common.date')); ?><div class="quick-register-date-row"><input type="date" name="data" required data-quick-register-date><button type="button" data-quick-register-today><?php echo stridebr_e(stridebr_t('common.today')); ?></button></div></label>
                                <label class="time24-field-label"><?php echo stridebr_e(stridebr_t('schedule.start_time')); ?><div class="time24-control" data-time24><div class="time24-input-row"><input type="text" inputmode="numeric" maxlength="2" required data-time24-hours><span class="time24-separator">:</span><input type="text" inputmode="numeric" maxlength="2" required data-time24-minutes><button type="button" class="time24-toggle" data-time24-toggle aria-label="<?php echo stridebr_e(stridebr_t('agenda.choose_time')); ?>">⌄</button></div><div class="time24-menu" data-time24-menu hidden><div class="time24-menu-head"><span><?php echo stridebr_e(stridebr_t('schedule.time_24h')); ?></span><button type="button" data-time24-now><?php echo stridebr_e(stridebr_t('common.now')); ?></button></div><div class="time24-hours-grid" data-time24-hours-grid></div><div class="time24-minutes-grid" data-time24-minutes-grid></div></div><input type="hidden" name="hora" value="18:00" data-time24-value data-quick-register-time></div></label>
                                <div class="quick-register-duration-field"><div class="quick-register-duration-heading"><span><?php echo stridebr_e(stridebr_t('schedule.duration_optional')); ?> <small><?php echo stridebr_e(stridebr_t('common.optional')); ?></small></span><label><input type="checkbox" data-quick-register-no-duration> <?php echo stridebr_e(stridebr_t('schedule.do_not_log')); ?></label></div><div class="quick-register-duration-input"><input type="number" name="duracao_minutos" min="0" max="1440" inputmode="numeric" placeholder="60" data-quick-register-duration><span><?php echo stridebr_e(stridebr_t('schedule.duration_minutes_suffix')); ?></span></div></div>
                            </div>
                            <label><?php echo stridebr_e(stridebr_t('schedule.intensity')); ?><select name="intensidade"><option value=""><?php echo stridebr_e(stridebr_t('schedule.not_inform')); ?></option><option value="leve"><?php echo stridebr_e(stridebr_t('schedule.light')); ?></option><option value="moderado"><?php echo stridebr_e(stridebr_t('schedule.moderate')); ?></option><option value="intenso"><?php echo stridebr_e(stridebr_t('schedule.intense')); ?></option></select></label>
                            <label><?php echo stridebr_e(stridebr_t('schedule.notes')); ?><textarea name="observacoes" rows="2" maxlength="1000" placeholder="<?php echo stridebr_e(stridebr_t('schedule.optional_placeholder')); ?>"></textarea></label>
                            <div class="quick-register-note"><strong><?php echo stridebr_e(stridebr_t('schedule.full_log')); ?></strong><span><?php echo stridebr_e(stridebr_t('schedule.full_log_help')); ?></span></div>
                            <div class="quick-register-actions"><button type="button" class="secondary-button" data-close-quick-register><?php echo stridebr_e(stridebr_t('common.cancel')); ?></button><button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('schedule.save_day')); ?></button></div>
                        </form>
                    </section>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/time24.js')); ?>"></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/cronogramas.js')); ?>"></script>
</body>
</html>
