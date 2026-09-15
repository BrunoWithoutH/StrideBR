<?php

declare(strict_types=1);

require_once __DIR__ . '/activity_stream_service.php';

function enduranceWorkoutParseDuration(?string $value): ?float
{
    $raw = strtolower(trim((string) $value));
    if ($raw === '') return null;
    if (preg_match('/^(\d{1,3}):(\d{2})(?::(\d{2}))?$/', $raw, $m) === 1) {
        if (isset($m[3]) && $m[3] !== '') return (float) ((int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3]);
        return (float) ((int) $m[1] * 60 + (int) $m[2]);
    }
    if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(s|seg|segs|segundo|segundos)$/u', $raw, $m) === 1) return (float) str_replace(',', '.', $m[1]);
    if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(min|mins|minuto|minutos)$/u', $raw, $m) === 1) return (float) str_replace(',', '.', $m[1]) * 60.0;
    if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(h|hora|horas)$/u', $raw, $m) === 1) return (float) str_replace(',', '.', $m[1]) * 3600.0;
    return null;
}

function enduranceWorkoutParseDistance(?string $value): ?float
{
    $raw = strtolower(trim((string) $value));
    if ($raw === '') return null;
    if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(m|metro|metros)$/u', $raw, $m) === 1) return (float) str_replace(',', '.', $m[1]);
    if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(km|quilometro|quilometros|quilômetro|quilômetros)$/u', $raw, $m) === 1) return (float) str_replace(',', '.', $m[1]) * 1000.0;
    return null;
}

function enduranceWorkoutInterpolateAtMoving(array $samples, float $movingMs): ?array
{
    $previous = null;
    foreach ($samples as $sample) {
        $axis = is_numeric($sample['moving_ms'] ?? null) ? (float) $sample['moving_ms'] : (float) ($sample['elapsed_ms'] ?? 0);
        if ($axis === $movingMs) return $sample;
        if ($axis > $movingMs && $previous !== null) {
            $previousAxis = is_numeric($previous['moving_ms'] ?? null) ? (float) $previous['moving_ms'] : (float) ($previous['elapsed_ms'] ?? 0);
            $span = $axis - $previousAxis;
            if ($span <= 0) return $previous;
            $ratio = ($movingMs - $previousAxis) / $span;
            $result = $previous;
            foreach (['elapsed_ms','moving_ms','distance_m','speed_mps','heart_rate_bpm','altitude_m','grade_pct','cadence','power_w','temperature_c'] as $field) {
                if (is_numeric($previous[$field] ?? null) && is_numeric($sample[$field] ?? null)) $result[$field] = (float) $previous[$field] + ((float) $sample[$field] - (float) $previous[$field]) * $ratio;
            }
            return $result;
        }
        $previous = $sample;
    }
    return null;
}

function enduranceWorkoutStepTarget(array $row): ?array
{
    $type = trim((string) ($row['alvo_tipo'] ?? ''));
    if ($type === '') return null;
    return [
        'type' => $type,
        'min' => is_numeric($row['alvo_min'] ?? null) ? (float) $row['alvo_min'] : null,
        'max' => is_numeric($row['alvo_max'] ?? null) ? (float) $row['alvo_max'] : null,
        'unit' => trim((string) ($row['alvo_unidade'] ?? '')) ?: null,
    ];
}

function enduranceWorkoutActualTargetValue(?array $target, array $metrics): ?float
{
    if ($target === null) return null;
    return match ((string) $target['type']) {
        'pace' => is_numeric($metrics['pace_s_per_km'] ?? null) ? (float) $metrics['pace_s_per_km'] : null,
        'speed' => is_numeric($metrics['speed_kmh'] ?? null) ? (float) $metrics['speed_kmh'] : null,
        'heart_rate' => is_numeric($metrics['heart_rate_avg_bpm'] ?? null) ? (float) $metrics['heart_rate_avg_bpm'] : null,
        'duration' => is_numeric($metrics['moving_duration_s'] ?? null) ? (float) $metrics['moving_duration_s'] : null,
        'distance' => is_numeric($metrics['distance_m'] ?? null) ? (float) $metrics['distance_m'] : null,
        default => null,
    };
}

function enduranceWorkoutTargetComparison(?array $target, ?float $actual): ?array
{
    if ($target === null || $actual === null) return null;
    $min = $target['min'];
    $max = $target['max'];
    if ($min === null && $max === null) return ['actual' => $actual, 'within_target' => null, 'delta_from_mid' => null];
    $lower = $min ?? $max;
    $upper = $max ?? $min;
    $mid = ($lower + $upper) / 2.0;
    return ['actual' => $actual, 'within_target' => $actual >= $lower && $actual <= $upper, 'delta_from_mid' => $actual - $mid];
}

function enduranceWorkoutRowsForActivity(PDO $pdo, string $userId, string $activityId): array
{
    $stmt = $pdo->prepare("SELECT ra.idtreino_cronograma,
        (SELECT st.idagendamento_origem FROM sessoes_treino st WHERE st.idusuario=:session_user AND st.idregistro_atividade=ra.idregistro AND st.idagendamento_origem IS NOT NULL ORDER BY st.data_criacao DESC LIMIT 1) AS idagendamento_origem
        FROM registros_atividade ra WHERE ra.idregistro=:activity AND ra.idusuario=:user AND ra.excluido_em IS NULL LIMIT 1");
    $stmt->execute([':session_user' => $userId, ':activity' => $activityId, ':user' => $userId]);
    $link = $stmt->fetch();
    if (!$link) throw new RuntimeException('Activity não encontrada.');
    if (!empty($link['idagendamento_origem'])) {
        $rows = $pdo->prepare('SELECT * FROM treinos_agendados_exercicios WHERE idagendamento=:id ORDER BY ordem');
        $rows->execute([':id' => $link['idagendamento_origem']]);
        return ['kind' => 'scheduled', 'id' => (string) $link['idagendamento_origem'], 'rows' => $rows->fetchAll()];
    }
    if (!empty($link['idtreino_cronograma'])) {
        $owner = $pdo->prepare('SELECT idtreino FROM treinos_cronograma tc JOIN cronogramas c ON c.idcronograma=tc.idcronograma WHERE tc.idtreino=:id AND c.idusuario=:user LIMIT 1');
        $owner->execute([':id' => $link['idtreino_cronograma'], ':user' => $userId]);
        if (!$owner->fetchColumn()) return ['kind' => null, 'id' => null, 'rows' => []];
        $rows = $pdo->prepare('SELECT * FROM treinos_exercicios WHERE idtreino=:id ORDER BY ordem');
        $rows->execute([':id' => $link['idtreino_cronograma']]);
        return ['kind' => 'recurring', 'id' => (string) $link['idtreino_cronograma'], 'rows' => $rows->fetchAll()];
    }
    return ['kind' => null, 'id' => null, 'rows' => []];
}

function enduranceWorkoutExpandRows(array $rows): array
{
    $expanded = [];
    $sequence = 1;
    foreach ($rows as $row) {
        $type = trim((string) ($row['tipo_passo'] ?? 'exercise')) ?: 'exercise';
        if ($type === 'exercise') continue;
        $repeat = is_numeric($row['repeticoes_bloco'] ?? null) ? max(1, min(99, (int) $row['repeticoes_bloco'])) : 1;
        $duration = enduranceWorkoutParseDuration(isset($row['duracao']) ? (string) $row['duracao'] : null);
        $distance = enduranceWorkoutParseDistance(isset($row['distancia']) ? (string) $row['distancia'] : null);
        $target = enduranceWorkoutStepTarget($row);
        for ($iteration = 1; $iteration <= $repeat; $iteration++) {
            $expanded[] = [
                'sequence' => $sequence++, 'source_order' => (int) ($row['ordem'] ?? 0), 'repeat_index' => $iteration, 'repeat_count' => $repeat,
                'type' => $type, 'name' => trim((string) ($row['nome_snapshot'] ?? '')) ?: cronogramaPassoNomePadrao($type),
                'planned_duration_s' => $duration, 'planned_distance_m' => $distance, 'target' => $target,
            ];
            $recoveryDuration = is_numeric($row['recuperacao_duracao_s'] ?? null) ? (float) $row['recuperacao_duracao_s'] : null;
            $recoveryDistance = is_numeric($row['recuperacao_distancia_m'] ?? null) ? (float) $row['recuperacao_distancia_m'] : null;
            if (($recoveryDuration !== null && $recoveryDuration > 0) || ($recoveryDistance !== null && $recoveryDistance > 0)) {
                $expanded[] = [
                    'sequence' => $sequence++, 'source_order' => (int) ($row['ordem'] ?? 0), 'repeat_index' => $iteration, 'repeat_count' => $repeat,
                    'type' => 'recovery', 'name' => 'Recuperação', 'planned_duration_s' => $recoveryDuration, 'planned_distance_m' => $recoveryDistance, 'target' => null,
                ];
            }
        }
    }
    return $expanded;
}

function enduranceWorkoutCompareActivity(PDO $pdo, string $userId, string $activityId): array
{
    $workout = enduranceWorkoutRowsForActivity($pdo, $userId, $activityId);
    $steps = enduranceWorkoutExpandRows((array) $workout['rows']);
    if ($steps === []) return ['activity_id' => $activityId, 'workout' => ['kind' => $workout['kind'], 'id' => $workout['id']], 'steps' => [], 'comparable_steps' => 0];
    activityStreamEnsureMaterialized($pdo, $userId, $activityId);
    $stored = activityStreamRows($pdo, $userId, $activityId);
    $samples = array_values(array_filter($stored['samples'], static fn(array $sample): bool => is_numeric($sample['elapsed_ms'] ?? null)));
    if ($samples === []) return ['activity_id' => $activityId, 'workout' => ['kind' => $workout['kind'], 'id' => $workout['id']], 'steps' => array_map(static fn(array $step): array => $step + ['actual' => null, 'target_comparison' => null], $steps), 'comparable_steps' => 0];
    $cursor = $samples[0];
    $result = [];
    $comparable = 0;
    foreach ($steps as $step) {
        $metrics = [];
        $end = null;
        $startDistance = is_numeric($cursor['distance_m'] ?? null) ? (float) $cursor['distance_m'] : null;
        if ($step['planned_distance_m'] !== null && $step['planned_distance_m'] > 0 && $startDistance !== null) {
            $endDistance = $startDistance + (float) $step['planned_distance_m'];
            $end = activityStreamInterpolateAtDistance($samples, $endDistance);
            if ($end !== null) $metrics = activityStreamSegmentMetrics($samples, $startDistance, $endDistance);
        } elseif ($step['planned_duration_s'] !== null && $step['planned_duration_s'] > 0) {
            $startMoving = is_numeric($cursor['moving_ms'] ?? null) ? (float) $cursor['moving_ms'] : (float) $cursor['elapsed_ms'];
            $end = enduranceWorkoutInterpolateAtMoving($samples, $startMoving + (float) $step['planned_duration_s'] * 1000.0);
            if ($end !== null && $startDistance !== null && is_numeric($end['distance_m'] ?? null) && (float) $end['distance_m'] > $startDistance) $metrics = activityStreamSegmentMetrics($samples, $startDistance, (float) $end['distance_m']);
        }
        if ($end !== null && $metrics !== []) {
            $cursor = $end;
            $comparable++;
        }
        $targetActual = enduranceWorkoutActualTargetValue($step['target'], $metrics);
        $result[] = $step + [
            'actual' => $metrics === [] ? null : [
                'distance_m' => $metrics['distance_m'] ?? null,
                'elapsed_duration_s' => $metrics['elapsed_duration_s'] ?? null,
                'moving_duration_s' => $metrics['moving_duration_s'] ?? null,
                'pace_s_per_km' => $metrics['pace_s_per_km'] ?? null,
                'speed_kmh' => $metrics['speed_kmh'] ?? null,
                'heart_rate_avg_bpm' => $metrics['heart_rate_avg_bpm'] ?? null,
                'heart_rate_max_bpm' => $metrics['heart_rate_max_bpm'] ?? null,
                'cadence_avg' => $metrics['cadence_avg'] ?? null,
                'power_avg_w' => $metrics['power_avg_w'] ?? null,
            ],
            'target_comparison' => enduranceWorkoutTargetComparison($step['target'], $targetActual),
        ];
    }
    return ['activity_id' => $activityId, 'workout' => ['kind' => $workout['kind'], 'id' => $workout['id']], 'steps' => $result, 'comparable_steps' => $comparable];
}
