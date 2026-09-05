<?php

declare(strict_types=1);

require_once __DIR__ . '/atividade_modelo.php';

function atividadeForcaNumeroDecimal(mixed $value, float $min, float $max, int $decimals = 3): ?float
{
    $raw = str_replace(',', '.', trim((string) $value));
    if ($raw === '') return null;
    if (!is_numeric($raw)) throw new InvalidArgumentException('Há um valor numérico inválido nas séries.');
    $number = (float) $raw;
    if ($number < $min || $number > $max) throw new InvalidArgumentException('Há um valor fora do intervalo permitido nas séries.');
    return round($number, $decimals);
}

function atividadeForcaNumeroInteiro(mixed $value, int $min, int $max): ?int
{
    $raw = trim((string) $value);
    if ($raw === '') return null;
    if (preg_match('/^-?\d+$/', $raw) !== 1) throw new InvalidArgumentException('Há um valor inteiro inválido nas séries.');
    $number = (int) $raw;
    if ($number < $min || $number > $max) throw new InvalidArgumentException('Há um valor fora do intervalo permitido nas séries.');
    return $number;
}

function atividadeForcaNormalizarEntrada(array $source): array
{
    $normalized = [];
    $exerciseOrder = 0;
    foreach (array_slice($source, 0, 80) as $exercise) {
        if (!is_array($exercise)) continue;
        $name = trim((string) ($exercise['nome'] ?? ''));
        $idExercise = trim((string) ($exercise['idexercicio'] ?? ''));
        if (stridebr_length($name) > 160) throw new InvalidArgumentException('O nome de um exercício é muito longo.');
        $sets = [];
        $setOrder = 0;
        foreach (array_slice((array) ($exercise['series'] ?? []), 0, 100) as $set) {
            if (!is_array($set)) continue;
            $load = atividadeForcaNumeroDecimal($set['carga_kg'] ?? null, 0, 9999.999);
            $reps = atividadeForcaNumeroInteiro($set['repeticoes'] ?? null, 0, 999);
            $rir = atividadeForcaNumeroDecimal($set['rir'] ?? null, 0, 10, 1);
            $rpe = atividadeForcaNumeroDecimal($set['rpe'] ?? null, 1, 10, 1);
            $duration = atividadeForcaNumeroInteiro($set['duracao_segundos'] ?? null, 0, 86400);
            $distance = atividadeForcaNumeroDecimal($set['distancia_metros'] ?? null, 0, 1000000);
            $type = trim((string) ($set['tipo'] ?? 'trabalho'));
            if (!in_array($type, ['aquecimento', 'trabalho', 'drop', 'falha', 'outro'], true)) $type = 'trabalho';
            $hasData = $load !== null || $reps !== null || $rir !== null || $rpe !== null || $duration !== null || $distance !== null;
            if (!$hasData) continue;
            $setOrder++;
            $sets[] = [
                'ordem_serie' => $setOrder,
                'tipo' => $type,
                'carga_kg' => $load,
                'repeticoes' => $reps,
                'duracao_segundos' => $duration,
                'distancia_metros' => $distance,
                'rir' => $rir,
                'rpe' => $rpe,
                'concluida' => !array_key_exists('concluida', $set) || stridebr_db_bool($set['concluida']),
                'observacoes' => trim((string) ($set['observacoes'] ?? '')) ?: null,
            ];
        }
        if ($sets === [] && $name === '' && $idExercise === '') continue;
        if ($name === '' && $idExercise === '') throw new InvalidArgumentException('Escolha ou informe o exercício antes de registrar as séries.');
        $exerciseOrder++;
        $normalized[] = [
            'idexercicio' => $idExercise !== '' ? $idExercise : null,
            'nome' => $name,
            'ordem_exercicio' => $exerciseOrder,
            'series' => $sets,
        ];
    }
    return $normalized;
}

function atividadeForcaResolverExercicios(PDO $pdo, string $idUsuario, array $exercises): array
{
    $ids = [];
    foreach ($exercises as $exercise) {
        $id = trim((string) ($exercise['idexercicio'] ?? ''));
        if ($id !== '') $ids[$id] = true;
    }
    if ($ids === []) return $exercises;
    $params = [':usuario' => $idUsuario];
    $marks = [];
    foreach (array_keys($ids) as $index => $id) {
        $key = ':exercicio_' . $index;
        $marks[] = $key;
        $params[$key] = $id;
    }
    $stmt = $pdo->prepare('SELECT idexercicio, nome FROM exercicios WHERE ativo = TRUE AND (idusuario IS NULL OR idusuario = :usuario) AND idexercicio IN (' . implode(',', $marks) . ')');
    $stmt->execute($params);
    $allowed = [];
    foreach ($stmt->fetchAll() as $row) $allowed[(string) $row['idexercicio']] = (string) $row['nome'];
    foreach ($exercises as &$exercise) {
        $id = trim((string) ($exercise['idexercicio'] ?? ''));
        if ($id === '') continue;
        if (!isset($allowed[$id])) throw new InvalidArgumentException('Um dos exercícios selecionados não está disponível.');
        $exercise['nome'] = $allowed[$id];
    }
    unset($exercise);
    return $exercises;
}

function atividadeForcaPersistirSeriesManuais(PDO $pdo, string $idUsuario, string $idRegistro, array $source): array
{
    if (!stridebr_db_table_exists($pdo, 'series_exercicio_atividade')) {
        if ($source !== []) throw new RuntimeException('A estrutura de séries ainda não foi aplicada ao banco.');
        return [];
    }
    $owner = $pdo->prepare('SELECT 1 FROM registros_atividade WHERE idregistro = :registro AND idusuario = :usuario AND excluido_em IS NULL');
    $owner->execute([':registro' => $idRegistro, ':usuario' => $idUsuario]);
    if (!$owner->fetchColumn()) throw new RuntimeException('Atividade não encontrada.');
    $exercises = atividadeForcaResolverExercicios($pdo, $idUsuario, atividadeForcaNormalizarEntrada($source));
    $pdo->prepare('DELETE FROM series_exercicio_atividade WHERE idregistro = :registro')->execute([':registro' => $idRegistro]);
    if ($exercises === []) return [];
    $insert = $pdo->prepare(
        'INSERT INTO series_exercicio_atividade
        (idserie, idregistro, idexercicio, nome_exercicio, ordem_exercicio, ordem_serie, tipo, carga_kg, repeticoes, duracao_segundos, distancia_metros, rir, rpe, concluida, observacoes)
        VALUES (:id, :registro, :exercicio, :nome, :ordem_exercicio, :ordem_serie, :tipo, :carga, :reps, :duracao, :distancia, :rir, :rpe, :concluida, :observacoes)'
    );
    foreach ($exercises as $exercise) {
        foreach ($exercise['series'] as $set) {
            $insert->execute([
                ':id' => atividadeGerarId(),
                ':registro' => $idRegistro,
                ':exercicio' => $exercise['idexercicio'],
                ':nome' => $exercise['nome'],
                ':ordem_exercicio' => $exercise['ordem_exercicio'],
                ':ordem_serie' => $set['ordem_serie'],
                ':tipo' => $set['tipo'],
                ':carga' => $set['carga_kg'],
                ':reps' => $set['repeticoes'],
                ':duracao' => $set['duracao_segundos'],
                ':distancia' => $set['distancia_metros'],
                ':rir' => $set['rir'],
                ':rpe' => $set['rpe'],
                ':concluida' => $set['concluida'] ? 1 : 0,
                ':observacoes' => $set['observacoes'],
            ]);
        }
    }
    return $exercises;
}

function atividadeForcaBuscarSeries(PDO $pdo, string $idUsuario, string $idRegistro): array
{
    if (!stridebr_db_table_exists($pdo, 'series_exercicio_atividade')) return [];
    $stmt = $pdo->prepare(
        'SELECT s.* FROM series_exercicio_atividade s
         JOIN registros_atividade r ON r.idregistro = s.idregistro
         WHERE s.idregistro = :registro AND r.idusuario = :usuario AND r.excluido_em IS NULL
         ORDER BY s.ordem_exercicio, s.ordem_serie, s.idserie'
    );
    $stmt->execute([':registro' => $idRegistro, ':usuario' => $idUsuario]);
    $grouped = [];
    foreach ($stmt->fetchAll() as $row) {
        $order = (int) $row['ordem_exercicio'];
        if (!isset($grouped[$order])) {
            $grouped[$order] = [
                'idexercicio' => (string) ($row['idexercicio'] ?? ''),
                'nome' => (string) $row['nome_exercicio'],
                'ordem_exercicio' => $order,
                'series' => [],
            ];
        }
        $grouped[$order]['series'][] = [
            'ordem_serie' => (int) $row['ordem_serie'],
            'tipo' => (string) $row['tipo'],
            'carga_kg' => $row['carga_kg'] !== null ? (float) $row['carga_kg'] : null,
            'repeticoes' => $row['repeticoes'] !== null ? (int) $row['repeticoes'] : null,
            'duracao_segundos' => $row['duracao_segundos'] !== null ? (float) $row['duracao_segundos'] : null,
            'distancia_metros' => $row['distancia_metros'] !== null ? (float) $row['distancia_metros'] : null,
            'rir' => $row['rir'] !== null ? (float) $row['rir'] : null,
            'rpe' => $row['rpe'] !== null ? (float) $row['rpe'] : null,
            'concluida' => stridebr_db_bool($row['concluida']),
            'observacoes' => (string) ($row['observacoes'] ?? ''),
        ];
    }
    return array_values($grouped);
}

function atividadeForcaReferenciaExercicio(PDO $pdo, string $idUsuario, ?string $idExercicio, string $nome): array
{
    if (!stridebr_db_table_exists($pdo, 'series_exercicio_atividade')) return [];
    $idExercicio = trim((string) $idExercicio);
    $nome = trim($nome);
    if ($idExercicio === '' && $nome === '') return [];
    $where = $idExercicio !== '' ? 's.idexercicio = :exercicio' : 'lower(s.nome_exercicio) = lower(:nome)';
    $stmt = $pdo->prepare(
        "SELECT s.idexercicio, s.nome_exercicio, s.carga_kg, s.repeticoes, s.rir, s.rpe, s.tipo, s.ordem_serie,
                r.idregistro, r.titulo, r.data_inicio
         FROM series_exercicio_atividade s
         JOIN registros_atividade r ON r.idregistro = s.idregistro
         WHERE r.idusuario = :usuario AND r.excluido_em IS NULL AND r.status = 'concluido' AND s.concluida = TRUE AND {$where}
         ORDER BY r.data_inicio DESC, s.ordem_serie, s.idserie
         LIMIT 240"
    );
    $params = [':usuario' => $idUsuario];
    if ($idExercicio !== '') $params[':exercicio'] = $idExercicio;
    else $params[':nome'] = $nome;
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    if ($rows === []) return [];
    $sessions = [];
    $bestLoad = null;
    $bestE1rm = null;
    foreach ($rows as $row) {
        $recordId = (string) $row['idregistro'];
        $load = $row['carga_kg'] !== null ? (float) $row['carga_kg'] : null;
        $reps = $row['repeticoes'] !== null ? (int) $row['repeticoes'] : null;
        $e1rm = $load !== null && $reps !== null && $reps > 0 && $reps <= 12 ? $load * (1 + ($reps / 30)) : null;
        if ($load !== null) $bestLoad = $bestLoad === null ? $load : max($bestLoad, $load);
        if ($e1rm !== null) $bestE1rm = $bestE1rm === null ? $e1rm : max($bestE1rm, $e1rm);
        if (!isset($sessions[$recordId])) {
            $sessions[$recordId] = [
                'idregistro' => $recordId,
                'titulo' => (string) ($row['titulo'] ?? ''),
                'data_inicio' => (string) $row['data_inicio'],
                'series' => [],
                'melhor_carga_kg' => null,
                'melhor_e1rm_kg' => null,
                'volume_kg' => 0.0,
            ];
        }
        $sessions[$recordId]['series'][] = [
            'carga_kg' => $load,
            'repeticoes' => $reps,
            'rir' => $row['rir'] !== null ? (float) $row['rir'] : null,
            'rpe' => $row['rpe'] !== null ? (float) $row['rpe'] : null,
            'tipo' => (string) ($row['tipo'] ?? 'trabalho'),
        ];
        if ($load !== null) $sessions[$recordId]['melhor_carga_kg'] = $sessions[$recordId]['melhor_carga_kg'] === null ? $load : max($sessions[$recordId]['melhor_carga_kg'], $load);
        if ($e1rm !== null) $sessions[$recordId]['melhor_e1rm_kg'] = $sessions[$recordId]['melhor_e1rm_kg'] === null ? $e1rm : max($sessions[$recordId]['melhor_e1rm_kg'], $e1rm);
        if ($load !== null && $reps !== null) $sessions[$recordId]['volume_kg'] += $load * $reps;
    }
    $sessions = array_slice(array_values($sessions), 0, 3);
    return [
        'nome' => (string) ($rows[0]['nome_exercicio'] ?? $nome),
        'melhor_carga_kg' => $bestLoad !== null ? round($bestLoad, 3) : null,
        'melhor_e1rm_kg' => $bestE1rm !== null ? round($bestE1rm, 3) : null,
        'ultima_sessao' => $sessions[0] ?? null,
        'sessao_anterior' => $sessions[1] ?? null,
        'sessoes' => $sessions,
    ];
}
