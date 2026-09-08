<?php

declare(strict_types=1);

require_once __DIR__ . '/cronograma.php';

/** Derived facts only. The entire planned day remains available for completion. */
function planejamentoEstado(array $item, ?string $today = null): string
{
    if (in_array($item['status'] ?? '', ['cancelado', 'cancelled'], true)) return 'cancelled';
    if (!empty($item['concluido']) || ($item['status'] ?? '') === 'concluido') {
        return !empty($item['realizado_fora_planejado']) ? 'shifted' : 'completed';
    }
    $today ??= (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
    return (string) ($item['data_planejada'] ?? $item['data_treino'] ?? $today) < $today ? 'missed' : 'todo';
}

function planejamentoResumir(array $items, array $activities, string $start, string $end, ?string $today = null): array
{
    $result = ['planned' => 0, 'completed' => 0, 'missed' => 0, 'todo' => 0, 'items' => [], 'extra' => []];
    $linked = [];
    foreach ($items as $item) {
        if (!empty($item['idregistro'])) $linked[(string) $item['idregistro']] = true;
        $date = (string) ($item['data_planejada'] ?? $item['data_treino']);
        if ($date < $start || $date > $end) continue;
        $state = planejamentoEstado($item, $today);
        $item['planning_state'] = $state;
        $result['items'][] = $item;
        if ($state === 'cancelled') continue;
        $result['planned']++;
        $result[in_array($state, ['completed', 'shifted'], true) ? 'completed' : $state]++;
    }
    foreach ($activities as $activity) {
        $date = substr((string) $activity['data_inicio'], 0, 10);
        if ($date < $start || $date > $end || isset($linked[(string) $activity['idregistro']])) continue;
        // Explicit origin outside this week is still planned, never an extra activity.
        if (!empty($activity['idtreino_cronograma']) || !empty($activity['idagendamento_origem'])) continue;
        $result['extra'][] = $activity;
    }
    usort($result['items'], static fn($a, $b) => [$a['data_planejada'] ?? $a['data_treino'], $a['hora_inicio'] ?? ''] <=> [$b['data_planejada'] ?? $b['data_treino'], $b['hora_inicio'] ?? '']);
    return $result;
}

/** Caller must authorize schedule AND activity access before calling. No persistent cache. */
function planejamentoSemana(PDO $pdo, string $userId, string $start, ?string $scheduleId = null): array
{
    $from = cronogramaValidarDataIso($start);
    $end = $from->modify('+6 days')->format('Y-m-d');
    $items = cronogramaListarOcorrencias($pdo, $userId, $start, $end, $scheduleId, true);
    $items = cronogramaConciliarOcorrenciasComRegistros($pdo, $userId, $items, $start, $end, $scheduleId);
    $stmt = $pdo->prepare("SELECT idregistro, titulo, data_inicio, idtreino_cronograma, (SELECT st.idagendamento_origem FROM sessoes_treino st WHERE st.idregistro_atividade = registros_atividade.idregistro AND st.idagendamento_origem IS NOT NULL LIMIT 1) AS idagendamento_origem FROM registros_atividade WHERE idusuario = :usuario AND excluido_em IS NULL AND status = 'concluido' AND data_inicio >= :inicio AND data_inicio < :fim ORDER BY data_inicio");
    $stmt->execute([':usuario' => $userId, ':inicio' => $start . ' 00:00:00', ':fim' => $from->modify('+7 days')->format('Y-m-d') . ' 00:00:00']);
    $activities = $stmt->fetchAll();
    if ($scheduleId === null) {
        $stmt = $pdo->prepare("SELECT ta.idagendamento, ta.titulo, ta.data_treino, ta.hora_inicio, ta.status, ra.idregistro, ra.data_inicio AS realizada FROM treinos_agendados ta LEFT JOIN LATERAL (SELECT r.idregistro, r.data_inicio FROM sessoes_treino st JOIN registros_atividade r ON r.idregistro = st.idregistro_atividade WHERE st.idagendamento_origem = ta.idagendamento AND st.idusuario = ta.idatleta AND r.idusuario = ta.idatleta AND r.status = 'concluido' AND r.excluido_em IS NULL ORDER BY r.data_inicio LIMIT 1) ra ON TRUE WHERE ta.idatleta = :usuario AND ta.data_treino BETWEEN :inicio AND :fim AND ta.status IN ('publicado','concluido','cancelado')");
        $stmt->execute([':usuario' => $userId, ':inicio' => $start, ':fim' => $end]);
        foreach ($stmt->fetchAll() as $appointment) {
            $appointment['realizado_fora_planejado'] = !empty($appointment['realizada']) && substr($appointment['realizada'], 0, 10) !== $appointment['data_treino'];
            $items[] = $appointment;
        }
    }
    return planejamentoResumir($items, $activities, $start, $end);
}
