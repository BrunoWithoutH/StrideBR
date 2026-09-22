<?php

declare(strict_types=1);

require_once __DIR__ . '/teams_surface_provider.php';

require_once __DIR__ . '/atividade_modelo.php';
require_once __DIR__ . '/cronograma.php';
require_once __DIR__ . '/product_analytics.php';
require_once __DIR__ . '/workout_load.php';

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
    $duration = null; $distance = null;
    foreach ($latestSets as $set) { $duration ??= $set['duracao_realizada_s'] ?? null; $distance ??= $set['distancia_realizada_m'] ?? null; }

    $bestNumber = null;
    $bestLabel = '';
    foreach ($previous as $row) {
        $candidateLabels = [];
        foreach ($setRows[(string) $row['idsessao_exercicio']] ?? [] as $set) {
            $candidate = trim((string) ($set['carga_realizada'] ?? ''));
            if ($candidate !== '') $candidateLabels[] = $candidate;
        }

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
            'data_iso' => $date->format('Y-m-d'),
            'series_concluidas' => $done,
            'series_total' => count($latestSets),
            'repeticoes' => $reps,
            'carga' => $load,
            'duracao_s' => $duration,
            'distancia_m' => $distance,
            'series' => array_map(static fn(array $set): array => [
                'numero' => (int) ($set['numero'] ?? 0),
                'repeticoes' => trim((string) ($set['repeticoes_realizadas'] ?? '')),
                'carga' => trim((string) ($set['carga_realizada'] ?? '')),
                'duracao_s' => $set['duracao_realizada_s'] ?? null,
                'distancia_m' => $set['distancia_realizada_m'] ?? null,
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
        $setStmt = $pdo->prepare('SELECT idsessao_exercicio, numero, concluida, repeticoes_realizadas, carga_realizada, duracao_realizada_s, distancia_realizada_m FROM sessoes_treino_series WHERE idsessao_exercicio IN (' . implode(', ', $placeholders) . ') ORDER BY idsessao_exercicio, numero');
        $setStmt->execute($setParams);
        foreach ($setStmt->fetchAll() as $row) $setRows[(string) $row['idsessao_exercicio']][] = $row;
    }

    $result = [];
    foreach ($previousByCurrent as $currentId => $rows) {
        $result[$currentId] = sessaoMontarHistoricoExercicio($rows, $setRows);
    }
    return $result;
}

function sessaoHidratar(PDO $pdo, string $idUsuario, array $session, bool $includeHistory = true): array
{
    foreach (['data_inicio' => 'started_at_ms', 'data_fim' => 'ended_at_ms'] as $field => $msField) {
        try {
            if (trim((string) ($session[$field] ?? '')) === '') {
                $session[$msField] = null;
                continue;
            }
            $date = new DateTimeImmutable((string) $session[$field]);
            $session[$msField] = $date->getTimestamp() * 1000;
            $session[$field] = $date->format(DateTimeInterface::ATOM);
        } catch (Throwable) {
            $session[$msField] = null;
        }
    }

    $exerciseStmt = $pdo->prepare('SELECT * FROM sessoes_treino_exercicios WHERE idsessao = :sessao ORDER BY ordem');
    $exerciseStmt->execute([':sessao' => $session['idsessao']]);
    $session['exercicios'] = $exerciseStmt->fetchAll();
    if (($session['status'] ?? '') === 'ativo') $session['exercicios'] = cronogramaHidratarExerciciosPlanejados($pdo, $idUsuario, $session['exercicios']);
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

function sessaoCarregar(PDO $pdo, string $idUsuario, bool $includeHistory = true): array
{
    $stmt = $pdo->prepare("SELECT s.*, c.nome AS cronograma_nome FROM sessoes_treino s LEFT JOIN cronogramas c ON c.idcronograma = s.idcronograma_origem WHERE s.idusuario = :usuario AND s.status = 'ativo' ORDER BY s.data_inicio DESC LIMIT 1");
    $stmt->execute([':usuario' => $idUsuario]);
    $session = $stmt->fetch();
    return $session ? sessaoHidratar($pdo, $idUsuario, $session, $includeHistory) : [];
}

function sessaoCarregarPorId(PDO $pdo, string $idUsuario, string $idSessao, bool $includeHistory = true): array
{
    $stmt = $pdo->prepare('SELECT s.*, c.nome AS cronograma_nome FROM sessoes_treino s LEFT JOIN cronogramas c ON c.idcronograma = s.idcronograma_origem WHERE s.idusuario = :usuario AND s.idsessao = :sessao LIMIT 1');
    $stmt->execute([':usuario' => $idUsuario, ':sessao' => $idSessao]);
    $session = $stmt->fetch();
    return $session ? sessaoHidratar($pdo, $idUsuario, $session, $includeHistory) : [];
}



function sessaoSerieRepeticoes(mixed $value): ?string
{
    $raw = trim((string) $value);
    if ($raw === '') return null;
    if (preg_match('/^\d{1,3}$/', $raw) !== 1) throw new InvalidArgumentException(stridebr_t('workout_session.invalid_reps'));
    $number = (int) $raw;
    if ($number < 0 || $number > 999) throw new InvalidArgumentException(stridebr_t('workout_session.invalid_reps'));
    return (string) $number;
}

function sessaoSerieCarga(mixed $value): ?string
{
    $raw = trim((string) $value);
    if ($raw === '') return null;
    if (preg_match('/^(\d{1,4}(?:[.,]\d{1,3})?)\s*(?:kg)?$/i', $raw, $match) !== 1) throw new InvalidArgumentException(stridebr_t('workout_session.invalid_load'));
    $number = (float) str_replace(',', '.', $match[1]);
    if ($number < 0 || $number > 9999.999) throw new InvalidArgumentException(stridebr_t('workout_session.invalid_load'));
    $formatted = rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');
    return $formatted . ' kg';
}

/** Same unit semantics as StrideBRWorkoutPrescription; storage is numeric. */
function sessaoSerieMetrica(mixed $value, string $field): int|string|null
{
    if ($value === null || trim((string) $value) === '') return null;
    $raw = stridebr_lower(trim((string) $value));
    $raw = str_replace(',', '.', $raw);
    $number = null;
    if ($field === 'duration') {
        if (preg_match('/^(\d{1,3}):([0-5]\d)(?::([0-5]\d))?$/', $raw, $m)) $number = isset($m[3]) ? (int) $m[1]*3600+(int) $m[2]*60+(int) $m[3] : (int) $m[1]*60+(int) $m[2];
        elseif (preg_match('/^(\d+(?:\.\d+)?)\s*(h|hr|hrs|hora|horas|m|min|mins|minuto|minutos|mn|s|seg|segs|segundo|segundos)$/u', $raw, $m)) $number = (float) $m[1] * (in_array($m[2], ['h','hr','hrs','hora','horas'], true) ? 3600 : (in_array($m[2], ['m','min','mins','minuto','minutos','mn'], true) ? 60 : 1));
        elseif (is_int($value) || is_float($value)) $number = (float) $value;
        if ($number === null || !is_finite((float) $number) || $number < 0 || $number > 2147483647) throw new InvalidArgumentException(stridebr_t('workout_session.invalid_duration'));
        return (int) round($number);
    }
    if ($field === 'distance') {
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(km|quil[oô]metros?|m|metros?)$/u', $raw, $m)) $number = (float) $m[1] * (in_array($m[2], ['km','quilometro','quilometros','quilômetro','quilômetros'], true) ? 1000 : 1);
        elseif (is_int($value) || is_float($value)) $number = (float) $value;
        if ($number === null || !is_finite((float) $number) || $number < 0 || $number > 999999999.999) throw new InvalidArgumentException(stridebr_t('workout_session.invalid_distance'));
        return number_format($number, 3, '.', '');
    }
    throw new InvalidArgumentException(stridebr_t('workout_session.invalid_field'));
}

/** Resolve only unequivocal targets. Ranges and malformed prescriptions stay empty. */
function sessaoDefaultsPlanejados(array $exercise): array
{
    $reps = trim((string) ($exercise['repeticoes_snapshot'] ?? ''));
    $duration = $exercise['duracao_snapshot'] ?? null;
    $distance = $exercise['distancia_snapshot'] ?? null;
    if (!$duration && preg_match('/[a-z:]/i', $reps)) { try { $duration = sessaoSerieMetrica($reps, 'duration'); $reps = ''; } catch (InvalidArgumentException) {} }
    if (!$distance && preg_match('/[a-z]/i', $reps)) { try { $distance = (float) sessaoSerieMetrica($reps, 'distance'); $reps = ''; } catch (InvalidArgumentException) {} }
    $result = ['reps'=>null,'load'=>null,'duration'=>null,'distance'=>null];
    if (preg_match('/^(\d{1,3})(?:\s*(?:rep|reps|repetições|repeticoes))?$/iu', $reps, $m)) $result['reps'] = (string) (int) $m[1];
    foreach (['load'=>$exercise['carga_snapshot'] ?? null, 'duration'=>$duration, 'distance'=>$distance] as $field=>$value) {
        try { $result[$field] = $field === 'load' ? sessaoSerieCarga($value) : sessaoSerieMetrica($value, $field); } catch (InvalidArgumentException) {}
    }
    return $result;
}

function sessaoPersistirSeriesAtividade(PDO $pdo, string $idRegistro, array $session): void
{
    $pdo->prepare('DELETE FROM series_exercicio_atividade WHERE idregistro = :registro')->execute([':registro' => $idRegistro]);
    $insert = $pdo->prepare(
        "INSERT INTO series_exercicio_atividade
        (idserie, idregistro, idexercicio, nome_exercicio, ordem_exercicio, ordem_serie, tipo, carga_kg, repeticoes, duracao_segundos, distancia_metros, rir, rpe, concluida)
        VALUES (:id, :registro, :exercicio, :nome, :ordem_exercicio, :ordem_serie, 'trabalho', :carga, :reps, :duration, :distance, :rir, :rpe, :concluida)"
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
                ':duration' => $set['duracao_realizada_s'] ?? null,
                ':distance' => $set['distancia_realizada_m'] ?? null,
                ':rir' => $rir,
                ':rpe' => $rpe,
                ':concluida' => stridebr_db_bool($set['concluida'] ?? false) ? 1 : 0,
            ]);
        }
    }
}

function sessaoModeloAtividade(PDO $pdo, string $idUsuario, ?string $idTreino, ?string $idModalidadeForcada = null): array
{
    $idModalidade = trim((string) $idModalidadeForcada) ?: 'm_geral';

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
        $idModalidade = trim((string) $idModalidadeForcada) ?: 'm_geral';
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
    ?string $horaOcorrenciaPlanejada = null,
    ?string $idModalidade = null
): string {
    $duration = $fim !== null ? max(1, $fim->getTimestamp() - $inicio->getTimestamp()) : null;
    $durationText = null;
    if ($duration !== null) {
        $hours = intdiv($duration, 3600);
        $minutes = intdiv($duration % 3600, 60);
        $seconds = $duration % 60;
        $durationText = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    $kind = sessaoModeloAtividade($pdo, $idUsuario, $idTreino, $idModalidade);
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

final class WorkoutSessionAlreadyActiveException extends RuntimeException
{
    public function __construct(public readonly array $session)
    {
        parent::__construct('Você já tem um treino em andamento.');
    }
}

function sessaoFeatureAtiva(PDO $pdo): bool
{
    $stmt = $pdo->prepare("SELECT ativo FROM feature_flags WHERE chave = 'workout_sessions.enabled' LIMIT 1");
    $stmt->execute();
    $value = $stmt->fetchColumn();
    return $value === false ? false : stridebr_db_bool($value);
}

function sessaoGarantirSemAtiva(PDO $pdo, string $idUsuario): void
{
    $existing = sessaoCarregar($pdo, $idUsuario);
    if ($existing !== []) throw new WorkoutSessionAlreadyActiveException($existing);
}

function sessaoCopiarExercicios(PDO $pdo, string $idSessao, array $exercicios): void
{
    $insertExercise = $pdo->prepare('INSERT INTO sessoes_treino_exercicios (idsessao_exercicio, idsessao, idexercicio, nome_snapshot, series_planejadas, repeticoes_snapshot, carga_snapshot, bloco_snapshot, cluster_snapshot, descanso_snapshot, observacoes_snapshot, duracao_snapshot, distancia_snapshot, intensidade_snapshot, rpe_snapshot, rir_snapshot, tempo_execucao_snapshot, cadencia_snapshot, ordem) VALUES (:id, :sessao, :exercicio, :nome, :series, :repeticoes, :carga, :bloco, :cluster, :descanso, :observacoes, :duracao, :distancia, :intensidade, :rpe, :rir, :tempo_execucao, :cadencia, :ordem)');
    $insertSet = $pdo->prepare('INSERT INTO sessoes_treino_series (idserie, idsessao_exercicio, numero) VALUES (:id, :exercicio, :numero)');
    foreach ($exercicios as $row) {
        $idSessaoExercicio = atividadeGerarId();
        $seriesRaw = $row['series'] ?? $row['series_planejadas'] ?? null;
        $plannedSets = is_numeric($seriesRaw) ? max(1, min(99, (int) $seriesRaw)) : 1;
        $insertExercise->execute([
            ':id' => $idSessaoExercicio,
            ':sessao' => $idSessao,
            ':exercicio' => trim((string) ($row['idexercicio'] ?? '')) ?: null,
            ':nome' => (string) ($row['nome_snapshot'] ?? 'Exercício'),
            ':series' => is_numeric($seriesRaw) ? (int) $seriesRaw : null,
            ':repeticoes' => ($row['repeticoes'] ?? $row['repeticoes_snapshot'] ?? null) ?: null,
            ':carga' => ($row['carga'] ?? $row['carga_snapshot'] ?? null) ?: null,
            ':bloco' => ($row['bloco'] ?? $row['bloco_snapshot'] ?? null) ?: null,
            ':cluster' => ($row['cluster'] ?? $row['cluster_snapshot'] ?? null) ?: null,
            ':descanso' => ($row['descanso'] ?? $row['descanso_snapshot'] ?? null) ?: null,
            ':observacoes' => ($row['observacoes'] ?? $row['observacoes_snapshot'] ?? null) ?: null,
            ':duracao' => ($row['duracao'] ?? $row['duracao_snapshot'] ?? null) ?: null,
            ':distancia' => ($row['distancia'] ?? $row['distancia_snapshot'] ?? null) ?: null,
            ':intensidade' => ($row['intensidade'] ?? $row['intensidade_snapshot'] ?? null) ?: null,
            ':rpe' => ($row['rpe'] ?? $row['rpe_snapshot'] ?? null) !== null && ($row['rpe'] ?? $row['rpe_snapshot']) !== '' ? ($row['rpe'] ?? $row['rpe_snapshot']) : null,
            ':rir' => ($row['rir'] ?? $row['rir_snapshot'] ?? null) !== null && ($row['rir'] ?? $row['rir_snapshot']) !== '' ? ($row['rir'] ?? $row['rir_snapshot']) : null,
            ':tempo_execucao' => ($row['tempo_execucao'] ?? $row['tempo_execucao_snapshot'] ?? null) ?: null,
            ':cadencia' => ($row['cadencia'] ?? $row['cadencia_snapshot'] ?? null) ?: null,
            ':ordem' => max(1, (int) ($row['ordem'] ?? 1)),
        ]);
        for ($number = 1; $number <= $plannedSets; $number++) {
            $insertSet->execute([':id' => atividadeGerarId(), ':exercicio' => $idSessaoExercicio, ':numero' => $number]);
        }
    }
}

function sessaoIniciarCronograma(PDO $pdo, string $idUsuario, string $idTreino, array $occurrence = []): array
{
    sessaoGarantirSemAtiva($pdo, $idUsuario);
    $treino = cronogramaBuscarTreino($pdo, $idTreino, $idUsuario);
    if ($treino === []) throw new RuntimeException('Treino não encontrado.');
    $dataOcorrenciaOrigem = trim((string) ($occurrence['data_ocorrencia_origem'] ?? '')) ?: null;
    $dataOcorrenciaPlanejada = trim((string) ($occurrence['data_ocorrencia_planejada'] ?? '')) ?: null;
    foreach ([$dataOcorrenciaOrigem, $dataOcorrenciaPlanejada] as $date) if ($date !== null) cronogramaValidarDataIso($date);
    $horaOcorrenciaPlanejada = trim((string) ($occurrence['hora_ocorrencia_planejada'] ?? '')) ?: null;
    if ($horaOcorrenciaPlanejada !== null && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $horaOcorrenciaPlanejada)) throw new InvalidArgumentException('Hora planejada inválida.');
    $exercicios = cronogramaListarTreinoExercicios($pdo, $idTreino, $idUsuario);
    $idSessao = atividadeGerarId();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO sessoes_treino (idsessao, idusuario, idcronograma_origem, idtreino_origem, idmodalidade_origem, data_ocorrencia_origem, data_ocorrencia_planejada, hora_ocorrencia_planejada, titulo_snapshot) VALUES (:id, :usuario, :cronograma, :treino, :modalidade, :ocorrencia_origem, :ocorrencia_planejada, :hora_ocorrencia_planejada, :titulo)');
        $stmt->execute([
            ':id' => $idSessao,
            ':usuario' => $idUsuario,
            ':cronograma' => $treino['idcronograma'],
            ':treino' => $idTreino,
            ':modalidade' => trim((string) ($treino['idmodalidade'] ?? '')) ?: null,
            ':ocorrencia_origem' => $dataOcorrenciaOrigem,
            ':ocorrencia_planejada' => $dataOcorrenciaPlanejada,
            ':hora_ocorrencia_planejada' => $horaOcorrenciaPlanejada,
            ':titulo' => $treino['titulo'],
        ]);
        sessaoCopiarExercicios($pdo, $idSessao, $exercicios);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e->getCode() === '23505') {
            $active = sessaoCarregar($pdo, $idUsuario);
            if ($active !== []) throw new WorkoutSessionAlreadyActiveException($active);
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    productAnalyticsRegistrar($pdo, $idUsuario, 'workout_started', ['source' => 'schedule']);
    return sessaoCarregarPorId($pdo, $idUsuario, $idSessao);
}

function sessaoIniciarAgendado(PDO $pdo, string $idUsuario, string $idAgendamento): array
{
    sessaoGarantirSemAtiva($pdo, $idUsuario);
    $stmt = $pdo->prepare("SELECT ta.*, COALESCE(ta.idmodalidade, tc.idmodalidade, tm.idmodalidade) AS resolved_idmodalidade FROM treinos_agendados ta LEFT JOIN treinos_cronograma tc ON tc.idtreino = ta.idtreino_origem LEFT JOIN treinos_modelo tm ON tm.idtreino_modelo = COALESCE(ta.idtreino_modelo_origem, tc.idtreino_modelo) WHERE ta.idagendamento = :id AND ta.idatleta = :usuario AND ta.status = 'publicado' LIMIT 1");
    $stmt->execute([':id' => $idAgendamento, ':usuario' => $idUsuario]);
    $agendamento = $stmt->fetch();
    if (!$agendamento) throw new RuntimeException('Treino agendado não encontrado ou indisponível.');
    $exerciseStmt = $pdo->prepare('SELECT * FROM treinos_agendados_exercicios WHERE idagendamento = :id ORDER BY ordem');
    $exerciseStmt->execute([':id' => $idAgendamento]);
    $exercicios = cronogramaHidratarExerciciosPlanejados($pdo, $idUsuario, $exerciseStmt->fetchAll());
    $idSessao = atividadeGerarId();
    $pdo->beginTransaction();
    try {
        $insertSession = $pdo->prepare('INSERT INTO sessoes_treino (idsessao, idusuario, idcronograma_origem, idtreino_origem, idagendamento_origem, idmodalidade_origem, data_ocorrencia_planejada, hora_ocorrencia_planejada, titulo_snapshot) VALUES (:id, :usuario, :cronograma, :treino, :agendamento, :modalidade, :data, :hora, :titulo)');
        $insertSession->execute([
            ':id' => $idSessao,
            ':usuario' => $idUsuario,
            ':cronograma' => $agendamento['idcronograma_origem'] ?: null,
            ':treino' => $agendamento['idtreino_origem'] ?: null,
            ':agendamento' => $idAgendamento,
            ':modalidade' => $agendamento['resolved_idmodalidade'] ?: null,
            ':data' => $agendamento['data_treino'],
            ':hora' => $agendamento['hora_inicio'],
            ':titulo' => $agendamento['titulo'],
        ]);
        sessaoCopiarExercicios($pdo, $idSessao, $exercicios);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e->getCode() === '23505') {
            $active = sessaoCarregar($pdo, $idUsuario);
            if ($active !== []) throw new WorkoutSessionAlreadyActiveException($active);
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    productAnalyticsRegistrar($pdo, $idUsuario, 'workout_started', ['source' => 'scheduled']);
    return sessaoCarregarPorId($pdo, $idUsuario, $idSessao);
}

function sessaoIniciarInstitucional(PDO $pdo, string $idUsuario, array $training): array
{
    sessaoGarantirSemAtiva($pdo, $idUsuario);
    $trainingRef = trim((string) ($training['training_ref'] ?? ''));
    $recipientRef = trim((string) ($training['recipient_ref'] ?? ''));
    if ($trainingRef === '' || $recipientRef === '' || (string) ($training['status'] ?? '') !== 'publicado') throw new RuntimeException('Treino institucional indisponível.');
    $capabilities = is_array($training['capabilities'] ?? null) ? $training['capabilities'] : [];
    if (!stridebr_db_bool($capabilities['can_start_session'] ?? false)) throw new RuntimeException('Este treino institucional não permite execução em sessão.');
    $steps = array_values((array) ($training['structure'] ?? []));
    if ($steps === []) throw new RuntimeException('Este treino institucional não possui estrutura executável.');

    $sportSlug = trim((string) ($training['sport'] ?? ''));
    $sportStmt = $pdo->prepare("SELECT idmodalidade FROM modalidades WHERE ativo=TRUE AND (idusuario IS NULL OR idusuario=:user) AND lower(slug)=lower(:slug) ORDER BY CASE WHEN idusuario=:user_order THEN 0 ELSE 1 END,ordem_catalogo,nome LIMIT 1");
    $sportStmt->execute([':user' => $idUsuario, ':slug' => $sportSlug, ':user_order' => $idUsuario]);
    $modalityId = $sportStmt->fetchColumn();
    $normalized = [];
    foreach ($steps as $index => $step) {
        if (!is_array($step)) continue;
        $duration = isset($step['duration_s']) && is_numeric($step['duration_s']) ? ((int) $step['duration_s']) . ' s' : null;
        $distance = isset($step['distance_m']) && is_numeric($step['distance_m']) ? ((float) $step['distance_m']) . ' m' : null;
        $rest = isset($step['rest_s']) && is_numeric($step['rest_s']) ? ((int) $step['rest_s']) . ' s' : null;
        $target = is_array($step['target'] ?? null) ? $step['target'] : null;
        $targetText = $target ? json_encode($target, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $normalized[] = [
            'idexercicio' => null,
            'nome_snapshot' => trim((string) ($step['name'] ?? '')) ?: ('Passo ' . ($index + 1)),
            'series' => isset($step['sets']) && is_numeric($step['sets']) ? (int) $step['sets'] : (isset($step['repeat_count']) && is_numeric($step['repeat_count']) ? (int) $step['repeat_count'] : 1),
            'repeticoes' => isset($step['repetitions']) ? (string) $step['repetitions'] : null,
            'carga' => null,
            'bloco' => (string) ($step['step_type'] ?? 'work'),
            'cluster' => null,
            'descanso' => $rest,
            'observacoes' => $targetText,
            'duracao' => $duration,
            'distancia' => $distance,
            'intensidade' => $target !== null ? (string) ($target['type'] ?? '') : null,
            'rpe' => $target !== null && ($target['type'] ?? '') === 'rpe' && is_numeric($target['max'] ?? null) ? (float) $target['max'] : null,
            'rir' => null,
            'tempo_execucao' => null,
            'cadencia' => null,
            'ordem' => isset($step['order']) && is_numeric($step['order']) ? (int) $step['order'] : $index + 1,
        ];
    }
    if ($normalized === []) throw new RuntimeException('Este treino institucional não possui estrutura executável.');
    $idSession = atividadeGerarId();
    $context = [
        'organization' => $training['organization'] ?? null,
        'team' => $training['team'] ?? null,
        'season' => $training['season'] ?? null,
        'planning_block' => $training['planning_block'] ?? null,
        'competition' => $training['competition'] ?? null,
        'planned_duration_s' => isset($training['planned_duration_s']) ? (int) $training['planned_duration_s'] : null,
        'planned_distance_m' => isset($training['planned_distance_m']) && $training['planned_distance_m'] !== null ? (float) $training['planned_distance_m'] : null,
        'sport' => $sportSlug !== '' ? $sportSlug : null,
    ];
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO sessoes_treino (idsessao,idusuario,idmodalidade_origem,data_ocorrencia_planejada,hora_ocorrencia_planejada,titulo_snapshot,origem_externa,referencia_externa,recipient_ref_externo,contexto_institucional_snapshot) VALUES (:id,:user,:sport,:date,:time,:title,'teams',:ref,:recipient,CAST(:context AS jsonb))");
        $stmt->execute([
            ':id' => $idSession,
            ':user' => $idUsuario,
            ':sport' => $modalityId !== false ? (string) $modalityId : null,
            ':date' => !empty($training['date']) ? (string) $training['date'] : null,
            ':time' => !empty($training['time']) ? (string) $training['time'] : null,
            ':title' => (string) ($training['title'] ?? 'Treino institucional'),
            ':ref' => $trainingRef,
            ':recipient' => $recipientRef,
            ':context' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
        sessaoCopiarExercicios($pdo, $idSession, $normalized);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e->getCode() === '23505') {
            $active = sessaoCarregar($pdo, $idUsuario);
            if ($active !== []) throw new WorkoutSessionAlreadyActiveException($active);
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    productAnalyticsRegistrar($pdo, $idUsuario, 'workout_started', ['source' => 'teams']);
    return sessaoCarregarPorId($pdo, $idUsuario, $idSession);
}

function sessaoAtualizarSerie(PDO $pdo, string $idUsuario, string $idSerie, mixed $repeticoes, mixed $carga, bool $propagateLoad = false, string $editedField = '', array $inherited = [], ?string $expectedSessionId = null, mixed $duracao = null, mixed $distancia = null): array
{
    if (trim($idSerie) === '') throw new InvalidArgumentException(stridebr_t('workout_session.invalid_field'));
    $columns = ['load'=>'carga_realizada','reps'=>'repeticoes_realizadas','duration'=>'duracao_realizada_s','distance'=>'distancia_realizada_m'];
    if ($editedField !== '' && !isset($columns[$editedField])) throw new InvalidArgumentException(stridebr_t('workout_session.invalid_field'));
    $input = ['load'=>$carga,'reps'=>$repeticoes,'duration'=>$duracao,'distance'=>$distancia];
    $values = [];
    foreach ($input as $field=>$value) {
        if ($editedField !== '' && $editedField !== $field) continue;
        if ($editedField === '' && $value === null && in_array($field, ['duration','distance'], true)) continue;
        $values[$field] = match ($field) { 'load'=>sessaoSerieCarga($value), 'reps'=>sessaoSerieRepeticoes($value), default=>sessaoSerieMetrica($value, $field) };
    }
    $fieldDefaults = isset($inherited['load']) && is_array($inherited['load']) || isset($inherited['reps']) && is_array($inherited['reps']) || isset($inherited['duration']) && is_array($inherited['duration']) || isset($inherited['distance']) && is_array($inherited['distance']) ? $inherited : ['load'=>$inherited];
    $pdo->beginTransaction();
    try {
        $lockSql = "SELECT s.idsessao FROM sessoes_treino s JOIN sessoes_treino_exercicios se ON se.idsessao = s.idsessao JOIN sessoes_treino_series st ON st.idsessao_exercicio = se.idsessao_exercicio WHERE st.idserie = :serie AND s.idusuario = :usuario AND s.status = 'ativo'";
        $lockParams = [':serie' => $idSerie, ':usuario' => $idUsuario];
        if ($expectedSessionId !== null) {
            $lockSql .= ' AND s.idsessao = :expected';
            $lockParams[':expected'] = $expectedSessionId;
        }
        $lockSql .= ' FOR UPDATE OF s';
        $lock = $pdo->prepare($lockSql);
        $lock->execute($lockParams);
        $sessionId = $lock->fetchColumn();
        if (!$sessionId) throw new RuntimeException('Série não encontrada.');
        $setsQuery = $pdo->prepare('SELECT * FROM sessoes_treino_series WHERE idsessao_exercicio = (SELECT idsessao_exercicio FROM sessoes_treino_series WHERE idserie = :serie) ORDER BY numero FOR UPDATE');
        $setsQuery->execute([':serie' => $idSerie]);
        $sets = $setsQuery->fetchAll();
        foreach ($sets as &$set) $set['concluida'] = stridebr_db_bool($set['concluida']);
        unset($set);
        $source = array_values(array_filter($sets, static fn(array $set): bool => (string) $set['idserie'] === $idSerie))[0] ?? null;
        if (!$source) throw new RuntimeException(stridebr_t('workout_session.invalid_field'));
        if ($source['concluida']) throw new InvalidArgumentException(stridebr_t('workout_session.completed_set_locked'));
        foreach ($values as $field=>$value) {
            $column = $columns[$field]; // Whitelisted internal column, never request-controlled SQL.
            $defaults = is_array($fieldDefaults[$field] ?? null) ? $fieldDefaults[$field] : [];
            $targets = $propagateLoad && ($editedField === $field || $editedField === '' && $field === 'load') ? stridebr_workout_field_targets($sets, $idSerie, $defaults, $field) : [];
            $update = $pdo->prepare("UPDATE sessoes_treino_series SET {$column}=:value WHERE idserie=:serie AND concluida=FALSE");
            $update->execute([':value'=>$value, ':serie'=>$idSerie]);
            $defaults[$idSerie] = null; // Explicit edit establishes this field's manual boundary.
            foreach ($targets as $target) { $update->execute([':value'=>$value, ':serie'=>$target]); $defaults[$target]=(string) $value; }
            $fieldDefaults[$field] = $defaults;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return ['session'=>sessaoCarregarPorId($pdo,$idUsuario,(string) $sessionId,false), 'field_defaults'=>$fieldDefaults, 'load_defaults'=>$fieldDefaults['load'] ?? []];
}

function sessaoAlternarSerie(PDO $pdo, string $idUsuario, string $idSerie, bool $done, ?string $expectedSessionId = null): array
{
    $pdo->beginTransaction();
    try {
        $lockSql = "SELECT s.idsessao, st.idsessao_exercicio FROM sessoes_treino s JOIN sessoes_treino_exercicios se ON se.idsessao = s.idsessao JOIN sessoes_treino_series st ON st.idsessao_exercicio = se.idsessao_exercicio WHERE st.idserie = :serie AND s.idusuario = :usuario AND s.status = 'ativo'";
        $lockParams = [':serie' => $idSerie, ':usuario' => $idUsuario];
        if ($expectedSessionId !== null) {
            $lockSql .= ' AND s.idsessao = :expected';
            $lockParams[':expected'] = $expectedSessionId;
        }
        $lockSql .= ' FOR UPDATE OF s';
        $lock = $pdo->prepare($lockSql);
        $lock->execute($lockParams);
        $state = $lock->fetch();
        if (!$state) throw new RuntimeException('Série não encontrada.');
        $stmt = $pdo->prepare('UPDATE sessoes_treino_series SET concluida = :done, data_conclusao = CASE WHEN :done2 THEN NOW() ELSE NULL END WHERE idserie = :serie');
        $stmt->bindValue(':done', $done, PDO::PARAM_BOOL);
        $stmt->bindValue(':done2', $done, PDO::PARAM_BOOL);
        $stmt->bindValue(':serie', $idSerie, PDO::PARAM_STR);
        $stmt->execute();
        $exercise = $pdo->prepare('UPDATE sessoes_treino_exercicios SET concluido = NOT EXISTS (SELECT 1 FROM sessoes_treino_series WHERE idsessao_exercicio = :exercise_check AND concluida = FALSE) WHERE idsessao_exercicio = :exercise');
        $exercise->execute([':exercise_check' => $state['idsessao_exercicio'], ':exercise' => $state['idsessao_exercicio']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return sessaoCarregarPorId($pdo, $idUsuario, (string) $state['idsessao'], false);
}

function sessaoAlternarExercicio(PDO $pdo, string $idUsuario, string $id, bool $done, ?string $expectedSessionId = null): array
{
    $pdo->beginTransaction();
    try {
        $lockSql = "SELECT s.idsessao FROM sessoes_treino_exercicios se JOIN sessoes_treino s ON s.idsessao = se.idsessao WHERE se.idsessao_exercicio = :id AND s.idusuario = :usuario AND s.status = 'ativo'";
        $lockParams = [':id' => $id, ':usuario' => $idUsuario];
        if ($expectedSessionId !== null) {
            $lockSql .= ' AND s.idsessao = :expected';
            $lockParams[':expected'] = $expectedSessionId;
        }
        $lockSql .= ' FOR UPDATE OF s';
        $lock = $pdo->prepare($lockSql);
        $lock->execute($lockParams);
        $sessionId = $lock->fetchColumn();
        if (!$sessionId) throw new RuntimeException('Exercício não encontrado.');
        $stmt = $pdo->prepare('UPDATE sessoes_treino_exercicios SET concluido = :done WHERE idsessao_exercicio = :id');
        $stmt->bindValue(':done', $done, PDO::PARAM_BOOL);
        $stmt->bindValue(':id', $id, PDO::PARAM_STR);
        $stmt->execute();
        $sets = $pdo->prepare('UPDATE sessoes_treino_series SET concluida = :done, data_conclusao = CASE WHEN :done2 THEN COALESCE(data_conclusao, NOW()) ELSE NULL END WHERE idsessao_exercicio = :id');
        $sets->bindValue(':done', $done, PDO::PARAM_BOOL);
        $sets->bindValue(':done2', $done, PDO::PARAM_BOOL);
        $sets->bindValue(':id', $id, PDO::PARAM_STR);
        $sets->execute();
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return sessaoCarregarPorId($pdo, $idUsuario, (string) $sessionId, false);
}

function sessaoMarcarTudo(PDO $pdo, string $idUsuario, bool $done, ?string $expectedSessionId = null): array
{
    $pdo->beginTransaction();
    try {
        $sql = "SELECT idsessao FROM sessoes_treino WHERE idusuario = :usuario AND status = 'ativo'";
        $params = [':usuario' => $idUsuario];
        if ($expectedSessionId !== null) {
            $sql .= ' AND idsessao = :sessao';
            $params[':sessao'] = $expectedSessionId;
        }
        $sql .= ' ORDER BY data_inicio DESC LIMIT 1 FOR UPDATE';
        $lock = $pdo->prepare($sql);
        $lock->execute($params);
        $sessionId = $lock->fetchColumn();
        if (!$sessionId) throw new RuntimeException('Nenhum treino em andamento.');
        $exerciseStmt = $pdo->prepare('UPDATE sessoes_treino_exercicios SET concluido = :done WHERE idsessao = :sessao');
        $exerciseStmt->bindValue(':done', $done, PDO::PARAM_BOOL);
        $exerciseStmt->bindValue(':sessao', $sessionId, PDO::PARAM_STR);
        $exerciseStmt->execute();
        $setStmt = $pdo->prepare('UPDATE sessoes_treino_series st SET concluida = :done, data_conclusao = CASE WHEN :done2 THEN COALESCE(st.data_conclusao, NOW()) ELSE NULL END FROM sessoes_treino_exercicios se WHERE st.idsessao_exercicio = se.idsessao_exercicio AND se.idsessao = :sessao');
        $setStmt->bindValue(':done', $done, PDO::PARAM_BOOL);
        $setStmt->bindValue(':done2', $done, PDO::PARAM_BOOL);
        $setStmt->bindValue(':sessao', $sessionId, PDO::PARAM_STR);
        $setStmt->execute();
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return sessaoCarregarPorId($pdo, $idUsuario, (string) $sessionId, false);
}

function sessaoRegistroRapidoCronograma(PDO $pdo, string $idUsuario, string $idTreino, array $payload): array
{
    $treino = cronogramaBuscarTreino($pdo, $idTreino, $idUsuario);
    if ($treino === []) throw new RuntimeException('Treino não encontrado.');
    $tz = new DateTimeZone('America/Sao_Paulo');
    $today = new DateTimeImmutable('now', $tz);
    $data = trim((string) ($payload['data'] ?? $today->format('Y-m-d')));
    $hora = trim((string) ($payload['hora'] ?? substr((string) $treino['hora_inicio'], 0, 5)));
    $duracaoRaw = trim((string) ($payload['duracao_minutos'] ?? ''));
    $duracao = $duracaoRaw !== '' && $duracaoRaw !== '0' ? filter_var($duracaoRaw, FILTER_VALIDATE_INT) : null;
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $data, $tz);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $data) throw new InvalidArgumentException('Data inválida.');
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) throw new InvalidArgumentException('Hora inválida.');
    if ($duracao !== null && ($duracao === false || $duracao < 1 || $duracao > 1440)) throw new InvalidArgumentException('Duração inválida.');
    [$intensidade, $feeling, $feedback] = sessaoFeedbackPayload($payload);
    $dataOcorrenciaOrigem = trim((string) ($payload['data_ocorrencia_origem'] ?? '')) ?: null;
    $dataOcorrenciaPlanejada = trim((string) ($payload['data_ocorrencia_planejada'] ?? '')) ?: null;
    foreach ([$dataOcorrenciaOrigem, $dataOcorrenciaPlanejada] as $date) if ($date !== null) cronogramaValidarDataIso($date);
    $horaOcorrenciaPlanejada = trim((string) ($payload['hora_ocorrencia_planejada'] ?? '')) ?: null;
    if ($horaOcorrenciaPlanejada !== null && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $horaOcorrenciaPlanejada)) throw new InvalidArgumentException('Hora planejada inválida.');
    if ($dataOcorrenciaOrigem === null || $dataOcorrenciaPlanejada === null) {
        $inferred = cronogramaInferirOcorrenciaParaData($pdo, $idUsuario, $idTreino, $data);
        $dataOcorrenciaOrigem ??= $inferred['data_ocorrencia_origem'] ?? null;
        $dataOcorrenciaPlanejada ??= $inferred['data_ocorrencia_planejada'] ?? null;
        $horaOcorrenciaPlanejada ??= $inferred['hora_ocorrencia_planejada'] ?? null;
    }
    $inicio = new DateTimeImmutable($data . ' ' . $hora, $tz);
    $fim = $duracao !== null ? $inicio->modify('+' . $duracao . ' minutes') : null;
    $countStmt = $pdo->prepare('SELECT COUNT(*) AS total_exercises, COALESCE(SUM(CASE WHEN series IS NULL OR series < 1 THEN 1 WHEN series > 99 THEN 99 ELSE series END), 0) AS total_sets FROM treinos_exercicios WHERE idtreino = :treino');
    $countStmt->execute([':treino' => $idTreino]);
    $counts = $countStmt->fetch() ?: [];
    $activityId = sessaoSalvarAtividade($pdo, $idUsuario, (string) $treino['titulo'], (string) $treino['idcronograma'], $idTreino, $inicio, $fim, (int) ($counts['total_exercises'] ?? 0), (int) ($counts['total_exercises'] ?? 0), (int) ($counts['total_sets'] ?? 0), (int) ($counts['total_sets'] ?? 0), 'Registro rápido do cronograma.', $intensidade, $feeling === false ? null : $feeling, $feedback, $dataOcorrenciaOrigem, $dataOcorrenciaPlanejada, $horaOcorrenciaPlanejada, trim((string) ($treino['idmodalidade'] ?? '')) ?: null);
    return ['activity_id' => $activityId];
}

function sessaoRegistroRapidoAgendado(PDO $pdo, string $idUsuario, string $idAgendamento, array $payload): array
{
    $stmt = $pdo->prepare("SELECT ta.*, COALESCE(ta.idmodalidade, tc.idmodalidade, tm.idmodalidade) AS resolved_idmodalidade FROM treinos_agendados ta LEFT JOIN treinos_cronograma tc ON tc.idtreino = ta.idtreino_origem LEFT JOIN treinos_modelo tm ON tm.idtreino_modelo = COALESCE(ta.idtreino_modelo_origem, tc.idtreino_modelo) WHERE ta.idagendamento = :id AND ta.idatleta = :usuario AND ta.status = 'publicado' LIMIT 1");
    $stmt->execute([':id' => $idAgendamento, ':usuario' => $idUsuario]);
    $row = $stmt->fetch();
    if (!$row) throw new RuntimeException('Treino agendado não encontrado ou indisponível.');
    $tz = new DateTimeZone('America/Sao_Paulo');
    $data = trim((string) ($payload['data'] ?? $row['data_treino']));
    $defaultTime = $row['hora_inicio'] !== null ? substr((string) $row['hora_inicio'], 0, 5) : (new DateTimeImmutable('now', $tz))->format('H:i');
    $hora = trim((string) ($payload['hora'] ?? $defaultTime));
    $duracaoRaw = trim((string) ($payload['duracao_minutos'] ?? ''));
    $duracao = $duracaoRaw !== '' && $duracaoRaw !== '0' ? filter_var($duracaoRaw, FILTER_VALIDATE_INT) : null;
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $data, $tz);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $data) throw new InvalidArgumentException('Data inválida.');
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) throw new InvalidArgumentException('Hora inválida.');
    if ($duracao !== null && ($duracao === false || $duracao < 1 || $duracao > 1440)) throw new InvalidArgumentException('Duração inválida.');
    [$intensidade, $feeling, $feedback] = sessaoFeedbackPayload($payload);
    $countStmt = $pdo->prepare('SELECT COUNT(*) AS total_exercises, COALESCE(SUM(CASE WHEN series IS NULL OR series < 1 THEN 1 WHEN series > 99 THEN 99 ELSE series END), 0) AS total_sets FROM treinos_agendados_exercicios WHERE idagendamento = :id');
    $countStmt->execute([':id' => $idAgendamento]);
    $counts = $countStmt->fetch() ?: [];
    $inicio = new DateTimeImmutable($data . ' ' . $hora, $tz);
    $fim = $duracao !== null ? $inicio->modify('+' . $duracao . ' minutes') : null;
    $activityId = sessaoSalvarAtividade($pdo, $idUsuario, (string) $row['titulo'], $row['idcronograma_origem'] ?: null, $row['idtreino_origem'] ?: null, $inicio, $fim, (int) ($counts['total_exercises'] ?? 0), (int) ($counts['total_exercises'] ?? 0), (int) ($counts['total_sets'] ?? 0), (int) ($counts['total_sets'] ?? 0), 'Registro rápido do agendamento.', $intensidade, $feeling === false ? null : $feeling, $feedback, null, (string) $row['data_treino'], $row['hora_inicio'] !== null ? substr((string) $row['hora_inicio'], 0, 5) : null, trim((string) ($row['resolved_idmodalidade'] ?? '')) ?: null);
    return ['activity_id' => $activityId];
}

function sessaoCancelar(PDO $pdo, string $idUsuario, ?string $idSessao = null): array
{
    $params = [':usuario' => $idUsuario];
    $where = "idusuario = :usuario AND status = 'ativo'";
    if ($idSessao !== null && $idSessao !== '') {
        $where .= ' AND idsessao = :sessao';
        $params[':sessao'] = $idSessao;
    }
    $stmt = $pdo->prepare("UPDATE sessoes_treino SET status = 'cancelado', data_fim = NOW(), data_atualizacao = NOW() WHERE {$where} RETURNING idsessao");
    $stmt->execute($params);
    $id = $stmt->fetchColumn();
    if ($id === false && $idSessao !== null) {
        $existing = sessaoCarregarPorId($pdo, $idUsuario, $idSessao, false);
        if ($existing === []) throw new RuntimeException('Sessão não encontrada.');
        if ((string) $existing['status'] !== 'cancelado') throw new RuntimeException('Esta sessão não pode ser cancelada.');
        return $existing;
    }
    return $id !== false ? sessaoCarregarPorId($pdo, $idUsuario, (string) $id, false) : [];
}

function sessaoFinalizar(PDO $pdo, string $idUsuario, string $idSessao, array $payload): array
{
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT status, idregistro_atividade FROM sessoes_treino WHERE idsessao = :sessao AND idusuario = :usuario FOR UPDATE');
        $lock->execute([':sessao' => $idSessao, ':usuario' => $idUsuario]);
        $state = $lock->fetch();
        if (!$state) throw new RuntimeException('Sessão não encontrada.');
        if ((string) $state['status'] === 'concluido' && !empty($state['idregistro_atividade'])) {
            $session = sessaoCarregarPorId($pdo, $idUsuario, $idSessao, false);
            $summary = sessaoResumoConclusao($session);
            $startDone = !empty($session['data_inicio']) ? new DateTimeImmutable((string) $session['data_inicio']) : null;
            $endDone = !empty($session['data_fim']) ? new DateTimeImmutable((string) $session['data_fim']) : null;
            $summary['duracao_segundos'] = $startDone !== null && $endDone !== null ? max(1, $endDone->getTimestamp() - $startDone->getTimestamp()) : 0;
            $summary['titulo'] = (string) ($session['titulo_snapshot'] ?? '');
            $summary['intensidade'] = '';
            $summary['sensacao'] = null;
            $pdo->commit();
            return ['session' => $session, 'activity_id' => (string) $state['idregistro_atividade'], 'summary' => $summary, 'reused' => true];
        }
        if ((string) $state['status'] !== 'ativo') throw new RuntimeException('Esta sessão não está em andamento.');
        $session = sessaoCarregarPorId($pdo, $idUsuario, $idSessao, false);
        $tz = new DateTimeZone('America/Sao_Paulo');
        $start = (new DateTimeImmutable((string) $session['data_inicio']))->setTimezone($tz);
        $end = new DateTimeImmutable('now', $tz);
        $startRaw = str_replace('T', ' ', trim((string) ($payload['inicio_real'] ?? '')));
        $endRaw = str_replace('T', ' ', trim((string) ($payload['fim_real'] ?? '')));
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
        $durationSeconds = $end->getTimestamp() - $start->getTimestamp();
        if ($durationSeconds < 60) throw new InvalidArgumentException('A duração do treino precisa ter pelo menos 1 minuto.');
        if ($durationSeconds > 86400) throw new InvalidArgumentException('A duração do treino não pode passar de 24 horas.');
        if ($end > (new DateTimeImmutable('now', $tz))->modify('+10 minutes')) throw new InvalidArgumentException('O término do treino não pode ficar no futuro.');
        [$intensidade, $feeling, $feedback] = sessaoFeedbackPayload($payload);
        $summary = sessaoResumoConclusao($session);
        $source = (($session['origem_externa'] ?? '') === 'teams') ? 'Treino institucional concluído no StrideBR.' : (!empty($session['idagendamento_origem']) ? 'Treino iniciado pela agenda/prescrição.' : 'Treino iniciado pelo cronograma.');
        $activityId = sessaoSalvarAtividade($pdo, $idUsuario, (string) $session['titulo_snapshot'], $session['idcronograma_origem'] ?: null, $session['idtreino_origem'] ?: null, $start, $end, $summary['exercicios_concluidos'], $summary['exercicios_total'], $summary['series_concluidas'], $summary['series_total'], $source, $intensidade, $feeling === false ? null : $feeling, $feedback, !empty($session['data_ocorrencia_origem']) ? (string) $session['data_ocorrencia_origem'] : null, !empty($session['data_ocorrencia_planejada']) ? (string) $session['data_ocorrencia_planejada'] : null, !empty($session['hora_ocorrencia_planejada']) ? substr((string) $session['hora_ocorrencia_planejada'], 0, 5) : null, !empty($session['idmodalidade_origem']) ? (string) $session['idmodalidade_origem'] : null);
        $stmt = $pdo->prepare("UPDATE sessoes_treino SET status = 'concluido', data_inicio = :inicio, data_fim = :fim, idregistro_atividade = :registro, data_atualizacao = NOW() WHERE idsessao = :sessao AND idusuario = :usuario AND status = 'ativo'");
        $stmt->execute([':inicio' => $start->format(DateTimeInterface::ATOM), ':fim' => $end->format(DateTimeInterface::ATOM), ':registro' => $activityId, ':sessao' => $idSessao, ':usuario' => $idUsuario]);
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
    $summary['duracao_segundos'] = max(1, $end->getTimestamp() - $start->getTimestamp());
    $summary['titulo'] = (string) $session['titulo_snapshot'];
    $summary['intensidade'] = $intensidade;
    $summary['sensacao'] = $feeling === false ? null : $feeling;
    productAnalyticsRegistrar($pdo, $idUsuario, 'workout_completed', ['duration_min' => (int) round($summary['duracao_segundos'] / 60), 'sets_done' => $summary['series_concluidas'], 'exercises_done' => $summary['exercicios_concluidos']]);
    productAnalyticsRegistrar($pdo, $idUsuario, 'activity_saved', ['source' => 'workout_session']);
    return ['session' => null, 'activity_id' => $activityId, 'summary' => $summary, 'reused' => false];
}

function sessaoResumoConclusao(array $session): array
{
    $exercises = (array) ($session['exercicios'] ?? []);
    $totalExercises = count($exercises);
    $doneExercises = count(array_filter($exercises, static fn(array $row): bool => !empty($row['concluido'])));
    $totalSets = 0;
    $doneSets = 0;
    foreach ($exercises as $exercise) {
        $sets = (array) ($exercise['series'] ?? []);
        $totalSets += count($sets);
        $doneSets += count(array_filter($sets, static fn(array $set): bool => !empty($set['concluida'])));
    }
    return ['exercicios_concluidos' => $doneExercises, 'exercicios_total' => $totalExercises, 'series_concluidas' => $doneSets, 'series_total' => $totalSets];
}
