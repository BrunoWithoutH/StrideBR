<?php

declare(strict_types=1);

require_once __DIR__ . '/exercise_resolver.php';


function cronogramaGerarId(int $length = 21): string
{
    return stridebr_generate_id($length);
}

/** Preserve the user's spelling while making whitespace and Unicode canonical. */
function cronogramaNormalizarNome(string $value): string
{
    $value = trim(str_replace("\u{00A0}", ' ', $value));
    if ($value === '') return '';
    if (class_exists('Normalizer')) {
        $normalized = Normalizer::normalize($value, Normalizer::FORM_C);
        if (is_string($normalized)) $value = $normalized;
    }
    return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
}

function cronogramaListar(PDO $pdo, string $idUsuario): array
{
    $stmt = $pdo->prepare('SELECT * FROM cronogramas WHERE idusuario = :usuario AND ativo = TRUE ORDER BY data_atualizacao DESC, nome');
    $stmt->execute([':usuario' => $idUsuario]);
    return $stmt->fetchAll();
}

function cronogramaBuscar(PDO $pdo, string $idCronograma, string $idUsuario): array
{
    $stmt = $pdo->prepare('SELECT * FROM cronogramas WHERE idcronograma = :id AND idusuario = :usuario LIMIT 1');
    $stmt->execute([':id' => $idCronograma, ':usuario' => $idUsuario]);
    return $stmt->fetch() ?: [];
}

function cronogramaCriar(PDO $pdo, string $idUsuario, string $nome, ?string $descricao = null): string
{
    $nome = cronogramaNormalizarNome($nome);
    if ($nome === '' || stridebr_length($nome) > 120) {
        throw new InvalidArgumentException(stridebr_t('schedule.validation.schedule_name'));
    }

    $stmt = $pdo->prepare('SELECT 1 FROM cronogramas WHERE idusuario = :usuario AND lower(nome) = lower(:nome) LIMIT 1');
    $stmt->execute([':usuario' => $idUsuario, ':nome' => $nome]);
    if ($stmt->fetchColumn()) {
        throw new InvalidArgumentException(stridebr_t('schedule.validation.schedule_duplicate'));
    }

    $id = cronogramaGerarId();
    $stmt = $pdo->prepare('INSERT INTO cronogramas (idcronograma, idusuario, nome, descricao) VALUES (:id, :usuario, :nome, :descricao)');
    $stmt->execute([
        ':id' => $id,
        ':usuario' => $idUsuario,
        ':nome' => $nome,
        ':descricao' => $descricao !== null && trim($descricao) !== '' ? trim($descricao) : null,
    ]);
    $member = $pdo->prepare("INSERT INTO cronograma_membros (idcronograma, idusuario, papel) VALUES (:cronograma, :usuario, 'owner') ON CONFLICT (idcronograma, idusuario) DO NOTHING");
    $member->execute([':cronograma' => $id, ':usuario' => $idUsuario]);
    return $id;
}

function cronogramaExcluir(PDO $pdo, string $idCronograma, string $idUsuario): bool
{
    $stmt = $pdo->prepare('DELETE FROM cronogramas WHERE idcronograma = :id AND idusuario = :usuario');
    $stmt->execute([':id' => $idCronograma, ':usuario' => $idUsuario]);
    return $stmt->rowCount() === 1;
}

function cronogramaListarTreinos(PDO $pdo, string $idCronograma, string $idUsuario): array
{
    if (cronogramaBuscar($pdo, $idCronograma, $idUsuario) === []) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT * FROM treinos_cronograma WHERE idcronograma = :cronograma ORDER BY dia_semana, hora_inicio, ordem');
    $stmt->execute([':cronograma' => $idCronograma]);
    return $stmt->fetchAll();
}

function cronogramaBuscarTreino(PDO $pdo, string $idTreino, string $idUsuario): array
{
    $stmt = $pdo->prepare(
        'SELECT t.*, c.nome AS cronograma_nome, c.idusuario FROM treinos_cronograma t JOIN cronogramas c ON c.idcronograma = t.idcronograma WHERE t.idtreino = :id AND c.idusuario = :usuario LIMIT 1'
    );
    $stmt->execute([':id' => $idTreino, ':usuario' => $idUsuario]);
    return $stmt->fetch() ?: [];
}

function cronogramaSalvarTreino(PDO $pdo, string $idUsuario, array $payload, ?string $idTreino = null): string
{
    $idCronograma = (string) ($payload['idcronograma'] ?? '');
    if (cronogramaBuscar($pdo, $idCronograma, $idUsuario) === []) {
        throw new RuntimeException(stridebr_t('planning.message.schedule_missing'));
    }

    $titulo = cronogramaNormalizarNome((string) ($payload['titulo'] ?? ''));
    $dia = filter_var($payload['dia_semana'] ?? null, FILTER_VALIDATE_INT);
    $inicio = (string) ($payload['hora_inicio'] ?? '');
    $fim = (string) ($payload['hora_fim'] ?? '');
    $nextDay = !empty($payload['termina_dia_seguinte']);
    $codigo = trim((string) ($payload['codigo'] ?? ''));
    $foco = trim((string) ($payload['foco'] ?? ''));
    $idModalidade = cronogramaValidarModalidadeTreino($pdo, $idUsuario, $payload['idmodalidade'] ?? null);
    $vigenciaInicioRaw = trim((string) ($payload['vigencia_inicio'] ?? ''));
    $vigenciaFimRaw = trim((string) ($payload['vigencia_fim'] ?? ''));
    $vigenciaInicio = $vigenciaInicioRaw !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $vigenciaInicioRaw) : new DateTimeImmutable('today');
    $vigenciaFim = $vigenciaFimRaw !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $vigenciaFimRaw) : null;
    if (!$vigenciaInicio || ($vigenciaInicioRaw !== '' && $vigenciaInicio->format('Y-m-d') !== $vigenciaInicioRaw)) {
        throw new InvalidArgumentException(stridebr_t('schedule.validation.start_invalid'));
    }
    if ($vigenciaFimRaw !== '' && (!$vigenciaFim || $vigenciaFim->format('Y-m-d') !== $vigenciaFimRaw)) {
        throw new InvalidArgumentException(stridebr_t('schedule.validation.end_invalid'));
    }
    if ($vigenciaFim && $vigenciaFim < $vigenciaInicio) {
        throw new InvalidArgumentException(stridebr_t('schedule.validation.end_before_start'));
    }

    if ($titulo === '' || stridebr_length($titulo) > 120) {
        throw new InvalidArgumentException(stridebr_t('schedule.validation.workout_title'));
    }
    if (stridebr_length($codigo) > 24) {
        throw new InvalidArgumentException(stridebr_t('schedule.validation.code_long'));
    }
    if (stridebr_length($foco) > 80) {
        throw new InvalidArgumentException(stridebr_t('schedule.validation.focus_long'));
    }
    if ($dia === false || $dia < 0 || $dia > 6) {
        throw new InvalidArgumentException(stridebr_t('schedule.invalid_weekday'));
    }
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $inicio) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $fim)) {
        throw new InvalidArgumentException(stridebr_t('planning.message.invalid_time'));
    }
    if (!$nextDay && $fim <= $inicio) {
        throw new InvalidArgumentException(stridebr_t('schedule.validation.end_time'));
    }
    if ($nextDay && $fim > $inicio) {
        throw new InvalidArgumentException(stridebr_t('schedule.validation.overnight_time'));
    }

    if ($idTreino !== null) {
        $existing = cronogramaBuscarTreino($pdo, $idTreino, $idUsuario);
        if ($existing === [] || $existing['idcronograma'] !== $idCronograma) {
            throw new RuntimeException(stridebr_t('schedule.workout_not_found'));
        }
        $stmt = $pdo->prepare('UPDATE treinos_cronograma SET titulo = :titulo, codigo = :codigo, foco = :foco, idmodalidade = :modalidade, descricao = :descricao, dia_semana = :dia, hora_inicio = :inicio, hora_fim = :fim, termina_dia_seguinte = :seguinte, vigencia_inicio = :vigencia_inicio, vigencia_fim = :vigencia_fim, data_atualizacao = NOW() WHERE idtreino = :id');
        $stmt->bindValue(':titulo', $titulo, PDO::PARAM_STR);
        $stmt->bindValue(':codigo', $codigo !== '' ? $codigo : null, $codigo !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':foco', $foco !== '' ? $foco : null, $foco !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':modalidade', $idModalidade, $idModalidade !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':descricao', trim((string) ($payload['descricao'] ?? '')) ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':dia', $dia, PDO::PARAM_INT);
        $stmt->bindValue(':inicio', $inicio, PDO::PARAM_STR);
        $stmt->bindValue(':fim', $fim, PDO::PARAM_STR);
        $stmt->bindValue(':seguinte', $nextDay, PDO::PARAM_BOOL);
        $stmt->bindValue(':vigencia_inicio', $vigenciaInicio->format('Y-m-d'), PDO::PARAM_STR);
        $stmt->bindValue(':vigencia_fim', $vigenciaFim?->format('Y-m-d'), $vigenciaFim ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':id', $idTreino, PDO::PARAM_STR);
        $stmt->execute();
        $id = $idTreino;
    } else {
        $id = cronogramaGerarId();
        $stmt = $pdo->prepare('INSERT INTO treinos_cronograma (idtreino, idcronograma, titulo, codigo, foco, idmodalidade, descricao, dia_semana, hora_inicio, hora_fim, termina_dia_seguinte, vigencia_inicio, vigencia_fim, ordem) VALUES (:id, :cronograma, :titulo, :codigo, :foco, :modalidade, :descricao, :dia, :inicio, :fim, :seguinte, :vigencia_inicio, :vigencia_fim, :ordem)');
        $orderStmt = $pdo->prepare('SELECT COALESCE(MAX(ordem), 0) + 1 FROM treinos_cronograma WHERE idcronograma = :cronograma AND dia_semana = :dia');
        $orderStmt->execute([':cronograma' => $idCronograma, ':dia' => $dia]);
        $ordem = (int) $orderStmt->fetchColumn();
        $stmt->bindValue(':id', $id, PDO::PARAM_STR);
        $stmt->bindValue(':cronograma', $idCronograma, PDO::PARAM_STR);
        $stmt->bindValue(':titulo', $titulo, PDO::PARAM_STR);
        $stmt->bindValue(':codigo', $codigo !== '' ? $codigo : null, $codigo !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':foco', $foco !== '' ? $foco : null, $foco !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':modalidade', $idModalidade, $idModalidade !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':descricao', trim((string) ($payload['descricao'] ?? '')) ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':dia', $dia, PDO::PARAM_INT);
        $stmt->bindValue(':inicio', $inicio, PDO::PARAM_STR);
        $stmt->bindValue(':fim', $fim, PDO::PARAM_STR);
        $stmt->bindValue(':seguinte', $nextDay, PDO::PARAM_BOOL);
        $stmt->bindValue(':vigencia_inicio', $vigenciaInicio->format('Y-m-d'), PDO::PARAM_STR);
        $stmt->bindValue(':vigencia_fim', $vigenciaFim?->format('Y-m-d'), $vigenciaFim ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':ordem', $ordem, PDO::PARAM_INT);
        $stmt->execute();
    }

    $pdo->prepare('UPDATE cronogramas SET data_atualizacao = NOW() WHERE idcronograma = :id')->execute([':id' => $idCronograma]);
    return $id;
}

function cronogramaExcluirTreino(PDO $pdo, string $idTreino, string $idUsuario): bool
{
    $treino = cronogramaBuscarTreino($pdo, $idTreino, $idUsuario);
    if ($treino === []) {
        return false;
    }
    $stmt = $pdo->prepare('DELETE FROM treinos_cronograma WHERE idtreino = :id');
    $stmt->execute([':id' => $idTreino]);
    return $stmt->rowCount() === 1;
}

function cronogramaCapturarTreinoParaDesfazer(PDO $pdo, string $idTreino, string $idUsuario): array
{
    if (cronogramaBuscarTreino($pdo, $idTreino, $idUsuario) === []) return [];
    $one = static function (PDO $pdo, string $sql, array $params): array {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: [];
    };
    $all = static function (PDO $pdo, string $sql, array $params): array {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    };
    $treino = $one($pdo, 'SELECT * FROM treinos_cronograma WHERE idtreino = :id', [':id' => $idTreino]);
    $campos = $all($pdo, 'SELECT * FROM campos_treino_exercicio WHERE idtreino = :id ORDER BY ordem', [':id' => $idTreino]);
    $exercicios = $all($pdo, 'SELECT * FROM treinos_exercicios WHERE idtreino = :id ORDER BY ordem', [':id' => $idTreino]);
    $valores = $all($pdo, 'SELECT v.* FROM valores_treino_exercicio v JOIN treinos_exercicios e ON e.idtreino_exercicio = v.idtreino_exercicio WHERE e.idtreino = :id', [':id' => $idTreino]);
    $excecoes = $all($pdo, 'SELECT * FROM treinos_cronograma_excecoes WHERE idtreino = :id', [':id' => $idTreino]);
    $sessoes = $all($pdo, 'SELECT idsessao FROM sessoes_treino WHERE idtreino_origem = :id', [':id' => $idTreino]);
    $agendamentos = $all($pdo, 'SELECT idagendamento FROM treinos_agendados WHERE idtreino_origem = :id', [':id' => $idTreino]);
    $atividades = $all($pdo, 'SELECT idregistro FROM registros_atividade WHERE idtreino_cronograma = :id AND excluido_em IS NULL', [':id' => $idTreino]);
    $preferencias = $all($pdo, 'SELECT idusuario, idcronograma, semana_inicio FROM cronograma_semana_preferencias WHERE idtreino_proximo = :id', [':id' => $idTreino]);
    return compact('treino', 'campos', 'exercicios', 'valores', 'excecoes', 'sessoes', 'agendamentos', 'atividades', 'preferencias');
}

function cronogramaRestaurarTreinoExcluido(PDO $pdo, string $idUsuario, array $snapshot): string
{
    $treino = is_array($snapshot['treino'] ?? null) ? $snapshot['treino'] : [];
    $idTreino = trim((string) ($treino['idtreino'] ?? ''));
    $idCronograma = trim((string) ($treino['idcronograma'] ?? ''));
    if ($idTreino === '' || $idCronograma === '' || cronogramaBuscar($pdo, $idCronograma, $idUsuario) === []) {
        throw new RuntimeException(stridebr_t('schedule.validation.restore_error'));
    }
    if (cronogramaBuscarTreino($pdo, $idTreino, $idUsuario) !== []) return $idTreino;
    $insertRow = static function (PDO $pdo, string $table, array $row): void {
        if ($row === []) return;
        foreach (array_keys($row) as $column) {
            if (!preg_match('/^[a-z_][a-z0-9_]*$/i', (string) $column)) throw new RuntimeException(stridebr_t('schedule.validation.restore_invalid'));
        }
        $columns = array_keys($row);
        $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);
        $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        foreach ($row as $column => $value) {
            if ($value === null) $stmt->bindValue(':' . $column, null, PDO::PARAM_NULL);
            elseif (is_bool($value)) $stmt->bindValue(':' . $column, $value, PDO::PARAM_BOOL);
            else $stmt->bindValue(':' . $column, $value);
        }
        $stmt->execute();
    };
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $insertRow($pdo, 'treinos_cronograma', $treino);
        foreach (($snapshot['campos'] ?? []) as $row) if (is_array($row)) $insertRow($pdo, 'campos_treino_exercicio', $row);
        foreach (($snapshot['exercicios'] ?? []) as $row) if (is_array($row)) $insertRow($pdo, 'treinos_exercicios', $row);
        foreach (($snapshot['valores'] ?? []) as $row) if (is_array($row)) $insertRow($pdo, 'valores_treino_exercicio', $row);
        foreach (($snapshot['excecoes'] ?? []) as $row) if (is_array($row)) $insertRow($pdo, 'treinos_cronograma_excecoes', $row);
        $relink = [
            ['table' => 'sessoes_treino', 'pk' => 'idsessao', 'rows' => $snapshot['sessoes'] ?? [], 'column' => 'idtreino_origem'],
            ['table' => 'treinos_agendados', 'pk' => 'idagendamento', 'rows' => $snapshot['agendamentos'] ?? [], 'column' => 'idtreino_origem'],
            ['table' => 'registros_atividade', 'pk' => 'idregistro', 'rows' => $snapshot['atividades'] ?? [], 'column' => 'idtreino_cronograma'],
        ];
        foreach ($relink as $link) {
            foreach ($link['rows'] as $row) {
                $pk = $row[$link['pk']] ?? null;
                if ($pk === null) continue;
                $stmt = $pdo->prepare('UPDATE ' . $link['table'] . ' SET ' . $link['column'] . ' = :treino WHERE ' . $link['pk'] . ' = :id');
                $stmt->execute([':treino' => $idTreino, ':id' => $pk]);
            }
        }
        foreach (($snapshot['preferencias'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $stmt = $pdo->prepare('UPDATE cronograma_semana_preferencias SET idtreino_proximo = :treino WHERE idusuario = :usuario AND idcronograma = :cronograma AND semana_inicio = :semana');
            $stmt->execute([':treino' => $idTreino, ':usuario' => $row['idusuario'] ?? '', ':cronograma' => $row['idcronograma'] ?? '', ':semana' => $row['semana_inicio'] ?? null]);
        }
        $pdo->prepare('UPDATE cronogramas SET data_atualizacao = NOW() WHERE idcronograma = :id')->execute([':id' => $idCronograma]);
        if ($ownsTransaction) $pdo->commit();
        return $idTreino;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function cronogramaDuplicarTreino(PDO $pdo, string $idUsuario, string $idTreino): string
{
    $source = cronogramaBuscarTreino($pdo, $idTreino, $idUsuario);
    if ($source === []) {
        throw new RuntimeException(stridebr_t('schedule.workout_not_found'));
    }

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();

    try {
        $newId = cronogramaSalvarTreino($pdo, $idUsuario, [
            'idcronograma' => $source['idcronograma'],
            'titulo' => trim((string) $source['titulo']) . ' (cópia)',
            'codigo' => (string) ($source['codigo'] ?? ''),
            'foco' => (string) ($source['foco'] ?? ''),
            'idmodalidade' => (string) ($source['idmodalidade'] ?? ''),
            'descricao' => $source['descricao'] ?? null,
            'dia_semana' => (int) $source['dia_semana'],
            'hora_inicio' => substr((string) $source['hora_inicio'], 0, 5),
            'hora_fim' => substr((string) $source['hora_fim'], 0, 5),
            'termina_dia_seguinte' => stridebr_db_bool($source['termina_dia_seguinte'] ?? false) ? '1' : '',
            'vigencia_inicio' => (string) ($source['vigencia_inicio'] ?? date('Y-m-d')),
            'vigencia_fim' => (string) ($source['vigencia_fim'] ?? ''),
        ]);
        if (!empty($source['idtreino_modelo'])) {
            $pdo->prepare('UPDATE treinos_cronograma SET idtreino_modelo = :modelo WHERE idtreino = :treino')->execute([':modelo' => $source['idtreino_modelo'], ':treino' => $newId]);
        }

        $fieldMap = [];
        $fieldsStmt = $pdo->prepare('SELECT * FROM campos_treino_exercicio WHERE idtreino = :treino ORDER BY ordem');
        $fieldsStmt->execute([':treino' => $idTreino]);
        $insertField = $pdo->prepare('INSERT INTO campos_treino_exercicio (idcampo, idtreino, nome, slug, tipo, ordem, ativo) VALUES (:id, :treino, :nome, :slug, :tipo, :ordem, :ativo)');
        foreach ($fieldsStmt->fetchAll() as $field) {
            $newFieldId = cronogramaGerarId();
            $fieldMap[(string) $field['idcampo']] = $newFieldId;
            $insertField->execute([
                ':id' => $newFieldId,
                ':treino' => $newId,
                ':nome' => $field['nome'],
                ':slug' => $field['slug'],
                ':tipo' => $field['tipo'],
                ':ordem' => (int) $field['ordem'],
                ':ativo' => stridebr_db_bool($field['ativo']) ? 'true' : 'false',
            ]);
        }

        $exerciseMap = [];
        $exerciseStmt = $pdo->prepare('SELECT * FROM treinos_exercicios WHERE idtreino = :treino ORDER BY ordem');
        $exerciseStmt->execute([':treino' => $idTreino]);
        $insertExercise = $pdo->prepare('INSERT INTO treinos_exercicios (idtreino_exercicio, idtreino, idexercicio, nome_snapshot, series, repeticoes, carga, bloco, cluster, descanso, observacoes, duracao, distancia, intensidade, rpe, rir, tempo_execucao, cadencia, ordem) VALUES (:id, :treino, :exercicio, :nome, :series, :repeticoes, :carga, :bloco, :cluster, :descanso, :observacoes, :duracao, :distancia, :intensidade, :rpe, :rir, :tempo_execucao, :cadencia, :ordem)');
        foreach ($exerciseStmt->fetchAll() as $exercise) {
            $newExerciseId = cronogramaGerarId();
            $exerciseMap[(string) $exercise['idtreino_exercicio']] = $newExerciseId;
            $insertExercise->execute([
                ':id' => $newExerciseId,
                ':treino' => $newId,
                ':exercicio' => $exercise['idexercicio'] ?: null,
                ':nome' => $exercise['nome_snapshot'],
                ':series' => $exercise['series'] !== null ? (int) $exercise['series'] : null,
                ':repeticoes' => $exercise['repeticoes'] ?: null,
                ':carga' => $exercise['carga'] ?: null,
                ':bloco' => $exercise['bloco'] ?: null,
                ':cluster' => $exercise['cluster'] ?: null,
                ':descanso' => $exercise['descanso'] ?: null,
                ':observacoes' => $exercise['observacoes'] ?: null,
                ':duracao' => $exercise['duracao'] ?: null,
                ':distancia' => $exercise['distancia'] ?: null,
                ':intensidade' => $exercise['intensidade'] ?: null,
                ':rpe' => $exercise['rpe'] !== null && $exercise['rpe'] !== '' ? $exercise['rpe'] : null,
                ':rir' => $exercise['rir'] !== null && $exercise['rir'] !== '' ? $exercise['rir'] : null,
                ':tempo_execucao' => $exercise['tempo_execucao'] ?: null,
                ':cadencia' => $exercise['cadencia'] ?: null,
                ':ordem' => (int) $exercise['ordem'],
            ]);
        }

        if ($exerciseMap !== [] && $fieldMap !== []) {
            $valueStmt = $pdo->prepare('SELECT v.* FROM valores_treino_exercicio v JOIN treinos_exercicios te ON te.idtreino_exercicio = v.idtreino_exercicio WHERE te.idtreino = :treino');
            $valueStmt->execute([':treino' => $idTreino]);
            $insertValue = $pdo->prepare('INSERT INTO valores_treino_exercicio (idvalor, idtreino_exercicio, idcampo, valor_texto, valor_inteiro, valor_decimal, valor_booleano) VALUES (:id, :exercicio, :campo, :texto, :inteiro, :decimal, :booleano)');
            foreach ($valueStmt->fetchAll() as $value) {
                $oldExerciseId = (string) $value['idtreino_exercicio'];
                $oldFieldId = (string) $value['idcampo'];
                if (!isset($exerciseMap[$oldExerciseId], $fieldMap[$oldFieldId])) continue;
                $insertValue->execute([
                    ':id' => cronogramaGerarId(),
                    ':exercicio' => $exerciseMap[$oldExerciseId],
                    ':campo' => $fieldMap[$oldFieldId],
                    ':texto' => $value['valor_texto'],
                    ':inteiro' => $value['valor_inteiro'],
                    ':decimal' => $value['valor_decimal'],
                    ':booleano' => $value['valor_booleano'] === null ? null : (stridebr_db_bool($value['valor_booleano']) ? 'true' : 'false'),
                ]);
            }
        }

        if ($ownsTransaction) $pdo->commit();
        return $newId;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function cronogramaFormatarRepeticoes(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') return '';
    if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(?:mn|min|mins|minuto|minutos)$/iu', $value, $match) === 1) {
        return str_replace(',', '.', $match[1]) . ' min';
    }
    if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(?:s|seg|segs|segundo|segundos)$/iu', $value, $match) === 1) {
        return str_replace(',', '.', $match[1]) . ' s';
    }
    if (preg_match('/\b(?:rep|reps|repetiç(?:ão|ões))\b/iu', $value) === 1) return $value;
    return $value . ' reps';
}

function cronogramaDuracaoMinutos(array $treino): int
{
    $inicio = ((int) substr($treino['hora_inicio'], 0, 2)) * 60 + (int) substr($treino['hora_inicio'], 3, 2);
    $fim = ((int) substr($treino['hora_fim'], 0, 2)) * 60 + (int) substr($treino['hora_fim'], 3, 2);
    if (stridebr_db_bool($treino['termina_dia_seguinte'])) {
        $fim += 1440;
    }
    return max(1, $fim - $inicio);
}

function cronogramaListarExerciciosBiblioteca(PDO $pdo, string $idUsuario): array
{
    $stmt = $pdo->prepare(
        "SELECT e.idexercicio, e.nome, e.descricao, e.idusuario, e.imagem_url, e.video_url,
                COALESCE(string_agg(DISTINCT c.nome, ', ' ORDER BY c.nome), '') AS categorias,
                COALESCE(string_agg(DISTINCT m.nome, ', ' ORDER BY m.nome), '') AS modalidades
         FROM exercicios e
         LEFT JOIN exercicios_categorias ec ON ec.idexercicio = e.idexercicio
         LEFT JOIN categorias_exercicio c ON c.idcategoria = ec.idcategoria AND c.ativo = TRUE
         LEFT JOIN exercicios_modalidades em ON em.idexercicio = e.idexercicio
         LEFT JOIN modalidades m ON m.idmodalidade = em.idmodalidade AND m.ativo = TRUE
         WHERE e.ativo = TRUE AND (e.idusuario IS NULL OR e.idusuario = :usuario)
         GROUP BY e.idexercicio
         ORDER BY e.idusuario NULLS FIRST, e.nome"
    );
    $stmt->execute([':usuario' => $idUsuario]);
    return $stmt->fetchAll();
}

function cronogramaResolverExercicioBiblioteca(array $biblioteca, string $idExercicio, string $nome): array
{
    $idExercicio = trim($idExercicio);
    $nome = cronogramaNormalizarNome($nome);
    $byId = array_column($biblioteca, null, 'idexercicio');
    if ($idExercicio !== '' && isset($byId[$idExercicio])) {
        return ['idexercicio' => $idExercicio, 'nome' => $nome !== '' ? $nome : (string) $byId[$idExercicio]['nome']];
    }
    if ($nome === '') return ['idexercicio' => '', 'nome' => ''];
    $resolution = stridebr_exercise_resolve_catalog($biblioteca, ['nome' => $nome]);
    if (($resolution['status'] ?? '') === 'matched' && is_array($resolution['match'] ?? null)) {
        return ['idexercicio' => (string) $resolution['match']['idexercicio'], 'nome' => $nome];
    }
    return ['idexercicio' => '', 'nome' => $nome];
}

function cronogramaListarCategorias(PDO $pdo, string $idUsuario): array
{
    $stmt = $pdo->prepare('SELECT * FROM categorias_exercicio WHERE ativo = TRUE AND (idusuario IS NULL OR idusuario = :usuario) ORDER BY idusuario NULLS FIRST, nome');
    $stmt->execute([':usuario' => $idUsuario]);
    return $stmt->fetchAll();
}

function cronogramaCriarCategoria(PDO $pdo, string $idUsuario, string $nome): string
{
    $nome = trim($nome);
    $slug = stridebr_slug($nome);
    if ($nome === '' || stridebr_length($nome) > 80 || $slug === '') {
        throw new InvalidArgumentException(stridebr_t('schedule.validation.category_name'));
    }
    $stmt = $pdo->prepare('SELECT idcategoria, ativo FROM categorias_exercicio WHERE idusuario = :usuario AND lower(slug) = lower(:slug) LIMIT 1');
    $stmt->execute([':usuario' => $idUsuario, ':slug' => $slug]);
    $existing = $stmt->fetch();
    if ($existing) {
        if (!stridebr_db_bool($existing['ativo'])) {
            $pdo->prepare('UPDATE categorias_exercicio SET ativo = TRUE, nome = :nome WHERE idcategoria = :id AND idusuario = :usuario')->execute([
                ':nome' => $nome,
                ':id' => $existing['idcategoria'],
                ':usuario' => $idUsuario,
            ]);
        }
        return (string) $existing['idcategoria'];
    }
    $id = cronogramaGerarId();
    $pdo->prepare('INSERT INTO categorias_exercicio (idcategoria, idusuario, nome, slug) VALUES (:id, :usuario, :nome, :slug)')->execute([
        ':id' => $id,
        ':usuario' => $idUsuario,
        ':nome' => $nome,
        ':slug' => $slug,
    ]);
    return $id;
}

function cronogramaNormalizarUrlMidia(?string $url): ?string
{
    $url = trim((string) $url);
    if ($url === '') return null;
    if (strlen($url) > 2000 || filter_var($url, FILTER_VALIDATE_URL) === false) {
        throw new InvalidArgumentException(stridebr_t('schedule.validation.media_invalid'));
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new InvalidArgumentException(stridebr_t('schedule.validation.media_protocol'));
    }
    return $url;
}

function cronogramaCriarExercicio(PDO $pdo, string $idUsuario, string $nome, ?string $descricao = null, array $categorias = [], ?string $imagemUrl = null, ?string $videoUrl = null): string
{
    $nome = cronogramaNormalizarNome($nome);
    $slug = stridebr_slug($nome);
    $imagemUrl = cronogramaNormalizarUrlMidia($imagemUrl);
    $videoUrl = cronogramaNormalizarUrlMidia($videoUrl);
    if ($nome === '' || stridebr_length($nome) > 120 || $slug === '') {
        throw new InvalidArgumentException(stridebr_t('schedule.validation.exercise_name'));
    }
    $stmt = $pdo->prepare('SELECT idexercicio, ativo FROM exercicios WHERE idusuario = :usuario AND lower(slug) = lower(:slug) LIMIT 1');
    $stmt->execute([':usuario' => $idUsuario, ':slug' => $slug]);
    $existing = $stmt->fetch();
    if ($existing) {
        if (!stridebr_db_bool($existing['ativo'])) {
            $pdo->prepare('UPDATE exercicios SET ativo = TRUE, nome = :nome, descricao = :descricao, imagem_url = :imagem, video_url = :video, data_atualizacao = NOW() WHERE idexercicio = :id AND idusuario = :usuario')->execute([
                ':nome' => $nome,
                ':descricao' => $descricao !== null && trim($descricao) !== '' ? trim($descricao) : null,
                ':imagem' => $imagemUrl,
                ':video' => $videoUrl,
                ':id' => $existing['idexercicio'],
                ':usuario' => $idUsuario,
            ]);
        }
        return (string) $existing['idexercicio'];
    }

    $id = cronogramaGerarId();
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $pdo->prepare('INSERT INTO exercicios (idexercicio, idusuario, nome, slug, descricao, imagem_url, video_url) VALUES (:id, :usuario, :nome, :slug, :descricao, :imagem, :video)')->execute([
            ':id' => $id,
            ':usuario' => $idUsuario,
            ':nome' => $nome,
            ':slug' => $slug,
            ':descricao' => $descricao !== null && trim($descricao) !== '' ? trim($descricao) : null,
            ':imagem' => $imagemUrl,
            ':video' => $videoUrl,
        ]);
        $catStmt = $pdo->prepare('SELECT idcategoria FROM categorias_exercicio WHERE idcategoria = :categoria AND (idusuario IS NULL OR idusuario = :usuario)');
        $insertCat = $pdo->prepare('INSERT INTO exercicios_categorias (idexercicio, idcategoria) VALUES (:exercicio, :categoria) ON CONFLICT DO NOTHING');
        foreach ($categorias as $categoria) {
            $catStmt->execute([':categoria' => $categoria, ':usuario' => $idUsuario]);
            if ($catStmt->fetchColumn()) {
                $insertCat->execute([':exercicio' => $id, ':categoria' => $categoria]);
            }
        }
        if ($ownsTransaction) {
            $pdo->commit();
        }
        return $id;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function cronogramaListarTreinoExercicios(PDO $pdo, string $idTreino, string $idUsuario): array
{
    if (cronogramaBuscarTreino($pdo, $idTreino, $idUsuario) === []) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT * FROM treinos_exercicios WHERE idtreino = :treino ORDER BY ordem');
    $stmt->execute([':treino' => $idTreino]);
    return $stmt->fetchAll();
}

function cronogramaListarExerciciosPorTreinos(PDO $pdo, string $idCronograma, string $idUsuario): array
{
    if (cronogramaBuscar($pdo, $idCronograma, $idUsuario) === []) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT te.* FROM treinos_exercicios te JOIN treinos_cronograma t ON t.idtreino = te.idtreino WHERE t.idcronograma = :cronograma ORDER BY te.idtreino, te.ordem'
    );
    $stmt->execute([':cronograma' => $idCronograma]);

    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        $result[$row['idtreino']][] = $row;
    }
    return $result;
}

function cronogramaListarCamposExtras(PDO $pdo, string $idTreino, string $idUsuario): array
{
    if (cronogramaBuscarTreino($pdo, $idTreino, $idUsuario) === []) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT * FROM campos_treino_exercicio WHERE idtreino = :treino AND ativo = TRUE ORDER BY ordem');
    $stmt->execute([':treino' => $idTreino]);
    return $stmt->fetchAll();
}

function cronogramaCarregarValoresExtras(PDO $pdo, array $exercicios): array
{
    if ($exercicios === []) return [];
    $ids = array_column($exercicios, 'idtreino_exercicio');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM valores_treino_exercicio WHERE idtreino_exercicio IN ({$placeholders})");
    $stmt->execute($ids);
    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        $value = $row['valor_texto'] ?? $row['valor_inteiro'] ?? $row['valor_decimal'] ?? $row['valor_booleano'];
        $result[$row['idtreino_exercicio']][$row['idcampo']] = $value;
    }
    return $result;
}

function cronogramaSalvarExercicios(PDO $pdo, string $idTreino, string $idUsuario, array $rows, array $camposExtras): void
{
    if (cronogramaBuscarTreino($pdo, $idTreino, $idUsuario) === []) {
        throw new RuntimeException(stridebr_t('schedule.workout_not_found'));
    }

    $existingRows = cronogramaListarTreinoExercicios($pdo, $idTreino, $idUsuario);
    $existing = array_column($existingRows, null, 'idtreino_exercicio');
    $biblioteca = cronogramaListarExerciciosBiblioteca($pdo, $idUsuario);
    $bibliotecaIds = array_column($biblioteca, null, 'idexercicio');
    $extraById = array_column($camposExtras, null, 'idcampo');

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $pdo->prepare('UPDATE treinos_exercicios SET ordem = ordem + 1000000 WHERE idtreino = :treino')->execute([':treino' => $idTreino]);
        $seen = [];
        $order = 1;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $idOccurrence = trim((string) ($row['idtreino_exercicio'] ?? ''));
            $idExercise = trim((string) ($row['idexercicio'] ?? ''));
            $name = cronogramaNormalizarNome((string) ($row['nome'] ?? $row['nome_snapshot'] ?? ''));

            $resolvedExercise = cronogramaResolverExercicioBiblioteca($biblioteca, $idExercise, $name);
            $idExercise = (string) $resolvedExercise['idexercicio'];
            $name = (string) $resolvedExercise['nome'];

            if ($name === '') continue;
            if (stridebr_length($name) > 120) {
                throw new InvalidArgumentException(stridebr_t('schedule.validation.exercise_name_long'));
            }
            $repeticoes = trim((string) ($row['repeticoes'] ?? ''));
            $carga = trim((string) ($row['carga'] ?? ''));
            $bloco = trim((string) ($row['bloco'] ?? ''));
            $cluster = trim((string) ($row['cluster'] ?? ''));
            $descanso = trim((string) ($row['descanso'] ?? ''));
            $duracao = trim((string) ($row['duracao'] ?? ''));
            $distancia = trim((string) ($row['distancia'] ?? ''));
            $intensidade = trim((string) ($row['intensidade'] ?? ''));
            $tempoExecucao = trim((string) ($row['tempo_execucao'] ?? ''));
            $cadencia = trim((string) ($row['cadencia'] ?? ''));
            $rpeRaw = str_replace(',', '.', trim((string) ($row['rpe'] ?? '')));
            $rirRaw = str_replace(',', '.', trim((string) ($row['rir'] ?? '')));
            $rpe = $rpeRaw !== '' && is_numeric($rpeRaw) ? (float) $rpeRaw : null;
            $rir = $rirRaw !== '' && is_numeric($rirRaw) ? (float) $rirRaw : null;
            if (($rpe !== null && ($rpe < 0 || $rpe > 10)) || ($rir !== null && ($rir < 0 || $rir > 10))) {
                throw new InvalidArgumentException(stridebr_t('schedule.validation.rpe_range'));
            }
            if (stridebr_length($repeticoes) > 40 || stridebr_length($carga) > 40 || stridebr_length($bloco) > 40 || stridebr_length($cluster) > 80 || stridebr_length($descanso) > 40 || stridebr_length($duracao) > 40 || stridebr_length($distancia) > 40 || stridebr_length($intensidade) > 80 || stridebr_length($tempoExecucao) > 40 || stridebr_length($cadencia) > 40) {
                throw new InvalidArgumentException(stridebr_t('schedule.validation.exercise_field_long'));
            }
            $seriesRaw = trim((string) ($row['series'] ?? ''));
            $series = $seriesRaw === '' ? null : filter_var($seriesRaw, FILTER_VALIDATE_INT);
            if ($seriesRaw !== '' && ($series === false || $series <= 0)) {
                throw new InvalidArgumentException(stridebr_t('schedule.validation.sets_positive'));
            }

            if ($idOccurrence !== '' && isset($existing[$idOccurrence])) {
                $stmt = $pdo->prepare('UPDATE treinos_exercicios SET idexercicio = :exercicio, nome_snapshot = :nome, series = :series, repeticoes = :repeticoes, carga = :carga, bloco = :bloco, cluster = :cluster, descanso = :descanso, observacoes = :observacoes, duracao = :duracao, distancia = :distancia, intensidade = :intensidade, rpe = :rpe, rir = :rir, tempo_execucao = :tempo_execucao, cadencia = :cadencia, ordem = :ordem WHERE idtreino_exercicio = :id AND idtreino = :treino');
                $stmt->execute([
                    ':exercicio' => $idExercise !== '' ? $idExercise : null,
                    ':nome' => $name,
                    ':series' => $series,
                    ':repeticoes' => $repeticoes !== '' ? $repeticoes : null,
                    ':carga' => $carga !== '' ? $carga : null,
                    ':bloco' => $bloco !== '' ? $bloco : null,
                    ':cluster' => $cluster !== '' ? $cluster : null,
                    ':descanso' => $descanso !== '' ? $descanso : null,
                    ':observacoes' => trim((string) ($row['observacoes'] ?? '')) ?: null,
                    ':duracao' => $duracao !== '' ? $duracao : null,
                    ':distancia' => $distancia !== '' ? $distancia : null,
                    ':intensidade' => $intensidade !== '' ? $intensidade : null,
                    ':rpe' => $rpe,
                    ':rir' => $rir,
                    ':tempo_execucao' => $tempoExecucao !== '' ? $tempoExecucao : null,
                    ':cadencia' => $cadencia !== '' ? $cadencia : null,
                    ':ordem' => $order,
                    ':id' => $idOccurrence,
                    ':treino' => $idTreino,
                ]);
            } else {
                $idOccurrence = cronogramaGerarId();
                $stmt = $pdo->prepare('INSERT INTO treinos_exercicios (idtreino_exercicio, idtreino, idexercicio, nome_snapshot, series, repeticoes, carga, bloco, cluster, descanso, observacoes, duracao, distancia, intensidade, rpe, rir, tempo_execucao, cadencia, ordem) VALUES (:id, :treino, :exercicio, :nome, :series, :repeticoes, :carga, :bloco, :cluster, :descanso, :observacoes, :duracao, :distancia, :intensidade, :rpe, :rir, :tempo_execucao, :cadencia, :ordem)');
                $stmt->execute([
                    ':id' => $idOccurrence,
                    ':treino' => $idTreino,
                    ':exercicio' => $idExercise !== '' ? $idExercise : null,
                    ':nome' => $name,
                    ':series' => $series,
                    ':repeticoes' => $repeticoes !== '' ? $repeticoes : null,
                    ':carga' => $carga !== '' ? $carga : null,
                    ':bloco' => $bloco !== '' ? $bloco : null,
                    ':cluster' => $cluster !== '' ? $cluster : null,
                    ':descanso' => $descanso !== '' ? $descanso : null,
                    ':observacoes' => trim((string) ($row['observacoes'] ?? '')) ?: null,
                    ':duracao' => $duracao !== '' ? $duracao : null,
                    ':distancia' => $distancia !== '' ? $distancia : null,
                    ':intensidade' => $intensidade !== '' ? $intensidade : null,
                    ':rpe' => $rpe,
                    ':rir' => $rir,
                    ':tempo_execucao' => $tempoExecucao !== '' ? $tempoExecucao : null,
                    ':cadencia' => $cadencia !== '' ? $cadencia : null,
                    ':ordem' => $order,
                ]);
            }

            $seen[] = $idOccurrence;
            $pdo->prepare('DELETE FROM valores_treino_exercicio WHERE idtreino_exercicio = :id')->execute([':id' => $idOccurrence]);
            $extras = is_array($row['extras'] ?? null) ? $row['extras'] : [];
            foreach ($extras as $idCampo => $raw) {
                if (!isset($extraById[$idCampo]) || $raw === '' || $raw === null) continue;
                $field = $extraById[$idCampo];
                $values = ['texto' => null, 'inteiro' => null, 'decimal' => null, 'booleano' => null];
                if ($field['tipo'] === 'inteiro') {
                    $parsed = filter_var($raw, FILTER_VALIDATE_INT);
                    if ($parsed === false) throw new InvalidArgumentException('Campo extra "' . $field['nome'] . '" precisa ser inteiro.');
                    $values['inteiro'] = $parsed;
                } elseif ($field['tipo'] === 'decimal') {
                    $parsed = str_replace(',', '.', trim((string) $raw));
                    if (!is_numeric($parsed)) throw new InvalidArgumentException('Campo extra "' . $field['nome'] . '" precisa ser numérico.');
                    $values['decimal'] = $parsed;
                } elseif ($field['tipo'] === 'booleano') {
                    if (!in_array($raw, [0, 1, '0', '1', false, true], true)) {
                        throw new InvalidArgumentException('Campo extra "' . $field['nome'] . '" precisa ser Sim ou Não.');
                    }
                    $values['booleano'] = in_array($raw, [1, '1', true], true);
                } else {
                    $values['texto'] = trim((string) $raw);
                }
                $pdo->prepare('INSERT INTO valores_treino_exercicio (idvalor, idtreino_exercicio, idcampo, valor_texto, valor_inteiro, valor_decimal, valor_booleano) VALUES (:id, :ocorrencia, :campo, :texto, :inteiro, :decimal, :booleano)')->execute([
                    ':id' => cronogramaGerarId(),
                    ':ocorrencia' => $idOccurrence,
                    ':campo' => $idCampo,
                    ':texto' => $values['texto'],
                    ':inteiro' => $values['inteiro'],
                    ':decimal' => $values['decimal'],
                    ':booleano' => $values['booleano'] === null ? null : ($values['booleano'] ? 1 : 0),
                ]);
            }
            $order++;
        }

        foreach ($existing as $id => $_) {
            if (!in_array($id, $seen, true)) {
                $pdo->prepare('DELETE FROM treinos_exercicios WHERE idtreino_exercicio = :id AND idtreino = :treino')->execute([':id' => $id, ':treino' => $idTreino]);
            }
        }
        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function cronogramaAdicionarCampoExtra(PDO $pdo, string $idTreino, string $idUsuario, string $nome, string $tipo): string
{
    if (cronogramaBuscarTreino($pdo, $idTreino, $idUsuario) === []) {
        throw new RuntimeException(stridebr_t('schedule.workout_not_found'));
    }
    $nome = cronogramaNormalizarNome($nome);
    $slug = stridebr_slug($nome);
    if ($nome === '' || stridebr_length($nome) > 80 || $slug === '' || !in_array($tipo, ['texto', 'inteiro', 'decimal', 'booleano'], true)) {
        throw new InvalidArgumentException(stridebr_t('schedule.validation.extra_field_invalid'));
    }
    $duplicateStmt = $pdo->prepare('SELECT idcampo, tipo, ativo FROM campos_treino_exercicio WHERE idtreino = :treino AND lower(slug) = lower(:slug) LIMIT 1');
    $duplicateStmt->execute([':treino' => $idTreino, ':slug' => $slug]);
    $existing = $duplicateStmt->fetch();
    if ($existing) {
        if (stridebr_db_bool($existing['ativo'])) {
            throw new InvalidArgumentException(stridebr_t('schedule.validation.column_duplicate'));
        }
        if ($existing['tipo'] !== $tipo) {
            throw new InvalidArgumentException(stridebr_t('schedule.validation.archived_column'));
        }
        $pdo->prepare('UPDATE campos_treino_exercicio SET ativo = TRUE, nome = :nome WHERE idcampo = :id AND idtreino = :treino')->execute([
            ':nome' => $nome,
            ':id' => $existing['idcampo'],
            ':treino' => $idTreino,
        ]);
        return (string) $existing['idcampo'];
    }
    $orderStmt = $pdo->prepare('SELECT COALESCE(MAX(ordem), 0) + 1 FROM campos_treino_exercicio WHERE idtreino = :treino');
    $orderStmt->execute([':treino' => $idTreino]);
    $id = cronogramaGerarId();
    $pdo->prepare('INSERT INTO campos_treino_exercicio (idcampo, idtreino, nome, slug, tipo, ordem) VALUES (:id, :treino, :nome, :slug, :tipo, :ordem)')->execute([
        ':id' => $id,
        ':treino' => $idTreino,
        ':nome' => $nome,
        ':slug' => $slug,
        ':tipo' => $tipo,
        ':ordem' => (int) $orderStmt->fetchColumn(),
    ]);
    return $id;
}

function cronogramaDesativarCampoExtra(PDO $pdo, string $idTreino, string $idUsuario, string $idCampo): bool
{
    if (cronogramaBuscarTreino($pdo, $idTreino, $idUsuario) === []) return false;
    $stmt = $pdo->prepare('UPDATE campos_treino_exercicio SET ativo = FALSE WHERE idcampo = :campo AND idtreino = :treino');
    $stmt->execute([':campo' => $idCampo, ':treino' => $idTreino]);
    return $stmt->rowCount() === 1;
}

function cronogramaCopiarExercicio(PDO $pdo, string $idUsuario, string $idTreinoExercicio, string $idTreinoDestino): bool
{
    $destino = cronogramaBuscarTreino($pdo, $idTreinoDestino, $idUsuario);
    if ($destino === []) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT te.*, t.idtreino AS treino_origem FROM treinos_exercicios te JOIN treinos_cronograma t ON t.idtreino = te.idtreino JOIN cronogramas c ON c.idcronograma = t.idcronograma WHERE te.idtreino_exercicio = :id AND c.idusuario = :usuario');
    $stmt->execute([':id' => $idTreinoExercicio, ':usuario' => $idUsuario]);
    $source = $stmt->fetch();
    if (!$source) {
        return false;
    }

    $orderStmt = $pdo->prepare('SELECT COALESCE(MAX(ordem), 0) + 1 FROM treinos_exercicios WHERE idtreino = :treino');
    $orderStmt->execute([':treino' => $idTreinoDestino]);
    $newId = cronogramaGerarId();

    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO treinos_exercicios (idtreino_exercicio, idtreino, idexercicio, nome_snapshot, series, repeticoes, carga, bloco, cluster, descanso, observacoes, ordem) VALUES (:id, :treino, :exercicio, :nome, :series, :repeticoes, :carga, :bloco, :cluster, :descanso, :observacoes, :ordem)')->execute([
            ':id' => $newId,
            ':treino' => $idTreinoDestino,
            ':exercicio' => $source['idexercicio'],
            ':nome' => $source['nome_snapshot'],
            ':series' => $source['series'],
            ':repeticoes' => $source['repeticoes'],
            ':carga' => $source['carga'],
            ':bloco' => $source['bloco'],
            ':cluster' => $source['cluster'],
            ':descanso' => $source['descanso'],
            ':observacoes' => $source['observacoes'],
            ':ordem' => (int) $orderStmt->fetchColumn(),
        ]);

        $extras = $pdo->prepare(
            'SELECT vo.valor_texto, vo.valor_inteiro, vo.valor_decimal, vo.valor_booleano, co.slug, co.tipo
             FROM valores_treino_exercicio vo
             JOIN campos_treino_exercicio co ON co.idcampo = vo.idcampo
             WHERE vo.idtreino_exercicio = :origem'
        );
        $extras->execute([':origem' => $idTreinoExercicio]);
        $destField = $pdo->prepare('SELECT idcampo FROM campos_treino_exercicio WHERE idtreino = :treino AND lower(slug) = lower(:slug) AND tipo = :tipo AND ativo = TRUE LIMIT 1');
        $insertValue = $pdo->prepare('INSERT INTO valores_treino_exercicio (idvalor, idtreino_exercicio, idcampo, valor_texto, valor_inteiro, valor_decimal, valor_booleano) VALUES (:id, :ocorrencia, :campo, :texto, :inteiro, :decimal, :booleano)');
        foreach ($extras->fetchAll() as $extra) {
            $destField->execute([':treino' => $idTreinoDestino, ':slug' => $extra['slug'], ':tipo' => $extra['tipo']]);
            $idCampo = $destField->fetchColumn();
            if (!$idCampo) {
                continue;
            }
            $insertValue->execute([
                ':id' => cronogramaGerarId(),
                ':ocorrencia' => $newId,
                ':campo' => $idCampo,
                ':texto' => $extra['valor_texto'],
                ':inteiro' => $extra['valor_inteiro'],
                ':decimal' => $extra['valor_decimal'],
                ':booleano' => $extra['valor_booleano'] === null ? null : (stridebr_db_bool($extra['valor_booleano']) ? 1 : 0),
            ]);
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function cronogramaListarTreinosUsuario(PDO $pdo, string $idUsuario, ?string $ignorarTreino = null): array
{
    $sql = 'SELECT t.idtreino, t.titulo, t.codigo, t.foco, t.dia_semana, t.hora_inicio, t.hora_fim, t.termina_dia_seguinte, c.idcronograma, c.nome AS cronograma_nome FROM treinos_cronograma t JOIN cronogramas c ON c.idcronograma = t.idcronograma WHERE c.idusuario = :usuario AND c.ativo = TRUE';
    $params = [':usuario' => $idUsuario];
    if ($ignorarTreino !== null) {
        $sql .= ' AND t.idtreino <> :ignorar';
        $params[':ignorar'] = $ignorarTreino;
    }
    $sql .= ' ORDER BY c.nome, t.dia_semana, t.hora_inicio, t.titulo';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function cronogramaListarModalidadesExercicio(PDO $pdo, string $idUsuario): array
{
    $stmt = $pdo->prepare('SELECT idmodalidade, nome FROM modalidades WHERE ativo = TRUE AND (idusuario IS NULL OR idusuario = :usuario) ORDER BY idusuario NULLS FIRST, nome');
    $stmt->execute([':usuario' => $idUsuario]);
    return $stmt->fetchAll();
}

function cronogramaAssociarExercicio(PDO $pdo, string $idExercicio, string $idUsuario, array $categorias, array $modalidades): void
{
    $stmt = $pdo->prepare('SELECT idexercicio FROM exercicios WHERE idexercicio = :id AND idusuario = :usuario LIMIT 1');
    $stmt->execute([':id' => $idExercicio, ':usuario' => $idUsuario]);
    if (!$stmt->fetchColumn()) {
        throw new RuntimeException(stridebr_t('library.personal_exercise_not_found'));
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM exercicios_categorias WHERE idexercicio = :id')->execute([':id' => $idExercicio]);
        $catCheck = $pdo->prepare('SELECT idcategoria FROM categorias_exercicio WHERE idcategoria = :id AND ativo = TRUE AND (idusuario IS NULL OR idusuario = :usuario)');
        $catInsert = $pdo->prepare('INSERT INTO exercicios_categorias (idexercicio, idcategoria) VALUES (:exercicio, :categoria) ON CONFLICT DO NOTHING');
        foreach (array_unique($categorias) as $idCategoria) {
            $catCheck->execute([':id' => $idCategoria, ':usuario' => $idUsuario]);
            if ($catCheck->fetchColumn()) {
                $catInsert->execute([':exercicio' => $idExercicio, ':categoria' => $idCategoria]);
            }
        }

        $pdo->prepare('DELETE FROM exercicios_modalidades WHERE idexercicio = :id')->execute([':id' => $idExercicio]);
        $modCheck = $pdo->prepare('SELECT idmodalidade FROM modalidades WHERE idmodalidade = :id AND ativo = TRUE AND (idusuario IS NULL OR idusuario = :usuario)');
        $modInsert = $pdo->prepare('INSERT INTO exercicios_modalidades (idexercicio, idmodalidade) VALUES (:exercicio, :modalidade) ON CONFLICT DO NOTHING');
        foreach (array_unique($modalidades) as $idModalidade) {
            $modCheck->execute([':id' => $idModalidade, ':usuario' => $idUsuario]);
            if ($modCheck->fetchColumn()) {
                $modInsert->execute([':exercicio' => $idExercicio, ':modalidade' => $idModalidade]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function cronogramaCriarExercicioCompleto(PDO $pdo, string $idUsuario, string $nome, ?string $descricao, array $categorias, array $modalidades, ?string $imagemUrl = null, ?string $videoUrl = null): string
{
    $id = cronogramaCriarExercicio($pdo, $idUsuario, $nome, $descricao, [], $imagemUrl, $videoUrl);
    cronogramaAssociarExercicio($pdo, $id, $idUsuario, $categorias, $modalidades);
    return $id;
}

function cronogramaDuplicarExercicioSistema(PDO $pdo, string $idUsuario, string $idExercicio): string
{
    $stmt = $pdo->prepare('SELECT idexercicio, nome, slug, descricao, imagem_url, video_url FROM exercicios WHERE idexercicio = :id AND idusuario IS NULL AND ativo = TRUE LIMIT 1');
    $stmt->execute([':id' => $idExercicio]);
    $source = $stmt->fetch();
    if (!$source) {
        throw new RuntimeException(stridebr_t('schedule.validation.system_exercise_missing'));
    }

    $baseName = $source['nome'];
    $name = $baseName;
    $suffix = 2;
    while (true) {
        $slug = stridebr_slug($name);
        $check = $pdo->prepare('SELECT 1 FROM exercicios WHERE idusuario = :usuario AND lower(slug) = lower(:slug) LIMIT 1');
        $check->execute([':usuario' => $idUsuario, ':slug' => $slug]);
        if (!$check->fetchColumn()) {
            break;
        }
        $name = $baseName . ' ' . $suffix;
        $suffix++;
    }

    $categories = $pdo->prepare('SELECT idcategoria FROM exercicios_categorias WHERE idexercicio = :id');
    $categories->execute([':id' => $idExercicio]);
    $modalities = $pdo->prepare('SELECT idmodalidade FROM exercicios_modalidades WHERE idexercicio = :id');
    $modalities->execute([':id' => $idExercicio]);
    return cronogramaCriarExercicioCompleto(
        $pdo,
        $idUsuario,
        $name,
        $source['descricao'],
        array_column($categories->fetchAll(), 'idcategoria'),
        array_column($modalities->fetchAll(), 'idmodalidade'),
        $source['imagem_url'] ?? null,
        $source['video_url'] ?? null
    );
}

function cronogramaDesativarExercicioPessoal(PDO $pdo, string $idUsuario, string $idExercicio): bool
{
    $stmt = $pdo->prepare('UPDATE exercicios SET ativo = FALSE, data_atualizacao = NOW() WHERE idexercicio = :id AND idusuario = :usuario');
    $stmt->execute([':id' => $idExercicio, ':usuario' => $idUsuario]);
    return $stmt->rowCount() === 1;
}


function cronogramaRestaurarExercicioPessoal(PDO $pdo, string $idUsuario, string $idExercicio): bool
{
    $stmt = $pdo->prepare('UPDATE exercicios SET ativo = TRUE, data_atualizacao = NOW() WHERE idexercicio = :id AND idusuario = :usuario AND ativo = FALSE');
    $stmt->execute([':id' => $idExercicio, ':usuario' => $idUsuario]);
    return $stmt->rowCount() === 1;
}


function cronogramaAtualizarExercicioPessoal(PDO $pdo, string $idUsuario, string $idExercicio, string $nome, ?string $descricao, array $categorias, array $modalidades, ?string $imagemUrl = null, ?string $videoUrl = null): bool
{
    $nome = cronogramaNormalizarNome($nome);
    $slug = stridebr_slug($nome);
    $imagemUrl = cronogramaNormalizarUrlMidia($imagemUrl);
    $videoUrl = cronogramaNormalizarUrlMidia($videoUrl);
    if ($nome === '' || stridebr_length($nome) > 120 || $slug === '') {
        throw new InvalidArgumentException(stridebr_t('schedule.validation.exercise_name'));
    }

    $check = $pdo->prepare('SELECT 1 FROM exercicios WHERE idusuario = :usuario AND lower(slug) = lower(:slug) AND idexercicio <> :id LIMIT 1');
    $check->execute([':usuario' => $idUsuario, ':slug' => $slug, ':id' => $idExercicio]);
    if ($check->fetchColumn()) {
        throw new InvalidArgumentException(stridebr_t('schedule.validation.exercise_duplicate'));
    }

    $stmt = $pdo->prepare('UPDATE exercicios SET nome = :nome, slug = :slug, descricao = :descricao, imagem_url = :imagem, video_url = :video, data_atualizacao = NOW() WHERE idexercicio = :id AND idusuario = :usuario');
    $stmt->execute([
        ':nome' => $nome,
        ':slug' => $slug,
        ':descricao' => $descricao !== null && trim($descricao) !== '' ? trim($descricao) : null,
        ':imagem' => $imagemUrl,
        ':video' => $videoUrl,
        ':id' => $idExercicio,
        ':usuario' => $idUsuario,
    ]);
    if ($stmt->rowCount() === 0) {
        $owner = $pdo->prepare('SELECT 1 FROM exercicios WHERE idexercicio = :id AND idusuario = :usuario');
        $owner->execute([':id' => $idExercicio, ':usuario' => $idUsuario]);
        if (!$owner->fetchColumn()) {
            return false;
        }
    }
    cronogramaAssociarExercicio($pdo, $idExercicio, $idUsuario, $categorias, $modalidades);
    return true;
}

function cronogramaBibliotecaDisponivel(PDO $pdo): bool
{
    return stridebr_db_table_exists($pdo, 'treinos_modelo') && stridebr_db_table_exists($pdo, 'treinos_modelo_exercicios');
}

function cronogramaListarModalidadesTreino(PDO $pdo, string $idUsuario): array
{
    $stmt = $pdo->prepare(
        "SELECT DISTINCT m.idmodalidade, m.nome, m.slug, m.familia_hub, COALESCE(NULLIF(m.categoria, ''), 'Outras atividades') AS categoria, m.ordem_catalogo
         FROM modalidades m
         JOIN modelos_modalidade mm ON mm.idmodalidade = m.idmodalidade AND mm.ativo = TRUE
         WHERE m.ativo = TRUE
           AND (m.idusuario IS NULL OR m.idusuario = :usuario_modalidade)
           AND (mm.idusuario IS NULL OR mm.idusuario = :usuario_modelo)
         ORDER BY categoria, m.ordem_catalogo, m.nome"
    );
    $stmt->execute([':usuario_modalidade' => $idUsuario, ':usuario_modelo' => $idUsuario]);
    return $stmt->fetchAll();
}

function cronogramaValidarModalidadeTreino(PDO $pdo, string $idUsuario, ?string $idModalidade): ?string
{
    $idModalidade = trim((string) $idModalidade);
    if ($idModalidade === '') return null;
    $stmt = $pdo->prepare(
        'SELECT 1 FROM modalidades m
         WHERE m.idmodalidade = :modalidade AND m.ativo = TRUE
           AND (m.idusuario IS NULL OR m.idusuario = :usuario)
         LIMIT 1'
    );
    $stmt->execute([':modalidade' => $idModalidade, ':usuario' => $idUsuario]);
    if (!$stmt->fetchColumn()) throw new InvalidArgumentException(stridebr_t('schedule.validation.workout_sport'));
    return $idModalidade;
}

function cronogramaInferirModalidadeTreino(PDO $pdo, string $idUsuario, string $idTreino): ?string
{
    $stmt = $pdo->prepare(
        "SELECT tc.titulo, tc.foco, tc.idmodalidade AS modalidade_direta, tm.idmodalidade AS modalidade_modelo
         FROM treinos_cronograma tc
         JOIN cronogramas c ON c.idcronograma = tc.idcronograma
         LEFT JOIN treinos_modelo tm ON tm.idtreino_modelo = tc.idtreino_modelo
         WHERE tc.idtreino = :treino AND c.idusuario = :usuario
         LIMIT 1"
    );
    $stmt->execute([':treino' => $idTreino, ':usuario' => $idUsuario]);
    $row = $stmt->fetch();
    if (!$row) return null;
    if (!empty($row['modalidade_direta'])) return (string) $row['modalidade_direta'];
    if (!empty($row['modalidade_modelo'])) return (string) $row['modalidade_modelo'];

    $exerciseStmt = $pdo->prepare(
        "SELECT em.idmodalidade
         FROM treinos_exercicios te
         JOIN exercicios_modalidades em ON em.idexercicio = te.idexercicio
         JOIN modalidades m ON m.idmodalidade = em.idmodalidade AND m.ativo = TRUE
         WHERE te.idtreino = :treino
         GROUP BY em.idmodalidade, m.ordem_catalogo
         ORDER BY COUNT(*) DESC,
                  CASE em.idmodalidade WHEN 'm_musculacao' THEN 0 WHEN 'm_calistenia' THEN 1 ELSE 2 END,
                  m.ordem_catalogo
         LIMIT 1"
    );
    $exerciseStmt->execute([':treino' => $idTreino]);
    $inferred = $exerciseStmt->fetchColumn();
    if ($inferred !== false) return (string) $inferred;

    $text = stridebr_lower(trim((string) ($row['titulo'] ?? '')) . ' ' . trim((string) ($row['foco'] ?? '')));
    if (str_contains($text, 'academia') || str_contains($text, 'muscula')) return 'm_musculacao';
    if (str_contains($text, 'calisten')) return 'm_calistenia';
    return null;
}

function cronogramaListarTreinosModelo(PDO $pdo, string $idUsuario): array
{
    if (!cronogramaBibliotecaDisponivel($pdo)) return [];
    $stmt = $pdo->prepare(
        "SELECT tm.*, m.nome AS modalidade_nome, m.slug AS modalidade_slug,
                (SELECT COUNT(*) FROM treinos_modelo_exercicios tme WHERE tme.idtreino_modelo = tm.idtreino_modelo) AS exercicios_total,
                (SELECT COUNT(*) FROM treinos_cronograma tc JOIN cronogramas c ON c.idcronograma = tc.idcronograma WHERE tc.idtreino_modelo = tm.idtreino_modelo AND c.idusuario = tm.idusuario) AS usos_total
         FROM treinos_modelo tm
         LEFT JOIN modalidades m ON m.idmodalidade = tm.idmodalidade
         WHERE tm.idusuario = :usuario AND tm.ativo = TRUE
         ORDER BY tm.data_atualizacao DESC, tm.titulo"
    );
    $stmt->execute([':usuario' => $idUsuario]);
    return $stmt->fetchAll();
}

function cronogramaBuscarTreinoModelo(PDO $pdo, string $idUsuario, string $idTreinoModelo): array
{
    if (!cronogramaBibliotecaDisponivel($pdo)) return [];
    $stmt = $pdo->prepare(
        'SELECT tm.*, m.nome AS modalidade_nome, m.slug AS modalidade_slug,
                (SELECT COUNT(*) FROM treinos_cronograma tc JOIN cronogramas c ON c.idcronograma = tc.idcronograma WHERE tc.idtreino_modelo = tm.idtreino_modelo AND c.idusuario = tm.idusuario) AS usos_total
         FROM treinos_modelo tm
         LEFT JOIN modalidades m ON m.idmodalidade = tm.idmodalidade
         WHERE tm.idtreino_modelo = :id AND tm.idusuario = :usuario AND tm.ativo = TRUE LIMIT 1'
    );
    $stmt->execute([':id' => $idTreinoModelo, ':usuario' => $idUsuario]);
    $row = $stmt->fetch();
    if (!$row) return [];
    $exerciseStmt = $pdo->prepare('SELECT * FROM treinos_modelo_exercicios WHERE idtreino_modelo = :id ORDER BY ordem');
    $exerciseStmt->execute([':id' => $idTreinoModelo]);
    $row['exercicios'] = $exerciseStmt->fetchAll();
    return $row;
}

function cronogramaSalvarTreinoModelo(PDO $pdo, string $idUsuario, array $payload, ?string $idTreinoModelo = null, bool $propagarVinculados = false): string
{
    if (!cronogramaBibliotecaDisponivel($pdo)) {
        throw new RuntimeException(stridebr_t('schedule.validation.library_unavailable'));
    }
    $titulo = cronogramaNormalizarNome((string) ($payload['titulo'] ?? ''));
    $codigo = trim((string) ($payload['codigo'] ?? ''));
    $foco = trim((string) ($payload['foco'] ?? ''));
    $descricao = trim((string) ($payload['descricao'] ?? ''));
    if ($titulo === '' || stridebr_length($titulo) > 120) throw new InvalidArgumentException(stridebr_t('planning.message.invalid_title'));
    if (stridebr_length($codigo) > 24) throw new InvalidArgumentException(stridebr_t('schedule.validation.code_long'));
    if (stridebr_length($foco) > 80) throw new InvalidArgumentException(stridebr_t('schedule.validation.focus_long'));

    $existing = [];
    if ($idTreinoModelo !== null) {
        $existing = cronogramaBuscarTreinoModelo($pdo, $idUsuario, $idTreinoModelo);
        if ($existing === []) throw new RuntimeException(stridebr_t('library.workout_not_found'));
    }
    $idModalidade = array_key_exists('idmodalidade', $payload)
        ? cronogramaValidarModalidadeTreino($pdo, $idUsuario, $payload['idmodalidade'])
        : ($existing['idmodalidade'] ?? null);

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        if ($idTreinoModelo !== null) {
            $stmt = $pdo->prepare('UPDATE treinos_modelo SET titulo = :titulo, codigo = :codigo, foco = :foco, descricao = :descricao, idmodalidade = :modalidade, data_atualizacao = NOW() WHERE idtreino_modelo = :id AND idusuario = :usuario');
            $stmt->execute([
                ':titulo' => $titulo,
                ':codigo' => $codigo !== '' ? $codigo : null,
                ':foco' => $foco !== '' ? $foco : null,
                ':descricao' => $descricao !== '' ? $descricao : null,
                ':modalidade' => $idModalidade,
                ':id' => $idTreinoModelo,
                ':usuario' => $idUsuario,
            ]);
            if ($propagarVinculados) {
                $propagate = $pdo->prepare(
                    'UPDATE treinos_cronograma tc
                     SET titulo = :titulo, codigo = :codigo, foco = :foco, idmodalidade = :modalidade, descricao = :descricao, data_atualizacao = NOW()
                     FROM cronogramas c
                     WHERE c.idcronograma = tc.idcronograma
                       AND c.idusuario = :usuario
                       AND tc.idtreino_modelo = :modelo'
                );
                $propagate->execute([
                    ':titulo' => $titulo,
                    ':codigo' => $codigo !== '' ? $codigo : null,
                    ':foco' => $foco !== '' ? $foco : null,
                    ':modalidade' => $idModalidade,
                    ':descricao' => $descricao !== '' ? $descricao : null,
                    ':usuario' => $idUsuario,
                    ':modelo' => $idTreinoModelo,
                ]);
            }
            if ($ownsTransaction) $pdo->commit();
            return $idTreinoModelo;
        }

        $idTreinoModelo = cronogramaGerarId();
        $stmt = $pdo->prepare('INSERT INTO treinos_modelo (idtreino_modelo, idusuario, titulo, codigo, foco, descricao, idmodalidade) VALUES (:id, :usuario, :titulo, :codigo, :foco, :descricao, :modalidade)');
        $stmt->execute([
            ':id' => $idTreinoModelo,
            ':usuario' => $idUsuario,
            ':titulo' => $titulo,
            ':codigo' => $codigo !== '' ? $codigo : null,
            ':foco' => $foco !== '' ? $foco : null,
            ':descricao' => $descricao !== '' ? $descricao : null,
            ':modalidade' => $idModalidade,
        ]);
        if ($ownsTransaction) $pdo->commit();
        return $idTreinoModelo;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function cronogramaArquivarTreinoModelo(PDO $pdo, string $idUsuario, string $idTreinoModelo): bool
{
    $stmt = $pdo->prepare('UPDATE treinos_modelo SET ativo = FALSE, data_atualizacao = NOW() WHERE idtreino_modelo = :id AND idusuario = :usuario AND ativo = TRUE');
    $stmt->execute([':id' => $idTreinoModelo, ':usuario' => $idUsuario]);
    return $stmt->rowCount() === 1;
}

function cronogramaSalvarExerciciosTreinoModelo(PDO $pdo, string $idUsuario, string $idTreinoModelo, array $rows): void
{
    $modelo = cronogramaBuscarTreinoModelo($pdo, $idUsuario, $idTreinoModelo);
    if ($modelo === []) throw new RuntimeException(stridebr_t('library.workout_not_found'));
    if (count($rows) > 200) throw new InvalidArgumentException(stridebr_t('schedule.validation.exercise_limit'));
    $biblioteca = cronogramaListarExerciciosBiblioteca($pdo, $idUsuario);
    $bibliotecaIds = array_column($biblioteca, null, 'idexercicio');
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM treinos_modelo_exercicios WHERE idtreino_modelo = :id')->execute([':id' => $idTreinoModelo]);
        $insert = $pdo->prepare(
            'INSERT INTO treinos_modelo_exercicios
             (idtreino_modelo_exercicio, idtreino_modelo, idexercicio, nome_snapshot, series, repeticoes, carga, bloco, cluster, descanso, observacoes, duracao, distancia, intensidade, rpe, rir, tempo_execucao, cadencia, ordem)
             VALUES (:id, :modelo, :exercicio, :nome, :series, :repeticoes, :carga, :bloco, :cluster, :descanso, :observacoes, :duracao, :distancia, :intensidade, :rpe, :rir, :tempo_execucao, :cadencia, :ordem)'
        );
        $order = 1;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $idExercicio = trim((string) ($row['idexercicio'] ?? ''));
            $nome = trim((string) ($row['nome'] ?? $row['nome_snapshot'] ?? ''));
            $resolvedExercise = cronogramaResolverExercicioBiblioteca($biblioteca, $idExercicio, $nome);
            $idExercicio = (string) $resolvedExercise['idexercicio'];
            $nome = (string) $resolvedExercise['nome'];
            if ($nome === '') continue;
            $seriesRaw = trim((string) ($row['series'] ?? ''));
            $series = $seriesRaw !== '' && ctype_digit($seriesRaw) ? max(1, min(99, (int) $seriesRaw)) : null;
            $rpeRaw = str_replace(',', '.', trim((string) ($row['rpe'] ?? '')));
            $rirRaw = str_replace(',', '.', trim((string) ($row['rir'] ?? '')));
            $rpe = $rpeRaw !== '' && is_numeric($rpeRaw) ? max(0, min(10, (float) $rpeRaw)) : null;
            $rir = $rirRaw !== '' && is_numeric($rirRaw) ? max(0, min(10, (float) $rirRaw)) : null;
            $insert->execute([
                ':id' => cronogramaGerarId(), ':modelo' => $idTreinoModelo,
                ':exercicio' => $idExercicio !== '' && isset($bibliotecaIds[$idExercicio]) ? $idExercicio : null,
                ':nome' => mb_substr($nome, 0, 120), ':series' => $series,
                ':repeticoes' => trim((string) ($row['repeticoes'] ?? '')) ?: null,
                ':carga' => trim((string) ($row['carga'] ?? '')) ?: null,
                ':bloco' => trim((string) ($row['bloco'] ?? '')) ?: null,
                ':cluster' => trim((string) ($row['cluster'] ?? '')) ?: null,
                ':descanso' => trim((string) ($row['descanso'] ?? '')) ?: null,
                ':observacoes' => trim((string) ($row['observacoes'] ?? '')) ?: null,
                ':duracao' => trim((string) ($row['duracao'] ?? '')) ?: null,
                ':distancia' => trim((string) ($row['distancia'] ?? '')) ?: null,
                ':intensidade' => trim((string) ($row['intensidade'] ?? '')) ?: null,
                ':rpe' => $rpe, ':rir' => $rir,
                ':tempo_execucao' => trim((string) ($row['tempo_execucao'] ?? '')) ?: null,
                ':cadencia' => trim((string) ($row['cadencia'] ?? '')) ?: null,
                ':ordem' => $order++,
            ]);
        }
        $pdo->prepare('UPDATE treinos_modelo SET data_atualizacao = NOW() WHERE idtreino_modelo = :id')->execute([':id' => $idTreinoModelo]);
        if ($ownsTransaction) $pdo->commit();
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function cronogramaSalvarTreinoAtualNaBiblioteca(PDO $pdo, string $idUsuario, string $idTreino): string
{
    $source = cronogramaBuscarTreino($pdo, $idTreino, $idUsuario);
    if ($source === []) throw new RuntimeException(stridebr_t('schedule.workout_not_found'));
    $id = cronogramaSalvarTreinoModelo($pdo, $idUsuario, [
        'titulo' => $source['titulo'], 'codigo' => $source['codigo'] ?? '', 'foco' => $source['foco'] ?? '', 'descricao' => $source['descricao'] ?? '',
        'idmodalidade' => cronogramaInferirModalidadeTreino($pdo, $idUsuario, $idTreino) ?? '',
    ]);
    $rows = cronogramaListarTreinoExercicios($pdo, $idTreino, $idUsuario);
    cronogramaSalvarExerciciosTreinoModelo($pdo, $idUsuario, $id, $rows);
    $pdo->prepare('UPDATE treinos_cronograma SET idtreino_modelo = :modelo WHERE idtreino = :treino')->execute([':modelo' => $id, ':treino' => $idTreino]);
    return $id;
}

function cronogramaAtualizarTreinoModeloDoTreino(PDO $pdo, string $idUsuario, string $idTreino): string
{
    $source = cronogramaBuscarTreino($pdo, $idTreino, $idUsuario);
    if ($source === []) throw new RuntimeException(stridebr_t('schedule.workout_not_found'));
    $idModelo = trim((string) ($source['idtreino_modelo'] ?? ''));
    if ($idModelo === '') return cronogramaSalvarTreinoAtualNaBiblioteca($pdo, $idUsuario, $idTreino);
    cronogramaSalvarTreinoModelo($pdo, $idUsuario, [
        'titulo' => $source['titulo'], 'codigo' => $source['codigo'] ?? '', 'foco' => $source['foco'] ?? '', 'descricao' => $source['descricao'] ?? '',
        'idmodalidade' => cronogramaInferirModalidadeTreino($pdo, $idUsuario, $idTreino) ?? '',
    ], $idModelo);
    cronogramaSalvarExerciciosTreinoModelo($pdo, $idUsuario, $idModelo, cronogramaListarTreinoExercicios($pdo, $idTreino, $idUsuario));
    return $idModelo;
}

function cronogramaAdicionarTreinoModeloAoCronograma(PDO $pdo, string $idUsuario, string $idTreinoModelo, string $idCronograma, int $diaSemana, string $horaInicio, string $horaFim, bool $terminaDiaSeguinte, string $vigenciaInicio, ?string $vigenciaFim = null): string
{
    $modelo = cronogramaBuscarTreinoModelo($pdo, $idUsuario, $idTreinoModelo);
    if ($modelo === []) throw new RuntimeException(stridebr_t('library.workout_not_found'));
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $idTreino = cronogramaSalvarTreino($pdo, $idUsuario, [
            'idcronograma' => $idCronograma, 'titulo' => $modelo['titulo'], 'codigo' => $modelo['codigo'] ?? '', 'foco' => $modelo['foco'] ?? '',
            'descricao' => $modelo['descricao'] ?? '', 'idmodalidade' => $modelo['idmodalidade'] ?? '', 'dia_semana' => $diaSemana, 'hora_inicio' => $horaInicio, 'hora_fim' => $horaFim,
            'termina_dia_seguinte' => $terminaDiaSeguinte ? '1' : '', 'vigencia_inicio' => $vigenciaInicio, 'vigencia_fim' => $vigenciaFim ?? '',
        ]);
        $pdo->prepare('UPDATE treinos_cronograma SET idtreino_modelo = :modelo WHERE idtreino = :treino')->execute([':modelo' => $idTreinoModelo, ':treino' => $idTreino]);
        $insert = $pdo->prepare(
            'INSERT INTO treinos_exercicios
             (idtreino_exercicio, idtreino, idexercicio, nome_snapshot, series, repeticoes, carga, bloco, cluster, descanso, observacoes, duracao, distancia, intensidade, rpe, rir, tempo_execucao, cadencia, ordem)
             VALUES (:id, :treino, :exercicio, :nome, :series, :repeticoes, :carga, :bloco, :cluster, :descanso, :observacoes, :duracao, :distancia, :intensidade, :rpe, :rir, :tempo_execucao, :cadencia, :ordem)'
        );
        foreach ($modelo['exercicios'] as $exercise) {
            $insert->execute([
                ':id' => cronogramaGerarId(), ':treino' => $idTreino, ':exercicio' => $exercise['idexercicio'] ?: null,
                ':nome' => $exercise['nome_snapshot'], ':series' => $exercise['series'], ':repeticoes' => $exercise['repeticoes'], ':carga' => $exercise['carga'],
                ':bloco' => $exercise['bloco'], ':cluster' => $exercise['cluster'], ':descanso' => $exercise['descanso'], ':observacoes' => $exercise['observacoes'],
                ':duracao' => $exercise['duracao'], ':distancia' => $exercise['distancia'], ':intensidade' => $exercise['intensidade'], ':rpe' => $exercise['rpe'], ':rir' => $exercise['rir'],
                ':tempo_execucao' => $exercise['tempo_execucao'], ':cadencia' => $exercise['cadencia'], ':ordem' => $exercise['ordem'],
            ]);
        }
        if ($ownsTransaction) $pdo->commit();
        return $idTreino;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function cronogramaValidarDataIso(string $date): DateTimeImmutable
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException(stridebr_t('schedule.validation.date_invalid'));
    return $parsed;
}

function cronogramaExcecaoTemMudanca(array $workout, ?array $exception, string $dataOriginal): bool
{
    if (!$exception) return false;
    if ((string) ($exception['tipo'] ?? '') !== 'alterar') return true;
    if ((string) ($exception['data_treino'] ?? $dataOriginal) !== $dataOriginal) return true;
    foreach (['titulo', 'codigo', 'foco', 'idmodalidade', 'descricao', 'hora_inicio', 'hora_fim'] as $field) {
        if ($exception[$field] !== null && (string) $exception[$field] !== (string) ($workout[$field] ?? '')) return true;
    }
    if ($exception['termina_dia_seguinte'] !== null && stridebr_db_bool($exception['termina_dia_seguinte']) !== stridebr_db_bool($workout['termina_dia_seguinte'] ?? false)) return true;
    return false;
}

function cronogramaListarOcorrencias(PDO $pdo, string $idUsuario, string $dataInicio, string $dataFim, ?string $idCronograma = null, bool $includeCancelled = false): array
{
    $inicio = cronogramaValidarDataIso($dataInicio);
    $fim = cronogramaValidarDataIso($dataFim);
    if ($fim < $inicio || $fim->diff($inicio)->days > 93) throw new InvalidArgumentException(stridebr_t('schedule.validation.agenda_range'));
    $sql = "SELECT t.*, c.nome AS cronograma_nome FROM treinos_cronograma t JOIN cronogramas c ON c.idcronograma = t.idcronograma WHERE c.idusuario = :usuario AND c.ativo = TRUE AND ((t.vigencia_inicio <= :fim AND (t.vigencia_fim IS NULL OR t.vigencia_fim >= :inicio)) OR EXISTS (SELECT 1 FROM treinos_cronograma_excecoes te WHERE te.idtreino = t.idtreino AND te.tipo = 'alterar' AND te.data_treino BETWEEN :inicio_ex AND :fim_ex))";
    $params = [':usuario' => $idUsuario, ':inicio' => $dataInicio, ':fim' => $dataFim, ':inicio_ex' => $dataInicio, ':fim_ex' => $dataFim];
    if ($idCronograma !== null && $idCronograma !== '') {
        $sql .= ' AND c.idcronograma = :cronograma';
        $params[':cronograma'] = $idCronograma;
    }
    $sql .= ' ORDER BY t.hora_inicio, t.ordem';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $workouts = $stmt->fetchAll();
    if ($workouts === []) return [];
    $ids = array_column($workouts, 'idtreino');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $exceptionStmt = $pdo->prepare("SELECT * FROM treinos_cronograma_excecoes WHERE idtreino IN ({$placeholders}) AND ((data_original BETWEEN ? AND ?) OR (data_treino BETWEEN ? AND ?))");
    $exceptionStmt->execute([...$ids, $dataInicio, $dataFim, $dataInicio, $dataFim]);
    $exceptions = [];
    foreach ($exceptionStmt->fetchAll() as $exception) $exceptions[(string) $exception['idtreino']][(string) $exception['data_original']] = $exception;
    $items = [];
    $includedExceptions = [];
    foreach ($workouts as $workout) {
        $seriesStart = max($inicio->getTimestamp(), (new DateTimeImmutable((string) $workout['vigencia_inicio']))->getTimestamp());
        $seriesEnd = $fim->getTimestamp();
        if (!empty($workout['vigencia_fim'])) $seriesEnd = min($seriesEnd, (new DateTimeImmutable((string) $workout['vigencia_fim']))->getTimestamp());
        $cursor = (new DateTimeImmutable('@' . $seriesStart))->setTimezone($inicio->getTimezone());
        while ((int) $cursor->format('w') !== (int) $workout['dia_semana']) $cursor = $cursor->modify('+1 day');
        while ($cursor->getTimestamp() <= $seriesEnd) {
            $original = $cursor->format('Y-m-d');
            $exception = $exceptions[(string) $workout['idtreino']][$original] ?? null;
            if (!$exception || $exception['tipo'] !== 'cancelar' || $includeCancelled) {
                $item = $workout;
                $item['data_original'] = $original;
                if (($exception['tipo'] ?? '') === 'cancelar') $item['status'] = 'cancelado';
                $item['data_treino'] = $exception && $exception['tipo'] === 'alterar' ? (string) $exception['data_treino'] : $original;
                $item['titulo'] = $exception['titulo'] ?? $workout['titulo'];
                $item['codigo'] = $exception['codigo'] ?? $workout['codigo'];
                $item['foco'] = $exception['foco'] ?? $workout['foco'];
                $item['idmodalidade'] = $exception['idmodalidade'] ?? $workout['idmodalidade'];
                $item['descricao'] = $exception['descricao'] ?? $workout['descricao'];
                $item['hora_inicio'] = $exception['hora_inicio'] ?? $workout['hora_inicio'];
                $item['hora_fim'] = $exception['hora_fim'] ?? $workout['hora_fim'];
                $item['termina_dia_seguinte'] = $exception['termina_dia_seguinte'] ?? $workout['termina_dia_seguinte'];
                $item['excecao'] = cronogramaExcecaoTemMudanca($workout, $exception ?: null, $original);
                if ($item['data_treino'] >= $dataInicio && $item['data_treino'] <= $dataFim) $items[] = $item;
                if ($exception) $includedExceptions[(string) $workout['idtreino'] . ':' . $original] = true;
            } elseif ($exception) {
                $includedExceptions[(string) $workout['idtreino'] . ':' . $original] = true;
            }
            $cursor = $cursor->modify('+7 days');
        }
    }
    foreach ($workouts as $workout) {
        foreach ($exceptions[(string) $workout['idtreino']] ?? [] as $original => $exception) {
            $key = (string) $workout['idtreino'] . ':' . $original;
            if (isset($includedExceptions[$key]) || $exception['tipo'] !== 'alterar') continue;
            $newDate = (string) ($exception['data_treino'] ?? '');
            if ($newDate < $dataInicio || $newDate > $dataFim) continue;
            $item = $workout;
            $item['data_original'] = $original;
            $item['data_treino'] = $newDate;
            $item['titulo'] = $exception['titulo'] ?? $workout['titulo'];
            $item['codigo'] = $exception['codigo'] ?? $workout['codigo'];
            $item['foco'] = $exception['foco'] ?? $workout['foco'];
            $item['idmodalidade'] = $exception['idmodalidade'] ?? $workout['idmodalidade'];
            $item['descricao'] = $exception['descricao'] ?? $workout['descricao'];
            $item['hora_inicio'] = $exception['hora_inicio'] ?? $workout['hora_inicio'];
            $item['hora_fim'] = $exception['hora_fim'] ?? $workout['hora_fim'];
            $item['termina_dia_seguinte'] = $exception['termina_dia_seguinte'] ?? $workout['termina_dia_seguinte'];
            $item['excecao'] = cronogramaExcecaoTemMudanca($workout, $exception, (string) $original);
            $items[] = $item;
        }
    }
    usort($items, static fn(array $a, array $b): int => [$a['data_treino'], $a['hora_inicio'], $a['ordem']] <=> [$b['data_treino'], $b['hora_inicio'], $b['ordem']]);
    return $items;
}

function cronogramaAlterarOcorrencia(PDO $pdo, string $idUsuario, string $idTreino, string $dataOriginal, string $novaData, string $scope = 'this', ?string $novaHoraInicio = null): array
{
    $workout = cronogramaBuscarTreino($pdo, $idTreino, $idUsuario);
    if ($workout === []) throw new RuntimeException(stridebr_t('schedule.workout_not_found'));
    $original = cronogramaValidarDataIso($dataOriginal);
    $target = cronogramaValidarDataIso($novaData);
    if (!in_array($scope, ['this', 'future', 'all'], true)) throw new InvalidArgumentException(stridebr_t('schedule.validation.move_scope'));

    $timePatch = null;
    $novaHoraInicio = $novaHoraInicio !== null ? trim($novaHoraInicio) : null;
    if ($novaHoraInicio !== null && $novaHoraInicio !== '') {
        if (!preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $novaHoraInicio)) throw new InvalidArgumentException(stridebr_t('planning.message.invalid_time'));
        $baseStart = ((int) substr((string) $workout['hora_inicio'], 0, 2)) * 60 + (int) substr((string) $workout['hora_inicio'], 3, 2);
        $baseEnd = ((int) substr((string) $workout['hora_fim'], 0, 2)) * 60 + (int) substr((string) $workout['hora_fim'], 3, 2);
        $duration = stridebr_db_bool($workout['termina_dia_seguinte'] ?? false)
            ? (1440 - $baseStart + $baseEnd)
            : ($baseEnd - $baseStart);
        $duration = max(1, $duration);
        $newStart = ((int) substr($novaHoraInicio, 0, 2)) * 60 + (int) substr($novaHoraInicio, 3, 2);
        $newEndTotal = $newStart + $duration;
        $newEnd = $newEndTotal % 1440;
        $timePatch = [
            'hora_inicio' => $novaHoraInicio,
            'hora_fim' => sprintf('%02d:%02d', intdiv($newEnd, 60), $newEnd % 60),
            'termina_dia_seguinte' => $newEndTotal >= 1440,
        ];
    }

    if ($scope === 'this') {
        $existingStmt = $pdo->prepare("SELECT titulo, codigo, foco, idmodalidade, descricao FROM treinos_cronograma_excecoes WHERE idtreino = :treino AND data_original = :original AND tipo = 'alterar' LIMIT 1");
        $existingStmt->execute([':treino' => $idTreino, ':original' => $dataOriginal]);
        $existing = $existingStmt->fetch() ?: null;
        $hasMetadataOverride = $existing && array_filter([
            $existing['titulo'] ?? null, $existing['codigo'] ?? null, $existing['foco'] ?? null,
            $existing['idmodalidade'] ?? null, $existing['descricao'] ?? null,
        ], static fn($value): bool => $value !== null && $value !== '') !== [];
        $timeMatchesBase = $timePatch === null || (
            substr((string) $timePatch['hora_inicio'], 0, 5) === substr((string) $workout['hora_inicio'], 0, 5)
            && substr((string) $timePatch['hora_fim'], 0, 5) === substr((string) $workout['hora_fim'], 0, 5)
            && (bool) $timePatch['termina_dia_seguinte'] === stridebr_db_bool($workout['termina_dia_seguinte'] ?? false)
        );
        if ($novaData === $dataOriginal && $timeMatchesBase && !$hasMetadataOverride) {
            $pdo->prepare('DELETE FROM treinos_cronograma_excecoes WHERE idtreino = :treino AND data_original = :original')->execute([':treino' => $idTreino, ':original' => $dataOriginal]);
            return ['idtreino' => $idTreino, 'data_original' => $dataOriginal, 'data_treino' => $novaData, 'scope' => $scope, 'excecao' => false];
        }
        if ($timePatch !== null) {
            $stmt = $pdo->prepare(
                "INSERT INTO treinos_cronograma_excecoes (idexcecao, idtreino, data_original, tipo, data_treino, hora_inicio, hora_fim, termina_dia_seguinte)
                 VALUES (:id, :treino, :original, 'alterar', :nova, :hora_inicio, :hora_fim, :seguinte)
                 ON CONFLICT (idtreino, data_original) DO UPDATE SET tipo = 'alterar', data_treino = EXCLUDED.data_treino, hora_inicio = EXCLUDED.hora_inicio, hora_fim = EXCLUDED.hora_fim, termina_dia_seguinte = EXCLUDED.termina_dia_seguinte, data_atualizacao = NOW()"
            );
            $stmt->execute([
                ':id' => cronogramaGerarId(),
                ':treino' => $idTreino,
                ':original' => $dataOriginal,
                ':nova' => $novaData,
                ':hora_inicio' => $timePatch['hora_inicio'],
                ':hora_fim' => $timePatch['hora_fim'],
                ':seguinte' => $timePatch['termina_dia_seguinte'] ? 'true' : 'false',
            ]);
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO treinos_cronograma_excecoes (idexcecao, idtreino, data_original, tipo, data_treino)
                 VALUES (:id, :treino, :original, 'alterar', :nova)
                 ON CONFLICT (idtreino, data_original) DO UPDATE SET tipo = 'alterar', data_treino = EXCLUDED.data_treino, data_atualizacao = NOW()"
            );
            $stmt->execute([':id' => cronogramaGerarId(), ':treino' => $idTreino, ':original' => $dataOriginal, ':nova' => $novaData]);
        }
        return [
            'idtreino' => $idTreino,
            'data_original' => $dataOriginal,
            'data_treino' => $novaData,
            'hora_inicio' => $timePatch['hora_inicio'] ?? null,
            'hora_fim' => $timePatch['hora_fim'] ?? null,
            'termina_dia_seguinte' => $timePatch['termina_dia_seguinte'] ?? null,
            'scope' => $scope,
        ];
    }
    $newWeekday = (int) $target->format('w');
    if ($scope === 'all') {
        $sql = 'UPDATE treinos_cronograma SET dia_semana = :dia';
        $params = [':dia' => $newWeekday, ':treino' => $idTreino];
        if ($timePatch !== null) {
            $sql .= ', hora_inicio = :inicio, hora_fim = :fim, termina_dia_seguinte = :seguinte';
            $params[':inicio'] = $timePatch['hora_inicio'];
            $params[':fim'] = $timePatch['hora_fim'];
            $params[':seguinte'] = $timePatch['termina_dia_seguinte'] ? 'true' : 'false';
        }
        $sql .= ', data_atualizacao = NOW() WHERE idtreino = :treino';
        $pdo->prepare($sql)->execute($params);
        return ['idtreino' => $idTreino, 'data_original' => $dataOriginal, 'data_treino' => $novaData, 'hora_inicio' => $timePatch['hora_inicio'] ?? null, 'scope' => $scope];
    }
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $dayBefore = $original->modify('-1 day')->format('Y-m-d');
        $seriesStart = (string) $workout['vigencia_inicio'];
        if ($dataOriginal <= $seriesStart) {
            $sql = 'UPDATE treinos_cronograma SET dia_semana = :dia, vigencia_inicio = :vigencia';
            $params = [':dia' => $newWeekday, ':vigencia' => $novaData, ':treino' => $idTreino];
            if ($timePatch !== null) {
                $sql .= ', hora_inicio = :inicio, hora_fim = :fim, termina_dia_seguinte = :seguinte';
                $params[':inicio'] = $timePatch['hora_inicio'];
                $params[':fim'] = $timePatch['hora_fim'];
                $params[':seguinte'] = $timePatch['termina_dia_seguinte'] ? 'true' : 'false';
            }
            $sql .= ', data_atualizacao = NOW() WHERE idtreino = :treino';
            $pdo->prepare($sql)->execute($params);
            if ($ownsTransaction) $pdo->commit();
            return ['idtreino' => $idTreino, 'data_original' => $dataOriginal, 'data_treino' => $novaData, 'scope' => $scope];
        }
        $oldEnd = $workout['vigencia_fim'] ?: null;
        $pdo->prepare('UPDATE treinos_cronograma SET vigencia_fim = :fim, data_atualizacao = NOW() WHERE idtreino = :treino')->execute([':fim' => $dayBefore, ':treino' => $idTreino]);
        $newId = cronogramaSalvarTreino($pdo, $idUsuario, [
            'idcronograma' => $workout['idcronograma'], 'titulo' => $workout['titulo'], 'codigo' => $workout['codigo'] ?? '', 'foco' => $workout['foco'] ?? '',
            'descricao' => $workout['descricao'] ?? '', 'idmodalidade' => $workout['idmodalidade'] ?? '', 'dia_semana' => $newWeekday, 'hora_inicio' => $timePatch['hora_inicio'] ?? substr((string) $workout['hora_inicio'], 0, 5),
            'hora_fim' => $timePatch['hora_fim'] ?? substr((string) $workout['hora_fim'], 0, 5), 'termina_dia_seguinte' => ($timePatch !== null ? (bool) $timePatch['termina_dia_seguinte'] : stridebr_db_bool($workout['termina_dia_seguinte'])) ? '1' : '',
            'vigencia_inicio' => $novaData, 'vigencia_fim' => $oldEnd ?? '',
        ]);
        if (!empty($workout['idtreino_modelo'])) $pdo->prepare('UPDATE treinos_cronograma SET idtreino_modelo = :modelo WHERE idtreino = :treino')->execute([':modelo' => $workout['idtreino_modelo'], ':treino' => $newId]);
        $sourceExercises = cronogramaListarTreinoExercicios($pdo, $idTreino, $idUsuario);
        if ($sourceExercises !== []) cronogramaSalvarExercicios($pdo, $newId, $idUsuario, $sourceExercises, []);
        if ($ownsTransaction) $pdo->commit();
        return ['idtreino' => $newId, 'data_original' => $dataOriginal, 'data_treino' => $novaData, 'scope' => $scope];
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}


function cronogramaDataNaMesmaSemana(DateTimeImmutable $referencia, int $diaSemana): DateTimeImmutable
{
    if ($diaSemana < 0 || $diaSemana > 6) throw new InvalidArgumentException(stridebr_t('schedule.invalid_weekday'));
    $domingo = $referencia->modify('-' . $referencia->format('w') . ' days');
    return $domingo->modify('+' . $diaSemana . ' days');
}

function cronogramaEditarTreinoEscopo(PDO $pdo, string $idUsuario, string $idTreino, string $dataOriginal, string $scope, array $payload): array
{
    $workout = cronogramaBuscarTreino($pdo, $idTreino, $idUsuario);
    if ($workout === []) throw new RuntimeException(stridebr_t('schedule.workout_not_found'));
    if (!in_array($scope, ['this', 'future', 'all'], true)) throw new InvalidArgumentException(stridebr_t('schedule.validation.edit_scope'));
    $payload['idcronograma'] = (string) $workout['idcronograma'];
    if ($scope === 'all' || trim($dataOriginal) === '') {
        return ['idtreino' => cronogramaSalvarTreino($pdo, $idUsuario, $payload, $idTreino), 'scope' => 'all'];
    }
    $occurrence = cronogramaBuscarOcorrenciaOriginal($pdo, $idUsuario, $idTreino, $dataOriginal);
    $referenceDate = cronogramaValidarDataIso((string) $occurrence['data_treino']);
    $day = filter_var($payload['dia_semana'] ?? null, FILTER_VALIDATE_INT);
    if ($day === false || $day < 0 || $day > 6) throw new InvalidArgumentException(stridebr_t('schedule.invalid_weekday'));
    $targetDate = cronogramaDataNaMesmaSemana($referenceDate, (int) $day);
    $titulo = cronogramaNormalizarNome((string) ($payload['titulo'] ?? ''));
    $codigo = trim((string) ($payload['codigo'] ?? ''));
    $foco = trim((string) ($payload['foco'] ?? ''));
    $descricao = trim((string) ($payload['descricao'] ?? ''));
    $inicio = trim((string) ($payload['hora_inicio'] ?? ''));
    $fim = trim((string) ($payload['hora_fim'] ?? ''));
    $nextDay = !empty($payload['termina_dia_seguinte']);
    $idModalidade = cronogramaValidarModalidadeTreino($pdo, $idUsuario, $payload['idmodalidade'] ?? null);
    if ($titulo === '' || stridebr_length($titulo) > 120) throw new InvalidArgumentException(stridebr_t('schedule.validation.workout_title'));
    if (stridebr_length($codigo) > 24) throw new InvalidArgumentException(stridebr_t('schedule.validation.code_long'));
    if (stridebr_length($foco) > 80) throw new InvalidArgumentException(stridebr_t('schedule.validation.focus_long'));
    if (!preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $inicio) || !preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $fim)) throw new InvalidArgumentException(stridebr_t('planning.message.invalid_time'));
    if (!$nextDay && $fim <= $inicio) throw new InvalidArgumentException(stridebr_t('schedule.validation.end_time'));
    if ($nextDay && $fim > $inicio) throw new InvalidArgumentException(stridebr_t('schedule.validation.overnight_time'));
    if ($scope === 'this') {
        $baseModality = trim((string) ($workout['idmodalidade'] ?? ''));
        $normalizedModality = trim((string) ($idModalidade ?? ''));
        $matchesBase = $targetDate->format('Y-m-d') === $dataOriginal
            && $titulo === trim((string) $workout['titulo'])
            && $codigo === trim((string) ($workout['codigo'] ?? ''))
            && $foco === trim((string) ($workout['foco'] ?? ''))
            && $descricao === trim((string) ($workout['descricao'] ?? ''))
            && $normalizedModality === $baseModality
            && $inicio === substr((string) $workout['hora_inicio'], 0, 5)
            && $fim === substr((string) $workout['hora_fim'], 0, 5)
            && $nextDay === stridebr_db_bool($workout['termina_dia_seguinte'] ?? false);
        if ($matchesBase) {
            $pdo->prepare('DELETE FROM treinos_cronograma_excecoes WHERE idtreino = :treino AND data_original = :original')->execute([':treino' => $idTreino, ':original' => $dataOriginal]);
            return ['idtreino'=>$idTreino, 'scope'=>'this', 'data_treino'=>$dataOriginal, 'excecao'=>false];
        }
        $stmt = $pdo->prepare("INSERT INTO treinos_cronograma_excecoes
            (idexcecao, idtreino, data_original, tipo, data_treino, titulo, codigo, foco, idmodalidade, descricao, hora_inicio, hora_fim, termina_dia_seguinte)
            VALUES (:id, :treino, :original, 'alterar', :data_treino, :titulo, :codigo, :foco, :modalidade, :descricao, :inicio, :fim, :seguinte)
            ON CONFLICT (idtreino, data_original) DO UPDATE SET tipo = 'alterar', data_treino = EXCLUDED.data_treino,
                titulo = EXCLUDED.titulo, codigo = EXCLUDED.codigo, foco = EXCLUDED.foco, idmodalidade = EXCLUDED.idmodalidade,
                descricao = EXCLUDED.descricao, hora_inicio = EXCLUDED.hora_inicio, hora_fim = EXCLUDED.hora_fim,
                termina_dia_seguinte = EXCLUDED.termina_dia_seguinte, data_atualizacao = NOW()");
        $stmt->execute([':id'=>cronogramaGerarId(), ':treino'=>$idTreino, ':original'=>$dataOriginal, ':data_treino'=>$targetDate->format('Y-m-d'),
            ':titulo'=>$titulo, ':codigo'=>$codigo !== '' ? $codigo : null, ':foco'=>$foco !== '' ? $foco : null, ':modalidade'=>$idModalidade,
            ':descricao'=>$descricao !== '' ? $descricao : null, ':inicio'=>$inicio, ':fim'=>$fim, ':seguinte'=>$nextDay ? 'true' : 'false']);
        return ['idtreino'=>$idTreino, 'scope'=>'this', 'data_treino'=>$targetDate->format('Y-m-d')];
    }
    $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
    try {
        $moved = cronogramaAlterarOcorrencia($pdo, $idUsuario, $idTreino, $dataOriginal, $targetDate->format('Y-m-d'), 'future', $inicio);
        $targetWorkout = cronogramaBuscarTreino($pdo, (string)$moved['idtreino'], $idUsuario);
        if ($targetWorkout === []) throw new RuntimeException(stridebr_t('schedule.validation.phase_error'));
        $payload['vigencia_inicio'] = (string)$targetWorkout['vigencia_inicio'];
        $payload['vigencia_fim'] = (string)($targetWorkout['vigencia_fim'] ?? '');
        $saved = cronogramaSalvarTreino($pdo, $idUsuario, $payload, (string)$moved['idtreino']);
        if ($own) $pdo->commit();
        return ['idtreino'=>$saved, 'scope'=>'future', 'data_treino'=>$targetDate->format('Y-m-d')];
    } catch (Throwable $e) { if ($own && $pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function cronogramaCancelarOcorrencia(PDO $pdo, string $idUsuario, string $idTreino, string $dataOriginal): void
{
    if (cronogramaBuscarTreino($pdo, $idTreino, $idUsuario) === []) throw new RuntimeException(stridebr_t('schedule.workout_not_found'));
    cronogramaValidarDataIso($dataOriginal);
    $stmt = $pdo->prepare(
        "INSERT INTO treinos_cronograma_excecoes (idexcecao, idtreino, data_original, tipo, data_treino)
         VALUES (:id, :treino, :original, 'cancelar', NULL)
         ON CONFLICT (idtreino, data_original) DO UPDATE SET tipo = 'cancelar', data_treino = NULL, data_atualizacao = NOW()"
    );
    $stmt->execute([':id' => cronogramaGerarId(), ':treino' => $idTreino, ':original' => $dataOriginal]);
}

function cronogramaBuscarOcorrenciaOriginal(PDO $pdo, string $idUsuario, string $idTreino, string $dataOriginal): array
{
    $workout = cronogramaBuscarTreino($pdo, $idTreino, $idUsuario);
    if ($workout === []) throw new RuntimeException(stridebr_t('schedule.workout_not_found'));
    $original = cronogramaValidarDataIso($dataOriginal);

    $stmt = $pdo->prepare('SELECT * FROM treinos_cronograma_excecoes WHERE idtreino = :treino AND data_original = :original LIMIT 1');
    $stmt->execute([':treino' => $idTreino, ':original' => $dataOriginal]);
    $exception = $stmt->fetch() ?: null;
    if ($exception && (string) $exception['tipo'] === 'cancelar') {
        throw new RuntimeException(stridebr_t('schedule.validation.skipped'));
    }

    if (!$exception) {
        $start = new DateTimeImmutable((string) $workout['vigencia_inicio']);
        $end = !empty($workout['vigencia_fim']) ? new DateTimeImmutable((string) $workout['vigencia_fim']) : null;
        if ($original < $start || ($end !== null && $original > $end) || (int) $original->format('w') !== (int) $workout['dia_semana']) {
            throw new RuntimeException(stridebr_t('schedule.validation.occurrence_missing'));
        }
    }

    $item = $workout;
    $item['data_original'] = $dataOriginal;
    $item['data_treino'] = $exception ? (string) $exception['data_treino'] : $dataOriginal;
    $item['titulo'] = $exception['titulo'] ?? $workout['titulo'];
    $item['codigo'] = $exception['codigo'] ?? $workout['codigo'];
    $item['foco'] = $exception['foco'] ?? $workout['foco'];
    $item['idmodalidade'] = $exception['idmodalidade'] ?? $workout['idmodalidade'];
    $item['descricao'] = $exception['descricao'] ?? $workout['descricao'];
    $item['hora_inicio'] = $exception['hora_inicio'] ?? $workout['hora_inicio'];
    $item['hora_fim'] = $exception['hora_fim'] ?? $workout['hora_fim'];
    $item['termina_dia_seguinte'] = $exception['termina_dia_seguinte'] ?? $workout['termina_dia_seguinte'];
    $item['excecao'] = cronogramaExcecaoTemMudanca($workout, $exception, $dataOriginal);
    return $item;
}

function cronogramaTrocarOcorrencias(PDO $pdo, string $idUsuario, string $idTreinoA, string $dataOriginalA, string $idTreinoB, string $dataOriginalB): array
{
    if ($idTreinoA === $idTreinoB && $dataOriginalA === $dataOriginalB) {
        throw new InvalidArgumentException(stridebr_t('schedule.validation.swap_different'));
    }
    $a = cronogramaBuscarOcorrenciaOriginal($pdo, $idUsuario, $idTreinoA, $dataOriginalA);
    $b = cronogramaBuscarOcorrenciaOriginal($pdo, $idUsuario, $idTreinoB, $dataOriginalB);
    if ((string) $a['idcronograma'] !== (string) $b['idcronograma']) {
        throw new InvalidArgumentException(stridebr_t('schedule.validation.same_schedule'));
    }

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $resultA = cronogramaAlterarOcorrencia(
            $pdo,
            $idUsuario,
            $idTreinoA,
            $dataOriginalA,
            (string) $b['data_treino'],
            'this',
            substr((string) $b['hora_inicio'], 0, 5)
        );
        $resultB = cronogramaAlterarOcorrencia(
            $pdo,
            $idUsuario,
            $idTreinoB,
            $dataOriginalB,
            (string) $a['data_treino'],
            'this',
            substr((string) $a['hora_inicio'], 0, 5)
        );
        if ($ownsTransaction) $pdo->commit();
        return ['a' => $resultA, 'b' => $resultB];
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function cronogramaPreferenciaSemanalBuscar(PDO $pdo, string $idUsuario, string $idCronograma, string $semanaInicio): ?string
{
    cronogramaValidarDataIso($semanaInicio);
    $stmt = $pdo->prepare('SELECT idtreino_proximo FROM cronograma_semana_preferencias WHERE idusuario = :usuario AND idcronograma = :cronograma AND semana_inicio = :semana LIMIT 1');
    $stmt->execute([':usuario' => $idUsuario, ':cronograma' => $idCronograma, ':semana' => $semanaInicio]);
    $id = $stmt->fetchColumn();
    return $id !== false && $id !== null && trim((string) $id) !== '' ? (string) $id : null;
}

function cronogramaPreferenciaSemanalSalvar(PDO $pdo, string $idUsuario, string $idCronograma, string $semanaInicio, ?string $idTreino): void
{
    cronogramaValidarDataIso($semanaInicio);
    if (cronogramaBuscar($pdo, $idCronograma, $idUsuario) === []) throw new RuntimeException(stridebr_t('planning.message.schedule_missing'));
    if ($idTreino !== null && $idTreino !== '') {
        $workout = cronogramaBuscarTreino($pdo, $idTreino, $idUsuario);
        if ($workout === [] || (string) $workout['idcronograma'] !== $idCronograma) {
            throw new InvalidArgumentException(stridebr_t('schedule.validation.workout_invalid'));
        }
    }
    $stmt = $pdo->prepare(
        'INSERT INTO cronograma_semana_preferencias (idusuario, idcronograma, semana_inicio, idtreino_proximo)
         VALUES (:usuario, :cronograma, :semana, :treino)
         ON CONFLICT (idusuario, idcronograma, semana_inicio)
         DO UPDATE SET idtreino_proximo = EXCLUDED.idtreino_proximo, data_atualizacao = NOW()'
    );
    $stmt->execute([':usuario' => $idUsuario, ':cronograma' => $idCronograma, ':semana' => $semanaInicio, ':treino' => $idTreino ?: null]);
}

function cronogramaInferirOcorrenciaParaData(PDO $pdo, string $idUsuario, string $idTreino, string $dataRealizada): array
{
    $date = cronogramaValidarDataIso($dataRealizada);
    $workout = cronogramaBuscarTreino($pdo, $idTreino, $idUsuario);
    if ($workout === []) return [];
    $weekStart = $date->modify('-' . $date->format('w') . ' days');
    $weekEnd = $weekStart->modify('+6 days');
    $items = cronogramaListarOcorrencias($pdo, $idUsuario, $weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d'), (string) $workout['idcronograma']);
    $candidates = array_values(array_filter($items, static fn(array $item): bool => (string) ($item['idtreino'] ?? '') === $idTreino));
    if ($candidates === []) return [];
    usort($candidates, static function (array $a, array $b) use ($date): int {
        $distanceA = abs((new DateTimeImmutable((string) $a['data_treino']))->diff($date)->days ?? 0);
        $distanceB = abs((new DateTimeImmutable((string) $b['data_treino']))->diff($date)->days ?? 0);
        return $distanceA <=> $distanceB;
    });
    return [
        'data_ocorrencia_origem' => (string) $candidates[0]['data_original'],
        'data_ocorrencia_planejada' => (string) $candidates[0]['data_treino'],
        'hora_ocorrencia_planejada' => substr((string) $candidates[0]['hora_inicio'], 0, 5),
    ];
}

function cronogramaConciliarOcorrenciasComRegistros(PDO $pdo, string $idUsuario, array $ocorrencias, string $dataInicio, string $dataFim, ?string $idCronograma = null): array
{
    $start = cronogramaValidarDataIso($dataInicio)->modify('-45 days');
    $end = cronogramaValidarDataIso($dataFim)->modify('+45 days');
    $sql = "SELECT idregistro, idcronograma, idtreino_cronograma, data_ocorrencia_origem, data_ocorrencia_planejada, hora_ocorrencia_planejada, data_inicio, data_fim
            FROM registros_atividade
            WHERE idusuario = :usuario
              AND excluido_em IS NULL
              AND idtreino_cronograma IS NOT NULL
              AND status = 'concluido'
              AND ((data_inicio >= :inicio AND data_inicio < :fim)
                   OR data_ocorrencia_planejada BETWEEN :plano_inicio AND :plano_fim
                   OR data_ocorrencia_origem BETWEEN :origem_inicio AND :origem_fim)";
    $params = [
        ':usuario' => $idUsuario,
        ':inicio' => $start->setTime(0, 0)->format(DateTimeInterface::ATOM),
        ':fim' => $end->modify('+1 day')->setTime(0, 0)->format(DateTimeInterface::ATOM),
        ':plano_inicio' => $dataInicio, ':plano_fim' => $dataFim,
        ':origem_inicio' => $dataInicio, ':origem_fim' => $dataFim,
    ];
    if ($idCronograma !== null && $idCronograma !== '') {
        $sql .= ' AND idcronograma = :cronograma';
        $params[':cronograma'] = $idCronograma;
    }
    $sql .= ' ORDER BY data_inicio ASC, idregistro ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $activities = $stmt->fetchAll();

    $byWorkout = [];
    foreach ($ocorrencias as $index => $item) {
        $ocorrencias[$index]['concluido'] = false;
        $ocorrencias[$index]['data_planejada'] = (string) $item['data_treino'];
        $ocorrencias[$index]['hora_planejada'] = substr((string) $item['hora_inicio'], 0, 5);
        $ocorrencias[$index]['suprimido_por_realizacao_semana'] = false;
        $byWorkout[(string) ($item['idtreino'] ?? '')][] = $index;
    }

    $used = [];
    $workoutTemplates = [];
    foreach ($byWorkout as $workoutId => $indices) $workoutTemplates[$workoutId] = $ocorrencias[$indices[0]];
    $missingIds = array_values(array_diff(array_unique(array_column($activities, 'idtreino_cronograma')), array_keys($workoutTemplates)));
    if ($missingIds) {
        $placeholders = implode(',', array_fill(0, count($missingIds), '?'));
        $templatesStmt = $pdo->prepare("SELECT t.*, c.nome AS cronograma_nome FROM treinos_cronograma t JOIN cronogramas c ON c.idcronograma = t.idcronograma WHERE c.idusuario = ? AND t.idtreino IN ({$placeholders})");
        $templatesStmt->execute([$idUsuario, ...$missingIds]);
        foreach ($templatesStmt->fetchAll() as $workout) $workoutTemplates[$workout['idtreino']] = $workout + ['data_original' => '', 'data_treino' => '', 'excecao' => false];
    }
    foreach ($activities as $activity) {
        $workoutId = (string) ($activity['idtreino_cronograma'] ?? '');
        if ($workoutId === '' || !isset($workoutTemplates[$workoutId])) continue;

        $template = $workoutTemplates[$workoutId];
        if ($template === []) continue;

        $activityDate = (new DateTimeImmutable((string) $activity['data_inicio']))->format('Y-m-d');
        $candidate = null;
        $originalHint = trim((string) ($activity['data_ocorrencia_origem'] ?? ''));
        $plannedHint = trim((string) ($activity['data_ocorrencia_planejada'] ?? ''));

        if ($plannedHint !== '' && !empty($byWorkout[$workoutId])) {
            foreach ($byWorkout[$workoutId] as $index) {
                if (isset($used[$index])) continue;
                $candidatePlanned = (string) ($ocorrencias[$index]['data_planejada'] ?? $ocorrencias[$index]['data_treino'] ?? '');
                $candidateDisplay = (string) ($ocorrencias[$index]['data_treino'] ?? '');
                if ($candidatePlanned === $plannedHint || $candidateDisplay === $plannedHint) {
                    $candidate = $index;
                    break;
                }
            }
        }

        $planWasCorrectedAwayFromOrigin = $plannedHint !== '' && $originalHint !== '' && $plannedHint !== $originalHint;
        if ($candidate === null && !$planWasCorrectedAwayFromOrigin && $originalHint !== '' && !empty($byWorkout[$workoutId])) {
            foreach ($byWorkout[$workoutId] as $index) {
                if (isset($used[$index])) continue;
                if ((string) $ocorrencias[$index]['data_original'] === $originalHint) {
                    $candidate = $index;
                    break;
                }
            }
        }

        if ($candidate === null && $originalHint === '' && $plannedHint === '' && !empty($byWorkout[$workoutId])) {
            $activityDateObj = new DateTimeImmutable($activityDate);
            $bestDistance = PHP_INT_MAX;
            foreach ($byWorkout[$workoutId] as $index) {
                if (isset($used[$index])) continue;
                $planned = new DateTimeImmutable((string) $ocorrencias[$index]['data_planejada']);
                if ($planned->format('Y-m-d') !== $activityDate) continue;
                $distance = abs($planned->diff($activityDateObj)->days ?? 0);
                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $candidate = $index;
                }
            }
        }

        if ($candidate === null && $plannedHint !== '') {
            $synthetic = $template;
            $synthetic['data_original'] = $plannedHint;
            $synthetic['data_treino'] = $plannedHint;
            $synthetic['data_planejada'] = $plannedHint;
            $synthetic['hora_planejada'] = trim((string) ($activity['hora_ocorrencia_planejada'] ?? '')) !== ''
                ? substr((string) $activity['hora_ocorrencia_planejada'], 0, 5)
                : substr((string) ($template['hora_inicio'] ?? '00:00'), 0, 5);
            $synthetic['excecao'] = false;
            $synthetic['concluido'] = false;
            $synthetic['suprimido_por_realizacao_semana'] = false;
            $candidate = count($ocorrencias);
            $ocorrencias[] = $synthetic;
            $byWorkout[$workoutId][] = $candidate;
        }

        if ($candidate === null) continue;
        $used[$candidate] = true;
        $plannedDate = $plannedHint !== '' ? $plannedHint : (string) $ocorrencias[$candidate]['data_planejada'];
        $plannedTimeHint = trim((string) ($activity['hora_ocorrencia_planejada'] ?? ''));
        $plannedTime = $plannedTimeHint !== '' ? substr($plannedTimeHint, 0, 5) : (string) $ocorrencias[$candidate]['hora_planejada'];
        if ($plannedHint !== '') $ocorrencias[$candidate]['data_planejada'] = $plannedHint;
        if ($plannedTimeHint !== '') $ocorrencias[$candidate]['hora_planejada'] = $plannedTime;
        $startAt = new DateTimeImmutable((string) $activity['data_inicio']);
        $endAt = !empty($activity['data_fim']) ? new DateTimeImmutable((string) $activity['data_fim']) : null;
        $realizedTime = $startAt->format('H:i');

        $ocorrencias[$candidate]['concluido'] = true;
        $ocorrencias[$candidate]['idregistro'] = (string) $activity['idregistro'];
        $ocorrencias[$candidate]['data_realizada'] = $activityDate;
        $ocorrencias[$candidate]['hora_realizada'] = $realizedTime;
        $ocorrencias[$candidate]['realizado_fora_planejado'] = $activityDate !== $plannedDate;
        $ocorrencias[$candidate]['data_treino'] = $activityDate;
        $ocorrencias[$candidate]['hora_inicio'] = $startAt->format('H:i:s');

        if ($endAt !== null) {
            $ocorrencias[$candidate]['hora_fim'] = $endAt->format('H:i:s');
            $ocorrencias[$candidate]['termina_dia_seguinte'] = $endAt->format('Y-m-d') !== $startAt->format('Y-m-d');
        } else {
            $baseStart = ((int) substr($plannedTime, 0, 2)) * 60 + (int) substr($plannedTime, 3, 2);
            $baseEnd = ((int) substr((string) $ocorrencias[$candidate]['hora_fim'], 0, 2)) * 60 + (int) substr((string) $ocorrencias[$candidate]['hora_fim'], 3, 2);
            $duration = stridebr_db_bool($ocorrencias[$candidate]['termina_dia_seguinte'] ?? false) ? 1440 - $baseStart + $baseEnd : $baseEnd - $baseStart;
            $duration = max(1, $duration);
            $newEnd = $startAt->modify('+' . $duration . ' minutes');
            $ocorrencias[$candidate]['hora_fim'] = $newEnd->format('H:i:s');
            $ocorrencias[$candidate]['termina_dia_seguinte'] = $newEnd->format('Y-m-d') !== $startAt->format('Y-m-d');
        }
    }

    usort($ocorrencias, static fn(array $a, array $b): int => [$a['data_treino'], $a['hora_inicio'], $a['ordem']] <=> [$b['data_treino'], $b['hora_inicio'], $b['ordem']]);
    return $ocorrencias;
}

function cronogramaAjustarHistoricoRegistro(PDO $pdo, string $idUsuario, string $idRegistro, string $dataPlanejada, ?string $horaPlanejada = null, ?string $dataRealizada = null, ?string $horaRealizada = null): array
{
    $idRegistro = trim($idRegistro);
    if ($idRegistro === '') throw new InvalidArgumentException(stridebr_t('schedule.validation.activity_invalid'));
    $planned = cronogramaValidarDataIso(trim($dataPlanejada));
    $horaPlanejada = trim((string) $horaPlanejada);
    if ($horaPlanejada !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $horaPlanejada)) throw new InvalidArgumentException(stridebr_t('schedule.validation.planned_time_invalid'));

    $stmt = $pdo->prepare("SELECT idregistro, idtreino_cronograma, data_ocorrencia_origem, data_inicio, data_fim FROM registros_atividade WHERE idregistro = :registro AND idusuario = :usuario AND excluido_em IS NULL AND idtreino_cronograma IS NOT NULL LIMIT 1");
    $stmt->execute([':registro' => $idRegistro, ':usuario' => $idUsuario]);
    $activity = $stmt->fetch();
    if (!$activity) throw new RuntimeException(stridebr_t('schedule.validation.linked_activity_missing'));

    $currentStart = new DateTimeImmutable((string) $activity['data_inicio']);
    $currentEnd = !empty($activity['data_fim']) ? new DateTimeImmutable((string) $activity['data_fim']) : null;
    $durationSeconds = $currentEnd !== null ? max(0, $currentEnd->getTimestamp() - $currentStart->getTimestamp()) : null;

    $realizedDate = trim((string) $dataRealizada);
    $realizedTime = trim((string) $horaRealizada);
    if ($realizedDate === '') $realizedDate = $currentStart->format('Y-m-d');
    if ($realizedTime === '') $realizedTime = $currentStart->format('H:i');
    $realized = cronogramaValidarDataIso($realizedDate);
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $realizedTime)) throw new InvalidArgumentException(stridebr_t('schedule.validation.actual_time_invalid'));
    $realizedAt = new DateTimeImmutable($realized->format('Y-m-d') . ' ' . $realizedTime, $currentStart->getTimezone());
    $newEnd = $durationSeconds !== null ? $realizedAt->modify('+' . $durationSeconds . ' seconds') : null;

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $update = $pdo->prepare('UPDATE registros_atividade SET data_ocorrencia_planejada = :planejada, hora_ocorrencia_planejada = :hora_planejada, data_inicio = :realizada, data_fim = :fim, data_atualizacao = NOW() WHERE idregistro = :registro AND idusuario = :usuario AND excluido_em IS NULL');
        $update->execute([
            ':planejada' => $planned->format('Y-m-d'),
            ':hora_planejada' => $horaPlanejada !== '' ? $horaPlanejada . ':00' : null,
            ':realizada' => $realizedAt->format('Y-m-d H:i:s'),
            ':fim' => $newEnd?->format('Y-m-d H:i:s'),
            ':registro' => $idRegistro,
            ':usuario' => $idUsuario,
        ]);
        $session = $pdo->prepare('UPDATE sessoes_treino SET data_ocorrencia_planejada = :planejada, hora_ocorrencia_planejada = :hora_planejada, data_inicio = :realizada, data_fim = :fim, data_atualizacao = NOW() WHERE idregistro_atividade = :registro AND idusuario = :usuario');
        $session->execute([
            ':planejada' => $planned->format('Y-m-d'),
            ':hora_planejada' => $horaPlanejada !== '' ? $horaPlanejada . ':00' : null,
            ':realizada' => $realizedAt->format('Y-m-d H:i:s'),
            ':fim' => $newEnd?->format('Y-m-d H:i:s'),
            ':registro' => $idRegistro,
            ':usuario' => $idUsuario,
        ]);
        if ($ownsTransaction) $pdo->commit();
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    return [
        'idregistro' => $idRegistro,
        'data_planejada' => $planned->format('Y-m-d'),
        'hora_planejada' => $horaPlanejada,
        'data_realizada' => $realizedAt->format('Y-m-d'),
        'hora_realizada' => $realizedAt->format('H:i'),
    ];
}

function cronogramaCorrigirPlanejamentoRegistro(PDO $pdo, string $idUsuario, string $idRegistro, string $dataPlanejada, ?string $horaPlanejada = null): array
{
    $stmt = $pdo->prepare('SELECT data_inicio FROM registros_atividade WHERE idregistro = :registro AND idusuario = :usuario AND excluido_em IS NULL LIMIT 1');
    $stmt->execute([':registro' => trim($idRegistro), ':usuario' => $idUsuario]);
    $start = $stmt->fetchColumn();
    if ($start === false) throw new RuntimeException(stridebr_t('schedule.validation.activity_missing'));
    $performed = new DateTimeImmutable((string) $start);
    return cronogramaAjustarHistoricoRegistro($pdo, $idUsuario, $idRegistro, $dataPlanejada, $horaPlanejada, $performed->format('Y-m-d'), $performed->format('H:i'));
}

function cronogramaListarOcorrenciasConciliadas(PDO $pdo, string $idUsuario, string $dataInicio, string $dataFim, ?string $idCronograma = null): array
{
    $start = cronogramaValidarDataIso($dataInicio);
    $end = cronogramaValidarDataIso($dataFim);
    $rangeDays = max(1, (int) ($start->diff($end)->days ?? 0) + 1);
    $margin = max(14, intdiv(max(0, 93 - $rangeDays), 2));
    $expandedStart = $start->modify('-' . $margin . ' days');
    $expandedEnd = $end->modify('+' . $margin . ' days');
    $items = cronogramaListarOcorrencias($pdo, $idUsuario, $expandedStart->format('Y-m-d'), $expandedEnd->format('Y-m-d'), $idCronograma);
    $items = cronogramaConciliarOcorrenciasComRegistros($pdo, $idUsuario, $items, $dataInicio, $dataFim, $idCronograma);
    return array_values(array_filter($items, static function (array $item) use ($dataInicio, $dataFim): bool {
        if (!empty($item['suprimido_por_realizacao_semana'])) return false;
        $display = (string) ($item['data_treino'] ?? '');
        return $display >= $dataInicio && $display <= $dataFim;
    }));
}
