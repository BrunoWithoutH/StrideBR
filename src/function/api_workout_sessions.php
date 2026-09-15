<?php

declare(strict_types=1);

require_once __DIR__ . '/api_workouts.php';
require_once __DIR__ . '/workout_session_service.php';

final class WorkoutSessionIdempotencyConflictException extends RuntimeException
{
}

function stridebr_api_workout_session_workout_id(array $session): ?string
{
    if (($session['origem_externa'] ?? '') === 'teams' && !empty($session['referencia_externa'])) return stridebr_api_workout_id_teams((string) $session['referencia_externa']);
    if (!empty($session['idagendamento_origem'])) return stridebr_api_workout_id_scheduled((string) $session['idagendamento_origem']);
    if (!empty($session['idtreino_origem'])) {
        $date = trim((string) ($session['data_ocorrencia_origem'] ?? $session['data_ocorrencia_planejada'] ?? ''));
        if ($date !== '') return stridebr_api_workout_id_recurring((string) $session['idtreino_origem'], substr($date, 0, 10));
    }
    return null;
}

function stridebr_api_workout_session_history_payload(array $history): ?array
{
    $latest = is_array($history['ultima'] ?? null) ? $history['ultima'] : null;
    if ($latest === null && empty($history['melhor_carga'])) return null;
    return [
        'last' => $latest === null ? null : [
            'date' => !empty($latest['data_iso']) ? (string) $latest['data_iso'] : null,
            'sets_completed' => (int) ($latest['series_concluidas'] ?? 0),
            'sets_total' => (int) ($latest['series_total'] ?? 0),
            'repetitions' => trim((string) ($latest['repeticoes'] ?? '')) ?: null,
            'load' => trim((string) ($latest['carga'] ?? '')) ?: null,
            'sets' => array_map(static fn(array $set): array => [
                'number' => (int) ($set['numero'] ?? 0),
                'repetitions' => trim((string) ($set['repeticoes'] ?? '')) ?: null,
                'load' => trim((string) ($set['carga'] ?? '')) ?: null,
                'completed' => stridebr_api_bool($set['concluida'] ?? false),
            ], (array) ($latest['series'] ?? [])),
        ],
        'best_load' => trim((string) ($history['melhor_carga'] ?? '')) ?: null,
    ];
}

function stridebr_api_workout_session_payload(array $session): array
{
    $exercises = [];
    $doneExercises = 0;
    $totalSets = 0;
    $doneSets = 0;
    foreach ((array) ($session['exercicios'] ?? []) as $exercise) {
        $sets = [];
        foreach ((array) ($exercise['series'] ?? []) as $set) {
            $completed = stridebr_api_bool($set['concluida'] ?? false);
            if ($completed) $doneSets++;
            $totalSets++;
            $sets[] = [
                'id' => (string) $set['idserie'],
                'number' => (int) $set['numero'],
                'completed' => $completed,
                'planned_repetitions' => trim((string) ($exercise['repeticoes_snapshot'] ?? '')) ?: null,
                'planned_load' => trim((string) ($exercise['carga_snapshot'] ?? '')) ?: null,
                'actual_repetitions' => trim((string) ($set['repeticoes_realizadas'] ?? '')) ?: null,
                'actual_load' => trim((string) ($set['carga_realizada'] ?? '')) ?: null,
                'repetitions' => trim((string) ($set['repeticoes_realizadas'] ?? '')) ?: null,
                'load' => trim((string) ($set['carga_realizada'] ?? '')) ?: null,
                'completed_at' => stridebr_api_iso(isset($set['data_conclusao']) ? (string) $set['data_conclusao'] : null),
            ];
        }
        $completed = stridebr_api_bool($exercise['concluido'] ?? false);
        if ($completed) $doneExercises++;
        $exercises[] = [
            'id' => (string) $exercise['idsessao_exercicio'],
            'exercise_id' => !empty($exercise['idexercicio']) ? (string) $exercise['idexercicio'] : null,
            'name' => (string) $exercise['nome_snapshot'],
            'order' => (int) $exercise['ordem'],
            'block' => !empty($exercise['bloco_snapshot']) ? (string) $exercise['bloco_snapshot'] : null,
            'cluster' => !empty($exercise['cluster_snapshot']) ? (string) $exercise['cluster_snapshot'] : null,
            'completed' => $completed,
            'planned' => [
                'sets' => $exercise['series_planejadas'] !== null ? (int) $exercise['series_planejadas'] : null,
                'repetitions' => trim((string) ($exercise['repeticoes_snapshot'] ?? '')) ?: null,
                'load' => trim((string) ($exercise['carga_snapshot'] ?? '')) ?: null,
                'rest' => trim((string) ($exercise['descanso_snapshot'] ?? '')) ?: null,
                'rest_s' => function_exists('stridebr_api_training_text_seconds') ? stridebr_api_training_text_seconds(trim((string) ($exercise['descanso_snapshot'] ?? '')) ?: null) : null,
                'notes' => trim((string) ($exercise['observacoes_snapshot'] ?? '')) ?: null,
                'duration' => trim((string) ($exercise['duracao_snapshot'] ?? '')) ?: null,
                'distance' => trim((string) ($exercise['distancia_snapshot'] ?? '')) ?: null,
                'intensity' => trim((string) ($exercise['intensidade_snapshot'] ?? '')) ?: null,
                'rpe' => is_numeric($exercise['rpe_snapshot'] ?? null) ? (float) $exercise['rpe_snapshot'] : null,
                'rir' => is_numeric($exercise['rir_snapshot'] ?? null) ? (float) $exercise['rir_snapshot'] : null,
                'tempo' => trim((string) ($exercise['tempo_execucao_snapshot'] ?? '')) ?: null,
                'cadence' => trim((string) ($exercise['cadencia_snapshot'] ?? '')) ?: null,
            ],
            'sets' => $sets,
            'history' => stridebr_api_workout_session_history_payload((array) ($exercise['historico'] ?? [])),
        ];
    }
    $workoutId = stridebr_api_workout_session_workout_id($session);
    $source = (($session['origem_externa'] ?? '') === 'teams') ? 'teams' : (!empty($session['idagendamento_origem']) ? 'scheduled' : (!empty($session['idtreino_origem']) ? 'recurring' : 'manual'));
    return [
        'id' => (string) $session['idsessao'],
        'workout_id' => $workoutId,
        'workout' => $workoutId !== null ? ['id' => $workoutId, 'kind' => $source === 'teams' ? 'institutional' : ($source === 'scheduled' ? 'scheduled' : ($source === 'recurring' ? 'recurring' : 'manual')), 'source' => $source] : null,
        'institutional_context' => $source === 'teams' ? (json_decode((string) ($session['contexto_institucional_snapshot'] ?? '{}'), true) ?: null) : null,
        'title' => (string) $session['titulo_snapshot'],
        'source' => $source,
        'status' => (string) $session['status'],
        'started_at' => stridebr_api_iso((string) ($session['data_inicio'] ?? '')),
        'ended_at' => stridebr_api_iso(isset($session['data_fim']) ? (string) $session['data_fim'] : null),
        'activity' => !empty($session['idregistro_atividade']) ? ['id' => (string) $session['idregistro_atividade']] : null,
        'schedule' => !empty($session['idcronograma_origem']) ? ['id' => (string) $session['idcronograma_origem'], 'name' => !empty($session['cronograma_nome']) ? (string) $session['cronograma_nome'] : null] : null,
        'planned_occurrence' => [
            'original_date' => !empty($session['data_ocorrencia_origem']) ? (string) $session['data_ocorrencia_origem'] : null,
            'date' => !empty($session['data_ocorrencia_planejada']) ? (string) $session['data_ocorrencia_planejada'] : null,
            'time' => !empty($session['hora_ocorrencia_planejada']) ? substr((string) $session['hora_ocorrencia_planejada'], 0, 5) : null,
            'timezone' => stridebr_api_workout_timezone(),
        ],
        'progress' => [
            'exercises_completed' => $doneExercises,
            'exercises_total' => count($exercises),
            'sets_completed' => $doneSets,
            'sets_total' => $totalSets,
        ],
        'exercises' => $exercises,
    ];
}

function stridebr_api_workout_session_current(PDO $pdo, string $userId, bool $includeHistory = true): ?array
{
    $session = sessaoCarregar($pdo, $userId, $includeHistory);
    return $session === [] ? null : stridebr_api_workout_session_payload($session);
}

function stridebr_api_workout_session_start(PDO $pdo, string $userId, string $workoutId): array
{
    $detail = stridebr_api_workout_detail($pdo, $userId, $workoutId);
    if ($detail === []) throw new InvalidArgumentException('Treino não encontrado.');
    if ((string) ($detail['status'] ?? '') !== 'publicado') throw new RuntimeException('Este treino não pode ser iniciado neste estado.');
    if (empty($detail['capabilities']['can_start_session'])) throw new RuntimeException('Este treino não possui estrutura executável para sessão.');
    $parsed = stridebr_api_workout_parse_id($workoutId);
    if ($parsed['kind'] === 'institutional') {
        $training = stridebr_teams_surface_training($pdo, $userId, (string) $parsed['ref']);
        if (!$training) throw new InvalidArgumentException('Treino não encontrado.');
        $session = sessaoIniciarInstitucional($pdo, $userId, $training);
    } else {
        $session = $parsed['kind'] === 'scheduled'
            ? sessaoIniciarAgendado($pdo, $userId, (string) $parsed['id'])
            : sessaoIniciarCronograma($pdo, $userId, (string) $parsed['id'], [
                'data_ocorrencia_origem' => $detail['original_date'] ?? $detail['date'],
                'data_ocorrencia_planejada' => $detail['date'],
                'hora_ocorrencia_planejada' => $detail['time'],
            ]);
    }
    return stridebr_api_workout_session_payload($session);
}

function stridebr_api_workout_session_set_update(PDO $pdo, string $userId, string $sessionId, string $setId, array $payload): array
{
    $result = sessaoAtualizarSerie(
        $pdo,
        $userId,
        $setId,
        $payload['repetitions'] ?? '',
        $payload['load'] ?? '',
        stridebr_api_bool($payload['propagate_load'] ?? false),
        trim((string) ($payload['edited_field'] ?? '')),
        [],
        $sessionId
    );
    return stridebr_api_workout_session_payload($result['session']);
}

function stridebr_api_workout_session_set_toggle(PDO $pdo, string $userId, string $sessionId, string $setId, array $payload): array
{
    return stridebr_api_workout_session_payload(sessaoAlternarSerie($pdo, $userId, $setId, stridebr_api_bool($payload['completed'] ?? false), $sessionId));
}

function stridebr_api_workout_session_exercise_toggle(PDO $pdo, string $userId, string $sessionId, string $exerciseId, array $payload): array
{
    return stridebr_api_workout_session_payload(sessaoAlternarExercicio($pdo, $userId, $exerciseId, stridebr_api_bool($payload['completed'] ?? false), $sessionId));
}

function stridebr_api_workout_session_mark_all(PDO $pdo, string $userId, string $sessionId, array $payload): array
{
    return stridebr_api_workout_session_payload(sessaoMarcarTudo($pdo, $userId, stridebr_api_bool($payload['completed'] ?? true), $sessionId));
}

function stridebr_api_workout_session_finish(PDO $pdo, string $userId, string $sessionId, array $payload): array
{
    $source = [
        'inicio_real' => trim((string) ($payload['started_at_local'] ?? '')),
        'fim_real' => trim((string) ($payload['ended_at_local'] ?? '')),
        'intensidade' => trim((string) ($payload['intensity'] ?? '')),
        'feeling' => $payload['feeling'] ?? '',
        'observacoes' => trim((string) ($payload['notes'] ?? '')),
    ];
    $before = sessaoCarregarPorId($pdo, $userId, $sessionId, false);
    $result = sessaoFinalizar($pdo, $userId, $sessionId, $source);
    if (($before['origem_externa'] ?? '') === 'teams') {
        $result['session_source'] = 'teams';
        $completedSnapshot = sessaoCarregarPorId($pdo, $userId, $sessionId, false);
        $result['execution_ack'] = stridebr_teams_execution_ack_payload($completedSnapshot !== [] ? $completedSnapshot : $before, 'completed', (string) $result['activity_id']);
    }
    return [
        'session_id' => $sessionId,
        'activity' => ['id' => (string) $result['activity_id']],
        'summary' => [
            'title' => (string) ($result['summary']['titulo'] ?? ''),
            'duration_s' => (int) ($result['summary']['duracao_segundos'] ?? 0),
            'exercises_completed' => (int) ($result['summary']['exercicios_concluidos'] ?? 0),
            'exercises_total' => (int) ($result['summary']['exercicios_total'] ?? 0),
            'sets_completed' => (int) ($result['summary']['series_concluidas'] ?? 0),
            'sets_total' => (int) ($result['summary']['series_total'] ?? 0),
            'intensity' => ($result['summary']['intensidade'] ?? '') !== '' ? (string) $result['summary']['intensidade'] : null,
            'feeling' => ($result['summary']['sensacao'] ?? null) !== null ? (int) $result['summary']['sensacao'] : null,
        ],
        'reused' => !empty($result['reused']),
        'execution_ack' => (($result['session_source'] ?? '') === 'teams') ? ($result['execution_ack'] ?? null) : null,
    ];
}

function stridebr_api_workout_session_cancel(PDO $pdo, string $userId, string $sessionId): array
{
    return stridebr_api_workout_session_payload(sessaoCancelar($pdo, $userId, $sessionId));
}

function stridebr_api_workout_quick_register(PDO $pdo, string $userId, string $workoutId, array $payload, string $idempotencyKey): array
{
    $detail = stridebr_api_workout_detail($pdo, $userId, $workoutId);
    if ($detail === []) throw new InvalidArgumentException('Treino não encontrado.');
    if (empty($detail['capabilities']['can_quick_register']) && empty($detail['activity']['id'])) throw new RuntimeException('Este treino não pode ser registrado rapidamente neste estado.');
    $scope = 'quick_register';
    $keyHash = hash('sha256', $idempotencyKey);
    $performedDate = trim((string) ($payload['performed_date'] ?? '')) ?: (string) ($detail['date'] ?? '');
    $startTime = trim((string) ($payload['start_time'] ?? '')) ?: trim((string) ($detail['time'] ?? ''));
    if ($startTime === '') throw new InvalidArgumentException('start_time é obrigatório quando o treino não possui horário planejado.');
    $normalized = [
        'workout_id' => $workoutId,
        'performed_date' => $performedDate,
        'start_time' => $startTime,
        'duration_min' => $payload['duration_min'] ?? null,
        'intensity' => trim((string) ($payload['intensity'] ?? '')),
        'feeling' => $payload['feeling'] ?? null,
        'notes' => trim((string) ($payload['notes'] ?? '')),
    ];
    $payloadHash = hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $owns = !$pdo->inTransaction();
    if ($owns) $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare('INSERT INTO api_workout_idempotencias (idusuario,escopo,chave_hash,payload_hash,recurso_id) VALUES (:user,:scope,:key,:payload,:resource) ON CONFLICT (idusuario,escopo,chave_hash) DO NOTHING');
        $insert->execute([':user' => $userId, ':scope' => $scope, ':key' => $keyHash, ':payload' => $payloadHash, ':resource' => $workoutId]);
        $lock = $pdo->prepare('SELECT payload_hash,recurso_id,idregistro FROM api_workout_idempotencias WHERE idusuario=:user AND escopo=:scope AND chave_hash=:key FOR UPDATE');
        $lock->execute([':user' => $userId, ':scope' => $scope, ':key' => $keyHash]);
        $stored = $lock->fetch();
        if (!$stored) throw new RuntimeException('Não foi possível reservar a operação idempotente.');
        if (!hash_equals((string) $stored['payload_hash'], $payloadHash) || (string) $stored['recurso_id'] !== $workoutId) throw new WorkoutSessionIdempotencyConflictException('Idempotency-Key já foi usada com outro payload.');
        if (!empty($stored['idregistro'])) {
            if ($owns) $pdo->commit();
            return ['activity' => ['id' => (string) $stored['idregistro']], 'workout' => stridebr_api_workout_detail($pdo, $userId, $workoutId), 'reused' => true];
        }
        $parsed = stridebr_api_workout_parse_id($workoutId);
        if ($parsed['kind'] === 'scheduled') {
            $domainLock = $pdo->prepare('SELECT idagendamento FROM treinos_agendados WHERE idagendamento=:id AND idatleta=:user FOR UPDATE');
            $domainLock->execute([':id' => $parsed['id'], ':user' => $userId]);
        } else {
            $domainLock = $pdo->prepare('SELECT tc.idtreino FROM treinos_cronograma tc JOIN cronogramas c ON c.idcronograma=tc.idcronograma WHERE tc.idtreino=:id AND c.idusuario=:user FOR UPDATE OF tc');
            $domainLock->execute([':id' => $parsed['id'], ':user' => $userId]);
        }
        if (!$domainLock->fetchColumn()) throw new RuntimeException('Treino não encontrado.');
        $detail = stridebr_api_workout_detail($pdo, $userId, $workoutId);
        if (!empty($detail['activity']['id'])) {
            $existingActivity = (string) $detail['activity']['id'];
            $update = $pdo->prepare('UPDATE api_workout_idempotencias SET idregistro=:activity WHERE idusuario=:user AND escopo=:scope AND chave_hash=:key');
            $update->execute([':activity' => $existingActivity, ':user' => $userId, ':scope' => $scope, ':key' => $keyHash]);
            if ($owns) $pdo->commit();
            return ['activity' => ['id' => $existingActivity], 'workout' => $detail, 'reused' => true];
        }
        if (empty($detail['capabilities']['can_quick_register'])) throw new RuntimeException('Este treino não pode ser registrado rapidamente neste estado.');
        $source = [
            'data' => $performedDate,
            'hora' => $startTime,
            'duracao_minutos' => $payload['duration_min'] ?? '',
            'intensidade' => trim((string) ($payload['intensity'] ?? '')),
            'feeling' => $payload['feeling'] ?? '',
            'observacoes' => trim((string) ($payload['notes'] ?? '')),
        ];
        if ($parsed['kind'] === 'recurring') {
            $source['data_ocorrencia_origem'] = $detail['original_date'] ?? $detail['date'];
            $source['data_ocorrencia_planejada'] = $detail['date'];
            $source['hora_ocorrencia_planejada'] = $detail['time'];
            $result = sessaoRegistroRapidoCronograma($pdo, $userId, (string) $parsed['id'], $source);
        } else {
            $result = sessaoRegistroRapidoAgendado($pdo, $userId, (string) $parsed['id'], $source);
        }
        $link = stridebr_api_workout_prepare_activity_link($pdo, $userId, $workoutId, (string) ($detail['sport']['id'] ?? ''));
        stridebr_api_workout_link_activity($pdo, $userId, (string) $result['activity_id'], $link);
        $update = $pdo->prepare('UPDATE api_workout_idempotencias SET idregistro=:activity WHERE idusuario=:user AND escopo=:scope AND chave_hash=:key');
        $update->execute([':activity' => $result['activity_id'], ':user' => $userId, ':scope' => $scope, ':key' => $keyHash]);
        if ($owns) $pdo->commit();
    } catch (Throwable $e) {
        if ($owns && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return ['activity' => ['id' => (string) $result['activity_id']], 'workout' => stridebr_api_workout_detail($pdo, $userId, $workoutId), 'reused' => false];
}
