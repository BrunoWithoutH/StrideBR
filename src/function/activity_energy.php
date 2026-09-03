<?php

declare(strict_types=1);

function atividadeEnergiaClamp(float $value, float $min, float $max): float
{
    return max($min, min($max, $value));
}

function atividadeEnergiaInterpolar(float $a, float $b, float $t): float
{
    return $a + (($b - $a) * atividadeEnergiaClamp($t, 0.0, 1.0));
}

function atividadeEnergiaMetPorEsforco(array $profile, ?int $rpe): float
{
    $light = (float) ($profile['met_leve'] ?? 2.5);
    $moderate = (float) ($profile['met_moderado'] ?? max($light, 4.5));
    $vigorous = (float) ($profile['met_vigoroso'] ?? max($moderate, 7.0));
    $maximum = (float) ($profile['met_maximo'] ?? max($vigorous, 10.0));
    if ($rpe === null) return $moderate;
    $rpe = max(1, min(10, $rpe));
    if ($rpe <= 4) return atividadeEnergiaInterpolar($light, $moderate, ($rpe - 1) / 3);
    if ($rpe <= 7) return atividadeEnergiaInterpolar($moderate, $vigorous, ($rpe - 4) / 3);
    return atividadeEnergiaInterpolar($vigorous, $maximum, ($rpe - 7) / 3);
}

function atividadeEnergiaMetRota(string $model, float $speedMMin, array $profile): ?float
{
    if (!in_array($model, ['run', 'walk'], true) || count($profile) < 2 || $speedMMin <= 0) return null;
    $weighted = 0.0;
    $distance = 0.0;
    for ($i = 1, $count = count($profile); $i < $count; $i++) {
        $previous = $profile[$i - 1];
        $current = $profile[$i];
        if (!is_array($previous) || !is_array($current)) continue;
        if (!is_numeric($previous['distancia_m'] ?? null) || !is_numeric($current['distancia_m'] ?? null) || !is_numeric($previous['elevacao_m'] ?? null) || !is_numeric($current['elevacao_m'] ?? null)) continue;
        $dx = (float) $current['distancia_m'] - (float) $previous['distancia_m'];
        if ($dx <= 0) continue;
        $grade = ((float) $current['elevacao_m'] - (float) $previous['elevacao_m']) / $dx;
        $grade = atividadeEnergiaClamp($grade, -0.12, 0.30);
        if ($grade < 0) $grade *= 0.35;
        $vo2 = $model === 'walk'
            ? (0.1 * $speedMMin) + (1.8 * $speedMMin * $grade) + 3.5
            : (0.2 * $speedMMin) + (0.9 * $speedMMin * $grade) + 3.5;
        $met = max(1.5, $vo2 / 3.5);
        $weighted += $met * $dx;
        $distance += $dx;
    }
    return $distance > 0 ? $weighted / $distance : null;
}

function atividadeEnergiaMetCiclismo(float $speedKmh): float
{
    if ($speedKmh < 16) return 4.0;
    if ($speedKmh < 19) return 6.8;
    if ($speedKmh < 22) return 8.0;
    if ($speedKmh < 25) return 10.0;
    if ($speedKmh < 30) return 12.0;
    if ($speedKmh < 35) return 14.0;
    return 16.8;
}

function atividadeEnergiaMetNatacao(float $speedMMin): float
{
    if ($speedMMin < 25) return 6.0;
    if ($speedMMin < 33) return 7.0;
    if ($speedMMin < 42) return 8.3;
    if ($speedMMin < 50) return 10.0;
    if ($speedMMin < 58) return 12.0;
    return 14.5;
}

function atividadeEnergiaMetRemo(?float $powerW, float $speedKmh, array $profile, ?int $rpe): float
{
    if ($powerW !== null && $powerW > 0) {
        if ($powerW < 75) return 3.5;
        if ($powerW < 125) return 7.0;
        if ($powerW < 175) return 8.5;
        if ($powerW < 225) return 12.0;
        return 14.0;
    }
    if ($speedKmh > 0) {
        if ($speedKmh < 5) return 3.5;
        if ($speedKmh < 7) return 6.0;
        if ($speedKmh < 9) return 8.5;
        return 12.0;
    }
    return atividadeEnergiaMetPorEsforco($profile, $rpe);
}

function atividadeEnergiaCaloriasMet(float $met, float $weightKg, float $minutes): array
{
    $oneMetPerMinute = 3.5 * $weightKg / 200;
    return [
        'active_kcal' => max(0.0, ($met - 1.0) * $oneMetPerMinute * $minutes),
        'total_kcal' => max(0.0, $met * $oneMetPerMinute * $minutes),
    ];
}

function atividadeEnergiaEstimar(array $context, array $profile, float $weightKg): ?array
{
    $durationS = is_numeric($context['duration_s'] ?? null) ? max(0.0, (float) $context['duration_s']) : 0.0;
    if ($durationS < 60 || $weightKg <= 0) return null;
    $minutes = $durationS / 60;
    $hours = $durationS / 3600;
    $distanceM = is_numeric($context['distance_m'] ?? null) ? max(0.0, (float) $context['distance_m']) : 0.0;
    $gainM = is_numeric($context['elevation_gain_m'] ?? null) ? max(0.0, (float) $context['elevation_gain_m']) : 0.0;
    $powerW = is_numeric($context['avg_power_w'] ?? null) ? max(0.0, (float) $context['avg_power_w']) : null;
    $rpe = is_numeric($context['rpe'] ?? null) ? max(1, min(10, (int) $context['rpe'])) : null;
    $sessionType = stridebr_lower(trim((string) ($context['session_type'] ?? '')));
    $gameFormat = stridebr_lower(trim((string) ($context['game_format'] ?? '')));
    $rounds = is_numeric($context['rounds'] ?? null) ? max(0, (int) $context['rounds']) : 0;
    $model = (string) ($profile['modelo'] ?? 'generic');
    $speedKmh = $hours > 0 ? ($distanceM / 1000) / $hours : 0.0;
    $speedMMin = $minutes > 0 ? $distanceM / $minutes : 0.0;
    $method = 'met_rpe';
    $confidence = $rpe !== null ? 'moderada' : 'baixa';
    $met = atividadeEnergiaMetPorEsforco($profile, $rpe);
    $directCalories = null;

    if (in_array($model, ['run', 'walk'], true) && $distanceM > 0) {
        $routeProfile = is_array($context['elevation_profile'] ?? null) ? $context['elevation_profile'] : [];
        $routeMet = atividadeEnergiaMetRota($model, $speedMMin, $routeProfile);
        if ($routeMet !== null) {
            $met = $routeMet;
            $method = $model . '_pace_grade_profile';
            $confidence = 'alta';
        } else {
            $vo2 = $model === 'walk' ? (0.1 * $speedMMin) + 3.5 : (0.2 * $speedMMin) + 3.5;
            $met = max(1.5, $vo2 / 3.5);
            if ($gainM > 0 && $distanceM > 0) $met += min(3.0, ($gainM / $distanceM) * 35.0);
            $method = $model . '_pace';
            $confidence = 'boa';
        }
    } elseif ($model === 'cycle') {
        if ($powerW !== null && $powerW >= 20) {
            $active = ($powerW * $durationS / 1000) * 1.02;
            $resting = (3.5 * $weightKg / 200) * $minutes;
            $directCalories = ['active_kcal' => $active, 'total_kcal' => $active + $resting];
            $met = $directCalories['total_kcal'] / max(0.01, $resting);
            $method = 'cycle_power';
            $confidence = 'alta';
        } elseif ($distanceM > 0) {
            $met = atividadeEnergiaMetCiclismo($speedKmh);
            if ($gainM > 0 && $hours > 0) $met += min(3.0, ($gainM / $hours) / 350.0);
            $method = 'cycle_speed';
            $confidence = 'boa';
        }
    } elseif ($model === 'swim' && $distanceM > 0) {
        $met = atividadeEnergiaMetNatacao($speedMMin);
        $method = 'swim_pace';
        $confidence = 'boa';
    } elseif ($model === 'row') {
        $met = atividadeEnergiaMetRemo($powerW, $speedKmh, $profile, $rpe);
        $method = $powerW !== null ? 'row_power' : ($distanceM > 0 ? 'row_speed' : 'row_rpe');
        $confidence = $powerW !== null ? 'alta' : ($distanceM > 0 ? 'boa' : ($rpe !== null ? 'moderada' : 'baixa'));
    } elseif ($model === 'strength') {
        $sets = is_numeric($context['completed_sets'] ?? null) ? max(0, (int) $context['completed_sets']) : 0;
        $setsPerHour = $hours > 0 ? $sets / $hours : 0.0;
        if ($sets > 0) {
            $met = $setsPerHour < 12 ? 3.5 : ($setsPerHour < 20 ? 4.5 : ($setsPerHour < 30 ? 5.5 : 6.5));
            if ($rpe !== null) $met += ($rpe - 5) * 0.18;
            $method = 'strength_density';
            $confidence = 'boa';
        }
    } elseif ($model === 'hiit') {
        $met = atividadeEnergiaMetPorEsforco($profile, $rpe ?? 7);
        $method = 'hiit_rpe';
        $confidence = $rpe !== null ? 'boa' : 'moderada';
    } elseif ($model === 'field_event') {
        $attempts = is_numeric($context['unit_count'] ?? null) ? max(0, (int) $context['unit_count']) : 0;
        $met = atividadeEnergiaMetPorEsforco($profile, $rpe);
        if ($attempts > 0 && $hours > 0) $met += min(1.0, ($attempts / $hours) / 20.0);
        $method = 'field_event_session';
        $confidence = $rpe !== null || $attempts > 0 ? 'moderada' : 'baixa';
    } elseif ($model === 'sprint') {
        $met = atividadeEnergiaMetPorEsforco($profile, $rpe ?? 8);
        $method = 'sprint_session';
        $confidence = $rpe !== null ? 'moderada' : 'baixa';
    } elseif ($model === 'racket') {
        $met = atividadeEnergiaMetPorEsforco($profile, $rpe);
        if ($sessionType === 'aula') $met *= 0.88;
        elseif ($sessionType === 'treino') $met *= 0.94;
        elseif ($sessionType === 'partida') $met *= 1.03;
        if ($gameFormat === 'duplas') $met *= 0.88;
        $method = 'racket_session';
        $confidence = $sessionType !== '' || $gameFormat !== '' || $rpe !== null ? 'moderada' : 'baixa';
    } elseif ($model === 'team') {
        $met = atividadeEnergiaMetPorEsforco($profile, $rpe);
        if ($sessionType === 'treino') $met *= 0.90;
        elseif ($sessionType === 'amistoso') $met *= 0.96;
        elseif ($sessionType === 'jogo') $met *= 1.04;
        $method = 'team_session';
        $confidence = $sessionType !== '' || $rpe !== null ? 'moderada' : 'baixa';
    } elseif ($model === 'combat') {
        $met = atividadeEnergiaMetPorEsforco($profile, $rpe);
        if ($sessionType === 'tecnica') $met *= 0.76;
        elseif ($sessionType === 'saco-manopla') $met *= 0.95;
        elseif ($sessionType === 'sparring') $met *= 1.08;
        elseif (in_array($sessionType, ['luta','competicao'], true)) $met *= 1.16;
        if ($rounds > 0 && $hours > 0) {
            $roundsPerHour = $rounds / $hours;
            if ($roundsPerHour >= 10) $met *= 1.04;
        }
        $method = 'combat_session';
        $confidence = $sessionType !== '' || $rounds > 0 || $rpe !== null ? 'moderada' : 'baixa';
    } else {
        $method = $model . ($rpe !== null ? '_rpe' : '_met');
    }

    $met = atividadeEnergiaClamp($met, 1.2, max(20.0, (float) ($profile['met_maximo'] ?? 10.0) * 1.25));
    $calories = $directCalories ?? atividadeEnergiaCaloriasMet($met, $weightKg, $minutes);
    return [
        'active_kcal' => round((float) $calories['active_kcal'], 1),
        'total_kcal' => round((float) $calories['total_kcal'], 1),
        'met' => round($met, 3),
        'method' => $method,
        'confidence' => $confidence,
    ];
}

function atividadeEnergiaPesoParaData(PDO $pdo, string $userId, DateTimeInterface $date): array
{
    if (stridebr_db_table_exists($pdo, 'historico_peso_usuario')) {
        $before = $pdo->prepare('SELECT peso_kg, data_medicao FROM historico_peso_usuario WHERE idusuario = :usuario AND data_medicao <= :data ORDER BY data_medicao DESC LIMIT 1');
        $before->execute([':usuario' => $userId, ':data' => $date->format('Y-m-d')]);
        if ($row = $before->fetch()) return ['weight_kg' => (float) $row['peso_kg'], 'source' => 'historico', 'date' => (string) $row['data_medicao']];
        $after = $pdo->prepare('SELECT peso_kg, data_medicao FROM historico_peso_usuario WHERE idusuario = :usuario AND data_medicao > :data ORDER BY data_medicao ASC LIMIT 1');
        $after->execute([':usuario' => $userId, ':data' => $date->format('Y-m-d')]);
        if ($row = $after->fetch()) return ['weight_kg' => (float) $row['peso_kg'], 'source' => 'historico_posterior', 'date' => (string) $row['data_medicao']];
    }
    $stmt = $pdo->prepare('SELECT pesousuario FROM usuarios WHERE idusuario = :usuario LIMIT 1');
    $stmt->execute([':usuario' => $userId]);
    $weight = $stmt->fetchColumn();
    return is_numeric($weight) && (float) $weight > 0 ? ['weight_kg' => (float) $weight, 'source' => 'perfil', 'date' => null] : ['weight_kg' => null, 'source' => 'ausente', 'date' => null];
}

function atividadeEnergiaPerfil(PDO $pdo, string $modalityId): array
{
    if (!stridebr_db_table_exists($pdo, 'modalidades_energia')) return [];
    $stmt = $pdo->prepare('SELECT * FROM modalidades_energia WHERE idmodalidade = :modalidade LIMIT 1');
    $stmt->execute([':modalidade' => $modalityId]);
    return $stmt->fetch() ?: [];
}

function atividadeEnergiaContexto(PDO $pdo, string $recordId, string $userId): array
{
    $stmt = $pdo->prepare('SELECT ra.idregistro, ra.idmodalidade, ra.data_inicio, ra.data_fim, ra.esforco_percebido, m.slug AS modalidade_slug, m.familia_hub FROM registros_atividade ra JOIN modalidades m ON m.idmodalidade = ra.idmodalidade WHERE ra.idregistro = :registro AND ra.idusuario = :usuario LIMIT 1');
    $stmt->execute([':registro' => $recordId, ':usuario' => $userId]);
    $row = $stmt->fetch();
    if (!$row) return [];
    $durationS = 0.0;
    if (!empty($row['data_inicio']) && !empty($row['data_fim'])) {
        $start = new DateTimeImmutable((string) $row['data_inicio']);
        $end = new DateTimeImmutable((string) $row['data_fim']);
        $durationS = max(0, $end->getTimestamp() - $start->getTimestamp());
    }
    $context = [
        'idmodalidade' => (string) $row['idmodalidade'],
        'modalidade_slug' => (string) ($row['modalidade_slug'] ?? ''),
        'familia_hub' => (string) ($row['familia_hub'] ?? 'other'),
        'data_inicio' => (string) $row['data_inicio'],
        'duration_s' => $durationS,
        'rpe' => $row['esforco_percebido'] !== null ? (int) $row['esforco_percebido'] : null,
        'distance_m' => 0.0,
        'elevation_gain_m' => 0.0,
        'avg_power_w' => null,
        'avg_hr' => null,
        'completed_sets' => 0,
        'unit_count' => 0,
        'session_type' => null,
        'game_format' => null,
        'rounds' => null,
        'elevation_profile' => [],
    ];
    if (stridebr_db_table_exists($pdo, 'rotas_atividade')) {
        $routeStmt = $pdo->prepare('SELECT distancia_metros, ganho_elevacao_m, perfil_elevacao FROM rotas_atividade WHERE idregistro = :registro LIMIT 1');
        $routeStmt->execute([':registro' => $recordId]);
        if ($route = $routeStmt->fetch()) {
            if (is_numeric($route['distancia_metros'] ?? null)) $context['distance_m'] = max(0.0, (float) $route['distancia_metros']);
            if (is_numeric($route['ganho_elevacao_m'] ?? null)) $context['elevation_gain_m'] = max(0.0, (float) $route['ganho_elevacao_m']);
            $decoded = json_decode((string) ($route['perfil_elevacao'] ?? ''), true);
            if (is_array($decoded)) $context['elevation_profile'] = $decoded;
        }
    }
    $fieldStmt = $pdo->prepare("SELECT lower(c.slug) AS slug, va.valor_decimal, va.valor_inteiro, va.valor_texto, EXTRACT(EPOCH FROM va.valor_intervalo) AS intervalo_s, u.simbolo AS unidade_simbolo, o.valor AS opcao_valor FROM valores_atividade va JOIN campos_modelo c ON c.idcampo = va.idcampo LEFT JOIN unidades u ON u.idunidade = c.idunidade LEFT JOIN campos_modelo_opcoes o ON o.idcampo = va.idcampo AND o.idopcao = va.idopcao WHERE va.idregistro = :registro AND lower(c.slug) IN ('distancia','duracao','elevacao','desnivel','potencia','fc-media','fc_media','tipo-sessao','formato-jogo','rounds')");
    $fieldStmt->execute([':registro' => $recordId]);
    $distanceFromFields = 0.0;
    $durationFromFields = 0.0;
    $elevationFromFields = 0.0;
    $powerValues = [];
    $hrValues = [];
    foreach ($fieldStmt->fetchAll() as $field) {
        $slug = (string) $field['slug'];
        $numeric = is_numeric($field['valor_decimal'] ?? null) ? (float) $field['valor_decimal'] : (is_numeric($field['valor_inteiro'] ?? null) ? (float) $field['valor_inteiro'] : null);
        $unit = stridebr_lower(trim((string) ($field['unidade_simbolo'] ?? '')));
        if ($slug === 'distancia' && $numeric !== null) $distanceFromFields += $unit === 'm' ? $numeric : ($unit === 'mi' ? $numeric * 1609.344 : $numeric * 1000);
        elseif ($slug === 'duracao' && is_numeric($field['intervalo_s'] ?? null)) $durationFromFields += max(0.0, (float) $field['intervalo_s']);
        elseif (in_array($slug, ['elevacao','desnivel'], true) && $numeric !== null) $elevationFromFields += $unit === 'km' ? $numeric * 1000 : $numeric;
        elseif ($slug === 'potencia' && $numeric !== null) $powerValues[] = $numeric;
        elseif (in_array($slug, ['fc-media','fc_media'], true) && $numeric !== null) $hrValues[] = $numeric;
        elseif ($slug === 'tipo-sessao') $context['session_type'] = trim((string) ($field['opcao_valor'] ?? $field['valor_texto'] ?? '')) ?: null;
        elseif ($slug === 'formato-jogo') $context['game_format'] = trim((string) ($field['opcao_valor'] ?? $field['valor_texto'] ?? '')) ?: null;
        elseif ($slug === 'rounds' && $numeric !== null) $context['rounds'] = max(0, (int) $numeric);
    }
    if ((float) $context['distance_m'] <= 0 && $distanceFromFields > 0) $context['distance_m'] = $distanceFromFields;
    if ((float) $context['elevation_gain_m'] <= 0 && $elevationFromFields > 0) $context['elevation_gain_m'] = $elevationFromFields;
    if ((float) $context['duration_s'] <= 0 && $durationFromFields > 0) $context['duration_s'] = $durationFromFields;
    if ($powerValues !== []) $context['avg_power_w'] = array_sum($powerValues) / count($powerValues);
    if ($hrValues !== []) $context['avg_hr'] = array_sum($hrValues) / count($hrValues);
    if (stridebr_db_table_exists($pdo, 'unidades_atividade')) {
        $unitStmt = $pdo->prepare('SELECT COUNT(*) FROM unidades_atividade WHERE idregistro = :registro');
        $unitStmt->execute([':registro' => $recordId]);
        $context['unit_count'] = (int) $unitStmt->fetchColumn();
    }
    if (stridebr_db_table_exists($pdo, 'series_exercicio_atividade')) {
        $setStmt = $pdo->prepare('SELECT COUNT(*) FROM series_exercicio_atividade WHERE idregistro = :registro AND concluida = TRUE');
        $setStmt->execute([':registro' => $recordId]);
        $context['completed_sets'] = (int) $setStmt->fetchColumn();
    }
    return $context;
}

function atividadeEnergiaAtualizarRegistro(PDO $pdo, string $recordId, string $userId, ?float $externalCalories = null, ?string $externalSource = null): ?array
{
    if (!stridebr_db_column_exists($pdo, 'registros_atividade', 'calorias_ativas_estimadas')) return null;
    $context = atividadeEnergiaContexto($pdo, $recordId, $userId);
    if ($context === []) return null;
    $profile = atividadeEnergiaPerfil($pdo, (string) $context['idmodalidade']);
    $date = new DateTimeImmutable((string) $context['data_inicio']);
    $weight = atividadeEnergiaPesoParaData($pdo, $userId, $date);
    $estimate = is_numeric($weight['weight_kg'] ?? null) && $profile !== [] ? atividadeEnergiaEstimar($context, $profile, (float) $weight['weight_kg']) : null;
    $sets = ['data_calorias_atualizacao = NOW()'];
    $params = [':registro' => $recordId, ':usuario' => $userId];
    if ($externalCalories !== null && is_finite($externalCalories) && $externalCalories >= 0) {
        $sets[] = 'calorias_externas = :externas';
        $sets[] = 'calorias_fonte = :fonte';
        $params[':externas'] = round($externalCalories, 2);
        $params[':fonte'] = trim((string) $externalSource) !== '' ? substr(trim((string) $externalSource), 0, 40) : 'externa';
    }
    if ($estimate !== null) {
        $sets[] = 'calorias_ativas_estimadas = :ativas';
        $sets[] = 'calorias_totais_estimadas = :totais';
        $sets[] = 'calorias_confianca = :confianca';
        $sets[] = 'calorias_metodo = :metodo';
        $sets[] = 'met_efetivo = :met';
        $sets[] = 'peso_calculo_kg = :peso';
        $params[':ativas'] = $estimate['active_kcal'];
        $params[':totais'] = $estimate['total_kcal'];
        $params[':confianca'] = $estimate['confidence'];
        $params[':metodo'] = $estimate['method'];
        $params[':met'] = $estimate['met'];
        $params[':peso'] = round((float) $weight['weight_kg'], 2);
    }
    $stmt = $pdo->prepare('UPDATE registros_atividade SET ' . implode(', ', $sets) . ' WHERE idregistro = :registro AND idusuario = :usuario');
    $stmt->execute($params);
    return ['estimate' => $estimate, 'weight' => $weight, 'context' => $context];
}
