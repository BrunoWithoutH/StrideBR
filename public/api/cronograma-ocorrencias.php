<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma.php';
require_once dirname(__DIR__, 2) . '/src/function/treinador.php';
require_once dirname(__DIR__, 2) . '/src/function/notificacoes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

$notifyWorkoutSchedule = static function (PDO $pdo, string $ownerId, string $workoutId, string $message): void {
    if ($workoutId === '') return;
    $workout = cronogramaBuscarTreino($pdo, $workoutId, $ownerId);
    if ($workout === []) return;
    notificacaoCronogramaSincronizadoAlterado($pdo, $ownerId, (string) ($workout['idcronograma'] ?? ''), $message);
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') stridebr_session_release();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        stridebr_verify_csrf();
        $action = trim((string) ($_POST['action'] ?? ''));
        $idTreino = trim((string) ($_POST['idtreino'] ?? ''));
        $dataOriginal = trim((string) ($_POST['data_original'] ?? ''));
        if ($action === 'move') {
            $result = cronogramaAlterarOcorrencia($pdo, $idUsuario, $idTreino, $dataOriginal, trim((string) ($_POST['data_treino'] ?? '')), trim((string) ($_POST['scope'] ?? 'this')), isset($_POST['hora_inicio']) ? trim((string) $_POST['hora_inicio']) : null);
            $notifyWorkoutSchedule($pdo, $idUsuario, $idTreino, 'Uma ocorrência de treino foi reagendada.');
            echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($action === 'cancel') {
            cronogramaCancelarOcorrencia($pdo, $idUsuario, $idTreino, $dataOriginal);
            $notifyWorkoutSchedule($pdo, $idUsuario, $idTreino, 'Uma ocorrência de treino foi removida.');
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($action === 'swap') {
            $result = cronogramaTrocarOcorrencias(
                $pdo,
                $idUsuario,
                $idTreino,
                $dataOriginal,
                trim((string) ($_POST['outro_idtreino'] ?? '')),
                trim((string) ($_POST['outra_data_original'] ?? ''))
            );
            $notifyWorkoutSchedule($pdo, $idUsuario, $idTreino, 'Duas ocorrências de treino foram reorganizadas.');
            $otherWorkoutId = trim((string) ($_POST['outro_idtreino'] ?? ''));
            if ($otherWorkoutId !== '' && $otherWorkoutId !== $idTreino) $notifyWorkoutSchedule($pdo, $idUsuario, $otherWorkoutId, 'Duas ocorrências de treino foram reorganizadas.');
            echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($action === 'edit_workout') {
            $scope = trim((string) ($_POST['scope'] ?? 'all'));
            $result = cronogramaEditarTreinoEscopo($pdo, $idUsuario, $idTreino, $dataOriginal, $scope, $_POST);
            $notifyWorkoutSchedule($pdo, $idUsuario, $idTreino, 'Um treino foi atualizado.');
            echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($action === 'set_planned_date') {
            $result = cronogramaCorrigirPlanejamentoRegistro(
                $pdo,
                $idUsuario,
                trim((string) ($_POST['idregistro'] ?? '')),
                trim((string) ($_POST['data_planejada'] ?? '')),
                trim((string) ($_POST['hora_planejada'] ?? '')) ?: null
            );
            echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($action === 'set_occurrence_history') {
            $result = cronogramaAjustarHistoricoRegistro(
                $pdo,
                $idUsuario,
                trim((string) ($_POST['idregistro'] ?? '')),
                trim((string) ($_POST['data_planejada'] ?? '')),
                trim((string) ($_POST['hora_planejada'] ?? '')) ?: null,
                trim((string) ($_POST['data_realizada'] ?? '')) ?: null,
                trim((string) ($_POST['hora_realizada'] ?? '')) ?: null
            );
            echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($action === 'set_next') {
            $idCronograma = trim((string) ($_POST['idcronograma'] ?? ''));
            $semanaInicio = trim((string) ($_POST['semana_inicio'] ?? ''));
            $idPreferido = trim((string) ($_POST['idtreino_proximo'] ?? '')) ?: null;
            cronogramaPreferenciaSemanalSalvar($pdo, $idUsuario, $idCronograma, $semanaInicio, $idPreferido);
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($action === 'add_library') {
            $idModelo = trim((string) ($_POST['idtreino_modelo'] ?? ''));
            $idCronograma = trim((string) ($_POST['idcronograma'] ?? ''));
            $dataTreino = trim((string) ($_POST['data_treino'] ?? ''));
            $date = cronogramaValidarDataIso($dataTreino);
            $horaInicio = trim((string) ($_POST['hora_inicio'] ?? '18:00'));
            if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $horaInicio)) throw new InvalidArgumentException('Horário inválido.');
            $horaFim = (new DateTimeImmutable($dataTreino . ' ' . $horaInicio))->modify('+1 hour')->format('H:i');
            $idCriado = cronogramaAdicionarTreinoModeloAoCronograma($pdo, $idUsuario, $idModelo, $idCronograma, (int) $date->format('w'), $horaInicio, $horaFim, false, $dataTreino, null);
            notificacaoCronogramaSincronizadoAlterado($pdo, $idUsuario, $idCronograma, 'Um treino salvo foi adicionado ao cronograma.');
            echo json_encode(['ok' => true, 'result' => ['idtreino' => $idCriado]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        throw new InvalidArgumentException('Ação inválida.');
    }

    $start = trim((string) ($_GET['start'] ?? ''));
    $end = trim((string) ($_GET['end'] ?? ''));
    $schedule = trim((string) ($_GET['schedule'] ?? ''));
    $targetId = trim((string) ($_GET['atleta'] ?? $idUsuario));
    if ($targetId === '') $targetId = $idUsuario;
    if ($targetId !== $idUsuario) {
        if (!stridebr_feature_enabled($pdo, 'trainer.enabled', false)) throw new RuntimeException('Agenda indisponível.');
        $link = treinadorVinculoAceito($pdo, $idUsuario, $targetId);
        if ($link === [] || !stridebr_db_bool($link['pode_ver_cronograma'] ?? false)) throw new RuntimeException('Sem permissão para ver esta agenda.');
    }
    if ($schedule !== '' && cronogramaBuscar($pdo, $schedule, $targetId) === []) throw new RuntimeException('Cronograma não encontrado.');
    $items = cronogramaListarOcorrenciasConciliadas($pdo, $targetId, $start, $end, $schedule !== '' ? $schedule : null);
    $scheduled = [];
    if (stridebr_feature_enabled($pdo, 'monthly_calendar.enabled', false)) {
        $sql = "SELECT ta.idagendamento, ta.idcronograma_origem, ta.idtreino_origem, ta.data_treino, ta.hora_inicio, ta.titulo, ta.origem, ta.status,
                       COALESCE(NULLIF(u.nome_exibicao, ''), u.nomeusuario) AS criador_nome,
                       (SELECT COUNT(*) FROM treinos_agendados_exercicios tae WHERE tae.idagendamento = ta.idagendamento) AS exercicios_total
                FROM treinos_agendados ta
                LEFT JOIN usuarios u ON u.idusuario = ta.idcriador
                WHERE ta.idatleta = :atleta AND ta.data_treino BETWEEN :inicio AND :fim AND ta.status <> 'cancelado'
                  AND (ta.status IN ('publicado','concluido') OR ta.idcriador = :viewer)";
        $params = [':atleta' => $targetId, ':inicio' => $start, ':fim' => $end, ':viewer' => $idUsuario];
        if ($schedule !== '') {
            $sql .= ' AND (ta.idcronograma_origem IS NULL OR ta.idcronograma_origem = :cronograma)';
            $params[':cronograma'] = $schedule;
        }
        $sql .= ' ORDER BY ta.data_treino, ta.hora_inicio NULLS LAST, ta.data_criacao';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $scheduled = $stmt->fetchAll();
    }
    $output = array_map(static function (array $item): array {
        return [
            'idtreino' => (string) $item['idtreino'], 'idcronograma' => (string) $item['idcronograma'], 'cronograma_nome' => (string) $item['cronograma_nome'],
            'titulo' => (string) $item['titulo'], 'codigo' => (string) ($item['codigo'] ?? ''), 'foco' => (string) ($item['foco'] ?? ''), 'idmodalidade' => (string) ($item['idmodalidade'] ?? ''),
            'data_original' => (string) $item['data_original'], 'data_treino' => (string) $item['data_treino'], 'hora_inicio' => substr((string) $item['hora_inicio'], 0, 5),
            'hora_fim' => substr((string) $item['hora_fim'], 0, 5), 'termina_dia_seguinte' => stridebr_db_bool($item['termina_dia_seguinte'] ?? false), 'excecao' => !empty($item['excecao']),
            'concluido' => !empty($item['concluido']), 'idregistro' => (string) ($item['idregistro'] ?? ''),
            'data_planejada' => (string) ($item['data_planejada'] ?? $item['data_treino']), 'hora_planejada' => (string) ($item['hora_planejada'] ?? substr((string) $item['hora_inicio'], 0, 5)),
            'data_realizada' => (string) ($item['data_realizada'] ?? ''), 'hora_realizada' => (string) ($item['hora_realizada'] ?? ''),
            'realizado_fora_planejado' => !empty($item['realizado_fora_planejado']),
        ];
    }, $items);
    $scheduledOutput = array_map(static fn(array $row): array => [
        'idagendamento' => (string) $row['idagendamento'], 'data_treino' => (string) $row['data_treino'], 'hora_inicio' => $row['hora_inicio'] ? substr((string) $row['hora_inicio'], 0, 5) : null,
        'titulo' => (string) $row['titulo'], 'origem' => (string) $row['origem'], 'status' => (string) $row['status'], 'criador_nome' => (string) ($row['criador_nome'] ?? ''), 'exercicios_total' => (int) $row['exercicios_total'],
    ], $scheduled);
    echo json_encode(['ok' => true, 'ocorrencias' => $output, 'agendados' => $scheduledOutput], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 400 : ($e instanceof RuntimeException ? 404 : 500));
    if (!$e instanceof InvalidArgumentException && !$e instanceof RuntimeException) error_log('StrideBR schedule occurrence API: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível atualizar a agenda.'], JSON_UNESCAPED_UNICODE);
}
