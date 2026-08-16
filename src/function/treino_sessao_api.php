<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/errors.php';
require_once dirname(__DIR__) . '/includes/app.php';

$idUsuario = stridebr_require_login();
require_once dirname(__DIR__) . '/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/atividade_modelo.php';
require_once __DIR__ . '/cronograma.php';

header('Content-Type: application/json; charset=UTF-8');

function sessaoJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!stridebr_feature_enabled($pdo, 'workout_sessions.enabled', false)) {
    sessaoJson(['ok' => false, 'error' => 'A execução de treinos está temporariamente desativada.'], 503);
}

function sessaoCarregar(PDO $pdo, string $idUsuario): array
{
    $stmt = $pdo->prepare("SELECT s.*, c.nome AS cronograma_nome
        FROM sessoes_treino s
        LEFT JOIN cronogramas c ON c.idcronograma = s.idcronograma_origem
        WHERE s.idusuario = :usuario AND s.status = 'ativo'
        ORDER BY s.data_inicio DESC LIMIT 1");
    $stmt->execute([':usuario' => $idUsuario]);
    $session = $stmt->fetch();
    if (!$session) return [];

    $exerciseStmt = $pdo->prepare('SELECT * FROM sessoes_treino_exercicios WHERE idsessao = :sessao ORDER BY ordem');
    $exerciseStmt->execute([':sessao' => $session['idsessao']]);
    $session['exercicios'] = $exerciseStmt->fetchAll();
    $seriesStmt = $pdo->prepare('SELECT * FROM sessoes_treino_series WHERE idsessao_exercicio = :exercicio ORDER BY numero');
    foreach ($session['exercicios'] as &$exercise) {
        $seriesStmt->execute([':exercicio' => $exercise['idsessao_exercicio']]);
        $exercise['series'] = $seriesStmt->fetchAll();
        $exercise['concluido'] = stridebr_db_bool($exercise['concluido']);
        foreach ($exercise['series'] as &$set) $set['concluida'] = stridebr_db_bool($set['concluida']);
        unset($set);
    }
    unset($exercise);
    return $session;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'current');

try {
    if ($method === 'GET' && $action === 'current') {
        sessaoJson(['ok' => true, 'session' => sessaoCarregar($pdo, $idUsuario) ?: null]);
    }

    if ($method !== 'POST') sessaoJson(['ok' => false, 'message' => 'Método inválido.'], 405);
    stridebr_verify_csrf();

    if ($action === 'start') {
        $existing = sessaoCarregar($pdo, $idUsuario);
        if ($existing !== []) sessaoJson(['ok' => false, 'message' => 'Você já tem um treino em andamento.', 'session' => $existing], 409);

        $idTreino = trim((string) ($_POST['idtreino'] ?? ''));
        $treino = cronogramaBuscarTreino($pdo, $idTreino, $idUsuario);
        if ($treino === []) throw new RuntimeException('Treino não encontrado.');
        $exercicios = cronogramaListarTreinoExercicios($pdo, $idTreino, $idUsuario);
        $idSessao = atividadeGerarId();

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO sessoes_treino (idsessao, idusuario, idcronograma_origem, idtreino_origem, titulo_snapshot) VALUES (:id, :usuario, :cronograma, :treino, :titulo)');
            $stmt->execute([':id' => $idSessao, ':usuario' => $idUsuario, ':cronograma' => $treino['idcronograma'], ':treino' => $idTreino, ':titulo' => $treino['titulo']]);
            $insertExercise = $pdo->prepare('INSERT INTO sessoes_treino_exercicios (idsessao_exercicio, idsessao, idexercicio, nome_snapshot, series_planejadas, repeticoes_snapshot, carga_snapshot, descanso_snapshot, observacoes_snapshot, ordem) VALUES (:id, :sessao, :exercicio, :nome, :series, :repeticoes, :carga, :descanso, :observacoes, :ordem)');
            $insertSet = $pdo->prepare('INSERT INTO sessoes_treino_series (idserie, idsessao_exercicio, numero) VALUES (:id, :exercicio, :numero)');
            foreach ($exercicios as $row) {
                $idSessaoExercicio = atividadeGerarId();
                $plannedSets = isset($row['series']) && is_numeric($row['series']) ? max(1, min(99, (int) $row['series'])) : 1;
                $insertExercise->execute([
                    ':id' => $idSessaoExercicio,
                    ':sessao' => $idSessao,
                    ':exercicio' => $row['idexercicio'] ?: null,
                    ':nome' => $row['nome_snapshot'],
                    ':series' => isset($row['series']) && is_numeric($row['series']) ? (int) $row['series'] : null,
                    ':repeticoes' => $row['repeticoes'] ?: null,
                    ':carga' => $row['carga'] ?: null,
                    ':descanso' => $row['descanso'] ?: null,
                    ':observacoes' => $row['observacoes'] ?: null,
                    ':ordem' => (int) $row['ordem'],
                ]);
                for ($number = 1; $number <= $plannedSets; $number++) $insertSet->execute([':id' => atividadeGerarId(), ':exercicio' => $idSessaoExercicio, ':numero' => $number]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        sessaoJson(['ok' => true, 'session' => sessaoCarregar($pdo, $idUsuario)]);
    }

    if ($action === 'toggle_set') {
        $idSerie = trim((string) ($_POST['idserie'] ?? ''));
        $done = stridebr_db_bool($_POST['concluida'] ?? false);
        $stmt = $pdo->prepare("UPDATE sessoes_treino_series st
            SET concluida = :done, data_conclusao = CASE WHEN :done2 THEN NOW() ELSE NULL END
            FROM sessoes_treino_exercicios se
            JOIN sessoes_treino s ON s.idsessao = se.idsessao
            WHERE st.idsessao_exercicio = se.idsessao_exercicio AND st.idserie = :serie AND s.idusuario = :usuario AND s.status = 'ativo'");
        $stmt->bindValue(':done', $done, PDO::PARAM_BOOL);
        $stmt->bindValue(':done2', $done, PDO::PARAM_BOOL);
        $stmt->bindValue(':serie', $idSerie);
        $stmt->bindValue(':usuario', $idUsuario);
        $stmt->execute();
        if ($stmt->rowCount() !== 1) throw new RuntimeException('Série não encontrada.');
        $pdo->prepare("UPDATE sessoes_treino_exercicios se SET concluido = NOT EXISTS (SELECT 1 FROM sessoes_treino_series st WHERE st.idsessao_exercicio = se.idsessao_exercicio AND st.concluida = FALSE) WHERE se.idsessao = (SELECT idsessao FROM sessoes_treino WHERE idusuario = :usuario AND status = 'ativo' LIMIT 1)")->execute([':usuario' => $idUsuario]);
        sessaoJson(['ok' => true, 'session' => sessaoCarregar($pdo, $idUsuario)]);
    }

    if ($action === 'toggle_exercise') {
        $id = trim((string) ($_POST['idsessao_exercicio'] ?? ''));
        $done = stridebr_db_bool($_POST['concluido'] ?? false);
        $owner = $pdo->prepare("SELECT se.idsessao FROM sessoes_treino_exercicios se JOIN sessoes_treino s ON s.idsessao = se.idsessao WHERE se.idsessao_exercicio = :id AND s.idusuario = :usuario AND s.status = 'ativo'");
        $owner->execute([':id' => $id, ':usuario' => $idUsuario]);
        if (!$owner->fetchColumn()) throw new RuntimeException('Exercício não encontrado.');
        $stmt = $pdo->prepare('UPDATE sessoes_treino_exercicios SET concluido = :done WHERE idsessao_exercicio = :id');
        $stmt->bindValue(':done', $done, PDO::PARAM_BOOL);
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        $sets = $pdo->prepare('UPDATE sessoes_treino_series SET concluida = :done, data_conclusao = CASE WHEN :done2 THEN COALESCE(data_conclusao, NOW()) ELSE NULL END WHERE idsessao_exercicio = :id');
        $sets->bindValue(':done', $done, PDO::PARAM_BOOL);
        $sets->bindValue(':done2', $done, PDO::PARAM_BOOL);
        $sets->bindValue(':id', $id);
        $sets->execute();
        sessaoJson(['ok' => true, 'session' => sessaoCarregar($pdo, $idUsuario)]);
    }

    if ($action === 'cancel') {
        $stmt = $pdo->prepare("UPDATE sessoes_treino SET status = 'cancelado', data_fim = NOW(), data_atualizacao = NOW() WHERE idusuario = :usuario AND status = 'ativo'");
        $stmt->execute([':usuario' => $idUsuario]);
        sessaoJson(['ok' => true, 'session' => null]);
    }

    if ($action === 'finish') {
        $session = sessaoCarregar($pdo, $idUsuario);
        if ($session === []) throw new RuntimeException('Nenhum treino em andamento.');
        $start = new DateTimeImmutable((string) $session['data_inicio']);
        $end = new DateTimeImmutable('now');
        $duration = max(1, $end->getTimestamp() - $start->getTimestamp());
        $hours = intdiv($duration, 3600);
        $minutes = intdiv($duration % 3600, 60);
        $seconds = $duration % 60;
        $durationText = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

        $muscStmt = $pdo->prepare("SELECT
            EXISTS (SELECT 1 FROM treinos_exercicios te JOIN exercicios_modalidades em ON em.idexercicio = te.idexercicio WHERE te.idtreino = :treino_musc AND em.idmodalidade = 'm_musculacao') AS musculacao,
            EXISTS (SELECT 1 FROM treinos_exercicios te JOIN exercicios_modalidades em ON em.idexercicio = te.idexercicio WHERE te.idtreino = :treino_cal AND em.idmodalidade = 'm_calistenia') AS calistenia");
        $muscStmt->execute([':treino_musc' => $session['idtreino_origem'], ':treino_cal' => $session['idtreino_origem']]);
        $kind = $muscStmt->fetch() ?: [];
        if (stridebr_db_bool($kind['musculacao'] ?? false)) {
            $model = 'md_musculacao'; $durationField = 'f_musc_dur';
        } elseif (stridebr_db_bool($kind['calistenia'] ?? false)) {
            $model = 'md_calistenia'; $durationField = 'f_cal_dur';
        } else {
            $model = 'md_geral'; $durationField = 'f_ger_dur';
        }

        $intensidade = trim((string) ($_POST['intensidade'] ?? ''));
        if (!in_array($intensidade, ['', 'leve', 'moderado', 'intenso'], true)) throw new InvalidArgumentException('Intensidade inválida.');
        $feelingRaw = trim((string) ($_POST['feeling'] ?? ''));
        $feeling = $feelingRaw === '' ? null : filter_var($feelingRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
        if ($feelingRaw !== '' && $feeling === false) throw new InvalidArgumentException('Sensação inválida.');
        $feedback = trim((string) ($_POST['observacoes'] ?? ''));
        if (stridebr_length($feedback) > 1000) throw new InvalidArgumentException('As observações são muito longas.');

        $totalExercises = count($session['exercicios']);
        $doneExercises = count(array_filter($session['exercicios'], fn(array $row): bool => !empty($row['concluido'])));
        $totalSets = 0; $doneSets = 0;
        foreach ($session['exercicios'] as $exercise) {
            $totalSets += count($exercise['series']);
            $doneSets += count(array_filter($exercise['series'], fn(array $set): bool => !empty($set['concluida'])));
        }
        $notesParts = ["Treino iniciado pelo cronograma. {$doneExercises}/{$totalExercises} exercícios e {$doneSets}/{$totalSets} séries concluídos."];
        if ($feeling !== null) $notesParts[] = 'Sensação: ' . $feeling . '/5.';
        if ($feedback !== '') $notesParts[] = $feedback;
        $notes = implode("
", $notesParts);

        $fieldPrefix = match ($model) {
            'md_musculacao' => 'musc',
            'md_calistenia' => 'cal',
            default => 'ger',
        };
        $recordValues = [];
        if ($intensidade !== '') {
            $intensityField = 'f_' . $fieldPrefix . '_int';
            $optionStmt = $pdo->prepare('SELECT idopcao FROM campos_modelo_opcoes WHERE idcampo = :campo AND valor = :valor AND ativo = TRUE LIMIT 1');
            $optionStmt->execute([':campo' => $intensityField, ':valor' => $intensidade]);
            $optionId = $optionStmt->fetchColumn();
            if ($optionId !== false) $recordValues[$intensityField] = $optionId;
        }
        $feedbackField = 'f_' . $fieldPrefix . '_obs';
        $feedbackParts = [];
        if ($feeling !== null) $feedbackParts[] = 'Sensação: ' . $feeling . '/5';
        if ($feedback !== '') $feedbackParts[] = $feedback;
        if ($feedbackParts !== []) $recordValues[$feedbackField] = implode("
", $feedbackParts);

        $tz = new DateTimeZone('America/Sao_Paulo');
        $activityId = atividadeSalvarRegistro($pdo, $idUsuario, [
            'idmodelo' => $model,
            'titulo' => $session['titulo_snapshot'],
            'observacoes' => $notes,
            'data_inicio' => $start->setTimezone($tz)->format('Y-m-d H:i'),
            'data_fim' => $end->setTimezone($tz)->format('Y-m-d H:i'),
            'status' => 'concluido',
            'visibilidade' => 'privado',
            'idcronograma' => $session['idcronograma_origem'],
            'idtreino_cronograma' => $session['idtreino_origem'],
            'record_values' => $recordValues,
            'unidades' => [[
                'rotulo' => 'Sessão',
                'values' => [$durationField => $durationText],
            ]],
        ]);

        $stmt = $pdo->prepare("UPDATE sessoes_treino SET status = 'concluido', data_fim = NOW(), idregistro_atividade = :registro, data_atualizacao = NOW() WHERE idsessao = :sessao AND idusuario = :usuario");
        $stmt->execute([':registro' => $activityId, ':sessao' => $session['idsessao'], ':usuario' => $idUsuario]);
        sessaoJson(['ok' => true, 'session' => null, 'activity_id' => $activityId]);
    }

    sessaoJson(['ok' => false, 'message' => 'Ação inválida.'], 400);
} catch (Throwable $e) {
    error_log('Workout session API: ' . $e->getMessage());
    $message = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível atualizar o treino.';
    sessaoJson(['ok' => false, 'message' => $message], 400);
}
