<?php

declare(strict_types=1);

require_once __DIR__ . '/benchmarks.php';

function benchmarkGoalTypes(): array
{
    return ['one_rm', 'ftp', 'css', 'distance_time'];
}

function benchmarkGoalTypeConfig(string $type): ?array
{
    if (!in_array($type, benchmarkGoalTypes(), true)) return null;
    return benchmarkTypeConfig($type);
}

function benchmarkGoalParseTarget(string $type, mixed $value): ?float
{
    return in_array($type, ['css', 'distance_time'], true)
        ? benchmarkParseClockSeconds($value)
        : benchmarkParseDecimal($value);
}

function benchmarkGoalSatisfies(string $type, float $value, float $target): bool
{
    $config = benchmarkGoalTypeConfig($type);
    if ($config === null) return false;
    return ($config['direction'] ?? 'higher') === 'lower' ? $value <= $target : $value >= $target;
}

function benchmarkGoalBetter(string $type, float $candidate, float $reference): bool
{
    $config = benchmarkGoalTypeConfig($type);
    if ($config === null) return false;
    return ($config['direction'] ?? 'higher') === 'lower' ? $candidate < $reference : $candidate > $reference;
}

function benchmarkGoalReferenceWhere(array $shape, string $userId, array &$params): array
{
    $where = [
        'b.idusuario = :goal_user',
        'b.excluido_progresso = FALSE',
        'b.tipo = :goal_type',
        'b.idmodalidade = :goal_modality',
    ];
    $params[':goal_user'] = $userId;
    $params[':goal_type'] = (string) $shape['benchmark_tipo'];
    $params[':goal_modality'] = (string) $shape['idmodalidade'];
    if ((string) $shape['benchmark_tipo'] === 'one_rm') {
        $exerciseId = trim((string) ($shape['idexercicio'] ?? ''));
        $snapshot = trim((string) ($shape['benchmark_referencia_nome_snapshot'] ?? ''));
        if ($exerciseId !== '') {
            $where[] = 'b.idexercicio = :goal_exercise';
            $params[':goal_exercise'] = $exerciseId;
        } elseif ($snapshot !== '') {
            $where[] = 'lower(trim(COALESCE(b.referencia_nome_snapshot, e.nome, \'\'))) = lower(trim(:goal_snapshot))';
            $params[':goal_snapshot'] = $snapshot;
        } else {
            $where[] = 'FALSE';
        }
    }
    if ((string) $shape['benchmark_tipo'] === 'distance_time') {
        $where[] = 'ABS(b.distancia_m - :goal_distance) < 0.001';
        $params[':goal_distance'] = (float) $shape['benchmark_distancia_m'];
    }
    return $where;
}

function benchmarkGoalReferenceBefore(PDO $pdo, string $userId, array $shape, string $date, bool $best): ?array
{
    $params = [];
    $where = benchmarkGoalReferenceWhere($shape, $userId, $params);
    $where[] = 'b.data_resultado <= :goal_date';
    $params[':goal_date'] = $date;
    $config = benchmarkGoalTypeConfig((string) $shape['benchmark_tipo']);
    if ($config === null) return null;
    if ($best) {
        $order = ($config['direction'] ?? 'higher') === 'lower'
            ? 'b.valor_canonico ASC, b.data_resultado DESC, b.data_criacao DESC'
            : 'b.valor_canonico DESC, b.data_resultado DESC, b.data_criacao DESC';
    } else {
        $order = ($config['primary'] ?? 'best') === 'latest'
            ? 'b.data_resultado DESC, b.data_criacao DESC'
            : (($config['direction'] ?? 'higher') === 'lower'
                ? 'b.valor_canonico ASC, b.data_resultado DESC, b.data_criacao DESC'
                : 'b.valor_canonico DESC, b.data_resultado DESC, b.data_criacao DESC');
    }
    $sql = 'SELECT b.*, e.nome AS exercicio_nome FROM benchmarks_usuario b LEFT JOIN exercicios e ON e.idexercicio=b.idexercicio WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order . ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function benchmarkGoalEligibleRows(PDO $pdo, string $userId, array $goal): array
{
    $params = [];
    $where = benchmarkGoalReferenceWhere($goal, $userId, $params);
    $where[] = 'b.data_resultado >= :goal_start';
    $params[':goal_start'] = (string) $goal['data_inicio'];
    if (!empty($goal['data_fim'])) {
        $where[] = 'b.data_resultado <= :goal_end';
        $params[':goal_end'] = (string) $goal['data_fim'];
    }
    $sql = 'SELECT b.*, e.nome AS exercicio_nome FROM benchmarks_usuario b LEFT JOIN exercicios e ON e.idexercicio=b.idexercicio WHERE ' . implode(' AND ', $where) . ' ORDER BY b.data_resultado ASC, b.data_criacao ASC, b.idbenchmark ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function benchmarkGoalBestRow(string $type, array $rows): ?array
{
    $best = null;
    foreach ($rows as $row) {
        if (!is_numeric($row['valor_canonico'] ?? null)) continue;
        if ($best === null || benchmarkGoalBetter($type, (float) $row['valor_canonico'], (float) $best['valor_canonico'])) $best = $row;
    }
    return $best;
}

function benchmarkGoalFirstSatisfying(string $type, array $rows, float $target): ?array
{
    foreach ($rows as $row) {
        if (is_numeric($row['valor_canonico'] ?? null) && benchmarkGoalSatisfies($type, (float) $row['valor_canonico'], $target)) return $row;
    }
    return null;
}

function benchmarkGoalProgressPercent(string $type, ?float $baseline, ?float $best, float $target): ?float
{
    if ($baseline === null || $best === null) return null;
    $config = benchmarkGoalTypeConfig($type);
    if ($config === null) return null;
    if (($config['direction'] ?? 'higher') === 'lower') {
        $denominator = $baseline - $target;
        if ($denominator <= 0) return null;
        return min(100.0, max(0.0, (($baseline - $best) / $denominator) * 100));
    }
    $denominator = $target - $baseline;
    if ($denominator <= 0) return null;
    return min(100.0, max(0.0, (($best - $baseline) / $denominator) * 100));
}

function benchmarkGoalRemaining(string $type, ?float $best, float $target): ?float
{
    if ($best === null) return null;
    $config = benchmarkGoalTypeConfig($type);
    if ($config === null) return null;
    return ($config['direction'] ?? 'higher') === 'lower' ? max(0.0, $best - $target) : max(0.0, $target - $best);
}

function benchmarkGoalSyncConclusion(PDO $pdo, string $userId, array $goal, ?array $evidence): void
{
    $goalId = trim((string) ($goal['idmeta'] ?? ''));
    if ($goalId === '') return;
    if ($evidence === null) {
        $pdo->prepare("DELETE FROM metas_conclusoes WHERE idmeta=:meta AND EXISTS (SELECT 1 FROM metas_usuario g WHERE g.idmeta=:meta AND g.idusuario=:usuario AND g.tipo_meta='benchmark')")
            ->execute([':meta' => $goalId, ':usuario' => $userId]);
        $pdo->prepare("UPDATE metas_usuario SET concluida_em=NULL,data_atualizacao=NOW() WHERE idmeta=:meta AND idusuario=:usuario AND tipo_meta='benchmark' AND concluida_em IS NOT NULL")
            ->execute([':meta' => $goalId, ':usuario' => $userId]);
        return;
    }
    $start = (string) $goal['data_inicio'];
    $periodEnd = !empty($goal['data_fim']) ? (string) $goal['data_fim'] : $start;
    $pdo->prepare("UPDATE metas_usuario SET concluida_em=COALESCE(concluida_em,NOW()),data_atualizacao=NOW() WHERE idmeta=:meta AND idusuario=:usuario AND tipo_meta='benchmark'")
        ->execute([':meta' => $goalId, ':usuario' => $userId]);
    $stmt = $pdo->prepare(
        'INSERT INTO metas_conclusoes (idconclusao,idmeta,periodo_inicio,periodo_fim,valor_atingido,idbenchmark,data_resultado) VALUES (:id,:meta,:inicio,:fim,:valor,:benchmark,:data) '
        . 'ON CONFLICT (idmeta,periodo_inicio,periodo_fim) DO UPDATE SET valor_atingido=EXCLUDED.valor_atingido,idbenchmark=EXCLUDED.idbenchmark,data_resultado=EXCLUDED.data_resultado'
    );
    $stmt->execute([
        ':id' => stridebr_generate_id(),
        ':meta' => $goalId,
        ':inicio' => $start,
        ':fim' => $periodEnd,
        ':valor' => (float) $evidence['valor_canonico'],
        ':benchmark' => (string) $evidence['idbenchmark'],
        ':data' => (string) $evidence['data_resultado'],
    ]);
}

function benchmarkGoalEvaluate(PDO $pdo, string $userId, array $goal, bool $sync = true): array
{
    $type = (string) ($goal['benchmark_tipo'] ?? '');
    $target = (float) ($goal['valor_alvo'] ?? 0);
    $rows = benchmarkGoalEligibleRows($pdo, $userId, $goal);
    $best = benchmarkGoalBestRow($type, $rows);
    $evidence = benchmarkGoalFirstSatisfying($type, $rows, $target);
    $persistedBaseline = is_numeric($goal['valor_inicial'] ?? null) ? (float) $goal['valor_inicial'] : null;
    $dynamicBaseline = null;
    if ($persistedBaseline === null && $rows !== [] && is_numeric($rows[0]['valor_canonico'] ?? null)) $dynamicBaseline = (float) $rows[0]['valor_canonico'];
    $baseline = $persistedBaseline ?? $dynamicBaseline;
    $bestValue = is_array($best) ? (float) $best['valor_canonico'] : null;
    $achieved = is_array($evidence);
    $percent = $achieved ? 100.0 : benchmarkGoalProgressPercent($type, $baseline, $bestValue, $target);
    if ($persistedBaseline === null && count($rows) < 2 && !$achieved) $percent = null;
    $remaining = benchmarkGoalRemaining($type, $bestValue, $target);
    $today = new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'));
    $expired = !$achieved && !empty($goal['data_fim']) && new DateTimeImmutable((string) $goal['data_fim']) < $today;
    if ($sync) benchmarkGoalSyncConclusion($pdo, $userId, $goal, $evidence);
    return [
        'progresso' => $bestValue ?? 0.0,
        'percentual' => $percent ?? 0.0,
        'percentual_real' => $percent ?? 0.0,
        'percentual_disponivel' => $percent !== null,
        'restante' => $remaining ?? 0.0,
        'restante_disponivel' => $remaining !== null,
        'atingida' => $achieved,
        'expirada' => $expired,
        'periodo_inicio' => new DateTimeImmutable((string) $goal['data_inicio'] . ' 00:00:00', new DateTimeZone('America/Sao_Paulo')),
        'periodo_fim' => new DateTimeImmutable((string) ($goal['data_fim'] ?: $today->format('Y-m-d')) . ' 23:59:59', new DateTimeZone('America/Sao_Paulo')),
        'benchmark_best' => $best,
        'benchmark_evidence' => $evidence,
        'benchmark_baseline_value' => $baseline,
        'benchmark_baseline_persisted' => $persistedBaseline !== null,
        'benchmark_dynamic_baseline' => $persistedBaseline === null ? ($rows[0] ?? null) : null,
        'benchmark_rows' => $rows,
    ];
}

function benchmarkGoalNormalizeInput(PDO $pdo, string $userId, array $payload, ?array $existing = null): array
{
    $isEdit = is_array($existing);
    $today = (new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
    $name = trim((string) ($payload['nome'] ?? $existing['nome'] ?? '')) ?: null;
    if ($name !== null && stridebr_length($name) > 80) throw new InvalidArgumentException(stridebr_t('goals.error.name_too_long'));

    if ($isEdit && !empty($existing['concluida_em'])) {
        $incomingTarget = benchmarkGoalParseTarget((string) $existing['benchmark_tipo'], $payload['valor_alvo'] ?? $existing['valor_alvo']);
        $incomingEnd = dashboardNormalizarDataMeta($payload['data_fim'] ?? $existing['data_fim'] ?? null);
        if ($incomingTarget === null || abs($incomingTarget - (float) $existing['valor_alvo']) > 0.000001 || $incomingEnd !== ($existing['data_fim'] ?? null)) throw new InvalidArgumentException(stridebr_t('goals.error.benchmark_completed_locked'));
        return array_merge($existing, ['nome' => $name]);
    }

    $type = $isEdit ? (string) $existing['benchmark_tipo'] : stridebr_lower(trim((string) ($payload['benchmark_tipo'] ?? '')));
    if ($isEdit && isset($payload['benchmark_tipo']) && trim((string) $payload['benchmark_tipo']) !== '' && stridebr_lower(trim((string) $payload['benchmark_tipo'])) !== $type) throw new InvalidArgumentException(stridebr_t('goals.error.benchmark_identity_locked'));
    $config = benchmarkGoalTypeConfig($type);
    if ($config === null) throw new InvalidArgumentException(stridebr_t('goals.error.invalid_benchmark_type'));
    $modalityId = $isEdit ? (string) $existing['idmodalidade'] : trim((string) ($payload['idmodalidade'] ?? ''));
    if ($isEdit && isset($payload['idmodalidade']) && trim((string) $payload['idmodalidade']) !== '' && trim((string) $payload['idmodalidade']) !== $modalityId) throw new InvalidArgumentException(stridebr_t('goals.error.benchmark_identity_locked'));
    $modality = $modalityId !== '' ? benchmarkModalityRow($pdo, $userId, $modalityId) : null;
    if ($modality === null || !benchmarkTypeSupportsModality($config, $modality)) throw new InvalidArgumentException(stridebr_t('goals.error.invalid_benchmark_sport'));

    $exerciseId = $isEdit ? trim((string) ($existing['idexercicio'] ?? '')) : trim((string) ($payload['idexercicio'] ?? ''));
    if ($isEdit && isset($payload['idexercicio']) && trim((string) $payload['idexercicio']) !== '' && trim((string) $payload['idexercicio']) !== $exerciseId) throw new InvalidArgumentException(stridebr_t('goals.error.benchmark_identity_locked'));
    $snapshot = $isEdit ? trim((string) ($existing['benchmark_referencia_nome_snapshot'] ?? '')) : '';
    if (!empty($config['requires_exercise'])) {
        if ($exerciseId === '') {
            if (!$isEdit || $snapshot === '') throw new InvalidArgumentException(stridebr_t('goals.error.choose_exercise'));
        } else {
            $exercise = benchmarkExerciseRow($pdo, $userId, $exerciseId);
            if ($exercise === null || (!$isEdit && !stridebr_db_bool($exercise['ativo'] ?? false))) throw new InvalidArgumentException(stridebr_t('goals.error.invalid_exercise'));
            if ($snapshot === '') $snapshot = trim((string) $exercise['nome']);
        }
    } else {
        $exerciseId = '';
        $snapshot = '';
    }

    $distanceRaw = $payload['benchmark_distancia_m'] ?? null;
    if ((string) $distanceRaw === 'custom') $distanceRaw = $payload['benchmark_distancia_custom_m'] ?? null;
    $distance = $isEdit && is_numeric($existing['benchmark_distancia_m'] ?? null)
        ? (float) $existing['benchmark_distancia_m']
        : benchmarkParseDecimal($distanceRaw);
    if ($isEdit && isset($payload['benchmark_distancia_m']) && (string) $payload['benchmark_distancia_m'] !== '') {
        $incomingDistanceRaw = $payload['benchmark_distancia_m'];
        if ((string) $incomingDistanceRaw === 'custom') $incomingDistanceRaw = $payload['benchmark_distancia_custom_m'] ?? null;
        $incomingDistance = benchmarkParseDecimal($incomingDistanceRaw);
        if ($incomingDistance !== null && is_numeric($existing['benchmark_distancia_m'] ?? null) && abs($incomingDistance - (float) $existing['benchmark_distancia_m']) >= 0.001) throw new InvalidArgumentException(stridebr_t('goals.error.benchmark_identity_locked'));
    }
    if (!empty($config['requires_distance']) && ($distance === null || $distance <= 0 || $distance > 1000000)) throw new InvalidArgumentException(stridebr_t('goals.error.benchmark_distance_required'));
    if (empty($config['requires_distance'])) $distance = null;

    $target = benchmarkGoalParseTarget($type, $payload['valor_alvo'] ?? null);
    if ($target === null || $target <= 0 || $target > 100000000) throw new InvalidArgumentException(stridebr_t('goals.error.target_positive'));

    $postedPeriod = trim((string) ($payload['periodo'] ?? ''));
    if ($postedPeriod !== '' && !in_array($postedPeriod, ['continuo', 'personalizado'], true)) throw new InvalidArgumentException(stridebr_t('goals.error.benchmark_recurring_period'));
    $deadline = dashboardNormalizarDataMeta($payload['data_fim'] ?? null);
    $start = $isEdit ? (string) $existing['data_inicio'] : $today;
    if ($deadline !== null && $deadline < $start) throw new InvalidArgumentException(stridebr_t('goals.error.end_before_start'));
    if ($deadline !== null && (new DateTimeImmutable($start))->diff(new DateTimeImmutable($deadline))->days > 3660) throw new InvalidArgumentException(stridebr_t('goals.error.max_ten_years'));
    $period = $deadline !== null ? 'personalizado' : 'continuo';

    $shape = [
        'benchmark_tipo' => $type,
        'idmodalidade' => $modalityId,
        'idexercicio' => $exerciseId !== '' ? $exerciseId : null,
        'benchmark_referencia_nome_snapshot' => $snapshot !== '' ? $snapshot : null,
        'benchmark_distancia_m' => $distance,
    ];

    $baseline = $isEdit ? null : benchmarkGoalReferenceBefore($pdo, $userId, $shape, $start, false);
    $bestKnown = benchmarkGoalReferenceBefore($pdo, $userId, $shape, $start, true);
    if (!$isEdit && is_array($bestKnown) && benchmarkGoalSatisfies($type, (float) $bestKnown['valor_canonico'], $target)) throw new InvalidArgumentException(stridebr_t('goals.error.benchmark_already_achieved'));
    if ($isEdit && is_numeric($existing['valor_inicial'] ?? null) && benchmarkGoalSatisfies($type, (float) $existing['valor_inicial'], $target)) throw new InvalidArgumentException(stridebr_t('goals.error.benchmark_already_achieved'));

    return [
        'tipo_meta' => 'benchmark',
        'metrica' => null,
        'periodo' => $period,
        'idmodalidade' => $modalityId,
        'idexercicio' => $exerciseId !== '' ? $exerciseId : null,
        'nome' => $name,
        'valor_alvo' => $target,
        'data_inicio' => $start,
        'data_fim' => $deadline,
        'benchmark_tipo' => $type,
        'benchmark_distancia_m' => $distance,
        'benchmark_referencia_nome_snapshot' => $snapshot !== '' ? $snapshot : null,
        'valor_inicial' => $isEdit ? ($existing['valor_inicial'] ?? null) : (is_array($baseline) ? (float) $baseline['valor_canonico'] : null),
        'data_valor_inicial' => $isEdit ? ($existing['data_valor_inicial'] ?? null) : (is_array($baseline) ? (string) $baseline['data_resultado'] : null),
        'idbenchmark_inicial' => $isEdit ? ($existing['idbenchmark_inicial'] ?? null) : (is_array($baseline) ? (string) $baseline['idbenchmark'] : null),
    ];
}

function benchmarkGoalSyncForUser(PDO $pdo, string $userId): void
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM metas_usuario WHERE idusuario=:usuario AND tipo_meta='benchmark'");
        $stmt->execute([':usuario' => $userId]);
        foreach ($stmt->fetchAll() as $goal) benchmarkGoalEvaluate($pdo, $userId, $goal, true);
    } catch (PDOException $e) {
        if ($e->getCode() !== '42703' && $e->getCode() !== '42P01') throw $e;
    }
}

function benchmarkGoalTitle(array $goal): string
{
    $name = trim((string) ($goal['nome'] ?? ''));
    if ($name !== '') return $name;
    $type = (string) ($goal['benchmark_tipo'] ?? '');
    if ($type === 'one_rm') {
        $exercise = trim((string) ($goal['exercicio_nome'] ?? $goal['benchmark_referencia_nome_snapshot'] ?? ''));
        return trim($exercise . ' · ' . stridebr_t('goals.benchmark.one_rm_measured'), ' ·');
    }
    if ($type === 'distance_time') {
        $distance = is_numeric($goal['benchmark_distancia_m'] ?? null) ? benchmarkFormatDistance((float) $goal['benchmark_distancia_m']) : '';
        return stridebr_t('goals.benchmark.distance_target_title', ['distance' => $distance, 'target' => benchmarkFormatValue('distance_time', (float) $goal['valor_alvo'])]);
    }
    return stridebr_t('benchmarks.' . $type);
}

function benchmarkGoalResultHref(array $goal): string
{
    $slug = trim((string) ($goal['modalidade_slug'] ?? ''));
    $type = trim((string) ($goal['benchmark_tipo'] ?? ''));
    $params = ['sport' => $slug, 'new_benchmark' => $type];
    if ($type === 'one_rm' && !empty($goal['idexercicio'])) $params['exercise'] = (string) $goal['idexercicio'];
    if ($type === 'distance_time' && is_numeric($goal['benchmark_distancia_m'] ?? null)) $params['distance_m'] = (string) (float) $goal['benchmark_distancia_m'];
    return '/user/progresso.php?' . http_build_query($params);
}
