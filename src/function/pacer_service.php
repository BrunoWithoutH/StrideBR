<?php

declare(strict_types=1);

const PACER_PLAN_RULES_VERSION = 1;

function pacerDefaultRules(): array
{
    return [
        'rules_version' => PACER_PLAN_RULES_VERSION,
        'persistence_s' => 15,
        'hysteresis_s_per_km' => 3.0,
        'cooldown_s' => 20,
        'heart_rate_persistence_s' => 15,
        'final_push' => ['enabled' => false, 'remaining_distance_m' => 1000.0, 'max_behind_s' => 0.0],
    ];
}

function pacerNormalizeRules(array $input): array
{
    $rules = array_replace_recursive(pacerDefaultRules(), $input);
    foreach (['persistence_s' => [1,120], 'cooldown_s' => [0,300], 'heart_rate_persistence_s' => [1,120]] as $key => [$min,$max]) {
        $value = filter_var($rules[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value < $min || $value > $max) throw new InvalidArgumentException($key . ' está fora do intervalo aceito.');
        $rules[$key] = (int) $value;
    }
    $hysteresis = activityStreamFinite($rules['hysteresis_s_per_km'] ?? null);
    if ($hysteresis === null || $hysteresis < 0 || $hysteresis > 120) throw new InvalidArgumentException('hysteresis_s_per_km está fora do intervalo aceito.');
    $rules['hysteresis_s_per_km'] = $hysteresis;
    $finalPush = is_array($rules['final_push'] ?? null) ? $rules['final_push'] : [];
    $remaining = activityStreamFinite($finalPush['remaining_distance_m'] ?? 1000);
    $behind = activityStreamFinite($finalPush['max_behind_s'] ?? 0);
    if ($remaining === null || $remaining <= 0 || $remaining > 10000) throw new InvalidArgumentException('final_push.remaining_distance_m é inválido.');
    if ($behind === null || $behind < -3600 || $behind > 3600) throw new InvalidArgumentException('final_push.max_behind_s é inválido.');
    $rules['final_push'] = ['enabled' => !empty($finalPush['enabled']), 'remaining_distance_m' => $remaining, 'max_behind_s' => $behind];
    $rules['rules_version'] = PACER_PLAN_RULES_VERSION;
    return $rules;
}

function pacerResolveSport(PDO $pdo, string $userId, string $sport): array
{
    $sport = trim($sport);
    if ($sport === '') throw new InvalidArgumentException('sport é obrigatório.');
    $stmt = $pdo->prepare("SELECT idmodalidade,slug,nome,familia_hub,metrica_derivada,permite_rota FROM modalidades WHERE ativo=TRUE AND (idmodalidade=:sport OR slug=:sport) AND (idusuario IS NULL OR idusuario=:user) LIMIT 1");
    $stmt->execute([':sport' => $sport, ':user' => $userId]);
    $row = $stmt->fetch();
    if (!$row) throw new InvalidArgumentException('Modalidade não encontrada.');
    if ((string) ($row['metrica_derivada'] ?? '') !== 'pace_km') throw new InvalidArgumentException('Pacer v1 exige modalidade com metrica_derivada=pace_km.');
    return $row;
}

function pacerValidateTarget(mixed $distanceRaw, mixed $timeRaw): array
{
    $distance = activityStreamFinite($distanceRaw);
    $time = activityStreamFinite($timeRaw);
    if ($distance === null || $distance <= 0 || $distance > 500000) throw new InvalidArgumentException('target_distance_m precisa ficar entre 0 e 500000.');
    if ($time === null || $time <= 0 || $time > 604800) throw new InvalidArgumentException('target_time_s precisa ficar entre 0 e 604800.');
    $pace = $time / ($distance / 1000.0);
    if ($pace < 60 || $pace > 3600) throw new InvalidArgumentException('O pace alvo é estruturalmente inválido para o Pacer v1.');
    return ['distance_m' => $distance, 'time_s' => $time, 'average_pace_s_per_km' => $pace];
}

function pacerGeneratedSegments(float $distanceM, float $timeS, string $strategy, float $tolerance, array $constraints = []): array
{
    if (!in_array($strategy, ['even','negative_split','positive_split'], true)) throw new InvalidArgumentException('strategy de geração precisa ser even, negative_split ou positive_split.');
    if ($strategy === 'even') {
        return [[
            'order' => 1, 'basis' => 'distance', 'start_distance_m' => 0.0, 'end_distance_m' => $distanceM,
            'target_pace_s_per_km' => $timeS / ($distanceM / 1000.0), 'tolerance_s_per_km' => $tolerance,
            'heart_rate_floor_bpm' => isset($constraints['heart_rate_floor_bpm']) && is_numeric($constraints['heart_rate_floor_bpm']) ? (int) $constraints['heart_rate_floor_bpm'] : null,
            'heart_rate_ceiling_bpm' => isset($constraints['heart_rate_ceiling_bpm']) && is_numeric($constraints['heart_rate_ceiling_bpm']) ? (int) $constraints['heart_rate_ceiling_bpm'] : null,
            'instruction_metadata' => [],
        ]];
    }
    $progression = activityStreamFinite($constraints['progression_percent'] ?? 8.0);
    if ($progression === null || $progression <= 0 || $progression > 30) throw new InvalidArgumentException('progression_percent precisa ficar entre 0 e 30.');
    $segmentLength = activityStreamFinite($constraints['segment_distance_m'] ?? null);
    if ($segmentLength === null) $segmentLength = $distanceM <= 5000 ? 1000.0 : ($distanceM <= 15000 ? 2000.0 : 5000.0);
    if ($segmentLength < 200 || $segmentLength > $distanceM) $segmentLength = min($distanceM, max(200.0, $segmentLength));
    $basePace = $timeS / ($distanceM / 1000.0);
    $segments = [];
    $start = 0.0;
    $order = 1;
    while ($start < $distanceM - 0.001) {
        $end = min($distanceM, $start + $segmentLength);
        $midRatio = (($start + $end) / 2.0) / $distanceM;
        $signed = ($strategy === 'negative_split' ? 1.0 : -1.0) * (0.5 - $midRatio) * ($progression / 100.0) * 2.0;
        $segments[] = ['order' => $order++, 'basis' => 'distance', 'start_distance_m' => $start, 'end_distance_m' => $end, 'target_pace_s_per_km' => $basePace * (1.0 + $signed), 'tolerance_s_per_km' => $tolerance, 'heart_rate_floor_bpm' => isset($constraints['heart_rate_floor_bpm']) && is_numeric($constraints['heart_rate_floor_bpm']) ? (int) $constraints['heart_rate_floor_bpm'] : null, 'heart_rate_ceiling_bpm' => isset($constraints['heart_rate_ceiling_bpm']) && is_numeric($constraints['heart_rate_ceiling_bpm']) ? (int) $constraints['heart_rate_ceiling_bpm'] : null, 'instruction_metadata' => ['generated_progression_percent' => $progression]];
        $start = $end;
    }
    $rawTime = 0.0;
    foreach ($segments as $segment) $rawTime += (($segment['end_distance_m'] - $segment['start_distance_m']) / 1000.0) * $segment['target_pace_s_per_km'];
    $scale = $rawTime > 0 ? $timeS / $rawTime : 1.0;
    foreach ($segments as &$segment) $segment['target_pace_s_per_km'] *= $scale;
    unset($segment);
    return $segments;
}

function pacerValidateSegments(array $segments, float $targetDistanceM, float $defaultTolerance): array
{
    if ($segments === [] || !array_is_list($segments) || count($segments) > 200) throw new InvalidArgumentException('Informe entre 1 e 200 segmentos.');
    $normalized = [];
    $expectedStart = 0.0;
    foreach ($segments as $index => $segment) {
        if (!is_array($segment)) throw new InvalidArgumentException('Cada segmento precisa ser um objeto.');
        $basis = strtolower(trim((string) ($segment['basis'] ?? 'distance')));
        if ($basis !== 'distance') throw new InvalidArgumentException('Pacer API v1 aceita segmentos por distance; time permanece future-ready no schema.');
        $start = activityStreamFinite($segment['start_distance_m'] ?? null);
        $end = activityStreamFinite($segment['end_distance_m'] ?? null);
        $pace = activityStreamFinite($segment['target_pace_s_per_km'] ?? null);
        $tolerance = activityStreamFinite($segment['tolerance_s_per_km'] ?? $defaultTolerance);
        if ($start === null || $end === null || $start < 0 || $end <= $start) throw new InvalidArgumentException('Intervalo de distância do segmento é inválido.');
        if (abs($start - $expectedStart) > 0.5) throw new InvalidArgumentException('Segmentos precisam cobrir a distância sem gaps ou sobreposição.');
        if ($pace === null || $pace < 60 || $pace > 3600) throw new InvalidArgumentException('target_pace_s_per_km do segmento é inválido.');
        if ($tolerance === null || $tolerance < 0 || $tolerance > 600) throw new InvalidArgumentException('tolerance_s_per_km do segmento é inválido.');
        $floor = isset($segment['heart_rate_floor_bpm']) && $segment['heart_rate_floor_bpm'] !== null ? filter_var($segment['heart_rate_floor_bpm'], FILTER_VALIDATE_INT) : null;
        $ceiling = isset($segment['heart_rate_ceiling_bpm']) && $segment['heart_rate_ceiling_bpm'] !== null ? filter_var($segment['heart_rate_ceiling_bpm'], FILTER_VALIDATE_INT) : null;
        if ($floor === false || ($floor !== null && ($floor < 20 || $floor > 260))) throw new InvalidArgumentException('heart_rate_floor_bpm é inválido.');
        if ($ceiling === false || ($ceiling !== null && ($ceiling < 20 || $ceiling > 260))) throw new InvalidArgumentException('heart_rate_ceiling_bpm é inválido.');
        if ($floor !== null && $ceiling !== null && $ceiling <= $floor) throw new InvalidArgumentException('heart_rate_ceiling_bpm precisa ser maior que floor.');
        $metadata = is_array($segment['instruction_metadata'] ?? null) && !array_is_list($segment['instruction_metadata']) ? $segment['instruction_metadata'] : [];
        $normalized[] = ['order' => $index + 1, 'basis' => 'distance', 'start_distance_m' => $start, 'end_distance_m' => $end, 'target_pace_s_per_km' => $pace, 'tolerance_s_per_km' => $tolerance, 'heart_rate_floor_bpm' => $floor !== null ? (int) $floor : null, 'heart_rate_ceiling_bpm' => $ceiling !== null ? (int) $ceiling : null, 'instruction_metadata' => $metadata];
        $expectedStart = $end;
    }
    if (abs($expectedStart - $targetDistanceM) > 0.5) throw new InvalidArgumentException('Segmentos precisam cobrir target_distance_m integralmente.');
    return $normalized;
}

function pacerBlueprint(PDO $pdo, string $userId, array $payload): array
{
    $sport = pacerResolveSport($pdo, $userId, (string) ($payload['sport'] ?? ''));
    $target = pacerValidateTarget($payload['target_distance_m'] ?? null, $payload['target_time_s'] ?? null);
    $strategy = strtolower(trim((string) ($payload['strategy'] ?? 'even')));
    if (!in_array($strategy, ['even','negative_split','positive_split','custom'], true)) throw new InvalidArgumentException('strategy inválida.');
    $tolerance = activityStreamFinite($payload['tolerance_s_per_km'] ?? 10);
    if ($tolerance === null || $tolerance < 0 || $tolerance > 600) throw new InvalidArgumentException('tolerance_s_per_km precisa ficar entre 0 e 600.');
    $constraints = is_array($payload['constraints'] ?? null) ? $payload['constraints'] : [];
    $preserveSegments = !empty($payload['_preserve_segments']);
    $segments = $strategy === 'custom' || $preserveSegments
        ? pacerValidateSegments(is_array($payload['segments'] ?? null) ? $payload['segments'] : [], $target['distance_m'], $tolerance)
        : pacerGeneratedSegments($target['distance_m'], $target['time_s'], $strategy, $tolerance, $constraints);
    $segments = pacerValidateSegments($segments, $target['distance_m'], $tolerance);
    $calculatedTime = 0.0;
    foreach ($segments as $segment) $calculatedTime += (($segment['end_distance_m'] - $segment['start_distance_m']) / 1000.0) * $segment['target_pace_s_per_km'];
    return [
        'sport' => ['id' => (string) $sport['idmodalidade'], 'slug' => (string) $sport['slug'], 'name' => (string) $sport['nome'], 'derived_metric' => (string) $sport['metrica_derivada']],
        'strategy' => $strategy,
        'target_distance_m' => $target['distance_m'],
        'target_time_s' => $target['time_s'],
        'target_average_pace_s_per_km' => $target['average_pace_s_per_km'],
        'default_tolerance_s_per_km' => $tolerance,
        'terrain_adjustment_mode' => 'none',
        'guidance_rules' => pacerNormalizeRules(is_array($payload['guidance_rules'] ?? null) ? $payload['guidance_rules'] : []),
        'segments' => $segments,
        'calculated_target_time_s' => $calculatedTime,
    ];
}

function pacerPlanSave(PDO $pdo, string $userId, array $payload, ?string $planId = null): array
{
    $blueprint = pacerBlueprint($pdo, $userId, $payload);
    $name = trim((string) ($payload['name'] ?? ''));
    if ($name === '') $name = $blueprint['sport']['name'] . ' · ' . round($blueprint['target_distance_m'] / 1000, 2) . ' km';
    if (strlen($name) > 120) throw new InvalidArgumentException('name do Pacer Plan é muito longo.');
    $status = strtolower(trim((string) ($payload['status'] ?? 'active')));
    if (!in_array($status, ['active','archived'], true)) throw new InvalidArgumentException('status do Pacer Plan é inválido.');
    if ($planId !== null) {
        $owner = $pdo->prepare('SELECT 1 FROM pacer_plans WHERE idplan=:id AND idusuario=:user LIMIT 1');
        $owner->execute([':id' => $planId, ':user' => $userId]);
        if (!$owner->fetchColumn()) throw new InvalidArgumentException('Pacer Plan não encontrado.');
    }
    $owns = !$pdo->inTransaction();
    if ($owns) $pdo->beginTransaction();
    try {
        $planId ??= activityStreamId();
        $stmt = $pdo->prepare("INSERT INTO pacer_plans (idplan,idusuario,idmodalidade,name,strategy,target_distance_m,target_time_s,target_average_pace_s_per_km,default_tolerance_s_per_km,terrain_adjustment_mode,guidance_rules,status,version,data_atualizacao) VALUES (:id,:user,:sport,:name,:strategy,:distance,:time,:pace,:tolerance,'none',CAST(:rules AS jsonb),:status,1,NOW()) ON CONFLICT (idplan) DO UPDATE SET idmodalidade=EXCLUDED.idmodalidade,name=EXCLUDED.name,strategy=EXCLUDED.strategy,target_distance_m=EXCLUDED.target_distance_m,target_time_s=EXCLUDED.target_time_s,target_average_pace_s_per_km=EXCLUDED.target_average_pace_s_per_km,default_tolerance_s_per_km=EXCLUDED.default_tolerance_s_per_km,terrain_adjustment_mode='none',guidance_rules=EXCLUDED.guidance_rules,status=EXCLUDED.status,version=pacer_plans.version+1,data_atualizacao=NOW()");
        $stmt->execute([':id' => $planId, ':user' => $userId, ':sport' => $blueprint['sport']['id'], ':name' => $name, ':strategy' => $blueprint['strategy'], ':distance' => $blueprint['target_distance_m'], ':time' => $blueprint['target_time_s'], ':pace' => $blueprint['target_average_pace_s_per_km'], ':tolerance' => $blueprint['default_tolerance_s_per_km'], ':rules' => json_encode($blueprint['guidance_rules'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ':status' => $status]);
        $pdo->prepare('DELETE FROM pacer_plan_segments WHERE idplan=:id')->execute([':id' => $planId]);
        $insert = $pdo->prepare('INSERT INTO pacer_plan_segments (idsegment,idplan,segment_order,basis,start_distance_m,end_distance_m,target_pace_s_per_km,tolerance_s_per_km,heart_rate_floor_bpm,heart_rate_ceiling_bpm,instruction_metadata) VALUES (:id,:plan,:ord,:basis,:start,:end,:pace,:tolerance,:hr_floor,:hr_ceiling,CAST(:metadata AS jsonb))');
        foreach ($blueprint['segments'] as $segment) $insert->execute([':id' => activityStreamId(), ':plan' => $planId, ':ord' => $segment['order'], ':basis' => $segment['basis'], ':start' => $segment['start_distance_m'], ':end' => $segment['end_distance_m'], ':pace' => $segment['target_pace_s_per_km'], ':tolerance' => $segment['tolerance_s_per_km'], ':hr_floor' => $segment['heart_rate_floor_bpm'], ':hr_ceiling' => $segment['heart_rate_ceiling_bpm'], ':metadata' => activityStreamEncodeJsonObject($segment['instruction_metadata'])]);
        if ($owns) $pdo->commit();
    } catch (Throwable $e) {
        if ($owns && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return pacerPlanGet($pdo, $userId, $planId);
}

function pacerPlanGet(PDO $pdo, string $userId, string $planId, bool $includeArchived = true): array
{
    $sql = 'SELECT p.*,m.slug AS sport_slug,m.nome AS sport_name,m.metrica_derivada FROM pacer_plans p JOIN modalidades m ON m.idmodalidade=p.idmodalidade WHERE p.idplan=:id AND p.idusuario=:user';
    if (!$includeArchived) $sql .= " AND p.status='active'";
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $planId, ':user' => $userId]);
    $row = $stmt->fetch();
    if (!$row) return [];
    $segments = $pdo->prepare('SELECT * FROM pacer_plan_segments WHERE idplan=:id ORDER BY segment_order');
    $segments->execute([':id' => $planId]);
    $rules = is_array($row['guidance_rules'] ?? null) ? $row['guidance_rules'] : json_decode((string) ($row['guidance_rules'] ?? '{}'), true);
    return [
        'id' => (string) $row['idplan'], 'name' => (string) $row['name'], 'sport' => ['id' => (string) $row['idmodalidade'], 'slug' => (string) $row['sport_slug'], 'name' => (string) $row['sport_name'], 'derived_metric' => (string) $row['metrica_derivada']],
        'strategy' => (string) $row['strategy'], 'target_distance_m' => (float) $row['target_distance_m'], 'target_time_s' => (float) $row['target_time_s'], 'target_average_pace_s_per_km' => (float) $row['target_average_pace_s_per_km'], 'default_tolerance_s_per_km' => (float) $row['default_tolerance_s_per_km'],
        'terrain_adjustment_mode' => (string) $row['terrain_adjustment_mode'], 'guidance_rules' => is_array($rules) ? $rules : pacerDefaultRules(), 'status' => (string) $row['status'], 'version' => (int) $row['version'],
        'segments' => array_map(static function(array $segment): array { $metadata=is_array($segment['instruction_metadata']??null)?$segment['instruction_metadata']:json_decode((string)($segment['instruction_metadata']??'{}'),true); return ['id'=>(string)$segment['idsegment'],'order'=>(int)$segment['segment_order'],'basis'=>(string)$segment['basis'],'start_distance_m'=>$segment['start_distance_m']!==null?(float)$segment['start_distance_m']:null,'end_distance_m'=>$segment['end_distance_m']!==null?(float)$segment['end_distance_m']:null,'start_time_s'=>$segment['start_time_s']!==null?(float)$segment['start_time_s']:null,'end_time_s'=>$segment['end_time_s']!==null?(float)$segment['end_time_s']:null,'target_pace_s_per_km'=>$segment['target_pace_s_per_km']!==null?(float)$segment['target_pace_s_per_km']:null,'tolerance_s_per_km'=>$segment['tolerance_s_per_km']!==null?(float)$segment['tolerance_s_per_km']:null,'heart_rate_floor_bpm'=>$segment['heart_rate_floor_bpm']!==null?(int)$segment['heart_rate_floor_bpm']:null,'heart_rate_ceiling_bpm'=>$segment['heart_rate_ceiling_bpm']!==null?(int)$segment['heart_rate_ceiling_bpm']:null,'instruction_metadata'=>is_array($metadata)?$metadata:[]]; }, $segments->fetchAll()),
        'created_at' => activityStreamIso((string) $row['data_criacao']), 'updated_at' => activityStreamIso((string) $row['data_atualizacao']),
    ];
}

function pacerPlanList(PDO $pdo, string $userId, array $query = []): array
{
    $status = strtolower(trim((string) ($query['status'] ?? 'active')));
    if (!in_array($status, ['active','archived','all'], true)) throw new InvalidArgumentException('status precisa ser active, archived ou all.');
    $where = ['idusuario=:user'];
    $params = [':user' => $userId];
    if ($status !== 'all') { $where[] = 'status=:status'; $params[':status'] = $status; }
    $stmt = $pdo->prepare('SELECT idplan FROM pacer_plans WHERE ' . implode(' AND ', $where) . ' ORDER BY data_atualizacao DESC,idplan');
    $stmt->execute($params);
    return array_values(array_map(static fn(string $id): array => pacerPlanGet($pdo, $userId, $id), array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
}

function pacerPlanArchive(PDO $pdo, string $userId, string $planId): void
{
    $stmt = $pdo->prepare("UPDATE pacer_plans SET status='archived',version=version+1,data_atualizacao=NOW() WHERE idplan=:id AND idusuario=:user");
    $stmt->execute([':id' => $planId, ':user' => $userId]);
    if ($stmt->rowCount() === 0) throw new InvalidArgumentException('Pacer Plan não encontrado.');
}

function pacerTargetElapsedAtDistance(array $plan, float $distanceM): float
{
    $distanceM = max(0.0, min((float) $plan['target_distance_m'], $distanceM));
    $elapsed = 0.0;
    foreach ($plan['segments'] as $segment) {
        if ($segment['basis'] !== 'distance') continue;
        $start = (float) $segment['start_distance_m'];
        $end = (float) $segment['end_distance_m'];
        if ($distanceM <= $start) break;
        $covered = min($distanceM, $end) - $start;
        if ($covered > 0) $elapsed += ($covered / 1000.0) * (float) $segment['target_pace_s_per_km'];
        if ($distanceM <= $end) break;
    }
    return $elapsed;
}

function pacerSegmentAtDistance(array $plan, float $distanceM): ?array
{
    foreach ($plan['segments'] as $segment) {
        if ($segment['basis'] !== 'distance') continue;
        if ($distanceM >= (float) $segment['start_distance_m'] && ($distanceM < (float) $segment['end_distance_m'] || abs($distanceM - (float) $plan['target_distance_m']) < 0.01)) return $segment;
    }
    return null;
}

function pacerEvaluate(array $plan, array $state): array
{
    $distance = activityStreamFinite($state['distance_m'] ?? null);
    $elapsed = activityStreamFinite($state['elapsed_s'] ?? null);
    $moving = activityStreamFinite($state['moving_time_s'] ?? $state['moving_s'] ?? $elapsed);
    $recentPace = activityStreamFinite($state['recent_pace_s_per_km'] ?? null);
    $averagePace = activityStreamFinite($state['average_pace_s_per_km'] ?? null);
    if ($distance === null || $distance < 0 || $elapsed === null || $elapsed < 0 || $moving === null || $moving < 0) throw new InvalidArgumentException('distance_m, elapsed_s e moving_time_s precisam ser não negativos.');
    $rules = pacerNormalizeRules(is_array($plan['guidance_rules'] ?? null) ? $plan['guidance_rules'] : []);
    $previous = is_array($state['guidance_state'] ?? null) ? $state['guidance_state'] : [];
    $targetDistance = (float) $plan['target_distance_m'];
    if ($distance >= $targetDistance) return ['code' => 'finished', 'segment' => null, 'target' => null, 'ahead_behind_s' => $elapsed - (float) $plan['target_time_s'], 'target_elapsed_s' => (float) $plan['target_time_s'], 'projected_finish' => ['at_current_average_s' => $elapsed, 'if_plan_followed_s' => $elapsed], 'heart_rate_constraint' => ['available' => isset($state['heart_rate_bpm']) && is_numeric($state['heart_rate_bpm']), 'status' => 'finished'], 'next_state' => $previous];
    $segment = pacerSegmentAtDistance($plan, $distance);
    if ($segment === null) throw new RuntimeException('Nenhum segmento do Pacer cobre a distância atual.');
    $targetPace = (float) $segment['target_pace_s_per_km'];
    $tolerance = (float) ($segment['tolerance_s_per_km'] ?? $plan['default_tolerance_s_per_km'] ?? 10);
    $targetElapsed = pacerTargetElapsedAtDistance($plan, $distance);
    $aheadBehind = $elapsed - $targetElapsed;
    $currentAverage = $averagePace;
    if ($currentAverage === null && $distance > 0 && $moving > 0) $currentAverage = $moving / ($distance / 1000.0);
    $projectCurrent = $currentAverage !== null && $distance > 0 ? $elapsed + (($targetDistance - $distance) / 1000.0) * $currentAverage : null;
    $planRemaining = max(0.0, (float) $plan['target_time_s'] - $targetElapsed);
    $projectPlan = $elapsed + $planRemaining;
    $hr = isset($state['heart_rate_bpm']) && is_numeric($state['heart_rate_bpm']) ? (int) $state['heart_rate_bpm'] : null;
    $hrCeiling = $segment['heart_rate_ceiling_bpm'] ?? null;
    $hrAvailable = $hr !== null;
    $hrStatus = $hrCeiling !== null ? ($hrAvailable ? ($hr > (int) $hrCeiling ? 'above_ceiling' : 'within_constraint') : 'unavailable') : 'not_configured';
    $now = $moving;
    $hrAboveSince = is_numeric($previous['hr_above_since_s'] ?? null) ? (float) $previous['hr_above_since_s'] : null;
    if ($hrCeiling !== null && $hr !== null && $hr > (int) $hrCeiling) $hrAboveSince ??= $now; else $hrAboveSince = null;
    $previousSegment = isset($previous['segment_order']) && is_numeric($previous['segment_order']) ? (int) $previous['segment_order'] : null;
    $segmentChanged = $previousSegment !== null && $previousSegment !== (int) $segment['order'];
    $rawCode = 'on_target';
    if ($recentPace !== null) {
        if ($recentPace > $targetPace + $tolerance) $rawCode = 'speed_up';
        elseif ($recentPace < $targetPace - $tolerance) $rawCode = 'slow_down';
    }
    $lastGuidance = (string) ($previous['last_guidance_code'] ?? 'on_target');
    $hysteresis = (float) $rules['hysteresis_s_per_km'];
    if ($rawCode === 'slow_down' && $lastGuidance === 'speed_up' && $recentPace !== null && $recentPace >= $targetPace - $tolerance - $hysteresis) $rawCode = 'on_target';
    if ($rawCode === 'speed_up' && $lastGuidance === 'slow_down' && $recentPace !== null && $recentPace <= $targetPace + $tolerance + $hysteresis) $rawCode = 'on_target';
    $candidate = (string) ($previous['candidate_code'] ?? '');
    $candidateSince = is_numeric($previous['candidate_since_s'] ?? null) ? (float) $previous['candidate_since_s'] : null;
    if ($candidate !== $rawCode) { $candidate = $rawCode; $candidateSince = $now; }
    $lastGuidanceAt = is_numeric($previous['last_guidance_at_s'] ?? null) ? (float) $previous['last_guidance_at_s'] : null;
    $code = 'on_target';
    if ($segmentChanged) {
        $code = 'segment_change';
    } elseif ($hrAboveSince !== null && $now - $hrAboveSince >= (float) $rules['heart_rate_persistence_s']) {
        $code = 'ease';
    } elseif ($candidate !== 'on_target' && $candidateSince !== null && $now - $candidateSince >= (float) $rules['persistence_s']) {
        $cooldownReady = $lastGuidanceAt === null || $now - $lastGuidanceAt >= (float) $rules['cooldown_s'] || $candidate === $lastGuidance;
        if ($cooldownReady) $code = $candidate;
    }
    $remaining = max(0.0, $targetDistance - $distance);
    $finalPush = $rules['final_push'];
    if ($code === 'on_target' && !empty($finalPush['enabled']) && $remaining <= (float) $finalPush['remaining_distance_m'] && $aheadBehind <= (float) $finalPush['max_behind_s']) {
        $hrCondition = $hrCeiling === null || ($hr !== null && $hr <= (int) $hrCeiling);
        if ($hrCondition) $code = 'final_push_available';
    }
    $nextState = [
        'segment_order' => (int) $segment['order'],
        'candidate_code' => $candidate,
        'candidate_since_s' => $candidateSince,
        'hr_above_since_s' => $hrAboveSince,
        'last_guidance_code' => in_array($code, ['speed_up','slow_down','ease','segment_change','final_push_available'], true) ? $code : $lastGuidance,
        'last_guidance_at_s' => in_array($code, ['speed_up','slow_down','ease','segment_change','final_push_available'], true) ? $now : $lastGuidanceAt,
    ];
    return [
        'code' => $code,
        'segment' => ['order' => (int) $segment['order'], 'start_distance_m' => (float) $segment['start_distance_m'], 'end_distance_m' => (float) $segment['end_distance_m'], 'progress_percent' => ((float) $segment['end_distance_m'] - (float) $segment['start_distance_m']) > 0 ? max(0.0, min(100.0, ($distance - (float) $segment['start_distance_m']) / ((float) $segment['end_distance_m'] - (float) $segment['start_distance_m']) * 100.0)) : 0.0],
        'target' => ['pace_s_per_km' => $targetPace, 'tolerance_s_per_km' => $tolerance, 'lower_pace_s_per_km' => max(0.0, $targetPace - $tolerance), 'upper_pace_s_per_km' => $targetPace + $tolerance, 'heart_rate_floor_bpm' => $segment['heart_rate_floor_bpm'], 'heart_rate_ceiling_bpm' => $segment['heart_rate_ceiling_bpm']],
        'ahead_behind_s' => $aheadBehind,
        'target_elapsed_s' => $targetElapsed,
        'remaining_distance_m' => $remaining,
        'projected_finish' => ['at_current_average_s' => $projectCurrent, 'if_plan_followed_s' => $projectPlan],
        'heart_rate_constraint' => ['available' => $hrAvailable, 'status' => $hrStatus, 'current_bpm' => $hr, 'ceiling_bpm' => $hrCeiling],
        'rules' => $rules,
        'next_state' => $nextState,
    ];
}

function pacerPlanEvaluate(PDO $pdo, string $userId, string $planId, array $state): array
{
    $plan = pacerPlanGet($pdo, $userId, $planId, false);
    if ($plan === []) throw new InvalidArgumentException('Pacer Plan não encontrado.');
    return pacerEvaluate($plan, $state);
}

function pacerPlanValidateForWorkout(PDO $pdo, string $userId, ?string $planId, string $sportId): ?string
{
    $planId = trim((string) $planId);
    if ($planId === '') return null;
    $plan = pacerPlanGet($pdo, $userId, $planId, false);
    if ($plan === []) throw new InvalidArgumentException('Pacer Plan não encontrado.');
    if ((string) $plan['sport']['id'] !== $sportId) throw new InvalidArgumentException('Pacer Plan usa modalidade diferente do treino.');
    return $planId;
}
