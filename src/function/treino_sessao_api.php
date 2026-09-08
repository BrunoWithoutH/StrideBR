<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/errors.php';
require_once dirname(__DIR__) . '/includes/app.php';

$idUsuario = stridebr_require_login();
require_once dirname(__DIR__) . '/config/pg_config.php';
require_once __DIR__ . '/atividade_modelo.php';
require_once __DIR__ . '/cronograma.php';
require_once __DIR__ . '/product_analytics.php';
require_once __DIR__ . '/workout_load.php';

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

function sessaoCargaNumero(?string $valor): ?float
{
    $text = trim((string) $valor);
    if ($text === '') return null;
    if (!preg_match('/-?\d+(?:[.,]\d+)?/', $text, $match)) return null;
    return (float) str_replace(',', '.', $match[0]);
}

function sessaoMontarHistoricoExercicio(array $previous, array $setRows): array
{
    if ($previous === []) return [];

    $latest = $previous[0];
    $latestSets = $setRows[(string) $latest['idsessao_exercicio']] ?? [];
    $done = count(array_filter($latestSets, static fn(array $row): bool => stridebr_db_bool($row['concluida'] ?? false)));
    $reps = '';
    $load = '';
    foreach ($latestSets as $set) {
        if ($reps === '' && trim((string) ($set['repeticoes_realizadas'] ?? '')) !== '') $reps = trim((string) $set['repeticoes_realizadas']);
        if ($load === '' && trim((string) ($set['carga_realizada'] ?? '')) !== '') $load = trim((string) $set['carga_realizada']);
    }
    if ($reps === '') $reps = trim((string) ($latest['repeticoes_snapshot'] ?? ''));
    if ($load === '') $load = trim((string) ($latest['carga_snapshot'] ?? ''));

    $bestNumber = null;
    $bestLabel = '';
    foreach ($previous as $row) {
        $candidateLabels = [];
        foreach ($setRows[(string) $row['idsessao_exercicio']] ?? [] as $set) {
            $candidate = trim((string) ($set['carga_realizada'] ?? ''));
            if ($candidate !== '') $candidateLabels[] = $candidate;
        }
        $snapshot = trim((string) ($row['carga_snapshot'] ?? ''));
        if ($snapshot !== '') $candidateLabels[] = $snapshot;
        foreach ($candidateLabels as $candidate) {
            $number = sessaoCargaNumero($candidate);
            if ($number !== null && ($bestNumber === null || $number > $bestNumber)) {
                $bestNumber = $number;
                $bestLabel = $candidate;
            }
        }
    }

    $date = new DateTimeImmutable((string) ($latest['data_fim'] ?: $latest['data_inicio']));
    return [
        'ultima' => [
            'data' => $date->format('d/m'),
            'series_concluidas' => $done,
            'series_total' => count($latestSets),
            'repeticoes' => $reps,
            'carga' => $load,
            'series' => array_map(static fn(array $set): array => [
                'numero' => (int) ($set['numero'] ?? 0),
                'repeticoes' => trim((string) ($set['repeticoes_realizadas'] ?? '')),
                'carga' => trim((string) ($set['carga_realizada'] ?? '')),
                'concluida' => stridebr_db_bool($set['concluida'] ?? false),
            ], $latestSets),
        ],
        'melhor_carga' => $bestLabel !== '' ? $bestLabel : null,
    ];
}

function sessaoHistoricoExercicios(PDO $pdo, string $idUsuario, string $idSessaoAtual, array $exercises): array
{
    if ($exercises === []) return [];

    $currentById = [];
    $currentByName = [];
    foreach ($exercises as $exercise) {
        $currentId = (string) ($exercise['idsessao_exercicio'] ?? '');
        if ($currentId === '') continue;
        $exerciseId = trim((string) ($exercise['idexercicio'] ?? ''));
        $name = stridebr_lower(trim((string) ($exercise['nome_snapshot'] ?? '')));
        if ($exerciseId !== '') $currentById[$exerciseId][] = $currentId;
        elseif ($name !== '') $currentByName[$name][] = $currentId;
    }
    if ($currentById === [] && $currentByName === []) return [];

    $conditions = [];
    $params = [':usuario' => $idUsuario, ':sessao_atual' => $idSessaoAtual];
    if ($currentById !== []) {
        $keys = [];
        foreach (array_keys($currentById) as $index => $id) {
            $key = ':exercise_id_' . $index;
            $keys[] = $key;
            $params[$key] = $id;
        }
        $conditions[] = 'se.idexercicio IN (' . implode(', ', $keys) . ')';
    }
    if ($currentByName !== []) {
        $keys = [];
        foreach (array_keys($currentByName) as $index => $name) {
            $key = ':exercise_name_' . $index;
            $keys[] = $key;
            $params[$key] = $name;
        }
        $conditions[] = 'lower(se.nome_snapshot) IN (' . implode(', ', $keys) . ')';
    }

    $stmt = $pdo->prepare("SELECT se.idsessao_exercicio, se.idexercicio, se.nome_snapshot, se.repeticoes_snapshot, se.carga_snapshot, s.data_inicio, s.data_fim
        FROM sessoes_treino_exercicios se
        JOIN sessoes_treino s ON s.idsessao = se.idsessao
        WHERE s.idusuario = :usuario
          AND s.status = 'concluido'
          AND s.idsessao <> :sessao_atual
          AND (" . implode(' OR ', $conditions) . ")
        ORDER BY COALESCE(s.data_fim, s.data_inicio) DESC
        LIMIT 600");
    $stmt->execute($params);

    $previousByCurrent = [];
    foreach ($stmt->fetchAll() as $row) {
        $targets = [];
        $rowExerciseId = trim((string) ($row['idexercicio'] ?? ''));
        if ($rowExerciseId !== '' && isset($currentById[$rowExerciseId])) {
            $targets = array_merge($targets, $currentById[$rowExerciseId]);
        }
        $rowName = stridebr_lower(trim((string) ($row['nome_snapshot'] ?? '')));
        if ($rowName !== '' && isset($currentByName[$rowName])) {
            $targets = array_merge($targets, $currentByName[$rowName]);
        }
        foreach (array_unique($targets) as $currentId) {
            if (count($previousByCurrent[$currentId] ?? []) < 20) $previousByCurrent[$currentId][] = $row;
        }
    }
    if ($previousByCurrent === []) return [];

    $historicalIds = [];
    foreach ($previousByCurrent as $rows) {
        foreach ($rows as $row) $historicalIds[(string) $row['idsessao_exercicio']] = true;
    }
    $setRows = [];
    $ids = array_keys($historicalIds);
    if ($ids !== []) {
        $placeholders = [];
        $setParams = [];
        foreach ($ids as $index => $id) {
            $key = ':history_set_' . $index;
            $placeholders[] = $key;
            $setParams[$key] = $id;
        }
        $setStmt = $pdo->prepare('SELECT idsessao_exercicio, numero, concluida, repeticoes_realizadas, carga_realizada FROM sessoes_treino_series WHERE idsessao_exercicio IN (' . implode(', ', $placeholders) . ') ORDER BY idsessao_exercicio, numero');
        $setStmt->execute($setParams);
        foreach ($setStmt->fetchAll() as $row) $setRows[(string) $row['idsessao_exercicio']][] = $row;
    }

    $result = [];
    foreach ($previousByCurrent as $currentId => $rows) {
        $result[$currentId] = sessaoMontarHistoricoExercicio($rows, $setRows);
    }
    return $result;
}

function sessaoCarregar(PDO $pdo, string $idUsuario, bool $includeHistory = true): array
{
    $stmt = $pdo->prepare("SELECT s.*, c.nome AS cronograma_nome
        FROM sessoes_treino s
        LEFT JOIN cronogramas c ON c.idcronograma = s.idcronograma_origem
        WHERE s.idusuario = :usuario AND s.status = 'ativo'
        ORDER BY s.data_inicio DESC LIMIT 1");
    $stmt->execute([':usuario' => $idUsuario]);
    $session = $stmt->fetch();
    if (!$session) return [];

    // PostgreSQL TIMESTAMPTZ is serialized explicitly for Safari and restored PWAs.
    try {
        if (trim((string) ($session['data_inicio'] ?? '')) === '') throw new UnexpectedValueException('Missing session start');
        $started = new DateTimeImmutable((string) $session['data_inicio']);
        $session['started_at_ms'] = $started->getTimestamp() * 1000;
        $session['data_inicio'] = $started->format(DateTimeInterface::ATOM);
    } catch (Throwable $e) {
        $session['started_at_ms'] = null;
    }

    $exerciseStmt = $pdo->prepare('SELECT * FROM sessoes_treino_exercicios WHERE idsessao = :sessao ORDER BY ordem');
    $exerciseStmt->execute([':sessao' => $session['idsessao']]);
    $session['exercicios'] = $exerciseStmt->fetchAll();
    $seriesByExercise = [];
    if ($session['exercicios'] !== []) {
        $exerciseIds = array_values(array_map(static fn(array $row): string => (string) $row['idsessao_exercicio'], $session['exercicios']));
        $placeholders = [];
        $params = [];
        foreach ($exerciseIds as $index => $idExercise) {
            $key = ':ex_' . $index;
            $placeholders[] = $key;
            $params[$key] = $idExercise;
        }
        $seriesStmt = $pdo->prepare('SELECT * FROM sessoes_treino_series WHERE idsessao_exercicio IN (' . implode(', ', $placeholders) . ') ORDER BY idsessao_exercicio, numero');
        $seriesStmt->execute($params);
        foreach ($seriesStmt->fetchAll() as $set) {
            $set['concluida'] = stridebr_db_bool($set['concluida']);
            $seriesByExercise[(string) $set['idsessao_exercicio']][] = $set;
        }
    }
    $historyByExercise = $includeHistory ? sessaoHistoricoExercicios($pdo, $idUsuario, (string) $session['idsessao'], $session['exercicios']) : [];
    foreach ($session['exercicios'] as &$exercise) {
        $exercise['series'] = $seriesByExercise[(string) $exercise['idsessao_exercicio']] ?? [];
        $exercise['concluido'] = stridebr_db_bool($exercise['concluido']);
        $exercise['historico'] = $historyByExercise[(string) $exercise['idsessao_exercicio']] ?? [];
    }
    unset($exercise);
    return $session;
}



function sessaoSerieRepeticoes(mixed $value): ?string
{
    $raw = trim((string) $value);
    if ($raw === '') return null;
    if (preg_match('/^\d{1,3}$/', $raw) !== 1) throw new InvalidArgumentException('Informe as repetições com um número inteiro.');
    $number = (int) $raw;
    if ($number < 0 || $number > 999) throw new InvalidArgumentException('Repetições fora do intervalo permitido.');
    return (string) $number;
}

function sessaoSerieCarga(mixed $value): ?string
{
    $raw = str_replace(',', '.', trim((string) $value));
    if ($raw === '') return null;
    if (preg_match('/^\d{1,4}(?:\.\d{1,3})?$/', $raw) !== 1) throw new InvalidArgumentException('Informe uma carga válida em kg.');
    $number = (float) $raw;
    if ($number < 0 || $number > 9999.999) throw new InvalidArgumentException('Carga fora do intervalo permitido.');
    return rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');
}

function sessaoPersistirSeriesAtividade(PDO $pdo, string $idRegistro, array $session): void
{
    $pdo->prepare('DELETE FROM series_exercicio_atividade WHERE idregistro = :registro')->execute([':registro' => $idRegistro]);
    $insert = $pdo->prepare(
        "INSERT INTO series_exercicio_atividade
        (idserie, idregistro, idexercicio, nome_exercicio, ordem_exercicio, ordem_serie, tipo, carga_kg, repeticoes, rir, rpe, concluida)
        VALUES (:id, :registro, :exercicio, :nome, :ordem_exercicio, :ordem_serie, 'trabalho', :carga, :reps, :rir, :rpe, :concluida)"
    );
    foreach ((array) ($session['exercicios'] ?? []) as $exercise) {
        $order = max(1, (int) ($exercise['ordem'] ?? 1));
        $rir = is_numeric($exercise['rir_snapshot'] ?? null) ? (float) $exercise['rir_snapshot'] : null;
        $rpe = is_numeric($exercise['rpe_snapshot'] ?? null) ? (float) $exercise['rpe_snapshot'] : null;
        foreach ((array) ($exercise['series'] ?? []) as $set) {
            $load = sessaoCargaNumero(isset($set['carga_realizada']) ? (string) $set['carga_realizada'] : null);
            $repsRaw = trim((string) ($set['repeticoes_realizadas'] ?? ''));
            $reps = preg_match('/^\d+$/', $repsRaw) === 1 ? (int) $repsRaw : null;
            $insert->execute([
                ':id' => atividadeGerarId(),
                ':registro' => $idRegistro,
                ':exercicio' => trim((string) ($exercise['idexercicio'] ?? '')) ?: null,
                ':nome' => (string) ($exercise['nome_snapshot'] ?? 'Exercício'),
                ':ordem_exercicio' => $order,
                ':ordem_serie' => max(1, (int) ($set['numero'] ?? 1)),
                ':carga' => $load,
                ':reps' => $reps,
                ':rir' => $rir,
                ':rpe' => $rpe,
                ':concluida' => stridebr_db_bool($set['concluida'] ?? false) ? 1 : 0,
            ]);
        }
    }
}

function sessaoModeloAtividade(PDO $pdo, string $idUsuario, ?string $idTreino): array
{
    $idModalidade = 'm_geral';

    if ($idTreino !== null && $idTreino !== '') {
        $workoutStmt = $pdo->prepare("SELECT COALESCE(tc.idmodalidade, tm.idmodalidade) AS idmodalidade, tc.titulo, tc.foco
            FROM treinos_cronograma tc
            LEFT JOIN treinos_modelo tm ON tm.idtreino_modelo = tc.idtreino_modelo
            WHERE tc.idtreino = :treino
            LIMIT 1");
        $workoutStmt->execute([':treino' => $idTreino]);
        $workoutKind = $workoutStmt->fetch() ?: [];
        $explicit = trim((string) ($workoutKind['idmodalidade'] ?? ''));
        if ($explicit !== '') {
            $idModalidade = $explicit;
        } else {
            $stmt = $pdo->prepare("SELECT
                EXISTS (SELECT 1 FROM treinos_exercicios te JOIN exercicios_modalidades em ON em.idexercicio = te.idexercicio WHERE te.idtreino = :treino_musc AND em.idmodalidade = 'm_musculacao') AS musculacao,
                EXISTS (SELECT 1 FROM treinos_exercicios te JOIN exercicios_modalidades em ON em.idexercicio = te.idexercicio WHERE te.idtreino = :treino_cal AND em.idmodalidade = 'm_calistenia') AS calistenia");
            $stmt->execute([':treino_musc' => $idTreino, ':treino_cal' => $idTreino]);
            $kind = $stmt->fetch() ?: [];
            $searchText = stridebr_lower(trim((string) ($workoutKind['titulo'] ?? '')) . ' ' . trim((string) ($workoutKind['foco'] ?? '')));
            if (stridebr_db_bool($kind['musculacao'] ?? false) || str_contains($searchText, 'academia') || str_contains($searchText, 'muscula')) {
                $idModalidade = 'm_musculacao';
            } elseif (stridebr_db_bool($kind['calistenia'] ?? false) || str_contains($searchText, 'calisten')) {
                $idModalidade = 'm_calistenia';
            }
        }
    }

    $modelStmt = $pdo->prepare(
        "SELECT mm.idmodelo
         FROM modelos_modalidade mm
         LEFT JOIN modalidades_usuario mu
           ON mu.idusuario = :usuario_pref
          AND mu.idmodalidade = mm.idmodalidade
         WHERE mm.idmodalidade = :modalidade
           AND mm.ativo = TRUE
           AND (mm.idusuario IS NULL OR mm.idusuario = :usuario_modelo)
         ORDER BY
           CASE WHEN mu.idmodelo_ativo = mm.idmodelo THEN 0 WHEN mm.padrao = TRUE THEN 1 ELSE 2 END,
           mm.versao DESC,
           mm.idmodelo
         LIMIT 1"
    );
    $modelStmt->execute([
        ':usuario_pref' => $idUsuario,
        ':modalidade' => $idModalidade,
        ':usuario_modelo' => $idUsuario,
    ]);
    $idModelo = $modelStmt->fetchColumn();

    if ($idModelo === false && $idModalidade !== 'm_geral') {
        $idModalidade = 'm_geral';
        $modelStmt->execute([
            ':usuario_pref' => $idUsuario,
            ':modalidade' => $idModalidade,
            ':usuario_modelo' => $idUsuario,
        ]);
        $idModelo = $modelStmt->fetchColumn();
    }

    if ($idModelo === false) {
        throw new RuntimeException('Não existe um modelo de atividade ativo para registrar este treino.');
    }

    $fieldStmt = $pdo->prepare(
        "SELECT idcampo, lower(slug) AS slug
         FROM campos_modelo
         WHERE idmodelo = :modelo
           AND ativo = TRUE
           AND lower(slug) IN ('duracao', 'intensidade', 'observacoes')"
    );
    $fieldStmt->execute([':modelo' => $idModelo]);
    $fields = [];
    foreach ($fieldStmt->fetchAll() as $field) {
        $fields[(string) $field['slug']] = (string) $field['idcampo'];
    }

    return [
        'modelo' => (string) $idModelo,
        'modalidade' => $idModalidade,
        'duracao' => $fields['duracao'] ?? null,
        'intensidade' => $fields['intensidade'] ?? null,
        'observacoes' => $fields['observacoes'] ?? null,
    ];
}

function sessaoFeedbackPayload(array $source): array
{
    $intensidade = trim((string) ($source['intensidade'] ?? ''));
    if (!in_array($intensidade, ['', 'leve', 'moderado', 'intenso'], true)) {
        throw new InvalidArgumentException('Intensidade inválida.');
    }

    $feelingRaw = trim((string) ($source['feeling'] ?? ''));
    $feeling = $feelingRaw === '' ? null : filter_var($feelingRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
    if ($feelingRaw !== '' && $feeling === false) {
        throw new InvalidArgumentException('Sensação inválida.');
    }

    $feedback = trim((string) ($source['observacoes'] ?? ''));
    if (stridebr_length($feedback) > 1000) {
        throw new InvalidArgumentException('As observações são muito longas.');
    }

    return [$intensidade, $feeling, $feedback];
}

function sessaoSalvarAtividade(
    PDO $pdo,
    string $idUsuario,
    string $titulo,
    ?string $idCronograma,
    ?string $idTreino,
    DateTimeImmutable $inicio,
    ?DateTimeImmutable $fim,
    int $exerciciosConcluidos,
    int $exerciciosTotal,
    int $seriesConcluidas,
    int $seriesTotal,
    string $origem,
    string $intensidade = '',
    ?int $feeling = null,
    string $feedback = '',
    ?string $dataOcorrenciaOrigem = null,
    ?string $dataOcorrenciaPlanejada = null,
    ?string $horaOcorrenciaPlanejada = null
): string {
    $duration = $fim !== null ? max(1, $fim->getTimestamp() - $inicio->getTimestamp()) : null;
    $durationText = null;
    if ($duration !== null) {
        $hours = intdiv($duration, 3600);
        $minutes = intdiv($duration % 3600, 60);
        $seconds = $duration % 60;
        $durationText = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    $kind = sessaoModeloAtividade($pdo, $idUsuario, $idTreino);
    $recordValues = [];
    if ($intensidade !== '' && !empty($kind['intensidade'])) {
        $intensityField = (string) $kind['intensidade'];
        $optionStmt = $pdo->prepare('SELECT idopcao FROM campos_modelo_opcoes WHERE idcampo = :campo AND valor = :valor AND ativo = TRUE LIMIT 1');
        $optionStmt->execute([':campo' => $intensityField, ':valor' => $intensidade]);
        $optionId = $optionStmt->fetchColumn();
        if ($optionId !== false) {
            $recordValues[$intensityField] = $optionId;
        }
    }

    $feedbackParts = [];
    if ($feeling !== null) {
        $feedbackParts[] = 'Sensação: ' . $feeling . '/5';
    }
    if ($feedback !== '') {
        $feedbackParts[] = $feedback;
    }
    if ($feedbackParts !== []) {
        if (!empty($kind['observacoes'])) {
            $recordValues[(string) $kind['observacoes']] = implode("\n", $feedbackParts);
        }
    }

    $notesParts = [sprintf(
        '%s %d/%d exercícios e %d/%d séries concluídos.',
        $origem,
        $exerciciosConcluidos,
        $exerciciosTotal,
        $seriesConcluidas,
        $seriesTotal
    )];
    if ($feeling !== null) {
        $notesParts[] = 'Sensação: ' . $feeling . '/5.';
    }
    if ($feedback !== '') {
        $notesParts[] = $feedback;
    }

    $tz = new DateTimeZone('America/Sao_Paulo');
    return atividadeSalvarRegistro($pdo, $idUsuario, [
        'idmodelo' => $kind['modelo'],
        'titulo' => $titulo,
        'observacoes' => implode("\n", $notesParts),
        'data_inicio' => $inicio->setTimezone($tz)->format('Y-m-d H:i'),
        'data_fim' => $fim?->setTimezone($tz)->format('Y-m-d H:i'),
        'status' => 'concluido',
        'visibilidade' => '',
        'idcronograma' => $idCronograma,
        'idtreino_cronograma' => $idTreino,
        'data_ocorrencia_origem' => $dataOcorrenciaOrigem,
        'data_ocorrencia_planejada' => $dataOcorrenciaPlanejada,
        'hora_ocorrencia_planejada' => $horaOcorrenciaPlanejada,
        'record_values' => $recordValues,
        'unidades' => [[
            'rotulo' => 'Sessão',
            'values' => !empty($kind['duracao']) && $durationText !== null ? [(string) $kind['duracao'] => $durationText] : [],
        ]],
    ]);
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
        $existing = sessaoCarregar($pdo, $idUsuario);
        if ($existing !== []) sessaoJson(['ok' => false, 'message' => 'Você já tem um treino em andamento.', 'session' => $existing], 409);

        $idTreino = trim((string) ($_POST['idtreino'] ?? ''));
        $treino = cronogramaBuscarTreino($pdo, $idTreino, $idUsuario);
        if ($treino === []) throw new RuntimeException('Treino não encontrado.');
        $dataOcorrenciaOrigem = trim((string) ($_POST['data_ocorrencia_origem'] ?? '')) ?: null;
        $dataOcorrenciaPlanejada = trim((string) ($_POST['data_ocorrencia_planejada'] ?? '')) ?: null;
        foreach ([$dataOcorrenciaOrigem, $dataOcorrenciaPlanejada] as $occurrenceDate) {
            if ($occurrenceDate !== null) cronogramaValidarDataIso($occurrenceDate);
        }
        $horaOcorrenciaPlanejada = trim((string) ($_POST['hora_ocorrencia_planejada'] ?? '')) ?: null;
        if ($horaOcorrenciaPlanejada !== null && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $horaOcorrenciaPlanejada)) throw new InvalidArgumentException('Hora planejada inválida.');
        $exercicios = cronogramaListarTreinoExercicios($pdo, $idTreino, $idUsuario);
        $idSessao = atividadeGerarId();

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO sessoes_treino (idsessao, idusuario, idcronograma_origem, idtreino_origem, data_ocorrencia_origem, data_ocorrencia_planejada, hora_ocorrencia_planejada, titulo_snapshot) VALUES (:id, :usuario, :cronograma, :treino, :ocorrencia_origem, :ocorrencia_planejada, :hora_ocorrencia_planejada, :titulo)');
            $stmt->execute([':id' => $idSessao, ':usuario' => $idUsuario, ':cronograma' => $treino['idcronograma'], ':treino' => $idTreino, ':ocorrencia_origem' => $dataOcorrenciaOrigem, ':ocorrencia_planejada' => $dataOcorrenciaPlanejada, ':hora_ocorrencia_planejada' => $horaOcorrenciaPlanejada, ':titulo' => $treino['titulo']]);
            $insertExercise = $pdo->prepare('INSERT INTO sessoes_treino_exercicios (idsessao_exercicio, idsessao, idexercicio, nome_snapshot, series_planejadas, repeticoes_snapshot, carga_snapshot, descanso_snapshot, observacoes_snapshot, duracao_snapshot, distancia_snapshot, intensidade_snapshot, rpe_snapshot, rir_snapshot, tempo_execucao_snapshot, cadencia_snapshot, ordem) VALUES (:id, :sessao, :exercicio, :nome, :series, :repeticoes, :carga, :descanso, :observacoes, :duracao, :distancia, :intensidade, :rpe, :rir, :tempo_execucao, :cadencia, :ordem)');
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
                    ':duracao' => $row['duracao'] ?: null,
                    ':distancia' => $row['distancia'] ?: null,
                    ':intensidade' => $row['intensidade'] ?: null,
                    ':rpe' => $row['rpe'] !== null && $row['rpe'] !== '' ? $row['rpe'] : null,
                    ':rir' => $row['rir'] !== null && $row['rir'] !== '' ? $row['rir'] : null,
                    ':tempo_execucao' => $row['tempo_execucao'] ?: null,
                    ':cadencia' => $row['cadencia'] ?: null,
                    ':ordem' => (int) $row['ordem'],
                ]);
                for ($number = 1; $number <= $plannedSets; $number++) $insertSet->execute([':id' => atividadeGerarId(), ':exercicio' => $idSessaoExercicio, ':numero' => $number]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        productAnalyticsRegistrar($pdo, $idUsuario, 'workout_started', ['source' => 'schedule']);
        sessaoJson(['ok' => true, 'session' => sessaoCarregar($pdo, $idUsuario)]);
    }

    if ($action === 'start_scheduled') {
        $existing = sessaoCarregar($pdo, $idUsuario);
        if ($existing !== []) sessaoJson(['ok' => false, 'message' => 'Você já tem um treino em andamento.', 'session' => $existing], 409);

        $idAgendamento = trim((string) ($_POST['idagendamento'] ?? ''));
        $stmt = $pdo->prepare("SELECT * FROM treinos_agendados WHERE idagendamento = :id AND idatleta = :usuario AND status = 'publicado' LIMIT 1");
        $stmt->execute([':id' => $idAgendamento, ':usuario' => $idUsuario]);
        $agendamento = $stmt->fetch();
        if (!$agendamento) throw new RuntimeException('Treino agendado não encontrado ou indisponível.');

        $exerciseStmt = $pdo->prepare('SELECT * FROM treinos_agendados_exercicios WHERE idagendamento = :id ORDER BY ordem');
        $exerciseStmt->execute([':id' => $idAgendamento]);
        $exercicios = $exerciseStmt->fetchAll();
        $idSessao = atividadeGerarId();

        $pdo->beginTransaction();
        try {
            $insertSession = $pdo->prepare('INSERT INTO sessoes_treino (idsessao, idusuario, idcronograma_origem, idtreino_origem, idagendamento_origem, titulo_snapshot) VALUES (:id, :usuario, :cronograma, :treino, :agendamento, :titulo)');
            $insertSession->execute([
                ':id' => $idSessao,
                ':usuario' => $idUsuario,
                ':cronograma' => $agendamento['idcronograma_origem'] ?: null,
                ':treino' => $agendamento['idtreino_origem'] ?: null,
                ':agendamento' => $idAgendamento,
                ':titulo' => $agendamento['titulo'],
            ]);
            $insertExercise = $pdo->prepare('INSERT INTO sessoes_treino_exercicios (idsessao_exercicio, idsessao, idexercicio, nome_snapshot, series_planejadas, repeticoes_snapshot, carga_snapshot, descanso_snapshot, observacoes_snapshot, duracao_snapshot, distancia_snapshot, intensidade_snapshot, rpe_snapshot, rir_snapshot, tempo_execucao_snapshot, cadencia_snapshot, ordem) VALUES (:id, :sessao, :exercicio, :nome, :series, :repeticoes, :carga, :descanso, :observacoes, :duracao, :distancia, :intensidade, :rpe, :rir, :tempo_execucao, :cadencia, :ordem)');
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
                    ':duracao' => $row['duracao'] ?: null,
                    ':distancia' => $row['distancia'] ?: null,
                    ':intensidade' => $row['intensidade'] ?: null,
                    ':rpe' => $row['rpe'] !== null && $row['rpe'] !== '' ? $row['rpe'] : null,
                    ':rir' => $row['rir'] !== null && $row['rir'] !== '' ? $row['rir'] : null,
                    ':tempo_execucao' => $row['tempo_execucao'] ?: null,
                    ':cadencia' => $row['cadencia'] ?: null,
                    ':ordem' => (int) $row['ordem'],
                ]);
                for ($number = 1; $number <= $plannedSets; $number++) {
                    $insertSet->execute([':id' => atividadeGerarId(), ':exercicio' => $idSessaoExercicio, ':numero' => $number]);
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        sessaoJson(['ok' => true, 'session' => sessaoCarregar($pdo, $idUsuario)]);
    }

    if ($action === 'update_set') {
        $idSerie = trim((string) ($_POST['idserie'] ?? ''));
        if ($idSerie === '') throw new InvalidArgumentException('Série inválida.');
        $reps = sessaoSerieRepeticoes($_POST['repeticoes'] ?? '');
        $load = sessaoSerieCarga($_POST['carga'] ?? '');
        $propagate = ($_POST['propagate_load'] ?? '0') === '1';
        $pdo->beginTransaction();
        try {
            // Lock the parent first, serializing edits from separate tabs/devices.
            $lock = $pdo->prepare("SELECT s.idsessao FROM sessoes_treino s
                JOIN sessoes_treino_exercicios se ON se.idsessao = s.idsessao
                JOIN sessoes_treino_series st ON st.idsessao_exercicio = se.idsessao_exercicio
                WHERE st.idserie = :serie AND s.idusuario = :usuario AND s.status = 'ativo' FOR UPDATE OF s");
            $lock->execute([':serie' => $idSerie, ':usuario' => $idUsuario]);
            $sessionId = $lock->fetchColumn();
            if (!$sessionId) throw new RuntimeException('Série não encontrada.');
            $setsQuery = $pdo->prepare('SELECT * FROM sessoes_treino_series WHERE idsessao_exercicio =
                (SELECT idsessao_exercicio FROM sessoes_treino_series WHERE idserie = :serie) ORDER BY numero FOR UPDATE');
            $setsQuery->execute([':serie' => $idSerie]);
            $sets = $setsQuery->fetchAll();
            foreach ($sets as &$set) $set['concluida'] = stridebr_db_bool($set['concluida']);
            unset($set);
            $inherited = $_SESSION['workout_load_defaults'][$sessionId] ?? [];
            $targets = $propagate ? stridebr_workout_load_targets($sets, $idSerie, $inherited) : [];
            // A queued load edit must not revert a concurrent reps edit, and vice versa.
            foreach ($sets as $set) {
                if ((string) $set['idserie'] !== $idSerie) continue;
                if (($_POST['edited_field'] ?? '') === 'load') $reps = $set['repeticoes_realizadas'];
                if (($_POST['edited_field'] ?? '') === 'reps') $load = $set['carga_realizada'];
            }
            $stmt = $pdo->prepare('UPDATE sessoes_treino_series SET repeticoes_realizadas = :reps, carga_realizada = :carga WHERE idserie = :serie');
            $stmt->execute([':reps' => $reps, ':carga' => $load, ':serie' => $idSerie]);
            if ($propagate) {
                $inherited[$idSerie] = null; // Even an explicit empty value creates a boundary.
                $update = $pdo->prepare('UPDATE sessoes_treino_series SET carga_realizada = :carga WHERE idserie = :serie AND concluida = FALSE');
                foreach ($targets as $target) {
                    $update->execute([':carga' => $load, ':serie' => $target]);
                    $inherited[$target] = (string) $load;
                }
            }
            $pdo->commit();
            $_SESSION['workout_load_defaults'] = [$sessionId => $inherited];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        sessaoJson(['ok' => true, 'session' => sessaoCarregar($pdo, $idUsuario, false)]);
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
        sessaoJson(['ok' => true, 'session' => sessaoCarregar($pdo, $idUsuario, false)]);
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
        sessaoJson(['ok' => true, 'session' => sessaoCarregar($pdo, $idUsuario, false)]);
    }

    if ($action === 'mark_all') {
        $session = sessaoCarregar($pdo, $idUsuario, false);
        if ($session === []) {
            throw new RuntimeException('Nenhum treino em andamento.');
        }
        $done = stridebr_db_bool($_POST['concluido'] ?? true);
        $pdo->beginTransaction();
        try {
            $exerciseStmt = $pdo->prepare('UPDATE sessoes_treino_exercicios SET concluido = :done WHERE idsessao = :sessao');
            $exerciseStmt->bindValue(':done', $done, PDO::PARAM_BOOL);
            $exerciseStmt->bindValue(':sessao', $session['idsessao']);
            $exerciseStmt->execute();

            $setStmt = $pdo->prepare('UPDATE sessoes_treino_series st SET concluida = :done, data_conclusao = CASE WHEN :done2 THEN COALESCE(st.data_conclusao, NOW()) ELSE NULL END FROM sessoes_treino_exercicios se WHERE st.idsessao_exercicio = se.idsessao_exercicio AND se.idsessao = :sessao');
            $setStmt->bindValue(':done', $done, PDO::PARAM_BOOL);
            $setStmt->bindValue(':done2', $done, PDO::PARAM_BOOL);
            $setStmt->bindValue(':sessao', $session['idsessao']);
            $setStmt->execute();
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        sessaoJson(['ok' => true, 'session' => sessaoCarregar($pdo, $idUsuario, false)]);
    }

    if ($action === 'quick_register') {
        $idTreino = trim((string) ($_POST['idtreino'] ?? ''));
        $treino = cronogramaBuscarTreino($pdo, $idTreino, $idUsuario);
        if ($treino === []) {
            throw new RuntimeException('Treino não encontrado.');
        }

        $tz = new DateTimeZone('America/Sao_Paulo');
        $today = new DateTimeImmutable('now', $tz);
        $data = trim((string) ($_POST['data'] ?? $today->format('Y-m-d')));
        $hora = trim((string) ($_POST['hora'] ?? substr((string) $treino['hora_inicio'], 0, 5)));
        $duracaoRaw = trim((string) ($_POST['duracao_minutos'] ?? ''));
        $duracao = null;
        if ($duracaoRaw !== '' && $duracaoRaw !== '0') {
            $duracao = filter_var($duracaoRaw, FILTER_VALIDATE_INT);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            throw new InvalidArgumentException('Data inválida.');
        }
        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $data, $tz);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $data) {
            throw new InvalidArgumentException('Data inválida.');
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) {
            throw new InvalidArgumentException('Hora inválida.');
        }
        if ($duracao !== null && ($duracao === false || $duracao < 1 || $duracao > 1440)) {
            throw new InvalidArgumentException('Duração inválida.');
        }
        [$intensidade, $feeling, $feedback] = sessaoFeedbackPayload($_POST);
        $dataOcorrenciaOrigem = trim((string) ($_POST['data_ocorrencia_origem'] ?? '')) ?: null;
        $dataOcorrenciaPlanejada = trim((string) ($_POST['data_ocorrencia_planejada'] ?? '')) ?: null;
        foreach ([$dataOcorrenciaOrigem, $dataOcorrenciaPlanejada] as $occurrenceDate) {
            if ($occurrenceDate !== null) cronogramaValidarDataIso($occurrenceDate);
        }
        $horaOcorrenciaPlanejada = trim((string) ($_POST['hora_ocorrencia_planejada'] ?? '')) ?: null;
        if ($horaOcorrenciaPlanejada !== null && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $horaOcorrenciaPlanejada)) throw new InvalidArgumentException('Hora planejada inválida.');
        if ($dataOcorrenciaOrigem === null || $dataOcorrenciaPlanejada === null) {
            $inferredOccurrence = cronogramaInferirOcorrenciaParaData($pdo, $idUsuario, $idTreino, $data);
            $dataOcorrenciaOrigem ??= $inferredOccurrence['data_ocorrencia_origem'] ?? null;
            $dataOcorrenciaPlanejada ??= $inferredOccurrence['data_ocorrencia_planejada'] ?? null;
            $horaOcorrenciaPlanejada ??= $inferredOccurrence['hora_ocorrencia_planejada'] ?? null;
        }

        $inicio = new DateTimeImmutable($data . ' ' . $hora, $tz);
        $fim = $duracao !== null ? $inicio->modify('+' . $duracao . ' minutes') : null;
        $countStmt = $pdo->prepare('SELECT COUNT(*) AS total_exercises, COALESCE(SUM(CASE WHEN series IS NULL OR series < 1 THEN 1 WHEN series > 99 THEN 99 ELSE series END), 0) AS total_sets FROM treinos_exercicios WHERE idtreino = :treino');
        $countStmt->execute([':treino' => $idTreino]);
        $counts = $countStmt->fetch() ?: [];
        $totalExercises = (int) ($counts['total_exercises'] ?? 0);
        $totalSets = (int) ($counts['total_sets'] ?? 0);

        $activityId = sessaoSalvarAtividade(
            $pdo,
            $idUsuario,
            (string) $treino['titulo'],
            (string) $treino['idcronograma'],
            $idTreino,
            $inicio,
            $fim,
            $totalExercises,
            $totalExercises,
            $totalSets,
            $totalSets,
            'Registro rápido do cronograma.',
            $intensidade,
            $feeling,
            $feedback,
            $dataOcorrenciaOrigem,
            $dataOcorrenciaPlanejada,
            $horaOcorrenciaPlanejada
        );
        sessaoJson(['ok' => true, 'activity_id' => $activityId]);
    }

    if ($action === 'cancel') {
        $stmt = $pdo->prepare("UPDATE sessoes_treino SET status = 'cancelado', data_fim = NOW(), data_atualizacao = NOW() WHERE idusuario = :usuario AND status = 'ativo'");
        $stmt->execute([':usuario' => $idUsuario]);
        sessaoJson(['ok' => true, 'session' => null]);
    }

    if ($action === 'finish') {
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare("SELECT idsessao FROM sessoes_treino WHERE idusuario = :usuario AND status = 'ativo' ORDER BY data_inicio DESC LIMIT 1 FOR UPDATE");
            $lock->execute([':usuario' => $idUsuario]);
            if (!$lock->fetchColumn()) throw new RuntimeException('Nenhum treino em andamento.');

            $session = sessaoCarregar($pdo, $idUsuario, false);
            if ($session === []) throw new RuntimeException('Nenhum treino em andamento.');
            $tz = new DateTimeZone('America/Sao_Paulo');
            $start = (new DateTimeImmutable((string) $session['data_inicio']))->setTimezone($tz);
            $end = new DateTimeImmutable('now', $tz);
            $startRaw = str_replace('T', ' ', trim((string) ($_POST['inicio_real'] ?? '')));
            $endRaw = str_replace('T', ' ', trim((string) ($_POST['fim_real'] ?? '')));
            if ($startRaw !== '') {
                $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $startRaw, $tz);
                if (!$parsed || $parsed->format('Y-m-d H:i') !== $startRaw) throw new InvalidArgumentException('Horário real de início inválido.');
                $start = $parsed;
            }
            if ($endRaw !== '') {
                $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $endRaw, $tz);
                if (!$parsed || $parsed->format('Y-m-d H:i') !== $endRaw) throw new InvalidArgumentException('Horário real de término inválido.');
                $end = $parsed;
            }
            $nowLimit = (new DateTimeImmutable('now', $tz))->modify('+10 minutes');
            $durationSeconds = $end->getTimestamp() - $start->getTimestamp();
            if ($durationSeconds < 60) throw new InvalidArgumentException('A duração do treino precisa ter pelo menos 1 minuto.');
            if ($durationSeconds > 86400) throw new InvalidArgumentException('A duração do treino não pode passar de 24 horas.');
            if ($end > $nowLimit) throw new InvalidArgumentException('O término do treino não pode ficar no futuro.');
            [$intensidade, $feeling, $feedback] = sessaoFeedbackPayload($_POST);
            $totalExercises = count($session['exercicios']);
            $doneExercises = count(array_filter($session['exercicios'], fn(array $row): bool => !empty($row['concluido'])));
            $totalSets = 0;
            $doneSets = 0;
            foreach ($session['exercicios'] as $exercise) {
                $totalSets += count($exercise['series']);
                $doneSets += count(array_filter($exercise['series'], fn(array $set): bool => !empty($set['concluida'])));
            }
            $sessionSource = !empty($session['idagendamento_origem']) ? 'Treino iniciado pela agenda/prescrição.' : 'Treino iniciado pelo cronograma.';
            $activityId = sessaoSalvarAtividade(
                $pdo, $idUsuario, (string) $session['titulo_snapshot'],
                $session['idcronograma_origem'] ?: null, $session['idtreino_origem'] ?: null,
                $start, $end, $doneExercises, $totalExercises, $doneSets, $totalSets,
                $sessionSource, $intensidade, $feeling, $feedback,
                !empty($session['data_ocorrencia_origem']) ? (string) $session['data_ocorrencia_origem'] : null,
                !empty($session['data_ocorrencia_planejada']) ? (string) $session['data_ocorrencia_planejada'] : null,
                !empty($session['hora_ocorrencia_planejada']) ? substr((string) $session['hora_ocorrencia_planejada'], 0, 5) : null
            );

            $stmt = $pdo->prepare("UPDATE sessoes_treino SET status = 'concluido', data_inicio = :inicio, data_fim = :fim, idregistro_atividade = :registro, data_atualizacao = NOW() WHERE idsessao = :sessao AND idusuario = :usuario AND status = 'ativo'");
            $stmt->execute([
                ':inicio' => $start->format(DateTimeInterface::ATOM),
                ':fim' => $end->format(DateTimeInterface::ATOM),
                ':registro' => $activityId,
                ':sessao' => $session['idsessao'],
                ':usuario' => $idUsuario,
            ]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException('Este treino já foi concluído.');
            sessaoPersistirSeriesAtividade($pdo, $activityId, $session);
            if (stridebr_db_column_exists($pdo, 'registros_atividade', 'calorias_ativas_estimadas')) {
                require_once __DIR__ . '/activity_energy.php';
                atividadeEnergiaAtualizarRegistro($pdo, $activityId, $idUsuario);
            }
            if (!empty($session['idagendamento_origem'])) {
                $scheduled = $pdo->prepare("UPDATE treinos_agendados SET status = 'concluido', data_atualizacao = NOW() WHERE idagendamento = :id AND idatleta = :usuario AND status = 'publicado'");
                $scheduled->execute([':id' => $session['idagendamento_origem'], ':usuario' => $idUsuario]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        $durationSeconds = max(1, $end->getTimestamp() - $start->getTimestamp());
        productAnalyticsRegistrar($pdo, $idUsuario, 'workout_completed', ['duration_min' => (int) round($durationSeconds / 60), 'sets_done' => $doneSets, 'exercises_done' => $doneExercises]);
        productAnalyticsRegistrar($pdo, $idUsuario, 'activity_saved', ['source' => 'workout_session']);
        sessaoJson([
            'ok' => true,
            'session' => null,
            'activity_id' => $activityId,
            'summary' => [
                'titulo' => (string) $session['titulo_snapshot'],
                'duracao_segundos' => $durationSeconds,
                'exercicios_concluidos' => $doneExercises,
                'exercicios_total' => $totalExercises,
                'series_concluidas' => $doneSets,
                'series_total' => $totalSets,
                'intensidade' => $intensidade,
                'sensacao' => $feeling,
            ],
        ]);
    }

    sessaoJson(['ok' => false, 'message' => 'Ação inválida.'], 400);
} catch (Throwable $e) {
    error_log('Workout session API: ' . $e->getMessage());
    $message = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível atualizar o treino.';
    sessaoJson(['ok' => false, 'message' => $message], 400);
}
