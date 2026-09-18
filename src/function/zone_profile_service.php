<?php

declare(strict_types=1);

/** Return only a local, header-safe destination for the contextual Zones flow. */
function zoneProfileReturnTo(string $value): string
{
    return stridebr_safe_redirect(trim($value), '');
}

function zoneProfileNormalizeType(string $type): string
{
    $type = strtolower(trim($type));
    if (!in_array($type, ['heart_rate', 'pace'], true)) throw new InvalidArgumentException('profile_type precisa ser heart_rate ou pace.');
    return $type;
}

function zoneProfileUnitForType(string $type): string
{
    return $type === 'heart_rate' ? 'bpm' : 's_per_km';
}

function zoneProfileSportId(PDO $pdo, string $userId, mixed $sport): ?string
{
    $sport = trim((string) $sport);
    if ($sport === '') return null;
    $stmt = $pdo->prepare('SELECT idmodalidade FROM modalidades WHERE ativo=TRUE AND (idmodalidade=:sport OR slug=:sport) AND (idusuario IS NULL OR idusuario=:user) LIMIT 1');
    $stmt->execute([':sport' => $sport, ':user' => $userId]);
    $id = $stmt->fetchColumn();
    if (!is_string($id) || $id === '') throw new InvalidArgumentException('Modalidade do perfil de zonas não encontrada.');
    return $id;
}

function zoneProfileValidateRanges(string $type, array $ranges): array
{
    if ($ranges === [] || !array_is_list($ranges) || count($ranges) > 20) throw new InvalidArgumentException('Informe entre 1 e 20 zonas.');
    $normalized = [];
    $previousMax = null;
    foreach ($ranges as $index => $range) {
        if (!is_array($range)) throw new InvalidArgumentException('Cada zona precisa ser um objeto.');
        $code = strtoupper(trim((string) ($range['code'] ?? '')));
        if ($code === '' || strlen($code) > 20) throw new InvalidArgumentException('Cada zona precisa de code com até 20 caracteres.');
        $label = trim((string) ($range['label'] ?? ''));
        if (strlen($label) > 80) throw new InvalidArgumentException('label da zona é muito longo.');
        $min = array_key_exists('min', $range) && $range['min'] !== null ? activityStreamFinite($range['min']) : null;
        $max = array_key_exists('max', $range) && $range['max'] !== null ? activityStreamFinite($range['max']) : null;
        if (array_key_exists('min', $range) && $range['min'] !== null && $min === null) throw new InvalidArgumentException('min da zona precisa ser numérico.');
        if (array_key_exists('max', $range) && $range['max'] !== null && $max === null) throw new InvalidArgumentException('max da zona precisa ser numérico.');
        if ($min !== null && $min < 0) throw new InvalidArgumentException('min da zona precisa ser não negativo.');
        if ($max !== null && $max <= 0) throw new InvalidArgumentException('max da zona precisa ser positivo.');
        if ($min !== null && $max !== null && $max <= $min) throw new InvalidArgumentException('max da zona precisa ser maior que min.');
        if ($index > 0 && $previousMax !== null && $min !== null && abs($min - $previousMax) > 0.001) throw new InvalidArgumentException('As zonas precisam ser contíguas, sem gaps ou sobreposição.');
        if ($index > 0 && $min === null) throw new InvalidArgumentException('Somente a primeira zona pode ter min nulo.');
        if ($index < count($ranges) - 1 && $max === null) throw new InvalidArgumentException('Somente a última zona pode ter max nulo.');
        if ($type === 'heart_rate') {
            if (($min !== null && $min < 20) || ($max !== null && $max > 260)) throw new InvalidArgumentException('Zonas de FC precisam ficar entre 20 e 260 bpm.');
        }
        $normalized[] = ['order' => $index + 1, 'code' => $code, 'label' => $label !== '' ? $label : null, 'min' => $min, 'max' => $max];
        $previousMax = $max;
    }
    return $normalized;
}

function zoneProfileSave(PDO $pdo, string $userId, array $payload, ?string $profileId = null): array
{
    $type = zoneProfileNormalizeType((string) ($payload['profile_type'] ?? ''));
    $name = trim((string) ($payload['name'] ?? ''));
    if ($name === '' || strlen($name) > 80) throw new InvalidArgumentException('name precisa ter entre 1 e 80 caracteres.');
    $sportId = zoneProfileSportId($pdo, $userId, $payload['sport'] ?? $payload['sport_id'] ?? null);
    $isDefault = !empty($payload['is_default']);
    $ranges = zoneProfileValidateRanges($type, is_array($payload['zones'] ?? null) ? $payload['zones'] : []);
    if ($profileId !== null) {
        $owner = $pdo->prepare('SELECT 1 FROM zone_profiles WHERE idprofile=:id AND idusuario=:user LIMIT 1');
        $owner->execute([':id' => $profileId, ':user' => $userId]);
        if (!$owner->fetchColumn()) throw new InvalidArgumentException('Perfil de zonas não encontrado.');
    }
    $owns = !$pdo->inTransaction();
    if ($owns) $pdo->beginTransaction();
    try {
        if ($isDefault) {
            if ($sportId === null) {
                $reset = $pdo->prepare('UPDATE zone_profiles SET is_default=FALSE,data_atualizacao=NOW() WHERE idusuario=:user AND profile_type=:type AND idmodalidade IS NULL');
                $reset->execute([':user' => $userId, ':type' => $type]);
            } else {
                $reset = $pdo->prepare('UPDATE zone_profiles SET is_default=FALSE,data_atualizacao=NOW() WHERE idusuario=:user AND profile_type=:type AND idmodalidade=:sport');
                $reset->execute([':user' => $userId, ':type' => $type, ':sport' => $sportId]);
            }
        }
        $profileId ??= activityStreamId();
        $stmt = $pdo->prepare("INSERT INTO zone_profiles (idprofile,idusuario,idmodalidade,profile_type,name,unit,is_default,data_atualizacao) VALUES (:id,:user,:sport,:type,:name,:unit,:default,NOW()) ON CONFLICT (idprofile) DO UPDATE SET idmodalidade=EXCLUDED.idmodalidade,profile_type=EXCLUDED.profile_type,name=EXCLUDED.name,unit=EXCLUDED.unit,is_default=EXCLUDED.is_default,data_atualizacao=NOW()");
        $stmt->execute([':id' => $profileId, ':user' => $userId, ':sport' => $sportId, ':type' => $type, ':name' => $name, ':unit' => zoneProfileUnitForType($type), ':default' => $isDefault ? 'true' : 'false']);
        $pdo->prepare('DELETE FROM zone_profile_ranges WHERE idprofile=:id')->execute([':id' => $profileId]);
        $insert = $pdo->prepare('INSERT INTO zone_profile_ranges (idrange,idprofile,zone_order,code,label,min_value,max_value) VALUES (:id,:profile,:ord,:code,:label,:min,:max)');
        foreach ($ranges as $range) $insert->execute([':id' => activityStreamId(), ':profile' => $profileId, ':ord' => $range['order'], ':code' => $range['code'], ':label' => $range['label'], ':min' => $range['min'], ':max' => $range['max']]);
        $pdo->prepare("DELETE FROM activity_analysis_cache a USING registros_atividade ra WHERE a.idregistro=ra.idregistro AND ra.idusuario=:user")->execute([':user' => $userId]);
        if ($owns) $pdo->commit();
    } catch (Throwable $e) {
        if ($owns && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return zoneProfileGet($pdo, $userId, $profileId);
}

function zoneProfileGet(PDO $pdo, string $userId, string $profileId): array
{
    $stmt = $pdo->prepare('SELECT z.*,m.slug AS sport_slug,m.nome AS sport_name FROM zone_profiles z LEFT JOIN modalidades m ON m.idmodalidade=z.idmodalidade WHERE z.idprofile=:id AND z.idusuario=:user LIMIT 1');
    $stmt->execute([':id' => $profileId, ':user' => $userId]);
    $row = $stmt->fetch();
    if (!$row) return [];
    $ranges = $pdo->prepare('SELECT idrange,zone_order,code,label,min_value,max_value FROM zone_profile_ranges WHERE idprofile=:id ORDER BY zone_order');
    $ranges->execute([':id' => $profileId]);
    return [
        'id' => (string) $row['idprofile'], 'profile_type' => (string) $row['profile_type'], 'name' => (string) $row['name'], 'unit' => (string) $row['unit'], 'is_default' => activityStreamBool($row['is_default']),
        'sport' => $row['idmodalidade'] !== null ? ['id' => (string) $row['idmodalidade'], 'slug' => (string) $row['sport_slug'], 'name' => (string) $row['sport_name']] : null,
        'zones' => array_map(static fn(array $range): array => ['id' => (string) $range['idrange'], 'order' => (int) $range['zone_order'], 'code' => (string) $range['code'], 'label' => $range['label'] !== null ? (string) $range['label'] : null, 'min' => $range['min_value'] !== null ? (float) $range['min_value'] : null, 'max' => $range['max_value'] !== null ? (float) $range['max_value'] : null], $ranges->fetchAll()),
        'created_at' => activityStreamIso((string) $row['data_criacao']), 'updated_at' => activityStreamIso((string) $row['data_atualizacao']),
    ];
}

function zoneProfileList(PDO $pdo, string $userId, array $query = []): array
{
    $where = ['z.idusuario=:user'];
    $params = [':user' => $userId];
    if (trim((string) ($query['profile_type'] ?? '')) !== '') {
        $where[] = 'z.profile_type=:type';
        $params[':type'] = zoneProfileNormalizeType((string) $query['profile_type']);
    }
    $stmt = $pdo->prepare('SELECT z.idprofile FROM zone_profiles z WHERE ' . implode(' AND ', $where) . ' ORDER BY z.is_default DESC,z.data_atualizacao DESC');
    $stmt->execute($params);
    return array_values(array_map(static fn(string $id): array => zoneProfileGet($pdo, $userId, $id), array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
}

function zoneProfileDelete(PDO $pdo, string $userId, string $profileId): void
{
    $stmt = $pdo->prepare('DELETE FROM zone_profiles WHERE idprofile=:id AND idusuario=:user');
    $stmt->execute([':id' => $profileId, ':user' => $userId]);
    if ($stmt->rowCount() === 0) throw new InvalidArgumentException('Perfil de zonas não encontrado.');
    $pdo->prepare("DELETE FROM activity_analysis_cache a USING registros_atividade ra WHERE a.idregistro=ra.idregistro AND ra.idusuario=:user")->execute([':user' => $userId]);
}

function zoneProfileResolve(PDO $pdo, string $userId, string $type, string $sportId): ?array
{
    $type = zoneProfileNormalizeType($type);
    $stmt = $pdo->prepare("SELECT idprofile FROM zone_profiles WHERE idusuario=:user AND profile_type=:type AND is_default=TRUE AND (idmodalidade=:sport OR idmodalidade IS NULL) ORDER BY CASE WHEN idmodalidade=:sport2 THEN 0 ELSE 1 END,data_atualizacao DESC LIMIT 1");
    $stmt->execute([':user' => $userId, ':type' => $type, ':sport' => $sportId, ':sport2' => $sportId]);
    $id = $stmt->fetchColumn();
    return is_string($id) && $id !== '' ? zoneProfileGet($pdo, $userId, $id) : null;
}

function zoneProfileDistribution(array $samples, array $profile, string $field, string $mode = 'time'): array
{
    $zones = $profile['zones'] ?? [];
    if (!is_array($zones) || $zones === []) return [];
    $totals = [];
    foreach ($zones as $zone) $totals[$zone['code']] = ['code' => $zone['code'], 'label' => $zone['label'], 'time_s' => 0.0, 'distance_m' => 0.0, 'percentage' => 0.0];
    $observedTime = 0.0;
    $observedDistance = 0.0;
    for ($i = 1; $i < count($samples); $i++) {
        $previous = $samples[$i - 1];
        $current = $samples[$i];
        if (!is_numeric($previous[$field] ?? null) || !is_numeric($current[$field] ?? null)) continue;
        $value = ((float) $previous[$field] + (float) $current[$field]) / 2.0;
        $zoneCode = null;
        foreach ($zones as $zone) {
            $min = $zone['min'];
            $max = $zone['max'];
            if (($min === null || $value >= (float) $min) && ($max === null || $value < (float) $max)) { $zoneCode = $zone['code']; break; }
        }
        if ($zoneCode === null) continue;
        $timeDelta = max(0.0, ((float) ($current['moving_ms'] ?? $current['elapsed_ms']) - (float) ($previous['moving_ms'] ?? $previous['elapsed_ms'])) / 1000.0);
        $distanceDelta = is_numeric($previous['distance_m'] ?? null) && is_numeric($current['distance_m'] ?? null) ? max(0.0, (float) $current['distance_m'] - (float) $previous['distance_m']) : 0.0;
        if ($timeDelta > 30) continue;
        $totals[$zoneCode]['time_s'] += $timeDelta;
        $totals[$zoneCode]['distance_m'] += $distanceDelta;
        $observedTime += $timeDelta;
        $observedDistance += $distanceDelta;
    }
    foreach ($totals as &$zone) {
        $denominator = $mode === 'distance' ? $observedDistance : $observedTime;
        $numerator = $mode === 'distance' ? $zone['distance_m'] : $zone['time_s'];
        $zone['percentage'] = $denominator > 0 ? $numerator / $denominator * 100.0 : 0.0;
    }
    unset($zone);
    return ['profile' => ['id' => $profile['id'], 'name' => $profile['name'], 'unit' => $profile['unit']], 'observed_time_s' => $observedTime, 'observed_distance_m' => $observedDistance, 'zones' => array_values($totals)];
}
