<?php

declare(strict_types=1);

require_once __DIR__ . '/cronograma.php';
require_once __DIR__ . '/workout_definition.php';

function treinoEstruturaTexto(mixed $value, int $max, string $field): ?string
{
    if ($value === null) return null;
    $text = trim((string) $value);
    if ($text === '') return null;
    if (stridebr_length($text) > $max) throw new InvalidArgumentException($field . ' excede o tamanho permitido.');
    return $text;
}

function treinoEstruturaNumeroOpcional(mixed $value, float $min, float $max, string $field): ?float
{
    if ($value === null || trim((string) $value) === '') return null;
    $raw = str_replace(',', '.', trim((string) $value));
    if (!is_numeric($raw)) throw new InvalidArgumentException($field . ' precisa ser numérico.');
    $number = (float) $raw;
    if (!is_finite($number) || $number < $min || $number > $max) throw new InvalidArgumentException($field . ' está fora do intervalo permitido.');
    return $number;
}

function treinoEstruturaSegundosTexto(mixed $seconds, string $field): ?string
{
    if ($seconds === null || $seconds === '') return null;
    if (filter_var($seconds, FILTER_VALIDATE_INT) === false) throw new InvalidArgumentException($field . ' precisa ser inteiro.');
    $value = (int) $seconds;
    if ($value < 0 || $value > 86400) throw new InvalidArgumentException($field . ' está fora do intervalo permitido.');
    return $value . 's';
}

function treinoEstruturaDistanciaTexto(mixed $meters, string $field): ?string
{
    if ($meters === null || $meters === '') return null;
    $value = treinoEstruturaNumeroOpcional($meters, 0, 10000000, $field);
    if ($value === null) return null;
    $formatted = rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    return $formatted . ' m';
}

function treinoEstruturaNormalizar(PDO $pdo, string $idUsuario, array $structure): array
{
    $rows = array_key_exists('exercises', $structure) ? $structure['exercises'] : $structure;
    if (!is_array($rows)) throw new InvalidArgumentException('structure.exercises precisa ser uma lista.');
    if (count($rows) > 200) throw new InvalidArgumentException('Um treino pode ter no máximo 200 passos.');
    $catalog = cronogramaListarExerciciosBiblioteca($pdo, $idUsuario);
    $catalogById = array_column($catalog, null, 'idexercicio');
    $normalized = [];
    foreach ($rows as $index => $row) {
        if (!is_array($row)) throw new InvalidArgumentException('Cada passo da estrutura precisa ser um objeto.');
        $structuredInput = $row;
        if (array_key_exists('step_type', $row)) $structuredInput['tipo_passo'] = $row['step_type'];
        if (array_key_exists('repeat_count', $row)) $structuredInput['repeticoes_bloco'] = $row['repeat_count'];
        if (isset($row['target']) && is_array($row['target'])) {
            $structuredInput['alvo_tipo'] = $row['target']['type'] ?? null;
            $structuredInput['alvo_min'] = $row['target']['min'] ?? null;
            $structuredInput['alvo_max'] = $row['target']['max'] ?? null;
            $structuredInput['alvo_unidade'] = $row['target']['unit'] ?? null;
        }
        if (isset($row['recovery']) && is_array($row['recovery'])) {
            $structuredInput['recuperacao_duracao_s'] = $row['recovery']['duration_s'] ?? null;
            $structuredInput['recuperacao_distancia_m'] = $row['recovery']['distance_m'] ?? null;
        }
        $structured = cronogramaNormalizarPassoEstruturado($structuredInput);
        $type = (string) $structured['tipo_passo'];
        $exerciseId = trim((string) ($row['exercise_id'] ?? $row['idexercicio'] ?? ''));
        $name = trim((string) ($row['name'] ?? $row['title'] ?? $row['nome'] ?? $row['nome_snapshot'] ?? ''));
        if ($type === 'exercise') {
            if ($exerciseId === '' || !isset($catalogById[$exerciseId])) throw new InvalidArgumentException('exercise_id inválido na posição ' . ($index + 1) . '.');
            $name = (string) $catalogById[$exerciseId]['nome'];
        } else {
            $exerciseId = '';
            if ($name === '') $name = cronogramaPassoNomePadrao($type);
        }
        if (stridebr_length($name) > 120) throw new InvalidArgumentException('name excede o tamanho permitido na posição ' . ($index + 1) . '.');
        $setsRaw = $row['sets'] ?? $row['series'] ?? null;
        $sets = null;
        if ($setsRaw !== null && $setsRaw !== '') {
            if (filter_var($setsRaw, FILTER_VALIDATE_INT) === false) throw new InvalidArgumentException('sets precisa ser inteiro na posição ' . ($index + 1) . '.');
            $sets = (int) $setsRaw;
            if ($sets < 1 || $sets > 99) throw new InvalidArgumentException('sets precisa ficar entre 1 e 99.');
        }
        $rpe = treinoEstruturaNumeroOpcional($row['rpe'] ?? null, 0, 10, 'rpe');
        $rir = treinoEstruturaNumeroOpcional($row['rir'] ?? null, 0, 10, 'rir');
        $rest = array_key_exists('rest_s', $row)
            ? treinoEstruturaSegundosTexto($row['rest_s'], 'rest_s')
            : treinoEstruturaTexto($row['rest'] ?? $row['descanso'] ?? null, 40, 'rest');
        $duration = array_key_exists('duration_s', $row)
            ? treinoEstruturaSegundosTexto($row['duration_s'], 'duration_s')
            : treinoEstruturaTexto($row['duration'] ?? $row['duracao'] ?? null, 40, 'duration');
        $distance = array_key_exists('distance_m', $row)
            ? treinoEstruturaDistanciaTexto($row['distance_m'], 'distance_m')
            : treinoEstruturaTexto($row['distance'] ?? $row['distancia'] ?? null, 40, 'distance');
        $normalizedRow = [
            'idexercicio' => $exerciseId !== '' ? $exerciseId : null,
            'nome' => $name,
            'series' => $sets,
            'repeticoes' => treinoEstruturaTexto($row['repetitions'] ?? $row['repeticoes'] ?? null, 40, 'repetitions'),
            'carga' => treinoEstruturaTexto($row['load'] ?? $row['carga'] ?? null, 40, 'load'),
            'bloco' => treinoEstruturaTexto($row['block'] ?? $row['bloco'] ?? null, 40, 'block'),
            'cluster' => treinoEstruturaTexto($row['cluster'] ?? null, 80, 'cluster'),
            'descanso' => $rest,
            'observacoes' => treinoEstruturaTexto($row['notes'] ?? $row['observacoes'] ?? null, 5000, 'notes'),
            'duracao' => $duration,
            'distancia' => $distance,
            'intensidade' => treinoEstruturaTexto($row['intensity'] ?? $row['intensidade'] ?? null, 80, 'intensity'),
            'rpe' => $rpe,
            'rir' => $rir,
            'tempo_execucao' => treinoEstruturaTexto($row['tempo'] ?? $row['tempo_execucao'] ?? null, 40, 'tempo'),
            'cadencia' => treinoEstruturaTexto($row['cadence'] ?? $row['cadencia'] ?? null, 40, 'cadence'),
            'tipo_passo' => $type,
            'repeticoes_bloco' => $structured['repeticoes_bloco'],
            'alvo_tipo' => $structured['alvo_tipo'],
            'alvo_min' => $structured['alvo_min'],
            'alvo_max' => $structured['alvo_max'],
            'alvo_unidade' => $structured['alvo_unidade'],
            'recuperacao_duracao_s' => $structured['recuperacao_duracao_s'],
            'recuperacao_distancia_m' => $structured['recuperacao_distancia_m'],
            'metodo_prescricao' => trim((string) ($row['prescription_method'] ?? $row['metodo_prescricao'] ?? 'standard')) ?: 'standard',
            'prescription' => is_array($row['prescription'] ?? null) ? $row['prescription'] : workoutPrescriptionDecode($row['config_prescricao'] ?? null),
        ];
        $group = is_array($row['group'] ?? null) ? $row['group'] : [];
        $normalizedRow['grupo_chave'] = trim((string) ($group['id'] ?? $group['key'] ?? $row['grupo_chave'] ?? $row['idgrupo_prescricao'] ?? ''));
        $normalizedRow['grupo_tipo'] = trim((string) ($group['type'] ?? $row['grupo_tipo'] ?? ''));
        $normalizedRow['grupo_voltas'] = $group['rounds'] ?? $row['grupo_voltas'] ?? null;
        $normalizedRow['grupo_descanso_entre_exercicios_s'] = $group['rest_between_exercises_s'] ?? $row['grupo_descanso_entre_exercicios_s'] ?? null;
        $normalizedRow['grupo_descanso_pos_volta_s'] = $group['rest_after_round_s'] ?? $row['grupo_descanso_pos_volta_s'] ?? null;
        if ($type === 'exercise') {
            $normalizedRow = workoutPrescriptionLegacyFields($normalizedRow, workoutPrescriptionNormalize($normalizedRow));
        } else {
            $normalizedRow['metodo_prescricao'] = 'standard';
            $normalizedRow['config_prescricao'] = null;
            $normalizedRow['grupo_chave'] = '';
            $normalizedRow['grupo_tipo'] = '';
        }
        $normalized[] = $normalizedRow;
    }
    return $normalized;
}

function treinoExerciciosMelhoresCargasKg(PDO $pdo, string $idUsuario, array $exerciseIds): array
{
    $exerciseIds = array_values(array_unique(array_filter(array_map(static fn(mixed $id): string => trim((string) $id), $exerciseIds))));
    if ($exerciseIds === []) return [];
    $placeholders = implode(',', array_fill(0, count($exerciseIds), '?'));
    $stmt = $pdo->prepare("SELECT sea.idexercicio, MAX(sea.carga_kg) AS best_load_kg
        FROM series_exercicio_atividade sea
        JOIN registros_atividade ra ON ra.idregistro=sea.idregistro
        WHERE ra.idusuario=? AND ra.excluido_em IS NULL AND ra.status='concluido'
          AND sea.concluida=TRUE AND sea.idexercicio IN ({$placeholders})
        GROUP BY sea.idexercicio");
    $stmt->execute(array_merge([$idUsuario], $exerciseIds));
    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        if ($row['idexercicio'] === null || $row['best_load_kg'] === null) continue;
        $result[(string) $row['idexercicio']] = (float) $row['best_load_kg'];
    }
    return $result;
}

function treinoExercicioMelhorCargaKg(PDO $pdo, string $idUsuario, string $exerciseId): ?float
{
    $exerciseId = trim($exerciseId);
    if ($exerciseId === '') return null;
    $values = treinoExerciciosMelhoresCargasKg($pdo, $idUsuario, [$exerciseId]);
    return $values[$exerciseId] ?? null;
}

function treinoAgendadoSalvarEstrutura(PDO $pdo, string $idUsuario, string $idAgendamento, array $rows): void
{
    $owner = $pdo->prepare("SELECT idagendamento FROM treinos_agendados WHERE idagendamento=:id AND idatleta=:user AND idcriador=:user_creator AND origem='usuario' AND status IN ('rascunho','publicado') LIMIT 1");
    $owner->execute([':id' => $idAgendamento, ':user' => $idUsuario, ':user_creator' => $idUsuario]);
    if (!$owner->fetchColumn()) throw new RuntimeException('Este treino não pode ter sua estrutura editada pelo usuário atual.');
    $normalized = treinoEstruturaNormalizar($pdo, $idUsuario, ['exercises' => $rows]);
    $owns = !$pdo->inTransaction();
    if ($owns) $pdo->beginTransaction();
    try {
        workoutDefinitionWriteScheduledItems($pdo, $idAgendamento, $normalized);
        $pdo->prepare('UPDATE treinos_agendados SET data_atualizacao=NOW() WHERE idagendamento=:id AND idatleta=:user')->execute([':id' => $idAgendamento, ':user' => $idUsuario]);
        if ($owns) $pdo->commit();
    } catch (Throwable $e) {
        if ($owns && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function treinoTemplateSalvarEstrutura(PDO $pdo, string $idUsuario, string $idTemplate, array $rows): void
{
    $normalized = treinoEstruturaNormalizar($pdo, $idUsuario, ['exercises' => $rows]);
    cronogramaSalvarExerciciosTreinoModelo($pdo, $idUsuario, $idTemplate, $normalized);
}
