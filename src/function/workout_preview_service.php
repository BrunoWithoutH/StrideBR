<?php

declare(strict_types=1);

require_once __DIR__ . '/cronograma.php';
require_once __DIR__ . '/api_v1.php';
require_once __DIR__ . '/workout_definition.php';

function workoutPreviewValidDate(string $value): bool
{
    if ($value === '') return false;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('America/Sao_Paulo'));
    return $date !== false && $date->format('Y-m-d') === $value;
}

function workoutPreviewLinkedActivity(PDO $pdo, string $userId, string $workoutId, string $activityId, string $occurrenceOriginal, string $plannedDate): array
{
    $params = [':user' => $userId, ':workout' => $workoutId];
    $where = "ra.idusuario=:user AND ra.idtreino_cronograma=:workout AND ra.status='concluido' AND ra.excluido_em IS NULL";
    if ($activityId !== '') {
        $where .= ' AND ra.idregistro=:activity';
        $params[':activity'] = $activityId;
    } elseif (workoutPreviewValidDate($occurrenceOriginal) || workoutPreviewValidDate($plannedDate)) {
        $dateParts = [];
        if (workoutPreviewValidDate($occurrenceOriginal)) {
            $dateParts[] = 'ra.data_ocorrencia_origem=CAST(:origin AS date)';
            $params[':origin'] = $occurrenceOriginal;
        }
        if (workoutPreviewValidDate($plannedDate)) {
            $dateParts[] = 'ra.data_ocorrencia_planejada=CAST(:planned AS date)';
            $params[':planned'] = $plannedDate;
        }
        $where .= ' AND (' . implode(' OR ', $dateParts) . ')';
    } else {
        return [];
    }
    $stmt = $pdo->prepare("SELECT ra.idregistro,ra.data_ocorrencia_origem,ra.data_ocorrencia_planejada FROM registros_atividade ra WHERE {$where} ORDER BY ra.data_inicio DESC,ra.idregistro LIMIT 1");
    $stmt->execute($params);
    $row = $stmt->fetch();
    if (!$row) return [];
    $storedOrigin = substr(trim((string) ($row['data_ocorrencia_origem'] ?? '')), 0, 10);
    $storedPlanned = substr(trim((string) ($row['data_ocorrencia_planejada'] ?? '')), 0, 10);
    if (workoutPreviewValidDate($occurrenceOriginal) && $storedOrigin !== '' && $storedOrigin !== $occurrenceOriginal) return [];
    if (workoutPreviewValidDate($plannedDate) && $storedPlanned !== '' && $storedPlanned !== $plannedDate) return [];
    return $row;
}

function workoutPreviewPlannedExercise(array $row): array
{
    return [
        'nome' => (string) ($row['nome_snapshot'] ?? ''),
        'series' => $row['series'] !== null ? (int) $row['series'] : null,
        'repeticoes' => (string) ($row['repeticoes'] ?? ''),
        'carga' => (string) ($row['carga'] ?? ''),
        'descanso' => (string) ($row['descanso'] ?? ''),
        'bloco' => (string) ($row['bloco'] ?? ''),
        'cluster' => (string) ($row['cluster'] ?? ''),
        'duracao' => (string) ($row['duracao'] ?? ''),
        'distancia' => (string) ($row['distancia'] ?? ''),
        'tipo_passo' => (string) ($row['tipo_passo'] ?? 'exercise'),
        'repeticoes_bloco' => $row['repeticoes_bloco'] !== null ? (int) $row['repeticoes_bloco'] : null,
        'alvo_tipo' => (string) ($row['alvo_tipo'] ?? ''),
        'alvo_min' => $row['alvo_min'] !== null ? (float) $row['alvo_min'] : null,
        'alvo_max' => $row['alvo_max'] !== null ? (float) $row['alvo_max'] : null,
        'alvo_unidade' => (string) ($row['alvo_unidade'] ?? ''),
        'recuperacao_duracao_s' => $row['recuperacao_duracao_s'] !== null ? (int) $row['recuperacao_duracao_s'] : null,
        'recuperacao_distancia_m' => $row['recuperacao_distancia_m'] !== null ? (float) $row['recuperacao_distancia_m'] : null,
        'observacoes' => (string) ($row['observacoes'] ?? ''),
    ];
}

function workoutPreviewPerformedExercise(array $exercise): array
{
    return [
        'exercise_id' => $exercise['exercise_id'] ?? null,
        'nome' => (string) ($exercise['name'] ?? ''),
        'sets' => array_values(array_map(static fn(array $set): array => [
            'number' => (int) ($set['number'] ?? 0),
            'type' => (string) ($set['type'] ?? 'work'),
            'repetitions' => $set['repetitions'] ?? null,
            'load_kg' => $set['load_kg'] ?? null,
            'duration_s' => $set['duration_s'] ?? null,
            'distance_m' => $set['distance_m'] ?? null,
            'rir' => $set['rir'] ?? null,
            'rpe' => $set['rpe'] ?? null,
            'completed' => stridebr_db_bool($set['completed'] ?? false),
            'notes' => $set['notes'] ?? null,
        ], (array) ($exercise['sets'] ?? []))),
    ];
}

function workoutPreviewData(PDO $pdo, string $userId, string $workoutId, array $context = []): array
{
    $occurrenceOriginal = substr(trim((string) ($context['occurrence_original'] ?? '')), 0, 10);
    $plannedDate = substr(trim((string) ($context['planned_date'] ?? '')), 0, 10);
    $activityId = trim((string) ($context['activity_id'] ?? ''));
    $referenceDate = workoutPreviewValidDate($plannedDate) ? $plannedDate : (workoutPreviewValidDate($occurrenceOriginal) ? $occurrenceOriginal : '');
    $today = (new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
    $mode = $referenceDate !== '' && $referenceDate < $today ? 'missed' : 'planned';
    $workout = cronogramaBuscarTreino($pdo, $workoutId, $userId);
    if ($workout === []) {
        return [
            'preview_mode' => $mode,
            'activity_id' => null,
            'exercises' => [],
            'presentation' => ['sections' => [], 'groups' => [], 'items' => []],
            'capabilities' => ['can_start_session' => false, 'can_quick_complete' => false, 'quick_complete_mode' => 'unsupported'],
            'actual' => null,
        ];
    }
    $definition = workoutDefinitionBuild($workout, cronogramaListarTreinoExercicios($pdo, $workoutId, $userId), 'schedule');
    $presentation = workoutDefinitionPresentation($definition);
    $capabilities = workoutDefinitionCapabilities($definition);
    $linked = workoutPreviewLinkedActivity($pdo, $userId, $workoutId, $activityId, $occurrenceOriginal, $plannedDate);
    if ($linked !== []) {
        $detail = stridebr_api_activity_detail($pdo, (string) $linked['idregistro'], $userId);
        if ($detail !== []) {
            $exercises = [];
            foreach ((array) ($detail['strength_exercises'] ?? []) as $exercise) {
                $performed = workoutPreviewPerformedExercise($exercise);
                if ($performed['sets'] !== []) $exercises[] = $performed;
            }
            return [
                'preview_mode' => 'performed',
                'activity_id' => (string) $detail['id'],
                'exercises' => $exercises,
                'presentation' => $presentation,
                'capabilities' => $capabilities,
                'actual' => [
                    'started_at' => $detail['started_at'] ?? null,
                    'ended_at' => $detail['ended_at'] ?? null,
                    'duration_s' => $detail['duration_s'] ?? null,
                    'distance_m' => $detail['distance_m'] ?? null,
                    'perceived_effort' => $detail['perceived_effort'] ?? null,
                ],
            ];
        }
    }
    return [
        'preview_mode' => $mode,
        'activity_id' => null,
        'exercises' => array_map('workoutPreviewPlannedExercise', workoutDefinitionRows($definition)),
        'presentation' => $presentation,
        'capabilities' => $capabilities,
        'actual' => null,
    ];
}
